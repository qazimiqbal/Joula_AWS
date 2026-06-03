
<?php
include_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}


require_once 'db.pgsql.php';

function get_authenticated_user_update($pdo) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);
    $sql = 'SELECT id, org_id, permissions FROM "Login_user_AWS" WHERE auth_token = :token AND status = :status LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
        'id' => intval($row['id']),
        'org_id' => intval($row['org_id']),
        'permission_level' => intval($row['permissions']),
    ];
}

$user = get_authenticated_user_update($pdo);
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($user['permission_level'] < 2) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$type = isset($body['type']) ? trim($body['type']) : '';
$id   = isset($body['id'])   ? intval($body['id'])   : 0;

if (!in_array($type, ['masjid', 'address'], true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid type or id']);
    exit;
}

$userId  = $user['id'];
$orgId   = $user['org_id'];
$permLvl = $user['permission_level'];

// -------------------------------------------------------
// UPDATE MASJID
// -------------------------------------------------------
if ($type === 'masjid') {
    // Ownership check: super_admin can edit any; others only their own or org-owned
    if ($permLvl < 4) {
        $checkSql = "SELECT ID FROM Masjids_AWS WHERE ID = ? AND (Created_by = ?";
        $checkTypes = 'ii';
        $checkParams = [$id, $userId];

        if ($orgId > 0) {
            // allow org members to edit masjids created by org members
            $checkSql .= " OR Created_by IN (SELECT id FROM Login_user_AWS WHERE org_id = ? AND status = 'true')";
            $checkTypes .= 'i';
            $checkParams[] = $orgId;
        }
        $checkSql .= ") LIMIT 1";

        $chk = mysqli_prepare($con, $checkSql);
        if (!$chk) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'DB error']);
            exit;
        }
        mysqli_stmt_bind_param($chk, $checkTypes, ...$checkParams);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        $found = mysqli_stmt_num_rows($chk) > 0;
        mysqli_stmt_close($chk);

        if (!$found) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this masjid']);
            exit;
        }
    }

    $allowed = ['Name', 'H_No', 'Apt_No', 'St_Name', 'City', 'State', 'Zip', 'Coordinates'];
    $setClauses = [];
    $setValues  = [];
    $altKeys = [
        'h_no'       => 'houseNo',
        'apt_no'     => 'aptNo',
        'st_name'    => 'streetName',
        'name'       => 'name',
        'city'       => 'city',
        'state'      => 'state',
        'zip'        => 'zip',
        'coordinates'=> 'coordinates',
    ];
    foreach ($allowed as $col) {
        $key = strtolower($col);
        $frontendKey = isset($altKeys[$key]) ? $altKeys[$key] : $key;
        if (array_key_exists($frontendKey, $body)) {
            $val = $body[$frontendKey] !== null ? trim((string)$body[$frontendKey]) : '';
            $setClauses[] = '"' . $col . '" = :' . $col;
            $setValues[':' . $col] = $val;
        }
    }
    if (empty($setClauses)) {
        echo json_encode(['success' => true, 'message' => 'Nothing to update']);
        exit;
    }
    $setValues[':id'] = $id;
    $sql = 'UPDATE "Masjids_AWS" SET ' . implode(', ', $setClauses) . ' WHERE "ID" = :id';
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($setValues);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Update failed']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Masjid updated']);
    exit;
}

// -------------------------------------------------------
// UPDATE ADDRESS
// -------------------------------------------------------
if ($type === 'address') {
    // Ownership check
    if ($permLvl < 4) {
        $checkSql = "SELECT ID FROM Addresses_AWS WHERE ID = ? AND (uploaded_by = ?";
        $checkTypes = 'ii';
        $checkParams = [$id, $userId];

        if ($orgId > 0) {
            $checkSql .= " OR uploaded_by IN (SELECT id FROM Login_user_AWS WHERE org_id = ? AND status = 'true')";
            $checkTypes .= 'i';
            $checkParams[] = $orgId;
        }
        $checkSql .= ") LIMIT 1";

        $chk = mysqli_prepare($con, $checkSql);
        if (!$chk) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'DB error']);
            exit;
        }
        mysqli_stmt_bind_param($chk, $checkTypes, ...$checkParams);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        $found = mysqli_stmt_num_rows($chk) > 0;
        mysqli_stmt_close($chk);

        if (!$found) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this address']);
            exit;
        }
    }

    $allowed = ['Name', 'H_No', 'Apt_No', 'St_Name', 'City', 'State', 'Zip', 'Locality', 'Last_Visit', 'Comments', 'Coordinates'];
    $altKeys = [
        'h_no'      => 'houseNo',
        'apt_no'    => 'aptNo',
        'st_name'   => 'streetName',
        'name'      => 'name',
        'city'      => 'city',
        'state'     => 'state',
        'zip'       => 'zip',
        'locality'  => 'locality',
        'last_visit'=> 'lastVisit',
        'comments'  => 'comments',
        'coordinates'=> 'coordinates',
    ];
    $setClauses = [];
    $setValues  = [];
    foreach ($allowed as $col) {
        $key = strtolower($col);
        $frontendKey = isset($altKeys[$key]) ? $altKeys[$key] : $key;
        if (array_key_exists($frontendKey, $body)) {
            $val = $body[$frontendKey] !== null ? trim((string)$body[$frontendKey]) : '';
            $setClauses[] = '"' . $col . '" = :' . $col;
            $setValues[':' . $col] = $val;
        }
    }
    if (empty($setClauses)) {
        echo json_encode(['success' => true, 'message' => 'Nothing to update']);
        exit;
    }
    $setValues[':id'] = $id;
    $sql = 'UPDATE "Addresses_AWS" SET ' . implode(', ', $setClauses) . ' WHERE "ID" = :id';
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($setValues);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Update failed']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Address updated']);
    exit;
}
