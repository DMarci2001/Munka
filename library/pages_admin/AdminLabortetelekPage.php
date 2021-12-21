<?php

class AdminLabortetelekPage extends AdminCorePage
{
    //Tétel kategória filter:
    private $cFilter;
    //Kérőlap filter:
    private $formFilter;
    //Tétel árak módosításának jelölés változója:
    private $setItemPrices;
    //Csomag árak módosításának jelölés változója:
    private $setPackagePrices;
    //Csomag azonosító:
    private $packageId;
    private $packageItems;
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

    public function __construct()
    {

        //Ha van csomagazonosító, akkor állítcsa be sessionbe az értéket:
        if (isset($_GET["szerk"])) {
            if (!empty($_GET["szerk"]) && $_GET["szerk"] != "*") {
                $_SESSION["packageId"] = $_GET["szerk"];
            } else {
                unset($_SESSION["packageId"]);
            }
        }

        //Ha van csomag azonosító session, tegybe be class változóba is:
        if (isset($_SESSION["packageId"])) {
            $this->packageId = $_SESSION["packageId"];
        } else {
            $this->packageId = null;
        }

        //Ha létezik a kiválaszott csomag json értéke, akkor azt helyezze el a class változóban is:
        if (isset($_SESSION["packageItems"])) {
            $this->packageItems = $_SESSION["packageItems"];
        } else {
            $this->packageItems = null;
        }

        if (!isset($_REQUEST["szerk"])) {
            unset($_SESSION["packageId"]);
            unset($_SESSION["packageItems"]);
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
        }

        //Kategórai szerinti szűrés session beállítása:
        if (isset($_POST["filterbycategory"])) {
            if (!empty($_POST["cFilter"]) && $_POST["cFilter"] != "*") {
                $_SESSION["cFilter"] = $_POST["cFilter"];
            } else {
                unset($_SESSION["cFilter"]);
            }
        }
        //Kategória szerinti szűrés class változó beállítása:
        if (isset($_SESSION["cFilter"])) {
            $this->cFilter = $_SESSION["cFilter"];
        } else {
            $this->cFilter = null;
        }

        //Kérőlap szerinti szűrés beállítása
        if (isset($_POST["filterbyform"])) {
            if (!empty($_POST["formFilter"]) && $_POST["formFilter"] != "*") {
                $_SESSION["formFilter"] = $_POST["formFilter"];
            } else {
                unset($_SESSION["formFilter"]);
                unset($_SESSION["cFilter"]);
            }
        }

        //Kérőlap szerinti szűrés class változó beállítása:
        if (isset($_SESSION["formFilter"])) {
            $this->formFilter = $_SESSION["formFilter"];
        } else {
            $this->formFilter = null;
        }

        //Tétel árak módosításának toggle kezelője:
        if (isset($_POST["setItemPrices"])) {
            if ($_POST["setItemPrices"] == "true") {
                $this->setItemPrices = true;
            } else {
                $this->setItemPrices = false;
            }
        }

        //Csomag árak módosításának toggle kezelője:
        if (isset($_POST["setPackagePrices"])) {
            if ($_POST["setPackagePrices"] == "true") {
                $this->setPackagePrices = true;
            } else {
                $this->setPackagePrices = false;
            }
        }

        //Tétel árak mentése:
        if (isset($_POST["saveItemPrices"]) && $_POST["saveItemPrices"] == true) {
            foreach ($_POST as $index => $value) {
                if (strpos($index, "item-") !== false) {
                    $index = explode("-", $index);
                    $id = $index[1];

                    sql_query("UPDATE synlab_labor_tetelek SET price=? WHERE id=?", array($value, $id));
                }
            }
        }

        //Csomag árak mentése:
        if (isset($_POST["savePackagePrices"])) {
            foreach ($_POST as $index => $value) {
                if (strpos($index, "package-") !== false) {
                    $index = explode("-", $index);
                    $id = $index[1];

                    sql_query("UPDATE synlab_labor_csomagok SET price=? WHERE id=?", array($value, $id));
                }
            }
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
                             WHERE TRUE " . (!empty($this->cFilter) ? "AND category = {$this->cFilter}" : "") . " " . (!empty($appform) ? "AND appform={$appform}" : "") . " " . (!empty($_POST["keyword"]) ? "AND slt.name LIKE '%" . $_POST["keyword"] . "%' " : "") . "
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

            $items = json_decode($this->packageItems);
            $key = array_search($_POST["id"], $items);

            if ($key !== false) {
                unset($items[$key]);
                $items = array_values($items);

                $this->packageItems = $_SESSION["packageItems"] = json_encode($items, true);
            } else {
                array_push($items, $_POST["id"]);
                $this->packageItems = $_SESSION["packageItems"] = json_encode($items, true);
            }

            die();
        }

        //Csomag mentése:
        if (isset($_POST["savePackage"])) {
            sql_query(
                "UPDATE synlab_labor_csomagok SET name=?, price=?, items=?, aktiv=? WHERE id=?",
                array($_POST["name"], $_POST["price"], $this->packageItems, $_POST["aktiv"], $this->packageId)
            );
        }
    }

