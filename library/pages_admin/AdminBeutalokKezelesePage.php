<?php

use Mpdf\Tag\B;
use mikehaertl\pdftk\Pdf;

class AdminBeutalokKezelesePage extends AdminCorePage
{

    const companyId = 220;

    public function __construct()
    {
        parent::__construct();

        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        if (!$this->adminUser->beutalomenupontAccess()) header("Location:index.php");

        if (isset($_GET["download-excel"])) {
            /*
            Egy névlistát kell készítsek a felettes számára.
            Tartalmazza a dolgozó nevét, munakörét és lejáratát első körben.
            */
            $data = sql_query("SELECT nev as 'Teljesnév',munkakor as 'Munkakör',visszarendeles as 'Visszarendelés' FROM beutalo_kezeles_data WHERE bmid=? AND sessionid=? AND felettesemail=?",[$_GET["szerk"],$_GET["sessionid"],"atikovacs@fgsz.hu"])->fetchAll(PDO::FETCH_ASSOC);
            $path = __DIR__."/other/tmp/";
            $filename = "fgsz_teszt_lista.xlsx";
            $excelService = new ExcelService();
            $excelService->generateXlsxFromArray($data, "A", "D");
            $excelService->setFileName("fgsz_teszt_lista". ".xlsx");
            $excelService->outputSpreadSheetFile($path.$filename);
            //$excelService->outputSpreadSheet();
        }

        if (isset($_POST["createBeutaloKezeles"])) {
            $q = sql_query("SELECT * FROM beutalo_kezeles WHERE title='{$_POST["record"]}'")->fetch(PDO::FETCH_ASSOC);
            if ($q) {
                die(json_encode(array("error" => "megadott megnevezés már létezik!")));
            } else {
                sql_query(
                    "INSERT INTO beutalo_kezeles SET title=?,created=?,created_by=?",
                    [$_POST["record"], date("Y-m-d H:i:s"), $_SESSION["adminuser"]["id"]]
                );
                die(json_encode(array("success" => true, "html" => $this->initializeBeutaloKezelesList())));
            }
            die();
        }

        if (isset($_POST["setCompanyId"])) {
            sql_query("UPDATE beutalo_kezeles SET cegid=? WHERE id=?", [$_POST["id"], $_POST["bmid"]]);
            die();
        }

        if (isset($_POST["uploadReferalFile"])) {

            $array = [
                "datatype" => "referal-sample-file",
                "dataid"   => intval($_POST["uploadReferalFile"]),
                "megnev"   => $_FILES[0]["name"],
            ];

            $docAgent = new DocAgent();
            $docAgent->saveDoc($_FILES[0], $array);
            die($docAgent->lastSavedId);
        }

        if (isset($_POST["create-referal-files"])) {
            $data = sql_query("SELECT * FROM beutalo_kezeles_data WHERE bmid=? AND sessionid=?", [$_GET["szerk"], $_GET["sessionid"]])->fetchAll(PDO::FETCH_ASSOC);
            $beutaloManagement = sql_query("SELECT * FROM beutalo_kezeles WHERE id=?", [$_GET["szerk"]])->fetch(PDO::FETCH_ASSOC);
            foreach ($data as $key => $each) {
                $referalParameters = sql_query(
                    "SELECT * FROM kockazati_tenyezok WHERE cegid=? AND munkakor=?",
                    [$beutaloManagement["cegid"], $each["munkakor"]]
                )->fetch(PDO::FETCH_ASSOC);

                if ($referalParameters) {
                    $id = $this->createReferalFile($referalParameters, $each);
                    $data[$key]["file"] = $id;
                } else {
                    echo "<span style='color:red'>Nincs találat!({$each["munkakor"]})</span><br>";
                }
            }
            $this->createSuperiorFilePacks($data);
            $this->createSupplierFilePacks($data, $_GET["szerk"]);
        }

        if (isset($_POST["save_superior_email_settings"])) {
            $bmid = intval($_POST["save_superior_email_settings"]);
            $cols = "";
            $values = [];
            foreach ($_POST as $col => $value) {
                if ($col == "save_superior_email_settings") continue;
                $cols .= $col . "=?,";
                $values[] = $value;
            }
            $values[] = $bmid;
            $cols = substr_replace($cols, '', -1);
            sql_query("UPDATE beutalo_kezeles SET {$cols} WHERE id=?", $values);
            die();
        }

        if (isset($_POST["save_doctor_email_settings"])) {
            $bmid = intval($_POST["save_doctor_email_settings"]);
            $cols = "";
            $values = [];
            foreach ($_POST as $col => $value) {
                if ($col == "save_doctor_email_settings") continue;
                $cols .= $col . "=?,";
                $values[] = $value;
            }
            $values[] = $bmid;
            $cols = substr_replace($cols, '', -1);
            sql_query("UPDATE beutalo_kezeles SET {$cols} WHERE id=?", $values);
            die();
        }

        if (isset($_POST["form_worker_email_settings"])) {
            $bmid = intval($_POST["form_worker_email_settings"]);
            $cols = "";
            $values = [];
            foreach ($_POST as $col => $value) {
                if ($col == "form_worker_email_settings") continue;
                $cols .= $col . "=?,";
                $values[] = $value;
            }
            $values[] = $bmid;
            $cols = substr_replace($cols, '', -1);
            sql_query("UPDATE beutalo_kezeles SET {$cols} WHERE id=?", $values);
            die();
        }

        if (isset($_POST["changeSuperiorEmailSettingsPrior"])) {
            sql_query("UPDATE beutalo_kezeles SET superior_email_send=? WHERE id=?", [$_POST["value"], $_POST["bmid"]]);
            die();
        }

        if (isset($_POST["changeDoctorEmailSettingsPrior"])) {
            sql_query("UPDATE beutalo_kezeles SET doctor_email_send=? WHERE id=?", [$_POST["value"], $_POST["bmid"]]);
            die();
        }

        if (isset($_POST["changeWorkerEmailSettingsPrior"])) {
            sql_query("UPDATE beutalo_kezeles SET worker_email_send=? WHERE id=?", [$_POST["value"], $_POST["bmid"]]);
            die();
        }

        if (isset($_POST["start-sending-sequence"])) {
            //Szükséges adatok:
            $session = $this->getSessionIdDetails($_GET["sessionid"]);
            $bm = sql_query("SELECT * FROM beutalo_kezeles WHERE id=?", [$_GET["szerk"]])->fetch(PDO::FETCH_ASSOC);
            $data = sql_query("SELECT * FROM beutalo_kezeles_data WHERE bmid=? AND sessionid=?", [$_GET["szerk"], $_GET["sessionid"]])->fetchAll(PDO::FETCH_ASSOC);
            $felettesek = $this->getSuperiorDetails($data);
            $orvosok = $this->getSupplierDetails($bm["cegid"], $data);
            $notificationService = new NotificationService();

            ini_set('memory_limit', '256M');

            //Felettesek részére
            if ($bm["superior_email_send"] == 1) {
                foreach ($felettesek as $superior) {
                    $file = [];
                    $excel = "";
                    if($bm["send_files_to_superior"]==1){
                        $file = ["id"=>$superior["file"],"filename"=>"Beutalók.zip"];
                    }

                    if($bm["send_list_to_superior"]==1){
                        $data = sql_query("SELECT nev as 'Teljesnév',munkakor as 'Munkakör',visszarendeles as 'Visszarendelés' 
                                           FROM beutalo_kezeles_data 
                                           WHERE bmid=? AND sessionid=? AND felettesemail=?",
                                           [$_GET["szerk"],$_GET["sessionid"],$superior["email"]]
                                           )->fetchAll(PDO::FETCH_ASSOC);

                        $path = __DIR__."/other/tmp/";
                        $filename = "Névlista(".trim($superior["email"]).").xlsx";
                        $excelService = new ExcelService();
                        $excelService->generateXlsxFromArray($data, "A", "D");
                        $excelService->setFileName($filename);
                        $excelService->outputSpreadSheetFile($path.$filename);
                        $excel = $path.$filename;
                    }
                    

                    $params = [
                        "objectId"    => $superior["id"],
                        "type"        => "referal-notifcation-superior",
                        "destination" => trim($superior["email"]),
                        "addressee"   => trim($superior["nev"]),
                        "file"        => $file,
                        "list"        => $excel, 
                        "sender"      => $bm["superior_email_sender_address"],
                        "copy"        => $bm["superior_email_copy_address"],
                        "subject"     => $bm["superior_email_subject"],
                        "content"     => $bm["superior_email_content"]
                    ];

                    $notificationService->sendReferalNotification($params);
                }
            }

            //Orvosok részére
            if ($bm["doctor_email_send"] == 1) {
                foreach($orvosok as $orvos){
                    $file = [];
                    if($bm["send_files_to_doctors"]==1){
                        $file = ["id"=>$orvos["file"],"filename"=>"Beutalók.zip"];
                    }

                    $params = [
                        "objectId"    => $orvos["id"],
                        "type"        => "referal-notifcation-doctor",
                        "destination" => trim($orvos["email"]),
                        "addressee"   => trim($orvos["nev"]),
                        "file"        => $file,
                        "list"        => $excel,
                        "sender"      => $bm["doctor_email_sender_address"],
                        "copy"        => $bm["doctor_email_copy_address"],
                        "subject"     => $bm["doctor_email_subject"],
                        "content"     => $bm["doctor_email_content"]
                    ];

                    $notificationService->sendReferalNotification($params);
                }
            }

            //Dolgozók részére
            if ($bm["worker_email_send"] == 1) {
                foreach($data as $worker){
                    $file = [];
                    if($bm["send_files_to_workers"]==1){
                        $file = ["id"=>$worker["file"],"filename"=>"Beutaló.pdf"];
                    }
                    $params = [
                        "objectId"    => $worker["id"],
                        "type"        => "referal-notifcation-worker",
                        "destination" => trim($worker["email"]),
                        "addressee"   => trim($worker["nev"]),
                        "file"        => $file,
                        "list"        => $excel,
                        "sender"      => $bm["worker_email_sender_address"],
                        "copy"        => $bm["worker_email_copy_address"],
                        "subject"     => $bm["worker_email_subject"],
                        "content"     => $bm["worker_email_content"]
                    ];

                    $notificationService->sendReferalNotification($params);
                }
            }

            //Session lezárása
            sql_query(
                "UPDATE  beutalo_kezeles_sessions 
                       SET superior_email_send=?, doctor_email_send=?, worker_email_send=?,
                           superior_email_sender_address=?, superior_email_copy_address=?, superior_email_subject=?, 
                           superior_email_content=?, doctor_email_sender_address=?, doctor_email_copy_address=?, 
                           doctor_email_subject=?, doctor_email_content=?, worker_email_sender_address=?, 
                           worker_email_copy_address=?, worker_email_subject=?, worker_email_content=?, 
                           status='finished'
                WHERE id=?;",
                [
                    $bm["superior_email_send"],$bm["doctor_email_send"],$bm["worker_email_send"],
                    $bm["superior_email_sender_address"],$bm["superior_email_copy_address"],$bm["superior_email_subject"],$bm["superior_email_content"],
                    $bm["doctor_email_sender_address"],$bm["doctor_email_copy_address"],$bm["doctor_email_subject"],$bm["doctor_email_content"],
                    $bm["worker_email_sender_address"],$bm["worker_email_copy_address"],$bm["worker_email_subject"],$bm["worker_email_content"],
                    $session["id"]
                ]
            );

            header("Location:index.php?page=beutalokkezelese&szerk={$_GET["szerk"]}&sessionid=");
        }

        if (isset($_POST["setFileSendingMethod"])) {
            sql_query("UPDATE beutalo_kezeles SET send_files_to_{$_POST["type"]}=? WHERE id=?", [(int)$_POST["value"], $_POST["bmid"]]);
            die();
        }

        if (isset($_POST["setListSendingMethod"])) {
            sql_query("UPDATE beutalo_kezeles SET send_list_to_{$_POST["type"]}=? WHERE id=?", [(int)$_POST["value"], $_POST["bmid"]]);
            die();
        }
    }

