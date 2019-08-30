<?php
//echo "itt vagyok!";
error_reporting(E_ALL);
ini_set('display_errors', 1);

include( "../../config.php" );
require("../fpdf/fpdf.php");

function pdfString($s) {
	return iconv("UTF-8","ISO-8859-2",$s);
}
//if( !isset($_GET['id'] )) header( "Location:index.php" );
$result = sql_fetch_array( sql_query( "SELECT * FROM GDPR WHERE id = ?", array( $_REQUEST['id'] )));

//Taj szám darabolása 3 részre + kötőjelek be illesztése:
$taj = chunk_split( $result['taj'], 3, ' - ' );
$taj = substr( $taj, 0, -3 );

$kelte = date( "Y.m.d", strtotime( $result['kelte'] ));

if($result['munkahely']!= "")
{
	$ceg = sql_fetch_array( sql_query( "SELECT * FROM cegek WHERE id = ?", array( $result['munkahely'] )));
	$munkahely = $ceg["megnev"];
}
else $munkahely = "";
	

//Aláírás fájlba mentése:
$encoded_image = explode(",", $result['URI'])[1];
$decoded_image = base64_decode($encoded_image);
file_put_contents("../../doc/{$_REQUEST['id']}.png", $decoded_image);

//require("fpdf/makefont/makefont.php");
//MakeFont('C:\\xampp\\htdocs\\create_pdf\\Montserrat-Medium.ttf','cp1252');

//FPDF inicializálása:
$pdf = new FPDF('p', 'mm', 'A4');
$pdf->AddPage();
//211mm széles

//meg akadájozza, hogy az alsó margin átlépésekor laptörést generáljjon, ezáltal növelem a lap terjedelmét.
$pdf->SetAutoPageBreak(false);

//Tartalom:
$pdf->AddFont('Montserrat-Black','','Montserrat-Black.php');
$pdf->AddFont('Montserrat-Italic','','Montserrat-Italic.php');
$pdf->AddFont('Montserrat-Light','','Montserrat-Light.php');
$pdf->AddFont('Montserrat-Medium','','Montserrat-Medium.php');

$pdf->Image('../../images/hmm_logo_nagy.png',65,10,0,0);

