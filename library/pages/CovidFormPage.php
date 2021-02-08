<?php

class CovidFormPage extends CorePage {

    public function __construct()
    {
        parent::__construct();

        $this->showMainMenu = false;
        $this->showLangMenu = false;
        $this->lockInPage   = true;

        if (isset($_REQUEST["covidformsavedata"])) {
            $result = ["error" => "", "html" => $this->donePage()];

            $_POST["szuldatum"] = "";

            if (isset($_POST["szuldatumev"])) {
                $datum = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            }

            if ($this->utils->validateDate($datum, "Y-m-d")) {
                $_POST["datum"] = $datum;
            }

            if (empty($_POST["datum"]) || empty($_POST["nev"]) || empty($_POST["taj"])) {
                $result["error"] = "Minden mező kitöltése kötelező!";
            }

            if ($result["error"] == "") {
                sql_query("insert into webservicelog set tipus=22, datum=now(), keres=?, action='covidform_new', response=?", [print_r($_POST, true), $result["html"]]);
            }

            $this->utils->jsonOut($result);
        }

    }

    public function showPage() {
        if (!isset($_POST["email"])) {
            $_POST["taj"]=$_POST["email"]=$_POST["jelszo"]="";
        }

        //echo $this->displayFejlec("FORM", true);

        echo $this->showFormErrors();

        if (isset($_GET["passwordsent"])) {
            echo $this->formMessage("Az új jelszavát a megadott e-mail címre elküldtük.");
        }

        echo "<h1 style='text-align: center;'>Páciens nyilatkozat</h1>";
        echo "<div style='text-align: center;'>Visitors declaration<br/>HATÁLYOS: 2020.10.27</div>";

        //echo "<form id='covidform'style='max-width:800px;margin:40px auto 40px auto;'>";
        echo "<div id='covidformdiv' style='max-width:800px;margin:40px auto 40px auto;'>";
        echo "<form id='covidform'>";

        echo "<div><strong>A rendelőbe érkezése előtti 14 napban járt e külföldön?</strong><br/>Have you travelled, visited or transited via airports in other country within 14 days before visiting medical examination rooms?</div>";

        echo "<div><input class='covidelement' type='radio' name='travel' value='1' /> IGEN (YES)</div>";
        echo "<div id='traveltextdiv' style='display:none;'><textarea id='traveltext' class='inputbox' style='width:90%;' placeholder='Igen válasz esetén fejtse ki bővebben'></textarea></div>";
        echo "<div><input class='covidelement' type='radio' name='travel' value='0' /> NEM (NO)</div>";

        echo "<div style='margin-top:20px;'><strong>Az elmúlt 14 nap folyamán kapcsolatban állt-e (otthon/munkahelyen/családban), vagy gondoskodott-e olyan személyekről, aki lázas, akiknél az új koronavírust diagnosztizáltak, vagy találkozott-e olyan személyekkel, akiket jelenleg egészségügyi megfigyelés alatt tartanak?</strong><br/>Have you been in close contact with or did you provide care to anyone diagnosed as having Coronavirus, or someone who is currently subject to health monitoring for possible exposure to Novel Coronavirus, or who has fever within the last 14 days?</div>";

        echo "<div><input class='covidelement' type='radio' name='kapcs' value='1' /> IGEN (YES)</div>";
        echo "<div id='kapcstextdiv' style='display:none;'><textarea id='kapcstext' class='inputbox' style='width:90%;' placeholder='Igen válasz esetén fejtse ki bővebben'></textarea></div>";
        echo "<div><input class='covidelement' type='radio' name='kapcs' value='0' /> NEM (NO)</div>";

        echo "<div style='margin-top:20px;'><strong>Jelenleg, vagy az elmúlt 10 napban észlelte-e magán az alábbi tüneteket:</strong><br/>Do You have have any symptoms from the following - now or in the last 10 days:</div>";

        echo "<ul>";

        echo "<li>köhögés (caughing)";
        echo "<div><input class='covidelement' type='radio' name='caugh' value='1' /> IGEN (YES)</div>";
        echo "<div><input class='covidelement' type='radio' name='caugh' value='0' /> NEM (NO)</div>";
        echo "</li>";

        echo "<li style='padding-top:5px;'>orrfolyás (runny nose)";
        echo "<div><input class='covidelement' type='radio' name='runnynose' value='1' /> IGEN (YES)</div>";
        echo "<div><input class='covidelement' type='radio' name='runnynose' value='0' /> NEM (NO)</div>";
        echo "</li>";

        echo "<li style='padding-top:5px;'>láz (fever)";
        echo "<div><input class='covidelement' type='radio' name='fever' value='1' /> IGEN (YES)</div>";
        echo "<div><input class='covidelement' type='radio' name='fever' value='0' /> NEM (NO)</div>";
        echo "</li>";

        echo "<li style='padding-top:5px;'>szaglás- vagy ízérzésvesztés (loss of taste or smell)";
        echo "<div><input class='covidelement' type='radio' name='smell' value='1' /> IGEN (YES)</div>";
        echo "<div><input class='covidelement' type='radio' name='smell' value='0' /> NEM (NO)</div>";
        echo "</li>";

        echo "</ul>";

        $_POST["szuldatum"] = null;

        echo "<div style='margin-top:20px;'><strong>Kérjük adja meg az adatait:</strong></div>";

        echo "<div style='margin-top:10px;'>Taj szám:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='taj' value='' /></div>";
        echo "<div style='margin-top:5px;'>Név:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='nev' value='' /></div>";
        echo "<div style='margin-top:5px;'>Születési dátum:</div><div style='padding-top:5px;'>".$this->utils->datumSelector($_POST["szuldatum"], "szuldatum")."</div>";


        echo "<div style='margin-top:20px;'><div style='display:table-cell;'><input class='covidelement' name='acceptcheck' id='acceptcheck' type='checkbox' value='1' /></div><div style='display:table-cell;padding-left:10px;'><strong>büntetőjogi felelősségem tudatában kijelentem, hogy a fent megadott információk pontosak és megfelelnek a valóságnak.</strong></div>";


        echo "<div style='margin-top:30px;text-align: center;'><a onclick='covidFormSubmit();return false;' id='covidsubmitbutton' class='newbutton' href='#' style='opacity: .3;'>Adatok elküldése</a></div>";

        echo "</form>";
        echo "</div>";

        //$_POST["caugh"] = 1;
        //$_POST["runnynose"] = 1;
        //echo $this->donePage();
    }


