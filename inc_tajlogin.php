<?php

if (!isset($_POST["email"])) {
	$_POST["taj"]=$_POST["email"]=$_POST["jelszo"]=$_POST["captcha"]="";
}


echo displayFejlec("Bejelentkezés");

if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}


echo "<div id='tajlogin'>";
echo "<form name='iform' method='post' enctype='multipart/form-data'>";
echo "<table style='font-size:12px;'>";

if (!isset($_SESSION["captcha"])) $_SESSION["captcha"]=rand(110,988);

echo "<tr><td width='100'>Az Ön TAJ száma:</td><td><input class='inputbox' style='width:200px;' type='text' name='taj' id='taj' value='{$_POST["taj"]}'></td></tr>";
echo "<tr><td width='100' colspan='2'><div style='margin-top:10px;'>Kérem, adja meg a következő számot számjegyekkel: <b>".numtostring($_SESSION["captcha"])."</b>:<br><input class='inputbox' style='width:60px;' type='text' name='captcha' id='captcha' value='{$_POST["captcha"]}'></div></td></tr>";

echo "<tr id='kodmezo' style='display:none;'><td width='120'>SMS-ben kapott kód:</td><td><input class='inputbox' style='width:200px;' type='text' autocomplete='off' name='jelszo' id='jelszo' value='{$_POST["jelszo"]}'></td></tr>";


echo "</table>";

echo "<div style='margin-top:10px;'>";
echo "<div id='kodkerogomb'><input id='kodbutton' onclick=\"requestSMSkod($('#taj').val(),$('#captcha').val());\" type='button' value='SMS kód kérése'></div>";
echo "<div id='logingomb' style='display:none'><input onclick=\"loginTryWithTAJ($('#taj').val(),$('#jelszo').val());\" type='button' id='logintrybutton' value='Bejelentkezés'></div>";
echo "</div>";


echo "</form>";


echo "<div style='margin-top:20px;'>";
echo "Ha inkább normál módon szeretne bejelentkezni, kattintson ide:<br/><a href='index.php?page=login'>Normál bejelentkezés</a>";
echo "</div>";

echo "</div>";




?>