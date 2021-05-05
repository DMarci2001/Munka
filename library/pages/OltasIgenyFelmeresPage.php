<?php

use PHPMailer\PHPMailer\PHPMailer;

class OltasIgenyFelmeresPage extends CorePage
{
    public $vakcinak = [
        1 => [
            "name" => "Pfizer-Biotech",
            "tajekoztato_url"  => "https://koronavirus.gov.hu/sites/default/files/sites/default/files/imce/pfizer-biontech_vakcina_lakossagi_tajekoztato_2.pdf",
        ],
        2 => [
            "name" => "Moderna",
            "tajekoztato_url"  => "https://koronavirus.gov.hu/sites/default/files/sites/default/files/imce/moderna-vakcina_lakossagi_tajekoztato_0.pdf"
        ],
        3 => [
            "name" => "Szputnyik",
            "tajekoztato_url"  => "https://koronavirus.gov.hu/sites/default/files/sites/default/files/imce/qa_lakossag_oltasi_javallatok_es_ellenjavallatok_szputnyik.pdf"
        ],
        4 => [
            "name" => "Astra Zeneca",
            "tajekoztato_url"  => "https://koronavirus.gov.hu/sites/default/files/sites/default/files/imce/astrazeneca_lakossagi_tajekoztato_2.pdf"
        ],
        5 => [
            "name" => "Sinopharm",
            "tajekoztato_url"  => "https://koronavirus.gov.hu/sites/default/files/sites/default/files/imce/sinopharm-vakcina_lakossagi_tajekoztato.pdf"
        ],
        6 => [
            "name" => "Janessen",
            "tajekoztato_url"  => "https://koronavirus.gov.hu/sites/default/files/sites/default/files/imce/janssen_vakcina_lakossagi_tajekoztato.pdf"
        ]
    ];

