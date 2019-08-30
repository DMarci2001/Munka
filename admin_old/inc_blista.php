<?php

ob_start();

$h="";

if (!isset($_SESSION["blistanap"])) $_SESSION["blistanap"]=date("Y-m-d");
if (isset($_GET["today"])) $_GET["blistanap"]=date("Y-m-d");
if (isset($_GET["blistanap"])) $_SESSION["blistanap"]=date("Y-m-d",strtotime($_GET["blistanap"]));


if (isset($_GET["filtercegid"])) {
	$_SESSION["filtercegid"]=$_GET["filtercegid"];
	$_SESSION["filterszurestipus"]=-1;
	$_SESSION["filternap"]=$_SESSION["blistanap"];
	$_SESSION["filternev"]="";
}
if (isset($_GET["filterhelyszinid"])) {
    $_SESSION["filterhelyszinid"]=$_GET["filterhelyszinid"];
    $_SESSION["filterszurestipus"]=-1;
    $_SESSION["filternap"]=$_SESSION["blistanap"];
    $_SESSION["filternev"]="";
}
if (isset($_GET["filterorvos"])) {
	$_SESSION["filterorvos"]=$_GET["filterorvos"];
	$_SESSION["filterszurestipus"]=-1;
	$_SESSION["filternap"]=$_SESSION["blistanap"];
	$_SESSION["filternev"]="";
}
if (isset($_GET["filterszurestipus"])) {
	$_SESSION["filterszurestipus"]=$_GET["filterszurestipus"];
	$_SESSION["filterorvos"]=-1;
	$_SESSION["filternap"]=$_SESSION["blistanap"];
	$_SESSION["filternev"]="";
}
if (isset($_GET["filternev"])) {
	$_SESSION["filternev"]=$_GET["filternev"];
	$_SESSION["filterszurestipus"]=-1;
	$_SESSION["filterorvos"]=-1;
	$_SESSION["filternap"]=$_SESSION["blistanap"];
}

if (isset($_SESSION["filternap"]) && $_SESSION["filternap"]!=$_SESSION["blistanap"]) {
	if (isset($_SESSION["filterszurestipus"])) unset($_SESSION["filterszurestipus"]);
	if (isset($_SESSION["filterorvos"])) unset($_SESSION["filterorvos"]);
	if (isset($_SESSION["filtercegid"])) unset($_SESSION["filtercegid"]);
	if (isset($_SESSION["filternev"])) unset($_SESSION["filternev"]);
}


$datumtol=$_SESSION["blistanap"]." 00:00:00";
$datumig=$_SESSION["blistanap"]." 23:59:59";

echo "<div style='display:inline-block;vertical-align:middle;'>";
echo "<div style='display:table-cell;vertical-align:middle;background:#eee;padding:10px;'>";
echo "<input type='text' value='{$_SESSION["blistanap"]}' name='blistanap' id='blistanap' style='width:85px;font-size:16px;' /> <input onclick='window.location.href=\"{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&blistanap=\"+$(\"#blistanap\").val();' type='button' value='OK'/> <input onclick='window.location.href=\"{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&today\"' type='button' value='Ma'/>";
echo "</div>";

echo "<div style='display:table-cell;vertical-align:middle;background:#eee;padding:10px;'>";

$nextday=date("Y-m-d",strtotime("{$datumtol} + 1 day"));
$prevday=date("Y-m-d",strtotime("{$datumtol} - 1 day"));
echo "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&blistanap={$prevday}'><img height='15' src='images/prev.png' title='Előző nap' style='margin-left:10px;'/></a>";
echo "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&blistanap={$nextday}'><img height='15' src='images/next.png' title='Következő nap' style='margin-left:10px;'/></a>";
echo "</div>";

