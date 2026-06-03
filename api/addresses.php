<?php
include_once __DIR__ . '/cors.php';
file_put_contents('/tmp/addresses_php_debug.txt', 'executed: '.date('c')."\n", FILE_APPEND);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include('db.php');

function get_authenticated_user($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);
    $stmt = $con->prepare('SELECT "id", "org_id", "permissions" FROM "Login_user_AWS" WHERE "auth_token" = :token AND "status" = :status LIMIT 1');
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !$row['id']) return null;
    return [
        'id' => intval($row['id']),
        'org_id' => intval($row['org_id']),
        'permission_level' => intval($row['permissions']),
    ];
}

function resolve_effective_owner_id($con, $userId, $orgId, $permissionLevel) {
    if ($permissionLevel >= 3 || $orgId <= 0) {
        return intval($userId);
    }
    $safeOrg = intval($orgId);
    $stmt = $con->prepare('SELECT "id" FROM "Login_user_AWS" WHERE "org_id" = :org AND "status" = :status AND ("org_role" = :org_admin OR "org_role" = :admin OR "permissions" = :p3 OR "permissions" = :p4) ORDER BY CASE WHEN "org_role" = :org_admin THEN 0 WHEN "org_role" = :admin THEN 1 ELSE 2 END, "id" ASC LIMIT 1');
    $stmt->execute([':org' => $safeOrg, ':status' => 'true', ':org_admin' => 'org_admin', ':admin' => 'admin', ':p3' => '3', ':p4' => '4']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($row && $row['id']) ? intval($row['id']) : intval($userId);
}


$state = isset($_GET['state']) ? trim($_GET['state']) : '';
$locality = isset($_GET['locality']) ? trim($_GET['locality']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$masjidId = isset($_GET['masjidId']) ? intval($_GET['masjidId']) : 0;
$mine = isset($_GET['mine']) && $_GET['mine'] === '1';
$listAll = isset($_GET['listAll']) && $_GET['listAll'] === '1';
$me = ($mine || $listAll) ? get_authenticated_user($con) : null;

// Build SQL and params for PostgreSQL
// Build SQL and params for PDO
$params = array();
$where = [];
if ($listAll) {
    $sql = "SELECT \"ID\", \"Name\", \"City\", \"Coordinates\", \"H_No\", \"St_Name\", \"State\", \"Zip\", \"Locality\", \"Last_Visit\", \"Apt_No\", \"Comments\" FROM \"Addresses_AWS\" WHERE 1=1";
} else {
    $sql = "SELECT \"ID\", \"Name\", \"City\", \"Coordinates\", \"H_No\", \"St_Name\", \"State\", \"Zip\", \"Locality\", \"Last_Visit\", \"Apt_No\", \"Comments\" FROM \"Addresses_AWS\" WHERE \"Coordinates\" != '' AND \"Coordinates\" != ',' AND COALESCE(\"Clear\", 1) = 1";
}
if ($masjidId > 0) {
    $sql .= " AND (\"Masjid_id\" = :masjidId OR TRIM(\"Masjid\") = (SELECT TRIM(\"Name\") FROM \"Masjids_AWS\" WHERE \"ID\" = :masjidId2 LIMIT 1))";
    $params[':masjidId'] = $masjidId;
    $params[':masjidId2'] = $masjidId;
}
if ($mine && $me) {
    $isOrgAdmin = false;
    $stmt = $con->prepare('SELECT "org_role" FROM "Login_user_AWS" WHERE "id" = :id');
    $stmt->execute([':id' => $me['id']]);
    $orgRoleRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($orgRoleRow && ($orgRoleRow['org_role'] === 'org_admin' || $orgRoleRow['org_role'] === 'admin')) {
        $isOrgAdmin = true;
    }
    if ($isOrgAdmin && $me['org_id'] > 0) {
        $sql .= " AND (\"uploaded_by\" IN (SELECT \"id\" FROM \"Login_user_AWS\" WHERE \"org_id\" = :orgId) OR \"Masjid_id\" IN (SELECT \"ID\" FROM \"Masjids_AWS\" WHERE \"Created_by\" IN (SELECT \"id\" FROM \"Login_user_AWS\" WHERE \"org_id\" = :orgId2)))";
        $params[':orgId'] = $me['org_id'];
        $params[':orgId2'] = $me['org_id'];
    } else {
        $effectiveOwnerId = resolve_effective_owner_id($con, $me['id'], $me['org_id'], $me['permission_level']);
        $sql .= " AND (\"uploaded_by\" = :ownerId OR \"Masjid_id\" IN (SELECT \"ID\" FROM \"Masjids_AWS\" WHERE \"Created_by\" = :ownerId2))";
        $params[':ownerId'] = $effectiveOwnerId;
        $params[':ownerId2'] = $effectiveOwnerId;
    }
}
if ($state !== '') {
    $sql .= " AND TRIM(\"State\") = :state";
    $params[':state'] = $state;
}
if ($locality !== '' && strcasecmp($locality, 'All') !== 0) {
    $sql .= " AND TRIM(\"Locality\") = :locality";
    $params[':locality'] = $locality;
}
if ($search !== '') {
    $searchLike = '%' . $search . '%';
    $sql .= " AND (\"Name\" LIKE :search1 OR \"H_No\" LIKE :search2 OR \"St_Name\" LIKE :search3 OR \"City\" LIKE :search4 OR \"Zip\" LIKE :search5)";
    $params[':search1'] = $searchLike;
    $params[':search2'] = $searchLike;
    $params[':search3'] = $searchLike;
    $params[':search4'] = $searchLike;
    $params[':search5'] = $searchLike;
}
$sql .= " ORDER BY \"Name\"";

try {
    $stmt = $con->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array('success' => true, 'data' => $rows));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Query failed: ' . $e->getMessage()));
}
?>
