<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Utils {

    public function __construct()
    {

    }

    public function isTesztIP() {
        return in_array($_SERVER["REMOTE_ADDR"],array("88.151.97.121","81.182.23.124","5.204.54.10","81.182.23.106","194.143.226.42"));
    }

    public function sendUserSMSKod($userId)
    {
        if ($rowu = sql_fetch_array(sql_query("SELECT f.* FROM felhasznalok f 
	    LEFT JOIN cegek c ON c.id=f.cegid
	    WHERE f.id=? AND c.`noregsms`=0", array($userId)))) {
            $this->sendSMS($rowu["telefon"], "kód a regisztráció befejezéséhez: {$rowu["rkod"]}");
        } else {
            sql_query("update felhasznalok set validated=1 where id=?", array($userId));
        }
    }

    public function sendLoginSMSKod($userId)
    {
        if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where id='{$userId}'"))) {
            $this->sendSMS($rowu["telefon"], "kód a bejelentkezéshez: {$rowu["rkod"]}");
        }
    }


    function showDebugInfo($s)
    {
        if ($_SERVER["REMOTE_ADDR"] == "88.151.97.121") {
            echo "<div>{$s}</div>";
        }
    }



    function isBeosztasWeekDay($beosztas, $wd, $weekNumber = 0)
    {
        for ($i = 0; $i < count($beosztas); $i++) {
            if ($beosztas[$i]["nap"] == $wd) {
                if ($weekNumber == 0) return true;
                if ($beosztas[$i]["hetek"] == 2) {
                    if ($weekNumber % 2 == 0) {
                        return true;
                    } else {
                        return false;
                    }
                }
                if ($beosztas[$i]["hetek"] == 1) {
                    if ($weekNumber % 2 == 0) {
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

    function isFreeIdopont($wd, $ora, $beosztas, $hanyfoglalt)
    {
        $szabad[0] = 0;

        $dokik = 0;
        for ($i = 0; $i < count($beosztas); $i++) {
            $beo = $beosztas[$i];
            if ($beo["nap"] == $wd) {
                if (strtotime(date("Y-m-d {$ora}")) >= strtotime(date("Y-m-d {$beo["tol"]}")) && strtotime(date("Y-m-d {$ora}")) < strtotime(date("Y-m-d {$beo["ig"]}"))) {
                    $dokik++;

                    //csak sorban foglalható időpont ellenőrzése
                    if ($beo["csaksorban"] == 1) {
                        if (isset($GLOBALS["cs{$beo["id"]}"])) {
                            $szabad[0] = 2;
                            $szabad[1] = $GLOBALS["cs{$beo["id"]}"];
                        } else {
                            $GLOBALS["cs{$beo["id"]}"] = $ora;
                        }
                    }

                }
            }
        }
        if ($dokik > $hanyfoglalt) {
            if ($szabad[0] == 0) $szabad[0] = 1;
        } else {
            $szabad[0] = 0;
        }

        return $szabad;
    }

    public function showPaciensFiles()
    {
        $htmlout = "";
        if (isset($_SESSION["filefix"])) {
            $htmlout .= "<div style='margin:5px 0px;'>";
            $res = sql_query("select * from dokumentumok where sess=?", array($_SESSION["filefix"] . session_id()));
            //if (sql_num_rows($res)==0) $htmlout.="Az adminisztráció megkönnyítése érdekében a beutaló itt feltölthető";
            while ($row = sql_fetch_array($res)) {
                $htmlout .= "<div><div style='display:table-cell;vertical-align:middle;'><a href='#' onclick='deletePaciensFile({$row["id"]},\"{$row["kod"]}\");return false;'><img style='margin-right:5px;' src='/images/trash.png' /></a></div><div style='display:table-cell;vertical-align:middle;'>{$row["filename"]}</div></div>";
            }
            $htmlout .= "</div>";
        }
        return $htmlout;
    }

    public function cimLangQuery($fieldName = "cim")
    {
        $q = "h.cim AS {$fieldName}";
        if (isset($_COOKIE["lang"]) && in_array($_COOKIE["lang"], array("en", "de"))) {
            $q = "IF(h.cim_{$_COOKIE["lang"]}='',h.cim,h.cim_{$_COOKIE["lang"]}) AS {$fieldName}";
        }
        return $q;
    }

    public function datumSelector($date, $prefix, $future = 0, $class = null,$customJs="") {
        $lang = new Lang();
        $webText = $lang->webText;

        $h="";

        $ev  = substr($date,0,4);
        $ho  = substr($date,5,2);
        $nap = substr($date,8,2);

        $h.= "<select {$class} {$customJs} name='{$prefix}ev'>";
        $h.= "<option value='0'>{$webText["ev"]}</option>";
        if ($future == 0) {
            for ($i = date("Y"); $i > date("Y") - 100; $i--) {
                $h .= "<option value='{$i}'" . ($ev == $i ? " selected" : "") . ">{$i}</option>";
            }
        } else {
            for ($i = date("Y"); $i < date("Y") + $future; $i++) {
                $h .= "<option value='{$i}'" . ($ev == $i ? " selected" : "") . ">{$i}</option>";
            }
        }

        $h.= "</select> ";

        $h.= "<select {$class} {$customJs} name='{$prefix}ho'>";
        $h.= "<option value='0'>{$webText["ho"]}</option>";
        for ($i=1;$i<=12;$i++) {
            $h.= "<option value='{$i}'".($ho==$i?" selected":"").">{$webText["honaptext"][$i]}</option>";
        }
        $h.= "</select> ";

        $h.= "<select {$class} {$customJs} name='{$prefix}nap'>";
        $h.= "<option value='0'>{$webText["nap"]}</option>";
        for ($i=1;$i<=31;$i++) {
            $h.= "<option value='{$i}'".($nap==$i?" selected":"").">{$i}</option>";
        }
        $h.= "</select>";

        return $h;
    }

    public function fixPhoneNumber($tel) {
        $tel=str_replace("(","",$tel);
        $tel=str_replace(")","",$tel);
        $tel=str_replace("-","",$tel);
        $tel=str_replace("/","",$tel);
        $tel=str_replace("+","",$tel);
        $tel=str_replace(" ","",$tel);
        if (substr($tel,0,2)=="06") $tel="36".substr($tel,2);
        return $tel;
    }


    public function checkSzulDatum($datum) {
        $datum=str_replace("-","",$datum);
        $datum=str_replace(".","",$datum);
        $datum=str_replace(" ","",$datum);

        if (strlen($datum)!=8) return false;
        if (!is_numeric($datum)) return false;

        $ev=intval(substr($datum,0,4));
        $ho=intval(substr($datum,4,2));
        $nap=intval(substr($datum,6,2));

        if ($ev<1900 || $ev>date("Y")) return false;
        if ($ho<1 || $ho>12) return false;
        if ($nap<1 || $nap>31) return false;

        return true;
    }

    public function validateDate($date,$format="Y-m-d H:i:s") {
        $d=DateTime::createFromFormat($format, $date);
        return $d && $d->format($format)==$date;
    }


    function substr_jns($s,$p1,$p2) {
        $sz=iconv("UTF-8","ISO-8859-2",$s);
        $sz=substr($sz,$p1,$p2);
        $sz=iconv("ISO-8859-2","UTF-8",$sz);
        return $sz;
    }

    public function numToString($Mit) {
        $EgyesStr = array('', 'egy', 'kettő', 'három', 'négy', 'öt', 'hat', 'hét', 'nyolc', 'kilenc');
        $TizesStr = array('', 'tíz', 'húsz', 'harminc', 'negyven', 'ötven', 'hatvan', 'hetven', 'nyolcvan', 'kilencven');
        $TizenStr = array('', 'tizen', 'huszon', 'harminc', 'negyven', 'ötven', 'hatvan', 'hetven', 'nyolcvan', 'kilencven');
        $Result = '';
        if ($Mit == 0) {
            $Result = 'Nulla';
        } else {
            $Maradek = abs($Mit);
            if ($Maradek > 999999999999) {
                die("Túl nagy szám");
            }

            $Oszto=1000000000;
            $Osztonev="milliárd";
            if ($Maradek>=$Oszto) {
                if (mb_strlen($Result)>0) $Result = $Result . '-';
                $Mit=$Maradek/$Oszto;
                if ($Mit>=100) $Result = $Result.$EgyesStr[$Mit/100].'száz';
                $Mit = $Mit % 100;
                if ($Mit % 10 !== 0) {
                    $Result = $Result . $TizenStr[$Mit / 10] . $EgyesStr[$Mit % 10] . $Osztonev;
                } else {
                    $Result = $Result . $TizesStr[$Mit / 10] . $Osztonev;
                }
            }
            $Maradek=$Maradek % $Oszto;

            $Oszto=1000000;
            $Osztonev="millió";
            if ($Maradek>=$Oszto) {
                if (mb_strlen($Result)>0) $Result = $Result . '-';
                $Mit=$Maradek/$Oszto;
                if ($Mit>=100) $Result = $Result.$EgyesStr[$Mit/100].'száz';
                $Mit = $Mit % 100;
                if ($Mit % 10 !== 0) {
                    $Result = $Result . $TizenStr[$Mit / 10] . $EgyesStr[$Mit % 10] . $Osztonev;
                } else {
                    $Result = $Result . $TizesStr[$Mit / 10] . $Osztonev;
                }
            }
            $Maradek=$Maradek % $Oszto;

            $Oszto=1000;
            $Osztonev="ezer";
            if ($Maradek>=$Oszto) {
                if (mb_strlen($Result)>0) $Result = $Result . '-';
                $Mit=$Maradek/$Oszto;
                if ($Mit>=100) $Result = $Result.$EgyesStr[$Mit/100].'száz';
                $Mit = $Mit % 100;
                if ($Mit % 10 !== 0) {
                    $Result = $Result . $TizenStr[$Mit / 10] . $EgyesStr[$Mit % 10] . $Osztonev;
                } else {
                    $Result = $Result . $TizesStr[$Mit / 10] . $Osztonev;
                }
            }
            $Maradek=$Maradek % $Oszto;

            $Oszto=1;
            $Osztonev="";
            if ($Maradek>=$Oszto) {
                if (mb_strlen($Result)>0) $Result = $Result . '-';
                $Mit=$Maradek/$Oszto;
                if ($Mit>=100) $Result = $Result.$EgyesStr[$Mit/100].'száz';
                $Mit = $Mit % 100;
                if ($Mit % 10 !== 0) {
                    $Result = $Result . $TizenStr[$Mit / 10] . $EgyesStr[$Mit % 10] . $Osztonev;
                } else {
                    $Result = $Result . $TizesStr[$Mit / 10] . $Osztonev;
                }
            }
            $Maradek=$Maradek % $Oszto;

            /*
              Alakit($Maradek, 1000000000, 'milliárd');
              Alakit($Maradek, 1000000, 'millió');
              Alakit($Maradek, 1000, 'ezer');
              Alakit($Maradek, 1, '');
            */

            $Result = ucfirst($Result);
            if ($Mit<0) $Result = 'Mínusz ' . $Result;
        }

        return $Result;
    }

    public function getTajFromString($str) {
        preg_match_all('/\d+/', $str, $matches);
        foreach ($matches[0] as $val) {
            if (strlen($val)==9) return $val;
        }
        return "";
    }

    public function htmlheader($pageTitle = "Online bejelentkezés") {
        $subdomain = $_SESSION["helyszindata"]["domain"];
        if (isset($GLOBALS["subtitle"]) && !empty($GLOBALS["subtitle"])) {
            $pageTitle = "{$GLOBALS["subtitle"]} - $pageTitle";
        }

        $htmlout='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        $htmlout.='<html xmlns="http://www.w3.org/1999/xhtml">';
        $htmlout.='<head>';

        $v = "version".date("YmdHi");

        $htmlout.="<title>{$pageTitle}</title>";
        $htmlout.="<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />";
        $htmlout.='<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1" />';
        $favicon="/images/".Booking_Constants::SITE_FAVICON;
        if (is_file("images/logo_{$subdomain}.png") || is_file("../images/logo_{$subdomain}.png")) {
            $favicon="/images/logo_{$subdomain}.png";
        }

        $htmlout.="<link rel='shortcut icon' type='image/png' href='{$favicon}' />";
        $htmlout.='<script type="text/javascript" src="/js/jquery/jquery-3.7.1.min.js"></script>';
        $htmlout.='<script type="text/javascript" src="/js/jquery/jquery-ui.js"></script>';
        $htmlout.='<script type="text/javascript" src="/js/sweetalert/sweetalert2.min.js"></script>';
        $htmlout.='<link href="/js/air-datepicker-master/dist/css/datepicker.css" rel="stylesheet" type="text/css">';
        $htmlout.='<script src="/js/air-datepicker-master/dist/js/datepicker.min.js"></script>';
        $htmlout.='<script src="/js/air-datepicker-master/dist/js/i18n/datepicker.hu.js"></script>';
        $htmlout.="<script type='text/javascript' src='js/ajax.js?v={$v}'></script>";

        if (isset($_GET["page"]) && $_GET["page"] == "covidform") {
            $htmlout.="<script type='text/javascript' src='js/covidform.js?v={$v}'></script>";
        }

        if(isset($_GET["page"]) && $_GET["page"] == "psychosocialform") {
            $htmlout.="<script type='text/javascript' src='js/psychosocform.js'></script>";
        }

        if (isset($_GET["page"]) && $_GET["page"] == "oltasigenyfelmeres") {
            $htmlout.="<script type='text/javascript' src='js/oltasigenyform.js?v={$v}'></script>";
        }

        if (isset($_GET["page"]) && $_GET["page"] == "suzukiform") {
            $htmlout.="<script type='text/javascript' src='js/suzukiform.js?v={$v}'></script>";
        }

        if (isset($_GET["page"]) && $_GET["page"] == "elsosegelyvizsga") {
            $htmlout.="<script type='text/javascript' src='js/elsosegelyvizsga.js?v={$v}'></script>";
        }

        if(CompanyService::isSuzukiGHC() || CompanyService::isFiFi() || CompanyService::isAstostecCompany()){
            if(isset($_GET["page"]) && in_array($_GET["page"],array("registration","login","booking","registrationsuccessful"))){
                $htmlout .= "<link href= '/admin/bootstrap-5.3.0-dist/css/bootstrap.css' rel='stylesheet' type='text/css'>";
                $htmlout .= "<script src='/admin/bootstrap-5.3.0-dist/js/bootstrap.bundle.min.js'></script>";
            }
        }
        

        if (isset($GLOBALS["admin"])) {
            $htmlout .= '<link href="/admin/js/jquery.toast/jquery.toast.min.css" rel="stylesheet" type="text/css">';
            $htmlout .= '<script src="/admin/js/jquery.toast/jquery.toast.min.js"></script>';

            $htmlout .= "<link href= '/admin/bootstrap-5.3.0-dist/css/bootstrap.css' rel='stylesheet' type='text/css'>";
            $htmlout .= "<script src='/admin/bootstrap-5.3.0-dist/js/bootstrap.bundle.min.js'></script>";

            $htmlout .= '<link href="/admin/js/confirm/jquery-confirm.css" rel="stylesheet" type="text/css">';
            $htmlout .= '<script src="/admin/js/confirm/jquery-confirm.js"></script>';

            $htmlout .= '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
            $htmlout .= '<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';
            //$htmlout .= '<script src="https://cdn.tiny.cloud/1/6gy62135dsr0pjrg1jx08egwhvjyuhbo8a463re02bmikbzj/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>';

            $htmlout .= '<script src="/admin/js/tinymce/tinymce.min.js" referrerpolicy="origin"></script>';
            $htmlout .= '<script src="/admin/js/BrowserPrint-3.1.250.min.js"></script>';

            $htmlout .= '<link href="/admin/js/cropperjs-main/dist/cropper.css" rel="stylesheet">';
            $htmlout .= '<script src="/admin/js/cropperjs-main/dist/cropper.js"></script>';
        } else {
            $htmlout .= '<link href="/chat/chatStyle.css" rel="stylesheet" type="text/css">';
            $htmlout .= '<script src="/chat/chatJs.js"></script>';
        }

        //$htmlout .= "<link href= '/admin/bootstrap-5.3.0-dist/css/bootstrap.css' rel='stylesheet' type='text/css'>";
        //$htmlout .= "<script src='/admin/bootstrap-5.3.0-dist/js/bootstrap.bundle.min.js'></script>";

        $htmlout.="<script src='https://www.google.com/recaptcha/api.js?hl={$_COOKIE["lang"]}'></script>";
        $htmlout.="<link rel='stylesheet' type='text/css' href='css/index.css?v={$v}' />";

        if (isset($GLOBALS["css"])) {
            foreach ($GLOBALS["css"] as $css) {
                $htmlout.="<link rel='stylesheet' type='text/css' href='css/{$css}?v={$v}' />";
                
            }
        }
        if (isset($GLOBALS["javascript"])) {
            foreach ($GLOBALS["javascript"] as $js) {
                $htmlout.="<script type='text/javascript' src='js/{$js}?v={$v}'></script>";
            }
        }
		$htmlout.='<link rel="stylesheet" href="/css/fontawesome-free-6.2.1-web/css/all.css" />';
        $htmlout.='<link rel="stylesheet" href="/js/jquery/jquery-ui.css">';
        $htmlout.='<link rel="stylesheet" href="/js/sweetalert/sweetalert2.css" type="text/css" />';
        $htmlout.="<link rel='stylesheet' href='/images/webfonts/roboto_regular_hungarian/stylesheet.css' type='text/css' charset='utf-8' async/>";
        $htmlout.="<link rel='stylesheet' href='/images/webfonts/roboto_bold_hungarian/stylesheet.css' type='text/css' charset='utf-8' async/>";
        $htmlout.="<link rel='stylesheet' href='/images/webfonts/roboto_light_hungarian/stylesheet.css' type='text/css' charset='utf-8' async/>";

        if (!isset($_GLOBALS["admin"])) {
            if (Booking_Constants::SQL_DB == "hungariamed") {
                $htmlout.='<meta name="facebook-domain-verification" content="rwr3rpdmypu9vnv6jqwyuxvhpgxisw" />';
                $htmlout.="<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '944162703126175');
fbq('track', 'PageView');
</script>
<noscript><img height=\"1\" width=\"1\" style=\"display:none\"
src=\"https://www.facebook.com/tr?id=944162703126175&ev=PageView&noscript=1\"
/></noscript>
<!-- End Meta Pixel Code -->
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-P89C75S');</script>
<!-- End Google Tag Manager -->";

            }
        }


        $htmlout.="</head>";
        return $htmlout;
    }

    public function setupLongSession() {
        //$sessionUp = 2; //óra
        //ini_set('session.gc_maxlifetime', $sessionUp*60*60);
        //session_set_cookie_params($sessionUp*60*60);

        $_SESSION["LAST_ACTIVITY"] = time();
    }

    public function sendSMS($num, $szoveg, $raw = false) {
        $num = str_replace(" ","",$num);
        $num = str_replace("-","",$num);
        $num = str_replace("/","",$num);
        $num = str_replace("(","",$num);
        $num = str_replace(")","",$num);
        $num = str_replace("+","",$num);

        if (!$raw) {
            if (substr($num, 0, 2) == "06") {
                $num = "36" . substr($num, 2);
            }
            if (substr($num, 0, 2) != "36") {
                $num = "36" . $num;
            }
        }

        $SeeMe = new SeeMeGateway(Booking_Constants::SEEME_API_KEY);

        try {
            $SeeMe->sendSMS($num, $szoveg);
        } catch (SeeMeGatewayException $e) {
            //print_r($SeeMe->getResult());
            //die();
        }
        $result = $SeeMe->getResult();
        //print_r($result);

        @sql_query("insert into smslog set datum=now(),tel=?,szoveg=?,result=?",array($num,$szoveg,print_r($SeeMe->getResult(),true)));

        return $result["result"]=="OK";
    }


    public function ENS($companies) {
        foreach ($companies as $company) {
            $query = sql_query( "SELECT felh.alklejarat,felh.nev,c.domain,felh.taj,felh.email AS umail,felh.id AS userid, felh.hrmail 
							 FROM felhasznalok felh
							 LEFT JOIN cegek c ON c.id = felh.cegid
							 WHERE cegid = {$company}
							 AND felh.alklejarat >= NOW() AND felh.alklejarat < ADDDATE(NOW(),14)
							 AND CASE WHEN felh.lastalkert IS NOT NULL 
							 THEN felh.lastalkert NOT BETWEEN ADDDATE(NOW(),-14) AND ADDDATE(NOW(),14) 
							 ELSE TRUE 
							 END");

            while ($result = sql_fetch_array($query)) {
                $checkFoglalas = sql_query("SELECT * FROM foglalasok WHERE email=? AND taj=? AND datum>=NOW() AND datum<ADDDATE(NOW(),14)", array($result['umail'], $result['taj']));
                if ($checkFoglalas->rowCount() == 0) {
                    $mail = NotificationService::getDefaultMailer();
                    $mail->AddAddress(iconv("UTF-8","ISO-8859-2",$result['umail']));
                    if ($result['hrmail'] != "") {
                        $mail->AddAddress(iconv("UTF-8","ISO-8859-2",$result['hrmail']));
                    }

                    $subject = "Orvosi alkalmassági vizsgálata hamarosan lejár!";

                    $mbody = "Kedves {$result['nev']},<br/>";
                    $mbody.= "Az orvosi alkalmassági vizsgálata hamarosan lejár!<br/>";
                    $mbody.= "Lejárat dátuma: ".date("Y.m.d",strtotime($result['alklejarat']))."<br/>";
                    $mbody.= "Kérem foglaljon időpontot honlapunkon:<br/>";
                    $mbody.= "<a href='".Booking_Constants::SITE_PROTOCOL."://".$result['domain'].".".Booking_Constants::SITE_DOMAIN."'>".Booking_Constants::SITE_PROTOCOL."://".$result['domain'].".".Booking_Constants::SITE_DOMAIN."</a><br/>";
                    $mbody.= "Tisztelettel,<br/>";
                    $mbody.= Booking_Constants::COMPANY_NAME;

                    $mail->Subject = $subject;
                    $mail->Body = $mbody;
                    if (!$mail->Send()) {
                        sql_query("INSERT INTO alkert_mail SET nev = '{$result['nev']}', email = '{$result['umail']}', eredmeny = '{$mail->ErrorInfo}', datum = NOW() ");
                    } else {
                        sql_query("INSERT INTO alkert_mail SET nev = '{$result['nev']}', email = '{$result['umail']}', eredmeny = 'elkuldve', datum = NOW() ");
                        sql_query("UPDATE felhasznalok SET lastalkert = NOW() WHERE id = {$result['userid']} ");
                    }
                }
            }
        }
    }

    private function getWeeks( $date, $rollover ) {
        $cut = substr( $date, 0, 8 );
        $daylen = 86400;

        $timestamp 	= strtotime( $date );
        $first 		= strtotime( $cut . "00" );
        $elapsed	= ( $timestamp - $first ) / $daylen;

        $weeks = 1;

        for ($i = 1; $i <= $elapsed; $i++) {
            $dayfind = $cut.(strlen( $i ) < 2 ? '0' . $i : $i);
            $daytimestamp = strtotime($dayfind);

            $day = strtolower(date("l", $daytimestamp));

            if ($day == strtolower($rollover)) {
                $weeks ++;
            }
        }

        return $weeks;
    }

    public function azonositotipusCheck($string){
        $type="";
        //$type=2 TAJ
        //$type=4 Útlevél

        if(ctype_digit($string) && strlen($string)==9){
            $type = 2;
        }else{
            $type = 4;
        }

        return $type;
    }


    public function dataField($fieldName,$RequiedForced=false,$customJs="") {
        $lang = new Lang();
        $webText = $lang->webText;

        $translateKey = $fieldName;
        $field        = $fieldName;
        $html         = "";
        $extraHTML    = "";
        $extraRow     = "";
        $width        = 250;
        $required     = $this->getFieldRequired($field);
        $hidden       = $this->getFieldHidden($field);
        $inputMode    = "";

        switch ($fieldName) {
            case "taj":
                $translateKey = "tajszam";
                //$inputMode = "inputmode='numeric' oninput=\"this.value = this.value.replace(/\D+/g, '')\"";
                break;
            case "email":
                if ($_SESSION["helyszindata"]["visszaigazolas"] == 1) {
                    $extraRow = "<tr class='datarow'><td></td><td>{$webText["kerjukugyeljenemail"]}</td></tr>";
                }
                break;
            case "nev":
                break;
            case "telefon":
                $translateKey = "mobil";
                //$extraRow = "<tr><td></td><td>{$webText["mobiltip"]}</td></tr>";

                if ($_SESSION["helyszindata"]["id"] == CompanyService::ASTOTEC_ID) {
                    $inputMode = "inputmode='numeric' oninput=\"this.value = this.value.replace(/\D+/g, '')\"";
                    $value = $_POST[$field];
                    $korzetszamok = [20, 30, 31, 70];
                    $extraHTML.= "<tr class='datarow'>";
                    $extraHTML.= "<td>{$webText[$translateKey]}: #requiredmark#</td>";
                    $extraHTML.= "<td>";
                    $extraHTML.= "+36 <select class='inputbox' style='width:50px;' type='text' name='korzetszam' />";
                    foreach ($korzetszamok as $val) {
                        $extraHTML.= "<option value='{$val}' ".($_POST["korzetszam"] == $val? "selected":"").">{$val}</option>";
                    }
                    $extraHTML.= "</select>&nbsp;";

                    $extraHTML.= "<input class='inputbox' {$inputMode} style='width:100px;' type='text' name='{$field}' id='{$field}' value='{$value}' />";

                    $extraHTML.= "</td>";
                    $extraHTML.= "</tr>";
                }

                break;
            case "szuldatum":
                $extraHTML = "<tr class='datarow'><td>{$webText["szuletesidatum"]}: #requiredmark#</td><td>".$this->datumSelector($_POST["szuldatum"],"szuldatum",0,null,$customJs)."</td></tr>";
                break;
            case "szulhely":
                //if ($_SESSION['helyszindata']['id'] == 46) {
                //    $hidden = true;
                //}
                $translateKey = "szuletesihely";
                break;
            case "neme":
                $extraHTML = "<tr class='datarow'><td>{$webText["neme"]}: #requiredmark#</td><td><input type='radio' {$customJs} name='neme' value='1' ".($_POST["neme"]==1?"checked":"")."/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' {$customJs} name='neme' value='2' ".($_POST["neme"]==2?"checked":"")."/> {$webText["no"]} </td></tr>";
                break;
            case "anyjaneve":
                //if ($_SESSION['helyszindata']['id'] == 46) {
                //    $hidden = true;
                //}
                break;
            case "irsz":
                $inputMode = "inputmode='numeric' oninput=\"this.value = this.value.replace(/\D+/g, '')\"";
                $width = 60;
                //if ($_SESSION['helyszindata']['id'] == 46) {
                //    $hidden = true;
                //}
                break;
            case "varos":
                //temp 1
                //if ($_SESSION['helyszindata']['id'] == 46) {
                //    $hidden = true;
                //}
                break;
            case "utca":
                //temp 2
                //if ($_SESSION['helyszindata']['id'] == 46) {
                //    $hidden = true;
                //}
                break;
            case "munkakor":
                if (!in_array($_SESSION["helyszindata"]["domain"], ["bejelentkezes", "gyor-bejelentkezes"])) {
                    //$hidden = true;
                }
                if (CompanyService::isFesztivalCompany($_SESSION["helyszindata"]["id"])) {
                    //forced default
                    $_POST[$field] = "rendezvény kisegítő";
                }
                if($_SESSION["helyszindata"]["domain"]=="bp"){
                    if(empty($_POST[$field]) || !isset($_POST[$field])){
                        $_POST[$field] = "Képernyő előtti szellemi munkavégzés";
                    }
                }
                if($_SESSION["helyszindata"]["domain"]=="fgsz"){
                    $q = sql_query("SELECT * FROM kockazati_tenyezok WHERE cegid=? ORDER BY munkakor ASC",array(220));
                    $extraHTML.= "<tr class='datarow'>";
                    $extraHTML.= "<td>{$webText[$translateKey]}: #requiredmark#</td>";
                    $extraHTML.= "<td>";
                    $extraHTML.= "<select class='inputbox' style='width:{$width}px;' type='text' name='{$field}' value='{$_POST[$field]}' />";
                    while($r=sql_fetch_array($q)){
                        $extraHTML.= "<option value=\"{$r["munkakor"]}\">{$r["munkakor"]}</option>";
                    }
                    $extraHTML.= "</select>";
                    $extraHTML.= "</td>";
                    $extraHTML.= "</tr>";
                }

                if ($_SESSION["helyszindata"]["id"] == CompanyService::ASTOTEC_ID){
                    $extraHTML.= "<tr class='datarow'>";
                    $extraHTML.= "<td>{$webText[$translateKey]}: #requiredmark#</td>";
                    $extraHTML.= "<td>";
                    $extraHTML.= "<select class='inputbox' style='width:{$width}px;' type='text' name='{$field}' />";
                    $extraHTML.= "<option value=''>Válasszon!</option>";
                    foreach ($this->astotecMunkakorok as $val) {
                        $extraHTML.= "<option value='{$val}' ".($_POST[$field] == $val? "selected":"").">{$val}</option>";
                    }
                    $extraHTML.= "</select>";
                    $extraHTML.= "</td>";
                    $extraHTML.= "</tr>";
                }
                break;
            case "torzsszam":
                if ($_SESSION["helyszindata"]["id"] == CompanyService::ASTOTEC_ID) {
                    $inputMode = "inputmode='numeric' oninput=\"this.value = this.value.replace(/\D+/g, '')\"";
                }
                if ($_SESSION["helyszindata"]["domain"]=="bp-teszt" || $_SESSION["helyszindata"]["domain"]=="bp"){
                    $webText[$translateKey] = "NTID";
                }
                break;
            case "adoszam":
                //adószám csak 1 cégnek
                if (!CompanyService::isFesztivalEgyeb()) {
                    $hidden = true;
                }
                break;
        }

        if($_SESSION["helyszindata"]["id"]==200 && $fieldName=="email"){
            $jsCall = "onfocusout=\"uniqaEmailCheck($(this).val())\" onClick=\"uniqaServiceCheck()\"";
            $extraNameTag = "Céges";
        }else{
            $jsCall = $extraNameTag = "";
            
        }
        
        if((CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser()) && $fieldName=="taj"){
            $jsCall = "onfocusout='checkWhiteList($(this).val())'";
        }

        if(CompanyService::isSuzukiGHC() && $fieldName=="taj"){
            $jsCall = $customJs;
        }

        if(CompanyService::isALDI() && $fieldName=="taj"){
            $value = $_POST[$field];
            $inputMode = "inputmode='numeric' oninput=\"this.value = this.value.replace(/\D+/g, '')\" maxlength='9'";
            $extraHTML = "<tr class='datarow'><td>{$extraNameTag} {$webText[$translateKey]}: #requiredmark#</td><td><input class='inputbox' {$inputMode} {$jsCall} style='width:{$width}px;' type='text' name='{$field}' id='{$field}' value='{$value}' /></td></tr>";
        }

        if(CompanyService::isALDI() && $fieldName=="telefon"){
            $inputMode = "inputmode='numeric' oninput=\"this.value = this.value.replace(/\D+/g, '')\" maxlength='11' placeholder='06301234567'";
        }

        if (!$hidden || $RequiedForced) {
            if (empty($extraHTML)) {
                if(isset($_POST[$field])){
                    $value = $_POST[$field];
                }else{
                    $value = "";
                }
                $html.= "<tr class='datarow'><td>{$extraNameTag} {$webText[$translateKey]}: #requiredmark#</td><td><input class='inputbox' {$inputMode} {$jsCall} style='width:{$width}px;' type='text' name='{$field}' id='{$field}' value='{$value}' /></td></tr>";
            } else {
                $html.= $extraHTML;
            }
        }
        $html.= $extraRow;
        $html = str_replace("#requiredmark#",$required||$RequiedForced?"*":"", $html);

        return $html;
    }

    public function getFieldRequired($field) {
        $required = true;
		if (substr_count($_SESSION["helyszindata"]["fieldoptions"], "notreq_{$field}") || $this->getFieldHidden($field)) {
			$required = false;
		}
		
        return $required;
    }

    public function getFieldHidden($field) {
        $hidden = false;
		if (substr_count($_SESSION["helyszindata"]["fieldoptions"], "hidden_{$field}")) {
			$hidden = true;
		}
		
        return $hidden;
    }

    public function checkCaptcha() {
        $lang = new Lang();
        $webText = $lang->webText;

        $error = "";
        if (isset($_POST["g-recaptcha-response"])) {
            $captcha = $_POST["g-recaptcha-response"];
        }
        if (isset($captcha)) {
            if (!$captcha) {
                $error = "{$webText["captchaerror1"]}<br/>";
            } else {
                $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfCaTIUAAAAAF1-t94n7TBAsKov_dglwP6b8Luo&response=" . urlencode($captcha) . "&remoteip=" . $_SERVER["REMOTE_ADDR"]), true);
                if ($response["success"] == false) {
                    $error = "{$webText["captchaerror2"]}<br/>";
                }
            }
        } else {
            $error = "{$webText["captchaerror3"]}<br/>";
        }
        return $error;
    }

    public static function jsonOut($data, $encoding = "utf-8") {
        header("Content-Type: application/json; charset={$encoding}");
        echo json_encode($data);
        die();
    }
	
	public static function isDemoSite() {
        return Booking_Constants::IS_DEMO;
    }

    public static function converResult($result) {
        return htmlentities(trim(str_replace("\n\n","\n",str_replace("<","\n<", $result))));
    }

    public function create_zip( $files = array(), $destination = '', $removeContainers = NULL, $overwrite = false ) {
        //if the zip file already exists and overwrite is false, return false
        if( file_exists( $destination ) && !$overwrite ) { return false; }
        //vars
        $valid_files = array();
        //if files were passed in...
        if( is_array( $files )) {
            //cycle through each file
            foreach( $files as $file ) {
                //make sure the file exists
                if( file_exists( $file )) {
                    $valid_files[] = $file;
                }
            }
        }
        //if we have good files...
        if( count( $valid_files )) {
            //create the archive
            $zip = new ZipArchive();
            if( $zip->open($destination,$overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true ) {
                return false;
            }
            //add the files
            foreach( $valid_files as $file ) {
                if($removeContainers != NULL)
                {
                    $onlyFile = explode( "/", $file );
                    $zip->addFile( $file,$onlyFile[array_key_last($onlyFile)]);
                    //
                    //$zip->addFile( $file, $onlyFile[$removeContainers] );
                }
                else
                {
                    $zip->addFile($file, $file);
                }
            }
            //debug
            //echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;
            //close the zip -- done!
            $zip->close();
            
            //system("zip -P $password $destination $destination");
            
            //check to make sure the file exists
            return file_exists( $destination );
        }
        else
        {
            return false;
        }
    }

    public static function generateRandomString($length = 10) {
        $characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function tajCheck($taj):bool {
        $taj = trim($taj);
        if (!empty($taj)) {
            if (strlen($taj) != 9) {
                return false;
            } else {
                $checkNum = 0;
                for ($i = 0; $i < 8; $i++) {
                    $number = intval(substr($taj, $i, 1));
                    $checkNum += $i % 2 == 1 ? $number*7 : $number*3;
                }
                if ($checkNum % 10 != substr($taj, -1)) {
                    return false;
                }
            }
        }
        return true;
    }


    public static function convertAccentsAndSpecialToNormal($string):string {
        $table = array(
            'À'=>'Á', 'Á'=>'Á', 'Â'=>'Á', 'Ã'=>'Á', 'Ä'=>'Á', 'Å'=>'Á', 'Ă'=>'Á', 'Ā'=>'Á', 'Ą'=>'A', 'Æ'=>'A', 'Ǽ'=>'A',
            'à'=>'á', 'á'=>'á', 'â'=>'á', 'ã'=>'á', 'ä'=>'á', 'å'=>'á', 'ă'=>'á', 'ā'=>'á', 'ą'=>'a', 'æ'=>'a', 'ǽ'=>'a',

            'Þ'=>'B', 'þ'=>'b', 'ß'=>'Ss',

            'Ç'=>'C', 'Č'=>'C', 'Ć'=>'C', 'Ĉ'=>'C', 'Ċ'=>'C',
            'ç'=>'c', 'č'=>'c', 'ć'=>'c', 'ĉ'=>'c', 'ċ'=>'c',

            'Đ'=>'Dj', 'Ď'=>'D',
            'đ'=>'dj', 'ď'=>'d',

            'È'=>'É', 'É'=>'É', 'Ê'=>'É', 'Ë'=>'É', 'Ĕ'=>'É', 'Ē'=>'É', 'Ę'=>'É', 'Ė'=>'É',
            'è'=>'é', 'é'=>'é', 'ê'=>'é', 'ë'=>'é', 'ĕ'=>'é', 'ē'=>'é', 'ę'=>'é', 'ė'=>'é',

            'Ĝ'=>'G', 'Ğ'=>'G', 'Ġ'=>'G', 'Ģ'=>'G',
            'ĝ'=>'g', 'ğ'=>'g', 'ġ'=>'g', 'ģ'=>'g',

            'Ĥ'=>'H', 'Ħ'=>'H',
            'ĥ'=>'h', 'ħ'=>'h',

            'Ì'=>'Í', 'Í'=>'Í', 'Î'=>'Í', 'Ï'=>'Í', 'İ'=>'Í', 'Ĩ'=>'Í', 'Ī'=>'Í', 'Ĭ'=>'Í', 'Į'=>'Í',
            'ì'=>'í', 'í'=>'í', 'î'=>'i', 'ï'=>'i', 'į'=>'i', 'ĩ'=>'i', 'ī'=>'i', 'ĭ'=>'i', 'ı'=>'i',

            'Ĵ'=>'J',
            'ĵ'=>'j',

            'Ķ'=>'K',
            'ķ'=>'k', 'ĸ'=>'k',

            'Ĺ'=>'L', 'Ļ'=>'L', 'Ľ'=>'L', 'Ŀ'=>'L', 'Ł'=>'L',
            'ĺ'=>'l', 'ļ'=>'l', 'ľ'=>'l', 'ŀ'=>'l', 'ł'=>'l',

            'Ñ'=>'N', 'Ń'=>'N', 'Ň'=>'N', 'Ņ'=>'N', 'Ŋ'=>'N',
            'ñ'=>'n', 'ń'=>'n', 'ň'=>'n', 'ņ'=>'n', 'ŋ'=>'n', 'ŉ'=>'n',

            'Ò'=>'Ó', 'Ó'=>'Ó', 'Ô'=>'Ő', 'Õ'=>'Ő', 'Ö'=>'Ö', 'Ø'=>'O', 'Ō'=>'Ö', 'Ŏ'=>'Ö', 'Ő'=>'Ő', 'Œ'=>'O',
            'ò'=>'ó', 'ó'=>'ó', 'ô'=>'ő', 'õ'=>'ő', 'ö'=>'ö', 'ø'=>'o', 'ō'=>'ö', 'ŏ'=>'ö', 'ő'=>'ő', 'œ'=>'o', 'ð'=>'o',

            'Ŕ'=>'R', 'Ř'=>'R',
            'ŕ'=>'r', 'ř'=>'r', 'ŗ'=>'r',

            'Š'=>'S', 'Ŝ'=>'S', 'Ś'=>'S', 'Ş'=>'S',
            'š'=>'s', 'ŝ'=>'s', 'ś'=>'s', 'ş'=>'s',

            'Ŧ'=>'T', 'Ţ'=>'T', 'Ť'=>'T',
            'ŧ'=>'t', 'ţ'=>'t', 'ť'=>'t',

            'Ù'=>'Ú', 'Ú'=>'Ú', 'Û'=>'Ű', 'Ü'=>'Ü', 'Ũ'=>'Ű', 'Ū'=>'Ü', 'Ŭ'=>'Ü', 'Ů'=>'Ú', 'Ű'=>'Ű', 'Ų'=>'U',
            'ù'=>'ú', 'ú'=>'ú', 'û'=>'ű', 'ü'=>'ü', 'ũ'=>'ű', 'ū'=>'ü', 'ŭ'=>'ü', 'ů'=>'ú', 'ű'=>'ű', 'ų'=>'u',

            'Ŵ'=>'W', 'Ẁ'=>'W', 'Ẃ'=>'W', 'Ẅ'=>'W',
            'ŵ'=>'w', 'ẁ'=>'w', 'ẃ'=>'w', 'ẅ'=>'w',

            'Ý'=>'Y', 'Ÿ'=>'Y', 'Ŷ'=>'Y',
            'ý'=>'y', 'ÿ'=>'y', 'ŷ'=>'y',

            'Ž'=>'Z', 'Ź'=>'Z', 'Ż'=>'Z',
            'ž'=>'z', 'ź'=>'z', 'ż'=>'z',

            '“'=>'"', '”'=>'"', '‘'=>"'", '’'=>"'", '•'=>'-', '…'=>'...', '—'=>'-', '–'=>'-', '¿'=>'?', '¡'=>'!', '°'=>' degrees ',
            '¼'=>' 1/4 ', '½'=>' 1/2 ', '¾'=>' 3/4 ', '⅓'=>' 1/3 ', '⅔'=>' 2/3 ', '⅛'=>' 1/8 ', '⅜'=>' 3/8 ', '⅝'=>' 5/8 ', '⅞'=>' 7/8 ',
            '÷'=>' divided by ', '×'=>' times ', '±'=>' plus-minus ', '√'=>' square root ', '∞'=>' infinity ',
            '≈'=>' almost equal to ', '≠'=>' not equal to ', '≡'=>' identical to ', '≤'=>' less than or equal to ', '≥'=>' greater than or equal to ',
            '←'=>' left ', '→'=>' right ', '↑'=>' up ', '↓'=>' down ', '↔'=>' left and right ', '↕'=>' up and down ',
            '℅'=>' care of ', '℮' => ' estimated ',
            'Ω'=>' ohm ',
            '♀'=>' female ', '♂'=>' male ',
            '©'=>' Copyright ', '®'=>' Registered ', '™' =>' Trademark ',
        );

        return strtr($string, $table);
    }

    public function generateRandomStringv2($length = 10) {
        return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
    }


    private array $astotecMunkakorok = [
        'Általános tréner',
        'Anyagmozgató',
        'Anyagtervezési csoportvezető',
        'Anyagtervező diszponens',
        'Asszisztens',
        'Automatizálási mérnök',
        'Ballisztikai ellenőr',
        'Bejövő áru ellenőr',
        'Bérszámfejtő - TB ügyintéző',
        'Beszállítói & Termék jóváhagyási csopvez',
        'Beszállítói minőségbiztosítási mérnök',
        'Beszerzési vezető',
        'Beszerző',
        'Elektromos terméktesztelő',
        'Főkönyvelő',
        'Gépbeállító',
        'Göngyöleg tisztító',
        'Gyártástámogató mérnök',
        'Gyártástámogató technikus',
        'Gyártástervező',
        'Gyártástervező csoportvezető',
        'Gyártósori dolgozó',
        'HR adminisztrátor',
        'HR generalista',
        'HR vezető',
        'Hulladékkezelési munkatárs',
        'IT / Business Analyst vezető',
        'IT Business Analyst',
        'IT technikus',
        'Junior IT Business Analyst',
        'Junior minőségbiztosítási mérnök',
        'Kanban koordinátor',
        'Kanbanfelelős',
        'Karbantartás fejlesztő mérnök',
        'Karbantartási csoportvezető',
        'Karbantartó',
        'Kézi raktári adminisztrátor',
        'Kiemelt dolgozó',
        'Kontroller',
        'Könyvelési adminisztrátor',
        'Könyvelési ellenőr',
        'Könyvelő',
        'Launch manager',
        'Lean manager',
        'Leanmérnök',
        'Learning and development specialist',
        'Logisztikai folyamatmérnök',
        'Logisztikai folyamatmérnökség csoportvez',
        'Logisztikai vezető',
        'Méréstechnikus',
        'Mérnökségi csoportvezető',
        'Mérnökségvezető',
        'Mérőlabor vezető',
        'Mérőlaboratórium adminisztrátor',
        'Metrológus',
        'Minőségbiztosítási mérnök',
        'Minőségbiztosítási mérnökség csopvez',
        'Minőségbiztosítási vezető',
        'Minőségellenőrzési csoportvezető',
        'Minőségügyi adminisztrátor',
        'Minőségügyi asszisztens',
        'Minőségügyi ellenőr',
        'Minőségügyi oktató',
        'Minőségügyi operátor',
        'Minőségügyi rendszer csoportvezető',
        'Minőségügyi rendszer munkatárs',
        'Mintagyártási adminisztrátor',
        'Mintagyártási koordinátor',
        'Operációs ügyvezető',
        'Operatív Termelési Vezető',
        'Pénzügyi és Kontrolling vezető',
        'PPAP Koordinátor',
        'Product manager',
        'Profit Center vezető',
        'Raktári adminisztrátor',
        'Raktári muszakvezető',
        'Raktári oktató',
        'Raktáros',
        'Raktárvezető',
        'SCM ügyintéző',
        'Segédgépbeállító',
        'Segédmunkás',
        'Selejttermék kezelő',
        'Senior beszerző',
        'Senior könyvelő',
        'Számviteli ügyintéző',
        'Szerszám karbantartó',
        'Szerszámmérnök',
        'Technikai csoportvezető',
        'Technikai koordinátor',
        'Termék mérnök',
        'Termékauditor',
        'Termelési adminisztrátor',
        'Termelési területvezető',
        'Termelési vezető',
        'Területi adminisztrátor',
        'Ügyvezető Central Functions',
        'Üzemfenntartási technikus',
        'Válogatási csoportvezető',
        'Válogató',
        'Vevőkapcsolattartás csoportvezető',
        'Vevőkapcsolattartó',
    ];

    public function getCegTelephelyCsoportok($cegid){
        $telephelyek= [];
        $q= sql_query("SELECT * FROM cegvars WHERE cegid=? AND parentid=0",[$cegid]);
        while($res=sql_fetch_array($q)) $telephelyek[] = array("id"=>$res["id"],"name"=>$res["megnev"]);
        return $telephelyek;
    }

    public function showTelephelyHelyszinek($cegvarRow){
        $h = "";
        if($cegvarRow["parentid"]==0) return "";
        $placeids = json_decode($cegvarRow["placeids"],true);
        if(!empty($placeids)){
        $h=sql_fetch_array(sql_query("SELECT GROUP_CONCAT(cim) as cimek FROM helyszinek WHERE id IN(".implode(",",$placeids).")"));
        }
        $html = "<span id=\"helyszinstatus{$cegvarRow["id"]}\"><a href=\"#\" class=\"tlink\" title=\"".(isset($h["cimek"])?$h["cimek"]:"")."\" onclick='showTelephelyHelyszinValaszto({$cegvarRow["id"]});return false;'>".count($placeids)." Helyszín</a></span>";
        return $html;
    }

    public function showSzurestipusok($cegvarRow){
        $h = "";
        if($cegvarRow["parentid"]==0) return "";
        $szurestipusids = json_decode($cegvarRow["szurestipusids"],true);
        if(!empty($szurestipusids)){
            $h=sql_fetch_array(sql_query("SELECT GROUP_CONCAT(megnev) as szuresek FROM szurestipusok WHERE id IN(".implode(",",$szurestipusids).")"));
        }
        $html = "<span id=\"szuresstatus{$cegvarRow["id"]}\"><a href=\"#\" class=\"tlink\" title=\"".(isset($h["szuresek"])?$h["szuresek"]:"")."\" onclick='showTelephelySzurestipusValaszto({$cegvarRow["id"]});return false;'>".count($szurestipusids)." Típus</a></span>";
        
        
        return $html;
    }

    public function showTelephelyHelyszinValaszto($telephely){
        $html = "";
        $telephelyek = json_decode($telephely["placeids"]);

            $q=sql_query("SELECT beo.helyszinid,h.cim FROM orvos_beosztas_new beo
                                LEFT JOIN helyszinek h ON h.id=beo.helyszinid
                                WHERE INSTR(beo.beocegek,\"|{$telephely["cegid"]}|\") GROUP BY beo.helyszinid");
            $html.= "<div class=\"width:1000px;padding:4px 0px;\">";
            while($helyszin=sql_fetch_array($q)){
                $onClick = "onClick='selectTelephelyHelyszin({$telephely["id"]},{$helyszin["helyszinid"]})'";
                $html.= "<a style=\"cursor:pointer\" {$onClick} class=\"".(in_array($helyszin["helyszinid"],$telephelyek)?"serviceselected":"servicenotselected")."\">";
                $html.= $helyszin["cim"];
                $html.= "</a>&nbsp;";
            }
            $html.= "</div>";

            return $html;
    }

    public function showTelephelySzurestipusValaszto($telephely){
        $html = "";
        $szuresek = json_decode($telephely["szurestipusids"]);

            $q=sql_query("SELECT sz.id,sz.megnev FROM szurestipusok sz
                         LEFT JOIN orvos_beosztas_new beo ON INSTR(beo.tipusok,CONCAT(\"|\",sz.id,\"|\"))
                         WHERE INSTR(beo.beocegek,CONCAT('|',".$telephely["cegid"].",'|')) GROUP BY sz.id");

            $html.= "<div class=\"width:1000px;padding:4px 0px;\">";
            while($szures=sql_fetch_array($q)){
                $onClick = "onClick='selectTelephelySzurestipus({$telephely["id"]},{$szures["id"]})'";
                $html.= "<a style=\"cursor:pointer\" {$onClick} class=\"".(in_array($szures["id"],$szuresek)?"serviceselected":"servicenotselected")."\">";
                $html.= $szures["megnev"];
                $html.= "</a>&nbsp;";
            }
            $html.= "</div>";

            return $html;
    }

    public function AlternativSzurestipusNevByCeg($szurestipusId,$megnev){
        $resq = sql_query("SELECT * FROM eltero_ceg_szurestipus_nevek WHERE cegid=? and szurestipusid=?",array($_SESSION["helyszindata"]["id"],$szurestipusId));
        if($altmegnev=sql_fetch_array($resq)){
            $megnev = $altmegnev["megnev"];
            return $megnev;
        }
        
        return $megnev;
    }

    /**
     * Védelmi funkció Javascript és HTML inject támadások ellen.
     * @param array $array       Vizsgálandó tömb.
     */
    public function sanitize_array(array $array):array{
        if(!empty($array)){
            foreach($array as $key=>$value){
                $remove = ["<",">","\"","'"];
                $replace = ["","","",""];
                $array[$key] = str_replace($remove,$replace, $value);
            }
        }
        return $array;
    }

}