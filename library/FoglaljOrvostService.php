<?php


class FoglaljOrvostService {
    const API_URL = "http://foglaljorvost-test.digitalbeaver.hu/dokucomms/";
    const API_PASSWORD = "wzUpTVrpexTh";

    const API_TEST_URL = "http://foglaljorvost-test.digitalbeaver.hu/dokucomms/";
    const API_TEST_PASSWORD = "wzUpTVrpexTh";

    const IFC_NAME = "HUNGARIAMED";

    private $testing = true;

    private $method;
    private $logId;
    private $bookingService;

    private $soapServer;

    public function __construct()
    {
        $this->method = $_SERVER["REQUEST_METHOD"];
        $this->bookingService = new BookingService();
    }

    public function processInput() {
        $body = file_get_contents('php://input');
        $userAgent = isset($_SERVER["HTTP_USER_AGENT"])?$_SERVER["HTTP_USER_AGENT"]:"";
        sql_query("insert into webservicelog set tipus=10, datum=now(), keres=?, ip=?, useragent=?",array($body, $_SERVER["REMOTE_ADDR"], $userAgent));
        $this->logId = sql_insert_id();

        $this->tesztKuldesek($body); // -----

        $namespace = "https://bejelentkezes.hungariamed.hu/foApi.php";

        $this->soapServer = new soap_server();
        $this->soapServer->soap_defencoding = "utf-8";
        $this->soapServer->configureWSDL("FoApi", $namespace);
        $this->soapServer->register('EnqueueMessage'
            ,array('pMessage' => 'xsd:string', "pCallerID" => 'xsd:string')
            ,array('return' => 'xsd:string')
            ,$namespace,false
            ,'rpc'
            ,'encoded'
            ,'Message processing'
        );

        $POST_DATA = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
        $this->soapServer->service($POST_DATA);
        exit();
    }

    public function messageOutput($code, $message) {
        return '<?xml version="1.0" encoding="UTF-8"?>
        <MESSAGE>
            <RETURN
                RETCODE="'.$code.'"
                RETMESSAGE="'.$message.'" />
        </MESSAGE>';
    }

    private function checkDoctor($oid) {
        if ($row = sql_fetch_array(sql_query("select id from orvosok where id=?", [$oid]))) {
            return true;
        }
        return false;
    }

    private function checkField($fieldId) {
        if ($row = sql_fetch_array(sql_query("select id from szurestipusok where id=?", [$fieldId]))) {
            return true;
        }
        return false;
    }

    private function appointmentNew(SimpleXMLElement $xml) {
        $appointmentId = (string)$xml->APPOINTMENT["OUTERSYS_ID"];
        $srvId         = (string)$xml->APPOINTMENT["SRV_ID"];
        $status        = (string)$xml->APPOINTMENT["STATUS"];
        $fieldOwnId    = (string)$xml->FIELD["OWN_ID"];
        $fieldId       = (string)$xml->FIELD["OUTERSYS_ID"];
        $doctorOwnId   = (string)$xml->DOCTOR["OWN_ID"];
        $doctorId      = (string)$xml->DOCTOR["OUTERSYS_ID"];
        $unionCode     = (string)$xml->APPOINTMENT["UNION_AUTHORIZATION_CODE"];

        if (!$this->checkDoctor($doctorOwnId)) {
            return $this->messageOutput("NO_DOCTOR", "Az orvos nem található a klinika rendszerében ({$doctorOwnId})");
        }

        if (!$this->checkField($fieldOwnId)) {
            return $this->messageOutput("NO_FIELD", "A megadott FIELD nem található a klinika rendszerében ({$fieldOwnId})");
        }

        $data = [
            "parentid" => 0,
            "paciensid" => 0,
            "cegid" => 0,
            "datum" => (string)$xml->APPOINTMENT["APPOINTMENT"],
            "rinterval" => intval((string)$xml->APPOINTMENT["APPOINTMENT_LONG"]),
            "telephely" => "",
            "helyszin" => 0,
            "szurestipus" => 0,
            "nev" => (string)$xml->APPOINTMENT["PATIENT_NAME"],
            "email" => (string)$xml->APPOINTMENT["PATIENT_EMAIL"],
            "telefon" => (string)$xml->APPOINTMENT["PATIENT_PHONE"],
            "szuldatum" => isset($xml->APPOINTMENT["DATE_OF_BIRTH"]) ? str_replace(".","-",(string)$xml->APPOINTMENT["DATE_OF_BIRTH"]) : "0000-00-00",
            "szulhely" => "",
            "anyjaneve" => "",
            "neme" => 0,
            "taj" => "",
            "irsz" => "0000",
            "varos" => "",
            "utca" => "",
            "megj" => (string)$xml->APPOINTMENT["DESCRIPTION"],
            "munkakor" => "",
            "tudoszuro" => 0,
            "lang" => "hu",
            "orvosid" => $doctorOwnId,
            "aktiv" => 0,
            "rn" => rand(1000000, 9999999)];

        $_REQUEST["rinterval"] = $data["rinterval"]; //fix

        $fid = $this->bookingService->addReservationQuery($data);

        //set foglaljorvost id
        sql_query("update foglalasok set fofid=? where id=?", [$appointmentId, $fid]);

        return $this->messageOutput("0",$fid);
    }

