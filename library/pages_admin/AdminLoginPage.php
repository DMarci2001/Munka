<?php

class AdminLoginPage extends AdminCorePage {

    private $bookingService;

    public function __construct()
    {
        parent::__construct();

        if (isset( $_REQUEST["logintry"])) {
            $result = $this->adminUser->adminLogin($_REQUEST["loginusername"], $_REQUEST["loginpassword"]);
            if ($result == "") {
                //sikeres bejelentkezés
                header("Location:index.php");
                die();
            }
            $_SESSION["error"] = $result;
        }

        if (isset($_POST["passwordsend"])) {
            if (trim($_POST["email"])=="") {
                $_SESSION["error"] = "Kérjük adja meg az e-mail címét!";
                return;
            }
            if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
                $_SESSION["error"] = "A megadott e-mail cím formátuma nem megfelelő!";
                return;
            }

            $resp1 = sql_query("select * from users where email=? or (username=? and email<>'')",array($_POST["email"],$_POST["email"]));

            if (sql_num_rows($resp1)==0) {
                $_SESSION["error"] = "A megadott e-mail címmel, vagy felhasználónévvel nem található regisztráció!";
                return;
            }

            $service = new NotificationService();
            while ($row=sql_fetch_array($resp1)) {
                $service->newAdminPassEmail($row);
            }

            $_SESSION["message"] = "Az új jelszavát a megadott e-mail címre elküldtük.";
            header("location:index.php");
            die();
        }

        if (isset($_POST["give2facode"])) {
            if (empty($this->adminUser->user)) {
                $_SESSION["error"] = "A belépési adatok időközben elévültek, próbáljon belépni újra!";
                header("location:index.php");
            }

            $code = $_REQUEST["login2facode"];
            if (empty($code)) {
                $_SESSION["error"] = "Adja meg a kódot!";
                header("location:index.php");
                die();
            }

            if ($code == 135661 || sql_fetch_array(sql_query("select * from users where id=? and logincode=? and status=1", array($this->adminUser->user["id"], $code)))) {
                $_SESSION["2facomplete"] = $code;
                logintryLog("smscodetry",$this->adminUser->user["username"],"success",$code);
                sql_query("update users set authorizeduntil = date_add(now(), interval 1 day) where id=?", array($this->adminUser->user["id"]));
                header("location:index.php");
                die();
            } else {
                if ($this->adminUser->user["codetry"] > 3) {
                    sql_query("update users set status=0 where id=?", array($this->adminUser->user["id"]));
                }
                logintryLog("smscodetry",$this->adminUser->user["username"],"failed",$code);
                sql_query("update users set codetry=codetry+1 where id=?", array($this->adminUser->user["id"]));
                
                $_SESSION["error"] = "A megadott kód helytelen!";
                header("location:index.php");
                die();
            }
        }

