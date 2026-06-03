<?php
// Helper: send JSON response and exit
if (!function_exists('respond')) {
    function respond($statusCode, $payload) {
        http_response_code($statusCode);
        echo json_encode($payload);
        exit;
    }
}

// Helper: convert permissions string/label to numeric level
if (!function_exists('permission_to_level')) {
    function permission_to_level($permissionRaw) {
        $value = trim((string)$permissionRaw);
        if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
        if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
        if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
        if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
        if (is_numeric($value)) return intval($value);
        return 0;
    }
}
include_once __DIR__ . '/cors.php';
// Extract requesterId from Authorization header (Bearer token)
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
file_put_contents(__DIR__ . '/php-error.log', date('c') . " AUTH_HEADER: $authHeader | UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n", FILE_APPEND);
if (strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
    file_put_contents(__DIR__ . '/php-error.log', date('c') . " TOKEN_EXTRACTED: $token\n", FILE_APPEND);
    // Find user by token
    include_once __DIR__ . '/db.pgsql.php';
    $pdo = $con;
    $stmt = $pdo->prepare('SELECT id, permissions FROM "Login_user_AWS" WHERE auth_token = :token AND status = :status LIMIT 1');
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $requesterId = intval($row['id']);
        file_put_contents(__DIR__ . '/php-error.log', date('c') . " REQUESTER_ID: $requesterId\n", FILE_APPEND);
    } else {
        file_put_contents(__DIR__ . '/php-error.log', date('c') . " INVALID_TOKEN\n", FILE_APPEND);
        respond(401, array('success' => false, 'message' => 'Unauthorized: Invalid token'));
    }
} else {
    file_put_contents(__DIR__ . '/php-error.log', date('c') . " NO_BEARER_PREFIX\n", FILE_APPEND);
    $requesterId = 0;
}

// Helper: send JSON response and exit
if (!function_exists('respond')) {
    function respond($statusCode, $payload) {
        http_response_code($statusCode);
        echo json_encode($payload);
        exit;
    }
}

function has_column($con, $tableName, $columnName) {
    $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = :table AND column_name = :column LIMIT 1";
    $stmt = $con->prepare($sql);
    $stmt->execute([':table' => $tableName, ':column' => $columnName]);
    return $stmt->fetch() !== false;
}

function has_table($con, $tableName) {
    $sql = "SELECT table_name FROM information_schema.tables WHERE table_name = :table LIMIT 1";
    $stmt = $con->prepare($sql);
    $stmt->execute([':table' => strtolower($tableName)]);
    return $stmt->fetch() !== false;
}


include('db.pgsql.php');

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}


