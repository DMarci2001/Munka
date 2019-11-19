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

        if (isset($_POST["adddoctors"])) {
            echo "<div style='display:table-cell;vertical-align: top;padding-right: 10px;'>";
            echo "<select size='6' id='orvosselector' style='width:250px;'>";
            $res = sql_query("select * from orvosok order by nev");
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

            echo "<div style='padding-top:10px;'><input type='button' name='addtipmegj' value='+ orvos hozzáadása'></div>";

            echo "</div>";


            die;
        }

        $GLOBALS["css"][] = "schedule.css";
        $GLOBALS["javascript"][] = "schedule.js";
    }

    public function showPage() {
        if (!$this->adminUtils->helyszinModJog()) {
            return;
        }

        echo "<div style='white-space: nowrap;'>";

        for ($i = 0; $i < 7; $i++) {
            $weekStart = strtotime("this week monday + {$i} day");

            $thisDay = date("Y-m-d", $weekStart);
            //$weekDayKey = ;
            $weekDay = $this->settings->hetnap[date("N", $weekStart)];

            echo "<div class='scheduleday'>";
            echo "<div class='scheduledayhead'>{$thisDay} {$weekDay}</div>";
            echo "<div class='schedulenapszakhead'>Délelőtt</div>";

            echo "<div style='display:table-row;'>";
            echo "<div class='sch_rendelooszlop'>".$this->_rendeloFejCell()."</div>";
            echo "<div class='sch_orvososzlop'>".$this->_orvosFejCell()."</div>";
            echo "<div class='sch_noveroszlop'>".$this->_noverFejCell()."</div>";
            echo "</div>";

            $resTipus = sql_query("select * from schedule_tipusok order by sorrend");
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

        echo "<div id='schdialog' class='sch_dialog'><div class='sch_dialogtop' onmousedown=\"mydragg.startMoving(this,'schdialog',event);\" onmouseup=\"mydragg.stopMoving('schdialog');\"><div id='dialogclose' style='width:20px;height:20px;float:right;'></div></div><div class='sch_dialogcontent'></div></div>";

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

        if (true) {
            $html .= "[<a onclick='Schedule.ShowAddDoctorDialog(this);return false;' href='#'>add</a>]";
        }

        $html.="</div>";
        return $html;
    }

    private function _noverCell($tipusData) {
        $html="";
        $html.="<div class='sch_oszlopdatacell'>Nővér</div>";
        return $html;
    }


}

