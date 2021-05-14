<?php

class AdminDicomPage extends AdminCorePage
{

    private $dicomService;
    public function __construct()
    {
        parent::__construct();


        $this->dicomService = new DicomService();

        if (isset($_REQUEST["generalsearch"])) {
            $images = $this->dicomService->getImages(["search" => $_REQUEST["term"]]);
            echo $this->listDicomEntries($images);
            die;
        }

        if (isset($_GET["getimage"])) {
            $content = $this->dicomService->getRawImage($_GET["getimage"]);

            //header('Content-Disposition: attachment; filename="'.$content["fileName"].'.png"');
            header("Content-Type: image/png");

            echo $content["imageData"];
            die();
        }

        if (isset($_GET["downloaddicomfile"])) {
            $content = $this->dicomService->getRawDicomFile($_GET["downloaddicomfile"]);

            header("Pragma: no-cache");
            header("Cache-Control: no-store, no-cache");
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header("Cache-Control: must-revalidate");
            header('Content-transfer-encoding: binary');
            header('Content-Disposition: attachment; filename="'.$content["fileName"].'.dcm"');
            header("Content-Type: application/dicom");

            echo $content["file"];
            die();
        }

    }

    public function showPage()
    {
        //if (!$this->adminUtils->szurestipusModJog()) {
        //    return;
        //}


        echo "<div style='margin-bottom:20px;'>";

        echo "<input data-page='dicom' data-resultdiv='dicomlist' type='text' id='generalsearch' value='' placeholder='Keresés...'/>";

        echo "</div>";



        echo "<div id='dicomlist'>";

        $images = $this->dicomService->getImages();
        echo $this->listDicomEntries($images);

        echo "</div>";
    }


    private function listDicomEntries($images) {
        $html = "";

        $html.= "<table cellpadding='0' cellspacing='0' border='0'>";
        $html.= "<tr style='background:#eee;'>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Műveletek</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 5px;'>Időpont</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Paciens neve</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Szül. dátum</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>TAJ szám</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Megjegyzés</td>";
        $html.= "</tr>";

        foreach ($images as $row) {
            $tc = "tcella";
            if (!isset($first)) {
                $html.= "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (empty(trim($row["patientName"]))) {
                $row["patientName"] = "nincs neve";
            }
            $html.= "<tr>";

            $html.= "<td nowrap valign='top'><div class='{$tc}'>";
            $html.= "[<a style='color:#00f;' target='_blank' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&getimage={$row["id"]}'>kép megtekintése</a>] ";
            $html.= "[<a style='color:#00f;' target='_blank' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&downloaddicomfile={$row["id"]}'>DICOM file letöltése</a>]";
            $html.= "</td>";

            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["contentDate"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["patientName"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["patientBirthDate"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["patientOtherIDs"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["studyDescription"]}</div></td>";

            $html.= "</tr>";
            $html.= "<tr><td colspan='8' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        $html.= "</table>";

        return $html;
    }

}