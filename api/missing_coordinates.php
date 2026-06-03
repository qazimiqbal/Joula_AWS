<?php
include_once __DIR__ . '/cors.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.pgsql.php';

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

function get_auth_user_mc($pdo) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);

    $sql = 'SELECT id, permissions, org_id FROM "Login_user_AWS" WHERE auth_token = :token AND status = :status LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    return [
        'id' => intval($row['id']),
        'permission_level' => permission_to_level(isset($row['permissions']) ? $row['permissions'] : ''),
        'org_id' => intval($row['org_id']),
    ];
}

$authUser = get_auth_user_mc($pdo);
if (!$authUser) {
    respond(401, ['success' => false, 'message' => 'Unauthorized']);
}

$userId = $authUser['id'];
$isSuperAdmin = $authUser['permission_level'] >= 4;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if ($isSuperAdmin) {
            $sql = 'SELECT "ID", "Name", "H_No", "Apt_No", "St_Name", "City", "State", "Zip", "Locality"
                    FROM "Addresses_AWS"
                    WHERE "Coordinates" IS NULL OR TRIM("Coordinates") = :empty
                    ORDER BY "City", "St_Name", "H_No"';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':empty' => '']);
        } else {
            $sql = 'SELECT "ID", "Name", "H_No", "Apt_No", "St_Name", "City", "State", "Zip", "Locality"
                    FROM "Addresses_AWS"
                    WHERE ("Coordinates" IS NULL OR TRIM("Coordinates") = :empty)
                      AND "uploaded_by" = :uploadedBy
                    ORDER BY "City", "St_Name", "H_No"';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':empty' => '', ':uploadedBy' => $userId]);
        }

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
            ];
        }

        respond(200, ['success' => true, 'data' => $rows, 'count' => count($rows)]);
    } catch (Exception $e) {
        respond(500, ['success' => false, 'message' => 'Failed to load missing coordinates', 'error' => $e->getMessage()]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = isset($input['action']) ? trim((string)$input['action']) : '';

    if ($action === 'update_address') {
        $id = isset($input['id']) ? intval($input['id']) : 0;
        $name = isset($input['name']) ? trim((string)$input['name']) : '';
        $houseNo = isset($input['houseNo']) ? trim((string)$input['houseNo']) : '';
        $aptNo = isset($input['aptNo']) ? trim((string)$input['aptNo']) : '';
        $streetName = isset($input['streetName']) ? trim((string)$input['streetName']) : '';
        $city = isset($input['city']) ? trim((string)$input['city']) : '';
        $state = isset($input['state']) ? trim((string)$input['state']) : '';
        $zip = isset($input['zip']) ? trim((string)$input['zip']) : '';
        $locality = isset($input['locality']) ? trim((string)$input['locality']) : '';

        if ($id <= 0) {
            respond(400, ['success' => false, 'message' => 'Valid id is required']);
        }
        if ($streetName === '' || $city === '' || $state === '' || $zip === '') {
            respond(400, ['success' => false, 'message' => 'streetName, city, state, and zip are required']);
        }

        try {
            $sql = 'UPDATE "Addresses_AWS"
                    SET "Name" = :name,
                        "H_No" = :houseNo,
                        "Apt_No" = :aptNo,
                        "St_Name" = :streetName,
                        "City" = :city,
                        "State" = :state,
                        "Zip" = :zip,
                        "Locality" = :locality
                    WHERE "ID" = :id';

            $params = [
                ':name' => $name,
                ':houseNo' => $houseNo,
                ':aptNo' => $aptNo,
                ':streetName' => $streetName,
                ':city' => $city,
                ':state' => $state,
                ':zip' => $zip,
                ':locality' => $locality,
                ':id' => $id,
            ];

            if (!$isSuperAdmin) {
                $sql .= ' AND "uploaded_by" = :uploadedBy';
                $params[':uploadedBy'] = $userId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() <= 0) {
                respond(404, ['success' => false, 'message' => 'Address not found or not accessible']);
            }

            respond(200, ['success' => true, 'message' => 'Address updated successfully']);
        } catch (Exception $e) {
            respond(500, ['success' => false, 'message' => 'Failed to update address', 'error' => $e->getMessage()]);
        }
    }

    if ($action === 'delete_address') {
        $id = isset($input['id']) ? intval($input['id']) : 0;
        if ($id <= 0) {
            respond(400, ['success' => false, 'message' => 'Valid id is required']);
        }

        try {
            $sql = 'DELETE FROM "Addresses_AWS" WHERE "ID" = :id';
            $params = [':id' => $id];

            if (!$isSuperAdmin) {
                $sql .= ' AND "uploaded_by" = :uploadedBy';
                $params[':uploadedBy'] = $userId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() <= 0) {
                respond(404, ['success' => false, 'message' => 'Address not found or not accessible']);
            }

            respond(200, ['success' => true, 'message' => 'Address deleted successfully']);
        } catch (Exception $e) {
            respond(500, ['success' => false, 'message' => 'Failed to delete address', 'error' => $e->getMessage()]);
        }
    }

    if (!$isSuperAdmin) {
        respond(403, ['success' => false, 'message' => 'Only super admin can save coordinates']);
    }

    $id = isset($input['id']) ? intval($input['id']) : 0;
    $latitude = isset($input['latitude']) ? trim((string)$input['latitude']) : '';
    $longitude = isset($input['longitude']) ? trim((string)$input['longitude']) : '';

    if ($id <= 0 || $latitude === '' || $longitude === '') {
        respond(400, ['success' => false, 'message' => 'id, latitude, and longitude are required']);
    }

    $coordinates = $latitude . ',' . $longitude;

    try {
        $sql = 'UPDATE "Addresses_AWS" SET "Coordinates" = :coordinates, "Clear" = :clear WHERE "ID" = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':coordinates' => $coordinates, ':clear' => 0, ':id' => $id]);

        if ($stmt->rowCount() <= 0) {
            respond(404, ['success' => false, 'message' => 'Address not found or not accessible']);
        }

        respond(200, ['success' => true, 'message' => 'Coordinates saved successfully']);
    } catch (Exception $e) {
        respond(500, ['success' => false, 'message' => 'Failed to save coordinates', 'error' => $e->getMessage()]);
    }
}

respond(405, ['success' => false, 'message' => 'Method not allowed']);
