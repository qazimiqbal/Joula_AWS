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

function enforce_address_access($con, $permissionLevel, $orgId, $isFreeUser = false) {
    if ($permissionLevel < 2) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Only admins and editors can add addresses'));
        exit;
    }

    if ($permissionLevel >= 4) {
        return;
    }

    // Free users created by super admin bypass subscription checks
    if ($isFreeUser) {
        return;
    }

    if ($orgId <= 0) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Organization subscription is required'));
        exit;
    }

    $safeOrgId = intval($orgId);
    $subResult = mysqli_query($con, "SELECT plan_status, trial_ends_at FROM organizations WHERE id = $safeOrgId LIMIT 1");
    if (!$subResult) {
        // Cannot verify subscription — allow access
        return;
    }
    $subRow = mysqli_fetch_assoc($subResult);
    mysqli_free_result($subResult);

    if (!$subRow) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Organization subscription is required'));
        exit;
    }

    $planStatus  = $subRow['plan_status'];
    $trialEndsAt = $subRow['trial_ends_at'];
    $normalized  = strtolower(trim((string)$planStatus));

    if ($normalized === 'trial') {
        try {
            $now      = new DateTime('now', new DateTimeZone('UTC'));
            $trialEnd = new DateTime((string)$trialEndsAt, new DateTimeZone('UTC'));
            if ($now > $trialEnd) {
                mysqli_query($con, "UPDATE organizations SET plan_status = 'expired' WHERE id = $safeOrgId");
                $normalized = 'expired';
            }
        } catch (Exception $e) {
            $normalized = 'expired';
        }
    }

    if ($normalized !== 'active' && $normalized !== 'trial') {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Active subscription required to add addresses'));
        exit;
    }
}

function resolve_effective_owner_id($con, $userId, $orgId, $permissionLevel) {
        if ($permissionLevel >= 3 || $orgId <= 0) {
                return intval($userId);
        }

        $safeOrg = intval($orgId);
        $ownerRes = mysqli_query(
                $con,
                "SELECT id
                 FROM Login_user_AWS
                 WHERE org_id = $safeOrg
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
        if (!$ownerRes) return intval($userId);
        $ownerRow = mysqli_fetch_assoc($ownerRes);
        mysqli_free_result($ownerRes);

        return ($ownerRow && $ownerRow['id']) ? intval($ownerRow['id']) : intval($userId);
}


// Resolve uploaded_by from Bearer token.
$uploadedBy = null;
$uploadedByOrgId = 0;
$permissionLevel = 0;
$isFreeUser = false;
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
    $tokenEsc = mysqli_real_escape_string($con, $token);
    // Check if is_free_user column exists
    $freeColCheck = mysqli_query($con, "SHOW COLUMNS FROM Login_user_AWS LIKE 'is_free_user'");
    $hasFreeCol = ($freeColCheck && mysqli_num_rows($freeColCheck) > 0);
    if ($freeColCheck) mysqli_free_result($freeColCheck);
    $selectFree = $hasFreeCol ? ', is_free_user' : '';
    $resU = mysqli_query($con, "SELECT id, org_id, Permissions{$selectFree} FROM Login_user_AWS WHERE auth_token = '{$tokenEsc}' AND status = 'true' LIMIT 1");
    if ($resU) {
        $rowU = mysqli_fetch_assoc($resU);
        mysqli_free_result($resU);
        if ($rowU) {
            $permissionLevel = permission_to_level($rowU['Permissions']);
            $isFreeUser = $hasFreeCol && !empty($rowU['is_free_user']);
            $uploadedBy = resolve_effective_owner_id($con, intval($rowU['id']), intval($rowU['org_id']), $permissionLevel);
            $uploadedByOrgId = intval($rowU['org_id']);
        }
    }
}

if ($uploadedBy === null) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Unauthorized'));
    exit;
}

