<?php

class DailyStatService {
    private $months = ["","január","február","március","április","május","június","július","augusztus","szeptember","október","november","december"];
    private $weekDays = ["","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap"];
    private $weekDaysShort = ["","h","k","sz","cs","p","sz","v"];

    public function __construct() {

        $utils = new Utils();

        if (isset($_POST["generatedailystat"])) {
            $day = $_POST["day"];
            $this->resetDay($day);
            $this->dokirexQuery($day);
            $this->beosztasQuery($day);

            $result["html"] = $this->displayCalendarDayBox($day);
            $utils->jsonOut($result);
        }
    }

    private function displayCalendarDayBox($day):string {
        $html = "";

        $napClass = "calday";
        $diff = (strtotime($day) - strtotime(date("Y-m-d"))) / (3600*24);

        $boxStyle = ($diff == 0?"background:#f0e0e0":"");

        $html.= "<div class='monthlycell' style='{$boxStyle}'>";
        $html.= "<div style='text-align: center;font-weight: bold;font-size: 16px;'><div title='' onclick='alert(\"{$day}\");' class='{$napClass}'>".date("d", strtotime($day))."</div></div>";

        $html.= "<div data-day='{$day}' id='daytext{$day}' style='padding-top:10px;'>";

        if ($status = $this->getDayStatus($day)) {
            if (isset($status["dokirex"]) && $status["dokirex"] == "ok" && isset($status["beosztas"]) && $status["beosztas"] == "ok") {
                $html.= "napi stat kész";
            }
        } else {
            $html.= "<a href='#' onclick='generateDailyStat(this);return false;'>Generálás</a>";
        }

        $html.= "</div>";
        $html.= "</div>";

        return $html;
    }

    public function displayCalendar($offset):string {
        $html         = "";
        $now          = date("Y-m-01");
        $year         = date("Y",strtotime("{$now} + {$offset} month"));
        $month        = intval(date("n",strtotime("{$now} + {$offset} month")));
        $monthText    = date("F",strtotime("{$now} + {$offset} month"));
        $numberOfDays = date("t",strtotime("{$now} + {$offset} month"));
        $firstDay     = date("N",strtotime("first day of {$year} {$monthText}"));
        $weekDay      = 0;

        $html.= "<table class='montlytable' style='margin:0px;padding:0px;'>";
        $html.= "<tr><td colspan='7' class='montlycell mthead'>{$year} ".$this->months[$month]."</td></tr>";
        $html.= "<tr>";
        for ($i = 1; $i <= 7 ;$i++) {
            $html.= "<td class='monthlycell mtweekday'>".$this->weekDays[$i]."</td>";
        }
        $html.= "</tr><tr>";

        for ($i = 1; $i < $firstDay ;$i++) {
            $weekDay++;
            $html.= "<td class='monthlycell'>&nbsp;</td>";
        }

        for ($nap = 1; $nap <= $numberOfDays; $nap++) {
            if ($weekDay++ >= 7) {
                $html.= "</tr><tr>";
                $weekDay = 1;
            }

            $thisDay = "{$year}-".substr("00{$month}",-2)."-".substr("00{$nap}",-2);

            $html.= "<td id='daybox{$thisDay}'>";
            $html.= $this->displayCalendarDayBox($thisDay);
            $html.= "</td>";
        }

        $html.= "</tr>";
        $html.= "</table>";
        return $html;
    }

    private function resetDay($day) {
        $this->deleteDay($day);
        sql_query("insert dailystat set day=?", [$day]);
    }

    private function deleteDay($day) {
        sql_query("delete from dailystat where day=?", [$day]);
    }

    private function dokirexQuery($day) {
        $status = $this->getDayStatus($day);

        sleep(1);

        $status["dokirex"] = "ok";
        $this->setDayStatus($day, $status);
    }

    private function beosztasQuery($day) {
        $status = $this->getDayStatus($day);

        sleep(1);

        $status["beosztas"] = "ok";
        $this->setDayStatus($day, $status);
    }

    private function getDayStatus($day) {
        $status  = [];
        if ($dayData = sql_query("select * from dailystat where day=?", [$day])->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($dayData["status"])) {
                $status = json_decode($dayData["status"], JSON_OBJECT_AS_ARRAY);
            }
        }
        return $status;
    }

    private function setDayStatus($day, $status) {
        sql_query("update dailystat set status=? where day=?", [json_encode($status, JSON_PRETTY_PRINT), $day]);
    }

}