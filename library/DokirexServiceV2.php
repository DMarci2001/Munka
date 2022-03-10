<?php


class DokirexService {
    private $apiURL = "http://api-v2.dokirex.hu";

    private $token;
    public $version = 2;

    private $defaultParams = [
        "Nem" => 3,
        "Allampolgarsag" => 109,
        "Orszag" => 109
    ];

    private $dbName;
    private $dbEmail;
    private $dbPassword;

    public function __construct()
    {
        $this->dbName = Booking_Constants::DokiRex_dbName;
        $this->dbEmail = Booking_Constants::DokiRex_Email;
        $this->dbPassword = Booking_Constants::DokiRex_Password;

        if (isset($_REQUEST["config"]) && $_REQUEST["config"] == "hmm") {
            $this->dbName = Booking_Constants::DokiRex_HMM_dbName;
            $this->dbEmail = Booking_Constants::DokiRex_HMM_Email;
            $this->dbPassword = Booking_Constants::DokiRex_HMM_Password;
        }

        $this->token = $this->getToken();


        echo "<div class='loginbox' style='width:800px;padding:10px;'>".print_r($this->token, true)."</div>";
        die;
    }

    public function insertPaciensIntoDokirex($params = array()) {
        //Ellenőrzés, hogy a páciens adat tömb nem üres-e, ha igen akkor hagyja félbe a folyamatot.
        if (empty($params)) {
            exit;
        }

        //További adatok a service-ből:
        $params["token"] = $this->token;
        $params["dbName"] = $this->dbName;

        //Alapértelmezett adatok beillesztése a paraméterekbe, ha nem lettek volna deklarálva.
        foreach ($this->defaultParams as $index => $value) {
            if (!isset($params[$index]) || ($params[$index] == "" && $params[$index] == null)) {
                $params[$index] = $value;
            }
        }

        $curl = curl_init();
        //Régi: insertUpdatePaciens
        //Új: insertPaciens
        curl_setopt_array($curl, array(
            CURLOPT_URL => "api.dokirex.hu/insertPaciens",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $params,
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($response, true);

        if ($response["message"] == "OK") {
            return "Sikeres adatküldés! ({$response["data"]})";
        } else return $response;

        exit;
    }

    public function runBuiltInQuery($data)
    {
        $curl = curl_init();

        //Body-ba tartozó paraméterek a lekérdezés felépítéséhez:
        //-->Param4-re jelenleg semmilyen okból nincs szükségem, ezért
        $fields = array(
            "token" => $this->token,
            "StoredProcedure" => $data["runBuiltInQuery"],
            "Param1" => (isset($data["Param1"])) ? $data["Param1"] : "-1",
            "Param2" => (isset($data["Param2"])) ? $data["Param2"] : null,
            "Param3" => (isset($data["Param3"])) ? $data["Param3"] : null,
            "Param4" => (isset($data["Param4"])) ? $data["Param4"] : null,
            "Param5" => (isset($data["Param5"])) ? $data["Param5"] : null,
            "Param6" => (isset($data["Param6"])) ? $data["Param6"] : null,
            "Param7" => (isset($data["Param7"])) ? $data["Param7"] : null,
            "Param8" => (isset($data["Param8"])) ? $data["Param8"] : null,
            "Param9" => (isset($data["Param9"])) ? $data["Param9"] : null,
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'api.dokirex.hu/runBuiltInQuery',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }

    public function test_run()
    {

        $params = array(
            "token" => $this->token,
            "Nev" => "Teszt páciens",
            "Azonosito" => "0123456789",
            "AzonositoTipusID" => '2',
            "SzuletesiDatum" => "1994-09-23",
            "SzuletesiHely" => "Vác",
            "AnyjaNeve" => "Kovács Ildikó",
            "NemID" => '3',
            "SzuletesiNev" => "Márton Gergely",
            "AllampolgarsagID" => '109',
            "Telefon" => "0630606922",
            "Mobiltelefon" => "0630606922",
            "Iranyitoszam" => "2162",
            "Telepules" => "Őrbottyán",
            "Cim" => "Puskás Ferenc u. 74",
            "Email" => "m.gergely9409@gmail.com",
            "SzigSzam" => null,
            "KozgyogyTol" => null,
            "KozgyogyIg" => null,
            "KozgyogySzam" => null,
            "FelvevoID" => '3',
            "UtolsoModositoID" => '3',


            "dbName" => $this->dbName
        );

        echo "<pre>";
        print_r($params);
        echo "</pre>";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "api.dokirex.hu/insertPaciens",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $params,
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($response, true);

        echo "<pre>";
        print_r($response);
        echo "</pre>";
    }

    private function getToken() {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiURL."/api/auth/login",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => ['email' => $this->dbEmail, 'password' => $this->dbPassword],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($response, true);

        echo "<div class='loginbox' style='width:800px;padding:10px;'>".print_r($response, true)."</div>";
        die;

        if ($response["status"] == 1 && $response["message"] == "OK") {
            return $response["data"]["token"];
        }
    }
}
