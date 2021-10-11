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

}