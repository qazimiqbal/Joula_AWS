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
$masjidId = isset($_GET['masjidId']) ? intval($_GET['masjidId']) : 0;

$params = array();
$types = '';

$sql = "SELECT ID, Name, City, Coordinates, H_No, St_Name, State, Zip, Locality, Last_Visit, Apt_No, Comments
        FROM Addresses_AWS
    WHERE Coordinates != '' AND Coordinates != ',' AND COALESCE(`Clear`, 1) = 1";

if ($masjidId > 0) {
    $sql .= " AND Masjid_id = ?";
    $types .= 'i';
    $params[] = $masjidId;
}

if ($state !== '') {
    $sql .= " AND TRIM(State) = ?";
    $types .= 's';
    $params[] = $state;
}

if ($locality !== '' && strcasecmp($locality, 'All') !== 0) {
    $sql .= " AND TRIM(Locality) = ?";
    $types .= 's';
    $params[] = $locality;
}

if ($search !== '') {
    $searchLike = '%' . $search . '%';
    $sql .= " AND (Name LIKE ? OR H_No LIKE ? OR St_Name LIKE ? OR City LIKE ? OR Zip LIKE ?)";
    $types .= 'sssss';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$sql .= " ORDER BY Name";

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
mysqli_stmt_bind_result($stmt, $id, $name, $city, $coordinates, $houseNo, $streetName, $recordState, $zip, $recordLocality, $lastVisit, $aptNo, $comments);

$rows = array();
while (mysqli_stmt_fetch($stmt)) {
    $rows[] = array(
        'ID' => $id,
        'Name' => $name,
        'City' => $city,
        'Coordinates' => $coordinates,
        'H_No' => $houseNo,
        'St_Name' => $streetName,
        'State' => $recordState,
        'Zip' => $zip,
        'Locality' => $recordLocality,
        'Last_Visit' => $lastVisit,
        'Apt_No' => $aptNo,
        'Comments' => $comments
    );
}

mysqli_stmt_close($stmt);

echo json_encode(array('success' => true, 'data' => $rows));
?>
