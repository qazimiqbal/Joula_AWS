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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, array('success' => false, 'message' => 'Method not allowed'));
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : '';

if ($username === '' || $password === '' || $email === '' || $phone === '') {
    respond(400, array('success' => false, 'message' => 'Username, password, email, and phone are required'));
}

include('db.php');
mysqli_select_db($con, $db);

// Check duplicate username
$usernameExists = false;
$stmtUsername = mysqli_prepare($con, "SELECT id FROM Login_user_AWS WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmtUsername, 's', $username);
mysqli_stmt_execute($stmtUsername);
mysqli_stmt_bind_result($stmtUsername, $existingUserId);
if (mysqli_stmt_fetch($stmtUsername)) {
    $usernameExists = true;
}
mysqli_stmt_close($stmtUsername);

// Check duplicate password hash
$passwordExists = false;
$stmtPassword = mysqli_prepare($con, "SELECT id FROM Login_user_AWS WHERE password = MD5(?) LIMIT 1");
mysqli_stmt_bind_param($stmtPassword, 's', $password);
mysqli_stmt_execute($stmtPassword);
mysqli_stmt_bind_result($stmtPassword, $existingPasswordUserId);
if (mysqli_stmt_fetch($stmtPassword)) {
    $passwordExists = true;
}
mysqli_stmt_close($stmtPassword);

// Check duplicate email
$emailExists = false;
$stmtEmail = mysqli_prepare($con, "SELECT id FROM Login_user_AWS WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($stmtEmail, 's', $email);
mysqli_stmt_execute($stmtEmail);
mysqli_stmt_bind_result($stmtEmail, $existingEmailUserId);
if (mysqli_stmt_fetch($stmtEmail)) {
    $emailExists = true;
}
mysqli_stmt_close($stmtEmail);

if ($usernameExists || $passwordExists || $emailExists) {
    $messageParts = array();
    if ($usernameExists) {
        $messageParts[] = 'username already exists';
    }
    if ($passwordExists) {
        $messageParts[] = 'password already exists';
    }
    if ($emailExists) {
        $messageParts[] = 'email already exists';
    }

    respond(409, array(
        'success' => false,
        'message' => 'This ' . implode(' and ', $messageParts) . '. Please change and try again.'
    ));
}

$permissions = 'Viewer';
$status = 'false';
$passChange = 'No';
$masjid = '';
$locality = '';
$halaqa = '';

$stmtInsert = mysqli_prepare(
    $con,
    "INSERT INTO Login_user_AWS (username, password, email, phone, Masjid, Locality, Halaqa, Permissions, Pass_change, status) VALUES (?, MD5(?), ?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmtInsert) {
    respond(500, array('success' => false, 'message' => 'Failed to prepare registration query'));
}

mysqli_stmt_bind_param(
    $stmtInsert,
    'ssssssssss',
    $username,
    $password,
    $email,
    $phone,
    $masjid,
    $locality,
    $halaqa,
    $permissions,
    $passChange,
    $status
);

if (!mysqli_stmt_execute($stmtInsert)) {
    mysqli_stmt_close($stmtInsert);
    respond(500, array('success' => false, 'message' => 'Failed to create account request'));
}
mysqli_stmt_close($stmtInsert);

// Notify super administrators via email.
$adminEmails = array();
$stmtAdmins = mysqli_prepare($con, "SELECT email FROM Login_user_AWS WHERE Permissions = 'Super Administrator' AND status = 'true' AND email IS NOT NULL AND email <> ''");
if ($stmtAdmins) {
    mysqli_stmt_execute($stmtAdmins);
    mysqli_stmt_bind_result($stmtAdmins, $adminEmail);
    while (mysqli_stmt_fetch($stmtAdmins)) {
        $adminEmails[] = $adminEmail;
    }
    mysqli_stmt_close($stmtAdmins);
}

if (!empty($adminEmails)) {
    $subject = 'New Joula account request pending approval';
    $message = "A new user has created an account request and needs approval.\n\n";
    $message .= "Username: " . $username . "\n";
    $message .= "Email: " . $email . "\n";
    $message .= "Phone: " . $phone . "\n\n";
    $message .= "Please log in to the Joula dashboard and review Pending User Approvals.";

    foreach ($adminEmails as $toEmail) {
        @mail($toEmail, $subject, $message);
    }
}

respond(200, array(
    'success' => true,
    'message' => 'Account request submitted successfully. Pending super administrator approval.'
));
