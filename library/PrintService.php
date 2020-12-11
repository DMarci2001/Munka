<?php

class PrintService
{

    private $templates = array(
        "menedzserkerdoiv"   => "menedzserkerdoiv.html",
        "alkalmassagi"       => "alkalmassagi.html",
        "vizsgalatilap"      => "vizsgalatilap.html",
        "karton"             => "karton.html",
        "menedzsersetalolap" => "Menedzser_Setalolap(compressed)(fixed).pdf",
        "covidkerdoiv"       => "COVID-19_kérdőív_SZTK.pdf"
    );

    private $inputs = array(
        "covidkerdoiv" => array(
            "nev", "lakcim", "telefon", "anyjaneve", "szulhely", "taj","email","szuldatum","datum"
        ),
        "menedzsersetalolap" => array("nev","szulhely","szuldatum","cegnev","taj","vizsgnevdatum")
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
        if (!$this->reservationData = sql_fetch_array(sql_query("select f.*,c.megnev as cegnev,concat(sz.megnev,' ',f.datum) as vizsgnevdatum from foglalasok f
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
            foreach($this->inputs[$this->templateId] as $field){
                if(isset($data[$field])){
                    $fields[$field] = $data[$field];
                }elseif($field=="lakcim"){
                    $fields[$field]=$data["irsz"]." ".$data["varos"].", ".$data["utca"];
                }
               
            }

            //Dátum mező javítása:
            if(isset($fields["datum"])){
                $fields["datum"] = date("Y.m.d",strtotime($fields["datum"]));
            }
            

            $pdf = new FPDM("templates/{$this->templateFileName}");
            $pdf->Load($fields, true); // false-ra ha  ISO-8859-1, true-ra ha UTF-8 a beviteli szöveg
            $pdf->Merge();
            $pdf->Output();
            die();
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
}
