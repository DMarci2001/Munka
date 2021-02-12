<?php

class AdminWorkSchedulePage extends AdminCorePage {

    private $bookingService;
    private $workScheduleService;
    private $settings;
    private $workersSubPage;
    private $workplacesSubPage;
    private $notifySubPage;
    private $subPage = "beosztasok";
    private $adminUser;

    private $napszakok = ["Délelőtt", "Délután"];

    public function __construct()
    {
        parent::__construct();

        $this->workScheduleService = new WorkScheduleService();
        $this->workersSubPage = new WorkersSubPage($this->workScheduleService);
        $this->workplacesSubPage = new WorkplacesSubPage($this->workScheduleService);
        $this->notifySubPage = new NotifySubPage($this->workScheduleService);
        $this->settings = new Booking_Settings();
        $this->adminUser = new AdminUser();

        if (!isset($_SESSION["wpoffset"])) {
            $_SESSION["wpoffset"] = 0;
        }

        if (isset($_GET["setwpoffset"])) {
            $_SESSION["wpoffset"] = $_GET["setwpoffset"];
        }

        if (isset($_GET["subpage"])) {
            $this->subPage = $_GET["subpage"];
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
                    sql_query("update schedule_mapping set datumfrom=:datumFrom, datumto=:datumTo, napszak=:napszak, tipusid=:tipusId, roleid=:roleId, workerid=:workerId, megj=:megj where id=:id", $params);
                }

                $this->workScheduleService->reloadScheduleMapping();
                $result["message"] = $this->_scheduleDay($_POST["datum"]);
            }

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

            echo "<div style='display:table-cell;vertical-align: top;padding-right: 10px;'>";
            echo "<input type='hidden' name='napszak' value='{$_POST["napszak"]}' />";
            echo "<input type='hidden' name='mapid' value='{$mapId}' />";
            echo "<input type='hidden' name='roleid' value='{$_POST["roleid"]}' />";
            echo "<input type='hidden' name='datum' value='{$_POST["datum"]}' />";
            echo "<input type='hidden' name='tipusid' value='{$_POST["tipusid"]}' />";
            echo "<select size='6' name='workerselector' id='workerselector' style='width:250px;'>";
            $res = sql_query("select * from schedule_workers where roleid=? order by nev", array($_POST["roleid"]));
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
            echo "<div style='display:table-cell;vertical-align: top;'>";
            echo "<select id='doctortol' name='workertol' style='width:80px;'>";
            echo "<option value='0'>Kezdés?</option>";
            while ($hour<23) {
                $d = (isset($mapData["datumfrom"])?date("H:i", strtotime($mapData["datumfrom"])):"");
                $t = date("H:i",mktime($startHour,$n,0,1,1, date("Y")));
                $hour = date("H",mktime($startHour,$n,0,1,1, date("Y")));
                echo "<option value='{$t}'".($d==$t?" selected":"").">{$t}</option>";
                $n+=15;
            }
            echo "</select> - ";

