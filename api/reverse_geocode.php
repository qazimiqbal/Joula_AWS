<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$lat = isset($_GET['lat']) ? trim((string)$_GET['lat']) : '';
$lng = isset($_GET['lng']) ? trim((string)$_GET['lng']) : '';

if ($lat === '' || $lng === '' || !is_numeric($lat) || !is_numeric($lng)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'lat and lng parameters are required']);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/reverse?format=json&addressdetails=1&lat='
    . urlencode($lat)
    . '&lon=' . urlencode($lng);

$opts = ['http' => ['header' => "User-Agent: MyJoula/1.0\r\n"]];
$json = @file_get_contents($url, false, stream_context_create($opts));

if ($json === false) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Reverse geocoder request failed']);
    exit;
}

$data = json_decode($json, true);
$address = $data['address'] ?? [];

$city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['hamlet'] ?? '';
$houseNo = $address['house_number'] ?? '';

if ($houseNo === '' && !empty($data['display_name'])) {
    $parts = explode(',', (string)$data['display_name']);
    $first = trim($parts[0] ?? '');
    if ($first !== '' && preg_match('/\d/', $first) === 1) {
        if (preg_match('/^([0-9]+[A-Za-z0-9\-\/]*)\b/', $first, $m) === 1) {
            $houseNo = $m[1];
        }
    }
}

echo json_encode([
    'success' => true,
    'houseNo' => $houseNo,
    'streetName' => $address['road'] ?? '',
    'city' => $city,
    'state' => $address['state'] ?? '',
    'zip' => $address['postcode'] ?? '',
]);
