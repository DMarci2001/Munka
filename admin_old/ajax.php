<?php













if (isset($_GET["toggleeljott"])) {
	$id=round($_GET["toggleeljott"]);
	sql_query("update foglalasok set eljott=IF(eljott=1,0,1) where id='".addslashes($id)."'");
	$row=sql_fetch_array(sql_query("select * from foglalasok where id='".addslashes($id)."'"));
	echo showEljottCheckBox($row);
	die();
}






if (isset($_GET["sethelyszin"])) {
	$s=explode("-",$_GET["sethelyszin"]);
	$_SESSION["helyszin"]=$s[0];
	$_SESSION["helyszinceg"]=$s[1];
	header("location:index.php?page=bnaptar");
	die();
}
if (isset($_GET["sethelyszin2"])) {
	$s=explode("-",$_GET["sethelyszin2"]);
	$_SESSION["helyszin"]=$s[0];
	$_SESSION["helyszinceg"]=$s[1];
	header("location:index.php?page=elojegyzestabla");
	die();
}

if (isset($_GET["setnaptarszurestipus"])) {
	$_SESSION["naptarszurestipus"]=intval($_GET["setnaptarszurestipus"]);
	header("location:index.php?page=bnaptar");
	die();
}





if (isset($_POST["scancel"])) {
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}

if (isset($_GET["fi"])) {
	sql_query("insert foglalasok set technical=1,aktiv=1,regdatum=now(),datum='".addslashes($_GET["fi"])."',helyszinid='".addslashes($_GET["h"])."'");
	header("location:{$_SERVER["PHP_SELF"]}?page=bnaptar");
	die();
}

if (isset($_GET["fif"])) {
	sql_query("delete from foglalasok where technical=1 and datum='".addslashes($_GET["fif"])."' and helyszinid='".addslashes($_GET["h"])."'");
	header("location:{$_SERVER["PHP_SELF"]}?page=bnaptar");
	die();
}




if (isset($_POST["userdataverify"])) {
	$formerror="";	

	if (sql_fetch_array(sql_query("select * from orvosok where username=?",array($_POST["username"]))) || sql_fetch_array(sql_query("select * from users where username=? and id<>?",array($_POST["username"],$_POST["userid"])))) $formerror.="A megadott felhasználónév már foglalt<br/>";

	if (!ctype_alnum($_POST["username"])) $formerror.="A felhasználónév csak betükből és számokból állhat (ékezetes betüket se használj)!<br/>";

	if ($_POST["email"]!="") {
		if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) $formerror.="A megadott e-mail cím formátuma helytelen!<br/>";
	}

	if ($_POST["password"]!="") {
		if (strlen($_POST["password"])<6) $formerror.="A jelszónak minimum 6 karakterből kell állnia!<br/>";
		if (strlen($_POST["password"])>20) $formerror.="A jelszó maximum 20 karakterből állhat!<br/>";
	}

	if (strlen($_POST["username"])<3) $formerror.="A felhasználónév minimum 3 karakterből kell állnia!<br/>";
	if (strlen($_POST["username"])>30) $formerror.="A felhasznalónév maximum 30 karakterből állhat!<br/>";


	if ($formerror!="") {
		echo $formerror;
		die();
	}
	
	die("ok");
}






if (isset($_POST["orvosdataverify"])) {
	$formerror="";	

	if (sql_fetch_array(sql_query("select * from orvosok where username=? and id<>?",array($_POST["username"],$_POST["orvosid"]))) || sql_fetch_array(sql_query("select * from users where username=?",array($_POST["username"])))) $formerror.="A megadott felhasználónév már foglalt<br/>";

	if (!ctype_alnum($_POST["username"])) $formerror.="A felhasználónév csak betükből és számokból állhat (ékezetes betüket se használj)!<br/>";

	if (strlen($_POST["jelszo"])<6) $formerror.="A jelszónak minimum 6 karakterből kell állnia!<br/>";
	if (strlen($_POST["jelszo"])>20) $formerror.="A jelszó maximum 20 karakterből állhat!<br/>";

	if (strlen($_POST["username"])<3) $formerror.="A felhasználónév minimum 3 karakterből kell állnia!<br/>";
	if (strlen($_POST["username"])>30) $formerror.="A felhasznalónév maximum 30 karakterből állhat!<br/>";


	if ($formerror!="") {
		echo $formerror;
		die();
	}
	
	die("ok");
}




if (isset($_GET["oertes"])) {
	sendToCegAndOrvos($_GET["oertes"],1);
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&idopont=".urlencode($_GET["idopont"]));
	die();
}

