<?php

if($_SESSION['adminuser']['jog_helyszinset'] != 1) header("Location:index.php");

if (!helyszinModJog()) return;


if (isset($_GET["szerk"])) {
    $helyszinId=intval($_GET["szerk"]);
	$row=sql_fetch_array(sql_query("select * from helyszinek where id=?",array($helyszinId)));
	$_POST=$row;

	echo "<div style='background-color:#fff;padding:0px;'>";
	echo "<form name='iform' method='post' enctype='multipart/form-data'>";
	echo "<table style='font-size:12px;'>";

	echo "<tr><td width=100>Cím:</td><td><input class='inputbox' style='width:400px;' type='text' name='cim' value='{$_POST["cim"]}'></td></tr>";
	echo "<tr><td>Cím (en):</td><td><input class='inputbox' style='width:400px;' type='text' name='cim_en' value='{$_POST["cim_en"]}'></td></tr>";
	echo "<tr><td>Cím (de):</td><td><input class='inputbox' style='width:400px;' type='text' name='cim_de' value='{$_POST["cim_de"]}'></td></tr>";

	echo "<tr><td colspan=2 valign=top><hr></td></tr>";
	echo "<tr><td width='100'>Kiknek látszik:</td><td>";

	$resh=sql_query("select * from cegek order by megnev");
	$availableFor = 0;
	while ($rowh=sql_fetch_array($resh)) {
		$checkboxes.= "<div><input type='checkbox' name='cegcheck{$rowh["id"]}' value='1' ".(substr_count($_POST["ceglink"],"|{$rowh["id"]}|")>0?" checked":"")."/> {$rowh["megnev"]}</div>";
		if (substr_count($_POST["ceglink"],"|{$rowh["id"]}|")>0) $availableFor++;
	}

    echo "<div><a href='#' onclick='$(\"#cegboxes\").slideToggle();'>Elérhető {$availableFor} cég számára</a></div>";
    echo "<div id='cegboxes' style='display: none;'>{$checkboxes}</div>";
	echo "</td></tr>";

	echo "<tr><td colspan='2' valign='top'><hr></td></tr>";
	echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='aktiv'".($_POST["aktiv"]==1?" checked":"")."> Aktív</td></tr>";
    echo "<tr><td colspan='2' valign='top'><hr></td></tr>";
    //echo "<tr><td colspan='2' valign='top'>".beosztasEditorByAddress($helyszinId)."</td></tr>";

	echo "</table>";

	echo "<br><input type='submit' name='helyszinmentes' value='Mentés'> <input type='submit' name='scancel' value='Vissza'> ";

	echo "</form>";
	echo "</div>";
}


