<?php

class AdminBookingPage extends AdminCorePage
{

    private $bookingService;

    private $setDay;

    public function __construct()
    {
        parent::__construct();
        $this->bookingService = new BookingService();

        if (!isset($_SESSION["helyszin"])) $_SESSION["helyszin"] = 0;
        if (!isset($_SESSION["helyszinceg"])) $_SESSION["helyszinceg"] = 0;
        if (!isset($_SESSION["naptarszurestipus"])) $_SESSION["naptarszurestipus"] = 0;
        if (!isset($_SESSION["ecegfilter"])) $_SESSION["ecegfilter"] = 0;
        if (!isset($_SESSION["setday"])) $_SESSION["setday"] = date("Y-m-d");
        if (isset($_GET["setday"])) $_SESSION["setday"] = $_GET["setday"];
        if (isset($_GET["ecegfilter"])) $_SESSION["ecegfilter"] = $_GET["ecegfilter"];

        $this->setDay = $_SESSION["setday"];

        if (isset($_GET["sethelyszin2"])) {
            $s = explode("-",$_GET["sethelyszin2"]);
            $_SESSION["helyszin"]    = $s[0];
            $_SESSION["helyszinceg"] = $s[1];
            header("location:index.php?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["szabira"])) {
            sql_query("insert into szabadsag set oid=?,datumtol=?,datumig=?",array($_GET["orvosid"],$_GET["szabira"],$_GET["szabira"]));

            $rowo = sql_fetch_array(sql_query("select * from orvosok where id=?",array($_GET["orvosid"])));
            logActivity("orvos",$rowo["id"],"{$rowo["nev"]} szabira küldés link {$_GET["szabira"]}","");

            header("location:{$_SERVER['PHP_SELF']}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["showelojegyzestable"])) {
            if (isset($_GET["day"])) $_SESSION["setday"] = $_GET["day"];
            echo $this->showElojegyzesTable($_SESSION["setday"]);
            die();
        }

        if (isset($_GET["addidopont"])) {
            $this->bookingService->addIdoPont();

            if (isset($_SESSION["setday"])) {
                echo $this->showElojegyzesTable($_SESSION["setday"]);
            }
            die();
        }

        if (isset($_GET["removeidopont"])) {
            $this->bookingService->removeIdopont($_GET["removeidopont"], $_GET["p"]);
            echo $this->showElojegyzesTable($_SESSION["setday"]);
            die();
        }

        if (isset($_GET["moveidopont"])) {
            $this->bookingService->moveIdopont();

            if (isset($_SESSION["setday"])) {
                echo $this->showElojegyzesTable($_SESSION["setday"]);
            }
            die();
        }

        if (isset($_POST["addreplacedoctor"])) {
            $nap                 = $_POST["nap"];
            $helyszinId          = intval($_POST["helyszin"]);
            $szuresTipusId       = intval($_POST["szt"]);
            $oid                 = intval($_POST["sourceoid"]);
            $helyettesitoOrvosId = $_POST["helyettesitoorvosid"];
            $orvosMegj           = $_POST["orvosMegj"];
            $return              = ["error" => "", "html" => ""];

            sql_query("insert into helyettesites set nap=?, oid=?, helyettesitoorvosid=?, helyszinid=?, tipusid=?, megj=?", [$nap, $oid, $helyettesitoOrvosId, $helyszinId, $szuresTipusId, $orvosMegj]);

            $return["html"] = $this->showElojegyzesTable($_SESSION["setday"]);
            $this->utils->jsonOut($return);
        }

        if (isset($_GET["removereplacedoctor"])) {
            $orvosId = intval($_GET["oid"]);
            $nap     = $_GET["nap"];
            $return  = ["error" => "", "html" => ""];

            sql_query("delete from helyettesites where oid=? and nap=?", [$orvosId, $nap]);

            $return["html"] = $this->showElojegyzesTable($_SESSION["setday"]);
            $this->utils->jsonOut($return);
        }

        if (isset($_POST["addtempdoctor"])) {
            $nap           = $_POST["nap"];
            $helyszinId    = intval($_POST["helyszin"]);
            $szuresTipusId = intval($_POST["szt"]);
            $sourceOrvosId = intval($_POST["sourceoid"]);
            $weekDay       = date("N", strtotime($nap));
            $orvosNev      = $_POST["orvosNev"];
            $orvosMegj     = $_POST["orvosMegj"];
            $orvosTol      = $_POST["orvosTol"];
            $orvosIg       = $_POST["orvosIg"];
            $orvosInterval = $_POST["orvosInterval"];
            $return        = ["error" => "", "html" => "", "newOrvosId" => 0];

            if ($beoData = $this->bookingService->beosztasService->getBeosztasDataForDoctor($sourceOrvosId, $nap, $helyszinId, $szuresTipusId)) {
                sql_query("insert into orvosok set nev=?, description=?, aktiv=1, pecsetszam='temp', created=now(), createdby=?", [$orvosNev, $orvosMegj, $this->adminUser->user["nev"]]);
                $return["newOrvosId"] = $orvosId = sql_insert_id();

                sql_query("insert into orvos_beosztas_new set orvosid=?, helyszinid=?, nap=10, beonap=?, tol=?, ig=?, binterval=?, tipusok=?, aktiv=1, beocegek='|0|'", [$orvosId, $helyszinId, $nap, $orvosTol, $orvosIg, $orvosInterval, "|{$szuresTipusId}|"]);
            } else {
                $return["error"] = "Az orvos hozzáadása közben hiba történt!";
            }

            $return["html"] = $this->showElojegyzesTable($_SESSION["setday"]);
            $this->utils->jsonOut($return);
        }

        if (isset($_POST["savetempdoctor"])) {
            $oid           = intval($_POST["oid"]);
            $orvosNev      = $_POST["orvosNev"];
            $orvosMegj     = $_POST["orvosMegj"];
            $orvosTol      = $_POST["orvosTol"];
            $orvosIg       = $_POST["orvosIg"];
            $return        = ["error" => "", "html" => ""];

            sql_query("update orvosok set nev=?, description=? where id=?", [$orvosNev, $orvosMegj, $oid]);
            sql_query("update orvos_beosztas_new set tol=?, ig=? where orvosid=? limit 1", [$orvosTol, $orvosIg, $oid]);

            $return["html"] = $this->showElojegyzesTable($_SESSION["setday"]);
            $this->utils->jsonOut($return);
        }


        if (isset($_GET["removetempdoctor"])) {
            $tol     = date("Y-m-d 00:00:00", strtotime($_GET["nap"]));
            $ig      = date("Y-m-d 23:59:59", strtotime($_GET["nap"]));
            $orvosId = intval($_GET["oid"]);
            $return  = ["error" => "", "html" => ""];

            if (!$reservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE orvosassigned=? AND datum>? and datum<?", [$orvosId, $tol, $ig]))) {
                sql_query("delete from orvosok where id=?", [$orvosId]);
                sql_query("delete from orvos_beosztas_new where orvosid=?", [$orvosId]);
            } else {
                $return["error"] = "Az ideiglenes orvoshoz kapcsolódik foglalás, nem törölhető!";
            }

            $return["html"] = $this->showElojegyzesTable($_SESSION["setday"]);
            $this->utils->jsonOut($return);
        }

        if (isset($_POST["foReservationInfo"])) {
            if ($foglalasData = sql_fetch_array(sql_query("select f.*,o.foid as oid, sz.fotid as tid  from foglalasok f 
            left join orvosok o on o.id = f.orvosassigned
            left join szurestipusok sz on sz.id = f.szurestipusid
            where f.id=? and f.pass=?", [$_POST["fid"], $_POST["p"]]))) {
                if ($foglalasData["fofid"] != 0) {
                    $result = "Foglaljorvost szinkron sikeres!\n\n";
                    $result.= "Foglalás azonosító: {$foglalasData["fofid"]}\n";
                } else {
                    $result = "Foglaljorvost szinkron sikertelen!\n\n";
                }
                if ($foglalasData["oid"] == 0) {
                    $result .= "Orvos nincs összekötve\n";
                } else {
                    $result .= "Orvos azonosító: {$foglalasData["oid"]}\n";
                }
                if ($foglalasData["tid"] == 0) {
                    $result.= "Tipus nincs összekötve";
                } else {
                    $result.= "Tipus azonosító: {$foglalasData["tid"]}";
                }

            } else {
                $result = "error";
            }
            $this->utils->jsonOut(["result" => $result]);
        }
    }

    public function showPage()
    {
        echo "<div id='elojegyzestable'>".$this->showElojegyzesTable($this->setDay)."</div>";
        echo "<div id='idoponteditor'></div>";
    }

    private function elojegyzesRowClosed($oid, $tipusId) {
        return isset($_SESSION["closedbeotable"]["{$oid}_{$tipusId}"]);
    }


    public function showElojegyzesTable($setDay) {
        $settings      = new Booking_Settings();
        $htmlout       = "";
        $cimFilterHTML = $this->cimFilter();
        $cegFilterHTML = $this->cegFilter();
        $tipusLinks[0] = ["url" => "javascript:scrollTo(\"filterbox\");", "nev" => "Oldal teteje"];
        $rendelesek    = 0;
        $helyszin      = intval($_SESSION["helyszin"]);
        $nap           = date("Y-m-d", strtotime($setDay));
        $wd            = date("N", strtotime($setDay));
        $tipusok       = $this->bookingService->tipusExtract($this->bookingService->beosztasService->getTipusByHelyszin($helyszin));
        $foglalasok    = $this->bookingService->getAllReservationForDay($nap, $helyszin);
        $isHoliday     = in_array($nap, $settings->getMunkaszunetiNapok());
        $maxOrvosId    = sql_query("select max(id)+1 from orvosok")->fetchColumn();
        $orvosList     = sql_query("select id, nev from orvosok where aktiv=1 order by nev")->fetchAll(PDO::FETCH_ASSOC);
        $existingOrvosTimes = [];
        $emptySection  = false;
        $ExtraButtons = [];

        $htmlout.="<div id='filterbox' style='margin-top:10px;'>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'>".$this->napFilter2($setDay)."</div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'><a onclick='setListDay(\"".date("Y-m-d",strtotime("{$setDay} -1 day"))."\");return false;' href='#'><img height='20' src='images/prev.png' title='Előző nap'/></a>&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'><input type='button' onclick='setListDay(\"".date("Y-m-d")."\");' value='MA' title='Ugrás a mai napra' />&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'><a onclick='setListDay(\"".date("Y-m-d",strtotime("{$setDay} +1 day"))."\");return false;' href='#'><img height='20' src='images/next.png' title='Következő nap'/></a></div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;padding-left:20px;'>{$cimFilterHTML}</div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;padding-left:20px;'>{$cegFilterHTML}</div>";

        if (in_array($nap, $settings->getMunkaszunetiNapok())) {
            $htmlout.="<div style='margin-top:10px;padding:5px 10px;background: #f00;color:#fff;font-size:18px;display:inline-block;'>Munkaszüneti nap!</div>";
        }

        $htmlout.= "</div>";

        $htmlout .= "<div class='stickytablefilter' id='stickytablefilter'>";
        $htmlout .= "<div class='tdm' style='padding:2px 10px 0px 0px;font-size: 16px;white-space: nowrap;'>".$nap."<br/>".$this->adminUtils->settings->hetnap[$wd]."</div>";
        $htmlout .= "<div class='tdm'>#tipuslinksplace#</div>";
        $htmlout .= "</div>";

        $htmlout.= "<table width='100%' cellpadding='0' cellspacing='0' border='0'>";

        sql_query("SET SESSION group_concat_max_len = 10000");

        if (empty($tipusok)) {
            $tipusok[] = 0;
        }
        $szuresTipusok = sql_query("select * from szurestipusok where id in (".implode(",",$tipusok).") order by !instr(megnev,'üzemorvosi'), !instr(megnev,'menedzser'), megnev");
        

        while ($szuresTipus = sql_fetch_array($szuresTipusok)) {
            $this->szuresTipusActual = $szuresTipus;
            $lastOrvosId = 0;

            

            $beosztasok = $this->bookingService->beosztasService->getBookingPageBeosztasok($nap, $_SESSION["helyszin"], $szuresTipus["id"]);
            foreach ($beosztasok as $beosztas) {

                /*echo "<pre>";
                print_r($beosztas);
                echo "</pre>";*/

                

                 $rendelesek++;
                //$cegek = array_unique(explode(",", $beosztas["cegek"]));
                $minTol = "24:00";
                $maxIg = "00:00";
                $maxPotIg = "00:00";
                $orvosId = $beosztas["orvosid"];
                $binterval = $beosztas["binterval"];
                $rendeloOrvosLink = "<a target='_blank' href='{$_SERVER['PHP_SELF']}?page=doctors&szerk={$orvosId}'>{$beosztas["orvosnev"]}</a>";
                $addDoctorLink = "<a class='orvosbutton' onclick=\"$('#adddoctordiv{$orvosId}').slideDown();return false;\" href='#'>+ orvos</a>";
                $szabi = sql_fetch_array(sql_query("select * from szabadsag where datumtol<=? and datumig>=? and oid=?", [$nap, $nap, $beosztas["orvosid"]]));
                $szabiURL = $szabi ? "szabadságon" : "<a onclick='return confirm(\"Biztos beállítod szabadságra erre a napra?\");' href='{$_SERVER['PHP_SELF']}?page={$_GET["page"]}&szabira={$nap}&orvosid={$orvosId}'>szabadságra</a>";
                $helyettesites = sql_fetch_array(sql_query("select h.*, o.nev as helyettesitoorvos from helyettesites h left join orvosok o on o.id = h.helyettesitoorvosid where h.nap=? and h.oid=? and h.tipusid=?", [$nap, $beosztas["orvosid"], $szuresTipus["id"]]));
                $helyettesitesLink = "<a class='orvosbutton' onclick=\"$('#helyettesitesdiv{$orvosId}').slideDown();return false;\" href='#'>helyettesítés</a>";

                if($beosztas["extrabuttonrequired"]==1){
                    $ExtraButtons[] = array("id"=>$orvosId,"nev"=>$beosztas["orvosnev"],"free"=>1);
                }

                if (isset($helyettesites["id"])) {
                    $helyettesitesLink = "";
                }

                if ($beosztas["pecsetszam"] == "temp") {
                    $rendeloOrvosLink = "<a target='_blank' href='#' title='Orvos eltávolítása' onclick=\"$('#editdoctordiv{$orvosId}').slideDown();return false;\">- {$beosztas["orvosnev"]}</a>";
                    //$rendeloOrvosLink = "<a target='_blank' href='#' title='Orvos eltávolítása' onclick='removeTempDoctor(\"{$nap}\", {$orvosId});return false;'>- {$beosztas["orvosnev"]}</a>";
                    $addDoctorLink = "";
                }

                if (strtotime($minTol) > strtotime($beosztas["mintol"])) {
                    $minTol = $beosztas["mintol"];
                }
                if (strtotime($maxIg) < strtotime($beosztas["maxig"])) {
                    $maxIg = $beosztas["maxig"];
                }
                if (strtotime($maxPotIg) < strtotime($beosztas["maxpotig"])) {
                    $maxPotIg = $beosztas["maxpotig"];
                }

                if ($maxPotIg == "00:00") {
                    $maxPotIg = $maxIg;
                }

                $htmlout .= "<tr>";
                $htmlout .= "<td>";
                if ($lastOrvosId != $orvosId) {
                    $lastOrvosId = $orvosId;
                    $existingOrvosTimes = [];

                    $htmlout .= "<div class='etabletipushead' id='tpid{$szuresTipus["id"]}'>";

                    $htmlout .= "<div style='display:table-cell;vertical-align:middle;cursor:pointer;font-size:32px;padding:0px 10px 0px 10px;' onclick=\"toggleElojegyzesTableNaptar({$orvosId}, {$szuresTipus["id"]});\"><i id='tablenyito{$orvosId}_{$szuresTipus["id"]}' class='tablenyito fas fa-chevron-up' style='" . ($this->elojegyzesRowClosed($orvosId, $szuresTipus["id"]) ? "transform:rotate(180deg);" : "") . "'></i></div>";
                    $htmlout .= "<div style='display:table-cell;vertical-align:top;'>";
                    $htmlout .= "<div id='orvosdiv{$orvosId}' style='font-size:16px;font-weight:bold;'>{$rendeloOrvosLink}&nbsp;{$szuresTipus["megnev"]}&nbsp;&nbsp;{$addDoctorLink} {$helyettesitesLink}</div>";
                    $htmlout .= "<div>#foglalt{$orvosId}_{$szuresTipus["id"]}# #szabad{$orvosId}_{$szuresTipus["id"]}#</div>";
                    $htmlout .= "<div>{$beosztas["description"]}</div>";
                    if ($szabi) {
                        $szabiData = sql_fetch_array(sql_query("select min(datumtol) as datumtol, max(datumig) as datumig from szabadsag where groupid=?", [$szabi["groupid"]]));
                        $htmlout .= "<div style='padding:2px 0px;'><span style='color:#fff;background:#f00;padding:2px 5px;'>Szabadságon {$szabiData["datumtol"]} - {$szabiData["datumig"]}</span></div>";
                    }

                    if ($beosztas["onlytel"] == 1) {
                        $htmlout .= "<div style='padding:2px 0px;'><span style='color:#fff;background:#f00;padding:2px 5px;'>Ez az orvos csak a telefonjára fogad foglalást!</span></div>";
                    }

                    $orvosOptions = "Válassz helyettesítő orvost!";
                    foreach ($orvosList as $orvos) {
                        $orvosOptions .= "<option value='{$orvos["id"]}'>{$orvos["nev"]}</option>";
                    }

                    $htmlout .= "<div id='helyettesitesdiv{$orvosId}' style='display:none;margin:10px 0px;padding:10px 0px;border-top:1px solid #888;border-bottom:1px solid #888;'>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'>Helyettesítő orvos:</div><div class='tdm' style='padding:2px 0px;'><select id='helyettesitoorvosid{$orvosId}'>{$orvosOptions}</select></div></div>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'>Megjegyzés: </div><div class='tdm' style='padding:2px 0px;'><input type='text' id='orvosmegj{$orvosId}' style='width:300px;'/></div></div>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'></div><div class='tdm' style='padding:2px 0px;'><input onclick=\"addReplaceDoctor('{$nap}', {$helyszin}, {$szuresTipus["id"]}, {$orvosId});\" type='button' value='Helyettesítés megadása' /> <input onclick=\"$('#helyettesitesdiv{$orvosId}').slideUp()\" type='button' value='mégsem' /></div></div>";
                    $htmlout .= "</div>";

                    if (isset($helyettesites["id"])) {
                        $htmlout .= "<div style='padding:4px 0px;font-size: 14px;'><span style='color:#000;background:#ff8;padding:2px 0px;'>Helyettesítő: {$helyettesites["helyettesitoorvos"]} <span style='color:#888;'>{$helyettesites["megj"]}</span> <a title='helyettesítés törlése' href='#' onclick=\"removeReplaceDoctor('{$nap}', {$orvosId});return false;\"><i class='fas fa-times-circle'></i></a></span></div>";
                    }

                    $htmlout .= "<div id='adddoctordiv{$orvosId}' style='display:none;margin:10px 0px;padding:10px 0px;border-top:1px solid #888;border-bottom:1px solid #888;'>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'>Adj nevet az orvosnak:</div><div class='tdm' style='padding:2px 0px;'><input type='text' id='orvosnev{$orvosId}' value='TempOrvos{$maxOrvosId}'/></div></div>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'>Megjegyzés: </div><div class='tdm' style='padding:2px 0px;'><input type='text' id='orvosmegj{$orvosId}' style='width:300px;'/></div></div>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'>Rendelési idő: </div><div class='tdm' style='padding:2px 0px;'>" . $this->rendIdoSelect("orvostol{$orvosId}", $minTol) . " - " . $this->rendIdoSelect("orvosig{$orvosId}", $maxIg) . "&nbsp;&nbsp;időtartam: " . $this->rendIntervalSelect("orvosinterval{$orvosId}", $binterval) . "</div></div>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'></div><div class='tdm' style='padding:2px 0px;'><input onclick=\"addTempDoctor('{$nap}', {$helyszin}, {$szuresTipus["id"]}, {$orvosId});\" type='button' value='Orvos hozzáadása' /> <input onclick=\"$('#adddoctordiv{$orvosId}').slideUp()\" type='button' value='mégsem' /></div></div>";
                    $htmlout .= "</div>";

                    $htmlout .= "<div id='editdoctordiv{$orvosId}' style='display:none;margin:10px 0px;padding:10px 0px;border-top:1px solid #888;border-bottom:1px solid #888;'>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'>Név:</div><div class='tdm' style='padding:2px 0px;'><input type='text' id='editorvosnev{$orvosId}' value='{$beosztas["orvosnev"]}'/></div></div>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'>Megjegyzés: </div><div class='tdm' style='padding:2px 0px;'><input type='text' id='editorvosmegj{$orvosId}' style='width:300px;' value='{$beosztas["orvosdescription"]}' /></div></div>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'>Rendelési idő: </div><div class='tdm' style='padding:2px 0px;'>" . $this->rendIdoSelect("editorvostol{$orvosId}", $beosztas["tol"]) . " - " . $this->rendIdoSelect("editorvosig{$orvosId}", $beosztas["ig"]) . "</div></div>";
                    $htmlout .= "<div style='display:table-row;'><div class='tdm'></div><div class='tdm' style='padding:2px 0px;'><input onclick='saveTempDoctor({$orvosId});' type='button' value='Mentés' /> <input onclick=\"removeTempDoctor('{$nap}', {$orvosId});\" type='button' value='Orvos törlése' /> <input onclick=\"$('#editdoctordiv{$orvosId}').slideUp()\" type='button' value='mégsem' /></div></div>";
                    $htmlout .= "</div>";

                    //if (isset($cegek[0]) && !empty($cegek[0])) {
                    //    $htmlout .= "<div style=''><a onclick='$(\"#beocegek{$rendelesek}\").slideToggle();return false;' href='#'>" . count($cegek) . " cég</a></div>";
                    //    $htmlout .= "<div id='beocegek{$rendelesek}' style='" . (count($cegek) > 0 ? "display:none;" : "") . "font-size:10px;color:#888;'>" . implode(", ", $cegek) . "</div>";
                    //}
                    $htmlout .= "</div>";
                    $htmlout .= "</div>";
                }


                $freeCounter = $timeCounter = 0;

                if ($minTol != "24:00") {
                    $htmlout .= "<div class='beotable{$orvosId}_{$szuresTipus["id"]}' style='".($this->elojegyzesRowClosed($orvosId, $szuresTipus["id"])?"display:none;":"")."'>";

                    if (!empty($existingOrvosTimes) && !$emptySection) {
                        $emptySection = true;
                        $htmlout.="<div style='border-top:1px solid #ccc;marign-top:3px;padding-top:3px;width:100%;'></div>";
                    }

                    $beoComment = trim($beosztas["bmegj"]);
                    if (!empty($beoComment)) {
                        $beoComment.= " ({$minTol} - {$maxIg})";
                        $htmlout.= "<div style='margin:5px 0px;padding:2px 5px;background: red;color:#fff;display: inline-block;'>{$beoComment}</div>";
                    }

                    $htmlout .= "<table cellpadding='0' cellspacing='0'>";
                    for ($o = 0; $o < 3600; $o += $binterval) {
                        $ora = date("H:i", strtotime("{$minTol}:00 +{$o} minute"));
                        if (strtotime($maxPotIg) <= strtotime($ora)) {
                            break;
                        }

                        if (in_array($ora, $existingOrvosTimes)) {
                            continue;
                        }
                        $existingOrvosTimes[] = $ora;
                        $emptySection = false;

                        $this->potIdopont = strtotime($ora) >= strtotime($maxIg);

                        $timeFrom = "{$nap} {$ora}:00";
                        $timeTo = date("Y-m-d H:i:s", strtotime("{$timeFrom} + {$binterval} minute"));

                        $this->addIdopontJavaScript = "setSelectedOrvos({$beosztas["orvosid"]});setSelectedInterval({$binterval});addIdopont(\"{$nap} {$ora}\", {$szuresTipus["id"]});return false;";
                        if ($isHoliday) {
                            $this->addIdopontJavaScript = "if (confirm(\"Ez munkaszüneti nap, biztos foglalsz?\")) { {$this->addIdopontJavaScript} } return false;";
                        }

                        $reservations = sql_query("select f.*, c.megnev as cegnev, o.nev as orvosnev, d.id as docid, sz.megnev as szurestipusnev from foglalasok f 
                        left join cegek c on c.id=f.cegid
                        left join szurestipusok sz on sz.id=f.szurestipusid
                        left join orvosok o on o.id=f.orvosassigned
                        left join dokumentumok d on d.foglalasid=f.id
                        where f.datum>=? and f.datum<? and (f.helyszinid=? or sz.webdoktor=1) ".(in_array($szuresTipus["id"], [6, 34, 35])?" and f.szurestipusid='{$szuresTipus["id"]}'":"")." and f.orvosassigned in (0, ?) 
                        group by f.id order by f.datum", [$timeFrom, $timeTo, $_SESSION["helyszin"], $orvosId])->fetchAll(PDO::FETCH_ASSOC);

                        $this->lastIdopont = "";
                        $this->foglalasButtonVolt = 0;
                        foreach ($reservations as $reservation) {
                            if (in_array($reservation["id"], $this->displayedReservations) && $beosztas["pecsetszam"] == "temp") {
                                continue;
                            }
                            if (isset($foglalasok[$reservation["szurestipusid"]][$reservation["id"]])) {
                                unset($foglalasok[$reservation["szurestipusid"]][$reservation["id"]]);
                            }
                            $htmlout .= $this->elojegyzesTableRow($reservation, $ora, $binterval);
                            $this->displayedReservations[] = $reservation["id"];
                        }

                        if ($this->lastIdopont == "") {
                            //nem volt foglalás, üres időpont kirakás
                            $htmlout .= "<tr style=''>";
                            $htmlout .= "<td valign='top' nowrap style='".$this->datePastStyle($nap, $ora)."'>{$ora}" . ($this->potIdopont ? " <span title='pótidőpont'>(p)</span>" : "") . "&nbsp;&nbsp;</td>";
                            $htmlout .= "<td valign='top'><a onclick='{$this->addIdopontJavaScript}' class='iconbutton' title='foglalás' href='#'><i class='fas fa-plus-square'></i></a>&nbsp;&nbsp;</td>";
                            $htmlout .= "</tr>";
                            if (!$szabi) {
                                $freeCounter++;
                            }
                        } else {
                            $timeCounter++;
                        }
                    }
                    $htmlout .= "</table>";
                    $htmlout .= "</div>";

                    $htmlout = str_replace("#szabad{$orvosId}_{$szuresTipus["id"]}#", "{$freeCounter} szabad", $htmlout);
                    $htmlout = str_replace("#foglalt{$orvosId}_{$szuresTipus["id"]}#", "{$timeCounter} foglalt, ", $htmlout);
                }

                $tipusLinks[$szuresTipus["id"]]["url"] = "javascript:scrollTo(\"tpid{$szuresTipus["id"]}\");";
                $tipusLinks[$szuresTipus["id"]]["nev"] = $szuresTipus["megnev"];
                if (!isset($tipusLinks[$szuresTipus["id"]]["free"])) {
                    $tipusLinks[$szuresTipus["id"]]["free"] = 0;
                }
                $tipusLinks[$szuresTipus["id"]]["free"] += $freeCounter;

                $htmlout .= "</td>";
                $htmlout .= "</tr>";

            }

            //beosztás variálás miatt esetleg nem megjelenő foglalások
            if (isset($foglalasok[$szuresTipus["id"]]) && !empty($foglalasok[$szuresTipus["id"]])) {
                //orvosok megállapítása
                $doctors = [];
                foreach ($foglalasok[$szuresTipus["id"]] as $foglalas) {
                    $doctors[] = $foglalas["orvosnev"];
                }
                $doctors = array_unique($doctors);

                foreach ($doctors as $doctor) {
                    $htmlout .= "<tr>";
                    $htmlout .= "<td>";
                    $htmlout .= "<div style='padding:4px 0px;'>Beosztáson kívüli foglalások - {$doctor}:</div>";
                    $htmlout .= "<table cellpadding='0' cellspacing='0'>";
                    foreach ($foglalasok[$szuresTipus["id"]] as $foglalas) {
                        if ($foglalas["orvosnev"] != $doctor) {
                            continue;
                        }
                        $htmlout .= $this->elojegyzesTableRow($foglalas, date("H:i", strtotime($foglalas["datum"])), 0, true);
                    }
                    $htmlout .= "</table>";
                    $htmlout .= "</td>";
                    $htmlout .= "</tr>";
                }
            }

            if (isset($foglalasok[$szuresTipus["id"]]) && empty($foglalasok[$szuresTipus["id"]])) {
                unset($foglalasok[$szuresTipus["id"]]);
            }
        }
        $htmlout.="</table>";

        if ($rendelesek==0) {
            $htmlout.="<div style='margin-top:30px;'>Ezen a napon nincs rendelés a kiválasztott helyszínen.</div>";
        }

        if (count($tipusLinks) > 1) {
            $links = [];
            foreach ($tipusLinks as $link) {
                
                $tlink = "<a class='tipuslink' href='{$link["url"]}'>{$link["nev"]} <span style='".(isset($link["free"]) && $link["free"] == 0?"font-weight:bold;border-radius:20px;background:#888;color:#fff;opacity:.3;":"font-weight:bold;border-radius:20px;background:#0a0;color:#fff;")."'>".(isset($link["free"])?"&nbsp;{$link["free"]}&nbsp;":"")."</span></a>";
                $links[] = $tlink;
            }

            //Extra gyors gombok beillesztése:
            $links = $this->addExtraShortCutLinks($links,$ExtraButtons);

            $htmlout = str_replace("#tipuslinksplace#", "<div class='tipuslinksbox'>". implode(" ", $links) . "</div>", $htmlout);
        } else {
            $htmlout = str_replace("#tipuslinksplace#", "", $htmlout);
        }

        return $htmlout;
    }

    private function addExtraShortCutLinks($links = array(),$ExtraButtons){

        foreach($ExtraButtons as $link){
            $url = "javascript:scrollTo(\"orvosdiv{$link["id"]}\");";
            $extraLink = "<a class='tipuslink' href='{$url}'>{$link["nev"]} <span style='font-weight:bold;border-radius:20px;background:#0a0;color:#fff;'></span></a>";
            $links[] = $extraLink;
        }
        return $links;
    }


    private function datePastStyle($nap, $ora) {
        return strtotime("now") > strtotime("{$nap} {$ora}") ? "color:#aaa;" : "";
    }


    private $lastIdopont;
    private $foglalasButtonVolt;
    private $addIdopontJavaScript;
    private $potIdopont;
    private $displayedReservations = [];
    private $szuresTipusActual;

    private function elojegyzesTableRow($reservationData, $ora, $binterval, $noAdd = false) {
        $nap = date("Y-m-d", strtotime($reservationData["datum"]));
        //$ora = date("H:i", strtotime($rowf["datum"]));

        $htmlout = "";

        if ($reservationData["eljott"] == 0 && !empty($reservationData["nev"]) && $reservationData["nev"] !="nincs név" && strtotime("now - 10 minute") > strtotime($reservationData["datum"])) {
            $reservationData["megj"] = "<span style='color:red;border:1px solid red;padding:0px 2px;'>nem jött el</span> ".$reservationData["megj"];
        }

        if ($reservationData["nev"] == "nincs név") {
            $reservationData["nev"] = "Foglalt";
        }

        $jogosult       = $this->adminUser->cegJog($reservationData["cegid"]);
        $idopontShow    = date("H:i", strtotime($reservationData["datum"]));
        $cegNev         = trim($this->utils->substr_jns($reservationData["cegnev"], 0, 20));
        $detailURL      = "showIdopontEditor(\"{$_GET["page"]}\",\"{$reservationData["pass"]}\",{$reservationData["id"]});return false;";
        $companyWarning = "";

        $warnings = $this->bookingService->foglalasWarnings($reservationData);
        if (!empty($warnings)) {
            $companyWarning = "<a onclick='{$detailURL}' href='#'><i title='".implode("\n", $warnings)."' class='fas fa-exclamation-circle'></i></a>&nbsp;";
        }

        $htmlout .= "<tr style=''>";
        $htmlout .= "<td valign='top' nowrap style='".$this->datePastStyle($nap, $ora)."'>" . ($idopontShow != $this->lastIdopont ? $idopontShow . ($this->potIdopont ? "&nbsp;<span title='pótidőpont'>(p)</span>" : "") : "") . "&nbsp;&nbsp;</td>";
        $htmlout .= "<td valign='top' nowrap>";
        if ($this->foglalasButtonVolt == 0 && "{$nap} {$idopontShow}" == "{$nap} {$ora}" && !$noAdd) {
            $htmlout .= "<a onclick='{$this->addIdopontJavaScript}' class='iconbutton' title='foglalás' href='#'><i class='fas fa-plus-square'></i></a>&nbsp;&nbsp;";
            $this->foglalasButtonVolt = 1;
        }
        $htmlout .= "</td>";
        if ($jogosult) {
            $htmlout .= "<td valign='top' nowrap><a onclick='removeIdopont({$reservationData["id"]},\"{$reservationData["pass"]}\",\"booking\");return false;' class='iconbutton' title='foglalás törlése' href='#'><i class='fas fa-minus-square'></i></a>&nbsp;&nbsp;</td>";
            $htmlout .= "<td valign='top' nowrap>";

            if ($reservationData["rinterval"] != $binterval) {
                $htmlout .= "({$reservationData["rinterval"]} perc) ";
            }

            if ($this->szuresTipusActual["id"] == $reservationData["szurestipusid"]) {
                $htmlout .= "<a onclick='{$detailURL}' href='#' style='" . ($reservationData["nev"] == "Foglalt" ? "color:#aaa;" : "") . "'>{$reservationData["nev"]}</a>" . ($reservationData["tudoszuro"] != 0 ? " <i title='tüdőszűrés kell' class='fas fa-lungs'></i>" : "") . "&nbsp;" . ($reservationData["docid"] != null ? " <i title='file' class='fas fa-file'></i>" : "") . "&nbsp;&nbsp;";
            } else {
                $htmlout .= "Foglalva ({$reservationData["szurestipusnev"]})&nbsp;&nbsp;";
            }

            if (!empty($reservationData["externalid"])) {
                $htmlout.= "<span class='externalmark' title='foglalás forrása'>".str_replace("hungariamed", "hmm", preg_replace('/[0-9]+/', '', $reservationData["externalid"]))."</span>&nbsp;&nbsp;";
            }

            if ($reservationData["foglalta"] == "foglaljorvost") {
                $htmlout.= "<span class='externalmark' title='foglaljorvost foglalás'>FO</span>&nbsp;&nbsp;";
            }

            $htmlout .= "</td>";
            $htmlout .= "<td valign='top' nowrap>";

            $htmlout .= "{$companyWarning}<span style='" . ($reservationData["cegid"] == $_SESSION["ecegfilter"] ? "font-weight:bold;color:#00a;" : "color:#0a0;") . "'>{$cegNev}</span>";
            if ($reservationData["telephely"] != "") {
                $htmlout .= "&nbsp;<span title='telephely' style='color:#003366'>{$reservationData["telephely"]}</span>";
            }
            $htmlout .= "&nbsp;&nbsp;";

            $htmlout .= "<div id='fiz_szolglist{$reservationData["id"]}'>" . $this->adminUtils->showFizSzolg($reservationData["id"], 1) . "</div>";
            $htmlout .= "</td>";

            if ($this->adminUser->paciensMegjegyzesAccess()) {
                $htmlout .= "<td valign='top' nowrap>{$reservationData["megj"]}</td>";
            }

            $this->lastIdopont = $idopontShow;
        } else {
            $htmlout .= "<td colspan='2' valign='top'><span style='color:#aaa;'>Másik cég foglalása</span>&nbsp;&nbsp;</td>";
        }
        $htmlout .= "</tr>";

        return $htmlout;
    }

    private function napFilter2($setDay) {
        $html = "";
        $w = date("N", strtotime($setDay));
        $html.= "<input class='napfilter' id='napfilter' value='{$setDay} {$this->adminUtils->settings->hetnap[$w]}' style='font-size:18px;background-color:#eee;color:#444;margin-right:10px;border:1px solid #ccc;' data-page='{$_GET["page"]}' />";
        return $html;
    }

    private function cegFilter() {
        $html = "";
        $html.="<select class='s2 companyselector2' name='ecegfilter' onchange=\"window.location.href='index.php?page={$_GET["page"]}&ecegfilter='+this.value;\">";
        $html.="<option value=''>Szűrés cégre</option>";

        $companies = $this->bookingService->beosztasService->getPlaceCompanies($_SESSION["helyszin"]);
        foreach ($companies as $company) {
            if (empty($company["megnev"])) {
                continue;
            }
            $html.="<option value='{$company["id"]}'".($_SESSION["ecegfilter"]==$company["id"]?" selected":"").">{$company["megnev"]}</option>";
        }

        $html.="</select>";
        return $html;
    }


    private function cimFilter():string {
        $html = "<select class='s2 addressselector2' name='helyszin' onchange='setHelyszin2(this.value);'>";
        $html.= "<option value='0'>Válassz helyszínt!</option>";
        $res = sql_query("SELECT h.* FROM helyszinek h WHERE true ORDER BY trim(h.cim)");
        while ($placeData = sql_fetch_array($res)) {
            if (!$this->adminUser->allCegJog()) {
                $cegidk = $this->adminUser->getCegListArray();
                $cegJog = false;
                foreach ($cegidk as &$val) {
                    if (substr_count($placeData["ceglink"],"|{$val}|") && $val!="") {
                        $cegJog = true;
                    }
                }
                if (!$cegJog) {
                    continue;
                }
            }

            if  ($_SESSION["helyszin"] == 0 && $placeData["id"] == Booking_Constants::DEFAULT_PLACE_IDS[0]) {
                //default cím beállítása
                $_SESSION["helyszin"] = $placeData["id"];
            }

            $html.= "<option value='{$placeData["id"]}-0'".("{$_SESSION["helyszin"]}-0"=="{$placeData["id"]}-0"?" selected":"").">{$placeData["cim"]}</option>";
        }
        $html.= "</select>";
        return $html;
    }

    private function rendIdoSelect($id, $selectedTime) {
        $html = "";
        $html.= "<select name='{$id}' id='{$id}'>";
        $html.= "<option value='0'>Válassz!</option>";
        for ($n = 0; $n <= 1065; $n+=5) {
            $t = date("H:i",mktime(6,0+$n,0,1,1, date("Y")));
            $html.= "<option value='{$t}'".($selectedTime==$t?" selected" : "").">{$t}</option>";
        }
        $html.= "</select> ";
        return $html;
    }

    private function rendIntervalSelect($id, $selectedInterval) {
        $html = "";
        $html.= "<select title='egy kezelés időtartama' id='{$id}'>";
        foreach ($this->adminUtils->settings->validIntervals as $interval) {
            $html.= "<option value='{$interval}'".($selectedInterval==$interval?" selected":"").">{$interval} perc</option>";
        }
        $html.= "</select> ";
        return $html;
    }

}

