<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


class ExcelService {
    private $fileName;
    private $spreadSheet;
    private $sheet;
    private $columnNames = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "X", "Y", "Z"];

    public function setFileName($fileName) {
        $this->fileName = $fileName;
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

    private function _bejelentkezoFoglalasokLista($sheetId, $rawInput, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Foglalások lista");
        $this->titleRow("A1", "Foglalások - {$from} - {$to} (forrás: bejelentkező)");
        $this->sheet->SetCellValue("A2", "* csak eljöttek");

        $sor = 4;

        $tipusok = sql_query("SELECT t.id, t.megnev FROM foglalasok f
            LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
            WHERE f.datum>'{$from} 00:00:00' AND f.datum<'{$to} 23:59:59' and f.eljott=1 GROUP BY f.`szurestipusid`")->fetchAll(PDO::FETCH_ASSOC);


        foreach ($tipusok as $tipus) {
            $this->titleRow("A{$sor}", "{$tipus["megnev"]}");
            $sor+=2;

            $this->headingRow("A", $sor, ["Dátum", "Orvos", "Cég", "Paciens", "TAJ", "Születési dátum","Megjegyzés"]);
            $sor++;

            $reservations = sql_query("SELECT t.megnev AS tipusnev, c.megnev AS cegnev, o.nev AS orvosnev, f.* FROM foglalasok f
                LEFT JOIN orvosok o ON o.id=f.orvosassigned
                LEFT JOIN cegek c ON c.id=f.cegid
                LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
                WHERE datum>'{$from} 00:00:00' AND datum<'{$to} 23:59:59' and f.szurestipusid=? and f.eljott=1 order by datum", [$tipus["id"]])->fetchAll(PDO::FETCH_ASSOC);


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

    private function _kiegeszitoFoglalasokLista($sheetId, $rawInput, $from, $to) {
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

    private function _dokirexVizsgalatokLista($sheetId, $rawInput, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Dokirex vizsgálat lista");
        $this->titleRow("A1", "Vizsgálatok - {$from} - {$to} (forrás: dokirex)");

        $data = json_decode($rawInput["dokirexvizsgalatokresult"], JSON_OBJECT_AS_ARRAY);

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


    public function _rtgLista($sheetId, $rawInput, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }
        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("RTG lista");

        $data = sql_query_common("select d.*, count(*) as db from dicom d where d.contentDate>? AND d.contentDate<=? and d.institutionName=? GROUP BY d.patientName, d.patientBirthDate ORDER BY d.contentDate", [$from, $to, Booking_Constants::FOOTER_COPYRIGHT])->fetchAll(PDO::FETCH_ASSOC);

        $this->titleRow("A1", " RTG lista {$from} - {$to}");

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

        $this->sheet->getColumnDimension('A')->setWidth(30);
        $this->sheet->getColumnDimension('A')->setWidth(30);
        $this->sheet->getColumnDimension('B')->setWidth(40);
        $this->sheet->getColumnDimension('C')->setWidth(20);
        $this->sheet->getColumnDimension('D')->setWidth(40);
        $this->sheet->getColumnDimension('E')->setWidth(20);

        $sor = 3;
        $this->dataRow("A", $sor, ["Összes paciens: {$total}, összes kép: {$totalImage}"]);
    }

    public function _cegEsOrvosStat($sheetId, $rawInput, $from, $to) {
        if ($sheetId != 0) {
            $this->spreadSheet->createSheet();
            $this->spreadSheet->setActiveSheetIndex($sheetId);
        }

        $this->sheet = $this->spreadSheet->getActiveSheet();
        $this->sheet->setTitle("Cég és orvos stat");

        $this->titleRow("A1", "Cég és orvos statisztika {$from} - {$to}");

        $companyStat = sql_query("SELECT c.megnev AS ceg, COUNT(*) AS foglalasok, SUM(IF(f.eljott=1, 1, 0)) AS eljott FROM foglalasok f
            LEFT JOIN cegek c ON c.id=f.cegid
            WHERE f.aktiv=1 AND f.nev NOT IN ('nincs név', 'ne foglalj', 'ebéd', 'ebédszünet')
            AND datum>? AND datum<=? AND f.`externalid`=''
            GROUP BY c.id, megnev ORDER BY c.megnev", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);

        $doctorStat = sql_query("SELECT o.nev AS orvos, COUNT(*) AS foglalasok, SUM(IF(eljott=1, 1, 0)) AS eljott FROM foglalasok f
            LEFT JOIN orvosok o ON o.id=f.orvosassigned
            WHERE f.aktiv=1 AND f.nev NOT IN ('nincs név', 'ne foglalj', 'ebéd', 'ebédszünet')
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

    private function _beosztasLista($sheetId, $rawInput, $from, $to) {
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

        $data = json_decode($rawInput["beosztasresult"], JSON_OBJECT_AS_ARRAY);

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


    public function napiStat($rawInput, $from, $to) {
        $this->spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

        //error_reporting(E_ALL);
        //ini_set('display_errors', 1);

        //$this->_vizsgalatKimutatas(0, $rawInput, $from, $to);
        $this->_bejelentkezoFoglalasokLista(0, $rawInput, $from, $to);
        $this->_kiegeszitoFoglalasokLista(1, $rawInput, $from, $to);
        $this->_dokirexVizsgalatokLista(2, $rawInput, $from, $to);
        $this->_beosztasLista(3, $rawInput, $from, $to);
        $this->_rtgLista(4, $rawInput, $from, $to);
        $this->_cegEsOrvosStat(5, $rawInput, $from, $to);
        //$this->_fizetesLista(4, $rawInput, $from, $to);

        $this->spreadSheet->setActiveSheetIndex(0);
    }

}