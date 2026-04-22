<center><br><form action="index.php">
    <input type="submit" value="Go Back" />
</form></center>
<?php
$locality = urldecode($_POST['Locality']);
//$locality = urlencode($locality);

//echo $locality."<BR>";
//    if($_SESSION['permissions_level'] == 3) {
        include("../connection.php.ini");
        mysqli_select_db($con, $db);
       // $admin_query = "SELECT * FROM Masjids where Verified = 'Yes'";
        $admin_query = "SELECT * FROM Addresses2 where Locality = '$locality'  order by NAME ";
//        echo $admin_query;
        $admin_result = mysqli_query($con, $admin_query);

//        $num_rows = mysqli_num_rows($admin_result);
//        if ($num_rows > 0) {
//            echo "<a href='https://www.myjoula.com/mobile/verify_Masjid.php' data-transition='slide' data-role='button' data-mini='true' data-theme='b' rel='external'>Verify New Masjid</a>";
//        }


?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<!--    <link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">-->
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
<!--    <script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>-->
<!--    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">-->

    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.10.0/bootstrap-table.min.css">
    <link rel="stylesheet" href="//rawgit.com/vitalets/x-editable/master/dist/bootstrap3-editable/css/bootstrap-editable.css">

    <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>

    <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.10.0/bootstrap-table.js"></script>

    <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.9.1/extensions/editable/bootstrap-table-editable.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.9.1/extensions/export/bootstrap-table-export.js"></script>
    <script src="//rawgit.com/hhurz/tableExport.jquery.plugin/master/tableExport.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.9.1/extensions/filter-control/bootstrap-table-filter-control.js"></script>


    <!--Here -->
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.12/css/dataTables.bootstrap.min.css'>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,300i,500" rel="stylesheet"/>
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="styles.css">
<Script>
    function nameFormatter(value, row) {

        var icon = row.id % 2 === 0 ? 'fa-star' : 'fa-star-and-crescent'
        return '<a class="fa" href=Reportfilter.php?value="' + value + '">'+ value +'</a>'
    }
</Script>
<style>
    td{
        position:relative;
    }
    td a{
        display: block;
        text-align: center;
        padding: 10em;
        margin: -10em;
    }
</style>



</head>
<Body>
<!-- Start -->
<div class="row">
    <div class="container">

        <h1>Report for <?php echo $locality; ?> Locality</h1>
<!--        <table class="table responsive" id="sort">-->
            <table id="sort"
                           data-toggle="table"
                           data-search="false"
                           data-filter-control="true"
                           data-show-export="true"
                           data-click-to-select="true"
                           data-toolbar="#toolbar"
                           class="table-responsive">

            <thead>
            <tr>
                <th data-field="state" data-checkbox="true"></th>
                <th scope="col" >Name</th>
                <th scope="col">Address</th>
                <th scope="col">Ethinicity</th>
                <th scope="col">Comments</th>
                <th scope="col">Last Visit</th>
<!--                <th data-field="action" data-formatter="nameFormatter" >Coordinates</th>-->
                <th  >Coordinates</th>
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
                    //$Showstring = "<a href='pageToGoTo.html' title='Page to go to' class='whatEver'>Anchor text</a>";//$link;
                    $Showstring = "Needs Geocoding";
                }
                // {$Showstring = "<a href='https://www.myjoula.com/mobile/verify_Masjid.php' data-transition='slide' data-role='button' data-mini='true' data-theme='b' rel='external'>Verify New Masjid</a>";}
                else{$Showstring = $Coordinates;}
                //echo $Name."<BR>";
                ?>

             <tr class="clickable "
                 onclick="window.location='https://www.studytonight.com/'">
                 <td class='bs-checkbox '><input data-index='0' name='btSelectItem' type='checkbox'></td>
                 <td><?php echo $Name; ?></td>
                  <td><?php echo $Address; ?></td>
                 <td> <?php  echo $Ethinicity; ?></td>
                 <td><?php  echo $Comments; ?></td>
                  <td><?php  echo $Last_visit; ?></td>
                 <td><?php  echo $Showstring; ?></td>
                 
            </tr>
            <?php
            }

            ?>

            </tbody>
        </table>
    </div>
</div>






