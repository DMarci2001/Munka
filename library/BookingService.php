<?php

use mikehaertl\pdftk\Pdf;

class BookingService
{
    private Lang $lang;
    public array $packContentTypes = [];
    private int $honnan = 0;
    private int $neme = 0;
    public int $helyszin = 0;
    public int $szuresTipus = 0;
    public $szuresTipusData;
    public array $szuresTipusMap = [];
    public bool $betegallomany = false;
    public BeosztasService $beosztasService;
    public NotificationService $notificationService;
    private string $taj;
    private AdminUser $adminUser;
    public int $newReservationId;
    public MunkakorVizsgalatok $munkakorVizsgalatok;
    private $utils;

    public $availableDocs = array(
        array("name" => "Éjszakai", "cegid"=>74, "type"=> "simple", "value" => "bp-nightshift", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/bp_A_munkakori_beutalo_generalNight.pdf"),
        array("name" => "Nappali", "cegid"=>74, "type"=> "simple", "value" => "bp-normal", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/bp_A_munkakori_beutalo_general.pdf"),

        //array("name" => "FGSZ", "cegid"=>220, "type"=> "full-form", "value" => "fgsz-beutalo", "filename" => "/var/www/marci/onlinebejelentkezes/public/admin/templates/FGSZ_beutalo.pdf"),
        array("name" => "FGSZ", "cegid"=>220, "type"=> "full-form", "value" => "fgsz-beutalo", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/FGSZ_beutalo.pdf"),
        array("name"=>"Apollo", "cegid"=> 43, "type"=> "full-form", "value"=> "apollo-beutalo", "filename" => "/var/www/marci/onlinebejelentkezes/public/admin/templates/apollo_beutalo.pdf"),
        //array("name" => "Geodéta", "cegid"=>220, "value" => "Geodéta", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/fgsz_geodeta_beutalo.pdf"),
        //array("name" => "Hírközlési munkatárs", "cegid"=>220, "value" => "Hírközlési munkatárs", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/fgsz_hirközlesi_munkatars_beutalo.pdf"),
        //array("name" => "Működés támogatás munkatárs", "cegid"=>220, "value" => "Működés támogatás munkatárs", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/fgsz_mukodes_tamogatas_munkatars_beutalo.pdf"),
        //array("name" => "Régiós diszpécser", "cegid"=>220, "value" => "Régiós diszpécser", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/fgsz_regios_diszpecser_beutalo.pdf"),
        //array("name" => "Számviteli munkatárs", "cegid"=>220, "value" => "Számviteli munkatárs", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/fgsz_szamviteli_munkatars_beutalo.pdf"),
        //array("name" => "Technológiai szerelő", "cegid"=>220, "value" => "Technológiai szerelő", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/fgsz_technologiai_szerelo_beutalo.pdf"),

    );

    public function __construct()
    {
        $this->beosztasService = new BeosztasService();
        $this->notificationService = new NotificationService();
        $this->adminUser = new AdminUser();
        $this->munkakorVizsgalatok = new MunkakorVizsgalatok();
        $this->utils = new Utils();

    }

    private function holterReserved($day):bool {
        $numberOfHolters = 1;
        $result = false;
        $holterTypeId = 0;
        $yesterday = date("Y-m-d", strtotime("{$day} -1 day"));
        if (date("N", strtotime($day)) == 1) {
            $yesterday = date("Y-m-d", strtotime("{$day} -3 day"));
        }

        if (Booking_Constants::SQL_DB == "hungariamed") {
            $holterTypeId = 136;
        }

        if ($holterTypeId == $this->szuresTipus) {
            $yesterdayReserved = sql_query("select count(*) as hany from foglalasok where datum>? and datum<? and szurestipusid=?", ["{$yesterday} 00:00:00", "{$yesterday} 23:59:59", $holterTypeId])->fetch(PDO::FETCH_ASSOC);
            $todayReserved = sql_query("select count(*) as hany from foglalasok where datum>? and datum<? and szurestipusid=?", ["{$day} 00:00:00", "{$day} 23:59:59", $holterTypeId])->fetch(PDO::FETCH_ASSOC);
            if ($yesterdayReserved["hany"] >= $numberOfHolters) {
                $result = true;
            }
            if ($todayReserved["hany"] >= $numberOfHolters && !$result) {
                $result = true;
            }
        }

        return $result;
    }

    private function waitListProcess($nap) {
        //<-- MARCI KÓDJA, HA VALAMI NEM STIMMELNE :) -->

        //Végig kell mennyek az orvos listán hogy lecsekkoljam van-e köztük várólistás:
        $orvosList = $this->getOrvosListForIdopontValaszto($nap);
        foreach($orvosList as $orvos){
            //lekérem a beosztásokat majd listázom őket
            $napiBeos = $this->getBeosztasok("{$nap}", $this->helyszin, $this->szuresTipus, $orvos);
            $keys = array_keys(array_column($napiBeos,"waitlist"));

            if(count($keys)!=0){
                foreach($keys as $key){
                    if(empty($napiBeos[$key]["waitlist"])) continue;
                    //Megkapom a rendelési időt percben
                    $rendelesi_ido =  round(abs(strtotime($napiBeos[$key]["ig"]) - strtotime($napiBeos[$key]["tol"])) / 60,2);
                    $foglalasok = array();

                    //Lekérem a beosztáshoz tartozó adatokat:
                    $waitlist = sql_fetch_array(sql_query("SELECT * FROM orvos_beosztas_new WHERE id = {$napiBeos[$key]["waitlist"]}"));

                    if($waitlist["aktiv"]==0){
                        //Minden foglalás rintervalját kilistázom ami az adott napra, orvoshoz és helyszínen van
                        $foglalasokq = sql_query("SELECT rinterval FROM foglalasok WHERE datum >'{$nap} 00:00:00' AND datum < '{$nap} 23:59:59' AND orvosassigned={$napiBeos[$key]["orvosid"]} AND helyszinid = {$napiBeos[$key]["helyszinid"]}");
                        while($foglalasokq_fetch = sql_fetch_array($foglalasokq)){$foglalasok[] = $foglalasokq_fetch["rinterval"];}

                        //Megvizsgálom, hogy a kifoglalt idő megegyezik-e vagy meghaladja-e a rendelési idő tartamát
                        if(array_sum($foglalasok)>=$rendelesi_ido){
                            //Ha igen, akkor aktiválom a beosztáshoz tartozó várólistát:
                            sql_query("UPDATE orvos_beosztas_new SET aktiv=1 WHERE id=?",array($napiBeos[$key]["waitlist"]));
                        }
                    }
                }
            }
        }
    }

    private function laborOptionBreak($time):bool {
        $laborOption = $_GET["laborOption"] ?? 0;
        $companyData = $_SESSION["helyszindata"];
        if ($laborOption != 0) {
            if (!empty($companyData["laboropcioig"])) {
                if (strtotime($time) >= strtotime($companyData["laboropcioig"])) {
                    return true;
                }
            }
            if (!empty($companyData["laboropciotol"])) {
                if (strtotime($time) < strtotime($companyData["laboropciotol"])) {
                    return true;
                }
            }
        }

        return false;
    }


    private array $kiegIds = [];
    private array $kiegTimes = [];
    private array $kiegBeosztas = [];

    private function kiegVizsgalatOverrideIfNeeded():void {
        if (!empty($_GET["kiegChecked"])) {
            $this->kiegIds = explode("_", $_GET["kiegChecked"]);
            $this->kiegIds = array_unique($this->kiegIds);
            if (count($this->kiegIds) == 1) {
                //$_GET["szurestipus"] = $this->kiegIds[0];
            }
        }

        if (CompanyService::isKRE() && count($this->kiegIds) == 1 && $this->kiegIds[0] == 137) {
            $nap = date("Y-m-d", strtotime("this week monday +{$this->honnan} day"));
            $napEnd = date("Y-m-d", strtotime("this week monday +".($this->honnan+7)." day"));
            $reservationService = new ReservationService();
            $reservationService->startDate = $nap;
            $reservationService->endDate = $napEnd;
            $reservationService->companyId = $_SESSION["helyszindata"]["id"];
            $reservationService->locationId = $this->helyszin;
            $reservationService->reservationTypeId = $this->kiegIds[0];
            $this->kiegBeosztas = $reservationService->getSlotsPlace();

            //echo $nap." ".$napEnd;

            //print_r($result);
        }

    }


    public function showIdoPontValasztoV2() {
        $this->lang = new Lang();
        $webText = $this->lang->webText;

        $html = "";
        if (isset($_GET["helyszin"]) && $_GET["helyszin"] != "undefined") {
            $this->setHelyszin($_GET["helyszin"]);
        }
        if (isset($_GET["neme"])) {
            $this->setNeme($_GET["neme"]);
        }
        if (isset($_GET["szurestipus"])) {
            $this->setSzuresTipus($_GET["szurestipus"]);
        }
        if (isset($_GET["honnan"])) {
            $this->honnan = intval($_GET["honnan"]);
        }
        $this->taj = (!isset($_GET['taj']) ? 0 : $_GET['taj']);
        $cegId = $_SESSION["helyszindata"]["id"];

        $this->kiegVizsgalatOverrideIfNeeded();
        $this->setBetegallomany((!isset($_GET['betegallomany']) ? false : $_GET['betegallomany']));

        if (!isset($_GET['javascript'])) {
            $_GET['javascript'] = "showIdoPontValasztoV2";
        }

        if ($this->helyszin == 0) {
            return json_encode(array("error" => $webText["valasszhelyszint"], "html" => ""));
        }
        if ($this->szuresTipus == 0) {
            return json_encode(array("error" => $webText["valasszszurestipust"], "html" => ""));
        }

        if (count($this->getGenderPackContentTypes($this->szuresTipus)) != 0 && $this->neme == 0) {
            return json_encode(array("error" => $webText["valassznemet"], "html" => ""));
        }

        if (count($this->getGenderPackContentTypes($this->szuresTipus)) != 0 && $this->neme == 0) {
            return json_encode(array("error" => $webText["valassznemet"], "html" => ""));
        }

        $checkedServices = [];
        if (!empty($_GET["checkedServices"])) {
            $checkedServices = explode("_", $_GET["checkedServices"]);
        }

        //echo "hét: {$this->honnan}<br>";
        if(CompanyService::isSuzukiGHC()){
            $thisMonday = strtotime("this week monday");
            $targetMonday = strtotime("2025-09-15");
            $diffvalue = ($targetMonday-$thisMonday);
            $diffdays = ($diffvalue/86400);

            if($this->honnan<$diffdays){
                $this->honnan=$diffdays;
            }
           
        }

        $html .= "<div style='margin:10px 0px 10px 0px;'>";
        $html .= "<div>{$webText["valasszidopontot"]}:</div>";
        $html .= "<div style='margin-top:5px;'><a href='javascript:{$_GET['javascript']}(" . ($this->honnan - 7) . ($_GET['javascript'] == "showIdoPontValasztoV3" ? ",{$_GET['selectoid']},{$_GET['szurestipus']},{$_GET['helyszin']}" : "") . ")'><i class='fa-solid fa-angles-left'></i>&nbsp;{$webText["elo7"]}</a>&nbsp;&nbsp;&bull;&nbsp;&nbsp;<a href='javascript:{$_GET['javascript']}(" . ($this->honnan + 7) . ($_GET['javascript'] == "showIdoPontValasztoV3" ? ",{$_GET['selectoid']},{$_GET['szurestipus']},{$_GET['helyszin']}" : "") . ")'>{$webText["kov7"]}&nbsp;<i class='fa-solid fa-angles-right'></i></a></div>";
        $html .= "<table style='width:100%;' cellpadding='0' cellspacing='0'><tr>";
        
        for ($i = 0; $i <= 6; $i++) {
            $fix       = $i + $this->honnan;
            $nap       = date("Y-m-d", strtotime("this week monday +{$fix} day"));
            $wd        = date("N", strtotime($nap));
            $this->waitListProcess($nap);
            $orvosList = $this->getOrvosListForIdopontValaszto($nap);


            /**
             * Itt tárolom le a paramétereket a műszakokhoz.
            */
            if(CompanyService::isSuzukiGHC()){
                $shifts = array(
                    "A-A-SE"=>array(
                        //frissítve 2025
                        1=>array("start"=>null,"end"=>null),
                        2=>array("start"=>null,"end"=>null),
                        3=>array("start"=>null,"end"=>null),
                        4=>array("start"=>"13:0","end"=>"18:0"),
                        5=>array("start"=>null,"end"=>null),
                    ),
                    "A-A-ST"=>array(
                        1=>array("start"=>"13:0","end"=>"18:0"),
                        2=>array("start"=>"13:0","end"=>"18:0"),
                        3=>array("start"=>"13:0","end"=>"18:0"),
                        4=>array("start"=>"13:0","end"=>"18:0"),
                        5=>array("start"=>"13:0","end"=>"18:0"),
                    ),
                    "A-B-SE"=>array(
                        //frissítve 2025
                        1=>array("start"=>"7:0","end"=>"12:0"),
                        2=>array("start"=>"7:0","end"=>"12:0"),
                        3=>array("start"=>"7:0","end"=>"12:0"),
                        4=>array("start"=>"7:0","end"=>"12:0"),
                        5=>array("start"=>"7:0","end"=>"12:0"),
                    ),
                    "A-B-ST"=>array(
                        1=>array("start"=>null,"end"=>null),
                        2=>array("start"=>null,"end"=>null),
                        3=>array("start"=>null,"end"=>null),
                        4=>array("start"=>null,"end"=>null),
                        5=>array("start"=>null,"end"=>null),
                    ),
                    "A-O-SE"=>array(
                        1=>array("start"=>"7:0","end"=>"12:0"),
                        2=>array("start"=>"7:0","end"=>"12:0"),
                        3=>array("start"=>"7:0","end"=>"12:0"),
                        4=>array("start"=>"7:0","end"=>"12:0"),
                        5=>array("start"=>"7:0","end"=>"12:0"),
                    ),
                    "A-O-ST"=>array(
                        1=>array("start"=>"13:0","end"=>"18:0"),
                        2=>array("start"=>"13:0","end"=>"18:0"),
                        3=>array("start"=>"13:0","end"=>"18:0"),
                        4=>array("start"=>"13:0","end"=>"18:0"),
                        5=>array("start"=>"13:0","end"=>"18:0"),
                    ),
                    "A-D-SE"=>array(
                        1=>array("start"=>"7:0","end"=>"13:0"),
                        2=>array("start"=>"7:0","end"=>"13:0"),
                        3=>array("start"=>"7:0","end"=>"13:0"),
                        4=>array("start"=>"7:0","end"=>"18:0"),
                        5=>array("start"=>"7:0","end"=>"13:0"),
                    ),
                    "A-D-ST"=>array(
                        1=>array("start"=>"13:0","end"=>"18:0"),
                        2=>array("start"=>"13:0","end"=>"18:0"),
                        3=>array("start"=>"13:0","end"=>"18:0"),
                        4=>array("start"=>"13:0","end"=>"18:0"),
                        5=>array("start"=>"13:0","end"=>"18:0"),
                    ),
                    "B-A-SE"=>array(
                        //Frissítve 2025
                        1=>array("start"=>"7:0","end"=>"12:0"),
                        2=>array("start"=>"7:0","end"=>"12:0"),
                        3=>array("start"=>"7:0","end"=>"12:0"),
                        4=>array("start"=>"7:0","end"=>"12:0"),
                        5=>array("start"=>"7:0","end"=>"12:0"),
                    ),
                    "B-A-ST"=>array(
                        1=>array("start"=>null,"end"=>null),
                        2=>array("start"=>null,"end"=>null),
                        3=>array("start"=>null,"end"=>null),
                        4=>array("start"=>null,"end"=>null),
                        5=>array("start"=>null,"end"=>null),
                    ),
                    "B-B-SE"=>array(
                        //Frissítve 2025
                        1=>array("start"=>null,"end"=>null),
                        2=>array("start"=>null,"end"=>null),
                        3=>array("start"=>null,"end"=>null),
                        4=>array("start"=>"13:0","end"=>"18:0"),
                        5=>array("start"=>null,"end"=>null),
                    ),
                    "B-B-ST"=>array(
                        1=>array("start"=>"13:0","end"=>"18:0"),
                        2=>array("start"=>"13:0","end"=>"18:0"),
                        3=>array("start"=>"13:0","end"=>"18:0"),
                        4=>array("start"=>"13:0","end"=>"18:0"),
                        5=>array("start"=>"13:0","end"=>"18:0"),
                    ),
                    "B-O-SE"=>array(
                        1=>array("start"=>"7:0","end"=>"12:0"),
                        2=>array("start"=>"7:0","end"=>"12:0"),
                        3=>array("start"=>"7:0","end"=>"12:0"),
                        4=>array("start"=>"7:0","end"=>"12:0"),
                        5=>array("start"=>"7:0","end"=>"12:0"),
                    ),
                    "B-O-ST"=>array(
                        1=>array("start"=>"13:0","end"=>"18:0"),
                        2=>array("start"=>"13:0","end"=>"18:0"),
                        3=>array("start"=>"13:0","end"=>"18:0"),
                        4=>array("start"=>"13:0","end"=>"18:0"),
                        5=>array("start"=>"13:0","end"=>"18:0"),
                    ),
                    "B-D-SE"=>array(
                        1=>array("start"=>"7:0","end"=>"13:0"),
                        2=>array("start"=>"7:0","end"=>"13:0"),
                        3=>array("start"=>"7:0","end"=>"13:0"),
                        4=>array("start"=>"7:0","end"=>"18:0"),
                        5=>array("start"=>"7:0","end"=>"13:0"),
                    ),
                    "B-D-ST"=>array(
                        1=>array("start"=>"13:0","end"=>"18:0"),
                        2=>array("start"=>"13:0","end"=>"18:0"),
                        3=>array("start"=>"13:0","end"=>"18:0"),
                        4=>array("start"=>"13:0","end"=>"18:0"),
                        5=>array("start"=>"13:0","end"=>"18:0"),
                    ),
                );

                /**
                 * Női dolgozók részére extra sávok kellenek O.o...
                 * A délelőtti sávon kell nekik időpontot biztosítani óránként maximum 4 helyet.
                */
                if($noiDolgozo=sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=? AND nem=2",[$_SESSION["user"]["torzsszam"]])->fetch(PDO::FETCH_ASSOC))
                {  

                    //Ez kvázi egy korlátozás, ezért csak akkor alkalmazom, hogyha tényleg kéri a vizsgálatot a dolgozó az emlő/mammót
                    if(isset($_GET["szurestipus292"]) || isset($_GET["szurestipus112"])){

                        $shifts["A-A-ST"][1] = array("start"=>"15:0","end"=>"18:0");
                        $shifts["A-A-ST"][2] = array("start"=>"15:0","end"=>"18:0");
                        $shifts["A-A-ST"][3] = array("start"=>"15:0","end"=>"18:0");
                        $shifts["A-A-ST"][4] = array("start"=>"15:0","end"=>"18:0");
                        $shifts["A-A-ST"][5] = array("start"=>"15:0","end"=>"18:0");

                        $shifts["A-B-ST"][1] = array("start"=>"7:0","end"=>"18:0");
                        $shifts["A-B-ST"][2] = array("start"=>"7:0","end"=>"18:0");
                        $shifts["A-B-ST"][3] = array("start"=>"7:0","end"=>"18:0");
                        $shifts["A-B-ST"][4] = array("start"=>"7:0","end"=>"18:0");
                        $shifts["A-B-ST"][5] = array("start"=>"7:0","end"=>"18:0");

                        $shifts["B-A-ST"][1] = array("start"=>"7:0","end"=>"12:0");
                        $shifts["B-A-ST"][2] = array("start"=>"7:0","end"=>"12:0");
                        $shifts["B-A-ST"][3] = array("start"=>"7:0","end"=>"12:0");
                        $shifts["B-A-ST"][4] = array("start"=>"7:0","end"=>"12:0");
                        $shifts["B-A-ST"][5] = array("start"=>"7:0","end"=>"12:0");

                        $shifts["A-O-ST"][1] = array("start"=>"13:0","end"=>"18:0");
                        $shifts["A-O-ST"][2] = array("start"=>"13:0","end"=>"18:0");
                        $shifts["A-O-ST"][3] = array("start"=>"13:0","end"=>"18:0");
                        $shifts["A-O-ST"][4] = array("start"=>"13:0","end"=>"18:0");
                        $shifts["A-O-ST"][5] = array("start"=>"13:0","end"=>"18:0");

                        $shifts["B-O-ST"][1] = array("start"=>"13:0","end"=>"18:0");
                        $shifts["B-O-ST"][2] = array("start"=>"13:0","end"=>"18:0");
                        $shifts["B-O-ST"][3] = array("start"=>"13:0","end"=>"18:0");
                        $shifts["B-O-ST"][4] = array("start"=>"13:0","end"=>"18:0");
                        $shifts["B-O-ST"][5] = array("start"=>"13:0","end"=>"18:0");
                    }
                      
                    

                    

                    //$shifts["B-B-ST"][1] = array("start"=>"7:0","end"=>"18:0");
                    //$shifts["B-B-ST"][2] = array("start"=>"7:0","end"=>"18:0");
                    //$shifts["B-B-ST"][3] = array("start"=>"7:0","end"=>"18:0");
                    //$shifts["B-B-ST"][4] = array("start"=>"7:0","end"=>"18:0");
                    //$shifts["B-B-ST"][5] = array("start"=>"7:0","end"=>"18:0");
                }
                

                //Default paraméterek
                $week = null;
                $pre_beginHour = "";
                $_prebeginMinute = "";

                //Hétválasztás (A hét, B hét)
                if(in_array(date("W",strtotime($nap)),[38,40])){
                    $week = "A";
                }
                if(in_array(date("W",strtotime($nap)),[39])){
                    $week = "B";
                }

                //Csomag definíció
                if($this->szuresTipus==217) $csomag = "SE";
                if($this->szuresTipus==216) $csomag = "ST";

                //Ha minden együtt áll, megekeresem a szükséges műszak beosztást a fenti tömbből
                if(!empty($week) && in_array(date("w",strtotime($nap)),[1,2,3,4,5]) && isset($_GET["muszak"])){
                    //echo $beginHour." - ".$beginMinute."<br>";
                    $startIdo = explode(":",$shifts["{$week}-{$_GET["muszak"]}-{$csomag}"][date("w",strtotime($nap))]["start"]);
                    $endIdo   = explode(":",$shifts["{$week}-{$_GET["muszak"]}-{$csomag}"][date("w",strtotime($nap))]["end"]);


                    //Definiálom a kezdő és vég idő értékeket (kezdő nullákat levágom, nehogy furán viselkedjen az mktime fügvény)
                    if(count($startIdo)>1){
                        $pre_beginHour = $startIdo[0];
                        $_prebeginMinute = $startIdo[1];
                    }

                    if(count($endIdo)>1){
                        $pre_EndHour = $endIdo[0];
                        $pre_EndMinute = $endIdo[1];
                    }
                    
                    //echo $pre_beginHour." - ".$_prebeginMinute."<br>";
                    //echo $week."-{$_GET["muszak"]}-{$csomag}<br>";
                }

                
           }
            
            if (($wd == 6 || $wd == 7) && empty($orvosList)) {
                continue;
            }

            $html .= "<td valign='top'>";
            $html .= "<div style='" . ($nap == date("Y-m-d") ? "background:#405d5b;" : "background:#607d8b;") . "margin:8px 1px;padding:4px 10px 4px 10px;color:#fff;font-weight:bold;text-align:center;white-space: nowrap;'>{$nap}<br/>{$webText["hetnap"][$wd]}</div>";

            if (in_array($nap, $this->getSzunnapok())) {
                $html .= "<div style='text-align:center;margin:5px;padding:5px 0px;color:#888;'>Munkaszüneti<br/>nap</div>";
                $html .= "</td>";
                continue;
            }

            if ($this->holterReserved($nap)) {
                $html .= "<div style='text-align:center;margin:5px;padding:5px 0px;color:#888;'>Eszköz nem<br/>elérhető</div>";
                $html .= "</td>";
                continue;
            }

            if ($_SESSION["helyszindata"]["id"] == 46 && strtotime("{$nap}") > strtotime("2022-10-31") && Booking_Constants::SQL_DB == "hungariamed") {
                //vodafone okt 31-ig foglalhat
                $html.= "</td>";
                continue;
            }

            $html.="<div style='display:table;width:100%;'>";

            foreach ($orvosList as $oKey => $orvosId) {
                $orvosData   = sql_query("select * from orvosok where id=?", [$orvosId])->fetch();
                $preResData  = $this->preReservationProtocol($cegId, $this->helyszin, $orvosId, $this->szuresTipus);
                $napiBeos    = $this->getBeosztasok("{$nap}", $this->helyszin, $this->szuresTipus, $orvosId);

                $thisServiceIsDisabled = 0;
                $disabledServices = json_decode($orvosData["disabledservices"], JSON_OBJECT_AS_ARRAY);
                if (!empty($disabledServices) && !empty($checkedServices)) {
                    foreach ($checkedServices as $checkedService) {
                        if (in_array($checkedService, $disabledServices)) {
                            $thisServiceIsDisabled = $checkedService;
                        }
                    }
                }
                if ($thisServiceIsDisabled != 0) {
                    continue;
                }

                $html .= "<div style='display:table-cell;text-align:center;vertical-align: top;" . ($oKey > 1 ? "padding-left:3px;" : "") . "'>";

                if ($_SESSION["helyszindata"]["no_doctor_select"] == 0) {
                    $s = "width:100px;overflow: hidden;text-align: center;font-size: 12px;margin:0px auto 5px auto;";
                    $html .= "<div style='{$s}'>{$orvosData["nev"]}</div>";
                } else {
                    $html .= "<div style='width:70px;'></div>";
                }

                foreach ($napiBeos as $beoKey => $napiBeo) {
                    $rowmax = $this->getMinMax([$napiBeo]);
                    $step = 0;
                    $freeTimes = 0;
                    $elsoIdopont = [];
                    $beginHour = round(substr($rowmax["minrendeles"], 0, 2));
                    $beginMinute = round(substr($rowmax["minrendeles"], 3, 2));
                    $dist = $preResData["hour"]; //ennyi órán belül kell foglalni
                    $distFullDay = $preResData["day"]; //ennyi napon belül kell foglalni
                    $binterval = $napiBeo["binterval"];
                    $sorszam = 1;
                    $jarat = 0;
                    $jaratStart = "";

                    //Megvizsgálom lett-e predefiniált beosztás kiválasztva ha igen, felül írom a kezdő értékeket vele
                    if(CompanyService::isSuzukiGHC()){
                        if($pre_beginHour!="" && $_prebeginMinute!=""){
                            //echo $beginHour."<".$pre_beginHour."<br>";
                            if($beginHour<$pre_beginHour){
                                
                                $beginHour = $pre_beginHour;
                                $beginMinute = $_prebeginMinute;
                            }
                        }else{
                            continue;
                        }
                    }

                    //echo $beginHour.":".$beginMinute."<br>";
                    

                    if ($orvosData["pecsetszam"] == "temp") {
                        continue;
                    }

                    $sectionHTML = "";
                    if ($beoKey != 0) {
                        $sectionHTML .= "<div style='width:70px;border-top:1px solid #888;padding-top:5px;margin:8px auto 0px auto;'></div>";
                    } else {
                        $sectionHTML .= "<div>";
                    }

                    $currentora="";
                    while (true) {
                        $ora = date("H:i", mktime($beginHour, $beginMinute + $step * $binterval, 0, date("m"), date("d"), date("Y")));
                        $buttonTitle = "";
                        $buttonClass = "foglaltbtn";
                        $buttonJava = "nemfog();return false;";
                        $buttonStyle = "";
                        $beoData = [];

                        

                        //Ez most nem tudom mit csinál egész pontosan xd (2025)
                        if(companyService::isSuzukiGHC()){
                            if($pre_EndHour!="" && $pre_EndMinute!=""){
                                $pre_EndHour = $endIdo[0];
                                $pre_EndMinute = $endIdo[1];
                                $overtime = strtotime($pre_EndHour.":".$pre_EndMinute);
                                //echo "Current: {$ora}(".strtotime($ora)."<br>";
                                //echo "End: ".$pre_EndHour.":".$pre_EndMinute."(".strtotime($pre_EndHour.":".$pre_EndMinute).")<br><br>";
                                if($overtime<=strtotime($ora)){
                                    break;
                                }
                            }
                        }
                        
                        

                        $step++;


                        if (strtotime($ora) >= strtotime($rowmax["maxrendeles"])) {
                            break;
                        }

                        if ($this->laborOptionBreak($ora)) {
                            continue;
                        }

                        //Óránként csak 1 időpontot rakok ki ezzel a pár sorral megoldva
                        if(CompanyService::isSuzukiGHC()){
                            if(isset($currentora)){
                                //echo $currentora." -> ".date("H",strtotime($ora))."<br>";
                            }
                            if(isset($currentora)){
                                //echo $currentora."<br>";
                            }
                            
                            if(isset($currentora) && $currentora==date("H",strtotime($ora))){
                                //echo $currentora."-".date("H",strtotime($ora))."<br>";
                                //echo "és most kifogja hagyni.<br>";
                                continue;
                            }

                            //echo $nap." ".$ora."<br>";
                        }
                        
                        
                        //beosztások beolvasása
                        if ($beos = $this->getBeosztasok("{$nap} {$ora}", $this->helyszin, $this->szuresTipus, $orvosId)) { 
                            //szabad orvos kiválasztása
                            foreach ($beos as &$beoData) {
                                //Meg kell találnom azokat a beokat, amiknél jelezve van, hogy van backup plan-jük. vagy orvost? Nézzük meg orvosra, úgy talán
                                if ($this->orvosIdopontIsFree("{$nap} {$ora}", $beoData["orvosid"], $binterval)) {
                                    if (!($beoData["ispotig"] == 1 && $freeTimes != 0)) {
                                        $freeTimes++;
                                        $buttonClass = "foglalhatobtn";

                                        $varolista = 0;
                                        if ($beoData["csaksorban"] == 1 && strpos($beoData["orvosnev"], "Várólista") !== false) {
                                            $varolista = 1;
                                        }
                                        $buttonTitle = $_SESSION["helyszindata"]["no_doctor_select"] == 0 ? "{$orvosData["nev"]}" : "szabad időpont";
                                        $buttonJava = "varolista={$varolista};chooseIdoPont(\"{$nap} {$ora}\",{$binterval},{$orvosId},{$this->helyszin},{$this->szuresTipus});return false;";
                                        break;
                                    }
                                }
                            }
                        }

                        //csak sorban foglalható időpontok intézése
                        if (isset($beoData["csaksorban"]) && $beoData["csaksorban"] == 1 && isset($elsoIdopont[$nap]) && $buttonClass == "foglalhatobtn") {
                            $buttonJava = "nemfogs(\"{$elsoIdopont[$nap]}\");return false;";
                            $buttonClass .= " halv";

                        }
                        if (!isset($elsoIdopont[$nap]) && $buttonClass == "foglalhatobtn") {
                            $elsoIdopont[$nap] = $ora;
                        }

                        //teszt: minden időpont foglalható
                        //$buttonJava="chooseIdoPont(\"{$nap} {$ora}\");return false;";

                        $fesztivalOverride = CompanyService::isFesztivalCompany() && strtotime(date("Y-m-d")) == strtotime($nap);

                        if (strtotime("now + {$dist}") > strtotime("{$nap} {$ora}")) {
                            //mégse foglalható, múltbéli dátum vagy túl közeli
                            if (!$fesztivalOverride) {
                                $buttonTitle = "";
                                $buttonClass = "foglaltbtn";
                                $buttonJava = "nemfog();return false;";
                            }
                        }

                        if (strtotime("now + {$distFullDay}") > strtotime("{$nap} 23:59:59")) {
                            //mégse foglalható, csak x napra előre foglalható
                            $buttonTitle = "";
                            $buttonClass = "foglaltbtn";
                            $buttonJava = "nemfog();return false;";
                        }

                        if (isset($beoData["csaksorban"]) && $beoData["csaksorban"] == 1 && strpos($beoData["orvosnev"], "Várólista") !== false) {
                            $ora = $step . ".";
                            $buttonStyle = "width:37px";
                        }

                        $btn = "<a class='{$buttonClass}' style='{$buttonStyle}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a>";

                        //csak fordított sorrendben időpontok intézése
                        if (isset($beoData["csaksorban"]) && $beoData["csaksorban"] == 2 && $buttonClass == "foglalhatobtn") {
                            $lastButton = $btn;
                            $buttonJava = "nemfogs2();return false;";
                            $buttonClass .= " halv";
                            $btn = "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a>";
                        }

                        //csomag override
                        //új managerfoglalás módszer
                        if (!empty($this->packContentTypes)) {
                            if (!isset($availableData[$nap])) {

                                $availableData[$nap] = $this->getPackageAvailabilityForDay($nap,true,$_GET);

                                if (session_id() == "6f4e9bbellt7r9qhrsvrsft1ge") {
                                    //$sectionHTML.= "<pre>";
                                    //$sectionHTML.= print_r($availableData, true);
                                    //$sectionHTML.= "</pre>";
                                }
                            }

                            if (!empty($availableData[$nap]["error"])) {
                                $buttonTitle = "";
                                $buttonClass = "foglaltbtn";
                                $buttonJava = "nemfog();return false;";
                                $btn = "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a><br/>";
                                $dayError = "<div style='font-size:11px;width:100px;margin: 10px auto;'>{$availableData[$nap]["error"]}</div>";
                            }

                            //Órákénti ellenőrzés
                            /*if(CompanyService::isSuzukiGHC()){
                                if (!isset($availableData["{$nap} {$ora}"])) {
                                    $availableData["{$nap} {$ora}"] = $this->getPackageAvailabilityForHour("{$nap} {$ora}",true,$_GET);
                                    if (!empty($availableData["{$nap} {$ora}"]["error"])) {
                                        //echo "<pre>";
                                        //print_r($availableData["{$nap} {$ora}"]);
                                        //echo "</pre>";
                                        $buttonTitle = "";
                                        $buttonClass = "foglaltbtn";
                                        $buttonJava = "nemfog();return false;";
                                        $btn = "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a><br/>";
                                        $dayError = "<div style='font-size:11px;width:100px;margin: 10px auto;'>{$availableData["{$nap} {$ora}"]["error"]}</div>";
                                    }
                                }
                            }*/
                        }

                        //sorszám override aldi esetében
                        if (Booking_Constants::SQL_DB == "hungariamed" && CompanyService::isALDI() && $this->szuresTipus == Booking_Constants::TUDOSZURES_ID) {
                            $jaratok = ["08:30", "09:30","10:00", "10:30","11:15", "11:30", "12:30", "13:30", "14:00", "14:30"];

                            //Ha az első járat ne ma 08:30-as, ezzel betudom állítani a megfelelő járatot első kiíráshoz.
                            if (empty($jaratStart)) {
                                $jaratStart = $ora;
                                $jarat = array_search($ora,$jaratok);
                                $aktualisJarat = $jaratok[$jarat];
                            }

                            if ($sorszam % 6 == 1) {
                                $aktualisJarat = $jaratok[$jarat];
                                $sectionHTML .= "<div style='margin-top:10px;border-bottom:1px solid #ccc;border-top:1px solid #ccc;padding: 5px 0px;'>{$jaratok[$jarat]}-as járat</div>";
                                $jarat++;
                            }

                            $btn = "<a class='{$buttonClass}' title='{$buttonTitle}'  onclick='setJarat(\"{$aktualisJarat}\");{$buttonJava}' href='#' style='min-width: 40px;'>{$sorszam}.</a><br/>";
                            $sorszam++;
                           
                           

                        }

                        //bme és dr lászló larissza esetén minden időpont legyen foglalt
                        if ($buttonClass == "foglalhatobtn" && CompanyService::isBME() && $orvosId == 841) {
                            $buttonClass = "foglaltbtn";
                            $buttonJava = "nemfog();return false;";
                            $btn = "<a class='{$buttonClass}' title='' onclick='{$buttonJava}' href='#'>{$ora}</a><br/>";
                        }

                        if ($buttonClass == "foglalhatobtn" && CompanyService::isKRE() && !empty($this->kiegBeosztas)) {
                            //kiegészítő vizsgálatokkal csak együtt szabad időpontok mutatása
                            $kiegTimefound = false;
                            foreach ($this->kiegBeosztas as $kiegBeoszta) {
                                $kiegTime = date("Y-m-d H:i", strtotime($kiegBeoszta["date"]));
                                if ($kiegTime == "{$nap} {$ora}") {
                                    $kiegTimefound = true;
                                    break;
                                }
                            }

                            if (!$kiegTimefound) {
                                $buttonClass = "foglaltbtn";
                                $buttonJava = "nemfog();return false;";
                                $btn = "<a class='{$buttonClass}' title='' onclick='{$buttonJava}' href='#'>{$ora}</a><br/>";
                            }
                        }

                        //A foglalt időpontokat le rejtem, nincs szükség a megjelenítésükre, plusz be zavar az óránként 1 időpont kipakolásba
                        if(CompanyService::isSuzukiGHC()){
                            if($buttonClass == "foglaltbtn"){
                                //echo $ora."<br>";
                                continue;
                            }

                            /**
                             * Meg kell vizsgáljam azt is hogy férfi v. nőről van szó... óránként csak 11 férfi foglalhat...
                             * ezt a legegyszerűbben úgy érhetem el, hogyha az adott órára lehivom a foglalt időpontokat, 
                             * törzszámok alapján megnézem a nemet a segéd táblában és egy counter alapján ellenőrzöm hogy elérhet-e a limitet vagy még foglalhat.
                             * A férfi foglalási szám függ a dátumtól, órától...
                             * az emlő napokon max 5 ember, ami 17,18,19,22,23,03
                            */

                            $maxFoglalhatoFerfi = 9;
                            $emlonapok = ["2025-09-17","2025-09-18","2025-09-19","2025-09-22","2025-09-23","2025-10-03"];

                            if($ferfiDolgozo=sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=? AND nem=1",[$_SESSION["user"]["torzsszam"]])->fetch(PDO::FETCH_ASSOC)){
                                //Le kell kérdezzem az összes férfi időpontot...
                                $currentora01 = date("H",strtotime($ora));
                                $nextora = date("H",strtotime($ora." + 1 hour"));

                                //Emlő napokon max 5 férfi...
                                if(in_array($nap,$emlonapok)){
                                    //echo "ez egy emlő nap!({$nap})<br>";
                                    $maxFoglalhatoFerfi=5;
                                    if($currentora01>="13"){
                                        $maxFoglalhatoFerfi=11;
                                    }
                                }

                                if(!in_array($nap,$emlonapok)){
                                    if($currentora01>="13"){
                                        //echo "Már délután van! ({$currentora01})<br>";
                                        $maxFoglalhatoFerfi=15;
                                    }
                                }


                                $ferfiIdopontok = sql_query("SELECT * FROM foglalasok fogl
                                                             LEFT JOIN felhasznalok felh ON felh.id=fogl.paciensid
                                                             LEFT JOIN ghc_segedtabla gs ON gs.torzsszam=felh.torzsszam
                                                             WHERE gs.torzsszam!='' AND gs.nem=1 AND fogl.helyszinid=? AND fogl.szurestipusid IN(216,217)
                                                             AND datum BETWEEN '{$nap} {$currentora01}%' AND '{$nap} {$nextora}%'",
                                                             [640])->fetchAll(PDO::FETCH_ASSOC);
                                foreach($ferfiIdopontok as $index58=>$ferfiak){
                                    //echo date("H",strtotime($ferfiak["datum"])).">.".date("H",strtotime($ora))."<br>";
                                    if(date("H",strtotime($ferfiak["datum"]))>date("H",strtotime($ora))){
                                        //echo "bele mentem.<br>";
                                        unset($ferfiIdopontok[$index58]);
                                    }
                                    //echo $ferfiak["nev"]." - ".$ferfiak["datum"]."({$ora})<br>";
                                }
                                //echo $nap." ".$currentora01. " - ".$nextora."<br>";
                                //echo "A férfi időpontok száma: ".count($ferfiIdopontok)."/{$maxFoglalhatoFerfi} ($buttonClass)<br>";
                                if(count($ferfiIdopontok)>=$maxFoglalhatoFerfi){
                                    //echo "belementem.<br>";
                                    continue;
                                    //$buttonClass == "foglaltbtn";
                                }

                            }

                            $maxFoglalhatoNoHaVanExtra = 6;
                            if($noiDolgozo=sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=? AND nem=2",[$_SESSION["user"]["torzsszam"]])->fetch(PDO::FETCH_ASSOC)){

                                $currentora02 = date("H",strtotime($ora));
                                $nextora = date("H",strtotime($ora." + 1 hour"));

                                if(isset($_GET["szurestipus292"])){
                                    $maxFoglalhatoNoHaVanExtra=10;
                                    $noiIdopontok = sql_query("SELECT * FROM foglalasok fogl
                                                                LEFT JOIN felhasznalok felh ON felh.id=fogl.paciensid
                                                                LEFT JOIN ghc_segedtabla gs ON gs.torzsszam=felh.torzsszam
                                                                WHERE gs.torzsszam!='' AND gs.nem=2 AND fogl.helyszinid=? AND fogl.szurestipusid IN(216,217)
                                                                AND fogl.szuldatum > '1985-12-31' AND datum BETWEEN '{$nap} {$currentora02}%' AND '{$nap} {$nextora}'",
                                                                [640])->fetchAll(PDO::FETCH_ASSOC);
                                    foreach($noiIdopontok as $index59=>$nok){
                                        //echo date("H",strtotime($nok["datum"])).">.".date("H",strtotime($ora))."<br>";
                                        if(date("H",strtotime($nok["datum"]))>date("H",strtotime($ora))){
                                            //echo "bele mentem.<br>";
                                            unset($noiIdopontok[$index59]);
                                        }
                                        //echo $nok["nev"]." - ".$nok["datum"]."({$ora})<br>";
                                    }
                                    //echo $nap." ".$currentora02. " - ".$nextora."<br>";
                                    //echo "A női időpontok száma: ".count($noiIdopontok)."/{$maxFoglalhatoNoHaVanExtra}<br>";
                                    if(count($noiIdopontok)>=$maxFoglalhatoNoHaVanExtra){
                                        //echo "belementem.<br>";
                                        continue;
                                        //$buttonClass == "foglaltbtn";
                                    }
                                   
                                }
                                if(isset($_GET["szurestipus112"])){
                                    $noiIdopontok = sql_query("SELECT * FROM foglalasok fogl
                                                                LEFT JOIN felhasznalok felh ON felh.id=fogl.paciensid
                                                                LEFT JOIN ghc_segedtabla gs ON gs.torzsszam=felh.torzsszam
                                                                WHERE gs.torzsszam!='' AND gs.nem=2 AND fogl.helyszinid=? AND fogl.szurestipusid IN(216,217)
                                                                AND fogl.szuldatum <= '1985-12-31' AND datum BETWEEN '{$nap} {$currentora02}%' AND '{$nap} {$nextora}'",
                                                                [640])->fetchAll(PDO::FETCH_ASSOC);
                                    foreach($noiIdopontok as $index59=>$nok){
                                        //echo date("H",strtotime($nok["datum"])).">.".date("H",strtotime($ora))."<br>";
                                        if(date("H",strtotime($nok["datum"]))>date("H",strtotime($ora))){
                                            //echo "töröltem.<br>";
                                            unset($noiIdopontok[$index59]);
                                        }
                                        //echo $nok["nev"]." - ".$nok["datum"]."({$ora})<br>";
                                    }
                                    //echo $nap." ".$currentora02. " - ".$nextora."<br>";
                                    //echo "A női időpontok száma: ".count($noiIdopontok)."/{$maxFoglalhatoNoHaVanExtra}<br>";
                                    if(count($noiIdopontok)>=$maxFoglalhatoNoHaVanExtra){
                                        //echo "belementem.<br>";
                                        continue;
                                        //$buttonClass == "foglaltbtn";
                                    }
                                }
                            }
                            


                            

                            if(!isset($csomagBeo[$nap])){
                                //Itt kell megvizsgáljam hogy a csomagba tartozó vizsgálatokhoz az adott órában van-e szabad hely...
                                $checkForTypes = $this->packContentTypes;
                                $checkForTypes = $this->utils->ghcNoiCsomagKiegeszites($checkForTypes);

                                foreach($checkForTypes as $packTypeIndex=>$packTypeId){
                                    if(!isset($_GET["szurestipus{$packTypeId}"])){
                                        //echo $checkForTypes[$packTypeIndex]." törölve.<br>";
                                        unset($checkForTypes[$packTypeIndex]);
                                        
                                        $checkForTypes = array_values($checkForTypes);
                                    }
                                }

                                

                                //echo "<pre>";
                                //print_r($checkForTypes);
                                //echo "</pre>";

                                $possibleIdopont[$nap] = [];
                                foreach($checkForTypes as $typeId){
                                    $csomagBeo[$nap] = sql_query("SELECT beonap,tol,ig,aktiv,binterval,{$typeId} as szurestipusid FROM orvos_beosztas_new WHERE helyszinid=? AND INSTR(tipusok,?) AND beonap=? AND aktiv=1 ORDER BY tol ASC",
                                                    [$this->helyszin, "|{$typeId}|",$nap])->fetchAll(PDO::FETCH_ASSOC);
                                    //echo "<pre>";
                                    //print_r($csomagBeo[$nap]);
                                    //echo "</pre>";
                                    $possibleIdopontok[$nap][$typeId] = [];
                                    foreach($csomagBeo[$nap] as $beoData){
                                        $to_time = strtotime("{$beoData["beonap"]} {$beoData["tol"]}");
                                        $from_time = strtotime("{$beoData["beonap"]} {$beoData["ig"]}");
                                        $possibleTimes = ((round(abs($to_time - $from_time) / 60,2))/$beoData["binterval"]);
                                        for($o=0;$o<$possibleTimes;$o++){
                                            $possibleIdopontok[$nap][$typeId][] = date("Y-m-d H:i",strtotime("{$beoData["beonap"]} {$beoData["tol"]} + ".($o*$beoData["binterval"])." minutes"));
                                        }  
                                    }
                                }
                            }

                            $hour = date("H:00",strtotime($ora));
                            $available = 0;
                            if(isset($checkForTypes)){
                                foreach($checkForTypes as $typeId){
                                    //echo "({$nap}) ".$typeId." óra: {$hour}\n<br>";
                                    foreach($possibleIdopontok[$nap][$typeId] as $index=>$value){
                                        //Azokba az időpontokba megyek csak be, ami az adott időpontban van
                                        if($typeId==292){
                                            //echo "{$value}>={$nap} {$hour}&&{$value}<=".date("Y-m-d H:i",strtotime("{$nap} {$hour} + 1 hour"))."\n<br>";
                                        }
                                        //echo "{$value}>={$nap} {$hour}&&{$value}<=".date("Y-m-d H:i",strtotime("{$nap} {$hour} + 1 hour"))."\n<br>";
                                        //echo "{$value}(".strtotime($value).") >= {$nap} {$hour}(".strtotime("{$nap} {$hour}").") && {$value}(".strtotime($value).") <= ".date("Y-m-d H:i",strtotime("{$nap} {$hour} + 1 hour"))."(".strtotime("{$nap} {$hour} + 1 hour").")<br>";
                                        if(strtotime($value)>=strtotime("{$nap} {$hour}") && strtotime($value)<strtotime("{$nap} {$hour} + 1 hour")){
                                            if($Availablity = sql_query("SELECT * FROM foglalasok WHERE helyszinid=? AND szurestipusid=? AND datum=?",[$this->helyszin,$typeId,$value])->fetch(PDO::FETCH_ASSOC)){
                                                if($typeId==292){
                                                    //echo $Availablity["datum"]." - {$Availablity["nev"]}\n<br>";
                                                }
                                                //Ha foglalt, törlöm tömbből, legközelebb nem is fog már rá próbálni így
                                                //unset($possibleIdopontok[$nap][$typeId][$index]);
                                                //$possibleIdopontok[$nap] = array_values($possibleIdopontok[$nap]);
                                            }else{
                                                
                                                //echo "a {$value} időpont szabad a {$typeId}-hez\n<br>";
                                                $available++;
                                                break 1;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            
                            if($available!=count($checkForTypes)){
                                //echo "{$available}!=".count($checkForTypes)." ({$buttonClass})\n\n<br><br>";
                                continue;
                            }
                            
                            if(!isset($dayError) && $buttonClass=="foglalhatobtn"){
                                $currentora = date("H",strtotime($ora));
                                //echo "a currentora: {$currentora}<br>";
                                
                                $btn = "<a class='{$buttonClass}' title='' onclick='setJarat(\"{$currentora}:00\");{$buttonJava}' href='#'>{$currentora}:00</a><br/>";
                            }else{
                                //echo $dayError." - ".$buttonClass;
                            }
                            
                        }

                        if(CompanyService::isAszMenedzser()){
                            if($this->szuresTipus==284){
                                if(in_array(date("w",strtotime($nap)),[3,5])){
                                    $r=sql_query("SELECT * FROM foglalasok WHERE INSTR(datum,?) AND szurestipusid=? AND cegid=?",
                                        [$nap,$this->szuresTipus,$_SESSION["helyszindata"]["id"]]
                                    )->fetchAll(PDO::FETCH_ASSOC);

                                    if(count($r)>=4){
                                        $buttonClass = "foglaltbtn";
                                        $buttonJava = "nemfog();return false;";
                                        $btn = "<a class='{$buttonClass}' title='' onclick='{$buttonJava}' href='#'>{$ora}</a><br/>";
                                    }
                                }
                            }
                        }

                        $sectionHTML .= "<div style='text-align:center;'>{$btn}</div>";


                        
                    }

                    if (isset($dayError)) {
                        $sectionHTML .= $dayError;
                        unset($dayError);
                    }

                    //debugdata
                    /*
                    if ($this->adminUser->managerTest() && isset($availableData[$nap])) {
                        foreach ($availableData[$nap]["timeTableForPackage"] as $key => $data) {
                            if ($key != $this->szuresTipus) {
                                $napHTML .= "<div style='font-size:11px;margin-top:10px;'>";
                                $napHTML .= "<div><strong>{$data["tipusnev"]}</strong></div>";
                                $napHTML .= "<div>{$data["orvosnev"]}</div>";
                                $napHTML .= "<div>";
                            }
                        }
                        //$napHTML.= print_r($availableData[$nap], true);
                    }
                    */

                    $sectionHTML .= "</div>";

                    if (isset($lastButton) && isset($btn)) {
                        $sectionHTML = str_replace($btn, $lastButton, $sectionHTML);
                        unset($lastButton);
                    }

                    $html .= $sectionHTML;
                }
                
                

                $html.= "</div>";
            }
            $html .= "</div>";
            $html .= "</td>";
            
        }
        
        $html .= "</tr></table>";
        $html .= "</div>";
        //die($html);
        return json_encode(array("error" => "", "html" => $html));

    }



    private function preReservationProtocol($cegId, $helyszinId, $orvosId, $szurestipusId) {
        $dist = "24 hour";
        if (Booking_Constants::DEFAULT_PLACE_IDS[0] == $helyszinId) {
            $dist = "6 hour";
        }

        $distFullDay = "0 day";
        //ennyi napon belül kell foglalni

        if (Booking_Constants::SITE_DOMAIN == "hungariamed.hu") {
            //if ($helyszinId == 1 || $helyszinId == 614 || CompanyService::isFesztivalCompany()) {
                //jász utca vagy fesztivál bármikor foglalhat
                //$dist = "-10 hour";
            //}

            if ($cegId == 888) {
                $dist = "6 hour";
            }

            //BFKH - Buda / Pest / VIP
            if ($cegId == 136 || $cegId == 131 || $cegId == 137) {
                //cib
                $dist = "24 hour";
                /*if (date("N") == 4) {
                    $dist = "48 hour";
                }
                if (date("N") == 5) {
                    $dist = "72 hour";
                }*/
            }

            if ($cegId == 6) {
                //cib
                $dist = "72 hour";
                if (date("N") == 4) {
                    $dist = "96 hour";
                }
                if (date("N") == 5) {
                    $dist = "120 hour";
                }
            }

            if ($cegId == 642) {
                $dist = "6 hour";
            }

            if ($cegId == 937 && Booking_Constants::SQL_DB == "hungariamed") {
                $dist = "0 hour";
            }

            if (in_array($cegId, [82, 664, 340, 347, 348]) && Booking_Constants::SQL_DB == "hungariamed") {
                //0 órás cégek
                $dist = "0 hour -2 hour";
            }

            if ($cegId == 46 && $helyszinId != 320) {
                //vodafone
                $dist = "72 hour";
                if (date("N") == 4) {
                    $dist = "96 hour";
                }
                if (date("N") == 5) {
                    $dist = "120 hour";
                }
            }

            if ($orvosId == 36) {
                //36 - dr Bodonyi Melinda
                $distFullDay = "2 day";
            }

            if ($cegId == 4) {
                $dist = "0 hour";
            }

            if(in_array($cegId, [375])){
                $dist = "13 day";

                /*if (date("N") == 3) {
                    $dist = "5 day";
                }
                if (date("N") == 4) {
                    $dist = "6 day";
                }
                if (date("N") == 5) {
                    $dist = "7 day";
                }*/
            }

            if(in_array($cegId, [373,374,376,933])){
                $dist = "0 hour";
            }

            //lighttech
            if (in_array($cegId, [884, 858])) {
                $dist = "0 hour";
            }

            if(in_array($cegId, [342])){
                $dist = "0 hour";
            }

            //0 óra foglalási idő ha a Magyar Államkincstár orvosra akar foglalni :P
            if(in_array($orvosId,[1089,841])){
                $dist = "0 hour";
            }

            if (in_array($orvosId, [74, 25])) {
                //74 - Dr. Kővári Gábor 25 - Dr. Tiba Sándor
                $dist = "24 hour";
            }

            if($cegId==1242){
                if($szurestipusId==284){
                    $distFullDay="7 day";
                }
            }
        }

        //echo "|{$dist}|";
        return ["hour" => $dist, "day" => $distFullDay];
    }

    public function needTappenzCheckbox($helyszinId):bool {
        if ($helyszinId == 253 && $_SESSION["helyszindata"]["id"] == 74) {
            return true;
        }
        return false;
    }

    public function setHonnan($honnan)
    {
        $this->honnan = intval($honnan);
    }

    public function setHelyszin($helyszinId)
    {
        $this->helyszin = intval($helyszinId);
    }

    public function setTaj($taj)
    {
        $this->taj = $taj;
    }

    public function setSzuresTipus($szuresTipusId)
    {
        $this->szuresTipus = intval($szuresTipusId);
        $this->szuresTipusData = sql_fetch_array(sql_query("select * from szurestipusok where id=?", array($szuresTipusId)));
        $this->packContentTypes = $this->getPackContentTypes($szuresTipusId);
    }

    public function setNeme($neme)
    {
        $this->neme = intval($neme);
    }

    public function setBetegallomany($betegallomany)
    {
        if ($betegallomany != "true") $betegallomany = false;
        else $betegallomany = true;

        $this->betegallomany = $betegallomany;
    }


    private bool $debugPack = false;

    public function getPackageAvailabilityForDay($day, $limitTimes = true,$data=array(),$forcdeBeginHour=null):array {
        $vanFixError = false;
        $error = "";
        $timeTableForPackage = [];

        $checkForTypes = $this->packContentTypes;
        $checkForTypes[] = $this->szuresTipus; //csekkoljuk magát csomagot is, hogy van-e benne hely

        if(CompanyService::isSuzukiGHC()){
            if($noiDolgozo=sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=? AND nem=2",[$_SESSION["user"]["torzsszam"]])->fetch(PDO::FETCH_ASSOC))
            {
                //Emlő ultrahang
                if(strtotime($_SESSION["user"]["szuldatum"])<strtotime("1984-12-31")){
                $checkForTypes[] = 112;
                }

                //Mammográfia
                if(strtotime($_SESSION["user"]["szuldatum"])>strtotime("1985-12-31")){
                    $checkForTypes[] = 292;
                }

                //Ha 1985.01.01~1985.12.31 között született megkapja mind2 csomagot xd
                //Mindkettő
                if(strtotime($_SESSION["user"]["szuldatum"])>strtotime("1984-12-31")&&strtotime($_SESSION["user"]["szuldatum"])<strtotime("1986-01-01")){
                    $checkForTypes[] = 112;
                    $checkForTypes[] = 292;
                }    
            }
        }
        

        if (empty($this->szuresTipusMap)) {
            $res = sql_query("select * from szurestipusok");
            while ($row = sql_fetch_array($res)) {
                $this->szuresTipusMap[$row["id"]] = $row;
            }
        }

        foreach ($checkForTypes as $packTypeId) {
            $orvos = 0;

            //Orvos választás ha van előre küldött adatt
            if(isset($data["prefDoctor{$packTypeId}"])){
                $orvos = $data["prefDoctor{$packTypeId}"];
            }

            //Ha foglaló kivette a kötelező elemek közül a vizsgálatot akkor kihagyjuk a loopból ezt a szűréstípust.
            if(!isset($data["szurestipus{$packTypeId}"]) && (CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser() || CompanyService::isSuzukiGHC())){
                continue;
            }

            if ($beos = $this->getBeosztasok("{$day}", $this->helyszin, $packTypeId, $orvos, true)) {
                foreach ($beos as &$beoData) {
                    if ($beoData["nopack"] != 0 && !isset($GLOBAL["ezmostegysuzukifoglalas"])) {
                        continue;
                    }
                    
                    $orvosId     = $beoData["orvosid"];
                    $orvosNev    = $beoData["orvosnev"];
                    $interval    = $beoData["binterval"];
                    $step        = 0;
                    $beginHour   = intval(substr($beoData["tol"], 0, 2));
                    $beginMinute = intval(substr($beoData["tol"], 3, 2));

                    //kingának kivétel eltáv miatt
                    if (Booking_Constants::SQL_DB == "hungariamed" && $orvosId == 64 && isset($data["otherservices-{$packTypeId}-0"])) {
                        $interval = 60;
                    }

                    //Ha van küldött kezdési óra, akkor onnan kezdődik a szabad időpont keresés
                    //ebben bug van, át kell nézni ha máskor kell ilyen
                    if(!empty($forcdeBeginHour)){
                        $beginHour = $forcdeBeginHour;
                    }

                    while (true) {
                        if ($beoData["nap"] == 10 & $day != $beoData["beonap"]) {
                            break;
                        }

                        $ora = date("H:i", mktime($beginHour, $beginMinute + $step * $interval, 0, date("m"), date("d"), date("Y")));
                        //if (strtotime($ora) >= strtotime($beoMinMax["maxrendeles"])) {
                        if (strtotime($ora) >= strtotime($beoData["ig"])) {
                            break;
                        }
                        $step++;

                        if ($this->orvosIdopontIsFree("{$day} {$ora}", $beoData["orvosid"], $interval)) {
                            $timeData = ["idopont" => "{$day} {$ora}", "interval" => $interval, "orvosid" => $orvosId, "orvosnev" => $orvosNev, "tipusnev" => $this->szuresTipusMap[$packTypeId]["megnev"]];

                            if ($limitTimes) {
                                $timeTableForPackage[$packTypeId] = $timeData;
                                break 2;
                            } else {
                                $timeTableForPackage[$packTypeId][] = $timeData;
                            }
                        }
                    }
                }
            }

            if (!isset($timeTableForPackage[$packTypeId]) && $packTypeId == $this->szuresTipus) {
                $error = "Erre a napra elfogytak az időpontok!";
                $vanFixError = true;
            }

            if (!isset($timeTableForPackage[$packTypeId]) && !$vanFixError) {
                if (User::debugUser()) {
                    $text = "nincs időpont:<br/>";
                    if (substr_count($error, $text) == 0) {
                        $error .= $text;
                    }
                    if($packTypeId!=0){
                        $error .= "{$this->szuresTipusMap[$packTypeId]["megnev"]}<br/>";
                    }

                } else {
                    //die("itt{$error}".$vanFixError);
                    $text = "nincs időpont<br/>";
                    if (substr_count($error, $text) == 0) {
                        $error .= $text;
                    }
                }
            }
        }

        if (count($timeTableForPackage) < count($this->packContentTypes)) {
            //$error = "Nincs időpont erre a napra!";
        }

        if (strtotime("now") > strtotime("{$day} 00:00:00")) {
            $error = "Erre a napra már nem lehet foglalni<br/>";
        }

        if (User::debugUser()) {
            //$error = $forcdeBeginHour. " ". print_r($timeTableForPackage, true);
        }

        return ["error" => $error, "timeTableForPackage" => $timeTableForPackage];
    }

    public function getPackageAvailabilityForHour($idoPont, $limitTimes = true,$data=array(),$forcdeBeginHour=null):array {
        $vanFixError = false;
        $error = "";
        $timeTableForPackage = [];

        $checkForTypes = $this->packContentTypes;
        $checkForTypes[] = $this->szuresTipus; //csekkoljuk magát csomagot is, hogy van-e benne hely

        if(CompanyService::isSuzukiGHC()){
            if($noiDolgozo=sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=? AND nem=2",[$_SESSION["user"]["torzsszam"]])->fetch(PDO::FETCH_ASSOC))
            {
                //Emlő ultrahang
                if(strtotime($_SESSION["user"]["szuldatum"])<strtotime("1984-12-31")){
                $checkForTypes[] = 112;
                }

                //Mammográfia
                if(strtotime($_SESSION["user"]["szuldatum"])>strtotime("1985-12-31")){
                    $checkForTypes[] = 292;
                }

                //Ha 1985.01.01~1985.12.31 között született megkapja mind2 csomagot xd
                //Mindkettő
                if(strtotime($_SESSION["user"]["szuldatum"])>strtotime("1984-12-31")&&strtotime($_SESSION["user"]["szuldatum"])<strtotime("1986-01-01")){
                    $checkForTypes[] = 112;
                    $checkForTypes[] = 292;
                }    
            }
        }
        

        if (empty($this->szuresTipusMap)) {
            $res = sql_query("select * from szurestipusok");
            while ($row = sql_fetch_array($res)) {
                $this->szuresTipusMap[$row["id"]] = $row;
            }
        }

        foreach ($checkForTypes as $packTypeId) {
            $orvos = 0;

            //Orvos választás ha van előre küldött adatt
            if(isset($data["prefDoctor{$packTypeId}"])){
                $orvos = $data["prefDoctor{$packTypeId}"];
            }

            //Ha foglaló kivette a kötelező elemek közül a vizsgálatot akkor kihagyjuk a loopból ezt a szűréstípust.
            if(!isset($data["szurestipus{$packTypeId}"]) && (CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser() || CompanyService::isSuzukiGHC())){
                continue;
            }

            if ($beos = $this->getBeosztasok("{$idoPont}", $this->helyszin, $packTypeId, $orvos, true)) {
                foreach ($beos as &$beoData) {
                    if ($beoData["nopack"] != 0 && !isset($GLOBAL["ezmostegysuzukifoglalas"])) {
                        continue;
                    }
                    
                    $orvosId     = $beoData["orvosid"];
                    $orvosNev    = $beoData["orvosnev"];
                    $interval    = $beoData["binterval"];
                    $step        = 0;
                    $beginHour   = intval(substr($beoData["tol"], 0, 2));
                    $beginMinute = intval(substr($beoData["tol"], 3, 2));

                    //kingának kivétel eltáv miatt
                    if (Booking_Constants::SQL_DB == "hungariamed" && $orvosId == 64 && isset($data["otherservices-{$packTypeId}-0"])) {
                        $interval = 60;
                    }

                    //Ha van küldött kezdési óra, akkor onnan kezdődik a szabad időpont keresés
                    //ebben bug van, át kell nézni ha máskor kell ilyen
                    if(!empty($forcdeBeginHour)){
                        $beginHour = $forcdeBeginHour;
                    }
                    $idoData = explode(" ",$idoPont);

                    while (true) {
                        if ($beoData["nap"] == 10 & $idoData[0] != $beoData["beonap"]) {
                            break;
                        }

                        $ora = date("H:i", mktime($beginHour, $beginMinute + $step * $interval, 0, date("m"), date("d"), date("Y")));
                        //if (strtotime($ora) >= strtotime($beoMinMax["maxrendeles"])) {
                        if (strtotime($ora) >= strtotime($beoData["ig"])) {
                            break;
                        }
                        $step++;

                        if ($this->orvosIdopontIsFree($idoPont, $beoData["orvosid"], $interval)) {
                            $timeData = ["idopont" => $idoPont, "interval" => $interval, "orvosid" => $orvosId, "orvosnev" => $orvosNev, "tipusnev" => $this->szuresTipusMap[$packTypeId]["megnev"]];

                            if ($limitTimes) {
                                $timeTableForPackage[$packTypeId] = $timeData;
                                break 2;
                            } else {
                                $timeTableForPackage[$packTypeId][] = $timeData;
                            }
                        }
                    }
                }
            }

            if (!isset($timeTableForPackage[$packTypeId]) && $packTypeId == $this->szuresTipus) {
                $error = "Erre a napra elfogytak az időpontok!";
                $vanFixError = true;
            }

            if (!isset($timeTableForPackage[$packTypeId]) && !$vanFixError) {
                if (User::debugUser()) {
                    $text = "nincs időpont:<br/>";
                    if (substr_count($error, $text) == 0) {
                        $error .= $text;
                    }
                    if($packTypeId!=0){
                        $error .= "{$this->szuresTipusMap[$packTypeId]["megnev"]}<br/>";
                    }

                } else {
                    //die("itt{$error}".$vanFixError);
                    $text = "nincs időpont<br/>";
                    if (substr_count($error, $text) == 0) {
                        $error .= $text;
                    }
                }
            }
        }

        if (count($timeTableForPackage) < count($this->packContentTypes)) {
            //$error = "Nincs időpont erre a napra!";
        }


        if (User::debugUser()) {
            //$error = $forcdeBeginHour. " ". print_r($timeTableForPackage, true);
        }

        return ["error" => $error, "timeTableForPackage" => $timeTableForPackage];
    }




    public function getPackageAvailabilityForDayV2($day, $limitTimes = true, $data = [], $forceBeginHour = ""):array {
        $vanFixError = false;
        $error = "";
        $timeTableForPackage = [];
        $this->replicatedTimes = [];

        $checkForTypes = $this->packContentTypes;
        $checkForTypes[] = $this->szuresTipus; //csekkoljuk magát csomagot is, hogy van-e benne hely

        if (empty($this->szuresTipusMap)) {
            $res = sql_query("select * from szurestipusok");
            while ($row = sql_fetch_array($res)) {
                $this->szuresTipusMap[$row["id"]] = $row;
            }
        }

        if(CompanyService::isSuzukiGHC()){
            if($noiDolgozo=sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=? AND nem=2",[$_SESSION["user"]["torzsszam"]])->fetch(PDO::FETCH_ASSOC))
            {
                //Emlő ultrahang
                if(strtotime($_SESSION["user"]["szuldatum"])<strtotime("1984-12-31")){
                $checkForTypes[] = 112;
                }

                //Mammográfia
                if(strtotime($_SESSION["user"]["szuldatum"])>strtotime("1985-12-31")){
                    $checkForTypes[] = 292;
                }

                //Ha 1985.01.01~1985.12.31 között született megkapja mind2 csomagot xd
                //Mindkettő
                if(strtotime($_SESSION["user"]["szuldatum"])>strtotime("1984-12-31")&&strtotime($_SESSION["user"]["szuldatum"])<strtotime("1986-01-01")){
                    $checkForTypes[] = 112;
                    $checkForTypes[] = 292;
                }    
            }
        }

        foreach ($checkForTypes as $packTypeId) {
            $orvos = 0;
            $foundTimes = [];

            //Orvos választás ha van előre küldött adatt
            if(isset($data["prefDoctor{$packTypeId}"])){
                $orvos = $data["prefDoctor{$packTypeId}"];
            }

            //Ha foglaló kivette a kötelező elemek közül a vizsgálatot akkor kihagyjuk a loopból ezt a szűréstípust.
            if(!isset($data["szurestipus{$packTypeId}"]) && (CompanyService::isSuzukiTeszt() || CompanyService::isSuzukiMenedzser() || CompanyService::isSuzukiGHC())){
                continue;
            }

            //Meghívom az aktuális vizsgálathoz elérhető beosztásokat
            if ($beos = $this->getBeosztasok("{$day}", $this->helyszin, $packTypeId, $orvos, true)) {

                foreach ($beos as &$beoData) {
                    if ($beoData["nopack"] != 0) {
                        continue;
                    }

                    $orvosId     = $beoData["orvosid"];
                    $orvosNev    = $beoData["orvosnev"];
                    $interval    = $beoData["binterval"];
                    $step        = 0;
                    $beoMinMax   = $this->getMinMax($this->getBeosztasok($day, $this->helyszin, $packTypeId, $orvosId, true));
                    $beginHour   = intval(substr($beoMinMax["minrendeles"], 0, 2));
                    $beginMinute = intval(substr($beoMinMax["minrendeles"], 3, 2));
                    $strictCheck = Booking_Constants::SQL_DB == "hungariamed" && $orvosId == 64; //Kingának magasabb szintű ellenőrzés

                    while (true) {
                        if ($beoData["nap"] == 10 & $day != $beoData["beonap"]) {
                            break;
                        }

                        $ora = date("H:i", mktime($beginHour, $beginMinute + $step * $interval, 0, date("m"), date("d"), date("Y")));
                        //echo $ora."<br>";
                        //ha elérte a rendelés végét akkor kilép
                        if (strtotime($ora) >= strtotime($beoMinMax["maxrendeles"])) {
                            break;
                        }
                        $step++;

                        //Ebbe akkor fut bele, ha talál szabad időpontot
                        if ($this->orvosIdopontIsFree("{$day} {$ora}", $beoData["orvosid"], $interval)) {
                            $timeData = [
                                "idopont" => "{$day} {$ora}", 
                                "interval" => $interval, 
                                "orvosid" => $orvosId, 
                                "orvosnev" => $orvosNev, 
                                "tipusnev" => $this->szuresTipusMap[$packTypeId]["megnev"]
                            ];
                            //Ez nincs annyira használva
                            if ($limitTimes) {
                                $timeTableForPackage[$packTypeId] = $timeData;
                                break 2;
                            } else {
                                //második vagy teljesül
                                if (empty($forceBeginHour) || strtotime("{$day} {$forceBeginHour}") <= strtotime("{$day} {$ora}")) {
                                    $foundTimes[] = $timeData;
                                }
                            }
                        }
                    }
                }
            }

            //echo "<pre>";
            //print_r($foundTimes);
            //echo "</pre>";


            if (!empty($foundTimes) && !empty($data)) {
                $diff = 1000000;
                $optimalTime = [];
                $notSoGoodTime = [];

                foreach ($foundTimes as $foundTime) {
                    $checkDiff = abs(strtotime($data["datum"]) - strtotime($foundTime["idopont"]));
                    if ($checkDiff < $diff) {
                        foreach ($this->replicatedTimes as $reservedTime) {
                            $tempDiff = abs(strtotime($reservedTime["idopont"]) - strtotime($foundTime["idopont"]));
                            if ($tempDiff < 5*60) {
                                $notSoGoodTime = $foundTime;
                            }
                        }

                        if ($notSoGoodTime != $foundTime) {
                            $diff = $checkDiff;
                            $optimalTime = $foundTime;
                        }
                    }
                }

                if (empty($optimalTime)) {
                    $optimalTime = $notSoGoodTime;
                }

                $timeTableForPackage[$packTypeId] = $optimalTime;
                if (empty($forceBeginHour)) {
                    $this->replicatedTimes[] = $optimalTime;
                }
            }


            if (!isset($timeTableForPackage[$packTypeId]) && $packTypeId == $this->szuresTipus) {
                $error = "Erre a napra elfogytak az időpontok!";
                $vanFixError = true;
            }

            if (!isset($timeTableForPackage[$packTypeId]) && !$vanFixError) {
                if (User::debugUser()) {
                    $text = "nincs időpont:<br/>";
                    if (substr_count($error, $text) == 0) {
                        $error .= $text;
                    }
                    $error .= "{$this->szuresTipusMap[$packTypeId]["megnev"]}<br/>";
                } else {
                    //die("itt{$error}".$vanFixError);
                    $text = "nincs időpont<br/>";
                    if (substr_count($error, $text) == 0) {
                        $error .= $text;
                    }
                }
            }
        }

        if (count($timeTableForPackage) < count($this->packContentTypes)) {
            //$error = "Nincs időpont erre a napra!";
        }

        if (strtotime("now") > strtotime("{$day} 00:00:00")) {
            $error = "Erre a napra már nem lehet foglalni<br/>";
        }

        return ["error" => $error, "timeTableForPackage" => $timeTableForPackage];
    }



    public function selectOrvosForIdopont($idopont, $orvos = 0)
    {
        $nap           = substr($idopont, 0, 10);
        $ora           = substr($idopont, 11, 5);
        $cegid         = $_SESSION["helyszindata"]["id"];
        $helyszin      = $this->helyszin;
        $orvosData     = false;
        if (!$this->szuresTipusData = sql_fetch_array(sql_query("select * from szurestipusok where id=?", array($this->szuresTipus)))) {
            return false;
        }

        //időpontra beosztott orvosok kiolvasása
        $resb = sql_query("SELECT * FROM orvos_beosztas_new b 
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		WHERE b.`helyszinid`=? 
        AND ((INSTR(b.beocegek, ?) OR b.beocegek='') OR (b.nap=10 AND b.open_beo_for_all_company=1 AND DATE_SUB(CONCAT(b.beonap, ' ', b.tol), INTERVAL ROUND(b.release_beo_before_expire_time) HOUR)<NOW())) 
		AND (nap=WEEKDAY(?)+1 or beonap=?) AND TIME(tol)<=TIME(?) AND TIME(IF(potig<>'',potig,ig))>TIME(?) AND INSTR(b.tipusok,?) " . ($orvos == 0 ? "" : "and b.orvosid='{$orvos}'") . " and b.aktiv=1
        ORDER BY o.onlytel,o.id", array($helyszin, "|{$cegid}|", $nap, $nap, $ora, $ora, "|{$this->szuresTipus}|"));

        while ($rowb = sql_fetch_array($resb)) {
            //orvos foglalt-e?
            if (!sql_fetch_array(sql_query("SELECT datum FROM foglalasok WHERE datum=? AND orvosassigned=?", array($idopont, $rowb["orvosid"])))) {
                //nap foglalt-e
                if (!sql_fetch_array(sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap=? and (szurestipusid=0 or szurestipusid=?)", array($this->helyszin, $cegid, $nap, $this->szuresTipus)))) {
                    //orvos szabad ->
                    if (!sql_fetch_array(sql_query("select * from szabadsag where oid=? and datumtol<=? and datumig>=?", array($rowb["orvosid"], $nap, $nap)))) {
                        //+nincs szabadságon
                        $orvosData = $rowb;
                    }
                }
            }
        }

        if (!$orvosData) {
            return false;
        }

        //pack esetén megnézzük a többi szolgáltatás foglaltságát is mielőtt visszaadjuk a szabad orvost
        if ($this->szuresTipusData["ispack"] == 1) {
            $this->packContentTypes = $this->getPackContentTypes($this->szuresTipus);

            $packTimeData = $this->getPackageAvailabilityForDay($nap);
            if (empty($packTimeData["error"])) {
                return $orvosData;
            } else {
                return false;
            }
        }

        return $orvosData;
    }


    private function getSzunnapok()
    {
        $szunnapok = [];
        $rows = sql_fetch_array(sql_query("select * from settings"));
        $n = explode(",", $rows["szunnapok"]);
        for ($i = 0; $i < count($n); $i++) {
            $nap = trim($n[$i]);
            if (isset($_SESSION["helyszindata"]) && $_SESSION["helyszindata"]["id"] == 114) {
                continue;
            }
            $szunnapok[] = $nap;
        }
        return $szunnapok;
    }


    private function getOrvosListForIdopontValaszto($day) {
        $beosztasok = $this->getBeosztasok($day, $this->helyszin, $this->szuresTipus);
       
        $orvosAvailable = [];
        if($beosztasok){
            foreach ($beosztasok as $beosztas) {
                if (Booking_Constants::SQL_DB == "keltexmed" && $beosztas["orvosid"] == 403) {
                    //skip dr. megyeri márta - keltexmed temp
                    continue;
                }
                $orvosAvailable[] = $beosztas["orvosid"];
            }
        }
        
        return array_unique($orvosAvailable);
    }

    private function getPackContentTypes($szuresTipusId)
    {
        $types = [];
        if (isset($this->szuresTipusData["ispack"]) && $this->szuresTipusData["ispack"] == 1) {
            $res = sql_query("select k.* from szurescsomagok_kapcs k LEFT JOIN szurestipusok t ON t.id=k.szurestipusid where k.csomagid=? and k.noreservation=0 order by t.megnev", array($szuresTipusId));
            while ($row = sql_fetch_array($res)) {
                if ($row["nemerequired"] == 0 || $row["nemerequired"] == $this->neme || $this->neme == 0) {
                    $types[] = $row["szurestipusid"];
                }
            }
        }
        return $types;
    }

    private function getGenderPackContentTypes($szuresTipusId)
    {
        $types = [];
        if ($this->szuresTipusData["ispack"] == 1) {
            $res = sql_query("select * from szurescsomagok_kapcs where csomagid=?", array($szuresTipusId));
            while ($row = sql_fetch_array($res)) {
                if ($row["nemerequired"] != 0 && $row["nemerequired"] != $this->neme) {
                    $types[] = $row["szurestipusid"];
                }
            }
        }
        return $types;
    }

    public function selectFreeOrvosForIdopont($fid)
    {
        $oid = 0;

        if ($foglalasData = sql_fetch_array(sql_query("select * from foglalasok where id=?", array($fid)))) {
            $idopont = date("Y-m-d H:i", strtotime($foglalasData["datum"]));
            if ($foglalasData["orvosassigned"] != 0) {
                $oid = $foglalasData["orvosassigned"];
            } else {
                if ($beos = $this->getBeosztasok($idopont, $foglalasData["helyszinid"], $foglalasData["szurestipusid"])) {
                    //print_r($beos);
                    //szabad orvos kiválasztása
                    foreach ($beos as &$beoData) {
                        if ($this->orvosIdopontIsFree($idopont, $beoData["orvosid"], $beoData["binterval"])) {
                            $oid = $beoData["orvosid"];
                            break;
                        }
                    }
                }
            }
        }

        if ($oid == 0 && !$this->adminUser->authenticated()) {
            $service = new NotificationService();
            $service->sendDebugEmail("Hibás időpontfoglalás szültett! 0-ás orvosassigned!!!", "<p>A foglalás azonosítója: {$fid}</p>");
        }

        return $oid;
    }

    public function orvosIdopontIsFree($idoPont, $orvosId, $interval = 15) {
        $idoPont = $idoPont.":00";
        $nap     = substr($idoPont, 0, 10);
        $free    = false;
        $wadd    = "";

        //if ($helyszin != 0) {
        //    $wadd = "or (helyszinid='{$helyszin}' and cegid=0 and orvosassigned=0)";
        //}

        if (!sql_fetch_array(sql_query("select * from szabadsag where oid=? and datumtol<=? and datumig>=?", [$orvosId, $nap, $nap]))) {
            //if (!$reservationData = sql_fetch_array(sql_query("SELECT id, datum FROM foglalasok WHERE datum>=? AND datum<=? AND datum>DATE_SUB(?, INTERVAL IF(rinterval=0, 5, rinterval) MINUTE) AND (orvosassigned=? {$wadd})", [$nap." 00:00:00", $idoPont, $idoPont, $orvosId]))) {
            if (!$reservationData = sql_fetch_array(sql_query("SELECT id, datum FROM foglalasok WHERE datum>=?
                                   AND ((datum<=? AND datum>DATE_SUB(?, INTERVAL IF(rinterval=0, 5, rinterval) MINUTE)) OR (datum>=? AND datum<DATE_ADD(?, INTERVAL ? MINUTE)))
                                   AND (orvosassigned=? {$wadd})", [$nap." 00:00:00", $idoPont, $idoPont, $idoPont, $idoPont, $interval, $orvosId]))) {
                $free = true;
            } else {
                $this->reservedTimeId = $reservationData["id"];
            }
        }

        return $free;
    }

    private function shrinkReservation($idoPont, $orvosId, $rinterval) {
        if (!empty($this->reservedTimeId)) {
            if ($reservationData = sql_fetch_array(sql_query("select rinterval from foglalasok where id=?", [$this->reservedTimeId]))) {
                sql_query("update foglalasok set rinterval=1 where id=?", [$this->reservedTimeId]);

                for ($offset = 1; $offset <= $rinterval; $offset++) {
                    $newTime = date("Y-m-d H:i", strtotime("{$idoPont} +{$offset} minute"));
                    if ($this->orvosIdopontIsFree($newTime, $orvosId, $rinterval)) {
                        $this->newAddTime = $newTime;
                        return;
                    }
                }

                //nem sikerült beszúrni, visszaállítjuk...
                sql_query("update foglalasok set rinterval=? where id=?", [$reservationData["rinterval"], $this->reservedTimeId]);
            }
        }
    }

    private function getMinMax($beosztasok):array {
        $minRendeles = date("Y-m-d 23:59:59");
        $maxRendeles = date("Y-m-d 00:00:00");

        foreach ($beosztasok as $beosztas) {
            if (strtotime(date("Y-m-d {$beosztas["tol"]}")) < strtotime($minRendeles)) {
                $minRendeles = date("Y-m-d {$beosztas["tol"]}");
            }
            if (strtotime(date("Y-m-d {$beosztas["ig"]}")) > strtotime($maxRendeles)) {
                $maxRendeles = date("Y-m-d {$beosztas["ig"]}");
            }
            if (!empty($beosztas["potig"]) && strtotime(date("Y-m-d {$beosztas["potig"]}")) > strtotime($maxRendeles)) {
                $maxRendeles = date("Y-m-d {$beosztas["potig"]}");
            }
        }

        return ["minrendeles" => date("H:i", strtotime($minRendeles)), "maxrendeles" => date("H:i", strtotime($maxRendeles))];
    }

    public function getBeosztasok($idoPont, $helyszin, $szuresTipus, $orvos = 0, $isPack = false) {
        $nap         = substr($idoPont, 0, 10);
        $ora         = substr($idoPont, 11, 5);
        $helyszin    = intval($helyszin);
        $szuresTipus = intval($szuresTipus);
        $cegId       = $_SESSION["helyszindata"]["id"] ?? Booking_Constants::DEFAULT_COMPANY_ID;
        if (isset($_SESSION["helyszinceg"]) && isset($GLOBALS["admin"])) {
            $cegId = $_SESSION["helyszinceg"];
        }

        if (!$isPack) {
            if (count($this->kiegIds) == 1) {
                $isPack = true;
            }
        }

        $wora = $wceg = "";
        $wcegSecondary = "999999999999999";
        $wnoreservation = $isPack ? "":"and b.noreservation=0";

        if (!empty($ora)) {
            $wora = "AND TIME(tol)<=TIME('{$ora}') AND TIME(IF(potig='', ig, potig))>TIME('{$ora}')";
        }

        //admin esetén lazább szűrés
        if (isset($GLOBALS["admin"])) {
            if (!$this->adminUser->allCegJog()) {
                $wceg = "and (instr(b.beocegek, '|{$cegId}|') or b.beocegek='')";
            }
        } else {
            if ($isPack && Booking_Constants::SQL_DB == "hungariamed") {
                //manadzserek cég jelölés, hogy oda csak menedzserek foglalhassanak
                $wcegSecondary = "92";
            }
            $wceg = "AND ((INSTR(b.beocegek, '|{$cegId}|') OR INSTR(b.beocegek, '|{$wcegSecondary}|')) OR (b.nap=10 AND b.open_beo_for_all_company=1 AND DATE_SUB(CONCAT(b.beonap, ' ', b.tol), INTERVAL ROUND(b.release_beo_before_expire_time) HOUR) < NOW()))";
        }

        //időpontra beosztott orvosok kiolvasása
        $query = "SELECT 
        IF(potig<>'' and TIME('{$ora}')>=TIME(ig),1,0) as ispotig, 
        b.*,o.id as orvosid,o.nev as orvosnev,o.onlytel 
        FROM orvos_beosztas_new b 
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		WHERE b.`helyszinid`='{$helyszin}' {$wceg} AND (nap=WEEKDAY('{$nap}')+1 or beonap='{$nap}') {$wora} AND INSTR(b.tipusok,'|{$szuresTipus}|')
        AND (b.validfrom='0000-00-00' OR b.validfrom<='{$nap}') AND (b.validto='0000-00-00' OR b.validto >='{$nap}')
		AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2) OR (WEEK('{$nap}',3)%2=1 AND b.hetek=1)) and b.aktiv=1 and o.aktiv=1 {$wnoreservation}
        ORDER BY o.nev, o.onlytel, b.tol";

        $beosztasok = sql_query($query)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($beosztasok as $beosztas) {
            if ($beosztas["orvosid"] == $orvos || $orvos == 0) {
            //if (($beosztas["orvosid"] == $orvos) || $orvos == 0) {
                $beos[] = $beosztas;
            }
        }
        if (!isset($beos)) {
            return false;
        }
        return $beos;
    }

    public function tipusExtract($resource) {
        $tipusok = [];
        while ($row = sql_fetch_array($resource)) {
            $ta = explode("|", $row["tipusok"]);
            for ($i = 0; $i < count($ta); $i++) {
                if (trim($ta[$i]) != "" && !in_array($ta[$i], $tipusok)) {
                    $tipusok[] = $ta[$i];
                }
            }
        }
        return $tipusok;
    }

    public function getAllReservationForDayByDoctor($day, $helyszinId=0):array {
        $tol        = "{$day} 00:00:00";
        $ig         = "{$day} 23:59:59";
        $return     = [];
        $wCeg       = $this->adminUser->cegSQLFilter("f.cegid");

        $resf = sql_query("select f.*,c.megnev as cegnev,o.nev as orvosnev,d.id as docid from foglalasok f 
                        left join cegek c on c.id=f.cegid
                        left join orvosok o on o.id=f.orvosassigned
                        left join szurestipusok sz on sz.id=f.szurestipusid
                        left join dokumentumok d on d.foglalasid=f.id
                        where f.datum>=? and f.datum<=? and (f.helyszinid=? or sz.webdoktor=1) {$wCeg}", [$tol, $ig, $helyszinId]);
        while ($reservationData = sql_fetch_array($resf)) {
            $return[$reservationData["orvosassigned"]][$reservationData["id"]] = $reservationData;
        }
        return $return;
    }

    public function getTipusMegj($cegid, $tid, $helyszinId = Booking_Constants::DEFAULT_PLACE_IDS[0], $radioButton = false, $selectedSzolg = 0):string {
        //$radioButton = false;
        //$selectedSzolg = 0;

        if(Booking_Constants::SQL_DB == "hungariamed"){
            if($cegid==43){
                $radioButton = true;
            }
        }

        $this->lang = new Lang();
        $webText = $this->lang->webText;

        $h = "";
        if ($row = sql_fetch_array(sql_query("select * from szurestipusok_megj where (cegid=? or cegid=0) and tipusid=? and csomag=0 and (helyszinid=? or helyszinid=0)", [$cegid, $tid,$helyszinId]))) {
            if (!empty(trim($row["megj"]))) {
                $h .= "<div class='tipusmegj'>" . trim($row["megj"]) . "</div>";
            }
        }

        $res = sql_query("SELECT o.* FROM orvos_beosztas_new b 
        LEFT JOIN orvosok o ON o.id=b.`orvosid`
        WHERE instr(cegid, ?) AND INSTR(b.`tipusok`,'|" . intval($tid) . "|') AND o.`tel`<>'' and o.telpublic=1 and b.helyszinid=?
        GROUP BY b.`orvosid`", ["|{$cegid}|", $helyszinId]);

        if (sql_num_rows($res) > 0) {
            $h .= "<div style='margin:10px 0px;'>";
            $h .= "<div style='font-weight:bold;'>Elérhetőségek:</div>";
            while ($row = sql_fetch_array($res)) {
                $h .= "<div>Telefonos időpontfoglalás: {$row["tel"]}</div>";
            }
            $h .= "</div>";
        }
        
        if ($helyszinId == Booking_Constants::DEFAULT_PLACE_IDS[0] || $helyszinId == 100 || $helyszinId == 328 || $helyszinId == 644 || $helyszinId == 162) {
            $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and trim(megnev)<>'' and csomag=0 and paciens=1 and aktiv=1", array("|{$cegid}|", $tid));
            /*if($tid==116){
                $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and trim(megnev)<>'' and csomag=0", array("|{$cegid}|", $tid));
            }*/
            if (sql_num_rows($res) > 0) {
                $chooseText = $webText["valasszonszolgaltatast"];
                $hidden = "";
                $priceDisplay = false;
                $tileMode = false;

                if (CompanyService::isBME()) {
                    $priceDisplay = true;
                    $tileMode = session_id() == "gtefskm5mqb91q928dabh4lqg9";
                    $chooseText = "Igénye esetén, válasszon a Fehérvári úti rendelőnkben elérhető alábbi téritéses szolgáltatások közül. BME dolgozókank 20% kedvezmény.";
                    //$hidden = "display:none;";
                    //$h.= "<div style='margin-bottom:10px;'><a class='bmebutton' href='#' onclick='$(\"#kiegdiv\").slideToggle();return false;' target='_blank'>Kattintson ide, és válasszon térítéses kiegészítő vizsgálatot!</a></div>";
                }
                if($tid==116){
                    $priceDisplay = true;
                }

                $h .= "<div id='kiegdiv' style='margin:10px 0px;{$hidden}'>";
                $h .= "<div style='font-weight:bold;margin-bottom:5px;'>{$chooseText}:</div>";
                while ($row = sql_fetch_array($res)) {
                    $price = $akcioPrice = $row["price"];
                    if ($helyszinId == 644 && $row["megnev"] == "Tüdőszűrés") {
                        continue;
                    }

                    if (CompanyService::isBME()) {
                        $akcioPrice = $price * 0.8;
                    }


                    //$lengthText = empty($row["plusminute"]) ? "" : " ({$row["plusminute"]} perc)";
                    $lengthText = "";
                    $priceText = $priceTextBox = "";
                    if ($priceDisplay) {
                        if (substr_count($row["megnev"], "Labor")) {
                            if ($akcioPrice == $price) {
                                $priceText = " (" . number_format($row["price"], 0, " ", "&nbsp;") . " Ft-tól)";
                            } else {
                                $priceText = " (<span style='text-decoration: line-through'>" . number_format($price, 0, " ", "&nbsp;") . "</span> " . number_format($akcioPrice, 0, " ", "&nbsp;") . " Ft-tól)";
                            }
                            $priceTextBox = number_format($row["price"], 0, " ", "&nbsp;") . " Ft-tól";
                        } else {
                            if ($akcioPrice == $price) {
                                $priceText = " (" . number_format($price, 0, " ", "&nbsp;") . " Ft)";
                            } else {
                                $priceText = " (<span style='text-decoration: line-through'>" . number_format($price, 0, " ", "&nbsp;") . "</span> " . number_format($akcioPrice, 0, " ", "&nbsp;") . " Ft)";
                            }
                            $priceTextBox = number_format($row["price"], 0, " ", "&nbsp;") . " Ft";
                        }
                    }
                    //if ($_COOKIE["lang"]!="hu" && trim($row["megnev_{$_COOKIE["lang"]}"])!="") $row["megnev"]=$row["megnev_{$_COOKIE["lang"]}"];
                    if ($tileMode) {

                        $bmeBackgroundMap = [
                            "ABI érrendszer vizsgálat" => "abi.png",
                            "Dietetika" => "dietetika.png",
                            "12 elvezetéses nyugalmi EKG, vérnyomás-, pulzus mérés" => "ekg.png",
                            "Laborvizsgálat" => "labor.png",
                            "Vércukor - koleszterin" => "lipidpanel.png",
                            "Tüdőszűrés" => "tudoszures.png",
                            "Szívstressz-Vicardio vizsgálat" => "vicardio.png",
                        ];

                        $h.= "<div class='bmeservicebox' style='background:url(/images/bme/{$bmeBackgroundMap[$row["megnev"]]});background-size:cover;'>";
                        $h.= "<input type='checkbox' class='altipuscheck' style='display:none;' name='altipus{$row["id"]}' value='1' " . (isset($_POST["altipus{$row["id"]}"]) ? "checked" : "") . " />";
                        $h.= "<div style='height:125px;overflow: hidden;'><div style='margin-top:10px;background:rgba(0, 0, 0, .5);color:white;padding:10px;font-weight: bold;text-transform: uppercase;'>{$row["megnev"]}</div></div>";
                        $h.= "<div style='font-size:15px;background:rgba(255, 255, 255, .7);color:#b00;padding:10px;'><strong>{$priceTextBox}</strong></div>";
                        $h.= "</div>";
                    } else {
                        if(!$radioButton){
                            $h .= "<div><input type='checkbox' class='altipuscheck' name='altipus{$row["id"]}' id='altipus{$row["id"]}' value='1' " . (isset($_POST["altipus{$row["id"]}"]) ? "checked" : "") . " /> <label for='altipus{$row["id"]}'>{$row["megnev"]}{$lengthText}{$priceText}</label></div>";
                        }else{
                            $h .= "<div><input type='radio' ".($tid==116?"onChange='changeWebSzolg({$row["id"]})'":"")." class='altipuscheck' name='altipusradiobutton' ".($selectedSzolg && $selectedSzolg==$row["id"]?"checked='true'":"")." id='altipusradiobutton{$row["id"]}' value='{$row["id"]}' " . (isset($_POST["altipusradiobutton"]) && $_POST["altipusradiobutton"]==$row["id"] ? "checked" : "") . " /> <label for='altipusradiobutton{$row["id"]}'>{$row["megnev"]}{$lengthText}{$priceText}</label></div>";
                        }
                        
                    }
                }
                if (CompanyService::isBME()) {
                    $h.= "<div style='margin-top:10px;'><a href='https://www.keltexmed.hu/site/images/arlista_keltexmed.pdf?v202402' target='_blank'>Teljes KeltexMed árlista</a>";
                    $h.= "<div class='bmecolumn2'><div style='font-weight:bold;margin-bottom:5px;'>További szakvizsgálatok</div>A 1135 Budapest, Jász u. 33-35. szám alatt található Egészségügyi Központunkban tudják igénybe venni.<br/>Időpontfoglaláshoz kettintson  ide: <a style='text-decoration:underline;' href='https://bejelentkezes.hungariamed.hu' target='_blank'>bejelentkező&nbsp;rendszer</a>.<br/>Figyelem, ezen a helyszínen nincs lehetőség üzemorvosi vizsgálat elvégzésére.</div>";
                }
                $h .= "</div>";
            }
        }

        $radiologyCheckBoxNeeded = false;
        if (Booking_Constants::SQL_DB == "hungariamed") {
            if ($_SESSION["helyszindata"]["tudoszuroopcio"] == 1 && $tid == 1 && in_array($helyszinId, [1, 100])) {
                $radiologyCheckBoxNeeded = true;
            }
        }

        if ($radiologyCheckBoxNeeded) {
            $h .= "<div><input type='hidden' name = 'tudoszuroanswerneeded' value = '1' /><span style='font-weight: bold;'>Rendelkezik 1 éven belüli érvényes tüdőszűrő lelettel?</span><br/>";
            $h .= "<input type='radio' name = 'tudoszuro' value = '1' ".(isset($_POST["tudoszuro"]) && $_POST["tudoszuro"] == 1?"checked":"")."/>Nem rendelkezem, kérek tüdőszűrő vizsgálatot, azonnali lelet kiadással<br/>";
            $h .= "<input type='radio' name = 'tudoszuro' value = '0' ".(isset($_POST["tudoszuro"]) && $_POST["tudoszuro"] == 0?"checked":"")."/>Igen rendelkezem érvényes tüdőszűrő lelettel<br/>";
            //$h .= "<input type='radio' name = 'tudoszuro' value = '0' ".(isset($_POST["tudoszuro"]) && $_POST["tudoszuro"] == 0?"checked":"")."/>A munkakörömhöz nincs szükségem tüdőszűrésre<br/>";
            $h .= "</div>";
        }

        $noRadiologyWarningCheckBox = false;
        if (Booking_Constants::SQL_DB == "hungariamed") {
            if ($_SESSION["helyszindata"]["tudoszuroopcio"] == 1 && $tid == 1 && in_array($helyszinId, [644])) {
                $noRadiologyWarningCheckBox = true;
            }
        }

        if ($noRadiologyWarningCheckBox) {
            $h .= "<div><span style='font-weight: bold;'>Ha tüdőszűrő vizsgálatra is szüksége van és Budapesten kíván részt venni a vizsgálatokon, kérjük, az alábbi rendelőink közül válasszon:</span><br/>";
            $h .="<ul style=\"margin-left:10px\">";
            $h .="  <li style=\"list-style: disc;\">1135 Budapest, Jász u. 33-35</li>";
            $h .="  <li style=\"list-style: disc;\">1117 Budapest, Fehérvári út 44.</li>";
            $h .="</ul>";
            $h .= "</div>";
        }

        if ($_SESSION['helyszindata']['laboropcio'] == 1 && $tid == 1 && in_array($helyszinId, [1])) {
            $h .= "<div><input type='hidden' id='laboranswerneeded' name = 'laboranswerneeded' value = '1' /><span style='font-weight: bold;'>Szükségem van BEM vizsgálatra is.</span><br/>";
            $h .= "<input type='radio' name = 'labor' value = '1' onchange='reservedTimeInvalidate();' ".(isset($_POST["labor"]) && $_POST["labor"] == 1?"checked":"")."/>Igen<br/>";
            $h .= "<input type='radio' name = 'labor' value = '0' onchange='reservedTimeInvalidate();' ".(isset($_POST["labor"]) && $_POST["labor"] == 0?"checked":"")."/>Nem<br/>";
            $h .= "</div>";
        }
        return $h;
    }

    public function checkIdopontSzabad($data)
    {
        //$_POST["datum"]
        //$_POST["helyszin"]
        //$_POST["szurestipus"]
        //Teszt:
        //return $this->selectOrvosForIdopont($data["datum"], $data["orvosselected"]);

        if ($this->selectOrvosForIdopont($data["datum"], $data["orvosselected"])) {
            return true;
        }
        return false;
    }

    public function plusMinuteDrotozasok($plusMinute, $priceId, $orvosId) {
        if (Booking_Constants::SQL_DB == "hungariamed") {
            //dr fontosnál nem kell semmi plusz idő
            if ($orvosId == 335) {
                $plusMinute = 0;
            }
            //dr kósa duplázás
            //if ($priceId == 120 && $orvosId == 971) {
            //    $plusMinute = 60;
            //}
        }
        return $plusMinute;
    }


    public function checkIdopontSzabadForServices($data):array {
        $result = [];

        $plusMinute = 0;
        $serviceName = "";
        $serviceNum = 0;
        $prices = sql_query("select * from arak where instr(cegid,?) and tipusid=? and csomag=0", ["|{$_SESSION["helyszindata"]["id"]}|", $data["szurestipus"]]);
        foreach ($prices as $price) {
            if (isset($data["altipus{$price["id"]}"])) {
                $serviceNum++;
                $price["plusminute"] = $this->plusMinuteDrotozasok($price["plusminute"], $price["id"], $data["orvosselected"]);
                if ($price["plusminute"] > $plusMinute) {
                    $plusMinute = $price["plusminute"];
                    $serviceName = $price["megnev"];
                }
            }
        }

        //Dr. Danielisz Zsuszannánál több szolgáltatás választása esetén hosszabb idő foglalás
        if ($serviceNum > 1 && $plusMinute == 0 && $data["orvosselected"] == 1289 && Booking_Constants::SQL_DB == "hungariamed") {
            $serviceName = "több";
            $plusMinute = $data["rinterval"] * 2;
        }

        if ($plusMinute > 0 && !empty($data["datum"]) && !empty($data["rinterval"]) && !empty($data["orvosselected"])) {
            $nap = date("Y-m-d", strtotime($data["datum"]));
            $allInterval = $plusMinute > $data["rinterval"] ? $plusMinute : $data["rinterval"];
            if (sql_fetch_array(sql_query("SELECT id, datum FROM foglalasok WHERE datum>=?
                   AND ((datum<=? AND datum>DATE_SUB(?, INTERVAL IF(rinterval=0, 5, rinterval) MINUTE)) OR (datum>=? AND datum<DATE_ADD(?, INTERVAL ? MINUTE)))
                   AND orvosassigned=?", ["{$nap} 00:00:00", $data["datum"], $data["datum"], $data["datum"], $data["datum"], $allInterval, $data["orvosselected"]]))) {
                $result["error"] = "Ha {$serviceName} szolgáltatásunkat választja, olyan időpontot válasszon ahol egyben szabad {$allInterval} perc";
            }

            //utolsó időpont check
            if (empty($result["error"])) {
                if ($doctorBeo = $this->beosztasService->getBeosztasDataForDoctor($data["orvosselected"], $nap, $data["helyszin"], $data["szurestipus"])) {
                    $end = date("H:i", strtotime("{$data["datum"]} + {$allInterval} minute"));
                    if (strtotime($end) > strtotime($doctorBeo["ig"])) {
                        $result["error"] = "Ha {$serviceName} szolgáltatásunkat választja, olyan időpontot válasszon ahol egyben szabad {$allInterval} perc";
                    }
                }
            }
        }
        return $result;
    }


    public function updateFoglalasData($id)
    {
        $rInterval = 0;
        if (isset($_REQUEST["rinterval"])) {
            $rInterval = intval($_REQUEST["rinterval"]);
        }

        sql_query("UPDATE foglalasok SET pass=SHA1(CONCAT(id,regdatum,datum)) where id=? and pass=''", array($id));
        sql_query("UPDATE foglalasok SET rinterval=? where id=? and rinterval=0", array($rInterval, $id));

        sql_query("UPDATE foglalasok fogl
				   LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
				   SET fogl.aktiv=1  
				   WHERE fogl.id=? AND sz.webdoktor=1", array($id));

        if (isset($_SESSION["filefix"])) {
            sql_query("update dokumentumok set foglalasid=?,sess='validated' where sess=?", array($id, $_SESSION["filefix"] . session_id()));
        }
    }

    public function availableDoctorsForTime($nap, $ora, $beosztas)
    {
        foreach ($beosztas as &$beo) {
            if (strtotime(date("Y-m-d {$ora}")) >= strtotime(date("Y-m-d {$beo["tol"]}")) && strtotime(date("Y-m-d {$ora}")) < strtotime(date("Y-m-d {$beo["ig"]}"))) {
                if (!isset($doks) || !in_array($beo["orvosid"], $doks)) {
                    $szabinVan = false;
                    if (isset($GLOBALS["szabidata"][$beo["orvosid"]])) {
                        foreach ($GLOBALS["szabidata"][$beo["orvosid"]] as $orvosSzabi) {
                            if (strtotime(date("{$nap} {$ora}")) >= strtotime(date("{$orvosSzabi["datumtol"]} 00:00:00")) && strtotime(date("{$nap} {$ora}")) <= strtotime(date("{$orvosSzabi["datumig"]} 23:59:59"))) {
                                $szabinVan = true;
                            }
                        }
                    }
                    if (!$szabinVan) {
                        $doks[] = $beo["orvosid"];
                    }
                }
            }
        }

        if (!isset($doks)) {
            return false;
        }
        return $doks;
    }

    public function deleteReservation($id, $code, $force = false) {
        if ($this->adminUser->korlatlanFoglalasTorles()) {
            $force = true;
        }

        if ($force) {
            $res = sql_query("select * from foglalasok WHERE id=? and (pass=? or rkod=?)", array($id, $code, $code));
        } else {
            $res = sql_query("select * from foglalasok WHERE id=? and (pass=? or rkod=?) and (datum>now() or aktiv=0) and eljott=0", array($id, $code, $code));
        }
        if ($row = sql_fetch_array($res)) {
            $foService = new FoglaljOrvostService();
            $foService->deleteReservation($row["id"]);

            $api = new BookingSyncApi();
            $api->deleteReservation($row);

            $notificationService = new NotificationService();
            $notificationService->deleteDoctorMessage($row["id"]);

            $logSubject = "{$row["nev"]} foglalás törlése {$row["datum"]}";
            logActivity("foglalastorles", $row["id"], $logSubject, json_encode($row, JSON_PRETTY_PRINT));

            sql_query("update beutalok set foglalasid='0' where foglalasid=?", array($row["id"]));
            sql_query("delete from foglalasok WHERE id=?", array($row["id"]));
            sql_query("delete from foglalasok WHERE parentid=? and parentid<>0 and datum>date_sub(now(), interval 1 month)", array($row["id"]));
            sql_query("delete from fizkapcs where fid=?", array($row["id"]));
        }
    }

    public function addReservation($data):string {
        $cegId = $_SESSION["helyszindata"]["id"];

        if (!isset($data["tudoszuro"])) {
            $data["tudoszuro"] = 0;
        }

        $rn = rand(1000000, 9999999);

        $paciensId = 0;
        if (!empty($data["taj"])) {
            if (isset($_SESSION["user"]["id"])) {
                $paciensId = intval($_SESSION["user"]["id"]);
            } else {
                if ($userInfo = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE taj = ? AND email = ? AND cegid=?", array($data['taj'], $data['email'], $cegId)))) {
                    $paciensId = $userInfo['id'];
                } else {
                    sql_query("INSERT INTO felhasznalok SET validated=1, cegid=?, regtime=now(), taj = ?, email = ?, nev = ?, telefon = ?, munkakor = ?, irsz = ?, varos = ?, utca = ?, szulhely = ?, anyjaneve = ?, szuldatum = ? ", array($cegId, $data['taj'], $data['email'], $data['nev'], $data['telefon'], $data['munkakor'], $data['irsz'], $data['varos'], $data['utca'], $data['szulhely'], $data['anyjaneve'], $data['szuldatum']));
                    $paciensId = sql_insert_id();
                }
            }
        }

        $lang = "hu";
        if (isset($_COOKIE["lang"]) && !empty($_COOKIE["lang"])) {
            $lang = $_COOKIE["lang"];
        }

        $data["paciensid"] = $paciensId;
        $data["rn"]        = $rn;
        $data["aktiv"]     = 0;
        $data["parentid"]  = 0;
        $data["cegid"]     = $cegId;
        $data["lang"]      = $lang;
        if (!isset($data['orvosid'])) $data["orvosid"] = 0;

        $fid = $this->addReservationQuery($data);
        $this->replicateKiegeszitoVizsgalatok($fid); //kiegészítő vizsgálatok, pl tüdőszűrő hozzáadása
        $this->doAuchanExceptions($fid);
        $this->doOIFExceptions($fid);
        $this->doBudapestBrandExceptions($fid);
        $this->doKREExceptions($fid);
        $this->doEONExceptions($fid);
        $this->doCargoExceptions($fid);
        $this->doDRVExceptions($fid);

        $this->newReservationId=$fid;

        //labshop esetén visszatároljuk a foglalás id-t és leteszünk egy laborkérő doksit
        if (isset($_SESSION["labcode"])) {
            if ($labData = sql_fetch_array(sql_query("select * from labshop_vasarlasok where hash=?", [$_SESSION["labcode"]]))) {
                sql_query("update labshop_vasarlasok set status='done', reservationid=? where hash=? limit 1", [$this->newReservationId, $_SESSION["labcode"]]);

                //$synlabService = new SynlabService();
                //$docAgent = new DocAgent();
                //$path = $synlabService->create_labshop_laborkero($labData["id"]);
                //$docAgent->saveLocalDoc($path, ["fid" => $this->newReservationId]);
            }
        }

        //Ha BP-s dolgozóról van szó, lerakok neki egy beutalót mindenképp mint hozott fájl
        if($data["cegid"]==74){
            
            $referalType="bp-normal";
            //El kell döntenem, hogy a dolgozó milyen beutalót kell kapjon, ehhez lesz egy segéd tábla
            $refQuery = sql_query("SELECT fogl.id AS fid,fogl.nev,fogl.szuldatum,fogl.taj,fogl.regdatum,fogl.munkakor,sz.megnev,fogl.pass AS vizsgalat,helpdesk.type,helpdesk.worklocation,helpdesk.ntid,fogl.torzsszam FROM foglalasok fogl
                                   LEFT JOIN bp_beutalo_seged_tabla helpdesk ON helpdesk.ntid=fogl.torzsszam
                                   LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
                                   WHERE fogl.id=?",array($fid));
            if($referalData=sql_fetch_array($refQuery)){
                //Ha éjszakairól van szó átállítom éjszakaira a típust
                if($referalData["type"]=="délutáni"){
                    $referalType="bp-nightshift";
                }
                //Ha üres az ntid, azaz, nemtaláltam a listában egyezést, akkor automatikusan újbelépő
                if(empty($referalData["ntid"])){
                    $referalData["vizsgalat"]="Előzetes- Foglalkozás Egészségügyi vizsgálat";
                }
                echo $this->createReferalDoc($referalData,$referalType);
            }

            //Psychosoc sor generálása
            //$foglalasinfo = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?",array($fid)));
            //sql_query("INSERT INTO psychosoc_eredmenyek SET foglid=?,cegid=?,pass=?",array($fid,$data["cegid"],$foglalasinfo["pass"]));
        }

        if($data["cegid"]==43){
            $refQuery = sql_query(
                "SELECT fogl.id AS fid,fogl.cegid,fogl.nev,fogl.szuldatum,fogl.taj,CONCAT(fogl.irsz,' ',fogl.varos,', ',fogl.utca) AS teljescim,
                 fogl.regdatum,fogl.munkakor,sz.megnev AS vizsgalat,null as worklocation,felh.beutalo_megjegyzes,felh.szervezet_megnev,
                 felh.khkod,felh.torzsszam,fogl.szulhely,fogl.anyjaneve,'{$data["reszleg"]}' as worklocation 
                 FROM foglalasok fogl
                 LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
                 LEFT JOIN felhasznalok felh on felh.taj=fogl.taj
                 WHERE fogl.id=?",
                 array($fid));

            $altipus=sql_query("SELECT * FROM arak WHERE id=?",[$data["altipusradiobutton"]])->fetch(PDO::FETCH_ASSOC);
            

            if($referalData=sql_fetch_array($refQuery)){
                $referalData["vizsgalat"] = $altipus["megnev"];
                echo $this->createReferalDoc($referalData,"apollo-beutalo");
            }
        }


        if($data["cegid"]==220){
            $refQuery = sql_query("SELECT fogl.id AS fid,fogl.cegid,fogl.nev,fogl.szuldatum,fogl.taj,CONCAT(fogl.irsz,' ',fogl.varos,', ',fogl.utca) AS teljescim,fogl.regdatum,fogl.munkakor,sz.megnev AS vizsgalat,null as worklocation,felh.beutalo_megjegyzes,felh.szervezet_megnev,felh.khkod,felh.torzsszam FROM foglalasok fogl
            LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
            LEFT JOIN felhasznalok felh on felh.taj=fogl.taj
            WHERE fogl.id=?",array($fid));
            if($referalData=sql_fetch_array($refQuery)){
                echo $this->createReferalDoc($referalData,"fgsz-beutalo");
            }
        }

        if (!isset($data["noreservation"])) {
            $data["noreservation"] = 0;
        }

        if ($data['noreservation'] != 1) {
            $oid = $this->selectFreeOrvosForIdopont($fid);
            sql_query("update foglalasok set orvosassigned=? where id=? and orvosassigned=0", array($oid, $fid));
        }

        if (isset($_SESSION["beutaloid"]) && isset($_SESSION["user"]) && $rowb = sql_fetch_array(sql_query("select * from beutalok where id=?", array($_SESSION["beutaloid"])))) {
            sql_query("update beutalok set foglalasid=? where id=?", array($fid, $_SESSION["beutaloid"]));
            sql_query("update foglalasok set megj=? where id=?", array($rowb["megj"], $fid));
            unset($_SESSION["beutaloid"]);
        }

        //altipusok tárolása
        $rinterval = $data["rinterval"];
        $serviceNum = 0;
        $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and csomag=0", array("|{$_SESSION["helyszindata"]["id"]}|", $data["szurestipus"]));
        while ($row = sql_fetch_array($res)) {
            if (isset($data["altipus{$row["id"]}"])) {
                $serviceNum++;
                $row["plusminute"] = $this->plusMinuteDrotozasok($row["plusminute"], $row["id"], $data["orvosid"]);
                if ($row["plusminute"] > $rinterval) {
                    $rinterval = $row["plusminute"];
                    sql_query("update foglalasok set rinterval=? where id=? limit 1", [$rinterval, $fid]);
                }
                sql_query("insert into fizkapcs set fid=?,aid=?,megnev=?,ar=?,valuta=?", array($fid, $row["id"], $row["megnev"], $row["price"], $row["penznem"]));
            }
        }

        if(isset($data["altipusradiobutton"])){
            if($altipus=sql_query("SELECT * FROM arak WHERE id=?",[$data["altipusradiobutton"]])->fetch(PDO::FETCH_ASSOC)){
                sql_query("insert into fizkapcs set fid=?,aid=?,megnev=?,ar=?,valuta=?", 
                    array($fid, $altipus["id"], $altipus["megnev"], $altipus["price"], $altipus["penznem"])
                );
            }
        }



        //Dr. Danielisz Zsuszannánál több szolgáltatás választása esetén hosszabb idő foglalás
        if ($serviceNum > 1 && $data["orvosid"] == 1289 && Booking_Constants::SQL_DB == "hungariamed") {
            $rinterval = $data["rinterval"] * 2;
            sql_query("update foglalasok set rinterval=? where id=? limit 1", [$rinterval, $fid]);
        }

        //Menedzser csomag időpont foglalások
        $this->addSubReservation($data, $fid);

        if (isset($_SESSION["remotebeutalo"]) || $_SESSION["helyszindata"]["visszaigazolas"] == 0 || $this->isOnlineTipus($data["szurestipus"])) {
            //ha fizetős, vagy orvos jött, akkor nem kérünk visszaigazolást, megyünk visszaigazolni automatikusan
            $forwardURL = "index.php?page=bookingvalidate&id={$fid}&rk={$rn}";
        } else {
            //visszaigazolást kérünk
            $this->notificationService->sendUserVisszaIgazolas($fid);
            $forwardURL = "index.php?page=bookingsuccessful";
        }

        if(CompanyService::isBP() && true){
            //Itt kell létrehozzam a pszihosoc kérdőív adatsort a foglalási adatok alapján és legenerálnom a forwardurl-t.
            $fogl= sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?",array($fid)));
            sql_query("INSERT INTO psychosoc_eredmenyek SET foglid=?,cegid=?,pass=?",array($fid,$fogl["cegid"],$fogl["pass"]));

            $forwardURL = "https://{$_SERVER["HTTP_HOST"]}/?page=psychosocialform&fid={$fogl["id"]}&pass={$fogl["pass"]}";
        }

        //Foglaljorvost.hu-nak átküldés
        $foService = new FoglaljOrvostService();
        $foService->newReservation($fid);

        $api = new BookingSyncApi();
        $api->newReservation($fid);
        return $forwardURL;
    }

    private array $parentReservationData = [];

    public function addSubReservation($data, $parentId)
    {
        if ($this->szuresTipusData["ispack"] == 1) {

            $map = $this->getPackageAvailabilityForDayV2(date("Y-m-d", strtotime($data["datum"])), false, $data, CompanyService::isSuzukiGHC() ? date("H:i", strtotime($data["datum"])) : "");


            $this->parentReservationData = sql_fetch_array(sql_query("select * from foglalasok where id=?", array($parentId)));
            $tipusData = sql_query("select megnev from szurestipusok t where t.id=?", [$this->parentReservationData["szurestipusid"]])->fetch(PDO::FETCH_ASSOC);
            $data["megj"] = $data["megj"] == "" ? "{$tipusData["megnev"]}":"{$tipusData["megnev"]} - {$data["megj"]}";
        
            $originMegj = $data["megj"];
            foreach ($map["timeTableForPackage"] as $subTypeId => $subData) {
                if ($this->parentReservationData["szurestipusid"] == $subTypeId) {

                    //a parent tipus időpontját pontosítjuk
                    //sql_query("update foglalasok set datum=?, orvosassigned=? where id=?", array($subData["idopont"], $subData["orvosid"], $parentId));
                    sql_query("update foglalasok set orvosassigned=? where id=?", [$subData["orvosid"], $parentId]);
                    continue;
                }

                $servicesToMegj = "";
                //Megvizsgálom, hogy van-e a szűréstípushoz tartozó egyéb szolgáltatás
                $reskapcs = sql_fetch_array(sql_query("SELECT * FROM szurescsomagok_kapcs WHERE csomagid=? AND szurestipusid=?",[$this->parentReservationData["szurestipusid"],$subTypeId]));
                if(!empty($reskapcs["otherservices"])){
                    //Ha van, akkor dekódolom a JSON objektumot és végig fuok rajta egy loopban.
                    $otherservices = json_decode($reskapcs["otherservices"],true);
                    foreach($otherservices as $index => $service){
                        //Ha találok egyezést szűréstípusid és index érték alapján, akkor hozzáadom az adatbázisban található szöveg értéket a megjegyzéshez.
                        if(isset($data["otherservices-{$subTypeId}-{$index}"]) && $subTypeId==$reskapcs["szurestipusid"]){
                            $servicesToMegj = " + {$service}";
                        }
                    }
                }

                $data["datum"] = $subData["idopont"];
                $data["paciensid"] = $this->parentReservationData["paciensid"];
                $data["rn"] = rand(1000000, 9999999);
                $data["aktiv"] = $this->parentReservationData["aktiv"];
                $data["parentid"] = $parentId;
                $data["cegid"] = $this->parentReservationData["cegid"];
                $data["lang"] = $this->parentReservationData["rlang"];
                $data["orvosid"] = $subData["orvosid"];
                $data["szurestipus"] = $subTypeId;
                $data["rinterval"] = $subData["interval"];
                $data["megj"] = $originMegj.$servicesToMegj;

                $this->addReservationQuery($data);
            }
        }
    }

    public function getDokirexCompanyID($companyId,$foglalasData):int {
        $dokirexCompanyId = 0;
        if ($companyId != 0) {
            $resq=sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?",[$companyId]));
            $dokirexcegids = json_decode($resq["dokirexcegid_json"],true);

            //Csak akkor küldjön vissza bármit is, hogyha nem üres
            if(!empty($dokirexcegids)){
                $dokirexCompanyId = $dokirexcegids[0];
            }

            //Ha van telephelyid akkor nézze meg a cegvars-t és onnan keresse elő a rendszer a rögzített dokirexcegid-t
            if(isset($foglalasData["telephelyid"]) && !empty($foglalasData["telephelyid"])){
                $rest = sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE id=?",[$foglalasData["telephelyid"]]));
                $dokirexCompanyId = intval($rest["dokirexcegid"]);
            }
        }
        return $dokirexCompanyId;
    }

    public function addReservationQuery($data) {
        if (!isset($data["szulhely"])) $data["szulhely"] = "";
        if (!isset($data["telephelyid"])) $data["telephelyid"] = "";
        if (!isset($data["telephely"])) $data["telephely"] = "";
        if (!isset($data["anyjaneve"])) $data["anyjaneve"] = "";
        if (!isset($data["torzsszam"])) $data["torzsszam"] = "";
        if (!isset($data["taj"])) $data["taj"] = "";
        if (!isset($data["rinterval"])) $data["rinterval"] = 0;
        if (!isset($data["helyszin"])) $data["helyszin"] = 0;
        if (!isset($data["irsz"])) $data["irsz"] = "";
        if (!isset($data["varos"])) $data["varos"] = "";
        if (!isset($data["utca"])) $data["utca"] = "";
        if (!isset($data["megj"])) $data["megj"] = "";
        if (!isset($data["munkakor"])) $data["munkakor"] = "";
        if (!isset($data["adoszam"])) $data["adoszam"] = "";
        if (!isset($data["betegallomanynyilatkozat"])) $data["betegallomanynyilatkozat"] = 0;
        if (!isset($data["parentid"])) $data["parentid"] = 0;
        if (!isset($data["externalid"])) $data["externalid"] = "";
        if (!isset($data["pass"])) $data["pass"] = "";
        if (!isset($data["foglalta"])) $data["foglalta"] = "";

        if (!isset($data["questions"])) $data["questions"] = "";
        if (!isset($data["simplepay"])) $data["simplepay"] = 0;
        if (!isset($data["noreservation"])) $data["noreservation"] = 0;
        if (!isset($data["totalprice"])) $data["totalprice"] = 0;
        if (!isset($data["exportdata"])) $data["exportdata"] = "";
        if (!isset($data["currency"])) $data["currency"] = 0;
        if (!isset($data["lang"])) $data["lang"] = "hu";
        if (!isset($data["paid"])) $data["paid"] = 0;
        if (!isset($data["expire"])) $data["expire"] = "0000-00-00 00:00:00";
        if (!isset($data["rn"])) $data["rn"] = rand(1000000, 9999999);
        if (!isset($data["cegid"])) $data["cegid"] = 0;
        if (!isset($data["dokirexcegid"])) $data["dokirexcegid"] = $this->getDokirexCompanyID($data["cegid"],$data);
        if (!isset($data["jarat"])) $data["jarat"] = "";
        if (!isset($data["companytext"])) $data["companytext"] = "";
        if (!isset($data["tudoszuro"])) $data["tudoszuro"] = 0;
        if ($data["tudoszuro"] < 0) $data["tudoszuro"] = 0;

        if (!empty($_SESSION["selectedJarat"])) {
            $data["jarat"] = $_SESSION["selectedJarat"];
        }

        sql_query(
            "insert into foglalasok set 
            regdatum=now(),
            parentid=?,
            paciensid=?,
            cegid=?,
            datum=?,
            rinterval=?,
            telephelyid=?,
            telephely=?,
            helyszinid=?,
            szurestipusid=?,
            nev=?,
            email=?,
            telefon=?,
            szuldatum=?,
            szulhely=?,
            anyjaneve=?,
            neme=?,
            taj=?,
            torzsszam=?,
            irsz=?,
            varos=?,
            utca=?,
            megj=?,
            munkakor=?,
            adoszam=?,
            tudoszuro=?,
            rlang=?,
            orvosassigned=?,
            aktiv=?,
            rkod=?,
			tappenzcheck=?,
			simplepay=?,
			noreservation=?,
			questions=?,
			totalprice=?,
			currency=?,
			foglalta=?,
			exportdata=?,
			externalid=?,
			paid=?,
			expire=?,
			pass=?,
            jarat=?,
            companytext=?,
            dokirexcegid=?",
            array(
                $data["parentid"],
                $data["paciensid"],
                $data["cegid"],
                $data["datum"],
                intval($data["rinterval"]),
                $data["telephelyid"],
                $data["telephely"],
                $data["helyszin"],
                $data["szurestipus"],
                $data["nev"],
                $data["email"],
                $data["telefon"],
                $data["szuldatum"],
                $data["szulhely"],
                $data["anyjaneve"],
                $data["neme"],
                $data["taj"],
                $data["torzsszam"],
                $data["irsz"],
                $data["varos"],
                $data["utca"],
                $data["megj"],
                $data["munkakor"],
                $data["adoszam"],
                $data["tudoszuro"],
                $data["lang"],
                $data["orvosid"],
                $data["aktiv"],
                $data["rn"],
                $data["betegallomanynyilatkozat"],
                $data["simplepay"],
                $data["noreservation"],
                $data["questions"],
                $data["totalprice"],
                $data["currency"],
                $data["foglalta"],
                $data["exportdata"],
                $data["externalid"],
                $data["paid"],
                $data["expire"],
                $data["pass"],
                $data["jarat"],
                $data["companytext"],
                $data["dokirexcegid"]
            )
        );

        $fid = sql_insert_id();
        $this->updateFoglalasData($fid);

        return $fid;
    }


    public function setAutoAddressForReservation($placeId, $reservationId) {
        if ($reservationData = sql_query("select id from foglalasok where id=? and (irsz='' or irsz is null) and (varos='' or varos is null) and (utca='' or utca is null)", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
            //sql_query("update foglalasok set irsz=? where id=?", ["1111", $reservationId]);
            if ($placeData = sql_query("select * from helyszinek where id=?", [$placeId])->fetch(PDO::FETCH_ASSOC)) {
                sql_query("update foglalasok set irsz=?, varos=?, utca=? where id=?", [$placeData["autoirsz"], $placeData["autovaros"], $placeData["autoutca"], $reservationId]);
            }
        }
    }

    private $reservedTimeId = 0;
    private $newAddTime = null;
    private $copyReservationData = [];
    private $copy = false;

    public function addIdoPont():int {
        //ide már csak orvosid paraméterrel érkezhet hívás!
        //input:
        //$_GET["orvosid"]
        //$_GET["szt"]
        //$_GET["addidopont"]
        //$_GET["rinterval"]

        if (empty($this->adminUser->user)) {
            die("errorA foglalás nem sikerült, nem vagy bejelentkezve!");
        }

        $fid = 0;
        if (isset($_SESSION["helyszin"])) {
            $foService = new FoglaljOrvostService();

            $szuresTipusId = intval($_GET["szt"]);
            $cegId         = 0;
            $orvosId       = !empty($_GET["orvosid"]) ? intval($_GET["orvosid"]) : 0;

            if ($this->adminUser->isCegAdmin()) {
                $cegIds = $this->adminUser->getCegListArray();
                if (isset($cegIds[1])) {
                    $cegId = $cegIds[1];
                }
            }

            $errorMsg = "Az orvos nem elérhető!";

            if ($orvosData = sql_fetch_array(sql_query("select * from orvosok where id=? and aktiv=1", [$orvosId]))) {
                if ($orvosData["onlytel"] == 1) {
                    //die("errorEz az orvos csak a telefonjára fogad foglalást!");
                }
                if ($orvosData["externalonly"] == 1) {
                    //die("errorEhhez az orvoshoz a recepció nem rögzíthet foglalást!");
                }
            }

            //if ($orvosId == 117) {
                //managerszűrés korlátlan
            //    $selectedOrvosId = $orvosId;
            //}
            if ($this->orvosIdopontIsFree($_GET["addidopont"], $orvosId, $_GET["rinterval"])) {
                $selectedOrvosId = $orvosId;
            }

            if (!isset($selectedOrvosId)) {
                die("error{$errorMsg}");
            }

            sql_query("insert into foglalasok set aktiv=1, foglalta=?, regdatum=now(), nev='nincs név', cegid=?, helyszinid=?, szurestipusid=?, orvosassigned=?, datum=?",
                [$this->adminUser->user["username"], $cegId, $_SESSION["helyszin"], $szuresTipusId, $selectedOrvosId, $_GET["addidopont"]]);
            $fid = sql_insert_id();
            $this->setAutoAddressForReservation($_SESSION["helyszin"], $fid);

            if (!empty($this->copyReservationData)) {
                sql_query("update foglalasok set regdatum=now(), foglalta=?, modifiedby=?, modifiedtime=now(), cegid=?, paciensid=?, nev=?, email=?, telefon=?, szuldatum=?, szulhely=?, anyjaneve=?, neme=?, taj=?, irsz=?, varos=?, utca=?, munkaltato=?, munkakor=?, adoszam=?, rkod=?, megj=?, alkalmassag=?, alkalmassagido=?, alkalmassagikhet=?, tudoszuroervenyesseg=?, tudoszuro=?, smssent=1 where id=?",
                    [$this->copyReservationData["foglalta"], $this->adminUser->user["username"], $this->copyReservationData["cegid"], $this->copyReservationData["paciensid"], $this->copyReservationData["nev"], $this->copyReservationData["email"], $this->copyReservationData["telefon"], $this->copyReservationData["szuldatum"], $this->copyReservationData["szulhely"], $this->copyReservationData["anyjaneve"], $this->copyReservationData["neme"], $this->copyReservationData["taj"], $this->copyReservationData["irsz"], $this->copyReservationData["varos"], $this->copyReservationData["utca"], $this->copyReservationData["munkaltato"], $this->copyReservationData["munkakor"], $this->copyReservationData["adoszam"], rand(11000,98000), $this->copyReservationData["megj"], $this->copyReservationData["alkalmassag"], $this->copyReservationData["alkalmassagido"], $this->copyReservationData["alkalmassagikhet"], $this->copyReservationData["tudoszuroervenyesseg"], $this->copyReservationData["tudoszuro"], $fid]);
                logActivity("foglalas", $fid,"{$this->copyReservationData["nev"]} foglalás másolása {$this->copyReservationData["datum"]} -> {$_GET["moveidopont"]}","");
            } else {
                logActivity("foglalas", $fid, "foglalás hozzáadása {$_GET["addidopont"]}", print_r($_POST, true));
            }

            $this->updateFoglalasData($fid);

            if ($selectedOrvosId == 0) {
                $oid = $this->selectFreeOrvosForIdopont($fid);
                //echo $oid;
                sql_query("update foglalasok set orvosassigned=? where id=? and orvosassigned=0", array($oid, $fid));
            }

            //Foglaljorvost.hu-nak átküldés
            $foService->newReservation($fid);

            $api = new BookingSyncApi();
            $api->newReservation($fid);
        }
        return $fid;
    }

    public function addIdoPontNew():array {
        //ide már csak orvosid paraméterrel érkezhet hívás!
        //input:
        //$_GET["orvosid"]
        //$_GET["szt"]
        //$_GET["addidopont"]
        //$_GET["rinterval"]

        $result = ["error" => "", "reservationId" => 0];

        if (empty($this->adminUser->user)) {
            $result["error"] = "A foglalás nem sikerült, nem vagy bejelentkezve!";
            return $result;
        }

        $fid = 0;
        if (isset($_SESSION["helyszin"])) {
            $foService = new FoglaljOrvostService();

            $szuresTipusId = intval($_GET["szt"]);
            $cegId         = 0;
            $orvosId       = !empty($_GET["orvosid"]) ? intval($_GET["orvosid"]) : 0;

            if ($this->adminUser->isCegAdmin()) {
                $cegIds = $this->adminUser->getCegListArray();
                if (isset($cegIds[1])) {
                    $cegId = $cegIds[1];
                }
            }

            $errorMsg = "Az orvos nem elérhető!";

            if ($orvosData = sql_fetch_array(sql_query("select * from orvosok where id=? and aktiv=1", [$orvosId]))) {
                if ($orvosData["onlytel"] == 1) {
                    //$result["error"] = "Ez az orvos csak a telefonjára fogad foglalást!";
                    //return $result;
                }
                if ($orvosData["externalonly"] == 1) {
                    //$result["error"] = "Ehhez az orvoshoz a recepció nem rögzíthet foglalást!";
                    //return $result;
                }
            }

            //if ($orvosId == 117) {
            //managerszűrés korlátlan
            //    $selectedOrvosId = $orvosId;
            //}
            if ($this->orvosIdopontIsFree($_GET["addidopont"], $orvosId, $_GET["rinterval"])) {
                $selectedOrvosId = $orvosId;
            }

            if (!isset($selectedOrvosId)) {
                $result["error"] = $errorMsg;
                return $result;
            }

            sql_query("insert into foglalasok set aktiv=1,foglalta=?,regdatum=now(),nev='nincs név',cegid=?,helyszinid=?,szurestipusid=?,orvosassigned=?,datum=?", array($this->adminUser->user["username"], $cegId, $_SESSION["helyszin"], $szuresTipusId, $selectedOrvosId, $_GET["addidopont"]));
            $fid = sql_insert_id();

            if (!empty($this->copyReservationData)) {
                sql_query("update foglalasok set regdatum=now(), foglalta=?, modifiedby=?, modifiedtime=now(), cegid=?, paciensid=?, nev=?, email=?, telefon=?, szuldatum=?, szulhely=?, anyjaneve=?, neme=?, taj=?, irsz=?, varos=?, utca=?, munkaltato=?, munkakor=?, adoszam=?, rkod=?, megj=?, alkalmassag=?, alkalmassagido=?, alkalmassagikhet=?, tudoszuroervenyesseg=?, tudoszuro=?, smssent=1 where id=?",
                    [$this->copyReservationData["foglalta"], $this->adminUser->user["username"], $this->copyReservationData["cegid"], $this->copyReservationData["paciensid"], $this->copyReservationData["nev"], $this->copyReservationData["email"], $this->copyReservationData["telefon"], $this->copyReservationData["szuldatum"], $this->copyReservationData["szulhely"], $this->copyReservationData["anyjaneve"], $this->copyReservationData["neme"], $this->copyReservationData["taj"], $this->copyReservationData["irsz"], $this->copyReservationData["varos"], $this->copyReservationData["utca"], $this->copyReservationData["munkaltato"], $this->copyReservationData["munkakor"], $this->copyReservationData["adoszam"], rand(11000,98000), $this->copyReservationData["megj"], $this->copyReservationData["alkalmassag"], $this->copyReservationData["alkalmassagido"], $this->copyReservationData["alkalmassagikhet"], $this->copyReservationData["tudoszuroervenyesseg"], $this->copyReservationData["tudoszuro"], $fid]);
                logActivity("foglalas", $fid,"{$this->copyReservationData["nev"]} foglalás másolása {$this->copyReservationData["datum"]} -> {$_GET["moveidopont"]}","");
            } else {
                logActivity("foglalas", $fid, "foglalás hozzáadása {$_GET["addidopont"]}", print_r($_POST, true));
            }

            $this->updateFoglalasData($fid);

            if ($selectedOrvosId == 0) {
                $oid = $this->selectFreeOrvosForIdopont($fid);
                //echo $oid;
                sql_query("update foglalasok set orvosassigned=? where id=? and orvosassigned=0", array($oid, $fid));
            }

            //Foglaljorvost.hu-nak átküldés
            $foService->newReservation($fid);

            $api = new BookingSyncApi();
            $api->newReservation($fid);
        }
        $result["reservationId"] = $fid;
        return $result;
    }

    public function deleteAllKiegeszitoVizsgalatok($reservationData) {
        if (Booking_Constants::SQL_DB == "keltexmed" && $reservationData["szurestipusid"] == 1) {
            sql_query("delete from foglalasok where taj=? and taj<>'' and date(datum)=? and szurestipusid=? limit 1", [$reservationData["taj"], date("Y-m-d", strtotime($this->copyReservationData["datum"])), Booking_Constants::TUDOSZURES_ID]);
            sql_query("delete from foglalasok where taj=? and taj<>'' and date(datum)=? and szurestipusid=? limit 1", [$reservationData["taj"], date("Y-m-d", strtotime($this->copyReservationData["datum"])), Booking_Constants::LABOR_ID]);
            sql_query("delete from foglalasok where taj=? and taj<>'' and date(datum)=? and szurestipusid=? limit 1", [$reservationData["taj"], date("Y-m-d", strtotime($this->copyReservationData["datum"])), Booking_Constants::HALLASVIZSGALAT_ID]);
        }
    }

    public function replicateKiegeszitoVizsgalatok($reservationId):string {
        $status = "";
        if ($reservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id = ? and szurestipusid=1", [$reservationId]))) {
            if ($reservationData["tudoszuro"] == 1) {
                $status .= $this->replicateReservationToAnotherService($reservationData, Booking_Constants::TUDOSZURES_ID);
            } else {
                //$this->deleteKiegeszitoVizsgalat($reservationData, Booking_Constants::TUDOSZURES_ID);
            }
            if ($reservationData["kieg_labor"] == 1) {
                $status .= $this->replicateReservationToAnotherService($reservationData, Booking_Constants::LABOR_ID);
            } else {
                //$this->deleteKiegeszitoVizsgalat($reservationData, Booking_Constants::LABOR_ID);
            }
            if ($reservationData["kieg_hallas"] == 1) {
                $status .= $this->replicateReservationToAnotherService($reservationData, Booking_Constants::HALLASVIZSGALAT_ID);
            } else {
                //$this->deleteKiegeszitoVizsgalat($reservationData, Booking_Constants::HALLASVIZSGALAT_ID);
            }
        }
        return $status;
    }


    public function moveIdopont() {
        if (isset($_SESSION["helyszin"])) {
            $fid           = intval($_GET["fid"]);
            $newfid        = $fid;
            $szuresTipusId = intval($_GET["szt"]);
            $this->copy    = !empty($_GET["cpy"]);
            $orvosId       = intval($_GET["orvosid"]);

            $this->copyReservationData = sql_fetch_array(sql_query("select * from foglalasok where id=?", [$fid]));

            if ($this->copy) {
                //ha másolás
                $_GET["addidopont"] = $_GET["moveidopont"];
                $this->addIdoPont();
                return;
            }

            //ha mozgatás
            if (!$this->orvosIdopontIsFree($_GET["moveidopont"], $orvosId, $_GET["rinterval"])) {
                die("errorAz orvos nem elérhető!");
            }

            sql_query("update foglalasok set aktiv=1, foglalta=?, helyszinid=?, szurestipusid=?, datum=?, rinterval=?, orvosassigned=0
                where id=?", [$this->adminUser->user["nev"], $_SESSION["helyszin"], $szuresTipusId, $_GET["moveidopont"], intval($_GET["rinterval"]), $newfid]);

            if ($orvosId != $this->copyReservationData["orvosassigned"] && $this->copyReservationData["fofid"] != 0) {
                //foglaljorvos foglalás csak egy orvoson belül mozgatható, ha nem így van visszaállítjuk az adatokat
                sql_query("update foglalasok set aktiv=?, foglalta=?, helyszinid=?, szurestipusid=?, datum=?, rinterval=?, orvosassigned=? where id=?",
                    [$this->copyReservationData["aktiv"], $this->copyReservationData["foglalta"], $this->copyReservationData["helyszinid"], $this->copyReservationData["szurestipusid"], $this->copyReservationData["datum"], $this->copyReservationData["rinterval"], $this->copyReservationData["orvosassigned"], $newfid]);
                die("errorFoglaljOrvost.hu foglalás nem helyezhető át másik orvoshoz!");
            }

            //kiegészítő vizsgálatok áthelyezése
            $this->deleteAllKiegeszitoVizsgalatok($this->copyReservationData);
            $this->replicateKiegeszitoVizsgalatok($fid);

            $api = new BookingSyncApi();
            sql_query("update foglalasok set orvosassigned=? where id=?", array($orvosId, $newfid));
            if ($orvosId == $this->copyReservationData["orvosassigned"]) {
                $api->modifyReservation($newfid);
            } else {
                $api->deleteReservation($this->copyReservationData);
                $api->newReservation($newfid);
            }

            logActivity("foglalas", $newfid,"{$this->copyReservationData["nev"]} foglalás mozgatása {$this->copyReservationData["datum"]} -> {$_GET["moveidopont"]}","");

            $foService = new FoglaljOrvostService();
            if ($this->copyReservationData["fofid"] == 0) {
                $foService->newReservation($newfid);
            } else {
                $foService->modifyReservation($newfid);
            }
        }
    }

    public function removeIdopont($id, $code) {
        if ($rowf = sql_fetch_array(sql_query("select * from foglalasok where id=? and pass=?", array($id, $code)))) {
            //tüdőszűrés törlése
            if (Booking_Constants::SQL_DB == "keltexmed" && $rowf["szurestipusid"] == 1) {
                sql_query("delete from foglalasok where taj=? and taj<>'' and date(datum)=? and szurestipusid=? limit 1", [$rowf["taj"], date("Y-m-d", strtotime($rowf["datum"])), Booking_Constants::TUDOSZURES_ID]);
            }

            $GLOBALS["extraloginfo"] = "admin törlés";
            $this->deleteReservation($id, $code);
        }
    }

    public function tappenzCheckHTML($helyszinId) {
        $this->lang = new Lang();
        $webText = $this->lang->webText;
        $html = "";
        if ($this->needTappenzCheckbox($helyszinId)) {
            $html.= "<input type='checkbox' id='betegallomanynyilatkozat' value='1' name='betegallomanynyilatkozat'>";
            $html.= "<span style='cursor:pointer' onClick='toggleCheckBox(\"#betegallomanynyilatkozat\");'><strong>".$webText["betegallomanynyilatkozat"]."</strong></span>";
        }
        return $html;
    }

    public function getPublicServices($helyszinId):array {
        $docAgent = new DocAgent();
        $docAgent->showDefaultAsset = true;

        $rest = sql_query("SELECT b.*, b.noreservation as bnoreservation FROM orvos_beosztas_new b
            LEFT JOIN orvosok o on o.id = b.orvosid
            WHERE (instr(b.beocegek, ?) or b.beocegek='') AND b.aktiv=1 AND o.aktiv=1 AND b.`helyszinid`=?
            AND (b.nap<10 or (b.nap=10 and b.beonap>=date(now()))) AND (b.validto='0000-00-00' OR b.validto>DATE(NOW()))
			GROUP BY b.tipusok", ["|{$_SESSION["helyszindata"]["id"]}|", $helyszinId]);

        $tipusok = [0];
        while ($tipusData = sql_fetch_array($rest)) {
            $tids = explode("|", $tipusData["tipusok"]);
            foreach ($tids as $tid) {
                if (!empty($tid)) {
                    if ($tid == 114 && !isset($_SESSION["enabletest"])) {
                        continue;
                    }
                    $tipusok[] = $tid;
                }
            }
        }

        $tipusok = array_unique($tipusok);
        $services = [];

        $res = sql_query("select * from szurestipusok where id in (".implode(",", $tipusok).") order by !instr(megnev, 'prevent'), !instr(megnev, 'basic'), !instr(megnev, 'silver'), !instr(megnev, 'gold'), !instr(megnev, 'platina'), instr(megnev, 'extra'), megnev");
        while ($tipusData = sql_fetch_array($res)) {
            $tipusData["doctors"] = $this->beosztasService->getDoctors(11, 1, $tipusData["id"]);
            $tipusData["assets"] = $docAgent->getAssetsByType(DocAgent::ASSET_SERVICE_ILLUSTRATION_IMAGE, $tipusData["id"]);

            if ($tipusData["webdoktor"] == 1 || sql_query("SELECT b.* FROM orvos_beosztas_new b
            LEFT JOIN orvosok o on o.id = b.orvosid
            WHERE (instr(b.beocegek, ?) or b.beocegek='') AND b.aktiv=1 AND o.aktiv=1 AND b.`helyszinid`=? and INSTR(tipusok, '|{$tipusData["id"]}|') and noreservation=0 AND (b.nap<10 or (b.nap=10 and b.beonap>=date(now())))
            LIMIT 1", ["|{$_SESSION["helyszindata"]["id"]}|", $helyszinId])->fetch(PDO::FETCH_ASSOC)) {
                $services[] = $tipusData;
            }
        }

        return $services;
    }

    public function foglalasWarnings($reservationData):array {
        $warnings = [];

        if (!in_array($reservationData["nev"], ["Foglalt", "nincs név"])) {
            if (empty($reservationData["cegnev"])) {
                $warnings[] = "Nincs cég kiválasztva!";
            }
            if ($reservationData["cegid"] == Booking_Constants::DEFAULT_COMPANY_ID && $reservationData["szurestipusid"] == 1 && !empty($reservationData["taj"])) {
                $warnings[] = "Üzemorvosi vizsgálathoz válassz másik céget!";
            }
            if (empty($reservationData["taj"])) {
                $warnings[] = "A TAJ szám nincs megadva!";
            }

        }
        return $warnings;
    }


    const AUCHAN_SZURESEK = [
        [103, 0, "Laborvizsgálatok - kisrutin", "Általános, szűrővizsgálat céljából válassza a legkisebb laborvizsgálatunkat. Vénás vérvétel: Vérkép, Süllyedés, Vércukor, Húgysav, Nátrium, Kálium, Karbamid, Kreatinin +eGFR, GOT, GPT, GGT, Alkalikus Foszfatáz AP, LDH, Koleszterin, Triglicerid, összbilirubin, HDL, LDL, Vas", []],
        [13, 0, "Szemészet - optometrista", "A műszeres vizsgálat során az optometrista szakember vizsgálja a látásélességet, a fénytörési hibákat, a szemizmok tevékenységét, a szemlencsét. Javaslatot tesz az esetleges látás korrekciókra.", []],
        [108, 0, "Szív terheléssel összefüggő vizsgálatok", "Szívstressz-mérésre alkalmas műszeres vizsgálat. A szív ingerületvezetési adataiból ad képet a szívizom állapotára, az ingerületvezetés folyamatára, és a szív-stresszkezelésének intenzitására.", []],
        [103, 9500, "Laborvizsgálatok - nagyrutin", "Javasolt, mert számos betegség kimutatható az eredményekből, még a fizikálisan érzékelt panaszok megjelenése előtt. Átfogó vénás vérvétel (Női csomag): Vérkép, Süllyedés, Vércukor, Húgysav, Nátrium, Kálium, Kálcium, Magnézium, Karbamid, Kreatinin +eGFR, GOT, GPT, GGT, Alkalikus Foszfatáz AP, LDH, Koleszterin, Triglicerid, összbilirubin, HDL, LDL, Vas, CRP, Transzferin, TSH, D vitamin, CA-125. Átfogó vénás vérvétel (Férfi csomag): Vérkép, Süllyedés, Vércukor, Húgysav, Nátrium, Kálium, Kálcium, Magnézium, Karbamid, Kreatinin +eGFR, GOT, GPT, GGT, Alkalikus Foszfatáz AP, LDH, Koleszterin, Triglicerid, összbilirubin, HDL, LDL, Vas, CRP, Transzferin, TSH, D vitamin, PSA", []],
        [103, 14000, "Laborvizsgálatok - pajzsmirigy vizsgálat", "A laborcsomag segítségével kimutatható a pajzsmirigy működési zavara (alul működés vagy túl működés) Vénás vérvétel: T3, T4, TSH, Anti TPO, TRAK", []],
        [107, 6000, "ABI vizsgálat", "Keringési zavarok kiszűrése alkalmas műszeres vizsgálat .Kimutatható az  artériák szűkülete, és a kezdődő artériás elváltozások.", []],
        [109, 8000, "Dietetika", "Táplálkozási tanácsadás, egyénre szabott diétás étrend kialakításával.", []],
        [68, 8000, "Mozgásszervi vizsgálat", "Minden ízületre kiterjedő funkcionális mozgásszervi vizsgálat, tanácsadás.", []],
    ];

    const OIF_SZURESEK = [
        [48, 0, "Laborvizsgálat", "laboratóriumi diagnosztikai vizsgálatok (minőségi vérkép, mennyiségi vérkép, vvt. süllyedés, GOT, GPT, GGT, alkalikus foszfatáz, összes bilirubin, karbamid, kreatinin, húgysav, vércukor, Na, K, Mg, vas, összfehérje, összkoleszterin, LDL, HDL, triglicerid, Vizelet vizsgálat: teljes vizelet és üledék)", []],
        [137, 0, "Szemészet", "Képernyő előtti munkavégzéshez szükséges szemészeti szűrővizsgálat, 2 évente szükséges megismételni.", []],
    ];

    const BudapestBrand_SZURESEK = [
        [48, 0, "Laborvizsgálat", "", []],
        [15,0,"Hasi-és kismedencei ultrahang","",[], 1],
        //Menedzser csomag neve: Komplex egészségügyi szűrés - BudapestBrand
    ];

    const KRE_SZURESEK = [
        [137, 0,"Szemészet","",[], 0],
    ];

    public function getInfoPageText($szurestipusid, $inputData = null){
        $checkboxes = ["kisrutin", "nagyrutin", "pajzsmirigy", "noi-tumormarker", "ferfi-tumormarker", "egyeb-labor"];

        if (CompanyService::isALDI()) {
            return "";
        }

        if(CompanyService::isSuzukiGHC()){
            if(isset($_SESSION["user"])){
                if($result = sql_fetch_array(sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=?",array($_SESSION["user"]["torzsszam"])))){
                    $szurestipusid = $_POST["szurestipus"] =$result["csomagid"];
                }
            }    
        }
        
        $data = sql_fetch_array(sql_query("SELECT infopagetext,csomagidotartam FROM szurestipusok WHERE id=?",array($szurestipusid)));

        $text = "";

        if(!empty($data["infopagetext"])){
            $text.= $data["infopagetext"];
        }

        foreach ($checkboxes as $checkbox) {
           if (isset($inputData[$checkbox])) {
               $text = str_replace("id='{$checkbox}'", "id='{$checkbox}' checked ", $text);
           }
        }

        $radiologyCheckBoxNeeded = false;
        if (Booking_Constants::SQL_DB == "keltexmed") {
            if ($_SESSION["helyszindata"]["tudoszuroopcio"] == 1 && $szurestipusid == 1) {
                $radiologyCheckBoxNeeded = true;
            }
        }

        if ($radiologyCheckBoxNeeded) {
            $text .= "<div style='margin-bottom: 10px;'><input type='hidden' name = 'tudoszuroanswerneeded' value = '1' /><span style='font-weight: bold;'>Rendelkezik 1 éven belüli érvényes tüdőszűrő lelettel?</span><br/>";
            $text .= "<input onchange='reservedTimeInvalidate();' type='radio' name = 'tudoszuro' value = '1' ".(isset($_POST["tudoszuro"]) && $_POST["tudoszuro"] == 1?"checked":"")."/>Nem rendelkezem, kérek tüdőszűrő vizsgálatot, azonnali lelet kiadással<br/>";
            $text .= "<input onchange='reservedTimeInvalidate();' type='radio' name = 'tudoszuro' value = '0' ".(isset($_POST["tudoszuro"]) && $_POST["tudoszuro"] == 0?"checked":"")."/>Igen rendelkezem érvényes tüdőszűrő lelettel<br/>";
            $text .= "<input onchange='reservedTimeInvalidate();' type='radio' name = 'tudoszuro' value = '-1' ".(isset($_POST["tudoszuro"]) && $_POST["tudoszuro"] == -1?"checked":"")."/>A munkakörömhöz nincs szükségem tüdőszűrésre<br/>";
            $text .= "</div>";
        }

        $tipusData = sql_query("select * from szurestipusok t where t.id=?", [$szurestipusid])->fetch(PDO::FETCH_ASSOC);
        //Ha menedzser csomagról van szó:
        if (isset($tipusData["ispack"]) && $tipusData["ispack"] == 1) {
            $pack = sql_query("select t.megnev, t.id  from szurescsomagok_kapcs k 
             left join szurestipusok t on t.id = k.szurestipusid
             where k.csomagid=? order by t.megnev", [$szurestipusid])->fetchAll(PDO::FETCH_ASSOC);

            $text.= "<div>";
            $text.= "<div>{$tipusData["megnev"]} tartalma:</div><ul>";
            foreach ($pack as $packData) {
                $text.= "<li>{$packData["megnev"]}</li>";
            }
            $text.= "</ul></div>";
        }

        if (CompanyService::isSuzukiTeszt() || companyService::isSuzukiMenedzser() || CompanyService::isSuzukiGHC()) {

            //Csomag tartalmának kilistázása
            $pack = sql_query("SELECT t.megnev, t.id,k.szurestipusid,k.optionaldoctors,k.shortdescription,k.otherservices  FROM szurescsomagok_kapcs k 
                               LEFT JOIN szurestipusok t ON t.id = k.szurestipusid
                               WHERE k.csomagid=? ORDER BY t.megnev", [$szurestipusid])->fetchAll(PDO::FETCH_ASSOC);

            //További vizsgálatok hozzáadása kor és nem alapján:
            //Ha nőről van szó, megvizsgálom hogy melyik vizsgálatra jogosult (mammó/emlő) a kora alapján (Ha 1985.12.31 előtt született akkor 40+ ha ezután születt akkor 40-)
            if($noiDolgozo=sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=? AND nem=2",[$_SESSION["user"]["torzsszam"]])->fetch(PDO::FETCH_ASSOC))
            {
                //Emlő ultrahang
                if(strtotime($_SESSION["user"]["szuldatum"])>strtotime("1985-12-31")){
                    $extraPack = sql_query("SELECT t.megnev, t.id,k.szurestipusid,k.optionaldoctors,k.shortdescription,k.otherservices  FROM szurescsomagok_kapcs k 
                                       LEFT JOIN szurestipusok t ON t.id = k.szurestipusid
                                       WHERE k.csomagid=0 AND k.szurestipusid=292")->fetch(PDO::FETCH_ASSOC);
                    $pack[] = $extraPack;
                }

                //Mammográfia
                if(strtotime($_SESSION["user"]["szuldatum"])<strtotime("1984-12-31")){
                    $extraPack = sql_query("SELECT t.megnev, t.id,k.szurestipusid,k.optionaldoctors,k.shortdescription,k.otherservices  FROM szurescsomagok_kapcs k 
                                       LEFT JOIN szurestipusok t ON t.id = k.szurestipusid
                                       WHERE k.csomagid=0 AND k.szurestipusid=112")->fetch(PDO::FETCH_ASSOC);

                    $pack[] = $extraPack;
                }

                //Ha 1985.01.01~1985.12.31 között született megkapja mind2 csomagot xd
                //Mindkettő
                if(strtotime($_SESSION["user"]["szuldatum"])>strtotime("1984-12-31")&&strtotime($_SESSION["user"]["szuldatum"])<strtotime("1986-01-01")){
                     $extraPack = sql_query("SELECT t.megnev, t.id,k.szurestipusid,k.optionaldoctors,k.shortdescription,k.otherservices  FROM szurescsomagok_kapcs k 
                                             LEFT JOIN szurestipusok t ON t.id = k.szurestipusid
                                             WHERE k.csomagid=0 AND k.szurestipusid IN (112,292)")->fetchAll(PDO::FETCH_ASSOC);
                    $pack = array_merge($pack,$extraPack);
                }    
            }

            if($pack){
                //Megjelenített szöveg kezdete
                $text = "<div style='margin:5px 0px;font-weight: bold;'>A csomag az alábbi vizsgálatokat tartalmazza:</div>";
                $text.= "<div style='margin-bottom: 10px;'>";
                $text.= "<div style=\"margin-bottom:15px;margin-left:5px;\"><strong>Várható ellátási idő:</strong> <i>{$data["csomagidotartam"]}</i></div>";
                //Vizsgálatok megjelenítése:
                $text.= $this->managerCsomagSzerkeszto($pack);

                $text.= "</div>";
            }
        }

        if (CompanyService::isAuchan()) {
            //auchan override
            $options = "";
            foreach (self::AUCHAN_SZURESEK as $key => $auchanSzures) {
                $disabled = "";
                $onChange = "clearIdopontValasztoOnly();";
                if (!empty($_POST["helyszin"])) {
                    if (in_array($_POST["helyszin"], CompanyService::auchanSingleReservationPlaces())) {
                        $onChange = "preventMultipleServiceSelect(this);";
                        //$onChange = "myAlert(\"A kiegészítő vizsgálatokra már nem fogadunk több foglalást!\");";
                    }
                }

                $price = "<span>(a vizsgálat költségét az Auchan Magyarország Kft téríti a munkavállalónak)</span>";
                if ($auchanSzures[1] != 0) {
                    if (empty($teriteses)) {
                        $options.= "<div style='border-top:1px solid #ccc;padding-top:10px;'><div style='font-weight: bold;'>Térítéses vizsgálatok:</div><div>A kiegészítő vizsgálatok eredményei segítenek megismerni az aktuális egészségi állapotát. Szakembereink javaslatot tesznek a panaszok, tünetek kezelésére. Éljen a lehetőséggel, vegye igénybe a kiegészítő vizsgálatokat!</div></div>";
                        $teriteses = 1;
                        if (true) {
                            $options.= "<div style='color:red;margin-top:10px;'>A kiegészítő térítéses vizsgálatokra nem fogadunk több foglalást!</div>";
                        }
                    }
                    $price = " - <span>".number_format($auchanSzures[1])." Ft</span>";
                    if (true) {
                        $disabled = "disabled";
                        $onChange = "";
                    }
                    if (in_array($_POST["helyszin"], [0])) {
                        $disabled = "";
                        $onChange = "myAlert(\"A kiegészítő vizsgálatok választásához először válassza ki a helyszínt!\");$(this).prop(\"checked\", false);";
                    }
                }
                $options .= "<div style='margin-top:10px;'>";
                $options .= "<div><input {$disabled} onchange='{$onChange}' id='kiegoption{$key}' name='kiegoption{$key}' type='checkbox' ".(isset($_POST["kiegoption{$key}"]) ? "checked":"")." value='{$auchanSzures[0]}'/><label for='kiegoption{$key}'> {$auchanSzures[2]}</label> {$price}</div>";
                if (!empty($auchanSzures[3])) {
                    $options .= "<div style='padding-left:25px;font-size:12px;color:#999;'>{$auchanSzures[3]}</div>";
                }
                $options .= "</div>";
            }

            $text = "<div style='margin:5px 0px;font-weight: bold;'>Kérjük válassza ki az igényelt vizsgálatokat:</div><div style='margin-bottom: 10px;'>{$options}</div>";
        }

        if (CompanyService::isOIF() && $szurestipusid == 1) {
            $options = "";
            foreach (self::OIF_SZURESEK as $key => $szures) {
                $onChange = "clearIdopontValasztoOnly();";
                $options .= "<div style='margin-top:10px;'>";
                $options .= "<div><input onchange='{$onChange}' id='kiegoption{$key}' name='kiegoption{$key}' type='checkbox' ".(isset($_POST["kiegoption{$key}"]) ? "checked":"")." value='{$szures[0]}'/><label for='kiegoption{$key}'> {$szures[2]}</label></div>";
                if (!empty($szures[3])) {
                    $options .= "<div style='padding-left:25px;font-size:12px;color:#999;'>{$szures[3]}</div>";
                }
                $options .= "</div>";
            }
            $text = "<div style='margin:5px 0px;font-weight: bold;'>Kérjük válassza ki az igényelt vizsgálatokat:</div><div style='margin-bottom: 10px;'>{$options}</div>";
        }

        if (CompanyService::isBudapestBrand() && $szurestipusid == 1) {
            $options = "";
            foreach (self::BudapestBrand_SZURESEK as $key => $szures) {
                $tipusId = $szures[0];
                $onChange = "clearIdopontValasztoOnly();";
                if (isset($_SESSION["cartTimes"][$tipusId])) {
                    $_POST["kiegoption{$key}"] = 1;
                }
                $options .= "<div style='margin-top:10px;'>";
                $options .= "<div><input onchange='{$onChange}' id='kiegoption{$key}' name='kiegoption{$key}' type='checkbox' ".(isset($_POST["kiegoption{$key}"]) ? "checked":"")." value='{$szures[0]}'/><label for='kiegoption{$key}'> {$szures[2]}</label></div>";
                if (!empty($szures[3])) {
                    $options .= "<div style='padding-left:25px;font-size:12px;color:#999;'>{$szures[3]}</div>";
                }
                if (!empty($szures[5])) {
                    $options.= "<div style='margin-left:25px;'>";

                    $reservationButtonText = "<i class='fa-regular fa-clock'></i> időpont kiválasztása";
                    $reservationButtonClass = "cartreservationbutton";
                    if (isset($_SESSION["cartTimes"][$tipusId])) {
                        $reservationButtonText = "<i class='fa-regular fa-clock'></i> " . $_SESSION["cartTimes"][$tipusId]["time"];
                        $reservationButtonClass = "cartreservationbuttonfilled";
                    }
                    $options.= "<a class='{$reservationButtonClass} subreservationopenbutton' data-reservationcompanyid='{$_SESSION["helyszindata"]["id"]}' data-reservationtypeid='{$tipusId}' href='#'>{$reservationButtonText}</a>&nbsp;";

                    $options.= "<div id='reservationContainer{$tipusId}' style='display:none;'><div style='margin:0px 0px 10px 0px;'>Időpontok betöltése folyamatban...</div></div>";
                    $options.= "</div>";
                }
                $options .= "</div>";
            }
            $text = "<div style='margin:5px 0px;font-weight: bold;'>Kérjük válassza ki az igényelt vizsgálatokat:</div><div style='margin-bottom: 10px;'>{$options}</div>";
        }

        if (CompanyService::isKRE() && $szurestipusid == 1) {
            if ((isset($_POST["helyszin"]) && in_array($_POST["helyszin"], [0, 1]))) {
                $options = "";
                foreach (self::KRE_SZURESEK as $key => $szures) {
                    $tipusId = $szures[0];
                    $onChange = "clearIdopontValasztoOnly();";
                    if (isset($_SESSION["cartTimes"][$tipusId])) {
                        $_POST["kiegoption{$key}"] = 1;
                    }
                    $options .= "<div style='margin-top:10px;'>";
                    $options .= "<div><input onchange='{$onChange}' id='kiegoption{$key}' name='kiegoption{$key}' type='checkbox' " . (isset($_POST["kiegoption{$key}"]) ? "checked" : "") . " value='{$szures[0]}'/><label for='kiegoption{$key}'> {$szures[2]}</label></div>";
                    if (!empty($szures[3])) {
                        $options .= "<div style='padding-left:25px;font-size:12px;color:#999;'>{$szures[3]}</div>";
                    }
                    if (!empty($szures[5])) {
                        $options .= "<div style='margin-left:25px;'>";

                        $reservationButtonText = "<i class='fa-regular fa-clock'></i> időpont kiválasztása";
                        $reservationButtonClass = "cartreservationbutton";
                        if (isset($_SESSION["cartTimes"][$tipusId])) {
                            $reservationButtonText = "<i class='fa-regular fa-clock'></i> " . $_SESSION["cartTimes"][$tipusId]["time"];
                            $reservationButtonClass = "cartreservationbuttonfilled";
                        }
                        $options .= "<a class='{$reservationButtonClass} subreservationopenbutton' data-reservationcompanyid='{$_SESSION["helyszindata"]["id"]}'  data-reservationtypeid='{$tipusId}' href='#'>{$reservationButtonText}</a>&nbsp;";

                        $options .= "<div id='reservationContainer{$tipusId}' style='display:none;'><div style='margin:0px 0px 10px 0px;'>Időpontok betöltése folyamatban...</div></div>";
                        $options .= "</div>";
                    }
                    $options .= "</div>";
                }
                $text = "<div style='margin:5px 0px;font-weight: bold;'>Kérjük válassza ki az igényelt vizsgálatokat:</div><div style='margin-bottom: 10px;'>{$options}</div>";
            }
        }

        return $text;
    }

    public function isOnlineTipus($tipusId):bool {
        if (isset($_SESSION["labcode"])) {
            if ($labshopData = sql_fetch_array(sql_query("select * from labshop_vasarlasok where hash=? and status in ('pending', 'done') and payment_method='simplepay'", [$_SESSION["labcode"]]))) {
                return true;
            }
        }
        if ($tipusData = sql_fetch_array(sql_query("select webdoktor, simplepayaktiv, onlysimplepay from szurestipusok where id=?", [$tipusId]))) {
            if ($tipusData["webdoktor"] == 1 && $tipusData["simplepayaktiv"] == 1 && $this->getPriceData($tipusId)) {
                return true;
            }
        }
        return false;
    }

    public function getPriceData($tipusId) {
        if (isset($_SESSION["labcode"])) {
            if ($labshopData = sql_fetch_array(sql_query("select fullprice as price, 'huf' as currency from labshop_vasarlasok where hash=? and status in ('pending', 'done')", [$_SESSION["labcode"]]))) {
                return $labshopData;
            }
        }
        return sql_fetch_array(sql_query("SELECT * FROM arak WHERE tipusid=? AND cegid LIKE '%|{$_SESSION['helyszindata']['id']}|%' ", [$tipusId]));
    }


    private int $lastSubReservationId = 0;
    public bool $replicateDuplicateCheck = true;
    private array $replicatedTimes = [];
    public bool $replicateTajRequired = true;
    public bool $replicateToFirstAvailableTime = false;

    public function replicateReservationToAnotherServiceWithSelectedTime($reservationData, $tipusId, $cartData):string {
        $status = "";
        if (!$tipusData = sql_query("select id, megnev from szurestipusok where id=?", [$tipusId])->fetch(PDO::FETCH_ASSOC)) {
            return $status;
        }

        $reservationData["parentid"] = $reservationData["id"];
        $reservationData["datum"] = $cartData["time"];
        $reservationData["rinterval"] = $cartData["length"];
        $reservationData["orvosid"] = $cartData["doctorId"];
        $reservationData["szurestipus"] = $tipusId;
        $reservationData["tudoszuro"] = 0;
        $reservationData["aktiv"] = 1;
        $reservationData["helyszin"] = $reservationData["helyszinid"];
        $this->lastSubReservationId = $this->addReservationQuery($reservationData);
        $this->replicatedTimes[] = $cartData["time"];

        return "{$tipusData["megnev"]} időpont foglalva: {$cartData["time"]}\n";
    }

    public function tudoszuresHelyszin($helyszinId, $tipusId) {
        if (Booking_Constants::SQL_DB == "keltexmed" && $tipusId == Booking_Constants::TUDOSZURES_ID) {
            $helyszinId = Booking_Constants::DEFAULT_PLACE_IDS[0];
        }
        return $helyszinId;
    }

    public bool $sameTime = false;

    public function replicateReservationToAnotherService($reservationData, $tipusId, $testOnly = false):string {
        $this->lastSubReservationId = 0;
        //$this->replicatedTimes[] = $reservationData["datum"];
        $status = "";
        if (!$tipusData = sql_query("select id, megnev from szurestipusok where id=?", [$tipusId])->fetch(PDO::FETCH_ASSOC)) {
            return $status;
        }
        if (empty(trim($reservationData["taj"])) && $this->replicateTajRequired) {
            return $status;
        }

        if ($this->replicateDuplicateCheck) {
            if (sql_fetch_array(sql_query("select * from foglalasok where taj=? and datum>? and datum<? and szurestipusid=?", [$reservationData["taj"], date("Y-m-d 00:00:00", strtotime($reservationData["datum"])), date("Y-m-d 23:59:59", strtotime($reservationData["datum"])), $tipusId]))) {
                return $status;
            }
        }

        $targetHelyszinId = $this->tudoszuresHelyszin($reservationData["helyszinid"], $tipusId);

        $date = date("Y-m-d", strtotime($reservationData["datum"]));
        $foundTimes = [];
        $bestTime = [];
        $bestCheck = 1000000;
        $weekDay = date("N", strtotime($reservationData["datum"]));
        $beoRes = sql_query("SELECT tol, ig, orvosid, binterval FROM orvos_beosztas_new b WHERE INSTR(b.tipusok, ?) AND (b.nap=? or (b.nap=10 and b.beonap=?)) and aktiv=1 and helyszinid=? 
              AND (b.validfrom='0000-00-00' OR b.validfrom<=?) AND (b.validto='0000-00-00' OR b.validto>=?)                                               
              order by !instr(b.bmegj, 'magán')", ["|{$tipusId}|", $weekDay, $date, $targetHelyszinId, $date, $date]);

        $checkTimesDebug = ["0"];

        while ($beoData = sql_fetch_array($beoRes)) {
            $binterval = $beoData["binterval"];
            $orvosId = $beoData["orvosid"];
            $strictCheck = Booking_Constants::SQL_DB == "hungariamed" && $orvosId == 64; //Kingának magasabb szintű ellenőrzés

            if (sql_fetch_array(sql_query("select * from szabadsag where oid=? and datumtol<=? and datumig>=?", [$orvosId, $date, $date]))) {
                continue;
            }

            $o = 0;
            $startTime = date("Y-m-d H:i:s", strtotime("{$date} {$beoData["tol"]}"));
            $lastTime = date("Y-m-d H:i:s", strtotime("{$date} {$beoData["ig"]} -{$binterval} minute"));
            while (true) {
                $addMinute = $o * $beoData["binterval"];
                $checkTime = date("Y-m-d H:i:s", strtotime("{$startTime} + {$addMinute} minute"));

                if (strtotime($checkTime) > strtotime($lastTime) || $o > 200) {
                    break;
                }

                $checkTimesDebug[] = "{$orvosId}:{$checkTime}";

                $diff = abs(strtotime($reservationData["datum"]) - strtotime($checkTime));
                if ($diff < $bestCheck) {
                    //itt csak letárolom a legjobb időt, ez csak akkor kell, ha elfogyott minden hely
                    $bestCheck = ["time" => $checkTime, "binterval" => $binterval, "orvosId" => $orvosId];
                    $bestTime = $checkTime;
                }

                if ($strictCheck) {
                    $reservationCheckResult = $this->orvosIdopontIsFree(date("Y-m-d H:i", strtotime("{$checkTime}")), $orvosId, $binterval);
                } else {
                    $reservationCheckResult = !sql_fetch_array(sql_query("select * from foglalasok where datum=? and szurestipusid=? and helyszinid=? and orvosassigned=?", [$checkTime, $tipusId, $targetHelyszinId, $orvosId]));
                }

                if ($reservationCheckResult) {
                    $foundTimes[] = ["time" => $checkTime, "binterval" => $binterval, "orvosId" => $orvosId];
                    if ($this->replicateToFirstAvailableTime) {
                        break;
                    }
                }

                $o++;
            }
        }

        if ($testOnly) {
            if (empty($foundTimes)) {
                return "{$tipusData["megnev"]} szolgáltatásra már elfogytak erre a napra az időpontok, kérjük próbáljon egy másik napra foglalni.";
            } else {
                return "";
            }
        }

        if (empty($foundTimes) && !empty($bestTime)) {
            $foundTimes[] = $bestTime;
        }

        if (!empty($foundTimes)) {
            $diff = 1000000;
            $optimalTime = [];
            $notSoGoodTime = [];

            if (Booking_Constants::SQL_DB == "keltexmed") {
                $this->replicatedTimes = [];
            }

            foreach ($foundTimes as $foundTime) {
                $checkDiff = abs(strtotime($reservationData["datum"]) - strtotime($foundTime["time"]));
                if ($checkDiff < $diff) {
                    foreach ($this->replicatedTimes as $reservedTime) {
                        $tempDiff = abs(strtotime($reservedTime["time"]) - strtotime($foundTime["time"]));
                        if ($tempDiff < 5*60) {
                            $notSoGoodTime = $foundTime;
                        }
                    }

                    if ($notSoGoodTime != $foundTime) {
                        $diff = $checkDiff;
                        $optimalTime = $foundTime;
                    }
                }
            }

            if (empty($optimalTime)) {
                $optimalTime = $notSoGoodTime;
            }

            if (Booking_Constants::SQL_DB == "hungariamed" && $reservationData["helyszinid"] == CompanyService::SUZUKI_ARENA_HELSZIN_ID) {
                //ghc esetében fixen a csomag időpontja mindennek az időpontja
                $optimalTime["time"] = $reservationData["datum"];
            }

            $reservationData["parentid"] = $reservationData["id"];
            $reservationData["datum"] = $this->sameTime ? $reservationData["datum"] : $optimalTime["time"];
            $reservationData["rinterval"] = $optimalTime["binterval"];
            $reservationData["orvosid"] = $optimalTime["orvosId"];
            $reservationData["szurestipus"] = $tipusId;
            $reservationData["tudoszuro"] = 0;
            $reservationData["aktiv"] = 1;
            $reservationData["helyszin"] = $targetHelyszinId;
            $this->lastSubReservationId = $this->addReservationQuery($reservationData);
            $this->replicatedTimes[] = $optimalTime;

            //Foglaljorvost.hu-nak átküldés
            $foService = new FoglaljOrvostService();
            $foService->newReservation($this->lastSubReservationId);

            //$api = new BookingSyncApi();
            //$api->newReservation($newReservationId);

            $status = "{$tipusData["megnev"]} időpont foglalva: {$optimalTime["time"]}\n";
        }

        return $status;
    }

    public function set_referal_values($data,$input){

        $q=sql_query("SELECT * FROM kockazati_tenyezok WHERE munkakor=? AND cegid=? AND osztaly=?",array($data["munkakor"],$data["cegid"],$data["worklocation"]));
        while($r=sql_fetch_array($q)){
            foreach($r as $key=>$value){
                $input[$key]=$value;
            }
        }
        
        return $input;
    }

    public function createReferalDoc($data, $docName, $massDump=false)
    {
        //Dokumentum kikeresése név alapján
        $key = array_search($docName, array_column($this->availableDocs, "value"));

        $pdf = new Pdf($this->availableDocs[$key]["filename"]);
        $utils = New Utils();
        $auth_id = $utils->generateRandomStringv2(32);
        $filename = "{$data["nev"]}-{$data["taj"]}-{$data["szuldatum"]}-{$this->availableDocs[$key]["name"]}-(" . $auth_id . ").pdf";

        $input = [
            "nev" => $this->pdfChars($data["nev"]),
            "taj" => $data["taj"],
            "szuldatum" => date("Y.m.d", strtotime($data["szuldatum"])),
            "szulhely" => $this->pdfChars($data["szulhely"]),
            "anyjaneve" => $this->pdfChars($data["anyjaneve"]),
            "munkakor" => $this->pdfChars($data["munkakor"]),
            "vizsgalat"=> $this->pdfChars($data["vizsgalat"]),
            "telephely"=> $this->pdfChars($data["worklocation"]),
            "kelte" => date("Y.m.d", strtotime($data["regdatum"])),
            "keltezes" => date("Y.m.d", strtotime($data["regdatum"])),
            "teljescim" => $this->pdfChars($data["teljescim"]),
            "auth_id" => $auth_id
        ];

        if($data["cegid"]==220){
            $input["beutalo_megjegyzes"] = $this->pdfChars($data["beutalo_megjegyzes"]);
            $input["szervezet"] = $this->pdfChars($data["szervezet_megnev"]);
            $input["torzsszam"] = $this->pdfChars($data["torzsszam"]);
            $input["khkod"] = $this->pdfChars($data["khkod"]);

            if($data["vizsgalat"]=="Időszakos- Foglalkozás Egészségügyi vizsgálat"){
                $input["indoklas"]= "Elözö vizsgálat érvényessége hamarosan lejár";
            }
            if($data["vizsgalat"]=="Előzetes - Foglalkozás Egészségügyi vizsgálat"){
                $input["indoklas"]= "Munkába állást megelözö orvosi alkalmassági vizsgálat";
            }
            if($data["vizsgalat"]=="Soronkívüli- Foglalkozás Egészségügyi vizsgálat"){
                $input["indoklas"]= "hozott dokumentumok/javaslat alapján";
            }
            if($data["vizsgalat"]=="Záró- Foglalkozás Egészségügyi vizsgálat"){
                $input["indoklas"]= "Foglalkoztatás megszűnése";
            }
        }

        if($this->availableDocs[$key]["type"]=="full-form"){
            $input = $this->set_referal_values($data,$input);
        }

        $result = $pdf->fillForm($input)
            ->flatten()
            //->saveAs("/var/www/marci/onlinebejelentkezes/public/admin/templates/" . $filename);
            ->saveAs("/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/" . $filename);

        if ($result === false) {
            $error = $pdf->getError();

            var_dump($error);
        } else {
            $docAgent= new DocAgent();
            //$docAgent->saveLocalDoc("/var/www/marci/onlinebejelentkezes/public/admin/templates/" . $filename, ["fid" => $data["fid"]]);
            $docAgent->saveLocalDoc("/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/" . $filename, ["fid" => $data["fid"]]);

            return $filename;
        }
    }

    private function pdfChars($text) {
        return str_replace(["ő","ű","í","Ő","Ű","Í"], ["ö","ü","i","Ö","Ü","I"], $text);
    }


    public function getLaborSzoveg():string {
        $laborszoveg = "";
        if(isset($_POST["labor-csomagok"]) && $_POST["labor-csomagok"]==1){
            if(isset($_POST["kisrutin"])&&$_POST["kisrutin"]==1)$laborszoveg.=", Kisrutin";
            if(isset($_POST["nagyrutin"])&&$_POST["nagyrutin"]==1)$laborszoveg.=", Nagyrutin";
            if(isset($_POST["pajzsmirigy"])&&$_POST["pajzsmirigy"]==1)$laborszoveg.=", Pajzsmirigy";
            if(isset($_POST["noi-tumormarker"])&&$_POST["noi-tumormarker"]==1)$laborszoveg.=", Női tumormarker";

            if(isset($_POST["elnijo"])&&$_POST["elnijo"]==1)$laborszoveg.=", Élni jó csomag";
            if(isset($_POST["paros"])&&$_POST["paros"]==1)$laborszoveg.=", Páros csomag";
            if(isset($_POST["dvitamin"])&&$_POST["dvitamin"]==1)$laborszoveg.=", D-vitamin csomag";
            if(isset($_POST["manager"])&&$_POST["manager"]==1)$laborszoveg.=", Manager csomag";
            if(isset($_POST["holgyegeszseg"])&&$_POST["holgyegeszseg"]==1)$laborszoveg.=", Egészség 50+ csomag hölgyeknek";
            if(isset($_POST["ferfiegeszseg"])&&$_POST["ferfiegeszseg"]==1)$laborszoveg.=", Egészség 50+ csomag férfiaknak";
            if(isset($_POST["prosztata"])&&$_POST["prosztata"]==1)$laborszoveg.=", Prosztata csomag";
            if(isset($_POST["cukor"])&&$_POST["cukor"]==1)$laborszoveg.=", Cukor-kontroll csomag";
            if(isset($_POST["inzulin"])&&$_POST["inzulin"]==1)$laborszoveg.=", Inzulinrezisztencia csomag";
            if(isset($_POST["kerek"])&&$_POST["kerek"]==1)$laborszoveg.=", Kerek csomag";
            if(isset($_POST["noihajhullas"])&&$_POST["noihajhullas"]==1)$laborszoveg.=", Hajhullás női csomag";
            if(isset($_POST["ferfihajhullas"])&&$_POST["ferfihajhullas"]==1)$laborszoveg.=", Hajhullás férfi csomag";
            if(isset($_POST["noihormon3"])&&$_POST["noihormon3"]==1)$laborszoveg.=", Női hormon ciklus 3-5. nap, kiegészítő csomag";
            if(isset($_POST["noihormon21"])&&$_POST["noihormon21"]==1)$laborszoveg.=", Női hormon ciklus 21-23. nap, kiegészítő csomag";
            if(isset($_POST["csontritkulas"])&&$_POST["csontritkulas"]==1)$laborszoveg.=", Csontritkulás csomag";
            if(isset($_POST["pajzsmirigybazis"])&&$_POST["pajzsmirigybazis"]==1)$laborszoveg.=", Pajzsmirigy Bázis csomag";
            if(isset($_POST["pajzsmirigybovitett"])&&$_POST["pajzsmirigybovitett"]==1)$laborszoveg.=", Pajzsmirigy Bővített csomag";
            if(isset($_POST["pajzsmirigymax"])&&$_POST["pajzsmirigymax"]==1)$laborszoveg.=", Pajzsmirigy Max csomag";
            if(isset($_POST["pcos"])&&$_POST["pcos"]==1)$laborszoveg.=", PCOS csomag";
            if(isset($_POST["csaladholgyeknek"])&&$_POST["csaladholgyeknek"]==1)$laborszoveg.=", Családtervező csomag hölgyeknek";
            if(isset($_POST["golya1"])&&$_POST["golya1"]==1)$laborszoveg.=", Gólya csomag I. trimeszter";
            if(isset($_POST["golya2"])&&$_POST["golya2"]==1)$laborszoveg.=", Gólya csomag II. trimeszter (16. hét)";
            if(isset($_POST["golya3"])&&$_POST["golya3"]==1)$laborszoveg.=", Gólya csomag III, trimeszter (24. - 28. hét)";
            if(isset($_POST["hepab"])&&$_POST["hepab"]==1)$laborszoveg.=", Hepatitis B vírus (HBV) csomag";
            if(isset($_POST["torchalap"])&&$_POST["torchalap"]==1)$laborszoveg.=", TORCH alapcsomag";
            if(isset($_POST["torchbovitett"])&&$_POST["torchbovitett"]==1)$laborszoveg.=", TORCH bővített csomag";
            if(isset($_POST["tumorferfi1"])&&$_POST["tumorferfi1"]==1)$laborszoveg.=", Tumormarker csomag férfiaknak I.";
            if(isset($_POST["tumorferfi2"])&&$_POST["tumorferfi2"]==1)$laborszoveg.=", Tumormarker csomag férfiaknak II.";
            if(isset($_POST["tumorholgy1"])&&$_POST["tumorholgy1"]==1)$laborszoveg.=", Tumormarker csomag hölgyeknek I.";
            if(isset($_POST["tumorholgy2"])&&$_POST["tumorholgy2"]==1)$laborszoveg.=", Tumormarker csomag hölgyeknek II.";
            if(isset($_POST["ateresztoalap"])&&$_POST["ateresztoalap"]==1)$laborszoveg.=", Áteresztő bél szindróma Alap csomag";
            if(isset($_POST["ateresztobovitett"])&&$_POST["ateresztobovitett"]==1)$laborszoveg.=", Áteresztő bél szindróma Bővített csomag";
            if(isset($_POST["ateresztopremium"])&&$_POST["ateresztopremium"]==1)$laborszoveg.=", Áteresztő bél szindróma Prémium csomag";
            if(isset($_POST["sportbasic"])&&$_POST["sportbasic"]==1)$laborszoveg.=", Sport Basic csomag*";
            if(isset($_POST["sportextendedferfi"])&&$_POST["sportextendedferfi"]==1)$laborszoveg.=", Sport Extended Férfi csomag*";
            if(isset($_POST["sportpro"])&&$_POST["sportpro"]==1)$laborszoveg.=", Sport Pro csomag*";
            if(isset($_POST["fitkontroll"])&&$_POST["fitkontroll"]==1)$laborszoveg.=", Fitkontroll csomag*";
            if(isset($_POST["covidpajzsalap"])&&$_POST["covidpajzsalap"]==1)$laborszoveg.=", COVID Pajzs alapcsomag";
            if(isset($_POST["covidpajzsxxl"])&&$_POST["covidpajzsxxl"]==1)$laborszoveg.=", COVID Pajzs XXL csomag";
            if(isset($_POST["postcovid"])&&$_POST["postcovid"]==1)$laborszoveg.=", POST COVID csomag";
            if(isset($_POST["nyomelem"])&&$_POST["nyomelem"]==1)$laborszoveg.=", Nyomelem csomag";
            if(isset($_POST["teljesvitamin"])&&$_POST["teljesvitamin"]==1)$laborszoveg.=", Teljes vitamin csomag";
            if(isset($_POST["cdvitamin"])&&$_POST["cdvitamin"]==1)$laborszoveg.=", C- és D-vitamin csomag";
            if(isset($_POST["bvitamin"])&&$_POST["bvitamin"]==1)$laborszoveg.=", B-vitamin csomag";
            if(isset($_POST["faradtvitamin"])&&$_POST["faradtvitamin"]==1)$laborszoveg.=", Fáradtság vitamin csomag";
            if(isset($_POST["antioxidans"])&&$_POST["antioxidans"]==1)$laborszoveg.=", Antioxidáns vitamin csomag";
            if(isset($_POST["vastagbel"])&&$_POST["vastagbel"]==1)$laborszoveg.=", Vastagbéldaganat szűrőcsomag";

            $laborszoveg = substr($laborszoveg, 2);
        }
        return $laborszoveg;
    }

    public function numberOfReservationRequired():int {
        if (CompanyService::isFGSZ()) {
            if (session_id() == "fpsdm440519dgohrth4a3kf4om" || session_id() == "rj5cbf2g8d5n22r73hv00jobar") {
                $doctors = $this->beosztasService->getDoctors($_SESSION["helyszindata"]["id"], $this->helyszin, $this->szuresTipus);
                if (count($doctors) == 1) {
                    if ($doctors[0]["onlytel"] == 1 && substr_count($doctors[0]["email"], "@") && substr_count($doctors[0]["email"], ".")) {
                        return 3;
                    }
                }
                return 3;
            }
        }
        return 1;
    }

    public function setLabShopStatus($id, $status) {
        sql_query("update labshop_vasarlasok set status=? where reservationid=? limit 1", [$status, $id]);
    }

    public function doAuchanExceptions($reservationId):void {
        if (Booking_Constants::SQL_DB != "keltexmed") {
            return;
        }

        if (!CompanyService::isAuchan()) {
            return;
        }

        if (!$reservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id = ?", [$reservationId]))) {
            return;
        }

        //echo "debug info<br/><br/><pre>".print_r($_POST, true)."</pre>";
        //die;

        $reservedTipusok = $subServices = [];
        $price = 0;
        foreach (self::AUCHAN_SZURESEK as $key => $auchanSzures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $auchanSzures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    $reservedTipusok[] = $tipusId;
                }
                $subServices[] = $auchanSzures[2];
                $price += $auchanSzures[1];
            }
        }

        if (count($reservedTipusok) <= 1) {
            sql_query("update foglalasok set megj=trim(concat(megj, ?)) where id=?", [" Választott vizsgálat: ".implode(", ", $subServices). ". Ára: ".number_format($price)." Ft", $reservationId]);
            return;
        }

        $subServices = $reservedTipusok = $reservationTipusMap = $priceMap = [];
        $this->replicateDuplicateCheck = false;
        foreach (self::AUCHAN_SZURESEK as $key => $auchanSzures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $auchanSzures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    $this->replicateReservationToAnotherService($reservationData, $tipusId);
                    $reservationTipusMap[$tipusId] = $this->lastSubReservationId;
                    $reservedTipusok[] = $tipusId;
                }

                $subServices[$tipusId][] = $auchanSzures[2];
                if (!isset($priceMap[$tipusId])) {
                    $priceMap[$tipusId] = 0;
                }
                $priceMap[$tipusId] += $auchanSzures[1];
            }
        }

        foreach ($subServices as $tipusId => $subService) {
            sql_query("update foglalasok set megj=trim(concat(megj, ?)) where id=?", [" Választott vizsgálat: ".implode(", ", $subService). ". Ára: ".number_format($priceMap[$tipusId])." Ft", $reservationTipusMap[$tipusId]]);
        }
    }

    public function doOIFExceptions($reservationId):void {
        if (!CompanyService::isOIF()) {
            return;
        }

        if (!$reservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id = ?", [$reservationId]))) {
            return;
        }

        $reservedTipusok = [];
        foreach (self::OIF_SZURESEK as $key => $szures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $szures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    $reservedTipusok[] = $tipusId;
                }
            }
        }

        $reservedTipusok = $reservationTipusMap = [];
        $this->replicateDuplicateCheck = false;
        foreach (self::OIF_SZURESEK as $key => $szures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $szures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    $this->replicateReservationToAnotherService($reservationData, $tipusId);
                    $reservationTipusMap[$tipusId] = $this->lastSubReservationId;
                    $reservedTipusok[] = $tipusId;
                }
            }
        }

    }

    public function doBudapestBrandExceptions($reservationId):void {
        if (!CompanyService::isBudapestBrand()) {
            return;
        }

        if (!$reservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id = ?", [$reservationId]))) {
            return;
        }

        $reservedTipusok = [];
        foreach (self::BudapestBrand_SZURESEK as $key => $szures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $szures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    $reservedTipusok[] = $tipusId;
                }
            }
        }

        $reservedTipusok = $reservationTipusMap = [];
        $this->replicateDuplicateCheck = false;
        foreach (self::BudapestBrand_SZURESEK as $key => $szures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $szures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    if (isset($_SESSION["cartTimes"][$tipusId])) {
                        $this->replicateReservationToAnotherServiceWithSelectedTime($reservationData, $tipusId, $_SESSION["cartTimes"][$tipusId]);
                    } else {
                        $this->replicateReservationToAnotherService($reservationData, $tipusId);
                    }
                    $reservationTipusMap[$tipusId] = $this->lastSubReservationId;
                    $reservedTipusok[] = $tipusId;
                }
            }
        }

    }

    public function doEONExceptions($reservationId):void {
        if (!CompanyService::isEON()) {
            return;
        }

        if (!$reservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id = ?", [$reservationId]))) {
            return;
        }

        $eonTypes = [110, 164, 185];

        $reservedTipusok = $reservationTipusMap = [];
        $this->replicateDuplicateCheck = false;
        $this->sameTime = true;
        foreach ($eonTypes as $tipusId) {
            $this->replicateReservationToAnotherService($reservationData, $tipusId);
        }

    }

    public function doCargoExceptions($reservationId):void {
        if (!CompanyService::isCargo()) {
            return;
        }

        if (!$reservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id = ?", [$reservationId]))) {
            return;
        }

        $copyTypes = [14, 15, 164, 58, 9];

        $this->replicateDuplicateCheck = false;
        $this->sameTime = true;
        foreach ($copyTypes as $tipusId) {
            $this->replicateReservationToAnotherService($reservationData, $tipusId);
        }

    }

    public function doDRVExceptions($reservationId):void {
        if (!CompanyService::isDRV()) {
            return;
        }

        if (!$reservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id = ?", [$reservationId]))) {
            return;
        }

        $copyTypes = [67,164,107,58];

        $this->replicateDuplicateCheck = false;
        $this->sameTime = true;
        foreach ($copyTypes as $tipusId) {
            $this->replicateReservationToAnotherService($reservationData, $tipusId);
        }

    }


    public function doKREExceptions($reservationId):void {
        if (!CompanyService::isKRE()) {
            return;
        }

        if (!$reservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id = ?", [$reservationId]))) {
            return;
        }

        $reservedTipusok = [];
        foreach (self::KRE_SZURESEK as $key => $szures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $szures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    $reservedTipusok[] = $tipusId;
                }
            }
        }

        $reservedTipusok = $reservationTipusMap = [];
        $this->replicateDuplicateCheck = false;
        foreach (self::KRE_SZURESEK as $key => $szures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $szures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    if (isset($_SESSION["cartTimes"][$tipusId])) {
                        $this->replicateReservationToAnotherServiceWithSelectedTime($reservationData, $tipusId, $_SESSION["cartTimes"][$tipusId]);
                    } else {
                        $this->replicateReservationToAnotherService($reservationData, $tipusId);
                    }
                    $reservationTipusMap[$tipusId] = $this->lastSubReservationId;
                    $reservedTipusok[] = $tipusId;
                }
            }
        }

    }

    public function doAuchanServicesTest():string {
        if (Booking_Constants::SQL_DB != "keltexmed") {
            return "";
        }

        if (!CompanyService::isAuchan() || empty($_POST["helyszin"]) || empty($_POST["datum"])) {
            return "";
        }


        $_POST["helyszinid"] = $_POST["helyszin"];

        //echo "debug info<br/><br/><pre>".print_r($_POST, true)."</pre>";

        $results = [];

        $reservedTipusok = [];
        $this->replicateDuplicateCheck = false;
        foreach (self::AUCHAN_SZURESEK as $key => $auchanSzures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $auchanSzures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    $result = $this->replicateReservationToAnotherService($_POST, $tipusId, true);
                    if (!empty($result)) {
                        $results[] = $result;
                    }
                    $reservedTipusok[] = $tipusId;
                }
            }
        }

        return implode("<br/>", $results);
    }

    public function doOIFServicesTest():string {
        if (!CompanyService::isOIF() || empty($_POST["helyszin"]) || empty($_POST["datum"])) {
            return "";
        }

        $_POST["helyszinid"] = $_POST["helyszin"];

        $results = [];
        $reservedTipusok = [];
        $this->replicateDuplicateCheck = false;
        foreach (self::OIF_SZURESEK as $key => $szures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $szures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    $result = $this->replicateReservationToAnotherService($_POST, $tipusId, true);
                    if (!empty($result)) {
                        $results[] = $result;
                    }
                    $reservedTipusok[] = $tipusId;
                }
            }
        }

        return implode("<br/>", $results);
    }

    public function doBudapestBrandServicesTest():string {
        if (!CompanyService::isBudapestBrand() || empty($_POST["helyszin"]) || empty($_POST["datum"])) {
            return "";
        }

        $_POST["helyszinid"] = $_POST["helyszin"];

        $results = [];
        $reservedTipusok = [];
        $this->replicateDuplicateCheck = false;
        foreach (self::BudapestBrand_SZURESEK as $key => $szures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $szures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    $result = $this->replicateReservationToAnotherService($_POST, $tipusId, true);
                    if (!empty($result)) {
                        $results[] = $result;
                    }
                    $reservedTipusok[] = $tipusId;
                }
            }
        }

        return implode("<br/>", $results);
    }

    public function doKREServicesTest():string {
        if (!CompanyService::isKRE() || empty($_POST["helyszin"]) || empty($_POST["datum"])) {
            return "";
        }

        $_POST["helyszinid"] = $_POST["helyszin"];

        $results = [];
        $reservedTipusok = [];
        $this->replicateDuplicateCheck = false;
        foreach (self::KRE_SZURESEK as $key => $szures) {
            if (isset($_POST["kiegoption{$key}"])) {
                $tipusId = $szures[0];
                if (!in_array($tipusId, $reservedTipusok)) {
                    $result = $this->replicateReservationToAnotherService($_POST, $tipusId, true);
                    if (!empty($result)) {
                        $results[] = $result;
                    }
                    $reservedTipusok[] = $tipusId;
                }
            }
        }

        return implode("<br/>", $results);
    }


    public function getPatientByTAJ($taj, $fid=0, $pid=0):array {
        $w = "";
        if (!$this->adminUser->allCegJog()) {
            $w = "and cegid in (" . $this->adminUser->getCegList() . ")";
        }

        if (!$data = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE taj = ? and id<>? and parentid=0 {$w} order by modifiedtime desc, id desc limit 1", [$taj, $fid]))) {
            if ($data = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE taj = ? and id<>? {$w} order by id desc", [$taj, $pid]))) {
                $data["id"] = 0;
            } else {
                $data["error"] = "Ezzel a TAJ számmal felhasználó nem található!";
            }
        }

        //ha nincs találat, akkor keltexmed esetén benézünk a hmm-re is
        if (isset($data["error"]) && Booking_Constants::SQL_DB == "keltexmed" && $this->adminUser->allCegJog()) {
            //keresés hmm-ben
            $data["error"] = "";
            if (!$data = sql_fetch_array(sql_query_common("SELECT * FROM foglalasok WHERE taj = ? order by datum desc limit 1", [$taj]))) {
                $data["error"] = "Ezzel a TAJ számmal felhasználó nem található!";
            }
        }

        if (!empty($data["error"])) {
            //keresés vízművekben
            $data["error"] = "";

            $file = file_get_contents(__DIR__."/vizmuvektaj.csv");
            $lines = explode("\n", $file);

            foreach ($lines as $key => $line) {
                $fields = explode(";", $line);
                if (trim($fields[2]) == $taj) {
                    $data["nev"] = $fields[1];
                    break;
                }
            }

            if (empty($data["nev"])) {
                $data["error"] = "Ezzel a TAJ számmal felhasználó nem található!".$lines[3][1];
            }
        }

        if (!isset($data["error"])) {
            $data["error"] = "";
        }

        return $data;
    }

    public function managerCsomagSzerkeszto($pack){
        $text = "";

        foreach($pack as $packData){
            $checked="checked=\"true\"";



            //Ha más nevet kell használni csak a cégre akkor ide bele kell futnia
            $packData["megnev"] = $this->utils->AlternativSzurestipusNevByCeg($packData["szurestipusid"],$packData["megnev"]);

            
            if(isset($_POST) && !isset($_POST["szurestipus{$packData["szurestipusid"]}"]) && !empty($_POST)){
                $checked="";
            }

            if(CompanyService::isSuzukiGHC()){
                $checked="checked=\"true\"";
            }


            $onclick = "onClick='$(\"#descriptonForSzurestipus{$packData["szurestipusid"]}\").toggle(\"fast\").toggleClass(\"show-dscriptionForSzurestipus\")'";
            $onChange = "onChange='clearIdopontValasztoOnly()'";
            $text .= "<div><input {$onChange} name=\"szurestipus{$packData["szurestipusid"]}\" type=\"checkbox\" {$checked} /> <label style=\"cursor:pointer\" {$onclick} for=\"\">{$packData["megnev"]} <i class=\"fa-solid fa-chevron-down\"></i></label></div>";

            $text .= "<div class=\"\" id=\"descriptonForSzurestipus{$packData["szurestipusid"]}\">";
            /*$text .= "  <div style='padding-left:25px;font-size:12px;'>";
            $text .= "    <span><strong>A vizsgálatról:</strong></span>";
            $text .= "  </div>";*/
            $text .= "  <div style='padding-left:27px;font-size:12px;color:#999;text-align:justify'>{$packData["shortdescription"]}</div>";

            //Orvos választó:
            if(!empty($packData["optionaldoctors"])){
                $onChange = "onChange='clearIdopontValasztoOnly()'";
                $orvosok = json_decode($packData["optionaldoctors"],true);
                $text .= "<div style='padding-top:5px;padding-left:25px;font-size:12px;'>";
                $text .= "  <span style=\"font-weight:bold\">Orvos választás:&nbsp;&nbsp;</span>";
                $text .= "  <select {$onChange} name=\"prefDoctor{$packData["szurestipusid"]}\">";
                $text .= "<option value=\"0\">Nincs kiválasztva</option>";
                foreach($orvosok as $orvos){
                    $selected="";
                    $orvosData = sql_fetch_array(sql_query("SELECT * FROM orvosok WHERE id=?",[$orvos]));
                    if(isset($_POST["prefDoctor{$packData["szurestipusid"]}"]) && $_POST["prefDoctor{$packData["szurestipusid"]}"] == $orvosData["id"]){
                        $selected="selected=\"true\"";
                    }
                    $text .= "<option {$selected} value=\"{$orvosData["id"]}\">{$orvosData["nev"]}</option>";
                }
                $text .= "</select></div>";
            }

            //Egyéb szolgáltatások:
            if(!empty($packData["otherservices"])){
                $x=0;
                $onChange = "onChange='clearIdopontValasztoOnly()'";
                $szolgaltatasok = json_decode($packData["otherservices"],true);
                $text .= "  <div style='padding-top:5px;padding-left:25px;font-size:12px;'>";
                $text .= "    <span><strong>Igénybe vehető egyéb szolgáltatásaink:</strong></span>";
                $text .= "  </div>";
                $text .= "  <div style='padding-left:27px;font-size:12px;'>";
                foreach($szolgaltatasok as $index => $szolg){
                    $checked="";
                    if(isset($_POST["otherservices-{$packData["szurestipusid"]}-{$index}"]) && $_POST["otherservices-{$packData["szurestipusid"]}-{$index}"]=="on"){
                        $checked="checked=\"true\"";
                    }
                    $text .= "<input {$onChange} name=\"otherservices-{$packData["szurestipusid"]}-{$index}\" {$checked} type=\"checkbox\" />&nbsp;<span>{$szolg}</span>";
                    $x++;
                }
                $text .= "  </div>";
            }

            $text .= "</div>";

            $text .= "<hr style=\"margin-top:10px;margin-bottom:10px;\"></hr>";
        }
        return $text;
    }


}
