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
    return ['id' => intval($userId), 'org_id' => intval($orgId), 'permission_level' => intval($permissions)];
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
$createdBy = isset($_GET['createdBy']) ? intval($_GET['createdBy']) : 0;
$orgScoped = isset($_GET['orgScoped']) && $_GET['orgScoped'] === '1';
$includeOwnPending = isset($_GET['includeOwnPending']) && $_GET['includeOwnPending'] === '1';
$mine = isset($_GET['mine']) && $_GET['mine'] === '1';
$me = ($orgScoped || $includeOwnPending || $mine) ? get_authenticated_user($con) : null;

$hasCoordinates = false;
$colResult = mysqli_query($con, "SHOW COLUMNS FROM Masjids_AWS LIKE 'Coordinates'");
if ($colResult) {
    $hasCoordinates = mysqli_num_rows($colResult) > 0;
    mysqli_free_result($colResult);
}

$selectCoordinates = $hasCoordinates ? 'm.Coordinates' : "'' AS Coordinates";

$sql = "SELECT m.ID, m.Name, m.H_No, m.Apt_No, m.St_Name, m.City, m.State, m.Zip, $selectCoordinates
        FROM Masjids_AWS m
        WHERE (
            COALESCE(m.`Clear`, 1) = 1";

if ($includeOwnPending && $me && $createdBy > 0 && intval($me['id']) === $createdBy) {
    $sql .= " OR m.Created_by = ?";
    $types = 'i';
    $params = array($createdBy);
} else {
    $params = array();
    $types = '';
}

$sql .= ")";

if ($mine && $me) {
    $effectiveOwnerId = resolve_effective_owner_id($con, $me['id'], $me['org_id'], $me['permission_level']);
    $sql .= " AND m.Created_by = ?";
    $types .= 'i';
    $params[] = $effectiveOwnerId;
}


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

if ($createdBy > 0) {
    $sql .= " AND m.Created_by = ?";
    $types .= 'i';
    $params[] = $createdBy;
}

if ($orgScoped && $me) {
    if (!empty($me['org_id'])) {
        $sql .= " AND EXISTS (
            SELECT 1
            FROM Login_user_AWS owner
            WHERE owner.id = m.Created_by
              AND owner.org_id = ?
        )";
        $types .= 'i';
        $params[] = $me['org_id'];
    } else {
        $sql .= " AND m.Created_by = ?";
        $types .= 'i';
        $params[] = $me['id'];
    }
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