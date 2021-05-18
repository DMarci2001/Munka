<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class NotificationService {
    private $utils;

    public function __construct() {
        $this->utils = new Utils();
    }

    public function createNotificationRecord($tipus, $objectid, $destination, $subject = "", $text = "") {
        $uid = $_SESSION["adminuser"]["id"] ?? 0;

        sql_query("INSERT INTO notifications SET datum=now(), tipus=?, objectid=?, destination=?, targy=?, szoveg=?, uid=?", [$tipus, $objectid, $destination, $subject, $text, $uid]);
    }

    public function sendUserReservationNotification($id) {
        //visszaigazoló levél a foglalás sikerességéről a felhasználónak

        $res = sql_query("SELECT " . $this->utils->cimLangQuery("helyszin") . ",sz.megnev AS szurestipus, sz.megnev_en AS szurestipus_en, sz.megnev_de AS szurestipus_de, f.*, c.megnev as cegnev, c.email as cegemail, c.foglalasemail, c.domain, o.nev as orvosnev 
        FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
		LEFT JOIN orvosok o ON o.id=f.`orvosassigned` 
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=?",  [$id]);

        if ($row = sql_fetch_array($res)) {
            if (sql_fetch_array(sql_query("select id from notifications where tipus='usernotification' and objectid=?", [$id])) && !isset($_GET["mailtest"])) {
                return;
            }

            if ($row["rlang"] == "en" && $row["szurestipus_en"] != "") $row["szurestipus"] = $row["szurestipus_en"];
            if ($row["rlang"] == "de" && $row["szurestipus_de"] != "") $row["szurestipus"] = $row["szurestipus_de"];

            if ($row["noreservation"] == 0) {
                $mailTemplate = $this->userMailTemplate($row);
            } else {
                $mailTemplate = $this->userMailTemplateWebDoctor($row);
            }

            $mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress($row["email"]);
            if (!empty(Booking_Constants::USER_BCC_MAIL)) {
                $mail->AddBCC(Booking_Constants::USER_BCC_MAIL);
            }
            $mail->CharSet = "UTF-8";
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);

            $mail->Subject = $mailTemplate["subject"];
            $mail->Body = $mailTemplate["body"];
            //$mail->AddAttachment("");

            if ($row["noreservation"] == 0) {
                //csak ha nem webdoctor
                $mail->addStringAttachment($this->getCalendarItem($row), 'foglalas.ics', 'base64', 'text/calendar');
            }

            $mail->Send();

            $this->createNotificationRecord("usernotification", $id, $row["email"], $mailTemplate["subject"], $mailTemplate["body"]);
        }
    }


    public function sendToCegAndOrvos($id, $force = 0, $test = 0) {
        if (Utils::isDemoSite()) {
            return;
        }

        $fids[] = $id;
        $res = sql_query("select id from foglalasok where parentid=?", [$id]);
        while ($row = sql_fetch_array($res)) {
            $fids[] = $row["id"];
        }

        //orvos kikeresése és értesítése
        $resf = sql_query("SELECT h.cim AS helyszin,sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail,c.calendaritem FROM foglalasok f
		LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
		LEFT JOIN cegek c on c.id=f.cegid
		LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
		WHERE f.id in (" . implode(",", $fids) . ")");

        while ($rowf = sql_fetch_array($resf)) {
            if (sql_fetch_array(sql_query("select id from notifications where tipus='doctornotification' and objectid=?", [$rowf["id"]])) && $force == 0) {
                return;
            }


            $cegId = $rowf["cegid"];
            if ($rowo = sql_fetch_array(sql_query("select * from orvosok where id=?", [$rowf["orvosassigned"]]))) {
                $resp = sql_query("select * from smsphones where orvosid=? and smsfoglalas=1 and smsgroupfoglalas=0 and instr(cegek, ?)", [$rowo["id"], "|{$cegId}|"]);
                while ($rowp = sql_fetch_array($resp)) {
                    if ($test == 1) {
                        $rowp["tel"] = "06209996183";
                    }
                    $this->utils->sendSMS(trim($rowp["tel"]), Booking_Constants::COMPANY_NAME_SHORT . " időpont foglalása érkezett: " . substr($rowf["datum"], 0, 16) . " {$rowf["helyszin"]}");
                }

                if (!empty(trim($rowo["email"])) || $test == 1) {
                    if ($rowf["noreservation"] == 0) {
                        $mailTemplate = $this->orvosMailTemplate($rowf, $rowo);
                    } else {
                        $mailTemplate = $this->orvosMailTemplateRemote($rowf, $rowo);
                    }

                    $mail = new PHPMailer();
                    $mail->FromName = Booking_Constants::COMPANY_NAME;
                    if ($test == 1) {
                        $mail->AddAddress("jns@jns.hu");
                    } else {
                        $mail->AddAddress($rowo["email"]);
                    }

                    $mail->From = $mailTemplate["from"];
                    $mail->AddReplyTo($mailTemplate["from"]);
                    $mail->IsHTML(true);
                    $mail->CharSet = "UTF-8";
                    $mail->Subject = $mailTemplate["subject"];
                    $mail->Body = $mailTemplate["body"];

                    if (isset($mailTemplate["docs"])) {
                        foreach ($mailTemplate["docs"] as $docData) {
                            $mail->addStringAttachment($docData["raw"], $docData["filename"]);
                        }
                    }

                    if ($rowf["noreservation"] == 0) {
                        $mail->addStringAttachment($this->getCalendarItem($rowf), 'foglalas.ics', 'base64', 'text/calendar');
                    }

                    $mail->Send();

                    $this->createNotificationRecord("doctornotification", $rowf["id"], $rowo["email"], $mailTemplate["subject"] ?? "", $mailTemplate["body"] ?? "");
                }
            }
        }

        $res = sql_query("SELECT o.`nev` AS orvosnev,o.`email` AS orvosemail,o.hmedemail,h.cim AS helyszin,sz.megnev AS szurestipus,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
        LEFT JOIN orvosok o ON o.`id`=f.`orvosassigned`
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=?", [$id]);

        if ($row = sql_fetch_array($res)) {
            if ($row["foglalasemail"] == 1) {
                if (!sql_fetch_array(sql_query("select id from notifications where tipus='cegnotification' and objectid=?", [$row["id"]])) || $force == 1) {
                    $packText = $this->_getPackText($row);

                    $mail = new PHPMailer();
                    $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
                    $mail->FromName = Booking_Constants::COMPANY_NAME;
                    if ($test == 1) {
                        $mail->AddAddress("jns@jns.hu");
                    } else {
                        $mail->AddAddress($row["cegemail"]);
                        if (!empty(trim($row["hmedemail"]))) {
                            $row["hmedemail"] = str_replace(" ", "", $row["hmedemail"]);
                            $addresses = explode(",", $row["hmedemail"]);

                            foreach ($addresses as $address) {
                                $mail->AddAddress($address);
                            }
                        }
                    }
                    $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
                    $mail->IsHTML(true);
                    $mail->CharSet = "UTF-8";

                    $t = "{$row["cegnev"]} - időpont regisztráció";

                    $mbody = "Név: {$row["nev"]}<br>";
                    $mbody .= "Cég: {$row["cegnev"]}<br>";
                    $mbody .= "TAJ: {$row["taj"]}<br>";
                    $mbody .= "Munkakör: {$row["munkakor"]}<br>";
                    $mbody .= "Telefon: {$row["telefon"]}<br><br>";
                    $mbody .= "<b>Időpont: {$row["datum"]}</b><br><br>";
                    $mbody .= "Szűréstípus: {$row["szurestipus"]}<br>";
                    $mbody .= $packText;
                    $mbody .= "Helyszín: {$row["helyszin"]}<br>";
                    if ($row["megj"] != "") $mbody .= "Megjegyzés: {$row["megj"]}<br>";
                    $mbody .= "<br/>";

                    if ($row["orvosnev"] != "" && $row["orvosemail"] != "") {
                        $mbody .= "Értesített orvos: {$row["orvosnev"]} ({$row["orvosemail"]})";
                    }

                    $mail->Subject = $t;
                    $mail->Body = $mbody;
                    $mail->Send();

                    $this->createNotificationRecord("cegnotification", $row["id"], $row["cegemail"].",".$row["hmedemail"], $t, $mbody);
                }
            }
        }

    }

    public function sendUserVisszaIgazolas($id) {
        $lang = new Lang();
        //Visszaigazolás a foglalásról, megerősítés kérése
        $h = "cim";
        if ($_SESSION["helyszindata"]["nocim"] == 1) $h = "megnev";

        $res = sql_query("SELECT h.{$h} AS helyszin,sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail,o.nev as orvosnev FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
		LEFT JOIN orvosok o ON o.id=orvosassigned
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=?", [$id]);

        if ($row = sql_fetch_array($res)) {
            if ($row["rlang"] == "en" && $row["szurestipus_en"] != "") $row["szurestipus"] = $row["szurestipus_en"];
            if ($row["rlang"] == "de" && $row["szurestipus_de"] != "") $row["szurestipus"] = $row["szurestipus_de"];

            $mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress($row["email"]);
            if (!empty(Booking_Constants::USER_BCC_MAIL)) {
                $mail->AddBCC(Booking_Constants::USER_BCC_MAIL);
            }
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);

            $webTextLocal = $lang->getWebTexts($row["rlang"]);
            $t = iconv("UTF-8", "ISO-8859-2", $webTextLocal["mailtitleerositsdmeg"]);

            $mbody = "";

            if ($row["rlang"] == "de") {
                $mbody = "<h2>Már majdnem kész!</h2>
                Ha nem erősíti meg <b>1 órán belül</b>, a foglalása automatikusan <b>törlődik.</b><br/>
                {$webTextLocal["nev"]}: {$row["nev"]}<br>
                {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
                <b>Időpont: {$row["datum"]}</b><br>
                {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
                {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
				" . ($row["cegid"] == 6 ? "Ellátó orvos: {$row["orvosnev"]}<br>" : "") . "
                <br/>
                Az időpont foglalásának megerősítéséhez <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingvalidate&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>kattintson ide</a><br>
                <br/>
                Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;
            }
            if ($row["rlang"] == "en") {
                $mbody = "<h2>Almost done!</h2>
                if you do not confirm <b>within 1 hour</b>, your reservation will be automatically <b>canceled</b>.<br/>
                {$webTextLocal["nev"]}: {$row["nev"]}<br>
                {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
                <b>Time: {$row["datum"]}</b><br>
                {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
                {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
                <br/>
                To confirm your reservation <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingvalidate&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>click here</a><br>
                <br/>
                Regards<br>" . Booking_Constants::COMPANY_NAME;
            }

            if ($mbody == "") {
                $mbody = "<h2>Már majdnem kész!</h2>
                Ha nem erősíti meg <b>1 órán belül</b>, a foglalása automatikusan <b>törlődik.</b><br/>
                {$webTextLocal["nev"]}: {$row["nev"]}<br>
                {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
                <b>Időpont: {$row["datum"]}</b><br>
                {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
                {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
				" . ($row["cegid"] == 6 ? "Ellátó orvos: {$row["orvosnev"]}<br>" : "") . "
                <br/>
                Az időpont foglalásának megerősítéséhez <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingvalidate&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>kattintson ide</a><br>
                <br/>
                Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;
            }

            $mail->Subject = $t;
            $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
            //$mail->AddAttachment("");
            $mail->Send();

            $this->createNotificationRecord("usermegerosito", $id, $row["email"], $t, $mbody);
        }
    }


    public function reservationReminder($data){
        $deleteURL = "http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$data["id"]}&rk={$data["rkod"]}&setlang={$data["rlang"]}";

        $mail = new PHPMailer();
        $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
        $mail->FromName = Booking_Constants::COMPANY_NAME;
        $mail->AddAddress($data["email"]);
        $mail->AddBCC("jns@jns.hu");
        $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
        $mail->IsHTML(true);
        $mail->CharSet = "UTF-8";

        $t = iconv("UTF-8", "ISO-8859-2", "Időpontfoglalás Emlékeztető - {$data["megnev"]}");

        $mbody = "<p style='font-size:18px;font-weight:bold;font-family:calibri'>Tisztelt hölgyem/uram!</p>";
        $mbody.= "";
        $mbody.= "<p style='font-family:calibri'>Szeretnénk emlékeztetni, hogy <strong>".date("Y.m.d H:i",strtotime($data["datum"]))."-ra</strong> időpontfoglalása van,<br><br>";

        $mbody.= "<table style='font-family:calibri'>";
        $mbody.= "<tr><td style='font-weight:bold'> - <td/><td style='font-weight:bold'>Ellátás megnevezése:</td><td style='padding:0px 10px'>{$data["megnev"]},</td></tr>";
        $mbody.= "<tr><td style='font-weight:bold'> - <td/><td style='font-weight:bold'>Helyszín:</td><td style='padding:0px 10px'>{$data["cim"]},</td></tr>";
        $mbody.= "<tr><td style='font-weight:bold'> - <td/><td style='font-weight:bold'>Ellátó orvos vagy<br> rendelő megnevezése:</td><td style='padding:0px 10px' valign='middle'>{$data["nev"]}</td></tr>";
        $mbody.= "</table>";

        $mbody.= "<br><br>";
        $mbody.= "Az ellátás folytonossága érdekében kérjük, hogy legalább <strong>15 percel</strong> a lefoglalt időpont előtt sziveskedjék megjelenni!<br>";
        $mbody.= "Ha bármilyen okból nem tud megjelenni a vizsgálaton, vagy lemondaná a foglalást kérem <a style='color:#a00;' href='{$deleteURL}' target='_blank'>kattintson ide az időpont törléséhez.</a></p>";

        $mbody.= "<p style='font-family:calibri'>Köszönjük, ".Booking_Constants::COMPANY_NAME." Csapata!</p>";

        $mail->Subject = $t;
        $mail->Body = $mbody;
        $mail->Send();

        sql_query("UPDATE foglalasok SET emlekezteto_mail = 1 WHERE id=?",array($data['id']));

        $this->createNotificationRecord("emlekezteto", $data["id"], $data["email"], $t, $mbody);
    }


    private function orvosMailTemplate($rowf, $rowo) {
        $mbody = "";

        $from = Booking_Constants::NO_REPLY_ADDRESS;;

        if ($rowo["visszaigazol"] == 1 && $rowo["visszaigazolemail"] != "") {
            $mbody .= "Kedves {$rowo["nev"]}!<br>
                            <br>
                            Foglalása érkezett a " . Booking_Constants::COMPANY_NAME_SHORT . " foglalási rendszerén keresztül az alábbi adatokkal. Kérjük erre az levélre válaszolva jelezze, hogy tudja-e fogadni a pacienst. Köszönjük!<br>
                            <br>
                            <hr>
                            <br>";
            $from = $rowo["visszaigazolemail"];
        }

        $mbody .= "Név: {$rowf["nev"]}<br>";
        $mbody .= "Cég: {$rowf["cegnev"]}<br>";
        $mbody .= "TAJ: {$rowf["taj"]}<br>";
        $mbody .= "Munkakor: {$rowf["munkakor"]}<br>";
        $mbody .= "Telefon: {$rowf["telefon"]}<br><br>";
        $mbody .= "<b>Időpont: {$rowf["datum"]}</b><br><br>";
        $mbody .= "Szűréstípus: {$rowf["szurestipus"]}<br>";
        $mbody .= "Helyszín: {$rowf["helyszin"]}<br>";
        if ($rowf["megj"] != "") $mbody .= "Megjegyzés: {$rowf["megj"]}<br>";
        $mbody .= "<br/>";

        $template["subject"] = "{$rowf["cegnev"]} - időpont regisztráció {$rowo["nev"]} részére";
        $template["body"] = $mbody;
        $template["from"] = $from;
        return $template;
    }

    private function orvosMailTemplateRemote($rowf, $rowo) {
        $mbody = "";

        $from = Booking_Constants::NO_REPLY_ADDRESS;;

        $mbody .= "Kedves {$rowo["nev"]}!<br>
        <br>
        WebDoctor megrendelése érkezett a " . Booking_Constants::COMPANY_NAME_SHORT . " foglalási rendszerén keresztül az alábbi adatokkal:<br>
        
        <hr>";

        $mbody .= "Név: {$rowf["nev"]}<br>";
        $mbody .= "TAJ: {$rowf["taj"]}<br>";
        $mbody .= "Szül. dátum: {$rowf['szuldatum']}<br>";
        $mbody .= "Neme: " . ($rowf['neme'] == 1 ? "Férfi" : "Nő") . "<br>";
        $mbody .= "Email: {$rowf["email"]}<br>";
        $mbody .= "Telefon: {$rowf["telefon"]}<br>";
        $mbody .= "Cím: {$rowf["irsz"]} {$rowf["varos"]}, {$rowf["utca"]}<br>";
        $mbody .= "Szűréstípus: {$rowf["szurestipus"]}<br><hr>";
        $mbody .= "{$rowf["questions"]}<br>";

        $mbody .= "<br/>";

        $template["subject"] = "{$rowf["cegnev"]} - WebDoktor megrendelés: {$rowf["nev"]}";
        $template["body"] = $mbody;
        $template["from"] = $from;

        $docAgent = new DocAgent();
        $res = sql_query("select * from dokumentumok where foglalasid=?", [$rowf["id"]]);
        while ($docData = sql_fetch_array($res)) {
            $docData["raw"] = $docAgent->getDoc($docData["id"]);
            $template["docs"][] = $docData;
        }

        return $template;
    }

    private function userMailTemplate($row) {
        $lang = new Lang();
        $webTextLocal = $lang->getWebTexts($row["rlang"]);
        $packText = $this->_getPackText($row);

        $extraMsg = "";

        if ($result = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE id = '" . intval($row["paciensid"]) . "'"))) {
            if ((strtotime("now") - strtotime($result["regtime"])) < 3600) {
                $c = explode(",", $row["domain"]);
                $extraMsg = "A kiállított leleteit és dokumentumait a " . Booking_Constants::SITE_PROTOCOL . "://{$c[0]}." . Booking_Constants::SITE_DOMAIN . " oldalon a taj számával megtekintheti online.<br/>";
            }
        }

        $mbody = "";
        $mbody .= "<h1>{$row["datum"]} - {$row["helyszin"]}</h1>";
        $mbody .= "{$webTextLocal["nev"]}: {$row["nev"]}<br>";
        $mbody .= "{$webTextLocal["telefon"]}: {$row["telefon"]}<br><br>";
        $mbody .= "<b>{$webTextLocal["idopont"]}: {$row["datum"]}</b><br><br>";
        $mbody .= "{$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>";
        $mbody .= ($row["cegid"] == 6 ? "Ellátó orvos: {$row["orvosnev"]}<br>" : "");
        $mbody .= "{$packText}";
        $mbody .= "{$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>";

        $resv = sql_query("SELECT * FROM visszaigazolok WHERE cegid='{$row["cegid"]}' AND (orvosid='{$row["orvosassigned"]}' OR orvosid=0) AND (helyszinid='{$row["helyszinid"]}' OR helyszinid=0) AND TRIM(szoveg)<>''");
        while ($rowv = sql_fetch_array($resv)) {
            $maplink = "";
            if ($rowv["mapurl"] != "") $maplink = "<a href='{$rowv["mapurl"]}'>Az útvonal térképen megjelenítéséhez kattintson ide.</a>";
            $rowv["szoveg"] = str_replace("#maplink#", $maplink, $rowv["szoveg"]);
            $mbody .= "<hr>" . nl2br($rowv["szoveg"]);
        }

        $mbody .= "<hr>";

        if ($row["rlang"] != "de" && $row["rlang"] != "en") {
            $mbody .= "Ha törölni szeretné ezt a foglalását, kérjük kattintson a következő linkre: <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>időpont regisztráció törlése</a><br>";
            $mbody .= "Amennyiben módosítani szeretné a foglalását, abban az esetben először törölje a régi időpontját a fenti linken, utána pedig regisztrálja újra.<br>{$extraMsg}";
            $mbody .= "<br/>";
            $mbody .= "Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;
        }
        if ($row["rlang"] == "de") {
            $mbody .= "Wenn Sie möchten Diese Termin Reservierung Canceln, bitte drücken Sie an Ihre Brief <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>Die Termin Registration Canceln</a> LINK.<br>";
            $mbody .= "Wenn Sie möchten Ihre Reservierung Verändern ,bitte Streichen Sie aus den anderen Zeitpunkt, dannach registrieren bitte nochmal.<br>";
            $mbody .= "<br/>";
            $mbody .= "Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;
        }
        if ($row["rlang"] == "en") {
            $mbody .= "If you wish to cancel this appointment, please click on link: <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>Cancellation of confirmed appointment</a><br>";
            $mbody .= "If you would like to modify your appointment, first cancel your old appointment then register it again.<br>";
            $mbody .= "<br/>";
            $mbody .= "Regards:<br>" . Booking_Constants::COMPANY_NAME;
        }

        $template["subject"] = "{$webTextLocal["sikeresidopontreg"]}";
        $template["body"] = $mbody;
        return $template;
    }

    private function userMailTemplateWebDoctor($row) {
        $mbody = "<b>Kedves Páciensünk,</b><br/>
        <br/>
        Köszönjük, hogy megtisztelt minket bizalmával és a Hungária Med-M Web-Doktor
        szolgáltatását választotta.<br/>
        A szolgáltatás költségének térítése sikeresen megtörtént (sikeres tranzakció).<br/><br/>
        Az Ön által választott szakorvos 24 órán belül elektronikus úton válaszol megkeresésére.<br/><br/>
        Amennyiben 1 napon belül nem kerülne továbbításra a szakorvosi vélemény, kérjük,
        ellenőrizze Spam /Promóciók mappában is. <br>
		Abban az esetben, ha leletét nem találja előbb
        említett mappákban sem kérjük, jelezze a problémát ügyfélkapcsolati munkatársunknál.<br/>
        <br/>
        <i>Pénzvisszafizetési garancia</i><br/>
        <br/>
        Elégedettsége fontos számunkra, így abban az esetben, ha panaszára a Web-Doktor
        szolgáltatás keretén belül nem tudunk megoldást nyújtani, úgy a teljes összeg
        visszautalásra kerül.<br/>
        <br/>
        Telefonos ügyfélkapcsolat: (Hétfőtől- Péntekig 8:00- 16:00 rendelési időben)<br/>
        +36 1 800 9333<br/>
        +36 30 633 0961<br/>
        Ügyfélkapcsolat:<br/>
        <a href='mailto:ugyfelkapcsolat@hungariamed.hu'>ugyfelkapcsolat@hungariamed.hu</a><br/>
        
        <br/>
        <p>
        <b>További jó egészséget kívánunk!</b>
        <br/>
        <img src='https://bejelentkezes.hungariamed.hu/images/logo-retina.png' width='200' alt='' />
        </p>";

        $template["subject"] = "Web-Doktor szolgáltatás megrendelése";
        $template["body"] = $mbody;
        return $template;
    }


    private function _getPackText($reservationData) {
        $packText = "";

        $rescs = sql_query("SELECT f.id,sz.* FROM foglalasok f LEFT JOIN szurestipusok sz ON sz.id=f.szurestipusid WHERE parentid=?", array($reservationData["id"]));
        while ($rowcs = sql_fetch_array($rescs)) {
            if ($reservationData["rlang"] == "en" && $rowcs["megnev_en"] != "") $rowcs["megnev"] = $rowcs["megnev_en"];
            if ($reservationData["rlang"] == "de" && $rowcs["megnev_de"] != "") $rowcs["megnev"] = $rowcs["megnev_de"];
            if (empty($packText)) {
                $packText .= "<br/>Csomag tartalma:<br/>";
            }
            $packText .= "{$rowcs["megnev"]}<br/>";
        }

        $rescs = sql_query("SELECT t.* FROM szurescsomagok_kapcs k LEFT JOIN szurestipusok t ON t.id=k.szurestipusid WHERE k.csomagid=? AND k.noreservation=1", [$reservationData["szurestipusid"]]);
        while ($rowcs = sql_fetch_array($rescs)) {
            if ($reservationData["rlang"] == "en" && $rowcs["megnev_en"] != "") $rowcs["megnev"] = $rowcs["megnev_en"];
            if ($reservationData["rlang"] == "de" && $rowcs["megnev_de"] != "") $rowcs["megnev"] = $rowcs["megnev_de"];
            if (empty($packText)) {
                $packText .= "<br/>Csomag tartalma:<br/>";
            }
            $packText .= "{$rowcs["megnev"]}<br/>";
        }

        if (!empty($packText)) {
            $packText .= "<br/>";
        }

        return $packText;
    }


    private function getCalendarItem($foglalasData) {
        $lang = new Lang();
        $webTextLocal = $lang->getWebTexts($foglalasData["rlang"]);

        $interval = (int) $foglalasData["rinterval"];
        if ($interval == 0) {
            $interval = 15;
        }
        $dateStart = date("Ymd", strtotime("{$foglalasData["datum"]} -0 hour"));
        $timeStart = date("His", strtotime("{$foglalasData["datum"]} -0 hour"));
        $dateEnd = date("Ymd", strtotime("{$foglalasData["datum"]} -0 hour + {$interval} minute"));
        $timeEnd = date("His", strtotime("{$foglalasData["datum"]} -0 hour + {$interval} minute"));

        $ical = "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//ical.marudot.com//iCal Event Maker
CALSCALE:GREGORIAN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
TZURL:http://tzurl.org/zoneinfo-outlook/Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:" . date("Ymd") . "T" . date("His") . "Z
UID:" . date("Ymd") . "T" . date("His") . "Z-" . $foglalasData["id"] . "@marudot.com
DTSTART;TZID=Europe/Berlin:{$dateStart}T{$timeStart}
DTEND;TZID=Europe/Berlin:{$dateEnd}T{$timeEnd}
SUMMARY:{$webTextLocal["idopontfoglalas"]} - {$foglalasData["nev"]}
DESCRIPTION:{$foglalasData["szurestipus"]}
LOCATION:{$foglalasData["helyszin"]}
ORGANIZER;CN=\"Hungária Med - m Kft . \":mailto:info@hungariamed.hu
END:VEVENT
END:VCALENDAR";

        return $ical;
    }

}