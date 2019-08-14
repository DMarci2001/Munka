<?php

session_start();

$sessionup=2; //óra
ini_set('session.gc_maxlifetime', $sessionup*60*60);
session_set_cookie_params($sessionup*60*60);


$_SESSION["LAST_ACTIVITY"]=time();

$GLOBALS["admin"]=1;
if(isset($_SESSION['destroyFile']) && $_GET['page'] != "zaro-kezelo" )
{
	foreach($_SESSION['destroyFile'] as $file)
	{
		unlink($file);
	}
	unset($_SESSION['destroyFile']);
}

require_once "../config.php";
require_once "../foglalas_engine.php";

if (isTesztIP()) {
	error_reporting(E_ALL);
	ini_set('display_errors',1);
}

if (!isset($_SESSION["helyszindata"]["megnev"])) die("domain not found");

if (!isset($_GET["page"])) $_GET["page"]="bnaptar";
if (!isset($_SESSION["helyid"])) $_SESSION["helyid"]=1;


if (isset($_COOKIE["pid"])) $_SESSION["pid"]=$_COOKIE["pid"];


if (isset($_SESSION["pid"])) {
	$user=sql_fetch_array(sql_query("select * from users where id='".addslashes($_SESSION["pid"])."'"));
	$_SESSION["adminuser"]=$GLOBALS["adminuser"]=$user;
}

header("Content-type: text/html; charset=UTF-8");
require_once "elojegyzestable.php";
require_once "ajax.php";

ob_start();

echo htmlheader("{$_SESSION["helyszindata"]["megnev"]} orvosi felület");
echo "<body>";
echo "<div class = 'shader' style = 'display:none'></div>";

if (!isset($_SESSION["pid"])) {	
	include("inc_login.php");
}




if ($user["localeaccess"]==1 && substr_count($GLOBALS["adminuser"]["localeip"],$_SERVER["REMOTE_ADDR"])==0) {
	//echo $GLOBALS["adminuser"]["localeip"]." ".$_SERVER["REMOTE_ADDR"];
	echo "<div id='errordiv' style='background:#f00;padding:10px;font-weight:bold;color:#fff;text-align:center;'>Ez a fiók csak lokálisan engedélyezett.</div>";
	echo "<div style='margin-top:20px;text-align:center;'><a href='index.php?logoutadmin'>kijelentkezés</a></div>";
	echo "</body>";
	echo "</html>";
	die();
}


if (isset($user["auth2fac"]) && $user["auth2fac"]==1 && $user["tel"]!="") {
	if (!isset($_SESSION["2facomplete"])) {
		include("inc_orvos2fa.php");
	}
}

?>
<script>
$(document).ready(function(){
		var width  = $(window).width();
		var height = $(window).height();
		height = ( height/10 );
		console.log(height);
		$('.scrollToTop').css({'bottom':height+'px','right':'200px'});
		// Make the DIV element draggable:
		//dragElement(document.getElementById('ClickBox'));

		function dragElement(elmnt) {
		 
		  var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
		  if (document.getElementById(elmnt.id + 'header')) {
			// if present, the header is where you move the DIV from:
			document.getElementById(elmnt.id + 'header').onmousedown = dragMouseDown;
		  } else {
			// otherwise, move the DIV from anywhere inside the DIV:
			elmnt.onmousedown = dragMouseDown;
		  }

		  function dragMouseDown(e) {
			e = e || window.event;
			e.preventDefault();
			// get the mouse cursor position at startup:
			pos3 = e.clientX;
			pos4 = e.clientY;
			document.onmouseup = closeDragElement;
			// call a function whenever the cursor moves:
			document.onmousemove = elementDrag;
		  }

		  function elementDrag(e) {
			$('#dragElement').css('margin','');
			e = e || window.event;
			e.preventDefault();
			// calculate the new cursor position:
			pos1 = pos3 - e.clientX;
			pos2 = pos4 - e.clientY;
			pos3 = e.clientX;
			pos4 = e.clientY;
			// set the element's new position:
			elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
			elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
		  }

		  function closeDragElement() {
			// stop moving when mouse button is released:
			document.onmouseup = null;
			document.onmousemove = null;
		  }
		}
	});
	$(document).scroll(function() {
	  var y = $(this).scrollTop();
	  if (y > 800) {
		$('.scrollToTop').fadeIn();
	  } else {
		$('.scrollToTop').fadeOut();
	  }
	});
</script>
<script>
$( function() {
$( "#draggableBox" ).draggable();
} );
</script>
<?php
echo "<div class = 'scrollToTop' onClick = 'scrollToTop()'><i style = 'margin-top:8px' class='fas fa-arrow-up'></i></div>";
echo "<div id='sound' style='display:none;'></div>";
echo "<div class='szamlalo'>";
echo "<table class = 'ui-table'>";
echo "<tr>";

