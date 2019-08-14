<?php


session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SESSION["LAST_ACTIVITY"]=time();

require_once "includes/config.php";

header("Content-type: text/html; charset=UTF-8");

if (!isset($_GET["page"]) && isset($_SESSION["user"])) $_GET["page"] = "idopontfoglalas";
if (!isset($_GET["page"])) $_GET["page"] = "main";


if (isset($GLOBALS["ertekeles"])) {
    include("ertekeles_index.php");
    return;
}

echo htmlheader("{$_SESSION["helyszindata"]["megnev"]} online bejelentkezés");
echo "<body>";

echo "<div id='sound' style='display:none;'></div>";
echo '<div class = "successful-message"><span>Loading...</span></div>';
echo '<div class = "obj-container-framebox"></div>';
echo "<div style='display:table;width:100%;margin:20px 0px;'>";
echo "<div style='display:table-row;'>";
echo "<div style='display:table-cell;vertical-align:middle;width:20px;padding-left:40px;'>";
echo "<a href='/index.php'><img width='30' src='images/hmm_logo.png' alt='' title='Hungáriamed időpontfoglalás' style='margin-right:10px;' /></a>";
echo "</div>";
echo "<div style='display:table-cell;vertical-align:middle;'>";


if (isset($_SESSION["user"])) {
    $rowb=sql_fetch_array(sql_query("select count(*) as hany from beutalok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and foglalasid=0"));
    $rowd=sql_fetch_array(sql_query("select count(*) as hany from dokumentumok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and megnezve is null"));
    $rowf=sql_fetch_array(sql_query("select count(*) as hany from foglalasok where paciensid='{$_SESSION["user"]["id"]}' and datum>now()"));

    echo "<div>{$webText["udvozlunk"]} {$_SESSION["user"]["nev"]}!</div>";
    echo "<a href='index.php?page=idopontfoglalas'>{$webText["idopontfoglalas"]}</a> &bull; ";
    echo "<a href='index.php?page=foglalasok'>{$webText["foglalasok"]}</a>".($rowf["hany"]>0?" <span class='ujnumber'>{$rowf["hany"]}</span>":"")." &bull; ";
    echo "<a href='index.php?page=beutalok'>{$webText["beutalok"]}</a>".($rowb["hany"]>0?" <span class='ujnumber'>{$rowb["hany"]}</span>":"")." &bull; ";
    echo "<a href='index.php?page=dokumentumok'>{$webText["dokumentumok"]}</a>".($rowd["hany"]>0?" <span class='ujnumber'>{$rowd["hany"]}</span>":"")." &bull; ";
    //echo "<a href='index.php?page=foglalasok'>foglalások</a>&nbsp; ";
    echo "<a href='index.php?page=leletek'>Leletek</a> &bull; ";
    echo "<a href='index.php?page=profil'>{$webText["adatmodositas"]}</a> &bull; ";
    echo "<a href='index.php?logout'>{$webText["kijelentkezes"]}</a>";
} else {
    echo "<a href='index.php'>{$webText["idopontfoglalas"]}</a> &bull; ";
    if( $_SESSION["helyszindata"]["id"] != 11 ) echo "<a href='index.php?page=reg'>{$webText["regisztracio"]}</a>&nbsp;&bull;&nbsp;";
	echo "<a href='index.php?page=login'>{$webText["bejelentkezes"]}</a>";
}
echo "</div>";


$link=$_SERVER["PHP_SELF"];
if ($_SERVER["QUERY_STRING"]!="") {
	$link.="?".$_SERVER["QUERY_STRING"]."&";
} else {
	$link.="?";
}

echo "<div style='display:table-cell;vertical-align:middle;padding-left:10px;padding-right:40px;text-align:right;'>";

if (isset($_SERVER["HTTP_HOST"]) && substr_count($_SERVER["HTTP_HOST"],"anmeldung")==0) {
    echo getLangLink("hu")." ";
    echo getLangLink("en")." ";
    echo getLangLink("de")." ";
}
echo "</div>";

echo "</div>";
echo "</div>";

echo "<div style='margin:20px;min-height:0px;'>";

$pageFile="inc_{$_GET["page"]}.php";

if ($_GET["page"]=="main" && in_array($_SERVER["REMOTE_ADDR"],array("31.46.168.67","88.151.97.121")) && substr_count($_SERVER["HTTP_USER_AGENT"],"Firefox")) {
	//$pageFile="inc_{$_GET["page"]}_new.php";
}

echo "<div style='background-color:#fff;border-radius:5px;'>";
echo "<div style='padding:20px;'>";
include($pageFile);
echo "</div>";
?>

<div style = "background-color:#ccc;margin:100 -20 -20 -20;border-radius:0px 0px 5px 5px;padding:20px;">
	<table style = "color:#555">
		<tr style = "font-weight:bold"><td>Budapesti egészségközpont&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td><td>Telefon:</td></tr>
		<tr><td>1135 Budapest, Jász u. 33-35.</td><td> <a href = "tel:+36 1 800 9333">+36 1 800 9333</a>,<a href = "tel:+36 30 633 0961"> +36 30 633 0961</a></td></tr>
		<tr><td></td><td></td></tr>
		<tr><td>© <?php echo date("Y") ?> KeltexMed KFT</td><td></td></tr>
	</table>
</div>
	
<?php
/*if (in_array($_SESSION["helyszindata"]["id"],array(11,42))) {
	echo "<div style='background:#ccc;color:#555;padding:20px;border-bottom-left-radius:5px;border-bottom-right-radius:5px;'>";
	
	echo "<div style='float:left;margin:0px 20px 10px 0px;'><b>Budapesti egészségközpont</b><br/>1135 Budapest, Jász u. 33-35.</div>";
	echo "<div style='float:left;margin:0px 10px 10px 0px;'><b>Telefon:</b><br/>+36 1 800 9333, +36 30 633 0961</div>";
	echo "<br clear='all'/>";

	echo "&copy; ".date("Y")." HUNGÁRIA MED-M KFT.";
	echo "</div>";
}*/


echo "</div>";

echo "</div>";



//echo "<div class='footersor'>&copy; HUNGÁRIAMED</div>";
//echo "</table>";

echo "</body>";



?>