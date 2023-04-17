<?php

class BookingSyncApi {
    const LOG_ID = 30;

    private $utils;

    private array $placeSyncMap = [
        "hungariamed" => [100, 292, "https://bejelentkezes.keltexmed.hu/syncApi.php?key=e23f8b75-9d88-4ad1-8149-12ece3ff9ce9"],
        "keltexmed"   => [292, 100, "https://bejelentkezes.hungariamed.hu/syncApi.php?key=04ab0c03-7e9f-468f-8d37-edc1a639d013"]
    ];

    public function __construct() {
        $this->utils = new Utils();
    }

    public function start() {
        if (!isset($_GET["key"]) || $_GET["key"] != Booking_Constants::API_KEY) {
            die("Api key error!");
        }

        $input = json_decode(file_get_contents('php://input'), JSON_OBJECT_AS_ARRAY);

        if (!isset($input["action"])) {
            die("Action parameter missing!");
        }

        $action = $input["action"];

        sql_query("insert into webservicelog set tipus=?, datum=now(), keres=?, action=?", [self::LOG_ID, print_r($input, true), $action]);

        if ($action == "storenewreservation") {
            $this->storeNewReservationAction($input);
        }

        if ($action == "modifyremotereservation") {
            $this->modifyRemoteReservationAction($input);
        }

        if ($action == "deleteremotereservation") {
            $this->deleteRemoteReservationAction($input);
        }

        if ($action == "storebeosztas") {
            //$this->storeAllBeosztas($input);
        }

        die("Action not found");
    }


