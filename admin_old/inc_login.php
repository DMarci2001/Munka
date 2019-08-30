<?PHP


$htmlout="";

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
$htmlout.="<h1>{$_SESSION["helyszindata"]["megnev"]} orvosi felület</h1>";

$htmlout.="<div id='loginbox' style='color:#444;'>";
$htmlout.="<form method='post'>";
$htmlout.="<div>Felhasználónév:<br><input type=text name='loginusername'></div>";
$htmlout.="<div style='padding-top:5px;'>Jelszó:<br><input type='password' name='loginpassword' /></div>";
$htmlout.="<div style='padding-top:10px;'><input type='submit' name='logintry' value='Belépés' /></div>";
$htmlout.="</form>";

$htmlout.="<div style='margin-top:20px;'>";
$htmlout.="Ha nem emlékszik a jelszavára, az alábbi linkre kattintva új jelszót kérhet.<br/><a href='#' onclick='$(\"#loginbox\").slideToggle();$(\"#forgetbox\").slideToggle();$(\"#errordiv\").slideUp();return false;'>Új jelszó kérése</a>";
$htmlout.="</div>";
	
$htmlout.="</div>";



$htmlout.="<div id='forgetbox' style='color:#444;display:none;'>";
$htmlout.="<form method='post'>";
$htmlout.="<div style='margin-top:0px;'>Kérjük adja meg az e-mail címét, vagy felhasználónevét.Az új jelszavát a regisztrált e-mail címére fogjuk elküldeni.</div>";

$htmlout.="<div style='margin-top:5px;'><input type='text' name='email' placeholder='E-mail cím, vagy felhasználónév' style='width:300px;'></div>";
$htmlout.="<div style='padding-top:10px;'><input type='submit' name='passwordsend' value='Új jelszó kérése' /></div>";
$htmlout.="</form>";

$htmlout.="<div style='margin-top:20px;'>";
$htmlout.="<a href='#' onclick='$(\"#loginbox\").slideToggle();$(\"#forgetbox\").slideToggle();$(\"#errordiv\").slideUp();return false;'>Bejelentkezés</a>";
$htmlout.="</div>";
$htmlout.="</div>";



$htmlout.="</div>";
$htmlout.="</body>";
$htmlout.="</html>";


echo $htmlout;
ob_flush();
die();

?>