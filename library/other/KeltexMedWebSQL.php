<?php

class KeltexMedWebSQL {
    const KWEB_DB_NAME = 'keltexmedweb';
    const KWEB_DB_USER = 'keltexmedweb';
    const KWEB_DB_PASSWORD = 'ungeaBoami8ahf8';
    const KWEB_DB_HOST = 'localhost';

    public function __construct()
    {
        if (!isset($GLOBALS["kwebdb"])) {
            $GLOBALS["kwebdb"] = new PDO('mysql:host=' . self::KWEB_DB_HOST . ';dbname=' . self::KWEB_DB_NAME . ';charset=utf8', self::KWEB_DB_USER, self::KWEB_DB_PASSWORD);
            $GLOBALS["kwebdb"]->prepare("SET NAMES utf8")->execute();
            $GLOBALS["kwebdb"]->prepare("SET CHARACTER SET utf8")->execute();
            $GLOBALS["kwebdb"]->prepare("SET COLLATION_CONNECTION='utf8_unicode_ci'")->execute();
        }
    }


    public function sqlQuery($q,$params=null) {
        //$stmt = $GLOBALS["kwebdb"]->query($q);
        $stmt = $GLOBALS["kwebdb"]->prepare($q);
        $stmt->execute($params);
        $error = $stmt->errorInfo();
        if ($error[2] != "") print_r($error);
        return $stmt;
    }

    public function sqlFetchArray($stmt) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function sqlNumRows($stmt) {
        return $stmt->rowCount();
    }

    public function sqlInsertId() {
        return $GLOBALS["kwebdb"]->lastInsertId();
    }


    public function loadWebShopOrder() {
        if (isset($_GET["keltexmedhuorder"]) && isset($_GET["keltexmedhuproduct"])) {
            if ($keltexmedhuOrderData = $this->sqlQuery("select o.*, oi.orderid, p.alias from orders o 
                    left join orderitems oi on o.id=oi.orderid 
                    left join products p on p.id=oi.productid 
                    where o.sessionid=? and oi.productid=?", [$_GET["keltexmedhuorder"], $_GET["keltexmedhuproduct"]])->fetch(PDO::FETCH_ASSOC)) {
                if ($this->getTipusByAlias($keltexmedhuOrderData["alias"])) {
                    $_SESSION["keltexmedhuorderdata"] = $keltexmedhuOrderData;
                }
            }
        }
    }

    public function fillBookingDatas() {
        if ($tipus = $this->getTipusByAlias($_SESSION["keltexmedhuorderdata"]["alias"])) {
            $_POST["helyszin"]    = Booking_Constants::DEFAULT_PLACE_IDS[0];
            $_POST["szurestipus"] = $tipus["id"];
            $_POST["email"]       = $_SESSION["keltexmedhuorderdata"]["email"];
            $_POST["nev"]         = $_SESSION["keltexmedhuorderdata"]["nev"];
            $_POST["telefon"]     = $_SESSION["keltexmedhuorderdata"]["telefon"];
            $_POST["irsz"]        = $_SESSION["keltexmedhuorderdata"]["szamlairsz"];
            $_POST["varos"]       = $_SESSION["keltexmedhuorderdata"]["szamlavaros"];
            $_POST["utca"]        = $_SESSION["keltexmedhuorderdata"]["szamlacim"];
        }
    }

    public function getTipusByAlias($alias) {
        return sql_query("select id, megnev from szurestipusok t where t.webalias=?", [$alias])->fetch(PDO::FETCH_ASSOC);
    }




}