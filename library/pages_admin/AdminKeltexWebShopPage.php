<?php


class AdminKeltexWebShopPage extends AdminCorePage
{
    public array $contentCategories = [
        84 => "Egészség Blog",
        85 => "Statikus oldal",
    ];

    public array $services;

    public function __construct()
    {
        parent::__construct();

        $services = sql_query("select t.id, t.megnev from szurestipusok t 
                 left join dokumentumok d on d.assetid=? and d.dataid=t.id 
                 where d.id is not null group by t.id order by t.megnev", [DocAgent::ASSET_SERVICE_ILLUSTRATION_IMAGE])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($services as $service) {
            $this->services[$service["id"]] = $service;
        }

        if (isset($_REQUEST["generalsearch"])) {
            $contents = sql_query("select id, title, alias, state, created, publish_up, publish_down, catid, tipusid, tags from hmmweb.q9a8m_content where instr(title, ?) order by created desc", [$_REQUEST["term"]])->fetchAll(PDO::FETCH_ASSOC);
            echo $this->listContents($contents);
            die;
        }

        if (isset($_POST["contentmentes"])) {
            $state = -2;
            if (isset($_POST["state"])) {
                $state = 1;
            }

            $linkedServices = [];
            foreach ($this->services as $service) {
                if (isset($_POST["linkedservice{$service["id"]}"])) {
                    $linkedServices[] = $service["id"];
                }
            }

            sql_query("update hmmweb.q9a8m_content c set catid=?, title=?, alias=?, created=?, publish_up=?, publish_down=?, state=?, c.fulltext=?, tipusid=?, tags=? where id=?",
                [$_POST["catid"], $_POST["title"], $_POST["alias"], $_POST["created"], $_POST["publish_up"], $_POST["publish_down"], $state, $_POST["fulltext"], json_encode($linkedServices), $_POST["tags"], $_GET["szerk"]]);

            header("location:index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die;
        }

        if (isset($_GET["addnew"])) {
            sql_query("insert into hmmweb.q9a8m_content set catid=85, title='új oldal', created=now(), publish_up=now(), state=1");
            //header("location:index.php?page={$_GET["page"]}");
            die;
        }

        //$GLOBALS["javascript"][] = "dicom.js?v=" . date("YmdHi");
    }

    public function showPage() {
        if (!$this->adminUser->beallitasWebAdatokAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        $GLOBALS["subtitle"] = "Tartalmak";

        if (isset($_GET["szerk"])) {
            $content = sql_query("select * from hmmweb.q9a8m_content where id=?", [$_GET["szerk"]])->fetch(PDO::FETCH_ASSOC);
            echo $this->contentEditor($content);
        } else {
            echo "<div style='margin-bottom:20px;'>";
            echo "<input data-page='contents' data-resultdiv='tartalomlist' type='text' id='generalsearch' value='' placeholder='Keresés...'/>&nbsp;";
            echo "</div>";

            echo "<div id='tartalomlist'>";
            $contents = sql_query("select id, title, alias, state, created, publish_up, publish_down, catid, tipusid, tags from hmmweb.q9a8m_content order by created desc")->fetchAll(PDO::FETCH_ASSOC);
            echo $this->listContents($contents);
            echo "</div>";
        }
    }


    private function contentEditor($content):string {
        $html = "";

        $id = $content["id"];

        echo "<div style='background-color:#fff;padding:0px;'>";
        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<table style='font-size:12px;'>";

        echo "<tr><td width='100'>Cím:</td><td><input class='inputbox' style='width:400px;' type='text' name='title' value='{$content["title"]}'></td></tr>";
        echo "<tr><td width='100'>Alias:</td><td><input class='inputbox' style='width:400px;' type='text' name='alias' value='{$content["alias"]}'></td></tr>";
        echo "<tr><td width='100'>Created:</td><td><input class='inputbox' style='width:400px;' type='text' name='created' value='{$content["created"]}'></td></tr>";
        echo "<tr><td width='100'>Publish up:</td><td><input class='inputbox' style='width:400px;' type='text' name='publish_up' value='{$content["publish_up"]}'></td></tr>";
        echo "<tr><td width='100'>Publish down:</td><td><input class='inputbox' style='width:400px;' type='text' name='publish_down' value='{$content["publish_down"]}'></td></tr>";
        echo "<tr><td width='100'>Kategória:</td><td>";
        echo "<select name='catid'>";
        foreach ($this->contentCategories as $key => $val) {
            echo "<option value='{$key}'" . ($content["catid"] == $key ? " selected" : "") . ">{$val}</option>";
        }
        echo "</select>";
        echo "</td></tr>";

        echo "<tr><td colspan='2' valign='top'>";
        echo "<input type='checkbox' value='1' name='state'" . ($content["state"] == 1 ? " checked" : "") . "> Aktív&nbsp;&nbsp;";

        echo "</td></tr>";

        $linkedServices = json_decode($content["tipusid"], JSON_OBJECT_AS_ARRAY);

        echo "<tr><td colspan='2'><div class='tdsepdiv'>Kapcsolodó szolgáltatás</div></td></tr>";
        echo "<tr><td colspan='2' valign='top'>";
        foreach ($this->services as $service) {
            echo "<div><input type='checkbox' name='linkedservice{$service["id"]}' value='1' ".(in_array($service["id"], $linkedServices)?"checked":"")."/> {$service["megnev"]}</div>";
        }
        echo "</div>";
        echo "</td></tr>";
        echo "<tr><td width='100'>Cimkék:</td><td><input class='inputbox' style='width:500px;' type='text' name='tags' value='{$content["tags"]}'></td></tr>";


        $docAgent = new DocAgent();
        echo "<tr><td colspan='2'><div class='tdsepdiv'>Title image</div></td></tr>";
        echo "<tr><td colspan='2' valign='top'><div id='asseteditor'>".$docAgent->showAssetEditor(DocAgent::ASSET_CONTENT_TITLE_IMAGE, $id)."</div>";
        echo "</td></tr>";

        echo "<tr><td colspan='2'><div class='tdsepdiv'>Teljes szöveg</div></td></tr>";
        echo "<tr><td colspan='2' valign='top'><div id='desceditor'>";
        echo "<textarea class='mce' name='fulltext' style='width:900px;height:600px;'>{$content["fulltext"]}</textarea>";
        echo "</div></td></tr>";

        echo "</table>";

        echo "<br><input type='submit' name='contentmentes' value='Mentés'> ";
        echo "<input type='submit' name='scancel' value='Vissza'> ";
        echo "</form>";

        echo "</div>";

        return $html;
    }

    private function listContents($contents):string {
        $html = "";

        $html .= "<table cellpadding='0' cellspacing='0' border='0' width='100%;'>";
        $html .= "<tr style='background:#eee;'>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'>&nbsp;Dátum</div></td>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:50px;'>Kategória</div></td>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'>Aktív</td>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:300px;'>Cím</div></td>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Kapcsolódó szolgáltatás</div></td>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Cimkék</div></td>";
        $html .= "</tr>";

        foreach ($contents as $row) {
            $tc = "tcella";
            if (!isset($first)) {
                $html .= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (empty(trim($row["title"]))) {
                $row["title"] = "nincs címe";
            }

            $category = "Statikus oldal";
            if (isset($this->contentCategories[$row["catid"]])) {
                $category = $this->contentCategories[$row["catid"]];
            }

            if ($row["catid"] != 84) {
                $category = "<strong>{$category}</strong>";
            }

            $active = "Aktív";
            if ($row["state"] < 1) {
                $active = "Inaktív";
            }

            $services = [];
            $linkedServices = json_decode($row["tipusid"], JSON_OBJECT_AS_ARRAY);
            foreach ($this->services as $service) {
                if (in_array($service["id"], $linkedServices)) {
                    $services[] = $service["megnev"];
                }
            }

            $html .= "<tr>";

            $html .= "<td nowrap valign='top'><div class='{$tc}'>&nbsp;" . date("Y-m-d H:i", strtotime($row["created"])) . "</div></td>";
            $html .= "<td nowrap valign='top'><div class='{$tc}'>{$category}</div></td>";
            $html .= "<td nowrap valign='top'><div class='{$tc}'>{$active}</div></td>";
            $html .= "<td nowrap valign='top'><div class='{$tc}'><a href='index.php?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["title"]}</a></div></td>";
            $html .= "<td nowrap valign='top'><div class='{$tc}'>".implode(", ", $services)."</div></td>";
            $html .= "<td nowrap valign='top'><div class='{$tc}'>{$row["tags"]}</div></td>";

            $html .= "</tr>";
            $html .= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        $html .= "</table>";

        return $html;
    }





}