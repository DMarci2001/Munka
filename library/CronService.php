<?php


class CronService {

    private $interval = null;
    private $utils;
    private $bookingService;

    public function __construct()
    {
        if (isset($_GET["interval"])) {
            $this->interval = $_GET["interval"];
        } else {
            $this->interval = "perc";
        }
        $this->utils = new Utils();
        $this->bookingService = new BookingService();



        if (isset($_GET["dicomteszt"])) {
            $dicomService = new DicomService();
            $dicomService->teszt();
            die;
        }

        if (isset($_GET["missingteszt"])) {
            $this->sendMissingDataEmails();
            die("ok");
        }

        if (isset($_GET["sync"])) {
            $api = new BookingSyncApi();
            $reservations = sql_query("select * from foglalasok where orvosassigned='418' AND datum>NOW() AND externalid=''");
            foreach ($reservations as $reservation) {
                echo "{$reservation["nev"]}\n";
                //$api->newReservation($reservation["id"]);
            }

            die("ok\n");
        }

    }

    public function run() {
        if ($this->interval == "perc") {
            //percenként futó cronok
            $this->_deleteNotActivatedReservations();
            $this->_smsAlertBeforeReservation();
            $this->_updateNaploszam();
            $this->_sendFoglaljOrvostHeartBeat();
			$this->sendReservationReminders();
			$this->sendMissingDataEmails();
            $this->checkOneWebPage();

			$dicomService = new DicomService();
			$dicomService->processEntries();

			$foService = new FoglaljOrvostService();
			$foService->retryFailedMessages();
        }

        if ($this->interval == "1ora") {
            //óránként futó cronok
            $this->_sendReservationReportForDoctors();
            $this->_sendReviewMails();
            $this->_sendAlkExcel(); //** régi excel hívást használ, disabled
            $this->_sendAlkExpire();

            $this->checkSzabadsagCollisions();
            $this->checkCollisions();
        }

        if ($this->interval == "teszt") {
            $this->_tesztStuff();
        }

        if ($this->interval == "abi_upload") {
            $this->_abiUpload();
        }
		
		if ($this->interval == "ertesito_teszt") {
			$this->reservationReminder();
		}

    }

    private function _tesztStuff() {
        //$this->_sendAlkExcel();
        //$this->utils->sendSMS("06209996183","időpont foglalása van: 11:30 Győr Rákóczi Ferenc utca 44. Az üzemorvostól kapott beutaló nyomtatványt hozza magával!");
        //$this->sendSzabadsag2FoglaljOrvostBatch();
        $this->checkSzabadsagCollisions();
        $this->checkCollisions();

        echo "teszt\n";
        die();
    }

    private function checkSzabadsagCollisions() {
        $szabadsagok = sql_query("SELECT sz.*, o.nev as orvosnev  FROM szabadsag sz left join orvosok o on o.id = sz.oid WHERE sz.datumtol>=DATE(DATE_SUB(NOW(), INTERVAL 1 MONTH))")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($szabadsagok as $szabadsagData) {
            $szabadsagDatum = $szabadsagData["datumtol"];
            $orvosId = $szabadsagData["oid"];
            echo $szabadsagDatum." ".$szabadsagData["orvosnev"]." ";

            $foglalasok = sql_query("select id from foglalasok where datum>=? and datum<=? and orvosassigned=? and aktiv=1 and nev<>'niffncs név'", [date("Y-m-d 00:00:00", strtotime($szabadsagDatum)), date("Y-m-d 23:59:59", strtotime($szabadsagDatum)), $orvosId])->fetchAll(PDO::FETCH_ASSOC);

            if (count($foglalasok) > 0) {
                echo "not ok ".count($foglalasok);

                $meta = "szabadsag_{$szabadsagDatum}_{$orvosId}";
                $text =  "{$szabadsagData["orvosnev"]} {$szabadsagDatum} napi szabadságára ".count($foglalasok)."db foglalás van.";

                if (!sql_fetch_array(sql_query("select id from warnings where metaid=?", [$meta]))) {
                    sql_query("insert into warnings set created=now(), expires=?, metaid=?, orvosid=?, tipusid=?, szoveg=?", [$szabadsagDatum." 23:59:59", $meta, $orvosId, 0, $text]);
                }
            } else {
                echo "ok";
            }

            echo "\n";
        }
    }

