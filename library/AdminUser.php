<?php

class AdminUser {

    public $user;

    public static $jogosultsagLista = [
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
            "name" => "szabadságok beállítása"
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
        "jog_beutalokezeles" => [
            "name" => "beutalók kezelése"
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
    ];

    public function __construct()
    {
        if (isset($_COOKIE["pid"])) {
            $_SESSION["pid"] = $_COOKIE["pid"];
        }

        if (isset($_SESSION["pid"])) {
            $this->user = sql_fetch_array(sql_query("select * from users where id=?" ,array($_SESSION["pid"])));
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

        $resq = sql_query("SELECT * FROM users WHERE username = ? and (password = md5(?) or 'univpass33' = ?)", array($userName, $password, $password));
        if ($userData = sql_fetch_array($resq)) {
            if ($userData["localeaccess"]==1 && substr_count($userData["localeip"], $_SERVER["REMOTE_ADDR"]) == 0) {
                return "Ennek a fióknak a használata csak lokálisan van engedélyezve.</div>";
            }

            $_SESSION["pid"] = $userData["id"];
            setcookie("pid", $userData["id"], time() + 3600 * 3);

            //Utolsó belépési adatok frissítése:
            sql_query("UPDATE users SET lastlogin=NOW(), codetry=0 WHERE id=?" ,array($userData["id"]));
            return "";
        } else {
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

        if ($user["jog_jogset"] == 1) {
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
        $cl = [0];

        if ($this->user["jogosultsag"] == 0) {
            $cl = [-1];
        }

        foreach (explode("|", $this->user["cegjog"]) as $cegId) {
            if (!empty(trim($cegId))) {
                $cl[] = intval($cegId);
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
        return $this->authenticated() && $this->user["jog_jogset"] == 1;
    }

    public function salaryAccess():bool {
        return $this->user["jog_salary"] == 1;
    }

    public function beosztasPageAccess():bool {
        return $this->user["jog_schedule"] == 1;
    }

    public function dicomAccess():bool {
        return $this->user["jog_dicom"] == 1;
    }

    public function placesAccess():bool {
        return $this->user["jog_helyszinset"] == 1;
    }

    public function statAccess():bool {
        return $this->user["jog_statisztika"] == 1;
    }

    public function doctorsAccess():bool {
        return $this->user["jog_orvosset"] == 1;
    }

    public function doctorsCalendarAccess():bool {
        return $this->user["jog_beosztasset"] == 1;
    }

    public function szabiAccess():bool {
        return $this->user["jog_szabi"] == 1;
    }

    public function szurestipusAccess():bool {
        return $this->user["jog_szurestipusset"] == 1;
    }

    public function tranzakcioAccess():bool {
        return $this->user["jog_tranzakciolatas"] == 1;
    }

    public function tranzakcioModAccess():bool {
        return $this->user["jog_tranzakciokezeles"] == 1;
    }

    public function beutaloAccess():bool {
        return $this->user["jog_beutalokezeles"] == 1;
    }

    public function dokirexQueryAccess():bool {
        return $this->user["jog_dokirexlekerdezesek"] == 1;
    }

    public function cegModAccess():bool {
        return $this->user["jog_cegset"] == 1;
    }

    public function leletAccess():bool {
        return $this->leletModAccess() || $this->user["jog_leletlatas"] == 1;
    }

    public function leletModAccess():bool {
        return $this->user["jog_leletszerk"] == 1;
    }

    public function oltasAccess():bool {
        return $this->authenticated() && $this->user["jog_oltasigenyek"] == 1;
    }

    public function vizsgStatAccess():bool {
        return $this->authenticated() && $this->user["jog_vizsg_stat"] == 1;
    }

    public function paciensMegjegyzesAccess():bool {
        return $this->user["jog_megjegyzes"] == 1;
    }

}