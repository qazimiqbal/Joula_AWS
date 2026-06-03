<?php
// Disable error display and log errors to a file to avoid breaking CORS headers
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
include_once __DIR__ . '/cors.php';
header('Content-Type: application/json');
require_once 'db.pgsql.php';

// Helper: send JSON response and exit
if (!function_exists('respond')) {
    function respond($statusCode, $payload) {
        http_response_code($statusCode);
        echo json_encode($payload);
        exit;
    }
}

// Authenticate – super admin only (permissions = 4)

$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
// Debug log the received Authorization header and user agent
file_put_contents(__DIR__ . '/php-error.log', date('c') . " AUTH_HEADER: $authHeader | UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n", FILE_APPEND);
if (strpos($authHeader, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'debug' => $authHeader]);
    exit;
}
$token = substr($authHeader, 7);
// Use lowercase permissions for PostgreSQL
$sql = 'SELECT id, permissions FROM "Login_user_AWS" WHERE auth_token = :token AND status = :status LIMIT 1';
$stmt = $con->prepare($sql);
$stmt->execute([':token' => $token, ':status' => 'true']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || intval($row['permissions']) < 4) {
    respond(403, ['success' => false, 'message' => 'Forbidden – super admin only', 'debug' => $token]);
}
$authUserId = intval($row['id']);

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

if ($action === 'list') {
    // List all users on the platform
        $sql = "SELECT u.id, u.username, u.email,
                                     COALESCE(u.phone, '') AS phone,
                                     u.org_id,
                                     COALESCE(o.name, '') AS org_name,
                                     u.permissions,
                                     COALESCE(u.org_role, 'viewer') AS org_role,
                                     u.status,
                                     (SELECT COUNT(*) FROM \"Masjids_AWS\" m WHERE m.\"Created_by\" = u.id) AS masjid_count,
                                     (SELECT COUNT(*) FROM \"Login_user_AWS\" eu
                                        WHERE eu.org_id = u.org_id
                                            AND eu.org_role IN ('editor','viewer')
                                            AND eu.id != u.id) AS team_count,
                                     COALESCE(o.max_editors, 1) AS max_editors,
                                     COALESCE(o.max_viewers, 3) AS max_viewers,
                                     COALESCE(o.free_account, 0) AS free_account
                        FROM \"Login_user_AWS\" u
                        LEFT JOIN LATERAL (
                            SELECT name, max_editors, max_viewers, free_account
                            FROM organizations o1
                            WHERE o1.id = u.org_id
                            ORDER BY o1.created_at DESC NULLS LAST
                            LIMIT 1
                        ) o ON true
                        WHERE u.status = 'true'
                        ORDER BY u.permissions DESC, u.id ASC";
    try {
        $stmt = $con->prepare($sql);
        $stmt->execute();
        $users = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = [
                'id'          => intval($row['id']),
                'username'    => $row['username'],
                'email'       => $row['email'],
                'phone'       => $row['phone'],
                'orgId'       => intval($row['org_id']),
                'orgName'     => $row['org_name'],
                'permissions' => intval($row['permissions']),
                'orgRole'     => $row['org_role'],
                'status'      => $row['status'],
                'masjidCount' => intval($row['masjid_count']),
                'teamCount'   => intval($row['team_count']),
                'maxEditors'  => isset($row['max_editors'])  ? intval($row['max_editors'])  : 1,
                'maxViewers'  => isset($row['max_viewers'])  ? intval($row['max_viewers'])  : 3,
                'freeAccount' => isset($row['free_account']) ? (bool)$row['free_account']  : false,
            ];
        }
        if (empty($users)) {
            respond(200, ['success' => false, 'message' => 'No users found', 'debug' => 'Query returned 0 rows']);
        } else {
            respond(200, ['success' => true, 'data' => $users]);
        }
    } catch (Exception $e) {
        // Log the error to php-error.log for debugging
        file_put_contents(__DIR__ . '/php-error.log', date('c') . " PDOException in list: " . $e->getMessage() . "\nSQL: $sql\n", FILE_APPEND);
        respond(500, [
            'success' => false,
            'message' => 'DB error',
            'error' => $e->getMessage(),
            'sql' => $sql
        ]);
    }
}

