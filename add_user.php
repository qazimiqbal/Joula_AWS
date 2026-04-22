<?php
session_start();
if (!isset($_SESSION['username']))
{	
	header('Location: login.php');
}

?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
<script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
<script type="text/javascript" src="jquery.js"></script>


</head>
<body>
<!-- PAGE 1 -->
<div data-role="page" id="pageone" data-theme="a">
	<div data-role="header" data-add-back-btn="true" data-theme="b">
		 <a href="index.php" class="ui-btn ui-corner-all ui-shadow ui-icon-home ui-btn-icon-left">Home</a>
		<h1>Add New User</h1>
		<?php
			if (!isset($_SESSION['username']))
			{ 
				echo "<a href=\"login.php\" class=\"ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left\">login</a>";
			}
			else 
			{ 	
				echo "<a href=\"logout.php\" class=\"ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left\">logout</a>";
			}
		?>	
	</div>
	<div data-role="main" class="content1">  			
			<div align="center">
				<?php			
				
				if($_POST['Action'] == "Submit"){
					$username = $_POST['username'];
					$halaqa = $_POST['halaqa'];
					$email = $_POST['email'];
					$phone = $_POST['phone'];
					$masjid = $_POST['masjid'];
                    $locality = urlencode($_POST['locality']);
					$permissions = $_POST['permissions'];
					$pword = md5('1234');
					
					//echo $halaqa;
					include("connection.php.ini");
				
					mysqli_select_db($con, $db);
					$myquery = "insert into Login_user (username, password, email, phone, Masjid, Locality, Halaqa, Permissions, Pass_change, status) values ";
					$myquery .= "('$username', '$pword','$email','$phone','$masjid','$locality','$halaqa','$permissions','No','true')";
					//echo $myquery;
					$result = mysqli_query($con, $myquery);
					//$num_rows = mysql_num_rows($result);
					//echo $num_rows;
					echo "Record Successfully added<br> Password = 1234<BR><BR><BR>";
				}
				else if ($_POST['Action'] == "Update"){
					//echo $_POST['Action'];
					$changeid = $_POST['changeid'];
					$email = $_POST['email'];
					$phone = $_POST['phone'];
					$pword = MD5($_POST['password']);
					
					include("connection.php.ini");				
					mysqli_select_db($con, $db);
					$sql_update = "UPDATE Login_user set email = '$email', phone = '$phone', password = '$pword' where ID ='$changeid'";
					echo $sql_update;
					$result = mysqli_query($con, $sql_update);
					//$num_rows = mysqli_num_rows($result);
					//echo $num_rows;
					echo "<center><BR><BR>Record Successfully updated</center?";
				}	
				else if ($_GET['id'] != ""){
					$changeID = $_GET['id'];
					include("connection.php.ini");
					mysqli_select_db($con, $db);
					$myquery = "SELECT * FROM Login_user WHERE id = $changeID";
					//echo $myquery;
					$result = mysqli_query($con, $myquery);
					// Check username and password match
					//echo mysql_num_rows($login);
					
					while($row = mysqli_fetch_array($result))
					{			
					
							$uname = $row['username'];
							$pword = $row['password'];
							$email = $row['email'];
							$phone = $row['phone'];
                            $locality = $row['locality'];
					}
					?>
					<form name="myForm" id="testForm" method="POST" action="add_user.php">
						<h3>Edit your Information</h3>
						<table width="90%" style="border-collapse:collapse" border="1">
							<tr>
								<td bgcolor="#F5F5DC"><label for="password">Enter newPassword</label></td><td><input id='password' name='password' value=''></td>
							</tr>
							<tr>
								<td bgcolor="#F5F5DC"><label for="email">Email</label></td><td><input id='email' name='email' value='<?php echo $email; ?>'></td>
							</tr>
							<tr>	
								<td bgcolor="#F5F5DC"><label for="phone">Phone</label></td><td><input id='phone' name='phone' value='<?php echo $phone; ?>'></td>
							</tr>
                            <tr>
                                <td bgcolor="#F5F5DC"><label for="locality">Phone</label></td><td><input id='locality' name='locality' value='<?php echo $locality; ?>'></td>
                            </tr>
						
							<tr><td  colspan="2" align="middle">
								<input type="hidden" name="changeid" value="<?php echo $changeID; ?>">
								<input type="submit" name="Action" value="Update"><br><br>
							</td></tr>
						</table>
					</form>		
					<?php		
					
				}
				else{
           		?>
				<form name="myForm" id="testForm" method="POST" action="add_user.php">
				<h3>Fill Your Information</h3>
					<table width="90%" style="border-collapse:collapse" border="1">
						<tr>
							<td bgcolor="#F5F5DC"><label for="username">User Name</label></td><td><input id='username' name='username' value=''></td>
						</tr>
						<tr>	
							<td bgcolor="#F5F5DC"><label for="halaqa">Halaqa</label></td><td><input id='halaqa' name='halaqa' value='Atlanta East'></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC"><label for="email">email</label></td><td><input id='email' name='email' value=''></td>
						</tr>
						<tr>	
							<td bgcolor="#F5F5DC"><label for="phone">Phone</label></td><td><input id='phone' name='phone' value=''></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC"><label for="masjid">Masjid</label></td><td><input id='masjid' name='masjid' value=''></td>
						</tr>
                        <tr>
                            <td bgcolor="#F5F5DC">
                                <label for="locality">Locality</label></td>
                            <td>
<!--                                <input id='locality' name='masjid' value=''>-->


                            <?php
                            include("connection.php.ini");
                            mysqli_select_db($con, $db);
                            $sql = "SELECT Distinct Locality FROM Addresses2 where Locality != '' and Coordinates != '' order by Locality,  Name";
                            $result = mysqli_query($con, $sql);
                            ?>
                            <select name="locality" id="locality" data-native-menu="true" data-mini="true">
                                <?php
                                echo "<option value='select' >Select Locality</option>";
                                echo "<option value='All' >ACCESS ALL AREAS - SUPER USER</option>";
                                while ($row = mysqli_fetch_array($result)) {
                                    //if($row['Area'] == $myArea){ $mycheck = "SELECTED";}
                                    //else{$mycheck = "";};
                                    //$encode_Area = urlencode($row['Area']);
                                    $Locality_name = $row['Locality'];
                                    $Locality = urlencode($row['Locality']);
                                    if ($Locality != "") {
                                        echo "<option value=$Locality >$Locality_name</option>";
                                    }
                                }
                                ?>
                              </select>
                            </td>





                        </tr>
                        <tr>
							<td bgcolor="#F5F5DC"><label for="permissions">Permissions</label></td>
							<td>
								<select name="permissions" id="permissions" data-native-menu="true" data-mini="true">
									<option value="Viewer">Viewer</option>
									<option value="Editor">Editor</option>
									<option value="Administrator">Administrator</option>
								</select>
							</td>
						</tr>
					
						<tr><td  colspan="2" align="middle">
							<input type="submit" name="Action" value="Submit" /><br><br>
						</td></tr>
					</table>
				</form>
				<?php
				}
				?>
			</div>
		
	</div>
	<div data-role="footer" data-theme="b">
		<h1>Footer Text</h1>
	</div>
