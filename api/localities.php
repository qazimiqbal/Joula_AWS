<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$state = isset($_GET['state']) ? trim($_GET['state']) : '';
if ($state === '') {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'State parameter is required'));
    exit;
}

include('../connection.php.ini');
mysqli_select_db($con, $db);

$stmt = mysqli_prepare($con,
    "SELECT DISTINCT Locality FROM Addresses2 WHERE State = ? AND Coordinates != '' ORDER BY Locality"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Query prepare failed'));
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $state);
mysqli_stmt_execute($stmt);

$localities = array();
$locality = '';
mysqli_stmt_bind_result($stmt, $locality);
while (mysqli_stmt_fetch($stmt)) {
    $localities[] = $locality;
}
mysqli_stmt_close($stmt);

echo json_encode(array('success' => true, 'data' => $localities));
