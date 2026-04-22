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

//        $num_rows = mysqli_num_rows($admin_result);
//        if ($num_rows > 0) {
//            echo "<a href='https://www.myjoula.com/mobile/verify_Masjid.php' data-transition='slide' data-role='button' data-mini='true' data-theme='b' rel='external'>Verify New Masjid</a>";
//        }


?>
<!DOCTYPE html>
<!-- Code By Webdevtrick ( https://webdevtrick.com ) -->
<html lang="en" >
<head>
    <meta charset="UTF-8">
    <title>Bootstrap Datatable | Webdevtrick.com</title>

    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js"></script>
<!--   Original Start -->
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.12/css/dataTables.bootstrap.min.css'>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,300i,500" rel="stylesheet"/>
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="styles.css">
    <!--   Original End -->


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
                ]
            } );
        } );
    </script>

</head>

<body>


<div class="row">
    <div class="container">

        <h1>Bootstrap 3 SortTable</h1>
        <table class="table responsive" id="sort">
            <thead>
            <tr>
                <th scope="col"></th>
                <th scope="col"  data-filter-control="input" data-sortable="true" >Title</th>
                <th scope="col">Authors</th>
                <th scope="col">Journal</th>
                <th scope="col">Date</th>
                <th scope="col">dfds</th>
                <th scope="col">Dasdfdte</th>
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



            <!--            <tr>-->
