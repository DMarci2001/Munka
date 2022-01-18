<?php

class AutoertesitesleiratkozasPage extends CorePage {
    private $bookingService;
    private $zeusService;

    public function __construct()
    {
        parent::__construct();

        $this->bookingService = new BookingService();
        $this->zeusService    = new ZeusService();
        $this->data = [];

        if (isset($_POST["sikeres"])) {
            unset($_POST["sikeres"]);
        }

        if (!$this->data = $this->zeusService->getPaciensByMask($_GET["mid"])) {
            header("location:index.php");
            die;
        }

        if(isset($_POST["unsub-notification"])){
            $this->zeusService->sql_query("UPDATE paciensek SET stop_notification=1 WHERE id=?",array($this->data["id"]));
            $this->zeusService->sql_query("INSERT INTO leiratkozok_az_ertesitesrol SET pid=?,datum=?",array($this->data["id"],date("Y-m-d H:i:s")));
            $_POST["sikeres"]=true;
        }

    }

    public function showPage() {
        $utils = new Utils();

        echo $this->displayFejlec("HungáriaMed-M - Automata értesítés alkalmassági vizsgálat esedékességéről", true);

        if (isset($_POST["sikeres"])) {
            echo $this->sikeresRogzites();
            return;
        }

        echo $this->showErrors();
        echo $this->showPageDescription("Az alábbi beállítással tudunk küldeni az Ön részére egy emlékeztető üzenetet, hogy időben megjelenhessen az éves vizsgálatán!");
        echo "<div>";
        echo "<form method='POST' id='unsubscribe-from-auto-notification'>";
	    echo "<div style=\"font-size:16px;font-weight:bold\">Valóban leiratkozik a az automata értesítési rendszerről?</div>";
        echo "<div style='padding-top:10px;'><input type=\"submit\" style=\"border:none\" class='newbutton' name=\"unsub-notification\" value=\"Leiratkozom\"></div>";
        echo "</form>";
        echo "</div>";
    }


    private function sikeresRogzites() {
        $html = "";
        $html.="<div style='font-weight: bold;'>Sikeres leiratkozás!</div>";
        $html.="<div>";

        $html.="<p>Köszönjük, hogy segítette a munkánkat!</p>";
        $html.="<p>Üdvözlettel:<br>Hungária Med-M Csapata!<p>";
        $html.="<a href='index.php'>Vissza a bejelentkezéshez</a>";

        $html.="</div>";
        return $html;
    }

    private function generateRandomString($length = 32) {
        return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
    }
    

}