<?php

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

        if (isset($_POST["saveplace"])) {
            $this->_apiSavePlace();
        }

        if (isset($_POST["deleteplace"])) {
            $this->_apiDeletePlace();
        }

        if (isset($_POST["orderplace"])) {
            $this->_apiOrderPlace();
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

        if (isset($_GET["getnotifications"])) {
            $this->_apiGetNotifications();
        }

        if (isset($_POST["sendnotify"])) {
            $this->_apiSendNotify();
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

        echo "<div id='menusor'>";
        echo "<a href='index.php?page={$_GET["page"]}'>Beosztások</a> &bull; ";
        echo "<a href='index.php?page={$_GET["page"]}&subpage=workers'>Munkatársak</a> &bull; ";
        echo "<a href='index.php?page={$_GET["page"]}&subpage=vacations'>Szabadságok</a> &bull; ";
        echo "<a href='index.php?page={$_GET["page"]}&subpage=workplaces'>Munkahelyek</a> &bull; ";
        echo "<a href='index.php?page={$_GET["page"]}&subpage=notify'>Értesítések</a> &bull; ";
        echo "<a href='index.php'>Vissza az adminba</a>";
        echo "</div>";

        if ($this->subPage == "beosztasok") {
            $GLOBALS["fullscreen_react"] = true;
            $this->_showReactPage();
            return;
        }

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

        echo "<div id='workerbeosztasdiv' style='margin:10px;'>";
        echo $this->workScheduleService->workerScheduleList($workerData["id"]);
        echo "</div>";
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

        if ($szabiData = sql_query("select w.nev from schedule_szabadsag sz left join schedule_workers w on sz.oid = w.id where sz.datumtol=?", [$this->thisDay])->fetchAll()) {
            $html .= "<div class='scheduledayhead' style='background:#ff6961;color:#fff;'>{$this->thisDay} " . $this->settings->hetnap[$weekDay] . "<br/>Szabadságok</div>";
            foreach ($szabiData as $data) {
                $html.="<div style='padding:2px;'>{$data["nev"]}</div>";
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
        echo "  <title>HMM – Munkaidő beosztás</title>\n";
        echo "  <script src='https://cdn.tailwindcss.com'></script>\n";
        echo "  <script src='https://unpkg.com/react@18/umd/react.production.min.js' crossorigin></script>\n";
        echo "  <script src='https://unpkg.com/react-dom@18/umd/react-dom.production.min.js' crossorigin></script>\n";
        echo "  <script src='https://unpkg.com/@babel/standalone/babel.min.js'></script>\n";
        echo "  <link href='https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap' rel='stylesheet'>\n";
        echo "</head>\n<body style='margin:0;padding:0;overflow:hidden;'>\n";
        echo "  <div id='hmm-schedule-root'></div>\n";
        echo "  <script>\n";
        echo "    window.HMM_SCHEDULE_CONFIG = { url: {$pageUrl}, offset: {$offset}, adminName: {$adminName} };\n";
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
            "SELECT * FROM schedule_tipusok WHERE aktiv=1 ORDER BY kulso, sorrend"
        )->fetchAll(PDO::FETCH_ASSOC);

        $mondayStart = $monday . " 00:00:00";
        $mappings = sql_query(
            "SELECT m.id, m.datumfrom, m.datumto, m.tipusid, m.roleid, m.workerid, m.megj,
                    IF(TRIM(w.teljesnev) <> '', w.teljesnev, w.nev) AS workernev
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

        $weekEnd = date("Y-m-d", strtotime($monday . " +6 days"));
        $vacationRows = sql_query(
            "SELECT sz.oid AS workerid, sz.datumtol, sz.datumig, sz.status,
                    IF(TRIM(w.teljesnev) <> '', w.teljesnev, w.nev) AS workernev
             FROM schedule_szabadsag sz
             LEFT JOIN schedule_workers w ON w.id = sz.oid
             WHERE sz.status IN (0,1) AND sz.datumtol <= :weekend AND sz.datumig >= :monday",
            ["monday" => $monday, "weekend" => $weekEnd]
        )->fetchAll(PDO::FETCH_ASSOC);

        $vacByDate = [];
        foreach ($vacationRows as $v) {
            $cur = max($v["datumtol"], $monday);
            $end = min($v["datumig"], $weekEnd);
            while ($cur <= $end) {
                $vacByDate[$cur][] = [
                    "workerId" => (int)$v["workerid"],
                    "name"     => $v["workernev"],
                    "status"   => (int)$v["status"],
                ];
                $cur = date("Y-m-d", strtotime($cur . " +1 day"));
            }
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date     = date("Y-m-d", strtotime($monday . " +{$i} days"));
            $bookings = [];

            foreach ($allTipusok as $tipus) {
                $forDay = ($tipus["forday"] !== "0000-00-00");
                if ($forDay && $tipus["forday"] !== $date) continue;

                $key      = "{$date}_{$tipus["id"]}";
                $staffRows = $mappingIdx[$key] ?? [];

                $staff   = [];
                $minFrom = null;
                $maxTo   = null;

                foreach ($staffRows as $m) {
                    $role = ($m["roleid"] == 2) ? "n" : "d";
                    $from = date("H:i", strtotime($m["datumfrom"]));
                    $to   = date("H:i", strtotime($m["datumto"]));

                    $staff[] = [
                        "mapId"    => (int)$m["id"],
                        "role"     => $role,
                        "name"     => $m["workernev"],
                        "workerId" => (int)$m["workerid"],
                        "from"     => $from,
                        "to"       => $to,
                        "megj"     => $m["megj"] ?? ""
                    ];

                    if ($minFrom === null || $from < $minFrom) $minFrom = $from;
                    if ($maxTo   === null || $to   > $maxTo)   $maxTo   = $to;
                }

                $bookings[] = [
                    "id"      => "tip_{$tipus["id"]}_{$date}",
                    "tipusId" => (int)$tipus["id"],
                    "cat"     => $tipus["kulso"] == 0 ? "belso" : "kulso",
                    "title"   => $tipus["megnev"],
                    "address" => $tipus["cim"]  ?? "",
                    "note"    => $tipus["megj"] ?? "",
                    "from"    => $minFrom ?? "08:00",
                    "to"      => $maxTo   ?? "16:00",
                    "date"    => $date,
                    "staff"   => $staff,
                    "forDay"  => $forDay
                ];
            }

            $days[] = ["date" => $date, "dayIndex" => $i, "bookings" => $bookings, "vacations" => $vacByDate[$date] ?? []];
        }

        $doctorRows = sql_query(
            "SELECT id, IF(TRIM(teljesnev) <> '', teljesnev, nev) AS nev FROM schedule_workers WHERE roleid=1 ORDER BY nev"
        )->fetchAll(PDO::FETCH_ASSOC);
        $assistantRows = sql_query(
            "SELECT id, IF(TRIM(teljesnev) <> '', teljesnev, nev) AS nev FROM schedule_workers WHERE roleid=2 ORDER BY nev"
        )->fetchAll(PDO::FETCH_ASSOC);

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
        ]);
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
            $roleId  = ($s["role"] ?? "d") === "n" ? 2 : 1;
            $megj    = substr($s["megj"] ?? "", 0, 200);
            sql_query(
                "INSERT INTO schedule_mapping SET datumfrom=?, datumto=?, napszak=0, tipusid=?, roleid=?, workerid=?, megj=?, createdat=now(), createdby=?",
                ["{$datum} {$from}:00", "{$datum} {$to}:00", $tipusId, $roleId, $workerId, $megj, $this->adminUser->user["id"]]
            );
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

        $copyFrom = $_GET["copyfrom"] ?? "";
        $copyTo   = $_GET["copyto"]   ?? "";

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $copyFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $copyTo)) {
            $this->utils->jsonOut(["status" => "error", "message" => "Érvénytelen dátum!"]);
            die;
        }

        $distance = strtotime($copyTo) - strtotime($copyFrom);
        if ($distance > 0) {
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
             ORDER BY w.roleid, w.nev"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->utils->jsonOut([
            "roles"   => $roles,
            "workers" => array_map(function ($w) {
                return [
                    "id"        => (int)$w["id"],
                    "nev"       => $w["nev"],
                    "teljesnev" => $w["teljesnev"],
                    "roleid"    => (int)$w["roleid"],
                    "rolenev"   => $w["rolenev"],
                    "email"     => $w["email"],
                    "tel"       => $w["tel"],
                    "smsert"    => (int)$w["smsert"],
                    "emailert"  => (int)$w["emailert"],
                ];
            }, $workers),
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

        if ($nev === "" || !$roleid) {
            $this->utils->jsonOut(["status" => "error", "message" => "Add meg a nevet és a típust!"]);
        }

        if ($id) {
            sql_query(
                "UPDATE schedule_workers SET roleid=?, nev=?, teljesnev=?, email=?, tel=?, smsert=?, emailert=? WHERE id=?",
                [$roleid, $nev, $teljesnev, $email, $tel, $smsert, $emailert, $id]
            );
        } else {
            sql_query(
                "INSERT INTO schedule_workers SET roleid=?, nev=?, teljesnev=?, email=?, tel=?, smsert=?, emailert=?",
                [$roleid, $nev, $teljesnev, $email, $tel, $smsert, $emailert]
            );
            $id = (int)sql_insert_id();
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
                    "id"      => (int)$r["id"],
                    "megnev"  => $r["megnev"],
                    "cim"     => $r["cim"],
                    "megj"    => $r["megj"],
                    "kulso"   => (int)$r["kulso"],
                    "roleid"  => (int)$r["roleid"],
                    "sorrend" => (int)$r["sorrend"],
                    "aktiv"   => (int)$r["aktiv"],
                ];
            }, $rows),
        ]);
    }

    private function _apiAddPlace(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $roleid = intval($_POST["roleid"] ?? 0);
        $kulso  = intval($_POST["kulso"]  ?? 0);

        sql_query("INSERT INTO schedule_tipusok SET megnev='_Új helyszín', roleid=?, kulso=?, aktiv=1", [$roleid, $kulso]);

        $this->utils->jsonOut(["status" => "ok", "id" => (int)sql_insert_id()]);
    }

    private function _apiSavePlace(): void {
        if (!$this->adminUser->beosztasPageAccess()) {
            $this->utils->jsonOut(["status" => "error", "message" => "Nincs jogosultságod!"]);
        }

        $id      = intval($_POST["id"] ?? 0);
        $megnev  = trim($_POST["megnev"] ?? "");
        $cim     = trim($_POST["cim"] ?? "");
        $sorrend = intval($_POST["sorrend"] ?? 0);

        if (!$id || $megnev === "") {
            $this->utils->jsonOut(["status" => "error", "message" => "Add meg a megnevezést!"]);
        }

        sql_query("UPDATE schedule_tipusok SET megnev=?, cim=?, sorrend=? WHERE id=?", [$megnev, $cim, $sorrend, $id]);

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

        if (!$workerId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tol) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ig)) {
            $this->utils->jsonOut(["status" => "error", "message" => "Add meg a munkatársat és a szabadság kezdő/vég napját!"]);
        }
        if (strtotime($tol) > strtotime($ig)) {
            $this->utils->jsonOut(["status" => "error", "message" => "A kezdő dátum nem lehet később, mint a vég dátum!"]);
        }
        if (strtotime($ig) - strtotime($tol) > 86400 * 31) {
            $this->utils->jsonOut(["status" => "error", "message" => "A szabadság nem lehet hosszabb, mint 1 hónap!"]);
        }

        $groupId = 0;
        $cur = $tol;
        while (strtotime($cur) <= strtotime($ig)) {
            sql_query("INSERT INTO schedule_szabadsag SET datumtol=?, datumig=?, oid=?", [$cur, $cur, $workerId]);
            $newId = sql_insert_id();
            if ($groupId === 0) $groupId = $newId;
            sql_query("UPDATE schedule_szabadsag SET groupid=? WHERE id=?", [$groupId, $newId]);
            $cur = date("Y-m-d", strtotime("{$cur} +1 day"));
        }

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
                 WHERE m.datumfrom>=DATE(DATE_ADD(NOW(), INTERVAL 1 DAY)) AND m.notifyhash<>md5(concat(m.datumfrom, m.datumto)) AND m.workerid=:uid LIMIT 1",
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

}

