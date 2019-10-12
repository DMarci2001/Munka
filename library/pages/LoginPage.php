<?php

class LoginPage extends CorePage {

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;

        if (isset($_POST["logintry"])) {
            if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where email=? and jelszo=md5(?) and cegid=?", array($_POST["email"], $_POST["jelszo"], $_SESSION["helyszindata"]["id"])))) {
                $_SESSION["loggeduser"] = $rowu["id"];
                header("location:index.php");
                die();
            } else {
                $this->formError = "{$webText["loginerror"]}";
            }
        }

    }

    public function showPage() {
        $webText = $this->lang->webText;

        if (!isset($_POST["email"])) {
            $_POST["taj"]=$_POST["email"]=$_POST["jelszo"]="";
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

