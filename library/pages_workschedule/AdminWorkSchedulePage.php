<?php

class AdminWorkSchedulePage extends AdminCorePage {

    private $bookingService;
    private $workScheduleService;
    private $settings;

    public function __construct()
    {
        parent::__construct();

        $this->workScheduleService = new WorkScheduleService();
        $this->settings = new Booking_Settings();

        if (isset($_POST["addworker"])) {
            echo "<div style='display:table-cell;vertical-align: top;padding-right: 10px;'>";
            echo "<select size='6' id='orvosselector' style='width:250px;'>";
            $res = sql_query("select * from schedule_workers where roleid=? order by nev", array($_POST["tipus"]));
            while ($orvosData = sql_fetch_array($res)) {
                echo "<option value='{$orvosData["id"]}'>{$orvosData["nev"]}</option>";
            }
            echo "</select>";
            echo "</div>";

            echo "<div style='display:table-cell;vertical-align: top;'>";
            echo "<select id='doctortol'>";
            echo "<option value='0'>Kezdés?</option>";
            for ($n=0; $n<=1000; $n+=15) {
                $t = date("H:i",mktime(6,0+$n,0,1,1,2015));
                echo "<option value='{$t}'>{$t}</option>";
            }
            echo "</select> - ";

            echo "<select id='doctorig'>";
            echo "<option value='0'>Vége?</option>";
            for ($n=0; $n<=1000; $n+=15) {
                $t = date("H:i",mktime(6,0+$n,0,1,1,2015));
                echo "<option value='{$t}'>{$t}</option>";
            }
            echo "</select> ";

            echo "<div style='padding-top:10px;'><input type='button' name='addtipmegj' value='+ hozzáadás'></div>";

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
            $weekStart = strtotime("this week monday + {$i} day");

            $this->thisDay = date("Y-m-d", $weekStart);
            //$weekDayKey = ;
            $weekDay = $this->settings->hetnap[date("N", $weekStart)];

            echo "<div class='scheduleday'>";
            echo "<div class='scheduledayhead'>{$this->thisDay} {$weekDay}</div>";

            $this->napszak = 0;
            echo "<div class='schedulenapszakhead'>Délelőtt</div>";

            echo "<div style='display:table-row;'>";
            echo "<div class='sch_rendelooszlop'>".$this->_rendeloFejCell()."</div>";
            echo "<div class='sch_orvososzlop'>".$this->_orvosFejCell()."</div>";
            echo "<div class='sch_noveroszlop'>".$this->_noverFejCell()."</div>";
            echo "</div>";

            $resTipus = sql_query("select * from schedule_tipusok order by roleid, sorrend");
            while ($tipusData = sql_fetch_array($resTipus)) {
                echo "<div style='display:table-row;'>";
                echo "<div class='sch_rendelooszlop'>".$this->_rendeloCell($tipusData)."</div>";
                echo "<div class='sch_orvososzlop'>".$this->_orvosCell($tipusData)."</div>";
                echo "<div class='sch_noveroszlop'>".$this->_noverCell($tipusData)."</div>";
                echo "</div>";
            }

            $this->napszak = 1;
            echo "<div class='schedulenapszakhead'>Délután</div>";

            echo "<div style='display:table-row;'>";
            echo "<div class='sch_rendelooszlop'>".$this->_rendeloFejCell()."</div>";
            echo "<div class='sch_orvososzlop'>".$this->_orvosFejCell()."</div>";
            echo "<div class='sch_noveroszlop'>".$this->_noverFejCell()."</div>";
            echo "</div>";

            $resTipus = sql_query("select * from schedule_tipusok order by roleid, sorrend");
            while ($tipusData = sql_fetch_array($resTipus)) {
                echo "<div style='display:table-row;'>";
                echo "<div class='sch_rendelooszlop'>".$this->_rendeloCell($tipusData)."</div>";
                echo "<div class='sch_orvososzlop'>".$this->_orvosCell($tipusData)."</div>";
                echo "<div class='sch_noveroszlop'>".$this->_noverCell($tipusData)."</div>";
                echo "</div>";
            }

            echo "</div>";
        }

        echo "</div>";

        echo "<div id='schdialog' class='sch_dialog'><div class='sch_dialogtop'></div><div class='sch_dialogcontent'></div></div>";

    }

    private function _rendeloFejCell() {
        $html="";
        $html.="<div class='sch_oszlopfejcell'>Rendelő</div>";
        return $html;
    }

    private function _orvosFejCell() {
        $html="";
        $html.="<div class='sch_oszlopfejcell'>Orvos</div>";
        return $html;
    }

    private function _noverFejCell() {
        $html="";
        $html.="<div class='sch_oszlopfejcell'>Nővér</div>";
        return $html;
    }

    private function _rendeloCell($tipusData) {
        $html="";
        $html.="<div class='sch_oszlopdatacell'>{$tipusData["megnev"]}</div>";
        return $html;
    }

    private function _orvosCell($tipusData) {
        $html="";
        $html.="<div class='sch_oszlopdatacell'>";
        if (isset($this->workScheduleService->scheduleMapping["{$this->thisDay}_{$this->napszak}_{$tipusData["id"]}"])) {
            $mappings = $this->workScheduleService->scheduleMapping["{$this->thisDay}_{$this->napszak}_{$tipusData["id"]}"];
            foreach ($mappings as $mapping) {
                $html .= "<div><a data-roleid='{$tipusData["roleid"]}' data-tipusnev='{$tipusData["megnev"]}' onclick='Schedule.ShowAddWorkerDialog(this);return false;' href='#'>{$mapping["workernev"]}</a></div>";
            }
        } else {
            $html .= "[<a data-roleid='{$tipusData["roleid"]}' data-tipusnev='{$tipusData["megnev"]}' onclick='Schedule.ShowAddWorkerDialog(this);return false;' href='#'>add</a>]";
        }

        $html.="</div>";
        return $html;
    }

    private function _noverCell($tipusData) {
        $html="";
        $html.="<div class='sch_oszlopdatacell'>";
        if ($tipusData["roleid"] == 1) {
            $html .= "[<a data-roleid='2' data-tipusnev='{$tipusData["megnev"]} - nővér' onclick='Schedule.ShowAddWorkerDialog(this);return false;' href='#'>add</a>]";
        }
        $html.="</div>";
        return $html;
    }


}

