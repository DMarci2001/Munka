<?php

use PHPMailer\PHPMailer\PHPMailer;

class SuzukiFormPage extends CorePage
{
    public function __construct()
    {
        parent::__construct();

        $this->pageTitle = "Suzuki GHC - Online jelentkezés";
        $this->lockInPage = true;
        $this->showLangMenu = false;
        $this->showMainMenu = false;
        $this->showSuzukiLogo = true;

        if (isset($_GET["emailteszt"])) {
            $data["email"] = "jnsmobil@gmail.com";
            $this->doneEmail($data);
        }

        if (isset($_REQUEST["formsavedata"])) {
            $result = ["error" => "", "html" => $this->donePage()];

            if (empty($_POST["nev"]) || empty($_POST["torzsszam"])) {
                $result["error"] = "Kérjük adja meg az adatait!";
            }

            if (empty($result["error"])) {
                $result["error"] = $this->utils->checkCaptcha();
            }

            if ($result["error"] == "") {
                unset($_POST["g-recaptcha-response"]);
                sql_query("insert into dataform set formtype='suzukitorzsszam', nev=?, torzsszam=?", [$_POST["nev"], $_POST["torzsszam"]]);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["suzuki-confirm-button"])) {
            if (isset($_POST["confirmed"])) {
                $sid = intval($_GET["sid"]);

                //$igeny = sql_query("select * from webservicelog where id=?", [$sid])->fetch(PDO::FETCH_ASSOC);

                sql_query("update webservicelog set useragent=? where id=?", [$_POST["confirmed"], $sid]);

                $GLOBALS["confirmed"] = 1;
            }
        }


    }

    public function showPage()
    {
        if (!isset($_POST["nev"])) $_POST["nev"] = "";
        if (!isset($_POST["szuldatum"])) $_POST["szuldatum"] = "";
        if (!isset($_POST["taj"])) $_POST["taj"] = "";
        if (!isset($_POST["email"])) $_POST["email"] = "";
        if (!isset($_POST["telefon"])) $_POST["telefon"] = "";

        if (isset($_POST["szuldatumev"]) && isset($_POST["szuldatumho"]) && isset($_POST["szuldatumnap"])) {
            $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . ($_POST["szuldatumho"] < 10 ? "0" : "") . $_POST["szuldatumho"] . "-" . ($_POST["szuldatumnap"] < 10 ? "0" : "") . $_POST["szuldatumnap"];
        }


        echo $this->displayFejlexSuzuki("Suzuki GHC - Online jelentkezés", true);


        echo "<div id='oltasformdiv'>";

        echo $this->showErrors();

        echo "<form name='suzukiform' id='suzukiform' method='POST' enctype='multipart/form-data'>";


        if (isset($_GET["subpage"]) && $_GET["subpage"] == "suzukiconfirmation") {
            echo "<div style='margin:20px 0px 20px 0px;'>";
            echo "<div><strong>Köszönjük a közreműködést!</strong></div>";
            echo "</div>";
            echo "</form>";
            echo "</div>";
            return;
        }


        echo "<div>#szöveg helye#<br/><br/>Kérjük töltse ki az alábbi űrlapot.</div>";


        //Páciens Adatok:
        echo "<h2>Adatok</h2>";
        echo "<table cellpadding='3' cellspacing='0'>";
        echo "<tr><td>Név:</td><td><input style='width:260px' type='text' value='{$_POST["nev"]}' name='nev' id='nev'></td></tr>";
        echo "<tr><td>Törzsszám:</td><td><input style='width:260px' type='text' value='{$_POST["torszam"]}' name='torzsszam' id='torzsszam'></td></tr>";
        if (!isset($_POST["g-recaptcha-response"])) {
            echo "<tr><td></td><td><div class='g-recaptcha' data-callback='recaptchaCallback' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
        }
        echo "</table>";

        echo "<div style='margin-top:30px;text-align: center;'><input type='button' name='suzukiform-submit-button' id='suzukiform-submit-button' class='newbutton' style='border:none' value='Adatok elküldése' /></div>";

        echo "</form>";
        echo "</div>";

    }

    private function donePage():string {
        $html = "";

        $html.= "<div style='margin:20px 0px 20px 0px;'>">
        $html.= "<div style='min-height:100px;'><strong>Köszönjük a kitöltést!</strong></div>";
        $html.= "</div>";

        return $html;
    }

    private function doneEmail($data) {
        $mail = new PHPMailer();
        $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
        $mail->FromName = Booking_Constants::COMPANY_NAME;
        $mail->AddAddress($data["email"]);
        $mail->CharSet = "UTF-8";
        $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
        $mail->IsHTML(true);

        $mail->Subject = "Értesítés oltási regisztrációról";
        $mail->Body = "Tisztelt jelentkező!<br/>
        <br/>
        Köszönjük regisztrációját.<br/>
        Oltási időpontjáról hamarosan értesítést küldünk e-mail címére és SMS-ben.<br/>
        <br/>
        Üdvözlettel:<br/>
        Hungária Med-M Kft.";

        $mail->Send();
    }

}
