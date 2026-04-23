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
mysqli_select_db($con, $db);

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

if ($name === '' || $houseNo === '' || $streetName === '' || $city === '' || $state === '' || $zip === '' || $locality === '') {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Name, house number, street name, city, state, zip, and locality are required'));
    exit;
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

$stmt = mysqli_prepare(
    $con,
    'INSERT INTO Addresses_AWS (Name, Halaqa, H_No, Apt_No, St_Name, City, State, Zip, Verified, Masjid, Comments, Last_Visit, Coordinates, Locality, Area, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Failed to prepare insert query'));
    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    'ssssssssssssssss',
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
    $status
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