    private function donePage() {
        $html = "";

        $covidNum = 0;
        $covidLaz = 0;

        $warn = "Kérjük, fáradjon beljebb!";
        $warnColor = "#44d362;";
        $warnTextColor = "#fff";

        if ($_POST["caugh"] == 1) {
            $covidNum++;
        }
        if ($_POST["runnynose"] == 1) {
            $covidNum++;
        }
        if ($_POST["fever"] == 1) {
            $covidNum++;
            $covidLaz++;
        }
        if ($_POST["smell"] == 1) {
            $covidNum++;
        }
        if ($_POST["travel"] == 1) {
            $covidNum++;
        }
        if ($_POST["kapcs"] == 1) {
            $covidNum++;
        }

        if ($covidNum == 1 && $covidLaz == 0) {
            $warn = "Kovid? Kérjük, fáradjon a recepcióhoz!";
            $warnColor = "#fdfd96";
            $warnTextColor = "#444";
        }

        if ($covidNum > 1 || $covidLaz == 1) {
            $warn = "Sajnáljuk, a bentlévők egészségének védelme érdekében most nem tudjuk Önt fogadni.";
            $warnColor = "#ff6961";
            $warnTextColor = "#fff";
        }


        $html.= "<div style='padding:10px;background-color:{$warnColor};color:{$warnTextColor};font-size: 18px;'><strong>{$warn}</strong></div>";
        $html.= "<div style='margin-top:20px;'><strong>Köszönjük a kitöltést!</strong></div>";



        return $html;
    }
}

