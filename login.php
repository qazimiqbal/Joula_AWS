<?php
session_start();
if(isset($_POST['username'])){
    include("connection.php.ini");

    mysqli_select_db($con, $db) or DIE('Database is not available!');
    $myquery = "SELECT * FROM Login_user WHERE (username = '" . mysqli_real_escape_string($con, $_POST['username']) . "') and (password = '" . mysqli_real_escape_string($con, md5($_POST['password'])) . "') and status = 'true'";
    $login = mysqli_query($con, $myquery);
    // Check username and password match
    if (mysqli_num_rows($login) == 1) {
        while ($row = mysqli_fetch_assoc($login)) {
            $_SESSION['id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['halaqa'] = $row['Halaqa'];
            $_SESSION['pass_change'] = $row['Pass_change'];
            $_SESSION['permissions'] = $row['Permissions'];
        }
        if ($_SESSION['permissions'] == "Viewer"){$_SESSION['permissions_level'] = 0;}
        if ($_SESSION['permissions'] == "Editor"){$_SESSION['permissions_level'] = 1;}
        if ($_SESSION['permissions'] == "Administrator"){$_SESSION['permissions_level'] = 2;}
        if ($_SESSION['permissions'] == "Super Administrator"){$_SESSION['permissions_level'] = 3;}

        $today = date("Y-m-d H:i:s");
        $lastloginquery = "UPDATE Login_user set Lastlogin = '$today'  WHERE (username = '" . mysqli_real_escape_string($con, $_POST['username']) . "') and (password = '" . mysqli_real_escape_string($con, md5($_POST['password'])) . "')";
        mysqli_query($con, $lastloginquery);

        header("location: https://".$_SERVER['HTTP_HOST']."/mobile/index.php");
        //exit;
        //****************************************SEND MAIL *************************************
        /*
        //BELOW CODE IS SENDING EMAIL
        $encoding = "utf-8";
        // Preferences for Subject field
        $subject_preferences = array(
            "input-charset" => $encoding,
            "output-charset" => $encoding,
            "line-length" => 76,
            "line-break-chars" => "\r\n"
        );
        // Mail header
        $header = "Content-type: text/html; charset=".$encoding." \r\n";
        $header .= "From: My Joula <administrator@myjoula.com> \r\n";
        $header .= "MIME-Version: 1.0 \r\n";
        $header .= "Content-Transfer-Encoding: 8bit \r\n";
        $header .= "Date: ".date("r (T)")." \r\n";
        $header .= iconv_mime_encode("Subject", $mail_subject, $subject_preferences);
        $message = $_SESSION['username']." Logged in";
        // Send mail
        //$success = mail('qazi.iqbal@gmail.com', 'Joula Login Alert', $message, $header);
        if (!$success) {
            //$errorMessage = error_get_last()['message'];
            echo "Log in Failed";
        }
        */

        //***************************************************************************************

    }
    else
    {
        $credentials = "<div style='text-align:center; color:red;'>Please enter again,<BR> Username or password did not match</div>";

    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <!-- Include meta tag to ensure proper rendering and touch zooming -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Include jQuery Mobile stylesheets -->
    <link rel="stylesheet" href="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
    <!-- Include the jQuery library -->
    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
    <!-- Include the jQuery Mobile library -->
    <script src="https://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
</head>
<body>
<div data-role="page" id="pageone">
    <div data-role="header"  data-theme="b">
        <h1>Joula Login</h1>
    </div>
    <div data-role="main" class="ui-content" data-transition="slide"  data-position="fixed" data-role="button" data-theme="b">

        <?php
        echo $credentials;
        ?>
        <p><center>
            <form method="POST" data-ajax="false"  id="login-form">
                <table border="0"><tr><td>Username</td><td>:</td><td><input type="text" name="username" size="20"></td></tr>
                    <tr><td>Password</td><td>:</td><td><input type="password" name="password" size="20"></td></tr>
                    <tr><td>&nbsp;</td><td>&nbsp;</td><td><input type="submit" value="Login"></td></tr>
                </table>
            </form>
    </div>

    <a href="https://www.myjoula.com/mobile/nearestMasjid.php" data-transition="slide" data-role="button" data-mini="true" data-theme="e" rel="external">Find Nearest Masjid</a>

    </center>
    </p>
    <div data-role="footer"   data-position="fixed" data-theme="b">
        <h1>Please Log in</h1>
    </div>
</div>

</body>
</html>