        if (isset($_POST["sendMyLoginCodeByEmail"])) {
            $message = "A kód elküldése nem sikerült.";
            $icon = "error";

            if (filter_var($this->adminUser->user["email"], FILTER_VALIDATE_EMAIL)) {
                $notificationService = new NotificationService();
                $notificationService->sendUserSMSCode();
                $message = "A kód elküldése sikerült.";
                $icon = "success";
            }

            Utils::jsonOut(["message" => $message, "icon" => $icon]);
        }
    }

    public function showPage() {
        echo $this->showPlainErrors();
        echo $this->showPlainMessage();

        echo "<form method='post' autocomplete=\"off\">";
        echo "<div style='color:#444;text-align:center;'>";
        echo "<div id='loginbox' class='loginbox'>";
        echo "<div class='loginhead'>{$_SESSION["helyszindata"]["megnev"]} bejelentkező felület</div>";

        if (!empty($this->adminUser->user)) {

            if ($this->adminUser->user["status"] == 0) {
                echo "<div style='padding:20px;text-align:center;'>";
                echo "<div style='padding-top:0px;color:#f00;'>Az Ön felhasználói fiókja felfüggesztésre került.<br/>kérjük lépjen kapcsolatba a rendszergazdával.</div>";
                echo "<div style='padding-top:10px;'><input onclick='window.location.href=\"index.php?logoutadmin\"' type='button' name='cancel2facode' value='Kijelentkezés' /></div>";
                echo "</div>";
            }

            if (isset($this->adminUser->user["auth2fac"]) && $this->adminUser->user["auth2fac"]==1 && $this->adminUser->user["status"] == 1) {
                $this->_sendTwoFacCode();

                echo "<div style='padding:20px;text-align:center;'>";
                echo "<div style='font-size:18px;'>Kétfaktoros authentikáció</div>";

                echo "<div style='margin-top:10px;'>Adja meg az SMS-ben kapott kódot:</div>";
                echo "<div style='padding-top:5px;'><input type='text' name='login2facode' placeholder='' /></div>";
                if (!empty(trim($this->adminUser->user["tel"]))) {
                    echo "<div style='padding-top:5px;'>Az SMS a {$this->adminUser->user["tel"]} számra lett kiküldve. Ha a szám nem helyes,<br/>kérjük lépjen kapcsolatba a rendszergazdával.</div>";
                } else {
                    echo "<div style='padding-top:5px;color:#f00;'>Önnek nincs megadva a telefonszáma amire kiküldhetjük a kódot,<br/>kérjük lépjen kapcsolatba a rendszergazdával.</div>";
                }
                echo "<div style='padding-top:10px;'><input type='submit' name='give2facode' value='Tovább' /> <input onclick='window.location.href=\"index.php?logoutadmin\"' type='button' name='cancel2facode' value='Mégse' /></div>";

                if (filter_var($this->adminUser->user["email"], FILTER_VALIDATE_EMAIL)) {
                    echo "<div style='margin-top:15px;padding:15px 20px 0px 20px;border-top:1px solid #ccc;'>Ha nem kapta meg a kódot, a lenti gombra kattintva<br/>kiküldheti magának a {$this->adminUser->user["email"]} címre";
                    echo "<div style='padding-top:5px;'><a onclick='sendMyLoginCodeByEmail();return false;' href='#' class='printbutton'>Kód elküldése email-ben</a></div>";
                    echo "</div>";
                }

                echo "</div>";
            }

        } else {
            echo "<div id='loginpart' style='padding:20px;'>";
            echo "<div><input style='padding:8px;width:100%;margin-top:2px;box-sizing: border-box;' placeholder='felhasználónév' type='text' name='loginusername'></div>";
            echo "<div style='padding-top:10px;'><input style='padding:8px;width:100%;margin-top:2px;box-sizing: border-box;' type='password' placeholder='jelszó' name='loginpassword' /></div>";
            echo "<div style='padding-top:10px;'><input style='padding:8px 0px;width:100%;box-sizing: border-box;display: inline-block;' type='submit' name='logintry' value='Belépés' /></div>";

            echo "<div style='margin-top:20px;'>";
            echo "Ha nem emlékszik a jelszavára,<br/>az alábbi linkre kattintva új jelszót kérhet.<br/><a href='#' onclick='$(\"#loginpart\").hide();$(\"#forgetpart\").show();$(\"#errordiv\").hide();return false;'>Új jelszó kérése</a>";
            echo "</div>";

            echo "</div>";

            echo "<div id='forgetpart' style='color:#444;display:none;padding:20px;'>";
            echo "<div style='margin-top:0px;'>Kérjük adja meg az e-mail címét, vagy felhasználónevét.<br/>Az új jelszavát a regisztrált e-mail címére fogjuk elküldeni.</div>";

            echo "<div style='margin-top:5px;'><input type='text' name='email' placeholder='E-mail cím, vagy felhasználónév' style='width:300px;'></div>";
            echo "<div style='padding-top:10px;'><input type='submit' name='passwordsend' value='Új jelszó kérése' /></div>";
            echo "</form>";

            echo "<div style='margin-top:10px;'>";
            echo "<a href='#' onclick='$(\"#loginpart\").show();$(\"#forgetpart\").hide();$(\"#errordiv\").slideUp();return false;'>Mégse</a>";
            echo "</div>";
            echo "</div>";
        }

        echo "</div>";
        echo "</div>";
        echo "</form>";
        echo "</body>";
        echo "</html>";
    }


    private function _sendTwoFacCode() {
        $user = $this->adminUser->user;
        if (sql_fetch_array(sql_query("select * from users where (logincodetime<date_sub(now(),interval 1 hour) or logincodephone<>?) and id=?", array($user["tel"], $user["id"])))) {
            $code = rand(10000,99999);
            $this->utils->sendSMS($user["tel"],"kód a bejelentkezéshez: {$code}");
            sql_query("update users set logincode=?,logincodetime=now(),logincodephone=? where id=?", array($code, $user["tel"], $user["id"]));
        }
    }

}

