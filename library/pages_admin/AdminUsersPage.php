<?php

class AdminUsersPage extends AdminCorePage {

    private $bookingService;

    private $w = "";

    public function __construct()
    {
        parent::__construct();

        $this->w = "u.cegid in (".$this->adminUtils->getCegList($_SESSION["adminuser"]["cegjog"]).") and u.cegid<>0";
        if ($_SESSION["adminuser"]["jogosultsag"] >= 2) {
            $this->w = "true";
        }


        if (isset($_POST["usermentes"]) || isset($_POST["userform"])) {
            $id = intval($_GET["szerk"]);

            sql_query("update users set	nev=?, email=?, tel=?, username=? where id=?",array($_POST["nev"], $_POST["email"], $_POST["tel"], $_POST["username"], $id));

            if ($_POST["password"]!="") sql_query("update users set password=md5(?)	where id=?",array($_POST["password"], $id));

            if ($_SESSION["adminuser"]["jogosultsag"]>=2 && $_SESSION["adminuser"]['jog_jogset']==1) {
				
				if($_SESSION["adminuser"]["jogosultsag"]==99) $_POST["jogosultsag"] = 99;
				
                if (!isset($_POST["jog_jogset"])) $_POST["jog_jogset"]=0;
                if (!isset($_POST["jog_cegset"])) $_POST["jog_cegset"]=0;
                if (!isset($_POST["jog_helyszinset"])) $_POST["jog_helyszinset"]=0;
                if (!isset($_POST["jog_orvosset"])) $_POST["jog_orvosset"]=0;
                if (!isset($_POST["jog_beosztasset"])) $_POST["jog_beosztasset"]=0;
                if (!isset($_POST["jog_szabi"])) $_POST["jog_szabi"]=0;
                if (!isset($_POST['jog_statisztika'])) $_POST['jog_statisztika']=0;
                if (!isset($_POST['jog_beallitasok'])) $_POST['jog_beallitasok']=0;
                if (!isset($_POST["jog_szurestipusset"])) $_POST["jog_szurestipusset"]=0;
                if (!isset($_POST["jog_nofoglimitset"])) $_POST["jog_nofoglimitset"]=0;
                if (!isset($_POST['jog_zarolista'])) $_POST['jog_zarolista']=0;
                if (!isset($_POST['jog_zaroszerk'])) $_POST['jog_zaroszerk']=0;
                if (!isset($_POST['jog_leletlatas'])) $_POST['jog_leletlatas']=0;
                if (!isset($_POST['jog_leletszerk'])) $_POST['jog_leletszerk']=0;
                if (!isset($_POST['jog_gdprhferes'])) $_POST['jog_gdprhferes']=0;
                if (!isset($_POST['jog_kuponlista'])) $_POST['jog_kuponlista']=0;
                if (!isset($_POST['jog_kuponkeszites'])) $_POST['jog_kuponkeszites']=0;
				if (!isset($_POST['jog_tranzakciolatas'])) $_POST['jog_tranzakciolatas']=0;
				if (!isset($_POST['jog_tranzakciokezeles'])) $_POST['jog_tranzakciokezeles']=0;
                if (!isset($_POST['jog_beutalokezeles'])) $_POST['jog_beutalokezeles']=0;
                if (!isset($_POST["jog_dokirexlekerdezesek"])) $_POST["jog_dokirexlekerdezesek"]=0;
                if (!isset($_POST["jog_salary"])) $_POST["jog_salary"]=0;
                if (!isset($_POST["jog_dicom"])) $_POST["jog_dicom"]=0;

                if (!isset($_POST["auth2fac"])) $_POST["auth2fac"]=0;
                if (!isset($_POST["localeaccess"])) $_POST["localeaccess"]=0;
                if (!isset($_POST["status"])) $_POST["status"]=0;

                sql_query("UPDATE users 
				   SET jog_jogset 	      = ?, jog_cegset 	      = ?, jog_helyszinset         = ?, jog_orvosset   = ?, jog_beosztasset     = ?, jog_szurestipusset    = ?, 
					   jog_szabi  	      = ?, jog_zarolista 	  = ?, jog_zaroszerk           = ?, jog_leletlatas = ?, jog_leletszerk      = ?, jog_gdprhferes 	   = ?, 
					   jog_kuponlista     = ?, jog_kuponkeszites  = ?, auth2fac	  	           = ?, localeaccess   = ?, localeip            = ?, jogosultsag           = ?,
					   jog_beallitasok    = ?, jog_nofoglimitset  = ?, jog_statisztika         = ?, jog_vizsg_stat = ?, jog_tranzakciolatas = ?, jog_tranzakciokezeles = ?, 
					   status             = ?, jog_beutalokezeles = ?, jog_dokirexlekerdezesek = ?, jog_salary     = ?, jog_dicom           = ?
				   WHERE id = ?",
                    array( $_POST["jog_jogset"], $_POST["jog_cegset"], $_POST["jog_helyszinset"], $_POST["jog_orvosset"], $_POST["jog_beosztasset"], $_POST["jog_szurestipusset"],
                        $_POST["jog_szabi"],  $_POST['jog_zarolista'], $_POST['jog_zaroszerk'], $_POST['jog_leletlatas'], $_POST['jog_leletszerk'], $_POST['jog_gdprhferes'],
                        $_POST['jog_kuponlista'], $_POST['jog_kuponkeszites'], $_POST["auth2fac"],   $_POST["localeaccess"],    $_POST["localeip"],     $_POST["jogosultsag"],
                        $_POST['jog_beallitasok'], $_POST['jog_nofoglimitset'], $_POST['jog_statisztika'],$_POST['jog_vizsg_stat'],$_POST['jog_tranzakciolatas'],$_POST['jog_tranzakciokezeles'],$_POST['status'],
						$_POST['jog_beutalokezeles'], $_POST["jog_dokirexlekerdezesek"], $_POST["jog_salary"], $_POST["jog_dicom"], $id)
                );

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
        if (!$this->adminUtils->userModJog()) {
            return;
        }

        $adminszintek = $this->adminUtils->settings->adminszintek;

        if (isset($_GET["szerk"])) {
            $row = sql_fetch_array(sql_query("select u.*,c.megnev as cegnev from users u left join cegek c on c.id=u.cegid where u.id=? and {$this->w}", array($_GET["szerk"])));
            $_POST = $row;

            echo "<div style='background-color:#fff;padding:0px;'>";
            echo "<form name='iform' id='iform' method='post' enctype='multipart/form-data'><input type='hidden' name='userform' value='1'/><input type='hidden' name='userid' value='{$_POST["id"]}'/>";
            echo "<table style='font-size:12px;'>";

            echo "<tr><td width='100'>Név:</td><td><input class='inputbox' style='width:400px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
            echo "<tr><td width='100'>Felhasználónév:</td><td><input autocomplete='off' class='inputbox' style='width:200px;' type='text' name='username' value='{$_POST["username"]}'></td></tr>";
            echo "<tr><td width='100'>E-mail:</td><td><input autocomplete='off' class='inputbox' style='width:200px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
            echo "<tr><td width='100'>Telefon:</td><td><input autocomplete='off' class='inputbox' style='width:100px;' type='text' name='tel' value='{$_POST["tel"]}'></td></tr>";
            echo "<tr><td width='100'>Új jelszó:</td><td><input autocomplete='off' class='inputbox' style='width:200px;' type='text' name='password' value=''></td></tr>";

            if ($_SESSION["adminuser"]["jogosultsag"]>=2 && $_SESSION["adminuser"]["jog_jogset"]==1) {
                echo "<tr><td width='100'>Jogosultság szint:</td><td>";
                echo "<select name='jogosultsag' onchange=\"if (this.value!=1) { $('#cegjogok').hide(); } else { $('#cegjogok').show(); }\">";
                for ($i=0;$i<count($adminszintek);$i++) {
                    if ($i>$_SESSION["adminuser"]["jogosultsag"]) break;
                    echo "<option value='{$i}'".($row["jogosultsag"]==$i?" selected":"").">{$adminszintek[$i]}</option>";
                }
                echo "</select> ";
                echo "</td></tr>";

                echo "<tr><td></td><td>";
                echo "<div id='cegjogok' style='".($row["jogosultsag"]<=1||$row['orvosid']!=""?"":"display:none;")."'>";

                $resh=sql_query("select * from cegek order by megnev");
                while ($rowh=sql_fetch_array($resh)) {
                    if ($_SESSION["adminuser"]["jogosultsag"]>=2) {
                        echo "<span style='white-space:nowrap;".(substr_count($_POST["cegjog"],"|{$rowh["id"]}|")?"font-weight:bold;color:#00f;":"")."'><input type='checkbox' name='cegjog{$rowh["id"]}' ".(substr_count($_POST["cegjog"],"|{$rowh["id"]}|")?"checked":"")." value='1' />&nbsp;{$rowh["megnev"]}</span> ";
                    } else {
                        if (substr_count($_POST["cegjog"],"|{$rowh["id"]}|")) echo "<span style='padding:2px 5px;white-space:nowrap;background:#888;color:#fff;'>{$rowh["megnev"]}</span> ";
                    }
                }


                echo "</div>";
                echo "</td></tr>";

                echo "<tr><td>Csak lokális elérés ip címek:</td><td><input class='inputbox' style='width:300px;' type='text' name='localeip' value='{$_POST["localeip"]}'> <input type='checkbox' name='localeaccess' ".($_POST["localeaccess"]==1?"checked":"")." value='1' />&nbsp;csak helyi elérés engedélyezése</td></tr>";
                echo "<tr><td></td><td><input type='checkbox' name='auth2fac' ".($_POST["auth2fac"]==1?"checked":"")." value='1' />&nbsp;2 faktoros authentikáció</td></tr>";
                echo "<tr><td></td><td><input type='checkbox' name='status' ".($_POST["status"]==1?"checked":"")." value='1' />&nbsp;aktiválás/deaktiválás</td></tr>";
                if ($row["jogosultsag"] >= 2) {
                    echo "<tr><td></td><td><input type='checkbox' name='jog_jogset' ".($_POST["jog_jogset"]==1?"checked":"")." value='1' />&nbsp;jogkörök kiosztása</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_beallitasok' ".($_POST["jog_beallitasok"]==1?"checked":"")." value='1' />&nbsp;Beállítások kezelése</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_statisztika' ".($_POST["jog_statisztika"]==1?"checked":"")." value='1' />&nbsp;Statisztika látása</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_cegset' ".($_POST["jog_cegset"]==1?"checked":"")." value='1' />&nbsp;cégek kezelése</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_helyszinset' ".($_POST["jog_helyszinset"]==1?"checked":"")." value='1' />&nbsp;helyszínek kezelése</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_orvosset' ".($_POST["jog_orvosset"]==1?"checked":"")." value='1' />&nbsp;orvosok kezelése</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_beosztasset' ".($_POST["jog_beosztasset"]==1?"checked":"")." value='1' />&nbsp;orvos beosztások kezelése</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_szabi' ".($_POST["jog_szabi"]==1?"checked":"")." value='1' />&nbsp;szabadságok beállítása</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_szurestipusset' ".($_POST["jog_szurestipusset"]==1?"checked":"")." value='1' />&nbsp;szűréstipusok kezelése</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_nofoglimitset' ".($_POST["jog_nofoglimitset"]==1?"checked":"")." value='1' />&nbsp; Korlátan időpontfoglalás</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_zarolista' ".($_POST["jog_zarolista"]==1?"checked":"")." value='1' />&nbsp;Zárólista látása</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_zaroszerk' ".($_POST["jog_zaroszerk"]==1?"checked":"")." value='1' />&nbsp;Záró leletek szerkesztése</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_vizsg_stat' ".($_POST["jog_vizsg_stat"]==1?"checked":"")." value='1' />&nbsp;Vizsgálati statisztika lekérdezése</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_dokirexlekerdezesek' ".($_POST["jog_dokirexlekerdezesek"]==1?"checked":"")." value='1' />&nbsp;Dokirex alapú lekérdezések</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_leletlatas' ".($_POST["jog_leletlatas"]==1?"checked":"")." value='1' />&nbsp;Leletek látása</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_leletszerk' ".($_POST["jog_leletszerk"]==1?"checked":"")." value='1' />&nbsp;Leletek szerkesztése</td></tr>";
					
					echo "<tr><td></td><td><input type='checkbox' name='jog_tranzakciolatas' ".($_POST["jog_tranzakciolatas"]==1?"checked":"")." value='1' />&nbsp;Tranzakciók látása</td></tr>";
					echo "<tr><td></td><td><input type='checkbox' name='jog_tranzakciokezeles' ".($_POST["jog_tranzakciokezeles"]==1?"checked":"")." value='1' />&nbsp;Tranzakciók kezelése</td></tr>";
					echo "<tr><td></td><td><input type='checkbox' name='jog_beutalokezeles' ".($_POST["jog_beutalokezeles"]==1?"checked":"")." value='1' />&nbsp;Beutalók kezelése</td></tr>";
					
                    echo "<tr><td></td><td><input type='checkbox' name='jog_gdprhferes' ".($_POST["jog_gdprhferes"]==1?"checked":"")." value='1' />&nbsp;GDPR hozzáférés</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_kuponlista' ".($_POST["jog_kuponlista"]==1?"checked":"")." value='1' />&nbsp;Kuponkód lista</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_kuponkeszites' ".($_POST["jog_kuponkeszites"]==1?"checked":"")." value='1' />&nbsp;Kuponkód hozzáadás/szerkesztés</td></tr>";

                    echo "<tr><td></td><td><input type='checkbox' name='jog_salary' ".($_POST["jog_salary"]==1?"checked":"")." value='1' />&nbsp;Jövedelem adatok megadása / statisztika</td></tr>";
                    echo "<tr><td></td><td><input type='checkbox' name='jog_dicom' ".($_POST["jog_dicom"]==1?"checked":"")." value='1' />&nbsp;DICOM / röntgen képekhez hozzáférés</td></tr>";
                }
            }

            echo "</table>";

            echo "<div id='errorlistdiv' style='padding:10px;background:#f00;color:#fff;font-weight:bold;display:none;'></div>";

            echo "<br/><input type='submit' name='usermentes' value='Mentés'> ";
            echo "<input type='submit' name='scancel' value='Vissza'> ";

            echo "</form>";
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
            //echo "<td nowrap valign='top'><div class='{$tc}' style='min-width:300px;'>{$row["cim"]}&nbsp;&nbsp;</div></td>";


            echo "<td valign='top'><div class='{$tc}'>";
            if (isset($cegList)) unset($cegList);
            if ($row["jogosultsag"]<2) {
                $j=explode("|",$row["cegjog"]);
                for ($i=0;$i<count($j);$i++) {
                    if (isset($cegek[$j[$i]])) {
                        $cegList[]=$cegek[$j[$i]];
                        //echo "<span style='padding:2px 5px;white-space:nowrap;background:#888;color:#fff;'>".$cegek[$j[$i]]."</span> ";
                    }
                }
            }
            echo "</div></td>";

            echo "<td nowrap valign='top'><div class='{$tc}'>{$adminszintek[$row["jogosultsag"]]}".(isset($cegList)?" (<span title='".(implode(", ", $cegList))."' style='border-bottom:1px dashed #888;'>".count($cegList)." cég</span>)":"")."</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["tel"]}";
            echo ($row["auth2fac"]==1?" <span title='kétfaktoros authentikáció' style='border:1px solid #f00;padding:1px 3px;color:#f00;'>2fac</span>":"").($row["localeaccess"]==1?" <span title='csak lokális belépés endedélyezett' style='border:1px solid #f00;padding:1px 3px;color:#f00;'>local</span>":"")."</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["email"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>".($row["lastlogin"]=="0000-00-00 00:00:00"?"":"Utolsó login: ".substr($row["lastlogin"],0,16))."</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='return confirm(\"Biztosan törlöd ezt a felhasználót?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
            echo "</tr>";
            echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        echo "</table>";

    }
}

