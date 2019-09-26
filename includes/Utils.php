<?php

class Utils {

    public function __construct()
    {
        if (isset($_GET["downloaddoc"]) && isset($_GET["f"]) && isset($_GET["k"])) {
            $docAgent = new DocAgent();
            $docAgent->showDocBinary($_GET["f"], $_GET["k"]);
        }

        if (isset($_GET["print"]) && isset($_GET["template"])) {
            $printService = new PrintService();
            $printService->setTemplate($_GET["template"]);
            if (isset($_GET["fid"]) && isset($_GET["p"])) {
                $printService->setReservation($_GET["fid"], $_GET["p"]);
            }
            $printService->start();
            die;
        }
    }

    public function isTesztIP() {
        return in_array($_SERVER["REMOTE_ADDR"],array("88.151.97.121","81.182.23.124","5.204.54.10","81.182.23.106","194.143.226.42"));
    }

    public function sendEljottMail($foglalasData) {
        $mail = new PHPMailer();
        $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
        $mail->FromName = Booking_Constants::COMPANY_NAME;
        //$mail->AddAddress($foglalasData["email"]); //ne élesítsd még
        $mail->AddAddress("jns@jns.hu");
        $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
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
            $mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress($row["email"]);
            //$mail->AddAddress("jns@jns.hu");
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);

            $t = iconv("UTF-8", "ISO-8859-2", "Figyelem! Foglalását töröltük!");

            $mbody = "<h2>Foglalását töröltük!</h2>";
            $mbody .= "Előző levelünkben küldött megerősítő hivatkozásra nem kattintott rá, ezért a {$row["datum"]} időpontra szóló foglalását töröltük.<br/>";
            $mbody .= "<br/>";
            $mbody .= "Üdvözlettel:<br/>".Booking_Constants::COMPANY_NAME;

            $mail->Subject = $t;
            $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
            //$mail->AddAttachment("");
            $mail->Send();

            $mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress(Booking_Constants::RESERVATION_TO_ADDRESS);
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
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

            $this->sendSMS($row["telefon"], "Figyelem, {$row["datum"]} foglalását visszaigazolás hiányában töröltük!");
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