if (isset($_POST["foglalasmentesnaptar"]) || isset($_POST["foglalasmentesnaptaresertesites"])) {
	if (isset($_POST["szuldatumev"])) {
		$_POST["szuldatum"]=$_POST["szuldatumev"]."-".substr("00".$_POST["szuldatumho"],-2)."-".substr("00".$_POST["szuldatumnap"],-2);
	}

    if (!isset($_POST["eljott"])) $_POST["eljott"]=0;
    if (!isset($_POST["voltnalunk"])) $_POST["voltnalunk"]=0;
	if (!isset($_POST["alkalmassag"])) $_POST["alkalmassag"]=0;
	if (!isset($_POST["alkalmassagido"])) $_POST["alkalmassagido"]=0;
	if (!isset($_POST["tudoszuro"])) $_POST["tudoszuro"]=0;
	
	sql_query("update foglalasok set 
		orvosassigned='".addslashes($_POST["orvosassigned"])."',
		taj='".addslashes($_POST["taj"])."',
		nszam='".addslashes($_POST["nszam"])."',
		nev='".addslashes($_POST["nev"])."',
		munkakor='".addslashes($_POST["munkakor"])."',
		email='".addslashes($_POST["email"])."',
		telefon='".addslashes($_POST["telefon"])."',
		szuldatum='".addslashes($_POST["szuldatum"])."',
		irsz='".addslashes($_POST["irsz"])."',
		varos='".addslashes($_POST["varos"])."',
		utca='".addslashes($_POST["utca"])."',
		eljott='".addslashes($_POST["eljott"])."',
		alkalmassag='".addslashes($_POST["alkalmassag"])."',
		alkalmassagido='".addslashes($_POST["alkalmassagido"])."',
		alkalmassagikhet='".addslashes($_POST["alkalmassagikhet"])."',
		alkalmassagkorl='".addslashes($_POST["alkalmassagkorl"])."',
		tudoszuroervenyesseg='".addslashes($_POST["tudoszuroervenyesseg"])."',
		tudoszuro='".addslashes($_POST["tudoszuro"])."',
		megj='".addslashes($_POST["megj"])."'
	where id='".addslashes($_POST["fid"])."'");
	
	if ($_POST["orvosassigned"]!=$_POST["regiorvos"]) {
		sql_query("update foglalasok set ertesitve=0 where id='".addslashes($_POST["fid"])."'");
	}

	if (isset($_POST["foglalasmentesnaptaresertesites"])) {
		sendToCegAndOrvos($_POST["fid"],1);
	}
	
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&idopont=".urlencode($_GET["idopont"]));
	die();
}

if (isset($_POST["foglalasmentes"])) {
	//if (!isset($_POST["aktiv"])) $_POST["aktiv"]=0;
	
	sql_query("update foglalasok set 
		taj='".addslashes($_POST["taj"])."',
		nev='".addslashes($_POST["nev"])."',
		email='".addslashes($_POST["email"])."',
		telefon='".addslashes($_POST["telefon"])."',
		szuldatum='".addslashes($_POST["szuldatum"])."',
		irsz='".addslashes($_POST["irsz"])."',
		varos='".addslashes($_POST["varos"])."',
		utca='".addslashes($_POST["utca"])."',
		megj='".addslashes($_POST["megj"])."'
	where id='".addslashes($_GET["szerk"])."'");

	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}





if (isset($_POST["multifoglalstart"])) {
	
	$cegid=round($_SESSION["helyszinceg"]);
	for ($i=0;$i<$_POST["hanynapot"];$i++) {
		if ($i>30) break;

		$nap=date("Y-m-d",strtotime("{$_GET["from"]} +{$i} day"));
	
		$szurestipus=0;
		if (isset($_SESSION["naptarszurestipus"])) $szurestipus=$_SESSION["naptarszurestipus"];
		
		if (!sql_fetch_array(sql_query("select nap from foglaltnapok where nap=? and helyszinceg=? and helyszinid=? and szurestipusid=?",array($nap,$cegid,$_SESSION["helyszin"],$szurestipus)))) {
			sql_query("insert into foglaltnapok set foglalta=?,nap=?,helyszinceg=?,helyszinid=?,szurestipusid=?",array($user["username"],$nap,$cegid,$_SESSION["helyszin"],$szurestipus));
		}

	}
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}

if (isset($_POST["multifoglalcancel"])) {
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}

if (isset($_GET["enablenap"]) && isset($_SESSION["helyszin"])) {
	$cegid=round($_SESSION["helyszinceg"]);
	$szurestipus=0;
	if (isset($_SESSION["naptarszurestipus"])) $szurestipus=$_SESSION["naptarszurestipus"];
	
	sql_query("delete from foglaltnapok where nap=? and helyszinceg=? and helyszinid=? and szurestipusid=?",array($_GET["enablenap"],$cegid,$_SESSION["helyszin"],$szurestipus));
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}

if (isset($_GET["disablenap"]) && isset($_SESSION["helyszin"])) {
	$cegid=round($_SESSION["helyszinceg"]);
	$szurestipus=0;
	if (isset($_SESSION["naptarszurestipus"])) $szurestipus=$_SESSION["naptarszurestipus"];
	sql_query("insert into foglaltnapok set foglalta=?,nap=?,helyszinceg=?,helyszinid=?,szurestipusid=?",array($user["username"],$_GET["disablenap"],$cegid,$_SESSION["helyszin"],$szurestipus));
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}


//törölhető, használd helyette a DocAgent osztályt.
function get_Doc_Path($fileid) {
	$path="../doc/".floor($fileid/1000);
	if (!is_dir($path)) mkdir($path);
	$path.="/{$fileid}.bin";
	return $path;
}



function datumprint($d) {
	$d=str_replace("-",".",$d);
	$d=substr($d,0,16);
	return $d;
}
	
	




if (isset($_POST["scancel"])) {
	header("location:index.php?page={$_GET["page"]}");
	die();
}

























if (isset($_POST["foglalasorvosertesitesonly"])) {
	sendToCegAndOrvos($_POST["fid"],1);
	die("ok");
}







if (isset($_GET["loadnaptar"])) {
	if (isset($_GET["shift"])) $_SESSION["shift"]+=intval($_GET["shift"]);
	
	echo showAdminNaptar();
	die();
}





if (isset($_GET["shownaptaridopont"])) {
	echo showAdminNaptarIdopont($_GET["shownaptaridopont"]);
	die();
}









if (isset($_GET["getattachment"])) {
	$aid=intval($_GET["getattachment"]);
	if ($row=sql_fetch_array(sql_query("select * from emailattachments where id=?",array($aid)))) {
		
		$file=file_get_contents("/var/www/emailattachments/attachment{$aid}.bin");
		$filename=strtolower($row["filename"]);
		$ext=pathinfo($filename, PATHINFO_EXTENSION);
		
		header("Pragma: no-cache");
		header("Cache-Control: no-store, no-cache");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: must-revalidate");
		header('Content-transfer-encoding: binary');
		header('Content-Disposition: attachment; filename="'.$filename.'"');


		if ($ext=="pdf") header("Content-Type: application/pdf;");
		if ($ext=="jpg") header("Content-Type: image/jpeg;");
		if ($ext=="doc") header("Content-Type: application/msword;");
		if ($ext=="docx") header("Content-Type: application/msword;");
		if ($ext=="xls") header("Content-Type: application/vnd.ms-excel;");
		if ($ext=="xlsx") header("Content-Type: application/vnd.ms-excel;");
		
		
		echo $file;
		
		//echo $row["filename"];
		die();
	}
	
	die("error");
}



if (isset($_GET["addatouser"])) {
	addAttachmentToPaciens($_GET["addatouser"]);
	header("location:index.php?page={$_GET["page"]}");
	die();
}



//include("ajax_lelet.php");
//include("ajax_protocol.php");









function sample_category( $condition ) {
	$htmlout = "";
	$request = sql_query("SELECT * FROM labor_mintak WHERE minta_kategoria = ? ", array( $condition ));
	while( $result = sql_fetch_array( $request ))
	{
		$htmlout.= "<tr><td>{$result['minta_nev']}</td></tr>";
	}
	return $htmlout;
}





function blacklistElements($blacklisted = '', &$errors = array()) {
    if ((string)$blacklisted == '') {
        $errors[] = 'Empty string.';
        return array();
    }

    $html5 = array(
        "<menu>","<command>","<summary>","<details>","<meter>","<progress>",
        "<output>","<keygen>","<textarea>","<option>","<optgroup>","<datalist>",
        "<select>","<button>","<input>","<label>","<legend>","<fieldset>","<form>",
        "<th>","<td>","<tr>","<tfoot>","<thead>","<tbody>","<col>","<colgroup>",
        "<caption>","<table>","<math>","<svg>","<area>","<map>","<canvas>","<track>",
        "<source>","<audio>","<video>","<param>","<object>","<embed>","<iframe>",
        "<img>","<del>","<ins>","<wbr>","<br>","<span>","<bdo>","<bdi>","<rp>","<rt>",
        "<ruby>","<mark>","<u>","<b>","<i>","<sup>","<sub>","<kbd>","<samp>","<var>",
        "<code>","<time>","<data>","<abbr>","<dfn>","<q>","<cite>","<s>","<small>",
        "<strong>","<em>","<a>","<div>","<figcaption>","<figure>","<dd>","<dt>",
        "<dl>","<li>","<ul>","<ol>","<blockquote>","<pre>","<hr>","<p>","<address>",
        "<footer>","<header>","<hgroup>","<aside>","<article>","<nav>","<section>",
        "<body>","<noscript>","<script>","<style>","<meta>","<link>","<base>",
        "<title>","<head>","<html>"
    );

    $list = trim(strtolower($blacklisted));
    $list = preg_replace('/[^a-z ]/i', '', $list);
    $list = '<' . str_replace(' ', '> <', $list) . '>';
    $list = array_map('trim', explode(' ', $list));

    return array_diff($html5, $list);
}

if(isset($_POST['downloadGDPR']))
{	
	$result = sql_fetch_array( sql_query( "SELECT * FROM GDPR WHERE id = ?", array( $_POST['downloadGDPR'] )));
	$file = $_POST['downloadGDPR'].".pdf";
	$destination = "../doc/GDPR/".$file;
	$filename = $file;
	if( file_exists( $destination ))
	{
		header("Pragma: no-cache");
		header("Cache-Control: no-store, no-cache");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: must-revalidate");
		header('Content-transfer-encoding: binary');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header("Content-Type: application/pdf");
		readfile( $destination );
		die();
	}
	else die("File doesnt Exist!");
}


function kuponCheck($coupon,$version,$foglalas,$szurestipus)
{
	$query = sql_query("SELECT * FROM kuponkodok WHERE kod = ? AND statusz = 'aktiv' AND event_end >= '{$foglalas}' AND event_start <= '{$foglalas}' ", array( $coupon ));
	
	if($query->rowCount() > 0)
	{
		$result = sql_fetch_array($query);
		$szurestipusok = explode("|",$result['szurestipusok']);
		
		$data = $result['megnev']."|".$result['leiras'];
		if($version == 2) $data.="|".$result['kedvezmeny'].($result['kedvezmeny_tipus'] =="szazalek"?"%":"Ft");
		if($result['tipus'] == "Egyszer")
		{
			$query = sql_query("SELECT * FROM kupon_lista WHERE kuponkod = '{$coupon}' ");
			if($query->rowCount() != 0)
			{
				$data = "error02";
			}
		}
		if(!in_array($szurestipus,$szurestipusok)) $data = "error03";
		if($version == 3 && $data != "error02" && $data != "error03") $data = "usable";
	}
	else{
		$data = "error01";
	}
	
	return $data;
}

if(isset($_POST['kuponCheck']))
{
	echo kuponCheck($_POST['coupon'],$_POST['version'],$_POST['foglalas'],$_POST['szurestipus']);
	die();
}

if( isset( $_POST['function'] ) && $_POST['function'] == "createAlkxlsx" )
{
	createAlkxlsx($_POST['start'],$_POST['end']);
}

function createAlkxlsx( $start, $end )
{
	$query = "SELECT felh.id,felh.nev,felh.szuldatum,felh.torzsszam,felh.alklejarat,felh.lastalkert 
			  FROM felhasznalok felh
			  LEFT JOIN cegek c ON c.id = felh.cegid
			  WHERE true
			  ".( isset( $_SESSION['alkalmassagi']['multifilter'] )   ? $_SESSION['alkalmassagi']['multifilter'] : "" )."
			  ".( isset( $_SESSION['alkalmassagi']['date-interval'] ) ? $_SESSION['alkalmassagi']['date-interval'] : "" )."
			  ".( isset( $_SESSION['alkalmassagi']['cegfilter'] )  ? $_SESSION['alkalmassagi']['cegfilter'] : "" )."
			  ".( isset( $_SESSION['alkalmassagi']['sort-by'] ) ? "ORDER BY ".$_SESSION['alkalmassagi']['sort-by'] : "" )."
			 ";
			 
	require_once("Classes/PHPExcel.php");
	$start = date("Y.m.d",strtotime($start));
	$end = date("Y.m.d",strtotime($end));
	$filename = "{$start}-{$end}_alkalmassági_lista";
	$objPHPExcel = new PHPExcel();
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet()->setTitle('munkavállaló_lista');
	
	//Oszlop nevek:
	$objPHPExcel->getActiveSheet()->SetCellValue('A1', "Munkavállaló");
	$objPHPExcel->getActiveSheet()->SetCellValue('B1', "Szül. dátum");
	$objPHPExcel->getActiveSheet()->SetCellValue('C1', "Törzsszám");
	$objPHPExcel->getActiveSheet()->SetCellValue('D1', "Utolsó értesítés");
	$objPHPExcel->getActiveSheet()->SetCellValue('E1', "Alkalmassági lejárata");
	
	$row = 2;
	$request = sql_query($query);
	while( $result = sql_fetch_array( $request ))
	{
		if($result['lastalkert'] != "") $lastalkert = date( "Y.m.d", strtotime( $result['lastalkert'] ));
		else $lastalkert = "";
		$objPHPExcel->getActiveSheet()->SetCellValue('A'.$row, $result['nev']);
		$objPHPExcel->getActiveSheet()->SetCellValue('B'.$row, date( "Y.m.d", strtotime( $result['szuldatum'] )));
		$objPHPExcel->getActiveSheet()->SetCellValue('C'.$row, $result['torzsszam']);
		$objPHPExcel->getActiveSheet()->SetCellValue('D'.$row, $lastalkert);
		$objPHPExcel->getActiveSheet()->SetCellValue('E'.$row, date( "Y.m.d", strtotime( $result['alklejarat'] )));
		$row++;
	}
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
}

if( isset( $_REQUEST['accountIni'] ))
{
	$copyData = sql_fetch_array( sql_query( "SELECT * FROM orvosok WHERE id = ?", array( $_REQUEST['docid'] )));
	sql_query( "INSERT INTO users SET orvosid = ?, nev = ?, tel = ?, cegid = 0, jogosultsag = 2", 
			    array( $_REQUEST['docid'], $copyData['nev'], $copyData['tel'] )
			  );
	echo "ok";
	die();
}

function medTemplateFilter($type)
{
	$array = explode( ",", $type );
	
	$htmlout = "";
	$title = array("Belgyógyászat","Röntgen","Ultrahang","Bőrgyógyászat","Szemészet","Kardiológia","Gyógytornász","Labor","Urológia","Nőgyógyászat","Tüdőgyógyászat","Ortopédia");
	$query = sql_query("SELECT * FROM lelet_mintak ORDER BY tipus ASC");
	
	while($result = sql_fetch_array( $query ))
	{
		if( in_array($result['tipus'], $array ))
		{
			$index = ($result['tipus'] - 1);
			$$result['tipus']++;
			if($$result['tipus'] == 1) $htmlout.= "<option disabled style = 'background-color:#444;color:white' value = '0'>{$title[$index]}</option>";
			if($result['lelet_ver'] != "") $version = "({$result['lelet_ver']})";
			else $version = "";
			$htmlout.= "<option value = '{$result['lm_id']}'>{$result['lelet_nev']}{$version}</option>";
		}
	}
	return $htmlout;
}


if ( isset( $_POST['download-signed-pdf'] ))
{
	header("Pragma: no-cache");
	header("Cache-Control: no-store, no-cache");
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: must-revalidate");
	header('Content-transfer-encoding: binary');
	header("Content-Disposition: attachment; filename=" . $_POST['id'] . ".pdf");
	header("Content-Type: application/pdf");
	readfile("../doc/GDPR/{$_POST['id']}.pdf");
	die();
}

function listWarnings()
{
	$managers 	   = array();
	$reportArray   = array();
	$szurestipusok = array();
	$top 	  	   = "15,10,13,14,8";
	$undertop 	   = "15,13,14,8";
	
	//Szűréstípusok megnevezése:
	$request = sql_query("SELECT * FROM szurestipusok");
	while($result = sql_fetch_array($request)) $szurestipusok[] = $result;
	
	//Összes managerszűrés listázása:
	$request = sql_query("SELECT * FROM foglalasok 
						  WHERE helyszinid = 1
						  AND nev != 'nincs név'
						  AND szurestipusid IN(6,34,35) 
						  AND datum BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 2 WEEK)");
	while($result = sql_fetch_array($request)) $managers[] = $result;
	
	//Át vizsgálom a páciensek nevét, hogy megtalálható-e az időszakban a többi vizsgálatra:
	foreach( $managers as $manager )
	{
		$missingExams = array();
		//Szűréstípusok kiválasztása menedzserszűrés alapján:
		if( $manager['szurestipusid'] == 35 ) $szuresek = explode( ",", $top );
		else $szuresek = explode( ",", $undertop );
		
		//Megvizsgálom az összes többi vizsgálatát, melyekre nincsen még kiadva időpont:
		foreach( $szuresek as $szures )
		{
			$request = sql_query( "SELECT * FROM foglalasok 
								   WHERE nev LIKE '%{$manager['nev']}%'
								   AND helyszinid = 1
								   AND szurestipusid = {$szures} 
								   AND datum BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 2 WEEK) " );
								   
			if( sql_num_rows( $request ) == 0 )
			{
				$missingExams[] = $szures;
			}
		}
		
		//COOKIE ellenőrzése hogy létezik-e:
		if( isset( $_COOKIE['removedManagers'] ))
		{
			$data = json_decode( $_COOKIE['removedManagers'], true );
		}
		if(isset($data) && is_array($data))
		{
			$key = array_search($manager['id'], $data);
			if($key !== FALSE)
			{
				continue;
			}
		}
		
		if( count( $missingExams ) > 0 ) $reportArray[] = array( "managerid" => $manager['szurestipusid'], "nev" => $manager['nev'], "idopont" => $manager['datum'], "foglid" => $manager['id'], "missingExams" => $missingExams );
	}
	return $reportArray;
}

function checkWarningsList()
{
	$reportArray = listWarnings();
	$possibleMissing = 0;
	
	foreach( $reportArray as $key => $report )
	{
		foreach( $report['missingExams'] as $vizsgalat )
		{
			$possibleMissing++;
		}
	}
	if( $possibleMissing > 0 ) return $possibleMissing;
	else return false;
}

function displayWarnings()
{
	$reportArray = listWarnings();
	$htmlout   = "";
	$number    = 0;
	$maxlength = count( $reportArray );
	$szuresek = array();
	$request = sql_query("SELECT * FROM szurestipusok");
	while($result = sql_fetch_array($request)) $szuresek[] = $result;
	//$icon = "<i class='fas fa-angle-double-down'></i>";
	$onClick = "onClick = 'if( $(\".warrnings-content\").css(\"max-height\") == \"285px\")
								{ 
									$(\".warrnings-content\").css(\"max-height\", \"none\"); 
									$(\".warrnings-content\").append( $(\".warningOpenFolder\"));
									$(\".warningOpenFolder\").text(\"Kevesebb\");
								} 
								else 
								{ 
									$(\".warrnings-content\").css(\"max-height\", \"285px\"); 
									$(\".warrnings-content\").children().eq(9).after( $(\".warningOpenFolder\") );
									$(\".warningOpenFolder\").html(\" Még ".( $maxlength - 10 )."db \");
								}' ";
	foreach( $reportArray as $key => $report )
	{
		$properties = "";
		$number++;
		$vizsgalatok = "";
		//#DC4806 Emelt
		//$onClick  = "onClick = 'scrollToTarget(\"{$report['nev']}\", $(this))'";
		$onClick = "onClick = 'showMissingExams({$key})'";
		$onClick2 = "onClick = 'removeManager({$report['foglid']})'";
		$id = "id = 'manager-{$report['foglid']}'";
		if( $report['managerid'] == 6  ) $difficult = "alapManager";
		if( $report['managerid'] == 34 ) $difficult = "emeltManager";
		if( $report['managerid'] == 35 ) $difficult = "topManager";
		//if( $number == $maxlength || $number == 10 ) $properties.= "border-radius:0px 0px 5px 5px;";
		$style   = "style = '{$properties}'";
		$date    = date("Y.m.d",strtotime($report['idopont']));
		foreach( $report['missingExams'] as $vizsgalat )
		{
			$key = array_search( $vizsgalat, array_column( $szuresek, 'id' ));
			$vizsgalatok.= $szuresek[$key]['megnev'].", ";
		}		
		$vizsgalatok = substr( $vizsgalatok, 0, -2) ;
		$htmlout.= "<div {$style}  {$id}  class = 'warningCell {$difficult}' title = '{$vizsgalatok}'  ><div {$onClick}>{$report['nev']} - {$date}</div><div  class = 'disableWarningCell'><i {$onClick2} class='fas fa-trash-alt'></i></div></div>";
	}
	return $htmlout;
}

if(isset($_POST['refreshWL']))
{
	echo displayWarnings();
	die();
}
if(isset($_POST['refreshLWOpener']))
{
	echo listWarningsOpener();
	die();
}

function listWarningsOpener()
{
	$reportArray = listWarnings();
	$length = count( $reportArray );
	$onClick = "onClick = 'if( $(\".warrnings-content\").css(\"max-height\") == \"250px\")
								{ 
									$(\".warrnings-content\").css(\"max-height\", \"none\"); 
									$(\".warningOpenFolder\").text(\"Kevesebb\");
								} 
								else 
								{ 
									$(\".warrnings-content\").css(\"max-height\", \"250px\"); 
									$(\".warningOpenFolder\").html(\" Még ".( $length - 10 )."db \");
								}' ";
								
	$htmlout = "<div onClick = 'LWOpener({$length})' class = 'warningOpenFolder'>Még ".( $length - 10 )."db <i class='fas fa-angle-double-down'></i></div>";
	return $htmlout;
}
function loadWLLeftMenu()
{
	$htmlout = "<div id = 'option-1' onClick = 'selectSPOption(\"option-1\")' class = 'WLS-LeftMenu-Element'>Kikapcsoltak</div>";
	$htmlout.= "<div id = 'option-2' onClick = 'selectSPOption(\"option-2\")' class = 'WLS-LeftMenu-Element'>Hibás O. beáll.</div>";
	$htmlout.= "<div id = 'option-2' onClick = 'selectSPOption(\"option-3\")' class = 'WLS-LeftMenu-Element'>Hiányzó vizsg.</div>";
	return $htmlout;
}



function loadWLSelectedMenu($option,$index)
{
	if($option == "option-1")
	{
		$htmlout = "<table class = 'removedManagers'>";
		if(isset($_COOKIE['removedManagers']))
		{
			$data = json_decode($_COOKIE['removedManagers'], true );
			if( is_array( $data ))
			{
				$reportArray = listWarnings();
				foreach( $data as $key )
				{
					$result = sql_fetch_array(sql_query( "SELECT * FROM foglalasok WHERE id = {$key}" ));
					if( $reportArray !== FALSE )
					{
						$date = date("Y.m.d H:i", strtotime($result['datum']));
						$htmlout.= "<tr id = 'removedManager-{$key}'>";
						$htmlout.= 	"<td style = 'border-left:none;'>{$result['nev']}</td>";
						$htmlout.= 	"<td style = 'white-space:nowrap;'>{$date}</td>";
						$htmlout.= 	"<td style = 'border-right:none;' onClick = 'withdrawRemove({$key})'><i style = 'font-size:16px;cursor:pointer' class='fas fa-undo'></i></td>";
						$htmlout.= "</tr>";
					}
				}
			}
			else $htmlout.= "<tr><td colspan = '3' style = 'font-size:20px'>- Nincs menedzser kikapcsolva -</td></tr>";
		}
		else $htmlout.= "<tr><td colspan = '3' style = 'font-size:20px'>- Nincs menedzser kikapcsolva -</td></tr>";		
		$htmlout.= "</table>";
	}
	
	if($option == "option-2")
	{
		$request = sql_query("SELECT COUNT(fogl.datum), fogl.datum, sz.megnev,o.nev FROM foglalasok fogl
							  LEFT JOIN orvosok o ON o.id =  fogl.orvosassigned
							  LEFT JOIN szurestipusok sz ON sz.id = fogl.szurestipusid
							  WHERE fogl.helyszinid = 1 
							  AND fogl.datum BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 2 WEEK) 
							  GROUP BY fogl.datum,fogl.orvosassigned, fogl.szurestipusid 
							  HAVING COUNT(fogl.datum) > 1");
							  
		$htmlout = "<table class = 'removedManagers'>";
		while( $result = sql_fetch_array( $request ))
		{
			$date = date("Y.m.d H:i", strtotime($result['datum']));
			$htmlout.= "<tr>";
			$htmlout.= "<td>{$date}</td>";
			$htmlout.= "<td>{$result['megnev']}</td>";
			$htmlout.= "<td>{$result['nev']}</td>";
			$htmlout.= "</tr>";
		}
		
		$htmlout.= "</table>";
	}
	if($option == "option-3")
	{
		$szuresek = array();
		$request = sql_query("SELECT * FROM szurestipusok");
		while($result = sql_fetch_array($request)) $szuresek[] = $result;
		$htmlout = "<table class = 'missingExams'>";
		if($index == "empty")
		{
			$htmlout.= "<tr><td colspan = '3' style = 'font-size:20px;text-align:center'>- Válassz egy vizsgálatot! -</td></tr>";
		}
		else
		{
			$reportArray = listWarnings();
			$result = sql_fetch_array(sql_query("SELECT fogl.*,sz.megnev FROM foglalasok fogl 
												 LEFT JOIN szurestipusok sz ON sz.id = fogl.szurestipusid 
												 WHERE fogl.id = {$reportArray[$index]['foglid']}"));
			
			$htmlout.= "<tr>";
			$htmlout.= "	<td colspan = '2'><i onClick = '$(\"body\").highlight(\"{$reportArray[$index]['nev']}\");' style = 'font-size:20px;cursor:pointer' class='fas fa-lightbulb'></i>&nbsp;&nbsp;";
			$htmlout.= "		<i id = 'copyButton' onClick = 'copyBooking({$result['id']},\"{$result['pass']}\")' title = 'Időpont másolása' style = 'font-size:20px;cursor:pointer;color:black' class='fas fa-clone'></i>&nbsp;&nbsp;";
			$htmlout.= "		<i onClick = 'showIdopontEditor(\"elojegyzestabla\",\"{$result['pass']}\",{$result['id']})' title = 'Időpont szerkesztő' style = 'font-size:20px;cursor:pointer;color:black' class='fas fa-edit'></i></td>";
			$htmlout.= "</tr>";
			$htmlout.= "<tr><td><strong>Ügyfél neve:</strong></td><td>{$reportArray[$index]['nev']}</td></tr>";
			$htmlout.= "<tr><td><strong>Vizsgálat:</strong></td><td>{$result['megnev']}</td></tr>";
			$htmlout.= "<tr><td><strong>Telefonszám:</strong></td><td>{$result['telefon']}</td></tr>";
			$htmlout.= "<tr><td><strong>Email:</strong></td><td>{$result['email']}</td></tr>";
			$htmlout.= "<tr><td><strong>Foglalás ideje:</strong></td><td>".date("Y.m.d H:i",strtotime($result['datum']))."</td></tr>";
			$htmlout.= "<tr><td><strong>Foglalás kelte:</strong></td><td><i>".date("Y.m.d H:i",strtotime($result['regdatum']))."</i></td></tr>";
			$htmlout.= "<tr><td><strong>Megjegyzés:</strong></td><td><textarea style = 'width:238px;height:54px'>".($result['megj'] != ""?$result['megj']:"Nincs.")."</textarea></td></tr>";
			$htmlout.= "<tr><td colspan = '2' style = 'font-size:20px'><strong>Hiányzó vizsgálatok:</strong></td></tr>";
			foreach( $reportArray[$index]['missingExams'] as $vizsgalat )
			{
				$key = array_search( $vizsgalat, array_column( $szuresek, 'id' ));
				$onClick01 = "<i title = 'Ugrás {$szuresek[$key]['megnev']}' style = 'font-size:20px;cursor:pointer' onClick = 'SmoothScrollTo(\"{$szuresek[$key]['megnev']}\",1000)' class='fas fa-arrow-alt-circle-left'></i>";
				//$onClick02 = "<i title = 'Időpont másolása' onClick = 'semmi({$result['id']},\"{$result['pass']}\")' style = 'font-size:20px;cursor:pointer' class='fas fa-clone'></i>";
				$htmlout.= "<tr>";
				$htmlout.= "	<td>{$szuresek[$key]['megnev']}</td>";
				$htmlout.= "	<td>{$onClick01}</td></tr>";
			}
		}
		$htmlout.= "</table>";
	}
	
	return $htmlout;
}

if(isset($_POST['loadWLSelectedMenu']))
{
	if( isset($_POST['index'] )) $index = $_POST['index'];
	else $index = "empty";
	echo loadWLSelectedMenu($_POST['option'], $index);
	die();
}
function WLSPTitle($option)
{
	if($option == 'option-1') $title = "Kikpacsolt menedzserek";
	if($option == 'option-2') $title = "Hibás orvos beállítások";
	if($option == 'option-3') $title = "Hiányzó vizsgálatok";
	return $title;
}
if(isset($_POST['loadWLSPTitle']))
{
	echo WLSPTitle($_POST['option']);
	die();
}
if(isset($_POST['loadSelectedMenu']))
{
	$title = WLSPTitle($_POST['option']);
	$htmlout = "";
	$htmlout.= "<div class = 'WL-sidePanel-title'>";
	$htmlout.= "	<span style = ''>{$title}</span>";
	$htmlout.= "	<span><i style = 'cursor:pointer;' onClick = '$(\".WL-sidePanel\").animate({width: \"toggle\"});cpy=0;foglalasSelected=0;foglalasSelectedPass=0;' class='fas fa-times'></i></span>";
	$htmlout.= "</div>";
	
	$htmlout.= "<div class = 'WL-sidePanel-leftMenu-container'>".loadWLLeftMenu()."</div>";
	$htmlout.= "<div class = 'WL-sidePanel-selected-menu-conainer'>".loadWLSelectedMenu($_POST['option'], $_POST['index']);
	$htmlout.= "</div>";
	die($htmlout);
}

if(isset($_POST['withdrawManager']))
{
	if(isset($_COOKIE['removedManagers']))
	{
		$data = json_decode($_COOKIE['removedManagers'], true);
		$key  = array_search( $_POST['withdrawManager'], $data );
		unset($data[$key]);
		array_values( $data );
		if( empty( $data )) $time = time() - 3600;
		else $time = time()+86400;
		if(setcookie('removedManagers', json_encode($data), $time ))
		{
			die("sikeres!");
		}
	}
	else die();
}

if(isset($_POST['removeManager']))
{
	if(isset($_COOKIE['removedManagers']))
	{
		$data = json_decode($_COOKIE['removedManagers'], true);
		//$data = unserialize($_COOKIE['removedManagers'], ["allowed_classes" => false]);
		$data[] = $_POST['removeManager'];
		if(setcookie('removedManagers', json_encode($data), time()+86400 ))
		{
			die("sikeres!");
		}
	}
	else
	{
		$data = array($_POST['removeManager']);
		if(setcookie( 'removedManagers', json_encode($data), time() + 86400 ))
		{
			die("sikeres!");
		}
	}
}

if(isset($_POST['downloadExamStat']) && $_POST['downloadExamStat']==true){
	
	if($_POST['cegid']!=0) $result = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id = ?",array($_POST['cegid'])));
	else $result['megnev']="teljes";
	$filename="{$_POST['start']}-{$_POST['end']} {$result['megnev']} statisztika";
	
	$_POST['start'] = $_POST['start']." 00:00:00";
	$_POST['end']   = $_POST['end']  ." 23:59:59";
	
	require_once "Classes/PHPExcel.php";
	$objPHPExcel = new PHPExcel();
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet()->setTitle('Vizsgálat lista');
	
	$objPHPExcel->getActiveSheet()->SetCellValue('A1', "Ügyfél neve");
	$objPHPExcel->getActiveSheet()->SetCellValue('B1', "Szül. dátum");
	$objPHPExcel->getActiveSheet()->SetCellValue('C1', "Cég");
	$objPHPExcel->getActiveSheet()->SetCellValue('D1', "Email");
	$objPHPExcel->getActiveSheet()->SetCellValue('E1', "Telefon");
	$objPHPExcel->getActiveSheet()->SetCellValue('F1', "Vizsgálat");
	$objPHPExcel->getActiveSheet()->SetCellValue('G1', "Keltezés");
	$objPHPExcel->getActiveSheet()->SetCellValue('H1', "Ellátó orvos");
	
	$i = 1;
	
	$request = sql_query("SELECT 

	/*Páciens információk*/
	felh.nev AS 'Ügyfél neve', felh.szuldatum AS 'Szül. dátum',c.megnev AS 'Cég', felh.email AS 'Email', felh.telefon AS 'Telefon', 

	/*Vizsgálat információi*/
	lm.lelet_nev AS 'Vizsgálat',pl.kelte AS 'Keltezés',
	(CASE WHEN pl.lelet_szoveg LIKE '%Dr. Al-Mohamed Ádám%'    THEN 'Dr. Al-Mohamed Ádám'
		  WHEN pl.lelet_szoveg LIKE '%Dr. Juhász Anita%' 	   THEN 'Dr. Juhász Anita'
		  WHEN pl.lelet_szoveg LIKE '%Dr. Tarján Zsolt%' 	   THEN 'Dr. Tarján Zsolt'
		  WHEN pl.lelet_szoveg LIKE '%Dr. Ferenczi Zsuzsanna%' THEN 'Dr. Ferenczi Zsuzsanna'
		  WHEN pl.lelet_szoveg LIKE '%Dr. Magyar Judit%' 	   THEN 'Dr. Magyar Judit'
	END) AS 'Orvos'

	/*default lekérdés alap tábla*/
	FROM paciens_leletek pl

	/*Szükséges kiegészítő információk*/
	LEFT JOIN felhasznalok felh ON felh.id = pl.paciens_id
	LEFT JOIN lelet_mintak lm ON lm.lm_id = pl.lelet_type
	LEFT JOIN cegek c ON c.id = felh.cegid

	/*Vizsgálati elemek*/
	WHERE ".($_POST['cegid']!=0?"felh.cegid = 104 AND ":"")."pl.kelte BETWEEN '{$_POST['start']}' AND '{$_POST['end']}'

	/*Rendezés*/
	GROUP BY pl.kelte ASC");
	
	while($result = sql_fetch_array($request)){
		$i++;
		$objPHPExcel->getActiveSheet()->SetCellValue('A'.$i, $result['Ügyfél neve']);
		$objPHPExcel->getActiveSheet()->SetCellValue('B'.$i, $result['Szül. dátum']);
		$objPHPExcel->getActiveSheet()->SetCellValue('C'.$i, $result['Cég']);
		$objPHPExcel->getActiveSheet()->SetCellValue('D'.$i, $result['Email']);
		$objPHPExcel->getActiveSheet()->SetCellValue('E'.$i, $result['Telefon']);
		$objPHPExcel->getActiveSheet()->SetCellValue('F'.$i, $result['Vizsgálat']);
		$objPHPExcel->getActiveSheet()->SetCellValue('G'.$i, $result['Keltezés']);
		$objPHPExcel->getActiveSheet()->SetCellValue('H'.$i, $result['Orvos']);
	}
	
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
	header('Cache-Control: max-age=0');
	
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
}
?>