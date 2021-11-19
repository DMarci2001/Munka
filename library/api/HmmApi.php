<?php

class HmmApi {
    const LOCATION_ID = 1;
    const PROVIDER_NAME = "hmmapi";
    const LOG_ID = 100;

    public $apiMethod;
    public $apiParam;
    private $postParams;
    private $postBody;
    private $requestMethod;

    private $utils;
    private $bookingService;

    public function __construct() {
        $params = explode("/", strtok($_SERVER["REQUEST_URI"], "?"));

        $this->apiMethod = $params[2] ?? "";
        $this->apiParam = $params[3] ?? "";
        $this->postParams = $_REQUEST;
        $this->postBody = file_get_contents("php://input");
        $this->requestMethod = $_SERVER["REQUEST_METHOD"];

        $this->utils = new Utils();
        $this->bookingService = new BookingService();

        if (empty($this->apiMethod)) {
            $this->apiError(500, "missing method", "Hiányzó metódus");
        }
    }


    private function apiError($code, $error, $errorDescription) {
        http_response_code($code);
        $this->utils->jsonOut(["error" => $error, "error_description" => $errorDescription]);
    }

    public function startApi() {
        $result = null;

        if (!empty($this->apiMethod)) {
            sql_query("insert into webservicelog set tipus=?, datum=now(), keres=?, ip=?, useragent=?, action=?", array(self::LOG_ID,  !empty($this->postBody) ? $this->postBody : print_r($_REQUEST, true), "", "", $this->apiMethod."/".$this->apiParam));
            $logId = sql_insert_id();
        }

        if ($this->apiMethod == "token") {
            $result = $this->token();
        } else {
            if (!$this->checkBearer()) {
                $this->apiError(401, "invalid auth key", "Auth key hiányzik, vagy nem érvényes");
            }
        }

        if ($this->apiMethod == "doctors") {
            $result = $this->doctors();
        }

        if ($this->apiMethod == "specializations") {
            $result = $this->specializations();
        }

        if ($this->apiMethod == "locations") {
            $result = $this->locations();
        }

        if ($this->apiMethod == "slots") {
            $result = $this->slots();
        }

        if ($this->apiMethod == "reservations" && $this->requestMethod == "GET") {
            $result = $this->reservationsGet();
        }

        if ($this->apiMethod == "reservations" && $this->requestMethod == "POST") {
            $result = $this->reservationsPost();
        }

        if ($this->apiMethod == "reservations" && $this->requestMethod == "PUT") {
            $result = $this->reservationsPut();
        }

        if ($this->apiMethod == "reservations" && $this->requestMethod == "DELETE") {
            $result = $this->reservationsDelete();
        }


        $this->utils->jsonOut($result);
    }

    private function doctors():array {
        $doctorArray = [];
        $doctors = sql_query("select * from orvosok where foid<>0")->fetchAll();
        foreach ($doctors as $doctor) {
            $doctorArray[] = [
                "id" => $doctor["id"],
                "name" => $doctor["nev"],
                "languages" => [],
                "modifiedAt" => date("c")
            ];
        }

        return $doctorArray;
    }

    private function specializations():array {
        $specialisationArray = [];
        $specialisations = sql_query("select * from szurestipusok order by megnev")->fetchAll();
        foreach ($specialisations as $specialisation) {
            $specialisationArray[] = [
                "id" => $specialisation["id"],
                "name" => $specialisation["megnev"],
                "modifiedAt" => date("c")
            ];
        }

        return $specialisationArray;
    }

    private function locations():array {
        $locationArray = [];
        $locations = sql_query("select * from helyszinek where id=? order by megnev", [self::LOCATION_ID])->fetchAll();
        foreach ($locations as $location) {
            $phones = ["+36 1 800 9333", "+36 30 633 0961"];
            $locationArray[] = [
                "id" => $location["id"],
                "name" => "Hungariamed",
                "zip" => "1135",
                "city" => "Budapest",
                "address" => "Jász u. 33-35.",
                "phone" => $phones,
                "email" => "info@hungariamed.hu",
                "website" => "https://www.hungariamed.hu",
                "modifiedAt" => date("c")
            ];
        }

        return $locationArray;
    }

