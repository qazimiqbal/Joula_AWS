<?php
class Connection {
	protected $host = "localhost";
	protected $dbname = "Atlanta";
    protected $user = "root";
    protected $pass = "Labqi1962";
    protected $DBH;
	
	
	public function __construct(){
		try {
			$this->DBH = new PDO("mysql:host=$this->host;dbname=$this->dbname", $this->user, $this->pass);
		}
		catch (PDOException $e) {

            echo $e->getMessage();
        }
		//$db = new PDO("mysql:host=".$host.";dbname=".$database,$username,$password);
	}
	//public function insert($Name,$Halaqa,$H_No,$Apt_No,$St_Name,$City,$State,$Zip,$J_Friendly,$Imam,$Population,$Contact_Name,$Contact_No,$Comments) {
	public function insert($Name,$Halaqa,$H_No,$Apt_No,$St_Name,$City,$State,$Zip,$J_Friendly,$Five_Aamaal,$Four_M_Brothers,$Forty_D_Brothers,$Three_D_Brothers,$Forty_D_Sisters,$Ten_D_Sisters,$Three_D_Sisters,$Ten_D_Brothers,$Ladies_Taleem,$First_Joula,$Second_Joula,$Three_d_Jamat,$Masjid_Taleem,$Daily_Fiqr,$Daily_Mashwara,$Home_Taleem,$Imam,$Population,$Contact_Name,$Contact_No,$Comments,$Kitchen,$Shower) {

        //echo $Five_Aamaal;
		$sql = "INSERT INTO Masjids (Name,Halaqa, H_no, Apt_No, St_Name, City,State,Zip,J_Friendly,5_Aamaal,4M_Brothers,40D_Brothers,10D_Brothers,3D_Brothers,40D_Sisters,10D_Sisters, 3D_Sisters,Ladies_Taleem,1st_Joula,2nd_Joula,3D_Jamat,Daily_Fiqr,Daily_Mashwara,Home_Taleem,Imam,Population,Contact_Name,Contact_No,Comments,Kitchen,Shower) VALUES ('$Name','$Halaqa','$H_No','$Apt_No','$St_Name','$City','$State','$Zip','$J_Friendly','$Five_Aamaal','$Four_M_Brothers','$Forty_D_Brothers','$Ten_D_Brothers','$Three_D_Brothers','$Forty_D_Sisters','$Ten_D_Sisters','$Three_D_Sisters','$Ladies_Taleem','$First_Joula','$Second_Joula','$Three_d_Jamat','$Daily_Fiqr','$Daily_Mashwara','$Home_Taleem','$Imam','$Population','$Contact_Name','$Contact_No','$Comments','$Kitchen','$Shower')";
		
		//$sql = "INSERT INTO Masjids (Name,Halaqa)";
		//$sql .= "VALUES ('$Name','$Halaqa')";
		
		//echo $sql;
		$STH = $this->DBH->prepare($sql);
        $STH->execute();
    }
	public function UpdateLocation($mycoordinates, $geoID) {

        //echo $Name;
		$sql_update = "UPDATE Masjids set Coordinates = '$mycoordinates', Status = 'true' where ID ='$geoID'";
		echo $sql;
		$STH_update = $this->DBH->prepare($sql_update);
        $STH_update->execute();
    }
	function getData($halaqa,$permissions)
        {
            if($permissions == "Viewer"||$permissions == "Editor" ||$permissions == "Administrator"){
				$STH = $this->DBH->prepare("SELECT * FROM Masjids where Halaqa = '$halaqa'");
			}
			if($permissions == "Super Administrator"){
				$STH = $this->DBH->prepare("SELECT * FROM Masjids");
			}
			//else{
			//	$STH = $this->DBH->prepare("SELECT * FROM Masjids");
			//}
			
			$STH->execute();
			return $STH->fetchAll();
			
			//$query = $this->DBH->prepare('SELECT * FROM Masjids');
            //$query->execute();
            //return $query->fetchAll();
        }
	public function closeConnection() {
        $this->DBH = null;
    }
}
?>