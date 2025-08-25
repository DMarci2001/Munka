<?php

class BookingPage extends CorePage
{

    const AUCHAN_WARN = "Figyelem, az Auchan munkatársi szűrőprogram időpontjainak foglalása 2023.09.18. 9:00 órától lehetséges!";

    private BookingService $bookingService;
    public array $paymentMethods = [
        "utanvet" => "Utánvét",
        "simplepay" => "SimplePay"
    ];

    private array $telephelyek = [];
    private array $selectedVizsgalatok = [];

    private int $numberOfTimes = 1;

    public function __construct()
    {
        parent::__construct();

        //$this->utils->ghc_notification_send();

        unset($_SESSION["selectedService"]);

        if ($_SESSION["helyszindata"]["onlyreg"] == 1 && !isset($_SESSION["user"])) {

        }

        /**
         * Erre az oldalra HTML, Javascript inject védelemet raktam be.
        */
        $_POST = $this->utils->sanitize_array($_POST);
        $_GET  = $this->utils->sanitize_array($_GET);


        $_POST = $this->utils->sanitize_array($_POST);
        $_GET  = $this->utils->sanitize_array($_GET);

        //unset($_SESSION["cartTimes"]);

        $this->bookingService = new BookingService();
        $webText = $this->lang->webText;

        $this->detectServiceSelect();

        if (CompanyService::isBME() && empty($_POST["helyszin"])) {
            $_POST["helyszin"] = 644; // 644: bercsény, 100:fehérvári
        }

        $this->telephelyek = sql_query("select * from cegvars where cegid=? and (placeids<>'' or selectable=0) order by sorrend, megnev", [$_SESSION["helyszindata"]["id"]])->fetchAll(PDO::FETCH_ASSOC);

        if (isset($_GET["labcode"])) {
            //labshopból érkezés, labor foglaláshoz irányítás
            //https://bejelentkezes.hungariamed.hu/index.php?page=booking&labcode=ccb124b499f1a0d372e49adfe3fc18c3161913b92a32805ba8751ab0b345e354
            $_SESSION["labcode"] = $_GET["labcode"];
            header("location:index.php?page=booking&szurestipus=48&helyszin=1");
            die;
        }

        if (isset($_GET["keltexmedhuorder"])) {
            $keltexmedWebService = new KeltexMedWebSQL();
            $keltexmedWebService->loadWebShopOrder();
        }


        if (isset($_GET["showpaciensfiles"])) {
            echo $this->utils->showPaciensFiles();
            die();
        }

        if (isset($_POST["deletepaciensfile"])) {
            $docAgent = new DocAgent();
            $docAgent->deleteDoc($_POST["id"], $_POST["k"]);
            echo $this->utils->showPaciensFiles();
            die();
        }

        if (isset($_POST["displaySlots"])) {
            $reservationService = new ReservationService();
            $reservationService->companyId = intval($_POST["companyId"]) ?? Booking_Constants::DEFAULT_COMPANY_ID;
            $reservationService->reservationTypeId = $_POST["reservationTypeId"];
            $reservationService->cartRow = $_POST["cartRow"] ?? 0;
            $reservationService->num = $_POST["num"] ?? 0;
            echo $reservationService->displaySlots();
            die;
        }

        if (isset($_POST["selectSubTime"])) {
            $reservationTypeId = $_POST["reservationTypeId"];
            $mainServiceId = $_POST["mainServiceId"];
            //$_POST["registered"] = date("Y-m-d H:i:s");

            $_SESSION["cartTimes"][$reservationTypeId] = $_POST;

            echo $this->bookingService->getInfoPageText($mainServiceId);
            die;
        }

        if(isset($_POST["uniqaEmailCheck"])){
           if($_POST["email"]!=null){
               $freeBooking = array(157,158,159);
               $blacklistScenario = $alreadyBookedForFreeScenario = $isFree = $companyEmail = false;
                //Ha feketelistás:
               if($blacklisted=sql_fetch_array(sql_query("SELECT * FROM uniqa_blacklist WHERE email=?",array($_POST["email"])))){
                   $blacklistScenario = true;
               }

               //Le kell csekkolnom, ha már foglalt ingyenesre, ha igen, akkor fusson ebbe bele, ez az üzi azoknak szól akik nem fekete listásak O.o: 
               /*if($alreadybookedforfree=sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE email=? AND szurestipusid IN(158,159,157) AND datum BETWEEN '2022-05-31 00:00:00' AND '2022-05-31 23:59:59'",array($_POST["email"])))){
                $alreadyBookedForFreeScenario = true;
               }*/

               //Megkell nézzem milyen vizsgálatra jelentkezik:
               if(in_array($_POST["szurestipus"],$freeBooking)){
                $isFree=true;
               }

               if (preg_match('/@uniqa.hu|@uniqa.net/i', $_POST["email"])){
                   $companyEmail=true;
               }
              
           }
           $this->utils->jsonOut(array("blacklistScenario" => $blacklistScenario, "alreadyBookedForFreeScenario" => $alreadyBookedForFreeScenario, "isFree"=>$isFree, "companyEmail"=> $companyEmail));
           die();
           //die();
        }

        if (isset($_REQUEST["addpaciensfiles"])) {
            if (!isset($_SESSION["filefix"])) $_SESSION["filefix"] = rand(10000, 99999);
            $fileFix = $_SESSION["filefix"];

            $docAgent = new DocAgent();

            foreach ($_FILES as $file) {
                $sess = $fileFix . session_id();
                $result = $docAgent->saveDoc($file, array('beutaloid' => 0, 'userid' => 0, 'megnev' => '', 'sess' => $sess));
                if ($result != "0") {
                    echo $result;
                    die;
                }
            }
            die();
        }

        if (isset($_GET["helyszinrefresh"])) {
            $_POST["szurestipus"] = intval($_GET["helyszinrefresh"]);
            echo $this->_reservationPlaceSelectorNew();
            die;
        }

        if (isset($_GET["clearselecteddoctor"])) {
            $_SESSION["orvosselected"] = 0;
            die;
        }

        if (isset($_GET["remotereserve"])) {
            if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where id=? and rkod=?", array($_GET["fid"], $_GET["fkod"])))) {
                $_SESSION["remotebeutalo"] = $_GET["remotereserve"];
                $_SESSION["loggeduser"] = $rowu["id"];
                header("location:index.php?setbeutalo=" . intval($_GET["remotereserve"]));
                die();
            }
        }

        if (isset($_GET["setbeutalo"])) {
            if ($row = sql_fetch_array(sql_query("select * from beutalok where id=? and userid=?", array($_GET["setbeutalo"], $_SESSION["user"]["id"])))) {
                $_SESSION["beutaloid"] = $row["id"];
            }
            header("location:index.php?page=booking");
            die();
        }

