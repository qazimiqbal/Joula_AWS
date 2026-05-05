<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Masjid-Create-Version: 2026-05-04-insert-diagnostics-v8');

// Temporary fallback for environments where GOOGLE_MAPS_API_KEY is not configured.
putenv('GOOGLE_MAPS_API_KEY=AIzaSyDzWWzAZ6-PxDds7RX3FVeaDa22RqIr8HU');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'CORS preflight OK']);
    exit;
}

if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($statusCode, $payload, $debugInResponse = false, $debugValues = null, $debugQuery = null)
    {
        http_response_code($statusCode);
        $payload['apiVersion'] = '2026-05-04-insert-diagnostics-v8';
        if ($debugInResponse) {
            $payload['debug'] = [
                'values' => $debugValues,
                'query' => $debugQuery,
            ];
        }
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('resolveCoordinatesForAddress')) {
    function resolveCoordinatesForAddress($address)
    {
        $apiKey = getenv('GOOGLE_MAPS_API_KEY');
        $apiKeySource = $apiKey ? 'env' : null;
        if (!$apiKey) {
            return [
                'success' => false,
                'reason' => 'missing_api_key',
                'status' => null,
                'errorMessage' => 'GOOGLE_MAPS_API_KEY is not configured on server',
                'apiKeySource' => $apiKeySource,
            ];
        }

        $fetchUrl = function ($url, $headers = []) {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                if (!empty($headers)) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                }
                $body = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($body !== false && $httpCode >= 200 && $httpCode < 300) {
                    return $body;
                }
            }

            $context = null;
            if (!empty($headers)) {
                $context = stream_context_create([
                    'http' => [
                        'header' => implode("\r\n", $headers) . "\r\n",
                        'timeout' => 8,
                    ],
                ]);
            }

            $body = @file_get_contents($url, false, $context);
            if ($body !== false) {
                return $body;
            }

            return null;
        };

        $googleUrl = 'https://maps.googleapis.com/maps/api/geocode/json?address='
            . urlencode($address)
            . '&key=' . urlencode($apiKey);
        $googleJson = $fetchUrl($googleUrl, ['User-Agent: MyJoula/1.0']);
        if ($googleJson === null) {
            return [
                'success' => false,
                'reason' => 'http_failure',
                'status' => null,
                'errorMessage' => 'Unable to reach Google Geocoding API',
                'apiKeySource' => $apiKeySource,
            ];
        }

        $googleData = json_decode($googleJson, true);
        if (!is_array($googleData)) {
            return [
                'success' => false,
                'reason' => 'invalid_json',
                'status' => null,
                'errorMessage' => 'Invalid JSON response from Google Geocoding API',
                'apiKeySource' => $apiKeySource,
            ];
        }

        $status = $googleData['status'] ?? null;
        if ($status !== 'OK') {
            return [
                'success' => false,
                'reason' => 'google_status',
                'status' => $status,
                'errorMessage' => $googleData['error_message'] ?? null,
                'apiKeySource' => $apiKeySource,
            ];
        }

        $location = $googleData['results'][0]['geometry']['location'] ?? null;
        if (!$location || !isset($location['lat']) || !isset($location['lng'])) {
            return [
                'success' => false,
                'reason' => 'missing_location',
                'status' => $status,
                'errorMessage' => 'Google response missing location geometry',
                'apiKeySource' => $apiKeySource,
            ];
        }

        $lat = (float) $location['lat'];
        $lng = (float) $location['lng'];

        return [
            'success' => true,
            'lat' => $lat,
            'lng' => $lng,
            'coordinates' => $lat . ',' . $lng,
            'reason' => null,
            'status' => $status,
            'errorMessage' => null,
            'apiKeySource' => $apiKeySource,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
}

include('db.php');

