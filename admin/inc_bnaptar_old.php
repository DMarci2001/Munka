<?php



function isBeosztasWeekDay_old($beosztas,$wd,$weekNumber=0) {
	for ($i=0;$i<count($beosztas);$i++) {
		if ($beosztas[$i]["nap"]==$wd) {
			if ($weekNumber==0) return true;
			if ($beosztas[$i]["hetek"]==2) {
				if ($weekNumber%2==0) {
					return true;
				} else {
					return false;
				}
			}
			if ($beosztas[$i]["hetek"]==1) {
				if ($weekNumber%2==0) {
					return false;
				} else {
					return true;
				}
			}
			return true;
		}
	}
	return false;
}


function isBeosztasWeekDay($beosztas,$wd,$weekNumber=0) {
	for ($i=0;$i<count($beosztas);$i++) {
		if ($beosztas[$i]["nap"]==$wd) {
			if ($weekNumber==0) return true;
			if ($beosztas[$i]["hetek"]==2) {
				if ($weekNumber%2==0) {
					return true;
				} else {
					continue;
				}
			}
			if ($beosztas[$i]["hetek"]==1) {
				if ($weekNumber%2!=0) {
					return true;
				} else {
					continue;
				}
			}
			return true;
		}
	}
	return false;
}


function isFreeIdopont($wd,$ora,$beosztas,$hanyfoglalt) {
	$dokik=0;
	for ($i=0;$i<count($beosztas);$i++) {
		$beo=$beosztas[$i];
		if ($beo["nap"]==$wd) {
			if (strtotime(date("Y-m-d {$ora}")) >= strtotime(date("Y-m-d {$beo["tol"]}")) && strtotime(date("Y-m-d {$ora}")) < strtotime(date("Y-m-d {$beo["ig"]}"))) {
				$dokik++;
			}
		}
	}
	//echo $dokik.$hanyfoglalt;
	if ($dokik>$hanyfoglalt) return true;
	return false;
}


$daydisplay=7;


if (!isset($_SESSION["helyszin"])) $_SESSION["helyszin"]=0;
if (!isset($_SESSION["helyszinceg"])) $_SESSION["helyszinceg"]=0;
if (!isset($_SESSION["naptarszurestipus"])) $_SESSION["naptarszurestipus"]=0;
if (!isset($_SESSION["shift"])) $_SESSION["shift"]=0;
if (isset($_GET["shift"])) $_SESSION["shift"]=$_GET["shift"];

$shift=round($_SESSION["shift"]);



echo "<div style='display:table;'>";
echo "<div style='display:table-row;'>";
echo "<div style='display:table-cell;'>";


$szunnapok[]="";
$rows=sql_fetch_array(sql_query("select * from settings"));
$n=explode(",",$rows["szunnapok"]);
for ($i=0;$i<count($n);$i++) {
	$szunnapok[]=trim($n[$i]);
}



$resh=sql_query("select * from cegek order by megnev");
while ($rowh=sql_fetch_array($resh)) {
	$cegek[$rowh["id"]]=$rowh["megnev"];
}

echo "<div>";
echo "<select name='helyszin' onchange='setHelyszin(this.value);'>";
echo "<option value='0'>Válassz helyszínt!</option>";


$resc=sql_query("select * from cegek where aktiv=1 order by id<>'{$_SESSION["helyszindata"]["id"]}',megnev");
while ($rowc=sql_fetch_array($resc)) {
    if ($user["jogosultsag"]<2 && substr_count($user["cegjog"],"|{$rowc["id"]}|")==0) continue;

    $res=sql_query("SELECT h.* FROM helyszinek h WHERE instr(ceglink,'|{$rowc["id"]}|') ORDER BY h.cim");
    if (sql_num_rows($res)==0) continue;

    echo "<option value='0' disabled style='background:#bbb;color:#fff;'>{$rowc["megnev"]}</option>";
    while ($rowt=sql_fetch_array($res)) {

        $color="#000";
        if (substr_count($rowt["cim"],"Martin ")>0 && $rowc["id"]==15) $color="#a00;";
        if (substr_count($rowt["cim"],"Martin ")>0 && $rowc["id"]==42) $color="#00a;";

        echo "<option style='color:{$color}' value='{$rowt["id"]}-{$rowc["id"]}'".("{$_SESSION["helyszin"]}-{$_SESSION["helyszinceg"]}"=="{$rowt["id"]}-{$rowc["id"]}"?" selected":"").">{$rowt["cim"]} ({$cegek[$rowc["id"]]})</option>";
    }

}


