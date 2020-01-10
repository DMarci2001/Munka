<?php



require_once "../autoload.php";
require_once "../library/other/nusoap/nusoap.php";
require_once "../library/FoglaljOrvostService.php";

if (isset($_GET["tesztapi"])) {
    /*
    $client = new nusoap_client("https://bejelentkezes.hungariamed.hu/foApi.php?wsdl", true);
    $error  = $client->getError();

    if ($error) {
        echo "<h2>Constructor error</h2><pre>" . $error . "</pre>";
    }

    $result = $client->call("FoglaljOrvostService.EnqueueMessage");

    echo $result;
    die;
    */

    $client = new SoapClient("https://bejelentkezes.hungariamed.hu/foApi.php?wsdl&".rand(1,10000000), array('soap_version' => SOAP_1_1, 'trace' => true,));
    //echo $client->EnqueueMessage("<tag>Bakker</tag>", "HUN");
    //die;

    $body = '<?xml version="1.0" encoding="UTF-8"?>
<MESSAGE>
    <MSGINFO
        IFCNAME="FOGLALJORVOST"
        MESSAGETYPE="APPOINTMENT"
        ACTION="NEW"
        ROTATE_HASH="dfcff922546f512bc75c15adbf6bab8a" />   
    <DOCTOR
        OWN_ID="1212"
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

    $xml = simplexml_load_string($body);
    echo $client->EnqueueMessage($body, "HUN");
    die;
}

$foService = new FoglaljOrvostService();
$foService->processInput();