    public function showPage()
    {
        $html = "";

        if (!isset($_GET["szerk"])) {
            $html .= $this->initializeBeutaloKezelesList();
        } else {
            $data = sql_query("SELECT * FROM beutalo_kezeles_data WHERE bmid=? AND sessionid=?", [$_GET["szerk"], $_GET["sessionid"]])->fetchAll(PDO::FETCH_ASSOC);

            $html .= "<div class='container-xxl mx-3'>";
            $html .= $this->tabMenuUI($data);
            $html .= "</div>";
        }

        /*if ($data) {
            $html .= "<div class='container-xxl mx-3'>";
            $html .= $this->tabMenuUI($data);
            $html .= "</div>";
        } else {
            $html .= "<a href='?page=patientdata&action=create-mass-ohr&cid=" . self::companyId . "'>";
            $html .= "  <h5>Lista feltöltéséhez kattints ide <i class='fa-solid fa-arrow-up-right-from-square'></i></h5>";
            $html .= "</a>";
        }*/
        $html .= "<script type='text/javascript' src='js/beutalokezeles.js'></script>";

        echo $html;
    }

    private function initializeBeutaloKezelesList(): string
    {
        $html = "";

        $html .= "<div id='beutalokezeles-container'>";
        $html .= "  <div class='container-xxl mb-3'>";
        $html .= "      <button type='button' class='btn btn-secondary btn-sm' title='Új beutaló kezelés készítése' onClick='createBeutaloKezeles()'><i class='fa-solid fa-table-list'>&nbsp;</i><i class='fa-solid fa-plus'></i></button>";
        //$html .= "      <a role='button' href='?page=direktmarketing&szerk=cimzett_lista' class='btn btn-success btn-sm' title='Teljes címzett lista' onClick=''><i class='fa-solid fa-address-book'></i></a>";
        $html .= "  </div>";

        $data = sql_query("SELECT bk.*,(SELECT id FROM beutalo_kezeles_sessions WHERE bmid=bk.id AND status='inprogress' ORDER BY id desc LIMIT 1) as sessionid FROM beutalo_kezeles bk ORDER BY bk.title ASC")->fetchAll(PDO::FETCH_ASSOC);

        $html .= "   <table id='beutalo-kezeles-list' class='table table-hover'>";
        $html .= "       <thead>";
        $html .= "           <tr class='text-center'>";
        $html .= "           <th scope='col'>#</th>";
        $html .= "           <th scope='col'>Lista név</th>";
        $html .= "           <th scope='col'>Cégnév</th>";
        $html .= "           <th scope='col'>Létrehozva</th>";
        $html .= "           <th scope='col'>Létrehozta</th>";
        $html .= "           </tr>";
        $html .= "       </thead>";
        $html .= "       <tbody>";
        for ($i = 0; $i < count($data); $i++) {
            $html .= "      <tr role='button' class='text-center' data-beutalo-kezeles-id='{$data[$i]["id"]}' data-session-id='{$data[$i]["sessionid"]}'>";
            $html .= "          <th scope='row'>{$i}.</th>";
            $html .= "          <td>" . $this->replaceOnNull($data[$i]["title"]) . "</td>";
            $html .= "          <td>" . $this->replaceOnNull($data[$i]["cegid"]) . "</td>";
            $html .= "          <td>" . $this->replaceOnNull($data[$i]["created"]) . "</td>";
            //$html .= "          <td>" . $this->replaceOnNull($data[$i]["recipient_list_size"]) . "</td>";
            $html .= "          <td>" . $this->replaceOnNull($this->getUserName($data[$i]["created_by"])) . "</td>";
            $html .= "      </tr>";
        }

        $html .= "       </tbody>";
        $html .= "   </table>";
        $html .= "   <script type='text/javascript' src='js/dm_ui.js'></script>";
        $html .= "</div>";

        return $html;
    }

