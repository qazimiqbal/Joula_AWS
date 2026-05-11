<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

const GOOGLE_KEY_FALLBACK_PRIMARY = 'AIzaSyDzWWzAZ6-PxDds7RX3FVeaDa22RqIr8HU';
const GOOGLE_KEY_FALLBACK_SECONDARY = 'AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8';

$lat = isset($_GET['lat']) ? trim((string)$_GET['lat']) : '';
$lng = isset($_GET['lng']) ? trim((string)$_GET['lng']) : '';

if ($lat === '' || $lng === '' || !is_numeric($lat) || !is_numeric($lng)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'lat and lng parameters are required']);
    exit;
}

function get_google_api_keys()
{
    $candidates = [
        ['key' => getenv('GOOGLE_GEOCODING_API_KEY') ?: '', 'source' => 'GOOGLE_GEOCODING_API_KEY'],
        ['key' => getenv('GOOGLE_MAPS_API_KEY') ?: '', 'source' => 'GOOGLE_MAPS_API_KEY'],
        ['key' => getenv('VITE_GOOGLE_MAPS_API_KEY') ?: '', 'source' => 'VITE_GOOGLE_MAPS_API_KEY'],
        ['key' => isset($_ENV['GOOGLE_GEOCODING_API_KEY']) ? (string)$_ENV['GOOGLE_GEOCODING_API_KEY'] : '', 'source' => '$_ENV GOOGLE_GEOCODING_API_KEY'],
        ['key' => isset($_ENV['GOOGLE_MAPS_API_KEY']) ? (string)$_ENV['GOOGLE_MAPS_API_KEY'] : '', 'source' => '$_ENV GOOGLE_MAPS_API_KEY'],
        ['key' => isset($_ENV['VITE_GOOGLE_MAPS_API_KEY']) ? (string)$_ENV['VITE_GOOGLE_MAPS_API_KEY'] : '', 'source' => '$_ENV VITE_GOOGLE_MAPS_API_KEY'],
        ['key' => isset($_SERVER['GOOGLE_GEOCODING_API_KEY']) ? (string)$_SERVER['GOOGLE_GEOCODING_API_KEY'] : '', 'source' => '$_SERVER GOOGLE_GEOCODING_API_KEY'],
        ['key' => isset($_SERVER['GOOGLE_MAPS_API_KEY']) ? (string)$_SERVER['GOOGLE_MAPS_API_KEY'] : '', 'source' => '$_SERVER GOOGLE_MAPS_API_KEY'],
        ['key' => isset($_SERVER['VITE_GOOGLE_MAPS_API_KEY']) ? (string)$_SERVER['VITE_GOOGLE_MAPS_API_KEY'] : '', 'source' => '$_SERVER VITE_GOOGLE_MAPS_API_KEY'],
        ['key' => GOOGLE_KEY_FALLBACK_PRIMARY, 'source' => 'hardcoded fallback primary'],
        ['key' => GOOGLE_KEY_FALLBACK_SECONDARY, 'source' => 'hardcoded fallback secondary'],
    ];

    $out = [];
    $seen = [];
    foreach ($candidates as $candidate) {
        $trimmed = trim((string)$candidate['key']);
        if ($trimmed === '' || isset($seen[$trimmed])) continue;
        $seen[$trimmed] = true;
        $out[] = ['key' => $trimmed, 'source' => $candidate['source']];
    }
    return $out;
}

function pick_google_component($components, $wantedType)
{
    if (!is_array($components)) return '';
    foreach ($components as $component) {
        if (!is_array($component)) continue;
        $types = $component['types'] ?? [];
        if (is_array($types) && in_array($wantedType, $types, true)) {
            return trim((string)($component['long_name'] ?? ''));
        }
    }
    return '';
}

function extract_house_and_street_from_part($part)
{
    $text = trim((string)$part);
    if ($text === '') {
        return ['', ''];
    }

    if (preg_match('/\b([0-9]+[A-Za-z0-9\-\/]*)\b\s+(.*)$/', $text, $m) === 1) {
        $house = trim($m[1]);
        $street = trim($m[2]);
        return [$house, $street];
    }

    return ['', ''];
}

// 1) Prefer Google reverse geocoding when API key is available.
$apiKeys = get_google_api_keys();
if (count($apiKeys) === 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Google geocoding API key is not configured on server']);
    exit;
}

// Try each key until one succeeds.
$googleData = null;
$status = '';
$result = null;
$errorMessage = '';
$usedSource = '';
foreach ($apiKeys as $candidate) {
    $googleUrl = 'https://maps.googleapis.com/maps/api/geocode/json?latlng='
        . urlencode($lat . ',' . $lng)
        . '&key=' . urlencode($candidate['key']);

    $googleJson = @file_get_contents($googleUrl);
    if ($googleJson === false) {
        continue;
    }

    $googleData = json_decode($googleJson, true);
    $status = trim((string)($googleData['status'] ?? ''));
    $result = $googleData['results'][0] ?? null;
    $errorMessage = trim((string)($googleData['error_message'] ?? ''));
    $usedSource = (string)$candidate['source'];

    if ($status === 'OK' && is_array($result)) {
        break;
    }
}

if ($status !== 'OK' || !is_array($result)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Google reverse geocoder returned no result',
        'status' => $status,
        'errorMessage' => $errorMessage,
        'keySource' => $usedSource,
    ]);
    exit;
}

$components = $result['address_components'] ?? [];

$houseNo = pick_google_component($components, 'street_number');
$streetName = pick_google_component($components, 'route');
$city = pick_google_component($components, 'locality');
if ($city === '') {
    $city = pick_google_component($components, 'sublocality');
}
if ($city === '') {
    $city = pick_google_component($components, 'administrative_area_level_2');
}
$state = pick_google_component($components, 'administrative_area_level_1');
$zip = pick_google_component($components, 'postal_code');

if ($houseNo === '' || $streetName === '') {
    $formatted = trim((string)($result['formatted_address'] ?? ''));
    $firstPart = trim((string)explode(',', $formatted)[0]);
    [$candidateHouse, $candidateStreet] = extract_house_and_street_from_part($firstPart);
    if ($houseNo === '' && $candidateHouse !== '') {
        $houseNo = $candidateHouse;
    }
    if ($streetName === '' && $candidateStreet !== '') {
        $streetName = $candidateStreet;
    }
}

echo json_encode([
    'success' => true,
    'source' => 'google',
    'keySource' => $usedSource,
    'houseNo' => $houseNo,
    'streetName' => $streetName,
    'city' => $city,
    'state' => $state,
    'zip' => $zip,
]);
