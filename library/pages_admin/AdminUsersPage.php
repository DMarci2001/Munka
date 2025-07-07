<?php

class AdminUsersPage extends AdminCorePage {

    private $bookingService;

    private $companyFilter = "";

    public function __construct()
    {
        parent::__construct();

        if (isset($_GET["szerk"]) && $_GET["szerk"] == "self") {
            $_GET["szerk"] = $this->adminUser->user["id"];
        }

        $this->companyFilter = "u.cegid in (".$this->adminUser->getCegList().") and u.cegid<>0";
        if ($this->adminUser->jogosultsagAccess()) {
            $this->companyFilter = "true";
        }

        if (isset($_POST["usersavecancel"])) {
            if ($this->adminUser->jogosultsagAccess()) {
                header("location:index.php?page={$_GET["page"]}");
            } else {
                header("location:index.php");
            }
            die;
        }

        if (isset($_POST["usermentes"]) || isset($_POST["userform"])) {
            $id = intval($_GET["szerk"]);
            $beoUserId = $_POST["beouserid"] ?? 0;

            sql_query("update users set	nev=?, email=?, tel=?, username=?, pecsetszam=?, beouserid=? where id=?",array($_POST["nev"], $_POST["email"], $_POST["tel"], $_POST["username"], $_POST["pecsetszam"], $beoUserId, $id));

            if ($_POST["password"]!="") sql_query("update users set password=md5(?)	where id=?",array($_POST["password"], $id));

            if ($this->adminUser->jogosultsagAccess()) {
                if (!isset($_POST["localeaccess"])) {
                    $_POST["localeaccess"] = 0;
                }
                if (!isset($_POST["status"])) {
                    $_POST["status"] = 0;
                }

                $permissions = [];
                foreach ($_POST as $key => $value) {
                    if (substr_count($key, "jog_")) {
                        $permissions["permissions"][$key] = $value;
                    }
                }

                $fields = "localeaccess=?, localeip=?, jogosultsag=?, status=?, permissions=?";
                $params = [$_POST["localeaccess"], $_POST["localeip"], $_POST["jogosultsag"], $_POST["status"], json_encode($permissions, JSON_PRETTY_PRINT)];

                $params[] = $id;

                sql_query("update users set {$fields} where id=?", $params);

                if ($this->adminUser->user["2facset"] == 1) {
                    if (!isset($_POST["auth2fac"])) {
                        $_POST["auth2fac"] = 0;
                    }
                    sql_query("update users set auth2fac=? where id=?", [$_POST["auth2fac"], $id]);
                }

                $jogs = "";
                $resh = sql_query("select * from cegek order by megnev");
                while ($rowh = sql_fetch_array($resh)) {
                    if (isset($_POST["cegjog{$rowh["id"]}"])) $jogs.="|{$rowh["id"]}|";
                }
                sql_query("update users set cegjog=? where id=?",array($jogs, $id));
            }

            logActivity("user",$id,$_POST["username"]." adatlap",print_r($_POST,true));

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }


    }

