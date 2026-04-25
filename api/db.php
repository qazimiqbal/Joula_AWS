<?php
$con = mysqli_connect("p3plzcpnl491154.prod.phx3.secureserver.net", "joula", "Joula@955");
if (mysqli_connect_errno()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit;
}
$db = "Joula_AWS";
if (!mysqli_select_db($con, $db)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "Failed to select database '$db': " . mysqli_error($con)
    ]);
    exit;
}
?>
