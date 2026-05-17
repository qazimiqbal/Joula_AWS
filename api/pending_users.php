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
    if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
    if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
    if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
    if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
    if (is_numeric($value)) return intval($value);
    return 0;
}

function has_column($con, $tableName, $columnName) {
    $safeTable = '`' . str_replace('`', '``', $tableName) . '`';
    $safeColumn = mysqli_real_escape_string($con, $columnName);
    $result = mysqli_query($con, "SHOW COLUMNS FROM $safeTable LIKE '$safeColumn'");
    if (!$result) return false;
    $exists = mysqli_fetch_row($result) ? true : false;
    mysqli_free_result($result);
    return $exists;
}

function has_table($con, $tableName) {
    $safeTable = mysqli_real_escape_string($con, $tableName);
    $result = mysqli_query($con, "SHOW TABLES LIKE '$safeTable'");
    if (!$result) return false;
    $exists = mysqli_fetch_row($result) ? true : false;
    mysqli_free_result($result);
    return $exists;
}

include('db.php');
mysqli_select_db($con, $db);

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$requesterId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $requesterId = isset($_GET['requesterId']) ? intval($_GET['requesterId']) : 0;
} else {
    $requesterId = isset($input['requesterId']) ? intval($input['requesterId']) : 0;
}

if ($requesterId <= 0) {
    respond(400, array('success' => false, 'message' => 'Valid requesterId is required'));
}

$stmtRequester = mysqli_prepare($con, "SELECT Permissions FROM Login_user_AWS WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmtRequester, 'i', $requesterId);
mysqli_stmt_execute($stmtRequester);
$requesterPermissionsRaw = '';
mysqli_stmt_bind_result($stmtRequester, $requesterPermissionsRaw);
$hasRequester = mysqli_stmt_fetch($stmtRequester);
mysqli_stmt_close($stmtRequester);

if (!$hasRequester) {
    respond(404, array('success' => false, 'message' => 'Requester not found'));
}

if (permission_to_level($requesterPermissionsRaw) < 4) {
    respond(403, array('success' => false, 'message' => 'Only Super Administrators can perform this action'));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = mysqli_prepare($con, "SELECT id, username, email, phone FROM Login_user_AWS WHERE status = 'false' ORDER BY id DESC");
    if (!$stmt) {
        respond(500, array('success' => false, 'message' => 'Failed to prepare pending users query'));
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $username, $email, $phone);

    $rows = array();
    while (mysqli_stmt_fetch($stmt)) {
        $rows[] = array(
            'id' => intval($id),
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'createdAt' => ''
        );
    }
    mysqli_stmt_close($stmt);

    respond(200, array('success' => true, 'data' => $rows));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($input['userId']) ? intval($input['userId']) : 0;
    $action = isset($input['action']) ? trim($input['action']) : '';

    if ($userId <= 0 || ($action !== 'approve' && $action !== 'disapprove')) {
        respond(400, array('success' => false, 'message' => 'Valid userId and action are required'));
    }

    if ($action === 'approve') {
        $newStatus = 'true';
    } else {
        $newStatus = 'rejected';
    }

    $stmtUpdate = mysqli_prepare($con, "UPDATE Login_user_AWS SET status = ? WHERE id = ? AND status = 'false'");
    if (!$stmtUpdate) {
        respond(500, array('success' => false, 'message' => 'Failed to prepare update query'));
    }

    mysqli_stmt_bind_param($stmtUpdate, 'si', $newStatus, $userId);
    mysqli_stmt_execute($stmtUpdate);
    $affectedRows = mysqli_stmt_affected_rows($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);

    if ($affectedRows <= 0) {
        respond(404, array('success' => false, 'message' => 'Pending user not found or already reviewed'));
    }

    // On approval, provision org defaults for self-signup users (e.g., Google signups)
    // so they can use admin/team features immediately after approval.
    if ($action === 'approve') {
        $hasOrgId = has_column($con, 'Login_user_AWS', 'org_id');
        $hasOrgRole = has_column($con, 'Login_user_AWS', 'org_role');
        $hasPermissions = has_column($con, 'Login_user_AWS', 'Permissions');
        $hasOrganizations = has_table($con, 'organizations');

        $permissionsRaw = '';
        $username = '';
        $orgId = 0;
        if ($hasOrgId && $hasPermissions) {
            $stmtUser = mysqli_prepare($con, "SELECT username, Permissions, org_id FROM Login_user_AWS WHERE id = ? LIMIT 1");
            if ($stmtUser) {
                mysqli_stmt_bind_param($stmtUser, 'i', $userId);
                mysqli_stmt_execute($stmtUser);
                mysqli_stmt_bind_result($stmtUser, $username, $permissionsRaw, $orgId);
                mysqli_stmt_fetch($stmtUser);
                mysqli_stmt_close($stmtUser);
            }
        }

        // Align with self-signup policy: default approved new users should be admin (3)
        if ($hasPermissions && permission_to_level($permissionsRaw) < 3) {
            $perm = '3';
            $stmtPerm = mysqli_prepare($con, "UPDATE Login_user_AWS SET Permissions = ? WHERE id = ? LIMIT 1");
            if ($stmtPerm) {
                mysqli_stmt_bind_param($stmtPerm, 'si', $perm, $userId);
                mysqli_stmt_execute($stmtPerm);
                mysqli_stmt_close($stmtPerm);
            }
        }

        // Ensure org_role is admin for these newly approved self-signup users.
        if ($hasOrgRole) {
            $role = 'admin';
            $stmtRole = mysqli_prepare($con, "UPDATE Login_user_AWS SET org_role = ? WHERE id = ? AND (org_role IS NULL OR org_role = '' OR org_role = 'viewer') LIMIT 1");
            if ($stmtRole) {
                mysqli_stmt_bind_param($stmtRole, 'si', $role, $userId);
                mysqli_stmt_execute($stmtRole);
                mysqli_stmt_close($stmtRole);
            }
        }

        // If user has no org yet, create one with 30-day trial and link them.
        if ($hasOrganizations && $hasOrgId && intval($orgId) <= 0) {
            $orgName = ($username !== '' ? $username : 'User') . "'s Organization";
            $trialEndsAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            $stmtOrg = mysqli_prepare($con,
                "INSERT INTO organizations (name, owner_user_id, plan_status, trial_ends_at, max_editors, max_viewers) VALUES (?, ?, 'trial', ?, 1, 3)");
            if ($stmtOrg) {
                mysqli_stmt_bind_param($stmtOrg, 'sis', $orgName, $userId, $trialEndsAt);
                mysqli_stmt_execute($stmtOrg);
                $newOrgId = mysqli_insert_id($con);
                mysqli_stmt_close($stmtOrg);

                if ($newOrgId > 0) {
                    $stmtLink = mysqli_prepare($con, "UPDATE Login_user_AWS SET org_id = ? WHERE id = ? LIMIT 1");
                    if ($stmtLink) {
                        mysqli_stmt_bind_param($stmtLink, 'ii', $newOrgId, $userId);
                        mysqli_stmt_execute($stmtLink);
                        mysqli_stmt_close($stmtLink);
                    }
                }
            }
        }
    }

    respond(200, array('success' => true, 'message' => 'User review completed successfully'));
}

respond(405, array('success' => false, 'message' => 'Method not allowed'));
