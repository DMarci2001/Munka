<?php

if($_SESSION['adminuser']['jog_cegset'] != 1) header("Location:index.php");

if (!cegModJog()) return;

if (isset($_GET["szerk"])) {
	
    $row=sql_fetch_array(sql_query("select * from cegek where id='".addslashes($_GET["szerk"])."'"));
    $_POST=$row;

    echo "<div style='background-color:#fff;padding:0px;'>";
    echo "<form name='iform' method='post' enctype='multipart/form-data'>";
    echo "<table style='font-size:12px;'>";


    echo "<tr><td width='150'>Név:</td><td><input class='inputbox' style='width:400px;' type='text' name='megnev' value='{$_POST["megnev"]}'></td></tr>";
    echo "<tr><td>Domain:</td><td>http:// <input class='inputbox' style='width:100px;' type='text' name='domain' value='{$_POST["domain"]}'> .hungariamed.hu</td></tr>";
    echo "<tr><td>E-mail:</td><td><input class='inputbox' style='width:300px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
    echo "<tr><td>SMS a pacinenseknek:</td><td><input class='inputbox' style='width:20px;' type='text' name='smshour' value='{$_POST["smshour"]}'> órával előtte</td></tr>";
    echo "<tr><td>Figyelmeztető szöveg:</td><td><input class='inputbox' style='width:600px;' type='text' name='beutaloszoveg' value='{$_POST["beutaloszoveg"]}'></td></tr>";
    echo "<tr><td>Figyelmeztető szöveg (német):</td><td><input class='inputbox' style='width:600px;' type='text' name='beutaloszoveg_de' value='{$_POST["beutaloszoveg_de"]}'></td></tr>";
    echo "<tr><td>Figyelmeztető szöveg (angol):</td><td><input class='inputbox' style='width:600px;' type='text' name='beutaloszoveg_en' value='{$_POST["beutaloszoveg_en"]}'></td></tr>";
    echo "<tr><td>Protokoll:</td><td><textarea class='inputbox' style='width:600px;height:80px;' type='text' name='protokoll'>{$_POST["protokoll"]}</textarea></td></tr>";


    echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='aktiv'".($_POST["aktiv"]==1?" checked":"")."> Aktív</td></tr>";
    echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='onlyreg'".($_POST["onlyreg"]==1?" checked":"")."> Csak regisztrációval lehessen foglalni</td></tr>";
    echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='visszaigazolas'".($_POST["visszaigazolas"]==1?" checked":"")."> Vissza kell igazolni a foglalást</td></tr>";
    echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='onlybeutalo'".($_POST["onlybeutalo"]==1?" checked":"")."> Csak beutalóval lehessen foglalni</td></tr>";
    echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='nocim'".($_POST["nocim"]==1?" checked":"")."> A rendelési cím ne, csak a cím megnevezése látszódjon a pacienseknek</td></tr>";
    echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='foglalasemail'".($_POST["foglalasemail"]==1?" checked":"")."> Menjen a foglalásokról e-mail értesítés</td></tr>";
    echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='tudoszuroopcio'".($_POST["tudoszuroopcio"]==1?" checked":"")."> Tüdőszűrő opció az üzemorvosi vizsgálatnál</td></tr>";
    echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='alkertsend'".($_POST["alkertsend"]==1?" checked":"")."> Alkalmassági lejártáról értesítés a pácienseknek</td></tr>";
    echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='alksend'".($_POST["alksend"]==1?" checked":"")."> Alkalmassági lista küldése</td></tr>";

	echo "<tr><td>Rendszeresség: </td><td><select name='alksendint'>";
	echo "	<option ".($_POST["alksendint"]=="napi"?" selected":"")." value='napi'>Napi</option>";
	echo "	<option ".($_POST["alksendint"]=="heti"?" selected":"")." value='heti'>Heti</option>";
	echo "	<option ".($_POST["alksendint"]=="havi"?" selected":"")." value='havi'>Havi</option>";
	echo "</select></td></tr>";
	echo "<tr><td>Fogadó email(ek): </td><td ><textarea class='inputbox' name='sendmail' style='width:600px;height:80px;'>".(isset($_POST["sendmail"])?$_POST["sendmail"]:"")."</textarea>";
	echo "</td></tr>";
	
	echo "<tr><td colspan='2'><div class='tdsepdiv'>Cég egységek</div></td></tr>";
	echo "<tr><td colspan='2' valign='top'><input type='submit' name='addcegvar' value='+ Egység hozzáadása'></td></tr>";

	$resb=sql_query("select * from cegvars where cegid='".addslashes($_GET["szerk"])."' order by varos,megnev");

	$sor=1;
	while ($rowb=sql_fetch_array($resb)) {
		echo "<tr><td colspan='2'>";
		echo "<input type='hidden' name='cegvarid{$sor}' value='{$rowb["id"]}'/>";
		echo "<div><input type='text' name='cegvarvaros{$sor}' style='width:195px;' placeholder='város...' value='{$rowb["varos"]}'/> <input type='text' name='cegvarmegnev{$sor}' style='width:395px;' placeholder='egység megnevezése...' value='{$rowb["megnev"]}'/>";
		echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delcegvar={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt az egységet?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
		echo "</div>";
		echo "</td></tr>";
		$sor++;
	}

	echo "<tr><td colspan='2'><div class='tdsepdiv'>Beosztások</div></td></tr>";
	echo "<tr><td colspan=2 valign=top><input type='submit' name='addcegbeosztas' value='+ Beosztás hozzáadása'></td></tr>";

	$resb=sql_query("select * from cegbeosztasok where cegid='".addslashes($_GET["szerk"])."' order by megnev");

	$sor=1;
	while ($rowb=sql_fetch_array($resb)) {
		echo "<tr><td colspan='2'>";
		echo "<input type='hidden' name='cegbeosztasid{$sor}' value='{$rowb["id"]}'/>";
		echo "<div><input type='text' name='cegbeosztasmegnev{$sor}' style='width:595px;' placeholder='beosztás megnevezése...' value='{$rowb["megnev"]}'/>";
		echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delcegbeosztas={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt a beosztást?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
		echo "</div>";
		echo "</td></tr>";
		$sor++;
	}




	echo "<tr><td colspan='2'><div class='tdsepdiv'>Visszaigazoló szövegek</div></td></tr>";

	$w=$wc="";
	if ($user["jogosultsag"]<2) {
		$w="and b.cegid='{$user["cegid"]}'";
		$wc="and id='{$user["cegid"]}'";
	}

	$resb=sql_query("select * from visszaigazolok where cegid='".addslashes($_GET["szerk"])."' order by id");

	$sor=1;
	while ($rowb=sql_fetch_array($resb)) {
		echo "<tr><td colspan='2'>";

		echo "<input type='hidden' name='visszid{$sor}' value='{$rowb["id"]}'/>";

		echo "<select name='helyszinid{$sor}' style='width:300px;'>";

		$resh=sql_query("SELECT * FROM helyszinek WHERE INSTR(ceglink,'|{$rowb["cegid"]}|') order by cim");

		echo "<option value='0'>Minden helyszín</option>";	
		while ($rowh=sql_fetch_array($resh)) {
			echo "<option value='{$rowh["id"]}'".($rowb["helyszinid"]==$rowh["id"]?" selected":"").">{$rowh["cim"]}</option>";	
		}
		echo "</select> ";

		echo "<select name='orvosid{$sor}' style='width:300px;'>";

		$resh=sql_query("SELECT o.* FROM orvosok o LEFT JOIN orvos_beosztas b ON b.`orvosid`=o.`id` WHERE b.`cegid`='4' GROUP BY o.id ORDER BY nev");

		if (sql_num_rows($resh)>1) echo "<option value='0'>Minden orvos</option>";	
		while ($rowh=sql_fetch_array($resh)) {
			echo "<option value='{$rowh["id"]}'".($rowb["orvosid"]==$rowh["id"]?" selected":"").">{$rowh["nev"]}</option>";	
		}
		echo "</select> ";
		
		echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delvisszaigazolo={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt a visszaigazoló szöveget?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
		echo "</div>";
		echo "<div><textarea name='szoveg{$sor}' style='width:595px;height:80px;' placeholder='szöveg a visszaigazoló levélbe...'>{$rowb["szoveg"]}</textarea></div>";
		echo "<div><input type='text' name='mapurl{$sor}' style='width:595px;' placeholder='google maps link...' value='{$rowb["mapurl"]}'/>";
		if (trim($rowb["mapurl"])!="") echo "<a target='_blank' href='{$rowb["mapurl"]}'><img style='height:20px;padding:2px 0px 0px 3px;' align='right' src='images/mapicon.png' title='Térkép tesztelése'/></a>";
		echo "</div>";
				
		echo "</td></tr>";
		$sor++;
	}

    echo "<tr><td colspan='2'><div class='tdsepdiv'>Nincs foglalás szöveg</div></td></tr>";
    echo "<tr><td colspan='2' valign='top'>*ha ezek a mezők ki vannak töltve, akkor a foglalás nem lesz lehetséges ehhez a céghez, helyette ez a szöveg fog megjelenni (HTML tartalom használható)</td></tr>";

    echo "<tr><td colspan='2'><textarea placeholder='HU szöveg' class='inputbox' style='width:800px;height:80px;' type='text' name='nofoglalas_hu'>{$_POST["nofoglalas_hu"]}</textarea></td></tr>";
    echo "<tr><td colspan='2'><textarea placeholder='EN szöveg' class='inputbox' style='width:800px;height:80px;' type='text' name='nofoglalas_en'>{$_POST["nofoglalas_en"]}</textarea></td></tr>";
    echo "<tr><td colspan='2'><textarea placeholder='DE szöveg' class='inputbox' style='width:800px;height:80px;' type='text' name='nofoglalas_de'>{$_POST["nofoglalas_de"]}</textarea></td></tr>";

    echo "</table>";


	echo "<br><input type='submit' name='cegmentes' value='Mentés'> ";
	echo "<input type='submit' name='scancel' value='Vissza'> ";


	echo "</form>";
	
	
	$res=sql_query("SELECT b.*,o.`nev`,GROUP_CONCAT(DISTINCT b.`tipusok` SEPARATOR '') AS tipusokok FROM orvos_beosztas b
	LEFT JOIN orvosok o ON o.id=b.`orvosid`
	LEFT JOIN cegek c ON c.id=b.`cegid`
	WHERE b.cegid='{$row["id"]}' GROUP BY orvosid ORDER BY o.nev");

	if (sql_num_rows($res)>0) {
		echo "<div class='tdsepdiv' style='margin-top:20px;'>{$_POST["megnev"]} orvosai</div>";

		$rest=sql_query("select * from szurestipusok");
		while ($rowt=sql_fetch_array($rest)) {
			$tipusnevek[$rowt["id"]]=$rowt["megnev"];
		}

		echo "<table cellpadding='0' cellspacing='0' border='0'>";
		while ($row=sql_fetch_array($res)) {
			if (trim($row["nev"])=="") continue;
			
			$ta=explode("|",$row["tipusokok"]);
			unset($tipusok);
			for ($i=0;$i<count($ta);$i++) {
				if (isset($tipusnevek[$ta[$i]])) $tipusok[]=$tipusnevek[$ta[$i]];
			}
			
			$tc="tcella";
			
			@$tipusok=array_unique($tipusok);
			
			echo "<tr>";
			echo "<td nowrap valign='top'><div class='{$tc}'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page=orvosok&szerk={$row["orvosid"]}'>{$row["nev"]}</a></div></td>";
			//echo "<td valign='top'><div class='{$tc}'>{$row["tipusokok"]}</div></td>";
			echo "<td valign='top'><div class='{$tc}'>".@implode(", ",$tipusok)."</div></td>";
			echo "</tr>";
			echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
		}
		echo "</table>";
	}
	echo "</div>";
	
	
	echo "</div>";
}


if (!isset($_GET["szerk"])) {

	$szin="#dddddd";
	
	$res=sql_query("SELECT * from cegek	ORDER BY megnev<>'Új cég',megnev");

	echo "<table cellpadding=0 cellspacing=0 border=0>";
	while ($row=sql_fetch_array($res)) {
		$tc="tcella";
		if (!isset($first)) {
			echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
			$first=1;
		}
		if (trim($row["megnev"])=="") $row["megnev"]="nincs neve";
		echo "<tr>";
		echo "<td nowrap valign=top><div class={$tc}><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["megnev"]}</a>";
		if ($row["onlyreg"]==1) echo "<br>Csak regisztráltaknak";
		if ($row["onlybeutalo"]==1) echo "<br>Csak beutalóval lehet foglalni";
		if ($row["tajnotreq"]==1) echo "<br>Csak név, email, telefonszám kötelező";
		echo "</div></td>";
		//echo "<td nowrap valign=top><div class={$tc} style='min-width:300px;'>{$row["cim"]}&nbsp;&nbsp;</div></td>";
		echo "<td nowrap valign=top><div class={$tc}>".($row["domain"]==""?"":"http://{$row["domain"]}.hungariamed.hu (<a target='_blank' href='http://{$row["domain"]}.hungariamed.hu'>open</a>)")."</div></td>";
		//echo "<td nowrap valign=top><div class={$tc}>{$row["cimek"]}</div></td>";
		echo "<td nowrap valign=top><div class={$tc} style='min-width:50px;'>".($row["aktiv"]==1?"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>":"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>")."</div></td>";
		echo "<td nowrap valign=top><div class={$tc}>[<a onclick='alert(\"Nem törölhető!\");return false;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
		echo "</tr>";
		echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
	}
	echo "</table>";

}





?>