echo "</select>";
echo "</div>";




//szűréstipus választó
if ($_SESSION["helyszinceg"]!=0) {
	echo "<div style='margin-top:10px;'>";
	echo "<select name='helyszin' onchange='setNaptarSzuresTipus(this.value);'>";
	echo "<option value='0'>Válassz szűréstípust!</option>";
	
	$rest=sql_query("select * from szurestipusok");
	while ($rowt=sql_fetch_array($rest)) {
		$tipusnevek[$rowt["id"]]=$rowt["megnev"];
	}
	
	
	$res=sql_query("SELECT tipusok FROM orvos_beosztas b WHERE b.helyszinid='".intval($_SESSION["helyszin"])."' and b.tol<>0 and b.ig<>0");
	while ($row=sql_fetch_array($res)) {

		if ($user["jogosultsag"]<2 && substr_count($user["cegjog"],"|{$row["cegid"]}|")==0) continue;

		$ta=explode("|",$row["tipusok"]);
		for ($i=0;$i<count($ta);$i++) {
			if (trim($ta[$i])!="" && !in_array($ta[$i],$tipusok)) {
				$tipusok[]=$ta[$i];
			}
		}
	}
	
	if (isset($tipusok)) {
		for ($i=0;$i<count($tipusok);$i++) {
			$tipusdisplay[$tipusok[$i]]=$tipusnevek[$tipusok[$i]];
		}
		if (isset($tipusdisplay)) {
			asort($tipusdisplay);
			foreach ($tipusdisplay as $key => $value) {
				if (count($tipusdisplay)==1) {
					//ha csak 1 van, akkor az default lesz
					$_SESSION["naptarszurestipus"]=$key;
				}
				echo "<option value='{$key}'".($_SESSION["naptarszurestipus"]==$key?" selected":"").">{$value}</option>";
			}
		}
	}
	
	echo "</select>";
	echo "</div>";
}




echo "</div>";

echo "<div style='display:table-cell;vertical-align:top;padding:3px 0px 0px 20px;'>";
if (isset($_SESSION["helyszin"]) && $_SESSION["helyszin"]!=0) {
	echo "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&shift=".($shift-$daydisplay)."'><img height='20' src='images/prev.png' title='Lapozás vissza'/></a>&nbsp;&nbsp;&nbsp;&nbsp;";
	echo "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&shift=".($shift+$daydisplay)."'><img height='20' src='images/next.png' title='Lapozás előre'/></a>";
}
echo "</div>";


echo "</div>";
echo "</div>";



if (!isset($_SESSION["helyszin"]) || $_SESSION["helyszin"]==0) return;

$helyszin=round($_SESSION["helyszin"]);
$helyszinceg=round($_SESSION["helyszinceg"]);




$foglaltidopontok[]="";

//el kell dönteni, hogy csak a cég foglaltjait mutassa, vagy az összes kiválasztott címre foglaltakat!
//$res=sql_query("select datum,nev,eljott from foglalasok where helyszinid='{$helyszin}' and cegid='{$helyszinceg}' and aktiv=1");
$wf="";
if ($_SESSION["naptarszurestipus"]!=0) $wf.=" and szurestipusid='".intval($_SESSION["naptarszurestipus"])."'";
$res=sql_query("select datum,nev,eljott from foglalasok where helyszinid='{$helyszin}' and aktiv=1 {$wf}");
while ($row=sql_fetch_array($res)) {
	$ido=substr($row["datum"],0,16);
	$foglaltidopontok[]=$ido;
	$foglaltdata[$ido]=$row;
}

//print_r($foglaltidopontok);

$foglaltnapok[]="";
$res=sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and (szurestipusid=0 or szurestipusid=?)",array($helyszin,$helyszinceg,$_SESSION["naptarszurestipus"]));
while ($row=sql_fetch_array($res)) {
	$foglaltnapok[]=$row["nap"];
}



