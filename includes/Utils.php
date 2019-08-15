<?php

class Utils {

    public function __construct()
    {

        if (isset($_GET["tesztsms"]))
        {
            include ("includes/seeme-gateway-class.php");
            sendSMS("36209996183", "kód a regisztráció befejezéséhez: 1111");
            die("ok");
        }

        if (!isset($_SESSION["user"]) && isset($_GET["page"])) {
            if (in_array($_GET["page"], array("beutalok", "dokumentumok", "foglalasok"))) {
                header("location:/");
                die();
            }
        }

        if (isset($_POST["validatelogin"])) {
            $formerror = "";
            if ($_POST["smskod"] == "") {
                $formerror .= "{$webText["nemadtamegkod"]}<br/>";
            } else {

                $kod = round($_POST["smskod"]);
                if ($_POST["smskod"] != "" && !sql_fetch_array(sql_query("select rkod from felhasznalok where id='{$_SESSION["user"]["id"]}' and rkod='{$kod}'"))) {
                    $formerror .= "{$webText["hibaskod"]}<br/>";
                } else {
                    sql_query("update felhasznalok set validated=1 where id='{$_SESSION["user"]["id"]}' and rkod='{$kod}'");
                    header("location:index.php?page=sikereservenyesites");
                    die();
                }
            }
        }

        if (isset($_POST["logintry"])) {
            $formerror = "";
            if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where email=? and jelszo=md5(?) and cegid=?", array($_POST["email"], $_POST["jelszo"], $_SESSION["helyszindata"]["id"])))) {
                $_SESSION["loggeduser"] = $rowu["id"];
                header("location:index.php");
                die();
            } else {
                $formerror = "{$webText["loginerror"]}";
            }
        }

        if (isset($_POST["passwordsend"])) {
            $formerror = "";

            if (trim($_POST["email"]) == "") {
                $formerror = "{$webText["kerjukadjamegemail2"]}";
                return;
            }
            if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
                $formerror = "{$webText["emailformat"]}";
                return;
            }

            if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where email=? and cegid=?", array($_POST["email"], $_SESSION["helyszindata"]["id"])))) {
                $pchars = "abcdefghijklmnpqrstuvwxyz1234567899";
                $p = "";
                for ($i = 0; $i < 8; $i++) {
                    $p .= substr($pchars, rand(0, strlen($pchars) - 1), 1);
                }

                include_once("phpmailer/class.phpmailer.php");
                $mail = new PHPMailer();
                $mail->From = "noreply@hungariamed.hu";
                $mail->FromName = "Hungariamed";
                $mail->AddAddress($rowu["email"]);
                $mail->AddReplyTo("noreply@hungariamed.hu");
                $mail->IsHTML(true);

                $t = iconv("UTF-8", "ISO-8859-2", "Új jelszó kérése");

                $mbody = "Kedves {$rowu["nev"]}!<br/><br/>";
                $mbody .= "Az online bejelentkezési felületünkön új jelszó kérését kezdeményezte.<br/><br/>";
                $mbody .= "Az új jelszava: <b>{$p}</b><br><br>";
                $mbody .= "Az új jelszavát bejelentkezés követően az adatmódosítás menüpont alatt tudja megváltoztatni.<br/>";
                $mbody .= "<br/>";
                $mbody .= "Üdvözlettel:<br>Hungariamed";

                if ($_COOKIE["lang"] == "de") {
                    $mbody = "Lieber {$rowu["nev"]}!<br/><br/>";
                    $mbody .= "Unsere online anmelden Oberfláche sie beginnen eine neue Kennwort anbietten.<br/><br/>";
                    $mbody .= "Die neue Kennwort: <b>{$p}</b><br><br>";
                    $mbody .= "Nach den anmelden können Sie um  einem neuem Kennwort bitten.<br/>";
                    $mbody .= "<br/>";
                    $mbody .= "Freundlichen Grüssen:<br>Hungariamed";
                }
                if ($_COOKIE["lang"] == "en") {
                    $mbody = "Dear {$rowu["nev"]}!<br/><br/>";
                    $mbody .= "You have requested a new password on our reservation page.<br/><br/>";
                    $mbody .= "Your new password: <b>{$p}</b><br><br>";
                    $mbody .= "You can change your new password under the profile page.<br/>";
                    $mbody .= "<br/>";
                    $mbody .= "Regards<br>Hungariamed";
                }

                $mail->Subject = $t;
                $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
                //$mail->AddAttachment("");
                $mail->Send();

                sql_query("update felhasznalok set jelszo='" . addslashes(md5($p)) . "'	where id='{$rowu["id"]}'");

