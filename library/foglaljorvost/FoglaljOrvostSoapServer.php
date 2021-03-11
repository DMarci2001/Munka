<?php

class FoglaljOrvostSoapServer {

    private $method;
    private $bookingService;
    private $requestCode;
    private $logId = 0;

    public function __construct()
    {
        $this->method = $_SERVER["REQUEST_METHOD"];
        $this->bookingService = new BookingService();
    }

    public function startServer() {
        $body = file_get_contents('php://input');
        if (!empty($body)) {
            $userAgent = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "";
            sql_query("insert into webservicelog set tipus=10, datum=now(), keres=?, ip=?, useragent=?", array($body, $_SERVER["REMOTE_ADDR"], $userAgent));
            $this->logId = sql_insert_id();
        }

        $namespace = Booking_Constants::SOAP_API_NAMESPACE;

        $soapServer = new soap_server();
        $soapServer->soap_defencoding = "utf-8";
        $soapServer->configureWSDL("FoApi", $namespace);
        $soapServer->register('EnqueueMessage'
            ,array('pMessage' => 'xsd:string', "pCallerID" => 'xsd:string')
            ,array('return' => 'xsd:string')
            ,$namespace,false
            ,'rpc'
            ,'encoded'
            ,'Message processing'
        );

        $soapServer->service(file_get_contents("php://input"));
        exit();
    }


    private function checkDoctor($oid) {
        if ($row = sql_fetch_array(sql_query("select id from orvosok where id=?", [$oid]))) {
            return true;
        }
        return false;
    }

    private function checkField($fieldId, $servId) {
        //gyermek bőrgyógy
        if ($fieldId == 98) {
            $fieldId = 4;
        }

        if ($result = sql_fetch_array(sql_query("select id from szurestipusok where (fotid=? or fotid=?) and fotid<>0", [$fieldId, $servId]))) {
            return $result;
        }

        if ($field = sql_query("SELECT remoteid, parentremoteid FROM remoteids WHERE remoteid=?", [$fieldId])->fetch()) {
            if ($result = sql_fetch_array(sql_query("select id from szurestipusok where fotid=? and fotid<>0", [$field["parentremoteid"]]))) {
                return $result;
            }
        }
        return false;

        //return sql_fetch_array(sql_query("select id from szurestipusok where (fotid=? or fotid=?) and fotid<>0", [$fieldId, $servId]));
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

        $reservationDate        = (string)$xml->APPOINTMENT["APPOINTMENT"];
        $rinterval              = intval((string)$xml->APPOINTMENT["APPOINTMENT_LONG"]);
        $patientName            = (string)$xml->APPOINTMENT["PATIENT_NAME"];
        $patientEmail           = (string)$xml->APPOINTMENT["PATIENT_EMAIL"];
        $patientPhone           = (string)$xml->APPOINTMENT["PATIENT_PHONE"];
        $patientBirthDate       = isset($xml->APPOINTMENT["DATE_OF_BIRTH"]) ? str_replace(".","-",(string)$xml->APPOINTMENT["DATE_OF_BIRTH"]) : "0000-00-00";
        $reservationDescription = trim((string)$xml->APPOINTMENT["DESCRIPTION"]." ".$this->getServiceString($srvId));
        $locationId             = Booking_Constants::DEFAULT_PLACE_IDS[0];

        if (empty(trim($patientName))) {
            $patientName = "nincs név";
        }

        if (!$this->checkDoctor($doctorOwnId)) {
            return $this->messageOutput("NO_DOCTOR", "Az orvos nem található a klinika rendszerében ({$doctorOwnId})");
        }

        if (!$szuresTipusData = $this->checkField($fieldId, $srvId)) {
            return $this->messageOutput("NO_FIELD", "A megadott FIELD nem található a klinika rendszerében ({$fieldId})");
        }

        $data = [
            "parentid" => 0,
            "paciensid" => 0,
            "cegid" => 0,
            "datum" => $reservationDate,
            "rinterval" => $rinterval,
            "telephely" => "",
            "helyszin" => $locationId,
            "szurestipus" => $szuresTipusData["id"],
            "nev" => $patientName,
            "email" => $patientEmail,
            "telefon" => $patientPhone,
            "szuldatum" => $patientBirthDate,
            "szulhely" => "",
            "anyjaneve" => "",
            "neme" => 0,
            "taj" => "",
            "irsz" => "0000",
            "varos" => "",
            "utca" => "",
            "megj" => $reservationDescription,
            "munkakor" => "",
            "tudoszuro" => 0,
            "lang" => "hu",
            "orvosid" => $doctorOwnId,
            "aktiv" => 1,
            "rn" => rand(1000000, 9999999)];

        $_REQUEST["rinterval"] = $data["rinterval"]; //fix

        $fid = $this->bookingService->addReservationQuery($data);

        //set foglaljorvost id
        sql_query("update foglalasok set fofid=?, foglalta='foglaljorvost' where id=?", [$appointmentId, $fid]);

        return $this->messageOutput("0",$fid);
    }