    private function tabMenuUI($data)
    {

        $beutaloManagement = sql_query("SELECT * FROM beutalo_kezeles WHERE id=?", [$_GET["szerk"]])->fetch(PDO::FETCH_ASSOC);
        $cegid = $beutaloManagement["cegid"];
        if (!empty($data)) {
            $orvosok = $this->getSupplierDetails($cegid, $data);
            $felettesek = $this->getSuperiorDetails($data);
        }


        $html = "";
        $html .= "<input type='hidden' id='bmid' value='{$beutaloManagement["id"]}'>";
        $html .= "<ul class='nav nav-tabs' id='myTab' role='tablist'>";
        $html .= "    <li class='nav-item' role='presentation'>";
        $html .= "        <button class='nav-link active' id='settings-tab' data-bs-toggle='tab' data-bs-target='#settings-tab-pane' type='button' role='tab' aria-controls='settings-tab-pane' aria-selected='true'>Tartalom beállítások</button>";
        $html .= "    </li>";
        $html .= "    <li class='nav-item' role='presentation'>";
            $html .= "        <button class='nav-link' id='log-tab' data-bs-toggle='tab' data-bs-target='#log-tab-pane' type='button' role='tab' aria-controls='log-tab-pane' aria-selected='false'>LOG</button>";
            $html .= "    </li>";
        if (!empty($data)) {
            $html .= "    <li class='nav-item' role='presentation'>";
            $html .= "        <button class='nav-link' id='supplier-tab' data-bs-toggle='tab' data-bs-target='#supplier-tab-pane' type='button' role='tab' aria-controls='supplier-tab-pane' aria-selected='true'>Ellátó orvosok</button>";
            $html .= "    </li>";
            $html .= "    <li class='nav-item' role='presentation'>";
            $html .= "        <button class='nav-link' id='supperiors-tab' data-bs-toggle='tab' data-bs-target='#supperiors-tab-pane' type='button' role='tab' aria-controls='supperiors-tab-pane' aria-selected='false'>Felettesek</button>";
            $html .= "    </li>";
            $html .= "    <li class='nav-item' role='presentation'>";
            $html .= "        <button class='nav-link' id='workers-tab' data-bs-toggle='tab' data-bs-target='#workers-tab-pane' type='button' role='tab' aria-controls='workers-tab-pane' aria-selected='false'>Munkavállalók</button>";
            $html .= "    </li>";
            $html .= "    <li class='nav-item' role='presentation'>";
            $html .= "        <button class='nav-link' id='referal-files-tab' data-bs-toggle='tab' data-bs-target='#referal-files-tab-pane' type='button' role='tab' aria-controls='referal-files-tab-pane' aria-selected='false'>Beutaló fájlok</button>";
            $html .= "    </li>";
        }
        $html .= "</ul>";
        $html .= "<div class='tab-content' id='myTabContent'>";
        $html .= "    <div class='tab-pane pt-3 ps-3 fade show active' id='settings-tab-pane' role='tabpanel' aria-labelledby='settings-tab' tabindex='0'>" . $this->beutaloKezelesSettings($beutaloManagement) . "</div>";
        $html .= "    <div class='tab-pane pt-3 ps-3 fade' id='log-tab-pane' role='tabpanel' aria-labelledby='log-tab' tabindex='0'>" . $this->showLogTable() . "</div>";
        if (!empty($data)) {
            $html .= "    <div class='tab-pane pt-3 ps-3 fade' id='supplier-tab-pane' role='tabpanel' aria-labelledby='supplier-tab' tabindex='0'>" . $this->showSupplierTable($orvosok) . "</div>";
            $html .= "    <div class='tab-pane pt-3 ps-3 fade' id='workers-tab-pane' role='tabpanel' aria-labelledby='workers-tab' tabindex='0'>" . $this->showWorkersTable($data) . "</div>";
            $html .= "    <div class='tab-pane pt-3 ps-3 fade' id='supperiors-tab-pane' role='tabpanel' aria-labelledby='supperiors-tab' tabindex='0'>" . $this->showSuperiorsTable($felettesek) . "</div>";
            $html .= "    <div class='tab-pane pt-3 ps-3 fade' id='referal-files-tab-pane' role='tabpanel' aria-labelledby='referal-files-tab' tabindex='0'>" . $this->showReferalFilesTable($data, $felettesek, $orvosok) . "</div>";
        }

        $html .= "</div>";

        return $html;
    }

    private function beutaloKezelesSettings($data): string
    {
        $html = "";
        $session = $this->getSessionIdDetails($_GET["sessionid"]);

        $html .= "<h5><i class='fa-solid fa-gear'></i>&nbsp;Alap beállítások</h5>";
        if (!empty($data["cegid"])) {
            $html .= "<a href='?page=patientdata&action=create-mass-ohr&bmid={$data["id"]}'>";
            $html .= "  <p>Lista feltöltéséhez kattints ide <i class='fa-solid fa-arrow-up-right-from-square'></i></p>";
            $html .= "</a>";
            if (isset($session["status"]) && $session["status"] == "inprogress") {
                $html .= "<form method='POST'>";
                $html .= "<button type='submit' class='btn btn-secondary' name='start-sending-sequence'>Beutalók küldése</button>";
                $html .= "</form>";
            }
        } else {
            $html .= "<div class='alert alert-danger' role='alert'>";
            $html .= "Lista föltéshez válaszd ki a céget és frissítsd az oldalt!";
            $html .= "</div>";
        }

        $html .= "<div style='margin-bottom:20px;margin-top:20px'>";
        $html .= "  <div class='mb-3'>";
        $html .= "      <label for='beutelokezeles-title' class='form-label'>Kezelés megnevezése:</label>";
        $html .= "     <input type='text' class='form-control' id='beutelokezeles-title' value='{$data["title"]}' placeholder=''>";
        $html .= "  </div>";
        $html .= "  <div class='mb-3'>";
        $html .= "    <label for='company-selector' class='form-label'>Cég kiválasztása</label>";
        $html .= "   <select class='form-select' id='company-selector' aria-label='Default select example'>";
        $html .= "        <option>Válassz céget!</option>";
        $cegek = sql_query("SELECT * FROM cegek ORDER BY megnev")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cegek as $ceg) {
            $html .= "    <option  " . ($ceg["id"] == $data["cegid"] ? "selected" : "") . " value='{$ceg["id"]}'>{$ceg["megnev"]}</option>";
        }
        $html .= "      </select>";
        $html .= "  </div>";
        $html .= "  <div class='mb-3'>";
        $html .= "      <label for='referal-sample-file-upload'  class='form-label'>Beutaló fájl feltöltése</label>";
        $html .= "      <input class='form-control' type='file' onChange='uploadReferalFile(event)' id='referal-sample-file-upload'>";
        $html .= "  </div>";