$minrendeles=0;
$maxrendeles=0;


$wt="";
if ($_SESSION["naptarszurestipus"]!=0) $wt.=" and instr(tipusok,'|".intval($_SESSION["naptarszurestipus"])."|')";
$res=sql_query("select * from orvos_beosztas where helyszinid='{$helyszin}' and cegid='{$helyszinceg}' {$wt} and tol<>0 and ig<>0");

while ($row=sql_fetch_array($res)) {
	if (strtotime($row["tol"])<strtotime($minrendeles) || $minrendeles==0) $minrendeles=$row["tol"];
	if (strtotime($row["ig"])>strtotime($maxrendeles) || $maxrendeles==0) $maxrendeles=$row["ig"];
	$beosztas[]=$row;
	$beosztasData[$row["nap"]]=$row;
}

if ($minrendeles==0) {
	echo "<div style='margin-top:20px;'>Nincs orvos beosztás hozzárendelve ehhez a céghez és helyszínhez.</div>";
	echo "</div>";
	return;	
}



echo "<div style='margin:10px 0px 10px 0px;'>";
//echo "Itt lesz az időpontválasztó... most még csak fixen kirak pár lehetőséget.<br>";
//echo "Kérjük válasszon a lenti időpontok közül:<br>";

echo "<table><tr>";


for ($i=0;$i<$daydisplay;$i++) {
	
	$nap=date("Y-m-d",mktime(0, 0, 0, date("m"), date("d")+$i+$shift, date("Y")));
	$wd=date("N",mktime(0, 0, 0, date("m"), date("d")+$i+$shift, date("Y"))); //day of week
	$wn=date("W",mktime(0, 0, 0, date("m"), date("d")+$i+$shift, date("Y"))); //number of week
	
	$dbg="#0a0";
	if ($nap==date("Y-m-d")) $dbg="#0a0";
		
		
	if (in_array($nap,$foglaltnapok)) $dbg="#ccc;";

	echo "<td valign='top'>";
	echo "<div style='background:{$dbg};padding:2px 10px 2px 10px;color:#fff;font-weight:bold;text-align:center;margin-right:5px;'>{$nap}<br>{$hetnap[$wd]}</div>";
	
	
	if (in_array($nap,$szunnapok)) {
		echo "<div style='text-align:center;'>Munkaszüneti<br/>nap!</div>";
		echo "</td>";
		continue;
	}
	
	if (!isset($beosztasData[$wd]["binterval"])) {
		echo "<div style='text-align:center;padding:0px;'>Nincs<br/>rendelés</div>";
		echo "</td>";
		continue;
	}
	
	
	if (in_array($nap,$foglaltnapok)) {
		echo "<div>erre a napra<br>foglalás tiltva</div>";
		echo "<div><a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&enablenap=".urlencode("{$nap}")."'>engedélyezés</a></div>";
	} else {
		echo "<div><a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&disablenap=".urlencode("{$nap}")."'>nap tiltása</a></div>";
	}
	
	$binterval=$beosztasData[$wd]["binterval"];
	$beginora=round(substr($minrendeles,0,2));
	$beginperc=round(substr($minrendeles,3,2));
	
	for ($o=0;$o<=55;$o++) {
		$ora=date("H:i",mktime($beginora,$beginperc+$o*$binterval,0,date("m"),date("d"),date("Y")));
		
		if (strtotime($ora)>=strtotime($maxrendeles)) break;
		
		$java="sF('{$nap} {$ora}');";			
		$class="nfb";
		$title="";
			
		if (isBeosztasWeekDay($beosztas,$wd,$wn)) {
			if (isFreeIdopont($wd,$ora,$beosztas,0)) {
				$class="fhb";			
				if (in_array("{$nap} {$ora}",$foglaltidopontok)) {
					$class="fb";
					$title=$foglaltdata["{$nap} {$ora}"]["nev"];
				}
			}
		}
		
		
		echo "<div style='text-align:left;'>";
		echo "<a class='{$class}' onclick=\"{$java}\" href='#' title='{$title}'>{$ora}</a>";
		
		if ($class!="nfb") {
			if ($class=="fb") {
				
				if ($foglaltdata["{$nap} {$ora}"]["eljott"]==1) {
					echo " <div title='Eljött' class='eljottjelzes'>&nbsp;</div>";
				}
			
			}	 else {
				echo " <a title='időpont lefoglalása' class='fi' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&idopont=".urlencode("{$nap} {$ora}")."&addnew'>+</a>";
			}
		}
		
		echo "</div>";
	}

	
	if (isOrvosLogin()) {
		echo "<div style='margin:10px 0px 0px 20px;'>";
		echo "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&tobbnapfoglal&from=".urlencode("{$nap}")."' title='több nap foglalása'>F+</a>";
		echo "</div>";
	}
	
	echo "</td>";	
	
}


