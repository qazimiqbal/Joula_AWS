<?php
// Test connection to local Postgres
date_default_timezone_set('UTC');
$db = 'myjouladb';
$user = 'joulauser';
$pass = 'JoulaSecure2026!';
$host = 'localhost';
try {
    $con = new PDO("pgsql:host=$host;dbname=$db", $user, $pass);
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connection successful!\n";
    $stmt = $con->query('SELECT NOW() as now, version() as version');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Postgres time: " . $row['now'] . "\n";
    echo "Postgres version: " . $row['version'] . "\n";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