        $docAgent = new DocAgent();
        $html .= "  <div class='mb-3'>";
        $html .= "      <label for='uploaded-referal-file'  class='form-label'>Feltöltött beutaló sablon:</label>";
        $html .= "      <div id='uploaded-referal-file'>" . $docAgent->showAssetEditorForReferalFiles("referal-sample-file", $data["id"]) . "</div>";
        $html .= "  </div>";
        $html .= "<hr>";
        $html .= "</div>";

        //Felettesnek küldött levél sablon
        $html .= "<form method='POST' id='form_superior_email_settings'>";
        $html .= "  <div class='form-check' style='padding-left:0px'>";
        $html .= "      <label class='form-check-label h5' for='allow_superior_email_settings'>";
        $html .= "        <i class='fa-solid fa-user-tie'></i>&nbsp;Felettesnek küldött levél sablon";
        $html .= "      </label>";
        $html .= "      <input class='form-check-input' onChange='setSuperiorEmailSettings($(this))' data-bmid='{$_GET["szerk"]}' style='float:none;margin-left:1.5em;width:1.5em;height:1.5em' type='checkbox' " . ($data["superior_email_send"] == 1 ? "checked" : "") . " value='1' id='allow_superior_email_settings'>";
        $html .= "  </div>";
        $html .= "  <div id='superior_email_settings' style='" . ($data["superior_email_send"] == 1 ? "" : "display:none;") . "margin-top:20px'>";
        $html .= "    <div class='form-check'>";
        $html .= "        <input class='form-check-input' type='checkbox' onChange='setFileSendingMethod(\"superior\",$(this))' data-bmid='{$_GET["szerk"]}' id='send_files_to_superiors' value='1'  " . ($data["send_files_to_superior"] == 1 ? "checked='true'" : "") . "  >";
        $html .= "        <label class='form-check-label' for='send_files_to_superiors'>";
        $html .= "            Fájlok küldése e-mailben";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "    <div class='form-check'>";
        $html .= "        <input class='form-check-input' type='checkbox' onChange='setListSendingMethod(\"superior\",$(this))' data-bmid='{$_GET["szerk"]}' id='send_list_to_superiors' value='1'  " . ($data["send_list_to_superior"] == 1 ? "checked='true'" : "") . "  >";
        $html .= "        <label class='form-check-label' for='send_list_to_superiors'>";
        $html .= "            Excel lista küldése e-mailben";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='superior_email_sender_address' class='form-label'>Küldő e-mail cím:</label>";
        $html .= "       <input type='email' class='form-control' name='superior_email_sender_address' value='{$data["superior_email_sender_address"]}' placeholder='name@example.com'>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='superior_email_copy_address' class='form-label'>Másolatot kap:</label>";
        $html .= "       <input type='email' class='form-control' name='superior_email_copy_address' value='{$data["superior_email_copy_address"]}' placeholder='name@example.com'>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='superior_email_subject' class='form-label'>Üzenet tárgya:</label>";
        $html .= "       <input type='text' class='form-control' name='superior_email_subject' value='{$data["superior_email_subject"]}' placeholder='Tárgy'>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='superior_email_content' class='form-label'>Levél tartalma:</label>";
        $html .= "       <textarea class='form-control mce' id='superior_email_content' name='superior_email_content'>{$data["superior_email_content"]}</textarea>";
        $html .= "    </div>";
        $html .= "    <div class='d-grid gap-2'>";
        $html .= "        <button class='btn btn-secondary' type='submit'><i class='fa-solid fa-floppy-disk'></i>&nbsp;Mentés</button>";
        $html .= "    </div>";
        $html .= "  </div>";
        $html .= "</form>";
        $html .= "<hr>";

        //Orvosnak küldött levél sablon
        $html .= "<form method='POST' id='form_doctor_email_settings'>";
        $html .= "  <div class='form-check' style='padding-left:0px'>";
        $html .= "      <label class='form-check-label h5' for='allow_doctor_email_settings'>";
        $html .= "        <i class='fa-solid fa-user-doctor'></i>&nbsp;Orvosnak küldött levél sablon";
        $html .= "      </label>";
        $html .= "      <input class='form-check-input' onChange='setDoctorEmailSettings($(this))' data-bmid='{$_GET["szerk"]}' style='float:none;margin-left:1.5em;width:1.5em;height:1.5em' type='checkbox' " . ($data["doctor_email_send"] == 1 ? "checked" : "") . " value='1' id='allow_doctor_email_settings'>";
        $html .= "  </div>";
        $html .= "  <div id='doctor_email_settings' style='" . ($data["doctor_email_send"] == 1 ? "" : "display:none;") . "margin-top:20px'>";
        $html .= "    <div class='form-check'>";
        $html .= "        <input class='form-check-input' type='checkbox' onChange='setFileSendingMethod(\"doctors\",$(this))' data-bmid='{$_GET["szerk"]}' id='send_files_to_doctors' value='1'  " . ($data["send_files_to_doctors"] == 1 ? "checked='true'" : "") . "  >";
        $html .= "        <label class='form-check-label' for='send_files_to_doctors'>";
        $html .= "            Fájlok küldése e-mailben";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "    <div class='form-check'>";
        $html .= "        <input class='form-check-input' type='checkbox' onChange='setListSendingMethod(\"doctors\",$(this))' data-bmid='{$_GET["szerk"]}' id='send_list_to_doctors' value='1'  " . ($data["send_list_to_doctors"] == 1 ? "checked='true'" : "") . "  >";
        $html .= "        <label class='form-check-label' for='send_list_to_doctors'>";
        $html .= "            Lista küldése e-mailben";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='doctor_email_sender_address' class='form-label'>Küldő e-mail cím:</label>";
        $html .= "       <input type='email' class='form-control' name='doctor_email_sender_address' value='{$data["doctor_email_sender_address"]}' placeholder='name@example.com'>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='doctor_email_copy_address' class='form-label'>Másolatot kap:</label>";
        $html .= "       <input type='email' class='form-control' name='doctor_email_copy_address' value='{$data["doctor_email_copy_address"]}' placeholder='name@example.com'>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='doctor_email_subject' class='form-label'>Üzenet tárgya:</label>";
        $html .= "       <input type='text' class='form-control' name='doctor_email_subject' value='{$data["doctor_email_subject"]}' placeholder='Tárgy'>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='doctor_email_content' class='form-label'>Levél tartalma:</label>";
        $html .= "       <textarea class='form-control mce' name='doctor_email_content'>{$data["doctor_email_content"]}</textarea>";
        $html .= "    </div>";
        $html .= "    <div class='d-grid gap-2'>";
        $html .= "        <button class='btn btn-secondary' type='submit'><i class='fa-solid fa-floppy-disk'></i>&nbsp;Mentés</button>";
        $html .= "    </div>";
        $html .= "  </div>";
        $html .= "</form>";
        $html .= "<hr>";