    private function appointmentMod(SimpleXMLElement $xml) {
        $status = (string)$xml->APPOINTMENT["STATUS"];
        $appointmentId = (string)$xml->APPOINTMENT["OUTERSYS_ID"];

        $fieldOwnId    = (string)$xml->FIELD["OWN_ID"];
        $fieldId       = (string)$xml->FIELD["OUTERSYS_ID"];
        $doctorOwnId   = (string)$xml->DOCTOR["OWN_ID"];
        $doctorId      = (string)$xml->DOCTOR["OUTERSYS_ID"];

        if (!$this->checkDoctor($doctorOwnId)) {
            return $this->messageOutput("NO_DOCTOR", "Az orvos nem található a klinika rendszerében ({$doctorOwnId})");
        }

        if (!$this->checkField($fieldOwnId)) {
            return $this->messageOutput("NO_FIELD", "A megadott FIELD nem található a klinika rendszerében ({$fieldOwnId})");
        }

        if ($reservationData = sql_fetch_array(sql_query("select * from foglalasok where fofid=? and fofid<>0 limit 1", [$appointmentId]))) {
            if ($status == "L") {
                //törlés
                sql_query("delete from foglalasok where fofid=? and fofid<>0", [$appointmentId]);
            } else {
                $params = [(string)$xml->APPOINTMENT["APPOINTMENT"],
                    intval((string)$xml->APPOINTMENT["APPOINTMENT_LONG"]),
                    (string)$xml->APPOINTMENT["PATIENT_NAME"],
                    (string)$xml->APPOINTMENT["PATIENT_EMAIL"],
                    (string)$xml->APPOINTMENT["PATIENT_PHONE"],
                    isset($xml->APPOINTMENT["DATE_OF_BIRTH"]) ? str_replace(".", "-", (string)$xml->APPOINTMENT["DATE_OF_BIRTH"]) : "0000-00-00",
                    (string)$xml->APPOINTMENT["DESCRIPTION"],
                    $doctorOwnId,
                    $fieldOwnId,
                    $appointmentId];

                //todo orvos outersys_id ellenőrzése!!!
                sql_query("update foglalasok set datum=?, rinterval=?, nev=?, email=?, telefon=?, szuldatum=?, megj=?, orvosassigned=?, szurestipusid=? where fofid=? and fofid<>0", $params);
            }

            return $this->messageOutput("0", $reservationData["id"]);
        }
        return $this->messageOutput("NO_APPOINTMENT", "A foglalás nem található a klinika rendszerében");
    }

    private function appointmentDel(SimpleXMLElement $xml) {
        $appointmentId = (string)$xml->APPOINTMENT["OUTERSYS_ID"];

        if ($reservationData = sql_fetch_array(sql_query("select * from foglalasok where fofid=? and fofid<>0 limit 1", [$appointmentId]))) {
            //törlés
            sql_query("delete from foglalasok where fofid=? and fofid<>0", [$appointmentId]);
            return $this->messageOutput("0", $reservationData["id"]);
        }
        return $this->messageOutput("NO_APPOINTMENT", "A foglalás nem található a klinika rendszerében");
    }



    private function tesztKuldesek($action) {
        if ($action == "tesztping") {
            $result = $this->sendPing();
            echo $result;
            die;
        }

        if ($action == "tesztorvosnew") {
            $result = $this->sendDoctor(71);
            echo $result;
            die;
        }

        if ($action == "tesztgetallfields") {
            $result = $this->getAllFields();
            echo $result;
            die;
        }

        if ($action == "tesztgetfieldsbyclinic") {
            $result = $this->getFieldsByClinic();
            echo $result;
            die;
        }

        if ($action == "tesztgetfieldsbydoctor") {
            $result = $this->getFieldsByDoctor(71);
            echo $result;
            die;
        }

        if ($action == "tesztsendreservation") {
            $result = $this->sendReservation(119440);
            echo $result;
            die;
        }
    }

