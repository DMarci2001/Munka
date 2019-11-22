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

                sql_query("insert into schedule_mapping set datumfrom=:datumFrom, datumto=:datumTo, napszak=:napszak, tipusid=:tipusId, roleid=:roleId, workerid=:workerId", $params);

                $this->workScheduleService->reloadScheduleMapping();
                $result["message"] = $this->_scheduleDay($_POST["datum"]);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["addworkerdialog"])) {
            echo "<div style='display:table-cell;vertical-align: top;padding-right: 10px;'>";
            echo "<input type='hidden' name='napszak' value='{$_POST["napszak"]}' />";
            echo "<input type='hidden' name='roleid' value='{$_POST["roleid"]}' />";
            echo "<input type='hidden' name='datum' value='{$_POST["datum"]}' />";
            echo "<input type='hidden' name='tipusid' value='{$_POST["tipusid"]}' />";
            echo "<select size='6' name='workerselector' id='workerselector' style='width:250px;'>";
            $res = sql_query("select * from schedule_workers where roleid=? order by nev", array($_POST["roleid"]));
            while ($orvosData = sql_fetch_array($res)) {
                echo "<option value='{$orvosData["id"]}'>{$orvosData["nev"]}</option>";
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
                $t = date("H:i",mktime($startHour,$n,0,1,1,2015));
                $hour = date("H",mktime($startHour,$n,0,1,1,2015));
                echo "<option value='{$t}'>{$t}</option>";
                $n+=15;
            }
            echo "</select> - ";

            $hour = $n = 0;
            echo "<select id='doctorig' name='workerig'>";
            echo "<option value='0'>Vége?</option>";
            while ($hour<23) {
                $t = date("H:i",mktime($startHour,$n,0,1,1,2015));
                $hour = date("H",mktime($startHour,$n,0,1,1,2015));
                echo "<option value='{$t}'>{$t}</option>";
                $n+=15;
            }
            echo "</select> ";

            echo "<div style='padding-top:10px;'><input type='button' onclick='Schedule.Addworker();' value='+ hozzáadás'></div>";
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

        $html.= "<div class='scheduledayhead'>{$this->thisDay} {$weekDay}</div>";

        for ($this->napszak = 0; $this->napszak<=1; $this->napszak++) {
            $html .= "<div class='schedulenapszakhead'>".$this->napszakok[$this->napszak]."</div>";

            $html .= "<div style='display:table-row;'>";
            $html .= "<div class='sch_rendelooszlop'>" . $this->_rendeloFejCell() . "</div>";
            $html .= "<div class='sch_orvososzlop'>" . $this->_workerFejCell("Orvos") . "</div>";
            $html .= "<div class='sch_noveroszlop'>" . $this->_workerFejCell("Nővér") . "</div>";
            $html .= "</div>";

            $resTipus = sql_query("select * from schedule_tipusok order by roleid, sorrend");
            while ($tipusData = sql_fetch_array($resTipus)) {
                $html .= "<div style='display:table-row;'>";
                $html .= "<div class='sch_rendelooszlop'>" . $this->_rendeloCell($tipusData) . "</div>";
                $html .= "<div class='sch_orvososzlop'>" . $this->_workerCell($tipusData) . "</div>";
                $html .= "<div class='sch_noveroszlop'>" . $this->_workerCell($tipusData, 2) . "</div>";
                $html .= "</div>";
            }
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
        $html.="<div class='sch_oszlopfejcell'>{$title}</div>";
        return $html;
    }

    private function _rendeloCell($tipusData) {
        $html="";
        $html.="<div class='sch_oszlopdatacell'>{$tipusData["megnev"]}</div>";
        return $html;
    }

    private function _workerCell($tipusData, $roleFilter = 0) {
        $roleId = $tipusData["roleid"];
        $tipusName = $tipusData["megnev"];
        if ($roleFilter != 0) {
            $roleId = $roleFilter;
        }
        if ($roleId == 2) {
            $tipusName.=" - nővér";
        }
        $workerExists = false;

        $html="";
        $html.="<div class='sch_oszlopdatacell'>";
        if (isset($this->workScheduleService->scheduleMapping["{$this->thisDay}_{$this->napszak}_{$tipusData["id"]}"])) {
            $mappings = $this->workScheduleService->scheduleMapping["{$this->thisDay}_{$this->napszak}_{$tipusData["id"]}"];
            foreach ($mappings as $mapping) {
                if ($mapping["roleid"] != $roleId) {
                    continue;
                }
                $workerExists = true;
                $html .= "<div><a data-datum='{$this->thisDay}' data-roleid='{$roleId}' data-tipusid='{$tipusData["id"]}' data-tipusnev='{$tipusName}' data-napszak='{$this->napszak}' onclick='Schedule.ShowAddWorkerDialog(this);return false;' href='#'>{$mapping["workernev"]}</a></div>";
            }
        }

        if (!$workerExists) {
            $html .= "[<a data-datum='{$this->thisDay}' data-roleid='{$roleId}' data-tipusid='{$tipusData["id"]}' data-tipusnev='{$tipusName}' data-napszak='{$this->napszak}' onclick='Schedule.ShowAddWorkerDialog(this);return false;' href='#'>add</a>]";
        }

        $html.="</div>";
        return $html;
    }

}

