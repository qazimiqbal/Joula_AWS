<?php
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

include('db.php');
if (!mysqli_select_db($con, $db)) {
    respond(500, array('success' => false, 'message' => 'Failed to select database'));
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

function get_authenticated_user($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;

    $token = substr($authHeader, 7);
    $stmt = mysqli_prepare($con,
        "SELECT id, org_id, org_role, Permissions
         FROM Login_user_AWS
         WHERE auth_token = ? AND status = 'true' LIMIT 1");

    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $id = $orgId = null;
    $orgRole = $permissionsRaw = null;
    mysqli_stmt_bind_result($stmt, $id, $orgId, $orgRole, $permissionsRaw);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$found || !$id) return null;

    return array(
        'id' => intval($id),
        'orgId' => intval($orgId),
        'orgRole' => $orgRole,
        'permissionLevel' => permission_to_level($permissionsRaw),
    );
}

$me = get_authenticated_user($con);
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

$seatStmt = mysqli_prepare($con,
    "SELECT max_editors, max_viewers FROM organizations WHERE id = ? LIMIT 1");
if (!$seatStmt) {
    respond(500, array('success' => false, 'message' => 'Failed to load organization limits'));
}
mysqli_stmt_bind_param($seatStmt, 'i', $me['orgId']);
mysqli_stmt_execute($seatStmt);
$maxEditors = $maxViewers = null;
mysqli_stmt_bind_result($seatStmt, $maxEditors, $maxViewers);
$foundOrg = mysqli_stmt_fetch($seatStmt);
mysqli_stmt_close($seatStmt);

if (!$foundOrg) {
    respond(404, array('success' => false, 'message' => 'Organization not found'));
}

$countStmt = mysqli_prepare($con,
    "SELECT COUNT(*) FROM Login_user_AWS WHERE org_id = ? AND org_role = ?");
if (!$countStmt) {
    respond(500, array('success' => false, 'message' => 'Failed to count current team members'));
}
mysqli_stmt_bind_param($countStmt, 'is', $me['orgId'], $orgRole);
mysqli_stmt_execute($countStmt);
$currentCount = 0;
mysqli_stmt_bind_result($countStmt, $currentCount);
mysqli_stmt_fetch($countStmt);
mysqli_stmt_close($countStmt);

$maxEditors = intval($maxEditors);
$maxViewers = intval($maxViewers);
if ($maxEditors <= 0) $maxEditors = 1;
if ($maxViewers <= 0) $maxViewers = 3;

$roleLimit = ($orgRole === 'editor') ? $maxEditors : $maxViewers;
if ($roleLimit > 0 && intval($currentCount) >= $roleLimit) {
    respond(422, array('success' => false, 'message' => "Seat limit reached for {$orgRole} users"));
}

$dupStmt = mysqli_prepare($con,
    "SELECT
        MAX(CASE WHEN username = ? THEN 1 ELSE 0 END) AS username_exists,
        MAX(CASE WHEN email = ? THEN 1 ELSE 0 END) AS email_exists
     FROM Login_user_AWS");
if (!$dupStmt) {
    respond(500, array('success' => false, 'message' => 'Failed to check duplicates'));
}
mysqli_stmt_bind_param($dupStmt, 'ss', $username, $email);
mysqli_stmt_execute($dupStmt);
$usernameExists = $emailExists = 0;
mysqli_stmt_bind_result($dupStmt, $usernameExists, $emailExists);
mysqli_stmt_fetch($dupStmt);
mysqli_stmt_close($dupStmt);

if (intval($usernameExists) === 1 || intval($emailExists) === 1) {
    $parts = array();
    if (intval($usernameExists) === 1) $parts[] = 'username already exists';
    if (intval($emailExists) === 1) $parts[] = 'email already exists';
    respond(409, array('success' => false, 'message' => 'This ' . implode(' and ', $parts) . '. Please change and try again.'));
}

$permission = ($orgRole === 'editor') ? '2' : '1';

// Dynamically build INSERT to handle optional columns (mirrors register.php pattern)
$hasPassChange = (function() use ($con) {
    $result = mysqli_query($con, "SHOW COLUMNS FROM Login_user_AWS LIKE 'Pass_change'");
    if ($result) {
        $row = mysqli_fetch_row($result);
        mysqli_free_result($result);
        return (bool)$row;
    }
    return false;
})();

$hasPhone = (function() use ($con) {
    $result = mysqli_query($con, "SHOW COLUMNS FROM Login_user_AWS LIKE 'phone'");
    if ($result) {
        $row = mysqli_fetch_row($result);
        mysqli_free_result($result);
        return (bool)$row;
    }
    return false;
})();

$hasGoogleOnly = (function() use ($con) {
    $result = mysqli_query($con, "SHOW COLUMNS FROM Login_user_AWS LIKE 'google_only'");
    if ($result) {
        $row = mysqli_fetch_row($result);
        mysqli_free_result($result);
        if ($row) return true;
    }
    mysqli_query($con, "ALTER TABLE Login_user_AWS ADD COLUMN google_only TINYINT(1) NOT NULL DEFAULT 0");
    $result2 = mysqli_query($con, "SHOW COLUMNS FROM Login_user_AWS LIKE 'google_only'");
    if ($result2) {
        $row2 = mysqli_fetch_row($result2);
        mysqli_free_result($result2);
        return (bool)$row2;
    }
    return false;
})();

$generatedPassword = bin2hex(random_bytes(24));

$insertColumns = array('username', 'password', 'email');
$insertValues  = array('?', 'MD5(?)', '?');
$bindTypes     = 'sss';
$bindValues    = array($username, $generatedPassword, $email);

if ($hasPhone) {
    $insertColumns[] = 'phone';
    $insertValues[]  = '?';
    $bindTypes       .= 's';
    $bindValues[]    = $phone;
}

$insertColumns[] = 'Permissions';
$insertValues[]  = '?';
$bindTypes       .= 's';
$bindValues[]    = $permission;

if ($hasPassChange) {
    $insertColumns[] = 'Pass_change';
    $insertValues[]  = "'No'";
}

$insertColumns[] = 'status';
$insertValues[]  = "'true'";

$insertColumns[] = 'org_role';
$insertValues[]  = '?';
$bindTypes       .= 's';
$bindValues[]    = $orgRole;

$insertColumns[] = 'org_id';
$insertValues[]  = '?';
$bindTypes       .= 'i';
$bindValues[]    = $me['orgId'];

if ($hasGoogleOnly) {
    $insertColumns[] = 'google_only';
    $insertValues[]  = '1';
}

$sqlInsert = "INSERT INTO Login_user_AWS (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
$stmtInsert = mysqli_prepare($con, $sqlInsert);

if (!$stmtInsert) {
    respond(500, array('success' => false, 'message' => 'Failed to prepare user creation query', 'error' => mysqli_error($con), 'sql' => $sqlInsert));
}

$bindParams = array($bindTypes);
foreach ($bindValues as $k => $v) {
    $bindParams[] = &$bindValues[$k];
}
call_user_func_array(array($stmtInsert, 'bind_param'), $bindParams);

if (!mysqli_stmt_execute($stmtInsert)) {
    $error = mysqli_stmt_error($stmtInsert);
    mysqli_stmt_close($stmtInsert);
    respond(500, array('success' => false, 'message' => 'Failed to create team user', 'error' => $error));
}

$newUserId = mysqli_insert_id($con);
mysqli_stmt_close($stmtInsert);

respond(200, array(
    'success' => true,
    'message' => 'Team member created successfully. They must sign in with Google using this email.',
    'userId' => intval($newUserId),
    'role' => $orgRole,
    'permission' => intval($permission),
));