if ( $_REQUEST['adatlap'] == "magán" )
{

	// 1. Width, height, text, border, end-line, align
	$pdf->SetXY(0,50);
	$pdf->SetFont('Montserrat-Black', '', 18);
	$pdf->Cell(211,10,pdfString('Páciens Adatlap'),0,1,'C');

	//Név(vastag):
	$pdf->SetXY(36.5,62);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Név:'),1,0);

	//Név(vékony):
	$pdf->SetXY(47.5,62);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['nev'] ),0,0);

	//Születési idő és hely(vastag):
	$pdf->SetXY(36.5,72);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString( 'Születési idő és hely:' ),1,0);

	//Születési idő és hely(vékony):
	$pdf->SetXY(82.5,72);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( date("Y-m-d", strtotime( $result['szuldatum'] )).", ".$result['szulhely']),0,0);

	//Taj(vastag):
	$pdf->SetXY(36.5,82);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('TAJ:'),1,0);

	//Taj(vékony):
	$pdf->SetXY(47.5,82);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $taj ),0,0);

	//Anyja neve(vastag):
	$pdf->SetXY(36.5,92);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString( 'Anyja leánykori neve:' ),1,0);

	//Anyja neve(vékony):
	$pdf->SetXY(84.5,92);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['anyjaneve'] ),0,0);

	//Lakcím(vastag):
	$pdf->SetXY(36.5,102);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Lakcím:'),1,0);

	//Lakcím(vékony):
	$pdf->SetXY(54.5,102);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['lakcim'] ),0,0);

	//Lev. cím(vastag):
	$pdf->SetXY(36.5,112);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Levelezési cím:'),1,0);

	//Lev. cím(vékony):
	$pdf->SetXY(70.5,112);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(104,10,pdfString($result['levelezesicim']),0,0);


	//Telefon(vastag):
	$pdf->SetXY(36.5,122);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Telefon:'),1,0);
	//Telefon(vékony):
	$pdf->SetXY(55.5,122);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(119,10,pdfString($result['telefon']),0,0);

	//Email(vastag):
	$pdf->SetXY(36.5,132);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Email cím:'),1,0);

	//Email(vékony):
	$pdf->SetXY(60.5,132);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(114,10,pdfString($result['email']),0,0);

	//Táblázat alatti szöveg mező:
	$pdf->SetFont('Montserrat-Light', '',11);
	$pdf->SetXY(15,152);
	$pdf->Cell(181,5,pdfString('A   Szolgáltató   adatkezelésére   a   személyes   adatok  védelméről   és    a   közérdekű  adatok '),0,1);
	$pdf->SetXY(15,157);
	$pdf->Cell(181,5,pdfString('nyilvánosságáról   szóló   GDPR  (E.U.2016/679)  irányadó.   Az   adatszolgáltatás   önkéntes.   Az '),0,1);
	$pdf->SetXY(15,162);
	$pdf->Cell(181,5,pdfString('adatkezelés célja az Adatkezelő által vállalt szolgáltatások és kötelezettségek teljesítése, jogok '),0,1);
	$pdf->SetXY(15,167);
	$pdf->Cell(181,5,pdfString('érvényesítése, az ügyfél azonosítása, az Ügyféllel való kapcsolattartás és kommunikáció.'),0,1);

	//Második bekezdés:
	$pdf->SetXY(15,177);
	$pdf->Cell(181,5,pdfString('További  személyes  adatok  kezelése   törvényi   felhatalmazáson   alapulhat,   amelynek   célja '),0,1);
	$pdf->SetXY(15,182);
	$pdf->Cell(181,5,pdfString('jogszabályi  kötelezettségek  teljesítése.  Kezelt  adatok:  Név,  születési  idő  és  hely,  TAJ szám, '),0,1);
	$pdf->SetXY(15,187);
	$pdf->Cell(181,5,pdfString('anyja leánykori neve, lakcím.'),0,1);

	//Első Checkbox helye(adatkuldes):
	$pdf->Image('../../images/'.($result['adatkuldes'] == 1?"checked.png":"unchecked.png"),20,196.5,0,0);
	$pdf->SetXY(30,197);
	$pdf->Cell(181,5,pdfString('Hozzájárulok,  hogy  az  adatkezelő  részemre  vizsgálati  eredményeimet  elektronikus '),0,1);
	$pdf->SetXY(30,202);
	$pdf->Cell(181,5,pdfString('úton e-mailben, küldje meg az Ügyfél által megadott email címre. '),0,1);

	//Második Checkbox helye(labor):
	$pdf->Image('../../images/'.($result['labor'] == 1?"checked.png":"unchecked.png"),20,211.5,0,0);
	$pdf->SetXY(30,212);
	$pdf->Cell(181,5,pdfString('Hozzájárulok, hogy az Adatkezelő laboratóriumi vizsgálat esetén, a Szolgáltató a vele '),0,1);
	$pdf->SetXY(30,217);
	$pdf->Cell(181,5,pdfString('szerződésben álló laboratóriumi részére személyes adatait elküldje. '),0,1);

	//Harmadik Checkbox helye(tajekoztato):
	$pdf->Image('../../images/'.($result['tajekoztato'] == 1?"checked.png":"unchecked.png"),20,226.5,0,0);
	$pdf->SetXY(30,227);
	$pdf->Cell(181,5,pdfString('Hozzájárulok, hogy részemre szolgáltatásainkról tájékoztatót küldjenek.'),0,1);

	//Alsó szöveg mező helye:
	$pdf->SetXY(15,237);
	$pdf->Cell(181,5,pdfString('Jelen  hozzájáruló  nyilatkozat  bármikor  korlátozás,  feltétel  és  indoklás  nélkül  visszavonható. '),0,1);
	$pdf->SetXY(15,242);
	$pdf->Cell(181,5,pdfString('Kijelentem,   hogy   ezen   hozzájárulásomat   önkéntesen   minden   külső   befolyás   nélkül,    a '),0,1);
	$pdf->SetXY(15,247);
	$pdf->Cell(181,5,pdfString('megfelelő tájékoztatás és  a  vonatkozó  jogszabályi  rendelkezések  ismeretében  tettem  meg.'),0,1);

	//Kelte:
	$pdf->SetXY(20,270);
	$pdf->Cell(211,5,pdfString('Budapest, '.$kelte),0,1);

	//Aláírása rész:
	$pdf->Image("../../doc/{$_REQUEST['id']}.png",140,276,30,0);
	$pdf->SetXY(132,275);
	$pdf->Cell(50,0,"",1,1);
	$pdf->SetXY(142,278);
	$pdf->Cell(211,5,pdfString('Ügyfél aláírása'),0,1);
	//$pdf->Output("{$result['nev']}.pdf","F");
	if( isset( $_REQUEST['method'] ) && $_REQUEST['method'] == "create" )  $pdf->Output("../../doc/GDPR/{$_REQUEST['id']}.pdf", "F");
	if( isset( $_REQUEST['method'] ) && $_REQUEST['method'] == "preview" ) $pdf->Output("{$_REQUEST['id']}.pdf", "I");
}