//Menedzerszűrés ellenőrzése:
if( checkWarningsList() > 0 )
{
echo "<td><img src = 'images/8e6671189d.png' onClick = '$(\".manager-warnings\").toggle()' style = 'cursor:pointer' width='30' />";
echo "<div style = 'position:relative;color:black;'>";
echo "<div class = 'manager-warnings' id = 'draggableBox' >";
echo "	<div class = 'WL-sidePanel'>";
//echo "		<div class = 'WL-sidePanel-title'><span style = ''>Placeholder</span><span><i style = 'cursor:pointer;' onClick = '$(\".WL-sidePanel\").animate({width: \"toggle\"});' class='fas fa-times'></i></span></div>";
echo "	</div>";
echo "	<div class = 'warrnings-top-border'>";
echo "	<span style = 'font-size:16px'></span>&ensp;";
echo "	<i class='fas fa-sync-alt' onClick = 'refreshWList()' style = 'cursor:pointer' ></i>&ensp;";
echo "	<i onClick = 'changeWLPosition()' style = 'cursor:pointer' class='fas fa-thumbtack'></i>&ensp;";
echo "	<i onClick = 'openSidePanel(\"option-1\")' style = 'cursor:pointer' class='fas fa-cog'></i>&ensp;";
echo "	<i onClick = '$(\"body\").removeHighlight()' style = 'cursor:pointer' class='fas fa-lightbulb'></i>&ensp;";
echo "	<i onClick = '$(\".manager-warnings\").hide();cpy=0;foglalasSelected=0;foglalasSelectedPass=0;' style = 'cursor:pointer;' class='fas fa-times-circle'></i>&ensp;";
echo "</div>";
echo "	<div class = 'warrnings-content'>".displayWarnings()."</div>";
echo 	"<div id = 'LWOpener-container'>".listWarningsOpener()."</div>";;
echo "</div>";
echo "</div>";
}
echo "</td>";
if ($_SESSION["adminuser"]["jogosultsag"]>=2) {
	echo "<td><a href='index.php?page=adminlog'>LOG</a></td>";
	echo "<td><span style='color:#fff;background:#0a0;padding:2px 5px;border-radius:2px;'>ADMIN</span></td>";
}
if ($_SESSION["adminuser"]["jogosultsag"]==1) echo "<td style='color:#fff;background:#00a;padding:2px 5px;border-radius:2px;'>CÉG ADMIN</td>&nbsp;&nbsp;";
if ($_SESSION["adminuser"]["jogosultsag"]==0) echo "<td style='color:#fff;background:#aaa;padding:2px 5px;border-radius:2px;'>RECEPCIÓ</td>&nbsp;&nbsp;";
echo "<td>Felhasználó: <span style='color:#44f;'>{$user["nev"]}</span> - <a href='index.php?logoutadmin'>kijelentkezés</a></td>";
echo "</tr>";
echo "</table></div>";
/*if ($_SESSION["adminuser"]["jogosultsag"]>=2) {
	echo "<span><a href='index.php?page=adminlog'>LOG</a></span>&nbsp;&nbsp;";
	echo "<span style='color:#fff;background:#0a0;padding:2px 5px;border-radius:2px;'>ADMIN</span>&nbsp;&nbsp;";
}
if ($_SESSION["adminuser"]["jogosultsag"]==1) echo "<span style='color:#fff;background:#00a;padding:2px 5px;border-radius:2px;'>CÉG ADMIN</span>&nbsp;&nbsp;";
if ($_SESSION["adminuser"]["jogosultsag"]==0) echo "<span style='color:#fff;background:#aaa;padding:2px 5px;border-radius:2px;'>RECEPCIÓ</span>&nbsp;&nbsp;";
//} else {
	//echo "{$_SESSION["helyszindata"]["megnev"]}</b> - ";
//}
echo " Felhasználó: <span style='color:#44f;'>{$user["nev"]}</span> - <a href='index.php?logoutadmin'>kijelentkezés</a></div>";*/


echo "<table width='100%' cellpadding='0' cellspacing='0' border='0'>";

echo "<tr>";
echo "<td valign='top' width='150' class='menuoszlop'>";



$subdomain=$_SESSION["helyszindata"]["domain"];

echo "<div align='center' style='margin-top:-20px;padding-right:5px;'><img width='120' src='/images/hmm_logo_nagy.png' /></div>";
if (is_file("images/logo_{$subdomain}.png") || is_file("../images/logo_{$subdomain}.png")) echo "<div align='center' style='padding-right:5px;'><img width='120' src='/images/logo_{$subdomain}.png' /></div>";


echo "<div style='padding-top:10px;padding-bottom:10px;font-size:12px;font-weight:bold;color:#9cf3c3;'>";