if ($action === 'masjids') {
    $userId = isset($_GET['userId']) ? intval($_GET['userId']) : 0;
    if ($userId <= 0) {
        respond(400, ['success' => false, 'message' => 'userId required']);
    }

    // Get org_id and org_role for the user
    $orgId = null;
    $orgRole = null;
    try {
        $orgStmt = $con->prepare('SELECT org_id, org_role FROM "Login_user_AWS" WHERE id = :userId LIMIT 1');
        $orgStmt->execute([':userId' => $userId]);
        $orgRow = $orgStmt->fetch(PDO::FETCH_ASSOC);
        if ($orgRow && isset($orgRow['org_id'])) {
            $orgId = $orgRow['org_id'];
            $orgRole = $orgRow['org_role'];
        }
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/php-error.log', date('c') . " PDOException in masjids-orgId: " . $e->getMessage() . "\n", FILE_APPEND);
        respond(500, ['success' => false, 'message' => 'DB error', 'error' => $e->getMessage()]);
    }

    if ($orgId && ($orgRole === 'org_admin' || $orgRole === 'admin')) {
        // Org admin: get all masjids created by anyone in the org
        $sql = 'SELECT m."ID", m."Name", m."H_No", m."Apt_No", m."St_Name", m."City", m."State", m."Zip", m."Clear", m."Coordinates" FROM "Masjids_AWS" m JOIN "Login_user_AWS" u ON m."Created_by" = u.id WHERE u.org_id = :orgId ORDER BY m."ID" DESC';
        $params = [':orgId' => $orgId];
    } else {
        // Regular user: only their own masjids
        $sql = 'SELECT "ID", "Name", "H_No", "Apt_No", "St_Name", "City", "State", "Zip", "Clear", "Coordinates" FROM "Masjids_AWS" WHERE "Created_by" = :userId ORDER BY "ID" DESC';
        $params = [':userId' => $userId];
    }
    try {
        $stmt = $con->prepare($sql);
        $stmt->execute($params);
        $masjids = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $masjids[] = [
                'id'         => intval($row['ID']),
                'name'       => $row['Name'],
                'houseNo'    => $row['H_No'],
                'aptNo'      => $row['Apt_No'],
                'streetName' => $row['St_Name'],
                'city'       => $row['City'],
                'state'      => $row['State'],
                'zip'        => $row['Zip'],
                'approved'   => intval($row['Clear']) === 1,
                'coordinates'=> $row['Coordinates'],
            ];
        }
        respond(200, ['success' => true, 'data' => $masjids]);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/php-error.log', date('c') . " PDOException in masjids: " . $e->getMessage() . "\nSQL: $sql\n", FILE_APPEND);
        respond(500, ['success' => false, 'message' => 'DB error', 'error' => $e->getMessage(), 'sql' => $sql]);
    }
}

if ($action === 'team') {
    $userId = isset($_GET['userId']) ? intval($_GET['userId']) : 0;
    if ($userId <= 0) {
        respond(400, ['success' => false, 'message' => 'userId required']);
    }

    // Get org_id for the user
    $orgId = null;
    try {
        $orgStmt = $con->prepare('SELECT org_id FROM "Login_user_AWS" WHERE id = :userId LIMIT 1');
        $orgStmt->execute([':userId' => $userId]);
        $orgRow = $orgStmt->fetch(PDO::FETCH_ASSOC);
        if ($orgRow && isset($orgRow['org_id'])) {
            $orgId = $orgRow['org_id'];
        }
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/php-error.log', date('c') . " PDOException in team-orgId: " . $e->getMessage() . "\n", FILE_APPEND);
        respond(500, ['success' => false, 'message' => 'DB error', 'error' => $e->getMessage()]);
    }

    if (!$orgId) {
        respond(200, ['success' => true, 'data' => []]);
    }

    // Check if phone column exists
    $hasPhone = false;
    try {
        $phoneChk = $con->query("SELECT column_name FROM information_schema.columns WHERE table_name='Login_user_AWS' AND column_name='phone'");
        if ($phoneChk && $phoneChk->fetch(PDO::FETCH_ASSOC)) {
            $hasPhone = true;
        }
    } catch (Exception $e) {
        // If this fails, just default to no phone
    }
    $phoneSelect = $hasPhone ? 'phone' : "'' AS phone";

    $sql = 'SELECT id, username, email, ' . $phoneSelect . ", org_role, status FROM \"Login_user_AWS\" WHERE org_id = :orgId AND id != :userId AND org_role IN ('editor','viewer') ORDER BY org_role ASC, id ASC";
    try {
        $stmt = $con->prepare($sql);
        $stmt->execute([':orgId' => $orgId, ':userId' => $userId]);
        $team = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $team[] = [
                'id'      => intval($row['id']),
                'username'=> $row['username'],
                'email'   => $row['email'],
                'phone'   => $row['phone'],
                'orgRole' => $row['org_role'],
                'status'  => $row['status'],
            ];
        }
        respond(200, ['success' => true, 'data' => $team]);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/php-error.log', date('c') . " PDOException in team: " . $e->getMessage() . "\nSQL: $sql\n", FILE_APPEND);
        respond(500, ['success' => false, 'message' => 'DB error', 'error' => $e->getMessage(), 'sql' => $sql]);
    }
}

