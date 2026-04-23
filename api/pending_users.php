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

if (permission_to_level($requesterPermissionsRaw) < 3) {
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

    respond(200, array('success' => true, 'message' => 'User review completed successfully'));
}

respond(405, array('success' => false, 'message' => 'Method not allowed'));
