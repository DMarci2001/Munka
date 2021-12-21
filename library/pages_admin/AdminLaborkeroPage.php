<?php

use mikehaertl\pdftk\Pdf;

class AdminLaborkeroPage extends AdminCorePage
{

    private $error;
    public  $success;

    public function __construct()
    {
        if (isset($_POST["saveNewPackege"])) {
            if (!empty($_POST["newPackegeName"])) {
                if (!empty($_POST["newPrice"])) {
                    $array = array();
                    foreach ($_POST as $item => $price) {
                        if (strpos($item, "sltc") !== false) {
                            array_push($array, str_replace("sltc-", "", $item));
                        }
                    }

                    sql_query("INSERT INTO synlab_labor_csomagok SET name=?,price=?,appform=?,items=?", array($_POST["newPackegeName"], $_POST["newPrice"], $_POST["AppId"], json_encode($array, true)));
                    $this->success .= "<p style=\"color:#278d2f;font-weight:bold;font-size:16px;margin:2px;\"> - Csomag sablon mentésre került! (ID:" . sql_insert_id() . ")</p>";
                } else {
                    if (empty($this->error)) $this->error = "<p style=\"color:red;font-weight:bold;font-size:16px;margin:2px;\">HIBA!</p>";
                    $this->error .= "<p style=\"color:red;font-weight:bold;font-size:16px;margin:2px;\"> - Új csomag megadásakor az ár kötelező!</p>";
                }
            } else {
                if (empty($this->error)) $this->error = "<p style=\"color:red;font-weight:bold;font-size:16px;margin:2px;\">HIBA!</p>";
                $this->error .= "<p style=\"color:red;font-weight:bold;font-size:16px;margin:2px;\"> - Új csomag megadásakor a név kötelező!</p>";
            }
        }

        if (isset($_POST["deleteThisPack"])) {
            sql_query("DELETE FROM synlab_labor_csomagok WHERE id=?", array($_POST["PackId"]));
            $this->success .= "<p style=\"color:#278d2f;font-weight:bold;font-size:16px;margin:2px;\"> - Csomag törlése került!</p>";
            unset($_POST["PackId"]);
        }

        if (isset($_POST["saveThisPack"])) {
            if (!empty($_POST["packPrice"])) {
                $array = array();
                foreach ($_POST as $item => $price) {
                    if (strpos($item, "sltc") !== false) {
                        array_push($array, str_replace("sltc-", "", $item));
                    }
                }
                sql_query("UPDATE synlab_labor_csomagok SET price=?,items=? WHERE id=?", array($_POST["packPrice"], json_encode($array, true), $_POST["PackId"]));
                $this->success .= "<p style=\"color:#278d2f;font-weight:bold;font-size:16px;margin:2px;\"> - Csomag sablon mentésre került! (ID:{$_POST["PackId"]})</p>";
            } else {
                if (empty($this->error)) $this->error = "<p style=\"color:red;font-weight:bold;font-size:16px;margin:2px;\">HIBA!</p>";
                $this->error .= "<p style=\"color:red;font-weight:bold;font-size:16px;margin:2px;\"> - Csomag módosításakor az ár kötelező!</p>";
            }
        }

        if (isset($_POST["setPack"]) && $_POST["PackId"] == 0) {
            foreach ($_POST as $item => $value) {
                if (strpos($item, "sltc") !== false) {
                    unset($_POST[$item]);
                }
            }
        }

        if (isset($_POST["getsynlabstatus"])) {

            $items = array();
            $total = 0;
            $tubeSetup = "";

            $synlab = new SynlabService();

            $_POST["items"] = array_filter($_POST["items"]);

            //Ha van csomag, lekérdezem a hozzá tartozó adatokat:
            if (isset($_POST["packId"]) && $_POST["packId"] != 0) {

                $pack = sql_fetch_array(sql_query("SELECT * FROM synlab_labor_csomagok WHERE id=?", array($_POST["packId"])));
                $items = json_decode($pack["items"], true);
                $total = $pack["price"];
            }

            //A csomag tartalmán felül jelölt tételeket hozzáadom az összárhoz:
            foreach ($_POST["items"] as $id => $value) {
                if (!in_array($id, $items)) {
                    $total = ($total + $value);
                }
            }


            $tubeSetup = $synlab->setTubeList($_POST["items"], "ajax");
            //die($tubeSetup);

            die(json_encode(array("price" => number_format($total, 2), "unit" => count($_POST["items"]), "tubes" => $tubeSetup), true));
        }

        if (isset($_POST["printSynlab"])) {

            $synlab = new SynlabService();

            $filename = $synlab->createPDF($_POST);

            header("Content-type: application/pdf");
            header("Content-Disposition: inline; filename=" . $filename);
            @readfile("../../public/admin/templates/" . $filename);

            unlink("../../public/admin/templates/" . $filename);

            die();
        }

        if (isset($_POST["saveDoc"])) {
            $synlab = new SynlabService();
            $filename = $synlab->createPDF($_POST);
            $path = "../../public/admin/templates/";
            $size = filesize($path . $filename);
            $extension =  pathinfo($path . $filename, PATHINFO_EXTENSION);

            if (in_array($extension, array("pdf", "doc", "xls", "docx", "xlsx", "jpg", "jpeg"))) {
                sql_query(
                    "INSERT  INTO dokumentumok SET 
                     foglalasid=?, megnev=?, filename=?, size=?, tipus=?, datum=now(), kod=SHA1(MD5(CONCAT(NOW(),RAND()*20000)))",
                    array($_GET["fid"], $filename, $filename, $size, $extension)
                );
                $id = sql_insert_id();

                $id = (int)$id;
                $destinationFile = Booking_Constants::DOCUMENT_PATH . floor($id / 1000);
                if (!is_dir($destinationFile)) mkdir($destinationFile);
                $destinationFile .= "/{$id}.bin";

                rename($path . $filename, $destinationFile);

                $this->success .= "<p style=\"color:#278d2f;font-weight:bold;font-size:16px;margin:2px;\"> - Fájl mentése sikerült!!</p>";
            } else {
                $this->error = "<p style=\"color:red;font-weight:bold;font-size:16px;margin:2px;\">Fájl feltöltés sikertelen!</p>";
            }
        }

        if (isset($_POST["searchPatient"]) && $_POST["searchPatient"] == true) {

            $request = sql_query("SELECT * FROM {$_POST["source"]} WHERE nev like \"%{$_POST["word"]}%\" GROUP BY szuldatum,nev ORDER BY nev ASC LIMIT 20");

            echo "<option></option>";
            while ($result = sql_fetch_array($request)) {
                echo "<option value=\"{$result["id"]}\" name=\"{$result["nev"]}\">{$result["nev"]} - {$result["szuldatum"]}</option>";
            }

            die();
        }

        if (isset($_POST["setPatientData"]) && $_POST["setPatientData"] == true) {

            $htmlout = "";
            $arr = sql_fetch_array(sql_query("SELECT * FROM {$_POST["source"]} WHERE id=?", array($_POST["aid"])));

            $htmlout .= "<tr><td>Név:</td><td><input type=\"textbox\" name=\"nev\" value=\"{$arr["nev"]}\"></td>";
            $htmlout .= "<td>Szül. hely:</td><td><input type=\"textbox\" name=\"szulhely\" value=\"{$arr["szulhely"]}\"></td></tr>";
            $htmlout .= "<tr><td>Születési neve:</td><td><input type=\"textbox\" name=\"szulnev\" value=\"{$arr["nev"]}\"></td>";
            $htmlout .= "<td>TAJ:</td><td><input type=\"textbox\" name=\"taj\" value=\"{$arr["taj"]}\"></td></tr>";
            $htmlout .= "<tr><td>Szül. dátum:</td><td><input type=\"textbox\" name=\"szuldatum\" value=\"{$arr["szuldatum"]}\"></td>";
            $htmlout .= "<td>Telefon:</td><td><input type=\"textbox\" name=\"telefon\" value=\"{$arr["telefon"]}\"></td></tr>";
            $htmlout .= "<tr><td>Lakcím (helyiség):</td><td><input type=\"textbox\" name=\"varos\" value=\"\"></td>";
            $htmlout .= "<td>Lakcím (utca,hsz.):</td><td><input type=\"textbox\" name=\"cim\" value=\"\"></td></tr>";
            $htmlout .= "<tr><td>Leletküldés e-mail:</td><td><input type=\"textbox\" name=\"email\" value=\"{$arr["email"]}\"></td>";
            $htmlout .= "<td>Neme:</td><td>Férfi<input type=\"radio\" name=\"neme\" value=\"ferfi\">&nbsp;Nő<input type=\"radio\" name=\"neme\" value=\"no\"></td></tr>";
            $htmlout .= "<tr><td>Iránydiag./ BNO:</td><td><input type=\"textbox\" name=\"bno\"></td>";
            $htmlout .= "<td>Terhességi hét:</td><td><input type=\"textbox\" name=\"terhessegihet\" value=\"\"></td></tr>";

            die($htmlout);
        }

        if (isset($_POST["createTemplate"])) {
            if ($_POST["template_name"] != "") {
                $numb = 0;
                $checkName = sql_query("SELECT * FROM synlab_labor_info_sablonok WHERE name=?", array($_POST["template_name"]));
                if ($numb = sql_num_rows($checkName) > 0) {
                    $_POST["template_name"] = $_POST["template_name"] . "({$numb})";
                }
                $array = array(
                    $_POST["template_name"],
                    $_POST["synlabtelephely"],
                    $_POST["bekuldokod"],
                    $_POST["bekuldonev"],
                    $_POST["bekuldocim"],
                    $_POST["orvosnev"],
                    $_POST["pecsetszam"],
                    $_POST["kuldesiemail"],
                    $_POST["terites"],
                    $_POST["befazon"],
                    $_POST["szamlazasinev"],
                    $_POST["szamlazasicim"],
                    date("Y-m-d H:i:s")
                );
                sql_query("INSERT INTO synlab_labor_info_sablonok SET name=?, synlabtelephely=?, bekuldokod=?, bekuldonev=?, bekuldocim=?, orvosnev=?, pecsetszam=?, 
                kuldesiemail=?, terites=?, befazon=?, szamlazasinev=?, szamlazasicim=?, kelte=?", $array);
                $this->success .= "<p style=\"color:#278d2f;font-weight:bold;font-size:16px;margin:2px;\"> - Synlab sablon mentésre került! (ID:" . sql_insert_id() . ")</p>";
            } else {
                $this->error .= "<p style=\"color:red;font-weight:bold;font-size:16px;margin:2px;\"> - Új sablon megadásakor a név kötelező!</p>";
            }
        }

        if (isset($_GET["beutalo_generalas"])) {

            $input = [
                "nev" => "Márton Gergely",
                "szulev" => "1994",
                "szulho" => "09",
                "szulnap" => "23",
                "lakcim" => "2162 Örbottyán, Puskás Ferenc u. 74",
                "munkakor" => "Webfejlesztö- programozóáéőű",
                "taj" => "09123456",
                "keltezes" => "Budapest, 2021.02.23"
            ];

            $filename = "Teszt_beutalo.pdf";

            $pdf = new Pdf("../../public/admin/templates/beutalo_alpin.pdf");
            $result = $pdf->fillForm($input)
                ->flatten()
                ->saveAs("../../public/admin/templates/" . $filename);

            if ($result === false) {
                $error = $pdf->getError();

                var_dump($error);
            }

            header("Content-type: application/pdf");
            header("Content-Disposition: inline; filename=" . $filename);
            @readfile("../../public/admin/templates/" . $filename);

            //unlink("../../public/admin/templates/" . $filename);

            die();
        }
    }

    public function showPage()
    {

        $synlab = new SynlabService();
        $patient = array();

        if (isset($_GET["fid"])) {
            $patient = sql_fetch_array(sql_query("SELECT * FROM foglalasok where id = ? AND pass=?", array($_GET["fid"], $_GET["p"])));
        }


        echo "<form method=\"POST\" name=\"synlabParamsForApplication\" id=\"synlabParamsForApplication\">";
        echo "<div class=\"pagehead\"><div style=\"display:table-cell;vertical-align:middle;\">Synlab laborkérő kiállítás</div>";

        if (isset($_POST["AppId"]) && $_POST["AppId"] != 0) {

            echo "<div style=\"display:table-cell;vertical-align:middle;\">";
            echo "&nbsp;&nbsp;&nbsp;<input type=\"submit\"class=\"printbutton\" style=\"padding:10px 15px 10px 25px;font-size:14px;cursor:pointer\" formtarget=\"_blank\" name=\"printSynlab\" value=\"Nyomtatás\">";
            echo "</div>";

            if (isset($_GET["fid"])) {
                echo "<div style=\"display:table-cell;vertical-align:middle;\">";
                echo "&nbsp;&nbsp;&nbsp;<input type=\"submit\"  style=\"background-color:#33b53d;padding:10px 15px 10px 15px;font-size:14px\" name=\"saveDoc\" value=\"Mentés\">";
                echo "</div>";
            }
        }

        echo "</div>";

        echo $this->error;
        echo $this->success;

        echo "<span style=\"font-size:18px;display:inline-block;margin:5px;\"><strong>Laborkérő forma nyomtatványok:</strong>&nbsp;&nbsp;<select name=\"AppId\">";
        echo $synlab->setApplicationFormList((isset($_POST["AppId"])) ? $_POST["AppId"] : null);
        echo "</select>&nbsp;&nbsp;<input type=\"submit\" name=\"setAppForm\" value=\"Kiválasztás\"></span>";

        if (isset($_POST["AppId"]) && !empty($_POST["AppId"]) && $_POST["AppId"] != 0) {
            echo "<span style=\"font-size:18px;display:inline-block;margin:5px;\"><strong>Csomagok:</strong>&nbsp;&nbsp;";
            echo $synlab->setApplicationFormPackages($_POST["AppId"], (isset($_POST["PackId"])) ? $_POST["PackId"] : null);
            echo "</span>";

            echo "<span style=\"font-size:18px;display:inline-block;margin:5px;\"><strong>Új csomag mentése:</strong>&nbsp;&nbsp;<input type=\"textbox\" name=\"newPackegeName\">";
            echo "&nbsp;&nbsp;Ár:&nbsp;&nbsp;<input style=\"width:60px\" type=\"textbox\" name=\"newPrice\">";
            echo "&nbsp;&nbsp;<input type=\"submit\" style=\"background-color:#33b53d\" name=\"saveNewPackege\" value=\"+ Mentés\">";
            echo "</span>";
        }
        //Ügyfél adatok:
        echo "<div style=\"display:block;overflow:hidden;\">";
        echo $synlab->setPatientData((isset($_POST) && !empty($_POST)) ? $_POST : $patient);
        echo $synlab->showAppFormStatus((isset($_POST["PackId"])) ? $_POST["PackId"] : null, (isset($_POST["synlabInfo"])) ? $_POST : null);
        echo "</div>";

        //Laborkérő tételek:
        echo "<div>";
        if (isset($_POST["AppId"]) && !empty($_POST["AppId"]) && $_POST["AppId"] != 0) {
            echo $synlab->setItemTablesForApplicationForm($_POST["AppId"], $_POST);
        }
        echo "</div>";

        echo "</form>";
    }
}
