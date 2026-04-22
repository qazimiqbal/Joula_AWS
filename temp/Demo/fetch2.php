<?php
//echo "HEllo Sir";

include('database_connection.php');

$column = array("ID", "Name", "Halaqa", "H_No", "Apt_No", "St_Name", "City", "State", "Zip", "Ethinicity", "Coordinates");

$query = "SELECT * FROM Addresses ";

if(isset($_POST["search"]["value"]))
{
	$query .= '
	WHERE Name LIKE "%'.$_POST["search"]["value"].'%" 
	OR Halaqa LIKE "%'.$_POST["search"]["value"].'%" 
	OR H_No LIKE "%'.$_POST["search"]["value"].'%" 
    OR Apt_No LIKE "%'.$_POST["search"]["value"].'%" 
    OR St_Name LIKE "%'.$_POST["search"]["value"].'%" 
    OR City LIKE "%'.$_POST["search"]["value"].'%" 
    OR State LIKE "%'.$_POST["search"]["value"].'%" 
    OR Zip LIKE "%'.$_POST["search"]["value"].'%" 
    OR Ethinicity LIKE "%'.$_POST["search"]["value"].'%" 
    OR Coordinates LIKE "%'.$_POST["search"]["value"].'%" 
	';
}

if(isset($_POST["order"]))
{
	$query .= 'ORDER BY '.$column[$_POST['order']['0']['column']].' '.$_POST['order']['0']['dir'].' ';
}
else
{
	$query .= 'ORDER BY ID ';
}
//exit;
$query1 = '';

if($_POST["length"] != -1)
{
	$query1 = 'LIMIT ' . $_POST['start'] . ', ' . $_POST['length'];
}

$statement = $connect->prepare($query);

$statement->execute();

$number_filter_row = $statement->rowCount();

$result = $connect->query($query . $query1);

$data = array();

foreach($result as $row)
{
	$sub_array = array();
	$sub_array[] = $row['ID'];
	$sub_array[] = $row['Name'];
	$sub_array[] = $row['Halaqa'];
	$sub_array[] = $row['H_No'];
    $sub_array[] = $row['Apt_No'];
    $sub_array[] = $row['St_Name'];
    $sub_array[] = $row['City'];
    $sub_array[] = $row['State'];
    $sub_array[] = $row['Zip'];
    $sub_array[] = $row['Ethinicity'];
    $sub_array[] = $row['Coordinates'];
	$data[] = $sub_array;
}

function count_all_data($connect)
{
	$query = "SELECT * FROM Addresses";

	$statement = $connect->prepare($query);

	$statement->execute();

	return $statement->rowCount();
}

$output = array(
	'draw'		=>	intval($_POST['draw']),
	'recordsTotal'	=>	count_all_data($connect),
	'recordsFiltered'	=>	$number_filter_row,
	'data'		=>	$data
);

echo json_encode($output);

?>