    //TODO: nem működő funckció!!
    private function storeAllBeosztas($data) {
        if ($orvosData = sql_fetch_array(sql_query("select * from orvosok where pecsetszam=?", [$data["pecsetszam"]]))) {
            if ($cegData = sql_fetch_array(sql_query("select * from cegek where domain=?", [$data["cegdomain"]]))) {
                sql_query("delete from orvos_beosztas_new where cegid=? and orvosid=? and remoteid<>0", [$cegData["id"], $orvosData["id"]]);

                foreach ($data["beosztasok"] as $beoData) {
                    sql_query("insert into orvos_beosztas_new set
                               orvosid=?,
                               cegid=?,
                               beocegek=?,
                               helyszinid=?,
                               nap=?,
                               beonap=?,
                               tol=?,
                               ig=?,
                               potig=?,
                               hetek=?,
                               binterval=?,
                               csaksorban=?,
                               tipusok=?,
                               aktiv=?,
                               noreservation=?,
                               remoteid=?                                                           
                        ", [$orvosData["id"], $cegData["id"], "|".$cegData["id"]."|", $data["defaulthelyszin"], $beoData["nap"], $beoData["beonap"], $beoData["tol"], $beoData["ig"], $beoData["potig"], $beoData["hetek"], $beoData["binterval"], $beoData["csaksorban"], $beoData["tipusok"], $beoData["aktiv"], $beoData["noreservation"], $beoData["id"]]);
                }
            }
        }
    }

    private function storeNewReservationAction($data) {
        if ($orvosData = sql_fetch_array(sql_query("select * from orvosok where pecsetszam=? and pecsetszam<>''", [$data["pecsetszam"]]))) {
            //echo "orvos";die;
            $externalReservation = $data["reservation"];

            $externalId = $data["source"].$externalReservation["id"];

            if (!$reservationData = sql_fetch_array(sql_query("select id from foglalasok where pass=? and orvosassigned=?", [$externalReservation["pass"], $orvosData["id"]]))) {
                $externalReservation["externalid"] = $externalId;
                $externalReservation["orvosid"] = $orvosData["id"];
                $externalReservation = $this->_fixReservation($externalReservation, $data);

                //print_r($externalReservation);die;
                $bookingService = new BookingService();
                $newReservationId = $bookingService->addReservationQuery($externalReservation);

                $foService = new FoglaljOrvostService();
                $foService->newReservation($newReservationId);
                //die;
            }
        }
        $this->checkTudoSzures($data);
    }

    private function modifyRemoteReservationAction($data) {
        if ($orvosData = sql_fetch_array(sql_query("select * from orvosok where pecsetszam=? and pecsetszam<>''", [$data["pecsetszam"]]))) {
            //echo "orvos";die;
            $externalReservation = $data["reservation"];
            $externalReservation["orvosid"] = $orvosData["id"];
            if (!$reservationData = sql_fetch_array(sql_query("select id from foglalasok where pass=? and orvosassigned=?", [$externalReservation["pass"], $orvosData["id"]]))) {
                sql_query("insert into foglalasok set pass=?, orvosassigned=?, externalid=?", [$externalReservation["pass"], $orvosData["id"], $data["source"].$externalReservation["id"]]);
            }
            $externalReservation = $this->_fixReservation($externalReservation, $data);
            $this->_updateReservation($externalReservation);
        }
        $this->checkTudoSzures($data);
    }

    private function deleteRemoteReservationAction($data) {
        if ($orvosData = sql_fetch_array(sql_query("select * from orvosok where pecsetszam=? and pecsetszam<>''", [$data["pecsetszam"]]))) {
            sql_query("delete from foglalasok where pass=? and orvosassigned=?", [$data["reservationpass"], $orvosData["id"]]);
        }
    }

    private function checkTudoSzures($data) {
        $reservationData = $data["reservation"];
        if (Booking_Constants::SQL_DB == "keltexmed" && $reservationData["tudoszuro"] == 1 && $reservationData["taj"] != "") {
            $reservationData = $this->_fixReservation($reservationData, $data);
            $reservationData["megj"] = "hungariamedről másolva, cég: {$reservationData["cegnev"]}";
            $reservationData["helyszinid"] = $reservationData["helyszin"];

            $bookingService = new BookingService();
            //tüdőszűréshez másolás
            $bookingService->replicateReservationToAnotherService($reservationData, Booking_Constants::TUDOSZURES_ID);
        }
    }


    private function _updateReservation($data) {
        sql_query("update foglalasok f set 
                paciensid=?,
                cegid=?,
                datum=?,
                rinterval=?,
                telephely=?,
                helyszinid=?,
                szurestipusid=?,
                nev=?,
                email=?,
                telefon=?,
                szuldatum=?,
                szulhely=?,
                anyjaneve=?,
                neme=?,
                taj=?,
                irsz=?,
                varos=?,
                utca=?,
                megj=?,
                munkakor=?,
                tudoszuro=?,
                aktiv=?,
                foglalta=?,
                modifiedby=?,
                modifiedtime=?,
                tappenzcheck=?,
                simplepay=?,
                noreservation=?,
                questions=?,
                totalprice=?,
                currency=?,
                exportdata=?
                where f.pass=? and f.orvosassigned=?

                ",
            [
                $data["paciensid"],
                $data["cegid"],
                $data["datum"],
                intval($data["rinterval"]),
                $data["telephely"],
                $data["helyszin"],
                $data["szurestipus"],
                $data["nev"],
                $data["email"],
                $data["telefon"],
                $data["szuldatum"],
                $data["szulhely"],
                $data["anyjaneve"],
                $data["neme"],
                $data["taj"],
                $data["irsz"],
                $data["varos"],
                $data["utca"],
                $data["megj"],
                $data["munkakor"],
                $data["tudoszuro"],
                $data["aktiv"],
                $data["foglalta"],
                $data["modifiedby"],
                $data["modifiedtime"],
                $data["betegallomanynyilatkozat"],
                $data["simplepay"],
                $data["noreservation"],
                $data["questions"],
                $data["totalprice"],
                $data["currency"],
                $data["exportdata"],
                $data["pass"], $data["orvosid"]
            ]
        );
    }

    private function _fixReservation($externalReservation, $syncData) {
        $externalReservation["paciensid"] = 0;
        $externalReservation["szurestipus"] = 0;
        $externalReservation["helyszin"] = 0;
        $externalReservation["lang"] = $externalReservation["rlang"];

        if ($cegData = sql_fetch_array(sql_query("select id from cegek where megnev=?", [$externalReservation["cegnev"]]))) {
            $externalReservation["cegid"] = $cegData["id"];
        } else {
            if (!empty($externalReservation["cegnev"])) {
                sql_query("insert into cegek set megnev=?, aktiv=1", [$externalReservation["cegnev"]]);
                $externalReservation["cegid"] = sql_insert_id();
            }
        }

        if ($tipusData = sql_fetch_array(sql_query("select id from szurestipusok where megnev=?", [$externalReservation["tipusnev"]]))) {
            $externalReservation["szurestipus"] = $tipusData["id"];
        }

        if ($helyszinData = sql_fetch_array(sql_query("select id from helyszinek where cim=?", [$externalReservation["helyszincim"]]))) {
            $externalReservation["helyszin"] = $helyszinData["id"];
        } else {
            if (isset($syncData["defaulthelyszin"])) {
                $externalReservation["helyszin"] = $syncData["defaulthelyszin"];
            }
        }

        return $externalReservation;
    }

    public function sendBeosztas($pecsetszam, $cegId) {
        if ($syncData = sql_fetch_array(sql_query("select * from remoteids r where r.tipus='orvos' and remoteid=?", [$pecsetszam]))) {
            $syncParameters = json_decode($syncData["megnev"], JSON_OBJECT_AS_ARRAY);

            if (isset($syncParameters["enablebeocopy"])) {
                $beosztasok = sql_query("SELECT b.*, c.domain FROM orvosok o
                LEFT JOIN orvos_beosztas_new b ON b.`orvosid`=o.id
                LEFT JOIN cegek c on c.id = b.cegid
                WHERE o.pecsetszam=? AND instr(b.cegid, ?) AND b.aktiv=1 AND b.remoteid=0 ORDER BY b.beonap", [$pecsetszam, "|{$cegId}|"])->fetchAll(PDO::FETCH_ASSOC);

                $cegData = sql_query("select domain from cegek where id=?", [$cegId])->fetch(PDO::FETCH_ASSOC);

                $data = [
                    "source" => Booking_Constants::SQL_DB,
                    "action" => "storebeosztas",
                    "pecsetszam" => $pecsetszam,
                    "cegdomain" => $cegData["domain"],
                    "defaulthelyszin" => $syncParameters["defaulthelyszin"],
                    "beosztasok" => $beosztasok
                ];

                $this->_send($data, $syncParameters["apiurl"]);
            }
        }
    }

    public function newReservation($reservationId) {
        $clinic           = Booking_Constants::SQL_DB;
        $sourcePlace      = $this->placeSyncMap[$clinic][0];
        $destinationPlace = $this->placeSyncMap[$clinic][1];
        $apiURL           = $this->placeSyncMap[$clinic][2];
        $reservation      = $this->_getReservation($reservationId);

        if ($reservation["helyszinid"] == $sourcePlace) {
            $data = [
                "source" => $clinic,
                "action" => "storenewreservation",
                "pecsetszam" => trim($reservation["pecsetszam"]),
                "defaulthelyszin" => $destinationPlace,
                "reservation" => $reservation
            ];

            $this->_send($data, $apiURL);
        }
    }

    public function modifyReservation($reservationId) {
        $clinic           = Booking_Constants::SQL_DB;
        $sourcePlace      = $this->placeSyncMap[$clinic][0];
        $destinationPlace = $this->placeSyncMap[$clinic][1];
        $apiURL           = $this->placeSyncMap[$clinic][2];
        $reservation      = $this->_getReservation($reservationId);

        if ($reservation["helyszinid"] == $sourcePlace) {
            $data = [
                "source" => $clinic,
                "action" => "modifyremotereservation",
                "pecsetszam" => trim($reservation["pecsetszam"]),
                "defaulthelyszin" => $destinationPlace,
                "reservation" => $reservation
            ];

            $this->_send($data, $apiURL);
        }
    }

    public function deleteReservation($reservationData) {
        $clinic           = Booking_Constants::SQL_DB;
        $sourcePlace      = $this->placeSyncMap[$clinic][0];
        $apiURL           = $this->placeSyncMap[$clinic][2];
        $orvosData        = sql_query("SELECT id, pecsetszam FROM orvosok o where id=?", [$reservationData["orvosassigned"]])->fetch(PDO::FETCH_ASSOC);

        if ($reservationData["helyszinid"] == $sourcePlace) {
            $data = [
                "source"          => Booking_Constants::SQL_DB,
                "action"          => "deleteremotereservation",
                "pecsetszam"      => trim($orvosData["pecsetszam"]),
                "reservationpass" => $reservationData["pass"]
            ];

            $this->_send($data, $apiURL);
        }
    }


    private function _getReservation($reservationId) {
        return sql_query("SELECT c.`megnev` AS cegnev, t.megnev AS tipusnev, h.cim as helyszincim, o.pecsetszam, f.* FROM foglalasok f
            LEFT JOIN orvosok o on o.id = f.orvosassigned
            LEFT JOIN cegek c ON c.id = f.cegid
            LEFT JOIN szurestipusok t ON t.id = f.szurestipusid
            LEFT JOIN helyszinek h ON h.id = f.helyszinid
            WHERE f.id=?", [$reservationId])->fetch(PDO::FETCH_ASSOC);
    }

    private function _send($data, $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf8", "Signature: ".Booking_Constants::API_KEY]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $result = curl_exec($ch);
        $return['httpCode'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $return['response'] = json_decode($result, true);

        //print_r($result);die;

        if ($return["httpCode"] == 200 && isset($return["error"]) && $return["error"] == "") {
            return true;
        } else {
            return false;
        }
    }

}