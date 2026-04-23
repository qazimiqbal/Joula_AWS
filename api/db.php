<?php
$con = mysqli_connect("p3plzcpnl491154.prod.phx3.secureserver.net", "joula", "Joula@955");
if (mysqli_connect_errno()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit;
}
$db = "joula";
mysqli_select_db($con, $db);
?>
