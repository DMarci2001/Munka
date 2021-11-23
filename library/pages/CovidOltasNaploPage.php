<?php
class CovidOltasNaploPage extends CorePage
{

    public $szervezetiEgysegek = [
        "hc" => [
            ["code" => "aaaa", "name" => "Első teszt egység"],
            ["code" => "bbbb", "name" => "Második teszt egység"],
            ["code" => "cccc", "name" => "Harmadik teszt egység"],
            ["code" => "dddd", "name" => "Negyedik teszt egység"],
            ["code" => "eeee", "name" => "Ötödik teszt egység"]
        ]

    ];

    private $oltoanyagok = array(
        array("id" => "sinopharm", "name" => "Sinopharm vakcina"),
        array("id" => "pfizer", "name" => "Pfizer"),
        array("id" => "johnson", "name" => "Johnson & Johnson"),
        array("id" => "moderna", "name" => "Moderna"),
        array("id" => "astrazeneca", "name" => "AstraZeneca"),
        array("id" => "szputnyik", "name" => "Szputnyik V")
    );

    private $user;
    public $webText;
    private $docAgent;

    public function __construct()
    {
        parent::__construct();

        $this->webText = $this->lang->webText;
        $this->user = new User();
        $this->docAgent = new DocAgent();

        //Ha már nem él a session, dobja vissza a kezdőlapra
        if (empty($this->user->user)) {
            header("Location:index.php?page=booking");
            die;
        }

        if (isset($_POST["oltasadatok"])) {
            $nocovid1 = isset($_POST["nocovid1"]) ? 1 : 0;
            $nocovid2 = isset($_POST["nocovid2"]) ? 1 : 0;
            sql_query("update felhasznalok set szervezetiegyseg=?, nocovid1=?, nocovid2=? where id=?", [$_POST["szervezetiegyseg"], $nocovid1, $nocovid2, $this->user->user["id"]]);
            header("location:index.php?page={$_GET["page"]}");
            die;
        }

        if (isset($_POST["uj-oltas-mentes"])) {

            $this->checkOltasData($_POST, "new");

            if (empty($this->errors)) {
                sql_query(
                    "INSERT INTO covid_oltas_naplo SET userid=?, oltas_tipus=?,oltas_datum=?,sorszam=?,regdatum=NOW()",
                    array($_SESSION["user"]["id"], $_POST["vaccination-type"], $_POST["vaccine-date"], $_POST["serial-number"])
                );
                unset($_POST);
                header("Location:index.php?page=covidoltasnaplo");
                exit();
            }
        }

        if (isset($_POST["modify_covid_data"]) && $_POST["modify_covid_data"] == true) {

            $this->checkOltasData($_POST, "modify");
            if ($data = sql_fetch_array(sql_query("SELECT id,regdatum,oltas_tipus as 'vaccination-type',oltas_datum as 'vaccine-date',sorszam as 'serial-number' FROM covid_oltas_naplo WHERE id=?", array($_POST["covId"])))) {
                echo $this->new_covid_oltas_naplo($data, "modify");
            }
            die();
        }

        if (isset($_POST["save_covid_data"]) && $_POST["save_covid_data"] == true) {
            $query = sql_query("SELECT * FROM covid_oltas_naplo WHERE id=?", array($_POST["covId"]));

            if (!empty(sql_num_rows($query))) {
                sql_query(
                    "UPDATE covid_oltas_naplo SET oltas_tipus=?,oltas_datum=?,sorszam=? WHERE id=?",
                    array($_POST["oltas_tipus"], $_POST["oltas_datum"], $_POST["sorszam"], $_POST["covId"])
                );

                $covid_data = $this->select_covid_oltas_naplo($_POST["covId"]);
                echo $this->_covidRow($covid_data[0]);
            }

            die();
        }


        if (isset($_POST["cancel_covid_data"]) && $_POST["cancel_covid_data"] == true) {
            $covid_data = $this->select_covid_oltas_naplo($_POST["covId"]);
            echo $this->_covidRow($covid_data[0]);
            die();
        }

        if (isset($_POST["delete_covid_data"]) && $_POST["delete_covid_data"] == true) {
            if ($data = sql_fetch_array(sql_query("SELECT * FROM covid_oltas_naplo WHERE id=? AND userid=?", array($_POST["covId"], $_SESSION["user"]["id"])))) {
                sql_query("DELETE FROM covid_oltas_naplo WHERE id=?", array($_POST["covId"]));
            }
            die();
        }
    }