if ($requesterId <= 0) {
    respond(400, array('success' => false, 'message' => 'Valid requesterId is required'));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmtRequester = $pdo->prepare('SELECT permissions FROM "Login_user_AWS" WHERE id = :id LIMIT 1');
        $stmtRequester->execute([':id' => $requesterId]);
        $row = $stmtRequester->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            respond(404, array('success' => false, 'message' => 'Requester not found'));
        }
        $requesterPermissionsRaw = $row['permissions'];
        if (permission_to_level($requesterPermissionsRaw) < 4) {
            respond(403, array('success' => false, 'message' => 'Only Super Administrators can perform this action'));
        }

        $stmt = $pdo->query('SELECT "id", "username", "email", "phone" FROM "Login_user_AWS" WHERE "status" = \'false\' ORDER BY "id" DESC');
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'id' => intval($row['id']),
                'username' => $row['username'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'createdAt' => ''
            ];
        }
        respond(200, array('success' => true, 'data' => $rows));
    } catch (Exception $e) {
        respond(500, array('success' => false, 'message' => 'Database error: ' . $e->getMessage()));
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($input['userId']) ? intval($input['userId']) : 0;
    $action = isset($input['action']) ? trim($input['action']) : '';

    if ($userId <= 0 || ($action !== 'approve' && $action !== 'disapprove')) {
        respond(400, array('success' => false, 'message' => 'Valid userId and action are required'));
    }

    try {
        $stmtRequester = $pdo->prepare('SELECT permissions FROM "Login_user_AWS" WHERE id = :id LIMIT 1');
        $stmtRequester->execute([':id' => $requesterId]);
        $row = $stmtRequester->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            respond(404, array('success' => false, 'message' => 'Requester not found'));
        }
        $requesterPermissionsRaw = $row['permissions'];
        if (permission_to_level($requesterPermissionsRaw) < 4) {
            respond(403, array('success' => false, 'message' => 'Only Super Administrators can perform this action'));
        }

        $newStatus = ($action === 'approve') ? 'true' : 'rejected';
        $stmtUpdate = $pdo->prepare('UPDATE "Login_user_AWS" SET "status" = :status WHERE "id" = :id AND "status" = \'false\'');
        $stmtUpdate->execute([':status' => $newStatus, ':id' => $userId]);
        $affectedRows = $stmtUpdate->rowCount();
        if ($affectedRows <= 0) {
            respond(404, array('success' => false, 'message' => 'Pending user not found or already reviewed'));
        }

        // On approval, provision org defaults for self-signup users (e.g., Google signups)
        // so they can use admin/team features immediately after approval.
        if ($action === 'approve') {

            $hasOrgId = has_column($pdo, 'Login_user_AWS', 'org_id');
            $hasOrgRole = has_column($pdo, 'Login_user_AWS', 'org_role');
            $hasPermissions = has_column($pdo, 'Login_user_AWS', 'permissions');
            $hasOrganizations = has_table($pdo, 'organizations');

            $permissionsRaw = '';
            $username = '';
            $orgId = 0;
            // Always try to fetch the user row for username/orgId, regardless of column checks
            $stmtUser = $pdo->prepare('SELECT username, permissions, org_id FROM "Login_user_AWS" WHERE id = :id LIMIT 1');
            $stmtUser->execute([':id' => $userId]);
            $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            file_put_contents(__DIR__ . '/php-error.log', date('c') . " [USER_ROW_DUMP] userId=$userId userRow=" . var_export($userRow, true) . "\n", FILE_APPEND);
            if ($userRow) {
                $username = $userRow['username'];
                $permissionsRaw = $userRow['permissions'];
                $orgId = $userRow['org_id'];
            }

            // Always set both permissions=3 and org_role=admin for approved users
            if ($hasPermissions) {
                $perm = '3';
                $stmtPerm = $pdo->prepare('UPDATE "Login_user_AWS" SET permissions = :perm WHERE id = :id');
                $stmtPerm->execute([':perm' => $perm, ':id' => $userId]);
            }
            if ($hasOrgRole) {
                $role = 'admin';
                $stmtRole = $pdo->prepare('UPDATE "Login_user_AWS" SET org_role = :role WHERE id = :id');
                $stmtRole->execute([':role' => $role, ':id' => $userId]);
            }

            // Debug: log org creation preconditions
            file_put_contents(__DIR__ . '/php-error.log', date('c') . " [ORG_CREATE_PRECHECK] hasOrganizations=" . ($hasOrganizations ? '1' : '0') . " hasOrgId=" . ($hasOrgId ? '1' : '0') . " orgId=" . $orgId . " userId=" . $userId . " username=" . $username . "\n", FILE_APPEND);

            // Log org creation preconditions for debugging
            file_put_contents(__DIR__ . '/php-error.log', date('c') . " [ORG_CREATE_CONDITIONS] hasOrganizations=$hasOrganizations hasOrgId=$hasOrgId orgId=$orgId userId=$userId username=$username\n", FILE_APPEND);
            // If user has no org yet, create one with 30-day trial and link them.
            if ($hasOrganizations && $hasOrgId && intval($orgId) <= 0) {
                $orgName = ($username !== '' ? $username : 'User') . "'s Organization";
                $trialEndsAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                file_put_contents(__DIR__ . '/php-error.log', date('c') . " [ORG_CREATE_ATTEMPT] userId=$userId orgName=$orgName\n", FILE_APPEND);
                try {
                    $stmtOrg = $pdo->prepare("INSERT INTO organizations (name, owner_user_id, plan_status, trial_ends_at, max_editors, max_viewers) VALUES (:name, :owner_user_id, 'trial', :trial_ends_at, 1, 3) RETURNING id");
                    $stmtOrg->execute([':name' => $orgName, ':owner_user_id' => $userId, ':trial_ends_at' => $trialEndsAt]);
                    $newOrgId = $stmtOrg->fetchColumn();
                    file_put_contents(__DIR__ . '/php-error.log', date('c') . " [ORG_CREATE_RESULT] newOrgId=$newOrgId\n", FILE_APPEND);
                    if ($newOrgId > 0) {
                        $stmtLink = $pdo->prepare('UPDATE "Login_user_AWS" SET "org_id" = :org_id WHERE "id" = :id');
                        $stmtLink->execute([':org_id' => $newOrgId, ':id' => $userId]);
                        file_put_contents(__DIR__ . '/php-error.log', date('c') . " [ORG_LINKED] userId=$userId orgId=$newOrgId\n", FILE_APPEND);
                    } else {
                        file_put_contents(__DIR__ . '/php-error.log', date('c') . " [ORG_CREATE_FAIL] userId=$userId\n", FILE_APPEND);
                    }
                } catch (Exception $e) {
                    file_put_contents(__DIR__ . '/php-error.log', date('c') . " [ORG_CREATE_EXCEPTION] userId=$userId error=" . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
        }
        respond(200, array('success' => true, 'message' => 'User review completed successfully'));
    } catch (Exception $e) {
        respond(500, array('success' => false, 'message' => 'Database error: ' . $e->getMessage()));
    }
}

respond(405, array('success' => false, 'message' => 'Method not allowed'));
