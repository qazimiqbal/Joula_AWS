<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include('db.php');
mysqli_select_db($con, $db);

$state = isset($_GET['state']) ? trim($_GET['state']) : '';
$locality = isset($_GET['locality']) ? trim($_GET['locality']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$hasCoordinates = false;
$colResult = mysqli_query($con, "SHOW COLUMNS FROM Masjids_AWS LIKE 'Coordinates'");
if ($colResult) {
    $hasCoordinates = mysqli_num_rows($colResult) > 0;
    mysqli_free_result($colResult);
}

$selectCoordinates = $hasCoordinates ? 'm.Coordinates' : "'' AS Coordinates";

$sql = "SELECT m.ID, m.Name, m.H_No, m.Apt_No, m.St_Name, m.City, m.State, m.Zip, $selectCoordinates
        FROM Masjids_AWS m
        WHERE COALESCE(m.`Clear`, 1) = 1";
$params = array();
$types = '';

if ($state !== '') {
    $sql .= " AND TRIM(m.State) = ?";
    $types .= 's';
    $params[] = $state;
}

if ($locality !== '' && strcasecmp($locality, 'All') !== 0) {
    $sql .= " AND EXISTS (
        SELECT 1
        FROM Addresses_AWS a
        WHERE a.Masjid = m.Name
          AND TRIM(a.Locality) = ?
          AND TRIM(a.State) = TRIM(m.State)
    )";
    $types .= 's';
    $params[] = $locality;
}

if ($search !== '') {
    $searchLike = '%' . $search . '%';
    $sql .= " AND (m.Name LIKE ? OR m.H_No LIKE ? OR m.St_Name LIKE ? OR m.City LIKE ? OR m.Zip LIKE ?)";
    $types .= 'sssss';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$sql .= " ORDER BY m.Name";

$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Query prepare failed'));
    exit;
}

if (!empty($params)) {
    $bindParams = array_merge(array($stmt, $types), $params);
    $refs = array();
    foreach ($bindParams as $key => $value) {
        $refs[$key] = &$bindParams[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', $refs);
}

mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $id, $name, $houseNo, $aptNo, $streetName, $city, $recordState, $zip, $coordinates);

$rows = array();
while (mysqli_stmt_fetch($stmt)) {
    $rows[] = array(
        'ID' => $id,
        'Name' => $name,
        'H_No' => $houseNo,
        'Apt_No' => $aptNo,
        'St_Name' => $streetName,
        'City' => $city,
        'State' => $recordState,
        'Zip' => $zip,
        'Coordinates' => $coordinates,
    );
}

mysqli_stmt_close($stmt);

echo json_encode(array('success' => true, 'data' => $rows));
?>