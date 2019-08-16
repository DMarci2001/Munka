<?php


class PasswordSendPage extends CorePage {

    public function __construct()
    {
        parent::__construct();

         $webText = $this->lang->webText;

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
                $pchars = "abcdefghijklmnpqrstuvwxyz1234567899";
                $p = "";
                for ($i = 0; $i < Booking_Settings::GENERATED_PASSWORD_LENGTH; $i++) {
                    $p .= substr($pchars, rand(0, strlen($pchars) - 1), 1);
                }

                include_once("includes/phpmailer/class.phpmailer.php");
                $mail = new PHPMailer();
                $mail->From = "noreply@hungariamed.hu";
                $mail->FromName = "Hungariamed";
                $mail->AddAddress($rowu["email"]);
                $mail->AddReplyTo("noreply@hungariamed.hu");
                $mail->IsHTML(true);

                $t = iconv("UTF-8", "ISO-8859-2", "Új jelszó kérése");

                $mbody = "Kedves {$rowu["nev"]}!<br/><br/>";
                $mbody .= "Az online bejelentkezési felületünkön új jelszó kérését kezdeményezte.<br/><br/>";
                $mbody .= "Az új jelszava: <b>{$p}</b><br><br>";
                $mbody .= "Az új jelszavát bejelentkezés követően az adatmódosítás menüpont alatt tudja megváltoztatni.<br/>";
                $mbody .= "<br/>";
                $mbody .= "Üdvözlettel:<br>Hungariamed";

                if ($_COOKIE["lang"] == "de") {
                    $mbody = "Lieber {$rowu["nev"]}!<br/><br/>";
                    $mbody .= "Unsere online anmelden Oberfláche sie beginnen eine neue Kennwort anbietten.<br/><br/>";
                    $mbody .= "Die neue Kennwort: <b>{$p}</b><br><br>";
                    $mbody .= "Nach den anmelden können Sie um  einem neuem Kennwort bitten.<br/>";
                    $mbody .= "<br/>";
                    $mbody .= "Freundlichen Grüssen:<br>Hungariamed";
                }
                if ($_COOKIE["lang"] == "en") {
                    $mbody = "Dear {$rowu["nev"]}!<br/><br/>";
                    $mbody .= "You have requested a new password on our reservation page.<br/><br/>";
                    $mbody .= "Your new password: <b>{$p}</b><br><br>";
                    $mbody .= "You can change your new password under the profile page.<br/>";
                    $mbody .= "<br/>";
                    $mbody .= "Regards<br>Hungariamed";
                }

                $mail->Subject = $t;
                $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
                //$mail->AddAttachment("");
                $mail->Send();

                sql_query("update felhasznalok set jelszo=?	where id=?",array(md5($p), $rowu["id"]));

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

        echo "<table style='font-size:12px;margin-top:20px;'>";
        echo "<tr><td>{$webText["email"]}:&nbsp;&nbsp;&nbsp;</td><td><input class='inputbox' style='width:200px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
        echo "</table>";

        echo "<br/><input type='submit' name='passwordsend' value='{$webText["ujjelszokerese"]}'> ";
        echo "</form>";

        echo "</div>";
    }
}
