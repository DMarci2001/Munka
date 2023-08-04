<?php

class AdminLabRequestsPage extends AdminCorePage {

    public function __construct()
    {
        parent::__construct();

        $GLOBALS["javascript"][] = "laborpage.js?v=".date("YmdHi");

        if (!isset($_SESSION["labcegfilter"])) {
            $_SESSION["labcegfilter"] = 0;
        }

        if (isset($_GET["setlabcegfilter"])) {
            $_SESSION["labcegfilter"] = $_GET["setlabcegfilter"];
        }

        if (isset($_REQUEST["generalsearch"])) {
            echo $this->listLabRequests();
            die;
        }

        if (isset($_POST["savelaborpaciensdata"])) {
            if (!$this->adminUser->labortetelAccess()) {
                Utils::jsonOut(["error" => "Jogosultság hiba", "html" => ""]);
            }

            $error = "";
            $requestId = intval($_POST["savelaborpaciensdata"]);

            sql_query("update labrequests set nev=?, taj=?, szuldatum=?, email=? where id=?", [$_POST["nev"], $_POST["taj"], $_POST["szuldatum"], $_POST["email"], $requestId]);

            $requests = $this->getLabRequests(["id" => $requestId]);

            Utils::jsonOut(["error" => $error, "html" => $this->labRequestRow($requests[0])]);
        }

        if (isset($_POST["showlaborpacienseditor"])) {
            if (!$this->adminUser->labortetelAccess()) {
                Utils::jsonOut(["error" => "Jogosultság hiba", "html" => ""]);
            }

            $requestId = intval($_POST["showlaborpacienseditor"]);
            $error = "";
            if (!$request = sql_query("select * from labrequests where id=?", [$requestId])->fetch(PDO::FETCH_ASSOC)) {
                $error = "Hiba: PacinesId = 0";
            }

            $html = "";
            $html .= "<div style='background:#eee;border:10px solid white;'>";

            $html .= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
            $html .= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class='fa-solid fa-flask'></i>&nbsp;&nbsp;Lelet adatainak szerkesztése</div>";
            $html .= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
            $html .= "</div>";

            $html .= "<div style='padding:10px;'>";

            $html.= "<div>Név:</div>";
            $html.= "<div><input style='width:300px;' id='laborpaciensnev' type='text' value='{$request["nev"]}' /></div>";

            $html.= "<div style='margin-top:5px;'>* TAJ:</div>";
            $html.= "<div><input style='width:300px;' id='laborpacienstaj' type='text' value='{$request["taj"]}' /></div>";

            $html.= "<div style='margin-top:5px;'>* Születési dátum:</div>";
            $html.= "<div><input style='width:300px;' id='laborpaciensszuldatum' type='text' value='{$request["szuldatum"]}' /></div>";

            $html.= "<div style='margin-top:5px;'>E-mail:</div>";
            $html.= "<div><input style='width:300px;' id='laborpaciensemail' type='text' value='{$request["email"]}' /></div>";

            $html.= "<div style='margin-top:10px;'>A kiküldött PDF jelszava a TAJ szám lesz.<br/>Ha a TAJ szám nincs megadva, akkor a jelszó<br/>a születési dátum kötőjelek és pontok nélkül.</div>";

            $html .= "<div style='margin-top:10px;'>";
            $html .= "<input type='hidden' id='laborrequestid' value='{$request["id"]}' />";
            $html .= "<a class='printbutton' onclick='saveLaborPaciensData();return false;' href='#' style='background: #00aa00'>Adatok mentése</a> ";
            $html .= "<a class='printbutton' onclick='hideGeneralPopup();return false;' href='#'>Bezárás</a> ";
            $html .= "</div>";

            $html .= "</div>";

            $html .= "</div>";

            Utils::jsonOut(["error" => $error, "html" => $html]);
        }

        if (isset($_POST["showrequestdetails"])) {
            $id = intval($_POST["showrequestdetails"]);
            $requestData = sql_query("SELECT resultdate, IF(r.resultpdf='', 0, 1) AS result, foglalasid, provider FROM labrequests r WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);

            $html = "";
            $html .= "<div style='background:#eee;border:10px solid white;'>";

            $html .= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
            $html .= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class='fa-solid fa-flask'></i>&nbsp;&nbsp;Lelet kérés részletei</div>";
            $html .= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
            $html .= "</div>";

            $html .= "<div style='padding:10px;'>";
            $html .= "<div style='height:610px;overflow: auto;'>";

            if ($requestData["provider"] == "spektrumlab") {
                $items = sql_query("SELECT i.itemid, t.* FROM labrequestitems i LEFT JOIN synlab_labor_tetelek t ON t.id=i.itemid WHERE i.requestid=?", [$id])->fetchAll(PDO::FETCH_ASSOC);

                $html.= "<div style='margin-bottom: 5px;'><a class='printbutton' target='_blank' onclick='showLaborKeroWin({$requestData["foglalasid"]});return false;' href='#' style='background: green;'><i class='fa-solid fa-flask'></i> Laborkérő megtekintése</a></div>";

                $html.= "<div style='margin-bottom: 5px;'>Kért vizsgálatok:</div>";
                $sor = 1;
                foreach ($items as $item) {
                    $html.="<div style='display:table-row;'>";
                    $html.="<div style='display:table-cell;'>{$sor}.&nbsp;&nbsp;</div>";
                    $html.="<div style='display:table-cell;'>{$item["name"]}</div>";
                    $html.="</div>";
                    $sor++;
                }

                if ($requestData["result"] == 1) {
                    $html.="<div style='margin:5px 0px;'>Eredmények megérkeztek: {$requestData["resultdate"]}</div>";
                }
            }


            $html.= "</div>";

            $html .= "<div style='margin-top:10px;'>";
            $html .= "<input type='hidden' id='laborrequestid' value='{$request["id"]}' />";
            $html .= "<a class='printbutton' onclick='hideGeneralPopup();return false;' href='#'>Bezárás</a> ";
            $html .= "</div>";

            $html .= "</div>";

            $html .= "</div>";

            echo $html;
            die;
        }

        if (isset($_POST["sendleletemail"])) {
            $id = intval($_POST["id"]);
            $html = "";

            $requestData = $this->getLabRequest($id);
            if (!empty($requestData)) {
                $service = new NotificationService();
                $service->sendLaborLeletEmail($id);
                $html = $this->userErtesitesForm($this->getLabRequest($id));
            } else {
                $error = "Error 9455";
            }

            Utils::jsonOut(["error" => "", "html" => $html]);
            die;
        }

    }

    public function showPage() {
        if (!$this->adminUser->labortetelAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        $cw = "";
        if (!$this->adminUser->allCegJog()) {
            $cw.= " and c.id in (" . $this->adminUser->getCegList() . ")";
        }

        $GLOBALS["subtitle"] = "Labor eredmények";

        echo "<div style='margin-bottom:20px;'>";
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        //echo $this->cegFilter()."&nbsp;&nbsp;";
        //echo $this->eszkozFilter();
        echo "<input data-page='labrequests' data-resultdiv='labrequestlist' type='text' id='generalsearch' value='' placeholder='Keresés...'/>&nbsp;&nbsp;&nbsp;&nbsp;";
        echo "</div>";


        /*
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo "<select name='cegselect' onchange='setLabCegFilter(this.value,\"patients\");'>";
        echo "<option value='0'>Szűrés cégre</option>";

        $res=sql_query("SELECT c.* FROM cegek c where true {$cw} order by megnev");
        if (sql_num_rows($res) > 1) {
            echo "<option value='-1'".($_SESSION["labcegfilter"]==-1?" selected":"").">Összes cég</option>";
        }
        while ($rowt = sql_fetch_array($res)) {
            echo "<option value='{$rowt["id"]}'".($_SESSION["labcegfilter"]==$rowt["id"]?" selected":"").">{$rowt["megnev"]}</option>";
        }

        echo "</select>";
        echo "</div>";
        */

        echo "</div>";

        echo "<div id='labrequestlist'>";
        echo $this->listLabRequests();
        echo "</div>";
    }

    private function listLabRequests():string {
        $requests = [];
        if (isset($_REQUEST["generalsearch"]) && isset($_REQUEST["term"])) {
            $requests = $this->getLabRequests(["search" => $_REQUEST["term"]]);
        }

        if (empty($requests)) {
            $requests = $this->getLabRequests();
        }

        $html = "";

        $html.= "<div style='display:table;width:100%;'>";
        $html.= "<div style='display:table-row;background:#ccc;font-weight: bold;'>";
        //$html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'></td>";
        $html.= "<div style='display:table-cell;white-space: nowrap;padding:5px 5px 5px 5px;width:100px;'>Eredmény időpontja</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;padding:5px 5px 5px 0px;width:90px;'>Provider</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;padding:5px 5px 5px 0px;width:10px;'></div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;padding:5px 5px 5px 0px;width:200px;'>Paciens</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;padding:5px 5px 5px 0px;width:100px;'>Szül. idő / TAJ</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;padding:5px 5px 5px 0px;width:30px;'>Eredmény</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;padding:5px 5px 5px 0px;'>Értesítés</div>";
        $html.= "</div>";

        foreach ($requests as $request) {
            $html.= "<div id='requestrow{$request["id"]}' style='display:table-row;".($request["provider"] == "spektrumlab" ? "background:#ffd;":"")."'>";
            $html.= $this->labRequestRow($request);
            $html.= "</div>";
        }
        $html.= "</div>";

        return $html;
    }

    private function labRequestRow($request):string {
        $html = "";

        $cellStyle = "display:table-cell;vertical-align:middle;padding:6px 2px;white-space: nowrap;border-bottom:1px solid #ccc;";

        $emailProvider = false;
        if (substr_count($request["provider"], "@")) {
            $data = json_decode($request["synlabdata"], JSON_OBJECT_AS_ARRAY);
            $emailProvider = true;
        }

        $html.= "<div style='{$cellStyle}'>".date("Y-m-d H:i", strtotime($request["resultdate"]))."</div>";
        $html.= "<div style='{$cellStyle}'><a class='printbutton' title='kérés megtekintése' target='_blank' onclick='toggleRequestDetailRow(\"{$request["id"]}\");return false;' href='#' style='padding:1px 5px;'>{$request["provider"]}</a></div>";
        $html.= "<div style='{$cellStyle}'><a class='printbutton' title='adatok szerkesztése' target='_blank' onclick='showLaborPaciensEditor(\"{$request["id"]}\");return false;' href='#' style='padding:1px 5px;'>szerk</a></div>";
        $html.= "<div style='{$cellStyle}'>{$request["nev"]}".($emailProvider?"<div style='font-size: 11px;' title='Eredeti fájlnév'>{$request["synlabfilename"]}</div>":"")."</div>";
        $html.= "<div style='{$cellStyle}'>{$request["szuldatum"]}<div>{$request["taj"]}</div></div>";
        $html.= "<div style='{$cellStyle}'>";
        if ($request["result"] == 1) {
            $html.= "<div style=''><a class='printbutton' target='_blank' href='https://bejelentkezes.hungariamed.hu/admin/index.php?print&template=laborlelet1&rid={$request["id"]}&p={$request["pass"]}' style='background: #00aa00;padding:1px 5px;'>Lelet letöltése</a></div>";
        }
        $html.= "</div>";
        $html.= "<div style='{$cellStyle}'><div id='ertesitesform{$request["id"]}'>".$this->userErtesitesForm($request)."</div></div>";

        return $html;
    }

    public function userErtesitesForm($request):string {
        $html = "";

        if ($request["result"] == 1) {
            if ($request["ertesitve"] == 1) {
                $html .= "Felhasználó értesítve: {$request["ertesitesemail"]} ({$request["ertesitesdatum"]})";
                $buttonText = "Újraküldés";
            } else {
                if (empty(trim($request["email"])) || !filter_var($request["email"], FILTER_VALIDATE_EMAIL)) {
                    $html .= "Nincs email cím megadva";
                    $buttonText = "";
                } else {
                    $html .= "Még nincs kiküldve";
                    $buttonText = "Küldés";
                }
            }

            if ($buttonText != "") {
                $html .= " <a onclick='sendLeletEmail(this);return false;' title='{$request["email"]}' data-id='{$request["id"]}' data-email='{$request["email"]}' class='printbutton' target='_blank' href='#' style='background: #00aa00;padding:1px 5px;'><i class='fa-solid fa-envelope'></i> {$buttonText}</a>";
            }
        }

        return $html;
    }


    private function getLabRequest($id) {
        $requestData = [];
        $requests = $this->getLabRequests(["id" => $id]);
        if (!empty($requests[0])) {
            $requestData = $requests[0];
        }
        return $requestData;
    }

    private function getLabRequests($params = []) {
        $queryParams = [];
        $w = "";

        if (!$this->adminUser->allCegJog()) {
            $w = " and f.cegid in (" . $this->adminUser->getCegList() . ")";
        }

        if ($_SESSION["labcegfilter"] > 0) {
            $w.= " and f.cegid=?";
            $queryParams[] = $_SESSION["labcegfilter"];
        }

        if (!empty($params["search"])) {
            $w .= " and instr(concat(r.nev,r.szuldatum,r.taj), ?)";
            $queryParams[] = $params["search"];
        }

        if (!empty($params["id"])) {
            $w .= " and r.id=?";
            $queryParams[] = $params["id"];
        }

        return sql_query("SELECT r.nev, r.szuldatum, r.taj, f.cegid, f.telefon, r.email, c.megnev AS cegnev, r.id, r.pass, r.created, r.provider, r.foglalasid, r.laborpacks, IF(r.resultpdf='', 0, 1) as result, r.resultdate, r.ertesitve, r.ertesitesdatum, r.ertesitesemail, r.synlabfilename, r.synlabdata FROM labrequests r 
            LEFT JOIN foglalasok f ON f.id=r.foglalasid
            LEFT JOIN cegek c ON c.id=f.cegid
            WHERE r.status<>'temp' {$w} ORDER BY r.resultdate DESC LIMIT 1000", $queryParams)->fetchAll(PDO::FETCH_ASSOC);
    }


}