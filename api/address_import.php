<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

include('db.php');

function permission_to_level($permissionRaw) {
        $value = trim((string)$permissionRaw);
        if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
        if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
        if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
        if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
        if (is_numeric($value)) return intval($value);
        return 0;
}

function enforce_address_access($con, $permissionLevel, $orgId) {
    if ($permissionLevel < 2) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Only admins and editors can import addresses'));
        exit;
    }

    if ($permissionLevel >= 3) {
        return;  // Admins and super admins can import
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
        echo json_encode(array('success' => false, 'message' => 'Active subscription required to import addresses'));
        exit;
    }
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

// ---------------------------------------------------------------
// Auth — any authenticated user may import; we record who they are
// ---------------------------------------------------------------
function get_auth_user_for_import($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);

    $stmt = mysqli_prepare($con,
        "SELECT id, Permissions, org_id FROM Login_user_AWS
         WHERE auth_token = ? AND status = 'true' LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $userId = null;
    $perms  = null;
    $orgId  = null;
    mysqli_stmt_bind_result($stmt, $userId, $perms, $orgId);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$found || !$userId) return null;
    return ['id' => intval($userId), 'permissions' => (string)$perms, 'org_id' => intval($orgId)];
}

$authUser = get_auth_user_for_import($con);
if (!$authUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$permissionLevel = permission_to_level($authUser['permissions']);
enforce_address_access($con, $permissionLevel, intval($authUser['org_id']));
$uploadedBy = resolve_effective_owner_id($con, intval($authUser['id']), intval($authUser['org_id']), permission_to_level($authUser['permissions']));
$uploadedByOrgId = $authUser['org_id'];
$selectedMasjid = isset($_POST['masjid']) ? trim($_POST['masjid']) : '';
$validateOnly = isset($_POST['validateOnly']) && $_POST['validateOnly'] === '1';
$ignoreErrors = isset($_POST['ignoreErrors']) && $_POST['ignoreErrors'] === '1';

if ($selectedMasjid === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Masjid is required']);
    exit;
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
    echo json_encode(['success' => false, 'message' => 'Failed to validate masjid: ' . mysqli_error($con)]);
    exit;
}
mysqli_stmt_bind_param($masjidStmt, 'siii', $selectedMasjid, $uploadedBy, $uploadedByOrgId, $uploadedByOrgId);
mysqli_stmt_execute($masjidStmt);
mysqli_stmt_bind_result($masjidStmt, $masjidId);
$hasApprovedMasjid = mysqli_stmt_fetch($masjidStmt);
mysqli_stmt_close($masjidStmt);

if (!$hasApprovedMasjid) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Select one of your approved masjids before importing addresses']);
    exit;
}

// ---------------------------------------------------------------
// File validation
// ---------------------------------------------------------------
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploadError = isset($_FILES['file']) ? $_FILES['file']['error'] : 'no file';
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error: ' . $uploadError]);
    exit;
}

$tmpPath = $_FILES['file']['tmp_name'];
$origName = $_FILES['file']['name'];

// Accept only CSV (by extension and MIME hint)
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Only CSV files are accepted']);
    exit;
}

$handle = fopen($tmpPath, 'r');
if (!$handle) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to open uploaded file']);
    exit;
}

// ---------------------------------------------------------------
// Parse header row
// ---------------------------------------------------------------
$rawHeaders = fgetcsv($handle);
if ($rawHeaders === false) {
    fclose($handle);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSV file is empty or unreadable']);
    exit;
}

function normalize_header_name($header) {
    $normalized = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $header)));
    $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);

    $aliases = [
        'houseno' => 'houseno',
        'hno' => 'houseno',
        'house_no' => 'houseno',
        'aptno' => 'aptno',
        'aptnoo' => 'aptno',
        'apt_no' => 'aptno',
        'apartment' => 'aptno',
        'streetname' => 'streetname',
        'stname' => 'streetname',
        'st_name' => 'streetname',
        'city' => 'city',
        'state' => 'state',
        'zip' => 'zip',
        'zipcode' => 'zip',
        'locality' => 'locality',
        'comments' => 'comments',
        'lastvisit' => 'lastvisit',
        'last_visit' => 'lastvisit',
        'masjid' => 'masjid',
        'coordinates' => 'coordinates',
        'verified' => 'verified',
        'name' => 'name',
        'halaqa' => 'halaqa',
    ];

    return isset($aliases[$normalized]) ? $aliases[$normalized] : $normalized;
}

