<?php

class LaborKeroService
{
    const SPEKTRUMLAB_VERKEP_ID = 846;
    const LABOR_PROVIDER_SYNLAB = "synlab";
    const LABOR_PROVIDER_SPEKTRUMLAB = "spektrumlab";

    private string $errorMessage = "";
    public string $laborProvider;
    public string $bekuldoKod;
    public string $bekuldoKodSpektrumLab;
    private array $laborNames = [self::LABOR_PROVIDER_SYNLAB => "SynLab", self::LABOR_PROVIDER_SPEKTRUMLAB => "SpektrumLab"];
    private string $testSession = "9ddh9hedkfve8nr1l7pkb2qjm0";

    public function __construct()
    {
        if (!isset($_SESSION["laborprovider"])) {
            $_SESSION["laborprovider"] = self::LABOR_PROVIDER_SPEKTRUMLAB;
        }
        $this->laborProvider = $_SESSION["laborprovider"];

        if (!isset($_SESSION["laborbekuldokod"])) {
            $_SESSION["laborbekuldokod"] = SynlabService::DEFAULT_BEKULDOKOD;
        }
        $this->bekuldoKod = $_SESSION["laborbekuldokod"];

        if (!isset($_SESSION["laborbekuldokodspektrumlab"])) {
            $_SESSION["laborbekuldokodspektrumlab"] = SpektrumlabService::DEFAULT_BEKULDOKOD;
            if (Booking_Constants::SQL_DB == "keltexmed") {
                $_SESSION["laborbekuldokodspektrumlab"] = SpektrumlabService::DEFAULT_BEKULDOKOD_KELTEXMED;
            }
        }
        $this->bekuldoKodSpektrumLab = $_SESSION["laborbekuldokodspektrumlab"];

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
            if (empty(trim($reservation["irsz"])) || empty(trim($reservation["varos"])) || empty(trim($reservation["utca"]))) {
                $error = "A cím megadása kötelező!";
            }
            if (empty(trim($reservation["neme"]))) {
                $error = "A paciens nemének megadása kötelező!";
            }

            if (empty($error)) {
                $requestWindow = $this->laborKeroWindow($reservationId);
            }

            Utils::jsonOut(["error" => $error, "html" => $requestWindow]);
        }

