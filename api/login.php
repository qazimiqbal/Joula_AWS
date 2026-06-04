<?php
$debugLog = __DIR__ . '/login_debug.log';
// Debug logging for troubleshooting (only for POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // $identifier and $password are not yet defined here, so this block should be moved after they are set.
    // We'll move this block to after $identifier and $password are set below.
}

// Always send CORS headers first
include_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

// Handle CORS preflight OPTIONS requests before any other logic
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Postgres-compatible: Find login table name
function find_login_table_name($con, $dbName) {
    global $debugLog;
    // Log all visible table names for diagnostics (guaranteed to run)
    $tableList = [];
    $tableListError = null;
    try {
        $stmt = $con->prepare("SELECT table_name, table_schema FROM information_schema.tables WHERE table_schema = 'public'");
        $stmt->execute();
        $allTables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tableList = array_map(function($row) { return $row['table_schema'] . '.' . $row['table_name']; }, $allTables);
    } catch (Exception $e) {
        $tableListError = $e->getMessage();
    }
    if (!empty($tableList)) {
        file_put_contents($debugLog, date('c') . " | [DEBUG] Tables visible to connection (guaranteed): " . implode(', ', $tableList) . "\n", FILE_APPEND);
    } else if ($tableListError) {
        file_put_contents($debugLog, date('c') . " | [DEBUG] Error fetching table list (guaranteed): " . $tableListError . "\n", FILE_APPEND);
    } else {
        file_put_contents($debugLog, date('c') . " | [DEBUG] No tables visible to connection (guaranteed)\n", FILE_APPEND);
    }
    $candidateTables = array(
        'Login_user_AWS', // actual case in DB
        'login_user_aws',
        'login_user',
        'users'
    );
    file_put_contents($debugLog, date('c') . " | [DEBUG] Searching for login table in DB: $dbName\n", FILE_APPEND);

    // Search information_schema.tables for a matching table (case-insensitive)
    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND LOWER(table_name) = :candidate LIMIT 1";
    foreach ($candidateTables as $candidateTable) {
        $stmt = $con->prepare($sql);
        $stmt->execute([':candidate' => strtolower($candidateTable)]);
        $result = $stmt->fetchColumn();
        file_put_contents($debugLog, date('c') . " | [DEBUG] Candidate: $candidateTable, Found: " . ($result ? $result : 'none') . "\n", FILE_APPEND);
        if ($result) {
            file_put_contents($debugLog, date('c') . " | [DEBUG] Using candidate table: $result\n", FILE_APPEND);
            return $result;
        }
    }

    // Fallback: search for a table with username, password, and email columns (case-insensitive)
    $sql = "SELECT table_name FROM information_schema.columns WHERE table_schema = 'public' AND LOWER(column_name) IN ('username','password','email') GROUP BY table_name HAVING COUNT(DISTINCT LOWER(column_name)) = 3 LIMIT 1";
    $stmt = $con->prepare($sql);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    file_put_contents($debugLog, date('c') . " | [DEBUG] Fallback search result: " . (isset($row['table_name']) ? $row['table_name'] : 'none') . "\n", FILE_APPEND);
    if ($row && isset($row['table_name'])) {
        file_put_contents($debugLog, date('c') . " | [DEBUG] Using fallback table: " . $row['table_name'] . "\n", FILE_APPEND);
        return $row['table_name'];
    }
    file_put_contents($debugLog, date('c') . " | [DEBUG] No login table found in DB: $dbName\n", FILE_APPEND);
    return null;
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, array('success' => false, 'message' => 'Method not allowed'));
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}


$identifier = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

// Debug logging for troubleshooting (only for POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__ . '/login_debug.log', date('c') . " | identifier: $identifier | password: $password\n", FILE_APPEND);
}

if ($identifier === '' || $password === '') {
    respond(400, array('success' => false, 'message' => 'Email/username and password are required'));
}

$usernameCandidate = $identifier;
$atPos = strpos($identifier, '@');
if ($atPos !== false) {
    $usernameCandidate = substr($identifier, 0, $atPos);
}

include('db.pgsql.php');


function has_column($con, $dbName, $tableName, $columnName) {
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND LOWER(table_name) = LOWER(:tableName) AND LOWER(column_name) = LOWER(:columnName) LIMIT 1";
    $stmt = $con->prepare($sql);
    $stmt->execute([':tableName' => $tableName, ':columnName' => $columnName]);
    return $stmt->fetchColumn() ? true : false;
}

