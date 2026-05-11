<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

include('db.php');
mysqli_select_db($con, $db);

function get_authenticated_user_update($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);
    $stmt = mysqli_prepare($con,
        "SELECT id, org_id, Permissions FROM Login_user_AWS
         WHERE auth_token = ? AND status = 'true' LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $userId = $orgId = $permissions = null;
    mysqli_stmt_bind_result($stmt, $userId, $orgId, $permissions);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$found || !$userId) return null;
    return [
        'id' => intval($userId),
        'org_id' => intval($orgId),
        'permission_level' => intval($permissions),
    ];
}

$user = get_authenticated_user_update($con);
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
    $setTypes   = '';
    $setValues  = [];

    foreach ($allowed as $col) {
        $key = strtolower($col);
        // Accept both camelCase variants and raw column names
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
        $frontendKey = isset($altKeys[$key]) ? $altKeys[$key] : $key;

        if (array_key_exists($frontendKey, $body)) {
            $val = $body[$frontendKey] !== null ? trim((string)$body[$frontendKey]) : '';
            $setClauses[] = "`$col` = ?";
            $setTypes    .= 's';
            $setValues[]  = $val;
        }
    }

    if (empty($setClauses)) {
        echo json_encode(['success' => true, 'message' => 'Nothing to update']);
        exit;
    }

    $setValues[] = $id;
    $setTypes   .= 'i';
    $sql = "UPDATE Masjids_AWS SET " . implode(', ', $setClauses) . " WHERE ID = ?";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . mysqli_error($con)]);
        exit;
    }
    mysqli_stmt_bind_param($stmt, $setTypes, ...$setValues);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

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
    $setTypes   = '';
    $setValues  = [];

    foreach ($allowed as $col) {
        $key = strtolower($col);
        $frontendKey = isset($altKeys[$key]) ? $altKeys[$key] : $key;

        if (array_key_exists($frontendKey, $body)) {
            $val = $body[$frontendKey] !== null ? trim((string)$body[$frontendKey]) : '';
            $setClauses[] = "`$col` = ?";
            $setTypes    .= 's';
            $setValues[]  = $val;
        }
    }

    if (empty($setClauses)) {
        echo json_encode(['success' => true, 'message' => 'Nothing to update']);
        exit;
    }

    $setValues[] = $id;
    $setTypes   .= 'i';
    $sql = "UPDATE Addresses_AWS SET " . implode(', ', $setClauses) . " WHERE ID = ?";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . mysqli_error($con)]);
        exit;
    }
    mysqli_stmt_bind_param($stmt, $setTypes, ...$setValues);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Update failed']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Address updated']);
    exit;
}
