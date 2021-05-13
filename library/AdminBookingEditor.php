<?php

class AdminBookingEditor {

    private $adminUtils;
    private $utils;
    private $bookingService;
    private $user;

    public function __construct()
    {
        $this->adminUtils = new AdminUtils();
        $this->utils = new Utils();
        $this->bookingService = new BookingService();
        $this->user = new AdminUser();

        if (isset($_GET["showidoponteditor"])) {
            echo $this->_showBookingEditor($_GET["showidoponteditor"], $_GET["p"]);
            die();
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
                if (ctype_digit($_POST["szuldatum"]) && strlen(trim($_POST["szuldatum"])) == 8) {
                    $_POST["szuldatum"] = substr($_POST["szuldatum"], 0, 4)."-".substr($_POST["szuldatum"], 4, 2)."-".substr($_POST["szuldatum"], 6, 2);
                }
            }

            if (!isset($_POST["eljott"])) $_POST["eljott"]=0;
            if (!isset($_POST["voltnalunk"])) $_POST["voltnalunk"]=0;
            if (!isset($_POST["alkalmassag"])) $_POST["alkalmassag"]=0;
            if (!isset($_POST["alkalmassagido"])) $_POST["alkalmassagido"]=0;
            if (!isset($_POST["tudoszuro"])) $_POST["tudoszuro"]=0;

            if ($_POST["nev"]=="") $_POST["nev"]="nincs név";

            sql_query("update foglalasok set
                orvosassigned=?,
                cegid=?,
                taj=?,
                nszam=?,
                nev=?,
                munkakor=?,
                email=?,
                telefon=?,
                szuldatum=?,
                szulhely=?,
                anyjaneve=?,
                irsz=?,
                varos=?,
                utca=?,
                eljott=?,
                voltnalunk=?,
                alkalmassag=?,
                alkalmassagido=?,
                alkalmassagikhet=?,
                alkalmassagkorl=?,
                tudoszuroervenyesseg=?,
                tudoszuro=?,
                megj=?
            where id=?", [intval($_POST["orvosassigned"]), intval($_POST["cegid"]), $_POST["taj"], $_POST["nszam"], $_POST["nev"], $_POST["munkakor"], $_POST["email"], $_POST["telefon"], $_POST["szuldatum"], $_POST["szulhely"], $_POST["anyjaneve"],
                $_POST["irsz"], $_POST["varos"], $_POST["utca"], $_POST["eljott"], $_POST["voltnalunk"], $_POST["alkalmassag"], $_POST["alkalmassagido"], $_POST["alkalmassagikhet"], $_POST["alkalmassagkorl"], $_POST["tudoszuroervenyesseg"], $_POST["tudoszuro"], $_POST["megj"], $fid]);


            if (!empty($_POST["paciensid"])) {
                sql_query("update foglalasok set paciensid=? where id=? and paciensid=0", [$_POST["paciensid"], $fid]);
            }

