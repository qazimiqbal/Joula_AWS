<?php

session_start();
/*
if (!isset($_SESSION['username']))
{
	header('Location: login.php');
	exit;
}
*/
include("connection.php.ini");
require_once("classes/dbcontroller.php");
$db_handle = new DBController();
if (isset($_POST['Locality']))
{
	echo $_POST['Locality'];
	if($_POST['Locality'] == "All")
	{
		echo "all";
		$sql = "SELECT * from $table order by Locality, Name";
	}
	else
	{
		if (isset($_POST['Area']))
		{
			$myLocality = urldecode($_POST['Locality']);
			$myArea = urldecode($_POST['Area']);
			if($myArea == "All"){
				$sql = "SELECT * from $table where Locality = '$myLocality' order by Locality, Name";
			}
			else{
				$sql = "SELECT * from $table where Locality = '$myLocality' and Area = '$myArea' order by Locality, Name";
			}
			
		}
		else{
			$myLocality = urldecode($_POST['Locality']);
			$sql = "SELECT * from $table where Locality = '$myLocality' order by Locality, Name";	
		}
		
	}	
}
else
{
	$sql = "SELECT * from $table order by Locality, Name limit 100";
}	
$faq = $db_handle->runQuery($sql);
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
<script type="text/javascript" src="https://maps.google.com/maps/api/js?key=AIzaSyDRJUdH7baSnN6Js1lF67ggb63Y_0w6dL8&sensor=true&language=en&libraries=geometry"></script>  
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.min.js"></script>  
<script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>




<script type=text/javascript>

		
		var x;
		
		function getLocation() { 
			x = document.getElementById("demo");			
			document.getElementById('demo').innerHTML = "<img src='images/loading.gif'>";
			
			//console.log(document.getElementById('demo').innerHTML);
			if (navigator.geolocation) {
				navigator.geolocation.getCurrentPosition(showPosition, showError);
			} else {
				x.innerHTML = "Geolocation is not supported by this browser.";
			}
		}
		
		function showPosition(position) {
				showNearestMasjid(position.coords.latitude, position.coords.longitude)
				//showNearestMasjid(33.803426, -84.184934)
		}
		function showError(error) {
			switch(error.code) {
				case error.PERMISSION_DENIED:
					x.innerHTML = "User denied the request for Geolocation."
					break;
				case error.POSITION_UNAVAILABLE:
					x.innerHTML = "Location information is unavailable."
					break;
				case error.TIMEOUT:
					x.innerHTML = "The request to get user location timed out."
					break;
				case error.UNKNOWN_ERROR:
					x.innerHTML = "An unknown error occurred."
					break;
			}
		}
	
		function showNearestMasjid(latt, longg){
			var your_latlng = new google.maps.LatLng(latt, longg);
			$.getJSON('getmasjiddata.php?locality=<?php echo $locality; ?>&area=<?php echo $area; ?>', function(data) {                 
				//console.log("HEllo");
				masjiddataArray = data;
				var i = 1;
				var temp_dist = 10000;
				var n_masjid_name;
				var nearestMasjid;
				var distanceArray= new Array();
				$.each(masjiddataArray, function(key, val) {
					//console.log(i+": "+val.Name);
					var res = val.Coordinates.split(","); 
					var masjid_lat = res[0];
					var masjid_long = res[1];
					var masjid_latlng = new google.maps.LatLng(masjid_lat, masjid_long);
					var Masjid_distance = (google.maps.geometry.spherical.computeDistanceBetween(masjid_latlng, your_latlng) / 1000).toFixed(2);
					//console.log(i+": MASJID DISTANCE: "+temp_dist+" Nearest Masjid: "+Masjid_distance+" Masjid name: "+val.Name);
					var diff_distance = Masjid_distance - temp_dist;
					mystring = Masjid_distance+"    ### "+val.Name+" ### "+val.H_No+" "+val.St_Name+" "+val.City+" "+val.State+" "+val.Zip;
					distanceArray.push(mystring);
					//console.log(mystring);
					i = i + 1;
				});
				//console.log(distanceArray.length);
				distanceNewArray = sortByDigits(distanceArray);
				var nearestSeven = distanceNewArray.slice(0,7);
				//console.log(nearestSeven);
				var content = "7 Masjids close to your location<br>";
				content += "<table width='100%' border='1' style='border-collapse: collapse'><tr><td><Strong>Masjid Name</strong></td><td><Strong>Distance</Strong></td><td><Strong>Navigate</Strong></td></tr>";
				for (i = 0, len = nearestSeven.length; i < len; i++) { 
					console.log(nearestSeven[i]);
					var myval = nearestSeven[i];
					var resArray = myval.split("###"); 
					var disttomasjid = resArray[0];
					var nameMasjid = resArray[1];
					var newMasjidlink = resArray[2];
					content += "<tr><td>"+nameMasjid+"</td><td>"+disttomasjid+" miles</td><td><Strong><a href='https://maps.google.com/?q="+newMasjidlink+"' target='_new'>Navigate to Masjid</a></Strong></td></tr>";
				}
				content += "</table>";
				document.getElementById('demo').innerHTML = content;
			});
			
		}
		function sortByDigits(array) {
			array.sort(function(a, b) {
				return(parseFloat(a.substring(0,5)) - parseFloat(b.substring(0,5)));
			});
			return(array);
		}
</script>
</head>
<body>

<div data-role="page" id="pageone" class="ui-home" style="background-color: #389jd3;">
  <div data-role="header" data-theme="b">
     <!--<a href="index.php" class="ui-btn ui-corner-all ui-shadow ui-icon-home ui-btn-icon-left">Home</a>-->
    <h1>Nearest Masjids</h1>
    
	<!--<a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left">log in</a>-->
  </div>
  
  <div data-role="main" class="ui-content">
	<button onclick="getLocation()">Show Nearest Masjids</button>
		<p id='demo' align="center"></p>
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
