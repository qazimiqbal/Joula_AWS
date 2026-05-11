<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include('db.php');
mysqli_select_db($con, $db);

function get_authenticated_user($con) {
        $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if (strpos($authHeader, 'Bearer ') !== 0) return null;
        $token = substr($authHeader, 7);

        $stmt = mysqli_prepare($con,
                "SELECT id, org_id, Permissions
                 FROM Login_user_AWS
                 WHERE auth_token = ? AND status = 'true' LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $userId = $orgId = $permissions = null;
        mysqli_stmt_bind_result($stmt, $userId, $orgId, $permissions);
        $found = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        if (!$found || !$userId) return null;
        return [
                'id' => intval($userId),
                'org_id' => intval($orgId),
                'permission_level' => intval($permissions),
        ];
}

function resolve_effective_owner_id($con, $userId, $orgId, $permissionLevel) {
        if ($permissionLevel >= 3 || $orgId <= 0) {
                return intval($userId);
        }

        $safeOrg = intval($orgId);
        $ownerRes = mysqli_query(
                $con,
                "SELECT id
                 FROM Login_user_AWS
                 WHERE org_id = $safeOrg
                     AND status = 'true'
                     AND (org_role = 'org_admin' OR org_role = 'admin' OR Permissions = '3' OR Permissions = '4')
                 ORDER BY
                     CASE
                         WHEN org_role = 'org_admin' THEN 0
                         WHEN org_role = 'admin' THEN 1
                         ELSE 2
                     END,
                     id ASC
                 LIMIT 1"
        );
        if (!$ownerRes) return intval($userId);
        $ownerRow = mysqli_fetch_assoc($ownerRes);
        mysqli_free_result($ownerRes);

        return ($ownerRow && $ownerRow['id']) ? intval($ownerRow['id']) : intval($userId);
}


$state = isset($_GET['state']) ? trim($_GET['state']) : '';
$locality = isset($_GET['locality']) ? trim($_GET['locality']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$masjidId = isset($_GET['masjidId']) ? intval($_GET['masjidId']) : 0;
$mine = isset($_GET['mine']) && $_GET['mine'] === '1';
$listAll = isset($_GET['listAll']) && $_GET['listAll'] === '1';
$me = ($mine || $listAll) ? get_authenticated_user($con) : null;

$params = array();
$types = '';

if ($listAll) {
    $sql = "SELECT ID, Name, City, Coordinates, H_No, St_Name, State, Zip, Locality, Last_Visit, Apt_No, Comments
            FROM Addresses_AWS
        WHERE 1=1";
} else {
    $sql = "SELECT ID, Name, City, Coordinates, H_No, St_Name, State, Zip, Locality, Last_Visit, Apt_No, Comments
            FROM Addresses_AWS
        WHERE Coordinates != '' AND Coordinates != ',' AND COALESCE(`Clear`, 1) = 1";
}

if ($masjidId > 0) {
    $sql .= " AND (Masjid_id = ? OR TRIM(Masjid) = (SELECT TRIM(Name) FROM Masjids_AWS WHERE ID = ? LIMIT 1))";
    $types .= 'ii';
    $params[] = $masjidId;
    $params[] = $masjidId;
}

if ($mine && $me) {
    $effectiveOwnerId = resolve_effective_owner_id($con, $me['id'], $me['org_id'], $me['permission_level']);
    $sql .= " AND (uploaded_by = ? OR Masjid_id IN (SELECT ID FROM Masjids_AWS WHERE Created_by = ?))";
    $types .= 'ii';
    $params[] = $effectiveOwnerId;
    $params[] = $effectiveOwnerId;
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
