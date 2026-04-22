<?php
// Inialize session
session_start();
//echo $_SESSION['username'];
if (!isset($_SESSION['username']))
{
	header('Location: login.php');
	exit;
}
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
echo $sql;
$faq = $db_handle->runQuery($sql);
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
<script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>




<script type=text/javascript>
function myFunction(locality, area, zoomid) {
	
	if(locality == ""){mylocality = "All";}else{mylocality = locality;}
	if(area == ""){myarea = "All";}else{myarea = area;}
	//alert(mylocality);
	//alert(myarea);
	//alert(zoomid);
	var url = "http://"+window.location.host+"/mobile/map.php?area="+myarea+"&zoomid="+zoomid+"&locality="+mylocality+"&Action=Submit";
    window.location = url;
}

</script>
</head>
<body>

<div data-role="page" id="pageone" class="ui-home" style="background-color: #389jd3;">
  <div data-role="header" data-theme="b">
     <!--<a href="index.php" class="ui-btn ui-corner-all ui-shadow ui-icon-home ui-btn-icon-left">Home</a>-->
    <h1>List</h1>
    
	<!--<a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left">log in</a>-->
  </div>
  
  <div data-role="main" class="ui-content">
     <p>
	<table width="100%" border="0">
	<tr>
		<td align="middle">
			<b>Sort by Locality</b><br>
			<form method="POST">
				<select name="Locality" onchange="this.form.submit()">
				<option value="All">All</option>
				<?php
				mysql_select_db($db, $con);
				//$mySQL = "Select distinct Locality from $table order by Locality";
				$mySQL = "Select DISTINCT Locality from $table where Coordinates != '' order by Locality,  Name";
					//echo $mySQL;
				$result = mysql_query($mySQL);
				while($row = mysql_fetch_array($result))
				{				
					if($row['Locality'] == $myLocality){ $mycheck = "SELECTED";}
					else{$mycheck = "";};
					$encode_Area = urlencode($row['Locality']);					
					$Locality = $row['Locality'];
					if($Locality != ""){
						
						echo "<option value=$encode_Area $mycheck>$Locality</option>";
					}	
				}	
				?>
				</select>
			</form>	
			
		</td>
	</tr>
	<tr>
		<td>
			<!--<form method="POST" Action="../admin/email_Area_report.php" target="_blank">
				<input type="hidden" name="Locality" value="<?php echo $myLocality; ?>">
				<input type="text" name="email" value="">
				<input type="submit" value="Email Report">
			</form>-->
			<?php
			if (isset($_POST['Locality']) && ($_POST['Locality'] != "All"))
			{
			?>	
				<b>Sort by Area $</b><br>
				<form method="POST">
					<select name="Area" onchange="this.form.submit()">
					<option value="All">All</option>
					<?php
					mysql_select_db($db, $con);
					$mySQL = "Select distinct Area from $table where Locality = '$myLocality' order by Area";
						//echo $mySQL;
					$result = mysql_query($mySQL);
					while($row = mysql_fetch_array($result))
					{				
						if($row['Area'] == $myArea){ $mycheck = "SELECTED";}
						else{$mycheck = "";};
						$encode_Area = urlencode($row['Area']);					
						$Area = $row['Area'];
						if($Area != ""){
							
							echo "<option value=$encode_Area $mycheck>$Area</option>";
						}	
					}	
					?>
					</select>
					<input type="hidden" name="Locality" value="<?php echo $myLocality; ?>">
				</form>	
			<?php
			}
			?>
		</td>
	<tr>	
</table>
	</p>
	  
		  <?php
			if(isset($_POST['Locality'])){$passlocality = "?locality=".$_POST['Locality'];}else{$passlocality = "";}			
			if(isset($_POST['Area'])){$passarea = "&area=".$_POST['Area'];}else{$passarea = "";}
			$link = "list_pdf_report.php".$passlocality.$passarea;
		 
		 if(isset($_POST['Locality'])){
		 
			echo "<center><a href='$link' target='_new'>View PDF Report</a></center>";
		 }
		 
	 
		?>
		  
   
  </div>

    <div data-role="footer" data-theme="b" data-position="fixed" >
    <div data-role="navbar">
		<ul>
			<li><a href="http://www.myjoula.com/mobile/index.php">Home</a></li>
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
