<?php
session_start();

if (!isset($_SESSION['username']))
{
	header('Location: login.php');
	exit;
}
	require_once("header.inc.php");
	session_start();
?>
<div data-role="page" id="pageone">
	<div data-role="header"  data-theme="b">
		 <a href="index.php" class="ui-btn ui-corner-all ui-shadow ui-icon-home ui-btn-icon-left">Home</a>
		<h1>Address Result</h1>
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

  <div data-role="main" class="ui-content" data-transition="slide"  data-position="fixed" data-role="button" data-theme="b">
    <center>
		<?php
			
			//include_once("classes/database.php");
			include_once("classes/geocoder.php");			
			//$con    = new Connection();
			$geo = new geocoder();
			
			/**/
			$Name=$_POST['Masjid_Name'];
			$H_No=$_POST['H_No'];
			$Apt_No=$_POST['Apt_No'];
			$St_Name=$_POST['St_Name'];
			$City=$_POST['City'];
			$State=$_POST['State'];
			$Zip=$_POST['Zip'];
            $Halaqa=$_POST['Halaqa'];
			$Verify=$_POST['Verified'];			
			$Comments=$_POST['Comments'];
			
			$geoAddress = $H_No." ".$St_Name." ".$City." ".$State." ".$Zip;			
			$address=urlencode($geoAddress);
			$result = $geo->getLocation($address);
			$Lat = $result["lat"];
			$Lon = $result["lng"];
			$mycoordinates = $Lat.",".$Lon;
			//$Name=$_GET['Name'];
			//$H_No=$_GET['H_No'];

			//echo $Locality;
			include("connection.php.ini");
				
				mysql_select_db($db, $con);
				
				$myquery = "SELECT * FROM Masjids where Name = '$Name' and H_No = '$H_No'";
				//echo $myquery;
				$result = mysql_query($myquery);
				$num_rows = mysql_num_rows($result);
				//echo $num_rows;
				
				if($num_rows == 1){		
					//echo $num_rows;	
					echo "<center>The Masjid with this name and number already exists in the database.</center>";					
				}
				else{
					$myquery = "insert into Masjids (Name, H_No, Apt_No, St_Name, City, State, Zip, Halaqa, Verified, Comments, Coordinates) values ";
					$myquery .= "('$Name','$H_No','$Apt_No','$St_Name','$City','$State','$Zip','$Halaqa','$Verify','$Comments','$mycoordinates')";
					
					//echo "$myquery<br>";
					$result = mysql_query($myquery);
					echo "<center>You Address Information has been added successfully</center>";
				}
		?>
	</center>
  </div>

  <div data-role="footer"   data-position="fixed" data-theme="b">
    <h1>Footer Text</h1>	
  </div>
</div> 
</body>
</html>