<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use mikehaertl\pdftk\Pdf;

class fgszCostumeFunctions
{
    const ASSET_REFERAL_SAMPLE_PDF  = "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/FGSZ_beutalo.pdf";
    const TEMPORAL_LIBRARY_PATH     = "/var/www/marci/onlinebejelentkezes/library/other/tmp/";
    //const ASSET_REFERAL_SAMPLE_PDF = "/var/www/marci/onlinebejelentkezes/public/admin/templates/Dok1.pdf";
    public $utils;
    private $docAgent;
    function __construct()
    {
        $this->utils = New Utils();
        $this->docAgent = New DocAgent();
    }

    public function showUI(){
        $html = "";

        $html.=$this->fileInput();

        $html.=$this->resultFields(); 

        echo $html;
    }

    private function fileInput(){
        $html = "";

        $html.= "<div class=\"container-fluid p-3\">";
        $html.= "    <form enctype=\"multipart/form-data\" method=\"POST\">";
        /*$html.= "        <div class=\"form-check form-switch\">";
        $html.= "            <input class=\"form-check-input\" type=\"checkbox\" role=\"switch\" id=\"flexSwitchOnlyPDF\" checked>";
        $html.= "            <label class=\"form-check-label\" for=\"flexSwitchOnlyPDF\">Csak pdf kiterjesztésű fájlok használata</label>";
        $html.= "        </div>";
        $html.= "        <div class=\"form-check form-switch\">";
        $html.= "            <input class=\"form-check-input\" type=\"checkbox\" role=\"switch\" id=\"flexSwitchIgnoreInProcess\" checked>";
        $html.= "            <label class=\"form-check-label\" for=\"flexSwitchCheckChecked\">Folyamatban lévő laborok figyelmenkívül";
        $html.= "                hagyása</label>";
        $html.= "        </div>";
        $html.= "        <div class=\"form-check form-switch\">";
        $html.= "            <input class=\"form-check-input\" type=\"checkbox\" role=\"switch\" id=\"flexSwitchIgnoreRepeateSampple\" checked>";
        $html.= "            <label class=\"form-check-label\" for=\"flexSwitchCheckChecked\">Ismétlendő laborok figyelmenkívül";
        $html.= "                hagyása</label>";
        $html.= "        </div>";*/
        /*$html.= "        <div class=\"input-group mb-3\">";
        $html.= "            <input type=\"file\" webkitdirectory directory multiple class=\"form-control\" id=\"labdocuments\"";
        $html.= "                name=\"labdocuments[]\" aria-describedby=\"\" onChange=\"uploadFiles()\" aria-label=\"Upload\">";
        $html.= "            <span class=\"input-group-text\" id=\"inputGroup-sizing-default\">Labor dokumentumok</span>";
        $html.= "        </div>";*/
        $html.= "        <div class=\"input-group mb-3\">";
        $html.= "            <input type=\"file\"";
        $html.= "                accept=\"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel\"";
        $html.= "                class=\"form-control\" id=\"excelList\" name=\"excelList[]\" aria-describedby=\"\" onChange=\"uploadFGSZExcelFile()\"";
        $html.= "                aria-label=\"Upload\">";
        $html.= "            <span class=\"input-group-text\" id=\"inputGroup-sizing-default\">FGSZ állománylista (.xlsx)</span>";
        $html.= "        </div>";
        $html.= "        <div class=\"progress mt-3 visually-hidden\">";
        $html.= "            <div class=\"progress-bar progress-bar-striped progress-bar-animated\" role=\"progressbar\" aria-valuenow=\"10\"";
        $html.= "                aria-valuemin=\"0\" aria-valuemax=\"100\" style=\"width: 40%\"></div>";
        $html.= "        </div>";
        $html.= "    </form>";
        $html.= "</div>";

        return $html;
    }

    public function resultFields(){
        $html = "";

        $html.="<ul class=\"nav nav-tabs\" id=\"myTab\" role=\"tablist\">";
        $html.="    <li class=\"nav-item\" role=\"presentation\">";
        $html.="        <button class=\"nav-link active\" id=\"data-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#data-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"data-tab-pane\" aria-selected=\"true\">Adatok</button>";
        $html.="    </li>";
        $html.="    <li class=\"nav-item\" role=\"presentation\">";
        $html.="        <button class=\"nav-link\" id=\"notification-editor-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#notification-editor-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"notification-editor-tab-pane\" aria-selected=\"false\">Üzenet sablonok</button>";
        $html.="    </li>";
        $html.="    <li class=\"nav-item\" role=\"presentation\">";
        $html.="        <button class=\"nav-link\" id=\"referals-management-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#referals-management-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"referals-management-tab-pane\" aria-selected=\"false\">Beutalók generálása</button>";
        $html.="    </li>";
        $html.="    <li class=\"nav-item\" role=\"presentation\">";
        $html.="        <button class=\"nav-link\" id=\"referal-arrays-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#referal-arrays-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"referal-arrays-tab-pane\" aria-selected=\"false\">Beutaló tömbök</button>";
        $html.="    </li>";
        $html.="    <li class=\"nav-item\" role=\"presentation\">";
        $html.="        <button class=\"nav-link\" id=\"sending-notifications-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#sending-notifications-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"sending-notifications-tab-pane\" aria-selected=\"false\">Értesitések kezelése</button>";
        $html.="    </li>";
        $html.="</ul>";

        $html.="<div class=\"tab-content\" id=\"\">";

        $html.="    <div class=\"tab-pane fade show active\" id=\"data-tab-pane\" role=\"tabpanel\" aria-labelledby=\"data-tab\" tabindex=\"0\">";
        $html.="        <div class=\"container-fluid\">";
        $html.="            <div class=\"row\">";
        /*$html.="                <div class=\"col-sm\" id=\"lab-document-container\" style=\"max-height: 800px;overflow-x:visible;overflow-y: scroll;\"> ";
        $html.="                </div>";*/
        $html.="                <div class=\"col-sm\" id=\"excel-list-container\" style=\"max-height: 800px;overflow-x:visible;overflow-y: scroll;\">";

        if(sql_query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'temp_fgsz_workforce';")){
            $html.= $this->showDataFromSQL();
        } 
        $html.="                </div>";
        /*$html.="                <div class=\"col-sm\" id=\"summit-data-structures\" style=\"max-height: 800px;overflow-x:visible;overflow-y: scroll;\">";
        $html.="                </div>";*/
        $html.="            </div>";
        $html.="        </div>";
        $html.="    </div>";

        $html.="    <div class=\"tab-pane fade\" id=\"notification-editor-tab-pane\" role=\"tabpanel\" aria-labelledby=\"statistics-tab\" tabindex=\"0\">";
        //$html.="        <button type=\"button\" onClick=\"callPatientSessionData()\" class=\"btn btn-primary\">Páciens session adatok megjelenítése</button>";
        //$html.="        <div id=\"result-of-patient-session-call\">";
        $html.=             $this->showNotificationEditors();
        //$html.="        </div>";
        $html.="    </div>";

        $html.="    <div class=\"tab-pane fade\" id=\"referals-management-tab-pane\" role=\"tabpanel\" aria-labelledby=\"referals-management-tab\" tabindex=\"0\">";
        $html.=         $this->showReferalsManagement();
        $html.="    </div>";

        $html.="    <div class=\"tab-pane fade\" id=\"referal-arrays-tab-pane\" role=\"tabpanel\" aria-labelledby=\"referal-arrays-tab\" tabindex=\"0\">";
        $html.=         $this->showReferalArrayManagement();
        $html.="    </div>";

        $html.="    <div class=\"tab-pane fade\" id=\"sending-notifications-tab-pane\" role=\"tabpanel\" aria-labelledby=\"sending-notifications-tab\" tabindex=\"0\">";
        $html.=         $this->showSendingNotificationsManagement();
        $html.="    </div>";
        $html.="</div>";

        return $html;
    }

