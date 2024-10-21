<?php

class AdminLabortetelekPage extends AdminCorePage
{
    //Tétel árak módosításának jelölés változója:
    private $setItemPrices;
    //Csomag azonosító:
    private int $packageId;
    private array $packageItems = [];
    //Csövek:
    private $tubes = array(
        "T" => array("title" => "T (tiszta)", "HexColorCode" => ""),
        "Y" => array("title" => "Y (nyál)", "HexColorCode" => ""),
        "N" => array("title" => "N (natív)", "HexColorCode" => ""),
        "L" => array("title" => "L (Lila, EDTA)", "HexColorCode" => ""),
        "Z" => array("title" => "Z (zöld, Li-heparinos)", "HexColorCode" => ""),
        "K" => array("title" => "K (kék)", "HexColorCode" => ""),
        "F" => array("title" => "F (szürke)", "HexColorCode" => ""),
        "V" => array("title" => "V (vizelet)", "HexColorCode" => ""),
        "S" => array("title" => "S (széklet)", "HexColorCode" => "")
    );

    const PRICE_TYPE_PACK = 1;
    const PRICE_TYPE_ITEM = 2;

    public function __construct()
    {
        parent::__construct();

        $GLOBALS["javascript"][] = "laborpage.js?v=".date("YmdHi");

        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        if (!isset($_SESSION["selectedcsomagcompany"])) {
            $_SESSION["selectedcsomagcompany"] = 0;
        }

        if (isset($_GET["used"])) {
            $allItems = [];
            $packs = sql_query("select * from synlab_labor_csomagok where aktiv<>0")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($packs as $pack) {
                $allItems = array_merge($allItems, array_values(json_decode($pack["items"])));
                //$allItems[] = array_values(json_decode($pack["items"]));

                //echo $pack["name"]." ";
            }

            echo implode(",", array_unique($allItems));
            //echo "<pre>".print_r(array_unique($allItems), true)."</pre>";
            die;
        }


        if (isset($_GET["szerk"])) {
           $this->packageId = $_GET["szerk"];
           if ($packageData = sql_query("select items from synlab_labor_csomagok where id=?", [$this->packageId])->fetch(PDO::FETCH_ASSOC)) {
               $this->packageItems = json_decode($packageData["items"]);
           }
        }

        //Csomagok aktiválása/deaktiválása:
        if (isset($_GET["packaktivetoggle"])) {

            $pack = sql_fetch_array(sql_query("SELECT * FROM synlab_labor_csomagok WHERE id=?", array($_GET["packaktivetoggle"])));
            if ($pack["aktiv"] == 1) {
                sql_query("UPDATE synlab_labor_csomagok SET aktiv=0 WHERE id=?", array($_GET["packaktivetoggle"]));
            }
            if ($pack["aktiv"] == 0) {
                sql_query("UPDATE synlab_labor_csomagok SET aktiv=1 WHERE id=?", array($_GET["packaktivetoggle"]));
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die;
        }

        if (isset($_POST["showcategoryselect"])) {
            //if (!$this->adminUser->allCegJog()) {
            //    die;
            //}
            $lastLaborCategory = $_SESSION["lastlaborcategory"] ?? 0;
            $lastCategories = sql_query("select id, trim(name) as name from synlab_labor_tetel_kategoriak where id=?", [$lastLaborCategory])->fetchAll(PDO::FETCH_ASSOC);
            $categories = sql_query_common("select id, trim(name) as name from hungariamed.synlab_labor_tetel_kategoriak where trim(name)<>'' order by trim(name)")->fetchAll(PDO::FETCH_ASSOC);

            echo "<select onchange='saveLaborCategory(\"{$_POST["showcategoryselect"]}\", this.value)' style='width:120px;font-size:12px;padding:1px 4px;'>";
            echo "<option value='0'>Válassz kategóriát!</option>";
            foreach ($lastCategories as $category) {
                $selected = $category["id"] == $_POST["category"] ? "selected":"";
                echo "<option value='{$category["id"]}' {$selected}>{$category["name"]}</option>";
            }
            foreach ($categories as $category) {
                $selected = $category["id"] == $_POST["category"] ? "selected":"";
                echo "<option value='{$category["id"]}' {$selected}>{$category["name"]}</option>";
            }
            echo "</select>";

            die;
        }

        if (isset($_POST["setcategoryid"])) {
            //if (!$this->adminUser->allCegJog()) {
            //    die;
            //}
            sql_query("update synlab_labor_tetelek t set t.category=? where id=?", [$_POST["category"], $_POST["id"]]);
            $_SESSION["lastlaborcategory"] = $_POST["category"];

            echo $this->showCategorySelector($_POST["id"], ["cegid" => $_POST["cegid"]]);
            die;
        }

        //Keresés tétel név szerint:
        if (isset($_POST["searchbyitem"])) {

            /*
            A kereséshez meg kell hívnom az aktuálisan beállított kategória szűrést, a kijelölt elemeket a csomag alapján és az aktuálisan kijelölt és/vagy kivett pipák
            alapján kell a jelöléseket reprodukálnom...
            */
            $packageInstall = json_decode($this->packageItems);
            $strPackageItems = "FIELD(slt.id," . implode(",", $packageInstall) . ") DESC,";

            $setSelectedItemJScall = "onClick='selectItemForPackage($(this).val())'";

            //Le kell kérdeznem a tételeket:
            $rq = sql_query("SELECT slt.*,sltk.name AS category_name,slk.name AS kerolap FROM synlab_labor_tetelek slt
                             LEFT JOIN synlab_labor_tetel_kategoriak sltk ON sltk.id=slt.category
                             LEFT JOIN synlab_labor_kerolapok slk ON slk.id=slt.appform
                             WHERE TRUE " . (!empty($_POST["keyword"]) ? "AND slt.name LIKE '%" . $_POST["keyword"] . "%' " : "") . "
                             ORDER BY " . (!empty($packageInstall) ? $strPackageItems : "") . " sltk.name, slt.name ASC");


            while ($resq = sql_fetch_array($rq)) {
                echo "<tr>";

                if (!empty($packageInstall)) {
                    echo "<td valign=\"top\"><div class=\"tcella\"><input type=\"checkbox\" name=\"item[]\" {$setSelectedItemJScall} " . (in_array($resq["id"], $packageInstall) ? "checked=\"true\"" : "") . " value=\"{$resq["id"]}\"></div></td>";
                }

                //Link a szerkesztéshez:
                echo "<td valign=\"top\"><div class=\"tcella\"><p style=\"color:#a00;margin:0;padding:0\">{$resq["name"]}</p></div></td>";

                //Kérőlap megnevezés:
                echo "<td nowrap valign=\"top\"><div class=\"tcella\" style=\"min-width:10px;\">{$resq["kerolap"]}</div></td>";

                //Kategória megnevezés:
                echo "<td nowrap valign=\"top\"><div class=\"tcella\" style=\"min-width:10px;\">{$resq["category_name"]}</div></td>";

                //Minta vételi cső:
                echo "<td nowrap valign=\"top\" ><div class=\"tcella\" style=\"min-width:10px\">" . (!empty($resq["sample_tube"]) ? $this->tubes[$resq["sample_tube"]]["title"] : "") . "</div></td>";

                if (empty($packageInstall)) {
                    //Forint alapú ár:
                    echo "<td nowrap valign=\"top\" ><div class=\"tcella\" style=\"min-width:10px;padding-right:30px;text-align:right;font-weight:bold\">" . ($this->setItemPrices == true ? "<input type=\"textbox\" style=\"width:80px;text-align:center\" name=\"item-{$resq["id"]}-price\" value=\"{$resq["price"]}\">" : number_format($resq["price"])) . " HUF</div></td>";
                }

                echo "</tr>";
            }
            die();
        }


        //Tétel kijelölése/kijelölés megszüntetése:
        if (isset($_POST["selectItemForPackage"])) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);

            $csomagId = $_POST["csomagId"];
            $itemId = $_POST["selectItemForPackage"];
            $fieldName = "items";

            $itemData = sql_query("select provider from synlab_labor_tetelek where id=?", [$itemId])->fetch(PDO::FETCH_ASSOC);
            if ($itemData["provider"] == "spektrumlab") {
                $fieldName = "spektrumitems";
            }

            if ($packageData = sql_query("select items, spektrumitems from synlab_labor_csomagok where id=?", [$csomagId])->fetch(PDO::FETCH_ASSOC)) {
                $this->packageItems = json_decode($packageData["{$fieldName}"]);
            }

            $newItems = [];
            foreach ($this->packageItems as $item) {
                if ($item != $itemId) {
                    $newItems[] = $item;
                }
            }
            if ($_POST["checked"] == 1) {
                $newItems[] = $itemId;
            }

            sql_query("update synlab_labor_csomagok set {$fieldName}=? where id=?", [json_encode($newItems), $csomagId]);
            die();
        }


        //Tétel kijelölése/kijelölés megszüntetése:
        if (isset($_POST["selectItemForPackage2"])) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);

