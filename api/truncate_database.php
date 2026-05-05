<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'CORS preflight OK']);
    exit;
}

// SECURITY: Only allow this on localhost or with a special override header
$isLocalRequest =
    (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1')) ||
    (isset($_SERVER['REMOTE_ADDR']) && ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1'));

$hasOverride = isset($_SERVER['HTTP_X_TRUNCATE_CONFIRM']) && $_SERVER['HTTP_X_TRUNCATE_CONFIRM'] === 'CONFIRM_TRUNCATE_ALL';

if (!$isLocalRequest && !$hasOverride) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Truncate endpoint only accessible from localhost or with X-Truncate-Confirm: CONFIRM_TRUNCATE_ALL header']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

include('db.php');

try {
    // Get list of all tables
    $result = mysqli_query($con, "SHOW TABLES");
    if (!$result) {
        throw new Exception('Failed to get table list: ' . mysqli_error($con));
    }

    $tables = [];
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    mysqli_free_result($result);

    // Disable foreign key checks
    $disableFkCheck = mysqli_query($con, "SET FOREIGN_KEY_CHECKS=0");
    if (!$disableFkCheck) {
        throw new Exception('Failed to disable foreign key checks: ' . mysqli_error($con));
    }

    $truncatedTables = [];
    $failedTables = [];

    // Truncate each table
    foreach ($tables as $table) {
        $truncateSql = "TRUNCATE TABLE `" . mysqli_real_escape_string($con, $table) . "`";
        $truncateResult = mysqli_query($con, $truncateSql);
        
        if ($truncateResult === false) {
            $failedTables[$table] = mysqli_error($con);
        } else {
            $truncatedTables[] = $table;
        }
    }

    // Re-enable foreign key checks
    $enableFkCheck = mysqli_query($con, "SET FOREIGN_KEY_CHECKS=1");
    if (!$enableFkCheck) {
        throw new Exception('Failed to re-enable foreign key checks: ' . mysqli_error($con));
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Database truncation completed',
        'truncated_tables' => $truncatedTables,
        'truncated_count' => count($truncatedTables),
        'failed_tables' => $failedTables,
        'failed_count' => count($failedTables)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Truncation failed: ' . $e->getMessage()
    ]);
}
?>
