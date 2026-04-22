<?php
session_start();
if($_SESSION['permissions_level'] != 3){
    echo"<div style='text-align:center; color:red;'> You dont have permissions to view this file.</div>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
    <link rel="stylesheet" href="css/new_style.css">
    <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
    <script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
    <script type="text/javascript" src="https://maps.google.com/maps/api/js?v=3&sensor=false&language=en&libraries=geometry"></script>

    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.min.js"></script>
    <script type="text/javascript" src="jquery.ui.map.js"></script>

    <script type="text/javascript" src="jquery.js"></script>
    <style>
        #map_canvas {
            height: 400px;
            margin: 0 auto;
            width: auto;
    </style>
</head>
<body>
<!-- PAGE 1 -->


<!-- ************************PAGE 2****************************************** -->
<div data-role="page" id="pageone" data-theme="a">
    <div data-role="header" data-theme="b">
        <!--<a href="index.php" class="ui-btn ui-corner-all ui-shadow ui-icon-home ui-btn-icon-left">Home</a>-->
        <h1>User List</h1>

        <!--<a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left">log in</a>-->
    </div>
    <div data-role="main" class="ui-content" data-transition="slide"  data-position="fixed" data-role="button" data-theme="b">

        <?php
        include("connection.php.ini");
        mysqli_select_db($con, $db);
        $myquery = "SELECT * FROM Login_user order by Permissions";
        //echo $myquery;
        $result = mysqli_query($con,$myquery);
        // Check username and password match
        //echo mysql_num_rows($login);
        echo"<ul id='mylist' data-role='listview' data-inset='true'  data-filter='false' data-theme='d'>";
        while($row = mysqli_fetch_array($result))
        {
            $id = $row['id'];$uname = $row['username'];	$pword = $row['password'];
            $email = $row['email'];$phone = $row['phone'];$permissions = $row['Permissions'];
            $lastlogin = $row['Lastlogin'];
            //echo"<li><a data-parm='$id' href='#UserDetails'>$uname</a></li>";
            echo"<li><a data-parm='$id' href='edit_user.php?id=$id'>$uname - ($permissions) - (Last login: $lastlogin) </a></li>";
            //echo"<a href='#UserDetails' data-transition='slide' data-role='button' data-mini='true' data-theme='b' >$uname - $permissions</a>";
            //echo"<li><a data-role='button' href=\"details.php?id=$id\" data-transition=\"slide\">$uname</a></li>";
        }
        echo"</ul>";
        ?>

    </div>
    <div data-role="footer" data-theme="b" data-position="fixed" >
        <div data-role="navbar">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><button onclick="location.reload(true)">Refresh</button></li>
                <?php
                if (!isset($_SESSION['username']))
                {
                    echo "<li><a href=\"login.php\" class=\"ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left\">Login</a></li>";
                }
                else
                {
                    echo "<li><a href=\"logout.php\" class=\"ui-btn ui-corner-all ui-shadow ui-icon-action ui-btn-icon-left\">LogOut</a></li>";
                }
                ?>
            </ul>
        </div>
    </div>
</div>



</body>
</html>
