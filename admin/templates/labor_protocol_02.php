<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("../../config.php");
include("../ajax.php");

//A páciens nevéből megtudom állapítani a laborhoz szükséges információkat:
require_once( "../Classes/PHPExcel.php" );
$tmpfname 	 = "debrecen.xlsx";
$excelReader = PHPExcel_IOFactory::createReader( 'Excel2007' );
$excelObj 	 = $excelReader->load( $tmpfname );
$worksheet 	 = $excelObj->getSheet(0);
//$lastRow 	 = $excelObj->getHighestRow();
$lastRow = $excelObj->setActiveSheetIndex(0)->getHighestRow();

for($sor = 5; $sor <= $lastRow; $sor++)
{
	$excelArray[] = array( "nev"      			 => $worksheet->getCell('A'.$sor)->getValue(),
						   "csomag_1"   		 => $worksheet->getCell('C'.$sor)->getValue(),
						   "csomag_2"  			 => $worksheet->getCell('D'.$sor)->getValue(),
						   "alap_tumor_no"  	 => $worksheet->getCell('F'.$sor)->getValue(),
						   "alap_tumor_ferfi"	 => $worksheet->getCell('G'.$sor)->getValue(),
						   "kieg. nagylabor"   	 => $worksheet->getCell('I'.$sor)->getValue(),
						   "kieg. tumor_csg2"  	 => $worksheet->getCell('J'.$sor)->getValue(),
						   "telj. tumor_csg1" 	 => $worksheet->getCell('K'.$sor)->getValue(),
						   "pajzsmirigy_labor"   => $worksheet->getCell('L'.$sor)->getValue()
						  );
}

$labMatts = array();
$labMatts[] = array("megnev" => "csomag_1", "materials" => "1,9,10,11,12,21,22,27,42,33,17,18,16");
$labMatts[] = array("megnev" => "csomag_2", "materials" => "1,9,10,11,12,21,22,27,42,33,17,18,16,70,69");
$labMatts[] = array("megnev" => "alap_tumor_no", "materials" => "70");
$labMatts[] = array("megnev" => "alap_tumor_ferfi", "materials" => "69");
$labMatts[] = array("megnev" => "kieg. nagylabor", "materials" => "79,20,26,13,5,4,3,7,19,35,46,80,8,81,39,49");
$labMatts[] = array("megnev" => "telj. tumor_csg1", "materials" => "66,67,68,70,69");
$labMatts[] = array("megnev" => "kieg. tumor_csg2", "materials" => "66,67,68");
$labMatts[] = array("megnev" => "pajzsmirigy_labor","materials" => "44,43,83,84");

function outputLabs($labStringArray,$neme){
	
	$labStringArray = array_unique($labStringArray);
	$labStringArray = array_values($labStringArray);
	//Férfi == 1 Nő == 2
	if($neme==1)
	{
		$key = array_search("70",$labStringArray);
		if($key!==FALSE) unset($labStringArray[$key]);
		$labStringArray = array_values($labStringArray);
	}
	if($neme==2)
	{
		$key = array_search("69",$labStringArray);
		if($key!==FALSE) unset($labStringArray[$key]);
		$labStringArray = array_values($labStringArray);
	}
	
	$etc 		 = "<tr><td><i>Egyéb</i></td></tr>";
	$hema 		 = "<tr><td><i>Hematológia</i></td></tr>";
	$kemia 		 = "<tr><td><i>Kémia</i></td></tr>";
	$tumor 		 = "<tr><td><i>Tumormarker</i></td></tr>";
	$veralavadas = "<tr><td><i>Véralvadás</i></td></tr>";
	$vizelet	 = "<tr><td><i>Vizelet</i></td></tr>";

	
	foreach($labStringArray as $lab){
		$result = sql_fetch_array(sql_query("SELECT * FROM labor_mintak WHERE minta_id = {$lab}"));
		
		if($result['minta_kategoria'] == "Egyéb") 	  $etc.= "<tr><td>{$result['minta_nev']}</td></tr>";
		if($result['minta_kategoria'] == "Hematológia") $hema.= "<tr><td>{$result['minta_nev']}</td></tr>";
		if($result['minta_kategoria'] == "Kémia") 	  $kemia.= "<tr><td>{$result['minta_nev']}</td></tr>";
		if($result['minta_kategoria'] == "Tumormarker") $tumor.= "<tr><td>{$result['minta_nev']}</td></tr>";
		if($result['minta_kategoria'] == "Véralvadás")  $veralavadas.= "<tr><td>{$result['minta_nev']}</td></tr>";
		if($result['minta_kategoria'] == "Vizelet") 	  $vizelet.= "<tr><td>{$result['minta_nev']}</td></tr>";
	}
	return( array($kemia,$hema,$veralavadas,$etc,$vizelet,$tumor));
}

