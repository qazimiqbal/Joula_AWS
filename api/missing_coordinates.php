<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

include('db.php');

// ---------------------------------------------------------------
// Auth helper — returns user info or null
// ---------------------------------------------------------------
function get_auth_user_mc($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);

    $stmt = mysqli_prepare($con,
        "SELECT id, Permissions FROM Login_user_AWS
         WHERE auth_token = ? AND status = 'true' LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $userId = null;
    $perms  = null;
    mysqli_stmt_bind_result($stmt, $userId, $perms);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$found || !$userId) return null;
    // permission_to_level: '' or NULL => 0, 'Staff' => 1, 'Admin' => 3, numeric => intval
    $level = 0;
    if (is_numeric($perms)) {
        $level = intval($perms);
    } elseif (stripos((string)$perms, 'admin') !== false) {
        $level = 3;
    } elseif (stripos((string)$perms, 'staff') !== false) {
        $level = 1;
    }
    return ['id' => intval($userId), 'permission_level' => $level];
}

// Require authentication
$authUser = get_auth_user_mc($con);
if (!$authUser) {
    respond(401, ['success' => false, 'message' => 'Unauthorized']);
}

$userId          = $authUser['id'];
$isAdmin         = $authUser['permission_level'] >= 3;

// GET — return addresses missing coordinates (admin sees all; regular user sees only their own)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($isAdmin) {
        $sql = "SELECT ID, Name, H_No, Apt_No, St_Name, City, State, Zip, Locality
                FROM Addresses_AWS
                WHERE Coordinates IS NULL OR TRIM(Coordinates) = ''
                ORDER BY City, St_Name, H_No";
        $stmt = mysqli_prepare($con, $sql);
    } else {
        $sql = "SELECT ID, Name, H_No, Apt_No, St_Name, City, State, Zip, Locality
                FROM Addresses_AWS
                WHERE (Coordinates IS NULL OR TRIM(Coordinates) = '')
                  AND uploaded_by = ?
                ORDER BY City, St_Name, H_No";
        $stmt = mysqli_prepare($con, $sql);
        if ($stmt) mysqli_stmt_bind_param($stmt, 'i', $userId);
    }

    if (!$stmt) {
        respond(500, array('success' => false, 'message' => 'Failed to prepare query'));
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $name, $hNo, $aptNo, $stName, $city, $state, $zip, $locality);

    $rows = array();
    while (mysqli_stmt_fetch($stmt)) {
        $rows[] = array(
            'id'       => intval($id),
            'name'     => $name,
            'houseNo'  => $hNo,
            'aptNo'    => $aptNo,
            'streetName' => $stName,
            'city'     => $city,
            'state'    => $state,
            'zip'      => $zip,
            'locality' => $locality,
        );
    }
    mysqli_stmt_close($stmt);

    respond(200, array('success' => true, 'data' => $rows, 'count' => count($rows)));
}

// POST — save coordinates for a specific address
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $id        = isset($input['id']) ? intval($input['id']) : 0;
    $latitude  = isset($input['latitude'])  ? trim((string)$input['latitude'])  : '';
    $longitude = isset($input['longitude']) ? trim((string)$input['longitude']) : '';

    if ($id <= 0 || $latitude === '' || $longitude === '') {
        respond(400, array('success' => false, 'message' => 'id, latitude, and longitude are required'));
    }

    $coordinates = $latitude . ',' . $longitude;

    // Admins can update any row; regular users can only update their own rows
    if ($isAdmin) {
        $stmt = mysqli_prepare($con,
            "UPDATE Addresses_AWS SET Coordinates = ?, `Clear` = 0 WHERE ID = ?");
        if (!$stmt) respond(500, array('success' => false, 'message' => 'Failed to prepare update query'));
        mysqli_stmt_bind_param($stmt, 'si', $coordinates, $id);
    } else {
        $stmt = mysqli_prepare($con,
            "UPDATE Addresses_AWS SET Coordinates = ?, `Clear` = 0
             WHERE ID = ? AND uploaded_by = ?");
        if (!$stmt) respond(500, array('success' => false, 'message' => 'Failed to prepare update query'));
        mysqli_stmt_bind_param($stmt, 'sii', $coordinates, $id, $userId);
    }

    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected <= 0) {
        respond(404, array('success' => false, 'message' => 'Address not found or not accessible'));
    }

    respond(200, array('success' => true, 'message' => 'Coordinates saved successfully'));
}

respond(405, array('success' => false, 'message' => 'Method not allowed'));
?>
