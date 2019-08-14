<?php
include("../../config.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
function createSetalolap($id)
{
	$result = sql_fetch_array( sql_query("SELECT * FROM felhasznalok WHERE id = ?", array( $id )));
	
	require_once( "../Classes/PHPExcel.php" );
	$tmpfname 	 = "tatabányaRAW3.xlsx";
	$excelReader = PHPExcel_IOFactory::createReader( 'Excel2007' );
	$excelObj 	 = $excelReader->load( $tmpfname );
	$worksheet 	 = $excelObj->getSheet(0);
	//$lastRow 	 = $excelObj->getHighestRow();
	$lastRow = $excelObj->setActiveSheetIndex(0)->getHighestRow();
	
	$excelArray = array();
	
	for($row = 1; $row <= $lastRow; $row++)
	{
		$excelArray[] = array( "nev"      => $worksheet->getCell('A'.$row)->getValue(),
							   "belgyogy" => $worksheet->getCell('B'.$row)->getValue(),
							   "hasiuh"   => $worksheet->getCell('D'.$row)->getValue(),
							   "pajzsuh"  => $worksheet->getCell('F'.$row)->getValue(),
							   "emlouh"   => $worksheet->getCell('H'.$row)->getValue(),
							   "abi"	  => $worksheet->getCell('N'.$row)->getValue(),
							   "carotis"  => $worksheet->getCell('G'.$row)->getValue(),
							   "arterio"  => $worksheet->getCell('E'.$row)->getValue(),
							   "csontsur" => $worksheet->getCell('L'.$row)->getValue(),
							   "szivuh"   => $worksheet->getCell('M'.$row)->getValue(),
							   "szemesz"  => $worksheet->getCell('K'.$row)->getValue(),
							   "borgyogy" => $worksheet->getCell('I'.$row)->getValue(),
							   "nrutin"   => $worksheet->getCell('O'.$row)->getValue(),
							   "krutin"   => $worksheet->getCell('C'.$row)->getValue(),
							   "tumor"    => $worksheet->getCell('J'.$row)->getValue()
							  );
	}
	
	//$searchName = $result['nev'];
	$searchName = $result['nev'];
	
	$key = array_search( $result['nev'], array_column( $excelArray, 'nev' ));
	$style = "style='text-decoration:line-through'";
	if($key !== FALSE)
	{
		//width:595px;height:842px;
		$output = "";
		$labor  = "";
		$labor .= ($excelArray[$key]['tumor']!=""?"Tumormarker":"");
		//$labor .= ($excelArray[$key]['krutin']!=""?",":"");
		//$labor .= ($excelArray[$key]['krutin']!=""?"kisrutin":"");
		$labor .= ($excelArray[$key]['nrutin']!=""?",":"");
		$labor .= ($excelArray[$key]['nrutin']!=""?"nagyrutin":"");
		if($labor == "") $laborStyle = $style;
		else $laborStyle = "";
		
		$output.= "<div style = 'width:198mm;height:277mm'>";
		$output.= "	<table id = 'patient-header'>";
		$output.= "		<tr><td colspan = '2' align = 'center'><img src = '../images/logo.png' width = '180' /></td></tr>";
		$output.= "		<tr><td colspan = '2' align = 'center' style = 'font-size:29px'><strong>Sétálólap</strong></td></tr>";
		$output.= "		<tr><td style = 'border-right:none'><strong>Név:</strong></td><td style = 'border-left:none'>{$excelArray[$key]['nev']}</td></tr>";
		$output.= "		<tr><td style = 'border-right:none'><strong>Születési idő:</strong></td><td style = 'border-left:none'>".date("Y.m.d",strtotime($result['szuldatum']))."</td></tr>";
		$output.= "	</table>";
			
		$output.= "	<table id = 'examination-list'>";
		$output.= "		<tr><td colspan = '2' style = 'font-size:20px;text-decoration:underline'>Elvégzett vizsgálatok</td></tr>";
		$output.= "		<tr><td><strong>Vizsgálat megnevezése</strong></td><td align = 'center' valign = 'top'><strong>Vizsgálatot végző aláírása</strong></td></tr>";
		$output.= "		<tr><td ".($excelArray[$key]['belgyogy']==""?$style:"")." >Belgyógyászat:</td><td></td></tr>";
		$output.= "		<tr><td ".($excelArray[$key]['hasiuh']==""?$style:"")." >Hasi ultrahang:</td><td></td></tr>";
		$output.= "		<tr><td ".($excelArray[$key]['pajzsuh']==""?$style:"")." >Pajzsmirigy ultrahang:</td><td></td></tr>";
		$output.= "		<tr><td ".($excelArray[$key]['csontsur']==""?$style:"")." >Csontsűrűségmérés:</td><td></td></tr>";
		$output.= "		<tr><td ".($excelArray[$key]['abi']==""?$style:"")." >Boka Kar index:</td><td></td></tr>";
		$output.= "		<tr><td {$laborStyle} >Vérvétel:<br/>".($labor!=""?"<span style = 'font-size:12px'>({$labor})</span>":"")."</td><td></td></tr>";
		$output.= "		<tr><td></td><td></td></tr>";
		$output.= "		<tr><td></td><td></td></tr>";
		$output.= "		<tr><td></td><td></td></tr>";
		$output.= "		<tr><td></td><td></td></tr>";
		$output.= "		<tr><td></td><td></td></tr>";
		$output.= "		<tr><td></td><td></td></tr>";
		
		$output.= "	</table>";
			
		$output.= "	<table id = 'patient-bottom' style = 'margin-top:20px;'>";
		$output.= "		<tr><td align = 'center' style = 'font-size:30px;font-weight:bold;'>Az adatlapot az összes vizsgálat megtörténte után kérjük leadni a koordinátornak!</td></tr>";
		$output.= "		<tr><td style = 'padding-top:40px'>Tatabánya, 2019. ...........................</td></tr>";
		$output.= "	</table>";
		$output.= "</div>";
		
		return $output;
	}
	else return false;
} 
?>

<html>
<head>
<link rel="stylesheet" href="setalolap.css" type="text/css" media="screen"/>
<link rel="stylesheet" href="setalolap.css" type="text/css" media="print"/>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<link rel="stylesheet" href="font-awesome/css/font-awesome.min.css">
<script src = "js/jquery.js"></script>
<script src = "source.js"></script>
<script defer src="https://use.fontawesome.com/releases/v5.0.6/js/all.js"></script>
</head>
<body>

<?php echo createSetalolap($_GET['szerk']) ?>


</body>
</html>
