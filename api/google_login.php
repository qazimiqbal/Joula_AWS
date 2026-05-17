<?php
ob_start();
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "PHP error [$errno]: $errstr in $errfile on line $errline"]);
    exit;
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Fatal PHP error: {$error['message']} in {$error['file']} on line {$error['line']}"]);
    }
});
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond($statusCode, $payload) {
    ob_end_clean();
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, array('success' => false, 'message' => 'Method not allowed'));
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

if (!function_exists('curl_init')) {
    respond(500, array('success' => false, 'message' => 'cURL is not enabled on this server. Please enable the PHP cURL extension.'));
}

$idToken = isset($input['idToken']) ? trim($input['idToken']) : '';
if ($idToken === '') {
    respond(400, array('success' => false, 'message' => 'idToken is required'));
}

// ── Verify the Google token ───────────────────────────────────────────────────
// Supports both access_token (implicit flow) and id_token (code/PKCE flow).
// Try userinfo endpoint first (for access tokens), fall back to tokeninfo (for id tokens).
$googlePayload = null;

$userinfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
$curlHandle = curl_init($userinfoUrl);
curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curlHandle, CURLOPT_TIMEOUT, 10);
curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $idToken));
$curlResponse = curl_exec($curlHandle);
$curlError    = curl_error($curlHandle);
$httpStatus   = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
curl_close($curlHandle);

if (!$curlError && $httpStatus === 200) {
    $googlePayload = json_decode($curlResponse, true);
}

// Fallback: treat token as id_token and use tokeninfo
if (!$googlePayload || !isset($googlePayload['sub'])) {
    $verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $curlHandle = curl_init($verifyUrl);
    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curlHandle, CURLOPT_TIMEOUT, 10);
    $curlResponse = curl_exec($curlHandle);
    $curlError    = curl_error($curlHandle);
    $httpStatus   = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
    curl_close($curlHandle);

    if (!$curlError && $httpStatus === 200) {
        $googlePayload = json_decode($curlResponse, true);
    }
}

if (!$googlePayload || !isset($googlePayload['sub']) || !isset($googlePayload['email'])) {
    respond(401, array('success' => false, 'message' => 'Google token verification failed'));
}

$googleSub   = $googlePayload['sub'];
$googleEmail = strtolower(trim($googlePayload['email']));
$googleName  = isset($googlePayload['name']) ? $googlePayload['name'] : $googleEmail;

// ── Connect to DB ─────────────────────────────────────────────────────────────
include('db.php');
if (!mysqli_select_db($con, $db)) {
    respond(500, array('success' => false, 'message' => 'Database connection failed'));
}

// Resolve the user table name
$loginTable = 'Login_user_AWS';
$candidateTables = array('Login_user_AWS', 'login_user_aws', 'Login_User_AWS', 'Login_user', 'login_user', 'users');
foreach ($candidateTables as $tbl) {
    $escaped = mysqli_real_escape_string($con, $tbl);
    $res = mysqli_query($con, "SHOW TABLES LIKE '$escaped'");
    if ($res && mysqli_fetch_row($res)) {
        $loginTable = $tbl;
        break;
    }
}

$quotedTable = sql_quote_identifier($loginTable);

// ── Ensure google_sub column exists ──────────────────────────────────────────
$hasGoogleSub = (bool) query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'google_sub'");
if (!$hasGoogleSub) {
    mysqli_query($con, "ALTER TABLE $quotedTable ADD COLUMN google_sub VARCHAR(128) DEFAULT NULL");
    $hasGoogleSub = (bool) query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'google_sub'");
}

// Detect optional columns
$hasPhone      = (bool) query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'phone'");
$hasPermissions= (bool) query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'Permissions'");
$hasStatus     = (bool) query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'status'");
$hasOrgId      = (bool) query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'org_id'");
$hasOrgRole    = (bool) query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'org_role'");
$hasFreeUser   = (bool) query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'is_free_user'");
$hasAuthToken  = (bool) query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'auth_token'");

if (!$hasAuthToken) {
    mysqli_query($con, "ALTER TABLE $quotedTable ADD COLUMN auth_token VARCHAR(255) DEFAULT NULL");
    $hasAuthToken = (bool) query_single_value($con, "SHOW COLUMNS FROM $quotedTable LIKE 'auth_token'");
}

