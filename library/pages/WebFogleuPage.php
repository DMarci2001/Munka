<?php


class WebFogleuPage extends CorePage
{
    private $successful = false;

    public function __construct()
    {
        parent::__construct();


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

            if (empty($_POST["munkakor"])) {
                $this->errors[] = "{$webText["munkakorkotelezo"]}";
            }

            if (empty($_POST["anyjaneve"])) {
                $this->errors[] = "{$webText["anyjaneve"]}!";
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
            if (!isset($_POST["szulhely"])) $_POST["szulhely"] = "";
            if (!isset($_POST["anyjaneve"])) $_POST["anyjaneve"] = "";
            if (!isset($_POST["irsz"])) $_POST["irsz"] = "";
            if (!isset($_POST["varos"])) $_POST["varos"] = "";
            if (!isset($_POST["utca"])) $_POST["utca"] = "";
            if (!isset($_POST["munkakor"])) $_POST["munkakor"] = "";
            if (!isset($_POST["neme"])) $_POST["neme"] = "";
            if (!isset($_POST["email"])) $_POST["email"] = "";
            if (!isset($_POST["telefon"])) $_POST["telefon"] = "";

            if (isset($_POST["szuldatumev"]) && isset($_POST["szuldatumho"]) && isset($_POST["szuldatumnap"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . ($_POST["szuldatumho"] < 10 ? "0" : "") . $_POST["szuldatumho"] . "-" . ($_POST["szuldatumnap"] < 10 ? "0" : "") . $_POST["szuldatumnap"];
            }



            $title = $_SESSION["helyszindata"]["megnev"] . " - Online Foglalkozás-egészségügyi vizsgálat";

            echo $this->displayFejlec($title, true);
            echo $this->showErrors();

            /*
        Szükséges adatok:
        Taj*
        szülhely
        szuldatum
        anyjaneve
        lakcím


        neme 3 opcionális Férfi - Nő - Nem adom meg
        Telefon/email/ opcionális

        e-mail szöveg figyelmezetés ha nem adja meg milesz hogyan tud tájékozódni.

        */

            echo "<form name=\"online-fogleu-form\" id=\"online-fogleu-form\" method=\"POST\" enctype=\"multipart/form-data\"><table cellpadding=\"3\" cellspacing=\"0\">";
            //Páciens Adatok:
            echo "<tr>";
            echo "  <td>Teljesnév: *</td><td><input class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"width:260px\" type=\"text\" value=\"{$_POST["nev"]}\" name=\"nev\"></td>";
            echo "</tr>";

            echo "<tr>";
            echo "  <td>Születési dátum: *</td><td>" . $this->utils->datumSelector($_POST["szuldatum"], "szuldatum", 0, "class='design-put' style='padding:3px;width:auto' onchange='checkFogleuForm();' ") . "</td>";
            echo "</tr>";

            echo "<tr>";
            echo "  <td>TAJ: *</td><td><input class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"width:260px\" type=\"text\" value=\"{$_POST["taj"]}\" name=\"taj\"></td>";
            echo "</tr>";

            echo "<tr>";
            echo "  <td>Születési hely: *</td><td><input class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"width:260px\" type=\"text\" value=\"{$_POST["szulhely"]}\" name=\"szulhely\"></td>";
            echo "</tr>";

            echo "<tr>";
            echo "  <td>Anyja neve: *</td><td><input class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"width:260px\" type=\"text\" value=\"{$_POST["anyjaneve"]}\" name=\"anyjaneve\"></td>";
            echo "</tr>";

            echo "<tr>";
            echo "  <td>Munkakör: *</td><td><input class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"width:260px\" type=\"text\" value=\"{$_POST["munkakor"]}\" name=\"munkakor\"></td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td>Neme: </td>";
            echo "<td>";
            echo "  <input type=\"radio\" value=\"1\" " . ($_POST["neme"] == 1 ? "checked" : "") . " name=\"neme\">&nbsp;Férfi&nbsp;&nbsp;";
            echo "  <input type=\"radio\" value=\"2\" " . ($_POST["neme"] == 2 ? "checked" : "") . " name=\"neme\">&nbsp;Nő&nbsp;&nbsp;";
            echo "  <input type=\"radio\" value=\"0\" " . ($_POST["neme"] == 0 ? "checked" : "") . " name=\"neme\">&nbsp;Nem adom meg";
            echo "</td>";
            echo "</tr>";

            echo "<tr>";
            echo "  <td>E-mail: </td><td><input class=\"design-put\" style=\"width:260px\" type=\"text\" value=\"{$_POST["email"]}\" name=\"email\"></td>";
            echo "</tr>";

            echo "<tr>";
            echo "  <td>Telefonszám: </td><td><input class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"width:260px\" type=\"text\" value=\"{$_POST["telefon"]}\" name=\"telefon\"></td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td></td>";
            echo "<td style=\"font-size:12px\">Amennyiben nem kívánja megadni az e-mail címét, telefonszámát további tájékoztatás érdekében a vizsgálata eredményéről, úgy a telefonos ügyfélszolgálatunkon kérhet tájékoztatást.</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td>Megjegyzés: </td><td><textarea class=\"design-put\" style=\"height:100px;width:400px;\" name=\"megj\" id=\"foglmegj\"></textarea></td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td></td>";
            echo "<td style=\"font-size:12px\">A vizsgálat megkezdéséhez szükséges feltölteni az Ön cégétől kapott orvosi beutaló dokumentumot, az alábbi gomb segítségével.</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td></td>";
            echo "<td><div class=\"upload-btn-wrapper\" style=\"cursor:pointer\"><a href=\"#\" class=\"upbtn newbutton\">{$webText["beutalofeltoltese"]}</a><input type=\"file\" id=\"paciensfile\" name=\"paciensfile[]\" multiple /></div><img id=\"paciensloader\" style=\"display:none;opacity:.5;height:30px;margin-left:10px;\" src=\"/images/loading.svg\" /></td>";
            echo "</tr>";
            echo "<tr><td></td><td><div id='paciensfilediv'>" . $this->utils->showPaciensFiles() . "</div></td></tr>";

            echo "</table>";






            echo "<h2>Kérdések</h2>";
            echo "<table style=\"width:100%\">";

            //Kérdés:
            echo "<tr><td><strong>1. Mikor járt utoljára háziorvosánál vagy üzemorvosánál?</strong></td></tr>";
            //Válasz:
            echo "<tr><td>";
            //Év:
            echo "  <select class=\"design-put\" style=\"padding:3px;width:auto\" name=\"haziorvos-vizsg-ev\">";
            $i = 0;
            do {
                echo $i;
                $year = date("Y", strtotime("NOW - {$i} years"));
                echo "<option value=\"{$year}\">{$year}</option>";
                $i++;
                //10 évet enged vissza
            } while ($i <= 9);
            echo "  </select>&nbsp;";

            //Hónap:
            echo "  <select class=\"design-put\" style=\"padding:3px;width:auto\" name=\"haziorvos-vizsg-honap\">";
            for ($i = 1; $i <= 12; $i++) {
                echo "<option value=\"{$i}\">" . ucfirst($webText["honaptext"][$i]) . "</option>";
            }
            echo "  </select>";
            echo "</td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";

            //Kérdés:
            echo "<tr><td><strong>2. Volt-e az utolsó üzemorvosi vizsgálat óta tartósan betegállományban?</strong></td></tr>";
            //Válasz:
            echo "<tr><td>";
            echo "<div><input class=\"online-fogleu-element\" type=\"radio\" value=\"1\" " . ($_POST["sick-leave"] == 1 ? "checked" : "") . " name=\"sick-leave\">&nbsp;Igen</div>";
            echo "<div id=\"sick-leave-textdiv\" " . ($_POST["sick-leave"] == 1 ? "" : "style=\"display:none;\"") . " >";
            echo "<textarea id=\"sick-leave-text\" name=\"sick-leave-text\" class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"font-size:12px;width:625px;height:120px\" placeholder=\"Igen válasz esetén fejtse ki bővebben\">{$_POST["sick-leave-text"]}</textarea>";
            echo "</div>";
            echo "<input class=\"online-fogleu-element\" type=\"radio\" value=\"0\" " . ($_POST["sick-leave"] == 0 ? "checked" : "") . " name=\"sick-leave\">&nbsp;Nem";
            echo "</td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";

            //Kérdés:
            echo "<tr><td><strong>3. Történt- e Önnel bármilyen műtét, baleset, Covid fertőzés, vagy krónikus megbetegedés?</strong></td></tr>";
            //Válasz:
            echo "<tr><td>";
            echo "<div><input class=\"online-fogleu-element\" type=\"radio\" value=\"1\" " . ($_POST["serious-health-condition"] == 1 ? "checked" : "") . " name=\"serious-health-condition\">&nbsp;Igen</div>";
            echo "<div id=\"serious-health-condition-textdiv\" " . ($_POST["serious-health-condition"] == 1 ? "" : "style=\"display:none;\"") . " >";
            echo "<textarea id=\"serious-health-condition-text\" name=\"serious-health-condition-text\" class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"font-size:12px;width:625px;height:120px\" placeholder=\"Igen válasz esetén fejtse ki bővebben\">{$_POST["serious-health-condition-text"]}</textarea>";
            echo "</div>";
            echo "<input class=\"online-fogleu-element\" type=\"radio\" value=\"0\" " . ($_POST["serious-health-condition"] == 0 ? "checked" : "") . " name=\"serious-health-condition\">&nbsp;Nem";
            echo "</td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";

            //Kérdés:
            echo "<tr><td><strong>4. Milyen gyógyszereket szed rendszeresen?</strong></td></tr>";
            //Válasz:
            echo "<tr><td>";
            echo "<div><input class=\"online-fogleu-element\" type=\"radio\" value=\"1\" " . ($_POST["medicine-use"] == 1 ? "checked" : "") . " name=\"medicine-use\">&nbsp;Szedek rendszeresen gyógyszert</div>";
            echo "<div id=\"medicine-use-textdiv\" " . ($_POST["medicine-use"] == 1 ? "" : "style=\"display:none;\"") . " >";
            echo "<textarea id=\"medicine-use-text\" name=\"medicine-use-text\" class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"font-size:12px;width:625px;height:120px\" placeholder=\"Igen válasz esetén fejtse ki bővebben\">{$_POST["medicine-use-text"]}</textarea>";
            echo "</div>";
            echo "<input class=\"online-fogleu-element\" type=\"radio\" value=\"0\" " . ($_POST["medicine-use"] == 0 ? "checked" : "") . " name=\"medicine-use\">&nbsp;Nem szedek rendszeresen gyógyszert";
            echo "</td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";

            //Kérdés:
            echo "<tr><td><strong>5. Kezelik valamilyen betegséggel?</strong></td></tr>";
            //Válasz:
            echo "<tr><td>";
            echo "<div><input class=\"online-fogleu-element\" type=\"radio\" value=\"1\" " . ($_POST["treated-disease"] == 1 ? "checked" : "") . " name=\"treated-disease\">&nbsp;Igen kezelnek</div>";
            echo "<div id=\"treated-disease-textdiv\" " . ($_POST["treated-disease"] == 1 ? "" : "style=\"display:none;\"") . ">";
            echo "<textarea id=\"treated-disease-text\" name=\"treated-disease-text\" class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"font-size:12px;width:625px;height:120px\" placeholder=\"Igen válasz esetén fejtse ki bővebben\">{$_POST["treated-disease-text"]}</textarea>";
            echo "</div>";
            echo "<input class=\"online-fogleu-element\" type=\"radio\" value=\"0\" " . ($_POST["treated-disease"] == 0 ? "checked" : "") . " name=\"treated-disease\">&nbsp;Nem kezelnek";
            echo "</td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";


            //Kérdés:
            echo "<tr><td><strong>6. Jelenleg van panasza?</strong></td></tr>";
            //Válasz:
            echo "<tr><td>";
            echo "<div><input class=\"online-fogleu-element\" type=\"radio\" value=\"1\" " . ($_POST["health-complaint"] == 1 ? "checked" : "") . " name=\"health-complaint\">&nbsp;Igen</div>";
            echo "<div id=\"health-complaint-textdiv\" " . ($_POST["health-complaint"] == 1 ? "" : "style=\"display:none;\"") . " >";
            echo "<textarea id=\"health-complaint-text\" name=\"health-complaint-text\" class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"font-size:12px;width:625px;height:120px\" placeholder=\"Igen válasz esetén fejtse ki bővebben\">{$_POST["health-complaint-text"]}</textarea>";
            echo "</div>";
            echo "<input class=\"online-fogleu-element\" type=\"radio\" value=\"0\" " . ($_POST["health-complaint"] == 0 ? "checked" : "") . " name=\"health-complaint\">&nbsp;Nem";
            echo "</td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";


            //Kérdés:
            echo "<tr><td><strong>7. Mennyi a jelenlegi testsúlya?</strong></td></tr>";
            //Válasz:
            echo "<tr><td><div><input class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"width:60px;padding:3px\" type=\"number\" name=\"weight\" value=\"{$_POST["weight"]}\">&nbsp;&nbsp;kg</div></td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";


            //Kérdés:
            echo "<tr><td><strong>8. Hány centiméter magas?</strong></td></tr>";
            //Válasz:
            echo "<tr><td><div><input class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"width:60px;padding:3px\" type=\"number\" name=\"height\" value=\"{$_POST["height"]}\">&nbsp;&nbsp;cm</div></td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";


            //Kérdés:
            echo "<tr><td><strong>9. Szemüveget vagy kontaktlencsét kell-e viselnie?</strong></td></tr>";
            //Válasz:
            echo "<tr><td>";
            echo "<div><input class=\"online-fogleu-element\" type=\"radio\" value=\"1\" " . ($_POST["eyeglasses-use"] == 1 ? "checked" : "") . " name=\"eyeglasses-use\">&nbsp;Igen</div>";
            echo "<div id=\"eyeglasses-use-textdiv\" " . ($_POST["eyeglasses-use"] == 1 ? "" : "style=\"display:none;\"") . " >";
            echo "<div>&nbsp;&nbsp;<input type=\"checkbox\" " . ($_POST["for-monitor"] == 1 ? "checked" : "") . " class=\"online-fogleu-element\" name=\"for-monitor\" value=\"1\">&nbsp;&nbsp;Képernyőhöz</div>";
            echo "<div>&nbsp;&nbsp;<input type=\"checkbox\" " . ($_POST["for-distance"] == 1 ? "checked" : "") . " class=\"online-fogleu-element\" name=\"for-distance\" value=\"1\">&nbsp;&nbsp;Távolra</div>";
            echo "<div>&nbsp;&nbsp;<input type=\"checkbox\" " . ($_POST["for-close"] == 1 ? "checked" : "") . " class=\"online-fogleu-element\" name=\"for-close\" value=\"1\">&nbsp;&nbsp;Közelre</div>";
            echo "<div>&nbsp;&nbsp;<input type=\"checkbox\" " . ($_POST["eyeglasses"] == 1 ? "checked" : "") . " class=\"online-fogleu-element\" name=\"eyeglasses\" value=\"1\">&nbsp;&nbsp;Szemüveget</div>";
            echo "<div>&nbsp;&nbsp;<input type=\"checkbox\" " . ($_POST["contact-lens"] == 1 ? "checked" : "") . " class=\"online-fogleu-element\" name=\"contact-lens\" value=\"1\">&nbsp;&nbsp;Kontaklencsét</div>";
            echo "</div>";
            echo "<input class=\"online-fogleu-element\" type=\"radio\" value=\"0\" " . ($_POST["eyeglasses-use"] == 0 ? "checked" : "") . " name=\"eyeglasses-use\">&nbsp;Nem";
            echo "</td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";


            //Kérdés:
            echo "<tr><td><strong>10. Visszerek vannak-e a lábán?</strong></td></tr>";
            //Válasz:
            echo "<tr><td>";
            echo "<div><input class=\"online-fogleu-element\" type=\"radio\" value=\"1\" " . ($_POST["varicose-veins"] == 1 ? "checked" : "") . " name=\"varicose-veins\">&nbsp;Igen</div>";
            echo "<input class=\"online-fogleu-element\" type=\"radio\" value=\"0\" " . ($_POST["varicose-veins"] == 0 ? "checked" : "") . " name=\"varicose-veins\">&nbsp;Nem";
            echo "</td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";



            //Kérdés:
            echo "<tr><td><strong>11. Szokta-e mérni a vérnyomását?</strong></td></tr>";
            //Válasz:
            echo "<tr><td>";
            echo "<div><input class=\"online-fogleu-element\" type=\"radio\" value=\"0\" " . ($_POST["tendency-of-blood-pressure-measurement"] == 0 ? "checked" : "") . " name=\"tendency-of-blood-pressure-measurement\">&nbsp;Soha</div>";

            echo "<input class=\"online-fogleu-element\" type=\"radio\" value=\"1\" " . ($_POST["tendency-of-blood-pressure-measurement"] == 1 ? "checked" : "") . " name=\"tendency-of-blood-pressure-measurement\">&nbsp;Szoktam";
            echo "<div id=\"tendency-of-blood-pressure-measurement-textdiv\" " . ($_POST["tendency-of-blood-pressure-measurement"] == 1 ? "" : "style=\"display:none;\"") . " >";
            echo "<div>&nbsp;&nbsp;&nbsp;Legutóbbi mérés időpontja: <select class=\"design-put\" style=\"width:auto;padding:3px\" name=\"last-occasion\">";
            echo "  <option value=\"one-week-ago\" " . ($_POST["last-occasion"] == "one-week-ago" ? "checked" : "") . " >Egy hete</option>";
            echo "  <option value=\"one-month-ago\" " . ($_POST["last-occasion"] == "one-month-ago" ? "checked" : "") . " >Egy hónapja</option>";
            echo "  <option value=\"three-months-ago\" " . ($_POST["last-occasion"] == "three-months-ago" ? "checked" : "") . " >Három hónapja</option>";
            echo "  <option value=\"six-months-ago\" " . ($_POST["last-occasion"] == "six-months-ago" ? "checked" : "") . " >Hat hónapja</option>";
            echo "</select></div><br>";
            echo "<div>&nbsp;&nbsp;&nbsp;Vérnyomás: <input class=\"design-put\" onkeyup=\"checkFogleuForm();\" type=\"number\" style=\"width:60px;padding:3px\" name=\"previous-blood-pressure-01\" min=\"80\" max=\"300\" value=\"{$_POST["previous-blood-pressure-01"]}\" />&nbsp;/&nbsp;";
            echo "<input class=\"design-put\" onkeyup=\"checkFogleuForm();\" type=\"number\" style=\"width:60px;padding:3px\" name=\"previous-blood-pressure-02\" min=\"40\" max=\"150\" value=\"{$_POST["previous-blood-pressure-02"]}\" />&nbsp;&nbsp;&nbsp;";
            echo "Pulzus: <input class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"width:60px;padding:3px;\" type=\"number\"  min=\"40\" max=\"190\" name=\"previous-pulse\" value=\"{$_POST["previous-pulse"]}\" /></div>";
            echo "</div>";
            echo "</td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";


            //Kérdés:
            echo "<tr><td><strong>12. Most megtudja mérni vérnyomását?</strong></td></tr>";
            //Válasz:
            echo "<tr><td>";
            echo "<div><input class=\"online-fogleu-element\" type=\"radio\" value=\"1\" name=\"current-blood-pressure-measurement\" " . ($_POST["current-blood-pressure-measurement"] == 1 ? "checked" : "") . " />&nbsp;Igen</div>";
            echo "<div id=\"current-blood-pressure-measurement-textdiv\" " . ($_POST["current-blood-pressure-measurement"] == 1 ? "" : "style=\"display:none;\"") . " >";
            echo "<div>&nbsp;&nbsp;&nbsp;Vérnyomás: <input class=\"design-put\" onkeyup=\"checkFogleuForm();\" type=\"number\" style=\"width:60px;padding:3px\" name=\"present-blood-pressure-01\" min=\"80\" max=\"300\" value=\"{$_POST["present-blood-pressure-01"]}\" />&nbsp;/&nbsp;";
            echo "<input class=\"design-put\" onkeyup=\"checkFogleuForm();\" type=\"number\" style=\"width:60px;padding:3px\" name=\"present-blood-pressure-02\" min=\"40\" max=\"150\" value=\"{$_POST["present-blood-pressure-02"]}\" />&nbsp;&nbsp;&nbsp;";
            echo "Pulzus: <input class=\"design-put\" onkeyup=\"checkFogleuForm();\" style=\"width:60px;padding:3px;\" type=\"number\"  min=\"40\" max=\"190\" name=\"present-pulse\" value=\"{$_POST["present-pulse"]}\" /></div>";
            echo "</div>";
            echo "<input class=\"online-fogleu-element\" type=\"radio\" value=\"0\" name=\"current-blood-pressure-measurement\" " . ($_POST["current-blood-pressure-measurement"] == 0 ? "checked" : "") . " />&nbsp;Nem";
            echo "</td></tr>";
            //Térköz:
            echo "<tr><td style=\"height:25px\"></td></tr>";

            echo "</table>";

            echo "<table>";
            if (!isset($_POST["g-recaptcha-response"])) {
                echo "<tr><td></td><td><div class='g-recaptcha' data-callback='recaptchaCallback' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
            }
            //echo "<tr><td><td><div style='margin-top:10px;'><input type='checkbox' class=\"online-fogleu-element\" name='aszf' value='1' " . (isset($_POST["aszf"]) ? "checked" : "") . "/> {$webText["aszfelf"]}</div></td></tr>";
            echo "<tr><td></td><td><div style=\"margin-top:10px\"><input type=\"checkbox\" class=\"online-fogleu-element\" name=\"gdpr\" value=\"1\" ".(isset($_POST["gdpr"])?"checked":"")." />Az <a href=\"https://bejelentkezes.hungariamed.hu/images/adatvedelmi_tajekoztato_rendelesi_mozaik_v4_210325.pdf\" target=\"_blank\">adatkezelési tájékoztató</a>t elolvastam, hozzájárulok a fenti adataim bejelentkezés céljából történő kezeléshez.</div></td></tr>";
            echo "<tr><td></td><td><div style=\"margin-top:10px\"><input type=\"checkbox\" class=\"online-fogleu-element\" name=\"trusted-data\" value=\"1\" ".(isset($_POST["trusted-data"])?"checked":"")." />Hozzájárulok az általam megadott egészségügyi adatok állapotfelmérés céljából történő kezeléséhez.</div></td></tr>";
            echo "<tr><td></td><td><div style=\margin-top:10px;\><input type=\"checkbox\" class=\"online-fogleu-element\" name=\"telepone-consultation-required\" " . (isset($_POST["telepone-consultation-required"]) ? "checked" : "") . "  value=\"1\">Szeretnék telefonos konzultációt kérni.</div></td></tr>";
            

            echo "<tr><td></td><td><div style=\margin-top:10px;\><input type=\"checkbox\" class=\"online-fogleu-element\"  name=\"responsiblity-confirmed\" " . (isset($_POST["responsiblity-confirmed"]) ? "checked" : "") . " value=\"1\"><strong>Büntetőjogi felelősségem tudatában kijelentem, hogy a fent megadott információk pontosak és megfelelnek a valóságnak.</strong></div></td></tr>";
            echo "</table>";

            echo "<div style='margin-top:30px;text-align: center;'><input type=\"submit\" name=\"online-fogoleu-submit-button\" id=\"online-fogoleu-submit-button\" onclick=\"return false\" class=\"newbutton\" style='opacity: .3;border:none' value=\"Adatok elküldése\" /></div>";

            echo "</form>";
            echo "<script src=\"javascript/onlinefogleuform.js\"></script>";
        }
    }

    private function successfulOnlineFogleu()
    {
        $webText = $this->lang->webText;
        $html = "";

        $html .= "<h2>Kedves Páciensünk!</h2>";
        $html .= "<p style = 'font-size:16px'>Köszönjük, hogy igénybe vette az online foglalkozás-egészségügyi szolgáltatásunkat!<br>";
        $html .= "Az Ön által megadott adatokat a lehető legnagyobb biztonsággal kezeljük és csak az arra jogosult Orvosok fogják kiértékelni.</p>";
        $html .= "<p style = 'font-size:16px'>Értesíteni fogjuk a vizsgálat eredményével kapcsolatban, amennyiben megadott bármilyen elérhetőséget.</p>";
        $html .= "<a href='/'>{$webText["visszafooldal"]}</a>";

        return $html;
    }
}
