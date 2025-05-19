<?php
class GeneraliApiService{

    const API_TEST_URL  = "https://release.medifoglalo.hu/api/obt";
    const Username      = "marton.gergely@hungariamed.hu";
    const Password      = "HrfDqh2m8mNqPKm";

    private $apiURL;
    private $token;

    function __construct()
    {
       $this->apiURL = self::API_TEST_URL;

        if (!isset($_SESSION["generalitoken"])) {
            $this->token = $this->loginTry();
            if (!empty($this->token)) {
                $_SESSION["generalitoken"] = $this->token;
            }
        } else {
            $this->token = $_SESSION["generalitoken"];
        }

    }

    public function loginTry()
    {
        $action = "/login";
        $params = json_encode(['username' => self::Username, 'password' => self::Password]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        //$this->log($action, $params, $response);

        curl_close($ch);


        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function specialities(){
        $action = "/specialities";

        echo $this->token["access_token"];

        $header = [
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json",
            "Authorization: ".$this->token["access_token"],
        ];

        $curl = curl_init();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }
}