if (isset($_GET["idopont"])) {
	echo "<td valign='top'>";

	echo "<div style='display:table;'>";
	echo "<div style='display:table-row;'>";
	echo "<div style='display:table-cell;vertical-align:middle;'><div style='font-size:28px;padding:0px 15px;'>".datumprint($_GET["idopont"])."</div></div>";
	echo "<div style='display:table-cell;vertical-align:middle;'><a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&idopont=".urlencode($_GET["idopont"])."&addnew'>+ foglalás</a></div>";
	echo "</div>";
	echo "</div>";

	$w="";
	if ($user["jogosultsag"]<2) {
		//$w="and f.cegid='{$user["cegid"]}'";
		$w="and f.cegid in (".getCegList($user["cegjog"]).")";
		
	}

	$res=sql_query("select f.*,t.megnev as sztipus,c.megnev as cegnev,o.nev as orvosnev from foglalasok f
	left join szurestipusok t on t.id=f.szurestipusid
	left join orvosok o on o.id=f.orvosassigned
	left join cegek c on c.id=f.cegid
	where datum='".addslashes($_GET["idopont"])."' and f.aktiv=1 and f.helyszinid='{$helyszin}' {$w}");
	
	if (sql_num_rows($res)>0) {
		while ($row=sql_fetch_array($res)) {
			echo "<div style='background:#eee;border-radius:5px;padding:15px 15px 20px 15px;margin-top:20px;'>";

			echo "<div style='font-size:20px;font-weight:bold;'>{$row["sztipus"]}</div>";

			echo "<div style='font-size:20px;'>{$row["cegnev"]}</div>";
			if ($row["foglalta"]!="") echo "<div style=''>Foglalta: {$row["foglalta"]}</div>";
			if ($row["orvosassigned"]!=0) echo "<div style=''>Orvos: {$row["orvosnev"]}".($row["ertesitve"]==1?" (értesítve)":"")."</div>";
			
			//fview begin
			echo "<div style='margin-top:20px;' id='fview{$row["id"]}'>";
			if ($row["nev"]!="") echo "<div style=''><b>{$row["nev"]}</b></div>";
			if ($row["munkakor"]!="") echo "<div style=''>{$row["munkakor"]}</div>";
			if ($row["nszam"]!="") echo "<div style='margin-bottom:10px;'>Naplószám: {$row["nszam"]}</div>";
			if ($row["taj"]!="") echo "<div style='margin-bottom:10px;'>TAJ: {$row["taj"]}</div>";
			if ($row["szuldatum"]!="") echo "<div>Születési dátum: {$row["szuldatum"]}</div>";
			if ($row["irsz"]!="") echo "<div>Cím: {$row["irsz"]} {$row["varos"]} {$row["utca"]}</div>";
			if ($row["telefon"]!="") echo "<div>Tel: {$row["telefon"]}</div>";
			if ($row["email"]!="") echo "<div>E-mail: <a href='mailto:{$row["email"]}'>{$row["email"]}</a></div>";
			
			echo "<div id='eljottcheck{$row["id"]}' style='margin-top:10px;'>";
			echo showEljottCheckBox($row);
			echo "</div>";

			echo "<div id='alkalmassagstatus{$row["id"]}'>";
			echo showAlkalmassagStatus($row);
			echo "</div>";
			
			echo "<div style='margin-top:10px;'>";
			//if ($row["munkaltato"]!="") echo "<div>Munkáltató: {$row["munkaltato"]}</div>";
			//if ($row["munkakor"]!="") echo "<div>Munkakör: {$row["munkakor"]}</div>";
			if ($row["megj"]!="") echo "<div>Megjegyzés: {$row["megj"]}</div>";
			echo "</div>";

			echo "<div style='margin-top:20px;'>";
			echo "<a class='ujbutton' href='javascript:toggleFSzerk({$row["id"]});'>Szerkesztés</a>&nbsp;&nbsp;";
			echo "<a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&idopont=".urlencode($_GET["idopont"])."&oertes={$row["id"]}'>Orvos értesítése</a>&nbsp;&nbsp;";
			echo "<a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&idopont=".urlencode($_GET["idopont"])."&delete={$row["id"]}' onclick='return confirm(\"Biztos törli ezt a foglalást?\");'>Törlés</a>";
			echo "</div>";
			echo "</div>";
			// fview end

			//fszerk begin
			echo "<div style='margin-top:20px;display:none;' id='fszerk{$row["id"]}'>";
			
			echo "<form name='iform' method='post' enctype='multipart/form-data'>";
			echo "<input type='hidden' name='fid' value='{$row["id"]}'>";
			echo "<table style='font-size:12px;'>";
		
			echo "<tr><td width='60'>Orvos:</td><td>";

			echo "<input type='hidden' name='regiorvos' value='{$row["orvosassigned"]}' /><select name='orvosassigned'>";
			echo "<option value='0'>Nincs orvoshoz kötve</option>";	
			$resh=sql_query("SELECT o.* FROM orvos_beosztas b LEFT JOIN orvosok o ON o.`id`=b.`orvosid` WHERE b.`helyszinid`='{$helyszin}' GROUP BY b.`orvosid`");
			while ($rowh=sql_fetch_array($resh)) {
				echo "<option value='{$rowh["id"]}'".($row["orvosassigned"]==$rowh["id"]?" selected":"").">{$rowh["nev"]}</option>";	
			}
			echo "</select> ";
		
			echo "</td></tr>";

			if ($row["nev"]=="nincs név") $row["nev"]="";
			
			echo "<tr><td width='60'>Taj szám:</td><td><input class='inputbox' style='width:200px;' type='text' name='taj' value='{$row["taj"]}'></td></tr>";
			echo "<tr><td width='60'>Név:</td><td><input class='inputbox' style='width:200px;' type='text' name='nev' value='{$row["nev"]}'></td></tr>";
			echo "<tr><td width='60'>Munkakör:</td><td><input class='inputbox' style='width:200px;' type='text' name='munkakor' value='{$row["munkakor"]}'></td></tr>";
			//echo "<tr><td width='60'>Szül. dátum:</td><td><input class='inputbox' style='width:200px;' type='text' name='szuldatum' value='{$row["szuldatum"]}'></td></tr>";
			echo "<tr><td width='60'>Szül. dátum:</td><td>".datumSelector($row["szuldatum"],"szuldatum")."</td></tr>";
			echo "<tr><td width='60'>E-mail:</td><td><input class='inputbox' style='width:200px;' type='text' name='email' value='{$row["email"]}'></td></tr>";
			echo "<tr><td width='60'>Telefon:</td><td><input class='inputbox' style='width:200px;' type='text' name='telefon' value='{$row["telefon"]}'></td></tr>";
			echo "<tr><td width='60'>Irsz:</td><td><input placeholder='Irsz' class='inputbox' style='width:40px;' type='text' name='irsz' value='{$row["irsz"]}'> <input placeholder='Város' class='inputbox' style='width:150px;' type='text' name='varos' value='{$row["varos"]}'></td></tr>";
			echo "<tr><td width='60'>Utca:</td><td><input class='inputbox' style='width:200px;' type='text' name='utca' value='{$row["utca"]}'></td></tr>";
			//echo "<tr><td width='60'>Munkáltató:</td><td><input class='inputbox' style='width:200px;' type='text' name='munkaltato' value='{$_POST["munkaltato"]}'></td></tr>";
			//echo "<tr><td width='60'>Munkakör:</td><td><input class='inputbox' style='width:200px;' type='text' name='munkakor' value='{$_POST["munkakor"]}'></td></tr>";
			echo "<tr><td width='60'></td><td><div><input type='checkbox' name='eljott' value='1' ".($row["eljott"]==1?"checked":"")." /> eljött</div>";
			echo "</td></tr>";
			echo "<tr><td width='60'>Naplószám:</td><td><input class='inputbox' style='width:200px;' type='text' name='nszam' value='{$row["nszam"]}'></td></tr>";
			echo "<tr><td width='60'>Megjegyzés:</td><td><textarea style='width:200px;height:60px;' name='megj'>{$row["megj"]}</textarea></td></tr>";
		
		
			echo "<tr><td colspan='2'><div style='background:#ccc;padding:2px 5px;'>Alkalmasság</div>";	
			
			foreach ($alkalmassagvariaciok as $key => $value) {
				$oc="";
				if ($key!="I") $oc="onclick=\"$('input[name=alkalmassagido]').attr('checked',false);\"";
				echo "<div><input ".($row["alkalmassag"]==$key?"checked":"")." {$oc} type='radio' name='alkalmassag' value='{$key}' /> {$value}";
				if ($key=="I") echo "
				<input ".($row["alkalmassagido"]==3?"checked":"")." type='radio' name='alkalmassagido' value='3' />3 hó 
				<input ".($row["alkalmassagido"]==6?"checked":"")." type='radio' name='alkalmassagido' value='6' />6 hó 
				<input ".($row["alkalmassagido"]==12?"checked":"")." type='radio' name='alkalmassagido' value='12' />1 év 
				<input ".($row["alkalmassagido"]==24?"checked":"")." type='radio' name='alkalmassagido' value='24' />2 év 
				<input ".($row["alkalmassagido"]==36?"checked":"")." type='radio' name='alkalmassagido' value='36' />3 év";
				if ($key=="IN") echo "<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;köv. vizsgálat: <input type='text' style='width:40px;' name='alkalmassagikhet' value='{$row["alkalmassagikhet"]}' /> hét";
				if ($key=="K") echo "<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<textarea placeholder='korlátozás szövege' style='width:250px;height:40px;' name='alkalmassagkorl'>{$row["alkalmassagkorl"]}</textarea>";
				echo "</div>";
			}
			echo "<div>Tüdőszűrő dátuma: <input type='text' style='width:80px;' name='tudoszuroervenyesseg' value='{$row["tudoszuroervenyesseg"]}' />";
			echo "</td></tr>";
			
		
			//echo "<tr><td colspan=2 valign=top><input type='checkbox' value=1 name='aktiv'".($_POST["aktiv"]==1?" checked":"")."> Aktív</td></tr>";
		
		
			echo "</table>";
		
		
			echo "<br><input type='submit' name='foglalasmentesnaptar' value='Mentés'/> ";
			echo "<input type='submit' name='foglalasmentesnaptaresertesites' value='Orvos értesítése'/> ";
			echo "<input onclick='javascript:toggleFSzerk({$row["id"]});' type='button' name='scancel' value='Vissza'/> ";
		
		
		
			echo "</form>";
			
			echo "</div>"; 
			//fszerk end

			echo "</div>";			
		}
	}
	
	
	echo "</td>";
}





echo "</tr></table>";


if (isset($_GET["tobbnapfoglal"])) {
	echo "<form method='post'>";

	echo "<table style='font-size:12px;'>";

	echo "<tr><td>Ettől a naptól: <input class='inputbox' style='width:70px;' type='text' name='datumtol' value='{$_GET["from"]}' /></td><td>";

	echo "<select id='hanynapot' name='hanynapot'>";
	echo "<option value='0'>Válassz!</option>";	
	echo "<option value='1'>1 napot</option>";	
	echo "<option value='7'>7 napot</option>";	
	echo "<option value='30'>30 napot</option>";
	echo "</select> ";

	echo "</td><td><input onclick=\"
	if ($('#hanynapot').val()==0) {
		alert('Válassza ki a foglalandó napok számát!');
		return false;
	}
	return confirm('Biztos lefoglal '+$('#hanynapot').val()+' napot?');
	
	\" type='submit' name='multifoglalstart' value='Lefoglalok' /> <input type='submit' name='multifoglalcancel' value='Mégse' />";

	echo "</td></tr>";

	echo "</table>";
	echo "</form>";
}



echo "</div>";

	

?>