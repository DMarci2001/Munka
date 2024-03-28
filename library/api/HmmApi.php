<?php

class HmmApi {
    const PROVIDER_NAME = "hmmapi";
    const LOG_ID = 100;

    public $apiMethod;
    public $apiParam;
    private $postParams;
    private $postBody;
    private $requestMethod;
    private $tokenData;

    private $utils;
    private $bookingService;
    private $authNeeded = true;

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
            $savedMethod = $this->apiMethod;
            if ($this->apiParam != "") {
                $savedMethod.= "/".$this->apiMethod;
            }

            sql_query("insert into webservicelog set tipus=?, datum=now(), keres=?, ip=?, useragent=?, action=?", array(self::LOG_ID,  !empty($this->postBody) ? $this->postBody : print_r($_REQUEST, true), "", "", $savedMethod));
            $logId = sql_insert_id();
        }

        //auth nélkül használható végpontok
        if ($this->apiMethod == "webpagedata") {
            $result = $this->webPageData();
        }

        if ($this->apiMethod == "webrootdata") {
            $result = $this->webRootData();
        }

        if ($this->apiMethod == "doctordata") {
            $result = $this->doctorData();
        }

        if ($this->apiMethod == "servicedata") {
            $result = $this->serviceData();
        }

        if ($this->apiMethod == "locationsdata") {
            $result = $this->locationsData();
        }

        if ($this->apiMethod == "contentdata") {
            $result = $this->contentData();
        }

        if ($this->apiMethod == "getReservationPatients") {
            $result = $this->getReservationPatients();
        }

        if ($this->apiMethod == "token") {
            $result = $this->token();
        }

        if (!$this->checkBearer()) {
            $this->apiError(401, "invalid auth key", "Auth key hiányzik, vagy nem érvényes");
        }

        if ($this->apiMethod == "getReservationPatients") {
            $result = $this->getReservationPatients();
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

        if ($this->apiMethod == "doctormappings") {
            $result = $this->doctorMappings();
        }

        if ($this->apiMethod == "slots") {
            $result = $this->slots();
        }

        if ($this->apiMethod == "reservations" && $this->requestMethod == "GET") {
            $result = $this->reservationsGet();
        }

        if ($this->apiMethod == "reservation" && $this->requestMethod == "GET") {
            $result = $this->reservationGet();
        }

        if ($this->apiMethod == "reservations" && $this->requestMethod == "POST") {
            $result = $this->reservationsPost();
        }

        if ($this->apiMethod == "reservations" && $this->requestMethod == "PUT") {
            $result = $this->reservationsPut();
        }

        if ($this->apiMethod == "reservation" && $this->requestMethod == "DELETE") {
            $result = $this->reservationDelete();
        }

        if ($this->apiMethod == "dokirexInsertVizsglapAdatok") {
            $result = $this->dokirexInsertVizsglapAdatok();
        }

        $this->utils->jsonOut($result);
    }

    private function doctorMappings():array {
        $mappingsArray = [];
        $locations = sql_query("select * from helyszinek where id=? order by megnev", [Booking_Constants::DEFAULT_PLACE_IDS[0]])->fetchAll();
        foreach ($locations as $location) {
            $doctors = sql_query("SELECT b.helyszinid, b.`orvosid`, REPLACE(REPLACE(REPLACE(GROUP_CONCAT(DISTINCT b.`tipusok`), '|,|', ','), '||', ','), '|', '') AS tipusok FROM orvos_beosztas_new b 
                LEFT JOIN orvosok o ON o.id=b.orvosid
                WHERE b.helyszinid=1 AND o.foid<>0 and b.aktiv=1
                GROUP BY b.`helyszinid`, b.`orvosid`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($doctors as $doctor) {
                $mappingsArray[] = [
                    "locationId" => $doctor["helyszinid"],
                    "doctorId" => $doctor["orvosid"],
                    "specializationIds" => array_unique(explode(",", $doctor["tipusok"])),
                ];
            }
        }

        return $mappingsArray;
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
        $locations = sql_query("select * from helyszinek where id=? order by megnev", [Booking_Constants::DEFAULT_PLACE_IDS[0]])->fetchAll();
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

    private function webRootData():array {
        $docAgent = new DocAgent();
        $this->authNeeded = false;

        $doctors = sql_query("SELECT o.id as orvosid, o.nev, o.webdescription, o.szurestipusok, group_concat(distinct b.tipusok) as tipusok FROM orvos_beosztas_new b
                LEFT JOIN orvosok o ON o.id=b.orvosid                                                                     
                WHERE (nap<10 OR (nap=10 AND beonap>date(now()))) and tol<>0 and ig<>0 and b.aktiv=1 and o.aktiv=1 and o.pecsetszam<>'temp'
                GROUP BY b.orvosid ORDER BY o.nev", [Booking_Constants::DEFAULT_PLACE_IDS[0]])->fetchAll(PDO::FETCH_ASSOC);

        $doctorData = [];
        $serviceData = [];
        $priceData = [];

        foreach ($doctors as $doctor) {
            $photos = $docAgent->getAssetsByType(DocAgent::ASSET_DOCTOR_PHOTO, $doctor["orvosid"]);
            if (!empty($photos)) {
                $tipusids = str_replace("|", "", str_replace("||", ",", $doctor["tipusok"]));

                $markedServices = json_decode($doctor["szurestipusok"], JSON_OBJECT_AS_ARRAY);
                if (json_last_error() == JSON_ERROR_NONE) {
                    foreach ($markedServices as $markedServiceId) {
                        $tipusids.= ",{$markedServiceId}";
                    }
                }

                $services = [];
                if (!empty($tipusids)) {
                    $services = sql_query("select id, webalias, megnev from szurestipusok where id in ({$tipusids})")->fetchAll(PDO::FETCH_ASSOC);
                }

                $doctorData["doctors"][] = [
                    "nev"      => $doctor["nev"],
                    "id"       => $doctor["orvosid"],
                    "photos"   => $photos,
                    "tipusids" => $tipusids,
                    "tipusok"  => $services,
                ];
            }
        }

        $services = sql_query("select t.id, t.megnev, t.webkiemelt, t.webalias, t.webdescription from szurestipusok t 
                 left join dokumentumok d on d.assetid=? and d.dataid=t.id 
                 where d.id is not null group by t.id order by t.megnev", [DocAgent::ASSET_SERVICE_ILLUSTRATION_IMAGE])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($services as $service) {
            $photos = $docAgent->getAssetsByType(DocAgent::ASSET_SERVICE_ILLUSTRATION_IMAGE, $service["id"]);
            if (!empty($photos)) {
                $serviceData["services"][] = [
                    "id"                => $service["id"],
                    "nev"               => $service["megnev"],
                    "webkiemelt"        => $service["webkiemelt"],
                    "webalias"          => $service["webalias"],
                    "photos"            => $photos,
                ];
            }
        }


        $prices = sql_query("SELECT t.id AS tipusid, t.megnev as tipus, a.price, a.megnev FROM arak a 
                LEFT JOIN szurestipusok t ON t.id=a.tipusid
                WHERE INSTR(cegid, '|243|') ORDER BY t.megnev, a.megnev")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($prices as $price) {
            $priceData[$price["tipus"]][] = [
                "tipusid" => $price["tipusid"],
                "price"   => $price["price"],
                "megnev"  => $price["megnev"],
            ];
        }

        return [
            "doctorData" => $doctorData,
            "serviceData" => $serviceData,
            "priceData" => $priceData
        ];
    }

    private function doctorData():array {
        $docAgent = new DocAgent();
        $this->authNeeded = false;

        $alias = $_GET["alias"];
        $doctorData = [];

        $doctors = sql_query("SELECT o.id as orvosid, o.nev, o.webdescription, group_concat(distinct b.tipusok) as tipusok FROM orvos_beosztas_new b
                LEFT JOIN orvosok o ON o.id=b.orvosid                                                                     
                WHERE (nap<10 OR (nap=10 AND beonap>date(now()))) and tol<>0 and ig<>0 and b.aktiv=1 and o.aktiv=1 and o.pecsetszam<>'temp'
                GROUP BY b.orvosid ORDER BY o.nev", [Booking_Constants::DEFAULT_PLACE_IDS[0]])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($doctors as $doctor) {
            if ($this->string2URL($doctor["nev"]) != $alias) {
                continue;
            }

            $photos = $docAgent->getAssetsByType(DocAgent::ASSET_DOCTOR_PHOTO, $doctor["orvosid"]);
            if (!empty($photos)) {
                $tipusids = str_replace("|", "", str_replace("||", ",", $doctor["tipusok"]));

                $services = [];
                if (!empty($tipusids)) {
                    $services = sql_query("select id, webalias, megnev from szurestipusok where id in ({$tipusids})")->fetchAll(PDO::FETCH_ASSOC);
                }

                $doctorData["doctors"][] = [
                    "nev"      => $doctor["nev"],
                    "id"       => $doctor["orvosid"],
                    "photos"   => $photos,
                    "tipusids" => $tipusids,
                    "tipusok"  => $services,
                    "webdescription" => $doctor["webdescription"],
                ];
            }
        }


        return [
            "doctorData" => $doctorData
        ];
    }

    private function string2URL($string):string {
        return strtolower(str_replace([".", " "], ["", "_"], $string));
    }

    private function serviceData():array {
        $docAgent = new DocAgent();
        $this->authNeeded = false;

        $alias = $_GET["alias"];
        $serviceData = [];

        $services = sql_query("select t.id, t.megnev, t.webkiemelt, t.webalias, t.webdescription from szurestipusok t 
                 left join dokumentumok d on d.assetid=? and d.dataid=t.id 
                 where t.webalias=? group by t.id order by t.megnev", [DocAgent::ASSET_SERVICE_ILLUSTRATION_IMAGE, $alias])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($services as $service) {
            $photos = $docAgent->getAssetsByType(DocAgent::ASSET_SERVICE_ILLUSTRATION_IMAGE, $service["id"]);
            if (!empty($photos)) {
                $serviceData["services"][] = [
                    "id"                => $service["id"],
                    "nev"               => $service["megnev"],
                    "webkiemelt"        => $service["webkiemelt"],
                    "webalias"          => $service["webalias"],
                    "photos"            => $photos,
                    "webdescription"    => $service["webdescription"],
                    "prices"            => sql_query("select id, price, megnev, penznem from arak where tipusid=? and instr(cegid, '|243|')", [$service["id"]])->fetchAll(PDO::FETCH_ASSOC)
                ];
            }
        }

        return [
            "serviceData" => $serviceData
        ];
    }

    private function locationsData():array {
        $maps = new Maps();
        $this->authNeeded = false;

        $locationsData = [];
        $locations = sql_query("SELECT id, geocodejson from helyszinek where aktiv=1 and halozat=1")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($locations as $location) {
            $addressInfo = $maps->getAddressInfo(json_decode($location["geocodejson"], JSON_OBJECT_AS_ARRAY));
            if ($addressInfo["lat"] != 0) {
                $locationsData["locations"][] = $addressInfo;
            }
        }

        return [
            "locationsData" => $locationsData
        ];
    }

    private function contentData():array {
        $docAgent = new DocAgent();
        $this->authNeeded = false;

        $limit = $_GET["limit"] ?? 1;
        $catId = $_GET["catid"] ?? 84;
        $id = $_GET["id"] ?? 0;
        $serviceId = $_GET["serviceid"] ?? 0;

        $contents = [];

        $serviceWhere = "";
        if ($serviceId != 0) {
            $serviceWhere = " AND instr(c.tipusid, '\"{$serviceId}\"')";
            if ($tipusData = sql_query("select webalias from szurestipusok where id=?", [$serviceId])->fetch(PDO::FETCH_ASSOC)) {
                $serviceWhere = " AND (instr(c.tipusid, '\"{$serviceId}\"') OR instr(c.tags, '{$tipusData["webalias"]}'))";
            }
        }

        if ($id == 0) {
            $items = sql_query("select * from hmmweb.q9a8m_content c where c.catid=? and publish_up<now() and (publish_down>now() or publish_down='0000-00-00 00:00:00') {$serviceWhere} order by created desc limit {$limit}", [$catId])->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $items = sql_query("select * from hmmweb.q9a8m_content c where c.id=? and publish_up<now() and (publish_down>now() or publish_down='0000-00-00 00:00:00')", [$id])->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($items as $contentData) {
            $images = json_decode($contentData["images"], JSON_OBJECT_AS_ARRAY);
            $introImage = "";
            $contentImages = [];
            if (isset($images["image_intro"]) && !empty($images["image_intro"])) {
                $introImage = "https://www.hungariamed.hu/{$images["image_intro"]}";
            }

            if (!empty($contentImages[$contentData["id"]]["contenttitleimage"])) {
                $introImage = $contentImages[$contentData["id"]]["contenttitleimage"][0]["url"];
            }

            $images = $docAgent->getAssetsByType(DocAgent::ASSET_CONTENT_TITLE_IMAGE, $contentData["id"]);
            if (!empty($images)) {
                $introImage = "https://bejelentkezes.hungariamed.hu".$images[0]["url"];
                foreach ($images as $image) {
                    $contentImages[] = [
                        "title" => "",
                        "url"   => "https://bejelentkezes.hungariamed.hu".$image["url"],
                    ];
                }

            }

            $contents[] = [
                "id"            => $contentData["id"],
                "title"         => $contentData["title"],
                "introImage"    => $introImage,
                "contentImages" => $contentImages,
                "created"       => $contentData["created"],
                "alias"         => $contentData["alias"],
                "fulltext"      => $contentData["fulltext"],
            ];
        }

        return [
            "contentData" => $contents
        ];
    }

    private function webPageData():array {
        $webPageData = new WebPageData();

        $this->authNeeded = false;

        if (!isset($this->postParams["domain"])) {
            $this->apiError(500, "missing parameter", "Missing parameter domain");
        }

        $domain = $this->postParams["domain"];
        if (!$domainData = sql_fetch_array(sql_query("select * from webpagedata where instr(domain, ?) limit 1", [$domain]))) {
            $this->apiError(500, "domain not found", "Domain not found!");
        }

        $pageParams = json_decode($domainData["params"], JSON_OBJECT_AS_ARRAY);
        foreach ($webPageData->params as $key => $paramData) {
            if (!isset($pageParams[$key])) {
                $pageParams[$key] = $webPageData->getOrokoltParam($domainData["parent"], $key, $paramData);
            }
            if ($paramData["type"] == "image") {
                $pageParams[$key] = $webPageData->getImageParam($key, $paramData["imagetype"], $domainData["id"]);
            }
        }

        $domainData["params"] = $pageParams;

        if ($pageParams["tipusid"] != 0) {
            $domainData["orvosok"] = sql_query("SELECT b.`orvosid`, o.nev FROM orvos_beosztas_new b
                LEFT JOIN orvosok o ON o.id=b.`orvosid` 
                WHERE INSTR(b.`tipusok`, ?) AND b.aktiv=1 AND o.pecsetszam<>'temp' AND TRIM(o.pecsetszam)<>'' and b.helyszinid=1 and b.aktiv=1
                and (b.nap<10 or (b.nap=10 and b.beonap>date_sub(now(), interval 7 day)))
                GROUP BY b.orvosid", ["|{$pageParams["tipusid"]}|"])->fetchAll(PDO::FETCH_ASSOC);

            $domainData["arak"] = sql_query("SELECT price, megnev FROM arak WHERE tipusid=? AND INSTR(cegid, '|243|')", [$pageParams["tipusid"]])->fetchAll(PDO::FETCH_ASSOC);
            $domainData["egeszsegpenztarak"] = sql_query("SELECT * FROM egeszsegpenztarak where aktiv=1 order by megnev")->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            "webpagedata" => $domainData,
        ];
    }

    private function getReservationPatients():array {
        //$this->authNeeded = false;

        $usersWithReservation = [];
        $userIds = [];

        $body = json_decode($this->postBody, JSON_OBJECT_AS_ARRAY);

        if (!isset($body["users"])) {
            $this->apiError(500, "missing parameter", "Missing parameter 'users'");
        }


        foreach ($body["users"] as $userId) {
            $userIds[] = intval($userId);
        }

        if (!empty($userIds)) {
            $ids = sql_query("select f.dokirex_userid from foglalasok f where f.dokirex_userid in (".implode(",", $userIds).") and datum>date_sub(now(), interval 1 week) and f.aktiv=1")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($ids as $id) {
                $usersWithReservation[] = intval($id["dokirex_userid"]);
            }
        }


        return [
            "usersWithReservation" => $usersWithReservation,
        ];
    }

    private function token():array {
        $this->authNeeded = false;

        //if (!isset($this->postParams["username"])) {
        //    print_r($_POST);
        //    echo "aaa".$this->postBody;die;

        //}

        if (isset($this->postParams["userName"])) {
            $this->postParams["username"] = $this->postParams["userName"];
        }

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
        if (!$this->authNeeded) {
            return true;
        }

        foreach (getallheaders() as $value) {
            if (substr_count($value, "Bearer ")) {
                $bearer = str_replace("Bearer ", "", $value);
                if ($this->tokenData = sql_fetch_array(sql_query("select * from tokens where token=? and expires>=now()", [$bearer]))) {
                    return true;
                }
            }
        }
        return false;
    }


    //A hívás a Szolgáltató adatbázisában rögzített AdMedes foglalások adatait adja vissza.
    private function reservationGet():array {
        $body = json_decode($this->postBody, JSON_OBJECT_AS_ARRAY);

        if (!$reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=? and pass=?", [intval($body["id"]), $body["authorizationCode"]]))) {
            $this->apiError(400, "E1003", "Reservation not found.");
        }

        return $this->_reservationArray($reservationData);
    }


    //A hívás a Szolgáltató adatbázisában rögzített AdMedes foglalások adatait adja vissza.
    private function reservationsGet():array {
        if (!empty($this->apiParam)) {
            $body = json_decode($this->postBody, JSON_OBJECT_AS_ARRAY);

            if (!$reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=? and pass=?", [intval($this->apiParam), $body["authorizationCode"]]))) {
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
        $locationId       = $this->postParams["locationId"] ?? Booking_Constants::DEFAULT_PLACE_IDS[0];
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
            $this->apiError(400, "E2002", "Invalid specializationId: {$body["specializationId"]}");
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
            "active" => $reservationData["aktiv"],
            "expiration" => $reservationData["expire"],
            "modifiedAt" => date("c", strtotime($reservationData["regdatum"])),
            "authorizationCode" => $reservationData["pass"],
        ];
    }

    //A hívás segítségével módosítható a Szolgáltató rendszerében rögzített foglalás. A foglalás adatai között
    //csak a pácienssel kapcsolatos adatok módosíthatók, illetve a megjegyzés.
    private function reservationsPut():array {
        if (!empty($this->apiParam)) {
            if ($reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=? and foglalta=?", [intval($this->apiParam), $this->tokenData["username"]]))) {

                $body = json_decode($this->postBody, JSON_OBJECT_AS_ARRAY);
                $this->_checkReservationBody($body);

                sql_query("update foglalasok set helyszinid=?, szurestipusid=?, orvosassigned=?, nev=?, telefon=?, email=?, szuldatum=?, anyjaneve=?, megj=?, aktiv=?, expire=? where id=? and pass=?",
                    [$body["locationId"], $body["specializationId"], $body["doctorId"], $body["patientName"], $body["patientPhone"], $body["patientEmail"], date("Y-m-d", strtotime($body["patientDateOfBirth"])), $body["patientMothersName"], $body["patientComment"], $body["active"], $body["expiration"], $reservationData["id"], $body["authorizationCode"]]);

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
    private function reservationDelete():array {
        if (!empty($this->apiParam)) {
            $body = json_decode($this->postBody, JSON_OBJECT_AS_ARRAY);

            if ($reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=? and pass=? and foglalta=?", [intval($body["id"]), $body["authorizationCode"], $this->tokenData["username"]]))) {
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
        $cegId              = $body["companyId"] ?? Booking_Constants::DEFAULT_COMPANY_ID;
        $taj                = $body["patientTaj"] ?? Utils::generateRandomString();
        $patientName        = $body["patientName"] ?? "nincs név";
        $patientPhone       = $body["patientPhone"] ?? "";
        $patientEmail       = $body["patientEmail"] ?? "";
        if (isset($body["patientDateOfBirth"])) {
            $patientDateOfBirth = date("Y-m-d", strtotime($body["patientDateOfBirth"]));
        } else {
            $patientDateOfBirth = "0000-00-00";
        }
        $patientMothersName = $body["patientMothersName"] ?? "";
        $patientComment     = $body["patientComment"] ?? "";
        $patientGender      = $body["patientGender"] ?? 0;
        $patientPostcode    = $body["patientPostcode"] ?? "0000";
        $patientCity        = $body["patientCity"] ?? "";
        $patientAddress     = $body["patientAddress"] ?? "";
        $paid               = $body["paid"] ?? 0;
        $expiration         = $body["expiration"] ?? "0000-00-00 00:00:00";
        $active             = $body["active"] ?? 1;

        if (empty($body["patientDateOfBirth"])) {
            $patientDateOfBirth = "0000-00-00";
        }
        if (empty($authorizationCode)) {
            $authorizationCode = md5($patientName.$patientEmail.date("YmdHis"));
        }

        //print_r($this->calculateSlots($nap, $nap, $locationId, $specializationId, $doctorId));die;
        foreach ($this->calculateSlots($nap, $nap, $locationId, $specializationId, $doctorId, $cegId) as $slot) {
            if (date("Y-m-d H:i", strtotime($slot["date"])) == $reservationDate) {
                $found = true;
                break;
            }
        }

        if (!isset($found)) {
            $this->apiError(400, "E1002", "The slot is already taken.");
        }

        if ($paciensData = sql_fetch_array(sql_query("select id from felhasznalok where createdby=? and email=? and nev=? limit 1", [$this->tokenData["username"], $patientEmail, $patientName]))) {
            $paciensId = $paciensData["id"];
        } else {
            sql_query("insert into felhasznalok set createdby=?, cegid=?, regtime=now(), nev=?, email=?, telefon=?, szuldatum=?, anyjaneve=?, taj=?, flang=?, rkod=?, validated=1",
                [$this->tokenData["username"], $cegId, $patientName, $patientEmail, $patientPhone, $patientDateOfBirth, $patientMothersName, $taj, "hu", rand(11000, 98000)]);
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
            "neme" => $patientGender,
            "taj" => $taj,
            "irsz" => $patientPostcode,
            "varos" => $patientCity,
            "utca" => $patientAddress,
            "megj" => $patientComment,
            "munkakor" => "",
            "tudoszuro" => 0,
            "lang" => "hu",
            "orvosid" => $doctorId,
            "aktiv" => $active,
            "paid" => $paid,
            "expire" => $expiration,
            "rn" => rand(1000000, 9999999)];

        $_REQUEST["rinterval"] = $data["rinterval"]; //fix

        $reservationId = $this->bookingService->addReservationQuery($data);

        //set authcode
        sql_query("update foglalasok set pass=?, foglalta=? where id=?", [$authorizationCode, $this->tokenData["username"], $reservationId]);

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
        $locationId       = $this->postParams["locationId"] ?? Booking_Constants::DEFAULT_PLACE_IDS[0];
        $specializationId = $this->postParams["specializationId"] ?? 0;
        $doctorId         = $this->postParams["doctorId"] ?? 0;
        $companyId        = $this->postParams["companyId"] ?? 0;

        return $this->calculateSlots($startDate, $endDate, $locationId, $specializationId, $doctorId, $companyId);
    }


    private function calculateSlots($startDate, $endDate, $locationId, $specializationId, $doctorId, $companyId):array {
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
        if ($companyId != 0) {
            $companyFilter = "AND INSTR(b.beocegek, '|".intval($companyId)."|')";
        } else {
            $companyFilter = "AND INSTR(b.beocegek, '|".intval(Booking_Constants::DEFAULT_COMPANY_ID)."|')";
        }

        $beoDatas = sql_query("SELECT b.*, o.id as orvosId, o.nev FROM orvos_beosztas_new b
            LEFT JOIN orvosok o ON o.id=b.orvosid
            WHERE ((nap = 10 AND beonap>=? AND beonap<=?) OR nap<10) AND nap<>0 {$specializationFilter} {$doctorFilter} AND b.helyszinid=? and b.aktiv=1 and b.noreservation=0 {$companyFilter} order by b.beonap", [date("Y-m-d", strtotime($startDate)), date("Y-m-d", strtotime($endDate)), $locationId])->fetchAll(PDO::FETCH_ASSOC);

        $szabadsagData = [];
        $szabadsagok = sql_query("select * from szabadsag where datumtol>=? {$doctorFilterSzabadsag}", [date("Y-m-d", strtotime($startDate))])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($szabadsagok as $szabadsag) {
            $szabadsagData[$szabadsag["oid"]][$szabadsag["datumtol"]] = 1;
        }

        $reservationTable = [];
        $reservations = sql_query("select datum, rinterval, orvosassigned from foglalasok where datum>? and datum<? {$doctorFilterReservation}", [date("Y-m-d 00:00:00", strtotime($startDate)), date("Y-m-d 23:59:59", strtotime($endDate))]);
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

                if (strtotime($nap) > strtotime($endDate)) {
                    continue;
                }

                //AND (b.validfrom='0000-00-00' OR b.validfrom<=:day) AND (b.validto='0000-00-00' OR b.validto>=:day)
                if (($beosztas["validfrom"] != "0000-00-00" && strtotime($beosztas["validfrom"]) > strtotime($nap)) || ($beosztas["validto"] != "0000-00-00" && strtotime($beosztas["validto"]) < strtotime($nap))) {
                    continue;
                }

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
                    //continue;
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
                            $timeTo = date("Y-m-d H:i:s", strtotime("{$timeFrom} + ".($binterval-1)." minute"));

                            if (strtotime($timeFrom) < strtotime("now")) {
                                continue;
                            }

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

        usort($slots, function($a, $b) {
            if ($a["date"] > $b["date"]) {
                return 1;
            } elseif ($a["date"] < $b["date"]) {
                return -1;
            }
            return 0;
        });

        return $slots;
    }

    private function dokirexInsertVizsglapAdatok():array {
        if (!empty($this->postBody)) {
            $data = json_decode($this->postBody, JSON_OBJECT_AS_ARRAY);

            sql_query("insert into dokirexvizsglaplog set VizsgalatiLapLogID=?, VizsgalatiLapID=?, PaciensID=?, FelhasznaloSzakrendelesID=?, Datum=?, Muvelet=?", [$data["VizsgalatiLapLogID"], $data["VizsgalatiLapID"], $data["PaciensID"], $data["FelhasznaloSzakrendelesID"], $data["Datum"], $data["Muvelet"]]);
        } else {
            $this->apiError(500, "Empty request", "The request was empty");
        }

        return ["{$this->apiMethod}" => "ok"];
    }


}