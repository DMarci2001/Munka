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

    public $availableDocs = array(
        array("name" => "Éjszakai", "cegid"=>74, "type"=> "simple", "value" => "bp-nightshift", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/bp_A_munkakori_beutalo_generalNight.pdf"),
        array("name" => "Nappali", "cegid"=>74, "type"=> "simple", "value" => "bp-normal", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/bp_A_munkakori_beutalo_general.pdf"),

        //array("name" => "FGSZ", "cegid"=>220, "type"=> "full-form", "value" => "fgsz-beutalo", "filename" => "/var/www/marci/onlinebejelentkezes/public/admin/templates/FGSZ_beutalo.pdf"),
        array("name" => "FGSZ", "cegid"=>220, "type"=> "full-form", "value" => "fgsz-beutalo", "filename" => "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/FGSZ_beutalo.pdf"),
        
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
    private function kiegVizsgalatOverrideIfNeeded():void {
        if (!empty($_GET["kiegChecked"])) {
            $this->kiegIds = explode("_", $_GET["kiegChecked"]);
            $this->kiegIds = array_unique($this->kiegIds);
            if (count($this->kiegIds) == 1) {
                $_GET["szurestipus"] = $this->kiegIds[0];
            }
        }
    }


    public function showIdoPontValasztoV2() {
        $this->lang = new Lang();
        $webText = $this->lang->webText;

        $this->kiegVizsgalatOverrideIfNeeded();

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

        $html .= "<div style='margin:10px 0px 10px 0px;'>";
        $html .= "<div>{$webText["valasszidopontot"]}:</div>";
        $html .= "<table style='margin-top:5px;width:100%;'><tr><td><a href='javascript:{$_GET['javascript']}(" . ($this->honnan - 7) . ($_GET['javascript'] == "showIdoPontValasztoV3" ? ",{$_GET['selectoid']},{$_GET['szurestipus']},{$_GET['helyszin']}" : "") . ")'>{$webText["elo7"]}</a></td><td align='right'><a href='javascript:{$_GET['javascript']}(" . ($this->honnan + 7) . ($_GET['javascript'] == "showIdoPontValasztoV3" ? ",{$_GET['selectoid']},{$_GET['szurestipus']},{$_GET['helyszin']}" : "") . ")'>{$webText["kov7"]}</a></td></tr></table>";
        $html .= "<table style='width:100%;' cellpadding='0' cellspacing='0'><tr>";
        
        for ($i = 0; $i <= 6; $i++) {
            $fix       = $i + $this->honnan;
            $nap       = date("Y-m-d", strtotime("this week monday +{$fix} day"));
            $wd        = date("N", strtotime($nap));
            $this->waitListProcess($nap);
            $orvosList = $this->getOrvosListForIdopontValaszto($nap);
            
            if (($wd == 6 || $wd == 7) && empty($orvosList)) {
                continue;
            }

            $html .= "<td valign='top'>";
            $html .= "<div style='" . ($nap == date("Y-m-d") ? "background:#405d5b;" : "background:#607d8b;") . "margin:8px 1px;padding:4px 10px 4px 10px;color:#fff;font-weight:bold;text-align:center;'>{$nap}<br/>{$webText["hetnap"][$wd]}</div>";

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
                $preResData  = $this->preReservationProtocol($cegId, $this->helyszin, $orvosId);
                $napiBeos    = $this->getBeosztasok("{$nap}", $this->helyszin, $this->szuresTipus, $orvosId);

                $html .= "<div style='display:table-cell;text-align:center;vertical-align: top;" . ($oKey > 1 ? "padding-left:3px;" : "") . "'>";

                if ($_SESSION["helyszindata"]["no_doctor_select"] == 0 || session_id() == "e0k7gvs3s4e9jalq7fhafnir3k") {
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

                    if ($orvosData["pecsetszam"] == "temp") {
                        continue;
                    }

                    $sectionHTML = "";
                    if ($beoKey != 0) {
                        $sectionHTML .= "<div style='width:70px;border-top:1px solid #888;padding-top:5px;margin:8px auto 0px auto;'>";
                    } else {
                        $sectionHTML .= "<div>";
                    }

                    while (true) {
                        $ora = date("H:i", mktime($beginHour, $beginMinute + $step * $binterval, 0, date("m"), date("d"), date("Y")));
                        $buttonTitle = "";
                        $buttonClass = "foglaltbtn";
                        $buttonJava = "nemfog();return false;";
                        $buttonStyle = "";
                        $beoData = [];
                        $step++;

                        if (strtotime($ora) >= strtotime($rowmax["maxrendeles"])) {
                            break;
                        }

                        if ($this->laborOptionBreak($ora)) {
                            continue;
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
                                $availableData[$nap] = $this->getPackageAvailabilityForDay($nap);
                            }
                            if (!empty($availableData[$nap]["error"])) {
                                $buttonTitle = "";
                                $buttonClass = "foglaltbtn";
                                $buttonJava = "nemfog();return false;";
                                $btn = "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a><br/>";
                                $dayError = "<div style='font-size:11px;width:100px;margin: 10px auto;'>{$availableData[$nap]["error"]}</div>";
                            }
                        }

                        //sorszám override aldi esetében
                        if (Booking_Constants::SQL_DB == "hungariamed" && $_SESSION["helyszindata"]["id"] == 90 && $this->szuresTipus == 58) {
                            $jaratok = ["08:30", "10:30", "10:30", "10:30", "10:30", "10:30", "10:30"];
                            if ($sorszam % 6 == 1) {
                                $sectionHTML .= "<div style='margin-top:10px;border-bottom:1px solid #ccc;border-top:1px solid #ccc;padding: 5px 0px;'>{$jaratok[$jarat]}-as járat</div>";
                                $jarat++;
                            }
                            $btn = "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#' style='min-width: 40px;'>{$sorszam}.</a><br/>";
                            $sorszam++;
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



    private function preReservationProtocol($cegId, $helyszinId, $orvosId) {
        $dist = "6 hour";
        $distFullDay = "0 day";
        //ennyi napon belül kell foglalni

        if (Booking_Constants::SITE_DOMAIN == "hungariamed.hu") {
            if ($helyszinId == 1 || $helyszinId == 614 || CompanyService::isFesztivalCompany()) {
                //jász utca vagy fesztivál bármikor foglalhat
                $dist = "-10 hour";
            }
            if (in_array($orvosId, [74])) {
                //74 - Dr. Kővári Gábor
                $dist = "24 hour";
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

            if(in_array($cegId, [373,374,376])){
                $dist = "0 hour";
            }

            if(in_array($cegId, [342])){
                $dist = "0 hour";
            }

            //0 óra foglalási idő ha a Magyar Államkincstár orvosra akar foglalni :P
            if(in_array($orvosId,[1089,841])){
                $dist = "0 hour";
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

    public function getPackageAvailabilityForDay($day, $limitTimes = true):array {
        $vanFixError = false;
        $error = "";
        $timeTableForPackage = [];

        $checkForTypes = $this->packContentTypes;
        $checkForTypes[] = $this->szuresTipus; //csekkoljuk magát csomagot is, hogy van-e benne hely

        if (empty($this->szuresTipusMap)) {
            $res = sql_query("select * from szurestipusok");
            while ($row = sql_fetch_array($res)) {
                $this->szuresTipusMap[$row["id"]] = $row;
            }
        }

        foreach ($checkForTypes as $packTypeId) {
            if ($beos = $this->getBeosztasok("{$day}", $this->helyszin, $packTypeId, 0, true)) {
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

                    while (true) {
                        if ($beoData["nap"] == 10 & $day != $beoData["beonap"]) {
                            break;
                        }

                        $ora = date("H:i", mktime($beginHour, $beginMinute + $step * $interval, 0, date("m"), date("d"), date("Y")));
                        if (strtotime($ora) >= strtotime($beoMinMax["maxrendeles"])) {
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
        if (!$this->szuresTipusData = sql_fetch_array(sql_query("select * from szurestipusok where id=?", array($this->szuresTipus)))) {
            return false;
        }

        if ($this->szuresTipusData["ispack"] == 1) {
            $this->packContentTypes = $this->getPackContentTypes($this->szuresTipus);

            $packTimeData = $this->getPackageAvailabilityForDay($nap);
            if (empty($packTimeData["error"])) {
                return array("onlytel" => 0, "tel" => "");
            } else {
                return false;
            }
        }

        /*
        echo "SELECT * FROM orvos_beosztas_new b
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		WHERE b.`helyszinid`=?
        AND ((INSTR(b.beocegek, ?) OR b.beocegek='') OR (b.nap=10 AND b.open_beo_for_all_company=1 AND DATE_SUB(CONCAT(b.beonap, ' ', b.tol), INTERVAL ROUND(b.release_beo_before_expire_time) HOUR)<NOW()))
		AND (nap=WEEKDAY(?)+1 or beonap=?) AND TIME(tol)<=TIME(?) AND TIME(IF(potig<>'',potig,ig))>TIME(?) AND INSTR(b.tipusok,?) " . ($orvos == 0 ? "" : "and b.orvosid='{$orvos}'") . " and b.aktiv=1
        ORDER BY o.onlytel,o.id";

        print_r(array($helyszin, "|{$cegid}|", $nap, $nap, $ora, $ora, "|{$this->szuresTipus}|"));
        die;
        */


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
                        return $rowb;
                    }
                }
            }
        }
        return false;
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

        /*
        $wd  = date("N", strtotime($day));

        $orvosAvailable = [];
        $res = sql_query("select b.* from orvos_beosztas_new b 
                                left join orvosok o on o.id=b.orvosid 
                                where b.helyszinid=? 
                                and instr(b.tipusok,?) 
                                AND ((INSTR(b.beocegek, ?) OR b.beocegek='') OR (b.nap=10 AND b.open_beo_for_all_company=1 AND DATE_SUB(CONCAT(b.beonap, ' ', b.tol), INTERVAL ROUND(b.release_beo_before_expire_time) HOUR)<NOW()))
                                and (b.nap=? or (b.nap=10 and b.beonap=?))
                                AND (b.validfrom='0000-00-00' OR b.validfrom<=?) AND (b.validto='0000-00-00' OR b.validto>=?)
                                and b.noreservation=0
                                and b.aktiv=1 
                                and o.aktiv=1", [$this->helyszin, "|{$this->szuresTipus}|", "|{$_SESSION['helyszindata']['id']}|", $wd, $day, $day, $day]);

        while ($beoData = sql_fetch_array($res)) {
            if (Booking_Constants::SQL_DB == "keltexmed" && $beoData["orvosid"] == 403) {
                //skip dr. megyeri márta - keltexmed temp
                continue;
            }

            $orvosAvailable[] = $beoData["orvosid"];
        }

        return array_unique($orvosAvailable);
        */
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

    public function orvosIdopontIsFree($idoPont, $orvosId, $interval = 15)
    {
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
                $wcegSecondary = "|92|";
            }
            $wceg = "AND ((INSTR(b.beocegek, '|{$cegId}|') OR INSTR(b.beocegek, '|{$wcegSecondary}|')) OR (b.nap=10 AND b.open_beo_for_all_company=1 AND DATE_SUB(CONCAT(b.beonap, ' ', b.tol), INTERVAL ROUND(b.release_beo_before_expire_time) HOUR)<NOW()))";
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

        //echo $query;die;

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

    public function getTipusMegj($cegid, $tid, $helyszinId = Booking_Constants::DEFAULT_PLACE_IDS[0]) {
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

        if ($helyszinId == 1) {
            $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and trim(megnev)<>'' and csomag=0 and paciens=1", array("|{$cegid}|", $tid));
            if (sql_num_rows($res) > 0) {
                $h .= "<div style='margin:10px 0px;'>";
                $h .= "<div style='font-weight:bold;'>Válasszon szolgáltatást:</div>";
                while ($row = sql_fetch_array($res)) {
                    $lengthText = empty($row["plusminute"]) ? "" : " ({$row["plusminute"]} perc)";
                    //if ($_COOKIE["lang"]!="hu" && trim($row["megnev_{$_COOKIE["lang"]}"])!="") $row["megnev"]=$row["megnev_{$_COOKIE["lang"]}"];
                    $h .= "<div><input type='checkbox' name='altipus{$row["id"]}' value='1' " . (isset($_POST["altipus{$row["id"]}"]) ? "checked" : "") . " /> {$row["megnev"]}{$lengthText}</div>";
                }
                $h .= "</div>";
            }
        }
        if ($_SESSION['helyszindata']['tudoszuroopcio'] == 1 && $tid == 1 && in_array($helyszinId, [1, 100])) {
            $h .= "<div><input type='hidden' name = 'tudoszuroanswerneeded' value = '1' /><span style='font-weight: bold;'>Rendelkezik 1 éven belüli érvényes tüdőszűrő lelettel?</span><br/>";
            $h .= "<input type='radio' name = 'tudoszuro' value = '1' ".(isset($_POST["tudoszuro"]) && $_POST["tudoszuro"] == 1?"checked":"")."/>Nem rendelkezem, kérek tüdőszűrő vizsgálatot, azonnali lelet kiadással<br/>";
            $h .= "<input type='radio' name = 'tudoszuro' value = '0' ".(isset($_POST["tudoszuro"]) && $_POST["tudoszuro"] == 0?"checked":"")."/>Igen rendelkezem érvényes tüdőszűrő lelettel<br/>";
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
            if ($priceId == 120 && $orvosId == 971) {
                $plusMinute = 60;
            }
        }
        return $plusMinute;
    }


    public function checkIdopontSzabadForServices($data):array {
        $result = [];

        $plusMinute = 0;
        $serviceName = "";
        $prices = sql_query("select * from arak where instr(cegid,?) and tipusid=? and csomag=0", ["|{$_SESSION["helyszindata"]["id"]}|", $data["szurestipus"]]);
        foreach ($prices as $price) {
            if (isset($data["altipus{$price["id"]}"])) {
                $price["plusminute"] = $this->plusMinuteDrotozasok($price["plusminute"], $price["id"], $data["orvosselected"]);
                if ($price["plusminute"] > $plusMinute) {
                    $plusMinute = $price["plusminute"];
                    $serviceName = $price["megnev"];
                }
            }
        }

        if ($plusMinute > 0 && !empty($data["datum"]) && !empty($data["rinterval"]) && !empty($data["orvosselected"])) {
            $nap = date("Y-m-d", strtotime($data["datum"]));
            $allInterval = $plusMinute > $data["rinterval"] ? $plusMinute : $data["rinterval"];
            if (sql_fetch_array(sql_query("SELECT id, datum FROM foglalasok WHERE datum>=?
                   AND ((datum<=? AND datum>DATE_SUB(?, INTERVAL IF(rinterval=0, 5, rinterval) MINUTE)) OR (datum>=? AND datum<DATE_ADD(?, INTERVAL ? MINUTE)))
                   AND orvosassigned=?", ["{$nap} 00:00:00", $data["datum"], $data["datum"], $data["datum"], $data["datum"], $allInterval, $data["orvosselected"]]))) {
                $result["error"] = "Ha \"{$serviceName}\" szolgáltatásunkat választja, olyan időpontot válasszon ahol egyben szabad {$allInterval} perc";
            }

            //utolsó időpont check
            if (empty($result["error"])) {
                if ($doctorBeo = $this->beosztasService->getBeosztasDataForDoctor($data["orvosselected"], $nap, $data["helyszin"], $data["szurestipus"])) {
                    $end = date("H:i", strtotime("{$data["datum"]} + {$allInterval} minute"));
                    if (strtotime($end) > strtotime($doctorBeo["ig"])) {
                        $result["error"] = "Ha \"{$serviceName}\" szolgáltatásunkat választja, olyan időpontot válasszon ahol egyben szabad {$allInterval} perc";
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
            $res = sql_query("select id, orvosassigned, helyszinid, pass from foglalasok WHERE id=? and (pass=? or rkod=?)", array($id, $code, $code));
        } else {
            $res = sql_query("select id, orvosassigned, helyszinid, pass from foglalasok WHERE id=? and (pass=? or rkod=?) and (datum>now() or aktiv=0) and eljott=0", array($id, $code, $code));
        }
        if ($row = sql_fetch_array($res)) {
            $foService = new FoglaljOrvostService();
            $foService->deleteReservation($row["id"]);

            $api = new BookingSyncApi();
            $api->deleteReservation($row);

            $notificationService = new NotificationService();
            $notificationService->deleteMessage($row["id"]);

            sql_query("update beutalok set foglalasid='0' where foglalasid=?", array($row["id"]));
            sql_query("delete from foglalasok WHERE id=?", array($row["id"]));
            sql_query("delete from foglalasok WHERE parentid=? and parentid<>0", array($row["id"]));
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
        if (isset($data["taj"])) {
            if (isset($_SESSION["user"]["id"])) {
                $paciensId = intval($_SESSION["user"]["id"]);
            } else {
                if ($userInfo = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE (taj = ? OR email = ?) and cegid=?", array($data['taj'], $data['email'], $cegId)))) {
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
        $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and csomag=0", array("|{$_SESSION["helyszindata"]["id"]}|", $data["szurestipus"]));
        while ($row = sql_fetch_array($res)) {
            if (isset($data["altipus{$row["id"]}"])) {
                $row["plusminute"] = $this->plusMinuteDrotozasok($row["plusminute"], $row["id"], $data["orvosid"]);
                if ($row["plusminute"] > $rinterval) {
                    $rinterval = $row["plusminute"];
                    sql_query("update foglalasok set rinterval=? where id=? limit 1", [$rinterval, $fid]);
                }
                sql_query("insert into fizkapcs set fid=?,aid=?,megnev=?,ar=?,valuta=?", array($fid, $row["id"], $row["megnev"], $row["price"], $row["penznem"]));
            }
        }

        $this->addSubReservation($data, $fid);

        if (isset($_SESSION["remotebeutalo"]) || $_SESSION["helyszindata"]["visszaigazolas"] == 0 || $this->isOnlineTipus($data["szurestipus"])) {
            //ha fizetős, vagy orvos jött, akkor nem kérünk visszaigazolást, megyünk visszaigazolni automatikusan
            $forwardURL = "index.php?page=bookingvalidate&id={$fid}&rk={$rn}";
        } else {
            //visszaigazolást kérünk
            $this->notificationService->sendUserVisszaIgazolas($fid);
            $forwardURL = "index.php?page=bookingsuccessful";
        }

        //Foglaljorvost.hu-nak átküldés
        $foService = new FoglaljOrvostService();
        $foService->newReservation($fid);

        $api = new BookingSyncApi();
        $api->newReservation($fid);

        return $forwardURL;
    }

    public function addSubReservation($data, $parentId)
    {
        if ($this->szuresTipusData["ispack"] == 1) {
            $map = $this->getPackageAvailabilityForDay(date("Y-m-d", strtotime($data["datum"])));

            $parentReservationData = sql_fetch_array(sql_query("select * from foglalasok where id=?", array($parentId)));
            $tipusData = sql_query("select megnev from szurestipusok t where t.id=?", [$parentReservationData["szurestipusid"]])->fetch(PDO::FETCH_ASSOC);
            $data["megj"] = $data["megj"] == "" ? "{$tipusData["megnev"]}":"{$tipusData["megnev"]} - {$data["megj"]}";

            foreach ($map["timeTableForPackage"] as $subTypeId => $subData) {
                if ($parentReservationData["szurestipusid"] == $subTypeId) {
                    //a parent tipus időpontját pontosítjuk
                    //sql_query("update foglalasok set datum=?, orvosassigned=? where id=?", array($subData["idopont"], $subData["orvosid"], $parentId));
                    sql_query("update foglalasok set orvosassigned=? where id=?", [$subData["orvosid"], $parentId]);
                    continue;
                }

                $data["datum"] = $subData["idopont"];
                $data["paciensid"] = $parentReservationData["paciensid"];
                $data["rn"] = rand(1000000, 9999999);
                $data["aktiv"] = $parentReservationData["aktiv"];
                $data["parentid"] = $parentId;
                $data["cegid"] = $parentReservationData["cegid"];
                $data["lang"] = $parentReservationData["rlang"];
                $data["orvosid"] = $subData["orvosid"];
                $data["szurestipus"] = $subTypeId;
                $data["rinterval"] = $subData["interval"];

                $this->addReservationQuery($data);
            }
        }
    }

    public function getDokirexCompanyID($companyId):int {
        $dokirexCompanyId = 0;
        if ($companyId != 0) {
            
        }

        return $dokirexCompanyId;
    }

    public function addReservationQuery($data) {
        if (!isset($data["szulhely"])) $data["szulhely"] = "";
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
        if (!isset($data["rn"])) $data["rn"] = rand(1000000, 9999999);
        if (!isset($data["cegid"])) $data["cegid"] = 0;
        if (!isset($data["dokirexcegid"])) $data["dokirexcegid"] = $this->getDokirexCompanyID($data["cegid"]);

        sql_query(
            "insert into foglalasok set 
            regdatum=now(),
            parentid=?,
            paciensid=?,
            cegid=?,
            datum=?,
            rinterval=?,
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
			pass=?",
            array(
                $data["parentid"],
                $data["paciensid"],
                $data["cegid"],
                $data["datum"],
                intval($data["rinterval"]),
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
                $data["pass"]
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

            sql_query("insert into foglalasok set aktiv=1,foglalta=?,regdatum=now(),nev='nincs név',cegid=?,helyszinid=?,szurestipusid=?,orvosassigned=?,datum=?", array($this->adminUser->user["username"], $cegId, $_SESSION["helyszin"], $szuresTipusId, $selectedOrvosId, $_GET["addidopont"]));
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
                $this->deleteKiegeszitoVizsgalat($reservationData, Booking_Constants::TUDOSZURES_ID);
            }
            if ($reservationData["kieg_labor"] == 1) {
                $status .= $this->replicateReservationToAnotherService($reservationData, Booking_Constants::LABOR_ID);
            } else {
                $this->deleteKiegeszitoVizsgalat($reservationData, Booking_Constants::LABOR_ID);
            }
            if ($reservationData["kieg_hallas"] == 1) {
                $status .= $this->replicateReservationToAnotherService($reservationData, Booking_Constants::HALLASVIZSGALAT_ID);
            } else {
                $this->deleteKiegeszitoVizsgalat($reservationData, Booking_Constants::HALLASVIZSGALAT_ID);
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

    public function removeIdopont($id, $code)
    {
        if ($rowf = sql_fetch_array(sql_query("select * from foglalasok where id=? and pass=?", array($id, $code)))) {
            //tüdőszűrés törlése
            if (Booking_Constants::SQL_DB == "keltexmed" && $rowf["szurestipusid"] == 1) {
                sql_query("delete from foglalasok where taj=? and taj<>'' and date(datum)=? and szurestipusid=? limit 1", [$rowf["taj"], date("Y-m-d", strtotime($rowf["datum"])), Booking_Constants::TUDOSZURES_ID]);
            }

            logActivity("foglalas", $rowf["id"], "{$rowf["nev"]} foglalás törlése {$rowf["datum"]}", json_encode($rowf, JSON_PRETTY_PRINT));
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
            AND (b.nap<10 or (b.nap=10 and b.beonap>=date(now())))
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

        $res = sql_query("select * from szurestipusok where id in (".implode(",", $tipusok).") order by megnev");
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
        [15,0,"Hasi-és kismedencei ultrahang","",[]],
        //Menedzser csomag neve: Komplex egészségügyi szűrés - BudapestBrand
    ];

    public function getInfoPageText($szurestipusid, $inputData = null){
        $checkboxes = ["kisrutin", "nagyrutin", "pajzsmirigy", "noi-tumormarker", "ferfi-tumormarker", "egyeb-labor"];

        $data = sql_fetch_array(sql_query("SELECT infopagetext FROM szurestipusok WHERE id=?",array($szurestipusid)));
       
        if(!empty($data["infopagetext"])){
            $text = $data["infopagetext"];
        }else{
            $text = "";
        }

        foreach ($checkboxes as $checkbox) {
           if (isset($inputData[$checkbox])) {
               $text = str_replace("id='{$checkbox}'", "id='{$checkbox}' checked ", $text);
           }
        }

        $tipusData = sql_query("select * from szurestipusok t where t.id=?", [$szurestipusid])->fetch(PDO::FETCH_ASSOC);
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
            //auchan override
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
            //auchan override
            $options = "";
            foreach (self::BudapestBrand_SZURESEK as $key => $szures) {
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


    public function deleteKiegeszitoVizsgalat($reservationData, $tipusId) {
        if ($oldReservation = sql_fetch_array(sql_query("select * from foglalasok where taj=? and datum>? and datum<? and szurestipusid=? and taj<>''", [$reservationData["taj"], date("Y-m-d 00:00:00", strtotime($reservationData["datum"])), date("Y-m-d 23:59:59", strtotime($reservationData["datum"])), $tipusId]))) {
            $this->deleteReservation($oldReservation["id"], $oldReservation["pass"]);
        }
    }

    private int $lastSubReservationId = 0;
    public bool $replicateDuplicateCheck = true;
    private array $replicatedTimes = [];
    public bool $replicateTajRequired = true;
    public bool $replicateToFirstAvailableTime = false;

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

        $date = date("Y-m-d", strtotime($reservationData["datum"]));
        $foundTimes = [];
        $bestTime = [];
        $bestCheck = 1000000;
        $weekDay = date("N", strtotime($reservationData["datum"]));
        $beoRes = sql_query("SELECT tol, ig, orvosid, binterval FROM orvos_beosztas_new b WHERE INSTR(b.tipusok, ?) AND (b.nap=? or (b.nap=10 and b.beonap=?)) and aktiv=1 and helyszinid=?", ["|{$tipusId}|", $weekDay, $date, $reservationData["helyszinid"]]);
        while ($beoData = sql_fetch_array($beoRes)) {
            $binterval = $beoData["binterval"];
            $orvosId = $beoData["orvosid"];

            $o = 0;
            $startTime = date("Y-m-d H:i:s", strtotime("{$date} {$beoData["tol"]}"));
            $lastTime = date("Y-m-d H:i:s", strtotime("{$date} {$beoData["ig"]} -{$binterval} minute"));
            while (true) {
                $addMinute = $o * $beoData["binterval"];
                $checkTime = date("Y-m-d H:i:s", strtotime("{$startTime} + {$addMinute} minute"));

                $diff = abs(strtotime($reservationData["datum"]) - strtotime($checkTime));
                if ($diff < $bestCheck) {
                    //itt csak letárolom a legjobb időt, ez csak akkor kell, ha elfogyott minden hely
                    $bestCheck = ["time" => $checkTime, "binterval" => $binterval, "orvosId" => $orvosId];
                    $bestTime = $checkTime;
                }

                if (!sql_fetch_array(sql_query("select * from foglalasok where datum=? and szurestipusid=? and helyszinid=?", [$checkTime, $tipusId, $reservationData["helyszinid"]]))) {
                    $foundTimes[] = ["time" => $checkTime, "binterval" => $binterval, "orvosId" => $orvosId];
                    if ($this->replicateToFirstAvailableTime) {
                        break;
                    }
                }

                $o++;
                if (strtotime($checkTime) > strtotime($lastTime) || $o > 200) {
                    break;
                }
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

            $reservationData["parentid"] = $reservationData["id"];
            $reservationData["datum"] = $optimalTime["time"];
            $reservationData["rinterval"] = $optimalTime["binterval"];
            $reservationData["orvosid"] = $optimalTime["orvosId"];
            $reservationData["szurestipus"] = $tipusId;
            $reservationData["tudoszuro"] = 0;
            $reservationData["aktiv"] = 1;
            $reservationData["helyszin"] = $reservationData["helyszinid"];
            $this->lastSubReservationId = $this->addReservationQuery($reservationData);
            $this->replicatedTimes[] = $optimalTime;

            //Foglaljorvost.hu-nak átküldés
            //$foService = new FoglaljOrvostService();
            //$foService->newReservation($newReservationId);

            //$api = new BookingSyncApi();
            //$api->newReservation($newReservationId);

            $status = "{$tipusData["megnev"]} időpont foglalva: {$optimalTime["time"]}\n";
        }

        return $status;
    }

    public function set_referal_values($data,$input){
        $q=sql_query("SELECT * FROM kockazati_tenyezok WHERE munkakor=? AND cegid=?",array($data["munkakor"],$data["cegid"]));
        while($r=sql_fetch_array($q)){
            foreach($r as $key=>$value){
                $input[$key]=$value;
            }
        }
        
        return $input;
    }

    public function createReferalDoc($data, $docName)
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
        if (CompanyService::isCib()) {
            if (session_id() == "ilh1cct47kd3jqpn5o5ggtqmf9" || session_id() == "rj5cbf2g8d5n22r73hv00jobar") {
                $doctors = $this->beosztasService->getDoctors($_SESSION["helyszindata"]["id"], $this->helyszin, $this->szuresTipus);
                if (count($doctors) == 1) {
                    if ($doctors[0]["onlytel"] == 1 && substr_count($doctors[0]["email"], "@") && substr_count($doctors[0]["email"], ".")) {
                        return 3;
                    }
                }
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
                    $this->replicateReservationToAnotherService($reservationData, $tipusId);
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

        if (!isset($data["error"])) {
            $data["error"] = "";
        }

        return $data;
    }


}
