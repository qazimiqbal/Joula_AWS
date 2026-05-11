<?php
// delete_address.php: Delete a pending address by ID
header('Content-Type: application/json');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$addressId = isset($data['id']) ? intval($data['id']) : 0;

if ($addressId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
    exit;
}

$stmt = mysqli_prepare($con, 'DELETE FROM Addresses_AWS WHERE ID = ?');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $addressId);
$success = mysqli_stmt_execute($stmt);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete address']);
}

mysqli_stmt_close($stmt);
$con->close();
