

<?php
include_once __DIR__ . '/cors.php';
require_once 'db.pgsql.php';
header('Content-Type: application/json');

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

function resolve_effective_owner_id($pdo, $userId, $orgId, $permissionLevel, $orgRole) {
    if ($permissionLevel >= 3 || $orgId <= 0) {
        return intval($userId);
    }
    $sql = 'SELECT "id" FROM "Login_user_AWS" WHERE "org_id" = :orgId AND "status" = :status AND ("org_role" = :orgAdmin OR "org_role" = :admin OR "Permissions" = :perm3 OR "Permissions" = :perm4) ORDER BY CASE WHEN "org_role" = :orgAdmin THEN 0 WHEN "org_role" = :admin THEN 1 ELSE 2 END, "id" ASC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':orgId' => $orgId,
        ':status' => 'true',
        ':orgAdmin' => 'org_admin',
        ':admin' => 'admin',
        ':perm3' => '3',
        ':perm4' => '4',
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($row && isset($row['id'])) ? intval($row['id']) : intval($userId);
}

$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (strpos($authHeader, 'Bearer ') !== 0) {
    respond(401, array('success' => false, 'message' => 'Unauthorized'));
}
$token = substr($authHeader, 7);
$sql = 'SELECT "id", "org_id", "org_role", "Permissions" FROM "Login_user_AWS" WHERE "auth_token" = :token AND "status" = :status LIMIT 1';
$stmt = $pdo->prepare($sql);
$stmt->execute([':token' => $token, ':status' => 'true']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || !$row['id']) {
    respond(401, array('success' => false, 'message' => 'Unauthorized'));
}
$permissionLevel = permission_to_level($row['Permissions']);
$effectiveOwnerId = resolve_effective_owner_id($pdo, intval($row['id']), intval($row['org_id']), $permissionLevel, $row['org_role']);
// Assume Submitted_by column exists in Postgres
$submittedByExpr = 'COALESCE(m."Submitted_by", m."Created_by")';

$sql = 'SELECT m."ID", m."Name", m."H_No", m."Apt_No", m."St_Name", m."City", m."State", m."Zip", m."Coordinates", m."Created_by", COALESCE(u."username", \'\') AS submitted_by FROM "Masjids_AWS" m LEFT JOIN "Login_user_AWS" u ON u."id" = ' + $submittedByExpr + ' WHERE COALESCE(m."Clear", 1) = 0 AND m."Created_by" = :createdBy ORDER BY m."City", m."St_Name", m."H_No"';
$stmt = $pdo->prepare($sql);
$stmt->execute([':createdBy' => $effectiveOwnerId]);
$rows = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = array(
        'id' => intval($row['ID']),
        'name' => $row['Name'],
        'houseNo' => $row['H_No'],
        'aptNo' => $row['Apt_No'],
        'streetName' => $row['St_Name'],
        'city' => $row['City'],
        'state' => $row['State'],
        'zip' => $row['Zip'],
        'Coordinates' => $row['Coordinates'],
        'createdBy' => isset($row['Created_by']) ? intval($row['Created_by']) : null,
        'submittedBy' => $row['submitted_by'],
    );
}
respond(200, array('success' => true, 'data' => $rows, 'count' => count($rows)));
