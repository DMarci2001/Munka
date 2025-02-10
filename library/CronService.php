<?php


class CronService {

    private $interval = null;
    private $utils;
    private $bookingService;

    private $smsWarningEmails = ["jnsmobil@gmail.com", "Luncz.brigitta@hungariamed.hu", "lazar.gabriella@hungariamed.hu", "marton.gergely@hungariamed.hu"];
    private $smsWarningLimit  = 20000;

    const HMM_ESZTERGOM_HELYSZINID = 532;

    public function __construct()
    {
        if (isset($_GET["interval"])) {
            $this->interval = $_GET["interval"];
        } else {
            $this->interval = "perc";
        }
        $this->utils = new Utils();
        $this->bookingService = new BookingService();

        if (isset($_GET["action"])) {
            $this->processActions();
            die;
        }


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
            $this->sendLabShopMails();
            $this->checkOneWebPage();
            $this->refreshWorklist();
            $this->deleteExpiredReservations();
            //$this->dokirexUserIdFill();

			$dicomService = new DicomService();
			$dicomService->processEntries();

			$foService = new FoglaljOrvostService();
			$foService->retryFailedMessages();

            //if (Booking_Constants::SQL_DB == "hungariamed") {
            $spektrumLabService = new SpektrumlabService();
            $spektrumLabService->getReceivedAnswer();
            //$spektrumLabService->fillMissingMessageRequestIds();
            $spektrumLabService->processPdfFromMessages();

            $laborKeroService = new LaborKeroService();
            $laborKeroService->storeLaborKeroFromLabShopData();

            if (Booking_Constants::SQL_DB == "hungariamed") {
                //synlab feldolgozás
                $service = new SynlabService();
                $service->synlabProcess();
                $service->processPdfFromMessages();
            }

        }

        if ($this->interval == "1ora") {
            //óránként futó cronok
            $this->_sendReservationReportForDoctors();
            //$this->sendReviewMails();
            $this->_sendAlkExcel(); //** régi excel hívást használ, disabled
            $this->_sendAlkExpire();
            $this->suzukiNotificationCheck();
            $this->scanLaborPDF();

            $this->checkSzabadsagCollisions();
            $this->checkCollisions();
            $this->seemeBalanceCheck();
            $this->sendManagerStatusEmail();

            //$laborKeroService = new LaborKeroService();
            //$laborKeroService->storeLaborKeroFromLabShopData();

            //$spektrumLabService = new SpektrumlabService();
            //$spektrumLabService->sendAutomaticRequests();

            $service = new SynlabService();
            $service->downloadSynlabEmails();

            if (Booking_Constants::SQL_DB == "hungariamed") {
                $this->getServerData();
            }
        }

        if ($this->interval == "napi") {
            //napi cronok
            //$this->dokirexPaciensDump();
            $this->readEmailReports();
        }

        if ($this->interval == "teszt") {
            $this->_tesztStuff();
        }

        if ($this->interval == "audimail") {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);

            require "AudiMail.php";

            $service = new AudiMail();
            $service->send();

