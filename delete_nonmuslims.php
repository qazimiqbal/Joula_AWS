<?php
session_start();
echo $_SESSION['username'];
//if (!isset($_SESSION['username']))
//{
//	header('Location: login.php');
//}
echo "Test".$_SESSION['permissions_level'];
if($_SESSION['permissions_level'] != 3){
	echo"<div style='text-align:center; color:red;'>  You dont have permissions to view this file.</div>";
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
<link rel="stylesheet" href="css/new_style.css">
<script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
<script type="text/javascript" src="https://maps.google.com/maps/api/js?v=3&sensor=false&language=en&libraries=geometry"></script>

<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.min.js"></script>
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
				include("connection.php.ini");
				mysqli_select_db($con, $db);
				//$myquery = "Delete from $table WHERE  status  = 'Non_muslim'";
				$myquery = "Update $table set status = 'Delete_Non_muslim' WHERE  status  = 'Non_muslim'";
				//echo $myquery;
				$result = mysqli_query($con, $myquery);
				if ($result === false) {
					echo "No data was deleted";
				}
				else{
					echo "<center>Data was successfully deleted</center>";
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