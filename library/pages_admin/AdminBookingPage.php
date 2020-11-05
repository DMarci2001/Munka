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
                sql_query("insert into orvosok set nev=?, description=?, aktiv=1, pecsetszam='temp', created=now(), createdby=?", [$orvosNev, $orvosMegj, $_SESSION["adminuser"]["nev"]]);
                $return["newOrvosId"] = $orvosId = sql_insert_id();

                sql_query("insert into orvos_beosztas set orvosid=?, helyszinid=?, nap=10, beonap=?, tol=?, ig=?, binterval=?, tipusok=?, aktiv=1", [$orvosId, $helyszinId, $nap, $orvosTol, $orvosIg, $orvosInterval, "|{$szuresTipusId}|"]);
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
            sql_query("update orvos_beosztas set tol=?, ig=? where orvosid=? limit 1", [$orvosTol, $orvosIg, $oid]);

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
                sql_query("delete from orvos_beosztas where orvosid=?", [$orvosId]);
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
        echo "<div>";
        echo "<select name='helyszin' onchange='setHelyszin2(this.value);'>";
        echo "<option value='0'>Válassz helyszínt!</option>";
        $res = sql_query("SELECT h.* FROM helyszinek h WHERE true ORDER BY trim(h.cim)");
        while ($placeData = sql_fetch_array($res)) {
            if ($_SESSION["adminuser"]["jogosultsag"] < 2) {
                if (isset($go)) unset($go);
                $cegidk = explode("|",$_SESSION["adminuser"]["cegjog"]);
                foreach ($cegidk as &$val) {
                    if (substr_count($placeData["ceglink"],"|{$val}|") && $val!="") {
                        $go = 1;
                        break;
                    }
                }
                if (!isset($go)) continue;
            }
            echo "<option value='{$placeData["id"]}-0'".("{$_SESSION["helyszin"]}-0"=="{$placeData["id"]}-0"?" selected":"").">{$placeData["cim"]}</option>";
        }
        echo "</select>";
        echo "</div>";

        if (!isset($_SESSION["helyszin"]) || $_SESSION["helyszin"] == 0) {
            return;
        }

        echo "<div id='elojegyzestable'>".$this->showElojegyzesTable($this->setDay)."</div>";
        echo "<div id='idoponteditor'></div>";
    }


    public function showElojegyzesTable($setDay) {
        $settings      = new Booking_Settings();
        $htmlout       = "";
        $tipusLinks[0] = ["url" => "javascript:scrollTo(\"filterbox\");", "nev" => "Oldal teteje"];
        $rendelesek    = 0;
        $helyszin      = intval($_SESSION["helyszin"]);
        $nap           = date("Y-m-d", strtotime($setDay));
        $wd            = date("N", strtotime($setDay));
        $wCeg          = $this->adminUtils->cegSQLFilter("b.cegid");
        $tipusok       = $this->bookingService->tipusExtract($this->bookingService->beosztasService->getTipusByHelyszin($helyszin));
        $foglalasok    = $this->bookingService->getAllReservationForDay($nap, $helyszin);
        $isHoliday     = in_array($nap, $settings->getMunkaszunetiNapok());
        $maxOrvosId    = sql_query("select max(id)+1 from orvosok")->fetchColumn();

        $htmlout.="<div id='filterbox' style='margin-top:10px;'>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'>".$this->napFilter2($setDay)."</div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'><a onclick='setListDay(\"".date("Y-m-d",strtotime("{$setDay} -1 day"))."\");return false;' href='#'><img height='20' src='images/prev.png' title='Előző nap'/></a>&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'><input type='button' onclick='setListDay(\"".date("Y-m-d")."\");' value='MA' title='Ugrás a mai napra' />&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'><a onclick='setListDay(\"".date("Y-m-d",strtotime("{$setDay} +1 day"))."\");return false;' href='#'><img height='20' src='images/next.png' title='Következő nap'/></a></div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;padding-left:20px;'>".$this->cegFilter()."</div>";

        if (in_array($nap, $settings->getMunkaszunetiNapok())) {
            $htmlout.="<div style='margin-top:10px;padding:5px 10px;background: #f00;color:#fff;font-size:18px;display:inline-block;'>Munkaszüneti nap!</div>";
        }

        $htmlout.= "</div>";
        $htmlout.= "<div class='stickytablefilter' id='stickytablefilter'>#tipuslinksplace#</div>";

        $htmlout.= "<table width='100%' cellpadding='0' cellspacing='0' border='0'>";

        sql_query("SET SESSION group_concat_max_len = 10000");
        $szuresTipusok = sql_query("select * from szurestipusok where id in (".implode(",",$tipusok).") order by !instr(megnev,'üzemorvosi'), !instr(megnev,'menedzser'), megnev");
        while ($szuresTipus = sql_fetch_array($szuresTipusok)) {


            $beosztasok = $this->bookingService->beosztasService->getBookingPageBeosztasok($nap, $_SESSION["helyszin"], $szuresTipus["id"]);


            foreach ($beosztasok as $beosztas) {
                $rendelesek         ++;
                $cegek              = array_unique(explode(",", $beosztas["cegek"]));
                $minTol             = "24:00";
                $maxIg              = "00:00";
                $maxPotIg           = "00:00";
                $orvosId            = $beosztas["orvosid"];
                $binterval          = $beosztas["binterval"];
                $rendeloOrvosLink   = "<a target='_blank' href='{$_SERVER['PHP_SELF']}?page=doctors&szerk={$orvosId}'>{$beosztas["orvosnev"]}</a>";
                $addDoctorLink      = "<a class='orvosbutton' onclick=\"$('#adddoctordiv{$orvosId}').slideDown();return false;\" href='#'>+ orvos</a>";
                $szabi              = sql_fetch_array(sql_query("select * from szabadsag where datumtol<=? and datumig>=? and oid=?", [$nap, $nap, $beosztas["orvosid"]]));
                $szabiURL           = $szabi ? "szabadságon" : "<a onclick='return confirm(\"Biztos beállítod szabadságra erre a napra?\");' href='{$_SERVER['PHP_SELF']}?page={$_GET["page"]}&szabira={$nap}&orvosid={$orvosId}'>szabadságra</a>";

                if ($beosztas["pecsetszam"] == "temp") {
                    $rendeloOrvosLink = "<a target='_blank' href='#' title='Orvos eltávolítása' onclick=\"$('#editdoctordiv{$orvosId}').slideDown();return false;\">- {$beosztas["orvosnev"]}</a>";
                    //$rendeloOrvosLink = "<a target='_blank' href='#' title='Orvos eltávolítása' onclick='removeTempDoctor(\"{$nap}\", {$orvosId});return false;'>- {$beosztas["orvosnev"]}</a>";
                    $addDoctorLink    = "";
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
                $htmlout .= "<div class='etabletipushead' id='tpid{$szuresTipus["id"]}'>";
                $htmlout .= "<div id='orvosdiv{$orvosId}' style='font-size:16px;font-weight:bold;'>{$rendeloOrvosLink}&nbsp;{$szuresTipus["megnev"]}&nbsp;&nbsp;{$addDoctorLink}</div>";
                $htmlout .= "<div>{$beosztas["description"]}</div>";
                if ($szabi) {
                    $szabiData = sql_fetch_array(sql_query("select min(datumtol) as datumtol, max(datumig) as datumig from szabadsag where groupid=?", [$szabi["groupid"]]));
                    $htmlout .= "<div style='padding:2px 0px;'><span style='color:#fff;background:#f00;padding:2px 5px;'>Szabadságon {$szabiData["datumtol"]} - {$szabiData["datumig"]}</span></div>";
                }

                $htmlout .= "<div id='adddoctordiv{$orvosId}' style='display:none;margin:10px 0px;padding:10px 0px;border-top:1px solid #888;border-bottom:1px solid #888;'>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Adj nevet az orvosnak:</div><div class='tdm' style='padding:2px 0px;'><input type='text' id='orvosnev{$orvosId}' value='TempOrvos{$maxOrvosId}'/></div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Megjegyzés: </div><div class='tdm' style='padding:2px 0px;'><input type='text' id='orvosmegj{$orvosId}' style='width:300px;'/></div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Rendelési idő: </div><div class='tdm' style='padding:2px 0px;'>".$this->rendIdoSelect("orvostol{$orvosId}", $minTol)." - ".$this->rendIdoSelect("orvosig{$orvosId}", $maxIg)."&nbsp;&nbsp;időtartam: ".$this->rendIntervalSelect("orvosinterval{$orvosId}", $binterval)."</div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'></div><div class='tdm' style='padding:2px 0px;'><input onclick=\"addTempDoctor('{$nap}', {$helyszin}, {$szuresTipus["id"]}, {$orvosId});\" type='button' value='Orvos hozzáadása' /> <input onclick=\"$('#adddoctordiv{$orvosId}').slideUp()\" type='button' value='mégsem' /></div></div>";
                $htmlout .= "</div>";

                $htmlout .= "<div id='editdoctordiv{$orvosId}' style='display:none;margin:10px 0px;padding:10px 0px;border-top:1px solid #888;border-bottom:1px solid #888;'>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Név:</div><div class='tdm' style='padding:2px 0px;'><input type='text' id='editorvosnev{$orvosId}' value='{$beosztas["orvosnev"]}'/></div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Megjegyzés: </div><div class='tdm' style='padding:2px 0px;'><input type='text' id='editorvosmegj{$orvosId}' style='width:300px;' value='{$beosztas["orvosdescription"]}' /></div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Rendelési idő: </div><div class='tdm' style='padding:2px 0px;'>".$this->rendIdoSelect("editorvostol{$orvosId}", $beosztas["tol"])." - ".$this->rendIdoSelect("editorvosig{$orvosId}", $beosztas["ig"])."</div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'></div><div class='tdm' style='padding:2px 0px;'><input onclick='saveTempDoctor({$orvosId});' type='button' value='Mentés' /> <input onclick=\"removeTempDoctor('{$nap}', {$orvosId});\" type='button' value='Orvos törlése' /> <input onclick=\"$('#editdoctordiv{$orvosId}').slideUp()\" type='button' value='mégsem' /></div></div>";
                $htmlout .= "</div>";

                if (isset($cegek[0]) && !empty($cegek[0])) {
                    $htmlout .= "<div style=''><a onclick='$(\"#beocegek{$rendelesek}\").slideToggle();return false;' href='#'>" . count($cegek) . " cég</a></div>";
                    $htmlout .= "<div id='beocegek{$rendelesek}' style='" . (count($cegek) > 10 ? "display:none;" : "") . "font-size:10px;color:#888;'>" . implode(", ", $cegek) . "</div>";
                }
                $htmlout .= "</div>";
                //$htmlout .= "</td>";

                $freeCounter = $timeCounter = 0;

                if ($minTol != "24:00") {
                    $htmlout .= "<table cellpadding='0' cellspacing='0'>";
                    for ($o = 0; $o < 3600; $o += $binterval) {
                        $ora = date("H:i", strtotime("{$minTol}:00 +{$o} minute"));
                        if (strtotime($maxPotIg) <= strtotime($ora)) {
                            break;
                        }
                        $this->potIdopont = strtotime($ora) >= strtotime($maxIg);

                        $timeFrom = "{$nap} {$ora}:00";
                        $timeTo = date("Y-m-d H:i:s", strtotime("{$timeFrom} + {$binterval} minute"));

                        $this->addIdopontJavaScript = "setSelectedOrvos({$beosztas["orvosid"]});setSelectedInterval({$binterval});addIdopont(\"{$nap} {$ora}\", {$szuresTipus["id"]});return false;";
                        if ($isHoliday) {
                            $this->addIdopontJavaScript = "if (confirm(\"Ez munkaszüneti nap, biztos foglalsz?\")) { {$this->addIdopontJavaScript} } return false;";
                        }

                        $resf = sql_query("select f.*,c.megnev as cegnev,o.nev as orvosnev,d.id as docid from foglalasok f 
                        left join cegek c on c.id=f.cegid
                        left join orvosok o on o.id=f.orvosassigned
                        left join dokumentumok d on d.foglalasid=f.id
                        where f.datum>=? and f.datum<? and f.helyszinid=? and f.szurestipusid=? and f.orvosassigned in (0, ?) group by f.id", array($timeFrom, $timeTo, $_SESSION["helyszin"], $szuresTipus["id"], $orvosId));

                        $this->lastIdopont = "";
                        $this->foglalasButtonVolt = 0;
                        while ($rowf = sql_fetch_array($resf)) {
                            if (in_array($rowf["id"], $this->displayedReservations) && $beosztas["pecsetszam"] == "temp") {
                                continue;
                            }
                            if (isset($foglalasok[$rowf["szurestipusid"]][$rowf["id"]])) {
                                unset($foglalasok[$rowf["szurestipusid"]][$rowf["id"]]);
                            }
                            $htmlout .= $this->elojegyzesTableRow($rowf, $nap, $ora);
                            $this->displayedReservations[] = $rowf["id"];
                        }

                        if ($this->lastIdopont == "") {
                            //nem volt foglalás, üres időpont kirakás
                            $htmlout .= "<tr style=''>";
                            $htmlout .= "<td valign='top'>{$ora}" . ($this->potIdopont ? " <span title='pótidőpont'>(p)</span>" : "") . "&nbsp;&nbsp;</td>";
                            $htmlout .= "<td valign='top'><a onclick='{$this->addIdopontJavaScript}' class='kisbutton' title='foglalás' href='#'>+</a>&nbsp;&nbsp;</td>";
                            $htmlout .= "</tr>";
                            if (!$szabi) {
                                $freeCounter++;
                            }
                        }
                    }
                    $htmlout .= "</table>";
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
                $htmlout .= "<tr>";
                $htmlout .= "<td>";
                $htmlout .= "<div style='padding:4px 0px;'>Beosztáson kívüli foglalások:</div>";
                $htmlout .= "<table cellpadding='0' cellspacing='0'>";
                foreach ($foglalasok[$szuresTipus["id"]] as $foglalas) {
                    $htmlout.= $this->elojegyzesTableRow($foglalas, date("Y-m-d", strtotime($foglalas["datum"])), date("H:i", strtotime($foglalas["datum"])), true);
                }
                $htmlout .= "</table>";
                $htmlout .= "</td>";
                $htmlout .= "</tr>";
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
            $htmlout = str_replace("#tipuslinksplace#", "<div class='tipuslinksbox'>". implode(" ", $links) . "</div>", $htmlout);
        } else {
            $htmlout = str_replace("#tipuslinksplace#", "", $htmlout);
        }

        return $htmlout;
    }

    private $lastIdopont;
    private $foglalasButtonVolt;
    private $addIdopontJavaScript;
    private $potIdopont;
    private $displayedReservations = [];

    private function elojegyzesTableRow($rowf, $nap, $ora, $noAdd = false) {
        $htmlout = "";

        $jogosult = true;
        if ($this->adminUtils->isCegAdmin() && substr_count($_SESSION["adminuser"]["cegjog"], "|{$rowf["cegid"]}|") == 0) {
            $jogosult = false;
        }

        if ($rowf["nev"] == "nincs név") {
            $rowf["nev"] = "Foglalt";
        }

        $idopontShow = date("H:i", strtotime($rowf["datum"]));

        $htmlout .= "<tr style=''>";
        $htmlout .= "<td valign='top'>" . ($idopontShow != $this->lastIdopont ? $idopontShow . ($this->potIdopont ? "&nbsp;<span title='pótidőpont'>(p)</span>" : "") : "") . "&nbsp;&nbsp;</td>";
        $htmlout .= "<td valign='top'>";
        if ($this->foglalasButtonVolt == 0 && "{$nap} {$idopontShow}" == "{$nap} {$ora}" && !$noAdd) {
            $htmlout .= "<a onclick='{$this->addIdopontJavaScript}' class='kisbutton' title='foglalás' href='#'>+</a>&nbsp;&nbsp;";
            $this->foglalasButtonVolt = 1;
        }
        $htmlout .= "</td>";
        if ($jogosult) {
            $htmlout .= "<td valign='top' nowrap><a onclick='removeIdopont({$rowf["id"]},\"{$rowf["pass"]}\",\"booking\");return false;' class='kisbutton' title='foglalás törlése' href='#'>-</a>&nbsp;&nbsp;</td>";
            $htmlout .= "<td valign='top' nowrap><a onclick='showIdopontEditor(\"{$_GET["page"]}\",\"{$rowf["pass"]}\",{$rowf["id"]});return false;' href='#' style='" . ($rowf["nev"] == "Foglalt" ? "opacity:.5;" : "") . "'>{$rowf["nev"]}</a>" . ($rowf["tudoszuro"] != 0 ? " <span title='Tüdőszűrés kell' style='background:#f00;color:#fff;padding:0px 5px;border-radius:3px;'>T</span>" : "") . "&nbsp;" . ($rowf["docid"] != null ? " <span style='background:#888;color:#fff;padding:0px 5px;border-radius:3px;'>file</span>" : "") . "&nbsp;&nbsp;</td>";
        } else {
            $htmlout .= "<td colspan='2' valign='top'><span style='color:#aaa;'>Másik cég foglalása</span>&nbsp;&nbsp;</td>";
        }
        if ($jogosult) {
            $htmlout .= "<td valign='top' nowrap>";
            $cegNev = trim($this->utils->substr_jns($rowf["cegnev"], 0, 20));

            $orvNev = "";
            if ($rowf["orvosassigned"] != 0) {
                $orvNev = trim($rowf["orvosnev"]);
            }

            $htmlout .= "<span style='" . ($rowf["cegid"] == $_SESSION["ecegfilter"] ? "font-weight:bold;color:#00a;" : "") . "'>{$cegNev}</span>";
            if ($orvNev != "" && $cegNev != "") {
                $htmlout .= " &#187; ";
            }
            $htmlout .= " <span style='color:#080;'>{$orvNev}</span>";
            if ($orvNev == "" && $cegNev == "") {
                $htmlout .= "???";
            }
            if ($rowf["telephely"] != "") {
                $htmlout .= "&nbsp;<span style='color:#003366'>{$rowf["telephely"]}</span>";
            }
            $htmlout .= "&nbsp;&nbsp;";

            $htmlout .= "<div id='fiz_szolglist{$rowf["id"]}'>" . $this->adminUtils->showFizSzolg($rowf["id"], 1) . "</div>";
            $htmlout .= "</td>";

            $htmlout .= "<td valign='top' nowrap>{$rowf["megj"]}</td>";

            $this->lastIdopont = $idopontShow;
        }
        $htmlout .= "</tr>";

        return $htmlout;
    }

    private function napFilter($setDay) {
        $html = "";
        $html.= "<select name='napselect' style='margin-right:10px;font-size:22px;width:300px;' onchange='window.location.href=\"index.php?page={$_GET["page"]}&setday=\"+this.value;'>";
        for ($i=-60; $i<150; $i++) {
            $day = date("Y-m-d",strtotime("now +{$i} day"));
            $html.= "<option value='{$day}'".($day == $setDay?" selected":"").">".$this->adminUtils->magyarDatum($day)."</option>";
        }
        $html.= "</select>";
        return $html;
    }

    private function napFilter2($setDay) {
        $html = "";
        $w = date("N", strtotime($setDay));
        $html.= "<input class='napfilter' id='napfilter' value='{$setDay} {$this->adminUtils->settings->hetnap[$w]}' style='font-size:18px;background-color:#eee;color:#444;margin-right:10px;border:1px solid #ccc;' data-page='{$_GET["page"]}' />";
        return $html;
    }

    private function cegFilter() {
        $html = "";
        $html.="<select name='ecegfilter' onchange=\"window.location.href='index.php?page={$_GET["page"]}&ecegfilter='+this.value;\">";
        $html.="<option value='0'>Szűrés cégre</option>";

        $cegek = $this->bookingService->beosztasService->getCegListByHelyszin($_SESSION["helyszin"]);
        foreach ($cegek as $rowcf) {
            if (empty($rowcf["megnev"])) {
                continue;
            }
            $html.="<option value='{$rowcf["id"]}'".($_SESSION["ecegfilter"]==$rowcf["id"]?" selected":"").">{$rowcf["megnev"]}</option>";
        }

        $html.="</select>";
        return $html;
    }

    private function _getIntervals($beosztasok) {
        $intervals = [];
        foreach ($beosztasok as $beosztasData) {
            if (!in_array($beosztasData["binterval"],$intervals)) {
                $intervals[] = $beosztasData["binterval"];
            }
        }
        return $intervals;
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

