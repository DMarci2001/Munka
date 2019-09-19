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

        if (empty($user)) {
            $result = false;
        }

        if (!empty($user) && $user["status"] == 0) {
            $result = false;
        }

        if (!empty($user) && $user["auth2fac"] == 1 && !isset($_SESSION["2facomplete"])) {
            $result = false;
        }

        return $result;

    }

}