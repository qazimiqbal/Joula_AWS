<?php
include_once __DIR__ . '/cors.php';
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

function permission_to_level($permissionRaw) {
    $value = trim((string)$permissionRaw);
    if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
    if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
    if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
    if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
    if (is_numeric($value)) return intval($value);
    return 0;
}

function has_table_column($pdo, $tableName, $columnName) {
    $sql = 'SELECT 1 FROM information_schema.columns WHERE table_schema = :schema AND table_name = :tableName AND column_name = :columnName LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':schema' => 'public',
        ':tableName' => $tableName,
        ':columnName' => $columnName,
    ]);
    return (bool)$stmt->fetchColumn();
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

require_once __DIR__ . '/db.pgsql.php';

$me = get_authenticated_user($pdo);
if (!$me) {
    respond(401, ['success' => false, 'message' => 'Unauthorized']);
}
if ($me['permissionLevel'] < 2) {
    respond(403, ['success' => false, 'message' => 'Only admins and editors can review submissions']);
}

$isSuperAdmin = $me['permissionLevel'] >= 4;
$effectiveOwnerId = resolve_effective_owner_id($pdo, $me);
$hasSubmittedBy = has_table_column($pdo, 'Masjids_AWS', 'Submitted_by');
$submittedByExpr = $hasSubmittedBy ? 'COALESCE(m."Submitted_by", m."Created_by")' : 'm."Created_by"';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $requestedCreatedBy = isset($_GET['createdBy']) ? intval($_GET['createdBy']) : 0;
    $params = [];

    $sql = 'SELECT m."ID", m."Name", m."H_No", m."Apt_No", m."St_Name", m."City", m."State", m."Zip", m."Coordinates", m."Created_by", COALESCE(u."username", \'\') AS submitted_by
            FROM "Masjids_AWS" m
            LEFT JOIN "Login_user_AWS" u ON u."id" = ' . $submittedByExpr . '
            WHERE COALESCE(m."Clear", 1) = 0';

    if ($isSuperAdmin) {
        if ($requestedCreatedBy > 0) {
            $sql .= ' AND m."Created_by" = :createdBy';
            $params[':createdBy'] = $requestedCreatedBy;
        }
    } else {
        $sql .= ' AND m."Created_by" = :ownerId';
        $params[':ownerId'] = $effectiveOwnerId;
    }

    $sql .= ' ORDER BY m."City", m."St_Name", m."H_No"';

    try {
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
                'coordinates' => $row['Coordinates'],
                'latitude' => $lat,
                'longitude' => $lng,
                'createdBy' => isset($row['Created_by']) ? intval($row['Created_by']) : null,
                'submittedBy' => $row['submitted_by'],
            ];
        }

        respond(200, ['success' => true, 'data' => $rows, 'count' => count($rows)]);
    } catch (Exception $e) {
        respond(500, ['success' => false, 'message' => 'Failed to load masjid review list', 'error' => $e->getMessage()]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = isset($input['action']) ? trim((string)$input['action']) : 'approve';
    $id = isset($input['id']) ? intval($input['id']) : 0;

    if ($id <= 0) {
        respond(400, ['success' => false, 'message' => 'id is required']);
    }

    if (!$isSuperAdmin) {
        $ownerStmt = $pdo->prepare('SELECT "Created_by" FROM "Masjids_AWS" WHERE "ID" = :id LIMIT 1');
        $ownerStmt->execute([':id' => $id]);
        $ownerRow = $ownerStmt->fetch(PDO::FETCH_ASSOC);
        $ownerId = $ownerRow && isset($ownerRow['Created_by']) ? intval($ownerRow['Created_by']) : 0;

        if ($ownerId !== $effectiveOwnerId) {
            respond(403, ['success' => false, 'message' => 'You can only modify submissions for your parent account']);
        }
    }

    if ($action === 'update') {
        $name = isset($input['name']) ? trim((string)$input['name']) : '';
        $houseNo = isset($input['houseNo']) ? trim((string)$input['houseNo']) : '';
        $aptNo = isset($input['aptNo']) ? trim((string)$input['aptNo']) : '';
        $streetName = isset($input['streetName']) ? trim((string)$input['streetName']) : '';
        $city = isset($input['city']) ? trim((string)$input['city']) : '';
        $state = isset($input['state']) ? trim((string)$input['state']) : '';
        $zip = isset($input['zip']) ? trim((string)$input['zip']) : '';
        $coordRaw = isset($input['coordinates']) ? trim((string)$input['coordinates']) : '';

        $coordinates = '';
        if ($coordRaw !== '') {
            if (!preg_match('/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*$/', $coordRaw)) {
                respond(400, ['success' => false, 'message' => 'coordinates must be in "lat,lng" format']);
            }
            $parts = explode(',', $coordRaw, 2);
            $coordinates = trim($parts[0]) . ',' . trim($parts[1]);
        }

        try {
            $stmt = $pdo->prepare('UPDATE "Masjids_AWS" SET "Name" = :name, "H_No" = :houseNo, "Apt_No" = :aptNo, "St_Name" = :streetName, "City" = :city, "State" = :state, "Zip" = :zip, "Coordinates" = :coordinates WHERE "ID" = :id');
            $stmt->execute([
                ':name' => $name,
                ':houseNo' => $houseNo,
                ':aptNo' => $aptNo,
                ':streetName' => $streetName,
                ':city' => $city,
                ':state' => $state,
                ':zip' => $zip,
                ':coordinates' => $coordinates,
                ':id' => $id,
            ]);

            if ($stmt->rowCount() <= 0) {
                respond(404, ['success' => false, 'message' => 'Masjid not found or unchanged']);
            }

            respond(200, ['success' => true, 'message' => 'Masjid updated']);
        } catch (Exception $e) {
            respond(500, ['success' => false, 'message' => 'Failed to update masjid', 'error' => $e->getMessage()]);
        }
    }

    if ($action === 'delete') {
        try {
            $stmt = $pdo->prepare('DELETE FROM "Masjids_AWS" WHERE "ID" = :id');
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() <= 0) {
                respond(404, ['success' => false, 'message' => 'Masjid not found']);
            }
            respond(200, ['success' => true, 'message' => 'Masjid deleted']);
        } catch (Exception $e) {
            respond(500, ['success' => false, 'message' => 'Failed to delete masjid', 'error' => $e->getMessage()]);
        }
    }

    try {
        $stmt = $pdo->prepare('UPDATE "Masjids_AWS" SET "Clear" = 1 WHERE "ID" = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() <= 0) {
            respond(404, ['success' => false, 'message' => 'Masjid not found or already approved']);
        }

        respond(200, ['success' => true, 'message' => 'Masjid approved']);
    } catch (Exception $e) {
        respond(500, ['success' => false, 'message' => 'Failed to approve masjid', 'error' => $e->getMessage()]);
    }
}

respond(405, ['success' => false, 'message' => 'Method not allowed']);
