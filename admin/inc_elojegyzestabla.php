<?php


if (!isset($_SESSION["helyszin"])) $_SESSION["helyszin"]=0;
if (!isset($_SESSION["helyszinceg"])) $_SESSION["helyszinceg"]=0;
if (!isset($_SESSION["naptarszurestipus"])) $_SESSION["naptarszurestipus"]=0;
if (!isset($_SESSION["ecegfilter"])) $_SESSION["ecegfilter"]=0;
if (!isset($_SESSION["setday"])) $_SESSION["setday"]=date("Y-m-d");
if (isset($_GET["setday"])) $_SESSION["setday"]=$_GET["setday"];
if (isset($_GET["ecegfilter"])) $_SESSION["ecegfilter"]=$_GET["ecegfilter"];

$setday=$_SESSION["setday"];


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
echo "<select name='helyszin' onchange='setHelyszin2(this.value);'>";
echo "<option value='0'>Válassz helyszínt!</option>";


$res=sql_query("SELECT h.* FROM helyszinek h WHERE true ORDER BY trim(h.cim)");
while ($rowt=sql_fetch_array($res)) {
    if ($_SESSION["adminuser"]["jogosultsag"]<2) {
        if (isset($mehet)) unset($mehet);
        $cegidk=explode("|",$_SESSION["adminuser"]["cegjog"]);
        foreach ($cegidk as &$val) {
            if (substr_count($rowt["ceglink"],"|{$val}|") && $val!="") {
                $mehet=1;
                break;
            }
        }
        if (!isset($mehet)) continue;
    }
    echo "<option value='{$rowt["id"]}-0'".("{$_SESSION["helyszin"]}-0"=="{$rowt["id"]}-0"?" selected":"").">{$rowt["cim"]}</option>";
}


echo "</select>";
echo "</div>";



echo "</div>";

echo "<div style='display:table-cell;vertical-align:top;padding:3px 0px 0px 20px;'>";
if (isset($_SESSION["helyszin"]) && $_SESSION["helyszin"]!=0) {
}
echo "</div>";


echo "</div>";
echo "</div>";




$jogW="AND b.cegid='".intval($_SESSION["helyszinceg"])."'";


if (!isset($_SESSION["helyszin"]) || $_SESSION["helyszin"]==0) return;

echo "<div id='elojegyzestable'>".showElojegyzesTable($setday)."</div>";
echo "<div id='idoponteditor' style='position:fixed;bottom:0px;right:0px;background:#e0e0e0;display:none;'></div>";


//echo "</div>";

