<?php

//if (!isset($_POST["email"])) {
//	$_POST["email"]=$_POST["nev"]=$_POST["telefon"]=$_POST["szuldatum"]=$_POST["taj"]=$_POST["irsz"]=$_POST["varos"]=$_POST["utca"]=$_POST["munkaltato"]=$_POST["munkakor"]=$_POST["jelszo"]=$_POST["jelszo2"]=$_POST["captcha"]="";
//}



echo displayFejlec($webText["adatmodositas"]);

if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}

echo "<form name='iform' method='post' enctype='multipart/form-data'>";
echo "<table style='font-size:12px;'>";

echo "<tr><td width=100>{$webText["email"]}:</td><td>{$_SESSION["user"]["email"]}</td></tr>";
echo "<tr><td colspan='2'><hr></td></tr>";

echo "<tr><td width=100>{$webText["tajszam"]}:</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' onchange='clearIdopontValaszto();'  value='{$_SESSION["user"]["taj"]}'></td></tr>";
echo "<tr><td width=100>{$webText["nev"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='nev' value='{$_SESSION["user"]["nev"]}'></td></tr>";
echo "<tr><td width=100>{$webText["mobil"]}:</td><td><input type='hidden' name='oldtelefon' id='oldtelefon' value='{$_SESSION["user"]["telefon"]}' /><input class='inputbox' style='width:250px;' type='text' name='telefon' id='telefon' value='{$_SESSION["user"]["telefon"]}' placeholder='Formátum pl: 06301234567' ></td></tr>";
echo "<tr><td width=100></td><td style='color:#888;'>{$webText["hamegvaltoztattel"]}</td></tr>";
echo "<tr><td width=100>{$webText["anyjaneve"]}:</td><td><input class='inputbox' style='width:180px;' type='text' name='anyjaneve' value='{$_SESSION["user"]["anyjaneve"]}'></td></tr>";
echo "<tr><td width=100>{$webText["szuletesihely"]}:</td><td><input class='inputbox' style='width:180px;' type='text' name='szulhely' value='{$_SESSION["user"]["szulhely"]}'></td></tr>";
echo "<tr><td width=100>{$webText["szuletesidatum"]}:</td><td>".datumSelector($_SESSION["user"]["szuldatum"],"szuldatum")."</td></tr>";
echo "<tr><td width=100>{$webText["neme"]}:</td><td><input type='radio' name='neme' value='1' ".($_SESSION["user"]["neme"]==1?"checked":"")."/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_SESSION["user"]["neme"]==2?"checked":"")."/> {$webText["no"]}</td></tr>";
echo "<tr><td width=100>{$webText["irsz"]}:</td><td><input class='inputbox' style='width:60px;' maxlength='4' type='text' name='irsz' value='{$_SESSION["user"]["irsz"]}'></td></tr>";
echo "<tr><td width=100>{$webText["varos"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='varos' value='{$_SESSION["user"]["varos"]}'></td></tr>";
echo "<tr><td width=100>{$webText["utca"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='utca' value='{$_SESSION["user"]["utca"]}'></td></tr>";
//echo "<tr><td width=100>Munkáltató:</td><td><input class='inputbox' style='width:250px;' type='text' name='munkaltato' value='{$_SESSION["user"]["munkaltato"]}'></td></tr>";
echo "<tr><td width=100>{$webText["munkakor"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='munkakor' value='{$_SESSION["user"]["munkakor"]}'></td></tr>";
echo "<tr><td width=100>{$webText["torzsszam"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='torzsszam' value='{$_SESSION["user"]["torzsszam"]}'></td></tr>";

echo "<tr><td colspan='2'><hr></td></tr>";

echo "<tr><td width=100>{$webText["jelszo"]}:</td><td><input style='width:200px;display:none;' type='text' autocomplete='off' name='dummyuser' value='' /><input style='width:200px;display:none;' type='password' autocomplete='off' name='dummypass' value='' /><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo' value=''/></td></tr>";
echo "<tr><td width=100>{$webText["jelszoujra"]}:</td><td><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo2' value=''/></td></tr>";
echo "<tr><td width=100></td><td style='color:#888;'>{$webText["toltsdki2jelszo"]}</td></tr>";

echo "</table>";


echo "<br><br><input type='submit' name='adatmodositas' value='{$webText["adatokmodositasa"]}' 
onclick=\"
if ($('#telefon').val()!=$('#oldtelefon').val()) {
	return confirm('{$webText["telmodq"]} '+$('#telefon').val()+'?');
}
\"
/> ";



echo "</form>";




?>