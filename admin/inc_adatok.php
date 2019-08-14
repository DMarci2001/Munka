<?php



$h="";
if (isset($_GET["szerk"])) {
	$row=sql_fetch_array(sql_query("select * from towns where id='".addslashes($_GET["szerk"])."'"));
	$_POST=$row;

	echo "<div style='background-color:#fff;padding:0px;'>";
	echo "<form name='iform' method='post' enctype='multipart/form-data'>";
	echo "<table style='font-size:12px;'>";

	echo "<tr><td width=100>Megnevezés:</td><td><input class='inputbox' style='width:200px;' type=text name='townname' value='{$_POST["townname"]}' readonly></td></tr>";
	//echo "<tr><td width=100>Esemény tól-ig:</td><td><input class=inputbox style='width:100px;' type=text name='datumtol' value='".substr($_POST["datumtol"],0,16)."'> - <input class=inputbox style='width:100px;' type=text name='datumig' value='".substr($_POST["datumig"],0,16)."'></td></tr>";
	echo "<tr><td>Cím:</td><td valign=top><input class=inputbox style='width:300px;' type=text name='btitle' value='".@$_POST["btitle"]."'> <input class=inputbox style='width:300px;' type=text name='btitle_en' value='".@$_POST["btitle_en"]."'></td></tr>";
	echo "<tr><td colspan=2 valign=top>Bemutatkozás:<br><textarea name='bemutatkozas' style='width:650px;height:300px;'>".@$_POST["bemutatkozas"]."</textarea> <textarea name='bemutatkozas_en' placeholder='English...' style='width:650px;height:300px;'>".@$_POST["bemutatkozas_en"]."</textarea></td></tr>";
	//echo "<tr><td colspan=2 valign=top><input type='checkbox' value=1 name=aktiv".($_POST["aktiv"]==1?" checked":"")."> Aktív</td></tr>";

	echo "</table>";


	echo "<br><input type='submit' name='townmentes' value='Mentés'> ";
	echo "<input type='submit' name='scancel' value='Vissza'> ";

/*
	echo "<div style='margin-top:20px;padding-top:10px;border-top:1px dashed #ccc;'>";

	
	$resp=sql_query("select * from hirfiles where mid='".addslashes($_GET["szerk"])."' and miez=10 order by picid desc");
	echo  "<a name=kepek></a>Képfeltöltés: &nbsp;<input size=50 type=file name=kepfile> <input type='submit' name='townmentes' value='Feltöltés'><br><br>";
	$x=0;
	while ($rowp=sql_fetch_array($resp)) {
		echo  "<div align=center style='border:1px solid #ccc;float:left;padding:5px;font-size:11px;margin-right:10px;margin-bottom:10px;'>";
		$exten=".jpg";
		$p=$rowp["picid"];
		$kepfile="{$uploadbasepath}/{$_GET["szerk"]}/{$p}{$exten}";
		$thumbfile="{$uploadbasepath}/{$_GET["szerk"]}/tn_{$p}{$exten}";
	
		if (substr($rowp["filename"],-4)==".jpg") {
			echo  "<a href='{$kepfile}' target='fkep'><img height='150' border='0' src='{$thumbfile}'></a>";
		} else {
			echo "<div style='height:150px;'><div style='font-size:28px;'><b>DOC</b></div><div>{$rowp["filename"]}</div></div>";
		}
		//echo  "<div><b>kis kép url:</b> {$domain}/$thumbfile</div>";
		//echo  "<div><b>nagy kép url:</b> {$domain}/$kepfile</div>";
		
		echo  "<input type='hidden' name='picid{$x}' value='{$p}'>";
		echo  "<div>[<a onclick='javascript:return confirm(\"Biztos törlöd ezt a képet?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delpicid={$p}'>törlés</a>]</div>";
		echo  "</div>";
		$x++;
	}
	echo  "<br clear=all>";

	echo  "</div>";
	*/
	
	
	echo "</form>";
	echo "</div>";
}


if (!isset($_GET["szerk"])) {

	$szin="#dddddd";
	
	$res=sql_query("SELECT * from towns");

	echo "<table cellpadding=0 cellspacing=0 border=0>";
	while ($row=sql_fetch_array($res)) {
		$tc="tcella";
		if (!isset($first)) {
			echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
			$first=1;
		}
		echo "<tr>";
		//echo "<td nowrap valign=top><div class={$tc}>".substr($row["datum"],0,16)."&nbsp;&nbsp;</div></td>";
		//echo "<td nowrap valign=top><div class={$tc}>".$cikktipusok[$row["tipus"]]."&nbsp;&nbsp;</div></td>";
		echo "<td nowrap valign=top><div class={$tc} style='min-width:300px;'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["townname"]}</a></div></td>";
		//echo "<td nowrap valign=top><div class={$tc}>".($row["aktiv"]==1?"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oinaktiv={$row["id"]}' style='color:#0a0;'>aktív</a>":"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktiv={$row["id"]}' style='color:#f00;'>inaktív</a>")."</div></td>";
		echo "<td nowrap valign=top><div class={$tc}>[<a onclick='alert(\"Törtlés tiltva!\");return false;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
		echo "</tr>";
		echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
	}
	echo "</table>";

}





?>