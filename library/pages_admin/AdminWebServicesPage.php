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
        if (isset($_GET["deltipar"]) && isset($_GET["szerk"])) {
            sql_query("delete from arak where id=? and tipusid=?", [$_GET["deltipar"], $_GET["szerk"]]);
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

                sql_query("update szurestipusok set webalias=?, webkiemelt=?, webdescription=?, seokeywords=?, seodescription=? where id=?",
                    [$_POST["webalias"], $_POST["webkiemelt"], $_POST["webdescription"], $_POST["seokeywords"], $_POST["seodescription"], $_GET["szerk"]]);

                logActivity("wwwservice",$_GET["szerk"],"{$_POST["megnev"]} adatlap", print_r($_POST,true));
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

    }

    public function showPage()
    {
        if (!$this->adminUser->beallitasWebAdatokAccess()) {
            echo $this->noPermissionMessage();
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
            $prices = sql_query("select * from arak where tipusid=? and instr(cegid, '|243|') and csomag=0 order by megnev", [$id])->fetchAll(PDO::FETCH_ASSOC);
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
                echo "<td style='text-align: center;'><input type='text' name='sorrend{$sor}' value='{$price["sorrend"]}' style='width:14px;' placeholder='időtartam' title='időtartam módosító'/> </td>";
                echo "<td><input type='text' name='price{$sor}' value='{$price["price"]}' style='width:50px;' placeholder='ár'/>&nbsp;HUF</td>";
                echo "<td style='font-size: 14px;'>&nbsp;<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&deltipar={$price["id"]}' onclick='return confirm(\"Biztos törlöd ezt az árat?\")'><i class='fas fa-trash'></i></a></td>";
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


        $kiemeltServicesHTML = $otherServicesHTML = "";

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

            if ($kiemelt) {
                $kiemeltServicesHTML.= $html;
            } else {
                $otherServicesHTML.= $html;
            }

        }


        echo "<h2>A weboldalon megjelenő szolgáltatások</h2>";
        echo $kiemeltServicesHTML;
        echo "<hr style='margin:0px 0px 20px 0px;'>";
        echo "<h2>Egyéb, a weboldalon nem használt szolgáltatások</h2>";
        echo $otherServicesHTML;

    }


}