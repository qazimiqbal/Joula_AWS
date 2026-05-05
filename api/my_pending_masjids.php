<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit;
}

function respond($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function permission_to_level($permissionRaw) {
        $value = trim((string)$permissionRaw);
        if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
        if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
        if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
        if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
        if (is_numeric($value)) return intval($value);
        return 0;
}

function has_table_column($con, $table, $column) {
    $tableSafe = mysqli_real_escape_string($con, $table);
    $columnSafe = mysqli_real_escape_string($con, $column);
    $result = mysqli_query($con, "SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    if (!$result) return false;
    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    return $exists;
}

function resolve_effective_owner_id($con, $userId, $orgId, $permissionLevel, $orgRole) {
        if ($permissionLevel >= 3 || $orgId <= 0) {
                return intval($userId);
        }

        $ownerStmt = mysqli_prepare(
                $con,
                "SELECT id
                 FROM Login_user_AWS
                 WHERE org_id = ?
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

        if (!$ownerStmt) return intval($userId);
        mysqli_stmt_bind_param($ownerStmt, 'i', $orgId);
        mysqli_stmt_execute($ownerStmt);
        $ownerId = null;
        mysqli_stmt_bind_result($ownerStmt, $ownerId);
        $found = mysqli_stmt_fetch($ownerStmt);
        mysqli_stmt_close($ownerStmt);

        return ($found && $ownerId) ? intval($ownerId) : intval($userId);
}

include('db.php');

$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (strpos($authHeader, 'Bearer ') !== 0) {
    respond(401, array('success' => false, 'message' => 'Unauthorized'));
}

$token = substr($authHeader, 7);
$stmtUser = mysqli_prepare($con, "SELECT id, org_id, org_role, Permissions FROM Login_user_AWS WHERE auth_token = ? AND status = 'true' LIMIT 1");
if (!$stmtUser) {
    respond(500, array('success' => false, 'message' => 'Failed to prepare user lookup'));
}

mysqli_stmt_bind_param($stmtUser, 's', $token);
mysqli_stmt_execute($stmtUser);
$userId = $orgId = null;
$orgRole = $permissionsRaw = null;
mysqli_stmt_bind_result($stmtUser, $userId, $orgId, $orgRole, $permissionsRaw);
$found = mysqli_stmt_fetch($stmtUser);
mysqli_stmt_close($stmtUser);

if (!$found || !$userId) {
    respond(401, array('success' => false, 'message' => 'Unauthorized'));
}

$permissionLevel = permission_to_level($permissionsRaw);
$effectiveOwnerId = resolve_effective_owner_id($con, intval($userId), intval($orgId), $permissionLevel, $orgRole);
$submittedByExpr = has_table_column($con, 'Masjids_AWS', 'Submitted_by')
    ? 'COALESCE(m.Submitted_by, m.Created_by)'
    : 'm.Created_by';

$stmt = mysqli_prepare(
    $con,
    "SELECT m.ID, m.Name, m.H_No, m.Apt_No, m.St_Name, m.City, m.State, m.Zip, m.Coordinates,
            m.Created_by, COALESCE(u.username, '') AS submitted_by
     FROM Masjids_AWS m
     LEFT JOIN Login_user_AWS u ON u.id = {$submittedByExpr}
     WHERE COALESCE(m.`Clear`, 1) = 0 AND m.Created_by = ?
     ORDER BY m.City, m.St_Name, m.H_No"
);

if (!$stmt) {
    respond(500, array('success' => false, 'message' => 'Failed to prepare pending query'));
}

mysqli_stmt_bind_param($stmt, 'i', $effectiveOwnerId);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $id, $name, $hNo, $aptNo, $stName, $city, $state, $zip, $coordinates, $createdById, $submittedBy);

$rows = array();
while (mysqli_stmt_fetch($stmt)) {
    $rows[] = array(
        'id' => intval($id),
        'name' => $name,
        'houseNo' => $hNo,
        'aptNo' => $aptNo,
        'streetName' => $stName,
        'city' => $city,
        'state' => $state,
        'zip' => $zip,
        'Coordinates' => $coordinates,
        'createdBy' => isset($createdById) ? intval($createdById) : null,
        'submittedBy' => $submittedBy,
    );
}

mysqli_stmt_close($stmt);

respond(200, array('success' => true, 'data' => $rows, 'count' => count($rows)));