    public function __construct()
    {
        parent::__construct();

        $this->pageTitle = "Magyar Suzuki - Oltás igény felmérés";
        $this->lockInPage = true;
        $this->showLangMenu = false;
        $this->showMainMenu = false;
        $this->showSuzukiLogo = true;

        if (isset($_GET["emailteszt"])) {
            $data["email"] = "jnsmobil@gmail.com";
            $this->doneEmail($data);
        }

        if (isset($_REQUEST["oltasformsavedata"])) {
            $result = ["error" => "", "html" => $this->donePage()];

            $_POST["szuldatum"] = "";

            $datum = "";
            if (isset($_POST["szuldatumev"])) {
                $datum = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            }

            if ($this->utils->validateDate($datum, "Y-m-d")) {
                $_POST["szuldatum"] = $datum;
            }

            if (empty($_POST["szuldatum"]) || empty($_POST["nev"]) || empty($_POST["torzsszam"])) {
                $result["error"] = "Kérjük adja meg az adatait!";
            }

            if (empty($result["error"]) && !filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
                $result["error"] = "A megadott e-mail cím formátuma nem megfelelő!";
            }

            if (empty($result["error"])) {
                $result["error"] = $this->utils->checkCaptcha();
            }

            if ($result["error"] == "") {
                unset($_POST["g-recaptcha-response"]);
                sql_query("insert into webservicelog set tipus=23, datum=now(), keres=?, action='oltasform_new', response=?", [json_encode($_POST, JSON_PRETTY_PRINT), $result["html"]]);

                $this->doneEmail($_POST);
            }

            $this->utils->jsonOut($result);
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


        echo $this->displayFejlexSuzuki("Oltás igény felmérés", true);


        echo "<div id='oltasformdiv'>";

        echo $this->showErrors();

        echo "<form name='oltasform' id='oltasform' method='POST' enctype='multipart/form-data'>";

        echo "<div>A Magyar Suzuki dolgozóinak
            létrehozott Covid-19 oltási igény felmérő felülete. A Magyar Suzuki 
            oltópontot hoz létre munkavállalói részére, ahol lehetőség nyílik soron
            kívül és biztonságos módon a Covid-19 oltás beadására. A helyszínen
            külön oltóorvos vezetésével oltóközpont kialakítására kerül sor. Itt
            lehetőség nyílik egyénre szabott teljes körű orvosi konzultációra,
            Covid-19 antigén gyorstesztre.<br/>
            <br/>
            Kérjük töltse ki az alábbi űrlapot, és válaszoljon az összes kérdésre.</div>";


        //Páciens Adatok:
        echo "<h2>Adatok és kérdések</h2>";
        echo "<table cellpadding='3' cellspacing='0'>";
        echo "<tr><td>Név:</td><td><input style='width:260px' type='text' value='{$_POST["nev"]}' name='nev' id='nev'></td></tr>";
        echo "<tr><td>Születési dátum:</td><td>" . $this->utils->datumSelector($_POST["szuldatum"], "szuldatum", 0, "") . "</td></tr>";
        echo "<tr><td>TAJ:</td><td><input style='width:260px' type='text' value='{$_POST["taj"]}' name='taj' id='taj'></td></tr>";
        echo "<tr><td>Magyar Suzuki<br/>törzsszám:</td><td><input style='width:260px' type='text' value='{$_POST["torszam"]}' name='torzsszam' id='torzsszam'></td></tr>";
        echo "<tr><td>E-mail: </td><td><input style='width:260px' type='text' value='{$_POST["email"]}' name='email' id='email'></td></tr>";
        echo "<tr><td>Telefonszám: </td><td><input style='width:260px' type='text' value='{$_POST["telefon"]}' name='telefon' id='telefon'></td></tr>";
        echo "</table>";

        //csoport
        echo "<div style='padding-bottom: 20px;margin-bottom: 20px;border-bottom: 1px solid #ccc;'>";
        echo "<div style='margin-top:20px;'><strong>Melyik csoportba tartozik?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='csoport' value='a muszak' /> \"A\" műszak</div>";
        echo "<div><input class='oltaselement' type='radio' name='csoport' value='b muszak' /> \"B\" műszak</div>";
        echo "<div><input class='oltaselement' type='radio' name='csoport' value='office worker' /> Irodai dolgozó </div>";
        echo "<div><input class='oltaselement' type='radio' name='csoport' value='karbantarto' /> Karbantartó </div>";
        echo "<div><input class='oltaselement' type='radio' name='csoport' value='egyeb' /> nem Magyar Suzuki dolgozó</div>";
        echo "<div id='csoporttextdiv' style='display:none;'><textarea name='csoporttext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='Kérjük adja meg melyik partnercégnél dolgozik'></textarea></div>";

        //igénybe venne
        //echo "<div style='padding-bottom: 20px;margin-bottom: 20px;border-bottom: 1px solid #ccc;'>";
        //echo "<div style='margin-top:20px;'><strong>Igénybe venné-e Ön a Magyar Suzuki szervezésében az MSC telephelyén felállított oltóponton az oltást?</strong></div>";
        //echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='igenybevenne' value='1' /> IGEN</div>";
        //echo "<div><input class='oltaselement' type='radio' name='igenybevenne' value='0' /> NEM</div>";

        //vakcina
        echo "<div style='margin-top:20px;'><strong>Jelenleg az alábbi oltóanyago(ka)t tudjuk biztosítani, igényt tart-e az adott vakcinára?</strong></div>";
        //echo "<div style='margin-top:10px;'><input class='oltaselement' type='checkbox' name='vakcina1' value='1' /> {$this->vakcinak[1]["name"]} [<a target='_blank' href='{$this->vakcinak[1]["tajekoztato_url"]}'>tájékoztató</a>]</div>";
        //echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina2' value='1' /> {$this->vakcinak[2]["name"]}</div>";
        //echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina3' value='1' /> {$this->vakcinak[3]["name"]}</div>";
        echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina4' value='1' /> {$this->vakcinak[4]["name"]} [<a target='_blank' href='{$this->vakcinak[4]["tajekoztato_url"]}'>tájékoztató</a>]</div>";
        //echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina5' value='1' /> {$this->vakcinak[5]["name"]}</div>";
        //echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina6' value='1' /> {$this->vakcinak[6]["name"]}</div>";

        echo "</div>";

        echo "<div id='tovabbikerdesek' style='opacity:1'>";

        //allergia
        echo "<div style='margin-top:20px;'><strong>Van-e bármilyen allergiája (élelmiszer, gyógyszer, egyéb)?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='allergia' value='1' /> IGEN</div>";
        echo "<div id='allergiatextdiv' style='display:none;'><textarea name='allergiatext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='Igen válasz esetén kérjük részletezze'></textarea></div>";
        echo "<div><input class='oltaselement' type='radio' name='allergia' value='0' /> NEM</div>";

        //anafilaxiás reakció
        echo "<div style='margin-top:20px;'><strong>Védőoltás beadását követően volt-e anafilaxiás reakciója?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='anafilaxia' value='1' /> IGEN</div>";
        echo "<div id='anafilaxiatextdiv' style='display:none;'><textarea name='anafilaxiatext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='Igen válasz esetén kérjük részletezze'></textarea></div>";
        echo "<div><input class='oltaselement' type='radio' name='anafilaxia' value='0' /> NEM</div>";

        //lázas
        echo "<div style='margin-top:20px;'><strong>Volt-e lázas beteg az elmúlt 2 hétben?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='lazas' value='1' /> IGEN</div>";
        echo "<div><input class='oltaselement' type='radio' name='lazas' value='0' /> NEM</div>";

        //terhes
        echo "<div style='margin-top:20px;'><strong>Terhes?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='terhes' value='1' /> IGEN</div>";
        echo "<div><input class='oltaselement' type='radio' name='terhes' value='0' /> NEM</div>";

        //krónikus betegseg
        echo "<div style='margin-top:20px;'><strong>Van-e tartós, krónikus betegsége? (cukorbetegség, magas vérnyomás, asztma, szív-, vesebetegség stb.)</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='betegseg' value='1' /> IGEN</div>";
        echo "<div id='betegsegtextdiv' style='display:none;'><textarea name='betegsegtext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='Igen válasz esetén kérjük részletezze'></textarea></div>";
        echo "<div><input class='oltaselement' type='radio' name='betegseg' value='0' /> NEM</div>";

        //véralvadás
        echo "<div style='margin-top:20px;'><strong>Volt-e Önnek valaha véralvadási megbetegedése (mélyvénás-trombózis, tüdőembólia, szívinfarktus, STROKE (agyi infarktus)?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='veralvadas' value='1' /> IGEN</div>";
        echo "<div><input class='oltaselement' type='radio' name='veralvadas' value='0' /> NEM</div>";

        //fogamzásgátlás
        echo "<div style='margin-top:20px;'><strong>Fogamzásgátlót szed-e?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='fogamzasgatlas' value='1' /> IGEN</div>";
        echo "<div><input class='oltaselement' type='radio' name='fogamzasgatlas' value='0' /> NEM</div>";

        //védőoltás
        echo "<div style='margin-top:20px;'><strong>Kapott-e az elmúlt 4 hétben védő oltást?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='vedooltas' value='1' /> IGEN</div>";
        echo "<div><input class='oltaselement' type='radio' name='vedooltas' value='0' /> NEM</div>";

        //regisztrált oltásra
        echo "<div style='margin-top:20px;'><strong>Regisztrált-e Ön oltásra a <a target='_blank' href='https://vakcinainfo.gov.hu'>vakcinainfo.gov.hu</a> oldalon?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='oltasregisztralt' value='1' /> IGEN</div>";
        echo "<div><input class='oltaselement' type='radio' name='oltasregisztralt' value='0' /> NEM</div>";

        //megkapta
        echo "<div style='margin-top:20px;'><strong>Kapott-e már Covid védőoltást?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='oltasmegkapta' value='1' /> IGEN</div>";
        echo "<div><input class='oltaselement' type='radio' name='oltasmegkapta' value='0' /> NEM</div>";

        //átesett
        echo "<div style='margin-top:20px;'><strong>Átesett-e 3 hónapon belül PCR vizsgálattal igazolt Covid fertőzésen?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='atesett' value='1' /> IGEN</div>";
        echo "<div><input class='oltaselement' type='radio' name='atesett' value='0' /> NEM</div>";

        echo "</div>";

        echo "<table style='margin-top:20px;'>";
        if (!isset($_POST["g-recaptcha-response"])) {
            echo "<tr><td></td><td><div class='g-recaptcha' data-callback='recaptchaCallback' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
        }
        echo "<tr><td></td><td><div style='margin-top:10px'><input type='checkbox' class='online-fogleu-element' name='gdpr' id='gdpr' value='1' ".(isset($_POST["gdpr"])?"checked":"")." />&nbsp;Az <a href='https://bejelentkezes.hungariamed.hu/images/Hungariamed_Suzuki_oltasigenyles_adatvedelmi_tajekoztato_final_HR_0428 v3.pdf' target='_blank'>adatkezelési tájékoztató</a>t elolvastam, hozzájárulok a fenti adataim koronavírus elleni oltás nyújtása céljából történő kezeléséhez.</div></td></tr>";
        echo "<tr><td></td><td><div style=\margin-top:5px;\><input type='checkbox' class='online-fogleu-element'  name='responsiblity-confirmed'  id='responsiblity-confirmed' " . (isset($_POST["responsiblity-confirmed"]) ? "checked" : "") . " value='1'>&nbsp;Kijelentem, hogy a megadott információk pontosak és megfelelnek a valóságnak.</div></td></tr>";
        //echo "<tr><td></td><td><div style='margin-top:5px'><input type='checkbox' class='online-fogleu-element' name='trusted-data' id='trusted-data' value='1' ".(isset($_POST["trusted-data"])?"checked":"")." /> Hozzájárulok, hogy a megadott egészségügyi adataim átadásra kerüljenek a [CÉG NEVE] foglalkozás-egészségügyi szolgáltató részére.</div></td></tr>";
        echo "</table>";

        echo "<div style='margin-top:30px;text-align: center;'><input type='button' name='oltas-submit-button' id='oltas-submit-button' class='newbutton' style='border:none' value='Adatok elküldése' /></div>";


        echo "</form>";
        echo "</div>";

    }

    private function donePage():string {
        $html = "";

        $html.= "<div style='margin:20px 0px 20px 0px;'>">
        $html.="<div><strong>Köszönjük a kitöltést!</strong></div>";
        if (isset($_POST["oltasregisztralt"]) && $_POST["oltasregisztralt"] == "0") {
            $html .= "<div style='margin:10px 0px 0px 0px;'>Kérjük tegye meg a regisztrációját a <a target='_blank' href='https://vakcinainfo.gov.hu'>vakcinainfo.gov.hu</a> oldalon is!</div>";
        }
        $html.="</div>";

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
