<?php

class AdminWebPageDataPage extends AdminCorePage {

    private WebPageData $webPageData;
    private DocAgent $docAgent;

    public function __construct()
    {
        parent::__construct();

        $this->webPageData = new WebPageData();
        $this->docAgent = new DocAgent();

        if (isset($_GET["addnew"])) {
            sql_query("insert into webpagedata set domain='aaaaa.hu', parent=?", [WebPageData::DEFAULT_DATA_ID]);
        }

        if (isset($_POST["getImageUploadDiv"])) {
            echo $this->docAgent->showAssetEditor($_POST["tipus"], intval($_POST["id"]));
            die;
        }

        if (isset($_POST["webpagedatasave"])) {
            $params = [];
            foreach ($this->webPageData->params as $key => $pageParam) {
                if (isset($_POST["{$key}_orokles"])) {
                    continue;
                }

                if ($pageParam["type"] == "textbox" || $pageParam["type"] == "tipuskapcs") {
                    $params[$key] = $_POST[$key];
                }
                if ($pageParam["type"] == "image") {
                    $params[$key] = "uploaded";
                }
                if ($pageParam["type"] == "checkbox") {
                    if (!isset($_POST[$key])) {
                        $_POST[$key] = 0;
                    }
                    $params[$key] = $_POST[$key];
                }
                if ($pageParam["type"] == "mainpageblocks") {
                    $index = 1;
                    $mainblocks = [];
                    while (isset($_POST["mainblock_cim{$index}"])) {
                        if (!empty($_POST["mainblock_cim{$index}"]) || !empty($_POST["mainblock_content{$index}"])) {
                            $mainblocks[] = [
                                "title" => $_POST["mainblock_cim{$index}"],
                                "content" => $_POST["mainblock_content{$index}"],
                            ];
                        }
                        $index++;
                    }
                    if (!empty($mainblocks)) {
                        $params[$key] = $mainblocks;
                    }
                }
            }

            sql_query("update webpagedata set domain=?, parent=?, params=?, aktiv=?, checkdate='0000-00-00 00:00:00' where id=?", [$_POST["domain"], $_POST["parent"], json_encode($params, JSON_PRETTY_PRINT), isset($_POST["aktiv"])?1:0, $_GET["szerk"]]);
            sql_query("update webpagedata set checkdate='0000-00-00 00:00:00' where parent=?", [$_GET["szerk"]]);

            header("location:index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

    }


    public function showPage() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        //echo "<div style='margin-bottom:20px;'>";
        //echo "<a href='index.php?page=settings'>Vissza</a>";
        //echo "</div>";

        if (isset($_GET["szerk"])) {
            $koz = 5;
            $textBoxStyle='width:500px;';
            $id = intval($_GET["szerk"]);
            $data = sql_fetch_array(sql_query("select * from webpagedata where id=?", [$id]));
            $params = json_decode($data["params"], JSON_OBJECT_AS_ARRAY);

            //echo "<pre>".print_r($params, true)."</pre>";

            echo "<div style=''>";

            echo "<form name='iform' method='post' enctype='multipart/form-data'>";

            echo "<div style='margin-top:{$koz}px;'>Domain:</div><div><input class='inputbox' style='{$textBoxStyle}' type='text' name='domain' value='{$data["domain"]}'></div>";
            echo "<div style='margin-top:{$koz}px;'>Parent:</div><div>";
            echo "<select name='parent'>";
            echo "<option value='0'>Alapértelmezett</option>";
            $parents = sql_query("select * from webpagedata where id<>? order by domain", [$id])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($parents as $parent) {
                echo "<option value='{$parent["id"]}'".($parent["id"] == $data["parent"] ? " selected":"").">".(in_array($parent["parent"], [0, 183]) || $parent["id"] == $data["parent"] ? "": "&nbsp;&nbsp;&nbsp;")."{$parent["domain"]}</option>";
            }
            echo "</select>";
            echo "</div>";
            echo "<div style='margin-top:{$koz}px;margin-bottom:10px;padding-bottom:10px;'>";
            echo "<input type='checkbox' value='1' name='aktiv'" . ($data["aktiv"] == 1 ? " checked" : "") . "> Aktív&nbsp;&nbsp;";
            echo "</div>";

            foreach ($this->webPageData->params as $key => $pageParam) {
                $orokolt = false;
                if (isset($params[$key])) {
                    $value = $params[$key];
                } else {
                    $value = $this->webPageData->getOrokoltParam($data["parent"], $key, $pageParam);
                    $orokolt = true;
                }

                echo "<div style='margin-top:{$koz}px;'>";
                if ($pageParam["type"] == "felirat") {
                    echo "<div style='background:#ccc;margin:20px 0px 10px 0px;padding:10px 10px 10px 10px;border-top:1px solid #888;border-bottom:1px solid #888;font-weight: bold;'>{$pageParam["title"]}</div>";
                }
                if ($pageParam["type"] == "textbox") {
                    echo "<div style=''>{$pageParam["title"]} ".$this->_oroklesCheckbox($data, $key, $params)."</div>";
                    echo "<div style=''><input onchange='oroklesSet(\"{$key}_orokles\");' type='text' name='{$key}' style='{$textBoxStyle}' value='{$value}' /></div>";
                }
                if ($pageParam["type"] == "image") {
                    echo "<div style=''>{$pageParam["title"]} ".$this->_oroklesImageCheckbox($data, $key, $params, $pageParam["imagetype"])."</div>";
                    echo "<div style=''><div id='asseteditor_".$pageParam["imagetype"]."'>".$this->docAgent->showAssetEditor($pageParam["imagetype"], ($orokolt?$data["parent"]:$id))."</div></div>";
                }
                if ($pageParam["type"] == "checkbox") {
                    echo "<div style=''><input name='{$key}' type='checkbox' value='1' ".($value == 1?"checked":"")." /> {$pageParam["title"]} ".$this->_oroklesCheckbox($data, $key, $params)."</div>";
                }
                if ($pageParam["type"] == "tipuskapcs") {
                    echo "<div style=''>{$pageParam["title"]} ".$this->_oroklesCheckbox($data, $key, $params)."</div>";
                    echo "<div style=''><select onchange='oroklesSet(\"{$key}_orokles\");' name='{$key}' style='{$textBoxStyle}'>";
                    echo "<option value='0'>Nincs kapcsolódó típus</option>";
                    $tipusok = sql_query("select id, megnev from szurestipusok order by megnev")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($tipusok as $tipus) {
                        echo "<option value='{$tipus["id"]}'".($tipus["id"] == $value?" selected":"").">{$tipus["megnev"]}</option>";
                    }
                    echo "</select></div>";
                }

                if ($pageParam["type"] == "mainpageblocks") {
                    $index = 1;
                    echo "<div style=''>";
                    echo "<div style='font-weight: bold;'>Tartalmi blokkok".$this->_oroklesCheckbox($data, $key, $params)."</div>";
                    foreach ($value as $mainPageBlock) {
                        echo $this->_mainPageBlockEditor($mainPageBlock, $index);
                        $index++;
                    }
                    echo $this->_mainPageBlockEditor(null, $index);
                    echo "</div>";
                }


                echo "</div>";
            }

            echo "<br><input type='submit' name='webpagedatasave' value='Mentés'> ";
            echo "<input type='submit' name='scancel' value='Vissza'> ";
            echo "</form>";

            echo "</div>";
            return;
        }

        echo "<div style='display:table-row;font-weight: bold'>";
        echo "<div class='langtd'>Domainok</div>";
        echo "<div class='langtd'></div>";
        echo "<div class='langtd'></div>";
        echo "</div>";

        //$last='';

        echo $this->_domainList(0, 0);

    }

    private function _oroklesCheckbox($data, $key, $params):string {
        $html = $checked = "";

        if ($data["parent"] != 0) {
            if (!isset($params[$key])) {
                $checked = "checked";
            }
            $html.= " | <input type='checkbox' id='{$key}_orokles' name='{$key}_orokles' value='1' {$checked}/> örökölt érték";
        }

        return $html;
    }

    private function _oroklesImageCheckbox($data, $key, $params, $imageTipus):string {
        $html = $checked = "";

        if ($data["parent"] != 0) {
            if (!isset($params[$key])) {
                $checked = "checked";
            }
            $html.= " | <input onchange='oroklesImageToggle(this, \"{$key}\", \"{$imageTipus}\", {$data["id"]}, {$data["parent"]});' type='checkbox' id='{$key}_orokles' name='{$key}_orokles' value='1' {$checked}/> örökölt érték";
        }

        return $html;
    }

    private function _mainPageBlockEditor($mainPageBlock, $index):string {
        if (empty($mainPageBlock)) {
            $mainPageBlock = [
                "title" => "",
                "content" => "",
            ];
        }
        $html = "";
        $html.= "<div style='margin-top:5px;'>Cím</div>";
        $html.= "<div style=''><input type='text' name='mainblock_cim{$index}' style='width:400px;' value='{$mainPageBlock["title"]}' /></div>";
        $html.= "<div style='margin-top:5px;'>Tartalom</div>";
        $html.= "<div style=''><textarea type='text' name='mainblock_content{$index}' style='width:700px;height:100px;'>{$mainPageBlock["content"]}</textarea></div>";
        return $html;
    }

    private function _domainList($parent, $level) {
        $html = "";
        $resData = sql_query("select id, domain, aktiv, checkresult from webpagedata d where parent=? order by d.aktiv desc, d.domain", [$parent]);

        while ($rowData = sql_fetch_array($resData)) {
            $html.= "<div style='display:table-row;" . ($rowData["aktiv"] == 1 ? "" : "opacity:.5;") . "'>";
            $html.= "<div class='langtd' style=''>".str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level).($level==0?"":" - ")."<a href='index.php?page={$_GET["page"]}&szerk={$rowData["id"]}'>{$rowData["domain"]}</a></div>";
            if ($rowData["id"] != WebPageData::DEFAULT_DATA_ID) {

                $status = "";
                if ($rowData["checkresult"] == "not found") {
                    $status = "<span style='color:#f00;'>nem létező domain</span>";
                }
                if ($rowData["checkresult"] == "found") {
                    $status = "régi oldal!";
                }
                if ($rowData["checkresult"] == "ok") {
                    $status = "<span style='background:#0a0;color:#fff;'>új oldal ok</span>";
                }

                $html .= "<div class='langtd' style=''><a target='_blank' href='http://{$rowData["domain"]}'>megnyitás</a></div>";
                $html .= "<div class='langtd' style=''>" . ($rowData["aktiv"] == 1 ? "<span style='color:green;'>Aktív</span>" : "Inaktív") . "</div>";
                $html .= "<div class='langtd' style=''>{$status}</div>";
            }
            $html.= "</div>";
            $html.= $this->_domainList($rowData["id"], $level+1);
        }
        return $html;
    }

}

