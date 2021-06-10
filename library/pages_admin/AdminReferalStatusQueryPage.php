<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


class AdminReferalStatusQueryPage extends AdminCorePage
{
    private $dokirexService;

    

    public function __construct()
    {
        parent::__construct();

        if (!$this->adminUtils->dokirexlekerdezesekJog()) {
            return;
        }

        $this->dokirexService = new DokirexService();

       

        if (isset($_POST["downloadExcel_x"]) && isset($_POST["downloadExcel_y"])) {
            //Itt el kell végeznem az adat letöltést excelben.

            $ceg = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE md5(concat('dokirexTelephelyId',dokirexTelephelyId))=?", array($_POST["Param1"])));
            if(!$ceg){
                die("Error - 486");
            }else{
                $_POST["Param1"] = $ceg["dokirexTelephelyId"];
            }

            $response = json_decode($this->dokirexService->runBuiltInQuery($_POST), true);

            

            
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $spreadsheet->getActiveSheet()->getProtection()->setSheet(true);

            $sheet->getProtection()->setPassword('PhpSpreadsheet');
            $sheet->getProtection()->setSheet(true);
            $sheet->getProtection()->setSort(true);
            $sheet->getProtection()->setInsertRows(true);
            $sheet->getProtection()->setFormatCells(true);

            $sheet->SetCellValue("A1", "Vizsgálat Dátuma");
            $sheet->SetCellValue("B1", "Dolgozó neve");
            $sheet->SetCellValue("C1", "TAJ");
            $sheet->SetCellValue("D1", "Szül. dátum");
            $sheet->SetCellValue("E1", "Vizsgálat");
            $sheet->SetCellValue("F1", "Ellátó orvos");
            $sheet->SetCellValue("G1", "Munkakör");
            $sheet->SetCellValue("H1", "Cég");
            $sheet->SetCellValue("I1", "Vizsgálat típusa");
            $sheet->SetCellValue("J1", "Státusz");
            $sheet->SetCellValue("K1", "Érvényesség");
            $sheet->SetCellValue("L1", "Korlátozás");

            $Filename = "{$ceg["megnev"]} {$_POST["Param2"]}-{$_POST["Param3"]}";

            for ($i = 1; $i < count($response["data"]); $i++) {
                $sheet->SetCellValue("A".($i+1), (isset($response["data"][$i]["PaciensVizsgalat_FelvetelDatuma"]) ? date("Y-m-d H:i:s",strtotime($response["data"][$i]["PaciensVizsgalat_FelvetelDatuma"])) : ""));
                $sheet->SetCellValue("B".($i+1), (isset($response["data"][$i]["PaciensNev"]) ? $response["data"][$i]["PaciensNev"] : ""));
                $sheet->SetCellValue("C".($i+1), (isset($response["data"][$i]["Azonosito"]) ? $response["data"][$i]["Azonosito"] : ""));
                $sheet->SetCellValue("D".($i+1), (isset($response["data"][$i]["SzuletesiDatum"]) ? date("Y-m-d",strtotime($response["data"][$i]["SzuletesiDatum"])) : ""));
                $sheet->SetCellValue("E".($i+1), (isset($response["data"][$i]["SzakrendelesNev"]) ? $response["data"][$i]["SzakrendelesNev"] : ""));
                $sheet->SetCellValue("F".($i+1), (isset($response["data"][$i]["FelhasznaloNev"]) ? $response["data"][$i]["FelhasznaloNev"] : ""));
                $sheet->SetCellValue("G".($i+1), (isset($response["data"][$i]["Munkakor"]) ? $response["data"][$i]["Munkakor"] : ""));
                $sheet->SetCellValue("H".($i+1), (isset($response["data"][$i]["Telephely"]) ? $response["data"][$i]["Telephely"] : ""));
                $sheet->SetCellValue("I".($i+1), (isset($response["data"][$i]["VizsgalatTipusa"]) ? $response["data"][$i]["VizsgalatTipusa"] : ""));
                $sheet->SetCellValue("J".($i+1), (isset($response["data"][$i]["Alkalmassag"]) ? $response["data"][$i]["Alkalmassag"] : ""));
                $sheet->SetCellValue("K".($i+1), (isset($response["data"][$i]["Ervenyesseg"]) ? $response["data"][$i]["Ervenyesseg"] : ""));
                $sheet->SetCellValue("L".($i+1), (isset($response["data"][$i]["Korlatozas"]) ? $response["data"][$i]["Korlatozas"] : ""));
            }

            ob_clean();
            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-Disposition: attachment;filename=\"{$Filename}.xlsx\"");
            header("Cache-Control: max-age=0");

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');

            /*ob_end_clean();
            //header('Content-Type: application/vnd.ms-excel');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $Filename . '.xlsx"');
            header('Cache-Control: max-age=0');
            //Excel fájl véglegesítése:
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
            $objWriter->save('php://output');
            ob_end_clean();*/
        }
    }



    public function showPage()
    {
        if (!$this->adminUtils->dokirexlekerdezesekJog()) {
            return;
        }

        //Itt kéne látszódjon majd egy kezelő felületnek, ahol belehet állítani az Aktuális alkalmassági listát, az összes dolgozót lekérdezzük itt, akik a céghez vannak rendelve és 
        //és az utolsó hozzá tartozó alkalmassági lejáratot.
        //A cég azonosítót fixen a profilhoz rendeljük majd, így nem lesz módja az adminnak magának beállítani, hogy mely cégre szűrjön le.
        //2 mező fog kelleni itt, Tól - Ig értéket lehet megadni, ha minden igaz, így letudjuk kérdezni, ettől eddig érvényes alkalmasságikat, 
        //pl így egy hónapra le vetítve megláthatjuk az eredményeket?
        $htmlout = "";

        //Deklarálnom kell a hónap első és utolsó napját mint alapértelmezett adat:

        $firstDate = date("Y-m-d", strtotime(date("Y-m-1")));
        $lastDate = date("Y-m-t", strtotime($firstDate));

        //Megvizsgálom, hogy az adott személy milyen jogosultsági szintel rendelkezik:
        if ($this->adminUser->user["jogosultsag"] > 1) {
            $qC = sql_query("SELECT * FROM cegek WHERE dokirexTelephelyId IS NOT NULL ORDER BY megnev ASC");

            //Ha alacsonyabb mint szuper admin és rendelkezik cég joggal akkor:
        } elseif (!empty($this->adminUser->user["cegjog"])) {
            $wC = $this->adminUtils->cegSQLFilter("id");
            $qC = sql_query("SELECT * FROM cegek WHERE dokirexTelephelyId IS NOT NULL {$wC} ORDER BY megnev ASC");
        }

        $htmlout .= "<form method=\"POST\">";


        $htmlout .= "<select name=\"runBuiltInQuery\" id=\"runBuiltInQuery\">";
        $htmlout .= "   <option value=\"" . Booking_Constants::DokiRex_dbName . "_DolgozokByFelvetelDatum\">Vizsgálatok lekérdezése időszakosan</option>";
        $htmlout .= "   <option value=\"hungaria_DolgozokByErvenyessegDatum\">Alkalmassági lejáratok lekérdezése</option>";
        $htmlout .= "</select>&nbsp;&nbsp;&nbsp;";

        //Szükség lesz a cég választóra is... vannak olyan adminisztrátorok, akik több cég joggal is rendelkeznek!

        if (isset($qC)) {
            $htmlout .= "<select name=\"Param1\">";
            while ($rC = sql_fetch_array($qC)) {
                $htmlout .= "<option value=\"".md5("dokirexTelephelyId{$rC["dokirexTelephelyId"]}")."\">{$rC["megnev"]}</option>";
            }
            $htmlout .= "</select>&nbsp;&nbsp;&nbsp;";
        }


        $htmlout .= "<input type=\"textbox\" value=\"{$firstDate}\" class=\"napfilter\" id=\"start-query-date\" style=\"font-size:18px;background-color:#eee;color:#444;margin-right:10px;border:1px solid #ccc;width:130px;border-radius:3px\" placeholder=\"Tól\" name=\"Param2\">-&nbsp;&nbsp;";
        $htmlout .= "<input type=\"textbox\" value=\"{$lastDate}\" class=\"napfilter\" id=\"end-query-date\" style=\"font-size:18px;background-color:#eee;color:#444;margin-right:10px;border:1px solid #ccc;width:130px;border-radius:3px\" placeholder=\"Ig\" name=\"Param3\">";


        $htmlout .= "&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"submit\" value=\"Lekérdezés\" style=\"text-align:center\" name=\"runQuery\">";

        $htmlout .= "<input class=\"grayscale\" style=\"height:30px;position:absolute;padding:0px 10px\" type=\"image\" src=\"../images/icon_xlsx.png\" onClick=\"form.submit\" name=\"downloadExcel\">";


        $htmlout .= "</form>";

        $htmlout .= "<table cellpadding='0' cellspacing='0' border='0' style='margin-top:10px;'>";

        if (isset($_POST["runQuery"])) {
            $htmlout .= $this->runDokirexQuery($_POST);
        }

        $htmlout .= "</table>";

        echo $htmlout;
    }

    private function runDokirexQuery($data)
    {
        $htmlout = "";

        $ceg = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE md5(concat('dokirexTelephelyId',dokirexTelephelyId))=?", array($data["Param1"])));
        if(!$ceg){
            die("Error - 486");
        }else{
            $data["Param1"] = $ceg["dokirexTelephelyId"];
        }

        $response = json_decode($this->dokirexService->runBuiltInQuery($data), true);

        /*while ($row = sql_fetch_array($res)) {*/
        foreach ($response["data"] as $row) {
            $tc = "tcella";
            if (!isset($first)) {
                $htmlout .= "<tr><td colspan='12' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            //if (trim($row["nev"]) == "") $row["nev"] = "nincs neve";
            $htmlout .= "<tr>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["PaciensVizsgalat_FelvetelDatuma"]) ? date("Y-m-d H:i:s", strtotime($row["PaciensVizsgalat_FelvetelDatuma"])) : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["PaciensNev"]) ? "{$row["PaciensNev"]}" : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["Azonosito"]) ? "{$row["Azonosito"]}" : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["SzuletesiDatum"]) ? date("Y-m-d", strtotime($row["SzuletesiDatum"])) : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["SzakrendelesNev"]) ? "{$row["SzakrendelesNev"]}" : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["FelhasznaloNev"]) ? "Ellátó orvos: {$row["FelhasznaloNev"]}" : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["Munkakor"]) ? "{$row["Munkakor"]}" : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["Telephely"]) ? "{$row["Telephely"]}" : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["VizsgalatTipusa"]) ? "{$row["VizsgalatTipusa"]}" : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["Alkalmassag"]) ? "Státusz: {$row["Alkalmassag"]}" : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["Ervenyesseg"]) ? "Érvényes: {$row["Ervenyesseg"]}" : "") . "</div></td>";
            $htmlout .= "<td nowrap valign='top'><div class='{$tc}'>" . (isset($row["Korlatozas"]) ? "Korlátozás: {$row["Korlatozas"]}" : "") . "</div></td>";

            $htmlout .= "</tr>";
            $htmlout .= "<tr><td colspan='12' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        return $htmlout;
    }
}
