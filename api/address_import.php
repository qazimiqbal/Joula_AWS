<?php
include_once __DIR__ . '/cors.php';
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

require_once 'db.pgsql.php';

function permission_to_level($permissionRaw) {
        $value = trim((string)$permissionRaw);
        if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
        if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
        if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
        if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
        if (is_numeric($value)) return intval($value);
        return 0;
}

function enforce_address_access($pdo, $permissionLevel, $orgId) {
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
        echo json_encode(array('success' => false, 'message' => 'Active subscription required to import addresses'));
        exit;
    }
}

function resolve_effective_owner_id($pdo, $userId, $orgId, $permissionLevel) {
    if ($permissionLevel >= 3 || $orgId <= 0) {
        return intval($userId);
    }
    $sql = 'SELECT id FROM "Login_user_AWS" WHERE org_id = :orgId AND status = :status AND (org_role = :orgAdmin OR org_role = :admin OR permissions = :perm3 OR permissions = :perm4) ORDER BY CASE WHEN org_role = :orgAdmin THEN 0 WHEN org_role = :admin THEN 1 ELSE 2 END, id ASC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':orgId' => $orgId,
        ':status' => 'true',
        ':orgAdmin' => 'org_admin',
        ':admin' => 'admin',
        ':perm3' => '3',
        ':perm4' => '4',
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($row && isset($row['id'])) ? intval($row['id']) : intval($userId);
}

// ---------------------------------------------------------------
// Auth — any authenticated user may import; we record who they are
// ---------------------------------------------------------------
function get_auth_user_for_import($pdo) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);

    $sql = 'SELECT id, permissions, org_id FROM "Login_user_AWS" WHERE auth_token = :token AND status = :status LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return ['id' => intval($row['id']), 'permissions' => (string)$row['permissions'], 'org_id' => intval($row['org_id'])];
}

