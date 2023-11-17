<?php

use mikehaertl\pdftk\Pdf;

class SynlabService
{
    public array $bekuldoKodok = ["000000719", "HMMSZURES", "HMMMAALLK"];

    public function __construct()
    {
        
    }

    public function createLabItem()
    {
    }

    public function setApplicationFormList($AppId)
    {

        $option = "";
        $request = sql_query("SELECT * FROM synlab_labor_kerolapok WHERE aktiv=1 ORDER BY name ASC");

        if (sql_num_rows($request) > 0) {
            $option = "<option value=\"0\">Válassz egy kérőlapot!</option>";
            while ($result = sql_fetch_array($request)) {
                if (isset($AppId) && $AppId == $result["id"]) {
                    $selected = "selected";
                } else {
                    $selected = "";
                }
                $option .= "<option {$selected} value=\"{$result["id"]}\">{$result["name"]}</option>";
            }
        } else {
            $option = "<option value=\"0\"> - Üres - </option>";
        }


        return $option;
    }

    public function setItemTablesForApplicationForm($appId, $data)
    {
        $categories = $packArray = array();
        $max = null;
        //Group by olni kell és ki kell szűrni az összes kategóriát a laborkérő->tételek->tétel kategóriák mentén.

        if (isset($data["PackId"]) && $data["PackId"] != 0) {
            $packArray = json_decode(implode("", sql_fetch_row(sql_query("SELECT items FROM synlab_labor_csomagok WHERE id=?", array($data["PackId"])))), true);
        }

        $qc = sql_query("SELECT sltk.*, (SELECT count(id) FROM synlab_labor_tetelek WHERE appform=slt.appform AND category=sltk.id) AS itemdb FROM synlab_labor_tetelek slt
                           LEFT JOIN synlab_labor_tetel_kategoriak sltk on sltk.id=slt.category
                           WHERE slt.appform = ?
                           GROUP BY sltk.id
                           ORDER BY 
                           CASE WHEN sltk.name LIKE '%Egyéb%' THEN INSERT(sltk.name,1,0,'Z') ELSE sltk.name END
                           ASC", array($appId));


        //Itt kell le generálnom a táblákat
        while ($resc = sql_fetch_array($qc)) {
            if (empty($max)) {
                $max = 14;
            }
            echo "<div style=\"display:inline-block;background-color:#088DA5;color:white;float:left;border:1px solid black;margin:2px;min-width:300px;width:24.6%;height:" . ($max * 27) . "px;overflow-y:auto\"><table style=\"display:inline-block;float:left;width:300px\">";
            echo "<tr><td colspan=\"3\" style=\"font-size:20px;white-space:wrap;\"><strong>{$resc["name"]}</strong>";

            //Ha van mennyiség megadási kötelezettség:

            if (!empty($resc["measure"] || !empty($resc["time"]))) {
                echo "<br>";
            }

            if (!empty($resc["measure"])) {
                echo "<input style=\"width:40px\" style=\"width:40px\" type=\"textbox\" name=\"sltkm-{$resc["id"]}\">&nbsp;{$resc["measure"]}";
            }
            if (!empty($resc["time"])) {
                echo "&nbsp;&nbsp;/&nbsp;<input style=\"width:40px\" type=\"textbox\" name=\"sltkt-{$resc["id"]}\">&nbsp;{$resc["time"]}";
            }
            echo "</td></tr>";

            $qt = sql_query("SELECT * FROM synlab_labor_tetelek WHERE appform=? AND category=?", array($appId, $resc["id"]));

            while ($rest = sql_fetch_array($qt)) {
                echo "<tr><td style=\"width:100%\">{$rest["name"]}";
                if (!empty($rest["select"])) {
                    echo "&nbsp;&nbsp;<select style=\"width:100px\" name=\"slts-{$rest["id"]}\">";
                    echo "<option value=\"\">Válasszon egyet!</option>";
                    foreach (json_decode($rest["select"], true) as $option) {
                        echo "<option value=\"{$option}\">{$option}</option>";
                    }
                    echo "</select>";
                }

                //Meg kell vizsgálnom a csomag árát és a benne szereplő tételeket, majd ehhez az árhoz hozzá kell adnom a további elemek árát. PackId, if substring contains "sltc-x" and get sltp-x value as price

                echo "</td>";

                //Mennyiség beállítása:
                echo "<td style=\"white-space: nowrap;float:right\">";
                if (!empty($rest["measure"])) {
                    echo "<input type=\"textbox\" style=\"width:20px\" name=\"sltu-{$rest["id"]}\">&nbsp;{$rest["measure"]}&nbsp;&nbsp;";
                }

                //Idő Beállítása:
                if (!empty($rest["time"])) {
                    $hours = $minutes = "";
                    for ($i = 0; $i <= 24; $i++) {
                        if ($i < 10) {
                            $value = "0" . $i;
                        } else {
                            $value = $i;
                        }
                        $hours .= "<option value=\"{$value}\">{$value}</option>";
                    }
                    for ($i = 0; $i <= 59; $i++) {
                        if ($i < 10) {
                            $value = "0" . $i;
                        } else {
                            $value = $i;
                        }
                        $minutes .= "<option value=\"$value\">{$value}</option>";
                    }
                    echo "<select style=\"text-align:center\" name=\"slth-{$rest["id"]}\">{$hours}</select>&nbsp;<span style=\"font-weight:bold;font-size:16px\">:</span>&nbsp;<select style=\"text-align:center\" name=\"sltm-{$rest["id"]}\">{$minutes}</select>&nbsp;&nbsp;";
                }

                echo "<input type=\"textbox\" style=\"width:60px\" onFocusOut=\"setSynlabStatus()\" name=\"sltp-{$rest["id"]}\" value=\"{$rest["price"]}\"></td>";
                echo "<td style=\"padding-right:20px\"><input onChange=\"setSynlabStatus()\" type=\"checkbox\" " . (isset($data["sltc-{$rest["id"]}"]) || (!empty($packArray) && in_array($rest["id"], $packArray)) ? "checked" : "") . "  name=\"sltc-{$rest["id"]}\" value=\"{$rest["id"]}\"></td></tr>";
            }

            echo "</table></div>";
        }
    }

    //Páciens adatokat le kezeli:
    public function setPatientData($arr = array(), $data = null)
    {


        if (!isset($arr["nev"])) $arr["nev"] = "";
        if (!isset($arr["szulhely"])) $arr["szulhely"] = "";
        if (!isset($arr["szulnev"])) $arr["szulnev"] = "";
        if (!isset($arr["taj"])) $arr["taj"] = "";
        if (!isset($arr["szuldatum"])) $arr["szuldatum"] = "";
        if (!isset($arr["telefon"])) $arr["telefon"] = "";
        if (!isset($arr["varos"])) $arr["varos"] = "";
        if (!isset($arr["cim"])) $arr["cim"] = "";
        if (!isset($arr["email"])) $arr["email"] = "";
        if (!isset($arr["bno"])) $arr["bno"] = "";
        if (!isset($arr["terhessegihet"])) $arr["terhessegihet"] = "";
        if (!isset($arr["neme"])) $arr["neme"] = "";

        $htmlout = "";
        $htmlout .= "<table style=\"width:50%;min-height:483px;min-width:600px;background-color:gray;color:white;padding:5px;display:inline-block;float:left\">";
        $htmlout .= "<tr><td text-align=\"middle\" style=\"padding:5px;\" colspan=\"4\"><span style=\"font-size:18px;font-weight:bold;\">Páciens adatok</span>&nbsp;&nbsp;&nbsp;";

        $htmlout .= "<div style=\"display:inline;position:absolute\">";
        $htmlout .= "<input type=\"text\" value=\"\" name=\"\" id=\"searchbar\" onkeyup=\"setPatientDroplist($(this).val())\"><input type=\"hidden\" id=\"aid\">";
        $htmlout .= "&nbsp;&nbsp;<select id=\"data-source\"><option value=\"foglalasok\">Előjegyzés</option><option value=\"felhasznalok\">Profil</option></select>";
        $htmlout .= '&nbsp;&nbsp;<input type="button" onClick=\'setLaborPatientData($("#aid").val(),$("#data-source").val())\' value="Kiválasztás">';
        $htmlout .= "<select style=\"position:absolute;top:24px;left:0px;display:none;\" onChange=\"setAId($(this).val())\" id=\"patientlist\"></option></select>";
        $htmlout .= "</div>";

        $htmlout .= "</td></tr>";

        $htmlout .= "<tbody id=\"patientData\"><tr><td>Név:</td><td><input type=\"textbox\" name=\"nev\" value=\"{$arr["nev"]}\"></td>";
        $htmlout .= "<td>Szül. hely:</td><td><input type=\"textbox\" name=\"szulhely\" value=\"{$arr["szulhely"]}\"></td></tr>";
        $htmlout .= "<tr><td>Születési neve:</td><td><input type=\"textbox\" name=\"szulnev\" value=\"{$arr["szulnev"]}\"></td>";
        $htmlout .= "<td>TAJ:</td><td><input type=\"textbox\" name=\"taj\" value=\"{$arr["taj"]}\"></td></tr>";
        $htmlout .= "<tr><td>Szül. dátum:</td><td><input type=\"textbox\" name=\"szuldatum\" value=\"{$arr["szuldatum"]}\"></td>";
        $htmlout .= "<td>Telefon:</td><td><input type=\"textbox\" name=\"telefon\" value=\"{$arr["telefon"]}\"></td></tr>";
        $htmlout .= "<tr><td>Lakcím (helyiség):</td><td><input type=\"textbox\" name=\"varos\" value=\"{$arr["varos"]}\"></td>";
        $htmlout .= "<td>Lakcím (utca,hsz.):</td><td><input type=\"textbox\" name=\"cim\" value=\"{$arr["cim"]}\"></td></tr>";
        $htmlout .= "<tr><td>Leletküldés e-mail:</td><td><input type=\"textbox\" name=\"email\" value=\"{$arr["email"]}\"></td>";
        $htmlout .= "<td>Neme:</td><td>Férfi<input type=\"radio\" name=\"neme\" " . ($arr["neme"] == "ferfi" ? "checked" : "") . " value=\"ferfi\">&nbsp;Nő<input type=\"radio\" name=\"neme\" " . ($arr["neme"] == "no" ? "checked" : "") . " value=\"no\"></td></tr>";
        $htmlout .= "<tr><td>Iránydiag./ BNO:</td><td><input type=\"textbox\" name=\"bno\" value=\"{$arr["bno"]}\"></td>";
        $htmlout .= "<td>Terhességi hét:</td><td><input type=\"textbox\" name=\"terhessegihet\" value=\"{$arr["terhessegihet"]}\"></td></tr></tbody>";
        $htmlout .= "</table>";

        return $htmlout;
    }

    public function showAppFormStatus($packId = null, $data = null)
    {

        $htmlout = $methodOption = $templateOption = "";
        $unit = $price = 0;
        $template = array();

        //Mintavétel:
        $methods = array("", "Synlabnál", "Hozzott minta", "Synlab + hozott", "Kiszállásos mv", "Laborban tároltból", "Beküldőnél");

        //Kiválasztott sablon adatok betöltése:
        if (!empty($data)) {
            $template = sql_fetch_array(sql_query("SELECT * FROM synlab_labor_info_sablonok WHERE id=?", array($data["synlabInfo"])));
        }

        $repayment = "<option value=\"ures\"></option>";
        $repayment .= "<option " . (isset($template["terites"]) && $template["terites"] == "szerzodeses" ? "selected" : "") . " value=\"szerzodeses\">Szerződéses</option>";
        $repayment .= "<option " . (isset($template["terites"]) && $template["terites"] == "helybenfizeto" ? "selected" : "") . " value=\"helybenfizeto\">Helyben fizető</option>";
        $repayment .= "<option " . (isset($template["terites"]) && $template["terites"] == "csekkelelore" ? "selected" : "") . " value=\"csekkelelore\">Csekkel előre</option>";
        $repayment .= "<option " . (isset($template["terites"]) && $template["terites"] == "atutalaselore" ? "selected" : "") . " value=\"atutalaselore\">Átutalás előre</option>";


        foreach ($methods as $method) {
            $methodOption .= "<option>{$method}</option>";
        }

        //Csomag:
        if (!empty($packId)) {
            $pack = sql_fetch_array(sql_query("SELECT * FROM synlab_labor_csomagok WHERE id=?", array($packId)));

            $unit = count(json_decode($pack["items"], true));
            $price = $pack["price"];
        }

        //Sablonok:
        $rs = sql_query("SELECT * FROM synlab_labor_info_sablonok ORDER BY name ASC");
        while ($res = sql_fetch_array($rs)) {
            $templateOption .= "<option " . (isset($data["synlabInfo"]) && $data["synlabInfo"] == $res["id"] ? "selected" : "") . " value=\"{$res["id"]}\">{$res["name"]}</option>";
        }




        $htmlout .= "<table style=\"width:50%;min-height:264px;min-width:600px;background-color:red;color:white;padding:5px;display:inline-block;float:left\">";
        $htmlout .= "<tr><td colspan=\"4\"><span style=\"font-size:18px;font-weight:bold;\">Synlab laborkérő információk</span>";
        $htmlout .= "&nbsp;&nbsp;<select style=\"max-width:250px;width:250px\" name=\"synlabInfo\">{$templateOption}</select>&nbsp;&nbsp;<input type=\"submit\" name=\"setSynlabInfoTemplate\" value=\"Kiválasztás\">";
        $htmlout .= "<br><br>Sablon név:&nbsp;<input type=\"textbox\" name=\"template_name\">&nbsp;<input type=\"submit\" style=\"background-color:#33b53d\" name=\"createTemplate\" value=\"+ Mentés\"><br><br>";
        $htmlout .= "</td></tr>";
        $htmlout .= "<tr><td>Synlab telephely:</td><td><input type=\"textbox\" name=\"synlabtelephely\" value=\"" . (isset($template["synlabtelephely"]) ? $template["synlabtelephely"] : "") . "\"></td>";
        $htmlout .= "<td>9 jegyű kód:</td><td><input type=\"textbox\" name=\"bekuldokod\" value=\"" . (isset($template["bekuldokod"]) ? $template["bekuldokod"] : "") . "\"></td></tr>";
        $htmlout .= "<tr><td>Beküldő neve:</td><td><input type=\"textbox\" name=\"bekuldonev\" value=\"" . (isset($template["bekuldonev"]) ? $template["bekuldonev"] : "") . "\"></td>";
        $htmlout .= "<td>Beküldő címe:</td><td><input type=\"textbox\" name=\"bekuldocim\" value=\"" . (isset($template["bekuldocim"]) ? $template["bekuldocim"] : "") . "\"></td></tr>";
        $htmlout .= "<tr><td>Orvos neve:</td><td><input type=\"textbox\" name=\"orvosnev\" value=\"" . (isset($template["orvosnev"]) ? $template["orvosnev"] : "") . "\"></td>";
        $htmlout .= "<td>Orvos pecsét száma:</td><td><input type=\"textbox\" name=\"pecsetszam\" value=\"" . (isset($template["pecsetszam"]) ? $template["pecsetszam"] : "") . "\"></td></tr>";
        $htmlout .= "<tr><td>Leletküldés e-mail:</td><td><input type=\"textbox\" name=\"kuldesiemail\" value=\"" . (isset($template["kuldesiemail"]) ? $template["kuldesiemail"] : "") . "\"></td><td></td></tr>";
        $htmlout .= "<tr><td>Térítés módja:	</td><td><select name=\"terites\">{$repayment}</select></td>";
        $htmlout .= "<td>Bef. azon.:</td><td><input type=\"textbox\" name=\"befazon\" value=\"" . (isset($template["befazon"]) ? $template["befazon"] : "") . "\"></td></tr>";
        $htmlout .= "<tr><td>Számlázási név:</td><td><input type=\"textbox\" name=\"szamlazasinev\" value=\"" . (isset($template["szamlazasinev"]) ? $template["szamlazasinev"] : "") . "\"></td>";
        $htmlout .= "<td>Számlázási cím:</td><td><input type=\"textbox\" name=\"szamlazasicim\" value=\"" . (isset($template["szamlazasicim"]) ? $template["szamlazasicim"] : "") . "\"></td></tr>";
        $htmlout .= "<tr><td>Mintavét dátuma:</td><td><input type=\"textbox\" name=\"mintavetdatum\" value=\"" . (isset($template["mintavetdatum"]) ? $template["mintavetdatum"] : "") . "\"></td>";
        $htmlout .= "<td>Kitöltés dátuma:</td><td><input type=\"textbox\" name=\"kitoltesdatum\" value=\"" . (isset($template["kitoltesdatum"]) ? $template["kitoltesdatum"] : "") . "\"></td></tr>";
        $htmlout .= "<tr><td style=\"border-bottom:2px solid white;padding:5px;\" colspan=\"4\"></tr>";
        $htmlout .= "<tr><td style=\"padding:5px;\" colspan=\"4\"></tr>";

        $htmlout .= "<tr><td>Mintavétel</td><td><select name=\"mintaveteltipus\">{$methodOption}</select></td>";
        $htmlout .= "<td>Bejelölt:</td><td id=\"item_numb\" style=\"font-size:16px;font-weight:bold;\">{$unit} db</td></tr>";
        $htmlout .= "<tr><td></td><td></td><td id=\"required_tubes\" colspan=\"2\" style=\"border-left:2px solid white;padding:5px;\">";
        $htmlout .= "<table id=\"required_tubes\" style=\"min-height:94px\">";

        /*9 db van.... 2 sorban kéne így redukálom min 5 sorra*/
        if (!empty($packId)) {
            $htmlout .= $this->setTubeList($pack, "packegeSetup");
        }


        $htmlout .= "</table>";
        $htmlout .= "</td></tr>";
        $htmlout .= "<tr><td style=\"padding:10px;\" colspan=\"4\"></tr>";
        $htmlout .= "<tr><td style=\"font-size:18px;font-weight:bold\">Végösszeg:</td><td id=\"grand_total\" style=\"font-size:18px;font-weight:bold;\">" . number_format($price, 2) . ".-</td></tr>";
        $htmlout .= "</table>";
        $htmlout .= "<input type=\"hidden\" name=\"grand_total_int\" id=\"grand_total_int\" value=\"{$price}\">";

        return $htmlout;
    }

    public function setApplicationFormPackages($AppId, $PackId = null)
    {
        $option = $htmlout = "";
        $request = sql_query("SELECT * FROM synlab_labor_csomagok WHERE appform=?", array($AppId));
        if (sql_num_rows($request) > 0) {
            $option = "<option value=\"0\">Válassz egy csomagot!</option>";
            while ($result = sql_fetch_array($request)) {
                if (isset($PackId) && $PackId == $result["id"]) {
                    $selected = "selected";
                    $selectedPrice = $result["price"];
                } else {
                    $selected = "";
                }
                $option .= "<option {$selected} value=\"{$result["id"]}\">{$result["name"]}</option>";
            }
        } else {
            $option = "<option value=\"0\"> - Üres - </option>";
        }

        $htmlout .= "<select name=\"PackId\">{$option}</select>";
        $htmlout .= "&nbsp;&nbsp;<input type=\"submit\" name=\"setPack\" value=\"Kiválasztás\">";
        if (!empty($PackId)) {

            //if (!confirm("Biztos másolod ezt a beosztást a kijelölt cégekhez?")) {return false;} $("#orvosmentesandcopy").val(1);document.iform.submit();
            $htmlout .= "&nbsp;&nbsp;Ár:&nbsp;&nbsp;<input type=\"textbox\" style=\"width:60px\" name=\"packPrice\" value=\"{$selectedPrice}\">";
            $htmlout .= "&nbsp;&nbsp;<input type=\"submit\" style=\"background-color:red\" name=\"deleteThisPack\" value=\"- Csomag törlése\" onClick='if (!confirm(\"Biztos törlöd a kijelölt csomagot?\")) {return false;}'>";
            $htmlout .= "&nbsp;&nbsp;<input type=\"submit\"style=\"background-color:#088DA5\" name=\"saveThisPack\" value=\"~ Módosítás mentése\" onClick='if (!confirm(\"Biztos menteni akarod a kijelölt csomagot?\")) {return false;}'>";
        }

        return $htmlout;
    }

    public function setTubeList($data, $method)
    {
        //Itt megkell vizsgálnom  kiválasztott tételeket, és meghatároznom milyen csövekre lesz szükség a vérvétel elvégzéséhez.
        $htmlout = "";
        $counter = 1;
        $required_tubes = array();
        $tubes = array(
            "T" => "<td>T (tiszta):</td>", "Y" => "<td>Y (nyál):</td>", "N" => "<td>N (natív):</td>", "L" => "<td>L (Lila, EDTA):</td>", "Z" => "<td>Z (zöld, Li-heparinos):</td>",
            "K" => "<td>K (kék):</td>", "F" => "<td>F (szürke):</td>", "V" => "<td>V (vizelet):</td>", "S" => "<td>S (széklet):</td>"
        );

        $tube_conditions = array(
            "N" => array(
                array("return @value>=15;" => "return 1+floor((@value/15));")
            )
        );

        $sample_conditions = array();

        //Ha a csomagot kell felépíteni más a küldött adat.
        if ($method == "packegeSetup") {
            $items = json_decode($data["items"], true);
        }
        if ($method == "ajax") {
            foreach ($data as $index => $value) {
                $items[] = $index;
            }
        }

        foreach ($items as $item) {
            $item = sql_fetch_array(sql_query("SELECT * FROM synlab_labor_tetelek WHERE id=?", array($item)));

            //Ha még nincsen minta számolva:
            if (!isset($required_tubes[$item["sample_tube"]])) {
                $required_tubes[$item["sample_tube"]]["sample_unit"] = 1;
                if (!empty($item["required_extra_tube"])) {
                    $required_tubes[$item["sample_tube"]]["extra_tube"] = $item["required_extra_tube"];
                }
                //Ha már van minta felvéve a tömbbe:
            } else {
                $required_tubes[$item["sample_tube"]]["sample_unit"] = ($required_tubes[$item["sample_tube"]]["sample_unit"] + 1);
                if (!empty($item["required_extra_tube"])) {
                    if (!isset($required_tubes[$item["sample_tube"]]["extra_tube"])) {
                        $required_tubes[$item["sample_tube"]]["extra_tube"] = $item["required_extra_tube"];
                    } else {
                        $required_tubes[$item["sample_tube"]]["extra_tube"] = ($required_tubes[$item["sample_tube"]]["extra_tube"] + $item["required_extra_tube"]);
                    }
                }
            }
        }

        foreach ($required_tubes as $index => $contains) {

            if (empty($htmlout) || $counter % 2 != 0) {
                $htmlout .= "<tr>";
            }

            $unit = 1;

            if (isset($tube_conditions[$index])) {
                foreach ($tube_conditions[$index] as $conditions) {
                    foreach ($conditions as $condition => $then) {
                        $condition = str_replace("@value", $required_tubes[$index]["sample_unit"], $condition);
                        if (eval($condition)) {
                            $then = str_replace("@value", $required_tubes[$index]["sample_unit"], $then);
                            $unit = eval($then);
                        }
                    }
                }
            }

            //Ha van extra cső igény bizonyos tételek miatt:
            if (!empty($required_tubes[$index]["extra_tube"]) && $required_tubes[$index]["sample_unit"] > 1) {
                $unit = ($unit + $required_tubes[$index]["extra_tube"]);
            }

            $htmlout .= $tubes[$index] . "<td style=\"min-width:40px\">" . floor($unit) . " db<input type=\"hidden\" name=\"{$index}-tube\" value=\"{$unit}\"></td>";
            if ($counter % 2 == 0 || ($counter - 1) == count($required_tubes)) {
                $htmlout .= "</tr>";
            }

            $counter++;
        }

        return $htmlout;
    }

    public function create_labshop_laborkero($id)
    {
        $labshopCart = sql_fetch_array(sql_query("SELECT * FROM labshop_vasarlasok WHERE id=?", array($id)));
        $packageIds = json_decode($labshopCart["package_ids"]);
        $customIds = json_decode($labshopCart["custom_ids"]);
        $ReservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?", array($labshopCart["reservationid"])));
        $filename = "Laborkérő(" . rand(200, 1200000) . ") - Egyéni.pdf";
        $custom = false;
        if (!empty($labshopCart["custom_ids"]) && $labshopCart["custom_ids"] != "[]") {
            $custom = true;
        }

        $input = [
            //Páciens adatok:
            "nev" => $ReservationData["nev"],
            "szulnev" => (isset($ReservationData["szulnev"])) ? $ReservationData["szulnev"] : "",
            "taj" => $ReservationData["taj"],
            "szuldatum" => (isset($ReservationData["szuldatum"])) ? str_replace("-", ".", $ReservationData["szuldatum"])  : "",
            "varos" => (isset($ReservationData["varos"])) ? $ReservationData["varos"] : "",
            "cim" => (isset($ReservationData["cim"])) ? $ReservationData["cim"] : "",
            "bno" => "",
            "terhessegihet" => "",
            "telefon" => $ReservationData["telefon"],
            "ferfi" => ($ReservationData["neme"] == 1) ? "Yes" : "",
            "no" => ($ReservationData["neme"] == 2) ? "Yes" : "",

            //Beküldő adatok:
            "bekuldonev" => "Hungáriamed-M Kft",
            "bekuldocim" => "1135 Budapest, Jász u. 33-35",
            "bekuldokod" => "000 000 787",
            "orvosnev" => "",
            "pecsetszam" => "",
            "atutalaselore" => "Yes",
            "befazon" => "",
            "szamlazasinev" => "Hungáriamed-M Kft.",
            "szamlazasicim" => "1132 Budapest, Csanády u. 6/b",
            "kuldesiemail" => "synlab@hungariamed.hu",
            "kitoltesdatum" => date("Y.m.d"),
            "mintavetdatum" => ""
        ];

        //Csomag tételek bepipálása
        foreach ($packageIds as $tid) {
            $input["sltc-{$tid}"] = "Yes";
        }

        //Egyéni tételek bepipálása
        foreach ($customIds as $tid) {
            $input["sltc-{$tid}"] = "Yes";
        }

        //Ha csomag lett választva csak vagy vegyesen csomag és egyéni tétel akkor generáljon más fájl nevet
        if (isset($labshopCart["package_id"]) && $labshopCart["package_id"] != "") {

            $package = sql_fetch_array(sql_query("SELECT * FROM synlab_labor_csomagok WHERE id=?", array($labshopCart["package_id"])));

            $filename = "Laborkérő(" . rand(200, 1200000) . ") - {$package["name"]} csomag.pdf";
            if ($custom == true) {
                $filename = "Laborkérő(" . rand(200, 1200000) . ") - {$package["name"]} csomag + Egyéni.pdf";
            }
        }

        //Létrehozom a laborkérő pdf-et
        $pdf = new Pdf("/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/klinikai_kemia.pdf");
        $result = $pdf->fillForm($input)
            ->flatten()
            ->saveAs("/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/" . $filename);

        if ($result === false) {
            $error = $pdf->getError();

            var_dump($error);
        }
        return "/var/www/onlinebejelentkezes_keltexmed/public/admin/templates/" . $filename;
    }

    public function createPDF($data)
    {

        $custom = false;
        $filename = "Laborkérő(" . rand(200, 1200000) . ") - Egyéni.pdf";

        //Meg kell határoznom az input értékeket:
        $input = [
            //Páciens adatok:
            "nev" => $_POST["nev"],
            "szulnev" => $_POST["szulnev"],
            "taj" => $_POST["taj"],
            "szuldatum" => $_POST["szuldatum"],
            "varos" => $_POST["varos"],
            "cim" => $_POST["cim"],
            "bno" => $_POST["bno"],
            "terhessegihet" => $_POST["terhessegihet"],
            "telefon" => $_POST["telefon"],

            //Beküldő adatok:
            "bekuldonev" => $_POST["bekuldonev"],
            "bekuldocim" => $_POST["bekuldocim"],
            "bekuldokod" => $_POST["bekuldokod"],
            "orvosnev" => $_POST["orvosnev"],
            "pecsetszam" => $_POST["pecsetszam"],
            $_POST["terites"] => "Yes",
            "befazon" => $_POST["befazon"],
            "szamlazasinev" => $_POST["szamlazasinev"],
            "szamlazasicim" => $_POST["szamlazasicim"],
            "kuldesiemail" => $_POST["kuldesiemail"],
            "kitoltesdatum" => $_POST["kitoltesdatum"],
            "mintavetdatum" => $_POST["mintavetdatum"]
        ];

        if (isset($_POST["neme"])) $input[$_POST["neme"]] = "Yes";

        //Laborkérő fájl név meg határozása:
        if (isset($_POST["PackId"]) && !in_array($_POST["PackId"],array("",0))) {
            $pack = sql_fetch_array(sql_query("SELECT * FROM synlab_labor_csomagok WHERE id=?", array($_POST["PackId"])));
            $items = json_decode($pack["items"], true);
        }

        //Labor tételek beállítása:
        foreach ($data as $index => $value) {
            //Checkboxok beállítása:
            if (strpos($index, "sltc") !== false) {
                $input[$index] = "Yes";

                if (isset($_POST["PackId"]) &&!in_array($_POST["PackId"],array("",0))) {
                    //Ellenőrzés, van-e csomagon felüli tétel:
                    if (!in_array($value, $items)) {
                        $custom = true;
                    }
                }
            }

            //Mennyiségek beállítása:
            if (strpos($index, "sltu") !== false) {
                $input[$index] = $_POST[$index];
            }
            //Legördülő mezők beállítása:
            if (strpos($index, "slts") !== false) {
                $input[$index] = $_POST[$index];
            }

            //Kategórikus mennyiségek beállítása:
            if (strpos($index, "sltkm") !== false) {
                $input[$index] = $_POST[$index];
            }

            //Kategórikus idő beállítása:
            if (strpos($index, "sltkt") !== false) {
                $input[$index] = $_POST[$index];
            }

            //Idő beállítása:
            if (strpos($index, "slth") !== false) {
                $id = explode("-", $index);
                if ($_POST["slth-" . $id[1]] != "00" && $_POST["sltm-" . $id[1]] != "00") {
                    $input["sltmh-" . $id[1]] = "(" . $_POST["slth-" . $id[1]] . ":" . $_POST["sltm-" . $id[1]] . ")";
                }
            }

            //Cső igény beállítása:
            if (strpos($index, "tube") !== false) {
                $input[$index] = $_POST[$index];
            }
        }

        if (isset($_POST["PackId"]) && !in_array($_POST["PackId"],array("",0))) {
            $filename = "Laborkérő(" . rand(200, 1200000) . ") - {$pack["name"]} csomag.pdf";
            if ($custom == true) {
                $filename = "Laborkérő(" . rand(200, 1200000) . ") - {$pack["name"]} csomag + Egyéni.pdf";
            }
        }
       //Fájlok kiolvasása adatbázisból
       $file = sql_fetch_array(sql_query("SELECT * FROM synlab_labor_kerolapok WHERE id=?",array($_POST["AppId"])));

        $pdf = new Pdf($file["path"] . $file["filename"]);
        $result = $pdf->fillForm($input)
            ->flatten()
            ->saveAs("../../public/admin/templates/" . $filename);

        if ($result === false) {
            $error = $pdf->getError();

            var_dump($error);
        }

        return $filename;
    }



    public function downloadSynlabEmails() {
        $emailConfigs["hungariamed"] = [
            ["email" => "tigazszures@hungariamed.hu", "password" => "Ree8ceix", "emailToCheck" => 100],
            ["email" => "synlab@hungariamed.hu", "password" => "SynLaB2223", "emailToCheck" => 100],
            ["email" => "mak@hungariamed.hu", "password" => "Kohju8cu", "emailToCheck" => 100],
            ["email" => "torvenyszek@hungariamed.hu", "password" => "xae2aiLu", "emailToCheck" => 100],
            ["email" => "hmmszures@hungariamed.hu", "password" => "4L8PtsbJJB", "emailToCheck" => 200],
            ["email" => "nmhh@hungariamed.hu", "password" => "k7ymino5TY", "emailToCheck" => 100],
            ["email" => "aldilabor@hungariamed.hu", "password" => "pVT54EuzwetvfUk4", "emailToCheck" => 100],
        ];

        $emailConfigs["keltexmed"] = [
            ["email" => "keltexmed@keltexmed.hu", "password" => "Keltex55", "emailToCheck" => 200],
        ];

        $mailServer["hungariamed"] = "{mail.hungariamed.hu/notls}";
        $mailServer["keltexmed"] = "{isp.itcoffee.hu/notls}";

        $pdfPasswords["hungariamed"] = ["AJ4/YFjY", "gk2q+JQU", "Ge-Weq5u", "dc8d+crV", "j8/EyFFp", "ZLKT=g1h", "AtNZ6=aN"];
        $pdfPasswords["keltexmed"]   = ["MMMLA+3a"];

        $validSenders = ["hungary@synlab.com", "lelet@synlabhungary.hu", "janoskorhaz@synlabhungary.hu"];
        $dir = "/var/pdfwork_".Booking_Constants::SQL_DB;

        if (!isset($emailConfigs[Booking_Constants::SQL_DB])) {
            return;
        }

        foreach ($emailConfigs[Booking_Constants::SQL_DB] as $emailConfig) {
            echo "reading account: ".$emailConfig["email"]. "\n";
            $connection = imap_open($mailServer[Booking_Constants::SQL_DB], $emailConfig["email"], $emailConfig["password"]);
            $count = imap_num_msg($connection);

            for ($i = 0; $i <= $emailConfig["emailToCheck"]; $i++) {
                $msgNum = $count - $i;
                if ($msgNum <= 0) {
                    break;
                }

                $header = imap_headerinfo($connection, $msgNum);
                //$raw_body = imap_body($connection, $msgNum);
                $subject = $header->subject;
                $from = $header->from[0]->mailbox . "@" . $header->from[0]->host;
                $structure = imap_fetchstructure($connection, $msgNum);
                $mailDate = date("Y-m-d H:i:s", strtotime($header->date));

                if (!in_array($from, $validSenders)) {
                    continue;
                }

                echo "processing mail from: {$from} {$mailDate}\n";

                $a = 0;
                foreach ($structure->parts as $part) {
                    if (!empty($part->ifparameters)) {
                        $part->ifdparameters = 1;
                        $part->dparameters = $part->parameters;
                    }
                    //print_r($part);
                    if ($part->ifdparameters) {
                        foreach ($part->dparameters as $object) {
                            echo "from:{$from} attr:".strtolower($object->attribute)."\n";
                            if (substr_count(strtolower($object->attribute), "name")) {
                                //attachment
                                $extension = "";
                                $fileName  =  $object->value;
                                $encoding  =  strtolower($part->encoding);
                                $subtype   =  strtolower($part->subtype);

                                if (sql_query("select id from labrequests where synlabfilename=? and folyamatban=0 limit 1", [$fileName])->fetch(PDO::FETCH_ASSOC)) {
                                    //continue;
                                }

                                echo "{$encoding} {$subtype} {$fileName}\n";

                                if (substr_count(strtolower($fileName), ".pdf")) {
                                    //pdf csatolmány feldolgozása
                                    $tempFile = "{$dir}/lelet.pdf";
                                    $tempFileDecoded = "{$dir}/leletdecoded.pdf";

                                    $attachment = imap_fetchbody($connection, $msgNum, $a + 1);
                                    if ($encoding == 3) {
                                        $attachment = base64_decode($attachment);
                                    }
                                    if ($encoding == 4) {
                                        $attachment = quoted_printable_decode($attachment);
                                    }

                                    file_put_contents($tempFile, $attachment);

                                    unlink($tempFileDecoded);

                                    foreach ($pdfPasswords[Booking_Constants::SQL_DB] as $pdfPassword) {
                                        $output = `qpdf --password={$pdfPassword} --decrypt {$tempFile} '{$tempFileDecoded}'`;
                                        if (is_file($tempFileDecoded)) {
                                            break;
                                        }
                                    }

                                    if (sql_query("select id from labrequests where synlabfilename=? and resultdate=? limit 1", [$fileName, $mailDate])->fetch(PDO::FETCH_ASSOC)) {
                                        continue;
                                    }

                                    $parsedPatientData = $this->parsePatientDataFromPDF($tempFileDecoded);

                                    sql_query("insert into labrequests set createdby='cron', created=now(), resultdate=?, nev=?, taj=?, szuldatum=?, email=?, status='done', provider=?, synlabfilename=?, synlabdata=?, resultpdf=?, pass=?, folyamatban=?, bekuldokod=?", [
                                        $mailDate,
                                        $parsedPatientData["nev"],
                                        $parsedPatientData["taj"],
                                        $parsedPatientData["szulDatum"],
                                        $parsedPatientData["patientEmail"],
                                        $emailConfig["email"],
                                        $fileName,
                                        "",
                                        base64_encode(file_get_contents($tempFileDecoded)),
                                        md5(date("YmdHis")) . md5($parsedPatientData["taj"] . date("Y-m-d His")),
                                        $parsedPatientData["folyamatban"],
                                        $parsedPatientData["bekuldokod"]
                                    ]);
                                }


                                if (substr_count(strtolower($fileName), ".zip")) {
                                    //zip csatolmány feldolgozása

                                    echo "processing zip\n";

                                    $tempFile = "{$dir}/lelet.zip";

                                    $attachment = imap_fetchbody($connection, $msgNum, $a + 1);
                                    if ($encoding == 3) {
                                        $attachment = base64_decode($attachment);
                                    }
                                    if ($encoding == 4) {
                                        $attachment = quoted_printable_decode($attachment);
                                    }

                                    file_put_contents($tempFile, $attachment);

                                    $unpacked = false;
                                    $unzipDir = "{$dir}/unzip/";
                                    foreach ($pdfPasswords[Booking_Constants::SQL_DB] as $pdfPassword) {
                                        $output = `unzip -o -P {$pdfPassword} {$dir}/lelet.zip -d {$unzipDir}`;
                                        if (substr_count("incorrect pass", $output) == 0) {
                                            $unpacked = true;
                                            break;
                                        }
                                    }

                                    if ($unpacked) {
                                        $d = dir($unzipDir);

                                        while (false !== ($entry = $d->read())) {
                                            if (substr_count(strtolower($entry), ".pdf")) {
                                                $leletPDF = base64_encode(file_get_contents("{$unzipDir}{$entry}"));
                                                if (sql_query("select id from labrequests where resultdate=? and resultpdf=? limit 1", [$mailDate, $leletPDF])->fetch(PDO::FETCH_ASSOC)) {
                                                    continue;
                                                }

                                                sql_query("insert into labrequests set createdby='cron', created=now(), resultdate=?, status='done', provider=?, synlabfilename=?, synlabdata=?, resultpdf=?, pass=?", [
                                                    $mailDate,
                                                    $emailConfig["email"],
                                                    $fileName,
                                                    json_encode(["nev" => $entry, "taj" => "", "szuldatum" => "", "email" => "", "errors" => "Zip fájlból kicsomagolt lelet!"]),
                                                    $leletPDF,
                                                    md5(date("YmdHis")) . md5($entry . date("Y-m-d His")),
                                                ]);

                                                echo $entry."\n";
                                                unlink("{$unzipDir}{$entry}");
                                            }
                                        }
                                    }
                                }

                            }
                        }
                    }
                    $a++;
                }
            }
        }

        echo "yeee";
        echo "\n";

    }


    private function parsePatientDataFromPDF($pdfFile):array {
        $utils = new Utils();

        $config = new \Smalot\PdfParser\Config();
        $config->setHorizontalOffset('');
        $parser = new \Smalot\PdfParser\Parser([], $config);
        $pdf = $parser->parseFile($pdfFile);
        $text = $pdf->getText();

        if (substr_count($text, "Mikrobiológiai Labor")) {
            //mikrobiológiai lelet
            $nev = trim(substr($text, strpos($text, "Név:\n") + 5, 100));
            $result["nev"] = substr($nev, 0, strpos($nev, "\n"));
            $taj = trim(substr($text, strpos($text, "Taj:\n") + 5, 50));
            $result["taj"] = trim(substr($taj, 0, strpos($taj, "\n")));
            $szulDatum = trim(substr($text, strpos($text, "Született:\n") + 11, 50));
            $result["szulDatum"] = date("Y.m.d", strtotime(substr($szulDatum, 0, strpos($szulDatum, "\n"))));
        }

        if (empty($result["nev"])) {
            $nev = substr($text, strpos($text, "Születési id") + 18, 100);
            $result["nev"] = substr($nev, 0, strpos($nev, "\t"));
            $result["taj"] = substr($text, strpos($text, "TAJ/ID:") + 8, 9);
            $result["szulDatum"] = substr($text, strpos($text, "www.synlab.hu") + 15, 10);
            $result["folyamatban"] = substr_count($text, "Folyamatban") ? 1 : 0;
        }

        $result["bekuldokod"] = "";
        foreach ($this->bekuldoKodok as $kod) {
            if (substr_count($text, "({$kod})")) {
                $result["bekuldokod"] = $kod;
                break;
            }
        }

        if (!ctype_digit($result["taj"])) {
            $result["taj"] = "";
        }
        if (!$utils->validateDate($result["szulDatum"], "Y.m.d")) {
            $result["szulDatum"] = "";
        }

        $result["patientEmail"] = "";
        if (!empty($result["taj"]) && !empty($result["szulDatum"])) {
            //email kibányászása
            if ($reservationData = sql_query("select email from foglalasok where taj=? and szuldatum=? order by datum desc limit 1", [$result["taj"], str_replace(".", "-", $result["szulDatum"])])->fetch(PDO::FETCH_ASSOC)) {
                $result["patientEmail"] = $reservationData["email"];
            }
        }

        return $result;
    }


}
