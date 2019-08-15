<?php


unset($_SESSION["beutaloid"]);



echo displayFejlec("Dokumentumok");

if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}



$res=sql_query("SELECT d.* FROM dokumentumok d WHERE userid='{$_SESSION["user"]["id"]}' and userid<>0 order by datum desc",array($_SESSION["user"]["id"]));


echo "<div>Itt találja a rendszerbe feltöltött dokumentumait.<br/>Kattintson a dokumentumra a letöltéshez, vagy megtekintéshez.</div>";

echo "<div style='display:inline-block'>";
echo "<table style='font-size:12px;'>";

while ($row=sql_fetch_array($res)) {
	echo "<div class='beutalobox' style='cursor:pointer;' onclick='window.location.href=\"downloaddoc.php?f={$row["id"]}&k={$row["kod"]}&v=1\";'>";
	echo "<div style='font-size:14px;font-weight:bold;'>{$row["megnev"]}</div>";
	echo "<div style='margin-top:0px;'>Feltöltve: ".substr($row["datum"],0,16)."</div>";
	echo "<div style='margin-top:5px;'><img height='50' src='images/icon_{$row["tipus"]}.png' alt='' /></div>";
	echo "<div style='margin-top:0px;'>{$row["filename"]}</div>";
	echo "</div>";
}

echo "</table>";
echo "</div>";




?>