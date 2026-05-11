<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

const GOOGLE_KEY_FALLBACK_PRIMARY = 'AIzaSyDzWWzAZ6-PxDds7RX3FVeaDa22RqIr8HU';
const GOOGLE_KEY_FALLBACK_SECONDARY = 'AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8';

$address = isset($_GET['address']) ? trim($_GET['address']) : '';
if ($address === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'address parameter required']);
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

$apiKeys = get_google_api_keys();
if (count($apiKeys) === 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Google geocoding API key is not configured on server']);
    exit;
}

$status = '';
$location = null;
$errorMessage = '';
$usedSource = '';
foreach ($apiKeys as $candidate) {
    $googleUrl = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . urlencode($candidate['key']);
    $googleJson = @file_get_contents($googleUrl);
    if ($googleJson === false) {
        continue;
    }

    $googleData = json_decode($googleJson, true);
    $status = trim((string)($googleData['status'] ?? ''));
    $location = $googleData['results'][0]['geometry']['location'] ?? null;
    $errorMessage = trim((string)($googleData['error_message'] ?? ''));
    $usedSource = (string)$candidate['source'];

    if ($status === 'OK' && is_array($location)) {
        break;
    }
}

if ($status !== 'OK' || !is_array($location)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Google geocoder returned no result',
        'status' => $status,
        'errorMessage' => $errorMessage,
        'keySource' => $usedSource,
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'source' => 'google',
    'keySource' => $usedSource,
    'lat' => (float)$location['lat'],
    'lng' => (float)$location['lng'],
]);
