<?php

if (!isset($_SESSION["orvosbeosztascegfilter"])) $_SESSION["orvosbeosztascegfilter"]=0;

$h="";
if (isset($_GET["szerk"])) {
    $oid=intval($_GET["szerk"]);
	$row=sql_fetch_array(sql_query("select * from orvosok where id=?",array($_GET["szerk"])));
	$_POST=$row;

	
	$hibak="";
	$resc=sql_query("SELECT TIME_TO_SEC(tol) AS tolsec,TIME_TO_SEC(ig) AS igsec,b.*,c.megnev as cegnev,h.cim as helyszin FROM orvos_beosztas b 
	left join cegek c on c.id=b.cegid
	left join helyszinek h on h.id=b.helyszinid
	WHERE orvosid=? AND tol<>0 AND ig<>0",array($_GET["szerk"]));
	while ($rowc=sql_fetch_array($resc)) {
		if ($rowe=sql_fetch_array(sql_query("SELECT b.*,c.megnev as cegnev,h.cim as helyszin FROM orvos_beosztas b
			left join cegek c on c.id=b.cegid
			left join helyszinek h on h.id=b.helyszinid
		  WHERE orvosid=? AND helyszinid<>? AND nap=? AND tol<>0 AND ig<>0 AND ((TIME_TO_SEC(tol)>? AND TIME_TO_SEC(tol)<?) OR  (TIME_TO_SEC(ig)>? AND TIME_TO_SEC(ig)<?))",array($_GET["szerk"],$rowc["helyszinid"],$rowc["nap"],$rowc["tolsec"],$rowc["igsec"],$rowc["tolsec"],$rowc["igsec"])))) {
			$hibak.="<div>Orvos két helyszínen van egyszerre: ".$GLOBALS["hetnap"][$rowe["nap"]]." <b>1.</b> {$rowe["tol"]}-{$rowe["ig"]} {$rowe["cegnev"]} {$rowe["helyszin"]} <b>2.</b> {$rowc["tol"]}-{$rowc["ig"]} {$rowc["cegnev"]} {$rowc["helyszin"]}</div>";
		}
	}
	

	if ($hibak!="") echo "<div style='margin-bottom:10px;background:#f88;padding:10px;display:inline-block;'>{$hibak}</div>";


	echo "<form name='iform' id='iform' method='post' enctype='multipart/form-data'><input type='hidden' name='orvosform' value='1'/><input type='hidden' name='orvosid' value='{$_POST["id"]}'/>";
	echo "<table style='font-size:12px;'>";

	echo "<tr><td width='130'>Név:</td><td><input class='inputbox' style='width:400px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
	echo "<tr><td>Pecsétszám:</td><td><input class='inputbox' style='width:200px;' type='text' name='pecsetszam' value='{$_POST["pecsetszam"]}'></td></tr>";
	echo "<tr><td>Orvos E-mail címe:</td><td><input class='inputbox' style='width:600px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
	echo "<tr><td valign='top' style='padding-top:5px;'>Orvos telefonszáma:</td><td><input class='inputbox' style='width:200px;' type='text' name='tel' value='{$_POST["tel"]}'> <input type='checkbox' value=1 name='telpublic'".($_POST["telpublic"]==1?" checked":"")."> megjelenjen a foglalási oldalon <input type='checkbox' value=1 name='onlytel'".($_POST["onlytel"]==1?" checked":"")."> csak telefonra fogad bejelentkezést<div style='padding-top:5px;'>Fontos: A telefonszám formátuma 06201234567.</div></td></tr>";

	echo "<tr><td>SMS értesítés:</td><td><div id='smsalertsettings'>".smsAlertSettings($oid)."</div></td></tr>";

	echo "<tr><td>&nbsp;</td><td><input type='checkbox' value=1 name='visszaigazol'".($_POST["visszaigazol"]==1?" checked":"")."> visszaigazolás szükséges, erre a címre: <input class='inputbox' style='width:200px;' type='text' name='visszaigazolemail' value='{$_POST["visszaigazolemail"]}'></td></tr>";
	echo "<tr><td>HMED értesítés email:</td><td><input class='inputbox' style='width:600px;' type='text' name='hmedemail' value='{$_POST["hmedemail"]}'></td></tr>";


	$w=$wc="";
	if ($user["jogosultsag"]<2) {
		//$w="and b.cegid='{$user["cegid"]}'";
		$w="and b.cegid in (".getCegList($user["cegjog"]).")";
		$wc="and id in (".getCegList($user["cegjog"]).")";
	}


	echo "<tr><td colspan='2'>";
	echo "<div class='tdsepdiv'>Beosztás ";

	$cegbeo[]=0;
	$resstat=sql_query("SELECT cegid,GROUP_CONCAT(DISTINCT concat(nap,beonap)) AS napok FROM orvos_beosztas b WHERE orvosid=? {$w} GROUP BY cegid",array($_GET["szerk"]));
	while ($rowstat=sql_fetch_array($resstat)) {
		if (isset($_GET["sp"]) && $_GET["sp"]!=1) {
			$_GET["sp"]=1;
			$_SESSION["orvosbeosztascegfilter"]=$rowstat["cegid"];
		}
		$beostat[$rowstat["cegid"]]=$rowstat;
		$cegbeo[]=$rowstat["cegid"];
	}


	echo "<select onchange='document.iform.submit();' name='orvosbeosztascegfilter' style='width:300px;'>";
	$resh=sql_query("select * from cegek where true {$wc} order by id not in (".implode(",",$cegbeo)."),megnev");

	if (sql_num_rows($resh)>1) {
		echo "<option value='0'>Válassz!".(count($cegbeo)>1?" (beosztva ".(count($cegbeo)-1)." céghez)":"")."</option>";	
	}
	
	while ($rowh=sql_fetch_array($resh)) {
		echo "<option style='".(isset($beostat[$rowh["id"]])?"font-weight:bold;":"")."' value='{$rowh["id"]}'".($_SESSION["orvosbeosztascegfilter"]==$rowh["id"]?" selected":"").">{$rowh["megnev"]} ".(isset($beostat[$rowh["id"]])?"(".count(explode(",",$beostat[$rowh["id"]]["napok"]))." nap)":"")."</option>";	
	}
	
	echo "</select> ";
		
		
	echo "<a class='ujbutton' style='padding:3px 10px;font-weight:normal;' href='#' onclick='$(\"#bcopierdiv\").slideToggle();return false;'>Beosztás másolása</a>";

	echo "<div id='bcopierdiv' style='font-size:12px;font-weight:normal;width:800px;padding:10px;display:none;'>";
	$resh=sql_query("select * from cegek where id<>? {$wc} order by id not in (".implode(",",$cegbeo)."),megnev",array($_SESSION["orvosbeosztascegfilter"]));
	while ($rowh=sql_fetch_array($resh)) {
		echo "<div style='display:inline-block;'><input name='copyceg{$rowh["id"]}' type='checkbox' ".(in_array($rowh["id"],$cegbeo)?" checked":"")." value='1' /> {$rowh["megnev"]}</div/> ";
	}
	echo "<div style='padding-top:10px;'>";
	echo "<input type='hidden' id='orvosmentesandcopy' name='orvosmentesandcopy' value='0' />";
	echo "<a class='ujbutton' style='padding:3px 10px;font-weight:normal;' href='#' onclick='if (!confirm(\"Biztos másolod ezt a beosztást a kijelölt cégekhez?\")) {return false;} $(\"#orvosmentesandcopy\").val(1);document.iform.submit();'>Beosztás másolása a kijelölt cégekhez</a> <a class='ujbutton' style='padding:3px 10px;font-weight:normal;' href='#' onclick='$(\"#bcopierdiv\").slideToggle();'>Mégse</a>";
	echo "</div>";

	
	echo "</div>";
		
		
	echo "</div>";
	
	
	
	echo "</td></tr>";
	
	if (!beosztasModJog()) {
		echo "<tr><td colspan='2' style=''><div class='nojog'>A beosztás módosításához nincs jogosultsága</div></td></tr>";
	}


	$resb=sql_query("select * from orvos_beosztas b where orvosid=? and (cegid=?) {$w} order by cegid,nap<>0,nap,tol",array($_GET["szerk"],$_SESSION["orvosbeosztascegfilter"]));

	$sor=1;
	
	//unset($_SESSION["orvos_helyszinid"]);
	//unset($_SESSION["orvos_cegid"]);
	
	$hetBackgrounds=array("","#ffffbb","#bbffff");
	
	while ($rowb=sql_fetch_array($resb)) {
		echo "<tr><td colspan='2'>";

		echo "<input type='hidden' name='beosztasid{$sor}' value='{$rowb["id"]}'/>";

		echo "<input title='aktív?' type='checkbox' name='aktiv{$sor}' value='1' ".($rowb["aktiv"]==1?" checked":"")."/> ";

		echo "<select name='weekday{$sor}' onchange=\"if (this.value!=10) { $('#hetek{$sor}').show(); $('#beonap{$sor}').hide(); } else { $('#hetek{$sor}').hide(); $('#beonap{$sor}').show(); }\">";
		echo "<option value='0'>Válassz napot!</option>";	
		for ($n=1;$n<=7;$n++) {
			echo "<option value='{$n}'".($rowb["nap"]==$n?" selected":"").">{$GLOBALS["hetnap"][$n]}</option>";
		}
		echo "<option value='10'".($rowb["nap"]==10?" selected":"").">Egy dátum</option>";	
		echo "</select> ";

		echo "<select id='hetek{$sor}' name='hetek{$sor}' style='width:110px;background:{$hetBackgrounds[$rowb["hetek"]]};".($rowb["nap"]==10?"display:none;":"")."'>";
		echo "<option value='0'".($rowb["hetek"]==0?" selected":"").">Minden hét</option>";	
		echo "<option value='1'".($rowb["hetek"]==1?" selected":"").">Páratlan hetek</option>";	
		echo "<option value='2'".($rowb["hetek"]==2?" selected":"").">Páros hetek</option>";	
		echo "</select> ";

		echo "<input id='beonap{$sor}' name='beonap{$sor}' type='text' value='{$rowb["beonap"]}' style='width:102px;".($rowb["nap"]==10?"":"display:none;")."' placeholder='éééé-hh-nn' /> ";


		if (!isset($_SESSION["orvos_helyszinid"]) && $rowb["helyszinid"]!=0) $_SESSION["orvos_helyszinid"]=$rowb["helyszinid"];
		if (!isset($_SESSION["orvos_cegid"]) && $rowb["cegid"]!=0) $_SESSION["orvos_cegid"]=$rowb["cegid"];

		echo "<select id='helyszinid{$sor}' name='helyszinid{$sor}' style='width:200px;'>";


		if ($rowb["helyszinid"]==0 && isset($_SESSION["orvos_helyszinid"])) $rowb["helyszinid"]=$_SESSION["orvos_helyszinid"];
		if ($rowb["cegid"]==0 && isset($_SESSION["orvos_cegid"])) $rowb["cegid"]=$_SESSION["orvos_cegid"];


		$resh=sql_query("select * from helyszinek where true order by cim");
		echo "<option value='0'>Válassz helyszínt!</option>";	
		while ($rowh=sql_fetch_array($resh)) {
			echo "<option value='{$rowh["id"]}'".($rowb["helyszinid"]==$rowh["id"]?" selected":"").">{$rowh["cim"]}</option>";	
		}
		echo "</select> ";

		echo "<select name='tol{$sor}'>";
		echo "<option value='0'>Kezdés?</option>";	
		for ($n=0;$n<=1125;$n+=5) {
			$t=date("H:i",mktime(5,0+$n,0,1,1,2015));
			echo "<option value='{$t}'".($rowb["tol"]==$t?" selected":"").">{$t}</option>";	
		}
		echo "</select> ";

		echo "<select name='ig{$sor}'>";
		echo "<option value='0'>Vége?</option>";	
		for ($n=0;$n<=1065;$n+=5) {
			$t=date("H:i",mktime(6,0+$n,0,1,1,2015));
			echo "<option value='{$t}'".($rowb["ig"]==$t?" selected":"").">{$t}</option>";	
		}
		echo "</select> ";

		/*
		echo "<select name='cegid{$sor}' style='width:200px;'>";

		$resh=sql_query("select * from cegek where true {$wc} order by megnev");

		if (sql_num_rows($resh)>1) echo "<option value='0'>Összes cég</option>";	
		while ($rowh=sql_fetch_array($resh)) {
			echo "<option value='{$rowh["id"]}'".($rowb["cegid"]==$rowh["id"]?" selected":"").">{$rowh["megnev"]}</option>";	
		}
		echo "</select> ";
		*/
		
		echo "<input type='hidden' name='tipusidk{$sor}' id='tipusidk{$sor}' value='{$rowb["tipusok"]}' />";
		
		$num=0;
		unset($idk);
		$idk[]=0;
		$titl="nincs tipus hozzárendelve";
		
		$ik=explode("|",$rowb["tipusok"]);
		for ($i=0;$i<count($ik);$i++) {
			if ($ik[$i]!="") {
				$num++;
				$idk[]=$ik[$i];
			}
		}
		
		if (count($idk)>1) {
			$rowtt=sql_fetch_array(sql_query("SELECT GROUP_CONCAT(megnev SEPARATOR ', ') AS megnevek FROM szurestipusok WHERE id IN (".implode(",",$idk).")"));
			$titl=$rowtt["megnevek"];	
		}
		
		//audi
		//if ($_SESSION["orvosbeosztascegfilter"]==15 || $_SESSION["orvosbeosztascegfilter"]==42) {
			echo "<span title='egy kezelés időtartama' id='intervalchooser{$rowb["id"]}'><a href='#' class='tlink' onclick='toggleIntervals({$rowb["id"]});return false;'>{$rowb["binterval"]} perc</a></span> ";
		//}
		
		echo "<span id='tipusstatus{$rowb["id"]}'><a href='#' class='tlink' title='{$titl}' onclick='showTipusValaszto({$rowb["id"]});return false;'>{$num} tipus</a></span> ";

		echo "<span title='Csak sorban foglalható időpontok'><input onclick='cssClick(1,{$sor});' type='checkbox' value='1' id='csaksorban{$sor}' name='csaksorban{$sor}'".($rowb["csaksorban"]==1?" checked":"").">&darr;</span> ";
		echo "<span title='Csak fordított sorrendben foglalható időpontok'><input onclick='cssClick(2,{$sor});' type='checkbox' value='2' id='csakvsorban{$sor}' name='csakvsorban{$sor}'".($rowb["csaksorban"]==2?" checked":"").">&uarr;</span> ";
		
		echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delbeosztas={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt a beosztás sort?\")'><img src='images/trash.png' title='Sor törlése'/></a>";

		echo "<div id='tipusvalaszto{$rowb["id"]}'></div>";
				
		echo "</td></tr>";
		$sor++;
	}
	
	echo "<tr><td colspan=2 valign=top>";
	if ($_SESSION["orvosbeosztascegfilter"]==0) {
		echo "<div style='margin:10px 0px;'>A beosztás szerkesztéséhez először válassz céget!</div>";
	} else {
		if (sql_num_rows($resb)==0) echo "<div style='margin:10px 0px;'>Ennek az orvosnak nincs beosztása a kiválasztott céghez!</div>";
		echo "<input type='submit' name='addbeosztas' value='+ Beosztás hozzáadása'>";
	}
	echo "</td></tr>";

	echo "<tr><td colspan='2'><div class='tdsepdiv' style='margin-top:10px;'>Szabadság</div></td></tr>";
	if (!szabadsagJog()) {
		echo "<tr><td colspan='2' style=''><div class='nojog'>A szabadságok módosításához nincs jogosultsága</div></td></tr>";
	}

	echo "<tr><td colspan='2'>";
	$ressz=sql_query("select * from szabadsag where oid=? order by datumtol",array($_GET["szerk"]));
	while ($rowsz=sql_fetch_array($ressz)) {
		echo "<div>{$rowsz["datumtol"]} - {$rowsz["datumig"]} <a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delszabadsag={$rowsz["id"]}' onclick='return confirm(\"Biztos törlöd ezt a szabadság sort?\")'><img src='images/trash.png' title='Sor törlése'/></a></div>";
	}
	echo "<div><input class='inputbox' style='width:100px;' type='text' name='szabadsagtol' value='' placeholder='-tól dátum'> - <input class='inputbox' style='width:100px;' type='text' name='szabadsagig' value='' placeholder='-ig dátum'> <input type='submit' onClick='return checkSzabiData()' name='addszabadsag' value='+ szabadság hozzáadása'></div>";
	echo "</td></tr>";

	if( $_SESSION['adminuser']['jog_orvosset'] == 1 )
	{
		$type = explode( ",", $_POST['szurestipusok'] );
		
		echo "<tr><td colspan = '2'><div class='tdsepdiv' style='margin:10px 0px 0px 0px'>Vizsgálat típusok kiválasztása</div></td></tr>";
		echo "<tr><td><table>";
		echo "<tr><td><input type = 'checkbox' ".(in_array(1, $type)?"checked":"")." name = 'szak_belgyogy' value = '1' /></td>";
		echo "	  <td>Belgyógyász</td></tr>";
		echo "<tr><td><input type = 'checkbox' ".(in_array(2, $type)?"checked":"")." name = 'szak_rtg' value = '2' /></td>";
		echo "	  <td>Röntgen</td></tr>";
		echo "<tr><td><input type = 'checkbox' ".(in_array(3, $type)?"checked":"")." name = 'szak_uh' value = '3' /></td>";
		echo "	  <td>Ultrahang</td></tr>";
		echo "<tr><td><input type = 'checkbox' ".(in_array(4, $type)?"checked":"")." name = 'szak_borgyogy' value = '4' /></td>";
		echo " 	  <td>Bőrgyógyász</td></tr>";
		echo "<tr><td><input type = 'checkbox' ".(in_array(5, $type)?"checked":"")." name = 'szak_szemesz' value = '5' /></td>";
		echo "	  <td>Szemész</td></tr>";
		echo "<tr><td><input type = 'checkbox' ".(in_array(6, $type)?"checked":"")." name = 'szak_kardio' value = '6' /></td>";
		echo "	  <td>Kardiológia</td></tr>";
		echo "<tr><td><input type = 'checkbox' ".(in_array(7, $type)?"checked":"")." name = 'szak_torna' value = '7' /></td>";
		echo "	  <td>Gyógytornász</td></tr>";	
		
		echo "<tr><td><input type = 'checkbox' ".(in_array(8, $type)?"checked":"")." name = 'szak_labor' value = '8' /></td>";
		echo "	  <td>Labor</td></tr>";	
		echo "<tr><td><input type = 'checkbox' ".(in_array(9, $type)?"checked":"")." name = 'szak_urologia' value = '9' /></td>";
		echo "	  <td>Urológia</td></tr>";	
		echo "<tr><td><input type = 'checkbox' ".(in_array(10, $type)?"checked":"")." name = 'szak_nogyogy' value = '10' /></td>";
		echo "	  <td>Nőgyógyászat</td></tr>";	
		echo "<tr><td><input type = 'checkbox' ".(in_array(11, $type)?"checked":"")." name = 'szak_tudogyogy' value = '11' /></td>";
		echo "	  <td>Tüdőgyógyászat</td></tr>";	
		echo "<tr><td><input type = 'checkbox' ".(in_array(12, $type)?"checked":"")." name = 'szak_ortopedia' value = '12' /></td>";
		echo "	  <td>Ortopédia</td></tr>";	
		
		echo "</td></tr></table>";
	}
	
	//echo "<tr><td colspan='2'><div class='tdsepdiv' style='margin-top:10px;'>Belépési információk</div></td></tr>";

	//echo "<tr><td width=100>Felhasználónév:</td><td style='font-weight:bold;'><input class='inputbox' style='width:200px;' type='text' name='username' value='{$_POST["username"]}'></td></tr>";
	//echo "<tr><td width=100>Jelszó:</td><td><input class='inputbox' style='width:200px;' type='text' name='jelszo' value='{$_POST["jelszo"]}'></td></tr>";

	//Orvosi jogkörök:
	$request = sql_query( "SELECT * FROM users WHERE orvosid = ?", array( $_GET['szerk'] ));
	if ( sql_num_rows($request) > 0 && $_SESSION['adminuser']['jog_jogset'] == 1 )
	{
		$adminAutorithy = "";
		$result = sql_fetch_array( $request );
		$nowrap = "style = 'white-space:nowrap'";
		$adminAutorithy.= "<tr><td colspan = '2'><div class='tdsepdiv' style='margin:10px 0px 0px 0px'>Jogkörök hozzárendelése</div></td></tr>";
		$adminAutorithy.= "<tr><td><table>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_cegset' ".( $result["jog_cegset"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Cégek kezelése</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_helyszinset' ".( $result["jog_helyszinset"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Helyszínek kezelése</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_orvosset' ".( $result["jog_orvosset"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Orvosok kezelése</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_beosztasset' ".( $result["jog_beosztasset"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Orvos beosztások kezelése</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_szabi' ".( $result["jog_szabi"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Szabadságok beállítása</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_szurestipusset' ".( $result["jog_szurestipusset"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Szűréstipusok kezelése</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_zarolista' ".( $result["jog_zarolista"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Zárólista látása</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_zaroszerk' ".( $result["jog_zaroszerk"] == 1 ? "checked" : "" )."  value = '1'/ ></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Záró leletek szerkesztése</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_leletlatas' ".( $result["jog_leletlatas"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Leletek látása</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_leletszerk' ".( $result["jog_leletszerk"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Leletek szerkesztése</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_gdprhferes' ".( $result["jog_gdprhferes"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >GDPR hozzáférés</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_kuponlista' ".( $result["jog_kuponlista"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Kuponkód lista</td></tr>";
		$adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_kuponkeszites' ".( $result["jog_kuponkeszites"] == 1 ? "checked" : "" )." value = '1' /></td>";
		$adminAutorithy.= "	   <td {$nowrap} >Kuponkód hozzáadás/szerkesztés</td></tr>";
		$adminAutorithy.= "<tr><td {$nowrap} colspan = '2'>Felh. név: <input type = 'text' value = '{$result['username']}' name = 'username' /></td></tr>";
		$adminAutorithy.= "<tr><td {$nowrap} colspan = '2'>Új jelszó: <input type = 'text' name = 'password' style = 'margin-left:2px'/></td></tr>";
		$adminAutorithy.= "</table></td></tr>";
		echo $adminAutorithy;
	}

	echo "<tr><td colspan=2 valign=top><input type='checkbox' value=1 name='aktiv'".($_POST["aktiv"]==1?" checked":"")."> Aktív</td></tr>";

	echo "</table>";


	echo "<div id='errorlistdiv' style='padding:10px;background:#f00;color:#fff;font-weight:bold;display:none;'></div>";
	//onclick=\"return orvosDataVerify();\";
	if (orvosModJog()) {
		if( sql_num_rows($request) == 0 ) {
			echo "<br><input type='submit' onClick='accountini({$_GET['szerk']})' name = 'account-ini' value = 'Account inicializálás' /> ";
		}
		else echo "<br>";
		echo "<input  type='submit' name='orvosmentes' value='Mentés'> ";
	} else {
		echo "<br><input onclick='alert(\"Az orvos adatlap módosításához nincs jogosultsága!\");return false;' type='submit' name='orvosmentes' value='Mentés'> ";
	}
	echo "<input type='submit' name='scancel' value='Vissza'> ";



	echo "</form>";

}


if (!isset($_GET["szerk"])) {

	$szin="#dddddd";

	$w="";
	if ($user["jogosultsag"]<2) {
		$w="and (b.cegid in (".getCegList($user["cegjog"]).") or b.cegid is null)";
	}
	
	if (!isset($_SESSION["cegfilter"])) $_SESSION["cegfilter"]=0;
	if ($_SESSION["cegfilter"]>0) $w="and (b.cegid='".addslashes($_SESSION["cegfilter"])."' or b.cegid is null)";
	if ($_SESSION["cegfilter"]==-1) $w="and (b.cegid='0' or b.cegid is null)";
	
	if ($user["jogosultsag"]>=2) {
		echo "<div style='margin-bottom:10px;'>";
		echo "<select name='cegselect' onchange='setCegFilter(this.value,\"orvosok\");'>";
		echo "<option value='0'>Szűrés cégre</option>";
		echo "<option value='-1'".($_SESSION["cegfilter"]==-1?" selected":"").">Összes céget fogadók</option>";
		
		$res=sql_query("SELECT * FROM cegek order by megnev");
		
		while ($rowt=sql_fetch_array($res)) {
			echo "<option value='{$rowt["id"]}'".($_SESSION["cegfilter"]==$rowt["id"]?" selected":"").">{$rowt["megnev"]}</option>";
		}

		echo "</select>";
		echo "</div>";
	}
	
	$res=sql_query("SELECT GROUP_CONCAT(DISTINCT b.tipusok SEPARATOR '') AS tipusok,o.*,GROUP_CONCAT(DISTINCT h.cim separator '<br/>') AS cimek,GROUP_CONCAT(DISTINCT c.megnev separator '<br/>') AS cegek,GROUP_CONCAT(DISTINCT IF(b.cegid=0,'nulla','') SEPARATOR ',') AS cegidk FROM orvosok o
	LEFT JOIN orvos_beosztas b ON b.`orvosid`=o.`id`
	LEFT JOIN helyszinek h ON h.`id`=b.`helyszinid`
	LEFT JOIN cegek c ON c.`id`=b.`cegid`
	where true {$w}
	GROUP BY o.id
	ORDER BY nev<>'Új orvos',nev");

	$rest=sql_query("select * from szurestipusok");
	while ($rowt=sql_fetch_array($rest)) {
		$tipusnevek[$rowt["id"]]=$rowt["megnev"];
	}

	echo "<table cellpadding='0' cellspacing='0' border='0'>";
	while ($row=sql_fetch_array($res)) {

		unset($tipusok);
		$ta=explode("|",$row["tipusok"]);
		for ($i=0;$i<count($ta);$i++) {
			if (trim($ta[$i])!="") {
				if (isset($tipusnevek[$ta[$i]])) $tipusok[]=$tipusnevek[$ta[$i]];
			}
		}
		
		if ($row["cegidk"]=="nulla") {
			$row["cegek"]="*<br/>{$row["cegek"]}";
		}
		$tc="tcella";
		if (!isset($first)) {
			echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
			$first=1;
		}
		if (trim($row["nev"])=="") $row["nev"]="nincs neve";
		echo "<tr>";
		echo "<td nowrap valign=top><div class='{$tc}'>";
		echo "<a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}&sp'>{$row["nev"]}</a>";
		if (isset($tipusok)) echo "<div>".implode("<br/>",array_unique($tipusok))."</div>";
		echo "</div></td>";
		//echo "<td nowrap valign=top><div class={$tc} style='min-width:300px;'>{$row["cim"]}&nbsp;&nbsp;</div></td>";
		//echo "<td nowrap valign=top><div class={$tc}>{$row["cegek"]}</div></td>";
		echo "<td nowrap valign=top><div class={$tc} style='min-width:300px;'>";
		if ($row["cimek"]!="") {
			echo "{$row["cimek"]}";
		} else {
			echo "<span style='color:#f00;'>nincs még beosztása</span>";
		}
		echo "</div></td>";
		echo "<td nowrap valign=top><div class={$tc} style='min-width:200px;'>";
		if ($row["cegek"]!="") {
			echo "{$row["cegek"]}";
		} else {
			echo "Létrehozta: {$row["createdby"]}";
		}
		echo "</div></td>";
		echo "<td nowrap valign=top><div class={$tc} style='color:#f00;'>".($row["visszaigazol"]==1?"V":"")."</div></td>";
		echo "<td nowrap valign=top><div class={$tc} style='min-width:100px;'>{$row["email"]}</div></td>";
		echo "<td nowrap valign=top><div class={$tc} style='min-width:50px;'>".($row["aktiv"]==1?"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>":"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>")."</div></td>";
		echo "<td nowrap valign=top><div class={$tc}>[<a onclick='return confirm(\"Biztosan törlöd ezt az orvost?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
		echo "</tr>";
		echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
	}
	echo "</table>";

}





?>