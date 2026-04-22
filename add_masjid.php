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
				<form name="myForm" id="masjidForm" method="POST" action="add_masjidData.php">
				<h3>Fill Your Information</h3>
					<table width="90%" style="border-collapse:collapse" border="1">
						<tr>
							<td bgcolor="#F5F5DC"><label for="Masjid_Name">Masjid Name</label></td><td><input id='Masjid_Name' name='Masjid_Name' value=''></td>
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
                            <td bgcolor="#F5F5DC"><label for="Halaqa">Halaqa Area</label></td>
                            <td>
                                <select name="Halaqa" id="Halaqa" data-native-menu="true" data-mini="true">
                                    <option value="Atlanta East">Atlanta East</option>
                                    <option value="Atlanta West">Atlanta West</option>
                                </select>
                            </td>
                        </tr>
						<tr>
							<td bgcolor="#F5F5DC">Verified</td>
							<td>
								<select name="Verified" id="Verified" data-native-menu="true" data-mini="true">
										<option value="N0">No</option>
										<option value="Yes">Yes</option>
								</select>
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
		
		//var contact = $("#contact").val();
		// Returns successful data submission message when the entered information is stored in database.
		var dataString = 'Name='+ Name + '&Halaqa='+ Halaqa + '&H_No='+ H_No + '&Apt_No='+ Apt_No + '&St_Name='+ St_Name ;
		dataString += '&City='+ City + '&State='+ State + '&Zip='+ Zip + '&Comments='+ Comments;
		
		
		if(Name==''||Halaqa==''||H_No==''||St_Name==''||City==''||State==''||Zip=='')
		{
			alert("Please Fill Name, Halaqa, H_No, St_Name, City, State and Zip");
		}
		else
		{
			$("#masjidForm").submit();
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
        $("#masjidForm").submit(function()
        {
         alert('Form is submitting');
         return true;
        });     
        $("#masjidForm").submit(); //invoke form submission
 
    });
});
</script>
</html>