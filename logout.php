<?php
session_start();
unset($_SESSION['username']);
unset($_SESSION['id']);
unset($_SESSION['permissions']);
unset($_SESSION['permissions_level']);
session_unset();
session_destroy();
session_write_close();
setcookie(session_name(),'',0,'/');
session_regenerate_id(true);
//header("location: https://".$_SERVER['HTTP_HOST']);
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
            <form method="POST" data-ajax="false"  id="login-form" Action="login.php">
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