        if(isset($_POST["setSzurestipusValaszto"])){

           /*
           Férfi: 1, Nő: 2
           A kor meghatározás: 45+-

           Meg kell találjam a cég alapján az elérhető csomagokat
           */
          $today = date("Y-m-d");
          $diff = date_diff(date_create($_POST["szuldatum"]), date_create($today));
          $kor = $diff->format("%y");

          //Ha 2024.08.31-ig betölti a 45-öt akkor már vegyük 45-nek xd Miki kérése volt.
          if($kor==44){
            if(date("Y-m-d",strtotime($_POST["szuldatum"]))<=date("Y-m-d",strtotime("1979-08-31"))){
                $kor=45;
            }
          }

            $tipusok = [];
            $reqTipusok=sql_query("SELECT beo.tipusok FROM orvos_beosztas_new beo
                                   WHERE INSTR(beo.beocegek,\"|{$_SESSION["helyszindata"]["id"]}|\")");

            while($resTipusok=sql_fetch_array($reqTipusok)){
                $resTipusok["tipusok"] = substr($resTipusok["tipusok"], 1, -1);
                $resTipusok["tipusok"] = explode("|",$resTipusok["tipusok"]);

                $tipusok = array_merge($tipusok,$resTipusok["tipusok"]);
            }
            $tipusok = array_values(array_filter(array_unique($tipusok)));

            
            $q=sql_query("SELECT sz.id,sz.megnev,sz.recommendedage,sz.recommendedgender,sz.recommendedageassist FROM szurestipusok sz 
                          WHERE sz.id IN(".implode(",",$tipusok).") AND ispack=1 
                          GROUP BY sz.id");
            while($res=sql_fetch_array($q)){
                if($res["recommendedgender"]==$_POST["neme"]){
                    if(!empty($res["recommendedage"])){
                       if($this->ifStatement("{$kor}{$res["recommendedage"]}",$kor.$res["recommendedage"])){
                        $firstStatement=true;
                        $szurestipusId=$res["id"];
                       }else{
                        $firstStatement=null;
                       }
                       if(!empty($res["recommendedageassist"])){
                        if($this->ifStatement("{$kor}{$res["recommendedageassist"]}",$kor.$res["recommendedageassist"])){
                            if($firstStatement==true){
                                $szurestipusId=$res["id"];
                            }else{
                                $szurestipusId=null;
                            }
                        }
                       }
                    }
                }
            }
            
            die(json_encode(["szurestipusValaszto"=>$this->_szuresTipusValasztoNew($szurestipusId),
                            "helyszinValaszto"=>$this->_reservationPlaceSelectorNew($szurestipusId),
                            "id"=>$szurestipusId,
                            "notification"=>$this->setNotificatitonForPackage($szurestipusId)]));
        }

        if(isset($_POST["setSzurestipusValasztoV2"])){
 
            $szurestipusId = "";

            if(isset($_POST["torzsszam"])){
                if($result = sql_fetch_array(sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=?",array($_POST["torzsszam"])))){

                    $szurestipusId = $result["csomagid"];

                    die(json_encode([
                        "szurestipusValaszto"=>$this->_szuresTipusValasztoNew($szurestipusId),
                        "helyszinValaszto"=>$this->_reservationPlaceSelectorNew($szurestipusId),
                        "id"=>$szurestipusId,
                        "notification"=>$this->setNotificatitonForPackage($szurestipusId)
                    ]));
                } 
            }
            
         }


        if (isset($_POST["idopontfoglalas"])) {

            $companyService = new CompanyService();

            //nem kötelező mezők létrehozása ha nincsenek
            if (!isset($_POST["taj"]))       $_POST["taj"] = "";
            if (!isset($_POST["szulhely"]))  $_POST["szulhely"] = "";
            if (!isset($_POST["anyjaneve"])) $_POST["anyjaneve"] = "";
            if (!isset($_POST["irsz"]))      $_POST["irsz"] = "";
            if (!isset($_POST["varos"]))     $_POST["varos"] = "";
            if (!isset($_POST["utca"]))      $_POST["utca"] = "";
            if (!isset($_POST["munkakor"]))  $_POST["munkakor"] = "";
            if (!isset($_POST["szuldatum"])) $_POST["szuldatum"] = "";
            if (!isset($_POST["nev"]))       $_POST["nev"] = "";
            if (!isset($_POST["telefon"]))   $_POST["telefon"] = "";
            if (!isset($_POST["neme"]))      $_POST["neme"] = 0;
            if (!isset($_POST["betegallomanynyilatkozat"])) $_POST["betegallomanynyilatkozat"] = 0;
            if (!isset($_POST["tudoszuroelf"])) $_POST["tudoszuroelf"] = 0;
            if (!isset($_POST["reszleg"])) $_POST["reszleg"] = "";

            if(isset($_POST["email"])) $_POST["email"] = trim($_POST["email"]);

            $laborszoveg = $this->bookingService->getLaborSzoveg();
            $_POST = $companyService->fillMAKPaciensData($_POST);
            if (!empty($_POST["error"])) {
                $this->errors[] = $_POST["error"];
            }

            if (isset($_POST["szuldatumev"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            }

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            if (!$this->utils->getFieldHidden("taj") && $this->utils->getFieldRequired("taj")) {
                if (empty($_POST["taj"])) {
                    $this->errors[] = "{$webText["tajkotelezo"]}";
                }

                if(CompanyService::isALDI() && !empty($_POST["taj"]) && !$this->utils->tajCheck($_POST["taj"])){
                    $this->errors[] = "{$webText["tajformat"]}";
                }
            }

            if (isset($_POST["szurestipus"])) {
                $subServices = sql_query("select szurestipusid from szurescsomagok_kapcs k where csomagid=?", [$_POST["szurestipus"]])->fetchAll(PDO::FETCH_ASSOC);
                foreach ($subServices as $subService) {
                    $this->selectedVizsgalatok[] = $subService["szurestipusid"];
                }
            }

            //Ha regexel akarom ellenőrizni magyar útlevélre
            /*$idTypes = array(
                0=>array("tipus">="TAJ","regex"=>""),
                1=>array("tipus"=>"Utlevel","regex"=>'^[A-Za-z]{2}+[1-9]{3,7}')
            );

            foreach($idTypes as $type){
                if(preg_match($type["regex"], $_POST["taj"])){
                    $this->errors[$type["tipus"]];
                }
            }*/

            //auchan esetén kötelező kieg vizsgálat választás
            if (CompanyService::isAuchan()) {
                $selectedKiegVizsgalat = [];
                foreach (BookingService::AUCHAN_SZURESEK as $key => $auchanSzures) {
                    if (isset($_POST["kiegoption{$key}"])) {
                        $selectedKiegVizsgalat[] = $_POST["kiegoption{$key}"];
                    }
                }
                $selectedKiegVizsgalat = array_unique($selectedKiegVizsgalat);
                if (empty($selectedKiegVizsgalat)) {
                    $this->errors[] = "Válasszon legalább 1 kiegészítő vizsgálatot!";
                }

                if (count($selectedKiegVizsgalat) > 1 && in_array($_POST["helyszin"], CompanyService::auchanSingleReservationPlaces())) {
                    $this->errors[] = "Egyszerre csak egy vizsgálathoz lehet időpontot foglalni. Ez alól kivételt képez a laborvizsgálat, amiből egyszerre többet is kijelölhet.";
                }

                $result = $this->bookingService->doAuchanServicesTest();
                if (!empty($result)) {
                    $this->errors[] = $result;
                }

                if (count($selectedKiegVizsgalat) == 1) {
                    $_POST["szurestipus"] = $selectedKiegVizsgalat[0];
                }

                $this->setAuchanWarning();
            }

            //oif esetén ellenőrzések
            if (CompanyService::isOIF()) {
                $selectedKiegVizsgalat = [];
                foreach (BookingService::OIF_SZURESEK as $key => $szures) {
                    if (isset($_POST["kiegoption{$key}"])) {
                        $selectedKiegVizsgalat[] = $_POST["kiegoption{$key}"];
                    }
                }
                /*$selectedKiegVizsgalat = array_unique($selectedKiegVizsgalat);
                if (empty($selectedKiegVizsgalat)) {
                    $this->errors[] = "Válasszon legalább 1 kiegészítő vizsgálatot!";
                }*/

                $result = $this->bookingService->doOIFServicesTest();
                if (!empty($result)) {
                    $this->errors[] = $result;
                }
            }

             //BudapestBrand esetén ellenőrzések
             if (CompanyService::isBudapestBrand()) {
                $selectedKiegVizsgalat = [];
                foreach (BookingService::BudapestBrand_SZURESEK as $key => $szures) {
                    if (isset($_POST["kiegoption{$key}"])) {
                        if (!empty($szures[5]) && empty($_SESSION["cartTimes"][$szures[0]])) {
                            $this->errors[] = "Válasszon a kiegészítő vizsgálathoz időpontot!";
                        }
                        $selectedKiegVizsgalat[] = $_POST["kiegoption{$key}"];
                    }
                }

                //Ha nincs vizsgálat nem engedjen foglalni.
                $selectedKiegVizsgalat = array_unique($selectedKiegVizsgalat);
                if (empty($selectedKiegVizsgalat)) {
                    //$this->errors[] = "Válasszon legalább 1 kiegészítő vizsgálatot!";
                }

                 $result = $this->bookingService->doBudapestBrandServicesTest();
                 if (!empty($result)) {
                     $this->errors[] = $result;
                 }
            }

            //KRE esetén ellenőrzések
            if (CompanyService::isKRE()) {
                $selectedKiegVizsgalat = [];
                foreach (BookingService::KRE_SZURESEK as $key => $szures) {
                    if (isset($_POST["kiegoption{$key}"])) {
                        if (!empty($szures[5]) && empty($_SESSION["cartTimes"][$szures[0]])) {
                            $this->errors[] = "Válasszon a kiegészítő vizsgálathoz időpontot!";
                        }
                        $selectedKiegVizsgalat[] = $_POST["kiegoption{$key}"];
                    }
                }

                //Ha nincs vizsgálat nem engedjen foglalni.
                $selectedKiegVizsgalat = array_unique($selectedKiegVizsgalat);
                if (empty($selectedKiegVizsgalat)) {
                    //$this->errors[] = "Válasszon legalább 1 kiegészítő vizsgálatot!";
                }

                $result = $this->bookingService->doKREServicesTest();
                if (!empty($result)) {
                    $this->errors[] = $result;
                }
            }
            

            if(CompanyService::isApollo()){

                if(empty($_POST["reszleg"]) || $_POST["reszleg"]=="Válassz részleget!"){
                    $this->errors[] = "Részleg kiválasztása kötelező!";
                }

                if(empty($_POST["munkakor"]) || $_POST["munkakor"]=="Válassz részleget!"){
                    $this->errors[] = "Munkakör kiválasztása kötelező!";
                }

            }

            //if ($_POST["taj"] == "") $this->errors[] = "{$webText["tajkotelezo"]}";
            //if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $this->errors[] = "{$webText["tajformat"]}";
            if ($_POST["helyszin"] == "0") $this->errors[] = "{$webText["helyszinkotelezo"]}";
            if ($_POST["szurestipus"] == "0") $this->errors[] = "{$webText["szurestipuskotelezo"]}";

            $this->bookingService->setSzuresTipus($_POST["szurestipus"]);
            $this->bookingService->setHelyszin($_POST["helyszin"]);
            $this->bookingService->setNeme($_POST["neme"]);

            //több dátum check
            if (isset($_POST["datum1"])) {
                $this->numberOfTimes = $this->bookingService->numberOfReservationRequired();

                $multipleTimes = $days = [];
                for ($i = 1; $i <= $this->numberOfTimes; $i++) {
                    if ($_POST["datum{$i}"] == "") {
                        $this->errors[] = "Kérjük adja meg a {$this->numberOfTimes} időpontot!";
                        break;
                    }
                    $multipleTimes[] = ["datum" => $_POST["datum{$i}"], "rinterval" => $_POST["rinterval{$i}"], "orvosselected" => $_POST["orvosselected{$i}"]];
                    $days[] = date("Y-m-d", strtotime($_POST["datum{$i}"]));

                    if (!$this->bookingService->checkIdopontSzabad(["datum" => $_POST["datum{$i}"], "orvosselected" => $_POST["orvosselected{$i}"]])) {
                        $this->errors[] = "{$webText["idopontlefoglaltak"]}";
                    }

                }

                if (count($days) == $this->numberOfTimes && count(array_unique($days)) != $this->numberOfTimes) {
                    $this->errors[] = "Kérjük különböző napokat jelöljön meg a {$this->numberOfTimes} időpont esetében!";
                }

            } else {
                if ($_POST["datum"] == "") {
                    $this->errors[] = "{$webText["idopontkotelezo"]}";
                }
            }

            if ($_SESSION["helyszindata"]["covid_oltas_bekeres"] == 1) {
                if ($_POST["is-vaccinated"] == 1) {
                    if (empty($_POST["first-vaccination-type"])) {
                        $this->errors[] = "Az 1. vakcina típusának megadása kötelező!";
                    }
                    if (checkdate($_POST["first-vaccine-month"], $_POST["first-vaccine-day"], $_POST["first-vaccine-year"])) {
                        $_POST["first-vaccine-date"] = $_POST["first-vaccine-year"] . "-" . $_POST["first-vaccine-month"] . "-" . $_POST["first-vaccine-day"];
                    } else {
                        $this->errors[] = "A megadott 1. oltási dátum helytelen!";
                    }

                    if (!empty($_POST["second-vaccine-year"]) || !empty($_POST["second-vaccine-month"]) || !empty($_POST["second-vaccine-day"]) || !empty($_POST["second-vaccination-type"])) {
                        if (checkdate($_POST["second-vaccine-month"], $_POST["second-vaccine-day"], $_POST["second-vaccine-year"])) {
                            $_POST["second-vaccine-date"] = $_POST["second-vaccine-year"] . "-" . $_POST["second-vaccine-month"] . "-" . $_POST["second-vaccine-day"];
                        } else {
                            $this->errors[] = "A megadott 2. oltási dátum helytelen!";
                        }
                        if (empty($_POST["second-vaccination-type"])) {
                            $this->errors[] = "Az 2. vakcina típusának megadása kötelező!";
                        }
                    }

                    if (!empty($_POST["third-vaccine-year"]) || !empty($_POST["third-vaccine-month"]) || !empty($_POST["third-vaccine-day"]) || !empty($_POST["third-vaccination-type"])) {
                        if (checkdate($_POST["third-vaccine-month"], $_POST["third-vaccine-day"], $_POST["third-vaccine-year"])) {
                            $_POST["third-vaccine-date"] = $_POST["third-vaccine-year"] . "-" . $_POST["third-vaccine-month"] . "-" . $_POST["third-vaccine-day"];
                        } else {
                            $this->errors[] = "A megadott 3. oltási dátum helytelen!";
                        }
                        if (empty($_POST["third-vaccination-type"])) {
                            $this->errors[] = "Az 3. vakcina típusának megadása kötelező!";
                        }
                    }
                }
            }

            if (!$this->utils->getFieldHidden("email") && $this->utils->getFieldRequired("email")) {

                if (empty($_POST["email"])) {
                    if (!CompanyService::isWaberers()) {
                        $this->errors[] = "{$webText["emailkotelezo"]}";
                    }
                }

                if (!empty($_POST["email"]) && !filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
                    $this->errors[] = "{$webText["hibasemail"]}";
                }
            }
            if (!$this->utils->getFieldHidden("nev") && $this->utils->getFieldRequired("nev")) {
                if (empty($_POST["nev"])) {
                    $this->errors[] = "{$webText["nevkotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("torzsszam") && $this->utils->getFieldRequired("torzsszam")) {
                if (empty($_POST["torzsszam"])) {
                    $this->errors[] = "{$webText["torzsszamkotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("telefon") && $this->utils->getFieldRequired("telefon")) {
                if(!CompanyService::telExceptions()){
                    $_POST["telefon"] = str_replace(["+", " ", "/", "-", "(", ")"], "", $_POST["telefon"]);
                    if (empty($_POST["telefon"])) {
                        $this->errors[] = "{$webText["telkotelezo"]}";
                    } else {
                        if (!preg_match('/^(36|06)(20|30|31|50|70)\d{7}$/', $_POST["telefon"])) {
                            $this->errors[] = "{$webText["telformat"]}";
                        }
                    }
                }
            }

            if (!$this->utils->getFieldHidden("szulhely") && $this->utils->getFieldRequired("szulhely")) {
                if (empty($_POST["szulhely"])) {
                    $this->errors[] = "{$webText["szulhelykotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("szuldatum") && $this->utils->getFieldRequired("szuldatum")) {
                $birthDateError = false;
                if (empty($_POST["szuldatum"])) {
                    $this->errors[] = "{$webText["szulkotelezo"]}";
                    $birthDateError = true;
                }
                if (!$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) {
                    $this->errors[] = "{$webText["szulformat"]}";
                    $birthDateError = true;
                } else {
                    if (strtotime($_POST["szuldatum"]) > strtotime("now - 1 day")) {
                        $this->errors[] = "{$webText["szulformat"]}";
                        $birthDateError = true;
                    }
                }

                if (!$birthDateError) {
                    if (substr_count(strtolower($this->bookingService->szuresTipusData["megnev"]), "tüdősz") && strtotime($_POST["szuldatum"]) > strtotime("now - 18 year")) {
                        $this->errors[] = "{$webText["tudoszurominimumkorerror"]}";
                    }
                    if (substr_count(strtolower($this->bookingService->szuresTipusData["megnev"]), "belgyógy") && strtotime($_POST["szuldatum"]) > strtotime("now - 18 year")) {
                        $this->errors[] = "{$webText["belgyogyminimumkorerror"]}";
                    }
                    if (substr_count(strtolower($this->bookingService->szuresTipusData["megnev"]), "mammo") && strtotime($_POST["szuldatum"]) > strtotime("now - 40 year")) {
                        $this->errors[] = "{$webText["mammominimumkorerror"]}";
                    }
                    if (substr_count(strtolower($this->bookingService->szuresTipusData["megnev"]), "ultrahang") && strtotime($_POST["szuldatum"]) > strtotime("now - 16 year")) {
                        $this->errors[] = "{$webText["uhminimumkorerror"]}";
                    }
                    if (substr_count(strtolower($this->bookingService->szuresTipusData["megnev"]), "neuro") && strtotime($_POST["szuldatum"]) > strtotime("now - 16 year")) {
                        $this->errors[] = "{$webText["neurominimumkorerror"]}";
                    }

                    foreach ($this->selectedVizsgalatok as $szurestipusId) {
                        if ($checkTipusData = sql_query("select megnev from szurestipusok where id=?", [$szurestipusId])->fetch(PDO::FETCH_ASSOC)) {
                            if (substr_count(strtolower($checkTipusData["megnev"]), "tüdősz") && strtotime($_POST["szuldatum"]) > strtotime("now - 18 year")) {
                                $this->errors[] = "{$webText["tudoszurominimumkorerror"]}";
                            }
                        }
                    }
                }
            }
            if (!$this->utils->getFieldHidden("irsz") && $this->utils->getFieldRequired("irsz")) {
                if (empty($_POST["irsz"])) {
                    $this->errors[] = "{$webText["irszkotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("varos") && $this->utils->getFieldRequired("varos")) {
                if (empty($_POST["varos"])) {
                    $this->errors[] = "{$webText["varoskotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("utca") && $this->utils->getFieldRequired("utca")) {
                if (empty($_POST["utca"])) {
                    $this->errors[] = "{$webText["utcakotelezo"]}";
                }
            }
            if (CompanyService::isFogleu() && empty($_POST["companytext"])) {
                $this->errors[] = "A cég megadása kötelező!";
            }
            if (!$this->utils->getFieldHidden("munkakor") && $this->utils->getFieldRequired("munkakor")) {
                if (empty($_POST["munkakor"])) {
                    $this->errors[] = "{$webText["munkakorkotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("anyjaneve") && $this->utils->getFieldRequired("anyjaneve")) {
                if (empty($_POST["anyjaneve"])) {
                    $this->errors[] = "{$webText["anyjanevekotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("neme") && $this->utils->getFieldRequired("neme")) {
                if (empty($_POST["neme"])) {
                    $this->errors[] = "{$webText["nemekotelezo"]}";
                }
            }
            if (!isset($_POST["aszf"])) {
                $this->errors[] = "{$webText["aszfkotelezo"]}";
            }

            if (isset($_POST["selectedtelephely"]) && (empty($_POST["selectedtelephely"]) || $_POST["selectedtelephely"] == 0)) {
                $this->errors[] = "{$webText["telephelykotelezo"]}";
            }

            if (isset($_POST["laboranswerneeded"]) && !isset($_POST["labor"])) {
                $this->errors[] = "Kérjük válasszon, hogy szüksége van-e BEM vizsgálatra!";
            }

            if (isset($_POST["tudoszuroanswerneeded"]) && !isset($_POST["tudoszuro"])) {
                $this->errors[] = "Kérjük válasszon, hogy van-e érvényes tüdőszűrője!";
            }

            if (isset($_POST["adoszam"])) {
                if (empty($_POST["adoszam"])) {
                    $this->errors[] = "Az adószám megadása kötelező!";
                } else {
                    if (!preg_match("/^[0-9]{8}-[0-9]-[0-9]{2}$/", $_POST["adoszam"])) {
                        $this->errors[] = "Az adószám formátuma nem megfelelő! (xxxxxxxx-x-xx)";
                    }
                }
            }

            //Helszín korlátozás VodaFone esetén
            if (isset($_POST["telephely"]) && in_array($_SESSION["helyszindata"]["id"] ,[46, 221])) {
                if ($_POST["telephely"] != "VSSB Zrt." && $_POST["helyszin"] == 320) {
                    $this->errors[] = "A kiválasztott helyszínre csak VSSB Zrt. alkalmazott foglalhat.";
                }
            }

            if (CompanyService::isFesztivalCompany()) {
                $_POST["questions"] = "";
                foreach (CompanyService::$fesztivalOnkentesQuestions as $key => $question) {
                    if ($question["required"] && !isset($_POST["question{$key}"])) {
                        $this->errors[] = $webText["euquestionerror"];
                        break;
                    }
                    $_POST["questions"] .= "{$question["question"]}: ". ($_POST["question{$key}"] == 1 ? "IGEN" : "NEM"). "\n";
                }
            }

            /*if (CompanyService::isAstostecCompany()) {
                if ($_POST["tudoszuroelf"]==0) {
                    $this->errors[] = "{$webText["tudoszurokotelezo"]}";
                }
            }*/

            if (CompanyService::isALDI()){
                $regex = '/^(\+36|06)(20|30|31|50|70)\d{7}$/';
                if (!preg_match($regex, $_POST["telefon"])) {
                    $this->errors[] = "{$webText["telformat"]}";
                }
            }

            //CSAK AZ UNIQÁNAK ERRE A SZŰRÉSRE
            if (CompanyService::isUniqa()){
                if($blacklistEmail = sql_fetch_array(sql_query("SELECT * FROM uniqa_blacklist WHERE email=? ",array($_POST["email"])))){
                    if(in_array($_POST["szurestipus"],array(157,158,159))){
                        $this->errors[] = "A kiválasztott vizsgálatra nem lehetséges az időpont foglalás, kérem, válasszon egy másik vizsgálat típust. (fekete listás ellenőrzés)";
                    }
                }
                /*if(in_array($_POST["szurestipus"],array(157,158,159))){
                    if($onlyOneFreeAllowed=sql_fetch_array(
                        sql_query("SELECT * FROM foglalasok WHERE cegid=? 
                                                            AND datum BETWEEN '2022-05-31 00:00:00' AND '2022-05-31 23:59:59' 
                                                            AND szurestipusid IN(157,158,159) 
                                                            AND email = ?", array(200,$_POST["email"])))){
                        $this->errors[] = "A kiválasztott vizsgálatra nem lehetséges az időpont foglalás, kérem, válasszon egy másik vizsgálat típust. (ingyenes vizsgálat ellenőrzés)";
                    }
                }*/
                if (preg_match('/@uniqa.hu|@uniqa.net/i', $_POST["email"])){
                }else{
                    $this->errors[] = "Az időpontfoglaláshoz céges e-mail címet kell megadni!";
                }
            }

            //if ($rowe=sql_fetch_array(sql_query("select id,datum,rkod from foglalasok where cegid='".addslashes($_SESSION["helyszindata"]["id"])."' and taj='".addslashes($_POST["taj"])."' and now()<datum"))) {
            //	$this->errors[] ="Már van egy foglalása ".substr($rowe["datum"],0,16)." időpontra. Ha újra szeretne foglalni, kérjük törölje az előző foglalását! <a style='color:#ff0;' href='index.php?page=torles&id={$rowe["id"]}&rk={$rowe["rkod"]}'>Időpont törlése</a>";
            //}

            if (isset($_POST["selectedtelephely"])) {
                $_POST["telephelyid"] = $_POST["selectedtelephely"];
            }
            if ($_POST["orvosselected"] != "") {
                $_POST["orvosid"] = $_POST["orvosselected"];
            }
            if (isset($_SESSION["orvosselected"]) && $_SESSION["orvosselected"]!=0) {
                $_POST["orvosid"] = $_SESSION["orvosselected"];
            }

            if($_SESSION["helyszindata"]["manual_booking_option"]!=1){
                if ($_POST["datum"] != "") {
                    if (!$this->bookingService->checkIdopontSzabad($_POST)) {
                        $this->errors[] = "{$webText["idopontlefoglaltak"]}";
                    } else {
                        //további check, hogy az esetleg kiválasztott szolgáltatás is belefér-e
                        $result = $this->bookingService->checkIdopontSzabadForServices($_POST);
                        if (!empty($result)) {
                            $this->errors[] = $result["error"];
                        }
                    }
                }

                if ($_POST["datum"] != "") {

                }
            }

            if($_SESSION["helyszindata"]["manual_booking_option"]==1){
                if($_POST["datum"]!="Időpont egyeztetés"){
                    if ($_POST["datum"] != "" && !$this->bookingService->checkIdopontSzabad($_POST)) {
                        $this->errors[] = "{$webText["idopontlefoglaltak"]}";
                    }
                }else{
                    $_POST["datum"]="1900-01-01 00:00:01";
                }
            }

            if ($this->bookingService->isOnlineTipus($_POST["szurestipus"]) && !isset($_POST["simplepay"])) {
                $this->errors[] = $webText["simplepaytoskotelezo"];
            }

            if (isset($_POST["simplepay"]) && $_POST["simplepay"] == 1) {
                $priceData = $this->bookingService->getPriceData($_POST["szurestipus"]);
                $_POST["totalprice"] = $priceData["price"];
            }

            if (!isset($_POST["rinterval"])) $_POST["rinterval"] = 0;
            if (!isset($_POST["telephely"])) $_POST["telephely"] = "";

            $_POST["foglalta"] = $this->getReferer();

            if (!isset($_SESSION["user"])) {
                $captchaError = $this->utils->checkCaptcha();
                if (!empty($captchaError) && empty($this->errors)) {
                    //$this->errors[] = $captchaError;
                }
            }

            if (empty($this->errors) && isset($multipleTimes)) {
                //leágazás több időpont esetén
                foreach ($multipleTimes as $time) {
                    $_POST["datum"] = $time["datum"];
                    $_POST["rinterval"] = $time["rinterval"];
                    $_POST["orvosselected"] = $_POST["orvosid"] = $time["orvosselected"];
                    $forwardURL = $this->bookingService->addReservation($_POST);
                    $fid = $this->bookingService->newReservationId;
                    if (!isset($firstReservationId)) {
                        $firstReservationId = $fid;
                    }

                    sql_query("update foglalasok set aktiv=1, fgroupid=? where id=? limit 1", [$firstReservationId, $fid]);
                }

                if (isset($forwardURL)) {
                    header("location:{$forwardURL}");
                }

                die;
            }

            //Ha Suzukis, töltse ki az összes adatát a rendszer
            //if(CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser()){
            //    if(!$suzukiData=sql_fetch_array(sql_query("SELECT * FROM suzuki_white_list WHERE taj=?",array($_POST["taj"])))){
            //        $this->errors[] = "Sajnálatos módon Ön nem jogosult a Suzuki Menedzser szűrésre, kérjük keresse meg a Magyar Suzuki Zrt. HR Osztályát.";
            //    }
            //}


            if (empty($this->errors)) {

                if ((CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser()) && isset($suzukiData)) {
                    $_POST["nev"]       = $suzukiData["nev"];
                    $_POST["szuldatum"] = $suzukiData["szuldatum"];
                    $_POST["szulhely"]  = $suzukiData["szulhely"];
                    $_POST["anyjaneve"] = $suzukiData["anyjaneve"];
                    $_POST["irsz"]      = $suzukiData["irsz"];
                    $_POST["varos"]     = $suzukiData["varos"];
                    $_POST["utca"]      = $suzukiData["utca"];
                    $_POST["neme"]      = $suzukiData["neme"];
                }

                if (!empty($_POST["telefon"]) && !empty($_POST["korzetszam"])) {
                    $_POST["telefon"] = "+36{$_POST["korzetszam"]}{$_POST["telefon"]}";
                }

                if(!empty($_SESSION["labshopMegjegyzes"])){
                    $_POST["megj"].=" ".$_SESSION["labshopMegjegyzes"];
                }

                if(CompanyService::isApollo() && isset($_SESSION["vizsgalati-ok"])){
                    $_POST["megj"].= implode(",",$_SESSION["vizsgalati-ok"]);
                }

                $forwardURL = $this->bookingService->addReservation($_POST);
                $fid = $this->bookingService->newReservationId;

                logActivity("foglalas", $fid,"{$_POST["nev"]} felhasználó foglalás", json_encode($_POST,JSON_PRETTY_PRINT));

                if (isset($_SESSION["cartTimes"])){
                    unset($_SESSION["cartTimes"]);
                }

                $this->record_covid_vaccination_data($fid,$_POST);
                header("location:{$forwardURL}");
                die();
            }

            if ($_POST["silentmode"] == 1) {
                $this->errors = [];
            }
        }

        if(isset($_POST["selectmuszak"])){
            /**
            *A műszakon kívül figyelembe kell venni a hetet is
            *A vagy B hét van?
            *2024-10-02->2024-10-04 "B" hét 
            *2024-10-07->2024-10-11 "A" hét
            *2024-10-14->2024-10-18 "B" hét

            *Első érték:     Hét, 
            *Második érték:  Műszak (Tól-ig hossza a no-no szakasz, azon túl kell lehetőséget biztosítani a vizsgálatokra)
            *Harmadik érték: A csomag, itt is külön sávok vannak mikor melyik elérhető, szerencsére fixek szval nem lesz gond a kalkulációkkal
             */

            $shifts = array(
                "A-A-SE"=>array(
                    "Hétfő"=>array("start"=>null,"end"=>null),
                    "Kedd"=>array("start"=>null,"end"=>null),
                    "Szerda"=>array("start"=>null,"end"=>null),
                    "Csütörtök"=>array("start"=>"15:0","end"=>"18:0"),
                    "Péntek"=>array("start"=>null,"end"=>null),
                ),
                "A-A-ST"=>array(
                    "Hétfő"=>array("start"=>"15:0","end"=>"18:0"),
                    "Kedd"=>array("start"=>"15:0","end"=>"18:0"),
                    "Szerda"=>array("start"=>"15:0","end"=>"18:0"),
                    "Csütörtök"=>array("start"=>"15:0","end"=>"18:0"),
                    "Péntek"=>array("start"=>"15:0","end"=>"18:0"),
                ),
                "A-B-SE"=>array(
                    "Hétfő"=>array("start"=>"7:0","end"=>"12:0"),
                    "Kedd"=>array("start"=>"7:0","end"=>"12:0"),
                    "Szerda"=>array("start"=>"7:0","end"=>"12:0"),
                    "Csütörtök"=>array("start"=>"7:0","end"=>"12:0"),
                    "Péntek"=>array("start"=>"7:0","end"=>"12:0"),
                ),
                "A-B-ST"=>array(
                    "Hétfő"=>array("start"=>null,"end"=>null),
                    "Kedd"=>array("start"=>null,"end"=>null),
                    "Szerda"=>array("start"=>null,"end"=>null),
                    "Csütörtök"=>array("start"=>null,"end"=>null),
                    "Péntek"=>array("start"=>null,"end"=>null),
                ),
                "B-A-SE"=>array(
                    "Hétfő"=>array("start"=>"07:0","end"=>"12:0"),
                    "Kedd"=>array("start"=>"07:0","end"=>"12:0"),
                    "Szerda"=>array("start"=>"07:0","end"=>"12:0"),
                    "Csütörtök"=>array("start"=>"07:0","end"=>"12:0"),
                    "Péntek"=>array("start"=>"07:0","end"=>"12:0"),
                ),
                "B-A-ST"=>array(
                    "Hétfő"=>array("start"=>null,"end"=>null),
                    "Kedd"=>array("start"=>null,"end"=>null),
                    "Szerda"=>array("start"=>null,"end"=>null),
                    "Csütörtök"=>array("start"=>null,"end"=>null),
                    "Péntek"=>array("start"=>null,"end"=>null),
                ),
                "B-B-SE"=>array(
                    "Hétfő"=>array("start"=>null,"end"=>null),
                    "Kedd"=>array("start"=>null,"end"=>null),
                    "Szerda"=>array("start"=>null,"end"=>null),
                    "Csütörtök"=>array("start"=>"15:0","end"=>"18:0"),
                    "Péntek"=>array("start"=>null,"end"=>null),
                ),
                "A-B-ST"=>array(
                    "Hétfő"=>array("start"=>"15:0","end"=>"18:0"),
                    "Kedd"=>array("start"=>"15:0","end"=>"18:0"),
                    "Szerda"=>array("start"=>"15:0","end"=>"18:0"),
                    "Csütörtök"=>array("start"=>"15:0","end"=>"18:0"),
                    "Péntek"=>array("start"=>"15:0","end"=>"18:0"),
                ),
            );
            die();
        }

        if(isset($_POST["selectvizsgok"])){
            if(isset($_POST["values"])){
                $_SESSION["vizsgalati-ok"] = $_POST["values"];
            }else{
                unset($_SESSION["vizsgalati-ok"]);
            }
           
           die();
        }
    }

    private function getReferer():string {
        return !empty($_SESSION["referer"]) ? $_SESSION["referer"] : "";
    }

    public function showPage() {
        $webText = $this->lang->webText;


        if (!isset($_POST["helyszin"])) {
            $_POST["helyszin"] = $_POST["szurestipus"] = "";
        }

        if (isset($_GET["szurestipus"])) {
            $_POST["szurestipus"] = $_GET["szurestipus"];
        }

        if (isset($_GET["helyszin"]) && !isset($_POST["helyszin"])) {
            $_POST["helyszin"] = $_GET["helyszin"];
        }

        if (isset($_GET["selectedtelephely"])) {
            $_POST["selectedtelephely"] = $_GET["selectedtelephely"];
        }

        $tipusData = sql_fetch_array(sql_query("select * from szurestipusok where id=?", [$_POST["szurestipus"]]));

        if (!isset($_POST["email"]) && !isset($_POST["nev"])) {
            $_POST["datum"] = $_POST["datumText"] = $_POST["email"] = $_POST["nev"] = $_POST["telefon"] = $_POST["szuldatum"] = $_POST["taj"] = $_POST["irsz"] = $_POST["varos"] = $_POST["utca"] = $_POST["munkaltato"] = $_POST["munkakor"] = $_POST["nev"] = $_POST["nev"] = $_POST["megj"] = $_POST["captcha"] = $_POST["szulhely"] = $_POST["anyjaneve"] = $_POST["telephely"] = "";
            $_POST["rinterval"] = 0;

            if (isset($_SESSION["keltexmedhuorderdata"])) {
                $keltexmedWebService = new KeltexMedWebSQL();
                $keltexmedWebService->fillBookingDatas();
            }

            if (isset($_SESSION["user"])) {
                $_POST["taj"]       = $_SESSION["user"]["taj"];
                $_POST["email"]     = $_SESSION["user"]["email"];
                $_POST["nev"]       = $_SESSION["user"]["nev"];
                $_POST["telefon"]   = $_SESSION["user"]["telefon"];
                $_POST["szuldatum"] = $_SESSION["user"]["szuldatum"];
                $_POST["szulhely"]  = $_SESSION["user"]["szulhely"];
                $_POST["anyjaneve"] = $_SESSION["user"]["anyjaneve"];
                $_POST["irsz"]      = $_SESSION["user"]["irsz"];
                $_POST["varos"]     = $_SESSION["user"]["varos"];
                $_POST["utca"]      = $_SESSION["user"]["utca"];
                $_POST["munkakor"]  = $_SESSION["user"]["munkakor"];
                $_POST["neme"]      = $_SESSION["user"]["neme"];
            }
        }

        if (!isset($_POST["neme"])) {
            $_POST["neme"] = "";
        }

        if (Booking_Constants::SQL_DB == "hungariamed") {
            //rawos marketinghez
            echo '<!-- Google Tag Manager (noscript) -->
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-P89C75S"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>

            <!-- End Google Tag Manager (noscript) -->';
        } else {
            echo "<script>
                  window.dataLayer = window.dataLayer || [];
                  function gtag() { dataLayer.push(arguments); }
                  gtag('consent', 'default', {
                    'ad_user_data': 'denied',
                    'ad_personalization': 'denied',
                    'ad_storage': 'denied',
                    'analytics_storage': 'denied',
                    'wait_for_update': 500,
                  });
                  gtag('js', new Date());
                  gtag('config', 'G-PPW39Z9QSN');
                </script>";
        }

        //auchan esetén ideiglenes üzenet
        $this->setAuchanWarning();

        if ($_SESSION["helyszindata"]["onlybeutalo"] == 1) {
            $_SESSION["helyszindata"]["onlyreg"] = 1;
        }

        if ($_SESSION["helyszindata"]["onlyreg"] == 1 && !isset($_SESSION["user"])) {
            $btext = $webText["mainudvozles"];

            if(CompanyService::isSuzukiGHC()){

                //header("location:index.php?page=login");

                $html = "";

                $html.=  $this->displayFejlec("Suzuki GHC szűrés",true);

                //$html.= $webText["mainudvozles"];

                //$html .= "<div class=\"row\">";
                //$html .= "    <div class=\"col-md-3\"></div>";
                //$html .= "    <div class=\"col-md-6 col-sm-12 mb-3 mt-3 text-center\">";
                //$html .= "      <p>Üdvözöljük, a Suzuki GHC szűrés - online regisztrációs felületén.</p>";
                //$html .= "      <p>Jelentkezését az októberi szűrésre a \"Regisztráció\" gombra kattintva adhatja le. Az időpontfoglalás szeptembertől indul, melyről e-mailben és SMS-ben értesítjük Önt.</p>";
                //$html .= "    </div>";
                //$html .= "    <div class=\"col-md-3\"></div>";
                //$html .= "</div>";
                //$html .= "<div class=\"row\">";
                //$html .= "    <div class=\"col-3\"></div>";
                //$html .= "    <div class=\"col-6 mb-3 mt-3\">";
                //$html .= "        <div class=\"row\">";
                //$html .= "            <div class=\"col pb-3 text-center\">";
                //$html .= "               <button type=\"button\" onClick=\"location.href='https://{$_SERVER["HTTP_HOST"]}/?page=registration'\" class=\"btn btn-hungariamed btn-lg\" style=\"width:170px\">Regisztráció</button>";
                //$html .= "           </div>";
                //$html .= "            <div class=\"col pb-3 text-center\">";
                //$html .= "               <button type=\"button\" onClick=\"location.href='https://{$_SERVER["HTTP_HOST"]}/?page=login'\" class=\"btn btn-hungariamed btn-lg\" style=\"width:170px\">Időpontfoglalás</button>";
                //$html .= "           </div>";
                //$html .= "       </div>";
                //$html .= "    </div>";
                //$html .= "    <div class=\"col-3\"></div>";
                //$html .= "</div>";

                $html .= "<div class=\"row\" style='color:#00368F'>";
                $html .= "    <div class=\"col-md-3\"></div>";
                $html .= "    <div class=\"col-md-6 col-sm-12 mb-3 mt-3 text-center\">";
                $html .= "      <p style=\"font-size:18px;color:#00368F\"><strong>Üdvözöljük a Suzuki GHC szűrés - online regisztrációs felületén.</strong></p>";
                $html .= "      <p style=\"font-size:20px;color:#DE0039\"><strong>2025-ben megújúlt tartalommal térünk vissza.</strong></p>"; // a résztvevő Hölgyek számára és új szabadidős programokkal várjuk Önöket!
                $html .= "      <p style=\"font-size:14px\">Jelentkezését a szeptemberi szűrésre a \"Regisztráció\" gombra kattintva adhatja le. Az időpontfoglalás augusztustól indul, melyről e-mailben és SMS-ben értesítjük Önt.</p>";
                
                $html .= "      <p style=\"font-size:14px;text-align:left;margin-bottom:0px\">Keresse kollégáinkat bizalommal:</p>";
                $html .= "      <ul style=\"margin-left: 10px;text-align:left\">";
                $html .= "          <li style=\"list-style: disc\"><span  style='font-size:14px'>Suzuki - Teberi Andrea: +3630-122-9084</li>";
                $html .= "          <li style=\"list-style: disc\"><span style='font-size:14px'>Suzuki - Balogh Miklós: +3620-587-8696</li>";
                $html .= "          <li style=\"list-style: disc\"><span style='font-size:14px'>Hungária Med-M - Szabó Melinda: +3670-779-9485</li>";
                $html .= "      </ul>";
                
                $html .= "    </div>";
                $html .= "    <div class=\"col-md-3\"></div>";
                $html .= "</div>";

                $html .= "<div class=\"row\">";
                $html .= "    <div class=\"col-md-3\"></div>";
                $html .= "    <div class=\"col-md-6\">";
                $html .= "      <p style='text-align:center;color:#DE0039;font-size:20px'><strong>Vizsgálati Csomagok</strong></p>"; //A munkavállalók egészségének átfogó felmérése érdekében az alábbi komplexszűrőcsomagokat kínáljuk:
                $html .= "      <table style='color:#00368F'>";
                $html .= "        <tr>";
                $html .= "            <td>";
                $html .= "                <table>";
                //$html .= "                    <tr><td style='color:#DE0039'><strong>SENIOR Csomag</strong></td><td style='color:#00368F'><strong>STANDARD Csomag</strong><td></tr>";
                //$html .= "                    <tr><td>Belgyógyászati vizsgálat + nyugalmi EKG</td><td>Belgyógyászati vizsgálat + nyugalmi EKG<td></tr>";
                //$html .= "                    <tr><td>Mellkas RTG</td><td>Mellkas RTG<td></tr>";
                //$html .= "                    <tr><td>ABI (Kar-Boka index)</td><td>ABI (Kar-Boka index)<td></tr>";
                //$html .= "                    <tr><td>BIA (Testösszetétel mérés)</td><td>BIA (Testösszetétel mérés)<td></tr>";
                //$html .= "                    <tr><td>Vérvétel + tumor markerekkel</td><td>Vérvétel + tumor markerekke<td></tr>";
                //$html .= "                    <tr><td>Hasi- és kismedencei ultrahang</td><td><td></tr>";
                //$html .= "                    <tr><td style='padding-right:20px'>Nyaki lágyrész, carotis és pajzsmirigy ultrahang</td><td><td></tr>";
                //$html .= "                    <tr><td>Melanóma szűrés</td><td><td></tr>";
                $html .= "                    <tr>";
                //$html .= "                      <td style='color:#DE0039'><strong>SENIOR Csomag</strong></td>";
                $html .= "                      <td style='vertical-align:middle'>";
                $html .= "                          <table>";
                $html .= "                              <tr><td ><strong>SENIOR Csomag</strong></td></tr>";
                $html .= "                              <tr><td>Belgyógyászati vizsgálat + nyugalmi EKG</td></tr>";
                $html .= "                              <tr><td>Mellkas RTG</td></tr>";
                $html .= "                              <tr><td>ABI (Kar-Boka index)</td></tr>";
                $html .= "                              <tr><td>BIA (Testösszetétel mérés)</td></tr>";
                $html .= "                              <tr><td>Vérvétel + tumormarkerekkel</td></tr>";
                $html .= "                              <tr><td>Hasi- és kismedencei ultrahang</td></tr>";
                $html .= "                              <tr><td>Nyaki lágyrész, carotis és pajzsmirigy ultrahang</td></tr>";
                $html .= "                              <tr><td>Melanóma szűrés</td></tr>";
                $html .= "                              <tr><td></td></tr>";
                $html .= "                          </table>";
                $html .= "                      </td>";
                $html .= "                      <td>";
                $html .= "                          <img src=\"https://{$_SERVER["HTTP_HOST"]}/images/suzuki-arena.png\" width=\"301px\">";
                $html .= "                      <td>";
                $html .= "                    </tr>";

                $html .= "                    <tr></tr>";

                $html .= "                    <tr>";
                $html .= "                      <td>";
                $html .= "                          <img src=\"https://{$_SERVER["HTTP_HOST"]}/images/suzuki-egeszseghaz.png\" width=\"\" style='margin-right:10px'>";
                $html .= "                      </td>";
                $html .= "                      <td style='vertical-align:middle'>";
                $html .= "                          <table>";
                $html .= "                              <tr><td style='color:#00368F'><strong>STANDARD Csomag</strong><td></tr>";
                $html .= "                              <tr><td>Belgyógyászati vizsgálat + nyugalmi EKG<td></tr>";
                $html .= "                              <tr><td>Mellkas RTG<td></tr>";
                $html .= "                              <tr><td>ABI (Kar-Boka index)<td></tr>";
                $html .= "                              <tr><td>BIA (Testösszetétel mérés)<td></tr>";
                $html .= "                              <tr><td>Vérvétel + tumormarkerekkel</td></tr>";
                $html .= "                          </table>";
                $html .= "                      </td>";
                $html .= "                    </tr>";
                $html .= "                </table>";
                $html .= "            </td>";
                $html .= "        </tr>"; 
                $html .= "      </table><br>";
                $html .= "     <p style='font-size:20px;text-align:center;color:#DE0039'><strong>Újdonságok</strong></p>";
                $html .= "     <p style='font-size:14px;text-align:center;color:#00368F'>Mindkét csomagot bővítettük</p>";
                
                //$html .= "      <p style='color:red;text-align:center'><strong>40 év alatt emlő ultrahang, 40 év felett mammográfia vizsgálatot biztosítunk.</strong></p>";
                //$html .= "      <div class=\"col mb-3 text-center\">";             
                //$html .= "          <img src=\"https://{$_SERVER["HTTP_HOST"]}/images/ghc_csomag_kepek.png\" width=\"450px\"  style=\"margin:10px\">"; //class=\"d-none d-md-inline\"
                //$html .= "      </div>";
                //$html .= "      <p><strong>Igénybevehető kiegészítő szolgáltatás mindkét csomag esetén:</strong> </p>";
                $html .= "      <ul style=\"margin-left: 10px\">";
                $html .= "          <li style=\"list-style: disc\"><strong><span  style='color:#DE0039;font-size:16px'>40 év feletti hölgyek számára MAMMOGRÁFIA vizsgálat</span></strong></li>";
                $html .= "          <li style=\"list-style: disc\"><strong><span style='color:#DE0039;font-size:16px'>40 év alatti hölgyek számára EMLŐ ULTRAHANG vizsgálat</span></strong></li>";
                $html .= "          <li style=\"list-style: disc\"><strong><span style='color:#DE0039;font-size:16px'>Emlőrák tumormarkerével bővített laboratóriumi csomag hölgyeknek</span></strong></li>";
                //$html .= "          <li style=\"list-style: disc\">Vicardio- Szívstressz mérés</li>";
                //$html .= "          <li style=\"list-style: disc\">Csontsűrűség mérés</li>";

                $html .= "      </ul>";
                //$html .= "      <hr></hr>";
                //$html .= "      <p><strong>Javaslat:</strong> </p>";

                //$html .= "      <p style='color:#00368F;text-align:center;margin-bottom:0px'><strong>Választható kiegészítő vizsgálatok:</strong></p>";
                //$html .= "      <ul style=\"margin-left: 10px;color:#00368F\">";
                //$html .= "          <li style=\"list-style: disc\">Vicardio- Szívstressz mérés</li>";
                //$html .= "          <li style=\"list-style: disc\">Csontsűrűség mérés</li>";
                //$html .= "      </ul>";

                $html .= "      <p style='color:#00368F;text-align:center;margin-bottom:0px'><strong>Családbarát szolgáltatásaink:</strong></p>";
                $html .= "      <ul style=\"margin-left: 10px;color:#00368F\">";
                $html .= "          <li style=\"list-style: disc\">Családtervezési tanácsadás (tudatos előkészítését és a fogamzásra való felkészülést jelenti)</li>";
                $html .= "          <li style=\"list-style: disc\">Gyermekfejlesztés, konduktív pedagógia szolgáltatás (3-6 éves és 6-9 éves gyerekek részére)</li>";
                $html .= "      </ul>";
                $html .= "      <p style='text-align:center;color:#00368F;'>További részletekkel a családbarát szolgáltatásiankról <a target='_blank' href='images/SUZUKI_GHC_2025_COMP-DEV-PROGRAM.pdf'>kattintson ide</a></p>";
                //$html .= "      <p><strong>További tumormarkerrel kiegészített vérvételi csomag hölgyek részére:</strong><br>";
                //$html .= "      A <strong>CA 15-3</strong> (Cancer Antigen 15-3) elsősorban az <strong>emlőrák</strong> (mellrák) <strong>tumormarkerével</strong> egészítenénk ki a ";
                //$html .= "      szűrési laboratóriumi csomagot.</p>";

                //$html .= "      <p><strong>Női szűrőcsomag bővítése:</strong> A GHC szűrésre jelentkező nők számára a 2025. évben az alábbi kiegészítő ";
                //$html .= "      vizsgálatokkal való bővítést javasoljuk, a Senior és Standard csoportban egyaránt:</p>";

                //$html .= "      <ul style=\"margin-left: 10px\">";
                //$html .= "          <li style=\"list-style: disc\">40 év feletti nők (1985. december 31. előtt születettek): Mammográfiai szűrés</li>";
                //$html .= "          <li style=\"list-style: disc\">40 év alatti nők (1985. december 31. után születettek): Emlő ultrahang szűrés</li>";
                //$html .= "          <li style=\"list-style: disc\">Minden női résztvevő: Petefészek tumormarkerrel bővített laboratóriumi csomag</li>";
                //$html .= "      </ul>";

                $html .= "      <hr></hr>";
                $html .= "      <p style='font-size:20px;text-align:center;color:#DE0039'><strong>Nyereményjáték</strong> </p>";
                $html .= "      <p style='text-align:center;color:#DE0039'><strong><span style='text-decoration:underline'>Minden résztvevő között</span> értékes nyereményeket sorsolunk ki</strong> </p>";
                //$html .= "      <p style='text-align:center;color:#00368F'><strong>A résztvevők között nyereményeket sorsolunk ki</strong> </p>";
                $html .= "      <p style='font-size:18px;text-align:center;color:#DE0039'><strong>Fődíj</strong></p>";

                $html .= "      <div class=\"col mb-3 text-center\">";
                $html .= "          <div class='row'>";
                $html .= "                  <div class='col'><img src='https://{$_SERVER["HTTP_HOST"]}/images/neuzer-man.png'></div>";
                $html .= "                  <div class='col'><img src='https://{$_SERVER["HTTP_HOST"]}/images/neuzer-woman.png'></div>";
                $html .= "          </div>";

                $html .= "      <p style='text-align:center;color:#00368F'>";
                $html .= "          <strong>2 db</strong> (1 férfi, 1 női) <strong>Neuzer</strong> gyártmányú <strong>elektromos kerékpár</strong>";
                $html .= "      </p>";
                
                //$html .= "          <img src=\"https://{$_SERVER["HTTP_HOST"]}/images/neuzer_nyeremenyjatek.png\" width=\"450px\"  style=\"margin:10px\">"; //class=\"d-none d-md-inline\"
                $html .= "      </div><br>";
                $html .= "      <p style='font-size:18px;text-align:center;color:#DE0039'><strong>További nyeremények</strong></p>";
                

                $html .= "      <div class=\"col mb-3 text-center\">";             
                $html .= "          <img src=\"https://{$_SERVER["HTTP_HOST"]}/images/nyeremenyjatek2.png\" width=\"200px\"  style=\"margin:10px\">"; //class=\"d-none d-md-inline\"
                $html .= "      </div>";
                $html .= "      <p style='text-align:center;color:#00368F'><strong>2 db</strong> csúcskategóriás <strong>vérnyomásmérő</strong> készülék</p>";

                //$html .= "      <p style='font-size:18px;text-align:center;color:#DE0039'><strong>3. helyezett</strong></p>";
                

                $html .= "      <div class=\"col mb-3 text-center\">";             
                $html .= "          <img src=\"https://{$_SERVER["HTTP_HOST"]}/images/nyeremenyjatek3.png\" width=\"250px\"  style=\"margin:10px\">"; //class=\"d-none d-md-inline\"
                $html .= "      </div>";
                $html .= "      <p style='text-align:center;color:#00368F'><strong>2 db</strong> gyümölcskosár</p>";

                $html .= "    </div>";
                $html .= "    <div class=\"col-md-3\"></div>";
                $html .= "</div>";

                $html .= "<div class=\"row\">";
                $html .= "    <div class=\"col-3\"></div>";
                $html .= "    <div class=\"col-6 mb-3 mt-3\">";
                $html .= "        <div class=\"row\">";
                $html .= "            <div class=\"col pb-3 text-center\">";
                $html .= "               <button type=\"button\" onClick=\"location.href='https://{$_SERVER["HTTP_HOST"]}/?page=registration'\" class=\"btn btn-hungariamed btn-lg\" style=\"width:170px\">Regisztráció</button>";
                $html .= "           </div>";
                $html .= "            <div class=\"col pb-3 text-center\">";
                $html .= "               <button type=\"button\" onClick=\"location.href='https://{$_SERVER["HTTP_HOST"]}/?page=login'\" class=\"btn btn-hungariamed btn-lg\" style=\"width:170px\">Időpontfoglalás</button>";
                $html .= "           </div>";
                $html .= "       </div>";
                $html .= "    </div>";
                $html .= "    <div class=\"col-3\"></div>";
                $html .= "</div>";
                
                echo $html;

                return;
            }

            if(CompanyService::isFiFi()){

                //header("location:index.php?page=login");

                $html = "";

                $html.=  $this->displayFejlec("ALDI FiFi szűrés",true);

                //$html.= $webText["mainudvozles"];

                $html .= "<div class=\"row\">";
                $html .= "    <div class=\"col-md-3\"></div>";
                $html .= "    <div class=\"col-md-6 col-sm-12 mb-3 mt-3 text-center\">";
                $html .= "      <p>Üdvözöljük, az ALDI FiFi szűrés - online regisztrációs felületén.</p>";
                $html .= "      <p>Jelentkezését az októberi szűrésre a \"Regisztráció\" gombra kattintva adhatja le. Az időpontfoglalás a webshopon szeptembertől indul, melyről e-mailben és SMS-ben értesítjük Önt.</p>";
                $html .= "    </div>";
                $html .= "    <div class=\"col-md-3\"></div>";
                $html .= "</div>";
                $html .= "<div class=\"row\">";
                $html .= "    <div class=\"col-3\"></div>";
                $html .= "    <div class=\"col-6 mb-3 mt-3\">";
                $html .= "        <div class=\"row\">";
                $html .= "            <div class=\"col pb-3 text-center\">";
                $html .= "               <button type=\"button\" onClick=\"location.href='https://{$_SERVER["HTTP_HOST"]}/?page=registration'\" class=\"btn btn-hungariamed btn-lg\" style=\"width:170px\">Regisztráció</button>";
                $html .= "           </div>";
                $html .= "            <div class=\"col pb-3 text-center\">";
                $html .= "               <button type=\"button\" onClick=\"location.href='https://{$_SESSION["helyszindata"]["webshop_alias"]}.hungariamed.hu'\" class=\"btn btn-hungariamed btn-lg\" style=\"width:170px\">Webshop</button>";
                $html .= "           </div>";
                $html .= "       </div>";
                $html .= "    </div>";
                $html .= "    <div class=\"col-3\"></div>";
                $html .= "</div>";
                
                echo $html;

                return;
            }

            if(CompanyService::isAstostecCompany()){

                //header("location:index.php?page=login");

                $html = "";

                $html.=  $this->displayFejlec("Astotec Automotive szűrés",true);

                //$html.= $webText["mainudvozles"];

                $html .= "<div class=\"row\">";
                $html .= "    <div class=\"col-md-3\"></div>";
                $html .= "    <div class=\"col-md-6 col-sm-12 mb-3 mt-3 text-center\">";
                $html .= "      <p>Üdvözöljük, az Astotec Automotive szűrés - online regisztrációs felületén.</p>";
                $html .= "      <p>Jelentkezését a szűrésre a \"Regisztráció\" gombra kattintva adhatja le.</p>";
                $html .= "    </div>";
                $html .= "    <div class=\"col-md-3\"></div>";
                $html .= "</div>";
                $html .= "<div class=\"row\">";
                $html .= "    <div class=\"col-3\"></div>";
                $html .= "    <div class=\"col-6 mb-3 mt-3\">";
                $html .= "        <div class=\"row\">";
                $html .= "            <div class=\"col pb-3 text-center\">";
                $html .= "               <button type=\"button\" onClick=\"location.href='https://{$_SERVER["HTTP_HOST"]}/?page=registration'\" class=\"btn btn-hungariamed btn-lg\" style=\"width:170px\">Regisztráció</button>";
                $html .= "           </div>";
                $html .= "            <div class=\"col pb-3 text-center\">";
                $html .= "               <button type=\"button\" onClick=\"location.href='https://{$_SERVER["HTTP_HOST"]}/?page=login'\" class=\"btn btn-hungariamed btn-lg\" style=\"width:170px\">Időpontfoglalás</button>";
                $html .= "           </div>";
                $html .= "           </div>";
                $html .= "       </div>";
                $html .= "    </div>";
                $html .= "    <div class=\"col-3\"></div>";
                //$html .= "</div>";
                
                echo $html;

                return;
            }
            

            if ($rowsz = sql_fetch_array(sql_query("select * from szovegek where cegid=? and tipus='welcome'", array($_SESSION["helyszindata"]["id"])))) {
                $btext = $rowsz["szoveg"];
            }

            echo "<div style=''>{$btext}</div>";

            echo "<div style='margin-top:20px;'><a href='index.php?page=registration' class='newbutton'>{$webText["regisztracio"]}</a>&nbsp;&nbsp;<a href='index.php?page=login' class='newbutton'>{$webText["bejelentkezes"]}</a></div>";
            return;
        }

        echo $this->displayFejlec();
        echo $this->showErrors();

        

        if ($_SESSION["helyszindata"]["onlybeutalo"] == 1 && isset($_SESSION["user"]) && !isset($_SESSION["beutaloid"])) {
            echo "<div style=''>{$webText["csakbeutalodesc"]}</div>";
            echo "<div style='margin-top:10px;'><a class='simabutton' href='index.php?page=beutalok'>{$webText["showbeutalobutton"]}</a></div>";
            return;
        }

        if (isset($_SESSION["beutaloid"])) {
            if (!$beutalodata = sql_fetch_array(sql_query("select * from beutalok where id=? and foglalasid=0", array($_SESSION["beutaloid"])))) {
                echo "<div style=''>A beutalóval probléma adodott!</div>";
                echo "<div style='margin-top:10px;'><a class='simabutton' href='index.php?page=beutalok'>{$webText["showbeutalobutton"]}</a></div>";
                return;
            }
        }

       

        if ($this->isExtendedForm()) {
            echo $this->_preSelectForm();
            echo "</div>";
            return;
        }

        if (CompanyService::isBME()) {
            echo "<ul>";
            echo "<li style='list-style: circle;'><strong>1117 Budapest, Bercsényi utca. 24.</strong> Foglalkozás-egészségügyi vizsgálat, Egyéni térítéses szakvizsgálatok <span style='color:red;'>*új helyszínen</span></li>";
            echo "<li style='list-style: circle;'><strong>1116 Budapest, Albertfalva u. 3.</strong> Foglalkozás-egészségügyi vizsgálat</li>";
            echo "<li style='list-style: circle;'><strong>1135 Budapest, Jász utca 33-35.</strong> Egyéni térítéses vizsgálatok</li>";
            echo "</ul>";
            echo "<hr>";
        }

        echo "<form name='iform' id='iform' method='post' enctype='multipart/form-data'>";

        if ($this->bookingService->isOnlineTipus($_POST["szurestipus"]) && !isset($_SESSION["labcode"])) {
            echo "<div style='margin-bottom:20px;'>Tudnivalók a telemedicina szolgáltatásunkkal kapcsolatban:
            Köszönjük, hogy <strong>\"{$tipusData["megnev"]}\"</strong> szolgáltatásunkat választotta.  
            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.<br/><br>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</div>";
        }

        if (CompanyService::isAuchan()) {
            echo "<div style='margin-bottom:20px;padding-bottom:20px;border-bottom: 1px solid #ccc;'>Ha bármi kérdése van, vagy korábban foglalt időpontját szeretné módosítani, kérjük hívja ezt a telefonszámot: 06 30 537 1008</div>";
        }

        if (isset($_SESSION["labcode"])) {
            if ($labData = sql_fetch_array(sql_query("select * from labshop_vasarlasok where hash=?", [$_SESSION["labcode"]]))) {
                if (strtoupper($labData["status"]) == "FINISHED" || ($labData["status"] == "done" && $labData["payment_method"] == "utanvet")) {
                    echo "<div style='margin-bottom:20px;'>Már bejejezte a foglalást ehhez a vásárláshoz!<br/><br/>Amennyiben szeretne egy másik csomagot is választani, kérem <a href='https://labshop.hungariamed.hu'>kattintson ide</a>";
                    unset($_SESSION["labcode"]);
                    return;
                } else {
                    $_SESSION["labshopMegjegyzes"] = $labItemsHTML = $labPacksHTML = $outputHTML = "";

                    $cartContent = json_decode($labData["cart_content"], JSON_OBJECT_AS_ARRAY);
                    //$packages = $cartContent["packages"];
                    //$items = $cartContent["items"];
                    foreach ($cartContent as $product) {
                        if(isset($product["type"]) && $product["type"]=="package"){
                            if ($packData = sql_query("select name from synlab_labor_csomagok where id=?", [$product["id"]])->fetch(PDO::FETCH_ASSOC)) {
                                $labPacksHTML .= "<li style='list-style:outside;'>{$packData["name"]} - {$product["unit"]} db - ".number_format($product["price"])." Ft</li>";
                                $_SESSION["labshopMegjegyzes"].= "Csomag: {$packData["name"]} - {$product["unit"]} db - ".number_format($product["price"])." Ft\n";
                            }
                        }
                        if(isset($product["type"]) && $product["type"]=="item"){
                            if ($itemData = sql_query("select name from synlab_labor_tetelek where id=?", [$product["id"]])->fetch(PDO::FETCH_ASSOC)) {
                                $labItemsHTML .= "<li style='list-style:outside;'>{$itemData["name"]} - {$product["unit"]} db - ".number_format($product["price"])." Ft</li>";
                                $_SESSION["labshopMegjegyzes"].= "Lab. elem: {$itemData["name"]} - {$product["unit"]} db - ".number_format($product["price"])." Ft\n";
                            }
                        }
                        if(isset($product["type"]) && $product["type"]=="exam"){
                            if ($itemData = sql_query("select a.megnev as name from arak a where id=?", [$product["id"]])->fetch(PDO::FETCH_ASSOC)) {
                                $labItemsHTML .= "<li style='list-style:outside;'>{$itemData["name"]} - {$product["unit"]} db - ".number_format($product["price"])." Ft</li>";
                                $_SESSION["labshopMegjegyzes"].= "Vizsgálat: {$itemData["name"]} - {$product["unit"]} db - ".number_format($product["price"])." Ft\n";
                            }
                        }                    }

                    $outputHTML.= "<div style='margin-bottom:20px;border-bottom:1px solid #ccc;'>Ön a LabShop vásárlásához készül időpontot foglalni. A vásálás értéke: <span style='font-family: robotobold;'>" . number_format($labData["fullprice"]) . " Ft</span>. Választott fizetési mód: <span style='font-family: robotobold;'>" . $this->paymentMethods[$labData["payment_method"]] . "</span>.<br/><br>";

                    if (!empty($labPacksHTML)) {
                        $outputHTML.=  "<span style='font-family: robotobold;'>Választott csomagok:</span><br/><ul>{$labPacksHTML}</ul>";
                    }
                    if (!empty($labItemsHTML)) {
                        $outputHTML.=  "<span style='font-family: robotobold;'>Választott tételek:</span><br/><ul>{$labItemsHTML}</ul>";
                    }
                    $outputHTML.=  "</div>";
                    echo $outputHTML;
                }
            }
        }



        $firstColumnWidth = 130;

        $hiddenTable = "";

        if (CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser()) {

            if (isset($_SESSION["suzukimanagertorzsszam"])) {
                echo "<div style='padding:20px;text-align: center;background:#f0f0f0;margin-bottom: 20px;'>";
                echo "Ön a <strong>{$_SESSION["suzukimanagertorzsszam"]}</strong> TAJ számmal foglal időpontot!<br/><a href='index.php?clearsmtorzsszam'>Nem ez a TAJ számom</a>";
                //echo "<input type='hidden' name='torzsszam' value='{$_SESSION["suzukimanagertorzsszam"]}' />";
                echo "</div>";
                if (empty($_POST["taj"])) {
                    $_POST["taj"] = $_SESSION["suzukimanagertorzsszam"];
                }
            } else {
                echo "<div style='padding:20px;text-align: center;'>";
                echo "<div>Kérjük az időpont foglalás megkezdéséhez adja meg a TAJ számát:</div>";
                echo "<div style='margin-top: 10px;'><input type='text' id='suzukimanagertorzsszam' name='suzukimanagertorzsszam' value='' placeholder='TAJ szám' /></div>";
                echo "<div style='margin-top: 10px;'><a id='smtorzsszambutton' href='#' class='newbutton' onclick='smTorzsszamSubmit();return false;'>Tovább</a></div>";
                echo "</div>";
                $hiddenTable = "display:none;";
            }

        }

        echo "<table cellpadding='3' cellspacing='0' style='width:100%;table-layout: fixed;{$hiddenTable}'>";

        //Kérjük akkut egészségkárosodás vagy életveszély esetén azonnal hívja az 104-es országos mentőszolgálat vagy a 112 központi segélyhívót.

        if(CompanyService::isBP() && true){
            //Figyelmeztetés a piszohosc kitöltésére
            echo "<div style='border-radius:20px;background-color:#990000;padding:5px 10px;'>";
            echo $webText["pszihoszocialis_kerdoiv_figyelmeztetes"];
            echo "</div>";
        }
        

        if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_COOKIE["lang"] != "hu" && trim($_SESSION["helyszindata"]["beutaloszoveg_{$_COOKIE["lang"]}"]) != "") {
            $_SESSION["helyszindata"]["beutaloszoveg"] = $_SESSION["helyszindata"]["beutaloszoveg_{$_COOKIE["lang"]}"];
        }

        if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_SESSION["helyszindata"]["beutaloszoveg"] != "" && !$this->bookingService->isOnlineTipus($_POST["szurestipus"])) {
            echo "<tr><td style='width:{$firstColumnWidth}px;'></td><td><div style='font-weight:bold;padding:5px 0px;'>{$_SESSION["helyszindata"]["beutaloszoveg"]}</div></td></tr>";
        }

        if (isset($beutalodata)) {
            //beutalóval fix választás
            echo "<tr><td>{$webText["helyszin"]}: *</td><td>";
            echo "<select name='helyszin' id='helyszin'>";
            $res = sql_query("SELECT h.*," . $this->utils->cimLangQuery() . " FROM helyszinek h where h.id='{$beutalodata["helyszinid"]}'");
            if ($rowt = sql_fetch_array($res)) {
                echo "<option value='{$rowt["id"]}' selected>{$rowt["cim"]}</option>";
            }
            echo "</select>";
            echo "</td></tr>";

            echo "<tr><td>{$webText["szurestipus"]}: *</td><td><div id='szurestipusvalaszto'>" . $this->_szuresTipusValasztoNew($beutalodata["szurestipusid"], 1) . "</div></td></tr>";
            $tipusMegj = $this->bookingService->getTipusMegj($_SESSION["helyszindata"]["id"], $beutalodata["szurestipusid"], $beutalodata["helyszinid"]);



            if (!empty($tipusMegj)) {
                echo "<tr><td></td><td><div id='szurestipusmegj'>{$tipusMegj}</div></td></tr>";
            }
        } else {
            if (CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser() || CompanyService::isAszMenedzser()) {
                $customJs="onChange='setSzurestipusValaszto()'";
                echo $this->utils->dataField("neme",true,$customJs);
                echo $this->utils->dataField("szuldatum",true,$customJs);
                echo "<input type=\"hidden\" name=\"cid\" value=\"{$_SESSION["helyszindata"]["id"]}\">";
            }

            if(CompanyService::isSuzukiGHC()){
                echo "<input type=\"hidden\" name=\"cid\" id=\"cid\" value=\"{$_SESSION["helyszindata"]["id"]}\">";
            }

            $szuresTipusValaszto = $this->_szuresTipusValasztoNew($_POST["szurestipus"]);
            $infoPageText = $this->bookingService->getInfoPageText($_POST["szurestipus"], $_POST);
            //Itt volt a tipusMegj!! $tipusMegj = $this->bookingService->getTipusMegj($_SESSION["helyszindata"]["id"], $_POST["szurestipus"], $_POST["helyszin"]);
            //beutaló nélkül szabad választás
            if (!empty($this->telephelyek)) {
                $telephelySelectText = "Telephely";
                if (CompanyService::isBME()) {
                    $telephelySelectText = "Tanszék";
                }
                echo "<tr><td style='width: {$firstColumnWidth}px;'>{$telephelySelectText}: *</td><td><div id='telephelyvalaszto'>" . $this->_telephelySelector() . "</div></td></tr>";
                echo "<tr><td></td><td></td></tr>";
            }

            //Műszak választó
            if(CompanyService::isSuzukiGHC()){
                $muszakSelect = "";
                $muszakSelect.= "<select name=\"muszak\" id=\"muszak\" onchange=\"clearIdopontValasztoOnly()\">";
                $muszakSelect.= "   <option value=\"\">Válassz műszakot!</option>";
                $muszakSelect.= "   <option value=\"A\">\"A\" műszak</option>";
                $muszakSelect.= "   <option value=\"B\">\"B\" műszak</option>";
                $muszakSelect.= "   <option value=\"D\">Karbantartó műszak</option>";
                $muszakSelect.= "   <option value=\"O\">Irodai műszak</option>";
                $muszakSelect.= "</select>";

                echo "<tr><td style='width:{$firstColumnWidth}px;'>Műszak: *</td><td><div id='muszakContainer'>{$muszakSelect}</div></td></tr>";
            }

            echo "<tr><td nowrap style='width: {$firstColumnWidth}px;'>{$webText["szurestipus"]}: *</td><td><div id='szurestipusvalaszto'>{$szuresTipusValaszto}</div></td></tr>";
            echo "<tr><td></td><td><div id='infopagetext'>{$infoPageText}</div></td></tr>";
            echo "<tr><td>{$webText["helyszin"]}: *</td><td><div id='helyszinvalaszto'>" . $this->_reservationPlaceSelectorNew() . "</div></td></tr>";
            $tipusMegj = $this->bookingService->getTipusMegj($_SESSION["helyszindata"]["id"], $_POST["szurestipus"], $_POST["helyszin"]);

            if(CompanyService::isFGSZ()){
                //echo $_SESSION["helyszndata"]["domain"];
                $helyszinek= array(125,132,96);
                if(in_array($_POST["helyszin"],$helyszinek)){
                    $tipusMegj = "<span><strong>Időpontfoglalás vérvételre:</strong></span><br>";
                    $tipusMegj.= "<span style=\"display:inline-block;margin-top:5px;\">Pongor Anita</span><br>";
                    $tipusMegj.= "<span style=\"display:inline-block;margin-bottom:5px\"><i>Kapcsolattartó munkatárs</i></span><br>";
                    $tipusMegj.= "<span style=\"display:inline-block;margin-left:5px\"><strong>Tel.:</strong> 06/30-337-8223</span><br>";
                    $tipusMegj.= "<span style=\"display:inline-block;margin-left:5px\"><strong>E-mail:</strong> <a href=\"mailto:pongor.anita@hungariamed.hu\">pongor.anita@hungariamed.hu</a></span><br>";
                    $tipusMegj.= "<span style=\"display:inline-block;margin-left:5px\"><strong>Elérhető:</strong> H-P 8:00-16:00</span>";
                }
            }

            echo "<tr><td></td><td><div id='szurestipusmegj'>{$tipusMegj}</div></td></tr>";
            echo "<tr><td></td><td><div id='tappenzcheck'>" . $this->bookingService->tappenzCheckHTML($_POST["helyszin"]) . "</div></td></tr>";
        }
       
        $nofoglalasText = trim($_SESSION["helyszindata"]["nofoglalas_{$_COOKIE["lang"]}"]);
        if (empty($nofoglalasText)) {
            $numberTexts = ["" => $webText["idopont"], 1 => "Első időpont", 2 => "Második időpont", 3 => "Harmadik időpont"];
            $this->bookingService->setHelyszin($_POST["helyszin"]);
            $this->bookingService->setSzuresTipus($_POST["szurestipus"]);
            $this->numberOfTimes = $this->bookingService->numberOfReservationRequired();
            for ($i = 1; $i <= $this->numberOfTimes; $i++) {
                $index = "";
                if ($this->numberOfTimes > 1) {
                    $index = $i;
                }

                $timeSelector = $this->_reservationTimeSelector($index);

                echo "<tr class='datarow'><td valign='middle'><div style=''>{$numberTexts[$index]}: *</div></td><td>{$timeSelector["html"]}</td></tr>";
                if (!empty($timeSelector["message"])) {
                    echo "<tr class='datarow'><td valign='middle'></td><td><div style='display:inline-block;padding:5px;color:white;background:red;'>{$timeSelector["message"]}</div></td></tr>";
                }
            }
            echo "<tr><td></td><td><div id='idopontvalasztodiv' style='display:none;width:100%;overflow: auto;'></div></td></tr>";
        } else {
            echo "<tr class='datarow'><td></td><td>{$nofoglalasText}</td></tr>";
        }

        //ezentúl csak üzemorvosi vizsgálatnál lesz doksi feltöltés mező
        if (!$this->utils->getFieldHidden("doksi") && $_POST["szurestipus"] == 1) {
            echo "<tr class='datarow'><td></td><td>";
            echo "<div class='datarow'>{$webText["dokfelinfo"]}</div>";
            echo "<div class='upload-btn-wrapper'><a href='#' class='upbtn newbutton'>{$webText["dokumentumfeltoltese"]}</a><input type='file' id='paciensfile' name='paciensfile[]' multiple /></div><img id='paciensloader' style='display:none;opacity:.5;height:30px;margin-left:10px;' src='/images/loading.svg' />";
            echo "</td></tr>";
            echo "<tr class='datarow'><td></td><td><div id='paciensfilediv'>" . $this->utils->showPaciensFiles() . "</div></td></tr>";
        }

        if (trim($_SESSION["helyszindata"]["telephelyek"]) != "") {
            echo "<tr class='datarow'><td>{$webText["munkaltato"]}: *</td><td><select name='telephely' id='telephely'>";
            $telephelyek = explode(",", $_SESSION["helyszindata"]["telephelyek"]);
            echo "<option value=''>{$webText["valasszmunkaltatot"]}!</option>";
            foreach ($telephelyek as $telephely) {
                $telephely = trim($telephely);
                echo "<option value='{$telephely}'" . ($_POST["telephely"] == $telephely ? " selected" : "") . ">{$telephely}</option>";
            }
            echo "</select></td></tr>";
        }

        echo $this->utils->dataField("taj");
        if (!CompanyService::isWaberers()) {
            echo $this->utils->dataField("email");
        }
        echo $this->utils->dataField("nev");
        echo $this->utils->dataField("telefon");
        echo $this->utils->dataField("szuldatum");
        echo $this->utils->dataField("szulhely");
        echo $this->utils->dataField("anyjaneve");
        echo $this->utils->dataField("neme");
        echo $this->utils->dataField("irsz");
        echo $this->utils->dataField("varos");
        echo $this->utils->dataField("utca");
        echo $this->utils->dataField("companytext");
        echo $this->utils->dataField("munkakor");
        echo $this->utils->dataField("adoszam");
        echo $this->utils->dataField("torzsszam");

        if (CompanyService::isFesztivalCompany()) {
            foreach (CompanyService::$fesztivalOnkentesQuestions as $key => $question) {
                echo "<tr><td>{$question["question_".$this->lang->selectedLang]}".($question["required"]?" *":"")."</td>";
                echo "<td>";
                echo "<input type='radio' value='1' " . (isset($_POST["question{$key}"]) && $_POST["question{$key}"] == 1 ? "checked" : "") . " name='question{$key}'>&nbsp;".$webText["igen"];
                echo "<input type='radio' value='0' " . (isset($_POST["question{$key}"]) && $_POST["question{$key}"] == 0 ? "checked" : "") . " name='question{$key}'>&nbsp;".$webText["nem"];
                echo "</td></tr>";
            }
        }

        //Oltási  adatok elkérése:
        if ($_SESSION["helyszindata"]["covid_oltas_bekeres"] == 1) {
            echo "<tr><td><strong>Kapott már oltást?</strong></td>";
            echo "<td><input class=\"vaccination-question-elements\" type=\"radio\" value=\"1\" " . (isset($_POST["is-vaccinated"]) && $_POST["is-vaccinated"] == 1 ? "checked" : "") . " name=\"is-vaccinated\">&nbsp;Igen</div>";
            echo "<input class=\"vaccination-question-elements\" type=\"radio\" value=\"0\" " . (!isset($_POST["is-vaccinated"]) || (isset($_POST["is-vaccinated"]) && $_POST["is-vaccinated"] == 0) ? "checked" : "") . " name=\"is-vaccinated\">&nbsp;Nem";
            echo "</td></tr>";

            echo "<tr id=\"vaccination-info-first-vaccine\" " . (isset($_POST["is-vaccinated"]) && $_POST["is-vaccinated"] == 1 ? "" : "style=\"display:none;\"") . "><td>1. oltás dátuma: *</td>";
            echo "<td>";

            echo "<select style=\"width:250px\" name=\"first-vaccination-type\">";
            echo "<option value=\"0\">Vakcina</option>";
            echo "<option " . (isset($_POST["first-vaccination-type"]) && $_POST["first-vaccination-type"] == "sinopharm" ? "selected=\"true\"" : "") . " value=\"sinopharm\">Sinopharm vakcina</option>";
            echo "<option " . (isset($_POST["first-vaccination-type"]) && $_POST["first-vaccination-type"] == "pfizer" ? "selected=\"true\"" : "") . " value=\"pfizer\">Pfizer</option>";
            echo "<option " . (isset($_POST["first-vaccination-type"]) && $_POST["first-vaccination-type"] == "johnson" ? "selected=\"true\"" : "") . " value=\"johnson\">Johnson & Johnson</option>";
            echo "<option " . (isset($_POST["first-vaccination-type"]) && $_POST["first-vaccination-type"] == "moderna" ? "selected=\"true\"" : "") . " value=\"moderna\">Moderna</option>";
            echo "<option " . (isset($_POST["first-vaccination-type"]) && $_POST["first-vaccination-type"] == "astrazeneca" ? "selected=\"true\"" : "") . " value=\"astrazeneca\">AstraZeneca</option>";
            echo "<option " . (isset($_POST["first-vaccination-type"]) && $_POST["first-vaccination-type"] == "szputnyik" ? "selected=\"true\"" : "") . " value=\"szputnyik\">Szputnyik V</option>";
            echo "</select>";

            echo "<select name=\"first-vaccine-year\">";
            echo "<option value=\"0\">Év</option>";
            $startYear = 2020;
            do {
                echo "<option " . (isset($_POST["first-vaccine-year"]) && $_POST["first-vaccine-year"] == $startYear ? "selected=\"true\"" : "") . " value=\"{$startYear}\">{$startYear}</option>";
                $startYear++;
            } while ($startYear <= date("Y"));
            echo "</select>&nbsp;";
            echo "<select name=\"first-vaccine-month\">";
            echo "<option value=\"0\">Hónap</option>";
            echo "<option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "01" ? "selected=\"true\"" : "") . " value=\"01\">Január</option><option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "02" ? "selected=\"true\"" : "") . " value=\"02\">Február</option><option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "03" ? "selected=\"true\"" : "") . " value=\"03\">Március</option>";
            echo "<option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "04" ? "selected=\"true\"" : "") . " value=\"04\">Április</option><option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "05" ? "selected=\"true\"" : "") . " value=\"05\">Május</option><option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "06" ? "selected=\"true\"" : "") . " value=\"06\">Június</option>";
            echo "<option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "07" ? "selected=\"true\"" : "") . " value=\"07\">Július</option><option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "08" ? "selected=\"true\"" : "") . " value=\"08\">Augusztus</option><option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "09" ? "selected=\"true\"" : "") . " value=\"09\">Szeptember</option>";
            echo "<option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "10" ? "selected=\"true\"" : "") . " value=\"10\">Október</option><option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "11" ? "selected=\"true\"" : "") . " value=\"11\">November</option><option " . (isset($_POST["first-vaccine-month"]) && $_POST["first-vaccine-month"] == "12" ? "selected=\"true\"" : "") . " value=\"12\">December</option>";
            echo "</select>&nbsp;";
            echo "<select name=\"first-vaccine-day\">";
            echo "<option value=\"0\">Nap</option>";
            for ($i = 1; $i <= 31; $i++) {
                $value = ($i < 10 ? "0" : "") . $i;
                echo "<option " . ($_POST["first-vaccine-day"] == $value ? "selected=\"true\"" : "") . " value=\"{$value}\">{$i}</option>";
            }
            echo "</select>";
            echo "</td></tr>";

