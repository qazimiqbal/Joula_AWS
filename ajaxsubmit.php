<?php
session_start();
include("connection.php.ini");
//$connection = mysql_connect("localhost", "root", "Labqi1962"); // Establishing Connection with Server..
//$db = mysql_select_db("mydba", $connection); // Selecting Database
//Fetching Values from URL
$name2=$_POST['name1'];
//$email2=$_POST['email1'];
$password2=$_POST['password1'];
//$contact2=$_POST['contact1'];


	mysql_select_db("Atlanta", $con);
	$myquery = "SELECT * FROM Login_user where username = '$name2' and password = '$password2'";
	$myquery = "SELECT * FROM Login_user WHERE (username = '" . mysql_real_escape_string($name2) . "') and (password = '" . mysql_real_escape_string(md5($password2)) . "') and status = 'true'";

	$result = mysql_query($myquery);
	$num_rows = mysql_num_rows($result);
	
	if ($num_rows == 1) {
		while ($row = mysql_fetch_assoc($result)) {
			$_SESSION['id'] = $row['id'];
			$_SESSION['username'] = $row['username'];
			$_SESSION['Halaqa'] = $row['Halaqa'];
			$_SESSION['Permissions'] = $row['Permissions'];
		}
	}	

	echo $num_rows;
//Insert query
//$query = mysql_query("insert into form_element(name, email, password, contact) values ('$name2', '$email2', '$password2','$contact2')");
//echo "Form Submitted Succesfully";
mysql_close($con); // Connection Closed
?>