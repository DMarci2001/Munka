<?php


unset($_SESSION["beutaloid"]);


echo displayFejlec($webText["beutalok"]);

if (isset($formerror) && $formerror!="") {
	echo "<div style='margin:0px 0px 10px 3px;background:#f00;color:#fff;border-radius:5px;padding:10px;'>{$formerror}</div>";
}



$res=sql_query("SELECT t.`megnev` AS tipusnev,t.megnev_de as tipusnev_de,t.megnev_en as tipusnev_en,h.cim AS helyszinnev,b.* FROM beutalok b
LEFT JOIN szurestipusok t ON t.`id`=b.`szurestipusid`
LEFT JOIN helyszinek h ON h.`id`=b.`helyszinid`
WHERE userid='{$_SESSION["user"]["id"]}' order by b.datum desc");

echo "<div>{$webText["beuitttalalja"]}</div>";


echo "<div style='display:inline-block'>";
//echo "<table style='font-size:12px;'>";

while ($row=sql_fetch_array($res)) {
	if ($_COOKIE["lang"]!="hu" && trim($row["tipusnev_{$_COOKIE["lang"]}"])!="") $row["tipusnev"]=$row["tipusnev_{$_COOKIE["lang"]}"];
	
	echo "<div class='beutalobox'>";
	echo "<div style='font-size:24px;'>{$row["tipusnev"]} {$webText["beutalo"]}</div>";
	echo "<div style='font-size:14px;'>{$row["helyszinnev"]}</div>";
	if ($row["naploszam"]!="") echo "<div style='font-size:14px;'>{$webText["naploszam"]}: {$row["naploszam"]}</div>";
	echo "<div style='margin-top:0px;'>{$webText["kiadva"]}: ".substr($row["datum"],0,16)."</div>";
	if ($row["foglalasid"]==0) {
		echo "<div style='margin-top:10px;margin-bottom:5px;'><a href='index.php?setbeutalo={$row["id"]}' class='newbutton blueversion'>{$webText["idopontfoglalasa"]}</a>";
		if ($row["selfcreated"]==1) echo "&nbsp;&nbsp;<a onclick='return confirm(\"{$webText["biztostorlibeutalo"]}\");' href='index.php?delbeutalo={$row["id"]}' class='newbutton redversion'>{$webText["beutorlese"]}</a>";
		echo "</div>";
	} else {
		if ($rowf=sql_fetch_array(sql_query("select * from foglalasok where id='{$row["foglalasid"]}'"))) {
			echo "<div style='margin-top:10px;'><b>Időpont foglalva: ".substr($rowf["datum"],0,16)."</b>";
			if (strtotime("now")<strtotime($rowf["datum"])) echo " <a onclick='return confirm(\"{$webText["idopontdelconfirm"]}\");' href='index.php?page={$_GET["page"]}&deltime&id={$rowf["id"]}&rk={$rowf["rkod"]}' class='newbutton redversion' style='padding:2px 5px;font-size:12px;'>{$webText["idoponttorlese"]}</a>";
			echo "</div>";
			if (true) echo "<div></div>";
		}
	}
	echo "</div>";
}

//echo "</table>";
echo "</div>";



/*

$resb=sql_query("SELECT * FROM szurestipusok");
while ($rowb=sql_fetch_array($resb)) {
	$szurestipusok[$rowb["id"]]=$rowb["megnev"];
}

$resb=sql_query("SELECT b.*,h.cim FROM orvos_beosztas b 
left join helyszinek h on h.id=b.helyszinid
WHERE b.cegid='{$_SESSION["helyszindata"]["id"]}'");

while ($rowb=sql_fetch_array($resb)) {
	$tipusok=explode("|",$rowb["tipusok"]);
	for ($i=0;$i<count($tipusok);$i++) {
		$t=$tipusok[$i];
		if (trim($t)!="") {
			$beutalohelyek["{$rowb["helyszinid"]}-{$t}"]="{$szurestipusok[$t]} ({$rowb["cim"]})";
		}
	}
}
asort($beutalohelyek);



echo "<div style='margin-top:20px;'>";
echo "<div>Ha van beutalója, kérjük adja meg az adatait itt:</div>";

echo "<div id='beutalokerbutton'>";
echo "<div style='margin-top:10px;'><a onclick=\"$('#beutalokerbutton').slideUp();$('#beutalokerform').slideDown();return false;\" href='#' class='newbutton'>Beutaló megadása</a></div>";
echo "</div>";

echo "<div id='beutalokerform' style='display:none;'>";
echo "<form name='iform' method='post' enctype='multipart/form-data'>";
echo "<input name='adduserbeutalo' type='hidden' value='1'/>";
echo "<div style='margin-top:10px;'>Hova szól a beutalója:</div>";
echo "<div style='margin-top:0px;'><select style='width:400px;' name='beutalotarget' id='beutalotarget'>";
echo "<option value='0'>Válasszon hova szól a beutalója!</option>";
foreach ($beutalohelyek as $key => $value) {
	echo "<option value='{$key}'>{$value}</option>";
}	
echo "</select></div>";

echo "<div style='margin-top:10px;'>Napló szám:*</div><div><input class='inputbox' autocomplete='off' style='width:250px;' type='text' name='naploszam' id='naploszam' value='' /></div><div style='font-size:11px;color:#666;'>* naplószám nélkül is megadhatja a beutalóját, ekkor viszont csak a fizetős rendelésre lesz jogosult.</div>";
echo "<div style='margin-top:10px;'>Megjegyzés:</div><div><textarea name='beutalomegj' placeholder='Ide írja a panaszát, vagy megjegyzését' id='beutalomegj' style='width:400px;height:100px;'></textarea></div>";
echo "<div style='margin-top:10px;'><input onclick='addUserBeutalo();' type='button' name='addbeutalo' value='Mentés'> <input onclick=\"$('#beutalokerbutton').slideDown();$('#beutalokerform').slideUp();\" type='button' name='scancel' value='Mégse'></div>";

echo "</form>";
echo "</div>";



echo "</div>";
*/







?>