    public function processExcelList(){
        $html = $list = "";
        $data = $col_titles = $patients = $details = [];
        $unit = 0;
        $workForceArray = [];
        $filePath = __DIR__ . "/other/tmp/";

        //Fájlok kiolvasása az excelből:
        move_uploaded_file($_FILES["excel-list"]["tmp_name"], $excelFile = $filePath . $_FILES["excel-list"]["name"]);
        $spreadsheet = new Spreadsheet();
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($excelFile);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        unlink($excelFile);

        $col_titles = $rows[0];
        unset($rows[0]);
        $rows = array_values($rows);

        sql_query("DROP TABLE IF EXISTS temp_fgsz_workforce");

        sql_query("CREATE TABLE temp_fgsz_workforce (
                        id                  INT AUTO_INCREMENT PRIMARY KEY,
                        name                VARCHAR(100) NOT NULL, /*1*/
                        taj                 VARCHAR(100) NOT NULL, /*20*/
                        birth_date          DATE NOT NULL,         /*11*/
                        address             VARCHAR(100) NOT NULL, /*26*/
                        position_name       VARCHAR(100) NOT NULL, /*6*/
                        rn_number           VARCHAR(100) NOT NULL, /*0*/
                        kh_code             VARCHAR(100) NOT NULL, /*2*/
                        kh_name             VARCHAR(100) NOT NULL, /*4*/
                        organization_name   VARCHAR(150) NOT NULL, /*9*/
                        service_location    VARCHAR(250) NOT NULL, /*3*/
                        comment             VARCHAR(250),          /*27*/
                        previous_exam_date  DATE NOT NULL,         /*16*/
                        next_exam_date      DATE NOT NULL,         /*17*/
                        superior_name       VARCHAR(150) NOT NULL, /*21*/
                        superior_email      VARCHAR(150) NOT NULL, /*22*/
                        created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );
        ");

        //Adatok kiolvasása és temporális táblába helyezése
        foreach($rows as $dictionary){

                $dictionary[11] = $this->excelDateToMysqlDate($dictionary[11]);
                $dictionary[16] = $this->excelDateToMysqlDate($dictionary[16]);
                $dictionary[17] = $this->excelDateToMysqlDate($dictionary[17]);

                //Ha nincsen következő vizsgálat datálva, akkor kilépett, nem kell vele foglalkozni.
                if(empty($dictionary[17])){
                    continue;
                }

                if(strlen($dictionary[20])<9){
                    $dictionary[20] = "0".$dictionary[20];
                }

                  $data = [              
                    "name"=>                $dictionary[1],/*1*/
                    "taj"=>                 $dictionary[20],/*20*/
                    "birth_date"=>          $dictionary[11],/*11*/
                    "address"=>             $dictionary[26],/*26*/
                    "position_name"=>       $dictionary[6],/*6*/
                    "rn_number"=>           $dictionary[0],/*0*/
                    "kh_code"=>             $dictionary[2],/*2*/
                    "kh_name"=>             $dictionary[4],/*4*/
                    "organization_name"=>   $dictionary[9],/*9*/
                    "service_location"=>    $dictionary[3],/*3*/
                    "comment"=>             $dictionary[27],/*27*/
                    "previous_exam_date"=>  $dictionary[16],/*16*/
                    "next_exam_date"=>      $dictionary[17],/*17*/
                    "superior_name"=>       $dictionary[21],/*21*/
                    "superior_email"=>      $dictionary[22],/*22*/
                ];

                sql_query("INSERT INTO temp_fgsz_workforce 
                           SET name=?, taj=?, birth_date=?, address=?, position_name=?, rn_number=?, kh_code=?, kh_name=?, 
                               organization_name=?, service_location=?, comment=?, previous_exam_date=?, next_exam_date=?, 
                               superior_name=?, superior_email=?",
                            [trim($dictionary[1]),trim($dictionary[20]),trim($dictionary[11]),trim($dictionary[26]),trim($dictionary[6]),trim($dictionary[0]),trim($dictionary[2]),trim($dictionary[4]),
                            trim($dictionary[9]),trim($dictionary[3]),trim($dictionary[27]),trim($dictionary[16]),trim($dictionary[17]),trim($dictionary[21]),trim($dictionary[22])]
                );
                //Beutalófájl létrehozása
                //$this->createReferalPDF($data);

                //Itt kéne létrehozzam az insertelt rekord alapján a beutaló fájlt... a rekord valamely adata vagy adatainak összessége.
                //Rá kéne, hogy tudjon mutatni a páciens beutalójára igy ha törlöm is a fájlt valahogyan össze tudom kapcsolni a páciensel
                //Ez valószinüleg a taj száma lesz a páciensnek... az lenne a legegyszerűbb
        }

        $spreadsheet->disconnectWorksheets(); // cellák/worksheet-ek leválasztása
        unset($spreadsheet);                  // referencia eldobása
        gc_collect_cycles();

        $html.= $this->showDataFromSQL();

        return $html;
    }

