<?php

if (!isset($_POST["smskod"])) {
	$_POST["smskod"]="";
}



if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}

echo "<form name='iform' method='post' enctype='multipart/form-data'>";
echo "<table style='font-size:12px;'>";

echo "<tr><td width=150>SMS-ben kapott kód: *</td><td><input class='inputbox' style='width:100px;' type='text' name='smskod' value='{$_POST["smskod"]}'></td></tr>";

echo "</table>";


echo "<br><br><input type='submit' name='validatelogin' value='Regisztráció érvényesítése'> ";
//echo "<input type='submit' name='scancel' value='Vissza'> ";



echo "</form>";





?>