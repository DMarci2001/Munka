<?php


class FoglaljOrvostService extends FoGeneral {
    const API_URL       = "https://foglaljorvost.hu/dokucomms";
    const API_TEST_URL  = "https://test.foglaljorvost.hu/dokucomms";
    const PROVIDER_NAME = "foglaljorvost";
    const LOG_ID        = 11;

    private int $placeId = 0;
    private string $currentAction;

    public function __construct()
    {
        $this->currentAction = "";
    }

    public function processTestInput() {
        if (isset($_GET["tesztaction"])) {
            $action = $_GET["tesztaction"];

            if ($action == "ping") {
                $result = $this->sendPing();
            }
            if ($action == "orvos") {
                $result = $this->sendDoctor(71);
            }
            if ($action == "getallfields") {
                $result = $this->getAllFields();
            }
            if ($action == "getfieldsbyclinic") {
                $result = $this->getFieldsByClinic();
            }
            if ($action == "getfieldsbydoctor") {
                $result = $this->getFieldsByDoctor(71);
            }
            if ($action == "newreservation") {
                $result = $this->newReservation(119440);
            }
            if ($action == "newconsultation") {
                $result = $this->newReservation(3123);
            }
        }
        if (isset($result)) {
            print_r($result);
            die;
        }
    }


    public array $bercsenyiDoctorIds = [461, 454, 468, 458, 480, 479];
    //463 Dr. Mauks kiszedve, 489 Dr Tímár is

    private function setPlaceByDoctorId($doctorId):void {
        //bercsényi előkészítése
        if (Booking_Constants::SQL_DB == "keltexmed" && in_array($doctorId, $this->bercsenyiDoctorIds)) {
            $this->placeId = Booking_Constants::DEFAULT_PLACE_IDS[1];
        }
    }

    private function setPlaceByReservationId($reservationId):void {
        //bercsényi előkészítése
        if (Booking_Constants::SQL_DB == "keltexmed") {
            if ($reservationData = sql_query("select helyszinid from foglalasok where id=?", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
                $this->placeId = $reservationData["helyszinid"];
            }
        }
    }

    /*
    Előjegyzés adatainak közlése
    Amennyiben a Partner klinikai rendszerében egy előjegyzést hoznak létre, akkor a klinikai rendszer egy “APPOINTMENT” üzenetet küld a FO rendszer felé. A FO rendszere az üzenetben található időpontot foglaltként tudja regisztrálni.
    A MSGINFO/ACTION csak NEW, MOD, vagy DEL lehet.
    A MSGINFO/ACTION szerepei:
    ●	NEW: új előjegyzés
    ●	MOD: meglévő előjegyzés adatainak megváltozása
    ●	DEL: előjegyzés törlése/lemondása

    A DOCTOR tag tartalmazza az orvos azonosítóit, amely orvoshoz az előjegyzés kapcsolódik.
    Az APPOINTMENT tag tartalmazza az előjegyzés adatait.
    Ezek az attribútumok a következők:
    ●	OWN_ID: Az előjegyzés azonosítója a klinikai rendszerben.
    ●	OUTERSYS_ID: Az előjegyzés azonosítója a FO rendszerében. NEW művelet esetén ez az adat üres, MOD és DEL esetén kitöltött.
    ●	APPOINTMENT: Az előjegyzés időpontja “ÉÉÉÉ-HH-NN ÓÓ:PP:MM” formátumban. Kötelező.
    ●	STATUS: Az előjegyzés státusza. Kötelező. A státuszjelzők a következők lehetnek:
    ○	“E”: sima előjegyzés, foglalás, egyéb elfoglaltság, szabadság
    ○	“J”: jelen, megjelent. A beteg megjelent a klinikán.
    ○	“N”: nem jött, nem jelent meg előzetes lemondás nélkül.
    ○	“L”: lemondta. A beteg lemondta az előjegyzését.
    ●	APPOINTMENT_LONG: Foglalás időtartama.  Kötelező
    ●	DESCRIPTION: Szöveges megjegyzés. Nem kötelező.
    */

    public function newReservation($fid) {
        $this->setPlaceByReservationId($fid);
        $this->currentAction = "APPOINTMENT_NEW";
        $results = [];
        $res = sql_query("select f.*,o.foid as orvosfoid from foglalasok f left join orvosok o on o.id=f.orvosassigned where (f.id=? or f.parentid=?) and o.foid<>0", [$fid, $fid]);
        while ($reservationData = sql_fetch_array($res)) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="APPOINTMENT"
                    ACTION="NEW"
                    ROTATE_HASH="#rotatehash#" />
                <DOCTOR
                    OWN_ID="'.$reservationData["orvosassigned"].'"
                    OUTERSYS_ID="'.$reservationData["orvosfoid"].'" />
                <APPOINTMENT
                    OWN_ID="'.$reservationData["id"].'"
                    OUTERSYS_ID="0"
                    APPOINTMENT="'.$reservationData["datum"].'"
                    STATUS="'.$this->getReservationStatus($reservationData).'"
                    APPOINTMENT_LONG="'.$reservationData["rinterval"].'"
                    DESCRIPTION="'.$this->description($reservationData).'" />
            </MESSAGE>';

            $result = $this->sendMessageToFoglaljOrvost($xml);

            $xml = simplexml_load_string($result);
            $message = (string)$xml->RETURN["RETMESSAGE"];
            if (ctype_digit($message)) {
                sql_query("update foglalasok set fofid=? where id=?", [$message, $reservationData["id"]]);
            }
            $results[] = $result;
        }
        return $results;
    }