    public function showDataFromSQL($container="workforce",$statement="",$year=null){
        $html = $list = $positionStyle = $subMenu = "";
        $unit = $existsCurrentFiles = $existsCurrentFile = 0;
        $missingPositions = [];

        $data = sql_query("SELECT * FROM temp_fgsz_workforce {$statement};")->fetchAll(PDO::FETCH_ASSOC);

        foreach($data as $each){
            $unit++;
            $subMenu = "";
            $existsCurrentFile = 0; 
            if(!$this->checkPositonExistInRiskFactorsTable($each["position_name"],220)){
                $positionStyle = "style=\"color:red;font-weight:bold\"";
                if(!in_array($each["position_name"],$missingPositions)){
                    $missingPositions[] = $each["position_name"];
                }
                
            }else{
                $positionStyle = "";
            }

            $files = sql_query("SELECT * FROM dokumentumok WHERE customid=?",[$each["taj"]])->fetchAll(PDO::FETCH_ASSOC);

            foreach($files as $file){
                
                if($file["megnev"]=="Beutaló ".date("Y",strtotime($each["next_exam_date"]))){
                    $existsCurrentFile++;
                    $existsCurrentFiles++;
                }
                $downloadLink = $this->docAgent->getDocURL($file,"bejelentkezes.hungariamed.hu");
                $subMenu .= "<tr>";
                $subMenu .= "  <td colspan=\"16\">";
                $subMenu .=    $file["megnev"]." - ".$file["datum"]." - <i class=\"fa-solid fa-eye\"></i> - <a href=\"{$downloadLink}\" title=\"Fájl letöltése\" target=\"_blank\"><i class=\"fa-solid fa-file-arrow-down\"></i></a>";
                $subMenu .= "  </td>";
                $subMenu .= "</tr>";
            }

            $list .= "<tbody class=\"align-middle\" style=\"border-top:none\">";
            $list .= "<tr data-toggle=\"collapse\" onClick=\"$('#{$container}-p{$unit}').fadeToggle(500)\">";
            $list .= "    <td class=\"text-center\">{$unit}.</td>";
            if($year){
                if($existsCurrentFile>0){
                    $statusIcon="<i style=\"color:#008000;font-size: 18px;\" class=\"fa-solid fa-file-circle-check\"></i>";
                }else{
                    $statusIcon="<i style=\"color:red;font-size: 18px;\" class=\"fa-solid fa-file-circle-xmark\"></i>";
                }
              $list .= "    <td class=\"text-center\">{$statusIcon}</td>";  
            }
            $list .= "    <td>{$each["name"]}</td>";
            $list .= "    <td>{$each["taj"]}</td>";
            $list .= "    <td style=\"white-space:nowrap\">{$each["birth_date"]}</td>";
            $list .= "    <td>{$each["address"]}</td>";
            $list .= "    <td {$positionStyle}>{$each["position_name"]}</td>";
            $list .= "    <td>{$each["rn_number"]}</td>";
            $list .= "    <td>{$each["kh_code"]}</td>";
            $list .= "    <td>{$each["kh_name"]}</td>";
            $list .= "    <td>{$each["organization_name"]}</td>";
            $list .= "    <td>{$each["service_location"]}</td>";
            $list .= "    <td>{$each["comment"]}</td>";
            $list .= "    <td style=\"white-space:nowrap\">{$each["previous_exam_date"]}</td>";
            $list .= "    <td style=\"white-space:nowrap\">{$each["next_exam_date"]}</td>";
            $list .= "    <td>{$each["superior_name"]}</td>";
            $list .= "    <td>{$each["superior_email"]}</td>";
            $list .= "</tr>";
            $list .= "</tbody>";
            $list .= "<tbody class=\"align-middle\" id=\"{$container}-p{$unit}\" style=\"display:none;font-size:0.9rem\">";
            $list .=    $subMenu;
            $list .= "</tbody>";
        }

        $html .= "<table class=\"table table-hover caption-top table-condensed\" >";
        $html .= "<caption>Listában szereplő páciensek (" . count($data) . " db)<br>";
        if($year){
            $html .= "Elérhető beutaló fájlok az időszakra (" . $existsCurrentFiles . " db)<br>";
        }
        $html .= "Adatok importálva: {$data[0]["created_at"]}<br>";
        if(!empty($missingPositions)){
            $html .= "<button type=\"button\" id=\"{$container}-widePopover\" class=\"btn btn btn-danger\"";
            $html .= "        data-bs-toggle=\"popover\"";
            $html .= "        data-bs-html=\"true\"";
            $html .= "        data-bs-title=\"Hiányzó munkakörök\"";
            $html .= "        data-bs-content=\"".implode("<br>",$missingPositions)."\">Hiányzó munkakörök";
            $html .= "</button>";
        }
        
        /*foreach ($details as $event => $db) {
            $html .= "<br>" . $event . ": " . $db . " db";
        }*/
        $html .= "</caption>";
        $html .= "<thead class=\"text-center\">";
        $html .= "  <tr>";
        $html .= "    <th scope= \"col\">#</th>";
        if($year){
             $html .="<th scope= \"col\">Fájl</th>";
        }
        $html .= "    <th scope=\"col\">Dolgozó neve</th>";
        $html .= "    <th scope=\"col\">TAJ sz.</th>";
        $html .= "    <th scope=\"col\" style=\"white-space:nowrap\">Sz.idö</th>";
        $html .= "    <th scope=\"col\">Lakcím</th>";
        $html .= "    <th scope=\"col\">Pozició neve</th>";
        $html .= "    <th scope=\"col\">Törzssz.</th>";
        $html .= "    <th scope=\"col\">Kh.kód</th>";
        $html .= "    <th scope=\"col\">Kh.megnevezés</th>";
        $html .= "    <th scope=\"col\">Szervezet neve</th>";
        $html .= "    <th scope=\"col\">FEÜ ellátóhely</th>";
        $html .= "    <th scope=\"col\">Megjegyzés</th>";
        $html .= "    <th scope=\"col\">Érv.</th>";
        $html .= "    <th scope=\"col\">Visszarend.</th>";
        $html .= "    <th scope=\"col\">Szerv. egység vezető</th>";
        $html .= "    <th scope=\"col\">E-MAIL címe</th>";
        $html .= "  </tr>";

        $html .= "</thead>";
        $html .= $list;
        $html .= "</table>";

        $html .= "<script type=\"text/javascript\">";
        $html .= "document.addEventListener('DOMContentLoaded', () => {";
        $html .= "    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle=\"popover\"]');";
        $html .= "    [...popoverTriggerList].forEach(el => new bootstrap.Popover(el));";
        $html .= "    new bootstrap.Popover(document.getElementById('{$container}-widePopover'), {";
        $html .= "        html: true,";
        $html .= "        content: '".implode("<br>",$missingPositions)."',";
        $html .= "        customClass: 'popover-wide'";
        $html .= "    });";
        $html .= "});";
        $html .= "</script>";

        return $html;
    }

    private function excelDateToMysqlDate($cellValue): ?string{
        if ($cellValue === null || $cellValue === '') {
            return null;
        }

        // 1) Ha ez egy Excel dátumsorszám (pl. 27400), akkor így kell konvertálni
        if (is_numeric($cellValue)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float)$cellValue);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        // 2) Ha szöveg (pl. "5/19/1975"), próbáljuk parsolni
        $str = trim((string)$cellValue);

        // első kör: az általad látott m/d/Y forma
        $dt = \DateTime::createFromFormat('n/j/Y', $str) ?: \DateTime::createFromFormat('m/d/Y', $str);
        if ($dt instanceof \DateTime) {
            return $dt->format('Y-m-d');
        }

