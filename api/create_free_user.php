<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
    if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
    if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
    if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
    if (is_numeric($value)) return intval($value);
    return 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Method not allowed']);
}

include('db.php');
mysqli_select_db($con, $db);

// ── Auth: super admin only ─────────────────────────────────────────────────
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (strpos($authHeader, 'Bearer ') !== 0) {
    respond(401, ['success' => false, 'message' => 'Unauthorized']);
}
$token = substr($authHeader, 7);
$tokenEsc = mysqli_real_escape_string($con, $token);
$resU = mysqli_query($con, "SELECT id, Permissions FROM Login_user_AWS WHERE auth_token = '$tokenEsc' AND status = 'true' LIMIT 1");
if (!$resU) {
    respond(500, ['success' => false, 'message' => 'Auth query failed: ' . mysqli_error($con)]);
}
$rowU = mysqli_fetch_assoc($resU);
mysqli_free_result($resU);
if (!$rowU) {
    respond(401, ['success' => false, 'message' => 'Unauthorized']);
}
$permissionLevel = permission_to_level($rowU['Permissions']);
if ($permissionLevel < 4) {
    respond(403, ['success' => false, 'message' => 'Only super admins can create free users']);
}

// ── Ensure is_free_user column exists ─────────────────────────────────────
$colCheck = mysqli_query($con, "SHOW COLUMNS FROM Login_user_AWS LIKE 'is_free_user'");
if ($colCheck && mysqli_num_rows($colCheck) === 0) {
    mysqli_query($con, "ALTER TABLE Login_user_AWS ADD COLUMN is_free_user TINYINT(1) NOT NULL DEFAULT 0");
}
if ($colCheck) mysqli_free_result($colCheck);

// ── Parse input ───────────────────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) $input = $_POST;

$username = isset($input['username']) ? trim($input['username']) : '';
$email    = isset($input['email'])    ? trim($input['email'])    : '';
$password = isset($input['password']) ? trim($input['password']) : '';
$phone    = isset($input['phone'])    ? trim($input['phone'])    : '';

if ($username === '' || $email === '' || $password === '') {
    respond(400, ['success' => false, 'message' => 'Username, email, and password are required']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, ['success' => false, 'message' => 'Invalid email address']);
}

if (strlen($password) < 6) {
    respond(400, ['success' => false, 'message' => 'Password must be at least 6 characters']);
}

// ── Check uniqueness ───────────────────────────────────────────────────────
$safeUsername = mysqli_real_escape_string($con, $username);
$safeEmail    = mysqli_real_escape_string($con, $email);

$dupCheck = mysqli_query($con, "SELECT id FROM Login_user_AWS WHERE username = '$safeUsername' OR email = '$safeEmail' LIMIT 1");
if ($dupCheck && mysqli_num_rows($dupCheck) > 0) {
    mysqli_free_result($dupCheck);
    respond(409, ['success' => false, 'message' => 'Username or email already in use']);
}
if ($dupCheck) mysqli_free_result($dupCheck);

// ── Check phone column exists ──────────────────────────────────────────────
$phoneColCheck = mysqli_query($con, "SHOW COLUMNS FROM Login_user_AWS LIKE 'phone'");
$hasPhone = ($phoneColCheck && mysqli_num_rows($phoneColCheck) > 0);
if ($phoneColCheck) mysqli_free_result($phoneColCheck);

// ── Insert new free editor user ────────────────────────────────────────────
$safePhone    = $hasPhone ? mysqli_real_escape_string($con, $phone) : '';
$hashedPw     = md5($password); // MD5 matches existing auth pattern in login.php

if ($hasPhone) {
    $insertSql = "INSERT INTO Login_user_AWS (username, email, password, phone, Permissions, org_role, org_id, status, is_free_user)
                  VALUES ('$safeUsername', '$safeEmail', '$hashedPw', '$safePhone', '2', 'editor', 0, 'true', 1)";
} else {
    $insertSql = "INSERT INTO Login_user_AWS (username, email, password, Permissions, org_role, org_id, status, is_free_user)
                  VALUES ('$safeUsername', '$safeEmail', '$hashedPw', '2', 'editor', 0, 'true', 1)";
}

$ok = mysqli_query($con, $insertSql);
if (!$ok) {
    respond(500, ['success' => false, 'message' => 'Failed to create user: ' . mysqli_error($con)]);
}

$newId = mysqli_insert_id($con);

respond(201, [
    'success' => true,
    'message' => 'Free editor user created successfully',
    'data' => [
        'id'       => intval($newId),
        'username' => $username,
        'email'    => $email,
        'phone'    => $phone,
        'role'     => 'editor',
        'isFreeUser' => true,
    ]
]);
