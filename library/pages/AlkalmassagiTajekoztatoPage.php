<?php

class AlkalmassagiTajekoztatoPage extends CorePage {
    private $bookingService;
    private $zeusService;

    public function __construct()
    {
        parent::__construct();

        $this->bookingService = new BookingService();
        $this->zeusService    = new ZeusService();

        if (isset($_GET["sikeres"])) {
            return;
        }

        if (!$data = $this->zeusService->getPaciensByMask($_GET["mid"])) {
            header("location:index.php");
            die;
        }

        if ($vizsgalatilapData = $this->zeusService->getVizsgalatiLapByPaciens($data["id"])) {
            header("location:index.php");
            die;
        }

        if (isset($_POST["lejaratev"])) {
            $_POST["lejarat"] = $_POST["lejaratev"] . "-" . substr("00" . $_POST["lejaratho"], -2) . "-" . substr("00" . $_POST["lejaratnap"], -2);

            if (date("Y-m-d", strtotime($_POST["lejarat"])) != $_POST["lejarat"]) {
                $this->errors[] = "A megadott lejárati dátum helytelen!";
            }
            if (strtotime($_POST["lejarat"]) < strtotime("Now - 5 years") && empty($this->errors)) {
                $this->errors[] = "A megadott lejárat túl régi!";
            }

            if (isset($_POST["g-recaptcha-response"])) {
                $captcha = $_POST["g-recaptcha-response"];
            }

            $captchaResult = $this->utils->checkCaptcha();
            if (!empty($captchaResult)) {
                $this->errors[] = $captchaResult;
            }

            if (empty($this->errors)) {
                $this->zeusService->addLejaratiIdo($data["id"], $_POST["lejarat"]);
                header("location:index.php?page={$_GET["page"]}&sikeres");
                die;
            }
        }
    }

    public function showPage() {
        $utils = new Utils();

        echo $this->displayFejlec("HungáriaMed-M - Alkalmassági lejárat adatbekérés", true);

        if (isset($_GET["sikeres"])) {
            echo $this->sikeresRogzites();
            return;
        }

        echo $this->showErrors();
        echo $this->showPageDescription("Az alábbi információ megadásával, tudunk küldeni az ön részére egy emlékeztető üzenetet, hogy időben megjelenhessen az éves vizsgálatán!");

        echo "<div>";
        echo "<form method='post' id='fitness-expiry-request'>";
	    echo "<div>Alkalmassági vizsgálat érvényessége (-ig):</div>";
	    echo "<div style='padding-top:10px;'>".$utils->datumSelector($_POST["lejarat"],"lejarat", 5)."</div>";
        echo "<div style='padding-top:10px;' class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div>";
        echo "<div style='padding-top:10px;'><a href='#' class='newbutton' onclick=\"$('#fitness-expiry-request').submit();return false;\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Küldés&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</a></div>";
        echo "</form>";
        echo "</div>";
    }


    private function sikeresRogzites() {
        $html = "";
        $html.="<div style='font-weight: bold;'>Sikeres rögzítés!</div>";
        $html.="<div>";

        $html.="<p>Köszönjük, hogy segítette a munkánkat!</p>";
        $html.="<p>Üdvözlettel:<br>Hungária Med-M Csapata!<p>";
        $html.="<a href='index.php'>Vissza a bejelentkezéshez</a>";

        $html.="</div>";
        return $html;
    }

}