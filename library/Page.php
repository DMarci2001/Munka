<?php

class Page {

    private $utils;
    private $lang;
    public $page;
    public $companyService;
    public $user;

    public function __construct()
    {
        $this->companyService = new CompanyService();
        $this->user = new User();
        $this->utils = new Utils();
        $this->lang = new Lang();

        $this->page = $this->_getActualPage();
    }

    private function _getActualPage() {
        if (isset($_POST["page"])) {
            $_GET["page"] =  $_POST["page"];
        }
        if (!isset($_GET["page"]) && isset($_SESSION["user"])) {
            $_GET["page"] = "booking";
        }
        if (!isset($_GET["page"])) {
            $_GET["page"] = "booking";
        }

        $pageName = ucfirst(str_replace("_","",$_GET["page"]))."Page";
        if (class_exists($pageName)) {
            $page = new $pageName;
        } else {
            die("Error, page not found!");
        }

        if (isset($_SESSION["user"]) && $_SESSION["user"]["validated"] == 0) {
            $page = new ValidateLoginPage();
        }
        return $page;
    }

    public function showPage() {
        $webText = $this->lang->webText;

        header("Content-type: text/html; charset=UTF-8");

        echo $this->utils->htmlheader("{$_SESSION["helyszindata"]["megnev"]} online bejelentkezés");
        echo "<body>";

        echo "<div class='pagecontainer'>";
        echo $this->_pageMenu();

        echo "<div class='contentcontainer'>";
        //echo $this->_pageHead();
        echo "<div style='padding:20px;'>";
        $this->page->showPage();
        echo "</div>";
        echo $this->_pageFooter();
        echo "</div>";
        echo "</div>";

        echo "</body>";
    }


    private function _pageHead() {
        return "<div class='fejlecdiv'></div>";
    }

    private function _pageMenu() {
        $webText = $this->lang->webText;

        $html = "";

        $html.= "<div id='sound' style='display:none;'></div>";
        $html.= '<div class="successful-message"><span>Loading...</span></div>';
        $html.= '<div class="obj-container-framebox"></div>';

        $html.= "<div class='headercontainer'>";
        $html.= "<div style='display:table;width:100%;'>";
        $html.= "<div style='display:table-row;'>";
        $html.= "<div style='display:table-cell;vertical-align:middle;width:20px;'>";

        if ($_SESSION["helyszindata"]["domain"] == "bejelentkezes" && substr_count($_SERVER["HTTP_HOST"], "keltexmed") == 0) {
            $html.= "<a href='index.php'><img width='120' src='/images/logo-retina.png' alt='' title='" .Booking_Constants::SITE_NAME."' style='margin-right:20px;' /></a>";
        } else {
            $html.= "<a href='index.php'><img width='30' src='" .Booking_Constants::SITE_LOGO."' alt='' title='".Booking_Constants::SITE_NAME."' style='margin-right:10px;' /></a>";
        }

        $html.= "</div>";
        $html.= "<div style='display:table-cell;vertical-align:middle;'>";


        if (isset($_SESSION["user"])) {
            $rowb = sql_fetch_array(sql_query("select count(*) as hany from beutalok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and foglalasid=0"));
            $rowd = sql_fetch_array(sql_query("select count(*) as hany from dokumentumok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and megnezve is null"));
            $rowf = sql_fetch_array(sql_query("select count(*) as hany from foglalasok where paciensid='{$_SESSION["user"]["id"]}' and datum>now()"));

            $html.= "<div>{$webText["udvozlunk"]} {$_SESSION["user"]["nev"]}!</div>";
            $html.= "<a class='toplink' href='index.php?page=booking'>".ucfirst($webText["idopontfoglalas"])."</a> &bull; ";
            $html.= "<a class='toplink' href='index.php?page=bookinglist'>".ucfirst($webText["foglalasok"])."</a>".($rowf["hany"]>0?" <span class='ujnumber'>{$rowf["hany"]}</span>":"")." &bull; ";
            $html.= "<a class='toplink' href='index.php?page=beutalok'>".ucfirst($webText["beutalok"])."</a>".($rowb["hany"]>0?" <span class='ujnumber'>{$rowb["hany"]}</span>":"")." &bull; ";
            //$html.= "<a class='toplink' href='index.php?page=documents'>".ucfirst($webText["dokumentumok"])."</a>".($rowd["hany"]>0?" <span class='ujnumber'>{$rowd["hany"]}</span>":"")." &bull; ";
            //leletek oldal határozatlan ideig szüntetel
            //$html.= "<a class='toplink' href='index.php?page=leletek'>".ucfirst($this->lang->getText("leletek","leletek"))."</a> &bull; ";
            $html.= "<a class='toplink' href='index.php?page=profile'>".ucfirst($webText["adatmodositas"])."</a> &bull; ";
            $html.= "<a class='toplink' href='index.php?logout'>".ucfirst($webText["kijelentkezes"])."</a>";
        } else {
            $html.= "<a class='toplink' href='index.php?page=booking'>".ucfirst($webText["idopontfoglalas"])."</a>";
            //$html.= "&nbsp;&bull;&nbsp;<a class='toplink' href='index.php?page=registration'>".ucfirst($webText["regisztracio"])."</a>";
            //$html.= "&nbsp;&bull;&nbsp;<a class='toplink' href='index.php?page=login'>".ucfirst($webText["bejelentkezes"])."</a>";
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

        $html.= "<div style='float:left;margin:0px 40px 10px 0px;'>".Booking_Constants::FOOTER_ADDRESS_PARAM."</div>";
        $html.= "<div style='float:left;margin:0px 10px 10px 0px;'>".Booking_Constants::FOOTER_CONTACT_PARAM."</div>";
        $html.= "<br clear='all'/>";

        $html.= "&copy; ".date("Y")." ".Booking_Constants::FOOTER_COPYRIGHT;
        $html.= "</div>";
        return $html;
    }


}