function resolve_column_name($con, $tableName, $columnName) {
    $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND LOWER(table_name) = LOWER(:tableName) AND LOWER(column_name) = LOWER(:columnName) ORDER BY CASE WHEN column_name = :preferred THEN 0 ELSE 1 END LIMIT 1";
    $stmt = $con->prepare($sql);
    $stmt->execute([
        ':tableName' => $tableName,
        ':columnName' => $columnName,
        ':preferred' => $columnName,
    ]);
    $resolved = $stmt->fetchColumn();
    return $resolved ? $resolved : $columnName;
}

$loginTable = find_login_table_name($con, $db);
if (!$loginTable) {
    respond(500, array(
        'success' => false,
        'message' => "Login table not found in database '$db'. Expected a table like Login_user_AWS with username/password/email columns."
    ));
}

$hasStatus = has_column($con, $db, $loginTable, 'status');
$hasOrgId = has_column($con, $db, $loginTable, 'org_id');
$hasOrgRole = has_column($con, $db, $loginTable, 'org_role');
$hasPhone = has_column($con, $db, $loginTable, 'phone');
$hasPermissions = has_column($con, $db, $loginTable, 'permissions');
$hasFreeUser = has_column($con, $db, $loginTable, 'is_free_user');
$hasGoogleOnly = has_column($con, $db, $loginTable, 'google_only');

$idColumn = resolve_column_name($con, $loginTable, 'id');
$usernameColumn = resolve_column_name($con, $loginTable, 'username');
$emailColumn = resolve_column_name($con, $loginTable, 'email');
$passwordColumn = resolve_column_name($con, $loginTable, 'password');
$statusColumn = resolve_column_name($con, $loginTable, 'status');
$phoneColumn = $hasPhone ? resolve_column_name($con, $loginTable, 'phone') : null;
$permissionsColumn = $hasPermissions ? resolve_column_name($con, $loginTable, 'permissions') : null;
$orgIdColumn = $hasOrgId ? resolve_column_name($con, $loginTable, 'org_id') : null;
$orgRoleColumn = $hasOrgRole ? resolve_column_name($con, $loginTable, 'org_role') : null;
$freeUserColumn = $hasFreeUser ? resolve_column_name($con, $loginTable, 'is_free_user') : null;
$googleOnlyColumn = $hasGoogleOnly ? resolve_column_name($con, $loginTable, 'google_only') : null;
$authTokenColumn = has_column($con, $db, $loginTable, 'auth_token') ? resolve_column_name($con, $loginTable, 'auth_token') : null;

$phoneExpr = $phoneColumn ? '"' . $phoneColumn . '" AS phone' : "'' AS phone";
$permissionsExpr = $permissionsColumn ? '"' . $permissionsColumn . '" AS permissions' : "'' AS permissions";
$orgIdExpr = $orgIdColumn ? '"' . $orgIdColumn . '" AS org_id' : '0 AS org_id';
$orgRoleExpr = $orgRoleColumn ? '"' . $orgRoleColumn . '" AS org_role' : "'viewer' AS org_role";
$freeUserExpr = $freeUserColumn ? '"' . $freeUserColumn . '" AS is_free_user' : '0 AS is_free_user';
$googleOnlyExpr = $googleOnlyColumn ? '"' . $googleOnlyColumn . '" AS google_only' : '0 AS google_only';

$whereStatus = $hasStatus ? '"' . $statusColumn . '" = :statusValue AND ' : '';

// Schema-adaptive login query for Joula_AWS (works with and without subscription columns).

$sql = "SELECT \"$idColumn\" AS id, \"$usernameColumn\" AS username, \"$emailColumn\" AS email, $phoneExpr, $permissionsExpr, $orgIdExpr, $orgRoleExpr, $freeUserExpr, $googleOnlyExpr FROM \"$loginTable\" WHERE $whereStatus (\"$usernameColumn\" = :identifier OR \"$usernameColumn\" = :usernameCandidate OR \"$emailColumn\" = :identifier2) AND \"$passwordColumn\" = :password LIMIT 1";
$stmt = $con->prepare($sql);
$userRow = null;
$stmt->execute([
    ':identifier' => $identifier,
    ':usernameCandidate' => $usernameCandidate,
    ':identifier2' => $identifier,
    ':statusValue' => 'true',
    ':password' => md5($password)
]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $userRow = $row;
    file_put_contents(__DIR__ . '/login_debug.log', date('c') . " | matched user: " . json_encode($row) . "\n", FILE_APPEND);
}

