<?php


class DokirexService {
    private string $apiURL = "http://api-v2.dokirex.hu";

    private $token;
    public int $version = 2;
    public array $requiredUserParams = ["Nev", "SzuletesiDatum", "Azonosito", "Nem", "SzuletesiNev"];

    const LOG_ID = 33;

    private array $defaultParams = [
        "Nem" => 3,
        "Allampolgarsag" => 109,
        "Orszag" => 109
    ];

    private string $dbName;
    private string $dbEmail;
    private string $dbPassword;

    public function __construct() {
        $this->dbName = Booking_Constants::DOKIREX_V2_DB;
        $this->dbEmail = Booking_Constants::DOKIREX_V2_EMAIL;
        $this->dbPassword = Booking_Constants::DOKIREX_V2_PASSWORD;

        if (isset($_REQUEST["config"]) && $_REQUEST["config"] == "hmm") {
            $this->dbName = Booking_Constants::DOKIREX_V2_HMM_DB;
            $this->dbEmail = Booking_Constants::DOKIREX_V2_HMM_EMAIL;
            $this->dbPassword = Booking_Constants::DOKIREX_V2_HMM_PASSWORD;
        }

        if (isset($_REQUEST["config"]) && $_REQUEST["config"] == "keltexmed") {
            $this->dbName = Booking_Constants::DOKIREX_V2_KELTEXMED_DB;
            $this->dbEmail = Booking_Constants::DOKIREX_V2_KELTEXMED_EMAIL;
            $this->dbPassword = Booking_Constants::DOKIREX_V2_KELTEXMED_PASSWORD;
        }

        $this->token = $this->getToken();
    }

    public function insertPaciensIntoDokirex($params) {
        //Ellenőrzés, hogy a páciens adat tömb nem üres-e, ha igen akkor hagyja félbe a folyamatot.
        if (empty($params)) {
            exit;
        }

        $action = "/api/public/insertUpdatePaciens";
        //További adatok a service-ből:
        $params["token"] = $this->token;
        $params["dbName"] = $this->dbName;

        //Alapértelmezett adatok beillesztése a paraméterekbe, ha nem lettek volna deklarálva.
        foreach ($this->defaultParams as $index => $value) {
            if (!isset($params[$index]) || ($params[$index] == "" && $params[$index] == null)) {
                $params[$index] = $value;
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL.$action);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $this->log($action, json_encode($params), $response);

        curl_close($ch);

        $response = json_decode($response, JSON_OBJECT_AS_ARRAY);

        if ($response["message"] == "OK") {
            $dokirexUserId = reset($response["data"][0]);
            if (!isset($_REQUEST["config"])) {
                sql_query("update foglalasok set dokirex_userid=? where id=? limit 1", [$dokirexUserId, $params["fid"]]);
            }

            return "Sikeres adatküldés!";
        } else {
            return $response;
        }
    }

    private function getToken() {
        $action = "/api/auth/login";
        $params = json_encode(['email' => $this->dbEmail, 'password' => $this->dbPassword]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL.$action);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $this->log($action, $params, $response);

        curl_close($ch);


        $response = json_decode($response, JSON_OBJECT_AS_ARRAY);

        if ($response["status"] == 1 && $response["message"] == "OK") {
            return $response["data"]["token"];
        }
    }


    private function log($action, $params, $response) {
        sql_query("insert into webservicelog set datum=now(), action=?, tipus=?, postkeres=?, response=?", [$action, self::LOG_ID, $params, $response]);
    }


    public function listPaciens():string {
        $action = "/api/public/listPaciens";

        $params["token"] = $this->token;
        $params["skip"] = 0;
        $params["take"] = 10;
        //$params["PaciensID"] = 9;
        $params["columns"] = [
            "PaciensID" => true,
            "Nev" => true,
            "Azonosito" => false,
            "AzonositoTipusID" => false,
            "SzuletesiDatum" => false,
            "SzuletesiHely" => false,
            "AnyjaNeve" => false,
            "NemID" => false,
            "SzuletesiNev" => false,
            "AllampolgarsagID" => false,
            "Telefon" => false,
            "Mobiltelefon" => false,
            "Iranyitoszam" => false,
            "Telepules" => false,
            "Cim" => false,
            "Email" => false,
            "SzigSzam" => false,
            "KozgyogyTol" => false,
            "KozgyogyIg" => false,
            "KozgyogySzam" => false,
            "FelvetelDatuma" => false,
            "UtolsoModositasDatuma" => false,
            "CegTelephelyID" => false,
            "Megjegyzes" => false,
            "PenztarID" => false,
            "TagKod" => false,
            "Biztosito" => false,
            "Orszag" => false,
            "StatusData" => false
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL.$action);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $this->log($action, json_encode($params), $response);

        curl_close($ch);

        return $response;
    }


    public function listFelhasznaloSzakrendeles():string {
        $action = "/api/public/listFelhasznaloSzakrendeles?Tipus=2";

        $params["token"] = $this->token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL.$action);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $this->log($action, json_encode($params), $response);

        curl_close($ch);

        return $response;
    }

    public function getPaciensByID($id):string {
        $action = "/api/public/getPaciensByID";
        //További adatok a service-ből:
        $params["token"] = $this->token;
        $params["PaciensID"] = $id;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL.$action);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $this->log($action, json_encode($params), $response);

        curl_close($ch);

        return $response;
    }


    public function getUserParamsFromReservation($reservationId) {
        $params = sql_fetch_array(sql_query("SELECT fogl.id as fid, fogl.nev AS 'Nev', fogl.taj AS 'Azonosito', '2' AS 'AzonositoTipusID',fogl.szuldatum AS 'SzuletesiDatum', 
                                                    fogl.szulhely AS 'SzuletesiHely', fogl.anyjaneve AS 'AnyjaNeve', CASE WHEN fogl.neme = 0 THEN 3 ELSE fogl.neme END AS 'NemID',
                                                    fogl.nev AS 'SzuletesiNev', '109' AS 'AllampolgarsagID', fogl.telefon AS 'Telefon', fogl.telefon AS 'Mobiltelefon',
                                                    fogl.irsz AS 'Iranyitoszam', fogl.varos AS 'Telepules', fogl.utca AS 'Cim', 
                                                    fogl.email AS 'Email', null AS 'SzigSzam', null AS 'KozgyogyTol', null AS 'KozgyogyIg', null AS 'KozgyogySzam', 
                                                    '3' AS 'FelvevoID', '3' AS 'UtolsoModositoID'                                            
                                            FROM foglalasok fogl WHERE id=?", [$reservationId]));

        $params["SzuletesiDatum"] = str_replace(".", "", $params["SzuletesiDatum"]);
        $params["SzuletesiDatum"] = str_replace("-", "", $params["SzuletesiDatum"]);
        $params["SzuletesiDatum"] = substr($params["SzuletesiDatum"], 0, 4) . "-" . substr($params["SzuletesiDatum"], 4, 2) . "-" . substr($params["SzuletesiDatum"], 6, 2);

        return $params;
    }


    public function checkUserParamErrors($params) {
        $error = [];
        foreach ($params as $index => $value) {
            if ($value == "" && in_array($index, $this->requiredUserParams)) {
                $error[] = "<span style='color:red'>*{$index} mező megadása kötelező!</span>";
            }
        }
        return $error;
    }
}
