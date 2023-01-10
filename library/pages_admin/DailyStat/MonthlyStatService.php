<?php

class MonthlyStatService {
    private $months = ["","január","február","március","április","május","június","július","augusztus","szeptember","október","november","december"];
    private $weekDays = ["","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap"];
    private $weekDaysShort = ["","h","k","sz","cs","p","sz","v"];
    private $excelService;

    public function __construct() {
        $utils = new Utils();
        $this->excelService = new ExcelService();

        if (!isset($_SESSION["monthlystatoffset"])) {
            $_SESSION["monthlystatoffset"] = 0;
        }


        if (isset($_GET["moveyear"])) {
            $_SESSION["monthlystatoffset"] += intval($_GET["moveyear"]);
            echo $this->displayCalendar($_SESSION["monthlystatoffset"]);
            die;
        }

        if (isset($_REQUEST["downloadmonthlystat2"])) {
            //$day = $_POST["day"];
            $result["error"] = "Fejlesztés alatt...";
            $utils->jsonOut($result);
        }

        if (isset($_REQUEST["downloadMonthlyStat"])) {
            $result = $this->combinedStat($_GET["year"], $_GET["month"]);

            if ($_GET["debug"] == 1) {
                $result["debug"] = "<pre>" . print_r($result["raw"], true) . "</pre>";
                unset($result["raw"]);
            }
            die;
        }

        if (isset($_REQUEST["downloadRontgenList"])) {
            $result = $this->rontgenList($_GET["year"], $_GET["month"]);

            if ($_GET["debug"] == 1) {
                $result["debug"] = "<pre>" . print_r($result["raw"], true) . "</pre>";
                unset($result["raw"]);
            }
            die;
        }
    }

    private function displayCalendarMonthBox($year, $month):string {
        $html = "";

        $napClass = "calday";

        $boxStyle = ($month == intval(date("m", strtotime("now"))) ? "background:#f0e0e0" : "");

        $html.= "<div class='yearlycell' style='{$boxStyle}'>";
        $html.= "<div style='text-align: center;font-weight: bold;font-size: 16px;'><div title='' class='{$napClass}'>{$year} ".$this->months[$month]."</div></div>";

        if (strtotime("now") > strtotime("{$year}-{$month}-01")) {
            $html .= "<div data-month='{$month}' id='monthtext{$month}' style='padding-top:0px;'>";

            //$dayData = $this->getDayData($day);

            $html .= "<div><img id='statloader{$month}' style='display:none;opacity:.5;height:30px;margin-top:10px;' src='/images/loading_transparent.svg' /></div>";

            $html .= "<div id='datablock{$month}' style='margin-top:10px;'>";
            $html .= "<div><a href='index.php?page={$_GET["page"]}&downloadMonthlyStat=1&year={$year}&month={$month}'>Havi statisztika letöltése</a></div>";
            $html .= "<div><a href='index.php?page={$_GET["page"]}&downloadRontgenList=1&year={$year}&month={$month}'>Röntgen lista</a></div>";
            $html .= "</div>";

            $html .= "</div>";
        }
        $html.= "</div>";

        return $html;
    }

    public function displayCalendar($offset):string {
        $html         = "";
        $now          = date("Y-m-01");
        $year         = date("Y",strtotime("{$now} +{$offset} year"));

        $html.= "<table class='montlytable' style='margin:0px;padding:0px;'>";
        $html.= "<tr>";
        $html.= "<td colspan='1' class='montlycell mthead' style='text-align: left;'><a href='#' onclick='DailyStatMoveYear(-1);return false;'><i class='fas fa-chevron-circle-left'></i></a></td>";
        $html.= "<td colspan='1' class='montlycell mthead'>&nbsp;&nbsp;{$year}</td>";
        $html.= "<td colspan='1' class='montlycell mthead' style='text-align: right;'><a href='#' onclick='DailyStatMoveYear(1);return false;'><i class='fas fa-chevron-circle-right'></i></a></td>";
        $html.= "</tr>";


        $month = 1;
        for ($column = 0; $column < 4; $column++) {
            $html .= "<tr>";
            for ($row = 0; $row < 3; $row++) {
                $html.= "<td id='monthbox{$month}'>";
                $html.= $this->displayCalendarMonthBox($year, $month);
                $html.= "</td>";

                $month++;
            }
            $html .= "</tr>";
        }

        $html.= "</table>";
        return $html;
    }


    private function combinedStat($year, $month):array {
        $result["error"] = "";

        $startDate = date("Y-m-d 00:00:00", strtotime("{$year}-{$month}-01"));
        $endDate   = date("Y-m-t 23:59:59", strtotime("{$year}-{$month}-01"));

        $result["raw"]["interval"] = [$startDate, $endDate];

        $result["raw"]["companystat"] = sql_query("SELECT c.megnev AS ceg, COUNT(*) AS foglalasok, SUM(IF(f.eljott=1, 1, 0)) AS eljott FROM foglalasok f
            LEFT JOIN cegek c ON c.id=f.cegid
            WHERE f.aktiv=1 AND f.nev NOT IN ('nincs név', 'ne foglalj', 'ebéd', 'ebédszünet')
            AND datum>? AND datum<=? AND f.`externalid`=''
            GROUP BY c.id, megnev ORDER BY c.megnev", [$startDate, $endDate])->fetchAll(PDO::FETCH_ASSOC);

        $result["raw"]["doctorstat"] = sql_query("SELECT o.nev AS orvos, COUNT(*) AS foglalasok, SUM(IF(eljott=1, 1, 0)) AS eljott FROM foglalasok f
            LEFT JOIN orvosok o ON o.id=f.orvosassigned
            WHERE f.aktiv=1 AND f.nev NOT IN ('nincs név', 'ne foglalj', 'ebéd', 'ebédszünet')
            AND datum>=? AND datum<=? AND f.`externalid`=''
            GROUP BY (IF (o.parentoid<>0, o.parentoid, o.id)) ORDER BY o.nev", [$startDate, $endDate])->fetchAll(PDO::FETCH_ASSOC);


        $this->excelService->combinedStat($result["raw"]);
        $this->excelService->setFileName("Bejelentkezo_havi_statisztika_" . date("Y-m", strtotime("{$year}-{$month}-01")) . ".xlsx");
        $this->excelService->outputSpreadSheet();

        return $result;
    }

    private function rontgenList($year, $month):array {
        $result["error"] = "";

        $startDate = date("Y-m-d 00:00:00", strtotime("{$year}-{$month}-01"));
        $endDate   = date("Y-m-t 23:59:59", strtotime("{$year}-{$month}-01"));

        $institutionNames = DicomService::getInstitutesQuery();
        $result["raw"]["interval"] = [$startDate, $endDate];
        $result["raw"]["list"] = sql_query_common("select d.*, count(*) as db from dicom d where d.contentDate>? AND d.contentDate<=? and d.institutionName in ({$institutionNames}) GROUP BY d.patientName, d.patientBirthDate ORDER BY d.contentDate", [$startDate, $endDate])->fetchAll(PDO::FETCH_ASSOC);

        $this->excelService->rtgList($result["raw"]);
        $this->excelService->setFileName("RTG_lista_" . date("Y-m", strtotime("{$year}-{$month}-01")) . ".xlsx");
        $this->excelService->outputSpreadSheet();

        return $result;
    }



}