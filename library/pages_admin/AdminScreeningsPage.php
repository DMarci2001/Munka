<?php

class AdminScreeningsPage extends AdminCorePage
{

    private $bookingService;

    public function __construct()
    {
        parent::__construct();

        if (isset($_GET["deltipmegj"]) && isset($_GET["szerk"])) {
            sql_query("delete from szurestipusok_megj where id='".intval($_GET["deltipmegj"])."' and tipusid='".intval($_GET["szerk"])."'");
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_GET["deltipar"]) && isset($_GET["szerk"])) {
            sql_query("delete from arak where id='".intval($_GET["deltipar"])."' and tipusid='".intval($_GET["szerk"])."'");
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_GET["delcskapcs"]) && isset($_GET["szerk"])) {
            sql_query("delete from szurescsomagok_kapcs where id=? and csomagid=?", array($_GET["delcskapcs"], $_GET["szerk"]));
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_POST["addcsomagkapcs"]) && isset($_GET["szerk"])) {
            sql_query("insert into szurescsomagok_kapcs set csomagid=?", array($_GET["szerk"]));
        }
        if (isset($_POST["addtipmegj"]) && isset($_GET["szerk"])) {
            sql_query("insert into szurestipusok_megj set tipusid=?, csomag=0", array($_GET["szerk"]));
        }
        if (isset($_POST["addprice"]) && isset($_GET["szerk"])) {
            sql_query("insert into arak set tipusid=?, csomag=0", array($_GET["szerk"]));
        }

        if (isset($_GET["syncfofields"])) {
            $foService = new FoglaljOrvostService();
            if ($result = $foService->getAllFields()) {
                $xml = simplexml_load_string($result);

                foreach ($xml->FIELDS->FIELD as $field) {
                    if (!empty($field["NAME"])) {
                        //echo $field["OUTERSYS_ID"] . " " . $field["NAME"] . "\n";
                        sql_query("update szurestipusok set fotid=? where megnev=? and megnev<>''", [$field["OUTERSYS_ID"], $field["NAME"]]);

                        if (isset($field->SERVICES->SERVICE)) {
                            foreach ($field->SERVICES->SERVICE as $service) {
                                //todo altipusokkal mi legyen?
                                //echo "   " . $service["OUTERSYS_ID"] . " " . $service["NAME"] . "\n";
                            }
                        }
                    }
                }
            }
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_POST["szurestipusmentes"])) {
            if (!isset($_POST["aktiv"])) $_POST["aktiv"]=0;
            if (!isset($_POST["infopage"])) $_POST["infopage"]=0;
            if (!isset($_POST["ispack"])) $_POST["ispack"]=0;

            if ($this->adminUtils->szuresTipusModJog()) {
                $sor=1;
                while (isset($_POST["cskapcsid{$sor}"])) {
                    sql_query("update szurescsomagok_kapcs set szurestipusid=?,nemerequired=? where id=?", array($_POST["cskapcstipid{$sor}"], $_POST["cskapcsnemerequired{$sor}"], $_POST["cskapcsid{$sor}"]));
                    $sor++;
                }

                $sor=1;
                while (isset($_POST["tipmegjid{$sor}"])) {
                    sql_query("update szurestipusok_megj set 
        			cegid=?,
                    megj=?
                    where id=?",array($_POST["tipmegjceg{$sor}"], $_POST["tipmegj{$sor}"], $_POST["tipmegjid{$sor}"]));
                    $sor++;
                }

                $sor = 1;
                while (isset($_POST["arid{$sor}"])) {
                    sql_query("update arak set megnev=?,price=? where id=?",array($_POST["megnev{$sor}"],$_POST["price{$sor}"],$_POST["arid{$sor}"]));
                    $sor++;
                }

                sql_query("update szurestipusok set megnev=?,megnev_de=?,megnev_en=?,infopage=?,infopagetext=?,aktiv=?,ispack=? where id=?",array($_POST["megnev"],$_POST["megnev_de"],$_POST["megnev_en"],$_POST["infopage"],$_POST["infopagetext"],$_POST["aktiv"],$_POST["ispack"],$_GET["szerk"]));

                logActivity("szurestipus",$_GET["szerk"],"{$_POST["megnev"]} adatlap",print_r($_POST,true));
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

    }

    public function showPage()
    {
        if (!$this->adminUtils->szurestipusModJog()) {
            return;
        }

        if (isset($_GET["szerk"])) {
            $row = sql_fetch_array(sql_query("select * from szurestipusok where id=?", array($_GET["szerk"])));
            $_POST = $row;

            echo "<div style='background-color:#fff;padding:0px;'>";
            echo "<form name='iform' method='post' enctype='multipart/form-data'>";
            echo "<table style='font-size:12px;'>";

            echo "<tr><td width='100'>Megnevezés:</td><td><input class='inputbox' style='width:400px;' type='text' name='megnev' value='{$_POST["megnev"]}'></td></tr>";
            echo "<tr><td width='100'>Megnevezés (de):</td><td><input class='inputbox' style='width:400px;' type='text' name='megnev_de' value='{$_POST["megnev_de"]}'></td></tr>";
            echo "<tr><td width='100'>Megnevezés (en):</td><td><input class='inputbox' style='width:400px;' type='text' name='megnev_en' value='{$_POST["megnev_en"]}'></td></tr>";

            echo "<tr><td colspan='2' valign='top'>";
            echo "<input type='checkbox' value='1' name='aktiv'" . ($_POST["aktiv"] == 1 ? " checked" : "") . "> Aktív&nbsp;&nbsp;";
            echo "<input type='checkbox' value='1' name='infopage'" . ($_POST["infopage"] == 1 ? " checked" : "") . "> Info oldalon megjelenik&nbsp;&nbsp;";
            echo "<input type='checkbox' value='1' name='ispack' onchange=\"if (this.checked) { $('.csomagsor').show() } else { $('.csomagsor').hide() }\" ".($_POST["ispack"] == 1 ? " checked" : "")."> Ez egy szűréscsomag";
            echo "</td></tr>";

            echo "<tr class='csomagsor' style='".($_POST["ispack"] == 1?"":"display:none;")."'><td colspan='2'><div class='tdsepdiv'>Csomag tartalma</div></td></tr>";
            echo "<tr class='csomagsor' style='".($_POST["ispack"] == 1?"":"display:none;")."'><td colspan='2' valign='top'><input type='submit' name='addcsomagkapcs' value='+ hozzáadás'></td></tr>";

            $resb = sql_query("select * from szurescsomagok_kapcs where csomagid=? order by id", array($_GET["szerk"]));

            $sor = 1;
            while ($rowb = sql_fetch_array($resb)) {
                echo "<tr><td colspan='2'>";
                echo "<input type='hidden' name='cskapcsid{$sor}' value='{$rowb["id"]}'/>";
                echo "<div>";
                echo "<select name='cskapcstipid{$sor}' style='width:500px;'>";
                echo "<option value='0'>Válassz szűréstipust!</option>";
                $resc = sql_query("select * from szurestipusok order by megnev");
                while ($rowc = sql_fetch_array($resc)) {
                    echo "<option value='{$rowc["id"]}'" . ($rowc["id"] == $rowb["szurestipusid"] ? " selected" : "") . ">{$rowc["megnev"]}</option>";
                }
                echo "</select> ";
                echo "<select name='cskapcsnemerequired{$sor}'>";
                echo "<option value='0'>Neme szükséges?</option>";
                echo "<option value='1'" . (1 == $rowb["nemerequired"] ? " selected" : "") . ">Csak férfiaknak</option>";
                echo "<option value='2'" . (2 == $rowb["nemerequired"] ? " selected" : "") . ">Csak nőknek</option>";
                echo "</select> ";
                echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delcskapcs={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd a csomagból?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
                echo "</div>";
                echo "</td></tr>";
                $sor++;
            }

            echo "<tr><td colspan='2'><div class='tdsepdiv'>Figyelmeztetések</div></td></tr>";
            echo "<tr><td colspan='2' valign=top><input type='submit' name='addtipmegj' value='+ figyelmeztetés hozzáadása'></td></tr>";

            $resb = sql_query("select * from szurestipusok_megj where tipusid=? and csomag=0 order by cegid", array($_GET["szerk"]));


            $sor = 1;
            while ($rowb = sql_fetch_array($resb)) {
                echo "<tr><td colspan='2'>";
                echo "<input type='hidden' name='tipmegjid{$sor}' value='{$rowb["id"]}'/>";
                echo "<div>";
                echo "<select name='tipmegjceg{$sor}' style='width:500px;'>";
                echo "<option value='0'>Válassz melyik céghez tartozik a megjegyzés!</option>";
                $resc = sql_query("select * from cegek order by megnev");
                while ($rowc = sql_fetch_array($resc)) {
                    echo "<option value='{$rowc["id"]}'" . ($rowc["id"] == $rowb["cegid"] ? " selected" : "") . ">{$rowc["megnev"]}</option>";
                }
                echo "</select> <a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&deltipmegj={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt az egységet?\")'><img src='images/trash.png' title='Sor törlése'/></a><br/>";
                echo "<textarea name='tipmegj{$sor}' style='width:520px;' placeholder='megjegyzés szövege...'>{$rowb["megj"]}</textarea>";
                echo "";
                echo "</div>";
                echo "</td></tr>";
                $sor++;
            }

            echo "<tr><td colspan='2'><div class='tdsepdiv'>Árak</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='submit' name='addprice' value='+ ár hozzáadása'></td></tr>";


            $resb = sql_query("select * from arak where tipusid=? and csomag=0 order by megnev", array($_GET["szerk"]));

            $sor = 1;

            echo "<tr><td colspan='2'>";
            echo "<table cellpadding='0' cellspacing='0'>";

            while ($rowb = sql_fetch_array($resb)) {
                echo "<tr>";
                echo "<td>";
                echo "<input type='hidden' name='arid{$sor}' id='arid{$sor}' value='{$rowb["id"]}'/>";
                echo "<div id='ceglist{$sor}' style='max-width:500px;'>" . $this->adminUtils->showCegListSzT($rowb["cegid"], $sor) . "</div>";
                echo "</td>";
                echo "<td><input type='text' name='megnev{$sor}' value='{$rowb["megnev"]}' style='width:350px;margin:2px 0px 2px 10px;' placeholder='megnevezés' /></td>";
                echo "<td><input type='text' name='price{$sor}' value='{$rowb["price"]}' style='width:50px;margin:2px 0px 2px 10px;' placeholder='ár'/>&nbsp;HUF</td>";
                echo "<td>&nbsp;<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&deltipar={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt az árat?\")'><img src='images/trash.png' title='Sor törlése'/></a><br/></td>";
                echo "</tr>";
                echo "<tr><td colspan='4'><div id='cegadd{$sor}' style='display:none;max-width:600px;'>" . $this->adminUtils->cegAddSorSzT($sor) . "</div></td></tr>";
                $sor++;
            }

            echo "</table>";
            echo "</td></tr>";

            echo "<tr><td colspan='2'>&nbsp;</td></tr>";
            echo "<tr><td colspan='2'><div class='tdsepdiv'>Leírás az info oldalra</div></td></tr>";
            echo "<tr><td colspan='2'><textarea name='infopagetext' style='height:80px;width:500px;'>{$row["infopagetext"]}</textarea></td></tr>";
            echo "</table>";

            echo "<br><input type='submit' name='szurestipusmentes' value='Mentés'> ";
            echo "<input type='submit' name='scancel' value='Vissza'> ";
            echo "</form>";

            $res = sql_query("SELECT b.*,o.`nev`,GROUP_CONCAT(DISTINCT c.`megnev` SEPARATOR ', ') AS cegnev FROM orvos_beosztas b
            LEFT JOIN orvosok o ON o.id=b.`orvosid`
            LEFT JOIN cegek c ON c.id=b.`cegid`
            WHERE INSTR(tipusok, ?) GROUP BY orvosid order by cegnev",array("|{$row["id"]}|"));

            if (sql_num_rows($res) > 0) {
                echo "<div class='tdsepdiv' style='margin-top:20px;'>{$_POST["megnev"]} orvosok</div>";
                echo "<table cellpadding='0' cellspacing='0' border='0'>";
                while ($row = sql_fetch_array($res)) {
                    $tc = "tcella";
                    if (!isset($first)) {
                        echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                        $first = 1;
                    }
                    if (empty(trim($row["nev"]))) {
                        continue;
                    }
                    echo "<tr>";
                    echo "<td nowrap valign='top'><div class='{$tc}'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page=orvosok&szerk={$row["orvosid"]}'>{$row["nev"]}</a></div></td>";
                    echo "<td valign='top'><div class='{$tc}'>{$row["cegnev"]}</div></td>";
                    echo "</tr>";
                    echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                }
                echo "</table>";
            }
            echo "</div>";
            return;
        }


        $res = sql_query("SELECT t.*,m.tipusid FROM szurestipusok t
        LEFT JOIN szurestipusok_megj m ON m.`tipusid`=t.`id` and csomag=0
        GROUP BY t.id
        ORDER BY !instr(megnev,'Új tétel'),megnev");

        $foStat = sql_fetch_array(sql_query("select count(*) as hany from szurestipusok where fotid<>0"));
        if (sql_num_rows($res) > 0 && Booking_Constants::FO_CONNECTION_ENABLED) {
            echo "<div style='margin-bottom: 10px;'>{$foStat["hany"]} típus szinkronizálva a foglaljorvost.hu-val - <a href='index.php?page={$_GET["page"]}&syncfofields'>frissítés</a></div>";
        }

        echo "<table cellpadding='0' cellspacing='0' border='0'>";
        echo "<tr><td colspan='7' style='background:#ccc;color:#fff;font-weight: bold;padding:5px;font-size:16px;'>Szűrések</td></tr>";
        while ($row = sql_fetch_array($res)) {
            $tc = "tcella";
            if (!isset($first)) {
                echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (empty(trim($row["megnev"]))) {
                $row["megnev"] = "nincs neve";
            }
            echo "<tr>";
            echo "<td valign='top'><div class='{$tc}'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["megnev"]}</a>";

            if ($row["ispack"] == 1) {
                echo "&nbsp;&nbsp;<span class='pack_badge'>CSOMAG</span>";
            }
            if ($row["fotid"] != 0) {
                echo "&nbsp;&nbsp;<span class='fo_badge' title='fo id: {$row["fotid"]}'>FOGLALJORVOST</span>";
            }
            $resa = sql_query("select a.*,c.megnev as cegnev from arak a left join cegek c on c.id=a.cegid where tipusid='{$row["id"]}' and price<>0 and a.csomag=0");
            $arak = "";
            while ($rowa = sql_fetch_array($resa)) {
                $arak .= "<span style='background:#0a0;color:#fff;padding:1px 3px;' title='{$rowa["cegnev"]}'>{$rowa["price"]} Ft</span> ";
            }
            if (!empty($arak)) {
                echo "<div>{$arak}</div>";
            }

            echo "</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>" . ($row["tipusid"] != null ? "<div style='background:#f00;color:#fff;padding:0px 3px;font-weight:bold;'>M</div>" : "") . "</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}' style='min-width:50px;'>" . ($row["aktiv"] == 1 ? "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>" : "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>") . "</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='return confirm(\"Biztosan törlöd ezt a szűréstipust?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
            echo "</tr>";
            echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        echo "</table>";

    }

}