enforce_address_access($con, $permissionLevel, $uploadedByOrgId, $isFreeUser);


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
$coordinatesInput = isset($input['coordinates']) ? trim((string)$input['coordinates']) : '';
$latitude = isset($input['latitude']) ? trim((string)$input['latitude']) : '';
$longitude = isset($input['longitude']) ? trim((string)$input['longitude']) : '';

if ($locality === '') {
    $locality = 'Unassigned';
}

// Only strip coordinates if user cannot see/enter them (permission < 2)
if ($permissionLevel < 2) {
    $coordinatesInput = '';
    $latitude = '';
    $longitude = '';
}

$hasCoordinates = ($coordinatesInput !== '' || ($latitude !== '' && $longitude !== ''));

if ($name === '') {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Name is required'));
    exit;
}

if (!$hasCoordinates && ($houseNo === '' || $streetName === '' || $city === '' || $state === '' || $zip === '')) {
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
}

// Super admin can use any approved masjid; others are restricted to their own/org masjids
$masjidEscaped = mysqli_real_escape_string($con, $masjid);
$safeUploadedBy = intval($uploadedBy);
$safeOrgId2 = intval($uploadedByOrgId);

if ($permissionLevel >= 4) {
    $masjidSql = "SELECT ID FROM Masjids_AWS WHERE Name = '{$masjidEscaped}' AND COALESCE(`Clear`, 1) = 1 LIMIT 1";
} else {
    $masjidSql = "SELECT m.ID
        FROM Masjids_AWS m
        INNER JOIN Login_user_AWS owner ON owner.id = m.Created_by
        WHERE m.Name = '{$masjidEscaped}'
          AND COALESCE(m.`Clear`, 1) = 1
          AND (m.Created_by = $safeUploadedBy OR ($safeOrgId2 > 0 AND owner.org_id = $safeOrgId2))
        LIMIT 1";
}
$masjidResult = mysqli_query($con, $masjidSql);
$hasApprovedMasjid = false;
$masjidId = null;
if ($masjidResult) {
    $masjidRow = mysqli_fetch_assoc($masjidResult);
    if ($masjidRow) {
        $hasApprovedMasjid = true;
        $masjidId = $masjidRow['ID'];
    }
    mysqli_free_result($masjidResult);
}

if (!$hasApprovedMasjid) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Select one of your approved masjids before adding an address'));
    exit;
}


$nameEscDup   = mysqli_real_escape_string($con, $name);
$houseEscDup  = mysqli_real_escape_string($con, $houseNo);
$dupRes = mysqli_query($con, "SELECT ID FROM Addresses_AWS WHERE Name = '{$nameEscDup}' AND H_No = '{$houseEscDup}' LIMIT 1");
$exists = false;
if ($dupRes !== false) {
    $exists = mysqli_num_rows($dupRes) > 0;
    mysqli_free_result($dupRes);
}

if ($exists) {
    http_response_code(409);
    echo json_encode(array('success' => false, 'message' => 'The address with this name and house number already exists'));
    exit;
}

$coordinates = '';
if ($coordinatesInput !== '') {
    $coordinates = $coordinatesInput;
} elseif ($latitude !== '' && $longitude !== '') {
    $coordinates = $latitude . ',' . $longitude;
}

$clear = 0;


// Use plain mysqli_query with escaped values (avoids GoDaddy prepared-statement issues)

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
$clearInt = intval($clear);
$uploadedByInt = intval($uploadedBy);

$insertSql = "INSERT INTO Addresses_AWS (Name, Halaqa, H_No, Apt_No, St_Name, City, State, Zip, Verified, Masjid, Comments, Last_Visit, Coordinates, Locality, `Clear`, uploaded_by) VALUES ('{$nameEsc}', '{$halaqaEsc}', '{$houseEsc}', '{$aptEsc}', '{$streetEsc}', '{$cityEsc}', '{$stateEsc}', '{$zipEsc}', '{$verifiedEsc}', '{$masjidEsc}', '{$commentsEsc}', '{$lastVisitEsc}', '{$coordinatesEsc}', '{$localityEsc}', {$clearInt}, {$uploadedByInt})";
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
