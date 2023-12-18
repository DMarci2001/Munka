<?php

class Page
{

    private Utils $utils;
    private Lang $lang;
    public $page;
    public CompanyService $companyService;
    public User $user;

    public function __construct()
    {
        $this->companyService = new CompanyService();
        $this->user = new User();
        $this->utils = new Utils();
        $this->lang = new Lang();

        $this->page = $this->_getActualPage();

        $this->checkReferer();
    }

    private function _getActualPage() {
        if (isset($_POST["page"])) {
            $_GET["page"] =  $_POST["page"];
        }
        if (!isset($_GET["page"]) && isset($_SESSION["user"])) {
            $_GET["page"] = $this->_getLandingPage();
        }
        if (!isset($_GET["page"])) {
            $_GET["page"] = $this->_getLandingPage();
        }

        $pageName = ucfirst(str_replace("_", "", $_GET["page"])) . "Page";
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

    private function _getLandingPage():string {
        $page = "booking";
        if (CompanyService::isHungarocontrol()) {
            $page = "covidoltasnaplo";
        }
        return $page;
    }

    public function showPage()
    {
        $webText = $this->lang->webText;

        header("Content-type: text/html; charset=UTF-8");

        echo $this->utils->htmlheader($this->page->pageTitle);
        echo "<body " . ($_GET["page"] == "webfogleu" ? "onload=\"checkFogleuForm();\"" : "") . ">";
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

        $settings = new Booking_Settings();
        $chatAvailable = $settings->chatStatus == 1;

        if ($chatAvailable == 0) {
            //ha session aktív, akkor mégis legyen online
            if (sql_query("select id from chatsession where session=?", [session_id()])->fetch(PDO::FETCH_ASSOC)) {
                //$chatAvailable = true;
            }
        }

        if ($chatAvailable) {
            echo "<div id='hmmchat' data-supportname='Hungariamed-M' data-supporttitle='Ügyfélszolgálat'></div>";
        }

        echo "</body>";
    }


    private function _pageHead()
    {
        return "<div class='fejlecdiv'></div>";
    }

    private function _pageMenu()
    {
        $webText = $this->lang->webText;

        $html = "";

        $html .= "<div id='sound' style='display:none;'></div>";
        $html .= '<div class="successful-message"><span>Loading...</span></div>';
        $html .= '<div class="obj-container-framebox"></div>';

        $html .= "<div class='headercontainer'>";
        $html .= "<div style='display:table;width:100%;'>";
        $html .= "<div style='display:table-row;'>";
        $html .= "<div style='display:table-cell;vertical-align:middle;width:20px;white-space: nowrap;'>";

        $mainURL = "index.php";
        if ($this->page->lockInPage) {
            $mainURL = "index.php?page={$_GET["page"]}";
        }

        if ($this->page->showSuzukiLogo) {
            $html .= "<img height='45' src='images/suzuki_logo_2.png' alt='' title='Magyar Suzuki Zrt.' style='margin-right:10px;' /> ";
        }

        if (!empty($this->page->customLogo)) {
            $html .= "<img height='{$this->page->customLogoHeight}' src='{$this->page->customLogo}' alt='' title='' style='margin-right:10px;' /> ";
        }

        if (CompanyService::isAuchan()) {
            $html .= "<a href='{$mainURL}'><img height='43' src='/images/Auchan-Logo.png' alt='' title='Auchan' style='margin:0px 0px 0px -20px;' /></a>";
        }
        $html .= "<a href='{$mainURL}'><img height='45' src='" . Booking_Constants::SITE_LOGO . "' alt='' title='" . Booking_Constants::SITE_NAME . "' style='margin-right:20px;' /></a>";

        $html .= "</div>";
        if ($this->page->showMainMenu) {
            $html .= "<div style='display:table-cell;vertical-align:middle;'>";
            if (isset($_SESSION["user"])) {
                $rowb = sql_fetch_array(sql_query("select count(*) as hany from beutalok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and foglalasid=0"));
                $rowd = sql_fetch_array(sql_query("select count(*) as hany from dokumentumok where userid='{$_SESSION["user"]["id"]}' and userid<>0 and megnezve is null"));
                $rowf = sql_fetch_array(sql_query("select count(*) as hany from foglalasok where paciensid='{$_SESSION["user"]["id"]}' and datum>now()"));

                $html .= "<div>{$webText["udvozlunk"]} {$_SESSION["user"]["nev"]}!</div>";
                if ($this->_idopontfoglalasMenuPolicy()) {
                    $html .= "<a class='toplink' href='index.php?page=booking'>" . ucfirst($webText["idopontfoglalas"]) . "</a> &bull; ";
                    $html .= "<a class='toplink' href='index.php?page=bookinglist'>" . ucfirst($webText["foglalasok"]) . "</a>" . ($rowf["hany"] > 0 ? " <span class='ujnumber'>{$rowf["hany"]}</span>" : "") . " &bull; ";
                }
                if ($this->_covidOltasNaploMenuPolicy()) {
                    $html .= "<a class='toplink' href='index.php?page=covidoltasnaplo'>" . ucfirst($webText["covidoltasnaplo"]) . "</a>" . ($rowb["hany"] > 0 ? " <span class='ujnumber'></span>" : "") . " &bull; ";
                }
                if ($this->_beutalokMenuPolicy()) {
                    $html .= "<a class='toplink' href='index.php?page=beutalok'>" . ucfirst($webText["beutalok"]) . "</a>" . ($rowb["hany"] > 0 ? " <span class='ujnumber'>{$rowb["hany"]}</span>" : "") . " &bull; ";
                }
                if ($this->_dokumentumokMenuPolicy()) {
                    $html .= "<a class='toplink' href='index.php?page=documents'>" . ucfirst($webText["dokumentumok"]) . "</a>" . ($rowd["hany"] > 0 ? " <span class='ujnumber'>{$rowd["hany"]}</span>" : "") . " &bull; ";
                }
                //leletek oldal határozatlan ideig szüntetel
                //$html.= "<a class='toplink' href='index.php?page=leletek'>".ucfirst($this->lang->getText("leletek","leletek"))."</a> &bull; ";
                $html .= "<a class='toplink' href='index.php?page=profile'>" . ucfirst($webText["adatmodositas"]) . "</a> &bull; ";
                $html .= "<a class='toplink' href='index.php?logout'>" . ucfirst($webText["kijelentkezes"]) . "</a>";
            } else {
                $html .= "<a class='toplink' href='index.php?page=booking'>" . ucfirst($webText["fooldal"]) . "</a>";
                if ($_SESSION["helyszindata"]["onlyreg"] == 1) {
                    $html .= "&nbsp;&bull;&nbsp;<a class='toplink' href='index.php?page=registration'>" . ucfirst($webText["regisztracio"]) . "</a>";
                }
                $html .= "&nbsp;&bull;&nbsp;<a class='toplink' href='index.php?page=login'>" . ucfirst($webText["bejelentkezes"]) . "</a>";
                if ($_SESSION["helyszindata"]["web_fogleu"] == 1) {
                    $html .= "&nbsp;&bull;&nbsp;<a class='toplink' href='index.php?page=webfogleu'>" . ucfirst($webText["webfogleu"]) . "</a>";
                }
            }
            $html .= "</div>";
        }

        if ($this->page->showLangMenu) {
            $html .= "<div style='display:table-cell;vertical-align:middle;padding-left:10px;text-align:right;'>";
            if (isset($_SERVER["HTTP_HOST"]) && substr_count($_SERVER["HTTP_HOST"], "anmeldung") == 0) {
                foreach ($this->page->langList as $lang) {
                    $html .= Lang::getLangLink($lang) . " ";
                }
            }
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";
        $html .= "</div>";
        return $html;
    }

    private function _idopontfoglalasMenuPolicy():bool {
        $show = true;
        if (CompanyService::isHungarocontrol()) {
            $show = false;
        }
        return $show;
    }

    private function _beutalokMenuPolicy():bool {
        $show = true;
        if (CompanyService::isHungarocontrol()) {
            $show = false;
        }
        return $show;
    }

    private function _dokumentumokMenuPolicy():bool {
        $show = true;
        if (CompanyService::isHungarocontrol()) {
            $show = false;
        }
        return $show;
    }

    private function _covidOltasNaploMenuPolicy():bool {
        $show = false;
        if (CompanyService::isHungarocontrol()) {
            $show = true;
        }
        return $show;
    }


    private function _pageFooter()
    {
        $class = "footercontainer_".Booking_Constants::SQL_DB;
        if ($this->page->showSuzukiLogo || !empty($this->page->customLogo)) {
            $class = "footercontainer_suzuki";
        }
        $html = "";
        $html .= "<div class='{$class}'>";

        $html .= "<div style='float:left;margin:0px 40px 10px 0px;'>" . Booking_Constants::FOOTER_ADDRESS_PARAM . "</div>";
        $html .= "<div style='float:left;margin:0px 10px 10px 0px;'>" . Booking_Constants::FOOTER_CONTACT_PARAM . "</div>";
        $html .= "<br clear='all'/>";

        $html .= "&copy; " . date("Y") . " " . Booking_Constants::FOOTER_COPYRIGHT;
        $html .= " ".session_id();
        $html .= "</div>";
        return $html;
    }


    private function checkReferer() {
        if (!empty($_SERVER["HTTP_REFERER"])) {
            if (substr_count($_SERVER["HTTP_REFERER"], "sanitas")) {
                $_SESSION["referer"] = "sanitas";
            }
        }
    }
}
