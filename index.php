<?php 
session_start();
if (!isset($_SESSION['username']))
{
	header("Location: https://".$_SERVER['HTTP_HOST']."/mobile/login.php");
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
<script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
  	
<script>
var pos;
		$(document).ready(function(){			
			$('#buffersubmit').click(function() {
				var mydistance = parseInt($("#distance option:selected").val());
				var url = "https://myjoula.com/mobile/mapdistance.php?mydistance="+mydistance+"&distance=Search";
				window.location = url;
			});
			$('#sathisearch').click(function() {
				var state = $("#state option:selected").val();
				//alert(state);
				var url = "https://myjoula.com/mobile/mapsathi.php?state="+state;
				window.location = url;
			});
			
			$.getJSON('getdata.php', function(data) {                
				//**********
				// Try HTML5 geolocation
				  if(navigator.geolocation) {
					navigator.geolocation.getCurrentPosition(function(position) {
						pos = new google.maps.LatLng(position.coords.latitude,position.coords.longitude);
						if(position.coords.latitude != ''){
							//alert(position.coords.latitude);
							$('#map_canvas').gmap('addMarker', { icon:new google.maps.MarkerImage('../images/geolocation.png'), 'position': pos, 'bounds': true}).click(function() {
								$('#map_canvas').gmap('openInfoWindow', {'content': 'Your are here'}, this);
							});
						}
					});
				  } else {
					// Browser doesn't support Geolocation
					handleNoGeolocation(false);
				  }
				//**********
				
				var i = 1;
				//var pos;
				$.each(data, function(key, val) {
					//if(i == 1){alert(val.Coordinates);}
					if(val.Coordinates != ""){
						var res = val.Coordinates.split(","); 
						var latt = res[0];
						var longg = res[1];
						$('#mylist').append('<li><a data-role="button" href=\"details.php?id='+ val.ID +'\" data-transition=\"slide\">'+ i +":  " + val.Name + ' (' + val.City +')' +  '</a></li>');
					}
					i = i + 1;
				});
				
				
			});	
		}); //document.ready

			
</script>
<script type=text/javascript>
	function ActionDeterminator(state_value) {
        var form_value;
        if(state_value == "Georgia"){form_value = document.mapForm.locality.value;}
        if(state_value == "Alabama"){form_value = document.mapForm_al.locality.value;}
        if(state_value == "South_Carolina"){form_value = document.mapForm_sc.locality.value;}
        if(state_value == "Tennessee"){form_value = document.mapForm_tn.locality.value;}


	    //alert(form_value);
		mylocality = document.mapForm.locality.value;
		var params = "locality="+form_value;
        if(form_value == "All") {
			var url = "https://"+window.location.host+"/mobile/map.php?"+params+"&state="+state_value+"&Action=Submit";
			window.location = url;
			//document.mapForm.action = 'map.php';
		}
		else{
			 var url = "https://"+window.location.host+"/mobile/map.php?area=All&"+params+"&Action=Submit";
			 window.location = url;
			 //document.mapForm.action = 'area.php'; 
		}
		return true;
	}
</script>
<style>
  .ui-home { background: #D4EBFF;}
  .ui-area { background: #D4EBFF;}
  .ui-east { background: #D4EBFF;}
</style>

</head>
<body>
<div data-role="page" id="pageone" class="ui-home" style="background-color: #389jd3;">
  <div data-role="header" data-theme="b">
     <!--<a href="index.php" class="ui-btn ui-corner-all ui-shadow ui-icon-home ui-btn-icon-left">Home</a>-->
    <h1>Joula Dashboard</h1>
    
	<!--<a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left">log in</a>-->
  </div>

  <div data-role="main" class="ui-content" data-transition="slide"  data-position="fixed" data-role="button" data-theme="b">
    
    <center>Welcome <?php echo $_SESSION['username']; ?></center>
	<a href="#AreaSelection" data-transition='slide' data-role='button' data-mini="true" data-theme="b" >View Map</a>
	<?php
	if($_SESSION['permissions_level'] > 0){
	?>
	<a href="https://www.myjoula.com/mobile/newList.php" data-transition="slide" data-role="button" data-mini="true" data-theme="b" rel="external">View List</a>
	<a href="https://www.myjoula.com/mobile/Print_List.php" data-transition="slide" data-role="button" data-mini="true" data-theme="b" rel="external">Print List</a>
	
	<?php
	}
	?>
	<a href="https://www.myjoula.com/mobile/nearestMasjid.php" data-transition="slide" data-role="button" data-mini="true" data-theme="b" rel="external">Find Nearest Masjid</a>
	<?php
	if($_SESSION['permissions_level'] > 0){
	?>
	<a href='https://www.myjoula.com/mobile/edit.php' data-transition='slide' data-role='button' data-mini="true" data-theme="b" rel='external'>Add New Address</a>
	<a href='https://www.myjoula.com/mobile/add_currentAddress.php' data-transition='slide' data-role='button' data-mini="true" data-theme="b" rel='external'>Add Current Address</a>
	<?php	
	}
	
	if($_SESSION['permissions_level'] == 3){
		?>	
			<a href='https://www.myjoula.com/mobile/add_masjid.php' data-transition='slide' data-role='button' data-mini="true" data-theme="b" rel='external'>Add New Masjid</a>	
			<a href='https://www.myjoula.com/mobile/add_user.php' data-transition='slide' data-role='button' data-mini="true" data-theme="b" rel='external'>Add New User</a>
			<a href='https://www.myjoula.com/mobile/delete_nonmuslims.php' data-transition='slide' data-role='button' data-mini="true" data-theme="b" rel='external'>Delete Non-Muslim</a>
		<?php
		include("connection.php.ini");
		mysqli_select_db($con, $db);
		$admin_query = "SELECT * FROM Masjids where Verified = 'No'";
		//echo $myquery;
		$admin_result = mysqli_query($con, $admin_query);


		$num_rows = mysqli_num_rows($admin_result);
		if($num_rows > 0){
			echo "<a href='https://www.myjoula.com/mobile/verify_Masjid.php' data-transition='slide' data-role='button' data-mini='true' data-theme='b' rel='external'>Verify New Masjid</a>";
		}
		?>	
			<a href='https://www.myjoula.com/mobile/list_users.php' data-transition='slide' data-role='button' data-mini="true" data-theme="b" rel='external'>Edit Users</a>
		<?php
		
	}

	if($_SESSION['permissions_level'] > 0){
	?>
	<a href='https://www.myjoula.com/mobile/list.php' data-transition='slide' data-role='button' data-mini="true" data-theme="b" rel='external'>Search</a>
	<?php
	
		
		echo "<a href='https://www.myjoula.com/halaqa' data-transition='slide' data-role='button' data-mini='true' data-theme='b' rel='external'>Halaqa Dashboard</a>";
		//echo $_SESSION['permissions_level']."-".$_SESSION['username']."-".$_SESSION['permissions'];	
	}
		$myid = $_SESSION['id'];
		echo "<a href='https://www.myjoula.com/mobile/add_user.php?id=$myid' data-transition='slide' data-role='button' data-mini='true' data-theme='b' rel='external'>Change Password</a>";
	?>
	
  </div>

  <div data-role="footer"   data-position="fixed" data-theme="b">
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
<!-- ************************Area Selection PAGE ****************************************** -->
<div data-role="page" id="AreaSelection"  class="ui-area">
	<div data-role="header"  data-theme="b" data-add-back-btn="true">
		<h1>Select Map Area</h1>
	</div>

	<div data-role="main" id="content1" class="ui-content" align="center">
		<a href="#Georgia" data-transition='slide' data-role='button' data-mini="true" data-theme='b'>Georgia</a>
		<a href="#Alabama" data-transition='slide' data-role='button' data-mini="true" data-theme='b'>Alabama</a>
		<a href="#South_Carolina" data-transition='slide' data-role='button' data-mini="true" data-theme='b'>South Carolina</a>
		<a href="#Tennessee" data-transition='slide' data-role='button' data-mini="true" data-theme='b'>Tennessee</a>
		<!--<a href="#North" data-transition='slide' data-role='button' data-mini="true" data-theme='b'>Atlanta North</a>
		<a href="#South" data-transition='slide' data-role='button' data-mini="true" data-theme='b'>Atlanta South</a>-->
		<BR><BR>
		<label for="distance">Select addresses by radius around you:</label>
		<form Action="mapdistance.php" method="Get" id="bufferdistance">
             <select name="mydistance" id="distance" data-mini="true" data-theme='b'>
                <option value="1">1 mile</option> 
                <option value="2">2 miles</option>
				<option value="3">3 miles</option>
                <option value="4">4 miles</option>
				<option value="5">5 miles</option>
                <option value="7">7 miles</option>
                <option value="10">10 miles</option>
                <option value="15">15 miles</option>
                <option value="25">25 miles</option>
             </select>             
		</form>
		<button id="buffersubmit" type="button"  data-mini="true" data-theme='b'>Search</button>
		
		
		<BR><BR>
		<?php
	if($_SESSION['permissions_level'] > 0){
	?>
	
		
		<label for="sathi">Show people who spend time</label>
		<form Action="mapsathi.php" method="Get" id="sathi">
             <select name="state" id="state" data-mini="true" data-theme='b'>
                <option value="All">All</option> 
                <option value="GA">Georgia</option>
				<option value="AL">Alabama</option>
                <option value="SC">South Carolina</option>
				<option value="TN">Tennessee</option>            
             </select>             
		</form>
		<button id="sathisearch" type="button"  data-mini="true" data-theme='b'>Search</button>
		
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

<!-- ************************Georgia Area Details PAGE ****************************************** -->
<div data-role="page" id="Georgia"   class="ui-georgia">
  <div data-role="header"  data-theme="b" data-add-back-btn="true">
   <h1>Georgia Area</h1>

  </div>

  <div data-role="main" id="content1" class="ui-content" align="center">
    <p><label for="select-choice-1" class="select">Select Localities</label></p>


	<form name="mapForm" id="mapForm" method="GET">
		<select name="locality" id="locality">
            <?php
            if($_SESSION['permissions_level'] == 3){
                echo "<option value='All'>All Localities</option>";
            }
            ?>
<!--			<option value="All">All Localities</option>-->
			<?php
					//$myID = $_GET['id'];
					include("connection.php.ini");
					mysqli_select_db($con, $db);
					$sql = "Select DISTINCT Locality from $table where State = 'GA' and Coordinates != '' order by Locality";
					$result = mysqli_query($con, $sql);
					while($row = mysqli_fetch_array($result))
					{				
						$Locality = $row['Locality'];
						$Locality_decode = urlencode($Locality);
						echo "<option value='$Locality_decode'>$Locality</option>";
					}
			?>	
		</select>	
<!--		<label><input type="checkbox" name="potential" id="potential">Potential Only</label>-->
<!--		<label>Select Category Below</label>-->
<!--		<label><input type="checkbox" name="African" id="African">African</label>-->
<!--		<label><input type="checkbox" name="American" id="American">American</label>		-->
		<input type="button" name="Action" data-inline="true" value="Submit" onClick="return ActionDeterminator('Georgia');">
	</form>	
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

<!-- ************************Alabama Area Details PAGE ****************************************** -->
<div data-role="page" id="Alabama"   class="ui-alabama">
    <div data-role="header"  data-theme="b" data-add-back-btn="true">
        <h1>Alabama Area</h1>
    </div>

    <div data-role="main" id="content1" class="ui-content" align="center">
        <p><label for="select-choice-1" class="select">Select Locality</label></p>


        <form name="mapForm_al" id="mapForm_al" method="GET">
            <select name="locality" id="locality">
                <?php
                if($_SESSION['permissions_level'] == 3){
                    echo "<option value='All'>All Localities</option>";
                }
                ?>
<!--                <option value="All">All Localities</option>-->
                <?php
                //$myID = $_GET['id'];
                include("connection.php.ini");
                mysqli_select_db($con, $db);
                $sql = "Select DISTINCT Locality from $table where State = 'AL' and Coordinates != '' order by Locality";
                $result = mysqli_query($con, $sql);
                while($row = mysqli_fetch_array($result))
                {
                    $Locality = $row['Locality'];
                    $Locality_decode = urlencode($Locality);
                    echo "<option value='$Locality_decode'>$Locality</option>";
                }
                ?>
            </select>
            <!--		<label><input type="checkbox" name="potential" id="potential">Potential Only</label>-->
            <!--		<label>Select Category Below</label>-->
            <!--		<label><input type="checkbox" name="African" id="African">African</label>-->
            <!--		<label><input type="checkbox" name="American" id="American">American</label>		-->
            <input type="button" name="Action" data-inline="true" value="Submit" onClick="return ActionDeterminator('Alabama');">
        </form>
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
<!-- ************************South Carolina Area Details PAGE ****************************************** -->
<div data-role="page" id="South_Carolina"   class="ui-South_Carolina">
    <div data-role="header"  data-theme="b" data-add-back-btn="true">
        <h1>South_Carolina</h1>
    </div>

    <div data-role="main" id="content1" class="ui-content" align="center">
        <p><label for="select-choice-1" class="select">Select Locality</label></p>


        <form name="mapForm_sc" id="mapForm_al" method="GET">
            <select name="locality" id="locality">
                <?php
                if($_SESSION['permissions_level'] == 3){
                    echo "<option value='All'>All Localities</option>";
                }
                ?>
<!--                <option value="All">All Localities</option>-->
                <?php
                //$myID = $_GET['id'];
                include("connection.php.ini");
                mysqli_select_db($con, $db);
                $sql = "Select DISTINCT Locality from $table where State = 'SC' and Coordinates != '' order by Locality";
                $result = mysqli_query($con, $sql);
                while($row = mysqli_fetch_array($result))
                {
                    $Locality = $row['Locality'];
                    $Locality_decode = urlencode($Locality);
                    echo "<option value='$Locality_decode'>$Locality</option>";
                }
                ?>
            </select>
            <!--		<label><input type="checkbox" name="potential" id="potential">Potential Only</label>-->
            <!--		<label>Select Category Below</label>-->
            <!--		<label><input type="checkbox" name="African" id="African">African</label>-->
            <!--		<label><input type="checkbox" name="American" id="American">American</label>		-->
            <input type="button" name="Action" data-inline="true" value="Submit" onClick="return ActionDeterminator('South_Carolina');">
        </form>
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
<!-- ************************Tennessee Area Details PAGE ****************************************** -->
<div data-role="page" id="Tennessee"   class="ui-Tennessee">
    <div data-role="header"  data-theme="b" data-add-back-btn="true">
        <h1>Tennessee</h1>
    </div>

    <div data-role="main" id="content1" class="ui-content" align="center">
        <p><label for="select-choice-1" class="select">Select Locality</label></p>


        <form name="mapForm_tn" id="mapForm_al" method="GET">
            <select name="locality" id="locality">
                <?php
                if($_SESSION['permissions_level'] == 3){
                echo "<option value='All'>All Localities</option>";
                }
                ?>
                <!--                <option value="All">All Localities</option>-->
                <?php
                //$myID = $_GET['id'];
                include("connection.php.ini");
                mysqli_select_db($con, $db);
                $sql = "Select DISTINCT Locality from $table where State = 'TN' and Coordinates != '' order by Locality";
                $result = mysqli_query($con, $sql);
                while($row = mysqli_fetch_array($result))
                {
                    $Locality = $row['Locality'];
                    $Locality_decode = urlencode($Locality);
                    echo "<option value='$Locality_decode'>$Locality</option>";
                }
                ?>
            </select>
            <!--		<label><input type="checkbox" name="potential" id="potential">Potential Only</label>-->
            <!--		<label>Select Category Below</label>-->
            <!--		<label><input type="checkbox" name="African" id="African">African</label>-->
            <!--		<label><input type="checkbox" name="American" id="American">American</label>		-->
            <input type="button" name="Action" data-inline="true" value="Submit" onClick="return ActionDeterminator('Tennessee');">
        </form>
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