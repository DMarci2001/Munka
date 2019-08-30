<?php



$res=sql_query("SELECT e.*,a.*,f.nev as paciens FROM emailattachments a
LEFT JOIN email e ON e.azo=a.`eid`
left join felhasznalok f on f.id=a.uid
WHERE RIGHT(LOWER(filename),4)='.pdf' OR RIGHT(LOWER(filename),5)='.docx' OR RIGHT(LOWER(filename),4)='.doc' OR RIGHT(LOWER(filename),4)='.jpg'
ORDER BY erkezett DESC");

$tc="tcella";
$colspan=7;

echo "<table cellpadding='0' cellspacing='0' border='0' style='margin-top:10px;min-width:600px;'>";
$s="background:#eee;font-size:16px;padding:5px;";

if (sql_num_rows($res)==0) {
	echo "<tr><td colspan='{$colspan}' class='{$tc}'>Nincs beérkező dokumentum</td></tr>";
} else {
	echo "<tr style='background:#eee;'>";
	echo "<td nowrap valign=top><div class='{$tc}'>&nbsp;&nbsp;Beérkezett</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>Feladó</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>Tárgy&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>File&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>&nbsp;&nbsp;</div></td>";
	echo "</tr>";
}

function jnsFilter($s) {
	$s=strtolower(iconv("UTF-8","ISO-8859-2",$s));
	return $s;
}

$csv="";

while ($row=sql_fetch_array($res)) {	

	echo "<tr><td colspan='{$colspan}' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
	
	echo "<tr>";
	//echo "<td nowrap valign=top><div class='{$tc}'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>".substr($row["datum"],0,16)."</a>&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>&nbsp;&nbsp;".substr($row["erkezett"],0,16)."&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>{$row["fromname"]}&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>{$row["subject"]}&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'><a href='index.php?getattachment={$row["id"]}'>{$row["filename"]}</a>&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>";
	if (getTajFromString("{$row["subject"]} {$row["filename"]}")=="") {
		echo "<span style='color:#f00;'>Nincs TAJ szám a file névben, vagy tárgyban!</span>";
	} else {
		if ($row["uid"]!=0) {
			echo "<span style='color:#0a0;'>Csatolva: {$row["paciens"]}</span>";
		} else {
			echo "<a href='index.php?page={$_GET["page"]}&addatouser={$row["id"]}'>csatolás</a>&nbsp;&nbsp;";
		}
	}
	echo "</div></td>";
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
	header('Content-transfer-encoding: binary');
	header('Content-Disposition: attachment; filename="erkeztetes_'.$_SESSION["blistanap"].'.csv"');

	header("Content-Type: text/csv;");
		
	echo iconv("utf-8//IGNORE","ISO-8859-2//IGNORE",$csv);
	ob_flush();
	die();
	
}




?>