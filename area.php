<?php 
	//require_once("header.inc.php");
	session_start();
	if (!isset($_SESSION['username']))
	{
		header("Location: http://".$_SERVER['HTTP_HOST']."/mobile/index.php");
	}
	$Locality = $_GET['locality'];
	
	
//echo "Hello".$Locality;

//exit;
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
<script src="http://code.jquery.com/jquery-1.11.2.min.js"></script>
<script src="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
<script type=text/javascript>
	function ActionDeterminator() { 
		//alert(document.mapForm.area.value);		
		//alert(document.mapForm.locality.value);
		
		var area = document.mapForm.area.value;
		var params = "area="+area;
		var locality = document.mapForm.locality.value;
		params += "&locality="+locality
		
		
		
		
		if(document.mapForm.potential){
			var potential = document.mapForm.potential.value;
			if(potential == "yes"){	params += "&potential=yes";	}
		}
		if(document.mapForm.american){
			var american = document.mapForm.american.value;
			if(american == "yes"){	params += "&american=yes";	}
		}
		
		if(document.mapForm.african){
			var african = document.mapForm.african.value;
			if(african == "yes"){	params += "&african=yes";}
		}
		
		
		
		
				
		
		var url = "http://"+window.location.host+"/mobile/map.php?"+params+"&Action=Submit";
		window.location = url;
		}
</script>
<style>
  .ui-home { background:  #D4EBFF;}
</style>

</head>
<body>

<!-- ************************East Area Details PAGE ****************************************** -->
<div data-role="page"  class="ui-home" id="East">
  <div data-role="header"  data-theme="b" data-add-back-btn="true">
   <h1>East Area</h1>
  </div>

  <div data-role="main" id="content1" class="ui-content" align="center">
    <p><label for="select-choice-1" class="select">Select Area from <?php echo $Locality; ?></label></p>
	<form name="mapForm" method="GET">
		<input type="hidden" name="locality" value="<?php echo $Locality;?>">
		<?php
		if(isset($_GET['potential'])){echo"<input type='hidden' name='potential' value='yes'>";}
		if(isset($_GET['american'])){echo"<input type='hidden' name='american' value='yes'>";}
		if(isset($_GET['african'])){echo"<input type='hidden' name='african' value='yes'>";}
		?>
		
		
		<select name="area" id="area">
			<option value="All">All Areas</option>
			<?php
					
					$locality_decode = urlencode($Locality);
					if($Locality == "All"){$srcLocality = "";}
					else{$srcLocality = "where Locality = '$Locality'";}
					include("connection.php.ini");
					mysql_select_db($db, $con);
					$sql = "Select DISTINCT Area from $table $srcLocality order by Locality";
					
					//$date = new DateTime();
					//$mydate = $date->format( 'd/m/Y H:i:s' );	
					//mail("qazi.iqbal@gmail.com",$mydate ,$sql, null);
					
					$result = mysql_query($sql);
					while($row = mysql_fetch_array($result))
					{				
						$area = $row['Area'];
						$area_decode = urlencode($area);
						echo "<option value='$area_decode'>$area</option>";
					}
			?>	
		</select>		
		<!--<input type="submit" name="Action" data-inline="true" value="Submit" onclick="this.form.submit()">-->
		<input type="button" name="Action" data-inline="true" value="Submit" onClick="return ActionDeterminator();">
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