echo "<div style='display:table-cell;vertical-align:middle;padding-left:20px;'><a href='//{$_SERVER["HTTP_HOST"]}{$_SERVER["REQUEST_URI"]}&downloadcsv'>Táblázat letöltése</a></div>";
echo "<div style='display:table-cell;vertical-align:middle;padding-left:20px;'>#cegfilter#<br/>#helyszinfilter#</div>";
echo "<div style='display:table-cell;vertical-align:middle;padding-left:20px;'>#tipusfilter#<br/>#orvosfilter#</div>";
echo "<div style='display:table-cell;vertical-align:middle;padding-left:20px;'>#nevfilter#</div>";


echo "</div>";

$w=$bw="";
if ($user["jogosultsag"]<2) {
	$w="and f.cegid in (".getCegList($user["cegjog"]).")";
	//echo $w;
}

if (isset($_SESSION["filternev"]) && $_SESSION["filternev"]!="") {
	$w.=" and instr(f.nev,'".addslashes($_SESSION["filternev"])."')";
}


$wo="";


$res=sql_query("SELECT o.`nev` AS orvosnev,h.cim AS helyszin,sz.megnev AS szurestipus,f.*,b.naploszam,b.megj as beutalomegj,c.megnev as cegnev FROM foglalasok f
LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
left join cegek c on c.id=f.cegid
LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
left join beutalok b on b.foglalasid=f.id
LEFT JOIN orvosok o ON o.id=f.`orvosassigned`
WHERE f.datum>=? and f.datum<=? and f.aktiv=1 and f.technical=0 {$w} {$wo} and f.nev<>'Nincs név' order by datum desc",array($datumtol,$datumig));

$tc="tcella";
$colspan=7;

echo "<table cellpadding='0' cellspacing='0' border='0' style='margin-top:10px;min-width:600px;'>";
$date=substr($datumtol,0,10);
$wd=date("N",strtotime($date));  //day of week
$s="background:#eee;font-size:16px;padding:5px;";
if ($date==date("Y-m-d")) $s="background:#f88;color:#fff;font-size:16px;padding:5px;";
echo "#warnrow#";
echo "<tr><td colspan='{$colspan}' style='{$s}'>{$date} {$GLOBALS["hetnap"][$wd]}</td></tr>";

if (sql_num_rows($res)==0) {
	echo "<tr><td colspan='{$colspan}' class='{$tc}'>Erre a napra nincs foglalás</td></tr>";
} else {
	echo "<tr style='background:#eee;'>";
	echo "<td nowrap valign=top><div class='{$tc}'>&nbsp;&nbsp;Időpont</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'></div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>Naplószám&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>Paciens&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>Orvos&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>Helyszín&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'></td>";
	echo "</tr>";
}

function jnsFilter($s) {
	$s=strtolower(iconv("UTF-8","ISO-8859-2",$s));
	return $s;
}

$csv="";
$szurtLista=false;

while ($row=sql_fetch_array($res)) {	
	$szuresTipusokIdk[$row["szurestipusid"]]=$row["szurestipus"];
	$orvosIdk[$row["orvosassigned"]]=$row["orvosnev"];
	$cegIdk[$row["cegid"]]=$row["cegnev"];
	$helyszinIdk[$row["helyszinid"]]=$row["helyszin"];

	if (isset($_SESSION["filternev"]) && $_SESSION["filternev"]!="") {
		//if (substr_count(jnsFilter($row["nev"]),jnsFilter($_SESSION["filternev"]))==0) continue;
		//if (strpos(strtolower($row["nev"]), strtolower($_SESSION["filternev"])) === false) continue;
	}
	if (isset($_SESSION["filtercegid"]) && $_SESSION["filtercegid"]!=-1 && $_SESSION["filtercegid"]!=$row["cegid"]) {
	    $szurtLista=true;
		continue;
	}
	if (isset($_SESSION["filterorvos"]) && $_SESSION["filterorvos"]!=-1 && $_SESSION["filterorvos"]!=$row["orvosassigned"]) {
        $szurtLista=true;
		continue;
	}
    if (isset($_SESSION["filterszurestipus"]) && $_SESSION["filterszurestipus"]!=-1 && $_SESSION["filterszurestipus"]!=$row["szurestipusid"]) {
        $szurtLista=true;
        continue;
    }
    if (isset($_SESSION["filterhelyszinid"]) && $_SESSION["filterhelyszinid"]!=-1 && $_SESSION["filterhelyszinid"]!=$row["helyszinid"]) {
        $szurtLista=true;
        continue;
    }

	
	$szolg="";
	if ($rowa=sql_fetch_array(sql_query("select * from arak WHERE INSTR(cegid,?) AND tipusid=? and csomag=0",array("|{$row["cegid"]}|",$row["szurestipusid"])))) {
		$szolg.="<a href='#' onclick='showFizSzolg({$row["id"]});return false;'>+ fizetős szolgáltatás</a>";
	}
	
	echo "<tr><td colspan='{$colspan}' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
	
	echo "<tr>";
	//echo "<td nowrap valign=top><div class='{$tc}'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>".substr($row["datum"],0,16)."</a>&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>&nbsp;&nbsp;".substr($row["datum"],0,16)."&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}' style='width:20px;'>".($row["eljott"]==0?"":"<img height='15' src='images/check.png' alt='' title='Megérkezett' />")."</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>{$row["naploszam"]}&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'><div>{$row["nev"]}&nbsp;&nbsp;</div><div>".($row["beutalomegj"]!=""?" [<a href='#' onclick='$(\"#bmegj{$row["id"]}\").toggle();return false;'>megj</a>]":"")."&nbsp;&nbsp;</div></div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'><div id='orvoschangediv{$row["id"]}'>{$row["orvosnev"]}&nbsp;".(orvosModJog()?"<a onclick='$(\"#orvoschangediv{$row["id"]}\").load(\"index.php?loadorvoschangecombo&fid={$row["id"]}\");return false;' href='#'><img style='height:10px;opacity: .5;' src='images/refresh.png' title='orvos csere'/></a>":"")."&nbsp;</div><div>{$row["szurestipus"]}&nbsp;&nbsp;{$szolg}</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'><div>{$row["cegnev"]}&nbsp;&nbsp;</div><div>{$row["helyszin"]}&nbsp;&nbsp;</div></td>";
	echo "<td nowrap valign=top><div class='{$tc}'>";
	echo "[<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&togglemegerkezett={$row["id"]}'>megérkezett</a>] ";
	echo "</div></td>";
	echo "</tr>";

	$files=showPaciensFiles($row["id"]);

	if ($row["tudoszuro"]!=0) echo "<tr><td colspan='4'></td><td class='{$tc}' colspan='{$colspan}'><div style='background:#f00;color:#fff;display:inline-block;padding:2px 5px;'>Tüdőszűrés</div></td></tr>";
	if ($row["megj"]!="") echo "<tr><td colspan='4'></td><td class='{$tc}' colspan='{$colspan}'><div style='max-width:500px;'>{$row["megj"]}</div></td></tr>";
	echo "<tr><td colspan='4'></td><td class='{$tc}' colspan='{$colspan}'>".showPaciensFiles($row["id"])."</td></tr>";
	
	echo "<tr><td colspan='4'></td><td colspan='{$colspan}'><div id='fizszolglist{$row["id"]}'>".showFizSzolg($row["id"])."</div></td></tr>";
	echo "<tr id='bmegj{$row["id"]}' style='display:none;'><td colspan='3'></td><td colspan='{$colspan}'><div style='display:inline-block;background:#eee;padding:5px;margin:0px 0px 10px 0px;'>".nl2br($row["beutalomegj"])."</div></td></tr>";
	
	$csv.=substr($row["datum"],0,16).";";
	$csv.="{$row["naploszam"]};";
	$csv.=($files==""?"":"feltöltött beutalót").";";
	$csv.="{$row["nev"]};";
	$csv.="{$row["megj"]};";
	$csv.="{$row["orvosnev"]};";
	$csv.="{$row["szurestipus"]};";
	$csv.="{$row["cegnev"]};";
	$csv.="{$row["helyszin"]};";
	$csv.="\n";
}
echo "</table>";


$out=ob_get_contents();
ob_end_clean();

//$szuresTipusokIdk=array_unique($szuresTipusokIdk);


$c="<select onchange=\"window.location.href='index.php?page={$_GET["page"]}&blistanap={$_SESSION["blistanap"]}&filtercegid='+this.value;\" name='filterceg' style='width:300px;'>";
$c.="<option value='-1'>Szűrés cégre</option>";
if (isset($cegIdk)) {
	$cegIdk=array_unique($cegIdk);
	asort($cegIdk);
	foreach ($cegIdk as $key => $val) {
		if ($val!="") $c.="<option value='{$key}'".((isset($_SESSION["filtercegid"]) && $_SESSION["filtercegid"]==$key)?" selected":"").">{$val}</option>";
	}
}
$c.="</select>";
$out=str_replace("#cegfilter#",$c,$out);

$c="<select onchange=\"window.location.href='index.php?page={$_GET["page"]}&blistanap={$_SESSION["blistanap"]}&filterhelyszinid='+this.value;\" name='filterceg' style='width:300px;'>";
$c.="<option value='-1'>Szűrés helyszínre</option>";
if (isset($helyszinIdk)) {
    $cegIdk=array_unique($helyszinIdk);
    asort($helyszinIdk);
    foreach ($cegIdk as $key => $val) {
        if ($helyszinIdk!="") $c.="<option value='{$key}'".((isset($_SESSION["filterhelyszinid"]) && $_SESSION["filterhelyszinid"]==$key)?" selected":"").">{$val}</option>";
    }
}
$c.="</select>";
$out=str_replace("#helyszinfilter#",$c,$out);


$c="<select onchange=\"window.location.href='index.php?page={$_GET["page"]}&blistanap={$_SESSION["blistanap"]}&filterszurestipus='+this.value;\" name='filterszurestipus' style='width:200px;'>";
$c.="<option value='-1'>Szűrés tipusra</option>";
if (isset($szuresTipusokIdk)) {
	$szuresTipusokIdk=array_unique($szuresTipusokIdk);
	asort($szuresTipusokIdk);
	foreach ($szuresTipusokIdk as $key => $val) {
		if ($val!="") $c.="<option value='{$key}'".((isset($_SESSION["filterszurestipus"]) && $_SESSION["filterszurestipus"]==$key)?" selected":"").">{$val}</option>";
	}
}
$c.="</select>";
$out=str_replace("#tipusfilter#",$c,$out);



$c="<select onchange=\"window.location.href='index.php?page={$_GET["page"]}&blistanap={$_SESSION["blistanap"]}&filterorvos='+this.value;\" name='filterorvos' style='width:200px;'>";
$c.="<option value='-1'>Szűrés orvosra</option>";
if (isset($orvosIdk)) {
	$orvosIdk=array_unique($orvosIdk);
	asort($orvosIdk);
	foreach ($orvosIdk as $key => $val) {
		if ($val!="") $c.="<option value='{$key}'".((isset($_SESSION["filterorvos"]) && $_SESSION["filterorvos"]==$key)?" selected":"").">{$val}</option>";
	}
}
$c.="</select>";
$out=str_replace("#orvosfilter#",$c,$out);
$out=str_replace("#nevfilter#","<input onkeyup=\"if (event.which == 13){ window.location.href='index.php?page={$_GET["page"]}&blistanap={$_SESSION["blistanap"]}&filternev='+encodeURIComponent(this.value); }\" type='text' name='namefilter' value='".(isset($_SESSION["filternev"])? $_SESSION["filternev"]:"")."' placeholder='Szűrés névre' />",$out);


$warnRow="";
if ($szurtLista) {
    $warnRow="<tr><td colspan='7' style='background:#484;color:#fff;padding:5px 10px;font-weight: bold;'>Szűrt lista!</td></tr>";
}
$out=str_replace("#warnrow#",$warnRow,$out);


echo $out;







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