<?php

class User {

    public $user;

    public function __construct()
    {

        if (isset($_SESSION["loggeduser"])) {
            $this->user = sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_SESSION["loggeduser"])));
            $_SESSION["user"] = $this->user;
        }

        if (isset($_GET["logout"])) {
            $this->_logOut();
            header("location:index.php");
            die();
        }

        if (isset($_GET["debuguser"]) && $_GET["debuguser"] == 1555) {
            $_SESSION["debuguser"] = 1;
        }

    }

    private function _logOut() {
        unset($_SESSION["loggeduser"]);
        unset($_SESSION["user"]);
    }

    public static function debugUser() {
        return isset($_SESSION["debuguser"]) || session_id() == "mroqsati011us8d3coi7rr3vvj";
    }
}