<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'db.php';

// Authenticate – super admin only (Permissions = 4)
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (strpos($authHeader, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$token = substr($authHeader, 7);
$authStmt = mysqli_prepare($con,
    "SELECT id, Permissions FROM Login_user_AWS WHERE auth_token = ? AND status = 'true' LIMIT 1");
if (!$authStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}
mysqli_stmt_bind_param($authStmt, 's', $token);
mysqli_stmt_execute($authStmt);
$authUserId = null; $authPerms = null;
mysqli_stmt_bind_result($authStmt, $authUserId, $authPerms);
mysqli_stmt_fetch($authStmt);
mysqli_stmt_close($authStmt);

if (!$authUserId || intval($authPerms) < 4) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden – super admin only']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

if ($action === 'list') {
    // List all users on the platform
    $hasMaxEditors = false;
    $chk = mysqli_query($con, "SHOW COLUMNS FROM organizations LIKE 'max_editors'");
    if ($chk) { $hasMaxEditors = mysqli_num_rows($chk) > 0; mysqli_free_result($chk); }
    $hasFreeAccount = false;
    $chk2 = mysqli_query($con, "SHOW COLUMNS FROM organizations LIKE 'free_account'");
    if ($chk2) { $hasFreeAccount = mysqli_num_rows($chk2) > 0; mysqli_free_result($chk2); }

    $orgExtras = '';
    if ($hasMaxEditors) $orgExtras .= ', COALESCE(o.max_editors, 1) AS max_editors, COALESCE(o.max_viewers, 3) AS max_viewers';
    if ($hasFreeAccount) $orgExtras .= ', COALESCE(o.free_account, 0) AS free_account';

    $sql = "SELECT u.id, u.username, u.email,
                   COALESCE(u.phone, '') AS phone,
                   u.org_id,
                   COALESCE(o.name, '') AS org_name,
                   u.Permissions,
                   COALESCE(u.org_role, '') AS org_role,
                   u.status,
                   (SELECT COUNT(*) FROM Masjids_AWS m WHERE m.Created_by = u.id) AS masjid_count,
                   (SELECT COUNT(*) FROM Login_user_AWS eu
                    WHERE eu.org_id = u.org_id
                      AND eu.org_role IN ('editor','viewer')
                      AND eu.id != u.id) AS team_count
                   {$orgExtras}
            FROM Login_user_AWS u
            LEFT JOIN organizations o ON o.id = u.org_id
            WHERE u.status = 'true'
            ORDER BY u.Permissions DESC, u.id ASC";

    $result = mysqli_query($con, $sql);
    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        exit;
    }

    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = [
            'id'          => intval($row['id']),
            'username'    => $row['username'],
            'email'       => $row['email'],
            'phone'       => $row['phone'],
            'orgId'       => intval($row['org_id']),
            'orgName'     => $row['org_name'],
            'permissions' => intval($row['Permissions']),
            'orgRole'     => $row['org_role'],
            'status'      => $row['status'],
            'masjidCount' => intval($row['masjid_count']),
            'teamCount'   => intval($row['team_count']),
            'maxEditors'  => isset($row['max_editors'])  ? intval($row['max_editors'])  : 1,
            'maxViewers'  => isset($row['max_viewers'])  ? intval($row['max_viewers'])  : 3,
            'freeAccount' => isset($row['free_account']) ? (bool)$row['free_account']  : false,
        ];
    }
    mysqli_free_result($result);

    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

if ($action === 'masjids') {
    $userId = isset($_GET['userId']) ? intval($_GET['userId']) : 0;
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'userId required']);
        exit;
    }

    $userIdEsc = intval($userId);
    $sql = "SELECT ID, Name, H_No, Apt_No, St_Name, City, State, Zip, `Clear`, Coordinates
            FROM Masjids_AWS
            WHERE Created_by = {$userIdEsc}
            ORDER BY ID DESC";
    $res = mysqli_query($con, $sql);
    if (!$res) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        exit;
    }
    $masjids = [];
    while ($row = mysqli_fetch_assoc($res)) {
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
    mysqli_free_result($res);

    echo json_encode(['success' => true, 'data' => $masjids]);
    exit;
}

