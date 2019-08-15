<?php


if (!isset($_POST["email"])) {
	$_POST["helyszin"]=$_POST["datum"]=$_POST["szurestipus"]=$_POST["email"]=$_POST["nev"]=$_POST["telefon"]=$_POST["szuldatum"]=$_POST["taj"]=$_POST["irsz"]=$_POST["varos"]=$_POST["utca"]=$_POST["munkaltato"]=$_POST["munkakor"]=$_POST["nev"]=$_POST["nev"]=$_POST["megj"]=$_POST["captcha"]=$_POST["szulhely"]=$_POST["anyjaneve"]=$_POST["telephely"]="";
	$_POST["rinterval"]=0;
	if (isset($_SESSION["user"])) {
		$_POST["taj"]=$_SESSION["user"]["taj"];
		$_POST["email"]=$_SESSION["user"]["email"];
		$_POST["nev"]=$_SESSION["user"]["nev"];
		$_POST["telefon"]=$_SESSION["user"]["telefon"];
		$_POST["szuldatum"]=$_SESSION["user"]["szuldatum"];
		$_POST["szulhely"]=$_SESSION["user"]["szulhely"];
		$_POST["anyjaneve"]=$_SESSION["user"]["anyjaneve"];
		$_POST["irsz"]=$_SESSION["user"]["irsz"];
		$_POST["varos"]=$_SESSION["user"]["varos"];
		$_POST["utca"]=$_SESSION["user"]["utca"];
		$_POST["munkakor"]=$_SESSION["user"]["munkakor"];
		$_POST["neme"]=$_SESSION["user"]["neme"];
	}
}

if (!isset($_POST["neme"])) $_POST["neme"]="";




echo displayFejlec();


if (isset($_SESSION["user"]) && $_SESSION["user"]["validated"]==0) {
	include("inc_validatelogin.php");
	return;
}


if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}



if ($_SESSION["helyszindata"]["onlybeutalo"]==1 && isset($_SESSION["user"]) && !isset($_SESSION["beutaloid"])) {
	echo "<div style=''>{$webText["csakbeutalodesc"]}</div>";
	echo "<div style='margin-top:10px;'><a class='simabutton' href='index.php?page=beutalok'>{$webText["showbeutalobutton"]}</a></div>";
	//echo "</div>";
	return;
}

if (isset($_SESSION["beutaloid"])) {
	if (!$beutalodata=sql_fetch_array(sql_query("select * from beutalok where id='".intval($_SESSION["beutaloid"])."' and foglalasid=0"))) {
		echo "<div style=''>A beutalóval probléma adodott!</div>";
		echo "<div style='margin-top:10px;'><a class='simabutton' href='index.php?page=beutalok'>{$webText["showbeutalobutton"]}</a></div>";
		//echo "</div>";
		return;
	}
}


if ($_SESSION["helyszindata"]["onlyreg"]==1 && !isset($_SESSION["user"])) {
	$btext=$webText["mainudvozles"];
	
	if ($rowsz=sql_fetch_array(sql_query("select * from szovegek where cegid=? and tipus='welcome'",array($_SESSION["helyszindata"]["id"])))) {
		$btext=$rowsz["szoveg"];
	}
	
	echo "<div style=''>{$btext}</div>";

	echo "<div style='margin-top:20px;'><a href='index.php?page=reg' class='newbutton'>{$webText["regisztracio"]}</a>&nbsp;&nbsp;<a href='index.php?page=login' class='newbutton'>{$webText["bejelentkezes"]}</a></div>";
	//echo "</div>";
	return;
}



echo "<form name='iform' method='post' enctype='multipart/form-data'>";
echo "<table style='font-size:12px;'>";

echo "<tr><td width='120'>{$webText["tajszam"]}: *</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' onchange='clearIdopontValaszto();'  value='{$_POST["taj"]}'></td></tr>";

//Kérjük akkut egészségkárosodás vagy életveszély esetén azonnal hívja az 104-es országos mentőszolgálat vagy a 112 központi segélyhívót.

if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_COOKIE["lang"]!="hu" && trim($_SESSION["helyszindata"]["beutaloszoveg_{$_COOKIE["lang"]}"])!="") $_SESSION["helyszindata"]["beutaloszoveg"]=$_SESSION["helyszindata"]["beutaloszoveg_{$_COOKIE["lang"]}"];



