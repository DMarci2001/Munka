<?php
include("../../config.php");

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

$result = sql_fetch_array( sql_query("SELECT * FROM felhasznalok WHERE id = ?", array( $_GET['szerk'] )));

require_once( "../Classes/PHPExcel.php" );
$tmpfname 	 = "debrecen.xlsx";
$excelReader = PHPExcel_IOFactory::createReader( 'Excel2007' );
$excelObj 	 = $excelReader->load( $tmpfname );
$worksheet 	 = $excelObj->getSheet(0);
//$lastRow 	 = $excelObj->getHighestRow();
$lastRow = $excelObj->setActiveSheetIndex(0)->getHighestRow();

$excelArray = array();

for($sor = 5; $sor <= $lastRow; $sor++)
{
	$excelArray[] = array( "nev"      			 => $worksheet->getCell('A'.$sor)->getValue(),
						   "neme" 	  			 => $worksheet->getCell('B'.$sor)->getValue(),
						   "csomag_1"   		 => $worksheet->getCell('C'.$sor)->getValue(),
						   "csomag_2"  			 => $worksheet->getCell('D'.$sor)->getValue(),
						   "nap"   				 => $worksheet->getCell('E'.$sor)->getValue(),
						   "alap_tumor_no"  	 => $worksheet->getCell('F'.$sor)->getValue(),
						   "alap_tumor_ferfi"	 => $worksheet->getCell('G'.$sor)->getValue(),
						   "abi" 				 => $worksheet->getCell('H'.$sor)->getValue(),
						   "kieg. nagylabor"   	 => $worksheet->getCell('I'.$sor)->getValue(),
						   "kieg. tumor_csg2"  	 => $worksheet->getCell('J'.$sor)->getValue(),
						   "telj. tumor_csg1" 	 => $worksheet->getCell('K'.$sor)->getValue(),
						   "pajzsmirigy_labor"   => $worksheet->getCell('L'.$sor)->getValue(),
						   "pajzsmirigy_uh"   	 => $worksheet->getCell('M'.$sor)->getValue(),
						   "kedv. 01"    		 => $worksheet->getCell('N'.$sor)->getValue(),
						   "kedv. 02"    		 => $worksheet->getCell('O'.$sor)->getValue(),
						   "carotis_vizsg"    	 => $worksheet->getCell('Q'.$sor)->getValue(),
						   "fizetendo"			 => $worksheet->getCell('P'.$sor)->getValue()	
						  );
}
$key = array_search( $result['nev'], array_column( $excelArray, 'nev' ));

$default_vizsg = "";
$default_labor = "";

//Végig megyek a páciens adat során és kigyűjtöm a megjelölt vizsgálatokat:
foreach($excelArray[$key] as $packName => $value)
{
	if($value == "X" || $value == "x"){
		//Vizsgálatok:
		if($packName=="csomag_1") 		$default_vizsg.=",0,1,4,5";
		if($packName=="csomag_2") 		$default_vizsg.=",0,4,5";
		if($packName=="abi")	  		$default_vizsg.=",1";
		if($packName=="pajzsmirigy_uh") $default_vizsg.=",6";
		if($packName=="carotis_vizsg")  $default_vizsg.=",7";
		
		//Laborok:
		if($packName=="alap_tumor_no") 	   $default_labor.=",0";
		if($packName=="alap_tumor_ferfi")  $default_labor.=",0";
		if($packName=="kieg. tumor_csg2")  $default_labor.=",1";
		if($packName=="telj. tumor_csg1")  $default_labor.=",2";
		if($packName=="kieg. nagylabor")   $default_labor.=",3";
		if($packName=="pajzsmirigy_labor") $default_labor.=",4";
	}
}

//---->FUNCIONS

function vizsg(){
	//Vizsgálatok:
	$vizsg = array();
	$vizsg[0] = "Belgyógyászat";
	$vizsg[1] = "Boka-kar index";
	$vizsg[2] = "12 elvezetéses EKG vizsgálat";
	$vizsg[3] = "Mellkas röntgen";
	$vizsg[4] = "Hasi- és kismedencei ultrahang";
	$vizsg[5] = "Labor";                                                                          
	$vizsg[6] = "Pajzsmirigy ultrahang";
	$vizsg[7] = "Carotis doppler";
	return $vizsg;
}

