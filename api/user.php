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

include('../connection.php.ini');
mysqli_select_db($con, $db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        respond(400, array('success' => false, 'message' => 'Valid user id is required'));
    }

    $stmt = mysqli_prepare($con, "SELECT id, username, email, phone, Permissions FROM Login_user WHERE id = ? LIMIT 1");
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

    $permissions = isset($row['Permissions']) ? intval($row['Permissions']) : 0;
    $role = $permissions >= 3 ? 'admin' : 'user';

    respond(200, array(
        'success' => true,
        'data' => array(
            'id' => intval($row['id']),
            'name' => $row['username'],
            'email' => $row['email'],
            'phone' => $row['phone'] ? $row['phone'] : '',
            'role' => $role,
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

    $name = isset($input['name']) ? trim($input['name']) : '';
    $email = isset($input['email']) ? trim($input['email']) : '';
    $phone = isset($input['phone']) ? trim($input['phone']) : '';

    if ($name === '' || $email === '') {
        respond(400, array('success' => false, 'message' => 'Name and email are required'));
    }

    $stmt = mysqli_prepare($con, "UPDATE Login_user SET username = ?, email = ?, phone = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'sssi', $name, $email, $phone, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $permissions = 0;
    $stmt2 = mysqli_prepare($con, "SELECT Permissions FROM Login_user WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt2, 'i', $id);
    mysqli_stmt_execute($stmt2);
    $row2 = null;
    mysqli_stmt_bind_result($stmt2, $permissionsRaw);
    if (mysqli_stmt_fetch($stmt2)) {
        $row2 = array('Permissions' => $permissionsRaw);
    }
    mysqli_stmt_close($stmt2);
    if ($row2 && isset($row2['Permissions'])) {
        $permissions = intval($row2['Permissions']);
    }

    $role = $permissions >= 3 ? 'admin' : 'user';

    respond(200, array(
        'success' => true,
        'data' => array(
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'createdAt' => date('c')
        ),
        'message' => 'Profile updated successfully'
    ));
}

respond(405, array('success' => false, 'message' => 'Method not allowed'));
