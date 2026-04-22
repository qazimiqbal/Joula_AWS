<?php
echo $_POST['username'];

/*session_start();
include("connection.php.ini");

mysql_select_db("Atlanta", $con) or DIE('Database is not available!');
$myquery = "SELECT * FROM Login_user WHERE (username = '" . mysql_real_escape_string($_POST['username']) . "') and (password = '" . mysql_real_escape_string(md5($_POST['password'])) . "') and status = 'true'";
$login = mysql_query($myquery);
// Check username and password match
if (mysql_num_rows($login) == 1) {
	while ($row = mysql_fetch_assoc($login)) {
		$_SESSION['id'] = $row['id'];
		$_SESSION['username'] = $row['username'];
		$_SESSION['Halaqa'] = $row['Halaqa'];
		$_SESSION['Permissions'] = $row['Permissions'];
	}
	
	// Set username session variable
	
	
	//Go to secured page
	//header('Location: jquery.php');
	*/
}
?>
