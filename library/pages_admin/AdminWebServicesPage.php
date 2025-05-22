<?php

class AdminWebServicesPage extends AdminCorePage
{
    public function __construct()
    {
        parent::__construct();

        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        if (isset($_POST["addprice"]) && isset($_GET["szerk"])) {
            sql_query("insert into arak set tipusid=?, cegid='|243|', csomag=0", [$_GET["szerk"]]);
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_POST["addlinkedservice"]) && isset($_GET["szerk"])) {
            sql_query("insert into sitedata set datum=now(), tipus='linkedservice', tipusid=?", [$_GET["szerk"]]);
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_GET["deltipar"]) && isset($_GET["szerk"])) {
            sql_query("delete from arak where id=? and tipusid=?", [$_GET["deltipar"], $_GET["szerk"]]);
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_GET["dellinkedservice"]) && isset($_GET["szerk"])) {
            sql_query("delete from sitedata where id=? and tipusid=?", [$_GET["dellinkedservice"], $_GET["szerk"]]);
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_POST["szurestipusmentes"])) {
            if ($this->adminUser->beallitasWebAdatokAccess()) {
                $sor = 1;
                while (isset($_POST["arid{$sor}"])) {
                    sql_query("update arak set megnev=?, price=?, sorrend=?, aktiv=? where id=?",
                        [$_POST["megnev{$sor}"], $_POST["price{$sor}"], $_POST["sorrend{$sor}"], isset($_POST["aktiv{$sor}"])?1:0, $_POST["arid{$sor}"]]);
                    $sor++;
                }

                $sor = 1;
                while (isset($_POST["linkedserviceid{$sor}"])) {
                    sql_query("update sitedata set value1=?, value2=?, value3=?, sorrend=?, aktiv=? where id=?",
                        [$_POST["linkedserviceurl{$sor}"], $_POST["linkedserviceurltitle{$sor}"], $_POST["linkedservicetitle{$sor}"], $_POST["linkedservicesorrend{$sor}"], isset($_POST["linkedserviceaktiv{$sor}"])?1:0, $_POST["linkedserviceid{$sor}"]]);
                    $sor++;
                }

                $webOptions = [];
                foreach ($_POST as $key => $val) {
                    if (substr_count($key, "weboptions_")) {
                        $index = str_replace("weboptions_", "", $key);
                        $webOptions[$index] = $val;
                    }
                }

                $managerOptions = [];
                foreach ($_POST as $key => $val) {
                    if (substr_count($key, "manager_")) {
                        $index = str_replace("manager_", "", $key);
                        $managerOptions[$index] = $val;
                    }
                }

                $managerItems = [];
                foreach ($_POST as $key => $val) {
                    if (substr_count($key, "manageritem_")) {
                        $managerItems[] = $val;
                    }
                }
                $managerOptions["items"] = $managerItems;

                sql_query("update szurestipusok set webalias=?, webkiemelt=?, webdescription=?, seokeywords=?, seodescription=?, weboptions=?, packcontents=? where id=?",
                    [$_POST["webalias"], $_POST["webkiemelt"], $_POST["webdescription"], $_POST["seokeywords"], $_POST["seodescription"], json_encode($webOptions), json_encode($managerOptions), $_GET["szerk"]]);

                logActivity("wwwservice",$_GET["szerk"],"{$_POST["megnev"]} adatlap", print_r($_POST,true));
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_POST["addmanagerwebitem"])) {
            $id = intval($_GET["pack"]);
            $packData = sql_query("select * from sitedata where tipus='packjson' order by datum desc limit 1")->fetch(PDO::FETCH_ASSOC);
            $packs = json_decode($packData["valuetext"], JSON_OBJECT_AS_ARRAY);
            $packs["manager1"][$id]["items"][] = ["új elem", ""];
            $this->savePack($packs);

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&pack={$_GET["pack"]}");
            die();
        }

        if (isset($_GET["delmanagerwebitem"])) {
            $deleteKey = intval($_GET["delmanagerwebitem"]);
            $id = intval($_GET["pack"]);
            $packData = sql_query("select * from sitedata where tipus='packjson' order by datum desc limit 1")->fetch(PDO::FETCH_ASSOC);
            $packs = json_decode($packData["valuetext"], JSON_OBJECT_AS_ARRAY);
            unset($packs["manager1"][$id]["items"][$deleteKey]);
            $this->savePack($packs);

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&pack={$_GET["pack"]}");
            die();
        }

        if (isset($_POST["packmentes"])) {
            if ($this->adminUser->beallitasWebAdatokAccess()) {
                $id = intval($_GET["pack"]);
                $packData = sql_query("select * from sitedata where tipus='packjson' order by datum desc limit 1")->fetch(PDO::FETCH_ASSOC);
                $packs = json_decode($packData["valuetext"], JSON_OBJECT_AS_ARRAY);
                $pack = $packs["manager1"][$id];

                $pack["name"] = $_POST["name"];
                $pack["alias"] = $_POST["alias"];
                $pack["background"] = $_POST["background"];
                $pack["kategoria"] = $_POST["kategoria"];
                $pack["price"] = $_POST["price"];
                $packs["manager1"][$id] = $pack;

                foreach ($pack["items"] as $key => $item) {
                    if (isset($_POST["itemdescription{$key}"])) {
                        $itemName = $item[0];
                        $pack["items"][$key][0] = $itemName;
                        $packs["descriptions"][$itemName] = $_POST["itemdescription{$key}"];
                        //echo $itemName." ".$_POST["packdescription{$key}"];die;
                    }
                }

                $this->savePack($packs);
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&pack={$_GET["pack"]}");
            die();
        }

    }


    private function savePack($packData):void {
        sql_query("insert into sitedata set datum=now(), username=?, tipus='packjson', valuetext=?", [$this->adminUser->user["username"], json_encode($packData, JSON_PRETTY_PRINT)]);
    }

    public function showPage()
    {
        if (!$this->adminUser->beallitasWebAdatokAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        if (isset($_GET["pack"])) {
            $id = intval($_GET["pack"]);
            $packData = sql_query("select * from sitedata where tipus='packjson' order by datum desc limit 1")->fetch(PDO::FETCH_ASSOC);
            $packs = json_decode($packData["valuetext"], JSON_OBJECT_AS_ARRAY);
            $pack = $packs["manager1"][$id];
            $descriptions = $packs["descriptions"];

            $GLOBALS["subtitle"] = $pack["name"];

            echo "<div style='background-color:#fff;padding:0px;'>";
            echo "<form name='iform' method='post' enctype='multipart/form-data'>";
            echo "<h2>{$pack["name"]}</h2>";

            echo "<table style='font-size:12px;'>";

            echo "<tr><td>Megnevezés:&nbsp;</td><td><input class='inputbox' style='width:500px;' type='text' name='name' value='{$pack["name"]}'  /></td></tr>";
            echo "<tr><td>Alias:</td><td><input class='inputbox' style='width:500px;' type='text' name='alias' value='{$pack["alias"]}'  /></td></tr>";
            echo "<tr><td>Ár:</td><td><input class='inputbox' style='width:100px;' type='text' name='price' value='{$pack["price"]}'  /></td></tr>";
            echo "<tr><td>Háttérszín:</td><td><input class='inputbox' style='width:100px;' type='text' name='background' value='{$pack["background"]}'  /></td></tr>";
            echo "<tr><td>Kategória:</td><td><input class='inputbox' style='width:100px;' type='text' name='kategoria' value='{$pack["kategoria"]}'  /></td></tr>";

            echo "<tr><td colspan='2'>&nbsp;</td></tr>";
            echo "<tr><td colspan='2'><div class='tdsepdiv'>Csomag elemei</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='submit' name='addmanagerwebitem' value='+ elem hozzáadása'></td></tr>";

            echo "<tr><td colspan='2'>";
            echo "<table cellpadding='0' cellspacing='0'>";

            $sor = 1;
            echo "<tr style='font-weight: bold;'>";
            echo "<td style='padding:5px 0px;'>Megnevezés&nbsp;&nbsp;</td>";
            echo "<td style='padding:5px 0px;'>Sorrend&nbsp;&nbsp;</td>";
            echo "<td style='padding:5px 0px;'>&nbsp;</td>";
            echo "</tr>";
            foreach ($pack["items"] as $key => $item) {
                $description = $descriptions[$item[0]] ?? "";
                echo "<tr>";
                echo "<td><input type='text' name='megnev{$key}' value='{$item[0]}' style='width:450px;' placeholder='megnevezés' /></td>";
                echo "<td style='text-align: center;'><input type='text' name='sorrend{$key}' value='{$key}' style='width:34px;' placeholder='sorrend' title='sorrend'/> </td>";
                echo "<td style='font-size: 14px;'>&nbsp;<a onclick='$(\"#packdescription{$key}\").slideToggle();return false;' href='#'>szöveg</a>&nbsp;&nbsp;<a href='index.php?page={$_GET["page"]}&pack={$_GET["pack"]}&delmanagerwebitem={$key}' onclick='return confirm(\"Biztos törlöd ezt az elemet?\")'><i class='fas fa-trash'></i></a></td>";
                echo "</tr>";
                echo "<tr>";
                echo "<td colspan='10'><div id='packdescription{$key}' style='display:none;'><textarea class='mce' name='itemdescription{$key}' style='width:550px;height:500px;'>{$description}</textarea></div></td>";
                echo "</tr>";
            }

            echo "</table>";
            echo "</td></tr>";

            echo "<tr><td colspan='2'>&nbsp;</td></tr>";
            echo "</table>";

            echo "<br><input type='submit' name='packmentes' value='Mentés'> ";
            echo "<input type='submit' name='scancel' value='Vissza'> ";
            echo "</form>";

            echo "</div>";
            return;
        }


        if (isset($_GET["szerk"])) {
            $service = sql_fetch_array(sql_query("select * from szurestipusok where id=?", [$_GET["szerk"]]));

            $GLOBALS["subtitle"] = $service["megnev"];

            $id = $service["id"];

            echo "<div style='background-color:#fff;padding:0px;'>";
            echo "<form name='iform' method='post' enctype='multipart/form-data'>";
            echo "<h2>{$service["megnev"]}</h2>";

            echo "<table style='font-size:12px;'>";

            //echo "<tr><td width='100'>Megnevezés:</td><td><input class='inputbox' style='width:400px;' type='text' name='megnev' value='{$service["megnev"]}'></td></tr>";

            $docAgent = new DocAgent();
            echo "<tr><td colspan='2'><div class='tdsepdiv'>Kép</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><div id='asseteditor'>".$docAgent->showAssetEditor(DocAgent::ASSET_SERVICE_ILLUSTRATION_IMAGE, $id)."</div>";
            echo "</td></tr>";


            echo "<tr><td colspan='2'><div class='tdsepdiv'>Árak</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='submit' name='addprice' value='+ ár hozzáadása'></td></tr>";

            echo "<tr><td colspan='2'>";
            echo "<table cellpadding='0' cellspacing='0'>";

            $sor = 1;
            $prices = sql_query("select * from arak where tipusid=? and instr(cegid, '|243|') and csomag=0 order by sorrend, megnev", [$id])->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($prices)) {
                echo "<tr style='font-weight: bold;'>";
                echo "<td style='padding:5px 0px;'>Aktív&nbsp;&nbsp;</td>";
                echo "<td style='padding:5px 0px;'>Megnevezés&nbsp;&nbsp;</td>";
                echo "<td style='padding:5px 0px;'>Sorrend&nbsp;&nbsp;</td>";
                echo "<td style='padding:5px 0px;'>Ár&nbsp;&nbsp;</td>";
                echo "<td style='padding:5px 0px;'>&nbsp;</td>";
                echo "</tr>";
            }
            foreach ($prices as $price) {
                echo "<tr>";
                echo "<td><input type='hidden' name='arid{$sor}' id='arid{$sor}' value='{$price["id"]}' /><input type='checkbox' title='aktiv' name='aktiv{$sor}' value='1' ".($price["aktiv"] == 1 ? "checked":"")."/></td>";
                echo "<td><input type='text' name='megnev{$sor}' value='{$price["megnev"]}' style='width:350px;' placeholder='megnevezés' /></td>";
                echo "<td style='text-align: center;'><input type='text' name='sorrend{$sor}' value='{$price["sorrend"]}' style='width:34px;' placeholder='időtartam' title='időtartam módosító'/> </td>";
                echo "<td><input type='text' name='price{$sor}' value='{$price["price"]}' style='width:50px;' placeholder='ár'/>&nbsp;HUF</td>";
                echo "<td style='font-size: 14px;'>&nbsp;<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&deltipar={$price["id"]}' onclick='return confirm(\"Biztos törlöd ezt az árat?\")'><i class='fas fa-trash'></i></a></td>";
                echo "</tr>";
                $sor++;
            }

            echo "</table>";
            echo "</td></tr>";

            echo "<tr><td colspan='2'><div class='tdsepdiv'>Kapcsolódó szolgáltatások</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='submit' name='addlinkedservice' value='+ hozzáadás'></td></tr>";

            echo "<tr><td colspan='2'>";
            echo "<table cellpadding='0' cellspacing='0'>";

            $sor = 1;
            $linkedServices = sql_query("select * from sitedata where tipus='linkedservice' and tipusid=? order by sorrend", [$id])->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($linkedServices)) {
                echo "<tr style='font-weight: bold;'>";
                echo "<td style='padding:5px 0px;'>Aktív&nbsp;&nbsp;</td>";
                echo "<td style='padding:5px 0px;'>URL&nbsp;&nbsp;</td>";
                echo "<td style='padding:5px 0px;'>URL szöveg&nbsp;&nbsp;</td>";
                echo "<td style='padding:5px 0px;'>Szöveg&nbsp;&nbsp;</td>";
                echo "<td style='padding:5px 0px;'>Sorrend&nbsp;&nbsp;</td>";
                echo "<td style='padding:5px 0px;'>&nbsp;</td>";
                echo "</tr>";
            }
            foreach ($linkedServices as $linkedService) {
                echo "<tr>";
                echo "<td><input type='hidden' name='linkedserviceid{$sor}' id='linkedserviceid{$sor}' value='{$linkedService["id"]}' /><input type='checkbox' title='aktiv' name='linkedserviceaktiv{$sor}' value='1' ".($linkedService["aktiv"] == 1 ? "checked":"")."/>&nbsp;</td>";
                echo "<td><input type='text' name='linkedserviceurl{$sor}' value='{$linkedService["value1"]}' style='width:250px;' placeholder='URL' />&nbsp;</td>";
                echo "<td><input type='text' name='linkedserviceurltitle{$sor}' value='{$linkedService["value2"]}' style='width:250px;' placeholder='URL szöveg' />&nbsp;</td>";
                echo "<td><input type='text' name='linkedservicetitle{$sor}' value='{$linkedService["value3"]}' style='width:250px;' placeholder='rövid szöveg' />&nbsp;</td>";
                echo "<td style='text-align: center;'><input type='text' name='linkedservicesorrend{$sor}' value='{$linkedService["sorrend"]}' style='width:14px;' placeholder='sorrend' title='sorrend'/>&nbsp;</td>";
                echo "<td style='font-size: 14px;'>&nbsp;<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&dellinkedservice={$linkedService["id"]}' onclick='return confirm(\"Biztos törlöd ezt az sort?\")'><i class='fas fa-trash'></i></a></td>";
                echo "</tr>";
                $sor++;
            }

            echo "</table>";
            echo "</td></tr>";

            echo "<tr><td colspan='2'>&nbsp;</td></tr>";

            echo "<tr><td colspan='2'><div class='tdsepdiv'>Weboldal szöveg</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><div id='desceditor' style=''>";
            echo "<textarea class='mce' name='webdescription' style='width:900px;height:600px;'>{$service["webdescription"]}</textarea>";
            echo "</div></td></tr>";
            echo "<tr><td>Alias:</td><td><input class='inputbox' style='width:200px;' type='text' name='webalias' value='{$service["webalias"]}' placeholder='ez lesz az url' />&nbsp;&nbsp;<input type='checkbox' value='1' name='webkiemelt'" . ($service["webkiemelt"] == 1 ? " checked" : "") . "> Kiemelve a weboldalon</td></tr>";
            echo "<tr><td>SEO keywords:</td><td><input class='inputbox' style='width:800px;' type='text' name='seokeywords' value='{$service["seokeywords"]}'  /></td></tr>";
            echo "<tr><td>SEO description:</td><td><input class='inputbox' style='width:800px;' type='text' name='seodescription' value='{$service["seodescription"]}'  /></td></tr>";

            echo "<tr><td></td><td>";

            $webOptions = json_decode($service["weboptions"], true);

            echo "<div><input type='checkbox' value='1' name='weboptions_prices'" . (isset($webOptions["prices"]) && $webOptions["prices"] == 1 ? " checked" : "") . "> Árlista</div>";
            echo "<div><input type='checkbox' value='1' name='weboptions_blog'" . (isset($webOptions["blog"]) && $webOptions["blog"] == 1 ? " checked" : "") . "> Blog kivonat</div>";
            echo "<div><input type='checkbox' value='1' name='weboptions_doctorlist'" . (isset($webOptions["doctorlist"]) && $webOptions["doctorlist"] == 1 ? " checked" : "") . "> Orvosok</div>";
            echo "<div><input type='checkbox' value='1' name='weboptions_reservation'" . (isset($webOptions["reservation"]) && $webOptions["reservation"] == 1 ? " checked" : "") . "> Időpontfoglalás gomb</div>";
            echo "<div><input type='checkbox' value='1' name='weboptions_ajanlatform'" . (isset($webOptions["ajanlatform"]) && $webOptions["ajanlatform"] == 1 ? " checked" : "") . "> Ajánlatkérő form</div>";
            echo "</td></tr>";

            if ($service["ismanagerpack"] == 1) {
                $managerOptions = json_decode($service["packcontents"], true);
                if (!isset($managerOptions["backgroundcolor"])) {
                    $managerOptions["backgroundcolor"] = "";
                }
                echo "<tr><td>&nbsp;</td></tr>";
                echo "<tr><td>Háttérszín:</td><td><input class='inputbox' style='width:200px;' type='text' name='manager_backgroundcolor' value='{$managerOptions["backgroundcolor"]}' /></td></tr>";

                echo "<tr><td>&nbsp;</td><td><strong>Csomag tartalma (weboldalra)</strong></td></tr>";
                echo "<tr><td></td><td>";
                foreach (sql_query("select * from sitedata where tipus='manageritems' order by value1")->fetchAll() as $sitedata) {
                    echo "<div><input type='checkbox' value='{$sitedata["id"]}' name='manageritem_{$sitedata["id"]}'" . (isset($managerOptions["items"]) && in_array($sitedata["id"], $managerOptions["items"]) ? " checked" : "") . "> {$sitedata["value1"]}</div>";
                }
                echo "</td></tr>";


            }

            echo "</table>";

            echo "<br><input type='submit' name='szurestipusmentes' value='Mentés'> ";
            echo "<input type='submit' name='scancel' value='Vissza'> ";
            echo "</form>";

            echo "</div>";
            return;
        }


        $docAgent = new DocAgent();

        $services = sql_query("SELECT t.*,m.tipusid FROM szurestipusok t
        LEFT JOIN szurestipusok_megj m ON m.`tipusid`=t.`id` and csomag=0
        GROUP BY t.id
        ORDER BY !instr(megnev,'Új tétel'),megnev")->fetchAll(PDO::FETCH_ASSOC);


        $kiemeltServicesHTML = $otherServicesHTML = $managerServicesHTML = "";

        foreach ($services as $service) {
            $kiemelt = false;
            $html = "";

            $prices = sql_query("select a.* from arak a where tipusid='{$service["id"]}' and price<>0 and instr(cegid, '|243|') and a.csomag=0", [$service["id"]])->fetchAll(PDO::FETCH_ASSOC);
            $arak = "";
            foreach ($prices as $key => $price) {
                $kiemelt = true;
                if ($key > 5) {
                    $arak.= "<span class='price_badge'>".(count($prices)-$key)." további ár</span>";
                    break;
                }
                $arak .= "<span class='price_badge' style='cursor: pointer' title='{$price["megnev"]}'>{$price["price"]} Ft</span> ";
            }

            $image = "/admin/images/serviceplaceholder.png";
            $assets = $docAgent->getAssetsByType(DocAgent::ASSET_SERVICE_ILLUSTRATION_IMAGE, $service["id"]);
            if (!empty($assets)) {
                $kiemelt = true;
                $image = $assets[0]["url"];
            }

            $html.= "<div style='display:inline-block;vertical-align:top;width:250px;height:125px;border:0px solid #ddd;margin:0px 20px 20px 0px;padding:10px;background:#f0f0f0;'>";
            $html.= "<div style='height:110px;overflow: hidden;'>";
            $html.= "<div style='float:left;padding:0px 10px 10px 0px;'><a style='color:#00a;font-size:14px;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$service["id"]}'><img style='width:100px;height:100px;object-fit:cover;box-shadow:2px 2px 2px rgba(100,100,100, .2);' src='{$image}' /></a></div>";
            $html.= "<div><a style='color:#00a;font-size:14px;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$service["id"]}'>{$service["megnev"]}</a></div>";
            if (!empty($arak)) {
                $html.= "<div style='margin-top:5px;line-height:18px;'>{$arak}</div>";
            }
            $html.= "</div>";

            $html.= "<div style=''>";

            $html.= "<div style='display:table;width:100%;'>";
            $html.= "<div style='display:table-cell;vertical-align: middle;'>";
            if ($service["webalias"] != "") {
                $html.= "<span class='pack_badge'><a target='_blank' style='color:white;' href='https://uj.hungariamed.hu/szurovizsgalatok/{$service["webalias"]}'>WEBOLDAL <i class='fa-solid fa-arrow-right'></i></a></span>&nbsp;&nbsp;";
            }

            $html.= "</div>";
            $html.= "</div>";

            $html.= "</div>";

            $html.= "</div>";

            if ($service["ismanagerpack"] == 1) {
                $managerServicesHTML .= $html;
            } else {
                if ($kiemelt) {
                    $kiemeltServicesHTML .= $html;
                } else {
                    $otherServicesHTML .= $html;
                }
            }

        }


        echo "<h2>Menedzser csomagok</h2>";
        echo $managerServicesHTML;
        echo "<hr style='margin:0px 0px 20px 0px;'>";

        echo "<h2>Kiemelt szolgáltatások</h2>";
        //echo "<div style='margin:20px 0px;'>" . $this->packDataEditor() . "</div>";
        echo $kiemeltServicesHTML;
        echo "<hr style='margin:0px 0px 20px 0px;'>";

        echo "<h2>Egyéb, a weboldalon nem használt szolgáltatások</h2>";
        echo $otherServicesHTML;
    }


    private function packDataEditor():string {
        $html = "";
        $packData = sql_query("select * from sitedata where tipus='packjson' order by datum desc limit 1")->fetch(PDO::FETCH_ASSOC);

        $packs = json_decode($packData["valuetext"], JSON_OBJECT_AS_ARRAY);

        $html.= "<div style='margin-bottom: 10px;'>Menedzser csomagok: ";
        foreach ($packs["manager1"] as $key => $pack) {
            if ($key != 0) {
                $html.= "&nbsp;&bull;&nbsp;";
            }
            $html.= "<a href='index.php?page={$_GET["page"]}&pack={$key}'>{$pack["name"]}</a>";
        }
        $html.= "</div>";

        return $html;
    }


}