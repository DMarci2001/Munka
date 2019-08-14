<?php
session_start();

include("../config.php");
include("ajax.php");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$file = "";
if($_POST['status'] == "open") $query = "SELECT * FROM felhasznalok WHERE id = {$_POST['patient']}";
if($_POST['status'] == "closed") $query = "SELECT felh.* FROM felhasznalok felh
										   LEFT JOIN paciens_leletek pl ON pl.paciens_id = felh.id
										   LEFT JOIN zaro_leletek zl ON zl.zaro_id = pl.zaro_id
										   WHERE zl.zaro_id = {$_POST['patient']} LIMIT 1";
										   

										   
if($details = sql_fetch_array(sql_query( $query )))
{
	$patient = "<p style = 'font-family:Calibri;font-size:21px;font-weight:bold;text-align:center;color:#000'><em>{$details['nev']}</em></p>";
	$patient.= "<p style = 'font-family:Calibri;font-size:16px;font-weight:bold;text-align:center;color:#000'><em>Születési idő: {$details['szuldatum']}</em></p>";
	$patient.= "<p style = 'font-family:Calibri;font-size:16px;font-weight:bold;text-align:center;color:#000'><em>TAJ: {$details['taj']}</em></p>";
	
	if($details['anyjaneve'] != "") {
		$patient.= "<p style = 'font-family:Calibri;font-size:16px;font-weight:bold;text-align:center;color:#000'><em>Anyja neve: {$details['anyjaneve']}</em></p>";
	}
	
	$patient.= "<p style = 'font-family:Calibri;font-size:16px;font-weight:bold;text-align:center;color:#000'><em>Lakcím: {$details['irsz']}, {$details['varos']} {$details['utca']}</em></p>";
	
	if($details['telefon']!="") {
		$patient.= "<p style = 'font-family:Calibri;font-size:16px;font-weight:bold;text-align:center;color:#000'><em>{$details['telefon']}</em></p>";
	}
}
else $patient = "";

/*$dom     = new DOMDocument;
$dom->loadHTML( mb_convert_encoding( $_POST['header'] ,'HTML-ENTITIES', 'UTF-8' ));
$xPath   = new DOMXPath( $dom );
$patient     = $dom->saveHTML();
$blacklisted = "<html> <body>";
$whitelist   = blacklistElements( $blacklisted );
$patient     = strip_tags( $patient, implode( "", $whitelist ));*/

//Fejléc:
$file.= "<p style = 'font-family:Calibri;font-size:21px;font-weight:bold;text-align:center;color:#000;text-decoration:underline'>Orvosi jelentés</p><br/>";
//Páciens Adatok:
$file.= $patient;
//Belgyógyászat:
$file.= ( isset( $_POST['belgyogy'] ) ? $_POST['belgyogy'] : "" );
//Röntgen:
$file.= ( isset( $_POST['rontgen'] ) ? $_POST['rontgen'] : "" );
//Hasi ultrahang:
$file.= ( isset( $_POST['hasi'] ) ? $_POST['hasi'] : "" );
//Nyaki ultrahang:
$file.= ( isset( $_POST['nyaki'] ) ? $_POST['nyaki'] : "" );
//Here ultrahang:
$file.= ( isset( $_POST['here'] ) ? $_POST['here'] : "" );
//Emlő ultrahang:
$file.= ( isset( $_POST['emlo'] ) ? $_POST['emlo'] : "" );
//Pajzsmirigy ultrahang:
$file.= ( isset( $_POST['pajzs'] ) ? $_POST['pajzs'] : "" );
//Bőrvizsgálat:
$file.= ( isset( $_POST['borv'] ) ? $_POST['borv'] : "" );
//Szemészet:
$file.= ( isset( $_POST['szem'] ) ? $_POST['szem'] : "" );
//Echocardiographia:
$file.= ( isset( $_POST['echo'] ) ? $_POST['echo'] : "" );
//Mozgásszervi:
$file.= ( isset( $_POST['mozgas'] ) ? $_POST['mozgas'] : "" );




//Véleményezés:
if( isset($_POST['velemenyezes']) && $_POST['velemenyezes'] != "" )
{
	$dom     = new DOMDocument;
	libxml_use_internal_errors(true);
	$dom->loadHTML( mb_convert_encoding( $_POST['velemenyezes'] ,'HTML-ENTITIES', 'UTF-8' ));
	libxml_clear_errors();
	$xPath   = new DOMXPath( $dom );
	//HTML tag módosítások(CSS):
	$paragraphs = $dom->getElementsByTagName("p");
	for ($i = 0; $i < $paragraphs->length; $i++) {
		$paragraph = $paragraphs->item($i);
		$paragraph->setAttribute("style","text-align:left;color:black;font-family:Calibri;font-size:16px");
	}

	$spans = $dom->getElementsByTagName("span");
	for ($i = 0; $i < $spans->length; $i++) {
		$span = $spans->item($i);
		$span->setAttribute("style","text-align:left;color:black;font-family:Calibri;font-size:16px");
	}
	$blacklisted = "<html> <body>";
	$whitelist   = blacklistElements( $blacklisted );
	$velemeny    = strip_tags( $_POST['velemenyezes'], implode( "", $whitelist ));
	$velemeny 	 = "<span id = 'velemenyezes-tag'>".$velemeny."</span>"; 
	$file.= $velemeny;
}
else $velemeny = "";

