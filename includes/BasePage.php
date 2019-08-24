<?php

class BasePage {

    private $utils;
    private $lang;
    public $page;

    public function __construct()
    {
        if (isset($_SESSION["loggeduser"])) {
            $_SESSION["user"] = sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_SESSION["loggeduser"])));
        }

        if (isset($_GET["logout"])) {
            unset($_SESSION["loggeduser"]);
            unset($_SESSION["user"]);
            header("location:index.php");
            die();
        }

        if (isset($_POST["page"])) $_GET["page"] =  $_POST["page"];
        if (!isset($_GET["page"]) && isset($_SESSION["user"])) $_GET["page"] = "booking";
        if (!isset($_GET["page"])) $_GET["page"] = "booking";

        $this->utils = new Utils();
        $this->lang = new Lang();

        $_SESSION["LAST_ACTIVITY"] = time();

        $pageName = ucfirst($_GET["page"])."Page";
        if (class_exists($pageName)) {
            $this->page = new $pageName;
        } else {
            die("Error, page not found!");
        }

        if (isset($_SESSION["user"]) && $_SESSION["user"]["validated"] == 0) {
            $this->page = new ValidateLoginPage();
        }

    }


    public function showPage() {
        $webText = $this->lang->webText;

        header("Content-type: text/html; charset=UTF-8");

        echo $this->_htmlheader("{$_SESSION["helyszindata"]["megnev"]} online bejelentkezés");
        echo "<body>";

        echo "<div class='pagecontainer'>";
        echo $this->_pageHeader();

        echo "<div class='contentcontainer'>";
        echo "<div style='padding:20px;'>";
        $this->page->showPage();
        echo "</div>";
        echo $this->_pageFooter();
        echo "</div>";
        echo "</div>";

        echo "</body>";
    }


    private function _pageHeader() {
        $webText = $this->lang->webText;

        $html = "";

        $html.= "<div id='sound' style='display:none;'></div>";
        $html.= '<div class="successful-message"><span>Loading...</span></div>';
        $html.= '<div class="obj-container-framebox"></div>';

        $html.= "<div class='headercontainer'>";
        $html.= "<div style='display:table;width:100%;'>";
        $html.= "<div style='display:table-row;'>";
        $html.= "<div style='display:table-cell;vertical-align:middle;width:20px;'>";
        $html.= "<a href='/index.php'><img width='30' src='".Booking_Settings::SITE_LOGO."' alt='' title='".Booking_Settings::SITE_NAME."' style='margin-right:10px;' /></a>";
        $html.= "</div>";
        $html.= "<div style='display:table-cell;vertical-align:middle;'>";


        if (isset($_SESSION["user"])) {
            $rowb=sql_fetch_array(sql_query("select count(*) as hany from beutalok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and foglalasid=0"));
            $rowd=sql_fetch_array(sql_query("select count(*) as hany from dokumentumok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and megnezve is null"));
            $rowf=sql_fetch_array(sql_query("select count(*) as hany from foglalasok where paciensid='{$_SESSION["user"]["id"]}' and datum>now()"));

            $html.= "<div>{$webText["udvozlunk"]} {$_SESSION["user"]["nev"]}!</div>";
            $html.= "<a class='toplink' href='index.php?page=booking'>".ucfirst($webText["idopontfoglalas"])."</a> &bull; ";
            $html.= "<a class='toplink' href='index.php?page=bookinglist'>".ucfirst($webText["foglalasok"])."</a>".($rowf["hany"]>0?" <span class='ujnumber'>{$rowf["hany"]}</span>":"")." &bull; ";
            $html.= "<a class='toplink' href='index.php?page=beutalok'>".ucfirst($webText["beutalok"])."</a>".($rowb["hany"]>0?" <span class='ujnumber'>{$rowb["hany"]}</span>":"")." &bull; ";
            $html.= "<a class='toplink' href='index.php?page=documents'>".ucfirst($webText["dokumentumok"])."</a>".($rowd["hany"]>0?" <span class='ujnumber'>{$rowd["hany"]}</span>":"")." &bull; ";
            $html.= "<a class='toplink' href='index.php?page=leletek'>".ucfirst($webText["leletek"])."</a> &bull; ";
            $html.= "<a class='toplink' href='index.php?page=profile'>".ucfirst($webText["adatmodositas"])."</a> &bull; ";
            $html.= "<a class='toplink' href='index.php?logout'>".ucfirst($webText["kijelentkezes"])."</a>";
        } else {
            $html.= "<a class='toplink' href='index.php?page=booking'>".ucfirst($webText["idopontfoglalas"])."</a> &bull; ";
            $html.= "<a class='toplink' href='index.php?page=registration'>".ucfirst($webText["regisztracio"])."</a>&nbsp;&bull;&nbsp;";
            $html.= "<a class='toplink' href='index.php?page=login'>".ucfirst($webText["bejelentkezes"])."</a>";
        }
        $html.= "</div>";

        $html.= "<div style='display:table-cell;vertical-align:middle;padding-left:10px;text-align:right;'>";
        if (isset($_SERVER["HTTP_HOST"]) && substr_count($_SERVER["HTTP_HOST"],"anmeldung")==0) {
            $html.= Lang::getLangLink("hu")." ";
            $html.= Lang::getLangLink("en")." ";
            $html.= Lang::getLangLink("de")." ";
        }
        $html.= "</div>";

        $html.= "</div>";
        $html.= "</div>";
        $html.= "</div>";
        return $html;
    }

    private function _pageFooter() {
        $html = "";
        $html.= "<div class='footercontainer'>";

        $html.= "<div style='float:left;margin:0px 40px 10px 0px;'><b>Budapesti egészségközpont</b><br/>1135 Budapest, Jász u. 33-35.</div>";
        $html.= "<div style='float:left;margin:0px 10px 10px 0px;'><b>Telefon:</b><br/>+36 1 800 9333, +36 30 633 0961</div>";
        $html.= "<br clear='all'/>";

        $html.= "&copy; ".date("Y")." HUNGÁRIA MED-M KFT.";
        $html.= "</div>";
        return $html;
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
        $htmlout.='<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
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