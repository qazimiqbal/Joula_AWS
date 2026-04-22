<?php
    /* set out document type to text/javascript instead of text/html */
    header("Content-type: text/javascript");
    /* our multidimentional php array to pass back to javascript via ajax */
    include("connection.php.ini");
	mysql_select_db($db, $con);
	//$condition = "";
	$myquery;
	if($_GET['search_val'] <> ""){
		$search_val = strtolower($_GET['search_val']);
		
		//echo $search_val;
		//$condition = "and Name like '%$search_val%'";
		$myquery = "SELECT * FROM Addresses2 where Coordinates != '' and Name like '%$search_val%' order by Name";
		//mail('qazi.iqbal@gmail.com', 'My Subject', $myquery);
	}
	else{
		//echo "Hello";
	//	$condition = "";
		//mail('qazi.iqbal@gmail.com', 'My Subject', "Nothing");
		$myquery = "SELECT * FROM Addresses2 where Coordinates != '' order by Name LIMIT 2 ";
	}
	//echo $myquery;
	//$myquery = "SELECT * FROM Addresses2 where Coordinates != '' $condition order by Name ";
	
	//mail('qazi.iqbal@gmail.com', 'My Subject', $myquery);
	
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
				$myarray = array(
                    "ID" => $ID,
                    "Name" => $Name,
					"City" => $City,
					"Coordinates" => $Coordinates,
					"H_No" => $H_No,
					"St_Name" => $St_Name,
					"State" => $State,
					"Zip" => $Zip					
				);
				array_push($output, $myarray);
	}
	
    /* encode the array as json. this will output [{"first_name":"Darian","last_name":"Brown","age":"28","email":"darianbr@example.com"},{"first_name":"John","last_name":"Doe","age":"47","email":"john_doe@example.com"}] */
    echo json_encode($output);

    ?>