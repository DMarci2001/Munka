<?php

class AdminSettingsPage extends AdminCorePage
{


    public function __construct()
    {
        parent::__construct();

        if (isset($_POST["settingsmentes"])) {
            $szunnapok = str_replace(".", "-", $_POST["szunnapok"]);
            sql_query("update settings set szunnapok=?", array($szunnapok));

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        /*if (isset($_POST["uploadDokirexExport"])) {
            if (isset($_FILES)) {
                $this->dokirexExportData($_FILES["file"]["tmp_name"]);
            }
        }*/
    }

    public function showPage()
    {
        if (!$this->adminUser->beallitasMunkaszunetinapokAccess()) {
            echo $this->noPermissionMessage();
            return;
        }
        echo $this->showErrors();
        echo $this->showSuccess();

        $row = sql_fetch_array(sql_query("select * from settings"));

        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<div>Munkaszüneti napok: </div>";
        echo "<div style='font-size:11px;'>(dátumok vesszővel elválasztva)</div>";
        echo "<div><textarea name='szunnapok' style=' box-sizing: border-box;width:100%;height:100px;'>{$row["szunnapok"]}</textarea></div>";

        echo "<div style='margin-top:10px;'><input type='submit' name='settingsmentes' value='Mentés'></div>";
        echo "</form>";

        /*echo "<div style=\"margin-top:20px\">Dokirex Export Adatok betöltése</div>";
        echo "<div><form method=\"POST\" enctype=\"multipart/form-data\" name=\"dokirex-export\" id = \"dokirex-export\">";
        echo "<input type=\"file\" name=\"file\" id=\"file\"><br>";
        echo "<div style = 'margin-top:10px'><input type = 'submit' name='uploadDokirexExport' value='Feltöltés'></div>";
        echo "</form></div>";
        echo "<div id = 'upload-result'>";*/

        //echo "</div>";
    }

    private function dokirexExportData($file)
    {
        $html = file_get_contents($file);
        $companyArray = array();
        $workArray = array();

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $companySelect = $xpath->query("//select[@name='custom[15]']");
        //$companySelect = $xpath->query("//select[@name='custom[13]']");
        $workSelect = $xpath->query("//select[@name='custom[16]']");

        if ($companySelect->length == 0) {
            $this->errors[] =  "A listában nem találhatók a cégek!";
        }

        if ($workSelect->length == 0) {
            $this->errors[] = "A listában nem találhatók a munkakörök!";
        }

        if (count($this->errors) > 0) {
            return;
        }

        //Cégek importálása:
        $companySelect = $companySelect->item(0)->getElementsByTagName("option");
        $option = $companySelect->item(0);
        $first = $option = $companySelect->item(0);
        $first->parentNode->removeChild($first);
        $option = $companySelect->item(0);

        if (sql_num_rows(sql_query("SELECT * from dokirex_telephelyek")) > 0) {
            sql_query("DELETE FROM dokirex_telephelyek");
        }

        while ($option != NULL) {
            $option = $companySelect->item(0);

            if ($option === null) {
                break;
            }

            $companyArray[] = $option->nodeValue;
            $companyArray[] = $option->getAttribute("value");
            $companyArray[] = date("Y-m-d H:i:s");
            $companyArray[] = $this->adminUser->user["nev"];

            $first = $option = $companySelect->item(0);
            $first->parentNode->removeChild($first);

            $option = $companySelect->item(0);
        }

        $length = (count($companyArray) / 4);
        $value = "";
        for ($i = 0; $i < $length; $i++) {
            $value .= ($i > 0 ? "," : "") . "(?,?,?,?)";
        }

        $uploadCompanies = sql_query("INSERT INTO dokirex_telephelyek (megnev, dokirexid, datum, rogzitette) VALUES {$value}", $companyArray);
        if (!$uploadCompanies) {
            $this->errors[] = "Cégek rögzítése sikertelen!";
        } else {
            $this->success[] = "Cégek rögzítése sikeres!";
        }

        //Munkakörök importálása:
        $workSelect = $workSelect->item(0)->getElementsByTagName("option");
        $option = $workSelect->item(0);
        $first = $option = $workSelect->item(0);
        $first->parentNode->removeChild($first);
        $option = $workSelect->item(0);

        if (sql_num_rows(sql_query("SELECT * from dokirex_munkakorok")) > 0) {
            sql_query("DELETE FROM dokirex_munkakorok");
        }
        while ($option != NULL) {
            $option = $workSelect->item(0);

            if ($option === null) {
                break;
            }

            $workName = $option->nodeValue;
            $workId = $option->getAttribute("value");

            $workArray[] = $workId;
            $workArray[] = $workName;
            $workArray[] = date("Y-m-d H:i:s");
            $workArray[] = $this->adminUser->user["nev"];

            $first = $option = $workSelect->item(0);
            $first->parentNode->removeChild($first);

            $option = $workSelect->item(0);
        }
        $length = (count($workArray) / 4);
        $value = "";
        for ($i = 0; $i < $length; $i++) {
            $value .= ($i > 0 ? "," : "") . "(?,?,?,?)";
        }

        $uploadWorks = sql_query("INSERT INTO dokirex_munkakorok (dokirexid, megnev, datum, rogzitette) VALUES {$value}", $workArray);
        if (!$uploadWorks) {
            $this->errors[] = "Munkakörök rögzítése sikertelen!";
        } else {
            $this->success[] = "Munkakörök rögzítése sikeres!";
        }
    }
}
