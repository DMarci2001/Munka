<?php



if (isset($_GET["idoszak"])) $_SESSION["idoszak"]=$_GET["idoszak"];


	echo "<div>Időszak: <select name='idoszak' onchange='statIdoszakChange(this.value)'></div>";
for ($i=0;$i<1000;$i++) {
	$date=date("Y-m",mktime(0,0,0,date("m")-$i,1,date("Y")));
	if (!isset($_SESSION["idoszak"])) $_SESSION["idoszak"]=$date;
	echo "<option value='{$date}'".($_SESSION["idoszak"]==$date?" selected":"").">{$date}</option>";

	if ($date=="2015-10") break;


}
echo "</select>";	


$tol=$_SESSION["idoszak"]."-01 00:00:00";
$ig=$_SESSION["idoszak"]."-".date("t",strtotime($tol))." 23:59:59";


$csv="";


if (isset($_GET["orvoslista"])) {
	$cegid=intval($_GET["cegid"]);
	$rowc=sql_fetch_array(sql_query("SELECT * from cegek	where id='{$cegid}' ORDER BY megnev"));


	echo "<div style='margin-top:20px;'><a href='index.php?page=stat'>Vissza</a></div>";

	echo "<table cellpadding='0' cellspacing='4' border='0' style='margin-top:10px;'>";

	echo "<tr><td colspan='10' style='background:#eee;font-size:16px;color:#888;padding:5px 10px;margin-top:20px;'>{$rowc["megnev"]}</td></tr>";
	$reso=sql_query("SELECT o.nev AS orvosnev,h.cim AS helyszincim,t.`megnev` AS szurestipus,COUNT(*) AS hany,SUM(eljott) AS hanyeljott,f.* FROM foglalasok f
	LEFT JOIN orvosok o ON o.id=f.orvosassigned
	LEFT JOIN szurestipusok t ON t.`id`=f.`szurestipusid`
	LEFT JOIN helyszinek h ON h.id=f.helyszinid
	WHERE f.datum>'{$tol}' AND f.datum<'{$ig}' AND f.aktiv=1 AND f.cegid='{$rowc["id"]}' AND (f.taj<>'' OR f.nev<>'') AND f.nev<>'nincs név' 
	GROUP BY orvosassigned,szurestipusid	
	ORDER BY orvosnev");

	echo "<tr>";
	echo "<td>Orvos</td>";
	echo "<td>Szűréstípus</td>";
	echo "<td align='right'>Összes időpont</td>";
	echo "<td align='right'>Ebből eljött</td>";
	echo "<td>&nbsp;</td>";
	echo "</tr>";
	while ($rowo=sql_fetch_array($reso)) {
		echo "<tr>";
		echo "<td>{$rowo["orvosnev"]}</td>";
		echo "<td>{$rowo["szurestipus"]}</td>";
		echo "<td align='right'>{$rowo["hany"]}</td>";
		echo "<td align='right'>{$rowo["hanyeljott"]}</td>";
		echo "<td align='right'>&nbsp;&nbsp;&nbsp;[<a href='#' onclick='$(\"#orvosdetail{$rowo["orvosassigned"]}\").toggle();return false;'>részletek</a>]</td>";
		echo "</tr>";

		echo "<tr><td colspan='10'>";
		echo "<div id='orvosdetail{$rowo["orvosassigned"]}' style='background:#eee;padding:5px;display:inline-block;display:none;'>";
		
		
		echo "<table cellpadding='0' cellspacing='4' border='0' style=''>";

		$ress=sql_query("SELECT o.nev AS orvosnev,h.cim AS helyszincim,f.* FROM foglalasok f
		LEFT JOIN orvosok o ON o.id=f.orvosassigned
		LEFT JOIN helyszinek h ON h.id=f.helyszinid
		WHERE f.datum>'{$tol}' and f.datum<'{$ig}' and f.aktiv=1 AND f.cegid='{$rowc["id"]}' and f.orvosassigned='{$rowo["orvosassigned"]}' AND (f.taj<>'' OR f.nev<>'') AND f.nev<>'nincs név' order by datum");

		echo "<tr>";
		echo "<td title='Eljött'>E</td>";
		echo "<td title='Alkalmasság'>A</td>";
		echo "<td>Foglalás időpontja</td>";
		echo "<td>Név</td>";
		echo "<td>Telefon</td>";
		echo "<td></td>";
		echo "<td>TAJ szám</td>";
		echo "<td>Orvos</td>";
		echo "<td></td>";
		echo "<td>Regisztráció időpontja</td>";
		echo "</tr>";
		while ($rows=sql_fetch_array($ress)) {
			echo "<tr>";
			echo "<td>".($rows["eljott"]==1?"*":"")."</td>";
			echo "<td>";
			echo $rows["alkalmassag"];
			if ($rows["alkalmassag"]=="I") echo $rows["alkalmassagido"];
			echo "</td>";
			echo "<td>".substr($rows["datum"],0,16)."</td>";
			echo "<td>{$rows["nev"]}</td>";
			echo "<td>{$rows["telefon"]}</td>";
			echo "<td>{$rows["munkakor"]}</td>";
			echo "<td>{$rows["taj"]}</td>";
			echo "<td>{$rows["orvosnev"]}</td>";
			echo "<td>{$rows["helyszincim"]}</td>";
			echo "<td>".substr($rows["regdatum"],0,16)."</td>";
			echo "</tr>";
		}
		echo "</table>";		

		
		echo "</div>";
		echo "</td></tr>";

	}
	
	
	echo "</table>";





	return;
}


if (isset($_GET["tetellista"])) {
	$cegid=intval($_GET["cegid"]);
	
	$rowc=sql_fetch_array(sql_query("SELECT * from cegek	where id='{$cegid}' ORDER BY megnev"));


	echo "<div style='margin-top:20px;'><a href='index.php?page=stat'>Vissza</a> | <a href='index.php?page={$_GET["page"]}&tetellista&cegid={$_GET["cegid"]}&downloadcsv'>Letöltés</a></div>";
	echo "<div style='margin-top:10px;'>* = Eljött. Alkalmasság: I = Alkalmas, N = Nem alkalmas, IK = Ideiglenesen nem alkalmas, K = Korlátozottan alkalmas</div>";

	echo "<table cellpadding='0' cellspacing='4' border='0' style='margin-top:10px;'>";

	echo "<tr><td colspan='10' style='background:#eee;font-size:16px;color:#888;padding:5px 10px;margin-top:20px;'>{$rowc["megnev"]}</td></tr>";
	$ress=sql_query("SELECT o.nev AS orvosnev,h.cim AS helyszincim,f.* FROM foglalasok f
	LEFT JOIN orvosok o ON o.id=f.orvosassigned
	LEFT JOIN helyszinek h ON h.id=f.helyszinid
	WHERE f.datum>'{$tol}' and f.datum<'{$ig}' and f.aktiv=1 AND f.cegid='{$rowc["id"]}' AND (f.taj<>'' OR f.nev<>'') AND f.nev<>'nincs név' order by datum");

	echo "<tr>";
	echo "<td title='Eljött'>E</td>";
	echo "<td title='Alkalmasság'>A</td>";
	echo "<td>Foglalás időpontja</td>";
	echo "<td>Név</td>";
	echo "<td>Telefon</td>";
	echo "<td></td>";
	echo "<td>TAJ szám</td>";
	echo "<td>Orvos</td>";
	echo "<td></td>";
	echo "<td>Regisztráció időpontja</td>";
	echo "</tr>";
	while ($rows=sql_fetch_array($ress)) {
		echo "<tr>";
		echo "<td>".($rows["eljott"]==1?"*":"")."</td>";
		echo "<td>";
		echo $rows["alkalmassag"];
		if ($rows["alkalmassag"]=="I") echo $rows["alkalmassagido"];
		echo "</td>";
		echo "<td>".substr($rows["datum"],0,16)."</td>";
		echo "<td>{$rows["nev"]}</td>";
		echo "<td>{$rows["telefon"]}</td>";
		echo "<td>{$rows["munkakor"]}</td>";
		echo "<td>{$rows["taj"]}</td>";
		echo "<td>{$rows["orvosnev"]}</td>";
		echo "<td>{$rows["helyszincim"]}</td>";
		echo "<td>".substr($rows["regdatum"],0,16)."</td>";

		if ($rows["taj"]=="") $rows["taj"]="000000000";

		$csv.="{$rows["taj"]};";
		$csv.="{$rows["nev"]};";
		$csv.=substr($rows["datum"],0,16).";";
		$csv.=date("Y-m-d",strtotime("{$rows["datum"]} +6 month")).";";
		$csv.="\n";
		echo "</tr>";
	}
	echo "</table>";		
	
	
	
	if (isset($_GET["downloadcsv"])) {
		ob_clean();	
		header("Pragma: no-cache");
		header("Cache-Control: no-store, no-cache");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: must-revalidate");
		//header('Content-transfer-encoding: binary');
		header('Content-Disposition: attachment; filename="tetellista.csv"');
		
		header("Content-type: text/csv; charset=UTF-8");
		echo iconv("UTF-8","ISO-8859-2",$csv);
		
		die();
	}


	
	
	
	return;
}


$res=sql_query("SELECT * from cegek	ORDER BY megnev");

echo "<table cellpadding='0' cellspacing='0' border='0' style='margin-top:20px;'>";
while ($row=sql_fetch_array($res)) {
	$tc="tcella";
	if (trim($row["megnev"])=="") $row["megnev"]="nincs neve";
	echo "<tr style='background:#eee;'>";
	echo "<td nowrap valign=top><div class='{$tc}' style='font-size:16px;color:#888;padding:5px 10px;'>{$row["megnev"]}</div></td>";
	//echo "<td nowrap valign=top><div class={$tc} style='min-width:300px;'>{$row["cim"]}&nbsp;&nbsp;</div></td>";
	//echo "<td nowrap valign=top><div class={$tc}>".($row["domain"]==""?"":"http://{$row["domain"]}.hungariamed.hu (<a target='_blank' href='http://{$row["domain"]}.hungariamed.hu'>open</a>)")."</div></td>";
	//echo "<td nowrap valign=top><div class={$tc}>{$row["cimek"]}</div></td>";
	//echo "<td nowrap valign=top><div class={$tc} style='min-width:50px;'>".($row["aktiv"]==1?"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>":"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>")."</div></td>";
	//echo "<td nowrap valign=top><div class={$tc}>[<a onclick='alert(\"Nem törölhető!\");return false;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
	echo "</tr>";


	echo "<tr>";
	echo "<td nowrap colspan='2' valign='top'>";
	echo "<div style='margin:5px 10px;color:#888;'>";
	
	$all=$eljott=0;
	$ress=sql_query("SELECT nev,eljott FROM foglalasok WHERE datum>'{$tol}' and datum<'{$ig}' and aktiv=1 AND cegid='{$row["id"]}' AND (taj<>'' OR nev<>'') AND nev<>'nincs név'");
	while ($rows=sql_fetch_array($ress)) {
		$all++;
		if ($rows["eljott"]==1) $eljott++;
	}
	

	echo "<div style='display:table;'>";
	echo "<div style='display:table-row;'>";
	echo "<div style='display:table-cell;'>Foglalások száma:&nbsp;&nbsp;</div><div style='display:table-cell;text-align:right;'>{$all}</div>";		
	if ($all>0) {
		echo "<div style='display:table-cell;padding-left:20px;'>[<a href='index.php?page=stat&tetellista&cegid={$row["id"]}'>Tételes lista</a>] [<a href='index.php?page=stat&orvoslista&cegid={$row["id"]}'>Orvos lista</a>]</div>";		
	}
	echo "</div>";
	echo "<div style='display:table-row;'>";
	echo "<div style='display:table-cell;'>Eljött:&nbsp;&nbsp;</div><div style='display:table-cell;text-align:right;'>{$eljott}</div>";		
	echo "</div>";
	echo "</div>";
	
	echo "</div>";
	
	echo "</td>";
	echo "</tr>";


}
echo "</table>";







?>