function labor(){
	//Laborok
	$labor = array();
	$labor[0] = "Alap tumormarker";
	$labor[1] = "Kieg. tumormarker";
	$labor[2] = "Teljes tumormarker";
	$labor[3] = "Nagylabor";
	$labor[4] = "Pajzsmirigy complex";
	return $labor;
}
function setbackpedestrian($default_vizsg,$default_labor){
	$vizsgalatok = "<tr><td colspan = '2' style = 'font-size:20px;text-decoration:underline'>Elvégzett vizsgálatok</td></tr>";
	$vizsgalatok.= "<tr><td style='width:100px'><strong>Vizsgálat megnevezése</strong></td><td align = 'center' valign = 'top'><strong>Vizsgálatot végző aláírása</strong></td></tr>";
	$laborok = "";
	
	//Vizsgálatok:
	$vizsg = vizsg();

	//Laborok
	$labor = labor();
	
	for($i=0;$i<=11;$i++){
		$vizsgalatok.="<tr><td>".(isset($vizsg[$default_vizsg[$i]])?$vizsg[$default_vizsg[$i]]:"");
		if($vizsg[$default_vizsg[$i]]=="Labor" && $default_labor[0]!=""){
			$vizsgalatok.="<br/>(<span style='font-size:12px;'>";
			foreach($default_labor as $index){
				$laborok.=", {$labor[$index]}";
			}
			$laborok = substr($laborok, 2);
			$vizsgalatok.= $laborok;
			$vizsgalatok.="</span>)";
		}
		$vizsgalatok.="</td><td></td></tr>";
	}
	return $vizsgalatok;
}
if(isset($_POST['setbackpedestrian']) && $_POST['setbackpedestrian'] == true){
	$data = "";
	//Értelmezem a POST információkat
	if($_POST['csg_pack']==1) $data.= ",csomag_1";
	if($_POST['csg_pack']==2) $data.= ",csomag_2";
	//az alapnál vizsgálnom kell a páciens nemét is:
	if($_POST['tumor_pack']==1 && $_POST['neme']==2) $data.= ",alap_tumor_no";
	if($_POST['tumor_pack']==1 && $_POST['neme']==1) $data.= ",alap_tumor_ferfi";
	if($_POST['tumor_pack']==2) $data.= ",kieg. tumor_csg2";
	if($_POST['tumor_pack']==3) $data.= ",telj. tumor_csg1";
	//A checkboxonál vizsgálnom kell, hogy létezik-e:
	if(isset($_POST['nagylabor']) && $_POST['nagylabor']==1) $data.= ",kieg. nagylabor";
	if(isset($_POST['pajzsmirigy']) && $_POST['pajzsmirigy']==1) $data.= ",pajzsmirigy_labor";
	if(isset($_POST['abi']) && $_POST['abi']) $data.=",abi";
	if(isset($_POST['pajzsmirigyuh']) && $_POST['pajzsmirigyuh']) $data.=",pajzsmirigy_uh";
	if(isset($_POST['carotis']) && $_POST['carotis']) $data.=",carotis_vizsg";
	//clear
	$data = substr($data, 1);
	$data = explode(",",$data);
	
	$uj_vizsg = "";
	$uj_labor = "";
	foreach($data as $each){
		//Vizsgálatok:
		if($each=="csomag_1") 		$uj_vizsg.=",0,1,4,5";
		if($each=="csomag_2") 		$uj_vizsg.=",0,4,5";
		if($each=="abi")	  		$uj_vizsg.=",1";
		if($each=="pajzsmirigy_uh") $uj_vizsg.=",6";
		if($each=="carotis_vizsg")  $uj_vizsg.=",7";
		//Laborok:
		if($each=="alap_tumor_no") 	   $uj_labor.=",0";
		if($each=="alap_tumor_ferfi")  $uj_labor.=",0";
		if($each=="kieg. tumor_csg2")  $uj_labor.=",1";
		if($each=="telj. tumor_csg1")  $uj_labor.=",2";
		if($each=="kieg. nagylabor")   $uj_labor.=",3";
		if($each=="pajzsmirigy_labor") $uj_labor.=",4";
	}
	//clear
	$uj_vizsg = substr($uj_vizsg, 1);
	$uj_labor = substr($uj_labor, 1);

	//Set Array
	$uj_vizsg = explode(",",$uj_vizsg);
	$uj_labor = explode(",",$uj_labor);
	
	echo setbackpedestrian($uj_vizsg,$uj_labor);
	die();
	
}
//<----FUNCIONS

//---->DEFAULT

//clear
$default_vizsg = substr($default_vizsg, 1);
$default_labor = substr($default_labor, 1);

//Set Array
$default_vizsg = explode(",",$default_vizsg);
$default_labor = explode(",",$default_labor);
//Ha üres labor tömb vesse el.
//if($default_labor[0]=="") unset($default_labor);