<!--                <td data-table-header="Title">Parent Adolescent Relationship Factors and Adolescent Outcomes Among High-Risk Families.</td>-->
<!--                <td data-table-header="Authors">Matthew Withers, Lenore McWey, Mallory Lucier-Greer</td>-->
<!--                <td data-table-header="Journal">Family Relations</td>-->
<!--                <td data-table-header="Date">Jan. 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Prescription Drugs and Nutrient Depletion: How Much is Known?</td>-->
<!--                <td data-table-header="Authors">Wendimere Reilly, Jasminka Ilich</td>-->
<!--                <td data-table-header="Journal">Advances in Nutrition: An International Review Journal</td>-->
<!--                <td data-table-header="Date">Jan. 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Relation of Adiponectin with Body Adiposity and Bone Mineral Density in Older Women.</td>-->
<!--                <td data-table-header="Authors">Pegah Jafari Nasabian, Julia Inglis, Miranda Ave, Hayley Hebrock, Katie Hall, Sara Nieto, Jasminka Ilich</td>-->
<!--                <td data-table-header="Journal">Advances in Nutrition: An International Review Journal</td>-->
<!--                <td data-table-header="Date">Jan. 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Benefits of whole-body vibration training on arterial function and muscle strength in young overweight/obese women.</td>-->
<!--                <td data-table-header="Authors">Alvarez-Alvarado S, Jaime SJ, Ormsbee MJ, Campbell JC, Post J, Pacilio J, Figueroa A.</td>-->
<!--                <td data-table-header="Journal">Hypertension Research International Journal</td>-->
<!--                <td data-table-header="Date">Jan. 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Overexpression of PGC-1α Increases Peroxisomal and Mitochondrial Fatty Acid Oxidation in Human Primary Myotubes.</td>-->
<!--                <td data-table-header="Authors">Huang TY, Zheng D, Houmard JA, Brault JJ, Hickner RC, Cortright RN.</td>-->
<!--                <td data-table-header="Journal">American Journal of Physiology: Endocrinology and Metabolism</td>-->
<!--                <td data-table-header="Date">Jan. 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Observed Parenting in Families exposed to Homelessness: Child and Parent Characteristics as Predictors of Response to the Early Risers Intervention.</td>-->
<!--                <td data-table-header="Authors">Kendal Holtrop, Timothy F. Piehler, Abigail H. Gewirtz, Gerald J. August</td>-->
<!--                <td data-table-header="Journal">Child and Family Well-Beging and Homelessness:&nbsp;Integrating&nbsp;Research into Practice and Policy</td>-->
<!--                <td data-table-header="Date">Feb. 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Testing the impact of sliding versus deciding in cyclical and non cyclical relationships.</td>-->
<!--                <td data-table-header="Authors">Charity E. Clifford, Amber Vennum, Michelle Busk, Frank D. Fincham</td>-->
<!--                <td data-table-header="Journal">Personal Relationships:&nbsp;Journal of the International Assoc. for Relationship Research</td>-->
<!--                <td data-table-header="Date">Feb. 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Personal and Cultural Identity Development in Recently Immigrated Hispanic Adolescents: Links With Psychosocial Functioning.</td>-->
<!--                <td data-table-header="Authors">Meca A, Sabet RF, Farrelly CM, Benitez CG, Schwartz SJ,&nbsp;Gonzales-Backen M, Lorenzo-Blanco EI, Unger JB, Zamboanga BL, Baezconde-Garbanati L, Picariello S, Des Rosiers SE, Soto DW, Pattarroyo M, Villamar JA, Lizzi KM. </td>-->
<!--                <td data-table-header="Journal">American Psychological Association: Cultural Diversity &amp; Ethnic Minority Psychology</td>-->
<!--                <td data-table-header="Date">Feb. 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">School burnout and intimate partner violence: The role of self-control.</td>-->
<!--                <td data-table-header="Authors">AN Cooper, GS Seibert, RW May, MC Fitzgerald, FD Fincham</td>-->
<!--                <td data-table-header="Journal">Personality and Individual Differences</td>-->
<!--                <td data-table-header="Date">Feb. 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Efficacy Of The Repetitions In Reserve-Based Rating Of Perceived Exertion For The Bench Press In Experienced And Novice Benchers.</td>-->
<!--                <td data-table-header="Authors">Ormsbee MJ, Carzoli JP, Klemp A, Allman BR, Zourdos MC, Kim JS, Panton LB.</td>-->
<!--                <td data-table-header="Journal">The Journal of Strength and Conditioning Research</td>-->
<!--                <td data-table-header="Date">March 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Exercise training reverses age-induced diastolic dysfunction and restores coronary microvascular function.</td>-->
<!--                <td data-table-header="Authors">Hotta K, Chen B, Behnke BJ, Ghosh P, Stabley JN, Bramy JA, Sepulveda JL, Delp MD, Muller-Delp JM.</td>-->
<!--                <td data-table-header="Journal">The Journal of Physiology</td>-->
<!--                <td data-table-header="Date">March 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Macronutrient Intake and Distribution in the Etiology, Prevention and Treatment of Osteosarcopenic Obesity.</td>-->
<!--                <td data-table-header="Authors">Kelly OJ, Gilman JC, Kim Y, Ilich JZ.</td>-->
<!--                <td data-table-header="Journal">Current Aging Science</td>-->
<!--                <td data-table-header="Date">May 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Perception in Romantic Relationships: a Latent Profile Analysis of Trait Mindfulness in Relation to Attachment and Attributions.</td>-->
<!--                <td data-table-header="Authors">JG Kimmes, JA Durtschi, FD Fincham.</td>-->
<!--                <td data-table-header="Journal">Mindfulness</td>-->
<!--                <td data-table-header="Date">April 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Individual Differences in Adolescents’ Emotional Reactivity across Relationship Contexts.</td>-->
<!--                <td data-table-header="Authors">Cook EC, Blair BL, Buehler C.</td>-->
<!--                <td data-table-header="Journal">Journal of Youth Adolescence</td>-->
<!--                <td data-table-header="Date">April 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Is Plus Size Equal? The Positive Impacts of Average and Plus Sized Media Fashion Models on Women’s Cognitive Resource Allocation, Social Comparisons, and Body Satisfaction. [in press]</td>-->
<!--                <td data-table-header="Authors">RB Clayton, JL Ridgway, J Hendrickse.</td>-->
<!--                <td data-table-header="Journal">Communication Monographs</td>-->
<!--                <td data-table-header="Date">April 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Effects of Tart Cherry Juice on Brachial and Aortic Hemodynamics, Arterial Stiffness, and Blood Biomarkers of Cardiovascular Health in Adults with Metabolic Syndrome.</td>-->
<!--                <td data-table-header="Authors">Sarah Johnson, Negin Navaei, Shirin Pourafshar, Salvador Jaime, Neda Akhavan, Stacey Alvarez-Alvarado, Nicole Litwin, Marcus Elam, Mark Payton, Bahram Arjmandi, Arturo Figueroa.</td>-->
<!--                <td data-table-header="Journal">Journal of Federation of American Societies for Experimental Biology</td>-->
<!--                <td data-table-header="Date">April 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Parenting Styles and College Enrollment: A Path Analysis of Risky Human Capital Decisions.</td>-->
<!--                <td data-table-header="Authors">J Kimmes, S Heckman</td>-->
<!--                <td data-table-header="Journal">Journal of Family and Economic Issues</td>-->
<!--                <td data-table-header="Date">May 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Emerging Adult Relationship Transitions as Opportune Times for Tailored Interventions.</td>-->
<!--                <td data-table-header="Authors">A Vennum, JK Monk, BK Pasley, FD Fincham</td>-->
<!--                <td data-table-header="Journal">Emerging Adulthood</td>-->
<!--                <td data-table-header="Date">May 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Watermelon and L-Arginine Consumption Regulate Gene Expression Related to Serum Lipid Profile, Inflammation, and Oxidative Stress in Rats Fed on Atherogenic Diet.</td>-->
<!--                <td data-table-header="Authors">Joshua Beidler, Shirin Hooshmand, Mark Kern, Arturo Figueroa, Men Young Hong</td>-->
<!--                <td data-table-header="Journal">Journal of Federation of American Societies for Experimental Biology</td>-->
<!--                <td data-table-header="Date">April 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Contribution of Adiponectin to Vascular Responses in Bone Resistance Arteries in Mice.</td>-->
<!--                <td data-table-header="Authors">Payal Ghosh, Kazuki Hotta, Tiffany Lucero, Kyle Borodunovich, Morgan Cowan, Jeremy Bramy, Bradley Behnke, Michael Delp, Judy Delp</td>-->
<!--                <td data-table-header="Journal">Journal of Federation of American Societies for Experimental Biology</td>-->
<!--                <td data-table-header="Date">April 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Bone-Protective Effects of Dried Plum in Postmenopausal Women: Efficacy and Possible Mechanisms.</td>-->
<!--                <td data-table-header="Authors">Arjmani BH, Johnson SA, Pourafshar S, Navaei N, George KS, Hooshmand S, Chai SC, Akhavan NS</td>-->
<!--                <td data-table-header="Journal">Nutrients</td>-->
<!--                <td data-table-header="Date">May 2017</td>-->
<!--            </tr>-->
<!--            <tr>-->
<!--                <td data-table-header="Title">Cardiovascular Responses to Unilateral, Bilateral, and Alternating Limb Resistance Exercise Performed Using Different Body Sements.</td>-->
<!--                <td data-table-header="Authors">Moreira OC, Faraci LL, de Matos DG, Mazini Filho ML, da Silva SF, Aidar FJ, Hickner RC, de Oliveira CE</td>-->
<!--                <td data-table-header="Journal">Journal of Strength and Conditioning Research</td>-->
<!--                <td data-table-header="Date">March 2017</td>-->
<!--            </tr>-->
            </tbody>
        </table>
    </div>
</div>

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

</body>
</html>
