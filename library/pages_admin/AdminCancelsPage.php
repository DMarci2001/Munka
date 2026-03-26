<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AdminCancelsPage extends AdminCorePage
{

    private ExcelService $excelService;


    public function __construct()
    {
        parent::__construct();


        if (!isset($_SESSION["cancelmonth"])) {
            $_SESSION["cancelmonth"] =  date("Y-m", strtotime("-1 month"));;
        }

        if (isset($_REQUEST["filtermonth"])) {
            $_SESSION["cancelmonth"] =  $_REQUEST["filtermonth"];;
        }

        if (isset($_GET["download"])) {
            $fileName = Booking_Constants::COMPANY_NAME_SHORT." {$_SESSION["cancelmonth"]} havi lemondott foglalások.xlsx";

            $startTime = date("Y-m-01 00:00:00", strtotime("{$_SESSION["cancelmonth"]}-01"));
            $endTime = date("Y-m-01 00:00:00", strtotime("{$_SESSION["cancelmonth"]}-01 +1 month"));

            $this->excelService = new ExcelService();
            $this->generateExcel($startTime, $endTime);
            $this->excelService->setFileName($fileName);
            $this->excelService->outputSpreadSheetFile(self::getTempFileName());

            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-Disposition: attachment;filename=\"{$fileName}\"");
            header("Cache-Control: max-age=0");

            echo file_get_contents(self::getTempFileName());
            @unlink(self::getTempFileName());


            die();
        }

    }

    public static function getTempFileName():string {
        return Booking_Constants::APP_PATH."library/other/tmp/cancels".session_id().".xlsx";
    }

    private array $categNames = [
        "torolt" => "Törölt",
        "nemeljott" => "Nem eljött",
    ];

    public function showPage() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        //if (!$this->adminUtils->szurestipusModJog()) {
        //    return;
        //}


        echo "<h1>Lemondott foglalások</h1>";

        echo "<div style='padding:5px;background-color:#eee;margin-bottom:10px;'>";
        echo "<form name='f1' method='post' style='padding:0px;margin:0px;'>";
        echo "<table><tr>";

        echo "<td><select name='filtermonth' onchange='document.f1.submit();'>";

        for ($i=0; $i<24; $i++) {
            $month = date("Y-m", strtotime("-{$i} month"));;
            echo "<option value='{$month}'".($month==$_SESSION["cancelmonth"]?" selected":"").">{$month}</option>";
        }
        echo "</select>&nbsp;</td>";

        echo "<td><input type='submit' name='frissit' style='' value='Frissítés'/>&nbsp;</td>";

        echo "</tr></table>";
        echo "</form>";
        echo "</div>";


        $startTime = date("Y-m-01 00:00:00", strtotime("{$_SESSION["cancelmonth"]}-01"));
        $endTime = date("Y-m-01 00:00:00", strtotime("{$_SESSION["cancelmonth"]}-01 +1 month"));

        $cancelLogs = $this->cancelList($startTime, $endTime);


        echo "<div style='margin-bottom:10px;'><a target='_blank' href='index.php?page={$_GET["page"]}&download'>Letöltés</a></div>";
        echo "<div style='margin-bottom:10px;'>".count($cancelLogs)." sor</div>";


        echo "<table cellpadding='0' cellspacing='0' border='0'>";


        echo "<tr style='background:#eee;'>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 5px;'>Kategória</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 5px;'>Törlés ideje</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Időpont</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Különbség (óra)</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Cég</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Szöveg</div></td>";
        echo "</tr>";

        foreach ($cancelLogs as $cancelData) {
            $tc = "tcella";
            if (!isset($first)) {
                echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            echo "<tr>";

            echo "<td nowrap valign='top'><div class='{$tc}'>{$this->categNames[$cancelData["categ"]]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$cancelData["datum"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$cancelData["reservationdatum"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$cancelData["diffHour"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$cancelData["cegnev"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$cancelData["deletemegj"]}</div></td>";

            echo "</tr>";

            echo "<tr><td colspan='18' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }

        echo "</table>";

        error_reporting(0);
    }


    public function cancelList($startTime, $endTime):array {
        $list =  [];
        $cancelLogs = sql_query("select * from activitylog l where tipus='foglalastorles' and !instr(megnev, 'nincs név') and !instr(megnev, 'egyeztet') and !instr(megnev, 'teszt') and datum>? and datum<?", [$startTime, $endTime])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cancelLogs as $cancelData) {
            $reservationData = json_decode($cancelData["query"], JSON_OBJECT_AS_ARRAY);

            $diffHour = round((strtotime($reservationData["datum"]) - strtotime($cancelData["datum"]))/3600);

            if ($diffHour > 48) {
                continue;
            }

            if (!$cegData = sql_query("select megnev from cegek where id=?", [$reservationData["cegid"]])->fetch(PDO::FETCH_ASSOC)) {
                $cegData["megnev"] = "";
            }

            $list[$cancelData["datum"]] = [
                "categ" => "torolt",
                "datum" => $cancelData["datum"],
                "reservationdatum" => $cancelData["datum"],
                "diffHour" => $diffHour,
                "cegnev" => $cegData["megnev"],
                "deletemegj" => $cancelData["megnev"],
            ];
        }

        $notArrivedList = sql_query("select t.megnev AS tipus, c.megnev AS cegnev, f.* FROM foglalasok f
            LEFT JOIN cegek c ON c.id=f.cegid
            LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
            WHERE datum>? AND datum<? AND eljott=0 AND nev<>'nincs név'", [$startTime, $endTime])->fetchAll(PDO::FETCH_ASSOC);


        foreach ($notArrivedList as $cancelData) {
            $diffHour = 0;

            $list[$cancelData["datum"]] = [
                "categ" => "nemeljott",
                "datum" => $cancelData["datum"],
                "reservationdatum" => $cancelData["datum"],
                "diffHour" => $diffHour,
                "cegnev" => $cancelData["cegnev"],
                "deletemegj" => $cancelData["megj"],
            ];
        }

        ksort($list, SORT_STRING);

        return $list;
    }


    public function generateExcel($startTime, $endTime) {
        $this->excelService->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        $sheetId = 0;
        try {
            //$this->_orvosWorkHours($sheetId++, $from, $to);
            $this->_cancelListTag($sheetId++, $startTime, $endTime);
        } catch (\Exception $e) {
            //valami hibakezelés...
        }

        $this->excelService->spreadSheet->setActiveSheetIndex(0);
    }

    private function _cancelListTag($sheetId, $startTime, $endTime) {
        if ($sheetId != 0) {
            $this->excelService->spreadSheet->createSheet();
            $this->excelService->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->excelService->sheet = $this->excelService->spreadSheet->getActiveSheet();
        $this->excelService->sheet->setTitle("Lemondottak");
        $this->excelService->titleRow("A1", "Lemondott és el nem jött foglalások - ".date("Y-m-d", strtotime($startTime))." - ".date("Y-m-d", strtotime("{$endTime} -1 day"))." (forrás: bejelentkező)");

        $sor = 4;

        $this->excelService->headingRow("A", $sor, ["Kategória", "Törlés ideje", "Időpont", "Különbség (óra)", "Cég", "Szöveg"]);
        $sor++;

        $cancelLogs = $this->cancelList($startTime, $endTime);

        foreach ($cancelLogs as $cancelData) {
            $this->excelService->dataRow("A", $sor, [$this->categNames[$cancelData["categ"]], $cancelData["datum"], $cancelData["reservationdatum"], $cancelData["diffHour"], $cancelData["cegnev"], $cancelData["deletemegj"]]);
            $this->excelService->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
            $sor++;
        }
        $sor++;


        $this->excelService->setAutoWidth(range('B','L'));
        $this->excelService->sheet->getColumnDimension('A')->setWidth(20);
    }

}