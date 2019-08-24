<?php

class Utils {

    public function __construct()
    {
        if (isset($_GET["downloaddoc"]) && isset($_GET["f"]) && isset($_GET["k"])) {
            $docAgent = new DocAgent();
            $docAgent->showDocBinary($_GET["f"], $_GET["k"]);
        }

        /*
        if (isset($_GET["tesztsms"]))
        {
            include ("includes/seeme-gateway-class.php");
            sendSMS("36209996183", "kód a regisztráció befejezéséhez: 1111");
            die("ok");
        }





        if (isset($_GET["tesztvissza"])) {
            sendVisszaIgazolas(89252);
            die("sent");
        }


        if (isset($_REQUEST['idopontfoglalasV2'])) {
            $formerror = "";
            //Hibás adat korrigálás:
            $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            //Mező ellenőrzés:
            //if( $_POST["taj"] == "" ) $formerror.= "{$webText["tajkotelezo"]}<br/>";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $formerror .= "{$webText["tajformat"]}<br/>";
            if ($_POST["helyszin"] == "0") $formerror .= "{$webText["helyszinkotelezo"]}<br/>";
            if ($_POST["datum"] == "") $formerror .= "{$webText["idopontkotelezo"]}<br/>";
            if ($_POST["szurestipus"] == "0") $formerror .= "{$webText["szurestipuskotelezo"]}<br/>";

            if ($_POST["email"] == "") $formerror .= "{$webText["emailkotelezo"]}<br/>";
            if ($_POST["nev"] == "") $formerror .= "{$webText["nevkotelezo"]}<br/>";
            if ($_POST["tel"] == "") $formerror .= "{$webText["telkotelezo"]}<br/>";
            if ($_POST["szuldatum"] == "") $formerror .= "{$webText["szulkotelezo"]}<br/>";
            if (!validateDate($_POST["szuldatum"], "Y-m-d")) $formerror .= "{$webText["szulformat"]}<br/>";

            //if( !isset( $_POST["neme"] )) $formerror.= "{$webText["nemekotelezo"]}<br/>";
            if (!isset($_POST["aszf"])) $formerror .= "{$webText["aszfkotelezo"]}<br/>";

            //Még nem bevett mezők:
            //if ($_POST["irsz"]=="") $formerror.="Az irányítószám megadása kötelező!<br/>";
            //if ($_POST["varos"]=="") $formerror.="A város megadása kötelező!<br/>";
            //if ($_POST["utca"]=="") $formerror.="Az utca megadása kötelező!<br/>";
            if (isset($_POST["munkakor"])) if ($_POST["munkakor"] == "") $formerror .= "{$webText["munkakorkotelezo"]}<br/>";
            else $_POST["munkakor"] = "";

            //Szabad időpont ellenőrzése:
            if ($_POST["datum"] != "" && !checkIdopontSzabad($_POST)) $formerror .= "{$webText["idopontlefoglaltak"]}<br>";

            //Captcha ellenőrzés ha a páciens nincs belépve:
            if (!isset($_SESSION["user"])) {
                if (isset($_POST["version2"])) {
                    if (isset($_POST["g-recaptcha-response"])) $captcha = $_POST["g-recaptcha-response"];
                    if (isset($captcha)) {
                        if (!$captcha) $formerror .= "{$webText["captchaerror1"]}<br/>";
                        else {
                            $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfCaTIUAAAAAF1-t94n7TBAsKov_dglwP6b8Luo&response=" . urlencode($captcha) . "&remoteip=" . $_SERVER["REMOTE_ADDR"]), true);
                            if ($response["success"] == false) $formerror .= "{$webText["captchaerror2"]}<br/>";
                        }
                    } else $formerror .= "{$webText["captchaerror3"]}<br/>";
                }
            }

            //Ha nincsen hiba akkor rögzítem az adatok az adatbázisban:
            if ($formerror == "") {
                if (!isset($_POST["tudoszuro"])) $_POST["tudoszuro"] = 0;

                $rn = rand(1000000, 9999999);

                $paciensId = 0;

                if (isset($_SESSION["user"]["id"])) $paciensId = intval($_SESSION["user"]["id"]);
                else {
                    $request_user = sql_query("SELECT * FROM felhasznalok WHERE (taj = ? OR email = ?) and cegid=?",
                        array($_REQUEST['taj'], $_REQUEST['email'], $_SESSION["helyszindata"]["id"]));
                    if (sql_num_rows($request_user) > 0) {
                        $userInfo = sql_fetch_array($request_user);
                        $paciensId = $userInfo['id'];
                    } else {
                        sql_query("INSERT INTO felhasznalok SET 
						   validated = 1, cegid = ?, regtime = NOW(), taj  = ?, email 	  = ?, nev 		 = ?, telefon   = ?, 
						   munkakor  = ?, irsz 	= ?, varos	 = ?, 	  utca = ?, szulhely  = ?, anyjaneve = ?, szuldatum = ? ",
                            array($_SESSION["helyszindata"]["id"], $_REQUEST['taj'], $_REQUEST['email'], $_REQUEST['nev'], $_REQUEST['tel'],
                                $_REQUEST['munkakor'], $_REQUEST['irsz'], $_REQUEST['varos'], $_REQUEST['utca'], $_REQUEST['szulhely'],
                                $_REQUEST['anyjaneve'], $_REQUEST['datum']
                            ));
                        $paciensId = sql_insert_id();
                    }
                }

                if (isset($_SESSION["user"]["id"])) $paciensId = intval($_SESSION["user"]["id"]);

                sql_query("INSERT INTO foglalasok SET 
				   regdatum  = NOW(), paciensid = ?, cegid = ?, datum 	 = ?, helyszinid = ?, 
				   szurestipusid = ?, nev 		= ?, email = ?, telefon  = ?, szuldatum  = ?, 
				   neme  		 = ?, taj       = ?, megj  = ?, rlang 	 = ?, rkod 		 = ?",
                    array($paciensId, $_SESSION["helyszindata"]["id"], $_POST["datum"], $_POST["helyszin"], $_POST["szurestipus"],
                        $_POST["nev"], $_POST["email"], $_POST["tel"], $_POST["szuldatum"],
                        $_POST["neme"], $_POST["taj"], $_POST["megj"], $_COOKIE["lang"], $rn));

                $fid = sql_insert_id();
                updateFoglalasData($fid);

                if ($_POST['kuponkod'] != "") {
                    $foglalas = sql_fetch_array(sql_query("SELECT fogl.datum, kl.foglalasid, fogl.szurestipusid FROM foglalasok fogl LEFT JOIN kupon_lista kl ON kl.foglalasid = fogl.id WHERE fogl.id = ? ", array($fid)));
                    $check = kuponCheck($_POST['kuponkod'], 3, date("Y-m-d", strtotime($foglalas['datum'])), $foglalas['szurestipusid']);
                    if ($check == "usable") {
                        $kupon = sql_fetch_array(sql_query("SELECT * FROM kuponkodok WHERE kod = ?", array($_POST['kuponkod'])));
                        sql_query("INSERT INTO kupon_lista SET kuponid = ?, kuponkod = ?, foglalasid = ?",
                            array($kupon['id'], $kupon['kod'], $fid));
                    }
                }

                $oid = selectFreeOrvosForIdopont($fid);
                sql_query("UPDATE foglalasok SET orvosassigned = ? WHERE id = ?", array($oid, $fid));

                if (isset($_SESSION["beutaloid"]) && isset($_SESSION["user"]) && $rowb = sql_fetch_array(sql_query("SELECT * FROM beutalok WHERE id = ?", array($_SESSION["beutaloid"])))) {
                    sql_query("UPDATE beutalok SET foglalasid = ? WHERE id=?", array($fid, $_SESSION["beutaloid"]));
                    sql_query("UPDATE fogalalasok SET megj = ? where id = ?", array($rowb["megj"], $fid));
                    unset($_SESSION["beutaloid"]);
                }

                //altipusok tárolása
                $res = sql_query("SELECT * FROM arak WHERE INSTR( cegid, ? ) and tipusid = ? and csomag=0", array("|{$_SESSION["helyszindata"]["id"]}|", $_POST["szurestipus"]));
                while ($row = sql_fetch_array($res)) {
                    if (isset($_POST["altipus{$row["id"]}"])) {
                        sql_query("INSERT INTO fizkapcs SET fid = ?, aid = ?, megnev = ?, ar = ?, valuta = ?",
                            array($fid, $row["id"], $row["megnev"], $row["price"], $row["penznem"]));
                    }
                }

                if (isset($_SESSION["remotebeutalo"]) || $_SESSION["helyszindata"]["visszaigazolas"] == 0) {
                    //orvos jött, akkor nem kérünk visszaigazolást, megyünk visszaigazolni automatikusan
                    header("Location:index.php?page=megerosites&id={$fid}&rk={$rn}");
                } else {
                    //visszaigazolást kérünk
                    sendVisszaIgazolas($fid);
                    header("Location:index.php?page=sikeresfoglalas");
                }

                die();
            }
        }





        if (isset($_GET["showidopontvalaszto"])) {
            $honnan = intval($_GET["honnan"]);
            if ($honnan < 0) $honnan = 0;

            $helyszin = intval($_GET["helyszin"]);
            $szurestipus = intval($_GET["szurestipus"]);
            $taj = $_GET["taj"];

            $szunnapok[] = "";
            $rows = sql_fetch_array(sql_query("select * from settings"));
            $n = explode(",", $rows["szunnapok"]);
            for ($i = 0; $i < count($n); $i++) {
                $szunnapok[] = trim($n[$i]);
            }

            $foglaltnapok[] = "";
            $res = sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap>=? and (szurestipusid=0 or szurestipusid=?)", array($helyszin, $_SESSION["helyszindata"]["id"], date("Y-m-d"), $szurestipus));
            while ($row = sql_fetch_array($res)) {
                $foglaltnapok[] = $row["nap"];
            }

            $res = sql_query("select datum,COUNT(*) AS hany,GROUP_CONCAT(szurestipusid) AS szurestipusok from foglalasok where helyszinid='{$helyszin}' and szurestipusid='{$szurestipus}' and datum>now() GROUP BY datum");
            while ($row = sql_fetch_array($res)) {
                $i = substr($row["datum"], 0, 16);
                $foglaltidopontok[$i] = $row;
            }

            if (!$rowmax = sql_fetch_array(sql_query("SELECT MIN(tol) as minrendeles,MAX(ig) as maxrendeles FROM orvos_beosztas WHERE helyszinid='{$helyszin}' and (cegid='{$_SESSION["helyszindata"]["id"]}') and (instr(tipusok,'|{$szurestipus}|')) HAVING MAX(tol) IS NOT NULL"))) {
                echo "<div style='margin:10px 0px;'>Erre a szűrés típusra nincsenek beállítva rendelési időpontok.</div>";
                die();
            }

            $res = sql_query("select b.*,o.nev as orvosnev from orvos_beosztas b
                left join orvosok o on o.id=b.orvosid
                where b.helyszinid='{$helyszin}' and (b.cegid='{$_SESSION["helyszindata"]["id"]}' or b.cegid=0) and (instr(b.tipusok,'|{$szurestipus}|'))");
            while ($row = sql_fetch_array($res)) {
                $beosztas[] = $row;
                $beosztasData[$row["nap"]] = $row;
                //$beosztasOrvos[$row["nap"]]=$row["orvosnev"];
            }

            echo "<div style='margin:10px 0px 10px 0px;'>";
            //echo "Itt lesz az időpontválasztó... most még csak fixen kirak pár lehetőséget.<br>";
            echo "<div>{$webText["valasszidopontot"]}:</div>";
            echo "<div style='margin-top:5px;'><a href='javascript:showIdoPontValaszto(" . ($honnan - 7) . ")'>{$webText["elo7"]}</a> | <a href='javascript:showIdoPontValaszto(" . ($honnan + 7) . ")'>{$webText["kov7"]}</a></div>";
            echo "<table><tr>";

            $dist = "6 hour";
            if ($helyszin == 1) $dist = "0 hour"; //jász utca bármikor foglalható

            for ($i = 0; $i <= 6; $i++) {

                $nap = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $i + $honnan, date("Y")));
                $wd = date("N", mktime(0, 0, 0, date("m"), date("d") + $i + $honnan, date("Y")));  //day of week
                $wn = date("W", mktime(0, 0, 0, date("m"), date("d") + $i + $honnan, date("Y")));  //number of week

                echo "<td valign='top'>";
                echo "<div style='background:#0a0;padding:2px 10px 2px 10px;color:#fff;font-weight:bold;text-align:center;'>{$nap}<br>{$webText["hetnap"][$wd]}</div>";

                $beginora = round(substr($rowmax["minrendeles"], 0, 2));
                $beginperc = round(substr($rowmax["minrendeles"], 3, 2));

                if (!isset($beosztasData[$wd]["binterval"])) {
                    echo "<div style='text-align:center;margin:5px;padding:5px 0px;border-radius:5px;'>{$webText["nincsrendeles"]}</div>";
                    echo "</td>";
                    continue;
                }

                $binterval = $beosztasData[$wd]["binterval"];
                $firstfreetime = "";
                for ($o = 0; $o <= 55; $o++) {
                    $ora = date("H:i", mktime($beginora, $beginperc + $o * $binterval, 0, date("m"), date("d"), date("Y")));
                    if (strtotime($ora) >= strtotime($rowmax["maxrendeles"])) break;

                    echo "<div style='text-align:center;'>";

                    $java = "nemfog();";
                    $class = "foglaltbutton";

                    if (isBeosztasWeekDay($beosztas, $wd, $wn) && !in_array($nap, $szunnapok)) {
                        if (strtotime("now + {$dist}") < strtotime("{$nap} {$ora}")) {
                            $hanyfoglalt = 0;
                            if (isset($foglaltidopontok["{$nap} {$ora}"])) $hanyfoglalt = $foglaltidopontok["{$nap} {$ora}"]["hany"];


                            if (!in_array("{$nap}", $foglaltnapok)) {
                                $szabad = isFreeIdopont($wd, $ora, $beosztas, $hanyfoglalt);
                                if ($szabad[0] == 1 || $szabad[0] == 2) {
                                    $java = "chooseIdoPont(\"{$nap} {$ora}\");return false;";
                                    $class = "foglalhatobutton";
                                    if ($szabad[0] == 2 && $firstfreetime != "") {
                                        $java = "nemfogs(\"{$firstfreetime}\");return false;";
                                        $class .= " halv";
                                    }
                                    if ($firstfreetime == "") $firstfreetime = $ora;
                                }
                            }
                        }
                    }

                    $t = "";
                    if ($_SESSION["helyszindata"]["id"] == 15) {
                        if (isset($beosztasData[$wd]["orvosnev"])) {
                            $t = "title='" . $beosztasData[$wd]["orvosnev"] . "'";
                        }
                    }

                    echo "<a class='{$class}' {$t} onclick='{$java}' href='#'>{$ora}</a>";
                    echo "</div>";
                }

                echo "</td>";

            }

            echo "</tr></table>";
            echo "</div>";
            die();
        }
        */

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
        //return $sz;
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

}