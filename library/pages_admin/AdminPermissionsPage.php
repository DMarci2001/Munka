<?php

class AdminPermissionsPage extends AdminCorePage {

    private $bookingService;

    public function __construct()
    {
        parent::__construct();



        if (isset($_POST["userlist"]) && $this->adminUser->jogosultsagAccess()) {
            $key = $_POST["key"];
            $html = "<div style='border-top:1px solid #ccc;border-bottom:1px solid #ccc;padding:10px 0px;margin:10px 0px;'>";
            $html.= "<form id='userlist_{$key}' name='userlist_{$key}'>";

            $users = sql_query("select * from users order by username");
            foreach ($users as $user) {
                $id = "user{$user["id"]}";
                $checked = "";

                $permissionData = json_decode($user["permissions"], JSON_OBJECT_AS_ARRAY);
                if (isset($permissionData["permissions"][$key]) && $permissionData["permissions"][$key] == 1) {
                    $checked = "checked";
                }

                $html.= "<span style='white-space: nowrap;'>";
                $html.= "<input type='checkbox' id='{$id}' name='{$id}' value='1' {$checked}>";
                $html.= "<label for='{$id}'> {$user["username"]}</label>";
                $html.= "</span> ";
            }

            $html.= "<div style='padding-top: 5px;'>";
            $html.= "<a class='abutton' href='#' onclick='savePermissionEditor(\"{$key}\");return false;'>Mentés</a>&nbsp;&nbsp;";
            $html.= "<a class='abutton' href='#' onclick='openPermissionEditor(\"{$key}\");return false;'>Mégse</a>&nbsp;&nbsp;";
            $html.= "<a class='bbutton' href='#' onclick='checkAllPermissionEditor(\"{$key}\", 1);return false;'>check all</a>&nbsp;&nbsp;";
            $html.= "<a class='bbutton' href='#' onclick='checkAllPermissionEditor(\"{$key}\", 0);return false;'>uncheck all</a>";
            $html.= "</div>";

            $html.= "</form>";
            $html.= "</div>";
            Utils::jsonOut(["html" => $html]);
        }

        if (isset($_POST["savepermissions"]) && $this->adminUser->jogosultsagAccess()) {
            $key = $_POST["key"];
            $users = sql_query("select * from users order by username");
            foreach ($users as $user) {
                $permissionData = json_decode($user["permissions"], JSON_OBJECT_AS_ARRAY);
                $checked = isset($permissionData["permissions"][$key]) && $permissionData["permissions"][$key] == 1;

                if (isset($_POST["user{$user["id"]}"])) {
                    if (!$checked) {
                        $permissionData["permissions"][$key] = 1;
                        sql_query("update users set permissions=? where id=?", [json_encode($permissionData, JSON_PRETTY_PRINT), $user["id"]]);
                    }
                } else {
                    if ($checked) {
                        unset($permissionData["permissions"][$key]);
                        sql_query("update users set permissions=? where id=?", [json_encode($permissionData, JSON_PRETTY_PRINT), $user["id"]]);
                    }
                }
            }

            die;
        }

    }

    public function showPage() {
        if (!$this->adminUser->jogosultsagAccess()) {
            echo $this->noPermissionMessage();
            return;
        }


        echo "<div style=''>";

        echo "<div style='font-weight: bold;margin-bottom: 5px;'>Oldalak elérése</div>";
        $existingKeys = [];
        $pages = sql_query("select group_concat(megnev separator ', ') as megnev, jogosultsag from adminmenu where aktiv=1 and jogosultsag<>'' group by jogosultsag order by megnev")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pages as $page) {
            $permissionKey = $page["jogosultsag"];
            $existingKeys[] = $permissionKey;
            echo "<div><a href='#' onclick='openPermissionEditor(\"{$permissionKey}\");return false;'>".ucfirst($page["megnev"])."</a> ({$permissionKey})</div>";
            echo "<div id='permissioneditor_{$permissionKey}' style='display:none;'></div>";
        }
        echo "</div>";

        echo "<div style='font-weight: bold;margin-bottom: 5px;margin-top:10px;'>Egyéb jogosultságok</div>";
        foreach (AdminUser::$jogosultsagLista as $permissionKey => $permissionData) {
            if (!in_Array($permissionKey, $existingKeys)) {
                echo "<div><a href='#' onclick='openPermissionEditor(\"{$permissionKey}\");return false;'>" . ucfirst($permissionData["name"]) . "</a> ({$permissionKey})</div>";
                echo "<div id='permissioneditor_{$permissionKey}' style='display:none;'></div>";
            }
        }

        echo "</div>";

    }
}

