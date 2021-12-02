<?php

class AdminDicomPage extends AdminCorePage
{

    private $dicomService;
    public function __construct()
    {
        parent::__construct();


        $this->dicomService = new DicomService();

        if (isset($_REQUEST["generalsearch"])) {
            $patients = $this->dicomService->getPatients(["search" => $_REQUEST["term"]]);
            echo $this->listDicomEntries($patients);
            die;
        }

        if (isset($_GET["dcegfilter"])) {
            $this->dicomService->setSelectedCompany($_GET["dcegfilter"]);
            header("location:index.php?page={$_GET["page"]}");
            die;
        }



        if (isset($_GET["getimage"])) {
            $content = $this->dicomService->getRawImage($_GET["getimage"]);

            header("Content-Type: image/png");
            if (isset($_GET["thumb"])) {
                header("Content-Type: image/jpeg");
                imagejpeg($content["imageData"]);
            } else {
                header("Content-Type: image/png");
                imagepng($content["imageData"]);
            }

            die();
        } //lemondás

        if (isset($_GET["displayimage"])) {
            echo $this->displayImageEditor($_GET["displayimage"]);
            die;
        }

        if (isset($_POST["showimagelist"])) {
            $patients = $this->dicomService->getPatients(["byuid" => $_POST["showimagelist"]]);
            echo $this->showImageList($patients[0]["patientID"]);
            die;
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

        $GLOBALS["javascript"][] = "dicom.js?v=".date("YmdHi");
    }

    public function showPage()
    {
        if (!$this->adminUser->dicomAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        $GLOBALS["subtitle"] = "DICOM";

        echo "<div style='margin-bottom:20px;'>";
        echo $this->cegFilter();
        echo "&nbsp;&nbsp;<input data-page='dicom' data-resultdiv='dicomlist' type='text' id='generalsearch' value='' placeholder='Keresés...'/>&nbsp;";
        echo "</div>";


        echo "<div id='dicomlist'>";
        $patients = $this->dicomService->getPatients();
        echo $this->listDicomEntries($patients);
        echo "</div>";
    }


    private function listDicomEntries($images) {
        $html = "";

        $html.= "<table cellpadding='0' cellspacing='0' border='0' width='100%;'>";
        $html.= "<tr style='background:#eee;'>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Időpont</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Klinika</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Gép</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:10px;'></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:240px;'>Paciens neve</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:100px;'>Szül. dátum</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:100px;'>TAJ szám</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Megjegyzés</td>";
        $html.= "</tr>";

        foreach ($images as $row) {
            $patientData = $this->patinentService->getPatinentByTaj($row["patientOtherIDs"]);
            $machineName = "{$row["manufacturer"]} {$row["manufacturerModelName"]}";

            $tc = "tcella";
            if (!isset($first)) {
                $html.= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (empty(trim($row["patientName"]))) {
                $row["patientName"] = "nincs neve";
            }
            $html.= "<tr>";

            $html.= "<td nowrap valign='top'><div class='{$tc}'>";
            $html.= "<a style='' onclick='toggleDicomImageRow(\"{$row["uid"]}\");return false;' href='#'>{$row["imageNum"]} kép</a> ";
            //$html.= "[<a style='color:#00f;' target='_blank' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&displayimage={$row["uid"]}'>kép megtekintése</a>] ";
            //$html.= "[<a style='color:#00f;' target='_blank' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&downloaddicomfile={$row["uid"]}'>DICOM file letöltése</a>]";
            $html.= "</td>";

            $html.= "<td nowrap valign='top'><div class='{$tc}'>".date("Y-m-d H:i", strtotime($row["datum"]))."</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["institutionName"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$machineName}</div></td>";
            if (!empty($patientData)) {
                $html .= "<td nowrap valign='top'><div class='{$tc}'><i title='pacienssel összekapcsolva' class='fas fa-link'></i></div></td>";
                $html .= "<td nowrap valign='top'><div class='{$tc}'><a target='_blank' href='index.php?page=patients&szerk={$patientData["id"]}'>{$row["patientName"]}</a></div></td>";
            } else {
                $html .= "<td nowrap valign='top'></td>";
                $html .= "<td nowrap valign='top'><div class='{$tc}'>{$row["patientName"]}</div></td>";
            }

            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["patientBirthDate"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["patientOtherIDs"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["studyDescription"]}</div></td>";

            $html.= "</tr>";
            $html.= "<tr><td colspan='10' ><div id='imagerow{$row["uid"]}' style='padding:10px 0px 10px 0px;display:none;'>";
            $html.= "</div></td></tr>";
            $html.= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        $html.= "</table>";

        return $html;
    }

    private function displayImageEditor($id):string {
        $dicomData = $this->dicomService->getDicomEntry($id);

        $html = "<!DOCTYPE html>";
        $html.= "<head>";
        $html.= "<title>{$dicomData["patientName"]} DICOM image</title>";
        $html.= "<script src='https://bejelentkezes.hungariamed.hu/js/panzoom.min.js'></script>";
        $html.= "<script src='https://bejelentkezes.hungariamed.hu/js/jquery/jquery.js'></script>";
        $html.= "<script src='https://bejelentkezes.hungariamed.hu/admin/js/dicom.js?v=".date("mdHi")."'></script>";
        $html.= '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">';
        $html.= '<link rel="stylesheet" href="https://bejelentkezes.hungariamed.hu/css/dicom.css?v='.date("mdHi").'" />';
        $html.= "</head>";
        $html.= "<body style='margin:0px;padding:0px;background:black;font-family:arial;font-size: 18px;'>";

        $html.= "<div style='text-align: center;padding:5px;border-bottom:2px solid #888;'>";

        $html.= "<a class='dicombutton' id='invertbutton' data-status='0' onclick='toggleInvert();return false;' href='#' title='invertálás'><i class='fas fa-star-half-alt'></i></a> ";
        $html.= "<a class='dicombutton' id='normalizebutton' data-status='0' onclick='toggleNormalize();return false;' href='#' title='automata fényszint'><i class='fas fa-sun'></i></a>&nbsp;&nbsp;";

        $html.= "<a class='dicombutton' onclick='panzoom.zoomIn();return false;' href='#' title='közelítés'><i class='fas fa-search-plus'></i></a> ";
        $html.= "<a class='dicombutton' onclick='panzoom.reset();return false;' href='#' title='alapértelmezett nagyítás'><i class='far fa-window-close'></i></a> ";
        $html.= "<a class='dicombutton' onclick='panzoom.zoomOut();return false;' href='#' title='távolítás'><i class='fas fa-search-minus'></i></a>";

        $html.= "</div>";

        $html.= "<div style='display:table;width:100%;color:#ccc'>";
        $html.= "<div style='display:table-cell;width:300px;vertical-align: top;padding:20px;'>";
        $html.= "<div style='font-size:32px;text-transform: uppercase;font-weight: bold;'>{$dicomData["patientName"]}</div>";
        if (!empty($dicomData["patientBirthDate"])) {
            $html .= "<div style=''>{$dicomData["patientBirthDate"]}</div>";
        }
        if (!empty($dicomData["patientOtherIDs"])) {
            $html .= "<div style=''>TAJ: {$dicomData["patientOtherIDs"]}</div>";
        }
        if (!empty($dicomData["contentDate"])) {
            $html .= "<div style='margin-top:15px;'>Készítés időpontja:<br/>{$dicomData["contentDate"]}</div>";
        }
        if (!empty($dicomData["institutionName"])) {
            $html .= "<div style='margin-top:15px;'>Intézet:<br/>{$dicomData["institutionName"]}</div>";
        }
        if (!empty($dicomData["manufacturer"])) {
            $html .= "<div style='margin-top:15px;'>Gép:<br/>{$dicomData["manufacturer"]} {$dicomData["manufacturerModelName"]}</div>";
        }
        if (!empty($dicomData["studyDescription"])) {
            $html .= "<div style='margin-top:15px;'>{$dicomData["studyDescription"]}</div>";
        }

        $html.= "</div>";

        $html.= "<div id='imagecell' style='display:table-cell;vertical-align: top;border-left:2px solid #999;'>";

        $imageURL = "https://{$_SERVER['HTTP_HOST']}/admin/index.php?page=dicom&getimage={$id}";

        $html.= "<div id='panzoom' width='100%;' style='text-align: center;'><img id='dicomimage' style='' src='' data-rooturl='{$imageURL}' /></div>";
        $html.= "</div>";
        $html.= "</div>";

        $html.="<div id='dicomloading'>Kép betöltése...</div>";

        $html.= "</body>";
        //$html.= "</html>";

        return $html;
    }


    public function showImageList($patientId):string {
        $html = "";

        $images = $this->dicomService->getImages($patientId);

        foreach ($images as $row) {
            $html.= "<div style='display:inline-block;margin:0px 10px 10px 0px;'>";
            $html.= "<a title='kép megtekintése' style='' target='_blank' href='{$_SERVER["PHP_SELF"]}?page=dicom&displayimage={$row["uid"]}'><img src='https://{$_SERVER['HTTP_HOST']}/admin/index.php?page=dicom&getimage={$row["uid"]}&thumb' style='width:100px;height:100px;object-fit: cover;' alt='' /></a>";
            $html.= "<div style='text-align: center;padding-top: 5px;'>".date("Y-m-d H:i", strtotime($row["contentDate"]))."</div>";
            $html.= "<div style='text-align: center;padding-top: 5px;font-size: 16px;'>";
            //$html.= "<a title='kép megtekintése' style='' target='_blank' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&displayimage={$row["uid"]}'><i class='fas fa-eye'></i></a>&nbsp;";
            $html.= "<a title='DICOM file letöltése' style='' target='_blank' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&downloaddicomfile={$row["uid"]}'><i class='fas fa-cloud-download-alt'></i></a>";
            $html.= "</div>";
            $html.= "</div>";
        }

        return $html;
    }


    private function cegFilter() {
        $html = "";
        $html.="<select class='companyselector' name='dcegfilter' onchange=\"window.location.href='index.php?page={$_GET["page"]}&dcegfilter='+this.value;\">";
        $html.="<option value=''>Szűrés klinikára</option>";

        $companies = $this->dicomService->getCompanies();

        foreach ($companies as $company) {
            if (empty($company["institutionName"])) {
                continue;
            }
            $html.="<option value='{$company["institutionName"]}'".($this->dicomService->getSelectedCompany()==$company["institutionName"]?" selected":"").">{$company["institutionName"]}</option>";
        }

        $html.="</select>";
        return $html;
    }

}