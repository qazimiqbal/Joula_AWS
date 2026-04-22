<?php
session_start();
//if (!isset($_SESSION['username']))
//{
//	header('Location: login.php');
//}

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
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
<script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
<script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>

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
    <div data-role="content" id="content"  align="center">
		     <p>
	<?php
		if(isset($_POST['search_val'])){
				$searchString = $_POST['search_val'];
		}
		else{
			$searchString = "";
		}
	?>
			 
	<table width="100%" border="0">
	<tr>
		<td align="middle">
			<b>Sort by Locality</b><br>
			<form method="POST">
				<select name="Locality" onchange="this.form.submit()">
				<option value="All">All</option>
				<?php
				mysqli_select_db($con, $db);
				//$mySQL = "Select DISTINCT Locality from $table where State = 'GA' and Coordinates != '' order by Locality,  Name";
				$mySQL = "Select DISTINCT Locality from $table where Coordinates != '' order by Locality,  Name";
					//echo $mySQL;
				$result = mysqli_query($con, $mySQL);
				while($row = mysqli_fetch_array($result))
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
				<input type="hidden" name="search_val" value="<?php echo $searchString; ?>">
			</form>	
			
		</td>
	</tr>
	
	<tr>
		<td>
		<form method="POST">
			  <input type="text" name="search_val" value="<?php echo $searchString; ?>">
			  <?php
			if (isset($_POST['Locality']) && ($_POST['Locality'] != "All"))
			{
			?>
			  <input type="hidden" name="Locality" value="<?php echo $myLocality; ?>">
			<?php
			}
			if (isset($_POST['Area']) && ($_POST['Area'] != "All")){
				//$passArea = urlencode($_POST['Area']);	
					echo "<input type='hidden' name='Area' value='$myArea'>";
				}
			 ?>
			 <input type="submit" value="Search">
		</form>
		<br>	
		
		
		<?php		 
			 //if(isset($_POST['Test'])){
		
				
		//THE BELOW BLOCK OF CODE OPERATES WHEN SEARCH IS SUBMITTED
		$myquery;
		if(isset($_POST['search_val'])){
			if($_POST['search_val'] <> ""){
				$search_val = strtolower($_POST['search_val']);
				if (isset($_POST['Locality']) && ($_POST['Locality'] != "All")){
					$mylocality = $_POST['Locality'];
					$localityCondition = "and Locality = \"$mylocality\""; 
				}
				else{
					$localityCondition = "";
				}	
			
				//echo $search_val."<BR>";
				//$condition = "and Name like '%$search_val%'";
				$myquery = "SELECT * FROM Addresses2 where Coordinates != '' and Name like '%$search_val%' $localityCondition order by Name";
				//mail('qazi.iqbal@gmail.com', 'My Subject', $myquery);
			}
			else{
				//echo "Hello<BR>";
			//	$condition = "";
				//mail('qazi.iqbal@gmail.com', 'My Subject', "Nothing");
				//$myquery = "SELECT * FROM Addresses2 where Coordinates != '' order by Name LIMIT 2 ";
				echo "Please enter the name above.<BR><BR>";
				exit;
			}
			//echo $myquery;	
			$result = mysqli_query($con, $myquery);
			$num_rows = mysqli_num_rows($result);
			//echo $num_rows."<BR><BR>";
		
			//echo count($output);
			if($num_rows != 0){
				echo "<ul id=\"mylist\" data-role=\"listview\" data-theme=\"d\">";
				while($row = mysqli_fetch_array($result)){
							$ID = $row['ID'];
							$Name = $row['Name'];
							$City = $row['City'];
							$Coordinates = $row['Coordinates'];
							$H_No = $row['H_No'];
							$St_Name = $row['St_Name'];
							$State = $row['State'];
							$Zip = $row['Zip'];
							$Locality = $row['Locality'];
							
							echo "<li><a data-role=\"button\" href=\"details.php?id=$ID\" data-transition=\"slide\">$Name($City)</a></li>";
				}
			}
			else{
				echo "No Results found for $search_val.<BR><BR>";
			}
		}	
		?>
			</ul>
		
		<img id="loading" src="images/loading.gif" style="visibility:hidden">		
		</td>
	</tr>
</table>
	</p>		
		
	</div>
		<!--<div data-role="footer" data-theme="b" data-position="fixed" >
			<div data-role="navbar">

			</div>
		</div>-->
		
		
		
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