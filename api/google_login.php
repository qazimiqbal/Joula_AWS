<?php
include_once __DIR__ . '/cors.php';

ob_start();
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "PHP error [$errno]: $errstr in $errfile on line $errline",
    ]);
    exit;
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Fatal PHP error: {$error['message']} in {$error['file']} on line {$error['line']}",
        ]);
    }
});

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond($statusCode, $payload)
{
    ob_end_clean();
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function permission_to_level($permissionRaw)
{
    $value = trim((string) $permissionRaw);
    if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
    if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
    if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
    if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
    if (is_numeric($value)) return intval($value);
    return 0;
}

function quote_ident($identifier)
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function pdo_column_name($con, $table, $column)
{
    $stmt = $con->prepare(
        'SELECT column_name FROM information_schema.columns WHERE table_schema = :schema AND lower(table_name) = lower(:table) AND lower(column_name) = lower(:column) LIMIT 1'
    );
    $stmt->execute([
        ':schema' => 'public',
        ':table' => $table,
        ':column' => $column,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['column_name'] : null;
}

function verify_google_token($token)
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $googlePayload = null;

    $userinfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
    $ch = curl_init($userinfoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (!$err && $code === 200) {
        $googlePayload = json_decode($body, true);
    }

    if (!$googlePayload || !isset($googlePayload['sub'])) {
        $verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token);
        $ch2 = curl_init($verifyUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
        $body2 = curl_exec($ch2);
        $err2 = curl_error($ch2);
        $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if (!$err2 && $code2 === 200) {
            $googlePayload = json_decode($body2, true);
        }
    }

    return $googlePayload;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed']);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$idToken = isset($input['idToken']) ? trim((string) $input['idToken']) : '';
if ($idToken === '') {
    respond(400, ['success' => false, 'message' => 'idToken is required']);
}

$googlePayload = verify_google_token($idToken);
if (!$googlePayload || !isset($googlePayload['sub']) || !isset($googlePayload['email'])) {
    respond(401, ['success' => false, 'message' => 'Google token verification failed']);
}

$googleSub = (string) $googlePayload['sub'];
$googleEmail = strtolower(trim((string) $googlePayload['email']));
$googleName = isset($googlePayload['name']) ? (string) $googlePayload['name'] : $googleEmail;

include 'db.php';
if (!$con) {
    respond(500, ['success' => false, 'message' => 'Database connection failed']);
}

try {
    $loginTable = 'Login_user_AWS';
    $candidateTables = ['Login_user_AWS', 'login_user_aws', 'Login_User_AWS', 'Login_user', 'login_user', 'users'];
    foreach ($candidateTables as $tbl) {
        $stmt = $con->prepare("SELECT to_regclass('public.' || :qname) AS reg");
        $stmt->execute([':qname' => quote_ident($tbl)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['reg'])) {
            $loginTable = $tbl;
            break;
        }
    }

    $tableQ = quote_ident($loginTable);

    $colId = pdo_column_name($con, $loginTable, 'id') ?: 'id';
    $colUsername = pdo_column_name($con, $loginTable, 'username') ?: 'username';
    $colEmail = pdo_column_name($con, $loginTable, 'email') ?: 'email';
    $colPassword = pdo_column_name($con, $loginTable, 'password') ?: 'password';
    $colPhone = pdo_column_name($con, $loginTable, 'phone');
    $colPermissions = pdo_column_name($con, $loginTable, 'permissions') ?: pdo_column_name($con, $loginTable, 'Permissions');
    $colOrgId = pdo_column_name($con, $loginTable, 'org_id');
    $colOrgRole = pdo_column_name($con, $loginTable, 'org_role');
    $colFreeUser = pdo_column_name($con, $loginTable, 'is_free_user');
    $colStatus = pdo_column_name($con, $loginTable, 'status');
    $colAuthToken = pdo_column_name($con, $loginTable, 'auth_token');
    $colGoogleSub = pdo_column_name($con, $loginTable, 'google_sub');

    if (!$colGoogleSub) {
        $con->exec("ALTER TABLE $tableQ ADD COLUMN google_sub VARCHAR(128) DEFAULT NULL");
        $colGoogleSub = pdo_column_name($con, $loginTable, 'google_sub');
    }
    if (!$colAuthToken) {
        $con->exec("ALTER TABLE $tableQ ADD COLUMN auth_token VARCHAR(255) DEFAULT NULL");
        $colAuthToken = pdo_column_name($con, $loginTable, 'auth_token');
    }

    $idQ = quote_ident($colId);
    $usernameQ = quote_ident($colUsername);
    $emailQ = quote_ident($colEmail);
    $phoneExpr = $colPhone ? quote_ident($colPhone) : "'' AS phone";
    $permissionsExpr = $colPermissions ? quote_ident($colPermissions) : "'' AS permissions";
    $orgIdExpr = $colOrgId ? quote_ident($colOrgId) : '0 AS org_id';
    $orgRoleExpr = $colOrgRole ? quote_ident($colOrgRole) : "'viewer' AS org_role";
    $freeUserExpr = $colFreeUser ? quote_ident($colFreeUser) : '0 AS is_free_user';
    $googleSubExpr = $colGoogleSub ? quote_ident($colGoogleSub) : "'' AS google_sub";

    $statusCond = '1=1';
    if ($colStatus) {
        $statusQ = quote_ident($colStatus);
        $statusCond = "COALESCE(CAST($statusQ AS text), '') ILIKE 'true'";
    }

    $userRow = null;

    if ($colGoogleSub) {
        $googleSubQ = quote_ident($colGoogleSub);
        $sql = "SELECT $idQ AS id, $usernameQ AS username, $emailQ AS email, $phoneExpr, $permissionsExpr AS permissions, $orgIdExpr AS org_id, $orgRoleExpr AS org_role, $freeUserExpr AS is_free_user, $googleSubExpr FROM $tableQ WHERE $statusCond AND $googleSubQ = :googleSub LIMIT 1";
        $stmt = $con->prepare($sql);
        $stmt->execute([':googleSub' => $googleSub]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$userRow) {
        $emailQWhere = quote_ident($colEmail);
        $sql = "SELECT $idQ AS id, $usernameQ AS username, $emailQ AS email, $phoneExpr, $permissionsExpr AS permissions, $orgIdExpr AS org_id, $orgRoleExpr AS org_role, $freeUserExpr AS is_free_user FROM $tableQ WHERE $statusCond AND $emailQWhere = :email LIMIT 1";
        $stmt = $con->prepare($sql);
        $stmt->execute([':email' => $googleEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $userRow = $row;
            if ($colGoogleSub) {
                $googleSubQ = quote_ident($colGoogleSub);
                $stmtUp = $con->prepare("UPDATE $tableQ SET $googleSubQ = :googleSub WHERE $idQ = :id");
                $stmtUp->execute([':googleSub' => $googleSub, ':id' => intval($row['id'])]);
            }
        }
    }

    if (!$userRow && $colStatus) {
        $statusQ = quote_ident($colStatus);
        $emailQWhere = quote_ident($colEmail);
        $pendingSql = "SELECT $idQ AS id FROM $tableQ WHERE $emailQWhere = :email AND NOT (COALESCE(CAST($statusQ AS text), '') ILIKE 'true') LIMIT 1";
        $stmtPending = $con->prepare($pendingSql);
        $stmtPending->execute([':email' => $googleEmail]);
        $pendingRow = $stmtPending->fetch(PDO::FETCH_ASSOC);
        if ($pendingRow) {
            respond(403, [
                'success' => false,
                'message' => 'Your account is pending administrator approval. Please contact your administrator.',
                'pendingApproval' => true,
                'email' => $googleEmail,
            ]);
        }
    }

    if (!$userRow) {
        $placeholder = bin2hex(random_bytes(16));
        $hashed = password_hash($placeholder, PASSWORD_DEFAULT);

        $cols = [$usernameQ, quote_ident($colEmail), quote_ident($colPassword)];
        $vals = [':username', ':email', ':password'];
        $params = [
            ':username' => $googleName,
            ':email' => $googleEmail,
            ':password' => $hashed,
        ];

        if ($colPermissions) {
            $cols[] = quote_ident($colPermissions);
            $vals[] = ':permissions';
            $params[':permissions'] = '3';
        }
        if ($colGoogleSub) {
            $cols[] = quote_ident($colGoogleSub);
            $vals[] = ':googleSub';
            $params[':googleSub'] = $googleSub;
        }
        if ($colStatus) {
            $cols[] = quote_ident($colStatus);
            $vals[] = ':status';
            $params[':status'] = 'false';
        }
        if ($colOrgRole) {
            $cols[] = quote_ident($colOrgRole);
            $vals[] = ':orgRole';
            $params[':orgRole'] = 'admin';
        }

        $insertSql = 'INSERT INTO ' . $tableQ . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ') RETURNING ' . $idQ;
        $stmtIns = $con->prepare($insertSql);
        $stmtIns->execute($params);

        respond(403, [
            'success' => false,
            'message' => 'Account created but pending admin approval. Please contact your administrator.',
            'pendingApproval' => true,
            'email' => $googleEmail,
        ]);
    }

    $token = bin2hex(random_bytes(24));
    $authTokenQ = quote_ident($colAuthToken);
    $stmtToken = $con->prepare("UPDATE $tableQ SET $authTokenQ = :token WHERE $idQ = :id");
    $stmtToken->execute([
        ':token' => $token,
        ':id' => intval($userRow['id']),
    ]);

    $subscription = null;
    $orgId = isset($userRow['org_id']) ? intval($userRow['org_id']) : 0;
    if ($orgId > 0) {
        $orgStmt = $con->prepare('SELECT plan_status, trial_ends_at, COALESCE(free_account, 0) AS free_account FROM organizations WHERE id = :orgId LIMIT 1');
        $orgStmt->execute([':orgId' => $orgId]);
        $orgRow = $orgStmt->fetch(PDO::FETCH_ASSOC);
        if ($orgRow) {
            $planStatus = $orgRow['plan_status'] ?? null;
            $trialEndsAt = $orgRow['trial_ends_at'] ?? null;
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
                        $upd = $con->prepare("UPDATE organizations SET plan_status = 'expired' WHERE id = :orgId");
                        $upd->execute([':orgId' => $orgId]);
                    }
                    $trialDaysLeft = max(0, (int) ceil(($trialEnd->getTimestamp() - $now->getTimestamp()) / 86400));
                } else {
                    $trialDaysLeft = 0;
                }
            }

            $subscription = [
                'orgId' => $orgId,
                'orgRole' => $userRow['org_role'] ?? 'viewer',
                'planStatus' => $planStatus,
                'trialEndsAt' => $trialEndsAt,
                'trialDaysLeft' => $trialDaysLeft,
                'freeAccount' => $freeAccount,
            ];
        }
    }

    $permissions = permission_to_level($userRow['permissions'] ?? '');
    $role = $permissions >= 3 ? 'admin' : 'user';

    $user = [
        'id' => intval($userRow['id']),
        'name' => $userRow['username'] ?? '',
        'email' => $userRow['email'] ?? '',
        'phone' => $userRow['phone'] ?? '',
        'role' => $role,
        'permissionLevel' => $permissions,
        'orgRole' => $userRow['org_role'] ?? 'viewer',
        'isFreeUser' => !empty($userRow['is_free_user']),
        'createdAt' => date('c'),
    ];

    respond(200, [
        'success' => true,
        'data' => [
            'token' => $token,
            'user' => $user,
            'subscription' => $subscription,
        ],
    ]);
} catch (Exception $e) {
    respond(500, [
        'success' => false,
        'message' => 'Google login failed: ' . $e->getMessage(),
    ]);
}