//Controll Panel
$default_control = array();
//-->Csomag
if($excelArray[$key]['csomag_1']=="X"||$excelArray[$key]['csomag_1']=="x") $default_control[0] = "checked";
if($excelArray[$key]['csomag_2']=="X"||$excelArray[$key]['csomag_2']=="x") $default_control[1] = "checked";
//-->Tumormarkerek
if($excelArray[$key]['alap_tumor_no']=="X"||$excelArray[$key]['alap_tumor_no']=="x"||$excelArray[$key]['alap_tumor_ferfi']=="X"||$excelArray[$key]['alap_tumor_ferfi']=="x") $default_control[2] = "checked";
if($excelArray[$key]['kieg. tumor_csg2']=="X"||$excelArray[$key]['kieg. tumor_csg2']=="x") $default_control[3] = "checked";
if($excelArray[$key]['telj. tumor_csg1']=="X"||$excelArray[$key]['telj. tumor_csg1']=="x"?"checked":"") $default_control[4] = "checked";
if($excelArray[$key]['telj. tumor_csg1']==""&&$excelArray[$key]['kieg. tumor_csg2']==""&&$excelArray[$key]['alap_tumor_no']==""&&$excelArray[$key]['alap_tumor_ferfi']=="") $default_control[5] = "checked";
//-->Egyéb laborok
if($excelArray[$key]['kieg. nagylabor']=="X"||$excelArray[$key]['kieg. nagylabor']=="x") $default_control[6] = "checked";
if($excelArray[$key]['pajzsmirigy_labor']=="X"||$excelArray[$key]['pajzsmirigy_labor']=="x") $default_control[7] = "checked";
//-->Extra vizsgálatok
if($excelArray[$key]['abi']=="X"||$excelArray[$key]['abi']=="x") $default_control[8] = "checked";
if($excelArray[$key]['pajzsmirigy_uh']=="X"||$excelArray[$key]['pajzsmirigy_uh']=="x") $default_control[9] = "checked";
if($excelArray[$key]['carotis_vizsg']=="X"||$excelArray[$key]['carotis_vizsg']=="x") $default_control[10] = "checked";

//<----DEFAULT

//DEFAULT OUTPUT:
$output = setbackpedestrian($default_vizsg,$default_labor);

?>

<html>
<head>
<link rel="stylesheet" href="setalolap-print.css" type="text/css" media="print"/>
<link rel="stylesheet" href="setalolap.css" type="text/css" media="screen"/>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
<script src = "js/jquery.js"></script>
<script src = "source.js"></script>
<script defer src="https://use.fontawesome.com/releases/v5.0.6/js/all.js"></script>
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

function setbackpedestrian(){
	
	if($('#csg1').prop('checked')==true){
		$('#tumor1').prop('disabled',false);
		$('#tumor2').prop('disabled',true);
		$('#tumor3').prop('disabled',false);
		$('#abi').prop('disabled',true);
		if($('#tumor2').prop('checked')==true){
			$('#tumor2').prop('checked',false);
			$('#tumor4').prop('checked',true);
		}
	}

	if($('#csg2').prop('checked')==true){
		$('#tumor1').prop('disabled',true);
		$('#tumor2').prop('disabled',false);
		$('#tumor3').prop('disabled',true);
		$('#abi').prop('disabled',false);
		if($('#abi').prop('checked')==true) $('#abi').prop('checked',false);
		if($('#tumor1').prop('checked')==true || $('#tumor3').prop('checked')==true){
			$('#tumor1').prop('checked',false);
			$('#tumor3').prop('checked',false);
			$('#tumor4').prop('checked',true);
		}
	} 
	
	var selector = $('#protocol-control-table');
	var data 	 = 'setbackpedestrian=true&'+formSerializer(selector);
	
	$.ajax({
		method:'POST',
		url:'setalolap_02.php',
		data:data,
		success:function(data){
			$('#examination-list').html(data);
			$('#savemodify').css('display','inline-block');
			$('#status-icon').css({'display':'inline-block','color':'red'});
		}
	});
}

function saveModify(){
	var selector = $('#protocol-control-table');
	var data 	 = 'savemodifySP=true&'+formSerializer(selector);
	
	$.ajax({
		method:'POST',
		url:'ajax.php',
		data:data,
		success:function(data){
			if(data==''){
				$('#savemodify').css('display','none');
				$('#status-icon').css('color','#00ba03');
			}
			else alert('Error-404');
		}
	});
}
</script>
</head>
<body>

