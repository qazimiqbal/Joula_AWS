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

function coordinates_filter_sql($alias = '') {
    $prefix = $alias !== '' ? $alias . '.' : '';
    return 'COALESCE(TRIM(' . $prefix . '"Coordinates"), \'\') <> \'\' AND ' . $prefix . '"Coordinates" <> ' . chr(39) . ',' . chr(39);
}

function get_authenticated_user($pdo) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);

    $sql = 'SELECT id, org_id, org_role, permissions FROM "Login_user_AWS" WHERE auth_token = :token AND status = :status LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    return [
        'id' => intval($row['id']),
        'orgId' => intval($row['org_id']),
        'orgRole' => (string)$row['org_role'],
        'permissionLevel' => permission_to_level($row['permissions']),
    ];
}

function resolve_effective_owner_id($pdo, $me) {
    if ($me['permissionLevel'] >= 3 || empty($me['orgId'])) {
        return intval($me['id']);
    }

    $sql = 'SELECT id FROM "Login_user_AWS" WHERE org_id = :orgId AND status = :status AND (org_role = :orgAdmin OR org_role = :admin OR permissions = :perm3 OR permissions = :perm4) ORDER BY CASE WHEN org_role = :orgAdmin THEN 0 WHEN org_role = :admin THEN 1 ELSE 2 END, id ASC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':orgId' => $me['orgId'],
        ':status' => 'true',
        ':orgAdmin' => 'org_admin',
        ':admin' => 'admin',
        ':perm3' => '3',
        ':perm4' => '4',
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($row && isset($row['id'])) ? intval($row['id']) : intval($me['id']);
}

require_once 'db.pgsql.php';

$me = get_authenticated_user($pdo);
if (!$me) {
    respond(401, ['success' => false, 'message' => 'Unauthorized']);
}
if ($me['permissionLevel'] < 2) {
    respond(403, ['success' => false, 'message' => 'Only admins and editors can review submissions']);
}

$effectiveOwnerId = resolve_effective_owner_id($pdo, $me);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $createdBy = isset($_GET['createdBy']) ? intval($_GET['createdBy']) : 0;
    $sql = 'SELECT a."ID", a."Name", a."H_No", a."Apt_No", a."St_Name", a."City", a."State", a."Zip", a."Locality", a."Coordinates", a."Comments", a."Last_Visit", a."Verified", a."Masjid", a."uploaded_by", COALESCE(u."username", \'\') AS submitted_by FROM "Addresses_AWS" a LEFT JOIN "Login_user_AWS" u ON u."id" = a."uploaded_by" WHERE COALESCE(a."Clear", 1) = 0 AND ' . coordinates_filter_sql('a');
    $params = [];

    if ($me['permissionLevel'] >= 4) {
        if ($createdBy > 0) {
            $sql .= ' AND a."uploaded_by" = :createdBy';
            $params[':createdBy'] = $createdBy;
        }
    } else {
        $sql .= ' AND a."uploaded_by" = :ownerId';
        $params[':ownerId'] = $effectiveOwnerId;
    }

    $sql .= ' ORDER BY a."City", a."St_Name", a."H_No"';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];
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

        $rows[] = [
            'id' => intval($row['ID']),
            'name' => $row['Name'],
            'houseNo' => $row['H_No'],
            'aptNo' => $row['Apt_No'],
            'streetName' => $row['St_Name'],
            'city' => $row['City'],
            'state' => $row['State'],
            'zip' => $row['Zip'],
            'locality' => $row['Locality'],
            'comments' => $row['Comments'],
            'lastVisit' => $row['Last_Visit'],
            'verified' => $row['Verified'],
            'masjid' => $row['Masjid'],
            'coordinates' => $row['Coordinates'],
            'latitude' => $lat,
            'longitude' => $lng,
            'uploadedBy' => isset($row['uploaded_by']) ? intval($row['uploaded_by']) : null,
            'submittedBy' => $row['submitted_by'],
        ];
    }

    respond(200, ['success' => true, 'data' => $rows, 'count' => count($rows)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = isset($input['action']) ? trim((string)$input['action']) : 'approve';

    if ($action === 'approve_all') {
        if ($me['permissionLevel'] >= 4) {
            $sql = 'UPDATE "Addresses_AWS" SET "Clear" = 1 WHERE COALESCE("Clear", 1) = 0 AND ' . coordinates_filter_sql();
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } else {
            $sql = 'UPDATE "Addresses_AWS" SET "Clear" = 1 WHERE COALESCE("Clear", 1) = 0 AND "uploaded_by" = :ownerId AND ' . coordinates_filter_sql();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ownerId' => $effectiveOwnerId]);
        }

        respond(200, [
            'success' => true,
            'message' => 'Pending addresses approved',
            'approvedCount' => $stmt->rowCount(),
        ]);
    }

    if ($action === 'update') {
        $id = isset($input['id']) ? intval($input['id']) : 0;
        if ($id <= 0) {
            respond(400, ['success' => false, 'message' => 'id is required']);
        }

        $fieldMap = [
            'name' => 'Name',
            'houseNo' => 'H_No',
            'aptNo' => 'Apt_No',
            'streetName' => 'St_Name',
            'city' => 'City',
            'state' => 'State',
            'zip' => 'Zip',
            'locality' => 'Locality',
            'comments' => 'Comments',
            'lastVisit' => 'Last_Visit',
            'masjid' => 'Masjid',
            'verified' => 'Verified',
            'coordinates' => 'Coordinates',
        ];

        $setParts = [];
        $params = [':id' => $id];
        foreach ($fieldMap as $key => $column) {
            if (array_key_exists($key, $input)) {
                $setParts[] = '"' . $column . '" = :' . $key;
                $params[':' . $key] = trim((string)$input[$key]);
            }
        }

        if (count($setParts) === 0) {
            respond(400, ['success' => false, 'message' => 'No fields to update']);
        }

        $sql = 'UPDATE "Addresses_AWS" SET ' . implode(', ', $setParts) . ' WHERE "ID" = :id AND COALESCE("Clear", 1) = 0';
        if ($me['permissionLevel'] < 4) {
            $sql .= ' AND "uploaded_by" = :ownerId';
            $params[':ownerId'] = $effectiveOwnerId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() <= 0) {
            respond(404, ['success' => false, 'message' => 'Address not found, unchanged, or not permitted']);
        }

        respond(200, ['success' => true, 'message' => 'Pending address updated']);
    }

    $id = isset($input['id']) ? intval($input['id']) : 0;
    if ($id <= 0) {
        respond(400, ['success' => false, 'message' => 'id is required']);
    }

    if ($me['permissionLevel'] >= 4) {
        $sql = 'UPDATE "Addresses_AWS" SET "Clear" = 1 WHERE "ID" = :id AND COALESCE("Clear", 1) = 0 AND ' . coordinates_filter_sql();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
    } else {
        $sql = 'UPDATE "Addresses_AWS" SET "Clear" = 1 WHERE "ID" = :id AND COALESCE("Clear", 1) = 0 AND "uploaded_by" = :ownerId AND ' . coordinates_filter_sql();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':ownerId' => $effectiveOwnerId]);
    }

    if ($stmt->rowCount() <= 0) {
        respond(404, ['success' => false, 'message' => 'Address not found or already approved']);
    }

    respond(200, ['success' => true, 'message' => 'Address approved for regular map/list display']);
}

respond(405, ['success' => false, 'message' => 'Method not allowed']);
