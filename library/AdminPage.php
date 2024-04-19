<?php

class AdminPage {

    private $utils;
    private $adminUtils;
    public $adminUser;
    public $companyService;
    private $lang;
    private $bookingEditor;
    public $page;
    public $pageData;

    private bool $skipFrame = false;
    private bool $skipMenu = false;
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

        $this->adminMenu = $this->getAdminMenu(0);
    }

    private function _getActualPage() {
        if (isset($_POST["page"])) {
            $_GET["page"] = $_POST["page"];
        }
        if (!isset($_GET["page"])) {
            $_GET["page"] = "booking";

            if ($this->adminUser->oltasAccess()) {
                $_GET["page"] = "oltasigenyek";
            }
        }

        if ($this->adminUser->getLockPage() != "") {
            $this->skipMenu = true;
            $_GET["page"] = $this->adminUser->getLockPage();
        }

        if (!isset($_SESSION["helyid"])) {
            $_SESSION["helyid"] = 1;
        }

        $this->pageData = sql_fetch_array(sql_query("select * from adminmenu where pageid=?", array($_GET["page"])));
        if ($this->adminUser->getLockPage() != "") {
            $this->pageData["skipmenu"] = 1;
        }

        $pageName = "Admin".ucfirst($_GET["page"])."Page";

        if (class_exists($pageName)) {
            $page = new $pageName;
        } else {
            $_GET["page"] = "error";
            $page = new AdminErrorPage();
        }

        //ha nincs bejelentkezve, akkor loginra dobjuk
        if (empty($this->adminUser->user)) {
            $this->skipFrame = true;
            $page = new AdminLoginPage();
        }

        //ha be van jelentkezve, de 2 faktoros authentikációt még nem végzett
        if (isset($this->adminUser->user["auth2fac"]) && $this->adminUser->user["auth2fac"]==1) {
            if (!isset($_SESSION["2facomplete"])) {
                $this->skipFrame = true;
                $page = new AdminLoginPage();
            }
        }


        if (isset($_GET["scheduletoken"])) {
            $_GET["page"] = "workschedule";
            $page = new AdminWorkSchedulePage();
            return $page;
        }

        //ha tiltott felhasználó
        if ($this->adminUser->tiltottUser()) {
            $this->skipFrame = true;
            $page = new AdminLoginPage();
        }
        return $page;
    }

    public function showPage() {
        $pageContent = $this->_getPageContent();

        header("Content-type: text/html; charset=UTF-8");

        echo $this->utils->htmlheader("{$_SESSION["helyszindata"]["megnev"]} bejelentkező felület");
        echo "<body>";

        //login és más keret nélküli oldalak
        if ($this->skipFrame) {
            echo $pageContent;
            die;
        }

        echo $this->_statusRow();

        echo "<table width='100%' cellpadding='0' cellspacing='0' border='0'>";
        echo "<tr>";

        if ($this->pageData["skipmenu"] == 0 && !$this->skipMenu) {
            echo "<td valign='top' class='menuoszlop'>";
            echo $this->_menuColumn();
            echo "<div id='loggedusers' style='border-top:1px solid #888;color:#888;padding:10px;width:160px;overflow:hidden;font-size: 11px;'></div>";
            echo "</td>";
        }

        echo "<td valign='top' style='background-color:#fff;box-shadow:-0px 0px 10px #bbb;'>";
        echo "<div style='margin:20px;min-height:400px;'>";
        echo $this->_contentHeader($this->pageData);
        echo $pageContent;
        echo "</div>";
        echo "</td>";

        echo "</tr>";

        echo "<tr>";
        if ($this->pageData["skipmenu"] == 0) {
            echo "<td></td>";
        }
        echo "<td>";
        echo "<div class='footersor'>&copy; ".Booking_Constants::FOOTER_COPYRIGHT." | ".session_id()." | <a onclick='printSpektrumlabMatrica(\"0\", \"0\");return false;' href='#'>zteszt</a></div>";
        echo "</td></tr>";

        echo "</table>";

        echo "<div id='generalpopup'></div>";
        echo "</body>";
        echo "</html>";
    }

    private function _getPageContent():string {
        ob_start();
        $this->page->showPage();
        $pageContent = ob_get_contents();
        ob_end_clean();
        return $pageContent;
    }

    private function _statusRow() {
        $html = "";

        $html.= "<div id='adminwarnwindow'></div>";
        $html.= "<div class='szamlalo' style='display:table;float: right'>";
        $html.= "<div style='display: table-cell;padding-right: 10px;' id='chatbuttoncontainer'></div>";
        $html.= "<div style='display: table-cell;padding-right: 10px;' id='warnbuttoncontainer'></div>";
        $html.= "<div style='display: table-cell;'>".$this->adminUser->getAdminLevel($this->adminUser->user, true)."&nbsp;&nbsp;</div>";
        $html.= "<div style='display: table-cell;'>Felhasználó: <a style='color:#44f;' href='index.php?page=users&szerk=self'>".$this->adminUser->user["nev"]."</a> - <a href='index.php?logoutadmin'>kijelentkezés</a></div>";
        $html.= "</div>";
        return $html;
    }

    private function _menuColumn() {
        $subDomain = $_SESSION["helyszindata"]["domain"];

        $html = "";
        $html.= "<div align='center' style='margin:-20px 0px 20px 0px;padding-right:5px;'><a href='index.php'><img width='120' src='/images/".Booking_Constants::SITE_ADMIN_LOGO."' /></a></div>";
        if (is_file("images/logo_{$subDomain}.png") || is_file("../images/logo_{$subDomain}.png")) {
            $html.= "<div align='center' style='padding-right:5px;'><img width='120' src='/images/logo_{$subDomain}.png' /></div>";
        }

        $html.= "<div style='padding-top:10px;padding-bottom:10px;font-size:12px;'>";

        foreach ($this->adminMenu as $menu) {
            if ($menu["sorrend"] == 0) {
                 continue;
            }

            $aktualPage = $_GET["page"] == $menu["pageid"] || $this->pageData["parent"] == $menu["id"];
            $url = "index.php?page={$menu["pageid"]}";
            $onClick = "";
            if ($menu["pageid"] == "#") {
                $url = "#";
                $onClick = "toggleSubMenu({$menu["id"]});return false;";
            }

            $subMenuHtml = "";
            if (!empty($menu["submenu"])) {
                $subMenuHtml .= "<div id='submenu{$menu["id"]}' style='margin:5px 0px;".(isset($_SESSION["opensubmenu"][$menu["id"]])?"":"display:none;")."'>";
                foreach ($menu["submenu"] as $submenuItem) {
                    $subMenuHtml .= "<div><a class='mainmenuitem_sub" . ($_GET["page"] == $submenuItem["pageid"] ? "_aktiv" : "") . "' href='index.php?page={$submenuItem["pageid"]}'>{$submenuItem["megnev"]}</a></div>";
                }
                $subMenuHtml .= "</div>";
            }

            if ($url != "#" || !empty($subMenuHtml)) {
                if ($menu["pageid"] == "hirek") {
                    $news = sql_query("select id from news where datum>date_sub(now(), interval 1 month) and !instr(readby, ?) limit 1", ["|{$this->adminUser->user["id"]}|"])->fetchAll(PDO::FETCH_ASSOC);
                    $newSign = "";
                    if (count($news) > 0) {
                        $newSign = "<i style='color:#a00;' title='" . count($news) . " új bejegyzés' class='fas fa-exclamation-circle'></i>";
                    }
                    $html .= "<div><a class='mainmenuitem".($_GET["page"]=="hirek"?"_aktiv":"")."' href='index.php?page=hirek'><i class='fas fa-rss'></i> Faliújság {$newSign}</a></div>";
                } else {
                    $html .= "<div><a class='mainmenuitem" . ($aktualPage ? "_aktiv" : "") . "' href='{$url}' onclick='{$onClick}'>{$menu["megnev"]}</a></div>";
                }
            }

            $html.= $subMenuHtml;


        }

        return $html;
    }

    private function _contentHeader($menu) {
        $title = $menu["megnev"];
        if (!empty($this->page->subtitle)) {
            $title = $this->page->subtitle;
        }

        if (empty($title)) {
            return "";
        }

        $html = "";

        $html.= "<div class='pagehead'>";
        $html.= "<div style='display:table-cell;vertical-align:middle;'>{$title}</div>";

        if (!isset($GLOBALS["nopageaccess"])) {
            if ($menu["newbutton"] != "" && !isset($_GET["szerk"])) {
                $html .= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&addnew'>+ {$menu["newbutton"]}</a></div>";
            }
            if (isset($_GET["szerk"])) {
                $html .= "<div style='display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px;'><a class='ujbutton' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}'>Vissza</a></div>";
            }
        }
        $html.= "</div>";

        return $html;
    }

    public function getAdminMenu($parent) {
        $adminMenu = [];

        if ($this->adminUser->authenticated()) {
            $res = sql_query("select * from adminmenu where aktiv=1 and parent=? order by sorrend, megnev", [$parent]);
            while ($menuData = sql_fetch_array($res)) {
                //suzukinak csak 1 menüpont
                if ($this->adminUser->oltasAccess() && $menuData["pageid"] != "oltasigenyek" && $this->adminUser->readOnlySelectedCegAccess()) {
                    continue;
                }

                if ($menuData["jogosultsag"] != "" && !isset($this->adminUser->user[$menuData["jogosultsag"]])) {
                    $this->adminUser->user[$menuData["jogosultsag"]] = 0;
                }

                if ($menuData["jogosultsag"] != "" && $this->adminUser->user[$menuData["jogosultsag"]] != 1) {
                    continue;
                } 

                $subMenu = $this->getAdminMenu($menuData["id"]);
                $menuData["submenu"] = $subMenu;

                $adminMenu[] = $menuData;
            }
        }
        return $adminMenu;
    }
}