    private function token():array {
        //if (!isset($this->postParams["username"])) {
        //    print_r($_POST);
        //    echo "aaa".$this->postBody;die;

        //}

        if (!isset($this->postParams["grant_type"])) {
            $this->postParams["grant_type"] = "password";
        }

        if (!isset($this->postParams["username"]) || !isset($this->postParams["password"]) || !isset($this->postParams["grant_type"])) {
            $this->apiError(500, "missing parameter", "Missing parameter or parameters.");
        }

        if ($this->postParams["grant_type"] != "password") {
            $this->apiError(500, "wrong grant_type", "The grant type value is invalid.");
        }

        if (!$tokenData = sql_fetch_array(sql_query("select * from tokens where username=? and password=md5(?)", [$this->postParams["username"], $this->postParams["password"]]))) {
            $this->apiError(400, "invalid user", "The user name or password is incorrect.");
        }

        $token = bin2hex(openssl_random_pseudo_bytes(32));
        $now = strtotime("now");
        $expires = strtotime("now + 1 day");
        $expiresIn = $expires-$now;

        sql_query("update tokens set token=?, created=?, expires=? where id=?", [$token, date("Y-m-d H:i:s", $now), date("Y-m-d H:i:s", $expires), $tokenData["id"]]);

        return [
            "access_token" => $token,
            "token_type" => "bearer",
            "expires_in" => $expiresIn,
            "userName" => $tokenData["username"],
            ".issued" => date("r", $now),
            ".expires" => date("r", $expires)

        ];
    }

    private function checkBearer():bool {
        foreach (getallheaders() as $value) {
            if (substr_count($value, "Bearer ")) {
                $bearer = str_replace("Bearer ", "", $value);
                if (sql_fetch_array(sql_query("select * from tokens where token=? and expires>=now()", [$bearer]))) {
                    return true;
                }
            }
        }
        return false;
    }

    //A hívás a Szolgáltató adatbázisában rögzített AdMedes foglalások adatait adja vissza.
    private function reservationsGet():array {
        if (!empty($this->apiParam)) {
            if (!$reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=?", [intval($this->apiParam)]))) {
                $this->apiError(400, "E1003", "Reservation not found.");
            }

            return $this->_reservationArray($reservationData);
        }

        if (!isset($this->postParams["startDate"])) {
            $this->apiError(500, "missing startDate", "Missing parameter startDate.");
        }
        if (!isset($this->postParams["endDate"])) {
            $this->apiError(500, "missing endDate", "Missing parameter endDate.");
        }

        $startDate        = date("Y-m-d 00:00:00", strtotime($this->postParams["startDate"]));
        $endDate          = date("Y-m-d 23:59:59", strtotime($this->postParams["endDate"]));
        $locationId       = $this->postParams["locationId"] ?? self::LOCATION_ID;
        $specializationId = $this->postParams["specializationId"] ?? 0;
        $doctorId         = $this->postParams["doctorId"] ?? 0;

        $doctorFilterReservation = $specializationFilter = $locationFilter = "";
        if ($doctorId != 0) {
            $doctorFilterReservation = "AND orvosassigned='".intval($doctorId)."'";
        }
        if ($specializationId != 0) {
            $specializationFilter = "AND szurestipusid='".intval($specializationId)."'";
        }
        if ($locationId != 0) {
            $locationFilter = "AND helyszinid='".intval($locationId)."'";
        }

        $return = [];
        $reservations = sql_query("select * from foglalasok where datum>=? and datum<=? {$doctorFilterReservation} {$specializationFilter} {$locationFilter}", [$startDate, $endDate])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reservations as $reservationData) {
            $return[] = $this->_reservationArray($reservationData);
        }

