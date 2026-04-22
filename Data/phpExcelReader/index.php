<?php
	/*
Script Name: Read excel file in php with example
Script URI: http://allitstuff.com/?p=1303
Website URI: http://allitstuff.com/
*/
?>
<html>
<title>Read Excel file</title>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style>
	thead {color:green;}
	tbody {color:black;}
	tfoot {color:red;}
	table, th, td {
		border: 1px solid black;
		border-collapse:collapse;
	}
</style>
</head>
<body>

<?php
/** Include path **/
set_include_path(get_include_path() . PATH_SEPARATOR . 'Classes/');

/** PHPExcel_IOFactory */
include 'PHPExcel/IOFactory.php';


//$inputFileName = './jd.xlsx';  // File to read
$inputFileName = './OmarMuslimLists.xlsx';  // File to read
//echo 'Loading file ',pathinfo($inputFileName,PATHINFO_BASENAME),' using IOFactory to identify the format<br />';
try {
	$objPHPExcel = PHPExcel_IOFactory::load($inputFileName);
} catch(Exception $e) {
	die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
}


echo '<hr />';

//echo "<pre>";
$sheetData = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
//var_dump($sheetData);
//print_r($sheetData);
echo "<table><thead><tr>";
 $c = 1;     
foreach($sheetData as $rec)
{
	
	//print_r($rec);
	if($c == 1){
		 
			echo "<thead><tr>";
	}
	else{
		echo "<tbody><tr>";
	}
	
	foreach($rec as $data)
	{
		//print_r($data);
		//echo $data;
		
		 if($c == 1){
		 
			echo "<th>".$data."</th>";
		 }
		 else {
			echo "<td>".$data."</td>";
		 }	
		
		
	}
	if($c == 1){
		 
			echo "</thead></tr>";
	}
	else{
		echo "</tbody></tr>";
	}
	$c++;
}
echo "</tbody></table>";
/* 
for($r=1; $r<=$data->sheetData[0]['numRows']; $r++)
{
   for($c=1; $c<=$data->sheetData[0]['numCols']; $c++)
   {
      if (isset($data->sheetData[0]['cells'][$r][$c])) 
      {
         //I'm using this code to get the value
		 if($c == 1){
		 
			echo "<th>".$data->sheetData[0]['cells'][$r][$c]."</th>";
		 }
		 else {
			echo "<tbody><tr>".$data->sheetData[0]['cells'][$r][$c]."</tbody></tr>";
		 }
         //echo $data->sheetData[0]['cells'][$r][$c]."<BR>";
      }
   }
}
*/
?>
<body>
</html>