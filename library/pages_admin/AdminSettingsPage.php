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

        if (isset($_REQUEST['uploadfiles'])) {
            $smartFile       = array();
            $missingUsers = array();
            $successFind  = array();
            $z = 0;
            $t = 0;
            foreach ($_FILES['FileUpload']['name'] as $key => $file) {
                //Ha a fájl neve tartalmazza a "!" karaktert hagyja ki a vizsgálatból:
                if (strpos($file, "!") === false) {
                    $infoString  = explode("_", $file);
                    $type          = explode(".", $file);
                    $szuldatum   = explode(".", $infoString[3]);
                    $smartFile[] = array(
                        "filename" => $infoString[0],
                        "taj" => $infoString[2],
                        "szuldatum" => $szuldatum[0],
                        "release" => $infoString[1],
                        "type" => $type[1],
                        "TEMP" => $_FILES['FileUpload']['tmp_name'][$key],
                        "size" => $_FILES['FileUpload']['size'][$key],
                        "origin" => $file
                    );
                } else continue;
            }
            $f = 0;
            $z = 0;
            for ($i = 0; $i < count($smartFile); $i++) {
                if (in_array($smartFile[$i]['type'], array("pdf", "doc", "xls", "docx", "xlsx"))) {
                    if ($result = sql_fetch_array(sql_query("SELECT felh.*,c.megnev FROM felhasznalok felh
													   LEFT JOIN cegek c ON c.id = felh.cegid
													   WHERE taj = ? AND szuldatum = ?", array($smartFile[$i]['taj'], $smartFile[$i]['szuldatum'])))) {
                        $successFind[] = array(
                            "taj" => $smartFile[$i]['taj'],
                            "szuldatum" => $smartFile[$i]['szuldatum'],
                            "file" => $smartFile[$i]['origin'],
                            "nev" => $result['nev'],
                            "ceg" => $result['megnev']
                        );
                        sql_query("INSERT INTO dokumentumok SET 
						   beutaloid = 0, userid =" . $result['id'] . ", 
						   megnev    = '" . addslashes($smartFile[$i]["filename"]) . "',
						   filename  = '" . addslashes($smartFile[$i]["filename"]) . ".pdf',
						   size      = '" . addslashes($smartFile[$i]['size']) . "',
						   tipus     = '{$smartFile[$i]['type']}',
						   datum     = now(),
						   kod       = SHA1(MD5(CONCAT(NOW(),RAND()*20000)))");
                        $id = sql_insert_id();
                        $destination = getDocPath($id);
                        if (move_uploaded_file($smartFile[$i]["TEMP"], $destination)) {
                        } else {
                            echo "Nem sikerült a feltöltés! ({$smartFile[$i]['origin']})<br/>";
                        }
                    } else {
                        $z++;
                        $missingUsers[] = array(
                            "taj" => $smartFile[$i]['taj'],
                            "szuldatum" => $smartFile[$i]['szuldatum'],
                            "file" => $smartFile[$i]['origin']
                        );
                    }
                } else {
                    echo "Hibás formátum!({$smartFile[$i]["filename"]})<br/>";
                }
            }

            $k     = 0;
            $exist = "({$f})Sikeres feltöltési próbálkozások:<br/>";
            if (!empty($successFind)) {
                foreach ($successFind as $success) {
                    $k++;
                    $exist .= "<p>[{$k}][Sikeres][{$success['nev']}][ {$success['ceg']} ]-[ {$success['file']} ]</p>";
                }
            } else {
                $exist = "";
            }

            $n        = 0;
            $nonExist =  "({$z})Sikertelen feltöltési próbálkozások:<br/>";
            if (!empty($missingUsers)) {
                foreach ($missingUsers as $miss) {
                    $n++;
                    $nonExist .= "<p>[{$n}][Sikertelen]=>[ {$miss['taj']} ]=>[ {$miss['szuldatum']} ]=>[ {$miss['file']} ]</p>";
                }
            } else {
                $nonExist = "";
            }
            $output = $exist . $nonExist;
            echo $output;
        }

        if (isset($_POST["uploadDokirexExport"])) {
            if (isset($_FILES)) {
                $this->dokirexExportData($_FILES["file"]["tmp_name"]);
            }
        }
    }

    public function showPage()
    {
        echo $this->showErrors();
        echo $this->showSuccess();

        echo "<div style='margin-bottom:20px;'>";
        echo "<a href='index.php?page=langsettings'>Többnyelvű szövegek beállítása</a>";
        echo "</div>";

        $row = sql_fetch_array(sql_query("select * from settings"));

        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<div>Munkaszüneti napok: </div>";
        echo "<div style='font-size:11px;'>(dátumok vesszővel elválasztva)</div>";
        echo "<div><textarea name='szunnapok' style=' box-sizing: border-box;width:100%;height:100px;'>{$row["szunnapok"]}</textarea></div>";

        echo "<div style='margin-top:10px;'><input type='submit' name='settingsmentes' value='Mentés'></div>";
        echo "</form>";

        echo "<div style='margin-top:20px'>Páciens dokumentumok feltöltése (MAX 300db):</div>";
        echo "<div><form method='POST' enctype='multipart/form-data' name='patient_docs' id = 'massDocs'>";
        echo "<input type='file' name='FileUpload[]' webkitdirectory mozdirectory msdirectory odirectory directory multiple />";
        echo "<div style = 'margin-top:10px'><input type = 'submit' onClick = 'massUpload();return false' name='uploadfiles' value='Feltöltés'></div>";
        echo "<div style = 'font-size:11px;color:gray'>a fájl név formátum pedig a következő legyen :fájlnév_tajszám_ÉÉÉÉ-HH-NN</div>";
        echo "</form></div>";
        echo "<div id = 'upload-result'></div>";

        echo "<div style=\"margin-top:20px\">Dokirex Export Adatok betöltése</div>";
        echo "<div><form method=\"POST\" enctype=\"multipart/form-data\" name=\"dokirex-export\" id = \"dokirex-export\">";
        echo "<input type=\"file\" name=\"file\" id=\"file\"><br>";
        echo "<div style = 'margin-top:10px'><input type = 'submit' name='uploadDokirexExport' value='Feltöltés'></div>";
        echo "</form></div>";
        echo "<div id = 'upload-result'>";

        echo "</div>";
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
