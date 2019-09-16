<?php

class BookingPage extends CorePage {

    private $bookingService;

    public function __construct()
    {
        parent::__construct();

        $this->bookingService = new BookingService();
        $webText = $this->lang->webText;

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
            if (!isset($_POST["szulhely"]))  $_POST["szulhely"] = "";
            if (!isset($_POST["anyjaneve"])) $_POST["anyjaneve"] = "";
            if (!isset($_POST["irsz"]))      $_POST["irsz"] = "";
            if (!isset($_POST["varos"]))     $_POST["varos"] = "";
            if (!isset($_POST["utca"]))      $_POST["utca"] = "";

            if (isset($_POST["szuldatumev"])) $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            if ($_POST["taj"] == "") $this->formError .= "{$webText["tajkotelezo"]}<br/>";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $this->formError .= "{$webText["tajformat"]}<br/>";
            if ($_POST["helyszin"] == "0") $this->formError .= "{$webText["helyszinkotelezo"]}<br/>";
            if ($_POST["datum"] == "") $this->formError .= "{$webText["idopontkotelezo"]}<br/>";
            if ($_POST["szurestipus"] == "0") $this->formError .= "{$webText["szurestipuskotelezo"]}<br/>";

            if ($_POST["email"] == "") $this->formError .= "{$webText["emailkotelezo"]}<br/>";
            if ($_POST["nev"] == "") $this->formError .= "{$webText["nevkotelezo"]}<br/>";
            if ($_POST["telefon"] == "") $this->formError .= "{$webText["telkotelezo"]}<br/>";
            if ($_POST["szuldatum"] == "") $this->formError .= "{$webText["szulkotelezo"]}<br/>";
            if (!$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) $this->formError .= "{$webText["szulformat"]}<br/>";

            //if ($_POST["irsz"]=="") $this->formError.="Az irányítószám megadása kötelező!<br/>";
            //if ($_POST["varos"]=="") $this->formError.="A város megadása kötelező!<br/>";
            //if ($_POST["utca"]=="") $this->formError.="Az utca megadása kötelező!<br/>";
            if (isset($_POST["munkakor"])) {
                if ($_POST["munkakor"] == "") $this->formError .= "{$webText["munkakorkotelezo"]}<br/>";
            } else {
                $_POST["munkakor"] = "";
            }


            if (!isset($_POST["neme"])) $this->formError .= "{$webText["nemekotelezo"]}<br/>";
            if (!isset($_POST["aszf"])) $this->formError .= "{$webText["aszfkotelezo"]}<br/>";

            if (isset($_POST["telephely"]) && trim($_POST["telephely"]) == "") $this->formError .= "{$webText["telephelykotelezo"]}<br/>";


            if (isset($_POST["captcha"]) && $_POST["captcha"] != $_SESSION["captcha"] && $_POST["captcha"] != "111") $this->formError .= "Az megadott szám nem egyezik!<br/>";

            //if ($rowe=sql_fetch_array(sql_query("select id,datum,rkod from foglalasok where cegid='".addslashes($_SESSION["helyszindata"]["id"])."' and taj='".addslashes($_POST["taj"])."' and now()<datum"))) {
            //	$this->formError.="Már van egy foglalása ".substr($rowe["datum"],0,16)." időpontra. Ha újra szeretne foglalni, kérjük törölje az előző foglalását! <a style='color:#ff0;' href='index.php?page=torles&id={$rowe["id"]}&rk={$rowe["rkod"]}'>Időpont törlése</a>";
            //}

            if ($_POST["datum"] != "" && !$this->bookingService->checkIdopontSzabad($_POST)) $this->formError .= "{$webText["idopontlefoglaltak"]}<br>";
            if (!isset($_POST["rinterval"])) $_POST["rinterval"] = 0;
            if (!isset($_POST["telephely"])) $_POST["telephely"] = "";

            if (!isset($_SESSION["user"])) {
                if (isset($_POST["version2"])) {
                    if (isset($_POST["g-recaptcha-response"])) $captcha = $_POST["g-recaptcha-response"];
                    if (isset($captcha)) {
                        if (!$captcha) {
                            $this->formError .= "{$webText["captchaerror1"]}<br/>";
                        } else {
                            $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfCaTIUAAAAAF1-t94n7TBAsKov_dglwP6b8Luo&response=" . urlencode($captcha) . "&remoteip=" . $_SERVER["REMOTE_ADDR"]), true);
                            if ($response["success"] == false) {
                                $this->formError .= "{$webText["captchaerror2"]}<br/>";
                            }
                        }
                    } else {
                        $this->formError .= "{$webText["captchaerror3"]}<br/>";
                    }
                }
            }


            if ($this->formError == "") {
                if (!isset($_POST["tudoszuro"])) $_POST["tudoszuro"] = 0;

                $rn = rand(1000000, 9999999);

                $paciensId = 0;

                if (isset($_SESSION["user"]["id"])) {
                    $paciensId = intval($_SESSION["user"]["id"]);
                } else {
                    $request_user = sql_query("SELECT * FROM felhasznalok WHERE (taj = ? OR email = ?) and cegid=?", array($_REQUEST['taj'], $_REQUEST['email'], $_SESSION["helyszindata"]["id"]));
                    if (sql_num_rows($request_user) > 0) {
                        $userInfo = sql_fetch_array($request_user);
                        $paciensId = $userInfo['id'];
                    } else {
                        sql_query("INSERT INTO felhasznalok SET validated=1, cegid=?, regtime=now(), taj = ?, email = ?, nev = ?, telefon = ?, munkakor = ?, irsz = ?, varos = ?, utca = ?, szulhely = ?, anyjaneve = ?, szuldatum = ? ",
                            array($_SESSION["helyszindata"]["id"], $_REQUEST['taj'], $_REQUEST['email'], $_REQUEST['nev'], $_REQUEST['tel'], $_REQUEST['munkakor'], $_REQUEST['irsz'], $_REQUEST['varos'], $_REQUEST['utca'], $_REQUEST['szulhely'], $_REQUEST['anyjaneve'], $_REQUEST['szuldatum']));
                        $paciensId = sql_insert_id();
                    }
                }


                if (isset($_SESSION["user"]["id"])) $paciensId = intval($_SESSION["user"]["id"]);

                sql_query("insert into foglalasok set regdatum=now(),paciensid=?,cegid=?,datum=?,rinterval=?,telephely=?,helyszinid=?,szurestipusid=?,nev=?,email=?,telefon=?,szuldatum=?,szulhely=?,anyjaneve=?,neme=?,taj=?,irsz=?,varos=?,utca=?,megj=?,munkakor=?,tudoszuro=?,rlang=?,rkod=?"
                    , array($paciensId, $_SESSION["helyszindata"]["id"], $_POST["datum"], intval($_POST["rinterval"]), $_POST["telephely"], $_POST["helyszin"], $_POST["szurestipus"], $_POST["nev"], $_POST["email"], $_POST["telefon"], $_POST["szuldatum"], $_POST["szulhely"], $_POST["anyjaneve"], $_POST["neme"], $_POST["taj"], $_POST["irsz"], $_POST["varos"], $_POST["utca"], $_POST["megj"], $_POST["munkakor"], $_POST["tudoszuro"], $_COOKIE["lang"], $rn));

                $fid = sql_insert_id();
                $this->bookingService->updateFoglalasData($fid);

                $oid = $this->bookingService->selectFreeOrvosForIdopont($fid);
                sql_query("update foglalasok set orvosassigned=? where id=?", array($oid, $fid));

                if (isset($_SESSION["beutaloid"]) && isset($_SESSION["user"]) && $rowb = sql_fetch_array(sql_query("select * from beutalok where id=?", array($_SESSION["beutaloid"])))) {
                    sql_query("update beutalok set foglalasid=? where id=?", array($fid, $_SESSION["beutaloid"]));
                    sql_query("update fogalalasok set megj=? where id=?", array($rowb["megj"], $fid));
                    unset($_SESSION["beutaloid"]);
                }

                //altipusok tárolása
                $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and csomag=0", array("|{$_SESSION["helyszindata"]["id"]}|", $_POST["szurestipus"]));
                while ($row = sql_fetch_array($res)) {
                    if (isset($_POST["altipus{$row["id"]}"])) {
                        sql_query("insert into fizkapcs set fid=?,aid=?,megnev=?,ar=?,valuta=?", array($fid, $row["id"], $row["megnev"], $row["price"], $row["penznem"]));
                    }
                }

                if (isset($_SESSION["remotebeutalo"]) || $_SESSION["helyszindata"]["visszaigazolas"] == 0) {
                    //orvos jött, akkor nem kérünk visszaigazolást, megyünk visszaigazolni automatikusan
                    header("location:index.php?page=megerosites&id={$fid}&rk={$rn}");
                } else {
                    //visszaigazolást kérünk
                    $this->bookingService->sendVisszaIgazolas($fid);
                    header("location:index.php?page=bookingsuccessful");
                }

                die();
            }
        }


    }

    public function showPage() {
        $webText = $this->lang->webText;

        if (!isset($_POST["email"])) {
            $_POST["helyszin"]=$_POST["datum"]=$_POST["szurestipus"]=$_POST["email"]=$_POST["nev"]=$_POST["telefon"]=$_POST["szuldatum"]=$_POST["taj"]=$_POST["irsz"]=$_POST["varos"]=$_POST["utca"]=$_POST["munkaltato"]=$_POST["munkakor"]=$_POST["nev"]=$_POST["nev"]=$_POST["megj"]=$_POST["captcha"]=$_POST["szulhely"]=$_POST["anyjaneve"]=$_POST["telephely"]="";
            $_POST["rinterval"]=0;
            if (isset($_SESSION["user"])) {
                $_POST["taj"]=$_SESSION["user"]["taj"];
                $_POST["email"]=$_SESSION["user"]["email"];
                $_POST["nev"]=$_SESSION["user"]["nev"];
                $_POST["telefon"]=$_SESSION["user"]["telefon"];
                $_POST["szuldatum"]=$_SESSION["user"]["szuldatum"];
                $_POST["szulhely"]=$_SESSION["user"]["szulhely"];
                $_POST["anyjaneve"]=$_SESSION["user"]["anyjaneve"];
                $_POST["irsz"]=$_SESSION["user"]["irsz"];
                $_POST["varos"]=$_SESSION["user"]["varos"];
                $_POST["utca"]=$_SESSION["user"]["utca"];
                $_POST["munkakor"]=$_SESSION["user"]["munkakor"];
                $_POST["neme"]=$_SESSION["user"]["neme"];
            }
        }

        if (!isset($_POST["neme"])) $_POST["neme"]="";

        echo $this->displayFejlec();
        echo $this->showFormErrors();

        if ($_SESSION["helyszindata"]["onlybeutalo"]==1 && isset($_SESSION["user"]) && !isset($_SESSION["beutaloid"])) {
            echo "<div style=''>{$webText["csakbeutalodesc"]}</div>";
            echo "<div style='margin-top:10px;'><a class='simabutton' href='index.php?page=beutalok'>{$webText["showbeutalobutton"]}</a></div>";
            //echo "</div>";
            return;
        }

        if (isset($_SESSION["beutaloid"])) {
            if (!$beutalodata=sql_fetch_array(sql_query("select * from beutalok where id='".intval($_SESSION["beutaloid"])."' and foglalasid=0"))) {
                echo "<div style=''>A beutalóval probléma adodott!</div>";
                echo "<div style='margin-top:10px;'><a class='simabutton' href='index.php?page=beutalok'>{$webText["showbeutalobutton"]}</a></div>";
                //echo "</div>";
                return;
            }
        }

        if ($_SESSION["helyszindata"]["onlyreg"]==1 && !isset($_SESSION["user"])) {
            $btext=$webText["mainudvozles"];

            if ($rowsz=sql_fetch_array(sql_query("select * from szovegek where cegid=? and tipus='welcome'",array($_SESSION["helyszindata"]["id"])))) {
                $btext=$rowsz["szoveg"];
            }

            echo "<div style=''>{$btext}</div>";

            echo "<div style='margin-top:20px;'><a href='index.php?page=reg' class='newbutton'>{$webText["regisztracio"]}</a>&nbsp;&nbsp;<a href='index.php?page=login' class='newbutton'>{$webText["bejelentkezes"]}</a></div>";
            //echo "</div>";
            return;
        }


        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<table>";

        echo "<tr><td width='140'>{$webText["tajszam"]}: *</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' onchange='clearIdopontValaszto();'  value='{$_POST["taj"]}'></td></tr>";

        //Kérjük akkut egészségkárosodás vagy életveszély esetén azonnal hívja az 104-es országos mentőszolgálat vagy a 112 központi segélyhívót.

        if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_COOKIE["lang"]!="hu" && trim($_SESSION["helyszindata"]["beutaloszoveg_{$_COOKIE["lang"]}"])!="") $_SESSION["helyszindata"]["beutaloszoveg"]=$_SESSION["helyszindata"]["beutaloszoveg_{$_COOKIE["lang"]}"];



        if (isset($beutalodata)) {
            //beutalóval fix választás

            if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_SESSION["helyszindata"]["beutaloszoveg"]!="") echo "<tr><td></td><td><div style='font-weight:bold;padding:5px 0px;'>{$_SESSION["helyszindata"]["beutaloszoveg"]}</div><td></tr>";
            echo "<tr><td>{$webText["helyszin"]}: *</td><td>";
            echo "<select name='helyszin' id='helyszin'>";
            $res=sql_query("SELECT h.*,".$this->utils->cimLangQuery()." FROM helyszinek h where h.id='{$beutalodata["helyszinid"]}'");
            if ($rowt=sql_fetch_array($res)) echo "<option value='{$rowt["id"]}' selected>{$rowt["cim"]}</option>";
            echo "</select>";
            echo "</td></tr>";

            echo "<tr><td>{$webText["szurestipus"]}: *</td><td><div id='szurestipusvalaszto'>".$this->bookingService->szuresTipusValasztoNew($beutalodata["helyszinid"],$beutalodata["szurestipusid"],1)."</div></td></tr>";
            $tipusMegj = $this->bookingService->getTipusMegj($_SESSION["helyszindata"]["id"],$beutalodata["szurestipusid"],$beutalodata["helyszinid"]);
            if ($tipusMegj!="") echo "<tr><td></td><td><div id='szurestipusmegj'>{$tipusMegj}</div></td></tr>";
        } else {
            //beutaló nélkül szabad választás

            if (isset($_SESSION["helyszindata"]["beutaloszoveg"]) && $_SESSION["helyszindata"]["beutaloszoveg"]!="") echo "<tr><td></td><td><div style='font-weight:bold;padding:5px 0px;'>{$_SESSION["helyszindata"]["beutaloszoveg"]}</div><td></tr>";
            echo "<tr><td>{$webText["helyszin"]}: *</td><td>";

            echo "<select name='helyszin' id='helyszin' onchange='clearIdopontValaszto();clearSzuresTipus(this.value);'>";
            $res=sql_query("SELECT h.*,".$this->utils->cimLangQuery()." FROM helyszinek h 
            LEFT JOIN orvos_beosztas b ON b.`helyszinid`=h.id 
            LEFT JOIN orvosok o on b.orvosid=o.id
            WHERE h.aktiv=1 AND o.aktiv=1 AND b.`helyszinid` IS NOT NULL and b.cegid='{$_SESSION["helyszindata"]["id"]}' GROUP BY h.id ORDER BY cim");

            $numOfH=sql_num_rows($res);

            echo "<option value='0'>{$webText["valasszhelyszint"]}</option>";
            while ($rowt = sql_fetch_array($res)) {
                if ($_SESSION["helyszindata"]["nocim"] == 1) {
                    $rowt["cim"] = $rowt["megnev"];
                }

                echo "<option value='{$rowt["id"]}'".($_POST["helyszin"]==$rowt["id"] || $numOfH==1?" selected":"").">{$rowt["cim"]}</option>";
                if ($numOfH == 1) {
                    $_POST["helyszin"] = $rowt["id"];
                    //$_POST["szurestipus"] = 0;
                }
            }
            echo "</select>";
            echo "</td></tr>";

            echo "<tr><td>{$webText["szurestipus"]}: *</td><td height='30'><div id='szurestipusvalaszto'>".$this->bookingService->szuresTipusValasztoNew($_POST["helyszin"], $_POST["szurestipus"])."</div></td></tr>";
            $tipusMegj = $this->bookingService->getTipusMegj($_SESSION["helyszindata"]["id"],$_POST["szurestipus"],$_POST["helyszin"]);
            echo "<tr><td></td><td><div id='szurestipusmegj'>{$tipusMegj}</div></td></tr>";
        }

        $nofoglalasText = trim($_SESSION["helyszindata"]["nofoglalas_{$_COOKIE["lang"]}"]);
        if ($nofoglalasText == "") {
            echo "<tr><td valign='middle'><div style=''>{$webText["idopont"]}: *</div></td><td>";
            echo "<div style='display:table-cell;vertical-align: middle;'>";
            echo "<input type='hidden' name='rinterval' id='rinterval' value='{$_POST["rinterval"]}' />";
            echo "<input placeholder='{$webText["kattintsagombra"]}' readonly='true' class='inputbox' 
            style='".(!empty($_POST["datum"])?"background-image:url(images/check.png);":"")."background-repeat:no-repeat;background-position:right 5px center;width:150px;height:24px;margin-right:5px;padding:4px 5px;font-size:16px;' 
            type='text' name='datum' id='datum' value='" . substr($_POST["datum"], 0, 16) . "' />";
            echo "</div><div style='display:table-cell;vertical-align: middle;'>";
            echo "<a href='#' onclick='showIdoPontValasztoV2(0);return false;' style='margin:0px;' class='newbutton'>{$webText["idopontvalasztas"]}</a></div><div style='display:table-cell;vertical-align: middle;'><img id='loadingspinner' style='margin-left:5px;height:25px;display:none;' src='/images/loading.svg' />";
            echo "</div>";
            echo "</td></tr>";
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
        echo "<tr><td>{$webText["email"]}: *</td><td><input class='inputbox' style='width:250px;' type='text' name='email' value='{$_POST["email"]}' /></td></tr>";
        echo "<tr><td></td><td>{$webText["kerjukugyeljenemail"]}</td></tr>";
        echo "<tr><td>{$webText["nev"]}: *</td><td><input class='inputbox' style='width:250px;' type='text' name='nev' value='{$_POST["nev"]}' /></td></tr>";
        echo "<tr><td>{$webText["mobil"]}: *</td><td><input class='inputbox' style='width:250px;' type='text' name='telefon' value='{$_POST["telefon"]}' placeholder='Formátum pl: 06301234567' /></td></tr>";
        //echo "<tr><td></td><td>{$webText["mobiltip"]}</td></tr>";
        echo "<tr><td>{$webText["szuletesidatum"]}: *</td><td>".$this->utils->datumSelector($_POST["szuldatum"],"szuldatum")."</td></tr>";

        if($_SESSION['helyszindata']['id'] != 46) echo "<tr><td>{$webText["szuletesihely"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='szulhely' value='{$_POST["szulhely"]}' placeholder='' /></td></tr>";
        else echo "<input type='hidden' name='szulhely' value='' />";
        if($_SESSION['helyszindata']['id'] != 46) echo "<tr><td>{$webText["anyjaneve"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='anyjaneve' value='{$_POST["anyjaneve"]}' placeholder='' /></td></tr>";
        else echo "<input type='hidden' name='anyjaneve' value='' />";
        echo "<tr><td>{$webText["neme"]}: *</td><td><input type='radio' name='neme' value='1' ".($_POST["neme"]==1?"checked":"")."/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_POST["neme"]==2?"checked":"")."/> {$webText["no"]} </td></tr>";
        if($_SESSION['helyszindata']['id'] != 46) echo "<tr><td>{$webText["irsz"]}:</td><td><input class='inputbox' style='width:60px;' type='text' name='irsz' value='{$_POST["irsz"]}' /></td></tr>";
        else echo "<input type='hidden' name='irsz' value='' />";
        if($_SESSION['helyszindata']['id'] != 46) echo "<tr><td>{$webText["varos"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='varos' value='{$_POST["varos"]}' /></td></tr>";
        else echo "<input type='hidden' name='varos' value='' />";
        if($_SESSION['helyszindata']['id'] != 46) echo "<tr><td>{$webText["utca"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='utca' value='{$_POST["utca"]}' /></td></tr>";
        else echo "<input type='hidden' name='utca' value='' />";

        if (!in_array($_SESSION["helyszindata"]["domain"],array("bejelentkezes","gyor-bejelentkezes"))) {
            echo "<tr><td>{$webText["munkakor"]}: *</td><td><input class='inputbox' style='width:250px;' type='text' name='munkakor' value='{$_POST["munkakor"]}' /></td></tr>";
        }

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

        if (!isset($_SESSION["captcha"])) $_SESSION["captcha"]=rand(110,988);
        if (!isset($_SESSION["user"])) {
            //echo "<tr><td colspan='2'><div style='margin-top:10px;'>Kérem, adja meg a következő számot számjegyekkel: ".numtostring($_SESSION["captcha"]).":<br><input class='inputbox' style='width:60px;' type='text' name='captcha' value='{$_POST["captcha"]}'></div></td></tr>";
            echo "<tr><td></td><td><div class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
        }

        if (!isset($_SESSION["user"])) {
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



}

