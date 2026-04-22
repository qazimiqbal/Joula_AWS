<?php
session_start();
if (!isset($_SESSION['username']))
{
	header("Location: http://".$_SERVER['HTTP_HOST']."/mobile/index.php");
}
$locality = urldecode($_GET['locality']);
//$locality = $_GET['locality'];
if(isset($_GET['area'])){
	$area = urldecode($_GET['area']);
	$old_area = $area;
}
else{
	$area = "";
}
if(isset($_GET['zoomid'])){
	$zoomid = $_GET['zoomid'];
}
else{
	$zoomid = 0;
}
//echo $message;
//exit;
?>
<!DOCTYPE html>
<html>
<head>
<title>Muslim Addresses my Map</title>
    <meta name="viewport" content="initial-scale=1, maximum-scale=1"/>
    <script type="text/javascript" src="http://maps.google.com/maps/api/js?language=en&libraries=geometry"></script>    
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.min.js"></script>
	<link rel="stylesheet" href="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
	<script src="http://code.jquery.com/jquery-1.11.2.min.js"></script>
	<script src="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
	<script type="text/javascript" src="jquery.ui.map.js"></script>
	<script>
		function showNearestMasjid(latt, longg){
			var glatlng_unit = new google.maps.LatLng(latt, longg);
			$.getJSON('getmasjiddata.php?locality=<?php echo $locality; ?>&area=<?php echo $area; ?>', function(data) {                 
				
				masjiddataArray = data;
				var i = 1;
				var masjid_dist = 10000;
				var n_masjid_name;
				var nearestMasjid;
				$.each(masjiddataArray, function(key, val) {
					//if(i < 5){
						//alert(val.Coordinates);
						var res = val.Coordinates.split(","); 
						var masjid_lat = res[0];
						var masjid_long = res[1];
						
						var glatlng_masjid = new google.maps.LatLng(masjid_lat, masjid_long);
						var Masjid_distance = (google.maps.geometry.spherical.computeDistanceBetween(glatlng_masjid, glatlng_unit) / 1000).toFixed(2);
						//console.log("MASJID DISTANCE: "+masjid_dist+" Nearest Masjid: "+Masjid_distance);
						
						var diff_distance = Masjid_distance - masjid_dist;
						if(diff_distance < 0){ 
							//console.log(Masjid_distance - masjid_dist);
							masjid_dist = Masjid_distance
							n_masjid_name = val.Name;
							masjid_link = val.H_No+" "+val.St_Name+" "+val.City+" "+val.State+" "+val.Zip;
						} 						
					//}
					i = i + 1;
				});
				document.getElementById('nearestMasjid').innerHTML = "<strong>("+n_masjid_name+") "+masjid_dist+" miles </strong><br><a href='http://maps.google.com/?q="+masjid_link+"' target='_new'>Navigate to Masjid</a>";
			});
			
		}
		function calcDistance(latt, longg){
			var glatlng1 = new google.maps.LatLng(latt, longg);
			try
			{
						var glatlng1 = pos;
						var glatlng2 = new google.maps.LatLng(latt, longg);
						var mydistance = (google.maps.geometry.spherical.computeDistanceBetween(glatlng1, glatlng2) / 1000).toFixed(2);
						//alert(mydistance);
						document.getElementById('results').innerHTML = "<strong>"+mydistance+" miles Approx.</strong>";
			}
			catch (error)
			{
				alert(error);
			}
		}	
		function changeLastVisit(ID){
			var d = new Date();    								
			var yr = d.getFullYear();
			var mth = d.getMonth() + 1;
			var dy = d.getDay();
			var today = yr+"-"+mth+"-"+dy;
			document.getElementById("Last_Visit").value = today; 
			//alert(ID);
		}
		function changeLocality() { 		
			var url = "http://"+window.location.host+"/mobile/index.php#East";
			window.location = url;
		}
		function changeArea() { 		
			var url = "http://"+window.location.host+"/mobile/area.php?locality=<?php echo $locality;?>&Action=Submit";
			window.location = url;
		}
	</script>
	<style>
		#mycontent {
			padding: 0;
			position : absolute !important; 
			top : 40px !important;  
			right : 0; 
			bottom : 40px !important;  
			left : 0 !important;     
		}
		div.relative {
			position: relative;
			left: 50%;
			top:70%;
			width:50px;
			visibility:hidden;
			z-index: 100;
		}
	</style>
