<?php

class AdminAjaxService {

    public function start() {

        $adminUtils = new AdminUtils();
        $adminUser = new AdminUser();

        if (isset($_GET["print"]) && isset($_GET["template"])) {
            $printService = new PrintService();
            $printService->setTemplate($_GET["template"]);
            if (isset($_GET["fid"]) && isset($_GET["p"])) {
                $printService->setReservation($_GET["fid"], $_GET["p"]);
            }
            $printService->start();
            die;
        }

        if (isset($_GET["delassets22222222222222"])) {
            $docAgent = new DocAgent();
            $images = sql_query("SELECT * FROM dokumentumok WHERE assetid IN ('covidpassimage', 'covidegsimage') order by id desc limit 1000")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($images as $image) {
                echo $image["id"]. " ".$image["filename"]. " ";
                //die;
                $docAgent->deleteAsset($image["assetid"], $image["id"]);
                //$docAgent->deleteDoc($image["id"], $image["kod"]);
                //die;
            }
            die;
        }

        if (isset($_GET["showfoto"])) {
            $service = new DocAgent();
            $service->outputAsset($_GET["showfoto"], $_GET["c"]);
        }

        if (isset($_GET["simpletest"])) {
            $simpleService = new SimplePayService();
            $simpleService->startPay(131688);
            die;
        }

        if (isset($_POST["showrefund"]) && $adminUser->authenticated()) {
            $simpleService = new SimplePayService();
            echo $simpleService->showRefundWindow($_POST["showrefund"]);
            die;
        }

        if (isset($_POST["startsimplerefund"]) && $adminUser->authenticated()) {
            $simpleService = new SimplePayService();
            echo $simpleService->startRefund($_POST["startsimplerefund"], $_POST["osszeg"]);
            die;
        }

        if (isset($_POST["scancel"])) {
            header("location:index.php?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["setcegfilter"])) {
            $_SESSION["cegfilter"] = $_GET["setcegfilter"];
            $_SESSION["kereskulcs"] = "";
            header("location:index.php?page={$_GET["p"]}");
            die();
        }

        if (isset($_GET["addnew"])) {
            if ($_GET["page"] == "companies" && $adminUser->cegModAccess()) {
                sql_query("insert into cegek set megnev='Új cég'");
            }
            if ($_GET["page"] == "places" && $adminUser->placesAccess()) {
                sql_query("insert into helyszinek set cim='Új helyszín'");
            }
            if ($_GET["page"] == "doctors" && $adminUser->doctorsAccess()) {
                sql_query("insert into orvosok set nev='Új orvos',createdby=?, created=now()", array($adminUser->user["nev"]));
                $oid = sql_insert_id();
                sql_query("update orvosok set username='d{$oid}',jelszo=SUBSTR(MD5(CONCAT(nev,id)) FROM 3 FOR 6) where id='{$oid}'");
            }
            if ($_GET["page"] == "screenings" && $adminUser->szurestipusAccess()) {
                sql_query("insert into szurestipusok set megnev='Új tétel'");
            }
            if ($_GET["page"] == "users" && $adminUser->jogosultsagAccess()) {
                sql_query("insert into users set nev='Új felhasználó'");
                logActivity("user", sql_insert_id(), "felhasználó létrehozva");
            }
            if ($_GET["page"] == "webpagedata") {
                sql_query("insert into webpagedata set domain='aaaaaa.hu'");
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["delete"])) {
            if ($_GET["page"] == "places" && $adminUser->placesAccess()) {
                sql_query("delete from helyszinek where id=?", array($_GET["delete"]));
            }
            if ($_GET["page"] == "doctors" && $adminUser->doctorsAccess()) {
                sql_query("delete from orvosok where id=?", array($_GET["delete"]));
                sql_query("delete from orvos_beosztas_new where orvosid=?", array($_GET["delete"]));
            }

            if ($_GET["page"] == "screenings" && $adminUser->szurestipusAccess()) {
                sql_query("delete from szurestipusok where id=?", array($_GET["delete"]));
            }
            if ($_GET["page"] == "users" && $adminUser->jogosultsagAccess()) {
                sql_query("delete from users where id=? and id<>1", array($_GET["delete"]));
                logActivity("user", $_GET["delete"], "felhasználó törölve");
            }
            if ($_GET["page"] == "patients") {
                sql_query("delete from felhasznalok where id=?", array($_GET["delete"]));
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["oaktivtoggle"])) {
            if ($_GET["page"] == "places") {
                sql_query("update helyszinek set aktiv=not aktiv where id=?", array($_GET["oaktivtoggle"]));
            }
            if ($_GET["page"] == "doctors") {
                sql_query("update orvosok set aktiv=not aktiv where id=?", array($_GET["oaktivtoggle"]));
            }
            if ($_GET["page"] == "screenings") {
                sql_query("update szurestipusok set aktiv=not aktiv where id=?", array($_GET["oaktivtoggle"]));
            }
            if ($_GET["page"] == "companies") {
                sql_query("update cegek set aktiv=not aktiv where id=?", array($_GET["oaktivtoggle"]));
            }
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }
        if (isset($_GET["ocsaktivtoggle"])) {
            if ($_GET["page"] == "szurestipusok") sql_query("update szurescsomagok set aktiv=not aktiv where id=?", array($_GET["ocsaktivtoggle"]));
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_POST["add2sztceg"])) {
            $sor = intval($_POST["sor"]);
            $cegid = "|" . intval($_POST["cegid"]) . "|";

            if ($row = sql_fetch_array(sql_query("select * from arak where id=?", array($_POST["arid"])))) {

                if (substr_count($row["cegid"], $cegid) == 0) {
                    $row["cegid"] .= $cegid;
                    sql_query("update arak set cegid=? where id=?", array($row["cegid"], $_POST["arid"]));
                }

                echo $adminUtils->showCegListSzT($row["cegid"], $sor);
            }
            die();
        }

        if (isset($_POST["removesztceg"])) {
            $sor = intval($_POST["sor"]);
            $cegid = "|" . intval($_POST["cegid"]) . "|";

            sql_query("update arak set cegid=replace(cegid,?,'') where id=?", array($cegid, $_POST["arid"]));

            if ($row = sql_fetch_array(sql_query("select * from arak where id=?", array($_POST["arid"])))) {
                echo $adminUtils->showCegListSzT($row["cegid"], $sor);
            }
            die();
        }

        if (isset($_POST["insertPaciensIntoDokirex"]) && $_POST["insertPaciensIntoDokirex"] == true) {
            $html = $response = "";
            $error = array();
            $dokirexService = new DokirexService();

            error_reporting(E_ALL);
            ini_set('display_errors', 1);

            $required = ["Nev", "SzuletesiDatum", "Azonosito", "Nem", "Iranyitoszam", "Telepules", "Cim", "SzuletesiNev"];

            $params = sql_fetch_array(sql_query("SELECT fogl.id as fid, fogl.nev AS 'Nev', fogl.taj AS 'Azonosito', '2' AS 'AzonositoTipusID',fogl.szuldatum AS 'SzuletesiDatum', 
                                                    fogl.szulhely AS 'SzuletesiHely', fogl.anyjaneve AS 'AnyjaNeve', CASE WHEN fogl.neme = 0 THEN 3 ELSE fogl.neme END AS 'NemID',
                                                    fogl.nev AS 'SzuletesiNev', '109' AS 'AllampolgarsagID', fogl.telefon AS 'Telefon', fogl.telefon AS 'Mobiltelefon',
                                                    fogl.irsz AS 'Iranyitoszam', fogl.varos AS 'Telepules', fogl.utca AS 'Cim', 
                                                    fogl.email AS 'Email', null AS 'SzigSzam', null AS 'KozgyogyTol', null AS 'KozgyogyIg', null AS 'KozgyogySzam', 
                                                    '3' AS 'FelvevoID', '3' AS 'UtolsoModositoID'                                            
                                            FROM foglalasok fogl WHERE id=?", [$_POST["pid"]]));

            $params["SzuletesiDatum"] = str_replace(".", "", $params["SzuletesiDatum"]);
            $params["SzuletesiDatum"] = str_replace("-", "", $params["SzuletesiDatum"]);
            $params["SzuletesiDatum"] = substr($params["SzuletesiDatum"], 0, 4) . "-" . substr($params["SzuletesiDatum"], 4, 2) . "-" . substr($params["SzuletesiDatum"], 6, 2);

            foreach ($params as $index => $value) {
                if ($value == "" && in_array($index, $required)) {
                    $error[] = "<span style='color:red'>*{$index} mező megadása kötelező!</span>";
                }
            }

            if (empty($error)) {
                $response = $dokirexService->insertPaciensIntoDokirex($params);

                $reservationData = sql_query("select cegid from foglalasok where id=?", [$_POST["pid"]])->fetch(PDO::FETCH_ASSOC);
                if (Booking_Constants::SQL_DB == "hungariamed" && in_array($reservationData["cegid"], [111111,111112])) {
                    $_REQUEST["config"] = "keltexmed";
                    $dokirexServiceKeltexMed = new DokirexService();
                    $responseKeltexMed = $dokirexServiceKeltexMed->insertPaciensIntoDokirex($params);
                }
            }

            $html .= "<div style='color:#444;text-align:center;'>";
            $html .= "<div id='loginbox' class='loginbox'>";
            $html .= "<div class='loginhead'>Dokirex adatfeltöltés</div>";

            $html .= "<div style='padding:20px;text-align:center;'>";

            if (count($error) > 0) {
                for ($i = 0; $i < count($error); $i++) {
                    $html .= "<div style='text-align:left'>{$error[$i]}</div>";
                }
            } else {
                $html .= "<div style='margin-top:10px;'>";
                if (is_array($response)) {
                    foreach ($response as $index => $value) {
                        $html .= "$index = $value <br>";
                    }
                } else {
                    $html .= $response;
                }

                $html .= "</div>";
            }

            $html .= "<div style='padding-top:10px;'><input onclick='hideGeneralPopup();return false;' type='button' id='simplerefundclosebutton' value='Bezárás' /></div>";
            $html .= "</div>";

            $html .= "</div>";
            $html .= "</div>";

            die($html);
        }

        if (isset($_POST['manualNotificationSend']) && $_POST['manualNotificationSend'] == true) {
            header('Content-Type: application/json');

            $request = sql_fetch_array(sql_query("SELECT userertesitve,email FROM foglalasok where id=?", array($_POST['id'])));

            //Ha hibás e-mail van megadva akkor hibára fut:
            if (!filter_var($request["email"], FILTER_VALIDATE_EMAIL)) {
                die(json_encode(array("status" => "error", "text" => "Nincs megadott helyes e-mail cím!")));
            } else {
                //Ha nem volt még értesítve, vagy post tartalmazza a megerősítési kérelmet:
                if (!NotificationService::hasNotification("usernotification", $_POST["id"]) || (isset($_POST['status']) && $_POST['status'] == true)) {
                    //Lekérdezés ellenőrzése
                    $service = new BookingService();
                    $service->notificationService->sendUserReservationNotification($_POST['id'], true);
                    die(json_encode(array("status" => true, "text" => "Sikeres értesítő küldés!")));
                } else {
                    $notifications = NotificationService::getNotificationsByType("usernotification", $_POST["id"]);
                    if (!empty($notifications)) {
                        $notification = reset($notifications);
                        die(json_encode(array("status" => false, "text" => "A páciens részére már volt értesítés küldve {$notification['datum']}-kor! Biztosan küldeni akarsz egyet ismét?")));
                    }
                }
            }
            die();
        }

        if(isset($_GET["dokirexTest"]) && $_GET["dokirexTest"]=="buHL4tjsVkXt9K4M"){
            //Dokirex tesztelés response outputtal
            $dokirexService = new DokirexService();
            echo $response = $dokirexService->test_run();
            die();
        }

        if (isset($_GET["sendingService"]) && $_GET["sendingService"] == "beuertkuldes" && $_GET["SecureCode"]=="7ae70e2062e5f193016d5885aaa868786649") {

            //SC: 7ae70e2062e5f193016d5885aaa868786649

            //Excel és a Csatolmány elérési mappái:
            $ExcelFile  = __DIR__ . "/other/excel_lists/";
            $Attachment = __DIR__ . "/other/attachments/";

            //Fájl neve:
            $ExcelFile .="04_havi_lista.xlsx";
            $Attachment.="stressz teszt.docx";

            //Excel betöltése:
            $excelReader = PHPExcel_IOFactory::createReaderForFile($ExcelFile);
            $excelObj = $excelReader->load($ExcelFile);
            $worksheet = $excelObj->getSheet(0);
            $lastRow = $worksheet->getHighestRow();

            for($row = 2; $row <= $lastRow; $row++) {
                //echo $worksheet->getCell('A'.$row)->getValue()." : ".$worksheet->getCell('B'.$row)->getValue()." : ".$worksheet->getCell('C'.$row)->getValue()." : ".$worksheet->getCell('D'.$row)->getValue()."<br/>";

                $mail = NotificationService::getDefaultMailer();
                $mail->AddAddress($worksheet->getCell('C'.$row)->getValue(), $worksheet->getCell('A'.$row)->getValue());
                $mail->AddAddress($worksheet->getCell('D'.$row)->getValue(), $worksheet->getCell('D'.$row)->getValue());
                //$mail->AddAddress("m.gergely9409@gmail.com", "Márton Gergely");

                $subject = "Orvosi alkalmassági vizsgálata hamarosan lejár!";

                $mbody = "Kedves {$worksheet->getCell('A'.$row)->getValue()},<br/>";
                $mbody.= "Az orvosi alkalmassági vizsgálata hamarosan lejár!<br/>";
                $mbody.= "Lejárat dátuma: {$worksheet->getCell('B'.$row)->getValue()}<br/>";
                $mbody.= "Kérem foglaljon időpontot honlapunkon:<br/>";
                $mbody.= "<a href='https://bert.hungariamed.hu'>https://bert.hungariamed.hu</a><br/>";
                $mbody.= "Tisztelettel,<br/>";
                $mbody.= "Hungária Med - M.kft";

                $mail->Subject = $subject;
                $mail->Body = $mbody;
                $mail->AddAttachment($Attachment);
                if($mail->Send()) echo "success!({$worksheet->getCell('A'.$row)->getValue()})<br/>";
                else echo "failed!({$worksheet->getCell('A'.$row)->getValue()})<br/>";
            }
            die();
        }

        if (isset($_POST["checkAdminWarnings"])) {
            $return = [
                "number" => 0,
                "button" => "",
                "window" => ""
            ];

            if ($adminUser->jogosultsagAccess()) {
                $warnings = sql_query("select * from warnings where checked=0 order by created")->fetchAll(PDO::FETCH_ASSOC);
                $numberOfWarnings = count($warnings);
                $return["number"] = $numberOfWarnings;
                if ($numberOfWarnings > 0) {
                    $return["button"] = "<span style='color:#fff;background:#f00;padding:2px 5px;cursor:pointer;border-radius: 3px;' onclick='toggleWarnWindow();'>{$numberOfWarnings} figyelmeztetés!</span>";

                    $html = "";
                    foreach ($warnings as $warning) {
                        $event = "";
                        if (substr_count($warning["metaid"], "collision") > 0) {
                            $event = "Dupla foglalás";
                        }
                        if (substr_count($warning["metaid"], "szabadsag") > 0) {
                            $event = "Szabadságra eső foglalás";
                        }
                        $html.="<div style='display:table-row;'>";
                        $html.="<div style='display:table-cell;'>{$event} {$warning["szoveg"]}</div>";
                        $html.="<div style='display:table-cell;text-align: right;'>&nbsp;&nbsp;<a style='color:#ff0;' href='#' onclick='warningAck({$warning["id"]});return false;'>OK</a></div>";
                        $html.="</div>";
                    }
                    if ($html != "") {
                        $html= "<div style='display:table;width:100%;'>{$html}</div>";
                    }
                    $return["window"] = $html;
                }
            }

            $this->jsonOut($return);
        }

        if (isset($_POST["warningAck"])) {
            if ($adminUser->authenticated()) {
                sql_query("update warnings set checked=1, checkedby=? where id=?", [$adminUser->user["username"], $_POST["wid"]]);
            }
            die("ok");
        }


        if (isset($_POST["uploadasset"])) {
            $dataId = intval($_POST["uploadasset"]);
            $tipus  = $_POST["tipus"];

            $docAgent = new DocAgent();
            $result = $docAgent->uploadAssetImage($tipus, $dataId, $_FILES[0]);

            $result["html"] = $docAgent->showAssetEditor($tipus, $dataId);
            $this->jsonOut($result);

            die;
        }

        if (isset($_POST["deleteasset"])) {
            $id = intval($_POST["deleteasset"]);
            $tipus  = $_POST["tipus"];

            $data = sql_fetch_array(sql_query("select dataid from dokumentumok where id=? and assetid=?", [$id, $tipus]));
            $dataId = $data["dataid"];

            $docAgent = new DocAgent();
            $docAgent->deleteAsset($tipus, $id);

            $result["html"] = $docAgent->showAssetEditor($tipus, $dataId);
            $this->jsonOut($result);

            die;
        }

        if (isset($_POST['searchpaciens'])) {
            $kulcs = $_POST["term"];

            $res = sql_query("SELECT u.*,c.megnev as cegnev FROM felhasznalok u
	            LEFT JOIN cegek c ON c.`id`=u.`cegid`
	            WHERE true and ( instr(u.nev, :kulcs) or instr(u.taj, :kulcs) or instr(u.torzsszam, :kulcs) or instr(u.email, :kulcs) )
            	ORDER BY u.nev limit 9" ,["kulcs" => $kulcs]);

            echo "<div style='display:table;'>";
            while ($row = sql_fetch_array($res)) {
                echo "<div style='display:table-row;'>";
                echo "<div style='display:table-cell;white-space: nowrap;overflow: hidden;width:160px;max-width:160px;'><a href='#' onclick='bindUserToReservation({$row["id"]});return false;'>{$row["nev"]}</a></div>";
                echo "<div style='display:table-cell;padding-left:10px;white-space: nowrap;overflow: hidden;width:80px;max-width:80px;'>{$row["szuldatum"]}</div>";
                echo "<div style='display:table-cell;padding-left:10px;white-space: nowrap;overflow: hidden;width:80px;max-width:80px;'>{$row["taj"]}</div>";
                echo "<div style='display:table-cell;padding-left:10px;white-space: nowrap;overflow: hidden;width:190px;max-width:190px;'>{$row["cegnev"]}</div>";
                echo "</div>";
            }
            echo "</div>";
            die;
        }

        if (isset($_POST["bindusertoreservation"])) {
            if ($userData = sql_fetch_array(sql_query("select * from felhasznalok where id=?", [$_POST["uid"]]))) {
                sql_query("update foglalasok set paciensid=?, cegid=?, nev=?, email=?, telefon=?, szuldatum=?, szulhely=?, anyjaneve=?, neme=?, munkakor=?, irsz=?, varos=?, utca=?, taj=? where id=? and pass=?",
                    [$userData["id"], $userData["cegid"], $userData["nev"], $userData["email"], $userData["telefon"], $userData["szuldatum"], $userData["szulhely"], $userData["anyjaneve"], $userData["neme"], $userData["munkakor"], $userData["irsz"], $userData["varos"], $userData["utca"], $userData["taj"], $_POST["fid"], $_POST["pp"]]);
            }
            die("ok");
        }

        if (isset($_REQUEST["newUserDataFromReservation"])) {
            $error = "";

            if (isset($_REQUEST["szuldatumev"])) {
                $_REQUEST["szuldatum"] = $_REQUEST["szuldatumev"] . "-" . substr("00" . $_REQUEST["szuldatumho"], -2) . "-" . substr("00" . $_REQUEST["szuldatumnap"], -2);
            }

            $_REQUEST["szuldatum"] = str_replace(".", "-", $_REQUEST["szuldatum"]);

            if (empty($_REQUEST["taj"]) || empty($_REQUEST["nev"]) || empty($_REQUEST["cegid"]) || !$this->validateDate($_REQUEST["szuldatum"], "Y-m-d")) {
                $error .= "A TAJ szám, a név, a születési dátum és a cég megadása kötelező a paciens adatlap létrehozásához!\n";
            }
            if (sql_fetch_array(sql_query("select id from felhasznalok where cegid=? and taj=?", [$_REQUEST["cegid"], $_REQUEST["taj"]]))) {
                $error.= "Már van paciens adatlap ezzel a taj számmal a kiválasztott cégnél. Használd a paciens adatlap keresés gombot.\n";
            }

            if (empty($error)) {
                sql_query("insert into felhasznalok set cegid=?, regtime=now(), nev=?, email=?, telefon=?, szuldatum=?, taj=?, irsz=?, varos=?, utca=?, munkakor=?, rkod=?, validated=1",
                    [$_REQUEST["cegid"], $_REQUEST["nev"], $_REQUEST["email"], $_REQUEST["telefon"], $_REQUEST["szuldatum"], $_REQUEST["taj"], $_REQUEST["irsz"], $_REQUEST["varos"], $_REQUEST["utca"], $_REQUEST["munkakor"], rand(11000, 98000)]);
                $uid = sql_insert_id();
                sql_query("update foglalasok set paciensid=? where id=? and pass=?", [$uid, $_REQUEST["fid"], $_REQUEST["p"]]);
            }

            $this->jsonOut(["error" => $error]);
        }


        if (isset($_REQUEST["tajrequest"])) {
            $utils = new Utils();

            if (!$adminUser->authenticated()) {
                $utils->jsonOut(["error" => "error"]);
            }

            $data = [];
            $data[] = ["id" => 1, "text" => "ez egy sor"];
            $data[] = ["id" => 2, "text" => "még egy sor"];
            $data[] = ["id" => 3, "text" => "harmadik sor"];
            $data[] = ["id" => 4, "text" => "tamás jános"];
            $data[] = ["id" => 5, "text" => "marha"];

            $utils->jsonOut($data);
            die();
        }

        if (isset($_POST["opensubmenu"])) {
            $id = intval($_POST["opensubmenu"]);

            if (!isset($_SESSION["opensubmenu"])) {
                $_SESSION["opensubmenu"] = [];
            }

            if (isset($_SESSION["opensubmenu"][$id])) {
                unset($_SESSION["opensubmenu"][$id]);
            }

            if ($_POST["open"] == 1) {
                $_SESSION["opensubmenu"][$id] = 1;
            }
            die;
        }

        if (isset($_POST["closebeotable"])) {
            $closed = intval($_POST["closebeotable"]);
            $index = intval($_POST["oid"])."_".intval($_POST["tid"]);

            if (!isset($_SESSION["closedbeotable"])) {
                $_SESSION["closedbeotable"] = [];
            }

            if (isset($_SESSION["closedbeotable"][$index])) {
                unset($_SESSION["closedbeotable"][$index]);
            }

            if ($closed == 1) {
                $_SESSION["closedbeotable"][$index] = 1;
            }
            die;
        }

        if (isset($_POST["toggleBeoCegSelector"])) {
            $id = intval($_POST["toggleBeoCegSelector"]);

            if (!isset($_SESSION["toggleBeoCegSelector"])) {
                $_SESSION["toggleBeoCegSelector"] = [];
            }

            if (isset($_SESSION["toggleBeoCegSelector"][$id])) {
                unset($_SESSION["toggleBeoCegSelector"][$id]);
            }

            if ($_POST["open"] == 1) {
                $_SESSION["toggleBeoCegSelector"][$id] = 1;
            }
            die;
        }

        if (isset($_POST["eljottcheckboxprotocol"])) {
            $id = intval($_POST["id"]);

            $data = ["confirm" => "", "html" => ""];
            if ($reservationData = sql_query("select * from foglalasok where id=?", [$id])->fetch(PDO::FETCH_ASSOC)) {

                if (Booking_Constants::COMPANY_NAME_SHORT != "Keltexmed") {
                    if ($reservationData["eljott"] == 0 && strtotime($reservationData["datum"]) < strtotime("now - 10 minute")) {
                        $data["confirm"] = "Már régi foglalás, biztos eljöttre állítod?";
                    }

                    if ($reservationData["eljott"] == 0 && strtotime($reservationData["datum"]) > strtotime("now + 10 minute")) {
                        $data["confirm"] = "Túl korán jelölöd eljöttre, biztos vagy benne?";
                    }
                }

                //if ($adminUser->user["username"] == "jns") {
                //    $data["confirm"] = "Már régi foglalás, biztos eljöttre állítod?";
                //}

                if ($data["confirm"] == "" || $_POST["force"] == 1) {
                    sql_query("update foglalasok set eljott=if(eljott=0, 1, 0) where id=? limit 1", [$id]);
                }

                $data["html"] = AdminBookingEditor::eljottCheckbox(sql_query("select * from foglalasok where id=?", [$id])->fetch(PDO::FETCH_ASSOC));
            }
            $this->jsonOut($data);
        }


        if (isset($_POST["duplicatereservation"])) {
            //die("funkció kikapcsolva");

            $num = 0;
            if ($reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=?", [$_POST["id"]]))) {

                $dayOfWeek = date("N", strtotime($reservationData["datum"]));

                for ($i = 1; $i < 1000; $i++) {
                    $date = date("Y-m-d H:i:s", strtotime("{$reservationData["datum"]} + ".($i*7)." day"));

                    if (sql_fetch_array(sql_query("select id from foglalasok where datum=? and orvosassigned=?", [$date, $reservationData["orvosassigned"]]))) {
                        continue;
                    }

                    $data = [
                        "parentid" => 0,
                        "paciensid" => 0,
                        "cegid" => $reservationData["cegid"],
                        "datum" => $date,
                        "rinterval" => $reservationData["rinterval"],
                        "telephely" => "",
                        "helyszin" => $reservationData["helyszinid"],
                        "szurestipus" => $reservationData["szurestipusid"],
                        "nev" => $reservationData["nev"],
                        "email" => $reservationData["email"],
                        "telefon" => $reservationData["telefon"],
                        "szuldatum" => "0000-00-00 00:00:00",
                        "szulhely" => "",
                        "anyjaneve" => "",
                        "neme" => 0,
                        "taj" => "",
                        "irsz" => "0000",
                        "varos" => "",
                        "utca" => "",
                        "megj" => $reservationData["megj"],
                        "munkakor" => "",
                        "tudoszuro" => 0,
                        "lang" => "hu",
                        "orvosid" => $reservationData["orvosassigned"],
                        "aktiv" => 1,
                        "rn" => rand(1000000, 9999999)];

                    $_REQUEST["rinterval"] = $reservationData["rinterval"]; //fix

                    $service = new BookingService();
                    $fid = $service->addReservationQuery($data);

                    //Foglaljorvost.hu-nak átküldés
                    $foService = new FoglaljOrvostService();
                    $foService->newReservation($fid);

                    $api = new BookingSyncApi();
                    $api->newReservation($fid);

                    $num++;
                    if ($num == 52) {
                        break;
                    }
                }
            }



            echo "Foglalás ismételve {$num} alkalommal";
            die;
        }

        if (isset($_GET["mailteszt"])) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);

            $service = new NotificationService();
            $service->tesztMessage();

            echo "mailteszt";
            die;
        }

        if(isset($_POST["beutaloHozzadasBox"])){
            $html = "";

            $service = new BookingService();

            $html .= "<div style='color:#444;text-align:center;'>";
            $html .= "<div id='loginbox' class='loginbox'>";
            $html .= "<div class='loginhead'>Beutaló hozzáadása</div>";

            $html .= "<div style='padding:20px;text-align:center;'>";
            $html .= "<div style=\"padding-bottom:3px\">Kérem, válasszon egy típust és egy telephelyet a legördülő listákból.</div>";
            $html .= "<div style=\"padding-bottom:3px\"><select id=\"beutaloSelector\">";
            foreach($service->availableDocs as $beutalo){
                $html .= "<option value=\"{$beutalo["value"]}\">{$beutalo["name"]} munkavégézés</option>";
            }
            $html .= "</select></div>";

            $html .= "<div><select id=\"telephelySelector\">";
            $html .= "<option value=\"Budapest\">Budapest</option>";
            $html .= "<option value=\"Szeged\">Szeged</option>";
            $html .= "</select></div>";
            $html .= "<div style=\"padding-top:10px;\"><input type=\"button\" onclick='beutalohozzadasafinish($(\"#beutaloSelector\").val(),$(\"#telephelySelector\").val(),{$_POST["beutaloHozzadasBox"]})' value=\"Kiválaszt\">";
            $html .= "<input onclick='hideGeneralPopup();return false;' type=\"button\" id=\"simplerefundclosebutton\" value=\"Bezárás\"></div>";

            $html .= "<div style='padding-top:10px;'><input onclick='hideGeneralPopup();return false;' type='button' id='simplerefundclosebutton' value='Bezárás' /></div>";
            $html .= "</div>";

            $html .= "</div>";
            $html .= "</div>";
            die($html);
        }

        if(isset($_POST["beutalohozzadasafinish"])){
            $service = new BookingService();
            $p = sql_fetch_array(sql_query("SELECT fogl.id as fid,fogl.nev,fogl.taj,fogl.szuldatum,fogl.munkakor,now() as kelte,sz.megnev as vizsgalat FROM foglalasok fogl LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid WHERE fogl.id=?",array($_POST["fid"])));
            $p["telephely"] = $_POST["tname"];

            echo $service->createReferalDoc($p,$_POST["bid"]);
            die();
        }
    }

    private function validateDate($date, $format="Y-m-d H:i:s"):bool {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format)==$date;
    }

    private function jsonOut($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        die();
    }

}