    public function showPage()
    {

        if (!isset($_GET["szerk"])) {
            echo "<form id=\"labortetelek-form\" method=\"POST\">";
            //Csomagok:
            echo $this->showPackages();

            //Tételek:
            echo $this->showItems($this->cFilter, $this->formFilter);

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
            echo "<tr><td colspan=\"2\"><input type=\"submit\" name=\"savePackage\" onClick='if(!confirm(\"Biztosan elakarod menteni a módosításokat?\")){return false;}' value=\"Mentés\"></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Csomag megnevezése: </p></td><td><input type=\"textbox\" name=\"name\" style=\"min-width:300px;height:25px\" value=\"{$packageData["name"]}\"></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Kérőlap: </p></td><td><input type=\"textbox\" disabled=\"true\" name=\"appform\" style=\"min-width:300px;height:25px\" value=\"{$packageData["kerolap"]}\"></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Ár: </p></td><td><input type=\"textbox\" name=\"price\" style=\"min-width:300px;height:25px\" value=\"{$packageData["price"]}\"></td></tr>";
            echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Státusz: </p></td><td><select name=\"aktiv\"><option " . ($packageData["aktiv"] == 1 ? "selected=\"true\"" : "") . " value=\"1\">Aktív</option><option " . ($packageData["aktiv"] == 0 ? "selected=\"true\"" : "") . " value=\"0\">Inaktív</option></select></td></tr>";
            echo "</table>";

            echo $this->showItems($this->cFilter, $packageData["appform"], $packageData["items"]);
            echo "</form>";
        }
    }

