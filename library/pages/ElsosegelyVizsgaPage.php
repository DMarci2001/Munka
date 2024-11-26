<?php


class ElsosegelyVizsgaPage extends CorePage {

    private $answerLetters = ["", "A", "B", "C", "D", "E"];

    public function __construct()
    {
        parent::__construct();


        if (!isset($_SESSION["vizsgarandom"])) {
            $_SESSION["vizsgarandom"] = rand(1,100000000);
        }

        $this->showMainMenu = false;
        $this->showLangMenu = false;
        $this->lockInPage = true;
        $this->pageTitle = "Elsősegély teszt - ".Booking_Constants::COMPANY_NAME_SHORT;

        if (isset($_REQUEST["vizsgaformsavedata"])) {
            if (isset($_POST["cegid"])) {
                $_SESSION["elsosegelyuser"] = $_POST["cegid"];
            }

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

            if (empty($_POST["szuldatum"]) || empty($_POST["nev"]) || empty($_POST["anyjaneve"]) || empty($_POST["szulhely"]) || empty($_POST["iskolavegzettseg"]) || empty($_POST["email"]) || empty($_POST["varos"]) || empty($_POST["irsz"]) || empty($_POST["cim"])) {
                $result["error"] = "Minden mező kitöltése kötelező!";
            }


            if ($result["error"] == "") {
                sql_query("insert into vizsgavalaszok set datum=now(), cegid=?, adatok=?, osszesvalasz=?, helyesvalasz=?", [$_SESSION["elsosegelyuser"], json_encode($data), $osszesvalasz, $helyesvalasz]);
                $_SESSION["vizsgaid"] = sql_insert_id();

                unset($_SESSION["vizsgarandom"]);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_GET["elsosegelylogout"])) {
            unset($_SESSION["elsosegelyuser"]);
            header("location:index.php");
            die;
        }

        if (isset($_POST["eloginusername"])) {
            if (empty($_POST["eloginusername"]) || empty($_POST["eloginpassword"])) {
                $this->errors[] = "Kérjük adja meg a felhasználónevet és a jelszót!";
            }

            if (empty($this->errors)) {
                foreach ($this->users as $key => $user) {
                    if ($user["username"] == $_POST["eloginusername"] && $user["password"] == $_POST["eloginpassword"]) {

                        if (strtotime("now") > strtotime($user["validuntil"])) {
                            $this->errors[] = "A belépési jogosultság ehhez a fiókhoz lejárt!";
                            break;
                        }

                        if (strtotime("now") < strtotime($user["validfrom"])) {
                            $this->errors[] = "A belépési jogosultság ehhez a fiókhoz még nincs aktiválva!";
                            break;
                        }

                        $_SESSION["elsosegelyuser"] = $key;
                        header("location:index.php");
                        die;
                    }
                }

                if (!isset($_SESSION["elsosegelyuser"]) && empty($this->errors)) {
                    $this->errors[] = "Felhasználó név vagy jelszó nem megfelelő!";
                }
            }
        }

    }

    private array $users = [
        [
            "username" => "null",
            "password" => "null12345",
            "validfrom" => "2021-08-30 00:00:00",
            "validuntil" => "2025-12-31 00:00:00"
        ],
        [
            "username" => "teszt",
            "password" => "teszt2",
            "validfrom" => "2021-08-30 00:00:00",
            "validuntil" => "2022-10-22 00:00:00"
        ],
        [
            "username" => "teszt2",
            "password" => "teszt3",
            "validfrom" => "2021-08-30 00:00:00",
            "validuntil" => "2021-08-30 00:00:00"
        ],
        [
            "username" => "teszt4",
            "password" => "teszt5",
            "validfrom" => "2022-10-01 08:00:00",
            "validuntil" => "2022-10-10 00:00:00"
        ],
        [
            "username" => "esegely",
            "password" => "evizsga",
            "validfrom" => "2023-05-09 01:00:00",
            "validuntil" => "2023-05-10 23:00:00"
        ],
        [
            "username" => "cegvizsga",
            "password" => "evizsga999",
            "validfrom" => "2023-05-15 01:00:00",
            "validuntil" => "2023-05-21 23:00:00"
        ],
        [
            "username" => "evizsga",
            "password" => "evizsga445",
            "validfrom" => "2024-01-24 08:00:00",
            "validuntil" => "2024-02-15 18:00:00"
        ],
        [
            "username" => "elsosegely",
            "password" => "vizsga154",
            "validfrom" => "2024-03-18 08:00:00",
            "validuntil" => "2024-03-29 18:00:00"
        ],
        [
            "username" => "spar",
            "password" => "evizsga399",
            "validfrom" => "2024-02-06 08:00:00",
            "validuntil" => "2024-03-12 18:00:00"
        ],
        [
            "username" => "evizsga2024",
            "password" => "ev2024",
            "validfrom" => "2024-05-01 04:00:00",
            "validuntil" => "2024-06-30 22:00:00"
        ],
        [
            "username" => "egvizsga2024",
            "password" => "egv2024",
            "validfrom" => "2024-08-05 08:00:00",
            "validuntil" => "2024-08-11 20:00:00"
        ],
        [
            "username" => "ttrozsdamentes",
            "password" => "ttr2024",
            "validfrom" => "2024-11-12 08:00:00",
            "validuntil" => "2024-12-12 20:00:00"
        ],
        [
            "username" => "esv2024",
            "password" => "esv2024",
            "validfrom" => "2024-11-27 10:00:00",
            "validuntil" => "2024-11-27 14:00:00"
        ],
    ];

