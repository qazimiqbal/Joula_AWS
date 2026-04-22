<?php
error_reporting(E_ALL ^ E_NOTICE);
require_once 'excel_reader2.php';
$filename = "OmarMuslimLists.xls";
$data = new Spreadsheet_Excel_Reader();
$data->setUTFEncoder('iconv');
$data->setOutputEncoding('CP1251');
$data->read($filename);


for($r=1; $r<=$data->sheets[0]['numRows']; $r++)
{
   for($c=1; $c<=$data->sheets[0]['numCols']; $c++)
   {
      if (isset($data->sheets[0]['cells'][$r][$c])) 
      {
         //I'm using this code to get the value
         echo $data->sheets[0]['cells'][$r][$c]."<BR>";
      }
   }
}




?>
<html>
<head>
<style>
table.excel {
	border-style:ridge;
	border-width:1;
	border-collapse:collapse;
	font-family:sans-serif;
	font-size:12px;
}
table.excel thead th, table.excel tbody th {
	background:#CCCCCC;
	border-style:ridge;
	border-width:1;
	text-align: center;
	vertical-align:bottom;
}
table.excel tbody th {
	text-align:center;
	width:20px;
}
table.excel tbody td {
	vertical-align:bottom;
}
table.excel tbody td {
    padding: 0 3px;
	border: 1px solid #EEEEEE;
}
</style>
</head>

<body>
<?php 
	//echo $data->dump(true,true); 
	
	$array = getDataInArray($data);

function getDataInArray($data)
{
   
	for ($row = 2; $row <= $data->rowcount(); $row++) {
		$address = "";
		$name = "";
		$h_no = "";
		$st_name = "";
		$city = "";
		$state = "";
		$zip = "";
		$verified = "";
		$contact = "";
		$round1 = "";
		$r1comments = "";
		$round2 = "";
		$r2comments = "";
		$round3 = "";
		$r3comments = "";
		$area = "";
		$zone = "";
        for ($col = 1; $col <= $data->colcount(); $col++) {
			if($col == 1){
				$name .= $data->val($row, $col);
			}
			if($col == 2){
				$address .= $data->val($row, $col)." ";
				$h_no .= $data->val($row, $col);
			}
			if($col == 3){
				$address .= $data->val($row, $col)." ";
				$st_name .= $data->val($row, $col);
			}
			if($col == 4){
				$address .= $data->val($row, $col)." ";
				$city .= $data->val($row, $col);
			}
			if($col == 5){
				$address .= $data->val($row, $col)." ";
				$state .= $data->val($row, $col);
			}
			if($col == 6){
				$address .= $data->val($row, $col)." ";
				$zip .= $data->val($row, $col);
			}
			if($col == 7){
				$verified .= $data->val($row, $col);
			}
			if($col == 8){
				$contact .= $data->val($row, $col);
			}
			if($col == 9){
				//$round1 .= $data->val($row, $col);
				//$round1 .= date('Y.m.d', strtotime(trim($data->val($row, $col))));
				$round1 .= date('Y.m.d', strtotime(str_replace('/', '-', trim($data->val($row, $col)))));
			}
			if($col == 10){
				$r1comments .= $data->val($row, $col);
			}
			if($col == 11){
				$round2 .= $data->val($row, $col);
			}
			if($col == 12){
				$r2comments .= $data->val($row, $col);
			}
			if($col == 13){
				$round3 .= $data->val($row, $col);
			}
			if($col == 14){
				$r3comments .= $data->val($row, $col);
			}
			if($col == 15){
				$area .= $data->val($row, $col);
			}
			if($col == 16){
				$zone .= $data->val($row, $col);
			}
        }
		//echo $name." ".$address." ".$verified." ".$round1." ".$r1comments." ".$round2." ".$r2comments." ".$round3." ".$r3comments." ".$contact." ".$area." ".$zone."<BR>";
    }
	
	//echo "<BR>";
    //return $out;
}
	
	

?>
</body>
</html>