        // 3) Utolsó esély: "okos" parse (ha pl. "1975-05-19", "19.05.1975" stb.)
        try {
            $dt = new \DateTime($str);
            return $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function showNotificationEditors(){
        $html = "";
        $html.="<div class=\"container\" style=\"max-width:1140px;margin-left:0;margin-right:auto\">";
        $html.="    <form method=\"POST\" id=\"doctors_mail_sample\">";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col\">";
        $html.="                <h5 class=\"card-title mt-2\">Orvosok levél sablonja</h5>";
        $html.="            </div>";
        $html.="        </div>";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col-sm\">";
        $html.="               <select class=\"form-select form-select-sm my-2\" id=\"doctors_mail_sample_log_selector\" aria-label=\"\">";
        $html.="                   <option value=\"0\" selected>Jelenlegi sablon</option>";
        if($doctorMailSampleLogs=sql_query("SELECT * FROM fgsz_level_sablonok_log WHERE cegid=? AND type=? ORDER BY og_ver_created_at DESC",[220,"doctor"])->fetchAll(PDO::FETCH_ASSOC)){
            foreach($doctorMailSampleLogs as $mailLog){
                $html.="           <option value=\"{$mailLog["id"]}\">{$mailLog["og_ver_created_at"]}</option>";
            }
        }
        $html.="               </select>";
        $html.="            </div>";
        $html.="            <div class=\"col-sm\">";
        $html.="                <button type=\"button\" onClick='loadFgszMailSample(\"doctor\",\"doctors_mail_content\",$(\"#doctors_mail_subject\").val(),220,\"#doctors_mail_sample_log_selector\")' class=\"btn btn-warning my-1\"><i class=\"fa-solid fa-arrow-rotate-right\"></i>&nbsp;Betöltés</button>";
        $html.="                <button type=\"button\" onClick='saveFgszMailSample(\"doctor\",\"doctors_mail_content\",$(\"#doctors_mail_subject\").val(),220,\"#doctors_mail_sample_log_selector\")' class=\"btn btn-success my-1\"><i class=\"fa-regular fa-floppy-disk\"></i>&nbsp;Mentés</button>";
        $html.="            </div>";
        $html.="        </div>";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col\">";
        $html.="                <input class=\"form-control my-1\" type=\"text\" id=\"doctors_mail_subject\" placeholder=\"Üzenet tárgya\" aria-label=\"Tárgy\" ";
        if($doctorMailSample=sql_query("SELECT * FROM fgsz_level_sablonok WHERE cegid=? AND type=?",[220,"doctor"])->fetch(PDO::FETCH_ASSOC)){
            $html.="                 value=\"{$doctorMailSample["subject"]}\"";
        }
        $html.="                >";
        $html.="            </div>";
        $html.="        </div>";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col\">";
        $html.="                <textarea class='mce' name='doctors_mail_content' id='doctors_mail_content' style='max-height:600px;'>";
        if($doctorMailSample=sql_query("SELECT * FROM fgsz_level_sablonok WHERE cegid=? AND type=?",[220,"doctor"])->fetch(PDO::FETCH_ASSOC)){
            $html.=                 $doctorMailSample["content"];
        }
        $html.="                </textarea>";
        $html.="            </div>";
        $html.="        </div>";
        $html.="    </form>";

        $html.="    <form method=\"POST\" id=\"leaders_mail_sample\">";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col\">";
        $html.="                <h5 class=\"card-title mt-2\">Szervezeti egységvezetők levél sablonja</h5>";
        $html.="            </div>";
        $html.="        </div>";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col-sm\">";
        $html.="               <select class=\"form-select form-select-sm my-2\" id=\"leaders_mail_sample_log_selector\" aria-label=\"\">";
        $html.="                   <option value=\"0\" selected>Jelenlegi sablon</option>";
        if($leaderMailSampleLogs=sql_query("SELECT * FROM fgsz_level_sablonok_log WHERE cegid=? AND type=? ORDER BY og_ver_created_at DESC",[220,"leader"])->fetchAll(PDO::FETCH_ASSOC)){
            foreach($leaderMailSampleLogs as $mailLog){
                $html.="           <option value=\"{$mailLog["id"]}\">{$mailLog["og_ver_created_at"]}</option>";
            }
        }
        $html.="               </select>";
        $html.="            </div>";
        $html.="            <div class=\"col-sm\">";
        $html.="                <button type=\"button\" onClick='loadFgszMailSample(\"leader\",\"leaders_mail_content\",$(\"#leaders_mail_subject\").val(),220,\"#leaders_mail_sample_log_selector\")' class=\"btn btn-warning my-1\"><i class=\"fa-solid fa-arrow-rotate-right\"></i>&nbsp;Betöltés</button>";
        $html.="                <button type=\"button\" onClick='saveFgszMailSample(\"leader\",\"leaders_mail_content\",$(\"#leaders_mail_subject\").val(),220,\"#leaders_mail_sample_log_selector\")' class=\"btn btn-success my-1\"><i class=\"fa-regular fa-floppy-disk\"></i>&nbsp;Mentés</button>";
        $html.="            </div>";
        $html.="        </div>";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col\">";
        $html.="                <input class=\"form-control my-1\" type=\"text\" id=\"leaders_mail_subject\" placeholder=\"Üzenet tárgya\" aria-label=\"Tárgy\" ";
        if($leaderMailSample=sql_query("SELECT * FROM fgsz_level_sablonok WHERE cegid=? AND type=?",[220,"leader"])->fetch(PDO::FETCH_ASSOC)){
            $html.="                 value=\"{$leaderMailSample["subject"]}\"";
        }
        $html.="                >";
        $html.="            </div>";
        $html.="        </div>";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col\">";
        $html.="                <textarea class='mce' name='leaders_mail_content' id='leaders_mail_content' style='max-height:600px;'>";
        if($leaderMailSample=sql_query("SELECT * FROM fgsz_level_sablonok WHERE cegid=? AND type=?",[220,"leader"])->fetch(PDO::FETCH_ASSOC)){
            $html.=                 $leaderMailSample["content"];
        }
        $html.="                </textarea>";
        $html.="            </div>";
        $html.="        </div>";
        $html.="    </form>";

        $html.="    <form method=\"POST\" id=\"workers_mail_sample\">";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col\">";
        $html.="                <h5 class=\"card-title mt-2\">Dolgozók levél sablonja</h5>";
        $html.="            </div>";
        $html.="        </div>";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col-sm\">";
        $html.="               <select class=\"form-select form-select-sm my-2\" id=\"workers_mail_sample_log_selector\" aria-label=\"\">";
        $html.="                   <option value=\"0\" selected>Jelenlegi sablon</option>";
        if($doctorMailSampleLogs=sql_query("SELECT * FROM fgsz_level_sablonok_log WHERE cegid=? AND type=? ORDER BY og_ver_created_at DESC",[220,"worker"])->fetchAll(PDO::FETCH_ASSOC)){
            foreach($doctorMailSampleLogs as $mailLog){
                $html.="           <option value=\"{$mailLog["id"]}\">{$mailLog["og_ver_created_at"]}</option>";
            }
        }
        $html.="               </select>";
        $html.="            </div>";
        $html.="            <div class=\"col-sm\">";
        $html.="                <button type=\"button\" onClick='loadFgszMailSample(\"worker\",\"workers_mail_content\",$(\"#workers_mail_subject\").val(),220,\"#workers_mail_sample_log_selector\")' class=\"btn btn-warning my-1\"><i class=\"fa-solid fa-arrow-rotate-right\"></i>&nbsp;Betöltés</button>";
        $html.="                <button type=\"button\" onClick='saveFgszMailSample(\"worker\",\"workers_mail_content\",$(\"#workers_mail_subject\").val(),220,\"#workers_mail_sample_log_selector\")' class=\"btn btn-success my-1\"><i class=\"fa-regular fa-floppy-disk\"></i>&nbsp;Mentés</button>";
        $html.="            </div>";
        $html.="        </div>";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col\">";
        $html.="                <input class=\"form-control my-1\" type=\"text\" id=\"workers_mail_subject\" placeholder=\"Üzenet tárgya\" aria-label=\"Tárgy\" ";
        if($workerMailSample=sql_query("SELECT * FROM fgsz_level_sablonok WHERE cegid=? AND type=?",[220,"worker"])->fetch(PDO::FETCH_ASSOC)){
            $html.="                 value=\"{$workerMailSample["subject"]}\"";
        }
        $html.="                >";
        $html.="            </div>";
        $html.="        </div>";
        $html.="        <div class=\"row\">";
        $html.="            <div class=\"col\">";
        $html.="                <textarea class='mce' name='workers_mail_content' id='workers_mail_content' style='max-height:600px;'>";
        if($workerMailSample=sql_query("SELECT * FROM fgsz_level_sablonok WHERE cegid=? AND type=?",[220,"worker"])->fetch(PDO::FETCH_ASSOC)){
            $html.=                 $workerMailSample["content"];
        }
        $html.="                </textarea>";
        $html.="            </div>";
        $html.="        </div>";
        $html.="    </form>";

        $html.="</div>";
        
        return $html;
    }

    private function pdfChars($text) {
        return str_replace(["ő","ű","í","Ő","Ű","Í"], ["ö","ü","i","Ö","Ü","I"], $text);
    }

    public function createReferalPDF($data){

        //Ha létzeik már a dolgozónak fájlja, megvizsgálom, hogy az adott évre lett-e már neki kiállítva vagy sem.
        $files = sql_query("SELECT * FROM dokumentumok WHERE customid=?",[$data["taj"]])->fetchAll(PDO::FETCH_ASSOC);
        foreach($files as $file){
            //A fájl megnev értékéből tudom, hogy melyik évre lett kiállítva a beutaló.
            if($file["megnev"]=="Beutaló ".date("Y",strtotime($data["next_exam_date"]))){
                return;
            }
        }

        $pdf = new Pdf(self::ASSET_REFERAL_SAMPLE_PDF);
        
        $auth_id = $this->utils->generateRandomStringv2(32);
        $filename = "{$data["name"]}-{$data["taj"]}-{$data["birth_date"]}-Beutaló-(" . $auth_id . ").pdf";
     
        $input = [
        "nev" =>                $this->pdfChars($data["name"]),
        "taj" =>                $data["taj"],
        "szuldatum" =>          date("Y.m.d", strtotime("birth_date")),
        "munkakor" =>          $this->pdfChars($data["position_name"]),
        "vizsgalat"=>           $this->pdfChars("Időszakos- Foglalkozás Egészségügyi vizsgálat"),
        "kelte" =>              date("Y.m.d"),
        "keltezes" =>           date("Y.m.d"),
        "teljescim" =>          $this->pdfChars($data["address"]),
        "auth_id" =>            $auth_id,
        "beutalo_megjegyzes" => $this->pdfChars($data["comment"]),
        "szervezet" =>          $this->pdfChars($data["organization_name"]),
        "torzsszam" =>          $this->pdfChars($data["rn_number"]),
        "khkod" =>              $this->pdfChars($data["kh_name"]."-".$data["kh_code"]),
        "indoklas"=>            $this->pdfChars("Elözö vizsgálat érvényessége hamarosan lejár")
        ];

        if($this->checkPositonExistInRiskFactorsTable($data["position_name"],220)){
            $input = $this->set_referal_values($data,$input);
            
            //$tmp = "/var/www/marci/onlinebejelentkezes/library/other/tmp/tmp.pdf";
            //$url = 'file://' . $tmp; // $tmp legyen abszolút path!
            $file = "/var/www/marci/onlinebejelentkezes/library/other/tmp/" . $filename;
            //$fontPath = "/usr/share/fonts:/usr/local/share/fonts";

            $result = $pdf->fillForm($input)
                ->flatten()
                //->saveAs($final);
                ->saveAs("/var/www/marci/onlinebejelentkezes/library/other/tmp/" . $filename);

            if ($result === false) {
                $error = $pdf->getError();
                var_dump($error);
            }

            //fel kell vigyek egy adatsort a dokumentumok táblába és annak a segítségével pedig átmozgatni a megfelelő doc könyvtárba.
            //A customid-t összerakom a páiens tajszámából és a vizsgálat évéből... az évet a next_exam_date-ből kitudom ezt nyerni.
            //taj+év pl 0123456782026 nem bonyolitom túl, jó az  ebben a formában.
            $docAgent = new DocAgent();
            sql_query("INSERT INTO dokumentumok SET assetid=?,customid=?,megnev=?,filename=?,size=?,tipus=?,datum=NOW(),kod=SHA1(MD5(CONCAT(NOW(),RAND()*20000)))",
                [docAgent::ASSET_PERSONA_REFERAL_PDF,$data["taj"],"Beutaló ".date("Y",strtotime($data["next_exam_date"])),$filename,filesize($file),"pdf"]
            );
            $fileId = sql_insert_id(); 
            $destinationFile = $docAgent->_getDocPath($fileId);
            rename($file,$destinationFile);
        }
        return;
    }

    public function set_referal_values($data,$input){
        $q=sql_query("SELECT * FROM kockazati_tenyezok WHERE munkakor=? AND cegid=?",array($data["position_name"],220))->fetch(PDO::FETCH_ASSOC);
        foreach($q as $key=>$value){
            if($key=="munkakor") continue;
            $input[$key]=$value;
        }
        
        return $input;
    }

    public function checkPositonExistInRiskFactorsTable($position,$cegid){
        if(sql_query("SELECT * FROM kockazati_tenyezok WHERE munkakor=? AND cegid=?",array($position,$cegid))->fetch(PDO::FETCH_ASSOC)){
            return true;
        }
        return false;
    }
    
    public function showReferalsManagement(){
        $months = [
            1=>"Január",
            2=>"Február",
            3=>"Március",
            4=>"Április",
            5=>"Május",
            6=>"Június",
            7=>"Július",
            8=>"Augusztus",
            9=>"Szeptember",
            10=>"Október",
            11=>"November",
            12=>"December",
        ];

        $html = "";
        $html.="<div class=\"container-fluid\">";
        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col\">";
        $html.="            <h5 class=\"card-title mt-2\">Válassz hónapot a dolgozók kilistázásához!</h5>";
        $html.="        </div>";
        $html.="    </div>";
        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col-sm\">";
        $html.="            <select class=\"form-select\" id=\"month-selector\" aria-label=\"\">";
        $html.="                <option selected>Válassz hónapot</option>";
                                for($i=1;$i<=12;$i++){
                                    $month = ($i<10?"0".$i:$i);
                                    $html.="<option value=\"".date("Y",strtotime("now"))."-{$month}\">{$months[$i]}</option>";
                                }
        $html.="            </select>";
        $html.="        </div>";
        $html.="        <div class=\"col-sm\">";
        $html.="            <button type=\"button\" onClick='filterWorkforceList($(\"#month-selector\").val(),220)' class=\"btn btn-secondary\">Kiválasztás</button>";
        $html.="            <button type=\"button\" onClick='generateReferalPdfByFilterSelector($(\"#month-selector\").val(),220)' class=\"btn btn-warning\">Beutalók generálása</button>";
        $html.="        </div>";
        $html.="    </div>";
        $html.="    <div class=\"row\" id=\"actual-referral-list-container\">";
        $html.="    </div>";
        $html.="</div>";

        return $html;
    }

    public function showReferalArrayManagement(){
        $html = "";

        $months = [
            1=>"Január",
            2=>"Február",
            3=>"Március",
            4=>"Április",
            5=>"Május",
            6=>"Június",
            7=>"Július",
            8=>"Augusztus",
            9=>"Szeptember",
            10=>"Október",
            11=>"November",
            12=>"December",
        ];

        $html.="<div class=\"container-fluid\">";
        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col\">";
        $html.="            <h5 class=\"card-title mt-2\">Válassz hónapot az orvosok és a felettesek kiválasztásához kiválasztásához!</h5>";
        $html.="        </div>";
        $html.="    </div>";
        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col-sm\">";
        $html.="            <select class=\"form-select\" id=\"referral-array-month-selector\" aria-label=\"\">";
        $html.="                <option selected>Válassz hónapot</option>";
                                for($i=1;$i<=12;$i++){
                                    $month = ($i<10?"0".$i:$i);
                                    $html.="<option value=\"".date("Y",strtotime("now"))."-{$month}\">{$months[$i]}</option>";
                                }
        $html.="            </select>";
        $html.="        </div>";
        $html.="        <div class=\"col-sm\">";
        $html.="            <button type=\"button\" onClick='filterDoctorsAndLeadersList($(\"#referral-array-month-selector\").val(),220)' class=\"btn btn-secondary\">Kiválasztás</button>";
        $html.="            <button type=\"button\" style=\"display:none\" id=\"generateReferralPdfArraysButton\" onClick='generateReferralPdfArrays($(\"#referral-array-month-selector\").val(),220)' class=\"btn btn-warning\">Tömbök generálása</button>";
        $html.="        </div>";
        $html.="    </div>";
        $html.="    <div class=\"row\" id=\"actual-referral-arrays-container\">";
        //$html.=         $this->generateListForReferralArrays();
        $html.="    </div>";
        $html.="</div>";

        return $html;
    }

    public function generateListForReferralArrays($statement,$year){
        $html = "";

        $html.= $this->showDoctorListForReferralArray($statement,$year);
        $html.= "<br><br>";
        $html.= $this->showLeaderListForReferralArray($statement,$year);

        return $html;
    }

    public function showDoctorListForReferralArray($statement,$yearMonth){
        $html = $list = $subMenu = "";
        $unit = 0;
        $locations = $doctors = [];
        $docAgent = new DocAgent();

        $data = sql_query("SELECT * FROM temp_fgsz_workforce {$statement} GROUP BY service_location")->fetchAll(PDO::FETCH_ASSOC);

        foreach($data as $each){
            $unit++;
            $participantUnit = $missingFiles = 0;
            $subMenu = "";
            $explode = explode(" - ",$each["service_location"]);
            $location = $explode[0];
            $doctor = $explode[1];

            $email = "";
            if($doctorDetails = sql_query("SELECT * FROM orvosok WHERE nev=?",[$doctor])->fetch(PDO::FETCH_ASSOC)){
                $email = $doctorDetails["email"];
            }

            $participantsDetails = sql_query("SELECT * FROM temp_fgsz_workforce {$statement} AND service_location = ?",[$each["service_location"]])->fetchAll(PDO::FETCH_ASSOC);

            foreach($participantsDetails as $participant){
                $participantUnit++;
                
                $existsCurrentFile=0;
                $files = sql_query("SELECT * FROM dokumentumok WHERE customid=?",[$participant["taj"]])->fetchAll(PDO::FETCH_ASSOC);

                foreach($files as $file){
                    
                    if($file["megnev"]=="Beutaló ".date("Y",strtotime($participant["next_exam_date"]))){
                        $existsCurrentFile++;
                    }
                }

                $subMenu .= "<tr>";
                $subMenu .= "    <td></td>";
                $subMenu .= "    <td class=\"text-center\">{$participantUnit}.</td>";
                $subMenu .= "    <td>{$participant["name"]}</td>";
                $subMenu .= "    <td>{$participant["taj"]}</td>";
                $subMenu .= "    <td style=\"white-space:nowrap\">{$participant["birth_date"]}</td>";
                $subMenu .= "    <td colspan=\"2\" style=\"white-space:nowrap\">{$participant["next_exam_date"]}</td>";
                if($existsCurrentFile>0){
                    $statusIcon="<i style=\"color:#008000;font-size: 18px;\" class=\"fa-solid fa-file-circle-check\"></i>";
                }else{
                    $statusIcon="<i style=\"color:red;font-size: 18px;\" class=\"fa-solid fa-file-circle-xmark\"></i>";
                    $missingFiles++;
                }
                $subMenu .= "    <td class=\"text-center\">{$statusIcon}</td>";  

                $subMenu .= "</tr>";
            }
            if(strpos($location," ")!==false){
                $explode = explode(" ",$location);
                $location = $explode[0];
            }
            $fileName = $this->removeAccentsFallback($location);
            if($ReferralArrayFile = sql_query("SELECT * FROM dokumentumok WHERE assetid=? AND customid=? AND megnev=?",
            [docAgent::ASSET_REFERRAL_ARRAY,$fileName,"Beutaló tömb ".$yearMonth])->fetch(PDO::FETCH_ASSOC)){
                $downloadLink = $this->docAgent->getDocURL($ReferralArrayFile,"bejelentkezes.hungariamed.hu");
                $referralArrayIcon="<a href=\"{$downloadLink}\" target=\"_blank\"><i style=\"color:#008000;font-size: 18px;\" class=\"fa-solid fa-file-zipper\"></i></a>";
            }else{
                $referralArrayIcon="<i style=\"color:red;font-size: 18px;\" class=\"fa-solid fa-file-zipper\"></i>";
            }

            $list .= "<tbody class=\"align-middle\" style=\"border-top:none\">";
            $list .= "<tr data-toggle=\"collapse\" onClick=\"$('#doctor-array-p{$unit}').fadeToggle(500)\">";
            $list .= "    <td class=\"text-center\">{$unit}.</td>";
            $list .= "    <td colspan=\"2\">{$doctor}</td>";
            $list .= "    <td colspan=\"2\">{$location}</td>";
            $list .= "    <td>{$email}</td>";
            $list .= "    <td ".($missingFiles>0?"colspan=\"1\"":"colspan=\"2\"").">{$participantUnit} fő</td>";
            if($missingFiles>0){
                $list .= "<td title=\"hiányzó fájlok\">{$missingFiles}db&nbsp;&nbsp;<i style=\"color:#DAA520;font-size: 18px;\" class=\"fa-solid fa-file-circle-exclamation\"></i></td>";
            }
            $list .= "    <td title=\"Beutaló tömb letöltése\">{$referralArrayIcon}</td>";
            $list .= "</tr>";
            $list .= "</tbody>";
            $list .= "<tbody class=\"align-middle\" id=\"doctor-array-p{$unit}\" style=\"display:none;font-size:0.9rem\">";
            $list .=    $subMenu;
            $list .= "</tbody>";
        }

        $html.="<div class=\"container\" style=\"max-width:1140px;margin-left:0;margin-right:auto\">";
        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col\">";
        $html .= "          <table class=\"table table-hover caption-top table-condensed\" >";
        $html .= "          <thead class=\"\">";
        $html .= "            <tr>";
        $html .= "              <th class=\"text-center\" scope= \"col\">#</th>";
        $html .= "              <th colspan=\"2\" scope=\"col\">Orvos neve</th>";
        $html .= "              <th colspan=\"2\" scope=\"col\">Rendelő cím</th>";
        $html .= "              <th scope=\"col\">E-mail cím</th>";
        $html .= "              <th colspan=\"3\" scope=\"col\">Érintett dolgozók</th>";
        $html .= "            </tr>";

        $html .= "          </thead>";
        $html .=                $list;
        $html .= "          </table>";
        $html.="        </div>";
        $html.="    </div>";

        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col\">";

        $html.="        </div>";
        $html.="    </div>";
        $html.="</div>";

        return $html;
    }

    public function showLeaderListForReferralArray($statement,$yearMonth){
        $html = $list = $subMenu = "";
        $unit = 0;
        $docAgent = new DocAgent();

        $data = sql_query("SELECT * FROM temp_fgsz_workforce {$statement} GROUP BY superior_email")->fetchAll(PDO::FETCH_ASSOC);

        foreach($data as $each){
            $unit++;
            $participantUnit = $missingFiles = 0;
            $subMenu = "";

            $participantsDetails = sql_query("SELECT * FROM temp_fgsz_workforce {$statement} AND superior_email = ?",[$each["superior_email"]])->fetchAll(PDO::FETCH_ASSOC);

            foreach($participantsDetails as $participant){
                $participantUnit++;
                $existsCurrentFile=0;
                $files = sql_query("SELECT * FROM dokumentumok WHERE customid=?",[$participant["taj"]])->fetchAll(PDO::FETCH_ASSOC);

                foreach($files as $file){
                    
                    if($file["megnev"]=="Beutaló ".date("Y",strtotime($participant["next_exam_date"]))){
                        $existsCurrentFile++;
                    }
                }

                $subMenu .= "<tr>";
                $subMenu .= "    <td></td>";
                $subMenu .= "    <td class=\"text-center\">{$participantUnit}.</td>";
                $subMenu .= "    <td>{$participant["name"]}</td>";
                $subMenu .= "    <td>{$participant["taj"]}</td>";
                $subMenu .= "    <td style=\"white-space:nowrap\">{$participant["birth_date"]}</td>";
                $subMenu .= "    <td colspan=\"2\" style=\"white-space:nowrap\">{$participant["next_exam_date"]}</td>";
                if($existsCurrentFile>0){
                    $statusIcon="<i style=\"color:#008000;font-size: 18px;\" class=\"fa-solid fa-file-circle-check\"></i>";
                }else{
                    $statusIcon="<i style=\"color:red;font-size: 18px;\" class=\"fa-solid fa-file-circle-xmark\"></i>";
                    $missingFiles++;
                }
                $subMenu .= "    <td class=\"text-center\">{$statusIcon}</td>";  

                $subMenu .= "</tr>";
            }
            $fileName = $this->removeAccentsFallback($each["superior_email"]);
            if($ReferralArrayFile = sql_query("SELECT * FROM dokumentumok WHERE assetid=? AND customid=? AND megnev=?",
            [docAgent::ASSET_REFERRAL_ARRAY,$fileName,"Beutaló tömb ".$yearMonth])->fetch(PDO::FETCH_ASSOC)){
                $downloadLink = $this->docAgent->getDocURL($ReferralArrayFile,"bejelentkezes.hungariamed.hu");
                $referralArrayIcon="<a href=\"{$downloadLink}\" target=\"_blank\"><i style=\"color:#008000;font-size: 18px;\" class=\"fa-solid fa-file-zipper\"></i></a>";
            }else{
                $referralArrayIcon="<i style=\"color:red;font-size: 18px;\" class=\"fa-solid fa-file-zipper\"></i>";
            }

            $list .= "<tbody class=\"align-middle\" style=\"border-top:none\">";
            $list .= "<tr data-toggle=\"collapse\" onClick=\"$('#leader-array-p{$unit}').fadeToggle(500)\">";
            $list .= "    <td class=\"text-center\">{$unit}.</td>";
            $list .= "    <td colspan=\"2\">{$each["superior_name"]}</td>";
            $list .= "    <td colspan=\"2\">{$each["superior_email"]}</td>";
            $list .= "    <td ".($missingFiles>0?"colspan=\"2\"":"colspan=\"3\"").">{$participantUnit} fő</td>";
            if($missingFiles>0){
                $list .= "<td title=\"hiányzó fájlok\">{$missingFiles}db&nbsp;&nbsp;<i style=\"color:#DAA520;font-size: 18px;\" class=\"fa-solid fa-file-circle-exclamation\"></i></td>";
            }
            $list .= "    <td title=\"Beutaló tömb letöltése\">{$referralArrayIcon}</td>";
            $list .= "</tr>";
            $list .= "</tbody>";
            $list .= "<tbody class=\"align-middle\" id=\"leader-array-p{$unit}\" style=\"display:none;font-size:0.9rem\">";
            $list .=    $subMenu;
            $list .= "</tbody>";
        }

        $html.="<div class=\"container\" style=\"max-width:1140px;margin-left:0;margin-right:auto\">";
        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col\">";
        $html .= "          <table class=\"table table-hover caption-top table-condensed\" >";
        $html .= "          <thead class=\"\">";
        $html .= "            <tr>";
        $html .= "              <th class=\"text-center\" scope= \"col\">#</th>";
        $html .= "              <th colspan=\"2\" scope=\"col\">Felettes neve</th>";
        $html .= "              <th colspan=\"2\"scope=\"col\">E-mail cím</th>";
        $html .= "              <th colspan=\"3\" scope=\"col\">Érintett dolgozók</th>";
        $html .= "            </tr>";

        $html .= "          </thead>";
        $html .=                $list;
        $html .= "          </table>";
        $html.="        </div>";
        $html.="    </div>";

        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col\">";

        $html.="        </div>";
        $html.="    </div>";
        $html.="</div>";

        return $html;
    }

    public function createReferralPdfArrays($data,$group){
        $files = [];
        $tmpName = "export_".date("Ymd_His").".7z";
        $zipPath =self::TEMPORAL_LIBRARY_PATH.$tmpName;
        $password = 'FGSZ2026';
        $yearMonth = date("Y-m",strtotime($data[0]["next_exam_date"]));

        $explode = explode(" - ",$data[0][$group]);
        if (strpos($explode[0], " ") !== false) {
            $explode = explode(" ",$explode[0]);
        }
        $zipName = $this->removeAccentsFallback($explode[0]);

        if($referralArrayExists = sql_query("SELECT * FROM dokumentumok WHERE assetid=? AND customid=? AND megnev=?",
            [docAgent::ASSET_REFERRAL_ARRAY,$zipName,"Beutaló tömb ".$yearMonth])->fetch(PDO::FETCH_ASSOC)){
            return;
        }


        $escapedZip = escapeshellarg($zipPath);
        $escapedPwd = escapeshellarg($password);

        $docAgent = new DocAgent();
        foreach($data as $each){
            $searchName = "Beutaló ".date("Y",strtotime($each["next_exam_date"]));
            $file = sql_query("SELECT * FROM dokumentumok WHERE customid=? AND megnev=?",[$each["taj"],$searchName])->fetch(PDO::FETCH_ASSOC);
            if(!empty($file)){
                $destinationFile = self::TEMPORAL_LIBRARY_PATH.$file["filename"];
                $originalFile = $docAgent->getDocByCustomId(DocAgent::ASSET_PERSONA_REFERAL_PDF, $file["id"],$each["taj"]);
                file_put_contents($destinationFile,$originalFile);
                $files[] = $destinationFile;
            }
        }

        // 1. fájllista létrehozása
        $listFile = $zipPath . '.list';
        file_put_contents($listFile, implode("\n", $files) . "\n");

        // 2. parancs
        $escapedList = escapeshellarg($listFile);
        $cmd = "LC_ALL=C.UTF-8 LANG=C.UTF-8 7z a -y -t7z $escapedZip @$escapedList -p$escapedPwd -mhe=on";

        // 3. futtatás
        exec($cmd . ' 2>&1', $out, $code);

        // 4. takarítás
        unlink($listFile);

        foreach($files as $file){
            unlink($file);
        }

        // 5. hibakezelés
        if ($code >= 2) {
            throw new RuntimeException(
                "7z hiba. Kód: $code\n" . implode("\n", $out)
            );
        }
        
        
        $newZipPath =self::TEMPORAL_LIBRARY_PATH.$zipName.".7z";
        rename($zipPath,$newZipPath);

        sql_query("INSERT INTO dokumentumok SET assetid=?,customid=?,megnev=?,filename=?,size=?,tipus=?,datum=NOW(),kod=SHA1(MD5(CONCAT(NOW(),RAND()*20000)))",
            [docAgent::ASSET_REFERRAL_ARRAY,$zipName,"Beutaló tömb ".$yearMonth,$zipName.".7z",filesize($newZipPath),"7z"]
        );

        $fileId = sql_insert_id(); 
        $destinationFile = $docAgent->_getDocPath($fileId);
        rename($newZipPath,$destinationFile);
    }

    public function removeAccentsFallback(string $text): string{
        $map = [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ö'=>'O','Ő'=>'O','Ú'=>'U','Ü'=>'U','Ű'=>'U',' '=>'',
        ];
        return strtr($text, $map);
    }

    public function showSendingNotificationsManagement(){
        $html = "";

        $months = [
            1=>"Január",
            2=>"Február",
            3=>"Március",
            4=>"Április",
            5=>"Május",
            6=>"Június",
            7=>"Július",
            8=>"Augusztus",
            9=>"Szeptember",
            10=>"Október",
            11=>"November",
            12=>"December",
        ];

        $html.="<div class=\"container-fluid\">";
        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col\">";
        $html.="            <h5 class=\"card-title mt-2\">Válassz hónapot az értesitések kigyűjtéséhez!</h5>";
        $html.="        </div>";
        $html.="    </div>";
        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col-sm\">";
        $html.="            <select class=\"form-select\" id=\"sending-month-selector\" aria-label=\"\">";
        $html.="                <option selected>Válassz hónapot</option>";
                                for($i=1;$i<=12;$i++){
                                    $month = ($i<10?"0".$i:$i);
                                    $html.="<option value=\"".date("Y",strtotime("now"))."-{$month}\">{$months[$i]}</option>";
                                }
        $html.="            </select>";
        $html.="        </div>";
        $html.="        <div class=\"col-sm\">";
        $html.="            <button type=\"button\" onClick='filterNotificationList($(\"#sending-month-selector\").val(),220)' class=\"btn btn-secondary\">Kiválasztás</button>";
        $html.="            <button type=\"button\" style=\"display:none\" id=\"sendNotificationsButton\" onClick='sendNotifications($(\"#sending-month-selector\").val(),220)' class=\"btn btn-warning\">Értesitések kiküldése</button>";
        $html.="        </div>";
        $html.="    </div>";
        $html.="    <div class=\"row\" id=\"actual-sending-notifications-container\">";
        //$html.=         $this->generateListForReferralArrays();
        $html.="    </div>";

        $html.="    <div class=\"row\" id=\"notification-logs-container\">";
        //$html.=         $this->generateListForReferralArrays();
        $html.="    </div>";
        $html.="</div>";

        return $html;
    }

    public function generateListForNotificationEvent($statement,$yearMonth){
        $html = $list = "";
        $unit = 0;
        $doctorData = sql_query("SELECT * FROM temp_fgsz_workforce {$statement} GROUP BY service_location")->fetchAll(PDO::FETCH_ASSOC);
        $leaderData = sql_query("SELECT * FROM temp_fgsz_workforce {$statement} GROUP BY superior_email")->fetchAll(PDO::FETCH_ASSOC);

        foreach($doctorData as $doctor){
            $unit++;
            $participantsDetails = sql_query("SELECT * FROM temp_fgsz_workforce {$statement} AND service_location = ?",[$doctor["service_location"]])->fetchAll(PDO::FETCH_ASSOC);
            $explode = explode(" - ",$doctor["service_location"]);
            $location = $explode[0];
            $doctor = $explode[1];
            $email = "";
            if($doctorDetails = sql_query("SELECT * FROM orvosok WHERE nev=?",[$doctor])->fetch(PDO::FETCH_ASSOC)){
                $email = $doctorDetails["email"];
            }

            $notificationStatus="";
            if($notificationLog=sql_query("SELECT * FROM notifications WHERE tipus=? AND destination=?",
            ["referral-notification-{$yearMonth}",$email])->fetch(PDO::FETCH_ASSOC)){
                $notificationStatus = "<i style=\"color:#008000;font-size: 18px;\" class=\"fa-solid fa-circle-check\"></i>&nbsp;{$notificationLog["datum"]}";
            }

            $list .= "<tbody class=\"align-middle\" style=\"border-top:none\">";
            $list .= "<tr data-toggle=\"collapse\" onClick=\"$('#doctor-notification-p{$unit}').fadeToggle(500)\">";
            $list .= "    <td class=\"text-center\">{$unit}.</td>";
            $list .= "    <td colspan=\"2\">{$doctor}</td>";
            $list .= "    <td colspan=\"2\">{$email}</td>";
            $list .= "    <td colspan=\"2\">".count($participantsDetails)." fő</td>";
            $list .= "    <td>{$notificationStatus}</td>";
            /*if($missingFiles>0){
                $list .= "<td title=\"hiányzó fájlok\">{$missingFiles}db&nbsp;&nbsp;<i style=\"color:#DAA520;font-size: 18px;\" class=\"fa-solid fa-file-circle-exclamation\"></i></td>";
            }*/
            //$list .= "    <td title=\"Beutaló tömb letöltése\">{$referralArrayIcon}</td>";
            $list .= "</tr>";
            $list .= "</tbody>";
            /*$list .= "<tbody class=\"align-middle\" id=\"leader-array-p{$unit}\" style=\"display:none;font-size:0.9rem\">";
            $list .=    $subMenu;
            $list .= "</tbody>";*/
        }

        foreach($leaderData as $leader){
            $unit++;
            $participantsDetails = sql_query("SELECT * FROM temp_fgsz_workforce {$statement} AND superior_email = ?",[$leader["superior_email"]])->fetchAll(PDO::FETCH_ASSOC);

            $notificationStatus="";
            if($notificationLog=sql_query("SELECT * FROM notifications WHERE tipus=? AND destination=?",
            ["referral-notification-{$yearMonth}",$leader["superior_email"]])->fetch(PDO::FETCH_ASSOC)){
                $notificationStatus = "<i style=\"color:#008000;font-size: 18px;\" class=\"fa-solid fa-circle-check\"></i>&nbsp;{$notificationLog["datum"]}";
            }
            
            $list .= "<tbody class=\"align-middle\" style=\"border-top:none\">";
            $list .= "<tr data-toggle=\"collapse\" onClick=\"$('#leader-notification-p{$unit}').fadeToggle(500)\">";
            $list .= "    <td class=\"text-center\">{$unit}.</td>";
            $list .= "    <td colspan=\"2\">{$leader["superior_name"]}</td>";
            $list .= "    <td colspan=\"2\">{$leader["superior_email"]}</td>";
            $list .= "    <td colspan=\"2\">".count($participantsDetails)." fő</td>";
            $list .= "    <td>{$notificationStatus}</td>";
            /*if($missingFiles>0){
                $list .= "<td title=\"hiányzó fájlok\">{$missingFiles}db&nbsp;&nbsp;<i style=\"color:#DAA520;font-size: 18px;\" class=\"fa-solid fa-file-circle-exclamation\"></i></td>";
            }*/
            //$list .= "    <td title=\"Beutaló tömb letöltése\">{$referralArrayIcon}</td>";
            $list .= "</tr>";
            $list .= "</tbody>";
            /*$list .= "<tbody class=\"align-middle\" id=\"leader-array-p{$unit}\" style=\"display:none;font-size:0.9rem\">";
            $list .=    $subMenu;
            $list .= "</tbody>";*/
        }

        $html.="<div class=\"container\" style=\"max-width:1140px;margin-left:0;margin-right:auto\">";
        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col\">";
        $html .= "          <table class=\"table table-hover caption-top table-condensed\" >";
        $html .= "          <thead class=\"\">";
        $html .= "            <tr>";
        $html .= "              <th class=\"text-center\" scope= \"col\">#</th>";
        $html .= "              <th colspan=\"2\" scope=\"col\">Cimzett</th>";
        $html .= "              <th colspan=\"2\"scope=\"col\">E-mail cím</th>";
        $html .= "              <th colspan=\"2\" scope=\"col\">Érintett dolgozók</th>";
        $html .= "              <th colspan=\"2\" scope=\"col\">Log</th>";
        $html .= "            </tr>";

        $html .= "          </thead>";
        $html .=                $list;
        $html .= "          </table>";
        $html.="        </div>";
        $html.="    </div>";

        $html.="    <div class=\"row\">";
        $html.="        <div class=\"col\">";

        $html.="        </div>";
        $html.="    </div>";
        $html.="</div>";

        return $html;
    }

    public function sendNotifications($statement,$yearMonth){
        $html = "";
        $emailAddress = "tesztemail@hungariamed.hu";
        $notificationService = new NotificationService();
        $doctorData = sql_query("SELECT * FROM temp_fgsz_workforce {$statement} GROUP BY service_location")->fetchAll(PDO::FETCH_ASSOC);
        $leaderData = sql_query("SELECT * FROM temp_fgsz_workforce {$statement} GROUP BY superior_email")->fetchAll(PDO::FETCH_ASSOC);
        $files = [];
        foreach($doctorData as $doctor){
            $explode = explode(" - ",$doctor["service_location"]);
            $location = $explode[0];
            $doctorName = $explode[1];

            if(strpos($location," ")!==false){
                $explode = explode(" ",$location);
                $location = $explode[0];
            }
            
            $email = "";
            if($doctorDetails = sql_query("SELECT * FROM orvosok WHERE nev=?",[$doctorName])->fetch(PDO::FETCH_ASSOC)){
                $email = $doctorDetails["email"];

                $fileName = $this->removeAccentsFallback($location);
                if($ReferralArrayFile = sql_query("SELECT * FROM dokumentumok WHERE assetid=? AND customid=? AND megnev=?",
                [docAgent::ASSET_REFERRAL_ARRAY,$fileName,"Beutaló tömb ".$yearMonth])->fetch(PDO::FETCH_ASSOC)){
                    $path = $this->docAgent->_getDocPath($ReferralArrayFile["id"]);
                    $fileName = $ReferralArrayFile["filename"];
                    $files[] = [
                        "path"=>$path,
                        "filename"=>$fileName,
                        "group"=>"doctor",
                        "email"=>$email,
                        "name"=> $doctorDetails["nev"]
                    ];
                }
            }

            
        }

        foreach($leaderData as $leader){
            $fileName = $this->removeAccentsFallback($leader["superior_email"]);
            if($ReferralArrayFile = sql_query("SELECT * FROM dokumentumok WHERE assetid=? AND customid=? AND megnev=?",
            [docAgent::ASSET_REFERRAL_ARRAY,$fileName,"Beutaló tömb ".$yearMonth])->fetch(PDO::FETCH_ASSOC)){
                $path = $this->docAgent->_getDocPath($ReferralArrayFile["id"]);
                $fileName = $ReferralArrayFile["filename"];
                $files[] = [
                    "path"=>$path,
                    "filename"=>$fileName,
                    "group"=>"leader",
                    "email"=>$leader["superior_email"],
                    "name"=>$leader["superior_name"]
                ];
            }
        }

        //Megvan minden fájl és címzett. Meg kell hívnom a megfelelő sablont és fájlt minden címzetthez.
        //A group alapján tudom melyik sablon kell és a path/filename-ből pedig meg lesz a csatolmány is. Az e-mail adja a címzettet.
        $emailDetails = sql_query("SELECT * FROM fgsz_level_sablonok WHERE cegid=?",[220])->fetchAll(PDO::FETCH_ASSOC);

        foreach($files as $data){
            $messageDetail = [];
            $key = array_search($data["group"],array_column($emailDetails,"type"));
            if($key!==false){

                $search = ["#name#"];
                $replace = [$data["name"]];
                
                $messageDetail = $emailDetails[$key];
                $mail = $notificationService->getDefaultMailer();
                $mail->addAddress($emailAddress);
                $mail->Subject = $messageDetail["subject"];
                $mail->Body = str_replace($search,$replace,$messageDetail["content"]);
                $mail->AddAttachment($data["path"], $data["filename"]);
                $mail->Send();
                $notificationService->createNotificationRecord("referral-notification-{$yearMonth}", null, $data["email"], $mail->Subject, $mail->Body);
            }
        }
        return $html;
    }

}