function friendly_header_label($header) {
    $labels = array(
        'name' => 'Name',
        'houseno' => 'H_No',
        'aptno' => 'Apt_No',
        'streetname' => 'St_Name',
        'city' => 'City',
        'state' => 'State',
        'zip' => 'Zip',
        'comments' => 'Comments',
        'locality' => 'locality',
        'lastvisit' => 'Last_Visit',
        'coordinates' => 'Coordinates',
        'verified' => 'Verified',
        'masjid' => 'Masjid',
    );

    $key = strtolower(trim((string)$header));
    return isset($labels[$key]) ? $labels[$key] : $header;
}

// Normalise headers: lowercase, strip BOM + whitespace, then map aliases
$headers = array_map(function($h) {
    return normalize_header_name($h);
}, $rawHeaders);

$colIndex = array_flip($headers);

$required = ['name', 'houseno', 'streetname', 'city', 'state', 'zip', 'locality'];
$missing = [];
foreach ($required as $req) {
    if (!isset($colIndex[$req])) $missing[] = $req;
}
if ($missing) {
    fclose($handle);
    $missingLabels = array_map('friendly_header_label', $missing);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'CSV is missing required columns: ' . implode(', ', $missingLabels),
        'found_columns' => $headers,
    ]);
    exit;
}

// Helper to extract a value by column name (empty string if absent)
function col($row, $colIndex, $name) {
    return isset($colIndex[$name]) && isset($row[$colIndex[$name]])
        ? trim($row[$colIndex[$name]])
        : '';
}

// Prepare duplicate-check statement
$dupSql = 'SELECT ID FROM Addresses_AWS
           WHERE Name = ? AND H_No = ? AND St_Name = ? AND City = ? AND State = ? AND Zip = ?
           LIMIT 1';
$dupStmt = mysqli_prepare($con, $dupSql);
if (!$dupStmt) {
    fclose($handle);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare duplicate check: ' . mysqli_error($con)]);
    exit;
}

// ---------------------------------------------------------------
// Process rows
// ---------------------------------------------------------------
$inserted = 0;
$skipped  = 0;
$errors   = [];
$validRows = [];
$rowNum   = 1; // header was row 1; data starts at row 2

$defaultClear       = 0;
$defaultVerified    = 'N';
$defaultMasjid      = $selectedMasjid;
$defaultCoordinates = '';
$todayDate          = date('Y-m-d');

while (($row = fgetcsv($handle)) !== false) {
    $rowNum++;

    // Skip completely blank rows
    if (array_filter($row, function($v) { return trim($v) !== ''; }) === []) {
        continue;
    }

    $name        = col($row, $colIndex, 'name');
    $houseNo     = col($row, $colIndex, 'houseno');
    $aptNo       = col($row, $colIndex, 'aptno');
    $streetName  = col($row, $colIndex, 'streetname');
    $city        = col($row, $colIndex, 'city');
    $state       = col($row, $colIndex, 'state');
    $zip         = col($row, $colIndex, 'zip');
    $locality    = col($row, $colIndex, 'locality');
    $comments    = col($row, $colIndex, 'comments');
    $lastVisitRaw = col($row, $colIndex, 'lastvisit');
    // CSV masjid is intentionally ignored; selected dropdown masjid is authoritative.
    $rowCoordinatesRaw = col($row, $colIndex, 'coordinates');
    $rowVerifiedRaw = col($row, $colIndex, 'verified');
    $halaqa      = col($row, $colIndex, 'halaqa');

    if ($halaqa === '') $halaqa = 'Atlanta East';

    $masjidToUse = $defaultMasjid;

    $coordinatesToUse = $defaultCoordinates;
    $coordToken = strtoupper(trim($rowCoordinatesRaw));
    $isMissingCoordinate = in_array($coordToken, ['', 'NA', 'N/A', 'NULL', 'NONE', '-', '--'], true)
        || preg_match('/^\s*N\/?A\s*,\s*N\/?A\s*$/i', $rowCoordinatesRaw)
        || preg_match('/^\s*NULL\s*,\s*NULL\s*$/i', $rowCoordinatesRaw);

    if (!$isMissingCoordinate) {
        if (preg_match('/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*$/', $rowCoordinatesRaw)) {
            $parts = explode(',', $rowCoordinatesRaw, 2);
            $lat = trim($parts[0]);
            $lng = trim($parts[1]);
            $coordinatesToUse = $lat . ',' . $lng;
        } else {
            $errors[] = ['row' => $rowNum, 'message' => 'Coordinates must be in "lat,lng" format (or leave blank/NA)'];
            continue;
        }
    }

    $verifiedToUse = $defaultVerified;
    if ($rowVerifiedRaw !== '') {
        $normalized = strtoupper(trim($rowVerifiedRaw));
        if ($normalized === 'Y' || $normalized === 'YES' || $normalized === 'TRUE' || $normalized === '1') {
            $verifiedToUse = 'Y';
        } elseif ($normalized === 'N' || $normalized === 'NO' || $normalized === 'FALSE' || $normalized === '0') {
            $verifiedToUse = 'N';
        } else {
            $errors[] = ['row' => $rowNum, 'message' => 'Verified must be Y or N'];
            continue;
        }
    }

    // Sanitise last_visit — accept YYYY-MM-DD or common US formats
    $lastVisit = $todayDate;
    if ($lastVisitRaw !== '') {
        $parsed = date_create_from_format('Y-m-d', $lastVisitRaw)
               ?: date_create_from_format('m/d/Y', $lastVisitRaw)
               ?: date_create_from_format('n/j/Y', $lastVisitRaw);
        if ($parsed) {
            $lastVisit = date_format($parsed, 'Y-m-d');
        }
    }

    // Duplicate check
    $existingId = null;
    mysqli_stmt_bind_param($dupStmt, 'ssssss', $name, $houseNo, $streetName, $city, $state, $zip);
    mysqli_stmt_execute($dupStmt);
    mysqli_stmt_bind_result($dupStmt, $existingId);
    $isDuplicate = mysqli_stmt_fetch($dupStmt);
    mysqli_stmt_free_result($dupStmt);

    if ($isDuplicate) {
        $skipped++;
        continue;
    }

    $validRows[] = [
        'name' => $name,
        'halaqa' => $halaqa,
        'houseNo' => $houseNo,
        'aptNo' => $aptNo,
        'streetName' => $streetName,
        'city' => $city,
        'state' => $state,
        'zip' => $zip,
        'verified' => $verifiedToUse,
        'masjid' => $masjidToUse,
        'comments' => $comments,
        'lastVisit' => $lastVisit,
        'coordinates' => $coordinatesToUse,
        'locality' => $locality,
    ];
}