$phoneExpr       = $hasPhone       ? 'phone'              : "'' AS phone";
$permissionsExpr = $hasPermissions ? 'Permissions'        : "'' AS Permissions";
$orgIdExpr       = $hasOrgId       ? 'org_id'             : '0 AS org_id';
$orgRoleExpr     = $hasOrgRole     ? 'org_role'           : "'viewer' AS org_role";
$freeUserExpr    = $hasFreeUser    ? 'is_free_user'       : '0 AS is_free_user';
$googleSubExpr   = $hasGoogleSub   ? 'google_sub'         : "'' AS google_sub";
$statusCond      = $hasStatus      ? "status = 'true' AND " : "";

$userRow = null;

// ── 1. Look up by google_sub ──────────────────────────────────────────────────
if ($hasGoogleSub) {
    $sql = "SELECT id, username, email, $phoneExpr, $permissionsExpr, $orgIdExpr, $orgRoleExpr, $freeUserExpr, $googleSubExpr
            FROM $quotedTable
            WHERE $statusCond google_sub = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $googleSub);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $id, $username, $email, $phone, $permissionsRaw, $orgId, $orgRole, $isFreeUserRaw, $gSub);
        if (mysqli_stmt_fetch($stmt)) {
            $userRow = compact('id','username','email','phone','permissionsRaw','orgId','orgRole','isFreeUserRaw');
            $userRow['Permissions'] = $permissionsRaw;
            $userRow['org_id']      = $orgId;
            $userRow['org_role']    = $orgRole;
            $userRow['is_free_user']= $isFreeUserRaw;
        }
        mysqli_stmt_close($stmt);
    }
}