if (isset($beutalodata)) {
	//beutalóval fix választás
	
	if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_SESSION["helyszindata"]["beutaloszoveg"]!="") echo "<tr><td></td><td><div style='font-weight:bold;padding:5px 0px;'>{$_SESSION["helyszindata"]["beutaloszoveg"]}</div><td></tr>";
	echo "<tr><td>{$webText["helyszin"]}: *</td><td>";
	echo "<select name='helyszin' id='helyszin'>";
	$res=sql_query("SELECT h.*,".cimLangQuery()." FROM helyszinek h where h.id='{$beutalodata["helyszinid"]}'");
	if ($rowt=sql_fetch_array($res)) echo "<option value='{$rowt["id"]}' selected>{$rowt["cim"]}</option>";
	echo "</select>";
	echo "</td></tr>";

	echo "<tr><td>{$webText["szurestipus"]}: *</td><td><div id='szurestipusvalaszto'>".szuresTipusValasztoNew($beutalodata["helyszinid"],$beutalodata["szurestipusid"],1)."</div></td></tr>";
	$tipusMegj=getTipusMegj($_SESSION["helyszindata"]["id"],$beutalodata["szurestipusid"],$beutalodata["helyszinid"]);
	if ($tipusMegj!="") echo "<tr><td></td><td><div id='szurestipusmegj'>{$tipusMegj}</div></td></tr>";
} else {
	//beutaló nélkül szabad választás
	
	if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_SESSION["helyszindata"]["beutaloszoveg"]!="") echo "<tr><td></td><td><div style='font-weight:bold;padding:5px 0px;'>{$_SESSION["helyszindata"]["beutaloszoveg"]}</div><td></tr>";
	echo "<tr><td>{$webText["helyszin"]}: *</td><td>";
	
	echo "<select name='helyszin' id='helyszin' onchange='clearIdopontValaszto();clearSzuresTipus(this.value);'>";
	$res=sql_query("SELECT h.*,".cimLangQuery()." FROM helyszinek h 
	LEFT JOIN orvos_beosztas b ON b.`helyszinid`=h.id 
	LEFT JOIN orvosok o on b.orvosid=o.id
	WHERE h.aktiv=1 AND o.aktiv=1 AND b.`helyszinid` IS NOT NULL and b.cegid='{$_SESSION["helyszindata"]["id"]}' GROUP BY h.id ORDER BY cim");
	
	$numOfH=sql_num_rows($res);
	
	
	echo "<option value='0'>{$webText["valasszhelyszint"]}</option>";
	while ($rowt=sql_fetch_array($res)) {
		if ($_SESSION["helyszindata"]["nocim"]==1) $rowt["cim"]=$rowt["megnev"];
		
		echo "<option value='{$rowt["id"]}'".($_POST["helyszin"]==$rowt["id"] || $numOfH==1?" selected":"").">{$rowt["cim"]}</option>";
		if ($numOfH==1) {
			$_POST["helyszin"]=$rowt["id"];
			$_POST["szurestipus"]=0;
		}
	}
	echo "</select>";
	
	//print_r($_SESSION["helyszindata"]);
	echo "</td></tr>";

	echo "<tr><td>{$webText["szurestipus"]}: *</td><td height='30'><div id='szurestipusvalaszto'>".szuresTipusValasztoNew($_POST["helyszin"],$_POST["szurestipus"])."</div></td></tr>";
	$tipusMegj=getTipusMegj($_SESSION["helyszindata"]["id"],$_POST["szurestipus"],$_POST["helyszin"]);
	echo "<tr><td></td><td><div id='szurestipusmegj'>{$tipusMegj}</div></td></tr>";
}

