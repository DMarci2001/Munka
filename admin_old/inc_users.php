<?php

if($_SESSION['adminuser']['jog_jogset'] != 1) header("Location:index.php");

$w="u.cegid in (".getCegList($user["cegjog"]).") and u.cegid<>0";
if ($user["jogosultsag"]>=2) $w="true";


$h="";
if (isset($_GET["szerk"])) {
	$row=sql_fetch_array(sql_query("select u.*,c.megnev as cegnev from users u left join cegek c on c.id=u.cegid where u.id='".addslashes($_GET["szerk"])."' and {$w}"));
	$_POST=$row;

	echo "<div style='background-color:#fff;padding:0px;'>";
	echo "<form name='iform' id='iform' method='post' enctype='multipart/form-data'><input type='hidden' name='userform' value='1'/><input type='hidden' name='userid' value='{$_POST["id"]}'/>";
	echo "<table style='font-size:12px;'>";


	echo "<tr><td width='100'>Név:</td><td><input class='inputbox' style='width:400px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
	echo "<tr><td width='100'>Felhasználónév:</td><td><input autocomplete='off' class='inputbox' style='width:200px;' type='text' name='username' value='{$_POST["username"]}'></td></tr>";
	echo "<tr><td width='100'>E-mail:</td><td><input autocomplete='off' class='inputbox' style='width:200px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
	echo "<tr><td width='100'>Telefon:</td><td><input autocomplete='off' class='inputbox' style='width:100px;' type='text' name='tel' value='{$_POST["tel"]}'></td></tr>";
	echo "<tr><td width='100'>Új jelszó:</td><td><input autocomplete='off' class='inputbox' style='width:200px;' type='text' name='password' value=''></td></tr>";
	//echo "<tr><td width='100'>Cím:</td><td><input class='inputbox' style='width:400px;' type='text' name='cim' value='{$_POST["cim"]}'></td></tr>";

	/*
	if ($user["jogosultsag"]>=2) {
		echo "<tr><td width='100'>Cég:</td><td>";
		echo "<select name='cegid'>";
		echo "<option value='0'>Nem kapcsolódik céghez</option>";	
		$resh=sql_query("select * from cegek order by megnev");
		while ($rowh=sql_fetch_array($resh)) {
			echo "<option value='{$rowh["id"]}'".($row["cegid"]==$rowh["id"]?" selected":"").">{$rowh["megnev"]}</option>";	
		}
		echo "</select> ";
		echo "</td></tr>";
	} else {
		echo "<tr><td width='100'>Cég:</td><td>{$row["cegnev"]}<input type='hidden' name='cegid' value='{$row["cegid"]}' /></td></tr>";
	}
	*/

	if ($user["jogosultsag"]>=2 && $user["jog_jogset"]==1) {
		echo "<tr><td width='100'>Jogosultság szint:</td><td>";
		echo "<select name='jogosultsag' onchange=\"if (this.value!=1) { $('#cegjogok').hide(); } else { $('#cegjogok').show(); }\">";
		for ($i=0;$i<count($adminszintek);$i++) {
			if ($i>$user["jogosultsag"]) break;
			echo "<option value='{$i}'".($row["jogosultsag"]==$i?" selected":"").">{$adminszintek[$i]}</option>";	
		}
		echo "</select> ";
		echo "</td></tr>";
			
		echo "<tr><td></td><td>";
		echo "<div id='cegjogok' style='".($row["jogosultsag"]<=1||$row['orvosid']!=""?"":"display:none;")."'>";
		
		$resh=sql_query("select * from cegek order by megnev");
		while ($rowh=sql_fetch_array($resh)) {
			if ($user["jogosultsag"]>=2) {
				echo "<span style='white-space:nowrap;".(substr_count($_POST["cegjog"],"|{$rowh["id"]}|")?"font-weight:bold;color:#00f;":"")."'><input type='checkbox' name='cegjog{$rowh["id"]}' ".(substr_count($_POST["cegjog"],"|{$rowh["id"]}|")?"checked":"")." value='1' />&nbsp;{$rowh["megnev"]}</span> ";	
			} else {
				if (substr_count($_POST["cegjog"],"|{$rowh["id"]}|")) echo "<span style='padding:2px 5px;white-space:nowrap;background:#888;color:#fff;'>{$rowh["megnev"]}</span> ";			
			}
		}
		
		
		echo "</div>";
		echo "</td></tr>";

		echo "<tr><td>Csak lokális elérés ip címek:</td><td><input class='inputbox' style='width:300px;' type='text' name='localeip' value='{$_POST["localeip"]}'> <input type='checkbox' name='localeaccess' ".($_POST["localeaccess"]==1?"checked":"")." value='1' />&nbsp;csak helyi elérés engedélyezése</td></tr>";
		echo "<tr><td></td><td><input type='checkbox' name='auth2fac' ".($_POST["auth2fac"]==1?"checked":"")." value='1' />&nbsp;2 faktoros authentikáció</td></tr>";
		echo "<tr><td></td><td><input type='checkbox' name='status' ".($_POST["status"]==1?"checked":"")." value='1' />&nbsp;aktiválás/deaktiválás</td></tr>";
		if ($row["jogosultsag"]>=2) {
			echo "<tr><td></td><td><input type='checkbox' name='jog_jogset' ".($_POST["jog_jogset"]==1?"checked":"")." value='1' />&nbsp;jogkörök kiosztása</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_beallitasok' ".($_POST["jog_beallitasok"]==1?"checked":"")." value='1' />&nbsp;Beállítások kezelése</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_statisztika' ".($_POST["jog_statisztika"]==1?"checked":"")." value='1' />&nbsp;Statisztika látása</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_cegset' ".($_POST["jog_cegset"]==1?"checked":"")." value='1' />&nbsp;cégek kezelése</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_helyszinset' ".($_POST["jog_helyszinset"]==1?"checked":"")." value='1' />&nbsp;helyszínek kezelése</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_orvosset' ".($_POST["jog_orvosset"]==1?"checked":"")." value='1' />&nbsp;orvosok kezelése</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_beosztasset' ".($_POST["jog_beosztasset"]==1?"checked":"")." value='1' />&nbsp;orvos beosztások kezelése</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_szabi' ".($_POST["jog_szabi"]==1?"checked":"")." value='1' />&nbsp;szabadságok beállítása</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_szurestipusset' ".($_POST["jog_szurestipusset"]==1?"checked":"")." value='1' />&nbsp;szűréstipusok kezelése</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_nofoglimitset' ".($_POST["jog_nofoglimitset"]==1?"checked":"")." value='1' />&nbsp; Korlátan időpontfoglalás</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_zarolista' ".($_POST["jog_zarolista"]==1?"checked":"")." value='1' />&nbsp;Zárólista látása</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_zaroszerk' ".($_POST["jog_zaroszerk"]==1?"checked":"")." value='1' />&nbsp;Záró leletek szerkesztése</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_vizsg_stat' ".($_POST["jog_vizsg_stat"]==1?"checked":"")." value='1' />&nbsp;Vizsgálati statisztika lekérdezése</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_leletlatas' ".($_POST["jog_leletlatas"]==1?"checked":"")." value='1' />&nbsp;Leletek látása</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_leletszerk' ".($_POST["jog_leletszerk"]==1?"checked":"")." value='1' />&nbsp;Leletek szerkesztése</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_gdprhferes' ".($_POST["jog_gdprhferes"]==1?"checked":"")." value='1' />&nbsp;GDPR hozzáférés</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_kuponlista' ".($_POST["jog_kuponlista"]==1?"checked":"")." value='1' />&nbsp;Kuponkód lista</td></tr>";
			echo "<tr><td></td><td><input type='checkbox' name='jog_kuponkeszites' ".($_POST["jog_kuponkeszites"]==1?"checked":"")." value='1' />&nbsp;Kuponkód hozzáadás/szerkesztés</td></tr>";
		}
	}

	echo "</table>";

	echo "<div id='errorlistdiv' style='padding:10px;background:#f00;color:#fff;font-weight:bold;display:none;'></div>";

	echo "<br><input type='submit' name='usermentes' value='Mentés'> ";
	echo "<input type='submit' name='scancel' value='Vissza'> ";



	echo "</form>";
	echo "</div>";
}


