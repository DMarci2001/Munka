<?php


class RegistrationPage extends CorePage
{
    public function __construct()
    {
        parent::__construct();

        $webText = $this->lang->webText;

        if (isset($_POST["regisztracio"])) {
            if (isset($_POST["szuldatumev"]) && $_POST["szuldatumev"] != "0") {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            } else {
                $_POST["szuldatum"] = "";
            }

            $_POST["telefon"] = $this->utils->fixPhoneNumber($_POST["telefon"]);

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            if (!isset($_POST["munkakor"])) $_POST["munkakor"] = "";
            if (!isset($_POST["torzsszam"])) $_POST["torzsszam"] = "";

            if ($_POST["taj"] == "") $this->formError .= "{$webText["tajkotelezo"]}<br/>";
            if (!ctype_digit($_POST["taj"]) && !empty($_POST["taj"])) $this->formError .= "{$webText["tajformat"]}<br/>";
            if ($_POST["taj"] != "" && sql_fetch_array(sql_query("select taj from felhasznalok where taj=? and cegid=?", array($_POST["taj"], $_SESSION["helyszindata"]["id"])))) $this->formError .= "{$webText["tajletezik"]}<br/>";

            if ($_POST["email"] == "") $this->formError .= "{$webText["emailkotelezo"]}<br/>";
            if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) && $_POST["email"] != "") $this->formError .= "{$webText["emailformat"]}<br/>";
            if ($_POST["email"] != "" && sql_fetch_array(sql_query("select taj from felhasznalok where email=? and cegid=?", array($_POST["email"], $_SESSION["helyszindata"]["id"])))) $this->formError .= "{$webText["emailletezik"]}<br/>";

