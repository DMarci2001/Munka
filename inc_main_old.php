<?php


if (!isset($_POST["email"])) {
	$_POST["helyszin"]=$_POST["datum"]=$_POST["szurestipus"]=$_POST["email"]=$_POST["nev"]=$_POST["telefon"]=$_POST["szuldatum"]=$_POST["taj"]=$_POST["irsz"]=$_POST["varos"]=$_POST["utca"]=$_POST["munkaltato"]=$_POST["munkakor"]=$_POST["nev"]=$_POST["nev"]=$_POST["megj"]=$_POST["captcha"]="";
	if (isset($_SESSION["user"])) {
		$_POST["taj"]=$_SESSION["user"]["taj"];
		$_POST["email"]=$_SESSION["user"]["email"];
		$_POST["nev"]=$_SESSION["user"]["nev"];
		$_POST["telefon"]=$_SESSION["user"]["telefon"];
		$_POST["szuldatum"]=$_SESSION["user"]["szuldatum"];
		$_POST["irsz"]=$_SESSION["user"]["irsz"];
		$_POST["varos"]=$_SESSION["user"]["varos"];
		$_POST["utca"]=$_SESSION["user"]["utca"];
		$_POST["munkakor"]=$_SESSION["user"]["munkakor"];
		$_POST["neme"]=$_SESSION["user"]["neme"];

	}
}

if (!isset($_POST["neme"])) $_POST["neme"]="";



echo "<div style='background-color:#fff;border-radius:5px;padding:20px;'>";

echo displayFejlec();


if (isset($_SESSION["user"]) && $_SESSION["user"]["validated"]==0) {
	include("inc_validatelogin.php");
	return;
}


if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}



if ($_SESSION["helyszindata"]["onlybeutalo"]==1 && isset($_SESSION["user"]) && !isset($_SESSION["beutaloid"])) {
	echo "<div style=''>Az online időpont foglaló felületet csak beutalón keresztül veheti igénybe!</div>";
	echo "<div style=''>A beutalói megtekintéséhez kérjük kattintson az alábbi linkre:</div>";
	echo "<div style='margin-top:10px;'><a class='simabutton' href='index.php?page=beutalok'>Beutalók megtekintése</a></div>";
	echo "</div>";
	return;
}

if (isset($_SESSION["beutaloid"])) {
	if (!$beutalodata=sql_fetch_array(sql_query("select * from beutalok where id='".intval($_SESSION["beutaloid"])."' and foglalasid=0"))) {
		echo "<div style=''>A beutalóval probléma adodott!</div>";
		echo "<div style='margin-top:10px;'><a class='simabutton' href='index.php?page=beutalok'>Beutalók megtekintése</a></div>";
		echo "</div>";
		return;
	}
}


if ($_SESSION["helyszindata"]["onlyreg"]==1 && !isset($_SESSION["user"])) {
	$btext="Az online időpont foglaló felület csak regisztrációval használható!<br/>Kérjük regisztrálj, vagy jelentkezz be.";
	
	if ($rowsz=sql_fetch_array(sql_query("select * from szovegek where cegid=? and tipus='welcome'",array($_SESSION["helyszindata"]["id"])))) {
		$btext=$rowsz["szoveg"];
	}
	
	echo "<div style=''>{$btext}</div>";
	echo "</div>";
	return;
}



echo "<form name='iform' method='post' enctype='multipart/form-data'>";
echo "<table style='font-size:12px;'>";

echo "<tr><td width='100'>TAJ szám: *</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' onchange='clearIdopontValaszto();'  value='{$_POST["taj"]}'></td></tr>";

//Kérjük akkut egészségkárosodás vagy életveszély esetén azonnal hívja az 104-es országos mentőszolgálat vagy a 112 központi segélyhívót.


if (isset($beutalodata)) {
	//beutalóval fix választás
	
	echo "<tr><td width='100'>Helyszín: *</td><td>";
	echo "<select name='helyszin' id='helyszin'>";
	$res=sql_query("SELECT h.* FROM helyszinek h where h.id='{$beutalodata["helyszinid"]}'");
	if ($rowt=sql_fetch_array($res)) echo "<option value='{$rowt["id"]}' selected>{$rowt["cim"]}</option>";
	echo "</select>";
	echo "</td></tr>";

	echo "<tr><td width='100'>Szűrés tipusa: *</td><td><div id='szurestipusvalaszto'>".szurestipusvalaszto($beutalodata["helyszinid"],$beutalodata["szurestipusid"],1)."</div></td></tr>";
	$tipusMegj=getTipusMegj($_SESSION["helyszindata"]["id"],$beutalodata["szurestipusid"]);
	if ($tipusMegj!="") echo "<tr><td width='100'></td><td><div id='szurestipusmegj'><div id='szurestipusmegjtext' style='background:#f00;color:#fff;padding:10px;display:inline-block;font-weight:bold;'>{$tipusMegj}</div></div></td></tr>";
} else {
	//beutaló nélkül szabad választás
	
	if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_SESSION["helyszindata"]["beutaloszoveg"]!="") echo "<tr><td width='100'></td><td><div style='font-weight:bold;padding:5px 0px;'>{$_SESSION["helyszindata"]["beutaloszoveg"]}</div><td></tr>";
	echo "<tr><td width='100'>Helyszín: *</td><td>";
	
	/*
	echo "SELECT h.* FROM helyszinek h 
	LEFT JOIN orvos_beosztas b ON b.`helyszinid`=h.id 
	LEFT JOIN orvosok o on b.orvosid=o.id
	WHERE h.aktiv=1 AND o.aktiv=1 AND b.`helyszinid` IS NOT NULL and b.cegid='{$_SESSION["helyszindata"]["id"]}' GROUP BY h.id ORDER BY cim";
	*/
	echo "<select name='helyszin' id='helyszin' onchange='clearIdopontValaszto();clearSzuresTipus(this.value);'>";
	$res=sql_query("SELECT h.* FROM helyszinek h 
	LEFT JOIN orvos_beosztas b ON b.`helyszinid`=h.id 
	LEFT JOIN orvosok o on b.orvosid=o.id
	WHERE h.aktiv=1 AND o.aktiv=1 AND b.`helyszinid` IS NOT NULL and b.cegid='{$_SESSION["helyszindata"]["id"]}' GROUP BY h.id ORDER BY cim");
	
	$numOfH=sql_num_rows($res);
	
	
	echo "<option value='0'>Válassz helyszínt!</option>";
	while ($rowt=sql_fetch_array($res)) {
		echo "<option value='{$rowt["id"]}'".($_POST["helyszin"]==$rowt["id"] || $numOfH==1?" selected":"").">{$rowt["cim"]}</option>";
		if ($numOfH==1) {
			$_POST["helyszin"]=$rowt["id"];
			$_POST["szurestipus"]=0;
		}
	}
	echo "</select>";
	echo "</td></tr>";

	echo "<tr><td width='100'>Szűrés tipusa: *</td><td><div id='szurestipusvalaszto'>".szurestipusvalaszto($_POST["helyszin"],$_POST["szurestipus"])."</div></td></tr>";
	echo "<tr><td width='100'></td><td><div id='szurestipusmegj' style='display:none;'><div id='szurestipusmegjtext' style='background:#f00;color:#fff;padding:10px;display:inline-block;font-weight:bold;'></div></div></td></tr>";
}


