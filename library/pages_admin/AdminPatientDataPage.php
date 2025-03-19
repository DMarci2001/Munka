<?php

class AdminPatientDataPage extends AdminCorePage
{

    private $data;
    private $currentAction;
    private $cols = [
        0  => ["name" => "Időpont", "id" => "datum"],
        1  => ["name" => "Ellátási idő", "id" => "rinterval"],
        2  => ["name" => "Teljesnév", "id" => "nev"],
        3  => ["name" => "E-mail", "id" => "email"],
        4  => ["name" => "Telefon", "id" => "telefon"],
        5  => ["name" => "Szül. dátum", "id" => "szuldatum"],
        6  => ["name" => "Szül. hely", "id" => "szulhely"],
        7  => ["name" => "Anyjaneve", "id" => "anyjaneve"],
        8  => ["name" => "Neme", "id" => "neme"],
        9  => ["name" => "TAJ", "id" => "taj"],
        10 => ["name" => "Irsz.", "id" => "irsz"],
        11 => ["name" => "Település", "id" => "varos"],
        12 => ["name" => "Cím", "id" => "utca"],
        13 => ["name" => "Teljes cím", "id" => "cim"],
        14 => ["name" => "Munkakör", "id" => "munkakor"],
        15 => ["name" => "Törzsszám", "id" => "torzsszam"],
        16 => ["name" => "Vizsgálat dátuma", "id" => "vizsgalatdatuma"],
        17 => ["name" => "Visszarendelés", "id" => "visszarendeles"],
        18 => ["name" => "Ellátás helyszíne", "id" => "ellatoorvos"],
        19 => ["name" => "Felettes neve", "id" => "felettesnev"],
        20 => ["name" => "Felettes email címe", "id" => "felettesemail"],
    ];