                header("location:index.php?page=login&passwordsent");
                die();
            } else {
                $formerror = "{$webText["nemtalalhatoemail"]}";
            }

        }


        if (isset($_GET["remotereserve"])) {
            if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where id='" . intval($_GET["fid"]) . "' and rkod='" . intval($_GET["fkod"]) . "'"))) {
                $_SESSION["remotebeutalo"] = $_GET["remotereserve"];
                $_SESSION["loggeduser"] = $rowu["id"];
                header("location:index.php?setbeutalo=" . intval($_GET["remotereserve"]));
                die();
            }
        }


        if (isset($_POST["adatmodositas"])) {
            $formerror = "";

            if (isset($_POST["szuldatumev"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            }

            $_POST["telefon"] = fixPhoneNumber($_POST["telefon"]);

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));
            if ($_POST["taj"] == "" && $_SESSION["helyszindata"]["tajnotreq"] == 0) $formerror .= "{$webText["tajkotelezo"]}<br/>";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $formerror .= "{$webText["tajformat"]}<br/>";
            if ($_POST["taj"] != "" && sql_fetch_array(sql_query("select taj from felhasznalok where taj=? and cegid=? and id<>?", array($_POST["taj"], $_SESSION["helyszindata"]["id"], $_SESSION["user"]["id"])))) $formerror .= "{$webText["tajletezik"]}<br/>";

            //if ($_POST["email"]=="") $formerror.="Az e-mail cím megadása kötelező!<br/>";
            //if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) && $_POST["email"]!="") $formerror.="Az e-mail cím formátuma nem megfelelő!<br/>";
            if ($_POST["nev"] == "") $formerror .= "{$webText["nevkotelezo"]}<br/>";
            if ($_POST["telefon"] == "") $formerror .= "{$webText["telkotelezo"]}<br/>";
            if (!ctype_digit($_POST["telefon"]) && $_POST["telefon"] != "") $formerror .= "{$webText["telformat"]}<br/>";
            if ($_POST["szuldatum"] == "" && $_SESSION["helyszindata"]["tajnotreq"] == 0) $formerror .= "{$webText["szulkotelezo"]}<br/>";
            if (!validateDate($_POST["szuldatum"], "Y-m-d") && $_SESSION["helyszindata"]["tajnotreq"] == 0) $formerror .= "{$webText["szulformat"]}<br/>";
            //if ($_POST["munkakor"]=="" && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="A munkakör megadása kötelező!<br/>";
            if (!isset($_POST["neme"])) $formerror .= "{$webText["nemekotelezo"]}<br/>";

            if ($_POST["jelszo"] != "") {
                if ($_POST["jelszo"] != $_POST["jelszo2"]) $formerror .= "{$webText["ketjelszonem"]}<br/>";
                if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) < 6) $formerror .= "{$webText["jelszomin"]}<br/>";
                if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) > 20) $formerror .= "{$webText["jelszomax"]}<br/>";
            }

            if ($formerror == "") {

                sql_query("update felhasznalok set nev=?,telefon=?,szuldatum=?,szulhely=?,anyjaneve=?,neme=?,taj=?,irsz=?,varos=?,utca=?,munkakor=?,torzsszam=? where id=?"
                    , array($_POST["nev"], $_POST["telefon"], $_POST["szuldatum"], $_POST["szulhely"], $_POST["anyjaneve"], $_POST["neme"], $_POST["taj"], $_POST["irsz"], $_POST["varos"], $_POST["utca"], $_POST["munkakor"], $_POST["torzsszam"], $_SESSION["user"]["id"]));

                if ($_POST["jelszo"] != "") {
                    sql_query("update felhasznalok set jelszo=? where id=?", array(md5($_POST["jelszo"]), $_SESSION["user"]["id"]));
                }

                //ideiglenesen a funkció kiszedve
                if ($_POST["telefon"] != $_POST["oldtelefon"] and false) {
                    //megváltozott a telefon, új kódot küldünk és újravalidálunk.
                    $rn = rand(11000, 98000);
                    sql_query("update felhasznalok set validated=0,rkod='{$rn}'	where id='{$_SESSION["user"]["id"]}'");
                    sendUserSMSKod($_SESSION["user"]["id"]);
                    header("location:index.php");
                    die();
                }

                header("location:index.php?page=profil");
                die();

            }
        }


        if (isset($_POST["regisztracio"])) {
            $formerror = "";

            if (isset($_POST["szuldatumev"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            }

            $_POST["telefon"] = fixPhoneNumber($_POST["telefon"]);

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            //if (!isset($_SESSION["captcha"])) $formerror.="A form elévült, kérjük kattints újra az elküldésre!<br/>";

            if (!isset($_POST["munkakor"])) $_POST["munkakor"] = "";
            if (!isset($_POST["torzsszam"])) $_POST["torzsszam"] = "";

            if ($_POST["taj"] == "" && $_SESSION["helyszindata"]["tajnotreq"] == 0) $formerror .= "{$webText["tajkotelezo"]}<br/>";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $formerror .= "{$webText["tajformat"]}<br/>";
            if ($_POST["taj"] != "" && sql_fetch_array(sql_query("select taj from felhasznalok where taj=? and cegid=?", array($_POST["taj"], $_SESSION["helyszindata"]["id"])))) $formerror .= "{$webText["tajletezik"]}<br/>";

            if ($_POST["email"] == "") $formerror .= "{$webText["emailkotelezo"]}<br/>";
            if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) && $_POST["email"] != "") $formerror .= "{$webText["emailformat"]}<br/>";
            if ($_POST["email"] != "" && sql_fetch_array(sql_query("select taj from felhasznalok where email=? and cegid=?", array($_POST["email"], $_SESSION["helyszindata"]["id"])))) $formerror .= "{$webText["emailletezik"]}<br/>";

            if ($_POST["jelszo"] == "") $formerror .= "{$webText["jelszokotelezo"]}<br/>";
            if ($_POST["jelszo"] != $_POST["jelszo2"]) $formerror .= "{$webText["ketjelszonem"]}<br/>";
            if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) < 6) $formerror .= "{$webText["jelszomin"]}<br/>";
            if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) > 20) $formerror .= "{$webText["jelszomax"]}<br/>";
            if ($_POST["nev"] == "") $formerror .= "{$webText["nevkotelezo"]}<br/>";
            if ($_POST["telefon"] == "") $formerror .= "{$webText["telkotelezo"]}<br/>";
            if (!ctype_digit($_POST["telefon"]) && $_POST["telefon"] != "") $formerror .= "{$webText["telformat"]}<br/>";
            if ($_POST["szuldatum"] == "" && $_SESSION["helyszindata"]["tajnotreq"] == 0) $formerror .= "{$webText["szulkotelezo"]}<br/>";
            if (!validateDate($_POST["szuldatum"], "Y-m-d") && $_SESSION["helyszindata"]["tajnotreq"] == 0) $formerror .= "{$webText["szulformat"]}<br/>";
            //if (isset($_POST["munkakor"]) && $_POST["munkakor"]=="" && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="A munkakör megadása kötelező! {$_SESSION["helyszindata"]["tajnotreq"]}<br/>";
            if (!isset($_POST["neme"]) && $_SESSION["helyszindata"]["tajnotreq"] == 0) $formerror .= "{$webText["nemekotelezo"]}<br/>";

            if (!isset($_POST["neme"])) $_POST["neme"] = 0;

            //if ($_POST["captcha"]!=$_SESSION["captcha"] && $_POST["captcha"]!="111") $formerror.="Az megadott szám nem egyezik!<br/>";
            if (!isset($_POST["aszf"])) $formerror .= "{$webText["aszfkotelezo"]}<br/>";


            if (isset($_POST["g-recaptcha-response"])) $captcha = $_POST["g-recaptcha-response"];
            if (isset($captcha)) {
                if (!$captcha) {
                    $formerror .= "{$webText["captchaerror1"]}<br/>";
                } else {
                    $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfCaTIUAAAAAF1-t94n7TBAsKov_dglwP6b8Luo&response=" . urlencode($captcha) . "&remoteip=" . $_SERVER["REMOTE_ADDR"]), true);
                    if ($response["success"] == false) {
                        $formerror .= "{$webText["captchaerror2"]}<br/>";
                    }
                }
            } else {
                $formerror .= "{$webText["captchaerror3"]}<br/>";
            }


            if ($formerror == "") {
                $rn = rand(11000, 98000);

                sql_query("insert into felhasznalok set
		cegid=?,regtime=now(),nev=?,email=?,jelszo=?,telefon=?,szuldatum=?,neme=?,taj=?,irsz=?,varos=?,utca=?,munkakor=?,torzsszam=?,
		rkod=?", array($_SESSION["helyszindata"]["id"], $_POST["nev"], $_POST["email"], md5($_POST["jelszo"]), $_POST["telefon"], $_POST["szuldatum"], $_POST["neme"], $_POST["taj"], $_POST["irsz"], $_POST["varos"], $_POST["utca"], $_POST["munkakor"], $_POST["torzsszam"], $rn));

                $id = sql_insert_id();
                if ($_SESSION["helyszindata"]["id"] != 11) sendUserSMSKod($id);


                $_SESSION["loggeduser"] = $id;

                header("location:index.php");
                die();
            }
        }

        if (isset($_POST["idopontfoglalas"])) {
            $formerror = "";

            //nem kötelező mezők létrehozása ha nincsenek
            if (!isset($_POST["szulhely"])) $_POST["szulhely"] = "";
            if (!isset($_POST["anyjaneve"])) $_POST["anyjaneve"] = "";
            if (!isset($_POST["irsz"])) $_POST["irsz"] = "";
            if (!isset($_POST["varos"])) $_POST["varos"] = "";
            if (!isset($_POST["utca"])) $_POST["utca"] = "";

//print_r($_POST);die;
            if (isset($_POST["szuldatumev"])) $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            //if (!isset($_SESSION["captcha"])) $formerror.="A form elévült, kérjük kattints újra az elküldésre!<br/>";
            if ($_POST["taj"] == "") $formerror .= "{$webText["tajkotelezo"]}<br/>";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $formerror .= "{$webText["tajformat"]}<br/>";
            if ($_POST["helyszin"] == "0") $formerror .= "{$webText["helyszinkotelezo"]}<br/>";
            if ($_POST["datum"] == "") $formerror .= "{$webText["idopontkotelezo"]}<br/>";
            if ($_POST["szurestipus"] == "0") $formerror .= "{$webText["szurestipuskotelezo"]}<br/>";

            if ($_POST["email"] == "") $formerror .= "{$webText["emailkotelezo"]}<br/>";
            if ($_POST["nev"] == "") $formerror .= "{$webText["nevkotelezo"]}<br/>";
            if ($_POST["telefon"] == "") $formerror .= "{$webText["telkotelezo"]}<br/>";
            if ($_POST["szuldatum"] == "") $formerror .= "{$webText["szulkotelezo"]}<br/>";
            if (!validateDate($_POST["szuldatum"], "Y-m-d")) $formerror .= "{$webText["szulformat"]}<br/>";

            //if ($_POST["irsz"]=="") $formerror.="Az irányítószám megadása kötelező!<br/>";
            //if ($_POST["varos"]=="") $formerror.="A város megadása kötelező!<br/>";
            //if ($_POST["utca"]=="") $formerror.="Az utca megadása kötelező!<br/>";
            if (isset($_POST["munkakor"])) {
                if ($_POST["munkakor"] == "") $formerror .= "{$webText["munkakorkotelezo"]}<br/>";
            } else {
                $_POST["munkakor"] = "";
            }


            if (!isset($_POST["neme"])) $formerror .= "{$webText["nemekotelezo"]}<br/>";
            if (!isset($_POST["aszf"])) $formerror .= "{$webText["aszfkotelezo"]}<br/>";

            if (isset($_POST["telephely"]) && trim($_POST["telephely"]) == "") $formerror .= "{$webText["telephelykotelezo"]}<br/>";


            if (isset($_POST["captcha"]) && $_POST["captcha"] != $_SESSION["captcha"] && $_POST["captcha"] != "111") $formerror .= "Az megadott szám nem egyezik!<br/>";

            //if ($rowe=sql_fetch_array(sql_query("select id,datum,rkod from foglalasok where cegid='".addslashes($_SESSION["helyszindata"]["id"])."' and taj='".addslashes($_POST["taj"])."' and now()<datum"))) {
            //	$formerror.="Már van egy foglalása ".substr($rowe["datum"],0,16)." időpontra. Ha újra szeretne foglalni, kérjük törölje az előző foglalását! <a style='color:#ff0;' href='index.php?page=torles&id={$rowe["id"]}&rk={$rowe["rkod"]}'>Időpont törlése</a>";
            //}

            if ($_POST["datum"] != "" && !checkIdopontSzabad($_POST)) $formerror .= "{$webText["idopontlefoglaltak"]}<br>";
            if (!isset($_POST["rinterval"])) $_POST["rinterval"] = 0;
            if (!isset($_POST["telephely"])) $_POST["telephely"] = "";

            if (!isset($_SESSION["user"])) {
                if (isset($_POST["version2"])) {
                    if (isset($_POST["g-recaptcha-response"])) $captcha = $_POST["g-recaptcha-response"];
                    if (isset($captcha)) {
                        if (!$captcha) {
                            $formerror .= "{$webText["captchaerror1"]}<br/>";
                        } else {
                            $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfCaTIUAAAAAF1-t94n7TBAsKov_dglwP6b8Luo&response=" . urlencode($captcha) . "&remoteip=" . $_SERVER["REMOTE_ADDR"]), true);
                            if ($response["success"] == false) {
                                $formerror .= "{$webText["captchaerror2"]}<br/>";
                            }
                        }
                    } else {
                        $formerror .= "{$webText["captchaerror3"]}<br/>";
                    }
                }
            }


            if ($formerror == "") {
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
                updateFoglalasData($fid);

                $oid = selectFreeOrvosForIdopont($fid);
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
                    sendVisszaIgazolas($fid);
                    header("location:index.php?page=sikeresfoglalas");
                }

                die();
            }
        }


        if (isset($_GET["tesztvissza"])) {
            sendVisszaIgazolas(89252);
            die("sent");
        }


        if (isset($_REQUEST['idopontfoglalasV2'])) {
            $formerror = "";
            //Hibás adat korrigálás:
            $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            //Mező ellenőrzés:
            //if( $_POST["taj"] == "" ) $formerror.= "{$webText["tajkotelezo"]}<br/>";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $formerror .= "{$webText["tajformat"]}<br/>";
            if ($_POST["helyszin"] == "0") $formerror .= "{$webText["helyszinkotelezo"]}<br/>";
            if ($_POST["datum"] == "") $formerror .= "{$webText["idopontkotelezo"]}<br/>";
            if ($_POST["szurestipus"] == "0") $formerror .= "{$webText["szurestipuskotelezo"]}<br/>";

            if ($_POST["email"] == "") $formerror .= "{$webText["emailkotelezo"]}<br/>";
            if ($_POST["nev"] == "") $formerror .= "{$webText["nevkotelezo"]}<br/>";
            if ($_POST["tel"] == "") $formerror .= "{$webText["telkotelezo"]}<br/>";
            if ($_POST["szuldatum"] == "") $formerror .= "{$webText["szulkotelezo"]}<br/>";
            if (!validateDate($_POST["szuldatum"], "Y-m-d")) $formerror .= "{$webText["szulformat"]}<br/>";

            //if( !isset( $_POST["neme"] )) $formerror.= "{$webText["nemekotelezo"]}<br/>";
            if (!isset($_POST["aszf"])) $formerror .= "{$webText["aszfkotelezo"]}<br/>";

            //Még nem bevett mezők:
            //if ($_POST["irsz"]=="") $formerror.="Az irányítószám megadása kötelező!<br/>";
            //if ($_POST["varos"]=="") $formerror.="A város megadása kötelező!<br/>";
            //if ($_POST["utca"]=="") $formerror.="Az utca megadása kötelező!<br/>";
            if (isset($_POST["munkakor"])) if ($_POST["munkakor"] == "") $formerror .= "{$webText["munkakorkotelezo"]}<br/>";
            else $_POST["munkakor"] = "";

            //Szabad időpont ellenőrzése:
            if ($_POST["datum"] != "" && !checkIdopontSzabad($_POST)) $formerror .= "{$webText["idopontlefoglaltak"]}<br>";

            //Captcha ellenőrzés ha a páciens nincs belépve:
            if (!isset($_SESSION["user"])) {
                if (isset($_POST["version2"])) {
                    if (isset($_POST["g-recaptcha-response"])) $captcha = $_POST["g-recaptcha-response"];
                    if (isset($captcha)) {
                        if (!$captcha) $formerror .= "{$webText["captchaerror1"]}<br/>";
                        else {
                            $response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfCaTIUAAAAAF1-t94n7TBAsKov_dglwP6b8Luo&response=" . urlencode($captcha) . "&remoteip=" . $_SERVER["REMOTE_ADDR"]), true);
                            if ($response["success"] == false) $formerror .= "{$webText["captchaerror2"]}<br/>";
                        }
                    } else $formerror .= "{$webText["captchaerror3"]}<br/>";
                }
            }

            //Ha nincsen hiba akkor rögzítem az adatok az adatbázisban:
            if ($formerror == "") {
                if (!isset($_POST["tudoszuro"])) $_POST["tudoszuro"] = 0;

                $rn = rand(1000000, 9999999);

                $paciensId = 0;

                if (isset($_SESSION["user"]["id"])) $paciensId = intval($_SESSION["user"]["id"]);
                else {
                    $request_user = sql_query("SELECT * FROM felhasznalok WHERE (taj = ? OR email = ?) and cegid=?",
                        array($_REQUEST['taj'], $_REQUEST['email'], $_SESSION["helyszindata"]["id"]));
                    if (sql_num_rows($request_user) > 0) {
                        $userInfo = sql_fetch_array($request_user);
                        $paciensId = $userInfo['id'];
                    } else {
                        sql_query("INSERT INTO felhasznalok SET 
						   validated = 1, cegid = ?, regtime = NOW(), taj  = ?, email 	  = ?, nev 		 = ?, telefon   = ?, 
						   munkakor  = ?, irsz 	= ?, varos	 = ?, 	  utca = ?, szulhely  = ?, anyjaneve = ?, szuldatum = ? ",
                            array($_SESSION["helyszindata"]["id"], $_REQUEST['taj'], $_REQUEST['email'], $_REQUEST['nev'], $_REQUEST['tel'],
                                $_REQUEST['munkakor'], $_REQUEST['irsz'], $_REQUEST['varos'], $_REQUEST['utca'], $_REQUEST['szulhely'],
                                $_REQUEST['anyjaneve'], $_REQUEST['datum']
                            ));
                        $paciensId = sql_insert_id();
                    }
                }

                if (isset($_SESSION["user"]["id"])) $paciensId = intval($_SESSION["user"]["id"]);

                sql_query("INSERT INTO foglalasok SET 
				   regdatum  = NOW(), paciensid = ?, cegid = ?, datum 	 = ?, helyszinid = ?, 
				   szurestipusid = ?, nev 		= ?, email = ?, telefon  = ?, szuldatum  = ?, 
				   neme  		 = ?, taj       = ?, megj  = ?, rlang 	 = ?, rkod 		 = ?",
                    array($paciensId, $_SESSION["helyszindata"]["id"], $_POST["datum"], $_POST["helyszin"], $_POST["szurestipus"],
                        $_POST["nev"], $_POST["email"], $_POST["tel"], $_POST["szuldatum"],
                        $_POST["neme"], $_POST["taj"], $_POST["megj"], $_COOKIE["lang"], $rn));

                $fid = sql_insert_id();
                updateFoglalasData($fid);

                if ($_POST['kuponkod'] != "") {
                    $foglalas = sql_fetch_array(sql_query("SELECT fogl.datum, kl.foglalasid, fogl.szurestipusid FROM foglalasok fogl LEFT JOIN kupon_lista kl ON kl.foglalasid = fogl.id WHERE fogl.id = ? ", array($fid)));
                    $check = kuponCheck($_POST['kuponkod'], 3, date("Y-m-d", strtotime($foglalas['datum'])), $foglalas['szurestipusid']);
                    if ($check == "usable") {
                        $kupon = sql_fetch_array(sql_query("SELECT * FROM kuponkodok WHERE kod = ?", array($_POST['kuponkod'])));
                        sql_query("INSERT INTO kupon_lista SET kuponid = ?, kuponkod = ?, foglalasid = ?",
                            array($kupon['id'], $kupon['kod'], $fid));
                    }
                }

                $oid = selectFreeOrvosForIdopont($fid);
                sql_query("UPDATE foglalasok SET orvosassigned = ? WHERE id = ?", array($oid, $fid));

                if (isset($_SESSION["beutaloid"]) && isset($_SESSION["user"]) && $rowb = sql_fetch_array(sql_query("SELECT * FROM beutalok WHERE id = ?", array($_SESSION["beutaloid"])))) {
                    sql_query("UPDATE beutalok SET foglalasid = ? WHERE id=?", array($fid, $_SESSION["beutaloid"]));
                    sql_query("UPDATE fogalalasok SET megj = ? where id = ?", array($rowb["megj"], $fid));
                    unset($_SESSION["beutaloid"]);
                }

                //altipusok tárolása
                $res = sql_query("SELECT * FROM arak WHERE INSTR( cegid, ? ) and tipusid = ? and csomag=0", array("|{$_SESSION["helyszindata"]["id"]}|", $_POST["szurestipus"]));
                while ($row = sql_fetch_array($res)) {
                    if (isset($_POST["altipus{$row["id"]}"])) {
                        sql_query("INSERT INTO fizkapcs SET fid = ?, aid = ?, megnev = ?, ar = ?, valuta = ?",
                            array($fid, $row["id"], $row["megnev"], $row["price"], $row["penznem"]));
                    }
                }

                if (isset($_SESSION["remotebeutalo"]) || $_SESSION["helyszindata"]["visszaigazolas"] == 0) {
                    //orvos jött, akkor nem kérünk visszaigazolást, megyünk visszaigazolni automatikusan
                    header("Location:index.php?page=megerosites&id={$fid}&rk={$rn}");
                } else {
                    //visszaigazolást kérünk
                    sendVisszaIgazolas($fid);
                    header("Location:index.php?page=sikeresfoglalas");
                }

                die();
            }
        }

        if (isset($_GET["dodeleteidopont"])) {
            deleteFoglalas($_GET["id"], $_GET["rk"]);
            header("location:index.php?page=torlessikeres");
            die();
        }
        if (isset($_GET["deltime"])) {
            deleteFoglalas($_GET["id"], $_GET["rk"]);
            header("location:index.php?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["showpaciensfiles"])) {
            echo showPaciensFiles();
            die();
        }


        if (isset($_POST["deletepaciensdoc"])) {
            $docAgent = new DocAgent();
            $docAgent->deleteDoc($_POST["id"], $_POST["k"]);
            echo showPaciensFiles();
            die();
        }


        if (isset($_REQUEST["addpaciensfiles"])) {
            if (!isset($_SESSION["filefix"])) $_SESSION["filefix"] = rand(10000, 99999);
            $fileFix = $_SESSION["filefix"];

            $docAgent = new DocAgent();

            foreach ($_FILES as $file) {
                $sess = $fileFix . session_id();
                $result = $docAgent->saveDoc($file, array('beutaloid' => 0, 'userid' => 0, 'megnev' => $_POST["dokmegnev"], 'sess' => $sess));

                if ($result != "0") {
                    echo $result;
                    die;
                }
            }
            die();
        }

        if (isset($_POST["gettipusmegj"])) {
            echo getTipusMegj($_SESSION["helyszindata"]["id"], $_POST["tid"], $_POST["hid"]);
            die();
        }


        if (isset($_GET["setbeutalo"])) {
            if ($row = sql_fetch_array(sql_query("select * from beutalok where id='" . intval($_GET["setbeutalo"]) . "' and userid='" . intval($_SESSION["user"]["id"]) . "'"))) {
                $_SESSION["beutaloid"] = $row["id"];
            }

            header("location:index.php?page=main");
            die();
        }


        if (isset($_POST["requestsmskod"])) {
            $taj = $_POST["taj"];
            $taj = str_replace("-", "", $taj);
            $taj = trim(str_replace(" ", "", $taj));

            if ($_POST["captcha"] != $_SESSION["captcha"]) {
                echo "A beírt szám nem egyezik!";
                die();
            }

            if ($taj == "") {
                echo "A TAJ szám megadása kötelező!";
                die();
            }
            if (!ctype_digit($taj) && $taj != "") {
                echo "A TAJ szám formátuma nem megfelelő!";
                die();
            }

            if (!$rowu = sql_fetch_array(sql_query("select f.*,UNIX_TIMESTAMP()-UNIX_TIMESTAMP(rkoddatum) as rkodsec from felhasznalok f where taj='" . addslashes($taj) . "' and cegid='{$_SESSION["helyszindata"]["id"]}'"))) {
                echo "A megadott TAJ számmal nem található felhasználó!";
                die();
            }

            if ($rowu["rkodsec"] < 600 && $rowu["rkodsec"] != NULL) {
                echo "sentback";
                die();
            }

            //kód generálása és kiküldése:
            $rn = rand(11000, 98000);
            sql_query("update felhasznalok set rkod='{$rn}',rkoddatum=now() where id='{$rowu["id"]}'");
            sendLoginSMSKod($rowu["id"]);

            echo "sentnow";
            die();
        }


        if (isset($_POST["logintrywithtaj"])) {
            $taj = $_POST["taj"];
            $taj = str_replace("-", "", $taj);
            $taj = trim(str_replace(" ", "", $taj));

            if ($taj == "") {
                echo "A TAJ szám megadása kötelező!";
                die();
            }
            if (!ctype_digit($taj) && $taj != "") {
                echo "A TAJ szám formátuma nem megfelelő!";
                die();
            }


            if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where taj='" . addslashes($taj) . "' and rkod='" . intval($_POST["kod"]) . "' and cegid='" . addslashes($_SESSION["helyszindata"]["id"]) . "'"))) {

                if (strtotime("now") - strtotime($rowu["rkoddatum"]) > 600) {
                    echo "lejartkod";
                    die();
                }

                $_SESSION["loggeduser"] = $rowu["id"];
                echo "ok";
            } else {
                echo "A megadott TAJ szám, vagy kód nem megfelelő!";
            }
            die();
        }

        if (isset($_POST["adduserbeutalo"])) {
            if (isset($_SESSION["user"]["id"])) {

                $data = explode("-", $_POST["beutalotarget"]);
                $hid = intval($data[0]);
                $sztid = intval($data[1]);

                sql_query("insert into beutalok set datum=now(),selfcreated=1,userid=?,cegid=?,helyszinid=?,szurestipusid=?,naploszam=?,megj=?", array($_SESSION["user"]["id"], $_SESSION["helyszindata"]["id"], $hid, $sztid, $_POST["naploszam"], $_POST["beutalomegj"]));
            }

            header("location:index.php?page=beutalok");
            die();
        }

        if (isset($_GET["delbeutalo"])) {
            sql_query("delete from beutalok where id=? and userid=?", array($_GET["delbeutalo"], $_SESSION["user"]["id"]));
            header("location:index.php?page=beutalok");
            die();
        }

        if (isset($_GET["showidopontvalaszto"])) {
            $honnan = intval($_GET["honnan"]);
            if ($honnan < 0) $honnan = 0;

            $helyszin = intval($_GET["helyszin"]);
            $szurestipus = intval($_GET["szurestipus"]);
            $taj = $_GET["taj"];

            $szunnapok[] = "";
            $rows = sql_fetch_array(sql_query("select * from settings"));
            $n = explode(",", $rows["szunnapok"]);
            for ($i = 0; $i < count($n); $i++) {
                $szunnapok[] = trim($n[$i]);
            }

            $foglaltnapok[] = "";
            $res = sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap>=? and (szurestipusid=0 or szurestipusid=?)", array($helyszin, $_SESSION["helyszindata"]["id"], date("Y-m-d"), $szurestipus));
            while ($row = sql_fetch_array($res)) {
                $foglaltnapok[] = $row["nap"];
            }

            $res = sql_query("select datum,COUNT(*) AS hany,GROUP_CONCAT(szurestipusid) AS szurestipusok from foglalasok where helyszinid='{$helyszin}' and szurestipusid='{$szurestipus}' and datum>now() GROUP BY datum");
            while ($row = sql_fetch_array($res)) {
                $i = substr($row["datum"], 0, 16);
                $foglaltidopontok[$i] = $row;
            }

            if (!$rowmax = sql_fetch_array(sql_query("SELECT MIN(tol) as minrendeles,MAX(ig) as maxrendeles FROM orvos_beosztas WHERE helyszinid='{$helyszin}' and (cegid='{$_SESSION["helyszindata"]["id"]}') and (instr(tipusok,'|{$szurestipus}|')) HAVING MAX(tol) IS NOT NULL"))) {
                echo "<div style='margin:10px 0px;'>Erre a szűrés típusra nincsenek beállítva rendelési időpontok.</div>";
                die();
            }

            $res = sql_query("select b.*,o.nev as orvosnev from orvos_beosztas b
                left join orvosok o on o.id=b.orvosid
                where b.helyszinid='{$helyszin}' and (b.cegid='{$_SESSION["helyszindata"]["id"]}' or b.cegid=0) and (instr(b.tipusok,'|{$szurestipus}|'))");
            while ($row = sql_fetch_array($res)) {
                $beosztas[] = $row;
                $beosztasData[$row["nap"]] = $row;
                //$beosztasOrvos[$row["nap"]]=$row["orvosnev"];
            }

            echo "<div style='margin:10px 0px 10px 0px;'>";
            //echo "Itt lesz az időpontválasztó... most még csak fixen kirak pár lehetőséget.<br>";
            echo "<div>{$webText["valasszidopontot"]}:</div>";
            echo "<div style='margin-top:5px;'><a href='javascript:showIdoPontValaszto(" . ($honnan - 7) . ")'>{$webText["elo7"]}</a> | <a href='javascript:showIdoPontValaszto(" . ($honnan + 7) . ")'>{$webText["kov7"]}</a></div>";
            echo "<table><tr>";

            $dist = "6 hour";
            if ($helyszin == 1) $dist = "0 hour"; //jász utca bármikor foglalható

            for ($i = 0; $i <= 6; $i++) {

                $nap = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $i + $honnan, date("Y")));
                $wd = date("N", mktime(0, 0, 0, date("m"), date("d") + $i + $honnan, date("Y")));  //day of week
                $wn = date("W", mktime(0, 0, 0, date("m"), date("d") + $i + $honnan, date("Y")));  //number of week

                echo "<td valign='top'>";
                echo "<div style='background:#0a0;padding:2px 10px 2px 10px;color:#fff;font-weight:bold;text-align:center;'>{$nap}<br>{$webText["hetnap"][$wd]}</div>";

                $beginora = round(substr($rowmax["minrendeles"], 0, 2));
                $beginperc = round(substr($rowmax["minrendeles"], 3, 2));

                if (!isset($beosztasData[$wd]["binterval"])) {
                    echo "<div style='text-align:center;margin:5px;padding:5px 0px;border-radius:5px;'>{$webText["nincsrendeles"]}</div>";
                    echo "</td>";
                    continue;
                }

                $binterval = $beosztasData[$wd]["binterval"];
                $firstfreetime = "";
                for ($o = 0; $o <= 55; $o++) {
                    $ora = date("H:i", mktime($beginora, $beginperc + $o * $binterval, 0, date("m"), date("d"), date("Y")));
                    if (strtotime($ora) >= strtotime($rowmax["maxrendeles"])) break;

                    echo "<div style='text-align:center;'>";

                    $java = "nemfog();";
                    $class = "foglaltbutton";

                    if (isBeosztasWeekDay($beosztas, $wd, $wn) && !in_array($nap, $szunnapok)) {
                        if (strtotime("now + {$dist}") < strtotime("{$nap} {$ora}")) {
                            $hanyfoglalt = 0;
                            if (isset($foglaltidopontok["{$nap} {$ora}"])) $hanyfoglalt = $foglaltidopontok["{$nap} {$ora}"]["hany"];


                            if (!in_array("{$nap}", $foglaltnapok)) {
                                $szabad = isFreeIdopont($wd, $ora, $beosztas, $hanyfoglalt);
                                if ($szabad[0] == 1 || $szabad[0] == 2) {
                                    $java = "chooseIdoPont(\"{$nap} {$ora}\");return false;";
                                    $class = "foglalhatobutton";
                                    if ($szabad[0] == 2 && $firstfreetime != "") {
                                        $java = "nemfogs(\"{$firstfreetime}\");return false;";
                                        $class .= " halv";
                                    }
                                    if ($firstfreetime == "") $firstfreetime = $ora;
                                }
                            }
                        }
                    }

                    $t = "";
                    if ($_SESSION["helyszindata"]["id"] == 15) {
                        if (isset($beosztasData[$wd]["orvosnev"])) {
                            $t = "title='" . $beosztasData[$wd]["orvosnev"] . "'";
                        }
                    }

                    echo "<a class='{$class}' {$t} onclick='{$java}' href='#'>{$ora}</a>";
                    echo "</div>";
                }

                echo "</td>";

            }

            echo "</tr></table>";
            echo "</div>";
            die();
        }

    }

    public function sendEljottMail($foglalasData) {
        include_once("phpmailer/class.phpmailer.php");
        $mail = new PHPMailer();
        $mail->From = "noreply@hungariamed.hu";
        $mail->FromName = "Hungariamed";
        //$mail->AddAddress($foglalasData["email"]); //ne élesítsd még
        $mail->AddAddress("jns@jns.hu");
        $mail->AddReplyTo("noreply@hungariamed.hu");
        $mail->IsHTML(true);

        if ($emailData = sql_fetch_array(sql_query("select * from ertekeles_formok where (instr(rule_cegids,'|{$foglalasData["cegid"]}|') or rule_cegids='all') and rule_mail=1 and rule_aftereljott=1"))) {
            $mailSzoveg = $emailData["mailszoveg_{$foglalasData["rlang"]}"];
            if ($mailSzoveg == "") $mailSzoveg = $emailData["mailszoveg_hu"];
            $mailSubject = $emailData["megnev_{$foglalasData["rlang"]}"];
            if ($mailSubject == "") $mailSubject = $emailData["megnev_hu"];
            if ($mailSzoveg != "" && $mailSubject != "") {
                $mailSzoveg = str_replace("#nev#", $foglalasData["nev"], $mailSzoveg);
                $mail->Subject = iconv("UTF-8", "ISO-8859-2", $mailSubject);
                $mail->Body = iconv("UTF-8", "ISO-8859-2", $mailSzoveg);
                $mail->Send();
                sql_query("update foglalasok set eljottmail=1 where id=?", array($foglalasData["id"]));
            }
        }
        return;
    }

    function checkIdopontSzabad($data)
    {
        //TODO: időpont szabadság vizsgálása még kell ide..
        //$_POST["datum"]
        //$_POST["helyszin"]
        //$_POST["szurestipus"]

        if (selectOrvosForIdopont($data["datum"], $data["helyszin"], $data["szurestipus"], $data["orvosselected"])) return true;
        return false;
    }


    function sendUserSMSKod($userid)
    {
        if ($rowu = sql_fetch_array(sql_query("SELECT f.* FROM felhasznalok f 
	    LEFT JOIN cegek c ON c.id=f.cegid
	    WHERE f.id=? AND c.`noregsms`=0", array($userid)))) {
            include("includes/seeme-gateway-class.php");
            sendSMS($rowu["telefon"], "kód a regisztráció befejezéséhez: {$rowu["rkod"]}");
        } else {
            sql_query("update felhasznalok set validated=1 where id=?", array($userid));
        }
    }

    function sendLoginSMSKod($userid)
    {
        if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where id='{$userid}'"))) {
            include("includes/seeme-gateway-class.php");
            sendSMS($rowu["telefon"], "kód a bejelentkezéshez: {$rowu["rkod"]}");
        }
    }

    function sendVisszaIgazolas($id)
    {
        //Visszaigazolás a foglalásról, megerősítés kérése
        $h = "cim";
        if ($_SESSION["helyszindata"]["nocim"] == 1) $h = "megnev";

        $res = sql_query("SELECT h.{$h} AS helyszin,sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id='{$id}'");
        if ($row = sql_fetch_array($res)) {
            if ($row["rlang"] == "en" && $row["szurestipus_en"] != "") $row["szurestipus"] = $row["szurestipus_en"];
            if ($row["rlang"] == "de" && $row["szurestipus_de"] != "") $row["szurestipus"] = $row["szurestipus_de"];

            include_once("phpmailer/class.phpmailer.php");
            $mail = new PHPMailer();
            $mail->From = "noreply@hungariamed.hu";
            $mail->FromName = "Hungariamed";
            $mail->AddAddress($row["email"]);
            $mail->AddReplyTo("noreply@hungariamed.hu");
            $mail->IsHTML(true);

            $webTextLocal = getWebTexts($row["rlang"]);
            $t = iconv("UTF-8", "ISO-8859-2", $webTextLocal["mailtitleerositsdmeg"]);

            $mbody = "";

            if ($row["rlang"] == "hu") {
                $mbody = "<h2>Már majdnem kész!</h2>
                ha nem erősíti meg <b>1 órán belül</b>, a foglalása automatikusan <b>törlődik.</b><br/>
                {$webTextLocal["nev"]}: {$row["nev"]}<br>
                {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
                <b>Időpont: {$row["datum"]}</b><br>
                {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
                {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
                <br/>
                Az időpont foglalásának megerősítéséhez <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=megerosites&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>kattintson ide</a><br>
                <br/>
                Üdvözlettel:<br>Hungariamed";
            }
            if ($row["rlang"] == "de") {
                $mbody = "<h2>Már majdnem kész!</h2>
                ha nem erősíti meg <b>1 órán belül</b>, a foglalása automatikusan <b>törlődik.</b><br/>
                {$webTextLocal["nev"]}: {$row["nev"]}<br>
                {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
                <b>Időpont: {$row["datum"]}</b><br>
                {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
                {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
                <br/>
                Az időpont foglalásának megerősítéséhez <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=megerosites&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>kattintson ide</a><br>
                <br/>
                Üdvözlettel:<br>Hungariamed";
            }
            if ($row["rlang"] == "en") {
                $mbody = "<h2>Almost done!</h2>
                if you do not confirm <b>within 1 hour</b>, your reservation will be automatically <b>canceled</b>.<br/>
                {$webTextLocal["nev"]}: {$row["nev"]}<br>
                {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
                <b>Time: {$row["datum"]}</b><br>
                {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
                {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
                <br/>
                To confirm your reservation <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=megerosites&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>click here</a><br>
                <br/>
                Regards<br>Hungariamed";
            }

            $mail->Subject = $t;
            $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
            //$mail->AddAttachment("");
            $mail->Send();
        }
    }




    function sendNotConfirmedReservationMessages($id)
    {
        /*
        nem visszaigazolt foglalás esetén:
        - mail a paciensnek
        - mail a hmm-nek
        - sms a paciensnek
        */
        $h = "cim";
        if ($_SESSION["helyszindata"]["nocim"] == 1) $h = "megnev";;

        $res = sql_query("SELECT h.{$h} AS helyszin,sz.megnev AS szurestipus,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=?", array($id));
        if ($row = sql_fetch_array($res)) {
            include_once("phpmailer/class.phpmailer.php");
            $mail = new PHPMailer();
            $mail->From = "noreply@hungariamed.hu";
            $mail->FromName = "Hungariamed";
            $mail->AddAddress($row["email"]);
            //$mail->AddAddress("jns@jns.hu");
            $mail->AddReplyTo("noreply@hungariamed.hu");
            $mail->IsHTML(true);

            $t = iconv("UTF-8", "ISO-8859-2", "Figyelem! Foglalását töröltük!");

            $mbody = "<h2>Foglalását töröltük!</h2>";
            $mbody .= "Előző levelünkben küldött megerősítő hivatkozásra nem kattintott rá, ezért a {$row["datum"]} időpontra szóló foglalását töröltük.<br/>";
            $mbody .= "<br/>";
            $mbody .= "Üdvözlettel:<br/>Hungariamed";

            $mail->Subject = $t;
            $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
            //$mail->AddAttachment("");
            $mail->Send();

            $mail = new PHPMailer();
            $mail->From = "noreply@hungariamed.hu";
            $mail->FromName = "Hungariamed";
            $mail->AddAddress("bejelentkezes@hungariamed.hu");
            $mail->AddReplyTo("noreply@hungariamed.hu");
            $mail->IsHTML(true);

            $t = iconv("UTF-8", "ISO-8859-2", "Egy paciens foglalása törölve lett!");

            $mbody = "<h2>Törölt foglalás</h2>";
            $mbody .= "A paciens foglalt, de nem igazolta vissza a következő rendelést, ezért azt töröltük:<br/>";
            $mbody .= "Név: {$row["nev"]}<br/>";
            $mbody .= "Telefon: {$row["telefon"]}<br/>";
            $mbody .= "Email: {$row["email"]}<br/>";
            $mbody .= "<b>Időpont: {$row["datum"]}</b><br/>";
            $mbody .= "Szűréstípus: {$row["szurestipus"]}<br/>";
            $mbody .= "Helyszín: {$row["helyszin"]}<br/>";
            $mbody .= "<br/>";
            $mbody .= "Hívd fel az ügyfelet egyeztetés céljából.</a><br>";

            $mail->Subject = $t;
            $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
            //$mail->AddAttachment("");
            $mail->Send();

            include_once("includes/seeme-gateway-class.php");
            sendSMS($row["telefon"], "Figyelem, {$row["datum"]} foglalását visszaigazolás hiányában töröltük!");
        }
    }


    function sendToUser($id)
    {
        //visszaigazoló levél a foglalás sikerességéről

        if (isset($_GET["tesztvisszaigazolo"])) {
            $res = sql_query("SELECT " . cimLangQuery("helyszin") . ",sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail,c.domain FROM foglalasok f
            LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
            LEFT JOIN cegek c on c.id=f.cegid
            LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
            WHERE f.id='{$id}'");
        } else {
            $res = sql_query("SELECT " . cimLangQuery("helyszin") . ",sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail,c.domain FROM foglalasok f
            LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
            LEFT JOIN cegek c on c.id=f.cegid
            LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
            WHERE f.id='{$id}' and f.userertesitve=0");
        }

        if ($row = sql_fetch_array($res)) {
            if ($row["rlang"] == "en" && $row["szurestipus_en"] != "") $row["szurestipus"] = $row["szurestipus_en"];
            if ($row["rlang"] == "de" && $row["szurestipus_de"] != "") $row["szurestipus"] = $row["szurestipus_de"];

            $extraMsg = "";

            if ($result = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE id = '" . intval($row["paciensid"]) . "'"))) {
                if ((strtotime("now") - strtotime($result["regtime"])) < 3600) {
                    $c = explode(",", $row["domain"]);
                    $extraMsg = "A kiállított leleteit és dokumentumait a https://{$c[0]}.hungariamed.hu oldalon a taj számával megtekintheti online.<br/>";
                }
            }

            $webTextLocal = getWebTexts($row["rlang"]);

            sql_query("update foglalasok set userertesitve=1 where id='{$id}'");

            $resv = sql_query("SELECT * FROM visszaigazolok WHERE cegid='{$row["cegid"]}' AND (orvosid='{$row["orvosassigned"]}' OR orvosid=0) AND (helyszinid='{$row["helyszinid"]}' OR helyszinid=0) AND TRIM(szoveg)<>''");


            include_once("phpmailer/class.phpmailer.php");
            $mail = new PHPMailer();
            $mail->From = "noreply@hungariamed.hu";
            $mail->FromName = "Hungariamed";
            $mail->AddAddress($row["email"]);
            $mail->CharSet = "UTF-8";
            $mail->AddReplyTo("noreply@hungariamed.hu");
            $mail->IsHTML(true);

            $t = "{$webTextLocal["sikeresidopontreg"]}";

            $mbody = "";
            $mbody .= "<h1>{$row["datum"]} - {$row["helyszin"]}</h1>";
            $mbody .= "{$webTextLocal["nev"]}: {$row["nev"]}<br>";
            $mbody .= "{$webTextLocal["telefon"]}: {$row["telefon"]}<br><br>";
            $mbody .= "<b>{$webTextLocal["idopont"]}: {$row["datum"]}</b><br><br>";
            $mbody .= "{$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>";
            $mbody .= "{$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>";

            while ($rowv = sql_fetch_array($resv)) {
                $maplink = "";
                if ($rowv["mapurl"] != "") $maplink = "<a href='{$rowv["mapurl"]}'>Az útvonal térképen megjelenítéséhez kattintson ide.</a>";
                $rowv["szoveg"] = str_replace("#maplink#", $maplink, $rowv["szoveg"]);
                $mbody .= "<hr>" . nl2br($rowv["szoveg"]);
            }

            $mbody .= "<hr>";

            if ($row["rlang"] == "hu") {
                $mbody .= "Ha törölni szeretné ezt a foglalását, kérjük kattintson a következő linkre: <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=torles&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>időpont regisztráció törlése</a><br>";
                $mbody .= "Amennyiben módosítani szeretné a foglalását, abban az esetben először törölje a régi időpontját a fenti linken, utána pedig regisztrálja újra.<br>{$extraMsg}";
                $mbody .= "<br/>";
                $mbody .= "Üdvözlettel:<br>Hungariamed";
            }
            if ($row["rlang"] == "de") {
                $mbody .= "Wenn Sie möchten Diese Termin Reservierung Canceln, bitte drücken Sie an Ihre Brief <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=torles&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>Die Termin Registration Canceln</a> LINK.<br>";
                $mbody .= "Wenn Sie möchten Ihre Reservierung Verändern ,bitte Streichen Sie aus den anderen Zeitpunkt, dannach registrieren bitte nochmal.<br>";
                $mbody .= "<br/>";
                $mbody .= "Üdvözlettel:<br>Hungariamed";
            }
            if ($row["rlang"] == "en") {
                $mbody .= "If you wish to cancel this appointment, please click on link: <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=torles&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>Cancellation of confirmed appointment</a><br>";
                $mbody .= "If you would like to modify your appointment, first cancel your old appointment then register it again.<br>";
                $mbody .= "<br/>";
                $mbody .= "Regards:<br>Hungariamed";
            }

            $mail->Subject = $t;
            //$mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
            $mail->Body = $mbody;
            //$mail->AddAttachment("");

            if (true) {
                $mail->addStringAttachment(getCalendarItem($row), 'foglalas.ics', 'base64', 'text/calendar');
            }

            $mail->Send();

        }
    }




    function deleteFoglalas($id, $kod)
    {
        if ($row = sql_fetch_array(sql_query("select id from foglalasok WHERE id=? and rkod=? and datum>now() and eljott=0", array($id, $kod)))) {
            sql_query("update beutalok set foglalasid='0' where foglalasid='{$row["id"]}'");
            sql_query("delete from foglalasok WHERE id='{$row["id"]}'");
        }
        return;
    }




    function showDebugInfo($s)
    {
        if ($_SERVER["REMOTE_ADDR"] == "88.151.97.121") {
            echo "<div>{$s}</div>";
        }
    }



    function isBeosztasWeekDay($beosztas, $wd, $weekNumber = 0)
    {
        for ($i = 0; $i < count($beosztas); $i++) {
            if ($beosztas[$i]["nap"] == $wd) {
                if ($weekNumber == 0) return true;
                if ($beosztas[$i]["hetek"] == 2) {
                    if ($weekNumber % 2 == 0) {
                        return true;
                    } else {
                        return false;
                    }
                }
                if ($beosztas[$i]["hetek"] == 1) {
                    if ($weekNumber % 2 == 0) {
                        return false;
                    } else {
                        return true;
                    }
                }
                return true;
            }
        }
        return false;
    }

    function isFreeIdopont($wd, $ora, $beosztas, $hanyfoglalt)
    {
        $szabad[0] = 0;

        $dokik = 0;
        for ($i = 0; $i < count($beosztas); $i++) {
            $beo = $beosztas[$i];
            if ($beo["nap"] == $wd) {
                if (strtotime(date("Y-m-d {$ora}")) >= strtotime(date("Y-m-d {$beo["tol"]}")) && strtotime(date("Y-m-d {$ora}")) < strtotime(date("Y-m-d {$beo["ig"]}"))) {
                    $dokik++;

                    //csak sorban foglalható időpont ellenőrzése
                    if ($beo["csaksorban"] == 1) {
                        if (isset($GLOBALS["cs{$beo["id"]}"])) {
                            $szabad[0] = 2;
                            $szabad[1] = $GLOBALS["cs{$beo["id"]}"];
                        } else {
                            $GLOBALS["cs{$beo["id"]}"] = $ora;
                        }
                    }

                }
            }
        }
        if ($dokik > $hanyfoglalt) {
            if ($szabad[0] == 0) $szabad[0] = 1;
        } else {
            $szabad[0] = 0;
        }

        return $szabad;
    }


    function displayFejlec($title = "")
    {
        global $webText;
        $style = "";
        if ($_SESSION['helyszindata']['id'] == 91) {
            $img = "<img src='images/hungarian_crest.png' height='30' />";
        } else $img = "";

        if ($_SESSION["helyszindata"]["fejleccolor"] != "") $style .= "background:{$_SESSION["helyszindata"]["fejleccolor"]};";


        return "<div class='fejlecdiv' style='{$style}'>{$img} {$_SESSION["helyszindata"]["megnev"]} - {$webText["idopontfoglalas"]}" . ($title != "" ? " - {$title}" : "") . "</div>";
    }


    function szurestipusvalaszto($helyszinid, $selected = 0, $onlyselected = 0)
    {
        $tipusok = array();

        $rest = sql_query("select * from szurestipusok");
        while ($rowt = sql_fetch_array($rest)) {
            $tipusnevek[$rowt["id"]] = $rowt["megnev"];
        }

        $addJava = "";
        if ($_SESSION["helyszindata"]["id"] == 11) {
            $addJava = "if (this.value==1) { $(\"#fogleuwarn\").show(); } else { $(\"#fogleuwarn\").hide(); }";
        }


        $htmlout = "";
        $htmlout .= "<select name='szurestipus' id='szurestipus' onchange='clearIdopontValaszto();showTipusMegj(this.value);{$addJava}'>";
        $htmlout .= "<option value='0'>{$webText["valasszon"]}!</option>";

        /*
        $res=sql_query("SELECT t.* FROM orvos_beosztas b
            LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
            LEFT JOIN szurestipusok t ON t.`id`=o.`tipusid`
            WHERE b.helyszinid='".addslashes($helyszinid)."'  AND b.cegid='{$_SESSION["helyszindata"]["id"]}' AND t.`megnev` IS NOT NULL
            GROUP BY t.`id`");


        if ($onlyselected==0) $htmlout.="<option value='0'>Válassz!</option>";
        while ($rowt=sql_fetch_array($res)) {
            $tipusok[]=$rowt["id"];
            //$htmlout.="<option value='{$rowt["id"]}'".($selected==$rowt["id"]?" selected":"").">{$rowt["megnev"]}</option>";
        }
        */
        $res = sql_query("SELECT tipusok FROM orvos_beosztas b WHERE b.helyszinid='" . addslashes($helyszinid) . "' AND b.cegid='{$_SESSION["helyszindata"]["id"]}'");
        while ($row = sql_fetch_array($res)) {
            $ta = explode("|", $row["tipusok"]);
            for ($i = 0; $i < count($ta); $i++) {
                if (trim($ta[$i]) != "" && !in_array($ta[$i], $tipusok)) {
                    $tipusok[] = $ta[$i];
                }
            }
        }

        if (isset($tipusok)) {
            for ($i = 0; $i < count($tipusok); $i++) {
                @$tipusdisplay[$tipusok[$i]] = $tipusnevek[$tipusok[$i]];
            }
            if (isset($tipusdisplay)) {
                asort($tipusdisplay);
                foreach ($tipusdisplay as $key => $value) {
                    if ($onlyselected == 1 && $key != $selected) continue;
                    if (trim($value) == "") continue;
                    $htmlout .= "<option value='{$key}'" . ($selected == $key ? " selected" : "") . ">{$value}</option>";
                }
            }
        }

        $htmlout .= "</select>";
        return $htmlout;
    }

    function szuresTipusValasztoNewV2($helyszinid, $selected = NULL, $onlyselected = NULL)
    {
        $tipusok = array();

        $rest = sql_query("SELECT * FROM szurestipusok");
        while ($rowt = sql_fetch_array($rest)) {
            $tipusnevek[$rowt["id"]] = $rowt["megnev"];
        }

        $addJava = "";
        if ($_SESSION["helyszindata"]["id"] == 11) {
            $addJava = "if (this.value==1) { $(\"#fogleuwarn\").show(); } else { $(\"#fogleuwarn\").hide(); }";
        }

        $htmlout = '';
        $htmlout .= '<SELECT name = "szurestipus" class = "design-put" id = "szurestipus">';
        $htmlout .= '<option value = "0"> - Válassz Szűrést! - </option>';
        $res = sql_query("SELECT tipusok FROM orvos_beosztas b 
                           WHERE b.helyszinid = '" . addslashes($helyszinid) . "' AND b.cegid = '11' ");

        while ($row = sql_fetch_array($res)) {
            $ta = explode("|", $row["tipusok"]);

            for ($i = 0; $i < count($ta); $i++) {
                if (trim($ta[$i]) != "" && !in_array($ta[$i], $tipusok)) {
                    $tipusok[] = $ta[$i];
                }
            }
        }

        if (isset($tipusok)) {
            for ($i = 0; $i < count($tipusok); $i++) {
                @$tipusdisplay[$tipusok[$i]] = $tipusnevek[$tipusok[$i]];
            }
            if (isset ($tipusdisplay)) {

                asort($tipusdisplay);
                foreach ($tipusdisplay as $key => $value) {
                    //if (count($tipusdisplay)==1) $selected=$key;
                    if ($onlyselected == 1 && $key != $selected) continue;
                    if (trim($value) == "") continue;
                    if ($key == 1) continue;
                    $htmlout .= "<option value = '" . $key . "' " . ($selected == $key ? "selected" : "") . ">" . $value . "</option>";
                }
            }
        }

        $htmlout .= "</select>";

        if (trim($helyszinid) == "" || $helyszinid == 0) $htmlout = "Válassz előbb helyszínt!<input type = 'hidden' name = 'szurestipus' value = '' />";

        return $htmlout;
    }


    function getTipusMegj($cegid, $tid, $helyszinId = 1)
    {
        $h = "";
        if ($row = sql_fetch_array(sql_query("select * from szurestipusok_megj where cegid='" . intval($cegid) . "' and tipusid='" . intval($tid) . "' and csomag=0"))) {
            if (trim($row["megj"]) != "") $h .= "<div style='background:#f00;color:#fff;padding:10px;display:inline-block;font-weight:bold;'>" . trim($row["megj"]) . "</div>";
        }


        $res = sql_query("SELECT o.* FROM orvos_beosztas b 
        LEFT JOIN orvosok o ON o.id=b.`orvosid`
        WHERE cegid=? AND INSTR(b.`tipusok`,'|" . intval($tid) . "|') AND o.`tel`<>'' and o.telpublic=1 and b.helyszinid=?
        GROUP BY b.`orvosid`", array($cegid, $helyszinId));

        if (sql_num_rows($res) > 0) {
            $h .= "<div style='margin:10px 0px;'>";
            $h .= "<div style='font-weight:bold;'>Elérhetőségek:</div>";
            while ($row = sql_fetch_array($res)) {
                $h .= "<div>Telefonos időpontfoglalás: {$row["tel"]}</div>";
            }
            $h .= "</div>";
        }

        if ($helyszinId == 1 && $_SERVER["REMOTE_ADDR"] == "88.151.97.121") {
            $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and trim(megnev)<>'' and csomag=0", array("|{$cegid}|", $tid));
            if (sql_num_rows($res) > 0) {
                $h .= "<div style='margin:10px 0px;'>";
                $h .= "<div style='font-weight:bold;'>Ha kér, válasszon kiegészítő szolgáltatást:</div>";
                while ($row = sql_fetch_array($res)) {
                    //if ($_COOKIE["lang"]!="hu" && trim($row["megnev_{$_COOKIE["lang"]}"])!="") $row["megnev"]=$row["megnev_{$_COOKIE["lang"]}"];
                    $h .= "<div><input type='checkbox' name='altipus{$row["id"]}' value='1' " . (isset($_POST["altipus{$row["id"]}"]) ? "checked" : "") . " /> {$row["megnev"]}</div>";
                }
                $h .= "</div>";
            }
        }
        if ($_SESSION['helyszindata']['tudoszuroopcio'] == 1 && $helyszinId == 1 && $tid == 1) {
            $h .= "<div><input type='checkbox' name = 'tudoszuro' value = '1' />Tüdőszűrővel nem rendelkezik</div>";
        }
        return $h;
    }


    //törölhető, használd helyette a DocAgent osztályt.
    function get_Doc_Path($fileid)
    {
        $path = "./doc/" . floor($fileid / 1000);
        if (!is_dir($path)) mkdir($path);
        $path .= "/{$fileid}.bin";
        return $path;
    }


    function showPaciensFiles()
    {
        $htmlout = "";
        if (isset($_SESSION["filefix"])) {
            $htmlout .= "<div style='margin:5px 0px;'>";
            $res = sql_query("select * from dokumentumok where sess=?", array($_SESSION["filefix"] . session_id()));
            //if (sql_num_rows($res)==0) $htmlout.="Az adminisztráció megkönnyítése érdekében a beutaló itt feltölthető";
            while ($row = sql_fetch_array($res)) {
                $htmlout .= "<div><div style='display:table-cell;vertical-align:middle;'><a href='#' onclick='deletePaciensDoc({$row["id"]},\"{$row["kod"]}\");return false;'><img style='margin-right:5px;' src='/images/trash.png' /></a></div><div style='display:table-cell;vertical-align:middle;'>{$row["filename"]}</div></div>";
            }
            $htmlout .= "</div>";
        }
        return $htmlout;
    }



    function cimLangQuery($fieldName = "cim")
    {
        $q = "h.cim AS {$fieldName}";
        if (isset($_COOKIE["lang"]) && in_array($_COOKIE["lang"], array("en", "de"))) {
            $q = "IF(h.cim_{$_COOKIE["lang"]}='',h.cim,h.cim_{$_COOKIE["lang"]}) AS {$fieldName}";
        }
        return $q;
    }


}