if ( $_REQUEST['adatlap'] == "menedzser" )
{

	// 1. Width, height, text, border, end-line, align
	$pdf->SetXY(0,50);
	$pdf->SetFont('Montserrat-Black', '', 18);
	$pdf->Cell(211,10,pdfString('Menedzser Adatlap'),0,1,'C');

	//Név(vastag):
	$pdf->SetXY(36.5,62);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Név:'),1,0);

	//Név(vékony):
	$pdf->SetXY(47.5,62);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['nev'] ),0,0);

	//Születési idő és hely(vastag):
	$pdf->SetXY(36.5,72);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString( 'Születési idő és hely:' ),1,0);

	//Születési idő és hely(vékony):
	$pdf->SetXY(82.5,72);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( date("Y-m-d", strtotime( $result['szuldatum'] )).", ".$result['szulhely']),0,0);

	//Taj(vastag):
	$pdf->SetXY(36.5,82);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('TAJ:'),1,0);

	//Taj(vékony):
	$pdf->SetXY(47.5,82);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $taj ),0,0);

	//Anyja neve(vastag):
	$pdf->SetXY(36.5,92);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString( 'Anyja leánykori neve:' ),1,0);

	//Anyja neve(vékony):
	$pdf->SetXY(84.5,92);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['anyjaneve'] ),0,0);

	//Lakcím(vastag):
	$pdf->SetXY(36.5,102);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Lakcím:'),1,0);

	//Lakcím(vékony):
	$pdf->SetXY(54.5,102);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['lakcim'] ),0,0);

	//Lev. cím(vastag):
	$pdf->SetXY(36.5,112);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Levelezési cím:'),1,0);

	//Lev. cím(vékony):
	$pdf->SetXY(70.5,112);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(104,10,pdfString($result['levelezesicim']),0,0);

	//Telefon(vastag):
	$pdf->SetXY(36.5,122);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Telefon:'),1,0);

	//Telefon(vékony):
	$pdf->SetXY(55.5,122);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(119,10,pdfString($result['telefon']),0,0);

	//Email(vastag):
	$pdf->SetXY(36.5,132);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Email cím:'),1,0);

	//Email(vékony):
	$pdf->SetXY(60.5,132);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(114,10,pdfString($result['email']),0,0);

	//Táblázat alatti szöveg mező:
	$pdf->SetFont('Montserrat-Light', '',11);
	$pdf->SetXY(15,152);
	$pdf->Cell(181,5,pdfString('A   Szolgáltató   adatkezelésére   a   személyes   adatok  védelméről   és    a   közérdekű  adatok '),0,1);
	$pdf->SetXY(15,157);
	$pdf->Cell(181,5,pdfString('nyilvánosságáról   szóló   GDPR  (E.U.2016/679)  irányadó.   Az   adatszolgáltatás   önkéntes.   Az '),0,1);
	$pdf->SetXY(15,162);
	$pdf->Cell(181,5,pdfString('adatkezelés célja az Adatkezelő által vállalt szolgáltatások és kötelezettségek teljesítése, jogok '),0,1);
	$pdf->SetXY(15,167);
	$pdf->Cell(181,5,pdfString('érvényesítése, az ügyfél azonosítása, az Ügyféllel való kapcsolattartás és kommunikáció.'),0,1);

	//Második bekezdés:
	$pdf->SetXY(15,177);
	$pdf->Cell(181,5,pdfString('További  személyes  adatok  kezelése   törvényi   felhatalmazáson   alapulhat,   amelynek   célja '),0,1);
	$pdf->SetXY(15,182);
	$pdf->Cell(181,5,pdfString('jogszabályi  kötelezettségek  teljesítése.  Kezelt  adatok:  Név,  születési  idő  és  hely,  TAJ szám, '),0,1);
	$pdf->SetXY(15,187);
	$pdf->Cell(181,5,pdfString('anyja leánykori neve, lakcím.'),0,1);

	//Első Checkbox helye(adatkuldes):
	$pdf->Image('../../images/'.($result['adatkuldes'] == 1?"checked.png":"unchecked.png"),20,196.5,0,0);
	$pdf->SetXY(30,197);
	$pdf->Cell(181,5,pdfString('Hozzájárulok,  hogy  az  adatkezelő  részemre  vizsgálati  eredményeimet  elektronikus '),0,1);
	$pdf->SetXY(30,202);
	$pdf->Cell(181,5,pdfString('úton e-mailben, küldje meg az Ügyfél által megadott email címre. '),0,1);

	//Második Checkbox helye(labor):
	$pdf->Image('../../images/'.($result['labor'] == 1?"checked.png":"unchecked.png"),20,211.5,0,0);
	$pdf->SetXY(30,212);
	$pdf->Cell(181,5,pdfString('Hozzájárulok, hogy az Adatkezelő laboratóriumi vizsgálat esetén, a Szolgáltató a vele '),0,1);
	$pdf->SetXY(30,217);
	$pdf->Cell(181,5,pdfString('szerződésben álló laboratóriumi részére személyes adatait elküldje. '),0,1);

	//Harmadik Checkbox helye(tajekoztato):
	$pdf->Image('../../images/'.($result['tajekoztato'] == 1?"checked.png":"unchecked.png"),20,226.5,0,0);
	$pdf->SetXY(30,227);
	$pdf->Cell(181,5,pdfString('Hozzájárulok, hogy részemre szolgáltatásainkról tájékoztatót küldjenek.'),0,1);

	//Alsó szöveg mező helye:
	$pdf->SetXY(15,237);
	$pdf->Cell(181,5,pdfString('Jelen  hozzájáruló  nyilatkozat  bármikor  korlátozás,  feltétel  és  indoklás  nélkül  visszavonható. '),0,1);
	$pdf->SetXY(15,242);
	$pdf->Cell(181,5,pdfString('Kijelentem,   hogy   ezen   hozzájárulásomat   önkéntesen   minden   külső   befolyás   nélkül,    a '),0,1);
	$pdf->SetXY(15,247);
	$pdf->Cell(181,5,pdfString('megfelelő tájékoztatás és  a  vonatkozó  jogszabályi  rendelkezések  ismeretében  tettem  meg.'),0,1);

	//Kelte:
	$pdf->SetXY(20,270);
	$pdf->Cell(211,5,pdfString('Budapest, '.$kelte),0,1);

	//Aláírása rész:
	$pdf->Image("../../doc/{$_REQUEST['id']}.png",140,276,30,0);
	$pdf->SetXY(132,275);
	$pdf->Cell(50,0,"",1,1);
	$pdf->SetXY(142,278);
	$pdf->Cell(211,5,pdfString('Ügyfél aláírása'),0,1);
	//$pdf->Output("{$result['nev']}.pdf","F");
	if( isset( $_REQUEST['method'] ) && $_REQUEST['method'] == "create" )  $pdf->Output("../../doc/GDPR/{$_REQUEST['id']}.pdf", "F");
	if( isset( $_REQUEST['method'] ) && $_REQUEST['method'] == "preview" ) $pdf->Output("{$_REQUEST['id']}.pdf", "I");
}

