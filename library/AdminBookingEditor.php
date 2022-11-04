<?php

class AdminBookingEditor {

    private $adminUtils;
    private $utils;
    private $bookingService;
    private $beosztasService;
    private $user;
    private $notificationService;

    public function __construct()
    {
        $this->adminUtils = new AdminUtils();
        $this->utils = new Utils();
        $this->bookingService = new BookingService();
        $this->beosztasService = new BeosztasService();
        $this->user = new AdminUser();
        $this->notifyService = new NotificationService();

        if (isset($_GET["showidoponteditor"])) {
            echo $this->_showBookingEditor($_GET["showidoponteditor"], $_GET["p"]);
            die();
        }

        if (isset($_POST["deleteuploadedfile"])) {
            $docAgent = new DocAgent();
            $docAgent->deleteDoc($_POST["id"], $_POST["k"]);

            $reservationData = sql_query("select pass from foglalasok where id=?", [$_POST["fid"]])->fetch(PDO::FETCH_ASSOC);
            Utils::jsonOut(["status" => "", "html" => $this->_showBookingEditor($_POST["fid"], $reservationData["pass"])]);
        }

        if (isset($_POST["uploadbeutalofile"])) {
            $docAgent= new DocAgent();
            $reservationId = intval($_POST["uploadbeutalofile"]);
            $status = "";

            foreach ($_FILES as $file) {
                $result = $docAgent->saveDoc($file, ["fid" => $reservationId, "beutaloid" => 0]);
                if ($result != "0") {
                    $status = $result;
                }
            }

            $reservationData = sql_query("select pass from foglalasok where id=?", [$reservationId])->fetch(PDO::FETCH_ASSOC);
            Utils::jsonOut(["status" => $status, "html" => $this->_showBookingEditor($reservationId, $reservationData["pass"])]);
        }

        if (isset($_POST["foglalasmentesnaptar2"]) || isset($_POST["foglalasmentesnaptaresertesites2"]) && $this->user->authenticated()) {
            $fid=intval($_POST["fid"]);
            if (!isset($_POST["szuldatum"])) {
                if (isset($_POST["szuldatumev"])) {
                    $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
                    if ($_POST["szuldatumev"] == 0 || $_POST["szuldatumho"] == 0 || $_POST["szuldatumnap"] == 0) {
                        $_POST["szuldatum"] = "";
                    }
                }
            } else {
                $_POST["szuldatum"] = str_replace(".", "", $_POST["szuldatum"]);
                $_POST["szuldatum"] = str_replace("-", "", $_POST["szuldatum"]);
                $_POST["szuldatum"] = substr($_POST["szuldatum"], 0, 4)."-".substr($_POST["szuldatum"], 4, 2)."-".substr($_POST["szuldatum"], 6, 2);
                if (empty(str_replace("-", "", str_replace(" ", "", $_POST["szuldatum"])))) {
                    $_POST["szuldatum"] = "";
                }
            }


            $eljottIdopont = "0000-00-00 00:00:00";
            if (isset($_POST["eljottidopont"])) {
                $reservationData = sql_query("select datum from foglalasok where id=?", [$fid])->fetch(PDO::FETCH_ASSOC);
                $eljottIdopont = date("Y-m-d", strtotime($reservationData["datum"]))." ".$_POST["eljottidopont"].":00";
            }

            if (!isset($_POST["alkalmassaguserid"])) $_POST["alkalmassaguserid"]=0;
            if (!isset($_POST["voltnalunk"])) $_POST["voltnalunk"]=0;
            if (!isset($_POST["alkalmassag"])) $_POST["alkalmassag"]=0;
            if (!isset($_POST["alkalmassagido"])) $_POST["alkalmassagido"]=0;
            if (!isset($_POST["tudoszuro"])) $_POST["tudoszuro"]=0;
            if (!isset($_POST["vernyomas"])) $_POST["vernyomas"] = "";
            if (!isset($_POST["orvosszoveg"])) $_POST["orvosszoveg"] = "";
            if (!isset($_POST["torzsszam"])) $_POST["torzsszam"] = "";
            if (!isset($_POST["adoszam"])) $_POST["adoszam"] = "";

            if ($_POST["nev"]=="") $_POST["nev"]="nincs név";

            if (!is_numeric($_POST["cegid"])) {
                sql_query("insert into cegek set megnev=?, aktiv=1", [$_POST["cegid"]]);
                $_POST["cegid"] = sql_insert_id();
                $this->notifyService->newCompanyNotification($_POST["cegid"]);
            }

            sql_query("update foglalasok set
                orvosassigned=?,
                cegid=?,
                taj=?,
                nszam=?,
                torzsszam=?,
                nev=?,
                munkakor=?,
                adoszam=?,
                email=?,
                telefon=?,
                szuldatum=?,
                szulhely=?,
                anyjaneve=?,
                irsz=?,
                varos=?,
                utca=?,
                voltnalunk=?,
                alkalmassag=?,
                alkalmassagido=?,
                alkalmassagikhet=?,
                alkalmassagkorl=?,
                tudoszuroervenyesseg=?,
                tudoszuro=?,
                vernyomas=?,
                orvosszoveg=?,
                alkalmassaguserid=?,
                eljottidopont=?
            where id=?", [intval($_POST["orvosassigned"]), intval($_POST["cegid"]), $_POST["taj"], $_POST["nszam"], $_POST["torzsszam"], $_POST["nev"], $_POST["munkakor"], $_POST["adoszam"], $_POST["email"], $_POST["telefon"], $_POST["szuldatum"], $_POST["szulhely"], $_POST["anyjaneve"],
                $_POST["irsz"], $_POST["varos"], $_POST["utca"], $_POST["voltnalunk"], $_POST["alkalmassag"], $_POST["alkalmassagido"], $_POST["alkalmassagikhet"], $_POST["alkalmassagkorl"], $_POST["tudoszuroervenyesseg"], $_POST["tudoszuro"], $_POST["vernyomas"], $_POST["orvosszoveg"], $_POST["alkalmassaguserid"], $eljottIdopont, $fid]);


            if (!empty($_POST["paciensid"])) {
                sql_query("update foglalasok set paciensid=? where id=? and paciensid=0", [$_POST["paciensid"], $fid]);
            }

            if (isset($_POST["megj"])) {
                sql_query("update foglalasok set megj=? where id=?", [$_POST["megj"], $fid]);
            }

            $alkalmassagi = "";
            if($_POST['alkalmassag'] === "I") {
                $alkalmassagi = date("Y-m-d",strtotime("Now + {$_POST['alkalmassagido']} months"));
                //echo "I";
            }
            if($_POST['alkalmassag'] === "N") {
                $alkalmassagi = "0000-00-00 00:00:00";
                //echo "N";
            }
            if($_POST['alkalmassag'] === "IN") {
                $alkalmassagi = date("Y-m-d",strtotime("Now + {$_POST['alkalmassagikhet']} weeks"));
                //echo "IN";
            }
            if($_POST['alkalmassag'] === "K") {
                $alkalmassagi = date("Y-m-d",strtotime("Now + {$_POST['alkalmassagido']} months"));
                //echo "K";
            }

            $request = sql_query("SELECT id FROM felhasznalok WHERE email = '{$_POST['email']}' AND taj = '{$_POST['taj']}' ");
            if($request->rowCount() > 0 && $alkalmassagi != "") {
                $result = sql_fetch_array( $request );
                sql_query("UPDATE felhasznalok SET alklejarat = '{$alkalmassagi}' WHERE id = {$result['id']} ");
            }

            if( $_POST['kuponkod'] != "" ) {
                $foglalas = sql_fetch_array(sql_query("SELECT fogl.datum, kl.foglalasid, fogl.szurestipusid FROM foglalasok fogl LEFT JOIN kupon_lista kl ON kl.foglalasid = fogl.id WHERE fogl.id = ? ", array( $fid )));
                $check = kuponCheck($_POST['kuponkod'],3,date("Y-m-d",strtotime($foglalas['datum'])),$foglalas['szurestipusid']);
                if( $check == "usable") {
                    $kupon = sql_fetch_array(sql_query("SELECT * FROM kuponkodok WHERE kod = ?", array($_POST['kuponkod'])));
                    sql_query("INSERT INTO kupon_lista SET kuponid = ?, kuponkod = ?, foglalasid = ?, jovahagyta = ?",
                        array( $kupon['id'], $kupon['kod'], $fid, $this->user->user["username"] ));
                }
            }

            if( $_POST['kuponkod'] == "" ) {
                $kupon = sql_query("SELECT * FROM kupon_lista WHERE foglalasid = {$fid}");
                if( $kupon->rowCount() > 0 ) {
                    $result = sql_fetch_array($kupon);
                    //unlink using:
                    sql_query("DELETE FROM kupon_lista WHERE kuponkod = '{$result['kuponkod']}' AND foglalasid = {$fid} ");
                }
            }

            if ($_POST["orvosassigned"] != $_POST["regiorvos"]) {
                sql_query("update foglalasok set ertesitve=0 where id=?",array($fid));
            }

            $rowf = sql_fetch_array(sql_query("select * from foglalasok where id=?",array($fid)));
            logActivity("foglalas",$fid,"{$_POST["nev"]} foglalás adatlap {$rowf["datum"]}",print_r($_POST,true));

            if ($_POST["orvosassigned"]==0 && $_POST["cegid"]!=0) {
                $oid = $this->bookingService->selectFreeOrvosForIdopont($fid);
                //$rowo=sql_fetch_array(selectOrvosForFoglalas($fid));
                sql_query("update foglalasok set orvosassigned=? where id=? and orvosassigned=0",array($oid, $fid));
            }

            if (isset($_POST["foglalasmentesnaptaresertesites2"])) {
                $this->bookingService->notificationService->sendToCegAndOrvos($fid,1);
            }

            $foService = new FoglaljOrvostService();
            $foService->modifyReservation($fid);

            $api = new BookingSyncApi();
            $api->modifyReservation($fid);


            $status = "";

            if (Booking_Constants::SQL_DB == "keltexmed") {
                //error_reporting(E_ALL);
                //ini_set('display_errors', 1);

                if ($reservationData = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id = ? and szurestipusid=1 and tudoszuro=1", [$fid]))) {
                    //tüdőszűréshez másolás
                    $replicationStatus = $this->bookingService->replicateReservationToAnotherService($reservationData, 102);
                    $status.= $replicationStatus;
                }
            }


            Utils::jsonOut(["status" => $status, "html" => $this->_showBookingEditor($fid, $_POST["p"])]);
            //echo $this->_showBookingEditor($fid, $_POST["p"]);
            die;
        }


