<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Address-Create-Version: 2026-05-04-schema-fallback-v3');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    echo json_encode(array('success' => false, 'message' => 'CORS preflight'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit;
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

if (!function_exists('ensure_address_optional_columns')) {
    function ensure_address_optional_columns($con)
    {
        $requiredOptionalColumns = [
            'uploaded_by' => 'ALTER TABLE Addresses_AWS ADD COLUMN uploaded_by INT DEFAULT NULL',
            'Clear' => 'ALTER TABLE Addresses_AWS ADD COLUMN `Clear` TINYINT(1) NOT NULL DEFAULT 0',
            'Coordinates' => 'ALTER TABLE Addresses_AWS ADD COLUMN Coordinates VARCHAR(255) DEFAULT NULL',
            'Locality' => 'ALTER TABLE Addresses_AWS ADD COLUMN Locality VARCHAR(255) DEFAULT NULL',
            'Last_Visit' => 'ALTER TABLE Addresses_AWS ADD COLUMN Last_Visit DATE DEFAULT NULL',
        ];

        foreach ($requiredOptionalColumns as $column => $sql) {
            if (has_table_column($con, 'Addresses_AWS', $column)) {
                continue;
            }

            $ok = mysqli_query($con, $sql);
            if ($ok === false && !has_table_column($con, 'Addresses_AWS', $column)) {
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

$schemaEnsure = ensure_address_optional_columns($con);
if (empty($schemaEnsure['success'])) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Failed to prepare Addresses_AWS schema',
        'error' => $schemaEnsure['error'] ?? null,
        'errno' => $schemaEnsure['errno'] ?? null,
        'column' => $schemaEnsure['column'] ?? null,
        'schemaSql' => $schemaEnsure['sql'] ?? null,
    ));
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

function resolve_effective_owner_id($con, $userId, $orgId, $permissionLevel) {
        if ($permissionLevel >= 3 || $orgId <= 0) {
                return intval($userId);
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


// Resolve uploaded_by from Bearer token.
$uploadedBy = null;
$uploadedByOrgId = 0;
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
        if (mysqli_stmt_fetch($stmtU)) {
            $permissionLevel = permission_to_level($tmpPermissions);
            $uploadedBy = resolve_effective_owner_id($con, intval($tmpId), intval($tmpOrgId), $permissionLevel);
            $uploadedByOrgId = intval($tmpOrgId);
        }
        mysqli_stmt_close($stmtU);
    }
}

if ($uploadedBy === null) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Unauthorized'));
    exit;
}


$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}


$name = isset($input['name']) ? trim($input['name']) : '';
$halaqa = isset($input['halaqa']) ? trim($input['halaqa']) : 'Atlanta East';
$houseNo = isset($input['houseNo']) ? trim($input['houseNo']) : '';
$aptNo = isset($input['aptNo']) ? trim($input['aptNo']) : '';
$streetName = isset($input['streetName']) ? trim($input['streetName']) : '';
$city = isset($input['city']) ? trim($input['city']) : '';
$state = isset($input['state']) ? trim($input['state']) : '';
$zip = isset($input['zip']) ? trim($input['zip']) : '';
$locality = isset($input['locality']) ? trim($input['locality']) : '';
$verified = isset($input['verified']) ? trim($input['verified']) : 'N';
$masjid = isset($input['masjid']) ? trim($input['masjid']) : '';
$lastVisit = isset($input['lastVisit']) ? trim($input['lastVisit']) : date('Y-m-d');
$comments = isset($input['comments']) ? trim($input['comments']) : '';
$latitude = isset($input['latitude']) ? trim((string)$input['latitude']) : '';
$longitude = isset($input['longitude']) ? trim((string)$input['longitude']) : '';

$hasCoordinates = ($latitude !== '' && $longitude !== '');

if ($name === '') {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Name is required'));
    exit;
}

if (!$hasCoordinates && ($houseNo === '' || $streetName === '' || $city === '' || $state === '' || $zip === '' || $locality === '')) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Provide full address fields, or include coordinates from current location'));
    exit;
}

if ($masjid === '') {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Masjid is required'));
    exit;
}

if ($hasCoordinates) {
    if ($houseNo === '') $houseNo = 'GPS';
    if ($streetName === '') $streetName = 'Current Location';
    if ($city === '') $city = 'Unknown';
    if ($state === '') $state = 'GA';
    if ($zip === '') $zip = '00000';
    if ($locality === '') $locality = 'Unassigned';
}

$masjidStmt = mysqli_prepare($con, 'SELECT m.ID
    FROM Masjids_AWS m
    INNER JOIN Login_user_AWS owner ON owner.id = m.Created_by
    WHERE m.Name = ?
      AND COALESCE(m.`Clear`, 1) = 1
      AND (m.Created_by = ? OR (? > 0 AND owner.org_id = ?))
    LIMIT 1');
if (!$masjidStmt) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Failed to validate masjid', 'error' => mysqli_error($con)));
    exit;
}
mysqli_stmt_bind_param($masjidStmt, 'siii', $masjid, $uploadedBy, $uploadedByOrgId, $uploadedByOrgId);
mysqli_stmt_execute($masjidStmt);
mysqli_stmt_bind_result($masjidStmt, $masjidId);
$hasApprovedMasjid = mysqli_stmt_fetch($masjidStmt);
mysqli_stmt_close($masjidStmt);