    private function sendReservation($fid) {
        if ($reservationData = sql_fetch_array(sql_query("select f.*,o.foid as orvosfoid from foglalasok f left join orvosok o on o.id=f.orvosassigned where f.id=?", [$fid]))) {
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
                    STATUS="E"
                    APPOINTMENT_LONG="'.$reservationData["rinterval"].'"
                    DESCRIPTION="teszt" />
            </MESSAGE>';
            return $this->sendMessageToFoglaljOrvost($xml);
        }
        return false;
    }

    private function getAllFields() {
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

    private function getFieldsByClinic() {
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

    private function getFieldsByDoctor($oid) {
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

    private function sendDoctor($oid) {
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

    private function sendPing() {
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


    private function sendMessageToFoglaljOrvost($xml) {
        $xml = str_replace("#rotatehash#", $this->generateRotateHash(), $xml);
        $xml = str_replace("#ifcname#", self::IFC_NAME, $xml);

        //echo $xml;die;

        $client = new SoapClient($this->getApiURL());
        return $client->EnqueueMessage($xml, self::IFC_NAME);
    }

    private function saveLogResult() {

    }

    private function getApiURL() {
        $url = self::API_URL;
        if ($this->testing) {
            $url = self::API_TEST_URL;
        }
        return $url;
    }

    private function getApiPassword() {
        $password = self::API_PASSWORD;
        if ($this->testing) {
            $password = self::API_TEST_PASSWORD;
        }
        return $password;
    }

    private function generateRotateHash() {
        return md5(sha1("fo|".$this->getApiPassword()."|".date("Y.m.d"."$")));
    }

    private $requestCode;

    public function startSoapProcess($body, $code)
    {
        $xml = simplexml_load_string(utf8_encode($body));
        if ($xml === false) {
            return $this->messageOutput("WRONG XML", "Error parsing xml");
        }
        $this->requestCode = $code;

        $ifcName     = $xml->MSGINFO["IFCNAME"];
        $messageType = $xml->MSGINFO["MESSAGETYPE"];
        $action      = $xml->MSGINFO["ACTION"];

        if ($ifcName.$messageType.$action == "FOGLALJORVOSTAPPOINTMENTNEW") {
            return $this->appointmentNew($xml);
        }
        if ($ifcName.$messageType.$action == "FOGLALJORVOSTAPPOINTMENTMOD") {
            return $this->appointmentMod($xml);
        }
        if ($ifcName.$messageType.$action == "FOGLALJORVOSTAPPOINTMENTMOD") {
            return $this->appointmentDel($xml);
        }

        return $this->messageOutput("ACTION NOT FOUND", "Action not found");
    }

}

//SOAP api belépési pont
function EnqueueMessage($body, $code) {
    $service = new FoglaljOrvostService();
    return $service->startSoapProcess($body, $code);
}

if (isset($_GET["tesztapi"])) {
    $client = new SoapClient("https://bejelentkezes.hungariamed.hu/foApi.php?wsdl", array('soap_version' => SOAP_1_1, 'trace' => true, 'cache_wsdl' => WSDL_CACHE_NONE));

    $body = '<?xml version="1.0" encoding="UTF-8"?>
<MESSAGE>
    <MSGINFO
        IFCNAME="FOGLALJORVOST"
        MESSAGETYPE="APPOINTMENT"
        ACTION="NEW"
        ROTATE_HASH="dfcff922546f512bc75c15adbf6bab8a" />   
    <DOCTOR
        OWN_ID="71"
        OUTERSYS_ID="123232" />
    <FIELD
        OWN_ID="0"
        OUTERSYS_ID="18" />
    <APPOINTMENT
        OUTERSYS_ID="223386"
        APPOINTMENT="2019-05-05 07:00:00"
        STATUS="E"
        APPOINTMENT_LONG="30"
        SRV_ID="50"
        PATIENT_NAME="Kiss Géza"
        PATIENT_PHONE="+36301234567"
        PATIENT_EMAIL="kiss@geza.hu"
        DESCRIPTION="próba"
        UNION="0" 
        UNION_AUTHORIZATION_CODE="UE2342423"   
        DATE_OF_BIRTH="1975.12.11" />
</MESSAGE>';

    echo $client->EnqueueMessage($body, "HUN");
    die;
}

