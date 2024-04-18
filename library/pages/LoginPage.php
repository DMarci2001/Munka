<?php

class LoginPage extends CorePage {

    private $developMode = false;

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;

        if($_SERVER["HTTP_HOST"]=="marciteszt.hungariamed.hu"){
            $this->developMode = true;
        }

        if (isset($_POST["logintry"])) {
            if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where email=? and jelszo=md5(?) and cegid=?", array($_POST["email"], $_POST["jelszo"], $_SESSION["helyszindata"]["id"])))) {
                $_SESSION["loggeduser"] = $rowu["id"];
                header("location:index.php");
                die();
            } else {
                $this->formError = "{$webText["loginerror"]}";
            }
        }

        if(isset($_POST["sendsms"])){
            if($result = sql_fetch_array(sql_query("SELECT *,UNIX_TIMESTAMP()-UNIX_TIMESTAMP(rkoddatum) as rkodsec FROM felhasznalok WHERE taj=? AND cegid=?",array($_POST["taj"],$_SESSION["helyszindata"]["id"])))){
                
                if ($result["rkodsec"] < 600 && $result["rkodsec"] != NULL) {
                    if($this->developMode){
                        die("Az SMS kód ki let küldve! ({$result["rkodsec"]} sec a kód: {$result["rkod"]})");
                    }
                    die("Az SMS ki lett küldve a TAJ számhoz tartozó telefonszámra.");
                }else{
                    //kód generálása és kiküldése:
                    $rn = rand(11000, 98000);
                    sql_query("update felhasznalok set rkod=?,rkoddatum=now() where id=?",array($rn, $result["id"]));
                    $this->utils->sendLoginSMSKod($result["id"]);
                    if($this->developMode){
                        die("Az SMS ki lett küldve a TAJ számhoz tartozó telefonszámra. a kód: {$result["rkod"]})");
                    }
                    die("Az SMS ki lett küldve a TAJ számhoz tartozó telefonszámra.");
                }
            }
            if($this->developMode){
                die("Rosszak a megadott adatok!");
            }
            die("Az SMS ki lett küldve a TAJ számhoz tartozó telefonszámra.");
        }

        if(isset($_POST["suzukilogin"])){
            if($result = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE rkod=? AND taj=? and cegid=?",array($_POST["sms-code"],$_POST["taj"],$_SESSION["helyszindata"]["id"])))){
                if (strtotime("now") - strtotime($result["rkoddatum"]) > 600) {
                    die(json_encode(array("error"=>"A megadott TAJ szám, vagy kód nem megfelelő!")));
                }
                $_SESSION["loggeduser"] = $result["id"];
                die(json_encode(array("error"=>"","url"=>"https://{$_SERVER["HTTP_HOST"]}/?page=booking")));
            }
            die(json_encode(array("error"=>"A megadott TAJ szám, vagy kód nem megfelelő!")));
        }

    }

    public function showPage() {
        $webText = $this->lang->webText;

        if (!isset($_POST["email"])) {
            $_POST["taj"]=$_POST["email"]=$_POST["jelszo"]="";
        }

        if(CompanyService::isSuzukiGHC()){

            $html = "";
            $html = $this->displayFejlec("Suzuki GHC szűrés",true);
            $html .= "<div class=\"container\">";
            $html .= "   <form id='suzuki-ghc-login-form' method='POST' enctype='multipart/form-data'>";
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
            $html .= "          <div class=\"col-md\"></div>";
            $html .= "          <div class=\"col mb-3\">";
            $html .= "              <div class=\"input-group mb-3\">";
            $html .= "                  <input type=\"text\" class=\"form-control\" placeholder=\"SMS kód\" id=\"sms-code\" name=\"sms-code\" aria-label=\"SMS kód\" aria-describedby=\"send-sms\">";
            $html .= "                  <button class=\"btn btn-hungariamed\" onClick=\"sendLoginSms()\" type=\"button\" id=\"send-sms\">SMS kód küldés</button>";
            $html .= "              </div>";
            $html .= "          </div>";
            $html .= "          <div class=\"col-md\"></div>";
            $html .= "          </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3\">";
            $html .= "               <div class=\"d-grid gap-2\">";
            $html .= "                   <button class=\"btn btn-hungariamed\" id=\"suzuki-login\" type=\"button\">Időpontfoglalás</button>";
            $html .= "               </div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "       <div class=\"row\">";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "           <div class=\"col mb-3 text-center\">";
            $html .= "              <div><a href=\"https://{$_SERVER["HTTP_HOST"]}/?page=registration\">Még nem regisztrált?</a></div>";
            $html.= "               <div><a href=\"#\" onClick=\"alert(\"SMS kiküldése e-mailben.\")\">SMS kód küldése e-mail címre</a></div>";
            $html .= "           </div>";
            $html .= "           <div class=\"col-md\"></div>";
            $html .= "       </div>";
            $html .= "  </form";
            $html .= "</div>";

            echo $html;
            return;
        }

        echo $this->displayFejlec($webText["bejelentkezes"]);
        echo $this->showFormErrors();

        if (isset($_GET["passwordsent"])) {
            echo $this->formMessage("Az új jelszavát a megadott e-mail címre elküldtük.");
        }

        echo "<div id='normallogin'>";
        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<input type='hidden' name='logintry' value='1'/>";

        echo "<table>";
        echo "<tr><td width='100'>{$webText["email"]}:</td><td><input class='inputbox' style='width:200px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
        echo "<tr><td width='100'>{$webText["jelszo"]}:</td><td><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo' value='{$_POST["jelszo"]}'></td></tr>";
        echo "</table>";

        echo "<br/><a href='#' class='newbutton' onclick='document.iform.submit();return false;'>{$webText["bejelentkezes"]}</a>";
        echo "</form>";

        echo "<div style='margin-top:20px;'>";
        echo "{$webText["hanememlekszik"]}<br/><a href='index.php?page=passwordsend'>{$webText["ujjelszokerese"]}</a>";
        echo "</div>";

        echo "<div style='margin-top:20px;'>";
        echo "{$webText["amennyibennememail"]}:<br/><a href='index.php?page=loginwithtajnumber'>{$webText["bejelentkezestaj"]}</a>";
        echo "</div>";

        echo "</div>";
    }
}