    public function showPage() {
        if (!$this->adminUser->jogosultsagAccess()) {
            $GLOBALS["nopageaccess"] = true;
            if (isset($_GET["szerk"]) && $_GET["szerk"] == $this->adminUser->user["id"]) {
                $this->companyFilter = "true";
                //saját magához mindenkinek van jogosultsága
            } else {
                echo $this->noPermissionMessage();
                return;
            }
        }

        if (isset($_GET["szerk"])) {
            $row = sql_fetch_array(sql_query("select u.*,c.megnev as cegnev from users u left join cegek c on c.id=u.cegid where u.id=? and {$this->companyFilter}", array($_GET["szerk"])));
            $row = $this->adminUser->buildPermissions($row);
            $_POST = $row;

            $loginCode = $row["logincode"] != "" ? "Kód: {$row["logincode"]}, eddig jó: {$row["authorizeduntil"]}":"";

            echo "<div style='background-color:#fff;padding:0px;'>";
            echo "<form name='iform' id='iform' method='post' enctype='multipart/form-data'><input type='hidden' name='userform' value='1'/><input type='hidden' name='userid' value='{$_POST["id"]}'/>";
            echo "<table style='font-size:12px;'>";

            echo "<tr><td width='150'>Név:</td><td><input class='inputbox' style='width:400px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
            echo "<tr><td>Felhasználónév:</td><td><input autocomplete='off' class='inputbox' style='width:200px;' type='text' name='username' value='{$_POST["username"]}'></td></tr>";
            echo "<tr><td>E-mail:</td><td><input autocomplete='off' class='inputbox' style='width:300px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
            echo "<tr><td>Telefon:</td><td><input autocomplete='off' class='inputbox' style='width:100px;' type='text' name='tel' value='{$_POST["tel"]}'></td></tr>";
            echo "<tr><td>Pecsétszám (ha orvos):</td><td><input class='inputbox' style='width:100px;' type='text' name='pecsetszam' value='{$_POST["pecsetszam"]}'></td></tr>";
            echo "<tr><td>Új jelszó:</td><td><input autocomplete='off' class='inputbox' style='width:200px;' type='text' name='password' value=''> {$loginCode}</td></tr>";

            echo "<tr><td colspan='2' style='padding:5px 0px;'></td></tr>";
            echo "<tr><td colspan='2' style='padding:5px 0px;border-top: 1px solid #888;'></td></tr>";
            echo "<tr><td>Beosztás összekapcsolás:</td><td>";
            echo "<select name='beouserid'>";

            $beoUsers = sql_query("select w.id, w.nev, w.teljesnev, r.megnev as role from schedule_workers w left join schedule_roles r on r.id=w.roleid order by concat(teljesnev,nev)")->fetchAll(PDO::FETCH_ASSOC);
            echo "<option value='0'>Nincs összekapcsolva</option>";
            foreach ($beoUsers as $beoUser) {
                $nev = empty($beoUser["teljesnev"]) ? $beoUser["nev"] : $beoUser["teljesnev"];
                //$nev .= empty($beoUser["role"]) ? "" : " ({$beoUser["role"]})";
                echo "<option value='{$beoUser["id"]}'".($row["beouserid"]==$beoUser["id"]?" selected":"").">{$nev}</option>";
            }

            echo "</select> ";
            echo "</td></tr>";

            if ($this->adminUser->jogosultsagAccess()) {
                echo "<tr><td colspan='2' style='padding:5px 0px;'></td></tr>";
                echo "<tr><td colspan='2' style='padding:5px 0px;border-top: 1px solid #888;'></td></tr>";
                //echo "<tr><td></td><td style='padding-bottom:5px;font-weight: bold;'>Jogosultságok</td></tr>";
                echo "<tr><td>Jogosultságok:</td><td>";
                echo "<select name='jogosultsag' onchange=\"if (this.value>=2) { $('#cegjogok').hide(); } else { $('#cegjogok').show(); }\">";
                echo "<option value='0'".($row["jogosultsag"]==0?" selected":"").">Csak a kiválasztott cégek (recepció)</option>";
                echo "<option value='1'".($row["jogosultsag"]==1?" selected":"").">Csak a kiválasztott cégek (kezelés)</option>";
                echo "<option value='2'".($row["jogosultsag"]==2?" selected":"").">Összes cég (kezelés is)</option>";
                echo "</select> ";
                echo "</td></tr>";

                echo "<tr><td></td><td>";
                echo "<div id='cegjogok' style='".($row["jogosultsag"]<=1?"":"display:none;")."'>";

                $resh=sql_query("select * from cegek order by megnev");
                while ($rowh=sql_fetch_array($resh)) {
                    if ($this->adminUser->companyPermissionAccess()) {
                        echo "<span style='white-space:nowrap;".(substr_count($_POST["cegjog"],"|{$rowh["id"]}|")?"font-weight:bold;color:#00f;":"")."'><input type='checkbox' name='cegjog{$rowh["id"]}' ".(substr_count($_POST["cegjog"],"|{$rowh["id"]}|")?"checked":"")." value='1' />&nbsp;{$rowh["megnev"]}</span> ";
                    } else {
                        if (substr_count($_POST["cegjog"],"|{$rowh["id"]}|")) {
                            echo "<span style='padding:2px 5px;white-space:nowrap;background:#888;color:#fff;'>{$rowh["megnev"]}</span> ";
                        }
                    }
                }

                echo "<br/><br/>";


                echo "</div>";
                echo "</td></tr>";

                echo "<tr><td></td><td>";

                echo "<div style='display:table-cell;vertical-align: top;'>";
                echo "<div style='font-weight: bold;margin-bottom: 5px;'>Oldalak elérése</div>";
                $existingKeys = [];
                $pages = sql_query("select group_concat(megnev separator ', ') as megnev, jogosultsag from adminmenu where aktiv=1 and jogosultsag<>'' group by jogosultsag order by megnev")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($pages as $page) {
                    $jogKey = $page["jogosultsag"];
                    $existingKeys[] = $jogKey;
                    echo "<div><input type='checkbox' name='{$jogKey}' ".(isset($_POST[$jogKey])&&$_POST[$jogKey]==1?"checked":"")." value='1' />&nbsp;".ucfirst($page["megnev"])."</div>";
                }
                echo "</div>";

                echo "<div style='display:table-cell;vertical-align: top;'>";
                echo "<div style='font-weight: bold;margin-bottom: 5px;'>Egyéb jogosultságok</div>";
                foreach (AdminUser::$jogosultsagLista as $jogKey => $jogosultsagData) {
                    if (!in_Array($jogKey, $existingKeys)) {
                        echo "<div><input type='checkbox' name='{$jogKey}' " . (isset($_POST[$jogKey])&&$_POST[$jogKey] == 1 ? "checked" : "") . " value='1' />&nbsp;" . ucfirst($jogosultsagData["name"]) . "</div>";
                    }
                }
                echo "</div>";

                echo "</td></tr>";

                echo "<tr><td colspan='2' style='padding:5px 0px;'></td></tr>";
                echo "<tr><td colspan='2' style='padding:5px 0px;border-top: 1px solid #888;'></td></tr>";
                echo "<tr><td>Csak lokális elérés ip címek:</td><td><input class='inputbox' style='width:300px;' type='text' name='localeip' value='{$_POST["localeip"]}'> <input type='checkbox' name='localeaccess' ".($_POST["localeaccess"]==1?"checked":"")." value='1' />&nbsp;csak helyi elérés engedélyezése</td></tr>";
                if ($this->adminUser->user["2facset"] == 1) {
                    echo "<tr><td></td><td><input type='checkbox' name='auth2fac' " . ($_POST["auth2fac"] == 1 ? "checked" : "") . " value='1' />&nbsp;2 faktoros authentikáció</td></tr>";
                }
                echo "<tr><td></td><td><input type='checkbox' name='status' ".($_POST["status"]==1?"checked":"")." value='1' />&nbsp;aktiválás/deaktiválás</td></tr>";
            }

            echo "</table>";

            echo "<div id='errorlistdiv' style='padding:10px;background:#f00;color:#fff;font-weight:bold;display:none;'></div>";

            $GLOBALS["savesubmitbutton"] = "iform";

            echo "<br/><input type='hidden' name='usermentes' value='Mentés'> ";

            echo "</form>";

            if (!empty($row["beouserid"])) {
                $service = new WorkScheduleService();

                echo "<div id='workerbeosztasdiv' style='padding:15px 0px 10px 0px;border-top: 1px solid #888;'>";
                echo $service->workerScheduleList($row["beouserid"]);
                echo "</div>";
            }

            echo "</div>";
            return;
        }



        $resh = sql_query("select * from cegek order by megnev");
        while ($rowh = sql_fetch_array($resh)) {
            $cegek[$rowh["id"]] = $rowh["megnev"];
        }

        $res = sql_query("SELECT u.* FROM users u where true ORDER BY !instr(u.nev,'új felh'),u.nev");

        echo "<table cellpadding='0' cellspacing='0' border='0'>";
        while ($row=sql_fetch_array($res)) {
            $tc="tcella";
            if (!isset($first)) {
                echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first=1;
            }
            if (trim($row["nev"])=="") $row["nev"]="nincs neve";
            echo "<tr>";
            echo "<td nowrap valign='top'><div class='{$tc}'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["nev"]}</a> ({$row["username"]})</div></td>";


            echo "<td valign='top'><div class='{$tc}'>";
            if (isset($cegList)) unset($cegList);
            if ($row["jogosultsag"]<2) {
                $j=explode("|",$row["cegjog"]);
                for ($i=0;$i<count($j);$i++) {
                    if (isset($cegek[$j[$i]])) {
                        $cegList[]=$cegek[$j[$i]];
                    }
                }
            }
            echo "</div></td>";



            echo "<td nowrap valign='top'><div class='{$tc}'>".$this->adminUser->getAdminLevel($row, true).(isset($cegList)?" (<span title='".(implode(", ", $cegList))."' style='border-bottom:1px dashed #888;'>".count($cegList)." cég</span>)":"")."</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["tel"]}";
            echo ($row["auth2fac"]==1?" <span title='kétfaktoros authentikáció' style='border:1px solid #f00;padding:1px 3px;color:#f00;'>2fac</span>":"").($row["localeaccess"]==1?" <span title='csak lokális belépés endedélyezett' style='border:1px solid #f00;padding:1px 3px;color:#f00;'>local</span>":"").($row["status"]==0?" <span title='inaktív felhasználó' style='border:1px solid #f00;background:#f00;padding:1px 3px;color:#fff;'>inaktív</span>":"")."</div></td>";
            //echo ($row["status"]==0?" <span title='inaktív felhasználó' style='border:1px solid #f00;background:#f00;padding:1px 3px;color:#fff;'>inaktív</span>":"")."</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["email"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>".($row["lastlogin"]=="0000-00-00 00:00:00"?"":"Utolsó login: ".substr($row["lastlogin"],0,16))."</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='return confirm(\"Biztosan törlöd ezt a felhasználót?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
            echo "</tr>";
            echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        echo "</table>";


        if (isset($_GET["addglobaljog"])) {
            $users = sql_query("select * from users_copy where jog_megjegyzes=1")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users as $u) {
                $user = sql_query("select * from users where id=?", [$u["id"]])->fetch(PDO::FETCH_ASSOC);
                $jogok = json_decode($user["permissions"], JSON_OBJECT_AS_ARRAY);

                if (!isset($jogok["permissions"]["jog_megjegyzes"])) {
                    $jogok["permissions"]["jog_megjegyzes"] = 1;
                    echo "<pre>{$user["username"]}".print_r($jogok, true)."</pre>";
                }

                sql_query("update users set permissions=? where id=?", [json_encode($jogok, JSON_PRETTY_PRINT), $user["id"]]);
            }
        }


        if (isset($_GET["convert"])) {
            $users = sql_query("select * from users")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users as $user) {
                $jogosultsagok = [];
                $jogok = [];

                $pages = sql_query("select * from adminmenu where aktiv=1 and jogosultsag<>''")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($pages as $page) {
                    $key = $page["jogosultsag"];
                    if (isset($user[$key]) && $user[$key] == 1) {
                        $jogok[$key] = $user[$key];
                    }
                }

                foreach (AdminUser::$jogosultsagLista as $key => $jogosultsagData) {
                    if (isset($user[$key]) && $user[$key] == 1) {
                        $jogok[$key] = $user[$key];
                    }
                }

                $jogosultsagok["permissions"] = $jogok;

                sql_query("update users set permissions=? where id=?", [json_encode($jogosultsagok, JSON_PRETTY_PRINT), $user["id"]]);
            }

        }

    }
}

