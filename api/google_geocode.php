<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// For testing only: set your Google Maps API key here
putenv('GOOGLE_MAPS_API_KEY=AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8');

// Google Geocoding API proxy
header('Content-Type: application/json');

$debugLog = __DIR__ . '/masjid_geocode_debug.log';
file_put_contents($debugLog, date('c') . " - Script started\n", FILE_APPEND);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        file_put_contents($debugLog, date('c') . " - Method not allowed\n", FILE_APPEND);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $address = isset($input['address']) ? trim($input['address']) : '';
    $apiKey = getenv('GOOGLE_MAPS_API_KEY');

    file_put_contents($debugLog, date('c') . "\nINPUT: " . print_r($input, true) . "\nADDRESS: $address\nAPI_KEY: $apiKey\n", FILE_APPEND);

    if (!$address || !$apiKey) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing address or API key']);
        file_put_contents($debugLog, date('c') . " - Missing address or API key\n", FILE_APPEND);
        exit;
    }

    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $apiKey;
    file_put_contents($debugLog, date('c') . " - Requesting URL: $url\n", FILE_APPEND);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    file_put_contents($debugLog, date('c') . " - CURL response: $response\nCURL error: $curlErr\n", FILE_APPEND);

    $data = json_decode($response, true);
    if ($data && isset($data['results'][0]['geometry']['location'])) {
        $loc = $data['results'][0]['geometry']['location'];
        echo json_encode([
            'success' => true,
            'lat' => $loc['lat'],
            'lng' => $loc['lng'],
            'raw' => $data['results'][0]
        ]);
        file_put_contents($debugLog, date('c') . " - Success: " . print_r($loc, true) . "\n", FILE_APPEND);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'No results', 'raw' => $data]);
        file_put_contents($debugLog, date('c') . " - No results: " . print_r($data, true) . "\n", FILE_APPEND);
        exit;
    }
} catch (Exception $e) {
    file_put_contents($debugLog, date('c') . " - Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error', 'error' => $e->getMessage()]);
    exit;
}