    private function showPackages()
    {
        //Le kell kérdeznem a csomagokat:
        $rq = sql_query("SELECT slc.*,slk.name AS kerolap FROM synlab_labor_csomagok slc
                         LEFT JOIN synlab_labor_kerolapok slk ON slk.id=slc.appform
                         ORDER BY name ASC");



        $qf = sql_query("SELECT id,name FROM synlab_labor_kerolapok ORDER BY name ASC");

        $formFilter = "<select name=\"formFilterInPackages\">";
        $formFilter .= "<option value=\"*\">Összes</option>";
        while ($resf = sql_fetch_array($qf)) {
            $formFilter .= "<option " . (!empty($appform) && $appform == $resf["id"] ? "selected" : null) . " value=\"{$resf["id"]}\">{$resf["name"]}</option>";
        }
        $formFilter .= "</select>&nbsp;<input type=\"submit\" value=\"Kérőlap szűrés\" name=\"filterbyform\">";

        echo "<table cellpadding='0' cellspacing='0' border='0' style='width:100%'>";
        echo "<tr><td colspan='3' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:16px'>Csomagok</td>";

        $setPackagePricesButton = "onClick=\"$('#labortetelek-form').append($('<input>').attr('type','hidden').attr('name','setPackagePrices').val('true')).submit()\"";
        $cancelPackagePricesButton = "onClick=\"$('#labortetelek-form').append($('<input>').attr('type','hidden').attr('name','setPackagePrices').val('false')).submit()\"";
        $savePackagePricesButton = "onClick=\"$('#labortetelek-form').append($('<input>').attr('type','hidden').attr('name','savePackagePrices').val('false'),$('<input>').attr('type','hidden').attr('name','savePackagePrices').val('true')).submit()\"";

        if ($this->setPackagePrices == false) {
            echo "<td colspan=\"1\" style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:18px;text-align:right;padding-right:60px'><i class=\"fas fa-cog\" style=\"cursor:pointer\" {$setPackagePricesButton}></i></td>";
        } else {
            echo "<td colspan=\"1\" style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:18px;text-align:right;padding-right:80px'><i class=\"fas fa-save\" style=\"cursor:pointer\" {$savePackagePricesButton}></i>&nbsp;&nbsp;<i class=\"fas fa-times\" style=\"cursor:pointer\" {$cancelPackagePricesButton}></i></td>";
        }

        echo "</tr>";

        while ($resq = sql_fetch_array($rq)) {

            $items = json_decode($resq["items"]);

            echo "<tr>";

            //Link a szerkesztéshez:
            echo "<td valign=\"top\"><div class=\"tcella\">";
            echo "<a style=\"#00f;\" href=\"index.php?page=labortetelek&szerk={$resq["id"]}\">{$resq["name"]}&nbsp;(" . count($items) . ")</a>";
            echo "</div></td>";

            //Kérőlap megnevezés:
            echo "<td nowrap valign=\"top\"><div class=\"tcella\" style=\"min-width:10px;\">{$resq["kerolap"]}</div></td>";

            //Aktiválás/Deaktiválás:
            echo "<td nowrap valign=\"top\"><div class=\"tcella\" style=\"min-width:10px;\">";
            if ($resq["aktiv"] == 1) {
                echo "<a href=\"index.php?page=labortetelek&packaktivetoggle={$resq["id"]}\" style=\"color:#0a0;\">aktív</a>";
            } else {
                echo "<a href=\"index.php?page=labortetelek&packaktivetoggle={$resq["id"]}\" style=\"color:#f00;\">inaktív</a>";
            }

            //Forint alapú ár:
            echo "<td nowrap valign=\"top\" ><div class=\"tcella\" style=\"min-width:10px;padding-right:30px;text-align:right;font-weight:bold\">" . ($this->setPackagePrices == true ? "<input type=\"textbox\" style=\"width:80px;text-align:center\" name=\"package-{$resq["id"]}-price\" value=\"{$resq["price"]}\">" : number_format($resq["price"])) . " HUF</div></td>";

            echo "</div></td>";
            echo "</tr>";
        }





        echo "</table>";
        return;
    }

    private function showItems($filterId = null, $appform = null, $packageInstall = null)
    {
        //Ha az appform-ot nem választották ki v. nullára állították akkor resetelje a kategória filtert is.
        if ($appform == null) $filterId = null;

        //Csomag szerkesztésekor dekódolom a küldött json-t, hogy megvizsgálhassam.                 
        if (!empty($packageInstall)) {
            $packageInstall = json_decode($packageInstall);
            $strPackageItems = "FIELD(slt.id," . implode(",", $packageInstall) . ") DESC,";
        }

        //Le kell kérdeznem a tételeket:
        $rq = sql_query("SELECT slt.*,sltk.name AS category_name,slk.name AS kerolap FROM synlab_labor_tetelek slt
                         LEFT JOIN synlab_labor_tetel_kategoriak sltk ON sltk.id=slt.category
                         LEFT JOIN synlab_labor_kerolapok slk ON slk.id=slt.appform
                         WHERE TRUE " . (!empty($filterId) ? "AND category = {$filterId}" : "") . " " . (!empty($appform) ? "AND appform={$appform}" : "") . "
                         ORDER BY " . (!empty($packageInstall) ? $strPackageItems : "") . " sltk.name, slt.name ASC");

        //Filterezések:
        $cFilter = $formFilter = "";

        $qf = sql_query("SELECT id, name FROM synlab_labor_kerolapok ORDER BY name ASC");

        $formFilter = "<select name=\"formFilter\" " . (!empty($packageInstall) ? "style=\"display:none\"" : "") . ">";
        $formFilter .= "<option value=\"*\">Összes</option>";
        while ($resf = sql_fetch_array($qf)) {
            $formFilter .= "<option " . (!empty($appform) && $appform == $resf["id"] ? "selected" : null) . " value=\"{$resf["id"]}\">{$resf["name"]}</option>";
        }
        $formFilter .= "</select>&nbsp;<input type=\"submit\" " . (!empty($packageInstall) ? "style=\"display:none\"" : "") . " value=\"Kérőlap szűrés\" name=\"filterbyform\">";

        $qc = sql_query("SELECT sltk.id,sltk.name FROM synlab_labor_tetelek slt
                         LEFT JOIN synlab_labor_tetel_kategoriak sltk ON sltk.id=slt.category
                         WHERE TRUE " . (!empty($appform) ? "AND slt.appform={$appform}" : "") . "
                         GROUP BY  sltk.id
                         ORDER BY name ASC");

        $cFilter = "<select name=\"cFilter\" " . (empty($appform) ? "disabled=\"true\"" : "") . ">";
        $cFilter .= "<option value=\"*\">Összes</option>";
        while ($resc = sql_fetch_array($qc)) {
            $cFilter .= "<option " . (!empty($filterId) && $filterId == $resc["id"] ? "selected" : null) . " value=\"{$resc["id"]}\">{$resc["name"]}</option>";
        }
        $cFilter .= "</select>&nbsp;<input " . (empty($appform) ? "disabled=\"true\"" : "") . " type=\"submit\" value=\"Kategória szűrés\" name=\"filterbycategory\">";

        $setItemPricesButton = "onClick=\"$('#labortetelek-form').append($('<input>').attr('type','hidden').attr('name','setItemPrices').val('true')).submit()\"";
        $cancelSetItemPricesButton = "onClick=\"$('#labortetelek-form').append($('<input>').attr('type','hidden').attr('name','setItemPrices').val('false')).submit()\"";
        $saveItemPricesButton = "onClick=\"$('#labortetelek-form').append($('<input>').attr('type','hidden').attr('name','saveItemPrices').val('false'),$('<input>').attr('type','hidden').attr('name','saveItemPrices').val('true')).submit()\"";

        $searchbyitem = "<input type=\"textbox\" placeholder=\"Keresés...\" onkeyup=\"searchbyitem($(this).val())\" name=\"search-by-item-name\" >";

        $setSelectedItemJScall = "onClick='selectItemForPackage($(this).val())'";

        echo "<table cellpadding='0' cellspacing='0' border='0' style='width:100%' id=\"LaborItems\">";
        echo "<tr><td colspan='" . (!empty($packageInstall) ? "4" : "4") . "' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:16px'>Tételek&nbsp;&nbsp;{$formFilter}&nbsp;{$cFilter}&nbsp;&nbsp;" . (!empty($packageInstall) ? $searchbyitem : "") . "</td>";

        if (empty($packageInstall)) {
            if ($this->setItemPrices == false) {
                echo "<td colspan=\"1\" style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:18px;text-align:right;padding-right:60px'><i class=\"fas fa-cog\" style=\"cursor:pointer\" {$setItemPricesButton}></i></td>";
            } else {
                echo "<td colspan=\"1\" style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:18px;text-align:right;padding-right:80px'><i class=\"fas fa-save\" style=\"cursor:pointer\" {$saveItemPricesButton}></i>&nbsp;&nbsp;<i class=\"fas fa-times\" style=\"cursor:pointer\" {$cancelSetItemPricesButton}></i></td>";
            }
        } else {
            echo "<td colspan=\"1\" style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:18px'></td>";
        }

        echo "</tr>";

        echo "<tbody id= \"item-content\">";
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

        echo "</tbody>";



        echo "</table>";
        return;
    }

    private function editPackage($packageId = null)
    {

        $packageData = sql_fetch_array(sql_query("SELECT slc.*,slk.name AS kerolap FROM synlab_labor_csomagok slc 
                                                  LEFT JOIN synlab_labor_kerolapok slk ON slk.id=slc.appform
                                                  WHERE slc.id=?", array($packageId)));

        //Alap adatok megadása:
        echo "<table>";
        echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Csomag megnevezése: </p></td><td><input type=\"textbox\" name=\"name\" style=\"min-width:300px;height:25px\" value=\"{$packageData["name"]}\"></td></tr>";
        echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Kérőlap: </p></td><td><input type=\"textbox\" disabled=\"true\" name=\"appform\" style=\"min-width:300px;height:25px\" value=\"{$packageData["kerolap"]}\"></td></tr>";
        echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Ár: </p></td><td><input type=\"textbox\" name=\"price\" style=\"min-width:300px;height:25px\" value=\"{$packageData["price"]}\"></td></tr>";
        echo "<tr><td><p style=\"font-weight:bold;font-size:14px\">Státusz: </p></td><td><select name=\"aktiv\"><option value=\"1\">Aktív</option><option value=\"0\">Inaktív</option></select></td></tr>";
        echo "</table>";

        echo $this->showItems(null, $packageData["appform"], $packageData["items"]);
    }
}