if ( $_REQUEST['adatlap'] == "üzemorvosi" )
{
	// 1. Width, height, text, border, end-line, align
	$pdf->SetXY(0,50);
	$pdf->SetFont('Montserrat-Black', '', 18);
	$pdf->Cell(211,10,pdfString('Üzemorvosi Adatlap'),0,1,'C');

	//Név(vastag):
	$pdf->SetXY(36.5,62);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Név:'),1,0);

	//Név(vékony):
	$pdf->SetXY(47.5,62);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['nev'] ),0,0);

	//Születési idő és hely(vastag):
	$pdf->SetXY(36.5,72);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString( 'Születési idő és hely:' ),1,0);

	//Születési idő és hely(vékony):
	$pdf->SetXY(82.5,72);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( date("Y-m-d", strtotime( $result['szuldatum'] )).", ".$result['szulhely']),0,0);

	//Taj(vastag):
	$pdf->SetXY(36.5,82);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('TAJ:'),1,0);

	//Taj(vékony):
	$pdf->SetXY(47.5,82);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $taj ),0,0);

	//Anyja neve(vastag):
	$pdf->SetXY(36.5,92);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString( 'Anyja leánykori neve:' ),1,0);

	//Anyja neve(vékony):
	$pdf->SetXY(84.5,92);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['anyjaneve'] ),0,0);

	//Lakcím(vastag):
	$pdf->SetXY(36.5,102);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Lakcím:'),1,0);

	//Lakcím(vékony):
	$pdf->SetXY(54.5,102);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['lakcim'] ),0,0);

	//Lev. cím(vastag):
	$pdf->SetXY(36.5,112);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Levelezési cím:'),1,0);

	//Lev. cím(vékony):
	$pdf->SetXY(70.5,112);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(104,10,pdfString($result['levelezesicim']),0,0);

	//Telefon(vastag):
	$pdf->SetXY(36.5,122);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Telefon:'),1,0);

	//Telefon(vékony):
	$pdf->SetXY(55.5,122);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(119,10,pdfString($result['telefon']),0,0);

	//Email(vastag):
	$pdf->SetXY(36.5,132);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Email cím:'),1,0);

	//Email(vékony):
	$pdf->SetXY(60.5,132);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(114,10,pdfString($result['email']),0,0);
	
	//Munkahely(vastag):
	$pdf->SetXY(36.5,142);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Munkahely:'),1,0);

	//Munkahely(vékony):
	$pdf->SetXY(62,142);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(114,10,pdfString($munkahely),0,0);
	
	//Munkakör(vastag):
	$pdf->SetXY(36.5,152);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Munkakör:'),1,0);

	//Munkakör(vékony):
	$pdf->SetXY(60.5,152);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(114,10,pdfString($result['munkakor']),0,0);

	//Táblázat alatti szöveg mező:
	
	//Vizsgálat típusa megadása:
	//Munkakör(vastag):
	$pdf->SetXY(36.5,165);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Vizsgálat típusa:'),0,0);
	
	$pdf->SetXY(76.5,167.6);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(23,5,pdfString('ELŐZETES'),($result['vizsgalat'] == "Előzetes" ? "B" : 0),0);
	
	$pdf->SetXY(106.5,167.6);
	$pdf->Cell(27,5,pdfString('IDŐSZAKOS'),($result['vizsgalat'] == "Időszakos" ? "B" : 0),0);
	
	$pdf->SetXY(136.5,167.6);
	$pdf->Cell(33,5,pdfString('SORON KÍVÜLI'),($result['vizsgalat'] == "Soronkívüli" ? "B" : 0),0);
	
	
	$pdf->SetFont('Montserrat-Light', '',11);
	$pdf->SetXY(15,175);
	$pdf->Cell(181,5,pdfString('A   Szolgáltató   adatkezelésére   a   személyes   adatok  védelméről   és    a   közérdekű  adatok '),0,1);
	$pdf->SetXY(15,180);
	$pdf->Cell(181,5,pdfString('nyilvánosságáról   szóló   GDPR  (E.U.2016/679)  irányadó.   Az   adatszolgáltatás   önkéntes.   Az '),0,1);
	$pdf->SetXY(15,185);
	$pdf->Cell(181,5,pdfString('adatkezelés célja az Adatkezelő által vállalt szolgáltatások és kötelezettségek teljesítése, jogok '),0,1);
	$pdf->SetXY(15,190);
	$pdf->Cell(181,5,pdfString('érvényesítése, az ügyfél azonosítása, az Ügyféllel való kapcsolattartás és kommunikáció.'),0,1);

	//Második bekezdés:
	$pdf->SetXY(15,200);
	$pdf->Cell(181,5,pdfString('További  személyes  adatok  kezelése   törvényi   felhatalmazáson   alapulhat,   amelynek   célja '),0,1);
	$pdf->SetXY(15,205);
	$pdf->Cell(181,5,pdfString('jogszabályi  kötelezettségek  teljesítése.  Kezelt  adatok:  Név,  születési  idő  és  hely,  TAJ szám, '),0,1);
	$pdf->SetXY(15,210);
	$pdf->Cell(181,5,pdfString('anyja leánykori neve, lakcím.'),0,1);

	//Első Checkbox helye(adatkuldes):
	$pdf->Image('../../images/'.($result['adatkuldes'] == 1?"checked.png":"unchecked.png"),20,219.5,0,0);
	$pdf->SetXY(30,220);
	$pdf->Cell(181,5,pdfString('Hozzájárulok,  hogy  az  adatkezelő  részemre  vizsgálati  eredményeimet  elektronikus '),0,1);
	$pdf->SetXY(30,225);
	$pdf->Cell(181,5,pdfString('úton e-mailben, küldje meg az Ügyfél által megadott email címre. '),0,1);

	//Második Checkbox helye(labor):
	$pdf->Image('../../images/'.($result['labor'] == 1?"checked.png":"unchecked.png"),20,234.5,0,0);
	$pdf->SetXY(30,235);
	$pdf->Cell(181,5,pdfString('Hozzájárulok, hogy az Adatkezelő laboratóriumi vizsgálat esetén, a Szolgáltató a vele '),0,1);
	$pdf->SetXY(30,240);
	$pdf->Cell(181,5,pdfString('szerződésben álló laboratóriumi részére személyes adatait elküldje. '),0,1);

	//Harmadik Checkbox helye(tajekoztato):
	$pdf->Image('../../images/'.($result['tajekoztato'] == 1?"checked.png":"unchecked.png"),20,249.5,0,0);
	$pdf->SetXY(30,251);
	$pdf->Cell(181,5,pdfString('Hozzájárulok, hogy részemre szolgáltatásainkról tájékoztatót küldjenek.'),0,1);

	//Alsó szöveg mező helye:
	$pdf->SetXY(15,260);
	$pdf->Cell(181,5,pdfString('Jelen  hozzájáruló  nyilatkozat  bármikor  korlátozás,  feltétel  és  indoklás  nélkül  visszavonható. '),0,1);
	$pdf->SetXY(15,265);
	$pdf->Cell(181,5,pdfString('Kijelentem,   hogy   ezen   hozzájárulásomat   önkéntesen   minden   külső   befolyás   nélkül,    a '),0,1);
	$pdf->SetXY(15,270);
	$pdf->Cell(181,5,pdfString('megfelelő tájékoztatás és  a  vonatkozó  jogszabályi  rendelkezések  ismeretében  tettem  meg.'),0,1);

	//Kelte:
	$pdf->SetXY(20,285);
	$pdf->Cell(211,5,pdfString('Budapest, '.$kelte),0,1);

	//Aláírása rész:
	
	$pdf->Image("../../doc/{$_REQUEST['id']}.png",140,276,30,0);
	/*try {
		$pdf->Image "../../doc/{$_REQUEST['id']}.png",140,276,30,0);
	} catch(Exception $e) {
		echo $e->getMessage();
	}*/
	$pdf->SetXY(132,290);
	$pdf->Cell(50,0,"",1,1);
	$pdf->SetXY(142,293);
	$pdf->Cell(211,5,pdfString('Ügyfél aláírása'),0,1);
	//$pdf->Output("{$result['nev']}.pdf","F");
	
	if( isset( $_REQUEST['method'] ) && $_REQUEST['method'] == "create" )  $pdf->Output("../../doc/GDPR/{$_REQUEST['id']}.pdf", "F");
	if( isset( $_REQUEST['method'] ) && $_REQUEST['method'] == "preview" ) $pdf->Output("{$_REQUEST['id']}.pdf", "I");
}

