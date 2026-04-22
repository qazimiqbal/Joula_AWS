<?php
    session_start();
//if (!isset($_SESSION['username']))
//{
//	header('Location: login.php');
//}
if($_SESSION['permissions_level'] != 3){
	echo"<center> You dont have permissions to view this file.</center>";
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
<link rel="stylesheet" href="css/new_style.css">
<script src="http://code.jquery.com/jquery-1.11.2.min.js"></script>
<script src="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?v=3&sensor=false&language=en&libraries=geometry"></script>

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.min.js"></script>
<script type="text/javascript" src="jquery.ui.map.js"></script>

<script type="text/javascript" src="jquery.js"></script>
	<style>
		#map_canvas {
		height: 400px;
		margin: 0 auto;
		width: auto;
	</style>
</head>
<body>
<!-- PAGE 1 -->


<!-- ************************PAGE 2****************************************** -->
<div data-role="page" id="pageone" data-theme="a">
<div data-role="header" data-theme="b">
     <!--<a href="index.php" class="ui-btn ui-corner-all ui-shadow ui-icon-home ui-btn-icon-left">Home</a>-->
    <h1>Search</h1>
    
	<!--<a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left">log in</a>-->
  </div>
     <div data-role="main" class="ui-content" data-transition="slide"  data-position="fixed" data-role="button" data-theme="b">

		<?php
		
		if($_GET['UserDelete'] == "Yes")
		{
			$myid = $_GET['myID'];
			include("connection.php.ini");
            mysqli_select_db($con, $db);
			$myquery = "Delete from Login_user where id = $myid";
			//echo $myquery;
			$result = mysqli_query($con, $myquery);
			echo "<Center> The user has been successfully deleted.</center>";
		}
		if(isset ($_GET['ResetPassword']))
		{
				//echo $_GET['Update'];
				$digits = 4;
				$rand_no = rand(pow(10, $digits-1), pow(10, $digits)-1);
			
				
				$myid = $_GET['myID']; $email = $_GET['myemail'];//$username = $_GET['UserName']; $email = $_GET['email']; $phone = $_GET['phone'];
				//echo $rand_no."<BR>".$email;
				include("connection.php.ini");
				mysqli_select_db($con, $db);
				$myquery = "Update Login_user set password = MD5('$rand_no') where id = $myid";
				//echo $myquery;
				$result = mysqli_query($con, $myquery);
				
				$to      = $email;
				$subject = 'My Joula Password Reset';
				$message = 'You new password is '.$rand_no;
				$headers = 'From: webmaster@myjoula.com' . "\r\n" .
					'Reply-To: webmaster@myjoula.com' . "\r\n" .
					'X-Mailer: PHP/' . phpversion();

				mail($to, $subject, $message, $headers);
				
				
				echo "<Center> Your password has been successfully reset<br>Email has been send to you with new password.<br> $rand_no</center>";
		}
		if(isset ($_GET['ResetPermissions']))
		{
				//echo $_GET['Permissions'];
				$newPermissions = $_GET['Permissions'];
				//echo $newPermissions." Test";
				
				$myid = $_GET['myID']; 
				include("connection.php.ini");
				//mysql_select_db($db, $con);
                 mysqli_select_db($con, $db);
                $myquery_permissions = "Update Login_user set Permissions = '$newPermissions' where id = $myid";
            	//echo $myquery_permissions;
				$result = mysqli_query($con, $myquery_permissions);
				
				$to      = 'qazi.iqbal@gmail.com'; //$email;
				$subject = 'My Joula Password Reset';
				$message = 'The permissions for the user id '.$_GET['myID'] .'has been set to  '.$_GET['Permissions'];
				$headers = 'From: webmaster@myjoula.com' . "\r\n" .
					'Reply-To: webmaster@myjoula.com' . "\r\n" .
					'X-Mailer: PHP/' . phpversion();

				//mail($to, $subject, $message, $headers);
				
				
				echo "<Center> Your permissions for this user has been successfully reset</center>";
		}
		if(isset ($_GET['Update']))
		{
				//echo $_GET['Update'];
				$myid = $_GET['myID']; $username = $_GET['UserName']; $email = $_GET['email']; $phone = $_GET['phone'];
				include("connection.php.ini");
				mysqli_select_db($con, $db);
				$myquery = "Update Login_user set username = '$username', email = '$email', phone = '$phone' where id = $myid";
				//echo $myquery;
				$result = mysqli_query($con, $myquery);
				echo "<Center> Your data has been successfully updated</center>";
		}
		
		
		if(isset ($_GET['id']))
		{
				
				$myID = $_GET['id'];
				//echo $myID;
				include("connection.php.ini");
				mysqli_select_db($con, $db);
				$myquery = "SELECT * FROM Login_user where id = $myID";
				//echo $myquery;
				$result = mysqli_query($con, $myquery);
				// Check username and password match
				//echo mysql_num_rows($login);
				while($row = mysqli_fetch_array($result))
				{			
					$id = $row['id'];$uname = $row['username'];	$pword = $row['password'];$email = $row['email'];$phone = $row['phone'];$permissions = $row['Permissions'];
				}							
			?>
			<form  method="GET">
				<center><h3>Update Your Information</h3>
					<table width="90%" style="border-collapse:collapse" border="1">
						<tr>
							<td bgcolor="#F5F5DC"><label for="UserName">User Name</label></td><td><input id='UserName' name='UserName' value='<?php echo $uname; ?>'></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC"><label for="email">email</label></td><td><input id='email' name='email' value='<?php echo $email; ?>'></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC"><label for="phone">phone</label></td><td><input id='phone' name='phone' value='<?php echo $phone; ?>'></td>
						</tr>
						<tr>
							<td colspan="2">
								<input name='Update' type='hidden' value='true'>
								<input name='myID' type='hidden' value=<?php echo $id; ?>>
								<input name='Action' type='Submit' value='Submit'>
							</td>
						</tr>
					</table>	
				</center>
			</form><br><hr><br>
			
			<form  method="GET">
				<center><h3>Change Permissions</h3>
					<table width="90%" style="border-collapse:collapse" border="1">
						<tr>
							<td bgcolor="#F5F5DC"><label for="Permissions">Permissions</label></td>
							<td>
								<select name="Permissions">
									<option value="Editor" <?php if($permissions == "Editor"){echo "SELECTED";}; ?>>Editor</option>
									<option value="Administrator" <?php if($permissions == "Administrator"){echo "SELECTED";}; ?>>Administrator</option>
									<option value="Viewer" <?php if($permissions == "Viewer"){echo "SELECTED";}; ?>>Viewer</option>
								</select>
							</td>
						</tr>
						
						<tr>
							<td colspan="2">
								<input name='ResetPermissions' type='hidden' value='true'>
								<input name='myID' type='hidden' value=<?php echo $id; ?>>
								<input name='Action' type='Submit' value='Change'>
							</td>
						</tr>
					</table>	
				</center>
			</form>
			<br><hr><br>
			
			<form  method="GET">
				<center><h3>Reset Password Below</h3>
					<table width="90%" style="border-collapse:collapse" border="1">

						<tr>
							<td colspan="2">
								<input name='ResetPassword' type='hidden' value='true'>
								<input name='myemail' type='hidden' value=<?php echo $email; ?>>
								<input name='myID' type='hidden' value=<?php echo $id; ?>>
								<input name='Action' type='Submit' value='Reset Password'>
							</td>
						</tr>
					</table>	
				</center>
			</form>
			<br><hr><br>
			<form  method="GET">
				<center><h3>Delete User</h3>
					<table width="90%" style="border-collapse:collapse" border="1">

						<tr>
							<td colspan="2">
								<input name='UserDelete' type='hidden' value='Yes'>
								<input name='myID' type='hidden' value=<?php echo $id; ?>>
								<input name='Action' type='Submit' value='Delete User'>
							</td>
						</tr>
					</table>	
				</center>
			</form>
			
			<?php	
		}
		?>
				
		
	</div>
	  <div data-role="footer" data-theme="b" data-position="fixed" >
        <div data-role="navbar">
		<ul>
			<li><a href="index.php">Home</a></li>
			<li><button onclick="location.reload(true)">Refresh</button></li>
			<?php
			if (!isset($_SESSION['username']))
			{ 				
				echo "<li><a href=\"login.php\" class=\"ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left\">Login</a></li>";
			}
			else 
			{ 	
				echo "<li><a href=\"logout.php\" class=\"ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left\">LogOut</a></li>";
			}
			?>			
		</ul>
	</div>
  </div>
</div> 



</body>
</html>