</div> 


</body>
<script>
$(document).ready(function()
{
    $("#submit1").click(function()
    {
        var Name = $("#Name").val();
		var Halaqa = $("#Halaqa").val();
		var H_No = $("#H_No").val();
		var Apt_No = $("#Apt_No").val();
		var St_Name = $("#St_Name").val();
		var City = $("#City").val();
		var State = $("#State").val();
		var Zip = $("#Zip").val();
		var Comments = $("#Comments").val();
		
		//var contact = $("#contact").val();
		// Returns successful data submission message when the entered information is stored in database.
		var dataString = 'Name='+ Name + '&Halaqa='+ Halaqa + '&H_No='+ H_No + '&Apt_No='+ Apt_No + '&St_Name='+ St_Name ;
		dataString += '&City='+ City + '&State='+ State + '&Zip='+ Zip + '&Comments='+ Comments;
		
		
		if(Name==''||Halaqa==''||H_No==''||St_Name==''||City==''||State==''||Zip=='')
		{
			alert("Please Fill Name, Halaqa, H_No, St_Name, City, State and Zip");
		}
		else
		{
			$("#testForm").submit();
		}
 
    });
    $("#submit2").click(function()
    {
        $("form[name='myForm']").submit(); 
    });
    $("#submit3").click(function()
    {
        $("form:first").submit();
 
    });
 
    $("#submit4").click(function()
    {
        $("#testForm").submit(function()
        {
         alert('Form is submitting');
         return true;
        });     
        $("#testForm").submit(); //invoke form submission
 
    });
});
</script>
</html>