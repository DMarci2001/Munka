<?php

class AdminLabRequestsPage extends AdminCorePage {

    public function __construct()
    {
        parent::__construct();

        $GLOBALS["javascript"][] = "laborpage.js?v=".date("YmdHi");

        if (!isset($_SESSION["labcegfilter"])) {
            $_SESSION["labcegfilter"] = 0;
        }

        if (!isset($_SESSION["labfuturefilter"])) {
            $_SESSION["labfuturefilter"] = 0;
        }

        if (isset($_GET["setlabcegfilter"])) {
            $_SESSION["labcegfilter"] = $_GET["setlabcegfilter"];
        }

        if (!isset($_SESSION["labrequestsearchbekuldokod"])) {
            $_SESSION["labrequestsearchbekuldokod"] = "";
        }

        if (isset($_REQUEST["generalsearch"])) {
            echo $this->listLabRequests();
            die;
        }

        if (isset($_REQUEST["bekuldokodfilter"])) {
            $_SESSION["labrequestsearchbekuldokod"] = $_REQUEST["bekuldokodfilter"];
            echo $this->listLabRequests();
            die;
        }

        if (isset($_REQUEST["filterchange"])) {
            $_SESSION["labfuturefilter"] = intval($_REQUEST["futureFilter"]);
            echo $this->listLabRequests();
            die;
        }

        if (isset($_REQUEST["getlaboremailtemplate"])) {
            $id = intval($_REQUEST["getlaboremailtemplate"]);
            $template = sql_query("SELECT szoveg FROM labor_szoveg_sablonok WHERE id=?",array($id))->fetch(PDO::FETCH_ASSOC);
            //$template["szoveg"] = $this->messageTemplates[$id];
            $template["szoveg"] = str_replace("#felhasznalo#", $this->adminUser->user["nev"], $template["szoveg"]);
            echo $template["szoveg"];

            die;
        }


        if (isset($_POST["storeLabKiertekeles"])) {
            if (!$this->adminUser->laborRequestPageAccess()) {
                Utils::jsonOut(["error" => "Jogosultság hiba", "html" => ""]);
            }

            $error = "";
            $message = "Kiértékelés visszavonva!";
            $requestId = intval($_POST["storeLabKiertekeles"]);

            sql_query("update labrequests set kiertekelve=not kiertekelve, kiertekelveby=?, kiertekelvedate=now() where id=?", [$this->adminUser->user["nev"], $requestId]);
            if ($data = sql_query("SELECT kiertekelve, kiertekelveby FROM labrequests where id=?", [$requestId])->fetch(PDO::FETCH_ASSOC)) {
                if ($data["kiertekelve"] == 1) {
                    $message = "Kiértékelés tárolva: {$data["kiertekelveby"]}";
                }
            }

            $requests = $this->getLabRequests(["id" => $requestId]);

            Utils::jsonOut(["error" => $error, "message"=> $message, "html" => $this->labRequestRow($requests[0])]);
        }

        if (isset($_POST["savelaborpaciensdata"])) {
            if (!$this->adminUser->laborRequestPageAccess()) {
                Utils::jsonOut(["error" => "Jogosultság hiba", "html" => ""]);
            }

            $error = "";
            $requestId = intval($_POST["savelaborpaciensdata"]);

            sql_query("update labrequests set nev=?, taj=?, szuldatum=?, email=?, megj=? where id=?", [trim($_POST["nev"]), trim($_POST["taj"]), trim($_POST["szuldatum"]), trim($_POST["email"]), $_POST["labormegjtext"], $requestId]);
            if ($_POST["laboremailtext"] != "-") {
                sql_query("update labrequests set emailtext=? where id=?", [$_POST["laboremailtext"], $requestId]);
            }

            $requests = $this->getLabRequests(["id" => $requestId]);

            Utils::jsonOut(["error" => $error, "html" => $this->labRequestRow($requests[0])]);
        }

        if (isset($_POST["showlaborpacienseditor"])) {
            if (!$this->adminUser->laborRequestPageAccess()) {
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
            $html .= "<a class='printbutton' onclick='saveLaborPaciensData(1);return false;' href='#' style='background: #00aa00'>Adatok mentése</a> ";
            $html .= "<a class='printbutton' onclick='hideGeneralPopup();return false;' href='#'>Bezárás</a> ";
            $html .= "</div>";

            $html .= "</div>";

            $html .= "</div>";

            Utils::jsonOut(["error" => $error, "html" => $html]);
        }

        if (isset($_POST["showrequestdetails"])) {
            $id = intval($_POST["showrequestdetails"]);
            $requestData = sql_query("SELECT resultdate, IF(r.status='done', 1, 0) AS result, foglalasid, provider, laborpacks, nev, createdby FROM labrequests r WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);
            $packIds = json_decode($requestData["laborpacks"], JSON_OBJECT_AS_ARRAY);

            $html = "";
            $html .= "<div style='background:#eee;border:10px solid white;'>";

            $html .= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
            $html .= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class='fa-solid fa-flask'></i>&nbsp;&nbsp;{$requestData["nev"]} labor kérés részletei</div>";
            $html .= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
            $html .= "</div>";

            $html .= "<div style='padding:10px;'>";
            $html .= "<div style='min-height:210px;overflow: auto;'>";

            if (substr_count($requestData["provider"], "@")) {
                $html.= "<div style='margin: 40px 0px 5px 0px;font-weight: bold;text-align: center'>Email címről letöltött lelet esetén<br/>a labor kérés adatai nem elérhetőek</div>";
            }

            if (in_array($requestData["provider"], [LaborKeroService::LABOR_PROVIDER_SYNLAB, LaborKeroService::LABOR_PROVIDER_SPEKTRUMLAB])) {
                $items = sql_query("SELECT i.itemid, t.* FROM labrequestitems i LEFT JOIN synlab_labor_tetelek t ON t.id=i.itemid WHERE i.requestid=?", [$id])->fetchAll(PDO::FETCH_ASSOC);

                //$html.= "<div style='margin-bottom: 5px;'><a class='printbutton' target='_blank' onclick='showLaborKeroWin({$requestData["foglalasid"]});return false;' href='#' style='background: green;'><i class='fa-solid fa-flask'></i> Laborkérő megtekintése</a></div>";

                $html.= "<div style='margin: 0px 0px 5px 0px;font-weight: bold;'>Laborkérőt kitöltötte:</div>";
                $html.="<div style=''>{$requestData["createdby"]}</div>";

                if (!empty($packIds)) {
                    $html.= "<div style='margin: 10px 0px 5px 0px;font-weight: bold;'>Választott csomagok:</div>";
                    $packs = sql_query("select name from synlab_labor_csomagok where id in (".implode(",", $packIds).")")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($packs as $pack) {
                        $html.="<div style='display:table-row;'>";
                        $html.="<div style='display:table-cell;'>&bull;&nbsp;</div>";
                        $html.="<div style='display:table-cell;'>{$pack["name"]}</div>";
                        $html.="</div>";
                    }
                }

                $html.= "<div style='margin: 10px 0px 5px 0px;font-weight: bold;'>Kért vizsgálatok:</div>";
                $sor = 1;
                foreach ($items as $item) {
                    $html.="<div style='display:table-row;'>";
                    $html.="<div style='display:table-cell;'>{$sor}.&nbsp;&nbsp;</div>";
                    $html.="<div style='display:table-cell;'>{$item["name"]}</div>";
                    $html.="</div>";
                    $sor++;
                }

                if ($requestData["result"] == 1) {
                    $html.="<div style='margin:5px 0px;'>Lelet megérkezett: {$requestData["resultdate"]}</div>";
                }
            }


            $html.= "</div>";

            $html .= "<div style='margin-top:10px;'>";
            $html .= "<input type='hidden' id='laborrequestid' value='{$requestData["id"]}' />";
            $html .= "<a class='printbutton' onclick='hideGeneralPopup();return false;' href='#'>Bezárás</a> ";
            $html .= "</div>";

            $html .= "</div>";

            $html .= "</div>";

            echo $html;
            die;
        }

        if (isset($_POST["showSendLeletWindow"])) {
            $id = intval($_POST["showSendLeletWindow"]);
            $request = sql_query("SELECT resultdate, IF(r.status='done', 1, 0) AS result, id, foglalasid, provider, nev, taj, szuldatum, email, emailtext, ertesiteslog, r.megj FROM labrequests r WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);

            $nev = $request["nev"] ?? "Nincs neve!";

            $html = "";
            $html .= "<div style='background:#eee;border:10px solid white;'>";

            $html .= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
            $html .= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class='fa-solid fa-flask'></i>&nbsp;&nbsp;{$nev} lelet küldése</div>";
            $html .= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
            $html .= "</div>";

            $html .= "<div style='padding:10px 0px;'>";
            $html .= "<div style='display:table-row;'>";
            $html .= "<div style='display:table-cell;vertical-align:top;padding:0px 10px;'>";

            $html.= "<div>Név:</div>";
            $html.= "<div><input style='width:300px;' id='laborpaciensnev' type='text' value='{$request["nev"]}' /></div>";

            $html.= "<div style='margin-top:5px;'>* TAJ:</div>";
            $html.= "<div><input style='width:300px;' id='laborpacienstaj' type='text' value='{$request["taj"]}' /></div>";

            $html.= "<div style='margin-top:5px;'>* Születési dátum:</div>";
            $html.= "<div><input style='width:300px;' id='laborpaciensszuldatum' type='text' value='{$request["szuldatum"]}' /></div>";

            $html.= "<div style='margin-top:5px;'>E-mail:</div>";
            $html.= "<div><input style='width:300px;' id='laborpaciensemail' type='text' value='{$request["email"]}' /></div>";

            $html.= "<div style='margin-top:10px;'>A kiküldött PDF jelszava a TAJ szám lesz.<br/>Ha a TAJ szám nincs megadva, akkor a jelszó<br/>a születési dátum kötőjelek és pontok nélkül.</div>";

            $html.= "<div style='margin-top:5px;'>Megjegyzés:</div>";
            $html.= "<div><textarea style='width:300px;height:50px;' id='labormegjtext'>{$request["megj"]}</textarea></div>";

            $html.= "</div>";

            $html.= "<div style='display:table-cell;vertical-align:top;padding:0px 10px;border-left:1px solid #ccc;'>";

            $html.= "<div>Levél szövege:</div>";
            $html.= "<div><textarea style='width:500px;height: 270px;' id='laboremailtext'>{$request["emailtext"]}</textarea></div>";
            $html.= "<div style='padding-top:5px;'>sablon betöltése: <a href='#' onclick='return loadLaborEmailTemplate(1);'>jó arcoknak</a> &bull; <a href='#' onclick='return loadLaborEmailTemplate(2);'>rossz arcoknak</a></div>";


            $html.= "</div>";

            $html.= "</div>";


            $html .= "<div style='display:table-row;'>";
            $html .= "<div style='display:table-cell;vertical-align:top;padding:0px 10px;'>";


            $html.= "<div style='margin:10px 0px 0px 0px;'>";
            $html.= "<input type='hidden' id='laborrequestid' value='{$request["id"]}' />";
            $html.= "<a class='printbutton' onclick='saveLaborPaciensData(0);return false;' href='#' style='background: #00aa00'>Adatok mentése</a> ";
            $html.= "<a class='printbutton' onclick='hideGeneralPopup();return false;' href='#'>Bezárás</a> ";
            $html.= "</div>";

            $html.= "</div>";

            $html.= "<div style='display:table-cell;vertical-align:top;padding:0px 10px;border-left:1px solid #ccc;'>";

            $html.= "<div style='margin:10px 0px 0px 0px;'>";
            $html.= "<a class='printbutton' onclick='sendLeletEmail();return false;' href='#' style='background: #00aa00'>Lelet kiküldése</a> ";
            $html.= "</div>";


            $html.= "</div>";
            $html.= "</div>";
            $html.= "</div>";

            if (!empty($request["ertesiteslog"])) {
                $html .= "<div style='padding:10px 10px;'>";
                $html .= "<div style='padding:10px 10px 10px 10px;max-height:50px;background:lightgray;overflow: auto;'>{$request["ertesiteslog"]}</div>";
                $html .= "</div>";
            }

            $html.= "</div>";
            $html.= "</div>";

            echo $html;
            die;
        }

        if (isset($_POST["sendleletemail"])) {
            $id = intval($_POST["id"]);
            $html = "";

            $error = "";
            $requestData = $this->getLabRequest($id);
            if (!empty($requestData)) {
                if (empty($requestData["emailtext"])) {
                    $error = "Nincs megadva szöveg";
                }
                if (empty($requestData["email"]) || !filter_var($requestData["email"], FILTER_VALIDATE_EMAIL)) {
                    $error = "Nincs megadva az email cím";
                }
                if (empty($requestData["taj"])) {
                    $error = "Nincs megadva a taj szám";
                }
                if (empty($this->adminUser->user["email"]) || !filter_var($this->adminUser->user["email"], FILTER_VALIDATE_EMAIL)) {
                    $error = "Nincs megadva, vagy hibás az email címed";
                }
                if (empty($error)) {
                    $service = new NotificationService();
                    $service->sendLaborLeletEmail($id);
                }
                $html = $this->userErtesitesForm($this->getLabRequest($id));
            } else {
                $error = "Error 9455";
            }

            Utils::jsonOut(["error" => $error, "html" => $html]);
            die;
        }


        if (isset($_GET["importinzulin"])) {
            $messages = sql_query("SELECT * FROM labrequestmessages m WHERE tipus='in' AND INSTR(content, 'inzu') AND DATE(datum)='2023-10-20'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($messages as $message) {
                $lastRequestId = 0;
                $rows = explode("\r", $message["content"]);
                foreach ($rows as $key => $row) {
                    $fields = explode("|", $row);
                    if (trim($fields[0]) == "OBR") {
                        $lastRequestId = intval($fields[2]);
                    }

                    if (substr_count($row, "|inz")) {
                        echo "set {$lastRequestId}<br/>";
                        sql_query("update labrequests set printmatrica=1 where id=?", [$lastRequestId]);
                    }
                }
            }
            echo "done";
            die;
        }


    }

    public function showPage() {
        if (!$this->adminUser->laborRequestPageAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        $cw = "";
        if (!$this->adminUser->allCegJog()) {
            $cw.= " and c.id in (" . $this->adminUser->getCegList() . ")";
        }

        $GLOBALS["subtitle"] = "Labor eredmények";

        echo "<div style='margin-bottom:20px;'>";

        $bekuldoKodok = SpektrumlabService::BEKOLDO_KOD_MAP;
        $bekuldoKodok["000000719"] = "SYNLAB Hungaria Med-M Kft.";

        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo "<select name='reqlaborbekuldokodfilter' id='reqlaborbekuldokodfilter' onchange='labReuestPageFilter(this.value);' style='width:210px;background:#ffc;' title='Szűrés beküldőkódra'>";
        echo "<option value=''>Összes beküldőkód</option>";
        foreach ($bekuldoKodok as $bekuldoKod => $bekuldoKodName) {
            echo "<option value='{$bekuldoKod}'" . ($bekuldoKod == $_SESSION["labrequestsearchbekuldokod"] ? " selected" : "") . ">{$bekuldoKod} - {$bekuldoKodName}</option>";
        }
        echo "</select>&nbsp;&nbsp;";
        echo "</div>";

        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo "<input data-page='labrequests' data-resultdiv='labrequestlist' type='text' id='generalsearch' value='' placeholder='Keresés...'/>&nbsp;<a onclick='doGeneralSearch($(\"#generalsearch\"));return false;' href='#' class='ujbutton' style='padding:1px 10px;'>Keresés<span id='generalsearchprogress' style='display: none;'>&nbsp;&nbsp;<i class='fa-solid fa-spinner fa-spin'></i></span></a>&nbsp;&nbsp;&nbsp;";
        //echo "<input type='checkbox' id='futurefiltercheckbox' value='1' ".($_SESSION["labfuturefilter"] == 1 ?"checked":"")." /> jövőbeniek is&nbsp;&nbsp;&nbsp;&nbsp;";
        echo "</div>";

        if (isset($_GET["tesztjns"])) {
            $service = new SpektrumlabService();
            echo $service->importItems();
        }


        $felado = $this->adminUser->user["nev"];
        $feladoEmail = empty(trim($this->adminUser->user["email"])) ? "<span style='color:red;'>nincs megadva email cím, így a lelet kiküldés nem lehetséges</span>":$this->adminUser->user["email"];
        echo "<div style='margin:10px 0px 10px 0px;padding:10px 0px 10px 0px;border-top:1px solid #ccc;border-bottom:1px solid #ccc;'>A leletek kiküldésekor a levél feladója: {$felado}, email: {$feladoEmail}</div>";

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
        $html = "";

        $requests = [];
        if (isset($_REQUEST["generalsearch"]) && isset($_REQUEST["term"])) {
            $requests = $this->getLabRequests(["search" => $_REQUEST["term"]]);
            if (empty($requests)) {
                $html.= "<div style='margin:20px 0px;'>A keresés nem hozott eredmény!</div>";
                //$requests = $this->getLabRequests();
            }
        }

        if (empty($requests) && empty($_REQUEST["term"])) {
            $requests = $this->getLabRequests();
        }

        if (!empty($requests)) {
            $html .= "<div style='display:table;width:100%;'>";
            $html .= "<div style='display:table-row;background:#ccc;font-weight: bold;'>";
            //$html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'></td>";
            $html .= "<div style='display:table-cell;white-space:nowrap;padding:5px 5px 5px 5px;width:100px;'>Eredmény időpontja</div>";
            $html .= "<div style='display:table-cell;white-space:nowrap;padding:5px 5px 5px 0px;width:90px;'>Provider</div>";
            $html .= "<div style='display:table-cell;white-space:nowrap;padding:5px 5px 5px 0px;width:200px;'>Paciens</div>";
            $html .= "<div style='display:table-cell;white-space:nowrap;padding:5px 5px 5px 0px;width:80px;'>Szül. idő / TAJ</div>";
            $html .= "<div style='display:table-cell;white-space:nowrap;padding:5px 5px 5px 0px;width:30px;'>Eredmény</div>";
            $html .= "<div style='display:table-cell;white-space:nowrap;padding:5px 5px 5px 0px;'>Értesítés</div>";
            $html .= "</div>";
        }

        foreach ($requests as $request) {
            $bg = "#fff";
            if ($request["provider"] == LaborKeroService::LABOR_PROVIDER_SPEKTRUMLAB) {
                $bg = "#ffd";
            }
            if ($request["provider"] == LaborKeroService::LABOR_PROVIDER_SYNLAB) {
                $bg = "#e0f3e0";
            }
            $html.= "<div id='requestrow{$request["id"]}' style='display:table-row;background:{$bg};'>";
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

        $resultDate = date("Y-m-d H:i", strtotime($request["resultdate"]));
        if ($request["resultdate"] == $request["created"]) {
            $resultDate = "Elküldve..<br/>".date("Y-m-d H:i", strtotime($request["created"]));
            //if ($request["spelkuldve"] == 0 && $request["provider"] == LaborKeroService::LABOR_PROVIDER_SPEKTRUMLAB) {
            //    $resultDate = "<span style='color:red;'>Nincs elküldve</span><br/>".date("Y-m-d H:i", strtotime($request["created"]));
            //}
        }

        if ($request["folyamatban"] == 1) {
            $resultDate.= "<div style=''><span style='display:inline-block;background:red;color:#fff;padding:2px 4px;border-radius: 4px;margin:2px 0px;'>folyamatban</span></div>";
        }

        if (!empty($request["scanresult"])) {
            $scanResult = json_decode($request["scanresult"], JSON_OBJECT_AS_ARRAY);
            if (in_array("hemolitikus", $scanResult)) {
                $resultDate.= "<div style=''><span style='display:inline-block;background:#a2bffe;color:#fff;padding:2px 4px;border-radius: 4px;margin:2px 0px;'>hemolitikus</span></div>";
            }
            if (in_array("lipemias", $scanResult)) {
                $resultDate.= "<div style=''><span style='display:inline-block;background:#a2bffe;color:#fff;padding:2px 4px;border-radius: 4px;margin:2px 0px;'>lipémiás</span></div>";
            }
            if (in_array("alvadekos", $scanResult)) {
                $resultDate.= "<div style=''><span style='display:inline-block;background:#a2bffe;color:#fff;padding:2px 4px;border-radius: 4px;margin:2px 0px;'>alvadékos</span></div>";
            }
            if (in_array("kevesminta", $scanResult)) {
                $resultDate.= "<div style=''><span style='display:inline-block;background:#a2bffe;color:#fff;padding:2px 4px;border-radius: 4px;margin:2px 0px;'>kevés minta</span></div>";
            }
        }

        if ($request["printmatrica"] == 1) {
            $resultDate.= "<div style=''><span style='display:inline-block;background:limegreen;color:#fff;padding:2px 4px;border-radius: 4px;'>INZULIN</span></div>";
        }

        $html.= "<div style='{$cellStyle}'>{$resultDate}</div>";
        $html.= "<div style='{$cellStyle}'>";
        $html.= "<div><a title='kérés megtekintése' target='_blank' onclick='toggleRequestDetailRow(\"{$request["id"]}\");return false;' href='#'>{$request["provider"]}</a></div>";
        $html.= "<div>{$request["bekuldokod"]}</div>";
        $html.= "</div>";
        //$html.= "<div style='{$cellStyle}'></div>";
        $html.= "<div style='{$cellStyle}'>{$request["nev"]}".($emailProvider?"<div style='font-size: 11px;' title='Eredeti fájlnév'>{$request["synlabfilename"]}</div>":"")."</div>";
        $html.= "<div style='{$cellStyle}'>{$request["szuldatum"]}<div>{$request["taj"]}</div></div>";
        $html.= "<div style='{$cellStyle}'>";
        if ($request["result"] == 1) {
            $html.= "<div style=''><a class='printbutton' target='_blank' href='index.php?print&template=laborlelet1&rid={$request["id"]}&p={$request["pass"]}' style='background: #00aa00;padding:1px 5px;font-size: 15px;'>Letöltés</a>&nbsp;</div>";
        }
        $html.= "</div>";
        $html.= "<div style='{$cellStyle}'>";
        $html.= "<div id='ertesitesform{$request["id"]}'>".$this->userErtesitesForm($request)."</div>";

        //if (!empty($request["megj"])) {
        //    $html.= "<div style='display: inline-block;background:red;color:white;border-radius: 5px;padding:3px 5px;max-width:400px;white-space: normal;'>{$request["megj"]}</div>";
        //}

        $html.= "</div>";

        return $html;
    }

    public function userErtesitesForm($request):string {
        $html = $tooltip = "";
        $icon = "fa-square";
        $buttonText = "Küldés...";
        $color = "#00aa00";

        if ($request["result"] == 1) {
            if ($request["ertesitve"] == 1) {
                $logArray = explode("<br/>", $request["ertesiteslog"]);
                //$html .= $logArray[0];
                $tooltip = $logArray[0];
                //$buttonText = "Újraküldés...";
                $icon = "fa-square-check";
            } else {
                //$html .= "Még nincs kiküldve";
                $tooltip = "Még nincs kiküldve";
                $color = "#88aa88";
            }

            $html .= " <a title='{$tooltip}' onclick='sendLeletWindow(this);return false;' data-id='{$request["id"]}' data-email='{$request["email"]}' class='printbutton' target='_blank' href='#' style='background:{$color};padding:1px 5px;font-size: 15px;'><i class='fa-solid {$icon}'></i> {$buttonText}</a>";

            if (substr_count($request["laboritems"], "\"".Booking_Constants::SPEKTRUM_KIERTEKELES_ID."\"")) {
                $icon = "fa-square";
                $tooltip = "Nincs kiértékelve";
                if ($request["kiertekelve"] == 1) {
                    $icon = "fa-check-square";
                    $tooltip = "Kiértékelte: {$request["kiertekelveby"]}, időpont: ".date("Y-m-d H:i", strtotime($request["kiertekelvedate"]));
                }
                $html .= "&nbsp;&nbsp;<a title='{$tooltip}' onclick='storeLabKiertekeles(this);return false;' data-id='{$request["id"]}' class='printbutton' target='_blank' href='#' style='background: #aaaa00;padding:1px 5px;font-size: 15px;'><i class='fa-solid {$icon}'></i> Kiértékelés..</a>";
            }

        }

        if (!empty($request["megj"])) {
            $html.= "<div style='display:inline-block;background:red;color:white;border-radius:5px;padding:3px 5px;white-space:nowrap;margin-left:10px;'>{$request["megj"]}</div>";
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
            $w .= " and instr(concat(r.nev,r.szuldatum,r.taj,r.synlabfilename), ?)";
            $queryParams[] = $params["search"];
        }

        if (!empty($params["id"])) {
            $w .= " and r.id=?";
            $queryParams[] = $params["id"];
        }

        if ($_SESSION["labrequestsearchbekuldokod"] != "") {
            $w.= " and r.bekuldokod = ?";
            $queryParams[] = $_SESSION["labrequestsearchbekuldokod"];
        }

        //if ($_SESSION["labfuturefilter"] == 0) {
            //$w.= " and r.created<'".date("Y-m-d 23:59:59")."'";
        //}

        return sql_query("SELECT r.nev, r.szuldatum, r.taj, f.cegid, r.email, c.megnev AS cegnev, r.id, r.pass, r.created, r.provider, r.foglalasid, r.laborpacks, r.laboritems, r.kiertekelveby, r.kiertekelve, r.kiertekelvedate, IF(r.status='done', 1, 0) as result, r.resultdate, r.ertesitve, r.ertesitesdatum, r.ertesitesemail, r.synlabfilename, r.synlabdata, r.bekuldokod, r.folyamatban, r.ertesiteslog, r.emailtext, r.printmatrica, r.megj, r.scanresult 
            FROM labrequests r 
            LEFT JOIN foglalasok f ON f.id=r.foglalasid
            LEFT JOIN cegek c ON c.id=f.cegid
            WHERE r.status<>'temp' {$w} 
            ORDER BY r.resultdate DESC LIMIT 1000", $queryParams)->fetchAll(PDO::FETCH_ASSOC);
    }


}