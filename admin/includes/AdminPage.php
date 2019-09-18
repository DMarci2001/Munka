<?php

class AdminPage {

    private $utils;
    private $adminUtils;
    public $adminUser;
    public $companyService;
    private $lang;
    private $bookingEditor;
    public $page;

    private $skipFrame = false;
    private $adminMenu = [];

    public function __construct()
    {
        $this->companyService = new CompanyService();
        $this->adminUser = new AdminUser();
        $this->utils = new Utils();
        $this->adminUtils = new AdminUtils();
        $this->lang = new Lang();
        $this->bookingEditor =new AdminBookingEditor();

        $this->utils->setupLongSession();

        $this->page = $this->_getActualPage();

        $this->adminMenu = $this->adminUtils->getAdminMenu();
    }

    private function _getActualPage() {
        if (isset($_POST["page"])) {
            $_GET["page"] = $_POST["page"];
        }
        if (!isset($_GET["page"])) {
            $_GET["page"] = "booking";
        }
        if (!isset($_SESSION["helyid"])) {
            $_SESSION["helyid"] = 1;
        }

        $pageName = "Admin".ucfirst($_GET["page"])."Page";
        if (class_exists($pageName)) {
            $page = new $pageName;
        } else {
            $_GET["page"] = "error";
            $page = new AdminErrorPage();
        }

        if (empty($this->adminUser->user)) {
            $this->skipFrame = true;
            $page = new AdminLoginPage();
        }

        if (isset($this->adminUser->user["auth2fac"]) && $this->adminUser->user["auth2fac"]==1) {
            if (!isset($_SESSION["2facomplete"])) {
                $this->skipFrame = true;
                $page = new AdminLoginPage();
            }
        }
        if ($this->adminUser->user["status"] == 0) {
            $this->skipFrame = true;
            $page = new AdminLoginPage();
        }
        return $page;
    }

    public function showPage() {
        $adminUtils = new AdminUtils();

        header("Content-type: text/html; charset=UTF-8");

        echo $this->utils->htmlheader("{$_SESSION["helyszindata"]["megnev"]} orvosi felület");
        echo "<body>";

        //login és más keret nélküli oldalak
        if ($this->skipFrame) {
            $this->page->showPage();
            die;
        }

        if ($GLOBALS["adminuser"]["localeaccess"]==1 && substr_count($GLOBALS["adminuser"]["localeip"], $_SERVER["REMOTE_ADDR"]) == 0) {
            //echo $GLOBALS["adminuser"]["localeip"]." ".$_SERVER["REMOTE_ADDR"];
            echo "<div id='errordiv' style='background:#f00;padding:10px;font-weight:bold;color:#fff;text-align:center;'>Ez a fiók csak lokálisan engedélyezett.</div>";
            echo "<div style='margin-top:20px;text-align:center;'><a href='index.php?logoutadmin'>kijelentkezés</a></div>";
            echo "</body>";
            echo "</html>";
            die();
        }

        echo "<div class='szamlalo' style='display:table;float: right'>";

        if ($_SESSION["adminuser"]["jogosultsag"] >= 2) {
            echo "<div style='display: table-cell;'><a href='index.php?page=log'>LOG</a>&nbsp;&nbsp;</div>";
            echo "<div style='display: table-cell;'><span style='color:#fff;background:#0a0;padding:2px 5px;border-radius:2px;'>ADMIN</span>&nbsp;&nbsp;</div>";
        }
        if ($_SESSION["adminuser"]["jogosultsag"] == 1) echo "<div style='display: table-cell;'><span style='color:#fff;background:#00a;padding:2px 5px;border-radius:2px;'>CÉG ADMIN</span></div>";
        if ($_SESSION["adminuser"]["jogosultsag"] == 0) echo "<div style='display: table-cell;'><span style='color:#fff;background:#aaa;padding:2px 5px;border-radius:2px;'>RECEPCIÓ</span></div>";
        echo "<div style='display: table-cell;'>Felhasználó: <span style='color:#44f;'>{$_SESSION["adminuser"]["nev"]}</span> - <a href='index.php?logoutadmin'>kijelentkezés</a></div>";
        echo "</div>";


        echo "<table width='100%' cellpadding='0' cellspacing='0' border='0'>";
        echo "<tr>";

        echo "<td valign='top' width='150' class='menuoszlop'>";
        echo $this->_menuColumn();
        echo "</td>";

        echo "<td valign='top' style='background-color:#fff;box-shadow:-0px 0px 10px #bbb;'>";
        echo "<div style='margin:20px;min-height:400px;'>";

        echo $this->_contentHeader();

        $this->page->showPage();
        echo "</div>";

        echo "</td>";

        echo "</tr>";

        echo "<tr><td></td><td>";
        echo "<div class='footersor'>&copy; ".Booking_Constants::FOOTER_COPYRIGHT."</div>";
        echo "</td></tr>";

        echo "</table>";

        echo "</body>";
        echo "</html>";
    }

