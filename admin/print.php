<?PHP



session_start();

require_once "../config.php";

error_reporting(E_ALL);
ini_set('display_errors',1);



require_once "ajax.php";



$templates[1]=array("menedzserkerdoiv.html");
$templates[2]=array("alkalmassagi.html");
$templates[3]=array("vizsgalatilap.html");
$templates[4]=array("karton.html");

$templateId=intval($_GET["template"]);


if (!$data=sql_fetch_array(sql_query("select f.*,c.megnev as cegnev from foglalasok f
left join cegek c on c.id=f.cegid
where f.id=? and pass=?",array($_GET["fid"],$_GET["p"])))) die("error code 1254");

if ($templateId==4) {
	
	
	require("fpdf/fpdf.php");

	$pdf = new FPDF("P","mm",array(264,420));
	$pdf->AddPage();
	$pdf->SetFont("Arial","",12);
	$pdf->SetTextColor(0,0,255);


	$pdf->SetXY(160,0);
	$pdf->Cell(100,10,pdfString($data["cegnev"]),0,0,"R");


	$pdf->SetXY(10,10);
	$pdf->Cell(60,10,pdfString($data["nev"]));

	$pdf->SetXY(60,10);
	$pdf->Cell(60,10,pdfString($data["szulhely"]));

	$pdf->SetXY(120,10);
	$pdf->Cell(60,10,pdfString($data["szuldatum"]));

	$pdf->SetXY(60,20);
	$pdf->Cell(60,10,pdfString($data["taj"]));

	$pdf->SetXY(180,20);
	$pdf->Cell(60,10,pdfString($data["anyjaneve"]));
	
	$pdf->SetXY(100,30);
	$pdf->Cell(60,10,pdfString("{$data["irsz"]} {$data["varos"]} {$data["utca"]}"));
	
	$pdf->Output();
	die();
}

header("Content-type: text/html; charset=UTF-8");

if (!isset($templates[$templateId])) die("error code 1255");


$templateData=$templates[$templateId];


$template=file_get_contents("templates/{$templateData[0]}");


$template=str_replace("#nev#",$data["nev"],$template);
$template=str_replace("#foglalkozas#",$data["munkakor"],$template);
$template=str_replace("#szuletesihelyesdatum#",(($data["szulhely"]!=""?"{$data["szulhely"]}, ":"").datumki($data["szuldatum"])),$template);
$template=str_replace("#anyjaneve#",$data["anyjaneve"],$template);
$template=str_replace("#lakcim#","{$data["irsz"]} {$data["varos"]} {$data["utca"]}",$template);
$template=str_replace("#taj#",$data["taj"],$template);
$template=str_replace("#telefon#",$data["telefon"],$template);
$template=str_replace("#email#",$data["email"],$template);
$template=str_replace("#szuldatum#",datumki($data["szuldatum"]),$template);



$keretstyle="border:1px solid #000;display:inline-block;padding:2px 5px;";
$sorkoz=10;

$template=str_replace("#i_alkalmassag#",($data["alkalmassag"]=="I"?"{$keretstyle}":""),$template);
$template=str_replace("#ik_alkalmassag#",($data["alkalmassag"]=="IN"?"{$keretstyle}":""),$template);
$template=str_replace("#n_alkalmassag#",($data["alkalmassag"]=="N"?"{$keretstyle}":""),$template);
//$template=str_replace("#k_alkalmassag#",($data["alkalmassag"]=="K"?"{$keretstyle}":""),$template);
$template=str_replace("#alkalmassagkorl#",($data["alkalmassagkorl"]!=""?"{$data["alkalmassagkorl"]}":"______________________________________"),$template);
$template=str_replace("#alkalmassagikhet#",($data["alkalmassagikhet"]!=""?"{$data["alkalmassagikhet"]}":"____________"),$template);
$template=str_replace("#tudoszuroervenyesseg#",($data["tudoszuroervenyesseg"]!=""?"{$data["tudoszuroervenyesseg"]}":"____________"),$template);
$template=str_replace("#maidatum#",datumki(date("Y-m-d")),$template);
$template=str_replace("#idopont#",substr(datumki($data["datum"]),0,10),$template);
$template=str_replace("#sorkoz#",$sorkoz,$template);

$vlaptipus="Időszakos";
if (isset($_GET["tipus"]) && $_GET["tipus"]="soronkivuli") $vlaptipus="Soron kívüli";

$template=str_replace("#vlaptipus#",$vlaptipus,$template);




if ($data["alkalmassag"]=="I") {
	$ido=intval($data["alkalmassagido"]);
	$template=str_replace("#alkalmasextra#","<div style='margin-top:{$sorkoz}px;'>Érv: ".datumki(date("Y-m-d",strtotime("now +{$ido} month")))."</div>",$template);
} else {
	$template=str_replace("#alkalmasextra#","",$template);
}


/*
<div style='display:table-cell;text-align:center;'><div style='".($data["alkalmassag"]=="I"?"{$keretstyle}":"")."'>ALKALMAS</div></div>
<div style='display:table-cell;text-align:center;'><div style='".($data["alkalmassag"]=="IN"?"{$keretstyle}":"")."'>IDEIGLENESEN NEM ALKALMAS</div></div>
<div style='display:table-cell;text-align:center;'><div style='".($data["alkalmassag"]=="N"?"{$keretstyle}":"")."'>NEM ALKALMAS</div></div>

".($data["alkalmassagkorl"]!=""?"{$data["alkalmassagkorl"]}":"______________________________________")."

#ervenyessegsor#
if ($data["alkalmassag"]=="I") {
	$ido=intval($data["alkalmassagido"]);
	
	<div style='margin-top:{$sorkoz}px;'>Érv: ".datumki(date("Y-m-d",strtotime("now +{$ido} month")))."</div>
}
*/
echo $template;




function datumki($datum) {
	$d=str_replace("-",".",$datum);
	return $d;
}

function pdfString($s) {
	return iconv("UTF-8","ISO-8859-2",$s);
}


?>