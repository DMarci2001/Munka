<?php

class ValidateLoginPage extends CorePage {

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;

        if (isset($_POST["validatelogin"])) {
            if ($_POST["smskod"] == "") {
                $this->formError.= "{$webText["nemadtamegkod"]}<br/>";
            } else {

                $kod = intval($_POST["smskod"]);
                if ($_POST["smskod"] != "" && !sql_fetch_array(sql_query("select rkod from felhasznalok where id=? and rkod=?",array($_SESSION["user"]["id"], $kod)))) {
                    $this->formError.= "{$webText["hibaskod"]}<br/>";
                } else {
                    sql_query("update felhasznalok set validated=1 where id=? and rkod=?",array($_SESSION["user"]["id"], $kod));
                    header("location:index.php?page=validationsuccessful");
                    die();
                }
            }
        }

    }

    public function showPage() {
        $webText = $this->lang->webText;

        if (!isset($_POST["smskod"])) {
            $_POST["smskod"] = "";
        }

        echo $this->displayFejlec($webText["bejelentkezes"]);
        echo $this->showFormErrors();

        echo $this->showPageDescription("A regisztrációja aktiválásához egy kódot küldtünk a megadott telefonszámára. Kérjük adja meg az alábbi mezőbe a kapott kódot.");

        echo "<form name='iform' method='post' enctype='multipart/form-data'>";

        echo "<table>";
        echo "<tr><td width='150'>SMS-ben kapott kód: *</td><td><input class='inputbox' style='width:100px;' type='text' name='smskod' value='{$_POST["smskod"]}'><input type='hidden' name='validatelogin' value='1' /></td></tr>";
        echo "</table>";

        echo "<br><a href='#' class='newbutton' onclick='document.iform.submit();return false;'>Regisztráció aktiválása</a>";
        echo "</form>";
    }
}