if (!isset($_GET["szerk"])) {
	$resh=sql_query("select * from cegek order by megnev");
	while ($rowh=sql_fetch_array($resh)) {
		$cegek[$rowh["id"]]=$rowh["megnev"];
	}
	
	$szin="#dddddd";
	
	$res=sql_query("SELECT u.* FROM users u where true ORDER BY !instr(u.nev,'új felh'),u.nev");

	echo "<table cellpadding='0' cellspacing='0' border='0'>";
	while ($row=sql_fetch_array($res)) {
		$tc="tcella";
		if (!isset($first)) {
			echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
			$first=1;
		}
		if (trim($row["nev"])=="") $row["nev"]="nincs neve";
		echo "<tr>";
		echo "<td nowrap valign='top'><div class='{$tc}'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["nev"]}</a> ({$row["username"]})</div></td>";
		//echo "<td nowrap valign='top'><div class='{$tc}' style='min-width:300px;'>{$row["cim"]}&nbsp;&nbsp;</div></td>";

		
		echo "<td valign='top'><div class='{$tc}'>";
		if (isset($cegList)) unset($cegList);
		if ($row["jogosultsag"]<2) {
			$j=explode("|",$row["cegjog"]);
			for ($i=0;$i<count($j);$i++) {
				if (isset($cegek[$j[$i]])) {
					$cegList[]=$cegek[$j[$i]];
					//echo "<span style='padding:2px 5px;white-space:nowrap;background:#888;color:#fff;'>".$cegek[$j[$i]]."</span> ";
				}
			}
		}
		echo "</div></td>";
		
		
		echo "<td nowrap valign='top'><div class='{$tc}'>{$adminszintek[$row["jogosultsag"]]}".(isset($cegList)?" (<span title='".(implode(", ", $cegList))."' style='border-bottom:1px dashed #888;'>".count($cegList)." cég</span>)":"")."</div></td>";
		echo "<td nowrap valign='top'><div class='{$tc}'>{$row["tel"]}";
		echo ($row["auth2fac"]==1?" <span title='kétfaktoros authentikáció' style='border:1px solid #f00;padding:1px 3px;color:#f00;'>2fac</span>":"").($row["localeaccess"]==1?" <span title='csak lokális belépés endedélyezett' style='border:1px solid #f00;padding:1px 3px;color:#f00;'>local</span>":"")."</div></td>";
		echo "<td nowrap valign='top'><div class='{$tc}'>{$row["email"]}</div></td>";
		echo "<td nowrap valign='top'><div class='{$tc}'>".($row["lastlogin"]=="0000-00-00 00:00:00"?"":"Utolsó login: ".substr($row["lastlogin"],0,16))."</div></td>";
		echo "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='return confirm(\"Biztosan törlöd ezt a felhasználót?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
		echo "</tr>";
		echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
	}
	echo "</table>";

}





?>