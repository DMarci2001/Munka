<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
            if (isset($_GET["rid"]) && isset($_GET["p"])) {
                $printService->setLaborRequest($_GET["rid"], $_GET["p"]);
            }
            $printService->start();
            die;
        }


        if (isset($_GET["hmmpackcopy"])) {
            $packs = sql_query("select * from hungariamed.synlab_labor_csomagok where id in (219, 220, 84, 96, 98, 100, 97, 114, 115, 12, 13, 121, 120, 156, 140, 142, 141, 155, 157, 243, 244, 242, 130, 129, 127, 128, 134, 136, 131, 133, 144, 160, 159, 161, 160, 193, 191, 192, 194, 65)")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($packs as $pack) {
                echo $pack["hmm_name"]."<br/>";

                sql_query("insert into keltexmed.synlab_labor_csomagok set
                 appform=?, name=?, hmm_name=?, alias=?, price=?, line_through_price=?, items=?, spektrumitems=?,  categories=?, gender=?, companies=?, description=?, preperation_description=?, aktiv=?, kiemelt=?",
                [$pack["appform"], $pack["name"], $pack["hmm_name"], $pack["alias"], $pack["price"], $pack["line_through_price"], $pack["items"], $pack["spektrumitems"], $pack["categories"], $pack["gender"], $pack["companies"], $pack["description"], $pack["preperation_description"], $pack["aktiv"], $pack["kiemelt"]]
                );

            }

            die("ok");
        }

        if (isset($_GET["keltexsync"])) {
            $service = new BookingSyncApi();

            $reservations = sql_query("SELECT * FROM foglalasok WHERE orvosassigned='427' AND datum>NOW() limit 100")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($reservations as $key => $reservation) {
                echo $key." ".$reservation["nev"]."<br/>";
                $service->newReservation($reservation["id"]);
            }
            die;
        }

        if (isset($_POST["storemainmenuwidth"])) {
            $width = intval($_POST["storemainmenuwidth"])."px";
            $_SESSION["mainmenuwidth"] = $width;
            die;
        }

        if (isset($_POST["setStyckyVarolista"])) {
            $_SESSION["setStyckyVarolista"] = $_POST["setStyckyVarolista"];
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
            if ($_GET["page"] == "klinikak" && $adminUser->statAccess()) {
                $_SESSION["tipusfilter"] = [];
                $_SESSION["klinikavarosfilter"] = 0;
                sql_query("insert into klinikak.klinikak set created=now(), megnev=''");
            }
            if ($_GET["page"] == "klinikak" && $adminUser->statAccess()) {
                $_SESSION["tipusfilter"] = [];
                sql_query("insert into klinikak.klinikak set created=now(), megnev=''");
            }
            if ($_GET["page"] == "contents" && $adminUser->beallitasWebAdatokAccess()) {
                sql_query("insert into hmmweb.q9a8m_content set catid=85, title='új tartalom', created=now(), publish_up=now(), state=1");
            }

            if ($_GET["page"] == "munkanaplo") {
                //error_reporting(E_ALL);
                //ini_set('display_errors', 1);

                $rn = rand(1000000,9999999);
                sql_query("insert into munkahigienes_felmeres set created=now(), datum=now(), munkaltato='', orvos=?, pecsetszam=?, rn=?", [$rn, $_SESSION["adminuser"]["nev"], $_SESSION["adminuser"]["pecsetszam"]]);
                //die;
            }

            if($_GET["page"] == "labortetelek"){
                sql_query("INSERT INTO synlab_labor_csomagok SET appform=1, NAME='_új üres csomag', price='-1', line_through_price='0', items='[]', spektrumitems = '[]', categories = '[]', gender='both', aktiv=1");
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
            $dokirexService = new DokirexService();

            error_reporting(E_ALL);
            ini_set('display_errors', 1);

            $required = ["Nev", "SzuletesiDatum", "Azonosito", "Nem", "Iranyitoszam", "Telepules", "Cim", "SzuletesiNev"];

            $params = $dokirexService->getUserParamsFromReservation($_POST["pid"]);
            $error = $dokirexService->checkUserParamErrors($params);

            if (empty($error)) {
                $response = $dokirexService->insertPaciensIntoDokirex($params);

                $reservationData = sql_query("select cegid from foglalasok where id=?", [$_POST["pid"]])->fetch(PDO::FETCH_ASSOC);
                if (Booking_Constants::SQL_DB == "hungariamed" && in_array($reservationData["cegid"], [111111,111112])) {
                    $_REQUEST["config"] = "keltexmed";
                    $dokirexServiceKeltexMed = new DokirexService();
                    $responseKeltexMed = $dokirexServiceKeltexMed->insertPaciensIntoDokirex($params);
                }
                if(isset($response["message"]) && $response["message"]!="OK"){
                    $html .= "<div style='color:#444;text-align:center;'>";
                    $html .= "<div id='loginbox' class='loginbox'>";
                    $html .= "<div class='loginhead'>Dokirex adatfeltöltés</div>";

                    $html .= "<div style='padding:20px;text-align:center;'>";
                    
                    $html .= "<div style='text-align:left'>{$response["message"]}</div>";

                    $html .= "<div style='padding-top:10px;'><input onclick='hideGeneralPopup();return false;' type='button' id='simplerefundclosebutton' value='Bezárás' /></div>";
                    $html .= "</div>";

                    $html .= "</div>";
                    $html .= "</div>";
                    die($html);
                }
            }

            $p = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?",array($_POST["pid"])));

            if($p["dokirex_userid"]!=0){
                //Munkakör rögzítése:
                if(Booking_Constants::SQL_DB == "keltexmed")   $FormElementID = 225;
                if(Booking_Constants::SQL_DB == "hungariamed") $FormElementID = 235;

                if($p["dokirexmunkakorid"]){
                    $params = array(
                        "FormElementID"=>$FormElementID,
                        "PaciensID"=>$p["dokirex_userid"],
                        "PaciensEgyediUrlapID"=> -1,
                        "Value"=> strval($p["dokirexmunkakorid"])
                    );
                    $dokirexService->insertUpdateFormElementValue($params);
                }

                //Cég rögzítése:
                if($p["dokirexcegid"]){

                    if(Booking_Constants::SQL_DB == "keltexmed")   $FormElementID = 224;
                    if(Booking_Constants::SQL_DB == "hungariamed") $FormElementID = 234;

                    $params = array(
                        "FormElementID"=>$FormElementID,
                        "PaciensID"=>$p["dokirex_userid"],
                        "PaciensEgyediUrlapID"=> -1,
                        "Value"=> strval($p["dokirexcegid"])
                    );
                    $dokirexService->insertUpdateFormElementValue($params);
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
                $warnings = sql_query("select * from warnings where checked=0 and created>date_sub(now(), interval 1 month) order by created")->fetchAll(PDO::FETCH_ASSOC);
                $numberOfWarnings = count($warnings);
                $return["number"] = $numberOfWarnings;
                if ($numberOfWarnings > 0) {
                    $return["button"] = "<span style='color:#fff;background:#f00;padding:2px 5px;cursor:pointer;border-radius: 3px;' onclick='toggleWarnWindow();'>{$numberOfWarnings} <i class='fa-solid fa-triangle-exclamation'></i></span>";

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

                if ($data["confirm"] == "" || $_POST["force"] == 1) {
                    sql_query("update foglalasok set eljott=if(eljott=0, 1, 0) where id=? limit 1", [$id]);
                    sql_query("update foglalasok set eljottidopont=now() where id=? AND eljott=1 AND eljottidopont='0000-00-00 00:00:00' limit 1", [$id]);

                    $eljottData = sql_query("select eljott, eljottidopont from foglalasok where id=?", [$id])->fetch(PDO::FETCH_ASSOC);

                    $service =  new BookingSyncApi();
                    $service->modifyReservation($id);

                    logActivity("eljott", $id, $eljottData["eljott"] == 1 ? "eljöttre állítva" : "nem eljöttre állítva");
                }

                $data["html"] = AdminBookingEditor::eljottCheckbox(sql_query("select * from foglalasok where id=?", [$id])->fetch(PDO::FETCH_ASSOC));
            }
            $this->jsonOut($data);
        }

        if (isset($_POST["behivvacheckboxprotocol"])) {
            $id = intval($_POST["id"]);

            $data = ["html" => ""];
            if ($reservationData = sql_query("select * from foglalasok where id=?", [$id])->fetch(PDO::FETCH_ASSOC)) {
                sql_query("update foglalasok set behivva=if(behivva=0, 1, 0) where id=? limit 1", [$id]);
                sql_query("update foglalasok set behivvaidopont=now() where id=? AND eljott=1 AND behivvaidopont='0000-00-00 00:00:00' limit 1", [$id]);

                $data = sql_query("select behivva, behivvaidopont from foglalasok where id=?", [$id])->fetch(PDO::FETCH_ASSOC);

                logActivity("behivva", $id, $data["behivva"] == 1 ? "behívottra állítva" : "nem behívottra állítva");

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

            $foglalas = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?",array($_POST["beutaloHozzadasBox"])));
            $service = new BookingService();

            $html .= "<div style='color:#444;text-align:center;'>";
            $html .= "<div id='loginbox' class='loginbox'>";
            $html .= "<div class='loginhead'>Beutaló hozzáadása</div>";

            $html .= "<div style='padding:20px;text-align:center;'>";
            if($foglalas["cegid"]==74){
                $html .= "<div style=\"padding-bottom:3px\">Kérem, válasszon egy típust és egy telephelyet a legördülő listákból.</div>";
            }
            if($foglalas["cegid"]==220){
                $html .= "<div style=\"padding-bottom:3px\">Kérem, válasszon egy munkakört a legördülő listákból.</div>";
            }
            $html .= "<div style=\"padding-bottom:3px\"><select id=\"beutaloSelector\">";
            $q=sql_query("SELECT * FROM kockazati_tenyezok WHERE cegid=220 ORDER BY munkakor ASC");
            while($r=sql_fetch_array($q)){
                $html .= "<option value=\"{$r["munkakor"]}\">{$r["munkakor"]}</option>";
            }
            $html .= "</select></div>";

            if($foglalas["cegid"]==74){
                $html .= "<div><select id=\"telephelySelector\">";
                $html .= "<option value=\"Budapest\">Budapest</option>";
                $html .= "<option value=\"Szeged\">Szeged</option>";
                $html .= "</select></div>";
            }
           
            $html .= "<div style=\"padding-top:10px;\"><input type=\"button\" onclick='beutalohozzadasafinish($(\"#beutaloSelector\").val(),{$_POST["beutaloHozzadasBox"]},$(\"#telephelySelector\").val())' value=\"Kiválaszt\">";
            $html .= "&nbsp;&nbsp;<input onclick='hideGeneralPopup();return false;' type=\"button\" id=\"simplerefundclosebutton\" value=\"Bezárás\"></div>";

            $html .= "</div>";

            $html .= "</div>";
            $html .= "</div>";
            die($html);
        }

        if(isset($_POST["beutalohozzadasafinish"])){
            $f=sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?",array($_POST["fid"])));
            $service = new BookingService();
            if($f["cegid"]==74){
                $p = sql_fetch_array(sql_query("SELECT fogl.id as fid,fogl.nev,fogl.taj,fogl.szuldatum,fogl.munkakor,now() as regdatum,sz.megnev as vizsgalat FROM foglalasok fogl LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid WHERE fogl.id=?",array($_POST["fid"])));
                $p["worklocation"] = $_POST["tname"];
            }

            if($f["cegid"]==220){
                $refQuery = sql_query("SELECT fogl.id AS fid,fogl.cegid,fogl.nev,fogl.szuldatum,fogl.taj,CONCAT(fogl.irsz,' ',fogl.varos,', ',fogl.utca) AS teljescim,fogl.regdatum,fogl.munkakor,sz.megnev AS vizsgalat,null as worklocation,felh.beutalo_megjegyzes FROM foglalasok fogl
                LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
                LEFT JOIN felhasznalok felh ON felh.taj=fogl.taj
                WHERE fogl.id=?",array($_POST["fid"]));
                if($referalData=sql_fetch_array($refQuery)){
                    $referalData["munkakor"] = $_POST["bid"];
                    echo $service->createReferalDoc($referalData,"fgsz-beutalo");
                    die();
                }
            }

            echo $service->createReferalDoc($p,$_POST["bid"]);
            die();
        }

        if (isset($_POST["checkChat"])) {
            $number = 0;
            $button = "";
            $usersButton = "";
            $usersData = ["html" => ""];

            $chatData = [];

            if ($adminUser->chatAccess()) {
                $chatData["notify"] = 0;
                $notifycations = sql_query("SELECT * FROM chatsessionlog WHERE userid=? AND notified=0 AND tipus='unread'", [$adminUser->user["id"]])->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($notifycations)) {
                    $chatService = new ChatService($adminUser);
                    foreach ($notifycations as $notifycation) {
                        sql_query("update chatsessionusers set active=1 where userid=? and sessionid=?", [$notifycation["userid"], $notifycation["sessionid"]]);
                    }
                    sql_query("update chatsessionlog set notified=1 where userid=? and tipus='unread' and notified=0", [$adminUser->user["id"]]);
                    $chatData["notify"] = 1;
                    $chatData["notifyMessage"] = count($notifycations)." új üzenet érkezett";
                    $chatData["sessionlist"] = $chatService->getSessionListHTML($adminUser->user["id"]);
                }
            }

            if (!empty($adminUser->user)) {
                $usersData = $this->getActiveUsers($adminUser);
                if (!empty($usersData["html"])) {
                    $usersButton = "<span style='color:#fff;background:#33cc33;padding:2px 5px;cursor:pointer;border-radius: 3px;' onclick='toggleUsersWindow();' title='Bejelentkezés adatok'> {$usersData["count"]}&nbsp;<i class='fa-solid fa-user'></i></span>";
                }
            }

            $this->jsonOut(["number" => $number, "button" => $button, "users" => "", "usersbutton" => $usersButton, "usershtml" => $usersData["html"], "chatData" => $chatData, "logged" => isset($adminUser->user["id"])]);
        }

        if (isset($_POST["showeljottlog"])) {
            if (empty($adminUser->user)) {
                die;
            }

            $logItems = sql_query("select l.*, u.username from activitylog l left join users u on u.id=l.userid where l.mid=? and l.tipus in ('eljott','behivva') order by datum", [$_POST["fid"]])->fetchAll(PDO::FETCH_ASSOC);
            if (empty($logItems)) {
                echo "Még senki nem jelölte eljöttre.";
            }

            foreach ($logItems as $logItem) {
                echo "<div>{$logItem["datum"]} {$logItem["username"]} {$logItem["megnev"]}</div>";
            }
            die;
        }

        if (isset($_REQUEST["getmunkakorlist"])) {
            $apiv2 = new DokirexService();
            die(json_encode($apiv2->sqlListMunkakor($_REQUEST["q"]),JSON_PRETTY_PRINT));
        }

        if (isset($_REQUEST["getceglist"])) {
            $apiv2 = new DokirexService();
            die(json_encode($apiv2->sqlListTelephely($_REQUEST["q"]),JSON_PRETTY_PRINT));
        }
        if(isset($_REQUEST["initCeglistSelect2"])){
            die($adminUtils->ceglista(null,$_POST["cegid"]));
        }

        if (isset($_REQUEST["showimageeditor"])) {
            $docId = intval($_REQUEST["docId"]);
            $dataId = intval($_REQUEST["dataId"]);
            $message = $html = "";

            $docAgent = new DocAgent();
            if ($imageData = sql_query("select * from dokumentumok where id=?", [$docId])->fetch(PDO::FETCH_ASSOC)) {
                $imagePath = $docAgent->getAssetImageURL($imageData, true);
                $originalImagePath = str_replace($imageData["assetid"], $imageData["assetid"]."_original", $imagePath);
                if (!is_file($originalImagePath)) {
                    $message = "A vágás használatához a képet újra fel kell tölteni.";
                }
            } else {
                $message = "A kép betöltése nem lehetséges!";
            }


            $html.= "<div style='background:#eee;border:10px solid white;'>";

            $html.= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
            $html.= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class='fa-solid fa-print'></i>&nbsp;&nbsp;Kép kivágás</div>";
            $html.= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
            $html.= "</div>";

            $html.= "<div style='padding:10px;'>";
            $html.= "<div style='min-height:210px;max-height:700px;max-widht:900px;overflow: auto;'>";

            $html.= "<img id='imagetoedit' style='display: block;max-width:100%;width:1000px;height:700px;' src='{$_REQUEST["imageURL"]}' />";
            $html.= "</div>";

            $html.= "<div style='margin-top:10px;' id='buttonscontainer'>";
            $html.= "<a data-dataid='{$dataId}' data-id='{$docId}' onclick='saveCroppedImage(this);return false;' class='printbutton' target='_blank' href='#'>Mentés</a> ";
            $html.= "<a class='printbutton' onclick='hideGeneralPopup();return false;' href='#'>Bezárás</a> ";
            $html.= "</div>";

            $html.= "</div>";

            $html.= "</div>";

            $this->jsonOut(["message" => $message, "html" => $html]);
        }

        if (isset($_REQUEST["saveCroppedImage"])) {
            $docId = intval($_REQUEST["docId"]);
            $imageCropX = intval($_REQUEST["imageCropX"]);
            $imageCropY = intval($_REQUEST["imageCropY"]);
            $imageCropWidth = intval($_REQUEST["imageCropWidth"]);
            $imageCropHeight = intval($_REQUEST["imageCropHeight"]);

            $message = "";

            $docAgent = new DocAgent();
            if ($imageData = sql_query("select * from dokumentumok where id=?", [$docId])->fetch(PDO::FETCH_ASSOC)) {
                $imagePath = $docAgent->getAssetImageURL($imageData, true);
                $originalImagePath = str_replace($imageData["assetid"], $imageData["assetid"]."_original", $imagePath);
                $execute = "convert {$originalImagePath} -crop {$imageCropWidth}x{$imageCropHeight}+{$imageCropX}+{$imageCropY} -quality 70 {$imagePath}";
                //$message.= $execute;
                `{$execute}`;
            }

            $this->jsonOut(["message" => $message]);
        }


        if (isset($_REQUEST["keltexmedstatok"])) {
            $munkakorokContent = file_get_contents(__DIR__."/stathoz_munkakorok.csv");
            $rows = explode("\n", $munkakorokContent);
            $munkakorMap = [];
            foreach ($rows as $key => $row) {
                if ($key == 0) {
                    continue;
                }
                $fields = explode(";", $row);

                //echo $fields[0]."<br>";
                $munkakorMap[$fields[0]] = $fields[1];
            }



            $statContent = file_get_contents(__DIR__."/stathoz.csv");

            $rows = explode("\n", $statContent);

            $korcsoportok = ["0-20" => 0, "21-30" => 0, "31-40" => 0, "41-50" => 0, "51-60" => 0, "61-70" => 0, "71-200" => 0];
            $bmicsoportok = ["0-18.5" => 0, "18.5-24.9" => 0, "25-29.9" => 0, "30-34.9" => 0, "35-39.9" => 0, "40-200" => 0];
            $vernyomascsoportok = ["0-129" => 0, "130-999" => 0];

            $korok = [];
            $bmik = [];
            $vernyomasok = [];
            $pulzusok = [];
            $korlatozasok = [];
            $szemuveggel = 0;
            $kontaktlencsevel = 0;
            $munkakorok = [];
            $munkakorFizikai = 0;
            $munkakorSzellemi = 0;
            $munkakorVegyes = 0;
            $munkakorNincs = 0;


            foreach ($rows as $key => $row) {
                if ($key == 0) {
                    continue;
                }

                $fields = explode(";", $row);

                $kor = date("Y", strtotime("now")) - date("Y", strtotime(str_replace(".", "-", $fields[2])));
                $korok[] = $kor;
                if (!empty($fields[3])) {
                    $bmi = $fields[3];
                    foreach ($bmicsoportok as $key => $val) {
                        $minMax = explode("-", $key);
                        $min = $minMax[0];
                        $max = $minMax[1];
                        if ($bmi >= $min && $bmi <= $max) {
                            $bmicsoportok[$key]++;
                            break;
                        }
                    }
                    $bmik[] = str_replace(",", ".", $bmi);
                }
                if (!empty($fields[4])) {
                    $vernyomas = intval($fields[4]);
                    foreach ($vernyomascsoportok as $key => $val) {
                        $minMax = explode("-", $key);
                        $min = $minMax[0];
                        $max = $minMax[1];
                        if ($bmi >= $min && $bmi <= $max) {
                            $vernyomascsoportok[$key]++;
                            break;
                        }
                    }
                    $vernyomasok[] = $vernyomas;
                }
                if (!empty($fields[5])) {
                    $pulzusok[] = $fields[5];
                }
                if (!empty($fields[6])) {
                    $korlatozasok[] = $fields[6];
                }
                if (!empty($fields[8])) {
                    $munkakorok[] = $fields[8];
                }

                if (substr_count(strtolower($fields[6]), "szemüveg")) {
                    $szemuveggel++;
                }
                if (substr_count(strtolower($fields[6]), "kontakt")) {
                    $kontaktlencsevel++;
                }
                //echo $fields[0]." ".$kor." ";


                foreach ($korcsoportok as $key => $val) {
                    $minMax = explode("-", $key);
                    $min = $minMax[0];
                    $max = $minMax[1];
                    if ($kor >= $min && $kor <= $max) {
                        $korcsoportok[$key]++;
                        break;
                    }
                }

                $munkakor = trim($fields[8]);
                if (isset($munkakorMap[$munkakor])) {
                    $m = trim($munkakorMap[$munkakor]);
                    if ($m == "0") {
                        $munkakorNulla++;
                    }
                    if ($m == "1") {
                        $munkakorFizikai++;
                    }
                    if ($m == "2") {
                        $munkakorSzellemi++;
                    }
                    if ($m == "3") {
                        $munkakorVegyes++;
                    }
                } else {
                    $munkakorNincs++;
                }

            }

            echo "korcsoportok:<br/>";
            echo "<pre>".print_r($korcsoportok, true)."</pre>";

            echo "bmi csoportok:<br/>";
            echo "<pre>".print_r($bmicsoportok, true)."</pre>";

            echo "vérnyomás csoportok:<br/>";
            echo "<pre>".print_r($vernyomascsoportok, true)."</pre>";

            echo "szemüveggel:<br/>";
            echo "<pre>".$szemuveggel."</pre>";

            echo "kontakt lencsével:<br/>";
            echo "<pre>".$kontaktlencsevel."</pre>";


            //$munkakorok = array_unique($munkakorok);

            echo "<pre>fizikai: {$munkakorFizikai}</pre>";
            echo "<pre>szellemi: {$munkakorSzellemi}</pre>";
            echo "<pre>vegyes: {$munkakorVegyes}</pre>";
            echo "<pre>nulla: {$munkakorNulla}</pre>";
            echo "<pre>nincs megadva munkakör: {$munkakorNincs}</pre>";



            


            //echo count($munkakorok). " ";
            //print_r($munkakorok);
            //echo $statContent;
            die;
        }

        if(isset($_POST["showTelephelyHelyszinValaszto"])){
            $telephelyid=$_POST["showTelephelyHelyszinValaszto"];
            $telephely=sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE id=?",[$telephelyid]));
            if($telephely["parentid"]==0) die();
            $utils = new Utils();
            die($utils->showTelephelyHelyszinValaszto($telephely));
        }

        if(isset($_POST["showTelephelySzurestipusValaszto"])){
         
            $telephelyid=$_POST["showTelephelySzurestipusValaszto"];
            $telephely=sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE id=?",[$telephelyid]));
            if($telephely["parentid"]==0) die();
            $utils = new Utils();
            die($utils->showTelephelySzurestipusValaszto($telephely));
        }

        if(isset($_POST["selectTelephelyHelyszin"])){
            $telephelyid=$_POST["telephelyid"];
            $helyszinid=$_POST["helyszinid"];
            $telephely=sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE id=?",[$telephelyid]));
            if($telephely["parentid"]==0) die();
            $helyszinek = json_decode($telephely["placeids"]);
            $index = array_search($helyszinid,$helyszinek);

            //Ha már benne van akkor törlöm
            if($index!==false){
                unset($helyszinek[$index]);
                $helyszinek = array_values($helyszinek);
                sql_query("UPDATE cegvars SET placeids=? WHERE id=? AND cegid=?",[json_encode($helyszinek,true),$telephely["id"],$telephely["cegid"]]);
            }else{
                //Ha nincs, akkor házzadom :)
                $helyszinek[] = $helyszinid;
                sql_query("UPDATE cegvars SET placeids=? WHERE id=? AND cegid=?",[json_encode($helyszinek,true),$telephely["id"],$telephely["cegid"]]);
            }
            $utils = new Utils();
            $telephely=sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE id=?",[$telephelyid]));
            die(json_encode(
                array(
                    "selector"=>$utils->showTelephelyHelyszinValaszto($telephely),
                    "button"=>$utils->showTelephelyHelyszinek($telephely)
                )));
        }

        if(isset($_POST["selectTelephelySzurestipus"])){
            $telephelyid=$_POST["telephelyid"];
            $szurestipusid=$_POST["szurestipusid"];
            $telephely=sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE id=?",[$telephelyid]));
            if($telephely["parentid"]==0) die();
            $szuresek = json_decode($telephely["szurestipusids"]);
            $index = array_search($szurestipusid,$szuresek);

            //Ha már benne van akkor törlöm
            if($index!==false){
                unset($szuresek[$index]);
                $szuresek = array_values($szuresek);
                sql_query("UPDATE cegvars SET szurestipusids=? WHERE id=? AND cegid=?",[json_encode($szuresek,true),$telephely["id"],$telephely["cegid"]]);
            }else{
                //Ha nincs, akkor házzadom :)
                $szuresek[] = $szurestipusid;
                sql_query("UPDATE cegvars SET szurestipusids=? WHERE id=? AND cegid=?",[json_encode($szuresek,true),$telephely["id"],$telephely["cegid"]]);
            }
            $utils = new Utils();
            $telephely=sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE id=?",[$telephelyid]));

            die(json_encode(
                array(
                    "selector"=>$utils->showTelephelySzurestipusValaszto($telephely),
                    "button"=> $utils->showSzurestipusok($telephely)
                )));
        }

        if(isset($_POST["setTelephelyDokireId"])){
            /*echo "<pre>";
            print_r($_POST);
            echo "</pre>";*/
            if(empty($_POST["dokirexcegid"])) $_POST["dokirexcegid"] = null;
            sql_query("UPDATE cegvars SET dokirexcegid=? WHERE id=?",[$_POST["dokirexcegid"],$_POST["telephelyid"]]);
            die();
        }

        if(isset($_POST["setQuitter"])){
            if($q=sql_query("SELECT * FROM felhasznalok WHERE id=?",[$_POST["setQuitter"]])->fetch(PDO::FETCH_ASSOC)){
                if(empty($q["kilepett"])){
                    sql_query("UPDATE felhasznalok SET kilepett=1 WHERE id=?",[$_POST["setQuitter"]]);
                }else{
                    sql_query("UPDATE felhasznalok SET kilepett=NULL WHERE id=?",[$_POST["setQuitter"]]);
                }
                $q=sql_query("SELECT * FROM felhasznalok WHERE id=?",[$_POST["setQuitter"]])->fetch(PDO::FETCH_ASSOC);
            }
            die();
        }

        if(isset($_POST["showGeneraliDocSetup"])){
            $utils = New Utils();
            die(json_encode(array("html"=>$utils->showGeneraliSetup($_POST["showGeneraliDocSetup"]),"error"=>"")));
        }

        if(isset($_POST["saveGeneraliDoctorData"])){
            $generaliService = new GeneraliApiService();
            echo "<pre>";
            print_r($_POST);
            echo "</pre>";

            $q=sql_query("SELECT * FROM orvosok WHERE id=?",[$_POST["oid"]])->fetch(PDO::FETCH_ASSOC);

            if(empty($q["generaliId"])){
                $generaliService->storeDoctor($_POST["oid"],$_POST["name"],$_POST["titles"],$_POST["min_age"],$_POST["languages"]);
                sql_query("UPDATE orvosok SET generaliId=? WHERE id=?",[$_POST["oid"],$_POST["oid"]]);
            }else{
                $generaliService->updateDoctor($q["generaliId"],$_POST["name"],$_POST["titles"],$_POST["min_age"],$_POST["languages"]);
            }

            
            echo "<pre>";
            print_r($generaliService->retrieveDoctors());
            echo "</pre>";
        }

        if(isset($_POST["storeGeneraliScreening"])){
            $error = $success = "";
            $q=sql_query("SELECT * FROM szurestipusok WHERE id=?;",[$_POST["storeGeneraliScreening"]])->fetch(PDO::FETCH_ASSOC);
            if(!empty($q["generaliId"])){
                $error = "Szűréstípus már hozzá lett adva a Generali rendszeréhez!<br>";
            }else{
                $generaliService = New GeneraliApiService();
                $generaliService->storeSpecialities($_POST["storeGeneraliScreening"],$_POST["generaliid"]);
                sql_query("UPDATE szurestipusok SET generaliId=? WHERE id=?",[$_POST["storeGeneraliScreening"],$_POST["storeGeneraliScreening"]]);
                $success = "Sikeres rögzítés!";
            }
            die(json_encode(array("error"=>$error,"message"=>$success)));
        }

        if(isset($_POST["refresGeneralihExaminations"])){
            $generaliService = New GeneraliApiService();
            $html = "";
            if($_POST["refresGeneralihExaminations"]!="0"){
                echo $_POST["refresGeneralihExaminations"];
                $examinations = $generaliService->retrieveExaminationsOfSpeciality($_POST["refresGeneralihExaminations"]);
                $html.= "<option>Válassz vizsgálatot!</option>";
                foreach($examinations as $examination){
                    if(!empty($examination["partner_examination_id"])){
                        $html.= "<option value='{$examination["partner_examination_id"]}'>{$examination["name"]}</option>";
                    }    
                }
            }
            
            die($html);
        }

        if(isset($_POST["setExaminationOfSpeciality"])){
            $generaliService = New GeneraliApiService();
            $generaliService->storeExamination($_POST["szid"],$_POST["eid"]);
            die();
        }

        new LaborKeroService();
        new InvoiceService();
    }

    private function getActiveUsers(AdminUser $adminUser):array {
        $result = ["count" => 0];
        $html = "";

        if (!empty($adminUser->user)) {
            sql_query("update users set lastlogin=now() where id=?", [$adminUser->user["id"]]);
            if ($adminUser->beallitasTevekenysegnaploAccess()) {
                $users = sql_query("select nev, username from users where lastlogin>date_sub(now(), interval 1 minute) order by username")->fetchAll(PDO::FETCH_ASSOC);

                foreach ($users as $user) {
                    $html .= "<div>{$user["username"]}</div>";
                    $result["count"]++;
                }
            }
            if (!empty($html)) {
                $html = "<div style='font-weight: bold;margin-bottom: 5px;'>Bejelentkezve:</div></b>{$html}";
            }

            if ($adminUser->beallitasWebAdatokAccess()) {
                $data = sql_query_common("select valuetext from sitedata where tipus='serverdata' order by datum desc limit 1")->fetch();
                $serverData = json_decode($data["valuetext"], true);

                foreach ($serverData as $server) {
                    $html.="<div style='border-top:1px dashed white;padding-top:10px;margin-top:10px;font-weight: bold;'>{$server["name"]}</div>";

                    $load = Utils::getBetween($server["proc"], "average:", ",");

                    $html.= "<div style='display:table;width:100%;'>";
                    $html.= $this->statusDataRow("Server load:", "", "", trim($load[1]));

                    foreach (explode("\n", $server["hdd"]) as $hddRow) {
                        $hddRow = preg_replace('!\s+!', ' ', $hddRow);
                        $hddParts = explode(" ", $hddRow);
                        if (substr_count($hddParts[0], "/dev")) {
                            $html.= $this->statusDataRow($hddParts[0], $hddParts[1], $hddParts[3], $hddParts[4]);
                        }
                    }

                    $html.= "</div>";
                }
            }

        }

        $result["html"] = $html;

        return $result;
    }

    private function statusDataRow($title, $data1, $data2, $data3):string {
        return "<div style='display:table-row;'><div style='display:table-cell;'>{$title}</div><div style='display:table-cell;text-align: right;'>&nbsp;&nbsp;{$data1}</div><div style='display:table-cell;text-align: right;'>&nbsp;&nbsp;{$data2}</div><div style='display:table-cell;text-align: right;'>&nbsp;&nbsp;{$data3}</div></div>";
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


    private array $csvUsers = [
      0 => [
          "orvosid" => 1411,
          "helyszinid" => 1198,
          "cegid" => 1468,
          "tipusid" => 48,
          "rinterval" => 2,
          "users" => "1.;8:00;Dr. Mona Gyula;Gyömrő, Fogarasi u. 2/b;030 106 251;30/6190421
2.;8:02;Dékány Boglárka;Deák F. 28.;123101763;30/6384770
3.;8:04;Ivanics Józsefné;Rét u. 22.;075 063 230 ;30/4243551
4.;8:06;Nyáriné Szücsi Anita;Deák F. u. 41.;106 867 310;30/1490292
5.;8:08;Petényi Tamás;Arany J. u. 13.;107031921;70/7799715
6.;8:10;Szántó Tamás;Deák F. u. 71;034 568 947;20/2845810
7.;8:12;Mikulik Tiborné;Locsodi u. 14.;073 191 449;30/4789075
8.;8:14;Dékányné Sziráki Mariann;Deák F. 28.;082 491 554;30/6384770
9.;8:16;Baglyas Rita;Bem tér 2.;084 368 979;20/5793440
10.;8:18;Gyarmati Beatrix;Üllő, Vadvirág u. 21/a;078 302 747;20/5332494
11.;8:20;Mészárosné Sziráki Anikó;Honvéd u. 2/a.;086 897 769;30/7737857
12.;8:22;Hutóczki Kerti Annamária;Meredek u. 8.;082 685 412;30/657 6589
13.;8:24;Hutóczki Gábor;Meredek u. 8.;038 556 296;30/657 6589
14.;8:26;Bartáné Varró Ágnes;Dolina köz 4.;075 265 496;30/1267931
15.;8:28;Barta Péter;Dolina köz 4.;114 908 575;30/1267931
16.;8:30;Daragics Piroska;Temető u. 4.;094 407 019;30/4535994
17.;8:32;Billinger László;Dózsa Gy. Út 94.;043 505 595;30/2637229
18.;8:34;;;;
19.;8:36;Krajczárné Apró Ildikó;Szövetkezeti u. 1/a.;078 983 685;20/3730299
20.;8:38;Prekop Lászlóné;Dózsa Gy. Út 41.;061 040 234;0629/747981
21.;8:40;Nagy Rita;Dózsa Gy. Út 212.;084 659 091;
22.;8:42;Földi Ágnes;Szövetkezeti u. 4.;079 801 652;70/6352550
23.;8:44;Somodi Hajnalka;Honvéd u. 114.;087 789 663;70/7765458
24.;8:46;Gagán József;Szövetkezeti u. 12/a;016 503 238;20/2539277
25.;8:48;;;;
26.;8:50;Mosonyi Ferencné;Nap u. 25.;073 003 236;30/3836437
27.;8:52;;;;
28.;8:54;Kopcsai Renáta;Rét u. 12/b B1;125 714 064;30/5086063
29.;8:56;;;;
30.;8:58;;;;
31.;9:00;Márton Attila;Kossuth L. u. 17.;037 523 640;30/5445957
32.;9:02;;;;
33.;9:04;Ács Józsefné;Locsodi u. 19.;060 673 051;20/4271753
34.;9:06;;;;
35.;9:08;Zsga Imre;Szabadság u. 11/a;107 243 135;30/6581523
36.;9:10;hanyóné Simontornyai Renáta;Bacsó B. u. 4.;087 145 726;70/3959888
37.;9:12;;;;
38.;9:14;;;;
39.;9:16;Jakab Sándorné;Arany J. u. 4.;066 516 660;20/3522165
40.;9:18;;;;
41.;9:20;Homa Bianka;Dobó I. u. 35.;121 370 648;20/3133430
42.;9:22;Cserna Antal;2230 Gyömrő, Csokonai .u. 7;117 135 363;20/9840658
43.;9:24;;;;
44.;9:26;Gremann Béláné;Dobó I. u. 31;063 397 541;70/4169728
45.;9:28;;;;
46.;9:30;Joó Anett;Dózsa Gy. Út 110.;092 287 745;30/8970133
47.;9:32;;;;
48.;9:34;;;;
49.;9:36;Viasz Beatrix;Dobó I. u. 3.;085 316 265;30/6906520
50.;9:38;;;;
51.;9:40;Szalkainé Pribula Ágnes;Szabadság u. 5/a;079 818 496;70/9023852
52.;9:42;;;;
53.;9:44;;;;
54.;9:46;;;;
55.;9:48;Huszák József;Tulipán u. 3.;039 322 274;30/5914870
56.;9:50;Huszák Józsefné;Tulipán u. 3.;072 196 960;30/5914870
57.;9:52;;;;
58.;9:54;;;;
59.;9:56;Boda Szilvia;Dózsa gy. Út 198.;089 739 657;20/5185973
60.;9:58;Boda Tibor;Dózsa gy. Út 198.;019 058 029;20/5185973
61.;10:00;;;;
62.;10:02;;;;
63.;10:04;;;;
64.;10:06;;;;
65.;10:08;Varga Károly;Locsodi u. 28.;025 157 997;20/9864269
66.;10:10;Takács Máté;Péceli u. 9.;111 801 374;
67.;10:12;;;;
68.;10:14;Szilágyi Jázmin;Hársfa u.;126 121 517;
69.;10:16;;;;
70.;10:18;(Molnár?) nincs adat, de biztonságból itt hagyom.;;;
71.;10:20;Bányai Ferenc;Tavasz u. 11.;125 487 326;20/5921114
72.;10:22;;;;
73.;10:24;Kormosné Bori Éva;Rét u. 11.;074 837 627;20/3546898
74.;10:26;;;;
75.;10:28;Tremml-Kurczné Mladoniczki Judit;Szövetkezeti u. 7/a;105 418 067;30/5704558
76.;10:30;;;;
77.;10:32;Mosonyi-Bárány Anna;Jókai u. 47.;092 453 663;30/4458195
78.;10:34;;;;
79.;10:36;;;;
80.;10:38;Nyerges Jánosné;Dózsa Gy. Út 93.;072 744 224;20/5252863
81.;10:40;;;;
82.;10:42;Papp Gábor;Nap u. 18.;024 668 129;
83.;10:44;;;;
84.;10:46;Kruger Lászlóné;Nyár u. 4.;076 023 389;30/5600504
85.;10:48;;;;
86.;10:50;Rózsa Rita;Árpád V. út 26.;072 901 861;30/8989377
87.;10:52;;;;
88.;10:54;Szabóné Fülöp Éva;Petőfi u. 41.;074 674 019;70/6114184
89.;10:56;;;;
90.;10:58;;;;
91.;11:00;;;;
92.;11:02;Kelemen Sándorné;Árpád V. út 19.;069 681 253;20/3764830
93.;11:04;;;;
94.;11:06;;;;
95.;11:08;;;;
96.;11:10;;;;
97.;11:12;Pénzes Károlyné;Ady E. u. 13.;068 758 879;30/5610545
98.;11:14;;;;
99.;11:16;;;;
100.;11:18;;;;
101.;11:20;Gerhard Alexander;Csalogány u. 19/3;091 544 984;30/7266089
102.;11:22;;;;
103.;11:24;;;;
104.;11:26;;;;
105.;11:28;;;;
106.;11:30;Dúró Józsefné;Meredek u. 1.;059 940 368;20/2076323
107.;11:32;;;;
108.;11:34;;;;
109.;11:36;Bajári Dorottya;Toldi u. 1.;082 495 590;30/3069143
110.;11:38;;;;
111.;11:40;;;;
112.;11:42;Sós Győző Rezső;Bem tér 12.;094 732 007;20/2374076
113.;11:44;;;;
114.;11:46;;;;
115.;11:48;;;;
116.;11:50;Molnár Tibor;Rét u. 14.;039 196 822;30/3180880
117.;11:52;;;;
118.;11:54;Molnár Tiborné;Rét u. 14.;071 935 865;30/3180880
119.;11:56;;;;
120.;11:58;;;;
121.;12:00;;;;
122.;12:02;;;;
123.;12:04;Rékási Mihályné;Dózsa Gy. Út 98.;069 072 743;30/4106551
124.;12:06;;;;
125.;12:08;;;;
126.;12:10;Jamrik István;Bacsó B. u. 6.;103 334 062;0629/438168
127.;12:12;;;;
128.;12:14;;;;
129.;12:16;Grabecz Balázs;Legelő u. 8/b;034 367 483;30/9447796
130.;12:18;;;;
131.;12:20;Bálint Gábor;Hunyadi u. 4.;019 514 206;30/9722180
132.;12:22;;;;
133.;12:24;;;;
134.;12:26;;;;
135.;12:28;;;;
136.;12:30;Szakács Emese;Honvéd u. 22.;119 230 842;70/3682978
137.;12:32;;;;
138.;12:34;Újvári László;Dózsa Gy. Út 180/a;033 650 186;20/3430115
139.;12:36;;;;
140.;12:38;;;;
141.;12:40;Juhász Krisztina;Zrínyi u. 40/a;081 576 131;30/5019061
142.;12:42;;;;
143.;12:44;Csala Zoltánné;Iskola u. 4.;075 032 980;70/6780110
144.;12:46;;;;
145.;12:48;Cseh Sándorné;Csigási u. 25.;077 498 678;70/7901206
146.;12:50;;;;
147.;12:52;;;;
148.;12:54;;;;
149.;12:56;Mészárosné Gárdosi Gabriella;Fő u. 44.;107 957 647;20/3817125
150.;12:58;Mészáros Csenge;Fő u. 44.;117 732 546;20/3817125
151.;13:00;;;;
152.;13:02;;;;
153.;13:04;;;;
154.;13:06;;;;
155.;13:08;;;;
156.;13:10;Toronicza Rózsa;Rét u. 21.;080 420 385;20/8030788
157.;13:12;;;;
158.;13:14;;;;
159.;13:16;;;;
160.;13:18;;;;
161.;13:20;;;;
162.;13:22;Molnár Klára;Kossuth L. u. 44.;072 437 207;20/3604801
163.;13:24;;;;
164.;13:26;;;;
165.;13:28;;;;
166.;13:30;;;;
167.;13:32;;;;
168.;13:34;;;;
169.;13:36;Gülaydin-Kovács Kitti;Fő u. 6.;089 528 079;20/9260745
170.;13:38;Mehmet Can Gülaydin;Fő u. 6.;131 161 803;20/9260745
171.;13:40;Kovácsné dr. Dóczi Ildikó;Fő u. 6.;073 640 112;20/9260745
172.;13:42;Lilik Ágnes;Rét u. 4.;085 901 087;0629/438390
173.;13:44;Csikós Jánosné;Rét u. 4.;065 010 660;0629/438390
174.;13:46;;;;
175.;13:48;;;;
176.;13:50;Buktáné Tóth Ágnes;Dózsa Gy. Út 16.;073 463 933;30/3199965
177.;13:52;;;;
178.;13:54;;;;
179.;13:56;Urbán Károlyné;Rét u. 25.;073 809 337;20/3287651
180.;13:58;;;;
181.;14:00;Lekrinszki Helga;Szabadság u. 2.;073 261 526;30/8663721
182.;14:02;;;;
183.;14:04;;;;
184.;14:06;;;;
185.;14:08;;;;
186.;14:10;;;;
187.;14:12;;;;
188.;14:14;;;;
189.;14:16;;;;
190.;14:18;;;;
191.;14:20;;;;
192.;14:22;;;;
193.;14:24;;;;
194.;14:26;;;;
195.;14:28;;;;
196.;14:30;;;;
197.;14:32;;;;
198.;14:34;;;;
199.;14:36;;;;
200.;14:38;;;;
201.;14:40;;;;
202.;14:42;;;;
203.;14:44;;;;
204.;14:46;;;;
205.;14:48;;;;
206.;14:50;;;;
207.;14:52;;;;
208.;14:54;;;;
209.;14:56;;;;
210.;14:58;;;;
211.;15:00;;;;
212.;15:02;;;;
213.;15:04;;;;
214.;15:06;;;;
215.;15:08;;;;
216.;15:10;;;;
217.;15:12;;;;
218.;15:14;;;;
219.;15:16;;;;
220.;15:18;;;;
221.;15:20;;;;
222.;15:22;;;;
223.;15:24;;;;
224.;15:26;;;;
225.;15:28;;;;
226.;15:30;;;;
227.;15:32;;;;
228.;15:34;;;;
229.;15:36;;;;
230.;15:38;;;;
231.;15:40;;;;
232.;15:42;;;;
233.;15:44;;;;
234.;15:46;;;;
235.;15:48;;;;
236.;15:50;;;;
237.;15:52;;;;
238.;15:54;;;;
239.;15:56;;;;
240.;15:58;;;;
241.;16:00;;;;
242.;16:02;;;;
243.;16:04;;;;
244.;16:06;;;;
245.;16:08;;;;
246.;16:10;;;;
247.;16:12;;;;
248.;16:14;;;;
249.;16:16;;;;
250.;16:18;;;;"
      ],
    1 => [
          "orvosid" => 1501,
          "helyszinid" => 1198,
          "cegid" => 1468,
          "tipusid" => 48,
          "rinterval" => 2,
          "users" => "1.;8:00;Forgács Melitta;Szabadság u. 2/a;082 859 219;30/2637229
2.;8:02;Kovács Richárd;Új-Élet u. 27.;114076098;30/6767047
3.;8:04;Labadics István;Jókai u. 29.;026 666 138;30/2637229
4.;8:06;Nyári Antal;Deák F. u. 41.;035 518 873;30/1490292
5.;8:08;Petényiné Király Tímea;Arany J. u. 13.;110540368;70/7799715
6.;8:10;Szántóné Horváth Melinda;Deák F. u. 71.;084 690 856;20/2845810
7.;8:12;Takács Bence;Deák F. 28.;118398785;30/6384770
8.;8:14;Flámis Lászlóné;Bem tér 2.;072 084 629;20/5793440
9.;8:16;Kmetty Szilvia;Dózsa Gy. Út 113.;106 841 020;30/3386710
10.;8:18;Homa Istvánné;Dobó I. u. 35.;121 370 648;20/9840658
11.;8:20;Mikulik Dörgő Fruzsina;Dörgő major 1.;092 734 919;20/3110041
12.;8:22;Nagyné Gutai Melinda;Ady E. u 3.;084 158 453;30/2036240
13.;8:24;Paskóné Bán Brigitta;Tavasz u. 4.;087 426 041;70/9670231
14.;8:26;Bakosné Gupcsó Mariann;Dózsa Gy. Út 59.;089 410 042;20/9503625
15.;8:28;Csutorka Enikő;T.szentmárton, Vasvárí Pál u. 11.;115 588 213;30/1267931
16.;8:30;Billinger Lászlóné;Toldi 8.;074 470 581;70/3163103
17.;8:32;Juhász István;Sülysáp, Gárdonyi u. 27.;027 689 200;30/9064756
18.;8:34;;;;
19.;8:36;Endrődi Lili;Bajcsy-Zs. u. 6.;122 701 683;30/5089739
20.;8:38;Endrődiné Veres Ágnes;Bajcsy-Zs. u. 6.;081 707 973;30/5089739
21.;8:40;Ferencz Krisztián;Dózsa Gy. Út 212.;040 007 540;20/9907258
22.;8:42;Virág Róbert;Szövetkezeti u. 4.;028 244 552;70/6352550
23.;8:44;Tóthné Somodi Szidónia;Honvéd u. 112.;081 773 756;70/7765458
24.;8:46;Cseriné Bosánszki Gyöngyi;Rét u. 11.;082 465 278;30/4650461
25.;8:48;;;;
26.;8:50;Korsoveczki Andrea;Szegfű u. 2.;078 391 929;20/4110341
27.;8:52;;;;
28.;8:54;Mucska Krisztián;Rét u. 12 B1;032 061 301;30/5086063
29.;8:56;;;;
30.;8:58;;;;
31.;9:00;Barna Gabriella;Kossuth L. u. 17.;084 531 607;30/5445757
32.;9:02;;;;
33.;9:04;Viasz Istvánné;Dobó I. u. 3.;071 847 786;30/1906520
34.;9:06;;;;
35.;9:08;Zsiga Urbán Alexandra;Szabadság u. 11/a;091 568 641;30/6581523
36.;9:10;Lengyel Anikó;Csalogány u. 3.;075 752 868;30/3653875
37.;9:12;;;;
38.;9:14;Nagy Gáborné;Sz.mártonkáta Nefelejcs u. 18.;083 374 768;20/3929405
39.;9:16;;;;
40.;9:18;;;;
41.;9:20;Juhász Istvánné;Sülysáp, Gárdonyi u. 27.;078 432 262;30/5663973
42.;9:22;;;;
43.;9:24;;;;
44.;9:26;Gremann Béla;Dobó I. u. 31.;015 082 024;70/4169728
45.;9:28;;;;
46.;9:30;Joó Gábor;Dózsa Gy. Út 110.;038 540 057;30/8970133
47.;9:32;;;;
48.;9:34;;;;
49.;9:36;;;;
50.;9:38;;;;
51.;9:40;Tőrös Patrícia;Dózsa Gy. Út 207.;115 067 590;20/4526040
52.;9:42;Szalkai Róbert;Szabadság u. 5/a;030 252 705;70/9023852
53.;9:44;;;;
54.;9:46;;;;
55.;9:48;Huszák-Zima Aliz;Tulipán u. 3.;121 639 365;30/5914870
56.;9:50;;;;
57.;9:52;Póka Katalin;Tavasz u. 11.;121 432 239;20/5535849
58.;9:54;;;;
59.;9:56;Kőrösi Gergő;Dózsa Gy. Út 198.;042 671 121;20/5185973
60.;9:58;;;;
61.;10:00;;;;
62.;10:02;Kohajda Mihályné;Szent I. út 15.;063 062 357;0629/439-231
63.;10:04;;;;
64.;10:06;;;;
65.;10:08;Wágner Mihály;Bajcsy-Zs u. 12.;015 392 509;0620/3738266
66.;10:10;Takácsné Kókai Fruzsina;Péceli u. 9.;115 161 227;
67.;10:12;;;;
68.;10:14;Balogh Veronika;Hársfa u.;083 282 052;30/9900270
69.;10:16;;;;
70.;10:18;;;;
71.;10:20;Bányai Andrea;Tavasz u. 11.;120 087 512;20/5921114
72.;10:22;;;;
73.;10:24;Bakos Gábor;Dózsa Gy. Út 59.;031 625 997;20/9503625
74.;10:26;;;;
75.;10:28;Tremml-Kurcz Ágost Máté;Szövetkezeti 7/a;117 563 933;30/5704558
76.;10:30;Harkály Ida;Oszlári u. 8/b;075 985 257;30/5260007
77.;10:32;;;;
78.;10:34;Pászti Ildikó;Alkotmány u. 6.;084 781 084;70/3899114
79.;10:36;;;;
80.;10:38;;;;
81.;10:40;;;;
82.;10:42;Pappné Borbás Erzsébet;Nap u. 18.;072 322 941;20/5689446
83.;10:44;;;;
84.;10:46;;;;
85.;10:48;;;;
86.;10:50;Zólyomi Ildikó;Toldi köz 4.;076 571 189;70/3871780
87.;10:52;;;;
88.;10:54;Szabó Gyula;Petőfi u. 41.;024 775 575;70/6114184
89.;10:56;;;;
90.;10:58;Rádi Istvánné;Honvéd u. 7/a;068 716 622;20/2309289
91.;11:00;;;;
92.;11:02;Heüsz Zoltánné;Honvéd u. 7/a;083 642 845;20/2309289
93.;11:04;;;;
94.;11:06;;;;
95.;11:08;;;;
96.;11:10;Verseczki Tiborné;Jókai u. 35.;073 625 393;70/4509886
97.;11:12;Verseczki Eszter;Csigási u. 3.;107 647 399;70/4509886
98.;11:14;;;;
99.;11:16;;;;
100.;11:18;;;;
101.;11:20;Horváth Tamás;Csalogány u. 19/3.;043 080 348;30/7266089
102.;11:22;;;;
103.;11:24;Trepák Ferencné;Bajcsy-Zs. U. 54.;104 379 608;0629/266318
104.;11:26;;;;
105.;11:28;;;;
106.;11:30;Remecz Gáborné;Zrínyi u. 18.;087 441 169;70/4150312
107.;11:32;;;;
108.;11:34;;;;
109.;11:36;Varga Zoltán;Toldi u. 1.;029 506 455;30/3069143
110.;11:38;;;;
111.;11:40;;;;
112.;11:42;Sós Andrea;Bem tér 12.;079 725 501;20/2374076
113.;11:44;;;;
114.;11:46;;;;
115.;11:48;Vitéz Tünde;Dobó I. u. 33.;085 510 188;20/3582088
116.;11:50;;;;
117.;11:52;;;;
118.;11:54;Jámbor László;Rét u. 14.;021 376 269;30/3180880
119.;11:56;;;;
120.;11:58;;;;
121.;12:00;;;;
122.;12:02;;;;
123.;12:04;Zelei Miklós;Dózsa Gy. út 1.;031 654 205;30/3752604
124.;12:06;Zelei Supara;Dózsa Gy. út 1.;127 158 996;30/3752604
125.;12:08;Zelei Klára;Dózsa Gy. út 1.;076 291 270;30/3752604
126.;12:10;Szabolcs Attila;Dózsa Gy. út 1.;026 570 280;30/3752604
127.;12:12;;;;
128.;12:14;;;;
129.;12:16;Grabecz Melinda;Legelő u. 8/b;084 694 225;30/9447796
130.;12:18;;;;
131.;12:20;Bálint Gáborné;Hunyadi u. 4.;072 600 887;30/9722180
132.;12:22;Vágó Gábor;Rákóczi u. 9.;013 295 019;20/9371248
133.;12:24;;;;
134.;12:26;Fabók Ferenc;Jókai u. 47/a;030 093 432;30/9112942
135.;12:28;;;;
136.;12:30;;;;
137.;12:32;;;;
138.;12:34;Rostásné Varga Zsuzsanna;Dózsa Gy. Út 180/a;075 170 332;20/3430115
139.;12:36;;;;
140.;12:38;;;;
141.;12:40;;;;
142.;12:42;;;;
143.;12:44;Csala Zoltán;Iskola u. 4.;027 103 546;70/6780110
144.;12:46;;;;
145.;12:48;Palaczki Ferencné;Szent I. u. 5.;064 248 491;70/7901206
146.;12:50;;;;
147.;12:52;;;;
148.;12:54;;;;
149.;12:56;;;;
150.;12:58;Horváth Gyöngyi;Kossuth L. u. 11.;070 012 831;30/9527875
151.;13:00;;;;
152.;13:02;;;;
153.;13:04;;;;
154.;13:06;;;;
155.;13:08;;;;
156.;13:10;Toronicza Róbert;Rét u. 21.;125 866 455;20/8030788
157.;13:12;;;;
158.;13:14;;;;
159.;13:16;;;;
160.;13:18;Győrfi Levente;Bercsényi u. 2.;128 125 865;30/2568726
161.;13:20;;;;
162.;13:22;;;;
163.;13:24;;;;
164.;13:26;;;;
165.;13:28;;;;
166.;13:30;;;;
167.;13:32;;;;
168.;13:34;;;;
169.;13:36;;;;
170.;13:38;;;;
171.;13:40;;;;
172.;13:42;;;;
173.;13:44;;;;
174.;13:46;;;;
175.;13:48;;;;
176.;13:50;;;;
177.;13:52;;;;
178.;13:54;;;;
179.;13:56;Urbán Károly;Rét u. 25.;021 918 432;20/3287651
180.;13:58;;;;
181.;14:00;;;;
182.;14:02;;;;
183.;14:04;;;;
184.;14:06;;;;
185.;14:08;;;;
186.;14:10;;;;
187.;14:12;;;;
188.;14:14;;;;
189.;14:16;;;;
190.;14:18;;;;
191.;14:20;;;;
192.;14:22;;;;
193.;14:24;;;;
194.;14:26;;;;
195.;14:28;;;;
196.;14:30;;;;
197.;14:32;;;;
198.;14:34;;;;
199.;14:36;;;;
200.;14:38;;;;
201.;14:40;;;;
202.;14:42;;;;
203.;14:44;;;;
204.;14:46;;;;
205.;14:48;;;;
206.;14:50;Csorba József;Jókai u. 67.;024 000 934;70/5628718
207.;14:52;Romfa Attiláné;Jókai u. 67.;075 755 584;70/5628718
208.;14:54;;;;
209.;14:56;;;;
210.;14:58;;;;
211.;15:00;Nagy Dalma;Locsodi u. 22.;092 912 494;70/2621566
212.;15:02;;;;
213.;15:04;;;;
214.;15:06;;;;
215.;15:08;;;;"
      ],
        2 => [
            "orvosid" => 1498,
            "helyszinid" => 1198,
            "cegid" => 1468,
            "tipusid" => 164,
            "rinterval" => 6,
            "users" => "1.;8:00;Mikulik Tiborné;Locsodi u. 14.;073 191 449;30/4789075
2.;8:06;Barta Péter;Dolina köz 4.;114 908 575;30/1267931
3.;8:12;Ivanics Józsefné;Rét u. 2.;075 063 230;30/4243551
4.;8:18;Petényiné Király Tímea;Arany J. u. 13.;110 540 368;70/7799715
5.;8:24;Csutorka Enikő;T.szentmárton, Vasvárí Pál u. 11.;115 588 213;30/1267931
6.;8:30;Bakosné Gupcsó Marianna;Dózsa Gy. Út 59.;089 410 042;20/9503625
7.;8:36;Szántó Tamás;Deák F. u. 71.;034 568 947;20/2845810
8.;8:42;Flámis Lászlóné;Bem tér 2.;072 084 629;20/5793440
9.;8:48;Billinger Lászlóné;Toldi 8.;074 470 581;70/3163103
10.;8:54;Beleznay Dravicza;Dózsa Gy. Út 44.;074 834 767;70/3260781
11.;9:00;Lilikné Kövics Valéria;Bajcsy-Zs. U. 36.;060 652 663;30/4554691
12.;9:06;;;;
13.;9:12;Barna Gabriella;Kossuth L. u. 17.;084 531 607;30/5445757
14.;9:18;Joó Gábor;Dózsa Gy. 110.;038 540 057;30/8970133
15.;9:24;;;;
16.;9:30;Nyári Antal;Deák F. u. 41.;035 518 873;30/1490292
17.;9:36;Juhász Istvánné;Sülysáp Gárdonyi G. u. 27.;027 689 200;30/9064756
18.;9:42;;;;
19.;9:48;Viasz Beatrix;Dobó I. u. 3.;085 316 265;30/6906520
20.;9:54;Szalkai Róbert;;;
21.;10:00;Virág Róbert;Szövetkezeti u. 4.;028 244 552;70/6352550
22.;10:06;Somodi Hajnalka;Honvéd u. 114.;087 789 663;70/7765458
23.;10:12;Kőrösi Gergő;Dózsa Gy. Út 198.;042 671 121;20/5185973
24.;10:18;;;;
25.;10:24;Balogh Veronika;Hársfa u.;083 282 052;30/9900270
26.;10:30;Kopcsai Renáta;Rét u. 12/b B1;125 714 064;
27.;10:36;;;;
28.;10:42;Viasz Istvánné;Dobó I. u. 3.;071 847 786;30/1906520
29.;10:48;Lengyel Anikó;Csalogány u. 3.;075 752 868;30/3653875
30.;10:54;;;;
31.;11:00;Harkály Ida;Oszlári u. 2/b;075 985 257;30/5260007
32.;11:06;;;;
33.;11:12;Szántó Mihály;Gárdonyi G. u. 58.;019 132 138;20/3952099
34.;11:18;;;;
35.;11:24;Gremann Béláné;Dobó I. u. 31;063 397 541;70/4169728
36.;11:30;Gerhard Alexandra;Csalogány u. 19/3;091 544 984;30/7266089
37.;11:36;;;;
38.;11:42;Huszák József;Tulipán u. 3.;039 322 274;30/5914870
39.;11:48;Huszák Józsefné;Tulipán u. 3.;072 196 960;30/5914870
40.;11:54;;;;
41.;12:00;Molnár Tiborné;Rét u. 14.;071 935 865;30/3180880
42.;12:06;;;;
43.;12:12;Jámbor László;Rét u. 14.;021 376 269;30/3180880
44.;12:18;Veres Lászlóné;Nap u. 28.;068 624 033;30/5089739
45.;12:24;;;;
46.;12:30;Pénzes Károlyné;Ady E. u. 13.;068 758 879;30/5610545
47.;12:36;Bálint Gábor;Hunyadi u. 4.;019 514 206;30/9722180
48.;12:42;Kmetty Szilvia;Dózsa Gy. Út 113.;106 841 020;30/3386710
49.;12:48;Szakács Emese;Honvéd u. 22.;119 230 842;70/3682978
50.;12:54;;;;
51.;13:00;;;;
52.;13:06;Foltan Ilona;Dózsa Gy. Út 170/b;075 219 750;70/6071503
53.;13:12;;;;
54.;13:18;;;;
55.;13:24;Kormosné Bori Éva;Rét u. 11.;074 837 627;20/3546898
56.;13:30;;;;
57.;13:36;Rádi Istvánné;Honvéd u. 7/a.;068 716 622;20/2309289
58.;13:42;Heüsz Zoltánné;Honvéd u. 7/a.;083 642 845;20/2309289
59.;13:48;;;;
60.;13:54;Csikós Jánosné;Rét u. 4.;065 010 660;0629/438-390
61.;14:00;Horváth Istvánné;Deák F. u. 48.;067 705 887;0629/438-465
62.;14:06;;;;
63.;14:12;Sós Andrea;Bem tér 12.;079 725 501;20/2374076
64.;14:18;;;;
65.;14:24;Lekrinszki Helga;Szabadság u. 2.;073 261 526;30/8337721
66.;14:30;;;;
67.;14:36;Verseczki Tiborné;Jókai u. 35.;073 625 393;70/4509886
68.;14:42;Verseczki Eszter;Csigási u. 3.;107 647 399;70/4509886
69.;14:48;;;;
70.;14:54;;;;
71.;15:00;Velkei Sándorné;Tulipán u. 7.;062 896 838;0629/438-183
72.;15:06;;;;
73.;15:12;;;;
74.;15:18;Farkas István Józsefné;Dobó I. u. 13.;059 900 689;50/1275645
75.;15:24;;;;
76.;15:30;;;;
77.;15:36;Szabóné Fülöp Éva;Petőfi u. 41.;074 674 019;70/6114184
78.;15:42;;;;
79.;15:48;;;;"
        ],
        3 => [
            "orvosid" => 1502,
            "helyszinid" => 1198,
            "cegid" => 1468,
            "tipusid" => 164,
            "rinterval" => 6,
            "users" => "1.;8:00;Bartáné Varró Ágnes;Dolina köz 4.;075 265 496;30/1267931
2.;8:06;Dr. Mona Gyula;Gyömrő, Fogarasi u. 2/b;030 106 251;30/6190421
3.;8:12;Labadics István;Jókai u. 29.;026 666 138;30/2637229
4.;8:18;Petényi Tamás;Arany J. u. 13.;107 031 921;70/7799715
5.;8:24;Dékányné Sziráki Mariann;Deák F. 28.;082 491 554;30/6384770
6.;8:30;Forgács Melitta;Szabadság u. 2/a;082 859 219;30/2637229
7.;8:36;Szántóné Horváth Melinda;Deák F. u. 71;084 690 856;20/2845810
8.;8:42;Baglyas Rita;Bem tér 2.;084 368 979;20/5793440
9.;8:48;Billinger László;Dózsa Gy. Út 94.;043 505 595;30/2637229
10.;8:54;Homa Jánosné;Legelő u. 2.;077 232 674;30/9720579
11.;9:00;Cseriné Bosánszki Gyöngyi;Rét u. 11.;082 465 278;30/4650461
12.;9:06;;;;
13.;9:12;Márton Attila;Kossuth L. u. 17.;037 523 640;30/5445757
14.;9:18;Joó Anett;Dózsa Gy. 110.;092 287 745;30/8970133
15.;9:24;;;;
16.;9:30;Nyáriné Szücsi Anita;Deák F. u. 41.;106 867 310;30/1490292
17.;9:36;Póka Katalin;Tavasz u. 11.;121 432 238;20/5535849
18.;9:42;;;;
19.;9:48;;;;
20.;9:54;Szalkainé Pribula Ágnes;Szabadság u. 5/a.;079 818 496;70/9023852
21.;10:00;Földi Ágnes;Szövetkezeti u. 4.;079 801 652;70/6352550
22.;10:06;Tóthné Somodi Szidónia;Honvéd u. 112.;081 773 756;70/7765458
23.;10:12;;;;
24.;10:18;Korsoveczki Andrea;Szegfű u. 2.;078 391 929;20/4110341
25.;10:24;;;;
26.;10:30;Mucska Krisztián;Rét u. 12 B1;032 061 301;30/5086063
27.;10:36;;;;
28.;10:42;Ács Józsefné;Locsodi u. 19.;060 673 051;
29.;10:48;Zsiga Urbán Alexandra;Szabadság u. 11/a;091 568 641;30/6581523
30.;10:54;Zsga Imre;Szabadság u. 11/a;107 243 135;30/6581523
31.;11:00;Pászti Ildikó;Alkotmány u. 6.;084 781 084;70/3899114
32.;11:06;Rózsa Rita;Árpád V. út 26.;072 901 861;30/8989377
33.;11:12;Kelemen Sándorné;Árpád V. út 19.;069 681 253;20/3764830
34.;11:18;;;;
35.;11:24;Gremann Béla;Dobó I. u. 31.;015 082 024;70/4169728
36.;11:30;Horváth Tamás;Csalogány u. 19/3.;043 080 348;30/7266089
37.;11:36;;;;
38.;11:42;Huszák-Zima Aliz;Tulipán u. 3.;121 639 365;30/5914870
39.;11:48;Dúró Józsefné;Meredek u. 1.;059 940 368;20/2076323
40.;11:54;;;;
41.;12:00;Krajczárné Apró Ildikó;Szövetkezeti u. 1/a.;078 983 685;20/3730299
42.;12:06;;;;
43.;12:12;Molnár Tibor;Rét u. 14.;039 196 822;30/3180880
44.;12:18;;;;
45.;12:24;Zólyomi Ildikó;Toldi köz 4.;076 571 189;70/3871780
46.;12:30;;;;
47.;12:36;Bálint Gáborné;Hunyadi u. 4.;072 600 887;30/9722180
48.;12:42;Remecz Gáborné;Zrínyi u. 18.;087 441 169;70/4150312
49.;12:48;Pappné Borbás Erzsébet;Nap u. 18.;072 322 941;20/5689446
50.;12:54;Papp Gábor;Nap u. 18.;024 668 129;20/5689446
51.;13:00;;;;
52.;13:06;Fogl István;Dózsa Gy. Út 170/b;025 630 206;70/6071503
53.;13:12;;;;
54.;13:18;Györfi Renáta;Zrínyi u. 2.;125 091 848;30/2568726
55.;13:24;;;;
56.;13:30;Labadics Józsefné;Báthory u. 19.;057 818 685;30/2637229
57.;13:36;Labadicsné Nyerges Zsófia;Szövetkezeti u. 2.;077 045 227;30/2637229
58.;13:42;Labadics Réka;Szövetkezeti u. 2.;118 450 234;30/2637229
59.;13:48;Labadics József ;Szövetkezeti u. 2.;024 876 579;30/2637229
60.;13:54;;;;
61.;14:00;Buktáné Tóth Ágnes;Dózsa Gy. Út 16.;073 463 933;30/3199965
62.;14:06;;;;
63.;14:12;Sós Győző Rezső;Bem tér 12.;094 732 007;20/2374076
64.;14:18;Zelnik Jánosné;Nap u. 9.;067 713 491;20/8248567
65.;14:24;;;;
66.;14:30;Turbék Ottóné;Csigási u. 23.;060 516 105;30/7296198
67.;14:36;;;;
68.;14:42;;;;
69.;14:48;Vitéz Tünde;Dobó I. u. 33.;085 510 188;20/3582088
70.;14:54;;;;
71.;15:00;Csorba József;Jókai u. 67.;024 000 934;70/5628718
72.;15:06;Romfalvi Attiláné;Jókai u. 67.;075 755 584;70/5628718
73.;15:12;;;;
74.;15:18;;;;
75.;15:24;;;;
76.;15:30;;;;
77.;15:36;Szabó Gyula;Petőfi u. 41.;024 775 575;70/6114184
78.;15:42;;;;
79.;15:48;;;;
80.;15:54;;;;"
        ],
        4 => [
            "orvosid" => 1504,
            "helyszinid" => 1198,
            "cegid" => 1468,
            "tipusid" => 14,
            "rinterval" => 10,
            "users" => "1.;8:00;Dékányné Sziráki Mariann;Deák F. 28.;082 491 554;30/6384770
2.;8:10;Faragó Mátyás;Szabadság u. 2/a.;125045281;30/2637229
3.;8:20;Dr. Mona Gyula;Gyömrő, Fogarasi u. 2/b;030 106 251;30/6190421
4.;8:30;Takács Bence;Deák F. 28.;118398785;30/6384770
5.;8:40;Dékány Boglárka;Deák F. 28.;123101763;30/6384770
6.;8:50;Petényi Tamás;Arany J. u. 13.;107031921;70/7799715
7.;9:00;Nyári Antal;Deák F. u. 41.;035 518 873;30/1490292
8.;9:10;Nyáriné Szücsi Anita;Deák F. u. 41.;106 867 310;30/1490292
9.;9:20;Labadics István;Jókai u. 29.;026 666 138;30/2637229
10.;9:30;Petényiné Király Tímea;Arany J. u. 13.;110540368;70/7799715
11.;9:40;Joó Anett;Dózsa Gy. 110.;092 287 745;30/8970133
12.;9:50;Joó Gábor;Dózsa Gy. 110.;038 540 057;30/8970133
13.;10:00;Barna Gabriella;Kossuth L. u. 17.;084 531 607;30/5445757
14.;10:10;Márton Attila;Kossuth L. u. 17.;037 523 640;30/5445757
15.;10:20;Takácsné Kókai Fruzsina;Péceli u. 9.;115 161 227;
16.;10:30;Takács Máté;Péceli u. 9.;111 801 374;
17.;10:40;Ivanics Józsefné;Rét u. 22.;075 063 230;30/4243551
18.;10:50;Szántóné Horváth Melinda;Deák F. u. 71.;084 690 856;20/2845810
19.;11:00;Szántó Tamás;Deák F. u. 71.;034 568 947;20/2845810
20.;11:10;Flámis Lászlóné;Bem tér 2.;072 084 629;20/5793440
21.;11:20;Baglyas Rita;Bem tér 2.;084 368 979;20/5793440
22.;11:30;Karikás Maja;Bajcsy-Zs. u. 41.;086 632 865;20/4223081
23.;11:40;Fülöp Zsolt;Bajcsy-Zs. u. 41.;036 768 679;20/4223081
24.;11:50;Mikulik Tiborné;Locsodi u. 14.;073 191 449;30/4789075
25.;12:00;fabókné Tabányi Mariann;Jókai u. 47/a;080 778 301;20/2461813
26.;12:10;Béres Korinna;Új-Élet u. 27.;114 409 010;30/6767047
27.;12:20;Baráth Erika;Új-Élet u. 27.;077 784 991;30/6767047
28.;12:30;Endrődiné Veres Ágnes;Bajcsy-Zs. u. 6.;081 707 973;30/5089739
29.;12:40;Endrődi Lili;Bajcsy-Zs. u. 6.;122 701 683;30/5089739
30.;12:50;Balázs Nóra;Toldi köz 5.;085 886 711;30/5089739
31.;13:00;Kmetty Szilvia;Dózsa Gy. Út 113.;106 841 020;30/3386710
32.;13:10;Homa Istvánné;Dobó I. u. 35.;121 370 648;20/9840658
33.;13:20;Mészárosné Sziráki Anikó;Honvéd u. 2/a.;086 897 769;30/7737857
34.;13:30;Hutóczki Kerti Annamária;Meredek u. 8.;082 685 412;30/657 6589
35.;13:40;Hutóczki Gábor;Meredek u. 8.;038 556 296;30/657 6589
36.;13:50;Paskóné Bán Brigitta;Tavasz u. 4.;087 426 041;70/9670231
37.;14:00;Kovácsné dr. Dóczi Ildikó;Fő u. 6.;073 640 112;20/9260745
38.;14:10;Mehmet Can Gülaydin;Fő u. 6.;131 161 803;20/9260745
39.;14:20;Gülaydin-Kovács Kitti;Fő u. 6.;089 528 079;20/9260745
40.;14:30;Bakosné Gupcsó Mariann;Dózsa Gy. Út 59.;089 410 042;20/9503625
41.;14:40;Bartáné Varró Ágnes;Dolina köz 4.;075 265 496;30/1267931
42.;14:50;Barta Péter;Dolina köz 4.;114 908 575;30/1267931
43.;15:00;Csutorka Enikő;T.szentmárton, Vasvárí Pál u. 11.;115 588 213;30/1267931
44.;15:10;Billinger Lászlóné;Toldi 8.;074 470 581;70/3163103
45.;15:20;Bányai Andrea;Tavasz u. 11.;120 087 512;20/5921114
46.;15:30;Billinger László;Dózsa Gy. Út 94.;043 505 595;30/2637229
47.;15:40;Labadicsné Nyerges Zsófia;Szövetkezeti u. 2.;077 045 227;30/2637229
48.;15:50;Labadics Réka;Szövetkezeti u. 2.;118 450 234;30/2637229
49.;16:00;Beleznay Dravicza;Dózsa Gy. Út 44.;074 834 767;70/3260781
50.;16:10;Homa Jánosné;Legelő u. 2.;077 232 674;30/9720579"
        ],
        5 => [
            "orvosid" => 1460,
            "helyszinid" => 1198,
            "cegid" => 1468,
            "tipusid" => 15,
            "rinterval" => 10,
            "users" => "1.;8:00;Takács Bence;Deák F. u. 28.;118 398 785;30/6384770
2.;8:10;Dékány Boglárka;Deák F. u. 28.;123 101 763;30/6384770
3.;8:20;Forgács Melitta;Szabadság u. 2/a;082 859 219;30/2637229
4.;8:30;Nyári Antal;Deák F. u. 41.;035 518 873;30/1490292
5.;8:40;Nyáriné Szücsi Anita;Deák F. u. 41.;106 837 310;30/1490292
6.;8:50;Dékányné Sziráki Mariann;Deák F. u. 28.;082 491 554;30/6384770
7.;9:00;Labadics István;Jókai u. 29.;026 666 138;30/2637229
8.;9:10;Petényiné Király Tímea;Arany J. u. 13.;110 540 368;70/7799715
9.;9:20;Ivanics Józsefné;Rét u. 22.;075 063 230;30/4243551
10.;9:30;Szántóné Horváth Melinda;Deák F. u. 71;084 690 856;20/2845810
11.;9:40;Szántó Tamás;Deák F. u. 71;034 568 947;20/2845810
12.;9:50;Tőrös Patrícia;Dózsa Gy. Út 207.;115 067 590;20/4526040
13.;10:00;Joó Anett;Dózsa Gy. Út 110.;092 287 745;30/8970133
14.;10:10;Joó Gábor;Dózsa Gy. Út 110.;038 540 057;30/8970133
15.;10:20;Barna Gabriella;Kossuth L. u. 17.;084 531 607;30/5445957
16.;10:30;Márton Attila;Kossuth L. u. 17.;037 523 640;30/5445957
17.;10:40;Flámis Lászlóné;Bem tér 2.;072 084 629;20/5793440
18.;10:50;Baglyas Rita;Bem tér 2.;084 368 979;20/5793440
19.;11:00;Szántó Tamás;Gárdonyi G. u. 58.;019 132 138;20/3952099
20.;11:10;Mikulik Tiborné;Locsodi u. 14.;073 191 449;30/4789075
21.;11:20;Fabókné Tabányi Mariann;Jókai u. 47/a;080 778 301;20/2461813
22.;11:30;Mikulik Dörgő Fruzsina;Dörgő major 1.;092 734 919;20/3110041
23.;11:40;Nagyné Gutai Melinda;Ady E. u 3.;084 158 453;30/2036240
24.;11:50;Hutóczki Kerti Annamária;Meredek u. 8.;082 685 412;30/657 6589
25.;12:00;Hutóczki Gábor;Meredek u. 8.;038 556 296;30/657 6589
26.;12:10;Daragics Piroska;Temető u.4.;094 407 019;30/4535994
27.;12:20;Földi Ágnes;Szövetkezeti u. 4.;079 801 652;70/6352550
28.;12:30;Veres Lászlóné;Nap u. 28.;068 624 033;30/5089739
29.;12:40;Virág Róbert;Szövetkezeti u. 4.;028 244 552;70/6352550
30.;12:50;Kmetty Szilvia;Dózsa Gy. Út 113.;106 841 020;30/3386710
31.;13:00;Homa Istvánné;Dobó I. u. 35.;121 370 648;20/9840658
32.;13:10;Labadics Józsefné;Báthory u. 19.;057 818 685;30/2637229
33.;13:20;Foltan Ilona;Dózsa Gy. Út 170/b;075 219 750;70/6071503
34.;13:30;Fogl István;Dózsa Gy. Út 170/b;025 630 206;70/6071503
35.;13:40;Somodi Hajnalka;Honvéd u. 114.;087 789 663;70/7765458
36.;13:50;Korsoveczki Andrea;Szegfű u. 2.;078 391 929;20/4110341
37.;14:00;Bakosné Gupcsó Mariann;Dózsa Gy. Út 59.;089 410 042;20/9503625
38.;14:10;Gülaydin-Kovács Kitti;Fő u. 6.;089 528 079;20/9260745
39.;14:20;Mehmet Can Gülaydin;Fő u. 6.;131 161 803;20/9260745
40.;14:30;Bartáné Varró Ágnes;Dolina köz 4.;075 265 496;30/1267931
41.;14:40;Barta Péter;Dolina köz 4.;114 908 575;30/1267931
42.;14:50;Csutorka Enikő;T.szentmárton, Vasvárí Pál u. 11.;115 588 213;30/1267931
43.;15:00;Billinger Lászlóné;Toldi 8.;074 470 581;70/3163103
44.;15:10;Kopcsai Renáta;Rét u. 12/b B1;125 714 064;30/5086063
45.;15:20;Viasz Istvánné;Dobó I. u. 3.;071 847 786;30/1906520
46.;15:30;Ács Józsefné;Locsodi u. 19.;060 673 051;
47.;15:40;Bányai Andrea;Tavasz u. 11.;120 087 512;20/5921114
48.;15:50;Zsiga Urbán Alexandra;Szabadság u. 11/a;091 568 641;30/6581523
49.;16:00;Gecser Istvánné;Báthory u. 24.;077 230 780;30/3662183
50.;16:10;Gremann Béláné;Dobó I. u. 31;063 397 541;70/4169728"
        ],
    ];

    public function addCsvUsers() {
        sql_query("delete from foglalasok where cegid=1468");

        foreach ($this->csvUsers as $csvUser) {
            $users = explode("\n", $csvUser["users"]);
            foreach ($users as $userRow) {
                $userField = explode(";", $userRow);
                $datum = "2025-10-04 ".substr("0".$userField[1], -5).":00";
                $nev = trim($userField[2]);
                $taj = str_replace(" ", "", $userField[4]);
                $utca = $userField[3];
                $telefon = $userField[5];


                echo "{$datum} {$nev} {$taj} {$utca} {$telefon}\n";


                if (empty($nev)) {
                    continue;
                }

                sql_query("INSERT INTO foglalasok set regdatum=now(), rinterval=?, aktiv=1, cegid=?, helyszinid=?, szurestipusid=?, orvosassigned=?, rkod=22233, datum=?, nev=?, taj=?, utca=?, telefon=?",
                    [$csvUser["rinterval"], $csvUser["cegid"], $csvUser["helyszinid"], $csvUser["tipusid"], $csvUser["orvosid"], $datum, $nev, $taj, $utca, $telefon]);


            }
        }

    }


    /*
     *
SELECT t.megnev, COUNT(*) AS total
,SUM(IF (MONTH(datum)=1, 1, 0)) AS jan
,SUM(IF (MONTH(datum)=2, 1, 0)) AS feb
,SUM(IF (MONTH(datum)=3, 1, 0)) AS marc
,SUM(IF (MONTH(datum)=4, 1, 0)) AS apr
,SUM(IF (MONTH(datum)=5, 1, 0)) AS maj
,SUM(IF (MONTH(datum)=6, 1, 0)) AS jun
,SUM(IF (MONTH(datum)=7, 1, 0)) AS jul
,SUM(IF (MONTH(datum)=8, 1, 0)) AS aug
,SUM(IF (MONTH(datum)=9, 1, 0)) AS szep
,SUM(IF (MONTH(datum)=10, 1, 0)) AS okt
,SUM(IF (MONTH(datum)=11, 1, 0)) AS nov
,SUM(IF (MONTH(datum)=12, 1, 0)) AS 'dec'
 FROM foglalasok f
LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
WHERE helyszinid=1 AND datum>'2024-01-01 00:00:00' AND datum<'2024-12-31 23:59:59' AND f.cegid IN (56,59,75,87,98,104) AND f.eljott GROUP BY f.`szurestipusid`
ORDER BY t.megnev
;


SELECT * FROM cegek WHERE INSTR(megnev, 'törv');


SELECT t.megnev, COUNT(*) AS total
,SUM(IF (MONTH(datum)=1, 1, 0)) AS jan
,SUM(IF (MONTH(datum)=2, 1, 0)) AS feb
,SUM(IF (MONTH(datum)=3, 1, 0)) AS marc
,SUM(IF (MONTH(datum)=4, 1, 0)) AS apr
,SUM(IF (MONTH(datum)=5, 1, 0)) AS maj
,SUM(IF (MONTH(datum)=6, 1, 0)) AS jun
,SUM(IF (MONTH(datum)=7, 1, 0)) AS jul
,SUM(IF (MONTH(datum)=8, 1, 0)) AS aug
,SUM(IF (MONTH(datum)=9, 1, 0)) AS szep
,SUM(IF (MONTH(datum)=10, 1, 0)) AS okt
,SUM(IF (MONTH(datum)=11, 1, 0)) AS nov
,SUM(IF (MONTH(datum)=12, 1, 0)) AS 'dec'
 FROM foglalasok f
LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
WHERE datum>'2024-01-01 00:00:00' AND datum<'2024-12-31 23:59:59' AND f.eljott=1 GROUP BY f.`szurestipusid`
ORDER BY t.megnev
;

fo;
;

SELECT * FROM notifications WHERE tipus='usermegerosito' ORDER BY datum DESC;
;
SELECT o.nev, COUNT(*) AS total
,SUM(IF (MONTH(regdatum)=1, 1, 0)) AS jan
,SUM(IF (MONTH(regdatum)=2, 1, 0)) AS feb
,SUM(IF (MONTH(regdatum)=3, 1, 0)) AS marc
,SUM(IF (MONTH(regdatum)=4, 1, 0)) AS apr
,SUM(IF (MONTH(regdatum)=5, 1, 0)) AS maj
,SUM(IF (MONTH(regdatum)=6, 1, 0)) AS jun
,SUM(IF (MONTH(regdatum)=7, 1, 0)) AS jul
,SUM(IF (MONTH(regdatum)=8, 1, 0)) AS aug
,SUM(IF (MONTH(regdatum)=9, 1, 0)) AS szep
,SUM(IF (MONTH(regdatum)=10, 1, 0)) AS okt
,SUM(IF (MONTH(regdatum)=11, 1, 0)) AS nov
,SUM(IF (MONTH(regdatum)=12, 1, 0)) AS 'dec'
FROM foglalasok f
LEFT JOIN orvosok o ON o.id=f.`orvosassigned`
LEFT JOIN szurestipusok t ON t.id=f.szurestipusid
WHERE regdatum>'2025-01-01 00:00:00' AND regdatum<'2025-12-31 00:59:59' AND eljott=1 AND cegid IN (11,392,606)
GROUP BY f.orvosassigned

ORDER BY o.nev


    SELECT calcfoglalta AS forras, COUNT(*) AS total

,SUM(IF (MONTH(datum)=1, 1, 0)) AS jan
,SUM(IF (MONTH(datum)=2, 1, 0)) AS feb
,SUM(IF (MONTH(datum)=3, 1, 0)) AS marc
,SUM(IF (MONTH(datum)=4, 1, 0)) AS apr
,SUM(IF (MONTH(datum)=5, 1, 0)) AS maj
,SUM(IF (MONTH(datum)=6, 1, 0)) AS jun
,SUM(IF (MONTH(datum)=7, 1, 0)) AS jul
,SUM(IF (MONTH(datum)=8, 1, 0)) AS aug
,SUM(IF (MONTH(datum)=9, 1, 0)) AS szep
,SUM(IF (MONTH(datum)=10, 1, 0)) AS okt
,SUM(IF (MONTH(datum)=11, 1, 0)) AS nov
,SUM(IF (MONTH(datum)=12, 1, 0)) AS 'dec'

FROM (SELECT datum, foglalta, szurestipusid,
IF (foglalta='', 'bejelentkezo',

IF (foglalta IN ('', 'labshop', 'foglaljorvost', 'union', 'webpage', 'webshop', 'keltexmedwww'), foglalta, 'admin')) AS calcfoglalta
FROM foglalasok
WHERE datum>'2025-01-01 00:00:00' AND datum<'2025-12-31 23:55:55' AND (foglalta='foglaljorvost' OR eljott=1) AND helyszinid IN (292,328) AND eljott=1 AND cegid IN (11,392,606)) a

LEFT JOIN szurestipusok t ON t.id=a.szurestipusid

GROUP BY calcfoglalta ORDER BY t.megnev, calcfoglalta;




ELECT szurestipusid, t.megnev AS tipus

,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=1, 1, 0)) AS 2023_jan
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=2, 1, 0)) AS 2023_feb
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=3, 1, 0)) AS 2023_marc
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=4, 1, 0)) AS 2023_apr
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=5, 1, 0)) AS 2023_maj
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=6, 1, 0)) AS 2023_jun
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=7, 1, 0)) AS 2023_jul
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=8, 1, 0)) AS 2023_aug
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=9, 1, 0)) AS 2023_szep
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=10, 1, 0)) AS 2023_okt
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=11, 1, 0)) AS 2023_nov
,SUM(IF (YEAR(datum)=2023 AND MONTH(datum)=12, 1, 0)) AS 2023_dec
,SUM(IF (YEAR(datum)=2023, 1, 0)) AS 2023_total
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=1, 1, 0)) AS 2024_jan
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=2, 1, 0)) AS 2024_feb
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=3, 1, 0)) AS 2024_marc
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=4, 1, 0)) AS 2024_apr
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=5, 1, 0)) AS 2024_maj
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=6, 1, 0)) AS 2024_jun
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=7, 1, 0)) AS 2024_jul
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=8, 1, 0)) AS 2024_aug
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=9, 1, 0)) AS 2024_szep
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=10, 1, 0)) AS 2024_okt
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=11, 1, 0)) AS 2024_nov
,SUM(IF (YEAR(datum)=2024 AND MONTH(datum)=12, 1, 0)) AS 2024_dec
,SUM(IF (YEAR(datum)=2024, 1, 0)) AS 2024_total
FROM (
SELECT f.id, f.datum, f.orvosassigned, f.szurestipusid, f.nev, f.taj, (SELECT id FROM foglalasok ff WHERE datum<f.datum AND ff.taj=f.taj LIMIT 1) multiple  FROM foglalasok f
WHERE f.datum>'2025-01-01 00:00:00' AND f.datum<'2025-12-31 23:00:00' AND f.helyszinid IN (176) AND eljott=1 AND f.cegid=42
HAVING multiple IS NULL LIMIT 111111111111
) a
LEFT JOIN szurestipusok t ON a.szurestipusid=t.id
WHERE t.megnev IS NOT NULL
GROUP BY a.szurestipusid ORDER BY t.megnev







         *
     */


}