if(isset($_POST['setLaborPage']) && $_POST['setLaborPage']==true){
	
	header("Content-type: application/json");
	$data = "";
	$labString = "";
	if($_POST['csg_pack']==1) $data.= ",csomag_1";
	if($_POST['csg_pack']==2) $data.= ",csomag_2";
	if($_POST['tumor_pack']==1 && $_POST['neme']==2) $data.= ",alap_tumor_no";
	if($_POST['tumor_pack']==1 && $_POST['neme']==1) $data.= ",alap_tumor_ferfi";
	if($_POST['tumor_pack']==2) $data.= ",kieg. tumor_csg2";
	if($_POST['tumor_pack']==3) $data.= ",telj. tumor_csg1";
	if(isset($_POST['nagylabor'])&&$_POST['nagylabor']==1) $data.= ",kieg. nagylabor";
	if(isset($_POST['pajzsmirigy'])&&$_POST['pajzsmirigy']==1) $data.= ",pajzsmirigy_labor";
	
	$data = substr($data, 1);
	$data = explode(",",$data);
	
	foreach($data as $each){
		$labor = array_search($each,array_column($labMatts,"megnev"));
		$labString.= ",".$labMatts[$labor]['materials'];
	}
	$labString = substr($labString, 1);
	$labStringArray = explode(",",$labString);
	//echo $labString;
	$output = outputLabs($labStringArray,$_POST['neme']);
	
	die(json_encode(array($output[0],$output[1],$output[2],$output[3],$output[4],$output[5])));
}



$patient = sql_fetch_array( sql_query("SELECT * FROM felhasznalok WHERE id = ?", array( $_REQUEST['szerk'] )));

$taj = chunk_split($patient['taj'], 3, ' - ');
$taj = substr($taj,0,-3);






$key = array_search( $patient['nev'], array_column( $excelArray, 'nev' ));

//echo "<pre>";
//print_r($excelArray[$key]);
//echo "</pre>";

$labOverall = array();
$labString = "";

//adott az összes lista érték, létre kell hoznom egy gyűjtő tömböt, amiben össze sorolom az összes vizsgálatot, és a duplikációkat kiütöm.
foreach($excelArray[$key] as $packName => $value)
{
	if($value == "X" || $value == "x"){
		$labor = array_search($packName,array_column($labMatts,"megnev"));
		$labString.= ",".$labMatts[$labor]['materials'];
	}
}

$labString = substr($labString, 1);
$labStringArray = explode(",",$labString);
$labStringArray = array_unique($labStringArray);
$labStringArray = array_values($labStringArray);

//$labStringPro = "";
//foreach($labStringArray as $score) $labStringPro.=",".$score;
//$labStringPro = substr($labStringPro, 1);

$output = outputLabs($labStringArray,$patient['neme']);

//echo "<pre>";
//echo $output;
//echo "</pre>";

//echo "<pre>";
//print_r($labMatts);
//echo "</pre>";

$userLabArray = array("csomag");
?>
<html>
<title>Labor Protocoll</title>
<header>
<link rel="stylesheet" href="print-style.css" type="text/css" media="print"/>
<link rel="stylesheet" href="style.css" type="text/css" media="screen"/>
<script src="https://code.jquery.com/jquery-latest.js"></script>
<script type="text/javascript" src = "source.js"></script>
<script>

function formSerializer(selector){
  data="";
   // look for every form field
  selector.find('input, select, textarea').each(function(){
     // serialize data
	//Radio gomb kezelés:
	if($(this).attr("type")=="radio" && $(this).prop("checked")!=true) return true;
	//Checkbox kezelés:
	if($(this).attr("type")=="checkbox" && $(this).prop("checked")!=true) return true;
	
	//Normál kezelés:
    data+=$(this).attr("name")+"="+$(this).val()+"&";
  });
// remove the last & char
return data.replace(/&$/g,"");
}

