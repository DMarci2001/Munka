<?php

class AdminAjaxService {

    public function start() {

        $adminUtils = new AdminUtils();

        if (isset($_GET["print"]) && isset($_GET["template"])) {
            $printService = new PrintService();
            $printService->setTemplate($_GET["template"]);
            if (isset($_GET["fid"]) && isset($_GET["p"])) {
                $printService->setReservation($_GET["fid"], $_GET["p"]);
            }
            $printService->start();
            die;
        }

        if (isset($_GET["simpletest"])) {
            $simpleService = new SimplePayService();
            $simpleService->startPay(131688);
            die;
        }

        if (isset($_POST["showrefund"]) && isset($_SESSION["adminuser"])) {
            $simpleService = new SimplePayService();
            echo $simpleService->showRefundWindow($_POST["showrefund"]);
            die;
        }

        if (isset($_POST["startsimplerefund"]) && isset($_SESSION["adminuser"])) {
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
            if ($_GET["page"] == "companies" && $adminUtils->cegModJog()) {
                sql_query("insert into cegek set megnev='Új cég'");
            }
            if ($_GET["page"] == "places" && $adminUtils->helyszinModJog()) {
                sql_query("insert into helyszinek set cim='Új helyszín'");
            }
            if ($_GET["page"] == "doctors" && $adminUtils->orvosModJog()) {
                sql_query("insert into orvosok set nev='Új orvos',createdby=?, created=now()", array($_SESSION["adminuser"]["nev"]));
                $oid = sql_insert_id();
                sql_query("update orvosok set username='d{$oid}',jelszo=SUBSTR(MD5(CONCAT(nev,id)) FROM 3 FOR 6) where id='{$oid}'");
            }
            if ($_GET["page"] == "screenings" && $adminUtils->szurestipusModJog()) {
                sql_query("insert into szurestipusok set megnev='Új tétel'");
            }
            if ($_GET["page"] == "users") {
                if ($_SESSION["adminuser"]["jogosultsag"] >= 2) {
                    sql_query("insert into users set nev='Új felhasználó'");
                } else {
                    sql_query("insert into users set nev='Új felhasználó', cegid='{$_SESSION["adminuser"]["cegid"]}'");
                }
                logActivity("user", sql_insert_id(), "felhasználó létrehozva");
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["delete"])) {
            if ($_GET["page"] == "places" && $adminUtils->helyszinModJog()) {
                sql_query("delete from helyszinek where id=?", array($_GET["delete"]));
            }
            if ($_GET["page"] == "doctors" && $adminUtils->orvosModJog()) {
                sql_query("delete from orvosok where id=?", array($_GET["delete"]));
                sql_query("delete from orvos_beosztas where orvosid=?", array($_GET["delete"]));
            }

            if ($_GET["page"] == "screenings" && $adminUtils->szurestipusModJog()) {
                sql_query("delete from szurestipusok where id=?", array($_GET["delete"]));
            }
            if ($_GET["page"] == "users") {
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

            $required = array("Nev", "SzuletesiDatum", "Azonosito", "Nem", "Iranyitoszam", "Telepules", "Cim", "SzuletesiNev", "Telefon", "Mobiltelefon");

            $params = sql_fetch_array(sql_query("SELECT fogl.nev AS 'Nev', fogl.taj AS 'Azonosito', '2' AS 'AzonositoTipusID',fogl.szuldatum AS 'SzuletesiDatum', 
                                                        fogl.szulhely AS 'SzuletesiHely', fogl.anyjaneve AS 'AnyjaNeve', CASE WHEN fogl.neme = 0 THEN 3 ELSE fogl.neme END AS 'NemID',
                                                        fogl.nev AS 'SzuletesiNev', '109' AS 'AllampolgarsagID', fogl.telefon AS 'Telefon', fogl.telefon AS 'Mobiltelefon',
													    fogl.irsz AS 'Iranyitoszam', fogl.varos AS 'Telepules', fogl.utca AS 'Cim', 
													    fogl.email AS 'Email', null AS 'SzigSzam', null AS 'KozgyogyTol', null AS 'KozgyogyIg', null AS 'KozgyogySzam', 
                                                        '3' AS 'FelvevoID', '3' AS 'UtolsoModositoID'
                                                        
											    FROM foglalasok fogl WHERE id=?", array($_POST["pid"])));


            foreach ($params as $index => $value) {
                if ($value == "" && in_array($index, $required)) {
                    $error[] = "<span style='color:red'>*{$index} mező megadása kötelező!</span>";
                }
            }

            if (empty($error)) {
                $response = $dokirexService->insertPaciensIntoDokirex($params);
            }

            $html = "";
            $html .= "<div style='color:#444;text-align:center;'>";
            $html .= "<div id='loginbox' class='loginbox'>";
            $html .= "<div class='loginhead'>Dokirex adatfeltöltés</div>";

            $html .= "<div style='padding:20px;text-align:center;'>";

            if (count($error) > 0) {
                for ($i = 0; $i < count($error); $i++) {
                    $html .= "<div style='margin-top:10px;text-align:left'>{$error[$i]}</div>";
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

            //$html.= "<div style='padding-top:5px;'><input type='text' style='width:100px;' id='refundprice' placeholder='' value='{$transactionData["osszeg"]}' /></div>";
            //$html.= "<div style='margin-top:10px;display:none;' id='transferresult'></div>";

            $html .= "<div style='padding-top:10px;'><input onclick='hideGeneralPopup();return false;' type='button' id='simplerefundclosebutton' value='Bezárás' /></div>";
            $html .= "</div>";

            $html .= "</div>";
            $html .= "</div>";

            die($html);
        }

        if (isset($_POST['manualNotificationSend']) && $_POST['manualNotificationSend'] == true) {
            header('Content-Type: application/json');
            $status = "";
            $error  = "";

            $request = sql_fetch_array(sql_query("SELECT userertesitve,email FROM foglalasok where id=?", array($_POST['id'])));

            //Ha hibás e-mail van megadva akkor hibára fut:
            if (!filter_var($request["email"], FILTER_VALIDATE_EMAIL)) {
                die(json_encode(array("status" => "error", "text" => "Nincs megadott helyes e-mail cím!")));
            } else {
                //Ha nem volt még értesítve, vagy post tartalmazza a megerősítési kérelmet:
                if ($request['userertesitve'] == 0 || (isset($_POST['status']) && $_POST['status'] == true)) {
                    //Lekérdezés ellenőrzése
                    if ($request['userertesitve'] == 1) sql_query("UPDATE foglalasok SET userertesitve=0 WHERE id=?", array($_POST['id']));
                    $service = new BookingService();
                    $service->sendToUser($_POST['id']);
                    die(json_encode(array("status" => true, "text" => "Sikeres értesítő küldés!")));
                } else {
                    $notification = sql_fetch_array(sql_query("SELECT MAX(datum) as datum FROM ertesites_log WHERE foglid=? GROUP BY foglid", array($_POST['id'])));
                    if (count($notification) > 0) {
                        die(json_encode(array("status" => false, "text" => "A páciens részére már volt értesítés küldve {$notification['datum']}-kor! Biztosan küldeni akarsz egyet ismét?")));
                    } else die(json_encode(array("status" => false, "text" => "A páciens részére már volt értesítés küldve! Biztosan küldeni akarsz egyet ismét?")));
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

            /*$mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress("m.gergely9409@gmail.com", "marton.gergely@hungariamed.hu");
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);

            $t=iconv("UTF-8","ISO-8859-2","Orvosi alkalmassági vizsgálata hamarosan lejár!");

            $mbody = "Kedves Márton Gergely,<br/>";
            $mbody.= "Az orvosi alkalmassági vizsgálata hamarosan lejár!<br/>";
            $mbody.= "Lejárat dátuma: 2018-09-23<br/>";
            $mbody.= "Kérem foglaljon időpontot honlapunkon:<br/>";
            $mbody.= "<a href='https://bert.hungariamed.hu'>https://bert.hungariamed.hu</a><br/>";
            $mbody.= "Tisztelettel,<br/>";
            $mbody.= "HungáriaMed - M.kft";

            $mail->Subject=$t;
            $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
            $mail->AddAttachment($Attachment);
            if ($mail->Send()) echo "success!";
            else echo "failed!";*/

            for($row = 2; $row <= $lastRow; $row++)
            {
                //echo $worksheet->getCell('A'.$row)->getValue()." : ".$worksheet->getCell('B'.$row)->getValue()." : ".$worksheet->getCell('C'.$row)->getValue()." : ".$worksheet->getCell('D'.$row)->getValue()."<br/>";

                $mail = new PHPMailer();
                $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
                $mail->FromName = Booking_Constants::COMPANY_NAME;
                $mail->AddAddress($worksheet->getCell('C'.$row)->getValue(), $worksheet->getCell('A'.$row)->getValue());
                $mail->AddAddress($worksheet->getCell('D'.$row)->getValue(), $worksheet->getCell('D'.$row)->getValue());
                //$mail->AddAddress("m.gergely9409@gmail.com", "Márton Gergely");
                $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
                $mail->IsHTML(true);

                $t=iconv("UTF-8","ISO-8859-2","Orvosi alkalmassági vizsgálata hamarosan lejár!");

                $mbody = "Kedves {$worksheet->getCell('A'.$row)->getValue()},<br/>";
                $mbody.= "Az orvosi alkalmassági vizsgálata hamarosan lejár!<br/>";
                $mbody.= "Lejárat dátuma: {$worksheet->getCell('B'.$row)->getValue()}<br/>";
                $mbody.= "Kérem foglaljon időpontot honlapunkon:<br/>";
                $mbody.= "<a href='https://bert.hungariamed.hu'>https://bert.hungariamed.hu</a><br/>";
                $mbody.= "Tisztelettel,<br/>";
                $mbody.= "Hungária Med - M.kft";

                $mail->Subject=$t;
                $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
                $mail->AddAttachment($Attachment);
                if($mail->Send()) echo "success!({$worksheet->getCell('A'.$row)->getValue()})<br/>";
                else echo "failed!({$worksheet->getCell('A'.$row)->getValue()})<br/>";
            }
            die();
        }
    }


}