    public function showPage()
    {
        echo $this->displayFejlec("Oltás napló");
        echo $this->showErrors();

        if (empty($this->user->user)) {
            echo "Kérjük jelentkezzen be, vagy regisztráljon!";
            return;
        }

        echo "<h3>Kérjük adja meg a kapott COVID oltásainak adatait:</h3>";
        echo $this->adatForm();

        echo "<form method=\"POST\" enctype=\"multipart/form-data\">";
        echo "  <table class=\"covid-oltas-lista\">";
        echo "      <tr><td>Rögzités dátuma</td><td>Oltás típusa</td><td>Oltás dátuma</td><td>Hanyadik oltás</td><td>Védettségi igazolvány QR kód</td><td>Státusz</td><td></td></tr>";

        //Meglévő oltások listázása:
        $covid_data = $this->select_covid_oltas_naplo();

        for ($i = 0; $i < count($covid_data); $i++) {
            echo "<tr id=\"covid-data-id-{$covid_data[$i]["id"]}\">";
            echo $this->_covidRow($covid_data[$i]);
            echo "</tr>";
        }

        //Új oltás rögzitése:
        echo "<tr>" . $this->new_covid_oltas_naplo($_POST) . "</tr>";
        echo "      <tr>";
        echo "          <td colspan=\"6\" style=\"text-align:center\"><input type=\"submit\" class=\"covid-oltas-mentes-button\" name=\"uj-oltas-mentes\" value=\"Oltás hozzáadása\" /></td>";
        echo "      </tr>";
        echo "  </table>";
        echo "</form>";
    }

    private function _covidRow($data): string
    {
        $html = "<td>{$data["regdatum"]}</td>";
        $html .= "<td>" . $this->oltoanyag_nev($data["oltas_tipus"]) . "</td>";
        $html .= "<td>{$data["oltas_datum"]}</td>";
        $html .= "<td>{$data["sorszam"]}</td>";
        $html .= "<td><div id='asseteditor{$data["id"]}'>" . $this->docAgent->showAssetEditor(DocAgent::ASSET_COVIDPASS_IMAGE, $data["id"]) . "</div></td>";
        $html .= "<td>" . $this->statuszDisplay($data["statusz"]) . "</td>";
        $html .= "<td>";
        //$html .= "<i title=\"Oltás adatainak módosítása\" onClick='modify_covid_data({$data["id"]})' class=\"fas fa-pen covid-oltas-buttons\"></i>&nbsp;&nbsp;";
        $html .= "<i title=\"Oltási bejegyzés törlése\" onClick='delete_covid_data({$data["id"]})' class=\"fas fa-trash covid-oltas-buttons\"></i>";
        $html .= "</td>";
        return $html;
    }

    private function adatForm(): string
    {
        $html = "";

        $this->readSzervezetiEgysegCsv();
        $egysegek = $this->szervezetiEgysegek[$_SESSION["helyszindata"]["domain"]];
        ksort($egysegek);

        if (isset($this->szervezetiEgysegek[$_SESSION["helyszindata"]["domain"]])) {
            $select = "<select name='szervezetiegyseg'>";
            $select .= "<option value='0'>Válasszon!</option>";
            foreach ($egysegek as $szervezetiEgyseg) {
                $select .= "<option value='{$szervezetiEgyseg["code"]}'" . ($this->user->user["szervezetiegyseg"] == $szervezetiEgyseg["code"] ? " selected" : "") . ">{$szervezetiEgyseg["name"]}</option>";
            }
            $select .= "</select>";
        }


        $html .= "<div style='border-bottom:1px solid #ccc;padding-bottom:20px;margin-bottom:20px;'>";
        $html .= "<form name='iform' method='post' enctype='multipart/form-data'>";
        $html .= "<input type='hidden' name='oltasadatok' value='1'/>";
        $html .= "<table>";
        $html .= "<tr><td>Szervezeti egység:<div style='margin-top: 5px;'>{$select}</div></td></tr>";
        $html .= "<tr><td><div style='margin-top:10px;'><input type='checkbox' name='nocovid1' value='1' " . ($this->user->user["nocovid1"] == 1 ? "checked" : "") . "/> egészségügyi okból nem oltható</div></td></tr>";
        $html .= "<tr><td><div style='margin-top:5px;'><input type='checkbox' name='nocovid2' value='1' " . ($this->user->user["nocovid2"] == 1 ? "checked" : "") . "/> nem igényel oltást</div></td></tr>";
        $html .= "<tr><td><br/><a href='#' class='newbutton' onclick='document.iform.submit();return false;'>Adatok mentése</a></td></tr>";
        $html .= "</table>";
        $html .= "</form>";
        $html .= "</div>";

        return $html;
    }

