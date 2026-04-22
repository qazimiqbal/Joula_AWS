<center><br><form action="index.php">
    <input type="submit" value="Go Back" />
</form></center>
<?php
$locality = urldecode($_POST['Locality']);
$locality = "Omar";

//echo $locality."<BR>";
//    if($_SESSION['permissions_level'] == 3) {
        include("../connection.php.ini");
        mysqli_select_db($con, $db);
       // $admin_query = "SELECT * FROM Masjids where Verified = 'Yes'";
        $admin_query = "SELECT * FROM Addresses2 where Locality = '$locality'  order by NAME ";
//        echo $admin_query;
        $admin_result = mysqli_query($con, $admin_query);

?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js"></script>

    <link href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css" rel="stylesheet"/>
    <link href="https://cdn.datatables.net/buttons/2.3.2/css/buttons.dataTables.min.css" rel="stylesheet"/>

    <script>
        $(document).ready(function() {
            $('#example').DataTable( {
                dom: 'Bfrtip',
                buttons: [
                    'copyHtml5',
                    'excelHtml5',
                    'csvHtml5',
                    'pdfHtml5'
                ],
                "lengthMenu": [[5, 10, 20, -1], [5, 10, 20, "All"]]
            } );
        } );
    </script>

</head>
<Body>
<!-- Start -->
<div class="row">
    <div class="container">

        <h1>Report for <?php echo $locality; ?> Locality</h1>
            <table id="example"  class="display" style="width:100%">

            <thead>
            <tr>
                <th data-field="state" data-checkbox="true"></th>
                <th>Name</th>
                <th>Address</th>
                <th>Ethinicity</th>
                <th>Comments</th>
                <th>Last Visit</th>
                <th>Coordinates</th>
            </tr>
            </thead>
            <tbody>
            <?php
            while($row = mysqli_fetch_array($admin_result)) {
                $ID = $row['ID'];
                $Name = $row['Name'];
                $City = $row['City'];
                $Coordinates = $row['Coordinates'];
                $H_No = $row['H_No'];
                $St_Name = $row['St_Name'];
                $State = $row['State'];
                $Zip = $row['Zip'];
                $Ethinicity = $row['Ethinicity'];
                $Status = $row['Status'];
                $Last_visit = $row['Last_Visit'];
                $R1_comments = $row['R1_comments'];
                $Comments = $row['Comments'];
                $Four_M_Men = $row['Four_M_Men'];
                $Forty_D_Men = $row['Forty_D_Men'];
                $Ten_D_Men = $row['Ten_D_Men'];
                $Three_D_Men = $row['Three_D_Men'];
                $Forty_D_Female = $row['Forty_D_Female'];
                $Ten_D_Female = $row['Ten_D_Female'];
                $Three_D_Female = $row['Three_D_Female'];
                $Masjid = $row['Masjid'];
                $Locality = $row['Locality'];

                $Address = "$H_No $St_Name $State $Zip";
                $link = "<a href='https://www.myjoula.com/mobile/verify_Masjid.php'>Click Me</a>";


                if ($Coordinates == "NA" || substr( $Coordinates, 0, 2 ) === "00" || $Coordinates == ""){
                    //$Showstring ="<a href='pageToGoTo.html' title='Page to go to' class='whatEver'>Anchor text</a>";//$link;
                    $Showstring ="Needs Geocoding";
                }
                // {$Showstring = "<a href='https://www.myjoula.com/mobile/verify_Masjid.php' data-transition='slide' data-role='button' data-mini='true' data-theme='b' rel='external'>Verify New Masjid</a>";}
                else{$Showstring = $Coordinates;}
                //echo $Name."<BR>";
                echo "
             <tr>
                 <td class='bs-checkbox '><input data-index='0' name='btSelectItem' type='checkbox'></td>
                 <td>$Name</td>
                  <td>$Address</td>
                 <td>$Ethinicity</td>
                 <td>$Comments</td>
                  <td>$Last_visit</td>
                 <td>$Showstring</td>
            </tr>";
            }

            ?>

            </tbody>
        </table>
    </div>
</div>

</Body>
</html>