if ( $_REQUEST['adatlap'] == "biztosítós" )
{
	// 1. Width, height, text, border, end-line, align
	$pdf->SetXY(0,50);
	$pdf->SetFont('Montserrat-Black', '', 18);
	$pdf->Cell(211,10,pdfString('Biztosítási Adatlap'),0,1,'C');

	//Név(vastag):
	$pdf->SetXY(36.5,62);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Név:'),1,0);

	//Név(vékony):
	$pdf->SetXY(47.5,62);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['nev'] ),0,0);

	//Születési idő és hely(vastag):
	$pdf->SetXY(36.5,72);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString( 'Születési idő és hely:' ),1,0);

	//Születési idő és hely(vékony):
	$pdf->SetXY(82.5,72);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( date("Y-m-d", strtotime( $result['szuldatum'] )).", ".$result['szulhely']),0,0);

	//Taj(vastag):
	$pdf->SetXY(36.5,82);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('TAJ:'),1,0);

	//Taj(vékony):
	$pdf->SetXY(47.5,82);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $taj ),0,0);

	//Anyja neve(vastag):
	$pdf->SetXY(36.5,92);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString( 'Anyja leánykori neve:' ),1,0);

	//Anyja neve(vékony):
	$pdf->SetXY(84.5,92);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['anyjaneve'] ),0,0);

	//Lakcím(vastag):
	$pdf->SetXY(36.5,102);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Lakcím:'),1,0);

	//Lakcím(vékony):
	$pdf->SetXY(54.5,102);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(138,10,pdfString( $result['lakcim'] ),0,0);

	//Lev. cím(vastag):
	$pdf->SetXY(36.5,112);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Levelezési cím:'),1,0);

	//Lev. cím(vékony):
	$pdf->SetXY(70.5,112);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(104,10,pdfString($result['levelezesicim']),0,0);

	//Telefon(vastag):
	$pdf->SetXY(36.5,122);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Telefon:'),1,0);

	//Telefon(vékony):
	$pdf->SetXY(55.5,122);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(119,10,pdfString($result['telefon']),0,0);

	//Email(vastag):
	$pdf->SetXY(36.5,132);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Email cím:'),1,0);

	//Email(vékony):
	$pdf->SetXY(60.5,132);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(114,10,pdfString($result['email']),0,0);
	
	//Email(vastag):
	$pdf->SetXY(36.5,142);
	$pdf->SetFont('Montserrat-Black', '', 11);
	$pdf->Cell(138,10,pdfString('Személyi igazolvány szám:'),1,0);

	//Email(vékony):
	$pdf->SetXY(94,142);
	$pdf->SetFont('Montserrat-Light', '', 11);
	$pdf->Cell(114,10,pdfString($result['szig']),0,0);

	//Táblázat alatti szöveg mező:
	$pdf->SetFont('Montserrat-Light', '',11);
	$pdf->SetXY(15,162);
	$pdf->Cell(181,5,pdfString('A   Szolgáltató   adatkezelésére   a   személyes   adatok  védelméről   és    a   közérdekű  adatok '),0,1);
	$pdf->SetXY(15,167);
	$pdf->Cell(181,5,pdfString('nyilvánosságáról   szóló   GDPR  (E.U.2016/679)  irányadó.   Az   adatszolgáltatás   önkéntes.   Az '),0,1);
	$pdf->SetXY(15,172);
	$pdf->Cell(181,5,pdfString('adatkezelés célja az Adatkezelő által vállalt szolgáltatások és kötelezettségek teljesítése, jogok '),0,1);
	$pdf->SetXY(15,177);
	$pdf->Cell(181,5,pdfString('érvényesítése, az ügyfél azonosítása, az Ügyféllel való kapcsolattartás és kommunikáció.'),0,1);

	//Második bekezdés:
	$pdf->SetXY(15,187);
	$pdf->Cell(181,5,pdfString('További  személyes  adatok  kezelése   törvényi   felhatalmazáson   alapulhat,   amelynek   célja '),0,1);
	$pdf->SetXY(15,192);
	$pdf->Cell(181,5,pdfString('jogszabályi  kötelezettségek  teljesítése.  Kezelt  adatok:  Név,  születési  idő  és  hely,  TAJ szám, '),0,1);
	$pdf->SetXY(15,197);
	$pdf->Cell(181,5,pdfString('anyja leánykori neve, lakcím.'),0,1);

	//Első Checkbox helye(adatkuldes):
	$pdf->Image('../../images/'.($result['adatkuldes'] == 1?"checked.png":"unchecked.png"),20,206.5,0,0);
	$pdf->SetXY(30,207);
	$pdf->Cell(181,5,pdfString('Hozzájárulok,  hogy  az  adatkezelő  részemre  vizsgálati  eredményeimet  elektronikus '),0,1);
	$pdf->SetXY(30,212);
	$pdf->Cell(181,5,pdfString('úton e-mailben, küldje meg az Ügyfél által megadott email címre. '),0,1);

	//Második Checkbox helye(labor):
	$pdf->Image('../../images/'.($result['labor'] == 1?"checked.png":"unchecked.png"),20,221.5,0,0);
	$pdf->SetXY(30,222);
	$pdf->Cell(181,5,pdfString('Hozzájárulok, hogy az Adatkezelő laboratóriumi vizsgálat esetén, a Szolgáltató a vele '),0,1);
	$pdf->SetXY(30,227);
	$pdf->Cell(181,5,pdfString('szerződésben álló laboratóriumi részére személyes adatait elküldje. '),0,1);

	//Harmadik Checkbox helye(tajekoztato):
	$pdf->Image('../../images/'.($result['tajekoztato'] == 1?"checked.png":"unchecked.png"),20,236.5,0,0);
	$pdf->SetXY(30,237);
	$pdf->Cell(181,5,pdfString('Hozzájárulok, hogy részemre szolgáltatásainkról tájékoztatót küldjenek.'),0,1);

	//Alsó szöveg mező helye:
	$pdf->SetXY(15,247);
	$pdf->Cell(181,5,pdfString('Jelen  hozzájáruló  nyilatkozat  bármikor  korlátozás,  feltétel  és  indoklás  nélkül  visszavonható. '),0,1);
	$pdf->SetXY(15,252);
	$pdf->Cell(181,5,pdfString('Kijelentem,   hogy   ezen   hozzájárulásomat   önkéntesen   minden   külső   befolyás   nélkül,    a '),0,1);
	$pdf->SetXY(15,257);
	$pdf->Cell(181,5,pdfString('megfelelő tájékoztatás és  a  vonatkozó  jogszabályi  rendelkezések  ismeretében  tettem  meg.'),0,1);

	//Kelte:
	$pdf->SetXY(20,280);
	$pdf->Cell(211,5,pdfString('Budapest, '.$kelte),0,1);

	//Aláírása rész:
	$pdf->Image("../../doc/{$_REQUEST['id']}.png",140,276,30,0);
	$pdf->SetXY(132,285);
	$pdf->Cell(50,0,"",1,1);
	$pdf->SetXY(142,288);
	$pdf->Cell(211,5,pdfString('Ügyfél aláírása'),0,1);
	//$pdf->Output("{$result['nev']}.pdf","F");
	//Fájl létrehozása
	if( isset( $_REQUEST['method'] ) && $_REQUEST['method'] == "create" )  $pdf->Output("../../doc/GDPR/{$_REQUEST['id']}.pdf", "F");
	if( isset( $_REQUEST['method'] ) && $_REQUEST['method'] == "preview" ) $pdf->Output("{$_REQUEST['id']}.pdf", "I");
}
//Képtörlése a szerverről(Aláírás)
//unlink("../../doc/{$_REQUEST['id']}.png");
$filename = "{$_REQUEST['id']}.pdf";
//sql_query( "UPDATE GDPR SET alairas = NULL, URI = NULL" );
if( file_exists ( "../../doc/GDPR/{$_REQUEST['id']}.pdf" ) && $_REQUEST['method'] == "create" )
{
	sql_query("UPDATE GDPR SET exist_doc = 1 WHERE id = {$_REQUEST['id']}");
	header("Location:/admin/index.php?page=gdpr&status=closed");
}
//Fájl letöltése
if($_REQUEST['method'] == "download")
{
	header('Content-Disposition: attachment; filename=' . $filename);
	readfile ("../../doc/GDPR/{$_REQUEST['id']}.pdf");	
}

//unlink("../../doc/{$_REQUEST['id']}.pdf");
?>