echo "<tr><td width=100 valign='top'><div style='margin-top:5px;'>Időpont: *</div></td><td><input placeholder='kattints a gombra' readonly class='inputbox' style='width:120px;' type=text name='datum' id='datum' value='".substr($_POST["datum"],0,16)."'> <input onclick='showIdoPontValaszto(0);' type='button' value='Időpont választás'><div id='idopontvalasztodiv'></div></td></tr>";

echo "</td></tr>";

echo "<tr><td width=100></td><td>&nbsp;</td></tr>";

echo "<tr><td width=100>E-mail: *</td><td><input class='inputbox' style='width:250px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
echo "<tr><td width=100></td><td>Kérjük ügyeljen arra, hogy e-mail címét helyesen adja meg, mert a foglalásról a visszaigazolást az Ön által megadott email címre fogjuk elküldeni és csak így tudja majd véglegesíteni foglalását.</td></tr>";
echo "<tr><td width=100>Név: *</td><td><input class='inputbox' style='width:250px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
echo "<tr><td width=100>Telefonszám: *</td><td><input class='inputbox' style='width:250px;' type='text' name='telefon' value='{$_POST["telefon"]}' placeholder='Formátum pl: 06301234567' ></td></tr>";
echo "<tr><td width=100></td><td>Kérjük olyan telefonszámot adjon meg, amin el tudjuk érni, ha az esetleges változásokkal kapcsolatban értesíteni akarjuk!</td></tr>";
echo "<tr><td width=100>Születési dátum: *</td><td>";
echo datumSelector($_POST["szuldatum"],"szuldatum");
//echo "<input class='inputbox' style='width:120px;' type='text' name='szuldatum' value='{$_POST["szuldatum"]}'>";
echo "</td></tr>";
echo "<tr><td width=100>Neme:</td><td><input type='radio' name='neme' value='1' ".($_POST["neme"]==1?"checked":"")."/> Férfi&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_POST["neme"]==2?"checked":"")."/> Nő</td></tr>";
echo "<tr><td width=100>Irányítószám:</td><td><input class='inputbox' style='width:60px;' type='text' name='irsz' value='{$_POST["irsz"]}'></td></tr>";
echo "<tr><td width=100>Város:</td><td><input class='inputbox' style='width:250px;' type='text' name='varos' value='{$_POST["varos"]}'></td></tr>";
echo "<tr><td width=100>Utca, házszám:</td><td><input class='inputbox' style='width:250px;' type='text' name='utca' value='{$_POST["utca"]}'></td></tr>";
//echo "<tr><td width=100>Munkáltató:</td><td><input class='inputbox' style='width:250px;' type='text' name='munkaltato' value='{$_POST["munkaltato"]}'></td></tr>";
echo "<tr><td width=100>Munkakör: *</td><td><input class='inputbox' style='width:250px;' type='text' name='munkakor' value='{$_POST["munkakor"]}'></td></tr>";

if (!isset($beutalodata)) {
	echo "<tr><td width=100>Megjegyzés:</td><td><div id='fogleuwarn' style='display:none;margin-top:5px;color:#f00;font-weight:bold;'>Kérjük adja meg a megjegyzés rovatban a céget, ahonnan érkezik</div><textarea class='inputbox' style='height:100px;width:400px;' name='megj'>{$_POST["megj"]}</textarea></td></tr>";
}

if (!isset($_SESSION["captcha"])) $_SESSION["captcha"]=rand(110,988);
if (!isset($_SESSION["user"])) {
	echo "<tr><td width=100 colspan='2'><div style='margin-top:10px;'>Kérem, adja meg a következő számot számjegyekkel: ".numtostring($_SESSION["captcha"]).":<br><input class='inputbox' style='width:60px;' type='text' name='captcha' value='{$_POST["captcha"]}'></div></td></tr>";
}


echo "</table>";


echo "<br><br><input type='submit' name='idopontfoglalas' value='Időpont foglalása'> ";
//echo "<input type='submit' name='scancel' value='Vissza'> ";



echo "</form>";

echo "</div>";




?>