<?php

if (!isset($_POST["email"])) {
	$_POST["email"]=$_POST["nev"]=$_POST["telefon"]=$_POST["szuldatum"]=$_POST["taj"]=$_POST["irsz"]=$_POST["varos"]=$_POST["utca"]=$_POST["munkaltato"]=$_POST["munkakor"]=$_POST["torzsszam"]=$_POST["jelszo"]=$_POST["jelszo2"]=$_POST["captcha"]="";
}
if (!isset($_POST["neme"])) $_POST["neme"]="";


echo displayFejlec($webText["regisztracio"]);

if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}

echo "<form name='iform' method='post' enctype='multipart/form-data'>";
echo "<table style='font-size:12px;'>";

echo "<tr><td width='100'>{$webText["email"]}: *</td><td><input class='inputbox' autocomplete='off' style='width:250px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
echo "<tr><td>{$webText["jelszo"]}: *</td><td><input style='display:none;' type='text' autocomplete='off' name='dummyname' value=''><input style='display:none;' type='password' autocomplete='off' name='dummypass' value=''> <input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo' value='{$_POST["jelszo"]}'></td></tr>";
echo "<tr><td>{$webText["jelszoujra"]}: *</td><td><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo2' value='{$_POST["jelszo2"]}'></td></tr>";
echo "<tr><td colspan='2'><hr></td></tr>";

echo "<tr><td>{$webText["tajszam"]}: *</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' onchange='clearIdopontValaszto();'  value='{$_POST["taj"]}'></td></tr>";
echo "<tr><td>{$webText["nev"]}: *</td><td><input class='inputbox' style='width:270px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
echo "<tr><td>{$webText["mobil"]}: *</td><td><input class='inputbox' style='width:270px;' type='text' name='telefon' value='{$_POST["telefon"]}' placeholder='{$webText["mobilformat"]}' ></td></tr>";
echo "<tr><td></td><td style='color:#888;'>{$webText["mobiltip"]}</td></tr>";
echo "<tr><td>{$webText["szuletesidatum"]}: *</td><td>".datumSelector($_POST["szuldatum"],"szuldatum")."</td></tr>";
echo "<tr><td>{$webText["neme"]}: *</td><td><input type='radio' name='neme' value='1' ".($_POST["neme"]==1?"checked":"")."/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_POST["neme"]==2?"checked":"")."/> {$webText["no"]}</td></tr>";
echo "<tr><td>{$webText["irsz"]}:</td><td><input class='inputbox' style='width:60px;' maxlength='4' type='text' name='irsz' value='{$_POST["irsz"]}'></td></tr>";
echo "<tr><td>{$webText["varos"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='varos' value='{$_POST["varos"]}'></td></tr>";
echo "<tr><td>{$webText["utca"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='utca' value='{$_POST["utca"]}'></td></tr>";

if (false) echo "<tr><td>{$webText["munkakor"]}: *</td><td><input class='inputbox' style='width:250px;' type='text' name='munkakor' value='{$_POST["munkakor"]}'></td></tr>";
//if ($_SESSION["helyszindata"]["id"]==15) echo "<tr><td>Törzsszám: </td><td><input class='inputbox' style='width:120px;' type='text' id='torzsszam' name='torzsszam' value='{$_POST["torzsszam"]}'></td></tr>";

echo "<tr><td></td><td><div style='margin-top:5px;' class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";

echo "<tr><td width='100'><td><div style='margin-top:10px;'><input type='checkbox' name='aszf' value='1' ".(isset($_POST["aszf"])?"checked":"")."/> {$webText["aszfelf"]}</div>";
echo "<div id='adatvedelem' style='display:none;'>";

echo "<div style='margin-top:10px;'>".getASZF()."</div>";
echo "</div>";
echo "</td></tr>";

echo "<tr><td></td><td><br/><input style='margin-top:5px;' type='submit' name='regisztracio' value='{$webText["regisztracio"]}'></td></tr>";

echo "</table>";


echo "</form>";




?>