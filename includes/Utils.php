<?php

class Utils {

    public function __construct()
    {
        if (isset($_GET["downloaddoc"]) && isset($_GET["f"]) && isset($_GET["k"])) {
            $docAgent = new DocAgent();
            $docAgent->showDocBinary($_GET["f"], $_GET["k"]);
        }
    }

    public function sendEljottMail($foglalasData) {
        include_once("phpmailer/class.phpmailer.php");
        $mail = new PHPMailer();
        $mail->From = "noreply@hungariamed.hu";
        $mail->FromName = "Hungariamed";
        //$mail->AddAddress($foglalasData["email"]); //ne élesítsd még
        $mail->AddAddress("jns@jns.hu");
        $mail->AddReplyTo("noreply@hungariamed.hu");
        $mail->IsHTML(true);

        if ($emailData = sql_fetch_array(sql_query("select * from ertekeles_formok where (instr(rule_cegids,'|{$foglalasData["cegid"]}|') or rule_cegids='all') and rule_mail=1 and rule_aftereljott=1"))) {
            $mailSzoveg = $emailData["mailszoveg_{$foglalasData["rlang"]}"];
            if ($mailSzoveg == "") $mailSzoveg = $emailData["mailszoveg_hu"];
            $mailSubject = $emailData["megnev_{$foglalasData["rlang"]}"];
            if ($mailSubject == "") $mailSubject = $emailData["megnev_hu"];
            if ($mailSzoveg != "" && $mailSubject != "") {
                $mailSzoveg = str_replace("#nev#", $foglalasData["nev"], $mailSzoveg);
                $mail->Subject = iconv("UTF-8", "ISO-8859-2", $mailSubject);
                $mail->Body = iconv("UTF-8", "ISO-8859-2", $mailSzoveg);
                $mail->Send();
                sql_query("update foglalasok set eljottmail=1 where id=?", array($foglalasData["id"]));
            }
        }
        return;
    }

