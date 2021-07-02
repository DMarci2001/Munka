<?php

class AdminCompaniesPage extends AdminCorePage {

    private $bookingService;

    private $optionalFields = [
        "taj"       => "TAJ szám",
        "szuldatum" => "Születési dátum",
        "szulhely"  => "Születési hely",
        "anyjaneve" => "Anyja neve",
        "neme"      => "Neme",
        "irsz"      => "Irányítószám",
        "varos"     => "Város",
        "utca"      => "Utca",
        "munkakor"  => "Munkakör"
    ];

    public function __construct()
    {
        parent::__construct();


        if (isset($_GET["delvisszaigazolo"])) {
            sql_query("delete from visszaigazolok where id='".addslashes($_GET["delvisszaigazolo"])."' and cegid='".addslashes($_GET["szerk"])."'");
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_POST["addvisszaigazolo"])) {
            sql_query("insert into visszaigazolok set cegid='".addslashes($_GET["szerk"])."'");
            $_POST["cegmentes"]=1;
        }

        if (isset($_GET["delcegvar"])) {
            sql_query("delete from cegvars where id='".addslashes($_GET["delcegvar"])."' and cegid='".addslashes($_GET["szerk"])."'");
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_POST["addcegvar"])) {
            sql_query("insert into cegvars set cegid='".intval($_GET["szerk"])."'");
            $_POST["cegmentes"]=1;
        }

        if (isset($_GET["delcegbeosztas"])) {
            sql_query("delete from cegbeosztasok where id='".intval($_GET["delcegbeosztas"])."' and cegid='".intval($_GET["szerk"])."'");
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_POST["addcegbeosztas"])) {
            sql_query("insert into cegbeosztasok set cegid='".intval($_GET["szerk"])."'");
            $_POST["cegmentes"]=1;
        }
		
        if (isset($_POST["cegmentes"])) {
            $id=intval($_GET["szerk"]);
            if ($this->adminUser->cegModAccess()) {
                $sor=1;
                while (isset($_POST["visszid{$sor}"])) {
                    sql_query("update visszaigazolok set 
                    helyszinid='".addslashes($_POST["helyszinid{$sor}"])."',
                    orvosid='".addslashes($_POST["orvosid{$sor}"])."',
                    mapurl='".addslashes(trim($_POST["mapurl{$sor}"]))."',
                    szoveg='".addslashes($_POST["szoveg{$sor}"])."'
                    where id='".addslashes($_POST["visszid{$sor}"])."'");
                    $sor++;
                }

                $sor=1;
                while (isset($_POST["cegvarid{$sor}"])) {
                    sql_query("update cegvars set 
                    varos='".addslashes($_POST["cegvarvaros{$sor}"])."',
                    megnev='".addslashes($_POST["cegvarmegnev{$sor}"])."'
                    where id='".addslashes($_POST["cegvarid{$sor}"])."'");
                    $sor++;
                }

                $sor=1;
                while (isset($_POST["cegbeosztasid{$sor}"])) {
                    sql_query("update cegbeosztasok set 
                    megnev='".addslashes($_POST["cegbeosztasmegnev{$sor}"])."'
                    where id='".addslashes($_POST["cegbeosztasid{$sor}"])."'");
                    $sor++;
                }

                $fieldOptions = [];
                foreach ($this->optionalFields as $field => $name) {
                    if (isset($_POST["fieldoption_{$field}"])) {
                        $option = $_POST["fieldoption_{$field}"];
                        if ($option == 0) {
                            $fieldOptions[] = "notreq_{$field}";
                        }
                        if ($option == 2) {
                            $fieldOptions[] = "hidden_{$field}";
                        }
                    }
                }

                if (!isset($_POST["aktiv"])) $_POST["aktiv"]=0;
                if (!isset($_POST["foglalasemail"])) $_POST["foglalasemail"]=0;
                if (!isset($_POST["onlyreg"])) $_POST["onlyreg"]=0;
                if (!isset($_POST["onlybeutalo"])) $_POST["onlybeutalo"]=0;
                if (!isset($_POST["tudoszuroopcio"])) $_POST["tudoszuroopcio"]=0;
                if (!isset($_POST["nocim"])) $_POST["nocim"]=0;
                if (!isset($_POST["noregsms"])) $_POST["noregsms"]=0;
                if (!isset($_POST["alksend"])) $_POST["alksend"]=0;
                if (!isset($_POST["alkertsend"])) $_POST["alkertsend"]=0;
                if (!isset($_POST["no_doctor_select"])) $_POST["no_doctor_select"]=0;
                if (!isset($_POST["dokirexTelephelyId"])) $_POST["dokirexTelephelyId"]="";

                sql_query("update cegek set megnev=?,domain=?,email=?,foglalasemail=?,onlyreg=?,nocim=?,visszaigazolas=?,onlybeutalo=?,tudoszuroopcio=?,smshour=?,beutaloszoveg=?,beutaloszoveg_de=?,beutaloszoveg_en=?,protokoll=?,aktiv=?,noregsms=?,alksend=?,alkertsend=?,alksendint=?,sendmail=?,nofoglalas_hu=?,nofoglalas_en=?,nofoglalas_de=?,fieldoptions=?,no_doctor_select=?,dokirexTelephelyId=? where id=?"
                    ,array($_POST["megnev"],$_POST["domain"],$_POST["email"],$_POST["foglalasemail"],$_POST["onlyreg"],$_POST["nocim"],$_POST["visszaigazolas"],$_POST["onlybeutalo"],$_POST["tudoszuroopcio"],$_POST["smshour"],$_POST["beutaloszoveg"],$_POST["beutaloszoveg_de"],$_POST["beutaloszoveg_en"],$_POST["protokoll"],$_POST["aktiv"],$_POST["noregsms"],$_POST["alksend"],$_POST["alkertsend"],$_POST["alksendint"],$_POST["sendmail"],$_POST["nofoglalas_hu"],$_POST["nofoglalas_en"],$_POST["nofoglalas_de"], implode(",",$fieldOptions), $_POST["no_doctor_select"],$_POST["dokirexTelephelyId"], $id));

                logActivity("ceg",$id,$_POST["megnev"]." adatlap",print_r($_POST,true));
            }
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$id}");
            die();
        }


        if (isset($_POST["adddoctortocompany"])) {
            $ids = explode("_", $_POST["adddoctortocompany"]);

            $orvosId = intval($ids[0]);
            $tipusId = intval($ids[1]);
            $cegId = intval($_POST["companyid"]);

            $beos = sql_query("SELECT * FROM orvos_beosztas WHERE orvosid=? AND INSTR(tipusok, ?) AND aktiv=1 GROUP BY CONCAT(nap, '_', beonap, '_', hetek, '_', tol, ig)", [$orvosId, "|{$tipusId}|"]);
            foreach ($beos as $beo) {
                sql_query("insert into orvos_beosztas set orvosid=?, helyszinid=?, nap=?, beonap=?, tol=?, ig=?, potig=?, hetek=?, binterval=?, cegid=?, csaksorban=?, tipusok=?, aktiv=1",
                [$orvosId, $beo["helyszinid"], $beo["nap"], $beo["beonap"], $beo["tol"], $beo["ig"], $beo["potig"], $beo["hetek"], $beo["binterval"], $cegId, $beo["csaksorban"], "|{$tipusId}|"]);
            }

            echo $this->_orvosAndServiceList($cegId);
            die;
        }

        if (isset($_POST["removedoctorfromcompany"])) {
            $ids = explode("_", $_POST["removedoctorfromcompany"]);

            $orvosId = intval($ids[0]);
            $tipusId = intval($ids[1]);
            $cegId = intval($_POST["companyid"]);

            $beos = sql_query("SELECT * FROM orvos_beosztas WHERE orvosid=? AND INSTR(tipusok, ?) AND cegid=?", [$orvosId, "|{$tipusId}|", $cegId]);
            foreach ($beos as $beo) {
                if ($beo["tipusok"] == "|{$tipusId}|") {
                    sql_query("delete from orvos_beosztas where id=? limit 1", [$beo["id"]]);
                } else {
                    $tipusok = str_replace("|{$tipusId}|", "", $beo["tipusok"]);
                    sql_query("update orvos_beosztas set tipusok=? where id=? limit 1", [$tipusok, $beo["id"]]);
                }
            }

            echo $this->_orvosAndServiceList($cegId);
            die;
        }

    }

    public function showPage() {
        if (!$this->adminUser->cegModAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        if (isset($_GET["szerk"])) {
            $row = sql_fetch_array(sql_query("select * from cegek where id=?", array($_GET["szerk"])));
            $_POST = $row;

            $GLOBALS["subtitle"] = $row["megnev"];

            echo "<div style=\"background-color:#fff;padding:0px;\">";
            echo "<form name=\"iform\" method=\"post\" enctype=\"multipart/form-data\">";
            echo "<input type='hidden' id='companyid' value='{$row["id"]}' />";
            echo "<table style=\"font-size:12px;\">";

            echo "<tr><td width=\"150\">Név:</td><td><input class=\"inputbox\" style=\"width:400px;\" type=\"text\" name=\"megnev\" value=\"{$_POST["megnev"]}\"></td></tr>";
            echo "<tr><td>Domain:</td><td>".Booking_Constants::SITE_PROTOCOL.":// <input class=\"inputbox\" style=\"width:100px;\" type=\"text\" name=\"domain\" value=\"{$_POST["domain"]}\"> .".Booking_Constants::SITE_DOMAIN."</td></tr>";
            echo "<tr><td>E-mail:</td><td><input class=\"inputbox\" style=\"width:300px;\" type=\"text\" name=\"email\" value=\"{$_POST["email"]}\"></td></tr>";
            echo "<tr><td>SMS a pacinenseknek:</td><td><input class=\"inputbox\" style=\"width:20px;\" type=\"text\" name=\"smshour\" value=\"{$_POST["smshour"]}\"> órával előtte</td></tr>";
            echo "<tr><td>Dokirex cég azonosító:</td><td><input class=\"inputbox\" style=\"width:40px;\" type=\"text\" name=\"dokirexTelephelyId\" value=\"{$_POST["dokirexTelephelyId"]}\"></td></tr>";
            echo "<tr><td>Figyelmeztető szöveg:</td><td><input class=\"inputbox\" style=\"width:600px;\" type=\"text\" name=\"beutaloszoveg\" value=\"{$_POST["beutaloszoveg"]}\"></td></tr>";
            echo "<tr><td>Figyelmeztető szöveg (német):</td><td><input class=\"inputbox\" style=\"width:600px;\" type=\"text\" name=\"beutaloszoveg_de\" value=\"{$_POST["beutaloszoveg_de"]}\"></td></tr>";
            echo "<tr><td>Figyelmeztető szöveg (angol):</td><td><input class=\"inputbox\" style=\"width:600px;\" type=\"text\" name=\"beutaloszoveg_en\" value=\"{$_POST["beutaloszoveg_en"]}\"></td></tr>";
            echo "<tr><td>Protokoll:</td><td><textarea class=\"inputbox\" style=\"width:600px;height:80px;\" type=\"text\" name=\"protokoll\">{$_POST["protokoll"]}</textarea></td></tr>";

            echo "<tr><td colspan=\"2\"><div style=\"margin-top:10px;padding-top:10px;border-top:1px solid #ccc;\"></div></td></tr>";

            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"aktiv\"".($_POST["aktiv"]==1?" checked":"")."> Aktív</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"onlyreg\"".($_POST["onlyreg"]==1?" checked":"")."> Csak regisztrációval lehessen foglalni</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"visszaigazolas\"".($_POST["visszaigazolas"]==1?" checked":"")."> Vissza kell igazolni a foglalást</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"onlybeutalo\"".($_POST["onlybeutalo"]==1?" checked":"")."> Csak beutalóval lehessen foglalni</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"nocim\"".($_POST["nocim"]==1?" checked":"")."> A rendelési cím ne, csak a cím megnevezése látszódjon a pacienseknek</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"foglalasemail\"".($_POST["foglalasemail"]==1?" checked":"")."> Menjen a foglalásokról e-mail értesítés</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"tudoszuroopcio\"".($_POST["tudoszuroopcio"]==1?" checked":"")."> Tüdőszűrő opció az üzemorvosi vizsgálatnál</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"alkertsend\"".($_POST["alkertsend"]==1?" checked":"")."> Alkalmassági lejártáról értesítés a pácienseknek</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"alksend\"".($_POST["alksend"]==1?" checked":"")."> Alkalmassági lista küldése</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"no_doctor_select\"".($_POST["no_doctor_select"]==1?" checked":"")."> Ne legyen orvos választás a foglalási folyamatban</td></tr>";
			//echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"bound_booking\"".($_POST["bound_booking"]==1?" checked":"")."> Foglalások korlátozása egy kiválasztott parameter alapján </td></tr>";
			
            echo "<tr><td>Rendszeresség: </td><td><select name=\"alksendint\">";
            echo "<option ".($_POST["alksendint"]=="napi"?" selected":"")." value=\"napi\">Napi</option>";
            echo "<option ".($_POST["alksendint"]=="heti"?" selected":"")." value=\"heti\">Heti</option>";
            echo "<option ".($_POST["alksendint"]=="havi"?" selected":"")." value=\"havi\">Havi</option>";
            echo "</select></td></tr>";
            echo "<tr><td>Fogadó email(ek): </td><td ><textarea class=\"inputbox\" name=\"sendmail\" style=\"width:600px;height:80px;\">".(isset($_POST["sendmail"])?$_POST["sendmail"]:"")."</textarea>";
            echo "</td></tr>";

            echo "<tr><td colspan=\"2\"><div class=\"tdsepdiv\">Foglalás mező paraméterek</div></td></tr>";

            foreach ($this->optionalFields as $field => $name) {
                echo $this->_fieldOptionsRow($field);
            }
			
            echo "<tr><td colspan='2'><div class='tdsepdiv'>Cég egységek</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='submit' name='addcegvar' value='+ Egység hozzáadása'></td></tr>";

            $resb = sql_query("select * from cegvars where cegid=? order by varos,megnev", array($_GET["szerk"]));

            $sor = 1;
            while ($rowb = sql_fetch_array($resb)) {
                echo "<tr><td colspan='2'>";
                echo "<input type='hidden' name='cegvarid{$sor}' value='{$rowb["id"]}'/>";
                echo "<div><input type='text' name='cegvarvaros{$sor}' style='width:195px;' placeholder='város...' value='{$rowb["varos"]}'/> <input type='text' name='cegvarmegnev{$sor}' style='width:395px;' placeholder='egység megnevezése...' value='{$rowb["megnev"]}'/>";
                echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delcegvar={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt az egységet?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
                echo "</div>";
                echo "</td></tr>";
                $sor++;
            }

            echo "<tr><td colspan='2'><div class='tdsepdiv'>Beosztások</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='submit' name='addcegbeosztas' value='+ Beosztás hozzáadása'></td></tr>";

            $resb = sql_query("select * from cegbeosztasok where cegid=? order by megnev", array($_GET["szerk"]));

            $sor=1;
            while ($rowb = sql_fetch_array($resb)) {
                echo "<tr><td colspan='2'>";
                echo "<input type='hidden' name='cegbeosztasid{$sor}' value='{$rowb["id"]}'/>";
                echo "<div><input type='text' name='cegbeosztasmegnev{$sor}' style='width:595px;' placeholder='beosztás megnevezése...' value='{$rowb["megnev"]}'/>";
                echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delcegbeosztas={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt a beosztást?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
                echo "</div>";
                echo "</td></tr>";
                $sor++;
            }



            echo "<tr><td colspan='2'><div class='tdsepdiv'>Visszaigazoló szövegek</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='submit' name='addvisszaigazolo' value='+ Visszaigaziló szöveg hozzáadása'></td></tr>";

            $w=$wc="";
            if (!$this->adminUser->allCegJog()) {
                $w = "and b.cegid in (".$this->adminUser->getCegList().")";
                $wc = "and id in (".$this->adminUser->getCegList().")";
            }

            $resb = sql_query("select * from visszaigazolok where cegid=? order by id", array($_GET["szerk"]));

            $sor = 1;
            while ($rowb = sql_fetch_array($resb)) {
                echo "<tr><td colspan='2'>";

                echo "<input type='hidden' name='visszid{$sor}' value='{$rowb["id"]}'/>";

                echo "<select name='helyszinid{$sor}' style='width:300px;'>";

                $resh = sql_query("SELECT * FROM helyszinek WHERE INSTR(ceglink,'|{$rowb["cegid"]}|') order by cim");

                echo "<option value='0'>Minden helyszín</option>";
                while ($rowh = sql_fetch_array($resh)) {
                    echo "<option value='{$rowh["id"]}'".($rowb["helyszinid"]==$rowh["id"]?" selected":"").">{$rowh["cim"]}</option>";
                }
                echo "</select> ";

                echo "<select name='orvosid{$sor}' style='width:300px;'>";

                $resh = sql_query("SELECT o.* FROM orvosok o LEFT JOIN orvos_beosztas b ON b.`orvosid`=o.`id` WHERE b.`cegid`='{$rowb["cegid"]}' GROUP BY o.id ORDER BY nev");

                if (sql_num_rows($resh) > 1) echo "<option value='0'>Minden orvos</option>";
                while ($rowh = sql_fetch_array($resh)) {
                    echo "<option value='{$rowh["id"]}'".($rowb["orvosid"]==$rowh["id"]?" selected":"").">{$rowh["nev"]}</option>";
                }
                echo "</select> ";

                echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delvisszaigazolo={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt a visszaigazoló szöveget?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
                if (trim($rowb["mapurl"])!="") echo "<a target='_blank' href='{$rowb["mapurl"]}'><img style='height:18px;padding:0px 0px 0px 3px;' src='images/mapicon.png' title='Térkép tesztelése'/></a>";
                echo "</div>";
                echo "<div><textarea name='szoveg{$sor}' style='width:595px;height:80px;' placeholder='szöveg a visszaigazoló levélbe...'>{$rowb["szoveg"]}</textarea></div>";
                echo "<div><input type='text' name='mapurl{$sor}' style='width:595px;' placeholder='google maps link...' value='{$rowb["mapurl"]}'/>";
                echo "</div>";

                echo "</td></tr>";
                $sor++;
            }

            echo "<tr><td colspan='2'><div class='tdsepdiv'>Nincs foglalás szöveg</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'>*ha ezek a mezők ki vannak töltve, akkor a foglalás nem lesz lehetséges ehhez a céghez, helyette ez a szöveg fog megjelenni (HTML tartalom használható)</td></tr>";

            echo "<tr><td colspan='2'><textarea placeholder='HU szöveg' class='inputbox' style='width:800px;height:80px;' type='text' name='nofoglalas_hu'>{$_POST["nofoglalas_hu"]}</textarea></td></tr>";
            echo "<tr><td colspan='2'><textarea placeholder='EN szöveg' class='inputbox' style='width:800px;height:80px;' type='text' name='nofoglalas_en'>{$_POST["nofoglalas_en"]}</textarea></td></tr>";
            echo "<tr><td colspan='2'><textarea placeholder='DE szöveg' class='inputbox' style='width:800px;height:80px;' type='text' name='nofoglalas_de'>{$_POST["nofoglalas_de"]}</textarea></td></tr>";

            echo "</table>";


            echo "<br/><input type='submit' name='cegmentes' value='Mentés'> ";
            echo "<input type='submit' name='scancel' value='Vissza'> ";

            echo "</form>";

            echo "<div class='tdsepdiv' style='margin-top:20px;'>{$_POST["megnev"]} orvosai és szolgáltatásai</div>";
            echo "<div id='doctorlist'>";
            echo $this->_orvosAndServiceList($row["id"]);
            echo "</div>";

            echo "</div>";
            echo "</div>";
            return;
        }


        $res = sql_query("SELECT * from cegek ORDER BY megnev<>'Új cég',megnev");

        echo "<table cellpadding='0' cellspacing='0' border='0'>";
        while ($row=sql_fetch_array($res)) {
            $tc="tcella";
            if (!isset($first)) {
                echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first=1;
            }
            if (trim($row["megnev"])=="") {
                $row["megnev"]="nincs neve";
            }

            $options = "";
            if ($row["onlyreg"]==1) $options.= "<div>Csak regisztráltaknak</div>";
            if ($row["onlybeutalo"]==1) $options.= "<div>Csak beutalóval lehet foglalni</div>";
            if ($row["no_doctor_select"]==1) $options.= "<div>Nincs orvos választás a foglalásnál</div>";
            if ($row["fieldoptions"]!="") $options.= "<div>".$this->displayFieldOptions($row["fieldoptions"])."</div>";

            echo "<tr>";
            echo "<td nowrap valign='top'><div class={$tc}><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["megnev"]}</a></div></td>";

            $url = Booking_Constants::SITE_PROTOCOL."://{$row["domain"]}.".Booking_Constants::SITE_DOMAIN;

            echo "<td nowrap valign='top'><div class='{$tc}'>".($row["domain"]==""?"":"{$url} (<a target='_blank' href='{$url}'>open</a>)")."</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}' style='min-width:300px;padding-right: 10px;'>{$options}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}' style='min-width:50px;'>".($row["aktiv"]==1?"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>":"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>")."</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='alert(\"Nem törölhető!\");return false;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
            echo "</tr>";
            echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        echo "</table>";
    }

    private function displayFieldOptions($options) {
        $options = str_replace("'","", $options);
        $options = str_replace(",",", ", $options);
        return $options;
    }

    private function _fieldOptionsRow($field) {
        $html ="<tr><td>".$this->optionalFields[$field].":</td><td>";

        $option = 1;
        if (substr_count($_POST["fieldoptions"],"notreq_{$field}")) {
            $option = 0;
        }
        if (substr_count($_POST["fieldoptions"],"hidden_{$field}")) {
            $option = 2;
        }

        $html.="<input name='fieldoption_{$field}' value='1' type='radio' ".($option==1?"checked":"")."/> kötelező ";
        $html.="<input name='fieldoption_{$field}' value='0' type='radio' ".($option==0?"checked":"")."/> nem kötelező ";
        $html.="<input name='fieldoption_{$field}' value='2' type='radio' ".($option==2?"checked":"")."/> elrejtés ";
        $html.="</td></tr>";
        return $html;
    }

    private function _orvosAndServiceList($cegId):string {
        $html = "";

        $res = sql_query("SELECT b.*,o.`nev`,GROUP_CONCAT(DISTINCT b.`tipusok` SEPARATOR '') AS tipusokok FROM orvos_beosztas b
	        LEFT JOIN orvosok o ON o.id=b.`orvosid`
	        LEFT JOIN cegek c ON c.id=b.`cegid`
	        WHERE b.cegid=? and b.aktiv=1 and nap<10 OR (nap=10 AND beonap>=DATE(NOW())) and o.parentoid=0 GROUP BY orvosid ORDER BY o.nev", [$cegId]);

        $rest = sql_query("select * from szurestipusok");
        while ($rowt = sql_fetch_array($rest)) {
            $tipusnevek[$rowt["id"]] = $rowt["megnev"];
        }

        $existingOrvos = [];
        if (sql_num_rows($res)>0) {
            $html.= "<table cellpadding='0' cellspacing='0' border='0'>";
            while ($row=sql_fetch_array($res)) {
                if (trim($row["nev"])=="") continue;

                $ta=explode("|",$row["tipusokok"]);
                $tipusok = [];
                for ($i=0;$i<count($ta);$i++) {
                    if (isset($tipusnevek[$ta[$i]])) {
                        $tipusok[] = ["nev" => $tipusnevek[$ta[$i]], "id" => $ta[$i]];
                        $existingOrvos[$row["orvosid"]][$ta[$i]] = 1;
                    }
                }

                $tc="tcella";

                @$tipusok = array_unique($tipusok);

                $html.= "<tr>";
                $html.= "<td nowrap valign='top'><div class='{$tc}'><a style='color:#00f;' target='_blank' href='{$_SERVER["PHP_SELF"]}?page=doctors&szerk={$row["orvosid"]}'>{$row["nev"]}</a></div></td>";
                //echo "<td valign='top'><div class='{$tc}'>{$row["tipusokok"]}</div></td>";
                $html.= "<td valign='top'><div class='{$tc}'>";
                foreach ($tipusok as $tipus) {
                    $html.= $tipus["nev"]."&nbsp;&nbsp;&nbsp;<a onclick='removeDoctorFromCompany(\"{$row["orvosid"]}_{$tipus["id"]}\");return false;' title='szolgáltatás eltávolítása' href='#'><i class='fas fa-trash-alt'></i></a>";
                }
                $html.= "</div></td>";
                $html.= "</tr>";
                $html.= "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
            }
            $html.= "</table>";
        } else {
            $html.= "<div style='margin-top: 10px;'>Nincs a céghez orvos kapcsolva</div>";
        }

        $html.= "<div style='margin-top: 10px;'>";
        $html.= "<select id='doctoridtocompany'>";
        $html.= "<option value='0'>Válassz orvost és szolgáltatást</option>";

        $doctors = sql_query("SELECT o.nev, GROUP_CONCAT(tipusok SEPARATOR '') AS alltipus, b.* FROM orvos_beosztas b
                LEFT JOIN orvosok o ON o.id = b.`orvosid`
                WHERE b.aktiv=1 and nap<10 OR (nap=10 AND beonap>=DATE(NOW())) and o.parentoid=0
                GROUP BY orvosid")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($doctors as $doctor) {
            $tipusok = explode("|", $doctor["alltipus"]);
            $tipusok = array_unique($tipusok);
            foreach ($tipusok as $tipus) {
                if (isset($tipusnevek[$tipus]) && !isset($existingOrvos[$doctor["orvosid"]][$tipus])) {
                    $html.= "<option value='{$doctor["orvosid"]}_{$tipus}'>{$doctor["nev"]} - {$tipusnevek[$tipus]}</option>";
                }
            }
        }

        $html.= "</select>&nbsp;";
        $html.= "<a onclick='addDoctorToCompany();return false;' href='#' class='ujbutton'>Hozzáadás</a>";
        $html.= "</div>";

        return $html;
    }

}

