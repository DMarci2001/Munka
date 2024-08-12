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

        //$data = sql_query("SELECT * FROM tesco_2024_segedtabla")->fetchAll(PDO::FETCH_ASSOC);



        //Tól-ig
        /*foreach($data as $index=>$value){
            $value["ido"] = explode("-",$value["ido"]);

            if(!isset($value["ido"][1])){
                echo $value["id"]." - ".$value["uzletszam"]."<br>";
            }

            sql_query("UPDATE tesco_2024_segedtabla SET tol=?, ig=? WHERE id=?",array($value["ido"][0],$value["ido"][1],$value["id"]));
        }*/

        //Tól-ig értékek korrigálása
        /*foreach ($data as $index => $value) {
            echo $value["tol"]." - ".date("H:i",strtotime($value["tol"]))."<br>";
            sql_query("UPDATE tesco_2024_segedtabla SET tol=?,ig=? WHERE id=?",array(date("H:i",strtotime($value["tol"])),date("H:i",strtotime($value["ig"])),$value["id"]));
        }*/

        //Rinterval kalkulálása
        /*foreach($data as $index=>$value){
            $start = strtotime($value["tol"]);
            $end = strtotime($value["ig"]);
            $elapsed = $end - $start;
            $rinterval = floor((($elapsed/60)/$value["letszam"]));
            echo $elapsed."s<br>";
            echo $value["tol"]." - ".$value["ig"]." ";
            echo "Köztes idő: ".($elapsed/60)." perc ";
            echo "rinterval: {$rinterval} perc / fő ({$value["letszam"]})";
            if(($rinterval*$value["letszam"])>($elapsed/60)){
                echo "<span style=\"color:red;font-weight:bold\">Túl lépi a rendelkezésre álló időt!</span>";
            }
            echo "<br>";
            sql_query("UPDATE tesco_2024_segedtabla SET rinterval=? WHERE id=?",array($rinterval,$value["id"]));

        }*/

        //Helyszinek átírása
        /*$maps = new maps();
        foreach($data as $index=>$value){
            echo $value["cim"]."<br>";
            if(!is_numeric(substr($value["cim"],0,4))){
                echo "<span style=\"color:red;font-weight:bold\">Nem rendelkezik irányítószámmal!</span><br>";
                $mapData = json_decode($maps->geoCoding($value["cim"]),true);
                $key = $this->getKeyByNestedValue($mapData["results"][0]["address_components"],"types","postal_code");
                $uzletszam = explode("-",$value["uzletszam"]);
                $cimArray = explode(" ",$value["cim"]);
                $irsz = $mapData["results"][0]["address_components"][$key]["long_name"];
                //$irsz = 1234;
                $varos = $cimArray[0];
                unset($cimArray[0]);
                $ujCim1 = $varos." ({$irsz}), ".implode(" ",$cimArray);
                $ujCim2 = $varos." ({$irsz}), ".implode(" ",$cimArray)." - {$uzletszam[1]}. Üzlet";
                $ujCim3 = "{$uzletszam[1]}. Üzlet - ".$varos." ({$irsz}), ".implode(" ",$cimArray);
                echo "Új cím (Ver. 1.): {$ujCim1}<br>";
                echo "Új cím (Ver. 2.): {$ujCim2}<br>";
                echo "Új cím (Ver. 3.): {$ujCim3}<br>";
                sql_query("UPDATE tesco_2024_segedtabla SET helyszin1=?, helyszin2=?,helyszin3=? WHERE id=?",array($ujCim1,$ujCim2,$ujCim3,$value["id"]));
                
            }else{
                $uzletszam = explode("-",$value["uzletszam"]);
                $cimArray = explode(" ",$value["cim"]);
                $irsz = $cimArray[0];
                unset($cimArray[0]);
                
                $varos = $cimArray[1];
                unset($cimArray[1]);
                $ujCim1 = $varos." ({$irsz}), ".implode(" ",$cimArray);
                $ujCim2 = $varos." ({$irsz}), ".implode(" ",$cimArray)." - {$uzletszam[1]}. Üzlet";
                $ujCim3 = "{$uzletszam[1]}. Üzlet - ".$varos." ({$irsz}), ".implode(" ",$cimArray);
                if(!isset($uzletszam[1])){
                    echo $value["uzletszam"]." - ".$value["id"]."<br>";
                }
                echo "<span style=\"color:green;font-weight:bold\">Rendelkezik irányítószámmal!</span><br>";
                echo "Új cím (Ver. 1.): {$ujCim1}<br>";
                echo "Új cím (Ver. 2.): {$ujCim2}<br>";
                echo "Új cím (Ver. 3.): {$ujCim3}<br>";
                sql_query("UPDATE tesco_2024_segedtabla SET helyszin1=?, helyszin2=?,helyszin3=? WHERE id=?",array($ujCim1,$ujCim2,$ujCim3,$value["id"]));
            }
            echo "<br>";
        }*/

        //Helyszinek importálása
        /*foreach($data as $index=>$value){
            sql_query("INSERT INTO helyszinek SET cim=?,aktiv=1, datum=?",array($value["helyszin3"],date("Y-m-d H:i:s")));
            $helyszinData = sql_query("SELECT * FROM helyszinek WHERE INSTR(cim,'{$value["helyszin3"]}')")->fetch(PDO::FETCH_ASSOC);
            $helyszinid = $helyszinData["id"];
            sql_query("UPDATE tesco_2024_segedtabla SET helyszinid=? WHERE id=?",array($helyszinid, $value["id"]));
            echo "Új helyszín rögzítve!({$value["helyszin3"]})(ID: {$helyszinid})<br>";
        }*/

        

        //Egyéb paraméterek rögzítése
        /*foreach ($data as $index => $value) {
            if (strpos($value["uzletszam"], "AIIS") === false) {
                //sql_query("UPDATE tesco_2024_segedtabla SET cegid=?,groupid=?,tipusid=?,orvosid=? WHERE id=?",array(682,14534,48,1396,$value["id"]));

                sql_query(
                    "INSERT INTO orvos_beosztas_new SET orvosid=?,helyszinid=?,nap=10,beonap=?,tol=?,ig=?,binterval=?,tipusok=?,aktiv=1,groupid=?,beocegek=?",
                    array(
                        $value["orvosid"], $value["helyszinid"], $value["datum"], $value["tol"], $value["ig"],
                        $value["rinterval"], "|" . $value["tipusid"] . "|", $value["groupid"], "|" . $value["cegid"] . "|"
                    )
                );
                $beoid=sql_insert_id();
                sql_query("UPDATE tesco_2024_segedtabla SET beoid=? WHERE id=?",array($beoid,$value["id"]));
                echo "Beosztás létrehozva! ({$beoid}.)<br>";
            }
        }*/

        /*foreach($mapData["results"][0]["address_components"] as $index=>$component){
            $key = array_search("postal_code",$component["types"]);
            if($key!==false){
                return $index;
            }
            //echo $key."<br>";
        }
        echo "itt a kulcs:".$key;*/



        //die();

        /*if (isset($_POST["uploadDokirexExport"])) {
            if (isset($_FILES)) {
                $this->dokirexExportData($_FILES["file"]["tmp_name"]);
            }
        }*/
    }

    public function getKeyByNestedValue($dictionary, $arrayName, $value)
    {
        foreach ($dictionary as $index => $component) {
            $key = array_search($value, $component[$arrayName]);
            if ($key !== false) {
                return $index;
            }
        }
        return false;
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