    public function sendUserSMSKod($userId)
    {
        if ($rowu = sql_fetch_array(sql_query("SELECT f.* FROM felhasznalok f 
	    LEFT JOIN cegek c ON c.id=f.cegid
	    WHERE f.id=? AND c.`noregsms`=0", array($userId)))) {
            include("includes/other/seeme-gateway-class.php");
            sendSMS($rowu["telefon"], "kód a regisztráció befejezéséhez: {$rowu["rkod"]}");
        } else {
            sql_query("update felhasznalok set validated=1 where id=?", array($userId));
        }
    }

    public function sendLoginSMSKod($userId)
    {
        if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where id='{$userId}'"))) {
            include("includes/other/seeme-gateway-class.php");
            sendSMS($rowu["telefon"], "kód a bejelentkezéshez: {$rowu["rkod"]}");
        }
    }

    function sendNotConfirmedReservationMessages($id)
    {
        /*
        nem visszaigazolt foglalás esetén:
        - mail a paciensnek
        - mail a hmm-nek
        - sms a paciensnek
        */
        $h = "cim";
        if ($_SESSION["helyszindata"]["nocim"] == 1) $h = "megnev";;

        $res = sql_query("SELECT h.{$h} AS helyszin,sz.megnev AS szurestipus,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=?", array($id));
        if ($row = sql_fetch_array($res)) {
            include_once("phpmailer/class.phpmailer.php");
            $mail = new PHPMailer();
            $mail->From = "noreply@hungariamed.hu";
            $mail->FromName = "Hungariamed";
            $mail->AddAddress($row["email"]);
            //$mail->AddAddress("jns@jns.hu");
            $mail->AddReplyTo("noreply@hungariamed.hu");
            $mail->IsHTML(true);

            $t = iconv("UTF-8", "ISO-8859-2", "Figyelem! Foglalását töröltük!");

            $mbody = "<h2>Foglalását töröltük!</h2>";
            $mbody .= "Előző levelünkben küldött megerősítő hivatkozásra nem kattintott rá, ezért a {$row["datum"]} időpontra szóló foglalását töröltük.<br/>";
            $mbody .= "<br/>";
            $mbody .= "Üdvözlettel:<br/>Hungariamed";

            $mail->Subject = $t;
            $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
            //$mail->AddAttachment("");
            $mail->Send();

            $mail = new PHPMailer();
            $mail->From = "noreply@hungariamed.hu";
            $mail->FromName = "Hungariamed";
            $mail->AddAddress("bejelentkezes@hungariamed.hu");
            $mail->AddReplyTo("noreply@hungariamed.hu");
            $mail->IsHTML(true);

            $t = iconv("UTF-8", "ISO-8859-2", "Egy paciens foglalása törölve lett!");

            $mbody = "<h2>Törölt foglalás</h2>";
            $mbody .= "A paciens foglalt, de nem igazolta vissza a következő rendelést, ezért azt töröltük:<br/>";
            $mbody .= "Név: {$row["nev"]}<br/>";
            $mbody .= "Telefon: {$row["telefon"]}<br/>";
            $mbody .= "Email: {$row["email"]}<br/>";
            $mbody .= "<b>Időpont: {$row["datum"]}</b><br/>";
            $mbody .= "Szűréstípus: {$row["szurestipus"]}<br/>";
            $mbody .= "Helyszín: {$row["helyszin"]}<br/>";
            $mbody .= "<br/>";
            $mbody .= "Hívd fel az ügyfelet egyeztetés céljából.</a><br>";

            $mail->Subject = $t;
            $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
            //$mail->AddAttachment("");
            $mail->Send();

            include_once("includes/seeme-gateway-class.php");
            sendSMS($row["telefon"], "Figyelem, {$row["datum"]} foglalását visszaigazolás hiányában töröltük!");
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

    function szurestipusvalaszto($helyszinid, $selected = 0, $onlyselected = 0)
    {
        $tipusok = array();

        $rest = sql_query("select * from szurestipusok");
        while ($rowt = sql_fetch_array($rest)) {
            $tipusnevek[$rowt["id"]] = $rowt["megnev"];
        }

        $addJava = "";
        if ($_SESSION["helyszindata"]["id"] == 11) {
            $addJava = "if (this.value==1) { $(\"#fogleuwarn\").show(); } else { $(\"#fogleuwarn\").hide(); }";
        }


        $htmlout = "";
        $htmlout .= "<select name='szurestipus' id='szurestipus' onchange='clearIdopontValaszto();showTipusMegj(this.value);{$addJava}'>";
        $htmlout .= "<option value='0'>{$webText["valasszon"]}!</option>";

        /*
        $res=sql_query("SELECT t.* FROM orvos_beosztas b
            LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
            LEFT JOIN szurestipusok t ON t.`id`=o.`tipusid`
            WHERE b.helyszinid='".addslashes($helyszinid)."'  AND b.cegid='{$_SESSION["helyszindata"]["id"]}' AND t.`megnev` IS NOT NULL
            GROUP BY t.`id`");


        if ($onlyselected==0) $htmlout.="<option value='0'>Válassz!</option>";
        while ($rowt=sql_fetch_array($res)) {
            $tipusok[]=$rowt["id"];
            //$htmlout.="<option value='{$rowt["id"]}'".($selected==$rowt["id"]?" selected":"").">{$rowt["megnev"]}</option>";
        }
        */
        $res = sql_query("SELECT tipusok FROM orvos_beosztas b WHERE b.helyszinid='" . addslashes($helyszinid) . "' AND b.cegid='{$_SESSION["helyszindata"]["id"]}'");
        while ($row = sql_fetch_array($res)) {
            $ta = explode("|", $row["tipusok"]);
            for ($i = 0; $i < count($ta); $i++) {
                if (trim($ta[$i]) != "" && !in_array($ta[$i], $tipusok)) {
                    $tipusok[] = $ta[$i];
                }
            }
        }

        if (isset($tipusok)) {
            for ($i = 0; $i < count($tipusok); $i++) {
                @$tipusdisplay[$tipusok[$i]] = $tipusnevek[$tipusok[$i]];
            }
            if (isset($tipusdisplay)) {
                asort($tipusdisplay);
                foreach ($tipusdisplay as $key => $value) {
                    if ($onlyselected == 1 && $key != $selected) continue;
                    if (trim($value) == "") continue;
                    $htmlout .= "<option value='{$key}'" . ($selected == $key ? " selected" : "") . ">{$value}</option>";
                }
            }
        }

        $htmlout .= "</select>";
        return $htmlout;
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

    public function datumSelector($date,$prefix) {
        $lang = new Lang();
        $webText = $lang->webText;

        $h="";

        $ev=substr($date,0,4);
        $ho=substr($date,5,2);
        $nap=substr($date,8,2);

        $h.= "<select name='{$prefix}ev'>";
        $h.= "<option value='0'>{$webText["ev"]}</option>";
        for ($i=date("Y");$i>date("Y")-100;$i--) {
            $h.= "<option value='{$i}'".($ev==$i?" selected":"").">{$i}</option>";
        }
        $h.= "</select> ";

        $h.= "<select name='{$prefix}ho'>";
        $h.= "<option value='0'>{$webText["ho"]}</option>";
        for ($i=1;$i<=12;$i++) {
            $h.= "<option value='{$i}'".($ho==$i?" selected":"").">{$webText["honaptext"][$i]}</option>";
        }
        $h.= "</select> ";

        $h.= "<select name='{$prefix}nap'>";
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


    function selectOrvosForFoglalas($fid) {
        $rowf=sql_fetch_array(sql_query("select * from foglalasok where id='{$fid}'"));
        $nap=substr($rowf["datum"],0,10);
        $ora=substr($rowf["datum"],11,5);

        if ($rowf["orvosassigned"]!=0) return sql_query("select o.*,o.id as orvosid from orvosok o where id='{$rowf["orvosassigned"]}'");

        return sql_query("SELECT WEEK('{$nap}',3)%2 AS weekmodulo,b.*,o.* FROM orvos_beosztas b 
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		WHERE b.`helyszinid`='{$rowf["helyszinid"]}' and (b.cegid='{$rowf["cegid"]}' or b.cegid=0) AND (INSTR(tipusok,'|{$rowf["szurestipusid"]}|') OR tipusok='') AND nap=WEEKDAY('{$nap}')+1 AND TIME(tol)<=TIME('{$ora}') AND TIME(ig)>TIME('{$ora}') AND TRIM(b.tipusok)<>''
		order by IF (hetek=1,weekmodulo=0,weekmodulo=1)");
    }


    public function getTajFromString($str) {
        preg_match_all('/\d+/', $str, $matches);
        foreach ($matches[0] as $val) {
            if (strlen($val)==9) return $val;
        }
        return "";
    }

    public function htmlheader($pageTitle = "HMM online bejelentkezés") {
        $subdomain=$_SESSION["helyszindata"]["domain"];

        $htmlout='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        $htmlout.='<html xmlns="http://www.w3.org/1999/xhtml">';
        $htmlout.='<head>';

        /*
        if (!isset($GLOBALS["admin"])) {
            $htmlout.="<!-- Google Tag Manager -->
                <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','GTM-KGL6C9C');</script>
                <!-- End Google Tag Manager -->";

            $htmlout.="<!-- Google Tag Manager (noscript) -->
                <noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=GTM-KGL6C9C\"
                height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>
                <!-- End Google Tag Manager (noscript) -->";
        }
        */

        $htmlout.="<meta http-equiv='Content-Type' content='text/html; charset=utf-8'><title>{$pageTitle}</title>";
        $htmlout.='<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
        $favicon="/images/hmm_favicon.png";
        if (is_file("images/logo_{$subdomain}.png") || is_file("../images/logo_{$subdomain}.png")) $favicon="/images/logo_{$subdomain}.png";

        $htmlout.="<link rel='shortcut icon' type='image/png' href='{$favicon}' />";
        $htmlout.='<script type="text/javascript" src="//code.jquery.com/jquery-latest.js"></script>';
        $htmlout.='<script type="text/javascript" src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>';
        $htmlout.='<script type="text/javascript" src="/javascript/sweetalert/sweetalert2.min.js"></script>';
        $htmlout.='<script type="text/javascript" src="javascript/ajax.js"></script>';
        $htmlout.="<script src='https://www.google.com/recaptcha/api.js?hl={$_COOKIE["lang"]}'></script>";
        $htmlout.="<link rel='stylesheet' type='text/css' href='index.css' />";
        $htmlout.='<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">';
        $htmlout.='<link rel="stylesheet" href="/javascript/sweetalert/sweetalert2.css" type="text/css" />';
        $htmlout.="<link rel='stylesheet' href='/images/webfonts/roboto_regular_hungarian/stylesheet.css' type='text/css' charset='utf-8' async/>";
        $htmlout.="<link rel='stylesheet' href='/images/webfonts/roboto_bold_hungarian/stylesheet.css' type='text/css' charset='utf-8' async/>";
        $htmlout.="<link rel='stylesheet' href='/images/webfonts/roboto_light_hungarian/stylesheet.css' type='text/css' charset='utf-8' async/>";

        $htmlout.="</head>";
        return $htmlout;
    }

}