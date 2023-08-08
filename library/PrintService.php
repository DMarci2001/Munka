<?php

use mikehaertl\pdftk\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Nick\SecureSpreadsheet\Encrypt;

class PrintService
{

    private array $templates = array(
        "menedzserkerdoiv"   => "menedzserkerdoiv.html",
        "alkalmassagi"       => "alkalmassagi.html",
        "alkalmassagipdf"    => "alkalmassagi_form2.pdf",
        "vizsgalatilap"      => "vizsgalatilap.html",
        "karton"             => "karton.html",
        "menedzsersetalolap" => "Menedzser_Setalolap(compressed)(fixed).pdf",
        "covidkerdoiv"       => "COVID-19_kérdőív_SZTK.pdf",
        "matrica"            => "matrica.html",
        "matricamegj"        => "matricaMegj.html",
        "nkfihsetalolap"     => "Menedzser_Setalolap(NKFIH).pdf",
        "laborlelet1"        => "laborLelet1.html",
        "makdailyreport"     => "mak.html"
    );

    private $inputs = array(
        "covidkerdoiv" => array(
            "nev", "lakcim", "telefon", "anyjaneve", "szulhely", "taj", "email", "szuldatum", "datum"
        ),
        "menedzsersetalolap" => array(
            "nev", "szulhely", "szuldatum", "cegnev", "taj", "vizsgnevdatum"
        ),
        "nkfihsetalolap" => array(
            "nev", "szulhely", "szuldatum", "cegnev", "taj", "vizsgnevdatum","megj"
        )
    );

    private $templateFileName = "";
    private $templateId = "";
    private array $reservationData = [];
    private array $laborRequestData = [];

    public function __construct()
    {
    }

    public function setTemplate($template)
    {
        if (!isset($this->templates[$template])) {
            die("error code 1255");
        }
        $this->templateId = $template;
        $this->templateFileName = $this->templates[$template];
    }