    public function modifyReservation($fid) {
        $this->setPlaceByReservationId($fid);
        $this->currentAction = "APPOINTMENT_MOD";
        if ($reservationData = sql_fetch_array(sql_query("select f.*,o.foid as orvosfoid from foglalasok f left join orvosok o on o.id=f.orvosassigned where f.id=? and o.foid<>0 and f.fofid<>0", [$fid]))) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="APPOINTMENT"
                    ACTION="MOD"
                    ROTATE_HASH="#rotatehash#" />
                <DOCTOR
                    OWN_ID="'.$reservationData["orvosassigned"].'"
                    OUTERSYS_ID="'.$reservationData["orvosfoid"].'" />
                <APPOINTMENT
                    OWN_ID="'.$reservationData["id"].'"
                    OUTERSYS_ID="'.$reservationData["fofid"].'"
                    APPOINTMENT="'.$reservationData["datum"].'"
                    STATUS="'.$this->getReservationStatus($reservationData).'"
                    APPOINTMENT_LONG="'.$reservationData["rinterval"].'"
                    DESCRIPTION="'.$this->description($reservationData).'" />
            </MESSAGE>';
            return $this->sendMessageToFoglaljOrvost($xml);
        }
        return false;
    }

    public function deleteReservation($fid) {
        $this->setPlaceByReservationId($fid);
        $this->currentAction = "APPOINTMENT_DEL";
        if ($reservationData = sql_fetch_array(sql_query("select f.*,o.foid as orvosfoid from foglalasok f left join orvosok o on o.id=f.orvosassigned where f.id=? and o.foid<>0 and f.fofid<>0", [$fid]))) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="APPOINTMENT"
                    ACTION="DEL"
                    ROTATE_HASH="#rotatehash#" />
                <APPOINTMENT
                    OWN_ID="'.$reservationData["id"].'"
                    OUTERSYS_ID="'.$reservationData["fofid"].'" />
            </MESSAGE>';
            return $this->sendMessageToFoglaljOrvost($xml);
        }
        return false;
    }

    /*
    Szakterületek/szolgáltatások lekérdezése FO rendszeréből
    Háromféleképpen lehet lekérdezni a szakterületet/szolgáltatást a FO rendszeréből. A MSGINFO/ACTION csak GET lehet.
    Ezekre az üzenetekre a többi kéréstől eltérő formában válaszol a FO rendszere, melynek formája itt megtekinthető.
    A MSGINFO/MESSAGETYPE a következők lehetnek:
    ●	Minden szakterület/szolgáltatás (ALLFIELDS)
    ●	Klinikához rendelt szakterület/szolgáltatás (FIELDSBYCLINIC)
    ●	Adott orvoshoz rendelt szakterület/szoláltatás (FIELDSBYDOCTOR)
    */

    public function getAllFields() {
        $this->currentAction = "ALLFIELDS_GET";
        $xml='<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="ALLFIELDS"
                    ACTION="GET"
                    ROTATE_HASH="#rotatehash#" />
            </MESSAGE>';
        return $this->sendMessageToFoglaljOrvost($xml);
    }

    public function getFieldsByClinic() {
        $this->currentAction = "FIELDSBYCLINIC_GET";
        $xml='<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="FIELDSBYCLINIC"
                    ACTION="GET"
                    ROTATE_HASH="#rotatehash#" />
            </MESSAGE>';
        return $this->sendMessageToFoglaljOrvost($xml);
    }

    public function getFieldsByDoctor($oid) {
        $this->setPlaceByDoctorId($oid);
        $this->currentAction = "FIELDSBYDOCTOR_GET";
        if ($orvosData = sql_fetch_array(sql_query("select * from orvosok where id=?", [$oid]))) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="FIELDSBYDOCTOR"
                    ACTION="GET"
                    ROTATE_HASH="#rotatehash#" />
                <DOCTOR
                    OWN_ID="'.$orvosData["id"].'"
                    OUTERSYS_ID="'.$orvosData["foid"].'" />
            </MESSAGE>';
            return $this->sendMessageToFoglaljOrvost($xml);
        }
        return false;
    }

    /*
    Orvos FO azonosítójának lekérdezése
    Miután az FO adminisztrátora rögzítette az FO rendszerében a megfelelő klinika entitáshoz az orvost, beállította az orvoshoz tartozó szakterületeket és szolgáltatásokat. A Partner Klinikának első lépésben le kell kérdezni az FO rendszeréből az orvos FO rendszerben lévő azonosítóját, hogy később erre hivatkozva tudjon kéréseket beküldeni FO felé és fordítva. Az azonosító lekérdezéséhez klinikai rendszer egy “DOCTOR” üzenetet küld a FO rendszere felé.
    A MSGINFO/ACTION csak NEW értéket vehet fel. A DOCTOR tag tartalmazza az orvos adatait.
    Ezek az attribútumok a következők:
    ●	OWN_ID: Az orvos azonosítója a klinikai rendszerben.
    ●	OUTERSYS_ID: Az orvos azonosítója a FO rendszerében. NEW művelet esetén ez az adat üres
    ●	NAME: Az orvos neve.
    ●	SEAL_NUMBER: az orvos pecsétszáma. Ez alapján szinkronizálja össze az API az FO rendszerében lévő orvost. Maximum 32 karakter hosszúságú string lehet.
    */

    public function sendDoctor($oid) {
        $this->setPlaceByDoctorId($oid);
        $this->currentAction = "DOCTOR_NEW";
        if ($orvosData = sql_fetch_array(sql_query("select * from orvosok where id=?", [$oid]))) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="DOCTOR"
                    ACTION="NEW"
                    ROTATE_HASH="#rotatehash#" />
                <DOCTOR OWN_ID="' . $orvosData["id"] . '"
                    OUTERSYS_ID="0"
                    NAME="' . $orvosData["nev"] . '"
                    SEAL_NUMBER="' . $orvosData["pecsetszam"] . '" />
            </MESSAGE>';
            return $this->sendMessageToFoglaljOrvost($xml);
        }
        return false;
    }

    /*
    Életjel küldése
    Biztonság szempontjából a klinikarendszer küldhet ú.n. életjelet az FO rendszere felé, mellyel jelzi, hogy a rendszere fut és képes foglalásokat fogadni. Ez azért fontos, mert ha esetleg a klinika rendszere vagy a szerver leáll, akkor a két rendszer ne legyen aszinkronban.
    Életjelet kétféleképpen küldhet a klinikarendszer:
    1.	Bármilyen műveletet végez (Orvost vesz fel, módosít, rendelési időt regisztrál/módosít, stb.)
    2.	Óránként egyszer küld egy egyszerű parancsot, mellyel beregisztrálja a FO rendszerében az aktuális életjelét.
    Ezt az életjelet a FO rendszere óránként levizsgálja, és ha az elmúlt órában nem kapott ilyen jelet, akkor letiltja az adott klinika összes orvosánál a foglalási lehetőséget. Ezzel egy idejűleg kiküld egy e-mail értesítőt a klinika által meghatározott e-mail címekre.
    Amint a klinika rendszere ismét működésbe lép és küld életjelet, akkor a FO rendszere az órás vizsgálatnál ismét engedélyezi a foglalást a klinika orvosainál, illetve kiküld erről is e-mail értesítést. A FO órás ütemezése minden óra egészkor fut le, így érdemes a klinika rendszerben egy hasonló ütemezést beállítani minden óra 45 percére.
    */

    public function sendPing() {
        $this->currentAction = "HEARTBEAT_SEND";
        $xml='<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="HEARTBEAT"
                    ACTION="SEND"
                    ROTATE_HASH="#rotatehash#" />
            </MESSAGE>';
        return $this->sendMessageToFoglaljOrvost($xml);
    }

    /*
    Az üzenet tartalmazza a naptár részletet, melyben a rendelési napok és azon belül a rendelési idők kezdetét és végét adja át a FO rendszerének. Ezen kívül paraméterként átjön a ciklikusság értéke.
    A MSGINFO/ACTION csak NEW, MOD vagy DEL lehet.
    Az új szabad időpont és a meglévő szerkesztésénél ugyan azt a struktúra kell, egyedül a rendelési idő törlés műveltnél tér el a szerkezet.
    CONSULTATION részben vannak a szükséges adatok:
    ●	OWN_ID: A szabad időpont azonosító a klinikai rendszerben
    ●	OUTERSYS_ID: A szabad időpont azonosító a FO rendszerben
    ●	STARTDATETIME: kezdő időpont
    ●	STOPDATETIME: záró időpont (a dátum résznek ugyan annak kell lennie, mint kezdő időpontban)
    ●	THISWEEK: erre hétre menti az időpontot (MOD művelet esetén érdekes - 0 vagy 1)
    ●	WEEK: ismétlődés beállítása
    WEEK opciók:
    0.	nincs ismétlődés
    1.	minden héten
    2.	minden második héten
    3.	minden harmadik héten
    4. 	minden hónap X. napján (pl.: minden hónap 3. keddén)
    A nulladik opciót "csak erre a napra" esetén használható.

    A szabad időpont törlésének struktúrája hasonló.
    */

    public function newConsultation($beoId) {
        $beo = $this->getBeosztasData($beoId);
        $this->setPlaceByDoctorId($beo["orvosid"]);

        if (isset($beo["error"])) {
            return $beo["error"];
        }

        if ($beo["orvosid"] == 64) {
            //return "error: Dr. Csanády Kinga előjegyzés felküldése tiltva";
        }

        $this->currentAction = "CONSULTATION_NEW";
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="CONSULTATION"
                    ACTION="NEW"
                    ROTATE_HASH="#rotatehash#" />
                <DOCTOR
                    OWN_ID="'.$beo["orvosid"].'"
                    OUTERSYS_ID="'.$beo["foid"].'" />
                <CONSULTATION
                    OWN_ID="'.$beo["id"].'"
                    OUTERSYS_ID="0"
                    WEEK="'.$beo["week"].'"
                    STARTDATETIME="'.$beo["startTime"].'"
                    STOPDATETIME="'.$beo["endTime"].'" />
            </MESSAGE>';

        return $this->sendMessageToFoglaljOrvost($xml);
    }

    public function modifyConsultation($beoId) {
        $beo = $this->getBeosztasData($beoId);
        $this->setPlaceByDoctorId($beo["orvosid"]);

        if (isset($beo["error"])) {
            return $beo["error"];
        }

        $this->currentAction = "CONSULTATION_MOD";
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="CONSULTATION"
                    ACTION="MOD"
                    ROTATE_HASH="#rotatehash#" />
                <DOCTOR
                    OWN_ID="'.$beo["orvosid"].'"
                    OUTERSYS_ID="'.$beo["foid"].'" />
                <CONSULTATION
                    OWN_ID="'.$beo["id"].'"
                    OUTERSYS_ID="'.$beo["fobid"].'"
                    WEEK="'.$beo["week"].'"
                    STARTDATETIME="'.$beo["startTime"].'"
                    STOPDATETIME="'.$beo["endTime"].'" />
            </MESSAGE>';

        return $this->sendMessageToFoglaljOrvost($xml);
    }

    public function deleteConsultation($beoId) {
        $beo = $this->getBeosztasData($beoId);
        $this->setPlaceByDoctorId($beo["orvosid"]);

        if (isset($beo["error"])) {
            return $beo["error"];
        }

        $this->currentAction = "CONSULTATION_DEL";
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="CONSULTATION"
                    ACTION="DEL"
                    ROTATE_HASH="#rotatehash#" />
                <DOCTOR
                    OWN_ID="'.$beo["orvosid"].'"
                    OUTERSYS_ID="'.$beo["foid"].'" />
                <CONSULTATION
                    OWN_ID="'.$beo["id"].'"
                    OUTERSYS_ID="'.$beo["fobid"].'"
                    WEEK="'.$beo["week"].'"
                    STARTDATE="'.$beo["startDate"].'" />
            </MESSAGE>';
        return $this->sendMessageToFoglaljOrvost($xml);
    }

    public function deleteConsultationFix($beoId, $remoteId, $orvosId, $remoteOrvosId, $date) {
        $this->currentAction = "CONSULTATION_DEL";
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="CONSULTATION"
                    ACTION="DEL"
                    ROTATE_HASH="#rotatehash#" />
                <DOCTOR
                    OWN_ID="'.$orvosId.'"
                    OUTERSYS_ID="'.$remoteOrvosId.'" />
                <CONSULTATION
                    OWN_ID="'.$beoId.'"
                    OUTERSYS_ID="'.$remoteId.'"
                    WEEK="1"
                    STARTDATE="'.$date.'" />
            </MESSAGE>';
        return $this->sendMessageToFoglaljOrvost($xml);
    }

    private function sendMessageToFoglaljOrvost($xml, $logId = 0) {
        if (!Booking_Constants::FO_CONNECTION_ENABLED) {
            return false;
        }

        $ifc = $this->getIfcName($this->placeId);

        $xml = str_replace("#rotatehash#", $this->generateRotateHash($this->placeId), $xml);
        $xml = str_replace("#ifcname#", $ifc, $xml);

        $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "";
        $remoteAddr = $_SERVER["REMOTE_ADDR"] ?? "";
        if (empty($logId)) {
            sql_query("insert into webservicelog set tipus=?, datum=now(), keres=?, ip=?, useragent=?, action=?", array(self::LOG_ID, $xml, $remoteAddr, $userAgent, $this->currentAction));
            $logId = sql_insert_id();
        }

        try {
            $client = new SoapClient($this->getApiURL());
            $result = $client->EnqueueMessage($xml, $ifc);
            sql_query("update webservicelog set response=?, exception='' where id=?", [$result, $logId]);
            return $result;
        } catch (SoapFault $exception) {
            sql_query("update webservicelog set retrycount=retrycount+1, exception=? where id=?", [$exception->getMessage(), $logId]);
            return false;
        }
    }

    public function retryFailedMessages() {
        //$res = sql_query("select * from webservicelog l where l.exception<>'' and datum>date_sub(now(), interval 2 hour) and tipus=? limit 10", [self::LOG_ID]);
        //while ($data = sql_fetch_array($res)) {
        //    $this->sendMessageToFoglaljOrvost($data["keres"], $data["id"]);
        //}
    }

    public function sendSzabadsag($szabadsagGroupId = 0) {
        if (!Booking_Constants::FO_CONNECTION_ENABLED) {
            return false;
        }
        $results = [];
        $szabadsagGroupId = intval($szabadsagGroupId);
        $this->currentAction = "APPOINTMENT_NEW";

        $res = sql_query("SELECT sz.*,o.foid as orvosfoid FROM szabadsag sz
        LEFT JOIN orvosok o ON o.id = sz.`oid`
        WHERE o.`foid` <> 0 and sz.datumtol > date(now()) ".($szabadsagGroupId!=0?" and sz.groupid='{$szabadsagGroupId}'":"")." ORDER BY sz.datumig DESC");
        while ($szabadsagData = sql_fetch_array($res)) {
            $this->setPlaceByDoctorId($szabadsagData["oid"]);

            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="APPOINTMENT"
                    ACTION="NEW"
                    ROTATE_HASH="#rotatehash#" />
                <DOCTOR
                    OWN_ID="'.$szabadsagData["oid"].'"
                    OUTERSYS_ID="'.$szabadsagData["orvosfoid"].'" />
                <APPOINTMENT
                    OWN_ID="sz'.$szabadsagData["id"].'"
                    OUTERSYS_ID="0"
                    APPOINTMENT="'.$szabadsagData["datumtol"].' 00:00:00"
                    STATUS="E"
                    APPOINTMENT_LONG="1440"
                    DESCRIPTION="szabadság" />
            </MESSAGE>';

            $result = $this->sendMessageToFoglaljOrvost($xml);

            $xml = simplexml_load_string($result);
            $message = (string)$xml->RETURN["RETMESSAGE"];
            if (ctype_digit($message)) {
                sql_query("update szabadsag set foid=? where id=?", [$message, $szabadsagData["id"]]);
            }
            $results[] = $result;
        }
        return $results;
    }

    public function deleteSzabadsag($szabadsagGroupId) {
        if (!Booking_Constants::FO_CONNECTION_ENABLED) {
            return false;
        }

        $result = null;

        $this->currentAction = "APPOINTMENT_DEL";
        $res = sql_query("select * from szabadsag where groupid=? and foid<>0", [$szabadsagGroupId]);
        while ($szabadsagData = sql_fetch_array($res)) {
            $this->setPlaceByDoctorId($szabadsagData["oid"]);

            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="APPOINTMENT"
                    ACTION="DEL"
                    ROTATE_HASH="#rotatehash#" />
                <APPOINTMENT
                    OWN_ID="sz'.$szabadsagData["id"].'"
                    OUTERSYS_ID="'.$szabadsagData["foid"].'" />
            </MESSAGE>';
            $result = $this->sendMessageToFoglaljOrvost($xml);
        }
        return $result;
    }


    public function deleteSzabadsagFix() {
        $this->setPlaceByDoctorId(479);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="#ifcname#"
                    MESSAGETYPE="APPOINTMENT"
                    ACTION="DEL"
                    ROTATE_HASH="#rotatehash#" />
                <APPOINTMENT
                    OWN_ID="sz1429"
                    OUTERSYS_ID="9552210" />
            </MESSAGE>';
        return $this->sendMessageToFoglaljOrvost($xml);
    }


}
