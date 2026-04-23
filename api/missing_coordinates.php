<?php
header('Content-Type: application/json');

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
mysqli_select_db($con, $db);

// GET — return all addresses missing coordinates
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = mysqli_prepare(
        $con,
        "SELECT ID, Name, H_No, Apt_No, St_Name, City, State, Zip, Locality
         FROM Addresses_AWS
         WHERE Coordinates IS NULL OR TRIM(Coordinates) = ''
         ORDER BY City, St_Name, H_No"
    );
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

    $stmt = mysqli_prepare($con, "UPDATE Addresses_AWS SET Coordinates = ? WHERE ID = ?");
    if (!$stmt) {
        respond(500, array('success' => false, 'message' => 'Failed to prepare update query'));
    }

    mysqli_stmt_bind_param($stmt, 'si', $coordinates, $id);
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected <= 0) {
        respond(404, array('success' => false, 'message' => 'Address not found or coordinates already set'));
    }

    respond(200, array('success' => true, 'message' => 'Coordinates saved successfully'));
}

respond(405, array('success' => false, 'message' => 'Method not allowed'));
?>
