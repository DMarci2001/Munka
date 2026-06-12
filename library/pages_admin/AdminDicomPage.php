<?php

class AdminDicomPage extends AdminCorePage
{

    private DicomService $dicomService;

    public function __construct()
    {
        parent::__construct();

        if (DicomService::tesztMode()) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }

        $this->dicomService = new DicomService();

        if (isset($_GET["dicomteszt"])) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);

            //sötét kép teszt";
            header("Content-Type: image/png");
            //imagePNG(imagecreatefromstring(`dcmj2pnm --write-png --histogram-window 8 /var/rtg/2024_12_17_100017_PA_CHEST.dcm`));
            imagePNG(imagecreatefromstring(`dcmj2pnm --write-png --roi-min-max-window 700 700 1000 3000 --inverse-shape /var/rtg/2024_12_17_100017_PA_CHEST.dcm`));
            die;
        }


        if (!isset($_SESSION["lastdicomcompany"])) {
            $_SESSION["lastdicomcompany"] = 0;
        }

        if (isset($_REQUEST["generalsearch"])) {
            echo $this->listDicomEntries();
            die;
        }

        if (isset($_GET["dcegfilter"])) {
            $this->dicomService->setSelectedCompany($_GET["dcegfilter"]);
            header("location:index.php?page={$_GET["page"]}");
            die;
        }

        if (isset($_GET["deszkozfilter"])) {
            $this->dicomService->setSelectedModel($_GET["deszkozfilter"]);
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
            $patients = $this->dicomService->getPatients(["byid" => $_POST["showimagelist"]]);
            echo $this->showImageList($patients[0]["patientID"]);
            die;
        }

        if (isset($_GET["deletedicomfile"])) {
            if (!in_array($this->adminUser->user["username"], ["jns", "Marci94"])) {
                die("nincs jogosultságod!");
            }

            $dicomData = $this->dicomService->getDicomEntry($_GET["deletedicomfile"]);

            if (empty($dicomData["id"])) {
                die("hiba 4419");
            }

            $fileName = $dicomData["fileName"];

            if (!empty($fileName) && !is_file($fileName)) {
                die("hiba 4420");
            }

            if (is_dir($fileName)) {
                die("hiba 4421");
            }

            //file törlése
            unlink($fileName);

            //ellenőrzés, hogy tényleg letörlődött a file mielőtt az adatbázisból is törlésre kerül
            if (is_file($fileName)) {
                die("hiba 4422");
            }

            $this->dicomService->deleteDicomEntry($dicomData["id"]);

            $_SESSION["dicommessage"] = "{$dicomData["patientName"]} ({$fileName}) törölve!";

            header("location:index.php?page={$_GET["page"]}");
            die;
        }

        if(isset($_GET["forwardtopartner"])){
            $dicomData = $this->dicomService->getDicomEntry($_GET["forwardtopartner"]);
            $dicomFile = $dicomData["fileName"];

            $storescu = 'storescu';   // DCMTK storescu útvonala
            $dcmodify = '/usr/bin/dcmodify'; // Modalitás útvonala
            $host = '81.0.104.18';
            $port = 104;
            $callingAet = 'ICWS';
            $calledAet  = 'PBQUANTUM';

            if (!is_file($dicomFile)) {
                throw new RuntimeException('A DICOM fájl nem található.');
            }
           
            if($dicomData["manufacturerModelName"]=="Essenta DR"){
                /**
                 * Modality átállítása DX-re
                 */
                $modifyCmd = sprintf(
                    '%s ' .
                    '-i %s ' .
                    '-i %s ' .
                    '-i %s ' .
                    '%s 2>&1',
                    escapeshellcmd($dcmodify),
                    escapeshellarg('(0008,0060)=DX'),
                    escapeshellarg('(0008,0016)=1.2.840.10008.5.1.4.1.1.1.1'),
                    escapeshellarg('(0008,0068)=FOR PRESENTATION'),
                    escapeshellarg($dicomFile)
                );

                exec($modifyCmd, $modifyOutput, $modifyExit);

                if ($modifyExit !== 0) {
                    throw new RuntimeException(
                        "Modality módosítás sikertelen:\n" .
                        implode("\n", $modifyOutput)
                    );
                }
            }

            if($dicomData["manufacturer"]=="FUJIFILM Corporation"){
                /**
                 * Modality átállítása MG-re
                 */
                $modifyCmd = sprintf(
                    '%s ' .
                    '-i %s ' .
                    '-i %s ' .
                    '-i %s ' .
                    '%s 2>&1',
                    escapeshellcmd($dcmodify),
                    escapeshellarg('(0008,0060)=MG'),
                    escapeshellarg('(0008,0016)=1.2.840.10008.5.1.4.1.1.1.2'),
                    escapeshellarg('(0008,0068)=FOR PRESENTATION'),
                    escapeshellarg($dicomFile)
                );

                exec($modifyCmd, $modifyOutput, $modifyExit);

                if ($modifyExit !== 0) {
                    throw new RuntimeException(
                        "Modality módosítás sikertelen:\n" .
                        implode("\n", $modifyOutput)
                    );
                }
            }

            /**
             * DICOM küldés
             */

            $storeCmd = sprintf(
                '%s -d -xs -aec %s -aet %s %s %d %s 2>&1',
                escapeshellcmd($storescu),
                escapeshellarg($calledAet),
                escapeshellarg($callingAet),
                escapeshellarg($host),
                $port,
                escapeshellarg($dicomFile)
            );

            exec($storeCmd, $output, $exitCode);
            
            /*Debug*/
            //echo "Exit code: $exitCode\n";
            //echo implode("\n", $output);

            if ($exitCode !== 0) {
                throw new RuntimeException("A küldés sikertelen.");
            }
            sql_query("UPDATE dicom SET senttopartner=? WHERE uid=?",[date("Y-m-d H:i:s"),$_GET["forwardtopartner"]]);

            //header("location:index.php?page={$_GET["page"]}");
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

        if (isset($_POST["setleletstatus"])) {
            $this->dicomService->setLeletStatus($_POST["id"], $_POST["num"], $this->adminUser->user["nev"]);

            $patients = $this->dicomService->getPatients(["byuid" => $_POST["id"]]);
            Utils::jsonOut(["imagerow" => $this->showImageList($patients[0]["patientID"]), "leletstatus" => $this->showDicomStatus($patients[0]["patientID"], $patients[0]["datum"])]);
        }

        if (isset($_POST["toggleLeletKiallitva"])) {
            $this->dicomService->toggleLeletKiallitva($_POST["id"], $this->adminUser->user["nev"]);
            Utils::jsonOut(["leletstatus" => $this->showDicomStatus($_POST["pid"], $_POST["date"])]);
        }

        if (isset($_POST["showcompanyselect"])) {
            if (!$this->adminUser->allCegJog()) {
                die;
            }
            $lastCompanyId = $_SESSION["lastdicomcompany"] ?? 0;
            $lastCompanies = sql_query_common("select id, trim(megnev) as megnev from cegek where id=?", [$lastCompanyId])->fetchAll(PDO::FETCH_ASSOC);
            $companies = sql_query_common("select id, trim(megnev) as megnev from cegek where trim(megnev)<>'' order by trim(megnev)")->fetchAll(PDO::FETCH_ASSOC);

            echo "<select onchange='saveDicomCompany(\"{$_POST["showcompanyselect"]}\", this.value)' style='width:120px;font-size:12px;padding:1px 4px;'>";
            echo "<option value='0'>Válassz céget!</option>";
            foreach ($lastCompanies as $company) {
                $selected = $company["id"] == $_POST["cegid"] ? "selected":"";
                echo "<option value='{$company["id"]}' {$selected}>{$company["megnev"]}</option>";
            }
            foreach ($companies as $company) {
                $selected = $company["id"] == $_POST["cegid"] ? "selected":"";
                echo "<option value='{$company["id"]}' {$selected}>{$company["megnev"]}</option>";
            }
            echo "</select>";

            die;
        }

        if (isset($_POST["setcegid"])) {
            if (!$this->adminUser->allCegJog()) {
                die;
            }
            $this->dicomService->setCompanyId($_POST["id"], $_POST["cegid"]);
            $_SESSION["lastdicomcompany"] = $_POST["cegid"];

            echo $this->showCompanySelector($_POST["id"], ["cegid" => $_POST["cegid"]]);
            die;
        }

        if (isset($_REQUEST["addfiles"])) {
            $return = ["error" => "", "ok" => ""];

            foreach ($_FILES as $file) {
                $result = $this->dicomService->addFile($file);
                if (!empty($result)) {
                    $return["error"] = $result;
                    break;
                } else {
                    $return["ok"] = "<strong>A feltöltés sikerült!</strong><br/>A képnek feldolgozás után, 1 percen belül meg kell jelennie a listában.";
                }
            }

            $this->utils->jsonOut($return);
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
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo $this->cegFilter()."&nbsp;&nbsp;";
        echo $this->eszkozFilter();
        echo "&nbsp;&nbsp;<input data-page='dicom' data-resultdiv='dicomlist' type='text' id='generalsearch' value='' placeholder='Keresés...'/>&nbsp;&nbsp;&nbsp;&nbsp;";
        echo "</div>";

        echo "<div style='display:table-cell;vertical-align: middle;border-left:1px solid #ccc;padding-left:10px;'>";
        echo "<div id='uploadarea'>";
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo "<div class='upload-btn-wrapper'><a href='#' onclick='return false;' class='dicomuploadbutton'>Kép feltöltése</a><input type='file' id='dicomfile' class='dicomfilebutton' name='dicomfile[]' /></div>";
        echo "</div>";
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo "<div><img id='uploadloader' style='display:none;opacity:.5;height:25px;margin-left:10px;' src='/images/loading_transparent.svg' /></div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";

        echo "</div>";

        if (isset($_SESSION["dicommessage"])) {
            echo "<div style='padding:10px;background:forestgreen;color:white;margin-bottom:20px;'>{$_SESSION["dicommessage"]}</div>";
            unset($_SESSION["dicommessage"]);
        }

        echo "<div id='dicomlist'>";
        echo $this->listDicomEntries();
        echo "</div>";
    }


    private function listDicomEntries():string {
        if (isset($_REQUEST["generalsearch"]) && isset($_REQUEST["term"])) {
            $images = $this->dicomService->getPatients(["search" => $_REQUEST["term"]]);
        }

        if (!isset($images)) {
            $images = $this->dicomService->getPatients();
        }

        $html = "";

        $html.= "<table cellpadding='0' cellspacing='0' border='0' width='100%;'>";
        $html.= "<tr style='background:#eee;'>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Időpont</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Klinika</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Gép</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:10px;white-space: nowrap;'>Cég</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:10px;'></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:240px;'>Paciens neve</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:100px;'>Szül. dátum</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:100px;'>TAJ szám</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Megjegyzés</td>";
        $html.= "</tr>";

        foreach ($images as $row) {
            $patientData = $this->patinentService->getPatinentByTaj($row["patientOtherIDs"]);
            $machineName = "{$row["manufacturer"]} {$row["manufacturerModelName"]}";

            $studyDescription = $row["studyDescription"];
            if (!empty($row["seriesDescription"])) {
                if (!empty($studyDescription)) {
                    $studyDescription.= " &gt; ";
                }
                $studyDescription.= "{$row["seriesDescription"]}";
            }

            $tc = "tcella";
            if (!isset($first)) {
                $html.= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (empty(trim($row["patientName"]))) {
                $row["patientName"] = "nincs neve";
            }
            $html.= "<tr>";

            $html.= "<td nowrap><div class='{$tc}'>";
            $html.= "<a style='' onclick='toggleDicomImageRow(\"{$row["patientID"]}\");return false;' href='#'>{$row["imageNum"]} kép</a> ";
            $html.= "&nbsp;<span style='font-size: 14px;' id='lstatus{$row["patientID"]}'>".$this->showDicomStatus($row["patientID"], $row["datum"])."</span>";
            //$html.= "[<a style='color:#00f;' target='_blank' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&displayimage={$row["uid"]}'>kép megtekintése</a>] ";
            //$html.= "[<a style='color:#00f;' target='_blank' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&downloaddicomfile={$row["uid"]}'>DICOM file letöltése</a>]";
            $html.= "</td>";

            $html.= "<td nowrap><div class='{$tc}'>".date("Y-m-d H:i", strtotime($row["datum"]))."</div></td>";
            $html.= "<td nowrap><div class='{$tc}'>{$row["institutionName"]}</div></td>";
            $html.= "<td nowrap><div class='{$tc}'>{$machineName}</div></td>";
            $html.= "<td nowrap><div class='{$tc}'>";

            $html.= "<span style='' id='cegid{$row["patientID"]}'>".$this->showCompanySelector($row["patientID"], $row)."</span>";

            $html.= "</div></td>";
            if (!empty($patientData)) {
                $html .= "<td nowrap><div class='{$tc}'><i title='pacienssel összekapcsolva' class='fas fa-link'></i></div></td>";
                $html .= "<td nowrap><div class='{$tc}'><a target='_blank' href='index.php?page=patients&szerk={$patientData["id"]}'>{$row["patientName"]}</a></div></td>";
            } else {
                $html .= "<td nowrap></td>";
                $html .= "<td nowrap><div class='{$tc}'>{$row["patientName"]}</div></td>";
            }

            $html.= "<td nowrap><div class='{$tc}'>{$row["patientBirthDate"]}</div></td>";
            $html.= "<td nowrap><div class='{$tc}'>{$row["patientOtherIDs"]}</div></td>";
            $html.= "<td nowrap><div class='{$tc}'>{$studyDescription}</div></td>";

            $html.= "</tr>";
            $html.= "<tr><td colspan='10' ><div id='imagerow{$row["patientID"]}' style='padding:10px 0px 10px 0px;display:none;'></div></td></tr>";
            $html.= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        $html.= "</table>";

        return $html;
    }

    private function showCompanySelector($id, $dicomData):string {
        if (!$companyData = sql_query_common("select megnev from cegek where id=?", [$dicomData["cegid"]])->fetch(PDO::FETCH_ASSOC)) {
            $companyData["megnev"] = "cég?";
        }

        if (!$this->adminUser->allCegJog()) {
            $id = 0;
        }

        return "<span onclick='showCompanySelect(\"{$id}\", \"{$dicomData["cegid"]}\");' style='border:1px solid #ccc;padding:2px 4px;cursor:pointer;'>{$companyData["megnev"]}</span>";
    }

    private function showDicomStatus($id, $date):string {
        $leletStatus = $this->dicomService->getLeletStatus($id, $date);

        $status = "<i class='fa-solid fa-circle-question' title='nincs leletezve'></i>";
        if ($leletStatus["leletstatus"] == 2) {
            $status = "<i class='fa-solid fa-square-plus' style='color:red;' title='pozitív lelet - {$leletStatus["leletcreatedby"]}'></i>";
        }
        if ($leletStatus["leletstatus"] == 1) {
            $status = "<i class='fa-solid fa-square-minus' style='color:green;' title='negatív lelet - {$leletStatus["leletcreatedby"]}'></i>";
        }

        $status .= "&nbsp;";
        if ($leletStatus["leletkiallitva"] == 1) {
            $status .= "<i onclick='toggleLeletKiallitva(\"{$leletStatus["id"]}\", \"{$id}\", \"{$date}\");' class='fa-solid fa-square-check' style='cursor:pointer;' title='lelet kiállítva - {$leletStatus["leletkiallitvaby"]}'></i>";
        } else {
            $status .= "<i onclick='toggleLeletKiallitva(\"{$leletStatus["id"]}\", \"{$id}\", \"{$date}\");' class='fa-regular fa-square' style='cursor:pointer;' title='lelet nincs kiállítva'></i>";
        }

        return $status;
    }

    private function displayImageEditor($id):string {
        $dicomData = $this->dicomService->getDicomEntry($id);

        $studyDescription = $dicomData["studyDescription"];
        if (!empty($dicomData["seriesDescription"])) {
            if (!empty($studyDescription)) {
                $studyDescription.= " &gt; ";
            }
            $studyDescription.= "{$dicomData["seriesDescription"]}";
        }

        $html = "<!DOCTYPE html>";
        $html.= "<head>";
        $html.= "<title>{$dicomData["patientName"]} DICOM image</title>";
        $html.= "<script src='https://bejelentkezes.hungariamed.hu/js/panzoom.min.js'></script>";
        $html.= "<script src='https://bejelentkezes.hungariamed.hu/js/jquery/jquery-3.7.1.min.js?v=".date("mdHi")."'></script>";
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
        if (!empty($studyDescription)) {
            $html .= "<div style='margin-top:15px;'>{$studyDescription}</div>";
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
        $html = $sendButton = "";

        $images = $this->dicomService->getImages($patientId);

        foreach ($images as $row) {
            if (strtotime($row["contentDate"]) < strtotime("now - 1 week") && !isset($oldSign)) {
                $html.= "<br clear='all'>";
                $html.= "<div style='font-weight: bold;font-size: 16px;margin-bottom: 10px;'>Régebbi képek</div>";
                $oldSign = true;
            }

            if(!empty($row["senttopartner"])){
                $sendButton = "<a title='Továbbítva: {$row["senttopartner"]}' style='color:green' onclick='return confirm(\"Biztos továbbítod a Quantumdoktor felé?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&forwardtopartner={$row["uid"]}' ><i class='fa-solid fa-share-from-square'></i></a>";
            }else{
                $sendButton = "<a title='DICOM file továbbítása partner felé' style='' onclick='return confirm(\"Biztos továbbítod a Quantumdoktor felé?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&forwardtopartner={$row["uid"]}'><i class='fa-solid fa-share-from-square'></i></a>";
            }

            $sendButton = "<a title='DICOM file továbbítása partner felé' style='' onclick='return confirm(\"Biztos továbbítod a Quantumdoktor felé?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&forwardtopartner={$row["uid"]}'><i class='fa-solid fa-share-from-square'></i></a>";

            $html.= "<div style='display:inline-block;margin:0px 10px 10px 0px;'>";
            $html.= "<a title='kép megtekintése' style='' target='_blank' href='{$_SERVER["PHP_SELF"]}?page=dicom&displayimage={$row["uid"]}'><img src='https://{$_SERVER['HTTP_HOST']}/admin/index.php?page=dicom&getimage={$row["uid"]}&thumb' style='width:175px;height:175px;object-fit: cover;' alt='' /></a>";

            $html.= "<div style='margin:5px 0px 0px 0px;text-align: center'>";
            $html.= "<div style='display:table-cell;'><a title='Lelet pozitív' onclick='setLeletStatus(\"{$row["patientID"]}\", \"{$row["uid"]}\", 2);return false;' href='#' class='dicompozitivbutton".($row["leletstatus"] == 2 ?"_aktiv":"")."'>Pozítív</a>&nbsp;</div>";
            $html.= "<div style='display:table-cell;'><a title='Lelet negatív' onclick='setLeletStatus(\"{$row["patientID"]}\", \"{$row["uid"]}\", 1);return false;' href='#' class='dicomnegativbutton".($row["leletstatus"] == 1 ?"_aktiv":"")."'>Negatív</a>&nbsp;</div>";
            $html.= "<div style='display:table-cell;'><a title='Nincs lelet' onclick='setLeletStatus(\"{$row["patientID"]}\", \"{$row["uid"]}\", 0);return false;' href='#' class='dicomsemlegesbutton'>Egyik sem</a></div>";
            $html.= "</div>";

            $html.= "<div style='text-align: center;padding-top: 5px;'>".date("Y-m-d H:i", strtotime($row["contentDate"]))."</div>";
            $html.= "<div style='text-align: center;padding-top: 5px;font-size: 16px;'>";
            //$html.= "<a title='kép megtekintése' style='' target='_blank' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&displayimage={$row["uid"]}'><i class='fas fa-eye'></i></a>&nbsp;";
            $html.= "<a title='DICOM file letöltése' style='' target='_blank' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&downloaddicomfile={$row["uid"]}'><i class='fas fa-cloud-download-alt'></i></a>&nbsp;";
            $html.= "<a title='DICOM file törlése' style='' target='_blank' onclick='return confirm(\"Biztos törlöd ezt a képet?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&deletedicomfile={$row["uid"]}'><i class='fas fa-trash-can'></i></a>&nbsp;";
            $html.= $sendButton;
            
            $html.= "</div>";
            $html.= "</div>";
        }

        return $html;
    }


    private function cegFilter():string {
        $html = "";
        $html.="<select class='companyselector' name='dcegfilter' onchange=\"window.location.href='index.php?page={$_GET["page"]}&dcegfilter='+this.value;\">";
        $html.="<option value=''>Szűrés klinikára</option>";

        $companies = $this->dicomService->getCompanies();

        if ($this->adminUser->allCegJog()) {
            foreach ($companies as $company) {
                if (empty($company["institutionName"])) {
                    continue;
                }
                $html .= "<option value='{$company["institutionName"]}'" . ($this->dicomService->getSelectedCompany() == $company["institutionName"] ? " selected" : "") . ">{$company["institutionName"]}</option>";
            }
        }

        $html.="</select>";
        return $html;
    }


    private function eszkozFilter():string {
        $html = "";
        $html.="<select class='companyselector' name='deszkozfilter' onchange=\"window.location.href='index.php?page={$_GET["page"]}&deszkozfilter='+this.value;\">";
        $html.="<option value=''>Szűrés Eszközre</option>";

        $models = $this->dicomService->getModels();

        foreach ($models as $model) {
            if (empty($model["manufacturer"])) {
                continue;
            }
            $html.="<option value='{$model["manufacturer"]}'".($this->dicomService->getSelectedModel()==$model["manufacturer"]?" selected":"").">{$model["name"]}</option>";
        }

        $html.="</select>";
        return $html;
    }

}