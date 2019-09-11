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
        $d = $this->_getSubDomain();

        if ($d == "ertekeles") {
            $GLOBALS["ertekeles"] = 1;
            return;
        }

        if ($d == "keltexmed") {
            $d = "bejelentkezes";
        }
        if ($d!="admin") {
            if (!$_SESSION["helyszindata"] = sql_fetch_array(sql_query("select * from cegek where CONCAT(',',RTRIM(domain),',') LIKE CONCAT('%,',?,',%') or tesztdomain=?",array($d,$d)))) {
                unset($_SESSION["helyszindata"]);
                die("Domain nem található!");
            }
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