        if (isset($_REQUEST["syncFoglalasDataToUser"]) && $this->user->authenticated()) {
            /* Nincs még kész, ahogy a javascript hívás párja se!!! */

            $error = "";
            if (empty($_REQUEST["taj"])) {
                $error .= "A TAJ szám megadása kötelező!\n";
            }
            //if (empty($_REQUEST["torzsszam"])) {
            //    $error .= "A törzsszám megadása kötelező!\n";
            //}
            if (empty($_REQUEST["nev"])) {
                $error .= "A név megadása kötelező!\n";
            }
            //if (empty($_REQUEST["email"])) {
            //    $error .= "Az email cím megadása kötelező!\n";
            //}
            if (empty($_REQUEST["munkakor"])) {
                $error .= "A munkakör megadása kötelező!\n";
            }
            if (empty($_REQUEST["szuldatum"])) {
                $error .= "A születési dátum megadása kötelező!\n";
            }

            $userId = 0;
            if (!isset($_REQUEST["torzsszam"])) {
                $_REQUEST["torzsszam"] = "";
            }

            if (empty($error)) {
                $_REQUEST["szuldatum"] = str_replace(".", "-", $_REQUEST["szuldatum"]);

                if ($userInfo = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE taj=? OR szuldatum=?", array($_REQUEST['taj'], $_REQUEST['szuldatum'])))) {
                    sql_query("UPDATE felhasznalok set taj=?, cegid=?, email=?, nev=?, telefon=?, munkakor=?, irsz=?, varos=?, utca=?, szulhely=?, anyjaneve = ?, szuldatum=?, torzsszam=? WHERE  id=?",
                        array($_REQUEST['taj'], $_REQUEST['cegid'], $_REQUEST['email'], $_REQUEST['nev'], $_REQUEST['telefon'], $_REQUEST['munkakor'], $_REQUEST['irsz'], $_REQUEST['varos'], $_REQUEST['utca'], $_REQUEST['szulhely'], $_REQUEST['anyjaneve'], $_REQUEST['szuldatum'], $_REQUEST['torzsszam'], $userInfo['id']));
                    $userId = $userInfo["id"];
                } else {
                    sql_query("INSERT INTO felhasznalok SET taj=?, cegid=?, email=?, nev=?, telefon=?, munkakor=?, irsz=?, varos=?, utca=?, szulhely=?, anyjaneve=?, szuldatum=?, torzsszam=?, validated=1",
                        array($_REQUEST['taj'], $_REQUEST['cegid'], $_REQUEST['email'], $_REQUEST['nev'], $_REQUEST['telefon'], $_REQUEST['munkakor'], $_REQUEST['irsz'], $_REQUEST['varos'], $_REQUEST['utca'], $_REQUEST['szulhely'], $_REQUEST['anyjaneve'], $_REQUEST['szuldatum'], $_REQUEST['torzsszam']));
                    $userId = sql_insert_id();
                }
                sql_query("UPDATE foglalasok SET paciensid=? WHERE id=?", array($userId, $_REQUEST['fid']));

                //Cég neve:
                $cegNev = "";
                if (isset($_REQUEST['cegid'])) {
                    if ($ceg = sql_fetch_array(sql_query("SELECT megnev FROM cegek WHERE id=?", array($_REQUEST['cegid'])))) {
                        $cegNev = $ceg['megnev'];
                    }
                }

                //Orvos neve:
                $orvosNev = "";
                if (isset($_REQUEST['orvosassigned'])) {
                    if ($orvos = sql_fetch_array(sql_query("SELECT nev FROM orvosok WHERE id=? ", array($_REQUEST['orvosassigned'])))) {
                        $orvosNev = $orvos['nev'];
                    }
                }

                /*
                $wsdl_url = 'http://89.134.90.181:3334/HMMService/Service1.svc?wsdl';
                $client = new SOAPClient($wsdl_url);
                $params = array(
                    'nev' => $_REQUEST['nev'],
                    'taj' => $_REQUEST['taj'],
                    'szuldatum' => $_REQUEST['szuldatum'],
                    'szulhely' => $_REQUEST['szulhely'],
                    'anyjaneve' => $_REQUEST['anyjaneve'],
                    'nem' => "",
                    'ceg' => $cegNev,
                    'munkakor' => $_REQUEST['munkakor'],
                    'orvos' => $orvosNev,
                    'email' => $_REQUEST['email'],
                    'telefon' => $_REQUEST['tel'],
                    'irszam' => $_REQUEST['irsz'],
                    'telepules' => $_REQUEST['varos'],
                    'utca' => $_REQUEST['utca'],
                    'naploszam' => 'naplószáma',
                    'megjegyzes' => $_REQUEST['megj'],
                    'token' => '3YFgyUfWRM5SmiCgMc3SFWb15WXAzAQ5'
                );
                if ($result = $client->InsertUpdatePaciens($params)) {
                    //echo "<pre>";
                    //echo print_r($params, true);
                    //echo "</pre>";
                }
                */
            }

            $this->utils->jsonOut(["error" => $error, "userId" => $userId]);
        }


