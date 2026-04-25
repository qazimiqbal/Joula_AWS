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

// ---------------------------------------------------------------
// Auth — any authenticated user may import; we record who they are
// ---------------------------------------------------------------
function get_auth_user_for_import($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);

    $stmt = mysqli_prepare($con,
        "SELECT id, Permissions FROM Login_user_AWS
         WHERE auth_token = ? AND status = 'true' LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $userId = null;
    $perms  = null;
    mysqli_stmt_bind_result($stmt, $userId, $perms);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$found || !$userId) return null;
    return ['id' => intval($userId), 'permissions' => (string)$perms];
}

$authUser = get_auth_user_for_import($con);
if (!$authUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$uploadedBy = $authUser['id'];

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

// Normalise headers: lowercase, strip BOM + whitespace
$headers = array_map(function($h) {
    return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)));
}, $rawHeaders);

$colIndex = array_flip($headers);

$required = ['name', 'houseno', 'streetname', 'city', 'state', 'zip', 'locality'];
$missing = [];
foreach ($required as $req) {
    if (!isset($colIndex[$req])) $missing[] = $req;
}
if ($missing) {
    fclose($handle);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'CSV is missing required columns: ' . implode(', ', $missing),
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

// ---------------------------------------------------------------
// Prepare insert statement (used for every valid row)
// ---------------------------------------------------------------
$insertSql = 'INSERT INTO Addresses_AWS
    (Name, Halaqa, H_No, Apt_No, St_Name, City, State, Zip,
     Verified, Masjid, Comments, Last_Visit, Coordinates, Locality,
     Area, Status, `Clear`, uploaded_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

$insertStmt = mysqli_prepare($con, $insertSql);
if (!$insertStmt) {
    fclose($handle);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare insert: ' . mysqli_error($con)]);
    exit;
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
$rowNum   = 1; // header was row 1; data starts at row 2

$defaultArea        = 'unclassified';
$defaultStatus      = 'Muslim';
$defaultClear       = 0;
$defaultVerified    = 'N';
$defaultMasjid      = '';
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
    $halaqa      = col($row, $colIndex, 'halaqa');

    if ($halaqa === '') $halaqa = 'Atlanta East';

    // Validate required fields per row
    $rowErrors = [];
    if ($name === '')       $rowErrors[] = 'name is empty';
    if ($houseNo === '')    $rowErrors[] = 'houseNo is empty';
    if ($streetName === '') $rowErrors[] = 'streetName is empty';
    if ($city === '')       $rowErrors[] = 'city is empty';
    if ($state === '')      $rowErrors[] = 'state is empty';
    if ($zip === '')        $rowErrors[] = 'zip is empty';
    if ($locality === '')   $rowErrors[] = 'locality is empty';

    if ($rowErrors) {
        $errors[] = ['row' => $rowNum, 'message' => implode('; ', $rowErrors)];
        continue;
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

    // Insert
    mysqli_stmt_bind_param(
        $insertStmt,
        'ssssssssssssssssii',
        $name,
        $halaqa,
        $houseNo,
        $aptNo,
        $streetName,
        $city,
        $state,
        $zip,
        $defaultVerified,
        $defaultMasjid,
        $comments,
        $lastVisit,
        $defaultCoordinates,
        $locality,
        $defaultArea,
        $defaultStatus,
        $defaultClear,
        $uploadedBy
    );

    if (!mysqli_stmt_execute($insertStmt)) {
        $errors[] = ['row' => $rowNum, 'message' => 'DB insert failed: ' . mysqli_stmt_error($insertStmt)];
    } else {
        $inserted++;
    }
}

fclose($handle);
mysqli_stmt_close($insertStmt);
mysqli_stmt_close($dupStmt);

http_response_code(200);
echo json_encode([
    'success'  => true,
    'inserted' => $inserted,
    'skipped'  => $skipped,
    'errors'   => $errors,
    'message'  => "Import complete: $inserted inserted, $skipped skipped, " . count($errors) . " errors",
]);
?>
