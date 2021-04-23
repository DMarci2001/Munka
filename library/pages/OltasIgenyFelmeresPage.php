<?php


class OltasIgenyFelmeresPage extends CorePage
{
    private $successful = false;

    private $vakcinak = [
        1 => [
            "name" => "Pfizer-Biotech",
            "url"  => ""
        ],
        2 => [
            "name" => "Moderna",
            "url"  => ""
        ],
        3 => [
            "name" => "Sputnyik",
            "url"  => ""
        ],
        4 => [
            "name" => "Astra Zeneca",
            "url"  => ""
        ],
        5 => [
            "name" => "Sinopharm",
            "url"  => ""
        ],
        6 => [
            "name" => "Janessen",
            "url"  => ""
        ]
    ];
    public function __construct()
    {
        parent::__construct();

        $this->lockInPage = true;
        $this->showLangMenu = false;
        $this->showMainMenu = false;

        if (isset($_REQUEST["oltasformsavedata"])) {
            $result = ["error" => print_r($_POST, true), "html" => $this->donePage()];
            $this->utils->jsonOut($result);

            if (!isset($_POST["vedooltas"])) {
                $_POST["vedooltas"] = 0;
            }

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

            /*
            if ($result["error"] == "") {
                sql_query("insert into webservicelog set tipus=22, datum=now(), keres=?, action='covidform_new', response=?", [print_r($_POST, true), $result["html"]]);
            }
            */

            $this->utils->jsonOut($result);
        }


        if (isset($_POST["online-fogoleu-submit-button"])) {

            $webText = $this->lang->webText;
            $question_html = "";
            //Adatok ellenőrzése:

            if (isset($_POST["szuldatumev"]) && isset($_POST["szuldatumho"]) && isset($_POST["szuldatumnap"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . ($_POST["szuldatumho"] < 10 ? "0" : "") . $_POST["szuldatumho"] . "-" . ($_POST["szuldatumnap"] < 10 ? "0" : "") . $_POST["szuldatumnap"];
            }

            if (empty($_POST["nev"])) {
                $this->errors[] = "{$webText["nevkotelezo"]}";
            }

            if (empty($_POST["taj"])) {
                $this->errors[] = "{$webText["tajkotelezo"]}";
            }

            /*if (empty($_POST["telefon"])) {
                $this->errors[] = "{$webText["telkotelezo"]}";
            }*/

            if (empty($_POST["szulhely"])) {
                $this->errors[] = "{$webText["szuletesidatum"]}!";
            }

            if (empty($_POST["szuldatum"])) {
                $this->errors[] = "{$webText["szulkotelezo"]}";
            }

            if (!$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) {
                $this->errors[] = "{$webText["szulformat"]}";
            }

            if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) $this->errors[] = $webText["hibasemail"];
            if ($_POST['szuldatum'] == "0-00-00") $this->errors[] = "A születési dátum megadása kötelező!";
            if (!$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) $this->errors[] = $webText["szulformat"];
            if (!isset($_POST["gdpr"])) $this->errors[] = $webText["aszfkotelezo"];
            if (!isset($_POST["trusted-data"])) $this->errors[] = "Kérjük, járuljon hozzá, hogy kezelhessük az adatait.";
            if (!isset($_POST["responsiblity-confirmed"])) $this->errors[] = "Kérjük, erősítse meg, hogy büntetőjogi felelősségének tudatában, valós és pontos információkat adott meg!";
            $captchaError = $this->utils->checkCaptcha();
            if (!empty($captchaError)) {
                $this->errors[] = $captchaError;
            }



            $questions = [

            ];

            $questions = array(
                array("question" => "Mikor járt utoljára háziorvosánál vagy üzemorvosánál?", "answers" => array("inputs" => array("haziorvos-vizsg-ev", "haziorvos-vizsg-honap"))),
                "sick-leave" => array("question" => "Volt-e az utolsó üzemorvosi vizsgálat óta tartósan betegállományban?", "answers" => array(1 => "Igen", 0 => "Nem", "text" => "sick-leave-text")),
                "serious-health-condition" => array("question" => "Történt- e Önnel bármilyen műtét, baleset, Covid fertőzés, vagy krónikus megbetegedés?", "answers" => array(1 => "Igen", 0 => "Nem", "text" => "serious-health-condition-text")),
                "medicine-use" => array("question" => "Milyen gyógyszereket szed rendszeresen?", "answers" => array(1 => "Szedek rendszeresen gyógyszert", 0 => "Nem szedek rendszeresen gyógyszert", "text" => "medicine-use-text")),
                "treated-disease" => array("question" => "Kezelik valamilyen betegséggel?", "answers" => array(1 => "Igen", 0 => "Nem", "text" => "treated-disease-text")),
                "health-complaint" => array("question" => "Jelenleg van panasza?", "answers" => array(1 => "Igen", 0 => "Nem", "text" => "health-complaint-text")),
                array("question" => "Mennyi a jelenlegi testsúlya?", "answers" => array("weight")),
                array("question" => "Hány centiméter magas?", "answers" => array("height")),
                "eyeglasses-use" => array("question" => "Szemüveget vagy kontaktlencsét kell-e viselnie?", "answers" => array(1 => "Igen", 0 => "Nem", "options" => array("for-monitor" => "Képernyőhöz", "for-distance" => "Távolra", "for-close" => "Közelre", "eyeglasses" => "Szemüveget", "contact-lens" => "Kontaklencsét"))),
                "varicose-veins" => array("question" => "Visszerek vannak-e a lábán?", "answers" => array(1 => "Igen", 0 => "Nem")),
                "tendency-of-blood-pressure-measurement" => array("question" => "Szokta-e mérni a vérnyomását?", "answers" => array(1 => "Szoktam", 0 => "Soha", "inputs" => array("last-occasion" => array("one-week-ago" => "Egy hete", "one-month-ago" => "Egy hónapja", "three-months-ago" => "Három hónapja", "six-months-ago" => "Hat hónapja"), "present-blood-pressure-01", "present-blood-pressure-02", "present-pulse"))),
                "current-blood-pressure-measurement" => array("question" => "Most megtudja mérni vérnyomását?", "answers" => array(1 => "Igen", 0 => "Nem", "inputs" => array("present-blood-pressure-01", "present-blood-pressure-02", "present-pulse")))
            );

            $question_html .= "<p><strong>Mikor járt utoljára háziorvosánál vagy üzemorvosánál?</strong></p>";
            $question_html .= "<p>{$_POST["haziorvos-vizsg-ev"]}-{$_POST["haziorvos-vizsg-honap"]}</p><br>";

            $question_html .= "<p><strong>Volt-e az utolsó üzemorvosi vizsgálat óta tartósan betegállományban?</strong></p>";
            $question_html .= "<p>{$questions["sick-leave"]["answers"][$_POST["sick-leave"]]}</p>";
            if ($_POST["sick-leave"] == 1) {
                $question_html .= "<p>{$_POST["sick-leave-text"]}</p>";
            }

            $question_html .= "<p><strong>Történt- e Önnel bármilyen műtét, baleset, Covid fertőzés, vagy krónikus megbetegedés?</strong></p>";
            $question_html .= "<p>{$questions["serious-health-condition"]["answers"][$_POST["serious-health-condition"]]}</p>";
            if ($_POST["serious-health-condition"] == 1) {
                $question_html .= "<p>{$_POST["serious-health-condition-text"]}</p>";
            }

            $question_html .= "<p><strong>Milyen gyógyszereket szed rendszeresen?</strong></p>";
            $question_html .= "<p>{$questions["medicine-use"]["answers"][$_POST["medicine-use"]]}</p>";
            if ($_POST["medicine-use"] == 1) {
                $question_html .= "<p>{$_POST["medicine-use-text"]}</p>";
            }

            $question_html .= "<p><strong>Kezelik valamilyen betegséggel?</strong></p>";
            $question_html .= "<p>{$questions["treated-disease"]["answers"][$_POST["treated-disease"]]}</p>";
            if ($_POST["treated-disease"] == 1) {
                $question_html .= "<p>{$_POST["treated-disease-text"]}</p>";
            }

            $question_html .= "<p><strong>Jelenleg van panasza?</strong></p>";
            $question_html .= "<p>{$questions["health-complaint"]["answers"][$_POST["health-complaint"]]}</p>";
            if ($_POST["health-complaint"] == 1) {
                $question_html .= "<p>{$_POST["health-complaint-text"]}</p>";
            }

            $question_html .= "<p><strong>Mennyi a jelenlegi testsúlya?</strong></p>";
            $question_html .= "<p>{$_POST["weight"]} kg</p>";

            $question_html .= "<p><strong>Hány centiméter magas?</strong></p>";
            $question_html .= "<p>{$_POST["height"]} cm</p>";

            $question_html .= "<p><strong>Szemüveget vagy kontaktlencsét kell-e viselnie?</strong></p>";
            $question_html .= "<p>{$questions["eyeglasses-use"]["answers"][$_POST["eyeglasses-use"]]}</p>";
            if ($_POST["eyeglasses-use"] == 1) {
                foreach ($questions["eyeglasses-use"]["answers"]["options"] as $index => $value) {
                    if (isset($_POST[$index]) && $_POST[$index] == 1) {
                        $question_html .= "<p>{$value}</p>";
                    }
                }
            }

            $question_html .= "<p><strong>Visszerek vannak-e a lábán?</strong></p>";
            $question_html .= "<p>{$questions["varicose-veins"]["answers"][$_POST["varicose-veins"]]}</p>";

            $question_html .= "<p><strong>Szokta-e mérni a vérnyomását?</strong></p>";
            $question_html .= "<p>{$questions["tendency-of-blood-pressure-measurement"]["answers"][$_POST["tendency-of-blood-pressure-measurement"]]}</p>";
            if ($_POST["tendency-of-blood-pressure-measurement"] == 1) {
                $question_html .= "<p>{$questions["tendency-of-blood-pressure-measurement"]["answers"]["inputs"]["last-occasion"][$_POST["last-occasion"]]}, {$_POST["previous-blood-pressure-01"]}/{$_POST["previous-blood-pressure-02"]} vérnyomás, {$_POST["previous-pulse"]} pulzus.</p>";
            }

            $question_html .= "<p><strong>Most megtudja mérni vérnyomását?</strong></p>";
            $question_html .= "<p>{$questions["current-blood-pressure-measurement"]["answers"][$_POST["current-blood-pressure-measurement"]]}</p>";
            if ($_POST["current-blood-pressure-measurement"] == 1) {
                $question_html .= "<p>{$_POST["present-blood-pressure-01"]}/{$_POST["present-blood-pressure-02"]} vérnyomás, {$_POST["present-pulse"]} pulzus.</p>";
            }

            if (count($this->errors) == 0) {
                $variables = array($_POST["nev"], $_POST["szuldatum"], $_POST["taj"], $_POST["szulhely"], $_POST["anyjaneve"], $_SESSION["helyszindata"]["id"], $_POST["munkakor"], $_POST["neme"], $_POST["email"], $_POST["telefon"], $_POST["megj"], $question_html, $_POST["responsiblity-confirmed"], $_POST["telepone-consultation-required"], 1, rand(11000, 98000));

                sql_query("INSERT INTO foglalasok SET nev=?, szuldatum=?, taj=?,szulhely=?,anyjaneve=?,cegid=?,munkakor=?,neme=?,email=?,telefon=?,megj=?,questions=?,regdatum=now(),felelosseg_vallalas=?,visszahivastker=?,online_fogleu=?, rkod=?, aktiv=1", $variables);
                $id = sql_insert_id();

                $bookingService = new BookingService();
                $bookingService->updateFoglalasData($id);
                $this->successful = true;
            }
        }
    }

    public function showPage()
    {
        $webText = $this->lang->webText;

        if ($this->successful) {
            echo $this->successfulOnlineFogleu();
        } else {

            if (!isset($_POST["nev"])) $_POST["nev"] = "";
            if (!isset($_POST["szuldatum"])) $_POST["szuldatum"] = "";
            if (!isset($_POST["taj"])) $_POST["taj"] = "";
            if (!isset($_POST["email"])) $_POST["email"] = "";
            if (!isset($_POST["telefon"])) $_POST["telefon"] = "";

            if (isset($_POST["szuldatumev"]) && isset($_POST["szuldatumho"]) && isset($_POST["szuldatumnap"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . ($_POST["szuldatumho"] < 10 ? "0" : "") . $_POST["szuldatumho"] . "-" . ($_POST["szuldatumnap"] < 10 ? "0" : "") . $_POST["szuldatumnap"];
            }


            echo $this->displayFejlec("Oltás igény felmérés", true);

            echo $this->showErrors();

            echo "<form name='oltasform' id='oltasform' method='POST' enctype='multipart/form-data'>";

            echo "<div>Ide nem kell valami szöveg, hogy mi ez a form, miért jó kitölteni, és mi történik a kitöltés után, szóval általános tudnivalók. Vagy aki ide jön az már tudni fogja pontosan hogy miről van szó?<br/>Kérjük töltse ki az alábbi űrlapot, és válaszoljon az összes kérdésre.</div>";


            //Páciens Adatok:
            echo "<h2>Adatok</h2>";
            echo "<table cellpadding='3' cellspacing='0'>";
            echo "<tr><td>Név:</td><td><input style='width:260px' type='text' value='{$_POST["nev"]}' name='nev' id='nev'></td></tr>";
            echo "<tr><td>Születési dátum:</td><td>" . $this->utils->datumSelector($_POST["szuldatum"], "szuldatum", 0, "") . "</td></tr>";
            echo "<tr><td>TAJ:</td><td><input style='width:260px' type='text' value='{$_POST["taj"]}' name='taj' id='taj'></td></tr>";
            echo "<tr><td>E-mail: </td><td><input style='width:260px' type='text' value='{$_POST["email"]}' name='email' id='email'></td></tr>";
            echo "<tr><td>Telefonszám: </td><td><input style='width:260px' type='text' value='{$_POST["telefon"]}' name='telefon' id='telefon'></td></tr>";
            echo "</table>";

            echo "<h2>Kérdések</h2>";

            //telefonos konz
            echo "<div style='margin-top:20px;'><strong>Igényt tart-e részletes telefonos konzultációra az oltást végző orvossal mielőtt hozzájárul az oltás beadásához?</strong></div>";
            echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='telconsultation' value='1' /> IGEN</div>";
            echo "<div id='telconsultationtextdiv' style='display:none;'><textarea id='telconsultationtext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='Igen esetén kérjük adjon meg egy Önnek alkalmas idősávot mikor kollégánk keresheti'></textarea></div>";
            echo "<div><input class='oltaselement' type='radio' name='telconsultation' value='0' /> NEM</div>";

            //vakcina
            echo "<div style='margin-top:20px;'><strong>Jelenleg az alábbi oltóanyago(ka)t tudjuk biztosítani, igényt tart-e az adott vakcinára?</strong></div>";
            echo "<div style='margin-top:10px;'><input class='oltaselement' type='checkbox' name='vakcina1' value='1' /> {$this->vakcinak[1]["name"]}</div>";
            //echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina2' value='1' /> {$this->vakcinak[2]["name"]}</div>";
            //echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina3' value='1' /> {$this->vakcinak[3]["name"]}</div>";
            echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina4' value='1' /> {$this->vakcinak[4]["name"]}</div>";
            //echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina5' value='1' /> {$this->vakcinak[5]["name"]}</div>";
            //echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina6' value='1' /> {$this->vakcinak[6]["name"]}</div>";

            //allergia
            echo "<div style='margin-top:20px;'><strong>Van-e bármilyen allergiája (élelmiszer, gyógyszer, egyéb)?</strong></div>";
            echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='allergia' value='1' /> IGEN</div>";
            echo "<div id='allergiatextdiv' style='display:none;'><textarea id='allergiatext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='Igen válasz esetén kérjük részletezze'></textarea></div>";
            echo "<div><input class='oltaselement' type='radio' name='allergia' value='0' /> NEM</div>";

            //anafilaxiás reakció
            echo "<div style='margin-top:20px;'><strong>Védőoltás beadását követően volt-e anafilaxiás reakciója?</strong></div>";
            echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='anafilaxia' value='1' /> IGEN</div>";
            echo "<div id='anafilaxiatextdiv' style='display:none;'><textarea id='anafilaxiatext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='Igen válasz esetén kérjük részletezze'></textarea></div>";
            echo "<div><input class='oltaselement' type='radio' name='anafilaxia' value='0' /> NEM</div>";

            //lázas
            echo "<div style='margin-top:20px;'><strong>Volt-e lázas beteg az elmúlt 2 hétben?</strong></div>";
            echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='lazas' value='1' /> IGEN</div>";
            echo "<div><input class='oltaselement' type='radio' name='lazas' value='0' /> NEM</div>";

            //terhes
            echo "<div style='margin-top:20px;'><strong>Terhes vagy szoptat?</strong></div>";
            echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='terhes' value='1' /> IGEN</div>";
            echo "<div><input class='oltaselement' type='radio' name='terhes' value='0' /> NEM</div>";

            //krónikus betegseg
            echo "<div style='margin-top:20px;'><strong>Van-e tartós, krónikus betegsége? (cukorbetegség, magas vérnyomás, asztma, szív-, vesebetegség stb.)</strong></div>";
            echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='betegseg' value='1' /> IGEN</div>";
            echo "<div id='betegsegtextdiv' style='display:none;'><textarea id='betegsegtext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='Igen válasz esetén kérjük részletezze'></textarea></div>";
            echo "<div><input class='oltaselement' type='radio' name='betegseg' value='0' /> NEM</div>";

            //fogamzásgátlás
            echo "<div style='margin-top:20px;'><strong>Fogamzásgátlót szed-e?</strong></div>";
            echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='fogamzasgatlas' value='1' /> IGEN</div>";
            echo "<div><input class='oltaselement' type='radio' name='fogamzasgatlas' value='0' /> NEM</div>";

            //védőoltás
            echo "<div style='margin-top:20px;'><strong>Kapott-e az elmúlt 4 hétben védő oltást?</strong></div>";
            echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='vedooltas' value='1' /> IGEN</div>";
            echo "<div><input class='oltaselement' type='radio' name='vedooltas' value='0' /> NEM</div>";


            echo "<table style='margin-top:20px;'>";
            if (!isset($_POST["g-recaptcha-response"])) {
                echo "<tr><td></td><td><div class='g-recaptcha' data-callback='recaptchaCallback' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
            }
            echo "<tr><td></td><td><div style='margin-top:10px'><input type='checkbox' class='online-fogleu-element' name='gdpr' id='gdpr' value='1' ".(isset($_POST["gdpr"])?"checked":"")." />&nbsp;Az <a href='https://bejelentkezes.hungariamed.hu/images/adatvedelmi_tajekoztato_rendelesi_mozaik_v4_210325.pdf' target='_blank'>adatkezelési tájékoztató</a>t elolvastam, hozzájárulok a fenti adataim bejelentkezés céljából történő kezeléshez.</div></td></tr>";
            //echo "<tr><td></td><td><div style='margin-top:10px'><input type='checkbox' class='online-fogleu-element' name='trusted-data' value='1' ".(isset($_POST["trusted-data"])?"checked":"")." />Hozzájárulok az általam megadott egészségügyi adatok állapotfelmérés céljából történő kezeléséhez.</div></td></tr>";
            //echo "<tr><td></td><td><div style=\margin-top:10px;\><input type='checkbox' class='online-fogleu-element' name='telepone-consultation-required' " . (isset($_POST["telepone-consultation-required"]) ? "checked" : "") . "  value='1'>Szeretnék telefonos konzultációt kérni.</div></td></tr>";
            echo "<tr><td></td><td><div style=\margin-top:5px;\><input type='checkbox' class='online-fogleu-element'  name='responsiblity-confirmed'  id='responsiblity-confirmed' " . (isset($_POST["responsiblity-confirmed"]) ? "checked" : "") . " value='1'>&nbsp;Büntetőjogi felelősségem tudatában kijelentem, hogy a fent megadott információk pontosak és megfelelnek a valóságnak.</div></td></tr>";
            echo "</table>";

            echo "<div style='margin-top:30px;text-align: center;'><input type='button' name='oltas-submit-button' id='oltas-submit-button' class='newbutton' style='border:none' value='Adatok elküldése' /></div>";

            echo "</form>";
        }
    }

    private function donePage() {
        $html = "";

        $html.= "<div style='margin-top:20px;'><strong>Köszönjük a kitöltést!</strong></div>";

        return $html;
    }
}