// Use PDO for all DB actions
$authUser = get_auth_user_for_import($pdo);
if (!$authUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$permissionLevel = permission_to_level($authUser['permissions']);
enforce_address_access($pdo, $permissionLevel, intval($authUser['org_id']));
$uploadedBy = resolve_effective_owner_id($pdo, intval($authUser['id']), intval($authUser['org_id']), permission_to_level($authUser['permissions']));
$uploadedByOrgId = $authUser['org_id'];
$selectedMasjid = isset($_POST['masjid']) ? trim($_POST['masjid']) : '';
$validateOnly = isset($_POST['validateOnly']) && $_POST['validateOnly'] === '1';
$ignoreErrors = isset($_POST['ignoreErrors']) && $_POST['ignoreErrors'] === '1';

if ($selectedMasjid === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Masjid is required']);
    exit;
}

// Validate masjid
$masjidSql = 'SELECT m."ID" FROM "Masjids_AWS" m INNER JOIN "Login_user_AWS" owner ON owner."id" = m."Created_by" WHERE m."Name" = :name AND COALESCE(m."Clear", 1) = 1 AND (m."Created_by" = :createdBy OR (:orgId > 0 AND owner."org_id" = :orgId)) LIMIT 1';
$masjidStmt = $pdo->prepare($masjidSql);
$masjidStmt->execute([':name' => $selectedMasjid, ':createdBy' => $uploadedBy, ':orgId' => $uploadedByOrgId]);
$masjidRow = $masjidStmt->fetch(PDO::FETCH_ASSOC);
if (!$masjidRow) {
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
$rawHeaders = fgetcsv($handle, 0, ',', '"', '\\');
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

function normalize_last_visit($rawValue, $fallbackDate) {
    $value = trim((string)$rawValue);
    if ($value === '') {
        return $fallbackDate;
    }

    $upper = strtoupper($value);
    if (in_array($upper, ['NA', 'N/A', 'NULL', 'NONE', '-', '--'], true)) {
        return $fallbackDate;
    }

    // Handle Excel-style serial date values (e.g. 45123)
    if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
        $serial = (float)$value;
        if ($serial > 0) {
            $days = (int)floor($serial);
            $base = new DateTime('1899-12-30', new DateTimeZone('UTC'));
            $base->modify('+' . $days . ' days');
            $year = (int)$base->format('Y');
            if ($year >= 1900 && $year <= 2100) {
                return $base->format('Y-m-d');
            }
        }
        return $fallbackDate;
    }

    $parsed = date_create_from_format('Y-m-d', $value)
           ?: date_create_from_format('m/d/Y', $value)
           ?: date_create_from_format('n/j/Y', $value);

    if (!$parsed) {
        return $fallbackDate;
    }

    $year = (int)$parsed->format('Y');
    if ($year < 1900 || $year > 2100) {
        return $fallbackDate;
    }

    return $parsed->format('Y-m-d');
}

// Prepare duplicate-check statement
// Prepare duplicate-check statement (PDO)
$dupSql = 'SELECT "ID" FROM "Addresses_AWS" WHERE "Name" = :name AND "H_No" = :hno AND "St_Name" = :stname AND "City" = :city AND "State" = :state AND "Zip" = :zip LIMIT 1';
$dupStmt = $pdo->prepare($dupSql);

// ---------------------------------------------------------------
// Process rows
// ---------------------------------------------------------------
$inserted = 0;
$skipped  = 0;
$errors   = [];
$validRows = [];
$rowNum   = 1; // header was row 1; data starts at row 2

$defaultClear       = 0;
$defaultStatus      = 'pending';
$defaultVerified    = 'N';
$defaultMasjid      = $selectedMasjid;
$defaultCoordinates = '';
$todayDate          = date('Y-m-d');

while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
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

    // Sanitise last_visit for Postgres date compatibility.
    $lastVisit = normalize_last_visit($lastVisitRaw, $todayDate);

    // Duplicate check
    $dupStmt->execute([
        ':name' => $name,
        ':hno' => $houseNo,
        ':stname' => $streetName,
        ':city' => $city,
        ':state' => $state,
        ':zip' => $zip
    ]);
    $isDuplicate = $dupStmt->fetch(PDO::FETCH_ASSOC);
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
$dupStmt = null;

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

// Prepare insert statement (PDO)
$insertSql = 'INSERT INTO "Addresses_AWS"
    ("Name", "Halaqa", "H_No", "Apt_No", "St_Name", "City", "State", "Zip",
     "Verified", "Masjid", "Comments", "Last_Visit", "Coordinates", "Locality",
     "Status", "Clear", "uploaded_by")
    VALUES (:name, :halaqa, :hno, :aptno, :stname, :city, :state, :zip, :verified, :masjid, :comments, :lastVisit, :coordinates, :locality, :status, :clear, :uploadedBy)';
$pdo->beginTransaction();
$insertFailed = false;
$inserted = 0;
try {
    $insertStmt = $pdo->prepare($insertSql);
    foreach ($validRows as $rowData) {
        $ok = $insertStmt->execute([
            ':name' => $rowData['name'],
            ':halaqa' => $rowData['halaqa'],
            ':hno' => $rowData['houseNo'],
            ':aptno' => $rowData['aptNo'],
            ':stname' => $rowData['streetName'],
            ':city' => $rowData['city'],
            ':state' => $rowData['state'],
            ':zip' => $rowData['zip'],
            ':verified' => $rowData['verified'],
            ':masjid' => $rowData['masjid'],
            ':comments' => $rowData['comments'],
            ':lastVisit' => $rowData['lastVisit'],
            ':coordinates' => $rowData['coordinates'],
            ':locality' => $rowData['locality'],
            ':status' => $defaultStatus,
            ':clear' => $defaultClear,
            ':uploadedBy' => $uploadedBy
        ]);
        if (!$ok) {
            $insertFailed = true;
            $errors[] = ['row' => -1, 'message' => 'DB insert failed: ' . implode(' | ', $insertStmt->errorInfo())];
            break;
        }
        $inserted++;
    }
    if ($insertFailed) {
        $pdo->rollBack();
        $inserted = 0;
    } else {
        $pdo->commit();
    }
} catch (PDOException $e) {
    $pdo->rollBack();
    $inserted = 0;
    $insertFailed = true;
    $errors[] = ['row' => -1, 'message' => 'DB insert failed: ' . $e->getMessage()];
}

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
