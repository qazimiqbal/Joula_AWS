
<?php
include_once __DIR__ . '/cors.php';
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

function resolve_effective_owner_id($con, $userId, $orgId, $permissionLevel) {
    if ($permissionLevel >= 3 || $orgId <= 0) {
        return intval($userId);
    }

    $ownerStmt = mysqli_prepare(
        $con,
        "SELECT id
         FROM Login_user_AWS
         WHERE org_id = ?
             AND status = 'true'
             AND (org_role = 'org_admin' OR org_role = 'admin' OR permissions = '3' OR permissions = '4')
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

// Removed MySQLi db.php include
require_once 'db.pgsql.php';

// PDO/Postgres version
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (strpos($authHeader, 'Bearer ') !== 0) {
    respond(401, array('success' => false, 'message' => 'Unauthorized'));
}

$token = substr($authHeader, 7);
// Use lowercase permissions for PostgreSQL
$sqlUser = 'SELECT id, org_id, permissions FROM "Login_user_AWS" WHERE auth_token = :token AND status = :status LIMIT 1';
$stmtUser = $pdo->prepare($sqlUser);
$stmtUser->execute([':token' => $token, ':status' => 'true']);
$rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
if (!$rowUser || !isset($rowUser['id'])) {
    respond(401, array('success' => false, 'message' => 'Unauthorized'));
}
$userId = intval($rowUser['id']);
$orgId = intval($rowUser['org_id']);
$permissionsRaw = $rowUser['permissions'];
$permissionLevel = permission_to_level($permissionsRaw);
if ($permissionLevel < 2) {
    respond(403, array('success' => false, 'message' => 'Only admins and editors can review submissions'));
}
$effectiveOwnerId = resolve_effective_owner_id($pdo, $userId, $orgId, $permissionLevel);

$sql = 'SELECT a."ID", a."Name", a."H_No", a."Apt_No", a."St_Name", a."City", a."State", a."Zip", a."Locality", a."Coordinates", a."uploaded_by", COALESCE(u."username", \'\') AS submitted_by FROM "Addresses_AWS" a LEFT JOIN "Login_user_AWS" u ON u."id" = a."uploaded_by" WHERE COALESCE(a."Clear", 1) = 0 AND a."uploaded_by" = :ownerId ORDER BY a."City", a."St_Name", a."H_No"';
$stmt = $pdo->prepare($sql);
$stmt->execute([':ownerId' => $effectiveOwnerId]);
$rows = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $lat = null;
    $lng = null;
    $parts = explode(',', (string)($row['Coordinates'] ?? ''));
    if (count($parts) === 2) {
        $latRaw = trim($parts[0]);
        $lngRaw = trim($parts[1]);
        if ($latRaw !== '' && $lngRaw !== '' && is_numeric($latRaw) && is_numeric($lngRaw)) {
            $lat = floatval($latRaw);
            $lng = floatval($lngRaw);
        }
    }
    $rows[] = array(
        'id' => intval($row['ID']),
        'name' => $row['Name'],
        'houseNo' => $row['H_No'],
        'aptNo' => $row['Apt_No'],
        'streetName' => $row['St_Name'],
        'city' => $row['City'],
        'state' => $row['State'],
        'zip' => $row['Zip'],
        'locality' => $row['Locality'],
        'latitude' => $lat,
        'longitude' => $lng,
        'uploadedBy' => isset($row['uploaded_by']) ? intval($row['uploaded_by']) : null,
        'submittedBy' => $row['submitted_by'],
    );
}
respond(200, array('success' => true, 'data' => $rows, 'count' => count($rows)));
?>
