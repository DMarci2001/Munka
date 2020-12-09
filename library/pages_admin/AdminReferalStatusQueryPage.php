<?php
class AdminReferalStatusQueryPage extends AdminCorePage
{
    private $adminUser;
    private $dokirexService;

    public function __construct()
    {
        parent::__construct();

        $this->adminUser = new adminUser();
        $this->dokirexService = new DokirexService();



        if (isset($_POST["downloadExcel_x"]) && isset($_POST["downloadExcel_y"])) {
            //Itt el kell végeznem az adat letöltést excelben.

            $response = json_decode($this->dokirexService->runBuiltInQuery($_POST), true);

            $ceg = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE dokirexTelephelyId=?", array($_POST["Param1"])));

            $Filename = "{$ceg["megnev"]} {$_POST["Param2"]}-{$_POST["Param3"]}";
            $objPHPExcel = new PHPExcel();
            $objPHPExcel->setActiveSheetIndex(0);
            //$objPHPExcel->getActiveSheet()->setTitle('Állomány');

            //Oszlop nevek:
            $objPHPExcel->getActiveSheet()->SetCellValue("A1", "Vizsgálat Dátuma");
            $objPHPExcel->getActiveSheet()->SetCellValue("B1", "Dolgozó neve");
            $objPHPExcel->getActiveSheet()->SetCellValue("C1", "TAJ");
            $objPHPExcel->getActiveSheet()->SetCellValue("D1", "Szül. dátum");
            $objPHPExcel->getActiveSheet()->SetCellValue("E1", "Vizsgálat");
            $objPHPExcel->getActiveSheet()->SetCellValue("F1", "Ellátó orvos");
            $objPHPExcel->getActiveSheet()->SetCellValue("G1", "Munkakör");
            $objPHPExcel->getActiveSheet()->SetCellValue("H1", "Cég");
            $objPHPExcel->getActiveSheet()->SetCellValue("I1", "Vizsgálat típusa");
            $objPHPExcel->getActiveSheet()->SetCellValue("J1", "Státusz");
            $objPHPExcel->getActiveSheet()->SetCellValue("K1", "Érvényesség");
            $objPHPExcel->getActiveSheet()->SetCellValue("L1", "Korlátozás");

            for ($i = 1; $i < count($response["data"]); $i++) {
                $objPHPExcel->getActiveSheet()->SetCellValue("A".($i+1), (isset($response["data"][$i]["PaciensVizsgalat_FelvetelDatuma"]) ? $response["data"][$i]["PaciensVizsgalat_FelvetelDatuma"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("B".($i+1), (isset($response["data"][$i]["PaciensNev"]) ? $response["data"][$i]["PaciensNev"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("C".($i+1), (isset($response["data"][$i]["Azonosito"]) ? $response["data"][$i]["Azonosito"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("D".($i+1), (isset($response["data"][$i]["SzuletesiDatum"]) ? $response["data"][$i]["SzuletesiDatum"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("E".($i+1), (isset($response["data"][$i]["SzakrendelesNev"]) ? $response["data"][$i]["SzakrendelesNev"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("F".($i+1), (isset($response["data"][$i]["FelhasznaloNev"]) ? $response["data"][$i]["FelhasznaloNev"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("G".($i+1), (isset($response["data"][$i]["Munkakor"]) ? $response["data"][$i]["Munkakor"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("H".($i+1), (isset($response["data"][$i]["Telephely"]) ? $response["data"][$i]["Telephely"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("I".($i+1), (isset($response["data"][$i]["VizsgalatTipusa"]) ? $response["data"][$i]["VizsgalatTipusa"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("J".($i+1), (isset($response["data"][$i]["Alkalmassag"]) ? $response["data"][$i]["Alkalmassag"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("K".($i+1), (isset($response["data"][$i]["Ervenyesseg"]) ? $response["data"][$i]["Ervenyesseg"] : ""));
                $objPHPExcel->getActiveSheet()->SetCellValue("L".($i+1), (isset($response["data"][$i]["Korlatozas"]) ? $response["data"][$i]["Korlatozas"] : ""));
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $Filename . '.xlsx"');
            header('Cache-Control: max-age=0');
            //Excel fájl véglegesítése:
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
            $objWriter->save('php://output');
        }
    }



    public function showPage()
    {
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
                $htmlout .= "<option value=\"{$rC["dokirexTelephelyId"]}\">{$rC["megnev"]}</option>";
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
