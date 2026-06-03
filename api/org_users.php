<?php
include_once __DIR__ . '/cors.php';
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

require_once 'db.pgsql.php';

function has_column($pdo, $table, $column) {
    $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = :table AND column_name = :column";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':table' => $table, ':column' => $column]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

// ---------------------------------------------------------------
// Auth helper
// ---------------------------------------------------------------
function get_authenticated_user($pdo) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);
    $sql = 'SELECT "id", "org_id", "org_role", "email" FROM "Login_user_AWS" WHERE "auth_token" = :token AND "status" = :status LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
        'id' => intval($row['id']),
        'org_id' => intval($row['org_id']),
        'org_role' => $row['org_role'],
        'email' => $row['email']
    ];
}

// ---------------------------------------------------------------
// GET /api/org_users.php  — list all users in the org
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $me = get_authenticated_user($pdo);
    if (!$me) respond(401, ['success' => false, 'message' => 'Unauthorized']);
    if ($me['org_role'] !== 'org_admin' && $me['org_role'] !== 'admin') {
        respond(403, ['success' => false, 'message' => 'Only admins can view org users']);
    }
    $hasPhone = has_column($pdo, 'Login_user_AWS', 'phone');
    $selectPhone = $hasPhone ? '"phone"' : "'' AS phone";
    $sql = 'SELECT "id", "username", "email", ' . $selectPhone . ', "org_role", "status" FROM "Login_user_AWS" WHERE "org_id" = :org_id ORDER BY "org_role", "username"';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':org_id' => $me['org_id']]);
    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users[] = [
            'id'       => intval($row['id']),
            'username' => $row['username'],
            'email'    => $row['email'],
            'phone'    => $row['phone'] ?? '',
            'orgRole'  => $row['org_role'],
            'status'   => $row['status'],
        ];
    }
    // Count editors and viewers
    $editors = 0;
    $viewers = 0;
    foreach ($users as $u) {
        if (isset($u['orgRole']) && $u['orgRole'] === 'editor') {
            $editors++;
        }
        if (isset($u['orgRole']) && $u['orgRole'] === 'viewer') {
            $viewers++;
        }
    }
    // Get seat limits from org
    $limStmt = $pdo->prepare('SELECT "max_editors", "max_viewers" FROM "organizations" WHERE "id" = :id LIMIT 1');
    $limStmt->execute([':id' => $me['org_id']]);
    $orgLimits = $limStmt->fetch(PDO::FETCH_ASSOC);
    $maxEditors = isset($orgLimits['max_editors']) ? intval($orgLimits['max_editors']) : 1;
    $maxViewers = isset($orgLimits['max_viewers']) ? intval($orgLimits['max_viewers']) : 3;
    respond(200, [
        'success' => true,
        'data'    => [
            'users'       => $users,
            'editorCount' => $editors,
            'viewerCount' => $viewers,
            'maxEditors'  => $maxEditors,
            'maxViewers'  => $maxViewers,
        ]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = get_authenticated_user($pdo);
    if (!$me) respond(401, ['success' => false, 'message' => 'Unauthorized']);
    if ($me['org_role'] !== 'org_admin' && $me['org_role'] !== 'admin') {
        respond(403, ['success' => false, 'message' => 'Only admins can manage users']);
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

        if ($action === 'set_role') {
            $sqlTarget = 'SELECT "org_id", "org_role" FROM "Login_user_AWS" WHERE "id" = :id LIMIT 1';
            $stmtTarget = $pdo->prepare($sqlTarget);
            $stmtTarget->execute([':id' => $targetId]);
            $rowTarget = $stmtTarget->fetch(PDO::FETCH_ASSOC);
            if (!$rowTarget || intval($rowTarget['org_id']) !== intval($me['org_id'])) {
                respond(404, ['success' => false, 'message' => 'User not found in your organization']);
            }
            if ($rowTarget['org_role'] === 'org_admin' || $rowTarget['org_role'] === 'admin') {
                respond(403, ['success' => false, 'message' => 'Cannot change role for organization admins']);
            }
            if ($rowTarget['org_role'] === $newRole) {
                respond(200, ['success' => true, 'message' => 'Role is already up to date']);
            }
        }

        // Check seat availability
        $sqlCount = 'SELECT COUNT(*) as cnt FROM "Login_user_AWS" WHERE "org_id" = :orgId AND "org_role" = :orgRole';
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute([':orgId' => $me['org_id'], ':orgRole' => $newRole]);
        $rowCount = $stmtCount->fetch(PDO::FETCH_ASSOC);
        $currentCount = $rowCount ? intval($rowCount['cnt']) : 0;
        $sqlLim = 'SELECT "max_editors", "max_viewers" FROM "organizations" WHERE "id" = :id LIMIT 1';
        $stmtLim = $pdo->prepare($sqlLim);
        $stmtLim->execute([':id' => $me['org_id']]);
        $rowLim = $stmtLim->fetch(PDO::FETCH_ASSOC);
        $maxE = $rowLim ? intval($rowLim['max_editors']) : 1;
        $maxV = $rowLim ? intval($rowLim['max_viewers']) : 3;
        if ($maxE <= 0) $maxE = 1;
        if ($maxV <= 0) $maxV = 3;
        $limit = ($newRole === 'editor') ? $maxE : $maxV;
        if ($limit > 0 && $currentCount >= $limit) {
            respond(422, ['success' => false,
                'message' => "Seat limit reached: your plan allows $limit {$newRole}s"]);
        }

        // Assign user to this org
        $perm = ($newRole === 'editor') ? '2' : '1';
        $sqlUp = 'UPDATE "Login_user_AWS" SET org_id = :orgId, org_role = :orgRole, status = :status, permissions = :perm WHERE id = :id';
        $stmtUp = $pdo->prepare($sqlUp);
        $stmtUp->execute([
            ':orgId' => $me['org_id'],
            ':orgRole' => $newRole,
            ':status' => 'true',
            ':perm' => $perm,
            ':id' => $targetId
        ]);
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

        $sqlRm = 'UPDATE "Login_user_AWS" SET org_id = NULL, org_role = :orgRole, status = :status, permissions = :perm WHERE id = :id AND org_id = :orgId';
        $stmtRm = $pdo->prepare($sqlRm);
        $stmtRm->execute([
            ':orgRole' => 'viewer',
            ':status' => 'false',
            ':perm' => '1',
            ':id' => $targetId,
            ':orgId' => $me['org_id']
        ]);
        $affected = $stmtRm->rowCount();
        if ($affected === 0) {
            respond(404, ['success' => false, 'message' => 'User not found in your organization']);
        }
        respond(200, ['success' => true, 'message' => 'User removed from organization']);
    }

    respond(400, ['success' => false, 'message' => 'Unknown action']);
}

respond(405, ['success' => false, 'message' => 'Method not allowed']);
