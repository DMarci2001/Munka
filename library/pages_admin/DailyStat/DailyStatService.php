<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DailyStatService {
    private array $months = ["","január","február","március","április","május","június","július","augusztus","szeptember","október","november","december"];
    private array $weekDays = ["","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap"];
    private array $weekDaysShort = ["","h","k","sz","cs","p","sz","v"];

    public function __construct() {
        $utils = new Utils();

        if (!isset($_SESSION["dailystatoffset"])) {
            $_SESSION["dailystatoffset"] = 0;
        }

        if (isset($_REQUEST["downloaddailystat"])) {
            //error_reporting(E_ALL);
            //ini_set('display_errors', 1);

            $from = $_REQUEST["dayFrom"];
            $to = $_REQUEST["dayTo"];

            $fileName = Booking_Constants::COMPANY_NAME_SHORT." napi statisztika " . date("Y-m-d", strtotime($from)) . ".xlsx";
            if ($from != $to) {
                $fileName = Booking_Constants::COMPANY_NAME_SHORT." statisztika " . date("Y-m-d", strtotime($from)) . " - " . date("Y-m-d", strtotime($to)) . ".xlsx";
            }

            @unlink(self::getTempFileName());
            $excelService = new ExcelService();
            $excelService->napiStat($from, $to);
            $excelService->setFileName($fileName);
            $excelService->outputSpreadSheetFile(self::getTempFileName());

            $utils->jsonOut(["debughtml" => "", "error" => ""]);
        }

        if (isset($_REQUEST["downloaddailystatfile"])) {
            //error_reporting(E_ALL);
            //ini_set('display_errors', 1);

            $from = $_REQUEST["downloaddailystatfile"];
            $to = $_REQUEST["dayTo"];

            $fileName = Booking_Constants::COMPANY_NAME_SHORT." napi statisztika " . date("Y-m-d", strtotime($from)) . ".xlsx";
            if ($from != $to) {
                $fileName = Booking_Constants::COMPANY_NAME_SHORT." statisztika " . date("Y-m-d", strtotime($from)) . " - " . date("Y-m-d", strtotime($to)) . ".xlsx";
            }

            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-Disposition: attachment;filename=\"{$fileName}\"");
            header("Cache-Control: max-age=0");

            echo file_get_contents(self::getTempFileName());
            die;
        }

        if (isset($_REQUEST["downloadelojegyzestable"])) {
            $from = $_REQUEST["downloadelojegyzestable"];
            $to = $_REQUEST["dayTo"];

            $fileName = Booking_Constants::COMPANY_NAME_SHORT." előjegyzés táblázat " . date("Y-m-d", strtotime($from)) . ".xlsx";
            if ($from != $to) {
                $fileName = Booking_Constants::COMPANY_NAME_SHORT." előjegyzés táblázat " . date("Y-m-d", strtotime($from)) . " - " . date("Y-m-d", strtotime($to)) . ".xlsx";
            }

            $excelService = new ExcelService();
            $excelService->elojegyzesTable($from, $to);
            $excelService->setFileName($fileName);
            $excelService->outputSpreadSheet();
        }

        if (isset($_REQUEST["getdailystateditor"])) {
            $day = $_POST["day"];
            $result["error"] = "";
            $result["html"] = $this->dailyStatEditor($day);
            $utils->jsonOut($result);
        }

        if (isset($_REQUEST["adddailystatfiles"])) {
            $return = ["error" => ""];
            $day = $_REQUEST["day"];

            foreach ($_FILES as $file) {
                $result = $this->processUploadedFile($file);
                if (!empty($result)) {
                    $return["error"] = $result;
                    break;
                }
            }

            $return["html"] = $this->displayCalendarDayBox($day);
            $utils->jsonOut($return);
        }

        if (isset($_GET["movemonth"])) {
            $_SESSION["dailystatoffset"] += intval($_GET["movemonth"]);
            echo $this->displayCalendar($_SESSION["dailystatoffset"]);
            die;
        }

    }


    public function processFileCsv($tempFile):string {
        $content = file_get_contents($tempFile);
        $rows = explode("\n", $content);

        $firstRow = explode(",", $rows[0]);

        if ($firstRow[1] == "PaciensVizsgalat_UtolsoModositasDatuma") {
            foreach ($rows as $key => $row) {
                if ($key == 0) {
                    continue;
                }
                $data = explode(",", $row);
                $ervenyesseg = $data[11];
                if (empty($ervenyesseg)) {
                    $ervenyesseg = "2000-01-01";
                }

                $params = [
                    "datum" => date("Y-m-d H:i:s", strtotime($data[1])),
                    "nev" => trim($data[2], '"'),
                    "szakrendeles" => trim($data[3], '"'),
                    "orvos" => trim($data[4], '"'),
                    "paciensid" => trim($data[5], '"'),
                    "szuldatum" => date("Y-m-d", strtotime($data[6])),
                    "telephely" => trim($data[7], '"'),
                    "munkakor" => trim($data[8], '"'),
                    "korlatozas" => trim($data[9], '"'),
                    "alkalmassag" => $data[10],
                    "ervenyesseg" => $ervenyesseg
                ];

                print_r($params);die;
            }
        }

        die;

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($tempFile);

        //zeusz vizsgálatok + dokirex excel
        $sheet = $spreadsheet->getSheet(0);
        $days = [];

        if (!empty($sheet)) {
            $testCell = $sheet->getCell("A1")->getValue();
            $testCellKieg = $sheet->getCell("H1")->getValue();
            if ($testCell == "Vizsgalat/UtolsoModositasDatuma" || $testCell == "Vizsgalat/FelvetelDatuma") {
                $rowNr = 2;
                if ($testCellKieg == "Egyedi/Tüdőszűrő helyszíne") {
                    //kiegészítő vizsgálatos tábla
                    while (true) {
                        $datum = $sheet->getCell("A{$rowNr}")->getFormattedValue();
                        $modDatum = $sheet->getCell("P{$rowNr}")->getFormattedValue();
                        if (empty($datum)) {
                            break;
                        }

                        $datum = date("Y-m-d H:i:s", strtotime(str_replace(".", "-", $datum)));
                        $modDatum = date("Y-m-d H:i:s", strtotime(str_replace(".", "-", $modDatum)));
                        $nev = $sheet->getCell("B{$rowNr}")->getValue();

                        $row = [
                            "tudoszuro" => trim($sheet->getCell("H{$rowNr}")->getValue()) == "1117 Budapest, Fehérvári út 44." ? 1:0,
                            "ekg" => trim($sheet->getCell("J{$rowNr}")->getValue()) == "Nem" ? 0:1,
                            "hallasvizsgalat" => trim($sheet->getCell("K{$rowNr}")->getValue()) == "Nem" ? 0:1,
                            "labor" => trim($sheet->getCell("N{$rowNr}")->getValue()) == "" ? 0:1,
                            "datum" => $datum,
                            "moddatum" => $modDatum,
                            "nev" => $nev
                        ];

                        sql_query("update dokirex_vizsgalatok set tudoszuro=:tudoszuro, ekg=:ekg, hallasvizsgalat=:hallasvizsgalat, labor=:labor, datum=:datum, updated=1 where moddatum=:moddatum and nev=:nev", $row);

                        $rowNr++;
                    }
                } else {
                    //$result = [];
                    while (true) {
                        $datum = str_replace(".", "-", $sheet->getCell("A{$rowNr}")->getFormattedValue());

                        if (empty($datum)) {
                            break;
                        }

                        $ervenyesseg = str_replace(".", "-", $sheet->getCell("K{$rowNr}")->getFormattedValue());
                        if (empty($ervenyesseg)) {
                            $ervenyesseg = "2000-01-01";
                        }

                        $row = [
                            "datum" => date("Y-m-d H:i:s", strtotime($datum)),
                            "nev" => $sheet->getCell("B{$rowNr}")->getValue(),
                            "szakrendeles" => $sheet->getCell("C{$rowNr}")->getValue(),
                            "orvos" => $sheet->getCell("D{$rowNr}")->getValue(),
                            "paciensid" => $sheet->getCell("E{$rowNr}")->getValue(),
                            "szuldatum" => $sheet->getCell("F{$rowNr}")->getValue(),
                            "telephely" => $sheet->getCell("G{$rowNr}")->getValue(),
                            "munkakor" => $sheet->getCell("H{$rowNr}")->getValue(),
                            "korlatozas" => $sheet->getCell("I{$rowNr}")->getValue(),
                            "alkalmassag" => $sheet->getCell("J{$rowNr}")->getValue(),
                            "ervenyesseg" => $ervenyesseg
                        ];

                        sql_query("delete from dokirex_vizsgalatok where datum=? and orvos=?", [$row["datum"], $row["orvos"]]);
                        sql_query("insert into dokirex_vizsgalatok set 
                                    datum=:datum, moddatum=:datum, nev=:nev,
                                    szakrendeles=:szakrendeles, orvos=:orvos,
                                    paciensid=:paciensid,szuldatum=:szuldatum, telephely=:telephely, munkakor=:munkakor, 
                                    korlatozas=:korlatozas, alkalmassag=:alkalmassag, ervenyesseg=:ervenyesseg", $row);

                        $rowNr++;
                    }
                }
            }
            return "";
        }

        return "A feltöltött file-t nem sikerült beazonosítani";
    }


    public function processFileXls($tempFile):string {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($tempFile);

        //zeusz vizsgálatok + dokirex excel
        $sheet = $spreadsheet->getSheet(0);
        $days = [];

        if (!empty($sheet)) {
            $testCell = $sheet->getCell("A1")->getValue();
            $testCellKieg = $sheet->getCell("H1")->getValue();
            if ($testCell == "Workday ID") {
                $this->processJenbacherExcel($sheet);
                return "";
            }

            if ($testCell == "Vizsgalat/UtolsoModositasDatuma" || $testCell == "Vizsgalat/FelvetelDatuma") {
                $rowNr = 2;
                if ($testCellKieg == "Egyedi/Tüdőszűrő helyszíne") {
                    //kiegészítő vizsgálatos tábla
                    while (true) {
                        $datum = $sheet->getCell("A{$rowNr}")->getFormattedValue();
                        $modDatum = $sheet->getCell("P{$rowNr}")->getFormattedValue();
                        if (empty($datum)) {
                            break;
                        }

                        $datum = date("Y-m-d H:i:s", strtotime(str_replace(".", "-", $datum)));
                        $modDatum = date("Y-m-d H:i:s", strtotime(str_replace(".", "-", $modDatum)));
                        $nev = $sheet->getCell("B{$rowNr}")->getValue();

                        $row = [
                            "tudoszuro" => trim($sheet->getCell("H{$rowNr}")->getValue()) == "1117 Budapest, Fehérvári út 44." ? 1:0,
                            "ekg" => trim($sheet->getCell("J{$rowNr}")->getValue()) == "Nem" ? 0:1,
                            "hallasvizsgalat" => trim($sheet->getCell("K{$rowNr}")->getValue()) == "Nem" ? 0:1,
                            "labor" => trim($sheet->getCell("N{$rowNr}")->getValue()) == "" ? 0:1,
                            "datum" => $datum,
                            "moddatum" => $modDatum,
                            "nev" => $nev
                        ];

                        sql_query("update dokirex_vizsgalatok set tudoszuro=:tudoszuro, ekg=:ekg, hallasvizsgalat=:hallasvizsgalat, labor=:labor, datum=:datum, updated=1 where moddatum=:moddatum and nev=:nev", $row);

                        $rowNr++;
                    }
                } else {
                    //$result = [];
                    while (true) {
                        $datum = str_replace(".", "-", $sheet->getCell("A{$rowNr}")->getFormattedValue());

                        if (empty($datum)) {
                            break;
                        }

                        $ervenyesseg = str_replace(".", "-", $sheet->getCell("K{$rowNr}")->getFormattedValue());
                        if (empty($ervenyesseg)) {
                            $ervenyesseg = "2000-01-01";
                        }

                        $vizsgalatDatum = str_replace(".", "-", $sheet->getCell("M{$rowNr}")->getFormattedValue());
                        if (empty($vizsgalatDatum)) {
                            $vizsgalatDatum = "0000-00-00";
                        }

                        $row = [
                            "datum" => date("Y-m-d H:i:s", strtotime($datum)),
                            "nev" => $sheet->getCell("B{$rowNr}")->getValue(),
                            "szakrendeles" => $sheet->getCell("C{$rowNr}")->getValue(),
                            "orvos" => $sheet->getCell("D{$rowNr}")->getValue(),
                            "paciensid" => $sheet->getCell("E{$rowNr}")->getValue(),
                            "szuldatum" => $sheet->getCell("F{$rowNr}")->getValue(),
                            "telephely" => $sheet->getCell("G{$rowNr}")->getValue(),
                            "munkakor" => $sheet->getCell("H{$rowNr}")->getValue(),
                            "korlatozas" => $sheet->getCell("I{$rowNr}")->getValue(),
                            "alkalmassag" => $sheet->getCell("J{$rowNr}")->getValue(),
                            "ervenyesseg" => $ervenyesseg,
                            "vizsgalattipus" => $sheet->getCell("L{$rowNr}")->getValue(),
                            "vizsgalatdatum" => date("Y-m-d H:i:s", strtotime($vizsgalatDatum))
                        ];

                        sql_query("delete from dokirex_vizsgalatok where datum=? and orvos=?", [$row["datum"], $row["orvos"]]);
                        sql_query("insert into dokirex_vizsgalatok set 
                                    datum=:datum, moddatum=:datum, nev=:nev,
                                    szakrendeles=:szakrendeles, orvos=:orvos,
                                    paciensid=:paciensid,szuldatum=:szuldatum, telephely=:telephely, munkakor=:munkakor, 
                                    korlatozas=:korlatozas, alkalmassag=:alkalmassag, ervenyesseg=:ervenyesseg, vizsgalattipus=:vizsgalattipus, vizsgalatdatum=:vizsgalatdatum", $row);

                        $rowNr++;
                    }
                }
            }
            return "";
        }

        return "A feltöltött file-t nem sikerült beazonosítani";
    }

    public function processFileXlsFromEmail($tempFile):string {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($tempFile);

        //zeusz vizsgálatok + dokirex excel
        $sheet = $spreadsheet->getSheet(0);
        $days = [];

        if (!empty($sheet)) {
            $testCell = $sheet->getCell("B1")->getValue();
            $testCellKieg = $sheet->getCell("I1")->getValue();

            if ($testCell == "PaciensVizsgalat_UtolsoModositasDatuma" || $testCell == "Vizsgalat/FelvetelDatuma") {
                $rowNr = 2;

                //$result = [];
                while (true) {
                    $datum = str_replace(".", "-", $sheet->getCell("B{$rowNr}")->getFormattedValue());

                    if (empty($datum)) {
                        break;
                    }

                    $ervenyesseg = str_replace(".", "-", $sheet->getCell("L{$rowNr}")->getFormattedValue());
                    if (empty($ervenyesseg)) {
                        $ervenyesseg = "2000-01-01";
                    }

                    $vizsgalatDatum = str_replace(".", "-", $sheet->getCell("N{$rowNr}")->getFormattedValue());
                    if (empty($vizsgalatDatum)) {
                        $vizsgalatDatum = "0000-00-00";
                    } else {
                        $vizsgalatDatum = date("Y-m-d H:i:s", strtotime($vizsgalatDatum));
                    }

                    $row = [
                        "datum" => date("Y-m-d H:i:s", strtotime($datum)),
                        "nev" => $sheet->getCell("C{$rowNr}")->getValue(),
                        "szakrendeles" => $sheet->getCell("D{$rowNr}")->getValue(),
                        "orvos" => $sheet->getCell("E{$rowNr}")->getValue(),
                        "paciensid" => $sheet->getCell("F{$rowNr}")->getValue(),
                        "szuldatum" => date("Y-m-d", strtotime($sheet->getCell("G{$rowNr}")->getFormattedValue())),
                        "telephely" => $sheet->getCell("H{$rowNr}")->getValue(),
                        "munkakor" => $sheet->getCell("I{$rowNr}")->getValue(),
                        "korlatozas" => $sheet->getCell("J{$rowNr}")->getValue(),
                        "alkalmassag" => $sheet->getCell("K{$rowNr}")->getValue(),
                        "ervenyesseg" => date("Y-m-d", strtotime($ervenyesseg)),
                        "vizsgalattipus" => $sheet->getCell("M{$rowNr}")->getValue(),
                        "vizsgalatdatum" => $vizsgalatDatum
                    ];

                    sql_query("delete from dokirex_vizsgalatok where datum=? and orvos=?", [$row["datum"], $row["orvos"]]);
                    sql_query("insert into dokirex_vizsgalatok set 
                                datum=:datum, moddatum=:datum, nev=:nev,
                                szakrendeles=:szakrendeles, orvos=:orvos,
                                paciensid=:paciensid,szuldatum=:szuldatum, telephely=:telephely, munkakor=:munkakor, 
                                korlatozas=:korlatozas, alkalmassag=:alkalmassag, ervenyesseg=:ervenyesseg, vizsgalattipus=:vizsgalattipus, vizsgalatdatum=:vizsgalatdatum", $row);

                    $rowNr++;
                }

            }
            return "";
        }

        return "A feltöltött file-t nem sikerült beazonosítani";
    }

    private function processUploadedFile($uploadedFile) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        if (is_uploaded_file($uploadedFile["tmp_name"])) {
            $ok = false;
            $fileName = strtolower($uploadedFile["name"]);
            $fileSize = $uploadedFile["size"];
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            if (in_array($extension, array("xls","xlsx"))) {
                $tempFile = Booking_Constants::DOCUMENT_PATH.session_id().".{$extension}";
                @move_uploaded_file($uploadedFile["tmp_name"], $tempFile);

                return $this->processFileXls($tempFile);
            } else {
                return "A feltöltött file formátuma nem megfelelő (csak excel .xlsx dokumentumot lehet feltölteni)";
            }
        } else {
            return "Nincs feltöltött file!";
        }
    }

    public static function getDokirexVizsgalatok($from, $to = ""):array {
        if (empty($to)) {
            $to = $from;
        }

        $from = date("Y-m-d 00:00:00", strtotime($from));
        $to = date("Y-m-d 23:59:59", strtotime($to));

        return sql_query("select * from dokirex_vizsgalatok where datum>=? and datum<=?", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFoglalasok($from, $to = ""):array {
        if (empty($to)) {
            $to = $from;
        }

        $from = date("Y-m-d 00:00:00", strtotime($from));
        $to = date("Y-m-d 23:59:59", strtotime($to));

        return sql_query("select id from foglalasok f where f.datum>=? and f.datum<=? and f.eljott=1", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRontgen($from, $to = ""):array {
        $institutionNames = DicomService::getInstitutesQuery();

        if (empty($to)) {
            $to = $from;
        }

        $from = date("Y-m-d 00:00:00", strtotime($from));
        $to = date("Y-m-d 23:59:59", strtotime($to));

        return sql_query_common("select contentDate, patientName from dicom d where d.contentDate>=? and d.contentDate<=? and d.institutionName in ({$institutionNames})group by d.patientID, d.patientBirthDate", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);
    }



    private function displayCalendarDayBox($day):string {
        $html = "";

        $napClass = "calday";
        $diff = (strtotime($day) - strtotime(date("Y-m-d"))) / (3600*24);

        $boxStyle = ($diff == 0?"background:#f0e0e0":"");

        $html.= "<div class='monthlycell' style='{$boxStyle}'>";
        $html.= "<div style='text-align: center;font-weight: bold;font-size: 22px;'><div title='' onclick='alert(\"{$day}\");' class='{$napClass}'>".date("d", strtotime($day))."</div></div>";

        if (strtotime("now") > strtotime($day)) {
            $html .= "<div data-day='{$day}' id='daytext{$day}' style='padding-top:0px;'>";

            $dokirex = $this->getDokirexVizsgalatok($day);
            $reservations = $this->getFoglalasok($day);
            $rontgen = $this->getRontgen($day);
            $beosztas = WorkScheduleService::getDailySchedule($day);
            $workers = [];
            foreach ($beosztas as $beo) {
                $workers[] = $beo["workername"];
            }

            $emptyAll = empty($dokirex) && empty($beosztas) && empty($reservations) && empty($rontgen);

            $html .= "<div><img id='dailystatloader{$day}' style='display:none;opacity:.5;height:30px;margin-top:10px;' src='/images/loading_transparent.svg' /></div>";

            $html .= "<div id='datablock{$day}' style='margin-top:10px;'>";
            $html .= "<div style='height:70px;overflow: hidden;'>";
            if (!$emptyAll) {
                $html .= "<div>" . (empty($reservations) ? "<span style='opacity: .5;'>0 foglalás</span>" : count($reservations) . " foglalás") . "</div>";
                $html .= "<div>" . (empty($rontgen) ? "<span style='opacity: .5;'>0 röntgen</span>" : count($rontgen) . " röntgen") . "</div>";
                $html .= "<div>" . (empty($dokirex) ? "<span style='opacity: .5;'>0 dokirex vizsgálatok</span>" : count($dokirex) . " dokirex vizsgálat") . "</div>";
                $html .= "<div>" . (empty($beosztas) ? "<span style='opacity: .5;'>0 beosztás</span>" : "<span title='" . implode(", ", array_unique($workers)) . "'>" . count(array_unique($workers)) . " dolgozó</span>") . "</div>";
            }
            $html .= "</div>";
            $html .= "<div style='margin-top:10px;'>";
            if (!$emptyAll) {
                $html .= "<div class='dailysmallbutton' data-day='dayvalid' onclick='downloadDailyStat(this, \"$day\", \"$day\")' title='Napi statisztika letöltése'><i class='fas fa-file-download'></i> napi statisztika</div> ";
            }
            $html .= "</div>";
            $html .= "</div>";

            $html .= "</div>";
        }
        $html.= "</div>";

        return $html;
    }

    public function displayCalendar($offset):string {
        $html         = "";
        $now          = date("Y-m-01");
        $year         = date("Y",strtotime("{$now} +{$offset} month"));
        $month        = intval(date("n",strtotime("{$now} +{$offset} month")));
        $monthText    = date("F",strtotime("{$now} +{$offset} month"));
        $numberOfDays = date("t",strtotime("{$now} +{$offset} month"));
        $firstDay     = date("N",strtotime("first day of {$year} {$monthText}"));
        $firstDate    = date("Y-m-d",strtotime("first day of {$year} {$monthText}"));
        $firstDateY   = date("Y-01-01",strtotime("first day of {$year}"));
        $lastDate     = date("Y-m-d",strtotime("last day of {$year} {$monthText}"));
        $lastDateY    = date("Y-m-d");
        $weekDay      = 0;

        $dokirex = $this->getDokirexVizsgalatok($firstDate, $lastDate);
        $reservations = $this->getFoglalasok($firstDate, $lastDate);
        $rontgen = $this->getRontgen($firstDate, $lastDate);
        $emptyAll = empty($dokirex) && empty($reservations) && empty($rontgen);

        $html.= "<table class='montlytable' style='margin:0px;padding:0px;'>";
        $html.= "<tr>";
        $html.= "<td colspan='1' class='montlycell mthead' style='text-align: left;'><a href='#' onclick='DailyStatMoveMonth(-1);return false;'><i class='fas fa-chevron-circle-left'></i></a></td>";
        $html.= "<td colspan='5' class='montlycell mthead'>";

            $html .= "<div>&nbsp;&nbsp;{$year} " . $this->months[$month] . "&nbsp;&nbsp;</div>";

        if (!$emptyAll) {
            $html.= "<div style='font-weight: normal;font-size: 12px;'>" . (empty($reservations) ? "foglalás  <i style='color:red' class='fas fa-times-circle'></i>" : count($reservations) . " foglalás") . "</div>";
            $html.= "<div style='font-weight: normal;font-size: 12px;'>" . (empty($rontgen) ? "röntgen  <i style='color:red' class='fas fa-times-circle'></i>" : count($rontgen) . " röntgen") . "</div>";
            $html.= "<div style='font-weight: normal;font-size: 12px;'>" . (empty($dokirex) ? "dokirex vizsgálatok  <i style='color:red' class='fas fa-times-circle'></i>" : count($dokirex) . " dokirex vizsgálat</i>") . "</div>";
            $html.= "<div style='padding-top: 5px;'><div class='dailysmallbutton' onclick='downloadDailyStat(this, \"{$firstDateY}\", \"{$lastDateY}\")' title='Éves statisztika letöltése'><i class='fas fa-file-download'></i> éves statisztika</div>&nbsp;&nbsp;<div class='dailysmallbutton' onclick='downloadDailyStat(this, \"{$firstDate}\", \"{$lastDate}\")' title='Havi statisztika letöltése'><i class='fas fa-file-download'></i> havi statisztika</div></div>";
        }
        $html.= "</td>";
        $html.= "<td colspan='1' class='montlycell mthead' style='text-align: right;'><a href='#' onclick='DailyStatMoveMonth(1);return false;'><i class='fas fa-chevron-circle-right'></i></a></td>";
        $html.= "</tr>";
        $html.= "<tr>";
        for ($i = 1; $i <= 7 ;$i++) {
            $html.= "<td class='monthlycell mtweekday' style=''>".$this->weekDays[$i]."</td>";
        }
        $html.= "</tr><tr>";

        for ($i = 1; $i < $firstDay ;$i++) {
            $weekDay++;
            $html.= "<td class='monthlycell' style='background: white;'>&nbsp;</td>";
        }

        $weekHtml = "";
        for ($nap = 1; $nap <= $numberOfDays; $nap++) {
            $thisDay = "{$year}-".substr("00{$month}",-2)."-".substr("00{$nap}",-2);

            if ($weekDay++ >= 7) {
                $weekStartDay = date("Y-m-d", strtotime("{$thisDay} - 7 day"));
                $weekEndDay = date("Y-m-d", strtotime("{$thisDay} - 1 day"));
                $numberOfWeek = date("W", strtotime($weekEndDay));
                $html.= "<td style='text-align: center;padding:10px;'><div style='font-size: 18px;font-weight: bold;margin-bottom:10px;'>{$numberOfWeek}. hét</div>";
                $html.= "<div class='dailysmallbutton' data-day='dayvalid' onclick='downloadDailyStat(this, \"{$weekStartDay}\", \"{$weekEndDay}\")' title='Heti statisztika letöltése'><i class='fas fa-file-download'></i> heti statisztika</div>";
                $html.= "</td>";
                $html.= "</tr><tr>";
                $weekDay = 1;
            }

            $html.= "<td id='daybox{$thisDay}'>";
            $dayBox = $this->displayCalendarDayBox($thisDay);
            $weekHtml.= $dayBox;
            $html.= $dayBox;
            $html.= "</td>";
        }

        $html.= "</tr>";
        $html.= "</table>";
        return $html;
    }


    private function generateDailyStat($dayFrom, $dayTo):array {
        $result = [
            "error" => "",
            "debughtml" => "",
            "result" => ""
        ];

        $dailyStatData = [];

        $szakrendelesek = $orvosok = [];

        $dokirexData = $this->getDokirexVizsgalatok($dayFrom);
        $beosztasData = WorkScheduleService::getDailySchedule($dayFrom);

        foreach ($dokirexData as $vizsgalat) {
            $szakrendelesek[] = $vizsgalat["szakrendeles"];
            $orvosok[] = $vizsgalat["orvos"];
        }

        $szakrendelesek = array_unique($szakrendelesek);
        $orvosok = array_unique($orvosok);

        foreach ($szakrendelesek as $szakrendeles) {
            $paciensekDb      = 0;
            $szakterOsszesIdo = 0;
            $normaIdoMinute   = 15;

            foreach ($dokirexData as $vizsgalat) {
                if ($vizsgalat["szakrendeles"] == $szakrendeles) {
                    $paciensekDb++;
                }
            }

            $szakrendelesOrvos = [];

            foreach ($orvosok as $orvos) {
                $orvosPaciensekDb = $osszesido = 0;
                $minTime = "{$dayFrom} 23:59:59";
                $maxTime = "{$dayFrom} 00:00:00";
                $beoTime = "nincs megadva";
                $nover = "";
                $orvosBeosztas = [];

                foreach ($dokirexData as $vizsgalat) {
                    if ($vizsgalat["orvos"] == $orvos && $vizsgalat["szakrendeles"] == $szakrendeles) {
                        $orvosPaciensekDb++;
                        if (strtotime($minTime) > strtotime($vizsgalat["datum"])) {
                            $minTime = $vizsgalat["datum"];
                        }
                        if (strtotime($maxTime) < strtotime($vizsgalat["datum"])) {
                            $maxTime = $vizsgalat["datum"];
                        }
                    }
                }

                if ($orvosPaciensekDb > 0) {
                    $osszesido = (strtotime($maxTime) - strtotime($minTime)) + ($normaIdoMinute*60);
                    $atlagido = $osszesido / $orvosPaciensekDb;

                    foreach ($beosztasData as $beosztas) {
                        if ($beosztas["workername"] == $orvos) {
                            $orvosBeosztas[] = $beosztas;
                        }
                    }

                    if (!empty($orvosBeosztas)) {
                        $beo = $orvosBeosztas[0];
                        $beoTime = date("H:i", strtotime($beo["datumfrom"])) . " - " . date("H:i", strtotime($beo["datumto"]));
                    }

                    $szakrendelesOrvos[$orvos] = [
                        "name" => $orvos,
                        "db" => $orvosPaciensekDb,
                        "nover" => $nover,
                        "mintime" => $minTime,
                        "maxtime" => $maxTime,
                        "osszesido" => round($osszesido/60, 2),
                        "atlagido" => round($atlagido/60, 2),
                        "beo" => $beoTime
                    ];
                }

                $szakterOsszesIdo += $osszesido;
            }


            $dailyStatData["day"] = $dayFrom;
            $dailyStatData["dayFrom"] = $dayFrom;
            $dailyStatData["dayTo"] = $dayTo;
            $dailyStatData["szakrendelesek"][$szakrendeles] = [
                "name" => $szakrendeles,
                "db" => $paciensekDb,
                "normaido" => $normaIdoMinute,
                "osszesido" => round($szakterOsszesIdo/60 ,2),
                "atlagido" => round(($szakterOsszesIdo / $paciensekDb) / 60, 2),
                "orvosok" => $szakrendelesOrvos
            ];
        }

        //sql_query("update dailystat set finalresult=? where day=?", [json_encode($dailyStatData, JSON_PRETTY_PRINT), $day]);

        $result["result"] = $dailyStatData;
        //$result["debughtml"] = "<pre>".print_r($dailyStatData, true)."</pre>";

        return $result;

    }

    public static function getTempFileName():string {
        return Booking_Constants::APP_PATH."library/other/tmp/".session_id().".xlsx";
    }


    private function processJenbacherExcel($sheet) {
        $rowNr = 2;


        sql_query("delete from dokirex_vizsgalatok where orvos=?", ["Jenbacher"]);

        while (true) {
            $id = $sheet->getCell("A{$rowNr}")->getFormattedValue();

            if (empty($id)) {
                break;
            }

            $szulDatum = str_replace(".", "-", $sheet->getCell("D{$rowNr}")->getFormattedValue());
            $vizsgalatDatum = str_replace(".", "-", $sheet->getCell("U{$rowNr}")->getFormattedValue());
            if (empty($vizsgalatDatum)) {
                $vizsgalatDatum = "0000-00-00";
            }

            $ervenyesseg = str_replace(".", "-", $sheet->getCell("V{$rowNr}")->getFormattedValue());
            if (empty($ervenyesseg)) {
                $ervenyesseg = "2000-01-01";
            }

            $row = [
                "datum" => "2020-01-01 00:00:00",
                "nev" => $sheet->getCell("B{$rowNr}")->getValue(),
                "szakrendeles" => "",
                "orvos" => "Jenbacher",
                "paciensid" => $sheet->getCell("F{$rowNr}")->getValue(),
                "szuldatum" => date("Y-m-d", strtotime($szulDatum)),
                "telephely" => "Jenbacher",
                "munkakor" => "",
                "email" => $sheet->getCell("AB{$rowNr}")->getValue(),
                "korlatozas" => "",
                "alkalmassag" => "",
                "ervenyesseg" => date("Y-m-d H:i:s", strtotime($ervenyesseg)),
                "vizsgalattipus" => "",
                "vizsgalatdatum" => date("Y-m-d H:i:s", strtotime($vizsgalatDatum))
            ];

            sql_query("insert into dokirex_vizsgalatok set 
                                    datum=:datum, moddatum=:datum, nev=:nev,
                                    szakrendeles=:szakrendeles, orvos=:orvos,
                                    paciensid=:paciensid,szuldatum=:szuldatum, telephely=:telephely, munkakor=:munkakor, email=:email, 
                                    korlatozas=:korlatozas, alkalmassag=:alkalmassag, ervenyesseg=:ervenyesseg, vizsgalattipus=:vizsgalattipus, vizsgalatdatum=:vizsgalatdatum", $row);

            $rowNr++;
        }
    }

}