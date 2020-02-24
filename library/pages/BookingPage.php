<?php

class BookingPage extends CorePage {

    private $bookingService;
    public $beutaloData = null;

    public function __construct()
    {
        parent::__construct();

        $this->bookingService = new BookingService();
        $webText = $this->lang->webText;
		
		echo "<pre>";
		print_r($_SESSION);
		echo "</pre>";

        if (isset($_GET["showpaciensfiles"])) {
            echo $this->utils->showPaciensFiles();
            die();
        }

        if (isset($_POST["deletepaciensfile"])) {
            $docAgent = new DocAgent();
            $docAgent->deleteDoc($_POST["id"], $_POST["k"]);
            echo $this->utils->showPaciensFiles();
            die();
        }

        if (isset($_REQUEST["addpaciensfiles"])) {
            if (!isset($_SESSION["filefix"])) $_SESSION["filefix"] = rand(10000, 99999);
            $fileFix = $_SESSION["filefix"];

            $docAgent = new DocAgent();

            foreach ($_FILES as $file) {
                $sess = $fileFix . session_id();
                $result = $docAgent->saveDoc($file, array('beutaloid' => 0, 'userid' => 0, 'megnev' => '', 'sess' => $sess));
                if ($result != "0") {
                    echo $result;
                    die;
                }
            }
            die();
        }

        if (isset($_GET["remotereserve"])) {
            if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where id=? and rkod=?",array($_GET["fid"], $_GET["fkod"])))) {
                $_SESSION["remotebeutalo"] = $_GET["remotereserve"];
                $_SESSION["loggeduser"] = $rowu["id"];
                header("location:index.php?setbeutalo=" . intval($_GET["remotereserve"]));
                die();
            }
        }

        if (isset($_GET["setbeutalo"])) {
            if ($row = sql_fetch_array(sql_query("select * from beutalok where id=? and userid=?",array($_GET["setbeutalo"], $_SESSION["user"]["id"])))) {
                $_SESSION["beutaloid"] = $row["id"];
            }
            header("location:index.php?page=booking");
            die();
        }