</head>
<body>
<div class="relative"><span id="zoomid"><?php echo $zoomid;?></span></div>
    <div data-role="page" id="index"  data-theme="b"  data-add-back-btn="true">
        <div data-theme="b" data-role="header"  data-add-back-btn="true">
			
			<center>
			<?php 
			
			//if(isset($_GET['area'])){
				$old_locality = $locality;							
							$locality_decode = urlencode($locality);
							include("connection.php.ini");
							if($locality == "All"){$srcLocality = "";}
							else{$srcLocality = "and Locality = '$locality'";}
							//if(isset($_GET['area'])){
							if($area == "All"){
								$srcArea = "";
							}
							else{
								$srcArea = urlencode($area);
							}
			if(!isset($_GET['area'])){
				echo "<input type='button' name='Action' data-inline='true' value='Change Locality' onClick='return changeLocality();'>";
			}
			else{
				echo "<input type='button' name='Action' data-inline='true' value='Change Locality' onClick='return changeLocality();'>";
				echo "<input type='button' name='Action' data-inline='true' value='Change Area' onClick='return changeArea();'>";
			}
				
			?>	
			<button id='masjidbutton' type='button'>Show Masjids</button>			
			</center>
			
	    </div>

        <div data-role="mycontent" id="mycontent">
            	<script>
				//location.reload(true)
				var pos;
				var masjidmarkerslist = new Array();
				var addressmarkerslist = new Array();
				//var imgpath;
				var imgpath2;
				var imgpath_green;
				var imgpath_red;
				var imgpath_yellow;
				var imgpath_black;
				var imgpath_blue;
				var imgpath_grey;
				var imgpath_masjid;				
				var string;
				var addressdataArray = new Array();
				var masjiddataArray = new Array();
				var poi_markers = new Array();	
				var zoom_coord;
				
				
				$(document).ready(function(){
					$( "#map_canvas" ).html("<center><br><br><br><span class='red'><img src='images/loading.gif'></span></center>");
					$.ajaxSetup({ cache: false });
					//BELOW CREATES THE ARRAY FOR ADDRESSES
					$.getJSON('getpointdata.php?locality=<?php echo $locality; ?>&area=<?php echo $area; ?>', function(data) {                 
						//**********
						// Try HTML5 geolocation
				
				if(navigator.geolocation) {
					navigator.geolocation.getCurrentPosition(function(position) {
						pos = new google.maps.LatLng(position.coords.latitude,position.coords.longitude);
						if(position.coords.latitude != ''){
							//alert(position.coords.latitude);
							$('#map_canvas').gmap('addMarker', { icon:new google.maps.MarkerImage('../images/urlocation.png'), 'position': pos, 'bounds': true}).click(function() {
								$('#map_canvas').gmap('openInfoWindow', {'content': 'Your are here'}, this);
							});
						}
					});
				  } else {
					// Browser doesn't support Geolocation
					handleNoGeolocation(false);
				  }
				  /**/
				//**********
						var i = 1;
						$.each(data, function(key, val) {
							if(i == 1){
								//alert(val.Coordinates);
								//alert(val.Last_Visit);
								//alert(val.City);
								//alert(val.R1_comments);
							}
							var res = val.Coordinates.split(","); 
							var latt = res[0];
							var longg = res[1];
							//alert(val.Last_Visit);
							if(val.Last_Visit != "0000-00-00"){
								//alert(val.Last_Visit);
								var FirstDate = parseDate(val.Last_Visit)							
								
								var d = new Date();
								var today_yr = d.getFullYear();
								var today_day = d.getDay();
								var today_month = d
								
								var today = new Date();
								var dd = today.getDate();
								var mm = today.getMonth()+1; //January is 0!
								var yyyy = today.getFullYear();
								
								var formatted_today = yyyy+"-"+mm+"-"+dd;								
								var SecondDate = parseDate(formatted_today)
								//Below function calculates the difference in days from last visit till today
								var diffmonth =  daydiff(FirstDate, SecondDate)
								
							}
							
							if(diffmonth < 31){var imgpath = 'images/Green_Ball_16.png';  var colorcode = 'Last Visit - less than 30 days'}
							if(diffmonth > 30 && diffmonth < 61){var imgpath = 'images/Orange_Ball_16.png';  var colorcode = 'Last Visit - between 30 and 60 days'}
							if(diffmonth > 60 && diffmonth < 91){var imgpath = 'images/Yellow_Ball_16.png';  var colorcode = 'Last Visit - between 60 and 90 days'}
							if(diffmonth > 90){var imgpath = 'images/Blue_Ball_16.png';   var colorcode = 'Last Visit - more than 90 days'}
							if(val.Last_Visit == "0000-00-00"){var imgpath = 'images/Red_Ball_16.png';  var colorcode = 'Never Visited'}
							if(val.Status == "Non_muslim"){var imgpath = 'images/Black_Ball_16.png';   var colorcode = 'Non Muslim'}
							if(val.Status == "Ismailee"){var imgpath = 'images/Grey_Ball_16.png';   var colorcode = 'Ismaliee - Not Muslim'}
							if(val.Status == "Owner_Muslim"){var imgpath = 'images/Blue_Ball_16.png';   var colorcode = 'Owner muslim rented to non_muslim - Not Muslim'}
							
							
							
							
							var string = "<table border='1' width='100%' style='border-collapse:collapse'><tr>";
							string += "<td><font color='black'><b>Name</b></font></td><td><font color='#660000'>"+val.Name+"</font></td></tr>";
							string += "<tr><td><font color='black'><b>City</b></font></td><td><font color='#660000'>"+val.City+"</font></td></tr>";
							string += "<tr><td><font color='black'><b>Address</b></font></td><td><font color='#660000'>"+val.H_No+" "+val.St_Name+" "+val.City+" "+val.State+" "+val.Zip+"</font></td></tr>";
							string += "<tr><td><font color='black'><b>Status</b></font></td><td><font color='#660000'>"+val.Status+"</font></td></tr>";
							string += "<tr><td><img src='"+imgpath+"'></td><td><font color='#660000'>"+colorcode+"</font></td></tr>";
							string += "<td><b><a href='#' onclick='calcDistance("+latt+","+longg+")'><font color='black'>Get Distance</font></a></b></td><td><font color='#660000'><span id='results'></span></font></td></tr>";
							string += "<tr><td><font color='black'><b>Last Visit</b></font></td><td><font color='#660000'>"+val.Last_Visit+"</font></td></tr>";
							string += "<tr><td><font color='black'><b>Comments</b></font></td><td><font color='#660000'>"+val.R1_comments+"</font></td></tr>";
							<?php
							if (isset($_SESSION['username']))
							{ 
								?>
								string += "<tr><td colspan='2' align='middle'><a data-role='button' href='functions.php?Action=lastvisit&ID="+ val.ID +"' rel='external' target='_new'>Enter Visit Comments</a>";
								string += "<tr><td><font color='black'><b>Details</b></font></td><td><font color='#660000'><a href='http://"+window.location.host+"/edit.php?id="+val.ID+"'  rel='external' target='_new'>Edit</a></font></td></tr>";
								<?php
							}				
							?>
							string += "<tr><td colspan='2' align='middle'><a href='http://maps.google.com/?q="+val.H_No+" "+val.St_Name+" "+val.City+" "+val.State+" "+val.Zip+"'><font color='#660000'>Navigate me here</font></a></td></tr>";
							string += "</table>";
							
							$('#map_canvas').gmap('addMarker', { icon:new google.maps.MarkerImage(imgpath), 'position': val.Coordinates, 'bounds': true}).click(function() {
								$('#map_canvas').gmap('openInfoWindow', {'content': string}, this);
							});
							i = i + 1;
						});				
						$.ajaxSetup({ cache: true });
					});	
					
				}); //document.ready	
				//window.location.reload(true);				
			</script>
			<script>
				function parseDate(str) {
					//alert(str);
					var mdy = str.split('-')
					return new Date(mdy[0], mdy[1]-1, mdy[2]);
				}

				function daydiff(first, second) {
					return (second-first)/(1000*60*60*24);
				}

				//alert(daydiff(parseDate($('#first').val()), parseDate($('#second').val())));
			</script>
			
			<div id="map_canvas" style="height:100%"></div>
        </div>

        <div data-theme="b" data-role="footer" data-position="fixed">
            <div data-role="navbar">
				<ul>
					<li><a href="index.php"  data-ajax="false">Home</a></li>
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