<?php
// delete_masjid.php: Delete a masjid by ID
header('Content-Type: application/json');
require_once 'db.pgsql.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$masjidId = isset($data['id']) ? intval($data['id']) : 0;

if ($masjidId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid masjid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM "Masjids_AWS" WHERE "ID" = :id');
    $success = $stmt->execute([':id' => $masjidId]);
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete masjid']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