        //Dolgozónak küldött levél sablon
        $html .= "<form method='POST' id='form_worker_email_settings'>";
        $html .= "  <div class='form-check' style='padding-left:0px'>";
        $html .= "      <label class='form-check-label h5' for='allow_worker_email_settings'>";
        $html .= "        <i class='fa-solid fa-user'></i>&nbsp;Dolgozónak küldött levél sablon";
        $html .= "      </label>";
        $html .= "      <input class='form-check-input' onChange='setWorkerEmailSettings($(this))' data-bmid='{$_GET["szerk"]}' style='float:none;margin-left:1.5em;width:1.5em;height:1.5em' type='checkbox' " . ($data["worker_email_send"] == 1 ? "checked" : "") . " value='1' id='allow_worker_email_settings'>";
        $html .= "  </div>";
        $html .= "  <div id='worker_email_settings' style='" . ($data["worker_email_send"] == 1 ? "" : "display:none;") . "margin-top:20px'>";
        $html .= "    <div class='form-check'>";
        $html .= "        <input class='form-check-input' type='checkbox' onChange='setFileSendingMethod(\"workers\",$(this))' data-bmid='{$_GET["szerk"]}' id='send_files_to_workers' value='1'  " . ($data["send_files_to_workers"] == 1 ? "checked='true'" : "") . "  >";
        $html .= "        <label class='form-check-label' for='send_files_to_workers'>";
        $html .= "            Fájlok küldése e-mailben";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='worker_email_sender_address' class='form-label'>Küldő e-mail cím:</label>";
        $html .= "       <input type='email' class='form-control' name='worker_email_sender_address' value='{$data["worker_email_sender_address"]}' placeholder='name@example.com'>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='worker_email_copy_address' class='form-label'>Másolatot kap:</label>";
        $html .= "       <input type='email' class='form-control' name='worker_email_copy_address' value='{$data["worker_email_copy_address"]}' placeholder='name@example.com'>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='worker_email_subject' class='form-label'>Üzenet tárgya:</label>";
        $html .= "       <input type='text' class='form-control' name='worker_email_subject' value='{$data["worker_email_subject"]}' placeholder='Tárgy'>";
        $html .= "    </div>";
        $html .= "    <div class='mb-3'>";
        $html .= "       <label for='worker_email_content' class='form-label'>Levél tartalma:</label>";
        $html .= "       <textarea class='form-control mce' name='worker_email_content'>{$data["worker_email_content"]}</textarea>";
        $html .= "    </div>";
        $html .= "    <div class='d-grid gap-2'>";
        $html .= "        <button class='btn btn-secondary' type='submit'><i class='fa-solid fa-floppy-disk'></i>&nbsp;Mentés</button>";
        $html .= "    </div>";
        $html .= "  </div>";
        $html .= "</form>";
        $html .= "<hr>";
        return $html;
    }

    private function showSuperiorsTable($superiors): string
    {
        $html = "";
        $html .= "<table class='table table-hover' id=''>";
        $html .= "  <thead>";
        $html .= "      <tr>";
        //$html .= "      <th class='' scope='col'><i class='fa-solid fa-gear'></i></th>";
        $html .= "      <th class='' scope='col'>Felettes</th>";
        $html .= "      <th class='' scope='col'>E-mail cím</th>";
        //$html .= "      <th class='' scope='col'>Rendelési hely</th>";
        $html .= "      </tr>";
        $html .= "  </thead>";
        $html .= "  <tbody>";
        foreach ($superiors as $index => $superior) {
            $html .= "<tr>";
            //$html .= "  <td><a href='?page=doctors".(!empty($supplier["id"])?"&szerk={$supplier["id"]}":"")."' title='Orvos szerkesztése' target='_blank'><i class='fa-solid fa-arrow-up-right-from-square'></i></a></td>";
            $html .= "  <td>{$superior["nev"]}</td>";
            $html .= "  <td>{$superior["email"]}</td>";
            //$html .= "  <td>{$supplier["helyszinek"]}</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody>";
        $html .= "</table>";

        return $html;
    }

    private function showSupplierTable($suppliers): string
    {
        $html = "";
        $html .= "<table class='table table-hover' id=''>";
        $html .= "  <thead>";
        $html .= "      <tr>";
        $html .= "      <th class='' scope='col'><i class='fa-solid fa-gear'></i></th>";
        $html .= "      <th class='' scope='col'>Orvos</th>";
        $html .= "      <th class='' scope='col'>E-mail cím</th>";
        $html .= "      <th class='' scope='col'>Rendelési hely</th>";
        $html .= "      </tr>";
        $html .= "  </thead>";
        $html .= "  <tbody>";
        foreach ($suppliers as $index => $supplier) {
            $html .= "<tr>";
            $html .= "  <td><a href='?page=doctors" . (!empty($supplier["id"]) ? "&szerk={$supplier["id"]}" : "") . "' title='Orvos szerkesztése' target='_blank'><i class='fa-solid fa-arrow-up-right-from-square'></i></a></td>";
            $html .= "  <td>{$supplier["nev"]}</td>";
            $html .= "  <td>{$supplier["email"]}</td>";
            $html .= "  <td>{$supplier["helyszinek"]}</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody>";
        $html .= "</table>";
        return $html;
    }

    private function showWorkersTable($data): string
    {
        $html = "";

        $html .= "<table class='table table-hover' id=''>";
        $html .= "  <thead>";
        $html .= "      <tr>";
        $html .= "      <th class='text-center align-middle' scope='col'>Törzsszám</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Szül. dátum</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Szül. dátum</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>TAJ</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Munkakör</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>A. neve</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Szül. hely</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>U. vizsgálat dátuma</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Vizsgálat lejárata</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Felettes</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>E-mail (Felettes)</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Cím</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Ellátó orvos</th>";
        $html .= "      </tr>";
        $html .= "  </thead>";
        $html .= "  <tbody>";
        foreach ($data as $index => $row) {
            $html .= "<tr>";
            $html .= "  <td>{$row["torzsszam"]}</td>";
            $html .= "  <td stly>{$row["nev"]}</td>";
            $html .= "  <td stly>{$row["szuldatum"]}</td>";
            $html .= "  <td>{$row["taj"]}</td>";
            $html .= "  <td>{$row["munkakor"]}</td>";
            $html .= "  <td>{$row["anyjaneve"]}</td>";
            $html .= "  <td>{$row["szulhely"]}</td>";
            $html .= "  <td>{$row["vizsgalatdatuma"]}</td>";
            $html .= "  <td>{$row["visszarendeles"]}</td>";
            $html .= "  <td>{$row["felettesnev"]}</td>";
            $html .= "  <td>{$row["felettesemail"]}</td>";
            $html .= "  <td>{$row["cim"]}</td>";
            $html .= "  <td>{$row["ellatoorvos"]}</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody>";
        $html .= "</table>";
        return $html;
    }

    private function _getSupplierDetails($orvosok, $companyId, $data): array
    {
        $array = [];
        foreach ($data as $key => $value) {
            $supplier = sql_query("SELECT o.id,o.nev,o.email,GROUP_CONCAT(DISTINCT(h.cim)) as helyszinek FROM orvosok o 
                                   LEFT JOIN orvos_beosztas_new beo ON o.id=beo.orvosid AND INSTR(beo.beocegek,?) AND beo.aktiv=1 
                                   LEFT JOIN helyszinek h ON h.id=beo.helyszinid
                                   WHERE o.nev=?
                                   GROUP BY o.id", ["|{$companyId}|", $value["ellatoorvos"]])->fetch(PDO::FETCH_ASSOC);
            if ($supplier) {
                $exists = array_search($supplier["email"], array_column($array, "email"));
            } else {
                $exists = false;
            }

            if ($exists === false) {
                //$array[] = $supplier;
                if (!$supplier) {
                    if (!$supplier) {
                        sql_query(
                            "INSERT INTO beutalo_kezeles_felettesek SET bmid=?, email=?, nev=?, created=?",
                            [$_GET["szerk"], $value["felettesemail"], $value["felettesnev"], date("Y-m-d H:i:s")]
                        );
                        $supplier["id"] = sql_insert_id();
                    }
                    $array[] = ["id" => null, "nev" => $value["ellatoorvos"], "email" => null, "helyszinek" => null, "dolgozok" => [$value["taj"]]];
                } else {
                    $array[] = ["id" => $supplier["id"], "nev" => $supplier["nev"], "email" => $supplier["email"], "helyszinek" => $supplier["helyszinek"], "dolgozok" => [$value["taj"]]];
                }
            } else {
                $array[$exists]["dolgozok"][] = $value["taj"];
                //$array[] = ["id" => null, "nev" => $value, "email" => null, "helyszinek" => null, "dolgozok"=>[$value["taj"]]];
            }
        }
        return $array;
    }

    private function getSessionIdDetails($sessionId): array
    {

        if (!$array = sql_query("SELECT * FROM beutalo_kezeles_sessions WHERE id=?", [$sessionId])->fetch(PDO::FETCH_ASSOC)) {
            $array = [];
        }

        return $array;
    }

    private function getSupplierDetails($companyId, $data): array
    {
        $array = [];
        foreach ($data as $key => $value) {
            $q = sql_query("SELECT * FROM beutalo_kezeles_orvosok WHERE nev=? AND bmid=? AND sessionid=?", [$value["ellatoorvos"], $value["bmid"], $value["sessionid"]])->fetch(PDO::FETCH_ASSOC);
            $exists = array_search($value["ellatoorvos"], array_column($array, "nev"));

            if ($exists === false) {

                $supplier = sql_query("SELECT o.id,o.nev,o.email,GROUP_CONCAT(DISTINCT(h.cim)) as helyszinek FROM orvosok o 
                                   LEFT JOIN orvos_beosztas_new beo ON o.id=beo.orvosid AND INSTR(beo.beocegek,?) AND beo.aktiv=1 
                                   LEFT JOIN helyszinek h ON h.id=beo.helyszinid
                                   WHERE o.nev=?
                                   GROUP BY o.id", ["|{$companyId}|", $value["ellatoorvos"]])->fetch(PDO::FETCH_ASSOC);

                if (!$q) {
                    if ($supplier) {
                        sql_query(
                            "INSERT INTO beutalo_kezeles_orvosok SET bmid=?,sessionid=?, email=?, nev=?, created=?",
                            [$_GET["szerk"], $value["sessionid"], $supplier["email"], $supplier["nev"], date("Y-m-d H:i:s")]
                        );
                        $q["file"] = null;
                        $q["id"] = sql_insert_id();
                    } else {
                        sql_query(
                            "INSERT INTO beutalo_kezeles_orvosok SET bmid=?,sessionid=?, email=?, nev=?, created=?",
                            [$_GET["szerk"], $value["sessionid"], null, $value["ellatoorvos"], date("Y-m-d H:i:s")]
                        );
                        $q["id"] = sql_insert_id();
                        $q["file"] = null;
                    }
                }
                $array[] = ["id" => $q["id"], "file" => $q["file"], "nev" => $value["ellatoorvos"], "email" => (!empty($supplier["email"]) ? $supplier["email"] : null), "helyszinek" => (!empty($supplier["helyszinek"]) ? $supplier["helyszinek"] : null), "dolgozok" => [$value["taj"]]];
            } else {
                $array[$exists]["dolgozok"][] = $value["taj"];
                //$array[] = ["id" => null, "nev" => $value, "email" => null, "helyszinek" => null, "dolgozok"=>[$value["taj"]]];
            }
        }
        return $array;
    }

    private function getSuperiorDetails($data)
    {
        $felettesek = [];
        foreach ($data as $value) {
            $q = sql_query("SELECT * FROM beutalo_kezeles_felettesek WHERE email=? AND bmid=? AND sessionid=?", [$value["felettesemail"], $value["bmid"], $value["sessionid"]])->fetch(PDO::FETCH_ASSOC);
            $exists = array_search($value["felettesemail"], array_column($felettesek, "email"));
            if ($exists === false) {
                if (!$q) {
                    sql_query(
                        "INSERT INTO beutalo_kezeles_felettesek SET bmid=?, sessionid=?, email=?, nev=?, created=?",
                        [$_GET["szerk"], $value["sessionid"], $value["felettesemail"], $value["felettesnev"], date("Y-m-d H:i:s")]
                    );
                    $q["id"] = sql_insert_id();
                }
                $felettesek[] = ["id" => $q["id"], "file" => $q["file"], "nev" => $value["felettesnev"], "email" => $value["felettesemail"], "dolgozok" => [$value["taj"]]];
            } else {
                $felettesek[$exists]["dolgozok"][] = $value["taj"];
            }
        }
        return $felettesek;
    }

    private function showReferalFilesTable($data, $felettesek, $orvosok): string
    {
        $html = "";

        if (empty($data[0]["file"])) {
            $html .= "<form method='post'><button type='submit'name='create-referal-files' class='btn btn-secondary'>Beutalók létrehozása</button></form>";
        } else {
            $html .= "<ul class='nav nav-tabs' id='myTab' role='tablist'>";
            $html .= "    <li class='nav-item' role='presentation'>";
            $html .= "        <button class='nav-link active' id='workers-files-tab' data-bs-toggle='tab' data-bs-target='#workers-files-tab-pane' type='button' role='tab' aria-controls='workers-files-tab-pane' aria-selected='true'>Dolgozók részére</button>";
            $html .= "    </li>";
            $html .= "    <li class='nav-item' role='presentation'>";
            $html .= "        <button class='nav-link' id='superiors-files-tab' data-bs-toggle='tab' data-bs-target='#superiors-files-tab-pane' type='button' role='tab' aria-controls='superiors-files-tab-pane' aria-selected='true'>Felettesek részére</button>";
            $html .= "    </li>";
            $html .= "    <li class='nav-item' role='presentation'>";
            $html .= "        <button class='nav-link' id='suppliers-files-tab' data-bs-toggle='tab' data-bs-target='#suppliers-files-tab-pane' type='button' role='tab' aria-controls='suppliers-files-tab-pane' aria-selected='false'>Ellátók részére</button>";
            $html .= "    </li>";
            $html .= "</ul>";
            $html .= "<div class='tab-content' id='myTabContent'>";
            $html .= "    <div class='tab-pane pt-3 ps-3 fade show active' id='workers-files-tab-pane' role='tabpanel' aria-labelledby='workers-files-tab' tabindex='0'>" . $this->showWorkersFiles($data) . "</div>";
            $html .= "    <div class='tab-pane pt-3 ps-3 fade' id='superiors-files-tab-pane' role='tabpanel' aria-labelledby='superiors-files-tab' tabindex='0'>" . $this->showSuperiorsFiles($felettesek, $data) . "</div>";
            $html .= "    <div class='tab-pane pt-3 ps-3 fade' id='suppliers-files-tab-pane' role='tabpanel' aria-labelledby='wosuppliers-filesrkers-tab' tabindex='0'>" . $this->showSuppliersFiles($orvosok, $data) . "</div>";
            $html .= "</div>";
        }

        return $html;
    }

    private function showWorkersFiles($data)
    {
        $docAgent = new DocAgent();
        $html = "";
        $html .= "<table class='table table-hover'>";
        $html .= "  <thead>";
        $html .= "      <tr>";
        $html .= "      <th class='text-center align-middle' scope='col'>Teljesnév</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>TAJ</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Munkakör</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Fájlnév</th>";
        $html .= "      </tr>";
        $html .= "  </thead>";
        foreach ($data as $index => $row) {
            $link = " - ";
            if (!empty($row["file"])) {
                if ($fileData = sql_query("SELECT * FROM dokumentumok WHERE id=?", [$row["file"]])->fetch(PDO::FETCH_ASSOC)) {
                    $link = "<a target='_blank' href='https://bejelentkezes.hungariamed.hu/?downloaddoc&f={$fileData["id"]}&k={$fileData["kod"]}' >{$fileData["megnev"]}</a>";
                }
            }


            $html .= "<tr>";
            $html .= "  <td>{$row["nev"]}</td>";
            $html .= "  <td stly>{$row["taj"]}</td>";
            $html .= "  <td>{$row["munkakor"]}</td>";
            $html .= "  <td>{$link}</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody>";
        $html .= "</table>";

        return $html;
    }

    private function showSuperiorsFiles($felettesek, $data)
    {
        //$this->createSuperiorFilePacks($data);

        /*$q = sql_query("SELECT * FROM dokumentumok WHERE datatype=? OR datatype=?",["superior-file-pack","personal-referal-file"])->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($q);
        echo "</pre>";
        $docAgent= new DocAgent();
        foreach($q as $r){
            echo $docAgent->deleteDoc($r["id"],$r["kod"]);
        }
        $q = sql_query("SELECT * FROM dokumentumok WHERE datatype=? OR datatype=?",["superior-file-pack","personal-referal-file"])->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($q);
        echo "</pre>";*/

        /*echo "<pre>";
        print_r($felettesek);
        echo "</pre>";*/

        $html = "";
        $html .= "<table class='table table-hover'>";
        $html .= "  <thead>";
        $html .= "      <tr>";
        $html .= "      <th class='text-center align-middle' scope='col'>Teljesnév</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>E-mail</th>";
        //$html .= "      <th class='text-center align-middle' scope='col'>Fájl</th>";
        $html .= "      </tr>";
        $html .= "  </thead>";
        foreach ($felettesek as $index => $row) {
            $link = " - ";
            if (!empty($row["file"])) {
                if ($fileData = sql_query("SELECT * FROM dokumentumok WHERE id=?", [$row["file"]])->fetch(PDO::FETCH_ASSOC)) {
                    $link = "<a target='_blank' href='https://bejelentkezes.hungariamed.hu/?downloaddoc&f={$fileData["id"]}&k={$fileData["kod"]}' >Letöltés</a>";
                }
            }


            $html .= "<tr>";
            $html .= "  <td>{$row["nev"]}</td>";
            $html .= "  <td stly>{$row["email"]}</td>";
            $html .= "  <td>{$link}</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody>";
        $html .= "</table>";

        return $html;
    }

    private function showSuppliersFiles($orvosok, $data)
    {
        $html = "";
        $html .= "<table class='table table-hover'>";
        $html .= "  <thead>";
        $html .= "      <tr>";
        $html .= "      <th class='text-center align-middle' scope='col'>Orvos</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>E-mail</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Rendelési hely</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Fájl</th>";
        $html .= "      </tr>";
        $html .= "  </thead>";
        foreach ($orvosok as $index => $row) {
            $link = " - ";
            if (!empty($row["file"])) {
                if ($fileData = sql_query("SELECT * FROM dokumentumok WHERE id=?", [$row["file"]])->fetch(PDO::FETCH_ASSOC)) {
                    $link = "<a target='_blank' href='https://bejelentkezes.hungariamed.hu/?downloaddoc&f={$fileData["id"]}&k={$fileData["kod"]}' >Letöltés</a>";
                }
            }

            $html .= "<tr>";
            $html .= "  <td>{$row["nev"]}</td>";
            $html .= "  <td stly>{$row["email"]}</td>";
            $html .= "  <td stly>{$row["helyszinek"]}</td>";
            $html .= "  <td>{$link}</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody>";
        $html .= "</table>";

        return $html;
    }

    private function showLogTable() {
        $notifications = sql_query("SELECT * FROM notifications n
                           WHERE n.tipus='referal-notifcation-superior' 
                           OR n.tipus='referal-notifcation-doctor' 
                           OR n.tipus='referal-notifcation-worker'"
                        )->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        foreach($notifications as $notification){
            if($notification["tipus"]=="referal-notifcation-superior"){
                $q=sql_query("SELECT * FROM beutalo_kezeles_felettesek WHERE id=?",[$notification["objectid"]])->fetch(PDO::FETCH_ASSOC);
                
                $link = " - ";
                if (!empty($q["file"])) {
                    if ($fileData = sql_query("SELECT * FROM dokumentumok WHERE id=?", [$q["file"]])->fetch(PDO::FETCH_ASSOC)) {
                        $link = "<a target='_blank' href='https://bejelentkezes.hungariamed.hu/?downloaddoc&f={$fileData["id"]}&k={$fileData["kod"]}' >Letöltés</a>";
                    }
                }

                $data[] = [
                    "id"=>$q["id"],
                    "nev"=>$q["nev"],
                    "email"=>$q["email"],
                    "type"=>"Felettes",
                    "file" => $link,
                    "sent"=>$notification["datum"]
                ];
            }

            if($notification["tipus"]=="referal-notifcation-doctor"){
                $q=sql_query("SELECT * FROM beutalo_kezeles_orvosok WHERE id=?",[$notification["objectid"]])->fetch(PDO::FETCH_ASSOC);

                $link = " - ";
                if (!empty($q["file"])) {
                    if ($fileData = sql_query("SELECT * FROM dokumentumok WHERE id=?", [$q["file"]])->fetch(PDO::FETCH_ASSOC)) {
                        $link = "<a target='_blank' href='https://bejelentkezes.hungariamed.hu/?downloaddoc&f={$fileData["id"]}&k={$fileData["kod"]}' >Letöltés</a>";
                    }
                }

                $data[] = [
                    "id"=>$q["id"],
                    "nev"=>$q["nev"],
                    "email"=>$q["email"],
                    "type"=>"Orvos",
                    "file" => $link,
                    "sent"=>$notification["datum"]
                ];
            }
        }
        //Rendezés
        $keys = array_column($data, "sent");
        array_multisort($keys, SORT_DESC, $data);

        $html = "";

        $html .= "<table class='table table-hover' id=''>";
        $html .= "  <thead>";
        $html .= "      <tr>";
        $html .= "      <th class='text-center align-middle' scope='col'>ID.</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Teljes név</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>E-mail</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Típus</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Csatolmány</th>";
        $html .= "      <th class='text-center align-middle' scope='col'>Elküldve</th>";
        $html .= "      </tr>";
        $html .= "  </thead>";
        $html .= "  <tbody>";
        foreach ($data as $index => $row) {
            $html .= "<tr>";
            $html .= "  <td>{$row["id"]}</td>";
            $html .= "  <td stly>{$row["nev"]}</td>";
            $html .= "  <td stly>{$row["email"]}</td>";
            $html .= "  <td>{$row["type"]}</td>";
            $html .= "  <td>{$row["file"]}</td>";
            $html .= "  <td>{$row["sent"]}</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody>";
        $html .= "</table>";
        return $html;

    }



    private function getUserName($uid): string
    {
        $user = sql_query("SELECT nev FROM users WHERE id=?", [$uid])->fetch(PDO::FETCH_ASSOC);
        return $user["nev"];
    }

    private function replaceOnNull($data)
    {
        if ($data == "0" || $data == null) {
            return " - ";
        }
        return $data;
    }

    private function setReferalValues($data, $input)
    {
        $q = sql_query("SELECT * FROM kockazati_tenyezok WHERE munkakor=? AND cegid=?", array($data["munkakor"], $data["cegid"]));
        while ($r = sql_fetch_array($q)) {
            foreach ($r as $key => $value) {
                $input[$key] = $value;
            }
        }

        return $input;
    }

    private function createReferalFile($parameters = [], $patient = []): int
    {
        $input = [];
        $docPath =  __DIR__ . "/other/tmp/";
        $utils = new Utils();
        $docAgent = new DocAgent();
        $sampleFileContent = $docAgent->getDocByDataType("referal-sample-file", $_GET["szerk"]);
        $sampleFilePath = $docPath . "referal-sample-file.pdf";
        file_put_contents($sampleFilePath, $sampleFileContent);
        $pdf = new Pdf($sampleFilePath);
        $auth_id = $utils->generateRandomStringv2(32);

        $filename = str_replace(" ", "_", trim($patient["nev"])) . ".pdf";

        //Kockázati tényezők beolvasása
        foreach ($parameters as $key => $value) {
            $input[$key] = $value;
        }

        //Páciens adatok beolvasása
        $input["nev"] = $this->pdfChars($patient["nev"]);
        $input["taj"] = $patient["taj"];
        $input["szuldatum"] = date("Y.m.d", strtotime($patient["szuldatum"]));
        $input["munkakor"] = $this->pdfChars($patient["munkakor"]);
        $input["vizsgalat"] = $this->pdfChars("Időszakos- Foglalkozás Egészségügyi vizsgálat");
        $input["indoklas"] = $this->pdfChars("Elözö vizsgálat érvényessége hamarosan lejár");
        $input["kelte"] = date("Y.m.d");
        $input["keltezes"] = date("Y.m.d");
        $input["teljescim"] = $this->pdfChars($patient["cim"]);
        $input["auth_id"] = $auth_id;
        //$input[""]=$this->pdfChars($patient["nev"]);

        //Fájl localhost mentése
        $result = $pdf->fillForm($input)->flatten()->saveAs($docPath . $filename);

        $array = [
            "datatype" => "personal-referal-file",
            "dataid"   => intval($patient["id"]),
            "megnev"   => $filename,
        ];

        $docAgent->saveLocalDoc($docPath . $filename, $array);
        sql_query("UPDATE beutalo_kezeles_data SET file=? WHERE id=?;", [$docAgent->lastSavedId, $patient["id"]]);
        unlink($sampleFilePath);
        return $docAgent->lastSavedId;
    }

    private function createSuperiorFilePacks($data)
    {

        $docAgent = new DocAgent();
        $docPath =  __DIR__ . "/other/tmp/";
        $felettesek = $this->getSuperiorDetails($data);
        foreach ($felettesek as $key => $superior) {
            $files = [];
            foreach ($superior["dolgozok"] as $taj) {
                $zipName = "Beutalok.zip";
                $zipPath = $docPath . $zipName;
                $index = array_search($taj, array_column($data, "taj"));
                if ($index !== false) {
                    if (!empty($data[$index]["file"])) {
                        //A temp könyvtárba pakolom a páciens fájlokat amiket majd zippelnem kell.
                        $personalFileContent = $docAgent->getDoc($data[$index]["file"]);
                        $personalFilePath = $docPath . str_replace(" ", "_", trim($data[$index]["nev"])) . ".pdf";
                        file_put_contents($personalFilePath, $personalFileContent);
                        $files[] = $personalFilePath;
                    }
                }
            }

            exec("zip -j {$zipPath} " . implode(" ", $files));
            $array = [
                "datatype" => "superior-file-pack",
                "dataid"   => intval($superior["id"]),
                "megnev"   => $zipName,
            ];
            $docAgent->saveLocalDoc($zipPath, $array, ["zip"]);
            sql_query("UPDATE beutalo_kezeles_felettesek SET file=? WHERE id=?;", [$docAgent->lastSavedId, $superior["id"]]);
            foreach ($files as $file) {
                unlink($file);
            }
        }
        return;
    }

    private function createSupplierFilePacks($data, $bmid)
    {

        $docAgent = new DocAgent();
        $docPath =  __DIR__ . "/other/tmp/";
        $beutaloManagement = sql_query("SELECT * FROM beutalo_kezeles WHERE id=?", [$bmid])->fetch(PDO::FETCH_ASSOC);
        $orvosok = $this->getSupplierDetails($beutaloManagement["cegid"], $data);

        foreach ($orvosok as $key => $supplier) {
            $files = [];
            foreach ($supplier["dolgozok"] as $taj) {
                $zipName = "Beutalok.zip";
                $zipPath = $docPath . $zipName;
                $index = array_search($taj, array_column($data, "taj"));
                if ($index !== false) {
                    if (!empty($data[$index]["file"])) {
                        //A temp könyvtárba pakolom a páciens fájlokat amiket majd zippelnem kell.
                        $personalFileContent = $docAgent->getDoc($data[$index]["file"]);
                        $personalFilePath = $docPath . str_replace(" ", "_", trim($data[$index]["nev"])) . ".pdf";
                        file_put_contents($personalFilePath, $personalFileContent);
                        $files[] = $personalFilePath;
                    }
                }
            }

            echo exec("zip -j {$zipPath} " . implode(" ", $files));
            $array = [
                "datatype" => "supplier-file-pack",
                "dataid"   => intval($supplier["id"]),
                "megnev"   => $zipName,
            ];
            echo $docAgent->saveLocalDoc($zipPath, $array, ["zip"]);
            sql_query("UPDATE beutalo_kezeles_orvosok SET file=? WHERE id=?;", [$docAgent->lastSavedId, $supplier["id"]]);
            foreach ($files as $file) {
                unlink($file);
            }
        }
        return;
    }

    private function pdfChars($text)
    {
        return str_replace(["ő", "ű", "í", "Ő", "Ű", "Í"], ["ö", "ü", "i", "Ö", "Ü", "I"], $text);
    }
}