if (!$hasApprovedMasjid) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Select one of your approved masjids before adding an address'));
    exit;
}


$checkStmt = mysqli_prepare($con, 'SELECT ID FROM Addresses_AWS WHERE Name = ? AND H_No = ? LIMIT 1');
$exists = false;
if ($checkStmt) {
    mysqli_stmt_bind_param($checkStmt, 'ss', $name, $houseNo);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_bind_result($checkStmt, $existingId);
    $exists = mysqli_stmt_fetch($checkStmt);
    mysqli_stmt_close($checkStmt);
} else {
    $nameEsc = mysqli_real_escape_string($con, $name);
    $houseEsc = mysqli_real_escape_string($con, $houseNo);
    $dupSql = "SELECT ID FROM Addresses_AWS WHERE Name = '{$nameEsc}' AND H_No = '{$houseEsc}' LIMIT 1";
    $dupRes = mysqli_query($con, $dupSql);
    if ($dupRes === false) {
        error_log('Address duplicate check failed; continuing with insert fallback. Error: ' . mysqli_error($con));
        $exists = false;
    } else {
        $exists = mysqli_num_rows($dupRes) > 0;
        mysqli_free_result($dupRes);
    }
}

if ($exists) {
    http_response_code(409);
    echo json_encode(array('success' => false, 'message' => 'The address with this name and house number already exists'));
    exit;
}

$coordinates = '';
if ($latitude !== '' && $longitude !== '') {
    $coordinates = $latitude . ',' . $longitude;
}

$area = 'unclassified';
$status = 'Muslim';
$clear = 0;


$stmt = mysqli_prepare(
    $con,
    'INSERT INTO Addresses_AWS (Name, Halaqa, H_No, Apt_No, St_Name, City, State, Zip, Verified, Masjid, Comments, Last_Visit, Coordinates, Locality, Area, Status, `Clear`, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if ($stmt) {
    mysqli_stmt_bind_param(
        $stmt,
        'ssssssssssssssssii',
        $name,
        $halaqa,
        $houseNo,
        $aptNo,
        $streetName,
        $city,
        $state,
        $zip,
        $verified,
        $masjid,
        $comments,
        $lastVisit,
        $coordinates,
        $locality,
        $area,
        $status,
        $clear,
        $uploadedBy
    );

    if (!mysqli_stmt_execute($stmt)) {
        $errorMsg = mysqli_error($con);
        mysqli_stmt_close($stmt);
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Failed to create address', 'error' => $errorMsg));
        exit;
    }

    mysqli_stmt_close($stmt);
    echo json_encode(array('success' => true, 'message' => 'Address created successfully'));
    exit;
}

$nameEsc = mysqli_real_escape_string($con, $name);
$halaqaEsc = mysqli_real_escape_string($con, $halaqa);
$houseEsc = mysqli_real_escape_string($con, $houseNo);
$aptEsc = mysqli_real_escape_string($con, $aptNo);
$streetEsc = mysqli_real_escape_string($con, $streetName);
$cityEsc = mysqli_real_escape_string($con, $city);
$stateEsc = mysqli_real_escape_string($con, $state);
$zipEsc = mysqli_real_escape_string($con, $zip);
$verifiedEsc = mysqli_real_escape_string($con, $verified);
$masjidEsc = mysqli_real_escape_string($con, $masjid);
$commentsEsc = mysqli_real_escape_string($con, $comments);
$lastVisitEsc = mysqli_real_escape_string($con, $lastVisit);
$coordinatesEsc = mysqli_real_escape_string($con, $coordinates);
$localityEsc = mysqli_real_escape_string($con, $locality);
$areaEsc = mysqli_real_escape_string($con, $area);
$statusEsc = mysqli_real_escape_string($con, $status);
$clearInt = intval($clear);
$uploadedByInt = intval($uploadedBy);

$insertSql = "INSERT INTO Addresses_AWS (Name, Halaqa, H_No, Apt_No, St_Name, City, State, Zip, Verified, Masjid, Comments, Last_Visit, Coordinates, Locality, Area, Status, `Clear`, uploaded_by) VALUES ('{$nameEsc}', '{$halaqaEsc}', '{$houseEsc}', '{$aptEsc}', '{$streetEsc}', '{$cityEsc}', '{$stateEsc}', '{$zipEsc}', '{$verifiedEsc}', '{$masjidEsc}', '{$commentsEsc}', '{$lastVisitEsc}', '{$coordinatesEsc}', '{$localityEsc}', '{$areaEsc}', '{$statusEsc}', {$clearInt}, {$uploadedByInt})";
$ok = mysqli_query($con, $insertSql);
if ($ok === false) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Failed to create address (insert fallback)',
        'error' => mysqli_error($con),
        'errno' => mysqli_errno($con),
        'insertSql' => $insertSql,
        'uploadedBy' => $uploadedByInt,
    ));
    exit;
}

echo json_encode(array('success' => true, 'message' => 'Address created successfully'));
exit;
