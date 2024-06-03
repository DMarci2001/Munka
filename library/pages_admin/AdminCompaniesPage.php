<?php

class AdminCompaniesPage extends AdminCorePage
{

    private $bookingService;

    private $optionalFields = [
        "taj"       => "TAJ szám",
        "szuldatum" => "Születési dátum",
        "szulhely"  => "Születési hely",
        "anyjaneve" => "Anyja neve",
        "neme"      => "Neme",
        "irsz"      => "Irányítószám",
        "varos"     => "Város",
        "utca"      => "Utca",
        "email"     => "Email",
        "telefon"   => "Telefon",
        "munkakor"  => "Munkakör",
        "torzsszam" => "Törzsszám",
        "doksi"     => "Dokumentum feltöltés"
    ];

    public function __construct()
    {
        parent::__construct();

        if (isset($_GET["delvisszaigazolo"])) {
            sql_query("delete from visszaigazolok where id='" . addslashes($_GET["delvisszaigazolo"]) . "' and cegid='" . addslashes($_GET["szerk"]) . "'");
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_POST["addvisszaigazolo"])) {
            sql_query("insert into visszaigazolok set cegid='" . addslashes($_GET["szerk"]) . "'");
            $_POST["cegmentes"] = 1;
        }

        if (isset($_GET["delcegvar"])) {
            sql_query("delete from cegvars where id='" . addslashes($_GET["delcegvar"]) . "' and cegid='" . addslashes($_GET["szerk"]) . "'");
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_POST["addcegvar"])) {
            sql_query("insert into cegvars set cegid='" . intval($_GET["szerk"]) . "'");
            $_POST["cegmentes"] = 1;
        }

        if (isset($_GET["delcegbeosztas"])) {
            sql_query("delete from cegbeosztasok where id='" . intval($_GET["delcegbeosztas"]) . "' and cegid='" . intval($_GET["szerk"]) . "'");
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
        if (isset($_POST["addcegbeosztas"])) {
            sql_query("insert into cegbeosztasok set cegid='" . intval($_GET["szerk"]) . "'");
            $_POST["cegmentes"] = 1;
        }

        if (isset($_POST["cegmentes"])) {
            $id = intval($_GET["szerk"]);
            if ($this->adminUser->cegModAccess()) {
                $sor = 1;
                while (isset($_POST["visszid{$sor}"])) {
                    sql_query("update visszaigazolok set 
                    helyszinid='" . addslashes($_POST["helyszinid{$sor}"]) . "',
                    orvosid='" . addslashes($_POST["orvosid{$sor}"]) . "',
                    mapurl='" . addslashes(trim($_POST["mapurl{$sor}"])) . "',
                    szoveg='" . addslashes($_POST["szoveg{$sor}"]) . "'
                    where id='" . addslashes($_POST["visszid{$sor}"]) . "'");
                    $sor++;
                }

                $sor = 1;
                while (isset($_POST["cegvarid{$sor}"])) {
                    sql_query("update cegvars set 
                    varos='" . addslashes($_POST["cegvarvaros{$sor}"]) . "',
                    megnev='" . addslashes($_POST["cegvarmegnev{$sor}"]) . "'
                    where id='" . addslashes($_POST["cegvarid{$sor}"]) . "'");
                    $sor++;
                }

                $sor = 1;
                while (isset($_POST["cegbeosztasid{$sor}"])) {
                    sql_query("update cegbeosztasok set 
                    megnev='" . addslashes($_POST["cegbeosztasmegnev{$sor}"]) . "'
                    where id='" . addslashes($_POST["cegbeosztasid{$sor}"]) . "'");
                    $sor++;
                }

                $fieldOptions = [];
                foreach ($this->optionalFields as $field => $name) {
                    if (isset($_POST["fieldoption_{$field}"])) {
                        $option = $_POST["fieldoption_{$field}"];
                        if ($option == 0) {
                            $fieldOptions[] = "notreq_{$field}";
                        }
                        if ($option == 2) {
                            $fieldOptions[] = "hidden_{$field}";
                        }
                    }
                }

                if (!isset($_POST["aktiv"])) $_POST["aktiv"] = 0;
                if (!isset($_POST["foglalasemail"])) $_POST["foglalasemail"] = 0;
                if (!isset($_POST["onlyreg"])) $_POST["onlyreg"] = 0;
                if (!isset($_POST["onlybeutalo"])) $_POST["onlybeutalo"] = 0;
                if (!isset($_POST["tudoszuroopcio"])) $_POST["tudoszuroopcio"] = 0;
                if (!isset($_POST["laboropcio"])) $_POST["laboropcio"] = 0;
                if (!isset($_POST["laboropciotol"])) $_POST["laboropciotol"] = "";
                if (!isset($_POST["laboropcioig"])) $_POST["laboropcioig"] = "";
                if (!isset($_POST["nocim"])) $_POST["nocim"] = 0;
                if (!isset($_POST["noregsms"])) $_POST["noregsms"] = 0;
                if (!isset($_POST["alksend"])) $_POST["alksend"] = 0;
                if (!isset($_POST["alkertsend"])) $_POST["alkertsend"] = 0;
                if (!isset($_POST["no_doctor_select"])) $_POST["no_doctor_select"] = 0;
                if (!isset($_POST["dokirexTelephelyId"])) $_POST["dokirexTelephelyId"] = "";

                sql_query(
                    "update cegek set megnev=?,domain=?,email=?,foglalasemail=?,onlyreg=?,nocim=?,visszaigazolas=?,onlybeutalo=?,tudoszuroopcio=?,smshour=?,beutaloszoveg=?,beutaloszoveg_de=?,beutaloszoveg_en=?,protokoll=?,aktiv=?,noregsms=?,alksend=?,alkertsend=?,alksendint=?,sendmail=?,nofoglalas_hu=?,nofoglalas_en=?,nofoglalas_de=?,fieldoptions=?,no_doctor_select=?,dokirexTelephelyId=?,laboropcio=?,laboropciotol=?,laboropcioig=? where id=?",
                    array($_POST["megnev"], $_POST["domain"], $_POST["email"], $_POST["foglalasemail"], $_POST["onlyreg"], $_POST["nocim"], $_POST["visszaigazolas"], $_POST["onlybeutalo"], $_POST["tudoszuroopcio"], $_POST["smshour"], $_POST["beutaloszoveg"], $_POST["beutaloszoveg_de"], $_POST["beutaloszoveg_en"], $_POST["protokoll"], $_POST["aktiv"], $_POST["noregsms"], $_POST["alksend"], $_POST["alkertsend"], $_POST["alksendint"], $_POST["sendmail"], $_POST["nofoglalas_hu"], $_POST["nofoglalas_en"], $_POST["nofoglalas_de"], implode(",", $fieldOptions), $_POST["no_doctor_select"], $_POST["dokirexTelephelyId"], $_POST["laboropcio"], $_POST["laboropciotol"], $_POST["laboropcioig"], $id)
                );

                logActivity("ceg", $id, $_POST["megnev"] . " adatlap", print_r($_POST, true));
            }
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$id}");
            die();
        }

        if (isset($_POST['readExcel']) && $_POST['readExcel'] == true) {

            //Variables:
            $listExist = false;
            $error = array();
            $nevError = 0;
            $tajError = 0;
            $szuldatumError = 0;
            $organizationalUnits = array();
            $newUnits = array();
            $ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($_POST["cegid"])));
            $columns = array(
                "nev" => $_POST["nev"],
                "taj" => $_POST["taj"],
                "szuldatum" => $_POST["szuldatum"],
                "szulhely" => $_POST["szulhely"],
                "anyjaneve" => $_POST["anyjaneve"],
                "lakcim" => $_POST["lakcim"],
                "email" => $_POST["email"],
                "tel1" => $_POST["tel1"],
                "tel2" => $_POST["tel2"],
                "szerv" => $_POST["szerv"]
            );
            if (isset($_SESSION["excelData"])) unset($_SESSION["excelData"]);

            $excelReader = PHPExcel_IOFactory::createReaderForFile($_FILES['file-0']['tmp_name']);
            $excelObj = $excelReader->load($_FILES['file-0']['tmp_name']);
            $worksheet = $excelObj->getSheet(0);
            $lastRow = $worksheet->getHighestRow();
            $excelData = array();

            //There is existing staff list data?
            if (sql_num_rows(sql_query("SELECT * FROM allomanyi_listak WHERE cegid=?", array($_POST["cegid"]))) > 0) {
                $listExist = true;
            }

            //RAW adatok:
            for ($row = 2; $row <= $lastRow; $row++) {
                $i = ($row - 1);
                $excelData[$i] = array(
                    "nev" => $worksheet->getCell($_POST["nev"] . $row)->getValue(),
                    "taj" => $worksheet->getCell($_POST["taj"] . $row)->getValue(),
                    "szulhely" => $worksheet->getCell($columns["szulhely"] . $row)->getValue(),
                    "szuldatum" => PHPExcel_Style_NumberFormat::toFormattedString($worksheet->getCell($columns["szuldatum"] . $row)->getValue(), 'YYYY-MM-DD'),
                    "anyjaneve" => $worksheet->getCell($columns["anyjaneve"] . $row)->getValue(),
                    "szervezeti_egyseg" => $worksheet->getCell($columns["szerv"] . $row)->getValue(),
                    "szid" => null,
                    "lakcim" => $worksheet->getCell($columns["lakcim"] . $row)->getValue(),
                    "email" => $worksheet->getCell($columns["email"] . $row)->getValue(),
                    "telefon" => $worksheet->getCell($columns["tel1"] . $row)->getValue(),
                    "hivataliszam" => $worksheet->getCell($columns["tel2"] . $row)->getValue(),
                    "error" => array()
                );
                //Megvannak az adatok, mostmár meg kell határoznom, hogy mit kezdek vele...
                //meg kell csekkoljam, van-e már bent adat...

                //Szervezeti egységeket kigyűjtöm...
                $key = array_search($excelData[$i]["szervezeti_egyseg"], array_column($organizationalUnits, "megnev"));
                if ($key === false) {
                    $organizationalUnits[]["megnev"] = $excelData[$i]["szervezeti_egyseg"];
                }
            }

            //Szervezeti egységek Ellenőrzése:
            foreach ($organizationalUnits as $unit) {
                //Keresési feltétel:
                $unitResult = sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE megnev=? AND cegid=? OR parentid=? LIMIT 1", array($unit["megnev"], $ceginfo["id"], $ceginfo["parentid"])));

                //Ha nincsen találat akkor vegye fel az új szervezeti egységek közé:
                if (!$unitResult) {
                    $newUnits[] = str_replace(" ", "_", $unit["megnev"]);
                }
            }

            //Új szervezeti egységek megjelenítése rögzítés/áttekintés céljából
            if (!empty($newUnits)) {
                echo "<form method='POST'>";
                for ($x = 0; $x < count($newUnits); $x++) {
                    echo "<input type='hidden' name='new_units[]' id='new_units[]' value={$newUnits[$x]}>";
                }
                echo "Új szerv. egységek:<button type='button' onClick='$(\"#new_units\").slideToggle();'>Megjelenítés (" . count($newUnits) . "db)</button>&nbsp;";
                echo "<button type='button' id='Insert-New-O-Units-Button' style='background-color:red;color:white;border:none' onClick='Insert_New_Organizational_Units({$ceginfo["id"]})'>Rögzítés</button><br>";
                echo "<div id='new_units' style='display:none'>";
                echo "<pre>";
                print_r($newUnits);
                echo "</pre>";
                echo "</div>";
                echo "</form>";
            } else {
                echo "<p>Minden szervezeti egység rögzítve van!</p>";
            }

            //Dolgozók adatainak ellenőrzése:
            foreach ($excelData as $i => $v) {
                $szid = null;
                //Ellenőrzöm a nevét
                if (empty($excelData[$i]["nev"])) {
                    $excelData[$i]["error"][] = "Nincs név megadva!";
                    $nevError++;
                }
                if (empty($excelData[$i]["szuldatum"])) {
                    $excelData[$i]["error"][] = "Nincs születési dátum megadva!";
                    $szuldatumError++;
                }
                if (false === strtotime($excelData[$i]["szuldatum"])) {
                    $excelData[$i]["error"][] = "Hibás dátum adat!";
                    $szuldatumError++;
                }

                if (empty($excelData[$i]["taj"])) {
                    $excelData[$i]["error"][] = "Nincs TAJ szám megadva!";
                    $tajError++;
                }

                //Ha van rögzített szervezeti egység, akkor az id-ját hozzá rendeli
                if ($szid = sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE megnev=? AND (cegid=? OR parentid=?) ", array($excelData[$i]["szervezeti_egyseg"], $ceginfo["id"], $ceginfo["parentid"])))) {
                    $excelData[$i]["szid"] = $szid["id"];
                }
            }
            //Kiértékelés:
            if (!empty($nevError)) echo "<p>{$nevError}db hiányzó név!</p>";
            if (!empty($szuldatumError)) echo "<p>{$nevError}db hiányzó vagy hibás születési dátum!</p>";
            if (!empty($tajError)) echo "<p>{$nevError}db hiányzó TAJ szám!</p>";

            //Az excel adatait sessionbe rakom, hogy hozzáférhető legyen egy third submit után
            $_SESSION["excelData"] = $excelData;

            //Dolgozói Adatok megjelenítése:
            echo "<form method='POST'>";
            echo "Importált állományi lista:<button type='button' onClick='$(\"#staff-list\").slideToggle();'>Megjelenítés (" . count($excelData) . "db)</button>&nbsp;";
            echo "<button type='button' id='Insert-New-Staff-List-Button' style='background-color:red;color:white;border:none' onClick='Insert_New_Staff_List({$ceginfo["id"]})'>Rögzítés</button><br>";
            echo "<div id='staff-list' style='display:none'>";
            echo "<pre>";
            print_r($excelData);
            echo "</pre>";
            echo "</div>";
            echo "</form>";

            die();
        }
        if (isset($_POST["Insert_New_Organizational_Units"]) && $_POST["Insert_New_Organizational_Units"] == true) {

            //Variables:
            $counter = 0;

            //Ha nincs ilyen cég akkor megszakítom a rögzítést
            if (!$ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($_POST["cegid"])))) {
                die();
            }

            foreach ($_POST["units"] as $unit) {
                $query = null;
                if ($query = sql_query("INSERT INTO cegvars SET cegid=?,parentid=?,megnev=?", array($_POST["cegid"], $ceginfo["parentid"], str_replace("_", " ", $unit)))) {
                    $counter++;
                }
            }
            //die("{$counter} és ".count($_POST["units"]));
            echo "{$counter}db sikeres rögzítés!";
            die();
        }

        if (isset($_POST["Insert_New_Staff_List"]) && $_POST["Insert_New_Staff_List"] == true) {

            $ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek where id=?", array($_POST["cegid"])));

            foreach ($_SESSION["excelData"] as $subject) {
                if (empty($subject["error"])) {
                    sql_query(
                        "INSERT INTO allomanyi_listak SET cegid=?,parentid=?,datum=NOW(),nev=?,taj=?,szulhely=?,szuldatum=?,anyjaneve=?,szervezeti_egyseg=?,szid=?,lakcim=?,email=?,telefon=?,hivataliszam=?",
                        array($ceginfo["id"], $ceginfo["parentid"], $subject["nev"], $subject["taj"], $subject["szulhely"], $subject["szuldatum"], $subject["anyjaneve"], $subject["szervezeti_egyseg"], $subject["szid"], $subject["lakcim"], $subject["email"], $subject["telefon"], $subject["hivataliszam"])
                    );
                }
            }

            die("Állományi lista rögzítve lett!");
        }

        if (isset($_POST["New_Notification_Message"]) && $_POST["New_Notification_Message"]) {
            //Itt meg kell jelenítenem akkor egy textbox-ot, amibe be lehet pakolni a szükséges cuccokat... szval kell egy form, és egy submit gomb és egy megszakítóóó ofc...
            echo "Üzenet megnevezése: <input type='textbox' id='megnev' value=''>&nbsp;&nbsp;";
            echo "<input type='button' onClick='Insert_New_Notification()' value='Mentés'/>&nbsp;&nbsp;";
            echo "<input type='button' onClick='Cancel_Notification_Processing()' value='Bezárás'/><br><br>";
            echo "Tárgy: <input type='textbox' id='targy' value=''><br><br>";
            echo "<textarea style='width:800px;height:500px' id='szoveg'></textarea>";
            die();
        }

        if (isset($_POST["Insert_New_Notification"]) && $_POST["Insert_New_Notification"]) {
            //Ha semmi se üres, akkor ebbe fusson bele:
            if (!empty($_POST["szoveg"]) && !empty($_POST["targy"]) && !empty($_POST["megnev"])) {


                if ($checkSubject = sql_fetch_array(sql_query("SELECT * FROM ertesito_uzenetek WHERE targy=? and cegid=?", array($_POST["targy"], $_POST["cegid"])))) {
                    if (empty($_POST["targy"])) $response["targyError"] = true;
                }

                sql_query("INSERT INTO ertesito_uzenetek SET cegid=?,megnev=?,targy=?,szoveg=?,kelte=NOW()", array($_POST["cegid"], $_POST["megnev"], $_POST["targy"], $_POST["szoveg"]));

                $response["result"] = sql_insert_id();

                die(json_encode($response));
            }

            if (empty($_POST["megnev"]))  $response["megnevError"] = true;

            if (empty($_POST["szoveg"])) $response["szovegError"] = true;

            if (empty($_POST["targy"])) $response["targyError"] = true;

            die(json_encode($response));
        }

        if (isset($_POST["Load_Notification_Message"]) && $_POST["Load_Notification_Message"] == true) {
            if ($notification = sql_fetch_array(sql_query("SELECT * FROM ertesito_uzenetek WHERE id=? AND cegid=?", array($_POST["notificationId"], $_POST["cegid"])))) {
                $response["editor"] = "Tárgy:&nbsp;" . $notification["targy"] . "<br><br>" . $notification["szoveg"];

                $requestList = sql_query("SELECT * FROM ertesito_uzenetek WHERE cegid=? ORDER BY targy ASC", array($_POST["cegid"]));
                $response["selector"] = "";
                while ($list = sql_fetch_array($requestList)) {
                    $response["selector"] .= "<option " . ($list["id"] == $_POST["notificationId"] ? "selected='true'" : "") . " value='{$list["id"]}'>{$list["targy"]}</option>";
                }
                die(json_encode($response));
            }
            die();
        }

        if (isset($_POST["Save_Notification"]) && $_POST["Save_Notification"] == true) {
            if (!empty($_POST["notificationId"])) {
                if ($notification = sql_fetch_array(sql_query("SELECT * FROM ertesito_uzenetek WHERE id=?", array($_POST["notificationId"])))) {
                    if (!empty($_POST["szoveg"]) && !empty($_POST["targy"])) {


                        sql_query("UPDATE ertesito_uzenetek SET targy=?,szoveg=? WHERE id=?", array($_POST["targy"], $_POST["szoveg"], $_POST["notificationId"]));

                        die(json_encode(array("errorCode" => "0", "result" => $_POST["notificationId"])));
                    }
                    if (empty($_POST["szoveg"]) && !empty($_POST["targy"])) {
                        die(json_encode(array("errorCode" => "1", "result" => null)));
                    }
                    if (!empty($_POST["szoveg"]) && empty($_POST["targy"])) {
                        die(json_encode(array("errorCode" => "2", "result" => null)));
                    }
                    if (empty($_POST["szoveg"]) && empty($_POST["targy"])) {
                        die(json_encode(array("errorCode" => "3", "result" => null)));
                    }
                }
            }
        }

        if (isset($_POST["Edit_Notification_Message"]) && $_POST["Edit_Notification_Message"] == true) {
            if ($notification = sql_fetch_array(sql_query("SELECT * FROM ertesito_uzenetek WHERE id=?", array($_POST["notificationId"])))) {
                echo "Üzenet megnevezése: <input type='textbox' id='megnev' value='{$notification["megnev"]}'>&nbsp;&nbsp;";
                echo "<input type='button' onClick='Save_Notification({$_POST["notificationId"]})' value='Mentés'/>&nbsp;&nbsp;";
                echo "<input type='button' onClick='Cancel_Notification_Processing()' value='Bezárás'/><br><br>";
                echo "Tárgy: <input type='textbox' id='targy' value='{$notification["targy"]}'><br><br>";
                echo "<textarea style='width:800px;height:500px' id='szoveg'>{$notification["szoveg"]}</textarea>";
            }
            die();
        }

        if (isset($_POST["Cancel_Notification_Processing"]) && $_POST["Cancel_Notification_Processing"] == true) {
            if (!empty($_POST["notificationId"])) {
                if ($notification = sql_fetch_array(sql_query("SELECT * FROM ertesito_uzenetek WHERE id=?", array($_POST["notificationId"])))) {
                    echo "Tárgy:&nbsp;" . $notification["targy"] . "<br><br>" . $notification["szoveg"];
                }
            }
            die();
        }

        if (isset($_POST["Delete_Notification_Message"]) && $_POST["Delete_Notification_Message"] == true) {
            sql_query("DELETE FROM ertesito_uzenetek WHERE id=?", array($_POST["notificationId"]));
            die();
        }

        if (isset($_POST["create-notification-list"])) {
            if (!empty($_POST["notification-list-type"])) {
                sql_query("INSERT INTO egyeni_ertesitesi_listak SET cegid=?, tipus=?,megnev=?, datum=NOW()", array($_POST["companyid"], $_POST["notification-list-type"], "Új értesítési lista"));
            }
        }

        if (isset($_POST["Show_Organizational_List"]) && $_POST["Show_Organizational_List"] = true) {
            $output = "";
            if ($ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($_POST["cegid"])))) {
                if ($listainfo = sql_fetch_array(sql_query("SELECT * FROM egyeni_ertesitesi_listak WHERE cegid=? AND id=?", array($_POST["cegid"], $_POST["list"])))) {
                    $selectedList = json_decode($listainfo["szervek"], true);
                    $szervinfo = sql_query("SELECT * FROM cegvars WHERE cegid=? OR parentid=? ORDER BY megnev ASC", array($_POST["cegid"], $ceginfo["parentid"]));
                    while ($szerv = sql_fetch_array($szervinfo)) {
                        $output .= "<label style='white-space: nowrap;'>";
                        $output .= "<input type='checkbox' " . (!empty($selectedList) && in_array($szerv["id"], $selectedList) ? "checked=true" : "") . " value='{$szerv["id"]}' onClick='Set_Organizational_To_List({$szerv["id"]},{$_POST["cegid"]},{$_POST["list"]})'>";
                        $output .= "&nbsp;{$szerv["megnev"]}";
                        $output .= "</label>";
                    }
                }
            }
            die($output);
        }

        if (isset($_POST["Set_Organizational_To_List"]) && $_POST["Set_Organizational_To_List"] == true) {
            if ($ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($_POST["cegid"])))) {
                if ($listainfo = sql_fetch_array(sql_query("SELECT * FROM egyeni_ertesitesi_listak WHERE cegid=? AND id=?", array($_POST["cegid"], $_POST["list"])))) {
                    $selectedList = json_decode($listainfo["szervek"], true);
                    if ($szervinfo = sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE id=? AND cegid=? OR parentid=?", array($_POST["szid"], $_POST["cegid"], $ceginfo["parentid"])))) {
                        if (!empty($selectedList)) {
                            $key = array_search($_POST["szid"], $selectedList);
                        } else {
                            $key = false;
                        }

                        if ($key !== false) {
                            unset($selectedList[$key]);
                            $selectedList = array_values($selectedList);
                        } else {
                            $selectedList[] = $_POST["szid"];
                        }
                        sql_query("UPDATE egyeni_ertesitesi_listak SET szervek=? WHERE id=?", array(json_encode($selectedList, true),$_POST["list"]));
                        echo count($selectedList) . " egység";
                    }
                }
            }
            die();
        }

        if (isset($_POST["Save_Custom_Notification_List"]) && $_POST["Save_Custom_Notification_List"]) {
            if ($listainfo = sql_fetch_array(sql_query("SELECT * FROM egyeni_ertesitesi_listak WHERE id=?", array($_POST["list"])))) {
                if (!empty($_POST["megnev"]) && !empty($_POST["uid"])) {
                    if ($uzenetinfo = sql_fetch_array(sql_query("SELECT * FROM ertesito_uzenetek WHERE id=?", array($_POST["uid"])))) {
                        sql_query(
                            "UPDATE egyeni_ertesitesi_listak SET megnev=?,leiras=?,uzenetid=?,uzenet=?,targy=? WHERE id=?",
                            array($_POST["megnev"], $_POST["leiras"], $_POST["uid"],  $uzenetinfo["szoveg"], $uzenetinfo["targy"], $_POST["list"])
                        );
                        echo "success";
                    }
                }
            }
            die();
        }

        if (isset($_POST["Show_Affected_Staff"]) && $_POST["Show_Affected_Staff"] == true) {
            if ($listainfo = sql_fetch_array(sql_query("SELECT * FROM egyeni_ertesitesi_listak WHERE id=?", array($_POST["list"])))) {
                $szervinfo = json_decode($listainfo["szervek"], true);
                $ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($listainfo["cegid"])));

                $content = $html = "";
                $row = 0;
                $tdCSS = "style='padding: 8px 8px 8px 0px;border-bottom:1px solid gray'";
                $columns = array("#.", "Teljesnév", "Szül. dátum", "Munkakör", "TAJ", "E-mail","Érvényesség", "<span id='checkBoxSwitcher' onClick='switchCheckBoxes(\"referral-checker\",\"disable\")' style='color:red;cursor:pointer'>Egyikse</span>");
                $columnTitle = implode("</td><td {$tdCSS}>", $columns);

                if($listainfo["tipus"]=="by_organizational_units"){
                    $erintettek = sql_query("SELECT * FROM felhasznalok WHERE szid IN(" . implode(",", $szervinfo) . ") AND statusz=1 ORDER BY nev ASC");
                }
                if($listainfo["tipus"]=="by_fitness_expire"){
                    $erintettek = sql_query("SELECT felh.id,felh.nev,felh.szuldatum,felh.munkakor,felh.taj,felh.email,vizsgalat.vizsgalatdatuma FROM felhasznalok felh LEFT JOIN bfkh_osszesites vizsgalat on vizsgalat.taj=felh.taj WHERE felh.cegid in(131,136) AND felh.statusz=1 AND vizsgalat.ervenyesseg like '".date("Y-m")."%' ORDER BY nev ASC");
                }
                

                $selectedStaff = json_decode($listainfo["dolgozoi_lista"]);

                if (!empty($selectedStaff)) {
                    $db = count($selectedStaff);
                } else {
                    $db = sql_num_rows($erintettek);
                }


                while ($staff = sql_fetch_array($erintettek)) {

                    if (empty($selectedStaff)) {
                        $checkStatus = "checked='checked'";
                    } else {
                        if (in_array($staff["id"], $selectedStaff)) {
                            $checkStatus = "checked='checked'";
                        } else {
                            $checkStatus = "";
                        }
                    }

                    /*if(!$inActualWorkerList=sql_fetch_array(sql_query("SELECT * FROM bfkh_allomany_2022 WHERE taj=?",array($staff["taj"])))){
                        continue;
                    }*/

                    if(!$lastVisit=sql_fetch_array(sql_query("SELECT MAX(ervenyesseg) as ervenyesseg FROM alkalmassagi_meta_adatok WHERE paciensid=?",array($staff["id"])))){
                        $lastVisit["ervenyesseg"] = null;
                    }

                    $content .= "<tr>";
                    $content .= "<td {$tdCSS}>#" . ($row + 1) . "</td>";
                    $content .= "<td {$tdCSS}>{$staff['nev']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['szuldatum']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['munkakor']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['taj']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['email']}</td>";
                    $content .= "<td {$tdCSS}>{$lastVisit['ervenyesseg']}</td>";
                    $content .= "<td {$tdCSS} align='center' onClick='toggleCheckBox(\"#{$staff['id']}\")'><input type='checkbox' onClick='toggleCheckBox(\"#{$staff['id']}\")' {$checkStatus} class='referral-checker' name='selected-data[]' id='{$staff['id']}' value='{$staff['id']}'/></td>";
                    $content .= "</tr>";
                    $row++;
                }

                $html .= "<br><form method='post'><input type='hidden' name='nlid' value='{$_POST["list"]}'/>";
                if (empty($listainfo["elesitve"])) {
                    $html .= "<input type='submit' value='Értesítendő névsor rögzítése' name='set-validated-staff'/>&nbsp;&nbsp;";
                }

                $html .= "Kiválasztott dolgozók száma: {$db} db";
                $html .= "<br><table class='transactions_table' style='border-collapse:collapse'>";
                $html .= "<tr><td {$tdCSS}>" . $columnTitle . "</td></tr>";
                $html .= $content;
                $html .= "</table></form>";
                echo $html;
            }
            die();
        }

        if (isset($_POST["set-validated-staff"])) {
            if (isset($_POST["nlid"])) {
                sql_query("UPDATE egyeni_ertesitesi_listak SET  dolgozoi_lista = ? WHERE id=?", array(json_encode($_POST["selected-data"], true), $_POST["nlid"]));
            }
        }

        if (isset($_POST["Inicialize_Custom_Notification_List"]) && $_POST["Inicialize_Custom_Notification_List"] == true) {
			if ($listainfo = sql_fetch_array(sql_query("SELECT * FROM egyeni_ertesitesi_listak WHERE dolgozoi_lista IS NOT NULL AND elesitve IS NULL AND id=?", array($_POST["list"])))) {
                $stafflist = json_decode($listainfo["dolgozoi_lista"], true);
                foreach ($stafflist as $worker) {
                    $uzenet = $listainfo["uzenet"];
                    $workerinfo = sql_fetch_array(sql_query("SELECT felh.*,h.cim AS cim FROM felhasznalok felh LEFT JOIN helyszinek h ON h.id=felh.kijelolt_helyszin WHERE felh.id=?", array($worker)));
                    $ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($workerinfo["cegid"])));
					$ertesitesinfo = sql_fetch_array(sql_query("SELECT MAX(datum) AS utolso_ertesites FROM ertesites_log WHERE email = ?",array($workerinfo["email"])));
                    //Sablon szöveg testreszabása az aktuális dolgozóra:
                    $search = array("#nev#", "#domain#", "#cim#", "#utolso_ertesites#");
                    $replace = array($workerinfo["nev"], $ceginfo["domain"], $workerinfo["cim"],date("Y-m-d",strtotime($ertesitesinfo["utolso_ertesites"])));

                    $uzenet = str_replace($search, $replace, $uzenet);

                    //Email kiküldése:
                    $mail = NotificationService::getDefaultMailer();
                    $mail->AddAddress($workerinfo["email"]);
					$mail->AddBCC("tesztemail@hungariamed.hu");
					//$mail->AddAddress("tesztemail@hungariamed.hu");
                    
                    if (!empty(Booking_Constants::USER_BCC_MAIL)) {
                        $mail->AddBCC(Booking_Constants::USER_BCC_MAIL);
                    }

                    $t = $listainfo["targy"];
						
                    $mail->Subject = $t;
                    $mail->Body = $uzenet;
                    $mail->Send();
                    sql_query("INSERT INTO ertesites_log SET uid=?,email=?,targy=?,szoveg=?,datum=NOW()", array($workerinfo["id"], $workerinfo["email"], $listainfo["targy"], $uzenet));
                }

                sql_query("UPDATE egyeni_ertesitesi_listak SET elesitve=NOW() WHERE id=?", array($_POST["list"]));
            }
            die();
        }

        if (isset($_POST["Set_Scroll_To_Staff_List"]) && $_POST["Set_Scroll_To_Staff_List"] == true) {
            if ($ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($_POST["cegid"])))) {
                echo $this->show_allomanyi_lista($_POST["cegid"], $_POST["scroll"]);
            }
            die();
        }

        if (isset($_POST["Staff_List_Searching"]) && $_POST["Staff_List_Searching"] == true) {
            if ($ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($_POST["cegid"])))) {
                echo $this->show_allomanyi_lista($_POST["cegid"], null, $_POST["keyword"], $_POST["szid"]);
            }
            die();
        }


        if (isset($_POST["Staff_List_Filtering"]) && $_POST["Staff_List_Filtering"] == true) {
            if ($ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($_POST["cegid"])))) {
                if ($szervinfo = sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE id=? AND (cegid=? OR parentid=?)", array($_POST["szid"], $_POST["cegid"], $ceginfo["parentid"]))) || $_POST["szid"] == 0) {
                    echo $this->show_allomanyi_lista($_POST["cegid"], null, $_POST["keyword"], $_POST["szid"]);
                }
            }
            die();
        }

        if (isset($_REQUEST["generalsearch"])) {
            $companies = sql_query("select * from cegek where instr(megnev, ?) ORDER BY megnev<>'Új cég', megnev", [$_REQUEST["term"]])->fetchAll(PDO::FETCH_ASSOC);
            echo $this->list($companies);
            die;
        }

        if(isset($_POST["dxidtocid"])){
            if(in_array($_POST["cid"],[61,11])) die();
            $this->adminUtils->add_DokirexCegid_to_BejelentkezoCeg($_POST["dokirexcegid"],$_POST["cid"]);
            if($_POST["res"]){
                echo $this->cegBubbles($_POST["cid"]);
            }
            die();
        }

        if(isset($_POST["setDefaultDokirexCegId"]) && $_POST["setDefaultDokirexCegId"]==true){
            die($this->adminUtils->setDefaultDokirexCegId($_POST["cegid"]));
        }

    }



    public function showPage()
    {

        //echo $this->adminUtils->add_DokirexCegid_to_BejelentkezoCeg(17,1);
        //die();
        if (!$this->adminUser->cegModAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        if (isset($_GET["szerk"])) {
            $row = sql_fetch_array(sql_query("select * from cegek where id=?", array($_GET["szerk"])));
            $_POST = $row;

            $GLOBALS["subtitle"] = $row["megnev"];

            echo "<div style=\"background-color:#fff;padding:0px;\">";
            echo "<form name=\"iform\" method=\"post\" enctype=\"multipart/form-data\">";
            echo "<input type='hidden' id='companyid' name='companyid' value='{$row["id"]}' />";
            echo "<table style=\"font-size:12px;\">";

            echo "<tr><td width=\"150\">Név:</td><td><input class=\"inputbox\" style=\"width:400px;\" type=\"text\" name=\"megnev\" value=\"{$_POST["megnev"]}\"></td></tr>";
            echo "<tr><td>Domain:</td><td>" . Booking_Constants::SITE_PROTOCOL . ":// <input class=\"inputbox\" style=\"width:100px;\" type=\"text\" name=\"domain\" value=\"{$_POST["domain"]}\"> ." . Booking_Constants::SITE_DOMAIN . "</td></tr>";
            echo "<tr><td>E-mail:</td><td><input class=\"inputbox\" style=\"width:300px;\" type=\"text\" name=\"email\" value=\"{$_POST["email"]}\"></td></tr>";
            echo "<tr><td>SMS a pacinenseknek:</td><td><input class=\"inputbox\" style=\"width:20px;\" type=\"text\" name=\"smshour\" value=\"{$_POST["smshour"]}\"> órával előtte</td></tr>";
            echo "<tr><td>Dokirex cég azonosító:</td><td><input class=\"inputbox\" style=\"width:40px;\" type=\"text\" name=\"dokirexTelephelyId\" value=\"{$_POST["dokirexTelephelyId"]}\"></td></tr>";
            echo "<tr><td>Cég csoport azonosító:</td><td><input class=\"inputbox\" style=\"width:40px;\" type=\"text\" name=\"parentid\" value=\"{$_POST["parentid"]}\"></td></tr>";
            echo "<tr><td>Figyelmeztető szöveg:</td><td><input class=\"inputbox\" style=\"width:600px;\" type=\"text\" name=\"beutaloszoveg\" value=\"{$_POST["beutaloszoveg"]}\"></td></tr>";
            echo "<tr><td>Figyelmeztető szöveg (német):</td><td><input class=\"inputawwwbox\" style=\"width:600px;\" type=\"text\" name=\"beutaloszoveg_de\" value=\"{$_POST["beutaloszoveg_de"]}\"></td></tr>";
            echo "<tr><td>Figyelmeztető szöveg (angol):</td><td><input class=\"inputbox\" style=\"width:600px;\" type=\"text\" name=\"beutaloszoveg_en\" value=\"{$_POST["beutaloszoveg_en"]}\"></td></tr>";
            echo "<tr><td>Protokoll:</td><td><textarea class=\"inputbox\" style=\"width:600px;height:80px;\" type=\"text\" name=\"protokoll\">{$_POST["protokoll"]}</textarea></td></tr>";
            
            echo "<tr><td>Dokirex azonosítók:</td><td>";
            echo $this->adminUtils->ceglista(null,null,"onChange='setCegBubble({$_GET["szerk"]},$(this).val(),true)'");
            echo "<div style=\"margin-top:5px\" class=\"cegbubble-container\">";
            echo $this->cegBubbles($_GET["szerk"]);
            echo "</div>";
            echo "</td></tr>";
            
            echo "<tr><td colspan=\"2\"><div style=\"margin-top:10px;padding-top:10px;border-top:1px solid #ccc;\"></div></td></tr>";

            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"aktiv\"" . ($_POST["aktiv"] == 1 ? " checked" : "") . "> Aktív</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"onlyreg\"" . ($_POST["onlyreg"] == 1 ? " checked" : "") . "> Csak regisztrációval lehessen foglalni</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"visszaigazolas\"" . ($_POST["visszaigazolas"] == 1 ? " checked" : "") . "> Vissza kell igazolni a foglalást</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"onlybeutalo\"" . ($_POST["onlybeutalo"] == 1 ? " checked" : "") . "> Csak beutalóval lehessen foglalni</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"nocim\"" . ($_POST["nocim"] == 1 ? " checked" : "") . "> A rendelési cím ne, csak a cím megnevezése látszódjon a pacienseknek</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"foglalasemail\"" . ($_POST["foglalasemail"] == 1 ? " checked" : "") . "> Menjen a foglalásokról e-mail értesítés</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"tudoszuroopcio\"" . ($_POST["tudoszuroopcio"] == 1 ? " checked" : "") . "> Tüdőszűrő opció az üzemorvosi vizsgálatnál</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"laboropcio\"" . ($_POST["laboropcio"] == 1 ? " checked" : "") . "> Labor opció az üzemorvosi vizsgálatnál, eltérő beosztás: <input class=\"inputbox\" style=\"width:40px\" type=\"textbox\" value=\"{$_POST["laboropciotol"]}\" name=\"laboropciotol\" />-tól, <input class=\"inputbox\" style=\"width:40px\" type=\"textbox\" value=\"{$_POST["laboropcioig"]}\" name=\"laboropcioig\" />-ig</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"alkertsend\"" . ($_POST["alkertsend"] == 1 ? " checked" : "") . "> Alkalmassági lejártáról értesítés a pácienseknek</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"alksend\"" . ($_POST["alksend"] == 1 ? " checked" : "") . "> Alkalmassági lista küldése</td></tr>";
            echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"no_doctor_select\"" . ($_POST["no_doctor_select"] == 1 ? " checked" : "") . "> Ne legyen orvos választás a foglalási folyamatban</td></tr>";
            //echo "<tr><td colspan=\"2\" valign=\"top\"><input type=\"checkbox\" value=\"1\" name=\"bound_booking\"".($_POST["bound_booking"]==1?" checked":"")."> Foglalások korlátozása egy kiválasztott parameter alapján </td></tr>";

            echo "<tr><td>Rendszeresség: </td><td><select name=\"alksendint\">";
            echo "<option " . ($_POST["alksendint"] == "napi" ? " selected" : "") . " value=\"napi\">Napi</option>";
            echo "<option " . ($_POST["alksendint"] == "heti" ? " selected" : "") . " value=\"heti\">Heti</option>";
            echo "<option " . ($_POST["alksendint"] == "havi" ? " selected" : "") . " value=\"havi\">Havi</option>";
            echo "</select></td></tr>";
            echo "<tr><td>Fogadó email(ek): </td><td ><textarea class=\"inputbox\" name=\"sendmail\" style=\"width:600px;height:80px;\">" . (isset($_POST["sendmail"]) ? $_POST["sendmail"] : "") . "</textarea>";
            echo "</td></tr>";

            echo "<tr><td colspan=\"2\"><div class=\"tdsepdiv\">Foglalás mező paraméterek</div></td></tr>";

            foreach ($this->optionalFields as $field => $name) {
                echo $this->_fieldOptionsRow($field);
            }

            /*echo "<tr><td colspan='2'><div class='tdsepdiv'>Cég egységek</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='submit' name='addcegvar' value='+ Egység hozzáadása'></td></tr>";

            $resb = sql_query("select * from cegvars where cegid=? order by varos,megnev", array($_GET["szerk"]));

            $sor = 1;
            while ($rowb = sql_fetch_array($resb)) {
                echo "<tr><td colspan='2'>";
                echo "<input type='hidden' name='cegvarid{$sor}' value='{$rowb["id"]}'/>";
                echo "<div><input type='text' name='cegvarvaros{$sor}' style='width:195px;' placeholder='város...' value='{$rowb["varos"]}'/> <input type='text' name='cegvarmegnev{$sor}' style='width:395px;' placeholder='egység megnevezése...' value='{$rowb["megnev"]}'/>";
                echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delcegvar={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt az egységet?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
                echo "</div>";
                echo "</td></tr>";
                $sor++;
            }*/

            echo "<tr><td colspan='2'><div class='tdsepdiv'>Beosztások</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='submit' name='addcegbeosztas' value='+ Beosztás hozzáadása'></td></tr>";

            $resb = sql_query("select * from cegbeosztasok where cegid=? order by megnev", array($_GET["szerk"]));

            $sor = 1;
            while ($rowb = sql_fetch_array($resb)) {
                echo "<tr><td colspan='2'>";
                echo "<input type='hidden' name='cegbeosztasid{$sor}' value='{$rowb["id"]}'/>";
                echo "<div><input type='text' name='cegbeosztasmegnev{$sor}' style='width:595px;' placeholder='beosztás megnevezése...' value='{$rowb["megnev"]}'/>";
                echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delcegbeosztas={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt a beosztást?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
                echo "</div>";
                echo "</td></tr>";
                $sor++;
            }



            echo "<tr><td colspan='2'><div class='tdsepdiv'>Visszaigazoló szövegek</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><input type='submit' name='addvisszaigazolo' value='+ Visszaigaziló szöveg hozzáadása'></td></tr>";

            $resb = sql_query("select * from visszaigazolok where cegid=? order by id", array($_GET["szerk"]));

            $sor = 1;
            while ($rowb = sql_fetch_array($resb)) {
                echo "<tr><td colspan='2'>";

                echo "<input type='hidden' name='visszid{$sor}' value='{$rowb["id"]}'/>";

                echo "<select name='helyszinid{$sor}' style='width:300px;'>";

                $resh = sql_query("SELECT * FROM helyszinek WHERE INSTR(ceglink,'|{$rowb["cegid"]}|') order by cim");

                echo "<option value='0'>Minden helyszín</option>";
                while ($rowh = sql_fetch_array($resh)) {
                    echo "<option value='{$rowh["id"]}'" . ($rowb["helyszinid"] == $rowh["id"] ? " selected" : "") . ">{$rowh["cim"]}</option>";
                }
                echo "</select> ";

                echo "<select name='orvosid{$sor}' style='width:300px;'>";

                $resh = sql_query("SELECT o.* FROM orvosok o LEFT JOIN orvos_beosztas_new b ON b.`orvosid`=o.`id` WHERE (instr(b.beocegek, '|{$rowb["cegid"]}|') or b.beocegek='') GROUP BY o.id ORDER BY nev");

                if (sql_num_rows($resh) > 1) echo "<option value='0'>Minden orvos</option>";
                while ($rowh = sql_fetch_array($resh)) {
                    echo "<option value='{$rowh["id"]}'" . ($rowb["orvosid"] == $rowh["id"] ? " selected" : "") . ">{$rowh["nev"]}</option>";
                }
                echo "</select> ";

                echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delvisszaigazolo={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt a visszaigazoló szöveget?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
                if (trim($rowb["mapurl"]) != "") echo "<a target='_blank' href='{$rowb["mapurl"]}'><img style='height:18px;padding:0px 0px 0px 3px;' src='images/mapicon.png' title='Térkép tesztelése'/></a>";
                echo "</div>";
                echo "<div><textarea name='szoveg{$sor}' style='width:595px;height:80px;' placeholder='szöveg a visszaigazoló levélbe...'>{$rowb["szoveg"]}</textarea></div>";
                echo "<div><input type='text' name='mapurl{$sor}' style='width:595px;' placeholder='google maps link...' value='{$rowb["mapurl"]}'/>";
                echo "</div>";

                echo "</td></tr>";
                $sor++;
            }



            //Itt meg kell jelenítenem az értesítő üzeneteket...
            //Legördülő listából kellene kiválasztani az aktuálisan szerkeszthető üzeneteket
            //Az új hozzáadása meg egy + gomb lenne a lista mellett közvetlen
            //Simán mikor kiválasztom az adott üzenetet, először olvashatóan jelenjen meg
            //De legyen mellette egy szerkesztés gomb és egy mentés ezt követően
            //Egy mentés másként is ideális lenne még... így különösebb másolgatások nélkül is
            //meg lehetne csinálni a kövekező sablonokat/cég


            echo "<tr><td colspan='2'><div class='tdsepdiv'>Telephelyek / Szervezteti egységek</div></td></tr>";
            //kell egy lekérdezés, amivel megszerzem az értesítő üzeneteket
            echo "<tr><td colspan='2'><div>";
            echo "<input type='button' name='set-new-notification-message' onClick='' value='+ Hozzáadás'>";
            echo "<div>";
            echo "<table>";
            $telephelyek= $this->utils->getCegTelephelyCsoportok($_GET["szerk"]);
            $sor = 0;
            $q=sql_query("SELECT * FROM cegvars WHERE cegid=?",[$_GET["szerk"]]);
            while($resq=sql_fetch_array($q)){
                $sor++;
                echo "  <tr>";
                echo "      <td><select name=\"telephely_parentid{$sor}\" style=\"width:200px\">";
                echo "          <option value=\"0\" ".($resq["parentid"]==0?"selected=\"true\"":"").">Csoport</option>";
                foreach($telephelyek as $telephely){
                    echo "      <option ".($telephely["id"]==$resq["parentid"]?"selected=\"true\"":"")." value=\"{$telephely["id"]}\">{$telephely["name"]}</option>";
                }
                echo "      </select></td>";//Parentid helye
                echo "      <td><input type=\"textbox\" value=\"{$resq["megnev"]}\" name=\"telephely_megnev{$sor}\" style=\"width:400px\"></td>";//Szervezeti egység neve
                echo "      <td>".($resq["parentid"]!=0?$this->adminUtils->ceglista($resq["dokirexcegid"], null,"onChange='setTelephelyDokireId({$resq["id"]},$(this).val())'"):"")."</td>";//Selectable értéke (egyenlőre nemtudom miez)
                echo "      <td id=telephelyhelyszingomb{$resq["id"]}>".$this->utils->showTelephelyHelyszinek($resq)."</td>";//Helyszínek amik meg jelennek ezalatt a szervezeti egység alatt
                echo "      <td id=telephszurestipusgomb{$resq["id"]}>".$this->utils->showSzurestipusok($resq)."</td>";//Vizsgálat típusok amiket elláthatunk itt

                echo "  </tr>";
                echo "  <tr>";
                echo "      <td colspan=\"5\"><div id=\"helyszinvalaszto{$resq["id"]}\"></div></td>";
                echo "  </tr>";
                echo "  <tr>";
                echo "      <td colspan=\"5\"><div id=\"szuresvalaszto{$resq["id"]}\"></div></td>";
                echo "  </tr>";
            }
            
            echo "</table>";
            echo "</div>";
            echo "</div></td></tr>";

            echo "<tr><td colspan='2'></td></tr>";


            echo "<tr><td colspan='2'><div class='tdsepdiv'>Nincs foglalás szöveg</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'>*ha ezek a mezők ki vannak töltve, akkor a foglalás nem lesz lehetséges ehhez a céghez, helyette ez a szöveg fog megjelenni (HTML tartalom használható)</td></tr>";

            echo "<tr><td colspan='2'><textarea placeholder='HU szöveg' class='inputbox' style='width:800px;height:80px;' type='text' name='nofoglalas_hu'>{$_POST["nofoglalas_hu"]}</textarea></td></tr>";
            echo "<tr><td colspan='2'><textarea placeholder='EN szöveg' class='inputbox' style='width:800px;height:80px;' type='text' name='nofoglalas_en'>{$_POST["nofoglalas_en"]}</textarea></td></tr>";
            echo "<tr><td colspan='2'><textarea placeholder='DE szöveg' class='inputbox' style='width:800px;height:80px;' type='text' name='nofoglalas_de'>{$_POST["nofoglalas_de"]}</textarea></td></tr>";

            echo "</table>";


            echo "<br/><input type='submit' name='cegmentes' value='Mentés'> ";
            echo "<input type='submit' name='scancel' value='Vissza'> ";

            echo "</form>";

            //TODO: át kell nézni, jelenleg nem működik
            //echo "<div class='tdsepdiv' style='margin-top:20px;'>{$_POST["megnev"]} orvosai és szolgáltatásai</div>";
            //echo "<div id='doctorlist'>";
            //echo $this->_orvosAndServiceList($row["id"]);
            ///echo "</div>";

            echo "</div>";
            echo "</div>";
            return;
        }


        echo "<div style='margin-bottom:20px;'>";
        echo "<input data-page='companies' data-resultdiv='tartalomlist' type='text' id='generalsearch' value='' placeholder='Keresés...'/>&nbsp;";
        echo "</div>";

        echo "<div id='tartalomlist'>".$this->list(sql_query("SELECT * from cegek ORDER BY megnev<>'Új cég', megnev")->fetchAll(PDO::FETCH_ASSOC))."</div>";
    }

    private function list($companies):string {
        $html = "";
        $html.= "<table cellpadding='0' cellspacing='0' border='0'>";
        foreach ($companies as $row) {
            $tc = "tcella";
            if (!isset($first)) {
                $html.= "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (trim($row["megnev"]) == "") {
                $row["megnev"] = "nincs neve";
            }

            $options = "";
            if ($row["onlyreg"] == 1) $options .= "<div>Csak regisztráltaknak</div>";
            if ($row["onlybeutalo"] == 1) $options .= "<div>Csak beutalóval lehet foglalni</div>";
            if ($row["no_doctor_select"] == 1) $options .= "<div>Nincs orvos választás a foglalásnál</div>";
            //if ($row["fieldoptions"] != "") $options .= "<div>" . $this->displayFieldOptions($row["fieldoptions"]) . "</div>";

            $html.= "<tr>";
            $html.= "<td nowrap valign='top'><div class={$tc}><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["megnev"]}</a></div></td>";

            $url = Booking_Constants::SITE_PROTOCOL . "://{$row["domain"]}." . Booking_Constants::SITE_DOMAIN;

            $html.= "<td nowrap valign='top'><div class='{$tc}'>" . ($row["domain"] == "" ? "" : "{$url} (<a target='_blank' href='{$url}'>open</a>)") . "</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}' style='min-width:300px;padding-right: 10px;'>{$options}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}' style='min-width:50px;'>" . ($row["aktiv"] == 1 ? "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>" : "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>") . "</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='alert(\"Nem törölhető!\");return false;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
            $html.= "</tr>";
            $html.= "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        $html.= "</table>";
        return $html;
    }


    private function displayFieldOptions($options)
    {
        $options = str_replace("'", "", $options);
        $options = str_replace(",", ", ", $options);
        return $options;
    }

    private function _fieldOptionsRow($field)
    {
        $html = "<tr><td>" . $this->optionalFields[$field] . ":</td><td>";

        $option = 1;
        if (substr_count($_POST["fieldoptions"], "notreq_{$field}")) {
            $option = 0;
        }
        if (substr_count($_POST["fieldoptions"], "hidden_{$field}")) {
            $option = 2;
        }

        $html .= "<input name='fieldoption_{$field}' value='1' type='radio' " . ($option == 1 ? "checked" : "") . "/> kötelező ";
        $html .= "<input name='fieldoption_{$field}' value='0' type='radio' " . ($option == 0 ? "checked" : "") . "/> nem kötelező ";
        $html .= "<input name='fieldoption_{$field}' value='2' type='radio' " . ($option == 2 ? "checked" : "") . "/> elrejtés ";
        $html .= "</td></tr>";
        return $html;
    }


    public function show_allomanyi_lista($cegid, $scroll = null, $keyword = null, $filter = null)
    {
        if ($ceginfo = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?", array($cegid)))) {

            //Lekérdezéshez szükséges query össze rakása
            if (!empty($keyword)) {
                //Mi lehet benne? E-mail, taj szám, születési dátum és név
                //A feltételt OR-okkal fogom felépíteni
                $searchConditions = " AND (al.nev LIKE '%" . htmlspecialchars($keyword) . "%' ";
                $searchConditions .= "OR al.taj LIKE '%" . htmlspecialchars($keyword) . "%' ";
                $searchConditions .= "OR al.email LIKE '%" . htmlspecialchars($keyword) . "%' ";
                $szuldatumCondition = str_replace(".", "-", $keyword);
                $searchConditions .= "OR al.szuldatum LIKE '%" . htmlspecialchars($szuldatumCondition) . "%') ";
            } else {
                $searchConditions = "";
            }

            if (!empty($filter) && $filter != "0") {
                $searchConditions .= " AND al.szid=" . htmlspecialchars($filter) . " ";
            }

            $query = array("query" => "SELECT al.*,cv.megnev AS szervnev FROM allomanyi_listak al LEFT JOIN cegvars cv ON cv.id=al.szid WHERE (al.cegid=? or al.parentid=?) {$searchConditions} ORDER BY al.nev ASC", "variables" => array($cegid, $ceginfo["parentid"]));

            if (sql_num_rows(sql_query($query["query"], $query["variables"])) > 0) {

                //Oldal számolás:
                $page_counter = sql_query($query["query"], $query["variables"]);

                $page_numb = $page_counter->rowCount() / 50;
                $page  = array();
                $range = 50;
                for ($i = 0; $i <= round($page_numb); $i++) {
                    if ($page_numb < round($page_numb) && $i == round($page_numb)) {
                        break;
                    }
                    $start_value = ($i * $range);
                    $page[] = array("number" => ($i + 1), "limit" => "{$start_value}, 250");
                }

                //Ha olyan oldal szám szerepel az URL-ben ami irreleváns, akkor átirányít az első oldalra:
                if (!empty($scroll)) {
                    if ($scroll > count($page) || $scroll < 0) {
                        $scroll = 1;
                    }
                } else {
                    $scroll = 1;
                }

                $limitedListQuery = sql_query($query["query"] . " LIMIT {$page[($scroll - 1)]['limit']}", $query["variables"]);

                while ($staffResult = sql_fetch_array($limitedListQuery)) $staffData[] = $staffResult;
                $content = $html = "";
                $row = 0;
                $tdCSS = "style='padding: 8px 8px 8px 0px;border-bottom:1px solid gray'";
                $columns = array("#.", "Teljesnév", "Szül. dátum", "TAJ", "E-mail", "Anyja neve", "Szül. hely", "Szerv. egys.", "Lakcím", "Telefon", "Hivatali szám",   "<span id='checkBoxSwitcher' onClick='switchCheckBoxes(\"referral-checker\",\"enable\")' style='color:red;cursor:pointer'>Mindegyik</span>");
                $columnTitle = implode("</td><td {$tdCSS}>", $columns);
                foreach ($staffData as $staff) {
                    $content .= "<tr>";
                    $content .= "<td {$tdCSS}>#" . ($row + 1) . "</td>";
                    $content .= "<td {$tdCSS}>{$staff['nev']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['szuldatum']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['taj']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['email']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['anyjaneve']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['szulhely']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['szervnev']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['lakcim']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['telefon']}</td>";
                    $content .= "<td {$tdCSS}>{$staff['hivataliszam']}</td>";
                    $content .= "<td {$tdCSS} align='center' onClick='toggleCheckBox(\"#{$staff['id']}\")'><input type='checkbox' onClick='toggleCheckBox(\"#{$staff['id']}\")'  class='referral-checker' name='selected-data[]' id='{$staff['id']}' value='{$staff['id']}'/></td>";
                    $content .= "</tr>";
                    $row++;
                }

                //Lapozó inicializálása
                $pager = "";
                $preHide = 0;
                foreach ($page as $key => $value) {
                    if ($page[$key]['number'] == $scroll) {
                        $aStyle = "style='background-color: #2f8793; text-decoration: none;'";
                    } else {
                        $aStyle = "style='cursor:pointer'";
                    }
                    //Ha a lapszám több mint 10 akkor rejtse le a a fölösleges lap számot(de az 1.-t jelenítse meg.)
                    if (($scroll - 10) > $key) {
                        if ($preHide > 0) continue;
                        $pager .= "<a class = 'ujbutton' onClick='Set_Scroll_To_Staff_List(1,{$cegid})'  {$aStyle} >1</a>&nbsp;";
                        $pager .= "...&nbsp;";
                        $preHide++;
                        continue;
                    }
                    $pager .= "<a class = 'ujbutton' onClick='Set_Scroll_To_Staff_List({$page[$key]['number']},{$cegid})' {$aStyle} >{$page[$key]['number']}</a>&nbsp;";
                    //Ha lapszámhoz képest 8 értékkel nagyobb lapokat rejtse le, de az utolsót mutassa.
                    if ($key == ($scroll + 8)) {
                        $pager .= "...&nbsp;";
                        $pager .= "<a class = 'ujbutton' onClick='Set_Scroll_To_Staff_List(" . count($page) . ",{$cegid})' {$aStyle} >" . count($page) . "</a>&nbsp;";
                        break;
                    }
                }

                //Eredmény megjeleníése:
                $html = "<div style='max-height:600px;overflow-y:scroll'>";

                $html .= "<table class='transactions_table' style='border-collapse:collapse'>";
                $html .= "<tr><td {$tdCSS}>" . $columnTitle . "</td></tr>";
                $html .= $content;
                $html .= "</table></div>";
                if ($scroll != 1 || $page_numb > 1) {
                    $html .= "<table style='margin:auto'><tr><td colspan='12' align='center' style = 'padding-top:10px'>{$pager}</td></tr></table>";
                }
                return $html;
            }
            return;
        }

        if (isset($_REQUEST["generalsearch"])) {
            $companies = sql_query("select * from cegek where instr(megnev, ?) ORDER BY megnev<>'Új cég', megnev", [$_REQUEST["term"]])->fetchAll(PDO::FETCH_ASSOC);
            echo $this->list($companies);
            die;
        }
    }
    public function cegBubbles($cid){
        $html = "";
        $q=sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id=?",array($cid)));

        if(!empty($q["dokirexcegid_json"])){
            $dokirexServices = new DokirexService();
            $data = $dokirexServices->process_dokirexcegid_json($q["dokirexcegid_json"]);
        }else{
            return;
        }

        foreach($data as $key=>$bubble){
            $html.="<a class=\"serviceselected\" onClick='setCegBubble({$cid},{$bubble["id"]},true)' href=\"#\">{$bubble["nev"]}</a>&nbsp;";
        }

        return $html;
    }

    
}
