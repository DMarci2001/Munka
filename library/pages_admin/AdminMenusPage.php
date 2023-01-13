<?php

class AdminMenusPage extends AdminCorePage
{
    public function __construct()
    {
        parent::__construct();

        if (isset($_POST["menumentes"])) {

            //sql_query("update hmmweb.q9a8m_menu set title=?, parent_id=?, published=? where id=?", [$_POST["title"], $_POST["parent_id"], isset($_POST["published"])?1:0, $_GET["szerk"]]);

            header("location:index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

    }

    public function showPage()
    {
        if (!$this->adminUser->szurestipusAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        if (isset($_GET["szerk"])) {
            $menu = sql_fetch_array(sql_query("select * from hmmweb.q9a8m_menu where id=?", array($_GET["szerk"])));

            $GLOBALS["subtitle"] = $menu["title"];

            $id = $menu["id"];

            $parents = sql_query("SELECT id, title, parent_id, path from hmmweb.q9a8m_menu m where m.parent_id=1 and m.menutype='mainmenu' ORDER BY m.lft", [])->fetchAll(PDO::FETCH_ASSOC);
            $services = sql_query("SELECT id, megnev, webalias from szurestipusok t where t.webalias<>'' ORDER BY t.megnev", [])->fetchAll(PDO::FETCH_ASSOC);
            $contents = sql_query("select id, title, alias, state, created, publish_up, publish_down, catid, tipusid, tags from hmmweb.q9a8m_content order by trim(title)")->fetchAll(PDO::FETCH_ASSOC);

            echo "<div style='background-color:#fff;padding:0px;'>";
            echo "<form name='iform' method='post' enctype='multipart/form-data'>";
            echo "<table style='font-size:12px;'>";

            echo "<tr><td width='100'>Megnevezés:</td><td><input class='inputbox' style='width:400px;' type='text' name='title' value='{$menu["title"]}'></td></tr>";
            echo "<tr><td width='100'>Szülő menüpont:</td><td><select name='parent_id'>";
            echo "<option value='1'>ROOT</option>";
            foreach ($parents as $parent) {
                echo "<option value='{$parent["id"]}' ".($menu["parent_id"] == $parent["id"]?"selected":"").">{$parent["title"]}</option>";

            }
            echo "</select></td></tr>";

            echo "<tr><td width='100'>Target:</td><td><select name='parent_id'>";
            echo "<option value='1'>#</option>";
            echo "<option value='-1'".($menu["type"] == "url"?"selected":"").">Fix URL</option>";
            echo "<option value='-2'".($menu["type"] == "category" && $menu["component_id"] == 84?"selected":"").">Egészség blog</option>";
            foreach ($services as $service) {
                $selected = "";
                if (substr_count($menu["path"], "szurovizsgalatok/")) {
                    $alias = str_replace("szurovizsgalatok/", "", $menu["path"]);
                    if ($alias == $service["webalias"]) {
                        $selected = "selected";
                    }
                }
                echo "<option value='service{$service["id"]}' {$selected}>Szakrendelés: {$service["megnev"]}</option>";

            }
            foreach ($contents as $content) {
                $selected = "";
                if ($menu["type"] == "content" && $menu["component_id"] == $content["id"]) {
                    $selected = "selected";
                }
                echo "<option value='content{$content["id"]}' {$selected}>Tartalom: {$content["title"]}</option>";
            }
            echo "</select></td></tr>";

            if ($menu["type"] != "url") {
                $menu["link"] = "";
            }

            echo "<tr><td>URL fix url esetén:</td><td><input class='inputbox' style='width:400px;' type='text' name='link' value='{$menu["link"]}'></td></tr>";
            echo "<tr><td>Sorrend:</td><td><input class='inputbox' style='width:40px;' type='text' name='lft' value='{$menu["lft"]}'></td></tr>";


            echo "<tr><td colspan='2' valign='top'>";
            echo "<input type='checkbox' value='1' name='published'" . ($menu["published"] == 1 ? " checked" : "") . "> Aktív&nbsp;&nbsp;";
            echo "</td></tr>";

            echo "</table>";

            echo "<br><input type='submit' name='menumentes' value='Mentés'> ";
            echo "<input type='submit' name='scancel' value='Vissza'> ";
            echo "</form>";

            echo "</div>";
            return;
        }

        echo "<div id='menutree'>";
        echo $this->showMenuTree(1, 0);
        echo "</div>";
    }


    private function showMenuTree($parentId, $level) {
        $html = "";
        if ($level == 0) {
            $html.= "<h2>Főmenü</h2>";
        }
        $menus = sql_query("SELECT * from hmmweb.q9a8m_menu m where m.parent_id=? and m.menutype='mainmenu' ORDER BY m.lft", [$parentId])->fetchAll(PDO::FETCH_ASSOC);

        foreach ($menus as $menu) {
            $aktivStyle = "";
            if ($menu["published"] != 1) {
                $aktivStyle = "opacity:.5;";
            }

            $html.= "<div style='{$aktivStyle}'>";
            $html.= "<div>".str_repeat("&nbsp;", $level*4)."{$menu["lft"]} <a style='color:#00a;font-size:14px;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$menu["id"]}'>{$menu["title"]}</a></div>";
            //$html.= "<div style=''><a onclick='return confirm(\"Biztosan törlöd ezt a menüpontot?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$menu["id"]}'><i class='fas fa-trash-alt'></i></a></div>";
            $html.= "</div>";
            $html.= $this->showMenuTree($menu["id"], $level+1);
        }

        return $html;
    }

}