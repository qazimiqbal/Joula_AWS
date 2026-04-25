<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function permission_to_level($permissionRaw) {
    $value = trim((string)$permissionRaw);
    if ($value === '3' || strcasecmp($value, 'Super Administrator') === 0) return 3;
    if ($value === '2' || strcasecmp($value, 'Administrator') === 0) return 2;
    if ($value === '1' || strcasecmp($value, 'Editor') === 0) return 1;
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

if ($identifier === '' || $password === '') {
    respond(400, array('success' => false, 'message' => 'Email/username and password are required'));
}

$usernameCandidate = $identifier;
$atPos = strpos($identifier, '@');
if ($atPos !== false) {
    $usernameCandidate = substr($identifier, 0, $atPos);
}

include('db.php');
if (!mysqli_select_db($con, $db)) {
    respond(500, array(
        'success' => false,
        'message' => "Failed to select database '$db' in login.php: " . mysqli_error($con)
    ));
}

function sql_quote_identifier($identifier) {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function query_single_value($con, $sql) {
    $result = mysqli_query($con, $sql);
    if (!$result) return null;
    $row = mysqli_fetch_row($result);
    mysqli_free_result($result);
    return $row ? $row[0] : null;
}

function table_exists($con, $tableName) {
    $escapedTable = mysqli_real_escape_string($con, $tableName);
    $result = mysqli_query($con, "SHOW TABLES LIKE '$escapedTable'");
    if (!$result) return false;
    $row = mysqli_fetch_row($result);
    mysqli_free_result($result);
    return $row ? $row[0] : false;
}

function find_login_table_name($con, $dbName) {
    $candidateTables = array(
        'Login_user_AWS',
        'login_user_aws',
        'Login_User_AWS',
        'Login_user',
        'login_user',
        'Login_User',
        'users'
    );

    foreach ($candidateTables as $candidateTable) {
        $matchedTable = table_exists($con, $candidateTable);
        if ($matchedTable) {
            return $matchedTable;
        }
    }

    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND (LOWER(table_name) IN ('login_user_aws', 'login_user', 'users') OR (LOWER(table_name) LIKE 'login%user%' AND LOWER(table_name) NOT LIKE 'idx_%')) ORDER BY CASE LOWER(table_name) WHEN 'login_user_aws' THEN 1 WHEN 'login_user' THEN 2 WHEN 'users' THEN 3 ELSE 4 END, table_name LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 's', $dbName);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $tableName);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ($found && $tableName) {
        return $tableName;
    }

    $tablesResult = mysqli_query($con, 'SHOW TABLES');
    if (!$tablesResult) return null;

    while ($tableRow = mysqli_fetch_row($tablesResult)) {
        $tableName = $tableRow[0];
        $quotedTable = sql_quote_identifier($tableName);
        $usernameColumn = query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'username'");
        $passwordColumn = query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'password'");
        $emailColumn = query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'email'");
        if ($usernameColumn && $passwordColumn && $emailColumn) {
            mysqli_free_result($tablesResult);
            return $tableName;
        }
    }

    mysqli_free_result($tablesResult);
    return null;
}

function get_table_diagnostics($con) {
    $diagnostics = array(
        'tables' => array(),
        'matches' => array(),
        'showTablesError' => null,
    );

    $tablesResult = mysqli_query($con, 'SHOW TABLES');
    if (!$tablesResult) {
        $diagnostics['showTablesError'] = mysqli_error($con);
        return $diagnostics;
    }

    while ($tableRow = mysqli_fetch_row($tablesResult)) {
        $tableName = $tableRow[0];
        $diagnostics['tables'][] = $tableName;

        $quotedTable = sql_quote_identifier($tableName);
        $hasUsername = query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'username'") ? true : false;
        $hasPassword = query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'password'") ? true : false;
        $hasEmail = query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'email'") ? true : false;

        if ($hasUsername || $hasPassword || $hasEmail) {
            $diagnostics['matches'][] = array(
                'table' => $tableName,
                'username' => $hasUsername,
                'password' => $hasPassword,
                'email' => $hasEmail,
            );
        }
    }

    mysqli_free_result($tablesResult);
    return $diagnostics;
}

function has_column($con, $dbName, $tableName, $columnName) {
    $quotedTable = sql_quote_identifier($tableName);
    $escapedColumn = mysqli_real_escape_string($con, $columnName);
    $result = mysqli_query($con, "SHOW COLUMNS FROM $quotedTable LIKE '$escapedColumn'");
    if ($result) {
        $row = mysqli_fetch_row($result);
        mysqli_free_result($result);
        if ($row) {
            return true;
        }
    }

    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'sss', $dbName, $tableName, $columnName);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $exists);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $found ? true : false;
}

