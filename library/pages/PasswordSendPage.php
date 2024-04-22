<?php

class PasswordSendPage extends CorePage {

    public function __construct()
    {
        parent::__construct();

         $webText = $this->lang->webText;

        $_POST = $this->utils->sanitize_array($_POST);
        $_GET  = $this->utils->sanitize_array($_GET);

        if (isset($_POST["passwordsend"])) {
            if (trim($_POST["email"]) == "") {
                $this->formError = "{$webText["kerjukadjamegemail2"]}";
                return;
            }
            if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
                $this->formError = "{$webText["emailformat"]}";
                return;
            }

            if ($rowu = sql_fetch_array(sql_query("select * from felhasznalok where email=? and cegid=?", array($_POST["email"], $_SESSION["helyszindata"]["id"])))) {
                $service = new NotificationService();
                $service->newUserPassEmail($rowu, $_COOKIE["lang"]);
                header("location:index.php?page=login&passwordsent");
                die();
            } else {
                $this->formError = "{$webText["nemtalalhatoemail"]}";
            }

        }

    }

    public function showPage() {
        $webText = $this->lang->webText;

        if (!isset($_POST["email"])) {
            $_POST["email"]="";
        }

        echo $this->displayFejlec($webText["ujjelszokerese"]);
        echo $this->showFormErrors();

        echo "<div>";
        echo "<form name='iform' method='post' enctype='multipart/form-data'>";

        echo "<div style='margin-top:0px;'>";
        echo "{$webText["kerjukadjamegemail"]}";
        echo "</div>";

        echo "<table style='margin-top:20px;'>";
        echo "<tr><td>{$webText["email"]}:&nbsp;&nbsp;&nbsp;</td><td><input class='inputbox' style='width:200px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
        echo "</table>";

        echo "<br/><input type='submit' name='passwordsend' value='{$webText["ujjelszokerese"]}'> ";
        echo "</form>";

        echo "</div>";
    }
}
