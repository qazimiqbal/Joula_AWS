<?php
    /* set out document type to text/javascript instead of text/html */
	$locality = urldecode($_GET['locality']); 
	$area = urldecode($_GET['area']); 
	$test = "Area = ".$area." and Locality = ".$locality;
	if($locality == "All"){$mylocalitycondition = "";}
	else{$mylocalitycondition = "and Locality = '$locality'";}	
	if($area == "All"){$myareacondition = "";}
	else if($area == ""){$myareacondition = "";}
	else{$myareacondition = "and Area = '$area'";}
	$message = "Locality = ".$locality." and Area = ".$area;
	//mail('qazi.iqbal@gmail.com', 'My Subject', $message);
	//echo $mycondition;
    header("Content-type: text/javascript");
    /* our multidimentional php array to pass back to javascript via ajax */
    include("connection.php.ini");
	mysql_select_db($db, $con);
	
	$myquery = "SELECT * FROM Addresses2 where Coordinates != '' and Coordinates != ',' $mylocalitycondition $myareacondition order by Name";
	//echo $myquery;
	$newmessage = $test.$myquery;
	
	$date = new DateTime();
	$mydate = $date->format( 'd/m/Y H:i:s' );	
	//mail("qazi.iqbal@gmail.com",$mydate ,$myquery, null);
	
	$result = mysql_query($myquery);
	$num_rows = mysql_num_rows($result);
	$string = "";
	$output = array();
	//echo count($output);
	while($row = mysql_fetch_array($result)){
				$ID = $row['ID'];
				$Name = $row['Name'];
				$City = $row['City'];
				$Coordinates = $row['Coordinates'];
				$H_No = $row['H_No'];
				$St_Name = $row['St_Name'];
				$State = $row['State'];
				$Zip = $row['Zip'];
				$Last_Visit = $row['Last_Visit'];
				$Status = $row['Status'];
				$R1_comments = $row['R1_comments'];
				//echo $Last_Visit."<BR>";
				$myarray = array(
                    "ID" => $ID,
                    "Name" => $Name,
					"City" => $City,
					"Coordinates" => $Coordinates,
					"H_No" => $H_No,
					"St_Name" => $St_Name,
					"State" => $State,
					"Zip" => $Zip,
					"Last_Visit" => $Last_Visit,
					"Status" => $Status,
					"R1_comments" => $R1_comments						
					
				);
				array_push($output, $myarray);
	}
	
    /* encode the array as json. this will output [{"first_name":"Darian","last_name":"Brown","age":"28","email":"darianbr@example.com"},{"first_name":"John","last_name":"Doe","age":"47","email":"john_doe@example.com"}] */
    echo json_encode($output);
	//echo $output;
?>