        if (isset($_POST["idopontfoglalas"])) {
            //nem kötelező mezők létrehozása ha nincsenek
            if (!isset($_POST["taj"]))       $_POST["taj"] = "";
            if (!isset($_POST["szulhely"]))  $_POST["szulhely"] = "";
            if (!isset($_POST["anyjaneve"])) $_POST["anyjaneve"] = "";
            if (!isset($_POST["irsz"]))      $_POST["irsz"] = "";
            if (!isset($_POST["varos"]))     $_POST["varos"] = "";
            if (!isset($_POST["utca"]))      $_POST["utca"] = "";
            if (!isset($_POST["munkakor"]))  $_POST["munkakor"] = "";
            if (!isset($_POST["szuldatum"])) $_POST["szuldatum"] = "";
            if (!isset($_POST["nev"]))       $_POST["nev"] = "";
            if (!isset($_POST["telefon"]))   $_POST["telefon"] = "";
            if (!isset($_POST["neme"]))      $_POST["neme"] = 0;

            if (isset($_POST["szuldatumev"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            }

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            if (!$this->utils->getFieldHidden("taj") && $this->utils->getFieldRequired("taj")) {
                if (empty($_POST["taj"])) {
                    $this->errors[] = "{$webText["tajkotelezo"]}";
                }
            }

            //if ($_POST["taj"] == "") $this->errors[] = "{$webText["tajkotelezo"]}";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $this->errors[] = "{$webText["tajformat"]}";
            if ($_POST["helyszin"] == "0") $this->errors[] = "{$webText["helyszinkotelezo"]}";
            if ($_POST["datum"] == "") $this->errors[] = "{$webText["idopontkotelezo"]}";
            if ($_POST["szurestipus"] == "0") $this->errors[] = "{$webText["szurestipuskotelezo"]}";

            $this->bookingService->setSzuresTipus($_POST["szurestipus"]);
            $this->bookingService->setHelyszin($_POST["helyszin"]);
            $this->bookingService->setNeme($_POST["neme"]);

            if (!$this->utils->getFieldHidden("email") && $this->utils->getFieldRequired("email")) {
                if (empty($_POST["email"])) {
                    $this->errors[] = "{$webText["emailkotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("nev") && $this->utils->getFieldRequired("nev")) {
                if (empty($_POST["nev"])) {
                    $this->errors[] = "{$webText["nevkotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("telefon") && $this->utils->getFieldRequired("telefon")) {
                if (empty($_POST["telefon"])) {
                    $this->errors[] = "{$webText["telkotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("szulhely") && $this->utils->getFieldRequired("szulhely")) {
                if (empty($_POST["szulhely"])) {
                    $this->errors[] = "{$webText["szuletesidatum"]}!";
                }
            }
            if (!$this->utils->getFieldHidden("szuldatum") && $this->utils->getFieldRequired("szuldatum")) {
                if (empty($_POST["szuldatum"])) {
                    $this->errors[] = "{$webText["szulkotelezo"]}";
                }
                if (!$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) {
                    $this->errors[] = "{$webText["szulformat"]}";
                }
            }
            if (!$this->utils->getFieldHidden("irsz") && $this->utils->getFieldRequired("irsz")) {
                if (empty($_POST["irsz"])) {
                    $this->errors[] ="Az irányítószám megadása kötelező!";
                }
            }
            if (!$this->utils->getFieldHidden("varos") && $this->utils->getFieldRequired("varos")) {
                if (empty($_POST["varos"])) {
                    $this->errors[] ="A város megadása kötelező!";
                }
            }
            if (!$this->utils->getFieldHidden("utca") && $this->utils->getFieldRequired("utca")) {
                if (empty($_POST["utca"])) {
                    $this->errors[] ="Az utca megadása kötelező!";
                }
            }
            if (!$this->utils->getFieldHidden("munkakor") && $this->utils->getFieldRequired("munkakor")) {
                if (empty($_POST["munkakor"])) {
                    $this->errors[] = "{$webText["munkakorkotelezo"]}";
                }
            }
            if (!$this->utils->getFieldHidden("anyjaneve") && $this->utils->getFieldRequired("anyjaneve")) {
                if (empty($_POST["anyjaneve"])) {
                    $this->errors[] = "{$webText["anyjaneve"]}!";
                }
            }
            if (!$this->utils->getFieldHidden("neme") && $this->utils->getFieldRequired("neme")) {
                if (empty($_POST["neme"])) {
                    $this->errors[] = "{$webText["nemekotelezo"]}";
                }
            }
            if (!isset($_POST["aszf"])) {
                $this->errors[] = "{$webText["aszfkotelezo"]}";
            }

            if (isset($_POST["telephely"]) && empty(trim($_POST["telephely"]))) {
                $this->errors[] = "{$webText["telephelykotelezo"]}";
            }

            //if ($rowe=sql_fetch_array(sql_query("select id,datum,rkod from foglalasok where cegid='".addslashes($_SESSION["helyszindata"]["id"])."' and taj='".addslashes($_POST["taj"])."' and now()<datum"))) {
            //	$this->errors[] ="Már van egy foglalása ".substr($rowe["datum"],0,16)." időpontra. Ha újra szeretne foglalni, kérjük törölje az előző foglalását! <a style='color:#ff0;' href='index.php?page=torles&id={$rowe["id"]}&rk={$rowe["rkod"]}'>Időpont törlése</a>";
            //}

            if ($_POST["datum"] != "" && !$this->bookingService->checkIdopontSzabad($_POST)) {
                $this->errors[] = "{$webText["idopontlefoglaltak"]}";
            }
            if (!isset($_POST["rinterval"])) $_POST["rinterval"] = 0;
            if (!isset($_POST["telephely"])) $_POST["telephely"] = "";

            if (!isset($_SESSION["user"])) {
                $captchaError = $this->utils->checkCaptcha();
                if (!empty($captchaError)) {
                    $this->errors[] = $captchaError;
                }
            }

            if (empty($this->errors)) {
                $forwardURL = $this->bookingService->addReservation($_POST);

                header("location:{$forwardURL}");
                die();
            }
        }


    }

    public function showPage() {
        $webText = $this->lang->webText;

        if (!isset($_POST["helyszin"])) {
            $_POST["helyszin"] = $_POST["szurestipus"] = "";
        }
        if (!isset($_POST["email"])) {
            $_POST["datum"] = $_POST["email"] = $_POST["nev"] = $_POST["telefon"] = $_POST["szuldatum"] = $_POST["taj"] = $_POST["irsz"] = $_POST["varos"] = $_POST["utca"] = $_POST["munkaltato"] = $_POST["munkakor"] = $_POST["nev"] = $_POST["nev"] = $_POST["megj"] = $_POST["captcha"] = $_POST["szulhely"] = $_POST["anyjaneve"] = $_POST["telephely"] = "";
            $_POST["rinterval"] = 0;
            if (isset($_SESSION["user"])) {
                $_POST["taj"]       = $_SESSION["user"]["taj"];
                $_POST["email"]     = $_SESSION["user"]["email"];
                $_POST["nev"]       = $_SESSION["user"]["nev"];
                $_POST["telefon"]   = $_SESSION["user"]["telefon"];
                $_POST["szuldatum"] = $_SESSION["user"]["szuldatum"];
                $_POST["szulhely"]  = $_SESSION["user"]["szulhely"];
                $_POST["anyjaneve"] = $_SESSION["user"]["anyjaneve"];
                $_POST["irsz"]      = $_SESSION["user"]["irsz"];
                $_POST["varos"]     = $_SESSION["user"]["varos"];
                $_POST["utca"]      = $_SESSION["user"]["utca"];
                $_POST["munkakor"]  = $_SESSION["user"]["munkakor"];
                $_POST["neme"]      = $_SESSION["user"]["neme"];
            }
        }

        if (!isset($_POST["neme"])) {
            $_POST["neme"] = "";
        }

        echo $this->displayFejlec();
        echo $this->showErrors();

        if ($_SESSION["helyszindata"]["onlybeutalo"]==1) {
            $_SESSION["helyszindata"]["onlyreg"] = 1;
        }

        if ($_SESSION["helyszindata"]["onlybeutalo"]==1 && isset($_SESSION["user"]) && !isset($_SESSION["beutaloid"])) {
            echo "<div style=''>{$webText["csakbeutalodesc"]}</div>";
            echo "<div style='margin-top:10px;'><a class='simabutton' href='index.php?page=beutalok'>{$webText["showbeutalobutton"]}</a></div>";
            return;
        }

        if (isset($_SESSION["beutaloid"])) {
            if (!$beutalodata = sql_fetch_array(sql_query("select * from beutalok where id=? and foglalasid=0", array($_SESSION["beutaloid"])))) {
                echo "<div style=''>A beutalóval probléma adodott!</div>";
                echo "<div style='margin-top:10px;'><a class='simabutton' href='index.php?page=beutalok'>{$webText["showbeutalobutton"]}</a></div>";
                return;
            }
        }

        if ($_SESSION["helyszindata"]["onlyreg"]==1 && !isset($_SESSION["user"])) {
            $btext = $webText["mainudvozles"];

            if ($rowsz=sql_fetch_array(sql_query("select * from szovegek where cegid=? and tipus='welcome'",array($_SESSION["helyszindata"]["id"])))) {
                $btext = $rowsz["szoveg"];
            }

            echo "<div style=''>{$btext}</div>";

            echo "<div style='margin-top:20px;'><a href='index.php?page=registration' class='newbutton'>{$webText["regisztracio"]}</a>&nbsp;&nbsp;<a href='index.php?page=login' class='newbutton'>{$webText["bejelentkezes"]}</a></div>";
            return;
        }

        if ($this->isExtendedForm()) {
            echo $this->_preSelectForm();
            return;
        }

        echo "<form name='iform' id='iform' method='post' enctype='multipart/form-data'>";
        echo "<table>";

        echo $this->utils->dataField("taj");
        //echo "<tr><td width='140'>{$webText["tajszam"]}: *</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' value='{$_POST["taj"]}'></td></tr>";

        //Kérjük akkut egészségkárosodás vagy életveszély esetén azonnal hívja az 104-es országos mentőszolgálat vagy a 112 központi segélyhívót.

        if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_COOKIE["lang"]!="hu" && trim($_SESSION["helyszindata"]["beutaloszoveg_{$_COOKIE["lang"]}"])!="") {
            $_SESSION["helyszindata"]["beutaloszoveg"] = $_SESSION["helyszindata"]["beutaloszoveg_{$_COOKIE["lang"]}"];
        }


        if (isset($beutalodata)) {
            //beutalóval fix választás

            if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_SESSION["helyszindata"]["beutaloszoveg"]!="") {
                echo "<tr><td></td><td><div style='font-weight:bold;padding:5px 0px;'>{$_SESSION["helyszindata"]["beutaloszoveg"]}</div><td></tr>";
            }
            echo "<tr><td>{$webText["helyszin"]}: *</td><td>";
            echo "<select name='helyszin' id='helyszin'>";
            $res = sql_query("SELECT h.*,".$this->utils->cimLangQuery()." FROM helyszinek h where h.id='{$beutalodata["helyszinid"]}'");
            if ($rowt = sql_fetch_array($res)) {
                echo "<option value='{$rowt["id"]}' selected>{$rowt["cim"]}</option>";
            }
            echo "</select>";
            echo "</td></tr>";

            echo "<tr><td>{$webText["szurestipus"]}: *</td><td><div id='szurestipusvalaszto'>".$this->bookingService->szuresTipusValasztoNew($beutalodata["helyszinid"],$beutalodata["szurestipusid"],1)."</div></td></tr>";
            $tipusMegj = $this->bookingService->getTipusMegj($_SESSION["helyszindata"]["id"], $beutalodata["szurestipusid"], $beutalodata["helyszinid"]);
            if (!empty($tipusMegj)) {
                echo "<tr><td></td><td><div id='szurestipusmegj'>{$tipusMegj}</div></td></tr>";
            }
        } else {
            //beutaló nélkül szabad választás
            $tipusMegj = $this->bookingService->getTipusMegj($_SESSION["helyszindata"]["id"],$_POST["szurestipus"],$_POST["helyszin"]);

            if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_SESSION["helyszindata"]["beutaloszoveg"]!="") {
                echo "<tr><td></td><td><div style='font-weight:bold;padding:5px 0px;'>{$_SESSION["helyszindata"]["beutaloszoveg"]}</div><td></tr>";
            }
            echo "<tr><td>{$webText["helyszin"]}: *</td><td>".$this->_reservationPlaceSelector()."</td></tr>";
			
            echo "<tr><td>{$webText["szurestipus"]}: *</td><td height='30'><div id='szurestipusvalaszto'>".$this->bookingService->szuresTipusValasztoNew($_POST["helyszin"], $_POST["szurestipus"])."</div></td></tr>";
            echo "<tr><td></td><td><div id='szurestipusmegj'>{$tipusMegj}</div></td></tr>";
			
			//Ide kell bevinni a checkboxot!
			echo "<tr><td></td><td><input type='checkbox' name='betegallomanynyilatkozat'><strong>Nyilatkozom, hogy tartós betegállományból térek vissza az aktív munkába.</strong></td></tr>";
        }

        $nofoglalasText = trim($_SESSION["helyszindata"]["nofoglalas_{$_COOKIE["lang"]}"]);
        if (empty($nofoglalasText)) {
            echo "<tr><td valign='middle'><div style=''>{$webText["idopont"]}: *</div></td><td>".$this->_reservationTimeSelector()."</td></tr>";
            echo "<tr><td></td><td><div id='idopontvalasztodiv' style='display:none;'></div></td></tr>";
        } else {
            echo "<tr><td></td><td>{$nofoglalasText}</td></tr>";
        }

        echo "<tr><td></td><td>&nbsp;</td></tr>";

        echo "<tr><td></td><td>";
        echo "<div>{$webText["dokfelinfo"]}</div>";
        echo "<div class='upload-btn-wrapper'><a href='#' class='upbtn newbutton'>{$webText["dokumentumfeltoltese"]}</a><input type='file' id='paciensfile' name='paciensfile[]' multiple /></div><img id='paciensloader' style='display:none;opacity:.5;height:30px;margin-left:10px;' src='/images/loading.svg' />";
        echo "</td></tr>";
        echo "<tr><td></td><td><div id='paciensfilediv'>".$this->utils->showPaciensFiles()."</div></td></tr>";

        if (trim($_SESSION["helyszindata"]["telephelyek"]) != "") {
            echo "<tr><td>{$webText["munkaltato"]}: *</td><td><select name='telephely' id='telephely'>";
            $telephelyek = explode(",",$_SESSION["helyszindata"]["telephelyek"]);
            echo "<option value=''>{$webText["valasszmunkaltatot"]}!</option>";
            foreach ($telephelyek as $telephely) {
                $telephely = trim($telephely);
                echo "<option value='{$telephely}'".($_POST["telephely"]==$telephely?" selected":"").">{$telephely}</option>";
            }
            echo "</select></td></tr>";
        }

        echo $this->utils->dataField("email");
        echo $this->utils->dataField("nev");
        echo $this->utils->dataField("telefon");
        echo $this->utils->dataField("szuldatum");
        echo $this->utils->dataField("szulhely");
        echo $this->utils->dataField("anyjaneve");
        echo $this->utils->dataField("neme");
        echo $this->utils->dataField("irsz");
        echo $this->utils->dataField("varos");
        echo $this->utils->dataField("utca");
        echo $this->utils->dataField("munkakor");

        if (!isset($beutalodata)) {
            echo "<tr><td>{$webText["megjegyzes"]}:</td><td><div id='fogleuwarn' style='display:none;margin-top:5px;color:#f00;font-weight:bold;'>Kérjük adja meg a megjegyzés rovatban a céget, ahonnan érkezik</div>";
            echo "<textarea class='inputbox' style='height:100px;width:400px;' name='megj' id='foglmegj'>{$_POST["megj"]}</textarea>";
            //apollo tyres kivétel
            if ($_SESSION["helyszindata"]["id"]==43) {
                echo "<div>";
                //Indiába menő, előzetes, soron kívüli, Indiából hazatérő. Illetve: Hollandiába menő, előzetes, soron kívüli, Hollandiából hazatérő
                echo "<span class='addmegjlink'>Indiába menő</span> &bull; ";
                echo "<span class='addmegjlink'>Előzetes</span> &bull; ";
                echo "<span class='addmegjlink'>Soron kívüli</span> &bull; ";
                echo "<span class='addmegjlink'>Indiából hazatérő</span><br/>";
                echo "<span class='addmegjlink'>Hollandiába menő</span> &bull; ";
                echo "<span class='addmegjlink'>Előzetes</span> &bull; ";
                echo "<span class='addmegjlink'>Soron kívüli</span> &bull; ";
                echo "<span class='addmegjlink'>Hollandiából hazatérő</span>";
                echo "</div>";

            }
            echo "</td></tr>";
        }

        if (!isset($_SESSION["user"])) {
            echo "<tr><td></td><td><div class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
            echo "<tr><td><td><div style='margin-top:10px;'><input type='checkbox' name='aszf' value='1' ".(isset($_POST["aszf"])?"checked":"")."/> {$webText["aszfelf"]}</div></td></tr>";
        }

        echo "<tr><td></td><td><div style='margin-top:20px;'><a href='#' class='newbutton' onclick='document.iform.submit();return false;'>{$webText["idopontfoglalasa"]}</a><span id='warnidopontpress' style='display:none;color:#41b6c6;margin-left:5px;'>&#9664;<span class='warnidopontpress'>{$webText["idopontfoglalasawarn"]}</span></span><div></td></tr>";

        echo "</table>";

        if (!isset($_SESSION["orvosselected"])) $_SESSION["orvosselected"] = 0;

        if (isset($_SESSION["user"])) echo "<input type='hidden' name='aszf' value='1'/>";
        echo "<input type='hidden' name='idopontfoglalas' value='1'/>";
        echo "<input type='hidden' name='version2' value='1'/>";
        echo "<input type='hidden' name='orvosselected' id='orvosselected' value='{$_SESSION["orvosselected"]}'/>";

        //echo "<br/><br/><input type='submit' name='idopontfoglalas' value='Időpont foglalása'/> ";
        //echo "<div style='margin-top:20px;'><a href='#' class='newbutton' onclick='document.iform.submit();return false;'>Időpont foglalása</a><div>";
        //echo "<input type='submit' name='scancel' value='Vissza'> ";

        echo "</form>";
    }

    private function _reservationTimeSelector() {
        $webText = $this->lang->webText;

        $dateStyle = (!empty($_POST["datum"])?"background-image:url(images/check.png);":"")."background-repeat:no-repeat;background-position:right 5px center;width:150px;height:24px;margin-right:5px;padding:4px 5px;font-size:16px;";
        $dateVal = substr($_POST["datum"], 0, 16);

        $html = "";
        $html.= "<div style='display:table-cell;vertical-align: middle;'>";
        $html.= "<input type='hidden' name='rinterval' id='rinterval' value='{$_POST["rinterval"]}' />";
        $html.= "<input placeholder='{$webText["kattintsagombra"]}' readonly='true' class='inputbox' style='{$dateStyle}' type='text' name='datum' id='datum' value='{$dateVal}' />";
        $html.= "</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><a href='#' onclick='showIdoPontValasztoV2(0);return false;' style='margin:0px;' class='newbutton'>{$webText["idopontvalasztas"]}</a></div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><img id='loadingspinner' style='margin-left:5px;height:25px;display:none;' src='/images/loading.svg' /></div>";
        return $html;
    }

    private function _reservationPlaceSelector() {
        $webText = $this->lang->webText;

        $html = "";
        $html.= "<select name='helyszin' id='helyszin' onchange='clearIdopontValaszto();clearSzuresTipus(this.value);'>";
        $res = sql_query("SELECT h.*,".$this->utils->cimLangQuery()." FROM helyszinek h 
            LEFT JOIN orvos_beosztas b ON b.`helyszinid`=h.id 
            LEFT JOIN orvosok o on b.orvosid=o.id
            WHERE h.aktiv=1 AND o.aktiv=1 AND b.aktiv=1 AND b.`helyszinid` IS NOT NULL and b.cegid=? GROUP BY h.id ORDER BY cim", array($_SESSION["helyszindata"]["id"]));

        $numOfH = sql_num_rows($res);

        $html.= "<option value='0'>{$webText["valasszhelyszint"]}</option>";
        while ($rowt = sql_fetch_array($res)) {
            if ($_SESSION["helyszindata"]["nocim"] == 1) {
                $rowt["cim"] = $rowt["megnev"];
            }

            $html.= "<option value='{$rowt["id"]}'".($_POST["helyszin"]==$rowt["id"] || $numOfH==1?" selected":"").">{$rowt["cim"]}</option>";
            if ($numOfH == 1) {
                $_POST["helyszin"] = $rowt["id"];
                //$_POST["szurestipus"] = 0;
            }
        }
        $html.= "</select>";
        return $html;
    }


    private function _preSelectForm() {
        $webText = $this->lang->webText;

        $html = "";

        $html.="<div style='padding:0px 0px 10px 0px;;'>";
        $html.="<div>";
        $html.="<h2 style='text-transform: uppercase;'>".$this->lang->getText("miert.bennunket","Miért bennünket válasszon?")."</h2>";

        $html.=$this->lang->getText("miert.bennunket.description","Egészségmegőrző tevékenységünk alappillérei a gondosan és szakmailag igényesen kidolgozott szűrőprogramok, melyeket azonos adottságú csoportokra terveztünk meg.<br/>
        Célkitűzéseink szerint folyamatosan figyelünk megvizsgált partnereink egészségére, támogatást nyújtva az egészséges életmód kialakításához.<br/>
        <br/>
        Vizsgálatainkat szervezetten, magas szakmai színvonalon, kiváló szakemberekkel végezzük, exkluzív környezetben. A vizsgálatsorozatokat angolul is beszélő orvosaink menedzselik.<br/>
        <br/>
        Kattintson a szakrendelés nevére a foglalás indításához!
        ");
        $html.="</div>";

        $resh = sql_query("SELECT h.* FROM orvos_beosztas b
        LEFT JOIN helyszinek h ON h.id = b.helyszinid
        LEFT JOIN orvosok o on o.id = b.orvosid
        WHERE b.cegid=? AND b.aktiv=1 AND b.helyszinid=1 AND o.aktiv=1 GROUP BY b.helyszinid", array($_SESSION["helyszindata"]["id"]));

        while ($helyszin = sql_fetch_array($resh)) {
            $rest = sql_query("SELECT b.* FROM orvos_beosztas b
            LEFT JOIN orvosok o on o.id = b.orvosid
            WHERE b.cegid=? AND b.aktiv=1 AND o.aktiv=1 AND b.`helyszinid`=?
            GROUP BY b.tipusok", array($_SESSION["helyszindata"]["id"], $helyszin["id"]));
            $tipusok = array(0);
            while ($tipusData = sql_fetch_array($rest)) {
                $tids = explode("|", $tipusData["tipusok"]);
                foreach ($tids as $tid) {
                    if (!empty($tid)) {
                        $tipusok[] = $tid;
                    }
                }
            }

            $tipusok = array_unique($tipusok);
            $orvosok = [];
            $tipusdb = [];

            $res = sql_query("select * from szurestipusok where id in (".implode(",", $tipusok).") order by megnev");
            while ($tipusData = sql_fetch_array($res)) {
                $tipusdb[] = $tipusData;

                $reso = sql_query("SELECT o.*,COUNT(*) FROM orvos_beosztas b
                LEFT JOIN orvosok o ON o.id = b.orvosid
                WHERE b.cegid=:cegId AND b.aktiv=1 AND b.helyszinid=1 AND INSTR(b.tipusok,:tipusok)
                and (nap<10 OR b.beonap >= DATE(NOW()))
                GROUP BY b.orvosid", array("cegId" => $_SESSION["helyszindata"]["id"], "tipusok" => "|{$tipusData["id"]}|"));
                while ($orvosData = sql_fetch_array($reso)) {
                    $orvosok[$tipusData["id"]][] = $orvosData;
                }
            }

            if (!empty($orvosok)) {
                $html.= "<h2>{$helyszin["cim"]}</h2>";

                foreach ($tipusdb as $tipusData) {
                    $tipusData["megnev"] = Lang::multiLangField($tipusData, "megnev");

                    $html.= "<div style=''><a onclick=\"$('.tipr').slideUp();$('#tipr{$tipusData["id"]}_{$helyszin["id"]}').slideDown();return false;\" href='#'>{$tipusData["megnev"]}</a></div>";
                    $html.= "<div id='tipr{$tipusData["id"]}_{$helyszin["id"]}' class='tipr' style='display:none;padding:10px 0px;'>";

                    foreach ($orvosok[$tipusData["id"]] as $orvosData) {
                        $html.= "<div>".$orvosData["nev"]."</div>";
                    }

                    $html.= "<div style='margin-top:5px;'><a onclick='extendedReservationSelect({$tipusData["id"]},{$helyszin["id"]})' class='newbutton' href='#'>{$tipusData["megnev"]} - {$webText["idopontfoglalas"]}</a></div>";
                    $html.= "</div>";
                }
            }

        }
        $html.= "</div>";

        return $html;
    }

}