    public function setReservation($fid, $p):void {
        if (!$data = sql_fetch_array(sql_query("select f.*,c.megnev as cegnev,concat(sz.megnev,' ',date(f.datum)) as vizsgnevdatum from foglalasok f
        left join cegek c on c.id=f.cegid
        left join szurestipusok sz on sz.id=f.szurestipusid
        where f.id=? and pass=?", [$fid, $p]))) {
            die("error code 1254");
        }
        $this->reservationData = $data;
    }

    public function setReservationById($fid):void {
        if (!$data = sql_fetch_array(sql_query("select f.*,c.megnev as cegnev,concat(sz.megnev,' ',date(f.datum)) as vizsgnevdatum from foglalasok f
        left join cegek c on c.id=f.cegid
        left join szurestipusok sz on sz.id=f.szurestipusid
        where f.id=?", [$fid]))) {
            die("error code 1253");
        }
        $this->reservationData = $data;
    }

    public function setLaborRequest($rid, $p):void {
        if (!$data = sql_fetch_array(sql_query("SELECT r.* FROM labrequests r WHERE r.id=? and r.pass=?", [$rid, $p]))) {
            die("error code 1354");
        }
        $this->laborRequestData = $data;
        if ($this->laborRequestData["foglalasid"] != 0) {
            $this->setReservationById($this->laborRequestData["foglalasid"]);
        } else {
            $data = json_decode($this->laborRequestData["synlabdata"], JSON_OBJECT_AS_ARRAY);
            $this->reservationData["nev"] = $data["nev"];
        }
    }


    public function start()
    {
        if (substr_count($this->templateId, "labor")) {
            $this->printLaborLelet();
            return;
        }

        if ($this->templateId == "makdailyreport") {
            $this->printMakDailyReport();
            return;
        }

        if ($this->templateId == "karton") {
            $this->printKartonPDF();
            return;
        }

        if (empty($this->templateId)) {
            die("error code 1256");
        }

        if ($this->templateId == "alkalmassagipdf") {
            $this->printAlkalmassagi();
            return;
        }

        //HTML alapú dokumentumok:
        if (strpos($this->templateFileName, ".html") !== false) {
            header("Content-type: text/html; charset=UTF-8");

            $templateContent = file_get_contents("templates/{$this->templateFileName}");
            $templateContent = $this->setTemplateMacros($templateContent);

            echo $templateContent;
        }

        //PDF alapú dokumentumok:
        if (strpos($this->templateFileName, ".pdf") !== false) {
            require('fpdm/fpdm.php');

            //Adatok meghívása:
            $data = $this->reservationData;

            //Végig megyek a dokumentumhoz tartozó adatmezőkön:
            foreach ($this->inputs[$this->templateId] as $field) {
                if (isset($data[$field])) {
                    $fields[$field] = $data[$field];
                } elseif ($field == "lakcim") {
                    $fields[$field] = $data["irsz"] . " " . $data["varos"] . ", " . $data["utca"];
                }
            }

            //Dátum mező javítása:
            if (isset($fields["datum"])) {
                $fields["datum"] = date("Y.m.d", strtotime($fields["datum"]));
            }


            $pdf = new FPDM("templates/{$this->templateFileName}");
            $pdf->Load($fields, true); // false-ra ha  ISO-8859-1, true-ra ha UTF-8 a beviteli szöveg
            $pdf->Merge();
            $pdf->Output();
            die();
        }
    }

    private function printAlkalmassagi()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $alkalmasMark = $nemalkalmasMark = $ideiglenesennemalkalmasMark = $ervenyes = "";
        if ($this->reservationData["alkalmassag"] == "I" || $this->reservationData["alkalmassag"] == "K") {
            $alkalmasMark = "_____________________";
            $ervenyes = date("Y. m. d.", strtotime($this->reservationData["datum"] . " +{$this->reservationData["alkalmassagido"]} month"));
        }
        if ($this->reservationData["alkalmassag"] == "N") {
            $nemalkalmasMark = "________________________";
        }
        if ($this->reservationData["alkalmassag"] == "IN") {
            $ideiglenesennemalkalmasMark = "________________________________________";
        }

        $input = [
            "nev" => $this->pdfChars($this->reservationData["nev"]),
            "szuldatum" => date("Y. m. d.", strtotime($this->reservationData["szuldatum"])),
            "szulev" => date("Y", strtotime($this->reservationData["szuldatum"])),
            "szulho" => date("m", strtotime($this->reservationData["szuldatum"])),
            "szulnap" => date("d", strtotime($this->reservationData["szuldatum"])),
            "munkakor" => $this->pdfChars($this->reservationData["munkakor"]),
            "alkalmas" => $alkalmasMark,
            "nem_alkalmas" => $nemalkalmasMark,
            "ideiglenesen_nem_alkalmas" => $ideiglenesennemalkalmasMark,
            "kelte" => "Budapest, " . date("Y. m. d.", strtotime($this->reservationData["datum"])),
            "ervenyes" => $ervenyes,
            "korlatozas" => $this->pdfChars($this->reservationData["alkalmassagkorl"]),
            "ida_het" => $this->reservationData["alkalmassagikhet"]
        ];

        $fileName = "alkalmassagi_" . date("Y_m_d", strtotime($this->reservationData["datum"])) . "_{$this->reservationData["nev"]}.pdf";

        if (is_file("templates/{$this->reservationData["alkalmassaguserid"]}_{$this->templateFileName}")) {
            $this->templateFileName = "{$this->reservationData["alkalmassaguserid"]}_{$this->templateFileName}";
        }

        $pdf = new Pdf("templates/{$this->templateFileName}");


        $raw = $pdf->needAppearances()->fillForm($input)->flatten()->toString();

        if ($raw === false) {
            $error = $pdf->getError();
            var_dump($error);
        } else {
            header("Pragma: no-cache");
            header("Cache-Control: no-store, no-cache");
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header("Cache-Control: must-revalidate");
            header('Content-transfer-encoding: binary');
            header("Content-Type: application/octet-stream");
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            //header("Content-Type: application/pdf");
            echo $raw;
        }
    }


    private function printKartonPDF()
    {
        require("fpdf/fpdf.php");

        $data = $this->reservationData;

        $pdf = new FPDF("P", "mm", array(264, 420));
        $pdf->AddPage();
        $pdf->SetFont("Arial", "", 12);
        $pdf->SetTextColor(0, 0, 255);


        $pdf->SetXY(160, 0);
        $pdf->Cell(100, 10, $this->pdfString($data["cegnev"]), 0, 0, "R");


        $pdf->SetXY(10, 10);
        $pdf->Cell(60, 10, $this->pdfString($data["nev"]));

        $pdf->SetXY(60, 10);
        $pdf->Cell(60, 10, $this->pdfString($data["szulhely"]));

        $pdf->SetXY(120, 10);
        $pdf->Cell(60, 10, $this->pdfString($data["szuldatum"]));

        $pdf->SetXY(60, 20);
        $pdf->Cell(60, 10, $this->pdfString($data["taj"]));

        $pdf->SetXY(180, 20);
        $pdf->Cell(60, 10, $this->pdfString($data["anyjaneve"]));

        $pdf->SetXY(100, 30);
        $pdf->Cell(60, 10, $this->pdfString("{$data["irsz"]} {$data["varos"]} {$data["utca"]}"));

        $pdf->Output();
    }

    private function setTemplateMacros($template)
    {
        $data = $this->reservationData;

        $template = str_replace("#nev#", $data["nev"], $template);
        $template = str_replace("#foglalkozas#", $data["munkakor"], $template);
        $template = str_replace("#szuletesihelyesdatum#", (($data["szulhely"] != "" ? "{$data["szulhely"]}, " : "") . $this->datumki($data["szuldatum"])), $template);
        $template = str_replace("#anyjaneve#", $data["anyjaneve"], $template);
        $template = str_replace("#lakcim#", "{$data["irsz"]} {$data["varos"]} {$data["utca"]}", $template);
        $template = str_replace("#taj#", $data["taj"], $template);
        $template = str_replace("#telefon#", $data["telefon"], $template);
        $template = str_replace("#email#", $data["email"], $template);
        $template = str_replace("#szuldatum#", $this->datumki($data["szuldatum"]), $template);
        $template = str_replace("#megj#", $data["megj"], $template);

        $keretstyle = "border:1px solid #000;display:inline-block;padding:2px 5px;";
        $sorkoz = 10;

        $template = str_replace("#i_alkalmassag#", ($data["alkalmassag"] == "I" ? "{$keretstyle}" : ""), $template);
        $template = str_replace("#ik_alkalmassag#", ($data["alkalmassag"] == "IN" ? "{$keretstyle}" : ""), $template);
        $template = str_replace("#n_alkalmassag#", ($data["alkalmassag"] == "N" ? "{$keretstyle}" : ""), $template);
        //$template = str_replace("#k_alkalmassag#",($data["alkalmassag"]=="K"?"{$keretstyle}":""),$template);
        $template = str_replace("#alkalmassagkorl#", ($data["alkalmassagkorl"] != "" ? "{$data["alkalmassagkorl"]}" : "______________________________________"), $template);
        $template = str_replace("#alkalmassagikhet#", ($data["alkalmassagikhet"] != "" ? "{$data["alkalmassagikhet"]}" : "____________"), $template);
        $template = str_replace("#tudoszuroervenyesseg#", ($data["tudoszuroervenyesseg"] != "" ? "{$data["tudoszuroervenyesseg"]}" : "____________"), $template);
        $template = str_replace("#maidatum#", $this->datumki(date("Y-m-d")), $template);
        $template = str_replace("#idopont#", substr($this->datumki($data["datum"]), 0, 10), $template);
        $template = str_replace("#sorkoz#", $sorkoz, $template);

        $vlaptipus = "Időszakos";
        if (isset($_GET["tipus"]) && $_GET["tipus"] = "soronkivuli") {
            $vlaptipus = "Soron kívüli";
        }

        $template = str_replace("#vlaptipus#", $vlaptipus, $template);

        if ($data["alkalmassag"] == "I") {
            $ido = intval($data["alkalmassagido"]);
            $template = str_replace("#alkalmasextra#", "<div style='margin-top:{$sorkoz}px;'>Érv: " . $this->datumki(date("Y-m-d", strtotime("{$data["datum"]} +{$ido} month"))) . "</div>", $template);
        } else {
            $template = str_replace("#alkalmasextra#", "", $template);
        }

        return $template;
    }

    private function datumki($datum)
    {
        $d = str_replace("-", ".", $datum);
        return $d;
    }

    private function pdfString($s)
    {
        return iconv("UTF-8", "ISO-8859-2", $s);
    }

    private function pdfChars($text)
    {
        $text = str_replace("ő", "ö", $text);
        $text = str_replace("ű", "ü", $text);
        $text = str_replace("í", "i", $text);
        $text = str_replace("Ő", "Ö", $text);
        $text = str_replace("Ű", "Ü", $text);
        $text = str_replace("Í", "I", $text);
        return $text;
    }

    private function printLaborLelet() {
        $outFileName = $this->reservationData["nev"]." laborlelet.pdf";
        header("Content-Type: application/pdf");
        header('Content-Disposition: attachment; filename="'.$outFileName.'"');
        echo base64_decode($this->laborRequestData["resultpdf"]);
        die;


        if (isset($_REQUEST["pdf"])) {
            $id = $this->laborRequestData["id"];
            $pass = $this->laborRequestData["pass"];
            $pdfFileName = Booking_Constants::DOCUMENT_PATH."labor".md5($id.rand(1,10000)).".pdf";
            $pdfFileNameEncripted = Booking_Constants::DOCUMENT_PATH."laborenc".md5($id.rand(1,10000)).".pdf";
            $outFileName = $this->reservationData["nev"]." laborlelet.pdf";
            $output = `chromium --headless --print-to-pdf="{$pdfFileName}" --no-pdf-header-footer --no-sandbox "https://bejelentkezes.hungariamed.hu/admin/index.php?print&template=laborlelet1&rid={$id}&p={$pass}"`;
            $output = `pdftk {$pdfFileName} output {$pdfFileNameEncripted} owner_pw hmm1 user_pw hmm2`;

            header("Content-Type: application/pdf");
            header('Content-Disposition: attachment; filename="'.$outFileName.'"');
            //echo file_get_contents($pdfFileName);
            echo file_get_contents($pdfFileNameEncripted);
            unlink($pdfFileName);
            unlink($pdfFileNameEncripted);
            die;
        }

        $maxRowsPerPage = 45;

        header("Content-type: text/html; charset=UTF-8");

        $templateContent = file_get_contents("templates/laborLeletHead.html");

        $laborResultRows = [];

        for ($i=1;$i<=60;$i++) {
            $laborResultRows[] = "eredmény {$i}";
        }

        $pages = [];
        $resultRows = "";
        $pageNum = 0;
        $allPages = ceil(count($laborResultRows) / $maxRowsPerPage);

        $sor = 0;
        foreach ($laborResultRows as $laborResultRow) {
            if ($sor >= $maxRowsPerPage && isset($page)) {
                $resultRows.= "<div style='margin-top:10px;'>A lelet a következő oldalon folytatódik!</div>";
                $pageNum++;
                $page = str_replace("#laboreredmenysorok#", $resultRows, $page);
                $page = str_replace("#pagenum#", "{$pageNum}/{$allPages}", $page);
                $resultRows = "";
                $pages[] = $page;
                unset($page);
                $sor = 0;
            }

            if (!isset($page)) {
                $page = file_get_contents("templates/{$this->templateFileName}");
                $resultRows = "";
            }

            $resultRows.= "<div>{$laborResultRow}</div>";
            $sor++;
        }

        if ($resultRows != "") {
            $pageNum++;
            $page = str_replace("#laboreredmenysorok#", $resultRows, $page);
            $page = str_replace("#pagenum#", "{$pageNum}/{$allPages}", $page);
            $pages[] = $page;
        }

        $templateContent = str_replace("#laborlelet#", implode("", $pages), $templateContent);
        $templateContent = $this->setTemplateMacros($templateContent);

        echo $templateContent;
    }


    private $sheet;

    private function printMakDailyReport() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        if (isset($_GET["printtoken"]) && $_GET["printtoken"] == "eicahlig9Lei1phoox3h") {
            $_SESSION["adminuser"]["id"] = 0;
        }

        if (!isset($_SESSION["adminuser"]["id"])) {
            echo "authentication error";
            die;
        }

        $datum = $_GET["datum"];
        $password = "Hunmed2023";

        if (isset($_REQUEST["pdf"]) || isset($_REQUEST["excel"]) || isset($_REQUEST["mail"])) {
            //excel generation
            $excelFileName = "Magyar Államkincstár {$datum}.xlsx";
            $spreadSheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();

            //-------------------- összes vizsgálat -----------------------------------

            //$this->spreadSheet->createSheet();
            $spreadSheet->setActiveSheetIndex(0);

            $this->sheet = $spreadSheet->getActiveSheet();
            $this->sheet->setTitle("Vizsgálatok");

            $data = sql_query("SELECT * FROM dokirex_vizsgalatok v WHERE DATE(v.vizsgalatdatum)=? AND telephely IN ('Magyar Államkincstár', 'Magyar Államkincstár - Vidék')", [$datum])->fetchAll(PDO::FETCH_ASSOC);

            $sor = 1;
            $this->headingRow("A", $sor, ["Egyedi/Vizsgálat dátuma", "Vizsgálat/FelvetelDatuma", "Paciens/Nev", "Paciens/Azonosító", "Paciens/SzuletesiDatum", "Egyedi/Telephely", "Egyedi/Munkakor", "Egyedi/Vizsgálat típusa", "Egyedi/Korlátozás", "Egyedi/Alkalmasság", "Egyedi/Érvényesség", "Felhasználó"]);
            $sor++;

            $tajSzamok = [];
            foreach ($data as $item) {
                $tajSzamok[] = $item["paciensid"];
                $this->dataRow("A", $sor, [date("Y-m-d", strtotime($item["vizsgalatdatum"])), $item["datum"], $item["nev"], $item["paciensid"], $item["szuldatum"], $item["telephely"], $item["munkakor"], $item["vizsgalattipus"], $item["korlatozas"], $item["alkalmassag"], $item["ervenyesseg"], $item["orvos"]]);
                $sor++;
            }

            $this->setAutoWidth(range('B', 'L'));
            $this->sheet->getStyle("D")->getAlignment()->setHorizontal("left");
            $this->sheet->getColumnDimension('A')->setWidth(20);

            //-------------------- csak előzetes vizsgálatok -----------------------------------

            $spreadSheet->createSheet();
            $spreadSheet->setActiveSheetIndex(1);

            $this->sheet = $spreadSheet->getActiveSheet();
            $this->sheet->setTitle("Előzetesek");

            $data = sql_query("SELECT * FROM dokirex_vizsgalatok v WHERE DATE(v.vizsgalatdatum)=? AND telephely IN ('Magyar Államkincstár', 'Magyar Államkincstár - Vidék') AND (INSTR(vizsgalattipus, 'előzetes') OR INSTR(vizsgalattipus, 'soron'))", [$datum])->fetchAll(PDO::FETCH_ASSOC);

            $sor = 1;
            $this->headingRow("A", $sor, ["Egyedi/Vizsgálat dátuma", "Vizsgálat/FelvetelDatuma", "Paciens/Nev", "Paciens/Azonosító", "Paciens/SzuletesiDatum", "Egyedi/Telephely", "Egyedi/Munkakor", "Egyedi/Vizsgálat típusa", "Egyedi/Korlátozás", "Egyedi/Alkalmasság", "Egyedi/Érvényesség", "Felhasználó"]);
            $sor++;

            foreach ($data as $item) {
                $this->dataRow("A", $sor, [date("Y-m-d", strtotime($item["vizsgalatdatum"])), $item["datum"], $item["nev"], $item["paciensid"], $item["szuldatum"], $item["telephely"], $item["munkakor"], $item["vizsgalattipus"], $item["korlatozas"], $item["alkalmassag"], $item["ervenyesseg"], $item["orvos"]]);
                $sor++;
            }

            $this->setAutoWidth(range('B', 'L'));
            $this->sheet->getStyle("D")->getAlignment()->setHorizontal("left");
            $this->sheet->getColumnDimension('A')->setWidth(20);

            //-------------------- bejelentkezések -----------------------------------

            $spreadSheet->createSheet();
            $spreadSheet->setActiveSheetIndex(2);

            $this->sheet = $spreadSheet->getActiveSheet();
            $this->sheet->setTitle("Bejelentkezések");

            $data = sql_query("SELECT fogl.datum,h.cim,o.nev as orvosnev,fogl.nev,fogl.szuldatum,fogl.taj,fogl.regdatum,fogl.foglalta,fogl.megj, if(eljott=0, 'Nem jött el', 'Eljött') as eljott FROM foglalasok fogl 
            LEFT JOIN helyszinek h ON h.id=fogl.helyszinid
            LEFT JOIN orvosok o ON o.id=fogl.orvosassigned
            WHERE fogl.datum LIKE ? AND fogl.cegid IN (373,374,375,376) and fogl.nev<>'nincs név' ORDER BY fogl.datum", ["{$datum}%"])->fetchAll(PDO::FETCH_ASSOC);

            $sor = 1;
            $this->headingRow("A", $sor, ["Időpont", "Helyszín", "Ellátó orvos", "Dolgozó neve", "Szül. dátum", "TAJ", "Foglalás dátuma", "Admin foglaló", "Megjegyzés", "Eljött"]);
            $sor++;

            foreach ($data as $item) {
                if (in_array($item["taj"], $tajSzamok)) {
                    $item["eljott"] = "Eljött";
                }
                $this->dataRow("A", $sor, [date("Y-m-d H:i", strtotime($item["datum"])), $item["cim"], $item["orvosnev"], $item["nev"], $item["szuldatum"], $item["taj"], $item["regdatum"], $item["foglalta"], $item["megj"], $item["eljott"]]);
                $sor++;
            }

            $this->setAutoWidth(range('A', 'L'));
            $this->sheet->getStyle("F")->getAlignment()->setHorizontal("left");

            //-------------------- output -----------------------------------------------

            $spreadSheet->setActiveSheetIndex(0);

            $excelFileName = Booking_Constants::DOCUMENT_PATH . "mak" . md5(rand(1, 10000)) . ".xlsx";
            $excelFileNameEncrypted = Booking_Constants::DOCUMENT_PATH . "makenc" . md5(rand(1, 10000)) . ".xlsx";

            try {
                $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
                $writer->save($excelFileName);
            } catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
                die("error creating excel file");
            }

            $test = new Encrypt();
            $test->input($excelFileName)
                ->password($password)
                ->output($excelFileNameEncrypted);


            $pdfFileName = Booking_Constants::DOCUMENT_PATH . "mak" . md5(rand(1, 10000)) . ".pdf";
            $pdfFileNameEncripted = Booking_Constants::DOCUMENT_PATH . "makenc" . md5(rand(1, 10000)) . ".pdf";
            $output = `chromium --headless --print-to-pdf="{$pdfFileName}" --no-pdf-header-footer --no-sandbox "https://bejelentkezes.hungariamed.hu/admin/index.php?print&template=makdailyreport&datum={$datum}&printtoken=eicahlig9Lei1phoox3h"`;
            $output = `pdftk {$pdfFileName} output {$pdfFileNameEncripted} owner_pw Ua6Ithei7o user_pw {$password}`;

            $outFileNameExcel = "Magyar Államkincstár {$datum}.xlsx";
            $outFileNamePdf = "Magyar Államkincstár {$datum}.pdf";

            if (isset($_REQUEST["excel"])) {
                header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
                header("Content-Disposition: attachment;filename=\"{$outFileNameExcel}\"");
                header("Cache-Control: max-age=0");
                echo file_get_contents($excelFileName);
                //echo file_get_contents($excelFileNameEncrypted);
                unlink($excelFileName);
                unlink($excelFileNameEncrypted);
                unlink($pdfFileName);
                unlink($pdfFileNameEncripted);
                die;
            }

            if (isset($_REQUEST["pdf"])) {
                header("Content-Type: application/pdf");
                header('Content-Disposition: attachment; filename="'.$outFileNamePdf.'"');
                echo file_get_contents($pdfFileName);
                //echo file_get_contents($pdfFileNameEncripted);
            }

            if (isset($_REQUEST["mail"])) {
                $mail = NotificationService::getDefaultMailer();

                $mail->From = "allamkincstar@hungariamed.hu";

                $eles = false;

                if ($eles) {
                    $mail->AddAddress("uzemorvos@allamkincstar.gov.hu");
                    $mail->AddAddress("varga.katalin@hungariamed.hu");

                    $mail->AddBCC("jnsmobil@gmail.com");
                    $mail->AddBCC("marton.gergely@hungariamed.hu");
                } else {
                    $mail->AddAddress("jnsmobil@gmail.com");
                    $mail->AddAddress("marton.gergely@hungariamed.hu");
                }

                $mail->AddAttachment($pdfFileNameEncripted, $outFileNamePdf);
                $mail->AddAttachment($excelFileNameEncrypted, $outFileNameExcel);

                $subject = "Hungária Med-M - Alkalmassági vélemények ".date("Y.m.d", strtotime($datum));
                $mbody = "Tisztelt Címzettek!<br/>
                <br/>
                A levél tartalmazza a ".date("Y.m.d", strtotime($datum))." dátumon elvégzett foglalkozás-egészségügyi vizsgálatok alkalmassági véleményének másolatát illetve, a vizsgálatok és bejelentkezések Excel listáját. a Bejelentkezési adatokban a megjelenési információ nem pontos, azokon a helyszíneinken, ahol az orvos még nem dolgozik az orvosi programban, nem tudom meghatározni, hogy megjelent-e a páciens az időpontján!<br/>
                <br/>
                Dokumentumok aláírás és pecsét nélkül és érvényesek!<br/>
                <br/>
                A dokumentum titkosított, a korábban küldött jelszó szükséges a megtekintéshez.<br/>
                <br/>
                Üdvözlettel:<br/>Hungariamed-M Kft.<br/>
                <br/>
                <a href='https://www.hungariamed.hu' target='_blank'><img src='https://bejelentkezes.hungariamed.hu/images/hmm_logo_nagy.png' style='width:150px;' /></a>
                ";

                $mail->Subject = $subject;
                $mail->Body = $mbody;
                $mail->Send();

                unlink($excelFileName);
                unlink($excelFileNameEncrypted);
                unlink($pdfFileName);
                unlink($pdfFileNameEncripted);

                echo "Teszt mail kiküldve {$datum}";
                die;
            }
        }


        header("Content-type: text/html; charset=UTF-8");

        $templateContent = file_get_contents("templates/makDailyReport.html");
        $contentPages = [];

        $vizsgalatok = sql_query("SELECT * FROM dokirex_vizsgalatok v WHERE DATE(v.vizsgalatdatum)=? AND telephely IN ('Magyar Államkincstár', 'Magyar Államkincstár - Vidék') AND (INSTR(vizsgalattipus, 'előzetes') OR INSTR(vizsgalattipus, 'soron'))", [$datum])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($vizsgalatok as $vizsgalat) {
            $page = file_get_contents("templates/makDailyReportContent.html");

            $page = str_replace("#nev#", $vizsgalat["nev"], $page);
            $page = str_replace("#szuldatum#", str_replace("-", ".", $vizsgalat["szuldatum"]), $page);
            $page = str_replace("#taj#", $vizsgalat["paciensid"], $page);
            $page = str_replace("#munkakor#", $vizsgalat["munkakor"], $page);
            $page = str_replace("#ceg#", $vizsgalat["telephely"], $page);
            $page = str_replace("#alkalmassag#", $vizsgalat["alkalmassag"], $page);
            $page = str_replace("#korlatozas#", $vizsgalat["korlatozas"], $page);
            $page = str_replace("#datum#", date("Y.m.d", strtotime($vizsgalat["vizsgalatdatum"])), $page);
            $page = str_replace("#ervenyes#", date("Y.m.d", strtotime($vizsgalat["ervenyesseg"])), $page);
            $page = str_replace("#orvosnev#", $vizsgalat["orvos"], $page);
            $page = str_replace("#pecsetszam#", $this->pecsetSzamok[$vizsgalat["orvos"]], $page);
            $page = str_replace("#vizsgalat#", $vizsgalat["vizsgalattipus"], $page);

            if (!isset($this->pecsetSzamok[$vizsgalat["orvos"]])) {
                $templateContent = "Hiányzó pecsétszám: {$vizsgalat["orvos"]}";
                break;
            }
            $contentPages[] = $page;
        }

        $templateContent = str_replace("#report#", implode("", $contentPages), $templateContent);
        //$templateContent = $this->setTemplateMacros($templateContent);

        echo $templateContent;
    }

    private function setAutoWidth($range) {
        foreach($range as $columnID) {
            $this->sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }

    private function dataRow($startColumn, $row, $values) {
        $columnNames = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "X", "Y", "Z"];
        $columnId = array_search($startColumn, $columnNames);
        foreach ($values as $value) {
            $column = $columnNames[$columnId];
            $this->sheet->SetCellValue("{$column}{$row}", $value);
            $columnId++;
        }
    }

    private function headingRow($startColumn, $row, $values) {
        $columnNames = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "X", "Y", "Z"];
        $columnId = array_search($startColumn, $columnNames);
        $column = $startColumn;
        foreach ($values as $value) {
            $column = $columnNames[$columnId];
            $this->sheet->SetCellValue("{$column}{$row}", $value);
            $columnId++;
        }

        $this->sheet->getStyle("{$startColumn}{$row}:{$column}{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('cccccc');
        $this->sheet->getStyle("{$startColumn}{$row}:{$column}{$row}")->getFont()->setBold(true);
    }


    private array $pecsetSzamok = [
        "Bujtárné Dr. Ottmár Piroska" => 36322,
        "Dr. Kondorosi Györgyi" => 34206,
        "Dr. Benkő Katalin Gizella" => 39934,
        "Dr. Salamon László" => 43705,
        "Dr. Fejes Zoltán" => 40683,
        "Dr. Czabai Barbara Anett" => 66783,
        "Dr. Tariska Ágoston Tamás" => 93089,
        "Dr. Dancs-Hang Dóra" => 72028,
        "Dr. Kiss Zsolt" => 62101,
        "Dr. Huttmann Katalin" => 43707,
        "Dr. Kiss József" => 25844,
        "Dr. Kolonics Gábor" => 57770,
        "Dr. Varga Tibor" => 43364,
        "Dr. Kovács Andrea" => 38756,
        "Dr. Várkonyi Andrea" => 45632,
        "Dr. Ruzsa Csaba" => 25820,
        "Feketéné Dr. Tóth Judit" => 42898,
        "Dr. Fülöp Katalin" => 38674,
        "Dr. László Larisza" => 50663,
        "Dr. Soha Erzsébet" => 28640,
        "Dr. Szlivka János" => 79962,
        "Dr. Veisz Katalin" => 40306,
        "Dr. Lőczi Klára Zsuzsanna" => 43510,
        "Dr. Juhász István" => 48643,
        "Dr. Bérces Julianna" => 31573,
        "Dr. Pálmai Éva" => 49277,
        "Dr. Kovács Rita" => 55460,
        "Dr. Posta Luca Magdolna" => 94173,
        "Dr. Vladickaja Olga" => 91067,
        "Dr. Németh Petra" => 78809,
        "Dr. Berki Lucia" => 33277,
        "Dr. Balogh Enikő" => 89551,
        "Dr. Bajzik Éva" => 67070,
        "Dr. Kiss Csaba" => 55535,
        "Dr. Votin Valéria" => 24497,
        "Dr. Gábor Áron" => 63849,
        "Dr. Magyar Judit Katalin" => 44601,
        "Prof. Dr. Garam Tamás" => 22881,
        "Dr. Karro Margarita" => 36000,
        "Dr. Baumann Marcell" => 63276,
        "Dr. Debreceni Katalin" => 50633,
        "Dr. Svéd János" => 29865,
        "Dr. Kollár Erzsébet" => 35452,
        "Dr. Kovács Zoltán" => 54693,
        "Dr. Mesterházy Mária" => 32167,
        "Dr. Wiedemann István" => 45548,
        "Dr. Horváth Eszter" => 82802,
        "Dr. Tánczik Zsófia" => 94225,
        "Dr. Hetei Tünde" => 91881
    ];

}
