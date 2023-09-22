<?php

class LaborKeroService
{
    const SPEKTRUMLAB_VERKEP_ID = 846;

    private string $errorMessage = "";

    public function __construct()
    {
        if (!isset($_SESSION["spmatricaprinter"])) {
            $_SESSION["spmatricaprinter"] = "zebra";
        }
        if (!isset($_SESSION["spmatricaprinterpos"])) {
            $_SESSION["spmatricaprinterpos"] = "190,0";
        }

        if (isset($_POST["showSpektrumLabMatricaWin"])) {
            Utils::jsonOut(["error" => "", "html" => $this->showSpectrumLabMatricaWin()]);
        }

        if (isset($_POST["saveSpMatricData"])) {
            if (isset($_POST["printer"])) {
                $_SESSION["spmatricaprinter"] = $_POST["printer"];
            }
            if (isset($_POST["printerpos"])) {
                $_SESSION["spmatricaprinterpos"] = $_POST["printerpos"];
            }

            if (isset($_POST["checkedReq"])) {
                $_SESSION["checkedReq"] = [];
                $ids = explode("_", $_POST["checkedReq"]);
                foreach ($ids as $id) {
                    if (!empty($id)) {
                        $_SESSION["checkedReq"][] = $id;
                    }
                }
            }

            Utils::jsonOut(["error" => "", "html" => $this->showPrinterButtons()]);
        }

        if (isset($_POST["showlaborkerowindow"])) {
            $reservationId = intval($_POST["showlaborkerowindow"]);
            $error = "";
            $requestWindow = "";
            $reservation = sql_query("select * from foglalasok where id=?", [$reservationId])->fetch(PDO::FETCH_ASSOC);
            //if ($reservation["paciensid"] == 0) {
            //    $error = "Hiba: PacinesId = 0";
            //}

            $utils = new Utils();
            if (!$utils->validateDate($reservation["szuldatum"], "Y-m-d")) {
                $error = "A születési idő hibásan lett megadva! {$reservation["szuldatum"]}";
            }
            if (empty(trim($reservation["irsz"])) || empty(trim($reservation["varos"])) || empty(trim($reservation["utca"])) || empty(trim($reservation["neme"]))) {
                $error = "A laborkéréshez kötelező mezők: név, taj szám, születési idő, cím, neme!";
            }

            if (empty($error)) {
                $requestWindow = $this->laborKeroWindow($reservationId);
            }

            Utils::jsonOut(["error" => $error, "html" => $requestWindow]);
        }

        if (isset($_POST["sendlaborkero"])) {
            $requestId = intval($_POST["rid"]);
            $reservationId = intval($_POST["fid"]);
            $service = new SpektrumlabService();

            $error = "";

            $items = sql_query("select id from labrequestitems i where i.requestid=?", [$requestId])->fetchAll(PDO::FETCH_ASSOC);
            if (empty($items)) {
                $error = "Válassz legalább 1 vizsgálatot";
            }

            if (empty($error)) {
                sql_query("update labrequests set status='pending' where id=?", [$requestId]);
                $error = $service->writeNextRequest($requestId);
                //if (empty($error)) {
                    //sql_query("update labrequests set status='pending' where id=?", [$requestId]);
                //}
            }

            Utils::jsonOut(["error" => $error, "html" => $this->laborKeroWindow($reservationId)]);
        }

        if (isset($_POST["cancellaborkero"])) {
            $requestId = intval($_POST["rid"]);
            $reservationId = intval($_POST["fid"]);
            sql_query("update labrequests set status='temp' where id=?", [$requestId]);
            Utils::jsonOut(["error" => "", "html" => $this->laborKeroWindow($reservationId)]);
        }

        if (isset($_POST["refreshlaborkeromessages"])) {
            $requestId = intval($_POST["rid"]);
            $reservationId = intval($_POST["fid"]);
            $service = new SpektrumlabService();
            //$service->getReceivedAnswer();
            //$service->fillMissingMessageRequestIds();
            if (!$service->serviceRunning()) {
                $this->errorMessage = "Hiba: A SpectrumLab commcl szolgáltatás nem fut a szerveren.";
            }
            echo $this->laborKeroWindow($reservationId);
            die;
        }


        if (isset($_POST["laborkeroItemChange"])) {
            $requestId = intval($_POST["rid"]);
            $itemId = intval($_POST["itemId"]);
            $checked = intval($_POST["checked"]);
            $error = "";

            if ($requestData = sql_query("select id, laborpacks, status from labrequests where id=? and status='temp'", [$requestId])->fetch(PDO::FETCH_ASSOC)) {
                if ($checked == 1) {
                    sql_query("insert into labrequestitems set itemid=?, requestid=?", [$itemId, $requestId]);
                } else {
                    sql_query("delete from  labrequestitems where itemid=? and requestid=?", [$itemId, $requestId]);
                }
            } else {
                $error = "Lezárt laborkérő, már nem változtatható!";
            }

            $totalData = $this->calculateLaborKeroPrice($requestId);

            Utils::jsonOut(["error" => $error, "db" => $totalData["text"]]);
        }

        if (isset($_POST["addPackToLaborRequest"])) {
            $error = "";
            $reservationId = intval($_POST["fid"]);
            $packId = intval($_POST["packId"]);

            if ($packId == 0) {
                $error = "Nem választottál csomagot!";
            }

            if (empty($error)) {
                if ($requestData = sql_query("select id, laborpacks, status from labrequests where foglalasid=?", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
                    if ($requestData["status"] == "temp") {
                        $packs = json_decode($requestData["laborpacks"]);
                        if (in_array($packId, $packs)) {
                            $error = "Ezt a csomagot már hozzáadtad a laborkéréshez!";
                        } else {
                            $packs[] = $packId;
                            sql_query("update labrequests set laborpacks=? where id=?", [json_encode(array_values($packs)), $requestData["id"]]);

                            $items = $this->getLaborRequestItems($requestData["id"]);
                            if ($packData = sql_query("select spektrumitems as items from synlab_labor_csomagok where id=?", [$packId])->fetch(PDO::FETCH_ASSOC)) {
                                $packItems = json_decode($packData["items"]);
                                foreach ($packItems as $packItem) {
                                    if (!in_array($packItem, $items)) {
                                        $items[] = $packItem;
                                    }
                                }
                                $this->putLaborRequestItems($requestData["id"], $items);
                            }
                        }
                    } else {
                        $error = "Lezárt laborkérő, már nem változtatható!";
                    }
                }
            }

            Utils::jsonOut(["error" => $error, "html" => $this->laborKeroWindow($reservationId)]);
        }

        if (isset($_POST["removePackFromLaborRequest"])) {
            $reservationId = intval($_POST["fid"]);
            $packId = intval($_POST["packId"]);
            $error = "";

            if ($requestData = sql_query("select id, laborpacks, status from labrequests where foglalasid=?", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
                if ($requestData["status"] == "temp") {
                    $packs = json_decode($requestData["laborpacks"]);
                    if (($key = array_search($packId, $packs)) !== false) {
                        unset($packs[$key]);
                    }
                    sql_query("update labrequests set laborpacks=? where id=?", [json_encode(array_values($packs)), $requestData["id"]]);

                    $items = $this->getLaborRequestItems($requestData["id"]);
                    if ($packData = sql_query("select spektrumitems as items from synlab_labor_csomagok where id=?", [$packId])->fetch(PDO::FETCH_ASSOC)) {
                        $packItems = json_decode($packData["items"]);
                        foreach ($items as $key => $item) {
                            if (in_array($item, $packItems)) {
                                unset($items[$key]);
                            }
                        }
                    }
                    $this->putLaborRequestItems($requestData["id"], $items);

                    //visszatesszük az esetleg más csomagokban még szereplő tételeket
                    foreach ($packs as $pack) {
                        if ($packData = sql_query("select spektrumitems as items from synlab_labor_csomagok where id=?", [$pack])->fetch(PDO::FETCH_ASSOC)) {
                            $packItems = json_decode($packData["items"]);
                            foreach ($packItems as $packItem) {
                                if (!in_array($packItem, $items)) {
                                    $items[] = $packItem;
                                }
                            }
                        }
                    }
                    $this->putLaborRequestItems($requestData["id"], $items);
                } else {
                    $error = "Lezárt laborkérő, már nem változtatható!";
                }
            }

            Utils::jsonOut(["error" => $error, "html" => $this->laborKeroWindow($reservationId)]);
        }
    }

    public static function updateLaborKeroData($reservationId):void {
        if ($reservationData = sql_query("select nev, taj, szuldatum, email from foglalasok where id=?", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
            sql_query("update labrequests set nev=?, taj=?, szuldatum=?, email=? where foglalasid=?", [$reservationData["nev"], $reservationData["taj"], $reservationData["szuldatum"], $reservationData["email"], $reservationId]);
        }
    }

    public function getLaborRequestData($reservationId): array {
        if (!sql_query("select id from labrequests where foglalasid=?", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
            sql_query("insert into labrequests set created=now(), resultdate=now(), createdby=?, provider='spektrumlab', foglalasid=?, laborpacks='[]', laboritems='[]', status='temp', pass=?", [$_SESSION["adminuser"]["username"], $reservationId, md5(date("YmdHis")).md5($reservationId.date("YmdHis"))]);
            $newRequestId = sql_insert_id();
            sql_query("insert into labrequestitems set requestid=?, itemid=?", [$newRequestId, self::SPEKTRUMLAB_VERKEP_ID]);
        }
        self::updateLaborKeroData($reservationId);
        $result = sql_query("select * from labrequests where foglalasid=?", [$reservationId])->fetch(PDO::FETCH_ASSOC);
        $result["items"] = sql_query("select * from labrequestitems where id=?", [$reservationId])->fetchAll(PDO::FETCH_ASSOC);
        $result["itemarray"] = [];
        foreach ($result["items"] as $item) {
            $result["itemarray"][] = $item["itemid"];
        }
        return $result;
    }

    private function calculateLaborKeroPrice($requestId):array {
        $result = ["db" => 0, "price" => "", "text" => ""];
        $packPrice = 0;
        $itemPrice = 0;

        $packItems = [];
        $laborRequestData = sql_query("select id, laborpacks from labrequests where id=?", [$requestId])->fetch(PDO::FETCH_ASSOC);
        $selectedItems = $this->getLaborRequestItems($requestId);
        $requestPacks = json_decode($laborRequestData["laborpacks"], JSON_OBJECT_AS_ARRAY);

        foreach ($requestPacks as $pack) {
            $packData = sql_query("select items, price from synlab_labor_csomagok where id=?", [$pack])->fetch(PDO::FETCH_ASSOC);
            $packIds = json_decode($packData["items"], JSON_OBJECT_AS_ARRAY);
            $packItems = array_merge($packItems, $packIds);
            $packPrice += $packData["price"];
        }

        $packItems = array_unique($packItems);

        foreach ($selectedItems as $itemId) {
            if (!in_array($itemId, $packItems)) {
                $itemData = sql_query("select price from synlab_labor_tetelek where id=?", [$itemId])->fetch(PDO::FETCH_ASSOC);
                $itemPrice += $itemData["price"];
            }
        }

        $result["db"] = count($selectedItems);
        $result["price"] = $packPrice + $itemPrice;
        $result["text"] = "{$result["db"]} tétel, {$result["price"]} Ft";

        return $result;
    }

    public function laborKeroWindow($reservationId): string {
        $laborRequestData = $this->getLaborRequestData($reservationId);
        $requestPacks = json_decode($laborRequestData["laborpacks"]);
        $selectedItems = $this->getLaborRequestItems($laborRequestData["id"]);
        $totalData = $this->calculateLaborKeroPrice($laborRequestData["id"]);
        $reservationData = sql_query("select id, nev, szuldatum, taj from foglalasok where id=?", [$reservationId])->fetch(PDO::FETCH_ASSOC);

        $html = "";

        $html .= "<div style='width:1000px;background:#eee;'>";

        $html .= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class='fa-solid fa-flask'></i>&nbsp;&nbsp;{$reservationData["nev"]} - {$reservationData["szuldatum"]} - {$reservationData["taj"]}</div>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
        $html .= "</div>";

        $html .= "<div style='padding:10px;'>";

        $html .= "<div style='margin-bottom: 10px;'>";
        $html .= "<div style='display:table;width:100%;'>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding-right: 10px;width:10px;'>";
        $html .= "<select style='width:260px;height:27px;' id='laborkercsomagcombo'>";
        $html .= "<option value='0'>Válassz csomagot, vagy jelöld ki a tételeket!</option>";
        $packs = sql_query("select id, name from synlab_labor_csomagok WHERE spektrumitems<>'[]' AND aktiv=1 order by name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($packs as $pack) {
            $html .= "<option value='{$pack["id"]}'>{$pack["name"]}</option>";
        }

        $html .= "</select>";
        $html .= "</div>";

        $html .= "<div style='display:table-cell;vertical-align: middle;padding-right: 10px;width:10px;'>";
        $html .= "<a class='printbutton' onclick='addPackToLaborRequest();return false;' href='#' style='background: #00aa00'><i class='fa-solid fa-plus'></i> csomag</a> ";
        $html .= "</div>";
        $html .= "<div style='display:table-cell;vertical-align: middle;'>";
        if (empty($requestPacks)) {
            $html .= "Nem választottál még csomagot";
        } else {
            $rPacks = sql_query("select id, name, price from synlab_labor_csomagok where id in (" . implode(",", $requestPacks) . ")")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rPacks as $rPack) {
                $html .= "<a class='printbutton' onclick='removePackFromLaborRequest({$rPack["id"]});return false;' href='#' style='background: #ccc;' title='{$rPack["name"]}'>" . mb_substr($rPack["name"], 0, 15) . " <i class='fa-solid fa-xmark'></i></a> ";
            }
        }
        $html .= "</div>";

        $html .= "<div style='display:table-cell;vertical-align: middle;text-align: right;'><span id='laborkeroteteleknumber'>{$totalData["text"]}</span></div>";

        $html .= "</div>";

        $html .= "</div>";

        $showCheckBoxes = $laborRequestData["status"] == "temp";


        $html .= "<div id='labortetelekcheckboxes' style='height:610px;overflow: auto;'>";

        if ($showCheckBoxes) {
            if (true) {
                $items = sql_query("select t.*, k.name as kerolap, kat.name as categoryname from synlab_labor_tetelek t
                LEFT JOIN synlab_labor_tetelek t2 on t2.spid = t.id
                left join synlab_labor_kerolapok k on k.id=t2.appform 
                LEFT JOIN synlab_labor_tetel_kategoriak kat ON kat.id=t2.category
                WHERE t.provider='spektrumlab'
                group by t.id
                order by t2.appform is null, t2.appform, t.name")->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $items = sql_query("select t.*, k.name as kerolap, kat.name as categoryname from synlab_labor_tetelek t
                LEFT JOIN synlab_labor_tetelek t2 on t2.spid = t.id
                left join synlab_labor_kerolapok k on k.id=t2.appform 
                LEFT JOIN synlab_labor_tetel_kategoriak kat ON kat.id=t2.category
                WHERE t.provider='spektrumlab' and t2.spid<>0
                group by t.id
                order by t2.appform, t.name")->fetchAll(PDO::FETCH_ASSOC);
            }

            $lastAppForm = $lastCategory = "";
            foreach ($items as $item) {
                if (empty($item["kerolap"])) {
                    $item["kerolap"] = "Nem kategorizált";
                }
                if ($lastAppForm != $item["kerolap"]) {
                    $lastAppForm = $item["kerolap"];
                    $html .= "<div style='font-weight:bold;margin-bottom:6px;padding-bottom:3px;margin-top:6px;padding-top:3px;border-bottom:1px solid #ccc;border-top:1px solid #ccc;font-size: 16px;'>{$item["kerolap"]}</div>";
                }
                //if ($lastCategory != $item["categoryname"]) {
                //    $lastCategory = $item["categoryname"];
                //    $html .= "<div style='font-weight:bold;padding-bottom:3px;'>{$item["categoryname"]}</div>";
                //}
                $checked = in_array($item["id"], $selectedItems) ? "checked" : "";
                $html .= "<div style='display:inline-block;width:186px;overflow:hidden;white-space: nowrap;margin-right: 5px;'> <span title='{$item["name"]}'><input onchange='laborkeroItemChange($(this), {$item["id"]});' id='litem{$item["id"]}' type='checkbox' value='1' {$checked}/> <label for='litem{$item["id"]}'>{$item["name"]}</label></span></div>";
            }
        } else {
            if ($this->errorMessage != "") {
                $html.= "<div style='background:#a00;color:#fff;padding:10px;'>{$this->errorMessage}</div>";
            }
            $messages = sql_query("select * from labrequestmessages where requestid=? order by datum desc", [$laborRequestData["id"]]);
            $html.= "<div id='laborkerohistory'>";
            //$html.= "<div style='margin-bottom: 10px;'><a class='printbutton' onclick='refreshLaborKeroMessages();return false;' href='#' style='background: #00aa00'>Üzenetek frissítése</a></div>";

            if ($laborRequestData["resultpdf"] != "") {
                $html.= "<div style='margin-top:5px;padding:5px;background:lightskyblue;'>Lelet megérkezett {$laborRequestData["resultdate"]}</div>";
                $html.= "<div style='margin: 10px 0px;'><a class='printbutton' target='_blank' href='https://bejelentkezes.hungariamed.hu/admin/index.php?print&template=laborlelet1&rid={$laborRequestData["id"]}&p={$laborRequestData["pass"]}' style='background: #00aa00'>Lelet megtekintése</a></div>";
            }

            foreach ($messages as $message) {
                $messageHead = "";
                if ($message["tipus"] == "in") {
                    $messageHead = "Bejövő üzenet a SpektrumLab-tól {$message["datum"]}";
                }
                if ($message["tipus"] == "out") {
                    $messageHead = "Kimenő üzenet a SpektrumLab felé {$message["datum"]}";
                }
                $html.= "<div style='margin-top:5px;padding:5px;background:lightskyblue;'>{$messageHead}</div>";
                $html.= "<div style='margin: 5px 0px 5px 0px;'>".nl2br($message["content"])."</div>";
            }
            $html.= "</div>";
        }

        $html .= "<div style='margin-top:10px;'></div>";
        $html .= "</div>";

        $html .= "<div style='margin-top:10px;'>";
        $html .= "<input type='hidden' id='laborkeroreservationid' value='{$reservationId}' />";
        $html .= "<input type='hidden' id='laborkerorequestid' value='{$laborRequestData["id"]}' />";
        if (in_array($laborRequestData["status"], ["temp"])) {
            $html .= "<a class='printbutton' onclick='sendLaborKero();return false;' href='#' style='background: #00aa00'>Laborkérő küldése</a> ";
        }
        if (in_array($laborRequestData["status"], ["pending"])) {
            $html .= "<a class='printbutton' onclick='cancelLaborKero();return false;' href='#' style='background: #aa0000'>Laborkérő visszavonása</a> ";
        }

        $statusText = $laborRequestData["status"];
        if ($statusText == "pending") {
            $statusText = "Laborkérés elküldve, eredmény még nem érkezett";
        }

        $html .= "<a class='printbutton' onclick='hideGeneralPopup();return false;' href='#'>Bezárás</a> ";
        $html .= "Status: {$statusText}";
        $html .= "</div>";

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function getLaborRequestItems($requestId): array {
        $items = [];
        $rows = sql_query("select id, itemid from labrequestitems i where i.requestid=?", [$requestId]);
        foreach ($rows as $row) {
            $items[] = $row["itemid"];
        }
        return $items;
    }

    private function putLaborRequestItems($requestId, $items) {
        foreach ($items as $item) {
            if (!sql_query("select id from labrequestitems where requestid=? and itemid=?", [$requestId, $item])->fetch(PDO::FETCH_ASSOC)) {
                sql_query("insert into labrequestitems set itemid=?, requestid=?", [$item, $requestId]);
            }
        }

        $rows = sql_query("select id, itemid from labrequestitems i where i.requestid=?", [$requestId]);
        foreach ($rows as $row) {
            if (!in_array($row["itemid"], $items)) {
                sql_query("delete from labrequestitems where id=?", [$row["id"]]);
            }
        }
    }

    private function showSpectrumLabMatricaWin():string {
        $html = "";

        $printerName = $_SESSION["spmatricaprinter"];
        $printerPos  = $_SESSION["spmatricaprinterpos"];

        $html .= "<div style='background:#eee;border:10px solid white;'>";

        $html .= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class='fa-solid fa-print'></i>&nbsp;&nbsp;Spektrumlab matricák nyomtatása</div>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
        $html .= "</div>";

        $html .= "<div style='padding:10px;'>";
        $html .= "<div style='min-height:210px;max-height:500px;overflow: auto;'>";

        $html.= "<div style='margin-bottom: 10px;padding-bottom:10px;border-bottom:1px solid #ccc;'>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'>Nyomtató neve:&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><input onchange='refreshPrinterButtons();' style='width:150px;' type='text' id='spprintername' value='{$printerName}' />&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'>Pos:&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><input onchange='refreshPrinterButtons();' style='width:50px;' type='text' id='spprinterpps' value='{$printerPos}' />&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><a class='printbutton' target='_blank' href='#' onclick='return false;' style='padding:2px 5px;'>Beállítás</a></div>";
        $html.= "</div>";

        $messages = sql_query("SELECT m.* FROM labrequestmessages m WHERE m.datum>DATE_SUB(NOW(), INTERVAL 2 DAY) AND m.tipus='in' AND m.requestid<>0 ORDER BY datum DESC")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($messages)) {
           $html.= "<div style='margin: 40px 0px 5px 0px;font-weight: bold;text-align: center'>Nem található friss matrica adat</div>";
        } else {
            $requestIds = [];
            foreach ($messages as $message) {
                $rows = explode("\r", $message["content"]);
                foreach ($rows as $row) {
                    $fields = explode("|", $row);
                    if ($fields[0] == "MSA" && ($fields[1] == "AA" || $fields[1] == "AR")) {
                        $requestIds[] = $fields[2];
                    }
                }
            }

            $requestIds = array_unique($requestIds);
            $_SESSION["checkedReq"] = [];

            $html .= "<div id='matricacheckboxes'>";
            foreach ($requestIds as $requestId) {
                $matricaData = sql_query("select nev, taj, printmatrica from labrequests where id=?", [$requestId])->fetch(PDO::FETCH_ASSOC);
                $html .= "<div style='display:table-row;'>";
                $checked = "";
                if ($matricaData["printmatrica"] == 0) {
                    $checked = "checked";
                    $_SESSION["checkedReq"][] = $requestIds;
                }
                $html .= "<div style='display:table-cell;vertical-align: middle;padding-right: 5px;'><input onchange='refreshPrinterButtons();' type='checkbox' data-id='{$requestId}' id='spmatrica{$requestId}' value='1' {$checked} /></div>";
                $html .= "<div style='display:table-cell;vertical-align: middle;padding-right: 10px;'>{$matricaData["nev"]} ({$matricaData["taj"]})</div>";
                //$html .= "<div style='display:table-cell;vertical-align: middle;'>{$matricaData["taj"]}</div>";
                $html .= "</div>";
            }
            $html .= "</div>";
        }

        $html.= "</div>";

        $html .= "<div style='margin-top:10px;' id='printerbuttonscontainer'>".$this->showPrinterButtons()."</div>";

        $html .= "</div>";

        $html .= "</div>";

        return $html;
    }


    private function showPrinterButtons():string {
        $params = [
            "printer" => $_SESSION["spmatricaprinter"],
            "printerPos" => $_SESSION["spmatricaprinterpos"],
            "checkedReq" => $_SESSION["checkedReq"]
        ];

        $testParams = [
            "printer" => $_SESSION["spmatricaprinter"],
            "printerPos" => $_SESSION["spmatricaprinterpos"],
        ];

        $html = "";
        $html.= "<a class='printbutton' target='_blank' href='/admin/index.php?print&template=spektrumlabmatrica&params=".base64_encode(json_encode($params))."'>Nyomtatás</a> ";
        $html.= "<a class='printbutton' target='_blank' href='/admin/index.php?print&template=spektrumlabmatrica&params=".base64_encode(json_encode($testParams))."'>Teszt nyomtatása</a> ";
        $html.= "<a class='printbutton' onclick='hideGeneralPopup();return false;' href='#'>Bezárás</a> ";
        return $html;
    }

}