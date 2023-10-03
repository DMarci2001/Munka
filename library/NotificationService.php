<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class NotificationService {
    private $utils;

    public function __construct() {
        $this->utils = new Utils();
    }

    public static function getDefaultMailer():PHPMailer {
        $mail = new PHPMailer();

        if (Booking_Constants::SQL_DB == "keltexmed") {
            $mail->isSMTP();
            $mail->Host = "isp.itcoffee.hu";
            $mail->SMTPAuth = true;
            $mail->Username = "ugyfelkapcsolat@keltexmed.hu";
            $mail->Password = "6qWmXx7gC";
            $mail->SMTPSecure = "tls";
            $mail->Port = 25;

            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->CharSet = "UTF-8";
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);
        } else {
            $mail->isSMTP();
            $mail->Host = "mail.hungariamed.hu";
            $mail->SMTPAuth = true;
            $mail->Username = "web@hungariamed.hu";
            $mail->Password = "The9vae1";
            $mail->SMTPSecure = "tls";
            $mail->Port = 366;

            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->CharSet = "UTF-8";
            $mail->IsHTML(true);
        }

        return $mail;
    }

    public function createNotificationRecord($tipus, $objectid, $destination, $subject = "", $text = "") {
        $adminUser = new AdminUser();
        $uid = $adminUser->user["id"] ?? 0;

        sql_query("INSERT INTO notifications SET datum=now(), tipus=?, objectid=?, destination=?, targy=?, szoveg=?, uid=?", [$tipus, $objectid, $destination, $subject, $text, $uid]);
    }

    public static function hasNotification($tipus, $objectid):bool {
        return (bool)sql_fetch_array(sql_query("select id from notifications where tipus=? and objectid=?", [$tipus, $objectid]));
    }

    public static function getNotificationsByType($tipus, $objectid):array {
        return sql_query("select * from notifications where tipus=? and objectid=? order by datum desc", [$tipus, $objectid])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sendUserReservationNotification($id, $force = false) {
        //visszaigazoló levél a foglalás sikerességéről a felhasználónak

        $res = sql_query("SELECT " . $this->utils->cimLangQuery("helyszin") . ",sz.megnev AS szurestipus, sz.megnev_en AS szurestipus_en, sz.megnev_de AS szurestipus_de, sz.custompatientemail_option, sz.custompatientemail_text, f.*, c.megnev as cegnev, c.email as cegemail, c.foglalasemail, c.domain, o.nev as orvosnev,o.tel as orvostelefon 
        FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
		LEFT JOIN orvosok o ON o.id=f.`orvosassigned` 
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=?",  [$id]);

        if ($row = sql_fetch_array($res)) {
            if (self::hasNotification("usernotification", $id) && !isset($_GET["mailtest"]) && !$force) {
                return;
            }

            if ($row["fgroupid"] != "0") {
                return;
            }

            if ($row["rlang"] == "en" && $row["szurestipus_en"] != "") $row["szurestipus"] = $row["szurestipus_en"];
            if ($row["rlang"] == "de" && $row["szurestipus_de"] != "") $row["szurestipus"] = $row["szurestipus_de"];

            if ($row["datum"] == "1900-01-01 00:00:01") {
                $mailTemplate = $this->userMailTemplateManualBooking($row);
            } else {
                if ($row["noreservation"] == 0) {
                    $mailTemplate = $this->userMailTemplate($row);
                } else {
                    $mailTemplate = $this->userMailTemplateWebDoctor($row);
                }
            }

            if ($this->isVarolista($row)) {
                $mailTemplate = $this->userMailTemplateVarolista($row);
            }

            if (!empty($this->minimumTime)) {
                $row["datum"] = $this->minimumTime;
            }

            $mail = $this->getDefaultMailer();
            $mail->AddAddress($row["email"]);
            if (!empty(Booking_Constants::USER_BCC_MAIL)) {
                $mail->AddBCC(Booking_Constants::USER_BCC_MAIL);
            }

            $mail->Subject = $mailTemplate["subject"];
            $mail->Body = $mailTemplate["body"];
            //$mail->AddAttachment("");

            if ($row["noreservation"] == 0 && !$this->isVarolista($row)) {
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
            if ($rowf["datum"] == "1900-01-01 00:00:01") {
                return;
            }

            if (self::hasNotification("doctornotification", $rowf["id"]) && $force == 0) {
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

                    if ($rowf["fgroupid"] != 0) {
                        $mailTemplate = $this->orvosMailTemplateMultiTime($rowf, $rowo);
                    }

                    $mail = $this->getDefaultMailer();
                    if ($test == 1) {
                        $mail->AddAddress("jns@jns.hu");
                    } else {
                        $addresses = explode(",", $rowo["email"]);
                        foreach ($addresses as $address) {
                            $mail->AddAddress(trim($address));
                        }
                    }

                    if ($rowf["fgroupid"] != 0) {
                        $mail->addAddress("jnsmobil@gmail.com");
                    }

                    $mail->From = $mailTemplate["from"];
                    $mail->AddReplyTo($mailTemplate["from"]);
                    $mail->Subject = $mailTemplate["subject"];
                    $mail->Body = $mailTemplate["body"];

                    if (isset($mailTemplate["docs"])) {
                        foreach ($mailTemplate["docs"] as $docData) {
                            $mail->addStringAttachment($docData["raw"], $docData["filename"]);
                        }
                    }

                    if ($rowf["noreservation"] == 0 && $rowf["fgroupid"] == 0) {
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
                if (!self::hasNotification("cegnotification", $row["id"]) || $force == 1) {
                    $packText = $this->_getPackText($row);

                    $mail = $this->getDefaultMailer();
                    if ($test == 1) {
                        $mail->AddAddress("jns@jns.hu");
                    } else {
                        if (!empty(trim($row["cegemail"]))) {
                            $addresses = explode(",", $row["cegemail"]);
                            foreach ($addresses as $address) {
                                $mail->AddAddress(trim($address));
                            }
                        }

                        if (!empty(trim($row["hmedemail"]))) {
                            $addresses = explode(",", $row["hmedemail"]);
                            foreach ($addresses as $address) {
                                $mail->AddAddress(trim($address));
                            }
                        }
                    }

                    $subject = "{$row["cegnev"]} - időpont regisztráció";

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

                    $mail->Subject = $subject;
                    $mail->Body = $mbody;
                    $mail->Send();

                    $this->createNotificationRecord("cegnotification", $row["id"], $row["cegemail"].",".$row["hmedemail"], $subject, $mbody);
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

            $mail = $this->getDefaultMailer();
            $mail->AddAddress($row["email"]);
            if (!empty(Booking_Constants::USER_BCC_MAIL)) {
                $mail->AddBCC(Booking_Constants::USER_BCC_MAIL);
            }

            $webTextLocal = $lang->getWebTexts($row["rlang"]);
            $subject = $webTextLocal["mailtitleerositsdmeg"];

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

            $mail->Subject = $subject;
            $mail->Body = $mbody;
            $mail->Send();

            $this->createNotificationRecord("usermegerosito", $id, $row["email"], $subject, $mbody);
        }
    }


    public function reservationReminder($data){
        $deleteURL = "http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$data["id"]}&rk={$data["rkod"]}&setlang={$data["rlang"]}";

        $mail = $this->getDefaultMailer();
        $mail->AddAddress($data["email"]);
        $mail->AddBCC("jns@jns.hu");

        $subject = "Időpontfoglalás Emlékeztető - {$data["megnev"]}";

        $mbody = "<p style='font-size:18px;font-weight:bold;'>Tisztelt hölgyem/uram!</p>";
        $mbody.= "";
        $mbody.= "<p style=''>Szeretnénk emlékeztetni, hogy <strong>".date("Y.m.d H:i",strtotime($data["datum"]))."-ra</strong> időpontfoglalása van,<br><br>";

        $mbody.= "<strong>Ellátás megnevezése:</strong><br/>{$data["megnev"]}<br/>";
        $mbody.= "<strong>Helyszín:</strong><br/>{$data["cim"]}<br/>";
        $mbody.= "<strong>Ellátó orvos vagy rendelő megnevezése:</strong></br>{$data["nev"]}<br/>";

        $mbody.= "<br><br>";
        $mbody.= "Az ellátás folytonossága érdekében kérjük, hogy legalább <strong>15 percel</strong> a lefoglalt időpont előtt sziveskedjék megjelenni!<br>";
        $mbody.= "Ha bármilyen okból nem tud megjelenni a vizsgálaton, vagy lemondaná a foglalást kérem <a style='color:#a00;' href='{$deleteURL}' target='_blank'>kattintson ide az időpont törléséhez.</a></p>";

        $mbody.= "<p>Köszönjük, ".Booking_Constants::COMPANY_NAME." Csapata!</p>";

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();

        sql_query("UPDATE foglalasok SET emlekezteto_mail = 1 WHERE id=?",array($data['id']));

        $this->createNotificationRecord("emlekezteto", $data["id"], $data["email"], $subject, $mbody);
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

        $docAgent = new DocAgent();
        $res = sql_query("select * from dokumentumok where foglalasid=?", [$rowf["id"]]);
        while ($docData = sql_fetch_array($res)) {
            $docData["raw"] = $docAgent->getDoc($docData["id"]);
            $template["docs"][] = $docData;
        }

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

    private function orvosMailTemplateMultiTime($rowf, $rowo):array {
        $mbody = "";

        $from = Booking_Constants::NO_REPLY_ADDRESS;;

        if ($rowo["visszaigazol"] == 1 && $rowo["visszaigazolemail"] != "") {
            $dateLinks = "";

            $reservations = sql_query("select id, pass, datum from foglalasok where fgroupid=? and regdatum>=? order by datum",[$rowf["fgroupid"], date("Y-m-d 00:00:00", strtotime($rowf["regdatum"]))])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($reservations as $reservation) {
                $dateLinks.= "<a href='".Booking_Constants::MAIN_URL."/index.php?selectthistime={$reservation["id"]}&p={$reservation["pass"]}'>".date("Y.m.d. H:i", strtotime($reservation["datum"]))."</a><br/>";
            }

            $mbody .= "Kedves {$rowo["nev"]}!<br>
                            <br>
                            Foglalása érkezett a " . Booking_Constants::COMPANY_NAME_SHORT . " foglalási rendszerén keresztül az alábbi adatokkal. Kérjük a következő időpontok közül kattintson arra, amelyik megfelelő, és tudja fogadni a pacienst:<br>
                            <br>{$dateLinks}
                            <hr>
                            <br>";
            $from = $rowo["visszaigazolemail"];
        }

        $mbody .= "Név: {$rowf["nev"]}<br>";
        $mbody .= "Cég: {$rowf["cegnev"]}<br>";
        $mbody .= "TAJ: {$rowf["taj"]}<br>";
        $mbody .= "Munkakor: {$rowf["munkakor"]}<br>";
        $mbody .= "Telefon: {$rowf["telefon"]}<br>";
        $mbody .= "Szűréstípus: {$rowf["szurestipus"]}<br>";
        $mbody .= "Helyszín: {$rowf["helyszin"]}<br>";
        if ($rowf["megj"] != "") $mbody .= "Megjegyzés: {$rowf["megj"]}<br>";
        $mbody .= "<br/>";

        $template["subject"] = "{$rowf["cegnev"]} - időpont kiválasztás {$rowo["nev"]} részére";
        $template["body"] = $mbody;
        $template["from"] = $from;
        return $template;
    }

    private function isVarolista($reservationData) {
        return substr_count($reservationData["orvosnev"], "Várólista") != 0;
    }

    private function userMailTemplateVarolista($row) {
        $lang = new Lang();
        $webTextLocal = $lang->getWebTexts($row["rlang"]);

        $mbody = "Kedves Hölgyem/Uram!<br/>
            <br/>
            Köszönjük, hogy jelezte foglalási szándékát a várólistán, amennyiben lesz üresedés mindenképp értesíteni fogjuk Önt!<br/>
            <br/>
            Tisztelettel,<br/>
            Hungária Med-M";

        $template["subject"] = "{$webTextLocal["sikeresidopontreg"]}";
        $template["body"] = $mbody;
        return $template;
    }


    private function userMailTemplate($row) {
        $lang = new Lang();
        $webTextLocal = $lang->getWebTexts($row["rlang"]);
        $packText = $this->_getPackText($row);
        if (!empty($this->minimumTime)) {
            $row["datum"] = $this->minimumTime;
        }
        $extraMsg = ($row["custompatientemail_option"] == 1 && !empty($row["custompatientemail_text"]))? "<br/>".nl2br($row["custompatientemail_text"])."<br/>" : "<br/>";

        if ($result = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE id = '" . intval($row["paciensid"]) . "'"))) {
            if ((strtotime("now") - strtotime($result["regtime"])) < 3600) {
                $c = explode(",", $row["domain"]);
                $extraMsg = "<br/>A kiállított leleteit és dokumentumait a " . Booking_Constants::SITE_PROTOCOL . "://{$c[0]}." . Booking_Constants::SITE_DOMAIN . " oldalon a taj számával megtekintheti online.<br/>";
            }
        }

        $mbody = "";
        $mbody .= "<h1>".date("Y.m.d. H:i", strtotime($row["datum"]))." - {$row["helyszin"]}</h1>";
        $mbody .= "{$webTextLocal["nev"]}: {$row["nev"]}<br>";
        if (!empty($row["telefon"])) {
            $mbody .= "{$webTextLocal["telefon"]}: {$row["telefon"]}<br>";
        }
        $mbody .= "<br>";
        if (!$this->isVarolista($row)) {
            $mbody .= "<b>{$webTextLocal["idopont"]}: ".date("Y.m.d. H:i", strtotime($row["datum"]))."</b><br><br>";
        }

        $szuresTipus = $row["szurestipus"];
        if (CompanyService::isAuchan()) {
            $szuresTipus = $szuresTipus.". ".substr($row["megj"], strpos($row["megj"], "Választott vizsgálat"));
        }

        $mbody .= "{$webTextLocal["szurestipus"]}: {$szuresTipus}<br>";
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

            if (CompanyService::isBP() && false) {
                $mbody .= "A pszihoszociális kérdőívet az alábbi linken tudja megtekinteni és kitölteni:<br>";
                $mbody .= "<a target=\"_blank\" href=\"https://{$_SERVER["HTTP_HOST"]}/?page=psychosocialform&pass={$row["pass"]}\">Psyhosociális kérdőív link</a><br><br>";
            }

            if (CompanyService::isAuchan()) {
                $mbody.= "Ha bármi kérdése van, vagy a foglalt időpontját szeretné módosítani, kérjük hívja ezt a telefonszámot: 06 30 537 1008";
                $mbody.= "<hr>";
            }

            $mbody .= "Ha le szeretné mondani ezt a foglalását, kérjük kattintson a következő linkre: <a href='https://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>időpont foglalás törlése</a><br>";
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

    private function userMailTemplateManualBooking($row) {

        $cegInfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?",array($row["cegid"])));

        $deleteLink = "http://{$cegInfo["megnev"]}.hungariamed.hu/index.php?page=bookingdelete&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}";

        $mbody = "<p><span style=\"font-size: 14pt;\">Köszönjük, hogy időpontfoglaló rendszerünkben jelezte foglalási szándékát!</span></p>
        <p>Technikai okok miatt a megadott rendelőben jelenleg nem működik az online foglaló rendszer, ezért elnézését kérjük.</p>
        <p>Legyen szíves a kijelölt rendelőbe az alábbi telefonszámon időpontot egyeztetni: {$row["orvostelefon"]}</p>
        <p>Kérdés esetén keressen bennünket bátran a <a href=\"mailto:vodafone@hungariamed.hu\">vodafone@hungariamed.hu</a> e-mail címen.</p>

        <p>Ha módosítani szeretné az időpontjának esedékességét, kérem, törölje a jelenlegi foglalását az alábbi gombra kattintva:</p>
        <p>&nbsp;</p>
        <div style=\"height: 50px; text-align: center; padding-top: 25px;\">
        <a style=\"text-decoration: none; cursor: pointer; font-weight: bold; color: white; border-radius: 3px; bottom: 20px; text-transform: uppercase; padding: 15px 20px; box-sizing: border-box; background: #c02a2a;\" 
        href=\"{$deleteLink}\">Foglalási szándék törlése</a></div>
        
        <p>Üdvözlettel,</p>
        <p>Hungária Med-M Kft.</p>";

        $template["subject"] = "Időpont egyeztetés céljából hamarason keresni fogjuk!";
        $template["body"] = $mbody;
        return $template;
    }



    private string $minimumTime = "";
    private function _getPackText($reservationData):string {
        $packText = "";

        $rescs = sql_query("SELECT f.id, f.datum, f.cegid, f.megj, sz.* FROM foglalasok f LEFT JOIN szurestipusok sz ON sz.id=f.szurestipusid WHERE parentid=? order by datum", array($reservationData["id"]));
        while ($rowcs = sql_fetch_array($rescs)) {
            if ($reservationData["rlang"] == "en" && $rowcs["megnev_en"] != "") $rowcs["megnev"] = $rowcs["megnev_en"];
            if ($reservationData["rlang"] == "de" && $rowcs["megnev_de"] != "") $rowcs["megnev"] = $rowcs["megnev_de"];

            if (CompanyService::isAuchan($rowcs["cegid"])) {
                if (empty($this->minimumTime)) {
                    $this->minimumTime = $rowcs["datum"];
                }
                $name = substr($rowcs["megj"], strpos($rowcs["megj"], "Választott vizsgálat"));
                $packText.= "<br/>{$name}<br/>Időpont: ".date("Y.m.d H:i", strtotime($rowcs["datum"]))."<br/>";
            } else {
                if (empty($packText)) {
                    $packText .= "<br/>Csomag tartalma:<br/>";
                }
                $packText .= "{$rowcs["megnev"]}<br/>";
            }
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
        $companyName = Booking_Constants::COMPANY_NAME;
        $companyEmail = Booking_Constants::COMPANY_EMAIL;

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
ORGANIZER;CN=\"{$companyName}\":mailto:{$companyEmail}
END:VEVENT
END:VCALENDAR";

        return $ical;
    }


    public function sendMissingDataEmail($id) {
        $res = sql_query("SELECT " . $this->utils->cimLangQuery("helyszin") . ",sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail,c.domain,o.nev as orvosnev,
        CONCAT(SHA1(CONCAT(f.regdatum, f.id)), SHA1(CONCAT(f.nev, f.regdatum)), SHA1(CONCAT(f.id, f.nev, f.regdatum))) AS h 
        FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
		LEFT JOIN orvosok o ON o.id=f.`orvosassigned` 
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=?", array($id));

        if ($row = sql_fetch_array($res)) {
            $body = "Kedves {$row["nev"]}!<br/>
            <br/>
            Ezt a levelet azért kapja, mert  a FoglaljOrvost.hu felületén időpontot foglalt egészségközpontunkba.<br/>
            Az ügyintézés meggyorsítása és a várakozási idő csökkentése érdekében a következő űrlapon megadhatja a szükséges adatait.<br/>
            <br/>
            Az adatok megadásához <a href='".Booking_Constants::MAIN_URL."/index.php?page=missingdata&r={$row["id"]}&h={$row["h"]}'>kattintson ide</a><br/>
            <br/>
            Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;

            $mail = $this->getDefaultMailer();
            //$mail->AddAddress($row["email"]);
            $mail->AddAddress("jnsmobil@gmail.com");
            //if (!empty(Booking_Constants::USER_BCC_MAIL)) {
            //$mail->AddBCC("jns@jns.hu");
            //}

            $subject = "[".Booking_Constants::COMPANY_NAME_SHORT."] Kérjük adja meg az adatait";
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->Send();

            $this->createNotificationRecord("missingdata", $id, $row["email"], $subject, $body);
        }
    }

    public function newAdminPassEmail($userData) {
        $pchars = "abcdefghijklmnpqrstuvwxyz1234567899";
        $p = "";
        for ($i = 0; $i < 6; $i++) {
            $p .= substr($pchars, rand(0, strlen($pchars) - 1), 1);
        }

        $mail = $this->getDefaultMailer();
        $mail->AddAddress($userData["email"]);

        $subject = Booking_Constants::SITE_NAME . " - új jelszó";

        $mbody = "Kedves {$userData["nev"]}!<br/><br/>";
        $mbody .= "A " . Booking_Constants::SITE_NAME . " felületén új jelszó kérését kezdeményezte.<br/><br/>";
        $mbody .= "Felhasználóneve: <b>{$userData["username"]}</b><br/>";
        $mbody .= "Az új jelszava: <b>{$p}</b><br>";
        $mbody .= "<br/>";
        $mbody .= "Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();

        sql_query("update users set password=? where id=?", [md5($p), $userData["id"]]);
    }

    public function newUserPassEmail($userData, $lang = "hu") {
        $pchars = "abcdefghijklmnpqrstuvwxyz1234567899";
        $p = "";
        for ($i = 0; $i < Booking_Constants::GENERATED_PASSWORD_LENGTH; $i++) {
            $p .= substr($pchars, rand(0, strlen($pchars) - 1), 1);
        }

        $mail = self::getDefaultMailer();
        $mail->AddAddress($userData["email"]);

        $subject = "Új jelszó kérése";

        $mbody = "Kedves {$userData["nev"]}!<br/><br/>";
        $mbody .= "Az online bejelentkezési felületünkön új jelszó kérését kezdeményezte.<br/><br/>";
        $mbody .= "Az új jelszava: <b>{$p}</b><br><br>";
        $mbody .= "Az új jelszavát bejelentkezés követően az adatmódosítás menüpont alatt tudja megváltoztatni.<br/>";
        $mbody .= "<br/>";
        $mbody .= "Üdvözlettel:<br>".Booking_Constants::COMPANY_NAME;

        if ($_COOKIE["lang"] == "de") {
            $mbody = "Lieber {$userData["nev"]}!<br/><br/>";
            $mbody .= "Unsere online anmelden Oberfláche sie beginnen eine neue Kennwort anbietten.<br/><br/>";
            $mbody .= "Die neue Kennwort: <b>{$p}</b><br><br>";
            $mbody .= "Nach den anmelden können Sie um  einem neuem Kennwort bitten.<br/>";
            $mbody .= "<br/>";
            $mbody .= "Freundlichen Grüssen:<br>".Booking_Constants::COMPANY_NAME;
        }
        if ($_COOKIE["lang"] == "en") {
            $mbody = "Dear {$userData["nev"]}!<br/><br/>";
            $mbody .= "You have requested a new password on our reservation page.<br/><br/>";
            $mbody .= "Your new password: <b>{$p}</b><br><br>";
            $mbody .= "You can change your new password under the profile page.<br/>";
            $mbody .= "<br/>";
            $mbody .= "Regards<br>".Booking_Constants::COMPANY_NAME;
        }

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();

        sql_query("update felhasznalok set jelszo=?	where id=?", [md5($p), $userData["id"]]);
    }

    public function sendDebugEmail($subject, $mbody) {
        $mail = self::getDefaultMailer();
        $mail->AddAddress("jnsmobil@gmail.com");
        $mail->AddBCC("m.gergely9409@gmail.com");
        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();
    }

    public function sendEljottMail($foglalasData) {
        $mail = self::getDefaultMailer();
        //$mail->AddAddress($foglalasData["email"]); //ne élesítsd még
        //$mail->AddAddress("jns@jns.hu");

        if ($emailData = sql_fetch_array(sql_query("select * from ertekeles_formok where (instr(rule_cegids,'|{$foglalasData["cegid"]}|') or rule_cegids='all') and rule_mail=1 and rule_aftereljott=1"))) {
            $mailSzoveg = $emailData["mailszoveg_{$foglalasData["rlang"]}"];
            if ($mailSzoveg == "") $mailSzoveg = $emailData["mailszoveg_hu"];
            $mailSubject = $emailData["megnev_{$foglalasData["rlang"]}"];
            if ($mailSubject == "") $mailSubject = $emailData["megnev_hu"];
            if ($mailSzoveg != "" && $mailSubject != "") {
                $mailSzoveg = str_replace("#nev#", $foglalasData["nev"], $mailSzoveg);
                $mail->Subject = $mailSubject;
                $mail->Body = $mailSzoveg;
                //$mail->Send();
                sql_query("update foglalasok set eljottmail=1 where id=?", array($foglalasData["id"]));
            }
        }
    }

    function sendNotConfirmedReservationMessages($reservationId) {
        /*
        nem visszaigazolt foglalás esetén:
        - mail a paciensnek
        - mail a hmm-nek
        - sms a paciensnek
        */
        $h = "cim";
        if ($_SESSION["helyszindata"]["nocim"] == 1) {
            $h = "megnev";
        }

        $res = sql_query("SELECT h.{$h} AS helyszin,sz.megnev AS szurestipus,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=?", [$reservationId]);
        if ($row = sql_fetch_array($res)) {
            $mail = self::getDefaultMailer();
            $mail->AddAddress($row["email"]);
            $mail->AddBCC("jnsmobil@gmail.com");

            $subject = "Figyelem! Foglalását töröltük!";

            $mbody = "<h2>Foglalását töröltük!</h2>";
            $mbody .= "Előző levelünkben küldött megerősítő hivatkozásra nem kattintott rá, ezért a {$row["datum"]} időpontra szóló foglalását töröltük.<br/>";
            $mbody .= "Azonosító: {$row["id"]}<br/>";
            $mbody .= "<br/>";
            $mbody .= "Üdvözlettel:<br/>".Booking_Constants::COMPANY_NAME;

            $mail->Subject = $subject;
            $mail->Body = $mbody;
            $mail->Send();

            $mail = self::getDefaultMailer();
            $mail->AddAddress(Booking_Constants::RESERVATION_TO_ADDRESS);

            $subject = "Egy paciens foglalása törölve lett!";

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

            $mail->Subject = $subject;
            $mail->Body = $mbody;
            $mail->Send();

            $utils = new Utils();
            $utils->sendSMS($row["telefon"], "Figyelem, {$row["datum"]} foglalását visszaigazolás hiányában töröltük!");
        }
    }

    public function covidListMessage($covidListId) {
        $res = sql_query("SELECT f.email, n.* 
        FROM covid_oltas_naplo n
        LEFT JOIN felhasznalok f ON f.id=n.userid
        WHERE n.id=? and n.statusz in ('APPROVED', 'DENIED')", [$covidListId]);

        if ($data = sql_fetch_array($res)) {
            if ($data["statusz"] == "APPROVED") {
                $subject = "Oltás esemény regisztráció feldolgozva";

                $body = "Tisztelt Hölgyem/Uram!<br/>
                <br/>
                Az Ön által megadott adatokat sikeresen feldolgoztuk és leellenőriztük. <br/>
                A " . $data["regdatum"] . " időpontban megadott oltási eseményt hitelesítettük, ha további kérdése lenne, kérem forduljon bizalommal a HR osztályhoz.<br/>
                Köszönjük a részvételét a felmérésben!<br/>
                <br/>
                Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME."<br/><br/> 
                <img style='width:150px;' src='https://bejelentkezes.hungariamed.hu/images/hmm_logo_nagy.png' alt='" . Booking_Constants::COMPANY_NAME."' />";
                ;
            }

            if ($data["statusz"] == "DENIED") {
                $reason = "";
                if (!empty(trim($data["deniedtext"]))) {
                    $reason = "<strong>Az elutasítás oka:</strong><br/>".nl2br($data["deniedtext"])."<br/><br/>";
                }

                $subject = "Oltás esemény regisztráció feldolgozva";

                $body = "Tisztelt Hölgyem/Uram!<br/>
                <br/>
                Az Ön által megadott adatokat sikeresen feldolgoztuk és leellenőriztük.<br/> 
                A " . $data["regdatum"] . " időpontban megadott oltási eseményt nem tudtuk hitelesíteni, a megadott adatok nem egyeznek meg a regisztrált adatokkal, kérem, vegye fel a kapcsolatot kollegánkkal egyeztetés céljából!<br/>
                <br/>
                {$reason}
                Telefonszám: +36 30 750 0257<br/>
                E-mail: petrovszky.gergo@hungariamed.hu<br/>
                <br/>
                Köszönjük a részvételét a felmérésben!<br/>
                <br/>
                Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME."<br/><br/> 
                <img style='width:150px;' src='https://bejelentkezes.hungariamed.hu/images/hmm_logo_nagy.png' alt='" . Booking_Constants::COMPANY_NAME."' />";
                ;
            }

            $mail = $this->getDefaultMailer();
            $mail->AddAddress($data["email"]);
            //$mail->AddBCC("jnsmobil@gmail.com");

            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->Send();

            $this->createNotificationRecord("covidlistmessage", $covidListId, $data["email"], $subject, $body);
        }

    }


    public function newCompanyNotification($companyId) {
        if ($companyData = sql_query("select * from cegek where id=?", [$companyId])->fetch(PDO::FETCH_ASSOC)) {
            $adminUser = new AdminUser();
            $mail = self::getDefaultMailer();

            foreach (explode(",", Booking_Constants::REPORT_MAILS) as $email) {
                $mail->addAddress(trim($email));
            }

            $mail->Subject = "Új cég rögzítve a ".Booking_Constants::FOOTER_COPYRIGHT." bejelentkezőbe";

            $mail->Body = "Cég neve: {$companyData["megnev"]}<br/>Rögzítette: ".$adminUser->user["username"];
            $mail->send();
        }
    }

    public function tesztMessage() {
        $mail = self::getDefaultMailer();

        $mail->addAddress("jnsmobil@gmail.com");
        $mail->Subject = "Új teszt mail";
        $mail->Body = "ez egy teszt mail";
        $mail->send();
    }

    function deleteMessage($reservationId) {
        $res = sql_query("SELECT o.email as orvosemail, o.nev as orvosnev, h.cim AS helyszin,sz.megnev AS szurestipus,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
        LEFT JOIN orvosok o on o.id=f.orvosassigned
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=? and f.aktiv=1 and f.nev<>'nincs név'", [$reservationId]);

        if ($row = sql_fetch_array($res)) {
            if (filter_var($row["orvosemail"], FILTER_VALIDATE_EMAIL)) {
                $mail = self::getDefaultMailer();
                $mail->addAddress($row["orvosemail"]);
                $mail->addAddress(Booking_Constants::RESERVATION_TO_ADDRESS);
                if (filter_var($row["foglalta"], FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($row["foglalta"]);
                }
                //$mail->addBCC("jns@jns.hu");

                $subject = "Egy foglalás törölve lett! {$row["orvosnev"]} - {$row["helyszin"]}";

                $mbody = "<h2>Törölt foglalás adatai</h2>";
                $mbody .= "Orvos: {$row["orvosnev"]} - {$row["orvosemail"]}<br/>";
                $mbody .= "Név: {$row["nev"]}<br/>";
                $mbody .= "Telefon: {$row["telefon"]}<br/>";
                $mbody .= "Email: {$row["email"]}<br/>";
                $mbody .= "<b>Időpont: {$row["datum"]}</b><br/>";
                $mbody .= "Szűréstípus: {$row["szurestipus"]}<br/>";
                $mbody .= "Helyszín: {$row["helyszin"]}<br/>";
                $mbody .= "<br/>";

                $mail->Subject = $subject;
                $mail->Body = $mbody;
                $mail->Send();

                $this->createNotificationRecord("deletereservation", $row["id"], $row["orvosemail"], $subject, $mbody);
                if (filter_var($row["foglalta"], FILTER_VALIDATE_EMAIL)) {
                    $this->createNotificationRecord("deletereservation", $row["id"], $row["foglalta"], $subject, $mbody);
                }
            }
        }
    }

    public function sendLabShopMail($labShopData) {
        $mail = self::getDefaultMailer();
        $mail->AddAddress($labShopData["email"]);
        //$mail->AddAddress("jnsmobil@gmail.com");

        $urlResult = parse_url($labShopData["url"]);
        $domain = $urlResult["host"];

        $subject = "Foglaljon időpontot laborvizsgálatra most!";

        $mbody = "<strong>Kedves ügyfelünk!</strong><br><br/>";
        $mbody .= "Köszönjük, hogy a Hungária Med-M Kft labor szolgáltatását vette igénybe. Ha nem tudott időpontot foglalni még, nem kell újra kiválasztania a labor csomagját, az alábbi linken keresztül folytatni tudja az időpontválasztást!<br/>
        <br/>
        <br/>
        <a style='font-family:calibri;font-size:16px;font-weight:bold;color:white;border-radius:25px;text-transform:uppercase;padding:15px 20px;box-sizing:border-box;background:#474747;text-decoration:none' href='{$labShopData["url"]}' target='_blank'>Időpontfoglalás folytatása</a>
        <br/>
        <br/>
        <br/>
        Ha szeretné kipróbálni más csomagunkat is, vagy szeretné meglepni egy rokonát, ismerősét ne habozzon, tekintse meg egyéb szolgáltatásainkat!
        <br/>
        Nincs szebb ajándék a hosszú és egészséges életnél!<br/>
        <br/>
        <ul>
        <li><a style='color:#a90000;' href='https://{$domain}' target='_blank'>Labor csomagot szeretnék választani!</a></li>
        <li><a style='color:#a90000;' href='https://hungariamed.hu/' target='_blank'>Megszeretném tekinteni a teljes szolgáltatási palettájukat!</a></li>
        </ul>
        <br/>";
        $mbody .= "<br/>
                Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME." Csapata!<br/><br/> 
                <img style='width:150px;' src='https://bejelentkezes.hungariamed.hu/images/hmm_logo_nagy.png' alt='" . Booking_Constants::COMPANY_NAME."' />";

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();
    }


    public function sendManagerStatusMail() {
        $page = new AdminManagerStatusPage();

        $mail = self::getDefaultMailer();
        $mail->AddAddress("jnsmobil@gmail.com");
        $mail->AddAddress("kuzdyg@hungariamed.hu");
        $mail->AddAddress("marton.gergely@hungariamed.hu");

        $subject = "Manager csomag status ".date("Y-m-d");

        $mbody = $page->managerStatList(14);

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();
    }

    const OWNER_PASSWORD = "que3ikieP";

    public function sendLaborLeletEmail($id) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $adminUser = new AdminUser();
        if (empty($adminUser->user["email"])) {
            return;
        }

        if ($requestData = sql_query("SELECT r.nev, r.szuldatum, r.taj, r.email, c.megnev AS cegnev, r.id, r.pass, r.created, r.provider, r.foglalasid, r.laborpacks, r.resultpdf, r.ertesitve, r.ertesitesdatum, r.ertesitesemail, r.synlabdata, r.emailtext FROM labrequests r 
        LEFT JOIN foglalasok f ON f.id=r.foglalasid
        LEFT JOIN cegek c ON c.id=f.cegid
        WHERE r.id=?", [$id])->fetch(PDO::FETCH_ASSOC)) {
            $pdfFileName = Booking_Constants::DOCUMENT_PATH."labor".md5($id.rand(1,10000)).".pdf";
            $pdfFileNameEncripted = Booking_Constants::DOCUMENT_PATH."laborenc".md5($id.rand(1,10000)).".pdf";
            $outFileName = $requestData["nev"]." laborlelet.pdf";
            $userPassword = trim($requestData["taj"]);
            if (empty($userPassword)) {
                $userPassword = str_replace(".", "", str_replace("-", "", $requestData["szuldatum"]));
            }
            $ownerPassword = self::OWNER_PASSWORD;
            $patientEmail = $requestData["email"];

            file_put_contents($pdfFileName, base64_decode($requestData["resultpdf"]));

            $output = `pdftk {$pdfFileName} output {$pdfFileNameEncripted} owner_pw {$ownerPassword} user_pw {$userPassword}`;

            $mail = self::getDefaultMailer();
            $mail->AddAddress($patientEmail);
            //$mail->AddBCC("jnsmobil@gmail.com");
            //$mail->AddBCC("marton.gergely@hungariamed.hu");
            $mail->AddAttachment($pdfFileNameEncripted, $outFileName);

            $subject = $requestData["nev"]." labor lelet ".date("Y-m-d");
            $mbody = !empty($requestData["emailtext"]) ? nl2br($requestData["emailtext"]) : "Automatikus labor lelet küldés";

            if (Booking_Constants::SQL_DB == "hungariamed") {
                $mbody .= "<br/><img alt='Hungariamed-M Kft.' style='width:200px;' src='https://bejelentkezes.hungariamed.hu/images/hmm_logo_nagy.png' />";
            }

            $mail->From = $adminUser->user["email"];
            $mail->FromName = $adminUser->user["nev"];

            $mail->Subject = $subject;
            $mail->Body = $mbody;
            $mail->Send();

            unlink($pdfFileName);
            unlink($pdfFileNameEncripted);

            $admin = $_SESSION["adminuser"]["nev"] ?? "noname";
            $logText = "Kiküldve: ".date("Y.m.d H:i")." {$requestData["email"]} címre, {$admin} által<br/>";

            sql_query("update labrequests set ertesitve=1, ertesitesdatum=now(), ertesitesemail=?, ertesiteslog=CONCAT(?, ertesiteslog) where id=?", [$requestData["email"], $logText, $requestData["id"]]);
        }
    }

    public function checkPreviousNotifications($email,$tipus):array{
        if($q = sql_query("SELECT * FROM notifications WHERE destination=? AND tipus=? ORDER by datum DESC",array($email,$tipus))->fetchAll(PDO::FETCH_ASSOC)){
            return $q;
        }else{
            return [];
        }
       
    }


    public function sendReminderToFogleu($email,$content){

        //Helyettesítendő szövegek:
        

        $mail = self::getDefaultMailer();
        //$mail->AddAddress($email);
        //Teszt:
        $mail->addAddress("tesztemail@hungariamed.hu");
        $subject = $content["targy"];
        $mbody = $content["szoveg"];

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();
    }

    public function sendBFKHmarketing($email,$content){

        //Helyettesítendő szövegek:
        $filePath = "templates/labor_kiertekeles_es_ajanlas.pdf";
        $fileName = "Labor kiértékelés és ajánlás.pdf";

        $mail = self::getDefaultMailer();
        $mail->AddAddress($email);
        $mail->AddBCC("tesztemail@hungariamed.hu");
        //Teszt:
        //$mail->addAddress("tesztemail@hungariamed.hu");
        $subject = $content["targy"];
        $mbody = $content["szoveg"];

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->AddAttachment($filePath, $fileName);
        $mail->Send();
    }
}