$nofoglalasText = trim($_SESSION["helyszindata"]["nofoglalas_{$_COOKIE["lang"]}"]);
if ($nofoglalasText == "") {
    echo "<tr><td valign='top'><div style='margin-top:5px;'>{$webText["idopont"]}: *</div></td><td><table cellpadding='0' cellspacing='0'><tr><td><input type='hidden' name='rinterval' id='rinterval' value='{$_POST["rinterval"]}' /><input placeholder='{$webText["kattintsagombra"]}' readonly class='inputbox' style='width:120px;height:19px;margin-right:5px;' type='text' name='datum' id='datum' value='" . substr($_POST["datum"], 0, 16) . "'></td><td><a href='#' onclick='showIdoPontValasztoV2(0);return false;' class='newbuttonfoglalas'>{$webText["idopontvalasztas"]}</a></td><td><img id='loadingspinner' style='margin-left:5px;height:25px;display:none;' src='/images/loading.svg' /></td></tr></table></td></tr>";
    echo "<tr><td></td><td><div id='idopontvalasztodiv' style='display:none;'></div></td></tr>";
} else {
    echo "<tr><td></td><td>{$nofoglalasText}</td></tr>";
}

echo "<tr><td></td><td>&nbsp;</td></tr>";

echo "<tr><td></td><td>";
if ($_SESSION["helyszindata"]["onlyreg"]==0) echo "<div>{$webText["dokfelinfo"]}</div>";
echo "<div class='upload-btn-wrapper'><button class='upbtn'>{$webText["dokumentumfeltoltese"]}</button><input type='file' id='paciensfile' name='paciensfile[]' multiple /></div><img id='paciensloader' style='display:none;opacity:.5;height:30px;margin-left:10px;' src='/images/loading.svg' />";
echo "</td></tr>";
echo "<tr><td></td><td><div id='paciensfilediv'>".showPaciensFiles()."</div></td></tr>";

if (trim($_SESSION["helyszindata"]["telephelyek"]) != "") {
    echo "<tr><td>{$webText["munkaltato"]}: *</td><td><select name='telephely' id='telephely'>";
    $telephelyek = explode(",",$_SESSION["helyszindata"]["telephelyek"]);
    echo "<option value=''>{$webText["valasszmunkaltatot"]}!</option>";
    foreach ($telephelyek as $telephely) {
        $telephely = trim($telephely);
        echo "<option value='{$telephely}'".($_POST["telephely"]==$telephely?" selected":"").">{$telephely}</option>";
    }
    echo "</select></td></tr>";
}
echo "<tr><td>{$webText["email"]}: *</td><td><input class='inputbox' style='width:250px;' type='text' name='email' value='{$_POST["email"]}' /></td></tr>";
echo "<tr><td></td><td>{$webText["kerjukugyeljenemail"]}</td></tr>";
echo "<tr><td>{$webText["nev"]}: *</td><td><input class='inputbox' style='width:250px;' type='text' name='nev' value='{$_POST["nev"]}' /></td></tr>";
echo "<tr><td>{$webText["mobil"]}: *</td><td><input class='inputbox' style='width:250px;' type='text' name='telefon' value='{$_POST["telefon"]}' placeholder='Formátum pl: 06301234567' /></td></tr>";
//echo "<tr><td></td><td>{$webText["mobiltip"]}</td></tr>";
echo "<tr><td>{$webText["szuletesidatum"]}: *</td><td>".datumSelector($_POST["szuldatum"],"szuldatum")."</td></tr>";

if($_SESSION['helyszindata']['id'] != 46) echo "<tr><td>{$webText["szuletesihely"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='szulhely' value='{$_POST["szulhely"]}' placeholder='' /></td></tr>";
else echo "<input type='hidden' name='szulhely' value='' />";
if($_SESSION['helyszindata']['id'] != 46) echo "<tr><td>{$webText["anyjaneve"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='anyjaneve' value='{$_POST["anyjaneve"]}' placeholder='' /></td></tr>";
else echo "<input type='hidden' name='anyjaneve' value='' />";
echo "<tr><td>{$webText["neme"]}: *</td><td><input type='radio' name='neme' value='1' ".($_POST["neme"]==1?"checked":"")."/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_POST["neme"]==2?"checked":"")."/> {$webText["no"]}</td></tr>";
if($_SESSION['helyszindata']['id'] != 46) echo "<tr><td>{$webText["irsz"]}:</td><td><input class='inputbox' style='width:60px;' type='text' name='irsz' value='{$_POST["irsz"]}' /></td></tr>";
else echo "<input type='hidden' name='irsz' value='' />";
if($_SESSION['helyszindata']['id'] != 46) echo "<tr><td>{$webText["varos"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='varos' value='{$_POST["varos"]}' /></td></tr>";
else echo "<input type='hidden' name='varos' value='' />";
if($_SESSION['helyszindata']['id'] != 46) echo "<tr><td>{$webText["utca"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='utca' value='{$_POST["utca"]}' /></td></tr>";
else echo "<input type='hidden' name='utca' value='' />";