            echo "<tr id=\"vaccination-info-second-vaccine\" " . (isset($_POST["is-vaccinated"]) && $_POST["is-vaccinated"] == 1 ? "" : "style=\"display:none;\"") . "><td>2. oltás dátuma:</td>";
            echo "<td>";

            echo "<select style=\"width:250px\" name=\"second-vaccination-type\">";
            echo "<option value=\"0\">Vakcina</option>";
            echo "<option " . (isset($_POST["second-vaccination-type"]) && $_POST["second-vaccination-type"] == "sinopharm" ? "selected=\"true\"" : "") . " value=\"sinopharm\">Sinopharm vakcina</option>";
            echo "<option " . (isset($_POST["second-vaccination-type"]) && $_POST["second-vaccination-type"] == "pfizer" ? "selected=\"true\"" : "") . " value=\"pfizer\">Pfizer</option>";
            echo "<option " . (isset($_POST["second-vaccination-type"]) && $_POST["second-vaccination-type"] == "johnson" ? "selected=\"true\"" : "") . " value=\"johnson\">Johnson & Johnson</option>";
            echo "<option " . (isset($_POST["second-vaccination-type"]) && $_POST["second-vaccination-type"] == "moderna" ? "selected=\"true\"" : "") . " value=\"moderna\">Moderna</option>";
            echo "<option " . (isset($_POST["second-vaccination-type"]) && $_POST["second-vaccination-type"] == "astrazeneca" ? "selected=\"true\"" : "") . " value=\"astrazeneca\">AstraZeneca</option>";
            echo "<option " . (isset($_POST["second-vaccination-type"]) && $_POST["second-vaccination-type"] == "szputnyik" ? "selected=\"true\"" : "") . " value=\"szputnyik\">Szputnyik V</option>";
            echo "</select>";

