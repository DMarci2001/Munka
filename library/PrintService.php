<?php

use mikehaertl\pdftk\Pdf;

class PrintService
{

    private $templates = array(
        "menedzserkerdoiv"   => "menedzserkerdoiv.html",
        "alkalmassagi"       => "alkalmassagi.html",
        "alkalmassagipdf"    => "alkalmassagi_form2.pdf",
        "vizsgalatilap"      => "vizsgalatilap.html",
        "karton"             => "karton.html",
        "menedzsersetalolap" => "Menedzser_Setalolap(compressed)(fixed).pdf",
        "covidkerdoiv"       => "COVID-19_kérdőív_SZTK.pdf",
        "matrica"            => "matrica.html",
        "matricamegj"        => "matricaMegj.html",
        "nkfihsetalolap"     => "Menedzser_Setalolap(NKFIH).pdf"
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
    private $reservationData = null;

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

    public function setReservation($fid, $p)
    {
        if (!$this->reservationData = sql_fetch_array(sql_query("select f.*,c.megnev as cegnev,concat(sz.megnev,' ',date(f.datum)) as vizsgnevdatum from foglalasok f
        left join cegek c on c.id=f.cegid
        left join szurestipusok sz on sz.id=f.szurestipusid
        where f.id=? and pass=?", array($_GET["fid"], $_GET["p"])))) {
            die("error code 1254");
        }
    }


    public function start()
    {
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
            $ervenyes = date("Y. m. d.", strtotime($this->reservationData["fsfsfs"] . " +{$this->reservationData["alkalmassagido"]} month"));
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
            $template = str_replace("#alkalmasextra#", "<div style='margin-top:{$sorkoz}px;'>Érv: " . $this->datumki(date("Y-m-d", strtotime("now +{$ido} month"))) . "</div>", $template);
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
}