            if ($_POST["jelszo"] == "") $this->formError .= "{$webText["jelszokotelezo"]}<br/>";
            if ($_POST["jelszo"] != $_POST["jelszo2"]) $this->formError .= "{$webText["ketjelszonem"]}<br/>";
            if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) < Booking_Constants::PASSWORD_LENGTH_MIN) $this->formError .= "{$webText["jelszomin"]}<br/>";
            if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) > Booking_Constants::PASSWORD_LENGTH_MAX) $this->formError .= "{$webText["jelszomax"]}<br/>";
            if ($_POST["nev"] == "") $this->formError .= "{$webText["nevkotelezo"]}<br/>";
            if ($_POST["telefon"] == "") $this->formError .= "{$webText["telkotelezo"]}<br/>";
            if (!ctype_digit($_POST["telefon"]) && $_POST["telefon"] != "") $this->formError .= "{$webText["telformat"]}<br/>";
            if (!empty($_POST["szuldatum"]) && !$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) $this->formError .= "{$webText["szulformat"]}<br/>";

            if (!isset($_POST["neme"])) $_POST["neme"] = 0;

            if (!isset($_POST["aszf"])) $this->formError .= "{$webText["aszfkotelezo"]}<br/>";

            $this->formError .= $this->utils->checkCaptcha();

            if (empty($this->formError)) {
                $rn = rand(11000, 98000);

                sql_query(
                    "insert into felhasznalok set
                    cegid=?,
                    regtime=now(),
                    nev=?,
                    email=?,
                    jelszo=?,
                    telefon=?,
                    szuldatum=?,
                    neme=?,
                    taj=?,
                    irsz=?,
                    varos=?,
                    utca=?,
                    munkakor=?,
                    torzsszam=?,
                    rkod=?",
                    array(
                        $_SESSION["helyszindata"]["id"],
                        $_POST["nev"],
                        $_POST["email"],
                        md5($_POST["jelszo"]),
                        $_POST["telefon"],
                        $_POST["szuldatum"],
                        $_POST["neme"],
                        $_POST["taj"],
                        $_POST["irsz"],
                        $_POST["varos"],
                        $_POST["utca"],
                        $_POST["munkakor"],
                        $_POST["torzsszam"],
                        $rn
                    )
                );

                $_SESSION["loggeduser"] = sql_insert_id();
                $this->utils->sendUserSMSKod($_SESSION["loggeduser"]);

                header("location:index.php");
                die();
            }
        }

        if(isset($_POST["checktajdata"])){
            $response = [];
            $response[0] = array("id"=>"birthdate");
            $response[1] = array("id"=>"zip-code");
            $response[2] = array("id"=>"city");
            $response[3] = array("id"=>"address");
            
            if($q=sql_query("SELECT * FROM foglalasok WHERE taj=?",array($_POST["checktajdata"]))->fetch(PDO::FETCH_ASSOC)){
                if($q["szuldatum"]!="") unset($response[0]);
                if($q["irsz"]!="")      unset($response[1]);
                if($q["varos"]!="")     unset($response[2]);
                if($q["utca"]!="")      unset($response[3]);
                $response = array_values($response);
                
            }
            die(json_encode($response));
        }

        if(isset($_POST["suzuki_registration"])){

            $error = 0;
            $url = "";
            $status = [];
            $minimalRequirement = false;

            if($_POST["taj"]!="" && !$invited=sql_query("SELECT * FROM ghc_segedtabla WHERE torzsszam=?",array($_POST["torzsszam"]))->fetch(PDO::FETCH_ASSOC)){
                $error = "Sajnálatos módon Ön nem jogosult a Suzuki GHC szűrésre.<br><br> Kérjük keresse meg a Magyar Suzuki Zrt. HR Osztályát.";
                die(json_encode(array("error"=>$error,"status" => $status, "url"=> $url)));
            }
            
            if($_POST["taj"]!="" && $registered=sql_query("SELECT * FROM felhasznalok WHERE torzsszam=? AND cegid=?",array($_POST["torzsszam"],904))->fetch(PDO::FETCH_ASSOC)){
                $error = "Ön már regisztrálva van a Suzuki GHC szűrésre.<br><br> Kérem, jelentkezzen be a \"Bejelentkezés\" menüpont alatt a TAJ számával.<br>";
                $error.= "<a href=\"https://{$_SERVER["HTTP_HOST"]}/?page=login\">Bejelentkezésez kattintson ide!</a>";
                die(json_encode(array("error"=>$error,"status" => $status, "url"=> $url)));
            }

            //TAJ alapján ellenőrzöm, van-e foglalási előzmény, és hogy mely adatok állnak rendelkezésre.
            if(isset($_POST["taj"]) && $_POST["taj"]!=""){
                if($preData=sql_query("SELECT * FROM foglalasok WHERE taj=?",array($_POST["taj"]))->fetch(PDO::FETCH_ASSOC)){
                    $minimalRequirement = true;
                    if($_POST["birthdate"]==""){
                        if($preData["szuldatum"]!="") {
                            $_POST["birthdate"] = $preData["szuldatum"];
                        }else{
                            $_POST["birthdate"] = "";
                        }
                    }
                    
                    if($_POST["zip-code"]==""){
                        if($preData["irsz"]!="") {
                            $_POST["zip-code"] = $preData["irsz"];
                        }else{
                            $_POST["zip-code"] = "";
                        }
                    }
                    
                    if($_POST["city"]==""){
                        if($preData["varos"]!="") {
                            $_POST["city"] = $preData["varos"];
                        }else{
                            $_POST["city"] = "";
                        }
                    }
                    
                    if($_POST["address"]==""){
                        if($preData["utca"]!="") {
                            $_POST["address"] = $preData["utca"];
                        }else{
                            $_POST["address"] = "";
                        }
                    }
                }
            }
            

            //TAJ ellenőrzése
            if (isset($_POST["taj"]) && !empty($_POST["taj"])) {
                if ($this->utils->tajCheck($_POST["taj"]) || true) {
                    $status[] = array("id" => "taj", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "taj", "response" => "Helytelen TAJ szám!", "class" => "invalid");
                    $error++;
                }
            } else {
                $status[] = array("id" => "taj", "response" => "Adja meg a TAJ számát!", "class" => "invalid");
                $error++;
            }

             //Törzsszám
             if (isset($_POST["torzsszam"]) && !empty($_POST["torzsszam"])) {
                $status[] = array("id" => "torzsszam", "response" => "Helyes!", "class" => "valid");
            } else {
                $status[] = array("id" => "torzsszam", "response" => "Adja meg a törzsszámát!", "class" => "invalid");
                $error++;
            }

            //Név ellenőrzése
            if (isset($_POST["name"]) && !empty($_POST["name"])) {
                $status[] = array("id" => "name", "response" => "Helyes!", "class" => "valid");
            } else {
                $status[] = array("id" => "name", "response" => "Adja meg a nevét!", "class" => "invalid");
                $error++;
            }

            //Születési dátum ellenőrzése
                if (isset($_POST["birthdate"]) && !empty($_POST["birthdate"])) {
                    $status[] = array("id" => "birthdate", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "birthdate", "response" => "Adja meg a születési dátumát!", "class" => "invalid");
                    $error++;
                }
            
            

            //Email ellenőrzése
            if (isset($_POST["email"]) && !empty($_POST["email"])) {
                if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
                    $status[] = array("id" => "email", "response" => "Adjon meg egy helyes e-mail címet!", "class" => "invalid");
                    $error++;
                } else {
                    $status[] = array("id" => "email", "response" => "Helyes e-mail cím!", "class" => "valid");
                }
            } else {
                $status[] = array("id" => "email", "response" => "Adjon meg egy helyes e-mail címet!", "class" => "invalid");
                $error++;
            }

            //Telefonszám ellenőrzése
            if (isset($_POST["phone"]) && !empty($_POST["phone"])) {
                $regex = '/^(\+36|06)(20|30|31|50|70)\d{7}$/';
                if (preg_match($regex, $_POST["phone"])) {
                    $status[] = array("id" => "phone", "response" => "", "class" => "valid");
                } else {
                    $status[] = array("id" => "phone", "response" => "", "class" => "valid");
                    //$status[] = array("id" => "phone", "response" => "Helytelen telefonszám!", "class" => "invalid");
                    //$error++;
                }
            } else {
                $status[] = array("id" => "phone", "response" => "Adja meg a telefonszámát!", "class" => "invalid");
                $error++;
            }

            //Irányítószám ellenőrzése
            if (isset($_POST["zip-code"]) && !empty($_POST["zip-code"])) {
                if (isset($_POST["zip-code"]) && !empty($_POST["zip-code"])) {
                    $status[] = array("id" => "zip-code", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "zip-code", "response" => "Adja meg a irányítószámát!", "class" => "invalid");
                    $error++;
                }
            }else {
                $status[] = array("id" => "zip-code", "response" => "Adja meg a irányítószámát!", "class" => "invalid");
                $error++;
            }

            //Város ellenőrzése
            if (isset($_POST["city"]) && !empty($_POST["city"])) {
                if (isset($_POST["city"]) && !empty($_POST["city"])) {
                    $status[] = array("id" => "city", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "city", "response" => "Adja meg a Városa nevét!", "class" => "invalid");
                    $error++;
                }
            }else {
                $status[] = array("id" => "city", "response" => "Adja meg a Városa nevét!", "class" => "invalid");
                $error++;
            }

            //Lakcím ellenőrzése
            if (isset($_POST["address"]) && !empty($_POST["address"])) {
                if (isset($_POST["address"]) && !empty($_POST["address"])) {
                    $status[] = array("id" => "address", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "address", "response" => "Adja meg a pontos címét!", "class" => "invalid");
                    $error++;
                }
            }else {
                $status[] = array("id" => "address", "response" => "Adja meg a pontos címét!", "class" => "invalid");
                $error++;
            }

            //Szállítási kérdés ellenőrzése
            if (isset($_POST["transportation"]) && !empty($_POST["transportation"])) {
                $status[] = array("id" => "transport", "response" => "Helyes!", "class" => "valid");
                $status[] = array("id" => "transport1", "response" => "Helyes!", "class" => "valid");
                $status[] = array("id" => "transport2", "response" => "", "class" => "valid");
            } else {
                $status[] = array("id" => "transport", "response" => "Válasszon a lehetőségek közül!", "class" => "invalid");
                $status[] = array("id" => "transport1", "response" => "Válasszon a lehetőségek közül!", "class" => "invalid");
                $status[] = array("id" => "transport2", "response" => "", "class" => "invalid");
                $error++;
            }

            //OTP egészségpénztár kérdés ellenőrzése
            if (isset($_POST["otp-healthfund"]) && !empty($_POST["otp-healthfund"])) {
                $status[] = array("id" => "otp-healthfund", "response" => "Helyes!", "class" => "valid");
                $status[] = array("id" => "otp-healthfund1", "response" => "Helyes!", "class" => "valid");
                $status[] = array("id" => "otp-healthfund2", "response" => "", "class" => "valid");
            } else {
                $status[] = array("id" => "otp-healthfund", "response" => "Válasszon a lehetőségek közül!", "class" => "invalid");
                $status[] = array("id" => "otp-healthfund1", "response" => "Válasszon a lehetőségek közül!", "class" => "invalid");
                $status[] = array("id" => "otp-healthfund2", "response" => "", "class" => "invalid");
                $error++;
            }

            //ÁSZF ellenőrzése
            if (isset($_POST["aszf"]) && $_POST["aszf"] == "on") {
                $status[] = array("id" => "aszf", "response" => "", "class" => "form-color");
            } else {
                $status[] = array("id" => "aszf", "response" => "A fogalaláshoz el kell fogadja az ASZF-et!", "class" => "invalid");
                $error++;
            }

            if($error==0){
                $url = $this->registerGHCPatient($_POST);
            }

            
            die(json_encode(array("status" => $status, "url"=> $url)));
        }

        if(isset($_POST["aldi_fifi_registration"])){

            $error = 0;
            $url = "";
            $status = [];
            $minimalRequirement = false;
            
            /*if($_POST["taj"]!="" && $registered=sql_query("SELECT * FROM felhasznalok WHERE taj=? AND cegid=?",array($_POST["taj"],904))->fetch(PDO::FETCH_ASSOC)){
                $error = "Ön már regisztrálva van a Suzuki GHC szűrésre.<br><br> Kérem, jelentkezzen be a \"Bejelentkezés\" menüpont alatt a TAJ számával.<br>";
                $error.= "<a href=\"https://{$_SERVER["HTTP_HOST"]}/?page=login\">Bejelentkezésez kattintson ide!</a>";
                die(json_encode(array("error"=>$error,"status" => $status, "url"=> $url)));
            }*/

            //TAJ ellenőrzése
            if (isset($_POST["taj"]) && !empty($_POST["taj"])) {
                if ($this->utils->tajCheck($_POST["taj"]) || true) {
                    $status[] = array("id" => "taj", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "taj", "response" => "Helytelen TAJ szám!", "class" => "invalid");
                    $error++;
                }
            } else {
                $status[] = array("id" => "taj", "response" => "Adja meg a TAJ számát!", "class" => "invalid");
                $error++;
            }


            //Név ellenőrzése
            if (isset($_POST["name"]) && !empty($_POST["name"])) {
                $status[] = array("id" => "name", "response" => "Helyes!", "class" => "valid");
            } else {
                $status[] = array("id" => "name", "response" => "Adja meg a nevét!", "class" => "invalid");
                $error++;
            }

            //Születési dátum ellenőrzése
                if (isset($_POST["birthdate"]) && !empty($_POST["birthdate"])) {
                    $status[] = array("id" => "birthdate", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "birthdate", "response" => "Adja meg a születési dátumát!", "class" => "invalid");
                    $error++;
                }
            
            

            //Email ellenőrzése
            if (isset($_POST["email"]) && !empty($_POST["email"])) {
                if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
                    $status[] = array("id" => "email", "response" => "Adjon meg egy helyes e-mail címet!", "class" => "invalid");
                    $error++;
                } else {
                    $status[] = array("id" => "email", "response" => "Helyes e-mail cím!", "class" => "valid");
                }
            } else {
                $status[] = array("id" => "email", "response" => "Adjon meg egy helyes e-mail címet!", "class" => "invalid");
                $error++;
            }

            //Telefonszám ellenőrzése
            if (isset($_POST["phone"]) && !empty($_POST["phone"])) {
                $regex = '/^(\+36|06)(20|30|31|50|70)\d{7}$/';
                if (preg_match($regex, $_POST["phone"])) {
                    $status[] = array("id" => "phone", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "phone", "response" => "Helytelen telefonszám!", "class" => "invalid");
                    $error++;
                }
            } else {
                $status[] = array("id" => "phone", "response" => "Adja meg a telefonszámát!", "class" => "invalid");
                $error++;
            }

            //Irányítószám ellenőrzése
            if (isset($_POST["zip-code"]) && !empty($_POST["zip-code"])) {
                if (isset($_POST["zip-code"]) && !empty($_POST["zip-code"])) {
                    $status[] = array("id" => "zip-code", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "zip-code", "response" => "Adja meg a irányítószámát!", "class" => "invalid");
                    $error++;
                }
            }else {
                $status[] = array("id" => "zip-code", "response" => "Adja meg a irányítószámát!", "class" => "invalid");
                $error++;
            }

            //Város ellenőrzése
            if (isset($_POST["city"]) && !empty($_POST["city"])) {
                if (isset($_POST["city"]) && !empty($_POST["city"])) {
                    $status[] = array("id" => "city", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "city", "response" => "Adja meg a Városa nevét!", "class" => "invalid");
                    $error++;
                }
            }else {
                $status[] = array("id" => "city", "response" => "Adja meg a Városa nevét!", "class" => "invalid");
                $error++;
            }

            //Lakcím ellenőrzése
            if (isset($_POST["address"]) && !empty($_POST["address"])) {
                if (isset($_POST["address"]) && !empty($_POST["address"])) {
                    $status[] = array("id" => "address", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "address", "response" => "Adja meg a pontos címét!", "class" => "invalid");
                    $error++;
                }
            }else {
                $status[] = array("id" => "address", "response" => "Adja meg a pontos címét!", "class" => "invalid");
                $error++;
            }

            //ÁSZF ellenőrzése
            if (isset($_POST["aszf"]) && $_POST["aszf"] == "on") {
                $status[] = array("id" => "aszf", "response" => "", "class" => "form-color");
            } else {
                $status[] = array("id" => "aszf", "response" => "A fogalaláshoz el kell fogadja az ASZF-et!", "class" => "invalid");
                $error++;
            }

            if($error==0){
                $url = $this->registerFiFiPatient($_POST);
                //$url = $this->registerGHCPatient($_POST);
            }

            
            die(json_encode(array("status" => $status, "url"=> $url)));
        }

        if(isset($_POST["astotec_registration"])){

            $error = 0;
            $url = "";
            $status = [];
            $minimalRequirement = false;
            
            if($_POST["taj"]!="" && $registered=sql_query("SELECT * FROM felhasznalok WHERE taj=? AND cegid=?",array($_POST["taj"],664))->fetch(PDO::FETCH_ASSOC)){
                $error = "Ön már regisztrálva van az Astotec Automotive szűrésre.<br><br> Kérem, jelentkezzen be a \"Bejelentkezés\" menüpont alatt a TAJ számával.<br>";
                $error.= "<a href=\"https://{$_SERVER["HTTP_HOST"]}/?page=login\">Bejelentkezésez kattintson ide!</a>";
                die(json_encode(array("error"=>$error,"status" => $status, "url"=> $url)));
            }

            //TAJ ellenőrzése
            if (isset($_POST["taj"]) && !empty($_POST["taj"])) {
                if ($this->utils->tajCheck($_POST["taj"]) || true) {
                    $status[] = array("id" => "taj", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "taj", "response" => "Helytelen TAJ szám!", "class" => "invalid");
                    $error++;
                }
            } else {
                $status[] = array("id" => "taj", "response" => "Adja meg a TAJ számát!", "class" => "invalid");
                $error++;
            }


            //Név ellenőrzése
            if (isset($_POST["name"]) && !empty($_POST["name"])) {
                $status[] = array("id" => "name", "response" => "Helyes!", "class" => "valid");
            } else {
                $status[] = array("id" => "name", "response" => "Adja meg a nevét!", "class" => "invalid");
                $error++;
            }

            //Születési dátum ellenőrzése
            /*if (isset($_POST["birthdate"]) && !empty($_POST["birthdate"])) {
                $status[] = array("id" => "birthdate", "response" => "Helyes!", "class" => "valid");
            } else {
                $status[] = array("id" => "birthdate", "response" => "Adja meg a születési dátumát!", "class" => "invalid");
                $error++;
            }*/
            
            

            //Email ellenőrzése
            if (isset($_POST["email"]) && !empty($_POST["email"])) {
                if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
                    $status[] = array("id" => "email", "response" => "Adjon meg egy helyes e-mail címet!", "class" => "invalid");
                    $error++;
                } else {
                    $status[] = array("id" => "email", "response" => "Helyes e-mail cím!", "class" => "valid");
                }
            } else {
                $status[] = array("id" => "email", "response" => "Adjon meg egy helyes e-mail címet!", "class" => "invalid");
                $error++;
            }

            //Telefonszám ellenőrzése
            /*if (isset($_POST["phone"]) && !empty($_POST["phone"])) {
                $regex = '/^(\+36|06)(20|30|31|50|70)\d{7}$/';
                if (preg_match($regex, $_POST["phone"])) {
                    $status[] = array("id" => "phone", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "phone", "response" => "Helytelen telefonszám!", "class" => "invalid");
                    $error++;
                }
            } else {
                $status[] = array("id" => "phone", "response" => "Adja meg a telefonszámát!", "class" => "invalid");
                $error++;
            }*/

            //Irányítószám ellenőrzése
            /*if (isset($_POST["zip-code"]) && !empty($_POST["zip-code"])) {
                if (isset($_POST["zip-code"]) && !empty($_POST["zip-code"])) {
                    $status[] = array("id" => "zip-code", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "zip-code", "response" => "Adja meg a irányítószámát!", "class" => "invalid");
                    $error++;
                }
            }else {
                $status[] = array("id" => "zip-code", "response" => "Adja meg a irányítószámát!", "class" => "invalid");
                $error++;
            }*/

            //Város ellenőrzése
            /*if (isset($_POST["city"]) && !empty($_POST["city"])) {
                if (isset($_POST["city"]) && !empty($_POST["city"])) {
                    $status[] = array("id" => "city", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "city", "response" => "Adja meg a Városa nevét!", "class" => "invalid");
                    $error++;
                }
            }else {
                $status[] = array("id" => "city", "response" => "Adja meg a Városa nevét!", "class" => "invalid");
                $error++;
            }*/

            //Lakcím ellenőrzése
            /*if (isset($_POST["address"]) && !empty($_POST["address"])) {
                if (isset($_POST["address"]) && !empty($_POST["address"])) {
                    $status[] = array("id" => "address", "response" => "Helyes!", "class" => "valid");
                } else {
                    $status[] = array("id" => "address", "response" => "Adja meg a pontos címét!", "class" => "invalid");
                    $error++;
                }
            }else {
                $status[] = array("id" => "address", "response" => "Adja meg a pontos címét!", "class" => "invalid");
                $error++;
            }*/

            //ÁSZF ellenőrzése
            if (isset($_POST["aszf"]) && $_POST["aszf"] == "on") {
                $status[] = array("id" => "aszf", "response" => "", "class" => "form-color");
            } else {
                $status[] = array("id" => "aszf", "response" => "A fogalaláshoz el kell fogadja az ASZF-et!", "class" => "invalid");
                $error++;
            }

            if($error==0){
                $url = $this->registerASTPatient($_POST);
                //$url = $this->registerGHCPatient($_POST);
            }

            
            die(json_encode(array("status" => $status, "url"=> $url)));
        }
    }

    public function showPage()
    {
        $webText = $this->lang->webText;

        if (!isset($_POST["email"])) {
            $_POST["email"] = $_POST["nev"] = $_POST["telefon"] = $_POST["szuldatum"] = $_POST["taj"] = $_POST["irsz"] = $_POST["varos"] = $_POST["utca"] = $_POST["munkaltato"] = $_POST["munkakor"] = $_POST["torzsszam"] = $_POST["jelszo"] = $_POST["jelszo2"] = $_POST["captcha"] = "";
        }
        if (!isset($_POST["neme"])) $_POST["neme"] = "";

        
        $fejlec = $this->displayFejlec();
        $error  = $this->showFormErrors();
        $html   = "";
        

        if (CompanyService::isSuzukiGHC()) {

            $fejlec = $this->displayFejlec("Suzuki GHC szűrés - Regisztráció", true);
            $error  = $this->showFormErrors();

            //Suzuki
            $html = $this->suzukiRegTemplate();
            
            echo $fejlec;
            echo $error;
            echo $html;
            return;
        }

        if(CompanyService::isAstostecCompany()){

            $fejlec = $this->displayFejlec("Astotec Automotive HU - Regisztráció", true);
            $error  = $this->showFormErrors();
            $html = $this->AstotectRegTemplate();
            echo $fejlec;
            echo $error;
            echo $html;
            return;
        }

        if(CompanyService::isFiFi()){

            $fejlec = $this->displayFejlec("ALDI FiFi szűrés - Regisztráció", true);
            $error  = $this->showFormErrors();

            //Fifi
            $html = $this->fifiRegTemplate();

            //$html.= $this->inc_kerdoiv("fifiregisztracio");

            echo $fejlec;
            echo $error;
            echo $html;
            return;
        }

        echo $fejlec;
        echo $error;
        echo $html;

        echo $this->showPageDescription($this->lang->getText("page.reg.description", "Regisztráljon, hogy kényelmesebben foglalhasson időpontot, valamint kiegészítő szolgáltatásokat érhessen el. A regisztráció után nem kell minden foglalásnál kitöltenie az adatait, megtekintheti előző foglalásait, megnézheti a leleteit, és az egyéb vizsgálatokkal kapcsolatos dokumentumokat.<br/>Adja meg az adatait az alábbi form kitöltésével."));

        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<input type='hidden' name='regisztracio' value='1'/>";
        echo "<table>";
        echo "<tr><td width='100'>{$webText["email"]}: *</td><td><input class='inputbox' autocomplete='off' style='width:250px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
        echo "<tr><td>{$webText["jelszo"]}: *</td><td><input style='display:none;' type='text' autocomplete='off' name='dummyname' value=''><input style='display:none;' type='password' autocomplete='off' name='dummypass' value=''> <input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo' value='{$_POST["jelszo"]}'></td></tr>";
        echo "<tr><td>{$webText["jelszoujra"]}: *</td><td><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo2' value='{$_POST["jelszo2"]}'></td></tr>";
        echo "<tr><td colspan='2'><div style='border-top:1px solid #ccc;padding-top:10px;margin-top:10px;'></div></td></tr>";
        echo "<tr><td>{$webText["tajszam"]} *:</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' value='{$_POST["taj"]}'></td></tr>";
        echo "<tr><td>{$webText["nev"]}: *</td><td><input class='inputbox' style='width:270px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
        echo "<tr><td>{$webText["mobil"]}: *</td><td><input class='inputbox' style='width:270px;' type='text' name='telefon' value='{$_POST["telefon"]}' placeholder='{$webText["mobilformat"]}' ></td></tr>";
        echo "<tr><td></td><td style='color:#888;'>{$webText["mobiltip"]}</td></tr>";
        if (!CompanyService::isHungarocontrol()) {
            echo "<tr><td>{$webText["szuletesidatum"]}:</td><td>" . $this->utils->datumSelector($_POST["szuldatum"], "szuldatum") . " {$_POST["szuldatum"]}</td></tr>";
            echo "<tr><td>{$webText["neme"]}:</td><td><input type='radio' name='neme' value='1' " . ($_POST["neme"] == 1 ? "checked" : "") . "/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' " . ($_POST["neme"] == 2 ? "checked" : "") . "/> {$webText["no"]}</td></tr>";
            echo "<tr><td>{$webText["irsz"]}:</td><td><input class='inputbox' style='width:60px;' maxlength='4' type='text' name='irsz' value='{$_POST["irsz"]}'></td></tr>";
            echo "<tr><td>{$webText["varos"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='varos' value='{$_POST["varos"]}'></td></tr>";
            echo "<tr><td>{$webText["utca"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='utca' value='{$_POST["utca"]}'></td></tr>";
        }
        echo "<tr><td></td><td><div style='margin-top:5px;' class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";

        if (CompanyService::isHungarocontrol()) {
            echo "<tr><td><td><div style='margin-top:10px;'><input type='checkbox' name='aszf' value='1' " . (isset($_POST["aszf"]) ? "checked" : "") . "/> {$webText["aszfelhc"]}</div></td></tr>";
        } else {
            echo "<tr><td><td><div style='margin-top:10px;'><input type='checkbox' name='aszf' value='1' " . (isset($_POST["aszf"]) ? "checked" : "") . "/> {$webText["aszfelf"]}</div></td></tr>";
        }


        echo "<tr><td></td><td><br/><a href='#' class='newbutton' onclick='document.iform.submit();return false;'>{$webText["regisztracio"]}</a></td></tr>";
        echo "</table>";
        echo "</form>";
    }

    public function registerGHCPatient($data)
    {
        if($data["transportation"]=="transport-required"){
            $data["transportation"] = 1;
        }else{
            $data["transportation"] = 0;
        } 

        if($data["otp-healthfund"]=="yes"){
            $data["otp-healthfund"] = 1;
        }else{
            $data["otp-healthfund"] = 0;
        } 

        $pass=md5(date("Y-m-d H:is").$data["name"]."ghc");
        
        $q = sql_query("INSERT INTO felhasznalok 
        SET cegid=?,nev=?,szuldatum=?,email=?,telefon=?,taj=?,regtime=?,irsz=?,varos=?,utca=?,validated=?,szallitas=?,otp_penztar=?,pass=?,torzsszam=?
        ", array(
            904, $data["name"], $data["birthdate"], $data["email"], $data["phone"], $data["taj"], date("Y-m-d H:i:s"), $data["zip-code"],
            $data["city"], $data["address"], 1, $data["transportation"], $data["otp-healthfund"],$pass,$data["torzsszam"]
        ));

        $id = sql_insert_id();

        $notificationService = New NotificationService();


        $url = "https://{$_SERVER["HTTP_HOST"]}/?page=registrationsuccessful&pass={$pass}&id={$id}";

        return $url;
    }

    public function registerASTPatient($data)
    {
        $pass=md5(date("Y-m-d H:is").$data["name"]."astotec");
        
        $q = sql_query("INSERT INTO felhasznalok 
        SET cegid=?,nev=?,email=?,taj=?,regtime=?,validated=?,pass=?
        ", array(
            664, $data["name"], $data["email"], $data["taj"], date("Y-m-d H:i:s"), 1, $pass
        ));

        $id = sql_insert_id();

        //$notificationService = New NotificationService();


        $url = "https://{$_SERVER["HTTP_HOST"]}/?page=registrationsuccessful&pass={$pass}&id={$id}";

        return $url;
    }

    public function registerFiFiPatient($data)
    {
        $pass=md5(date("Y-m-d H:is").$data["name"]."fifi");
        
        $q = sql_query("INSERT INTO felhasznalok 
        SET cegid=?,nev=?,szuldatum=?,email=?,telefon=?,taj=?,regtime=?,irsz=?,varos=?,utca=?,validated=?,pass=?
        ", array(
            904, $data["name"], $data["birthdate"], $data["email"], $data["phone"], $data["taj"], date("Y-m-d H:i:s"), $data["zip-code"],
            $data["city"], $data["address"], 1,$pass
        ));

        $id = sql_insert_id();

        //Kérdőív válaszok letárolása
        $questionaries = sql_query("SELECT * FROM kerdoiv_kerdesek WHERE kerdoivid=?",array("fifiregisztracio"))->fetchAll(PDO::FETCH_ASSOC);
        foreach($questionaries as $questionIndex=>$questionData){
            if(isset($_POST["question-{$questionData["id"]}"])){
                $answerData = sql_query("SELECT * FROM kerdoiv_valaszok WHERE kerdesid=? AND (valasz_ertek=? || valasz_ertek IS NULL )",
                array($questionData["id"],$_POST["question-{$questionData["id"]}"]))->fetch(PDO::FETCH_ASSOC);

                //A kifejtős mező lekezelése:
                if(!isset($_POST["question-{$questionData["id"]}-{$answerData["id"]}-text"])){
                    $_POST["question-{$questionData["id"]}-{$answerData["id"]}-text"] = null;
                }

                //Ha beírós a válasz:
                if($answerData["valasz_ertek"]==null){
                    $answerData["valasz_szoveg"] = $_POST["question-{$questionData["id"]}"]." ".$answerData["valasz_szoveg"];
                }

                //Ha sub válasz is van:
                if($answerData["sub_valasz_input"]!=null){
                    $answerData["valasz_szoveg"] = str_replace("#sub_valasz_input#",$_POST[$answerData["sub_valasz_id"]],$answerData["valasz_szoveg"]);
                }

                if($answerData["valasz_tipus"]=="textarea"){
                    $answerData["valasz_szoveg"] = $_POST["question-{$questionData["id"]}"];
                }


                //Adatok rögzítése az adatbázisba
                sql_query("INSERT INTO kerdoiv_ugyfel_valaszok SET fid=?, kerdoivid=?, kerdesid=?, kerdes_szoveg=?, valaszid=?, valasz_szoveg=?, kifejtes_szoveg=?",
                array($id,"fifiregisztracio",$questionData["id"],$questionData["kerdes"],$answerData["id"],$answerData["valasz_szoveg"], $_POST["question-{$questionData["id"]}-{$answerData["id"]}-text"]));
            }
        }

        $notificationService = New NotificationService();


        $url = "https://{$_SERVER["HTTP_HOST"]}/?page=registrationsuccessful&pass={$pass}&id={$id}";

        return $url;
    }

    public function AstotectRegTemplate(){
        $maxBirthDate = date("Y-m-d",strtotime("Now - 18 years"));
        $html = "";
        $html .= "<div class=\"container og-bootstrap\" id='og-bootstrap'>";
        $html .= "   <form id='astotec-registration-form' method='POST' enctype='multipart/form-data'>";
        /*$html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html .= "               <label for=\"torzsszam\" class=\"form-label\">Törzsszám:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"torzsszam\" name=\"torzsszam\" value=\"\">";
        $html .= "               <div id=\"validation-torzsszam\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";*/
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html .= "               <label for=\"name\" class=\"form-label\">Teljes név:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"name\" name=\"name\" value=\"\">";
        $html .= "               <div id=\"validation-name\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html .= "               <label for=\"taj\" class=\"form-label\">TAJ szám:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"taj\" name=\"taj\" value=\"\">";
        $html .= "               <div id=\"validation-taj\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";
        
        /*$html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html .= "               <label for=\"birthdate\" class=\"form-label\">Születési dátum:</label>";
        $html .= "               <input type=\"date\" class=\"form-control\" id=\"birthdate\" max=\"{$maxBirthDate}\" name=\"birthdate\" value=\"\">";
        $html .= "               <div id=\"validation-birthdate\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";*/
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html .= "               <label for=\"email\" class=\"form-label\">E-mail:</label>";
        $html .= "               <div class=\"input-group\">";
        $html .= "               <span class=\"input-group-text\" id=\"emailPrepend\"><i class=\"fa-solid fa-at\"></i></span>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"email\" name=\"email\" aria-describedby=\"\">";
        $html .= "               <div id=\"validation-email\" class=\"valid-feedback\"></div>";
        $html .= "               </div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";
        /*$html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html .= "               <label for=\"phone\" class=\"form-label\">Telefonszám:</label>";
        $html .= "               <div class=\"input-group\">";
        $html .= "               <span class=\"input-group-text\" id=\"phonePrepend\"><i class=\"fa-solid fa-phone\"></i></span>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"phone\" name=\"phone\" value=\"+36\" aria-describedby=\"\">";
        $html .= "               <div id=\"validation-phone\" class=\"valid-feedback\"></div>";
        $html .= "               </div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html .= "               <label for=\"zip-code\" class=\"form-label\">Irányítószám:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"zip-code\" name=\"zip-code\">";
        $html .= "               <div id=\"validation-zip-code\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html .= "               <label for=\"city\" class=\"form-label\">Város:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"city\" name=\"city\">";
        $html .= "               <div id=\"validation-city\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html .= "               <label for=\"address\" class=\"form-label\">Utca, házszám:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"address\" name=\"address\">";
        $html .= "               <div id=\"validation-address\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";*/

        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html.= "               <div class=\"d-block d-xl-none\">";
        $html .= "                  <input class=\"form-check-input checkbox\" type=\"checkbox\" id=\"aszf\" name=\"aszf\">";
        $html .= "                  <label class=\"form-label checkbox\" for=\"aszf\">";
        $html .= "                      Az <a target=\"_blank\" href=\"https://hungariamed.hu/images/adatkezeles.pdf\">Adatvédelmi tájékoztatót</a> elolvastam, a fenti adatkezeléshez hozzájárulok.";
        $html .= "                  </label>";
        $html.= "               </div>";
        $html .= "              <div class=\"d-none d-xl-block\">";
        $html .= "                  <input class=\"form-check-input checkbox\" type=\"checkbox\" id=\"aszf\" name=\"aszf\">";
        $html .= "                  <label class=\"form-label checkbox\" for=\"aszf\">";
        $html .= "                      Az <a target=\"_blank\" data-bs-toggle=\"collapse\" href=\"#gdpr-collapse\" aria-expanded=\"false\" aria-controls=\"gdpr-collapse\">Adatvédelmi tájékoztatót</a> elolvastam, a fenti adatkezeléshez hozzájárulok.";
        $html .= "                  </label>";
        $html .= "              </div>";
        $html .= "               <div id=\"validation-aszf\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row d-none d-xl-block\">";
        $html .= "           <div class=\"col mb-3 text-center\">";
        $html .= "                  <div class=\"collapse multi-collapse\" id=\"gdpr-collapse\">";
        $html .= "                      <div class=\"ratio ratio-1x1\">";
        $html .= "                          <iframe src=\"https://hungariamed.hu/images/adatkezeles.pdf\" title=\"GDPR - Adatvédelmi tájékoztató\" allowfullscreen></iframe>";
        $html .= "                      </div>";
        $html .= "                  </div>";
        $html .= "           </div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "           <div class=\"col mb-3\">";
        $html .= "               <div class=\"d-grid gap-2\">";
        $html .= "                   <button class=\"btn btn-hungariamed\" id=\"astotec-registration\" type=\"button\">Regisztráció</button>";
        $html .= "               </div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md\"></div>";
        $html .= "       </div>";
        $html .= "   </form>";
        $html .= "</div>";

        return $html;
    }

    public function suzukiRegTemplate(){

        $maxBirthDate = date("Y-m-d",strtotime("Now - 18 years"));

            $html = "";
            $html .= "<div class=\"container og-bootstrap\" id='og-bootstrap'>";
            $html .= "   <form id='suzuki-ghc-registration-form' method='POST' enctype='multipart/form-data'>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"torzsszam\" class=\"form-label\">Törzsszám:</label>";
            $html .= "               <input type=\"text\" class=\"form-control\" id=\"torzsszam\" name=\"torzsszam\" value=\"\">";
            $html .= "               <div id=\"validation-torzsszam\" class=\"valid-feedback\"></div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"taj\" class=\"form-label\">TAJ szám:</label>";
            $html .= "               <input type=\"text\" class=\"form-control\" id=\"taj\" name=\"taj\" value=\"\">";
            $html .= "               <div id=\"validation-taj\" class=\"valid-feedback\"></div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"name\" class=\"form-label\">Teljes név:</label>";
            $html .= "               <input type=\"text\" class=\"form-control\" id=\"name\" name=\"name\" value=\"\">";
            $html .= "               <div id=\"validation-name\" class=\"valid-feedback\"></div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"birthdate\" class=\"form-label\">Születési dátum:</label>";
            $html .= "               <input type=\"date\" class=\"form-control\" id=\"birthdate\" max=\"{$maxBirthDate}\" name=\"birthdate\" value=\"\">";
            $html .= "               <div id=\"validation-birthdate\" class=\"valid-feedback\"></div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"email\" class=\"form-label\">E-mail:</label>";
            $html .= "               <div class=\"input-group\">";
            $html .= "               <span class=\"input-group-text\" id=\"emailPrepend\"><i class=\"fa-solid fa-at\"></i></span>";
            $html .= "               <input type=\"text\" class=\"form-control\" id=\"email\" name=\"email\" aria-describedby=\"\">";
            $html .= "               <div id=\"validation-email\" class=\"valid-feedback\"></div>";
            $html .= "               </div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"phone\" class=\"form-label\">Telefonszám:</label>";
            $html .= "               <div class=\"input-group\">";
            $html .= "               <span class=\"input-group-text\" id=\"phonePrepend\"><i class=\"fa-solid fa-phone\"></i></span>";
            $html .= "               <input type=\"text\" class=\"form-control\" id=\"phone\" name=\"phone\" value=\"+36\" aria-describedby=\"\">";
            $html .= "               <div id=\"validation-phone\" class=\"valid-feedback\"></div>";
            $html .= "               </div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"zip-code\" class=\"form-label\">Irányítószám:</label>";
            $html .= "               <input type=\"text\" class=\"form-control\" id=\"zip-code\" name=\"zip-code\">";
            $html .= "               <div id=\"validation-zip-code\" class=\"valid-feedback\"></div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"city\" class=\"form-label\">Város:</label>";
            $html .= "               <input type=\"text\" class=\"form-control\" id=\"city\" name=\"city\">";
            $html .= "               <div id=\"validation-city\" class=\"valid-feedback\"></div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"address\" class=\"form-label\">Utca, házszám:</label>";
            $html .= "               <input type=\"text\" class=\"form-control\" id=\"address\" name=\"address\">";
            $html .= "               <div id=\"validation-address\" class=\"valid-feedback\"></div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"transportation\" class=\"form-label fw-bold\">Szeretne szállítást kérni?</label>";
            $html .= "               <div class=\"form-check\">";
            $html .= "                   <input class=\"form-check-input\" type=\"radio\" name=\"transportation\" id=\"transport1\" value=\"transport-required\">";
            $html .= "                   <label class=\"form-check-label\" for=\"transport1\">";
            $html .= "                       Igen, kérek szállítást.";
            $html .= "                   </label>";
            $html .= "               </div>";
            $html .= "               <div class=\"form-check\">";
            $html .= "                   <input class=\"form-check-input\" type=\"radio\" name=\"transportation\" id=\"transport2\" value=\"no-transport\">";
            $html .= "                   <label class=\"form-check-label\" for=\"transport2\">";
            $html .= "                       Nem kérek szállítást.";
            $html .= "                   </label>";
            $html .= "                   <div id=\"validation-transport\" class=\"valid-feedback\"></div>";
            $html .= "               </div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <label for=\"otp-healthfund\" class=\"form-label fw-bold\">Rendelkezik OTP egészségpénztári tagsággal?</label>";
            $html .= "               <div class=\"form-check\">";
            $html .= "                   <input class=\"form-check-input\" type=\"radio\" name=\"otp-healthfund\" id=\"otp-healthfund1\" value=\"yes\">";
            $html .= "                   <label class=\"form-check-label\" for=\"otp-healthfund1\">";
            $html .= "                       Igen, rendelkezem.";
            $html .= "                   </label>";
            $html .= "               </div>";
            $html .= "               <div class=\"form-check\">";
            $html .= "                   <input class=\"form-check-input\" type=\"radio\" name=\"otp-healthfund\" id=\"otp-healthfund2\" value=\"no\">";
            $html .= "                   <label class=\"form-check-label\" for=\"otp-healthfund2\">";
            $html .= "                       Nem rendelkezem.";
            $html .= "                   </label>";
            $html .= "                   <div id=\"validation-otp-healthfund\" class=\"valid-feedback\"></div>";
            $html .= "               </div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html.= "               <div class=\"d-block d-xl-none\">";
            $html .= "                  <input class=\"form-check-input checkbox\" type=\"checkbox\" id=\"aszf\" name=\"aszf\">";
            $html .= "                  <label class=\"form-label checkbox\" for=\"aszf\">";
            $html .= "                      Az <a target=\"_blank\" href=\"https://{$_SERVER["HTTP_HOST"]}/images/HungáriaMed_Adatvédelmi_tájékoztató_GHC.pdf\">Adatvédelmi tájékoztatót</a> elolvastam, a fenti adatkezeléshez hozzájárulok.";
            $html .= "                  </label>";
            $html.= "               </div>";
            $html .= "              <div class=\"d-none d-xl-block\">";
            $html .= "                  <input class=\"form-check-input checkbox\" type=\"checkbox\" id=\"aszf\" name=\"aszf\">";
            $html .= "                  <label class=\"form-label checkbox\" for=\"aszf\">";
            $html .= "                      Az <a target=\"_blank\" data-bs-toggle=\"collapse\" href=\"#gdpr-collapse\" aria-expanded=\"false\" aria-controls=\"gdpr-collapse\">Adatvédelmi tájékoztatót</a> elolvastam, a fenti adatkezeléshez hozzájárulok.";
            $html .= "                  </label>";
            $html .= "              </div>";
            $html .= "               <div id=\"validation-aszf\" class=\"valid-feedback\"></div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row d-none d-xl-block\">";
            $html .= "           <div class=\"col mb-3 text-center\">";
            $html .= "                  <div class=\"collapse multi-collapse\" id=\"gdpr-collapse\">";
            $html .= "                      <div class=\"ratio ratio-1x1\">";
            $html .= "                          <iframe src=\"https://{$_SERVER["HTTP_HOST"]}/images/HungáriaMed_Adatvédelmi_tájékoztató_GHC.pdf\" title=\"GDPR - Adatvédelmi tájékoztató\" allowfullscreen></iframe>";
            $html .= "                      </div>";
            $html .= "                  </div>";
            $html .= "           </div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <div class=\"d-grid gap-2\">";
            $html .= "                   <button class=\"btn btn-hungariamed\" id=\"suzuki-registration\" type=\"button\">Regisztráció</button>";
            $html .= "               </div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "   </form>";
            $html .= "</div>";

            return $html;
    }

    public function fifiRegTemplate(){

        $maxBirthDate = date("Y-m-d",strtotime("Now - 18 years"));

        $html = "";
        $html .= "<div class=\"container og-bootstrap\" id='og-bootstrap'>";
        $html .= "   <form id='aldi-fifi-registration-form' method='POST' enctype='multipart/form-data'>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html .= "               <label for=\"taj\" class=\"form-label\">TAJ szám:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"taj\" name=\"taj\" value=\"\">";
        $html .= "               <div id=\"validation-taj\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html .= "               <label for=\"name\" class=\"form-label\">Teljes név:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"name\" name=\"name\" value=\"\">";
        $html .= "               <div id=\"validation-name\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html .= "               <label for=\"birthdate\" class=\"form-label\">Születési dátum:</label>";
        $html .= "               <input type=\"date\" class=\"form-control\" id=\"birthdate\" max=\"{$maxBirthDate}\" name=\"birthdate\" value=\"\">";
        $html .= "               <div id=\"validation-birthdate\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html .= "               <label for=\"email\" class=\"form-label\">E-mail:</label>";
        $html .= "               <div class=\"input-group\">";
        $html .= "               <span class=\"input-group-text\" id=\"emailPrepend\"><i class=\"fa-solid fa-at\"></i></span>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"email\" name=\"email\" aria-describedby=\"\">";
        $html .= "               <div id=\"validation-email\" class=\"valid-feedback\"></div>";
        $html .= "               </div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html .= "               <label for=\"phone\" class=\"form-label\">Telefonszám:</label>";
        $html .= "               <div class=\"input-group\">";
        $html .= "               <span class=\"input-group-text\" id=\"phonePrepend\"><i class=\"fa-solid fa-phone\"></i></span>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"phone\" name=\"phone\" value=\"+36\" aria-describedby=\"\">";
        $html .= "               <div id=\"validation-phone\" class=\"valid-feedback\"></div>";
        $html .= "               </div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html .= "               <label for=\"zip-code\" class=\"form-label\">Irányítószám:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"zip-code\" name=\"zip-code\">";
        $html .= "               <div id=\"validation-zip-code\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html .= "               <label for=\"city\" class=\"form-label\">Város:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"city\" name=\"city\">";
        $html .= "               <div id=\"validation-city\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html .= "               <label for=\"address\" class=\"form-label\">Utca, házszám:</label>";
        $html .= "               <input type=\"text\" class=\"form-control\" id=\"address\" name=\"address\">";
        $html .= "               <div id=\"validation-address\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";

        $uzletek = sql_query("SELECT * FROM fifi_helyszinek")->fetchAll(PDO::FETCH_ASSOC);
        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html .= "               <label for=\"uzlet\" class=\"form-label\">Melyik üzleten szeretne részt venni a szűrésen?</label>";
        $html .= "              <select class=\"form-select\" name=\"uzlet\" id=\"uzlet\">";
        $html .= "                  <option selected>Válassz Üzletet!</option>";
            foreach($uzletek as $uzlet){
                $html.= "<option value=\"{$uzlet["uzletszam"]}\">{$uzlet["uzletszam"]}. {$uzlet["varos"]} ({$uzlet["irsz"]}), {$uzlet["cim"]}</option>";
            }
        $html .= "              </select>";
        $html .= "               <div id=\"validation-address\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";

        $html.= $this->inc_kerdoiv("fifiregisztracio");

        /*$html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>1.</strong></span>";
        $html .= "    <label for=\"question-one\" class=\"form-label checkbox\"><strong>Dohányzik-e?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "    <div class=\"form-check ms-3\">";
        $html .= "        <input class=\"form-check-input questionaries\" style=\"margin-top:12px\" type=\"radio\" name=\"question-one\" id=\"question-one1\" value=\"1\">";
        $html .= "        <label class=\"form-check-label\" for=\"question-one1\">";
        $html .= "            Igen, napi <input class=\"form-control d-inline\" style=\"max-width:70px\" type=\"number\" name=\"\" value=\"\">&nbsp;db szálat";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "    <div class=\"form-check ms-3\">";
        $html .= "        <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-one\" id=\"question-one2\" value=\"2\">";
        $html .= "        <label class=\"form-check-label\" for=\"question-one2\">";
        $html .= "            Alkalmanként";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "    <div class=\"form-check ms-3\">";
        $html .= "        <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-one\" id=\"question-one3\" value=\"2\">";
        $html .= "        <label class=\"form-check-label\" for=\"question-one3\">";
        $html .= "            Nem";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "  </div>";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "</div>";

        $html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>2.</strong></span>";
        $html .= "  <label for=\"question-two\" class=\"form-label checkbox\"><strong>Fogyaszt-e alkoholt?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "  <div class=\"form-check ms-3\">";
        $html .= "      <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-two\" id=\"question-two1\" value=\"1\">";
        $html .= "      <label class=\"form-check-label\" for=\"question-two1\">";
        $html .= "          Igen, napi szinten";
        $html .= "      </label>";
        $html .= "  </div>";
        $html .= "  <div class=\"ms-4 form-floating mb-3 d-none\" style=\"font-size:12px\">";
        $html .= "      <textarea class=\"form-control\" style=\"font-size:12px\" placeholder=\"Leave a comment here\" id=\"question-two1-text\"></textarea>";
        $html .= "      <label for=\"question-two1-text\">Kérjük, fejtse ki pár sorban a válaszát.</label>";
        $html .= "  </div>";
        $html .= "  <div class=\"form-check ms-3\">";
        $html .= "      <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-two\" id=\"question-two2\" value=\"2\">";
        $html .= "      <label class=\"form-check-label\" for=\"question-two2\">";
        $html .= "          Igen, heti szinten";
        $html .= "      </label>";
        $html .= "  </div>";
        $html .= "  <div class=\"ms-4 form-floating mb-3 d-none\" style=\"font-size:12px\">";
        $html .= "      <textarea class=\"form-control\" style=\"font-size:12px\" placeholder=\"Leave a comment here\" id=\"question-two2-text\"></textarea>";
        $html .= "      <label for=\"question-two2-text\">Kérjük, fejtse ki pár sorban a válaszát.</label>";
        $html .= "  </div>";
        $html .= "  <div class=\"form-check ms-3\">";
        $html .= "      <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-two\" id=\"question-two3\" value=\"2\">";
        $html .= "      <label class=\"form-check-label\" for=\"question-two3\">";
        $html .= "          Igen, évente 1-2 alkalommal";
        $html .= "      </label>";
        $html .= "  </div>";
        $html .= "  <div class=\"ms-4 form-floating mb-3 d-none\" style=\"font-size:12px\">";
        $html .= "      <textarea class=\"form-control\" style=\"font-size:12px\" placeholder=\"Leave a comment here\" id=\"question-two3-text\"></textarea>";
        $html .= "      <label for=\"question-two3-text\">Kérjük, fejtse ki pár sorban a válaszát.</label>";
        $html .= "  </div>";
        $html .= "  <div class=\"form-check ms-3\">";
        $html .= "      <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-two\" id=\"question-two4\" value=\"2\">";
        $html .= "      <label class=\"form-check-label\" for=\"question-two4\">";
        $html .= "          Soha";
        $html .= "      </label>";
        $html .= "  </div>";
        $html .= "</div>";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "</div>";

        $html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>3.</strong></span>";
        $html .= "      <label for=\"question-three\" class=\"form-label checkbox\"><strong>Valamilyen diétát tart-e?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-three\" id=\"question-three1\" value=\"1\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-three1\">";
        $html .= "              Igen";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "  <div class=\"ms-4 form-floating mb-3 d-none\" style=\"font-size:12px\">";
        $html .= "      <textarea class=\"form-control\" style=\"font-size:12px\" placeholder=\"Leave a comment here\" id=\"question-three1-text\"></textarea>";
        $html .= "      <label for=\"question-three1-text\">Kérjük, fejtse ki pár sorban a válaszát.</label>";
        $html .= "  </div>";
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-three\" id=\"question-three2\" value=\"2\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-three2\">";
        $html .= "              Nem";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "  </div>";
        $html .= "<div class=\"col-md-3\"></div>";
        $html .= "</div>";

        $html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>4.</strong></span>";
        $html .= "      <label for=\"question-four\" class=\"form-label checkbox\"><strong>Van-e ismert krónikus betegséges (magas vérnyomás, pajzsmirigy betegség, cukorbetegség?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-four\" id=\"question-four1\" value=\"1\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-four1\">";
        $html .= "              Igen";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "      <div class=\"ms-4 form-floating mb-3 d-none\" style=\"font-size:12px\">";
        $html .= "          <textarea class=\"form-control\" style=\"font-size:12px\" placeholder=\"Leave a comment here\" id=\"question-four1-text\"></textarea>";
        $html .= "          <label for=\"question-four1-text\">Kérjük, fejtse ki pár sorban a válaszát.</label>";
        $html .= "      </div>";
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-four\" id=\"question-four2\" value=\"2\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-four2\">";
        $html .= "              Nincs";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "  </div>";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "</div>";

        $html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>5.</strong></span>";
        $html .= "      <label for=\"question-five\" class=\"form-label checkbox\"><strong>Szed-e gyógyszert rendszeresen?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-five\" id=\"question-five1\" value=\"1\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-five1\">";
        $html .= "              Igen";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "      <div class=\"ms-4 form-floating mb-3 d-none\" style=\"font-size:12px\">";
        $html .= "          <textarea class=\"form-control\" style=\"font-size:12px\" placeholder=\"Leave a comment here\" id=\"question-five1-text\"></textarea>";
        $html .= "          <label for=\"question-five1-text\">Kérjük, fejtse ki pár sorban a válaszát.</label>";
        $html .= "      </div>";
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-five\" id=\"question-five2\" value=\"2\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-five2\">";
        $html .= "              Nem";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "  </div>";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "</div>";

        $html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>6.</strong></span>";
        $html .= "      <label for=\"question-six\" class=\"form-label checkbox\"><strong>Mekkora a testsúlya?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "      <div class=\"form-check\">";
        $html .= "          <input class=\"form-control d-inline\" type=\"number\" name=\"question-six\" id=\question-six1\" style=\"max-width:70px\">";
        $html .= "          <span>kg</span>";
        $html .= "      </div>";
        $html .= "  </div>";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "</div>";

        $html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>7.</strong></span>";
        $html .= "      <label for=\"question-seven\" class=\"form-label checkbox\"><strong>Mekkora a magassága?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "      <div class=\"form-check\">";
        $html .= "          <input class=\"form-control d-inline\" type=\"number\" name=\"question-seven\" id=\question-seven1\" style=\"max-width:70px\">";
        $html .= "          <span>cm</span>";
        $html .= "      </div>";
        $html .= "  </div>";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "</div>";

        $html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>8.</strong></span>";
        $html .= "      <label for=\"question-eight\" class=\"form-label checkbox\"><strong>Szokott-e járni rendszeresen szűrővizsgálatokra?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-eight\" id=\"question-eight1\" value=\"1\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-eight1\">";
        $html .= "              Évente";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-eight\" id=\"question-eight2\" value=\"2\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-eight2\">";
        $html .= "              2 évente";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-eight\" id=\"question-eight3\" value=\"2\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-eight3\">";
        $html .= "              5 évente";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-eight\" id=\"question-eight4\" value=\"2\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-eight4\">";
        $html .= "              Nem szoktam járni";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "  </div>";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "</div>";

        $html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>9.</strong></span>";
        $html .= "      <label for=\"question-nine\" class=\"form-label checkbox\"><strong>Sportol-e rendszeresen?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-nine\" id=\"question-nine1\" value=\"1\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-nine1\">";
        $html .= "              Igen, napi szinten";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-nine\" id=\"question-nine2\" value=\"2\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-nine2\">";
        $html .= "              Igen, heti 2-3 alkalommal";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-nine\" id=\"question-nine3\" value=\"2\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-nine3\">";
        $html .= "              Igen, havi 2-3 alkalommal";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "      <div class=\"form-check ms-3\">";
        $html .= "          <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-nine\" id=\"question-nine4\" value=\"2\">";
        $html .= "          <label class=\"form-check-label\" for=\"question-nine4\">";
        $html .= "              Nem sportolok";
        $html .= "          </label>";
        $html .= "      </div>";
        $html .= "  </div>";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "</div>";

        $html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "  <span style=\"vertical-align:top\" class=\"form-label\"><strong>10.</strong></span>";
        $html .= "  <label for=\"question-ten\" class=\"form-label checkbox\"><strong>Van-e olyan gyógyszer, amelyet nem szedett be a vizsgálat előtt, de egyébként rendszerességgel szedi?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "  <div class=\"ms-4 form-floating mb-3\" style=\"font-size:12px\">";
        $html .= "      <textarea class=\"form-control\" style=\"font-size:12px\" placeholder=\"Leave a comment here\" id=\"question-five1-text\"></textarea>";
        $html .= "      <label for=\"question-five1-text\">Kérjük, fejtse ki pár sorban a válaszát.</label>";
        $html .= "  </div>";
        $html .= "</div>";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "</div>";*/

        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html.= "               <div class=\"d-block \">";//d-xl-none
        $html .= "                  <input class=\"form-check-input checkbox\" type=\"checkbox\" id=\"aszf\" name=\"aszf\">";
        $html .= "                  <label class=\"form-label checkbox\" for=\"aszf1\">";
        $html .= "                      Az <a target=\"_blank\" href=\"https://{$_SERVER["HTTP_HOST"]}/images/HungáriaMed_Adatvédelmi_tájékoztató_GHC.pdf\">Adatvédelmi tájékoztatót</a> elolvastam, a fenti adatkezeléshez hozzájárulok.";
        $html .= "                  </label>";
        $html.= "               </div>";
        /*$html .= "              <div class=\"d-none d-xl-block\">";
        $html .= "                  <input class=\"form-check-input checkbox\" type=\"checkbox\" id=\"aszf1\" name=\"aszf1\">";
        $html .= "                  <label class=\"form-label checkbox\" for=\"aszf1\">";
        $html .= "                      Az <a target=\"_blank\" data-bs-toggle=\"collapse\" href=\"#gdpr-collapse\" aria-expanded=\"false\" aria-controls=\"gdpr-collapse\">Adatvédelmi tájékoztatót</a> elolvastam, a fenti adatkezeléshez hozzájárulok.";
        $html .= "                  </label>";
        $html .= "              </div>";*/
        $html .= "               <div id=\"validation-aszf\" class=\"valid-feedback\"></div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";
        $html .= "       <div class=\"row d-none d-xl-block\">";
        $html .= "           <div class=\"col mb-3 text-center\">";
        $html .= "                  <div class=\"collapse multi-collapse\" id=\"gdpr-collapse\">";
        $html .= "                      <div class=\"ratio ratio-1x1\">";
        $html .= "                          <iframe src=\"https://{$_SERVER["HTTP_HOST"]}/images/HungáriaMed_Adatvédelmi_tájékoztató_GHC.pdf\" title=\"GDPR - Adatvédelmi tájékoztató\" allowfullscreen></iframe>";
        $html .= "                      </div>";
        $html .= "                  </div>";
        $html .= "           </div>";
        $html .= "       </div>";

        $html .= "       <div class=\"row\">";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "           <div class=\"col-md-6 mb-3\">";
        $html .= "               <div class=\"d-grid gap-2\">";
        $html .= "                   <button class=\"btn btn-hungariamed scrollToTopButton\" id=\"aldi-fifi-registration\" type=\"button\">Regisztráció</button>";
        $html .= "               </div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col-md-3\"></div>";
        $html .= "       </div>";
        $html .= "   </form>";
        $html .= "</div>";

        return $html;
    }

    public function inc_kerdoiv($kerdoivId){
        $html = "";
        $counter = 0;
        $questionaries = sql_query("SELECT * FROM kerdoiv_kerdesek WHERE kerdoivid=?",array($kerdoivId))->fetchAll(PDO::FETCH_ASSOC);

        $html .= "<div class=\"row\">";
        $html .= "    <div class=\"col-md-3\"></div>";
        $html .= "    <div class=\"col-md-6 mb-3 mt-3 text-center\">";
        $html .= "       <h1>Kérdőíves előszűrés</h1>";
        $html .= "       <span style=\"font-size:12px\">*A kérdéssor kitöltését erősen ajánljuk a páciens jobb kórtörténetének megítélése valamint a személyre szabott zárójelentés érdekében.</span>";
        $html .= "    </div>";
        $html .= "    <div class=\"col-md-3\"></div>";
        $html .= "</div>";
        
        foreach($questionaries as $index=>$value){

            $answers = sql_query("SELECT * FROM kerdoiv_valaszok WHERE kerdesid=?",array($value["id"]))->fetchAll(PDO::FETCH_ASSOC);

            $counter++;
            $html .= "<div class=\"row\">";
            $html .= "  <div class=\"col-md-3\"></div>";
            $html .= "  <div class=\"col-md-6 mb-3\">";
            $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>{$counter}.</strong></span>";
            $html .= "      <label for=\"question-{$value["id"]}\" class=\"form-label checkbox\"><strong>{$value["kerdes"]}</strong></label>";

            foreach($answers as $answerKey=>$answerData){
                if($answerData["valasz_tipus"]=="checkbox"){
                    $html .= "<div class=\"form-check ms-3\">";
                    $html .= "  <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-{$value["id"]}\" ".(!empty($answerData["sub_valasz_id"])?"style=\"margin-top:12px\"":"")." id=\"question-{$value["id"]}-{$answerData["id"]}\" value=\"{$answerData["valasz_ertek"]}\">"; /*style=\"margin-top:12px\"*/
                    $html .= "  <label class=\"form-check-label\" for=\"question-{$value["id"]}-{$answerData["id"]}\">";
                    if(!empty($answerData["sub_valasz_input"])){
                        $answerData["valasz_szoveg"] = str_replace("#sub_valasz_input#",$answerData["sub_valasz_input"], $answerData["valasz_szoveg"]);
                        $answerData["valasz_szoveg"] = str_replace("#sub_valasz_id#",$answerData["sub_valasz_id"], $answerData["valasz_szoveg"]);
                    }
                    $html .= "      {$answerData["valasz_szoveg"]}";
                    $html .= "  </label>";
                    $html .= "</div>";
                    if($answerData["kifejtes"]==1){
                        $html .= "  <div class=\"ms-4 form-floating mb-3 d-none\" style=\"font-size:12px\">";
                        $html .= "      <textarea class=\"form-control\" style=\"font-size:12px\" placeholder=\"Leave a comment here\" name=\"question-{$value["id"]}-{$answerData["id"]}-text\" id=\"question-{$value["id"]}-{$answerData["id"]}-text\"></textarea>";
                        $html .= "      <label for=\"question-{$value["id"]}-{$answerData["id"]}-text\">Kérjük, fejtse ki pár sorban a válaszát.</label>";
                        $html .= "  </div>";
                    }
                }
                if($answerData["valasz_tipus"]=="number"){
                    $html .= "<div class=\"form-check\">";
                    $html .= "    <input class=\"form-control d-inline\" type=\"number\" name=\"question-{$value["id"]}\" id=\question-{$value["id"]}-{$answerData["id"]}\" style=\"max-width:70px\">";
                    $html .= "    <span>{$answerData["valasz_szoveg"]}</span>";
                    $html .= "</div>";
                }
                if($answerData["valasz_tipus"]=="textarea"){
                    $html .= "  <div class=\"ms-4 form-floating mb-3\" style=\"font-size:12px\">";
                    $html .= "      <textarea class=\"form-control\" style=\"font-size:12px\" placeholder=\"\" name=\"question-{$value["id"]}\" name=\"question-{$value["id"]}-{$answerData["id"]}\" id=\"question-{$value["id"]}\"></textarea>";
                    $html .= "      <label for=\"question-{$value["id"]}\">{$answerData["valasz_szoveg"]}</label>";
                    $html .= "  </div>";
                }
                
            }
           
            $html .= "  </div>";
            $html .= "  <div class=\"col-md-3\"></div>";
            $html .= "</div>";
        }

        /*$html .= "<div class=\"row\">";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "  <div class=\"col-md-6 mb-3\">";
        $html .= "      <span style=\"vertical-align:top\" class=\"form-label\"><strong>1.</strong></span>";
        $html .= "    <label for=\"question-one\" class=\"form-label checkbox\"><strong>Dohányzik-e?</strong></label>"; //&nbsp;<span style=\"color:#cfa144\">*</span>
        $html .= "    <div class=\"form-check ms-3\">";
        $html .= "        <input class=\"form-check-input questionaries\" style=\"margin-top:12px\" type=\"radio\" name=\"question-one\" id=\"question-one1\" value=\"1\">";
        $html .= "        <label class=\"form-check-label\" for=\"question-one1\">";
        $html .= "            Igen, napi <input class=\"form-control d-inline\" style=\"max-width:70px\" type=\"number\" name=\"\" value=\"\">&nbsp;db szálat";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "    <div class=\"form-check ms-3\">";
        $html .= "        <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-one\" id=\"question-one2\" value=\"2\">";
        $html .= "        <label class=\"form-check-label\" for=\"question-one2\">";
        $html .= "            Alkalmanként";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "    <div class=\"form-check ms-3\">";
        $html .= "        <input class=\"form-check-input questionaries\" type=\"radio\" name=\"question-one\" id=\"question-one3\" value=\"2\">";
        $html .= "        <label class=\"form-check-label\" for=\"question-one3\">";
        $html .= "            Nem";
        $html .= "        </label>";
        $html .= "    </div>";
        $html .= "  </div>";
        $html .= "  <div class=\"col-md-3\"></div>";
        $html .= "</div>";*/

        return $html;
    }
}
