<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


class ExcelService {
    private $fileName;
    private $spreadSheet;
    private $sheet;
    private $columnNames = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "X", "Y", "Z"];

    private string $esztergomFilter = "f.helyszinid=532 and ";
    private string $jaszSuzukiFilter = "f.cegid IN (504) and ";
    private string $jaszAndEsztergomSuzukiFilter = "f.helyszinid in (1, 532, 100, 644) AND f.cegid IN (892) and ";
    private string $korosiUtcaFilter = "f.helyszinid in (600) and ";
    private string $extraFilter = "";

    public function __construct() {
        if (session_id() == "5bjd1s7gcfehaf9ar9uvs0g8k2") {
            $this->extraFilter = $this->jaszAndEsztergomSuzukiFilter;
        }
    }

    public function setFileName($fileName) {
        $this->fileName = $fileName;
    }

    private function read($fileName) {
        $reader = IOFactory::createReader("Xlsx");
        $this->spreadSheet = $reader->load($fileName);
        return;
    }

    public function outputSpreadSheet() {
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment;filename=\"{$this->fileName}\"");
        header("Cache-Control: max-age=0");

        try {
            $writer = IOFactory::createWriter($this->spreadSheet, 'Xlsx');
            $writer->save('php://output');
        } catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
            return false;
        }
        die;
    }

    public function outputSpreadSheetFile($fileName) {
        try {
            $writer = IOFactory::createWriter($this->spreadSheet, 'Xlsx');
            $writer->save($fileName);
        } catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
            return false;
        }
        return true;
    }

    public function getSpreadSheet() {
        return $this->spreadSheet;
    }

    private function titleRow($cell, $text) {
        $this->sheet->SetCellValue($cell, $text);
        $this->sheet->getStyle($cell)->getFont()->setBold(true)->setSize(16);
    }

    private function headingRow($startColumn, $row, $values) {
        $columnId = array_search($startColumn, $this->columnNames);
        $column = $startColumn;
        foreach ($values as $value) {
            $column = $this->columnNames[$columnId];
            $this->sheet->SetCellValue("{$column}{$row}", $value);
            $columnId++;
        }

        $this->sheet->getStyle("{$startColumn}{$row}:{$column}{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('cccccc');
        $this->sheet->getStyle("{$startColumn}{$row}:{$column}{$row}")->getFont()->setBold(true);
    }

    private function dataRow($startColumn, $row, $values) {
        $columnId = array_search($startColumn, $this->columnNames);
        foreach ($values as $value) {

            $column = $this->columnNames[$columnId];
            $this->sheet->SetCellValue("{$column}{$row}", $value);
            $columnId++;
        }
    }

    private function totalRow($startColumn, $row, $values) {
        $columnId = array_search($startColumn, $this->columnNames);
        $column = $startColumn;
        foreach ($values as $value) {
            $column = $this->columnNames[$columnId];
            $this->sheet->SetCellValue("{$column}{$row}", $value);
            $columnId++;
        }

        $this->sheet->getStyle("{$startColumn}{$row}:{$column}{$row}")->getFont()->setBold(true);
    }

    public function combinedStat($data) {
        $spreadsheet = new Spreadsheet();
        $this->sheet = $spreadsheet->getActiveSheet();

        $intervalString = date("Y-m-d", strtotime($data["interval"][0]))." - ".date("Y-m-d", strtotime($data["interval"][1]));

        $this->titleRow("A1", Booking_Constants::COMPANY_NAME_SHORT." bejelentkező statisztika {$intervalString}");

        //céges stat
        $sor = 3;
        $this->headingRow("A", $sor, ["Cég", "Foglalások", "Eljött"]);

        $sor++;
        $total = $totaleljott = 0;
        foreach ($data["companystat"] as $rowData) {
            if (empty($rowData["ceg"])) {
                $rowData["ceg"] = "nincs megadva";
            }
            $this->dataRow("A", $sor, [$rowData["ceg"], $rowData["foglalasok"], $rowData["eljott"]]);
            $total += $rowData["foglalasok"];
            $totaleljott += $rowData["eljott"];
            $sor++;
        }

        $this->totalRow("A", $sor, ["Összesen:", $total, $totaleljott]);
        $this->sheet->getColumnDimension('A')->setWidth(40);

        //orvos stat
        $sor = 3;
        $this->headingRow("E", $sor, ["Orvos", "Foglalások", "Eljött"]);

        $sor++;
        $total = $totaleljott = 0;
        foreach ($data["doctorstat"] as $rowData) {
            $this->dataRow("E", $sor, [$rowData["orvos"], $rowData["foglalasok"], $rowData["eljott"]]);
            $total += $rowData["foglalasok"];
            $totaleljott += $rowData["eljott"];
            $sor++;
        }

        $this->totalRow("E", $sor, ["Összesen:", $total, $totaleljott]);
        $this->sheet->getStyle("E{$sor}:G{$sor}")->getFont()->setBold(true);

        $this->sheet->getColumnDimension('E')->setWidth(40);

        $this->spreadSheet = $spreadsheet;
    }

    public function rtgList($data) {
        $spreadsheet = new Spreadsheet();
        $this->sheet = $spreadsheet->getActiveSheet();

        $intervalString = date("Y-m-d", strtotime($data["interval"][0]))." - ".date("Y-m-d", strtotime($data["interval"][1]));

        $this->titleRow("A1", Booking_Constants::COMPANY_NAME_SHORT." RTG lista {$intervalString}");

        //lista
        $sor = 5;
        $this->headingRow("A", $sor, ["Dátum", "Paciens", "Szül. dátum", "TAJ", "Cég", "db"]);

        $sor++;
        $total = $totalImage = 0;
        foreach ($data["list"] as $rowData) {
            $this->dataRow("A", $sor, [$rowData["contentDate"], $rowData["patientName"], $rowData["patientBirthDate"], $rowData["patientOtherIDs"], $rowData["studyDescription"], $rowData["db"]]);
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("left");
            $total ++;
            $totalImage += $rowData["db"];
            $sor++;
        }

        $this->sheet->getColumnDimension('A')->setWidth(30);

        $this->sheet->getColumnDimension('A')->setWidth(30);
        $this->sheet->getColumnDimension('B')->setWidth(40);
        $this->sheet->getColumnDimension('C')->setWidth(20);
        $this->sheet->getColumnDimension('D')->setWidth(40);
        $this->sheet->getColumnDimension('E')->setWidth(20);


        $sor = 3;
        $this->dataRow("A", $sor, ["Összes paciens: {$total}, összes kép: {$totalImage}"]);




        //$this->sheet->getStyle('C:D')->getAlignment()->setHorizontal('center');
        //$this->totalRow("A", $sor, ["Összesen:", $total, $totaleljott]);
        //$this->sheet->getColumnDimension('A')->setWidth(40);


        $this->spreadSheet = $spreadsheet;
    }

    public function vizsgaList($data) {
        $spreadsheet = new Spreadsheet();
        $this->sheet = $spreadsheet->getActiveSheet();

        $this->titleRow("A1", "Elsősegély vizsgázók");

        //lista
        $sor = 3;
        $this->headingRow("A", $sor, ["Időpont", "Eredmény", "Vizsgázó neve", "Iskolai végzettség", "Szül. dátum", "Email", "Irsz", "Város", "Cím"]);

        $sor++;
        $total = $totalImage = 0;
        foreach ($data["list"] as $rowData) {
            $data = json_decode($rowData["adatok"], JSON_OBJECT_AS_ARRAY);
            $szuldatum = $data["szuldatumev"]."-".substr("00{$data["szuldatumho"]}", -2)."-".substr("00{$data["szuldatumnap"]}", -2);

            $this->dataRow("A", $sor, [$rowData["datum"], "{$rowData["osszesvalasz"]}/{$rowData["helyesvalasz"]}", $data["nev"], $data["iskolavegzettseg"], $szuldatum, $data["email"], $data["irsz"], $data["varos"], $data["cim"]]);
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("left");
            $total ++;
            $totalImage += $rowData["db"];
            $sor++;
        }

        $this->sheet->getColumnDimension('A')->setWidth(20);
        $this->sheet->getColumnDimension('B')->setWidth(10);
        $this->sheet->getColumnDimension('C')->setWidth(40);
        $this->sheet->getColumnDimension('D')->setWidth(40);
        $this->sheet->getColumnDimension('E')->setWidth(10);
        $this->sheet->getColumnDimension('F')->setWidth(40);
        $this->sheet->getColumnDimension('G')->setWidth(10);
        $this->sheet->getColumnDimension('H')->setWidth(40);
        $this->sheet->getColumnDimension('I')->setWidth(40);
        $this->sheet->getColumnDimension('J')->setWidth(40);

        //$sor = 3;
        //$this->dataRow("A", $sor, ["Összes paciens: {$total}, összes kép: {$totalImage}"]);

        $this->spreadSheet = $spreadsheet;
    }


    /*        $html.= "<tr style='font-weight: bold;'>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Foglalás időpontja</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Típus</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Név</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Telefon</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Munkakör</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>TAJ szám</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Orvos</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'></td>";
        $html.= "</tr>";
        foreach ($data as $rows) {
            $html.= "<tr>";
            $html.= "<td nowrap>".substr($rows["datum"],0,16)."</td>";
            $html.= "<td nowrap>{$rows["tipusnev"]}</td>";
            $html.= "<td nowrap>{$rows["nev"]}</td>";
            $html.= "<td nowrap>{$rows["telefon"]}</td>";
            $html.= "<td nowrap>{$rows["munkakor"]}</td>";
            $html.= "<td nowrap>{$rows["taj"]}</td>";
            $html.= "<td nowrap>{$rows["orvosnev"]}</td>";
            $html.= "<td nowrap>{$rows["helyszincim"]}</td>";

            if ($rows["taj"] == "") {
                $rows["taj"] = "000000000";
            }
*/
    public function cegFoglalasList($data) {
        $spreadsheet = new Spreadsheet();
        $this->sheet = $spreadsheet->getActiveSheet();

        $this->titleRow("A1", "{$data["cegNev"]} foglalásai ".date("Y-m-d", strtotime($data["from"]))." - ".date("Y-m-d", strtotime($data["to"]))."");
        $this->dataRow("A", 2, ["Forrás: ".Booking_Constants::FOOTER_COPYRIGHT." bejelentkező, csak eljöttnek jelölt foglalások"]);

        //lista
        $sor = 4;
        $this->headingRow("A", $sor, ["Foglalás időpontja", "Típus", "Név", "Telefon", "Munkakör", "TAJ szám", "Orvos", "", "Megjegyzés"]);

        $sor++;
        foreach ($data["data"] as $rowData) {
            $this->dataRow("A", $sor, [substr($rowData["datum"], 0, 16), $rowData["tipusnev"], $rowData["nev"], $rowData["telefon"], $rowData["munkakor"], $rowData["taj"], $rowData["orvosnev"], $rowData["helyszincim"], $rowData["megj"]]);
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("left");
            $this->sheet->getStyle("F{$sor}")->getAlignment()->setHorizontal("left");
            $sor++;
        }

        $this->sheet->getColumnDimension('A')->setWidth(20);
        $this->sheet->getColumnDimension('B')->setWidth(20);
        $this->sheet->getColumnDimension('C')->setWidth(40);
        $this->sheet->getColumnDimension('D')->setWidth(20);
        $this->sheet->getColumnDimension('E')->setWidth(20);
        $this->sheet->getColumnDimension('F')->setWidth(20);
        $this->sheet->getColumnDimension('G')->setWidth(20);
        $this->sheet->getColumnDimension('H')->setWidth(40);
        $this->sheet->getColumnDimension('I')->setWidth(100);

        //$sor = 3;
        //$this->dataRow("A", $sor, ["Összes paciens: {$total}, összes kép: {$totalImage}"]);

        $this->spreadSheet = $spreadsheet;
    }


    private function setAutoWidth($range) {
        foreach($range as $columnID) {
            $this->sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }

     private function setAutoWidthInCustomSheet($range,$sheet) {
        foreach($range as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }


    private function _vizsgalatKimutatas($sheetId, $rawInput, $from, $to) {
        $salaryService = new SalaryCalculator();

        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Vizsgálatok-kimutatás");

        if (isset($data["day"])) {
            $data = json_decode($rawInput["finalresult"], JSON_OBJECT_AS_ARRAY);
            $day = $data["day"];

            $this->titleRow("A1", "Napi statisztika - {$day}");


            $sor = 3;
            $this->headingRow("B", $sor, ["Terület", "Ellátott paciensek", "Összes vizsgálati idő", "Átlagos vizsgálati idő"]);
            $sor++;

            $total = $totalTime = 0;
            foreach ($data["szakrendelesek"] as $szakrendelesData) {
                $this->dataRow("B", $sor, [$szakrendelesData["name"], $szakrendelesData["db"], $szakrendelesData["osszesido"], $szakrendelesData["atlagido"]]);
                $sor++;

                $total += $szakrendelesData["db"];
                $totalTime += $szakrendelesData["osszesido"];
            }
            $this->totalRow("B", $sor, ["Összesen:", $total, $totalTime, round($totalTime / $total, 2)]);
            $sor++;

            $sor++;

            foreach ($data["szakrendelesek"] as $szakrendelesData) {
                $this->titleRow("A{$sor}", "{$szakrendelesData["name"]}");
                $sor += 2;

                $this->headingRow("A", $sor, ["Orvos", "Nővér", "Beosztás", "Ellátott paciensek", "Összes vizsgálati idő", "Átlagos vizsgálati idő", "Költség", "Költségelemek"]);
                $sor++;

                $total = $totalTime = $totalPrice = 0;
                foreach ($szakrendelesData["orvosok"] as $orvosData) {
                    $salaryService->manualNumberOfPatients = $orvosData["db"];
                    $salaryService->manualNumberOfHours = round($orvosData["osszesido"] / 60, 2);
                    $doctorSalary = $salaryService->getDoctorSalary($orvosData["name"], $day, $day);
                    $salaryTextData = $salaryService->getSalaryText($doctorSalary);

                    //echo print_r($salaryTextData["text"], true);
                    //die;

                    $this->dataRow("A", $sor, [$orvosData["name"], $orvosData["nover"], $orvosData["beo"], $orvosData["db"], $orvosData["osszesido"], $orvosData["atlagido"], $salaryTextData["total"], implode(" + ", $salaryTextData["text"])]);
                    $sor++;
                    $total += $orvosData["db"];
                    $totalTime += $orvosData["osszesido"];
                    $totalPrice += $salaryTextData["total"];
                }

                $this->totalRow("A", $sor, ["Összesen:", "", "", $total, $totalTime, round($totalTime / $total, 2), $totalPrice]);
                $sor++;

                //$this->dataRow("B", $sor, [$szakrendelesData["name"], $szakrendelesData["db"], $szakrendelesData["osszesido"], $szakrendelesData["atlagido"]]);
                $sor++;
            }

            $this->setAutoWidth(range('A', 'H'));
        }
    }

    private function _bejelentkezoFoglalasokLista($sheetId, $from, $to, $onlyArrived = true) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Előjegyzések");
        $this->titleRow("A1", "Foglalások - {$from} - {$to} (forrás: bejelentkező)");
        $wArrived = "";
        if ($onlyArrived) {
            $this->sheet->SetCellValue("A2", "* csak eljöttek");
            $wArrived = "AND f.eljott=1";
        }

        $sor = 4;

        $tipusok = sql_query("SELECT t.id, t.megnev FROM foglalasok f
            LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
            WHERE {$this->extraFilter} f.datum>'{$from} 00:00:00' AND f.datum<'{$to} 23:59:59' {$wArrived} GROUP BY f.`szurestipusid`")->fetchAll(PDO::FETCH_ASSOC);


        foreach ($tipusok as $tipus) {
            $this->titleRow("A{$sor}", "{$tipus["megnev"]}");
            $sor+=2;

            $this->headingRow("A", $sor, ["Dátum", "Helyszín", "Orvos", "Cég", "Paciens", "TAJ", "Születési dátum", "Eljött", "Eljött időpont", "Megjegyzés"]);
            $sor++;

            $reservations = sql_query("SELECT h.cim as helyszincim, t.megnev AS tipusnev, c.megnev AS cegnev, o.nev AS orvosnev, f.* FROM foglalasok f
                LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
                LEFT JOIN orvosok o ON o.id=f.orvosassigned
                LEFT JOIN cegek c ON c.id=f.cegid
                LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
                WHERE {$this->extraFilter} datum>'{$from} 00:00:00' AND datum<'{$to} 23:59:59' and f.szurestipusid=? {$wArrived} order by datum", [$tipus["id"]])->fetchAll(PDO::FETCH_ASSOC);


            foreach ($reservations as $reservation) {
                $this->dataRow("A", $sor, [$reservation["datum"], $reservation["helyszincim"], $reservation["orvosnev"], $reservation["cegnev"], $reservation["nev"], $reservation["taj"], $reservation["szuldatum"], $reservation["eljott"] == 1 ? "eljött":"nem jött el", $reservation["eljottidopont"] != "0000-00-00 00:00:00" ? $reservation["eljottidopont"]:"", $reservation["megj"]]);
                $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
                $sor++;
            }
            $sor++;
        }

        $this->setAutoWidth(range('B','L'));
        $this->sheet->getColumnDimension('A')->setWidth(20);
    }

    private function _bejelentkezoNemEljottLista($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Nem eljöttek listája");
        $this->titleRow("A1", "El nem jöttek listája - {$from} - {$to} (forrás: bejelentkező)");

        $sor = 4;

        $tipusok = sql_query("SELECT t.id, t.megnev FROM foglalasok f
            LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
            WHERE f.datum>'{$from} 00:00:00' AND f.datum<'{$to} 23:59:59' and f.eljott=0 AND f.taj<>'' GROUP BY f.`szurestipusid`")->fetchAll(PDO::FETCH_ASSOC);


        foreach ($tipusok as $tipus) {
            $this->titleRow("A{$sor}", "{$tipus["megnev"]}");
            $sor+=2;

            $this->headingRow("A", $sor, ["Dátum", "Helyszín", "Orvos", "Cég", "Paciens", "TAJ", "Születési dátum", "Eljött", "Eljött időpont", "Megjegyzés"]);
            $sor++;

            $reservations = sql_query("SELECT h.cim as helyszincim, t.megnev AS tipusnev, c.megnev AS cegnev, o.nev AS orvosnev, f.* FROM foglalasok f
                LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
                LEFT JOIN orvosok o ON o.id=f.orvosassigned
                LEFT JOIN cegek c ON c.id=f.cegid
                LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
                WHERE datum>'{$from} 00:00:00' AND datum<'{$to} 23:59:59' and f.szurestipusid=? AND f.eljott=0 AND f.taj<>'' order by datum", [$tipus["id"]])->fetchAll(PDO::FETCH_ASSOC);

            foreach ($reservations as $reservation) {
                $this->dataRow("A", $sor, [$reservation["datum"], $reservation["helyszincim"], $reservation["orvosnev"], $reservation["cegnev"], $reservation["nev"], $reservation["taj"], $reservation["szuldatum"], $reservation["eljott"] == 1 ? "eljött":"nem jött el", $reservation["eljottidopont"] != "0000-00-00 00:00:00" ? $reservation["eljottidopont"]:"", $reservation["megj"]]);
                $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
                $sor++;
            }
            $sor++;
        }

        $this->setAutoWidth(range('B','L'));
        $this->sheet->getColumnDimension('A')->setWidth(20);
    }

    private function _bejelentkezoEljottStat($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Nem eljöttek");
        $this->titleRow("A1", "Nem eljöttek statisztikája - {$from} - {$to} (forrás: bejelentkező)");
        $this->sheet->SetCellValue("A2", "* csak a kitöltött taj számos foglalások szerepelnek a statisztikában!");

        $sor = 4;
        $this->titleRow("A{$sor}", "Dátum szerint");
        $sor+=2;

        $this->headingRow("A", $sor, ["Nap", "Összes időpont", "Nem jött el", "Százalék"]);
        $sor++;

        $queryFilter = "datum>'{$from}' AND datum<'{$to} 23:59:59' AND f.taj<>''";

        $reservations = sql_query("SELECT DATE(datum) AS datum, COUNT(*) AS total, SUM(IF(eljott=0, 1, 0)) AS nem_jott_el, ROUND(SUM(IF(eljott=0, 1, 0))/(COUNT(*)/100), 2) AS percent FROM foglalasok f 
            WHERE {$this->extraFilter} {$queryFilter}
            GROUP BY DATE(f.datum) ORDER BY DATE(f.datum)", [])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservations as $reservation) {
            $this->dataRow("A", $sor, [$reservation["datum"], $reservation["total"], $reservation["nem_jott_el"], $reservation["percent"]]);
            $sor++;
        }

        $sor = 4;
        $this->titleRow("F{$sor}", "Cégek szerint");
        $sor+=2;

        $this->headingRow("F", $sor, ["Cég", "Összes időpont", "Nem jött el", "Százalék"]);
        $sor++;

        $reservations = sql_query("SELECT c.megnev AS ceg, COUNT(*) AS total, SUM(IF(eljott=0, 1, 0)) AS nem_jott_el, ROUND(SUM(IF(eljott=0, 1, 0))/(COUNT(*)/100), 2) AS percent FROM foglalasok f 
            LEFT JOIN cegek c ON c.id=f.cegid
            WHERE {$this->extraFilter} {$queryFilter}
            GROUP BY c.id ORDER BY c.megnev", [])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservations as $reservation) {
            $ceg = empty($reservation["ceg"]) ? "Nem kitöltött cég" : $reservation["ceg"];
            $this->dataRow("F", $sor, [$ceg, $reservation["total"], $reservation["nem_jott_el"], $reservation["percent"]]);
            $sor++;
        }

        $sor = 4;
        $this->titleRow("K{$sor}", "Szolgáltatások szerint");
        $sor+=2;

        $this->headingRow("K", $sor, ["Szolgáltatás", "Összes időpont", "Nem jött el", "Százalék"]);
        $sor++;

        $reservations = sql_query("SELECT t.megnev AS szolgaltatas, COUNT(*) AS total, SUM(IF(eljott=0, 1, 0)) AS nem_jott_el, ROUND(SUM(IF(eljott=0, 1, 0))/(COUNT(*)/100), 2) AS percent FROM foglalasok f 
            LEFT JOIN szurestipusok t ON t.id=f.`szurestipusid`
            WHERE {$this->extraFilter} {$queryFilter}
            GROUP BY t.id ORDER BY t.megnev", [])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservations as $reservation) {
            $this->dataRow("K", $sor, [$reservation["szolgaltatas"], $reservation["total"], $reservation["nem_jott_el"], $reservation["percent"]]);
            $sor++;
        }


        $sor = 4;
        $this->titleRow("P{$sor}", "Orvosok szerint");
        $sor+=2;

        $this->headingRow("P", $sor, ["Orvos", "Összes időpont", "Nem jött el", "Százalék"]);
        $sor++;

        $reservations = sql_query("SELECT o.nev AS orvos, COUNT(*) AS total, SUM(IF(eljott=0, 1, 0)) AS nem_jott_el, ROUND(SUM(IF(eljott=0, 1, 0))/(COUNT(*)/100), 2) AS percent FROM foglalasok f 
            LEFT JOIN orvosok o ON o.id=f.`orvosassigned`
            WHERE {$this->extraFilter} {$queryFilter}
            GROUP BY o.id ORDER BY o.nev", [])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservations as $reservation) {
            $this->dataRow("P", $sor, [$reservation["orvos"], $reservation["total"], $reservation["nem_jott_el"], $reservation["percent"]]);
            $sor++;
        }

        $sor = 4;
        $this->titleRow("U{$sor}", "Helyszínek szerint");
        $sor+=2;

        $this->headingRow("U", $sor, ["Helyszín", "Összes időpont", "Nem jött el", "Százalék"]);
        $sor++;

        $reservations = sql_query("SELECT h.cim AS helyszincim, COUNT(*) AS total, SUM(IF(eljott=0, 1, 0)) AS nem_jott_el, ROUND(SUM(IF(eljott=0, 1, 0))/(COUNT(*)/100), 2) AS percent FROM foglalasok f 
            LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
            WHERE {$this->extraFilter} {$queryFilter}
            GROUP BY h.id ORDER BY h.cim", [])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservations as $reservation) {
            $this->dataRow("U", $sor, [$reservation["helyszincim"], $reservation["total"], $reservation["nem_jott_el"], $reservation["percent"]]);
            $sor++;
        }


        $this->setAutoWidth(range('B','Z'));
        $this->sheet->getColumnDimension('A')->setWidth(15);
    }

    private function _kiegeszitoFoglalasokLista($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Kiegészítő vizsgálatok");
        $this->titleRow("A1", "Kiegészítő vizsgálatok - {$from} - {$to} (forrás: bejelentkező)");
        $this->sheet->SetCellValue("A2", "* csak eljöttek, és megjegyzésben szerepel a 'kiegészítő' szó");

        $sor = 4;

        $tipusok = sql_query("SELECT t.id, t.megnev FROM foglalasok f
            LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
            WHERE f.datum>'{$from} 00:00:00' AND f.datum<'{$to} 23:59:59' and f.eljott=1 and f.`szurestipusid` IN (102, 85, 103, 101) and instr(f.megj, 'kiegészítő') GROUP BY f.`szurestipusid`")->fetchAll(PDO::FETCH_ASSOC);


        foreach ($tipusok as $tipus) {
            $this->titleRow("A{$sor}", "{$tipus["megnev"]}");
            $sor+=2;

            $this->headingRow("A", $sor, ["Dátum", "Orvos", "Cég", "Paciens", "TAJ", "Születési dátum","Megjegyzés"]);
            $sor++;

            $reservations = sql_query("SELECT t.megnev AS tipusnev, c.megnev AS cegnev, o.nev AS orvosnev, f.* FROM foglalasok f
                LEFT JOIN orvosok o ON o.id=f.orvosassigned
                LEFT JOIN cegek c ON c.id=f.cegid
                LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
                WHERE datum>'{$from} 00:00:00' AND datum<'{$to} 23:59:59' and f.szurestipusid=? and f.eljott=1 and f.`szurestipusid` IN (102, 85, 103, 101) and instr(f.megj, 'kiegészítő') order by datum", [$tipus["id"]])->fetchAll(PDO::FETCH_ASSOC);


            foreach ($reservations as $reservation) {
                $this->dataRow("A", $sor, [$reservation["datum"], $reservation["orvosnev"], $reservation["cegnev"], $reservation["nev"], $reservation["taj"], $reservation["szuldatum"], $reservation["megj"]]);
                $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
                $sor++;
            }
            $sor++;

        }

        $this->setAutoWidth(range('B','K'));
        $this->sheet->getColumnDimension('A')->setWidth(20);
    }

    private function _dokirexVizsgalatokLista($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Dokirex vizsgálat lista");
        $this->titleRow("A1", "Vizsgálatok - {$from} - {$to} (forrás: dokirex)");

        $data = DailyStatService::getDokirexVizsgalatok($from, $to);

        $sor = 3;
        $this->headingRow("A", $sor, ["Dátum", "Név", "Szakrendelés", "Orvos", "PaciensId", "Születési dátum", "Telephely", "Munkakör", "Korlázozás", "Alkalmasság", "Számla"]);
        $sor++;


        usort($data, function ($a, $b) {
            return strtotime($a["datum"]) - strtotime($b["datum"]);
        });

        foreach ($data as $item) {
            $this->dataRow("A", $sor, [$item["datum"], $item["nev"], $item["szakrendeles"], $item["orvos"], $item["paciensid"], $item["szuldatum"], $item["telephely"], $item["munkakor"], $item["korlatozas"], $item["alkalmassag"], $item["szamla"]]);
            $sor++;
        }

        $this->setAutoWidth(range('B','K'));
        $this->sheet->getColumnDimension('A')->setWidth(20);
    }


    public function _rtgLista($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("RTG lista");

        $this->titleRow("A1", " RTG lista {$from} - {$to}");

        $from.= " 00:00:00";
        $to.= " 23:59:59";

        $institutionNames = DicomService::getInstitutesQuery();
        $data = sql_query_common("select d.contentDate, d.patientName, d.patientBirthDate, d.patientOtherIDs, d.studyDescription, count(*) as db from dicom d where d.contentDate>? AND d.contentDate<=? and d.institutionName in ({$institutionNames}) GROUP BY d.patientName, d.patientBirthDate ORDER BY d.contentDate", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);

        //lista
        $sor = 5;
        $this->headingRow("A", $sor, ["Dátum", "Paciens", "Szül. dátum", "TAJ", "Cég", "db"]);

        $sor++;
        $total = $totalImage = 0;
        foreach ($data as $rowData) {
            $this->dataRow("A", $sor, [$rowData["contentDate"], $rowData["patientName"], $rowData["patientBirthDate"], $rowData["patientOtherIDs"], $rowData["studyDescription"], $rowData["db"]]);
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("left");
            $total ++;
            $totalImage += $rowData["db"];
            $sor++;
        }


        $sor = 3;
        $this->dataRow("A", $sor, ["BEJELENTKEZŐ Összes paciens: {$total}, összes kép: {$totalImage}"]);


        //dokirex lista
        $dokirexVizsgalatok = sql_query("select * from dokirex_vizsgalatok where datum>=? and datum<=? and instr(szakrendeles, '(RTG)') order by datum", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);

        $sor = 5;
        $this->headingRow("H", $sor, ["Dátum", "Paciens", "Szül. dátum", "TAJ", "Cég"]);

        $sor++;
        $total = $totalImage = 0;
        foreach ($dokirexVizsgalatok as $rowData) {
            $this->dataRow("H", $sor, [$rowData["datum"], $rowData["nev"], $rowData["szuldatum"], $rowData["paciensid"], $rowData["telephely"]]);
            $this->sheet->getStyle("K{$sor}")->getAlignment()->setHorizontal("left");
            $total ++;
            $sor++;
        }

        $this->sheet->getColumnDimension('A')->setWidth(20);
        $this->sheet->getColumnDimension('B')->setWidth(30);
        $this->sheet->getColumnDimension('C')->setWidth(15);
        $this->sheet->getColumnDimension('D')->setWidth(15);
        $this->sheet->getColumnDimension('E')->setWidth(20);

        $this->sheet->getColumnDimension('H')->setWidth(20);
        $this->sheet->getColumnDimension('I')->setWidth(30);
        $this->sheet->getColumnDimension('J')->setWidth(15);
        $this->sheet->getColumnDimension('K')->setWidth(15);
        $this->sheet->getColumnDimension('L')->setWidth(20);

        $sor = 3;
        $this->dataRow("H", $sor, ["DOKIREX Összes paciens: {$total}"]);

    }

    public function _cegEsOrvosStat($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }

        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Cég és orvos stat");

        $this->titleRow("A1", "Cég és orvos statisztika {$from} - {$to}");

        $from.= " 00:00:00";
        $to.= " 23:59:59";

        $companyStat = sql_query("SELECT c.megnev AS ceg, COUNT(*) AS foglalasok, SUM(IF(f.eljott=1, 1, 0)) AS eljott FROM foglalasok f
            LEFT JOIN cegek c ON c.id=f.cegid
            WHERE f.aktiv=1 AND f.nev NOT IN ('nincs név', 'ne foglalj', 'ebéd', 'ebédszünet')
            AND {$this->extraFilter} datum>? AND datum<=? AND f.`externalid`=''
            GROUP BY c.id, megnev ORDER BY c.megnev", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);

        $doctorStat = sql_query("SELECT o.nev AS orvos, COUNT(*) AS foglalasok, SUM(IF(eljott=1, 1, 0)) AS eljott FROM foglalasok f
            LEFT JOIN orvosok o ON o.id=f.orvosassigned
            WHERE {$this->extraFilter} f.aktiv=1 AND f.nev NOT IN ('nincs név', 'ne foglalj', 'ebéd', 'ebédszünet')
            AND datum>=? AND datum<=? AND f.`externalid`=''
            GROUP BY (IF (o.parentoid<>0, o.parentoid, o.id)) ORDER BY o.nev", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);


        //céges stat
        $sor = 3;
        $this->headingRow("A", $sor, ["Cég", "Foglalások", "Eljött"]);

        $sor++;
        $total = $totaleljott = 0;
        foreach ($companyStat as $rowData) {
            if (empty($rowData["ceg"])) {
                $rowData["ceg"] = "nincs megadva";
            }
            $this->dataRow("A", $sor, [$rowData["ceg"], $rowData["foglalasok"], $rowData["eljott"]]);
            $total += $rowData["foglalasok"];
            $totaleljott += $rowData["eljott"];
            $sor++;
        }

        $this->totalRow("A", $sor, ["Összesen:", $total, $totaleljott]);
        $this->sheet->getColumnDimension('A')->setWidth(40);

        //orvos stat
        $sor = 3;
        $this->headingRow("E", $sor, ["Orvos", "Foglalások", "Eljött"]);

        $sor++;
        $total = $totaleljott = 0;
        foreach ($doctorStat as $rowData) {
            $this->dataRow("E", $sor, [$rowData["orvos"], $rowData["foglalasok"], $rowData["eljott"]]);
            $total += $rowData["foglalasok"];
            $totaleljott += $rowData["eljott"];
            $sor++;
        }

        $this->totalRow("E", $sor, ["Összesen:", $total, $totaleljott]);
        $this->sheet->getStyle("E{$sor}:G{$sor}")->getFont()->setBold(true);

        $this->sheet->getColumnDimension('E')->setWidth(40);
    }

    private function _beosztasLista($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();

        $this->sheet->setTitle("Beosztás lista");

        if ($from != $to) {
            $this->titleRow("A1", "A beosztás lista csak napi lekérdezés esetén elérhető!");
            return;
        }

        $this->titleRow("A1", "Beosztások - {$from}");

        $data = WorkScheduleService::getDailySchedule($from);

        $sor = 3;
        $this->headingRow("A", $sor, ["Dolgozó", "Helyszín", "Tipus", "Időtartam", "Megjegyzés"]);
        $sor++;

        foreach ($data as $item) {
            $this->dataRow("A", $sor, [$item["workername"], $item["tipusnev"], $item["rolename"], date("H:i", strtotime($item["datumfrom"]))." - ".date("H:i", strtotime($item["datumto"])), $item["megj"]]);
            $sor++;
        }

        $this->setAutoWidth(range('A','K'));
        $this->sheet->getColumnDimension('A')->setWidth(20);
    }

    private function _fizetesLista($sheetId, $rawInput, $from, $to) {
        $salaryService = new SalaryCalculator();

        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Bérek");
        $this->titleRow("A1", "{$from} napon aktuális bérek");

        $salaryList = $salaryService->getAllSalaryDataForDay($from);

        $sor = 3;
        $this->headingRow("A", $sor, ["Dolgozó", "Összeg", "Elszámolás", "Érvényesség", "Megjegyzés"]);
        $sor++;

        foreach ($salaryList as $item) {
            $ervenyesseg = $item["datefrom"]." - ".$item["dateto"];
            if ($item["salarytype"] == "onetime") {
                $ervenyesseg = $item["datefrom"];
            }
            $this->dataRow("A", $sor, [$item["orvosnev"], $item["price"]." Ft", $salaryService->salaryTypes[$item["salarytype"]]["tag"], $ervenyesseg, $item["description"]]);
            $this->sheet->getStyle("B{$sor}")->getAlignment()->setHorizontal("right");
            $sor++;
        }

        $this->setAutoWidth(range('A','K'));
        $this->sheet->getColumnDimension('A')->setWidth(20);
    }


    private function _orvosWorkHoursGroup($sor, $monthStat) {
        $sor+=2;
        $this->headingRow("J", $sor, ["Hónap", "Rendelési óraszám", "Paciensek", "Paciens / óra"]);
        $sor++;

        foreach ($monthStat as $honap => $stat) {
            $paciensPerHour = round($stat["paciensek"] / $stat["hours"], 1);
            $this->dataRow("J", $sor, [substr($honap, 0, 4)." ".date("F", strtotime("{$honap}-01")), round($stat["hours"],1), $stat["paciensek"], $paciensPerHour]);
            $sor++;
        }

    }

    public function _orvosWorkHours($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }

        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Munkaórák");

        $this->titleRow("A1", "Orvosok munkaórái {$from} - {$to}");

        $from.= " 00:00:00";
        $to.= " 23:59:59";

        $workHoursArray = sql_query("SELECT orvosassigned as orvosid, o.nev AS orvosnev, f.`helyszinid`, h.cim AS helyszin, DATE(datum) AS datum, MIN(datum) AS begindate, AVG(rinterval) as hossz, DATE_ADD(MAX(datum), INTERVAL rinterval MINUTE) AS enddate, f.eljott,
            SUM(IF(f.taj='', 0, 1)) AS paciensek, SUM(f.eljott) AS eljottek, COUNT(*) AS total
            FROM foglalasok f 
            LEFT JOIN orvosok o ON o.id=f.orvosassigned
            LEFT JOIN helyszinek h ON h.id = f.helyszinid
            WHERE {$this->extraFilter} datum>=? AND datum<=? and orvosassigned<>0 and trim(f.taj)<>'' GROUP BY f.orvosassigned, DATE(f.datum), f.helyszinid", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);

        $sor = 3;

        $sor++;
        $totalHours = $totalPatients = $totalEljottek = 0;
        $lastDoctor = 0;
        $lastRow = 0;
        $monthStat = [];
        foreach ($workHoursArray as $rowData) {
            if ($rowData["orvosid"] != $lastDoctor) {
                $lastDoctor = $rowData["orvosid"];
                if ($totalHours != 0) {
                    $this->totalRow("A", $sor, ["Összesen:", "", "", "", "", round($totalHours, 1), $totalPatients, round($totalPatients/$totalHours, 1)]);
                    $totalHours = $totalPatients = $totalEljottek = 0;
                    $sor+=2;

                    $this->_orvosWorkHoursGroup($lastRow, $monthStat);
                }
                $lastRow = $sor;
                $monthStat = [];
                $this->titleRow("A{$sor}", "{$rowData["orvosnev"]} napi és havi bontás");
                $sor+=2;
                $this->headingRow("A", $sor, ["Nap", "Eleje", "Vége", "Hossz", "Helyszín", "Munkaóra", "Paciensek", "Paciens/óra"]);
                $sor++;
            }

            $hours = round((strtotime($rowData["enddate"]) - strtotime($rowData["begindate"]))/3600, 1);
            $hoursPontos = (strtotime($rowData["enddate"]) - strtotime($rowData["begindate"]))/3600;
            $this->dataRow("A", $sor, [$rowData["datum"], date("H:i", strtotime($rowData["begindate"])), date("H:i", strtotime($rowData["enddate"])), round($rowData["hossz"])." perc", $rowData["helyszin"], $hours, $rowData["paciensek"], round($rowData["paciensek"]/$hoursPontos, 1)]);
            //$total += $rowData["foglalasok"];
            $totalPatients += $rowData["paciensek"];
            $totalEljottek += $rowData["eljottek"];
            $totalHours += $hoursPontos;

            $month = date("Y-m", strtotime($rowData["datum"]));
            if (!isset($monthStat[$month]["hours"])) {
                $monthStat[$month]["hours"] = 0;
            }
            if (!isset($monthStat[$month]["paciensek"])) {
                $monthStat[$month]["paciensek"] = 0;
            }
            $monthStat[$month]["hours"] += $hoursPontos;
            $monthStat[$month]["paciensek"] += $rowData["paciensek"];

            $sor++;
        }

        if ($totalHours != 0) {
            $this->totalRow("A", $sor, ["Összesen:", "", "", "", "", round($totalHours, 1), $totalPatients, round($totalPatients/$totalHours, 1)]);
            $this->_orvosWorkHoursGroup($lastRow, $monthStat);
        }



        //$this->totalRow("A", $sor, ["Összesen:", $totalHours, $totalPatients]);
        //$this->sheet->getColumnDimension('A')->setWidth(40);

        //$this->totalRow("E", $sor, ["Összesen:", $total, $totaleljott]);
        //$this->sheet->getStyle("E{$sor}:G{$sor}")->getFont()->setBold(true);

        $this->setAutoWidth(range('B','T'));
        $this->sheet->getColumnDimension('A')->setWidth(20);
    }

    public function _laborLeletLista($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Laborlelet lista");

        $this->titleRow("A1", " Laborlelet lista {$from} - {$to}");

        $from.= " 00:00:00";
        $to.= " 23:59:59";

        $data = sql_query("select r.resultdate, r.provider, nev, taj, szuldatum from labrequests r where resultdate>? AND resultdate<=? and status='done' ORDER BY resultdate", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);


        //lista
        $sor = 5;
        $this->headingRow("A", $sor, ["Dátum", "Szolgáltató", "Paciens", "Szül. dátum", "TAJ"]);

        $sor++;
        $total = 0;
        foreach ($data as $rowData) {
            $provider = $rowData["provider"];
            if (substr_count($provider, "@")) {
                $provider = "SynLab";
            }
            $this->dataRow("A", $sor, [$rowData["resultdate"], $provider, $rowData["nev"], $rowData["szuldatum"], (string)$rowData["taj"]]);
            $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
            $total ++;
            $sor++;
        }


        $sor = 3;
        $this->dataRow("A", $sor, ["Összes lelet: {$total}"]);

        $this->sheet->getColumnDimension('A')->setWidth(20);
        $this->sheet->getColumnDimension('B')->setWidth(20);
        $this->sheet->getColumnDimension('C')->setWidth(30);
        $this->sheet->getColumnDimension('D')->setWidth(15);
        $this->sheet->getColumnDimension('E')->setWidth(15);
    }

    public function napiStat($from, $to) {
        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        //$result = $this->generateDailyStat($_POST["dayFrom"], $_POST["dayTo"]);
        //$_SESSION["lastgeneratedstat"]["finalresult"] = json_encode($result["result"]);
        //$_SESSION["lastgeneratedstat"]["dokirexvizsgalatokresult"] = json_encode($this->getDokirexVizsgalatok($_POST["dayFrom"], $_POST["dayTo"]));
        //$_SESSION["lastgeneratedstat"]["beosztasresult"] = json_encode(WorkScheduleService::getDailySchedule($_POST["dayFrom"]));

        $sheetId = 0;
        try {
            //$this->_orvosWorkHours($sheetId++, $from, $to);
            $this->_bejelentkezoFoglalasokLista($sheetId++, $from, $to);
            $this->_dokirexVizsgalatokLista($sheetId++, $from, $to);
            $this->_rtgLista($sheetId++, $from, $to);
            $this->_laborLeletLista($sheetId++, $from, $to);
            $this->_cegEsOrvosStat($sheetId++, $from, $to);
            $this->_bejelentkezoEljottStat($sheetId++, $from, $to);
            $this->_bejelentkezoNemEljottLista($sheetId++, $from, $to);
            $this->_orvosWorkHours($sheetId++, $from, $to);
            //$this->_fizetesLista($sheetId++, $rawInput, $from, $to);
        } catch (\Exception $e) {
            //valami hibakezelés...
        }

        $this->spreadSheet->setActiveSheetIndex(0);
    }

    public function rawWeeklyStat($from, $to) {
        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        $sheetId = 0;
        try {
            $this->_keltexmedSources($sheetId++, $from, $to);
            $this->_hmmSources($sheetId++, $from, $to);
            $this->_hmmGyorSources($sheetId++, $from, $to);
        } catch (\Exception $e) {
            //valami hibakezelés...
        }

        $this->spreadSheet->setActiveSheetIndex(0);
    }

    public function elojegyzesTable($from, $to) {
        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $this->_bejelentkezoFoglalasokLista(0, $from, $to, false);
        $this->spreadSheet->setActiveSheetIndex(0);
    }


    const TORVENYSZEK_DOCTOR_ID = 354;
    const TORVENYSZEK_COMPANY_ID = 56;

    public function torvenyszekStat() {
        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Törvényszék óraszámok");

        $this->titleRow("I1", "Törvényszék óra és paciens statisztika napi bontásban");

        $weekStat = [];
        $monthStat = [];

        $statDays = sql_query("SELECT DATE(datum) as nap, MIN(TIME(datum)) as mintime, MAX(TIME(DATE_ADD(f.datum, INTERVAL f.rinterval MINUTE))) as maxtime, (UNIX_TIMESTAMP(MAX(DATE_ADD(f.datum, INTERVAL f.rinterval MINUTE))) - UNIX_TIMESTAMP(MIN(datum)))/3600 AS rendelesora, COUNT(*) AS paciensek, SUM(eljott) AS eljottek, GROUP_CONCAT(cegid) AS cegek 
            FROM foglalasok f
            WHERE f.orvosassigned=? AND !INSTR(f.nev,'nincs név') AND !INSTR(f.nev,'ebéd') AND !INSTR(f.nev,'ne foglal') AND DATE(f.datum)<DATE(NOW()) and f.datum>'2022-01-01 00:00:00'
            GROUP BY DATE(datum)
            ORDER BY datum", [self::TORVENYSZEK_DOCTOR_ID])->fetchAll(PDO::FETCH_ASSOC);

        $sor = 3;
        $this->headingRow("I", $sor, ["Nap", "Kezdés", "Vége", "Rendelési óraszám", "Paciensek", "Ebből Törvényszékes paciens", "Egyéb cég paciense", "Paciens / óra"]);

        $sor++;



        foreach ($statDays as $statDay) {
            $paciensPerHour = round($statDay["paciensek"] / $statDay["rendelesora"], 1);
            $companyCounts = array_count_values(explode(",", $statDay["cegek"]));
            $torvenyszekPaciensCount = $companyCounts[self::TORVENYSZEK_COMPANY_ID];
            if (empty($torvenyszekPaciensCount)) {
                $torvenyszekPaciensCount = 0;
            }

            $month = date("Y-m", strtotime($statDay["nap"]));
            $monthStat[$month]["hours"] += $statDay["rendelesora"];
            $monthStat[$month]["paciensek"] += $statDay["paciensek"];
            $monthStat[$month]["torvenyszekpaciensek"] += $torvenyszekPaciensCount;

            $week = date("Y-W", strtotime($statDay["nap"]));
            $weekStat[$week]["hours"] += $statDay["rendelesora"];
            $weekStat[$week]["paciensek"] += $statDay["paciensek"];
            $weekStat[$week]["torvenyszekpaciensek"] += $torvenyszekPaciensCount;

            $this->dataRow("I", $sor, ["{$statDay["nap"]} ".date("l", strtotime($statDay["nap"])), substr($statDay["mintime"], 0, 5), substr($statDay["maxtime"], 0, 5), round($statDay["rendelesora"],1), $statDay["paciensek"], $torvenyszekPaciensCount, ($statDay["paciensek"] - $companyCounts[self::TORVENYSZEK_COMPANY_ID]), $paciensPerHour]);
            $sor++;
        }


        $this->titleRow("A1", "Törvényszék óra és paciens statisztika havi bontásban");
        $sor = 3;

        $this->headingRow("A", $sor, ["Hónap", "Rendelési óraszám", "Paciensek", "Ebből Törvényszékes paciens", "Egyéb cég paciense", "Paciens / óra"]);
        $sor++;

        foreach ($monthStat as $honap => $stat) {
            $paciensPerHour = round($stat["paciensek"] / $stat["hours"], 1);
            $this->dataRow("A", $sor, [substr($honap, 0, 4)." ".date("F", strtotime("{$honap}-01")), round($stat["hours"]), $stat["paciensek"], $stat["torvenyszekpaciensek"], ($stat["paciensek"] - $stat["torvenyszekpaciensek"]), $paciensPerHour]);
            $sor++;
        }

        $sor++;
        $this->titleRow("A{$sor}", "Törvényszék óra és paciens statisztika heti bontásban");
        $sor++;
        $sor++;
        $this->headingRow("A", $sor, ["Hét", "Rendelési óraszám", "Paciensek", "Ebből Törvényszékes paciens", "Egyéb cég paciense", "Paciens / óra"]);
        $sor++;

        foreach ($weekStat as $week => $stat) {
            $paciensPerHour = round($stat["paciensek"] / $stat["hours"], 1);
            $this->dataRow("A", $sor, [substr($week, 0, 4)." ".substr($week, 5).". hét", round($stat["hours"]), $stat["paciensek"], $stat["torvenyszekpaciensek"], ($stat["paciensek"] - $stat["torvenyszekpaciensek"]), $paciensPerHour]);
            $sor++;
        }

        $this->setAutoWidth(range('B','F'));
        $this->sheet->getColumnDimension('A')->setWidth(25);

        $this->setAutoWidth(range('J','P'));
        $this->sheet->getColumnDimension('I')->setWidth(25);
    }

    public function generateXlsxFromArray($array,$startCell=null,$lastCell=null,$setAlignLeft=[]){
        //Declare variables and utilites
        $columnNames = array_keys($array[0]);
        $row = 1;
        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->headingRow("A", $row, $columnNames);

        //Generate data rows
        for($i=0;$row<=count($array);$i++){
            $row++;
            $values = array_values($array[$i]);
            $this->dataRow("A", $row, $values);

            if(!empty($setAlignLeft)){
                foreach($setAlignLeft as $col){
                    $this->sheet->getStyle($col.$row)->getAlignment()->setHorizontal("left");
                }
            }
        }

        if($startCell && $lastCell){
            $this->setAutoWidth(range($startCell, $lastCell));
        }else{
            $this->setAutoWidth(range('A', 'E'));
        }

        //END
        $this->spreadSheet->setActiveSheetIndex(0);
    }


    public function suzukiGHCRegistrationList() {
        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Regisztrációk");
        $this->titleRow("A1", "Suzuki GHC Regisztrációk");

        $sor = 3;

        $registrations = sql_query("SELECT regtime, nev, taj, torzsszam, otp_penztar, szallitas FROM felhasznalok f WHERE f.cegid=? order by regtime desc", [CompanyService::SUZUKI_GHC_ID])->fetchAll(PDO::FETCH_ASSOC);

        $this->headingRow("A", $sor, ["Regisztráció ideje", "", "Név", "Törzsszám", "TAJ", "OTP Egészségpénztár", "Szállítás"]);
        $sor++;

        foreach ($registrations as $registration) {
            $this->dataRow("A", $sor, [date("Y-m-d", strtotime($registration["regtime"])), date("H:i", strtotime($registration["regtime"])), $registration["nev"], $registration["torzsszam"], $registration["taj"], $registration["otp_penztar"]==1?"igen":"", $registration["szallitas"]==1?"igen":""]);
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("left");
            $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
            $sor++;
        }

        $this->setAutoWidth(range('B','L'));
        $this->sheet->getColumnDimension('A')->setWidth(20);

        $this->spreadSheet->setActiveSheetIndex(0);
    }


    public function auchanReservationStat() {
        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Helyszin bontás");
        $this->titleRow("A1", "Auchan foglalások");

        $sor = 3;

        $registrations = sql_query("SELECT f.cegid, f.helyszinid, h.cim, COUNT(*) AS hany, MIN(DATE(regdatum)) AS mindate, MAX(DATE(regdatum)) AS maxdate, sum(f.eljott) as eljottek FROM foglalasok f 
            LEFT JOIN helyszinek h ON h.id=f.helyszinid
            WHERE f.foglalta='labshop' AND f.nev<>'nincs név' AND (f.cegid=? or f.cegid=?)
            GROUP BY f.helyszinid ORDER BY cim", [CompanyService::AUCHAN_ID, 602])->fetchAll(PDO::FETCH_ASSOC);

        $this->headingRow("A", $sor, ["Helyszín", "Foglalások", "Eljöttek", "", "Vásárlások"]);
        $sor++;

        $allServiceTotals = $allServiceTotalsE = [];
        $totalReservation = $totalEljottReservation = 0;
        foreach ($registrations as $registration) {
            $serviceTotals = $serviceTotalsE = [];

            $cartItems = sql_query("SELECT f.id, f.nev, f.eljott, i.product_id, IF(i.type='package', cs.name, IF(i.type='exam', a.megnev, IF(i.type='item', t.name, ''))) AS productname, i.type, i.price FROM foglalasok f 
                LEFT JOIN cart_item i ON i.`reservation_id`=f.id
                LEFT JOIN synlab_labor_csomagok cs ON cs.id=i.product_id
                LEFT JOIN synlab_labor_tetelek t ON t.id=i.product_id
                LEFT JOIN arak a ON a.id=i.product_id
                WHERE f.foglalta='labshop' AND f.nev<>'nincs név' AND f.cegid=? AND helyszinid=?
                ORDER BY CONCAT(i.product_id,i.type)<>'113package', CONCAT(i.product_id,i.type)<>'13exam'", [$registration["cegid"], $registration["helyszinid"]])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cartItems as $cartItem) {
                $productName = trim("{$cartItem["productname"]}");

                if (!empty($productName)) {
                    if (isset($serviceTotals[$productName])) {
                        $serviceTotals[$productName][1]++;
                        $serviceTotalsE[$productName][1] += $cartItem["eljott"];
                    } else {
                        $serviceTotals[$productName][0] = $cartItem["price"];
                        $serviceTotals[$productName][1] = 1;
                        $serviceTotalsE[$productName][1] = $cartItem["eljott"];
                    }
                    if (isset($allServiceTotals[$productName])) {
                        $allServiceTotals[$productName][1]++;
                        $allServiceTotalsE[$productName][1] += $cartItem["eljott"];
                    } else {
                        $allServiceTotals[$productName][0] = $cartItem["price"];
                        $allServiceTotals[$productName][1] = 1;
                        $allServiceTotalsE[$productName][1] = $cartItem["eljott"];
                    }
                }
            }

            $megjCell = [];
            foreach ($serviceTotals as $service => $value) {
                $megjCell[] = "{$service} {$value[1]} db";
            }

            $this->dataRow("A", $sor, [$registration["cim"], $registration["hany"], $registration["eljottek"], "", implode(", ", $megjCell)]);
            //$this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("left");
            //$this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
            $totalReservation+= $registration["hany"];
            $totalEljottReservation+= $registration["eljottek"];
            $sor++;
        }
        $this->dataRow("A", $sor, ["Összesen", $totalReservation, $totalEljottReservation, "", ""]);
        $this->sheet->getStyle("A{$sor}")->getFont()->setBold(true);
        $this->sheet->getStyle("B{$sor}")->getFont()->setBold(true);
        $this->sheet->getStyle("C{$sor}")->getFont()->setBold(true);

        $sor+=2;
        $this->titleRow("A{$sor}", "Vásárolt szolgáltatások összesen");
        $sor+=2;

        $this->headingRow("A", $sor, ["Szolgáltatás", "ár", "db", "db (eljött)", "Összeg", "Összeg (eljött)"]);
        $sor++;

        $totalPrice = $totalServices = $totalPriceE = $totalServicesE = 0;
        foreach ($allServiceTotals as $service => $value) {
            $osszeg = $value[0] * $value[1];
            $osszegE = $value[0] * $allServiceTotalsE[$service][1];

            $this->dataRow("A", $sor, [$service, $value[0] . " Ft", $value[1], $allServiceTotalsE[$service][1], $osszeg, $osszegE]);
            $this->sheet->getStyle("B{$sor}")->getAlignment()->setHorizontal("right");
            //$this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
            $sor++;
            $totalPrice += $osszeg;
            $totalPriceE += $osszegE;
            $totalServices += $value[1];
            $totalServicesE += $allServiceTotalsE[$service][1];
        }

        $this->dataRow("A", $sor, ["Összesen", $totalPrice." Ft", $totalServices, $totalServicesE, $totalPrice, $totalPriceE]);
        $this->sheet->getStyle("B{$sor}")->getAlignment()->setHorizontal("right");

        $this->sheet->getStyle("A{$sor}")->getFont()->setBold(true);
        $this->sheet->getStyle("B{$sor}")->getFont()->setBold(true);
        $this->sheet->getStyle("C{$sor}")->getFont()->setBold(true);
        $this->sheet->getStyle("D{$sor}")->getFont()->setBold(true);
        $this->sheet->getStyle("E{$sor}")->getFont()->setBold(true);
        $this->sheet->getStyle("F{$sor}")->getFont()->setBold(true);

        $sor++;


        $this->setAutoWidth(range('A','D'));
        $this->sheet->getColumnDimension('E')->setWidth(20);
        $this->sheet->getColumnDimension('F')->setWidth(20);

        $this->spreadSheet->setActiveSheetIndex(0);
    }

    public function auchanReservationStat2() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Minden");
        $this->titleRow("A1", "Auchan foglalások");

        $sor = 3;

        $registrations = sql_query("SELECT DATE(f.datum) AS datum, h.cim AS helyszin, t.megnev AS tipus, COUNT(*) AS total, SUM(eljott) AS eljott, f.cegid, f.helyszinid FROM foglalasok f 
            LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
            LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
            WHERE f.cegid IN ('338','602') AND f.datum>'2024-10-01 00:00:00' 
            GROUP BY DATE(datum), f.`helyszinid`, t.megnev", [CompanyService::AUCHAN_ID, 602])->fetchAll(PDO::FETCH_ASSOC);

        $this->headingRow("A", $sor, ["Dátum", "Helyszín", "Szolgáltatás", "Total", "Eljött", "Ár/db", "Ár * eljött"]);
        $sor++;

        $allServiceTotals = $allServiceTotalsE = [];
        $totalReservation = $totalEljottReservation = 0;
        foreach ($registrations as $registration) {
            $this->dataRow("A", $sor, [$registration["datum"], $registration["helyszin"], $registration["tipus"], $registration["total"], $registration["eljott"]]);
            //$this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("left");
            //$this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
            //$totalReservation+= $registration["hany"];
            //$totalEljottReservation+= $registration["eljottek"];
            $sor++;



            if ($registration["tipus"] == "Laborvizsgálatok") {
                $serviceTotals = $serviceTotalsE = [];

                $cartItems = sql_query("SELECT f.id, f.nev, f.eljott, i.product_id, IF(i.type='package', cs.name, IF(i.type='exam', a.megnev, IF(i.type='item', t.name, ''))) AS productname, i.type, i.price FROM foglalasok f 
                LEFT JOIN cart_item i ON i.`reservation_id`=f.id
                LEFT JOIN synlab_labor_csomagok cs ON cs.id=i.product_id
                LEFT JOIN synlab_labor_tetelek t ON t.id=i.product_id
                LEFT JOIN arak a ON a.id=i.product_id
                WHERE f.foglalta='labshop' AND f.nev<>'nincs név' AND f.cegid=? AND helyszinid=? AND CONCAT(i.product_id,i.type)<>'113package' AND CONCAT(i.product_id,i.type)<>'13exam'
                ORDER BY CONCAT(i.product_id,i.type)<>'113package', CONCAT(i.product_id,i.type)<>'13exam'", [$registration["cegid"], $registration["helyszinid"]])->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cartItems as $cartItem) {
                    $productName = trim("{$cartItem["productname"]}");

                    if (!empty($productName)) {
                        if (isset($serviceTotals[$productName])) {
                            $serviceTotals[$productName][1]++;
                            $serviceTotalsE[$productName][1] += $cartItem["eljott"];
                        } else {
                            $serviceTotals[$productName][0] = $cartItem["price"];
                            $serviceTotals[$productName][1] = 1;
                            $serviceTotalsE[$productName][1] = $cartItem["eljott"];
                        }
                        if (isset($allServiceTotals[$productName])) {
                            $allServiceTotals[$productName][1]++;
                            $allServiceTotalsE[$productName][1] += $cartItem["eljott"];
                        } else {
                            $allServiceTotals[$productName][0] = $cartItem["price"];
                            $allServiceTotals[$productName][1] = 1;
                            $allServiceTotalsE[$productName][1] = $cartItem["eljott"];
                        }
                    }
                }

                foreach ($serviceTotals as $service => $value) {
                    $this->dataRow("A", $sor, [$registration["datum"], $registration["helyszin"], $service, $value[1], $serviceTotalsE[$service][1], $value[0], $value[0] * $serviceTotalsE[$service][1]]);
                    $sor++;
                }
            }



        }

        //$this->dataRow("A", $sor, ["Összesen", $totalReservation, $totalEljottReservation, "", ""]);
        //$this->sheet->getStyle("A{$sor}")->getFont()->setBold(true);
        //$this->sheet->getStyle("B{$sor}")->getFont()->setBold(true);
        //$this->sheet->getStyle("C{$sor}")->getFont()->setBold(true);


        $this->setAutoWidth(range('A','D'));
        $this->sheet->getColumnDimension('E')->setWidth(20);
        $this->sheet->getColumnDimension('F')->setWidth(20);

        $this->spreadSheet->setActiveSheetIndex(0);
    }


    public function auchanReservationStat3() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Minden");
        $this->titleRow("A1", "Auchan foglalások");

        $sor = 3;

        $registrations = sql_query("SELECT DATE(f.datum) AS datum, h.cim AS helyszin, t.megnev AS tipus, COUNT(*) AS total, SUM(eljott) AS eljott, f.cegid, f.helyszinid FROM foglalasok f 
            LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
            LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
            WHERE f.cegid IN ('338','602') AND f.datum>'2024-10-01 00:00:00' 
            GROUP BY DATE(datum), f.`helyszinid`, t.megnev", [CompanyService::AUCHAN_ID, 602])->fetchAll(PDO::FETCH_ASSOC);

        $this->headingRow("A", $sor, ["Dátum", "Helyszín", "Szolgáltatás", "Total", "Eljött", "Ár/db", "Ár * eljött"]);
        $sor++;

        $allServiceTotals = $allServiceTotalsE = [];
        $totalReservation = $totalEljottReservation = 0;
        foreach ($registrations as $registration) {
            $this->dataRow("A", $sor, [$registration["datum"], $registration["helyszin"], $registration["tipus"], $registration["total"], $registration["eljott"]]);
            //$this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("left");
            //$this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
            //$totalReservation+= $registration["hany"];
            //$totalEljottReservation+= $registration["eljottek"];
            $sor++;



            if ($registration["tipus"] == "Laborvizsgálatok") {
                $serviceTotals = $serviceTotalsE = [];

                $cartItems = sql_query("SELECT f.id, f.nev, f.eljott, i.product_id, IF(i.type='package', cs.name, IF(i.type='exam', a.megnev, IF(i.type='item', t.name, ''))) AS productname, i.type, i.price FROM foglalasok f 
                LEFT JOIN cart_item i ON i.`reservation_id`=f.id
                LEFT JOIN synlab_labor_csomagok cs ON cs.id=i.product_id
                LEFT JOIN synlab_labor_tetelek t ON t.id=i.product_id
                LEFT JOIN arak a ON a.id=i.product_id
                WHERE f.foglalta='labshop' AND f.nev<>'nincs név' AND f.cegid=? AND helyszinid=? AND CONCAT(i.product_id,i.type)<>'113package' AND CONCAT(i.product_id,i.type)<>'13exam'
                ORDER BY CONCAT(i.product_id,i.type)<>'113package', CONCAT(i.product_id,i.type)<>'13exam'", [$registration["cegid"], $registration["helyszinid"]])->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cartItems as $cartItem) {
                    $productName = trim("{$cartItem["productname"]}");

                    if (!empty($productName)) {
                        if (isset($serviceTotals[$productName])) {
                            $serviceTotals[$productName][1]++;
                            $serviceTotalsE[$productName][1] += $cartItem["eljott"];
                        } else {
                            $serviceTotals[$productName][0] = $cartItem["price"];
                            $serviceTotals[$productName][1] = 1;
                            $serviceTotalsE[$productName][1] = $cartItem["eljott"];
                        }
                        if (isset($allServiceTotals[$productName])) {
                            $allServiceTotals[$productName][1]++;
                            $allServiceTotalsE[$productName][1] += $cartItem["eljott"];
                        } else {
                            $allServiceTotals[$productName][0] = $cartItem["price"];
                            $allServiceTotals[$productName][1] = 1;
                            $allServiceTotalsE[$productName][1] = $cartItem["eljott"];
                        }
                    }
                }

                foreach ($serviceTotals as $service => $value) {
                    $this->dataRow("A", $sor, [$registration["datum"], $registration["helyszin"], $service, $value[1], $serviceTotalsE[$service][1], $value[0], $value[0] * $serviceTotalsE[$service][1]]);
                    $sor++;
                }
            }



        }

        //$this->dataRow("A", $sor, ["Összesen", $totalReservation, $totalEljottReservation, "", ""]);
        //$this->sheet->getStyle("A{$sor}")->getFont()->setBold(true);
        //$this->sheet->getStyle("B{$sor}")->getFont()->setBold(true);
        //$this->sheet->getStyle("C{$sor}")->getFont()->setBold(true);


        $this->setAutoWidth(range('A','D'));
        $this->sheet->getColumnDimension('E')->setWidth(20);
        $this->sheet->getColumnDimension('F')->setWidth(20);

        $this->spreadSheet->setActiveSheetIndex(0);
    }

    public function auchanReservationStatForAuchan() {
        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Helyszin bontás");
        $this->titleRow("A1", "Auchan foglalások");

        $sor = 3;

        $registrations = sql_query("SELECT f.cegid, f.helyszinid, h.cim, COUNT(*) AS hany, MIN(DATE(regdatum)) AS mindate, MAX(DATE(regdatum)) AS maxdate FROM foglalasok f 
            LEFT JOIN helyszinek h ON h.id=f.helyszinid
            WHERE f.foglalta='labshop' AND f.nev<>'nincs név' AND f.cegid=? 
            GROUP BY f.helyszinid ORDER BY cim", [CompanyService::AUCHAN_ID])->fetchAll(PDO::FETCH_ASSOC);

        $this->headingRow("A", $sor, ["Helyszín", "Nagylabor vizsgálatra foglalt időpontok száma", "EKG vizsgálatra foglalt időpontok száma", "Összes foglalás"]);
        $sor++;

        $allLabor = $allEKG = $totalReservation = 0;

        foreach ($registrations as $registration) {
            $locationLabor = $locationEKG = 0;

            $cartItems = sql_query("SELECT f.id, f.nev, i.product_id, IF(i.type='package', cs.name, IF(i.type='exam', a.megnev, IF(i.type='item', t.name, ''))) AS productname, i.type, i.price FROM foglalasok f 
                LEFT JOIN cart_item i ON i.`reservation_id`=f.id
                LEFT JOIN synlab_labor_csomagok cs ON cs.id=i.product_id
                LEFT JOIN synlab_labor_tetelek t ON t.id=i.product_id
                LEFT JOIN arak a ON a.id=i.product_id
                WHERE f.foglalta='labshop' AND f.nev<>'nincs név' AND f.cegid=? AND helyszinid=?
                ORDER BY CONCAT(i.product_id,i.type)<>'113package', CONCAT(i.product_id,i.type)<>'13exam'", [$registration["cegid"], $registration["helyszinid"]])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cartItems as $cartItem) {
                if ($cartItem["type"] == "exam" && $cartItem["product_id"] == 13) {
                    $allEKG++;
                    $locationEKG++;
                }
                if ($cartItem["type"] == "package" && $cartItem["product_id"] == 113) {
                    $allLabor++;
                    $locationLabor++;
                }
            }

            $this->dataRow("A", $sor, [$registration["cim"], $locationLabor, $locationEKG, $registration["hany"]]);
            //$this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("left");
            //$this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("left");
            $totalReservation+= $registration["hany"];
            $sor++;
        }
        $this->dataRow("A", $sor, ["Összesen", $allLabor, $allEKG, $totalReservation]);
        $this->sheet->getStyle("A{$sor}")->getFont()->setBold(true);
        $this->sheet->getStyle("B{$sor}")->getFont()->setBold(true);
        $this->sheet->getStyle("C{$sor}")->getFont()->setBold(true);
        $this->sheet->getStyle("D{$sor}")->getFont()->setBold(true);

        $this->setAutoWidth(range('A','L'));
        //$this->sheet->getColumnDimension('A')->setWidth(20);

        $this->spreadSheet->setActiveSheetIndex(0);
    }

    public function loadPatientDataExcel($fileName,$sheetIndex=null):array{
        $this->read($fileName);

        if($sheetIndex){
            $this->sheet = $this->spreadSheet->getSheet($sheetIndex);
        }else{
            $this->sheet = $this->spreadSheet->getActiveSheet();
        }
        
        $rows = $this->sheet->toArray();
        return $rows;
    }

    public function checkSheets():array{
        return $this->spreadSheet->getSheetNames();
    }



    private function _keltexmedSources($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("KeltexMed");
        $this->titleRow("A1", "Magán foglalások forrás alapján Keltexmed - ".date("Y", strtotime($from)));
        $this->dataRow("A", 2, ["Jelentés készült: ".date("Y.m.d")]);
        $sor = 5;

        $this->headingRow("A", $sor, ["Forrás", "Összesen", "2025 jan", "2025 feb", "2025 már", "2025 ápr", "2025 máj", "2025 jun", "2025 júl", "2025 aug", "2025 szep", "2025 okt", "2025 nov", "2025 dec"]);
        $sor++;

        $reservations = sql_query("SELECT calcfoglalta AS forras, COUNT(*) AS total
            ,SUM(IF (MONTH(datum)=1, 1, 0)) AS jan
            ,SUM(IF (MONTH(datum)=2, 1, 0)) AS feb
            ,SUM(IF (MONTH(datum)=3, 1, 0)) AS marc
            ,SUM(IF (MONTH(datum)=4, 1, 0)) AS apr
            ,SUM(IF (MONTH(datum)=5, 1, 0)) AS maj
            ,SUM(IF (MONTH(datum)=6, 1, 0)) AS jun
            ,SUM(IF (MONTH(datum)=7, 1, 0)) AS jul
            ,SUM(IF (MONTH(datum)=8, 1, 0)) AS aug
            ,SUM(IF (MONTH(datum)=9, 1, 0)) AS szep
            ,SUM(IF (MONTH(datum)=10, 1, 0)) AS okt
            ,SUM(IF (MONTH(datum)=11, 1, 0)) AS nov
            ,SUM(IF (MONTH(datum)=12, 1, 0)) AS 'dec'
            
            FROM (SELECT datum, foglalta, szurestipusid,
            IF (foglalta='', 'bejelentkezo', 
            
            IF (foglalta IN ('', 'labshop', 'foglaljorvost', 'union', 'webpage', 'webshop', 'keltexmedwww'), foglalta, 'admin')) AS calcfoglalta
            FROM keltexmed.foglalasok 
            WHERE datum>'2025-01-01 00:00:00' AND datum<'2025-12-31 23:55:55' AND (foglalta='foglaljorvost' OR eljott=1) AND helyszinid IN (292,328) AND eljott=1 AND cegid IN (11,392,606)) a
            
            LEFT JOIN keltexmed.szurestipusok t ON t.id=a.szurestipusid
            
            GROUP BY calcfoglalta ORDER BY t.megnev, calcfoglalta", [])->fetchAll(PDO::FETCH_ASSOC);


        foreach ($reservations as $reservation) {
            $this->dataRow("A", $sor, [$reservation["forras"], $reservation["total"], $reservation["jan"], $reservation["feb"], $reservation["marc"], $reservation["apr"], $reservation["maj"], $reservation["jun"], $reservation["jul"], $reservation["aug"], $reservation["szep"], $reservation["okt"], $reservation["nov"], $reservation["dec"]]);
            $this->sheet->getStyle("B{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("C{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("F{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("G{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("H{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("I{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("J{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("K{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("L{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("M{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("N{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("O{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("P{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("Q{$sor}")->getAlignment()->setHorizontal("right");
            $sor++;
        }

        $sor++;
        $this->titleRow("A{$sor}", "Magán foglalások orvos alapján Keltexmed - ".date("Y", strtotime($from)));
        $sor += 2;

        $this->headingRow("A", $sor, ["Orvos", "Összesen", "2025 jan", "2025 feb", "2025 már", "2025 ápr", "2025 máj", "2025 jun", "2025 júl", "2025 aug", "2025 szep", "2025 okt", "2025 nov", "2025 dec"]);
        $sor++;

        $reservations = sql_query("SELECT o.nev AS orvos, COUNT(*) AS total
            ,SUM(IF (MONTH(datum)=1, 1, 0)) AS jan
            ,SUM(IF (MONTH(datum)=2, 1, 0)) AS feb
            ,SUM(IF (MONTH(datum)=3, 1, 0)) AS marc
            ,SUM(IF (MONTH(datum)=4, 1, 0)) AS apr
            ,SUM(IF (MONTH(datum)=5, 1, 0)) AS maj
            ,SUM(IF (MONTH(datum)=6, 1, 0)) AS jun
            ,SUM(IF (MONTH(datum)=7, 1, 0)) AS jul
            ,SUM(IF (MONTH(datum)=8, 1, 0)) AS aug
            ,SUM(IF (MONTH(datum)=9, 1, 0)) AS szep
            ,SUM(IF (MONTH(datum)=10, 1, 0)) AS okt
            ,SUM(IF (MONTH(datum)=11, 1, 0)) AS nov
            ,SUM(IF (MONTH(datum)=12, 1, 0)) AS 'dec'
            
            FROM (SELECT datum, foglalta, orvosassigned
            FROM keltexmed.foglalasok 
            WHERE datum>'2025-01-01 00:00:00' AND datum<'2025-12-31 23:55:55' AND (foglalta='foglaljorvost' OR eljott=1) AND helyszinid IN (292,328) AND eljott=1 AND cegid IN (11,392,606)) a
            
            LEFT JOIN keltexmed.orvosok o ON o.id=a.orvosassigned
            
            GROUP BY orvosassigned ORDER BY o.nev", [])->fetchAll(PDO::FETCH_ASSOC);


        foreach ($reservations as $reservation) {
            $this->dataRow("A", $sor, [$reservation["orvos"], $reservation["total"], $reservation["jan"], $reservation["feb"], $reservation["marc"], $reservation["apr"], $reservation["maj"], $reservation["jun"], $reservation["jul"], $reservation["aug"], $reservation["szep"], $reservation["okt"], $reservation["nov"], $reservation["dec"]]);
            $this->sheet->getStyle("B{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("C{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("F{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("G{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("H{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("I{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("J{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("K{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("L{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("M{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("N{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("O{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("P{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("Q{$sor}")->getAlignment()->setHorizontal("right");

            $sor++;
        }

        $this->setAutoWidth(range('B','L'));
        $this->sheet->getColumnDimension('A')->setWidth(20);
    }



    private function _hmmSources($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Hungariamed Jász utca");
        $this->titleRow("A1", "Magán foglalások forrás alapján Hungariamed Jász utca - ".date("Y", strtotime($from)));
        $this->dataRow("A", 2, ["Jelentés készült: ".date("Y.m.d")]);
        $sor = 5;

        $this->headingRow("A", $sor, ["Forrás", "Összesen", "2025 jan", "2025 feb", "2025 már", "2025 ápr", "2025 máj", "2025 jun", "2025 júl", "2025 aug", "2025 szep", "2025 okt", "2025 nov", "2025 dec"]);
        $sor++;

        $reservations = sql_query("SELECT calcfoglalta AS forras, COUNT(*) AS total
            ,SUM(IF (MONTH(datum)=1, 1, 0)) AS jan
            ,SUM(IF (MONTH(datum)=2, 1, 0)) AS feb
            ,SUM(IF (MONTH(datum)=3, 1, 0)) AS marc
            ,SUM(IF (MONTH(datum)=4, 1, 0)) AS apr
            ,SUM(IF (MONTH(datum)=5, 1, 0)) AS maj
            ,SUM(IF (MONTH(datum)=6, 1, 0)) AS jun
            ,SUM(IF (MONTH(datum)=7, 1, 0)) AS jul
            ,SUM(IF (MONTH(datum)=8, 1, 0)) AS aug
            ,SUM(IF (MONTH(datum)=9, 1, 0)) AS szep
            ,SUM(IF (MONTH(datum)=10, 1, 0)) AS okt
            ,SUM(IF (MONTH(datum)=11, 1, 0)) AS nov
            ,SUM(IF (MONTH(datum)=12, 1, 0)) AS 'dec'
            
            FROM (SELECT datum, foglalta, szurestipusid,
            IF (foglalta='', 'bejelentkezo', 
            
            IF (foglalta IN ('', 'labshop', 'foglaljorvost', 'union', 'webpage', 'webshop', 'keltexmedwww'), foglalta, 'admin')) AS calcfoglalta
            FROM hungariamed.foglalasok 
            WHERE datum>'2024-01-01 00:00:00' AND datum<'2024-12-31 23:55:55' AND (foglalta='foglaljorvost' OR eljott=1) AND helyszinid IN (1) AND eljott=1 AND (cegid IN (11,618,587) OR foglalta='foglaljorvost')) a
            
            LEFT JOIN hungariamed.szurestipusok t ON t.id=a.szurestipusid
            
            GROUP BY calcfoglalta ORDER BY calcfoglalta", [])->fetchAll(PDO::FETCH_ASSOC);


        foreach ($reservations as $reservation) {
            $this->dataRow("A", $sor, [$reservation["forras"], $reservation["total"], $reservation["jan"], $reservation["feb"], $reservation["marc"], $reservation["apr"], $reservation["maj"], $reservation["jun"], $reservation["jul"], $reservation["aug"], $reservation["szep"], $reservation["okt"], $reservation["nov"], $reservation["dec"]]);
            $this->sheet->getStyle("B{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("C{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("F{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("G{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("H{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("I{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("J{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("K{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("L{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("M{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("N{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("O{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("P{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("Q{$sor}")->getAlignment()->setHorizontal("right");
            $sor++;
        }

        $sor++;
        $this->titleRow("A{$sor}", "Magán foglalások orvos alapján Hungariamed Jász utca - ".date("Y", strtotime($from)));
        $sor += 2;

        $this->headingRow("A", $sor, ["Orvos", "Összesen", "2025 jan", "2025 feb", "2025 már", "2025 ápr", "2025 máj", "2025 jun", "2025 júl", "2025 aug", "2025 szep", "2025 okt", "2025 nov", "2025 dec"]);
        $sor++;

        $reservations = sql_query("SELECT o.nev AS orvos, COUNT(*) AS total
            ,SUM(IF (MONTH(datum)=1, 1, 0)) AS jan
            ,SUM(IF (MONTH(datum)=2, 1, 0)) AS feb
            ,SUM(IF (MONTH(datum)=3, 1, 0)) AS marc
            ,SUM(IF (MONTH(datum)=4, 1, 0)) AS apr
            ,SUM(IF (MONTH(datum)=5, 1, 0)) AS maj
            ,SUM(IF (MONTH(datum)=6, 1, 0)) AS jun
            ,SUM(IF (MONTH(datum)=7, 1, 0)) AS jul
            ,SUM(IF (MONTH(datum)=8, 1, 0)) AS aug
            ,SUM(IF (MONTH(datum)=9, 1, 0)) AS szep
            ,SUM(IF (MONTH(datum)=10, 1, 0)) AS okt
            ,SUM(IF (MONTH(datum)=11, 1, 0)) AS nov
            ,SUM(IF (MONTH(datum)=12, 1, 0)) AS 'dec'
            
            FROM (SELECT datum, foglalta, orvosassigned
            FROM hungariamed.foglalasok 
            WHERE datum>'2025-01-01 00:00:00' AND datum<'2025-12-31 23:55:55' AND (foglalta='foglaljorvost' OR eljott=1) AND helyszinid IN (1) AND eljott=1 AND (cegid IN (11,618) OR foglalta='foglaljorvost')) a
            
            LEFT JOIN hungariamed.orvosok o ON o.id=a.orvosassigned
            
            GROUP BY orvosassigned ORDER BY o.nev", [])->fetchAll(PDO::FETCH_ASSOC);


        foreach ($reservations as $reservation) {
            $this->dataRow("A", $sor, [$reservation["orvos"], $reservation["total"], $reservation["jan"], $reservation["feb"], $reservation["marc"], $reservation["apr"], $reservation["maj"], $reservation["jun"], $reservation["jul"], $reservation["aug"], $reservation["szep"], $reservation["okt"], $reservation["nov"], $reservation["dec"]]);
            $this->sheet->getStyle("B{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("C{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("F{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("G{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("H{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("I{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("J{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("K{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("L{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("M{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("N{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("O{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("P{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("Q{$sor}")->getAlignment()->setHorizontal("right");

            $sor++;
        }

        $this->setAutoWidth(range('B','L'));
        $this->sheet->getColumnDimension('A')->setWidth(20);
    }


    private function _hmmGyorSources($sheetId, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Hungariamed Győr");
        $this->titleRow("A1", "Magán foglalások forrás alapján Hungariamed Győr - ".date("Y", strtotime($from)));
        $this->dataRow("A", 2, ["Jelentés készült: ".date("Y.m.d")]);
        $sor = 5;

        $this->headingRow("A", $sor, ["Forrás", "Összesen", "2025 jan", "2025 feb", "2025 már", "2025 ápr", "2025 máj", "2025 jun", "2025 júl", "2025 aug", "2025 szep", "2025 okt", "2025 nov", "2025 dec"]);
        $sor++;

        $reservations = sql_query("SELECT calcfoglalta AS forras, COUNT(*) AS total
            ,SUM(IF (MONTH(datum)=1, 1, 0)) AS jan
            ,SUM(IF (MONTH(datum)=2, 1, 0)) AS feb
            ,SUM(IF (MONTH(datum)=3, 1, 0)) AS marc
            ,SUM(IF (MONTH(datum)=4, 1, 0)) AS apr
            ,SUM(IF (MONTH(datum)=5, 1, 0)) AS maj
            ,SUM(IF (MONTH(datum)=6, 1, 0)) AS jun
            ,SUM(IF (MONTH(datum)=7, 1, 0)) AS jul
            ,SUM(IF (MONTH(datum)=8, 1, 0)) AS aug
            ,SUM(IF (MONTH(datum)=9, 1, 0)) AS szep
            ,SUM(IF (MONTH(datum)=10, 1, 0)) AS okt
            ,SUM(IF (MONTH(datum)=11, 1, 0)) AS nov
            ,SUM(IF (MONTH(datum)=12, 1, 0)) AS 'dec'
            
            FROM (SELECT datum, foglalta, szurestipusid,
            IF (foglalta='', 'bejelentkezo', 
            IF (foglalta='fastreservation', 'admin', 
            
            IF (foglalta IN ('', 'fastreservation', 'labshop', 'foglaljorvost', 'union', 'webpage', 'webshop', 'keltexmedwww'), foglalta, 'admin'))) AS calcfoglalta
            FROM hungariamed_gyor.foglalasok 
            WHERE datum>'2025-01-01 00:00:00' AND datum<'2025-12-31 23:55:55' AND (foglalta='foglaljorvost' OR eljott=1) AND helyszinid IN (176) AND eljott=1 AND (cegid IN (42) OR foglalta='foglaljorvost')) a
            
            LEFT JOIN hungariamed_gyor.szurestipusok t ON t.id=a.szurestipusid
            
            GROUP BY calcfoglalta ORDER BY calcfoglalta", [])->fetchAll(PDO::FETCH_ASSOC);


        foreach ($reservations as $reservation) {
            $this->dataRow("A", $sor, [$reservation["forras"], $reservation["total"], $reservation["jan"], $reservation["feb"], $reservation["marc"], $reservation["apr"], $reservation["maj"], $reservation["jun"], $reservation["jul"], $reservation["aug"], $reservation["szep"], $reservation["okt"], $reservation["nov"], $reservation["dec"]]);
            $this->sheet->getStyle("B{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("C{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("F{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("G{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("H{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("I{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("J{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("K{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("L{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("M{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("N{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("O{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("P{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("Q{$sor}")->getAlignment()->setHorizontal("right");
            $sor++;
        }

        $sor++;
        $this->titleRow("A{$sor}", "Magán foglalások orvos alapján Hungariamed Győr - ".date("Y", strtotime($from)));
        $sor += 2;

        $this->headingRow("A", $sor, ["Orvos", "Összesen", "2025 jan", "2025 feb", "2025 már", "2025 ápr", "2025 máj", "2025 jun", "2025 júl", "2025 aug", "2025 szep", "2025 okt", "2025 nov", "2025 dec"]);
        $sor++;

        $reservations = sql_query("SELECT o.nev AS orvos, COUNT(*) AS total
            ,SUM(IF (MONTH(datum)=1, 1, 0)) AS jan
            ,SUM(IF (MONTH(datum)=2, 1, 0)) AS feb
            ,SUM(IF (MONTH(datum)=3, 1, 0)) AS marc
            ,SUM(IF (MONTH(datum)=4, 1, 0)) AS apr
            ,SUM(IF (MONTH(datum)=5, 1, 0)) AS maj
            ,SUM(IF (MONTH(datum)=6, 1, 0)) AS jun
            ,SUM(IF (MONTH(datum)=7, 1, 0)) AS jul
            ,SUM(IF (MONTH(datum)=8, 1, 0)) AS aug
            ,SUM(IF (MONTH(datum)=9, 1, 0)) AS szep
            ,SUM(IF (MONTH(datum)=10, 1, 0)) AS okt
            ,SUM(IF (MONTH(datum)=11, 1, 0)) AS nov
            ,SUM(IF (MONTH(datum)=12, 1, 0)) AS 'dec'
            
            FROM (SELECT datum, foglalta, orvosassigned
            FROM hungariamed_gyor.foglalasok 
            WHERE datum>'2025-01-01 00:00:00' AND datum<'2025-12-31 23:55:55' AND (foglalta='foglaljorvost' OR eljott=1) AND helyszinid IN (176) AND eljott=1 AND (cegid IN (42) OR foglalta='foglaljorvost')) a
            
            LEFT JOIN hungariamed_gyor.orvosok o ON o.id=a.orvosassigned
            
            GROUP BY orvosassigned ORDER BY o.nev", [])->fetchAll(PDO::FETCH_ASSOC);


        foreach ($reservations as $reservation) {
            $this->dataRow("A", $sor, [$reservation["orvos"], $reservation["total"], $reservation["jan"], $reservation["feb"], $reservation["marc"], $reservation["apr"], $reservation["maj"], $reservation["jun"], $reservation["jul"], $reservation["aug"], $reservation["szep"], $reservation["okt"], $reservation["nov"], $reservation["dec"]]);
            $this->sheet->getStyle("B{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("C{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("D{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("E{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("F{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("G{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("H{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("I{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("J{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("K{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("L{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("M{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("N{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("O{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("P{$sor}")->getAlignment()->setHorizontal("right");
            $this->sheet->getStyle("Q{$sor}")->getAlignment()->setHorizontal("right");

            $sor++;
        }

        $this->setAutoWidth(range('B','L'));
        $this->sheet->getColumnDimension('A')->setWidth(20);
    }


}