            $csomagId = $_POST["csomagId"];
            $itemId = $_POST["selectItemForPackage2"];
            $fieldName = "items";

            $itemData = sql_query("select provider from synlab_labor_tetelek where id=?", [$itemId])->fetch(PDO::FETCH_ASSOC);
            if ($itemData["provider"] == "spektrumlab") {
                $fieldName = "spektrumitems";
            }

            if ($packageData = sql_query("select items, spektrumitems from synlab_labor_csomagok where id=?", [$csomagId])->fetch(PDO::FETCH_ASSOC)) {
                $this->packageItems = json_decode($packageData["{$fieldName}"]);
            }

            $newItems = [];
            foreach ($this->packageItems as $item) {
                if ($item != $itemId) {
                    $newItems[] = $item;
                }
            }
            if ($_POST["checked"] == 1) {
                $newItems[] = $itemId;
            }

            sql_query("update synlab_labor_csomagok set {$fieldName}=? where id=?", [json_encode($newItems), $csomagId]);

            echo $this->showItemChecker($csomagId);
            die();
        }


        //Csomag mentése:
        if (isset($_POST["savePackage"])) {
            sql_query(
                "UPDATE synlab_labor_csomagok SET name=?, hmm_name=?, price=?,line_through_price=?, gender=?,categories=?, description=?, aktiv=?, kiemelt=?, alias=? WHERE id=?",
                array($_POST["name"], $_POST["hmm_name"], $_POST["price"], $_POST["line-through-price"], $_POST["gender"], $this->setCategories(), $_POST["description"], $_POST["aktiv"], $_POST["kiemelt"] ?? 0, $_POST["alias"], $this->packageId)
            );
        }

        if (isset($_POST["changeLaborCsomagPrice"])) {
            $price = intval($_POST["changeLaborCsomagPrice"]);
            $cid = intval($_POST["cid"]);
            $tid = intval($_POST["tid"]);

            if ($cid == 0) {
                sql_query("update synlab_labor_csomagok set price=? where id=?", [$price, $tid]);
            } else {
                sql_query("delete from synlab_labor_arak where tid=? and companyid=?", [$tid, $cid]);
                sql_query("insert into synlab_labor_arak set tipus=?, tid=?, companyid=?, price=?", [self::PRICE_TYPE_PACK, $tid, $cid, $price]);
            }
            die;
        }

        if (isset($_POST["changeLaborItemPrice"])) {
            $price = intval($_POST["changeLaborItemPrice"]);
            $tid = intval($_POST["tid"]);

            sql_query("update synlab_labor_tetelek set price=? where id=?", [$price, $tid]);
            die;
        }

        if (isset($_POST["changeLaborElkeszules"])) {
            $value = $_POST["changeLaborElkeszules"];
            $tid = intval($_POST["tid"]);

            sql_query("update synlab_labor_tetelek set elkeszules=? where id=?", [$value, $tid]);

            //Synlab páros esetén írja felül annak az értékét is!
            if($synlab = sql_query("SELECT * FROM synlab_labor_tetelek WHERE spid=?",[$tid])->fetch(PDO::FETCH_ASSOC)){
                sql_query("UPDATE synlab_labor_tetelek SET elkeszules=? WHERE id=?",[$value,$synlab["id"]]);
            }
            die;
        }

        if (isset($_POST["changeLaborCsomagCompany"])) {
            $_SESSION["selectedcsomagcompany"] = intval($_POST["changeLaborCsomagCompany"]);
            echo $this->showPackages();
            die;
        }

        if (isset($_POST["importCsomagPublicPrice"])) {
            $tid = intval($_POST["importCsomagPublicPrice"]);
            $cid = intval($_POST["cid"]);

            $data = sql_query("select price from synlab_labor_csomagok where id=?", [$tid])->fetch(PDO::FETCH_ASSOC);
            $price = $data["price"];

            if ($cid != 0) {
                sql_query("delete from synlab_labor_arak where tid=? and companyid=?", [$tid, $cid]);
                sql_query("insert into synlab_labor_arak set tipus=?, tid=?, companyid=?, price=?", [self::PRICE_TYPE_PACK, $tid, $cid, $price]);
            }

            echo $price;
            die;
        }

        if(isset($_POST["changeLaborCsomagCompanyShow"])){

            $response = "";

            if($data=sql_query("SELECT * FROM synlab_labor_arak WHERE tid=? AND companyid=?",[$_POST["tid"],$_POST["companyid"]])->fetch(PDO::FETCH_ASSOC)){
                if($data["aktiv"]==1){
                    sql_query("UPDATE synlab_labor_arak SET aktiv=0 WHERE id=?",[$data["id"]])->fetch(PDO::FETCH_ASSOC);
                    $response = "Az árat kikapcsoltuk a cégnél.";
                }else{
                    sql_query("UPDATE synlab_labor_arak SET aktiv=1 WHERE id=?",[$data["id"]])->fetch(PDO::FETCH_ASSOC);
                    $response = "Az árat bekapcsoltuk a cégnél.";
                }
                
            }else{
                sql_query("INSERT INTO synlab_labor_arak SET tipus=1, aktiv=1, companyid=?, tid=?",[$_POST["companyid"],$_POST["tid"]])->fetch(PDO::FETCH_ASSOC);
                $response = "Az árat létrehoztuk és bekapcsoltuk a cégnél.";
            }

            die($response);
        }

        if (isset($_POST["spektrumlabparositas"])) {
            $id = intval($_POST["id"]);
            $spid = intval($_POST["spid"]);

            sql_query("update synlab_labor_tetelek set spid=? where id=?", [$spid, $id]);
            die;
        }

        if (isset($_POST["synlabcodeinput"])) {
            $id = intval($_POST["id"]);
            $code = $_POST["code"];

            sql_query("update synlab_labor_tetelek set commazo=? where id=?", [$code, $id]);
            die;
        }


        if (isset($_GET["fastlist"])) {
            $packs = sql_query("select * from synlab_labor_csomagok p where aktiv=1 order by p.name")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($packs as $pack) {
                echo "<div><strong>{$pack["name"]}</strong></div>";


                $items = json_decode($pack["spektrumitems"]);
                if (empty($items)) {
                    $items = [0];
                }

                $items = sql_query("select * from synlab_labor_tetelek t where id in (".implode(",", $items).") order by t.name")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($items as $item) {
                    echo "<div>{$item["name"]}</div>";
                }



            }
            die;
        }


    }


    private function parositasWindow():string {
        $html = "";

        $existingSpids = [];

        $synlabItems = sql_query("select * from synlab_labor_tetelek where provider='synlab' and appform=1 order by name")->fetchAll(PDO::FETCH_ASSOC);
        $html .= "<div style='display:table-cell;vertical-align: top;padding-right: 20px;'>";
        $html .= "<div style='display:table;'>";
        foreach ($synlabItems as $synlabItem) {
            $existingSpids[] = $synlabItem["spid"];
            $html .= "<div style='display:table-row;'>";
            $html .= "<div style='display:table-cell;vertical-align: middle;'>";
            $html .= "<div>{$synlabItem["name"]}</div>";
            $html .= "</div>";


            $html .= "<div style='display:table-cell;vertical-align: middle;'>";
            $html .= "<select class='spektrumlabparositas' data-id='{$synlabItem["id"]}' name='item{$synlabItem["id"]}' id='item{$synlabItem["id"]}' ".($synlabItem["spid"]==0?"style='background:yellow;'":"").">";
            $html .= "<option value='0'>Válassz!</option>";
            $spectrumLabItems = sql_query("select * from synlab_labor_tetelek where provider='spektrumlab' order by name")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($spectrumLabItems as $spectrumLabItem) {
                $html .= "<option value='{$spectrumLabItem["id"]}'".($synlabItem["spid"] == $spectrumLabItem["id"] ? "selected":"").">{$spectrumLabItem["name"]}</option>";
            }
            $html .= "</select>";
            $html .= "</div>";

            $html .= "</div>";
        }
        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div style='display:table-cell;vertical-align: top;padding-left:20px;border-left:1px solid #ccc;'>";
        $spectrumLabItems = sql_query("select * from synlab_labor_tetelek where provider='spektrumlab' order by name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($spectrumLabItems as $spectrumLabItem) {
            if (in_array($spectrumLabItem["id"], $existingSpids)) {
                continue;
            }
            $html .= "<div>{$spectrumLabItem["name"]}</div>";
        }
        $html .= "</div>";

        return $html;
    }

    private function convertSynlabName($code):string {
        $code = str_replace(" IgE", "", $code);
        $code = str_replace(" IgG", "", $code);
        $code = str_replace("-", "", $code);
        $code = str_replace(" ", "", $code);
        $code = str_replace("/ELISA", "", $code);
        $code = str_replace("/MIF", "", $code);
        $code = str_replace("/CLIA", "", $code);
        $code = str_replace("/ELFA", "", $code);
        $code = str_replace("/CMIA", "", $code);
        $code = str_replace("/IFA", "", $code);
        $code = str_replace("/immunoblot", "", $code);
        $code = str_replace("/recomLine", "", $code);
        return strtolower($code);
    }

    private function synLabCodeEditorWindow():string {
        $html = "<div style='font-size: 18px;margin-bottom: 20px;'>SynLab vizsgálat azonosítók megadása</div>";


        $content = file_get_contents(__DIR__."/synlabkodok.csv");
        $codeTable = [];

        $rows = explode("\n", $content);
        foreach ($rows as $row) {
            $fields = explode(";", $row);
            $kod = $fields[6] ?? "";
            $nev = $fields[8] ?? "";
            if (substr_count($kod, "#") || substr_count($kod, "$")) {
                continue;
            }
            $nev =  $this->convertSynlabName($nev);
            $codeTable[$nev] = $kod;
        }

        $content = iconv("ISO-8859-2", "UTF-8", file_get_contents(__DIR__."/synlabkodokmikro.csv"));

        $rows = explode("\n", $content);
        $lastCode = "";
        foreach ($rows as $row) {
            $fields = explode(";", $row);
            $kod = $fields[0] ?? "";
            $nev = $fields[3] ?? "";
            if (empty($kod)) {
                $kod = $lastCode;
            } else {
                $lastCode = $kod;
            }
            $nev =  $this->convertSynlabName($nev);
            $codeTable[$nev] = $kod;
        }

        $lastAppForm = "";
        $synlabItems = sql_query("select t.*, k.name as kerolap from synlab_labor_tetelek t 
         LEFT JOIN synlab_labor_kerolapok k ON k.id=t.appform 
         where provider='synlab' order by appform=0, appform, name")->fetchAll(PDO::FETCH_ASSOC);
        $html .= "<div style='display:table-cell;vertical-align: top;padding-right: 20px;'>";
        $html .= "<div style='display:table;'>";
        foreach ($synlabItems as $synlabItem) {
            if ($lastAppForm != $synlabItem["kerolap"]) {
                $lastAppForm = $synlabItem["kerolap"];
                $html .= "<div style='display:table-row;'>";
                if (empty($synlabItem["kerolap"])) {
                    $synlabItem["kerolap"] = "Nem kategorizált";
                }
                $html .= "<div style='display:table-cell;font-weight:bold;margin-bottom:6px;padding-bottom:3px;margin-top:6px;padding-top:3px;margin-bottom:5px;border-bottom:1px solid #ccc;border-top:1px solid #ccc;font-size: 16px;'>{$synlabItem["kerolap"]}</div>";
                $html .= "<div style='display:table-cell;font-weight:bold;margin-bottom:6px;padding-bottom:3px;margin-top:6px;padding-top:3px;border-bottom:1px solid #ccc;border-top:1px solid #ccc;font-size: 16px;'></div>";
                $html .= "</div>";
                $html .= "<div style='display:table-row;height:5px;'>";
                $html .= "</div>";
            }

            $html .= "<div style='display:table-row;'>";
            $html .= "<div style='display:table-cell;vertical-align: middle;'>";
            $html .= "<div>{$synlabItem["name"]}</div>";
            $html .= "</div>";

            $commAzo = $synlabItem["commazo"];
            if (empty($commAzo)) {
                $name = $this->convertSynlabName($synlabItem["name"]);
                if (isset($codeTable[$name])) {
                    $commAzo = $codeTable[$name];
                    sql_query("update synlab_labor_tetelek set commazo=? where id=? limit 1", [$commAzo, $synlabItem["id"]]);
                }
            }

            $html .= "<div style='display:table-cell;vertical-align: middle;'>";
            $html .= "<input class='synlabcommcodeinput' type='text' data-id='{$synlabItem["id"]}' name='itemcode{$synlabItem["id"]}' id='itemcode{$synlabItem["id"]}' value='{$commAzo}' />";
            $html .= "</div>";

            $html .= "</div>";
        }
        $html .= "</div>";
        $html .= "</div>";


        return $html;
    }

    public function showPage()
    {

        if (!$this->adminUser->labortetelAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        if (isset($_GET["parositas"])) {
            echo $this->parositasWindow();
            return;
        }

        if (isset($_GET["synlabcodeeditor"])) {
            echo $this->synLabCodeEditorWindow();
            return;
        }

        if (!isset($_GET["szerk"])) {
            echo "<div id='labortetelek-form'>";
            echo $this->showPackages();
            echo "</div>";

            //Tételek:
            echo $this->showItemsNew();

            echo "</form>";
        } else {
            //Csomag szerkesztés:
            echo "<form id=\"package-editor-form\" method=\"POST\">";

            $packageData = sql_fetch_array(sql_query("SELECT slc.*,slk.name AS kerolap FROM synlab_labor_csomagok slc 
                                                      LEFT JOIN synlab_labor_kerolapok slk ON slk.id=slc.appform
                                                      WHERE slc.id=?", array($_GET["szerk"])));

            //Ha még a session érték nincsen letárolva, akkor a következő
            if (!isset($_SESSION["packageItems"])) {
                $_SESSION["packageItems"] = $packageData["items"];
            } else {
                $packageData["items"] = $_SESSION["packageItems"];
            }

            //Alap adatok megadása:
            echo "<table>";
            echo "<tr><td colspan='2'><input type='submit' name='savePackage' value='Mentés'></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Csomag megnevezése (public): </p></td><td><input type=\"textbox\" name=\"name\" style=\"min-width:300px;height:25px\" value=\"{$packageData["name"]}\"></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Csomag megnevezése (házon belül): </p></td><td><input type=\"textbox\" name=\"hmm_name\" style=\"min-width:300px;height:25px\" value=\"{$packageData["hmm_name"]}\"></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Kérőlap: </p></td><td><input type=\"textbox\" disabled=\"true\" name=\"appform\" style=\"min-width:300px;height:25px\" value=\"{$packageData["kerolap"]}\"></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Alias: </p></td><td><input type=\"textbox\" name=\"alias\" style=\"min-width:300px;height:25px\" value=\"{$packageData["alias"]}\"></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Áthúzott ár: </p></td><td><input type=\"textbox\" name=\"line-through-price\" style=\"min-width:300px;height:25px\" value=\"{$packageData["line_through_price"]}\"></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Ár: </p></td><td><input type=\"textbox\" name=\"price\" style=\"min-width:300px;height:25px\" value=\"{$packageData["price"]}\"></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Nemi beállítás: </p></td><td><input type=\"radio\" " . ($packageData["gender"] == "female" ? "checked" : "") . " name=\"gender\" value=\"female\">Női&nbsp;<input type=\"radio\" " . ($packageData["gender"] == "male" ? "checked" : "") . " name=\"gender\" value=\"male\">Férfi&nbsp;<input type=\"radio\" " . ($packageData["gender"] == "both" ? "checked" : "") . " name=\"gender\" value=\"both\">Mind2</td></tr>";

            echo "<tr><td style=\"vertical-align:top\"><p style=\"font-weight:bold;font-size:14px\">Csomag kategóriák: </p></td><td>" . $this->package_category_list($packageData) . "</td></tr>";

            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Státusz: </p></td><td>";
            echo "<select name=\"aktiv\"><option " . ($packageData["aktiv"] == 1 ? "selected=\"true\"" : "") . " value=\"1\">Aktív</option><option " . ($packageData["aktiv"] == 0 ? "selected=\"true\"" : "") . " value=\"0\">Inaktív</option></select>&nbsp;&nbsp;";

            echo "<input type='checkbox' name='kiemelt'" . ($packageData["kiemelt"]  == 1 ? "checked" : "") . " value='1' /> kiemelt";

            echo "</td></tr>";

            $docAgent = new DocAgent();
            echo "<tr><td style='font-weight:bold;font-size:14px'><div>Kép:</div></td><td><div id='asseteditor'>" . $docAgent->showAssetEditor(DocAgent::ASSET_LABOR_CSOMAG_IMAGE, $_GET["szerk"]) . "</div></td></tr>";

            echo "<tr><td colspan='2'><div class='tdsepdiv'>Leírás</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><div id='desceditor' style='margin-bottom:20px;'>";
            echo "<textarea class='mce' name='description' style='width:1000px;height:600px;'>{$packageData["description"]}</textarea>";
            echo "</div></td></tr>";

            echo "</table>";

            echo "<div id='itemeditordiv' style='min-height:1000px;'>";
            if (Booking_Constants::SQL_DB == "keltexmed") {
                echo $this->showItemChecker($packageData["id"]);
            } else {
                echo $this->showItemsNew($packageData["id"]);
            }
            echo "</div>";

            echo "</form>";
        }
    }

    private function package_category_list($packageData)
    {

        $html = "";

        $categories = json_decode($packageData["categories"]);

        $qCategories = sql_query("SELECT * FROM synlab_labor_csomag_kategoriak");

        while ($category = sql_fetch_array($qCategories)) {

            if (!empty($categories) && in_array($category["id"], $categories)) {
                $checked = "checked=\"checked\"";
            } else {
                $checked = "";
            }

            $html .= "<input type=\"checkbox\" {$checked} name=\"package-category-{$category["id"]}\">&nbsp;{$category["name"]}<br>";
        }

        return $html;
    }

    private function setCategories()
    {
        $qCategories = sql_query("SELECT * FROM synlab_labor_csomag_kategoriak");
        $array = array();
        while ($category = sql_fetch_array($qCategories)) {
            if (isset($_POST["package-category-{$category["id"]}"])) {
                $array[] = $category["id"];
            }
        }
        return json_encode($array);
    }

    private function showPackages():string {
        $html = "";
        $companyId = $_SESSION["selectedcsomagcompany"];

        $priceMap = [];
        $prices = sql_query("SELECT * FROM synlab_labor_arak a WHERE a.`companyid`=?", [$companyId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($prices as $price) {
            $priceMap[$price["tid"]] = $price;
        }

        //Le kell kérdeznem a csomagokat:
        $rq = sql_query("SELECT slc.*,slk.name AS kerolap FROM synlab_labor_csomagok slc
                         LEFT JOIN synlab_labor_kerolapok slk ON slk.id=slc.appform
                         ORDER BY slc.aktiv desc, name ASC");

        $qf = sql_query("SELECT id,name FROM synlab_labor_kerolapok ORDER BY name ASC");

        $formFilter = "<select name=\"formFilterInPackages\">";
        $formFilter .= "<option value=\"*\">Összes</option>";
        while ($resf = sql_fetch_array($qf)) {
            $formFilter .= "<option " . (!empty($appform) && $appform == $resf["id"] ? "selected" : null) . " value=\"{$resf["id"]}\">{$resf["name"]}</option>";
        }
        $formFilter .= "</select>&nbsp;<input type=\"submit\" value=\"Kérőlap szűrés\" name=\"filterbyform\">";

        $html.= "<table cellpadding='0' cellspacing='0' border='0' style=''>";
        $html.= "<tr><td colspan='3' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:16px'>Csomagok</td>";

        $html.= "<td colspan='1' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:18px;text-align:center;'>Árak<br/>";
        $html.= "<select id='companycsomag' style='width:300px;'>";
        $html.= "<option value='0'>Publikus ár</option>";
        $companies = sql_query("select id, megnev from cegek order by megnev")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($companies as $company) {
            $html.= "<option ".($_SESSION["selectedcsomagcompany"] == $company["id"] ? "selected":"")." value='{$company["id"]}'>{$company["megnev"]}</option>";
        }
        $html.= "</select>";
        $html.= "</td>";
        $html.= "</tr>";

        while ($resq = sql_fetch_array($rq)) {
            $items = json_decode($resq["spektrumitems"]);
            if (empty($items)) {
                $items = [];
            }



            $itemTexts = [];
            if (!empty($items)) {
                $items = sql_query("select * from synlab_labor_tetelek t where id in (" . implode(",", $items) . ") order by t.name")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($items as $item) {
                    $itemTexts[] = $item["name"];
                    //echo "<div>{$item["name"]}</div>";
                }
            }


            if ($resq["aktiv"] == 0 && !isset($separator)) {
                $separator = true;
                $html.= "<tr><td colspan='20'><div style='border-top:1px solid #888;padding-top: 10px;margin-top: 10px;font-weight: bold;'>Inaktív csomagok</div><div style='border-top:1px solid #888;padding-top: 10px;margin-top: 10px;'></div></td></tr>";
            }

            $html.= "<tr>";

            //Link a szerkesztéshez:
            $html.= "<td valign=\"top\"><div class=\"tcella\">";
            $html.= "<a style=\"#00f;\" href=\"index.php?page=labortetelek&szerk={$resq["id"]}\">".(!empty($resq["hmm_name"])?$resq["hmm_name"]:$resq["name"])."&nbsp;(" . count($items) . ")</a>";
            $html.= "</div></td>";

            //Kérőlap megnevezés:
            $html.= "<td nowrap valign=\"top\"><div class=\"tcella\" style=\"min-width:10px;\">{$resq["kerolap"]}</div></td>";

            //Aktiválás/Deaktiválás:
            $html.= "<td nowrap valign=\"top\"><div class=\"tcella\" style=\"min-width:10px;\">";
            if ($resq["aktiv"] == 1) {
                $html.= "<a href=\"index.php?page=labortetelek&packaktivetoggle={$resq["id"]}\" style=\"color:#0a0;\">aktív</a>";
            } else {
                $html.= "<a href=\"index.php?page=labortetelek&packaktivetoggle={$resq["id"]}\" style=\"color:#f00;\">inaktív</a>";
            }

            $html.= "</div></td>";

            //Forint alapú ár:
            $price = $resq["price"];
            if ($companyId != 0) {
                $price = 0;
                if (isset($priceMap[$resq["id"]])) {
                    $price = $priceMap[$resq["id"]]["price"];
                }
            }

            $html.= "<td nowrap valign='top' vertical-align='middle' ><div class='tcella' style='min-width:10px;text-align:center;'>";

            if($companyId != 0){
                $checked = "";
                if (isset($priceMap[$resq["id"]]) && $priceMap[$resq["id"]]["aktiv"]==1) {
                    $checked = "checked=\"true\"";
                }
                $html .= "<input type=\"checkbox\" {$checked} data-tid='{$resq["id"]}' data-companyid='{$companyId}' title=\"Megjelenítés céges webshopban\" class='cegesarcheckbox' onChange='changeLaborCsomagCompanyShow(\"#cegescsomagprice{$resq["id"]}\")' id=\"cegescsomagprice{$resq["id"]}\" value=\"1\">&nbsp;&nbsp;";
            }

            if ($companyId != 0) {
                $html .= "<a title='Publikus ár beemelése' onclick='importCsomapPublicPrice({$companyId}, {$resq["id"]});return false;' href='#'><i class='fa-solid fa-right-to-bracket'></i></a>&nbsp;&nbsp;";
            }

            $html.= "<input id='csomagprice{$resq["id"]}' data-cid='{$companyId}' data-tid='{$resq["id"]}' class='laborcsomagpricetextbox' type='textbox' style='width:80px;text-align:center' value='{$price}' /> HUF";
            $html.= "</div></td>";

            $html.= "</div></td>";
            $html.= "</tr>";

            //if (!empty($itemTexts)) {
                $html.= "<tr><td colspan='10'><div style='max-width: 800px;border-bottom:1px solid #ccc;padding-bottom: 5px;'>".implode(", ", $itemTexts)."</div></td></tr>";
            //}

        }

        $html.= "</table>";
        return $html;
    }

    private function showItemsNew($packageId = 0):string {
        $html = "";
        $listView = $packageId == 0;

        $packageData = sql_query("select * from synlab_labor_csomagok cs where cs.id=?", [$packageId])->fetch(PDO::FETCH_ASSOC);

        //Csomag szerkesztésekor dekódolom a küldött json-t, hogy megvizsgálhassam.
        if (!empty($packageData["items"])) {
            $packageInstall = json_decode($packageData["items"]);
            $packageInstallSpektrum = json_decode($packageData["spektrumitems"]);
            $strPackageItems = "FIELD(slt.id," . implode(",", $packageInstall) . ") DESC,";
            $strPackageItemsSpektrum = "slt.id in (" . implode(",", $packageInstallSpektrum) . ") DESC,";
        }

        //Le kell kérdeznem a tételeket:
        $rq = sql_query("SELECT slt.*,sltk.name AS category_name,slk.name AS kerolap, t2.name AS spname FROM synlab_labor_tetelek slt
                         LEFT JOIN synlab_labor_tetel_kategoriak sltk ON sltk.id=slt.category
                         LEFT JOIN synlab_labor_kerolapok slk ON slk.id=slt.appform
                         LEFT JOIN synlab_labor_tetelek t2 ON t2.id=slt.spid
                         WHERE slt.provider='spektrumlab' " . (!empty($filterId) ? "AND category = {$filterId}" : "") . " " . (!empty($appform) ? "AND appform={$appform}" : "") . "
                         ORDER BY " . (!empty($packageInstall) ? $strPackageItems : "") . " sltk.name, slt.name ASC");

        if ($listView) {
            $lastCategory = "null";

            $html .= "<table cellpadding='0' cellspacing='0' border='0' style='' id='LaborItems'>";

            $html .= "<tbody id='item-content'>";
            while ($resq = sql_fetch_array($rq)) {
                if (empty($resq["category_name"])) {
                    $resq["category_name"] = "Nem kategorizált vizsgálatok";
                }

                if ($lastCategory != $resq["category_name"]) {
                    $lastCategory = $resq["category_name"];

                    $html .= "<tr><td colspan='10' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:22px;text-align: center;'>{$resq["category_name"]}</td></tr>";
                    $html .= "<tr><td colspan='3' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:16px'>Tételek</td>";
                    $html .= "<td colspan='1' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:18px;text-align:center;'>Elkészülés (munkanap)</td>";
                    $html .= "<td colspan='1' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:18px;text-align:center;'>Árak</td>";
                    $html .= "</tr>";
                }
                $html .= "<tr>";
                $html .= "<td valign='top'><div class='tcella' style=''>";
                if (!$listView) {
                    $html .= "<input data-csomagid='{$packageId}' data-itemid='{$resq["id"]}' class='csitemcheckbox' type='checkbox' name='item[]' " . (!empty($packageInstall) && in_array($resq["id"], $packageInstall) ? "checked" : "") . " value=\"{$resq["id"]}\">&nbsp;";
                }
                $html .= "<span title='{$resq["name"]}'>".mb_substr($resq["name"], 0, 50)."</span>".(empty($resq["spname"]) ? "":"<br/><span style='font-size: 12px;color:#888;'>{$resq["spname"]}</span>");
                $html .= "</div></td>";

                //Kérőlap megnevezés:
                //$html .= "<td nowrap valign='top'><div class='tcella' style='min-width:10px'>{$resq["kerolap"]}</div></td>";

                //Kategória megnevezés:
                $html .= "<td nowrap valign='top'><div class='tcella' style='min-width:10px' id='categoryid{$resq["id"]}'>".$this->showCategorySelector($resq["id"], $resq)."</div></td>";

                //Minta vételi cső:
                $html .= "<td nowrap valign='top'><div class='tcella' style='min-width:10px'>" . (!empty($resq["sample_tube"]) ? $this->tubes[$resq["sample_tube"]]["title"] : "") . "</div></td>";
                $html .= "<td nowrap valign='top'><div class='tcella' style='min-width:10px;text-align:center;font-weight:bold'><input data-tid='{$resq["id"]}' class='laboritemelkeszulestextbox' type='textbox' style='width:80px;text-align:center' value='{$resq["elkeszules"]}'></div></td>";
                $html .= "<td nowrap valign='top'><div class='tcella' style='min-width:10px;text-align:center;font-weight:bold'><input data-tid='{$resq["id"]}' class='laboritempricetextbox' type='textbox' style='width:80px;text-align:center' value='{$resq["price"]}'> HUF</div></td>";
                $html .= "</tr>";
            }

            $html .= "</tbody>";
            $html .= "</table>";
        } else {
            $html .= "<div style='display:table-cell;vertical-align:top;'>";
            $html .= "<table cellpadding='0' cellspacing='0' border='0' style='' id='LaborItems'>";
            $html .= "<tr><td colspan='4' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:16px'>Synlab tételek</td>";
            $html .= "</tr>";

            $html .= "<tbody id='item-content'>";
            while ($resq = sql_fetch_array($rq)) {
                $html .= "<tr>";
                $html .= "<td valign='top'><div class='tcella' style=''>";
                $html .= "<input data-csomagid='{$packageId}' data-itemid='{$resq["id"]}' class='csitemcheckbox' type='checkbox' name='item[]' " . (!empty($packageInstall) && in_array($resq["id"], $packageInstall) ? "checked" : "") . " value=\"{$resq["id"]}\">&nbsp;";
                $html .= mb_substr($resq["name"], 0, 50);
                $html .= "</div></td>";

                //Kérőlap megnevezés:
                $html .= "<td nowrap valign='top'><div class='tcella' style='min-width:10px'>{$resq["kerolap"]}</div></td>";

                $html .= "</tr>";
            }

            $html .= "</tbody>";
            $html .= "</table>";
            $html .= "</div>";

            $rq = sql_query("SELECT slt.* FROM synlab_labor_tetelek slt 
                         WHERE slt.provider='spektrumlab' " . (!empty($filterId) ? "AND category = {$filterId}" : "") . " " . (!empty($appform) ? "AND appform={$appform}" : "") . "
                         ORDER BY " . (!empty($packageInstallSpektrum) ? $strPackageItemsSpektrum : "") . " slt.name ASC")->fetchAll(PDO::FETCH_ASSOC);


            $html .= "<div style='display:table-cell;width:20px;vertical-align:top;'></div>";
            $html .= "<div style='display:table-cell;width:20px;vertical-align:top;border-left:1px solid #ccc;'></div>";

            $html .= "<div style='display:table-cell;vertical-align:top;'>";
            $html .= "<table cellpadding='0' cellspacing='0' border='0' style='' id='LaborItems2'>";
            $html .= "<tr><td colspan='4' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:16px'>SpektrumLab tételek</td>";
            $html .= "</tr>";

            if (Booking_Constants::SQL_DB == "keltexmed__") {
                $html .= "<div id='item-content-spektrumlab'>";
                $html .= $this->showItemChecker();
                $html .= "</div>";

            } else {
                $html .= "<tbody id='item-content'>";
                foreach ($rq as $resq) {
                    $html .= "<tr>";
                    $html .= "<td valign='top'><div class='tcella' style=''>";
                    $html .= "<input data-csomagid='{$packageId}' data-itemid='{$resq["id"]}' class='csitemcheckbox' type='checkbox' name='item[]' " . (!empty($packageInstallSpektrum) && in_array($resq["id"], $packageInstallSpektrum) ? "checked" : "") . " value=\"{$resq["id"]}\">&nbsp;";
                    $html .= mb_substr($resq["name"], 0, 50);
                    $html .= "</div></td>";

                    //Kérőlap megnevezés:
                    $html .= "<td nowrap valign='top'><div class='tcella' style='min-width:10px'>{$resq["kod"]}</div></td>";

                    $html .= "</tr>";
                }
            }

            $html .= "</tbody>";
            $html .= "</table>";
            $html .= "</div>";


        }
        return $html;
    }


    private function showItemChecker($packageId):string {
        $html = "";

        $packageInstall = $packageInstallSpektrum = [];
        $strPackageItems = $strPackageItemsSpektrum = "";
        $packageData = sql_query("select * from synlab_labor_csomagok cs where cs.id=?", [$packageId])->fetch(PDO::FETCH_ASSOC);
        if (!empty($packageData["items"])) {
            $packageInstall = json_decode($packageData["items"]);
            $packageInstallSpektrum = json_decode($packageData["spektrumitems"]);
            $strPackageItems = "FIELD(slt.id," . implode(",", $packageInstall) . ") DESC,";
            $strPackageItemsSpektrum = "slt.id in (" . implode(",", $packageInstallSpektrum) . ") DESC,";
        }

        //synlab items
        /*
        $rq = sql_query("SELECT slt.*,sltk.name AS category_name,slk.name AS kerolap, t2.name AS spname FROM synlab_labor_tetelek slt
                         LEFT JOIN synlab_labor_tetel_kategoriak sltk ON sltk.id=slt.category
                         LEFT JOIN synlab_labor_kerolapok slk ON slk.id=slt.appform
                         LEFT JOIN synlab_labor_tetelek t2 ON t2.id=slt.spid
                         WHERE slt.provider='synlab' " . (!empty($filterId) ? "AND category = {$filterId}" : "") . " " . (!empty($appform) ? "AND appform={$appform}" : "") . "
                         ORDER BY " . (!empty($packageInstall) ? $strPackageItems : "") . " sltk.name, slt.name ASC");


        $html .= "<div style='display:table-cell;vertical-align:top;'>";
        $html .= "<div style='font-size: 18px;font-weight: bold;margin-bottom: 10px;'>Synlab tételek (".count($packageInstall)." tétel)</div>";

        $html .= "<div id='item-content-synlab'>";
        while ($resq = sql_fetch_array($rq)) {
            $name = mb_substr($resq["name"], 0, 50);
            $class = (!empty($packageInstall) && in_array($resq["id"], $packageInstall) ? "serviceselected" : "servicenotselected");
            $html.= "<a data-provider='synlab' data-csomagid='{$packageId}' data-itemid='{$resq["id"]}' title='' class='{$class} csitemcheckbox2' style='' href='#'>{$name}</a> ";

            //$html .= "<input data-csomagid='{$packageId}' data-itemid='{$resq["id"]}' class='csitemcheckbox' type='checkbox' name='item[]' " . (!empty($packageInstall) && in_array($resq["id"], $packageInstall) ? "checked" : "") . " value=\"{$resq["id"]}\">&nbsp;";
            //$html .= mb_substr($resq["name"], 0, 50);
        }

        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div style='display:table-cell;width:20px;vertical-align:top;'></div>";
        $html .= "<div style='display:table-cell;width:20px;vertical-align:top;border-left:1px solid #ccc;'></div>";
        */

        $rq = sql_query("SELECT slt.* FROM synlab_labor_tetelek slt 
                         WHERE slt.provider='spektrumlab' " . (!empty($filterId) ? "AND category = {$filterId}" : "") . " " . (!empty($appform) ? "AND appform={$appform}" : "") . "
                         ORDER BY " . (!empty($packageInstallSpektrum) ? $strPackageItemsSpektrum : "") . " slt.name ASC")->fetchAll(PDO::FETCH_ASSOC);

        $html .= "<div style='display:table-cell;vertical-align:top;'>";
        $html .= "<div style='font-size: 18px;font-weight: bold;margin-bottom: 10px;'>Spektrumlab tételek (".count($packageInstallSpektrum)." tétel)</div>";

        $html.= "<div style='margin-bottom: 10px;'><input id='csomagVizsgalatFilterText' type='text' placeholder='keresés..' /></div>";

        $html .= "<div id='item-content-spektrumlab'>";
        foreach ($rq as $resq) {
            $name = mb_substr($resq["name"], 0, 50);
            $class = (!empty($packageInstallSpektrum) && in_array($resq["id"], $packageInstallSpektrum) ? "serviceselected" : "servicenotselected");
            $html.= "<a data-provider='spektrumlab' data-csomagid='{$packageId}' data-itemid='{$resq["id"]}' title='' class='{$class} csitemcheckbox2' style='' href='#'>{$name}</a> ";

            //$html .= "<input data-csomagid='{$packageId}' data-itemid='{$resq["id"]}' class='csitemcheckbox' type='checkbox' name='item[]' " . (!empty($packageInstallSpektrum) && in_array($resq["id"], $packageInstallSpektrum) ? "checked" : "") . " value=\"{$resq["id"]}\">&nbsp;";
            //$html .= mb_substr($resq["name"], 0, 50);
        }

        $html .= "</div>";
        $html .= "</table>";
        $html .= "</div>";

        return $html;
    }

    private function showCategorySelector($id, $data):string {
        if (!$categoryData = sql_query_common("select name from synlab_labor_tetel_kategoriak where id=?", [$data["category"]])->fetch(PDO::FETCH_ASSOC)) {
            $categoryData["name"] = "Nem kategorizált vizsgálatok";
        }

        return "<span onclick='showLaborCategorySelect(\"{$id}\", \"{$data["category"]}\");' style='border:1px solid #ccc;padding:2px 4px;cursor:pointer;'>{$categoryData["name"]}</span>";
    }

}