            echo "audidone\n";
            die;
        }

        if ($this->interval == "abi_upload") {
            $this->_abiUpload();
        }
		
		if ($this->interval == "ertesito_teszt") {
			$this->reservationReminder();
		}

    }

    private function _tesztStuff() {
        //$this->saveResultPdfs();


        $service = new NotificationService();
        $service->xmasCampaign2024();

        //$this->sendReviewMails();

        /*

        <?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="HUNGARIA_MED_M"
                    MESSAGETYPE="CONSULTATION"
                    ACTION="MOD"
                    ROTATE_HASH="2c3e3e2db23fed868be9ac35c6cf59f5" />
                <DOCTOR
                    OWN_ID="1052"
                    OUTERSYS_ID="13699" />
                <CONSULTATION
                    OWN_ID="34573"
                    OUTERSYS_ID="14104892"
                    WEEK="2"
                    STARTDATETIME="2024-11-18 08:30:00"
                    STOPDATETIME="2024-11-18 13:30:00" />
            </MESSAGE>
        */


        //$foService = new FoglaljOrvostService();
        //$result = $foService->deleteConsultationFix(34573, 14104892, 1052, 13699, "2024-11-18");
        //echo $result;
        //die("itt2");

        //$service = new SpektrumlabService();
        //$service->processPdfFromMessages();

        //$dicomService = new DicomService();
        //$dicomService->processEntries();

        //$this->_smsAlertBeforeReservation();
        //$this->seemeBalanceCheck();

        //$this->_sendAlkExcel();
        //$this->utils->sendSMS("06209996183","időpont foglalása van: 11:30 Győr Rákóczi Ferenc utca 44. Az üzemorvostól kapott beutaló nyomtatványt hozza magával!");
        //$this->sendSzabadsag2FoglaljOrvostBatch();
        //$this->checkSzabadsagCollisions();
        //$this->checkCollisions();
        //$this->dokirexUserIdFill();
        //$this->dokirexPaciensDump();

        //$docAgent = new DocAgent();
        //$docAgent->storeLaborLeletek();

        //$this->scanLaborPDF();
        //$this->fillLabMessageDatas();

        //$this->readEmailReports();

        //$this->refreshWorklist();
        //$this->sendManagerStatusEmail();

        //$service = new SynlabService();
        //$service->pdfTeszt();


        //$laborKeroService = new LaborKeroService();
        //$laborKeroService->storeLaborKeroFromLabShopData();

        //$spektrumLabService = new SpektrumlabService();
        //$spektrumLabService->sendAutomaticRequests();

        //$this->addSyncReservations();

        //$service = new SynlabService();
        //$service->synlabProcess();

        //$service = new SynlabService();
        //$service->downloadSynlabEmails();

        //echo $result."\n";

        //$this->getServerData();

        echo "teszt2\n";
        die();
    }

    private function fillLabMessageDatas() {
        $orders = sql_query("SELECT c.`reservation_id`, v.name, b.* FROM banktransactions b 
            LEFT JOIN labshop_vasarlasok v ON v.`bankorderid`=b.`orderid`
            LEFT JOIN cart_item c ON c.session_id=v.id
            WHERE INSTR(ack, 'labshop') AND result='finished' AND c.`reservation_id` IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orders as $order) {
            echo "{$order["reservation_id"]} {$order["orderid"]}\n";
            sql_query("update foglalasok set bankorderid=? where id=?", array($order["orderid"], $order["reservation_id"]));
        }

        die;


        $messages = sql_query("select * from labrequestmessages m where m.tipus='out' and taj='' order by datum desc limit 1000")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($messages as $message) {
            $messageRows = explode("\n", $message["content"]);
            foreach ($messageRows as $messageRow) {
                if (substr($messageRow, 0, 3) == "PID") {
                    $fields = explode("|", $messageRow);

                    $pid = $fields[3];
                    $szulido = $fields[7];
                    $taj = $fields[19];

                    sql_query("update labrequestmessages set pid=?, taj=?, szuldatum=? where id=?", [$pid, $taj, $szulido, $message["id"]]);

                    echo "{$pid} {$szulido} {$taj}\n";
                }
            }
        }
    }

    private function scanLaborPDF() {
        $docAgent = new DocAgent();

        $requests = sql_query("select id, nev from labrequests where resultdate>date_sub(now(), interval 30 day) and status='done' and scanresult='' order by resultdate desc")->fetchAll(PDO::FETCH_ASSOC) ;
        foreach ($requests as $request) {
            echo "{$request["nev"]} ";

            $fileName = Booking_Constants::DOCUMENT_PATH . "/scanteszt.pdf";
            file_put_contents($fileName, $docAgent->getDocByType(DocAgent::ASSET_LABOR_RESULT, $request["id"]));

            $config = new \Smalot\PdfParser\Config();
            $config->setHorizontalOffset('');
            $parser = new \Smalot\PdfParser\Parser([], $config);
            $pdf = $parser->parseFile($fileName);
            $text = $pdf->getText();

            $scanItems = [];
            if (substr_count(strtolower($text),"hemolitikus")) {
                $scanItems[] = "hemolitikus";
            }

            if (substr_count(strtolower($text),"lipémiás")) {
                $scanItems[] = "lipemias";
            }

            if (substr_count(strtolower($text),"alvadékos")) {
                $scanItems[] = "alvadekos";
            }

            if (substr_count(strtolower($text),"kevés minta")) {
                $scanItems[] = "kevesminta";
            }

            sql_query("update labrequests set scanresult=? where id=?", [json_encode($scanItems), $request["id"]]);
            echo json_encode($scanItems);

            echo "\n";
        }
    }

    private function addSyncReservations() {
        $api = new BookingSyncApi();
        $reservations = sql_query("SELECT * FROM foglalasok WHERE szurestipusid='102' AND datum>NOW()")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reservations as $reservation) {
            echo $reservation["nev"]."\n";
            $api->modifyReservation($reservation["id"]);
        }
        die("done\n");
    }

    private function readEmailReports() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $validSenders["keltexmed"] = ["ugyfelkapcsolat@keltexmed.hu"];
        $validSenders["hungariamed"] = ["ugyfelkapcsolat@hungariamed.hu", "jnsmobil@gmail.com"];

        $connection = imap_open('{mail.hungariamed.hu/notls}', "reports@hungariamed.hu", "zeetao9U");

        $count = imap_num_msg($connection);

        echo "count:" . $count . "\n";

        for ($i = 0; $i <= 50; $i++) {
            //$msgNum = $i + 1;
            $msgNum = $count - $i;
            if ($msgNum <= 0) {
                break;
            }

            $header = imap_headerinfo($connection, $msgNum);

            //print_r($header);

            $raw_body = imap_body($connection, $msgNum);
            $subject = $this->rawDecode($header->subject);
            $from = $header->from[0]->mailbox."@".$header->from[0]->host;
            $structure = imap_fetchstructure($connection, $msgNum);

            //print_r($header->message_id);die;
            //echo "from: {$from}, subject:{$subject}\n";
            $a = 0;
            foreach ($structure->parts as $part) {
                if ($part->ifdparameters) {
                    foreach ($part->dparameters as $object) {
                        //echo "{$i} attr:{$from} ".strtolower($object->attribute)."\n";
                        if (substr_count(strtolower($object->attribute), "filename")) {
                            //attachment
                            $extension = "xls";
                            $fileName  =  $object->value;
                            $encoding  =  strtolower($part->encoding);
                            $subtype   =  strtolower($part->subtype);

                            echo "{$i} attr:{$from} ".$subtype." {$fileName}\n";

                            if (substr_count($fileName, ".xls") == 0) {
                                continue;
                            }

                            $tempFile = Booking_Constants::DOCUMENT_PATH.md5(rand(1,100000)).".{$extension}";

                            $attachment = imap_fetchbody($connection, $msgNum, $a+1);
                            if ($encoding == 3) {
                                $attachment = base64_decode($attachment);
                            }
                            if ($encoding == 4) {
                                $attachment = quoted_printable_decode($attachment);
                            }

                            if (in_array($from, $validSenders[Booking_Constants::SQL_DB])) {

                                if (sql_query("select id from webservicelog where action='processfile' and tipus=? and keres=? and datum>date_sub(now(), interval 1 month)", [Log::REPORTPROCESS_ID, $header->message_id])->fetch(PDO::FETCH_ASSOC)) {
                                    //continue;
                                }

                                //ez egy dokirex által küldött file, megpróbáljuk feldolgozni...
                                file_put_contents($tempFile, $attachment);

                                $requestText = "Dokirex excel file feldolgozás\nFeladó: {$from}\nTárgy: {$subject}\nCsatolt file: ".$this->rawDecode($fileName)."\n";

                                $logId = Log::store(Log::REPORTPROCESS_ID, "processfile", $header->message_id, "");

                                echo $tempFile." ".$this->rawDecode($fileName)."\n";

                                $dailyStatService = new DailyStatService();
                                $result = $dailyStatService->processFileXlsFromEmail($tempFile);

                                echo "result:{$result}\n";

                            }


                        }
                    }
                }
                $a++;
            }

            //die;
            //print_r($header->from[0]->host);die;
        }
        die("done\n");

    }


    private function seemeBalanceCheck() {
        if (Booking_Constants::SQL_DB == "hungariamed" && in_array(date("G"), [8, 14])) {
            $result = json_decode(file_get_contents("https://seeme.hu/gateway?key=" . Booking_Constants::SEEME_API_KEY . "&method=balance&format=json"), JSON_OBJECT_AS_ARRAY);

            if (isset($result["balance"])) {
                $balance = round($result["balance"]);
                if ($balance < $this->smsWarningLimit) {
                    $mail = NotificationService::getDefaultMailer();
                    foreach ($this->smsWarningEmails as $email) {
                        $mail->addAddress($email);
                    }
                    $mail->Subject = "Seeme egyenleg: {$balance} {$result["currency"]}";
                    $mail->Body = "Ez egy rendszerüzenet<br/>Töltsd fel a seeme egyeleget!";
                    $mail->send();
                }
            }
        }
    }

    /**
     * Suzuki statisztika kiküldése minden nap 19:00-kor.
    */
    private function suzukiNotificationCheck(){
        if (Booking_Constants::SQL_DB == "hungariamed" && in_array(date("G"), [19])) {
            $notificationService = new NotificationService();
            $notificationService->suzukiManagerNotificationList();
        }
    }

    private function dokirexPaciensDump() {
        $dokirexService = new DokirexService();
        $dokirexService->dokirexListPaciensInsertLoop();
    }

    private function dokirexUserIdFill() {
        $dokirexService = new DokirexService();
        //$result = $dokirexService->listFelhasznaloSzakrendeles();
        //echo json_encode(json_decode($result, JSON_OBJECT_AS_ARRAY), JSON_PRETTY_PRINT);echo "\n";die;

        $talalat = $nemtalalat = 0;
        $reservations = sql_query("SELECT * FROM foglalasok WHERE datum>DATE_SUB(NOW(), INTERVAL 2 WEEK) AND nev<>'nincs név' AND taj<>'' AND helyszinid=1 AND (dokirex_userid=0 or dokirex_userid<0) limit 100000")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservations as $reservation) {
            echo "user: {$reservation["szuldatum"]} {$reservation["taj"]} {$reservation["nev"]}\n";

            $params = $dokirexService->getUserParamsFromReservation($reservation["id"]);
            $error = $dokirexService->checkUserParamErrors($params);

            if (empty($error)) {
                $response = $dokirexService->insertPaciensIntoDokirex($params);
                print_r($response);
                //break;
            } else {
                print_r($error);
            }
            echo "\n";

            echo "secondary check..\n";
            if (sql_query("select * from foglalasok where id=? and dokirex_userid<=0", [$reservation["id"]])->fetch(PDO::FETCH_ASSOC)) {
                $paciensDatas = sql_query("select * from dokirex_allomany where Azonosito=?", [trim($reservation["taj"])])->fetchAll(PDO::FETCH_ASSOC);
                if (count($paciensDatas) == 1) {
                    foreach ($paciensDatas as $paciensData) {
                        echo $paciensData["Nev"] . " ";
                        //if ($paciensData["Nev"] == $reservation["nev"]) {
                            sql_query("update foglalasok set dokirex_userid=? where id=? limit 1", [$paciensData["PaciensID"], $reservation["id"]]);
                            echo "találat\n";
                            $talalat++;
                        //} else {
                        //    echo "-\n";
                        //}
                    }
                }
            }

            $nemtalalat++;

        }
        echo $talalat." ".$nemtalalat."\n";

        /*
        $logs = sql_query("SELECT l.* FROM dokirexvizsglaplog l
            LEFT JOIN foglalasok f ON f.`dokirex_userid`=l.`PaciensID`
            WHERE DATE(l.Datum)='2022-06-30' AND f.id IS NULL
            GROUP BY l.`PaciensID`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($logs as $log) {
            echo "userid: ".$log["PaciensID"]."\n";
            $data = $dokirexService->getPaciensByID($log["PaciensID"]);
            print_r($data);
            break;
        }
        */
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
            $GLOBALS["extraloginfo"] = "automatikus törlés - nem aktivált foglalás";
            $this->bookingService->deleteReservation($row["id"], $row["rkod"], true);
        }
    }

    public function _smsAlertBeforeReservation() {
        //sms értesítés a foglalás előtt (kivéve hmm esztergom!)
        if (!in_array(date("G"),array(22,23,0,1,2,3,4,5))) {
            $res = sql_query("SELECT f.*,h.cim FROM foglalasok f 
            LEFT JOIN helyszinek h ON h.id=f.helyszinid
		    LEFT JOIN cegek c ON c.`id`=f.`cegid`
		    WHERE datum>NOW() AND datum<DATE_ADD(NOW(),INTERVAL c.smshour hour) AND f.telefon<>'' AND f.aktiv=1 AND smssent=0 and f.parentid=0 AND c.smshour<>0 AND f.externalid='' and f.helyszinid<>?", [self::HMM_ESZTERGOM_HELYSZINID]);
            while ($row = sql_fetch_array($res)) {
                $tel = $row["telefon"];

                //kiegészítő vizsgálatokról nem kell sms
                if (in_array($row["szurestipusid"], [Booking_Constants::TUDOSZURES_ID, Booking_Constants::LABOR_ID, Booking_Constants::HALLASVIZSGALAT_ID, Booking_Constants::COVID_ID])) {
                    continue;
                }

                //ha aznap kapott már sms-t, ne menjen ki több
                $skip = sql_query("select id from foglalasok where telefon=? and datum>? and datum<? and smssent=1 limit 1",  [$tel, date("Y-m-d 00:00:00", strtotime($row["datum"])), date("Y-m-d 23:59:59", strtotime($row["datum"]))])->fetch(PDO::FETCH_ASSOC);
                sql_query("update foglalasok set smssent=1 where id='{$row["id"]}'");
                if ($skip) {
                    continue;
                }

                if (!empty($row["jarat"])) {
                    $row["datum"] = substr($row["datum"], 0, 10)." ".$row["jarat"];
                }

                $szoveg = Booking_Constants::COMPANY_NAME_SHORT." időpont foglalása van: ".date("Y.m.d H:i", strtotime($row["datum"]))." {$row["cim"]}";
                if ($row["rlang"] == "en") {
                    $szoveg = Booking_Constants::COMPANY_NAME_SHORT.": You have an appointment - ".substr($row["datum"],11,5)." {$row["cim"]}";
                }
                if ($row["rlang"] == "de") {
                    $szoveg = Booking_Constants::COMPANY_NAME_SHORT.": You have an appointment - ".substr($row["datum"],11,5)." {$row["cim"]}";
                }

                if (in_array(substr($tel,0,2),array("06","36"))) {
                    $this->utils->sendSMS($tel,$szoveg);
                    echo "sms sent to: {$tel}\n";
                }
                //mail("jns@jns.hu",$szoveg,"");
            }
        }

        //24 órával előtte értesítés esztergomnak
        if (Booking_Constants::SQL_DB == "hungariamed") {
            $res = sql_query("SELECT f.id, datum, helyszinid, szurestipusid, f.cegid, nev, email, telefon, h.cim FROM foglalasok f LEFT JOIN helyszinek h ON h.id=f.helyszinid WHERE helyszinid=? AND f.smssent=0 AND telefon<>'' AND datum>DATE_ADD(NOW(), INTERVAL 23 HOUR) AND datum<DATE_ADD(NOW(), INTERVAL 24 HOUR) AND f.aktiv=1 AND parentid=0 AND externalid='' ORDER BY datum", [self::HMM_ESZTERGOM_HELYSZINID]);
            while ($row = sql_fetch_array($res)) {
                $tel = $row["telefon"];

                //kiegészítő vizsgálatokról nem kell sms
                if (in_array($row["szurestipusid"], [Booking_Constants::TUDOSZURES_ID, Booking_Constants::LABOR_ID, Booking_Constants::HALLASVIZSGALAT_ID, Booking_Constants::COVID_ID])) {
                    continue;
                }

                sql_query("update foglalasok set smssent=1 where id='{$row["id"]}'");

                $szoveg = Booking_Constants::COMPANY_NAME_SHORT . " időpont foglalása van 24 óra múlva: " . substr($row["datum"], 11, 5) . " {$row["cim"]}";

                //if (in_array(substr($tel, 0, 2), ["00", "06", "36"])) {
                    $this->utils->sendSMS($tel, $szoveg);
                    echo "sms sent to: {$tel}\n";
                //}
                //mail("jnsmobil@gmail.com","{$tel} 24 óra - ".$szoveg,"");
            }
        }

        //7 órakor minden aznapinak sms
        if (Booking_Constants::SQL_DB == "hungariamed" && date("G") == 7) {
            $nap = date("Y-m-d");
            $res = sql_query("SELECT f.id, datum, helyszinid, szurestipusid, f.cegid, nev, email, telefon, h.cim FROM foglalasok f LEFT JOIN helyszinek h ON h.id=f.helyszinid WHERE helyszinid=? AND smssent IN (0, 1) AND telefon<>'' AND datum>'{$nap} 00:00:00' AND datum<'{$nap} 23:59:59' AND f.aktiv=1 AND parentid=0 AND externalid='' ORDER BY datum", [self::HMM_ESZTERGOM_HELYSZINID]);
            while ($row = sql_fetch_array($res)) {
                $tel = $row["telefon"];

                //kiegészítő vizsgálatokról nem kell sms
                if (in_array($row["szurestipusid"], [Booking_Constants::TUDOSZURES_ID, Booking_Constants::LABOR_ID, Booking_Constants::HALLASVIZSGALAT_ID, Booking_Constants::COVID_ID])) {
                    continue;
                }

                sql_query("update foglalasok set smssent=2 where id='{$row["id"]}'");

                $szoveg = Booking_Constants::COMPANY_NAME_SHORT . " a mai napon időpont foglalása van: " . substr($row["datum"], 11, 5) . " {$row["cim"]}";

                //if (in_array(substr($tel, 0, 2), ["00", "06", "36"])) {
                    $this->utils->sendSMS($tel, $szoveg);
                    echo "sms sent to: {$tel}\n";
                    //mail("jnsmobil@gmail.com","{$tel} 7 óra - ".$szoveg,"");
                //}
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
        if ($data = sql_query("SELECT id, domain FROM webpagedata d WHERE d.checkdate<DATE_SUB(NOW(), INTERVAL 7 day) AND INSTR(domain, 'www.') LIMIT 1")->fetch(PDO::FETCH_ASSOC)) {

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

    private function sendLabShopMails() {
        if (Booking_Constants::SQL_DB == "hungariamed") {
            $notificationService = new NotificationService();

            $labShopDatas = sql_query("select * from labshop_vasarlasok where date>date_sub(now(), interval 1 month) and visszaigazolva=0")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($labShopDatas as $labShopData) {
                $notificationService->sendLabShopMail($labShopData);
                sql_query("update labshop_vasarlasok set visszaigazolva=1 where id=?", [$labShopData["id"]]);
            }
        }
    }

    private function rawDecode($str) {
        $arrStr = explode('?', $str);

        if (isset($arrStr[1]) && in_array($arrStr[1], mb_list_encodings())) {
            switch ($arrStr[2]) {
                case 'B': //base64 encoded
                    $str = base64_decode($arrStr[3]);
                    break;
                case 'Q': //quoted printable encoded
                    $str = quoted_printable_decode($arrStr[3]);
                    break;
            }

            $str = iconv($arrStr[1], 'UTF-8', $str);
        }

        return $str;
    }


    private function refreshWorklist() {
        $dicomService = new DicomService();
        $tempWorkListFileName = DicomService::WORKLIST_DIR."/".Booking_Constants::SQL_DB."_worklist.tmp";
        $dicomFiles = [];

        $reservations = sql_query("SELECT f.id, f.datum, f.nev, f.taj, f.szuldatum, f.neme, c.megnev AS cegnev FROM foglalasok f 
            LEFT JOIN cegek c ON c.id = f.cegid
            WHERE f.`szurestipusid`=? AND datum>DATE_SUB(NOW(), INTERVAL 1 DAY) AND DATE(datum)<=DATE(NOW()) AND f.taj<>'' AND DATE(f.szuldatum)>DATE(DATE_SUB(NOW(), INTERVAL 100 YEAR)) AND DATE(f.szuldatum)<=DATE(NOW())", [Booking_Constants::TUDOSZURES_ID]);

        foreach ($reservations as $reservation) {
            $dicomFileName = DicomService::WORKLIST_DIR."/".Booking_Constants::SQL_DB."_".$reservation["id"].".wl";
            $dicomFiles[] = $dicomFileName;
            echo "{$reservation["nev"]}\n";
            $worklist = $dicomService->workListFileFormat($reservation);
            file_put_contents($tempWorkListFileName, $worklist);

            $output = `dump2dcm {$tempWorkListFileName} {$dicomFileName}`;
        }
        unlink($tempWorkListFileName);

        //régi fájlok törlése
        $d = dir(DicomService::WORKLIST_DIR);
        while (false !== ($entry = $d->read())) {
            if (substr_count($entry, Booking_Constants::SQL_DB)) {
                echo $entry . "\n";
                $dicomFileName = DicomService::WORKLIST_DIR."/".$entry;

                if (!in_array($dicomFileName, $dicomFiles)) {
                    unlink($dicomFileName);
                }
            }
        }

    }

    private function sendManagerStatusEmail() {
        if (Booking_Constants::SQL_DB == "hungariamed" && in_array(date("G"), [8]) && !in_array(date("N"), [6,7])) {
            $notificationService = new NotificationService();
            $notificationService->sendManagerStatusMail();
        }
    }

    /**
     * Lejáratos foglalások törlése a foglalasok táblálból.
     * @param   datetime    $expire A foglalasok táblában az "expire" oszlop értéke alapján törlöm ki a foglalásokat.
    */
    private function deleteExpiredReservations()
    {   
        if($reservations = sql_query("SELECT * FROM foglalasok WHERE expire < NOW() AND expire <> '0000-00-00 00:00:00' AND datum > NOW() and foglalta='labshop'")->fetchAll(PDO::FETCH_ASSOC)) {
            $bookingService = new BookingService();
            foreach($reservations as $reservationData){
                $GLOBALS["extraloginfo"] = "lejárt foglalás automatikus törlése - cron";
                $this->bookingService->deleteReservation($reservationData["id"],$reservationData["pass"]);
            }
        }

        return "Finished process.";
    }

    private function getServerData() {
        $serverData["mail"] = [
            "name" => "Levelező szerver",
            "hdd" => `ssh root@mail.hungariamed.hu 'df -h'`,
            "proc" => `ssh root@mail.hungariamed.hu 'w'`,
        ];
        $serverData["backup"] = [
            "name" => "Backup szerver",
            "hdd" => `ssh -p 2223 root@81.183.233.8 'df -h'`,
            "proc" => `ssh -p 2223 root@81.183.233.8 'w'`,
        ];
        $serverData["bejelentkezo"] = [
            "name" => "Bejelentkező szerver",
            "hdd" => `df -h`,
            "proc" => `w`,
        ];

        $this->checkServerData($serverData);

        sql_query("insert into sitedata set datum=now(), tipus='serverdata', valuetext=?", [json_encode($serverData, JSON_PRETTY_PRINT)]);
        //echo "serverdata{$result}";
    }

    private function checkServerData($data){
        $tels = ["06303668412","06209162276","06209996183"];
        foreach($data as $index=>$server){
            if(empty($server["hdd"]) || empty($server["proc"])){
                $szoveg = "A {$server["name"]} nem elérhető! A pontos idő: ".date("Y.m.d H:i:s");
                foreach($tels as $tel){
                    $this->utils->sendSMS($tel,$szoveg);
                }
            }
        }
    }

    private function sendReviewMails():void {
        //érkeztetett foglalásokra elégedettségi form kiküldése

        $service = new NotificationService();
        if (Booking_Constants::SQL_DB == "hungariamed") {
            $reservations = sql_query("SELECT * FROM foglalasok WHERE eljott=1 and helyszinid=? AND datum>DATE_SUB(NOW(), INTERVAL 20 DAY) AND datum<DATE_SUB(NOW(), INTERVAL 1 DAY) AND email<>'' limit 100", [Booking_Constants::DEFAULT_PLACE_IDS[0]])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($reservations as $reservation) {
                $service->sendReviewMail($reservation);
            }
        }

    }


    private function processActions() {
        if ($_GET["action"] == "syncnewreservation") {
            $service = new BookingSyncApi();
            $service->newReservation($_GET["id"], true);
        }
        if ($_GET["action"] == "syncmodifyreservation") {
            $service = new BookingSyncApi();
            $service->modifyReservation($_GET["id"], true);
        }
    }

    private function saveResultPdfs() {
        $service = new DocAgent();

        $results = sql_query("SELECT c.megnev AS ceg, r.* FROM labrequests r 
            LEFT JOIN foglalasok f ON f.id=r.foglalasid
            LEFT JOIN cegek c ON c.id=f.cegid
            WHERE provider='spektrumlab' AND r.status='done' AND INSTR(c.megnev, 'auchan') AND YEAR(r.created)=2024
            ORDER BY created DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $result) {
            echo "{$result["id"]} {$result["nev"]}\n";

            $pdf = $service->getDocByType("laborresult", $result["id"]);

            file_put_contents("/var/www/marci/keltexmed_auchan_pdfek/".date("Y-m-d_", strtotime($result["created"]))."{$result["id"]}.pdf", $pdf);
        }


    }

}