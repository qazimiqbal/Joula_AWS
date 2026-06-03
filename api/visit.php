<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.pgsql.php';

// ── GET: fetch existing Comments for an address ────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        exit;
    }

    $sql = 'SELECT "Comments" FROM "Addresses_AWS" WHERE "ID" = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode([
            'success' => true,
            'data'    => [
                'comments'   => $row['Comments'] ?? '',
                'ethinicity' => 'Others',
                'potential'  => 'No',
            ],
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }
    exit;
}

// ── POST: update visit data ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $id         = isset($input['id'])         ? intval($input['id'])         : 0;
    $today      = isset($input['today'])      ? trim($input['today'])        : date('Y-m-d');
    $actionTaken = isset($input['actionTaken']) ? trim($input['actionTaken']) : '';
    $comments   = isset($input['comments'])   ? trim($input['comments'])     : '';

    if ($id === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        exit;
    }

    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
        $today = date('Y-m-d');
    }

    // Determine Status / Verified changes based on action taken
    $newStatus   = null;
    $newVerified = null;
    switch ($actionTaken) {
        case 'met':
        case 'left_message':
            $newStatus = 'Muslim'; $newVerified = 'Y'; break;
        case 'No_Response':
            $newVerified = 'N'; break;
        case 'Ismailee':
            $newStatus = 'Ismailee'; $newVerified = 'Y'; break;
        case 'Owner_muslim_rented_non_muslim':
            $newStatus = 'Owner_Muslim'; $newVerified = 'Y'; break;
        case 'Non_muslim':
            $newStatus = 'Non_muslim'; $newVerified = 'Y'; break;
    }

    // Build parameterised UPDATE
    // Base columns always updated
    $setCols = [];
    $params = [];
    $setCols[] = '"Last_Visit" = :lastVisit';
    $params[':lastVisit'] = $today;
    $setCols[] = '"Comments" = :comments';
    $params[':comments'] = $comments;
    if ($newStatus !== null) {
        $setCols[] = '"Status" = :status';
        $params[':status'] = $newStatus;
    }
    if ($newVerified !== null) {
        $setCols[] = '"Verified" = :verified';
        $params[':verified'] = $newVerified;
    }
    $params[':id'] = $id;
    $sql = 'UPDATE "Addresses_AWS" SET ' . implode(', ', $setCols) . ' WHERE "ID" = :id';
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update record']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>