        return $return;
    }

    private function _checkReservationBody($body) {
        if (!sql_fetch_array(sql_query("select id from helyszinek where id=?", [$body["locationId"]]))) {
            $this->apiError(400, "E2001", "Invalid locationId.");
        }
        if (!sql_fetch_array(sql_query("select id from szurestipusok where id=?", [$body["specializationId"]]))) {
            $this->apiError(400, "E2002", "Invalid specializationId.");
        }
        if (!sql_fetch_array(sql_query("select id from orvosok where id=?", [$body["doctorId"]]))) {
            $this->apiError(400, "E2003", "Invalid doctorId.");
        }
        if (empty($body["date"])) {
            $this->apiError(400, "E2004", "Invalid date.");
        }
        if (empty($body["length"]) || intval($body["length"]) <=0 || intval($body["length"])>1000) {
            $this->apiError(400, "E2005", "Invalid length.");
        }
        if (empty($body["patientName"])) {
            $this->apiError(400, "E2006", "Invalid patientName.");
        }
        if (empty($body["patientPhone"])) {
            $this->apiError(400, "E2007", "Invalid patientPhone.");
        }
    }

    private function _reservationArray($reservationData):array {
        return [
            "id" => (string)$reservationData["id"],
            "locationId" => $reservationData["helyszinid"],
            "specializationId" => $reservationData["szurestipusid"],
            "doctorId" => $reservationData["orvosassigned"],
            "date" => date("c", strtotime($reservationData["datum"])),
            "length" => empty($reservationData["rinterval"]) ? 15 : $reservationData["rinterval"],
            "patientName" => $reservationData["nev"],
            "patientPhone" => $reservationData["telefon"],
            "patientEmail" => $reservationData["email"],
            "patientDateOfBirth" => date("c", strtotime($reservationData["szuldatum"])),
            "patientMothersName" => $reservationData["anyjaneve"],
            "patientComment" => $reservationData["megj"],
            "patientNotification" => false,
            "modifiedAt" => date("c", strtotime($reservationData["regdatum"]))
        ];
    }

    //A hívás segítségével módosítható a Szolgáltató rendszerében rögzített foglalás. A foglalás adatai között
    //csak a pácienssel kapcsolatos adatok módosíthatók, illetve a megjegyzés.
    private function reservationsPut():array {
        if (!empty($this->apiParam)) {
            if ($reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=? and source=?", [intval($this->apiParam), self::PROVIDER_NAME]))) {

                $body = json_decode($this->postBody, JSON_OBJECT_AS_ARRAY);
                $this->_checkReservationBody($body);

                sql_query("update foglalasok set helyszinid=?, szurestipusid=?, orvosassigned=?, nev=?, telefon=?, email=?, szuldatum=?, anyjaneve=?, megj=? where id=? and teladoccode=?",
                    [$body["locationId"], $body["specializationId"], $body["doctorId"], $body["patientName"], $body["patientPhone"], $body["patientEmail"], date("Y-m-d", strtotime($body["patientDateOfBirth"])), $body["patientMothersName"], $body["patientComment"], $reservationData["id"], $body["authorizationCode"]]);

                return $this->_reservationArray(sql_fetch_array(sql_query("select * from foglalasok where id=?", [$reservationData["id"]])));
            } else {
                $this->apiError(400, "E1003", "Reservation not found.");
            }
        } else {
            $this->apiError(500, "", "");
        }
        return [];
    }

    //A hívás segítségével a Szolgáltató rendszerében rögzített foglalás törölhető.
    private function reservationsDelete():array {
        if (!empty($this->apiParam)) {
            if ($reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=? and source=?", [intval($this->apiParam), self::PROVIDER_NAME]))) {
                $this->bookingService->deleteReservation($reservationData["id"], $reservationData["pass"]);
                return [];
            } else {
                $this->apiError(400, "E1003", "Reservation not found.");
            }
        } else {
            $this->apiError(500, "", "");
        }
        return [];
    }

    //A hívás a Szolgáltató adatbázisában rögzít egy új foglalást
    private function reservationsPost():array {
        $body = json_decode($this->postBody, JSON_OBJECT_AS_ARRAY);
        $this->_checkReservationBody($body);

        $authorizationCode  = $body["authorizationCode"];
        $locationId         = $body["locationId"];
        $specializationId   = $body["specializationId"];
        $doctorId           = $body["doctorId"];
        $nap                = date("Y-m-d", strtotime($body["date"]));
        $reservationDate    = date("Y-m-d H:i", strtotime($body["date"]));
        $length             = $body["length"];
        $cegId              = Booking_Constants::DEFAULT_COMPANY_ID;
        $taj                = Utils::generateRandomString();
        $patientName        = $body["patientName"];
        $patientPhone       = $body["patientPhone"];
        $patientEmail       = $body["patientEmail"];
        $patientDateOfBirth = date("Y-m-d", strtotime($body["patientDateOfBirth"]));
        $patientMothersName = $body["patientMothersName"];
        $patientComment     = $body["patientComment"];

        //print_r($this->calculateSlots($nap, $nap, $locationId, $specializationId, $doctorId));die;
        foreach ($this->calculateSlots($nap, $nap, $locationId, $specializationId, $doctorId) as $slot) {
            if (date("Y-m-d H:i", strtotime($slot["date"])) == $reservationDate) {
                $found = true;
                break;
            }
        }

        if (!isset($found)) {
            $this->apiError(400, "E1002", "The slot is already taken.");
        }


        if ($paciensData = sql_fetch_array(sql_query("select id from felhasznalok where source=? and email=? and nev=? limit 1", [self::PROVIDER_NAME, $patientEmail, $patientName]))) {
            $paciensId = $paciensData["id"];
        } else {
            sql_query("insert into felhasznalok set source=?, cegid=?, regtime=now(), nev=?, email=?, telefon=?, szuldatum=?, anyjaneve=?, taj=?, flang=?, rkod=?, validated=1",
                [self::PROVIDER_NAME, $cegId, $patientName, $patientEmail, $patientPhone, $patientDateOfBirth, $patientMothersName, $taj, "hu", rand(11000, 98000)]);
            $paciensId = sql_insert_id();
        }

        $data = [
            "parentid" => 0,
            "paciensid" => $paciensId,
            "cegid" => $cegId,
            "datum" => $reservationDate,
            "rinterval" => $length,
            "telephely" => "",
            "helyszin" => $locationId,
            "szurestipus" => $specializationId,
            "nev" => $patientName,
            "email" => $patientEmail,
            "telefon" => $patientPhone,
            "szuldatum" => $patientDateOfBirth,
            "szulhely" => "",
            "anyjaneve" => $patientMothersName,
            "neme" => 0,
            "taj" => $taj,
            "irsz" => "0000",
            "varos" => "",
            "utca" => "",
            "megj" => $patientComment,
            "munkakor" => "",
            "tudoszuro" => 0,
            "lang" => "hu",
            "orvosid" => $doctorId,
            "aktiv" => 1,
            "rn" => rand(1000000, 9999999)];

        $_REQUEST["rinterval"] = $data["rinterval"]; //fix

        $reservationId = $this->bookingService->addReservationQuery($data);

        //set authcode
        sql_query("update foglalasok set teladoccode=?, foglalta=?, source=? where id=?", [$authorizationCode, self::PROVIDER_NAME, self::PROVIDER_NAME, $reservationId]);

        //$unionService = new UnionService();
        //$unionService->newReservation($fid);

        return $this->_reservationArray(sql_fetch_array(sql_query("select * from foglalasok where id=?", [$reservationId])));
    }

    //A hívás a Szolgáltató adatbázisában rögzített szabad időpontok listáját adja vissza.
    //A szabad időpontok lehetnek valósak, amennyiben a Szolgáltató is időpontonként (slotok) tárolja az
    //orvosok rendelési idejeit. Vagy lehetnek virtuálisak, ha a Szolgáltató pl. rendelési idő intervallumokat tart
    //nyilván, és csak a lekéréskor generál belőlük foglalható slotokat.
    private function slots():array {
        if (!isset($this->postParams["startDate"])) {
            $this->apiError(500, "missing startDate", "Missing parameter startDate.");
        }

        $startDate        = $this->postParams["startDate"];
        $endDate          = $this->postParams["endDate"] ?? date("Y-m-d", strtotime($this->postParams["startDate"] . " + 1 month"));
        $locationId       = $this->postParams["locationId"] ?? self::LOCATION_ID;
        $specializationId = $this->postParams["specializationId"] ?? 0;
        $doctorId         = $this->postParams["doctorId"] ?? 0;

        return $this->calculateSlots($startDate, $endDate, $locationId, $specializationId, $doctorId);
    }


    private function calculateSlots($startDate, $endDate, $locationId, $specializationId, $doctorId):array {
        $settings = new Booking_Settings();
        $slots = [];
        $doctorFilter = $doctorFilterSzabadsag = $doctorFilterReservation = $specializationFilter = "";

        if ($doctorId != 0) {
            $doctorFilter = "AND b.orvosid='".intval($doctorId)."'";
            $doctorFilterSzabadsag = "AND oid='".intval($doctorId)."'";
            $doctorFilterReservation = "AND orvosassigned='".intval($doctorId)."'";
        }
        if ($specializationId != 0) {
            $specializationFilter = "AND INSTR(tipusok, '|".intval($specializationId)."|')";
        }

        $beoDatas = sql_query("SELECT b.*, o.id as orvosId, o.nev FROM orvos_beosztas b
            LEFT JOIN orvosok o ON o.id=b.orvosid
            WHERE ((nap = 10 AND beonap>=? AND beonap<=?) OR nap<10) AND nap<>0 {$specializationFilter} {$doctorFilter} AND o.foid<>0", [date("Y-m-d", strtotime($startDate)), date("Y-m-d", strtotime($endDate))])->fetchAll(PDO::FETCH_ASSOC);

        $szabadsagData = [];
        $szabadsagok = sql_query("select * from szabadsag where datumtol>=? {$doctorFilterSzabadsag}", [date("Y-m-d", strtotime($startDate))])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($szabadsagok as $szabadsag) {
            $szabadsagData[$szabadsag["oid"]][$szabadsag["datumtol"]] = 1;
        }

        $reservationTable = [];
        $reservations = sql_query("select datum, rinterval, orvosassigned from foglalasok where datum>now() and datum<date_add(now(), interval 31 day) {$doctorFilterReservation}");
        while ($reservation = sql_fetch_array($reservations)) {
            $rinterval = $reservation["rinterval"] == 0 ? 15 : $reservation["rinterval"];
            for ($r = 0; $r < $rinterval; $r++) {
                $date = date("Y-m-d H:i:s", strtotime("{$reservation["datum"]} + {$r} minute"));
                $reservationTable[$reservation["orvosassigned"]][$date] = 1;
            }
        }

        foreach ($beoDatas as $beosztas) {
            for ($napOffset = 0; $napOffset<=31; $napOffset++) {
                $dayFound    = false;
                $oneDayCheck = false;
                $nap         = date("Y-m-d", strtotime(date("Y-m-d", strtotime($startDate))." + {$napOffset} day"));
                $weekDay     = date("N", strtotime($nap));
                $weekNumber  = date("W", strtotime($nap));

                if ($beosztas["nap"] == 10) {
                    $nap = $beosztas["beonap"];
                    $dayFound = true;
                    $oneDayCheck = true;
                }

                if ($weekDay == $beosztas["nap"]) {
                    $dayFound = true;
                    if ($weekNumber%2 == 0 && $beosztas["hetek"] == 1) {
                        $dayFound = false;
                    }
                    if ($weekNumber%2 == 1 && $beosztas["hetek"] == 2) {
                        $dayFound = false;
                    }
                }

                if ($nap == date("Y-m-d")) {
                    continue;
                }

                if ($dayFound) {
                    $minTol    = "24:00";
                    $maxIg     = "00:00";
                    $maxPotIg  = "00:00";
                    $binterval = $beosztas["binterval"];
                    $orvosId   = $beosztas["orvosid"];

                    if (isset($szabadsagData[$orvosId][$nap])) {
                        continue;
                    }
                    if (in_array($nap, $settings->getMunkaszunetiNapok())) {
                        continue;
                    }

                    if (strtotime($minTol) > strtotime($beosztas["tol"])) {
                        $minTol = $beosztas["tol"];
                    }
                    if (strtotime($maxIg) < strtotime($beosztas["ig"])) {
                        $maxIg = $beosztas["ig"];
                    }
                    if (strtotime($maxPotIg) < strtotime($beosztas["potig"])) {
                        $maxPotIg = $beosztas["potig"];
                    }

                    if ($maxPotIg == "00:00") {
                        $maxPotIg = $maxIg;
                    }

                    if ($minTol != "24:00") {
                        for ($o = 0; $o < 3600; $o += $binterval) {
                            $ora = date("H:i", strtotime("{$minTol}:00 +{$o} minute"));
                            if (strtotime($maxPotIg) <= strtotime($ora)) {
                                break;
                            }

                            $timeFrom = "{$nap} {$ora}:00";
                            $timeTo = date("Y-m-d H:i:s", strtotime("{$timeFrom} + {$binterval} minute"));

                            if (isset($reservationTable[$orvosId][$timeFrom]) || isset($reservationTable[$orvosId][$timeTo])) {
                                continue;
                            }

                            $tipusId = $specializationId;
                            if ($tipusId == 0) {
                                $tipusok = explode("|", $beosztas["tipusok"]);
                                foreach ($tipusok as $tipus) {
                                    if (!empty($tipus)) {
                                        $tipusId = $tipus;
                                        break;
                                    }
                                }
                            }

                            $slot = [
                                "locationId"       => (string)$locationId,
                                "specializationId" => (string)$tipusId,
                                "doctorId"         => (string)$orvosId,
                                "date"             => date("c", strtotime("{$nap} {$ora}")),
                                "length"           => (string)$binterval
                            ];

                            $slots[] = $slot;
                        }
                    }
                }

                if ($oneDayCheck) {
                    break;
                }
           }
        }

        return $slots;
    }

}