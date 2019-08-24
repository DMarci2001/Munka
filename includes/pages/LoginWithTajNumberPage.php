<?php

class LoginWithTajNumberPage extends CorePage {

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;

        if (isset($_POST["requestsmskod"])) {
            $taj = $_POST["taj"];
            $taj = str_replace("-", "", $taj);
            $taj = trim(str_replace(" ", "", $taj));

            if ($_POST["captcha"] != $_SESSION["captcha"]) {
                echo "A beírt szám nem egyezik!";
                die();
            }
            if (empty($taj)) {
                echo "A TAJ szám megadása kötelező!";
                die();
            }
            if (!ctype_digit($taj) && $taj != "") {
                echo "A TAJ szám formátuma nem megfelelő!";
                die();
            }
            if (!$userData = sql_fetch_array(sql_query("select f.*,UNIX_TIMESTAMP()-UNIX_TIMESTAMP(rkoddatum) as rkodsec from felhasznalok f where taj=? and cegid=?",array($taj, $_SESSION["helyszindata"]["id"])))) {
                echo "A megadott TAJ számmal nem található felhasználó!";
                die();
            }

            if ($userData["rkodsec"] < 600 && $userData["rkodsec"] != NULL) {
                echo "sentback";
                die();
            }

            //kód generálása és kiküldése:
            $rn = rand(11000, 98000);
            sql_query("update felhasznalok set rkod=?,rkoddatum=now() where id=?",array($rn, $userData["id"]));
            $this->utils->sendLoginSMSKod($userData["id"]);

            die("sentnow");
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

            if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where taj=? and rkod=? and cegid=?",array($taj, $_POST["kod"], $_SESSION["helyszindata"]["id"])))) {
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

    }

    public function showPage() {
        $webText = $this->lang->webText;

        if (!isset($_POST["email"])) {
            $_POST["taj"]=$_POST["email"]=$_POST["jelszo"]=$_POST["captcha"]="";
        }

        echo $this->displayFejlec($webText["bejelentkezes"]);
        echo $this->showFormErrors();

        echo "<div id='tajlogin'>";
        echo "<form name='iform' method='post' enctype='multipart/form-data'>";

        echo "<table>";
        if (!isset($_SESSION["captcha"])) {
            $_SESSION["captcha"] = rand(110,988);
        }
        echo "<tr><td width='140'>Az Ön TAJ száma:</td><td><input class='inputbox' style='width:200px;' type='text' name='taj' id='taj' value='{$_POST["taj"]}'></td></tr>";
        echo "<tr><td colspan='2'><div style='margin-top:10px;'>Kérem, adja meg a következő számot számjegyekkel: <b>".$this->utils->numToString($_SESSION["captcha"])."</b>:<br><input class='inputbox' style='width:60px;' type='text' name='captcha' id='captcha' value='{$_POST["captcha"]}'></div></td></tr>";
        echo "<tr id='kodmezo' style='display:none;'><td width='120'>SMS-ben kapott kód:</td><td><input class='inputbox' style='width:200px;' type='text' autocomplete='off' name='jelszo' id='jelszo' value='{$_POST["jelszo"]}'></td></tr>";
        echo "</table>";

        echo "<div style='margin-top:10px;'>";
        echo "<div id='kodkerogomb'><a href='#' class='newbutton' onclick=\"requestSMSkod($('#taj').val(),$('#captcha').val());return false;\">SMS kód kérése</a></div>";
        echo "<div id='logingomb' style='display:none'><a href='#' class='newbutton' onclick=\"loginTryWithTAJ($('#taj').val(),$('#jelszo').val());return false;\">Bejelentkezés</a></div>";
        echo "</div>";
        echo "</form>";

        echo "<div style='margin-top:20px;'>";
        echo "Ha inkább normál módon szeretne bejelentkezni, kattintson ide:<br/><a href='index.php?page=login'>Normál bejelentkezés</a>";
        echo "</div>";

        echo "</div>";
    }
}

