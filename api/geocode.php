<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$address = isset($_GET['address']) ? trim($_GET['address']) : '';
if ($address === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'address parameter required']);
    exit;
}

$lat = null;
$lng = null;

// 1) US Census Geocoder (best for US addresses)
$censusUrl = 'https://geocoding.geo.census.gov/geocoder/locations/onelineaddress?address='
    . urlencode($address)
    . '&benchmark=Public_AR_Current&format=json';

$censusJson = @file_get_contents($censusUrl);
if ($censusJson !== false) {
    $censusData = json_decode($censusJson, true);
    $match = $censusData['result']['addressMatches'][0] ?? null;
    if ($match) {
        $lat = (float) $match['coordinates']['y'];
        $lng = (float) $match['coordinates']['x'];
    }
}

// 2) Nominatim fallback
if ($lat === null || $lng === null) {
    $nomUrl = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($address);
    $opts = ['http' => ['header' => "User-Agent: MyJoula/1.0\r\n"]];
    $nomJson = @file_get_contents($nomUrl, false, stream_context_create($opts));
    if ($nomJson !== false) {
        $nomData = json_decode($nomJson, true);
        if (!empty($nomData)) {
            $lat = (float) $nomData[0]['lat'];
            $lng = (float) $nomData[0]['lon'];
        }
    }
}

if ($lat === null || $lng === null) {
    echo json_encode(['success' => false, 'message' => 'No coordinates found']);
    exit;
}

echo json_encode(['success' => true, 'lat' => $lat, 'lng' => $lng]);
