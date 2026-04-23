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
    if ($value === '3' || strcasecmp($value, 'Super Administrator') === 0) return 3;
    if ($value === '2' || strcasecmp($value, 'Administrator') === 0) return 2;
    if ($value === '1' || strcasecmp($value, 'Editor') === 0) return 1;
    return 0;
}

include('db.php');
mysqli_select_db($con, $db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        respond(400, array('success' => false, 'message' => 'Valid user id is required'));
    }

    $stmt = mysqli_prepare($con, "SELECT id, username, email, phone, Permissions FROM Login_user_AWS WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = null;
    mysqli_stmt_bind_result($stmt, $userId, $username, $emailDb, $phoneDb, $permissionsRaw);
    if (mysqli_stmt_fetch($stmt)) {
        $row = array(
            'id' => $userId,
            'username' => $username,
            'email' => $emailDb,
            'phone' => $phoneDb,
            'Permissions' => $permissionsRaw
        );
    }
    mysqli_stmt_close($stmt);

    if (!$row) {
        respond(404, array('success' => false, 'message' => 'User not found'));
    }

    $permissions = permission_to_level(isset($row['Permissions']) ? $row['Permissions'] : '');
    $role = $permissions >= 3 ? 'admin' : 'user';

    respond(200, array(
        'success' => true,
        'data' => array(
            'id' => intval($row['id']),
            'name' => $row['username'],
            'email' => $row['email'],
            'phone' => $row['phone'] ? $row['phone'] : '',
            'role' => $role,
            'permissionLevel' => $permissions,
            'createdAt' => date('c')
        )
    ));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $id = isset($input['id']) ? intval($input['id']) : 0;
    if ($id <= 0) {
        respond(400, array('success' => false, 'message' => 'Valid user id is required'));
    }

    $stmtCurrent = mysqli_prepare($con, "SELECT username, email, phone, Permissions FROM Login_user_AWS WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtCurrent, 'i', $id);
    mysqli_stmt_execute($stmtCurrent);
    $currentRow = null;
    mysqli_stmt_bind_result($stmtCurrent, $currentUsername, $currentEmail, $currentPhone, $permissionsRaw);
    if (mysqli_stmt_fetch($stmtCurrent)) {
        $currentRow = array(
            'username' => $currentUsername,
            'email' => $currentEmail,
            'phone' => $currentPhone,
            'Permissions' => $permissionsRaw,
        );
    }
    mysqli_stmt_close($stmtCurrent);

    if (!$currentRow) {
        respond(404, array('success' => false, 'message' => 'User not found'));
    }

    $name = isset($input['name']) ? trim($input['name']) : trim($currentRow['username']);
    $email = isset($input['email']) ? trim($input['email']) : trim($currentRow['email']);
    $phone = isset($input['phone']) ? trim($input['phone']) : trim($currentRow['phone']);
    $password = isset($input['password']) ? trim($input['password']) : '';

    if ($name === '' || $email === '') {
        respond(400, array('success' => false, 'message' => 'Name and email are required'));
    }

    if ($password !== '') {
        $stmt = mysqli_prepare($con, "UPDATE Login_user_AWS SET username = ?, email = ?, phone = ?, password = MD5(?) WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $phone, $password, $id);
    } else {
        $stmt = mysqli_prepare($con, "UPDATE Login_user_AWS SET username = ?, email = ?, phone = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'sssi', $name, $email, $phone, $id);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $permissions = permission_to_level(isset($currentRow['Permissions']) ? $currentRow['Permissions'] : '');

    $role = $permissions >= 3 ? 'admin' : 'user';

    respond(200, array(
        'success' => true,
        'data' => array(
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'permissionLevel' => $permissions,
            'createdAt' => date('c')
        ),
        'message' => 'Profile updated successfully'
    ));
}

respond(405, array('success' => false, 'message' => 'Method not allowed'));