        if (isset($_REQUEST['AFForm'])) {
            if (!$this->user->authenticated()) {
                $this->utils->jsonOut(["error" => "error"]);
            }

            $w = "";
            if (!$this->user->allCegJog()) {
                $w = "and cegid in (" . $this->adminUser->getCegList() . ")";
            }


            $taj = $_REQUEST["AFForm"];
            $fid = $_REQUEST["fid"] ?? 0;
            $pid = $_REQUEST["pid"] ?? 0;
            
            if (!$data = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE taj = ? and id<>? {$w}", [$taj, $pid]))) {
                if ($data = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE taj = ? and id<>? {$w}", [$taj, $fid]))) {
                    $data["id"] = 0;
                } else {
                    $data["error"] = "Ezzel a TAJ számmal felhasználó nem található!";
                }
            }
            if (!isset($data["error"])) {
                $data["error"] = "";
            }

            $this->utils->jsonOut($data);
            die();
        }
		
    }


    private function _showBookingEditor($id, $p) {
        if (!$this->user->authenticated()) {
            return "Error 500 - Not authenticated!";
        }

        $html = "";
        $id = intval($id);

        if ($row = sql_fetch_array(sql_query("select f.*,t.megnev as sztipus,c.megnev as cegnev,o.nev as orvosnev from foglalasok f
                left join szurestipusok t on t.id=f.szurestipusid
                left join orvosok o on o.id=f.orvosassigned
                left join cegek c on c.id=f.cegid
                where f.id=? and f.pass=?",array($id, $p)))) {

            $html .= "<form id='iform' name='iform' method='post' enctype='multipart/form-data'>";

            $html .= $this->_alkalmassagFolder($row);

            $html .= "<div style='border-top:1px solid #999;margin-top:5px;padding-top:5px;'>";

            $html .= "<div style=''><textarea onclick='orvosVelemenyEnter();' placeholder='orvos vélemény...' style='width:265px;height:40px;' name='orvosszoveg' id='orvosszoveg'>{$row["orvosszoveg"]}</textarea></div>";
            $html .= "<div class='ovsubmit' style='padding:5px 0px 5px 0px;text-align:center;display:none;'><input type='button' style='padding-left:20px;padding-right:20px;' onclick='orvosVelemenyExit();' value='OK'/></div>";
            $html .= "</div>";

            //if (!empty($this->user->user["pecsetszam"])) {
            //    $html .= "<script>$( document ).ready(function() { toggleAlkalmassagBox(); });</script>";
            //}

            $html .= "<div style='padding:0px 0px 0px 0px;border-top:1px solid #999;margin-top:5px;padding-top:5px;'>Kitöltötte: ";
            $html .= "<select name='alkalmassaguserid' style='width:170px;".($this->user->jogosultsagAccess()?"":"pointer-events: none;touch-action: none;")."'>";
            $html .= "<option value='0'>Válasszon!</option>";
            foreach (sql_query("select id, nev from users where pecsetszam<>'' order by nev")->fetchAll(PDO::FETCH_ASSOC) as $orvos) {
                $html .= "<option value='{$orvos["id"]}' ".($row["alkalmassaguserid"] == $orvos["id"] || ($row["alkalmassaguserid"] == 0 && $this->user->user["id"] == $orvos["id"]) ? " selected":"").">{$orvos["nev"]}</option>";
            }
            $html .= "</select>";
            $html .= "</div>";

            $html .= "</div>";
            $html .= "</div>";
            $html .= "</div>";


            $html .= "<div style='position:relative;background:#e0e0e0;'>";
            $html .= "<div style='padding:10px;background:#555;color:#fff;'><span style='font-size:16px;font-weight:bold;' title='Foglalás ideje:{$row['regdatum']}'>" . $this->adminUtils->magyarDatum($row["datum"]) . " - {$row["sztipus"]}</span>";
            $html .= "<div style='display: table-row;'>";
            if ($row["foglalta"] != "") {
                $html .= "<div class='tdm'>Foglalta: {$row["foglalta"]}&nbsp;&nbsp;</div>";
            }
            if (Booking_Constants::FO_CONNECTION_ENABLED) {
                $foColor = "lightgreen";
                if ($row["fofid"] == 0) {
                    $foColor = "red";
                }
                $html .= "<div class='tdm' style='padding:2px 0px;'><a onclick='foReservationInfo({$row["id"]},\"{$row["pass"]}\");return false;' href='#' style='color:{$foColor};'>Foglaljorvost info</a></div>";
            }
            $html .= "</div>";

            $html .= "<div style='margin-top:4px;'>";
            $html .= "<a class='middlebutton' href='#' onclick='startFoglalasMove({$row["id"]},\"{$row["pass"]}\");return false;'>áthelyezés</a> ";
            $html .= "<a class='middlebutton' href='#' onclick='startFoglalasCopy({$row["id"]},\"{$row["pass"]}\");return false;'>másolás</a> ";
            $html .= "<a class='middlebutton' href='#' onClick='autoFill(false);return false;'>mezők kitöltése</a> ";
            if ($this->user->user["username"] == "jns") {
                $html .= "<a class='middlebutton' href='#' onClick='duplicateReservation({$row["id"]},\"{$row["pass"]}\");return false;'>foglalás ismétlése</a> ";
            }

            if (!empty(trim($row["taj"])) && !empty($row["szuldatum"])) {
                $btext = "paciens létrehozása";
                $bstyle = "";
                if ($row["paciensid"] != 0) {
                    $btext = "paciens szinkronizálása";
                    $bstyle = "background:#0a0;";
                }
                $html .= "<a class='middlebutton' style='{$bstyle}' href='#' onClick='syncFoglalasDataToUser({$row['id']},\"{$row["pass"]}\");return false;'>{$btext}</a> ";
            }

            $html .= "</div>";
            $html .= "</div>";
            $html .= "<div id='moveinfo' style='display:none;background:#ff8;color:#555;padding:10px;'>Kattints arra az időpont melletti \"+\" gombra, ahova át akarod helyezni a foglalást.<div style='margin:3px 0px;'><a class='middlebutton' href='#' onclick='cancelFoglalasMove();return false;'>mégse</a></div></div>";
            $html .= "<div id='copyinfo' style='display:none;background:#ff8;color:#555;padding:10px;'>Kattints arra az időpont melletti \"+\" gombra, ahova át akarod <b>másolni</b> a foglalást.<br/>Több időponthoz is másolhatsz, ha befejezted kattints a mégse gombra.<div style='margin:3px 0px;'><a class='middlebutton' href='#' onclick='cancelFoglalasMove();return false;'>mégse</a></div></div>";

            $html .= "<div style='padding:10px;'>";

            if ($row["nev"] != "" && $row["nev"] != "nincs név") {
                $html .= "<div style='margin-bottom:5px;'>";
                //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=menedzserkerdoiv&fid={$row["id"]}&p={$row["pass"]}'>menedzser kérdőív</a>&nbsp;&nbsp;";
                $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=alkalmassagipdf&fid={$row["id"]}&p={$row["pass"]}'>alkalmassági</a>&nbsp;&nbsp;";
                //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=vizsgalatilap&tipus=idoszakos&fid={$row["id"]}&p={$row["pass"]}'>vizsgálati lap (I)</a>&nbsp;&nbsp;";
                //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=vizsgalatilap&tipus=soronkivuli&fid={$row["id"]}&p={$row["pass"]}'>vizsgálati lap (S)</a>&nbsp;&nbsp;";
                //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=karton&fid={$row["id"]}&p={$row["pass"]}'>karton</a>&nbsp;&nbsp;";
                $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=covidkerdoiv&fid={$row["id"]}&p={$row["pass"]}'>COVID kérdőív</a>&nbsp;&nbsp;";
                $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=menedzsersetalolap&fid={$row["id"]}&p={$row["pass"]}'>Menedzser sétálólap</a>&nbsp;&nbsp;";
                $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=nkfihsetalolap&fid={$row["id"]}&p={$row["pass"]}'>NKFIH sétálólap</a>&nbsp;&nbsp;";
                $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=matrica&fid={$row["id"]}&p={$row["pass"]}'>Matrica</a>&nbsp;&nbsp;";
                $html .= "</div>";
            }

            $allowNewCompany = $this->user->allCegJog() ? 1:0;
            $mustChooseCompany  = $this->user->allCegJog() ? 0:1;

            $html .= "<input type='hidden' name='fid' id='reservationId' value='{$row["id"]}'/>";
            $html .= "<input type='hidden' name='paciensid' id='paciensId' value='{$row["paciensid"]}'/>";
            $html .= "<input type='hidden' id='idopontmarker' value='" . substr($row["datum"], 0, 16) . "'/>";
            $html .= "<input type='hidden' name='p' id='reservationToken' value='{$row["pass"]}'/>";
            $html .= "<input type='hidden' name='mustChooseCompany' id='mustChooseCompany' value='{$mustChooseCompany}'/>";
            $html .= "<input type='hidden' name='allowNewCompany' id='allowNewCompany' value='{$allowNewCompany}'/>";
            $html .= "<table style='font-size:12px;'>";

            $html .= "<tr><td width='60'>Cég:</td><td width='226'>";
            $html .= "<select class='bookingeditorcegselector2' name='cegid' id='cegid' style='width:200px;'>";
            $html .= "<option value='0'>Nincs céghez kötve</option>";

            $cegFilter = "";
            if (!$this->user->allCegJog()) {
                $cegFilter = "and id in (" . $this->user->getCegList() . ")";
            }

            foreach (sql_query("select id, megnev from cegek where true {$cegFilter} order by megnev")->fetchAll(PDO::FETCH_ASSOC) as $company) {
                $html .= "<option value='{$company["id"]}'" . ($row["cegid"] == $company["id"] ? " selected" : "") . ">{$company["megnev"]}</option>";
            }
            $html .= "</select></td>";

            $nap = substr($row["datum"], 0, 10);
            $ora = substr($row["datum"], 11, 5);
            $wora = "AND TIME(b.tol)<=TIME('{$ora}') AND TIME(b.ig)>TIME('{$ora}')";

            $html .= "<td width='64'>Orvos:</td><td>";
            $html .= "<input type='hidden' name='regiorvos' value='{$row["orvosassigned"]}' />";
            $html .= "<select class='bookingeditorselector2' name='orvosassigned' style='width:180px;'>";
            $html .= "<option value='0'>Nincs orvoshoz kötve</option>";
            $resh = sql_query("SELECT o.*, SUM((b.nap=WEEKDAY('{$nap}')+1 or b.beonap='{$nap}') {$wora} AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2) OR (WEEK('{$nap}',3)%2=1 AND b.hetek=1)) and b.aktiv=1) as beovan
                  FROM orvos_beosztas_new b 
                  LEFT JOIN orvosok o ON o.`id`=b.`orvosid` 
                  WHERE b.helyszinid=? and instr(tipusok, '|{$row["szurestipusid"]}|')  
                  GROUP BY b.orvosid order by beovan desc, o.nev", [$_SESSION["helyszin"]]);
            while ($rowh = sql_fetch_array($resh)) {
                $s = "";
                if ($rowh["beovan"] == 0) {
                    $s = " style='color:#aaa;'";
                    $rowh["nev"] .= " / nincs beosztása erre az időpontra";
                }
                $html .= "<option value='{$rowh["id"]}'" . ($row["orvosassigned"] == $rowh["id"] ? " selected" : "") . " {$s}>{$rowh["nev"]}</option>";
            }
            $html .= "</select>&nbsp;&nbsp;<a href='#' onclick='foglalasOrvosErtesites();return false;' title='Orvos értesítése' style='font-size: 16px;'><i class='fas fa-envelope'></i></a></td>";
            $html .= "</td></tr>";

            if ($row["nev"] == "nincs név") {
                $row["nev"] = "";
            }

            $couponCode = "";
            if ($result = sql_fetch_array(sql_query("SELECT * FROM kupon_lista WHERE foglalasid={$row["id"]}"))) {
                $couponCode = $result["kuponkod"];
            }

            if ($row["paciensid"] == 0) {
                $html .= "<tr><td colspan='4' valign='top'>";
                $html .= "<div style='border-top:1px solid #888;border-bottom:1px solid #888;padding:10px 0px;margin:4px 0px 2px 0px;'>";
                $html .= "Ehhez a foglaláshoz nem tartozik paciens adatlap! Válassz a meglévő adatlapok közül, vagy hozz létre újat.";
                $html .= "<div style='margin-top: 3px;'>";
                $html .= "<a class='middlebutton' style='background:#f00;' href='#' onClick='prepareUserDataSearch();return false;'>paciens adatlap keresése</a>&nbsp;&nbsp;";
                $html .= "<a class='middlebutton' style='' href='#' onClick='newUserDataFromReservation();return false;'>új paciens adatlap létrehozása</a> ";
                $html .= "</div>";

                $html .= "</div>";
                $html .= "</td></tr>";
            }

            $html .= "<tr id='pdatasearchrow' style='display:none;'><td colspan='4' valign='top'>";
            $html .= "<div style='background:#eee;padding:10px 10px 10px 10px;margin:0px 0px 0px 0px;width:558px;height:167px;overflow: hidden;'>";
            $html .= "<div><input id='pdatasearchinput' type='text' value='' placeholder='kereshetsz névre, taj számra, telefonszámra, email címre...' style='width:100%;box-sizing: border-box;'/></div>";
            $html .= "<div id='searchpaciensresult'></div>";
            $html .= "</div>";
            $html .= "</td></tr>";

            $tajButton = "<a onClick='autoFill(false);return false;' href='#'><i class='fas fa-search'></i></a>";
            $userNotificationMark = sql_query("select id from notifications where tipus='usernotification' and objectid=? and destination=?", [$id, $row["email"]])->fetch(PDO::FETCH_ASSOC) ? " <i style='color:#08a;' title='Visszaigazoló email kiment erre a címre' class='fa-solid fa-circle-check'></i>" : "";

            $html .= "<tr class='pdatarow'>";
            $html .= "<td width='60'>Taj szám:</td><td><input data-taborder='1' class='inputbox ui-taborder editortaj2' style='width:180px;' type='text' id='editortaj' name='taj' value='{$row["taj"]}'> {$tajButton}</td>";
            $html .= "<td width='60'>E-mail:{$userNotificationMark}</td><td><input data-taborder='7' class='inputbox ui-taborder' style='width:172px;' type='text' name='email' value='{$row["email"]}'>&nbsp;&nbsp;<a href='#' onclick='manualNotificationSend({$row["id"]},\"{$row["pass"]}\");return false;' title='Paciens értesítése' style='font-size: 16px;'><i class='fas fa-envelope'></i></a></td>";
            $html .= "</tr>";
            $html .= "<tr class='pdatarow'>";
            $html .= "<td width='60'>Név:</td><td><input data-taborder='2' onclick='return false;' class='inputbox ui-taborder' placeholder='Ide csak nevet írj' style='width:200px;' type='text' name='nev' value='{$row["nev"]}'></td>";
            $html .= "<td width='60'>Telefon:</td><td><input data-taborder='8' class='inputbox ui-taborder' style='width:200px;' type='text' name='telefon' value='{$row["telefon"]}'></td>";
            $html .= "</tr>";
            $html .= "<tr class='pdatarow'>";
            $html .= "<td width='60'>Munkakör:</td><td>".$this->munkakorInput($row)."</td><td width='60'>Irsz:</td>";
            $html .= "<td><input data-taborder='9' placeholder='Irsz' class='inputbox ui-taborder' style='width:40px;' type='text' name='irsz' id='irsz' value='{$row["irsz"]}'> <input data-taborder='10' placeholder='Város' class='inputbox ui-taborder' style='width:150px;' type='text' name='varos' id='varos' value='{$row["varos"]}'></td>";
            $html .= "</tr>";
            $html .= "<tr class='pdatarow'>";
            $html .= "<td width='70'>Szül. dátum:</td><td><input data-taborder='4'  class='inputbox ui-taborder' style='width:200px;' type='text' name='szuldatum' id='editorszuldatum' value='{$row["szuldatum"]}' placeholder='éééé-hh-nn'/></td>";
            $html .= "<td width='60'>Utca:</td><td><input data-taborder='11' class='inputbox ui-taborder' style='width:200px;' type='text' name='utca' value='{$row["utca"]}'/></td>";
            $html .= "</tr>";
            $html .= "<tr class='pdatarow'>";
            $html .= "<td width='60'>Szül. hely:</td><td><input data-taborder='5'  class='inputbox ui-taborder' style='width:200px;' type='text' name='szulhely' value='{$row["szulhely"]}'></td>";
            $html .= "<td width='60'>Naplószám:</td><td><input data-taborder='12' class='inputbox ui-taborder' style='width:200px;' type='text' name='nszam' value='{$row["nszam"]}'></td>";
            $html .= "</tr>";
            $html .= "<tr class='pdatarow'>";
            $html .= "<td width='60'>Anyja neve:</td><td><input data-taborder='6'  class='inputbox ui-taborder' style='width:200px;' type='text' name='anyjaneve' value='{$row["anyjaneve"]}'></td>";

            if (!empty($row["adoszam"])) {
                $html .= "<td width='60'>Adószám:</td><td><input data-taborder='13' class='inputbox ui-taborder' style='width:200px;' type='text' name='adoszam' value='{$row["adoszam"]}'></td>";
            } else {
                $html .= "<td width='60'>Törzsszám:</td><td><input data-taborder='13' class='inputbox ui-taborder' style='width:200px;' type='text' name='torzsszam' value='{$row["torzsszam"]}'></td>";
            }
            //$html .= "<td width='60'>Kupon:</td><td><input data-taborder='13' type = 'text' style='width:140px' class='inputbox ui-taborder' name='kuponkod' value='{$couponCode}' id='kuponkod' />&nbsp;<input type = 'button' value = 'Check' onClick = '$(\"#coupondesc\").empty();$(\"#coupondiscount\").empty();kuponCheck($(\"#kuponkod\").val(),2,\"" . date("Y-m-d", strtotime($row["datum"])) . "\",{$row['szurestipusid']});return false'/></td>";
            $html .= "</tr>";
            $html .= "<tr class='pdatarow'>";
            $html .= "<td width='60'></td><td>" . ($row["ertesitve"] == 1 ? " (orv. értesítve)" : "") . " <span id='eljottchk'>".$this->eljottCheckbox($row)."</span> <input type='checkbox' name='voltnalunk' value='1' " . ($row["voltnalunk"] == 1 ? "checked" : "") . " /> volt már </td>";
            $html .= "<td><span id='coupondesc' ></span><br/><span id='coupondiscount'></span></td>";
            $html .= "</tr>";

            if ($this->user->paciensMegjegyzesAccess()) {
                $html .= "<tr><td colspan='2'><textarea data-taborder='14' class='ui-taborder' placeholder='Megjegyzés...' style='width:273px;height:60px;' name='megj'>{$row["megj"]}</textarea></td>";
                $html .= "<td colspan='2' valign='top'>".$this->_filesFolderNew($row)."</td>";
                $html .= "</tr>";
            }

            $html .= "<tr><td colspan='4' valign='top'><div style='background:#ccc;padding:5px;'>Egyéb</div>";


            $html .= "<div>Tüdőszűrő dátuma: <input type='text' style='width:80px;' name='tudoszuroervenyesseg' value='{$row["tudoszuroervenyesseg"]}' />&nbsp;&nbsp;";

            $html .= "<div style='display:inline-block;" . ($row["tudoszuro"] == 1 ? "background:#f00;color:#fff;" : "") . "'><input type='checkbox' name='tudoszuro' value='1' " . ($row["tudoszuro"] == 1 ? "checked" : "") . " /> tüdőszűrés kell</div>";

            $html .= "</td>";

            /*
            $html.= "<td valign='top' style=''>";
            $html.= "<div style='width:200px;overflow:hidden;'><div style='width:1000px;'>".($row['noreservation']!=1?$this->adminUtils->showPaciensFiles($row["id"]):"")."</div></div>";

            if ($rowa = sql_fetch_array(sql_query("select * from arak WHERE INSTR(cegid,?) AND tipusid=? and csomag=0",array("|{$row["cegid"]}|",$row["szurestipusid"])))) {
                $html.= "<div><a href='#' onclick='showFizSzolg({$row["id"]});return false;'>+ szolgáltatás hozzáadása</a><div>";
            }
            $html.= "<div id='fizszolglist{$row["id"]}'>".$this->adminUtils->showFizSzolg($row["id"])."</div>";

            $html.= "</td>";
            */

            $html .= "</tr>";
            $html .= "</table>";

            if (isset($_POST["page"])) {
                $_GET["page"] = $_POST["page"];
            }

            $html .= "<br><input type='button' class='ui-taborderon' onclick='foglalasMentes(\"{$_GET["page"]}\", {$allowNewCompany});' value='Mentés'/>&nbsp;&nbsp;";
            //$html.= "<input onclick='foglalasOrvosErtesites();' type='button' value='Orvos értesítése'/>&nbsp;&nbsp;";
            $html .= "<input onclick='$(\"#idoponteditor\").slideUp();cancelFoglalasMove();' type='button' value='Bezár'/>&nbsp;&nbsp;";

            $html .= "<input onclick='removeIdopont({$row["id"]},\"{$row["pass"]}\",\"{$_GET["page"]}\");' type='button' value='foglalás törlése' style='background: #f00'>&nbsp;&nbsp;";
            //$html .= "<input onClick='manualNotificationSend({$row["id"]},\"{$row["pass"]}\")' type='button' value='Értesítés küldése' style='background:#ffa500'>&nbsp;&nbsp;";

            if (Booking_Constants::COMPANY_NAME_SHORT == "Keltexmed") {
                $html .= "<input onClick='insertPaciensIntoDokirex({$row["id"]})' type='button' value='Dokirex Keltexmed' style='background:#5ed5e3'>&nbsp;&nbsp;";
                $html .= "<input onClick='insertPaciensIntoDokirexHMM({$row["id"]})' type='button' value='Dokirex HMM' style='background:#9d0202'>&nbsp;&nbsp;";
            } else {
                $html .= "<input onClick='insertPaciensIntoDokirex({$row["id"]})' type='button' value='Dokirex".(empty($row["dokirex_userid"])?"":" uid:{$row["dokirex_userid"]}")."' style='background:#008080'>&nbsp;&nbsp;";
            }

            $html.= "</div>";
            $html.= "</div>";
            $html.= "</form>";
        } else {
            $html.= "Az időpont adatok lekérdezése közben hiba történt! {$_GET["p"]}";
        }

        return $html;
    }

    private function munkakorInput($row) {
        $html = "";

        $html .= "<input data-taborder='3'  class='inputbox ui-taborder' style='width:200px;' type='text' name='munkakor' id='bookingeditormunkakor' value='{$row["munkakor"]}'>";

        $items = [];
        foreach (sql_query("SELECT TRIM(munkakor) as munkakor, COUNT(*) AS hany FROM foglalasok WHERE datum>'2022-02-01 00:00:00' and munkakor IS NOT NULL AND munkakor<>'' AND CHAR_LENGTH(munkakor)<40 GROUP BY TRIM(munkakor) ORDER BY TRIM(munkakor)")->fetchAll(PDO::FETCH_ASSOC) as $munkakor) {
            $items[] = "'".str_replace("'", "", $munkakor["munkakor"])."'";
        }

        $html.= "<script>$(function() { var munkakorok = [".implode(",", $items)."];$('#bookingeditormunkakor').autocomplete({source: function(request, response) { var results = $.ui.autocomplete.filter(munkakorok, request.term);response(results.slice(0, 14)); }}); });</script>";
        return $html;
    }


    public static function eljottCheckbox($reservationData):string {
        $icon = "<i class='far fa-square'></i>";
        if ($reservationData["eljott"] == 1) {
            $icon = "<i class='fas fa-check-square'></i>";
        }

        $html = "<a data-id='{$reservationData["id"]}' href='#' onclick='eljottButtonProtocol(this, 0);return false;' style='font-size: 16px;'>{$icon}</a> eljött";
        if ($reservationData["eljott"] == 1) {
            $html.= " <input style='width:35px;' name='eljottidopont' type='text' title='eljött időpont' value='".date("H:i", strtotime($reservationData["eljottidopont"]))."' />";
        }

        return $html;
    }

    private function _filesFolderNew($row):string {
        $html = "";
        $files = $this->adminUtils->showPaciensFiles($row["id"]);
        $html .= "<div style='padding:0px;width:275px;height:70px;overflow-y:auto;overflow-x: hidden;'><div style='width:1000px;'>{$files}</div>";
        $html .= "</div></div>";
        return $html;
    }

    private function _alkalmassagFolder($row):string {
        $html = "";
        $html .= "<div id='alkalmassagfolder' style='position:absolute;margin-top:120px;margin-left:-45px;z-index:-1;transition: all .1s linear;'>";
        $html .= "<div style='display:table-cell;vertical-align: top;'><div style='padding:4px;background:#ddd;border-bottom-left-radius: 5px;border-top-left-radius: 5px;'><img title='alkalmasság' onclick='toggleAlkalmassagBox();' src='images/achievement.webp' style='width:38px;cursor:pointer;' /></div></div>";
        $html .= "<div style='display:table-cell;vertical-align: top;'><div style='padding:8px;background:#ddd;'>";

        if (CompanyService::isFesztivalCompany($row["cegid"])) {
            $text = nl2br($row["questions"]);
            $text = str_replace("IGEN", "<span style='color:#a00;'>IGEN</span>", $text);
            $text = str_replace("NEM", "<span style='color:#0a0;'>NEM</span>", $text);
            $html.= "<div style='margin:3px 5px;font-weight: bold;'>{$text}</div>";
            if (empty($row["orvosszoveg"])) {
                $row["orvosszoveg"] = CompanyService::FESZTIVAL_ALKALMASSAGI_DEFAULT_TEXT;
            }
        }

        $html.= "<div class='mainalkform'>";
        foreach ($this->adminUtils->settings->alkalmassagvariaciok as $key => $value) {
            $oc = "";
            $sb = "";
            if ($key != "I") {
                $oc = "onclick=\"$('input[name=alkalmassagido]').attr('checked',false);\"";
                $sb = "border-top:1px solid #999;margin-top:3px;padding-top:3px;";
            }
            $html .= "<div style='{$sb}'><input " . ($row["alkalmassag"] == $key ? "checked" : "") . " {$oc} type='radio' name='alkalmassag' value='{$key}' /> {$value}";
            if ($key == "I") $html .= "<div style='padding:0px 0px 0px 25px;'>
                    <input " . ($row["alkalmassagido"] == 3 ? "checked" : "") . " type='radio' name='alkalmassagido' value='3' />3 hó 
                    <input " . ($row["alkalmassagido"] == 6 ? "checked" : "") . " type='radio' name='alkalmassagido' value='6' />6 hó 
                    <input " . ($row["alkalmassagido"] == 12 ? "checked" : "") . " type='radio' name='alkalmassagido' value='12' />1 év 
                    <input " . ($row["alkalmassagido"] == 24 ? "checked" : "") . " type='radio' name='alkalmassagido' value='24' />2 év 
                    <input " . ($row["alkalmassagido"] == 36 ? "checked" : "") . " type='radio' name='alkalmassagido' value='36' />3 év
                    </div>";
            if ($key == "IN") {
                $html .= "<div style='padding:0px 0px 0px 25px;'>köv. vizsgálat: <input type='text' style='width:40px;' name='alkalmassagikhet' value='{$row["alkalmassagikhet"]}' /> hét</div>";
            }
            if ($key == "K") {
                $html .= "<div style='padding:3px 0px 0px 25px;'><textarea placeholder='korlátozás szövege' style='width:240px;height:40px;' name='alkalmassagkorl'>{$row["alkalmassagkorl"]}</textarea></div>";
            }
            $html .= "</div>";
        }

        $html .= "<div style='padding:0px 0px 0px 25px;'>vérnyomás: <input type='text' style='width:70px;' name='vernyomas' value='{$row["vernyomas"]}' /></div>";

        $html .= "</div>";
        return $html;
    }


}