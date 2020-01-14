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

    public function processTestInput() {
        $action = file_get_contents('php://input');

        if ($action == "tesztping") {
            $result = $this->sendPing();
        }
        if ($action == "tesztorvosnew") {
            $result = $this->sendDoctor(71);
        }
        if ($action == "tesztgetallfields") {
            $result = $this->getAllFields();
        }
        if ($action == "tesztgetfieldsbyclinic") {
            $result = $this->getFieldsByClinic();
        }
        if ($action == "tesztgetfieldsbydoctor") {
            $result = $this->getFieldsByDoctor(71);
        }
        if ($action == "tesztsendreservation") {
            $result = $this->sendReservation(119440);
        }
        if (isset($result)) {
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


}
