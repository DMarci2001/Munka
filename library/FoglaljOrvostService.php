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

    private function appointmentNew(SimpleXMLElement $xml) {
        $srvId = (string)$xml->APPOINTMENT["SRV_ID"];
        $status = (string)$xml->APPOINTMENT["STATUS"];
        $fieldId = (string)$xml->FIELD["OUTERSYS_ID"];
        $unionCode = (string)$xml->APPOINTMENT["UNION_AUTHORIZATION_CODE"];

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
            "orvosid" => (string)$xml->DOCTOR["OUTERSYS_ID"],
            "aktiv" => 0,
            "rn" => rand(1000000, 9999999)];

        $_REQUEST["rinterval"] = $data["rinterval"]; //fix

        $fid = $this->bookingService->addReservationQuery($data);

        return $this->messageOutput("0",$fid);
    }

    private function appointmentMod(SimpleXMLElement $xml) {
        $status = (string)$xml->APPOINTMENT["STATUS"];
        $fid = (string)$xml->APPOINTMENT["OUTERSYS_ID"];

        if ($status == "L") {
            //törlés
            sql_query("delete from foglalasok where id=?", [$fid]);
        } else {
            $params = [(string)$xml->APPOINTMENT["APPOINTMENT"],
                        intval((string)$xml->APPOINTMENT["APPOINTMENT_LONG"]),
                        (string)$xml->APPOINTMENT["PATIENT_NAME"],
                        (string)$xml->APPOINTMENT["PATIENT_EMAIL"],
                        (string)$xml->APPOINTMENT["PATIENT_PHONE"],
                        isset($xml->APPOINTMENT["DATE_OF_BIRTH"]) ? str_replace(".","-",(string)$xml->APPOINTMENT["DATE_OF_BIRTH"]) : "0000-00-00",
                        (string)$xml->APPOINTMENT["DESCRIPTION"],
                        (string)$xml->DOCTOR["OUTERSYS_ID"],
                        $fid];

            sql_query("update foglalasok set datum=?, rinterval=?, nev=?, email=?, telefon=?, szuldatum=?, megj=?, orvosassigned=? where id=?", $params);
        }

        return $this->messageOutput("0", $fid);
    }


    private function tesztKuldesek($action) {
        if ($action == "tesztping") {
            $result = $this->sendPing();
            echo $result;
            die;
        }

        if ($action == "tesztfoglalas") {
            $result = $this->sendReservation(11111);
            echo $result;
            die;
        }
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

    private function sendReservation($fid) {
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
        @$xml = simplexml_load_string($body);
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

        return $this->messageOutput("ACTION NOT FOUND", "Action not found");
    }

}

//SOAP api belépési pont
function EnqueueMessage($body, $code) {
    $service = new FoglaljOrvostService();
    return $service->startSoapProcess($body, $code);
}
