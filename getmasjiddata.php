<?php

    /* set out document type to text/javascript instead of text/html */

//	$locality = urldecode($_GET['locality']);

//	$area = urldecode($_GET['area']);

//	$test = "Area = ".$area." and Locality = ".$locality;

//	if($locality == "All"){$mylocalitycondition = "";}

//	else{$mylocalitycondition = "and Locality = '$locality'";}

//	if($area == "All"){$myareacondition = "";}

//	else if($area == ""){$myareacondition = "";}

//	else{$myareacondition = "and Area = '$area'";}

//	$message = "Locality = ".$locality." and Area = ".$area;

	//mail('qazi.iqbal@gmail.com', 'My Subject', $message);

	//echo $mycondition;

    header("Content-type: text/javascript");

    /* our multidimentional php array to pass back to javascript via ajax */

    include("connection.php.ini");

	mysqli_select_db($con, $db);



	$myquery2 = "SELECT * FROM Masjids where Coordinates != '' order by Name";

    //echo "$myquery2";

	$date = new DateTime();

	$mydate = $date->format( 'd/m/Y H:i:s' );	

	//mail("qazi.iqbal@gmail.com",$mydate ,$myquery2, null);

	

	$result2 = mysqli_query($con, $myquery2);

	$num_rows = mysqli_num_rows($result2);

	$string = "";

	$output = array();







	//echo $num_rows."HELLO";

    while($row = mysqli_fetch_array($result2)){

		$ID = $row['ID'];

		$Name = $row['Name'];

		$City = $row['City'];

		$Coordinates = $row['Coordinates'];

		$H_No = $row['H_No'];

		$St_Name = $row['St_Name'];

		$State = $row['State'];

		$Zip = $row['Zip'];

		$Last_Visit = $row['Last_Visit'];

		$R1_comments = $row['Comments'];

		$Verified = $row['Verified'];

		$Halaqa = $row['Halaqa'];

		$Locality = $row['Locality'];

		//echo $Name."<BR>";

		$myarray2 = array(

            "ID" => $ID,

            "Name" => $Name,

			"City" => $City,

			"Coordinates" => $Coordinates,

			"H_No" => $H_No,

			"St_Name" => $St_Name,

			"State" => $State,

			"Zip" => $Zip,

			"Last_Visit" => $Last_Visit,

			"Locality" => $Locality,

			"Status" => "Masjid",

			"R1_comments" => $R1_comments,	

			"Verified" => $Verified,	

			"Halaqa" => $Halaqa

		);

	array_push($output, $myarray2);

	}

    /* encode the array as json. this will output [{"first_name":"Darian","last_name":"Brown","age":"28","email":"darianbr@example.com"},{"first_name":"John","last_name":"Doe","age":"47","email":"john_doe@example.com"}] */

    echo json_encode($output);

	//echo $output;

?>