<div style = "width:198mm;height:277mm;display:inline-block;float:left;">
	<table id = "patient-header">
		<tr><td colspan = "2" align = "center"><img src = "../images/logo.png" width = "180" /></td></tr>
		<tr><td colspan = "2" align = "center" style = "font-size:29px"><strong>Sétálólap</strong></td></tr>
		<tr><td style = "border-right:none"><strong>Név:</strong></td><td style = "border-left:none"><?php echo $result["nev"] ?></td></tr>
		<tr><td style = "border-right:none"><strong>Születési idő:</strong></td><td style = "border-left:none"><?php echo date("Y.m.d",strtotime($result["szuldatum"])) ?></td></tr>
	</table>

	<table id = "examination-list">
		<?php echo $output ?>
	</table>

	<table id = "patient-bottom" style = "margin-top:20px;">
		<tr><td align = "center" style = "font-size:30px;font-weight:bold;">Az adatlapot az összes vizsgálat megtörténte után kérjük leadni a koordinátornak!</td></tr>
		<tr><td style = "padding-top:40px">Debrecen, <?php echo date("Y.m.d.",strtotime("Now")) ?></td></tr>
	</table>
</div>




<table id="protocol-control-table">
<tr><td colspan="2" style="font-size:22px;border-bottom:1px solid black;">Csomagok<input type="hidden" name="neme" id="neme" value="<?php echo $result["neme"]?>"/><input type="hidden" name="nev" id="nev" value="<?php echo $result["nev"] ?>"/></td></tr>
<tr><td colspan="2" height="20"></td></tr>
<tr><td>Csomag I.</td><td><input onChange="setbackpedestrian()" type="radio" <?php echo $default_control[0] ?> name="csg_pack" id="csg1" value="1"></td></tr>
<tr><td>Csomag II.</td><td><input onChange="setbackpedestrian()" type="radio" <?php echo $default_control[1] ?> name="csg_pack" id="csg2" value="2"></td></tr>

<tr><td colspan="2" height="20"></td></tr>

<tr><td colspan="2" style="font-size:22px;border-bottom:1px solid black;">Tumormarkerek</td></tr>
<tr><td colspan="2" height="20"></td></tr>
<tr><td>Alap tumormarkerek</td><td><input onChange="setbackpedestrian()" <?php echo $default_control[2] ?>  type="radio" name="tumor_pack" id="tumor1" value="1"></td></tr>
<tr><td>Kieg. tumormarkerek</td><td><input onChange="setbackpedestrian()" <?php echo $default_control[3] ?>  type="radio" name="tumor_pack" id="tumor2" value="2"></td></tr>
<tr><td>Teljes tumormarkerek</td><td><input onChange="setbackpedestrian()" <?php echo $default_control[4] ?> type="radio" name="tumor_pack" id="tumor3" value="3"></td></tr>
<tr><td>Egyik sem</td><td><input onChange="setbackpedestrian()" <?php echo $default_control[5] ?> type="radio" name="tumor_pack" id="tumor4" value="4"></td></tr>

<tr><td colspan="2" height="20"></td></tr>

<tr><td colspan="2" style="font-size:22px;border-bottom:1px solid black;">Egyéb laborok</td></tr>
<tr><td>Kieg. Nagylabor</td><td><input onChange="setbackpedestrian()" <?php echo $default_control[6] ?> type="checkbox" name="nagylabor" id="nagylabor" value="1"></td></tr>
<tr><td>Pajzsmirigy lab. complex</td><td><input onChange="setbackpedestrian()" <?php echo $default_control[7] ?> type="checkbox" name="pajzsmirigy" id="pajzsmirigy" value="1"></td></tr>

<tr><td colspan="2" height="20"></td></tr>

<tr><td colspan="2" style="font-size:22px;border-bottom:1px solid black;">Extra Vizsgálatok</td></tr>
<tr><td>Boka-kar index</td><td><input onChange="setbackpedestrian()" <?php echo $default_control[8] ?> type="checkbox" name="abi" id="abi" value="1"></td></tr>
<tr><td>Pajzsmirigy ultrahang</td><td><input onChange="setbackpedestrian()" <?php echo $default_control[9] ?> type="checkbox" name="pajzsmirigyuh" id="pajzsmirigyuh" value="1"></td></tr>
<tr><td>Carotis doppler</td><td><input onChange="setbackpedestrian()" <?php echo $default_control[10] ?> type="checkbox" name="carotis" id="carotis" value="1"></td></tr>

<tr><td colspan="2"><div id="savemodify" onClick="saveModify()" style="margin-top:20px;margin-right:20px;display:none" class="ujbutton">Mentés</div><i style="margin-top:10px;font-size:20px;display:none" id="status-icon" class="fas fa-circle"></i></td></tr>
</table>

</body>
</html>