    private $actions = [
        0 => [
            "id" => "create-dm-recipient-list",
            "name" => "Címzett lista készítése DM-hez",
            "operations" => [1],
        ],
        1 => [
            "id" => "create-mass-ohr",
            "name" => "Tömeges beutalók kiállítása",
            "operations" => [1],
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        if (!empty($_SESSION["patient-excel-data"])) {
            $this->data = $_SESSION["patient-excel-data"];
        }

        if (!empty($_SESSION["currentAction"])) {
            $this->currentAction = $_SESSION["currentAction"];
        }

        if (isset($_GET["action"])) {
            $key = array_search($_GET["action"], array_column($this->actions, "id"));
            if ($key !== false) {
                if (empty($this->currentAction)) {
                    unset($_SESSION["patient-excel-data"]);
                    unset($_SESSION["patient-excel-cols"]);
                }

                $this->currentAction = $_SESSION["currentAction"] = $this->actions[$key];
                if (isset($_GET["dmid"])) {
                    $this->currentAction["dmid"] = $_SESSION["currentAction"]["dmid"] = $_GET["dmid"];
                }
                if (isset($_GET["id"])) {
                    $this->currentAction["id"] = $_SESSION["currentAction"]["id"] = $_GET["id"];
                }
            }
        }

        if (isset($_POST["uploadPatientDataFile"])) {
            $excelService = new ExcelService();
            $index = 0;
            $data = $excelService->loadPatientDataExcel($_FILES["excel"]["tmp_name"]);


            if ($multipleSheets = $excelService->checkSheets()) {
                if (!isset($_POST["spreadsheet"])) {
                    die(json_encode(array("multiplesheets" => $multipleSheets)));
                } else {
                    $index = $_POST["spreadsheet"];
                    $data = $excelService->loadPatientDataExcel($_FILES["excel"]["tmp_name"], $index);
                }
            }

            unset($data[0]); //Első sor törlése mert irreleváns :P
            $data = array_values($data); //Újra rendezés

            $_SESSION["patient-excel-data"] = $data;

            $viewer = $this->setExcelViewer($data);
            die(json_encode(array("html" => $viewer)));
        }

        if (isset($_GET["remove"])) {
            unset($_SESSION["patient-excel-data"]);
            unset($_SESSION["patient-excel-cols"]);
            header("location:index.php?page={$_GET["page"]}");
        }

        if (isset($_POST["setPatientDataCol"])) {
            $_SESSION["patient-excel-cols"][$_POST["index"]] = $_POST["col"];
            die();
        }

        if (isset($_POST["sortPatientDataRows"])) {
            echo count($_POST["data"]);
            $_SESSION["patient-excel-data"] = $this->data = $_POST["data"];
            $_SESSION["patient-excel-cols"] = $_POST["cols"];

            die("{}");
        }

        if (isset($_POST["createDMRecipientList"])) {
            foreach ($this->data as $key => $row) {
                if (!$data = sql_query("SELECT * FROM direkt_marketing_cimzettek WHERE email=?", [$row[array_search("email", $_SESSION["patient-excel-cols"])]])->fetch(PDO::FETCH_ASSOC)) {
                    sql_query(
                        "INSERT INTO direkt_marketing_cimzettek SET nev=?, email=?, created=?, created_by=?, subscribed=?",
                        [
                            $row[array_search("nev", $_SESSION["patient-excel-cols"])],
                            $row[array_search("email", $_SESSION["patient-excel-cols"])],
                            date("Y-m-d H:i:s"),
                            $_SESSION["adminuser"]["id"],
                            1
                        ]
                    );
                    $recipient_id = sql_insert_id();
                } else {
                    $recipient_id = $data["id"];
                }

                sql_query(
                    "INSERT INTO direkt_marketing_cimzettek_link_tabla SET recipient_id=?,dm_id=?,created=?,created_by=?",
                    [$recipient_id, $this->currentAction["dmid"], date("Y-m-d H:i:s"), $_SESSION["adminuser"]["id"]]
                );
            }
            die();
        }
        if (isset($_POST["createReferalList"])) {

            $bm = sql_query(
                "SELECT id,cegid, superior_email_send, doctor_email_send, worker_email_send,
                        superior_email_sender_address, superior_email_copy_address, superior_email_subject, 
                        superior_email_content, doctor_email_sender_address, doctor_email_copy_address, 
                        doctor_email_subject, doctor_email_content, worker_email_sender_address, 
                        worker_email_copy_address, worker_email_subject, worker_email_content 
                 FROM beutalo_kezeles WHERE id=?",
                [$_POST["bmid"]]
            )->fetch(PDO::FETCH_ASSOC);

            sql_query(
                "INSERT INTO beutalo_kezeles_sessions 
                       SET bmid=?, cegid=?, created=NOW(), created_by={$_SESSION["adminuser"]["id"]}, status='inprogress';",
                [$bm["id"],$bm["cegid"]]
            );
            $sessionId = sql_insert_id();

            foreach ($this->data as $key => $row) {
                $orvos = explode(" - ", $row[array_search("ellatoorvos", $_SESSION["patient-excel-cols"])]);
                $taj = $row[array_search("taj", $_SESSION["patient-excel-cols"])];
                if (strlen($taj) < 9) {
                    $taj = "0" . $taj;
                }

                sql_query(
                    "INSERT INTO beutalo_kezeles_data 
                     SET nev=?, bmid=?, sessionid=?, torzsszam=?, szuldatum=?, taj=?, munkakor=?, anyjaneve=?, szulhely=?, vizsgalatdatuma=?, 
                         visszarendeles=?, felettesnev=?, felettesemail=?, cim=?, ellatoorvos=?, datum=?",
                    [
                        $row[array_search("nev", $_SESSION["patient-excel-cols"])],
                        $_POST["bmid"],
                        $sessionId,
                        $row[array_search("torzsszam", $_SESSION["patient-excel-cols"])],
                        date("Y-m-d", strtotime($row[array_search("szuldatum", $_SESSION["patient-excel-cols"])])),
                        $taj,
                        $row[array_search("munkakor", $_SESSION["patient-excel-cols"])],
                        $row[array_search("anyjaneve", $_SESSION["patient-excel-cols"])],
                        $row[array_search("szulhely", $_SESSION["patient-excel-cols"])],
                        date("Y-m-d", strtotime($row[array_search("vizsgalatdatuma", $_SESSION["patient-excel-cols"])])),
                        date("Y-m-d", strtotime($row[array_search("visszarendeles", $_SESSION["patient-excel-cols"])])),
                        $row[array_search("felettesnev", $_SESSION["patient-excel-cols"])],
                        strtolower($row[array_search("felettesemail", $_SESSION["patient-excel-cols"])]),
                        $row[array_search("cim", $_SESSION["patient-excel-cols"])],
                        end($orvos),
                        date("Y-m-d H:i:s"),
                    ]
                );
            }
            die();
        }
    }

    public function showPage()
    {
        echo "<div id='uploaded-excel-viewer' class='container-xxl mx-3'>" . (!empty($this->data) ? $this->setExcelViewer($this->data) : "") . "</div>";
    }

    private function setExcelViewer($data): string
    {
        $html = "";
        $colsNumb = count($data[0]);

        $html .= $this->controlMenuBar();
        $html .= "<script type='text/javascript' src='js/tabledragndrop.js'></script>";
        $html .= "<table class='table table-hover' id='diagnosis_list'>";
        $html .= "<thead>";
        $html .= "    <tr class='sortable-col' style='text-align:center;vertical-align:middle'>";
        $html .= "        <th class='nonsortable' style='vertical-align:bottom'><i class='fa-regular fa-square-check'></i></th>";
        $html .= "        <th class='nonsortable' style='vertical-align:bottom' scope='col'>#</th>";
        $html .= "        <th class='nonsortable' style='vertical-align:bottom' scope='col'><i class='fa-solid fa-gear'></i></th>";
        for ($i = 0; $i < $colsNumb; $i++) {
            $html .= "<th style='padding:0.6rem 0.6rem'>" . $this->setColumnName($i);
            /*$html .= "  <div style='display:inline;position:absolute;top:13px;right:-5px;padding:0px 10px 10px 0px'>";
            $html .= "    <i style='display:block;position:relative' class='fa-solid fa-sort-up'></i>";
            $html .= "    <i style='display:block;position:relative' class='fa-solid fa-sort-down'></i>";
            $html .= "  </div>";*/
            $html .= "</th>";
        }
        $html .= "    </tr>";
        $html .= "</thead>";
        $html .= "<tbody>";
        foreach ($data as $index => $row) {
            $html .= "    <tr class='sortable-col' style='vertical-align:middle' id='data-row-{$index}'>";
            $html .= "        <td><input class='form-check-input' type='checkbox' id='rowSelector{$index}' value=''></td>";
            $html .= "        <td class='priority' style='text-align:center' scope='row'>{$index}.</td>";
            $html .= "        <td style='white-space: nowrap;'>";
            $html .= "          <i style='cursor:pointer;padding:0.4rem' title='Szerkesztés' onClick='editDataRow({$index})' class='fa-solid fa-pen edit-row'></i>&nbsp;&nbsp;";
            $html .= "          <i style='cursor:pointer;padding:0.4rem' title='Törlés' onClick='deleteDataRow({$index})' class='fa-solid fa-trash-can remove-row'></i>";
            $html .= "        </td>";
            for ($i = 0; $i < $colsNumb; $i++) {
                $html .= "   <td class='selectable'>" . (isset($row[$i]) ? $row[$i] : "") . "</td>";
            }
            $html .= "    </tr>";
        }
        $html .= "</tbody>";
        $html .= "</table>";
        return $html;
    }

    private function setColumnName($numb): string
    {
        //return "valami{$numb}";

        $html = "";
        $html .= "<div style='white-space:nowrap'>";
        $html .= "  <button type='button' style='display:none' title='Oszlop törlése' onClick='removeCol(" . ($numb + 3) . ")' class='btn btn-danger btn-sm mb-1 column-delete-button'><i class='fa-solid fa-trash-can'></i></button><br>";
        $html .= "  <select style='display:inline' class='form-select form-select-sm my-select' onChange='sortPatientDataRows()'>";
        $html .= "      <option value='' disabled></option>";
        foreach ($this->cols as $index => $col) {
            if (isset($_SESSION["patient-excel-cols"][$numb]) && $_SESSION["patient-excel-cols"][$numb] == $col["id"]) {
                $selected = "selected='true'";
            } else {
                $selected = "";
            }
            $html .= "  <option {$selected} value='{$col["id"]}'>{$col["name"]}</option>";
        }
        //$html .= "      <option value='remove'>Törés <i class='fa-solid fa-trash-can'></i></option>";
        $html .= "  </select>";

        $html .= "  <i style='font-size:14px;padding:5px;cursor:pointer' class='fa-solid fa-sort'></i>";
        $html .= "</div>";

        return $html;
    }

    private function controlMenuBar(): string
    {
        $html = "";

        $operations = [
            0 => [
                "name" => "show-table-col-removes",
                "html" => "<button type='button' class='btn btn-danger btn-sm' title='Oszlopok törlése' onClick='showTablecolDelButtons()'><i class='fa-solid fa-table-columns'></i>&nbsp;<i class='fa-solid fa-xmark'></i></button>",
                "category" => "common",
            ],
            1 => [
                "name" => "create-dm-recipient-list",
                "html" => "<button type='button' class='btn btn-success btn-sm' title='Címzett lista készítése DM-hez' onClick='createDMRecipientList()'><i class='fa-regular fa-paper-plane'></i>&nbsp;<i class='fa-solid fa-list-ul'></i></button>",
                "category" => "restricted",
            ],
            1 => [
                "name" => "create-referal-list",
                "html" => "<button type='button' class='btn btn-success btn-sm' title='Beutaló lista készítése' onClick='createReferalList()'><i class='fa-solid fa-file-pdf'></i>&nbsp;<i class='fa-solid fa-table-list'></i></button>",
                "category" => "restricted",
            ],
        ];

        $html .= "<div class='container-xxl'>";
        if (!empty($this->currentAction)) {
            $html .= "<h5><i class='fa-solid fa-link'></i>&nbsp;{$this->currentAction["name"]}</h5>";
            $html .= "<hr></hr>";
        }
        foreach ($operations as $key => $operation) {
            if ($operation["category"] == "common") {
                $html .= $operation["html"] . "&nbsp;";
            }
            if (!empty($this->currentAction)) {
                if (in_array($key, $this->currentAction["operations"])) {
                    $html .= $operation["html"] . "&nbsp;";
                }
            }
        }
        $html .= "</div>";


        return $html;
    }
}
