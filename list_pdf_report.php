<?php
require('../halaqa/fpdf/fpdf.php');
if(isset($_GET['locality'])){
   $locality = $_GET['locality'];
}else{
    $locality = "All";
}

if(isset($_GET['area'])){
   $area = $_GET['area'];
}else{
    $area = "All";
}



class PDF extends FPDF
{
	function WordWrap(&$text, $maxwidth)
	{
		$text = trim($text);
		if ($text==='')
			return 0;
		$space = $this->GetStringWidth(' ');
		$lines = explode("\n", $text);
		$text = '';
		$count = 0;

		foreach ($lines as $line)
		{
			$words = preg_split('/ +/', $line);
			$width = 0;

			foreach ($words as $word)
			{
				$wordwidth = $this->GetStringWidth($word);
				if ($wordwidth > $maxwidth)
				{
					// Word is too long, we cut it
					for($i=0; $i<strlen($word); $i++)
					{
						$wordwidth = $this->GetStringWidth(substr($word, $i, 1));
						if($width + $wordwidth <= $maxwidth)
						{
							$width += $wordwidth;
							$text .= substr($word, $i, 1);
						}
						else
						{
							$width = $wordwidth;
							$text = rtrim($text)."\n".substr($word, $i, 1);
							$count++;
						}
					}
				}
				elseif($width + $wordwidth <= $maxwidth)
				{
					$width += $wordwidth + $space;
					$text .= $word.' ';
				}
				else
				{
					$width = $wordwidth + $space;
					$text = rtrim($text)."\n".$word.' ';
					$count++;
				}
			}
			$text = rtrim($text)."\n";
			$count++;
		}
		$text = rtrim($text);
		return $count;
	}
	function Header()
	{
		global $title;

		// Arial bold 15
		$this->SetFont('Arial','B',15);
		// Calculate width of title and position
		$w = $this->GetStringWidth($title)+6;
		$this->SetX((210-$w)/2);
		// Colors of frame, background and text
		$this->SetDrawColor(0,80,180);
		//$this->SetFillColor(230,230,0);
		$this->SetTextColor(220,50,50);
		// Thickness of frame (1 mm)
		$this->SetLineWidth(1);
		// Title
		$this->Cell($w,9,$title,1,1,'C',true);
		// Line break
		$this->Ln(10);
	}

	function Footer()
	{
		// Position at 1.5 cm from bottom
		$this->SetY(-15);
		// Arial italic 8
		$this->SetFont('Arial','I',8);
		// Text color in gray
		$this->SetTextColor(128);
		// Page number
		$this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');
	}

	