function setLaborPage()
{
	var selector = $('#protocol-control-table');
	var data 	 = 'setLaborPage=true&'+formSerializer(selector);
	console.log(data);
	$.ajax({
			method:'POST',
			url:'labor_protocol_02.php',
			data:data,
			success:function(data){
				$('#kemia-lista').html(data[0]);
				$('#hematologia-lista').html(data[1]);
				$('#veralvadas-lista').html(data[2]);
				$('#egyeb-lista').html(data[3]);
				$('#vizelet-lista').html(data[4]);
				$('#tumormarker-lista').html(data[5]);
			}
		});
}
</script>
</header>
<body>
<div class = "wrapper" style = "float:left;">
	<span class = "page-title">RUTIN VIZSGÁLATOK KÉRŐLAPJA</span>
	<span class = "top-gray-line"></span>
	<!--2018.05.10 13:18-->
	<span class="top-date-box"><i>Dátum:</i> <?php echo date("Y.m.d G:i"); ?></span>
	<table class="patient-details-table" id="PDT-row-01">
		<tr>
			<td><i>Beteg neve:</i>&emsp;&emsp;<strong style = "color:#000;"><?php echo $patient['nev'] ?></strong></td>
			<td><i>TAJ:</i>&emsp;<?php echo $taj ?></td>
		</tr>
	</table>

	<table class="patient-details-table" id="PDT-row-02">
		<tr>
			<td><i>Anyja neve:</i>&emsp;&emsp;<?php echo $patient['anyjaneve'] ?></td>
			<td><i>Leánykori neve:</i></td>
		</tr>
	</table>

	<table class="patient-details-table" id="PDT-row-03">
		<tr>
			<td><i>Neme:</i>&emsp;&emsp;<?php echo ( $patient['neme'] == 1 ? "férfi" : "nő" ) ?></td>
			<td><i>Születési idő:</i>&emsp;<?php echo date( "Y.m.d", strtotime( $patient['szuldatum'] )) ?></td>
		</tr>
	</table>

	<table class="patient-details-table" id="PDT-row-04">
		<tr>
			<td><i>Lakcím:</i>&emsp;&emsp;<?php echo $patient['irsz']." ".$patient['varos'].", ".$patient['utca'] ?></td>
			<td><i>Naplószám:</i></td>
		</tr>
	</table>

	<table class = "core-details-table">
		<tr>
			<td><i>Osztálykód:</i>&emsp;&emsp;000000719</td>
			<td><i>Orvos:</i></td>
			<td><i>Diagnózis:</i></td>
		</tr>
		<tr>
			<td colspan = "3">
				<i>Térítési kategória:</i>&emsp;&emsp;&emsp;4. Térítésköteles járó
			</td>
		</tr>
	</table>
	<div class = "modul-box">
		<table class = "s1-modul-table" id = "kemia-lista" style = "margin-right:5px;">
			<!--<tr><td><i>Kémia</i></td></tr>-->
			<?php echo $output[0] ?>
		</table>
		<div class = "s1-modul-table" style = "margin-right:5px;border:none;">
			<table class = "s2-modul-table" id = "hematologia-lista" style = "margin-bottom:5px;">
				<!--<tr><td><i>Hematológia</i></td></tr>-->
				<?php echo $output[1] ?>
			</table>
			<table class = "s2-modul-table" id = "veralvadas-lista">
				<!--<tr><td><i>Véralvadás</i></td></tr>-->
				<?php echo $output[2] ?>
			</table>
			<table class = "s2-modul-table" id = "egyeb-lista">
				<!--<tr><td><i>Egyéb</i></td></tr>-->
				<?php echo $output[3] ?>
			</table>
		</div>
		<div class = "s1-modul-table" id = "s3-scales">
		<table class = "s2-modul-table" id = "vizelet-lista">
			<!--<tr><td><i>Vizelet</i></td></tr>-->
			<?php echo $output[4] ?>
		</table>
		<table class = "s2-modul-table" id = "tumormarker-lista">
			<!--<tr><td><i>Tumormarker</i></td></tr>-->
			<?php echo $output[5] ?>
		</table>
		<table class = "s2-modul-table" id = "third-modul-table">
			<tr><td><i>Speciális labor</i></td></tr>
			<tr rowspan = "8"><td style="">
			<textarea></textarea>
			</td></tr>
		</table>
		</div>
	</div>
	<div class = "comment-box">
	<span>Megjegyzés:</span>
	<textarea></textarea>
	</div>
	<div class = "docketing-box">
	<span><i>Kelt:</i><input type = "textbox" placeholder = "Budapest" />, <?php echo date("Y.m.d") ?></span>
	</div>
	<div class = "signature-box">
	<span>Aláírás:</span>
	</div>