$adminmenu[]=array("bnaptar","Bejelentkezések","",0);
$adminmenu[]=array("elojegyzestabla","Előjegyzés táblázat","",1);
$adminmenu[]=array("blista","Érkeztetés","",2);
$adminmenu[]=array("felhasznalok","Páciensek","",0);
if($_SESSION['adminuser']['jog_helyszinset'] == 1) $adminmenu[]=array("helyszinek","Helyszínek","+ új helyszín",(helyszinModJog()?2:99));
if($_SESSION['adminuser']['jog_orvosset'] == 1) $adminmenu[]=array("orvosok","Orvosok","+ új orvos",2);
if($_SESSION['adminuser']['jog_szurestipusset'] == 1) $adminmenu[]=array("szurestipusok","Szűrés tipusok","+ új tipus",(szurestipusModJog()?2:99));
if($_SESSION['adminuser']['jog_cegset'] == 1) $adminmenu[]=array("cegek","Cégek","+ új cég",(cegModJog()?2:99));
if($_SESSION['adminuser']['jog_jogset'] == 1) $adminmenu[]=array("users","Adminisztrátorok","+ új admin",2);
if($_SESSION['adminuser']['jog_statisztika'] == 1) $adminmenu[]=array("stat","Statisztika","",2);
if($_SESSION['adminuser']['jog_beallitasok'] == 1) $adminmenu[]=array("settings","Beállítások","",2);
if($_SESSION['adminuser']['jog_gdprhferes'] == 1)$adminmenu[]=array("gdpr","GDPR","",0);
if($_SESSION['adminuser']['jog_zarolista'] == 1) $adminmenu[]=array("zarok","Záró leletek","",0);
if($_SESSION['adminuser']['jog_kuponlista'] == 1) $adminmenu[]=array("kuponok","Kuponok","",2);
$adminmenu[]=array("adminlog","Tevékenység napló","",99);
$adminmenu[]=array("settings_lang","Többnyelvű szövegek","",99);


for ($i=0;$i<count($adminmenu);$i++) {
	if ($adminmenu[$i][3]<=$user["jogosultsag"]) {
		echo "<div><a class='mainmenuitem".($_GET["page"]==$adminmenu[$i][0]?"_aktiv":"")."' href='index.php?page={$adminmenu[$i][0]}'>{$adminmenu[$i][1]}</a></div>";
	}
}


echo "</td>";

echo "<td valign=top style='background-color:#fff;box-shadow:-0px 0px 10px #bbb;'>";
echo "<div style='margin:20px;min-height:400px;'>";


for ($i=0;$i<count($adminmenu);$i++) {
	if ($_GET["page"]==$adminmenu[$i][0]) {
		echo "<div class='pagehead'>";
		echo "<div style='display:table-cell;vertical-align:middle;'>{$adminmenu[$i][1]}".($_GET["page"]=="elojegyzestdfdabla"?"&nbsp;&nbsp;<span style='background:#0a0;color:#fff;font-size:16px;font-weight:bold;padding:3px 8px;border-radius:10px;'>BÉTA</span>":"")."</div>";
		
		if ($adminmenu[$i][2]<>"" && !isset($_GET["szerk"])) {
			echo "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&addnew'>{$adminmenu[$i][2]}</a></div>";
		}
		if($adminmenu[$i][0] == "felhasznalok" && !isset($_GET["szerk"]) && $user["jogosultsag"] >= 1) {
			echo "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page=alkalmassagi'>Alkalmassági lista</a></div>";
		}
		if (isset($_GET["szerk"])) {
			echo "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}'>Vissza</a></div>";
		}
		if($_GET['page'] == "zarok" && ( isset($_GET['status']) && $_GET['status'] == "open" || !isset($_GET['status'])))
		{
			echo "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='index.php?page=zarok&status=closed'>Lezártak</a></div>";
		}
		if($_GET['page'] == "zarok" &&  isset($_GET['status']) && $_GET['status'] == "closed" )
		{
			echo "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'>&nbsp;<a class='ujbutton' href='index.php?page=zarok&status=open'>Nyitottak</a></div>";
		}
		if($_GET['page'] == "zaro-kezelo")
		{
			echo "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'>&nbsp;<a class='ujbutton' href='index.php?page=zarok'>Vissza</a></div>";
		}
		if($_GET['page'] == "gdpr" &&  isset($_GET['status']) && $_GET['status'] == "closed")
		{
			echo "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'>&nbsp;<a class='ujbutton' href='index.php?page=gdpr&status=open'>Aktuálisak</a></div>";
		}
		if($_GET['page'] == "gdpr" && ( isset($_GET['status']) && $_GET['status'] == "open" || !isset($_GET['status'])))
		{
			echo "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='index.php?page=gdpr&status=closed'>Archív</a></div>";
		}
		if($_GET['page'] == "gdpr_edit")
		{
			echo "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'>&nbsp;<a class='ujbutton' href='index.php?page=gdpr'>Vissza</a></div>";
		}
		//echo "<br clear=all>";
		echo "</div>";
	}
}

/*
if (isTesztIP() and $_GET["page"]=="bnaptar") {
	include("inc_{$_GET["page"]}_old.php");
} else {
	include("inc_{$_GET["page"]}.php");
}
*/

include("inc_{$_GET["page"]}.php");


ob_flush();

echo "</div>";
echo "</td>";

echo "</tr>";

echo "<tr><td></td><td>";
echo "<div class='footersor'>&copy; HMM</div>";
echo "</td></tr>";

echo "</table>";

echo "</body>";
echo "</html>";