if (!in_array($_SESSION["helyszindata"]["domain"],array("bejelentkezes","gyor-bejelentkezes"))) {
	echo "<tr><td>{$webText["munkakor"]}: *</td><td><input class='inputbox' style='width:250px;' type='text' name='munkakor' value='{$_POST["munkakor"]}' /></td></tr>";
}



if (!isset($beutalodata)) {
	echo "<tr><td width=100>{$webText["megjegyzes"]}:</td><td><div id='fogleuwarn' style='display:none;margin-top:5px;color:#f00;font-weight:bold;'>Kérjük adja meg a megjegyzés rovatban a céget, ahonnan érkezik</div>";
	echo "<textarea class='inputbox' style='height:100px;width:400px;' name='megj' id='foglmegj'>{$_POST["megj"]}</textarea>";
	//apollo tyres kivétel
	if ($_SESSION["helyszindata"]["id"]==43) {
		echo "<div>";
		 //Indiába menő, előzetes, soron kívüli, Indiából hazatérő. Illetve: Hollandiába menő, előzetes, soron kívüli, Hollandiából hazatérő
		echo "<span class='addmegjlink'>Indiába menő</span> &bull; ";
		echo "<span class='addmegjlink'>Előzetes</span> &bull; ";
		echo "<span class='addmegjlink'>Soron kívüli</span> &bull; ";
		echo "<span class='addmegjlink'>Indiából hazatérő</span><br/>";
		echo "<span class='addmegjlink'>Hollandiába menő</span> &bull; ";
		echo "<span class='addmegjlink'>Előzetes</span> &bull; ";
		echo "<span class='addmegjlink'>Soron kívüli</span> &bull; ";
		echo "<span class='addmegjlink'>Hollandiából hazatérő</span>";
		echo "</div>";
		
	}
	echo "</td></tr>";
}

if (!isset($_SESSION["captcha"])) $_SESSION["captcha"]=rand(110,988);
if (!isset($_SESSION["user"])) {
	//echo "<tr><td colspan='2'><div style='margin-top:10px;'>Kérem, adja meg a következő számot számjegyekkel: ".numtostring($_SESSION["captcha"]).":<br><input class='inputbox' style='width:60px;' type='text' name='captcha' value='{$_POST["captcha"]}'></div></td></tr>";
	echo "<tr><td></td><td><div class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
}



if (!isset($_SESSION["user"])) {
	echo "<tr><td><td><div style='margin-top:10px;'><input type='checkbox' name='aszf' value='1' ".(isset($_POST["aszf"])?"checked":"")."/> {$webText["aszfelf"]}</div>";
	echo "<div id='adatvedelem' style='display:none;'>";

	echo "<div style='margin-top:10px;'>".getASZF()."</div>";
	echo "</div>";
	echo "</td></tr>";
}

echo "<tr><td></td><td><div style='margin-top:20px;'><a href='#' class='newbutton' onclick='document.iform.submit();return false;'>{$webText["idopontfoglalasa"]}</a><span id='warnidopontpress' style='display:none;color:#41b6c6;margin-left:5px;'>&#9664;<span class='warnidopontpress'>{$webText["idopontfoglalasawarn"]}</span></span><div></td></tr>";


echo "</table>";

if (isset($_SESSION["user"])) echo "<input type='hidden' name='aszf' value='1'/>";
echo "<input type='hidden' name='idopontfoglalas' value='1'/>";
echo "<input type='hidden' name='version2' value='1'/>";
echo "<input type='hidden' name='orvosselected' id='orvosselected' value='{$_SESSION["orvosselected"]}'/>";

//echo "<br/><br/><input type='submit' name='idopontfoglalas' value='Időpont foglalása'/> ";
//echo "<div style='margin-top:20px;'><a href='#' class='newbutton' onclick='document.iform.submit();return false;'>Időpont foglalása</a><div>";
//echo "<input type='submit' name='scancel' value='Vissza'> ";



echo "</form>";



?>