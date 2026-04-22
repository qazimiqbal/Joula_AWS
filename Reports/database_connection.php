<?php
/* Database connection start */
$servername = "p3plcpnl0916.prod.phx3.secureserver.net";
$username = "joula";
$password = "Joula@955";
$dbname = "joula";

$conn= mysqli_connect($servername, $username, $password, $dbname) or die("Connection failed: " . mysqli_connect_error());
$connect = new PDO("mysql:host=$servername;dbname=joula", "joula", "Joula@955");

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

?>