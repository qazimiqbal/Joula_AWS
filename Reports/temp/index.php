<?php
    session_start();
    //if (!isset($_SESSION['username']))
    //{
    //	header('Location: login.php');
    //}
    include("../connection.php.ini");
?>
<html>
    <title>Data Processing Page</title>
    <Head></Head>
    <Body>
        <Center>

            <table width="100%" border="0">
                <tr>
                    <td align="middle">
                        <H1>Please select the locality below</H1><br>
                        <form method="POST" action="Reportfilter.php">
                            <select name="Locality" onchange="this.form.submit()">
                                <option value="All">All</option>
                                <?php
                                mysqli_select_db($con, $db);
                                //$mySQL = "Select DISTINCT Locality from $table where State = 'GA' and Coordinates != '' order by Locality,  Name";
                                $mySQL = "Select DISTINCT Locality from $table where Coordinates != '' order by Locality,  Name";
                                echo $mySQL;
                                $result = mysqli_query($con, $mySQL);
                                while($row = mysqli_fetch_array($result))
                                {
                                    if($row['Locality'] == $myLocality){ $mycheck = "SELECTED";}
                                    else{$mycheck = "";};
                                    $encode_Area = urlencode($row['Locality']);
                                    $Locality = $row['Locality'];
                                    if($Locality != ""){
                                        echo "<option value=$encode_Area $mycheck>$Locality</option>";
                                    }
                                }
                                ?>
                            </select><br><br>
                            <input type="submit" value="Submit">
                        </form>
                    </td>
                </tr>
            </table>
        </Center>
    </Body>
</html>