            echo "<select name=\"second-vaccine-year\">";
            echo "<option value=\"0\">Év</option>";
            $startYear = 2020;
            do {
                echo "<option " . (isset($_POST["second-vaccine-year"]) && $_POST["second-vaccine-year"] == $startYear ? "selected=\"true\"" : "") . " value=\"{$startYear}\">{$startYear}</option>";
                $startYear++;
            } while ($startYear <= date("Y"));
            echo "</select>&nbsp;";
            echo "<select name=\"second-vaccine-month\">";
            echo "<option value=\"0\">Hónap</option>";
            echo "<option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "01" ? "selected=\"true\"" : "") . " value=\"01\">Január</option><option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "02" ? "selected=\"true\"" : "") . " value=\"02\">Február</option><option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "03" ? "selected=\"true\"" : "") . " value=\"03\">Március</option>";
            echo "<option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "04" ? "selected=\"true\"" : "") . " value=\"04\">Április</option><option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "05" ? "selected=\"true\"" : "") . " value=\"05\">Május</option><option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "06" ? "selected=\"true\"" : "") . " value=\"06\">Június</option>";
            echo "<option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "07" ? "selected=\"true\"" : "") . " value=\"07\">Július</option><option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "08" ? "selected=\"true\"" : "") . " value=\"08\">Augusztus</option><option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "09" ? "selected=\"true\"" : "") . " value=\"09\">Szeptember</option>";
            echo "<option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "10" ? "selected=\"true\"" : "") . " value=\"10\">Október</option><option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "11" ? "selected=\"true\"" : "") . " value=\"11\">November</option><option " . (isset($_POST["second-vaccine-month"]) && $_POST["second-vaccine-month"] == "12" ? "selected=\"true\"" : "") . " value=\"12\">December</option>";
            echo "</select>&nbsp;";
            echo "<select name=\"second-vaccine-day\">";
            echo "<option value=\"0\">Nap</option>";
            for ($i = 1; $i <= 31; $i++) {
                $value = ($i < 10 ? "0" : "") . $i;
                echo "<option " . ($_POST["second-vaccine-day"] == $value ? "selected=\"true\"" : "") . " value=\"{$value}\">{$i}</option>";
            }
            echo "</select>";
            echo "</td></tr>";

            echo "<tr id=\"vaccination-info-third-vaccine\" " . (isset($_POST["is-vaccinated"]) && $_POST["is-vaccinated"] == 1 ? "" : "style=\"display:none;\"") . "><td>3. oltás dátuma:</td>";
            echo "<td>";

            echo "<select style=\"width:250px\" name=\"third-vaccination-type\">";
            echo "<option value=\"0\">Vakcina</option>";
            echo "<option " . (isset($_POST["third-vaccination-type"]) && $_POST["third-vaccination-type"] == "sinopharm" ? "selected=\"true\"" : "") . " value=\"sinopharm\">Sinopharm vakcina</option>";
            echo "<option " . (isset($_POST["third-vaccination-type"]) && $_POST["third-vaccination-type"] == "pfizer" ? "selected=\"true\"" : "") . " value=\"pfizer\">Pfizer</option>";
            echo "<option " . (isset($_POST["third-vaccination-type"]) && $_POST["third-vaccination-type"] == "johnson" ? "selected=\"true\"" : "") . " value=\"johnson\">Johnson & Johnson</option>";
            echo "<option " . (isset($_POST["third-vaccination-type"]) && $_POST["third-vaccination-type"] == "moderna" ? "selected=\"true\"" : "") . " value=\"moderna\">Moderna</option>";
            echo "<option " . (isset($_POST["third-vaccination-type"]) && $_POST["third-vaccination-type"] == "astrazeneca" ? "selected=\"true\"" : "") . " value=\"astrazeneca\">AstraZeneca</option>";
            echo "<option " . (isset($_POST["third-vaccination-type"]) && $_POST["third-vaccination-type"] == "szputnyik" ? "selected=\"true\"" : "") . " value=\"szputnyik\">Szputnyik V</option>";
            echo "</select>";

            echo "<select name=\"third-vaccine-year\">";
            echo "<option value=\"0\">Év</option>";
            $startYear = 2020;
            do {
                echo "<option " . (isset($_POST["third-vaccine-year"]) && $_POST["third-vaccine-year"] == $startYear ? "selected=\"true\"" : "") . " value=\"{$startYear}\">{$startYear}</option>";
                $startYear++;
            } while ($startYear <= date("Y"));
            echo "</select>&nbsp;";
            echo "<select name=\"third-vaccine-month\">";
            echo "<option value=\"0\">Hónap</option>";
            echo "<option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "01" ? "selected=\"true\"" : "") . " value=\"01\">Január</option><option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "02" ? "selected=\"true\"" : "") . " value=\"02\">Február</option><option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "03" ? "selected=\"true\"" : "") . " value=\"03\">Március</option>";
            echo "<option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "04" ? "selected=\"true\"" : "") . " value=\"04\">Április</option><option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "05" ? "selected=\"true\"" : "") . " value=\"05\">Május</option><option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "06" ? "selected=\"true\"" : "") . " value=\"06\">Június</option>";
            echo "<option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "07" ? "selected=\"true\"" : "") . " value=\"07\">Július</option><option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "08" ? "selected=\"true\"" : "") . " value=\"08\">Augusztus</option><option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "09" ? "selected=\"true\"" : "") . " value=\"09\">Szeptember</option>";
            echo "<option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "10" ? "selected=\"true\"" : "") . " value=\"10\">Október</option><option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "11" ? "selected=\"true\"" : "") . " value=\"11\">November</option><option " . (isset($_POST["third-vaccine-month"]) && $_POST["third-vaccine-month"] == "12" ? "selected=\"true\"" : "") . " value=\"12\">December</option>";
            echo "</select>&nbsp;";
            echo "<select name=\"third-vaccine-day\">";
            echo "<option value=\"0\">Nap</option>";
            for ($i = 1; $i <= 31; $i++) {
                $value = ($i < 10 ? "0" : "") . $i;
                echo "<option " . ($_POST["third-vaccine-day"] == $value ? "selected=\"true\"" : "") . " value=\"{$value}\">{$i}</option>";
            }
            echo "</select>";
            echo "</td></tr>";
        }



        if (!isset($beutalodata)) {
            //apollo tyres kivétel
            /*if (CompanyService::isApollo()) {
                echo "<tr class='datarow'>";
                echo "<td>{$webText["vizsgalati-ok"]}:</td>";
                echo "<td>";
                //Indiába menő, előzetes, soron kívüli, Indiából hazatérő. Illetve: Hollandiába menő, előzetes, soron kívüli, Hollandiából hazatérő

                $vizsgOkArray = array("(Munkába lépés előtti)","(Időszakos)","(Munkakör változás miatt)","(Záró alkalmassági)","(Egyéb)","(Nincs további)",
                                      "(30 napon túli keresőképtelenség)","(Vissza kell mennie)","(Konzultáció)","(Szemüveg)","(Időszakos/Emelőgép időszakos)",
                                      "(Emelőgép tanfolyam)","(Hegesztő tanfolyamhoz)","(Időszakos + Párizs 20)","(Párizs 20)","(Póni)",
                                    );

                echo "<select id=\"vizsglati-ok-list\" style=\"width:100%\" placeholder=\"Többet is választhatsz\" readonly=\"true\" onChange='selectVizsgOk()' multiple>";
                //echo "  <option ".(isset($_SESSION["vizsgalati-ok"]) && in_array("(Munkába lépés előtti)",$_SESSION["vizsgalati-ok"])?"selected=\"true\"":"")." value=\"(Munkába lépés előtti)\">Munkába lépés előtti</option>";
                foreach($vizsgOkArray as $reason){
                    echo "<option ".(isset($_SESSION["vizsgalati-ok"]) && in_array($reason,$_SESSION["vizsgalati-ok"])?"selected=\"true\"":"")." value=\"{$reason}\">".$webText[$reason]."</option>";
                }
                echo "</select>";
            }*/
            echo "<tr class='datarow'><td>{$webText["megjegyzes"]}:</td><td><div id='fogleuwarn' style='display:none;margin-top:5px;color:#f00;font-weight:bold;'>Kérjük adja meg a megjegyzés rovatban a céget, ahonnan érkezik</div>";
            echo "<textarea class='inputbox' style='height:100px;width:100%;box-sizing:border-box;' name='megj' id='foglmegj'>{$_POST["megj"]}</textarea>";
            echo "</td></tr>";
            
           
        }

        if (CompanyService::isUniqa()) {
            $webText["aszfelf"] = "Az <a href=\"#adatvedelmilink#\" target=\"_blank\">Adatvédelmi tájékoztatót</a> elolvastam, a fenti adatkezeléshez hozzájárulok, valamint a foglalás elküldésével elfogadom, hogy tudomásom van arról, hogy a Biztosító a Rendezvény megszervezése, a Rendezvényre történő regisztráció lebonyolítása és az általam kért vizsgálatok elvégzése céljából igénybe veszi a Hungária-Med M Kft. (HUNGÁRIA-MED M Kereskedelmi és Szolgáltató Korlátolt Felelősségű Társaság, székhely: 1132 Budapest, Csanády u. 6. B. ép. V. em. 2., a továbbiakban: „Hungária-Med M” vagy „Adatfeldolgozó”) orvosi szolgáltatásait.";
        }

        if (CompanyService::isFesztivalCompany()) {
            $webText["aszfelf"].= " <br/>".$webText["eltitkoltaszf"];
        }

        if (!isset($_SESSION["user"])) {
            //echo "<tr class='datarow'><td></td><td><div class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG' style='width:200px;'></div></td></tr>";
            if (CompanyService::isAuchan()) {
                echo "<tr class='datarow'><td></td><td><div style='margin-top:10px;max-width: 800px;'><input type='checkbox' name='aszf' value='1' " . (isset($_POST["aszf"]) ? "checked" : "") . "/> Az <a href='https://keltexmed.hu/site/images/ADATVEDELMI_TAJEKOZTATO_keltexmed_v.pdf' target='_blank' >Adatvédelmi tájékoztatót</a> és az <a target='_blank' href='https://www.keltexmed.hu/site/images/keltexmed_aszf.pdf'>ÁSZF</a>-et elolvastam, a fenti adatkezeléshez hozzájárulok.</div></td></tr>";
            } else {
                echo "<tr class='datarow'><td></td><td><div style='margin-top:10px;max-width: 800px;'><input type='checkbox' name='aszf' value='1' " . (isset($_POST["aszf"]) ? "checked" : "") . "/> {$webText["aszfelf"]}</div></td></tr>";
            }
        }
        if(!CompanyService::isSuzukiGHC()){
            echo "<tr class='datarow'><td></td><td><div style='margin-top:10px;max-width: 800px;'><input type='checkbox' name='gdpr' value='1' " . (isset($_POST["gdpr"]) ? "checked" : "") . "/> {$webText["gdprfelf"]}</div></td></tr>";
        }
        

        /*if (CompanyService::isAstostecCompany()) {
            echo "<tr class='datarow'><td></td><td><div style='margin-top:10px;max-width: 800px;'><input type='checkbox' name='tudoszuroelf' value='1' " . (!empty($_POST["tudoszuroelf"]) ? "checked" : "") . "/> {$webText["tudoszuroelf"]}</div></td></tr>";
        }*/

        //$submitButtonText = $webText["idopontfoglalasa"];
        $submitButtonText = $this->lang->getText("foglalasveglegesitese", "Foglalás véglegesítése");

        if ($this->bookingService->isOnlineTipus($_POST["szurestipus"])) {
            $priceData = $this->bookingService->getPriceData($_POST["szurestipus"]);
            $submitButtonText.= " és fizetés ({$priceData["price"]} Ft)";
            echo "<tr><td></td><td><div style='margin-top:10px;'><input type='checkbox' name='simplepay' value='1' ".(isset($_POST["simplepay"])?"checked":"")."/> Elfogadom a <a style='' href='http://simplepartner.hu/PaymentService/Fizetesi_tajekoztato.pdf' target='_blank'>SimplePay feltételeit.</a></div></td></tr>";
        }

        echo "<tr class='datarow'><td></td><td><div style='margin-top:20px;'>";
        echo "<a id='resbutton' href='#' class='newbutton' onclick='reservationSubmit();return false;'><span id='resbuttonloading' style='display:none;'><i class='fa-solid fa-rotate fa-spin'></i>&nbsp;&nbsp;</span>{$submitButtonText}</a>";


        if(!CompanyService::isSuzukiGHC()){
            echo "<div id='warnidopontpress' style='display:none;color:#a00;margin:10px 0px 0px 5px;'><i class='fa-solid fa-hand-point-up fa-bounce'></i>&nbsp;&nbsp;{$webText["idopontfoglalasawarn"]}</div>";
        }

        echo "<div></td></tr>";

        echo "</table>";

        if (!isset($_SESSION["orvosselected"])) $_SESSION["orvosselected"] = 0;

        if (isset($_SESSION["user"])) echo "<input type='hidden' name='aszf' value='1'/>";
        echo "<input type='hidden' name='idopontfoglalas' value='1'/>";
        echo "<input type='hidden' name='version2' value='1'/>";
        echo "<input type='hidden' name='silentmode' id='silentmode' value='0'/>";
        //echo "<input type='hidden' name='orvosselected' id='orvosselected' value='{$_SESSION["orvosselected"]}'/>";

        echo "</form>";
    }

    private function record_covid_vaccination_data($fid, $data)
    {
        if (isset($data["first-vaccination-type"]) && $foglalasData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?", array($fid)))) {
            sql_query(
                "UPDATE foglalasok SET elso_covid_vakcina_tipus=?, masodik_covid_vakcina_tipus=?, harmadik_covid_vakcina_tipus=?, elso_covid_oltas=?, masodik_covid_oltas=?,harmadik_covid_oltas=? WHERE id=?",
                array($data["first-vaccination-type"], $data["second-vaccination-type"], $data["third-vaccination-type"], $data["first-vaccine-date"], $data["second-vaccine-date"], $data["third-vaccine-date"], $fid)
            );
            
            return "SUCCESS";
        }
        else return "FAILED";
    }

    private function _reservationTimeSelector($index = ""):array {
        $webText = $this->lang->webText;

        $dateStyle = (!empty($_POST["datum{$index}"]) ? "background-image:url(images/check.png);" : "") . "background-repeat:no-repeat;background-position:right 5px center;width:150px;height:24px;margin-right:5px;padding:4px 5px;font-size:16px;";
        $dateVal = substr($_POST["datum{$index}"], 0, 16);

        if (!isset($_POST["orvosselected{$index}"])) {
            $_POST["orvosselected{$index}"] = 0;
        }

        $enableCache  = false;
        $freeFound    = false;
        $skipScan     = false;
        $firstFreeDay = 0;
        $testDay      = 0;
        $helyszin     = intval($_POST["helyszin"]);
        $szurestipus  = intval($_POST["szurestipus"]);
        $message      = "";

        if (CompanyService::isAuchan() || Booking_Constants::SQL_DB == "keltexmed") {
            $enableCache = false;
        }

        if (CompanyService::isFGSZ() || CompanyService::isFogleu()) {
            $skipScan = true;
            $freeFound = true;
        }

        if (!$skipScan) {
            if (isset($_SESSION["firstfreeday{$szurestipus}_{$helyszin}"]) && $enableCache) {
                $firstFreeDay = $_SESSION["firstfreeday{$szurestipus}_{$helyszin}"];
                $freeFound = true;
            } else {
                while ($testDay < 100) {
                    $this->bookingService->setHelyszin($helyszin);
                    $this->bookingService->setSzuresTipus($szurestipus);
                    $this->bookingService->setHonnan($testDay);
                    $json = $this->bookingService->showIdoPontValasztoV2();

                    if (substr_count($json, "foglaltbtn")) {
                        $firstFreeDay = $testDay;
                    }

                    if (substr_count($json, "foglalhatobtn")) {
                        $firstFreeDay = $_SESSION["firstfreeday{$szurestipus}_{$helyszin}"] = $testDay;
                        $freeFound = true;
                        break;
                    }
                    $testDay += 7;
                }
            }
        }

        $html = "";
        $html .= "<div style='display:table;'>";
        $html .= "<div style='display:table-row;'>";
        $html .= "<div style='display:table-cell;'>";
        $html .= "<input type='hidden' name='orvosselected' id='orvosselected' value='{$_POST["orvosselected{$index}"]}'/>";
        $html .= "<input type='hidden' name='rinterval{$index}' id='rinterval{$index}' value='{$_POST["rinterval{$index}"]}' />";
        $html .= "<input type='hidden' name='datum{$index}' id='datum{$index}' value='{$dateVal}' />";
        $html .= "<div class=\"input-group mb-3\" style=\"min-width: 280px;overflow:auto !important\";>";
       
        
        if(CompanyService::isSuzukiGHC()){
            $html .= "<input type=\"text\" style=\"font-family: SuzukiProRegular !important;\" class=\"form-control\" placeholder=\"{$webText["kattintsagombra"]}\" readonly='true' name='datumText{$index}' id='datumText{$index}' value='{$_POST["datumText{$index}"]}' aria-label=\"{$webText["kattintsagombra"]}\" aria-describedby=\"datumText{$index}\">";
            $html .= "<button class=\"btn btn-hungariamed\" style=\"font-family: SuzukiProRegular !important;\" onclick='setDatumIndex(\"{$index}\");showIdoPontValasztoV2({$firstFreeDay});return false;' type=\"button\">{$webText["idopontvalasztas"]}</button>";
        }else{
            $deaultResButtonTitle = $resButtonTitle = "<i class='fa-solid fa-calendar-days'></i>&nbsp;&nbsp;{$webText["idopontvalasztas"]}";
            if (!empty($_POST["datumText{$index}"])) {
                $resButtonTitle = "{$_POST["datumText{$index}"]}&nbsp;&nbsp;<i class='fa-solid fa-circle-check'></i>";
            }

            $html .= "<input type='hidden' name='datumText{$index}' id='datumText{$index}' value='{$_POST["datumText{$index}"]}'>";
            $html .= "<a class='newbutton' onclick='setDatumIndex(\"{$index}\");showIdoPontValasztoV2({$firstFreeDay});return false;'><span data-defaulttitle{$index}=\"{$deaultResButtonTitle}\" id='resbutton{$index}'>{$resButtonTitle}</span><span id='loadingspinner{$index}' style='margin-left:5px;display:none;'>&nbsp;<i class='fa-solid fa-spinner fa-spin'></i></span></a>";
        }
        
        $html .= "</div>";
        
        $html .= "</div>";
        $html .= "</div>";
        $html .= "</div>";

        if (!$freeFound && !empty($helyszin) && !empty($szurestipus)) {
            $serviceData = sql_query("select ispack from szurestipusok where id=?", [$szurestipus])->fetch(PDO::FETCH_ASSOC);
            if ($serviceData["ispack"] == 0) {
                $message = "Sajnáljuk, erre a rendelésre pillanatnyilag nincs szabad időpontunk.";
            }
        }

        return ["html" => $html, "message" => $message];
    }

    private function _szuresTipusValasztoNew($selected = 0, $onlyselected = 0):string {
        $tipusok = [];
        $tipusnevek = [];
        $suzukiDisabled = "";


        if(CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser() || CompanyService::isSuzukiGHC() || CompanyService::isAszMenedzser()){
            $suzukiDisabled = "disabled=\"true\"";
        }

        if(CompanyService::isSuzukiGHC()){
            $suzukiDisabled = "disabled=\"true\"";
            if(isset($_SESSION["user"])){
                if($result = sql_fetch_array(sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=?",array($_SESSION["user"]["torzsszam"])))){
                    $selected = $result["csomagid"];
                }
            }
            
        }

        $rest = sql_query("select * from szurestipusok");
        while ($rowt = sql_fetch_array($rest)) {
            if ($_COOKIE["lang"] != "hu" && trim($rowt["megnev_{$_COOKIE["lang"]}"]) != "") {
                $rowt["megnev"] = $rowt["megnev_{$_COOKIE["lang"]}"];
            }
            $tipusnevek[$rowt["id"]] = $rowt["megnev"];
        }

        $disabled = "";
        if (isset($_SESSION["labcode"])) {
            $disabled = "disabled";
        }


        $res = sql_query("SELECT tipusok FROM orvos_beosztas_new b WHERE (instr(b.beocegek, ?) or b.beocegek='') and b.aktiv=1 and b.nap<>0 and (nap<10 or (nap=10 and beonap>=date(now()))) and b.noreservation=0", ["|{$_SESSION["helyszindata"]["id"]}|"]);
        while ($row = sql_fetch_array($res)) {
            $ta = explode("|", $row["tipusok"]);
            for ($i = 0; $i < count($ta); $i++) {
                if (trim($ta[$i]) != "" && !in_array($ta[$i], $tipusok)) {
                    $tipusok[] = $ta[$i];
                }
            }
        }

        if (!empty($this->telephelyek) && !empty($_POST["selectedtelephely"])) {
            if ($telephelyData = sql_query("select * from cegvars where id=? and szurestipusids<>''", [$_POST["selectedtelephely"]])->fetch(PDO::FETCH_ASSOC)) {
                $validTipusok = json_decode($telephelyData["szurestipusids"], JSON_OBJECT_AS_ARRAY);
            }
        }

        $valasszon = $this->lang->webText["valasszon"];
        if (!empty($this->telephelyek) && empty($_POST["selectedtelephely"])) {
            $tipusok = [];
            $valasszon = "Válassza ki előbb a telephelyet";
            if (CompanyService::isBME()) {
                $valasszon = "Válassza ki előbb a tanszéket!";
            }
        }

        $htmlout = "";
        if($suzukiDisabled){
            $htmlout .= "<input type=\"hidden\" id=\"szurestipushidden\" name=\"szurestipus\" value=\"".($_REQUEST["szurestipus"] ?? $selected)."\">";
        }
        $htmlout .= "<select name='szurestipus' id='szurestipus' onchange='reservedTimeInvalidate();' style='width:100%;' {$suzukiDisabled}>";
        $htmlout .= "<option {$disabled} value='0'>{$valasszon}!</option>";

        if (isset($tipusok)) {
            foreach ($tipusok as $tipus) {
                if (CompanyService::isBudapestBrand() && $tipus == 15) {
                    continue;
                }
                @$tipusdisplay[$tipus] = $tipusnevek[$tipus];
            }
            if (isset($tipusdisplay)) {
                asort($tipusdisplay);
                foreach ($tipusdisplay as $key => $value) {
                    //if (count($tipusdisplay)==1) $selected=$key;
                    if ($onlyselected == 1 && $key != $selected) continue;
                    if (trim($value) == "") continue;

                    if (isset($validTipusok)) {
                        if (!in_array($key, $validTipusok)) {
                            continue;
                        }
                    }

                    if (count($tipusdisplay) == 1) {
                        $selected = $_REQUEST["szurestipus"] = $_POST["szurestipus"] = $key;
                    }

                    $disabled = "";
                    if ($selected != $key && isset($_SESSION["labcode"])) {
                        $disabled = "disabled";
                    }

                    $htmlout .= "<option {$disabled} value='{$key}'" . ($selected == $key ? " selected" : "") . ">{$value}</option>";
                }
            }
        }

        $htmlout .= "</select>";

        return $htmlout;
    }

    private function _reservationPlaceSelectorNew($forcedSzurestipusId=null):string {
        $html        = "";
        $szuresTipus = $_POST["szurestipus"] ?? $forcedSzurestipusId;
        $webText     = $this->lang->webText;
        $helyszinek  = $this->bookingService->beosztasService->getReservationPlaces($_SESSION["helyszindata"]["id"], $szuresTipus);
        $numOfH      = count($helyszinek);
        $disabled    = "";
        $validPlaces = [];

        if($forcedSzurestipusId){
            $szuresTipus = $forcedSzurestipusId;
        }

        $_SESSION["orvosselected"] = 0;

        if (!empty($this->telephelyek) && empty($_POST["selectedtelephely"])) {
            $helyszinek = [];
            $webText["valasszhelyszint"] = "Válassza ki előbb a telephelyet!";
            if (CompanyService::isBME()) {
                $webText["valasszhelyszint"] = "Válassza ki előbb a tanszéket!";
            }
        }

        if (!empty($this->telephelyek) && !empty($_POST["selectedtelephely"])) {
            if ($telephelyData = sql_query("select * from cegvars where id=? and placeids<>''", [$_POST["selectedtelephely"]])->fetch(PDO::FETCH_ASSOC)) {
                $validPlaces = json_decode($telephelyData["placeids"], JSON_OBJECT_AS_ARRAY);
            }
        }

        $html .= "<select name='helyszin' id='helyszin' onchange='reservedTimeInvalidate();' style='width:100%;' {$disabled}>";
        $html .= "<option value='0'>{$webText["valasszhelyszint"]}</option>";

        if (!empty($szuresTipus)) {
            if (Booking_Constants::SQL_DB == "keltexmed") {
                //tüdőszűrés esetén csak fehérvári út legyen választható
                if (isset($_POST["tudoszuro"]) && $_POST["tudoszuro"] == 1) {
                    $validPlaces[] = Booking_Constants::DEFAULT_PLACE_IDS[0];
                }
            }

            foreach ($helyszinek as $rowt) {
                if (!empty($validPlaces)) {
                    if (!in_array($rowt["id"], $validPlaces)) {
                        continue;
                    }
                }

                if ($_SESSION["helyszindata"]["nocim"] == 1) {
                    $rowt["cim"] = $rowt["megnev"];
                }
                $html .= "<option value='{$rowt["id"]}'" . (isset($_POST["helyszin"]) && $_POST["helyszin"] == $rowt["id"] || $numOfH == 1 ? " selected" : "") . ">{$rowt["cim"]}</option>";
                if ($numOfH == 1) {
                    $_POST["helyszin"] = $rowt["id"];
                }
            }
        }
        $html .= "</select>";

        //if($disabled){
        //    $html .= "<input type=\"hidden\" name=\"helyszin\" id=\"helyszin\" value=\"".(isset($_POST["helyszin"])?$_POST["helyszin"]:"")."\"/>";
        //}

        $html .= "<div id='helyszinvalasztowarn' style='display:none;background:#ff6961;color:#fff;font-size:16px;padding:10px;margin:10px 0px 0px 0px;'>Figyelem! Ha a győri címünkre szeretne foglalni, használja a győri bejelentkezési felületünket, majd ott kövesse az \"üzemorvosi vizsgálat\" linket. Foglalását telefonon is megteheted a következő számon: +36 20 373 3343<br/><br/><a class='newbutton' href='https://gyor-bejelentkezes.hungariamed.hu'>Folytatás a győri bejelentkező felületen</a></div>";

        return $html;
    }

    private function _telephelySelector():string {
        $html = "";
        $num = count($this->telephelyek);

        $telephelySelectText = "Válasszon telephelyet!";
        if (CompanyService::isBME()) {
            $telephelySelectText = "Válasszon tanszéket!";
        }

        $html .= "<select name='selectedtelephely' id='selectedtelephely' onchange='silentBookingPost();' style='width:100%;'>";
        $html .= "<option value='0'>{$telephelySelectText}</option>";

        foreach ($this->telephelyek as $rowt) {
            if ($rowt["parentid"] == 0) {
                $disabled = "";
                if ($rowt["selectable"] == 0) {
                    $disabled = "disabled style='background:#888;color:#fff;'";
                }
                $html .= "<option {$disabled} value='{$rowt["id"]}'" . (isset($_POST["selectedtelephely"])&&$_POST["selectedtelephely"] == $rowt["id"] || $num == 1 ? " selected" : "") . ">{$rowt["megnev"]}</option>";

                foreach ($this->telephelyek as $telephely) {
                    if ($telephely["parentid"] == $rowt["id"]) {
                        $html .= "<option value='{$telephely["id"]}'" . (isset($_POST["selectedtelephely"])&&$_POST["selectedtelephely"] == $telephely["id"] || $num == 1 ? " selected" : "") . ">{$telephely["megnev"]}</option>";
                        if ($num == 1) {
                            $_POST["selectedtelephely"] = $telephely["id"];
                        }
                    }
                }
            }
        }

        $html .= "</select>";

        return $html;
    }

    private function _preSelectForm()
    {
        if (isset($_GET["enabletest"])) {
            $_SESSION["enabletest"] = 1;
        }

        if (isset($_SESSION["labcode"])) {
            unset($_SESSION["labcode"]);
        }

        $webText = $this->lang->webText;
        $html    = "";

        $introText = $this->lang->getText("miert.bennunket.description.2", "");
        if (Booking_Constants::SQL_DB == "keltexmed") {
            $introText = $this->lang->getText("miert.bennunket.keltexmed", "");
        }



        foreach (Booking_Constants::DEFAULT_PLACE_IDS as $helyszinId) {
            $services = $this->bookingService->getPublicServices($helyszinId);

            $html .= "<div style='text-align:center;margin-top:10px;'>";

            if (!isset($_GET["menedzserszures"])) {
                $html .= "<h2 style='font-size:32px;font-family:robotolight;margin:20px 0px 15px 0px;'>{$webText["szakrendelesek"]}</h2>";
                if (count(Booking_Constants::DEFAULT_PLACE_IDS) > 1) {
                    $helyszinData = sql_query("select cim from helyszinek where id=?", [$helyszinId])->fetch(PDO::FETCH_ASSOC);
                    $html .= "<div style='font-size: 24px;'>{$helyszinData["cim"]}</div>";
                }
                $html .= $this->lang->getText("foglalas.inditas", "Kattintson a szakrendelés nevére a foglalás indításához!") . "<br/><br/>";
            }

            $managerBoxes = "";

            foreach ($services as $tipusData) {
                if (($tipusData["megnev"] == "Szemészet____" || $tipusData["megnev"] == "Menedzserszűrés") && Booking_Constants::SQL_DB == "hungariamed") {
                    //szemészet most éppen van
                    continue;
                }

                if ((substr_count($tipusData["megnev"], "GHC ") || substr_count($tipusData["megnev"], "Várkap") || substr_count($tipusData["megnev"], "EDAG ") || substr_count($tipusData["megnev"], "Brand")) && Booking_Constants::SQL_DB == "hungariamed") {
                    continue;
                }

                if(in_array($tipusData["id"],[287])){
                    continue;
                }

                $tipusData["megnev"] = Lang::multiLangField($tipusData, "megnev");

                if (empty($tipusData["facode"])) {
                    $tipusData["facode"] = "<i class='fas fa-hospital'></i>";
                }

                if ($tipusData["webdoktor"] == 1) {
                    $tipusData["facode"] = "<i class='fas fa-laptop-medical'></i>";
                }

                $box = "<div class='vizsgalatdoboz_".Booking_Constants::SQL_DB . ($tipusData["webdoktor"] == 1 ? " vizsgalatdobozwebdoctor" : "") . "' onclick='extendedReservationSelect({$tipusData["id"]},{$helyszinId},{$tipusData["noreservation"]});return false;'>";
                $box .= "  <div style='height:140px'>";
                $box .= "    <div style='font-size: 56px;padding:5px 10px 10px 10px;color:#fff;'>{$tipusData["facode"]}</div>";
                $box .= "    <div style='font-family: robotoregular;'>{$tipusData["megnev"]}</div>";
                $box .= "  </div>";
                $box .= "  <div class='" . ($tipusData["webdoktor"] == 1 ? "vizsgalatdobozbuttonwebdoctor" : "vizsgalatdobozbutton_".Booking_Constants::SQL_DB) . "'>" . ($tipusData["webdoktor"] == 1 ? $webText["megrendelem"] : $webText["idopontfoglalas_gomb"]) . "</div>";
                $box .= "</div>";

                if (substr_count($tipusData["megnev"], "HMM ")) {
                    $managerBoxes.= $box;
                    $box = "";
                }

                if (!isset($_GET["menedzserszures"])) {
                    $html .= $box;
                }
            }

            if (!empty($managerBoxes)) {
                $html .= "<h2 style='font-size:32px;font-family:robotolight;margin:20px 0px 15px 0px;'>".$this->lang->getText("menedzserszures", "Menedzserszűrés") . "</h2>";
                $html .= $this->lang->getText("manager.inditas", "Az időpontfoglalás indításához kattintson a menedzserszűrés csomagra!") . "<br/><br/>";
                $html.= $managerBoxes;
            }

            $html .= "</div>";
        }

        if (!empty($introText)) {
            $html .= "<div style='margin:40px 0px 0px 0px;padding:30px 20px 40px 20px;border-top:1px solid #888;'>";
            $html .= "<h2 style='font-size:32px;font-family:robotolight;'>" . $this->lang->getText("miert.bennunket", "Miért bennünket válasszon?") . "</h2>";
            $html .= $introText;
            $html .= "<div>";
        }


        return $html;
    }

    private function setAuchanWarning() {
        //return;

        if (CompanyService::isAuchan()) {
            if (strtotime("now") < strtotime("2023-09-18 09:00:00")) {
                $this->errors = [];
                $this->errors[] = self::AUCHAN_WARN;
            }
        }
    }

    private function ifStatement($query, $body) {

        $condition = eval("return $query;");

        if ($condition) return $body;
        return;
    }

    private function detectServiceSelect() {
        if (isset($_GET["service"]) && !isset($_POST["szurestipus"])) {
            if ($serviceData = sql_query("select id from szurestipusok where webalias=? limit 1", [$_GET["service"]])->fetch(PDO::FETCH_ASSOC)) {
                $_POST["szurestipus"] = $serviceData["id"];
                $_POST["helyszin"] = Booking_Constants::DEFAULT_PLACE_IDS[0];
            }
        }
    }

    private function setNotificatitonForPackage($szurestipusId){
        $notification = "";
        $csomag = sql_fetch_array(sql_query("SELECT megnev,csomagidotartam FROM szurestipusok WHERE id=?",array($szurestipusId)));


        if(CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser() || CompanyService::isSuzukiGHC()){
            $notification = "Kiválasztott csomag:<br> <strong>{$csomag["megnev"]}</strong><br>";
            $notification.= "<strong>Várható ellátási idő:</strong> <i>{$csomag["csomagidotartam"]}</i><br>";
            $notification.= "<br>Tartalma:";
            $q=sql_query("SELECT sz.megnev FROM szurescsomagok_kapcs szk 
                          LEFT JOIN szurestipusok sz ON szk.szurestipusid=sz.id
                          WHERE szk.csomagid=?",array($szurestipusId));

            while($res=sql_fetch_array($q)){
                $notification.= "<br>{$res["megnev"]}";
            }
            $notification.= "<br><br><strong>Amennyiben nem szeretne valamelyik vizsgálaton részt venni, kérem, kapcsolja ki a vizsgálat melletti checkboxot.</strong>";
        }
        return $notification;
    }
}
