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

require_once 'db.pgsql.php';

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

    // Get list of all tables in the current schema
    $tables = [];
    $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tables[] = $row['tablename'];
    }

    $truncatedTables = [];
    $failedTables = [];

    // Disable triggers (for foreign key constraints)
    $pdo->exec("SET session_replication_role = 'replica'");

    // Truncate each table
    foreach ($tables as $table) {
        try {
            $pdo->exec('TRUNCATE TABLE "' . $table . '" RESTART IDENTITY CASCADE');
            $truncatedTables[] = $table;
        } catch (PDOException $e) {
            $failedTables[$table] = $e->getMessage();
        }
    }

    // Re-enable triggers
    $pdo->exec("SET session_replication_role = 'origin'");

    echo json_encode([
        'success' => true,
        'truncated' => $truncatedTables,
        'failed' => $failedTables,
    ]);
        'success' => false,
