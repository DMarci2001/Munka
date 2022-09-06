<?php

class BookingPage extends CorePage
{

    private BookingService $bookingService;
    public array $paymentMethods = [
        "utanvet" => "Utánvét",
        "simplepay" => "SimplePay"
    ];

    public function __construct()
    {
        parent::__construct();

        $this->bookingService = new BookingService();
        $webText = $this->lang->webText;

        if (isset($_GET["labcode"])) {
            //labshopból érkezés, labor foglaláshoz irányítás
            //https://bejelentkezes.hungariamed.hu/index.php?page=booking&labcode=ccb124b499f1a0d372e49adfe3fc18c3161913b92a32805ba8751ab0b345e354
            $_SESSION["labcode"] = $_GET["labcode"];
            header("location:index.php?page=booking&szurestipus=48&helyszin=1");
            die;
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


        if (isset($_POST["idopontfoglalas"])) {
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

            $laborszoveg = $this->bookingService->getLaborSzoveg();

            if (isset($_POST["szuldatumev"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            }

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            if (!$this->utils->getFieldHidden("taj") && $this->utils->getFieldRequired("taj")) {
                if (empty($_POST["taj"])) {
                    $this->errors[] = "{$webText["tajkotelezo"]}";
                }
            }

            //if ($_POST["taj"] == "") $this->errors[] = "{$webText["tajkotelezo"]}";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $this->errors[] = "{$webText["tajformat"]}";
            if ($_POST["helyszin"] == "0") $this->errors[] = "{$webText["helyszinkotelezo"]}";
            if ($_POST["szurestipus"] == "0") $this->errors[] = "{$webText["szurestipuskotelezo"]}";

            $this->bookingService->setSzuresTipus($_POST["szurestipus"]);
            $this->bookingService->setHelyszin($_POST["helyszin"]);
            $this->bookingService->setNeme($_POST["neme"]);

            //több dátum check
            if (isset($_POST["datum1"])) {
                $numberOfTimes = $this->bookingService->numberOfReservationRequired();

                $multipleTimes = $days = [];
                for ($i = 1; $i <= $numberOfTimes; $i++) {
                    if ($_POST["datum{$i}"] == "") {
                        $this->errors[] = "Kérjük adja meg a {$numberOfTimes} időpontot!";
                        break;
                    }
                    $multipleTimes[] = ["datum" => $_POST["datum{$i}"], "rinterval" => $_POST["rinterval{$i}"], "orvosselected" => $_POST["orvosselected{$i}"]];
                    $days[] = date("Y-m-d", strtotime($_POST["datum{$i}"]));

                    if (!$this->bookingService->checkIdopontSzabad(["datum" => $_POST["datum{$i}"], "orvosselected" => $_POST["orvosselected{$i}"]])) {
                        $this->errors[] = "{$webText["idopontlefoglaltak"]}";
                    }

                }

                if (count($days) == $numberOfTimes && count(array_unique($days)) != $numberOfTimes) {
                    $this->errors[] = "Kérjük különböző napokat jelöljön meg a {$numberOfTimes} időpont esetében!";
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
                if (empty($_POST["telefon"])) {
                    $this->errors[] = "{$webText["telkotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("szulhely") && $this->utils->getFieldRequired("szulhely")) {
                if (empty($_POST["szulhely"])) {
                    $this->errors[] = "{$webText["szulhelykotelezo"]}!";
                }
            }
            if (!$this->utils->getFieldHidden("szuldatum") && $this->utils->getFieldRequired("szuldatum")) {
                if (empty($_POST["szuldatum"])) {
                    $this->errors[] = "{$webText["szulkotelezo"]}";
                }
                if (!$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) {
                    $this->errors[] = "{$webText["szulformat"]}";
                } else {
                    if (strtotime($_POST["szuldatum"]) > strtotime("now - 1 day")) {
                        $this->errors[] = "{$webText["szulformat"]}";
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
            if (!$this->utils->getFieldHidden("munkakor") && $this->utils->getFieldRequired("munkakor")) {
                if (empty($_POST["munkakor"])) {
                    $this->errors[] = "{$webText["munkakorkotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("anyjaneve") && $this->utils->getFieldRequired("anyjaneve")) {
                if (empty($_POST["anyjaneve"])) {
                    $this->errors[] = "{$webText["anyjaneve"]}!";
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

            if (isset($_POST["telephely"]) && empty(trim($_POST["telephely"]))) {
                $this->errors[] = "{$webText["telephelykotelezo"]}";
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
                $this->errors[] = "A simplepay felhasználási feltételeit a vásárláshoz el kell elfogadnia!";
            }

            if (isset($_POST["simplepay"]) && $_POST["simplepay"] == 1) {
                $priceData = $this->bookingService->getPriceData($_POST["szurestipus"]);
                $_POST["totalprice"] = $priceData["price"];
            }

            if (!isset($_POST["rinterval"])) $_POST["rinterval"] = 0;
            if (!isset($_POST["telephely"])) $_POST["telephely"] = "";

            if (!isset($_SESSION["user"])) {
                $captchaError = $this->utils->checkCaptcha();
                if (!empty($captchaError)) {
                    $this->errors[] = $captchaError;
                }
            }


            if(!empty($laborszoveg)){
                $_POST["megj"].=" Válaszott labor csomagok: ".$laborszoveg;
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

            if (empty($this->errors)) {
                $forwardURL = $this->bookingService->addReservation($_POST);
                $fid = $this->bookingService->newReservationId;
                $this->record_covid_vaccination_data($fid,$_POST);
                header("location:{$forwardURL}");
                die();
            }
        }
    }

    public function showPage()
    {
        $webText = $this->lang->webText;

        if (!isset($_POST["helyszin"])) {
            $_POST["helyszin"] = $_POST["szurestipus"] = "";
        }

        if (isset($_GET["szurestipus"])) {
            $_POST["szurestipus"] = $_GET["szurestipus"];
        }

        if (isset($_GET["helyszin"])) {
            $_POST["helyszin"] = $_GET["helyszin"];
        }

        $tipusData = sql_fetch_array(sql_query("select * from szurestipusok where id=?", [$_POST["szurestipus"]]));

        if (!isset($_POST["email"])) {
            $_POST["datum"] = $_POST["datumText"] = $_POST["email"] = $_POST["nev"] = $_POST["telefon"] = $_POST["szuldatum"] = $_POST["taj"] = $_POST["irsz"] = $_POST["varos"] = $_POST["utca"] = $_POST["munkaltato"] = $_POST["munkakor"] = $_POST["nev"] = $_POST["nev"] = $_POST["megj"] = $_POST["captcha"] = $_POST["szulhely"] = $_POST["anyjaneve"] = $_POST["telephely"] = "";
            $_POST["rinterval"] = 0;
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

        echo $this->displayFejlec();
        echo $this->showErrors();

        if ($_SESSION["helyszindata"]["onlybeutalo"] == 1) {
            $_SESSION["helyszindata"]["onlyreg"] = 1;
        }

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

        if ($_SESSION["helyszindata"]["onlyreg"] == 1 && !isset($_SESSION["user"])) {
            $btext = $webText["mainudvozles"];

            if ($rowsz = sql_fetch_array(sql_query("select * from szovegek where cegid=? and tipus='welcome'", array($_SESSION["helyszindata"]["id"])))) {
                $btext = $rowsz["szoveg"];
            }

            echo "<div style=''>{$btext}</div>";

            echo "<div style='margin-top:20px;'><a href='index.php?page=registration' class='newbutton'>{$webText["regisztracio"]}</a>&nbsp;&nbsp;<a href='index.php?page=login' class='newbutton'>{$webText["bejelentkezes"]}</a></div>";
            return;
        }

        if ($this->isExtendedForm()) {
            echo $this->_preSelectForm();
            return;
        }

        echo "<form name='iform' id='iform' method='post' enctype='multipart/form-data'>";

        if ($this->bookingService->isOnlineTipus($_POST["szurestipus"]) && !isset($_SESSION["labcode"])) {
            echo "<div style='margin-bottom:20px;'>Tudnivalók a telemedicina szolgáltatásunkkal kapcsolatban:
            Köszönjük, hogy <strong>\"{$tipusData["megnev"]}\"</strong> szolgáltatásunkat választotta.  
            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.<br/><br>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</div>";
        }

        if (isset($_SESSION["labcode"])) {
            if ($labData = sql_fetch_array(sql_query("select * from labshop_vasarlasok where hash=?", [$_SESSION["labcode"]]))) {
                if (strtoupper($labData["status"]) == "FINISHED" || ($labData["status"] == "done" && $labData["payment_method"] == "utanvet")) {
                    echo "<div style='margin-bottom:20px;'>Már bejejezte a foglalást ehhez a vásárláshoz!<br/><br/>Amennyiben szeretne egy másik csomagot is választani, kérem <a href='https://labshop.hungariamed.hu'>kattintson ide</a>";
                    unset($_SESSION["labcode"]);
                    return;
                } else {
                    $packages = json_decode($labData["package_ids"], JSON_OBJECT_AS_ARRAY);

                    $packData = sql_fetch_array(sql_query("select name from synlab_labor_csomagok where id=?", [$labData["package_id"]]));
                    $items = [];
                    foreach ($packages as $package) {
                        $itemData = sql_fetch_array(sql_query("select name from synlab_labor_tetelek where id=?", [$package]));
                        $items[] = $itemData["name"];
                    }

                    $labItemsHTML = "<li style='list-style:outside;'>{$packData["name"]}<div style='font-size: 12px;'>" . implode(", ", $items) . "</div></li>";


                    echo "<div style='margin-bottom:20px;'>Ön a labshop vásárlásához készül időpontot foglalni. A vásálás értéke: <span style='font-family: robotobold;'>" . number_format($labData["fullprice"]) . " Ft</span>. Választott fizetési mód: <span style='font-family: robotobold;'>" . $this->paymentMethods[$labData["payment_method"]] . "</span>.<br/>
                <br>
                <span style='font-family: robotobold;'>Választott tételek:</span><br/>
                <ul>{$labItemsHTML}</ul>
                </div>";
                }
            }
        }


        echo "<table cellpadding='3' cellspacing='0'>";


        //Kérjük akkut egészségkárosodás vagy életveszély esetén azonnal hívja az 104-es országos mentőszolgálat vagy a 112 központi segélyhívót.

        if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_COOKIE["lang"] != "hu" && trim($_SESSION["helyszindata"]["beutaloszoveg_{$_COOKIE["lang"]}"]) != "") {
            $_SESSION["helyszindata"]["beutaloszoveg"] = $_SESSION["helyszindata"]["beutaloszoveg_{$_COOKIE["lang"]}"];
        }

        if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_SESSION["helyszindata"]["beutaloszoveg"] != "" && !$this->bookingService->isOnlineTipus($_POST["szurestipus"])) {
            echo "<tr><td></td><td><div style='font-weight:bold;padding:5px 0px;'>{$_SESSION["helyszindata"]["beutaloszoveg"]}</div><td></tr>";
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
            //beutaló nélkül szabad választás
            echo "<tr><td>{$webText["szurestipus"]}: *</td><td height='30'><div id='szurestipusvalaszto'>" . $this->_szuresTipusValasztoNew($_POST["szurestipus"]) . "</div></td></tr>";
            echo "<tr><td></td><td><div id=\"infopagetext\">".$this->bookingService->getInfoPageText($_POST["szurestipus"], $_POST)."</div></td></tr>";
            
            echo "<tr><td>{$webText["helyszin"]}: *</td><td><div id='helyszinvalaszto'>" . $this->_reservationPlaceSelectorNew() . "</div></td></tr>";
            echo "<tr><td></td><td><div id='szurestipusmegj'>" . $this->bookingService->getTipusMegj($_SESSION["helyszindata"]["id"], $_POST["szurestipus"], $_POST["helyszin"]) . "</div></td></tr>";
            echo "<tr><td></td><td><div id='tappenzcheck'>" . $this->bookingService->tappenzCheckHTML($_POST["helyszin"]) . "</div></td></tr>";
        }

        $nofoglalasText = trim($_SESSION["helyszindata"]["nofoglalas_{$_COOKIE["lang"]}"]);
        if (empty($nofoglalasText)) {
            $numberTexts = ["" => $webText["idopont"], 1 => "Első időpont", 2 => "Második időpont", 3 => "Harmadik időpont"];
            $this->bookingService->setHelyszin($_POST["helyszin"]);
            $this->bookingService->setSzuresTipus($_POST["szurestipus"]);
            $numberOfTimes = $this->bookingService->numberOfReservationRequired();
            for ($i = 1; $i <= $numberOfTimes; $i++) {
                $index = "";
                if ($numberOfTimes > 1) {
                    $index = $i;
                }
                echo "<tr class='datarow'><td valign='middle'<div style=''>{$numberTexts[$index]}: *</div></td><td>" . $this->_reservationTimeSelector($index) . "</td></tr>";
            }
            echo "<tr><td></td><td><div id='idopontvalasztodiv' style='display:none;'></div></td></tr>";
        } else {
            echo "<tr class='datarow'><td></td><td>{$nofoglalasText}</td></tr>";
        }

        if (!$this->utils->getFieldHidden("doksi")) {
            echo "<tr class='datarow'><td></td><td>&nbsp;</td></tr>";
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
            echo "<tr class='datarow'><td>{$webText["megjegyzes"]}:</td><td><div id='fogleuwarn' style='display:none;margin-top:5px;color:#f00;font-weight:bold;'>Kérjük adja meg a megjegyzés rovatban a céget, ahonnan érkezik</div>";
            echo "<textarea class='inputbox' style='height:100px;width:400px;' name='megj' id='foglmegj'>{$_POST["megj"]}</textarea>";
            //apollo tyres kivétel
            if ($_SESSION["helyszindata"]["id"] == 43) {
                echo "<div>";
                //Indiába menő, előzetes, soron kívüli, Indiából hazatérő. Illetve: Hollandiába menő, előzetes, soron kívüli, Hollandiából hazatérő
                echo "<span class='addmegjlink'>Indiába menő</span> &bull; ";
                echo "<span class='addmegjlink'>Előzetes</span> &bull; ";
                echo "<span class='addmegjlink'>Soron kívüli</span> &bull; ";
                echo "<span class='addmegjlink'>Indiából hazatérő</span><br/>";
                echo "<span class='addmegjlink'>Hollandiába menő</span> &bull; ";
                echo "<span class='addmegjlink'>Előzetes</span> &bull; ";
                echo "<span class='addmegjlink'>Soron kívüli</span> &bull; ";
                echo "<span class='addmegjlink'>Hollandiából hazatérő</span>";
                echo "</div>";
            }
            echo "</td></tr>";
        }

        if (CompanyService::isUniqa()) {
            $webText["aszfelf"] = "Az <a href=\"#adatvedelmilink#\" target=\"_blank\">Adatvédelmi tájékoztatót</a> elolvastam, a fenti adatkezeléshez hozzájárulok, valamint a foglalás elküldésével elfogadom, hogy tudomásom van arról, hogy a Biztosító a Rendezvény megszervezése, a Rendezvényre történő regisztráció lebonyolítása és az általam kért vizsgálatok elvégzése céljából igénybe veszi a Hungária-Med M Kft. (HUNGÁRIA-MED M Kereskedelmi és Szolgáltató Korlátolt Felelősségű Társaság, székhely: 1132 Budapest, Csanády u. 6. B. ép. V. em. 2., a továbbiakban: „Hungária-Med M” vagy „Adatfeldolgozó”) orvosi szolgáltatásait.";
        }

        if (CompanyService::isFesztivalCompany()) {
            $webText["aszfelf"].= " <br/>".$webText["eltitkoltaszf"];
        }

        if (!isset($_SESSION["user"])) {
            echo "<tr class='datarow'><td></td><td><div class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
            echo "<tr class='datarow'><td></td><td><div style='margin-top:10px;max-width: 800px;'><input type='checkbox' name='aszf' value='1' " . (isset($_POST["aszf"]) ? "checked" : "") . "/> {$webText["aszfelf"]}</div></td></tr>";
        }

        //$submitButtonText = $webText["idopontfoglalasa"];
        $submitButtonText = $this->lang->getText("foglalasveglegesitese", "Foglalás véglegesítése");

        if ($this->bookingService->isOnlineTipus($_POST["szurestipus"])) {
            $priceData = $this->bookingService->getPriceData($_POST["szurestipus"]);
            $submitButtonText.= " és fizetés ({$priceData["price"]} Ft)";
            echo "<tr><td></td><td><div style='margin-top:10px;'><input type='checkbox' name='simplepay' value='1' ".(isset($_POST["simplepay"])?"checked":"")."/> Elfogadom a <a style='' href='http://simplepartner.hu/PaymentService/Fizetesi_tajekoztato.pdf' target='_blank'>SimplePay feltételeit.</a></div></td></tr>";
        }

        echo "<tr class='datarow'><td></td><td><div style='margin-top:20px;'><a href='#' class='newbutton' onclick='document.iform.submit();return false;'>{$submitButtonText}</a><span id='warnidopontpress' style='display:none;color:#41b6c6;margin-left:5px;'>&#9664;<span class='warnidopontpress'>{$webText["idopontfoglalasawarn"]}</span></span><div></td></tr>";

        echo "</table>";

        if (!isset($_SESSION["orvosselected"])) $_SESSION["orvosselected"] = 0;

        if (isset($_SESSION["user"])) echo "<input type='hidden' name='aszf' value='1'/>";
        echo "<input type='hidden' name='idopontfoglalas' value='1'/>";
        echo "<input type='hidden' name='version2' value='1'/>";
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

    private function _reservationTimeSelector($index = "") {
        $webText = $this->lang->webText;

        $dateStyle = (!empty($_POST["datum{$index}"]) ? "background-image:url(images/check.png);" : "") . "background-repeat:no-repeat;background-position:right 5px center;width:150px;height:24px;margin-right:5px;padding:4px 5px;font-size:16px;";
        $dateVal = substr($_POST["datum{$index}"], 0, 16);
        $dateValText = "";

        if (!isset($_POST["orvosselected{$index}"])) {
            $_POST["orvosselected{$index}"] = 0;
        }

        $firstFreeDay = 0;
        $testDay      = 0;
        $helyszin     = intval($_POST["helyszin"]);
        $szurestipus  = intval($_POST["szurestipus"]);

        if (isset($_SESSION["firstfreeday{$szurestipus}_{$helyszin}"])) {
            $firstFreeDay = $_SESSION["firstfreeday{$szurestipus}_{$helyszin}"];
        } else {
            while ($testDay < 44) {
                $this->bookingService->setHelyszin($_POST["helyszin"]);
                $this->bookingService->setSzuresTipus($_POST["szurestipus"]);
                $this->bookingService->setHonnan($testDay);
                $json = $this->bookingService->showIdoPontValasztoV2($testDay);

                if (substr_count($json, "foglalhatobtn")) {
                    $firstFreeDay = $_SESSION["firstfreeday{$szurestipus}_{$helyszin}"] = $testDay;
                    break;
                }
                $testDay += 7;
            }
        }

        $html = "";
        $html .= "<div style='display:table;'>";
        $html .= "<div style='display:table-row;'>";
        $html .= "<div style='display:table-cell;'>";
        $html .= "<input type='hidden' name='orvosselected' id='orvosselected' value='{$_POST["orvosselected{$index}"]}'/>";
        $html .= "<input type='hidden' name='rinterval{$index}' id='rinterval{$index}' value='{$_POST["rinterval{$index}"]}' />";
        $html .= "<input type='hidden' name='datum{$index}' id='datum{$index}' value='{$dateVal}' />";
        $html .= "<input placeholder='{$webText["kattintsagombra"]}' readonly='true' class='inputbox' style='{$dateStyle}' type='text' name='datumText{$index}' id='datumText{$index}' value='{$_POST["datumText{$index}"]}' />";
        $html .= "</div>";
        $html .= "<div style='display:table-cell;vertical-align: middle;'><a href='#' onclick='setDatumIndex(\"{$index}\");showIdoPontValasztoV2({$firstFreeDay});return false;' style='margin:0px;' class='newbutton'>{$webText["idopontvalasztas"]}</a></div>";
        $html .= "<div style='display:table-cell;vertical-align: middle;'><img id='loadingspinner{$index}' style='margin-left:5px;height:25px;display:none;' src='/images/loading.svg' /></div>";
        $html .= "</div>";
        $html .= "</div>";
        return $html;
    }

    private function _szuresTipusValasztoNew($selected = 0, $onlyselected = 0)
    {
        $tipusok = [];
        $tipusnevek = [];

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

        $htmlout = "";
        $htmlout .= "<select name='szurestipus' id='szurestipus' onchange='selectedTipus(this.value, {$_POST["helyszin"]});'>";
        $htmlout .= "<option {$disabled} value='0'>" . $this->lang->webText["valasszon"] . "!</option>";

        $res = sql_query("SELECT tipusok FROM orvos_beosztas_new b WHERE (instr(b.beocegek, ?) or b.beocegek='') and b.aktiv=1 and (nap<10 or (nap=10 and beonap>=date(now()))) and b.noreservation=0", ["|{$_SESSION["helyszindata"]["id"]}|"]);
        while ($row = sql_fetch_array($res)) {
            $ta = explode("|", $row["tipusok"]);
            for ($i = 0; $i < count($ta); $i++) {
                if (trim($ta[$i]) != "" && !in_array($ta[$i], $tipusok)) {
                    $tipusok[] = $ta[$i];
                }
            }
        }

        if (isset($tipusok)) {
            for ($i = 0; $i < count($tipusok); $i++) {
                @$tipusdisplay[$tipusok[$i]] = $tipusnevek[$tipusok[$i]];
            }
            if (isset($tipusdisplay)) {
                asort($tipusdisplay);
                foreach ($tipusdisplay as $key => $value) {
                    //if (count($tipusdisplay)==1) $selected=$key;
                    if ($onlyselected == 1 && $key != $selected) continue;
                    if (trim($value) == "") continue;
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
        if ($_SESSION["helyszindata"]["id"] != 82) {
            $htmlout .= "<div id='borgyogystuff' style='display: inline-block; visibility: hidden;margin-left:10px;padding:3px;background-color:#e13030;color:white;font-weight:bold'>Eltávoltításra is szükség van <input type='checkbox' style='' onChange='$(\"#foglmegj\").text(\"Eltávolításra is szükség van, VISSZAHÍVÁST KÉREK!\")' name = 'eltavolitas' value = 'szukseges'/></div>";
        }

        return $htmlout;
    }

    private function _reservationPlaceSelectorNew()
    {
        $html        = "";
        $szuresTipus = $_POST["szurestipus"];
        $webText     = $this->lang->webText;
        $helyszinek  = $this->bookingService->beosztasService->getReservationPlaces($_SESSION["helyszindata"]["id"], $szuresTipus);
        $numOfH      = count($helyszinek);

        $_SESSION["orvosselected"] = 0;

        $html .= "<select name='helyszin' id='helyszin' onchange='selectedTipus({$_REQUEST["szurestipus"]}, this.value);'>";
        $html .= "<option value='0'>{$webText["valasszhelyszint"]}</option>";
        foreach ($helyszinek as $rowt) {
            if ($_SESSION["helyszindata"]["nocim"] == 1) {
                $rowt["cim"] = $rowt["megnev"];
            }
            $html .= "<option value='{$rowt["id"]}'" . ($_POST["helyszin"] == $rowt["id"] || $numOfH == 1 ? " selected" : "") . ">{$rowt["cim"]}</option>";
            if ($numOfH == 1) {
                $_POST["helyszin"] = $rowt["id"];
            }
        }
        $html .= "</select>";

        $html .= "<div id='helyszinvalasztowarn' style='display:none;background:#ff6961;color:#fff;font-size:16px;padding:10px;margin:10px 0px 0px 0px;'>Figyelem! Ha a győri címünkre szeretne foglalni, használja a győri bejelentkezési felületünket, majd ott kövesse az \"üzemorvosi vizsgálat\" linket. Foglalását telefonon is megteheted a következő számon: +36 20 373 3343<br/><br/><a class='newbutton' href='https://gyor-bejelentkezes.hungariamed.hu'>Folytatás a győri bejelentkező felületen</a></div>";

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

        $html = "";

        $introText = $this->lang->getText("miert.bennunket.description.2", "");

        $html .= "<div style='padding:0px 0px 30px 0px;'>";
        if (!empty($introText)) {
            $html .= "<h2 style='font-size:32px;font-family:robotolight;'>" . $this->lang->getText("miert.bennunket", "Miért bennünket válasszon?") . "</h2>";
            $html .= $introText;
        }

        $html .= "<div>";

        foreach (Booking_Constants::DEFAULT_PLACE_IDS as $helyszinId) {
            $services = $this->bookingService->getPublicServices($helyszinId);

            $html .= "<div style='text-align:center;margin-top:30px;".(empty($introText)?"":"border-top:1px solid #888;")."'>";

            $html .= "<h2>Időpontfoglalás</h2>" . $this->lang->getText("foglalas.inditas", "Kattintson a szakrendelés nevére a foglalás indításához!") . "<br/><br/>";
            foreach ($services as $tipusData) {
                $tipusData["megnev"] = Lang::multiLangField($tipusData, "megnev");

                if (empty($tipusData["facode"])) {
                    $tipusData["facode"] = "<i class='fas fa-hospital'></i>";
                }

                if ($tipusData["webdoktor"] == 1) {
                    $tipusData["facode"] = "<i class='fas fa-laptop-medical'></i>";
                }

                $html .= "<div class='vizsgalatdoboz_".Booking_Constants::SQL_DB . ($tipusData["webdoktor"] == 1 ? " vizsgalatdobozwebdoctor" : "") . "' onclick='extendedReservationSelect({$tipusData["id"]},{$helyszinId},{$tipusData["noreservation"]});return false;'>";
                $html .= "<div style=''>";
                $html .= "<div style='font-size: 56px;padding:5px 10px 10px 10px;color:#fff;'>{$tipusData["facode"]}</div>";
                $html .= "<div style='font-size:16px;font-family: robotobold;color:#fff;'>{$tipusData["megnev"]}</div>";
                $html .= "</div>";

                $html .= "<div class='" . ($tipusData["webdoktor"] == 1 ? "vizsgalatdobozbuttonwebdoctor" : "vizsgalatdobozbutton_".Booking_Constants::SQL_DB) . "'>" . ($tipusData["webdoktor"] == 1 ? "&nbsp;&nbsp;&nbsp;&nbsp;megrendelem&nbsp;&nbsp;&nbsp;&nbsp;" : "időpontfoglalás") . "</div>";

                $html .= "</div>";
            }

            $html .= "</div>";
        }
        $html .= "</div>";

        return $html;
    }
}
