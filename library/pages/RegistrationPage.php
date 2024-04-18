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

            if($_POST["taj"]!="" && !$invited=sql_query("SELECT * FROM ghc_segedtabla WHERE taj=?",array($_POST["taj"]))->fetch(PDO::FETCH_ASSOC)){
                $error = "Sajnálatos módon Ön nem jogosult a Suzuki GHC szűrésre.<br><br> Kérjük keresse meg a Magyar Suzuki Zrt. HR Osztályát.";
                die(json_encode(array("error"=>$error,"status" => $status, "url"=> $url)));
            }
            
            if($_POST["taj"]!="" && $registered=sql_query("SELECT * FROM felhasznalok WHERE taj=? AND cegid=?",array($_POST["taj"],904))->fetch(PDO::FETCH_ASSOC)){
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
    }

    public function showPage()
    {
        $webText = $this->lang->webText;

        if (!isset($_POST["email"])) {
            $_POST["email"] = $_POST["nev"] = $_POST["telefon"] = $_POST["szuldatum"] = $_POST["taj"] = $_POST["irsz"] = $_POST["varos"] = $_POST["utca"] = $_POST["munkaltato"] = $_POST["munkakor"] = $_POST["torzsszam"] = $_POST["jelszo"] = $_POST["jelszo2"] = $_POST["captcha"] = "";
        }
        if (!isset($_POST["neme"])) $_POST["neme"] = "";

        $maxBirthDate = date("Y-m-d",strtotime("Now - 18 years"));

        echo $this->displayFejlec("Suzuki GHC szűrés - Regisztráció", true);
        echo $this->showFormErrors();

        if (CompanyService::isSuzukiGHC()) {

            $html = "";
            $html .= "<div class=\"container\">";
            $html .= "   <form id='suzuki-ghc-registration-form' method='POST' enctype='multipart/form-data'>";
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
            $html .= "                      Az <a target=\"_blank\" href=\"https://{$_SERVER["HTTP_HOST"]}/images/Hmed_GHC_adatvédelmi_tájékoztató.pdf\">Adatvédelmi tájékoztatót</a> elolvastam, a fenti adatkezeléshez hozzájárulok.";
            $html .= "                  </label>";
            $html.= "               </div>";
            $html .= "              <div class=\"d-none d-xl-block\">";
            $html .= "                  <input class=\"form-check-input checkbox\" type=\"checkbox\" id=\"aszf\" name=\"aszf\">";
            $html .= "                  <label class=\"form-label checkbox\" for=\"aszf\">";
            $html .= "                      Az <a target=\"_blank\" data-bs-toggle=\"collapse\" href=\"#multiCollapseExample1\" aria-expanded=\"false\" aria-controls=\"multiCollapseExample1\">Adatvédelmi tájékoztatót</a> elolvastam, a fenti adatkezeléshez hozzájárulok.";
            $html .= "                  </label>";
            $html .= "              </div>";
            $html .= "               <div id=\"validation-aszf\" class=\"valid-feedback\"></div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row d-none d-xl-block\">";
            $html .= "           <div class=\"col mb-3 text-center\">";
            $html .= "                  <div class=\"collapse multi-collapse\" id=\"multiCollapseExample1\">";
            $html .= "                      <div class=\"ratio ratio-1x1\">";
            $html .= "                          <iframe src=\"https://{$_SERVER["HTTP_HOST"]}/images/Hmed_GHC_adatvédelmi_tájékoztató.pdf\" title=\"GDPR - Adatvédelmi tájékoztató\" allowfullscreen></iframe>";
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
            echo $html;
            return;
        }

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
        SET cegid=?,nev=?,szuldatum=?,email=?,telefon=?,taj=?,regtime=?,irsz=?,varos=?,utca=?,validated=?,szallitas=?,otp_penztar=?,pass=?
        ", array(
            904, $data["name"], $data["birthdate"], $data["email"], $data["phone"], $data["taj"], date("Y-m-d H:i:s"), $data["zip-code"],
            $data["city"], $data["address"], 1, $data["transportation"], $data["otp-healthfund"],$pass
        ));

        $id = sql_insert_id();

        $notificationService = New NotificationService();


        $url = "https://marciteszt.hungariamed.hu/?page=registrationsuccessful&pass={$pass}&id={$id}";

        return $url;
    }
}
