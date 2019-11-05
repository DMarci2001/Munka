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

        if (!$data = $this->zeusService->sql_fetch_array($this->zeusService->sql_query("select * from paciensek where mask=?", array($_GET["mid"])))) {
            die("error1");
        }

        if (!$vizsgalatilapData = $this->zeusService->sql_fetch_array($this->zeusService->sql_query("SELECT * FROM vizsgalatilapok WHERE vizsgalatid=16 AND pid=?", array($data["id"])))) {
            die("error2");
        }

        if (isset($_POST["lejarat"])) {
            //....


        }
    }

    public function showPage() {
        $utils = new Utils();

        echo $this->displayFejlec("HungáriaMed-M - Alkalmassági lejárat adatbekérés", true);

        if (isset($_GET["sikeres"])) {
            echo $this->sikeresRogzites();
            return;
        }

        echo $this->showFormErrors();
        echo $this->showPageDescription("Az alábbi információ megadásával, tudunk küldeni az ön részére egy emlékeztető üzenetet, hogy időben megjelenhessen az éves vizsgálatán!");

        echo "<div>";
        echo "<form method='post' id='fitness-expiry-request'>";
	    echo "<div>Alkalmassági vizsgálat érvényessége (-ig):</div>";
	    echo "<div style='padding-top:10px;'>".$utils->datumSelector($_POST["lejarat"],"lejarat", 5)."</div>";
        echo "<div style='padding-top:10px;' class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div>";
        echo "<div style='padding-top:10px;'><a href='#' class='newbutton' onclick='checkData();return false;'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Küldés&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</a></div>";
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