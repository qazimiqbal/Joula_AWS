<?php 

	session_start();

	if (!isset($_SESSION['username']))

	{

	header('Location: index.php');

	}

	require_once("header.inc.php");	

	include_once("classes/geocoder.php");	

?>



<div data-role="page" id="pageone">

	<div data-role="header"  data-theme="b">

		<a href="index.php" class="ui-btn ui-corner-all ui-shadow ui-icon-home ui-btn-icon-left">Home</a>

    <h1>Data Result Page</h1>

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



  <div data-role="main" class="ui-content" data-transition="slide"  data-position="fixed" data-role="button" data-theme="b">

    <center>

			

		<?php

			include("connection.php.ini");

			$Action = $_GET['Action'];

			$myID = $_GET['ID'];

			$Visitinfo = $_GET['Visitinfo'];

			//echo $Action;

			if($Action == "lastvisit"){

				//changeLastVisit($myID);

				$today = date("Y-m-d");



                mysqli_select_db($con, $db);

				$select = "Select Comments, Ethinicity, Potential from $table where ID = $myID";

				//echo $select;

				$result = mysqli_query($con, $select);

				while($row = mysqli_fetch_array($result))

				{				

					$comments = $row['Comments'];

					$Ethinicity = $row['Ethinicity'];	

					$Potential = $row['Potential'];						

				}

				

				//echo $comments;

				

				

				?>

				<form method="GET">

				  <div class="ui-field-contain">

					<label for="today">Today Date:</label>

					<input type="text" name="today" value="<?php echo $today; ?>">

					<label for="R1_comments">Action Taken:</label>

					<select name="R1_comments" id="R1_comments">

					  <option value="met">Met</option>

					  <option value="left_message">Left Message</option>

					  <option value="No_Response">No Response</option>

					  <option value="Ismailee">Ismailee</option>

					  <option value="Owner_muslim_rented_non_muslim">Owner Muslim, Rented to Non Muslim</option>

					  <option value="Non_muslim">Non Muslim</option>

					  <option value="WrongAddress">Wrong Address</option>

					</select>

					

					<label for="Ethinicity">Ethinicity:</label>

					<select name="Ethinicity" id="Ethinicity">

						<option value="Others" <?php if($Ethinicity == "Others"){echo "SELECTED";} ?>>Others</option>

						<option value="African" <?php if($Ethinicity == "African"){echo "SELECTED";} ?>>African</option>

						<option value="American" <?php if($Ethinicity == "American"){echo "SELECTED";} ?>>American</option>

						<option value="Arab" <?php if($Ethinicity == "Arab"){echo "SELECTED";} ?>>Arab</option>

						<option value="Bengali" <?php if($Ethinicity == "Bengali"){echo "SELECTED";} ?>>Bengladeshi</option>

						<option value="Bosnian" <?php if($Ethinicity == "Bosnian"){echo "SELECTED";} ?>>Bosnian</option>

						<option value="Indian" <?php if($Ethinicity == "Indian"){echo "SELECTED";} ?>>Indian</option>

						<option value="Pakistani" <?php if($Ethinicity == "Pakistani"){echo "SELECTED";} ?>>Pakistani</option>

						<option value="Spanish" <?php if($Ethinicity == "Spanish"){echo "SELECTED";} ?>>Spanish</option>						 

					</select>

					<label for="Potential">Potential:</label>

					<select name="Potential" id="Potential">

					  <option value="No" <?php if($Potential == "No"){echo "SELECTED";} ?>>No</option>	

					  <option value="Yes" <?php if($Potential == "Yes"){echo "SELECTED";} ?>>Yes</option>					  				  

					</select>

					<label for="textarea-a">Comments:</label>

					<textarea name="comments" id="comments"><?php echo $comments; ?></textarea>

				  </div>

				  <input type="hidden" name="ID" value="<?php echo $myID; ?>">

				  <input type="hidden" name="Visitinfo" value="True">

				  <input type="submit" data-inline="true" value="Submit">

				</form>

				<?php

			}	

			if($Visitinfo == "True"){

				$myID = $_GET['ID'];

				$today = $_GET['today'];

				$R1_comments = trim($_GET['R1_comments']);

				$comments = trim($_GET['comments']);

				$Ethinicity = $_GET['Ethinicity'];

				$Potential = $_GET['Potential'];

                mysqli_select_db($con, $db);

				$select = "Select R1 from Addresses2 where ID = $myID";

				$result = mysqli_query($con, $select);

				while($row = mysqli_fetch_array($result))

				{				

					$Firstmeet = $row['R1'];	

				}

				

				//echo $Firstmeet."<BR>";

				

				changeLastVisit($myID, $today,$R1_comments,$comments,$Ethinicity,$Firstmeet,$Potential);

			}

			if($Action == "Edit"){

				UpdateRecord($myID);

			}

			if($Action == "Add"){

				

				AddRecord();

			}

			function changeLastVisit($myID,$today,$R1_comments, $comments,$Ethinicity,$Firstmeet,$Potential){

				//echo "$comments<BR>";

                include("connection.php.ini");

                mysqli_select_db($con, $db);

                $R1_comments = trim($R1_comments);

				if($R1_comments == "met"){ $status_info = ", Status = 'Muslim', Verified = 'Y'";}

				if($R1_comments == "left_message"){$status_info = ", Status = 'Muslim', Verified = 'Y'";}

				if($R1_comments == "No_Response"){$status_info = ", Verified = 'N'";}

				if($R1_comments == "Ismailee"){$status_info = ", Status = 'Ismailee', Verified = 'Y'";}

				if($R1_comments == "Owner_muslim_rented_non_muslim"){$status_info = ", Status = 'Owner_Muslim', Verified = 'Y'";}

				if($R1_comments == "Non_muslim"){$status_info = ", Status = 'Non_muslim', Verified = 'Y'";}

				//echo $status_info."<BR>TEst";

				if($Firstmeet == trim("0000-00-00")){

					$sql = "Update Addresses2 set Last_Visit = '$today', R1_comments =  '$R1_comments', Comments = '$comments' ,Ethinicity ='$Ethinicity', Potential = '$Potential', R1 = '$today', R1_comments = '$comments'  $status_info where  ID = $myID";

					//echo "First Meet<BR>$status_info<BR>";

				}

				else{

					$sql = "Update Addresses2 set Last_Visit = '$today', R1_comments =  '$R1_comments', Comments = '$comments', Ethinicity ='$Ethinicity', Potential = '$Potential'  $status_info where  ID = $myID";

				}

				

				//echo $sql;

				

				$result = mysqli_query($con, $sql);

				echo "<b>Record Last Visit Info has been updated</b><BR>";

			 

			} 

			function UpdateRecord($myID){

				$Name=$_GET['Name'];

				$Halaqa=$_GET['Halaqa'];

				$H_No=$_GET['H_No'];

				$Apt_No=$_GET['Apt_No'];

				$St_Name=$_GET['St_Name'];

				$City=$_GET['City'];

				$State=$_GET['State'];

				$Zip=$_GET['Zip'];

				$Verified=$_GET['Verified'];

				$No_Male=$_GET['No_Male'];

				$No_Female=$_GET['No_Female'];

				$No_Children=$_GET['No_Children'];

				$Four_M_Men=$_GET['Four_M_Men'];

				$Forty_D_Men=$_GET['Forty_D_Men'];

				$Ten_D_Men=$_GET['Ten_D_Men'];

				$Three_D_Men=$_GET['Three_D_Men'];

				$Forty_D_Female=$_GET['Forty_D_Female'];

				$Ten_D_Female=$_GET['Ten_D_Female'];

				$Three_D_Female=$_GET['Three_D_Female'];

				$Home_Taleem =$_GET['Home_Taleem'];

				$Area =$_GET['Area'];

				$Locality=urldecode($_GET['Locality']);

				$Zone =$_GET['Zone'];

				$Masjid =$_GET['Masjid'];

				$Last_Visit =$_GET['Last_Visit'];

				$Comments=$_GET['Comments'];			

			

				

				include("connection.php.ini");

                mysqli_select_db($con, $db);

				



				

				$sql = "Update Addresses2 set Name = '$Name',Halaqa = '$Halaqa',H_No  = '$H_No', Apt_No = '$Apt_No', St_Name = '$St_Name', City = '$City', State = '$State', Zip = '$Zip',";

				$sql .= "Verified = '$Verified', No_Male = '$No_Male', No_Female = '$No_Female', No_Children = '$No_Children', Four_M_Men = '$Four_M_Men', Forty_D_Men = '$Forty_D_Men',";

				$sql .= "Ten_D_Men  = '$Ten_D_Men', Three_D_Men  = '$Three_D_Men', Forty_D_Female = '$Forty_D_Female', Ten_D_Female = '$Ten_D_Female', Three_D_Female = '$Three_D_Female',";

				$sql .= " Home_Taleem = '$Home_Taleem', Area = '$Area', Locality = '$Locality', Zone = '$Zone', Masjid = '$Masjid', Last_Visit = '$Last_Visit', Comments = '$Comments' where  ID = $myID";

				

				//echo $sql;

				$result = mysqli_query($con, $sql);

				echo "<center>Thanks<br><b>Record has been updated</b><BR></center>";

			 

			} 

			

			function AddRecord(){

					

				

				$Name=$_GET['Name'];

				$Halaqa=$_GET['Halaqa'];

				$H_No=$_GET['H_No'];

				$Apt_No=$_GET['Apt_No'];

				$St_Name=$_GET['St_Name'];

				$City=$_GET['City'];

				$State=$_GET['State'];

				$Zip=$_GET['Zip'];

				$Verified=$_GET['Verified'];

				$No_Male=$_GET['No_Male'];

				$No_Female=$_GET['No_Female'];

				$No_Children=$_GET['No_Children'];

				$Four_M_Men=$_GET['Four_M_Men'];

				$Forty_D_Men=$_GET['Forty_D_Men'];

				$Ten_D_Men=$_GET['Ten_D_Men'];

				$Three_D_Men=$_GET['Three_D_Men'];

				$Forty_D_Female=$_GET['Forty_D_Female'];

				$Ten_D_Female=$_GET['Ten_D_Female'];

				$Three_D_Female=$_GET['Three_D_Female'];

				$Home_Taleem =$_GET['Home_Taleem'];

				$Area =$_GET['Area'];

				$Locality=urldecode($_GET['Locality']);

				$Zone =$_GET['Zone'];

				$Masjid =$_GET['Masjid'];

				$Last_Visit =$_GET['Last_Visit'];

				$Comments=$_GET['Comments'];

				

				

				$geo = new geocoder();

				$geoAddress = $H_No." ".$St_Name." ".$City." ".$State." ".$Zip;			

				$address=urlencode($geoAddress);

				$result = $geo->getLocation($address);

				$Lat = $result["lat"];

				$Lon = $result["lng"];

				$mycoordinates = $Lat.",".$Lon;



                //Start here



                echo $address;





                //static private $url = "http://maps.google.com/maps/api/geocode/json?sensor=false&key=AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8&address=";

                //$url = "https://maps.google.com/maps/api/geocode/json?key=AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8&address=".$address;

                //$url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key=AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8&sensor=false";

                $url = "https://maps.google.com/maps/api/geocode/json?key=AIzaSyAf-iWek9Zn3J00tIgYVcz0FDuTQFe_TD8&address=1600+Amphitheatre+Parkway,+Mountain+View,+CA&sensor=true";

		echo "<BR>$url<BR>";
                // get the json response

                $resp_json = file_get_contents($url);

				echo "<BR>---<BR>";

                // decode the json

                $resp = json_decode($resp_json, true);
				echo "<BR>".$resp['status']."<BR>"; 



                // response status will be 'OK', if able to geocode given address

                if($resp['status']=='OK'){

echo "Test1";

                    // get the important data

                    $lati = isset($resp['results'][0]['geometry']['location']['lat']) ? $resp['results'][0]['geometry']['location']['lat'] : "";

                    $longi = isset($resp['results'][0]['geometry']['location']['lng']) ? $resp['results'][0]['geometry']['location']['lng'] : "";

                    $formatted_address = isset($resp['results'][0]['formatted_address']) ? $resp['results'][0]['formatted_address'] : "";



                    // verify if data is complete

                    if($lati && $longi && $formatted_address){



                        // put the data in the array

                        $data_arr = array();



                        array_push(

                            $data_arr,

                            $lati,

                            $longi,

                            $formatted_address

                        );



                        echo $data_arr[0];



                    }else{

                        echo "No Address";

                    }



                }



                else{

                    echo "Test2<br>";

                    echo "<strong>ERROR: {$resp['status']}</strong>";

                    //return false;

                }



                echo $mycoordinates;

                exit;

				//end here

				

				

				

				include("connection.php.ini");

                mysqli_select_db($con, $db);

				



				

				$sql = "Insert into Addresses2  (Name, Halaqa ,H_No, Apt_No , St_Name , City, State , Zip, Verified , No_Male , No_Female , No_Children , Four_M_Men , Forty_D_Men,";

				$sql .= "Ten_D_Men , Three_D_Men , Forty_D_Female, Ten_D_Female, Three_D_Female, Home_Taleem , Area, Locality, Zone, Masjid, Last_Visit, Comments, Coordinates) values";

				$sql .= "('$Name','$Halaqa','$H_No','$Apt_No','$St_Name','$City','$State','$Zip','$Verified','$No_Male','$No_Female','$No_Children','$Four_M_Men','$Forty_D_Men',";

				$sql .= " '$Ten_D_Men','$Three_D_Men','$Forty_D_Female','$Ten_D_Female','$Three_D_Female','$Home_Taleem','$Area','$Locality','$Zone','$Masjid','$Last_Visit','$Comments','$mycoordinates')";

				

				//echo $sql;

				$result = mysqli_query($con, $sql);

				echo "<center>Thanks<br><b>Record has been updated</b><BR></center>";

			 

			} 

		?>

			

		

	</center>

  </div>



  <div data-role="footer"   data-position="fixed" data-theme="b">

    <h1>Footer Text</h1>	

  </div>

</div> 

</body>

</html>