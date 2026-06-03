<?php
include_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}


include('db.php'); // $con is PDO

function get_authenticated_user($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);
    $stmt = $con->prepare('SELECT "id", "org_id", "permissions" FROM "Login_user_AWS" WHERE "auth_token" = :token AND "status" = :status LIMIT 1');
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !$row['id']) return null;
    return ['id' => intval($row['id']), 'org_id' => intval($row['org_id']), 'permission_level' => intval($row['permissions'])];
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
$createdBy = isset($_GET['createdBy']) ? intval($_GET['createdBy']) : 0;
$orgScoped = isset($_GET['orgScoped']) && $_GET['orgScoped'] === '1';
$includeOwnPending = isset($_GET['includeOwnPending']) && $_GET['includeOwnPending'] === '1';
$mine = isset($_GET['mine']) && $_GET['mine'] === '1';
$me = ($orgScoped || $includeOwnPending || $mine) ? get_authenticated_user($con) : null;



$isSuperAdmin = $me && isset($me['permission_level']) && $me['permission_level'] >= 4;
// If super admin and no restrictive params, show all
if ($isSuperAdmin && !$mine && !$orgScoped && !$createdBy && !$includeOwnPending) {
    $sql = "SELECT \"ID\", \"Name\", \"H_No\", \"Apt_No\", \"St_Name\", \"City\", \"State\", \"Zip\", \"Coordinates\" FROM \"Masjids_AWS\" WHERE COALESCE(\"Clear\", 1) = 1";
    $params = array();
} else {
    $sql = "SELECT \"ID\", \"Name\", \"H_No\", \"Apt_No\", \"St_Name\", \"City\", \"State\", \"Zip\", \"Coordinates\" FROM \"Masjids_AWS\" WHERE (COALESCE(\"Clear\", 1) = 1";
    // ...existing code...
}


$params = array();
if ($includeOwnPending && $me && $createdBy > 0 && intval($me['id']) === $createdBy) {
    $sql .= " OR \"Created_by\" = :createdBy";
    $params[':createdBy'] = $createdBy;
}
$sql .= ")";


if ($mine && $me) {
    $effectiveOwnerId = resolve_effective_owner_id($con, $me['id'], $me['org_id'], $me['permission_level']);
    $sql .= " AND \"Created_by\" = :mineOwnerId";
    $params[':mineOwnerId'] = $effectiveOwnerId;
}


if ($state !== '') {
    $sql .= " AND TRIM(\"State\") = :state";
    $params[':state'] = $state;
}

if ($locality !== '' && strcasecmp($locality, 'All') !== 0) {
    $sql .= " AND EXISTS (
        SELECT 1
        FROM \"Addresses_AWS\" a
        WHERE a.\"Masjid\" = \"Masjids_AWS\".\"Name\"
          AND TRIM(a.\"Locality\") = :locality
          AND TRIM(a.\"State\") = TRIM(\"Masjids_AWS\".\"State\")
    )";
    $params[':locality'] = $locality;
}

if ($createdBy > 0) {
    $sql .= " AND \"Created_by\" = :createdBy2";
    $params[':createdBy2'] = $createdBy;
}

if ($orgScoped && $me) {
    if (!empty($me['org_id'])) {
        $sql .= " AND EXISTS (
            SELECT 1
            FROM \"Login_user_AWS\" owner
            WHERE owner.\"id\" = \"Masjids_AWS\".\"Created_by\"
              AND owner.\"org_id\" = :orgId
        )";
        $params[':orgId'] = $me['org_id'];
    } else {
        $sql .= " AND \"Created_by\" = :meId";
        $params[':meId'] = $me['id'];
    }
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