// ── 2. Look up by email (link the account) ───────────────────────────────────
if (!$userRow) {
    $sql = "SELECT id, username, email, $phoneExpr, $permissionsExpr, $orgIdExpr, $orgRoleExpr, $freeUserExpr
            FROM $quotedTable
            WHERE $statusCond email = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $googleEmail);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $id, $username, $email, $phone, $permissionsRaw, $orgId, $orgRole, $isFreeUserRaw);
        if (mysqli_stmt_fetch($stmt)) {
            $userRow = array(
                'id'          => $id,
                'username'    => $username,
                'email'       => $email,
                'phone'       => $phone,
                'Permissions' => $permissionsRaw,
                'org_id'      => $orgId,
                'org_role'    => $orgRole,
                'is_free_user'=> $isFreeUserRaw,
            );
            // Store google_sub to speed up future logins
            if ($hasGoogleSub) {
                $linkStmt = mysqli_prepare($con, "UPDATE $quotedTable SET google_sub = ? WHERE id = ? LIMIT 1");
                if ($linkStmt) {
                    mysqli_stmt_bind_param($linkStmt, 'si', $googleSub, $id);
                    mysqli_stmt_execute($linkStmt);
                    mysqli_stmt_close($linkStmt);
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// ── 2b. Existing email found but not active yet ─────────────────────────────
if (!$userRow && $hasStatus) {
    $pendingSql = "SELECT id FROM $quotedTable WHERE email = ? AND status <> 'true' LIMIT 1";
    $pendingStmt = mysqli_prepare($con, $pendingSql);
    if ($pendingStmt) {
        mysqli_stmt_bind_param($pendingStmt, 's', $googleEmail);
        mysqli_stmt_execute($pendingStmt);
        mysqli_stmt_bind_result($pendingStmt, $pendingId);
        if (mysqli_stmt_fetch($pendingStmt)) {
            mysqli_stmt_close($pendingStmt);
            respond(403, array(
                'success' => false,
                'message' => 'Your account is pending administrator approval. Please contact your administrator.',
                'pendingApproval' => true,
                'email' => $googleEmail,
            ));
        }
        mysqli_stmt_close($pendingStmt);
    }
}

// ── 3. No existing account → create a pending user ───────────────────────────
if (!$userRow) {
    // Generate a random placeholder password (never used for password auth)
    $placeholder = bin2hex(random_bytes(16));
    $escapedName  = mysqli_real_escape_string($con, $googleName);
    $escapedEmail = mysqli_real_escape_string($con, $googleEmail);
    $escapedSub   = $hasGoogleSub ? mysqli_real_escape_string($con, $googleSub) : null;

    $googleSubCol = $hasGoogleSub ? ', google_sub' : '';
    $googleSubVal = $hasGoogleSub ? ", '$escapedSub'" : '';
    $statusCol    = $hasStatus    ? ', status'      : '';
    $statusVal    = $hasStatus    ? ", 'false'"     : '';   // requires admin approval

    $insertSql = "INSERT INTO $quotedTable (username, email, password, Permissions $googleSubCol $statusCol)
                  VALUES ('$escapedName', '$escapedEmail', MD5('$placeholder'), '3' $googleSubVal $statusVal)";

    if (!mysqli_query($con, $insertSql)) {
        respond(500, array('success' => false, 'message' => 'Could not create user account'));
    }

    $newId = mysqli_insert_id($con);

    respond(403, array(
        'success' => false,
        'message'  => 'Account created but pending admin approval. Please contact your administrator.',
        'pendingApproval' => true,
        'email' => $googleEmail,
    ));
}

// ── Issue a new bearer token ──────────────────────────────────────────────────
if (function_exists('random_bytes')) {
    $token = bin2hex(random_bytes(24));
} else {
    $token = md5(uniqid('', true));
}

$stmtToken = mysqli_prepare($con, "UPDATE $quotedTable SET auth_token = ? WHERE id = ? LIMIT 1");
if (!$stmtToken) {
    respond(500, array('success' => false, 'message' => 'Failed to prepare auth token update: ' . mysqli_error($con)));
}

$userIdForToken = intval($userRow['id']);
mysqli_stmt_bind_param($stmtToken, 'si', $token, $userIdForToken);
if (!mysqli_stmt_execute($stmtToken)) {
    $stmtErr = mysqli_stmt_error($stmtToken);
    mysqli_stmt_close($stmtToken);
    respond(500, array('success' => false, 'message' => 'Failed to persist auth token: ' . $stmtErr));
}
mysqli_stmt_close($stmtToken);

// ── Load subscription / org info ──────────────────────────────────────────────
$subscription = null;
$orgId = isset($userRow['org_id']) ? intval($userRow['org_id']) : 0;
if ($orgId > 0) {
    $orgIdSafe = intval($orgId);
    $orgRes = mysqli_query($con, "SELECT plan_status, trial_ends_at, COALESCE(free_account, 0) AS free_account FROM organizations WHERE id = $orgIdSafe LIMIT 1");
    if ($orgRes) {
        $orgRow = mysqli_fetch_assoc($orgRes);
        mysqli_free_result($orgRes);

        if ($orgRow) {
            $planStatus = isset($orgRow['plan_status']) ? $orgRow['plan_status'] : null;
            $trialEndsAt = isset($orgRow['trial_ends_at']) ? $orgRow['trial_ends_at'] : null;
            $freeAccount = !empty($orgRow['free_account']);

            if ($freeAccount) {
                $planStatus = 'active';
                $trialDaysLeft = 0;
            } else {
                $now = new DateTime('now', new DateTimeZone('UTC'));
                if (!empty($trialEndsAt)) {
                    $trialEnd = new DateTime($trialEndsAt, new DateTimeZone('UTC'));
                    if ($planStatus === 'trial' && $now > $trialEnd) {
                        $planStatus = 'expired';
                        mysqli_query($con, "UPDATE organizations SET plan_status='expired' WHERE id=$orgIdSafe");
                    }
                    $trialDaysLeft = max(0, (int)ceil(($trialEnd->getTimestamp() - $now->getTimestamp()) / 86400));
                } else {
                    $trialDaysLeft = 0;
                }
            }

            $subscription = [
                'orgId'        => $orgIdSafe,
                'orgRole'      => $userRow['org_role'] ?? 'viewer',
                'planStatus'   => $planStatus,
                'trialEndsAt'  => $trialEndsAt,
                'trialDaysLeft'=> $trialDaysLeft,
                'freeAccount'  => $freeAccount,
            ];
        }
    }
}

$permissions = permission_to_level(isset($userRow['Permissions']) ? $userRow['Permissions'] : '');
$role = $permissions >= 3 ? 'admin' : 'user';

$user = array(
    'id'             => intval($userRow['id']),
    'name'           => $userRow['username'],
    'email'          => $userRow['email'],
    'phone'          => $userRow['phone'] ?? '',
    'role'           => $role,
    'permissionLevel'=> $permissions,
    'orgRole'        => $userRow['org_role'] ?? 'viewer',
    'isFreeUser'     => !empty($userRow['is_free_user']),
    'createdAt'      => date('c'),
);

respond(200, array(
    'success' => true,
    'data' => array(
        'token'        => $token,
        'user'         => $user,
        'subscription' => $subscription,
    )
));
