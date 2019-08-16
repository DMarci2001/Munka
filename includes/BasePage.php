<?php

class BasePage {

    private $utils;
    private $lang;
    public $page;

    public function __construct()
    {
        if (!isset($_GET["page"]) && isset($_SESSION["user"])) $_GET["page"] = "booking";
        if (!isset($_GET["page"])) $_GET["page"] = "booking";

        $this->utils = new Utils();
        $this->lang = new Lang();

        if ($_GET["page"] == "booking") $this->page = new BookingPage();
        if ($_GET["page"] == "bookingsuccessful") $this->page = new BookingSuccessfulPage();
        if ($_GET["page"] == "bookingvalidate") $this->page = new BookingValidatePage();
        if ($_GET["page"] == "bookingdelete") $this->page = new BookingDeletePage();
        if ($_GET["page"] == "bookingdeletesuccessful") $this->page = new BookingDeleteSuccessfulPage();
        if ($_GET["page"] == "login") $this->page = new LoginPage();
        if ($_GET["page"] == "reg") $this->page = new RegistrationPage();
        if ($_GET["page"] == "profile") $this->page = new ProfilePage();
        if ($_GET["page"] == "passwordsend") $this->page = new PasswordSendPage();
        if ($_GET["page"] == "validationsuccessful") $this->page = new ValidationSuccessfulPage();

        if (isset($_SESSION["user"]) && $_SESSION["user"]["validated"] == 0) {
            $this->page = new ValidateLoginPage();
        }
    }


    public function showPage() {
        $webText = $this->lang->webText;

        header("Content-type: text/html; charset=UTF-8");

        echo $this->_htmlheader("{$_SESSION["helyszindata"]["megnev"]} online bejelentkezés");
        echo "<body>";

        echo "<div id='sound' style='display:none;'></div>";
        echo '<div class="successful-message"><span>Loading...</span></div>';
        echo '<div class="obj-container-framebox"></div>';

        echo $this->_pageHeader();

        echo "<div style='margin:20px;min-height:0px;'>";
        echo "<div style='background-color:#fff;border-radius:5px;'>";
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
        $html.= "<div style='display:table;width:100%;margin:20px 0px;'>";
        $html.= "<div style='display:table-row;'>";
        $html.= "<div style='display:table-cell;vertical-align:middle;width:20px;padding-left:40px;'>";
        $html.= "<a href='/index.php'><img width='30' src='".Booking_Settings::SITE_LOGO."' alt='' title='".Booking_Settings::SITE_NAME."' style='margin-right:10px;' /></a>";
        $html.= "</div>";
        $html.= "<div style='display:table-cell;vertical-align:middle;'>";


        if (isset($_SESSION["user"])) {
            $rowb=sql_fetch_array(sql_query("select count(*) as hany from beutalok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and foglalasid=0"));
            $rowd=sql_fetch_array(sql_query("select count(*) as hany from dokumentumok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and megnezve is null"));
            $rowf=sql_fetch_array(sql_query("select count(*) as hany from foglalasok where paciensid='{$_SESSION["user"]["id"]}' and datum>now()"));

            $html.= "<div>{$webText["udvozlunk"]} {$_SESSION["user"]["nev"]}!</div>";
            $html.= "<a href='index.php?page=booking'>".ucfirst($webText["idopontfoglalas"])."</a> &bull; ";
            $html.= "<a href='index.php?page=foglalasok'>".ucfirst($webText["foglalasok"])."</a>".($rowf["hany"]>0?" <span class='ujnumber'>{$rowf["hany"]}</span>":"")." &bull; ";
            $html.= "<a href='index.php?page=beutalok'>".ucfirst($webText["beutalok"])."</a>".($rowb["hany"]>0?" <span class='ujnumber'>{$rowb["hany"]}</span>":"")." &bull; ";
            $html.= "<a href='index.php?page=dokumentumok'>".ucfirst($webText["dokumentumok"])."</a>".($rowd["hany"]>0?" <span class='ujnumber'>{$rowd["hany"]}</span>":"")." &bull; ";
            $html.= "<a href='index.php?page=leletek'>Leletek</a> &bull; ";
            $html.= "<a href='index.php?page=profile'>".ucfirst($webText["adatmodositas"])."</a> &bull; ";
            $html.= "<a href='index.php?logout'>".ucfirst($webText["kijelentkezes"])."</a>";
        } else {
            $html.= "<a href='index.php?page=booking'>".ucfirst($webText["idopontfoglalas"])."</a> &bull; ";
            $html.= "<a href='index.php?page=reg'>".ucfirst($webText["regisztracio"])."</a>&nbsp;&bull;&nbsp;";
            $html.= "<a href='index.php?page=login'>".ucfirst($webText["bejelentkezes"])."</a>";
        }
        $html.= "</div>";

        $html.= "<div style='display:table-cell;vertical-align:middle;padding-left:10px;padding-right:40px;text-align:right;'>";
        if (isset($_SERVER["HTTP_HOST"]) && substr_count($_SERVER["HTTP_HOST"],"anmeldung")==0) {
            $html.= Lang::getLangLink("hu")." ";
            $html.= Lang::getLangLink("en")." ";
            $html.= Lang::getLangLink("de")." ";
        }
        $html.= "</div>";

        $html.= "</div>";
        $html.= "</div>";
        return $html;
    }

    private function _pageFooter() {
        $html = "";
        $html.= "<div style='background:#ccc;color:#555;padding:20px;border-bottom-left-radius:5px;border-bottom-right-radius:5px;'>";

        $html.= "<div style='float:left;margin:0px 20px 10px 0px;'><b>Budapesti egészségközpont</b><br/>1135 Budapest, Jász u. 33-35.</div>";
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