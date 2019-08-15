<?php


unset($_SESSION["beutaloid"]);


echo displayFejlec("{$webText["foglalasok"]}");

if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}

$res=sql_query("SELECT c.megnev as cegnev,t.`megnev` AS tipusnev,t.megnev_de as tipusnev_de,t.megnev_en as tipusnev_en,h.cim AS helyszinnev,f.* FROM foglalasok f
LEFT JOIN szurestipusok t ON t.`id`=f.`szurestipusid`
LEFT JOIN helyszinek h ON h.`id`=f.`helyszinid`
left join cegek c on c.id=f.cegid
WHERE f.paciensid='{$_SESSION["user"]["id"]}' order by f.datum desc");

echo "<div>{$webText["foglalaslisttext"]}</div>";


echo "<table style='font-size:16px;margin-top:20px;'>";

while ($row=sql_fetch_array($res)) {
	
	if ($_COOKIE["lang"]!="hu" && trim($row["tipusnev_{$_COOKIE["lang"]}"])!="") $row["tipusnev"]=$row["tipusnev_{$_COOKIE["lang"]}"];

	echo "<tr>";
	
	echo "<td style='font-size:18px;'>".substr($row["datum"],0,16)."&nbsp;&nbsp;</td>";
	echo "<td style='font-size:18px;'>{$row["tipusnev"]}</td>";
	echo "<td style='font-size:14px;'>{$row["helyszinnev"]}</td>";
	echo "<td style='font-size:14px;'>{$row["cegnev"]}&nbsp;&nbsp</td>";
	if (strtotime("now + 6 hour")<strtotime($row["datum"])) {
		echo "<td style='font-size:12px;'>[ <a onclick='return confirm(\"{$webText["idopontdelconfirm"]}\");' href='index.php?dodeleteidopont&id={$row["id"]}&rk={$row["rkod"]}'>{$webText["idoponttorlese"]}</a> ]</td>";
	}
	
	echo "</tr>";
}

echo "</table>";
echo "</div>";








?>