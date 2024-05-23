<?php

class AdminPlacesPage extends AdminCorePage {

    private $bookingService;

    public function __construct()
    {
        parent::__construct();

        if (isset($_GET["delnyitvatartas"])) {
            sql_query("delete from helyszin_nyitvatartas where id=? and helyszinid=?",array($_GET["delnyitvatartas"], $_GET["szerk"]));
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_POST["addnyitvatartas"])) {
            sql_query("insert into helyszin_nyitvatartas set helyszinid=?",array($_GET["szerk"]));
            $_POST["helyszinmentes"]=1;
        }

        if (isset($_GET["scanh"])) {
            $maps = new Maps();
            $cimek = sql_query("SELECT h.* FROM helyszinek h where true")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cimek as $cim) {
                echo $cim["cim"];
                $json = $maps->geoCoding($cim["cim"]);
                sql_query("update helyszinek set geocodejson=? where id=?", [$json, $cim["id"]]);
            }
            die;
        }


        if (isset($_POST["helyszinmentes"]) || isset($_POST["helyszinform"])) {
            $_SESSION["helyszinbeosztascegfilter"] = $_POST["helyszinbeosztascegfilter"];

            $ceglink = "";
            $resh = sql_query("select * from cegek order by megnev");
            while ($rowh = sql_fetch_array($resh)) {
                if (isset($_POST["cegcheck{$rowh["id"]}"])) $ceglink.="|{$rowh["id"]}|";
            }

            $sor = 1;
            while (isset($_POST["nyid{$sor}"])) {
                sql_query("update helyszin_nyitvatartas set 
                nap=?,
                tol=?,
                ig=? 
                where id=?",array($_POST["weekday{$sor}"], $_POST["tol{$sor}"], $_POST["ig{$sor}"], $_POST["nyid{$sor}"]));
                $sor++;
            }

            if (!isset($_POST["aktiv"])) {
                $_POST["aktiv"] = 0;
            }
            if (!isset($_POST["halozat"])) {
                $_POST["halozat"] = 0;
            }

            $maps = new Maps();
            $json = $maps->geoCoding($_POST["cim"]);

            sql_query("update helyszinek set 
                cegid=?,
                cim=?,
                cim_en=?,
                cim_de=?,
                ceglink=?,
                geocodejson=?,
                halozat=?,
                aktiv=?,
                autoirsz=?,
                autovaros=?,
                autoutca=?,
                alias=?
            where id=?",[$_POST["cegid"], $_POST["cim"], $_POST["cim_de"], $_POST["cim_en"], $ceglink, $json, $_POST["halozat"], $_POST["aktiv"], $_POST["autoirsz"], $_POST["autovaros"], $_POST["autoutca"],$_POST["alias"], $_GET["szerk"]]);

            logActivity("helyszin",$_GET["szerk"],"{$_POST["cim"]} adatlap",print_r($_POST,true));

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }


    }

    public function showPage() {
        if (!$this->adminUser->placesAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        if (isset($_GET["szerk"])) {


            $helyszinId = intval($_GET["szerk"]);
            $row = sql_fetch_array(sql_query("select * from helyszinek where id=?",array($helyszinId)));
            $_POST = $row;

            $maps = new Maps();
            $addressInfo = $maps->getAddressInfo(json_decode($row["geocodejson"], JSON_OBJECT_AS_ARRAY));

            echo "<div style='background-color:#fff;padding:0px;'>";
            echo "<form name='iform' method='post' enctype='multipart/form-data'>";
            echo "<table style='font-size:12px;'>";

            echo "<tr><td width='100'>Cím:</td><td><input class='inputbox' style='width:400px;' type='text' name='cim' value='{$_POST["cim"]}'></td></tr>";
            echo "<tr><td>Cím (en):</td><td><input class='inputbox' style='width:400px;' type='text' name='cim_en' value='{$_POST["cim_en"]}'></td></tr>";
            echo "<tr><td>Cím (de):</td><td><input class='inputbox' style='width:400px;' type='text' name='cim_de' value='{$_POST["cim_de"]}'></td></tr>";
            echo "<tr><td>Alias:</td><td><input class='inputbox' style='width:400px;' type='text' name='alias' value='{$_POST["alias"]}'></td></tr>";

            $geoResult = print_r($addressInfo, true);
            if (empty($addressInfo["lat"])) {
                $geoResult = "Nem azonosítható cím";
            }

            echo "<tr><td>Geocode result:</td><td><pre style='background:#eee;padding:10px;'>{$geoResult}</pre></td></tr>";

            echo "<tr><td>Automata Irsz:</td><td><input class='inputbox' style='width:40px;' type='text' name='autoirsz' value='{$_POST["autoirsz"]}'></td></tr>";
            echo "<tr><td>Automata Város:</td><td><input class='inputbox' style='width:400px;' type='text' name='autovaros' value='{$_POST["autovaros"]}'></td></tr>";
            echo "<tr><td>Automata Utca:</td><td><input class='inputbox' style='width:400px;' type='text' name='autoutca' value='{$_POST["autoutca"]}'></td></tr>";


            echo "<tr><td colspan='2' valign='top'><hr></td></tr>";
            echo "<tr><td width='100'>Kiknek látszik:</td><td>";

            $resh = sql_query("select * from cegek order by megnev");
            $availableFor = 0;
            $checkboxes = "";
            while ($rowh = sql_fetch_array($resh)) {
                $checkboxes.= "<div><input type='checkbox' name='cegcheck{$rowh["id"]}' value='1' ".(substr_count($_POST["ceglink"],"|{$rowh["id"]}|")>0?" checked":"")."/> {$rowh["megnev"]}</div>";
                if (substr_count($_POST["ceglink"],"|{$rowh["id"]}|")>0) $availableFor++;
            }

            echo "<div><a href='#' onclick='$(\"#cegboxes\").slideToggle();'>Elérhető {$availableFor} cég számára</a></div>";
            echo "<div id='cegboxes' style='display: none;'>{$checkboxes}</div>";
            echo "</td></tr>";

            echo "<tr><td colspan='2' valign='top'><hr></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='aktiv'".($_POST["aktiv"]==1?" checked":"")."> Aktív</td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='checkbox' value='1' name='halozat'".($_POST["halozat"]==1?" checked":"")."> Orvos hálózat része (kerüljön rá a weboldalon a térképre)</td></tr>";
            echo "<tr><td colspan='2' valign='top'><hr></td></tr>";
            //echo "<tr><td colspan='2' valign='top'>".beosztasEditorByAddress($helyszinId)."</td></tr>";

            echo "</table>";

            echo "<br><input type='submit' name='helyszinmentes' value='Mentés'> <input type='submit' name='scancel' value='Vissza'> ";


            echo "</form>";

            echo "</div>";


        }

        if (!isset($_GET["szerk"])) {
            $resh = sql_query("select * from cegek order by megnev");
            while ($rowh = sql_fetch_array($resh)) {
                $cegek[$rowh["id"]] = $rowh["megnev"];
            }

            $w = "true";
            $res = sql_query("SELECT h.* FROM helyszinek h
            where {$w}
            ORDER BY h.cim!='Új helyszín',h.cim");

            echo "<table cellpadding='0' cellspacing='0' border='0'>";
            while ($row = sql_fetch_array($res)) {
                $tc = "tcella";
                if (!isset($first)) {
                    echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                    $first = 1;
                }
                if (trim($row["megnev"]) == "") $row["megnev"] = "nincs neve";

                $nyitva = "";
                $resny = sql_query("SELECT * FROM helyszin_nyitvatartas where helyszinid='{$row["id"]}' order by nap");
                while ($rowny = sql_fetch_array($resny)) {
                    $nyitva .= "{$GLOBALS["hetnap"][$rowny["nap"]]} ({$rowny["tol"]}-{$rowny["ig"]}), ";
                }

                $vanbeo = 0;
                if (sql_fetch_array(sql_query("select * from orvos_beosztas_new where helyszinid=? limit 1", array($row["id"])))) $vanbeo = 1;

                echo "<tr>";
                echo "<td nowrap valign='top'><div class={$tc}>".($row["halozat"] == 1?" <i class='fa-solid fa-map' title='térképen'></i>":"")."</div></td>";
                echo "<td nowrap valign='top'><div class={$tc}><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["cim"]}</a>". ($row["cim_en"] != "" ? "&nbsp;<span style='padding:2px;border:1px solid #f00;color:#f00;'>EN</span>" : "") . ($row["cim_de"] != "" ? "&nbsp;<span style='padding:2px;border:1px solid #f00;color:#f00;'>DE</span>" : "") . "</div></td>";

                echo "<td valign=top><div class={$tc}>";
                $cegids = explode("|", $row["ceglink"]);
                unset($ceglist);
                for ($i = 0; $i < count($cegids); $i++) {
                    if (@$cegek[$cegids[$i]] != "") $ceglist[] = $cegek[$cegids[$i]];
                }
                echo @implode(", ", $ceglist);
                echo "</div></td>";

                echo "<td nowrap valign='top'><div class='{$tc}' style=''>" . ($vanbeo == 0 ? "nincs beosztás" : "") . "&nbsp;&nbsp;</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}' style='min-width:50px;'>" . ($row["aktiv"] == 1 ? "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>" : "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>") . "</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='return confirm(\"Biztosan törlöd ezt a helyszínt?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
                echo "</tr>";
                echo "<tr><td colspan=7 style='border-bottom:1px solid #ccc;height:1px;'></td></tr>";
            }
            echo "</table>";
        }
    }
}

