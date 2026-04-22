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
		<h1>New Address</h1>
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
				<form name="myForm" id="testForm" method="POST" action="add_address.php">
				<h3>Fill Your Information</h3>
					<table width="90%" style="border-collapse:collapse" border="1">
						<tr>
							<td bgcolor="#F5F5DC"><label for="name">Name</label></td><td><input id='Name' name='Name' value=''></td>
						</tr>
						<tr>	
							<td bgcolor="#F5F5DC"><label for="Halaqa">Halaqa</label></td><td><input id='Halaqa' name='Halaqa' value='Atlanta East'></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC"><label for="H_No">H_No</label></td><td><input id='H_No' name='H_No' value=''></td>
						</tr>
						<tr>	
							<td bgcolor="#F5F5DC"><label for="Apt_No">Apt_No</label></td><td><input id='Apt_No' name='Apt_No' value=''></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC"><label for="St_Name">St_Name</label></td><td><input id='St_Name' name='St_Name' value=''></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC"><label for="City">City</label></td><td><input id='City' name='City' value=''></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC"><label for="State">State</label></td><td><input id='State' name='State' value='GA'></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC"><label for="Zip">Zip</label></td><td><input id='Zip' name='Zip' value=''></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">Verified</td>
							<td>
								<select name="Verified" id="Verified" data-native-menu="true" data-mini="true">
										<option value="N">No</option>
										<option value="Y">Yes</option>
								</select>
							</td>
						</tr>
						<!--
						<tr>
							<td bgcolor="#F5F5DC">No Of Male</td>
							<td>
								<select name="No_Male" id="No_Male" data-native-menu="true" data-mini="true">
										<option value="0">0</option>
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="5">5</option>
										<option value="6">6</option>
										<option value="7">7</option>
								</select>
							</td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">No Of Female</td>
							<td>
								<select name="No_Female" id="No_Female" data-native-menu="true" data-mini="true">
										<option value="0">0</option>
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="5">5</option>
										<option value="6">6</option>
										<option value="7">7</option>
								</select>
							</td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">No Of Children</td>
							<td>
								<select name="No_Children" id="No_Children" data-native-menu="true" data-mini="true">
										<option value="0">0</option>
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="5">5</option>
										<option value="6">6</option>
										<option value="7">7</option>
										<option value="8">8</option>
								</select>
							</td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">4 Month Men</td>
							<td>
								<select name="Four_M_Men" id="Four_M_Men" data-native-menu="true" data-mini="true">
										<option value="0">0</option>
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="5">5</option>
								</select>
							</td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">40 Day Men</td>
							<td>
								<select name="Forty_D_Men" id="Forty_D_Men" data-native-menu="true" data-mini="true">
										<option value="0">0</option>
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="5">5</option>
								</select>
							</td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">10 Day Men</td>
							<td>
								<input id='Ten_D_Men' name='Ten_D_Men' value='0'></td>
							</td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">3 Day Men</td>
							<td>
								<input id='Three_D_Men' name='Three_D_Men' value='0'></td>
							</td>
						</tr>
						
						<tr>
							<td bgcolor="#F5F5DC">40 Day Ladies</td>
							<td>
								<select name="Forty_D_Female" id="Forty_D_Female" data-native-menu="true" data-mini="true">
										<option value="0">0</option>
										<option value="1">1</option>
										<option value="2">2</option>
										<option value="3">3</option>
										<option value="4">4</option>
										<option value="5">5</option>
								</select>
							</td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">10 Day Ladies</td>
							<td>
								<input id='Ten_D_Female' name='Ten_D_Female' value='0'></td>
							</td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">3 Day Ladies</td>
							<td>
								<input id='Three_D_Female' name='Three_D_Female' value='0'></td>
							</td>
						</tr>
						
						<tr>
							<td bgcolor="#F5F5DC">Home Taleem</td>
							<td>
								<select name="Home_Taleem" id="Home_Taleem" data-native-menu="true" data-mini="true">
										<option value="No">No</option>
										<option value="Yes">Yes</option>
								</select>
							</td>
						</tr>
						-->
						<tr>	
							<td bgcolor="#F5F5DC">Masjid</td>
							<td>
								<input id='Masjid' name='Masjid' value=''>
							</td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">Locality</td>
							<td>
								<?php
									include("connection.php.ini");				
									mysql_select_db($db, $con);
									$mySQL = "SELECT Distinct Locality FROM Addresses2 where Locality != '' order by Locality";
									$result = mysql_query($mySQL);
											?>
								<select name="Locality" id="Locality" data-native-menu="true" data-mini="true">
									<?php
										
											echo "<option value='select' >Select Locality</option>";
											while($row = mysql_fetch_array($result))
											{				
												//if($row['Area'] == $myArea){ $mycheck = "SELECTED";}
												//else{$mycheck = "";};
												//$encode_Area = urlencode($row['Area']);
												$Locality_name = $row['Locality'];
												$Locality = urlencode($row['Locality']);
												if($Locality != ""){
													
													echo "<option value=$Locality >$Locality_name</option>";
												}	
											}	
											?>										
								</select>
							</td>
						</tr>	
						<!--
						<tr>	
							<td bgcolor="#F5F5DC">Area</td><td><input id='Area' name='Area' value=''></td>
						</tr>
						<tr>
							<td bgcolor="#F5F5DC">Zone</td><td><input id='Zone' name='Zone' value=''></td>
						</tr>
						-->
						<tr>
							<td bgcolor="#F5F5DC">Last Visit</td>
							<td>
								<input id='Last_Visit' name='Last_Visit' value=''>
								<!--<input type="text" id="datepicker">
								 -->
								<script type="text/javascript">
									var d = new Date();    								
									var yr = d.getFullYear();
									var mth = d.getMonth() + 1;
									var dy = d.getDay();
									var today = yr+"-"+mth+"-"+dy;
									document.getElementById("Last_Visit").value = today; 
								</script>
							</td>
						</tr>
						
						<tr>
							<td bgcolor="#F5F5DC"><label for="Zip">Comments</label></td><td><textarea rows='40' cols='10' id='Comments' name='Comments' value=''></textarea></td>
						</tr>
						<tr><td  colspan="2" align="middle">
							
						</td></tr>
					</table>
				</form>
				<center>
					<input type="button" id="submit1" value="Submit" /><br><br>
					<!--<input type="button" id="submit2" value="Submit by Form Name" />
					<input type="button" id="submit3" value="Submit by Form Index" />
					<input type="button" id="submit4" value="Submit with Event Handler" />-->
				</center>
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
		var Locality = $("#Locality").val();
		//var contact = $("#contact").val();
		// Returns successful data submission message when the entered information is stored in database.
		var dataString = 'Name='+ Name + '&Halaqa='+ Halaqa + '&H_No='+ H_No + '&Apt_No='+ Apt_No + '&St_Name='+ St_Name ;
		dataString += '&City='+ City + '&State='+ State + '&Zip='+ Zip + '&Comments='+ Comments;
		
		
		if(Name==''||Halaqa==''||H_No==''||St_Name==''||City==''||State=='')
		{
			alert("Please Fill Name, Halaqa, H_No, St_Name, City, State");
		}
		else
		{
			if(Locality == 'select')
			{
				alert("Please select the locality"); 
			}
			else{
				$("#testForm").submit();
			}	
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