    private function oltoanyag_nev($id)
    {
        $key = array_search($id, array_column($this->oltoanyagok, 'id'));
        return $this->oltoanyagok[$key]["name"];
    }

    private function oltoanyagok($post)
    {
        $html = $extraId = "";

        if (!empty($post) && isset($post["id"])) {
            $extraId = "-" . $post["id"];
        }

        $html .= "<select class=\"design-put oltas-mezo\" name=\"vaccination-type" . $extraId . "\">";
        $html .= "  <option value=\"0\">Vakcina</option>";

        foreach ($this->oltoanyagok as $index => $value) {
            $html .= "<option " . (isset($post["vaccination-type"]) && $post["vaccination-type"] == $this->oltoanyagok[$index]["id"] ? "selected=\"true\"" : "") . " value=\"{$this->oltoanyagok[$index]["id"]}\">" . $this->oltoanyagok[$index]["name"] . "</option>";
        }

        $html .= "</select>";
        return $html;
    }

    private function napFilter($post)
    {
        $html = $extraId = "";

        if (!empty($post) && isset($post["id"])) {
            $extraId = "-" . $post["id"];
        }

        $html .= "<input class=\"napfilter\" id=\"napfilter\" placeholder=\"Oltás dátuma\" value=\"" . (isset($post["vaccine-date"]) ? $post["vaccine-date"] : "") . "\" name=\"vaccine-date{$extraId}\" value=\"\" style=\"font-size:18px;background-color:#eee;color:#444;margin-right:10px;border:1px solid #ccc;text-align:center;\" data-page=\"{$_GET["page"]}\" />";
        return $html;
    }

    private function statuszDisplay($statusz)
    {
        if ($statusz == "IN PROGRESS") {
            return "<p style=\"font-weight:bold;\">Feldolgozás alatt</p>";
        }
        if ($statusz == "APPROVED") {
            return "<p style=\"font-weight:bold;color:#8fce00\">Elfogadva</p>";
        }
        if ($statusz == "DENIED") {
            return "<p style=\"font-weight:bold;color:#f44336\">Elutasitva</p>";
        }
    }

    private function select_covid_oltas_naplo($id = null)
    {
        if (isset($_SESSION["user"]["id"])) {
            if (!empty($id)) {
                $query = sql_query("SELECT * FROM covid_oltas_naplo WHERE userid=? and id=?", array($_SESSION["user"]["id"], $id));
            } else {
                $query = sql_query("SELECT * FROM covid_oltas_naplo WHERE userid=?", array($_SESSION["user"]["id"]));
            }

            if (!empty(sql_num_rows($query))) {
                while ($fetch = sql_fetch_array($query)) $data[] = $fetch;

                return $data;
            }
        }
    }
    private function new_covid_oltas_naplo($post, $action = null)
    {
        $html = $buttons = $extraId = "";
        if (!empty($action)) {
            if ($action == "modify") {
                $buttons .= "<i title=\"Módositás mentése\" onClick='save_covid_data({$post["id"]})' class=\"fas fa-save covid-oltas-buttons\"></i>&nbsp;&nbsp;";
                $buttons .= "<i title=\"Megszakitás\" onClick='cancel_covid_data({$post["id"]})' class=\"fas fa-times covid-oltas-buttons\"></i>";
            }
            $extraId = "-" . $post["id"];
        }


        $html .= "<td>" . (!empty($action) ? $post["regdatum"] : "") . "</td>";
        $html .= "<td>" . $this->oltoanyagok($post) . "</td>";
        $html .= "<td>" . $this->napFilter($post) . "</td>";
        $html .= "<td><input type=\"number\" class=\"design-put oltas-mezo\" style=\"text-align:center\" placeholder=\"Sorszám\" value=\"" . (isset($post["serial-number"]) ? $post["serial-number"] : "") . "\" name=\"serial-number{$extraId}\" value=\"\" /></td>";


        if (isset($post["id"])) {
            $html .= "<td><div id='asseteditor{$post["id"]}'>" . $this->docAgent->showAssetEditor(DocAgent::ASSET_COVIDPASS_IMAGE, $post["id"]) . "</div></td>";
        }
        //$html .= "<td><input type=\"file\" id=\"covid-validation-image\" name=\"covid-validation-image{$extraId}\" /></td>";

        $html .= "<td></td>";
        $html .= "<td>" . (!empty($action) ? $buttons : "") . "</td>";
        return $html;
    }

