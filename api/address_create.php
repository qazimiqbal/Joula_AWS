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

include('db.php');

// Resolve uploaded_by from Bearer token (optional — NULL if not authenticated)
$uploadedBy = null;
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
    $stmtU = mysqli_prepare($con, "SELECT id FROM Login_user_AWS WHERE auth_token = ? AND status = 'true' LIMIT 1");
    if ($stmtU) {
        mysqli_stmt_bind_param($stmtU, 's', $token);
        mysqli_stmt_execute($stmtU);
        $tmpId = null;
        mysqli_stmt_bind_result($stmtU, $tmpId);
        if (mysqli_stmt_fetch($stmtU)) $uploadedBy = intval($tmpId);
        mysqli_stmt_close($stmtU);
    }
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$name = isset($input['name']) ? trim($input['name']) : '';
$halaqa = isset($input['halaqa']) ? trim($input['halaqa']) : 'Atlanta East';
$houseNo = isset($input['houseNo']) ? trim($input['houseNo']) : '';
$aptNo = isset($input['aptNo']) ? trim($input['aptNo']) : '';
$streetName = isset($input['streetName']) ? trim($input['streetName']) : '';
$city = isset($input['city']) ? trim($input['city']) : '';
$state = isset($input['state']) ? trim($input['state']) : '';
$zip = isset($input['zip']) ? trim($input['zip']) : '';
$locality = isset($input['locality']) ? trim($input['locality']) : '';
$verified = isset($input['verified']) ? trim($input['verified']) : 'N';
$masjid = isset($input['masjid']) ? trim($input['masjid']) : '';
$lastVisit = isset($input['lastVisit']) ? trim($input['lastVisit']) : date('Y-m-d');
$comments = isset($input['comments']) ? trim($input['comments']) : '';
$latitude = isset($input['latitude']) ? trim((string)$input['latitude']) : '';
$longitude = isset($input['longitude']) ? trim((string)$input['longitude']) : '';

$hasCoordinates = ($latitude !== '' && $longitude !== '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Name is required'));
    exit;
}

if (!$hasCoordinates && ($houseNo === '' || $streetName === '' || $city === '' || $state === '' || $zip === '' || $locality === '')) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Provide full address fields, or include coordinates from current location'));
    exit;
}

if ($hasCoordinates) {
    if ($houseNo === '') $houseNo = 'GPS';
    if ($streetName === '') $streetName = 'Current Location';
    if ($city === '') $city = 'Unknown';
    if ($state === '') $state = 'GA';
    if ($zip === '') $zip = '00000';
    if ($locality === '') $locality = 'Unassigned';
}

$checkStmt = mysqli_prepare($con, 'SELECT ID FROM Addresses_AWS WHERE Name = ? AND H_No = ? LIMIT 1');
mysqli_stmt_bind_param($checkStmt, 'ss', $name, $houseNo);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_bind_result($checkStmt, $existingId);
$exists = mysqli_stmt_fetch($checkStmt);
mysqli_stmt_close($checkStmt);

if ($exists) {
    http_response_code(409);
    echo json_encode(array('success' => false, 'message' => 'The address with this name and house number already exists'));
    exit;
}

$coordinates = '';
if ($latitude !== '' && $longitude !== '') {
    $coordinates = $latitude . ',' . $longitude;
}

$area = 'unclassified';
$status = 'Muslim';
$clear = 0;

$stmt = mysqli_prepare(
    $con,
    'INSERT INTO Addresses_AWS (Name, Halaqa, H_No, Apt_No, St_Name, City, State, Zip, Verified, Masjid, Comments, Last_Visit, Coordinates, Locality, Area, Status, `Clear`, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Failed to prepare insert query'));
    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    'ssssssssssssssssii',
    $name,
    $halaqa,
    $houseNo,
    $aptNo,
    $streetName,
    $city,
    $state,
    $zip,
    $verified,
    $masjid,
    $comments,
    $lastVisit,
    $coordinates,
    $locality,
    $area,
    $status,
    $clear,
    $uploadedBy
);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Failed to create address'));
    exit;
}

mysqli_stmt_close($stmt);

echo json_encode(array('success' => true, 'message' => 'Address created successfully'));
?>
