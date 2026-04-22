<?php

//update.php

include('database_connection.php');

$query = "
UPDATE Addresses 
SET ".$_POST["name"]." = '".$_POST["value"]."' 
WHERE id = '".$_POST["pk"]."'
";

$connect->query($query);

?>