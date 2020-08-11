<?php


class FoglaljOrvostService {
    const FO_API_URL      = "http://test.foglaljorvost.hu/dokucomms";
    const FO_API_TEST_URL = "http://test.foglaljorvost.hu/dokucomms";

    const UNION_API_URL      = "http://foglaljorvost-test.digitalbeaver.hu/dokucomms";
    const UNION_API_TEST_URL = "http://foglaljorvost-test.digitalbeaver.hu/dokucomms";

    private $currentService = "foglaljorvost";

    private $testing = true;

    private $bookingService;

    public function __construct()
    {
        if (isset($_GET["testservicename"])) {
            $this->currentService = $_GET["testservicename"];
        }
        $this->bookingService = new BookingService();
    }

    public function setService($service) {
        $this->currentService = $service;
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
        if ($reservationData = sql_fetch_array(sql_query("select f.*,o.foid as orvosfoid from foglalasok f left join orvosok o on o.id=f.orvosassigned where f.id=? and o.foid<>0", [$fid]))) {
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
                sql_query("update foglalasok set fofid=? where id=?", [$message, $fid]);
            }

            return $result;
        }
        return false;
    }

    public function modifyReservation($fid) {
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
        if (isset($beo["error"])) {
            return $beo["error"];
        }

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
        if (isset($beo["error"])) {
            return $beo["error"];
        }

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
        if (isset($beo["error"])) {
            return $beo["error"];
        }

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

    private function getBeosztasData($beoId) {
        $res = sql_query("select b.*, o.foid from orvos_beosztas b left join orvosok o on o.id = b.orvosid where b.id=?", [$beoId]);
        if (!$beo = sql_fetch_array($res)) {
            $beo["error"] = "Beosztás nem található!";
            return $beo;
        }

        $tipusok = array_values(array_filter(array_unique(explode("|", $beo["tipusok"]))));
        foreach ($tipusok as $tipus) {
            if ($szurestipusData = sql_fetch_array(sql_query("select * from szurestipusok where id=?", [$tipus]))) {
                if ($szurestipusData["fotid"] == 0) {
                    $beo["error"] = "error: {$szurestipusData["megnev"]} tipus nincs a foglaljOrvos-al szinkronizálva!";
                }

                $beo["fotid"] = $szurestipusData["fotid"];
            }
        }

        $beo["week"] = 1;
        $beo["startTime"] = date("Y-m-d");
        if ($beo["nap"] == 1) $beo["startTime"] = date("Y-m-d", strtotime("this week monday"));
        if ($beo["nap"] == 2) $beo["startTime"] = date("Y-m-d", strtotime("this week tuesday"));
        if ($beo["nap"] == 3) $beo["startTime"] = date("Y-m-d", strtotime("this week wednesday"));
        if ($beo["nap"] == 4) $beo["startTime"] = date("Y-m-d", strtotime("this week thursday"));
        if ($beo["nap"] == 5) $beo["startTime"] = date("Y-m-d", strtotime("this week friday"));
        if ($beo["nap"] == 6) $beo["startTime"] = date("Y-m-d", strtotime("this week saturday"));
        if ($beo["nap"] == 7) $beo["startTime"] = date("Y-m-d", strtotime("this week sunday"));
        $beo["startDate"] = $beo["startTime"];
        $beo["endTime"] = $beo["startTime"];
        $beo["startTime"].=" ".$beo["tol"].":00";
        $beo["endTime"].=" ".$beo["ig"].":00";

        if ($beo["nap"] == 10) {
            $beo["week"] = 0;
            $beo["startDate"] = $beo["beonap"];
            $beo["startTime"] = $beo["beonap"]." ".$beo["tol"].":00";
            $beo["endTime"] = $beo["beonap"]." ".$beo["ig"].":00";
        }
        return $beo;
    }

    private function getReservationStatus($reservationData) {
        //STATUS: Az előjegyzés státusza. Kötelező. A státuszjelzők a következők lehetnek:
        //“E”: sima előjegyzés, foglalás, egyéb elfoglaltság, szabadság
        //“J”: jelen, megjelent. A beteg megjelent a klinikán.
        //“N”: nem jött, nem jelent meg előzetes lemondás nélkül.
        //“L”: lemondta. A beteg lemondta az előjegyzését.

        $status = "E";
        if ($reservationData["eljott"] == 1) {
            $status = "J";
        }
        return $status;
    }

    private function sendMessageToFoglaljOrvost($xml) {
        if (!Booking_Constants::FO_CONNECTION_ENABLED) {
            return false;
        }

        $xml = str_replace("#rotatehash#", $this->generateRotateHash(), $xml);
        $xml = str_replace("#ifcname#", Booking_Constants::FO_IFC_NAME, $xml);

        $userAgent = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "";
        $remoteAddr = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "";
        sql_query("insert into webservicelog set tipus=11, datum=now(), keres=?, ip=?, useragent=?", array($xml, $remoteAddr, $userAgent));
        $logId = sql_insert_id();

        try {
            $client = new SoapClient($this->getApiURL());
            $result = $client->EnqueueMessage($xml, Booking_Constants::FO_IFC_NAME);
            sql_query("update webservicelog set response=? where id=?", [$result, $logId]);
            return $result;
        } catch (SoapFault $exception) {
            sql_query("update webservicelog set exception=? where id=?", [$exception->getMessage(), $logId]);
            return false;
        }
    }

    private function getApiURL() {
        $url = self::FO_API_URL;
        if ($this->currentService == "union") {
            $url = self::UNION_API_URL;
        }
        if ($this->testing) {
            $url = self::FO_API_TEST_URL;
            if ($this->currentService == "union") {
                $url = self::UNION_API_TEST_URL;
            }
        }
        return $url;
    }

    private function getApiPassword() {
        $password = Booking_Constants::FO_API_PASSWORD;
        if ($this->testing) {
            $password = Booking_Constants::FO_API_TEST_PASSWORD;
        }
        return $password;
    }

    private function generateRotateHash() {
        return md5(sha1("fo|".$this->getApiPassword()."|".date("Y.m.d"."$")));
    }


    private function description($reservationData) {
        $description = "";

        if (trim($reservationData["nev"]) != "") {
            $description.= "név: {$reservationData["nev"]}\n";
        }

        if (trim($reservationData["telefon"]) != "") {
            $description.= "telefon: {$reservationData["telefon"]}\n";
        }

        if (trim($reservationData["megj"]) != "") {
            $description.= "megjegyzés: {$reservationData["megj"]}\n";
        }

        return $description;
    }
}
