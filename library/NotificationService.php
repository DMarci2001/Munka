<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use mikehaertl\pdftk\Pdf;

class NotificationService
{
    private $utils;

    public function __construct()
    {
        $this->utils = new Utils();
    }

    public static function getDefaultMailer(): PHPMailer
    {
        $mail = new PHPMailer();

        if (Booking_Constants::SQL_DB == "keltexmed") {
            $mail->isSMTP();
            $mail->Host = "mail.keltexmed.hu";
            $mail->SMTPAuth = true;
            $mail->Username = "keltexmed@keltexmed.hu";
            $mail->Password = "Keltex66";
            $mail->SMTPSecure = "tls";
            $mail->Port = 587;

            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->CharSet = "UTF-8";
            //$mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
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

    public function createNotificationRecord($tipus, $objectid, $destination, $subject = "", $text = "")
    {
        $adminUser = new AdminUser();
        $uid = $adminUser->user["id"] ?? 0;

        sql_query("INSERT INTO notifications SET datum=now(), tipus=?, objectid=?, destination=?, targy=?, szoveg=?, uid=?", [$tipus, $objectid, $destination, $subject, $text, $uid]);
        return sql_insert_id();
    }

    public static function hasNotification($tipus, $objectid): bool
    {
        return (bool)sql_fetch_array(sql_query("select id from notifications where tipus=? and objectid=?", [$tipus, $objectid]));
    }

    public static function getNotificationsByType($tipus, $objectid): array
    {
        return sql_query("select * from notifications where tipus=? and objectid=? order by datum desc", [$tipus, $objectid])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ghc_notification($data){

        $mail = $this->getDefaultMailer();
        $mail->AddAddress($data["email"]);
        //$mail->AddAddress("marton.gergely@hungariamed.hu");
        if (!empty(Booking_Constants::USER_BCC_MAIL)) {
            $mail->AddBCC(Booking_Constants::USER_BCC_MAIL);
        }

        $subject = "Suzuki GHC szűrés időpontfoglalás elindult!";
        $mail->Subject = $subject;

        $text  = "<h3>Tisztelt {$data["nev"]}!</h3>";

        //$text .= "<p>Elnézést kérünk a kellemetlenségért, az időpontfoglaló rendszer újra elérhető mostmár, ha korábbi időpontfoglalása nem megfelelő az Ön számára, kérem, keresse fel a korábban megjelölt Munkatársainkat az időpontja áthelyezésével kapcsolatban!</p>";
        $text .= "<p style=\"font-size:14px;\">Köszönjük jelentkezését a 2025 évi munkavállalói szűrésre!</p><br>";
        $text .= "<p style=\"font-size:14px;\">Időpontot az alábbi linkre kattintva foglalhat:</p>";
        $text .= "<p style=\"font-size:14px;\"><a style=\"color:#a00\" target=\"_blank\" href=\"https://ghc.hungariamed.hu/?page=login\">https://ghc.hungariamed.hu/?page=login</a></p><br><br>";
        $text .= "<p style=\"font-size:14px;\"><strong>Az Ön szűrőcsomagja:</strong> {$data["csomag"]}.</p><br><br>";
        $text .= "<p style=\"font-size:14px;\">Az időpontfoglaláshoz szüksége lesz a TAJ számára. A tovább lépéshez SMS kódot küldünk.</p><br>";
        $text .= "<p style=\"font-size:14px;\">A szűrés időpontja: <strong><span style=\"font-size:16px;\">2025.09.15-től 2025.10.03-ig</span></strong> tart.</p><br>";

        /*if($data["csomag"]=="Senior GHC csomag"){
            $text .= "<p style=\"font-size:14px;\">Senior szűrést 7:00-tól 12:00-ig végezzük. Két műszakos kollegák esetében a délutános műszak előtti délelőttön.</p><br>";

            $text .= "VAGY<br><br>";
        }else{
            $text .= "<p style=\"font-size:14px;\">Standard szűrést 13:00-tól 17:00-ig végezzük. Két műszakos kollegák esetében délelőttös műszak utáni délutánon.</p><br>";
        }*/

        $text .= "<p style=\"font-size:14px;\">Az időpontfoglaláskor meg kell adnia műszakját. Ezután a fent leírtak szerint tud időpontot foglalni. Ha Ön nem tud választani a felkínált időpontok között, kérje munkatársaink segítségét!</p><br>";
        $text .="<p style=\"font-size:14px;\"><strong>Munkatársaink elérhetőségei:</strong></p>";
        $text .="<ul style=\"margin-left:10px;font-size:14px;\">";
        $text .="<li style=\"list-style: disc;\">Magyar Suzuki - Teberi Andrea:  <a style=\"color:#a00\" href=\"tel:+36303553523\">+36 20/355-3523</a></li>";
        $text .="<li style=\"list-style: disc;\">Magyar Suzuki - Balogh Miklós:  <a style=\"color:#a00\" href=\"tel:+36205878696\">+36 20/417-4128</a></li><br>";
        $text .="<li style=\"list-style: disc;\">Hungária Med-M Kft. - Szabó Melinda: <a style=\"color:#a00\" href=\"tel:+36707799485\">+36 70/779-9485</a></li>";
        $text .="</ul>";

        $text .= "<p style=\"font-size:14px;\"><strong>Vizsgálat helyszíne:</strong></p>";
        $text .="<ul style=\"margin-left:10px;font-size:14px;\">";
        $text .="<li style=\"list-style: disc;\">Suzuki Aréna</li>";
        $text .="<li style=\"list-style: disc;\">2500 Esztergom, Helischer József út 5.</li>";
        $text .= "</ul>";

        $text= $this->setMailTemplate($text, "Suzuki GHC szűrés időpontfoglalás elindult!", "ghc");
        $mail->Body = $text;

        $smsSzoveg  = "Tisztelt GHC jelentkező! A szűrésre időpontot foglalhat a mai naptól, ";
        $smsSzoveg .= "2025.08.25 22:00-tól, az alábbi linken: https://ghc.hungariamed.hu/?page=login Bővebb tájékoztatást e-mailben olvashat.";

        //$this->utils->sendSMS($data["telefon"],$smsSzoveg);

        $this->createNotificationRecord("ghc_idopont_nyitas_restart",$data["id"],$data["email"], $subject, $text);

        //die($text);

        $mail->Send();
    }

    public function sendUserReservationNotification($id, $force = false) {
        //visszaigazoló levél a foglalás sikerességéről a felhasználónak

        $res = sql_query("SELECT " . $this->utils->cimLangQuery("helyszin") . ",sz.megnev AS szurestipus, sz.megnev_en AS szurestipus_en, sz.megnev_de AS szurestipus_de, sz.custompatientemail_option, sz.custompatientemail_text, f.*, c.megnev as cegnev, c.email as cegemail, c.foglalasemail, c.domain, o.nev as orvosnev,o.tel as orvostelefon, csomagidotartam 
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

            /*if ($row["fgroupid"] != "0") {
                return;
            }*/

            if (!empty($row["jarat"])) {
                $row["datum"] = substr($row["datum"], 0, 10)." ".$row["jarat"];
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

            //3 időpontos levél sablon
            /*if(!empty($row["fgroupid"])){
                $mailTemplate = $this->userMultiBookingTemplate($row);
            }*/

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
            $mail->Body = $this->setMailTemplate($mailTemplate["body"], $mailTemplate["subject"]);

            //$mail->AddAttachment("");

            if ($row["noreservation"] == 0 && !$this->isVarolista($row)) {
                //csak ha nem webdoctor
                $mail->addStringAttachment($this->getCalendarItem($row), 'foglalas.ics', 'base64', 'text/calendar');
            }

            if (CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser($row["cegid"])) {
                //Kiválasztom melyik fájlt akarom csatolni a levélhez.
                if ($row["szurestipus"] == "Suzuki 45 év alatti férfi csomag" || $row["szurestipus"] == "Suzuki 40 év alatti nő csomag") {
                    $filename = "Suzuki menedzser tajekoztato 45 alattiaknak.pdf";
                }
                if ($row["szurestipus"] == "Suzuki 45 év feletti férfi csomag" || $row["szurestipus"] == "Suzuki 40 év feletti nő csomag") {
                    $filename = "Suzuki menedzser tajekoztato 45 felettieknek.pdf";
                }

                //PDF szerkesztő inicializálása
                $pdf = new Pdf("/var/www/onlinebejelentkezes_keltexmed/public/images/" . $filename);
                //Input értékek betöltése
                $input = array(
                    "nev" => $row["nev"],
                    "idopont" => str_replace("-", ".", $row["datum"]),
                    "szurocsomag" => $row["szurestipus"]
                );
                //Módosítások mentése
                $result = $pdf->fillForm($input)
                    ->flatten()
                    ->saveAs($attachment = "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/" . $filename);

                $mail->AddAttachment($attachment);
            }

            $mail->Send();

            $this->createNotificationRecord("usernotification", $id, $row["email"], $mailTemplate["subject"], $mailTemplate["body"]);
        }
    }


    public function sendToCegAndOrvos($id, $force = 0, $test = 0)
    {
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

                    if (!empty($rowf["fgroupid"])) {
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

                    //cégnek ne menjen megjegyzés!!
                    //if ($row["megj"] != "") $mbody .= "Megjegyzés: {$row["megj"]}<br>";

                    $mbody .= "<br/>";

                    if ($row["orvosnev"] != "" && $row["orvosemail"] != "") {
                        $mbody .= "Értesített orvos: {$row["orvosnev"]} ({$row["orvosemail"]})";
                    }

                    if(!empty($row["fgroupid"])){
                        $mbody = "Orvos részére kimenő levél";
                    }

                    $mail->Subject = $subject;
                    $mail->Body = $mbody;
                    $mail->Send();

                    $this->createNotificationRecord("cegnotification", $row["id"], $row["cegemail"] . "," . $row["hmedemail"], $subject, $mbody);
                }
            }
        }
    }

    public function sendUserVisszaIgazolas($id)
    {
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


    public function reservationReminder($data)
    {
        $deleteURL = "http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$data["id"]}&rk={$data["rkod"]}&setlang={$data["rlang"]}&dodeletereservation";

        $mail = $this->getDefaultMailer();
        $mail->AddAddress($data["email"]);
        $mail->AddBCC("jns@jns.hu");

        $subject = "Időpontfoglalás Emlékeztető - {$data["megnev"]}";

        $mbody = "<p style='font-size:18px;font-weight:bold;'>Tisztelt hölgyem/uram!</p>";
        $mbody .= "";
        $mbody .= "<p style=''>Szeretnénk emlékeztetni, hogy <strong>" . date("Y.m.d H:i", strtotime($data["datum"])) . "-ra</strong> időpontfoglalása van,<br><br>";

        $mbody .= "<strong>Ellátás megnevezése:</strong><br/>{$data["megnev"]}<br/>";
        $mbody .= "<strong>Helyszín:</strong><br/>{$data["cim"]}<br/>";
        $mbody .= "<strong>Ellátó orvos vagy rendelő megnevezése:</strong></br>{$data["nev"]}<br/>";

        $mbody .= "<br><br>";
        $mbody .= "Az ellátás folytonossága érdekében kérjük, hogy legalább <strong>15 percel</strong> a lefoglalt időpont előtt sziveskedjék megjelenni!<br>";
        $mbody .= "Ha bármilyen okból nem tud megjelenni a vizsgálaton, vagy lemondaná a foglalást kérem <a style='color:#a00;' href='{$deleteURL}' target='_blank'>kattintson ide az időpont törléséhez.</a></p>";

        $mbody .= "<p>Köszönjük, " . Booking_Constants::COMPANY_NAME . " Csapata!</p>";

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();

        sql_query("UPDATE foglalasok SET emlekezteto_mail = 1 WHERE id=?", array($data['id']));

        $this->createNotificationRecord("emlekezteto", $data["id"], $data["email"], $subject, $mbody);
    }


    private function orvosMailTemplate($rowf, $rowo)
    {
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

    private function orvosMailTemplateRemote($rowf, $rowo)
    {
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
        $mbody .= "Szűréstípus: {$rowf["szurestipus"]}<br>";
        if($rowf["megj"]!=""){
            $mbody .= "Visszahívást kér ebben az idősávban: {$rowf["megj"]}<br>";
        }
        
        $mbody .= "<hr>";

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

    private function orvosMailTemplateMultiTime($rowf, $rowo): array
    {
        $mbody = "";

        $from = Booking_Constants::NO_REPLY_ADDRESS;;

        if ($rowo["visszaigazol"] == 1 && $rowo["visszaigazolemail"] != "") {
            $dateLinks = "";

            $reservations = sql_query("select id, pass, datum from foglalasok where fgroupid=? and regdatum>=? order by datum", [$rowf["fgroupid"], date("Y-m-d 00:00:00", strtotime($rowf["regdatum"]))])->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($reservations as $reservation) {
                $dateLinks .= "<a href='" . Booking_Constants::MAIN_URL . "/index.php?selectthistime={$reservation["id"]}&p={$reservation["pass"]}'>" . date("Y.m.d. H:i", strtotime($reservation["datum"])) . "</a><br/>";
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

    private function isVarolista($reservationData)
    {
        return substr_count($reservationData["orvosnev"], "Várólista") != 0;
    }

    private function userMailTemplateVarolista($row)
    {
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
        $extraMsg = ($row["custompatientemail_option"] == 1 && !empty($row["custompatientemail_text"])) ? "<br/>" . nl2br($row["custompatientemail_text"]) . "<br/>" : "<br/>";

        /*if ($result = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE id = '" . intval($row["paciensid"]) . "'"))) {
            if ((strtotime("now") - strtotime($result["regtime"])) < 3600) {
                $c = explode(",", $row["domain"]);
                $extraMsg = "<br/>A kiállított leleteit és dokumentumait a " . Booking_Constants::SITE_PROTOCOL . "://{$c[0]}." . Booking_Constants::SITE_DOMAIN . " oldalon a taj számával megtekintheti online.<br/>";
            }
        }*/

        $mbody = "";
        $mbody .= "<strong>" . ($row["fgroupid"]==""?date("Y.m.d. H:i", strtotime($row["datum"]))." - ":"") . "{$row["helyszin"]}</strong><br/><br/>";

        $mbody .= "{$webTextLocal["nev"]}: {$row["nev"]}<br>";
        if (!empty($row["telefon"])) {
            $mbody .= "{$webTextLocal["telefon"]}: {$row["telefon"]}<br>";
        }
        $mbody .= "<br>";

        if($row["fgroupid"]==""){
            if (!$this->isVarolista($row)) {
                $mbody .= "<b>{$webTextLocal["idopont"]}: " . date("Y.m.d. H:i", strtotime($row["datum"])) . "</b><br><br>";
            }
        }
        

        if(!empty($row["fgroupid"])){
            $multiBookings = sql_query("SELECT * FROM foglalasok WHERE fgroupid=?",[$row["fgroupid"]])->fetchAll(PDO::FETCH_ASSOC);
            foreach($multiBookings as $multibooking){
                $mbody .= "<b>{$webTextLocal["idopont"]}: " . date("Y.m.d. H:i", strtotime($multibooking["datum"])) . "</b><br><br>";
            }
            
        }

        $szuresTipus = $row["szurestipus"];
        if (CompanyService::isAuchan()) {
            $szuresTipus = $szuresTipus . ". " . substr($row["megj"], strpos($row["megj"], "Választott vizsgálat"));
        }

        if ((CompanyService::isDRV() || CompanyService::isEON() || CompanyService::isCargo()) && $row["szurestipusid"] == Booking_Constants::LABOR_ID) {
            $szuresTipus = "Helyszíni díjmentes szűrővizsgálat";
        }

        if (CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser()) {
            $mbody = "Köszönjük, hogy a Hungária Med-M Kft. szolgáltatását választotta.<br><br>";
            $mbody .= "Ezúton tájékoztatjuk, hogy időpontfoglalása sikeresen megtörtént.<br></br>";
            $mbody .= "<strong>Vizsgálat időpontja:</strong> " . date("Y.m.d H:i", strtotime($row["datum"])) . "<br>";
            $mbody .= "<strong>Választott szűrőcsomag:</strong> {$row["szurestipus"]}<br>";
            $mbody .= "<strong>Várható ellátási idő:</strong> <i>{$row["csomagidotartam"]}</i><br><br>";
            $mbody .= "<strong>Vizsgálatok helyszíne:</strong><br>";
            $mbody .= "<ul style=\"margin-left:10px\">";
            $mbody .= "<li style=\"list-style: disc;\">1135 Budapest, Jász utca 33-35. Hungária Med-M Kft. rendelője.</li>";
            $mbody .= "<li style=\"list-style: disc;\">Bejárat a Béke Patika épületének oldalán található.</li>";
            $mbody .= "<li style=\"list-style: disc;\">Parkolás a rendelő udvarában korlátozott számban lehetséges.</li>";
            $mbody .= "</ul>";
            $mbody .= "<strong>Vizsgálatokkal kapcsolatos értesítések: </strong><br>";
            $mbody .= "<ul style=\"margin-left:10px\">";
            $mbody .= " <li style=\"list-style: disc;\">Call-centeres munkatársunk a vizsgálat előtt 1 héttel és közvetlenül a vizsgálat előtt 1 munkanappal meg fogja Önt keresni egy közvetlen egyeztetés céljából a vizsgálatokkal kapcsolatban.</li>";
            $mbody .= " <li style=\"list-style: disc;\">Tovább 24 órával a vizsgálat előtt egy SMS emlékeztetőt is küldünk Önnek.</li>";
            $mbody .= "</ul>";
        }

        /*if(CompanyService::isObudaiegyetem($row["cegid"])){
            $mbody = "";
        }*/

        $mbody .= "{$webTextLocal["szurestipus"]}: {$szuresTipus}<br>";
        $mbody .= ($row["cegid"] == 6 ? "Ellátó orvos: {$row["orvosnev"]}<br>" : "");
        $mbody .= "{$packText}";
        $mbody .= "{$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>";

         if(!empty($row["fgroupid"])){
            $mbody .= "<br>Orvosunk hamarosan visszafog jelezni egy automata levél formájában a kiválaszott időpontjával!<br>";
         }

        if ($row["cegid"] == CompanyService::ALDI_FIFI_ID && Booking_Constants::SQL_DB == "hungariamed") {
            $mbody.= "<hr><br/>";
            $mbody.= "<strong>Kedves Jelentkező!</strong><br/>
                <br/>
                Köszönjük a regisztrációdat! Hamarosan újra kezdetét veszi a FIFI, amelyre sok szeretettel várjuk régi és új pácienseinket.<br/>
                A környezettudatosság jegyében idén nem készülnek nyomtatott plakátok, ezért a papírmentes kommunikációra helyezzük a hangsúlyt.<br/>
                Az alábbi linkre kattintva megnézhetitek a finanszírozott és a kiemelt önköltséges csomagok részleteit, és itt tudtok időpontot foglalni a vérvételre.<br/>
                <br/>
                <a href='https://aldi-fifi-uzletek-webshop.hungariamed.hu' target='_blank'>https://aldi-fifi-uzletek-webshop.hungariamed.hu</a><br/>
                <br/>
                Mindenki egészségének megőrzése fontos számunkra, hisz ez a küldetésünk, ezért kérünk Benneteket, hogy juttassátok el minél több kollégátokhoz a szükséges információkat és segítsétek Őket a jelentkezésben.<br/>
                Örömmel vesszük az előzetes bejelentkezést a gördülékenyebb munka érdekében. Természetesen helyszíni regisztrációra is van lehetőség, amelyben a Hungária Med-M Kft. dolgozói segítenek.<br/>
                A Fiolányi Figyelem, egy csepp gondoskodás, mert jobb megelőzni, mint a kezelni.<br/>
                <br/>
                Hungária Med-M csapata<br/><br/>";
        }

        if ($row["cegid"] == CompanyService::ALDI_FIFI_CTD_ID && Booking_Constants::SQL_DB == "hungariamed") {
            $mbody.= "<hr><br/>";
            $mbody.= "<strong>Kedves Jelentkező!</strong><br/>
                <br/>
                Köszönjük a regisztrációdat! Hamarosan újra kezdetét veszi a FIFI, amelyre sok szeretettel várjuk régi és új pácienseinket.<br/>
                A környezettudatosság jegyében idén nem készülnek nyomtatott plakátok, ezért a papírmentes kommunikációra helyezzük a hangsúlyt.<br/>
                Az alábbi linkre kattintva megnézhetitek a finanszírozott és a kiemelt önköltséges csomagok részleteit, és itt tudtok időpontot foglalni a vérvételre.<br/>
                <br/>
                <a href='https://aldi-fifi-webshop.hungariamed.hu' target='_blank'>https://aldi-fifi-webshop.hungariamed.hu</a><br/>
                <br/>
                Mindenki egészségének megőrzése fontos számunkra, hisz ez a küldetésünk, ezért kérünk Benneteket, hogy juttassátok el minél több kollégátokhoz a szükséges információkat és segítsétek Őket a jelentkezésben.<br/>
                Örömmel vesszük az előzetes bejelentkezést a gördülékenyebb munka érdekében. Természetesen helyszíni regisztrációra is van lehetőség, amelyben a Hungária Med-M Kft. dolgozói segítenek.<br/>
                A Fiolányi Figyelem, egy csepp gondoskodás, mert jobb megelőzni, mint a kezelni.<br/>
                <br/>
                Hungária Med-M csapata<br/><br/>";
        }


        if(CompanyService::isAszMenedzser()){
            $mbody .= "<hr>";

            $mbody .= "<p>Bejelentkezés az emeleti recepción történik.</p><br>";

            $mbody .= "<p>Kérjük, éhgyomorral érkezzen, szénsavmentes ásványvizet lehet és ajánlott is fogyasztani a vérvétel és az ultrahang vizsgálat előtt. ";
            $mbody .= "A laboratóriumi mintákat vérvétel előtt a laborban tudja leadni.</p><br>";

            $mbody .= "<p>Ezt követően reggelit biztosítunk szendvics formájában,(bármely érzékenysége van, vagy nem kérné, kérem, jelezze előre a megjegyzés rovatban) ";
            $mbody .= "majd a későbbiekben kolléganőm fog kíséretet nyújtani a szakrendelésekre a délelőtt folyamán.</p><br>";

            $mbody .= "<p>Parkolás szabad helyek függvényében az udvarban lehetséges, előzetes rendszám megadásával vagy az utcán, a rendelővel szemben a közterületen.</p><br>";

            $mbody .= "<p>Ha pluszban választott kardiológiai vizsgálatot, ami tartalmaz terheléses EKG vizsgálatot, ajánlott hozni váltóruhát, ";
            $mbody .= "egy kényelmesebb pólót, cipőt és nadrágot. Az úgynevezett béta-blokkoló (szívritmusszabályzó) gyógyszert a ";
            $mbody .= "vizsgálat előtti napon és a vizsgálat napján nem kell bevenni, de hozzák magukkal. ";
            $mbody .= "Ezek a gyógyszerek: Concor, Nebilet, Nebivolol, Lokren, Propanolol, Visken, Procolaran, Betaloc, Metoprolol, Bisoprolol, Bisoblock. ";
            $mbody .= "Amennyiben két hónapon belül esett át a covid fertőzésen a terheléses EKG vizsgálatot nem tudjuk elvégezni.</p><br>";
        }

        $resv = sql_query("SELECT * FROM visszaigazolok WHERE cegid='{$row["cegid"]}' AND (orvosid='{$row["orvosassigned"]}' OR orvosid=0) AND (helyszinid='{$row["helyszinid"]}' OR helyszinid=0) AND TRIM(szoveg)<>''");
        while ($rowv = sql_fetch_array($resv)) {
            $maplink = "";
            if ($rowv["mapurl"] != "") $maplink = "<a href='{$rowv["mapurl"]}'>Az útvonal térképen megjelenítéséhez kattintson ide.</a>";
            $rowv["szoveg"] = str_replace("#maplink#", $maplink, $rowv["szoveg"]);
            $mbody .= "<hr>" . nl2br($rowv["szoveg"]);
        }

        $mbody .= "<hr>";

        $deleteLink = "https://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$row["id"]}&rk={$row["rkod"]}";
        if ($row["szurestipusid"] != 1) {
            //$deleteLink.= "&dodeletereservation";
        }

        if ($row["rlang"] != "de" && $row["rlang"] != "en") {
            if (CompanyService::isBP($row["cegid"])) {
                $mbody .= "A pszihoszociális kérdőívet az alábbi linken tudja megtekinteni és kitölteni:<br>";
                $mbody .= "<a target=\"_blank\" href=\"https://{$_SERVER["HTTP_HOST"]}/?page=psychosocialform&pass={$row["pass"]}\">Pszihoszociális kérdőív link</a><br><br>";
            }

            if (CompanyService::isAuchan()) {
                $mbody .= "Ha bármi kérdése van, vagy a foglalt időpontját szeretné módosítani, kérjük hívja ezt a telefonszámot: 06 30 537 1008";
                $mbody .= "<hr>";
            }

            $mbody .= "Ha le szeretné mondani ezt a foglalását, kérjük kattintson a következő linkre: <a href='{$deleteLink}&setlang={$row["rlang"]}'>időpont foglalás lemodása</a><br>";
            $mbody .= "Amennyiben módosítani szeretné a foglalását, abban az esetben először törölje a régi időpontját a fenti linken, utána pedig regisztrálja újra.<br>{$extraMsg}";
            $mbody .= "<br/>";
            $mbody .= "Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;
        }
        if ($row["rlang"] == "de") {
            $mbody .= "Wenn Sie möchten Diese Termin Reservierung Canceln, bitte drücken Sie an Ihre Brief <a href='{$deleteLink}&setlang={$row["rlang"]}'>Die Termin Registration Canceln</a> LINK.<br>";
            $mbody .= "Wenn Sie möchten Ihre Reservierung Verändern ,bitte Streichen Sie aus den anderen Zeitpunkt, dannach registrieren bitte nochmal.<br>";
            $mbody .= "<br/>";
            $mbody .= "Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;
        }
        if ($row["rlang"] == "en") {
            $mbody .= "If you wish to cancel this appointment, please click on link: <a href='{$deleteLink}&setlang={$row["rlang"]}'>Cancellation of confirmed appointment</a><br>";
            $mbody .= "If you would like to modify your appointment, first cancel your old appointment then register it again.<br>";
            $mbody .= "<br/>";
            $mbody .= "Regards:<br>" . Booking_Constants::COMPANY_NAME;
        }

        if(CompanyService::isSuzukiGHC()){
            $datumOra = explode(" ",$row["datum"]);
            $ora = date("H:00",strtotime($datumOra[1]));

            $mbody ="<h3 style=\"text-align:center\">Kedves ".$row["nev"]."!</h3><br>";
            $mbody.="Köszönjük, hogy a Hungária Med-M Kft. szolgáltatását választotta.<br><br>";
            $mbody.="Ezúton tájékoztatjuk, hogy időpontfoglalása sikeresen megtörtént.<br></br>";

            $mbody.="<h3>Vizsgálat időpontja: ".date("Y.m.d H:00",strtotime($row["datum"]))."</h3><br>";
            
            $mbody.="Kérjük, a vizsgálat előtt legalább félórával hamarabb érkezzen meg, azaz <strong>".date("H:i",strtotime($ora." - 30 minutes"))."-kor várjuk Önt a Suzuki Arénában!</strong><br></br>";
            $mbody.="Az időpontja előtt a HR ügyfélszolgálatán kérhet vizeletes csövet.<br></br>";

            if($_SESSION["user"]["szallitas"]==1){
                $mbody.="<strong>Szállítással kapcsolatos információ:</strong><br>";
                $mbody.="<ul style=\"margin-left:10px\">";
                $mbody.=" <li style=\"list-style: disc;\">A szűrésre való szállítással kapcsolatban a Hungária Med kollégái fogják a szűrés előtti napokban megkeresni Önt. A pontos időpont és felszálló hely részleteivel.</li>";
                $mbody.="</ul>";
            }
            //$successText.="<strong>Várható ellátási idő:</strong> <i>{$this->foglalasData["csomagidotartam"]}</i><br><br>";
            $mbody.="<strong>Vizsgálatok helyszíne:</strong><br>";
            $mbody.="<ul style=\"margin-left:10px\">";
            $mbody.="<li style=\"list-style: disc;\">Suzuki Aréna</li>";
            $mbody.="<li style=\"list-style: disc;\">2500 Esztergom, Helischer József út 5.</li>";
            //$successText.="<li style=\"list-style: disc;\">Parkolás a rendelő udvarában korlátozott számban lehetséges.</li>";
            $mbody.="</ul>";

            $mbody.="<strong>Vizsgálatokkal kapcsolatos értesítések:</strong><br>";
            $mbody.="<ul style=\"margin-left:10px\">";
            $mbody.=" <li style=\"list-style: disc;\">24 órával a vizsgálat előtt SMS értesítést küldünk, és telefonon is keressük Önt.</li>";
            $mbody.="</ul>";

            $mbody.="<strong>Választott szűrőcsomag:</strong> {$row["szurestipus"]}<br>";
            $mbody.="<i>Szűrőcsomag tartalma:</i><br>";
            $mbody.="<ul style=\"margin-left:10px\">";
            $tartalom = sql_query("SELECT f.id, f.datum, f.cegid, f.megj, sz.* FROM foglalasok f LEFT JOIN szurestipusok sz ON sz.id=f.szurestipusid WHERE parentid=? order by datum", array($row["id"]));
            while($vizsgalat = sql_fetch_array($tartalom)){
                $mbody.="<li style=\"list-style: disc;\">{$vizsgalat["megnev"]}</li>";
            }
            $mbody.="</ul>";
        }

        

        $template["subject"] = "{$webTextLocal["sikeresidopontreg"]}";
        $template["body"] = $mbody;
        return $template;
    }

    private function userMailTemplateWebDoctor($row)
    {
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

    private function userMailTemplateManualBooking($row)
    {

        $cegInfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($row["cegid"])));

        $deleteLink = "http://{$cegInfo["megnev"]}.hungariamed.hu/index.php?page=bookingdelete&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}&dodeletereservation";

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
    private function _getPackText($reservationData): string
    {
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
                $packText .= "<br/>{$name}<br/>Időpont: " . date("Y.m.d H:i", strtotime($rowcs["datum"])) . "<br/>";
            } else {
                if (empty($packText)) {
                    $packText .= "<br/>Csomag tartalma:<br/>";
                }
                $packText .= "{$rowcs["megnev"]}";
                if (CompanyService::isBudapestBrand($rowcs["cegid"]) || CompanyService::isKRE($rowcs["cegid"])) {
                    $packText .= ", időpont: " . date("Y.m.d H:i", strtotime($rowcs["datum"]));
                }

                $packText .= "<br/>";
            }
        }

        $rescs = sql_query("SELECT t.* FROM szurescsomagok_kapcs k LEFT JOIN szurestipusok t ON t.id=k.szurestipusid WHERE k.csomagid=? AND k.noreservation=1", [$reservationData["szurestipusid"]]);
        while ($rowcs = sql_fetch_array($rescs)) {
            if ($reservationData["rlang"] == "en" && $rowcs["megnev_en"] != "") $rowcs["megnev"] = $rowcs["megnev_en"];
            if ($reservationData["rlang"] == "de" && $rowcs["megnev_de"] != "") $rowcs["megnev"] = $rowcs["megnev_de"];
            if (empty($packText)) {
                $packText .= "<br/><strong>Csomag tartalma:</strong><br/>";
            }
            $packText .= "{$rowcs["megnev"]}<br/>";
        }

        if (!empty($packText)) {
            $packText .= "<br/>";
        }

        return $packText;
    }


    private function getCalendarItem($foglalasData)
    {
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


    public function sendMissingDataEmail($id)
    {
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
            Az adatok megadásához <a href='" . Booking_Constants::MAIN_URL . "/index.php?page=missingdata&r={$row["id"]}&h={$row["h"]}'>kattintson ide</a><br/>
            <br/>
            Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;

            $mail = $this->getDefaultMailer();
            //$mail->AddAddress($row["email"]);
            $mail->AddAddress("jnsmobil@gmail.com");
            //if (!empty(Booking_Constants::USER_BCC_MAIL)) {
            //$mail->AddBCC("jns@jns.hu");
            //}

            $subject = "[" . Booking_Constants::COMPANY_NAME_SHORT . "] Kérjük adja meg az adatait";
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->Send();

            $this->createNotificationRecord("missingdata", $id, $row["email"], $subject, $body);
        }
    }

    public function newAdminPassEmail($userData)
    {
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

    public function newUserPassEmail($userData, $lang = "hu")
    {
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
        $mbody .= "Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;

        if ($_COOKIE["lang"] == "de") {
            $mbody = "Lieber {$userData["nev"]}!<br/><br/>";
            $mbody .= "Unsere online anmelden Oberfláche sie beginnen eine neue Kennwort anbietten.<br/><br/>";
            $mbody .= "Die neue Kennwort: <b>{$p}</b><br><br>";
            $mbody .= "Nach den anmelden können Sie um  einem neuem Kennwort bitten.<br/>";
            $mbody .= "<br/>";
            $mbody .= "Freundlichen Grüssen:<br>" . Booking_Constants::COMPANY_NAME;
        }
        if ($_COOKIE["lang"] == "en") {
            $mbody = "Dear {$userData["nev"]}!<br/><br/>";
            $mbody .= "You have requested a new password on our reservation page.<br/><br/>";
            $mbody .= "Your new password: <b>{$p}</b><br><br>";
            $mbody .= "You can change your new password under the profile page.<br/>";
            $mbody .= "<br/>";
            $mbody .= "Regards<br>" . Booking_Constants::COMPANY_NAME;
        }

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();

        sql_query("update felhasznalok set jelszo=?	where id=?", [md5($p), $userData["id"]]);
    }

    public function sendDebugEmail($subject, $mbody)
    {
        $mail = self::getDefaultMailer();
        $mail->AddAddress("jnsmobil@gmail.com");
        $mail->AddBCC("m.gergely9409@gmail.com");
        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();
    }

    public function sendReviewMail($reservation, $force = false) {
        if ($emailData = sql_fetch_array(sql_query("select * from ertekeles_formok where (instr(rule_cegids,'|{$reservation["cegid"]}|') or rule_cegids='all') and rule_mail=1 and rule_aftereljott=1 ORDER BY id DESC LIMIT 1"))) {
            $mailSzoveg = $emailData["mailszoveg_{$reservation["rlang"]}"];
            if ($mailSzoveg == "") $mailSzoveg = $emailData["mailszoveg_hu"];
            $mailSubject = $emailData["megnev_{$reservation["rlang"]}"];
            if ($mailSubject == "") $mailSubject = $emailData["megnev_hu"];

            if ($mailSzoveg != "" && $mailSubject != "" && filter_var($reservation["email"], FILTER_VALIDATE_EMAIL)) {
                //aki kapott 30 napon belül, az ne kapjon újra
                if ($force || !sql_query("select id from notifications where tipus=? and destination=? and datum>DATE_SUB(NOW(), INTERVAL 30 DAY)", ["reviewoption", $reservation["email"]])->fetch(PDO::FETCH_ASSOC)) {
                    $mail = self::getDefaultMailer();
                    //$mail->AddAddress($foglalasData["email"]); //ne élesítsd még
                    $mail->AddAddress("jnsmobil@gmail.com");

                    $mailSzoveg = str_replace("#nev#", $reservation["nev"], $mailSzoveg);
                    $mailSzoveg = str_replace("#reviewlink", "https://review.hungariamed.hu/index.php?eform={$emailData["kod"]}&kerdes", $mailSzoveg);
                    $mailSzoveg = str_replace("#cegnev#", Booking_Constants::COMPANY_NAME, $mailSzoveg);
                    $mail->Subject = $mailSubject;
                    $mail->Body = $this->setMailTemplate($mailSzoveg, $mailSubject);
                    $mail->Send();

                    echo $reservation["datum"] . " " . $reservation["nev"] . "{$reservation["email"]}\n";
                    $this->createNotificationRecord("reviewoption", $reservation["id"], $reservation["email"], $mailSubject, $mailSzoveg);
                    die("teszt force exit\n");
                }
            }
        }
    }

    function sendNotConfirmedReservationMessages($reservationId)
    {
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
            $mbody .= "Üdvözlettel:<br/>" . Booking_Constants::COMPANY_NAME;

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

    public function covidListMessage($covidListId)
    {
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
                Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME . "<br/><br/> 
                <img style='width:150px;' src='https://bejelentkezes.hungariamed.hu/images/hmm_logo_nagy.png' alt='" . Booking_Constants::COMPANY_NAME . "' />";;
            }

            if ($data["statusz"] == "DENIED") {
                $reason = "";
                if (!empty(trim($data["deniedtext"]))) {
                    $reason = "<strong>Az elutasítás oka:</strong><br/>" . nl2br($data["deniedtext"]) . "<br/><br/>";
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
                Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME . "<br/><br/> 
                <img style='width:150px;' src='https://bejelentkezes.hungariamed.hu/images/hmm_logo_nagy.png' alt='" . Booking_Constants::COMPANY_NAME . "' />";;
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


    public function newCompanyNotification($companyId)
    {
        if ($companyData = sql_query("select * from cegek where id=?", [$companyId])->fetch(PDO::FETCH_ASSOC)) {
            $adminUser = new AdminUser();
            $mail = self::getDefaultMailer();

            foreach (explode(",", Booking_Constants::REPORT_MAILS) as $email) {
                $mail->addAddress(trim($email));
            }

            $mail->Subject = "Új cég rögzítve a " . Booking_Constants::FOOTER_COPYRIGHT . " bejelentkezőbe";

            $mail->Body = "Cég neve: {$companyData["megnev"]}<br/>Rögzítette: " . $adminUser->user["username"];
            $mail->send();
        }
    }

    public function tesztMessage()
    {
        $mail = self::getDefaultMailer();

        $mail->addAddress("jnsmobil@gmail.com");
        $mail->Subject = "Új teszt mail";
        $mail->Body = "ez egy teszt mail";
        $mail->send();
    }

    public function deleteDoctorMessage($reservationId) {
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

    public function deleteUserMessage($reservationId) {
        $res = sql_query("SELECT o.email as orvosemail, o.nev as orvosnev, h.cim AS helyszin,sz.megnev AS szurestipus,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
        LEFT JOIN orvosok o on o.id=f.orvosassigned
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=? and f.aktiv=1 and f.nev<>'nincs név'", [$reservationId]);

        if ($row = sql_fetch_array($res)) {
            if (filter_var($row["email"], FILTER_VALIDATE_EMAIL)) {
                $mail = self::getDefaultMailer();
                $mail->addAddress($row["email"]);

                $subject = "Foglalás lemondva: {$row["szurestipus"]}";

                $mbody = "";
                $mbody .= "<b>A lemondott foglalás adatai</b><br/><br/>";
                $mbody .= "Név: {$row["nev"]}<br/>";
                $mbody .= "Telefon: {$row["telefon"]}<br/>";
                $mbody .= "Email: {$row["email"]}<br/>";
                $mbody .= "<b>Időpont: ".date("Y.m.d H:i", strtotime($row["datum"]))."</b><br/>";
                $mbody .= "Szolgáltatás: {$row["szurestipus"]}<br/>";
                $mbody .= "Helyszín: {$row["helyszin"]}<br/>";
                $mbody .= "<br/>";

                $mail->Subject = $subject;
                $mail->Body = $this->setMailTemplate($mbody, "Foglalás lemondva");
                $mail->Send();

                $this->createNotificationRecord("deleteuserreservation", $row["id"], $row["email"], $subject, $mbody);
            }
        }
    }

    private function setMailTemplate($body, $title = "",$forced = null) {
        
        $templateFile = "/var/www/onlinebejelentkezes_keltexmed/public/zebrateszt/userOrderMailTemplate_".Booking_Constants::SQL_DB.".html";
        if(CompanyService::isSuzukiGHC() || $forced=="ghc"){
            $templateFile = "/var/www/marci/onlinebejelentkezes/public/zebrateszt/ghc_email_template.html";
        }

        if (is_file($templateFile)) {
            $template = file_get_contents($templateFile);
            $template = str_replace("#body#", $body, $template);
            $template = str_replace("#title#", $title, $template);
            if(CompanyService::isBME()){
                $template = str_replace("#footer#","<strong><a href='https://www.hungariamed.hu' target='_blank'>www.hungariamed.hu</a></strong>",$template);
            }else{
                $template = str_replace("#footer#","<strong>Tel: +36 1 / 800 9333, Cím: 1135 Budapest, Jász u. 33-35.</strong>",$template);
            }
            $body = $template;
        }
        return $body;
    }

    public function sendLabShopMail($labShopData)
    {
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
                Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME . " Csapata!<br/><br/> 
                <img style='width:150px;' src='https://bejelentkezes.hungariamed.hu/images/hmm_logo_nagy.png' alt='" . Booking_Constants::COMPANY_NAME . "' />";

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();
    }


    public function sendManagerStatusMail()
    {
        $page = new AdminManagerStatusPage();

        $mail = self::getDefaultMailer();
        $mail->AddAddress("jnsmobil@gmail.com");
        $mail->AddAddress("kuzdyg@hungariamed.hu");
        $mail->AddAddress("marton.gergely@hungariamed.hu");

        $subject = "Manager csomag status " . date("Y-m-d");

        $mbody = $page->managerStatList(14);

        $mail->Subject = $subject;
        $mail->Body = $mbody;
        $mail->Send();
    }

    const OWNER_PASSWORD = "que3ikieP";
    const MAESZ_BEKULDOKOD = "000000481";

    public function sendLaborLeletEmail($id) {
        $adminUser = new AdminUser();
        $docAgent = new DocAgent();
        if (empty($adminUser->user["email"])) {
            return;
        }

        if ($requestData = sql_query("SELECT r.bekuldokod, r.nev, r.szuldatum, r.taj, r.email, c.megnev AS cegnev, r.id, r.pass, r.created, r.provider, r.foglalasid, r.laborpacks, r.ertesitve, r.ertesitesdatum, r.ertesitesemail, r.synlabdata, r.emailtext FROM labrequests r 
        LEFT JOIN foglalasok f ON f.id=r.foglalasid
        LEFT JOIN cegek c ON c.id=f.cegid
        WHERE r.id=?", [$id])->fetch(PDO::FETCH_ASSOC)) {
            $pdfFileName = Booking_Constants::DOCUMENT_PATH . "labor" . md5($id . rand(1, 10000)) . ".pdf";
            $pdfFileNameEncripted = Booking_Constants::DOCUMENT_PATH . "laborenc" . md5($id . rand(1, 10000)) . ".pdf";
            $outFileName = $requestData["nev"] . " laborlelet.pdf";
            $userPassword = trim($requestData["taj"]);
            if (empty($userPassword) || !ctype_digit($userPassword)) {
                $userPassword = str_replace(".", "", str_replace("-", "", $requestData["szuldatum"]));
            }
            $ownerPassword = self::OWNER_PASSWORD;
            $patientEmail = $requestData["email"];

            //file_put_contents($pdfFileName, base64_decode($requestData["resultpdf"]));
            file_put_contents($pdfFileName, $docAgent->getDocByType(DocAgent::ASSET_LABOR_RESULT, $requestData["id"]));

            $output = `pdftk {$pdfFileName} output {$pdfFileNameEncripted} user_pw {$userPassword}`;

            $mail = self::getDefaultMailer();
            $mail->AddAddress($patientEmail);
            //$mail->AddBCC("jnsmobil@gmail.com");
            //$mail->AddBCC("marton.gergely@hungariamed.hu");
            $mail->AddAttachment($pdfFileNameEncripted, $outFileName);

            if ($requestData["bekuldokod"] == self::MAESZ_BEKULDOKOD && Booking_Constants::SQL_DB == "hungariamed") {
                //máesznak külön pdf csatolás
                $mail->AddAttachment(Booking_Constants::APP_PATH."public/admin/templates/Verkep_labor_tajekoztato.pdf");
            }

            $subject = $requestData["nev"] . " labor lelet " . date("Y-m-d");
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
            $logText = "Kiküldve: " . date("Y.m.d H:i") . " {$requestData["email"]} címre, {$admin} által<br/>";

            sql_query("update labrequests set ertesitve=1, ertesitesdatum=now(), ertesitesemail=?, ertesiteslog=CONCAT(?, ertesiteslog) where id=?", [$requestData["email"], $logText, $requestData["id"]]);
        }
    }

    public function checkPreviousNotifications($email, $tipus): array
    {
        if ($q = sql_query("SELECT * FROM notifications WHERE destination=? AND tipus=? ORDER by datum DESC", array($email, $tipus))->fetchAll(PDO::FETCH_ASSOC)) {
            return $q;
        } else {
            return [];
        }
    }


    public function sendReminderToFogleu($email, $content)
    {

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

    public function ReminderForInspection($patient,$details){
        $mail = self::getDefaultMailer();
        $mail->addAddress($patient["email"]);
        $mail->Subject = $details["object"];
        $mail->Body = $this->setMailTemplate($details["content"],"Értesítés");
        $mail->Send();
        $this->createNotificationRecord($patient["reminderType"], null, $patient["email"], $mail->Subject, $mail->Body);
    }

    public function sendBFKHmarketing($email, $content)
    {
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


    public function sendUserSMSCode()
    {
        $adminUser = new AdminUser();

        $mail = self::getDefaultMailer();
        $mail->AddAddress($adminUser->user["email"]);
        //$mail->AddBCC("jnsmobil@gmail.com");
        //$mail->AddBCC("marton.gergely@hungariamed.hu");

        $mbody = "Kedves {$adminUser->user["nev"]}!<br><br/>";
        $mbody .= "A bejelentkezéshez a kód a következő:<br/>
        <br/>
        <span style='font-size: 24px;font-weight: bold;'>{$adminUser->user["logincode"]}</span>
        <br/>";
        $mbody .= "<br/>Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME . " Csapata!<br/><br/>";

        $mail->Subject = Booking_Constants::COMPANY_NAME." belépési kód";
        $mail->Body = $mbody;
        $mail->Send();
    }

    public function sendCustomerSMSCode($fid)
    {
        $user = sql_query("SELECT * FROM felhasznalok WHERE id=?",array($fid))->fetch(PDO::FETCH_ASSOC);

        $mail = self::getDefaultMailer();
        $mail->AddAddress($user["email"]);
        //$mail->AddBCC("jnsmobil@gmail.com");
        //$mail->AddAddress("marton.gergely@hungariamed.hu");

        //Ellenőrzöm, hogy van-e kód generálva, vagy frissíteni kell-e
        /*if (sql_query("SELECT * FROM felhasznalok WHERE logincodetime<DATE_SUB(NOW(),INTERVAL 1 HOUR) and id=?", array($user["id"]))->fetch(PDO::FETCH_ASSOC)) {
            $code = rand(10000,99999);
            sql_query("UPATE felhasznalok SET logincode=?,logincodetime=now() WHERE id=?", array($user["id"]));
            $user["logincode"] = $code;
        }*/

        $mbody = "Kedves {$user["nev"]}!<br><br/>";
        $mbody .= "A bejelentkezéshez a kód a következő:<br/>
        <br/>
        <span style='font-size: 24px;font-weight: bold;'>{$user["rkod"]}</span>
        <br/>";
        $mbody .= "<br/>Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME . " Csapata!<br/><br/>";

        $mail->Subject = Booking_Constants::COMPANY_NAME." belépési kód";
        $mail->Body = $mbody;
        $mail->Send();
    }

    /**
     * Suzuki menedzser foglalásokról értesíti a megjelölt e-mail címeket.
     * @param   string      $notificationDate       Értesítési nap meghatározása.
     * @param   int         $tipus                  webservicelog táblában ezzel az változó értékkel tudom majd kilistázni az előzmény adatokat.
     * @param   int         $cegId                  Cég azonosító filter.
     * @param   array       $szurestipusIds         Szűréstípus filter.
     */
    public function suzukiManagerNotificationList($notificationDate="")
    {
        $tipus                  = 12;
        $cegId                  = 892;
        $szurestipusIds         = [219, 220, 221, 222];
        $szurestipusIdsString   = implode(",", $szurestipusIds);
        $EmailAddresses         = ["marton.gergely@hungariamed.hu","szabo.melinda@hungariamed.hu"];
        $webservicelogs         = [];

        if(empty($notificationDate)){
            $notificationDate = date("Y-m-d");
        }

        /**
         * Ki listázni azokat az időpontokat, amiknek az esedékessége a most és a most+7 napban van.
         * BETWEEN NOW() AND NOW() + 7 DAYS
         * A foglalt időpontot kell nézzem.
        */
        $reservations = sql_query(
            "SELECT fogl.id,fogl.datum as \"Időpont\",sz.megnev as \"Csomag\",fogl.nev as \"Teljesnév\",fogl.email as \"E-mail\",fogl.telefon as \"Telefon\" FROM foglalasok fogl
            LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
            WHERE fogl.cegid=? AND fogl.szurestipusid IN ({$szurestipusIdsString}) AND fogl.datum BETWEEN \"{$notificationDate}\" AND (\"{$notificationDate}\" + INTERVAL 7 DAY) AND foglalta=\"\"
            ORDER BY fogl.datum ASC",
            array($cegId)
        )->fetchAll(PDO::FETCH_ASSOC);

        /**
         * Ki listázom a log fájlokat 14 napra visszamenőleg, hogy összevessem a foglalásokkal. 
        */
        $webservicelogs = sql_query(
            "SELECT keres,action FROM webservicelog WHERE tipus=? AND datum>\"".date("Y-m-d",strtotime("{$notificationDate} - 7 days"))."\"",
            array($tipus)
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach($reservations as $key=>$reservation){
            /**
             * Le kell ellenőriznem, hogy nem volt-e már értesítésre megjelölve a foglalás.
            */
            $reservationDate = date("Y-m-d",strtotime($reservation["Időpont"]));
            $maxRange = date("Y-m-d",strtotime("{$notificationDate} - 7 days"));

            //Első értesítés ellenőzrése
            $firstNotificationCheck = sql_query(
                "SELECT keres,action FROM webservicelog WHERE tipus=? AND datum > \"".$maxRange."\" AND keres=? AND action=?",
                array($tipus,$reservation["id"],"first-notification")
            )->fetchAll(PDO::FETCH_ASSOC);

            //Ha nagyobb, akkor simán szakítsa meg az értesítést.
            //echo "Az időpont kisebb mint az aktuális idő? ".strtotime($reservationDate).">".strtotime(date("Y-m-d",strtotime("now + 1 days")))."<br>";
            if(!empty($firstNotificationCheck) && strtotime($reservationDate)>strtotime(date("Y-m-d",strtotime("{$notificationDate} + 1 days")))){
                echo "nem értesítem erről a Melindát.<br>";
                unset($reservations[$key]);
            }

             //Második értesítés ellenőrzése
             $secondNotificationCheck = sql_query(
                "SELECT keres,action FROM webservicelog WHERE tipus=? AND datum > \"".$maxRange."\" AND keres=? AND action=?",
                array($tipus,$reservation["id"],"second-notification")
            )->fetchAll(PDO::FETCH_ASSOC);

            if(empty($secondNotificationCheck) && strtotime($reservationDate)==strtotime(date("Y-m-d",strtotime("{$notificationDate} + 1 days")))){
                echo "Értesíteni kell.<br>";;
            }else{
                if(!empty($firstNotificationCheck)){
                    unset($reservations[$key]);
                }
            }       
        }

        echo "<pre>";
        print_r($reservations);
        echo "</pre>";
        //return;
        /**
         * E-mailek kiküldése
        */

        if(!empty($reservations)){
                $mail = self::getDefaultMailer();
            for($i=0;$i<count($EmailAddresses);$i++){
                $mail->AddAddress($EmailAddresses[$i]);
            }
            $mbody = "A levél tartalmazza a következő munkanapon értesítendő Suzukis menedzsereket.";
            $mail->Subject = "Suzuki értesítő küldés";
            $mail->Body = $mbody;

            /**
             * Létrehozom az Excel fájlt és hozzá adom a levélhez mint csatolmány.
            */
            $excelService = new ExcelService();
            if($fileName = $excelService->generateXlsxFromArray($reservations)){
                $mail->AddAttachment($fileName);
            }

            /**
             * Levél küldése.
            */
            if($mail->Send()){
                foreach($reservations as $key=>$reservation){
                    //Első értesítés ellenőzrése
                    $maxRange = date("Y-m-d",strtotime("{$notificationDate} - 7 days"));
                    $firstNptificationCheck = sql_query(
                        "SELECT keres,action FROM webservicelog WHERE tipus=? AND datum > \"".$maxRange."\" AND keres=? AND action=?",
                        array($tipus,$reservation["id"],"first-notification")
                    )->fetchAll(PDO::FETCH_ASSOC);
                    
                    if(!$firstNptificationCheck){
                        sql_query("INSERT INTO webservicelog SET datum= \"{$notificationDate}\", tipus=?, keres=?,action=?",
                            array($tipus,$reservation["id"],"first-notification")
                        );
                    }
                    
                    //Második értesítés ellenőrzése
                    $reservationDate = date("Y-m-d",strtotime($reservation["Időpont"]));
                    if(strtotime($reservationDate)==strtotime(date("Y-m-d",strtotime("{$notificationDate} + 1 days")))){
                        sql_query("INSERT INTO webservicelog SET datum= \"{$notificationDate}\", tipus=?, keres=?,action=?",
                            array($tipus,$reservation["id"],"second-notification")
                        );
                    }
                    //Csatolmány törlése
                    unlink($fileName);
                }
                return "Success e-mail send.";
            }
        }else{
            return "Nothing to send.";
        }
        
        
        return "Something went wrong.";
    }

    public function suzuki_ghc_reg_confirmation_notification($fid){

        $html= "";
        $result = sql_query("SELECT felh.*,sz.megnev AS \"szurestipusNev\" FROM felhasznalok felh 
                            LEFT JOIN ghc_segedtabla ghc ON ghc.torzsszam=felh.torzsszam
                            LEFT JOIN szurestipusok sz ON sz.id=ghc.csomagid
                            WHERE felh.id=?",array($fid))->fetch(PDO::FETCH_ASSOC);

        $mail = self::getDefaultMailer();
        $mail->AddAddress($result["email"]);
        $subject = "Suzuki GHC regisztráció visszaigazolása";
        $html.="<h2 style='color:#DE0039'>Kedves {$result["nev"]}!</h2>";
        $html.="<p style='color:#00368F'>Köszönjük, hogy a Magyar Suzuki Zrt. és a Hungária Med-M Kft. által szervezett munkavállalói szűrővizsgálat (GHC) mellett döntött.</p>";
        $html.="<p style='color:#00368F'><strong>Vizsgálatok időpontja:</strong> 2025. szeptember 15. - 2025. október 3.</p>";
        $html.="<p style='color:#00368F'><strong>Időpontfoglalás kezdete:</strong> 2025. augusztus 25.</p>";

        $html.="<p style='color:#00368F'><strong>Az Ön szűrőcsomagja:</strong> {$result["szurestipusNev"]}</p>";

        $html.="<span style='color:#00368F'><strong>Vizsgálatok helyszíne:</strong></span><br>";
        $html.="<ul style=\"margin-left:10px;color:#00368F\">";
        $html.="<li style=\"list-style: disc;\">Suzuki Aréna</li>";
        $html.="<li style=\"list-style: disc;\">2500 Esztergom, Helischer József út 5.</li>";
        $html.="</ul>";

        $html.="<span style='color:#00368F'><strong>Vizsgálatokkal kapcsolatos értesítések:</strong></span><br>";
        
        $html.="<ul style=\"margin-left:10px;color:#00368F\">";
        $html.=" <li style=\"list-style: disc;\">Regisztrációjáról a Magyar Suzuki Zrt. HR & GA osztálya tájékoztatást kap.</li>";
        $html.=" <li style=\"list-style: disc;\">Szűrővizsgálatainkra 2025 augusztus 25-től foglalhat időpontot, melyre e-mailben és SMS-ben is felhívjuk figyelmét.</li>";
        $html.="</ul>";

        $html.="<span style='color:#00368F'><strong>Egészségpénztári tagság:</strong></span><br>";

        $html.="<ul style=\"margin-left:10px;color:#00368F\">";
        $html.=" <li style=\"list-style: disc;\">A szűrővizsgálatokon való részvételhez OTP Országos Egészség- és Önsegélyező Pénztári tagság szükséges.</li>";
        $html.=" <li style=\"list-style: disc;\">Amennyiben még nem rendelkezik tagsággal, a szűrővizsgálatokat megelőzően a Magyar Suzuki Zrt. munkatársai segítséget nyújtanak a belépéshez.</li>";
        $html.="</ul>";

        $html .= "      <p style=\"text-align:left;margin-bottom:0px;color:#00368F\"><strong>Telefonszámaink, ahol érdeklődhet:</strong></p>";
        $html .= "      <ul style=\"margin-left: 10px;text-align:left;color:#00368F\">";
        $html .= "          <li style=\"list-style: disc\"><span  style='font-size:14px'>Suzuki - Teberi Andrea: +3630-122-9084</li>";
        $html .= "          <li style=\"list-style: disc\"><span style='font-size:14px'>Suzuki - Balogh Miklós: +3620-587-8696</li>";
        $html .= "          <li style=\"list-style: disc\"><span style='font-size:14px'>Hungária Med-M - Szabó Melinda: +3670-779-9485</li>";
        $html .= "      </ul>";

        $html.= "<div style=\"margin-bottom:50px\"></div>";
        
        $html.= "<div style=\"width:100%\">";
        //$html .= "  <img src=\"https://uj.hungariamed.hu/assets/hmm_logo_nagy.png\" width=\"150px\" class=\"d-none d-md-inline\" style=\"margin:10px\">";
        $html .= "  <img src=\"https://{$_SERVER["HTTP_HOST"]}/images/suzuki_ghc_email_logo_banner_uj2.png\" style=\"max-height:180px; margin:10px\">";
        //$html .= "  <img src=\"https://{$_SERVER["HTTP_HOST"]}/images/suzuki_horizontal.png\" width=\"150px\" class=\"d-none d-md-inline\" style=\"margin:10px\">";
        //$html .= "  <div style=\"font-family:SuzukiProBold;font-size:16px\">Suzuki EGÉSZSÉGÚT, az érezhető TÖRŐDÉS</div>";
        $html.= "</div>";


        $mail->Subject = $subject;
        $mail->Body = $html;
        if($mail->Send()){
            $this->createNotificationRecord("regisztraciomegerosito", $fid, $result["email"], $subject, $html);
            return "E-mail sent.";
        }
        return;
    }

    public function astotec_reg_confirmation_notification($fid){

        $html= "";
        $result = sql_query("SELECT felh.*,sz.megnev AS \"szurestipusNev\" FROM felhasznalok felh 
                            LEFT JOIN ghc_segedtabla ghc ON ghc.torzsszam=felh.torzsszam
                            LEFT JOIN szurestipusok sz ON sz.id=ghc.csomagid
                            WHERE felh.id=?",array($fid))->fetch(PDO::FETCH_ASSOC);

        $mail = self::getDefaultMailer();
        $mail->AddAddress($result["email"]);
        $subject = "Astotec szűrés regisztráció visszaigazolása";
        $html.="<h2>Kedves {$result["nev"]}!</h2>";
        $html.="Köszönjük, hogy a Astotec Automotive HU Bt. és a Hungária Med-M Kft. által szervezett munkavállalói szűrővizsgálat mellett döntött.<br><br>";
        $html.="<strong>Vizsgálatok időpontja:</strong> 2024. szeptember 03. - 2024. szeptember 06.<br><br>";
        //$html.="<strong>Időpontfoglalás kezdete:</strong> 2024. szeptember 02.<br><br>";

        $html.="<strong>Vizsgálatok helyszíne:</strong><br>";
        $html.="<ul style=\"margin-left:10px\">";
        $html.="<li style=\"list-style: disc;\">Pápa, Astotec parkoló</li>";
        //$html.="<li style=\"list-style: disc;\">Pápa, Astotec parkoló</li>";
        $html.="</ul>";

        $html.= "<p>Az Időpontfoglaláshoz <a href=\"https://astotec.hungariamed.hu/index.php?page=login\" target=\"_blank\" style=\"color:#a00\">kattintson ide!</a></p>";

        $html.= "<div style=\"margin-bottom:50px\"></div>";
        
        $html.= "<div style=\"width:100%\">";
        $html .= "  <a href=\"https://www.hungariamed.hu\" target=\"_blank\"><img src=\"https://uj.hungariamed.hu/assets/hmm_logo_nagy.png\" width=\"150px\" class=\"d-none d-md-inline\" style=\"margin:10px\"></a>";
        //$html .= "  <img src=\"https://{$_SERVER["HTTP_HOST"]}/images/suzuki_ghc_email_logo_banner_uj.png\" style=\"max-height:180px; margin:10px\">";
        $html.= "</div>";


        $mail->Subject = $subject;
        $mail->Body = $html;
        if($mail->Send()){
            $this->createNotificationRecord("regisztraciomegerosito", $fid, $result["email"], $subject, $html);
            return "E-mail sent.";
        }
        return;
    }

    /**
     * beutalóhoz tartozó levelek/fájlok küldése
     * @param   string  $params["type]              Küldés típusa 
     * @param   string  $params["destination"]      Címzett
     * @param   string  $params["addressee"]        Címzett neve
     * @param   array   $params["file"]             Fájl azonosító és fájlnév
     * @param   string  $params["sender"]           Feladó címe
     * @param   string  $params["copy"]             Másolatot kap
     * @param   string  $params["subject"]          Levél tárgy
     * @param   string  $params["content"]          Levél tartalom
     * 
     * @return  integer Notification adatsor azonosítója.
    */
    public function sendReferalNotification($params):int{
       
        $mail = self::getDefaultMailer();
        $mail->From = $params["sender"];
        $filePath = "";
        //$mail->AddAddress($params["destination"]);
        //$mail->AddCC($params["copy"]);
        $mail->AddAddress("tesztemail@hungariamed.hu");
        //$mail->AddCC("marton.gergely@hungariamed.hu");
        $search = ["#nev#"];
        $replace = [$params["addressee"]];
        $params["content"] = str_replace($search,$replace,$params["content"]);

        $mail->Subject = $params["subject"];
        $mail->Body = $params["content"];

        if(!empty($params["file"])){
            $docAgent = New DocAgent();
            $docPath =  __DIR__ . "/other/tmp/";
            $docAgent = new DocAgent();
            $fileContent = $docAgent->getDoc($params["file"]["id"]);
            $filePath = $docPath . $params["file"]["filename"];
            file_put_contents($filePath, $fileContent);
            $fileContent = null;
            $mail->AddAttachment($filePath);
        }

        if(!empty($params["list"])){
            $mail->AddAttachment($params["list"]);
        }

        $mail->Send();
        if($filePath){
            unlink($filePath);
        }
        if(!empty($params["list"])){
            unlink($params["list"]);
        }
       
        return $this->createNotificationRecord($params["type"], $params["objectId"], implode(",",[$params["destination"],$params["copy"]]), $params["subject"], $params["content"]);
    }

    public function logProcess() {
        //$log = $this->apacheLog;
        //$reservationIds = [];

        $nincsutkozes = $vanutkozes = 0;

        $reservations = sql_query("SELECT f.*, o.nev as orvosnev FROM foglalasok f left join orvosok o on o.id=f.orvosassigned WHERE foglalta='restore'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reservations as $reservation) {

            $collisions = sql_query("select f.* from foglalasok f where f.datum=? and orvosassigned=? and f.nev<>?", [$reservation["datum"], $reservation["orvosassigned"], $reservation["nev"]])->fetchAll(PDO::FETCH_ASSOC);

            if (empty($collisions)) {
                $nincsutkozes++;
            } else {
                echo $reservation["nev"]." ".$reservation["datum"] . " orvos: {$reservation["orvosnev"]}<br>";
                //echo $reservation["nev"]."<br>";

                //foreach ($collisions as $collision) {
                //    echo "collision: ".$reservation["nev"]." ".$reservation["datum"] . " orvos: {$reservation["orvosnev"]}<br>";
                //}


                $vanutkozes++;
            }


        }

        echo "nincs ütközés: {$nincsutkozes}<br>";
        echo "van ütközés: {$vanutkozes}<br>";

        die;

        $rows = explode("\n", $log);
        foreach ($rows as $row) {
            $id = substr($row, strpos($row, "&id=")+4, 10);
            //echo intval($id)."<br/>";
            $reservationIds[] = intval($id);
        }

        $reservationIds = array_unique($reservationIds);
        echo "<pre>";
        echo implode(", ", $reservationIds). " ".count($reservationIds);
        echo "</pre>";

        $notifications = sql_query("select * from notifications where objectid in (".implode(", ", $reservationIds).") and tipus='usernotification' group by objectid");

        foreach ($notifications as $notification) {
            echo "<hr>";
            echo str_replace("<hr>", "<br>", str_replace("h1>", "h3>", $notification["szoveg"]));
        }

        echo "select * from activitylog where mid in (".implode(", ", $reservationIds).") and tipus='foglalas' order by datum<br>";
        $activities = sql_query("select * from activitylog where mid in (".implode(", ", $reservationIds).") and tipus='foglalas' AND userid=0 AND INSTR(megnev, 'felhasználó foglalás') order by datum");

        foreach ($activities as $activity) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);

            echo $activity["query"];
            $data = json_decode($activity["query"], JSON_OBJECT_AS_ARRAY);
            echo "<hr>";
            echo "data:<br>";
            echo "<pre>";
            //echo print_r($data, true);
            echo "<pre>";

            $companyId = 11;
            if (!empty($data["paciensid"])) {
                if ($paciendData = sql_query("select * from felhasznalok where id=?", [$data["paciensid"]])->fetch(PDO::FETCH_ASSOC)) {
                    $companyId = $paciendData["cegid"];
                }
            } else {
                $data["paciensid"] = 0;
            }

            if (empty($data["selectedtelephely"])) {
                $data["selectedtelephely"] = 0;
            }
            if (empty($data["szulhely"])) {
                $data["szulhely"] = 0;
            }
            if (empty($data["anyjaneve"])) {
                $data["anyjaneve"] = 0;
            }


            $pass = rand(11111, 99999);

            echo "query:<br>";
            sql_query("insert into foglalasok set
               cegid=?,
               szurestipusid=?,
               helyszinid=?,
               orvosassigned=?,
               rinterval=?,
               datum=?,
               taj=?,
               nev=?,
               megj=?,
               szulhely=?,
               szuldatum=?,
               anyjaneve=?,
               irsz=?,
               varos=?,
               utca=?,
               munkakor=?,
               telefon=?,
               neme=?,
               email=?,
               paciensid=?,
               aktiv=1,
               rkod=?,
               pass=?,
               telephely=?,
               foglalta='restore'",[
                    $companyId,
                    $data["szurestipus"],
                    $data["helyszin"],
                    $data["orvosid"],
                    $data["rinterval"],
                    $data["datum"],
                    $data["taj"],
                    $data["nev"],
                    $data["megj"],
                    $data["szulhely"],
                    $data["szuldatum"],
                    $data["anyjaneve"],
                    $data["irsz"],
                    $data["varos"],
                    $data["utca"],
                    $data["munkakor"],
                    $data["telefon"],
                    $data["neme"],
                    $data["email"],
                    $data["paciensid"],
                    $pass,
                    $pass,
                    $data["selectedtelephely"],
            ]);
            echo "query end:<br>";

            //die;
        }

    }

    public function xmasCampaign2024() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $subscribedEmails = [];
        //$subscribedEmails[] = "jnsmobil@gmail.com";

        $emailokRaw = file_get_contents("/var/www/onlinebejelentkezes_keltexmed/library/other/top100_emailok.txt");
        foreach (explode("\n", $emailokRaw) as $line) {
            $line = str_replace([";", ",", ":", "?", "\"", " ", "\t", "\n", "\r"], ["", "", "", "", "", "", "", "", ""], $line);
            $line = trim($line);
            if (!empty($line)) {
                if (!filter_var($line, FILTER_VALIDATE_EMAIL)) {
                    //rossz email
                    //echo strlen($line) . $line . PHP_EOL;
                } else {
                    if (in_array($line, $subscribedEmails)) {
                        //echo "Ez többször van: " . $line . PHP_EOL;
                    } else {
                        $subscribedEmails[] = $line;
                    }
                }
            }
        }

        echo "count:" . count($subscribedEmails). PHP_EOL;
        //die();

        if (true) {
            $subscribedEmails = [];
            //$subscribedEmails[] = "jns@jns.hu";
            $subscribedEmails[] = "jnsmobil@gmail.com";
            //$subscribedEmails[] = "adamekne.tannert.ildiko@hungariamed.hu";
            //$subscribedEmails[] = "kuzdy@kuzdy.hu";
            //$subscribedEmails[] = "sandor@hungariamed.hu";
        }


        print_r($subscribedEmails);



        $mail = self::getDefaultMailer();
        $mail->From = "kuzdy@hungariamed.hu";
        $mail->FromName = "Dr. Küzdy Gábor";

        $mail->AddEmbeddedImage("/var/www/onlinebejelentkezes_keltexmed/public/images/image003.png", "image003");
        $mail->AddEmbeddedImage("/var/www/onlinebejelentkezes_keltexmed/public/images/logo-retina.png", "logoimage");
        $mail->AddAttachment("/var/www/onlinebejelentkezes_keltexmed/public/images/MaESZ_Igenylesi_es_Tamogatasi_Projekt_2025-11-14.pdf");

        $number = 0;
        foreach ($subscribedEmails as $email) {
            $number ++;

            $body = file_get_contents("/var/www/onlinebejelentkezes_keltexmed/public/images/hirlevel_2025_01.html");

            $body = str_replace("{EMAIL}", $email, $body);
            $body = str_replace("{EMAILURL}", urlencode($email), $body);

            $mail->clearAddresses();
            $mail->clearCCs();
            $mail->clearBCCs();

            $mail->addAddress($email);
            $mail->Subject = "90%-os támogatottságú Egészségvédelmi szűrőprogramok országosan";
            $mail->Body = $body;
            $mail->send();
            echo "{$number}. sent: ".date("Y-m-d H:i:s")." {$email}".PHP_EOL;
        }
    }

    public function tesco_ertesito(){
        
        $data = sql_query("SELECT fogl.nev,fogl.email,fogl.telefon,fogl.datum,h.cim,sz.megnev FROM foglalasok fogl
                           LEFT JOIN helyszinek h ON h.id=fogl.helyszinid
                           LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
                           WHERE fogl.cegid=682 AND fogl.datum>='2025-10-15';")->fetchAll(PDO::FETCH_ASSOC);

        foreach($data as $row){
            $title = "Sikeres időpont regisztráció";

            $text = "<strong>{$row["datum"]} - {$row["cim"]}</strong><br><br>";
            $text.= "Név: {$row["nev"]}<br><br>"; 
            $text.= "<strong>Időpont: {$row["datum"]}</strong><br><br>";
            $text.= "Szűrés típusa: {$row["megnev"]}<br>";
            $text.= "Helyszín: {$row["cim"]}";
            $text.= "<hr>"; 
            $text.="<br><p><strong>Kedves Jelentkező!</strong></p></br>";
            $text.="<p>Köszönjük a regisztrációdat!<br>";
            $text.="Várunk szeretettel a Tesco vérvételi szűrésen!<br><br>";
            $text.="Az alábbi linkre kattintva megnézhetitek a finanszírozott és a kiemelt önköltséges csomagok részleteit, és itt tudtok időpontot foglalni a vérvételre.<br>";
            $text.="<a style='color:#a00' href='https://tesco-webshop.hungariamed.hu/' target='_blank'>https://tesco-webshop.hungariamed.hu/</a><br><br>";
            $text.="Mindenki egészségének megőrzése fontos számunkra, hisz ez a küldetésünk, ezért kérünk Benneteket, hogy juttassátok el minél több kollégátokhoz a szükséges információkat, és segítsétek Őket a jelentkezésben!<br><br>";
            $text.="Örömmel vesszük az előzetes bejelentkezést a gördülékenyebb munka érdekében. Természetesen helyszíni regisztrációra is van lehetőség, amelyben a Hungária Med-M Kft. dolgozói segítenek.<br>";
            $text.="</p><br>";
            $text.="<p>Hungária Med-M csapata</p>";

            $body = $this->setMailTemplate($text,$title);
            
            //$mail = self::getDefaultMailer();
            //$mail->AddAddress($row["email"]);
            //$subject = "Sikeres időpont regisztráció!";
        

            //$mail->Subject = $subject;
            //$mail->Body = $body;
            //$mail->Send();
        }
        echo "done.";
        
    }

    public function samsung_notification(){
        $html= "";

        $result = sql_query("SELECT datum,nev,szuldatum,taj,email FROM foglalasok 
                             WHERE cegid=1629 AND helyszinid=1246 
                             AND datum BETWEEN '2026-04-20 00:00:01' AND '2026-04-24 23:59:59';")->fetchAll(PDO::FETCH_ASSOC);
        $unit = 0;
        foreach($result as $row)
        {
            $html= "";
            $unit++;
            $mail = self::getDefaultMailer();
            $mail->AddAddress($row["email"]);
            $mail->addBCC("tesztemail@hungariamed.hu");
            //$mail->addAddress("tesztemail@hungariamed.hu");
            $subject = "Lung Screening – Reminder and Information";
                            
            $html .="<p style='font-family:calibri'>[ENG]</p>";

            $html .="<p style='font-family:calibri'>Lung Screening – Reminder and Information</p>";
            $html .="<p style='font-family:calibri'>Dear Colleagues,</p>";
            $html .="<p style='font-family:calibri'>We are delighted to see that so many of you have signed up for our Health Day.</p>";
            $html .="<p style='font-family:calibri'>Please read the following information very carefully.</p>";

            $html .="<ol>";
            $html .="   <li>";
            $html .="       <p style='font-family:calibri'><strong>Please arrive 5 minutes before your scheduled screening time!</strong></p>";
            $html .="   </li>";
            $html .="   <ul style=\"margin-left:10px;font-family:calibri\">";
            $html .="      <li style=\"list-style: disc;\">";
            $html.="           <strong>Check your appointment time:</strong> You received a confirmation email at this E-mail address after booking your appointment."; 
            $html .="          Look for the sender: \"Hungariamed\"; you will find your appointment time in the email.";
            $html .="      </li>";
            $html .="   </ul>";
            $html .="   <li>";
            $html .="       <p style='font-family:calibri'><strong>Location: SDIHU Central Park (at the main entrance)</strong></p>";
            $html .="   </li>";
            $html .="   <li>";
            $html .="       <p style='font-family:calibri'><strong>The screening process:</strong></p>";
            $html .="   </li>";
            $html .="   <ul style=\"margin-left:10px;font-family:calibri\">";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Quick data verification (please bring your ID or TAJ card)";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Screening";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           You will receive your results via email, and they will also be uploaded to the EESZT platform (within 1 month)";
            $html .="      </li>";
            $html .="   </ul>";
            $html .="   <li style=\"list-style: none;\">";
            $html .="       <p style='font-family:calibri'><strong>Join our 30-minute presentation on the importance of regular screening & health awareness</strong></p>";
            $html .="   </li>";
            $html .="   <ul style=\"margin-left:10px;font-family:calibri\">";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Date: Tuesday, April 21, 2026";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Start Time: 10:00 AM";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Value 4 – Welfare Building";
            $html .="      </li>";
            $html .="   </ul>";
            $html .="</ol>";
            $html .="<hr>";

            $html .="<p style='font-family:calibri'>[HUN]</p>";

            $html .="<p style='font-family:calibri'>Tüdőszűrés – Emlékeztető és információ</p>";
            $html .="<p style='font-family:calibri'>Kedves Kollégák,</p>";
            $html .="<p style='font-family:calibri'>Örömmel látjuk, hogy ilyen sokan jelentkeztetek az egészségnapunkra.</p>";
            $html .="<p style='font-family:calibri'>Kérünk benneteket, nagyon figyelmesen olvassátok végig az alábbi információkat.</p>";

            $html .="<ol>";
            $html .="   <li>";
            $html .="       <p style='font-family:calibri'><strong> A szűrésre foglalt időpontod előtt 5 perccel jelenj meg!</strong></p>";
            $html .="   </li>";
            $html .="   <ul style=\"margin-left:10px;font-family:calibri\">";
            $html .="      <li style=\"list-style: disc;\">";
            $html.="           <strong>Időpontot ellenőrzése:</strong> Erre az e-mail címedre kaptál visszaigazolást az időpontfoglalás után. Keress rá a feladóra: „Hungariamed\", az E-mailben megtalálod az időpontodat.";
            $html .="      </li>";
            $html .="   </ul>";
            $html .="   <li>";
            $html .="       <p style='font-family:calibri'><strong>Helyszín: SDIHU Central Park ( A főbejáratnál )</strong></p>";
            $html .="   </li>";
            $html .="   <li>";
            $html .="       <p style='font-family:calibri'><strong>A szűrés folyamata:</strong></p>";
            $html .="   </li>";
            $html .="   <ul style=\"margin-left:10px;font-family:calibri\">";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Gyors adategyeztetés (hozd magaddal személyi vagy TAJ kártyádat)";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Szűrés";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Az eredményedet E-mailben kapod majd meg, és az EESZT felületen is felöltésre kerül (1 hónapon belül)";
            $html .="      </li>";
            $html .="   </ul>";
            $html .="   <li style=\"list-style: none;\">";
            $html .="       <p style='font-family:calibri'><strong>Gyere el a szűréssel és az egészségmegőrzéssel kapcsolatos 30 perces előadásunkra is!</strong></p>";
            $html .="   </li>";
            $html .="   <ul style=\"margin-left:10px;font-family:calibri\">";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="          Dátum: 2026. 04. 21-én Kedd";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Kezdés Időpontja: 10:00";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Value 4 – Welfare Épület";
            $html .="      </li>";
            $html .="   </ul>";
            $html .="</ol>";
            $html .="<hr>";

            $html .="<p style='font-family:calibri'>[UA]</p>";

            $html .="<p style='font-family:calibri'>Обстеження легенів – Нагадування та інформація</p>";
            $html .="<p style='font-family:calibri'>Шановні колеги,</p>";
            $html .="<p style='font-family:calibri'>Ми раді бачити, що так багато з вас зареєструвалися на наш день здоров'я.</p>";
            $html .="<p style='font-family:calibri'>Просимо вас дуже уважно прочитати наведену нижче інформацію.</p>";

            $html .="<ol>";
            $html .="   <li>";
            $html .="       <p style='font-family:calibri'><strong>З'явіться на обстеження за 5 хвилин до призначеного часу!</strong></p>";
            $html .="   </li>";
            $html .="   <ul style=\"margin-left:10px;font-family:calibri\">";
            $html .="      <li style=\"list-style: disc;\">";
            $html.="           <strong>Перевірка часу:</strong>Після бронювання часу ви отримали підтвердження на цю електронну адресу. Знайдіть відправника: «Hungariamed», у листі ви знайдете час вашого візиту.";
            $html .="      </li>";
            $html .="   </ul>";
            $html .="   <li>";
            $html .="       <p style='font-family:calibri'><strong>Місце проведення: SDIHU Central Park (біля головного входу)</strong></p>";
            $html .="   </li>";
            $html .="   <li>";
            $html .="       <p style='font-family:calibri'><strong>Процес обстеження:</strong></p>";
            $html .="   </li>";
            $html .="   <ul style=\"margin-left:10px;font-family:calibri\">";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Швидка перевірка даних (візьміть із собою паспорт або картку TAJ)";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Обстеження";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Результати ви отримаєте електронною поштою, а також вони будуть завантажені на платформу EESZT (протягом одного місяця)";
            $html .="      </li>";
            $html .="   </ul>";
            $html .="   <li style=\"list-style: none;\">";
            $html .="       <p style='font-family:calibri'><strong>Завітайте також на нашу 30-хвилинну лекцію, присвячену обстеженню та збереженню здоров'я</strong></p>";
            $html .="   </li>";
            $html .="   <ul style=\"margin-left:10px;font-family:calibri\">";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="          Дата: вівторок, 21 квітня 2026 року";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="            Час початку: 10:00";
            $html .="      </li>";
            $html .="      <li style=\"list-style: disc;\">";
            $html .="           Будівля Value 4 – Welfare";
            $html .="      </li>";
            $html .="   </ul>";
            $html .="</ol>";
            
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->Send();

            echo $unit."<br>";
        }

        //return $html;
    }

    public function tesco_notification(){

        $dataSet = sql_query("SELECT fogl.nev,fogl.szuldatum,fogl.taj,fogl.email,telefon,fogl.helyszinid FROM foglalasok fogl
                              WHERE fogl.cegid=682 AND fogl.helyszinid IN(909,912,908,910) 
                              AND INSTR(fogl.datum,'2024') AND NOT EXISTS(SELECT * FROM foglalasok WHERE taj=fogl.taj AND INSTR(datum,'2026'));")->fetchAll(pdo::FETCH_ASSOC);

        $message = "Kedves TESCO munkavállaló! \n";
        $message .="Ön 2024 októberében jelentkezett a Tesco ingyenes vérvételére, amelyre idén is szeretettel várjuk, amennyiben még mindig a Tesco munkavállalója. \n";
        $message .="#helydatum# \n";
        $message .="Jelentkezés: https://tesco-webshop.hungariamed.hu/home (vagy a helyszínen) \n";
        $message .="Üdvözlettel, \n";
        $message .="A Hungária Med-M Csapata \n";
        $search = "#helydatum#";
        $body = "";
        $places = [
            ["helyszinid"=>909,"helydatum"=>"Holnap (május 11.) 7:00-9:30 között az Ön áruházában."],
            ["helyszinid"=>912,"helydatum"=>"Holnap (május 11.) 10:00-11:30 között az Ön áruházában."],
            ["helyszinid"=>908,"helydatum"=>"Holnap (május 11.) 12:00-12:30 között az Ön áruházában."],
            ["helyszinid"=>910,"helydatum"=>"Holnap (május 11.) 14:00-14:30 között az Ön áruházában."],
        ];
        $i=0;
        foreach($dataSet as $client){
            $i++;
            
            $key = array_search($client["helyszinid"],array_column($places,"helyszinid"));
            if($key!==false){
                $replace = $places[$key]["helydatum"];
                $body = str_replace($search,$replace,$message);
                $this->utils->sendSMS(trim($client["telefon"]),$body);
                //echo "{$i}. {$client["nev"]} részére kiküldve. ({$client["telefon"]})<br>";
            }           
        }
        
    
        /*echo "<pre>";
        print_r($dataSet);
        echo "</pre>";*/

        return;
    }
}