            $alkalmassagi = "";
            if($_POST['alkalmassag'] === "I") {
                $alkalmassagi = date("Y-m-d",strtotime("Now + {$_POST['alkalmassagido']} months"));
                echo "I";
            }
            if($_POST['alkalmassag'] === "N") {
                $alkalmassagi = "0000-00-00 00:00:00";
                echo "N";
            }
            if($_POST['alkalmassag'] === "IN") {
                $alkalmassagi = date("Y-m-d",strtotime("Now + {$_POST['alkalmassagikhet']} weeks"));
                echo "IN";
            }
            if($_POST['alkalmassag'] === "K") {
                $alkalmassagi = date("Y-m-d",strtotime("Now + {$_POST['alkalmassagido']} months"));
                echo "K";
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
                        array( $kupon['id'], $kupon['kod'], $fid, $_SESSION['adminuser']['username'] ));
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
                $this->bookingService->sendToCegAndOrvos($fid,1);
            }

            $foService = new FoglaljOrvostService();
            $foService->modifyReservation($fid);

            $api = new BookingSyncApi();
            $api->modifyReservation($fid);

            echo $this->_showBookingEditor($fid, $_POST["p"]);
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
            if (empty($_REQUEST["szuldatumev"]) || empty($_REQUEST["szuldatumho"]) || empty($_REQUEST["szuldatumnap"])) {
                $error .= "A születési dátum megadása kötelező!\n";
            }

            $userId = 0;
            if (!isset($_REQUEST["torzsszam"])) {
                $_REQUEST["torzsszam"] = "";
            }

            if (empty($error)) {
                $_REQUEST["szuldatum"] = $_REQUEST["szuldatumev"]."-".substr("00".$_REQUEST["szuldatumho"],-2)."-".substr("00".$_REQUEST["szuldatumnap"],-2);

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
            if (!isset($_SESSION["adminuser"])) {
                $this->utils->jsonOut(["error" => "error"]);
            }

            $TAJ = $_REQUEST['AFForm'];
            if ($data = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE taj = ?", [$TAJ]))) {
                $data["error"] = "";
            } else {
                $data["error"] = "Ezzel a TAJ számmal felhasználó nem található!";
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

            $html.= "<form id='iform' name='iform' method='post' enctype='multipart/form-data'>";

            $files = $this->adminUtils->showPaciensFiles($row["id"]);
            if (!empty($files) && $row["noreservation"] != 1) {
                $html .= "<div id='uploadfilesfolder' style='position:absolute;margin-top:120px;margin-left:-45px;z-index:-1;transition: all .1s linear;'>";
                $html .= "<div style='display:table-cell;vertical-align: top;'><div style='padding:8px;background:#ddd;border-bottom-left-radius: 5px;border-top-left-radius: 5px;'><img title='feltöltött fájlok' onclick='toggleUploadFiles();' src='images/Files-PNG-File.png' style='width:30px;cursor:pointer;' /></div></div>";
                $html .= "<div style='display:table-cell;vertical-align: top;'><div style='padding:8px;width:200px;background:#ddd;overflow:hidden;'><div style='width:1000px;'>{$files}</div></div></div>";
                $html .= "</div>";
            }

            $html .= "<div id='alkalmassagfolder' style='position:absolute;margin-top:170px;margin-left:-45px;z-index:-1;transition: all .1s linear;'>";
            $html .= "<div style='display:table-cell;vertical-align: top;'><div style='padding:4px;background:#ddd;border-bottom-left-radius: 5px;border-top-left-radius: 5px;'><img title='alkalmasság' onclick='toggleAlkalmassagBox();' src='images/achievement.webp' style='width:38px;cursor:pointer;' /></div></div>";
            $html .= "<div style='display:table-cell;vertical-align: top;'><div style='padding:8px;background:#ddd;'>";

            foreach ($this->adminUtils->settings->alkalmassagvariaciok as $key => $value) {
                $oc = "";
                $sb = "";
                if ($key != "I") {
                    $oc="onclick=\"$('input[name=alkalmassagido]').attr('checked',false);\"";
                    $sb = "border-top:1px solid #999;margin-top:3px;padding-top:3px;";
                }
                $html.= "<div style='{$sb}'><input ".($row["alkalmassag"]==$key?"checked":"")." {$oc} type='radio' name='alkalmassag' value='{$key}' /> {$value}";
                if ($key == "I") $html.= "<div style='padding:0px 0px 0px 25px;'>
                    <input ".($row["alkalmassagido"]==3?"checked":"")." type='radio' name='alkalmassagido' value='3' />3 hó 
                    <input ".($row["alkalmassagido"]==6?"checked":"")." type='radio' name='alkalmassagido' value='6' />6 hó 
                    <input ".($row["alkalmassagido"]==12?"checked":"")." type='radio' name='alkalmassagido' value='12' />1 év 
                    <input ".($row["alkalmassagido"]==24?"checked":"")." type='radio' name='alkalmassagido' value='24' />2 év 
                    <input ".($row["alkalmassagido"]==36?"checked":"")." type='radio' name='alkalmassagido' value='36' />3 év
                    </div>";
                if ($key == "IN") {
                    $html.= "<div style='padding:0px 0px 0px 25px;'>köv. vizsgálat: <input type='text' style='width:40px;' name='alkalmassagikhet' value='{$row["alkalmassagikhet"]}' /> hét</div>";
                }
                if ($key == "K") {
                    $html.= "<div style='padding:3px 0px 0px 25px;'><textarea placeholder='korlátozás szövege' style='width:240px;height:40px;' name='alkalmassagkorl'>{$row["alkalmassagkorl"]}</textarea></div>";
                }
                $html.= "</div>";
            }

            $html .= "</div>";
            $html .= "</div>";
            $html .= "</div>";


            $html.= "<div style='position:relative;background:#e0e0e0;'>";
            $html.= "<div style='padding:10px;background:#555;color:#fff;'><span style='font-size:16px;font-weight:bold;' title='Foglalás ideje:{$row['regdatum']}'>".$this->adminUtils->magyarDatum($row["datum"])." - {$row["sztipus"]}</span>";
            $html.= "<div style='display: table-row;'>";
            if ($row["foglalta"]!="") {
                $html.= "<div class='tdm'>Foglalta: {$row["foglalta"]}&nbsp;&nbsp;</div>";
            }
            if (Booking_Constants::FO_CONNECTION_ENABLED) {
                $foColor = "lightgreen";
                if ($row["fofid"] == 0) {
                    $foColor = "red";
                }
                $html.= "<div class='tdm' style='padding:2px 0px;'><a onclick='foReservationInfo({$row["id"]},\"{$row["pass"]}\");return false;' href='#' style='color:{$foColor};'>Foglaljorvost info</a></div>";
            }
            $html.= "</div>";

            $html.= "<div style='margin-top:4px;'>";
            $html.= "<a class='middlebutton' href='#' onclick='startFoglalasMove({$row["id"]},\"{$row["pass"]}\");return false;'>áthelyezés</a> ";
            $html.= "<a class='middlebutton' href='#' onclick='startFoglalasCopy({$row["id"]},\"{$row["pass"]}\");return false;'>másolás</a> ";
            $html.= "<a class='middlebutton' href='#' onClick='autoFill();return false;'>mezők kitöltése</a> ";

            if (!empty(trim($row["taj"])) && !empty($row["szuldatum"])) {
                $btext = "paciens létrehozása";
                $bstyle = "";
                if ($row["paciensid"] != 0) {
                    $btext = "paciens szinkronizálása";
                    $bstyle= "background:#0a0;";
                }
                $html.= "<a class='middlebutton' style='{$bstyle}' href='#' onClick='syncFoglalasDataToUser({$row['id']},\"{$row["pass"]}\");return false;'>{$btext}</a> ";
            }

            $html.= "</div>";
            $html.= "</div>";
            $html.= "<div id='moveinfo' style='display:none;background:#ff8;color:#555;padding:10px;'>Kattints arra az időpont melletti \"+\" gombra, ahova át akarod helyezni a foglalást.<div style='margin:3px 0px;'><a class='middlebutton' href='#' onclick='cancelFoglalasMove();return false;'>mégse</a></div></div>";
            $html.= "<div id='copyinfo' style='display:none;background:#ff8;color:#555;padding:10px;'>Kattints arra az időpont melletti \"+\" gombra, ahova át akarod <b>másolni</b> a foglalást.<br/>Több időponthoz is másolhatsz, ha befejezted kattints a mégse gombra.<div style='margin:3px 0px;'><a class='middlebutton' href='#' onclick='cancelFoglalasMove();return false;'>mégse</a></div></div>";

            $html.= "<div style='padding:10px;'>";

            if ($row["nev"]!="" && $row["nev"]!="nincs név") {
                $html.= "<div style='margin-bottom:5px;'>";
                //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=menedzserkerdoiv&fid={$row["id"]}&p={$row["pass"]}'>menedzser kérdőív</a>&nbsp;&nbsp;";
                $html.= "<a class='printbutton' target='_blank' href='index.php?print&template=alkalmassagi&fid={$row["id"]}&p={$row["pass"]}'>alkalmassági</a>&nbsp;&nbsp;";
                //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=vizsgalatilap&tipus=idoszakos&fid={$row["id"]}&p={$row["pass"]}'>vizsgálati lap (I)</a>&nbsp;&nbsp;";
                //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=vizsgalatilap&tipus=soronkivuli&fid={$row["id"]}&p={$row["pass"]}'>vizsgálati lap (S)</a>&nbsp;&nbsp;";
                //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=karton&fid={$row["id"]}&p={$row["pass"]}'>karton</a>&nbsp;&nbsp;";
                $html.= "<a class='printbutton' target='_blank' href='index.php?print&template=covidkerdoiv&fid={$row["id"]}&p={$row["pass"]}'>COVID kérdőív</a>&nbsp;&nbsp;";
                $html.= "<a class='printbutton' target='_blank' href='index.php?print&template=menedzsersetalolap&fid={$row["id"]}&p={$row["pass"]}'>Menedzser sétálólap</a>&nbsp;&nbsp;";
                $html.= "</div>";
            }

            $html.= "<input type='hidden' name='fid' id='reservationId' value='{$row["id"]}'/>";
            $html.= "<input type='hidden' name='paciensid' value='{$row["paciensid"]}'/>";
            $html.= "<input type='hidden' id='idopontmarker' value='".substr($row["datum"],0,16)."'/>";
            $html.= "<input type='hidden' name='p' id='reservationToken' value='{$row["pass"]}'/>";
            $html.= "<table style='font-size:12px;'>";

            $html.= "<tr><td width='60'>Cég:</td><td width='226'>";
            $html.= "<select name='cegid' style='width:200px;'>";
            $html.= "<option value='0'>Nincs céghez kötve</option>";
            $wCeg = $this->adminUtils->cegSQLFilter("b.cegid");
            $resh=sql_query("SELECT c.* FROM orvos_beosztas b 
                  LEFT JOIN cegek c ON c.`id`=b.`cegid` 
                  WHERE b.`helyszinid`=? and instr(tipusok,'|{$row["szurestipusid"]}|') {$wCeg} and c.id is not null
                  GROUP BY b.`cegid` order by c.megnev",array($_SESSION["helyszin"]));
            while ($rowh=sql_fetch_array($resh)) {
                $html.= "<option value='{$rowh["id"]}'".($row["cegid"]==$rowh["id"]?" selected":"").">{$rowh["megnev"]}</option>";
            }
            $html.= "</select></td>";

            $nap=substr($row["datum"],0,10);
            $ora=substr($row["datum"],11,5);
            $wora="AND TIME(b.tol)<=TIME('{$ora}') AND TIME(b.ig)>TIME('{$ora}')";

            $html.= "<td width='64'>Orvos:</td><td>";
            $html.= "<input type='hidden' name='regiorvos' value='{$row["orvosassigned"]}' /><select name='orvosassigned' style='width:200px;'>";
            $html.= "<option value='0'>Nincs orvoshoz kötve</option>";
            $resh=sql_query("SELECT o.*,
                  SUM((b.nap=WEEKDAY('{$nap}')+1 or b.beonap='{$nap}') {$wora} AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2) OR (WEEK('{$nap}',3)%2=1 AND b.hetek=1)) and b.aktiv=1) as beovan
                  FROM orvos_beosztas b 
                  LEFT JOIN orvosok o ON o.`id`=b.`orvosid` 
                  WHERE b.`helyszinid`=? and instr(tipusok,'|{$row["szurestipusid"]}|') {$wCeg} 
                  GROUP BY b.`orvosid` order by beovan desc,o.nev",array($_SESSION["helyszin"]));
            while ($rowh=sql_fetch_array($resh)) {
                $s="";
                if ($rowh["beovan"]==0) {
                    $s=" style='color:#aaa;'";
                    $rowh["nev"].=" / nincs beosztása erre az időpontra";
                }
                $html.= "<option value='{$rowh["id"]}'".($row["orvosassigned"]==$rowh["id"]?" selected":"")." {$s}>{$rowh["nev"]}</option>";
            }
            $html.= "</select></td>";
            $html.= "</td></tr>";

            if ($row["nev"] == "nincs név") {
                $row["nev"]="";
            }

            $result = sql_fetch_array(sql_query("SELECT * FROM kupon_lista WHERE foglalasid={$row["id"]}"));

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

            $html.= "<tr class='pdatarow'><td width='60'>Taj szám:</td><td><input data-taborder='1' class='inputbox ui-taborder' style='width:200px;' type='text' id='editortaj' name='taj' value='{$row["taj"]}'></td><td width='60'>E-mail:</td><td><input data-taborder='7' class='inputbox ui-taborder' style='width:200px;' type='text' name='email' value='{$row["email"]}'></td></tr>";
            $html.= "<tr class='pdatarow'><td width='60'>Név:</td><td><input data-taborder='2' onclick='return false;' class='inputbox ui-taborder' style='width:200px;' type='text' name='nev' value='{$row["nev"]}'></td><td width='60'>Telefon:</td><td><input data-taborder='8' class='inputbox ui-taborder' style='width:200px;' type='text' name='telefon' value='{$row["telefon"]}'></td></tr>";
            $html.= "<tr class='pdatarow'><td width='60'>Munkakör:</td><td><input data-taborder='3'  class='inputbox ui-taborder' style='width:200px;' type='text' name='munkakor' value='{$row["munkakor"]}'></td><td width='60'>Irsz:</td><td><input data-taborder='9' placeholder='Irsz' class='inputbox ui-taborder' style='width:40px;' type='text' name='irsz' id='irsz' value='{$row["irsz"]}'> <input data-taborder='10' placeholder='Város' class='inputbox ui-taborder' style='width:150px;' type='text' name='varos' id='varos' value='{$row["varos"]}'></td></tr>";
            $html.= "<tr class='pdatarow'><td width='60'>Szül. dátum:</td><td><input data-taborder='4'  class='inputbox ui-taborder' style='width:200px;' type='text' name='szuldatum' value='{$row["szuldatum"]}' placeholder='éééé-hh-nn'/></td><td width='60'>Utca:</td><td><input data-taborder='11' class='inputbox ui-taborder' style='width:200px;' type='text' name='utca' value='{$row["utca"]}'/></td></tr>";
            $html.= "<tr class='pdatarow'><td width='60'>Szül. hely:</td><td><input data-taborder='5'  class='inputbox ui-taborder' style='width:200px;' type='text' name='szulhely' value='{$row["szulhely"]}'></td><td width='60'>Naplószám:</td><td><input data-taborder='12' class='inputbox ui-taborder' style='width:200px;' type='text' name='nszam' value='{$row["nszam"]}'></td></tr>";
            $html.= "<tr class='pdatarow'><td width='60'>Anyja neve:</td><td><input data-taborder='6'  class='inputbox ui-taborder' style='width:200px;' type='text' name='anyjaneve' value='{$row["anyjaneve"]}'></td><td width='60'>Kupon:</td><td><input data-taborder='13' type = 'text' style='width:140px' class='inputbox ui-taborder' name='kuponkod' value='{$result['kuponkod']}' id='kuponkod' />&nbsp;<input type = 'button' value = 'Check' onClick = '$(\"#coupondesc\").empty();$(\"#coupondiscount\").empty();kuponCheck($(\"#kuponkod\").val(),2,\"".date("Y-m-d",strtotime($row["datum"]))."\",{$row['szurestipusid']});return false'/></td></tr>";
            $html.= "<tr class='pdatarow'><td width='60'></td><td>".($row["ertesitve"]==1?" (orv. értesítve)":"")." <input type='checkbox' name='eljott' value='1' ".($row["eljott"]==1?"checked":"")." /> eljött <input type='checkbox' name='voltnalunk' value='1' ".($row["voltnalunk"]==1?"checked":"")." /> volt már </td><td></td><td><span id='coupondesc' ></span><br/><span id='coupondiscount'></span></td></tr>";
            //$html.= "<tr class='pdatarow'><td width='60'>Munkáltató:</td><td><input class='inputbox' style='width:200px;' type='text' name='munkaltato' value='{$_POST["munkaltato"]}'></td></tr>";
            //$html.= "<tr class='pdatarow'><td width='60'>Munkakör:</td><td><input class='inputbox' style='width:200px;' type='text' name='munkakor' value='{$_POST["munkakor"]}'></td></tr>";
            $html.= "</td></tr>";
            //$html.= "<tr><td width='60'>Naplószám:</td><td><input class='inputbox' style='width:200px;' type='text' name='nszam' value='{$row["nszam"]}'></td><td></td><td>".($row["ertesitve"]==1?" (orvos értesítve)":"")." <input type='checkbox' name='eljott' value='1' ".($row["eljott"]==1?"checked":"")." /> eljött</td></tr>";
            $html.= "<tr><td width='60'>Megjegyzés:</td><td colspan='3'><textarea data-taborder='14' class='ui-taborder' style='width:98%;height:60px;' name='megj'>{$row["megj"]}</textarea></td></tr>";



            $html.= "<tr><td colspan='4' valign='top'><div style='background:#ccc;padding:5px;'>Egyéb</div>";


            $html.= "<div>Tüdőszűrő dátuma: <input type='text' style='width:80px;' name='tudoszuroervenyesseg' value='{$row["tudoszuroervenyesseg"]}' />&nbsp;&nbsp;";

            $html.= "<div style='display:inline-block;".($row["tudoszuro"]==1?"background:#f00;color:#fff;":"")."'><input type='checkbox' name='tudoszuro' value='1' ".($row["tudoszuro"]==1?"checked":"")." /> tüdőszűrés kell</div>";

            $html.= "</td>";

            /*
            $html.= "<td valign='top' style=''>";
            $html.= "<div style='width:200px;overflow:hidden;'><div style='width:1000px;'>".($row['noreservation']!=1?$this->adminUtils->showPaciensFiles($row["id"]):"")."</div></div>";

            if ($rowa = sql_fetch_array(sql_query("select * from arak WHERE INSTR(cegid,?) AND tipusid=? and csomag=0",array("|{$row["cegid"]}|",$row["szurestipusid"])))) {
                $html.= "<div><a href='#' onclick='showFizSzolg({$row["id"]});return false;'>+ szolgáltatás hozzáadása</a><div>";
            }
            $html.= "<div id='fizszolglist{$row["id"]}'>".$this->adminUtils->showFizSzolg($row["id"])."</div>";

            $html.= "</td>";
            */

            $html.= "</tr>";
            $html.= "</table>";

            if (isset($_POST["page"])) {
                $_GET["page"] = $_POST["page"];
            }
            $html.= "<br><input type='button' class='ui-taborderon' onclick='foglalasMentes(\"{$_GET["page"]}\");' value='Mentés'/>&nbsp;&nbsp;";
            $html.= "<input onclick='foglalasOrvosErtesites();' type='button' value='Orvos értesítése'/>&nbsp;&nbsp;";
            $html.= "<input onclick='$(\"#idoponteditor\").slideUp();cancelFoglalasMove();' type='button' value='Bezár'/>&nbsp;&nbsp;";

            $html.="<input onclick='removeIdopont({$row["id"]},\"{$row["pass"]}\",\"{$_GET["page"]}\");' type='button' value='foglalás törlése' style='background: #f00'>&nbsp;&nbsp;";
            $html.="<input onClick='manualNotificationSend({$row["id"]},\"{$row["pass"]}\")' type='button' value='Értesítés küldése' style='background:#ffa500'>&nbsp;&nbsp;";
            $html.="<input onClick='insertPaciensIntoDokirex({$row["id"]})' type='button' value='Dokirex' style='background:#008080'>&nbsp;&nbsp;";

            $html.= "</div>";
            $html.= "</div>";
            $html.= "</form>";
        } else {
            $html.= "Az időpont adatok lekérdezése közben hiba történt! {$_GET["p"]}";
        }

        return $html;
    }


}