    private function _menuColumn() {
        $subDomain = $_SESSION["helyszindata"]["domain"];

        $html = "";
        $html.= "<div align='center' style='margin:-20px 0px 20px 0px;padding-right:5px;'><a href='index.php'><img width='80' src='/images/".Booking_Constants::SITE_ADMIN_LOGO."' /></a></div>";
        if (is_file("images/logo_{$subDomain}.png") || is_file("../images/logo_{$subDomain}.png")) {
            $html.= "<div align='center' style='padding-right:5px;'><img width='120' src='/images/logo_{$subDomain}.png' /></div>";
        }

        $html.= "<div style='padding-top:10px;padding-bottom:10px;font-size:12px;font-weight:bold;color:#9cf3c3;'>";

        foreach ($this->adminMenu as $menu) {
            $html.= "<div><a class='mainmenuitem".($_GET["page"] == $menu["pageid"]?"_aktiv":"")."' href='index.php?page={$menu["pageid"]}'>{$menu["megnev"]}</a></div>";
        }

        return $html;
    }

    private function _contentHeader() {
        $html = "";
        foreach ($this->adminMenu as $menu) {
            if ($_GET["page"] == $menu["pageid"]) {
                $html.= "<div class='pagehead'>";
                $html.= "<div style='display:table-cell;vertical-align:middle;'>{$menu["megnev"]}".($_GET["page"]=="elojegyzestdfdabla"?"&nbsp;&nbsp;<span style='background:#0a0;color:#fff;font-size:16px;font-weight:bold;padding:3px 8px;border-radius:10px;'>BÉTA</span>":"")."</div>";
                if ($menu["newbutton"] != "" && !isset($_GET["szerk"])) {
                    $html.= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&addnew'>+ {$menu["newbutton"]}</a></div>";
                }
                if ($menu["pageid"] == "felhasznalok" && !isset($_GET["szerk"]) && $_SESSION["adminuser"]["jogosultsag"] >= 1) {
                    $html.= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page=alkalmassagi'>Alkalmassági lista</a></div>";
                }
                if (isset($_GET["szerk"])) {
                    $html.= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}'>Vissza</a></div>";
                }
                if($_GET['page'] == "zarok" && ( isset($_GET['status']) && $_GET['status'] == "open" || !isset($_GET['status']))) {
                    $html.= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='index.php?page=zarok&status=closed'>Lezártak</a></div>";
                }
                if($_GET['page'] == "zarok" &&  isset($_GET['status']) && $_GET['status'] == "closed" ) {
                    $html.= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'>&nbsp;<a class='ujbutton' href='index.php?page=zarok&status=open'>Nyitottak</a></div>";
                }
                if($_GET['page'] == "zaro-kezelo") {
                    $html.= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'>&nbsp;<a class='ujbutton' href='index.php?page=zarok'>Vissza</a></div>";
                }
                if($_GET['page'] == "gdpr" &&  isset($_GET['status']) && $_GET['status'] == "closed") {
                    $html.= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'>&nbsp;<a class='ujbutton' href='index.php?page=gdpr&status=open'>Aktuálisak</a></div>";
                }
                if($_GET['page'] == "gdpr" && ( isset($_GET['status']) && $_GET['status'] == "open" || !isset($_GET['status']))) {
                    $html.= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='index.php?page=gdpr&status=closed'>Archív</a></div>";
                }
                if($_GET['page'] == "gdpr_edit") {
                    $html.= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'>&nbsp;<a class='ujbutton' href='index.php?page=gdpr'>Vissza</a></div>";
                }
                $html.= "</div>";
            }
        }
        return $html;
    }


}