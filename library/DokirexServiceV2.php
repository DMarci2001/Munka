<?php


class DokirexService {
    private $apiURL = "http://api-v2.dokirex.hu";

    private $token;
    public $version = 2;

    const LOG_ID = 33;

    private $defaultParams = [
        "Nem" => 3,
        "Allampolgarsag" => 109,
        "Orszag" => 109
    ];

    private $dbName;
    private $dbEmail;
    private $dbPassword;

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
}