    private function authenticatedUser():array {
        if (isset($_SESSION["elsosegelyuser"])) {
            return $this->users[$_SESSION["elsosegelyuser"]];
        }
        return [];
    }

    public function showPage() {
        echo $this->showErrors();

        echo "<h1 style='text-align: center;'>Elsősegély vizsga</h1>";

        echo "<div id='vizsgaformdiv' style='margin:40px 40px 40px 40px;'>";

        if (!$user = $this->authenticatedUser()) {
            echo $this->loginForm();
            echo "</div>";
            return;
        }

        echo "<div style='margin-bottom:20px;text-align: center;'>Bejelentkezett cég: {$user["username"]} [<a href='index.php?elsosegelylogout'>kijelentkezés</a>]</div>";


        //$_SESSION["vizsgaid"] = 8;
        if (isset($_GET["subpage"]) && $_GET["subpage"] == "done" && isset($_SESSION["vizsgaid"])) {


            if ($vizsgaData = sql_query("select * from vizsgavalaszok where id=?", [$_SESSION["vizsgaid"]])->fetch(PDO::FETCH_ASSOC)) {
                $adatok = json_decode($vizsgaData["adatok"], JSON_OBJECT_AS_ARRAY);
                $percent = round($vizsgaData["helyesvalasz"] / ($vizsgaData["osszesvalasz"]/100));
                echo "<div style='margin-top:20px;text-align: center;'><strong>Kedves {$adatok["nev"]}, köszönjük a kitöltést!</strong></div>";
                echo "<div style='margin-top:20px;text-align: center;'>Az Ön eredménye:</div>";
                echo "<div style='margin-top:10px;text-align: center;'>{$vizsgaData["osszesvalasz"]} kérdésből, {$vizsgaData["helyesvalasz"]} válasz volt helyes ({$percent}%)</div>";

                $eredmeny = "JELES";
                if ($percent < 90) {
                    $eredmeny = "JÓ";
                }
                if ($percent < 78) {
                    $eredmeny = "KÖZEPES";
                }
                if ($percent < 66) {
                    $eredmeny = "ELÉGSÉGES";
                }
                if ($percent < 50) {
                    $eredmeny = "ELÉGTELEN";
                }

                /*
                0 - 49% elégtelen (1)
                50 - 65% elégséges (2)
                66 - 77% közepes (3)
                78 - 89% jó (4)
                90 - 100% jeles (5)
                */

                echo "<div style='margin-top:20px;text-align: center;font-size: 16px;font-weight: bold;'><span style='padding:2px 5px;border:1px solid #888;'>{$eredmeny}</span></div>";
            }


            echo "</div>";


            return;
        }


        echo "<form id='vizsgaform'>";

        echo "<input type='hidden' name='cegid' id='cegid' value='{$_SESSION["elsosegelyuser"]}'/>";
        echo "<div style='text-align: center;margin:0px 0px 20px 0px;' id='videodiv'>";
        echo "Kérjük nézze végig a következő videót, majd kattintson a vizsga indítása gombra:<br/><br/>";
        echo "<div style='margin-top:10px;'>";
        echo " <video width='100%' controls><source src='https://bejelentkezes.hungariamed.hu/presentation/First-Aid-creative.mp4' type='video/mp4'>Your browser does not support the video tag.</video>";
        echo "</div>";

        echo "<div style='margin-top:30px;text-align: center;'><a onclick='$(\"#vizsgablokk\").toggle();$(\"#videodiv\").toggle();return false;' id='covidsubmitbutton' class='newbutton' href='#'>Vizsga indítása</a></div>";

        //echo "<div style='margin-top:10px;'><a onclick='$(\"#vizsgablokk\").slideToggle();return false;' href='#'>Vizsga indítása</a></div>";
        echo "</div>";


        $_POST["szuldatum"] = null;

        echo "<div id='vizsgablokk' style='display: none;'>";
        echo "<div><strong>Adja meg az adatait:</strong></div>";

        echo "<div style='margin-top:5px;'>Név:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='nev' value='' /></div>";
        echo "<div style='margin-top:5px;'>Születési neve:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='szulnev' value='' /></div>";
        echo "<div style='margin-top:5px;'>Anyja neve:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='anyjaneve' value='' /></div>";
        echo "<div style='margin-top:5px;'>Születési dátum:</div><div style='padding-top:5px;'>" . $this->utils->datumSelector($_POST["szuldatum"], "szuldatum") . "</div>";
        echo "<div style='margin-top:5px;'>Születési helye:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='szulhely' value='' /></div>";
        //echo "<div style='margin-top:5px;'>Oktatási azonosító:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='oktatasiazonosito' value='' /></div>";
        echo "<div style='margin-top:5px;'>Legmagasabb iskolai végzettsége:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='iskolavegzettseg' value='' /></div>";
        echo "<div style='margin-top:5px;'>Adóazonosító jele:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='adoazonosito' value='' /></div>";
        echo "<div style='margin-top:5px;'>Email címe:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='email' value='' /></div>";
        echo "<div style='margin-top:20px;'><strong>Adja meg a címét:</strong></div>";
        echo "<div style='margin-top:5px;'>Város:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='varos' value='' /></div>";
        echo "<div style='margin-top:5px;'>Irányítószám:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='irsz' value='' /></div>";
        echo "<div style='margin-top:5px;'>Cím:</div><div style='padding-top:5px;'><input class='inputbox' style='width:250px;' type='text' name='cim' value='' /></div>";


        echo "<div style='margin-top:20px;'><strong>Töltse ki az alábbi tesztet:</strong></div>";

        $questions = sql_query("select * from vizsgakerdesek order by rand(?) limit 10", [$_SESSION["vizsgarandom"]])->fetchAll();
        $questionIds = [];
        foreach ($questions as $question) {
            $questionIds[] = $question["id"];
            echo "<div style='margin-top:20px;'><strong>{$question["kerdes"]}</strong></div>";

            $valasz = 1;
            while (!empty($question["valasz{$valasz}"])) {
                $valaszT = str_replace("->", "<i class='fas fa-angle-right'></i>", $question["valasz{$valasz}"]);
                echo "<div style='display: table;margin-top:5px;'>";
                echo "<div style='display: table-row;'>";
                echo "<div style='display: table-cell;vertical-align: top;'><input type='radio' name='question{$question["id"]}' id='question{$question["id"]}' value='{$valasz}' />&nbsp;</div><div style='display: table-cell;vertical-align: top;'> <label for='question{$question["id"]}'>".$this->answerLetters[$valasz].") {$valaszT}</label></div>";
                echo "</div>";
                echo "</div>";
                $valasz++;
            }

        }

        echo "<input type='hidden' name='questionids' value='".implode(",",$questionIds)."' />";

        echo "<div style='margin-top:20px;'><div style='display:table-cell;'><input name='acceptcheck' id='acceptcheck' type='checkbox' value='1' /></div><div style='display:table-cell;padding-left:10px;'><strong>Az <a href='".Booking_Constants::ADATVEDELMI_URL."' target='_blank' >Adatvédelmi tájékoztató</a> tartalmát megismertem.</strong></div>";


        echo "<div style='margin-top:30px;text-align: center;'><a onclick='elsosegelyFormSubmit();return false;' id='covidsubmitbutton' class='newbutton' href='#'>Vizsga elküldése</a></div>";

        echo "</div>";

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

    private function loginForm():string {
        $html = "";

        $html.= "<form method='post'>";
        $html.= "<div style='max-width:400px;margin:0px auto;'>";
        $html.= "<div><input style='padding:8px;width:100%;margin-top:2px;box-sizing: border-box;' placeholder='felhasználónév' type='text' name='eloginusername'></div>";
        $html.= "<div style='padding-top:10px;'><input style='padding:8px;width:100%;margin-top:2px;box-sizing: border-box;' type='password' placeholder='jelszó' name='eloginpassword' /></div>";
        $html.= "<div style='padding-top:10px;'><input style='padding:8px 0px;width:100%;box-sizing: border-box;display: inline-block;' type='submit' name='elsosegelylogintry' value='Belépés' /></div>";
        $html.= "</div>";
        $html.= "</form>";

        return $html;
    }
}

