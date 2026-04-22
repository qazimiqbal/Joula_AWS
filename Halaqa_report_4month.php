<?php
require('fpdf/fpdf.php');
if(isset($_GET['id'])){
   $myid = $_GET['id'];
}else{
    $myid = "Atlanta East";
}
//echo $_GET['id'];
//exit;
class PDF extends FPDF
{
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

	

	function ChapterBody($id)
	{
		$con = mysql_connect("p3plcpnl0916.prod.phx3.secureserver.net","joula","Joula@955");
		if (!$con)
		{
		   die('Could not connect: ' . mysql_error());
		}
		mysql_select_db("joula", $con);
		
		
		
		$today = date("m.d.y");
		
		$this->AddPage();
		/*
		$this->SetFont('Arial','',10);
		// Background color
		$this->SetFillColor(200,220,255);		
		$this->Cell(0,10,"Report of  :   ".$id,0,1,'L',true);
		$this->Ln(4);
		*/
		$this->SetFont('Arial','',7);
		$this->SetFillColor(200,220,255);
		
		
		//4 month and active 4 month data
		$FourMonthquery = "SELECT * FROM `Old_Workers` WHERE MaxTime_Spent = '4 Months' and Halaqa = '$id'";
		$FourMonthresult = mysql_query($FourMonthquery, $con);
		$total4months = mysql_num_rows($FourMonthresult);
		
		$FourMonthActive = "SELECT * FROM `Old_Workers` WHERE MaxTime_Spent = '4 Months' and Active = 'Yes' and Halaqa = '$id'";
		$result = mysql_query($FourMonthActive, $con);
		$totalActive4months = mysql_num_rows($result);		
		
		$this->Cell(47,7,"No. of 4 month Brothers:  ".$total4months,1, $ln =0, $align ='', $fill = true, 'http://www.sample.com');
		$this->Cell(48,7,"No. of Active 4 month brothers:  ".$totalActive4months,1);
		//$this->Ln();
				
		//40 days and active 40 days data
		$FortyDayquery = "SELECT * FROM `Old_Workers` WHERE MaxTime_Spent = '40 Days' and Gender = 'Male' and Halaqa = '$id'";
		$result = mysql_query($FortyDayquery, $con);
		$total40Days = mysql_num_rows($result);
		
		$FortyDayActive = "SELECT * FROM `Old_Workers` WHERE MaxTime_Spent = '40 Days' and Gender = 'Male' and Active = 'Yes' and Halaqa = '$id'";
		$result = mysql_query($FortyDayActive, $con);
		$totalActive40Days = mysql_num_rows($result);
				
		$this->Cell(47,7,"No. of 40 days brothers:  ".$total40Days,1);
		$this->Cell(48,7,"No. of Active 40 days brothers:  ".$totalActive40Days,1);
		$this->Ln();
		
		$this->Cell(9,7,"S.No",1);
		$this->Cell(97,7,"Item",1);
		$this->Cell(28,7,"Previous",1);
		$this->Cell(28,7,"Present",1);
		$this->Cell(28,7,"Future",1);
		$this->Ln();
		
		
		//No of Masjids with 5 aamaal
		$fiveamalmasjids = "SELECT * FROM `Masjids` WHERE 5_Aamaal = '5' and Halaqa = '$id'";
		$result = mysql_query($fiveamalmasjids, $con);
		$fiveamalmasjidstotal = mysql_num_rows($result);
			
		$this->Cell(9,7,"1",1);
		$this->Cell(97,7,"No. of Masjids with 5 Aamaal",1);
		$this->Cell(28,7," ",1);
		$this->Cell(28,7,"$fiveamalmasjidstotal",1);
		$this->Cell(28,7," ",1);
		$this->Ln();
		
		//No of Masjids with some aamaal
		$someamalmasjids = "SELECT * FROM `Masjids` WHERE 5_Aamaal BETWEEN 1 and 4 and Halaqa = '$id'";
		$result = mysql_query($someamalmasjids, $con);
		$someamalmasjidstotal = mysql_num_rows($result);
		
		$this->Cell(9,7,"2",1);
		$this->Cell(97,7,"No. of Masjids with Some Aamaal",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"$someamalmasjidstotal",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		//No of 3 day jamats per month
		$threedayjamats = "SELECT 3D_Jamat FROM `Masjids` WHERE Halaqa = '$id'";
		$result = mysql_query($threedayjamats, $con);
		//$someamalmasjidstotal = mysql_num_rows($result);
		$jamatCount;
		while ($row = mysql_fetch_assoc($result)) {				
			$jamatCount = $jamatCount + $row["3D_Jamat"];
		}		
		$this->Cell(9,7,"3",1);
		$this->Cell(97,7,"Average No. of 3 day jamats/month",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"$jamatCount",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		//No of brothers in 21/2 amal
		$dailyeffortbrothers = "SELECT sum(DailyFiqr_brothers) as No_bros FROM `Masjids` WHERE Halaqa = '$id'";
		$result = mysql_query($dailyeffortbrothers);
		$row = mysql_fetch_assoc($result); 
		$sum_bros = $row['No_bros'];
			
		$this->Cell(9,7,"4",1);
		$this->Cell(97,7,"No of Brothers participating in 2 1/2 effort daily",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"$sum_bros",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"5",1);
		$this->Cell(97,7,"No of Masjids doing daily effort with Aabaadi",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		//No of home taleem houses
		$hometaleemno = "SELECT Home_Taleem FROM `Masjids` WHERE Halaqa = '$id'";
		$result = mysql_query($hometaleemno, $con);
		//$someamalmasjidstotal = mysql_num_rows($result);
		$hometaleemCount;
		while ($row = mysql_fetch_assoc($result)) {				
			$hometaleemCount = $hometaleemCount + (int)$row["Home_Taleem"];
		}		
		
		$this->Cell(9,7,"6",1);
		$this->Cell(97,7,"Houses doing daily Taleem",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"$hometaleemCount",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		//DOing taleem with 6 qualities
		$hometaleemwithqualities = "SELECT sum(HmTlm_Sixqu) as HT_sixqualities FROM `Masjids` WHERE Halaqa = '$id'";
		$result = mysql_query($hometaleemwithqualities);
		$row = mysql_fetch_assoc($result); 
		$sum_HT6qu = $row['HT_sixqualities'];
		$this->Cell(9,7,"7",1);
		$this->Cell(97,7,"Houses doing Taleem with 6 qualities",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"$sum_HT6qu",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		//4 months spending yearly
		$query = "SELECT count(*) FROM `Old_Workers` WHERE FourM_Yearly = 'yes' and Halaqa = '$id'";
		$result = mysql_query($query);
		$fields=mysql_fetch_array($result);
		
		$this->Cell(9,7,"8",1);
		$this->Cell(97,7,"No. of brothers spending 4 months yearly",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"$fields[0]",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		//10 days spending monthly
		$queryM = "SELECT count(*) FROM `Old_Workers` WHERE TenD_Monthly = 'yes' and Halaqa = '$id'";
		$resultM = mysql_query($queryM);
		$fieldsM=mysql_fetch_array($resultM);
		$this->Cell(9,7,"9",1);
		$this->Cell(97,7,"No. of brothers spending 10 days monthly",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"$fieldsM[0]",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		//8 hours daily
		$queryE = "SELECT count(*) FROM `Old_Workers` WHERE EightH_Daily = 'yes' and Halaqa = '$id'";
		$resultE = mysql_query($queryE);
		$fieldsE=mysql_fetch_array($resultE);
		
		$this->Cell(9,7,"10",1);
		$this->Cell(97,7,"No. of brothers spendnig 8 Hours daily",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"$fieldsE[0]",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"11",1);
		$this->Cell(97,7,"No. of 4 month jamats",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"12",1);
		$this->Cell(97,7,"No. of 4 month jamats on Foot",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"13",1);
		$this->Cell(97,7,"No. of 40 day jamats",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"14",1);
		$this->Cell(97,7,"No. of 40 day jamats on Foot",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"15",1);
		$this->Cell(97,7,"No. of masjids that send complete 4 month jamats",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"16",1);
		$this->Cell(97,7,"No. of masjids that send complete 40 day jamats",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"17",1);
		$this->Cell(97,7,"No. of 40 day jamats with Ladies",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"18",1);
		$this->Cell(97,7,"No. of 10 day jamats with Ladies",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"19",1);
		$this->Cell(97,7,"No. of 3 days jamats with ladies",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"20",1);
		$this->Cell(97,7,"No. of 40 day jamats with ladies send to foreign",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"21",1);
		$this->Cell(97,7,"No. of brothers who spend 2 months at Nizamuddin",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
		
		$this->Cell(9,7,"22",1);
		$this->Cell(97,7,"No. of localities where new work started",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Cell(28,7,"",1);
		$this->Ln();
	}
	function PDF($orientation='P',$unit='mm',$format='A4')
{
    //Call parent constructor
    $this->FPDF($orientation,$unit,$format);
    //Initialization
    $this->B=0;
    $this->I=0;
    $this->U=0;
    $this->HREF='';
}

function WriteHTML($html)
{
    //HTML parser
    $html=str_replace("\n",' ',$html);
    $a=preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
    foreach($a as $i=>$e)
    {
        if($i%2==0)
        {
            //Text
            if($this->HREF)
                $this->PutLink($this->HREF,$e);
            else
                $this->Write(5,$e);
        }
        else
        {
            //Tag
            if($e{0}=='/')
                $this->CloseTag(strtoupper(substr($e,1)));
            else
            {
                //Extract attributes
                $a2=explode(' ',$e);
                $tag=strtoupper(array_shift($a2));
                $attr=array();
                foreach($a2 as $v)
                    if(ereg('^([^=]*)=["\']?([^"\']*)["\']?$',$v,$a3))
                        $attr[strtoupper($a3[1])]=$a3[2];
                $this->OpenTag($tag,$attr);
            }
        }
    }
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
$title = 'Report of '.$myid;
$pdf->SetTitle($title);
//$pdf->SetAuthor('Jules Verne');
$pdf->ChapterBody($myid);
//$pdf->PrintChapter($myid);
//$pdf->PrintChapter(2,'THE PROS AND CONS','20k_c2.txt');

$pdf->Output();
?>