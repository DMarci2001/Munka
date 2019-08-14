<?PHP



session_start();

require_once "../config.php";

error_reporting(E_ALL);
ini_set('display_errors',1);


header("Content-type: text/html; charset=UTF-8");
require_once "ajax.php";


if (!isset($_GET["id"]) || !isset($_GET["token"])) {
	die("error code 132");
} 


$id=intval($_GET["id"]);
$token=$_GET["token"];

if (!ctype_alnum($token)) die("error code 432");


if (!$data=sql_fetch_array(sql_query("select * from foglalasok where id='{$id}' and md5(concat(datum,regdatum))='{$token}'"))) die("error code 1254");

$sorkoz=10;

echo "<html>";
echo "<head>";
echo "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
echo "<title>Alkalmassági igazolás</title>";
echo "<script>
function selfPrint() {
	//return;
	self.print();
	self.close();
	//window.history.back();
}
</script>";
echo "</head>";
echo "<body onload='selfPrint()'>";


echo "<div style='width:700px;'>";

echo "<div style='text-align:right;'><img style='width:200px;' src='images/hmed_pecset_ff.jpg' alt='' /></div>";
echo "<div style='margin-top:-40px;'>";

echo "<div style='padding-top:-80px;'>Foglalkozás-egészségügyi szolgálat megnevezése:</div>";
echo "<h2 style='text-align:center;'>Elsőfokú munkaköri alkalmassági vélemény</h2>";

echo "<div>A vizsgálat eredménye alapján <b>{$data["nev"]}</b> munkavállaló</div>";

echo "<div style='margin-top:{$sorkoz}px;'>Születési dátum: <b>{$data["szuldatum"]}</b></div>";

echo "<div style='display:table;margin:20px 0px;width:100%;'>";


$keretstyle="border:1px solid #000;display:inline-block;padding:2px 5px;";

echo "<div style='display:table-cell;text-align:center;'><div style='".($data["alkalmassag"]=="I"?"{$keretstyle}":"")."'>ALKALMAS</div></div>";
echo "<div style='display:table-cell;text-align:center;'><div style='".($data["alkalmassag"]=="IN"?"{$keretstyle}":"")."'>IDEIGLENESEN NEM ALKALMAS</div></div>";
echo "<div style='display:table-cell;text-align:center;'><div style='".($data["alkalmassag"]=="N"?"{$keretstyle}":"")."'>NEM ALKALMAS</div></div>";

echo "</div>";

echo "<div style='margin-top:{$sorkoz}px;'>Nevezett munkaköri alkalmasságát érintő korlátozás: <b>".($data["alkalmassagkorl"]!=""?"{$data["alkalmassagkorl"]}":"______________________________________")."</b></div>";

echo "<div style='margin-top:{$sorkoz}px;'>Ideiglenesen nem alkalmas minősítés esetén a legközelebbi vizsgálat ideje <b>".($data["alkalmassagikhet"]!=""?"{$data["alkalmassagikhet"]}":"____________")."</b> hét múlva.</div>";
echo "<div style='margin-top:{$sorkoz}px;'>Tüdőszűrés érvényessége: <b>".($data["tudoszuroervenyesseg"]!=""?"{$data["tudoszuroervenyesseg"]}":"____________")."</b></div>";

echo "<div style='margin-top:{$sorkoz}px;'>Kiadva: ".datumki(date("Y-m-d"))."</div>";

if ($data["alkalmassag"]=="I") {
	$ido=intval($data["alkalmassagido"]);
	
	echo "<div style='margin-top:{$sorkoz}px;'>Érv: ".datumki(date("Y-m-d",strtotime("now +{$ido} month")))."</div>";
}
echo "<div style='margin-top:{$sorkoz}px;'>Kelt: ".datumki(substr($data["datum"],0,10))."</div>";


echo "<div style='float:right;width:300px;text-align:center;'>";
echo "________________________<br/>véleményező orvos";
echo "</div>";
echo "<br clear='all'/>";


echo "</div>";
echo "</div>";


echo "</body>";
echo "</html>";


function datumki($datum) {
	$d=str_replace("-",".",$datum);
	return $d;
}

?>