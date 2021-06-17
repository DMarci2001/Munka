<?php

class CompanyService {

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

        if (!$_SESSION["helyszindata"] = sql_fetch_array(sql_query("select * from cegek where CONCAT(',',RTRIM(domain),',') LIKE CONCAT('%,',?,',%') or tesztdomain=?",array($d,$d)))) {

            if ($_SESSION["helyszindata"] = sql_fetch_array(sql_query("select * from cegek where CONCAT(',',RTRIM(domain),',') LIKE CONCAT('%,',?,',%') or tesztdomain=?",array("bejelentkezes","bejelentkezes")))) {
                if ($d == "erkezes") {
                    $_GET["page"] = "covidform";
                    return;
                }

                if ($d == "mscoltas") {
                    if (!isset($GLOBALS["admin"])) {
                        $_GET["page"] = "oltasigenyfelmeres";
                    }
                    return;
                }

                if (in_array($d, ["secl", "samoo", "s-1", "testoltas", "sdi", "cksolution", "theductkft", "ekg", "janssen", "jkgroup", "sekwang", "gih", "daeha", "topengineering", "amsdesign20group", "uth", "irs", "hallimprecision"])) {
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


}