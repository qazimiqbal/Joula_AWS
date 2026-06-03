<?php
include_once __DIR__ . '/cors.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

function get_authenticated_user($pdo) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);

    $sql = 'SELECT id, permissions FROM "Login_user_AWS" WHERE auth_token = :token AND status = :status LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !$row['id']) return null;

    return [
        'id' => intval($row['id']),
        'permissionLevel' => permission_to_level($row['permissions']),
    ];
}

require_once 'db.pgsql.php';

$me = get_authenticated_user($pdo);
if (!$me) {
    respond(401, array('success' => false, 'message' => 'Unauthorized'));
}
if ($me['permissionLevel'] < 4) {
    respond(403, array('success' => false, 'message' => 'Only Super Admin can review submissions'));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $createdBy = isset($_GET['createdBy']) ? intval($_GET['createdBy']) : 0;
    $sql = 'SELECT a."ID", a."Name", a."H_No", a."Apt_No", a."St_Name", a."City", a."State", a."Zip", a."Locality", a."Coordinates", a."uploaded_by", COALESCE(u."username", \'\') AS submitted_by FROM "Addresses_AWS" a LEFT JOIN "Login_user_AWS" u ON u."id" = a."uploaded_by" WHERE COALESCE(a."Clear", 1) = 0';
    $params = [];
    if ($createdBy > 0) {
        $sql .= ' AND a."uploaded_by" = :createdBy';
        $params[':createdBy'] = $createdBy;
    }
    $sql .= ' ORDER BY a."City", a."St_Name", a."H_No"';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $lat = null;
        $lng = null;
        $parts = explode(',', (string)($row['Coordinates'] ?? ''));
        if (count($parts) === 2) {
            $latRaw = trim($parts[0]);
            $lngRaw = trim($parts[1]);
            if ($latRaw !== '' && $lngRaw !== '' && is_numeric($latRaw) && is_numeric($lngRaw)) {
                $lat = floatval($latRaw);
                $lng = floatval($lngRaw);
            }
        }
        $rows[] = array(
            'id' => intval($row['ID']),
            'name' => $row['Name'],
            'houseNo' => $row['H_No'],
            'aptNo' => $row['Apt_No'],
            'streetName' => $row['St_Name'],
            'city' => $row['City'],
            'state' => $row['State'],
            'zip' => $row['Zip'],
            'locality' => $row['Locality'],
            'latitude' => $lat,
            'longitude' => $lng,
            'uploadedBy' => isset($row['uploaded_by']) ? intval($row['uploaded_by']) : null,
            'submittedBy' => $row['submitted_by'],
        );
    }
    respond(200, array('success' => true, 'data' => $rows, 'count' => count($rows)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $id = isset($input['id']) ? intval($input['id']) : 0;
    if ($id <= 0) {
        respond(400, array('success' => false, 'message' => 'id is required'));
    }
    $stmt = $pdo->prepare('UPDATE "Addresses_AWS" SET "Clear" = 1 WHERE "ID" = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() <= 0) {
        respond(404, array('success' => false, 'message' => 'Address not found or already approved'));
    }
    respond(200, array('success' => true, 'message' => 'Address approved for regular map/list display'));
}

respond(405, array('success' => false, 'message' => 'Method not allowed'));
?>