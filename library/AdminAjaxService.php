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
                $execute = "convert {$originalImagePath} -crop {$imageCropWidth}x{$imageCropHeight}+{$imageCropX}+{$imageCropY} {$imagePath}";
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


    public function addCsvUsers() {
        $users = [];

        $users[] = ['1','Jeszenka Ildikó','Ady','075545989','1965.12.31','EP239015515'];
        $users[] = ['2','Molnár Péter','Ady','028368371','1968.09.22','EP249021205'];
        $users[] = ['3','Borbás Tamás','Ady','028027766','1968.05.23','EP229001985'];
        $users[] = ['4','Feketéné Kárpáti Tímea ','Ady','081629804','1974.11.24','EP249012372'];
        $users[] = ['5','Anisity Zsuzsanna','Ady','084344511','1978.03.13','EP249012368'];
        $users[] = ['6','Bene Róbert ','Ady','027115354','1967.01.17',''];
        $users[] = ['7','Gyarmatiné Herczeg Zsuzsanna ','Ady','074433195 ','1964.02.01',''];
        $users[] = ['8','Diószeghy Edina','Ady','081320635','1974.07.19','EP239015532'];
        $users[] = ['9','Egri Erzsébet','Ady','075619442','1966.02.15',''];
        $users[] = ['10','Gerzseiné Balaska Enikő','Ady','078986710','1971.03.21','EP239015405'];
        $users[] = ['11','Hegedüs-Horváth Szimonetta','Ady','088624264','1984.10.07','EP239015448'];
        $users[] = ['12','Holczer József','Ady','025629165','1964.07.10',''];
        $users[] = ['13','Link Tibor','Ady','033859066','1976.04.01','EP249012459'];
        $users[] = ['14','Valasek Valéria','Ady','086346810','1981.01.05','EP249012462'];
        $users[] = ['15','Földi János','Ady','030473056','1971.12.21','EP239015349'];
        $users[] = ['16','Gáspár Alex','Ady','0108367438','1994.07.10',''];
        $users[] = ['17','Huszár Zoltán','Ady','033368926','1975.09.04',''];
        $users[] = ['18','Kaszáné Muráti Beáta Kinga','Ady','083954128','1977.09.05',''];
        $users[] = ['19','Lauferné Orbán Anikó','Ady','085759352','1980.02.19','EP249012452'];
        $users[] = ['20','Molnárné Pap Márta','Ady','081025622','1974.03.11',''];
        $users[] = ['21','Orsós József','Ady','024430209','1962.06.09',''];
        $users[] = ['22','Bacsóné Tajti Margit','Ady','082719069','1976.03.02',''];
        $users[] = ['23','Tamaskó-Komjáti Anett','Ady','085125645','1979.04.02',''];
        $users[] = ['24','Csécsei Judit','Ady','087354719','1982.08.04','EP239026406'];
        $users[] = ['25','Amma Tamás','Apáczai','039637792','1984.04.05',''];
        $users[] = ['26','Kohn Istvánné','Apáczai','077247593','1968.08.13','EP229001996'];
        $users[] = ['27','Klajkóné Kalmár Anna ','Apáczai','076869231','1968.02.03','EP229001941'];
        $users[] = ['28','László-Tóth Ivett ','Apáczai','086763040','1981.08.17','EP249012432'];
        $users[] = ['29','Király-Ludvig Réka','Apáczai','088303105','1984.03.21','EP239003188'];
        $users[] = ['30','Kozma Erzsébet Elvira ','Apáczai','074427578','1964.01.28','EP239015452'];
        $users[] = ['31','Kovács Márta ','Apáczai','080541426','1973.07.03',''];
        $users[] = ['32','Tar Melinda ','Apáczai','085898811','1980.05.03',''];
        $users[] = ['33','Márfi Eszter  ','Apáczai','085613461','1979.12.03',''];
        $users[] = ['34','Noé Zoltán','Apáczai','034596052','1977.02.11','EP249012426'];
        $users[] = ['35','Balogh Attila','Bezerédj','040077077','1984.12.30','EP229001954'];
        $users[] = ['36','Borosné Radinovics Mónika','Bezerédj','086718617','1981.07.25','EP245002996'];
        $users[] = ['37','Simon Ivett ','Bezerédj','081191488','1974.05.25',''];
        $users[] = ['38','Boros Norbert','Bezerédj','041664896','1987.07.26','EP219018929'];
        $users[] = ['39','Molnár Gréta Klára','Bezerédj','084887155','1978.11.30',''];
        $users[] = ['40','Egresi Kornélia Tereza','Bezerédj','084360186','1978.03.20','EP239014937'];
        $users[] = ['41','Feketéné Földesi Judit','Bezerédj','087111970','1982.03.13','EP239014936'];
        $users[] = ['42','Málnainé Banó Edina','Bezerédj','082817323','1976.04.15','EP239015058'];
        $users[] = ['43','Szabó Rita','Bezerédj','076341083','1967.04.17','EP239015057'];
        $users[] = ['44','Töttős Gábor','Hunyadi','035979395','1978.10.22','EP249021206'];
        $users[] = ['45','Tomolik Katinka','Hunyadi','083231177','1976.10.10','EP229001955'];
        $users[] = ['46','Kocsisné  Doszpod Sziliva','Hunyadi','081035670','1974.03.15','EP249012455'];
        $users[] = ['47','Lukács Gábor','Hunyadi','036792205','1979.11.22','EP169007985'];
        $users[] = ['48','Drégely-Turi Hedvig','Hunyadi','078706884','1970.10.07',''];
        $users[] = ['49','Leskó László','Hunyadi','031911973','1974.01.14',''];
        $users[] = ['50','Nagy Andrea','Hunyadi','081395899','1974.08.17','EP249030751'];
        $users[] = ['51','Rittbergerné Fürj Anikó','Hunyadi','078819720','1970.12.19','EP229001991'];
        $users[] = ['52','Orbánné Palkó Mária','Hunyadi','086133719','1980.09.05',''];
        $users[] = ['53','Sársodi Bernadett','I. István','086133719','1980.09.05','EP229001516'];
        $users[] = ['54','Rutai Renáta','I. István','089544589','1986.05.01','EP229001938'];
        $users[] = ['55','Várszegi Petra','I. István','086912451','1981.11.15','EP229001951'];
        $users[] = ['56','Némethné Márton Renáta','Magyar ','085089675','1979.03.15','EP229001987'];
        $users[] = ['57','Fehér Katalin','Magyar ','086680550','1981.07.05','EP249015492 '];
        $users[] = ['58','Fazekas Attiláné','Magyar','0106779482','1978.12.21',''];
        $users[] = ['59','Háryné Viszló Beáta','Perczel','086293666','1980.12.04','EP020013046'];
        $users[] = ['60','Virányi Gabriella ','Perczel','081896932','1975.03.16','EP219000568'];
        $users[] = ['61','Barabás Ivett','Perczel','081283259','1974.07.04',''];
        $users[] = ['62','Ferencz-Szőcs Szilvia','Perczel','083704325','1977.04.17',''];
        $users[] = ['63','Brettnerné Ónadi Krisztina Edit','Perczel','106299218','1974.05.07','EP249027328'];
        $users[] = ['64','Égető Zsolt','Perczel','031388067','1973.04.17',''];
        $users[] = ['65','Furján Katalin Anett','Vályi','084563978','1978.06.22','EP229001461'];
        $users[] = ['66','Balassáné Herczeg Szilvia','Vályi ','083643938','1977.04.21','EP229002013'];
        $users[] = ['67','Kolozs Levente ','Vályi ','039213013','1983.07.21','EP229001449'];
        $users[] = ['68','Németh Lehelné','Vályi ','076705283','1967.11.02','EP239014581'];
        $users[] = ['69','Belényi Lászlóné','Vályi','074434178','1964.02.02','EP199015358'];
        $users[] = ['70','Molnár Gábor','Vályi','036812040','1979.12.03','EP239014888'];
        $users[] = ['71','Szilágyi Tímea','Vályi','078467716','1970.06.02','EP249012451'];
        $users[] = ['72','Szabó József','Vályi','029341630','1970.04.21','EP239014881'];
        $users[] = ['73','Pentz Andrea','Centrum','081560961','1974.10.24','EP052012371'];
        $users[] = ['74','Pintér Dóra','Centrum','085991778','1980.06.22','EP163000954'];
        $users[] = ['75','Sluzek Éva','Vizsgaközpont','081505315','1974.09.30','EP229001997'];
        $users[] = ['76','Borosné Ács Annamária','Vizsgaközpont','085535653','1979.10.22',''];
        $users[] = ['77','Dömötör Csaba','Centrum','025662302','1964.07.29','EP229001311'];
        $users[] = ['78','Éles Márta ','Centrum','083279797','1976.11.03','EP229001936'];
        $users[] = ['79','Szalai Ervin','Centrum','029267875','1970.03.16','EP239015205'];
        $users[] = ['80','Németh Erika','Centrum','086451004','1981.03.04','EP229001305'];
        $users[] = ['81','Dr. Szekeres Beáta','Centrum','085695847','1980.01.16','E129014343'];
        $users[] = ['82','Csuta Károly','Centrum','028272722','1968.09.25','EP143006883'];
        $users[] = ['83','Ruml Nikoletta','Centrum','092813872','1991.12.14','EP239015215'];
        $users[] = ['84','Schmidt Ágnes','Centrum','087751273','1983.04.07','EP229001313'];
        $users[] = ['85','Nemes Katalin','Centrum','085881091','1980.04.24','EP239015582'];
        $users[] = ['86','Nótás Diána','Centrum','092183087','1990.11.18','EP229001944'];
        $users[] = ['87','Vaskóné Csigi Viki','Centrum','084409566','1978.04.12','EP249030386'];
        $users[] = ['88','Bacher Erika','Centrum','081762213','1975.01.21','EP229001989'];
        $users[] = ['89','Elmné Tompai Mónika','Centrum','086993809','1982.01.03','EP239015517'];
        $users[] = ['90','Gyarmati Éva','Centrum','075645625','1966.03.02','EP239015446'];

        foreach ($users as $user) {
            sql_query("insert into felhasznalok set cegid=?, nev=?, jelszo=?, taj=?, torzsszam=?, szuldatum=?, rkod=?, validated=1, statusz=1",
                [   659,
                    $user[1],
                    md5($user[1].rand(10000000, 99999999)),
                    $user[3],
                    $user[5],
                    str_replace(".", "-", $user[4]),
                    rand(10000, 99999)]
            );

            echo $user[1]."\n";
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