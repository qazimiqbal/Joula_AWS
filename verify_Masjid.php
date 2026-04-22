<?php

session_start();

if (!isset($_SESSION['username']))
{
	header('Location: login.php');
	exit;
}
if ($_SESSION['permissions_level'] < 2){
	echo "You should be Super Administrator to view this page";
	exit;
}
else {
?>	


	<!DOCTYPE html>
	<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
	<script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
	<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
	<script>

		function clicked(e)
		{
			if(confirm("Do you really want to do this?"))
				document.forms[0].submit();
			  else
				return false;
		}

	</script>
	</head>
	<body>

	<div data-role="page" id="pageone" class="ui-home" style="background-color: #389jd3;">
	  <div data-role="header" data-theme="b">
		 <!--<a href="index.php" class="ui-btn ui-corner-all ui-shadow ui-icon-home ui-btn-icon-left">Home</a>-->
		<h1>Verify New Masjids</h1>
		
		<!--<a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left">log in</a>-->
	  </div>
	  
	  <div data-role="main" class="ui-content">
		<?php
			include("connection.php.ini");	
			mysql_select_db($db, $con);
			if($_POST['verify'] == 'Yes'){
				$masjidID = $_POST['masjidID'];				
				//echo "$masjidID";
				$myquery = "Update Masjids set Verified = 'Yes' where ID = $masjidID";
				//echo "$myquery<br>";
				$result = mysql_query($myquery);
				echo "<center>The masjid has been updated</center>";
			}
			else if($_POST['verify'] == 'No'){
				$masjidID = $_POST['masjidID'];				
				//echo "$masjidID";
				$myquery = "Update Masjids set Verified = 'No' where ID = $masjidID";
				//echo "$myquery<br>";
				$result = mysql_query($myquery);
				echo "<center>The masjid has been updated</center>";
			}
			else if($_POST['verify'] == 'Delete'){
				$masjidID = $_POST['masjidID'];				
				//echo "$masjidID";
				$myquery = "Delete from Masjids where ID = $masjidID";
				//echo "$myquery<br>";
				//$result = mysql_query($myquery);
				echo "<center>The masjid has been Deleted</center>";
			}
			else{
			
				mysql_select_db($db, $con);
					$mySQL = "SELECT * from Masjids where Verified = 'No'";
					//echo $mySQL;
					$result = mysql_query($mySQL);
					$num_rows = mysql_num_rows($result);
					echo $num_rows;
					if($num_rows > 0){		
						echo "<center><form method='POST'><table border='1' style='border-collapse:collapse'><tr><td><strong>Masjid Name</strong></td><td><strong>Address</strong></td><td><strong>Verify</strong></td><td><strong>Mapit</strong></td></tr>";
						while($row = mysql_fetch_array($result))
						{
							$MasjidName = $row['Name'];
								$masjidID = $row['ID'];
								$H_No = $row['H_No'];
								$Street = $row['St_Name'];
								$City = $row['City'];
								$State = $row['State'];
								$Zip = $row['Zip'];
								echo"<tr><td>$MasjidName</td>";
								echo"<td>$H_No $Street $City $State $Zip</td>";
								//echo"<td><select name='verify' onchange='this.form.submit()'>
								echo"<td><select name='verify' >
										<option value='No'>No</option>
										<option value='Yes'>Yes</option>
										<option value='Delete'>Delete</option>
										</select>
										<input type='hidden' name='masjidID' value='$masjidID'>
								</td>";
								echo"<td><a href='https://maps.google.com/?q=$H_No $Street $City $State $Zip'><font color='#660000'>Navigate me here</font></a></td></tr>";
						}	
						
						echo"</table>
							<input type='button' name='Submit' value='Submit' onclick='clicked(event)'>
							</form></center>";
					}
					else{
						echo"<center>There are no new records</center>";
					}
			}		
			
			
					
		?>
	  </div>

		<div data-role="footer" data-theme="b" data-position="fixed" >
		<div data-role="navbar">
			<ul>
				<li><a href="https://www.myjoula.com/mobile/index.php">Home</a></li>
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
<?php
}
?>