if ($action === 'update_user') {
    $data = json_decode(file_get_contents('php://input'), true);
    $targetId = isset($data['userId']) ? intval($data['userId']) : 0;
    $email    = isset($data['email'])  ? trim($data['email'])  : '';
    $phone    = isset($data['phone'])  ? trim($data['phone'])  : '';
    $password = isset($data['password']) ? trim($data['password']) : '';

    if ($targetId <= 0 || $email === '') {
        respond(400, ['success' => false, 'message' => 'userId and email are required']);
    }

    try {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE \"Login_user_AWS\" SET email = :email, phone = :phone, password = :password WHERE id = :id";
            $stmt = $con->prepare($sql);
            $stmt->execute([
                ':email' => $email,
                ':phone' => $phone,
                ':password' => $hash,
                ':id' => $targetId
            ]);
        } else {
            $sql = "UPDATE \"Login_user_AWS\" SET email = :email, phone = :phone WHERE id = :id";
            $stmt = $con->prepare($sql);
            $stmt->execute([
                ':email' => $email,
                ':phone' => $phone,
                ':id' => $targetId
            ]);
        }
        $affected = $stmt->rowCount();
        echo json_encode(['success' => true, 'affected' => $affected]);
        exit;
    } catch (Exception $e) {
        respond(500, ['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'update_org_limits') {
    $data = json_decode(file_get_contents('php://input'), true);
    $orgId       = isset($data['orgId'])       ? intval($data['orgId'])       : 0;
    $maxEditors  = isset($data['maxEditors'])  ? intval($data['maxEditors'])  : -1;
    $maxViewers  = isset($data['maxViewers'])  ? intval($data['maxViewers'])  : -1;
    $freeAccount = isset($data['freeAccount']) ? ($data['freeAccount'] ? 1 : 0) : -1;

    if ($orgId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'orgId required']);
        exit;
    }

    // Ensure free_account column exists (Postgres version)
    try {
        $colCheck = $con->query("SELECT column_name FROM information_schema.columns WHERE table_name='organizations' AND column_name='free_account'");
        $hasFreeCol = $colCheck->fetch(PDO::FETCH_ASSOC) !== false;
        if (!$hasFreeCol) {
            $con->exec("ALTER TABLE \"organizations\" ADD COLUMN free_account INTEGER NOT NULL DEFAULT 0");
        }
    } catch (Exception $e) {
        // Log but do not block
        file_put_contents(__DIR__ . '/php-error.log', date('c') . " free_account col check/add failed: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    $setClauses = [];
    $params = [];
    if ($maxEditors >= 0)  { $setClauses[] = 'max_editors = :maxEditors';  $params[':maxEditors'] = $maxEditors; }
    if ($maxViewers >= 0)  { $setClauses[] = 'max_viewers = :maxViewers';  $params[':maxViewers'] = $maxViewers; }
    if ($freeAccount >= 0) { $setClauses[] = 'free_account = :freeAccount'; $params[':freeAccount'] = $freeAccount; }

    if (empty($setClauses)) {
        echo json_encode(['success' => true, 'message' => 'Nothing to update']);
        exit;
    }

    $params[':orgId'] = $orgId;
    $sql = "UPDATE \"organizations\" SET " . implode(', ', $setClauses) . " WHERE id = :orgId";
    try {
        $stmt = $con->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        respond(500, ['success' => false, 'message' => $e->getMessage()]);
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action']);