fclose($handle);
mysqli_stmt_close($dupStmt);

if ($validateOnly) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'validationOnly' => true,
        'canImport' => count($validRows) > 0,
        'inserted' => count($validRows),
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => count($errors) === 0
            ? 'Validation passed. Confirm to import all rows.'
            : 'Some rows have invalid coordinates. Those rows will be skipped; all others will import.',
    ]);
    exit;
}

if (count($errors) > 0 && !$ignoreErrors) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'validationOnly' => false,
        'canImport' => false,
        'inserted' => 0,
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => 'Import blocked: fix all validation errors first.',
    ]);
    exit;
}

$insertSql = 'INSERT INTO Addresses_AWS
    (Name, Halaqa, H_No, Apt_No, St_Name, City, State, Zip,
     Verified, Masjid, Comments, Last_Visit, Coordinates, Locality,
     `Clear`, uploaded_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

$insertStmt = mysqli_prepare($con, $insertSql);
if (!$insertStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare insert: ' . mysqli_error($con)]);
    exit;
}

mysqli_begin_transaction($con);
$insertFailed = false;

foreach ($validRows as $rowData) {
    mysqli_stmt_bind_param(
        $insertStmt,
        'ssssssssssssssii',
        $rowData['name'],
        $rowData['halaqa'],
        $rowData['houseNo'],
        $rowData['aptNo'],
        $rowData['streetName'],
        $rowData['city'],
        $rowData['state'],
        $rowData['zip'],
        $rowData['verified'],
        $rowData['masjid'],
        $rowData['comments'],
        $rowData['lastVisit'],
        $rowData['coordinates'],
        $rowData['locality'],
        $defaultClear,
        $uploadedBy
    );

    if (!mysqli_stmt_execute($insertStmt)) {
        $insertFailed = true;
        $errors[] = ['row' => -1, 'message' => 'DB insert failed: ' . mysqli_stmt_error($insertStmt)];
        break;
    }
    $inserted++;
}

if ($insertFailed) {
    mysqli_rollback($con);
    $inserted = 0;
} else {
    mysqli_commit($con);
}

mysqli_stmt_close($insertStmt);

http_response_code(200);
echo json_encode([
    'success'  => !$insertFailed,
    'validationOnly' => false,
    'canImport' => !$insertFailed,
    'inserted' => $inserted,
    'skipped'  => $skipped,
    'errors'   => $errors,
    'message'  => $insertFailed
        ? 'Import failed and was rolled back. No rows were inserted.'
        : ($ignoreErrors && count($errors) > 0
            ? "Import complete with ignored row errors: $inserted inserted, $skipped skipped, " . count($errors) . " ignored errors"
            : "Import complete: $inserted inserted, $skipped skipped, " . count($errors) . " errors"),
]);
?>