    public function htmlheader($pageTitle = "Online bejelentkezés") {
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
        $favicon="/images/".Booking_Constants::SITE_FAVICON;
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

    public function setupLongSession() {
        $sessionUp = 2; //óra
        ini_set('session.gc_maxlifetime', $sessionUp*60*60);
        session_set_cookie_params($sessionUp*60*60);

        $_SESSION["LAST_ACTIVITY"] = time();
    }

    public function sendSMS($num,$szoveg) {
        $num = str_replace(" ","",$num);
        $num = str_replace("-","",$num);
        $num = str_replace("/","",$num);
        $num = str_replace("(","",$num);
        $num = str_replace(")","",$num);
        $num = str_replace("+","",$num);

        if (substr($num,0,2)=="06") {
            $num="36".substr($num,2);
        }

        $SeeMe = new SeeMeGateway("1uivd276x0rvuo9v97k6z4x7axmaukoi5828");

        try {
            $SeeMe->sendSMS($num, $szoveg);
        } catch (Exception $e) {
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
                    $mail = new PHPMailer();
                    $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
                    $mail->FromName = Booking_Constants::COMPANY_NAME;
                    $mail->AddAddress(iconv("UTF-8","ISO-8859-2",$result['umail']));
                    if ($result['hrmail'] != "") {
                        $mail->AddAddress(iconv("UTF-8","ISO-8859-2",$result['hrmail']));
                    }
                    $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
                    $mail->IsHTML(true);

                    $t = iconv("UTF-8","ISO-8859-2","Orvosi alkalmassági vizsgálata hamarosan lejár!");

                    $mbody = "Kedves {$result['nev']},<br/>";
                    $mbody.= "Az orvosi alkalmassági vizsgálata hamarosan lejár!<br/>";
                    $mbody.= "Lejárat dátuma: ".date("Y.m.d",strtotime($result['alklejarat']))."<br/>";
                    $mbody.= "Kérem foglaljon időpontot honlapunkon:<br/>";
                    $mbody.= "<a href='".Booking_Constants::SITE_PROTOCOL."://".$result['domain'].".".Booking_Constants::SITE_DOMAIN."'>".Booking_Constants::SITE_PROTOCOL."://".$result['domain'].".".Booking_Constants::SITE_DOMAIN."</a><br/>";
                    $mbody.= "Tisztelettel,<br/>";
                    $mbody.= Booking_Constants::COMPANY_NAME;

                    $mail->Subject=$t;
                    $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
                    //$mail->AddAttachment("");
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


    public function send_alkExcel($cegid, $intvallType, $mails) {
        require_once("other/PHPExcel.php");
        $rowCount = 2;
        $SendingDayParameters = array( "1", "2", "3", "4", "5", "6", "7" );
        if ($intvallType == "napi" && in_array(date("N"), $SendingDayParameters)) {
            $intervall = "fogl.datum ";
            //$intervall.= "LIKE '2018-10-01%' ";
            $intervall.= "LIKE '".date("Y-m-d")."%' ";
            $releaseDate = date("Y-m-d");
            //$releaseDate = "2018-11-05";
        }

        if ($intvallType == "heti" && date("N") == 2) {
            $intervall = "fogl.datum ";
            $intervall.= "BETWEEN '".date( "Y-m-d", strtotime(date( "Y-m-d" )." -4 day"))."' ";
            $intervall.= "AND     '".date( "Y-m-d", strtotime(date( "Y-m-d")." +1 day"))."' ";
            $releaseDate = date( "Y-m" )." ".$this->getWeeks(date("Y-m-d"), "sunday").". hét";
        }
        if ($intvallType == "havi" && date("j") == 1) {
            $intervall = "fogl.datum ";
            $intervall.= "BETWEEN '".date("Y-m-d", strtotime(date("Y-m-d")." -1 month"))."' ";
            $intervall.= "AND     '".date("Y-m-d", strtotime(date("Y-m-d")." -1 day"))."' ";
            $releaseDate = date("Y-m");
        }

        //Ha nem lehetett definiálni az intervallumot szakítsa meg a kódot.
        if (!isset($intervall)) {
            return;
        }

        $filename = $releaseDate." napi riport";
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->setTitle('Napi lista');

        //header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        //header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
        //header('Cache-Control: max-age=0');

        //Lekérdezés
        $request = sql_query("SELECT fogl.*, doc.nev as orvos FROM foglalasok fogl LEFT JOIN orvosok doc ON doc.id = fogl.orvosassigned WHERE {$intervall} AND fogl.cegid=?", array($cegid));

        //Oszlop nevek:
        $objPHPExcel->getActiveSheet()->SetCellValue('A1', "Név");
        $objPHPExcel->getActiveSheet()->SetCellValue('B1', "Szül. dátum");
        $objPHPExcel->getActiveSheet()->SetCellValue('C1', "TAJ");
        $objPHPExcel->getActiveSheet()->SetCellValue('D1', "Törzsszám");
        $objPHPExcel->getActiveSheet()->SetCellValue('E1', "Munkakör");
        $objPHPExcel->getActiveSheet()->SetCellValue('F1', "Orvos");
        $objPHPExcel->getActiveSheet()->SetCellValue('G1', "Vizsgálat dátuma");
        $objPHPExcel->getActiveSheet()->SetCellValue('H1', "Elvégzett vizsgálatok");
        $objPHPExcel->getActiveSheet()->SetCellValue('I1', "Alkalmassági státusz");
        $objPHPExcel->getActiveSheet()->SetCellValue('J1', "Alkalmassági idő");
        $objPHPExcel->getActiveSheet()->SetCellValue('K1', "Következő vizsg.");
        $objPHPExcel->getActiveSheet()->SetCellValue('L1', "Korlátozás/Megjegyzés");


        while ($result = sql_fetch_array($request)) {
            //Extra vizsgálatok listázáa:
            $request_extra = sql_query("SELECT a.megnev FROM extra_szolg es	LEFT JOIN arak a ON a.id = es.szurestipus_id WHERE idopont_id=?", array($result["id"]));

            $extrak = "";
            while ($extra = sql_fetch_array($request_extra)) {
                $extrak = $extrak.", ".$extra['megnev'];
            }
            if ($extrak != "") {
                $extrak = substr($extrak, 1);
            }

            //Ciklus változók:
            $status 	= "";
            $period 	= "";
            $limitation = "";
            $next_test  = "";

            if ($result['alkalmassag'] == "I") {
                $status 	= "Alkalmas";
                $period 	= $result['alkalmassagido']." hónap";
                $next_test 	= date("Y-m-d",strtotime($result['datum']." +".$result['alkalmassagido']." month"));
                $limitation = $result['alkalmassagkorl'];
            }
            if ($result['alkalmassag'] == "N") {
                $status 	= "Alkalmatlan";
                $period 	= "";
                $next_test 	= "";
                $limitation = $result['alkalmassagkorl'];
            }
            if ($result['alkalmassag'] == "IN") {
                $status 	= "Ideiglenesen nem alkalmas";
                $period 	= $result['alkalmassagido']." hónap";
                $next_test 	= $result['alkalmassagikhet']." hét";
                $limitation = $result['alkalmassagkorl'];
            }
            if ($result['alkalmassag'] == "K") {
                $status 	= "Korlátozottan alkalmas";
                $period 	= $result['alkalmassagido']." hónap";
                $next_test 	= date( "Y-m-d", strtotime( $result['datum']." +".$result['alkalmassagido']." month" ));
                $limitation = $result['alkalmassagkorl'];
            }
            if ($result['alkalmassag'] == "" && $result['alkalmassagkorl'] != "") {
                $limitation = $result['alkalmassagkorl'];
            }

            //Excel adatsorok:
            $objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, $result['nev']);
            $objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, $result['szuldatum']);
            $objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, $result['taj']);
            //$objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, $result['torzsszam']); //nincs törzsszám!
            $objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, $result['munkakor']);
            $objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, $result['orvos']);
            $objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, $result['datum']);
            $objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, $extrak);
            $objPHPExcel->getActiveSheet()->SetCellValue('I'.$rowCount, $status);
            $objPHPExcel->getActiveSheet()->SetCellValue('J'.$rowCount, $period);
            $objPHPExcel->getActiveSheet()->SetCellValue('K'.$rowCount, $next_test);
            $objPHPExcel->getActiveSheet()->SetCellValue('L'.$rowCount, $limitation);

            $rowCount++;
        }

        //Fájl véglegesítése:
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        ob_start();
        $objWriter->save('php://output');
        $xlsData = ob_get_contents();
        $contact_image_data = "data:application/vnd.ms-excel;base64,".base64_encode($xlsData);
        $data = substr($contact_image_data, strpos($contact_image_data, ","));
        $encoding = "base64";
        $type = "application/vnd.ms-excel";
        ob_end_clean();

        //Email(ek) készítése:
        $mail = new PHPMailer();
        $mail->From     = Booking_Constants::NO_REPLY_ADDRESS;
        $mail->FromName	= Booking_Constants::COMPANY_NAME;
        $mail->AddAddress("m.gergely9409@gmail.com");
        //$mail->AddAddress("jns@jns.hu");
        foreach ($mails as $email) {
            $mail->AddAddress($email, $email);
        }
        $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
        $mail->AddStringAttachment(base64_decode($data), $filename.".xlsx", $encoding, $type);
        $mail->IsHTML(true);

        $t = iconv("UTF-8","ISO-8859-2",$releaseDate." napi riport");

        $mbody = " ";

        $mail->Subject = $t;
        $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
        //$mail->AddAttachment("");
        $mail->Send();
    }


    public function dataField($fieldName) {
        $lang = new Lang();
        $webText = $lang->webText;

        $translateKey = $fieldName;
        $field        = $fieldName;
        $html         = "";
        $extraHTML    = "";
        $extraRow     = "";
        $width        = 250;

        switch ($fieldName) {
            case "email":
                $extraRow = "<tr><td></td><td>{$webText["kerjukugyeljenemail"]}</td></tr>";
                break;
            case "nev":
                break;
            case "telefon":
                $translateKey = "mobil";
                //$extraRow = "<tr><td></td><td>{$webText["mobiltip"]}</td></tr>";
                break;
            case "szuldatum":
                $extraHTML = "<tr><td>{$webText["szuletesidatum"]}: #requiredmark#</td><td>".$this->datumSelector($_POST["szuldatum"],"szuldatum")."</td></tr>";
                break;
            case "szulhely":
                if ($_SESSION['helyszindata']['id'] == 46) {
                    $hidden = true;
                }
                $translateKey = "szuletesihely";
                break;
            case "neme":
                $extraHTML = "<tr><td>{$webText["neme"]}: #requiredmark#</td><td><input type='radio' name='neme' value='1' ".($_POST["neme"]==1?"checked":"")."/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_POST["neme"]==2?"checked":"")."/> {$webText["no"]} </td></tr>";
                break;
            case "anyjaneve":
                if ($_SESSION['helyszindata']['id'] == 46) {
                    $hidden = true;
                }
                break;
            case "irsz":
                $width = 60;
                if ($_SESSION['helyszindata']['id'] == 46) {
                    $hidden = true;
                }
                break;
            case "varos":
                //temp 1
                if ($_SESSION['helyszindata']['id'] == 46) {
                    $hidden = true;
                }
                break;
            case "utca":
                //temp 2
                if ($_SESSION['helyszindata']['id'] == 46) {
                    $hidden = true;
                }
                break;
            case "munkakor":
                if (!in_array($_SESSION["helyszindata"]["domain"],array("bejelentkezes","gyor-bejelentkezes"))) {
                    $hidden = true;
                }
        }

        $required = $this->getFieldRequired($field);
        $hidden = $this->getFieldHidden($field);

        if (!$hidden) {
            if (empty($extraHTML)) {
                $html.= "<tr><td>{$webText[$translateKey]}: #requiredmark#</td><td><input class='inputbox' style='width:{$width}px;' type='text' name='{$field}' value='{$_POST[$field]}' /></td></tr>";
            } else {
                $html.= $extraHTML;
            }
        }
        $html.= $extraRow;
        $html = str_replace("#requiredmark#",$required?"*":"", $html);

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

}