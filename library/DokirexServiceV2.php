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

            return "Sikeres adatküldés! {$dokirexUserId}";
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


    public function listPaciens($skip=0,$take=10):string {
        $action = "/api/public/listPaciens";

        $params["token"] = $this->token;
        $params["skip"] = $skip;
        $params["take"] = $take;
        //$params["PaciensID"] = 9;
        $params["columns"] = [
            "PaciensID" => true,
            "Nev" => true,
            "Azonosito" => true,
            "AzonositoTipusID" => true,
            "SzuletesiDatum" => true,
            "SzuletesiHely" => true,
            "AnyjaNeve" => true,
            "NemID" => true,
            "SzuletesiNev" => true,
            "AllampolgarsagID" => true,
            "Telefon" => true,
            "Mobiltelefon" => true,
            "Iranyitoszam" => true,
            "Telepules" => true,
            "Cim" => true,
            "Email" => true,
            "SzigSzam" => true,
            "KozgyogyTol" => true,
            "KozgyogyIg" => true,
            "KozgyogySzam" => true,
            "FelvetelDatuma" => true,
            "UtolsoModositasDatuma" => true,
            "CegTelephelyID" => true,
            "Megjegyzes" => true,
            "PenztarID" => true,
            "TagKod" => true,
            "Biztosito" => true,
            "Orszag" => true,
            "StatusData" => true
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

    public function dokirexListPaciensInsert($startpoint=0,$quantity=1,$stepping=1){
        $x=$startpoint;
        $queryResult = array();
        for($i=$startpoint;$i<($quantity+$startpoint);$i=$i+$stepping){
            $listPaciens = json_decode($this->listPaciens($i,($stepping)),true);

            echo "<br><br><strong>Lekérdezett adat mennyiség: ".count($listPaciens["data"])."db</strong><br><br>";
            //Lefuttatom a dokirexből lekért állományi szegmenst
            foreach($listPaciens["data"] as $key=>$array){
                $data = $columns = array();
                //Tömbökbe szedem az oszlop neveket és a hozzá tartozó értéket
                foreach($array as $column=>$value){
                    $data[] = $value;
                    $columns[] = $column;
                }
                //Testreszabom az oszlop neveket, hogy illeszkedjen egy query hívásba stringesen.
                $queryFields = implode("=?,",$columns);

                

                //Insertelem az adatok az adatbázisba.
                sql_query("INSERT INTO dokirex_allomany SET {$queryFields}=?",$data);
                $x++;
                //echo "Adatok rögzítve! (#{$x}. - {$data[1]} - TAJ:{$data[2]})<br>";
            }
            $queryResult = array_merge($queryResult,$listPaciens["data"]);
        }
        return $queryResult;
    }

    public function dokirexListPaciensInsertLoop( $startpoint = 0, $quantity = 1000,$step = 100){
       $eventpoint = $size = 0;
       
        do{
            //Létrehozok egy eseménypontot, amit növelek a loop folyamán.
            if($eventpoint==0) $eventpoint = $startpoint;
            //Növelem a size értékét a lekérdezett adatokkal
            $size = $size + count($this->dokirexListPaciensInsert($eventpoint,$quantity,$step));
            //Lehívást követően növelem a eventpoint változót, hogy loopban tartsam a do-while-t,
            $eventpoint=($eventpoint+$quantity);
            //A eventpoint léptetését követően az $array és a változó mérete meg kell hogy egyezzen, ha végig tudott futni.
            if(($startpoint+$size)<$eventpoint){
                //Ha az $array értéke kisebb lesz, mint az új $startpoint akkor már hiányos volt az adatlekérdezés, szakítsa meg a loopot.
                echo "Sikeres megszakítás! (#{$size}.)<br>";
                break;
            }
        }while(($startpoint+$size)==$eventpoint);
    }

    public function listMunkakor():string {
        $action = "/api/public/listMunkakor";

        $params["token"] = $this->token;

        $params["Aktiv"] = true;

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

    public function listCegTelephelyByCegID($CegID):string {

        //if(empty($CegID)) return "";

        $action = "/api/public/listCegTelephelyByCegID";

        $params["token"] = $this->token;

        $params["CegID"] = $CegID;

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
        $action = "/api/public/listFelhasznaloSzakrendeles?Tipus=0";

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
        $params = sql_fetch_array(sql_query("SELECT fogl.id as fid, TRIM(fogl.nev) AS 'Nev', fogl.taj AS 'Azonosito', '2' AS 'AzonositoTipusID',fogl.szuldatum AS 'SzuletesiDatum', 
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