if ($action === 'team') {
    $userId = isset($_GET['userId']) ? intval($_GET['userId']) : 0;
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'userId required']);
        exit;
    }

    // Get org_id for the user
    $orgStmt = mysqli_prepare($con, "SELECT org_id FROM Login_user_AWS WHERE id = ? LIMIT 1");
    if (!$orgStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        exit;
    }
    mysqli_stmt_bind_param($orgStmt, 'i', $userId);
    mysqli_stmt_execute($orgStmt);
    $orgId = null;
    mysqli_stmt_bind_result($orgStmt, $orgId);
    mysqli_stmt_fetch($orgStmt);
    mysqli_stmt_close($orgStmt);

    if (!$orgId) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $hasPhone = false;
    $phoneChk = mysqli_query($con, "SHOW COLUMNS FROM `Login_user_AWS` LIKE 'phone'");
    if ($phoneChk && mysqli_num_rows($phoneChk) > 0) { $hasPhone = true; }
    if ($phoneChk) mysqli_free_result($phoneChk);
    $phoneSelect = $hasPhone ? 'phone' : "'' AS phone";

    $orgIdEsc = intval($orgId);
    $userIdEsc2 = intval($userId);
    $sql = "SELECT id, username, email, {$phoneSelect}, org_role, status
            FROM Login_user_AWS
            WHERE org_id = {$orgIdEsc} AND id != {$userIdEsc2} AND org_role IN ('editor','viewer')
            ORDER BY org_role ASC, id ASC";
    $res = mysqli_query($con, $sql);
    if (!$res) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        exit;
    }
    $team = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $team[] = [
            'id'      => intval($row['id']),
            'username'=> $row['username'],
            'email'   => $row['email'],
            'phone'   => $row['phone'],
            'orgRole' => $row['org_role'],
            'status'  => $row['status'],
        ];
    }
    mysqli_free_result($res);

    echo json_encode(['success' => true, 'data' => $team]);
    exit;
}

if ($action === 'update_user') {
    $data = json_decode(file_get_contents('php://input'), true);
    $targetId = isset($data['userId']) ? intval($data['userId']) : 0;
    $email    = isset($data['email'])  ? trim($data['email'])  : '';
    $phone    = isset($data['phone'])  ? trim($data['phone'])  : '';
    $password = isset($data['password']) ? trim($data['password']) : '';

    if ($targetId <= 0 || $email === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'userId and email are required']);
        exit;
    }

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($con,
            "UPDATE Login_user_AWS SET email = ?, phone = ?, password = ? WHERE id = ? LIMIT 1");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
            exit;
        }
        mysqli_stmt_bind_param($stmt, 'sssi', $email, $phone, $hash, $targetId);
    } else {
        $stmt = mysqli_prepare($con,
            "UPDATE Login_user_AWS SET email = ?, phone = ? WHERE id = ? LIMIT 1");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
            exit;
        }
        mysqli_stmt_bind_param($stmt, 'ssi', $email, $phone, $targetId);
    }

    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true, 'affected' => $affected]);
    exit;
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

    // Ensure free_account column exists
    $hasFreeCol = false;
    $chkFree = mysqli_query($con, "SHOW COLUMNS FROM organizations LIKE 'free_account'");
    if ($chkFree) { $hasFreeCol = mysqli_num_rows($chkFree) > 0; mysqli_free_result($chkFree); }
    if (!$hasFreeCol) {
        mysqli_query($con, "ALTER TABLE organizations ADD COLUMN free_account TINYINT(1) NOT NULL DEFAULT 0");
    }

    $setClauses = [];
    $types = '';
    $params = [];
    if ($maxEditors >= 0)  { $setClauses[] = 'max_editors = ?';  $types .= 'i'; $params[] = $maxEditors; }
    if ($maxViewers >= 0)  { $setClauses[] = 'max_viewers = ?';  $types .= 'i'; $params[] = $maxViewers; }
    if ($freeAccount >= 0) { $setClauses[] = 'free_account = ?'; $types .= 'i'; $params[] = $freeAccount; }

    if (empty($setClauses)) {
        echo json_encode(['success' => true, 'message' => 'Nothing to update']);
        exit;
    }

    $types .= 'i';
    $params[] = $orgId;
    $stmt = mysqli_prepare($con, "UPDATE organizations SET " . implode(', ', $setClauses) . " WHERE id = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        exit;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action']);
