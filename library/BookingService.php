<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class BookingService
{
    private $lang;
    private $utils;
    public $packContentTypes = [];
    private $honnan = 0;
    private $neme;
    public $helyszin = 0;
    public $szuresTipus = 0;
    public $szuresTipusData;
    public $szuresTipusMap = [];
    public $betegallomany = false;
    public $restrictParameters = [];
    public $beosztasService;
    public $notificationService;
    private $taj;
    private $adminUser;
    public $newReservationId;
    public $munkakorVizsgalatok;

    public function __construct()
    {
        $this->utils = new Utils();
        $this->beosztasService = new BeosztasService();
        $this->notificationService = new NotificationService();
        $this->adminUser = new AdminUser();
        $this->munkakorVizsgalatok = new MunkakorVizsgalatok();
    }


    public function getAvailableTimeTable($startDate, $endDate):array {
        $result["error"] = "";
        $result["startdate"] = $startDate;
        $result["enddate"] = $endDate;

        $docAgent = new DocAgent();
        $docAgent->showDefaultAsset = true;

        if ($this->helyszin == 0) {
            $result["error"] = "Az időpont kiválasztásához válassza ki a helyszínt!";
            return $result;
        }

        if ($this->szuresTipus == 0) {
            $result["error"] = "Az időpont kiválasztásához válassza ki a szűrés tipusát!";
            return $result;
        }

        if (count($this->getGenderPackContentTypes($this->szuresTipus)) != 0 && $this->neme == 0) {
            $result["error"] = "Szűréscsomag választása esetén előbb adja meg a nemét!";
            return $result;
        }

        if (!$rowmax = $this->getMinMax($this->szuresTipus)) {
            $result["error"] = "Erre a szűrés típusra nincsenek beállítva rendelési időpontok.";
            return $result;
        }

        if ($this->checkBookingRestrictionProtocol($this->helyszin)) {
            //Ha nem adott meg tajszámot:
            if ($this->taj == 0) {
                $result["error"] = "Időpontválasztás előtt kérem adja meg a TAJ számát!";
                return $result;
            }

            //Paraméterek beállítása a korlátozáshoz:
            $this->restrictParameters = $this->setRestrictParameters($this->helyszin);
        }

        $this->lang = new Lang();
        $webText = $this->lang->webText;
        $cegId   = $_SESSION["helyszindata"]["id"] ?? Booking_Constants::DEFAULT_COMPANY_ID;

        while (strtotime($startDate) <= strtotime($endDate)) {
            $nap       = $startDate;
            $startDate = date("Y-m-d", strtotime("{$startDate} + 1 day"));
            $wd        = date("N", strtotime($nap));
            $orvosList = $this->getOrvosListForIdopontValaszto($nap);

            $napData = [
                "day"         => $nap,
                "fulldayname" => "{$nap} {$webText["hetnap"][$wd]}",
                "weekday"     => "{$webText["hetnap"][$wd]}",
                "description" => "",
                "doctors"     => []
            ];

            if (in_array($nap, $this->getSzunnapok())) {
                $napData["description"] = "Munkaszüneti nap";
                $result["napdata"][] = $napData;
                continue;
            }

            foreach ($orvosList as $oKey => $orvosId) {
                $orvosData   = sql_query("select id, nev, tel, pecsetszam from orvosok where id=?", [$orvosId])->fetch(PDO::FETCH_ASSOC);
                $preResData  = $this->preReservationProtocol($cegId, $this->helyszin, $orvosId);
                $napiBeos    = $this->getBeosztasok("{$nap}", $this->helyszin, $this->szuresTipus, $orvosId);
                $rowmax      = $this->getMinMaxNew($this->szuresTipus, $orvosId, $nap);
                $step        = 0;
                $freeTimes   = 0;
                $realTimes   = 0;
                $elsoIdopont = [];
                $timeLoopEnd = false;
                $lastButton  = [];
                $beginHour   = round(substr($rowmax["minrendeles"], 0, 2));
                $beginMinute = round(substr($rowmax["minrendeles"], 3, 2));
                $dist        = $preResData["hour"]; //ennyi órán belül kell foglalni
                $distFullDay = $preResData["day"]; //ennyi napon belül kell foglalni
                $binterval   = $napiBeos[0]["binterval"];
                $orvosData["idopontok"] = [];
                $orvosData["assets"] = $docAgent->getAssetsByType(DocAgent::ASSET_DOCTOR_PHOTO, $orvosId);

                while (!$timeLoopEnd) {
                    $ora         = date("H:i", mktime($beginHour, $beginMinute + $step * $binterval, 0, date("m"), date("d"), date("Y")));
                    $beoData     = [];
                    $step        ++;

                    if (strtotime($ora) >= strtotime($rowmax["maxrendeles"])) {
                        break;
                    }

                    $idopontData = [
                        "idopont"  => $ora,
                        "interval" => $binterval,
                        "status"   => "reserved",
                        "title"    => "",
                        "message"  => "Nem foglalható, vagy foglalt időpont!"
                    ];

                    //beosztások beolvasása
                    if ($beos = $this->getBeosztasok("{$nap} {$ora}", $this->helyszin, $this->szuresTipus, $orvosId)) {
                        //szabad orvos kiválasztása
                        foreach ($beos as &$beoData) {
                            if ($this->orvosIdopontIsFree("{$nap} {$ora}", $beoData["orvosid"], $binterval)) {
                                $free = true;

                                if ($beoData["ispotig"] == 1 && $freeTimes != 0) {
                                    $free = false;
                                }

                                if ($free) {
                                    $freeTimes++;
                                    $idopontData["status"] = "free";
                                    $idopontData["title"] = $orvosData["nev"];
                                    $idopontData["message"] = "";
                                    break;
                                }
                            }
                        }
                    }

                    //csak sorban foglalható időpontok intézése
                    if (isset($beoData) && $beoData["csaksorban"] == 1 && isset($elsoIdopont[$nap]) && $idopontData["status"] == "free") {
                        $idopontData["message"] = "Csak sorrendben foglalható időpontok!";
                        $idopontData["status"] = "disabled";
                    }
                    if (!isset($elsoIdopont[$nap]) && $idopontData["status"] == "free") {
                        $elsoIdopont[$nap] = $ora;
                    }

                    if (strtotime("now + {$dist}") > strtotime("{$nap} {$ora}")) {
                        //mégse foglalható, múltbéli dátum vagy túl közeli
                        $idopontData["message"] = "Nem foglalható, vagy foglalt időpont!";
                        $idopontData["status"] = "reserved";
                    }

                    if (strtotime("now + {$distFullDay}") > strtotime("{$nap} 23:59:59")) {
                        //mégse foglalható, csak x napra előre foglalható
                        $idopontData["message"] = "Nem foglalható, vagy foglalt időpont!";
                        $idopontData["status"] = "reserved";
                    }

                    //Ha korlátozás van az orvosnál beállítva az adott cégre akkor vizsgáljam meg, hogy korlátozási időn belül van-e a foglalási szándék!
                    if (count($this->restrictParameters) != 0 && $this->betegallomany != true) {
                        $orvosok = $this->restrictParameters['orvosok'];
                        $oid = array_search($orvosId, array_column($orvosok, "orvosid"));
                        if ($oid !== false) {
                            if (strtotime("{$nap} {$ora}") <= strtotime($this->restrictParameters['datum'])) {
                                $idopontData["message"] = "Nem foglalható, vagy foglalt időpont!";
                                $idopontData["status"] = "reserved";
                            }
                        }
                    }

                    //csak fordított sorrendben időpontok intézése
                    if (isset($beoData) && $beoData["csaksorban"] == 2 && $idopontData["status"] == "free") {
                        $lastButton = $idopontData;
                        $idopontData["message"] = "Csak fordított sorrendben foglalható időpontok!";
                        $idopontData["status"] = "disabled";
                    }

                    //csomag override
                    if (!empty($this->packContentTypes)) {
                        $availableData = $this->getPackageAvailabilityForDay($nap);
                        if (empty($availableData["error"])) {
                            $idopontData["message"] = "";
                            $idopontData["status"] = "free";
                        } else {
                            $idopontData["message"] = $availableData["error"];
                            $idopontData["status"] = "reserved";
                        }
                        $idopontData["title"] = "";
                        $idopontData["idopont"] = "{$rowmax["minrendeles"]} ~ {$rowmax["maxrendeles"]}";
                        $timeLoopEnd = true;
                    }

                    if ($idopontData["status"] == "free") {
                        $realTimes++;
                    }

                    $orvosData["idopontok"][] = $idopontData;
                }

                if (!empty($lastButton) && !empty($orvosData["idopontok"])) {
                    $removed = array_pop($orvosData["idopontok"]);
                    $orvosData["idopontok"][] = $lastButton;
                    $lastButton = [];
                }

                $orvosData["clickabletimes"] = $realTimes;
                $napData["doctors"][] = $orvosData;
            }

            $result["helyszin"] = $this->helyszin;
            $result["szurestipus"] = $this->szuresTipus;
            $result["cegid"] = $cegId;
            $result["napdata"][] = $napData;
        }

        return $result;

    }

    public function showIdoPontValasztoV2() {
        
        $this->lang = new Lang();
        $webText = $this->lang->webText;

        $html = "";
        if (isset($_GET["helyszin"]) && $_GET["helyszin"] != "undefined") {
            $this->setHelyszin($_GET["helyszin"]);
        }
        $this->setNeme($_GET["neme"]);
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
            return json_encode(array("error" => "Az időpont kiválasztásához válassza ki a helyszínt!", "html" => ""));
        }
        if ($this->szuresTipus == 0) {
            return json_encode(array("error" => "Az időpont kiválasztásához válassza ki a szűrés tipusát!", "html" => ""));
        }

        if (count($this->getGenderPackContentTypes($this->szuresTipus)) != 0 && $this->neme == 0) {
            return json_encode(array("error" => "Szűréscsomag választása esetén előbb adja meg a nemét!", "html" => ""));
        }

        if (!$rowmax = $this->getMinMax($this->szuresTipus)) {
            return json_encode(array("error" => "Erre a szűrés típusra nincsenek beállítva rendelési időpontok.", "html" => ""));
        }

        //Foglalás korlátozáshoz szükséges a TAJ szám, ez alapján ellenőrzi vissza, hogy mikortól jelentkezhet vizsgálatra:
        //Először meg kell néznem, hogy az adott helyszínhez tartozik-e (emelett az orvost és céget is meg kell néznem) korlátozás:
        if ($this->checkBookingRestrictionProtocol($this->helyszin)) {
            //Ha nem adott meg tajszámot:
            if ($this->taj == 0) {
                return json_encode(array("error" => "Időpontválasztás előtt kérem adja meg a TAJ számát!", "html" => ""));
            }

            //Paraméterek beállítása a korlátozáshoz:
            $this->restrictParameters = $this->setRestrictParameters($this->helyszin);
        }

       

        $html .= "<div style='display:inline-block;margin:10px 0px 10px 0px;'>";
        $html .= "<div>{$webText["valasszidopontot"]}:</div>";
        $html .= "<table style='margin-top:5px;width:100%;'><tr><td><a href='javascript:{$_GET['javascript']}(" . ($this->honnan - 7) . ($_GET['javascript'] == "showIdoPontValasztoV3" ? ",{$_GET['selectoid']},{$_GET['szurestipus']},{$_GET['helyszin']}" : "") . ")'>{$webText["elo7"]}</a></td><td align='right'><a href='javascript:{$_GET['javascript']}(" . ($this->honnan + 7) . ($_GET['javascript'] == "showIdoPontValasztoV3" ? ",{$_GET['selectoid']},{$_GET['szurestipus']},{$_GET['helyszin']}" : "") . ")'>{$webText["kov7"]}</a></td></tr></table>";
        $html .= "<table cellpadding='0' cellspacing='0'><tr>";

        for ($i = 0; $i <= 6; $i++) {
            $fix       = $i + $this->honnan;
            $nap       = date("Y-m-d", strtotime("this week monday +{$fix} day"));
            $wd        = date("N", strtotime($nap));
            $orvosList = $this->getOrvosListForIdopontValaszto($nap);

            $html .= "<td valign='top'>";
            $html .= "<div style='" . ($nap == date("Y-m-d") ? "background:#405d5b;" : "background:#607d8b;") . "margin:8px 1px;padding:4px 10px 4px 10px;color:#fff;font-weight:bold;text-align:center;'>{$nap}<br/>{$webText["hetnap"][$wd]}</div>";

            if (in_array($nap, $this->getSzunnapok())) {
                $html .= "<div style='text-align:center;margin:5px;padding:5px 0px;color:#888;'>Munkaszüneti<br/>nap</div>";
                $html .= "</td>";
                continue;
            }

            $html.="<div style='display:table;width:100%;'>";

            //<-- MARCI KÓDJA, HA VALAMI NEM STIMMELNE :) -->

            //Végig kell mennyek az orvos listán hogy lecsekkoljam van-e köztük várólistás:
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
            $orvosList = $this->getOrvosListForIdopontValaszto($nap);
            

            foreach ($orvosList as $oKey => $orvosId) {
                $orvosData   = sql_query("select * from orvosok where id=?", [$orvosId])->fetch();
                $preResData  = $this->preReservationProtocol($cegId, $this->helyszin, $orvosId);
                $napiBeos    = $this->getBeosztasok("{$nap}", $this->helyszin, $this->szuresTipus, $orvosId);
                $rowmax      = $this->getMinMaxNew($this->szuresTipus, $orvosId, $nap);
                $step        = 0;
                $freeTimes   = 0;
                $elsoIdopont = [];
                $timeLoopEnd = false;
                $beginHour   = round(substr($rowmax["minrendeles"], 0, 2));
                $beginMinute = round(substr($rowmax["minrendeles"], 3, 2));
                $dist        = $preResData["hour"]; //ennyi órán belül kell foglalni
                $distFullDay = $preResData["day"]; //ennyi napon belül kell foglalni
                $binterval   = $napiBeos[0]["binterval"];

                if ($orvosData["pecsetszam"] == "temp") {
                    continue;
                }

                $napHTML = "";
                $napHTML.= "<div style='display:table-cell;text-align:center;vertical-align: top;".($oKey>1 ? "padding-left:3px;" : "")."'>";
                $napHTML.= "<div style='width:70px;overflow: hidden;text-align: center;font-size: 12px;margin:0px auto 5px auto;'>{$orvosData["nev"]}</div>";

                while (!$timeLoopEnd) {
                    $ora         = date("H:i", mktime($beginHour, $beginMinute + $step * $binterval, 0, date("m"), date("d"), date("Y")));
                    $buttonTitle = "";
                    $buttonClass = "foglaltbtn";
                    $buttonJava  = "nemfog();return false;";
                    $buttonStyle = "";
                    $beoData     = [];
                    $step        ++;

                    if (strtotime($ora) >= strtotime($rowmax["maxrendeles"])) {
                        break;
                    }

                    //beosztások beolvasása
                    if ($beos = $this->getBeosztasok("{$nap} {$ora}", $this->helyszin, $this->szuresTipus, $orvosId)) {
                        //szabad orvos kiválasztása
                        foreach ($beos as &$beoData) {

                            //Meg kell találnom azokat a beokat, amiknél jelezve van, hogy van backup plan-jük. vagy orvost? Nézzük meg orvosra, úgy talán

                            if ($this->orvosIdopontIsFree("{$nap} {$ora}", $beoData["orvosid"], $binterval)) {
                                $free = true;

                                if ($beoData["ispotig"] == 1 && $freeTimes != 0) {
                                    $free = false;
                                }

                                if ($free) {
                                    $freeTimes++;
                                    $buttonClass = "foglalhatobtn";
                                    $varolista = 0;
                                    if ($beoData["csaksorban"] == 1 && strpos($beoData["orvosnev"],"Várólista")!==false) {
                                        $varolista = 1;
                                    }
                                    $buttonTitle = "{$orvosData["nev"]}";
                                    $buttonJava  = "varolista={$varolista};chooseIdoPont(\"{$nap} {$ora}\",{$binterval},{$orvosId},{$_GET['helyszin']},{$_GET['szurestipus']});return false;";
                                    break;
                                }
                            }
                        }
                    }


                    //csak sorban foglalható időpontok intézése
                    if (isset($beoData) && $beoData["csaksorban"] == 1 && isset($elsoIdopont[$nap]) && $buttonClass == "foglalhatobtn") {
                        $buttonJava = "nemfogs(\"{$elsoIdopont[$nap]}\");return false;";
                        $buttonClass .= " halv";
                        
                    }
                    if (!isset($elsoIdopont[$nap]) && $buttonClass == "foglalhatobtn") {
                        $elsoIdopont[$nap] = $ora;
                    }

                    //teszt: minden időpont foglalható
                    //$buttonJava="chooseIdoPont(\"{$nap} {$ora}\");return false;";

                    if (strtotime("now + {$dist}") > strtotime("{$nap} {$ora}")) {
                        //mégse foglalható, múltbéli dátum vagy túl közeli
                        $buttonTitle = "";
                        $buttonClass = "foglaltbtn";
                        $buttonJava = "nemfog();return false;";
                    }

                    if (strtotime("now + {$distFullDay}") > strtotime("{$nap} 23:59:59")) {
                        //mégse foglalható, csak x napra előre foglalható
                        $buttonTitle = "";
                        $buttonClass = "foglaltbtn";
                        $buttonJava = "nemfog();return false;";
                    }

                    //Ha korlátozás van az orvosnál beállítva az adott cégre akkor vizsgáljam meg, hogy korlátozási időn belül van-e a foglalási szándék!
                    if (count($this->restrictParameters) != 0 && $this->betegallomany != true) {
                        $orvosok = $this->restrictParameters['orvosok'];
                        $oid = array_search($orvosId, array_column($orvosok, "orvosid"));
                        if ($oid !== false) {
                            if (strtotime("{$nap} {$ora}") <= strtotime($this->restrictParameters['datum'])) {
                                $buttonTitle = "";
                                $buttonClass = "foglaltbtn";
                                $buttonJava = "nemfog();return false;";
                            }
                        }
                    }

                    if(isset($beoData) && $beoData["csaksorban"] == 1 && strpos($beoData["orvosnev"],"Várólista")!==false){
                        $ora = $step.".";
                        $buttonStyle = "width:37px";
                    }

                    $btn = "<a class='{$buttonClass}' style='{$buttonStyle}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a>";

                    //csak fordított sorrendben időpontok intézése
                    if (isset($beoData) && $beoData["csaksorban"] == 2 && $buttonClass == "foglalhatobtn") {
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
                    /*
                    if (!empty($this->packContentTypes)) {
                        $btn = "";
                        $availableData = $this->getPackageAvailabilityForDay($nap);
                        if (empty($availableData["error"])) {
                            $buttonTitle = "";
                            $buttonClass = "foglalhatobtn";
                            $buttonJava = "chooseIdoPont(\"{$nap} {$ora}\",{$binterval},{$orvosId},{$_GET['helyszin']},{$_GET['szurestipus']});return false;";
                        } else {
                            $buttonTitle = "";
                            $buttonClass = "foglaltbtn";
                            $buttonJava = "nemfog();return false;";
                        }
                        $ora = "{$rowmax["minrendeles"]} ~ {$rowmax["maxrendeles"]}";
                        $timeLoopEnd = true;
                        $btn .= "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a><br/>";
                        $btn .= "<div style='font-size:11px;width:100px;'>{$availableData["error"]}</div>";
                    }
                    */

                    $napHTML .= "<div style='text-align:center;'>{$btn}</div>";
                }

                if (isset($dayError)) {
                    $napHTML.= $dayError;
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

                $napHTML.= "</div>";

                if (isset($lastButton) && isset($btn)) {
                    $napHTML = str_replace($btn, $lastButton, $napHTML);
                    unset($lastButton);
                }

                $html .= $napHTML;
            }
            $html .= "</div>";
            $html .= "</td>";
        }

        $html .= "</tr></table>";
        $html .= "</div>";

        return json_encode(array("error" => "", "html" => $html));
    }


    private function preReservationProtocol($cegId, $helyszinId, $orvosId) {
        $dist = "6 hour";
        $distFullDay = "0 day";
        //ennyi napon belül kell foglalni

        if (Booking_Constants::SITE_DOMAIN == "hungariamed.hu") {
            if ($helyszinId == 1) {
                //jász utca bármikor foglalható
                $dist = "0 hour";
            }
            if (CompanyService::isFesztivalCompany()) {
                //fesztivál bármikor foglalhat
                $dist = "0 hour";
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

            if ($cegId == 82) {
                //waberers
                $dist = "0 hour";
            }

            /*if ($cegId == 46) {
                //vodafone
                $dist = "72 hour";
                if (date("N") == 4) {
                    $dist = "96 hour";
                }
                if (date("N") == 5) {
                    $dist = "120 hour";
                }
            }*/

            if ($orvosId == 36) {
                //36 - dr Bodonyi Melinda
                $distFullDay = "2 day";
            }
        }
        return ["hour" => $dist, "day" => $distFullDay];
    }

    public function checkBookingRestrictionProtocol($helyszinId) {
        if (empty($helyszinId)) {
            return false;
        }
        return sql_num_rows(sql_query("SELECT * FROM foglalas_korlatozasok WHERE helyszinid=? AND cegek LIKE '%|{$_SESSION['helyszindata']['id']}|%' ", array($helyszinId))) > 0;
    }

    public function setRestrictParameters($helyszinId)
    {
        $korlatozottOrvosok = [];
        $korlatozottDatum = "";

        //Orvos->korlátozás idő
        $request = sql_query("SELECT orvosid,restrict_time FROM foglalas_korlatozasok WHERE helyszinid=?", array($helyszinId));
        while ($result = sql_fetch_array($request)) {
            array_push($korlatozottOrvosok, array("orvosid" => $result['orvosid'], "restrict_time" => $result['restrict_time']));
        }

        $oid = array_search($_SESSION['orvosselected'], array_column($korlatozottOrvosok, "orvosid"));

        //Páciens utolsó alkalmasságija:
        $pdata = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE taj=? AND datum < NOW() ORDER BY datum desc LIMIT 1", array($this->taj)));

        $korlatozottDatum = date("Y-m-d", strtotime("{$pdata['datum']} + " . ($pdata['alkalmassagido'] - $korlatozottOrvosok[$oid]['restrict_time']) . " months"));

        return array("orvosok" => $korlatozottOrvosok, "datum" => $korlatozottDatum);
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


    private function getMinMax($szuresTipus, $nap = "")
    {
        $helyszin=$this->helyszin;
        $orvosRestrict = "";
        $typeWhere = "instr(tipusok, '|{$szuresTipus}|')";
        if (!empty($_SESSION["orvosselected"])) {
            $typeWhere.= " and orvosid='".intval($_SESSION["orvosselected"])."'";
        }

        //BP-s maszkolt beosztások
        /*if($_SESSION["helyszindata"]["id"]==74){
            //Ha a fantom helyszínt választják ki:
            
            //Ha a jász utcát:
            if($helyszin==1){
                $orvosRestrict=" AND orvosid NOT IN(282,285)";
                $typeWhere = "instr(tipusok, '|{$szuresTipus}|')";
            }

            if($helyszin==98989898989898){
                $orvosRestrict=" AND orvosid IN(282,285)";
                $helyszin=1;
                $typeWhere = "instr(tipusok, '|{$szuresTipus}|')";
            }
        }*/

        if ($nap != "") {
            $wd  = date("N", strtotime($nap));
            $typeWhere.= " and (b.nap='{$wd}' or (b.nap=10 and b.beonap='{$nap}'))";
        }

        $minMaxData = sql_fetch_array(sql_query("SELECT MIN(tol) as minrendeles,MAX(ig) as maxrendeles,MAX(potig) as maxpotigrendeles 
                                                    FROM orvos_beosztas_new b
                                                    WHERE helyszinid=? and (instr(b.beocegek, ?) or b.beocegek='') and ({$typeWhere}) and aktiv=1 {$orvosRestrict} HAVING MAX(tol) IS NOT NULL",
                                                    [$helyszin, "|{$_SESSION["helyszindata"]["id"]}"]));
        if ($minMaxData["maxpotigrendeles"] > $minMaxData["maxrendeles"]) {
            $minMaxData["maxrendeles"] = $minMaxData["maxpotigrendeles"];
        }
        return $minMaxData;
    }

    private function getMinMaxNew($szuresTipus, $orvosId, $nap = "") {
        $helyszinId = $this->helyszin;
        $cegId      = $_SESSION["helyszindata"]["id"];
        $wd         = date("N", strtotime($nap));
        $orvosRestrict = "";

        //BP-s maszkolt beosztások
        /*if($_SESSION["helyszindata"]["id"]==74){
            //Ha a fantom helyszínt választják ki:
            //Ha a jász utcát:
            if($helyszinId == 1){
                $orvosRestrict = " AND orvosid NOT IN(282,285)";
            }

            if($helyszinId == 98989898989898){
                $orvosRestrict = " AND orvosid IN(282,285)";
                $helyszinId = 1;
            }
        }*/

        $minMaxData = sql_fetch_array(sql_query("SELECT MIN(tol) as minrendeles,MAX(ig) as maxrendeles,MAX(potig) as maxpotigrendeles 
                                                    FROM orvos_beosztas_new b
                                                    WHERE helyszinid=? and (instr(b.beocegek, ?) or b.beocegek='') and (instr(tipusok, ?) and b.orvosid=?) and aktiv=1 
                                                      AND (b.nap=? or (b.nap=10 and b.beonap=?)) 
                                                      AND (b.hetek=0 OR (WEEK(?,3)%2=0 AND b.hetek=2) OR (WEEK(?,3)%2=1 AND b.hetek=1)) 
                                                      {$orvosRestrict} HAVING MAX(tol) IS NOT NULL",
            [$helyszinId, "|{$cegId}|", "|{$szuresTipus}|", $orvosId, $wd, $nap, $nap, $nap]));


        //WHERE helyszinid=? and cegid=? and (instr(tipusok, '|{$szuresTipus}|') and b.orvosid=?) and aktiv=1 and (b.nap=? or (b.nap=10 and b.beonap=?)) AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2) OR (WEEK('{$nap}',3)%2=1 AND b.hetek=1)) {$orvosRestrict} HAVING MAX(tol) IS NOT NULL",


        if ($minMaxData["maxpotigrendeles"] > $minMaxData["maxrendeles"]) {
            $minMaxData["maxrendeles"] = $minMaxData["maxpotigrendeles"];
        }
        return $minMaxData;
    }

    private function getMinMaxPack($szuresTipus, $orvosId, $nap) {
        return sql_fetch_array(sql_query("SELECT MIN(tol) as minrendeles,MAX(ig) as maxrendeles 
        FROM orvos_beosztas_new b
        WHERE helyszinid=? and (instr(b.beocegek, ?) or b.beocegek='') and orvosid=? and instr(b.tipusok, ?) and aktiv=1
        AND (nap=WEEKDAY('{$nap}')+1 or beonap='{$nap}')  
		AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2)) 
        HAVING MAX(tol) IS NOT NULL", array($this->helyszin, "|{$_SESSION["helyszindata"]["id"]}|", $orvosId, "|{$szuresTipus}|")));
    }

    public function getPackageAvailabilityForDay($day)
    {
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
            if ($beos = $this->getBeosztasok("{$day}", $this->helyszin, $packTypeId)) {
                foreach ($beos as &$beoData) {
                    $orvosId     = $beoData["orvosid"];
                    $orvosNev    = $beoData["orvosnev"];
                    $interval    = $beoData["binterval"];
                    $step        = 0;
                    $beoMinMax   = $this->getMinMaxPack($packTypeId, $orvosId, $day);
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
                            $timeTableForPackage[$packTypeId] = ["idopont" => "{$day} {$ora}", "interval" => $interval, "orvosid" => $orvosId, "orvosnev" => $orvosNev, "tipusnev" => $this->szuresTipusMap[$packTypeId]["megnev"]];
                            break 2;
                        }
                    }
                }
            }

            if (!isset($timeTableForPackage[$packTypeId]) && $packTypeId == $this->szuresTipus) {
                $error = "Erre a napra elfogytak az időpontok!";
                $vanFixError = true;
            }

            if (!isset($timeTableForPackage[$packTypeId]) && !$vanFixError) {
                $text = "nincs időpont:<br/>";
                if (substr_count($error, $text) == 0) {
                    $error .= $text;
                }
                $error .= "{$this->szuresTipusMap[$packTypeId]["megnev"]}<br/>";
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
        $orvosRestrict = "";
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

        //Ha a BP cég az aktuális cég:
        /*if($_SESSION["helyszindata"]["id"]==74){
            //Ha a fantom helyszínt választják ki:
            
            //Ha a jász utcát:
            if($helyszin==1){
                $orvosRestrict=" AND b.orvosid NOT IN(282,285)";
                if(!empty($orvos)){
                    $orvos="";
                }
            }

            if($helyszin==98989898989898){
                $orvosRestrict=" AND b.orvosid IN(282,285)";
                $helyszin=1;
                if(!empty($orvos)){
                    $orvos="";
                }
            }
        }*/


        //időpontra beosztott orvosok kiolvasása
        $resb = sql_query("SELECT * FROM orvos_beosztas_new b 
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		WHERE b.`helyszinid`=? and (instr(b.beocegek, ?) or b.beocegek='') AND (nap=WEEKDAY(?)+1 or beonap=?) AND TIME(tol)<=TIME(?) AND TIME(IF(potig<>'',potig,ig))>TIME(?) AND INSTR(b.tipusok,?) " . ($orvos == 0 ? "" : "and b.orvosid='{$orvos}'") . " and b.aktiv=1 {$orvosRestrict}
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
        $orvosRestrict = "";
        $helyszin = $this->helyszin;
        $wd  = date("N", strtotime($day));

        /*if($_SESSION["helyszindata"]["id"]==74){
            //Ha a fantom helyszínt választják ki:

            //Ha a jász utcát:
            if ($helyszin == 1) {
                $orvosRestrict = " AND b.orvosid NOT IN(282,285)";
            }

            if($helyszin==98989898989898){
                $orvosRestrict = " AND b.orvosid IN(282,285)";
                $helyszin = 1;
            }
        }*/

        $orvosAvailable = [];
        $res = sql_query("select b.* from orvos_beosztas_new b 
                                left join orvosok o on o.id=b.orvosid 
                                where b.helyszinid=? 
                                and instr(b.tipusok,?) 
                                and	(instr(b.beocegek, ?) or b.beocegek='') 
                                and (b.nap=? or (b.nap=10 and b.beonap=?)) 
                                and b.noreservation=0
                                and b.aktiv=1 
                                and o.aktiv=1 {$orvosRestrict}", [$helyszin, "|{$this->szuresTipus}|", "|{$_SESSION['helyszindata']['id']}|", $wd, $day]);

        while ($beoData = sql_fetch_array($res)) {
            if (Booking_Constants::SQL_DB == "keltexmed" && $beoData["orvosid"] == 403) {
                //skip dr. megyeri márta - keltexmed temp
                continue;
            }

            $orvosAvailable[] = $beoData["orvosid"];
        }

        return array_unique($orvosAvailable);
    }

    private function getPackContentTypes($szuresTipusId)
    {
        $types = [];
        if ($this->szuresTipusData["ispack"] == 1) {
            $res = sql_query("select * from szurescsomagok_kapcs where csomagid=? and noreservation=0", array($szuresTipusId));
            while ($row = sql_fetch_array($res)) {
                if ($row["nemerequired"] == 0 || $row["nemerequired"] == $this->neme) {
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


    public function getBeosztasok($idoPont, $helyszin, $szuresTipus, $orvos = 0)
    {
        $nap         = substr($idoPont, 0, 10);
        $ora         = substr($idoPont, 11, 5);
        $helyszin    = intval($helyszin);
        $szuresTipus = intval($szuresTipus);
        $cegId       = $_SESSION["helyszindata"]["id"];
        if (isset($_SESSION["helyszinceg"]) && isset($GLOBALS["admin"])) {
            $cegId = $_SESSION["helyszinceg"];
        }

        $wora = $wceg = $orvosRestrict = "";
        if (!empty($ora)) {
            $wora = "AND TIME(tol)<=TIME('{$ora}') AND TIME(IF(potig='', ig, potig))>TIME('{$ora}')";
        }

        //admin esetén lazább szűrés
        if (isset($GLOBALS["admin"])) {
            if (!$this->adminUser->allCegJog()) {
                $wceg = "and (instr(b.beocegek, '|{$cegId}|') or b.beocegek='')";
            }
        } else {
            $wceg = "and (instr(b.beocegek, '|{$cegId}|') or b.beocegek='')";
        }
        
        //Ha a BP cég az aktuális cég:
        /*if($_SESSION["helyszindata"]["id"]==74){
            //Ha a fantom helyszínt választják ki:
            
            //Ha a jász utcát:
            if($helyszin==1){
                $orvosRestrict=" AND orvosid NOT IN(282,285)";
            }

            if($helyszin==98989898989898){
                $orvosRestrict=" AND orvosid IN(282,285)";
                $helyszin=1;
            }
        }*/

        //időpontra beosztott orvosok kiolvasása
        $resb = sql_query("SELECT 
        IF(potig<>'' and TIME('{$ora}')>=TIME(ig),1,0) as ispotig, 
        b.*,o.id as orvosid,o.nev as orvosnev,o.onlytel,c.megnev as cegnev 
        FROM orvos_beosztas_new b 
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		LEFT JOIN cegek c ON c.id=b.cegid
		WHERE b.`helyszinid`='{$helyszin}' {$wceg} AND (nap=WEEKDAY('{$nap}')+1 or beonap='{$nap}') {$wora} AND INSTR(b.tipusok,'|{$szuresTipus}|') 
		AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2) OR (WEEK('{$nap}',3)%2=1 AND b.hetek=1)) and b.aktiv=1 and o.aktiv=1 {$orvosRestrict}
        ORDER BY o.nev, o.onlytel");

        while ($rowb = sql_fetch_array($resb)) {
            if (isset($GLOBALS["admin"]) || !sql_fetch_array(sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap=? AND nap>=DATE(NOW()) and (szurestipusid=? or instr(szurestipusid,'|{$szuresTipus}|'))", array($helyszin, $cegId, $nap, $szuresTipus)))) {
                if ($rowb["orvosid"] == $orvos || $orvos == 0) {
                    $beos[] = $rowb;
                }
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

    public function getAllReservationForDay($day, $helyszinId=0) {
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
            $return[$reservationData["szurestipusid"]][$reservationData["id"]] = $reservationData;
        }
        return $return;
    }

    public function getTipusMegj($cegid, $tid, $helyszinId = 1) {
        $h = "";
        if ($row = sql_fetch_array(sql_query("select * from szurestipusok_megj where (cegid=? or cegid=0) and tipusid=? and csomag=0", [$cegid, $tid]))) {
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

        if ($helyszinId == 1 && $_SERVER["REMOTE_ADDR"] == "88.151.97.121") {
            $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and trim(megnev)<>'' and csomag=0", array("|{$cegid}|", $tid));
            if (sql_num_rows($res) > 0) {
                $h .= "<div style='margin:10px 0px;'>";
                $h .= "<div style='font-weight:bold;'>Ha kér, válasszon kiegészítő szolgáltatást:</div>";
                while ($row = sql_fetch_array($res)) {
                    //if ($_COOKIE["lang"]!="hu" && trim($row["megnev_{$_COOKIE["lang"]}"])!="") $row["megnev"]=$row["megnev_{$_COOKIE["lang"]}"];
                    $h .= "<div><input type='checkbox' name='altipus{$row["id"]}' value='1' " . (isset($_POST["altipus{$row["id"]}"]) ? "checked" : "") . " /> {$row["megnev"]}</div>";
                }
                $h .= "</div>";
            }
        }
        if ($_SESSION['helyszindata']['tudoszuroopcio'] == 1 && $tid == 1) {
            $h .= "<div><input type='checkbox' name = 'tudoszuro' value = '1' ".(isset($_POST["tudoszuro"])?"checked":"")."/>Tüdőszűrővel nem rendelkezik</div>";
        }
        return $h;
    }

    public function checkIdopontSzabad($data)
    {
        //TODO: időpont szabadság vizsgálása még kell ide..
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

    public function deleteReservation($id, $code, $force = false)
    {
        if ($force) {
            $res = sql_query("select id, orvosassigned, helyszinid, pass from foglalasok WHERE id=? and (pass=? or rkod=?) and aktiv=0", array($id, $code, $code));
        } else {
            $res = sql_query("select id, orvosassigned, helyszinid, pass from foglalasok WHERE id=? and (pass=? or rkod=?) and (datum>now() or aktiv=0) and eljott=0", array($id, $code, $code));
        }
        if ($row = sql_fetch_array($res)) {
            $foService = new FoglaljOrvostService();
            $foService->deleteReservation($row["id"]);

            $api = new BookingSyncApi();
            $api->deleteReservation($row);

            if (in_array($row["orvosassigned"], [11111111111, 1111111111])) {
                $notificationService = new NotificationService();
                $notificationService->deleteMessage($row["id"]);
            }

            sql_query("update beutalok set foglalasid='0' where foglalasid=?", array($row["id"]));
            sql_query("delete from foglalasok WHERE id=?", array($row["id"]));
            sql_query("delete from foglalasok WHERE parentid=? and parentid<>0", array($row["id"]));
            sql_query("delete from fizkapcs where fid=?", array($row["id"]));
        }
    }

    public function addReservation($data)
    {
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

        $this->newReservationId=$fid;

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
        $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and csomag=0", array("|{$_SESSION["helyszindata"]["id"]}|", $data["szurestipus"]));
        while ($row = sql_fetch_array($res)) {
            if (isset($data["altipus{$row["id"]}"])) {
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
        if (!isset($data["betegallomanynyilatkozat"])) $data["betegallomanynyilatkozat"] = 0;
        if (!isset($data["parentid"])) $data["parentid"] = 0;
        if (!isset($data["externalid"])) $data["externalid"] = "";
        if (!isset($data["pass"])) $data["pass"] = "";

        if (!isset($data["questions"])) $data["questions"] = "";
        if (!isset($data["simplepay"])) $data["simplepay"] = 0;
        if (!isset($data["noreservation"])) $data["noreservation"] = 0;
        if (!isset($data["totalprice"])) $data["totalprice"] = 0;
        if (!isset($data["exportdata"])) $data["exportdata"] = "";
        if (!isset($data["currency"])) $data["currency"] = 0;
        if (!isset($data["lang"])) $data["lang"] = "hu";
        if (!isset($data["rn"])) $data["rn"] = rand(1000000, 9999999);

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
			exportdata=?,
			externalid=?,
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
                $data["exportdata"],
                $data["externalid"],
                $data["pass"]
            )
        );

        $fid = sql_insert_id();
        $this->updateFoglalasData($fid);

        return $fid;
    }


    private $reservedTimeId = 0;
    private $newAddTime = null;
    private $copyReservationData = [];
    private $copy = false;

    public function addIdoPont()
    {
        //ide már csak orvosid paraméterrel érkezhet hívás!
        //input:
        //$_GET["orvosid"]
        //$_GET["szt"]
        //$_GET["addidopont"]
        //$_GET["rinterval"]

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

            $errorMsg = "Orvos nem elérhető!";

            if ($orvosData = sql_fetch_array(sql_query("select * from orvosok where id=? and aktiv=1", [$orvosId]))) {
                if ($orvosData["onlytel"] == 1) {
                    //die("errorEz az orvos csak a telefonjára fogad foglalást!");
                }
                if ($orvosData["externalonly"] == 1) {
                    die("errorEhhez az orvoshoz a recepció nem rögzíthet foglalást!");
                }
            }

            if ($orvosId == 117) {
                //managerszűrés korlátlan
                $selectedOrvosId = $orvosId;
            }
            if ($this->orvosIdopontIsFree($_GET["addidopont"], $orvosId, $_GET["rinterval"])) {
                $selectedOrvosId = $orvosId;
            }

            if (!isset($selectedOrvosId)) {
                die("error{$errorMsg}");
            }

            sql_query("insert into foglalasok set aktiv=1,foglalta=?,regdatum=now(),nev='nincs név',cegid=?,helyszinid=?,szurestipusid=?,orvosassigned=?,datum=?", array($this->adminUser->user["username"], $cegId, $_SESSION["helyszin"], $szuresTipusId, $selectedOrvosId, $_GET["addidopont"]));
            $fid = sql_insert_id();

            if (!empty($this->copyReservationData)) {
                sql_query("update foglalasok set regdatum=now(), cegid=?, paciensid=?, nev=?, email=?, telefon=?, szuldatum=?, szulhely=?, anyjaneve=?, neme=?, taj=?, irsz=?, varos=?, utca=?, munkaltato=?, munkakor=?, rkod=?, megj=?, alkalmassag=?, alkalmassagido=?, alkalmassagikhet=?, tudoszuroervenyesseg=?, tudoszuro=?, smssent=1 where id=?",
                    [$this->copyReservationData["cegid"], $this->copyReservationData["paciensid"], $this->copyReservationData["nev"], $this->copyReservationData["email"], $this->copyReservationData["telefon"], $this->copyReservationData["szuldatum"], $this->copyReservationData["szulhely"], $this->copyReservationData["anyjaneve"], $this->copyReservationData["neme"], $this->copyReservationData["taj"], $this->copyReservationData["irsz"], $this->copyReservationData["varos"], $this->copyReservationData["utca"], $this->copyReservationData["munkaltato"], $this->copyReservationData["munkakor"], rand(11000,98000), $this->copyReservationData["megj"], $this->copyReservationData["alkalmassag"], $this->copyReservationData["alkalmassagido"], $this->copyReservationData["alkalmassagikhet"], $this->copyReservationData["tudoszuroervenyesseg"], $this->copyReservationData["tudoszuro"], $fid]);
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
            logActivity("foglalas", $rowf["id"], "{$rowf["nev"]} foglalás törlése {$rowf["datum"]}", json_encode($rowf, JSON_PRETTY_PRINT));
            $this->deleteReservation($id, $code);
        }
    }

    public function tappenzCheckHTML($val) {
        $this->lang = new Lang();
        $webText = $this->lang->webText;
        $html = "";
        if ($this->checkBookingRestrictionProtocol($val)) {
            $html.= "<input type='checkbox' id='betegallomanynyilatkozat' value='1' name='betegallomanynyilatkozat'>";
            $html.= "<span style='cursor:pointer' onClick='toggleCheckBox(\"#betegallomanynyilatkozat\");'><strong>".$webText["betegallomanynyilatkozat"]."</strong></span>";
        }
        return $html;
    }

    public function getPublicServices($helyszinId) {
        $docAgent = new DocAgent();
        $docAgent->showDefaultAsset = true;

        $rest = sql_query("SELECT b.* FROM orvos_beosztas_new b
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
            $services[] = $tipusData;
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

    public function getInfoPageText($szurestipusid, $inputData = null){
        $checkboxes = ["kisrutin", "nagyrutin", "pajzsmirigy", "noi-tumormarker", "ferfi-tumormarker", "egyeb-labor"];

        $data = sql_fetch_array(sql_query("SELECT infopagetext FROM szurestipusok WHERE id=?",array($szurestipusid)));
        $text = $data["infopagetext"];

        foreach ($checkboxes as $checkbox) {
           if (isset($inputData[$checkbox])) {
               $text = str_replace("id='{$checkbox}'", "id='{$checkbox}' checked ", $text);
           }
        }

        $tipusData = sql_query("select * from szurestipusok t where t.id=?", [$szurestipusid])->fetch(PDO::FETCH_ASSOC);
        if ($tipusData["ispack"] == 1) {
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


        return $text;
    }

    public function isOnlineTipus($tipusId):bool {
        if ($tipusData = sql_fetch_array(sql_query("select webdoktor, simplepayaktiv, onlysimplepay from szurestipusok where id=?", [$tipusId]))) {
            if ($tipusData["webdoktor"] == 1 && $tipusData["simplepayaktiv"] == 1 && $this->getPriceData($tipusId)) {
                return true;
            }
        }
        return false;
    }

    public function getPriceData($tipusId) {
        return sql_fetch_array(sql_query("SELECT * FROM arak WHERE tipusid=? AND cegid LIKE '%|{$_SESSION['helyszindata']['id']}|%' ", [$tipusId]));
    }

    public function replicateReservationToAnotherService($reservationData, $tipusId):string {
        $status = "";
        if (sql_fetch_array(sql_query("select * from foglalasok where nev=? and taj=? and datum>? and datum<? and szurestipusid=?", [$reservationData["nev"], $reservationData["taj"], date("Y-m-d 00:00:00", strtotime($reservationData["datum"])), date("Y-m-d 23:59:59", strtotime($reservationData["datum"])), $tipusId]))) {
            return $status;
        }

        $date = date("Y-m-d", strtotime($reservationData["datum"]));
        $foundTimes = [];
        $binterval = 0;
        $orvosId = 0;
        $weekDay = date("N", strtotime($reservationData["datum"]));
        $beoRes = sql_query("SELECT tol, ig, orvosid, binterval FROM orvos_beosztas_new b WHERE INSTR(b.tipusok, ?) AND b.nap=?", ["|{$tipusId}|", $weekDay]);
        while ($beoData = sql_fetch_array($beoRes)) {
            $binterval = $beoData["binterval"];
            $orvosId = $beoData["orvosid"];

            $o = 0;
            $startTime = date("Y-m-d H:i:s", strtotime("{$date} {$beoData["tol"]}"));
            $lastTime = date("Y-m-d H:i:s", strtotime("{$date} {$beoData["ig"]}"));
            while (true) {
                $addMinute = $o * $beoData["binterval"];
                $checkTime = date("Y-m-d H:i:s", strtotime("{$startTime} + {$addMinute} minute"));

                if (!sql_fetch_array(sql_query("select * from foglalasok where datum=? and szurestipusid=?", [$checkTime, $tipusId]))) {
                    $foundTimes[] = $checkTime;
                }

                $o++;
                if (strtotime($checkTime) >= strtotime($lastTime) || $o > 100) {
                    break;
                }
            }
        }

        if (!empty($foundTimes)) {
            $diff = 1000000;
            $optimalTime = "";

            foreach ($foundTimes as $foundTime) {
                $checkDiff = abs(strtotime($reservationData["datum"]) - strtotime($foundTime));
                if ($checkDiff < $diff) {
                    $diff = $checkDiff;
                    $optimalTime = $foundTime;
                }
            }

            $reservationData["datum"] = $optimalTime;
            $reservationData["rinterval"] = $binterval;
            $reservationData["orvosid"] = $orvosId;
            $reservationData["szurestipus"] = $tipusId;
            $reservationData["tudoszuro"] = 0;
            $reservationData["helyszin"] = $reservationData["helyszinid"];
            $newReservationId = $this->addReservationQuery($reservationData);

            //Foglaljorvost.hu-nak átküldés
            //$foService = new FoglaljOrvostService();
            //$foService->newReservation($newReservationId);

            //$api = new BookingSyncApi();
            //$api->newReservation($newReservationId);

            $status = "Tüdőszűrő időpont foglalva: {$checkTime}";

        }

        return $status;
    }

}
