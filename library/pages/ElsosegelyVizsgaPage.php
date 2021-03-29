<?php


class ElsosegelyVizsgaPage extends CorePage {


    public function __construct()
    {
        parent::__construct();


        if (!isset($_SESSION["vizsgarandom"])) {
            $_SESSION["vizsgarandom"] = rand(1,100000000);
        }

        $this->showMainMenu = false;
        $this->showLangMenu = false;
        $this->lockInPage = true;

        if (isset($_REQUEST["vizsgaformsavedata"])) {
            $result = ["error" => "", "html" => $this->donePage()];

            $data = $_POST;

            $_POST["szuldatum"] = "";

            if (isset($_POST["szuldatumev"])) {
                $datum = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            }

            if ($this->utils->validateDate($datum, "Y-m-d")) {
                $_POST["szuldatum"] = $datum;
            }

            if (!isset($_POST["acceptcheck"])) {
                $result["error"] = "Kérjük fogadja el a adatvédelmi tájékoztatót!";
            }

            $osszesvalasz = $helyesvalasz = 0;
            foreach (explode(",", $_POST["questionids"]) as $questionid) {
                $osszesvalasz++;


                if (!isset($_POST["question{$questionid}"])) {
                    $result["error"] = "Kérjük válaszoljon az összes kérdésre!";
                } else {
                    $questionData = sql_query("select * from vizsgakerdesek where id=?", [$questionid])->fetch();
                    if ($questionData["helyesvalasz"] == $_POST["question{$questionid}"]) {
                        $helyesvalasz++;
                    }
                }
            }

            if (empty($_POST["szuldatum"]) || empty($_POST["nev"]) || empty($_POST["anyjaneve"]) || empty($_POST["szulhely"]) || empty($_POST["oktatasiazonosito"]) || empty($_POST["iskolavegzettseg"]) || empty($_POST["adoazonosito"]) || empty($_POST["email"]) || empty($_POST["varos"]) || empty($_POST["irsz"]) || empty($_POST["cim"])) {
                $result["error"] = "Minden mező kitöltése kötelező!";
            }


            if ($result["error"] == "") {
                sql_query("insert into vizsgavalaszok set datum=now(), adatok=?, osszesvalasz=?, helyesvalasz=?", [json_encode($data), $osszesvalasz, $helyesvalasz]);
                unset($_SESSION["vizsgarandom"]);
            }

            $this->utils->jsonOut($result);
        }

    }

    public function showPage() {
        $webText = $this->lang->webText;


         echo $this->showFormErrors();



        echo "<h1 style='text-align: center;'>Elsősegély vizsga</h1>";
        echo "<div style='text-align: center;'>Kérjük töltse ki az alábbi formot!</div>";

        echo "<div id='vizsgaformdiv' style='max-width:800px;margin:40px auto 40px auto;'>";
        echo "<form id='vizsgaform'>";



        $_POST["szuldatum"] = null;

        echo "<div><strong>Kérjük adja meg az adatait:</strong></div>";

        echo "<div style='margin-top:5px;'>Név:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='nev' value='' /></div>";
        echo "<div style='margin-top:5px;'>Születési neve:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='szulnev' value='' /></div>";
        echo "<div style='margin-top:5px;'>Anyja neve:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='anyjaneve' value='' /></div>";
        echo "<div style='margin-top:5px;'>Születési dátum:</div><div style='padding-top:5px;'>" . $this->utils->datumSelector($_POST["szuldatum"], "szuldatum") . "</div>";
        echo "<div style='margin-top:5px;'>Születési helye:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='szulhely' value='' /></div>";
        echo "<div style='margin-top:5px;'>Oktatási azonosító:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='oktatasiazonosito' value='' /></div>";
        echo "<div style='margin-top:5px;'>Legmagasabb iskolai végzettsége:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='iskolavegzettseg' value='' /></div>";
        //echo "<div style='margin-top:5px;'>Adóazonosító jele:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='adoazonosito' value='' /></div>";
        echo "<div style='margin-top:5px;'>Email címe:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='email' value='' /></div>";
        echo "<div style='margin-top:20px;'><strong>Kérjük adja meg a címét:</strong></div>";
        echo "<div style='margin-top:5px;'>Város:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='varos' value='' /></div>";
        echo "<div style='margin-top:5px;'>Irányítószám:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='irsz' value='' /></div>";
        echo "<div style='margin-top:5px;'>Cím:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='cim' value='' /></div>";


        echo "<div style='margin-top:20px;'><strong>Töltse ki az alábbi tesztet:</strong></div>";

        $questions = sql_query("select * from vizsgakerdesek order by rand(?)", [$_SESSION["vizsgarandom"]])->fetchAll();
        $questionIds = [];
        foreach ($questions as $question) {
            $questionIds[] = $question["id"];
            echo "<div style='margin-top:20px;'><strong>{$question["kerdes"]}</strong></div>";

            $valasz = 1;
            while (!empty($question["valasz{$valasz}"])) {
                echo "<div><input type='radio' name='question{$question["id"]}' value='{$valasz}' /> {$question["valasz{$valasz}"]}</div>";
                $valasz++;
            }

        }

        echo "<input type='hidden' name='questionids' value='".implode(",",$questionIds)."' />";

        echo "<div style='margin-top:20px;'><div style='display:table-cell;'><input name='acceptcheck' id='acceptcheck' type='checkbox' value='1' /></div><div style='display:table-cell;padding-left:10px;'><strong>Az <a href='".Booking_Constants::ADATVEDELMI_URL."' target='_blank' >Adatvédelmi tájékoztató</a> tartalmát megismertem.</strong></div>";


        echo "<div style='margin-top:30px;text-align: center;'><a onclick='elsosegelyFormSubmit();return false;' id='covidsubmitbutton' class='newbutton' href='#'>Vizsga elküldése</a></div>";

        echo "</form>";
        echo "</div>";
    }


    private function donePage()
    {
        $html = "";

        //$html .= "<div style='padding:10px;background-color:{$warnColor};color:{$warnTextColor};font-size: 18px;'><strong>{$warn}</strong></div>";
        $html .= "<div style='margin-top:20px;'><strong>Köszönjük a kitöltést!</strong></div>";


        return $html;
    }
}