    private function checkOltasData($post, $type)
    {

        $variables["new"] = array(
            "type" => "vaccination-type",
            "date" => "vaccine-date",
            "serial" => "serial-number"
        );

        $variables["modify"] = array(
            "type" => "oltas_tipus",
            "date" => "oltas_datum",
            "serial" => "sorszam"
        );

        //Ha üres a tipus:
        if (empty($post[$variables[$type]["type"]])) {
            $this->errors[] = "Az oltás tipus megadása kötelező!";
        }
        //Ha nem üres a tipus de hibás az azonositó:
        if (!empty($post[$variables[$type]["type"]])) {
            $key = array_search($post[$variables[$type]["type"]], array_column($this->oltoanyagok, "id"));
            if ($key === false) {
                $this->errors[] = "A megadott oltás tipus nem megfelelő!";
            }
        }
        //Ha üres az oltási dátum:
        if (empty($post[$variables[$type]["date"]])) {
            $this->errors[] = "Az oltás dátuma megadása kötelező!";
        }

        //Ha nem üres az oltási dátum de hibás a dátum forma:
        if (!empty($post[$variables[$type]["date"]])) {
            if (!$this->utils->validateDate($post[$variables[$type]["date"]], "Y-m-d")) {
                $this->errors[] = "Az oltás dátum formátuma hibás!";
            }
        }

        //Ha üres az oltás sorszáma:
        if (empty($post[$variables[$type]["serial"]])) {
            $this->errors[] = "Az oltás sorszámának megadása kötelező!";
        }

        //Ha üres az oltás sorszáma:
        if (!empty($post[$variables[$type]["serial"]]) && !is_numeric($post[$variables[$type]["serial"]])) {
            $this->errors[] = "Az oltás száma csak szám lehet!";
        }
    }


    private function readSzervezetiEgysegCsv()
    {
        $this->szervezetiEgysegek["hc"] = [];
        $rows = explode("\n", $this->szervezetiEgysegCsv);
        foreach ($rows as $row) {
            $fields = explode(";", $row);

            if ($fields[1] == "Hosszú név") {
                continue;
            }

            $data = [
                "code" => $fields[0],
                "name" => $fields[1],
                "short" => $fields[2]
            ];

            $this->szervezetiEgysegek["hc"][$fields[1]] = $data;
        }
    }


