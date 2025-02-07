<?php

class AdminBookingPage extends AdminCorePage
{

    private BookingService $bookingService;
    private WebShopService $webShopService;
    private VaroteremService $varoteremService;

    private $setDay;

    public function __construct()
    {
        parent::__construct();
        $this->bookingService = new BookingService();
        $this->webShopService = new WebShopService();
        $this->varoteremService = new VaroteremService();

        $GLOBALS["css"][] = "dailystat.css";
        $GLOBALS["javascript"][] = "dailystat.js";

        if (!isset($_SESSION["helyszin"])) $_SESSION["helyszin"] = 0;
        if (!isset($_SESSION["helyszinceg"])) $_SESSION["helyszinceg"] = 0;
        if (!isset($_SESSION["naptarszurestipus"])) $_SESSION["naptarszurestipus"] = 0;
        if (!isset($_SESSION["esearchkey"])) $_SESSION["esearchkey"] = "";
        if (!isset($_SESSION["ecegfilter"])) $_SESSION["ecegfilter"] = 0;
        if (!isset($_SESSION["setday"])) $_SESSION["setday"] = date("Y-m-d");
        if (isset($_GET["setday"])) $_SESSION["setday"] = $_GET["setday"];
        if (isset($_GET["ecegfilter"])) $_SESSION["ecegfilter"] = $_GET["ecegfilter"];

        $this->setDay = $_SESSION["setday"];

        if (isset($_GET["sethelyszin2"])) {
            $s = explode("-", $_GET["sethelyszin2"]);
            $_SESSION["helyszin"]    = $s[0];
            $_SESSION["helyszinceg"] = $s[1];
            header("location:index.php?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["sethelyszin3"])) {
            $s = explode("-", $_GET["sethelyszin3"]);
            $_SESSION["helyszin"]    = $s[0];
            $_SESSION["helyszinceg"] = $s[1];
        }

        if (isset($_GET["deletedreservations"])) {
            $service = new NotificationService();
            //$service->deleteUserMessage(615695);
            $service->logProcess();
            die();
        }

        if (isset($_GET["printbeoreservations"])) {
            echo $this->printBeoReservations();
            die;
        }

        if (isset($_GET["printbeopdf"])) {
            $printService = new PrintService();
            echo $printService->printBeoPdf($_GET["printbeopdf"], $_GET["nap"]);
            die;
        }

        if (isset($_GET["szabira"])) {
            sql_query("insert into szabadsag set oid=?,datumtol=?,datumig=?", array($_GET["orvosid"], $_GET["szabira"], $_GET["szabira"]));

            $rowo = sql_fetch_array(sql_query("select * from orvosok where id=?", array($_GET["orvosid"])));
            logActivity("orvos", $rowo["id"], "{$rowo["nev"]} szabira küldés link {$_GET["szabira"]}", "");

            header("location:{$_SERVER['PHP_SELF']}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["showelojegyzestable"])) {

            //$_SESSION["setday"] = date("Y-m-d");
            if (isset($_GET["setday"])) $_SESSION["setday"] = $_GET["setday"];
            if (isset($_GET["day"])) $_SESSION["setday"] = $_GET["day"];
            echo $this->showElojegyzesTableNew($_SESSION["setday"]);
            die();
        }

        if (isset($_GET["addidopont"])) {
            $this->bookingService->addIdoPont();

            if (isset($_SESSION["setday"])) {
                echo $this->showElojegyzesTableNew($_SESSION["setday"]);
            }
            die();
        }

        if (isset($_GET["removeidopont"])) {
            $this->bookingService->removeIdopont($_GET["removeidopont"], $_GET["p"]);
            echo $this->showElojegyzesTableNew($_SESSION["setday"]);
            die();
        }

        if (isset($_GET["moveidopont"])) {
            $this->bookingService->moveIdopont();

            if (isset($_SESSION["setday"])) {
                echo $this->showElojegyzesTableNew($_SESSION["setday"]);
            }
            die();
        }

        if (isset($_POST["addreplacedoctor"])) {
            $nap                 = $_POST["nap"];
            $helyszinId          = intval($_POST["helyszin"]);
            $beoId               = intval($_POST["beoid"]);
            $oid                 = intval($_POST["sourceoid"]);
            $helyettesitoOrvosId = $_POST["helyettesitoorvosid"];
            $orvosMegj           = $_POST["orvosMegj"];
            $return              = ["error" => "", "html" => ""];

            sql_query("insert into helyettesites set nap=?, oid=?, helyettesitoorvosid=?, helyszinid=?, beoid=?, megj=?", [$nap, $oid, $helyettesitoOrvosId, $helyszinId, $beoId, $orvosMegj]);

            $return["html"] = $this->showElojegyzesTableNew($_SESSION["setday"]);
            $this->utils->jsonOut($return);
        }

        if (isset($_POST["addcrewdata"])) {
            $nap                 = $_POST["nap"];
            $helyszinId          = intval($_POST["helyszin"]);
            $beoId               = intval($_POST["beoid"]);
            $orvos               = $_POST["orvosnev"];
            $asszisztens         = $_POST["asszisztensnev"];
            $return              = ["error" => "", "html" => ""];

            sql_query("insert into szemelyzet set beoid=?, nap=?, helyszinid=?, orvosnev=?, asszisztensnev=?, letrehozva=?", [$beoId, $nap, $helyszinId, $orvos, $asszisztens, date("Y-m-d H:i:s")]);
            $return["html"] = $this->showElojegyzesTableNew($_SESSION["setday"]);
            die(json_encode($return));
        }

        if (isset($_GET["removereplacedoctor"])) {
            $orvosId = intval($_GET["oid"]);
            $nap     = $_GET["nap"];
            $return  = ["error" => "", "html" => ""];

            sql_query("delete from helyettesites where oid=? and nap=?", [$orvosId, $nap]);

            $return["html"] = $this->showElojegyzesTableNew($_SESSION["setday"]);
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

            $return["html"] = $this->showElojegyzesTableNew($_SESSION["setday"]);
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

            $return["html"] = $this->showElojegyzesTableNew($_SESSION["setday"]);
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

            $return["html"] = $this->showElojegyzesTableNew($_SESSION["setday"]);
            $this->utils->jsonOut($return);
        }

        if (isset($_POST["foReservationInfo"])) {
            if ($foglalasData = sql_fetch_array(sql_query("select f.*,o.foid as oid, sz.fotid as tid  from foglalasok f 
            left join orvosok o on o.id = f.orvosassigned
            left join szurestipusok sz on sz.id = f.szurestipusid
            where f.id=? and f.pass=?", [$_POST["fid"], $_POST["p"]]))) {
                if ($foglalasData["fofid"] != 0) {
                    $result = "Foglaljorvost szinkron sikeres!\n\n";
                    $result .= "Foglalás azonosító: {$foglalasData["fofid"]}\n";
                } else {
                    $result = "Foglaljorvost szinkron sikertelen!\n\n";
                }
                if ($foglalasData["oid"] == 0) {
                    $result .= "Orvos nincs összekötve\n";
                } else {
                    $result .= "Orvos azonosító: {$foglalasData["oid"]}\n";
                }
                if ($foglalasData["tid"] == 0) {
                    $result .= "Tipus nincs összekötve";
                } else {
                    $result .= "Tipus azonosító: {$foglalasData["tid"]}";
                }
            } else {
                $result = "error";
            }
            $this->utils->jsonOut(["result" => $result]);
        }

        if (isset($_POST["searchkey"])) {
            $key = $_POST["searchkey"];
            $sqlFilter = "true";
            if (!isset($_POST["searchkeytype"])) {
                $_POST["searchkeytype"] = "name";
            }
            if ($_POST["searchkeytype"] == "name") {
                $_SESSION["esearchkey"] = $key;
                $sqlFilter = "(instr(f.nev,:key) or instr(f.taj,:key) or instr(f.torzsszam,:key) or instr(f.szuldatum,:key))";
            }
            if ($_POST["searchkeytype"] == "company") {
                $sqlFilter = "f.cegid = :key";
            }

            $cegFilter = "";
            if (!$this->adminUser->allCegJog()) {
                $cegFilter = "and f.cegid in (" . $this->adminUser->getCegList() . ")";
            }

            $results = sql_query(
                "select f.*, c.megnev as cegnev, o.nev as orvosnev, d.id as docid, sz.megnev as szurestipusnev, f.helyszinid, h.cim as helyszin from foglalasok f
                    left join cegek c on c.id=f.cegid
                    left join helyszinek h on h.id=f.helyszinid
                    left join szurestipusok sz on sz.id=f.szurestipusid
                    left join orvosok o on o.id=f.orvosassigned
                    left join dokumentumok d on d.foglalasid=f.id
                where {$sqlFilter} and f.nev<>'nincs név' {$cegFilter} " . ($this->adminUser->onlyDoctorReservations() ? " and f.orvosassigned in(" . $this->adminUser->getUserDoctorIds() . ")" : "") . "
                order by f.datum desc
                
                limit 1000",
                ["key" => $key]
            )->fetchAll(PDO::FETCH_ASSOC);

            echo "<div style='padding:10px 0px;'>";

            if (empty($results)) {
                echo "Nincs találat!";
            } else {
                echo "<table>";
                echo "<tr style='font-weight: bold;'>";
                echo "<td class='searchrowcell'>időpont</td>";
                echo "<td class='searchrowcell'>taj</td>";
                echo "<td class='searchrowcell'>név</td>";
                echo "<td class='searchrowcell'>szül. dátum</td>";
                echo "<td class='searchrowcell'>orvos</td>";
                echo "<td class='searchrowcell'>típus</td>";
                echo "<td class='searchrowcell'>cég</td>";
                echo "<td class='searchrowcell'>helyszin</td>";
                echo "</tr>";
                foreach ($results as $result) {
                    echo "<tr>";
                    echo "<td class='searchrowcell'><a href='#' title='ugrás a naphoz' onclick='setListDayAndHelyszin(\"" . date("Y-m-d", strtotime($result["datum"])) . "\", \"{$result["helyszinid"]}\");return false;'><i class='fas fa-arrow-right'></i> " . date("Y-m-d H:i", strtotime($result["datum"])) . "</a></td>";
                    echo "<td class='searchrowcell'>{$result["taj"]}</td>";
                    echo "<td class='searchrowcell'>{$result["nev"]}</td>";
                    echo "<td class='searchrowcell'>{$result["szuldatum"]}</td>";
                    echo "<td class='searchrowcell'>{$result["orvosnev"]}</td>";
                    echo "<td class='searchrowcell'>{$result["szurestipusnev"]}</td>";
                    echo "<td class='searchrowcell'>{$result["cegnev"]}</td>";
                    echo "<td class='searchrowcell'>{$result["helyszin"]}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
            echo "</div>";

            die;
        }

        if (isset($_GET["addidoponttipusdialog"])) {
            $tipusok = explode(",", $_GET["tipusok"]);
            $tipusAdd = [0];
            foreach ($tipusok as $tipusId) {
                $tipusAdd[] = $tipusId;
            }

            $tipusok = sql_query("select id, megnev, ispack from szurestipusok where id in (" . implode(",", $tipusAdd) . ")")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tipusok as $tipus) {
                if (true) {
                    if ($tipus["ispack"] == 1) {
                        $addIdopontJavaScript = "packConfirmDialog(\"{$_GET["idopont"]}\", \"{$tipus["id"]}\", this);return false;";
                        echo "<div> <i class='fa-solid fa-box-open'></i> <a href='#' onclick='{$addIdopontJavaScript}'>{$tipus["megnev"]}</a></div>";
                    } else {
                        $addIdopontJavaScript = "addIdopont(\"{$_GET["idopont"]}\", \"{$tipus["id"]}\", this);return false;";
                        echo "<div> &gt; <a href='#' onclick='{$addIdopontJavaScript}'>{$tipus["megnev"]}</a></div>";
                    }
                } else {
                    $addIdopontJavaScript = "addIdopont(\"{$_GET["idopont"]}\", \"{$tipus["id"]}\", this);return false;";
                    echo "<div> &gt; <a href='#' onclick='{$addIdopontJavaScript}'>{$tipus["megnev"]}</a></div>";
                }
            }

            die;
        }

        if (isset($_REQUEST["packConfirmDialog"])) {
            $tipus = intval($_REQUEST["tipus"]);
            $time = $_REQUEST["idopont"];

            if (!$packData = sql_query("select id, megnev from szurestipusok t where t.id=?", [$tipus])->fetch(PDO::FETCH_ASSOC)) {
                die("Tipus not found!");
            }

            echo "<b>{$packData["megnev"]}</b>";

            $packCheckBoxes = "";
            $genderCheckBoxNeeded = 0;
            $packItems = sql_query("SELECT t.id, t.megnev, k.nemerequired, k.noreservation FROM szurescsomagok_kapcs k
                LEFT JOIN szurestipusok t ON t.id=k.szurestipusid
                WHERE csomagid=? and k.noreservation=0 ORDER BY k.id", array($packData["id"]))->fetchAll(PDO::FETCH_ASSOC);
            foreach ($packItems as $packItem) {
                $defaultChecked = "checked";
                $cClass = "";
                if ($packItem["nemerequired"]) {
                    $defaultChecked = "";
                    $genderCheckBoxNeeded = 1;
                    $cClass = $packItem["nemerequired"] == 1 ? " pack_man_exam" : " pack_woman_exam";
                }
                $packCheckBoxes .= "<div><input class='pack_items{$cClass}' type='checkbox' value='{$packItem["id"]}' {$defaultChecked} /> {$packItem["megnev"]}</div>";
            }


            echo "<div style='margin-top:5px;'><input type='text' id='packPatientName' value='' style='width:200px;' placeholder='Paciens TAJ száma'/></div>";
            echo "<input type='hidden' id='packTipus' value='{$tipus}' />";
            echo "<input type='hidden' id='packTime' value='{$time}' />";
            echo "<input type='hidden' id='genderNeeded' value='{$genderCheckBoxNeeded}' />";

            if ($genderCheckBoxNeeded == 1) {
                echo "<div style='margin-top:5px;'>";
                echo "<input name='packGender' id='packGender' type='radio' value='1' onclick='checkGenderPackContents(1);' /> Férfi&nbsp;&nbsp;";
                echo "<input name='packGender' id='packGender' type='radio' value='2' onclick='checkGenderPackContents(2);' /> Nő&nbsp;&nbsp;";
                echo "<div>";
            } else {
                echo "<div style='display:none;'>";
                echo "<input name='packGender' id='packGender' type='radio' value='0' checked />";
                echo "</div>";
            }

            echo "<div style='margin-top:5px;'>{$packCheckBoxes}</div>";

            echo "<div style='margin-top:5px;'>";
            echo "<div id='elojloader_packsave' style='display:none;'><img src='/admin/images/loading.svg' style='width: 30px;'/></div>";
            echo "<a class='printbutton packbuttons' onclick='reservePackContents();return false;' href='#'>Lefoglalás</a> ";
            echo "<a class='printbutton packbuttons' onclick='$(\".eloj_dialog\").hide();return false;' href='#'>Mégse</a> ";
            echo "<div>";

            die;
        }

        if (isset($_POST["reservePackContents"])) {
            $patientName = trim($_POST["packPatientName"]);
            $packTipus = $_POST["packTipus"];
            $packTime = $_POST["packTime"];
            $packGender = $_POST["packGender"] ?? 0;
            $packContentIds = explode(",", $_POST["packContentIds"]);
            if (empty($patientName)) {
                $patientName = "nincs név";
            }

            $_GET["orvosid"] = $_POST["orvosid"];
            $_GET["szt"] = $packTipus;
            $_GET["addidopont"] = $packTime;
            $_GET["rinterval"] = $_POST["rinterval"];
            $result = $this->bookingService->addIdoPontNew();
            $packReservationId = $result["reservationId"];
            $moreMessage = "";

            if ($packReservationId != 0) {
                $this->bookingService->replicateTajRequired = false;
                $this->bookingService->replicateDuplicateCheck = false;
                $this->bookingService->replicateToFirstAvailableTime = true;

                sql_query("update foglalasok set nev=?, neme=? where id=?", [$patientName, $packGender, $packReservationId]);

                if (!empty($patientName)) {
                    $patientData = $this->bookingService->getPatientByTAJ($patientName, $packReservationId);
                    if (empty($patientData["error"])) {
                        sql_query(
                            "update foglalasok set cegid=?, paciensid=?, taj=?, nev=?, neme=?, telefon=?, email=?, anyjaneve=?, szulhely=?, szuldatum=?, irsz=?, varos=?, utca=?, munkakor=? where id=?",
                            [$patientData["cegid"], $patientData["id"], $patientData["taj"], $patientData["nev"], $patientData["neme"], $patientData["telefon"], $patientData["email"], $patientData["anyjaneve"], $patientData["szulhely"], $patientData["szuldatum"], $patientData["irsz"], $patientData["varos"], $patientData["utca"], $patientData["munkakor"], $packReservationId]
                        );
                    } else {
                        $moreMessage = "{$patientData["error"]}\n";
                    }
                }


                $packReservationData = sql_query("select * from foglalasok where id=?", [$packReservationId])->fetch(PDO::FETCH_ASSOC);

                $results = [];
                //időpontok tesztelése
                $this->bookingService->replicateToFirstAvailableTime = true;
                foreach ($packContentIds as $tipusId) {
                    $result = $this->bookingService->replicateReservationToAnotherService($packReservationData, $tipusId, true);
                    if (!empty($result)) {
                        $results[] = $result;
                    }
                }

                if (!empty($results)) {
                    $GLOBALS["extraloginfo"] = "sikertelen csomag foglalás utáni törlés";
                    $this->bookingService->deleteReservation($packReservationId, $packReservationData["pass"], true);
                    Utils::jsonOut(["error" => implode("\n", $results), "message" => "", "html" => ""]);
                }

                //időpontok lefoglalása
                $lefoglalva = 0;
                $this->bookingService->replicateToFirstAvailableTime = false;
                foreach ($packContentIds as $tipusId) {
                    $this->bookingService->replicateReservationToAnotherService($packReservationData, $tipusId);
                    $lefoglalva++;
                }

                Utils::jsonOut(["error" => "", "message" => "{$moreMessage}Lefoglalva {$lefoglalva} időpont.", "html" => $this->showElojegyzesTableNew($_SESSION["setday"])]);
            } else {
                Utils::jsonOut(["error" => "A foglalás közben hiba történt!", "message" => "", "html" => ""]);
            }
        }

        if (isset($_REQUEST["refreshceglist"])) {
            $apiv2 = new DokirexService();
            $results = json_decode($apiv2->updateListCegek(), true);
            die();
        }

        if (isset($_REQUEST["listCegTelephelyByCegID"])) {
            $apiv2 = new DokirexService();

            echo "<pre>";
            print_r(json_decode($apiv2->listCegTelephelyByCegID(9), true));
            echo "</pre>";
        }

        if (isset($_REQUEST["listCeg"])) {
            $apiv2 = new DokirexService();

            echo "<pre>";
            print_r(json_decode($apiv2->listCeg(), true));
            echo "</pre>";
        }

        if (isset($_REQUEST["searchPatientDataIndokirex"])) {
            $apiv2 = new DokirexService();

            $params = array(
                "New" => "",
                "SzuletesiDatum" => "",
                "Taj" => ""
            );
            echo "<pre>";
            print_r(json_decode($apiv2->listPaciensByParams($params), true));
            echo "</pre>";
            die();
        }

        if (isset($_REQUEST["refreshmunkakorlist"]) && $_REQUEST["refreshmunkakorlist"] == true) {
            $apiv2 = new DokirexService();
            $apiv2->updateListMunkakor();
            die();
        }

        if (isset($_POST["syncreservation"])) {
            $fid = intval($_POST["syncreservation"]);

            $foService = new FoglaljOrvostService();
            $foService->modifyReservation($fid);

            $api = new BookingSyncApi();
            $api->modifyReservation($fid);
            die("syncok");
        }

        if (isset($_POST["printSpektrumlabMatrica"])) {
            $id = intval($_POST["printSpektrumlabMatrica"]);
            $pass = $_POST["p"];

            if ($id == 0 && $pass == "0") {
                $matrica = 'Ck4KUjAsMApCMjc5LDM0LDAsMiwyLDYsNjksQiwiMDAwNTQwOTI5MDAzIgpBNjE3LDU5LDEsNSwxLDEsTiwiNTQiCkEyMzMsMiwwLDIsMSwxLE4sImR4aDgwMCxkeGg5MDAiCkEzNDcsMTgsMCwyLDEsMSxOLCI1NC8wOS4yOS4iCkEyMzMsMTI4LDAsMywxLDEsTiwiRURUQSIKQTIzMywxNDksMCwyLDEsMSxOLCJUZXN6dCBFbGVrLzE5ODguMTEuMTUuIgpBMjMzLDE2NiwwLDIsMSwxLE4sIktlbHRleG1lZCBLZnQiClAxCg==';
                echo iconv("ISO-8859-2", "UTF-8", base64_decode($matrica));
                die;
            }

            if (!sql_query("select id from foglalasok where id=? and pass=?", [$id, $pass])->fetch(PDO::FETCH_ASSOC)) {
                echo "errorFoglalás nem található!";
                die;
            }

            if (!$codeData = sql_query("select matricacode from labrequests where foglalasid=? and provider='spektrumlab' limit 1", [$id])->fetch(PDO::FETCH_ASSOC)) {
                echo "errorLaborkérés nem található!";
                die;
            }
            $error = "";
            $matrica = $codeData["matricacode"];

            if (empty($matrica)) {
                $error = "Ehhez a kéréshez nem érkezett matrica.";
            }

            if (!empty($error)) {
                echo "error{$error}";
                die;
            }


            //header('Content-Type: text/html; charset=cp850');

            //echo mb_convert_encoding(base64_decode($matrica), "CP850", "UTF-8");
            echo utf8_encode(base64_decode($matrica));
            //echo base64_decode($matrica);
            die;

            //Utils::jsonOut(["error" => $error, "matrica" => iconv("CP850", "UTF-8", base64_decode($matrica))], "iso-8859-2");
        }
    }

    public function showPage()
    {
        echo "<div id='webshoplist'>" . $this->webShopService->showOrdersList() . "</div>";

        if ($this->adminUser->varoteremuiAccess()) {
            echo "<div id='waiting-room' style='z-index: 9999;background:white;".(!empty($_SESSION["setStyckyVarolista"]) ? "position:sticky;top:0":"")."'>" . $this->varoteremService->waitingRoom() . "</div>";
        }

        echo "<div id='elojegyzestable'>" . $this->showElojegyzesTableNew($this->setDay) . "</div>";
        echo "<div id='elojdialog' class='eloj_dialog'><div class='eloj_dialogtop' onclick='$(\".eloj_dialog\").hide();'></div><div class='eloj_dialogcontent'></div></div>";
        echo "<div id='elojloader' style='position:absolute;display:none'><img src='/admin/images/loading.svg' style='width: 20px;'/></div>";
        echo "<div id='idoponteditor'></div>";
    }

    private function elojegyzesRowClosed($oid, $tipusId)
    {
        return isset($_SESSION["closedbeotable"]["{$oid}_{$tipusId}"]);
    }


    private function _beoHash($beosztas): string
    {
        return md5($beosztas["orvosid"] . "_" . $beosztas["tol"] . "_" . $beosztas["ig"] . "_" . $beosztas["nap"] . "_" . $beosztas["beonap"] . "_" . $beosztas["binterval"]);
    }

    private function _extractTipusok($raw): array
    {
        $raw = str_replace("||", ",", $raw);
        $raw = str_replace("|", "", $raw);
        return array_unique(explode(",", $raw));
    }

    private function getAllPaymentData(): array
    {
        $result = [];
        $payments      = sql_query("SELECT foglalasid, osszeg, orderid, result FROM banktransactions WHERE merchant=? AND datum>DATE_SUB(NOW(), INTERVAL 1 YEAR) AND result IN ('finished','done')", [Booking_Constants::SIMPLEPAY_MERCHANT_ID])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($payments as $payment) {
            $result[$payment["orderid"]] = $payment;
        }
        return $result;
    }

    public function showElojegyzesTableNew($setDay):string {
        $startTime     = microtime(true);
        $settings      = new Booking_Settings();
        $htmlout       = "";
        $cimFilterHTML = $this->cimFilter();
        $cegFilterHTML = $this->cegFilter();
        $tipusLinks[0] = ["url" => "javascript:scrollToElement(\"filterbox\");", "nev" => "Oldal teteje"];
        $rendelesek    = 0;
        $helyszin      = intval($_SESSION["helyszin"]);
        $nap           = date("Y-m-d", strtotime($setDay));
        $wd            = date("N", strtotime($setDay));
        $tipusok       = $this->bookingService->tipusExtract($this->bookingService->beosztasService->getTipusByHelyszin($helyszin));
        $foglalasok    = $this->bookingService->getAllReservationForDayByDoctor($nap, $helyszin);
        $isHoliday     = in_array($nap, $settings->getMunkaszunetiNapok($helyszin));
        $maxOrvosId    = sql_query("select max(id)+1 from orvosok")->fetchColumn();
        $orvosList     = sql_query("select id, nev from orvosok where aktiv=1 order by nev")->fetchAll(PDO::FETCH_ASSOC);
        $existingOrvosTimes = [];
        $emptySection  = false;
        $ExtraButtons  = [];
        $orvosListed   = [];
        $counterStore  = [];
        $sectionName   = "";
        $cegSearchLink = "[<a href='#' onclick='elojegyzesCegSearchStart();return false;'>lista</a>]";
        $this->paymentData = $this->getAllPaymentData();

        $htmlout .= "<div id='filterbox' style='margin-top:10px;'>";
        $htmlout .= "<div style='display:table-cell;vertical-align:middle;'>" . $this->napFilter2($setDay) . "</div>";
        $htmlout .= "<div style='display:table-cell;vertical-align:middle;'><a onclick='setListDay(\"" . date("Y-m-d", strtotime("{$setDay} -1 day")) . "\");return false;' href='#'><img height='20' src='images/prev.png' title='Előző nap'/></a>&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $htmlout .= "<div style='display:table-cell;vertical-align:middle;'><input type='button' onclick='setListDay(\"" . date("Y-m-d") . "\");' value='MA' title='Ugrás a mai napra' />&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $htmlout .= "<div style='display:table-cell;vertical-align:middle;'><a onclick='setListDay(\"" . date("Y-m-d", strtotime("{$setDay} +1 day")) . "\");return false;' href='#'><img height='20' src='images/next.png' title='Következő nap'/></a></div>";
        $htmlout .= "<div style='display:table-cell;vertical-align:middle;padding-left:20px;'>{$cimFilterHTML}</div>";
        $htmlout .= "<div style='display:table-cell;vertical-align:middle;padding-left:20px;'>{$cegFilterHTML} {$cegSearchLink}</div>";

        if (in_array($nap, $settings->getMunkaszunetiNapok($helyszin))) {
            $htmlout .= "<div style='margin-top:10px;padding:5px 10px;background: #f00;color:#fff;font-size:18px;display:inline-block;'>Munkaszüneti nap!</div>";
        }

        $htmlout .= "</div>";

        //searchbox
        $htmlout .= "<div class='elojegyzessearchbox'>";
        $htmlout .= "<form name='keresform' method='post' onsubmit='elojegyzesSearchStart();return false;'>";
        $htmlout .= "<input type='text' value='{$_SESSION["esearchkey"]}' name='kereskulcs' id='eljegyzessearchkey' placeholder='keresés névre, taj számra, email címre, szül. dátumra..' style='width:300px;'/> <input style='padding:3px 10px;' type='submit' value='Keresés' name='keresgo' />";
        $htmlout .= "</form>";
        $htmlout .= "<div id='elojegyzessearchloading' style='display:none;'><img src='/images/loading.svg' alt='' style='width:30px;opacity: .5;padding:10px 0px;' /></div>";
        $htmlout .= "<div id='elojegyzessearchresult'></div>";
        $htmlout .= "</div>";

        $htmlout .= "<div class='stickytablefilter' id='stickytablefilter'>";
        $htmlout .= "<div class='tdm' style='padding:2px 10px 0px 0px;font-size: 16px;white-space: nowrap;'>" . $nap . "<br/>" . $this->adminUtils->settings->hetnap[$wd] . "</div>";
        $htmlout .= "<div class='tdm'>#tipuslinksplace#</div>";
        $htmlout .= "</div>";

        $htmlout .= "<table width='100%' cellpadding='0' cellspacing='0' border='0'>";

        sql_query("SET SESSION group_concat_max_len = 10000");

        if (empty($tipusok)) {
            $tipusok[] = 0;
        }

        $tipusNevek = [];
        $szuresTipusok = sql_query("select * from szurestipusok where id in (" . implode(",", $tipusok) . ") order by !instr(megnev,'üzemorvosi'), !instr(megnev,'Foglalkozás Egészségügyi'), !instr(megnev,'menedzser'), megnev")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($szuresTipusok as $szuresTipus) {
            $tipusNevek[$szuresTipus["id"]] = $szuresTipus["megnev"];
        }

        //$this->szuresTipusActual = $szuresTipus;
        $lastOrvosId = 0;
        $freeCounter = 0;
        $timeCounter = 0;
        $sectionNum  = 0;

        $beosztasok = $this->bookingService->beosztasService->getBookingPageBeosztasok($nap, $_SESSION["helyszin"]);
        foreach ($beosztasok as $beoKey => $beosztas) {
            if (in_array($this->_beoHash($beosztas), $orvosListed)) {
                continue;
            }

            $rendelesek++;
            $minTol             = "24:00";
            $maxIg              = "00:00";
            $maxPotIg           = "00:00";
            $beoId              = $beosztas["id"];
            $orvosId            = $beosztas["orvosid"];
            $orvosListed[]      = $this->_beoHash($beosztas);
            $binterval          = $beosztas["binterval"];
            $this->orvosTipusok = $this->_extractTipusok($beosztas["alltipus"]);
            $orvosNev           = empty(trim($beosztas["orvosnev"])) ? " Név nélküli orvos" : $beosztas["orvosnev"];
            $orvosTipusNevek    = [];
            $lastTipusNev       = "";
            $rendeloOrvosLink   = "<a target='_blank' href='{$_SERVER['PHP_SELF']}?page=doctors&szerk={$orvosId}'>{$orvosNev}</a>";
            //$addDoctorLink      = "<a class='orvosbutton' onclick=\"$('#adddoctordiv{$orvosId}').slideDown();return false;\" href='#'>+ orvos</a>";
            $szabi              = sql_fetch_array(sql_query("select * from szabadsag where datumtol<=? and datumig>=? and oid=?", [$nap, $nap, $beosztas["orvosid"]]));
            $szabiURL           = $szabi ? "szabadságon" : "<a onclick='return confirm(\"Biztos beállítod szabadságra erre a napra?\");' href='{$_SERVER['PHP_SELF']}?page={$_GET["page"]}&szabira={$nap}&orvosid={$orvosId}'>szabadságra</a>";
            $helyettesites      = sql_fetch_array(sql_query("select h.*, o.nev as helyettesitoorvos from helyettesites h left join orvosok o on o.id = h.helyettesitoorvosid where h.nap=? and h.oid=? and h.beoid=?", [$nap, $beosztas["orvosid"], $beoId]));
            $helyettesitesLink  = "<a class='orvosbutton' onclick=\"$('#helyettesitesdiv{$orvosId}_{$beoId}').slideDown();return false;\" href='#'>Helyettesítés</a>";
            $szemelyzetLink     = "<a class='orvosbutton' onclick=\"$('#szemelyzetdiv{$beoId}').slideDown();return false;\" href='#'>Személyzet</a>";
            $printBeoLink       = "<a class='orvosbutton' target='_blank' href='index.php?page={$_GET["page"]}&printbeoreservations={$beoId}&nap={$nap}'>Nyomtatás</a>";
            $printBeoPdfLink    = "<a class='orvosbutton' target='_blank' href='index.php?page={$_GET["page"]}&printbeopdf={$beoId}&nap={$nap}'>Adatlapok</a>";
            $addDoctorLink      = "";

            foreach ($this->orvosTipusok as $tipusId) {
                if (isset($tipusNevek[$tipusId])) {
                    $orvosTipusNevek[] = $tipusNevek[$tipusId];
                    $lastTipusNev = $tipusNevek[$tipusId];
                }
            }

            if (empty($orvosTipusNevek)) {
                continue;
            }

            $szuresTipus["id"] = $beosztas["id"];
            $szuresTipus["megnev"] = implode(", ", $orvosTipusNevek);

            if ($beosztas["extrabuttonrequired"] == 1) {
                $ExtraButtons[] = array("id" => $orvosId, "nev" => $orvosNev, "free" => 1);
            }

            if (isset($helyettesites["id"])) {
                $helyettesitesLink = "";
            }

            if ($beosztas["pecsetszam"] == "temp") {
                $rendeloOrvosLink = "<a target='_blank' href='#' title='Orvos eltávolítása' onclick=\"$('#editdoctordiv{$orvosId}').slideDown();return false;\">- {$orvosNev}</a>";
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

            //if (session_id() == "49kok57aarptr23mjohbmbt7lq") {
            //    $htmlout.= "<pre>".print_r($beosztas, true)."</pre>";
            //}

            if ($lastOrvosId != $orvosId || Booking_Constants::SQL_DB == "keltexmed") {
                $lastOrvosId = $orvosId;
                $existingOrvosTimes = [];
                $sectionNum++;
                $sectionName = "tpid{$orvosId}_{$sectionNum}";
                $freeCounter = 0;
                $timeCounter = 0;

                $htmlout .= "<div class='etabletipushead' id='{$sectionName}'>";

                $htmlout .= "<div style='display:table-cell;vertical-align:middle;cursor:pointer;font-size:32px;padding:0px 10px 0px 10px;' onclick=\"toggleElojegyzesTableNaptar({$orvosId}, {$sectionNum});\"><i id='tablenyito{$orvosId}_{$sectionNum}' class='tablenyito fas fa-chevron-up' style='" . ($this->elojegyzesRowClosed($orvosId, $szuresTipus["id"]) ? "transform:rotate(180deg);" : "") . "'></i></div>";
                $htmlout .= "<div style='display:table-cell;vertical-align:top;'>";
                $htmlout .= "<div id='orvosdiv{$orvosId}' style='font-size:16px;font-weight:bold;'>{$rendeloOrvosLink}&nbsp;" . implode(", ", $orvosTipusNevek) . "&nbsp;&nbsp;{$addDoctorLink} {$helyettesitesLink} {$szemelyzetLink} {$printBeoLink}";
                if (Booking_Constants::SQL_DB == "hungariamed" && in_array($helyszin, [679, 681, 682, 678, 683, 684, 685, 686, 687, 689, 690, 693, 688, 696, 697, 701, 699, 702, 948, 949, 950, 951, 952, 953, 954, 955, 956, 957, 958])) {
                    $htmlout.= " {$printBeoPdfLink}";
                }
                $htmlout .= "</div>";
                $htmlout .= "<div>#foglalt{$orvosId}_{$sectionNum}# #szabad{$orvosId}_{$sectionNum}#</div>";
                $htmlout .= "<div>{$beosztas["description"]}</div>";

                //if (Booking_Constants::SQL_DB == "keltexmed" && in_array($orvosId, [399, 416, 417]) && in_array($nap, ["2022-07-18", "2022-07-19", "2022-07-20", "2022-07-21", "2022-07-22"])) {
                //    $htmlout .= "<div style='padding:2px 0px;'><span style='color:#fff;background:#f00;padding:2px 5px;'>DR. KIZMAN EZEN A NAPON NEM ELÉRHETŐ, EZÉRT TÜDŐSZŰRÉSRE NEM LEHET FOGLALNI!</span></div>";
                //}

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

                $htmlout .= "<div id='helyettesitesdiv{$orvosId}_{$beoId}' style='display:none;margin:10px 0px;padding:10px 0px;border-top:1px solid #888;border-bottom:1px solid #888;'>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Helyettesítő orvos:</div><div class='tdm' style='padding:2px 0px;'><select id='helyettesitoorvosid{$orvosId}'>{$orvosOptions}</select></div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Megjegyzés: </div><div class='tdm' style='padding:2px 0px;'><input type='text' id='orvosmegj{$orvosId}' style='width:300px;'/></div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'></div><div class='tdm' style='padding:2px 0px;'><input onclick=\"addReplaceDoctor('{$nap}', {$helyszin}, {$beoId}, {$orvosId});\" type='button' value='Helyettesítés megadása' /> <input onclick=\"$('#helyettesitesdiv{$orvosId}_{$beoId}').slideUp()\" type='button' value='mégsem' /></div></div>";
                $htmlout .= "</div>";

                $szemelyzetData = sql_query("SELECT * FROM szemelyzet WHERE beoid=? AND nap=? AND helyszinid=?", [$beoId, $nap, $helyszin])->fetch();

                $htmlout .= "<div id='szemelyzetdiv{$beoId}' style='display:none;margin:10px 0px;padding:10px 0px;border-top:1px solid #888;border-bottom:1px solid #888;'>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Orvos neve:</div><div class='tdm' style='padding:2px 0px;'><input type='text' id='orvosnev{$beoId}' value='" . (!empty($szemelyzetData["orvosnev"]) ? $szemelyzetData["orvosnev"] : "") . "' style='width:300px;'/></div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Asszisztens neve: </div><div class='tdm' style='padding:2px 0px;'><input type='text' id='asszisztensnev{$beoId}' value='" . (!empty($szemelyzetData["asszisztensnev"]) ? $szemelyzetData["asszisztensnev"] : "") . "' style='width:300px;'/></div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'></div><div class='tdm' style='padding:2px 0px;'><input onclick=\"addCrewData('{$nap}', {$helyszin}, {$beoId}, $('#orvosnev{$beoId}').val(),$('#asszisztensnev{$beoId}').val());\" type='button' value='Szeméyzet megadása' /> <input onclick=\"$('#szemelyzetdiv{$beoId}').slideUp()\" type='button' value='mégsem' /></div></div>";
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
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Név:</div><div class='tdm' style='padding:2px 0px;'><input type='text' id='editorvosnev{$orvosId}' value='{$orvosNev}'/></div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Megjegyzés: </div><div class='tdm' style='padding:2px 0px;'><input type='text' id='editorvosmegj{$orvosId}' style='width:300px;' value='{$beosztas["orvosdescription"]}' /></div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'>Rendelési idő: </div><div class='tdm' style='padding:2px 0px;'>" . $this->rendIdoSelect("editorvostol{$orvosId}", $beosztas["tol"]) . " - " . $this->rendIdoSelect("editorvosig{$orvosId}", $beosztas["ig"]) . "</div></div>";
                $htmlout .= "<div style='display:table-row;'><div class='tdm'></div><div class='tdm' style='padding:2px 0px;'><input onclick='saveTempDoctor({$orvosId});' type='button' value='Mentés' /> <input onclick=\"removeTempDoctor('{$nap}', {$orvosId});\" type='button' value='Orvos törlése' /> <input onclick=\"$('#editdoctordiv{$orvosId}').slideUp()\" type='button' value='mégsem' /></div></div>";
                $htmlout .= "</div>";

                $htmlout .= "</div>";
                $htmlout .= "</div>";
            }



            if ($minTol != "24:00") {
                $htmlout .= "<div class='beotable{$orvosId}_{$sectionNum}' style='" . ($this->elojegyzesRowClosed($orvosId, $sectionNum) ? "display:none;" : "") . "'>";

                if (!empty($existingOrvosTimes) && !$emptySection) {
                    $emptySection = true;
                    $htmlout .= "<div style='border-top:1px solid #ccc;marign-top:3px;padding-top:3px;width:100%;'></div>";
                }

                $beoComment = trim($beosztas["bmegj"]);
                if (!empty($beoComment) && $this->adminUser->allCegJog()) {
                    //$beoComment .= " ({$minTol} - {$maxIg})";
                    $htmlout .= "<div style='margin:5px 0px;padding:2px 5px;background: red;color:#fff;display: inline-block;'>{$beoComment}</div>";
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

                    //1405 1406;
                    if (substr_count(strtolower($lastTipusNev), "csomag")) {
                        $this->orvosTipusok[] = 0;
                    }

                    $this->addIdopontJavaScript = "setSelectedOrvos({$beosztas["orvosid"]});setSelectedInterval({$binterval});addIdopont(\"{$nap} {$ora}\", \"" . implode(",", $this->orvosTipusok) . "\", this);return false;";
                    if ($isHoliday) {
                        $this->addIdopontJavaScript = "if (confirm(\"Ez munkaszüneti nap, biztos foglalsz?\")) { {$this->addIdopontJavaScript} } return false;";
                    }

                    $reservations = sql_query("select f.*, c.megnev as cegnev, o.nev as orvosnev, d.id as docid, sz.megnev as szurestipusnev, if(f.telephelyid=0, f.telephely, v.megnev) as telephely from foglalasok f 
                        left join cegek c on c.id=f.cegid
                        left join szurestipusok sz on sz.id=f.szurestipusid
                        left join orvosok o on o.id=f.orvosassigned
                        left join dokumentumok d on d.foglalasid=f.id
                        left join cegvars v on v.id=f.telephelyid                     
                        where f.datum>=? and f.datum<? and (f.helyszinid=? or sz.webdoktor=1) and f.orvosassigned in (0, ?) 
                        group by f.id order by f.datum", [$timeFrom, $timeTo, $_SESSION["helyszin"], $orvosId])->fetchAll(PDO::FETCH_ASSOC);

                    $this->lastIdopont = "";
                    $this->foglalasButtonVolt = 0;
                    foreach ($reservations as $reservation) {
                        if (in_array($reservation["id"], $this->displayedReservations) && $beosztas["pecsetszam"] == "temp") {
                            continue;
                        }
                        if (isset($foglalasok[$reservation["orvosassigned"]][$reservation["id"]])) {
                            unset($foglalasok[$reservation["orvosassigned"]][$reservation["id"]]);
                        }
                        $htmlout .= $this->elojegyzesTableRow($reservation, $ora, $binterval);
                        $this->displayedReservations[] = $reservation["id"];
                    }

                    if ($this->lastIdopont == "") {
                        //nem volt foglalás, üres időpont kirakás
                        $htmlout .= "<tr style=''>";
                        $htmlout .= "<td valign='top' nowrap style=''>" . $this->idopontStatusIcon() . "&nbsp;<span style=\"" . $this->datePastStyle($nap, $ora) . "\">{$ora}" . ($this->potIdopont ? " <span title='pótidőpont'>(p)</span>" : "") . "&nbsp;&nbsp;</span></td>";
                        $htmlout .= "<td valign='top'><a onclick='{$this->addIdopontJavaScript}' class='iconbutton' title='foglalás' href='#'><i class='fas fa-plus-square'></i></a>&nbsp;&nbsp;</td>";
                        if ($szabi) {
                            $htmlout .= "<td valign='top'>Szabadság miatt nem foglalható</td>";
                        }
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

                $counterStore["{$orvosId}_{$sectionNum}"]["foglalt"] = $timeCounter;
                $counterStore["{$orvosId}_{$sectionNum}"]["szabad"] = $freeCounter;
            }

            foreach ($this->orvosTipusok as $tipusId) {
                if (!isset($tipusLinks[$tipusId])) {
                    $tipusLinks[$tipusId]["url"] = "javascript:scrollToElement(\"{$sectionName}\");";
                    $tipusLinks[$tipusId]["nev"] = $tipusNevek[$tipusId];
                }
                $tipusLinks[$tipusId]["freepart"][$orvosId] = $freeCounter;
            }

            $htmlout .= "</td>";
            $htmlout .= "</tr>";

            //beosztás variálás miatt esetleg nem megjelenő foglalások
            if (isset($beosztasok[($beoKey + 1)]["orvosid"]) && $beosztasok[($beoKey + 1)]["orvosid"] != $orvosId) {
                if (!empty($foglalasok[$orvosId])) {
                    $htmlout .= "<tr>";
                    $htmlout .= "<td>";
                    $htmlout .= "<div style='padding:4px 0px;'>Beosztáson kívüli foglalások:</div>";
                    $htmlout .= "<table cellpadding='0' cellspacing='0'>";
                    foreach ($foglalasok[$orvosId] as $foglalas) {
                        $htmlout .= $this->elojegyzesTableRow($foglalas, date("H:i", strtotime($foglalas["datum"])), 0, true);
                    }
                    $htmlout .= "</table>";
                    $htmlout .= "</td>";
                    $htmlout .= "</tr>";
                }
                if (isset($foglalasok[$orvosId]) && empty($foglalasok[$orvosId])) {
                    unset($foglalasok[$orvosId]);
                }
            }
        }

        if (!empty($foglalasok) && !$this->adminUser->onlyDoctorReservations()) {
            $this->showInterval = true;
            $this->showDoctorName = true;

            //beosztásban nem szereplő orvosok esetleges foglalásai

            $otherHtml = "";
            $exists = false;
            foreach ($foglalasok as $orvosFoglalasok) {
                $otherHtml .= "<table cellpadding='0' cellspacing='0'>";
                foreach ($orvosFoglalasok as $foglalas) {
                    $exists = true;
                    $otherHtml .= $this->elojegyzesTableRow($foglalas, date("H:i", strtotime($foglalas["datum"])), 0, true);
                }
                $otherHtml .= "</table>";
            }

            if ($exists) {
                $htmlout .= "<tr>";
                $htmlout .= "<td>";
                $htmlout .= "<div style='border-top:1px solid #888;margin-top:10px;padding:10px 0px 10px 0px;font-weight: bold;;'>Egyéb foglalások:</div>";
                $htmlout .= $otherHtml;
                $htmlout .= "</td>";
                $htmlout .= "</tr>";
            }
        }

        $htmlout .= "</table>";

        if ($rendelesek == 0) {
            $htmlout .= "<div style='margin-top:30px;'>Ezen a napon nincs rendelés a kiválasztott helyszínen.</div>";
        }

        if (count($tipusLinks) > 1) {
            $links = [];
            foreach ($tipusLinks as $link) {
                $free = 0;
                if (!empty($link["freepart"])) {
                    foreach ($link["freepart"] as $freePart) {
                        $free += $freePart;
                    }
                }

                $links[] = "<a class='tipuslink' href='{$link["url"]}'>{$link["nev"]} <span style='" . ($free == 0 ? "font-weight:bold;border-radius:20px;background:#888;color:#fff;opacity:.3;" : "font-weight:bold;border-radius:20px;background:#0a0;color:#fff;") . "'>" . ($free != 0 ? "&nbsp;{$free}&nbsp;" : "") . "</span></a>";
            }

            //Extra gyors gombok beillesztése:
            $links = $this->addExtraShortCutLinks($links, $ExtraButtons);

            $htmlout = str_replace("#tipuslinksplace#", "<div class='tipuslinksbox'>" . implode(" ", $links) . "</div>", $htmlout);
        } else {
            $htmlout = str_replace("#tipuslinksplace#", "", $htmlout);
        }

        if ($this->adminUser->statAccess() && strtotime($nap) < strtotime(date("Y-m-d"))) {
            $htmlout .= "<div style='margin-top:20px;padding-top:20px;border-top:1px solid #888;'>";
            $htmlout .= "<div class='dailysmallbutton' data-day='dayvalid' onclick='downloadDailyStat(\"$nap\", \"$nap\")' title='Napi statisztika letöltése'><i class='fas fa-file-download'></i> napi statisztika {$nap}</div>&nbsp;&nbsp;";
            $htmlout .= "<div class='dailysmallbutton' data-day='dayvalid' onclick='downloadElojegyzesTable(\"$nap\", \"$nap\")' title='Előjegyzés tábla export'><i class='fas fa-file-download'></i> előjegyzés tábla export {$nap}</div>";
            $htmlout .= "</div>";
        }

        //$htmlout.= "<pre>".print_r($counterStore, true)."</pre>";

        foreach ($counterStore as $section => $counter) {
            $htmlout = str_replace("#foglalt{$section}#", "{$counter["foglalt"]} foglalt", $htmlout);
            $htmlout = str_replace("#szabad{$section}#", "{$counter["szabad"]} szabad", $htmlout);
        }

        $endTime = microtime(true);

        //if (session_id() == "cg4lvnhsh3it0npor9jngbo6hf") {
            $htmlout.= "<div style='margin-top:10px;padding:10px;background:#eee;'>Execution time: " . round($endTime - $startTime, 2) . " sec</div>";
        //}

        return $htmlout;
    }

    private function addExtraShortCutLinks($links = array(), $ExtraButtons = array())
    {
        $ExtraButtons = array_unique($ExtraButtons, SORT_REGULAR);
        foreach ($ExtraButtons as $link) {
            $url = "javascript:scrollTo(\"orvosdiv{$link["id"]}\");";
            $extraLink = "<a class='tipuslink' href='{$url}'>{$link["nev"]} <span style='font-weight:bold;border-radius:20px;background:#0a0;color:#fff;'></span></a>";
            $links[] = $extraLink;
        }
        return $links;
    }


    private function datePastStyle($nap, $ora): string
    {
        return strtotime("now") > strtotime("{$nap} {$ora}") && date("Y-m-d") == $nap ? "color:#aaa;" : "";
    }


    private $lastIdopont;
    private $foglalasButtonVolt;
    private $addIdopontJavaScript;
    private $potIdopont;
    private array $displayedReservations = [];
    private array $orvosTipusok = [];
    private bool $showDoctorName = false;
    private bool $showInterval = false;
    private array $paymentData;

    private function elojegyzesTableRow($reservationData, $ora, $binterval, $noAdd = false): string
    {
        $nap = date("Y-m-d", strtotime($reservationData["datum"]));
        //$ora = date("H:i", strtotime($rowf["datum"]));

        $htmlout = "";
        $eljottText = "";
                                                                                                                                                                                                        /*Magyarállamkincstárosok nem látják hogy nem jött el :P*/
        if ($reservationData["eljott"] == 0 && !empty($reservationData["nev"]) && $reservationData["nev"] != "nincs név" && strtotime("now - 10 minute") > strtotime($reservationData["datum"]) && strpos($_SESSION["adminuser"]["email"],"@allamkincstar.gov.hu")===false) {
            $eljottText = "<span style='color:red;border:1px solid red;padding:0px 2px;'>nem jött el</span> ";
        }

        if ($munkakorVizsgalat = $this->bookingService->munkakorVizsgalatok->getMunkakorVizsgalat($reservationData["munkakor"])) {
            $reservationData["megj"] = "<span title='Munkakör alapján szükséges plusz vizsgálatok' style='cursor:pointer;background:red;color:white;border:1px solid red;padding:0px 2px;'>+ {$munkakorVizsgalat}</span> " . $reservationData["megj"];
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
            $companyWarning = "<a onclick='{$detailURL}' href='#'><i title='" . implode("\n", $warnings) . "' class='fas fa-exclamation-circle'></i></a>&nbsp;";
        }

        $htmlout .= "<tr style=''>";
        $htmlout .= "<td valign='top' nowrap style=''>" . $this->idopontStatusIcon($reservationData) . "&nbsp;<span style=\"" . $this->datePastStyle($nap, $ora) . "\">" . ($idopontShow != $this->lastIdopont ? $idopontShow . ($this->potIdopont ? "&nbsp;<span title='pótidőpont'>(p)</span>" : "") : "") . "&nbsp;&nbsp;</span></td>";
        $htmlout .= "<td valign='top' nowrap>";
        if ($this->foglalasButtonVolt == 0 && "{$nap} {$idopontShow}" == "{$nap} {$ora}" && !$noAdd) {
            $htmlout .= "<a onclick='{$this->addIdopontJavaScript}' class='iconbutton' title='foglalás' href='#'><i class='fas fa-plus-square'></i></a>&nbsp;&nbsp;";
            $this->foglalasButtonVolt = 1;
        }
        $htmlout .= "</td>";
        if ($jogosult) {
            $htmlout .= "<td valign='top' nowrap><a onclick='removeIdopont({$reservationData["id"]},\"{$reservationData["pass"]}\",\"booking\", this);return false;' class='iconbutton' title='foglalás törlése' href='#'><i class='fas fa-minus-square'></i></a>&nbsp;&nbsp;</td>";
            $htmlout .= "<td valign='top' nowrap>";

            if ($this->showDoctorName) {
                $htmlout .= "{$reservationData["orvosnev"]}&nbsp;";
            }

            if ($reservationData["rinterval"] != $binterval || $this->showInterval) {
                $htmlout .= "({$reservationData["rinterval"]} perc) ";
            }

            $kidSign = "";
            $tudoszuroSign = $reservationData["tudoszuro"] != 0 ? " <i title='tüdőszűrés kell' class='fas fa-lungs'></i>" : "";
            $hallasSign = $reservationData["kieg_hallas"] != 0 ? " <i title='hallásvizsgálat kell' class='fas fa-headphones'></i>" : "";
            $laborSign = $reservationData["kieg_labor"] != 0 ? " <i title='laborvizsgálat kell' class='fas fa-flask'></i>" : "";
            $docSign = $reservationData["docid"] != null ? " <i title='file' class='fas fa-file'></i>" : "";

            $extraInfo = "";

            if (isset($this->paymentData[$reservationData["bankorderid"]]) && $reservationData["bankorderid"]!=null) {
                $osszeg = number_format($this->paymentData[$reservationData["bankorderid"]]["osszeg"]);
                $extraInfo .= "<span class='externalmark' style='background-color:#0a0'>FIZETVE! (" . $osszeg . " Ft)</span> ";
            }

            if (!empty($reservationData["szuldatum"]) && strtotime("now") - strtotime($reservationData["szuldatum"]) < 567648000 && strtolower($reservationData["nev"]) != "szünet") {
                $kidSign = " <i class='fas fa-child' title='Fiatalkorú (18 év alatti)'></i>";
                $extraInfo .= "(18 év alatti!) ";
            }

            if (!empty($reservationData["alkalmassag"])) {
                $title = $this->settings->alkalmassagvariaciok[$reservationData["alkalmassag"]];
                $acolor = "#0a0";
                if ($reservationData["alkalmassag"] != "I") {
                    $acolor = "#a00";
                }
                $htmlout .= "<span title='{$title}' style='color:{$acolor};'><i class='fas fa-circle'></i></span>&nbsp;&nbsp;";
            }

            if (!empty($reservationData["dokirex_userid"]) && !in_array($reservationData["dokirex_userid"], array(-1, -2, -3))) {
                $htmlout .= "<img height=\"13px\" src=\"https://dokirex.hu/favicon.ico\" title='dokirex-el szinkronizálva'>&nbsp;";
            }

            if (count($this->orvosTipusok) > 1) {
                $htmlout .= "{$reservationData["szurestipusnev"]}&nbsp;&nbsp;";
                $htmlout .= "</td><td valign='top' nowrap>";
            }

            $htmlout .= "<a id='det{$reservationData["id"]}' onclick='{$detailURL}' href='#' style='" . ($reservationData["nev"] == "Foglalt" ? "color:#aaa;" : "") . "'>{$reservationData["nev"]}</a>{$kidSign}{$tudoszuroSign}{$hallasSign}{$laborSign}{$docSign}&nbsp;&nbsp;";

            if (!empty($reservationData["externalid"])) {
                $htmlout .= "<span class='externalmark' title='foglalás forrása'>" . str_replace("hungariamed", "hmm", preg_replace('/[0-9]+/', '', $reservationData["externalid"])) . "</span>&nbsp;&nbsp;";
            }

            if ($reservationData["foglalta"] == "foglaljorvost") {
                $htmlout .= "<span class='externalmark' title='foglaljorvost foglalás'>FO</span>&nbsp;&nbsp;";
            }
            if ($reservationData["foglalta"] == "sanitas") {
                $htmlout .= "<span class='externalmark' title='foglaljorvost foglalás'>SANITAS</span>&nbsp;&nbsp;";
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

            $htmlout .= "<td valign='top' nowrap>{$eljottText}";
            if ($this->adminUser->paciensMegjegyzesAccess()) {
                $htmlout .= "{$extraInfo} {$reservationData["megj"]}";
            }
            $htmlout .= "</td>";
        } else {
            $htmlout .= "<td colspan='2' valign='top'><span style='color:#aaa;'>Másik cég foglalása</span>&nbsp;&nbsp;</td>";
        }
        $this->lastIdopont = $idopontShow;

        $htmlout .= "</tr>";

        return $htmlout;
    }

    private function napFilter2($setDay)
    {
        $html = "";
        $w = date("N", strtotime($setDay));
        $html .= "<input class='napfilter' id='napfilter' value='{$setDay} {$this->adminUtils->settings->hetnap[$w]}' style='width:220px;font-size:18px;background-color:#eee;color:#444;margin-right:10px;border:1px solid #ccc;' data-page='{$_GET["page"]}' />";
        return $html;
    }

    private function cegFilter()
    {
        $html = "";
        $html .= "<select class='s2 companyselector2' name='ecegfilter' onchange=\"window.location.href='index.php?page={$_GET["page"]}&ecegfilter='+this.value;\">";
        $html .= "<option value=''>Szűrés cégre</option>";

        $companies = $this->bookingService->beosztasService->getPlaceCompanies($_SESSION["helyszin"]);
        foreach ($companies as $company) {
            if (empty($company["megnev"])) {
                continue;
            }
            $html .= "<option value='{$company["id"]}'" . ($_SESSION["ecegfilter"] == $company["id"] ? " selected" : "") . ">{$company["megnev"]}</option>";
        }

        $html .= "</select>";
        return $html;
    }


    private function cimFilter(): string
    {
        $html = "";

        //$html.= print_r($this->adminUser->getCegListArray(), true);

        $html.= "<select class='s2 addressselector2' name='helyszin' style='width:300px;' onchange='setHelyszin2(this.value);'>";
        $html.= "<option value='0'>Válassz helyszínt!</option>";

        $order = "h.id not in (1), h.id not in (100, 644), trim(h.cim)";
        if (Booking_Constants::SQL_DB == "keltexmed") {
            $order = "h.id not in (292, 328), trim(h.cim)";
        }

        $res = sql_query("SELECT h.* FROM helyszinek h WHERE true ORDER BY {$order}");
        while ($placeData = sql_fetch_array($res)) {
            if (!$this->adminUser->allCegJog()) {
                $cegidk = $this->adminUser->getCegListArray();
                $cegJog = false;
                foreach ($cegidk as &$val) {
                    if (substr_count($placeData["ceglink"], "|{$val}|") && $val != "") {
                        $cegJog = true;
                    }
                }
                if (!$cegJog) {
                    continue;
                }
            }

            if ($_SESSION["helyszin"] == 0 && $placeData["id"] == Booking_Constants::DEFAULT_PLACE_IDS[0]) {
                //default cím beállítása
                $_SESSION["helyszin"] = $placeData["id"];
            }

            $html .= "<option value='{$placeData["id"]}-0'" . ("{$_SESSION["helyszin"]}-0" == "{$placeData["id"]}-0" ? " selected" : "") . ">{$placeData["cim"]}</option>";
        }
        $html .= "</select>";
        return $html;
    }

    private function rendIdoSelect($id, $selectedTime)
    {
        $html = "";
        $html .= "<select name='{$id}' id='{$id}'>";
        $html .= "<option value='0'>Válassz!</option>";
        for ($n = 0; $n <= 1065; $n += 5) {
            $t = date("H:i", mktime(6, 0 + $n, 0, 1, 1, date("Y")));
            $html .= "<option value='{$t}'" . ($selectedTime == $t ? " selected" : "") . ">{$t}</option>";
        }
        $html .= "</select> ";
        return $html;
    }

    private function rendIntervalSelect($id, $selectedInterval)
    {
        $html = "";
        $html .= "<select title='egy kezelés időtartama' id='{$id}'>";
        foreach ($this->adminUtils->settings->validIntervals as $interval) {
            $html .= "<option value='{$interval}'" . ($selectedInterval == $interval ? " selected" : "") . ">{$interval} perc</option>";
        }
        $html .= "</select> ";
        return $html;
    }

    private function idopontStatusIcon($reservationData = array())
    {
        //Mikor bepipálja  hogy eljött: <i class="fa-solid fa-user"></i>
        //Mikor berakja kittike a váróba: <i class="fa-solid fa-user-clock"></i>
        //Mikor bent van a vizsgálaton: <i class="fa-solid fa-user-doctor"></i>
        //Mikor a páciensel vizsgálaton végeztek: <i class="fa-solid fa-user-check"></i>
        $icon = "&nbsp;&nbsp;&nbsp;";
        $statusIconArray = array(
            0 => array("status" => "eljott", "icon" => "<i title=\"Eljött\" class=\"fa-solid fa-user\"></i>"),
            1 => array("status" => "varakozik", "icon" => "<i title=\"Várakozik\" class=\"fa-solid fa-user-clock\"></i>"),
            2 => array("status" => "vizsgalaton", "icon" => "<i title=\"Vizsgálaton\" class=\"fa-solid fa-user-doctor\"></i>"),
            3 => array("status" => "vizsgalat_kesz", "icon" => "<i title=\"Vizsgálat kész\" class=\"fa-solid fa-user-check\"></i>")
        );

        //Ha nem én, jani, kitti, vagy kisg ne jelenítsen meg semmit.
        if (!in_array($_SESSION["adminuser"]["id"], array(280, 243, 1, 51))) {
            return $icon;
        }

        if (!empty($reservationData)) {
            if ($reservationData["eljott"] == 1) {
                $icon = $statusIconArray["0"]["icon"];
            }

            if ($varoteremData = sql_query("SELECT * FROM varoterem WHERE fid=? ORDER BY id DESC LIMIT 1", array($reservationData["id"]))->fetch(PDO::FETCH_ASSOC)) {
                $key = array_search($varoteremData["statusz"], array_column($statusIconArray, "status"));
                if ($key !== false) {
                    $icon = $statusIconArray[$key]["icon"];
                }
            }
        }

        return $icon;
    }


    public function printBeoReservations():string {
        $beoId = $_GET["printbeoreservations"];
        $nap = $_GET["nap"];
        $timeFrom = "{$nap} 00:00:00";
        $timeTo = "{$nap} 23:59:59";

        $html = "";

        if (!$beoData = sql_query("select * from orvos_beosztas_new where id=?", [$beoId])->fetch(PDO::FETCH_ASSOC)) {
            die("beo not found");
        }

        if (!isset($_SESSION["adminuser"])) {
            die("error 401");
        }

        $orvosId = $beoData["orvosid"];

        $reservations = sql_query("SELECT f.*, c.megnev as cegnev, o.nev as orvosnev, d.id as docid, sz.megnev as szurestipusnev, if(f.telephelyid=0, f.telephely, v.megnev) as telephely from foglalasok f
                        LEFT JOIN cegek c on c.id=f.cegid
                        LEFT JOIN szurestipusok sz on sz.id=f.szurestipusid
                        LEFT JOIN orvosok o on o.id=f.orvosassigned
                        LEFT JOIN dokumentumok d on d.foglalasid=f.id
                        LEFT JOIN cegvars v on v.id=f.telephelyid
                        WHERE f.datum>=? and f.datum<? and (f.helyszinid=? or sz.webdoktor=1) and f.orvosassigned in (0, ?) and f.nev<>'nincs név'
                        GROUP BY f.id order by f.datum", [$timeFrom, $timeTo, $_SESSION["helyszin"], $orvosId])->fetchAll(PDO::FETCH_ASSOC);

        echo "<style> .dobozok {border:1px solid #888;padding:3px;} </style>";

        echo "<table style='border-collapse: collapse;font-size:12px;'>";
        echo "<tr style='font-weight: bold;background:#ccc;'>";
        echo "<td class='dobozok'>Név</td>";
        echo "<td class='dobozok'>TAJ</td>";
        echo "<td class='dobozok'>Anyja neve</td>";
        echo "<td class='dobozok'>Születési hely</td>";
        echo "<td class='dobozok'>Születési idő</td>";
        echo "<td class='dobozok'>Lakcím</td>";
        echo "</tr>";
        foreach ($reservations as $reservation) {
            echo "<tr>";
            echo "<td class='dobozok'>{$reservation["nev"]}</td>";
            echo "<td class='dobozok'>{$reservation["taj"]}</td>";
            echo "<td class='dobozok'>{$reservation["anyjaneve"]}</td>";
            echo "<td class='dobozok'>{$reservation["szulhely"]}</td>";
            echo "<td class='dobozok'>{$reservation["szuldatum"]}</td>";
            echo "<td class='dobozok'>{$reservation["irsz"]} {$reservation["varos"]} {$reservation["utca"]}</td>";
            echo "</tr>";
        }
        echo "</table>";

        //$html.= "<pre>".print_r($reservations, true)."</pre>";

        return $html;
    }


}