if (!function_exists('has_table_column')) {
    function has_table_column($con, $table, $column)
    {
        $tableSafe = mysqli_real_escape_string($con, $table);
        $columnSafe = mysqli_real_escape_string($con, $column);
        $result = mysqli_query($con, "SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
        if (!$result) return false;
        $exists = mysqli_num_rows($result) > 0;
        mysqli_free_result($result);
        return $exists;
    }
}

if (!function_exists('get_table_columns')) {
    function get_table_columns($con, $table)
    {
        $tableSafe = mysqli_real_escape_string($con, $table);
        $result = mysqli_query($con, "SHOW COLUMNS FROM `{$tableSafe}`");
        if (!$result) return [];
        $columns = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if (isset($row['Field'])) {
                $columns[] = $row['Field'];
            }
        }
        mysqli_free_result($result);
        return $columns;
    }
}

if (!function_exists('first_existing_column')) {
    function first_existing_column($con, $table, $candidates)
    {
        foreach ($candidates as $candidate) {
            if (has_table_column($con, $table, $candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('ensure_masjid_optional_columns')) {
    function ensure_masjid_optional_columns($con)
    {
        $requiredOptionalColumns = [
            'Created_by' => 'ALTER TABLE Masjids_AWS ADD COLUMN Created_by INT DEFAULT NULL',
            'Submitted_by' => 'ALTER TABLE Masjids_AWS ADD COLUMN Submitted_by INT DEFAULT NULL',
            'Clear' => 'ALTER TABLE Masjids_AWS ADD COLUMN `Clear` TINYINT(1) NOT NULL DEFAULT 0',
            'Coordinates' => 'ALTER TABLE Masjids_AWS ADD COLUMN Coordinates VARCHAR(255) DEFAULT NULL',
        ];

        foreach ($requiredOptionalColumns as $column => $sql) {
            if (has_table_column($con, 'Masjids_AWS', $column)) {
                continue;
            }

            $ok = mysqli_query($con, $sql);
            if ($ok === false && !has_table_column($con, 'Masjids_AWS', $column)) {
                return [
                    'success' => false,
                    'column' => $column,
                    'error' => mysqli_error($con),
                    'errno' => mysqli_errno($con),
                    'sql' => $sql,
                ];
            }
        }

        return ['success' => true];
    }
}

$schemaEnsure = ensure_masjid_optional_columns($con);
if (empty($schemaEnsure['success'])) {
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Failed to prepare Masjids_AWS schema',
        'error' => $schemaEnsure['error'] ?? null,
        'errno' => $schemaEnsure['errno'] ?? null,
        'column' => $schemaEnsure['column'] ?? null,
        'schemaSql' => $schemaEnsure['sql'] ?? null,
    ]);
}

if (!function_exists('permission_to_level')) {
    function permission_to_level($permissionRaw)
    {
        $value = trim((string)$permissionRaw);
        if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
        if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
        if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
        if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
        if (is_numeric($value)) return intval($value);
        return 0;
    }
}

if (!function_exists('resolve_effective_owner_id')) {
    function resolve_effective_owner_id($con, $userId, $orgId, $permissionLevel)
    {
        if ($permissionLevel >= 3 || $orgId <= 0) {
            return intval($userId);
        }

        // 1) Prefer explicit parent link when the column exists.
        if (has_table_column($con, 'Login_user_AWS', 'parent_user_id')) {
            $parentStmt = mysqli_prepare(
                $con,
                "SELECT parent_user_id FROM Login_user_AWS WHERE id = ? LIMIT 1"
            );
            if ($parentStmt) {
                mysqli_stmt_bind_param($parentStmt, 'i', $userId);
                mysqli_stmt_execute($parentStmt);
                $parentId = null;
                mysqli_stmt_bind_result($parentStmt, $parentId);
                $foundParent = mysqli_stmt_fetch($parentStmt);
                mysqli_stmt_close($parentStmt);
                if ($foundParent && intval($parentId) > 0) {
                    return intval($parentId);
                }
            }
        }

        // 2) Prefer organization owner if organizations table is present.
        if (has_table_column($con, 'organizations', 'owner_user_id')) {
            $orgOwnerStmt = mysqli_prepare(
                $con,
                "SELECT owner_user_id FROM organizations WHERE id = ? LIMIT 1"
            );
            if ($orgOwnerStmt) {
                mysqli_stmt_bind_param($orgOwnerStmt, 'i', $orgId);
                mysqli_stmt_execute($orgOwnerStmt);
                $ownerUserId = null;
                mysqli_stmt_bind_result($orgOwnerStmt, $ownerUserId);
                $foundOwner = mysqli_stmt_fetch($orgOwnerStmt);
                mysqli_stmt_close($orgOwnerStmt);
                if ($foundOwner && intval($ownerUserId) > 0) {
                    return intval($ownerUserId);
                }
            }
        }

        $ownerStmt = mysqli_prepare(
            $con,
            "SELECT id
             FROM Login_user_AWS
             WHERE org_id = ?
               AND status = 'true'
               AND (org_role = 'org_admin' OR org_role = 'admin' OR Permissions = '3' OR Permissions = '4')
             ORDER BY
               CASE
                 WHEN org_role = 'org_admin' THEN 0
                 WHEN org_role = 'admin' THEN 1
                 ELSE 2
               END,
               id ASC
             LIMIT 1"
        );
        if (!$ownerStmt) return intval($userId);

        mysqli_stmt_bind_param($ownerStmt, 'i', $orgId);
        mysqli_stmt_execute($ownerStmt);
        $ownerId = null;
        mysqli_stmt_bind_result($ownerStmt, $ownerId);
        $found = mysqli_stmt_fetch($ownerStmt);
        mysqli_stmt_close($ownerStmt);

        return ($found && $ownerId) ? intval($ownerId) : intval($userId);
    }
}

if (!function_exists('find_inserted_masjid_id')) {
    function find_inserted_masjid_id($con, $name, $houseNo, $streetName, $city, $state, $zip, $createdBy)
    {
        $stmt = mysqli_prepare(
            $con,
            'SELECT ID FROM Masjids_AWS WHERE Name = ? AND H_No = ? AND St_Name = ? AND City = ? AND State = ? AND Zip = ? AND Created_by = ? ORDER BY ID DESC LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, 'ssssssi', $name, $houseNo, $streetName, $city, $state, $zip, $createdBy);
        mysqli_stmt_execute($stmt);
        $id = 0;
        mysqli_stmt_bind_result($stmt, $id);
        $found = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if ($found && $id) {
            return intval($id);
        }

        // Schema-alias fallback lookup for drifted databases.
        $idCol = first_existing_column($con, 'Masjids_AWS', ['ID', 'id']);
        $nameCol = first_existing_column($con, 'Masjids_AWS', ['Name', 'name', 'Masjid', 'Masjid_Name']);
        $houseCol = first_existing_column($con, 'Masjids_AWS', ['H_No', 'house_no', 'House_No']);
        $streetCol = first_existing_column($con, 'Masjids_AWS', ['St_Name', 'Street', 'Street_Name']);
        $cityCol = first_existing_column($con, 'Masjids_AWS', ['City', 'city']);
        $stateCol = first_existing_column($con, 'Masjids_AWS', ['State', 'state']);
        $zipCol = first_existing_column($con, 'Masjids_AWS', ['Zip', 'zip', 'ZIP', 'Zip_Code']);
        $ownerCol = first_existing_column($con, 'Masjids_AWS', ['Created_by', 'created_by']);

        if (!$idCol || !$nameCol || !$houseCol || !$streetCol || !$cityCol || !$stateCol || !$zipCol) {
            return 0;
        }

        $nameEsc = mysqli_real_escape_string($con, $name);
        $houseEsc = mysqli_real_escape_string($con, $houseNo);
        $streetEsc = mysqli_real_escape_string($con, $streetName);
        $cityEsc = mysqli_real_escape_string($con, $city);
        $stateEsc = mysqli_real_escape_string($con, $state);
        $zipEsc = mysqli_real_escape_string($con, $zip);

        $where = "`{$nameCol}` = '{$nameEsc}'"
            . " AND `{$houseCol}` = '{$houseEsc}'"
            . " AND `{$streetCol}` = '{$streetEsc}'"
            . " AND `{$cityCol}` = '{$cityEsc}'"
            . " AND `{$stateCol}` = '{$stateEsc}'"
            . " AND `{$zipCol}` = '{$zipEsc}'";

        if ($ownerCol) {
            $where .= " AND `{$ownerCol}` = " . intval($createdBy);
        }

        $sql = "SELECT `{$idCol}` FROM Masjids_AWS WHERE {$where} ORDER BY `{$idCol}` DESC LIMIT 1";
        $res = mysqli_query($con, $sql);
        if (!$res) return 0;
        $row = mysqli_fetch_row($res);
        mysqli_free_result($res);
        return ($row && isset($row[0])) ? intval($row[0]) : 0;
    }
}

if (!function_exists('collect_masjid_insert_diagnostics')) {
    function collect_masjid_insert_diagnostics($con, $name, $houseNo, $streetName, $city, $state, $zip, $createdBy)
    {
        $diag = [
            'dbName' => null,
            'dbUser' => null,
            'dbHostInfo' => mysqli_get_host_info($con),
            'createdByColumnExists' => has_table_column($con, 'Masjids_AWS', 'Created_by'),
            'looseMatchId' => 0,
            'strictMatchId' => 0,
            'tableCount' => null,
        ];

        $dbNameRes = mysqli_query($con, 'SELECT DATABASE()');
        if ($dbNameRes) {
            $row = mysqli_fetch_row($dbNameRes);
            if ($row && isset($row[0])) {
                $diag['dbName'] = $row[0];
            }
            mysqli_free_result($dbNameRes);
        }

        $dbUserRes = mysqli_query($con, 'SELECT CURRENT_USER()');
        if ($dbUserRes) {
            $row = mysqli_fetch_row($dbUserRes);
            if ($row && isset($row[0])) {
                $diag['dbUser'] = $row[0];
            }
            mysqli_free_result($dbUserRes);
        }

        $strictId = find_inserted_masjid_id($con, $name, $houseNo, $streetName, $city, $state, $zip, $createdBy);
        $diag['strictMatchId'] = intval($strictId);

        $looseStmt = mysqli_prepare(
            $con,
            'SELECT ID FROM Masjids_AWS WHERE Name = ? AND H_No = ? AND St_Name = ? AND City = ? AND State = ? AND Zip = ? ORDER BY ID DESC LIMIT 1'
        );
        if ($looseStmt) {
            mysqli_stmt_bind_param($looseStmt, 'ssssss', $name, $houseNo, $streetName, $city, $state, $zip);
            mysqli_stmt_execute($looseStmt);
            $looseId = 0;
            mysqli_stmt_bind_result($looseStmt, $looseId);
            $foundLoose = mysqli_stmt_fetch($looseStmt);
            mysqli_stmt_close($looseStmt);
            if ($foundLoose && $looseId) {
                $diag['looseMatchId'] = intval($looseId);
            }
        }

        $countRes = mysqli_query($con, 'SELECT COUNT(*) FROM Masjids_AWS');
        if ($countRes) {
            $countRow = mysqli_fetch_row($countRes);
            if ($countRow && isset($countRow[0])) {
                $diag['tableCount'] = intval($countRow[0]);
            }
            mysqli_free_result($countRes);
        }

        return $diag;
    }
}

if (!function_exists('collect_connection_identity')) {
    function collect_connection_identity($con)
    {
        $identity = [
            'dbName' => null,
            'dbUser' => null,
            'dbHostInfo' => mysqli_get_host_info($con),
            'serverName' => isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null,
            'requestHost' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null,
        ];

        $dbNameRes = mysqli_query($con, 'SELECT DATABASE()');
        if ($dbNameRes) {
            $row = mysqli_fetch_row($dbNameRes);
            if ($row && isset($row[0])) {
                $identity['dbName'] = $row[0];
            }
            mysqli_free_result($dbNameRes);
        }

        $dbUserRes = mysqli_query($con, 'SELECT CURRENT_USER()');
        if ($dbUserRes) {
            $row = mysqli_fetch_row($dbUserRes);
            if ($row && isset($row[0])) {
                $identity['dbUser'] = $row[0];
            }
            mysqli_free_result($dbUserRes);
        }

        return $identity;
    }
}

// Authenticate user (optional, adjust as needed)
$createdBy = null;
$submittedBy = null;
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
    $stmtU = mysqli_prepare($con, "SELECT id, org_id, Permissions FROM Login_user_AWS WHERE auth_token = ? AND status = 'true' LIMIT 1");
    if ($stmtU) {
        mysqli_stmt_bind_param($stmtU, 's', $token);
        mysqli_stmt_execute($stmtU);
        $tmpId = $tmpOrgId = null;
        $tmpPermissions = null;
        mysqli_stmt_bind_result($stmtU, $tmpId, $tmpOrgId, $tmpPermissions);
        $fetchedUserId = null;
        $fetchedOrgId = null;
        $fetchedPermissions = null;
        if (mysqli_stmt_fetch($stmtU)) {
            $fetchedUserId = $tmpId;
            $fetchedOrgId = $tmpOrgId;
            $fetchedPermissions = $tmpPermissions;
        }
        mysqli_stmt_close($stmtU); // Close BEFORE resolve_effective_owner_id to prevent "Commands out of sync"
        if ($fetchedUserId !== null) {
            $submittedBy = intval($fetchedUserId);
            $permissionLevel = permission_to_level($fetchedPermissions);
            $createdBy = resolve_effective_owner_id($con, intval($fetchedUserId), intval($fetchedOrgId), $permissionLevel);
        }
    }
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$debugValues = null;
$debugQuery = null;
$isLocalRequest =
    (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1')) ||
    (isset($_SERVER['REMOTE_ADDR']) && ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1'));

$debugInResponse =
    (isset($_GET['debug']) && $_GET['debug'] === '1') ||
    (isset($input['debug']) && strval($input['debug']) === '1') ||
    (isset($_SERVER['HTTP_X_DEBUG']) && $_SERVER['HTTP_X_DEBUG'] === '1') ||
    $isLocalRequest;


$name = isset($input['name']) ? trim($input['name']) : '';
$houseNo = isset($input['houseNo']) ? trim($input['houseNo']) : '';
$aptNo = isset($input['aptNo']) ? trim($input['aptNo']) : '';
$streetName = isset($input['streetName']) ? trim($input['streetName']) : '';
$city = isset($input['city']) ? trim($input['city']) : '';
$state = isset($input['state']) ? trim($input['state']) : '';
$zip = isset($input['zip']) ? trim($input['zip']) : '';

if ($name === '' || $houseNo === '' || $streetName === '' || $city === '' || $state === '' || $zip === '') {
    sendJsonResponse(400, ['success' => false, 'message' => 'Name, houseNo, streetName, city, state, and zip are required'], $debugInResponse, $debugValues, $debugQuery);
}

$fullAddress = trim(implode(', ', array_filter([$houseNo, $streetName, $city, $state, $zip], function ($v) {
    return $v !== '';
})));
$geo = resolveCoordinatesForAddress($fullAddress);
$geocodeSuccess = !empty($geo['success']);
$latitude = $geocodeSuccess ? ($geo['lat'] ?? null) : null;
$longitude = $geocodeSuccess ? ($geo['lng'] ?? null) : null;
$coordinates = $geocodeSuccess ? ($geo['coordinates'] ?? '') : '';
$geocodeProvider = $geocodeSuccess ? 'google' : null;
$geocodeStatus = $geo['status'] ?? null;
$geocodeReason = $geo['reason'] ?? null;
$geocodeError = $geo['errorMessage'] ?? null;
$geocodeApiKeySource = $geo['apiKeySource'] ?? null;

if (!$geocodeSuccess) {
    sendJsonResponse(502, [
        'success' => false,
        'message' => 'Google geocoding failed. Configure a server-side Google Geocoding API key (no HTTP referrer restriction).',
        'fullAddress' => $fullAddress,
        'geocodeStatus' => $geocodeStatus,
        'geocodeReason' => $geocodeReason,
        'geocodeError' => $geocodeError,
        'geocodeApiKeySource' => $geocodeApiKeySource,
    ], $debugInResponse, $debugValues, $debugQuery);
}

if ($createdBy === null) {
    sendJsonResponse(401, ['success' => false, 'message' => 'Unauthorized'], $debugInResponse, $debugValues, $debugQuery);
}

if ($submittedBy === null) {
    $submittedBy = intval($createdBy);
}

// Debug: log concrete runtime values and a rendered SQL string.
$debugValues = [
    'name' => $name,
    'houseNo' => $houseNo,
    'aptNo' => $aptNo,
    'streetName' => $streetName,
    'city' => $city,
    'state' => $state,
    'zip' => $zip,
    'createdBy' => $createdBy,
    'submittedBy' => $submittedBy,
    'coordinates' => $coordinates,
    'geocodeSuccess' => $geocodeSuccess,
    'geocodeProvider' => $geocodeProvider,
    'geocodeStatus' => $geocodeStatus,
    'geocodeReason' => $geocodeReason,
    'geocodeError' => $geocodeError,
    'geocodeApiKeySource' => $geocodeApiKeySource,
];
error_log('Masjid Create Values: ' . json_encode($debugValues));

$debugQuery = sprintf(
    "INSERT INTO Masjids_AWS (Name, H_No, Apt_No, St_Name, City, State, Zip, Created_by, Submitted_by, `Clear`, Coordinates) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, 0, '%s')",
    mysqli_real_escape_string($con, $name),
    mysqli_real_escape_string($con, $houseNo),
    mysqli_real_escape_string($con, $aptNo),
    mysqli_real_escape_string($con, $streetName),
    mysqli_real_escape_string($con, $city),
    mysqli_real_escape_string($con, $state),
    mysqli_real_escape_string($con, $zip),
    intval($createdBy),
    intval($submittedBy),
    mysqli_real_escape_string($con, $coordinates)
);
error_log('Insert Query: ' . $debugQuery);

// Check for duplicate
$checkStmt = mysqli_prepare($con, 'SELECT ID FROM Masjids_AWS WHERE Name = ? AND H_No = ? AND Created_by = ? LIMIT 1');
$exists = false;
if ($checkStmt) {
    mysqli_stmt_bind_param($checkStmt, 'ssi', $name, $houseNo, $createdBy);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_bind_result($checkStmt, $existingId);
    $exists = mysqli_stmt_fetch($checkStmt);
    mysqli_stmt_close($checkStmt);
} else {
    $nameEsc = mysqli_real_escape_string($con, $name);
    $houseEsc = mysqli_real_escape_string($con, $houseNo);
    $ownerId = intval($createdBy);
    $dupSql = "SELECT ID FROM Masjids_AWS WHERE Name = '{$nameEsc}' AND H_No = '{$houseEsc}' AND Created_by = {$ownerId} LIMIT 1";
    $dupRes = mysqli_query($con, $dupSql);
    if ($dupRes === false) {
        error_log('Masjid duplicate check failed; continuing with insert fallback. Error: ' . mysqli_error($con));
        $exists = false;
    } else {
        $exists = mysqli_num_rows($dupRes) > 0;
        mysqli_free_result($dupRes);
    }
}

if ($exists) {
    sendJsonResponse(409, ['success' => false, 'message' => 'This masjid already exists for your account'], $debugInResponse, $debugValues, $debugQuery);
}

// Use correct column name: Coordinates
$stmt = mysqli_prepare(
    $con,
    'INSERT INTO Masjids_AWS (Name, H_No, Apt_No, St_Name, City, State, Zip, Created_by, Submitted_by, `Clear`, Coordinates) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)'
);
$newId = 0;
$primaryInsertError = null;
$primaryInsertSqlState = null;
if ($stmt) {
    if (!mysqli_stmt_bind_param($stmt, 'sssssssiis', $name, $houseNo, $aptNo, $streetName, $city, $state, $zip, $createdBy, $submittedBy, $coordinates)) {
        $bindErr = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        sendJsonResponse(500, [
            'success' => false,
            'message' => 'Failed to bind insert parameters',
            'error' => $bindErr,
        ], $debugInResponse, $debugValues, $debugQuery);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        $sqlState = mysqli_stmt_sqlstate($stmt);
        mysqli_stmt_close($stmt);
        $primaryInsertError = $err;
        $primaryInsertSqlState = $sqlState;
    } else {
        $newId = mysqli_insert_id($con);
        if ($newId <= 0) {
            $newId = find_inserted_masjid_id($con, $name, $houseNo, $streetName, $city, $state, $zip, intval($createdBy));
        }
        mysqli_stmt_close($stmt);
        if ($newId <= 0) {
            $diag = collect_masjid_insert_diagnostics($con, $name, $houseNo, $streetName, $city, $state, $zip, intval($createdBy));
            sendJsonResponse(500, [
                'success' => false,
                'message' => 'Insert executed but could not verify created masjid row',
                'createdBy' => intval($createdBy),
                'insertId' => intval(mysqli_insert_id($con)),
                'affectedRows' => intval(mysqli_affected_rows($con)),
                'diagnostics' => $diag,
            ], $debugInResponse, $debugValues, $debugQuery);
        }
        sendJsonResponse(200, [
            'success' => true,
            'message' => 'Masjid created successfully',
            'id' => $newId,
            'fullAddress' => $fullAddress,
            'coordinates' => $coordinates,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geocodeSuccess' => $geocodeSuccess,
            'geocodeProvider' => $geocodeProvider,
            'geocodeStatus' => $geocodeStatus,
            'geocodeReason' => $geocodeReason,
            'geocodeError' => $geocodeError,
            'geocodeApiKeySource' => $geocodeApiKeySource,
            'insertQuery' => $debugQuery,
        ], $debugInResponse, $debugValues, $debugQuery);
    }
}

// Fallback path: dynamic column insert for schema-drifted servers.
if (!$stmt || $primaryInsertError !== null) {
    $nameEsc = mysqli_real_escape_string($con, $name);
    $houseEsc = mysqli_real_escape_string($con, $houseNo);
    $aptEsc = mysqli_real_escape_string($con, $aptNo);
    $streetEsc = mysqli_real_escape_string($con, $streetName);
    $cityEsc = mysqli_real_escape_string($con, $city);
    $stateEsc = mysqli_real_escape_string($con, $state);
    $zipEsc = mysqli_real_escape_string($con, $zip);
    $coordEsc = mysqli_real_escape_string($con, $coordinates);
    $ownerId = intval($createdBy);

    $nameCol = first_existing_column($con, 'Masjids_AWS', ['Name', 'name', 'Masjid', 'Masjid_Name']);
    $houseCol = first_existing_column($con, 'Masjids_AWS', ['H_No', 'house_no', 'House_No']);
    $streetCol = first_existing_column($con, 'Masjids_AWS', ['St_Name', 'Street', 'Street_Name']);
    $cityCol = first_existing_column($con, 'Masjids_AWS', ['City', 'city']);
    $stateCol = first_existing_column($con, 'Masjids_AWS', ['State', 'state']);
    $zipCol = first_existing_column($con, 'Masjids_AWS', ['Zip', 'zip', 'ZIP', 'Zip_Code']);
    $aptCol = first_existing_column($con, 'Masjids_AWS', ['Apt_No', 'apt_no', 'Apartment']);
    $ownerCol = first_existing_column($con, 'Masjids_AWS', ['Created_by', 'created_by']);
    $submittedByCol = first_existing_column($con, 'Masjids_AWS', ['Submitted_by', 'submitted_by']);
    $clearCol = first_existing_column($con, 'Masjids_AWS', ['Clear', '`Clear`', 'clear']);
    $coordCol = first_existing_column($con, 'Masjids_AWS', ['Coordinates', 'coordinates']);

    $missingRequired = [];
    if (!$nameCol) $missingRequired[] = 'name';
    if (!$houseCol) $missingRequired[] = 'houseNo';
    if (!$streetCol) $missingRequired[] = 'streetName';
    if (!$cityCol) $missingRequired[] = 'city';
    if (!$stateCol) $missingRequired[] = 'state';
    if (!$zipCol) $missingRequired[] = 'zip';

    if (!empty($missingRequired)) {
        $connIdentity = collect_connection_identity($con);
        sendJsonResponse(500, [
            'success' => false,
            'message' => 'Masjids_AWS schema is incompatible for required fields',
            'missingRequiredFields' => $missingRequired,
            'tableColumns' => get_table_columns($con, 'Masjids_AWS'),
            'createdBy' => $ownerId,
            'primaryInsertError' => $primaryInsertError,
            'primaryInsertSqlState' => $primaryInsertSqlState,
            'connectionIdentity' => $connIdentity,
        ], $debugInResponse, $debugValues, $debugQuery);
    }

    $dynamicColumns = [];
    $dynamicValues = [];

    if ($nameCol) { $dynamicColumns[] = "`{$nameCol}`"; $dynamicValues[] = "'{$nameEsc}'"; }
    if ($houseCol) { $dynamicColumns[] = "`{$houseCol}`"; $dynamicValues[] = "'{$houseEsc}'"; }
    if ($aptCol) { $dynamicColumns[] = "`{$aptCol}`"; $dynamicValues[] = "'{$aptEsc}'"; }
    if ($streetCol) { $dynamicColumns[] = "`{$streetCol}`"; $dynamicValues[] = "'{$streetEsc}'"; }
    if ($cityCol) { $dynamicColumns[] = "`{$cityCol}`"; $dynamicValues[] = "'{$cityEsc}'"; }
    if ($stateCol) { $dynamicColumns[] = "`{$stateCol}`"; $dynamicValues[] = "'{$stateEsc}'"; }
    if ($zipCol) { $dynamicColumns[] = "`{$zipCol}`"; $dynamicValues[] = "'{$zipEsc}'"; }
    if ($ownerCol) { $dynamicColumns[] = "`{$ownerCol}`"; $dynamicValues[] = (string)$ownerId; }
    if ($submittedByCol) { $dynamicColumns[] = "`{$submittedByCol}`"; $dynamicValues[] = (string)intval($submittedBy); }
    if ($clearCol) { $dynamicColumns[] = "`{$clearCol}`"; $dynamicValues[] = '0'; }
    if ($coordCol) { $dynamicColumns[] = "`{$coordCol}`"; $dynamicValues[] = "'{$coordEsc}'"; }

    if (count($dynamicColumns) === 0) {
        sendJsonResponse(500, [
            'success' => false,
            'message' => 'Masjids_AWS has no compatible insert columns',
            'tableColumns' => get_table_columns($con, 'Masjids_AWS'),
            'primaryInsertError' => $primaryInsertError,
            'primaryInsertSqlState' => $primaryInsertSqlState,
        ], $debugInResponse, $debugValues, $debugQuery);
    }

    $insertSql = "INSERT INTO Masjids_AWS (" . implode(', ', $dynamicColumns) . ") VALUES (" . implode(', ', $dynamicValues) . ")";
    $ok = mysqli_query($con, $insertSql);
    if ($ok === false) {
        sendJsonResponse(500, [
            'success' => false,
            'message' => 'Failed to create masjid (insert fallback)',
            'error' => mysqli_error($con),
            'errno' => mysqli_errno($con),
            'insertSql' => $insertSql,
            'createdBy' => $ownerId,
            'primaryInsertError' => $primaryInsertError,
            'primaryInsertSqlState' => $primaryInsertSqlState,
        ], $debugInResponse, $debugValues, $debugQuery);
    }
    $newId = mysqli_insert_id($con);
    if ($newId <= 0) {
        $newId = find_inserted_masjid_id($con, $name, $houseNo, $streetName, $city, $state, $zip, intval($createdBy));
    }
}

if ($newId <= 0) {
    $diag = collect_masjid_insert_diagnostics($con, $name, $houseNo, $streetName, $city, $state, $zip, intval($createdBy));
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Masjid insert returned success but row was not found in Masjids_AWS',
        'createdBy' => intval($createdBy),
        'insertId' => intval(mysqli_insert_id($con)),
        'affectedRows' => intval(mysqli_affected_rows($con)),
        'diagnostics' => $diag,
    ], $debugInResponse, $debugValues, $debugQuery);
}

sendJsonResponse(200, [
    'success' => true,
    'message' => 'Masjid created successfully',
    'id' => $newId,
    'fullAddress' => $fullAddress,
    'coordinates' => $coordinates,
    'latitude' => $latitude,
    'longitude' => $longitude,
    'geocodeSuccess' => $geocodeSuccess,
    'geocodeProvider' => $geocodeProvider,
    'geocodeStatus' => $geocodeStatus,
    'geocodeReason' => $geocodeReason,
    'geocodeError' => $geocodeError,
    'geocodeApiKeySource' => $geocodeApiKeySource,
    'insertQuery' => $debugQuery,
], $debugInResponse, $debugValues, $debugQuery);
