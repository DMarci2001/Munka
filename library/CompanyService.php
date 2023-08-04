<?php

class CompanyService {

    const CIB_ID            = 6;
    const UNIQA_ID          = 200;
    const HUNGAROCONTROL_ID = 201;
    const WABERERS_ID       = 129;
    const BP_ID             = 74;
    const ASTOTEC_ID        = 664;

    public static array $makIds = [373, 374, 375, 376];

    public function __construct()
    {
        //cgi indítás esetén nem kell
        if (!substr_count(php_sapi_name(),"cgi")) {
            $this->_domainProcess();
        }
    }

    private function _domainProcess() {
        $d = $GLOBALS["subdomain"] = $this->_getSubDomain();

        if ($d == "ertekeles") {
            $GLOBALS["ertekeles"] = 1;
            return;
        }

        if ($d == "keltexmed" || $d == "bejelentkezesuj" || $d == "demo") {
            $d = "bejelentkezes";
        }
        if($d=="marciteszt"){
            $d="astotec-teszt";
        }

        if (!$_SESSION["helyszindata"] = sql_fetch_array(sql_query("select * from cegek where (CONCAT(',',RTRIM(domain),',') LIKE CONCAT('%,',?,',%') or tesztdomain=?) and aktiv=1",array($d,$d)))) {

            if ($_SESSION["helyszindata"] = sql_fetch_array(sql_query("select * from cegek where (CONCAT(',',RTRIM(domain),',') LIKE CONCAT('%,',?,',%') or tesztdomain=?) and aktiv=1",array("bejelentkezes","bejelentkezes")))) {
                if ($d == "erkezes") {
                    $_GET["page"] = "covidform";
                    return;
                }

                if ($d == "suzukiform") {
                    if (!isset($GLOBALS["admin"])) {
                        $_GET["page"] = "suzukiform";
                    }
                    return;
                }

                if ($d == "mscoltas") {
                    if (!isset($GLOBALS["admin"])) {
                        $_GET["page"] = "oltasigenyfelmeres";
                    }
                    return;
                }

                if (in_array($d, ["secl", "samoo", "s-1", "testoltas", "sdi", "cksolution", "theductkft", "ekg", "janssen", "jkgroup", "sekwang", "gih", "daeha", "topengineering", "amsdesign20group", "uth", "irs", "hallimprecision", "ooksan", "shinsung", "hyojin"])) {
                    if (!isset($GLOBALS["admin"])) {
                        $_GET["page"] = "oltasjelentkezes";
                    }
                    return;
                }

                if ($d == "elsosegelyteszt") {
                    if (!isset($GLOBALS["admin"])) {
                        $_GET["page"] = "elsosegelyvizsga";
                    }
                    return;
                }
            }

            unset($_SESSION["helyszindata"]);
            die("Domain nem található!");
        }
    }

    private function _getSubDomain() {
        $domain="";
        if (isset($_SERVER["HTTP_HOST"])) {
            $domain = str_replace("www.","",$_SERVER["HTTP_HOST"]);
            $domain = substr($domain,0,strpos($domain,"."));
        }
        return $domain;
    }

    public static function isHungarocontrol():bool {
        return $_SESSION["helyszindata"]["domain"] == "hc";
    }

    public static function isUniqa():bool {
        return $_SESSION["helyszindata"]["domain"] == "uniqa";
    }

    public static function isCib($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "cib" || $companyId == self::CIB_ID;
    }

    public static function isWaberers($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "wszl" || $companyId == self::WABERERS_ID;
    }

    public static function isBP($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "bp";
    }

    public static function isFesztivalEgyeb($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "szigetegyeb";
    }

    public static function isMagyarAllamkincstar($companyId = 0):bool {
        return in_array($companyId, self::$makIds) || substr_count($_SESSION["helyszindata"]["domain"], "mak-");
    }

    public static function isAstostecCompany($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "astotec-teszt";
    }

