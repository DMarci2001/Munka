<?php

use PHPMailer\PHPMailer\PHPMailer;

class OltasJelentkezesPage extends CorePage
{
    private $langText = [
        "hu" => [
            "intro" => "",
            "title" => "Oltás regisztráció",
            "subtitle1" => "Adatok és kérdések",
            "nev" => "Név",
            "szuletesido" => "Születési dátum",
            "taj" => "TAJ",
            "utlevel" => "Útlevél szám",
            "telefon" => "Telefonszám",
            "oltoanyag" => "Jelenleg az alábbi oltóanyago(ka)t tudjuk biztosítani, igényt tart-e az adott vakcinára?",
            "tajekoztato" => "tájékoztató",
            "allergia" => "Van-e bármilyen allergiája (élelmiszer, gyógyszer, egyéb)?",
            "igen" => "IGEN",
            "nem" => "NEM",
            "anafilaxia" => "Védőoltás beadását követően volt-e anafilaxiás reakciója?",
            "lazas" => "Volt-e lázas beteg az elmúlt 2 hétben?",
            "terhes" => "Terhes?",
            "betegseg" => "Van-e tartós, krónikus betegsége? (cukorbetegség, magas vérnyomás, asztma, szív-, vesebetegség stb.)",
            "veralvadas" => "Volt-e Önnek valaha véralvadási megbetegedése (mélyvénás-trombózis, tüdőembólia, szívinfarktus, STROKE (agyi infarktus)?",
            "fogamzasgatlo" => "Fogamzásgátlót szed-e?",
            "vedooltas" => "Kapott-e az elmúlt 4 hétben védő oltást?",
            "regvakcinainfo" => "Regisztrált-e Ön oltásra a <a target='_blank' href='https://vakcinainfo.gov.hu'>vakcinainfo.gov.hu</a> oldalon?",
            "covidoltas" => "Kapott-e már Covid védőoltást?",
            "pcrteszt" => "Átesett-e 3 hónapon belül PCR vizsgálattal igazolt Covid fertőzésen?",
            "adatvedelmi" => "Az <a href='https://bejelentkezes.hungariamed.hu/images/Hungariamed_Suzuki_oltasigenyles_adatvedelmi_tajekoztato_final_HR_0428 v3.pdf' target='_blank'>adatkezelési tájékoztató</a>t elolvastam, hozzájárulok a fenti adataim koronavírus elleni oltás nyújtása céljából történő kezeléséhez.",
            "pontos" => "Kijelentem, hogy a megadott információk pontosak és megfelelnek a valóságnak.",
            "reszletezze" => "Igen válasz esetén kérjük részletezze",
            "errordata" => "Kérjük adja meg az adatait!",
            "erroremail" => "A megadott e-mail cím formátuma nem megfelelő!",
            "questionerror" => "Kérjük válaszoljon az összes kérdésre!",
            "vchoose1" => "Kérjük válasszon egy vakcinát!",
            "vonly1" => "Kérjük csak 1 vakcinát válasszon!",
            "send" => "Regisztráció",
            "thanks" => "Köszönjök a kitöltést!",
            "doreg" => "Kérjük tegye meg a regisztrációját a <a target='_blank' href='https://vakcinainfo.gov.hu'>vakcinainfo.gov.hu</a> oldalon is!",
            "maildonesubject" => "Értesítés oltási regisztrációról",
            "maildone" => "Tisztelt jelentkező!<br/>
                <br/>
                Köszönjük regisztrációját.<br/>
                Oltási időpontjáról hamarosan értesítést küldünk e-mail címére és SMS-ben.<br/>
                <br/>
                Üdvözlettel:<br/>
                Hungária Med-M Kft.",
        ],
        "en" => [
            "intro" => "",
            "title" => "Vaccination registration",
            "subtitle1" => "Data form",
            "nev" => "Name",
            "szuletesido" => "Date of birth",
            "taj" => "TAJ",
            "utlevel" => "Passport number",
            "telefon" => "Phone number",
            "oltoanyag" => "The following vaccines are currently available: Which vaccines do you allow?",
            "tajekoztato" => "information",
            "allergia" => "Do you have any allergies (food, medicine, other)?",
            "igen" => "YES",
            "nem" => "NO",
            "anafilaxia" => "Did you have an anaphylactic reaction after receiving the vaccine?",
            "lazas" => "Have you had a fever in the last 2 weeks?",
            "terhes" => "Pregnant?",
            "betegseg" => "Do you have a long-term, chronic illness? (diabetes, high blood pressure, asthma, heart disease, kidney disease, etc.)",
            "veralvadas" => "Have you ever had a blood clotting disorder (deep vein thrombosis, pulmonary embolism, heart attack, STROKE)?",
            "fogamzasgatlo" => "Are you taking a contraceptive?",
            "vedooltas" => "Have you received any kind of vaccination in the last 4 weeks?",
            "regvakcinainfo" => "Have you registered for vaccination at <a target='_blank' href='https://vakcinainfo.gov.hu'>vakcinainfo.gov.hu</a>?",
            "covidoltas" => "Have you received Covid vaccination?",
            "pcrteszt" => "Did you have a PCR-confirmed Covid infection within 3 months?",
            "adatvedelmi" => "Az <a href='https://bejelentkezes.hungariamed.hu/images/Hungariamed_Suzuki_oltasigenyles_adatvedelmi_tajekoztato_final_HR_0428 v3.pdf' target='_blank'>adatkezelési tájékoztató</a>t elolvastam, hozzájárulok a fenti adataim koronavírus elleni oltás nyújtása céljából történő kezeléséhez.",
            "pontos" => "Kijelentem, hogy a megadott információk pontosak és megfelelnek a valóságnak.",
            "reszletezze" => "If yes, please describe it",
            "errordata" => "Please fill all data field!",
            "erroremail" => "The format of the email you provided is not correct!",
            "questionerror" => "Please answer all of the questions!",
            "vchoose1" => "Please choose a vaccine!",
            "vonly1" => "Please choose only one vaccine!",
            "send" => "Registration",
            "thanks" => "Thank you for your registration!",
            "doreg" => "Kérjük tegye meg a regisztrációját a <a target='_blank' href='https://vakcinainfo.gov.hu'>vakcinainfo.gov.hu</a> oldalon is!",
            "maildonesubject" => "Értesítés oltási regisztrációról",
            "maildone" => "Dear applicant!<br/>
                <br/>
                Thank you for registering.<br/>
                We will send you a notification about your vaccination date to your email address and in text.<br/>
                <br/>
                Regards:<br/>
                Hungária Med-M Kft.",
        ]
    ];

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
            "name" => "Janssen",
            "tajekoztato_url"  => "https://koronavirus.gov.hu/sites/default/files/sites/default/files/imce/janssen_vakcina_lakossagi_tajekoztato.pdf"
        ]
    ];

    public $validVakcinak;


    public $pageParams = [
      "testoltas" => [
            "id" => "oltasformteszt",
            "title" => "Oltás teszt",
            "vakcinalist" => [1, 2, 3, 4, 5, 6],
            "supply" => [null, 10000, 4000, 10000, 10000, 10000, 10000],
            "supplyfrom" => [null, '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10'],
            "javascript" => "oltasform_samsung.js",
            "logo" => "https://influitive.com/wp-content/uploads/2021/01/UserTesting-Logo.png",
            "logoheight" => 45
      ],
      "secl" => [
            "id" => "oltasformsamsung",
            "title" => "Samsung",
            "vakcinalist" => [1, 2, 3, 4, 5, 6],
            "supply" => [null, 0, 0, 0, 0, 0, 10000],
            "supplyfrom" => [null, '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10'],
            "javascript" => "oltasform_samsung.js",
            "logo" => "https://upload.wikimedia.org/wikipedia/commons/2/24/Samsung_Logo.svg",
            "logoheight" => 45
      ],
      "samoo" => [
            "id" => "oltasformsamoo",
            "title" => "Samoo",
            "vakcinalist" => [6],
            "supply" => [null, 0, 0, 0, 0, 0, 10000],
            "supplyfrom" => [null, '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10'],
            "javascript" => "oltasform_samsung.js",
            "logo" => "https://upload.wikimedia.org/wikipedia/commons/thumb/a/a9/Logo_Samoo_Signature_Vertical.png/375px-Logo_Samoo_Signature_Vertical.png",
            "logoheight" => 45
        ],
        "s-1" => [
            "id" => "oltasforms1",
            "title" => "S-1",
            "vakcinalist" => [6],
            "supply" => [null, 0, 0, 0, 0, 0, 10000],
            "supplyfrom" => [null, '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10'],
            "javascript" => "oltasform_samsung.js",
            "logo" => "/images/s1logo.png",
            "logoheight" => 50
        ],
        "sdi" => [
            "id" => "oltasformsdi",
            "title" => "Sdi Samsung",
            "vakcinalist" => [6],
            "supply" => [null, 0, 0, 0, 0, 0, 10000],
            "supplyfrom" => [null, '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10'],
            "javascript" => "oltasform_samsung.js",
            "logo" => "https://www.samsungsdi.hu/assets/oldSite/img/common/logo.svg",
            "logoheight" => 45
        ],
        "cksolution" => [
            "id" => "oltasformck",
            "title" => "CK Solution",
            "vakcinalist" => [6],
            "supply" => [null, 0, 0, 0, 0, 0, 10000],
            "supplyfrom" => [null, '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10', '2021-05-10'],
            "javascript" => "oltasform_samsung.js",
            "logo" => "/images/cksolution_logo.png",
            "logoheight" => 45
        ]

    ];

    public $pageParam;

    public function __construct()
    {
        parent::__construct();

        $this->pageParam = $this->pageParams[$GLOBALS["subdomain"]];
        $this->pageTitle = $this->pageParam["title"]." - ".$this->getText("title");
        $this->lockInPage = true;
        //$this->showLangMenu = false;
        $this->langList = ["hu", "en"];
        $this->showMainMenu = false;

        $this->customLogo = $this->pageParam["logo"];
        $this->customLogoHeight = $this->pageParam["logoheight"];

        $this->validVakcinak = $this->pageParam["vakcinalist"];

        $GLOBALS["javascript"][] = $this->pageParam["javascript"];

        if (isset($_GET["emailteszt"])) {
            $data["email"] = "jnsmobil@gmail.com";
            $this->doneEmail($data);
        }

        if (isset($_REQUEST["oltasformsavedata"])) {
            $result = ["error" => "", "html" => $this->donePage()];

            $_POST["szuldatum"] = "";

            $selectedVakcinak = 0;
            for ($i=1;$i<=6;$i++) {
                if (isset($_POST["vakcina{$i}"])) {
                    $selectedVakcinak++;
                }
            }

            if ($selectedVakcinak==0) {
                $result["error"] =  $this->getText("vchoose1");
            }
            if ($selectedVakcinak>1) {
                $result["error"] =  $this->getText("vonly1");
            }

            $datum = "";
            if (isset($_POST["szuldatumev"])) {
                $datum = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            }

            if ($this->utils->validateDate($datum, "Y-m-d")) {
                $_POST["szuldatum"] = $datum;
            }

            if (empty($_POST["szuldatum"]) || empty($_POST["nev"]) || empty($_POST["utlevel"])) {
                $result["error"] = $this->getText("errordata");
            }

            if (empty($result["error"]) && !filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
                $result["error"] = $this->getText("erroremail");
            }

            if (empty($result["error"])) {
                $result["error"] = $this->utils->checkCaptcha();
            }

            if ($result["error"] == "") {
                unset($_POST["g-recaptcha-response"]);
                $_POST["lang"] = $_COOKIE["lang"];
                sql_query("insert into webservicelog set tipus=23, datum=now(), keres=?, action='{$this->pageParam["id"]}_new', response=?", [json_encode($_POST, JSON_PRETTY_PRINT), $result["html"]]);

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

        echo $this->displayFejlexSuzuki($this->getText("title"), true);

        echo "<div id='oltasformdiv'>";

        echo $this->showErrors();

        echo "<form name='oltasform' id='oltasform' method='POST' enctype='multipart/form-data'>";


        //if (session_id() != "eqgn831hqh61cvalmb6eohhgmc") {
        //    echo "<div>Fejlesztés alatt, kérjük nézzen vissza fél óra múlva! " . session_id() . "<br/><br/></div>";
        //    return;
        //}

        echo "<div>".$this->getText("intro")."</div>";


        //Páciens Adatok:
        //echo "<h2>".$this->getText("subtitle1")."</h2>";
        echo "<table cellpadding='3' cellspacing='0'>";
        echo "<tr><td>".$this->getText("nev").":</td><td><input style='width:260px' type='text' value='{$_POST["nev"]}' name='nev' id='nev'></td></tr>";
        echo "<tr><td>".$this->getText("szuletesido").":</td><td>" . $this->utils->datumSelector($_POST["szuldatum"], "szuldatum", 0, "") . "</td></tr>";
        echo "<tr><td>".$this->getText("utlevel").":</td><td><input style='width:260px' type='text' value='{$_POST["utlevel"]}' name='utlevel' id='taj'></td></tr>";
        //echo "<tr><td>Magyar Suzuki<br/>törzsszám:</td><td><input style='width:260px' type='text' value='{$_POST["torszam"]}' name='torzsszam' id='torzsszam'></td></tr>";
        echo "<tr><td>E-mail: </td><td><input style='width:260px' type='text' value='{$_POST["email"]}' name='email' id='email'></td></tr>";
        echo "<tr><td>".$this->getText("telefon").": </td><td><input style='width:260px' type='text' value='{$_POST["telefon"]}' name='telefon' id='telefon'></td></tr>";
        echo "</table>";

        //csoport

        echo "<div style='padding-bottom: 20px;margin-bottom: 20px;border-bottom: 1px solid #ccc;'>";
        /*
        echo "<div style='margin-top:20px;'><strong>Melyik csoportba tartozik?</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='csoport' value='a muszak' /> \"A\" műszak</div>";
        echo "<div><input class='oltaselement' type='radio' name='csoport' value='b muszak' /> \"B\" műszak</div>";
        echo "<div><input class='oltaselement' type='radio' name='csoport' value='office worker' /> Irodai dolgozó </div>";
        echo "<div><input class='oltaselement' type='radio' name='csoport' value='karbantarto' /> Karbantartó </div>";
        echo "<div><input class='oltaselement' type='radio' name='csoport' value='egyeb' /> nem Magyar Suzuki dolgozó</div>";
        echo "<div id='csoporttextdiv' style='display:none;'><textarea name='csoporttext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='Kérjük adja meg melyik partnercégnél dolgozik'></textarea></div>";
        */

        //vakcina
        //echo "<div style='margin:20px 0px 10px 0px;'><strong>".$this->getText("oltoanyag")."</strong></div>";
        //foreach ($this->validVakcinak as $vakcinaId) {
        //    echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina{$vakcinaId}' value='1' /> {$this->vakcinak[$vakcinaId]["name"]} [<a target='_blank' href='{$this->vakcinak[$vakcinaId]["tajekoztato_url"]}'>".$this->getText("tajekoztato")."</a>]</div>";
        //}
        //echo "</div>";


        //vakcina
        echo "<div style='margin:20px 0px 10px 0px;'><strong>".$this->getText("oltoanyag")."</strong></div>";
        foreach ($this->validVakcinak as $vakcinaId) {
            $data = sql_query("select count(*) as hany from webservicelog where tipus=23 and action='{$this->pageParam["id"]}_new' and instr(keres, ?) and datum>?", ["vakcina{$vakcinaId}", $this->pageParam["supplyfrom"][$vakcinaId]])->fetch(PDO::FETCH_ASSOC);
            if ($data["hany"] < $this->pageParam["supply"][$vakcinaId]) {
                $vakcinaExists = true;
                echo "<div style=''><input class='oltaselement' type='checkbox' name='vakcina{$vakcinaId}' value='1' /> {$this->vakcinak[$vakcinaId]["name"]} [<a target='_blank' href='{$this->vakcinak[$vakcinaId]["tajekoztato_url"]}'>".$this->getText("tajekoztato")."</a>]</div>";
            }
        }
        if (!isset($vakcinaExists)) {
            echo "<div style='background:#ff6961;color:white;padding:10px;border-radius: 3px;'>Jelenleg nincs beállítva választható vakcina, kérjük nézzen vissza később!</div>";
        }
        echo "</div>";



        echo "<div id='tovabbikerdesek' style='opacity:1'>";

        //allergia
        echo "<div style='margin-top:20px;'><strong>".$this->getText("allergia")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='allergia' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div id='allergiatextdiv' style='display:none;'><textarea name='allergiatext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='".$this->getText("reszletezze")."'></textarea></div>";
        echo "<div><input class='oltaselement' type='radio' name='allergia' value='0' /> ".$this->getText("nem")."</div>";

        //anafilaxiás reakció
        echo "<div style='margin-top:20px;'><strong>".$this->getText("anafilaxia")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='anafilaxia' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div id='anafilaxiatextdiv' style='display:none;'><textarea name='anafilaxiatext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='".$this->getText("reszletezze")."'></textarea></div>";
        echo "<div><input class='oltaselement' type='radio' name='anafilaxia' value='0' /> ".$this->getText("nem")."</div>";

        //lázas
        echo "<div style='margin-top:20px;'><strong>".$this->getText("lazas")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='lazas' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div><input class='oltaselement' type='radio' name='lazas' value='0' /> ".$this->getText("nem")."</div>";

        //terhes
        echo "<div style='margin-top:20px;'><strong>".$this->getText("terhes")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='terhes' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div><input class='oltaselement' type='radio' name='terhes' value='0' /> ".$this->getText("nem")."</div>";

        //krónikus betegseg
        echo "<div style='margin-top:20px;'><strong>".$this->getText("betegseg")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='betegseg' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div id='betegsegtextdiv' style='display:none;'><textarea name='betegsegtext' class='inputbox' style='margin-left:5px;width:50%;min-width:340px;' placeholder='".$this->getText("reszletezze")."'></textarea></div>";
        echo "<div><input class='oltaselement' type='radio' name='betegseg' value='0' /> ".$this->getText("nem")."</div>";

        //véralvadás
        echo "<div style='margin-top:20px;'><strong>".$this->getText("veralvadas")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='veralvadas' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div><input class='oltaselement' type='radio' name='veralvadas' value='0' /> ".$this->getText("nem")."</div>";

        //fogamzásgátlás
        echo "<div style='margin-top:20px;'><strong>".$this->getText("fogamzasgatlo")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='fogamzasgatlas' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div><input class='oltaselement' type='radio' name='fogamzasgatlas' value='0' /> ".$this->getText("nem")."</div>";

        //védőoltás
        echo "<div style='margin-top:20px;'><strong>".$this->getText("vedooltas")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='vedooltas' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div><input class='oltaselement' type='radio' name='vedooltas' value='0' /> ".$this->getText("nem")."</div>";

        //regisztrált oltásra
        echo "<div style='margin-top:20px;'><strong>".$this->getText("regvakcinainfo")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='oltasregisztralt' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div><input class='oltaselement' type='radio' name='oltasregisztralt' value='0' /> ".$this->getText("nem")."</div>";

        //megkapta
        echo "<div style='margin-top:20px;'><strong>".$this->getText("covidoltas")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='oltasmegkapta' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div><input class='oltaselement' type='radio' name='oltasmegkapta' value='0' /> ".$this->getText("nem")."</div>";

        //átesett
        echo "<div style='margin-top:20px;'><strong>".$this->getText("pcrteszt")."</strong></div>";
        echo "<div style='margin-top:10px;'><input class='oltaselement' type='radio' name='atesett' value='1' /> ".$this->getText("igen")."</div>";
        echo "<div><input class='oltaselement' type='radio' name='atesett' value='0' /> ".$this->getText("nem")."</div>";

        echo "</div>";

        echo "<table style='margin-top:20px;'>";
        if (!isset($_POST["g-recaptcha-response"])) {
            echo "<tr><td></td><td><div class='g-recaptcha' data-callback='recaptchaCallback' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
        }

        if ($_COOKIE["lang"] == "hu") {
            echo "<tr><td></td><td><div style='margin-top:10px'><input type='checkbox' class='online-fogleu-element' name='gdpr' id='gdpr' value='1' " . (isset($_POST["gdpr"]) ? "checked" : "") . " />&nbsp;Az <a href='https://bejelentkezes.hungariamed.hu/images/Hungariamed_Suzuki_oltasigenyles_adatvedelmi_tajekoztato_final_HR_0428 v3.pdf' target='_blank'>adatkezelési tájékoztató</a>t elolvastam, hozzájárulok a fenti adataim koronavírus elleni oltás nyújtása céljából történő kezeléséhez.<input type='hidden' name='gdprno' id='gdprno' value='0' /></div></td></tr>";
            echo "<tr><td></td><td><div style=\margin-top:5px;\><input type='checkbox' class='online-fogleu-element'  name='responsiblity-confirmed'  id='responsiblity-confirmed' " . (isset($_POST["responsiblity-confirmed"]) ? "checked" : "") . " value='1'>&nbsp;Kijelentem, hogy a megadott információk pontosak és megfelelnek a valóságnak.</div></td></tr>";
            //echo "<tr><td></td><td><div style='margin-top:5px'><input type='checkbox' class='online-fogleu-element' name='trusted-data' id='trusted-data' value='1' ".(isset($_POST["trusted-data"])?"checked":"")." /> Hozzájárulok, hogy a megadott egészségügyi adataim átadásra kerüljenek a [CÉG NEVE] foglalkozás-egészségügyi szolgáltató részére.</div></td></tr>";
        }
        echo "</table>";

        if ($_COOKIE["lang"] != "hu") {
        }

        echo "<div style='margin-top:30px;text-align: center;'><input type='button' name='oltas-submit-button' id='oltas-submit-button' class='newbutton' style='border:none' value='".$this->getText("send")."' /></div>";


        echo "</form>";
        echo "</div>";

        echo "<script>";
        echo "var questionErrorText = '".$this->getText("questionerror")."';";
        echo "</script>";

    }

    private function donePage():string {
        $html = "";

        $html.= "<div style='margin:20px 0px 20px 0px;'>">
            $html.="<div><strong>".$this->getText("thanks")."</strong></div>";
        if (isset($_POST["oltasregisztralt"]) && $_POST["oltasregisztralt"] == "0") {
            $html .= "<div style='margin:10px 0px 0px 0px;'>".$this->getText("doreg")."</div>";
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

        $mail->Subject = $this->getText("maildonesubject");
        $mail->Body = $this->getText("maildone");

        $mail->Send();
    }

    private function getText($key):string {
        return $this->langText[$_COOKIE["lang"]][$key];
    }
}
