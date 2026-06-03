
<?php
include_once __DIR__ . '/cors.php';
// delete_address.php: Delete a pending address by ID (Postgres/PDO)
header('Content-Type: application/json');
require_once 'db.pgsql.php';

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

try {
    $stmt = $pdo->prepare('DELETE FROM "Addresses_AWS" WHERE "ID" = :id');
    $success = $stmt->execute([':id' => $addressId]);
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete address']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
