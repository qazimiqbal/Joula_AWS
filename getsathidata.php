<?php
$yourLatitude = floatval(urldecode($_GET['yourlat']));
$yourLongitude = floatval(urldecode($_GET['yourlong']));
$state = urldecode($_GET['state']);
if($state != "All"){
	$state_condition = "and State = '$state'";
}
else{
	$state_condition = "";	
}
//echo $yourLatitude."<BR>".$yourLongitude;
//$distance = 2;

    header("Content-type: text/javascript");
    /* our multidimentional php array to pass back to javascript via ajax */
    include("connection.php.ini");
	mysql_select_db($db, $con);
	
	$myquery = "SELECT * FROM Addresses2 where Coordinates != '' and Coordinates != ','  and Coordinates != 'NA' and (Four_M_Men != 0 or Forty_D_Men != 0 or Ten_D_Men != 0 or Three_D_Men != 0 or Forty_D_Female != 0 or Ten_D_Female != 0  or Three_D_Female != 0) $state_condition order by Name";
	//echo "$myquery<BR><BR>";

	
	$result = mysql_query($myquery);
	$num_rows = mysql_num_rows($result);
	//echo $num_rows."<BR>";
	$string = "";
	$output = array();
	//echo count($output);
	$j=1;
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
		$Comments = $row['Comments'];
		$Four_M_Men = $row['Four_M_Men'];
		$Forty_D_Men = $row['Forty_D_Men'];
		$Ten_D_Men = $row['Ten_D_Men'];
		$Three_D_Men = $row['Three_D_Men'];
		$Forty_D_Female = $row['Forty_D_Female'];
		$Ten_D_Female = $row['Ten_D_Female'];
		$Three_D_Female = $row['Three_D_Female'];
		//echo $Last_Visit."<BR>";


        $Coord_array = explode(',', $Coordinates);
        $address_Latitude = floatval($Coord_array[0]);
        $address_Longitude = floatval($Coord_array[1]);
		//echo $address_Latitude ." ". $address_Longitude."<BR>";
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
			"R1_comments" => $R1_comments,
			"Comments" => $Comments,	
			"Four_M_Men" => $Four_M_Men,
			"Forty_D_Men" => $Forty_D_Men,
			"Ten_D_Men" => $Ten_D_Men,
			"Three_D_Men" => $Three_D_Men,
			"Forty_D_Female" => $Forty_D_Female,
			"Ten_D_Female" => $Ten_D_Female,
			"Three_D_Female" => $Three_D_Female,
		);

		        array_push($output, $myarray);
        $j++;
	}

	//SEND EMAIL
    $newmessage = "$myquery - Count = $num_rows - passed Lat = $yourLatitude and passed long = $yourLongitude";
    $date = new DateTime();
    $mydate = $date->format( 'd/m/Y H:i:s' );
    //mail("qazi.iqbal@gmail.com",$mydate ,$newmessage, null);

    /* encode the array as json. this will output [{"first_name":"Darian","last_name":"Brown","age":"28","email":"darianbr@example.com"},{"first_name":"John","last_name":"Doe","age":"47","email":"john_doe@example.com"}] */
    echo json_encode($output);
	//echo $output;




?>