            $hour = $n = 0;
            echo "<select id='doctorig' name='workerig' style='width:80px;'>";
            echo "<option value='0'>Vége?</option>";
            while ($hour<23) {
                $d = (isset($mapData["datumto"])?date("H:i", strtotime($mapData["datumto"])):"");
                $t = date("H:i",mktime($startHour,$n,0,1,1, date("Y")));
                $hour = date("H",mktime($startHour,$n,0,1,1, date("Y")));
                echo "<option value='{$t}'".($d==$t?" selected":"").">{$t}</option>";
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

        if (isset($_GET["copyfrom"])) {
            $distance = strtotime($_GET["copyto"]) - strtotime($_GET["copyfrom"]);

            if ($distance > 0) {
                $copyDatas = sql_query("SELECT * FROM schedule_mapping m WHERE m.datumfrom>=:copyFrom AND m.datumfrom<DATE_ADD(:copyFrom, INTERVAL 7 DAY)", ["copyFrom" => $_GET["copyfrom"]." 00:00:00"])->fetchAll();

                foreach ($copyDatas as $copyData) {
                    $newTimeStart = date("Y-m-d H:i:s", strtotime("{$copyData["datumfrom"]} + {$distance} second"));
                    $newTimeEnd   = date("Y-m-d H:i:s", strtotime("{$copyData["datumfrom"]} + {$distance} second"));

                    //echo $copyData["datumfrom"]." ".$newTimeStart;

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
        echo "<a href='index.php?page={$_GET["page"]}&subpage=workplaces'>Munkahelyek</a> &bull; ";
        echo "<a href='index.php?page={$_GET["page"]}&subpage=notify'>Értesítések</a>";
        echo "</div>";

        if ($this->subPage == "beosztasok") {
            $offset = $_SESSION["wpoffset"];
            $off = $offset*7;
            if ($off >= 0) {
                $off = "+{$off}";
            }

            $thisYear = date("Y", strtotime("this week monday {$off} day"));
            $thisWeek = date("W", strtotime("this week monday {$off} day"));
            $copyFromDate = date("Y-m-d", strtotime("this week monday {$off} day"));

            echo "<div id='schedulesubpage' style='white-space: nowrap;margin-top:20px;'>";

            echo "<div style='margin:0px 0px 10px 0px;'>";
            echo "<div style='display:table-cell;font-size: 18px;vertical-align: middle;'>";
            echo "<a title='előző hét' href='index.php?page={$_GET["page"]}&setwpoffset=".($offset-1)."'><i class='fas fa-angle-double-left'></i></a>&nbsp;";
            echo "<a title='következő hét' href='index.php?page={$_GET["page"]}&setwpoffset=".($offset+1)."'><i class='fas fa-angle-double-right'></i></a>&nbsp;";
            echo "</div>";
            echo "<div style='display:table-cell;vertical-align: middle;'>";
            echo "<strong>{$thisYear} {$thisWeek}. hét</strong>";
            echo " &bull; <a href='#' onclick='$(\"#weekcopydiv\").slideToggle();return false;'>hét másolása</a>";
            echo " &bull; <a href='index.php?page={$_GET["page"]}&clearweek' onclick='return confirm(\"Biztos törlöd ennek a hétnek az összes beosztását?\")'>heti beosztás törlése</a>";
            echo "</div>";
            echo "</div>";

            echo "<div id='weekcopydiv' style='margin:0px 0px 10px 0px;display:none;'>";
            echo "<div>Válaszd ki melyik héthez szeretnéd másolni ezt a hetet:</div>";

            echo "<div style='padding-top: 10px;'>";
            for ($i = 0; $i < 10; $i++) {
                $off = $offset*7+$i*7;
                if ($off >= 0) {
                    $off = "+{$off}";
                }

                $thisYear    = date("Y", strtotime("this week monday {$off} day"));
                $thisWeek    = date("W", strtotime("this week monday {$off} day"));
                $thisDate    = date("Y-m-d", strtotime("this week monday {$off} day"));
                $thisDateEnd = date("Y-m-d", strtotime("this week sunday {$off} day"));

                echo "<div>";
                echo "{$thisYear} {$thisWeek}. hét ({$thisDate} - {$thisDateEnd})&nbsp;";
                if ($copyFromDate != $thisDate) {
                    echo "<a onclick='return confirm(\"Biztos átmásolod ide: {$thisDate} - {$thisDateEnd} ?\");' href='index.php?page={$_GET["page"]}&copyfrom={$copyFromDate}&copyto={$thisDate}'>másolás ide</a>";
                }
                echo "</div>";
            }
            echo "</div>";

            echo "</div>";



            for ($i = 0; $i < 7; $i++) {
                $off = $offset*7+$i;
                if ($off >= 0) {
                    $off = "+{$off}";
                }
                $thisDay = date("Y-m-d", strtotime("this week monday {$off} day"));
                echo "<div class='scheduleday' id='daycontainer{$thisDay}'>";
                echo $this->_scheduleDay($thisDay);
                echo "</div>";
            }
            echo "</div>";
        }

        if ($this->subPage == "workers") {
            echo "<div id='workersubpage' style='margin-top:20px;'>";
            echo $this->workersSubPage->showPage();
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

        echo "<div id='schdialog' class='sch_dialog'><div class='sch_dialogtop'></div><form name='dialogform' id='dialogform' method='post'><div class='sch_dialogcontent'></div></form></div>";
    }


    public function showPublicPage($token) {
        if (!$workerData = sql_query("select * from schedule_workers w where concat(sha1(concat(w.id, w.roleid, w.email, w.tel)), md5(concat(w.email, w.tel))) = ?", [$token])->fetch()) {
            echo "Nincs jogosultságod!";
            return;
        }

        echo "<div style='margin:10px;'>";


        echo "<h2>{$workerData["nev"]} beosztása</h2>";

        echo $this->workScheduleService->workerScheduleList($workerData);

        /*
        if ($this->subPage == "beosztasok") {
            $offset = $_SESSION["wpoffset"];

            echo "<div id='schedulesubpage' style='white-space: nowrap;margin-top:20px;'>";

            echo "<div style='margin:0px 0px 0px 0px;font-size:18px;'>";
            echo "<a title='előző hét' href='index.php?page={$_GET["page"]}&setwpoffset=".($offset-1)."'><i class='fas fa-angle-double-left'></i></a>&nbsp;";
            echo "<a title='következő hét' href='index.php?page={$_GET["page"]}&setwpoffset=".($offset+1)."'><i class='fas fa-angle-double-right'></i></a>";
            echo "</div>";

            for ($i = 0; $i < 7; $i++) {
                $off = $offset*7+$i;
                if ($off >= 0) {
                    $off = "+{$off}";
                }
                $thisDay = date("Y-m-d", strtotime("this week monday {$off} day"));
                echo "<div class='scheduleday' id='daycontainer{$thisDay}'>";
                echo $this->_scheduleDay($thisDay);
                echo "</div>";
            }
            echo "</div>";
        }
        */
        //echo "<div id='schdialog' class='sch_dialog'><div class='sch_dialogtop'></div><form name='dialogform' id='dialogform' method='post'><div class='sch_dialogcontent'></div></form></div>";

        echo "</div>";
    }



    private function _scheduleDay($thisDay) {
        $this->thisDay = $thisDay;
        $weekDay = date("N", strtotime($thisDay));
        $html = "";

        $html.= "<div class='scheduledayhead'>".$this->adminUtils->magyarDatum($this->thisDay)."</div>";

        for ($this->napszak = 0; $this->napszak<=1; $this->napszak++) {
            $html .= "<div class='schedulenapszakhead'>".$this->napszakok[$this->napszak]."</div>";

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
        }

        $html .= "<div class='scheduledayhead'>{$this->thisDay} ".$this->settings->hetnap[$weekDay]."<br/>Külső cégek</div>";
        $html .= "<div style='display:table-row;'>";
        $html .= $this->_rendeloFejCell("Cégek");
        $html .= $this->_workerFejCell("Orvos");
        $html .= $this->_workerFejCell("Nővér");
        $html .= "</div>";
        $resTipus = sql_query("select * from schedule_tipusok where kulso=1 order by roleid, sorrend");
        while ($tipusData = sql_fetch_array($resTipus)) {
            $html .= "<div style='display:table-row;'>";
            $html .= $this->_rendeloCell($tipusData);
            $html .= $this->_workerCell($tipusData);
            $html .= $this->_workerCell($tipusData, 2);
            $html .= "</div>";
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

    private function _rendeloCell($tipusData) {
        $extraStyle = ($tipusData["roleid"]!=1?" style='background:#daeef3;'":"");
        $html="";
        $html.="<div class='sch_oszlopdatacell' {$extraStyle}>{$tipusData["megnev"]}</div>";
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
        $html.="<a data-mapid='0' data-datum='{$this->thisDay}' data-roleid='{$roleId}' data-tipusid='{$tipusData["id"]}' data-tipusnev='{$tipusName}' data-napszak='{$this->napszak}' onclick='Schedule.ShowAddWorkerDialog(this);return false;' href='#'><img src='/admin/images/add.png' class='sch_plusbtn'></a>";
        $html.="</div>";

        $html.="<div class='sch_oszlopdatacell' {$extraStyle} data-datum='{$this->thisDay}' data-napszak='{$this->napszak}' data-roleid='{$roleId}' data-tipusid='{$tipusData["id"]}'>";
        if (isset($this->workScheduleService->scheduleMapping["{$this->thisDay}_{$this->napszak}_{$tipusData["id"]}"])) {
            $mappings = $this->workScheduleService->scheduleMapping["{$this->thisDay}_{$this->napszak}_{$tipusData["id"]}"];
            foreach ($mappings as $mapping) {
                if ($mapping["roleid"] != $roleId) {
                    continue;
                }
                $html .= "<div class='workerlink'>";
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

}

