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

require_once 'db.pgsql.php';

// Skipping ensure_address_optional_columns for Postgres. Assume schema is correct.

function permission_to_level($permissionRaw) {
        $value = trim((string)$permissionRaw);
        if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
        if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
        if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
        if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
        if (is_numeric($value)) return intval($value);
        return 0;
}

function enforce_address_access($pdo, $permissionLevel, $orgId, $isFreeUser = false) {
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
    $stmt = $pdo->prepare('SELECT plan_status, trial_ends_at FROM organizations WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $safeOrgId]);
    $subRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subRow) {
        // Cannot verify subscription — allow access
        return;
    }

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
                $pdo->prepare('UPDATE organizations SET plan_status = :status WHERE id = :id')->execute([':status' => 'expired', ':id' => $safeOrgId]);
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

function resolve_effective_owner_id($pdo, $userId, $orgId, $permissionLevel) {
    if ($permissionLevel >= 3 || $orgId <= 0) {
        return intval($userId);
    }
    $sql = 'SELECT "id" FROM "Login_user_AWS" WHERE "org_id" = :orgId AND "status" = :status AND ("org_role" = :orgAdmin OR "org_role" = :admin) ORDER BY CASE WHEN "org_role" = :orgAdmin THEN 0 WHEN "org_role" = :admin THEN 1 ELSE 2 END, "id" ASC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':orgId' => $orgId,
        ':status' => 'true',
        ':orgAdmin' => 'org_admin',
        ':admin' => 'admin',
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($row && isset($row['id'])) ? intval($row['id']) : intval($userId);
}

// Resolve uploaded_by from Bearer token using PDO
$uploadedBy = null;
$uploadedByOrgId = 0;
$permissionLevel = 0;
$isFreeUser = false;
$authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (strpos($authHeader, 'Bearer ') === 0) {
    $token = substr($authHeader, 7);
    // Check if is_free_user column exists (assume true for Postgres)
    $sql = 'SELECT "id", "org_id", permissions AS "Permissions", "is_free_user" FROM "Login_user_AWS" WHERE "auth_token" = :token AND "status" = :status LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $rowU = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($rowU) {
        $permissionLevel = permission_to_level($rowU['Permissions']);
        $isFreeUser = !empty($rowU['is_free_user']);
        $uploadedBy = resolve_effective_owner_id($pdo, intval($rowU['id']), intval($rowU['org_id']), $permissionLevel);
        $uploadedByOrgId = intval($rowU['org_id']);
    }
}
if ($uploadedBy === null) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Unauthorized'));
    exit;
}
enforce_address_access($pdo, $permissionLevel, $uploadedByOrgId, $isFreeUser);


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
$status = isset($input['status']) ? trim((string)$input['status']) : '';
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
$hasApprovedMasjid = false;
$masjidId = null;
if ($permissionLevel >= 4) {
    $masjidSql = 'SELECT "ID" FROM "Masjids_AWS" WHERE "Name" = :name AND COALESCE("Clear", 1) = 1 LIMIT 1';
    $stmt = $pdo->prepare($masjidSql);
    $stmt->execute([':name' => $masjid]);
} else {
    $masjidSql = 'SELECT m."ID" FROM "Masjids_AWS" m INNER JOIN "Login_user_AWS" owner ON owner."id" = m."Created_by" WHERE m."Name" = :name AND COALESCE(m."Clear", 1) = 1 AND (m."Created_by" = :createdBy OR (:orgId > 0 AND owner."org_id" = :orgId)) LIMIT 1';
    $stmt = $pdo->prepare($masjidSql);
    $stmt->execute([':name' => $masjid, ':createdBy' => $uploadedBy, ':orgId' => $uploadedByOrgId]);
}
$masjidRow = $stmt->fetch(PDO::FETCH_ASSOC);
if ($masjidRow) {
    $hasApprovedMasjid = true;
    $masjidId = $masjidRow['ID'];
}
if (!$hasApprovedMasjid) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Select one of your approved masjids before adding an address'));
    exit;
}


// Check for duplicate address (PDO)
$dupSql = 'SELECT "ID" FROM "Addresses_AWS" WHERE "Name" = :name AND "H_No" = :hno LIMIT 1';
$dupStmt = $pdo->prepare($dupSql);
$dupStmt->execute([':name' => $name, ':hno' => $houseNo]);
$dupRow = $dupStmt->fetch(PDO::FETCH_ASSOC);
if ($dupRow) {
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
// Insert address (PDO)
$insertSql = 'INSERT INTO "Addresses_AWS" ("Name", "Halaqa", "H_No", "Apt_No", "St_Name", "City", "State", "Zip", "Verified", "Locality", "Masjid", "Comments", "Last_Visit", "Coordinates", "Status", "Clear", "uploaded_by") VALUES (:name, :halaqa, :hno, :aptno, :stname, :city, :state, :zip, :verified, :locality, :masjid, :comments, :lastVisit, :coordinates, :status, :clear, :uploadedBy)';
$stmt = $pdo->prepare($insertSql);
$ok = $stmt->execute([
    ':name' => $name,
    ':halaqa' => $halaqa,
    ':hno' => $houseNo,
    ':aptno' => $aptNo,
    ':stname' => $streetName,
    ':city' => $city,
    ':state' => $state,
    ':zip' => $zip,
    ':verified' => $verified,
    ':locality' => $locality,
    ':masjid' => $masjid,
    ':comments' => $comments,
    ':lastVisit' => $lastVisit,
    ':coordinates' => $coordinates,
    ':status' => $status,
    ':clear' => $clear,
    ':uploadedBy' => $uploadedBy
]);
if (!$ok) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Failed to create address (insert fallback)',
        'error' => implode(' | ', $stmt->errorInfo()),
        'insertSql' => $insertSql,
        'uploadedBy' => $uploadedBy,
    ));
    exit;
}
echo json_encode(array('success' => true, 'message' => 'Address created successfully'));
exit;