</div>
<div class = "protocol-select-box">
<style>
#protocol-control-table{
	border-collapse: collapse;
}
#protocol-control-table td{
	//text-align:center;
	vertical-align:middle;
	//border:1px solid black;
	padding:5px;
}
</style>
<?php
//Kontroll táblázat kezelő felület:
$htmlout = "";

$htmlout.= "<table id='protocol-control-table'>";
$htmlout.= "	<tr><td colspan='2' style='font-size:22px;border-bottom:1px solid black;'>Csomagok<input type='hidden' name='neme' id='neme' value='{$patient['neme']}'></td></tr>";
$htmlout.= "	<tr><td colspan='2' height='20'></td></tr>";
$htmlout.= "	<tr><td>Csomag I.</td><td><input onChange='setLaborPage()' ".($excelArray[$key]['csomag_1']=="X"||$excelArray[$key]['csomag_1']=="x"?"checked":"")." type='radio' name='csg_pack' id='csg1' value='1'></td></tr>";
$htmlout.= "	<tr><td>Csomag II.</td><td><input onChange='setLaborPage()' ".($excelArray[$key]['csomag_2']=="X"||$excelArray[$key]['csomag_2']=="x"?"checked":"")." type='radio' name='csg_pack' id='csg2' value='2'></td></tr>";

$htmlout.= "	<tr><td colspan='2' height='20'></td></tr>";

$htmlout.= "	<tr><td colspan='2' style='font-size:22px;border-bottom:1px solid black;'>Tumormarkerek</td></tr>";
$htmlout.= "	<tr><td colspan='2' height='20'></td></tr>";
$htmlout.= "	<tr><td>Alap tumormarkerek</td><td><input onChange='setLaborPage()' ".($excelArray[$key]['alap_tumor_no']=="X"||$excelArray[$key]['alap_tumor_no']=="x"||$excelArray[$key]['alap_tumor_ferfi']=="X"||$excelArray[$key]['alap_tumor_ferfi']=="x"?"checked":"")." type='radio' name='tumor_pack' id='tumor1' value='1'></td></tr>";
$htmlout.= "	<tr><td>Kieg. tumormarkerek</td><td><input onChange='setLaborPage()' ".($excelArray[$key]['kieg. tumor_csg2']=="X"||$excelArray[$key]['kieg. tumor_csg2']=="x"?"checked":"")." type='radio' name='tumor_pack' id='tumor2' value='2'></td></tr>";
$htmlout.= "	<tr><td>Teljes tumormarkerek</td><td><input onChange='setLaborPage()' ".($excelArray[$key]['telj. tumor_csg1']=="X"||$excelArray[$key]['telj. tumor_csg1']=="x"?"checked":"")." type='radio' name='tumor_pack' id='tumor3' value='3'></td></tr>";
$htmlout.= "	<tr><td>Egyik sem</td><td><input onChange='setLaborPage()' type='radio' ".($excelArray[$key]['telj. tumor_csg1']==""&&$excelArray[$key]['kieg. tumor_csg2']==""&&$excelArray[$key]['alap_tumor_no']==""&&$excelArray[$key]['alap_tumor_ferfi']==""?"checked":"")." name='tumor_pack' id='tumor4' value='4'></td></tr>";

$htmlout.= "	<tr><td colspan='2' height='20'></td></tr>";

$htmlout.= "	<tr><td colspan='2' style='font-size:22px;border-bottom:1px solid black;'>Egyéb</td></tr>";
$htmlout.= "	<tr><td>Kieg. Nagylabor</td><td><input onChange='setLaborPage()' ".($excelArray[$key]['kieg. nagylabor']=="X"||$excelArray[$key]['kieg. nagylabor']=="x"?"checked":"")." type='checkbox' name='nagylabor' id='nagylabor' value='1'></td></tr>";
$htmlout.= "	<tr><td>Pajzsmirigy lab. complex</td><td><input onChange='setLaborPage()' ".($excelArray[$key]['pajzsmirigy_labor']=="X"||$excelArray[$key]['pajzsmirigy_labor']=="x"?"checked":"")." type='checkbox' name='pajzsmirigy' id='pajzsmirigy' value='1'></td></tr>";
$htmlout.= "";
$htmlout.= "</table>";
echo $htmlout;
?>

</div>
</body>
</html>