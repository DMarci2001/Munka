<?php

class AdminWorkSchedulePage extends AdminCorePage {

    private $bookingService;
    private $workScheduleService;

    public function __construct()
    {
        parent::__construct();

        $this->workScheduleService = new WorkScheduleService();
    }

    public function showPage() {
        if (!$this->adminUtils->helyszinModJog()) {
            return;
        }


        echo "<div style='white-space: nowrap;'>";

        for ($i = 0; $i < 7; $i++) {
            $weekStart = strtotime("this week monday + {$i} day");

            $thisDay = date("Y-m-d", $weekStart);

            echo "<div class='scheduleday'>";
            echo "<div class='scheduledayhead'>{$thisDay}</div>";

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

        echo "<div class='sch_dialog'></div>";

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
            $html .= "[<a onclick='showAddDoctorDialog(this);return false;' href='#'>add</a>]";
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

