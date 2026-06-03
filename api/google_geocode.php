<?php
include_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

// Keep this endpoint as a compatibility shim for older clients that POST JSON.
// Newer clients should call geocode.php directly.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $address = isset($input['address']) ? trim((string)$input['address']) : '';
    if ($address === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'address parameter required']);
        exit;
    }
    $_GET['address'] = $address;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    require __DIR__ . '/geocode.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require __DIR__ . '/geocode.php';
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