    private function appointmentMod(SimpleXMLElement $xml) {
        $status          = (string)$xml->APPOINTMENT["STATUS"];
        $appointmentId   = (string)$xml->APPOINTMENT["OWN_ID"];
        $appointmentFoId = (string)$xml->APPOINTMENT["OUTERSYS_ID"];
        $srvId           = (string)$xml->APPOINTMENT["SRV_ID"];
        $fieldOwnId      = (string)$xml->FIELD["OWN_ID"];
        $fieldId         = (string)$xml->FIELD["OUTERSYS_ID"];
        $doctorOwnId     = (string)$xml->DOCTOR["OWN_ID"];
        $doctorId        = (string)$xml->DOCTOR["OUTERSYS_ID"];

        $reservationDate        = (string)$xml->APPOINTMENT["APPOINTMENT"];
        $rinterval              = intval((string)$xml->APPOINTMENT["APPOINTMENT_LONG"]);
        $patientName            = (string)$xml->APPOINTMENT["PATIENT_NAME"];
        $patientEmail           = (string)$xml->APPOINTMENT["PATIENT_EMAIL"];
        $patientPhone           = (string)$xml->APPOINTMENT["PATIENT_PHONE"];
        $patientBirthDate       = isset($xml->APPOINTMENT["DATE_OF_BIRTH"]) ? str_replace(".","-",(string)$xml->APPOINTMENT["DATE_OF_BIRTH"]) : "0000-00-00";
        $reservationDescription = trim((string)$xml->APPOINTMENT["DESCRIPTION"]." ".$this->getServiceString($srvId));

        if (!$this->checkDoctor($doctorOwnId)) {
            return $this->messageOutput("NO_DOCTOR", "Az orvos nem található a klinika rendszerében ({$doctorOwnId})");
        }

        if (!$szuresTipusData = $this->checkField($fieldId, $srvId)) {
            return $this->messageOutput("NO_FIELD", "A megadott FIELD nem található a klinika rendszerében ({$fieldId})");
        }

        if ($reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=? and fofid=? limit 1", [$appointmentId, $appointmentFoId]))) {
            if ($status == "L") {
                //törlés
                sql_query("delete from foglalasok where id=? and fofid=?", [$appointmentId, $appointmentFoId]);
            } else {
                $params = [
                    $reservationDate,
                    $rinterval,
                    $patientName,
                    $patientEmail,
                    $patientPhone,
                    $patientBirthDate,
                    $reservationDescription,
                    $doctorOwnId,
                    $szuresTipusData["id"],
                    $appointmentId,
                    $appointmentFoId
                ];

                sql_query("update foglalasok set datum=?, rinterval=?, nev=?, email=?, telefon=?, szuldatum=?, megj=?, orvosassigned=?, szurestipusid=? where id=? and fofid=?", $params);
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

    public function messageOutput($code, $message) {
        $message = '<?xml version="1.0" encoding="UTF-8"?>
        <MESSAGE>
            <RETURN
                RETCODE="'.$code.'"
                RETMESSAGE="'.$message.'" />
        </MESSAGE>';

        if ($this->logId != 0) {
            sql_query("update webservicelog set response=? where id=?", [$message, $this->logId]);
        }
        return $message;
    }

    private function getServiceString($srvId) {
        $return = "";
        if ($data = sql_fetch_array(sql_query("select megnev from remoteids where provider=? and tipus in ('service','field') and remoteid=?", [FoglaljOrvostService::PROVIDER_NAME, $srvId]))) {
            $return = "Szolgáltatás: ".$data["megnev"];
        }
        return $return;
    }

    private function checkRotateHash($hash) {
        if (md5(sha1("fo|".Booking_Constants::SOAP_API_PASSWORD."|".date("Y.m.d")."$")) == $hash) {
            return true;
        }
        return false;
    }

    public function startMessageProcess($body, $code) {
        $xml = simplexml_load_string(utf8_encode($body));
        if ($xml === false) {
            return $this->messageOutput("WRONG XML", "Error parsing xml");
        }
        $this->requestCode = $code;

        $ifcName     = (string)$xml->MSGINFO["IFCNAME"];
        $messageType = (string)$xml->MSGINFO["MESSAGETYPE"];
        $action      = (string)$xml->MSGINFO["ACTION"];
        $status      = (string)$xml->APPOINTMENT["STATUS"];
        $rotateHash  = (string)$xml->MSGINFO["ROTATE_HASH"];

        if (!$this->checkRotateHash($rotateHash)) {
            return $this->messageOutput("AUTH_FAILED", "Az api hívása nem engedélyezett");
        }

        $fullAction = "{$ifcName}_{$messageType}_{$action}_{$status}";
        sql_query("update webservicelog set action=? where id=?", [$fullAction, $this->logId]);

        if ($fullAction == "FOGLALJORVOST_APPOINTMENT_NEW_E") {
            return $this->appointmentNew($xml);
        }
        if ($fullAction == "FOGLALJORVOST_APPOINTMENT_MOD_E") {
            return $this->appointmentMod($xml);
        }
        if ($fullAction == "FOGLALJORVOST_APPOINTMENT_MOD_L") {
            return $this->appointmentDel($xml);
        }

        return $this->messageOutput("ACTION NOT FOUND", "Action not found");
    }

}


//SOAP server function
function EnqueueMessage($body, $code) {
    if (isset($GLOBALS["foSoapService"])) {
        $service = $GLOBALS["foSoapService"];
    } else {
        $service = new FoglaljOrvostSoapServer();
    }
    return $service->startMessageProcess($body, $code);
}

//soap teszteléshez
if (isset($_GET["tesztapi"])) {
    $client = new SoapClient(Booking_Constants::SOAP_API_NAMESPACE."/foApi.php?wsdl", array('soap_version' => SOAP_1_1, 'trace' => true, 'cache_wsdl' => WSDL_CACHE_NONE));

    $body = '<?xml version="1.0" encoding="UTF-8"?>
<MESSAGE>
    <MSGINFO
        IFCNAME="FOGLALJORVOST"
        MESSAGETYPE="APPOINTMENT"
        ACTION="NEW"
        ROTATE_HASH="'.generateFORotateHash().'" />   
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

function generateFORotateHash() {
    return md5(sha1("fo|".Booking_Constants::SOAP_API_PASSWORD."|".date("Y.m.d"."$")));
}
