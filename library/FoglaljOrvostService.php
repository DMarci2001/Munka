<?php


class FoglaljOrvostService {
    const API_TEST_URL = "http://foglaljorvost-test.digitalbeaver.hu/dokucomms/";
    const API_TEST_PASSWORD = "wzUpTVrpexTh";
    const IFC_NAME = "HUNGARIAMED";

    private $method;
    private $logId;
    private $bookingService;

    public function __construct()
    {
        $this->method = $_SERVER["REQUEST_METHOD"];
        $this->bookingService = new BookingService();
    }

    public function processInput() {
        $body = file_get_contents('php://input');
        sql_query("insert into webservicelog set tipus=10, datum=now(), keres=?, ip=?, useragent=?",array($body, $_SERVER["REMOTE_ADDR"], $_SERVER["HTTP_USER_AGENT"]));
        $this->logId = sql_insert_id();

        $this->tesztKuldesek($body); // -----

        if ($this->method != "POST") {
            $this->messageOutput("WRONG METHOD", "Method not supported");
        }
        if (!$xml = simplexml_load_string($body)) {
            $this->messageOutput("WRONG XML", "Error parsing xml");
        }

        $ifcName     = $xml->MSGINFO["IFCNAME"];
        $messageType = $xml->MSGINFO["MESSAGETYPE"];
        $action      = $xml->MSGINFO["ACTION"];

        if ($ifcName.$messageType.$action == "FOGLALJORVOSTAPPOINTMENTNEW") {
            $this->appointmentNew($xml);
        }
        if ($ifcName.$messageType.$action == "FOGLALJORVOSTAPPOINTMENTMOD") {
            $this->appointmentMod($xml);
        }


        $this->messageOutput("ACTION_NOT_FOUND", "Action not supported");
    }

    private function messageOutput($code, $message) {
        header("content-type:text/xml");

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <MESSAGE>
            <RETURN
                RETCODE="'.$code.'"
                RETMESSAGE="'.$message.'" />
        </MESSAGE>';

        echo $xml;
        die;
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
            "aktiv" => 1,
            "rn" => rand(1000000, 9999999)];

        $_REQUEST["rinterval"] = $data["rinterval"]; //fix

        $fid = $this->bookingService->addReservationQuery($data);

        $this->messageOutput("0",$fid);
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

        $this->messageOutput("0", $fid);
    }


    private function tesztKuldesek($action) {
        if ($action == "tesztping") {
            $result = $this->sendPing();
            echo $result;
            die;
        }

        if ($action == "tesztfoglalas") {

        }

    }



    private function sendPing() {
        $xml='<?xml version="1.0" encoding="UTF-8"?>
            <MESSAGE>
                <MSGINFO
                    IFCNAME="'.self::IFC_NAME.'"
                    MESSAGETYPE="HEARTBEAT"
                    ACTION="SEND"
                    ROTATE_HASH="1c7dcf26d679dd69b3504baa0a6bb355" />
            </MESSAGE>';
        return $this->sendMessageToFoglaljOrvost($xml);
    }

    private function sendMessageToFoglaljOrvost($xml) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_TEST_URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
        $a = self::IFC_NAME;
    }

    private function saveLogResult() {

    }

}