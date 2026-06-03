<?php
$db = 'myjouladb';
$user = 'joulauser';
$pass = 'JoulaSecure2026!';
$host = 'localhost';
$pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass, [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Backward compatibility for endpoints still referencing $con.
$con = $pdo;
?>
