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

include('db.php');
mysqli_select_db($con, $db);

// ---------------------------------------------------------------
// Auth helper
// ---------------------------------------------------------------
function get_authenticated_user($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);

    $stmt = mysqli_prepare($con,
        "SELECT id, org_id, org_role, email
         FROM Login_user_AWS
         WHERE auth_token = ? AND status = 'true' LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $userId = $orgId = $orgRole = $email = null;
    mysqli_stmt_bind_result($stmt, $userId, $orgId, $orgRole, $email);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$found || !$userId) return null;
    return ['id' => intval($userId), 'org_id' => intval($orgId), 'org_role' => $orgRole, 'email' => $email];
}

// ---------------------------------------------------------------
// GET /api/org_users.php  — list all users in the org
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $me = get_authenticated_user($con);
    if (!$me) respond(401, ['success' => false, 'message' => 'Unauthorized']);
    if ($me['org_role'] !== 'org_admin') {
        respond(403, ['success' => false, 'message' => 'Only org admins can view org users']);
    }

    $stmt = mysqli_prepare($con,
        "SELECT id, username, email, phone, org_role, status
         FROM Login_user_AWS
         WHERE org_id = ?
         ORDER BY org_role, username");
    mysqli_stmt_bind_param($stmt, 'i', $me['org_id']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $users = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $users[] = [
            'id'       => intval($row['id']),
            'username' => $row['username'],
            'email'    => $row['email'],
            'phone'    => $row['phone'] ?? '',
            'orgRole'  => $row['org_role'],
            'status'   => $row['status'],
        ];
    }
    mysqli_stmt_close($stmt);

    // Count editors and viewers
    $editors = count(array_filter($users, fn($u) => $u['orgRole'] === 'editor'));
    $viewers  = count(array_filter($users, fn($u) => $u['orgRole'] === 'viewer'));

    // Get seat limits from org
    $limStmt = mysqli_prepare($con,
        "SELECT max_editors, max_viewers FROM organizations WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($limStmt, 'i', $me['org_id']);
    mysqli_stmt_execute($limStmt);
    $maxEditors = $maxViewers = null;
    mysqli_stmt_bind_result($limStmt, $maxEditors, $maxViewers);
    mysqli_stmt_fetch($limStmt);
    mysqli_stmt_close($limStmt);

    respond(200, [
        'success' => true,
        'data'    => [
            'users'       => $users,
            'editorCount' => $editors,
            'viewerCount' => $viewers,
            'maxEditors'  => intval($maxEditors),
            'maxViewers'  => intval($maxViewers),
        ]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = get_authenticated_user($con);
    if (!$me) respond(401, ['success' => false, 'message' => 'Unauthorized']);
    if ($me['org_role'] !== 'org_admin') {
        respond(403, ['success' => false, 'message' => 'Only org admins can manage users']);
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) $input = $_POST;
    $action = isset($input['action']) ? trim($input['action']) : '';

    // ----------------------------------------------------------
    // ACTION: add_user  — promote an existing pending user into org
    // ACTION: set_role  — change an existing org member's role
    // ----------------------------------------------------------
    if ($action === 'add_user' || $action === 'set_role') {
        $targetId = isset($input['user_id']) ? intval($input['user_id']) : 0;
        $newRole  = isset($input['org_role']) ? trim($input['org_role']) : '';

        if ($targetId <= 0 || !in_array($newRole, ['editor', 'viewer'])) {
            respond(400, ['success' => false, 'message' => 'user_id and org_role (editor|viewer) are required']);
        }

        // Check seat availability
        $countStmt = mysqli_prepare($con,
            "SELECT org_role, COUNT(*) as cnt
             FROM Login_user_AWS
             WHERE org_id = ? AND org_role = ?
             GROUP BY org_role");
        mysqli_stmt_bind_param($countStmt, 'is', $me['org_id'], $newRole);
        mysqli_stmt_execute($countStmt);
        $currentCount = 0;
        mysqli_stmt_bind_result($countStmt, $roleIgnored, $currentCount);
        mysqli_stmt_fetch($countStmt);
        mysqli_stmt_close($countStmt);

        $limStmt = mysqli_prepare($con,
            "SELECT max_editors, max_viewers FROM organizations WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($limStmt, 'i', $me['org_id']);
        mysqli_stmt_execute($limStmt);
        $maxE = $maxV = null;
        mysqli_stmt_bind_result($limStmt, $maxE, $maxV);
        mysqli_stmt_fetch($limStmt);
        mysqli_stmt_close($limStmt);

        $limit = ($newRole === 'editor') ? intval($maxE) : intval($maxV);
        if ($action === 'add_user' && $currentCount >= $limit) {
            respond(422, ['success' => false,
                'message' => "Seat limit reached: your plan allows $limit {$newRole}s"]);
        }

        // Assign user to this org
        $upStmt = mysqli_prepare($con,
            "UPDATE Login_user_AWS
             SET org_id = ?, org_role = ?, status = 'true', Permissions = ?
             WHERE id = ? LIMIT 1");
        $perm = ($newRole === 'editor') ? 'Editor' : 'Viewer';
        mysqli_stmt_bind_param($upStmt, 'isis', $me['org_id'], $newRole, $perm, $targetId);
        mysqli_stmt_execute($upStmt);
        mysqli_stmt_close($upStmt);

        respond(200, ['success' => true, 'message' => "User assigned as $newRole"]);
    }

    // ----------------------------------------------------------
    // ACTION: remove_user — remove a user from this org (but keep
    //                        their account; set status = false)
    // ----------------------------------------------------------
    if ($action === 'remove_user') {
        $targetId = isset($input['user_id']) ? intval($input['user_id']) : 0;
        if ($targetId <= 0 || $targetId === $me['id']) {
            respond(400, ['success' => false, 'message' => 'Cannot remove yourself']);
        }

        $rmStmt = mysqli_prepare($con,
            "UPDATE Login_user_AWS
             SET org_id = NULL, org_role = 'viewer', status = 'false'
             WHERE id = ? AND org_id = ? LIMIT 1");
        mysqli_stmt_bind_param($rmStmt, 'ii', $targetId, $me['org_id']);
        mysqli_stmt_execute($rmStmt);
        $affected = mysqli_stmt_affected_rows($rmStmt);
        mysqli_stmt_close($rmStmt);

        if ($affected === 0) {
            respond(404, ['success' => false, 'message' => 'User not found in your organization']);
        }
        respond(200, ['success' => true, 'message' => 'User removed from organization']);
    }

    respond(400, ['success' => false, 'message' => 'Unknown action']);
}

respond(405, ['success' => false, 'message' => 'Method not allowed']);
