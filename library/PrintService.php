<?php

use mikehaertl\pdftk\Pdf;

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
        "laborlelet1"        => "laborLelet1.html"
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
        $this->setReservationById($this->laborRequestData["foglalasid"]);
    }


    public function start()
    {
        if (substr_count($this->templateId, "labor")) {
            $this->printLaborLelet();
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
}
