<?php

class AdminPatientsPage extends AdminCorePage {

    private $bookingService;

    private $w = "";
    private $bw = "";

    public function __construct()
    {
        parent::__construct();

        if( !isset( $_GET['scroll'] )) $_GET['scroll'] = 1;

        if (!$this->adminUser->allCegJog()) {
            $this->w = "and cegid in (" . $this->adminUser->getCegList() . ")";
            $this->bw = "and id in (" . $this->adminUser->getCegList() . ")";
        }

        if (!isset($_SESSION["cegfilter"])) $_SESSION["cegfilter"]=0;
        if ($_SESSION["cegfilter"] > 0) {
            $this->w.="and u.cegid='".addslashes($_SESSION["cegfilter"])."'";
        }
        if ($_SESSION["cegfilter"] == -1) {
            $this->w.="";
        }

        if (!isset($_SESSION["kereskulcs"])) {
            $_SESSION["kereskulcs"] = "";
        }
        if (isset($_POST["kereskulcs"])) {
            $_SESSION["kereskulcs"] = $_POST["kereskulcs"];
        }


        if (isset($_GET["deletefoglalas"])) {
            $id = $_GET["deletefoglalas"];
            if ($row = sql_fetch_array(sql_query("select * from foglalasok where id=? and pass=? and eljott=0",array($id, $_GET["p"])))) {
                $bookingService = new BookingService();
                $bookingService->deleteReservation($row["id"], $row["rkod"]);
            }
            header("location:index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_POST["dokmentes"])) {
            $docAgent = new DocAgent();
            $result = $docAgent->saveDoc($_FILES["dokfile"], array('beutaloid' => $_POST["beutaloid"], 'userid' => $_GET["szerk"], 'megnev' => $_POST["dokmegnev"]));

            if ($result!="0") {
                $_SESSION["uzenet"]=$result;
            } else {
                $rowf = sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_GET["szerk"])));
                logActivity("paciens",$rowf["id"],"{$rowf["nev"]} dokumentum feltöltése",print_r($_POST,true));
            }
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_POST["addbeutalo"])) {
            $data = explode("-",$_POST["beutalotarget"]);
            $hid = intval($data[0]);
            $sztid = intval($data[1]);

            sql_query("insert into beutalok set datum=now(),userid=?,cegid=?,helyszinid=?,szurestipusid=?,megj=?,naploszam=?",array($_GET["szerk"],$_POST["cegid"],$hid,$sztid,$_POST["beutalomegj"],$_POST["beutalonaploszam"]));

            $rowf = sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_GET["szerk"])));
            logActivity("paciens",$rowf["id"],"{$rowf["nev"]} beutaló hozzáadása",print_r($_POST,true));

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_GET["deletedoc"])) {
            $docAgent = new DocAgent();
            $docAgent->deleteDoc($_GET["deletedoc"], $_GET["kod"]);

            $rowf = sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_GET["szerk"])));
            logActivity("paciens",$rowf["id"],"{$rowf["nev"]} dokumentum törlése",print_r($_POST,true));

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_GET["deletebeutalo"])) {
            $rowf = sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_GET["szerk"])));
            logActivity("paciens",$rowf["id"],"{$rowf["nev"]} beutaló törlése",print_r($_POST,true));

            sql_query("delete from beutalok where id=? and userid=?",array($_GET["deletebeutalo"],$_GET["szerk"]));
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_POST["fcancel"])) {
            if (isset($_POST["back"])) {
                header("location:index.php?page={$_GET["page"]}&szerk={$_GET["fszerk"]}");
                die();
            }
            header("location:index.php?page={$_GET["page"]}");
            die();
        }

        if (isset($_POST["fregisztracio"])) {
            $id = intval($_GET["fszerk"]);
            $cegid = intval($_POST["cegid"]);

            if (isset($_POST["szuldatumev"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"]."-".substr("00".$_POST["szuldatumho"],-2)."-".substr("00".$_POST["szuldatumnap"],-2);
            }

            $_POST["telefon"] = $this->utils->fixPhoneNumber($_POST["telefon"]);

            $_POST["taj"] = str_replace("-","",$_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ","",$_POST["taj"]));

            //if ($_POST["taj"]=="") $this->formError.="A TAJ szám megadása kötelező!<br/>";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"]!="") $this->formError.="A TAJ szám formátuma nem megfelelő!<br/>";
            if ($_POST["taj"]!="" && sql_fetch_array(sql_query("select taj from felhasznalok where taj=? and cegid=? and id<>?", array($_POST["taj"], $cegid, $id)))) $this->formError.="Ehhez a TAJ számhoz már létezik regisztráció!<br/>";

            //if ($_POST["email"]=="") $this->formError.="Az e-mail cím megadása kötelező!<br/>";
            if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) && $_POST["email"]!="") $this->formError.="Az e-mail cím formátuma nem megfelelő!<br/>";
            if ($_POST["email"]!="" && sql_fetch_array(sql_query("select taj from felhasznalok where email=? and cegid=? and id<>?", array($_POST["email"], $cegid, $id)))) $this->formError.="Ezzel az e-mail címmel már létezik regisztráció!<br/>";

            if ($_POST["nev"]=="") $this->formError.="A név megadása kötelező!<br/>";
            //if ($_POST["telefon"]=="") $this->formError.="A telefonszám megadása kötelező!<br/>";
            if (!ctype_digit($_POST["telefon"]) && $_POST["telefon"]!="") $this->formError.="A telefonszám szám formátuma nem megfelelő!<br/>";

            //if ($_POST["szuldatum"]=="") $this->formError.="A születési dátum megadása kötelező!<br/>";
            //if (!$this->utils->validateDate($_POST["szuldatum"],"Y-m-d")) $this->formError.="A születési dátum formátuma nem megfelelő!<br/>";
            //if (!isset($_POST["neme"])) $this->formError.="A neme megadása kötelező!<br/>";
            //if (!$this->utils->checkSzulDatum($_POST["szuldatum"])) $this->formError.="A születési dátum formátuma nem megfelelő<br/>";

            //if ($_POST["munkakor"]=="") $this->formError.="A munkakör megadása kötelező!<br/>";

            if ($this->formError == "") {
                if ($id != 0) {
                    $this->patinentService->updatePatient($_POST, $id);

                    logActivity("paciens",$id,"{$_POST["nev"]} adatlap",print_r($_POST,true));
                    header("location:index.php?page={$_GET["page"]}&szerk={$id}");
                    die();
                } else {
                    $_POST["validated"] = 1;
                    $id = $this->patinentService->insertPatient($_POST);

                    logActivity("paciens",$id,"{$_POST["nev"]} bevitele",print_r($_POST,true));
                    header("location:index.php?page={$_GET["page"]}&szerk={$id}");
                    die();
                }
            }
        }




    }

    public function showPage() {
        //if (!$this->adminUtils->helyszinModJog()) {
        //    echo $this->noPermissionMessage();
        //    return;
        //}

        if (isset($_GET["fszerk"])) {
            $id=intval($_GET["fszerk"]);

            echo $this->showFormErrors();

            if (!isset($_POST["nev"])) {
                $_POST = $this->patinentService->getPatinentById($id);
            }

            if ($id != 0) {
                echo "<h2>Paciens adatai</h2>";
            }

            $GLOBALS["subtitle"] = $_POST["nev"];

            echo "<form name='iform' method='post' enctype='multipart/form-data'>";
            echo "<table style='font-size:12px;'>";

            if ($id != 0) {
                echo "<tr><td style='padding:4px 0px;'>Cég: </td><td>{$_POST["cegnev"]}<input type='hidden' name='cegid' value='{$_POST["cegid"]}'/></td></tr>";
            } else {
                $_SESSION["kereskulcs"] = "";

                $selected = $_SESSION["cegfilter"];
                if (isset($_POST["cegid"])) {
                    $selected = $_POST["cegid"];
                }
                echo "<tr><td>Cég: </td><td>";
                echo "<select name='cegid'>";
                $res = sql_query("SELECT * FROM cegek where true {$this->bw} order by megnev");
                while ($rowt = sql_fetch_array($res)) {
                    echo "<option value='{$rowt["id"]}'".($rowt["id"]==$selected?" selected":"").">{$rowt["megnev"]}</option>";
                }
                echo "</select>";
                echo "</td></tr>";
            }
            echo "<tr><td width='120'>TAJ szám: *</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' placeholder='Paciens TAJ száma' value='{$_POST["taj"]}'>";
            //audi
            if ($_SESSION["cegfilter"] == 15) {
                echo " [<a onclick='alert(\"TAJ szám nem található a törzs adatok között!\");' href='#'>Adatok automatikus kitöltése</a>]";
            }
            echo "</td></tr>";
            echo "<tr><td>Név: *</td><td><input class='inputbox' style='width:250px;' type='text' name='nev' placeholder='Név' value='{$_POST["nev"]}'></td></tr>";
            echo "<tr><td>E-mail: *</td><td><input class='inputbox' autocomplete='off' style='width:250px;' type='text' name='email' placeholder='Paciens e-mail címe' value='{$_POST["email"]}'></td></tr>";
            echo "<tr><td>Mobil telefonszám: *</td><td><input class='inputbox' style='width:250px;' type='text' name='telefon' value='{$_POST["telefon"]}' placeholder='Formátum pl: 06301234567' ></td></tr>";
            echo "<tr><td></td><td style='color:#888;'>A felhasználó a telefonszámával és a TAJ számával fog tudni bejelentkezni a felületre, ezért nagyon fontos a mobil telefonszámának is a pontos megadása.</td></tr>";
            echo "<tr><td>Születési dátum: *</td><td>";
            echo $this->utils->datumSelector($_POST["szuldatum"],"szuldatum");
            //echo "<input class='inputbox' style='width:120px;' type='text' name='szuldatum' placeholder='éééé-hh-nn' value='{$_POST["szuldatum"]}'>";
            echo "</td></tr>";

            if (!isset($_POST["neme"])) $_POST["neme"]=0;
            if (!isset($_POST["irsz"])) $_POST["irsz"]="";
            if (!isset($_POST["varos"])) $_POST["varos"]="";
            if (!isset($_POST["utca"])) $_POST["utca"]="";
            if (!isset($_POST["munkakor"])) $_POST["munkakor"]="";
            if (!isset($_POST["torzsszam"])) $_POST["torzsszam"]="";
            if (!isset($_POST["szulhely"])) $_POST["szulhely"]="";
            if (!isset($_POST["anyjaneve"])) $_POST["anyjaneve"]="";

            echo "<tr><td>Születési hely: *</td><td><input class='inputbox' style='width:250px;' type='text' name='szulhely' value='{$_POST["szulhely"]}' placeholder='' ></td></tr>";
            echo "<tr><td>Anyja neve: *</td><td><input class='inputbox' style='width:250px;' type='text' name='anyjaneve' value='{$_POST["anyjaneve"]}' placeholder='' ></td></tr>";
            echo "<tr><td>Neme:</td><td><input type='radio' name='neme' value='1' ".($_POST["neme"]==1?"checked":"")."/> Férfi&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_POST["neme"]==2?"checked":"")."/> Nő</td></tr>";
            echo "<tr><td>Irányítószám:</td><td><input class='inputbox' style='width:60px;' maxlength='4' type='text' name='irsz' value='{$_POST["irsz"]}'></td></tr>";
            echo "<tr><td>Város:</td><td><input class='inputbox' style='width:250px;' type='text' name='varos' value='{$_POST["varos"]}'></td></tr>";
            echo "<tr><td>Utca, házszám:</td><td><input class='inputbox' style='width:250px;' type='text' name='utca' value='{$_POST["utca"]}'></td></tr>";
            //echo "<tr><td>Munkáltató:</td><td><input class='inputbox' style='width:250px;' type='text' name='munkaltato' value='{$_POST["munkaltato"]}'></td></tr>";
            echo "<tr><td>Munkakör: </td><td><input class='inputbox' style='width:250px;' type='text' name='munkakor' value='{$_POST["munkakor"]}'></td></tr>";
            echo "<tr><td>Törzsszám: </td><td><input class='inputbox' style='width:250px;' type='text' name='torzsszam' autocomplete='false' value='{$_POST["torzsszam"]}'></td></tr>";
            if ($_GET["fszerk"]==0) {
                echo "<tr><td>Jelszó: </td><td><input class='inputbox' style='width:250px;' type='text' name='jelszo' value='' placeholder='Adja meg, hogy a paciens be tudjon jelentkezni'/></td></tr>";
            } else {
                echo "<tr><td>Jelszó: </td><td><input class='inputbox' style='width:250px;' type='text' name='jelszo' value='' placeholder='Csak akkor töltse ki, ha meg akarja változtatni.'/></td></tr>";
            }

            echo "</table>";

            if (isset($_REQUEST["back"])) echo "<input type='hidden' name='back' value='{$_REQUEST["back"]}' />";

            echo "<br><input type='submit' name='fregisztracio' value='Mentés'> <input type='submit' name='fcancel' value='Vissza'> ";
            echo "</form>";
            return;
        }



        if (isset($_GET["szerk"])) {
            $resb = sql_query("SELECT * FROM szurestipusok");
            while ($rowb = sql_fetch_array($resb)) {
                $szurestipusok[$rowb["id"]] = $rowb["megnev"];
            }


            $row = $this->patinentService->getPatinentById($_GET["szerk"]);

            $GLOBALS["subtitle"] = $row["nev"];

            echo "<div style='background-color:#fff;padding:0px;'>";

            echo "<h1>{$row["nev"]} ".($row["validated"]==1?"<span style='font-size:12px;color:#0a0;'>(aktíválva)</span>":"<span style='font-size:12px;color:#f00;border-bottom:1px dashed #888;cursor:pointer;' title='sms-ben kapott kód: {$row["rkod"]}'>(nem aktív)</span>")."</h1>";

            echo "<div style=''>Cég: {$row["cegnev"]} ".(!empty($row["munkakor"])?"({$row["munkakor"]})":"")."</div>";
            echo "<div style=''>TAJ: {$row["taj"]}</div>";
            if ($row["torzsszam"]!="") echo "<div style=''>Törzsszám: {$row["torzsszam"]}</div>";
            echo "<div style=''>Születési idő: {$row["szuldatum"]}</div>";
            if ($row["anyjaneve"]!="") echo "<div style=''>Anyja neve: {$row["anyjaneve"]}</div>";
            if ($row["telefon"]!="") echo "<div style=''>Telefon: {$row["telefon"]}</div>";
            if ($row["email"]!="") echo "<div style=''>E-mail: <a href='mailto:{$row["email"]}'>{$row["email"]}</a></div>";
            echo "<div>[<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&fszerk={$_GET["szerk"]}&back=szerk'>adatok módosítása</a>]</div>";

            //echo "<div style='margin-top:20px'>";
            //echo "<a href='/admin/templates/labor_protocol_02.php?szerk={$_GET["szerk"]}' class='printbutton' target='_blank'>Labor igénylő nyomtatása</a> ";
            //echo "<a href='/admin/templates/setalolap_02.php?szerk={$_GET["szerk"]}' class='printbutton' target='_blank'>Sétálólap nyomtatása</a>";
            //echo "</div>";

            $patientReservations = $this->patinentService->getPatientReservations($row["id"]);

            $reservationIds = [-1];

            if (!empty($patientReservations)) {
                echo "<div class='tdsepdiv' style='margin-top:20px;'>{$row["nev"]} időpont foglalásai</div>";
                echo "<table cellpadding='0' cellspacing='0' border='0'>";
                foreach ($patientReservations as $rowf) {
                    $tc = "tcella";
                    echo "<tr>";
                    echo "<td nowrap valign='top'><div class='{$tc}'>".substr($rowf["datum"],0,16).($rowf["beutalomegj"]!=""?" [<a href='#' onclick='$(\"#bmegj{$rowf["id"]}\").toggle();'>megj</a>]":"")."</div></td>";
                    echo "<td valign='top'><div class='{$tc}'>{$rowf["naploszam"]}</div></td>";
                    echo "<td valign='top'><div class='{$tc}'>{$rowf["cegnev"]}</div></td>";
                    echo "<td valign='top'><div class='{$tc}'>{$rowf["helyszin"]}</div></td>";
                    echo "<td valign='top'><div class='{$tc}'>{$rowf["szurestipus"]}</div></td>";
                    echo "<td valign='top'><div class='{$tc}'>{$rowf["orvos"]}</div></td>";
                    echo "<td valign='top'><div class='{$tc}'><a target='laborkero' href='index.php?page=laborkero&fid={$rowf["id"]}&p={$rowf["pass"]}'>+ Laborkérő</a></div></td>";
                    echo "</tr>";

                    echo "<tr id='bmegj{$rowf["id"]}' style='display:none;'><td colspan='7'><div style='display:inline-block;background:#eee;padding:5px;margin:0px 0px 10px 10px;'>".nl2br($rowf["beutalomegj"])."</div></td></tr>";
                    echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                    $reservationIds[] = $rowf["id"];
                }
                echo "</table>";
            }

            if ($this->adminUser->dicomAccess()) {
                $dicomPage = new AdminDicomPage();
                $dicomImagesHtml = $dicomPage->showImageList($row["taj"]);

                if (!empty($dicomImagesHtml)) {
                    echo "<div class='tdsepdiv' style='margin-top:20px;'>Röntgen felvételek</div>";
                    echo "<div style='margin:10px 0px 0px 0px;'>{$dicomImagesHtml}</div>";
                }
            }

            echo "<form name='dform' method='post' enctype='multipart/form-data'>";
            echo "<div class='tdsepdiv' style='margin-top:20px;'>Dokumentumok</div>";

            $resf = sql_query("select * from dokumentumok where userid='{$row["id"]}' order by datum desc");
            if (sql_num_rows($resf) > 0) {
                echo "<table style='font-size:12px;'>";
                while ($rowf=sql_fetch_array($resf)) {
                    if (trim($rowf["bno"])=="") $rowf["bno"]="nincs kitöltve";
                    echo "<tr>";
                    echo "<td>".substr($rowf["datum"],0,16)."&nbsp;&nbsp;&nbsp;</td>";
                    echo "<td>BNO: {$rowf["bno"]}&nbsp;&nbsp;&nbsp;</td>";
                    echo "<td>{$rowf["megnev"]}&nbsp;&nbsp;&nbsp;</td>";
                    echo "<td><a href='".DocAgent::getDocURL($rowf)."'>{$rowf["filename"]}</a>&nbsp;&nbsp;&nbsp;</td>";
                    echo "<td nowrap valign='top'><div>[<a onclick='return confirm(\"Biztosan törli ezt a dokumentumot?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}&deletedoc={$rowf["id"]}&kod={$rowf["kod"]}'>törlés</a>]</div></td>";
                    echo "</tr>";
                }
                echo "</table>";
            }	else {
                echo "<div style='color:#aaa;'>Nincs dokumentum feltöltve</div>";
            }

            echo "<div id='ftbutton' style='margin-top:10px;'><input onclick=\"$('#ftbutton').slideToggle();$('#ftform').slideToggle();\" type='button' value='+ Dokumentum feltöltése'></div>";

            echo "<div id='ftform' style='margin-top:10px;display:none;border:1px solid #ddd;background:#f0f0f0;padding:10px;'>";
            echo "<div>Melyik beutalóhoz tartozik:<br/>";

            echo "<select name='beutaloid'>";
            $resf=sql_query("SELECT b.*,c.domain,f.rkod FROM beutalok b left join felhasznalok f on f.id=b.userid LEFT JOIN cegek c ON c.`id`=b.`cegid` where b.userid='{$row["id"]}' order by b.datum desc");
            if (sql_num_rows($resf)>0) {
                while ($rowf=sql_fetch_array($resf)) {
                    echo "<option value='{$rowf["id"]}'>".substr($rowf["datum"],0,16)." - {$szurestipusok[$rowf["szurestipusid"]]}</option>";
                }
            }
            echo "</select>";

            echo "</div>";
            echo "<div>Dokumentum megnevezése:<br/><input class='inputbox' style='width:400px;' type='text' name='dokmegnev' id='dokmegnev' placeholder='Kérjük adjon nevet a dokumentumnak' value=''></div>";
            echo "<div style='margin-top:5px;'>BNO kód:<br/><input class='inputbox' style='width:200px;' type='text' name='bno' placeholder='Kérjük adja meg a BNO kódot' id='bno' value=''></div>";
            echo "<div style='margin-top:5px;'><input size='300' type='file' name='dokfile'></div>";
            echo "<div style='margin-top:5px;'><input onclick=\"if ($('#dokmegnev').val()=='') {alert('Nem adta meg a dokumentum megnevezését!');return false;}if ($('#bno').val()=='') {alert('Nem adta meg a BNO kódot!');return false;}\" type='submit' name='dokmentes' value='Feltöltés'> <input onclick=\"$('#ftbutton').slideToggle();$('#ftform').slideToggle();\" type='button' value='Mégse'></div>";
            echo "</div>";
            echo "</form>";

            echo "<form name='bform' method='post' enctype='multipart/form-data'>";
            echo "<div class='tdsepdiv' style='margin-top:20px;'>Beutalók</div>";

            $resb = sql_query("SELECT b.*,h.cim FROM orvos_beosztas_new b 
	        left join helyszinek h on h.id=b.helyszinid
	        WHERE (instr(b.beocegek, '|{$row["cegid"]}|') or b.beocegek='')");
            while ($rowb = sql_fetch_array($resb)) {
                $tipusok = explode("|", $rowb["tipusok"]);
                for ($i=0;$i<count($tipusok);$i++) {
                    $t = $tipusok[$i];
                    if (trim($t) != "" && isset($szurestipusok[$t])) {
                        $beutalohelyek["{$rowb["helyszinid"]}-{$t}"] = "{$szurestipusok[$t]} ({$rowb["cim"]})";
                    }
                }
            }

            $resf=sql_query("SELECT b.*,c.domain,f.rkod,r.id as foglalasid,r.pass as foglalaspass,r.datum as idopont FROM beutalok b
	        left join felhasznalok f on f.id=b.userid
	        LEFT JOIN cegek c ON c.`id`=b.`cegid` 
	        left join foglalasok r on r.id=b.foglalasid
	        where b.userid='{$row["id"]}' order by b.datum desc");

            if (sql_num_rows($resf)>0) {
                echo "<table style='font-size:12px;'>";
                echo "<tr style='font-weight:bold;'>";
                echo "<td width='100'>Rögzítve</td>";
                echo "<td width='100'>Naplószám</td>";
                echo "<td width='100'>Típus</td>";
                echo "<td width='100'>Időpont</td>";
                echo "</tr>";
                while ($rowf=sql_fetch_array($resf)) {
                    echo "<tr>";
                    echo "<td width='100'>".substr($rowf["datum"],0,16)."</td>";
                    echo "<td width='100'>{$rowf["naploszam"]}</td>";
                    echo "<td width='100'>{$szurestipusok[$rowf["szurestipusid"]]}</td>";
                    //echo "<td>".($rowf["foglalasid"]=="0"?"nincs felhasználva":"felhasználva")."</td>";
                    echo "<td nowrap valign='top'><div>";
                    if ($rowf["foglalasid"]==0) {
                        echo "[<a target='idopontfoglalo' href='//{$rowf["domain"]}.keltexmed.hu?fkod={$rowf["rkod"]}&fid={$rowf["userid"]}&remotereserve={$rowf["id"]}'>időpont foglalás</a>] ";
                        echo "[<a onclick='return confirm(\"Biztosan törli ezt a beutalót?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}&deletebeutalo={$rowf["id"]}'>törlés</a>]";
                    } else {
                        echo "Időpontja: ".substr($rowf["idopont"],0,16);
                        echo " [<a onclick='return confirm(\"Biztosan törli ezt a időpont foglalást?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}&deletefoglalas={$rowf["foglalasid"]}&p={$rowf["foglalaspass"]}'>foglalás törlése</a>]";
                    }
                    echo "</div></td>";
                    echo "</tr>";
                    if ($rowf["megj"]!="") {
                        echo "<tr><td colspan='5' style='color:#888;'>".substr($rowf["megj"],0,100)."</td></tr>";
                    }
                }
                echo "</table>";
            }	else {
                echo "<div style='color:#aaa;padding:2px;'>Még nincs beutalója</div>";
            }


            if (isset($beutalohelyek)) {
                asort($beutalohelyek);

                echo "<div id='beubutton' style='margin-top:10px;'><input onclick=\"$('#beubutton').slideToggle();$('#beuform').slideToggle();\" type='button' value='+ Beutaló kiadása'></div>";

                echo "<div id='beuform' style='margin-top:10px;display:none;border:1px solid #aaa;background:#eee;padding:10px;'>";
                echo "Hova kéri a beutalót:<br/><select style='width:400px;' name='beutalotarget' id='beutalotarget'>";

                echo "<option value='0'>Válasszon!</option>";
                foreach ($beutalohelyek as $key => $value) {
                    echo "<option value='{$key}'>{$value}</option>";
                }

                echo "</select>";
                echo "<div style='margin-top:5px;'>";
                echo "<div style='margin-top:5px;'>Naplószám:</div>";
                echo "<div><input type='text' name='beutalonaploszam' id='beutalonaploszam' style='width:400px;' /></div>";
                echo "<div style='margin-top:5px;'>Megjegyzés (üzenet az orvosnak):</div>";
                echo "<div><textarea name='beutalomegj' id='beutalomegj' style='width:400px;height:100px;'></textarea></div>";
                echo "</div>";
                echo "<input type='hidden' name='cegid' value='{$row["cegid"]}'/>";
                echo "<div style='margin-top:10px;'><input onclick=\"return validateBeutalo();\" type='submit' name='addbeutalo' value='Beutaló hozzáadása'> <input onclick=\"$('#beubutton').slideToggle();$('#beuform').slideToggle();\" type='button' value='Mégse'></div>";
                echo "</div>";
            } else {
                echo "<div style='color:#aaa;padding:2px;'>Nincs beosztás ehhez a céghez, beutaló kiadása nem lehetséges!</div>";
            }

            echo "</form>";


            if (isset($_SESSION["uzenet"])) {
                echo "<script>$(document).ready(function() { alert('{$_SESSION["uzenet"]}'); });</script>";
                unset($_SESSION["uzenet"]);
            }

            echo "</form>";
            echo "</div>";



            return;
        }


        //felhasználó lista

        if ($this->adminUser->vizsgStatAccess() && false) {
            echo "<div style='padding-bottom:20px'>";
            echo "<h3>Vizsgálat statisztikai lista letöltése</h3>";
            echo "<table>";
            echo "<tr>";
            echo "<td><input type='text' style='width:80px' id='vizsg_szures_start'/> - <input type='text' style='width:80px' id='vizsg_szures_end'/></td>";
            echo "<td><img onClick='downloadExamStat()' class='grayscale' src='../images/icon_xlsx.png' style='height:40px;'/></td>";
            echo "</tr>";
            echo "</table>";
            echo "</div>";
        }

        echo "<div style='margin-bottom:10px;'>";
        echo "<select name='cegselect' onchange='setCegFilter(this.value,\"patients\");'>";
        echo "<option value='0'>Szűrés cégre</option>";

        $res=sql_query("SELECT * FROM cegek where true {$this->bw} order by megnev");
        if (sql_num_rows($res) > 1) {
            echo "<option value='-1'".($_SESSION["cegfilter"]==-1?" selected":"").">Összes cég</option>";
        }
        while ($rowt = sql_fetch_array($res)) {
            echo "<option value='{$rowt["id"]}'".($_SESSION["cegfilter"]==$rowt["id"]?" selected":"").">{$rowt["megnev"]}</option>";
        }

        echo "</select>";
        echo "</div>";

        echo "<form name='keresform' method='post'>";
        echo "<input type='text' value='{$_SESSION["kereskulcs"]}' name='kereskulcs' placeholder='keresés névre, taj számra, email címre, szül. dátumra..' style='width:300px;'/> <input style='padding:3px 10px;' type='submit' value='Keresés' name='keresgo' />";
        echo "</form>";

        if (!isset($_SESSION["kereskulcs"]) || $_SESSION["kereskulcs"]=="") {
            echo "<h3>Legfrissebb regisztrációk</h3>";
        }
        if (isset($_SESSION["kereskulcs"]) && $_SESSION["kereskulcs"]!="") {
            echo "<h3>Keresés találatai</h3>";
            $kulcs = addslashes($_SESSION["kereskulcs"]);
            $this->w.="and ( instr(u.nev,'{$kulcs}') or instr(u.taj,'{$kulcs}') or instr(u.torzsszam,'{$kulcs}') or instr(u.szuldatum,'{$kulcs}'))";
        }

        $query = "SELECT u.*,c.megnev as cegnev FROM felhasznalok u
			  LEFT JOIN cegek c ON c.id = u.cegid
			  WHERE TRUE {$this->w}
			  ORDER BY u.regtime DESC";

        //Oldal számolás:
        $page_counter = sql_query("SELECT count(*) as hany FROm felhasznalok u WHERE TRUE {$this->w}")->fetch(PDO::FETCH_ASSOC);

        $page_numb = $page_counter["hany"] / 500;
        $page  = array();
        $range = 500;
        for ($i = 0; $i <= round($page_numb); $i++) {
            if ($page_numb < round($page_numb) && $i == round($page_numb)) {
                break;
            }
            $start_value = ($i * $range);
            $page[] = array( "number" => ( $i + 1 ), "limit" => "{$start_value}, 500");
        }

        //Ha olyan oldal szám szerepel az URL-ben ami irreleváns, akkor átirányít az első oldalra:
        if($_GET['scroll'] > count($page) || $_GET['scroll'] < 0) {
            $_GET["scroll"] = 1;
            //header("Location:index.php?page={$_GET["page"]}&scroll=1");
            //die;
        }

        echo "<a href='index.php?page={$_GET["page"]}&fszerk=0'>+ új felhasználó rögzitése</a>";

        $res = sql_query( $query." LIMIT {$page[($_GET['scroll']-1)]['limit']}" );


        echo "<table cellpadding='0' cellspacing='0' border='0' style='margin-top:10px;'>";
        while ($row = sql_fetch_array($res)) {
            $tc = "tcella";
            if (!isset($first)) {

                echo "<tr style='font-weight: bold;'>";
                echo "<td nowrap valign='top'><div class='{$tc}'>Regisztráció ideje</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>Név</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>Cég</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>TAJ</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>Telefon</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'></div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>Email</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'></div></td>";
                echo "</tr>";


                echo "<tr><td colspan='8' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first=1;
            }
            if (trim($row["nev"])=="") $row["nev"]="nincs neve";
            echo "<tr>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["regtime"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["nev"]}</a></div></td>";
            //echo "<td nowrap valign='top'><div class='{$tc}' style='min-width:300px;'>{$row["cim"]}&nbsp;&nbsp;</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["cegnev"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>TAJ: {$row["taj"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>Tel: {$row["telefon"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>".($row["validated"]==1?"<img width='12' src='images/check.png' title='Aktiválva' alt=''/>":"<span style='border-bottom:1px dashed #888;cursor:pointer;' title='sms-ben kapott kód'>{$row["rkod"]}</span>")."</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["email"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>[<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&fszerk={$row["id"]}'>szerk</a>] [<a onclick='return confirm(\"Biztosan törlöd ezt a felhasználót?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
            echo "</tr>";
            echo "<tr><td colspan='8' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        echo "<tr><td colspan='8' align='center' style = 'padding-top:10px'>";

        $pageout = "";
        $preHide = 0;
        foreach ($page as $key => $value) {
            if ($page[$key]['number'] == $_GET['scroll']) {
                $aStyle = "style='background-color: #2f8793; text-decoration: none;'";
            } else {
                $aStyle = "";
            }
            //Ha a lapszám több mint 10 akkor rejtse le a a fölösleges lap számot(de az 1.-t jelenítse meg.)
            if (($_GET['scroll']-10) > $key) {
                if ($preHide > 0) continue;
                $pageout.= "<a class = 'ujbutton' href = 'index.php?page={$_GET["page"]}&scroll=1' {$aStyle} >1</a>&nbsp;";
                $pageout.= "...&nbsp;";
                $preHide++;
                continue;
            }
            $pageout.= "<a class = 'ujbutton' href = 'index.php?page={$_GET["page"]}&scroll={$page[$key]['number']}' {$aStyle} >{$page[$key]['number']}</a>&nbsp;";
            //Ha lapszámhoz képest 8 értékkel nagyobb lapokat rejtse le, de az utolsót mutassa.
            if ($key == ( $_GET['scroll'] + 8)) {
                $pageout.= "...&nbsp;";
                $pageout.= "<a class = 'ujbutton' href = 'index.php?page={$_GET["page"]}&scroll=".count($page)."' {$aStyle} >".count($page)."</a>&nbsp;";
                break;
            }
        }

        if ($_GET["scroll"] != 1 || $page_numb > 1) {
            echo $pageout;
        }

        echo "</td></tr>";
        echo "</table>";
    }
}

