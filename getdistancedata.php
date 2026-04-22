<?php
$yourLatitude = floatval(urldecode($_GET['yourlat']));
$yourLongitude = floatval(urldecode($_GET['yourlong']));
$distance = urldecode($_GET['distance']);
//echo $yourLatitude."<BR>".$yourLongitude;
//$distance = 2;

    header("Content-type: text/javascript");
    /* our multidimentional php array to pass back to javascript via ajax */
    include("connection.php.ini");
	mysqli_select_db($con, $db);
	
	$myquery = "SELECT * FROM Addresses2 where Coordinates != '' and Coordinates != ','  and Coordinates != 'NA' order by Name";
	//echo "$myquery<BR><BR>";

	
	$result = mysqli_query($con, $myquery);
	$num_rows = mysqli_num_rows($result);
	//echo $num_rows."<BR>";
	$string = "";
	$output = array();
	//echo count($output);
	$j=1;
	while($row = mysqli_fetch_array($result)){
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
//		echo $Name."<BR>";


        $Coord_array = explode(',', $Coordinates);
        $address_Latitude = floatval($Coord_array[0]);
        $address_Longitude = floatval($Coord_array[1]);
		//echo $address_Latitude ." ". $address_Longitude."<BR>";
        $distanceFromYou = distance($address_Latitude, $address_Longitude, $yourLatitude, $yourLongitude, $distance);
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
			"Comments" => $Comments			
		);

		//if($j < 5){
        //    //echo "$address_Latitude, $address_Longitude, $yourLatitude, $yourLongitude<BR>";
        //    echo "Distance from you:  $distanceFromYou Miles<br>";
        //}
        if($distanceFromYou <= $distance){
            //echo $distanceFromYou."<BR>";
            array_push($output, $myarray);
        }
        $j++;
	}

	//SEND EMAIL
    $newmessage = "$myquery - Count = $num_rows - passed Lat = $yourLatitude and passed long = $yourLongitude";
    $date = new DateTime();
    $mydate = $date->format( 'd/m/Y H:i:s' );
    //mail("qazi.iqbal@gmail.com",$mydate ,$newmessage, null);

    /* encode the array as json. this will output [{"first_name":"Darian","last_name":"Brown","age":"28","email":"darianbr@example.com"},{"first_name":"John","last_name":"Doe","age":"47","email":"john_doe@example.com"}] */
    echo json_encode($output);
	//var_dump($myarray);

function distance($lat1, $lon1, $lat2, $lon2, $unit) {

    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    $unit = strtoupper($unit);

    if ($unit == "K") {
        return ($miles * 1.609344);
    } else if ($unit == "N") {
        return ($miles * 0.8684);
    } else {
        return $miles;
    }
}


?>