<!-- End -->



<!--<div class="container">-->
<!--    <h1>Locality Report</h1>-->
<!---->
<!--    <p>This is the report for locality --><?php //echo $locality; ?><!--</p>-->
<!---->
<!--    <div id="toolbar">-->
<!--        <select class="form-control">-->
<!--            <option value="">Export Basic</option>-->
<!--            <option value="all">Export All</option>-->
<!--            <option value="selected">Export Selected</option>-->
<!--        </select>-->
<!--    </div>-->
<!---->
<!--    <table id="table"-->
<!--           data-toggle="table"-->
<!--           data-search="true"-->
<!--           data-filter-control="true"-->
<!--           data-show-export="true"-->
<!--           data-click-to-select="true"-->
<!--           data-toolbar="#toolbar"-->
<!--           class="table-responsive">-->
<!--        <thead>-->
<!--        <tr>-->
<!--            <th data-field="state" data-checkbox="true"></th>-->
<!--            <th data-field="prenom" data-filter-control="input" data-sortable="true">Name</th>-->
<!--            <th data-field="date" data-filter-control="input" data-sortable="true">Address</th>-->
<!--            <th data-field="examen" data-filter-control="input" data-sortable="true">Ethinicity</th>-->
<!--            <th data-field="examend" data-sortable="true">Locality</th>-->
<!--            <th data-field="note" >Update Coordinates</th>-->
<!--        </tr>-->
<!--        </thead>-->
<!--        <tbody>-->
<!--        --><?php
//        while($row = mysqli_fetch_array($admin_result)) {
//            $ID = $row['ID'];
//            $Name = $row['Name'];
//            $City = $row['City'];
//            $Coordinates = $row['Coordinates'];
//            $H_No = $row['H_No'];
//            $St_Name = $row['St_Name'];
//            $State = $row['State'];
//            $Zip = $row['Zip'];
//            $Ethinicity = $row['Ethinicity'];
//            $Status = $row['Status'];
//            $R1_comments = $row['R1_comments'];
//            $Comments = $row['Comments'];
//            $Four_M_Men = $row['Four_M_Men'];
//            $Forty_D_Men = $row['Forty_D_Men'];
//            $Ten_D_Men = $row['Ten_D_Men'];
//            $Three_D_Men = $row['Three_D_Men'];
//            $Forty_D_Female = $row['Forty_D_Female'];
//            $Ten_D_Female = $row['Ten_D_Female'];
//            $Three_D_Female = $row['Three_D_Female'];
//            $Masjid = $row['Masjid'];
//            $Locality = $row['Locality'];
//
//            $Address = "$H_No $St_Name $State $Zip";
//            $link = "<a href='https://www.myjoula.com/mobile/verify_Masjid.php'>Click Me</a>";
//
//
//            if ($Coordinates == "NA" || substr( $Coordinates, 0, 2 ) === "00" || $Coordinates == ""){
//                //$Showstring ="<a href='pageToGoTo.html' title='Page to go to' class='whatEver'>Anchor text</a>";//$link;
//                $Showstring ="Needs Geocoding";
//            }
//           // {$Showstring = "<a href='https://www.myjoula.com/mobile/verify_Masjid.php' data-transition='slide' data-role='button' data-mini='true' data-theme='b' rel='external'>Verify New Masjid</a>";}
//            else{$Showstring = $Coordinates;}
//            //echo $Name."<BR>";
//            echo "
//             <tr>
//                 <td class='bs-checkbox '><input data-index='0' name='btSelectItem' type='checkbox'></td>
//                 <td>$Name</td>
//                  <td>$Address</td>
//                 <td>$Ethinicity</td>
//                 <td>$Locality</td>
//                 <td>$Showstring</td>
//            </tr>";
//        }
//
//        ?>
<!---->
<!--        </tbody>-->
<!--    </table>-->
<!--</div>-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.12/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.12/js/dataTables.bootstrap.min.js"></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.18.0/moment.min.js'></script>
<script>
    // Code By Webdevtrick ( https://webdevtrick.com )
    $(document).ready(function() {
        $("#sort").DataTable({
            columnDefs : [
                { type : 'date', targets : [3] }
            ],
        });
    });
</script>
</Body>
</html>