    const FESZTIVAL_ALKALMASSAGI_DEFAULT_TEXT = "Időszakos

Munkakör: 

Panasz: nincs
Ismert krónikus betegség: nem ismert
Gyógyszerszedés: nincs

Allergia: nincs
Cave: nincs

 
Státusz:
Kp táplált, exanthema, oedema, icterus, cyanosis nincs, sclera fehér. Részarányos mellkas, puha sejtes alaplégzés. Tiszta, ritmusos, kellően ékelt szívhangok, zörej nem hallható. Has puha, betapintható, kóros rezisztencia nem tapintható, nyomásérzékenységet nem jelez, máj-lép nem tapintható. Mozgásszervek alakilag és funkcionálisan épek. Durva neurológiai eltérés nincs, pupillák o, =.
Tüdőszűrés: neg ()
V: 1.0 1.0 .    KV: Cs IV
";

    public static $fesztivalOnkentesQuestions = [
        1 => ["type" => "igennem", "required" => true, "question" => "Allergiája van?", "question_hu" => "Allergiája van?", "question_en" => "Do you have allergies?", "question_de" => "Allergiája van?"],
        2 => ["type" => "igennem", "required" => true, "question" => "Gyógyszerérzékenysége van?", "question_hu" => "Gyógyszerérzékenysége van?", "question_en" => "Do you have a drug sensitivity?", "question_de" => "Gyógyszerérzékenysége van?"],
        3 => ["type" => "igennem", "required" => true, "question" => "Szed rendszeresen gyógyszert?", "question_hu" => "Szed rendszeresen gyógyszert?", "question_en" => "Do you take medicine regularly?", "question_de" => "Szed rendszeresen gyógyszert?"],
        4 => ["type" => "igennem", "required" => true, "question" => "Kezelik valamilyen betegséggel?", "question_hu" => "Kezelik valamilyen betegséggel?", "question_en" => "Are you being treated for any disease?", "question_de" => "Kezelik valamilyen betegséggel?"],
    ];

    public static function fesztivalCompanyIds():array {
        return [138, 275, 261, 318, 322, 639, 285, 286, 647, 632, 635];
    }

    public static function isFesztivalCompany($companyId = 0):bool {
        if ($companyId != 0) {
            return in_array($companyId, self::fesztivalCompanyIds()) && Booking_Constants::SQL_DB == "hungariamed";
        }
        return $_SESSION["helyszindata"]["domain"] == "annagora-gastro" || $_SESSION["helyszindata"]["domain"] == "aquapark-balatonfured" || $_SESSION["helyszindata"]["domain"] == "aquaticdipo" || $_SESSION["helyszindata"]["domain"] == "crewnmore" || $_SESSION["helyszindata"]["domain"] == "festfree" || $_SESSION["helyszindata"]["domain"] == "monofactura" || $_SESSION["helyszindata"]["domain"] == "etalon" || $_SESSION["helyszindata"]["domain"] == "fesztivalonkentes" || $_SESSION["helyszindata"]["domain"] == "szigetideny" || $_SESSION["helyszindata"]["domain"] == "tranzorg" || $_SESSION["helyszindata"]["domain"] == "szigetegyeb" || $_SESSION["helyszindata"]["domain"] == "colorcrew";
    }

    public function fillMAKPaciensData($data) {
        $data["error"] = "";
        if (self::isMagyarAllamkincstar() && !empty(trim($data["taj"]))) {

            if ($paciensData = sql_query("select * from felhasznalok where taj=? and cegid in (".implode(",", self::$makIds).")", [$data["taj"]])->fetch(PDO::FETCH_ASSOC)) {
                //$paciensData["email"] = "jnsmobil@gmail.com";
                $data["paciensid"] = $paciensData["id"];
                $data["nev"] = $paciensData["nev"];
                $data["email"] = $paciensData["email"];
                $data["telefon"] = $paciensData["telefon"];
                $data["szuldatum"] = $paciensData["szuldatum"];
                $data["szulhely"] = $paciensData["szulhely"];
                $data["anyjaneve"] = $paciensData["anyjaneve"];
                $data["neme"] = $paciensData["neme"];
                $data["irsz"] = $paciensData["irsz"];
                $data["varos"] = $paciensData["varos"];
                $data["utca"] = $paciensData["utca"];
                $data["munkakor"] = $paciensData["munkakor"];
            } else {
                $data["error"] = "TAJ szám alapján nem található MAK ügyfél!";
            }

        }
        return $data;
    }

}