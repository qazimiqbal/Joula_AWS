<?php 
//require_once("header.inc.php");
session_start();
include("connection.php.ini");
mysql_select_db($db, $con) or DIE('Database is not available!');
	$myquery = "SELECT * FROM Login_user WHERE (username = '" . mysql_real_escape_string($_POST['username']) . "') and (password = '" . mysql_real_escape_string(md5($_POST['password'])) . "') and status = 'true'";
	$login = mysql_query($myquery);
	// Check username and password match
	//echo mysql_num_rows($login);
	if (mysql_num_rows($login) == 1) {
		while ($row = mysql_fetch_assoc($login)) {
			$_SESSION['id'] = $row['id'];
			$_SESSION['username'] = $row['username'];
			$_SESSION['halaqa'] = $row['Halaqa'];
		}
		header('Location: index.php');
	}
	else
	{
		header('Location: login.php');	
	}

?>