	function ChapterBody($locality, $area)
	{
		
		if($locality == "All"){$locality_condition = ""; $localityString = "";}
		else{
			$locality_condition = "Locality = '$locality'";
			$localityString = "Locality = $locality";
		}
		if($area == "All"){$area_condition = "";$areaString = "";}
		else{
			$area_condition = "and Area = '$area'"; 
			$areaString = "Area = $area";
		}
		
		$this->AddPage();
		$this->SetFont('Arial','',10);
		// Background color
		$this->SetFillColor(200,220,255);		
		$this->Cell(0,10,$localityString,0,1,'L',true);
		//$this->Ln();
		$this->Cell(0,10,$areaString,0,1,'L',true);
		//echo "Hello";
		// Line break
		$this->Ln(4);
		$this->SetFont('Arial','',8);
		$this->SetFillColor(200,220,255);
		$this->Cell(5,7,"No",1);
		$this->Cell(37,7,"Name",1);
		$this->Cell(60,7,"Address",1);
		
		//$this->Cell(10,7,"Count",1);
		$this->Cell(72,7,"Comments",1);
		$this->Cell(16,7,"LastVisit",1);
		$this->Ln();
		
		//include("connection.php.ini");
		$con = mysql_connect("p3plcpnl0916.prod.phx3.secureserver.net","joula","Joula@955");
		if (!$con)
		{
		   die('Could not connect: ' . mysql_error());
		}
		
		
		mysql_select_db("joula", $con);
		//$myquery = "SELECT * FROM Masjids $locality_condition order by Name";
		$myquery = "Select * from Addresses2 where $locality_condition $area_condition and Coordinates != '' order by Last_Visit, Locality,  Name";
		//echo $myquery;
		//exit;
		$result = mysql_query($myquery);
		//$total_FM = 0; $total_Fortyday_Men = 0;$total_Tenday_Men = 0;$total_Fortyday_Sisters = 0;$total_Tenday_Sisters = 0;
		$i = 1;
		//$total_jamats = 0;
		//$total_dailytaleem_homes = 0;
		while($row = mysql_fetch_array($result))
		{
			$Name = substr($row['Name'], 0, 25); 
			$Halaqa = $row['Halaqa']; $H_No = $row['H_No']; $Apt_No = $row['Apt_No'];
			$St_Name = $row['St_Name']; $City = $row['City']; $State = $row['State'];
			$Zip = $row['Zip']; 
			$Last_Visit = $row['Last_Visit'];
			if($Last_Visit == "0000-00-00"){$Last_Visit = "";}
			if($Apt_No != ""){$AptString = "(Apt: $Apt_No)";}else {$AptString = "";}
			$Comments = $row['Comments'];
			$Comments_wrap = substr($Comments, 0, 60);  // returns "abcde"
			//$Comments_wrap = WordWrap($Comments,20);
			//$ = $row['']; $ = $row['']; $ = $row[''];
			//$ = $row['']; $ = $row['']; $ = $row[''];
			$Address = $H_No." ".$St_Name." ".$City." ".$State." ".$Zip." ".$AptString;
			
			$total_jamats = $total_jamats + (int)$Three_D_Jamat;
			$total_dailytaleem_homes = $total_dailytaleem_homes  + (int)$Home_Taleem;
			
			//$this->MultiCell(0,5,"Masjid Information");
			//$this->Cell(80,7,"Name of Halaqa",1);
			$this->SetDrawColor(0,80,180);
			$this->SetFillColor(230,230,0);
			$this->SetTextColor(0,50,50);
			$this->SetFont('Arial','',7);		
			$this->Cell(5,5,$i,1);
			$this->Cell(37,5,$Name,1);
			//$this->SetFont('Arial','',7);		
			$this->Cell(60,5,$Address,1);
			
			//$this->Cell(30,5,str_word_count($Comments),1);
			$this->Cell(72,5,$Comments_wrap,1);
			$this->Cell(16,5,$Last_Visit,1);
			
			
			$this->Ln();
			
			/*$total_FM = $total_FM + $Four_M_Brother;
			$total_Fortyday_Men = $total_Fortyday_Men + $Forty_D_Brothers;
			$total_Tenday_Men = $total_Tenday_Men + $Ten_D_Brothers;
			
			$total_Fortyday_Sisters = $total_Fortyday_Sisters + $Forty_D_Sisters;
			$total_Tenday_Sisters = $total_Tenday_Sisters + $Ten_D_Sisters;
			*/
			$i++;
		}
		/*
		mysql_select_db($db, $con);
		$total_Masjid_query = "SELECT * FROM Masjids where Halaqa = '$id'";
		$result_total_masjids = mysql_query($total_Masjid_query, $con);
		$totalMasjids = mysql_num_rows($result_total_masjids);
		
		$f_amaal_query = "SELECT * FROM Masjids where Halaqa = '$id' and 5_Aamaal = '5'";
		$result_famaal = mysql_query($f_amaal_query, $con);
		$fiveAmaalMasjids = mysql_num_rows($result_famaal);
		
		$some_amaal_query = "SELECT 5_Aamaal FROM Masjids where Halaqa = '$id' and 5_Aamaal != '5' and 5_Aamaal != '0'";
		$result_someamaal = mysql_query($some_amaal_query, $con);		
		$someAmaalMasjids = mysql_num_rows($result_someamaal);
		
		$no_amaal_query = "SELECT 5_Aamaal FROM Masjids where Halaqa = '$id' and 5_Aamaal = '0'";
		$result_no_amaal = mysql_query($no_amaal_query, $con);		
		$noAmaalMasjids = mysql_num_rows($result_no_amaal);
		*/
		

		
		
		
		
		//$this->Ln();
		
		
		//$this->Ln();
		/*
		
		*/
	}

	
}

$pdf = new PDF();
//********************
/*
$pdf = new mPDF('',    // mode - default ''
 '',    // format - A4, for example, default ''
 0,     // font size - default 0
 '',    // default font family
 15,    // margin_left
 15,    // margin right
 16,     // margin top
 16,    // margin bottom
 9,     // margin header
 9,     // margin footer
 'L');  // L - landscape, P - portrait
 */
//********************
$title = 'Musalleen Report';
$pdf->SetTitle($title);
//$pdf->SetAuthor('Jules Verne');
$pdf->ChapterBody($locality, $area);
//$pdf->PrintChapter($myid);
//$pdf->PrintChapter(2,'THE PROS AND CONS','20k_c2.txt');
$pdf->Output();
?>