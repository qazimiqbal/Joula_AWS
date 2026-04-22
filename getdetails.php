<?php
	$myid = $_GET['id'];
    /* set out document type to text/javascript instead of text/html */
    header("Content-type: text/javascript");
    /* our multidimentional php array to pass back to javascript via ajax */
    include("connection.php.ini");
	mysqli_select_db($con, $db);
	$myquery = "SELECT * FROM Addresses2 where ID = $myid";
	$result = mysqli_query($con, $myquery);
	$num_rows = mysqli_num_rows($result);
	$string = "";
	//echo count($output);
	while($row = mysqli_fetch_array($result)){
				$ID = $row['ID'];
				$Name = $row['Name'];
				$Halaqa = $row['Halaqa'];
				$H_No = $row['H_No'];
				$Apt_No = $row['Apt_No'];
				$St_Name = $row['St_Name'];
				$City = $row['City'];
				$State = $row['State'];
				$Zip = $row['Zip'];
				$Verified = $row['Verified'];
				$No_Male = $row['No_Male'];
				$No_Female = $row['No_Female'];
				$No_Children = $row['No_Children'];
				$Area = $row['Area'];
				$Zone = $row['Zone'];
				$Comments = $row['Comments'];
				$Last_Visit = $row['Last_Visit'];
				
				$Address = $H_No." ".$Apt_No." ".$St_Name." ".$City." ".$State." ".$Zip;
				
				//array_push($output, $myarray);
				$string .= $ID."*".$Name."*".$Halaqa."*".$Address."*".$Verified."*".$No_Male."*".$No_Female."*".$No_Children."*".$Area."*".$Zone."*".$Comments."*".$Last_Visit;
	}
    echo json_encode($string);
?>