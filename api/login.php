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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, array('success' => false, 'message' => 'Method not allowed'));
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$identifier = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

if ($identifier === '' || $password === '') {
    respond(400, array('success' => false, 'message' => 'Email/username and password are required'));
}

$usernameCandidate = $identifier;
$atPos = strpos($identifier, '@');
if ($atPos !== false) {
    $usernameCandidate = substr($identifier, 0, $atPos);
}

include('db.php');
mysqli_select_db($con, $db);

$sql = "SELECT id, username, email, phone, Permissions FROM Login_user_AWS WHERE status = 'true' AND (username = ? OR username = ? OR email = ?) AND password = MD5(?) LIMIT 1";
$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
    respond(500, array('success' => false, 'message' => 'Failed to prepare login query'));
}

mysqli_stmt_bind_param($stmt, 'ssss', $identifier, $usernameCandidate, $identifier, $password);
mysqli_stmt_execute($stmt);
$userRow = null;
mysqli_stmt_bind_result($stmt, $id, $username, $email, $phone, $permissionsRaw);
if (mysqli_stmt_fetch($stmt)) {
    $userRow = array(
        'id' => $id,
        'username' => $username,
        'email' => $email,
        'phone' => $phone,
        'Permissions' => $permissionsRaw
    );
}
mysqli_stmt_close($stmt);

if (!$userRow) {
    respond(401, array('success' => false, 'message' => 'Invalid credentials'));
}

$permissions = permission_to_level(isset($userRow['Permissions']) ? $userRow['Permissions'] : '');
$role = $permissions >= 3 ? 'admin' : 'user';

if (function_exists('random_bytes')) {
    $token = bin2hex(random_bytes(24));
} else {
    $token = md5(uniqid('', true));
}

$user = array(
    'id' => intval($userRow['id']),
    'name' => $userRow['username'],
    'email' => $userRow['email'] ? $userRow['email'] : $identifier,
    'phone' => $userRow['phone'] ? $userRow['phone'] : '',
    'role' => $role,
    'permissionLevel' => $permissions,
    'createdAt' => date('c')
);

respond(200, array(
    'success' => true,
    'data' => array(
        'token' => $token,
        'user' => $user
    )
));