$loginTable = find_login_table_name($con, $db);
if (!$loginTable) {
    respond(500, array(
        'success' => false,
        'message' => "Login table not found in database '$db'. Expected a table like Login_user_AWS with username/password/email columns.",
        'diagnostics' => get_table_diagnostics($con)
    ));
}

$hasStatus = has_column($con, $db, $loginTable, 'status');
$hasOrgId = has_column($con, $db, $loginTable, 'org_id');
$hasOrgRole = has_column($con, $db, $loginTable, 'org_role');
$hasPhone = has_column($con, $db, $loginTable, 'phone');
$hasPermissions = has_column($con, $db, $loginTable, 'Permissions');

$phoneExpr = $hasPhone ? 'phone' : "'' AS phone";
$permissionsExpr = $hasPermissions ? 'Permissions' : "'' AS Permissions";
$orgIdExpr = $hasOrgId ? 'org_id' : '0 AS org_id';
$orgRoleExpr = $hasOrgRole ? "org_role" : "'viewer' AS org_role";

$whereStatus = $hasStatus ? "status = 'true' AND " : "";

// Schema-adaptive login query for Joula_AWS (works with and without subscription columns).
$sql = "SELECT id, username, email, $phoneExpr, $permissionsExpr, $orgIdExpr, $orgRoleExpr FROM `$loginTable` WHERE $whereStatus (username = ? OR username = ? OR email = ?) AND password = MD5(?) LIMIT 1";
$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
    respond(500, array('success' => false, 'message' => 'Failed to prepare login query: ' . mysqli_error($con)));
}

$userRow = null;
mysqli_stmt_bind_param($stmt, 'ssss', $identifier, $usernameCandidate, $identifier, $password);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $id, $username, $email, $phone, $permissionsRaw, $orgId, $orgRole);
if (mysqli_stmt_fetch($stmt)) {
    $userRow = array(
        'id' => $id,
        'username' => $username,
        'email' => $email,
        'phone' => $phone,
        'Permissions' => $permissionsRaw,
        'org_id' => $orgId,
        'org_role' => $orgRole,
    );
}
mysqli_stmt_close($stmt);

if (!$userRow) {
    respond(401, array('success' => false, 'message' => 'Invalid credentials'));
}

$permissions = permission_to_level(isset($userRow['Permissions']) ? $userRow['Permissions'] : '');
$role = $permissions >= 3 ? 'admin' : 'user';

if (function_exists('random_bytes')) {
    $token = bin2hex(random_bytes(24));
} else {
    $token = md5(uniqid('', true));
}

// Persist token in DB for stateless API auth
$stmtToken = mysqli_prepare($con, "UPDATE `{$loginTable}` SET auth_token = ? WHERE id = ? LIMIT 1");
if ($stmtToken) {
    mysqli_stmt_bind_param($stmtToken, 'si', $token, $userRow['id']);
    mysqli_stmt_execute($stmtToken);
    mysqli_stmt_close($stmtToken);
}

// Load subscription / org info
$subscription = null;
$orgId = isset($userRow['org_id']) ? intval($userRow['org_id']) : 0;
if ($orgId > 0) {
    $stmtOrg = mysqli_prepare($con,
        "SELECT plan_status, trial_ends_at FROM organizations WHERE id = ? LIMIT 1");
    if ($stmtOrg) {
        mysqli_stmt_bind_param($stmtOrg, 'i', $orgId);
        mysqli_stmt_execute($stmtOrg);
        $planStatus = $trialEndsAt = null;
        mysqli_stmt_bind_result($stmtOrg, $planStatus, $trialEndsAt);
        if (mysqli_stmt_fetch($stmtOrg)) {
            // Auto-expire trial
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $trialEnd = new DateTime($trialEndsAt, new DateTimeZone('UTC'));
            if ($planStatus === 'trial' && $now > $trialEnd) {
                $planStatus = 'expired';
                $expStmt = mysqli_prepare($con, "UPDATE organizations SET plan_status='expired' WHERE id=?");
                mysqli_stmt_bind_param($expStmt, 'i', $orgId);
                mysqli_stmt_execute($expStmt);
                mysqli_stmt_close($expStmt);
            }
            $trialDaysLeft = max(0, (int)ceil(($trialEnd->getTimestamp() - $now->getTimestamp()) / 86400));
            $subscription = [
                'orgId'        => $orgId,
                'orgRole'      => $userRow['org_role'] ?? 'viewer',
                'planStatus'   => $planStatus,
                'trialEndsAt'  => $trialEndsAt,
                'trialDaysLeft'=> $trialDaysLeft,
            ];
        }
        mysqli_stmt_close($stmtOrg);
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
    'createdAt' => date('c')
);

respond(200, array(
    'success' => true,
    'data' => array(
        'token' => $token,
        'user' => $user,
        'subscription' => $subscription
    )
));
