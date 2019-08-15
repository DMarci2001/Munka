<?php

class Page {



    public function __construct()
    {
        new Utils();

        if (!isset($_GET["page"]) && isset($_SESSION["user"])) $_GET["page"] = "idopontfoglalas";
        if (!isset($_GET["page"])) $_GET["page"] = "main";
    }


    public function showPage() {
        header("Content-type: text/html; charset=UTF-8");

        echo $this->_htmlheader("{$_SESSION["helyszindata"]["megnev"]} online bejelentkezés");
        echo "<body>";

        echo "<div id='sound' style='display:none;'></div>";
        echo '<div class = "successful-message"><span>Loading...</span></div>';
        echo '<div class = "obj-container-framebox"></div>';
        echo "<div style='display:table;width:100%;margin:20px 0px;'>";
        echo "<div style='display:table-row;'>";
        echo "<div style='display:table-cell;vertical-align:middle;width:20px;padding-left:40px;'>";
        echo "<a href='/index.php'><img width='30' src='".Booking_Settings::SITE_LOGO."' alt='' title='".Booking_Settings::SITE_NAME."' style='margin-right:10px;' /></a>";
        echo "</div>";
        echo "<div style='display:table-cell;vertical-align:middle;'>";


        if (isset($_SESSION["user"])) {
            $rowb=sql_fetch_array(sql_query("select count(*) as hany from beutalok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and foglalasid=0"));
            $rowd=sql_fetch_array(sql_query("select count(*) as hany from dokumentumok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and megnezve is null"));
            $rowf=sql_fetch_array(sql_query("select count(*) as hany from foglalasok where paciensid='{$_SESSION["user"]["id"]}' and datum>now()"));

            echo "<div>{$webText["udvozlunk"]} {$_SESSION["user"]["nev"]}!</div>";
            echo "<a href='index.php?page=idopontfoglalas'>{$webText["idopontfoglalas"]}</a> &bull; ";
            echo "<a href='index.php?page=foglalasok'>{$webText["foglalasok"]}</a>".($rowf["hany"]>0?" <span class='ujnumber'>{$rowf["hany"]}</span>":"")." &bull; ";
            echo "<a href='index.php?page=beutalok'>{$webText["beutalok"]}</a>".($rowb["hany"]>0?" <span class='ujnumber'>{$rowb["hany"]}</span>":"")." &bull; ";
            echo "<a href='index.php?page=dokumentumok'>{$webText["dokumentumok"]}</a>".($rowd["hany"]>0?" <span class='ujnumber'>{$rowd["hany"]}</span>":"")." &bull; ";
            //echo "<a href='index.php?page=foglalasok'>foglalások</a>&nbsp; ";
            echo "<a href='index.php?page=leletek'>Leletek</a> &bull; ";
            echo "<a href='index.php?page=profil'>{$webText["adatmodositas"]}</a> &bull; ";
            echo "<a href='index.php?logout'>{$webText["kijelentkezes"]}</a>";
        } else {
            echo "<a href='index.php'>{$webText["idopontfoglalas"]}</a> &bull; ";
            if( $_SESSION["helyszindata"]["id"] != 11 ) echo "<a href='index.php?page=reg'>{$webText["regisztracio"]}</a>&nbsp;&bull;&nbsp;";
            echo "<a href='index.php?page=login'>{$webText["bejelentkezes"]}</a>";
        }
        echo "</div>";


        $link=$_SERVER["PHP_SELF"];
        if ($_SERVER["QUERY_STRING"]!="") {
            $link.="?".$_SERVER["QUERY_STRING"]."&";
        } else {
            $link.="?";
        }

        echo "<div style='display:table-cell;vertical-align:middle;padding-left:10px;padding-right:40px;text-align:right;'>";

        if (isset($_SERVER["HTTP_HOST"]) && substr_count($_SERVER["HTTP_HOST"],"anmeldung")==0) {
            echo getLangLink("hu")." ";
            echo getLangLink("en")." ";
            echo getLangLink("de")." ";
        }
        echo "</div>";

        echo "</div>";
        echo "</div>";

        echo "<div style='margin:20px;min-height:0px;'>";

        $pageFile="inc_{$_GET["page"]}.php";

        echo "<div style='background-color:#fff;border-radius:5px;'>";
        echo "<div style='padding:20px;'>";

        include($pageFile);

        echo "</div>";

        //if (in_array($_SESSION["helyszindata"]["id"],array(11,42))) {
            echo "<div style='background:#ccc;color:#555;padding:20px;border-bottom-left-radius:5px;border-bottom-right-radius:5px;'>";

            echo "<div style='float:left;margin:0px 20px 10px 0px;'><b>Budapesti egészségközpont</b><br/>1135 Budapest, Jász u. 33-35.</div>";
            echo "<div style='float:left;margin:0px 10px 10px 0px;'><b>Telefon:</b><br/>+36 1 800 9333, +36 30 633 0961</div>";
            echo "<br clear='all'/>";

            echo "&copy; ".date("Y")." HUNGÁRIA MED-M KFT.";
            echo "</div>";
        //}


        echo "</div>";

        echo "</div>";


        echo "</body>";
    }

    private function _htmlheader($pageTitle = "HMM online bejelentkezés") {
        $subdomain=$_SESSION["helyszindata"]["domain"];

        $htmlout='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        $htmlout.='<html xmlns="http://www.w3.org/1999/xhtml">';
        $htmlout.='<head>';

        /*
        if (!isset($GLOBALS["admin"])) {
            $htmlout.="<!-- Google Tag Manager -->
                <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','GTM-KGL6C9C');</script>
                <!-- End Google Tag Manager -->";

            $htmlout.="<!-- Google Tag Manager (noscript) -->
                <noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=GTM-KGL6C9C\"
                height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>
                <!-- End Google Tag Manager (noscript) -->";
        }
        */

        $htmlout.="<meta http-equiv='Content-Type' content='text/html; charset=utf-8'><title>{$pageTitle}</title>";
        $favicon="/images/hmm_favicon.png";
        if (is_file("images/logo_{$subdomain}.png") || is_file("../images/logo_{$subdomain}.png")) $favicon="/images/logo_{$subdomain}.png";

        $htmlout.="<link rel='shortcut icon' type='image/png' href='{$favicon}' />";
        $htmlout.="<link rel='stylesheet' type='text/css' href='index.css' />";
        $htmlout.='<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">';
        $htmlout.='<script type="text/javascript" src="//code.jquery.com/jquery-latest.js"></script>';
        $htmlout.='<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>';
        $htmlout.='<script type="text/javascript" src="ajax.js"></script>';
        $htmlout.="<script src='https://www.google.com/recaptcha/api.js?hl={$_COOKIE["lang"]}'></script>";
        $htmlout.='<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.1/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';
        $htmlout.="<link rel='stylesheet' href='images/webfonts/roboto_regular_hungarian/stylesheet.css' type='text/css' charset='utf-8' async/>";
        $htmlout.="<link rel='stylesheet' href='images/webfonts/roboto_bold_hungarian/stylesheet.css' type='text/css' charset='utf-8' async/>";
        $htmlout.="<link rel='stylesheet' href='images/webfonts/roboto_light_hungarian/stylesheet.css' type='text/css' charset='utf-8' async/>";

        $htmlout.="</head>";
        return $htmlout;
    }

}