    private $szervezetiEgysegCsv = "Szervezeti egység kód;Hosszú név;Rövid név;;;;;;;;;1
20000;Vezérigazgató;VZIG;;;;;;;;;0
20001;Single Sky csoport;SSCS;;;;;;;;;
21000;ATM légiforgalmi igazgatóság;ATMI;;;;;;;;;
21010;AIS osztály;AISO;;;;;;;;;
21011;NOTAM csoport;NOTA;;;;;;;;;
21012;Meterológiai csoport;RMET;;;;;;;;;
21013;Kiadványszerkesztő (PUB/SD) csoport;PSDU;;;;;;;;;
21020;Módszertani és koordinációs osztály;LMKO;;;;;;;;;
21021;Módszertani csoport;MTCS;;;;;;;;;
21022;Koordinációs csoport;RKCS;;;;;;;;;
21023;Eljárás- és Légtértervezési Csoport;LTCS;;;;;;;;;
21030;ATM képzési és szolgálatvezetési osztály;ATKO;;;;;;;;;
21031;Szolgálatvezetési csoport;SVCS;;;;;;;;;
21032;ATM Képzési Csoport;AKCS;;;;;;;;;
21100;ATS főosztály;ATSF;;;;;;;;;
21101;Áramlásszervező és légtérgazdálkodó részleg;ATFC;;;;;;;;;
21102;Repülési adatkezelő és bejelentő részleg;FDRU;;;;;;;;;
21110;Körzeti irányítási osztály;KIRO;;;;;;;;;
21111;Körzeti irányító részleg;BACC;;;;;;;;;
21112;Körzeti repüléstájékoztató részleg;BFIC;;;;;;;;;
21120;Terminál irányítási osztály;TIRO;;;;;;;;;
21121;Bevezető irányító részleg;BAPP;;;;;;;;;
21122;Repülőtéri irányító részleg;BTWR;;;;;;;;;
22000;Gazdasági igazgatóság;GZDI;;;;;;;;;
22010;Beszerzési és anyaggazdálkodási osztály;BSZO;;;;;;;;;
22011;Anyaggazdálkodási csoport;AGCS;;;;;;;;;
22020;Humán erőforrás osztály;HERO;;;;;;;;;
22021;HR fejlesztési csoport;HFCS;;;;;;;;;
22022;HR üzleti partner csoport;HRCS;;;;;;;;;
22023;HR kontrolling és bérszámfejtési csoport;HKCS;;;;;;;;;
22030;Kontrolling és nemzetközi pénzügyek osztály;KNPO;;;;;;;;;
22031;Nemzetközi kontrolling csoport;NKCS;;;;;;;;;
22032;Operatív kontrolling csoport;OKCS;;;;;;;;;
22033;Vállalati kontrolling csoport;VKCS;;;;;;;;;
22040;Pénzügyi és számviteli osztály;PSZO;;;;;;;;;
22041;Adó csoport;AVCS;;;;;;;;;
22042;Eszköznyilvántartási csoport;ENCS;;;;;;;;;
22043;Főkönyvi és beszámolói csoport;FKCS;;;;;;;;;
22044;Számlakezelési csoport;SKCS;;;;;;;;;
22045;Treasury csoport;TRCS;;;;;;;;;
22050;Projektmérnök és üzemeltetési osztály;PRMO;;;;;;;;;
22051;Alapinfrastruktúra fejlesztési csoport;AFCS;;;;;;;;;
22052;Alapinfrastruktúra üzemeltetési csoport;AÜCS;;;;;;;;;
22053;Védelmi csoport;VÉCS;;;;;;;;;
23000;Technológiai igazgatóság;TCHI;;;;;;;;;
23010;ATS rendszerfejlesztési osztály;ATRO;;;;;;;;;
23011;ATS rendszerfejlesztés MATIAS csoport;ATSM;;;;;;;;;
23012;ATS rendszerfejlesztés repülőterek csoport;TWRQ;;;;;;;;;
23020;CNS osztály;CNSO;;;;;;;;;
23024;Rádiókommunikációs csoport;RÜCS;;;;;;;;;
23025;Útvonal navigációs csoport;UNCS;;;;;;;;;
23026;Távüzem radar csoport;TRAD;;;;;;;;;
23030;Infokommunikációs szolgáltatások osztály;ICTS;;;;;;;;;
23031;Kibervédelmi és információbiztonsági csoport;CDIS;;;;;;;;;
23032;Vállalati folyamat- és szolgáltatásmenedzsment csoport;EPSM;;;;;;;;;
23033;Vállalati IT infrastruktúra csoport;EITM;;;;;;;;;
23034;IT service desk csoport;ITSD;;;;;;;;;
23040;Műszaki üzemeltetési és fejlesztési osztály;MÜFO;;;;;;;;;
23041;Irányítási rendszerek csoport;IRCS;;;;;;;;;
23042;Műszaki fejlesztési csoport;MFCS;;;;;;;;;
23043;Repülőtéri rendszerek csoport;RRCS;;;;;;;;;
23044;Távközlési és hálózati csoport;THCS;;;;;;;;;
24000;Üzletfejlesztési igazgatóság;ÜFIG;;;;;;;;;
24010;Értékesítési és marketing osztály;ÉRMO;;;;;;;;;
24011;Marketing csoport;MARK;;;;;;;;;
24012;Értékesítési csoport;ÉRCS;;;;;;;;;
24020;Stratégiai és projektmenedzsment osztály;SPMO;;;;;;;;;
24030;Szakmai fejlesztési osztály;SZFO;;;;;;;;;
24031;Szimuláció és validáció csoport;SZIM;;;;;;;;;
24032;Kutatás-fejlesztési csoport;KFCS;;;;;;;;;
25000;Jogi és compliance igazgatóság;JMFI;;;;;;;;;
25001;Jogi és szabályozási csoport;JSCS;;;;;;;;;
25002;Légi- és nemzetközi jogi csoport;LNCS;;;;;;;;;
25003;Compliance csoport;COMP;;;;;;;;;
25004;Adminisztrációs csoport;ADMC;;;;;;;;;
26000;Kommunikációs és kormányzati kapcsolatok igazgatóság;KKKI;;;;;;;;;
26001;Kommunikációs csoport;KMCS;;;;;;;;;
26002;Kormányzati kapcsolatok és protokoll csoport;KPCS;;;;;;;;;
27000;Repülésbiztonsági, minőségirányítási és belső ellenőrzési igazgatóság;RMBI;;;;;;;;;
27001;SQM kockázatértékelő és eseménykivizsgáló csoport;SQKE;;;;;;;;;
27002;SQM rendszerfejlesztő és monitoring csoport;SQRM;;;;;;;;;
27003;Internal audit csoport;IACS;;;;;;;;;";
}