$nev = str_replace(" ", "_", $details['nev']);

$filename = "{$nev}-{$details['szuldatum']}.doc";

if( !isset( $_POST['shortcut'] ))
{
	$dom     = new DOMDocument;
	libxml_use_internal_errors(true);
	$dom->loadHTML( mb_convert_encoding( $file ,'HTML-ENTITIES', 'UTF-8' ));
	libxml_clear_errors();
	$xPath   = new DOMXPath( $dom );
	$obj = $dom->getElementsByTagName( "body" );
	$body = $obj->item(0);
	$body->setAttribute("style","font-size:16px;color:#000;font-family: Calibri");
	$file = $dom->saveHTML();
}
if($_POST['status'] == "closed" && isset($_POST['shortcut']) && $_POST['shortcut'] == "on")
{
	$result = sql_fetch_array( sql_query( "SELECT zaro_szoveg FROM zaro_leletek WHERE zaro_id = {$_POST['patient']}" ));
	
	$file = $result['zaro_szoveg'];
	/*$dom     = new DOMDocument;
	libxml_use_internal_errors( true );
	$dom->loadHTML( mb_convert_encoding( $result['zaro_szoveg'] ,'HTML-ENTITIES', 'UTF-8' ));
	libxml_clear_errors();
	$xPath   = new DOMXPath( $dom );
	//HTML tag módosítások(CSS):
	$paragraphs = $dom->getElementsByTagName( "p" );
	for ( $i = 0; $i < $paragraphs->length; $i++ ) 
	{
		$paragraph = $paragraphs->item( $i );
		$paragraph->setAttribute( "style","text-align:left;color:black;font-family:Calibri;font-size:16px" );
	}

	$spans = $dom->getElementsByTagName( "span" );
	for ( $i = 0; $i < $spans->length; $i++ ) 
	{
		$span = $spans->item( $i );
		$span->setAttribute( "style","text-align:left;color:black;font-family:Calibri;font-size:16px" );
	}
	$file = $dom->saveHTML();*/
}

//$finds = "<html><body style = 'width:815px'>".$file."</body></html>";



$finds = $file;

if( $_POST['status'] == "open" && $_POST['submit'] == "Lezárás" )
{
	if(sql_query("INSERT INTO zaro_leletek SET 
				  zaro_szoveg = ?, lezarta = ?, kelte = ?, belgyogy = ?, rontgen = ?, hasi = ?, 
				  nyaki = ?, here = ?, emlo = ?, pajzs = ?, borv = ?, szem = ?, echo = ?, velemenyezes = ?,mozgas = ?",
				  array( $finds, $_SESSION['adminuser']['username'], date("Y-m-d H:i:s"), $_POST['belgyogy'], 
						 $_POST['rontgen'], $_POST['hasi'], $_POST['nyaki'], $_POST['here'],
						 $_POST['emlo'], $_POST['pajzs'], $_POST['borv'], $_POST['szem'], $_POST['echo'], $velemeny, $_POST['mozgas']
					   ))
	  ) {
			$zaro_id = sql_insert_id();
			if(isset($_POST['belgyogy']) && $_POST['belgyogy-id'] != "" ) sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['belgyogy-id']));
			if(isset($_POST['rontgen']) && $_POST['rontgen-id'] != "" )   sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['rontgen-id']));
			if(isset($_POST['hasi']) && $_POST['hasi-id'] != "" ) 		  sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['hasi-id'])); 
			if(isset($_POST['nyaki']) && $_POST['nyaki-id'] != "" ) 	  sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['nyaki-id'])); 
			if(isset($_POST['here']) && $_POST['here-id'] != "" ) 		  sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['here-id'])); 
			if(isset($_POST['emlo']) && $_POST['emlo-id'] != "" ) 		  sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['emlo-id'])); 
			if(isset($_POST['pajzs']) && $_POST['pajzs-id'] != "" ) 	  sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['pajzs-id'])); 
			if(isset($_POST['borv']) && $_POST['borv-id'] != "" ) 		  sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['borv-id'])); 
			if(isset($_POST['szem']) && $_POST['szem-id'] != "" ) 		  sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['szem-id']));  	
			if(isset($_POST['echo']) && $_POST['echo-id'] != "" ) 		  sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['echo-id'])); 
			if(isset($_POST['mozgas']) && $_POST['mozgas-id'] != "" ) 	  sql_query("UPDATE paciens_leletek SET zaro_id = ? WHERE lelet_id = ?", array($zaro_id, $_POST['mozgas-id'])); 
			header("Location:index.php?page=zarok");
		}
}
if( $_POST['status'] == "closed" && $_POST['submit'] == "Módosítás")
{
	sql_query("UPDATE zaro_leletek SET zaro_szoveg = ?, velemenyezes = ? WHERE zaro_id = ?", array($finds, $velemeny, $_POST['patient']));
	header("Location:index.php?page=zarok&status=closed");
}

//echo "<pre>".print_r($_POST)."</pre>";
//echo $file;
if(( isset($_POST['submit'] ) && $_POST['submit'] == "Letöltés") || ( isset( $_POST['alt'] ) && $_POST['alt'] == "download" ))
{
	//file_put_contents( "doc/".$filename, $finds );
	header('Content-Disposition: attachment; filename=' . $filename);
	echo $finds;
	//readfile($filename);
	//unlink($filename);
}
//header("Location:index.php?page=zarok");
?>