
<?php
// Use environment variables for DB connection, fallback to local defaults

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'myjouladb';
$db_user = getenv('DB_USER') ?: 'joulauser';
$db_pass = getenv('DB_PASS') ?: 'JoulaSecure2026!';

try {
    $dsn = "pgsql:host=$db_host;dbname=$db_name";
    $con = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
?>
