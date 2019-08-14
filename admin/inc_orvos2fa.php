<?PHP

$htmlout="";


if (sql_fetch_array(sql_query("select * from users where (logincodetime<date_sub(now(),interval 1 hour) or logincodephone<>?) and id=?",array($user["tel"],$user["id"])))) {
    include("../includes/seeme-gateway-class.php");
    $code=rand(10000,99999);
    sendSMS($user["tel"],"kód a bejelentkezéshez: {$code}");
    sql_query("update users set logincode=?,logincodetime=now(),logincodephone=? where id=?",array($code,$user["tel"],$user["id"]));
}


if (isset($loginerror) || isset($_SESSION["error"])) {
    if (isset($_SESSION["error"])) {
        $loginerror=$_SESSION["error"];
        unset($_SESSION["error"]);
    }
    $htmlout.="<div id='errordiv' style='background:#f00;padding:10px;font-weight:bold;color:#fff;text-align:center;'>{$loginerror}</div>";
}


if (isset($_SESSION["passwordsent"])) {
    unset($_SESSION["passwordsent"]);
    $htmlout.="<div id='errordiv' style='background:#0a0;padding:10px;font-weight:bold;color:#fff;text-align:center;'>Az új jelszavát a megadott e-mail címre elküldtük.</div>";
}
$htmlout.="<div style='padding-top:30px;text-align:center;'>";
$htmlout.="<h1>Kétfaktoros authentikáció</h1>";

$htmlout.="<div id='loginbox' style='color:#444;'>";
$htmlout.="<form method='post'>";
$htmlout.="<div>Adja meg az SMS-ben kapott kódot:</div>";
$htmlout.="<div style='padding-top:5px;'><input type='text' name='login2facode' /></div>";
if  (trim($user["tel"])!="") {
    $htmlout.="<div style='padding-top:5px;'>Az SMS-t a {$user["tel"]} számra küldtük ki. Amennyiben a szám nem helyes, kérjük lépjen kapcsolatba a rendszergazdával.</div>";
} else {
    $htmlout.="<div style='padding-top:5px;color:#f00;'>Önnek nincs megadva a telefonszáma amire kiküldhetjük a kódot, kérjük lépjen kapcsolatba a rendszergazdával.</div>";
}
$htmlout.="<div style='padding-top:10px;'><input type='submit' name='give2facode' value='Tovább' /> <input onclick='window.location.href=\"index.php?logoutadmin\"' type='button' name='cancel2facode' value='Mégse' /></div>";
$htmlout.="</form>";


$htmlout.="</div>";


$htmlout.="</div>";
$htmlout.="</body>";
$htmlout.="</html>";
echo $htmlout;
die();


?>
