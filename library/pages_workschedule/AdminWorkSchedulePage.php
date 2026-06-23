<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminWorkSchedulePage extends AdminCorePage {
    private WorkScheduleService $workScheduleService;
    private WorkersSubPage $workersSubPage;
    private VacationSubPage $vacationSubPage;
    private WorkplacesSubPage $workplacesSubPage;
    private NotifySubPage $notifySubPage;
    private PrintSubPage $printSubPage;
    private string $subPage = "beosztasok";

    private array $napszakok = ["Délelőtt", "Délután", "Külső"];

    public function __construct()
    {
        parent::__construct();

        $this->workScheduleService = new WorkScheduleService();
        $this->workersSubPage = new WorkersSubPage($this->workScheduleService);
        $this->vacationSubPage = new VacationSubPage($this->workScheduleService);
        $this->workplacesSubPage = new WorkplacesSubPage($this->workScheduleService);
        $this->notifySubPage = new NotifySubPage($this->workScheduleService);
        $this->printSubPage = new PrintSubPage($this->workScheduleService);
        $this->settings = new Booking_Settings();

        if (!isset($_SESSION["wpoffset"])) {
            $_SESSION["wpoffset"] = 0;
        }

        if (isset($_GET["setwpoffset"])) {
            $_SESSION["wpoffset"] = intval($_GET["setwpoffset"]);
        }

        if (isset($_GET["subpage"])) {
            $this->subPage = $_GET["subpage"];
        }

        if (isset($_GET["scheduletoken"]) && isset($_POST["publicrequstvacation"])) {
            $workerData = sql_query(
                "select * from schedule_workers w where concat(sha1(concat(w.id, w.roleid, w.email, w.tel)), md5(concat(w.email, w.tel))) = ?",
                [$_GET["scheduletoken"]]
            )->fetch();
            if (!$workerData) {
                Utils::jsonOut(["status" => "error", "message" => "Érvénytelen azonosító!"]);
            }
            $this->_publicApiRequestVacation($workerData);
        }

        if (isset($_GET["getweekdata"])) {
            $this->_apiGetWeekData();
        }

        if (isset($_POST["savebooking"])) {
            $this->_apiSaveBooking();
        }

        if (isset($_GET["copyweekjson"])) {
            $this->_apiCopyWeek();
        }

        if (isset($_GET["getstaff"])) {
            $this->_apiGetStaff();
        }

        if (isset($_POST["savestaff"])) {
            $this->_apiSaveStaff();
        }

        if (isset($_POST["deletestaff"])) {
            $this->_apiDeleteStaff();
        }

        if (isset($_GET["getplaces"])) {
            $this->_apiGetPlaces();
        }

        if (isset($_POST["addplace"])) {
            $this->_apiAddPlace();
        }

        if (isset($_POST["updateplaceaddress"])) {
            $this->_apiUpdatePlaceAddress();
        }

        if (isset($_POST["saveplace"])) {
            $this->_apiSavePlace();
        }

        if (isset($_POST["deleteplace"])) {
            $this->_apiDeletePlace();
        }

        if (isset($_POST["orderplace"])) {
            $this->_apiOrderPlace();
        }

        if (isset($_POST["saveplaceorder"])) {
            $this->_apiSavePlaceOrder();
        }

        if (isset($_GET["getvacations"])) {
            $this->_apiGetVacations();
        }

        if (isset($_POST["addvacation"])) {
            $this->_apiAddVacation();
        }

        if (isset($_POST["setvacationgroupstatus"])) {
            $this->_apiSetVacationGroupStatus();
        }

        if (isset($_POST["deletevacation"])) {
            $this->_apiDeleteVacation();
        }

        if (isset($_POST["togglebookingaktiv"])) {
            $this->_apiToggleBookingAktiv();
        }

        if (isset($_POST["dismissconflict"])) {
            $this->_apiDismissConflict();
        }

        if (isset($_POST["clearweek"])) {
            $this->_apiClearWeek();
        }

        if (isset($_POST["savedaynote"])) {
            $this->_apiSaveDayNote();
        }

        if (isset($_POST["toggledaylezart"])) {
            $this->_apiToggleDayLezart();
        }

        if (isset($_GET["getmonthhours"])) {
            $this->_apiGetMonthHours();
        }

        if (isset($_GET["exportstatistics"])) {
            $this->_apiExportStatistics();
        }

        // DB migration: munkaora + munkaora_tipus columns
        $hasMunkaora = sql_query(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='schedule_workers' AND column_name='munkaora'"
        )->fetchColumn();
        if (!$hasMunkaora) {
            sql_query("ALTER TABLE schedule_workers ADD COLUMN munkaora DECIMAL(4,1) DEFAULT NULL");
        }
        $hasMunkaoraTipus = sql_query(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='schedule_workers' AND column_name='munkaora_tipus'"
        )->fetchColumn();
        if (!$hasMunkaoraTipus) {
            sql_query("ALTER TABLE schedule_workers ADD COLUMN munkaora_tipus ENUM('havi','heti') NOT NULL DEFAULT 'havi'");
        }
        $hasAktiv = sql_query(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='schedule_workers' AND column_name='aktiv'"
        )->fetchColumn();
        if (!$hasAktiv) {
            sql_query("ALTER TABLE schedule_workers ADD COLUMN aktiv TINYINT(1) NOT NULL DEFAULT 1");
        }
        $hasOrvosKell = sql_query(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='schedule_tipusok' AND column_name='orvos_kell'"
        )->fetchColumn();
        if (!$hasOrvosKell) {
            sql_query("ALTER TABLE schedule_tipusok ADD COLUMN orvos_kell TINYINT(1) NOT NULL DEFAULT 1");
        }
        $hasColor = sql_query(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='schedule_tipusok' AND column_name='color'"
        )->fetchColumn();
        if (!$hasColor) {
            sql_query("ALTER TABLE schedule_tipusok ADD COLUMN color VARCHAR(7) DEFAULT NULL");
        }
        $hasMappingAccepted = sql_query(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='schedule_mapping' AND column_name='accepted_conflict'"
        )->fetchColumn();
        if (!$hasMappingAccepted) {
            sql_query("ALTER TABLE schedule_mapping ADD COLUMN accepted_conflict TINYINT(1) NOT NULL DEFAULT 0");
        }
        sql_query("CREATE TABLE IF NOT EXISTS schedule_datum_aktiv (
            tipusid INT NOT NULL,
            datum   DATE NOT NULL,
            aktiv   TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (tipusid, datum)
        )");
        $hasValidFrom = sql_query(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='schedule_tipusok' AND column_name='validfrom'"
        )->fetchColumn();
        if (!$hasValidFrom) {
            sql_query("ALTER TABLE schedule_tipusok ADD COLUMN validfrom DATE NULL DEFAULT NULL");
            sql_query("ALTER TABLE schedule_tipusok ADD COLUMN validto DATE NULL DEFAULT NULL");
        }
        sql_query("CREATE TABLE IF NOT EXISTS schedule_datum_megj (
            tipusid INT NOT NULL,
            datum   DATE NOT NULL,
            megj    TEXT NOT NULL DEFAULT '',
            PRIMARY KEY (tipusid, datum)
        )");
        sql_query("CREATE TABLE IF NOT EXISTS schedule_nap_lezart (
            datum DATE NOT NULL,
            megj  VARCHAR(200) NULL DEFAULT NULL,
            PRIMARY KEY (datum)
        )");
        $hasVacMegj = sql_query(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='schedule_szabadsag' AND column_name='megj'"
        )->fetchColumn();
        if (!$hasVacMegj) {
            sql_query("ALTER TABLE schedule_szabadsag ADD COLUMN megj VARCHAR(200) NULL DEFAULT NULL");
        }

        if (isset($_GET["getnotifications"])) {
            $this->_apiGetNotifications();
        }

        if (isset($_POST["sendnotify"])) {
            $this->_apiSendNotify();
        }

        if (isset($_POST["sendbulknotify"])) {
            $this->_apiSendBulkNotify();
        }

        if (isset($_GET["getnaplo"])) {
            $this->_apiGetNaplo();
        }

        if (isset($_GET["getweekworkers"])) {
            $this->_apiGetWeekWorkers();
        }

        if (isset($_POST["showcollisions"])) {
            $message = "";
            foreach ($this->workScheduleService->collisionData as $collisionItem) {
                $workerData = sql_query("select * from schedule_workers where id=?", [$collisionItem["workerid"]])->fetch();
                $message.= "<div style='display:table-row;'>";
                $message.= "<div style='display:table-cell;'>{$collisionItem["datum"]}&nbsp;&nbsp;</div>";
                //$message.= "<div style='display:table-cell;'>".strtolower($this->napszakok[$collisionItem["napszak"]])."&nbsp;&nbsp;</div>";
                $message.= "<div style='display:table-cell;'>{$workerData["nev"]}&nbsp;&nbsp;</div>";
                $message.= "</div>";
            }

            //$message="<pre>".print_r($this->workScheduleService->collisionData, true)."</pre>";
            $this->utils->jsonOut(["message" => $message]);
            die;
        }

        if (isset($_POST["addworker"])) {
            $result = ["status" => "ok", "message" => ""];

            if (!isset($_POST["workerselector"])) {
                $result = ["status" => "error", "message" => "Válassz dolgozót!"];
            }

            if ($result["status"] == "ok") {
                $timeStart  = $_POST["workertol"] == 0?"00:00:00":$_POST["workertol"].":00";
                $timeEnd    = $_POST["workerig"] == 0?"00:00:00":$_POST["workerig"].":00";
                $datumStart = "{$_POST["datum"]} {$timeStart}";
                $datumEnd   = "{$_POST["datum"]} {$timeEnd}";

                $params = [
                    "createdBy" => $this->adminUser->user["id"],
                    "datumFrom" => $datumStart,
                    "datumTo"   => $datumEnd,
                    "napszak"   => $_POST["napszak"],
                    "tipusId"   => $_POST["tipusid"],
                    "roleId"    => $_POST["roleid"],
                    "workerId"  => $_POST["workerselector"],
                    "megj"  => $_POST["megj"]
                ];

                if ($_POST["mapid"] == 0) {
                    sql_query("insert into schedule_mapping set createdat=now(), createdby=:createdBy, datumfrom=:datumFrom, datumto=:datumTo, napszak=:napszak, tipusid=:tipusId, roleid=:roleId, workerid=:workerId, megj=:megj", $params);
                } else {
                    $params["id"] = $_POST["mapid"];
                    sql_query("update schedule_mapping set createdby=:createdBy, datumfrom=:datumFrom, datumto=:datumTo, napszak=:napszak, tipusid=:tipusId, roleid=:roleId, workerid=:workerId, megj=:megj where id=:id", $params);
                }

                $_SESSION["datumstartcache"] = $datumStart;
                $_SESSION["datumendcache"]   = $datumEnd;

                $this->workScheduleService->reloadScheduleMapping();
                $this->workScheduleService->recalcAllCollisions();
                $result["message"] = $this->_scheduleDay($_POST["datum"]);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["addcompanyforday"])) {
            $result = ["status" => "ok", "message" => ""];

            if (empty($_POST["companyname"])) {
                $result = ["status" => "error", "message" => "Add meg a cég nevét!"];
            }

            if ($result["status"] == "ok") {
                sql_query("insert into schedule_tipusok set megnev=?, cim=?, megj=?, aktiv=1, sorrend=0, roleid=1, kulso=1, forday=?", [$_POST["companyname"], $_POST["companyaddress"], $_POST["companycomment"], $_POST["day"]]);

                $this->workScheduleService->reloadScheduleMapping();
                $result["message"] = $this->_scheduleDay($_POST["day"]);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["savecompanyforday"])) {
            $result = ["status" => "ok", "message" => ""];

            if (empty($_POST["companyname"])) {
                $result = ["status" => "error", "message" => "Add meg a cég nevét!"];
            }

            if ($result["status"] == "ok") {
                sql_query("update schedule_tipusok set megnev=?, cim=?, megj=? where id=?", [$_POST["companyname"], $_POST["companyaddress"], $_POST["companycomment"], $_POST["id"]]);

                $this->workScheduleService->reloadScheduleMapping();
                $result["message"] = $this->_scheduleDay($_POST["day"]);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["deleteworkplaceforday"])) {
            $result = ["status" => "ok", "message" => ""];

            sql_query("delete from schedule_tipusok where id=? and forday=?", [$_POST["id"], $_POST["day"]]);

            $this->workScheduleService->reloadScheduleMapping();
            $result["message"] = $this->_scheduleDay($_POST["day"]);

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["deleteworkermap"])) {
            $result = ["status" => "ok", "message" => ""];

            if (!$mappingData = sql_fetch_array(sql_query("select * from schedule_mapping where id=?", [$_POST["mapid"]]))) {
                $result = ["status" => "error", "message" => "A törlés közben hiba történt!"];
            }

            if ($result["status"] == "ok") {
                sql_query("delete from schedule_mapping where id=?", [$_POST["mapid"]]);
                $this->workScheduleService->reloadScheduleMapping();
                $result["message"] = $this->_scheduleDay($_POST["datum"]);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["copyworker"])) {
            $result = ["status" => "ok", "message" => "", "messageSource" => ""];

            if (!$sourceData = sql_fetch_array(sql_query("select * from schedule_mapping where id=?", array($_POST["sourceid"])))) {
                $result = ["status" => "error", "message" => "Másolás közben hiba történt!"];
            }

            if ($result["status"] == "ok") {
                $timeStart = date("H:i:s", strtotime($sourceData["datumfrom"]));
                $timeEnd   = date("H:i:s", strtotime($sourceData["datumto"]));
                $datumStart = "{$_POST["datum"]} {$timeStart}";
                $datumEnd   = "{$_POST["datum"]} {$timeEnd}";

                $params = [
                    "createdBy" => $this->adminUser->user["id"],
                    "datumFrom" => $datumStart,
                    "datumTo"   => $datumEnd,
                    "napszak"   => $_POST["napszak"],
                    "tipusId"   => $_POST["tipusid"],
                    "roleId"    => $_POST["roleid"],
                    "workerId"  => $sourceData["workerid"]
                ];

                sql_query("insert into schedule_mapping set createdat=now(), createdby=:createdBy, datumfrom=:datumFrom, datumto=:datumTo, napszak=:napszak, tipusid=:tipusId, roleid=:roleId, workerid=:workerId", $params);
                if ($_POST["operation"] =="move") {
                    sql_query("delete from schedule_mapping where id=?", [$_POST["sourceid"]]);
                }
                $this->workScheduleService->reloadScheduleMapping();
                $result["message"] = $this->_scheduleDay($_POST["datum"]);

                $sourceDate = date("Y-m-d", strtotime($sourceData["datumfrom"]));
                if ($sourceDate != $_POST["datum"]) {
                    $result["messageSource"] = $this->_scheduleDay($sourceDate);
                }
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["addworkerdialog"])) {
            $mapId = intval($_POST["mapid"]);
            if ($mapId!=0) {
                $mapData = sql_fetch_array(sql_query("select * from schedule_mapping where id=?", array($mapId)));
            }

            $roleFilter = [intval($_POST["roleid"])];
            if ($_POST["tipusid"]  == 20) {
                $roleFilter[] = 1;
            }
            if ($_POST["tipusid"]  == 19) {
                $roleFilter[] = 2;
            }

            echo "<div style='display:table-cell;vertical-align: top;padding-right: 10px;'>";
            echo "<input type='hidden' name='napszak' value='{$_POST["napszak"]}' />";
            echo "<input type='hidden' name='mapid' value='{$mapId}' />";
            echo "<input type='hidden' name='roleid' value='{$_POST["roleid"]}' />";
            echo "<input type='hidden' name='datum' value='{$_POST["datum"]}' />";
            echo "<input type='hidden' name='tipusid' value='{$_POST["tipusid"]}' />";
            echo "<input type='text' id='workersearch' placeholder='Keresés...' style='width:250px;margin-bottom:4px;display:block;' autocomplete='off' />";
            echo "<select size='6' name='workerselector' id='workerselector' style='width:250px;'>";
            $res = sql_query("select * from schedule_workers where roleid in (".implode(",", $roleFilter).") order by roleid, nev", array($_POST["roleid"]));
            while ($orvosData = sql_fetch_array($res)) {
                $checked = "";
                if (isset($mapData) && $mapData["workerid"] == $orvosData["id"]) {
                    $checked = "selected";
                }
                echo "<option value='{$orvosData["id"]}' {$checked}>{$orvosData["nev"]}</option>";
            }
            echo "</select>";
            echo "</div>";

            $startHour = 6;
            if ($_POST["napszak"] == 1) {
                $startHour = 12;
            }

            $hour = $n = 0;

            $defaultTimeStart = $defaultTimeEnd = "";
            if (isset($_SESSION["datumstartcache"])) {
                $defaultTimeStart = date("H:i", strtotime($_SESSION["datumstartcache"]));
                $defaultTimeEnd = date("H:i", strtotime($_SESSION["datumendcache"]));
            }

            if ($timeHelper = sql_fetch_array(sql_query("select datumfrom, datumto from schedule_mapping m where date(m.datumfrom)=? and m.tipusid=? limit 1", [$_POST["datum"], $_POST["tipusid"]]))) {
                $defaultTimeStart = date("H:i", strtotime($timeHelper["datumfrom"]));
                $defaultTimeEnd = date("H:i", strtotime($timeHelper["datumto"]));
            }

            if (isset($mapData["datumfrom"]) && isset($mapData["datumto"])) {
                $defaultTimeStart = date("H:i", strtotime($mapData["datumfrom"]));
                $defaultTimeEnd = date("H:i", strtotime($mapData["datumto"]));
            }

            echo "<div style='display:table-cell;vertical-align: top;'>";
            echo "<select id='doctortol' name='workertol' style='width:80px;'>";
            echo "<option value='0'>Kezdés?</option>";
            while ($hour<23) {
                $t = date("H:i",mktime($startHour,$n,0,1,1, date("Y")));
                $hour = date("H",mktime($startHour,$n,0,1,1, date("Y")));
                echo "<option value='{$t}'".($defaultTimeStart==$t?" selected":"").">{$t}</option>";
                $n+=15;
            }
            echo "</select> - ";

            $hour = $n = 0;
            echo "<select id='doctorig' name='workerig' style='width:80px;'>";
            echo "<option value='0'>Vége?</option>";
            while ($hour<23) {
                $t = date("H:i",mktime($startHour,$n,0,1,1, date("Y")));
                $hour = date("H",mktime($startHour,$n,0,1,1, date("Y")));
                echo "<option value='{$t}'".($defaultTimeEnd==$t?" selected":"").">{$t}</option>";
                $n+=15;
            }
            echo "</select> ";

            echo "<div style='padding-top:10px;'>";
            $megj = (isset($mapData)?$mapData["megj"]:"");
            echo "<input type='text' id='megj' name='megj' placeholder='megjegyzés' value='{$megj}' style='width:164px;'/>";
            echo "</div>";

            echo "<div style='padding-top:10px;'>";
            $buttonText = $mapId == 0?"+ hozzáadás":"mentés";
            echo "<input type='button' onclick='Schedule.AddWorker();' value='{$buttonText}'>";
            if ($mapId != 0) {
                echo " <input type='button' onclick='Schedule.DeleteWorkerMap();' value='Törlés'>";
            }
            echo "</div>";
            echo "</div>";
            die;
        }

        if (isset($_POST["addworkerdialogszabi"])) {
            $datum = date("Y-m-d", strtotime($_POST["datum"]));

            echo "<div style='display:table-cell;vertical-align: top;padding-right: 10px;'>";
            echo "<input type='hidden' name='datum' value='{$datum}' />";
            echo "<input type='text' id='workersearch' placeholder='Keresés...' style='width:250px;margin-bottom:4px;display:block;' autocomplete='off' />";
            echo "<select size='6' name='workerselector' id='workerselector' style='width:250px;'>";
            $res = sql_query("select * from schedule_workers where true order by roleid, nev", array($_POST["roleid"]));
            while ($orvosData = sql_fetch_array($res)) {
                $checked = "";
                //if (isset($mapData) && $mapData["workerid"] == $orvosData["id"]) {
                //    $checked = "selected";
                //}
                echo "<option value='{$orvosData["id"]}' {$checked}>{$orvosData["nev"]}</option>";
            }
            echo "</select>";

            echo "<div style='padding-top:10px;'>";
            echo "<input type='button' onclick='Schedule.AddWorkerVacation();' value='Hozzáadás'>";
            echo "</div>";

            echo "</div>";
            die;
        }

        if (isset($_POST["addworkervacation"])) {
            $result = ["status" => "ok", "message" => ""];

            if (!isset($_POST["workerselector"])) {
                $result = ["status" => "error", "message" => "Válassz dolgozót!"];
            }

            if ($result["status"] == "ok") {
                $datumStart = "{$_POST["datum"]}";

                sql_query("insert into schedule_szabadsag set datumtol=?, datumig=?, oid=?", [$datumStart, $datumStart, $_POST["workerselector"]]);
                $newId = sql_insert_id();
                sql_query("update schedule_szabadsag set groupid=? where id=?", [$newId, $newId]);

                $result["message"] = VacationSubPage::displaySzabiDayItems($datumStart);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["deleteworkervacation"])) {
            $datum = date("Y-m-d", strtotime($_POST["datum"]));
            $workerId = intval($_POST["id"]);

            sql_query("delete from schedule_szabadsag where datumtol=? and oid=? limit 1", [$datum, $workerId]);

            $result["message"] = VacationSubPage::displaySzabiDayItems($datum);
            $this->utils->jsonOut($result);
        }

        if (isset($_POST["setvacationstatus"])) {
            $datum = date("Y-m-d", strtotime($_POST["datum"]));
            $workerId = intval($_POST["id"]);
            $status = intval($_POST["status"]);
            $result["error"] = "";

            if ($this->adminUser->checkPermission("jog_szabi_beosztas")) {
                sql_query("update schedule_szabadsag set status=? where datumtol=? and oid=? limit 1", [$status, $datum, $workerId]);
            } else {
                $result["error"] = "Nincs jogosultságod a szabadság állapotának változtatásához";
            }

            $result["message"] = VacationSubPage::displaySzabiDayItems($datum);
            $this->utils->jsonOut($result);
        }

        if (isset($_POST["addworkervacation"])) {
            $result = ["status" => "ok", "message" => ""];

            if (!isset($_POST["workerselector"])) {
                $result = ["status" => "error", "message" => "Válassz dolgozót!"];
            }

            if ($result["status"] == "ok") {
                $datumStart = "{$_POST["datum"]}";

                sql_query("insert into schedule_szabadsag set datumtol=?, datumig=?, oid=?", [$datumStart, $datumStart, $_POST["workerselector"]]);
                $newId = sql_insert_id();
                sql_query("update schedule_szabadsag set groupid=? where id=?", [$newId, $newId]);

                $result["message"] = VacationSubPage::displaySzabiDayItems($datumStart);
            }

            $this->utils->jsonOut($result);
        }


        if (isset($_POST["addplacedialog"])) {
            $tipusId = intval($_POST["tipusid"]);
            $thisDay = $_POST["datum"];
            if ($tipusId != 0) {
                $tipusData = sql_query("select * from schedule_tipusok where id=?", [$tipusId])->fetch(PDO::FETCH_ASSOC);
            }

            echo "<input type='hidden' name='datum' id='companydayeditor' value='{$_POST["datum"]}' />";
            echo "<input type='hidden' name='tipusid' value='{$_POST["tipusid"]}' />";

            echo "<div style='display:table-cell;vertical-align: top;'>";

            echo "<div>Cég rövid neve:<br/><input type='text' name='companynameeditor' id='companynameeditor' value='{$tipusData["megnev"]}' /></div>";
            echo "<div style='margin-top:5px;'>Cég címe:<br/><input style='width:350px;' type='text' name='companyaddresseditor' id='companyaddresseditor' value='{$tipusData["cim"]}' /></div>";
            echo "<div style='margin-top:5px;'>Megjegyzése:<br/><input style='width:350px;' type='text' name='companycommenteditor' id='companycommenteditor' value='{$tipusData["megj"]}' /></div>";

            echo "<div style='padding-top:10px;'>";
            $buttonText = $tipusId == 0?"+ hozzáadás":"mentés";
            echo "<input type='button' onclick='Schedule.SavePlaceForDay({$tipusId});' value='{$buttonText}'>";
            if ($tipusId != 0) {
                echo " <input type='button' onclick='Schedule.DeleteWorkplaceForDay({$tipusId}, \"{$thisDay}\");' value='Törlés'>";
            }
            echo "</div>";
            echo "</div>";
            die;
        }

        if (isset($_GET["copyfrom"])) {
            $distance = strtotime($_GET["copyto"]) - strtotime($_GET["copyfrom"]);

            if ($distance > 0) {
                $copyDatas = sql_query("SELECT m.* FROM schedule_mapping m
                    LEFT JOIN schedule_tipusok t ON t.id=m.`tipusid`
                    WHERE m.datumfrom>=:copyFrom AND m.datumfrom<DATE_ADD(:copyFrom, INTERVAL 7 DAY) AND t.`forday`='0000-00-00'", ["copyFrom" => $_GET["copyfrom"]." 00:00:00"])->fetchAll();

                foreach ($copyDatas as $copyData) {
                    $newTimeStart = date("Y-m-d H:i:s", strtotime("{$copyData["datumfrom"]} + {$distance} second"));
                    $newTimeEnd   = date("Y-m-d H:i:s", strtotime("{$copyData["datumto"]} + {$distance} second"));

                    sql_query("insert into schedule_mapping set datumfrom=?, datumto=?, napszak=?, tipusid=?, roleid=?, workerid=?, noverid=?, megj=?, createdat=now(), createdby=?",
                    [$newTimeStart, $newTimeEnd, $copyData["napszak"], $copyData["tipusid"], $copyData["roleid"], $copyData["workerid"], $copyData["noverid"], $copyData["megj"], $this->adminUser->user["id"]]);
                    //echo "<br/>";
                }
            }
            header("location:index.php?page={$_GET["page"]}");
            die;
        }

        if (isset($_GET["clearweek"])) {
            $offset = $_SESSION["wpoffset"];
            $off = $offset*7;
            if ($off >= 0) {
                $off = "+{$off}";
            }

            $from = date("Y-m-d 00:00:00", strtotime("this week monday {$off} day"));

            sql_query("DELETE FROM schedule_mapping WHERE datumfrom>=:deleteFrom AND datumfrom<DATE_ADD(:deleteFrom, INTERVAL 7 DAY)", ["deleteFrom" => $from]);

            header("location:index.php?page={$_GET["page"]}");
            die;
        }

        if (isset($_POST["toggleWorkerFreeDay"])) {
            $day = $_POST["toggleWorkerFreeDay"];
            $workerId = $_POST["wid"];

            $result = [];

            if ($szData = sql_query("select id from schedule_szabadsag sz where sz.datumtol=? and sz.oid=?", [$day, $workerId])->fetch(PDO::FETCH_ASSOC)) {
                sql_query("delete from schedule_szabadsag where id=?", [$szData["id"]]);
                $result["message"] = "Szabadság törölve";
            } else {
                sql_query("insert into schedule_szabadsag set datumtol=?, datumig=?, oid=?", [$day, $day, $workerId]);
                $result["message"] = "Szabadság rögzítve";
            }

            $result["html"] = $this->workScheduleService->workerScheduleList($workerId);

            Utils::jsonOut($result);
            die;
        }

        $GLOBALS["css"][] = "schedule.css";
        $GLOBALS["javascript"][] = "schedule.js";
    }

    private $thisDay;
    private $napszak;


    public function showPage() {
        if (isset($_GET["scheduletoken"])) {
            return $this->showPublicPage($_GET["scheduletoken"]);
        }

        if (!$this->adminUser->beosztasPageAccess()) {
            echo "Nincs jogosultságod!";
            return;
        }

        if ($this->subPage == "beosztasok") {
            $GLOBALS["fullscreen_react"] = true;
            $this->_showReactPage();
            return;
        }

        echo "<div id='menusor'>";
        echo "<a href='index.php?page={$_GET["page"]}'>Beosztások</a> &bull; ";
        echo "<a href='index.php?page={$_GET["page"]}&subpage=workers'>Munkatársak</a> &bull; ";
        echo "<a href='index.php?page={$_GET["page"]}&subpage=vacations'>Szabadságok</a> &bull; ";
        echo "<a href='index.php?page={$_GET["page"]}&subpage=workplaces'>Munkahelyek</a> &bull; ";
        echo "<a href='index.php?page={$_GET["page"]}&subpage=notify'>Értesítések</a> &bull; ";
        echo "<a href='index.php'>Vissza az adminba</a>";
        echo "</div>";

        if ($this->subPage == "workers") {
            echo "<div id='workersubpage' style='margin-top:20px;'>";
            echo $this->workersSubPage->showPage();
            echo "</div>";
        }

        if ($this->subPage == "vacations") {
            echo "<div id='szabisubpage' style='margin-top:20px;'>";
            echo $this->vacationSubPage->showPage();
            echo "</div>";
        }

        if ($this->subPage == "workplaces") {
            echo "<div id='workersubpage' style='margin-top:20px;'>";
            echo $this->workplacesSubPage->showPage();
            echo "</div>";
        }

        if ($this->subPage == "notify") {
            echo "<div id='workersubpage' style='margin-top:20px;'>";
            echo $this->notifySubPage->showPage();
            echo "</div>";
        }

        if ($this->subPage == "print") {
            echo "<div id='printsubpage' style='margin-top:20px;'>";
            echo $this->printSubPage->showPage();
            echo "</div>";
        }

        echo "<div id='schdialog' class='sch_dialog'><div class='sch_dialogtop'></div><form name='dialogform' id='dialogform' method='post'><div class='sch_dialogcontent'></div></form></div>";
    }


    public function showPublicPage($token) {
        if (!$workerData = sql_query("select * from schedule_workers w where concat(sha1(concat(w.id, w.roleid, w.email, w.tel)), md5(concat(w.email, w.tel))) = ?", [$token])->fetch()) {
            echo "Nincs jogosultságod!";
            return;
        }

        $workerId   = (int)$workerData["id"];
        $workerName = trim($workerData["teljesnev"]) ?: $workerData["nev"];

        echo "<div id='pubschedulewrap' style='max-width:960px;padding:10px;'>";
        echo "<div style='display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;'>";
        echo "<div style='font-size:20px;font-weight:bold;'>" . htmlspecialchars($workerName) . " beosztása</div>";
        echo "<button onclick='pubShowSzabiModal()' style='background:#c00;color:#fff;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-size:15px;'>"
            . "<i class='fa-solid fa-umbrella-beach'></i>&nbsp; Szabadság kérése</button>";
        echo "</div>";
        echo $this->workScheduleService->workerPublicScheduleCards($workerId);
        echo "</div>";
        echo $this->_publicSzabiModal($token);
    }

    private function _publicApiRequestVacation(array $workerData): void {
        $tol   = $_POST["tol"]   ?? "";
        $ig    = $_POST["ig"]    ?? "";
        $tipus = in_array($_POST["tipus"] ?? "", ["Szabadság", "Betegszabadság", "Egyéb"])
            ? $_POST["tipus"] : "Szabadság";

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tol) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ig)) {
            Utils::jsonOut(["status" => "error", "message" => "Add meg a szabadság kezdő és vég napját!"]);
        }
        if (strtotime($tol) > strtotime($ig)) {
            Utils::jsonOut(["status" => "error", "message" => "A kezdő dátum nem lehet később, mint a vég dátum!"]);
        }
        $workerId  = (int)$workerData["id"];
        $szRow     = sql_query("SELECT szunnapok FROM settings LIMIT 1")->fetch();
        $szunnapok = $szRow ? array_filter(array_map('trim', explode(',', $szRow['szunnapok']))) : [];
        $groupId   = 0;
        $cur       = $tol;
        while (strtotime($cur) <= strtotime($ig)) {
            if ((int)date('N', strtotime($cur)) >= 6 || in_array($cur, $szunnapok)) {
                $cur = date("Y-m-d", strtotime("{$cur} +1 day"));
                continue;
            }
            sql_query("INSERT INTO schedule_szabadsag SET datumtol=?, datumig=?, oid=?, status=0, tipus=?", [$cur, $cur, $workerId, $tipus]);
            $newId = sql_insert_id();
            if ($groupId === 0) $groupId = $newId;
            sql_query("UPDATE schedule_szabadsag SET groupid=? WHERE id=?", [$groupId, $newId]);
            $cur = date("Y-m-d", strtotime("{$cur} +1 day"));
        }

        Utils::jsonOut(["status" => "ok", "message" => "Kérés sikeresen beküldve!"]);
    }

    private function _publicSzabiModal(string $token): string {
        $safeToken = htmlspecialchars($token, ENT_QUOTES);
        return <<<HTML
<script>
function pubToggleSec(id, btn) {
    var body = document.getElementById(id);
    var icon = btn.querySelector('.fa-chevron-up, .fa-chevron-down');
    if (body.style.display === 'none') {
        body.style.display = 'flex';
        if (icon) { icon.classList.remove('fa-chevron-down'); icon.classList.add('fa-chevron-up'); }
    } else {
        body.style.display = 'none';
        if (icon) { icon.classList.remove('fa-chevron-up'); icon.classList.add('fa-chevron-down'); }
    }
}
</script>

<div id="pubSzabiOverlay" style="display:none;position:fixed;inset:0;background:rgba(4,6,10,.55);backdrop-filter:blur(4px);z-index:9999;overflow-y:auto;padding:16px 24px;">
  <div style="max-width:460px;width:100%;background:#fff;border:1px solid #e3e8ef;border-radius:16px;box-shadow:0 50px 100px -28px rgba(0,0,0,.6);margin:40px auto 16px;overflow:hidden;font-family:Manrope,system-ui,sans-serif;">

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 24px;border-bottom:1px solid #e3e8ef;">
      <h2 style="margin:0;font-size:18px;font-weight:700;color:#1a2230;">Új szabadság</h2>
      <button onclick="pubCloseSzabiModal()" style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#f1f4f7;border:1px solid #e3e8ef;cursor:pointer;color:#5c6675;flex-shrink:0;padding:0;">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </button>
    </div>

    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px;">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <label style="display:block;">
          <span style="display:block;font-size:12.5px;font-weight:500;color:#5c6675;margin-bottom:6px;">Kezdő nap</span>
          <input type="date" id="pubSzabTol" style="width:100%;outline:none;background:#f1f4f7;border:1px solid #e3e8ef;color:#1a2230;border-radius:9px;padding:10px 12px;font-size:13.5px;box-sizing:border-box;font-family:monospace;" onfocus="this.style.borderColor='#9c3328'" onblur="this.style.borderColor='#e3e8ef'">
        </label>
        <label style="display:block;">
          <span style="display:block;font-size:12.5px;font-weight:500;color:#5c6675;margin-bottom:6px;">Utolsó nap</span>
          <input type="date" id="pubSzabIg" style="width:100%;outline:none;background:#f1f4f7;border:1px solid #e3e8ef;color:#1a2230;border-radius:9px;padding:10px 12px;font-size:13.5px;box-sizing:border-box;font-family:monospace;" onfocus="this.style.borderColor='#9c3328'" onblur="this.style.borderColor='#e3e8ef'">
        </label>
      </div>

      <label style="display:block;">
        <span style="display:block;font-size:12.5px;font-weight:500;color:#5c6675;margin-bottom:6px;">Típus</span>
        <div style="position:relative;">
          <select id="pubSzabTipus" style="width:100%;outline:none;background:#f1f4f7;border:1px solid #e3e8ef;color:#1a2230;border-radius:9px;padding:10px 32px 10px 12px;font-size:13.5px;font-weight:600;box-sizing:border-box;appearance:none;-webkit-appearance:none;cursor:pointer;font-family:inherit;" onfocus="this.style.borderColor='#9c3328'" onblur="this.style.borderColor='#e3e8ef'">
            <option value="Szabadság">Szabadság</option>
            <option value="Betegszabadság">Betegszabadság</option>
            <option value="Egyéb">Egyéb</option>
          </select>
          <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:#9aa3b1;display:flex;">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
        </div>
      </label>

      <div id="pubSzabMsg" style="display:none;padding:10px 12px;border-radius:8px;font-size:13px;font-weight:500;"></div>
    </div>

    <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:16px 24px;border-top:1px solid #e3e8ef;background:#f3f5f8;">
      <button onclick="pubCloseSzabiModal()" style="border-radius:8px;padding:10px 16px;font-size:13.5px;font-weight:600;color:#5c6675;border:1px solid #e3e8ef;background:#fff;cursor:pointer;font-family:inherit;">Mégse</button>
      <button id="pubSzabBtn" onclick="pubSubmitSzabi('{$safeToken}')" style="display:flex;align-items:center;gap:6px;border-radius:8px;padding:10px 20px;font-size:13.5px;font-weight:700;color:#fff;background:#9c3328;border:none;cursor:pointer;font-family:inherit;">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" style="flex-shrink:0;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M17 21v-8H7v8M7 3v5h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        Mentés
      </button>
    </div>

  </div>
</div>

<script>
function pubShowSzabiModal() {
    document.getElementById('pubSzabiOverlay').style.display = 'block';
    document.getElementById('pubSzabTipus').value = 'Szabadság';
    var msg = document.getElementById('pubSzabMsg');
    msg.style.display = 'none';
    var btn = document.getElementById('pubSzabBtn');
    btn.disabled = false;
    btn.style.background = '#9c3328';
    btn.style.cursor = 'pointer';
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" style="flex-shrink:0;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M17 21v-8H7v8M7 3v5h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg> Mentés';
    var today = new Date().toISOString().split('T')[0];
    document.getElementById('pubSzabTol').min = today;
    document.getElementById('pubSzabIg').min = today;
    document.getElementById('pubSzabTol').value = '';
    document.getElementById('pubSzabIg').value = '';
}
function pubCloseSzabiModal() {
    document.getElementById('pubSzabiOverlay').style.display = 'none';
}
document.addEventListener('click', function(e) {
    if (e.target === document.getElementById('pubSzabiOverlay')) pubCloseSzabiModal();
});
function pubSzabShowMsg(msg, ok) {
    var el = document.getElementById('pubSzabMsg');
    el.textContent = msg;
    el.style.display = 'block';
    el.style.background = ok ? '#dcfce7' : '#fee2e2';
    el.style.color      = ok ? '#166534' : '#991b1b';
    el.style.border     = '1px solid ' + (ok ? '#bbf7d0' : '#fecaca');
}
function pubSubmitSzabi(token) {
    var tol   = document.getElementById('pubSzabTol').value;
    var ig    = document.getElementById('pubSzabIg').value;
    var tipus = document.getElementById('pubSzabTipus').value;
    if (!tol || !ig) { pubSzabShowMsg('Add meg mindkét dátumot!', false); return; }
    if (tol > ig)    { pubSzabShowMsg('A kezdő nap nem lehet később, mint az utolsó nap!', false); return; }
    var btn = document.getElementById('pubSzabBtn');
    btn.disabled = true;
    btn.style.background = '#9aa3b1';
    btn.style.cursor = 'not-allowed';
    btn.textContent = 'Mentés…';
    $.post(location.href, { publicrequstvacation: 1, scheduletoken: token, tol: tol, ig: ig, tipus: tipus }, function(data) {
        if (data.status === 'ok') {
            pubSzabShowMsg(data.message || 'Kérés elküldve!', true);
            setTimeout(function() { location.reload(); }, 1800);
        } else {
            pubSzabShowMsg(data.message || 'Hiba történt!', false);
            btn.disabled = false;
            btn.style.background = '#9c3328';
            btn.style.cursor = 'pointer';
            btn.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" style="flex-shrink:0;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M17 21v-8H7v8M7 3v5h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg> Mentés';
        }
    }, 'json').fail(function() {
        pubSzabShowMsg('Hálózati hiba! Próbáld újra.', false);
        btn.disabled = false;
        btn.style.background = '#9c3328';
        btn.style.cursor = 'pointer';
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" style="flex-shrink:0;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M17 21v-8H7v8M7 3v5h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg> Mentés';
    });
}
</script>
HTML;
    }


    private function collisionMark():string {
        $collisionMark = "";

        if (isset($this->workScheduleService->collisionsByDate[$this->thisDay])) {
            $collisionMark = "&nbsp;&nbsp;<span style='color:#fff;background:red;border-radius: 3px;padding: 2px 0px;'>&nbsp;ÜTKÖZÉS!&nbsp;</span>";
        }

        return $collisionMark;
    }

    private function _scheduleDay($thisDay) {
        $this->thisDay = $thisDay;
        $weekDay = date("N", strtotime($thisDay));
        $html = "";

        $elerhetoData = sql_query("SELECT w.nev FROM schedule_szabadsag sz LEFT JOIN schedule_workers w ON sz.oid=w.id WHERE sz.datumtol=? AND sz.tipus=?", [$thisDay, "Elérhető"])->fetchAll();
        if ($elerhetoData) {
            $html .= "<div class='scheduledayhead' style='background:#2563eb;color:#fff;'>Elérhető orvosok</div>";
            foreach ($elerhetoData as $data) {
                $html .= "<div style='padding:2px 4px;'>{$data["nev"]}</div>";
            }
        }

        $html.= "<div class='scheduledayhead'>".$this->adminUtils->magyarDatum($this->thisDay).$this->collisionMark()."</div>";
        $html .= "<div style='display:table;width:100%;'>";

        $this->napszak = 0;
        //for ($this->napszak = 0; $this->napszak<=1; $this->napszak++) {
            //$html .= "<div class='schedulenapszakhead'>".$this->napszakok[$this->napszak]."</div>";

            $html .= "<div style='display:table-row;'>";
            $html .= $this->_rendeloFejCell("Rendelő");
            $html .= $this->_workerFejCell("Orvos");
            $html .= $this->_workerFejCell("Nővér");
            $html .= "</div>";

            $resTipus = sql_query("select * from schedule_tipusok where kulso=0 order by roleid, sorrend");
            while ($tipusData = sql_fetch_array($resTipus)) {
                $html .= "<div style='display:table-row;'>";
                $html .= $this->_rendeloCell($tipusData);
                $html .= $this->_workerCell($tipusData);
                $html .= $this->_workerCell($tipusData, 2);
                $html .= "</div>";
            }
        //}
        $html .= "</div>";

        $html .= "<div class='scheduledayhead'>".$this->adminUtils->magyarDatum($this->thisDay)."<br/>Külső cégek</div>";
        $html .= "<div style='display:table;'>";
        $html .= "<div style='display:table-row;'>";
        $html .= $this->_rendeloFejCell("Cégek");
        $html .= $this->_workerFejCell("Orvos");
        $html .= $this->_workerFejCell("Nővér");
        $html .= "</div>";
        $resTipus = sql_query("select * from schedule_tipusok t where t.kulso=1 and (t.forday='0000-00-00' or t.forday=?) order by forday, roleid, sorrend", [$thisDay]);
        $this->napszak = 2;
        while ($tipusData = sql_fetch_array($resTipus)) {
            $html .= "<div style='display:table-row;'>";
            $html .= $this->_rendeloCell($tipusData, $thisDay);
            $html .= $this->_workerCell($tipusData);
            $html .= $this->_workerCell($tipusData, 2);
            $html .= "</div>";
        }
        $html .= "</div>";

        $html.= "<div style='margin:10px 0px 5px 4px;'>";
        $html.= "<div><a href='#' onclick='$(\"#addnewcompanyday{$thisDay}\").slideToggle();return false;'>+ cég hozzáadása ehhez a naphoz</a></div>";

        $html.="<div id='addnewcompanyday{$thisDay}' style='display: none;padding-top: 10px;'>";
        $html.="<div>Cég rövid neve:<br/><input type='text' name='companyname{$thisDay}' id='companyname{$thisDay}' value='' /></div>";
        $html.="<div style='margin-top:5px;'>Cég címe:<br/><input style='width:350px;' type='text' name='companyaddress{$thisDay}' id='companyaddress{$thisDay}' value='' /></div>";
        $html.="<div style='margin-top:5px;'>Megjegyzése:<br/><input style='width:350px;' type='text' name='companycomment{$thisDay}' id='companycomment{$thisDay}' value='' /></div>";
        $html.="<div style='margin-top:5px;'><input type='button' onclick='Schedule.AddCompanyForDay(\"{$thisDay}\");' value='Cég hozzáadása'></div>";
        $html.= "</div>";

        $html.= "</div>";

        if ($szabiData = sql_query("SELECT w.nev, sz.status FROM schedule_szabadsag sz LEFT JOIN schedule_workers w ON sz.oid=w.id WHERE sz.datumtol=? AND COALESCE(sz.tipus,'') != ?", [$this->thisDay, "Elérhető"])->fetchAll()) {
            $html .= "<div class='scheduledayhead' style='background:#ff6961;color:#fff;'>{$this->thisDay} " . $this->settings->hetnap[$weekDay] . "<br/>Szabadságok</div>";
            foreach ($szabiData as $data) {
                $statusLabel = ((int)$data["status"] === 0) ? " <span style='font-size:11px;opacity:0.75;'>(elbírálás alatt)</span>" : "";
                $html.="<div style='padding:2px;'>{$data["nev"]}{$statusLabel}</div>";
                //$html.="<div style='padding:2px;display: table-cell;'>{$data["nev"]}</div>";
                //$html.="<div style='padding:2px;display: table-cell;'>{$data["nev"]}</div>";
            }
        }

        return $html;
    }

    private function _rendeloFejCell($text) {
        $html="";
        $html.="<div class='sch_oszlopfejcell'>{$text}</div>";
        return $html;
    }

    private function _workerFejCell($title) {
        $html="";
        $html.="<div class='sch_oszlopfejcell'>&nbsp;</div>";
        $html.="<div class='sch_oszlopfejcell'>{$title}</div>";
        return $html;
    }

    private function _rendeloCell($tipusData, $day = "") {
        $extraStyle = ($tipusData["roleid"]!=1?" style='background:#daeef3;'":"");
        $extraStyle = $tipusData["forday"] == "0000-00-00" ? $extraStyle : "style='background:#daeef3;'";
        $html="";
        $html.="<div class='sch_oszlopdatacell' {$extraStyle}>";
        if ($tipusData["forday"] != "0000-00-00") {
            $html.= "<a data-tipusid='{$tipusData["id"]}' data-datum='{$day}' data-tipusnev='{$tipusData["megnev"]}' href='#' onclick='Schedule.ShowAddPlaceDialog(this);return false;'>{$tipusData["megnev"]}</a>";
        } else {
            $html.= "{$tipusData["megnev"]}";
        }
        if ($tipusData["cim"] != "") {
            $html.= "&nbsp;<a title='Google Maps' href='https://www.google.com/maps/place/".urlencode($tipusData["cim"])."' target='_blank'><i class='fas fa-map'></i></a>";
        }
        $html.= ($tipusData["forday"]!="0000-00-00"?" <a href='#' onclick='Schedule.DeleteWorkplaceForDay({$tipusData["id"]}, \"{$day}\");return false;' title='cég törlése erről a napról'><i class='fas fa-trash-alt'></i></a>":"");

        if (!empty($tipusData["megj"])) {
            $html.= "<div style='font-style: italic;'>{$tipusData["megj"]}</div>";
        }
        $html.= "</div>";




        return $html;
    }

    private function _workerCell($tipusData, $roleFilter = 0) {
        $roleId = $tipusData["roleid"];
        $tipusName = $tipusData["megnev"];
        if ($roleFilter != 0) {
            if ($roleId != 1) {
                //nővér csak orvoshoz kell
                return "";
            }
            $roleId = $roleFilter;
        }
        if ($roleId == 2) {
            $tipusName.=" - nővér";
        }

        $extraStyle = ($roleId==3?" style='background:#daeef3;'":"");

        $html="";
        $html.="<div class='sch_oszlopdatacellbtn' {$extraStyle}>";
        $html.="<a data-mapid='0' data-datum='{$this->thisDay}' data-roleid='{$roleId}' data-tipusid='{$tipusData["id"]}' data-tipusnev='{$tipusName}' data-napszak='{$this->napszak}' onclick='Schedule.ShowAddWorkerDialog(this);return false;' href='#'><i class='fas fa-plus-circle'></i></a>";
        $html.="</div>";

        $html.="<div class='sch_oszlopdatacell' {$extraStyle} data-datum='{$this->thisDay}' data-napszak='{$this->napszak}' data-roleid='{$roleId}' data-tipusid='{$tipusData["id"]}'>";
        if (isset($this->workScheduleService->scheduleMapping["{$this->thisDay}_{$this->napszak}_{$tipusData["id"]}"])) {
            $mappings = $this->workScheduleService->scheduleMapping["{$this->thisDay}_{$this->napszak}_{$tipusData["id"]}"];
            foreach ($mappings as $mapping) {
                if ($mapping["roleid"] != $roleId) {
                    continue;
                }
                $html .= "<div class='workerlink'>";

                if (isset($this->workScheduleService->collisionsByDate[$this->thisDay][$mapping["workerid"]])) {
                    foreach ($this->workScheduleService->collisionsByDate[$this->thisDay][$mapping["workerid"]] as $collisionItem) {
                        if ($collisionItem == $mapping["datumfrom"].$mapping["datumto"]) {
                            $html.="<i style='color:red;' title='Ütközés' class='fas fa-exclamation-triangle'></i>&nbsp;";
                            break;
                        }
                    }
                }

                $html .= "<a data-mapid='{$mapping["id"]}' data-datum='{$this->thisDay}' data-roleid='{$roleId}' data-tipusid='{$tipusData["id"]}' data-tipusnev='{$tipusName}' data-napszak='{$this->napszak}' onclick='Schedule.ShowAddWorkerDialog(this);return false;' href='#'>";
                $html .= "{$mapping["workernev"]} ";
                $html .= "</a>";

                $html .= $this->workScheduleService->workInterval($mapping);

                $html .= "</div>";
                $html .= "<div class='workermegj'>";
                $html .= "<div class='sch_mappingmegj'>{$mapping["megj"]}</div>";
                $html .= "</div>";
            }
        }

        $html.="</div>";
        return $html;
    }

    /* ================================================================
     *  REACT UI – privát segédmetódusok
     * ================================================================ */

    private function _showReactPage(): void {
        $offset     = intval($_SESSION["wpoffset"]);
        $page       = $_GET["page"] ?? "workschedule";
        $pageUrl    = json_encode("index.php?page=" . htmlspecialchars($page));
        $adminName  = json_encode($this->adminUser->user["nev"] ?? "Admin");
        $jsFile     = __DIR__ . "/../../public/admin/js/schedule_react.js";

        echo "<!DOCTYPE html>\n<html lang='hu'>\n<head>\n";
        echo "  <meta charset='UTF-8'>\n";
        echo "  <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
        echo "  <title>".(Booking_Constants::SQL_DB === "keltexmed" ? "Keltexmed" : "HMM")." – Munkaidő beosztás</title>\n";
        echo "  <script src='https://cdn.tailwindcss.com'></script>\n";
        echo "  <script src='https://unpkg.com/react@18.3.1/umd/react.production.min.js' crossorigin></script>\n";
        echo "  <script src='https://unpkg.com/react-dom@18.3.1/umd/react-dom.production.min.js' crossorigin></script>\n";
        echo "  <script src='https://unpkg.com/@babel/standalone@7.27.6/babel.min.js'></script>\n";
        echo "  <link href='https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap' rel='stylesheet'>\n";
        echo "</head>\n<body style='margin:0;padding:0;overflow:hidden;'>\n";
        echo "  <div id='hmm-schedule-root'></div>\n";
        echo "  <script>\n";
        $tenant = Booking_Constants::SQL_DB === "keltexmed" ? "keltexmed" : "hmm";
        $logo   = json_encode($tenant === "keltexmed" ? "/images/keltexmed_logo_v2.png" : "/images/hmm_logo_nagy.png");
        echo "    window.HMM_SCHEDULE_CONFIG = { url: {$pageUrl}, offset: {$offset}, adminName: {$adminName}, tenant: '{$tenant}', logo: {$logo} };\n";
        echo "  </script>\n";
        echo "  <script type='text/babel' data-presets='react'>\n";
        if (is_file($jsFile)) {
            readfile($jsFile);
        } else {
            echo "console.error('schedule_react.js not found');";
        }
        echo "  </script>\n";
        echo "</body>\n</html>\n";
    }

    private function _apiGetWeekData(): void {
        $offset  = isset($_GET["offset"]) ? intval($_GET["offset"]) : intval($_SESSION["wpoffset"]);
        $_SESSION["wpoffset"] = $offset;

        $offDays = $offset * 7;
        $offStr  = ($offDays >= 0 ? "+" : "") . $offDays;
        $monday  = date("Y-m-d", strtotime("this week monday {$offStr} day"));
        $year    = (int)date("Y", strtotime($monday));
        $week    = (int)date("W", strtotime($monday));

        $allTipusok = sql_query(
            "SELECT * FROM schedule_tipusok WHERE forday='0000-00-00' ORDER BY kulso, sorrend"
        )->fetchAll(PDO::FETCH_ASSOC);

        $mondayStart = $monday . " 00:00:00";
        $mappings = sql_query(
            "SELECT m.id, m.datumfrom, m.datumto, m.tipusid, m.roleid, m.workerid, m.megj, COALESCE(m.aktiv, 1) AS aktiv, COALESCE(m.accepted_conflict, 0) AS accepted_conflict,
                    w.nev AS workernev
             FROM schedule_mapping m
             LEFT JOIN schedule_workers w ON m.workerid = w.id
             WHERE m.datumfrom >= :from AND m.datumfrom < DATE_ADD(:from, INTERVAL 7 DAY)
             ORDER BY m.datumfrom, m.roleid, w.nev",
            ["from" => $mondayStart]
        )->fetchAll(PDO::FETCH_ASSOC);

        $mappingIdx = [];
        foreach ($mappings as $m) {
            $date = date("Y-m-d", strtotime($m["datumfrom"]));
            $key  = "{$date}_{$m["tipusid"]}";
            $mappingIdx[$key][] = $m;
        }

        $datumAktivRows = sql_query(
            "SELECT tipusid, datum, aktiv FROM schedule_datum_aktiv WHERE datum >= :mon AND datum < DATE_ADD(:mon, INTERVAL 7 DAY)",
            ["mon" => $monday]
        )->fetchAll(PDO::FETCH_ASSOC);
        $datumAktivIdx = [];
        foreach ($datumAktivRows as $o) {
            $datumAktivIdx[(int)$o["tipusid"] . "_" . $o["datum"]] = (int)$o["aktiv"];
        }

        $lezartRows = sql_query(
            "SELECT datum, megj FROM schedule_nap_lezart WHERE datum >= :mon AND datum < DATE_ADD(:mon, INTERVAL 7 DAY)",
            ["mon" => $monday]
        )->fetchAll(PDO::FETCH_ASSOC);
        $lezartIdx = [];
        foreach ($lezartRows as $r) {
            $lezartIdx[$r["datum"]] = $r["megj"] ?? "";
        }

        $dayNoteRows = sql_query(
            "SELECT tipusid, datum, megj FROM schedule_datum_megj WHERE datum >= :mon AND datum < DATE_ADD(:mon, INTERVAL 7 DAY)",
            ["mon" => $monday]
        )->fetchAll(PDO::FETCH_ASSOC);
        $dayNoteIdx = [];
        foreach ($dayNoteRows as $r) {
            $dayNoteIdx[(int)$r["tipusid"] . "_" . $r["datum"]] = $r["megj"];
        }

        $weekEnd = date("Y-m-d", strtotime($monday . " +6 days"));
        $vacationRows = sql_query(
            "SELECT sz.oid AS workerid, sz.datumtol, sz.datumig, sz.status,
                    COALESCE(sz.tipus,'') AS tipus, COALESCE(sz.megj,'') AS megj,
                    IF(TRIM(w.teljesnev) <> '', w.teljesnev, w.nev) AS workernev
             FROM schedule_szabadsag sz
             LEFT JOIN schedule_workers w ON w.id = sz.oid
             WHERE sz.status IN (0,1) AND sz.datumtol <= :weekend AND sz.datumig >= :monday",
            ["monday" => $monday, "weekend" => $weekEnd]
        )->fetchAll(PDO::FETCH_ASSOC);

        $elerhetoByDate  = [];
        $szabadsagByDate = [];
        foreach ($vacationRows as $v) {
            $cur = max($v["datumtol"], $monday);
            $end = min($v["datumig"], $weekEnd);
            while ($cur <= $end) {
                if ($v["tipus"] === "Elérhető") {
                    $elerhetoByDate[$cur][] = ["name" => $v["workernev"], "megj" => $v["megj"]];
                } else {
                    $szabadsagByDate[$cur][] = [
                        "workerId" => (int)$v["workerid"],
                        "name"     => $v["workernev"],
                        "status"   => (int)$v["status"],
                        "megj"     => $v["megj"],
                    ];
                }
                $cur = date("Y-m-d", strtotime($cur . " +1 day"));
            }
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date     = date("Y-m-d", strtotime($monday . " +{$i} days"));
            $bookings = [];

            foreach ($allTipusok as $tipus) {
                $forDay  = ($tipus["forday"] !== "0000-00-00");
                if ($forDay && $tipus["forday"] !== $date) continue;
                $weekday = (int)date("N", strtotime($date));
                $napok   = (int)($tipus["napok"] ?? 127);
                if (!($napok & (1 << ($weekday - 1)))) continue;
                if (!empty($tipus["kiszallas"])) {
                    $vf = $tipus["validfrom"] ?? null;
                    $vt = $tipus["validto"]   ?? null;
                    if ($vf && $date < $vf) continue;
                    if ($vt && $date > $vt) continue;
                }

                $key      = "{$date}_{$tipus["id"]}";
                $staffRows = $mappingIdx[$key] ?? [];

                $staff   = [];
                $minFrom = null;
                $maxTo   = null;

                foreach ($staffRows as $m) {
                    $role = ($m["roleid"] == 2) ? "n" : ($m["roleid"] == 3 ? "e" : ($m["roleid"] == 5 ? "v" : "d"));
                    $from = date("H:i", strtotime($m["datumfrom"]));
                    $to   = date("H:i", strtotime($m["datumto"]));

                    $staff[] = [
                        "mapId"    => (int)$m["id"],
                        "role"     => $role,
                        "name"     => $m["workernev"],
                        "workerId" => (int)$m["workerid"],
                        "from"     => $from,
                        "to"       => $to,
                        "megj"             => $m["megj"] ?? "",
                        "aktiv"            => (int)($m["aktiv"] ?? 1),
                        "acceptedConflict" => (int)($m["accepted_conflict"] ?? 0),
                    ];

                    if ($minFrom === null || $from < $minFrom) $minFrom = $from;
                    if ($maxTo   === null || $to   > $maxTo)   $maxTo   = $to;
                }

                $bookings[] = [
                    "id"      => "tip_{$tipus["id"]}_{$date}",
                    "tipusId" => (int)$tipus["id"],
                    "cat"     => !empty($tipus["kiszallas"]) ? "kiszallas" : ($tipus["kulso"] == 0 ? ($tipus["roleid"] == 3 ? "belso_egyeb" : "belso") : "kulso"),
                    "roleid"  => (int)($tipus["roleid"] ?? 0),
                    "title"   => $tipus["megnev"],
                    "address"     => $tipus["cim"]    ?? "",
                    "rendelo"     => $tipus["rendelo"] ?? "",
                    "note"        => $dayNoteIdx[(int)$tipus["id"] . "_" . $date] ?? ($tipus["megj"] ?? ""),
                    "napok"       => (int)($tipus["napok"] ?? 127),
                    "org"         => $tipus["org"] ?: "HMM",
                    "orvosKell"   => (int)($tipus["orvos_kell"] ?? 1),
                    "ktarto_nev"  => $tipus["ktarto_nev"]   ?? "",
                    "ktarto_tel"  => $tipus["ktarto_tel"]   ?? "",
                    "ktarto_email"=> $tipus["ktarto_email"] ?? "",
                    "color"       => $tipus["color"] ?? null,
                    "from"    => $minFrom ?? "08:00",
                    "to"      => $maxTo   ?? "16:00",
                    "date"    => $date,
                    "staff"   => $staff,
                    "forDay"  => $forDay,
                    "aktiv"   => (function() use ($datumAktivIdx, $tipus, $date, $staffRows) {
                        $overrideKey = (int)$tipus["id"] . "_" . $date;
                        if (isset($datumAktivIdx[$overrideKey])) return $datumAktivIdx[$overrideKey];
                        return empty($staffRows) ? 1 : (int)(min(array_column($staffRows, "aktiv")) > 0);
                    })()
                ];
            }

            $lezart = isset($lezartIdx[$date]);
            $days[] = ["date" => $date, "dayIndex" => $i, "bookings" => $bookings, "elerheto" => $elerhetoByDate[$date] ?? [], "szabadsag" => $szabadsagByDate[$date] ?? [], "lezart" => $lezart, "lezartMegj" => $lezart ? ($lezartIdx[$date] ?? "") : ""];
        }

        $doctorRows = sql_query(
            "SELECT id, nev FROM schedule_workers WHERE roleid=1 AND COALESCE(aktiv,1)=1 ORDER BY nev"
        )->fetchAll(PDO::FETCH_ASSOC);
        $assistantRows = sql_query(
            "SELECT id, nev FROM schedule_workers WHERE roleid=2 AND COALESCE(aktiv,1)=1 ORDER BY nev"
        )->fetchAll(PDO::FETCH_ASSOC);
        $egyebRows = sql_query(
            "SELECT id, nev FROM schedule_workers WHERE roleid=3 AND COALESCE(aktiv,1)=1 ORDER BY nev"
        )->fetchAll(PDO::FETCH_ASSOC);
        $vehicleRows = sql_query(
            "SELECT id, nev FROM schedule_workers WHERE roleid=4 AND COALESCE(aktiv,1)=1 ORDER BY nev"
        )->fetchAll(PDO::FETCH_ASSOC);

        $places = array_values(array_map(
            fn($t) => ["megnev" => $t["megnev"], "cim" => $t["cim"] ?? "", "org" => $t["org"] ?: "HMM"],
            array_filter($allTipusok, fn($t) => !empty($t["kiszallas"]) || !empty($t["kulso"]))
        ));

        $this->utils->jsonOut([
            "year"             => $year,
            "week"             => $week,
            "offset"           => $offset,
            "monday"           => $monday,
            "days"             => $days,
            "doctors"          => array_column($doctorRows,    "nev"),
            "assistants"       => array_column($assistantRows, "nev"),
            "doctorsWithId"    => $doctorRows,
            "assistantsWithId" => $assistantRows,
            "egyebWithId"      => $egyebRows,
            "vehiclesWithId"   => $vehicleRows,
            "places"           => $places,
        ]);
        die;
    }

    private function _apiClearWeek(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
            die;
        }

        $monday = $_POST["monday"] ?? "";
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $monday)) {
            $this->utils->jsonOut(["status" => "error", "message" => "Érvénytelen dátum!"]);
            die;
        }

        sql_query(
            "DELETE m FROM schedule_mapping m
             LEFT JOIN schedule_tipusok t ON t.id=m.tipusid
             WHERE m.datumfrom>=:from AND m.datumfrom<DATE_ADD(:from, INTERVAL 7 DAY) AND t.forday='0000-00-00'",
            ["from" => $monday . " 00:00:00"]
        );

        $this->utils->jsonOut(["status" => "ok"]);
        die;
    }

    private function _apiSaveDayNote(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
            die;
        }

        $tipusId = intval($_POST["tipusid"] ?? 0);
        $megj    = substr(trim($_POST["megj"] ?? ""), 0, 200);
        $datums  = array_filter(
            array_map('trim', explode(',', $_POST["datum"] ?? "")),
            fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)
        );

        if (!$tipusId || empty($datums)) {
            $this->utils->jsonOut(["status" => "error", "message" => "Hiányzó adatok!"]);
            die;
        }

        foreach ($datums as $datum) {
            sql_query(
                "INSERT INTO schedule_datum_megj (tipusid, datum, megj) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE megj=VALUES(megj)",
                [$tipusId, $datum, $megj]
            );
        }

        $this->utils->jsonOut(["status" => "ok"]);
        die;
    }

    private function _apiToggleDayLezart(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]); die;
        }
        $datum = trim($_POST["datum"] ?? "");
        $megj  = substr(trim($_POST["megj"] ?? ""), 0, 200) ?: null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            $this->utils->jsonOut(["status" => "error", "message" => "Érvénytelen dátum!"]); die;
        }
        $exists = sql_query("SELECT COUNT(*) FROM schedule_nap_lezart WHERE datum=?", [$datum])->fetchColumn();
        if ($exists) {
            sql_query("DELETE FROM schedule_nap_lezart WHERE datum=?", [$datum]);
            $this->utils->jsonOut(["status" => "ok", "lezart" => false]);
        } else {
            sql_query("INSERT INTO schedule_nap_lezart (datum, megj) VALUES (?, ?)", [$datum, $megj]);
            $this->utils->jsonOut(["status" => "ok", "lezart" => true]);
        }
        die;
    }

    private function _apiGetMonthHours(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]); die;
        }
        $month = $_GET["month"] ?? date("Y-m");
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->utils->jsonOut(["status" => "error", "message" => "Érvénytelen hónap!"]); die;
        }
        $monthStart = $month . "-01 00:00:00";
        $monthEnd   = date("Y-m-01 00:00:00", strtotime($monthStart . " +1 month"));

        $hourRows = sql_query(
            "SELECT m.workerid, ROUND(SUM(TIMESTAMPDIFF(MINUTE, m.datumfrom, m.datumto)) / 60.0, 2) AS hours
             FROM schedule_mapping m
             LEFT JOIN schedule_tipusok t ON t.id=m.tipusid
             WHERE m.datumfrom >= :start AND m.datumfrom < :end AND t.forday='0000-00-00'
             GROUP BY m.workerid",
            ["start" => $monthStart, "end" => $monthEnd]
        )->fetchAll(PDO::FETCH_ASSOC);

        $bookedMap = [];
        foreach ($hourRows as $r) { $bookedMap[(int)$r["workerid"]] = (float)$r["hours"]; }

        $workers = sql_query(
            "SELECT id, nev, teljesnev, roleid, munkaora, munkaora_tipus FROM schedule_workers ORDER BY roleid, nev"
        )->fetchAll(PDO::FETCH_ASSOC);

        $result = array_map(function ($w) use ($bookedMap) {
            return [
                "id"       => (int)$w["id"],
                "nev"      => $w["nev"],
                "teljesnev"=> $w["teljesnev"],
                "roleid"   => (int)$w["roleid"],
                "quota"          => isset($w["munkaora"]) && $w["munkaora"] !== null ? (float)$w["munkaora"] : null,
                "munkaora_tipus" => $w["munkaora_tipus"] ?? "havi",
                "booked"         => $bookedMap[(int)$w["id"]] ?? 0.0,
            ];
        }, $workers);

        $this->utils->jsonOut(["workers" => $result]);
        die;
    }

    private function _apiExportStatistics(): void {
        if (!$this->adminUser->beosztasPageAccess()) { die("Nincs jogosultságod!"); }
        $month = $_GET["month"] ?? date("Y-m");
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) { die("Érvénytelen hónap!"); }
        $monthStart = $month . "-01 00:00:00";
        $monthEnd   = date("Y-m-01 00:00:00", strtotime($monthStart . " +1 month"));

        $HU_DAYS  = ["Hétfő","Kedd","Szerda","Csütörtök","Péntek","Szombat","Vasárnap"];
        $HU_MONTHS = ["január","február","március","április","május","június","július","augusztus","szeptember","október","november","december"];
        $monthLabel = date("Y", strtotime($monthStart)).". ".$HU_MONTHS[(int)date("m", strtotime($monthStart))-1];

        $rows = sql_query(
            "SELECT m.workerid, DATE(m.datumfrom) AS datum, t.megnev AS tipusnev,
                    DATE_FORMAT(m.datumfrom,'%H:%i') AS kezdes,
                    DATE_FORMAT(m.datumto,'%H:%i') AS veges,
                    ROUND(TIMESTAMPDIFF(MINUTE,m.datumfrom,m.datumto)/60.0,2) AS ora
             FROM schedule_mapping m
             LEFT JOIN schedule_tipusok t ON t.id=m.tipusid
             WHERE m.datumfrom >= :start AND m.datumfrom < :end AND t.forday='0000-00-00'
             ORDER BY m.workerid, m.datumfrom",
            ["start" => $monthStart, "end" => $monthEnd]
        )->fetchAll(PDO::FETCH_ASSOC);

        $workers = sql_query(
            "SELECT id, nev, teljesnev, munkaora FROM schedule_workers ORDER BY roleid, nev"
        )->fetchAll(PDO::FETCH_ASSOC);

        $byWorker = [];
        foreach ($rows as $r) { $byWorker[(int)$r["workerid"]][] = $r; }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $sheetIdx = 0;

        foreach ($workers as $w) {
            $wid   = (int)$w["id"];
            $wrows = $byWorker[$wid] ?? [];
            $name  = trim($w["teljesnev"]) ?: $w["nev"];

            $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, mb_substr($name, 0, 31));
            $spreadsheet->addSheet($sheet, $sheetIdx++);

            $sheet->setCellValue("A1", "{$name} — {$monthLabel} jelenléti");
            $sheet->mergeCells("A1:F1");
            $sheet->getStyle("A1")->getFont()->setBold(true)->setSize(13);
            $quota = isset($w["munkaora"]) && $w["munkaora"] !== null ? ((float)$w["munkaora"]." óra/hó") : "—";
            $sheet->setCellValue("A2", "Kvóta: {$quota}");

            foreach (["A4"=>"Dátum","B4"=>"Nap","C4"=>"Rendelés","D4"=>"Kezdés","E4"=>"Befejezés","F4"=>"Óra"] as $cell=>$label) {
                $sheet->setCellValue($cell, $label);
            }
            $sheet->getStyle("A4:F4")->getFont()->setBold(true);

            $row = 5; $totalHours = 0.0;
            foreach ($wrows as $r) {
                $dayNum = (int)date("N", strtotime($r["datum"])) - 1;
                $sheet->setCellValue("A{$row}", $r["datum"]);
                $sheet->setCellValue("B{$row}", $HU_DAYS[$dayNum]);
                $sheet->setCellValue("C{$row}", $r["tipusnev"]);
                $sheet->setCellValue("D{$row}", $r["kezdes"]);
                $sheet->setCellValue("E{$row}", $r["veges"]);
                $sheet->setCellValue("F{$row}", (float)$r["ora"]);
                $totalHours += (float)$r["ora"];
                $row++;
            }
            $sheet->setCellValue("E{$row}", "Összesen:");
            $sheet->setCellValue("F{$row}", round($totalHours, 2));
            $sheet->getStyle("E{$row}:F{$row}")->getFont()->setBold(true);
            foreach (["A","B","C","D","E","F"] as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $spreadsheet->createSheet()->setTitle("Nincs adat");
        }

        $fileName = "jelenleti_{$month}.xlsx";
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment;filename=\"{$fileName}\"");
        header("Cache-Control: max-age=0");
        (new Xlsx($spreadsheet))->save("php://output");
        die;
    }

    private function _apiSaveBooking(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
            die;
        }

        $tipusId = intval($_POST["tipusid"] ?? 0);
        $datum   = $_POST["datum"] ?? "";
        $staff   = json_decode($_POST["staff"] ?? "[]", true) ?: [];

        if (!$tipusId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            $this->utils->jsonOut(["status" => "error", "message" => "Hiányzó vagy érvénytelen adatok!"]);
            die;
        }

        sql_query("DELETE FROM schedule_mapping WHERE tipusid=? AND DATE(datumfrom)=?", [$tipusId, $datum]);

        foreach ($staff as $s) {
            $workerId = intval($s["workerId"] ?? 0);
            if (!$workerId) continue;
            $from  = $s["from"] ?? "08:00";
            $to    = $s["to"]   ?? "16:00";
            if (!preg_match('/^\d{2}:\d{2}$/', $from) || !preg_match('/^\d{2}:\d{2}$/', $to)) continue;
            $roleId           = ($s["role"] ?? "d") === "n" ? 2 : (($s["role"] ?? "d") === "e" ? 3 : (($s["role"] ?? "d") === "v" ? 5 : 1));
            $megj             = substr($s["megj"] ?? "", 0, 200);
            $acceptedConflict = intval($s["acceptedConflict"] ?? 0) ? 1 : 0;
            sql_query(
                "INSERT INTO schedule_mapping SET datumfrom=?, datumto=?, napszak=0, tipusid=?, roleid=?, workerid=?, megj=?, accepted_conflict=?, createdat=now(), createdby=?",
                ["{$datum} {$from}:00", "{$datum} {$to}:00", $tipusId, $roleId, $workerId, $megj, $acceptedConflict, $this->adminUser->user["id"]]
            );
            if ($roleId === 4) {
                $this->workScheduleService->notifyScheduleChange($workerId);
            }
        }

        $this->workScheduleService->reloadScheduleMapping();
        $this->workScheduleService->recalcAllCollisions();

        $this->utils->jsonOut(["status" => "ok"]);
        die;
    }

    private function _apiCopyWeek(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
            die;
        }

        $copyFrom  = $_GET["copyfrom"] ?? "";
        $copyTo    = $_GET["copyto"]   ?? "";
        $overwrite = intval($_GET["overwrite"] ?? $_POST["overwrite"] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $copyFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $copyTo)) {
            $this->utils->jsonOut(["status" => "error", "message" => "Érvénytelen dátum!"]);
            die;
        }

        $distance = strtotime($copyTo) - strtotime($copyFrom);
        if ($distance > 0) {
            if ($overwrite) {
                $targetFrom = date("Y-m-d H:i:s", strtotime($copyFrom . " 00:00:00") + $distance);
                sql_query(
                    "DELETE m FROM schedule_mapping m
                     LEFT JOIN schedule_tipusok t ON t.id=m.tipusid
                     WHERE m.datumfrom>=? AND m.datumfrom<DATE_ADD(?, INTERVAL 7 DAY) AND t.forday='0000-00-00'",
                    [$targetFrom, $targetFrom]
                );
            }

            $copyDatas = sql_query(
                "SELECT m.* FROM schedule_mapping m
                 LEFT JOIN schedule_tipusok t ON t.id=m.tipusid
                 WHERE m.datumfrom>=:copyFrom AND m.datumfrom<DATE_ADD(:copyFrom, INTERVAL 7 DAY) AND t.forday='0000-00-00'",
                ["copyFrom" => $copyFrom . " 00:00:00"]
            )->fetchAll();

            foreach ($copyDatas as $row) {
                $newFrom = date("Y-m-d H:i:s", strtotime($row["datumfrom"]) + $distance);
                $newTo   = date("Y-m-d H:i:s", strtotime($row["datumto"])   + $distance);
                sql_query(
                    "INSERT INTO schedule_mapping SET datumfrom=?, datumto=?, napszak=?, tipusid=?, roleid=?, workerid=?, noverid=?, megj=?, createdat=now(), createdby=?",
                    [$newFrom, $newTo, $row["napszak"], $row["tipusid"], $row["roleid"], $row["workerid"], $row["noverid"], $row["megj"], $this->adminUser->user["id"]]
                );
            }

            sql_query("INSERT INTO schedule_naplo SET tipus='copy', cim=?, letrehozva=now(), userid=?",
                ["Hét másolva: {$copyFrom} → {$copyTo}" . ($overwrite ? " (felülírással)" : ""), $this->adminUser->user["id"]]);
        }

        $this->utils->jsonOut(["status" => "ok"]);
        die;
    }

    private function _apiGetStaff(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $roles   = sql_query("SELECT * FROM schedule_roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $workers = sql_query(
            "SELECT w.*, r.megnev AS rolenev FROM schedule_workers w
             LEFT JOIN schedule_roles r ON r.id=w.roleid
             ORDER BY w.roleid, COALESCE(w.aktiv,1) DESC, w.nev"
        )->fetchAll(PDO::FETCH_ASSOC);
        $users = sql_query("SELECT id, nev, beouserid FROM users ORDER BY nev")->fetchAll(PDO::FETCH_ASSOC);
        $onVacationIds = sql_query(
            "SELECT DISTINCT oid FROM schedule_szabadsag WHERE status=1 AND CURDATE() BETWEEN datumtol AND datumig"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->utils->jsonOut([
            "roles"   => $roles,
            "workers" => array_map(function ($w) use ($onVacationIds) {
                return [
                    "id"         => (int)$w["id"],
                    "nev"        => $w["nev"],
                    "teljesnev"  => $w["teljesnev"],
                    "roleid"     => (int)$w["roleid"],
                    "rolenev"    => $w["rolenev"],
                    "email"      => $w["email"],
                    "tel"        => $w["tel"],
                    "smsert"     => (int)$w["smsert"],
                    "emailert"   => (int)$w["emailert"],
                    "efo"        => (int)($w["efo"] ?? 0),
                    "onVacation" => in_array((int)$w["id"], $onVacationIds, true),
                    "munkaora"       => isset($w["munkaora"]) && $w["munkaora"] !== null ? (float)$w["munkaora"] : null,
                    "munkaora_tipus" => $w["munkaora_tipus"] ?? "havi",
                    "aktiv"          => (int)($w["aktiv"] ?? 1),
                ];
            }, $workers),
            "users" => array_map(fn($u) => ["id" => (int)$u["id"], "nev" => $u["nev"], "beouserid" => (int)$u["beouserid"]], $users),
        ]);
    }

    private function _apiSaveStaff(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $id        = intval($_POST["id"] ?? 0);
        $roleid    = intval($_POST["roleid"] ?? 0);
        $nev       = trim($_POST["nev"] ?? "");
        $teljesnev = trim($_POST["teljesnev"] ?? "");
        $email     = trim($_POST["email"] ?? "");
        $tel       = trim($_POST["tel"] ?? "");
        $smsert    = empty($_POST["smsert"])   ? 0 : 1;
        $emailert  = empty($_POST["emailert"]) ? 0 : 1;
        $efo       = empty($_POST["efo"]) ? 0 : 1;
        $beouserid = intval($_POST["beouserid"] ?? 0);
        $munkaora       = (isset($_POST["munkaora"]) && $_POST["munkaora"] !== "") ? floatval($_POST["munkaora"]) : null;
        $munkaora_tipus = in_array($_POST["munkaora_tipus"] ?? "", ["havi","heti"]) ? $_POST["munkaora_tipus"] : "havi";
        $aktiv          = isset($_POST["aktiv"]) ? (intval($_POST["aktiv"]) ? 1 : 0) : 1;

        if ($nev === "" || !$roleid) {
            $this->utils->jsonOut(["status" => "error", "message" => "Add meg a nevet és a típust!"]);
        }

        if ($id) {
            sql_query(
                "UPDATE schedule_workers SET roleid=?, nev=?, teljesnev=?, email=?, tel=?, smsert=?, emailert=?, efo=?, munkaora=?, munkaora_tipus=?, aktiv=? WHERE id=?",
                [$roleid, $nev, $teljesnev, $email, $tel, $smsert, $emailert, $efo, $munkaora, $munkaora_tipus, $aktiv, $id]
            );
        } else {
            sql_query(
                "INSERT INTO schedule_workers SET roleid=?, nev=?, teljesnev=?, email=?, tel=?, smsert=?, emailert=?, efo=?, munkaora=?, munkaora_tipus=?, aktiv=1",
                [$roleid, $nev, $teljesnev, $email, $tel, $smsert, $emailert, $efo, $munkaora, $munkaora_tipus]
            );
            $id = (int)sql_insert_id();
        }

        sql_query("UPDATE users SET beouserid=0 WHERE beouserid=?", [$id]);
        if ($beouserid > 0) {
            sql_query("UPDATE users SET beouserid=? WHERE id=?", [$id, $beouserid]);
        }

        $this->utils->jsonOut(["status" => "ok", "id" => $id]);
    }

    private function _apiDeleteStaff(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $id = intval($_POST["id"] ?? 0);
        if (!$id) {
            $this->utils->jsonOut(["status" => "error", "message" => "Hiányzó azonosító!"]);
        }

        sql_query("DELETE FROM schedule_workers WHERE id=?", [$id]);
        sql_query("UPDATE users SET beouserid=0 WHERE beouserid=?", [$id]);

        $this->utils->jsonOut(["status" => "ok"]);
    }

    private function _apiGetPlaces(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $rows = sql_query(
            "SELECT * FROM schedule_tipusok WHERE forday='0000-00-00' ORDER BY kulso, roleid, sorrend"
        )->fetchAll(PDO::FETCH_ASSOC);

        $roles = sql_query("SELECT * FROM schedule_roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

        $this->utils->jsonOut([
            "roles"  => $roles,
            "places" => array_map(function ($r) {
                return [
                    "id"           => (int)$r["id"],
                    "megnev"       => $r["megnev"],
                    "cim"          => $r["cim"],
                    "rendelo"      => $r["rendelo"] ?? "",
                    "megj"         => $r["megj"],
                    "kulso"        => (int)$r["kulso"],
                    "kiszallas"    => (int)($r["kiszallas"] ?? 0),
                    "org"          => $r["org"] ?: "HMM",
                    "roleid"       => (int)$r["roleid"],
                    "sorrend"      => (int)$r["sorrend"],
                    "aktiv"        => (int)$r["aktiv"],
                    "napok"        => (int)($r["napok"] ?? 127),
                    "orvos_kell"   => (int)($r["orvos_kell"] ?? 1),
                    "ktarto_nev"   => $r["ktarto_nev"]   ?? "",
                    "ktarto_tel"   => $r["ktarto_tel"]   ?? "",
                    "ktarto_email" => $r["ktarto_email"] ?? "",
                    "color"        => $r["color"] ?? null,
                    "validfrom"    => $r["validfrom"] ?? null,
                    "validto"      => $r["validto"]   ?? null,
                ];
            }, $rows),
        ]);
    }

    private function _apiAddPlace(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $roleid    = intval($_POST["roleid"]    ?? 1);
        $kulso     = intval($_POST["kulso"]     ?? 0);
        $kiszallas = intval($_POST["kiszallas"] ?? 0);
        $org       = in_array($_POST["org"] ?? "", ["HMM", "Keltexmed"]) ? $_POST["org"] : "HMM";
        $megnev    = trim($_POST["megnev"] ?? "") ?: ($kiszallas ? "_Új kiszállás" : "_Új rendelés");
        $cim       = trim($_POST["cim"] ?? "");
        $rendelo   = substr(trim($_POST["rendelo"] ?? ""), 0, 255);
        $megj      = substr(trim($_POST["megj"] ?? ""), 0, 200);
        $napok     = intval($_POST["napok"] ?? 31) & 0x7F;
        $orvosKell = intval($_POST["orvos_kell"] ?? 1) ? 1 : 0;
        $color     = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST["color"] ?? "") ? $_POST["color"] : null;
        $validfrom = null;
        $validto   = null;
        if ($kiszallas) {
            $vf = trim($_POST["validfrom"] ?? "");
            $vt = trim($_POST["validto"]   ?? "");
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $vf)) $validfrom = $vf;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $vt)) $validto   = $vt;
        }

        sql_query("INSERT INTO schedule_tipusok SET megnev=?, cim=?, rendelo=?, megj=?, roleid=?, kulso=?, kiszallas=?, org=?, aktiv=1, napok=?, orvos_kell=?, color=?, validfrom=?, validto=?",
            [$megnev, $cim, $rendelo, $megj, $roleid, $kulso, $kiszallas, $org, $napok, $orvosKell, $color, $validfrom, $validto]);

        $this->utils->jsonOut(["status" => "ok", "id" => (int)sql_insert_id()]);
    }

    private function _apiUpdatePlaceAddress(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $id      = intval($_POST["id"] ?? 0);
        $cim     = trim($_POST["cim"] ?? "");
        $megj    = substr(trim($_POST["megj"] ?? ""), 0, 200);
        $rendelo = substr(trim($_POST["rendelo"] ?? ""), 0, 255);
        $napok   = intval($_POST["napok"] ?? -1);
        $org     = in_array($_POST["org"] ?? "", ["HMM", "Keltexmed"]) ? $_POST["org"] : null;

        if (!$id) {
            $this->utils->jsonOut(["status" => "error", "message" => "Hiányzó azonosító!"]);
        }

        $ktarto_nev   = substr(trim($_POST["ktarto_nev"]   ?? ""), 0, 255);
        $ktarto_tel   = substr(trim($_POST["ktarto_tel"]   ?? ""), 0, 50);
        $ktarto_email = substr(trim($_POST["ktarto_email"] ?? ""), 0, 255);

        $fields = "cim=?, megj=?, rendelo=?, ktarto_nev=?, ktarto_tel=?, ktarto_email=?";
        $params = [$cim, $megj, $rendelo, $ktarto_nev, $ktarto_tel, $ktarto_email];
        if ($napok >= 0 && $napok <= 127) { $fields .= ", napok=?"; $params[] = $napok; }
        if ($org !== null)                { $fields .= ", org=?";   $params[] = $org;   }
        $params[] = $id;
        sql_query("UPDATE schedule_tipusok SET {$fields} WHERE id=?", $params);

        $this->utils->jsonOut(["status" => "ok"]);
    }

    private function _apiSavePlace(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $id           = intval($_POST["id"] ?? 0);
        $megnev       = trim($_POST["megnev"] ?? "");
        $cim          = trim($_POST["cim"] ?? "");
        $rendelo      = substr(trim($_POST["rendelo"]      ?? ""), 0, 255);
        $sorrend      = intval($_POST["sorrend"] ?? 0);
        $org          = in_array($_POST["org"] ?? "", ["HMM", "Keltexmed"]) ? $_POST["org"] : "HMM";
        $napok        = intval($_POST["napok"] ?? 127) & 0x7F;
        $cat          = $_POST["cat"] ?? "";
        $orvosKell    = intval($_POST["orvos_kell"] ?? 1) ? 1 : 0;
        $ktarto_nev   = substr(trim($_POST["ktarto_nev"]   ?? ""), 0, 255);
        $ktarto_tel   = substr(trim($_POST["ktarto_tel"]   ?? ""), 0, 50);
        $ktarto_email = substr(trim($_POST["ktarto_email"] ?? ""), 0, 255);
        $color        = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST["color"] ?? "") ? $_POST["color"] : null;

        if (!$id || $megnev === "") {
            $this->utils->jsonOut(["status" => "error", "message" => "Add meg a megnevezést!"]);
        }

        $cur       = sql_query("SELECT kulso, kiszallas FROM schedule_tipusok WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);
        $kulso     = $cat === "kulso" ? 1 : ($cat !== "" ? 0 : (int)$cur["kulso"]);
        $kiszallas = $cat === "kiszallas" ? 1 : ($cat !== "" ? 0 : (int)$cur["kiszallas"]);
        $validfrom = null;
        $validto   = null;
        if ($kiszallas) {
            $vf = trim($_POST["validfrom"] ?? "");
            $vt = trim($_POST["validto"]   ?? "");
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $vf)) $validfrom = $vf;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $vt)) $validto   = $vt;
        }

        sql_query("UPDATE schedule_tipusok SET megnev=?, cim=?, rendelo=?, sorrend=?, org=?, napok=?, kulso=?, kiszallas=?, ktarto_nev=?, ktarto_tel=?, ktarto_email=?, orvos_kell=?, color=?, validfrom=?, validto=? WHERE id=?",
            [$megnev, $cim, $rendelo, $sorrend, $org, $napok, $kulso, $kiszallas, $ktarto_nev, $ktarto_tel, $ktarto_email, $orvosKell, $color, $validfrom, $validto, $id]);

        $this->utils->jsonOut(["status" => "ok"]);
    }

    private function _apiDeletePlace(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $id = intval($_POST["id"] ?? 0);
        if (!$id) {
            $this->utils->jsonOut(["status" => "error", "message" => "Hiányzó azonosító!"]);
        }

        sql_query("DELETE FROM schedule_tipusok WHERE id=?", [$id]);

        $this->utils->jsonOut(["status" => "ok"]);
    }

    private function _apiOrderPlace(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $id        = intval($_POST["id"] ?? 0);
        $direction = $_POST["direction"] ?? "";
        $place     = sql_query("SELECT * FROM schedule_tipusok WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);

        if (!$place) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs ilyen helyszín!"]);
        }

        if ($direction === "up") {
            if ($row2 = sql_fetch_array(sql_query(
                "SELECT id, sorrend FROM schedule_tipusok WHERE roleid=? AND kulso=? AND sorrend<? ORDER BY sorrend DESC LIMIT 1",
                [$place["roleid"], $place["kulso"], $place["sorrend"]]
            ))) {
                sql_query("UPDATE schedule_tipusok SET sorrend=? WHERE id=?", [$row2["sorrend"], $id]);
                sql_query("UPDATE schedule_tipusok SET sorrend=? WHERE id=?", [$place["sorrend"], $row2["id"]]);
            }
        }
        if ($direction === "down") {
            if ($row2 = sql_fetch_array(sql_query(
                "SELECT id, sorrend FROM schedule_tipusok WHERE roleid=? AND kulso=? AND sorrend>? ORDER BY sorrend LIMIT 1",
                [$place["roleid"], $place["kulso"], $place["sorrend"]]
            ))) {
                sql_query("UPDATE schedule_tipusok SET sorrend=? WHERE id=?", [$row2["sorrend"], $id]);
                sql_query("UPDATE schedule_tipusok SET sorrend=? WHERE id=?", [$place["sorrend"], $row2["id"]]);
            }
        }

        $this->utils->jsonOut(["status" => "ok"]);
    }

    private function _apiSavePlaceOrder(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', trim($_POST['ids'] ?? '')))));
        foreach ($ids as $i => $id) {
            if ($id > 0) {
                sql_query("UPDATE schedule_tipusok SET sorrend=? WHERE id=?", [$i + 1, $id]);
            }
        }
        $this->utils->jsonOut(["status" => "ok"]);
    }

    private function _apiGetVacations(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $workers = sql_query(
            "SELECT w.id, IF(TRIM(w.teljesnev)<>'', w.teljesnev, w.nev) AS nev, w.roleid, r.megnev AS rolenev
             FROM schedule_workers w LEFT JOIN schedule_roles r ON r.id=w.roleid
             ORDER BY w.roleid, nev"
        )->fetchAll(PDO::FETCH_ASSOC);

        $rows = sql_query(
            "SELECT sz.groupid, sz.oid AS workerid, MIN(sz.datumtol) AS datumtol, MAX(sz.datumig) AS datumig,
                    MIN(sz.status) AS minstatus, MAX(sz.status) AS maxstatus, COUNT(*) AS napok,
                    MIN(sz.tipus) AS tipus, MIN(sz.megj) AS megj,
                    IF(TRIM(w.teljesnev)<>'', w.teljesnev, w.nev) AS workernev
             FROM schedule_szabadsag sz
             LEFT JOIN schedule_workers w ON w.id=sz.oid
             GROUP BY sz.groupid
             ORDER BY datumtol DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->utils->jsonOut([
            "workers"   => array_map(function ($w) {
                return ["id" => (int)$w["id"], "nev" => $w["nev"], "roleid" => (int)$w["roleid"], "rolenev" => $w["rolenev"]];
            }, $workers),
            "vacations" => array_map(function ($r) {
                $minStatus = (int)$r["minstatus"];
                $maxStatus = (int)$r["maxstatus"];
                return [
                    "groupid"    => (int)$r["groupid"],
                    "workerId"   => (int)$r["workerid"],
                    "workerName" => $r["workernev"],
                    "from"       => $r["datumtol"],
                    "to"         => $r["datumig"],
                    "days"       => (int)$r["napok"],
                    "status"     => $minStatus === $maxStatus ? $minStatus : -1,
                    "tipus"      => $r["tipus"] ?: "Szabadság",
                    "megj"       => $r["megj"] ?? "",
                ];
            }, $rows),
        ]);
    }

    private function _apiAddVacation(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $workerId = intval($_POST["workerid"] ?? 0);
        $tol      = $_POST["tol"] ?? "";
        $ig       = $_POST["ig"]  ?? "";
        $tipus    = in_array($_POST["tipus"] ?? "", ["Szabadság", "Betegszabadság", "Képzés", "Egyéb", "Elérhető"]) ? $_POST["tipus"] : "Szabadság";
        $megj     = substr(trim($_POST["megj"] ?? ""), 0, 200) ?: null;

        if (!$workerId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tol) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ig)) {
            $this->utils->jsonOut(["status" => "error", "message" => "Add meg a munkatársat és a szabadság kezdő/vég napját!"]);
        }
        if (strtotime($tol) > strtotime($ig)) {
            $this->utils->jsonOut(["status" => "error", "message" => "A kezdő dátum nem lehet később, mint a vég dátum!"]);
        }
        $groupId   = 0;
        $cur       = $tol;
        while (strtotime($cur) <= strtotime($ig)) {
            sql_query("INSERT INTO schedule_szabadsag SET datumtol=?, datumig=?, oid=?, tipus=?, megj=?", [$cur, $cur, $workerId, $tipus, $megj]);
            $newId = sql_insert_id();
            if ($groupId === 0) $groupId = $newId;
            sql_query("UPDATE schedule_szabadsag SET groupid=? WHERE id=?", [$groupId, $newId]);
            $cur = date("Y-m-d", strtotime("{$cur} +1 day"));
        }

        $workerName = sql_query("SELECT IF(TRIM(teljesnev)<>'',teljesnev,nev) AS n FROM schedule_workers WHERE id=?", [$workerId])->fetchColumn();
        sql_query("INSERT INTO schedule_naplo SET tipus='vacation', cim=?, letrehozva=now(), userid=?",
            ["Szabadság felvéve: {$workerName} ({$tol} – {$ig})", $this->adminUser->user["id"]]);

        $this->utils->jsonOut(["status" => "ok"]);
    }

    private function _apiSetVacationGroupStatus(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }
        if (!$this->adminUser->checkPermission("jog_szabi_beosztas")) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod a szabadság állapotának változtatásához!"]);
        }

        $groupId = intval($_POST["groupid"] ?? 0);
        $status  = intval($_POST["status"] ?? 0);

        sql_query("UPDATE schedule_szabadsag SET status=? WHERE groupid=?", [$status, $groupId]);

        $this->utils->jsonOut(["status" => "ok"]);
    }

    private function _apiDeleteVacation(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $groupId = intval($_POST["groupid"] ?? 0);
        if (!$groupId) {
            $this->utils->jsonOut(["status" => "error", "message" => "Hiányzó azonosító!"]);
        }

        sql_query("DELETE FROM schedule_szabadsag WHERE groupid=?", [$groupId]);

        $this->utils->jsonOut(["status" => "ok"]);
    }

    private function _apiDismissConflict(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]); die;
        }
        $tipusId  = intval($_POST["tipusid"]  ?? 0);
        $datum    = $_POST["datum"]   ?? "";
        $workerId = intval($_POST["workerid"] ?? 0);
        if (!$tipusId || !$datum || !$workerId) {
            $this->utils->jsonOut(["status" => "error", "message" => "Hiányzó adatok"]); die;
        }
        sql_query(
            "UPDATE schedule_mapping SET accepted_conflict=1 WHERE tipusid=? AND DATE(datumfrom)=? AND workerid=?",
            [$tipusId, $datum, $workerId]
        );
        $this->utils->jsonOut(["status" => "ok"]);
        die;
    }

    private function _apiToggleBookingAktiv(): void {
        $tipusId = intval($_POST["tipusid"] ?? 0);
        $datum   = $_POST["datum"] ?? "";
        $aktiv   = intval($_POST["aktiv"] ?? 1) ? 1 : 0;
        if (!$tipusId || !$datum) {
            $this->utils->jsonOut(["status" => "error", "message" => "Hiányzó adatok"]);
            die;
        }
        sql_query("UPDATE schedule_mapping SET aktiv=? WHERE tipusid=? AND DATE(datumfrom)=?", [$aktiv, $tipusId, $datum]);
        if ($aktiv === 0) {
            sql_query("INSERT INTO schedule_datum_aktiv (tipusid, datum, aktiv) VALUES (?,?,0) ON DUPLICATE KEY UPDATE aktiv=0", [$tipusId, $datum]);
        } else {
            sql_query("DELETE FROM schedule_datum_aktiv WHERE tipusid=? AND datum=?", [$tipusId, $datum]);
        }
        $this->utils->jsonOut(["status" => "ok"]);
        die;
    }

    private function _apiGetNotifications(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $workers = sql_query(
            "SELECT w.* FROM schedule_workers w ORDER BY w.roleid, w.nev"
        )->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($workers as $w) {
            $changed = sql_query(
                "SELECT 1 FROM schedule_mapping m
                 WHERE m.datumfrom>=DATE(DATE_ADD(NOW(), INTERVAL 1 DAY))
                   AND m.notifyhash<>md5(concat(m.datumfrom, m.datumto))
                   AND m.aktiv=1
                   AND m.workerid=:uid
                   AND NOT EXISTS (
                       SELECT 1 FROM schedule_datum_aktiv sda
                       WHERE sda.tipusid=m.tipusid AND sda.datum=DATE(m.datumfrom) AND sda.aktiv=0
                   ) LIMIT 1",
                ["uid" => $w["id"]]
            )->fetch();

            if (!$changed) continue;

            $items[] = [
                "id"           => (int)$w["id"],
                "name"         => !empty($w["teljesnev"]) ? $w["teljesnev"] : $w["nev"],
                "phone"        => $w["tel"],
                "email"        => $w["email"],
                "smsDefault"   => $w["tel"]   !== "" && (int)$w["smsert"]   === 1,
                "emailDefault" => $w["email"] !== "" && (int)$w["emailert"] === 1,
            ];
        }

        $this->utils->jsonOut(["items" => $items]);
    }

    private function _apiSendNotify(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $workerId = intval($_POST["workerid"] ?? 0);
        $sms      = !empty($_POST["sms"]);
        $email    = !empty($_POST["email"]);

        if (!$workerId || (!$sms && !$email)) {
            $this->utils->jsonOut(["status" => "error", "message" => "Válassz legalább egy értesítési módot!"]);
        }

        if ($sms)   $this->workScheduleService->notifyScheduleChange($workerId, "sms");
        if ($email) $this->workScheduleService->notifyScheduleChange($workerId, "email");

        sql_query("UPDATE schedule_mapping SET notifyhash=md5(concat(datumfrom, datumto)) WHERE datumfrom>NOW() AND workerid=?", [$workerId]);

        $this->utils->jsonOut(["status" => "ok"]);
    }

    private function _apiSendBulkNotify(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $message    = trim($_POST["message"] ?? "");
        $recipients = json_decode($_POST["recipients"] ?? "[]", true) ?: [];

        if ($message === "" || !$recipients) {
            $this->utils->jsonOut(["status" => "error", "message" => "Adj meg üzenetet és legalább egy címzettet!"]);
        }

        $utils = new Utils();
        $sent  = 0;
        foreach ($recipients as $r) {
            $w = sql_query("SELECT * FROM schedule_workers WHERE id=?", [intval($r["workerId"] ?? 0)])->fetch(PDO::FETCH_ASSOC);
            if (!$w) continue;

            if (!empty($r["sms"]) && $w["tel"]) {
                $utils->sendSMS($w["tel"], $message);
                $sent++;
            }
            if (!empty($r["email"]) && $w["email"]) {
                $mail = NotificationService::getDefaultMailer();
                $mail->AddAddress($w["email"]);
                $mail->Subject = "[" . Booking_Constants::COMPANY_NAME_SHORT . "] Értesítés";
                $mail->Body    = nl2br(htmlspecialchars($message));
                $mail->Send();
                $sent++;
            }
        }

        sql_query("INSERT INTO schedule_naplo SET tipus='send', cim=?, letrehozva=now(), userid=?",
            ["Értesítés kiküldve ({$sent} címzés)", $this->adminUser->user["id"]]);

        $this->utils->jsonOut(["status" => "ok", "sent" => $sent]);
    }

    private function _apiGetNaplo(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $rows = sql_query("SELECT tipus, cim, letrehozva FROM schedule_naplo ORDER BY letrehozva DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

        $this->utils->jsonOut(["items" => $rows]);
    }

    private function _apiGetWeekWorkers(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $monday = $_GET["monday"] ?? "";
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $monday)) {
            $monday = date("Y-m-d", strtotime("this week monday"));
        }
        $mondayStart = $monday . " 00:00:00";

        $rows = sql_query(
            "SELECT DISTINCT w.id, w.nev, w.teljesnev, w.tel, w.email, w.smsert, w.emailert
             FROM schedule_mapping m
             JOIN schedule_workers w ON w.id = m.workerid
             WHERE m.datumfrom >= :from AND m.datumfrom < DATE_ADD(:from, INTERVAL 7 DAY)
             AND COALESCE(w.aktiv, 1) = 1
             AND m.aktiv = 1
             AND NOT EXISTS (
                 SELECT 1 FROM schedule_datum_aktiv sda
                 WHERE sda.tipusid = m.tipusid AND sda.datum = DATE(m.datumfrom) AND sda.aktiv = 0
             )
             ORDER BY w.roleid, w.nev",
            ["from" => $mondayStart]
        )->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(fn($w) => [
            "id"           => (int)$w["id"],
            "name"         => !empty($w["teljesnev"]) ? $w["teljesnev"] : $w["nev"],
            "phone"        => $w["tel"],
            "email"        => $w["email"],
            "smsDefault"   => $w["tel"]   !== "" && (int)$w["smsert"]   === 1,
            "emailDefault" => $w["email"] !== "" && (int)$w["emailert"] === 1,
        ], $rows);

        $this->utils->jsonOut(["items" => $items, "monday" => $monday]);
    }

}

