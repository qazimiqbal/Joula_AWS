<?php
include_once __DIR__ . '/cors.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, array('success' => false, 'message' => 'Method not allowed'));
}

require_once 'db.pgsql.php';

function permission_to_level($permissionRaw) {
    $value = trim((string)$permissionRaw);
    if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
    if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
    if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
    if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
    if (is_numeric($value)) return intval($value);
    return 0;
}

function get_authenticated_user($pdo) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);
    $sql = 'SELECT id, org_id, org_role, permissions FROM "Login_user_AWS" WHERE auth_token = :token AND status = :status LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return array(
        'id' => intval($row['id']),
        'orgId' => intval($row['org_id']),
        'orgRole' => $row['org_role'],
        'permissionLevel' => permission_to_level($row['permissions']),
    );
}

function has_column_pg($pdo, $tableName, $columnName) {
    $sql = 'SELECT 1 FROM information_schema.columns WHERE table_schema = :schema AND lower(table_name) = lower(:tableName) AND lower(column_name) = lower(:columnName) LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':schema' => 'public',
        ':tableName' => $tableName,
        ':columnName' => $columnName,
    ]);
    return (bool)$stmt->fetchColumn();
}

$me = get_authenticated_user($pdo);
if (!$me) {
    respond(401, array('success' => false, 'message' => 'Unauthorized'));
}

if ($me['permissionLevel'] < 3 || ($me['orgRole'] !== 'org_admin' && $me['orgRole'] !== 'admin')) {
    respond(403, array('success' => false, 'message' => 'Only subscribed admins can build a team'));
}

if ($me['orgId'] <= 0) {
    respond(400, array('success' => false, 'message' => 'Admin account is not linked to an organization'));
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$username = isset($input['username']) ? trim($input['username']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : '';
$orgRole = isset($input['orgRole']) ? trim($input['orgRole']) : '';

if ($username === '' || $email === '' || $phone === '') {
    respond(400, array('success' => false, 'message' => 'Username, email, and phone are required'));
}

if ($orgRole !== 'editor' && $orgRole !== 'viewer') {
    respond(400, array('success' => false, 'message' => 'orgRole must be editor or viewer'));
}


$sqlSeat = 'SELECT "max_editors", "max_viewers" FROM "organizations" WHERE "id" = :id LIMIT 1';
$stmtSeat = $pdo->prepare($sqlSeat);
$stmtSeat->execute([':id' => $me['orgId']]);
$rowSeat = $stmtSeat->fetch(PDO::FETCH_ASSOC);
if (!$rowSeat) {
    respond(404, array('success' => false, 'message' => 'Organization not found'));
}
$maxEditors = intval($rowSeat['max_editors']);
$maxViewers = intval($rowSeat['max_viewers']);
if ($maxEditors <= 0) $maxEditors = 1;
if ($maxViewers <= 0) $maxViewers = 3;


$sqlCount = 'SELECT COUNT(*) AS cnt FROM "Login_user_AWS" WHERE "org_id" = :orgId AND "org_role" = :orgRole';
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute([':orgId' => $me['orgId'], ':orgRole' => $orgRole]);
$rowCount = $stmtCount->fetch(PDO::FETCH_ASSOC);
$currentCount = $rowCount ? intval($rowCount['cnt']) : 0;

$maxEditors = intval($maxEditors);
$maxViewers = intval($maxViewers);
if ($maxEditors <= 0) $maxEditors = 1;
if ($maxViewers <= 0) $maxViewers = 3;

$roleLimit = ($orgRole === 'editor') ? $maxEditors : $maxViewers;
if ($roleLimit > 0 && intval($currentCount) >= $roleLimit) {
    respond(422, array('success' => false, 'message' => "Seat limit reached for {$orgRole} users"));
}


$sqlDup = 'SELECT MAX(CASE WHEN "username" = :username THEN 1 ELSE 0 END) AS username_exists, MAX(CASE WHEN "email" = :email THEN 1 ELSE 0 END) AS email_exists FROM "Login_user_AWS"';
$stmtDup = $pdo->prepare($sqlDup);
$stmtDup->execute([':username' => $username, ':email' => $email]);
$rowDup = $stmtDup->fetch(PDO::FETCH_ASSOC);
$usernameExists = $rowDup ? intval($rowDup['username_exists']) : 0;
$emailExists = $rowDup ? intval($rowDup['email_exists']) : 0;

if (intval($usernameExists) === 1 || intval($emailExists) === 1) {
    $parts = array();
    if (intval($usernameExists) === 1) $parts[] = 'username already exists';
    if (intval($emailExists) === 1) $parts[] = 'email already exists';
    respond(409, array('success' => false, 'message' => 'This ' . implode(' and ', $parts) . '. Please change and try again.'));
}

$permission = ($orgRole === 'editor') ? '2' : '1';


$hasPassChange = has_column_pg($pdo, 'Login_user_AWS', 'Pass_change');
$hasPhone = has_column_pg($pdo, 'Login_user_AWS', 'phone');
$hasGoogleOnly = has_column_pg($pdo, 'Login_user_AWS', 'google_only');

$generatedPassword = bin2hex(random_bytes(24));


$insertColumns = ['"username"', '"password"', '"email"', 'permissions', '"status"', '"org_role"', '"org_id"'];
$insertValues = [':username', 'MD5(:password)', ':email', ':permission', ':status', ':orgRole', ':orgId'];
$insertParams = [
    ':username' => $username,
    ':password' => $generatedPassword,
    ':email' => $email,
    ':permission' => $permission,
    ':status' => 'true',
    ':orgRole' => $orgRole,
    ':orgId' => $me['orgId'],
];

if ($hasPhone) {
    $insertColumns[] = '"phone"';
    $insertValues[] = ':phone';
    $insertParams[':phone'] = $phone;
}
if ($hasPassChange) {
    $insertColumns[] = '"Pass_change"';
    $insertValues[] = ':passChange';
    $insertParams[':passChange'] = 'No';
}
if ($hasGoogleOnly) {
    $insertColumns[] = '"google_only"';
    $insertValues[] = ':googleOnly';
    $insertParams[':googleOnly'] = 1;
}

$sqlInsert = 'INSERT INTO "Login_user_AWS" (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ') RETURNING "id"';
$stmtInsert = $pdo->prepare($sqlInsert);
$stmtInsert->execute($insertParams);
$newUserId = $stmtInsert->fetchColumn();
respond(200, array(
    'success' => true,
    'message' => 'Team member created successfully. They must sign in with Google using this email.',
    'userId' => intval($newUserId),
    'role' => $orgRole,
    'permission' => intval($permission),
));
