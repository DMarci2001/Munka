<?php


class DokirexService
{
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

    public function __construct()
    {
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

    public function insertPaciensIntoDokirex($params)
    {
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
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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

    private function getToken()
    {
        $action = "/api/auth/login";
        $params = json_encode(['email' => $this->dbEmail, 'password' => $this->dbPassword]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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


    private function log($action, $params, $response)
    {
        sql_query("insert into webservicelog set datum=now(), action=?, tipus=?, postkeres=?, response=?", [$action, self::LOG_ID, $params, $response]);
    }


    public function listPaciens($skip = 0, $take = 10): string
    {
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
            "Nem" => true,
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
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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



    public function dokirexListPaciensInsert($startpoint = 0, $quantity = 1)
    {
        $count = 0;

        $retry = 0;
        while (true) {
            $listPaciens = json_decode($this->listPaciens($startpoint, $quantity), true);
            print_r($listPaciens);
            die;
            if (isset($listPaciens["data"])) {
                break;
            }
            if ($retry++ > 10) {
                break;
            }
            sleep(1);
        }

        echo "\n<strong>Lekérdezett adat mennyiség: " . count($listPaciens["data"]) . "db</strong>\n\n";
        //Lefuttatom a dokirexből lekért állományi szegmenst
        foreach ($listPaciens["data"] as $key => $array) {
            $data = $columns = array();
            //Tömbökbe szedem az oszlop neveket és a hozzá tartozó értéket
            foreach ($array as $column => $value) {
                $data[] = $value;
                $columns[] = $column;
            }
            //Testreszabom az oszlop neveket, hogy illeszkedjen egy query hívásba stringesen.
            $queryFields = implode("=?,", $columns);

            //Insertelem az adatok az adatbázisba.
            sql_query("INSERT INTO dokirex_allomany SET {$queryFields}=?", $data);
            $count++;
            echo "Adatok rögzítve! (#" . ($startpoint + $count) . ". - {$data[1]} - TAJ:{$data[2]})\n";
        }

        return $count;
    }

    public function dokirexListPaciensInsertLoop($startpoint = 0, $quantity = 100)
    {
        sql_query("truncate table dokirex_allomany");
        while ($this->dokirexListPaciensInsert($startpoint, $quantity) == $quantity) {
            $startpoint += $quantity;
        }
        //felesleges log törlése
        sql_query("DELETE FROM webservicelog WHERE action='/api/public/listPaciens'");
    }

    public function listMunkakor(): string
    {
        $action = "/api/public/listMunkakor";

        $params["token"] = $this->token;

        $params["Aktiv"] = true;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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

    public function updateListMunkakor($onlyInsert = true, $returnLog = false)
    {
        //Futó folyamat ellenőrzése:
        $q = sql_query("SELECT * FROM activitylog WHERE tipus IN(\"munkakorlista_update_started\",\"munkakorlista_update_finished\") ORDER BY datum DESC LIMIT 1");
        $updateStatusz = sql_fetch_array($q);
        if ($updateStatusz["tipus"] == "munkakorlista_update_started") {
            return "Munkakörlista frissítése folyamatban van!";
        } else {
            sql_query("INSERT INTO activitylog SET datum = NOW(), tipus = \"munkakorlista_update_started\", megnev =\"Dokirex munkakörlista manuális frissítés kezdése\"");
        }

        //Legnagyobb munkakorid kinyerése:
        $lastRow = sql_fetch_array(sql_query("SELECT * FROM dokirex_munkakorok_new ORDER BY MunkakorID DESC LIMIT 1"));

        $log = array();
        $results = json_decode($this->listMunkakor(), true);


        foreach ($results["data"] as $data) {

            //Ha a munkakörid nagyobb mint a legnagyobb rögzített érték, akkor insertelje
            if ($data["MunkakorID"] > $lastRow["MunkakorID"]) {
                sql_query(
                    "INSERT INTO dokirex_munkakorok_new SET MunkakorID=?, FelvetelDatuma=?, Aktiv=?, Nev=?",
                    array($data["MunkakorID"], $data["FelvetelDatuma"], $data["Aktiv"], $data["Nev"])
                );
            } 
            //Ha csak insertelni akarok, akkor az update ágat skippeljük.
            if ($onlyInsert) {
                continue;
            }

            //umm, update?
            sql_query(
                "UPDATE dokirex_munkakorok_new SET Aktiv=?, Nev=? WHERE MunkakorID =?",
                array($data["Aktiv"], $data["Nev"], $data["MunkakorID"])
            );
            

            array_push($log, "MunkakorID:{$data["MunkakorID"]} has been inserted!");
        }

        if ($returnLog == true) {
            echo "<pre>";
            print_r($log);
            echo "</pre>";
        }

        sql_query("INSERT INTO activitylog SET datum = NOW(), tipus = \"munkakorlista_update_finished\", megnev = \"Dokirex munkakörlista manuális frissítés befejezve\"");

        return;
    }

    public function updateListCegek($onlyInsert = true)
    {
         //Futó folyamat ellenőrzése:
         $q = sql_query("SELECT * FROM activitylog WHERE tipus IN(\"ceglista_update_started\",\"ceglista_update_finished\") ORDER BY datum DESC LIMIT 1");
         $updateStatusz = sql_fetch_array($q);
         if ($updateStatusz["tipus"] == "ceglista_update_started") {
             return "Céglista frissítése folyamatban van!";
         } else {
             sql_query("INSERT INTO activitylog SET datum = NOW(), tipus = \"ceglista_update_started\", megnev =\"Dokirex ceglista manuális frissítés kezdése\"");
         }

         $lastRow = sql_fetch_array(sql_query("SELECT * FROM dokirex_telephelyek ORDER BY TelephelyID DESC LIMIT 1"));
         $results = json_decode($this->listCegekWithTelephelyek(),true);
         
         foreach($results["data"] as $telephely){

             $telephely["Nev"] = explode(" - ",$telephely["Nev"],2);

             if ($telephely["TelephelyID"] > $lastRow["TelephelyID"]) {
                 sql_query(
                     "INSERT INTO dokirex_telephelyek SET TelephelyID=?, CegNev =?, TelephelyNev=?, last_update = NOW()",
                     array($telephely["TelephelyID"], $telephely["Nev"][0], $telephely["Nev"][1])
                 );

                 echo "Inserted partner! ( {$telephely["TelephelyID"]} - {$telephely["Nev"][0]}: {$telephely["Nev"][1]})<br>";
             }

             //Ha csak insertelni akarok, akkor az update ágat skippeljük.
             if ($onlyInsert) {
                 continue;
             }

             if(!$q=sql_fetch_array(sql_query("SELECT * FROM dokirex_telephelyek WHERE TelephelyID = ?",array($telephely["TelephelyID"])))){
                sql_query(
                    "INSERT INTO dokirex_telephelyek SET TelephelyID=?, CegNev =?, TelephelyNev=?, last_update = NOW()",
                    array($telephely["TelephelyID"], $telephely["Nev"][0], $telephely["Nev"][1])
                );

                echo "Inserted partner! ( {$telephely["TelephelyID"]} - {$telephely["Nev"][0]}: {$telephely["Nev"][1]})<br>";
             }

             //umm, update?
             sql_query(
                 "UPDATE dokirex_telephelyek SET TelephelyNev=?, CegNev =?, last_update = NOW() WHERE TelephelyID =?",
                 array($telephely["Nev"][0], $telephely["Nev"][1], $telephely["TelephelyID"])
             );
             echo "Updated partner! ( {$telephely["TelephelyID"]} - {$telephely["Nev"][0]}: {$telephely["Nev"][1]})<br>";             
         }

         sql_query("INSERT INTO activitylog SET datum = NOW(), tipus = \"ceglista_update_finished\", megnev = \"Dokirex céglista manuális frissítés befejezve\"");

         return;
    }

    public function sqlListMunkakor($search = "", $onlySearch = true)
    {
        $array["results"] = array();

        if ($onlySearch && empty($search)) return $array;

        $q = sql_query("SELECT * FROM dokirex_munkakorok_new 
                        WHERE " . ((!empty($search)) ? "Nev LIKE '%{$search}%' " : "TRUE")." 
                        GROUP BY Nev
                        " . ((!empty($search)) ? "ORDER BY LEFT(Nev, ".strlen($search).")=\"{$search}\" DESC, Nev" : "ORDER BY Nev"), 
                        array($search));
        while ($result = sql_fetch_array($q)) {
            array_push($array["results"], array("id" => $result["MunkakorID"], "text" => $result["Nev"]));
        }

        return $array;
    }

    public function sqlListTelephely($search = "", $onlySearch = true)
    {
        $array["results"] = array();

        if ($onlySearch && empty($search)) return $array;

        $q = sql_query("SELECT * FROM dokirex_telephelyek WHERE " . ((!empty($search)) ? "(TelephelyNev LIKE '%{$search}%' OR CegNev LIKE '%{$search}%') " : "TRUE"), array($search));
        while ($result = sql_fetch_array($q)) {
            if(strpos($result["TelephelyNev"],$result["CegNev"])!==false){
            //if($result["TelephelyNev"] == $result["CegNev"]){
                $megnev = $result["TelephelyNev"];
            }else{
                $megnev = $result["CegNev"]." - ".$result["TelephelyNev"];
            }
            array_push($array["results"], array("id" => $result["TelephelyID"], "text" => $megnev));
        }

        return $array;
    }

    public function listCegTelephelyByCegID($CegID): string
    {

        //if(empty($CegID)) return "";

        $action = "/api/public/listCegTelephelyByCegID";

        $params["token"] = $this->token;

        $params["CegID"] = $CegID;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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

    public function listCegekWithTelephelyek():string{
        $action = "/api/public/listCegekWithTelephelyek";

        $params["token"] = $this->token;

        //$params["CegID"] = $CegID;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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

    public function insertUpdateFormElementValue($params = array()):string{
        $action = "/api/public/insertUpdateFormElementValue";

        $params["token"] = $this->token;


        //$params["CegID"] = $CegID;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $this->log($action, json_encode($params = array()), $response);

        curl_close($ch);

        return $response;
    }

    public function listPaciensByParams ($params): string
    {
        $action = "/api/public/listPaciensByParams";

        $params["token"] = $this->token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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


    public function listFelhasznaloSzakrendeles(): string
    {
        $action = "/api/public/listFelhasznaloSzakrendeles?Tipus=0";

        $params["token"] = $this->token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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

    public function getPaciensByID($id): string
    {
        $action = "/api/public/getPaciensByID";
        //További adatok a service-ből:
        $params["token"] = $this->token;
        $params["PaciensID"] = $id;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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

    public function listCeg(): string
    {
        $action = "/api/public/listCeg";

        $params["token"] = $this->token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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

    public function listCegTelephelyByCeg():string
    {
        $action = "/api/public/listCegID";

        $params["token"] = $this->token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
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


    public function getUserParamsFromReservation($reservationId)
    {
        $params = sql_fetch_array(sql_query("SELECT fogl.id as fid, TRIM(fogl.nev) AS 'Nev', fogl.taj AS 'Azonosito', '2' AS 'AzonositoTipusID',fogl.szuldatum AS 'SzuletesiDatum', 
                                                    fogl.szulhely AS 'SzuletesiHely', fogl.anyjaneve AS 'AnyjaNeve', CASE WHEN fogl.neme = 0 THEN '1' ELSE fogl.neme END AS 'Nem',
                                                    fogl.nev AS 'SzuletesiNev', '109' AS 'Allampolgarsag', fogl.telefon AS 'Telefon', fogl.telefon AS 'Mobiltelefon',
                                                    fogl.irsz AS 'Iranyitoszam', fogl.varos AS 'Telepules', fogl.utca AS 'Cim', 
                                                    fogl.email AS 'Email', null AS 'SzigSzam', null AS 'KozgyogyTol', null AS 'KozgyogyIg', null AS 'KozgyogySzam', 
                                                    '3' AS 'FelvevoID', '3' AS 'UtolsoModositoID'                                            
                                            FROM foglalasok fogl WHERE id=?", [$reservationId]));

        $params["SzuletesiDatum"] = str_replace(".", "", $params["SzuletesiDatum"]);
        $params["SzuletesiDatum"] = str_replace("-", "", $params["SzuletesiDatum"]);
        $params["SzuletesiDatum"] = substr($params["SzuletesiDatum"], 0, 4) . "-" . substr($params["SzuletesiDatum"], 4, 2) . "-" . substr($params["SzuletesiDatum"], 6, 2);

        return $params;
    }


    public function checkUserParamErrors($params)
    {
        $error = [];
        foreach ($params as $index => $value) {
            if ($value == "" && in_array($index, $this->requiredUserParams)) {
                $error[] = "<span style='color:red'>*{$index} mező megadása kötelező!</span>";
            }
        }
        return $error;
    }
}
