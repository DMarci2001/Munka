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
        "spektrumlabmatrica" => "spektrumlabmatrica.html",
        "matricamegj"        => "matricaMegj.html",
        "laborlelet1"        => "laborLelet1.html",
        "makdailyreport"     => "mak.html",
        "aldibeosetup"       => "aldibeosetup",
        "innioertesites"     => "innioertesites",
        "bfkh_email_kuldes"  => "bfkh_email_kuldes",
        "munkanaplo"         => "munkanaplo",
        "munkanaplopdf"      => "munkanaplopdf",
        "ghcsenior"          => "ghc-szures-2024-senior.pdf",
        "ghcstandard"        => "ghc-szures-2024-standard.pdf",
        "nkfihsetalolap"     => "NKFIH_setalolap_2024.pdf",
        "genetika"           => "genetikai_teljes_dokumentum.pdf",
        "generate_aldi_vv"   => "generate_aldi_vv",
        "generateAszKartyak" => "generateAszKartyak",
        "vercsoport"         => "spektrum_vercsoport_v1-1.pdf",
        "vercsoportmail"     => "spektrum_vercsoport_v2-1.pdf",
        "szuloibeleegyezo"   => "szuloi_beleegyezo_nyilatkozat.pdf",
    );

    private array $inputs = array(
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
        if ($this->templateId == "munkanaplo") {
            $this->printMunkaNaplo();
            return;
        }
        if ($this->templateId == "munkanaplopdf") {
            $this->printMunkaNaploPDF();
            return;
        }

        if ($this->templateId == "makdailyreport") {
            $this->printMakDailyReport();
            return;
        }

        if ($this->templateId == "aldibeosetup"){
            $this->aldiBeoSetup();
            return;
        }

        if ($this->templateId == "generate_aldi_vv"){
            $this->generate_aldi_vv();
            return;
        }

        if ($this->templateId == "generateAszKartyak"){
            $this->generateAszKartyak();
            return;
        }



        if ($this->templateId == "innioertesites"){
            $this->innioErtesites();
            return;
        }
        if ($this->templateId == "bfkh_email_kuldes"){
            $this->bfkh_email_kuldes();
            return;
        }

        if ($this->templateId == "karton") {
            $this->printKartonPDF();
            return;
        }

        if ($this->templateId == "spektrumlabmatrica") {
            $this->printSpektrumLabMatrica();
            return;
        }

        if (empty($this->templateId)) {
            die("error code 1256");
        }

        if ($this->templateId == "alkalmassagipdf") {
            $this->printAlkalmassagi();
            return;
        }

        if ($this->templateId == "ghcsenior" || $this->templateId == "ghcstandard") {
            $this->printGHCPdf();
            return;
        }

        if ($this->templateId == "nkfihsetalolap") {
            $this->printNKFIHsetalo();
            return;
        }

        if ($this->templateId == "genetika") {
            $this->printGenetikaiPdf();
            return;
        }

        if ($this->templateId == "mikrobi") {
            $this->printGenetikaiPdf();
            return;
        }



        if ($this->templateId == "vercsoport") {
            $this->printGenetikaiPdf();
            return;
        }

        if ($this->templateId == "vercsoportmail") {
            $this->printGenetikaiPdf(true);
            return;
        }

        if ($this->templateId == "vercsoportsima") {
            $this->printGenetikaiPdf();
            return;
        }

        if ($this->templateId == "szuloibeleegyezo") {
            $this->printGenetikaiPdf();
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


    private function printGHCPdf() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $cim  = $this->reservationData["irsz"]. " ". $this->reservationData["varos"].", ".$this->reservationData["utca"];

        $input = [
            "nev" => $this->pdfChars($this->reservationData["nev"]),
            "szuldatum" => date("Y. m. d.", strtotime($this->reservationData["szuldatum"])),
            "taj" => $this->pdfChars($this->reservationData["taj"]),
            "cim" => $this->pdfChars($cim),
            "levelezesicim" => $this->pdfChars($cim),
            "telefon" => $this->pdfChars($this->reservationData["telefon"]),
            "email" => $this->pdfChars($this->reservationData["email"]),
        ];

        $fileName = "ghc_senior_" . date("Y_m_d", strtotime($this->reservationData["datum"])) . "_{$this->reservationData["nev"]}.pdf";

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

    private function printNKFIHsetalo() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $cim  = $this->reservationData["irsz"]. " ". $this->reservationData["varos"].", ".$this->reservationData["utca"];

        $input = [
            "nev" => $this->pdfChars($this->reservationData["nev"]),
            "szuldatum" => date("Y. m. d.", strtotime($this->reservationData["szuldatum"])),
            "taj" => $this->pdfChars($this->reservationData["taj"]),
            "cim" => $this->pdfChars($cim),
            "levelezesicim" => $this->pdfChars($cim),
            "telefon" => $this->pdfChars($this->reservationData["telefon"]),
            "email" => $this->pdfChars($this->reservationData["email"]),
        ];

        $fileName = "NKFI_setalolap_" . date("Y_m_d", strtotime($this->reservationData["datum"])) . "_{$this->reservationData["nev"]}.pdf";

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

    private function printGenetikaiPdf($send = false) {
        if (empty($this->laborRequestData)) {
            if (!$data = sql_fetch_array(sql_query("SELECT r.* FROM labrequests r WHERE r.foglalasid=? order by r.created desc", [$this->reservationData["id"]]))) {
                die("error code 1354_2");
            }
            $this->laborRequestData = $data;
        }

        $spektrumlabService = new SpektrumlabService();

        $vkData = empty($this->laborRequestData["vkdata"]) ? [] : json_decode($this->laborRequestData["vkdata"], true);

        $cim  = $this->reservationData["irsz"]. " ". $this->reservationData["varos"].", ".$this->reservationData["utca"];
        $szulhelyido = $this->reservationData["szulhely"].", ".date("Y.m.d",strtotime($this->reservationData["szuldatum"]));

        $bekuldoNev = empty(SpektrumlabService::BEKOLDO_KOD_MAP[$this->laborRequestData["bekuldokod"]]) ? "" : SpektrumlabService::BEKOLDO_KOD_MAP[$this->laborRequestData["bekuldokod"]];
        if (Booking_Constants::SQL_DB == "keltexmed") {
            $bekuldoNev = empty(SpektrumlabService::BEKOLDO_KOD_MAP_KELTEXMED[$this->laborRequestData["bekuldokod"]]) ? "" : SpektrumlabService::BEKOLDO_KOD_MAP_KELTEXMED[$this->laborRequestData["bekuldokod"]];
        }

        $input = [
            "PaciensNev" => $this->pdfChars($this->reservationData["nev"]),
            "PaciensSzuletesiDatum" => date("Y. m. d.", strtotime($this->reservationData["szuldatum"])),
            "PaciensSzulhelyIdo" => $this->pdfChars($szulhelyido),
            "PaciensAnyjaNeve" => $this->pdfChars($this->reservationData["anyjaneve"]),
            "PaciensAzonosito" => $this->pdfChars($this->reservationData["taj"]),
            "PaciensTeljesCim" => $this->pdfChars($cim),
            "PaciensTelefon" => $this->pdfChars($this->reservationData["telefon"]),
            "PaciensEmail" => $this->pdfChars($this->reservationData["email"]),
            "MaiDatum" => date("Y.m.d"),
            "keltezes" => "Budapest, ".date("Y.m.d"),

            "BekuldoKod" => $this->laborRequestData["bekuldokod"],
            "BekuldoNev" => $bekuldoNev,
            "Gyogyszerek" => $vkData["vkgyogyszerek"],
            "TerhessegSzam" => $vkData["vkterhessegszam"],
            "TerhessegiHet" => $vkData["vkterhesseghet"],
            "GenderMale" => $this->reservationData["neme"] == 1 ? "x":"",
            "GenderFemale" => $this->reservationData["neme"] == 2 ? "x":"",
            "OrvosNev" => $spektrumlabService->params["orvosNev"],
            "OrvosPecsetszam" => $spektrumlabService->params["orvosPecsetszam"],
            "Check1" => !empty($vkData["vkcheckbox1"]) && $vkData["vkcheckbox1"] == "1" ? "x" : "",
            "Check2" => !empty($vkData["vkcheckbox2"]) && $vkData["vkcheckbox2"] == "1" ? "x" : "",
            "Check3" => !empty($vkData["vkcheckbox3"]) && $vkData["vkcheckbox3"] == "1" ? "x" : "",
            "Check4" => !empty($vkData["vkcheckbox4"]) && $vkData["vkcheckbox4"] == "1" ? "x" : "",
            "Check5" => !empty($vkData["vkcheckbox5"]) && $vkData["vkcheckbox5"] == "1" ? "x" : "",
            "Check6" => !empty($vkData["vkcheckbox6"]) && $vkData["vkcheckbox6"] == "1" ? "x" : "",
            "Check7" => !empty($vkData["vkcheckbox7"]) && $vkData["vkcheckbox7"] == "1" ? "x" : "",
            "Check8" => !empty($vkData["vkcheckbox8"]) && $vkData["vkcheckbox8"] == "1" ? "x" : "",
            "Check9" => !empty($vkData["vkcheckbox9"]) && $vkData["vkcheckbox9"] == "1" ? "x" : "",
        ];

        $fileName = "Genetikai_kerolap_es_beleegyezo({$input["PaciensNev"]})(".date("YmdHis").").pdf";

        if($this->templateId=="szuloibeleegyezo"){
            $fileName = "Szuloi_beleegyezo({$input["PaciensNev"]})(".date("YmdHis").").pdf";
        }

        $pdf = new Pdf("templates/{$this->templateFileName}");

        $raw = $pdf->needAppearances()->fillForm($input)->flatten()->toString();

        if ($send) {
            $mail = NotificationService::getDefaultMailer();

            $mail->From = Booking_Constants::COMPANY_EMAIL;
            $mail->FromName = Booking_Constants::COMPANY_NAME;

            $eles = true;

            if ($eles) {
                $mail->AddAddress(LaborKeroService::LABOR_VK_MAIL_RECIPIENT);
                $mail->AddBCC("jnsmobil@gmail.com");
                $mail->AddBCC("dudas.dorina@hungariamed.hu");
            } else {
                $mail->AddAddress("jnsmobil@gmail.com");
                $mail->AddBCC("jns@jns.hu");
                $mail->AddBCC("dudas.dorina@hungariamed.hu");
            }

            $mail->AddStringAttachment($raw, "vercsoport_kerolap.pdf");

            $subject = "Vércsoport kérőlap ".date("Y.m.d H:i", strtotime("now"));
            $mbody = "Vércsoport kérőlap";
            if (!$eles) {
                $mbody .= " teszt!";
            }

            $mail->Subject = $subject;
            $mail->Body = $mbody;
            $mail->Send();
            return;
        }


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
        $nev = empty($this->laborRequestData["nev"]) ? "Névtelen":$this->laborRequestData["nev"];

        $outFileName = "{$nev} laborlelet.pdf";
        header("Content-Type: application/pdf");
        header('Content-Disposition: attachment; filename="'.$outFileName.'"');

        $docAgent = new DocAgent();
        echo $docAgent->getDocByType(DocAgent::ASSET_LABOR_RESULT, $this->laborRequestData["id"]);

        //echo base64_decode($this->laborRequestData["resultpdf"]);
        die;
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

            $data = sql_query("SELECT * FROM dokirex_vizsgalatok v WHERE DATE(v.vizsgalatdatum)=? AND instr(telephely,'Államkincstár')", [$datum])->fetchAll(PDO::FETCH_ASSOC);

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

            $data = sql_query("SELECT * FROM dokirex_vizsgalatok v WHERE DATE(v.vizsgalatdatum)=? AND instr(telephely,'Államkincstár') AND (INSTR(vizsgalattipus, 'előzetes') OR INSTR(vizsgalattipus, 'soron'))", [$datum])->fetchAll(PDO::FETCH_ASSOC);

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

        $vizsgalatok = sql_query("SELECT * FROM dokirex_vizsgalatok v WHERE DATE(v.vizsgalatdatum)=? AND instr(telephely,'Államkincstár') AND (INSTR(vizsgalattipus, 'előzetes') OR INSTR(vizsgalattipus, 'soron'))", [$datum])->fetchAll(PDO::FETCH_ASSOC);
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

    private function aldiBeoSetup(){
        $utils = New Utils();
        $style = "body{background-color:black;color:white;font-size:18px}";
        $style.= ".error{color:red;}";
        $style.= ".update{color:green;}";
        $style.= ".bold{font-weight:bold;}";
        $style.= ".beo{color:#2acaea;}";

        $binterval = 3; //Perc
        $orvosId = 1064;
        $nap = 10;
        $aktiv = 1;
        $tipusok = "|94|";
        $helyszinId = $groupId = 0;
        $beonap = $tol = $ig = $beocegek = ""; //A cégek formátuma |cegid|
        $beosztas = [];

        $cegek = array(
            340 => array("kulcsszavak"=>array("CTP","Központ","Központ","Központ - LOG"),"groupid"=>10087),
            348 => array("kulcsszavak"=>array("üzletek"),"groupid"=>10089),
            347 => array("kulcsszavak"=>array("AIIS"),"groupid"=>10088),
        );

        echo "<style>";
        echo $style;
        echo "</style>";

        $global_errors = [];
        $q=sql_query("SELECT * FROM aldi_beosztasok_2023 WHERE statusz is null");

        
        while($r=sql_fetch_array($q)){

            $errors = $updates = [];
            //Új adatok meghatározása:
            $r["new_uzletszam"] = str_replace(".0","",$r["uzletszam"]);
            $r["new_teljescim"] = "{$r["varos"]} ({$r["irsz"]}), {$r["cim"]}";


            echo "<p class=\"bold\">Alap adatok a táblázatból:</p>";
            echo "<pre>";
            print_r($r);
            echo "</pre>";

            //Módosítások/Hibák/Frissítések:
            //-->Helyszín
            $helyszin=sql_query("SELECT * FROM helyszinek WHERE cim=?",[$r["new_teljescim"]])->fetchAll(PDO::FETCH_ASSOC);
            if(!$helyszin){
                $errors[] = "A {$r["new_teljescim"]} nem található a helyszínek között!<br>";
                if(!array_search(end($errors), $global_errors)){
                    $global_errors[] = end($errors);
                }
            }else{
                if($r["new_teljescim"]!=$r["teljescim"]){
                    sql_query("UPDATE aldi_beosztasok_2023 SET teljescim=? WHERE id=?",[$r["new_teljescim"],$r["id"]]);
                    $updates[] = "Teljes cím módosítva! ({$r["teljescim"]}->{$r["new_teljescim"]})";
                }else{
                    $helyszinId = $helyszin[0]["id"];
                }
                
            }

            //Üzletszám korrigálása:
            if(substr_count($r["uzletszam"],".0")>0){
                sql_query("UPDATE aldi_beosztasok_2023 SET uzletszam=? WHERE id=?",array($r["new_uzletszam"],$r["id"]));
                $updates[] = "Sikeres üzletszám korrigálás";
            }

            //Cégid meghatározása:
            if(empty($r["cegid"])){
                if(is_numeric($r["uzletszam"])){
                    $updates[] = "Ez egy üzlet!";
                    sql_query("UPDATE aldi_beosztasok_2023 SET cegid=? WHERE id=?",[348,$r["id"]]);
                }else{
                    foreach($cegek as $key=>$values){
                        echo "ittvagyok<br>";
                        if(in_array($r["uzletszam"],$values["kulcsszavak"])){
                            $updates[] = "Új cég azonosító: {$key}";
                            sql_query("UPDATE aldi_beosztasok_2023 SET cegid=? WHERE id=?",[$key,$r["id"]]);
                            break;
                        }
                    }
                }
            }else{
                $beocegek = "|{$r["cegid"]}|";
                $groupId = $cegek[$r["cegid"]]["groupid"];
            }

            //Dátum ellenőrzése:
            if($utils->validateDate($r["datum"],"Y-m-d")){
                $beonap = $r["datum"];
            }else{
                $errors[] = "Hibás beosztási dátum formátum!";
            }


            //Rendelési idő meghatározása
            if(empty($r["tol"] || $r["ig"])){
                $beoIdo=explode("-",$r["idopont"]);
                $tol = date("H:i",strtotime($beoIdo[0]));
                $ig = date("H:i",strtotime($beoIdo[1]));
                $updates[] = "Rendelés meghatározva {$tol}-tól, {$ig}-ig.";
                if($utils->validateDate($tol,"H:i") && $utils->validateDate($ig,"H:i")){
                    $updates[] = "A meghatározott rendelés formátuma helyes!";
                }else{
                    $errors[] = "A rendelés formátuma helytelen!";
                }
            }else{
                $tol = $r["tol"];
                $ig = $r["ig"];
            }
            
            //------------------------------------------------------------------
            echo "<p class=\"beo bold\">beosztáshoz szükséges információk:</p>";
            $beosztas[] = array(
                "orvosid" =>$orvosId, 
                "helyszinid" =>$helyszinId,
                "nap" =>$nap,
                "beonap" =>$beonap,
                "tol" => $tol,
                "ig" => $ig,
                "binterval"=>$binterval,
                "tipusok" => $tipusok,
                "aktiv" => $aktiv,
                "groupid" => $groupId,
                "beocegek" => $beocegek,
            );

            echo "<pre class=\"beo\">";
            print_r(end($beosztas));
            echo "</pre>";
            
            //------------------------------------------------------------------

            //Hiba infók:
            if(!empty($errors)){
                echo "<p class=\"error bold\">Hibák:</p>";

                echo "<pre class=\"error\">";
                print_r($errors);
                echo "</pre>";
            }  

            //Frissítési infók:
            if(!empty($updates)){
                echo "<p class=\"update bold\">Frissítések:</p>";

                echo "<pre class=\"update\">";
                print_r($updates);
                echo "</pre>";
            }

            echo "<hr>";
        }

        //Globális hibák kiírása:
        if(!empty($global_errors)){
            echo "<p class=\"error bold\">Összes hiba:</p>";
            echo "<pre class=\"error\">";
            print_r($global_errors);
            echo "</pre>";
        }

        //Beosztások:
        if(!empty($beosztas)){
            /*echo "<pre>";
            print_r($beosztas);
            echo "</pre>";*/
            foreach($beosztas as $beo){
                echo "<p>INSERT INTO orvos_beosztas_new 
                         SET orvosid={$beo["orvosid"]},helyszinid={$beo["helyszinid"]},nap={$beo["nap"]},beonap=\"{$beo["beonap"]}\",tol=\"{$beo["tol"]}\",ig=\"{$beo["ig"]}\",binterval={$beo["binterval"]},tipusok=\"{$beo["tipusok"]}\",aktiv={$beo["aktiv"]},groupid={$beo["groupid"]},beocegek=\"{$beo["beocegek"]}\";</p>";
            }
            
        }

        return;
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
        "Dr. Hetei Tünde" => 91881,
        "Dr. Benkő Csongor" => 93266,
        "Dr. Szénási Pál" => 25083,
        "Dr. Tóth-Kovalik Ádám" => 96276,
        "Dr. Váradi Zoltán" => 95840,
        "Dr. Gergely Melinda" => 77490,
        "Dr. Szász Angéla Imola" => 63018,
        "Dr. Hollandi Erzsébet" =>60694,
        "Dr. Balogh Zsuzsanna" => 81137,
        "Dr. Nemecz Zsuzsanna" => 37220,
        "Dr. Szűcs Rozália" => 31436,
        "Dr.Kiss Beáta Margit" => 65886,
        "Dr. Diczku Valéria" => 38122,
        "Dr. Finebone Adawari Godswill" => 91317,
        "Dr. Angyalosy Levente" => 83775,
    ];

    private function innioErtesites(){
        /*
        Kilistázom az összes innio-s dolgozót és egyesével vizsgálom meg őket, hogy kell-e értesítést küldenem nekik.
        legalább 2 féle képpen kéne megadjam, intervallumosan és napokkal
        */

        $verziok = [
            "1_month_before_expiration",
            "2_weeks_before_expiration",
            "1_week_before_expiration",
            "1_week_after_expiration",
            "2_weeks_after_expiration",
        ];

        $notificationService = new NotificationService();
        $telephely = "Jenbacher Gas Engines Hungary Kft.";
        $method = "";
        $waitingPeriod = 7; //napok
        $x=0;

        //Értesítő sablon kiválasztása
        $ertesito = sql_query("SELECT * FROM ertesito_uzenetek WHERE cegid=? AND tipus=? AND verzio=?",[637,"remindertofogleu","1_month_before_expiration"])->fetch(PDO::FETCH_ASSOC);

        //Cég dolgozóinak kilistázása
        $q=sql_query("SELECT * FROM dokirex_vizsgalatok WHERE telephely=? GROUP BY paciensid",[$telephely]);

        while($patientList=sql_fetch_array($q)){


            if(!empty($patientList["paciensid"])){ //Ha nincs tajszám, akkor az eredeti első sorra fusson tovább, hagyja figyelmen kívül  a taj alapú keresést
                $qr = sql_query("SELECT * FROM dokirex_vizsgalatok WHERE paciensid=? ORDER BY datum DESC LIMIT 1",array($patientList["paciensid"]));
                $r = sql_fetch_array($qr);
            }else{
                $r = $patientList;
            }

            if(empty($r["email"])) continue; //Ha nincsen email cím, ne is mennyen tovább folytassa a köv. dolgozóval, úgysem tudok értesítést kiküldeni

            //-> x nappal lejárat előtt
            if(isset($_GET["method"]) && $_GET["method"]=="days_to_expiry"){
                if(strtotime($r["ervenyesseg"])<strtotime("now + {$_GET["days"]} days")){

                    $found=0;
                    $notifications = $notificationService->checkPreviousNotifications($r["email"],"remindertofogleu");

                    //Értesítés 1 hónappal lejárat előtt
                    if(strtotime($r["ervenyesseg"])<=strtotime("now + 1 month") && strtotime($r["ervenyesseg"])>=strtotime("now + 2 weeks")){
                        $found=1;
                        $ertesito = sql_query("SELECT * FROM ertesito_uzenetek WHERE cegid=? AND tipus=? AND verzio=?",[637,"remindertofogleu","1_month_before_expiration"])->fetch(PDO::FETCH_ASSOC);
                        $ertesito["szoveg"] = str_replace("#nev#",$r["nev"],$ertesito["szoveg"]);
                        $ertesitesAzonosito = md5($r["ervenyesseg"].$r["email"]."now +1 month");

                        if(!empty($notifications)){
                            $key = array_search($ertesitesAzonosito, array_column($notifications, "objectid"));
                            echo "md5: {$ertesitesAzonosito}<br>";
                            if($key!==false){
                                echo "<b>Már ment ki értesítés ekkor: {$notifications[$key]["datum"]}</b><br>";
                            }else{
                                echo "<b>Menne értesítés most.</b><br>";
                                $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                                $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                            }
                        }else{
                            echo "<b>Menne értesítés most.</b><br>";
                            $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                            $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                        }

                        //$notificationService->sendReminderToFogleu($r["email"],$r["nev"],$ertesito);
                        echo "Lejárat:{$r["ervenyesseg"]} -> Értesítés 1 hónappal lejárat előtt<br>";
                        if(!empty($notifications) && $key!==false) echo "<b>Utolsó értesítés: {$notifications[$key]["datum"]}</b><br>";
                        echo "Név: {$r["nev"]}, email: {$r["email"]}<br><br>";
                    }

                    //Értesítés 2 héttel lejárat előtt
                    if(strtotime($r["ervenyesseg"])<=strtotime("now + 2 weeks") && strtotime($r["ervenyesseg"])>=strtotime("now + 1 week")){
                        $found=1;
                        $ertesito = sql_query("SELECT * FROM ertesito_uzenetek WHERE cegid=? AND tipus=? AND verzio=?", [637,"remindertofogleu","2_weeks_before_expiration"])->fetch(PDO::FETCH_ASSOC);
                        $ertesito["szoveg"] = str_replace("#nev#",$r["nev"],$ertesito["szoveg"]);
                        $ertesitesAzonosito = md5($r["ervenyesseg"].$r["email"]."now +2 weeks");

                        if(!empty($notifications)){
                            $key = array_search($ertesitesAzonosito, array_column($notifications, "objectid"));
                            echo "md5: {$ertesitesAzonosito}<br>";
                            if($key!==false){
                                echo "<b>Már ment ki értesítés ekkor: {$notifications[$key]["datum"]}</b><br>";
                            }else{
                                echo "<b>Menne értesítés most.</b><br>";
                                $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                                $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                            }
                        }else{
                            echo "<b>Menne értesítés most.</b><br>";
                            $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                            $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                        }

                        //$notificationService->sendReminderToFogleu($r["email"],$r["nev"],$ertesito);
                        echo "Lejárat:{$r["ervenyesseg"]} -> Értesítés 2 héttel lejárat előtt<br>";
                        if(!empty($notifications) && $key!==false) echo "<b>Utolsó értesítés: {$notifications[$key]["datum"]}</b><br>";
                        echo "Név: {$r["nev"]}, email: {$r["email"]}<br><br>";
                    }

                    //Értesítés 1 héttel lejárat előtt
                    if(strtotime($r["ervenyesseg"])<=strtotime("now + 1 week") && strtotime($r["ervenyesseg"])>=strtotime("now")){
                        $found=1;
                        $ertesito = sql_query("SELECT * FROM ertesito_uzenetek WHERE cegid=? AND tipus=? AND verzio=?", [637,"remindertofogleu","1_week_before_expiration"])->fetch(PDO::FETCH_ASSOC);
                        $ertesito["szoveg"] = str_replace("#nev#",$r["nev"],$ertesito["szoveg"]);
                        $ertesitesAzonosito = md5($r["ervenyesseg"].$r["email"]."now +1 week");

                        if(!empty($notifications)){
                            $key = array_search($ertesitesAzonosito, array_column($notifications, "objectid"));
                            echo "md5: {$ertesitesAzonosito}<br>";
                            if($key!==false){
                                echo "<b>Már ment ki értesítés ekkor: {$notifications[$key]["datum"]}</b><br>";
                            }else{
                                echo "<b>Menne értesítés most.</b><br>";
                                $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                                $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                            }
                        }else{
                            echo "<b>Menne értesítés most.</b><br>";
                            $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                            $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                        }

                        //$notificationService->sendReminderToFogleu($r["email"],$r["nev"],$ertesito);
                        echo "Lejárat:{$r["ervenyesseg"]} -> Értesítés 1 héttel lejárat előtt<br>";
                        if(!empty($notifications) && $key!==false) echo "<b>Utolsó értesítés: {$notifications[$key]["datum"]}</b><br>";
                        echo "Név: {$r["nev"]}, email: {$r["email"]}<br><br>";
                    }

                    //Értesítés 1 héttel lejárat után
                    if(strtotime($r["ervenyesseg"])<=strtotime("now - 1 week") && strtotime($r["ervenyesseg"])>=strtotime("now - 2 weeks")){
                        $found=1;
                        $ertesito = sql_query("SELECT * FROM ertesito_uzenetek WHERE cegid=? AND tipus=? AND verzio=?", [637,"remindertofogleu","1_week_after_expiration"])->fetch(PDO::FETCH_ASSOC);
                        $ertesito["szoveg"] = str_replace("#nev#",$r["nev"],$ertesito["szoveg"]);
                        $ertesitesAzonosito = md5($r["ervenyesseg"].$r["email"]."now -1 week");

                        if(!empty($notifications)){
                            $key = array_search($ertesitesAzonosito, array_column($notifications, "objectid"));
                            echo "md5: {$ertesitesAzonosito}<br>";
                            if($key!==false){
                                echo "<b>Már ment ki értesítés ekkor: {$notifications[$key]["datum"]}</b><br>";
                            }else{
                                echo "<b>Menne értesítés most.</b><br>";
                                $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                                $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                            }
                        }else{
                            echo "<b>Menne értesítés most.</b><br>";
                            $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                            $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                        }
                        //$notificationService->sendReminderToFogleu($r["email"],$r["nev"],$ertesito);
                        echo "Lejárat:{$r["ervenyesseg"]} -> Értesítés 1 héttel lejárat után<br>";
                        if(!empty($notifications) && $key!==false) echo "<b>Utolsó értesítés: {$notifications[$key]["datum"]}</b><br>";
                        echo "Név: {$r["nev"]}, email: {$r["email"]}<br><br>";
                    }

                     //Értesítés 2 héttel lejárat után
                     if(strtotime($r["ervenyesseg"])<=strtotime("now - 2 weeks")){
                        $found=1;
                        $ertesito = sql_query("SELECT * FROM ertesito_uzenetek WHERE cegid=? AND tipus=? AND verzio=?", [637,"remindertofogleu","2_weeks_after_expiration"])->fetch(PDO::FETCH_ASSOC);
                        $ertesito["szoveg"] = str_replace("#nev#",$r["nev"],$ertesito["szoveg"]);
                        $ertesitesAzonosito = md5($r["ervenyesseg"].$r["email"]."now -2 weeks");

                        if(!empty($notifications)){
                            $key = array_search($ertesitesAzonosito, array_column($notifications, "objectid"));
                            echo "md5: {$ertesitesAzonosito}<br>";
                            if($key!==false){
                                echo "<b>Már ment ki értesítés ekkor: {$notifications[$key]["datum"]}</b><br>";
                            }else{
                                echo "<b>Menne értesítés most.</b><br>";
                                $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                                $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                            }
                        }else{
                            echo "<b>Menne értesítés most.</b><br>";
                            $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                            $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                        }

                        //$notificationService->sendReminderToFogleu($r["email"],$r["nev"],$ertesito);
                        echo "Lejárat:{$r["ervenyesseg"]} -> Értesítés 2 héttel lejárat után<br>";
                        if(!empty($notifications) && $key!==false) echo "<b>Utolsó értesítés: {$notifications[$key]["datum"]}</b><br>";
                        echo "Név: {$r["nev"]}, email: {$r["email"]}<br><br>";
                    }

                    if($found==0){
                        $ertesito = sql_query("SELECT * FROM ertesito_uzenetek WHERE cegid=? AND tipus=? AND verzio=?", [637,"remindertofogleu","1_week_after_expiration"])->fetch(PDO::FETCH_ASSOC);
                        $ertesito["szoveg"] = str_replace("#nev#",$r["nev"],$ertesito["szoveg"]);
                        $ertesitesAzonosito = md5($r["ervenyesseg"].$r["email"]."now -1 week");

                        if(!empty($notifications)){
                            $key = array_search($ertesitesAzonosito, array_column($notifications, "objectid"));
                            echo "md5: {$ertesitesAzonosito}<br>";
                            if($key!==false){
                                echo "<b>Már ment ki értesítés ekkor: {$notifications[$key]["datum"]}</b><br>";
                            }else{
                                echo "<b>Menne értesítés most.</b><br>";
                                $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                                $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                            }
                        }else{
                            echo "<b>Menne értesítés most.</b><br>";
                            $notificationService->sendReminderToFogleu($r["email"],$ertesito);
                            $notificationService->createNotificationRecord("remindertofogleu",$ertesitesAzonosito,$r["email"],$ertesito["targy"],$ertesito["szoveg"]);
                        }
                        echo "<b>Ebbe a páciensbe nem futott bele: Név: {$r["nev"]}, email: {$r["email"]} -> Lejárat:{$r["ervenyesseg"]}</b><br><br>";
                    }
                    continue;
                }
            }
        }

        die();

        //Metódus választás:
        //-->ról / ig
        if(isset($_GET["method"]) && $_GET["method"]=="interval"){
            $method = " AND ervennyesseg BETWEEN '{$_GET["tol"]}' AND '{$_GET["ig"]}'";
        }

        //-> x nappal lejárat előtt
        if(isset($_GET["method"]) && $_GET["method"]=="days_to_expiry"){
            $method = "AND ervenyesseg <= '".date("Y-m-d",strtotime("now + {$_GET["days"]} days"))."'";
        }

        echo "SELECT * FROM dokirex_vizsgalatok WHERE telephely=? {$method}";

        //sql_query("SELECT * FROM dokirex_vizsgalatok WHERE telephely=? {$method}",array($telephely));

    }

    private function bfkh_email_kuldes(){
        $notificationService = new NotificationService();
        $q=sql_query("SELECT * FROM bfkh_email_kuldes WHERE sent is NULL");

        $content = sql_query("SELECT * FROM ertesito_uzenetek WHERE id=?",[28])->fetch(PDO::FETCH_ASSOC);

        while($r=sql_fetch_array($q)){
            $notificationService->sendBFKHmarketing($r["email"],$content);
            echo "e-mail sent to {$r["email"]}.<br>";
            $notificationService->createNotificationRecord("bfkhmarketing",null,$r["email"],$content["targy"],$content["szoveg"]);
            echo "notification record created in database.<br>";
            sql_query("UPDATE bfkh_email_kuldes SET sent=NOW() WHERE id=?",[$r["id"]]);
            echo "list object updated.<br>";
        }
        die("done.");
    }


    private function printSpektrumLabMatrica() {
        $fileName = "sp_matrica_".date("Y-m-d_H_i").".bat";
        header("Pragma: no-cache");
        header("Cache-Control: no-store, no-cache");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: must-revalidate");
        header('Content-transfer-encoding: binary');
        header("Content-Type: application/octet-stream");
        header('Content-Disposition: attachment; filename="'.$fileName.'"');

        echo 'echo off

echo N>txt.txt
echo R190,0,"">>txt.txt
echo B82,32,0,2,2,6,69,B,"000010414001">>txt.txt
echo A420,75,1,5,1,1,N,"1">>txt.txt
echo A36,0,0,2,1,1,N,"au5800-1_1,au5800-2_1,dxi8">>txt.txt
echo A156,16,0,2,1,1,N,"1/04.14.">>txt.txt
echo A36,126,0,3,1,1,N,"SERUM">>txt.txt
echo A36,147,0,2,1,1,N,"Teszt Beteg/1964.12.05.">>txt.txt
echo A36,164,0,2,1,1,N,"Teszt bekuldo">>txt.txt
echo P1>>txt.txt

copy /B txt.txt \\\\127.0.0.1\zebra1
';

        die;
    }

    private function printMunkaNaplo() {
        header("Content-type: text/html; charset=UTF-8");

        $_POST = sql_fetch_array(sql_query("select * from munkahigienes_felmeres where id=? and rn=?", [$_GET["mid"], $_GET["p"]]));
        ob_start();
        include (Booking_Constants::APP_PATH."public/images/felmeres/munkaNaploTemplate.php");
        $templateTorzs = ob_get_contents();
        ob_end_clean();

        include (Booking_Constants::APP_PATH."public/images/felmeres/munkaNaploAlairas.php");
        $templateSign = ob_get_contents();
        ob_end_clean();

        include (Booking_Constants::APP_PATH."public/images/felmeres/munkaNaploHeader.php");
        $templateHeader = ob_get_contents();
        //ob_end_clean();

        $templateTorzs = str_replace("#buttonhide#", "display:none;", $templateTorzs);

        echo $templateHeader;
        echo $templateTorzs;
        echo $templateSign;
    }

    private function printMunkaNaploPDF() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $mid = $_GET["mid"];
        $p = $_GET["p"];

        header("Content-type: text/html; charset=UTF-8");

        $pdfFileName = Booking_Constants::DOCUMENT_PATH . "munkanaplo" . md5(rand(1, 10000)) . ".pdf";
        $output = `chromium --headless --print-to-pdf="{$pdfFileName}" --no-pdf-header-footer --no-sandbox "https://bejelentkezes.hungariamed.hu/admin/index.php?print&template=munkanaplo&mid={$mid}&p={$p}"`;

        header("Content-Type: application/pdf");
        header('Content-Disposition: attachment; filename="MunkaNaplo.pdf"');
        echo file_get_contents($pdfFileName);
        unlink($pdfFileName);
    }


    public function printBeoPdf($beoId, $nap):string {
        //$beoId = $_GET["printbeopdf"];
        //$nap = $_GET["nap"];
        $timeFrom = "{$nap} 00:00:00";
        $timeTo = "{$nap} 23:59:59";

        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        if (!$beoData = sql_query("select * from orvos_beosztas_new where id=?", [$beoId])->fetch(PDO::FETCH_ASSOC)) {
            die("beo not found");
        }

        if (!isset($_SESSION["adminuser"])) {
            die("error 401");
        }

        $pdfHelyszinMap = [
            681 => "templates/szurovizsgalat_form_szugy.pdf",
            682 => "templates/szurovizsgalat_form_nograd.pdf",
            693 => "templates/szurovizsgalat_form_egyhazasgerge.pdf",
            685 => "templates/szurovizsgalat_form_endrefalva.pdf",
            684 => "templates/szurovizsgalat_form_szirak.pdf",
            687 => "templates/szurovizsgalat_form_varsany.pdf",
            690 => "templates/szurovizsgalat_form_nogradkovesd.pdf",
            689 => "templates/szurovizsgalat_form_dejtar.pdf",
            688 => "templates/szurovizsgalat_form_karancslapujto.pdf",
            686 => "templates/szurovizsgalat_form_bercel.pdf",
            696 => "templates/szurovizsgalat_form_kisecset.pdf",
            701 => "templates/szurovizsgalat_form_diosjeno.pdf",
            697 => "templates/szurovizsgalat_form_szecsenyfelfalu.pdf",

            958 => "templates/hazaipalya_cegled.pdf",
            952 => "templates/hazaipalya_cegledbercel.pdf",
            956 => "templates/hazaipalya_dany.pdf",
            954 => "templates/hazaipalya_felsopakony.pdf",
            948 => "templates/hazaipalya_koka.pdf",
            950 => "templates/hazaipalya_korostetetlen.pdf",
            953 => "templates/hazaipalya_nyarsapat.pdf",
            957 => "templates/hazaipalya_pilis.pdf",
            1077 => "templates/hazaipalya_kocser2.pdf",
            955 => "templates/hazaipalya_tapiobicske.pdf",
            949 => "templates/hazaipalya_tarnok.pdf",
            1051 => "templates/hazaipalya_pilisszentkereszt2.pdf",
            951 => "templates/hazaipalya_ujszilvas.pdf",
            1078 => "templates/hazaipalya_danszentmiklos2.pdf",
            1079 => "templates/hazaipalya_kistarcsa.pdf",
            1088 => "templates/hazaipalya_hevizgyork.pdf",
        ];

        //958 => "templates/hazaipalya_danszentmiklos.pdf",
        //958 => "templates/hazaipalya_pilisszentkereszt.pdf",

        $orvosId = $beoData["orvosid"];
        $helyszinId = $beoData["helyszinid"];
        $pdfLocation = "templates/szurovizsgalat_form.pdf";

        if (isset($pdfHelyszinMap[$helyszinId])) {
            $pdfLocation = $pdfHelyszinMap[$helyszinId];
        }
        if ($helyszinId == 693) {
            $pdfLocation = "templates/szurovizsgalat_form_egyhazasgerge.pdf";
        }
        if ($helyszinId == 685) {
            $pdfLocation = "templates/szurovizsgalat_form_endrefalva.pdf";
        }
        if ($helyszinId == 684) {
            $pdfLocation = "templates/szurovizsgalat_form_szirak.pdf";
        }
        if ($helyszinId == 702) {
            $pdfLocation = "templates/szurovizsgalat_form_varsany.pdf";
        }


        //csak a beosztás
        /*
        $reservations = sql_query("SELECT f.*, h.cim as helyszin, c.megnev as cegnev, o.nev as orvosnev, d.id as docid, sz.megnev as szurestipusnev, if(f.telephelyid=0, f.telephely, v.megnev) as telephely from foglalasok f
                        LEFT JOIN cegek c on c.id=f.cegid
                        LEFT JOIN szurestipusok sz on sz.id=f.szurestipusid
                        LEFT JOIN orvosok o on o.id=f.orvosassigned
                        LEFT JOIN helyszinek h on h.id=f.helyszinid
                        LEFT JOIN dokumentumok d on d.foglalasid=f.id
                        LEFT JOIN cegvars v on v.id=f.telephelyid
                        WHERE f.datum>=? and f.datum<? and (f.helyszinid=? or sz.webdoktor=1) and f.orvosassigned in (0, ?) and f.nev<>'nincs név'
                        GROUP BY f.id order by f.datum", [$timeFrom, $timeTo, $helyszinId, $orvosId])->fetchAll(PDO::FETCH_ASSOC);
        */

        //egész nap abc sorrendben
        $reservations = sql_query("SELECT f.*, h.cim as helyszin, c.megnev as cegnev, o.nev as orvosnev, d.id as docid, sz.megnev as szurestipusnev, if(f.telephelyid=0, f.telephely, v.megnev) as telephely from foglalasok f
                        LEFT JOIN cegek c on c.id=f.cegid
                        LEFT JOIN szurestipusok sz on sz.id=f.szurestipusid
                        LEFT JOIN orvosok o on o.id=f.orvosassigned
                        LEFT JOIN helyszinek h on h.id=f.helyszinid                
                        LEFT JOIN dokumentumok d on d.foglalasid=f.id
                        LEFT JOIN cegvars v on v.id=f.telephelyid
                        WHERE f.datum>=? and f.datum<? and (f.helyszinid=? or sz.webdoktor=1) and f.nev<>'nincs név'
                        GROUP BY f.id order by f.nev", [$timeFrom, $timeTo, $helyszinId])->fetchAll(PDO::FETCH_ASSOC);

        $savedPdfs = [];
        foreach ($reservations as $key => $reservation) {
            $saveName = "templates/".session_id()."_{$key}.pdf";
            $savedPdfs[] = $saveName;

            $neme = "";
            if ($reservation["neme"] == 1) {
                $neme = "Férfi";
            }
            if ($reservation["neme"] == 2) {
                $neme = "Nő";
            }

            $tz = new DateTimeZone("Europe/Brussels");
            try {
                $age = DateTime::createFromFormat("Y-m-d", $reservation["szuldatum"], $tz)->diff(new DateTime('now', $tz))->y;
            } catch (Error $e) {
                echo "Hibás születési dátum: {$reservation["nev"]}";
                die;
            }

            $input = [
                "datum" => date("Y.m.d", strtotime($reservation["datum"])),
                "datum2" => date("y.m.d", strtotime($reservation["datum"])),
                "helyszin" => $this->pdfChars($reservation["helyszin"]),
                "helyszin2" => $this->pdfChars($reservation["helyszin"]),
                "nev" => $this->pdfChars($reservation["nev"]),
                "cim" => $this->pdfChars($reservation["irsz"]." ".$reservation["varos"].", ".$reservation["utca"]),
                "anyjaneve" => $this->pdfChars($reservation["anyjaneve"]),
                "szuldatum" => date("Y.m.d", strtotime($reservation["szuldatum"])),
                "eletkor" => $age,
                "neme" => $this->pdfChars($neme),
                "szurestipus" => $this->pdfChars($reservation["szurestipusnev"]),
            ];
            $pdf = new Pdf($pdfLocation);
            $pdf->fillForm($input)->needAppearances()->flatten()->saveAs($saveName);
        }

        $multiPagePdf = new Pdf($savedPdfs);

        $fileName = $reservation["helyszin"]." - ".date("Y-m-d", strtotime($reservation["datum"])).".pdf";
        $raw = $multiPagePdf->toString();

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
        die;
    }

    public function generate_aldi_vv(){
        $laborRequestData = sql_query("SELECT * FROM labrequests WHERE bekuldokod='000000477' AND INSTR(created,'2024') and status='done';")->fetchAll(PDO::FETCH_ASSOC);
        $path = __DIR__."/pages_admin/other/tmp/";

        //echo count($laborRequestData)." db sor.";

        foreach($laborRequestData as $data){
            $filename = $data["id"].".pdf";
            $docAgent = new DocAgent();
            $pdf = $docAgent->getDocByType(DocAgent::ASSET_LABOR_RESULT, $data["id"]);
            file_put_contents($path.$filename,$pdf);
            echo "{$data["id"]}.pdf ({$data["nev"]}) is done.<br>";
        }
    }

    public function generateAszKartyak(){
        $q=sql_query("SELECT * FROM asz_dolgozok WHERE email IS NOT NULL AND finished IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        $sampleFilePath = __DIR__."/../public/admin/templates/peldakep_urlapos.pdf";
        $docPath =  __DIR__ . "/pages_admin/other/tmp/";

        $body = "";
        $body.="<p>Tisztelt Hölgyem/Uram!</p>";

        $body.="<p>Szeretnénk tájékoztatni, hogy a szerződés szerinti virtuális egészség kártya elkészült az Ön részére.</p>";

        $body.="<p>Melynek a Hungária Med-M Kft 1135 Bp., Jász u. 33-35. alatti vizsgáló helyszínen történő bemutatásával ";
        $body.="jogosult a szerződés szerinti egyszeri éves komplex szűrővizsgálatot igénybe venni, valamint minden ";
        $body.="további pluszban kért szolgáltatásunk árából 10% kedvezményre jogosult a szerződés teljes időtartama alatt. </p>";

        $body.="<p>Továbbá ezzel a kártyával igazolhatja közvetlen hozzátartozóját (szülő, testvér, házastárs, gyermek) is, ";
        $body.="hogy jogosult az egyszeri komplex szűrővizsgálat igénybevételére, valamint a plusz szolgáltatásokat 10% ";
        $body.="kedvezményes áron igénybe venni a szerződés teljes időtartama alatt.</p>";

        $body.="<p>A komplex szűrővizsgálat a közvetlen hozzátartozók részére díjköteles, amit a helyszínen szükséges ";
        $body.="kiegyenlíteniük. Szerződés szerinti ára 127.900 Forint .</p>";

        $body.="Rendelőnkben az alábbi fizetési lehetőségek közül választhat:";
        $body.="<ul style=\"margin-left:10px\">";
        $body.="<li style=\"list-style: disc;\">Készpénz</li>";
        $body.="<li style=\"list-style: disc;\">Bankkártya</li>";
        $body.="<li style=\"list-style: disc;\">Szép-kártya (OTP, MBH, K&H)</li>";
        $body.="<li style=\"list-style: disc;\">Egészségpénztári kártya</li>";
        $body.="</ul>";
        $body.="<p>Kérjük áfás számla igényét jelezze a recepciós kollégáinknál.</p>";
        $body.="<br>";

        $body.="<p>Üdvözlettel:</p><br>";
        $body.="<p>Pongor Anita</p>";
        $body.="<p>call center munkatárs</p>";
        $body.="<p>+36 30 337 8223</p>";
        $body.="<p>Hungária Med-M Kft.</p>";
        $body.="<p>1135 Budapest Jász u. 33.-35.</p>";
        $body .= "<a href=\"https://www.hungariamed.hu\" target=\"_blank\"><img src=\"https://uj.hungariamed.hu/assets/hmm_logo_nagy.png\" width=\"150px\" style=\"margin:10px\"></a>";


        foreach($q as $p){
            //$pdf = new Pdf($sampleFilePath);
            $filename = str_replace(" ", "_", trim($p["nev"])) . ".pdf";
            /*$input = array(
                "nev" => $p["nev"],
                "azonosito"=>$p["felhId"],
            );*/

            //echo $result = $pdf->fillForm($input)->flatten()->saveAs($docPath . $filename);

            $notificationService = new NotificationService();

            $mail = $notificationService->getDefaultMailer();
            $mail->AddAddress($p["email"]);
            //$mail->AddAddress("marton.gergely@hungariamed.hu");
            $mail->AddBCC("tesztemail@hungariamed.hu");
            $mail->Subject = "Virtuális Egészség kártya - Állami Számvevőszék szűrőcsomag igénybevételéhez";

            $mail->Body = $body;

            $mail->AddAttachment($docPath . $filename);
            $mail->AddAttachment( __DIR__."/../public/admin/templates/Árjegyzék_szakorvosi_vizsgálatokhoz.pdf");
            $mail->AddAttachment( __DIR__."/../public/admin/templates/Labor_kiegészítés_javaslat_03.24..xlsx");
            $mail->AddAttachment( __DIR__."/../public/admin/templates/Önköltségesen_igénybe_vehető_szolgáltatások_bővített_tájékoztató.docx");
            $mail->Send();
        }

        //echo "<pre>";
        //print_r($q);
        //echo "</pre>";

    }
}

