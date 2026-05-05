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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, array('success' => false, 'message' => 'Method not allowed'));
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : '';

if ($username === '' || $password === '' || $email === '' || $phone === '') {
    respond(400, array('success' => false, 'message' => 'Username, password, email, and phone are required'));
}

include('db.php');
if (!mysqli_select_db($con, $db)) {
    respond(500, array(
        'success' => false,
        'message' => "Failed to select database '$db' in register.php: " . mysqli_error($con)
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

function has_table($con, $dbName, $tableName) {
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'ss', $dbName, $tableName);
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

$hasPhone = has_column($con, $db, $loginTable, 'phone');
$hasMasjid = has_column($con, $db, $loginTable, 'Masjid');
$hasLocality = has_column($con, $db, $loginTable, 'Locality');
$hasHalaqa = has_column($con, $db, $loginTable, 'Halaqa');
$hasPermissions = has_column($con, $db, $loginTable, 'Permissions');
$hasPassChange = has_column($con, $db, $loginTable, 'Pass_change');
$hasStatus = has_column($con, $db, $loginTable, 'status');
$hasOrgRole = has_column($con, $db, $loginTable, 'org_role');
$hasOrgId = has_column($con, $db, $loginTable, 'org_id');

// Check duplicate username
$usernameExists = false;
$stmtUsername = mysqli_prepare($con, "SELECT id FROM `$loginTable` WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmtUsername, 's', $username);
mysqli_stmt_execute($stmtUsername);
mysqli_stmt_bind_result($stmtUsername, $existingUserId);
if (mysqli_stmt_fetch($stmtUsername)) {
    $usernameExists = true;
}
mysqli_stmt_close($stmtUsername);

// Check duplicate password hash
$passwordExists = false;
$stmtPassword = mysqli_prepare($con, "SELECT id FROM `$loginTable` WHERE password = MD5(?) LIMIT 1");
mysqli_stmt_bind_param($stmtPassword, 's', $password);
mysqli_stmt_execute($stmtPassword);
mysqli_stmt_bind_result($stmtPassword, $existingPasswordUserId);
if (mysqli_stmt_fetch($stmtPassword)) {
    $passwordExists = true;
}
mysqli_stmt_close($stmtPassword);

// Check duplicate email
$emailExists = false;
$stmtEmail = mysqli_prepare($con, "SELECT id FROM `$loginTable` WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($stmtEmail, 's', $email);
mysqli_stmt_execute($stmtEmail);
mysqli_stmt_bind_result($stmtEmail, $existingEmailUserId);
if (mysqli_stmt_fetch($stmtEmail)) {
    $emailExists = true;
}
mysqli_stmt_close($stmtEmail);

if ($usernameExists || $passwordExists || $emailExists) {
    $messageParts = array();
    if ($usernameExists) {
        $messageParts[] = 'username already exists';
    }
    if ($passwordExists) {
        $messageParts[] = 'password already exists';
    }
    if ($emailExists) {
        $messageParts[] = 'email already exists';
    }

    respond(409, array(
        'success' => false,
        'message' => 'This ' . implode(' and ', $messageParts) . '. Please change and try again.'
    ));
}

// New sign-ups default to Admin (3). The very first account becomes Super Admin (4).
$permissions = '3';
$resultUserCount = mysqli_query($con, "SELECT COUNT(*) FROM `$loginTable`");
$rowUserCount = $resultUserCount ? mysqli_fetch_row($resultUserCount) : null;
$userCount = $rowUserCount ? intval($rowUserCount[0]) : 0;
if ($resultUserCount) {
    mysqli_free_result($resultUserCount);
}
if ($userCount === 0) {
    $permissions = '4';
}
$status = 'true';
$passChange = 'No';
$masjid = '';
$locality = '';
$halaqa = '';

$insertColumns = array('username', 'password', 'email');
$insertValuesSql = array('?', 'MD5(?)', '?');
$bindTypes = 'sss';
$bindValues = array($username, $password, $email);

if ($hasPhone) {
    $insertColumns[] = 'phone';
    $insertValuesSql[] = '?';
    $bindTypes .= 's';
    $bindValues[] = $phone;
}
if ($hasMasjid) {
    $insertColumns[] = 'Masjid';
    $insertValuesSql[] = '?';
    $bindTypes .= 's';
    $bindValues[] = $masjid;
}
if ($hasLocality) {
    $insertColumns[] = 'Locality';
    $insertValuesSql[] = '?';
    $bindTypes .= 's';
    $bindValues[] = $locality;
}
if ($hasHalaqa) {
    $insertColumns[] = 'Halaqa';
    $insertValuesSql[] = '?';
    $bindTypes .= 's';
    $bindValues[] = $halaqa;
}
if ($hasPermissions) {
    $insertColumns[] = 'Permissions';
    $insertValuesSql[] = '?';
    $bindTypes .= 's';
    $bindValues[] = $permissions;
}
if ($hasPassChange) {
    $insertColumns[] = 'Pass_change';
    $insertValuesSql[] = '?';
    $bindTypes .= 's';
    $bindValues[] = $passChange;
}
if ($hasStatus) {
    $insertColumns[] = 'status';
    $insertValuesSql[] = '?';
    $bindTypes .= 's';
    $bindValues[] = $status;
}
if ($hasOrgRole) {
    $insertColumns[] = 'org_role';
    if ($userCount === 0) {
        $insertValuesSql[] = "'org_admin'";
    } else {
        $insertValuesSql[] = "'admin'";
    }
}

$sqlInsert = "INSERT INTO `$loginTable` (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValuesSql) . ")";
$stmtInsert = mysqli_prepare($con, $sqlInsert);

if (!$stmtInsert) {
    respond(500, array('success' => false, 'message' => 'Failed to prepare registration query: ' . mysqli_error($con)));
}

$bindParams = array($bindTypes);
foreach ($bindValues as $k => $v) {
    $bindParams[] = &$bindValues[$k];
}
call_user_func_array(array($stmtInsert, 'bind_param'), $bindParams);

if (!mysqli_stmt_execute($stmtInsert)) {
    mysqli_stmt_close($stmtInsert);
    respond(500, array('success' => false, 'message' => 'Failed to create account: ' . mysqli_error($con)));
}
$newUserId = mysqli_insert_id($con);
mysqli_stmt_close($stmtInsert);

// Create organization with 30-day trial when subscription tables exist.
$hasOrganizations = has_table($con, $db, 'organizations');
if ($hasOrganizations && $hasOrgId) {
    $orgName      = $username . "'s Organization";
    $trialEndsAt  = date('Y-m-d H:i:s', strtotime('+30 days'));
    $stmtOrg = mysqli_prepare($con,
        "INSERT INTO organizations (name, owner_user_id, plan_status, trial_ends_at) VALUES (?, ?, 'trial', ?)");
    if ($stmtOrg) {
        mysqli_stmt_bind_param($stmtOrg, 'sis', $orgName, $newUserId, $trialEndsAt);
        mysqli_stmt_execute($stmtOrg);
        $orgId = mysqli_insert_id($con);
        mysqli_stmt_close($stmtOrg);

        // Link user to their new org
        $stmtLink = mysqli_prepare($con,
            "UPDATE `$loginTable` SET org_id = ? WHERE id = ? LIMIT 1");
        if ($stmtLink) {
            mysqli_stmt_bind_param($stmtLink, 'ii', $orgId, $newUserId);
            mysqli_stmt_execute($stmtLink);
            mysqli_stmt_close($stmtLink);
        }
    }
}

respond(200, array(
    'success' => true,
    'message' => 'Account created successfully. You have a 30-day free trial.'
));