if (!isset($_GET["szerk"])) {

	$szin="#dddddd";
	
	$resh=sql_query("select * from cegek order by megnev");
	while ($rowh=sql_fetch_array($resh)) {
		$cegek[$rowh["id"]]=$rowh["megnev"];
	}


	
	//$w="h.cegid='{$_SESSION["helyszindata"]["id"]}' or h.cegid=0";
	//if ($user["jogosultsag"]>=2) 
	$w="true";
	
	$res=sql_query("SELECT h.* FROM helyszinek h
	where {$w}
	ORDER BY h.cim!='Új helyszín',h.cim");

	echo "<table cellpadding=0 cellspacing=0 border=0>";
	$group="aaa";
	while ($row=sql_fetch_array($res)) {
		$tc="tcella";
		if (!isset($first)) {
			echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
			$first=1;
		}
		if (trim($row["megnev"])=="") $row["megnev"]="nincs neve";
		
		$nyitva="";
		$resny=sql_query("SELECT * FROM helyszin_nyitvatartas where helyszinid='{$row["id"]}' order by nap");
		while ($rowny=sql_fetch_array($resny)) {
			$nyitva.="{$GLOBALS["hetnap"][$rowny["nap"]]} ({$rowny["tol"]}-{$rowny["ig"]}), ";
		}
		
		$vanbeo=0;
		if (sql_fetch_array(sql_query("select * from orvos_beosztas where helyszinid=? limit 1",array($row["id"])))) $vanbeo=1;

		echo "<tr>";
		echo "<td nowrap valign=top><div class={$tc}><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["cim"]}</a>".($row["cim_en"]!=""?"&nbsp;<span style='padding:2px;border:1px solid #f00;color:#f00;'>EN</span>":"").($row["cim_de"]!=""?"&nbsp;<span style='padding:2px;border:1px solid #f00;color:#f00;'>DE</span>":"")."</div></td>";

		echo "<td valign=top><div class={$tc}>";
		$cegids=explode("|",$row["ceglink"]);
		unset($ceglist);
		for ($i=0;$i<count($cegids);$i++) {
			if (@$cegek[$cegids[$i]]!="") $ceglist[]=$cegek[$cegids[$i]];
		}
		echo @implode(", ",$ceglist);
		echo "</div></td>";

		echo "<td nowrap valign=top><div class={$tc} style=''>".($vanbeo==0?"nincs beosztás":"")."&nbsp;&nbsp;</div></td>";
		echo "<td nowrap valign=top><div class={$tc} style='min-width:50px;'>".($row["aktiv"]==1?"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>":"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>")."</div></td>";
		echo "<td nowrap valign=top><div class={$tc}>[<a onclick='return confirm(\"Biztosan törlöd ezt a helyszínt?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
		echo "</tr>";
		echo "<tr><td colspan=7 style='border-bottom:1px solid #ccc;height:1px;'></td></tr>";
	}
	echo "</table>";
}


function beosztasEditorByAddress($helyszinId) {
    if (!beosztasModJog()) {
        return "<div class='nojog'>A beosztás módosításához nincs jogosultsága</div>";
    }

    if (!isset($_SESSION["helyszinbeosztascegfilter"])) $_SESSION["helyszinbeosztascegfilter"]=0;

    $w=$wc="";
    if ($GLOBALS["adminuser"]["jogosultsag"]<2) {
        $w="and b.cegid in (".getCegList($GLOBALS["adminuser"]["cegjog"]).")";
        $wc="and id in (".getCegList($GLOBALS["adminuser"]["cegjog"]).")";
    }


    echo "<tr><td colspan='2'><input type='hidden' name='helyszinform' value='1' />";
    echo "<div class='tdsepdiv'>Beosztás ";

    $cegbeo[]=0;
    $resstat=sql_query("SELECT cegid,GROUP_CONCAT(DISTINCT concat(nap,beonap)) AS napok FROM orvos_beosztas b WHERE orvosid=? {$w} GROUP BY cegid",array($_GET["szerk"]));
    while ($rowstat=sql_fetch_array($resstat)) {
        if (isset($_GET["sp"]) && $_GET["sp"]!=1) {
            $_GET["sp"]=1;
            $_SESSION["helyszinbeosztascegfilter"]=$rowstat["cegid"];
        }
        $beostat[$rowstat["cegid"]]=$rowstat;
        $cegbeo[]=$rowstat["cegid"];
    }


    echo "<select onchange='document.iform.submit();' name='helyszinbeosztascegfilter' style='width:300px;'>";
    $resh=sql_query("select * from cegek where true {$wc} order by id not in (".implode(",",$cegbeo)."),megnev");

    if (sql_num_rows($resh)>1) {
        echo "<option value='0'>Válassz!".(count($cegbeo)>1?" (beosztva ".(count($cegbeo)-1)." céghez)":"")."</option>";
    }

    while ($rowh=sql_fetch_array($resh)) {
        echo "<option style='".(isset($beostat[$rowh["id"]])?"font-weight:bold;":"")."' value='{$rowh["id"]}'".($_SESSION["helyszinbeosztascegfilter"]==$rowh["id"]?" selected":"").">{$rowh["megnev"]} ".(isset($beostat[$rowh["id"]])?"(".count(explode(",",$beostat[$rowh["id"]]["napok"]))." nap)":"")."</option>";
    }

    echo "</select> ";

    /*
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
    */

    echo "</div>";
    echo "</td></tr>";





    $resb=sql_query("select * from orvos_beosztas b where helyszinid=? and cegid=? {$w} order by cegid,nap<>0,nap,tol",array($helyszinId,$_SESSION["helyszinbeosztascegfilter"]));

    $sor=1;
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

        echo "<select id='orvosid{$sor}' name='orvosid{$sor}' style='width:200px;'>";

        if ($rowb["orvosid"]==0 && isset($_SESSION["orvos_orvosid"])) $rowb["orvosid"]=$_SESSION["orvos_orvosid"];
        if ($rowb["helyszinid"]==0 && isset($_SESSION["orvos_helyszinid"])) $rowb["helyszinid"]=$_SESSION["orvos_helyszinid"];
        if ($rowb["cegid"]==0 && isset($_SESSION["orvos_cegid"])) $rowb["cegid"]=$_SESSION["orvos_cegid"];

        $resh=sql_query("select * from orvosok where true order by nev");
        echo "<option value='0'>Válassz orvost!</option>";
        while ($rowh=sql_fetch_array($resh)) {
            echo "<option value='{$rowh["id"]}'".($rowb["orvosid"]==$rowh["id"]?" selected":"").">{$rowh["nev"]}</option>";
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

        echo "<span title='egy kezelés időtartama' id='intervalchooser{$rowb["id"]}'><a href='#' class='tlink' onclick='toggleIntervals({$rowb["id"]});return false;'>{$rowb["binterval"]} perc</a></span> ";

        echo "<span id='tipusstatus{$rowb["id"]}'><a href='#' class='tlink' title='{$titl}' onclick='showTipusValaszto({$rowb["id"]});return false;'>{$num} tipus</a></span> ";

        echo "<span title='Csak sorban foglalható időpontok'><input onclick='cssClick(1,{$sor});' type='checkbox' value='1' id='csaksorban{$sor}' name='csaksorban{$sor}'".($rowb["csaksorban"]==1?" checked":"").">&darr;</span> ";
        echo "<span title='Csak fordított sorrendben foglalható időpontok'><input onclick='cssClick(2,{$sor});' type='checkbox' value='2' id='csakvsorban{$sor}' name='csakvsorban{$sor}'".($rowb["csaksorban"]==2?" checked":"").">&uarr;</span> ";

        echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delbeosztas={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt a beosztás sort?\")'><img src='images/trash.png' title='Sor törlése'/></a>";

        echo "<div id='tipusvalaszto{$rowb["id"]}'></div>";

        echo "</td></tr>";
        $sor++;
    }



}




?>