    private function checkCollisions() {
        $checkedIds = [];

        $reservations = sql_query("select f.id, f.datum, f.rinterval, f.orvosassigned, f.szurestipusid, t.megnev as szurestipusnev, o.nev as orvosnev from foglalasok f
        left join szurestipusok t on t.id = f.szurestipusid
        left join orvosok o on o.id = f.orvosassigned
        where f.datum>date_sub(now(), interval 3 day) and f.aktiv=1 and f.orvosassigned<>117")->fetchAll(PDO::FETCH_ASSOC);

        echo count($reservations)."\n";

        foreach ($reservations as $reservation) {

            $coll = sql_query("SELECT id, datum, rinterval FROM foglalasok WHERE datum>=date_sub(:datum, interval 1 hour) and datum<=date_add(:datum, interval 1 hour)
                AND ((datum<=:datum AND datum>DATE_SUB(:datum, INTERVAL IF(rinterval=0, 5, rinterval) MINUTE)) OR (datum>=:datum AND datum<DATE_ADD(:datum, INTERVAL :interval MINUTE)))
                AND orvosassigned=:orvosid and id<>:reservationid", ["datum" => $reservation["datum"], "interval" => $reservation["rinterval"], "orvosid" => $reservation["orvosassigned"], "reservationid" => $reservation["id"]])->fetchAll(PDO::FETCH_ASSOC);

            if (count($coll)>0 && !in_array($reservation["id"], $checkedIds)) {
                $collision = $coll[0];


                $reservationTime = date("Y-m-d H:i", strtotime($reservation["datum"]));
                $collisionTime = date("H:i", strtotime($collision["datum"]));

                $meta = "collision_{$reservation["id"]}_{$collision["id"]}";
                $text =  "{$reservationTime} és {$collisionTime} {$reservation["orvosnev"]}, {$reservation["szurestipusnev"]} ";


                if (!sql_fetch_array(sql_query("select id from warnings where metaid=?", [$meta]))) {
                    sql_query("insert into warnings set created=now(), expires=?, metaid=?, orvosid=?, tipusid=?, szoveg=?", [$reservationTime, $meta, $reservation["orvosassigned"], $reservation["szurestipusid"], $text]);
                }

                echo $text."\n".count($coll)."\n";
                $checkedIds[] = $reservation["id"];
                $checkedIds[] = $collision["id"];

            }



        }

    }

    private function sendSzabadsag2FoglaljOrvostBatch() {
        $foService = new FoglaljOrvostService();

        $szabadsagok = sql_query("SELECT sz.* FROM szabadsag sz
        LEFT JOIN orvosok o ON o.id=sz.oid
        WHERE datumtol>=DATE(NOW()) AND o.foid<>0 AND sz.foid=0 group by sz.groupid")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($szabadsagok as $szabadsag) {
            echo $szabadsag["oid"]." ".$szabadsag["groupid"]." ".$szabadsag["datumtol"]."\n";
            //$foService->sendSzabadsag($szabadsag["groupid"]);
            //die;
        }

    }

    private function _checkGDPRFiles() {
        //Tömbbe rendezi a drive tartalmát:
        $downloader = new GoogleDriveDownloader();
        //$files = $downloader->getFiles();

        //Végig futok az SQL-ben rögzített adatsorokon:
        $gdpr = sql_query("SELECT * FROM GDPR WHERE exist_doc = 0 || mod_request = 1");

        if ( $gdpr->rowCount() > 0 )
        {
            $files = $downloader->getFiles();

            while ( $file = sql_fetch_array( $gdpr ))
            {
                //Megvizsgálom, hogy az SQL adatsorhoz tartozik-e a fájl:
                $key = array_search ( $file['id'].".pdf", array_column ( $files, 'name' ));
                //Ha a találati eredmény
                if ( $key !== FALSE )
                {
                    //Ha az SQL sorhoz nincsen még fájl párosítva akkor töltse fel a dokumentumot:
                    if ( $file["exist_doc"] != 1 )
                    {
                        $fileName 	 = $downloader->getFile($files[$key]["id"]);
                        $destination = "doc/GDPR/".$file['id'].".pdf";
                        if ( rename ( $fileName, $destination ))
                        {
                            sql_query("UPDATE GDPR SET exist_doc = 1, last_modify = '{$files[$key]['modifiedtime']}', driverid = '{$files[$key]['id']}' WHERE id = {$file['id']}");
                        }
                    }
                    //Ha már létezik dokumentum és módosítási kérelem történik, akkor cserélhesse le a dokumentumot:
                    if ($file["exist_doc"] == 1 && $file["mod_request"] == 1 && $file['last_modify'] != $files[$key]['modifiedtime'] )
                    {
                        $fileName 	 = $downloader->getFile($files[$key]["id"]);
                        $destination = "doc/GDPR/".$file['id'].".pdf";
                        if ( rename ( $fileName, $destination ))
                        {
                            sql_query("UPDATE GDPR SET mod_request = 0, last_modify = '{$files[$key]['modifiedtime']}', driverid = '{$files[$key]['id']}' WHERE id = {$file['id']}");
                        }
                    }
                }
            }
        }
    }

    private function _abiUpload() {
        $path = "abi_folder";
        $files = array_diff(scandir($path), array('.', '..'));

        $data = array();

        //Végig megyek az összes fájlon és $data array-ba gyűjtöm a fájlhoz tartozó páciensek id-ját(mindnek van le csekkoltam előre)
        foreach ($files as $pdf) {
            $size = filesize($path."/".$pdf);
            $filename = explode(".",$pdf);

            $result = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE taj=? AND cegid=104",array($filename[0])));
            $data[] = array("id"=> $result['id'],"nev"=>$result['nev'],"file"=>$pdf,'size'=>$size);
        }

        //végig megyek az így elkészült tömbön és az adatokat insertelem az a dokumentumok táblába és áthelyezem az új folder-be a fájlt:
        foreach($data as $file){
            sql_query("INSERT INTO dokumentumok SET 
				   beutaloid = 0, userid =".$file['id'].", 
				   megnev    = 'abi',
				   filename  = 'abi.pdf',
				   size      = '{$file['size']}',
				   tipus     = 'pdf',
				   datum     = now(),
				   kod       = SHA1(MD5(CONCAT(NOW(),RAND()*20000)))");

            $id = sql_insert_id();
            $destination = getDocPath( $id );
            if( copy($path."/".$file['file'], $destination )){
                echo "siker xd({$file[file]})";
            } else {
                echo "Nem sikerült a feltöltés! ({$file['file']})<br/>";
                sql_query("DELETE FROM dokumentumok WHERE id = ?",array($id));
            }
        }
    }

    private function _deleteNotActivatedReservations() {
        //1 órán belül nem aktivált foglalások törlése
        $service = new NotificationService();
        $res = sql_query("select * from foglalasok where regdatum<date_sub(now(),interval 1 hour) AND regdatum>DATE_SUB(NOW(),INTERVAL 2 HOUR) and aktiv=0 and externalid=''");
        while ($row = sql_fetch_array($res)) {
            $service->sendNotConfirmedReservationMessages($row["id"]);
            $this->bookingService->deleteReservation($row["id"], $row["rkod"], true);
        }
    }

    public function _smsAlertBeforeReservation() {
        //sms értesítés a foglalás előtt
        if (!in_array(date("G"),array(22,23,0,1,2,3,4,5))) {
            $res = sql_query("SELECT f.*,h.cim FROM foglalasok f 
            LEFT JOIN helyszinek h ON h.id=f.helyszinid
		    LEFT JOIN cegek c ON c.`id`=f.`cegid`
		    WHERE datum>NOW() AND datum<DATE_ADD(NOW(),INTERVAL c.smshour hour) AND f.telefon<>'' AND f.aktiv=1 AND smssent=0 AND f.externalid=''");
            while ($row = sql_fetch_array($res)) {
                sql_query("update foglalasok set smssent=1 where id='{$row["id"]}'");

                $szoveg = Booking_Constants::COMPANY_NAME_SHORT." időpont foglalása van: ".substr($row["datum"],11,5)." {$row["cim"]}";
                if ($row["rlang"] == "en") {
                    $szoveg = Booking_Constants::COMPANY_NAME_SHORT.": You have an appointment - ".substr($row["datum"],11,5)." {$row["cim"]}";
                }
                if ($row["rlang"] == "de") {
                    $szoveg = Booking_Constants::COMPANY_NAME_SHORT.": You have an appointment - ".substr($row["datum"],11,5)." {$row["cim"]}";
                }

                $tel = $row["telefon"];
                if (in_array(substr($tel,0,2),array("06","36"))) {
                    $this->utils->sendSMS($tel,$szoveg);
                    echo "sms sent to: {$tel}\n";
                }
                //mail("jns@jns.hu",$szoveg,"");
            }
        }
    }

    private function _updateNaploszam() {
        //naplószám átírása foglalásba (beutaló -> foglalás)
        $res = sql_query("SELECT id,nszam FROM foglalasok WHERE checked=0 limit 100");
        while ($row = sql_fetch_array($res)) {
            if ($rowb = sql_fetch_array(sql_query("select * from beutalok where foglalasid='{$row["id"]}' and naploszam<>''"))) {
                sql_query("update foglalasok set nszam='{$rowb["naploszam"]}' where id='{$row["id"]}'");
            }
            sql_query("update foglalasok set checked=1 where id='{$row["id"]}'");
        }
    }

    private function _sendReviewMails() {
        $service = new NotificationService();
        //érkeztetett foglalásokra elégedettségi form kiküldése
        $res = sql_query("SELECT * FROM foglalasok WHERE eljott=1 AND eljottmail=0 AND datum>DATE_SUB(NOW(), INTERVAL 2 DAY) AND datum<NOW() AND email<>'' and externalid=''");
        while ($foglalasData=sql_fetch_array($res)) {
            $service->sendEljottMail($foglalasData);
        }
    }

    private function _sendReservationReportForDoctors() {
        //sms jelentés a másnapra összegyűlt foglalásokról az orvosoknak 19 órakor
        if (date("G")==19) {
            //$orvosFilter="and o.pecsetszam<>'44563'";
            //if (date("G")==7) $orvosFilter="and o.pecsetszam='44563'";

            //$fnszereplok="15,42";

            $holnapDate    = date("Y-m-d", strtotime("+1 day"));
            $holnapWeekDay = date("N", strtotime("+1 day"));
            $weekNumber    = date("W", strtotime("+1 day"));
            $paros         = (date("W", strtotime("+1 day"))%2==0?2:1);

            $res=sql_query("SELECT b.orvosid,b.helyszinid,b.cegid,b.tipusok,o.nev,tel FROM orvos_beosztas_new b 
		        LEFT JOIN orvosok o ON o.id=b.orvosid 
		        WHERE ((nap='{$holnapWeekDay}' AND hetek IN (0,{$paros})) or (nap=10 and beonap='{$holnapDate}')) AND o.aktiv=1 AND o.tel<>'' and o.smsgroupfoglalas=1
		        GROUP BY orvosid");

            while ($row = sql_fetch_array($res)) {
                $oid = $row["orvosid"];
                //echo $oid.$row["nev"]." ".$row["tel"]." ";

                $resf = sql_query("SELECT * FROM foglalasok WHERE DATE(datum)='{$holnapDate}' AND orvosassigned='{$oid}' and aktiv=1 ORDER BY datum DESC");
                $num = sql_num_rows($resf);

                $resp = sql_query("select * from smsphones where orvosid=? and smsgroupfoglalas=1 and instr(cegek,'|{$row["cegid"]}|')",array($oid));
                while ($rowp = sql_fetch_array($resp)) {
                    $tel = $rowp["tel"];

                    if ($num == 0) {
                        $this->utils->sendSMS($tel,"A holnapi napra ({$holnapDate}) nem érkezett foglalása");
                        //ha nincs foglalása másnapra, lezárjuk a napot
                        //sql_query("insert into foglaltnapok set nap='{$holnapDate}',helyszinid='{$row["helyszinid"]}',helyszinceg='{$row["cegid"]}',szurestipusid='{$row["tipusok"]}',foglalta='system'");
                    } else {
                        $this->utils->sendSMS($tel,"{$num} foglalása érkezett holnapra ({$holnapDate})");
                    }
                }
                echo "\n";
            }
        }

        echo "ok\n";
    }

    private function _sendAlkExcel() {
        /*
        if (date("G") == 18) {
            $request = sql_query("SELECT * FROM cegek WHERE alksend = 1");
            while ($result = sql_fetch_array($request)) {
                $mails = explode(";", $result['sendmail']);
                $this->utils->send_alkExcel($result['id'], $result['alksendint'], $mails);
            }
        }
        */
    }

    private function _sendAlkExpire() {
        if (date("G") == 19) {
            $cc = array();
            $query = sql_query("SELECT * FROM cegek WHERE alkertsend = 1");
            while ($cegek = sql_fetch_array($query)) {
                $cc[] = $cegek['id'];
            }
            //Alkalmassági lejárat értesítő app:
            // cég
            echo $this->utils->ENS($cc);
        }
    }

    private function _sendFoglaljOrvostHeartBeat() {
        if (date("i") == 45) {
            $foService = new FoglaljOrvostService();
            $foService->sendPing();
        }
    }
	
	private function sendReservationReminders() {
        $notificationService = new NotificationService();

		if (date("G:i") == "10:00") {
			$request = sql_query("SELECT fogl.datum,h.cim,o.nev,fogl.email,sz.megnev,fogl.id,fogl.rkod,fogl.rlang FROM foglalasok fogl
								  LEFT JOIN cegek c ON c.id=fogl.cegid
								  LEFT JOIN helyszinek h ON h.id=fogl.helyszinid 
								  LEFT JOIN orvosok o ON o.id=fogl.orvosassigned
								  LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
								  WHERE c.emlekezteto_email_kuldes=1 
								  AND (fogl.emlekezteto_mail IS NULL OR fogl.emlekezteto_mail <> 1)
								  AND fogl.datum LIKE '".date("Y-m-d",strtotime("Now + 1 day"))."%'
								  ");
								  
			while($result=sql_fetch_array($request)) {
			    $notificationService->reservationReminder($result);
            }
		}
	}

    private function sendMissingDataEmails() {
        $notificationService = new NotificationService();

        $reservations = sql_query("SELECT id, nev, email, regdatum, datum, CONCAT(SHA1(CONCAT(regdatum, id)), SHA1(CONCAT(nev, regdatum)), SHA1(CONCAT(id, nev, regdatum))) AS h FROM foglalasok 
            WHERE regdatum>DATE_SUB(NOW(), INTERVAL 1 DAY) AND datum<DATE_ADD(NOW(), INTERVAL 2 DAY) AND foglalta='foglaljorvost'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reservations as $reservation) {
            if (!NotificationService::hasNotification("missingdata", $reservation["id"])) {
                echo $reservation["datum"] . " " . $reservation["nev"] . " " . $reservation["email"] . "\n";
                $notificationService->sendMissingDataEmail($reservation["id"]);
            }
        }
    }

    private function checkOneWebPage() {
        if ($data = sql_query("SELECT id, domain FROM webpagedata d WHERE d.checkdate<DATE_SUB(NOW(), INTERVAL 1 WEEK) AND INSTR(domain, 'www.') LIMIT 1")->fetch(PDO::FETCH_ASSOC)) {

            $status = "not found";
            $page = file_get_contents("http://".idn_to_ascii($data["domain"]));
            if (substr_count($page, "<title>")) {
                $status = "found";
            }
            if (substr_count($page, "Joomla!")) {
                $status = "joomla";
            }
            if (substr_count($page, "HMM SubPage Engine")) {
                $status = "ok";
            }

            sql_query("update webpagedata set checkresult=?, checkdate=now() where id=?", [$status, $data["id"]]);
        }
    }

}