<?php

class LaborKeroService
{
    public function __construct()
    {
        if (isset($_POST["showlaborkerowindow"])) {
            $reservationId = intval($_POST["showlaborkerowindow"]);
            echo $this->laborKeroWindow($reservationId);
            die;
        }

        if (isset($_POST["laborkeroItemChange"])) {
            $requestId = intval($_POST["rid"]);
            $itemId = intval($_POST["itemId"]);
            $checked = intval($_POST["checked"]);
            if ($checked == 1) {
                sql_query("insert into labrequestitems set itemid=?, requestid=?", [$itemId, $requestId]);
            } else {
                sql_query("delete from  labrequestitems where itemid=? and requestid=?", [$itemId, $requestId]);
            }

            $data = sql_query("select count(*) as db from labrequestitems where requestid=?", [$requestId])->fetch(PDO::FETCH_ASSOC);
            echo $data["db"];
            die;
        }

        if (isset($_POST["addPackToLaborRequest"])) {
            $error = "";
            $reservationId = intval($_POST["fid"]);
            $packId = intval($_POST["packId"]);

            if ($packId == 0) {
                $error = "Nem választottál csomagot!";
            }

            if (empty($error)) {
                if ($requestData = sql_query("select id, laborpacks from labrequests where foglalasid=?", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
                    $packs = json_decode($requestData["laborpacks"]);
                    if (in_array($packId, $packs)) {
                        $error = "Ezt a csomagot már hozzáadtad a laborkéréshez!";
                    } else {
                        $packs[] = $packId;
                        sql_query("update labrequests set laborpacks=? where id=?", [json_encode(array_values($packs)), $requestData["id"]]);

                        $items = $this->getLaborRequestItems($requestData["id"]);
                        if ($packData = sql_query("select items from synlab_labor_csomagok where id=?", [$packId])->fetch(PDO::FETCH_ASSOC)) {
                            $packItems = json_decode($packData["items"]);
                            foreach ($packItems as $packItem) {
                                if (!in_array($packItem, $items)) {
                                    $items[] = $packItem;
                                }
                            }
                            $this->putLaborRequestItems($requestData["id"], $items);
                        }
                    }
                }
            }

            Utils::jsonOut(["error" => $error, "html" => $this->laborKeroWindow($reservationId)]);
        }

        if (isset($_POST["removePackFromLaborRequest"])) {
            $reservationId = intval($_POST["fid"]);
            $packId = intval($_POST["packId"]);

            if ($requestData = sql_query("select id, laborpacks from labrequests where foglalasid=?", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
                $packs = json_decode($requestData["laborpacks"]);
                if (($key = array_search($packId, $packs)) !== false) {
                    unset($packs[$key]);
                }
                sql_query("update labrequests set laborpacks=? where id=?", [json_encode(array_values($packs)), $requestData["id"]]);

                $items = $this->getLaborRequestItems($requestData["id"]);
                if ($packData = sql_query("select items from synlab_labor_csomagok where id=?", [$packId])->fetch(PDO::FETCH_ASSOC)) {
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
                    if ($packData = sql_query("select items from synlab_labor_csomagok where id=?", [$pack])->fetch(PDO::FETCH_ASSOC)) {
                        $packItems = json_decode($packData["items"]);
                        foreach ($packItems as $packItem) {
                            if (!in_array($packItem, $items)) {
                                $items[] = $packItem;
                            }
                        }
                    }
                }
                $this->putLaborRequestItems($requestData["id"], $items);
            }

            echo $this->laborKeroWindow($reservationId);
            die;
        }
    }

    public function getLaborRequestData($reservationId): array {
        if (!sql_query("select id from labrequests where foglalasid=?", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
            sql_query("insert into labrequests set created=now(), createdby=?, provider='centrumlab', foglalasid=?, laborpacks='[]', laboritems='[]', status='temp'", [$_SESSION["adminuser"]["username"], $reservationId]);
        }
        $result = sql_query("select * from labrequests where foglalasid=?", [$reservationId])->fetch(PDO::FETCH_ASSOC);
        $result["items"] = sql_query("select * from labrequestitems where id=?", [$reservationId])->fetchAll(PDO::FETCH_ASSOC);
        $result["itemarray"] = [];
        foreach ($result["items"] as $item) {
            $result["itemarray"][] = $item["itemid"];
        }
        return $result;
    }

    public function laborKeroWindow($reservationId): string {
        $laborRequestData = $this->getLaborRequestData($reservationId);
        $requestPacks = json_decode($laborRequestData["laborpacks"]);
        $selectedItems = $this->getLaborRequestItems($laborRequestData["id"]);

        $html = "";

        $html .= "<div style='width:1000px;background:#eee;padding:10px;'>";

        $html .= "<div style='margin-bottom: 10px;'>";
        $html .= "<div style='display:table;width:100%;'>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding-right: 10px;width:10px;'>";
        $html .= "<select style='width:260px;height:27px;' id='laborkercsomagcombo'>";
        $html .= "<option value='0'>Válassz csomagot, vagy jelöld ki a tételeket!</option>";
        $packs = sql_query("select id, name from synlab_labor_csomagok WHERE items<>'[]' AND aktiv=1 order by name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($packs as $pack) {
            $html .= "<option value='{$pack["id"]}'>{$pack["name"]}</option>";
        }

        $html .= "</select>";
        $html .= "</div>";

        $html .= "<div style='display:table-cell;vertical-align: middle;padding-right: 10px;width:10px;'>";
        $html .= "<a class='printbutton' onclick='addPackToLaborRequest();return false;' href='#' style='background: #00aa00'>csomag hozzáadása</a> ";
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

        $html .= "<div style='display:table-cell;vertical-align: middle;text-align: right;'><span id='laborkeroteteleknumber'>".count($selectedItems)."</span> tétel</div>";

        $html .= "</div>";

        $html .= "</div>";


        $html .= "<div id='labortetelekcheckboxes' style='height:610px;overflow: auto;'>";
        $items = sql_query("select t.*, k.name as kerolap, kat.name as categoryname from synlab_labor_tetelek t 
            left join synlab_labor_kerolapok k on k.id=t.appform 
            LEFT JOIN synlab_labor_tetel_kategoriak kat ON kat.id=t.category
            order by t.appform, t.category, t.name")->fetchAll(PDO::FETCH_ASSOC);

        $lastAppForm = $lastCategory = 0;
        foreach ($items as $item) {
            if ($lastAppForm != $item["appform"]) {
                $lastAppForm = $item["appform"];
                $html .= "<div style='font-weight:bold;margin-bottom:6px;padding-bottom:3px;margin-top:6px;padding-top:3px;border-bottom:1px solid #ccc;border-top:1px solid #ccc;font-size: 16px;'>{$item["kerolap"]}</div>";
            }
            if ($lastCategory != $item["category"]) {
                $lastCategory = $item["category"];
                $html .= "<div style='font-weight:bold;padding-bottom:3px;'>{$item["categoryname"]}</div>";
            }
            $checked = in_array($item["id"], $selectedItems) ? "checked" : "";
            $html .= "<div style='display:inline-block;width:190px;overflow:hidden;white-space: nowrap;margin-right: 5px;'> <span title='{$item["name"]}'><input onchange='laborkeroItemChange($(this), {$item["id"]});' type='checkbox' value='1' {$checked}/> {$item["name"]}</span></div>";
        }
        $html .= "<div style='margin-top:10px;'></div>";
        $html .= "</div>";

        $html .= "<div style='margin-top:10px;'>";
        $html .= "<input type='hidden' id='laborkeroreservationid' value='{$reservationId}' />";
        $html .= "<input type='hidden' id='laborkerorequestid' value='{$laborRequestData["id"]}' />";
        $html .= "<a class='printbutton' onclick='saveLaborKero();return false;' href='#' style='background: #00aa00'>Laborkérő mentése</a> ";
        $html .= "<a class='printbutton' onclick='hideGeneralPopup();return false;' href='#'>Bezárás</a> ";
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
}