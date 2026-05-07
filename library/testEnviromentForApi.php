<?php

class testEnviromentForApi {

    const API_URL      = "https://bejelentkezes.hungariamed.hu/hmmapi";

    const API_USER     = "generali";
    const API_PASSWORD = "w(]1M56:yLgi";

    public string $token = "";
    public int $reservationTypeId = 0;
    public int $locationId = 0;
    public int $companyId = 745;
    public int $num = 0;
    public string $startDate = "";
    public string $endDate = "";



    public function __construct()
    {
        $this->getToken();
        $this->startDate = date("Y-m-d", strtotime("now"));
        $this->endDate = date("Y-m-d", strtotime("now + 1 week"));
    }


    public function getToken() {
        $result = $this->_apiCall("/token", "POST", "username=".SELF::API_USER."&grant_type=password&password=".urlencode(SELF::API_PASSWORD));
        if (isset($result["access_token"])) {
            $this->token = $result["access_token"];
        }
    }

    public function getSlots($startDate=null,$endDate=null) {
        if(!$startDate) $startDate = $this->startDate;
        if(!$endDate) $endDate = $this->endDate;

        return $this->_apiCall("/slots?startDate=".$startDate."&endDate=".$endDate."&locationId=".$this->locationId."&specializationId=".$this->reservationTypeId."&companyId=".$this->companyId);
    }


    private function _apiCall($action, $method = "GET", $postFields = "") {
        $headers = [];
        if ($method == "POST") {
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
        }
        if (!empty($this->token)) {
            $headers[] = "Authorization: Bearer {$this->token}";
        }

        $url = self::API_URL;

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url.$action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response, JSON_OBJECT_AS_ARRAY);
    }
}