        if (isset($_POST["toggleLaborProvider"])) {
            $reservationId = intval($_POST["toggleLaborProvider"]);
            if ($requestData = sql_query("select id from labrequests where foglalasid=?", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
                $this->resetLaborKero($requestData["id"]);
            }

            if (!empty($_POST["selection"])) {
                $_SESSION["laborprovider"] = $_POST["selection"];
            } else {
                if ($_SESSION["laborprovider"] == self::LABOR_PROVIDER_SYNLAB) {
                    $_SESSION["laborprovider"] = self::LABOR_PROVIDER_SPEKTRUMLAB;
                } else {
                    $_SESSION["laborprovider"] = self::LABOR_PROVIDER_SYNLAB;
                }
            }

            $this->laborProvider = $_SESSION["laborprovider"];
            $_SESSION["providerselected"] = 1;

            $message = "Választott labor szolgáltató: ".$this->laborNames[$this->laborProvider];

            $requestWindow = $this->laborKeroWindow($reservationId);

            Utils::jsonOut(["message" => $message, "html" => $requestWindow]);
        }

        if (isset($_POST["changeLaborBekuldoKod"])) {
            $reservationId = intval($_POST["changeLaborBekuldoKod"]);
            $laborId = $_POST["laborId"];
            $message = "A beküldőkód nem változott";

            if ($laborId == self::LABOR_PROVIDER_SYNLAB) {
                $_SESSION["laborbekuldokod"] = $_POST["selection"];
                $this->bekuldoKod = $_SESSION["laborbekuldokod"];
                $message = "Beküldőkód megváltozott: " . $this->bekuldoKod;
            }

            if ($laborId == self::LABOR_PROVIDER_SPEKTRUMLAB) {
                $_SESSION["laborbekuldokodspektrumlab"] = $_POST["selection"];
                $this->bekuldoKodSpektrumLab = $_SESSION["laborbekuldokodspektrumlab"];
                $message = "Beküldőkód megváltozott: " . $this->bekuldoKodSpektrumLab;
            }

            Utils::jsonOut(["message" => $message, "html" => $this->laborKeroWindow($reservationId)]);
        }

        if (isset($_POST["sendlaborkero"])) {
            $requestId = intval($_POST["rid"]);
            $reservationId = intval($_POST["fid"]);
            $error = "";

            $items = sql_query("select id from labrequestitems i where i.requestid=?", [$requestId])->fetchAll(PDO::FETCH_ASSOC);
            if (empty($items)) {
                $error = "Válassz legalább 1 vizsgálatot";
            }

            if (empty($error)) {
                if ($this->laborProvider == self::LABOR_PROVIDER_SYNLAB) {
                    sql_query("update labrequests set status='waiting', bekuldokod=?, laboritems=?, createdby=? where id=?", [$this->bekuldoKod, json_encode($this->getItemsWithoutPack($requestId)), $_SESSION["adminuser"]["username"], $requestId]);
                }

                if ($this->laborProvider == self::LABOR_PROVIDER_SPEKTRUMLAB) {
                    $service = new SpektrumlabService();
                    sql_query("update labrequests set status='pending', bekuldokod=?, laboritems=?, createdby=? where id=?", [$this->bekuldoKodSpektrumLab, json_encode($this->getItemsWithoutPack($requestId)), $_SESSION["adminuser"]["username"], $requestId]);
                    $error = $service->writeNextRequest($requestId);
                    if (!empty($error)) {
                        sql_query("update labrequests set status='temp' where id=?", [$requestId]);
                    }
                }
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

            $lastCheck = $service->getReceivedAnswer();
            $service->processPdfFromMessages(true);

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
        $createdBy = "automatic";
        if (isset($_SESSION["adminuser"]["username"])) {
            $createdBy = $_SESSION["adminuser"]["username"];
        }

        if (!sql_query("select id from labrequests where foglalasid=?", [$reservationId])->fetch(PDO::FETCH_ASSOC)) {
            sql_query("insert into labrequests set created=now(), resultdate=now(), createdby=?, provider=?, foglalasid=?, laborpacks='[]', laboritems='[]', status='temp', pass=?", [$createdBy, $this->laborProvider, $reservationId, md5(date("YmdHis")).md5($reservationId.date("YmdHis"))]);
            $newRequestId = sql_insert_id();
            if ($this->laborProvider == self::LABOR_PROVIDER_SPEKTRUMLAB) {
                sql_query("insert into labrequestitems set requestid=?, itemid=?", [$newRequestId, self::SPEKTRUMLAB_VERKEP_ID]);
            }
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

    public function resetLaborKero($requestId):void {
        sql_query("delete from labrequests where id=?", [$requestId]);
        sql_query("delete from labrequestitems where requestid=?", [$requestId]);
    }


    private function calculateLaborKeroPrice($requestId):array {
        $result = ["db" => 0, "price" => "", "text" => ""];
        $packPrice = 0;
        $itemPrice = 0;
        $companyId = 0;

        $packItems = [];
        $laborRequestData = sql_query("select id, foglalasid, laborpacks from labrequests where id=?", [$requestId])->fetch(PDO::FETCH_ASSOC);
        if ($reservationData = sql_query("select cegid from foglalasok where id=?", [$laborRequestData["foglalasid"]])->fetch(PDO::FETCH_ASSOC)) {
            $companyId = $reservationData["cegid"];
        }
        $selectedItems = $this->getLaborRequestItems($requestId);
        $requestPacks = json_decode($laborRequestData["laborpacks"], JSON_OBJECT_AS_ARRAY);

        foreach ($requestPacks as $pack) {
            $packData = sql_query("select items, price from synlab_labor_csomagok where id=?", [$pack])->fetch(PDO::FETCH_ASSOC);
            $price = $packData["price"];

            if ($priceData = sql_query("select * from synlab_labor_arak where tipus=1 and tid=? and companyid=? and aktiv=1 limit 1", [$pack, $companyId])->fetch(PDO::FETCH_ASSOC)) {
                $price = $priceData["price"];
            }

            $packIds = json_decode($packData["items"], JSON_OBJECT_AS_ARRAY);
            $packItems = array_merge($packItems, $packIds);
            if ($price == -1) {
                $price = 0;
            }
            $packPrice += $price;
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

    public function getItems() {
        if ($this->laborProvider == self::LABOR_PROVIDER_SYNLAB) {
            return sql_query("SELECT t.*, k.name AS kerolap, kat.name AS categoryname FROM synlab_labor_tetelek t
            LEFT JOIN synlab_labor_kerolapok k ON k.id=t.appform 
            LEFT JOIN synlab_labor_tetel_kategoriak kat ON kat.id=t.category
            WHERE t.provider='synlab' AND t.visibility=1
            GROUP BY t.id
            ORDER BY (t.appform IS NULL or t.appform=0), t.appform, t.name")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($_SESSION["laborprovider"] == self::LABOR_PROVIDER_SPEKTRUMLAB) {
            return sql_query("select t.*, k.name as kerolap, kat.name as categoryname from synlab_labor_tetelek t
            LEFT JOIN synlab_labor_tetelek t2 on t2.spid = t.id
            LEFT JOIN synlab_labor_kerolapok k on k.id=t2.appform 
            LEFT JOIN synlab_labor_tetel_kategoriak kat ON kat.id=t2.category
            WHERE t.provider='spektrumlab' and t.visibility=1
            group by t.id
            order by t2.appform is null, t2.appform, t.name")->fetchAll(PDO::FETCH_ASSOC);
        }

        return [];
    }

    private function packSelect():string {
        $itemsField = "items";
        if ($this->laborProvider == self::LABOR_PROVIDER_SPEKTRUMLAB) {
            $itemsField = "spektrumitems";
        }
        $html = "";
        $html.= "<select style='width:260px;height:27px;' id='laborkercsomagcombo'>";
        $html.= "<option value='0'>Válassz csomagot, vagy jelölj ki tételeket!</option>";
        $packs = sql_query("select id, IF(hmm_name='' or hmm_name is null, name, hmm_name) as name, {$itemsField} as items from synlab_labor_csomagok WHERE {$itemsField}<>'[]' AND aktiv=1 order by name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($packs as $pack) {
            $items = json_decode($pack["items"], JSON_OBJECT_AS_ARRAY);
            $items = array_unique($items);
            $html.= "<option value='{$pack["id"]}'>{$pack["name"]} (".count($items)." vizsgálat)</option>";
        }

        $html.= "</select>";
        //$html.= "<style>  .select2-results__options { min-height: 450px;max-height: 450px; } </style>";
        return $html;
    }

    public function laborKeroWindow($reservationId): string {
        $laborRequestData = $this->getLaborRequestData($reservationId);
        $requestPacks = json_decode($laborRequestData["laborpacks"]);
        $selectedItems = $this->getLaborRequestItems($laborRequestData["id"]);
        $totalData = $this->calculateLaborKeroPrice($laborRequestData["id"]);
        $reservationData = sql_query("select id, nev, szuldatum, taj, pass from foglalasok where id=?", [$reservationId])->fetch(PDO::FETCH_ASSOC);

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
        $html.= $this->packSelect();
        $html .= "</div>";

        $html .= "<div style='display:table-cell;vertical-align: middle;padding-right: 10px;width:10px;'>";
        $html .= "<a title='Csomag hozzáadása a laborkéréshez' class='printbutton' onclick='addPackToLaborRequest();return false;' href='#' style='background: #00aa00'><i class='fa-solid fa-plus'></i> hozzáadás</a> ";
        $html .= "</div>";
        $html .= "<div style='display:table-cell;vertical-align: middle;'>";
        if (empty($requestPacks)) {
            $html .= "Nem választottál még csomagot";
        } else {
            $rPacks = sql_query("select id, IF(hmm_name='' or hmm_name is null, name, hmm_name) as name, price from synlab_labor_csomagok where id in (" . implode(",", $requestPacks) . ")")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rPacks as $rPack) {
                $html .= "<a class='printbutton' onclick='removePackFromLaborRequest({$rPack["id"]});return false;' href='#' style='background: #ccc;' title='{$rPack["name"]}'>" . mb_substr($rPack["name"], 0, 15) . " <i class='fa-solid fa-xmark'></i></a> ";
            }
        }
        $html .= "</div>";

        $html .= "<div style='display:table-cell;vertical-align: middle;text-align: right;'><span id='laborkeroteteleknumber'>{$totalData["text"]}</span></div>";

        $html .= "</div>";

        $html .= "</div>";

        $showCheckBoxes = $laborRequestData["status"] == "temp";


        $html .= "<div id='labortetelekcheckboxes' style='height:510px;overflow: auto;'>";

        if ($showCheckBoxes) {
            if (!isset($_SESSION["providerselected"])) {
                $html.= "<div style='text-align: center;margin-top: 100px;'>";
                $html.= "Válassz melyik szolgáltatóval akarsz dolgozni. Ez később bármikor megváltoztatható.<br/><br/>";
                $html.= "<a class='middlebutton' href='#' onclick='toggleLaborProvider(\"".self::LABOR_PROVIDER_SPEKTRUMLAB."\", {$reservationId});return false;'>SpektrumLab</a>&nbsp;&nbsp;";
                $html.= "<a class='middlebutton' href='#' onclick='toggleLaborProvider(\"".self::LABOR_PROVIDER_SYNLAB."\", {$reservationId});return false;'>SynLab</a>";
                $html.= "</div>";
            } else {
                if ($this->laborProvider == self::LABOR_PROVIDER_SYNLAB) {
                    $html .= "<div style='color:red;font-weight:bold;margin-bottom:6px;padding-bottom:3px;margin-top:6px;padding-top:3px;font-size: 14px;'>Synlab részére egyelőre csak a klinika kémia és allergia vizsgálatok küldhetők!</div>";
                }

                $items = $this->getItems();

                $lastAppForm = "";
                foreach ($items as $item) {
                    if (empty($item["kerolap"])) {
                        $item["kerolap"] = "Nem kategorizált";
                    }
                    if ($lastAppForm != $item["kerolap"]) {
                        $lastAppForm = $item["kerolap"];
                        $html .= "<div style='font-weight:bold;margin-bottom:6px;padding-bottom:3px;margin-top:6px;padding-top:3px;border-bottom:1px solid #ccc;border-top:1px solid #ccc;font-size: 16px;'>{$item["kerolap"]}</div>";
                    }
                    $checked = in_array($item["id"], $selectedItems) ? "checked" : "";
                    $additionalStyle = "";
                    if ($laborRequestData["provider"] == self::LABOR_PROVIDER_SYNLAB && empty($item["commazo"])) {
                        $additionalStyle = "opacity:.4;";
                    }
                    $html .= "<div class='laborkerovizsgalatcheck' data-vizsgalat='{$item["name"]}' style='display:inline-block;width:186px;overflow:hidden;white-space: nowrap;margin-right: 5px;{$additionalStyle}'> <span title='{$item["name"]}'><input onchange='laborkeroItemChange($(this), {$item["id"]});' id='litem{$item["id"]}' type='checkbox' value='1' {$checked}/> <label for='litem{$item["id"]}'>{$item["name"]}</label></span></div>";
                }
            }
        } else {
            if ($laborRequestData["status"] == "waiting") {
                $html.= "<div style='text-align: center;margin-top: 100px;'>Ez a laborkérés elküldéshez sorban áll. A Synlab kb 10 percenként olvassa be a laborkéréseinket.<br/>Addig is készíthetsz további laborkéréseket.</div>";
            }
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

            if ($laborRequestData["matricacode"] != "") {
                $html.= "<div style='margin-top:5px;padding:5px;background:lightskyblue;'>Matrica megérkezett</div>";
                $html.= "<div style='margin: 10px 0px;'><a class='printbutton' target='_blank' onclick='printSpektrumlabMatrica(\"{$reservationData["id"]}\", \"{$reservationData["pass"]}\");return false;' href='#' style='background: #00aa00'>Vonalkódos matrica nyomtatása</a></div>";
            }

            foreach ($messages as $message) {
                $messageHead = "";
                if ($message["tipus"] == "in") {
                    //$messageHead = "Bejövő üzenet a SpektrumLab-tól {$message["datum"]}";
                    continue;
                }
                if ($message["tipus"] == "out") {
                    $messageHead = "Kimenő üzenet a ".$this->laborNames[$laborRequestData["provider"]]." felé {$message["datum"]}";
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
            $buttonTitle = $this->laborNames[$this->laborProvider]." laborkérő küldése";
            if ($this->laborProvider == self::LABOR_PROVIDER_SYNLAB) {
                $buttonTitle.= " <i class='fa-solid fa-caret-right'></i> {$this->bekuldoKod}";
            }
            $html .= "<a class='printbutton' onclick='sendLaborKero();return false;' href='#' style='background: #00aa00'>{$buttonTitle}</a> ";
        }
        if (in_array($laborRequestData["status"], ["waiting"])) {
            $html .= "<a class='printbutton' onclick='cancelLaborKero();return false;' href='#' style='background: #aa0000'>Laborkérő visszavonása</a> ";
        }

        $statusText = $laborRequestData["status"];
        if ($statusText == "pending") {
            $statusText = "Laborkérés elküldve, eredmény még nem érkezett";
        }

        $html .= "<a class='printbutton' onclick='hideGeneralPopup();return false;' href='#'>Bezárás</a> ";

        if (in_array($laborRequestData["status"], ["temp"])) {
            $html.= "Labor: ";
            $html.= "<select name='laborproviderselector' id='laborproviderselector' onchange='toggleLaborProvider($(this).val(), {$reservationId});' style='background:#ffc;'>";
            $html.= "<option value='".self::LABOR_PROVIDER_SPEKTRUMLAB."'".(self::LABOR_PROVIDER_SPEKTRUMLAB == $this->laborProvider ?" selected":"").">".$this->laborNames[self::LABOR_PROVIDER_SPEKTRUMLAB]."</option>";
            $html.= "<option value='".self::LABOR_PROVIDER_SYNLAB."'".(self::LABOR_PROVIDER_SYNLAB == $this->laborProvider ?" selected":"").">".$this->laborNames[self::LABOR_PROVIDER_SYNLAB]."</option>";
            $html.= "</select>&nbsp;&nbsp;";

            if ($this->laborProvider == self::LABOR_PROVIDER_SYNLAB) {
                $html.= "Beküldőkód: ";
                $html.= "<select name='laborbekuldokodselector' id='laborbekuldokodselector' onchange='changeLaborBekuldoKod(\"".self::LABOR_PROVIDER_SYNLAB."\", $(this).val(), {$reservationId});' style='width:210px;background:#ffc;'>";
                foreach (SynlabService::BEKOLDO_KOD_MAP as $bekuldoKod => $bekuldoKodName) {
                    $html .= "<option value='{$bekuldoKod}'" . ($bekuldoKod == $this->bekuldoKod ? " selected" : "") . ">{$bekuldoKod} - {$bekuldoKodName}</option>";
                }
                $html.= "</select>&nbsp;&nbsp;";
            }

            if ($this->laborProvider == self::LABOR_PROVIDER_SPEKTRUMLAB) {
                $html.= "Beküldőkód: ";
                $html.= "<select name='laborbekuldokodselector' id='laborbekuldokodselector' onchange='changeLaborBekuldoKod(\"".self::LABOR_PROVIDER_SPEKTRUMLAB."\", $(this).val(), {$reservationId});' style='width:210px;background:#ffc;'>";
                $codeMap = SpektrumlabService::BEKOLDO_KOD_MAP;
                if (Booking_Constants::SQL_DB == "keltexmed") {
                    $codeMap = SpektrumlabService::BEKOLDO_KOD_MAP_KELTEXMED;
                }
                foreach ($codeMap as $bekuldoKod => $bekuldoKodName) {
                    $html .= "<option value='{$bekuldoKod}'" . ($bekuldoKod == $this->bekuldoKodSpektrumLab ? " selected" : "") . ">{$bekuldoKod} - {$bekuldoKodName}</option>";
                }
                $html.= "</select>&nbsp;&nbsp;";
            }

            //$html.= "<a style='padding:2px 4px;background:red;color:white;border-radius:3px;' href='#' onclick='toggleLaborProvider(\"\", {$reservationId});return false;'>".$this->laborNames[$_SESSION["laborprovider"]]."</a> &bull; ";
        }


        $html .= "Status: {$statusText} ";
        if (in_array($laborRequestData["status"], ["temp"])) {
            $html.= "<input type='text' style='float: right;' id='laborVizsgalatFilterText' placeholder='vizsgálatok szűrése' />";
        }
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

    private function putLaborRequestItems($requestId, $items):void {
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

        $html .= "<div style='background:#eee;border:10px solid white;'>";

        $html .= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class='fa-solid fa-print'></i>&nbsp;&nbsp;Spektrumlab matricák nyomtatása</div>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
        $html .= "</div>";

        $html .= "<div style='padding:10px;'>";
        $html .= "<div style='min-height:210px;max-height:400px;overflow: auto;'>";

        $messages = sql_query("SELECT m.* FROM labrequestmessages m WHERE m.datum>DATE_SUB(NOW(), INTERVAL 30 DAY) AND m.tipus='in' AND m.requestid<>0 ORDER BY datum DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

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


    public function storeLaborKeroFromLabShopData():void {
        $labShopReservations = sql_query("SELECT l.id AS laborid, l.cart_content,l.status, l.payment_method, f.* FROM cart_item c
		LEFT JOIN labshop_vasarlasok l ON l.id = c.session_id
            LEFT JOIN foglalasok f ON f.id=c.reservation_id
            LEFT JOIN labrequests r ON r.foglalasid=f.id
            WHERE f.id IS NOT NULL AND f.datum>NOW() AND r.id IS NULL
            ORDER BY f.datum LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($labShopReservations as $key => $labShopReservation) {
            echo "{$key}\n";

            if ($labShopReservation["payment_method"] == "simplepay" && $labShopReservation["status"] != "done" && $labShopReservation["status"] != "FINISHED") {
                echo "nincs fizetve\n";
                continue;
            }

            if (sql_query("select id from labrequests where foglalasid=? limit 1", [$labShopReservation["id"]])->fetch(PDO::FETCH_ASSOC)) {
                echo "van már laborkérő\n";
                continue;
            }

            $items = $packs = [];

            $orderedPackages = sql_query("SELECT * FROM cart_item WHERE session_id=? AND TYPE='package'", [$labShopReservation["laborid"]])->fetchAll(PDO::FETCH_ASSOC);
            echo "csomagok: ".count($orderedPackages)."\n";

            foreach ($orderedPackages as $orderedPackage) {
                echo "itt3 {$orderedPackage["product_id"]}\n";
                if ($packData = sql_query("select cs.id, cs.name, cs.spektrumitems as items from synlab_labor_csomagok cs where cs.id=?", [$orderedPackage["product_id"]])->fetch(PDO::FETCH_ASSOC)) {
                    $packItems = json_decode($packData["items"]);
                    $packs[] = $packData["id"];
                    echo "{$labShopReservation["nev"]} - pack: {$packData["name"]} ".count($packItems)."\n";
                    foreach ($packItems as $packItem) {
                        if (!in_array($packItem, $items)) {
                            $items[] = $packItem;
                        }
                    }
                }
            }

            /*
            $cartDatas = json_decode($labShopReservation["cart_content"], JSON_OBJECT_AS_ARRAY);
            $onlyPackages = true;
            foreach ($cartDatas as $cartData) {
                if (isset($cartData["type"]) && $cartData["type"] != "package") {
                    //nem csak csomag van benne, így skippeljük
                    $onlyPackages = false;
                }
            }

            if (!$onlyPackages) {
                echo "{$labShopReservation["nev"]} nem csak csomagok {$labShopReservation["laborid"]}\n";
                continue;
            }

            echo "{$labShopReservation["nev"]} only packs {$labShopReservation["laborid"]}\n";

            $items = $packs = [];

            foreach ($cartDatas as $cartData) {
                if (isset($cartData["type"]) && $cartData["type"] == "package") {
                    if ($packData = sql_query("select cs.id, cs.name, cs.spektrumitems as items from synlab_labor_csomagok cs where cs.id=?", [$cartData["id"]])->fetch(PDO::FETCH_ASSOC)) {
                        $packItems = json_decode($packData["items"]);
                        $packs[] = $packData["id"];
                        echo "pack: {$packData["name"]} ".count($packItems)."\n";
                        foreach ($packItems as $packItem) {
                            if (!in_array($packItem, $items)) {
                                $items[] = $packItem;
                            }
                        }
                    }
                }
            }
            */

            $items = array_unique($items);

            if (!empty($items)) {
                $laborRequestData = $this->getLaborRequestData($labShopReservation["id"]);
                sql_query("update labrequests set created=?, resultdate=?, laborpacks=?, laboritems=?, status='temp' where id=?", [$labShopReservation["datum"], $labShopReservation["datum"], json_encode($packs), json_encode($items), $laborRequestData["id"]]);
                foreach ($items as $item) {
                    if (!sql_query("select requestid from labrequestitems where requestid=? and itemid=?", [$laborRequestData["id"], $item])->fetch(PDO::FETCH_ASSOC)) {
                        sql_query("insert into labrequestitems set requestid=?, itemid=?", [$laborRequestData["id"], $item]);
                    }
                }
            }
        }

    }

    public function getItemsWithoutPack($requestId):array {
        $packItems = $allItems = $itemsWithoutPack = [];
        $requestData = sql_query("select laborpacks from labrequests where id=?", [$requestId])->fetch(PDO::FETCH_ASSOC);
        $packs = json_decode($requestData["laborpacks"], JSON_OBJECT_AS_ARRAY);
        foreach ($packs as $pack) {
            if ($packData = sql_query("select spektrumitems as items from synlab_labor_csomagok where id=?", [$pack])->fetch(PDO::FETCH_ASSOC)) {
                $items = json_decode($packData["items"], JSON_OBJECT_AS_ARRAY);
                foreach ($items as $item) {
                    if (!in_array($item, $packItems)) {
                        $packItems[] = $item;
                    }
                }
            }
        }

        $items = sql_query("select * from labrequestitems where requestid=?", [$requestId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            if  (in_array($item["itemid"], $packItems)) {
                $itemsWithoutPack[] = $item["itemid"];
            }
        }

        return $itemsWithoutPack;
    }


}