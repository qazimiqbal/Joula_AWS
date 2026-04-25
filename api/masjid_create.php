<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit;
}

function respond($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function get_authenticated_user_id($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;

    $token = substr($authHeader, 7);
    $stmt = mysqli_prepare($con,
        "SELECT id
         FROM Login_user_AWS
         WHERE auth_token = ? AND status = 'true' LIMIT 1");

    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $userId = null;
    mysqli_stmt_bind_result($stmt, $userId);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$found || !$userId) return null;
    return intval($userId);
}

include('db.php');

$createdBy = get_authenticated_user_id($con);
if (!$createdBy) {
    respond(401, array('success' => false, 'message' => 'Unauthorized'));
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$name = isset($input['name']) ? trim($input['name']) : '';
$houseNo = isset($input['houseNo']) ? trim($input['houseNo']) : '';
$aptNo = isset($input['aptNo']) ? trim($input['aptNo']) : '';
$streetName = isset($input['streetName']) ? trim($input['streetName']) : '';
$city = isset($input['city']) ? trim($input['city']) : '';
$state = isset($input['state']) ? trim($input['state']) : '';
$zip = isset($input['zip']) ? trim($input['zip']) : '';

if ($name === '' || $houseNo === '' || $streetName === '' || $city === '' || $state === '' || $zip === '') {
    respond(400, array('success' => false, 'message' => 'Name, houseNo, streetName, city, state, and zip are required'));
}

$limitStmt = mysqli_prepare($con, 'SELECT COUNT(*) FROM Masjids_AWS WHERE Created_by = ?');
if (!$limitStmt) {
    respond(500, array('success' => false, 'message' => 'Failed to check masjid limit'));
}
mysqli_stmt_bind_param($limitStmt, 'i', $createdBy);
mysqli_stmt_execute($limitStmt);
$count = 0;
mysqli_stmt_bind_result($limitStmt, $count);
mysqli_stmt_fetch($limitStmt);
mysqli_stmt_close($limitStmt);

if (intval($count) >= 5) {
    respond(400, array('success' => false, 'message' => 'Maximum 5 masjids allowed per user at this time'));
}

$dupStmt = mysqli_prepare(
    $con,
    'SELECT ID FROM Masjids_AWS WHERE Name = ? AND H_No = ? AND St_Name = ? AND City = ? AND State = ? AND Zip = ? AND Created_by = ? LIMIT 1'
);
if (!$dupStmt) {
    respond(500, array('success' => false, 'message' => 'Failed to check duplicates'));
}
mysqli_stmt_bind_param($dupStmt, 'ssssssi', $name, $houseNo, $streetName, $city, $state, $zip, $createdBy);
mysqli_stmt_execute($dupStmt);
$existingId = null;
mysqli_stmt_bind_result($dupStmt, $existingId);
$exists = mysqli_stmt_fetch($dupStmt);
mysqli_stmt_close($dupStmt);

if ($exists) {
    respond(409, array('success' => false, 'message' => 'This masjid already exists for your account'));
}

$stmt = mysqli_prepare(
    $con,
    'INSERT INTO Masjids_AWS (Name, H_No, Apt_No, St_Name, City, State, Zip, Created_by, `Clear`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)'
);
if (!$stmt) {
    respond(500, array('success' => false, 'message' => 'Failed to prepare insert query: ' . mysqli_error($con)));
}

mysqli_stmt_bind_param($stmt, 'sssssssi', $name, $houseNo, $aptNo, $streetName, $city, $state, $zip, $createdBy);
if (!mysqli_stmt_execute($stmt)) {
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    respond(500, array('success' => false, 'message' => 'Failed to create masjid: ' . $err));
}

$newId = mysqli_insert_id($con);
mysqli_stmt_close($stmt);

respond(200, array('success' => true, 'message' => 'Masjid created and pending approval', 'id' => intval($newId)));
?>