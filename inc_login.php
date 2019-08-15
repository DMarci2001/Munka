<?php

if (!isset($_POST["email"])) {
	$_POST["taj"]=$_POST["email"]=$_POST["jelszo"]="";
}



echo displayFejlec($webText["bejelentkezes"]);

if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}

if (isset($_GET["passwordsent"])) {
	echo "<div style='margin:0px 0px 10px 3px;background:#8a8;color:#fff;border-radius:5px;padding:10px;'>Az új jelszavát a megadott e-mail címre elküldtük.</div>";
}

echo "<div id='normallogin'>";
echo "<form name='iform' method='post' enctype='multipart/form-data'>";

echo "<table style='font-size:12px;'>";
echo "<tr><td width='100'>{$webText["email"]}:</td><td><input class='inputbox' style='width:200px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
echo "<tr><td width='100'>{$webText["jelszo"]}:</td><td><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo' value='{$_POST["jelszo"]}'></td></tr>";
echo "</table>";

echo "<br/><input type='submit' name='logintry' value='{$webText["bejelentkezes"]}'> ";
echo "</form>";

echo "<div style='margin-top:20px;'>";
echo "{$webText["hanememlekszik"]}<br/><a href='index.php?page=passwordsend'>{$webText["ujjelszokerese"]}</a>";
echo "</div>";

echo "<div style='margin-top:20px;'>";
echo "{$webText["amennyibennememail"]}:<br/><a href='index.php?page=tajlogin'>{$webText["bejelentkezestaj"]}</a>";
echo "</div>";

echo "</div>";




echo "<div id='tajlogin' style='display:none;'>";
echo "<form name='iform' method='post' enctype='multipart/form-data'>";
echo "<table style='font-size:12px;'>";

echo "<tr><td width=100>Az Ön TAJ száma:</td><td><input class='inputbox' style='width:200px;' type='text' name='taj' value='{$_POST["taj"]}'></td></tr>";
//echo "<tr><td width=100>Jelszó:</td><td><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo' value='{$_POST["jelszo"]}'></td></tr>";


echo "</table>";


echo "<br/><input type='submit' name='logintry' value='Bejelentkezés'> ";



echo "</form>";


echo "<div style='margin-top:20px;'>";
echo "Ha inkább normál módon szeretne bejelentkezni, kattintson ide:<br/><a href='#' onclick=\"$('#normallogin').show();$('#tajlogin').hide();\"'>Normál bejelentkezés</a>";
echo "</div>";

echo "</div>";





?>