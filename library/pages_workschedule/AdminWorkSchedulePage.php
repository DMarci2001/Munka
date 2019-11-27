<?php

class AdminWorkSchedulePage extends AdminCorePage {

    private $bookingService;
    private $workScheduleService;
    private $settings;

    private $napszakok = ["Délelőtt", "Délután"];

    public function __construct()
    {
        parent::__construct();

        $this->workScheduleService = new WorkScheduleService();
        $this->settings = new Booking_Settings();


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
                    "datumFrom" => $datumStart,
                    "datumTo"   => $datumEnd,
                    "napszak"   => $_POST["napszak"],
                    "tipusId"   => $_POST["tipusid"],
                    "roleId"    => $_POST["roleid"],
                    "workerId"  => $_POST["workerselector"]
                ];

                if ($_POST["mapid"] == 0) {
                    sql_query("insert into schedule_mapping set datumfrom=:datumFrom, datumto=:datumTo, napszak=:napszak, tipusid=:tipusId, roleid=:roleId, workerid=:workerId", $params);
                } else {
                    $params["id"] = $_POST["mapid"];
                    sql_query("update schedule_mapping set datumfrom=:datumFrom, datumto=:datumTo, napszak=:napszak, tipusid=:tipusId, roleid=:roleId, workerid=:workerId where id=:id", $params);
                }

                $this->workScheduleService->reloadScheduleMapping();
                $result["message"] = $this->_scheduleDay($_POST["datum"]);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["deleteworker"])) {
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
                    "datumFrom" => $datumStart,
                    "datumTo"   => $datumEnd,
                    "napszak"   => $_POST["napszak"],
                    "tipusId"   => $_POST["tipusid"],
                    "roleId"    => $_POST["roleid"],
                    "workerId"  => $sourceData["workerid"]
                ];

                sql_query("insert into schedule_mapping set datumfrom=:datumFrom, datumto=:datumTo, napszak=:napszak, tipusid=:tipusId, roleid=:roleId, workerid=:workerId", $params);
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
            echo "<select id='doctortol' name='workertol'>";
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
            echo "<select id='doctorig' name='workerig'>";
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
            $buttonText = $mapId == 0?"+ hozzáadás":"mentés";
            echo "<input type='button' onclick='Schedule.AddWorker();' value='{$buttonText}'>";
            if ($mapId != 0) {
                echo " <input type='button' onclick='Schedule.DeleteWorker();' value='Törlés'>";
            }
            echo "</div>";
            echo "</div>";
            die;
        }

        $GLOBALS["css"][] = "schedule.css";
        $GLOBALS["javascript"][] = "schedule.js";
    }

    private $thisDay;
    private $napszak;

    public function showPage() {
        if (!$this->adminUtils->helyszinModJog()) {
            return;
        }

        echo "<div style='white-space: nowrap;'>";

        for ($i = 0; $i < 7; $i++) {
            $thisDay = date("Y-m-d", strtotime("this week monday + {$i} day"));
            echo "<div class='scheduleday' id='daycontainer{$thisDay}'>";
            echo $this->_scheduleDay($thisDay);
            echo "</div>";
        }

        echo "</div>";

        echo "<div id='schdialog' class='sch_dialog'><div class='sch_dialogtop'></div><form name='dialogform' id='dialogform' method='post'><div class='sch_dialogcontent'></div></form></div>";

    }

    private function _scheduleDay($thisDay) {
        $this->thisDay = $thisDay;
        $weekDay = date("N", strtotime($thisDay));
        $html = "";

        $html.= "<div class='scheduledayhead'>{$this->thisDay} ".$this->settings->hetnap[$weekDay]."</div>";

        for ($this->napszak = 0; $this->napszak<=1; $this->napszak++) {
            $html .= "<div class='schedulenapszakhead'>".$this->napszakok[$this->napszak]."</div>";

            $html .= "<div style='display:table-row;'>";
            $html .= $this->_rendeloFejCell();
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

    private function _rendeloFejCell() {
        $html="";
        $html.="<div class='sch_oszlopfejcell'>Rendelő</div>";
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
                $workerExists = true;
                $html .= "<div class='workerlink'>";
                $html .= "<a data-mapid='{$mapping["id"]}' data-datum='{$this->thisDay}' data-roleid='{$roleId}' data-tipusid='{$tipusData["id"]}' data-tipusnev='{$tipusName}' data-napszak='{$this->napszak}' onclick='Schedule.ShowAddWorkerDialog(this);return false;' href='#'>";
                $html .= "{$mapping["workernev"]} ";
                $html .= "</a>";
                $html .= $this->_workInterval($mapping);
                $html .= "</div>";
            }
        }

        $html.="</div>";
        return $html;
    }

    private function _workInterval($mapping) {
        $html="";

        $from = date("H:i", strtotime($mapping["datumfrom"]));
        $to   = date("H:i", strtotime($mapping["datumto"]));

        if ($from != "00:00" || $to != "00:00") {
            if ($from != "00:00" && $to == "00:00") {
                $html.="{$from} -";
            } else {
                if ($from == "00:00" && $to != "00:00") {
                    $html .= "- {$to}";
                } else {
                    $html .= "{$from} - {$to}";
                }
            }
        }
        return $html;
    }

}

