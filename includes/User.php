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

    }

    private function _logOut() {
        unset($_SESSION["loggeduser"]);
        unset($_SESSION["user"]);
    }

}