if (!$userRow) {
    file_put_contents(__DIR__ . '/login_debug.log', date('c') . " | login failed for: $identifier\n", FILE_APPEND);
    if ($hasGoogleOnly) {
        $googleOnlyCheckSql = "SELECT \"$googleOnlyColumn\" AS google_only FROM \"$loginTable\" WHERE $whereStatus (\"$usernameColumn\" = :identifier OR \"$usernameColumn\" = :usernameCandidate OR \"$emailColumn\" = :identifier2) LIMIT 1";
        $googleOnlyStmt = $con->prepare($googleOnlyCheckSql);
        $googleOnlyStmt->execute([
            ':identifier' => $identifier,
            ':usernameCandidate' => $usernameCandidate,
            ':identifier2' => $identifier,
            ':statusValue' => 'true'
        ]);
        $googleOnly = $googleOnlyStmt->fetchColumn();
        if ($googleOnly && intval($googleOnly) === 1) {
            respond(403, array('success' => false, 'message' => 'This account uses Google sign-in only. Please sign in with Google.'));
        }
    }
    respond(401, array('success' => false, 'message' => 'Invalid credentials'));
}

$permissions = permission_to_level(isset($userRow['permissions']) ? $userRow['permissions'] : '');
$role = $permissions >= 3 ? 'admin' : 'user';

if (function_exists('random_bytes')) {
    $token = bin2hex(random_bytes(24));
} else {
    $token = md5(uniqid('', true));
}

// Persist token in DB for stateless API auth
$tokenUpdateSql = $authTokenColumn ? "UPDATE \"$loginTable\" SET \"$authTokenColumn\" = :token WHERE \"$idColumn\" = :id" : null;
if ($tokenUpdateSql) {
    $stmtToken = $con->prepare($tokenUpdateSql);
    $stmtToken->execute([':token' => $token, ':id' => $userRow['id']]);
}

// Load subscription / org info
$subscription = null;
$orgId = isset($userRow['org_id']) ? intval($userRow['org_id']) : 0;
if ($orgId > 0) {
    $stmtOrg = $con->prepare("SELECT plan_status, trial_ends_at, COALESCE(free_account, 0) AS free_account FROM organizations WHERE id = :orgId LIMIT 1");
    $stmtOrg->execute([':orgId' => $orgId]);
    $row = $stmtOrg->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $planStatus = $row['plan_status'];
        $trialEndsAt = $row['trial_ends_at'];
        $freeAccount = $row['free_account'];
        if (!empty($freeAccount)) {
            $planStatus = 'active';
            $trialDaysLeft = 0;
        } else {
            $trialDaysLeft = 0;
            $trialEndsAtRaw = trim((string)$trialEndsAt);
            if ($trialEndsAtRaw !== '') {
                try {
                    // Auto-expire trial only when a valid trial end date exists.
                    $now = new DateTime('now', new DateTimeZone('UTC'));
                    $trialEnd = new DateTime($trialEndsAtRaw, new DateTimeZone('UTC'));
                    if ($planStatus === 'trial' && $now > $trialEnd) {
                        $planStatus = 'expired';
                        $expStmt = $con->prepare("UPDATE organizations SET plan_status='expired' WHERE id=:orgId");
                        $expStmt->execute([':orgId' => $orgId]);
                    }
                    $trialDaysLeft = max(0, (int)ceil(($trialEnd->getTimestamp() - $now->getTimestamp()) / 86400));
                } catch (Exception $e) {
                    file_put_contents(__DIR__ . '/login_debug.log', date('c') . " | [DEBUG] Invalid trial_ends_at for org $orgId: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
        }
        $subscription = [
            'orgId'        => $orgId,
            'orgRole'      => $userRow['org_role'] ?? 'viewer',
            'planStatus'   => $planStatus,
            'trialEndsAt'  => $trialEndsAt,
            'trialDaysLeft'=> $trialDaysLeft,
            'freeAccount'  => !empty($freeAccount),
        ];
    }
}

$user = array(
    'id' => intval($userRow['id']),
    'name' => $userRow['username'],
    'email' => $userRow['email'] ? $userRow['email'] : $identifier,
    'phone' => $userRow['phone'] ? $userRow['phone'] : '',
    'role' => $role,
    'permissionLevel' => $permissions,
    'orgRole' => $userRow['org_role'] ?? 'viewer',
    'isFreeUser' => !empty($userRow['is_free_user']),
    'createdAt' => date('c')
);

respond(200, array(
    'data' => array(
        'token' => $token,
        'user' => $user,
        'subscription' => $subscription
    )
));
