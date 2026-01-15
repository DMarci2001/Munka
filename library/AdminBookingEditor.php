<?php

class AdminBookingEditor {

    private $adminUtils;
    private $utils;
    private $bookingService;
    private $beosztasService;
    private $user;
    private $notificationService;
    private $varoteremService;

    private array $alkQuestions = [
        "Allergiája van?",
        "Gyógyszerérzékenysége van?",
        "Szed rendszeresen gyógyszert?",
        "Kezelik valamilyen betegséggel?"
    ];

    public function __construct()
    {
        $this->adminUtils = new AdminUtils();
        $this->utils = new Utils();
        $this->bookingService = new BookingService();
        $this->beosztasService = new BeosztasService();
        $this->user = new AdminUser();
        $this->notificationService = new NotificationService();
        $this->varoteremService = new VaroteremService();

        if (isset($_POST["setdefaulttelephely"])) {
            $cegId = $_POST["setdefaulttelephely"];
            echo $this->telephelySelector($cegId, 0);
            die;
        }

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

        if (isset($_POST["savetimemod"])) {
            $fid = intval($_POST["fid"]);
            $p = $_POST["p"];

            $reservationData = sql_query("select datum from foglalasok where id=?", [$fid])->fetch(PDO::FETCH_ASSOC);

            $newTime = date("Y-m-d", strtotime($reservationData["datum"]))." ".$_POST["modTime"];

            Utils::jsonOut(["status" => "Fejlesztés alatt...".$newTime, "html" => $this->_showBookingEditor($fid, $p)]);
            die;
        }



        if (isset($_POST["foglalasmentesnaptar2"]) || isset($_POST["foglalasmentesnaptaresertesites2"]) && $this->user->authenticated()) {
            $fid = intval($_POST["fid"]);
            $reservationData = sql_query("select datum, taj, szuldatum from foglalasok where id=?", [$fid])->fetch(PDO::FETCH_ASSOC);
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
                $eljottIdopont = date("Y-m-d", strtotime($reservationData["datum"]))." ".$_POST["eljottidopont"].":00";
            }

            
            if (!isset($_POST["voltnalunk"])) $_POST["voltnalunk"]=0;
            if (!isset($_POST["tudoszuro"])) $_POST["tudoszuro"]=0;
            if (!isset($_POST["kieg_labor"])) $_POST["kieg_labor"]=0;
            if (!isset($_POST["kieg_hallas"])) $_POST["kieg_hallas"]=0;
            if (!isset($_POST["torzsszam"])) $_POST["torzsszam"] = "";
            if (!isset($_POST["adoszam"])) $_POST["adoszam"] = "";
            if (!isset($_POST["neme"])) $_POST["neme"]=0;
            if (!isset($_POST["nszam"])) $_POST["nszam"]="";
            if (!isset($_POST["testalkat"])) $_POST["testalkat"]=0;
            if (!isset($_POST["dokirexmunkakorid"])) $_POST["dokirexmunkakorid"]=0;
            if (!isset($_POST["dokirexcegid"])) $_POST["dokirexcegid"]=0;
            if (!isset($_POST["telephelyid"])) $_POST["telephelyid"]=0;
            

            if ($_POST["nev"]=="") $_POST["nev"]="nincs név";

            if (!is_numeric($_POST["cegid"])) {
                sql_query("insert into cegek set megnev=?, aktiv=1", [$_POST["cegid"]]);
                $_POST["cegid"] = sql_insert_id();
                $this->notificationService->newCompanyNotification($_POST["cegid"]);
            }

            $this->setAuchanDataToAll($_POST, $fid);

            sql_query("update foglalasok set
                modifiedby=?,
                modifiedtime=now(),
                orvosassigned=?,
                cegid=?,
                telephelyid=?,
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
                neme=?,
                testalkat=?,
                irsz=?,
                varos=?,
                utca=?,
                voltnalunk=?,
                tudoszuroervenyesseg=?,
                tudoszuro=?,
                kieg_labor=?,
                kieg_hallas=?,
                eljottidopont=?,
                dokirexmunkakorid=?,
                dokirexcegid=?
            where id=?", [$this->user->user["username"], intval($_POST["orvosassigned"]), intval($_POST["cegid"]), intval($_POST["telephelyid"]), $_POST["taj"], $_POST["nszam"], $_POST["torzsszam"], $_POST["nev"], $_POST["munkakor"], $_POST["adoszam"], $_POST["email"], $_POST["telefon"], $_POST["szuldatum"], $_POST["szulhely"], $_POST["anyjaneve"],$_POST["neme"],$_POST["testalkat"],
                $_POST["irsz"], $_POST["varos"], $_POST["utca"], $_POST["voltnalunk"], $_POST["tudoszuroervenyesseg"], $_POST["tudoszuro"], $_POST["kieg_labor"],$_POST["kieg_hallas"],
                $eljottIdopont, $_POST["dokirexmunkakorid"], $_POST["dokirexcegid"], $fid]);



            $day = date("Y-m-d", strtotime($reservationData["datum"]));

            //Kiegészítő vizsgálatok adatainak módosítása hogy szinkronban legyen a parent foglalással
            sql_query("update foglalasok set
                modifiedby=?,
                modifiedtime=now(),
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
                neme=?,
                testalkat=?,
                irsz=?,
                varos=?,
                utca=?,
                dokirexmunkakorid=?,
                dokirexcegid=?
                WHERE parentid=? and parentid<>0 and datum>'{$day} 00:00:00' and datum<'{$day} 23:59:59' LIMIT 10", [$this->user->user["username"], intval($_POST["cegid"]), $_POST["taj"], $_POST["nszam"], $_POST["torzsszam"], $_POST["nev"], $_POST["munkakor"], $_POST["adoszam"], $_POST["email"], $_POST["telefon"], $_POST["szuldatum"], $_POST["szulhely"], $_POST["anyjaneve"],$_POST["neme"],$_POST["testalkat"],
                    $_POST["irsz"], $_POST["varos"], $_POST["utca"], $_POST["dokirexmunkakorid"], $_POST["dokirexcegid"], $fid]);

            //mégse írjuk felül a manager foglalás megjegyzéseket
            //if (isset($_POST["megj"])) {
            //    sql_query("update foglalasok set megj=? where megj='' and parentid=? and parentid<>0 and datum>'{$day} 00:00:00' and datum<'{$day} 23:59:59' LIMIT 10", [$_POST["megj"], $fid]);
            //}

            if (!empty($_POST["paciensid"])) {
                //páciens adatok tárolása, taj és születési dátum változás esetén paciensid törlése
                if (session_id() == "olkknm28hi3q7gj63jach71071") {
                    if ($reservationData["taj"] != $_POST["taj"] || $reservationData["szuldatum"] != $_POST["szuldatum"]) {
                        $_POST["paciensid"] = 0;
                        sql_query("update foglalasok set paciensid=0 where id=?", [$fid]);
                    } else {
                        sql_query("update felhasznalok set nev=?, telefon=?, szulhely=?, anyjaneve=?, telefon=?, email=?, neme=?, irsz=?, varos=?, utca=?, munkakor=?, torzsszam=? where id=? limit 1"
                            , [$_POST["nev"], $_POST["telefon"], $_POST["szulhely"], $_POST["anyjaneve"], $_POST["telefon"], $_POST["email"], $_POST["neme"], $_POST["irsz"], $_POST["varos"], $_POST["utca"], $_POST["munkakor"], $_POST["torzsszam"], $_POST["paciensid"]]
                        );
                    }
                }
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

            if ($_POST["orvosassigned"] != $_POST["regiorvos"]) {
                sql_query("update foglalasok set ertesitve=0 where id=?",array($fid));
            }

            $rowf = sql_fetch_array(sql_query("select * from foglalasok where id=?",array($fid)));
            logActivity("foglalas",$fid,"{$_POST["nev"]} foglalás adatlap {$rowf["datum"]}", json_encode($_POST,JSON_PRETTY_PRINT));

            if ($_POST["orvosassigned"]==0 && $_POST["cegid"]!=0) {
                $oid = $this->bookingService->selectFreeOrvosForIdopont($fid);
                //$rowo=sql_fetch_array(selectOrvosForFoglalas($fid));érfi 
                sql_query("update foglalasok set orvosassigned=? where id=? and orvosassigned=0",array($oid, $fid));
            }

            if (isset($_POST["foglalasmentesnaptaresertesites2"])) {
                $this->bookingService->notificationService->sendToCegAndOrvos($fid,1);
            }

            //Itt azt akarom elérni, hogy ha nem 0 a dokirexcegid, akkor ellenőrizze csak :/
            if($_POST["dokirexcegid"]!=0){
                if($this->adminUtils->checkBejelentkezoCegForDokirexCegid($_POST["dokirexcegid"],$_POST["cegid"])!==false){
                    $updatedokirexjson = false;
                }else{
                    if(!$telephelyek = sql_fetch_array(sql_query("SELECT * FROM cegvars WHERE cegid=?",[$_POST["cegid"]]))){
                        $updatedokirexjson = true;
                    }else{
                        $updatedokirexjson = false;
                    }
                   
                }
            }else{
                $updatedokirexjson = false;
            }

            if(in_array($_POST["cegid"],[61,11])) $updatedokirexjson = false;

            $status = "";

            //kiegészítő vizsgálatok másolása
            $status .= $this->bookingService->replicateKiegeszitoVizsgalatok($fid);

            Utils::jsonOut(["status" => $status, "html" => $this->_showBookingEditor($fid, $_POST["p"]), "updatedokirexjson"=>$updatedokirexjson, "sync" => $fid]);
            die;
        }


        if (isset($_REQUEST['AFForm'])) {
            if (!$this->user->authenticated()) {
                $this->utils->jsonOut(["error" => "error"]);
            }

            $taj = $_REQUEST["AFForm"];
            $fid = $_REQUEST["fid"] ?? 0;
            $pid = $_REQUEST["pid"] ?? 0;

            $data = $this->bookingService->getPatientByTAJ($taj, $fid, $pid);

            $this->utils->jsonOut($data);
            die();
        }

        if(isset($_POST["setMunkakorText"])){
            $q=sql_fetch_array(sql_query("SELECT * FROM dokirex_munkakorok_new WHERE MunkakorID=?",array($_POST["setMunkakorText"])));
            die($q["Nev"]);
        }

        if (isset($_POST["setalkanswer"])) {
            $changeId = intval($_POST["setalkanswer"]);
            $changeRow = intval($_POST["row"]);
            $answer = $_POST["answer"];

            $reservationData = sql_query("select id, questions from foglalasok where id=?", [$changeId])->fetch(PDO::FETCH_ASSOC);
            $questions = $reservationData["questions"];

            if (empty($questions)) {
                foreach ($this->alkQuestions as $alkQuestion) {
                    $questions.= "{$alkQuestion} NEM\n";
                }
            }

            $text = "";
            $rows = explode("\n", $questions);
            foreach ($rows as $id => $row) {
                if ($id == $changeRow) {
                    if ($answer == "IGEN") {
                        $row = str_replace("IGEN", "NEM", $row);
                    } else {
                        $row = str_replace("NEM", "IGEN", $row);
                    }
                }
                $text .= "{$row}\n";
            }

            sql_query("update foglalasok set questions=? where id=?", [$text, $changeId]);

            $data = sql_query("select id, questions from foglalasok where id=?", [$changeId])->fetch(PDO::FETCH_ASSOC);
            echo $this->showQuestionButtons($data["id"], $data["questions"]);
            die;
        }

        if(isset($_POST["showalkalmassagiwin"])){
            $row = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?",[$_POST["showalkalmassagiwin"]]));
            die(json_encode(array("html"=>$this->_alkalmassagFolder_new($row),"error"=>"")));
        }

        if(isset($_POST["showquestionaries"])){

            $questionaryData = sql_query("SELECT kuv.*,fogl.* FROM foglalasok fogl 
                                          LEFT JOIN felhasznalok felh ON felh.taj=fogl.taj AND felh.cegid=fogl.cegid
                                          LEFT JOIN kerdoiv_ugyfel_valaszok kuv ON kuv.fid=felh.id
                                          WHERE fogl.id=? AND fogl.pass=? ",[$_POST["fid"],$_POST["pass"]])->fetchAll(PDO::FETCH_ASSOC);
    
            //$row = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?",[$_POST["showalkalmassagiwin"]]));
            die(json_encode(array("html"=>$this->questionaryWindow($questionaryData),"error"=>"")));
        }

        if(isset($_POST["saveAlkalmassagiBox"])){
            $error = "";
            if(isset($_POST["fid"])){

                if(!isset($_POST["alkalmassag"])) $_POST["alkalmassag"]="";
                if(!isset($_POST["alkalmassagido"])) $_POST["alkalmassagido"]="";
                if(!isset($_POST["alkalmassagikhet"])) $_POST["alkalmassagikhet"]="";
                if(!isset($_POST["alkalmassagkorl"])) $_POST["alkalmassagkorl"]="";
                if(!isset($_POST["vernyomas"])) $_POST["vernyomas"]="";
                if(!isset($_POST["orvosszoveg"])) $_POST["orvosszoveg"]="";
                if(!isset($_POST["alkalmassaguserid"])) $_POST["alkalmassaguserid"]="";

                sql_query("UPDATE foglalasok SET alkalmassag=?,alkalmassagikhet=?,alkalmassagkorl=?,
                                  vernyomas=?,orvosszoveg=?,alkalmassaguserid=?,alkalmassagido=?
                       WHERE id=?",
                       array($_POST["alkalmassag"],$_POST["alkalmassagikhet"],$_POST["alkalmassagkorl"],
                             $_POST["vernyomas"],$_POST["orvosszoveg"],$_POST["alkalmassaguserid"],
                             $_POST["alkalmassagido"],$_POST["fid"]));
            }else{
                $error="Ismeretlen foglalás azonosító.";
            }
            
            die(json_encode(array("error"=>$error)));
        }

        /*if(isset($_POST["showSzamlazasWin"])){
            echo "<pre>";
            print_r($_POST);
            echo "</pre>";
            die();
        }*/
    }

    private function companySelector($selectedCompanyId):string {
        $html = "<div style='' id='editcolumn'>";
        $html .= "<select class='bookingeditorcegselector2' onChange='setDefaultDokirexCegId($(this).val())' name='cegid' id='cegid' style='width:100%;'>";
        $html .= "<option value='0'>Nincs céghez kötve</option>";

        $cegFilter = "";
        if (!$this->user->allCegJog()) {
            $cegFilter = "and id in (" . $this->user->getCegList() . ")";
        }

        foreach (sql_query("select id, megnev from cegek where true {$cegFilter} order by megnev")->fetchAll(PDO::FETCH_ASSOC) as $company) {
            $html .= "<option value='{$company["id"]}'" . ($selectedCompanyId == $company["id"] ? " selected" : "") . ">{$company["megnev"]}</option>";
        }
        $html .= "</select>";
        $html .= "</div>";
        return $html;
    }

    private function telephelySelector($selectedCompanyId, $telephelyId):string {
        $telephelyek = sql_query("select * from cegvars where cegid=? and (placeids<>'' or selectable=0) order by sorrend, megnev", [$selectedCompanyId])->fetchAll(PDO::FETCH_ASSOC);
        if (empty($telephelyek)) {
            return "";
        }

        $html = "";
        $html .= "<select name='telephelyid' id='telephelyid' style='width:200px;' title='Telephely'>";
        $html .= "<option value='0'>Telephely?</option>";

        foreach ($telephelyek as $rowt) {
            if ($rowt["parentid"] == 0) {
                $disabled = "";
                if ($rowt["selectable"] == 0) {
                    $disabled = "disabled style='background:#888;color:#fff;'";
                }
                $html .= "<option {$disabled} value='{$rowt["id"]}'" . ($telephelyId == $rowt["id"] ? " selected" : "") . ">{$rowt["megnev"]}</option>";

                foreach ($telephelyek as $telephely) {
                    if ($telephely["parentid"] == $rowt["id"]) {
                        $html .= "<option value='{$telephely["id"]}'" . ($telephelyId == $telephely["id"] ? " selected" : "") . ">{$telephely["megnev"]}</option>";
                    }
                }
            }
        }

        $html .= "</select>";
        return $html;
    }

    private function doctorSelector($reservationData):string {
        $html = "";
        $nap = substr($reservationData["datum"], 0, 10);
        $ora = substr($reservationData["datum"], 11, 5);
        $wora = "AND TIME(b.tol)<=TIME('{$ora}') AND TIME(b.ig)>TIME('{$ora}')";

        $html .= "<input type='hidden' name='regiorvos' value='{$reservationData["orvosassigned"]}' />";
        $html .= "<select class='bookingeditorselector2' name='orvosassigned' style='width:calc(100% - 30px);'>";
        $html .= "<option value='0'>Nincs orvoshoz kötve</option>";
        $resh = sql_query("SELECT o.*, SUM((b.nap=WEEKDAY('{$nap}')+1 or b.beonap='{$nap}') {$wora} AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2) OR (WEEK('{$nap}',3)%2=1 AND b.hetek=1)) and b.aktiv=1) as beovan
                  FROM orvos_beosztas_new b 
                  LEFT JOIN orvosok o ON o.`id`=b.`orvosid` 
                  WHERE b.helyszinid=? and instr(tipusok, '|{$reservationData["szurestipusid"]}|')  
                  GROUP BY b.orvosid order by beovan desc, o.nev", [$_SESSION["helyszin"]]);
        while ($rowh = sql_fetch_array($resh)) {
            $s = "";
            if ($rowh["beovan"] == 0) {
                $s = " style='color:#aaa;'";
                $rowh["nev"] .= " / nincs beosztása erre az időpontra";
            }
            $html .= "<option value='{$rowh["id"]}'" . ($reservationData["orvosassigned"] == $rowh["id"] ? " selected" : "") . " {$s}>{$rowh["nev"]}</option>";
        }
        $html .= "</select>";

        return $html;
    }


    private function heading($row):string {
        $html = "";
        $html .= "<div style='padding:10px;background:#555;color:#fff;'>";
        $html .= "<span style='font-size:16px;font-weight:bold;' title='Foglalás ideje:{$row['regdatum']}'>" . $this->adminUtils->magyarDatum($row["datum"])."";
        $html .= " - {$row["rinterval"]} perc <a style='color:yellow;' onclick='startTimeEditor({$row["id"]},\"{$row["pass"]}\");return false;' href='#'><i title='időpont és időtartam átírása' class='fa-solid fa-pen-to-square'></i></a>";
        $html .= " - {$row["sztipus"]}</span>";

        $html .= "<div>";
        if ($row["foglalta"] != "") {
            $html .= "Foglalta: {$row["foglalta"]}&nbsp;&nbsp;";
        }
        if ($row["modifiedby"] != "") {
            $html .= "Módosította: <span title='{$row["modifiedtime"]}'>{$row["modifiedby"]}</span>&nbsp;&nbsp;";
        }
        $html .= "</div>";

        $html .= "<div style='margin-top:4px;'>";
        $html .= "<a class='middlebutton' href='#' onclick='startFoglalasMove({$row["id"]},\"{$row["pass"]}\");return false;'>áthelyezés</a> ";
        $html .= "<a class='middlebutton' href='#' onclick='startFoglalasCopy({$row["id"]},\"{$row["pass"]}\");return false;'>másolás</a> ";
        $html .= "<a class='middlebutton' href='#' onClick='showQuestionaries({$row["id"]},\"{$row["pass"]}\");return false;'>kérdések/válaszok</a> ";
        if ($this->user->user["username"] == "jns") {
            $html .= "<a class='middlebutton' href='#' onClick='duplicateReservation({$row["id"]},\"{$row["pass"]}\");return false;'>foglalás ismétlése</a> ";
        }
        $html .= "</div>";

        $html .= "</div>";

        $html .= "<div id='moveinfo' style='display:none;background:#ff8;color:#555;padding:10px;'>Kattints arra az időpont melletti \"+\" gombra, ahova át akarod helyezni a foglalást.<div style='margin:3px 0px;'><a class='middlebutton' href='#' onclick='cancelFoglalasMove();return false;'>mégse</a></div></div>";
        $html .= "<div id='copyinfo' style='display:none;background:#ff8;color:#555;padding:10px;'>Kattints arra az időpont melletti \"+\" gombra, ahova át akarod <b>másolni</b> a foglalást.<br/>Több időponthoz is másolhatsz, ha befejezted kattints a mégse gombra.<div style='margin:3px 0px;'><a class='middlebutton' href='#' onclick='cancelFoglalasMove();return false;'>mégse</a></div></div>";
        $html .= "<div id='timeedit' style='display:none;background:#ff8;color:#555;padding:10px;'>";
        $html .= "Ez a rész az időpont egy napon belüli kisebb áthelyezésére és az időtartam átírására szolgál.<br/>";
        $html .= "Időpont: <input class='inputbox' style='width:40px;' type='text' name='modtime' id='modtime' value='".date("H:i", strtotime($row["datum"]))."'> ";
        $html .= "Időtartam: <input class='inputbox' style='width:20px;' type='text' name='modinterval' id='modinterval' value='{$row["rinterval"]}'> perc";
        $html .= "<div style='margin:3px 0px;'><a class='middlebutton' href='#' onclick='saveTimeEdit();return false;'>mentés</a> <a class='middlebutton' href='#' onclick='cancelFoglalasMove();return false;'>mégse</a></div>";
        $html .= "</div>";
        return $html;
    }

    private function printButtons($row):string {
        $html = "";
        if ($row["nev"] != "" && $row["nev"] != "nincs név") {
            $html .= "<div style='padding:10px;background:#d0d0d0;'>";
            //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=menedzserkerdoiv&fid={$row["id"]}&p={$row["pass"]}'>menedzser kérdőív</a>&nbsp;&nbsp;";
            $html .= "<a title='Alkalmassági' class='printbutton' target='_blank' onclick='showAlkalmassagiWin({$row["id"]});return false;' href='#'>&nbsp;<i class='fa-solid fa-award'></i>&nbsp;</a>&nbsp;&nbsp;";

            if ($this->user->szamlakeszitesAccess()) {
                $html .= "<a title='Számlázás' class='printbutton' target='_blank' onclick='showSzamlazasWin({$row["id"]});return false;' href='#'>&nbsp;<i class='fa-solid fa-file-invoice-dollar'></i>&nbsp;</a>&nbsp;&nbsp;";
                //$html .= "<div style=\"position: absolute;left: -38px;top: 138px;background-color: #e0e0e0;width:38px;height:38px\"><i style=\"font-size:30px;position:absolute;left:8px;cursor:pointer\" title=\"Számlázás\" onClick='showSzamlazasWin({$row["id"]})' class=\"fa-solid fa-file-invoice-dollar\"></i></div>";
            }


            if (Booking_Constants::SQL_DB == "hungariamed" && $row["cegid"] == CompanyService::BP_ID && $this->user->psyhosockerdoivAccess()) {
                $html .= "<a class='printbutton' target='_blank' href='../index.php?page=psychosocialform&fid={$row["id"]}&pass={$row["pass"]}&status=modify'><i class='fa-solid fa-print'></i> Pszihoszociális kérdőív</a>&nbsp;&nbsp;";
            }

            //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=vizsgalatilap&tipus=idoszakos&fid={$row["id"]}&p={$row["pass"]}'>vizsgálati lap (I)</a>&nbsp;&nbsp;";
            //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=vizsgalatilap&tipus=soronkivuli&fid={$row["id"]}&p={$row["pass"]}'>vizsgálati lap (S)</a>&nbsp;&nbsp;";
            //$html.= "<a class='printbutton' target='_blank' href='index.php?print&template=karton&fid={$row["id"]}&p={$row["pass"]}'>karton</a>&nbsp;&nbsp;";
            //$html .= "<a class='printbutton' target='_blank' href='index.php?print&template=covidkerdoiv&fid={$row["id"]}&p={$row["pass"]}'>COVID kérdőív</a>&nbsp;&nbsp;";
            //$html .= "<a class='printbutton' target='_blank' href='index.php?print&template=menedzsersetalolap&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> Menedzser sétálólap</a>&nbsp;&nbsp;";

            /*if ($row["cegid"] == CompanyService::SUZUKI_GHC_ID && Booking_Constants::SQL_DB == "hungariamed") {
                $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=ghcsenior&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> GHC SEN</a>&nbsp;&nbsp;";
                $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=ghcstandard&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> GHC STA</a>&nbsp;&nbsp;";
            }*/

            $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=nkfihsetalolap&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> NKFIH sétálólap</a>&nbsp;&nbsp;";

            $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=mende_adatkezeles&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> Mende adatkezelési</a>&nbsp;&nbsp;";

            //$html .= "<a class='printbutton' target='_blank' href='index.php?print&template=nkfihsetalolap&fid={$row["id"]}&p={$row["pass"]}'>NKFIH sétálólap</a>&nbsp;&nbsp;";
            $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=matricamegj&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> Megjegyzés</a>&nbsp;&nbsp;";
            $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=matrica&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> Matrica</a>&nbsp;&nbsp;";
            $html .= "<a class='printbutton' target='_blank' onclick='showLaborKeroWin({$row["id"]});return false;' href='#' style='background: green;'><i class='fa-solid fa-flask'></i> Laborkérő" . (sql_query("select id from labrequests where foglalasid=? and status='done' limit 1", [$row["id"]])->fetch(PDO::FETCH_ASSOC) ? " <i class='fa-solid fa-circle-check'></i>" : "") . "</a>&nbsp;&nbsp;";
            $html .= "<a class='printbutton' target='_blank' onclick='printSpektrumlabMatrica(\"{$row["id"]}\", \"{$row["pass"]}\");return false;' href='#' style='background: green;' title='Spektrumlab matrica'><i class='fa-solid fa-print'></i> SM</a>&nbsp;&nbsp;";

            if (CompanyService::isSuzukiGHC($row["cegid"])) {
                if ($row["neme"] == 1) {
                    $html .= "<div style='padding-top:10px;'>";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=1&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> senior 1</a>&nbsp;&nbsp;";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=2&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> senior 2</a>&nbsp;&nbsp;";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=3&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> senior 3</a>&nbsp;&nbsp;";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=4&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> standard 4</a>&nbsp;&nbsp;";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=5&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> standard 5</a>&nbsp;&nbsp;";
                    $html .= "</div>";
                }
                if ($row["neme"] == 2) {
                    $html .= "<div style='padding-top:10px;'>";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=6&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> senior 1</a>&nbsp;&nbsp;";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=7&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> senior 2</a>&nbsp;&nbsp;";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=8&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> senior 3</a>&nbsp;&nbsp;";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=9&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> standard 4</a>&nbsp;&nbsp;";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=10&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> standard 5</a>&nbsp;&nbsp;";
                    $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=suzukisetalolap&version=11&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> standard 5</a>&nbsp;&nbsp;";
                    $html .= "</div>";
                }

            }

            $html .= "</div>";


        }
        return $html;
    }

    private string $textCellWidth = "80px";

    private function companyBlock($row):string {
        $html = "";

        $html.= "<div class='pdatarow' style='display:table;width:100%;'>";
        $html.= "<div class='becell'>";

        $html.= "<div style='display:table;width:100%;'>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm' style='width:{$this->textCellWidth};white-space: nowrap;'><img height='13px' src='https://dokirex.hu/favicon.ico' title='Dokirex cég' />&nbsp;Cég:</div><div class='tdm' style='padding:1px 0;'>" . ($this->user->allCegJog() ? $this->adminUtils->ceglista($row["dokirexcegid"], $row["cegid"]) : "") . "</div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Cég:</div><div class='tdm' style='padding:1px 0;'>" . $this->companySelector($row["cegid"]) . "</div>";
        $html.= "</div>";
        $html.= "</div>";

        $html.= "</div>";

        $html.= "<div class='becell'>";

        $html.= "<div style='display:table;width:100%;'>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm' style='width:{$this->textCellWidth};'><img height='13px' src='https://dokirex.hu/favicon.ico' title='Dokirex munkakör' />&nbsp;Munkakör:</div><div class='tdm' style='padding:1px 0;'>{$this->adminUtils->munkakorlista($row["dokirexmunkakorid"],"onChange='setMunkakorText($(this).val())'")}</div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Orvos:</div><div class='tdm' style='padding:1px 0;'>" . $this->doctorSelector($row) . "&nbsp;&nbsp;<a href='#' onclick='foglalasOrvosErtesites();return false;' title='Orvos értesítése' style='font-size: 16px;'><i class='fas fa-envelope fa-lg'></i></a></div>";
        $html.= "</div>";
        $html.= "</div>";

        $html.= "</div>";

        $html .= "</div>";

        return $html;
    }

    private function patientNotFoundBlock($row):string {
        $html = "";

        if ($row["paciensid"] == 0) {
            $html .= "<div style='border-top:1px solid #888;padding:5px 0px;margin:5px 0px 2px 0px;'>";
            $html .= "Ehhez a foglaláshoz nem tartozik paciens adatlap! Válassz a meglévő adatlapok közül, vagy hozz létre újat.";
            $html .= "<div style='margin-top: 3px;'>";
            $html .= "<a class='middlebutton' style='background:#f00;' href='#' onClick='prepareUserDataSearch();return false;'>paciens adatlap keresése</a>&nbsp;&nbsp;";
            $html .= "<a class='middlebutton' style='' href='#' onClick='newUserDataFromReservation();return false;'>új paciens adatlap létrehozása</a> ";
            $html .= "</div>";
            $html .= "</div>";

            $html .= "<div id='pdatasearchrow' style='display:none;'>";
            $html .= "<div style='background:#eee;padding:10px 10px 10px 10px;margin:0px;width:100%;height:267px;overflow: hidden;'>";
            $html .= "<div><input id='pdatasearchinput' type='text' value='' placeholder='kereshetsz névre, taj számra, telefonszámra, email címre...' style='width:100%;box-sizing: border-box;'/></div>";
            $html .= "<div id='searchpaciensresult'></div>";
            $html .= "</div>";
            $html .= "</div>";
        }

        return $html;
    }

    private function patientDataBlock($row):string {
        $tajButton = "<a onClick='autoFill(false);return false;' href='#'><i class='fas fa-search fa-lg'></i></a>";
        $userNotificationMark = sql_query("select id from notifications where tipus='usernotification' and objectid=? and destination=?", [$row["id"], $row["email"]])->fetch(PDO::FETCH_ASSOC) ? " <i style='color:#08a;' title='Visszaigazoló email kiment erre a címre' class='fa-solid fa-circle-check'></i>" : "";

        $tajCheck = "";
        if (!empty($row["taj"])) {
            if (Utils::tajCheck($row["taj"])) {
                $tajCheck = " <i style='color:#08a;' title='TAJ szám helyes' class='fa-solid fa-circle-check'></i>";
            } else {
                $tajCheck = " <i style='color:#f00;' title='Helytelen TAJ szám' class='fa-solid fa-circle-xmark'></i>";
            }
        }

        $html = "";

        $html.= "<div style='border-top:1px solid #888;padding-top:5px;margin-top:5px;'>";
        $html.= "<div class='pdatarow' style='display:table;width:100%;'>";
        $html.= "<div class='becell'>";

        $html.= "<div style='display:table;width:100%;'>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm' style='width:{$this->textCellWidth};'><span>Taj szám:{$tajCheck}</span></div><div class='tdm'><input data-taborder='1' class='inputbox ui-taborder editortaj2 fipad' style='width:calc(100% - 30px);' type='text' id='editortaj' name='taj' value='{$row["taj"]}'> {$tajButton}</div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Név:</div><div class='tdm'><input data-taborder='2' onclick='return false;' class='inputbox ui-taborder fipad' placeholder='Ide csak nevet írj' style='width:100%;' type='text' name='nev' value='{$row["nev"]}'></div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Munkakör:</div><div class='tdm'>" . $this->munkakorInput($row) . "</div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Szül. dátum:</div><div class='tdm'><input data-taborder='4'  class='inputbox ui-taborder fipad' style='width:100%;' type='text' name='szuldatum' id='editorszuldatum' value='{$row["szuldatum"]}' placeholder='éééé-hh-nn'/></div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Szül. hely:</div><div class='tdm'><input data-taborder='5'  class='inputbox ui-taborder fipad' style='width:100%;' type='text' name='szulhely' value='{$row["szulhely"]}'></div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Anyja neve:</div><div class='tdm'><input data-taborder='6'  class='inputbox ui-taborder fipad' style='width:100%;' type='text' name='anyjaneve' value='{$row["anyjaneve"]}'></div>";
        $html.= "</div>";
        $html.= "</div>";

        $html.= "</div>";


        $html.= "<div class='becell'>";

        $html.= "<div style='display:table;width:100%;'>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm' style='width:{$this->textCellWidth};'>E-mail:{$userNotificationMark}</div><div class='tdm'><input data-taborder='7' class='inputbox ui-taborder fipad' style='width:calc(100% - 30px);' type='text' name='email' value='{$row["email"]}'>&nbsp;&nbsp;<a href='#' onclick='manualNotificationSend({$row["id"]},\"{$row["pass"]}\");return false;' title='Paciens értesítése' style='font-size: 16px;'><i class='fas fa-envelope fa-lg'></i></a></div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Telefon:</div><div class='tdm'><input data-taborder='8' class='inputbox ui-taborder fipad' style='width:100%;' type='text' name='telefon' value='{$row["telefon"]}'></div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Irsz:</div><div class='tdm'><input data-taborder='9' placeholder='Irsz' class='inputbox ui-taborder fipad' style='width:25%;' type='text' name='irsz' id='irsz' value='{$row["irsz"]}'> <input data-taborder='10' placeholder='Város' class='inputbox ui-taborder' style='width:70%;' type='text' name='varos' id='varos' value='{$row["varos"]}'></div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Utca:</div><div class='tdm'><input data-taborder='11' class='inputbox ui-taborder fipad' style='width:100%' type='text' name='utca' value='{$row["utca"]}'/></div>";
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        if (!empty($row["adoszam"])) {
            $html.= "<div class='tdm'>Adószám:</div><div class='tdm'><input data-taborder='13' class='inputbox ui-taborder fipad' style='width:100%;' type='text' name='adoszam' value='{$row["adoszam"]}'></div>";
        } else {
            $html.= "<div class='tdm'>Törzsszám:&nbsp;</div><div class='tdm'><input data-taborder='13' class='inputbox ui-taborder fipad' style='width:100%;' type='text' name='torzsszam' value='{$row["torzsszam"]}'></div>";
        }
        $html.= "</div>";
        $html.= "<div style='display:table-row'>";
        $html.= "<div class='tdm'>Neme:&nbsp;</div>";
        $html.= "<div class='tdm' style='padding:3px 0px;'>";
        $html.= "<input type='radio' name='neme' " . ($row["neme"] == 1 ? "checked" : "") . " value='1' class='fipad'/>&nbsp;Férfi&nbsp;<input type='radio' name='neme' " . ($row["neme"] == 2 ? "checked" : "") . " value='2' class='fipad'>&nbsp;Nő";

        if (CompanyService::isSuzukiGHC($row["cegid"])) {
            $html.= " <span style='color:#fff;background:#f00;border-radius:5px;padding:2px 3px;'><i class='fa-solid fa-arrow-left'></i> válasszd ki a nemét</span>";
        }

        $html.= "</div>";
        $html.= "</div>";
        $html.= "</div>";

        $html.= "</div>";

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }


    private function eljottBlock($row):string {
        $html = "";

        $html .= "<div class='pdatarow' style='padding-top:5px;margin-top: 5px;border-top:1px solid #888;'>";
        $html .= "<a onclick='showEljottLog({$row["id"]});return false;' href=''>log</a>&nbsp;&nbsp;<span id='eljottchk'>" . $this->eljottCheckbox($row) . "</span>&nbsp;&nbsp;";

        if (Booking_Constants::SQL_DB == "hungariamed") {
            /*
            $html .= "<input type='radio' name='testalkat' " . ($row["testalkat"] == 1 ? "checked='true'" : "") . " value='1' />&nbsp;Vékony&nbsp;";
            $html .= "<input type='radio' name='testalkat' " . ($row["testalkat"] == 2 ? "checked='true'" : "") . " value='2' />&nbsp;Normál&nbsp;";
            $html .= "<input type='radio' name='testalkat' " . ($row["testalkat"] == 3 ? "checked='true'" : "") . " value='3' />&nbsp;Túlsúlyos&nbsp;";
            $html .= "<input type='radio' name='testalkat' " . ($row["testalkat"] == 4 ? "checked='true'" : "") . " value='4' />&nbsp;Extrém&nbsp;";
            */
        }

        $html .= "</div>";

        return $html;
    }

    private function commentBlock($row):string {
        $html = "";

        $html.= "<div class='pdatarow' style='padding-top:5px;margin-top: 5px;border-top:1px solid #888;'>";
        $html.= "<div style='display:table;width:100%;'>";

        $html.= "<div class='becell'>";
        $html.= "<textarea data-taborder='14' class='ui-taborder' placeholder='Megjegyzés...' style='width:100%;height:60px;' id='reservationinfo' name='megj'>{$row["megj"]}</textarea>";

        if (Booking_Constants::SQL_DB == "keltexmed") {
            $html.= "<div>";
            $html.= "<a href='#' onclick=\"$('#reservationinfo').val('(előzetes) '+$('#reservationinfo').val());return false;\">előzetes</a> | ";
            $html.= "<a href='#' onclick=\"$('#reservationinfo').val('(időszakos) '+$('#reservationinfo').val());return false;\">időszakos</a> | ";
            $html.= "<a href='#' onclick=\"$('#reservationinfo').val('(soron kívüli) '+$('#reservationinfo').val());return false;\">soron kívüli</a> | ";
            $html.= "<a href='#' onclick=\"$('#reservationinfo').val('(záró) '+$('#reservationinfo').val());return false;\">záró</a>";
            $html.= "</div>";
        }

        $html.= "</div>";

        $html.= "<div class='becell'>";
        $html .= $this->_filesFolderNew($row);
        $html.= "</div>";

        $html.= "</div>";
        $html.= "</div>";

        return $html;
    }

    private function extraServiceBlock($row):string {
        $html = "";

        $html .= "<div style='padding-top:5px;border-top:1px solid #888;'>";
        $html .= "Tüdőszűrő dátuma: <input type='text' style='width:80px;' name='tudoszuroervenyesseg' value='{$row["tudoszuroervenyesseg"]}' />&nbsp;&nbsp;";
        $html .= "<div style='display:inline-block;" . ($row["tudoszuro"] == 1 ? "background:#f00;color:#fff;" : "") . "'><input type='checkbox' name='tudoszuro' value='1' " . ($row["tudoszuro"] == 1 ? "checked" : "") . " /> tüdőszűrés kell&nbsp;&nbsp;</div>";
        $html .= "<div style='display:inline-block;" . ($row["kieg_labor"] == 1 ? "background:#f00;color:#fff;" : "") . "'><input type='checkbox' name='kieg_labor' value='1' " . ($row["kieg_labor"] == 1 ? "checked" : "") . " /> labor&nbsp;&nbsp;</div>";
        $html .= "<div style='display:inline-block;" . ($row["kieg_hallas"] == 1 ? "background:#f00;color:#fff;" : "") . "'><input type='checkbox' name='kieg_hallas' value='1' " . ($row["kieg_hallas"] == 1 ? "checked" : "") . " /> hallás vizsgálat</div>";
        $html .= "</div>";

        /*
        $html.= "<td valign='top' style=''>";
        $html.= "<div style='width:200px;overflow:hidden;'><div style='width:1000px;'>".($row['noreservation']!=1?$this->adminUtils->showPaciensFiles($row["id"]):"")."</div></div>";

        if ($rowa = sql_fetch_array(sql_query("select * from arak WHERE INSTR(cegid,?) AND tipusid=? and csomag=0",array("|{$row["cegid"]}|",$row["szurestipusid"])))) {
            $html.= "<div><a href='#' onclick='showFizSzolg({$row["id"]});return false;'>+ szolgáltatás hozzáadása</a><div>";
        }
        $html.= "<div id='fizszolglist{$row["id"]}'>".$this->adminUtils->showFizSzolg($row["id"])."</div>";

        $html.= "</td>";
        */


        return $html;
    }

    private function footerButtons($row):string {
        $allowNewCompany = $this->user->allCegJog() ? 1 : 0;

        $html = "";

        if (isset($_POST["page"])) {
            $_GET["page"] = $_POST["page"];
        }

        $html .= "<div style='padding:10px;background:#d0d0d0;'>";
        $html .= "<a href='#' class='newsubmit ui-taborderon' onclick='foglalasMentes(\"{$_GET["page"]}\", {$allowNewCompany});return false;'>Mentés</a>&nbsp;&nbsp;";
        $html .= "<a href='#' class='newsubmit' onclick='$(\"#idoponteditor\").slideUp();cancelFoglalasMove();return false;'>Bezár</a>&nbsp;&nbsp;";
        $html .= "<a href='#' class='newsubmit' onclick='removeIdopont({$row["id"]},\"{$row["pass"]}\",\"{$_GET["page"]}\", 0);return false;' style='background:red;'><i title='foglalás törlése' class='fa-solid fa-trash'></i></a>&nbsp;&nbsp;";

        $dokirexSign = empty($row["dokirex_userid"]) ? "":" <i class='fa-solid fa-check'></i>";

        if (Booking_Constants::COMPANY_NAME_SHORT == "Keltexmed") {
            $html .= "<a href='#' class='newsubmit' onclick='insertPaciensIntoDokirex({$row["id"]});return false;' style='background:#5ed5e3'>Dokirex Keltexmed</a>&nbsp;&nbsp;";
            $html .= "<a href='#' class='newsubmit' onclick='insertPaciensIntoDokirexHMM({$row["id"]});return false;' style='background:#9d0202'>Dokirex HMM</a>&nbsp;&nbsp;";
        } else {
            $html .= "<a href='#' class='newsubmit' onclick='insertPaciensIntoDokirex({$row["id"]});return false;' style='background:#008080' title='uid: {$row["dokirex_userid"]}'>Dokirex{$dokirexSign}</a>&nbsp;&nbsp;";
        }

        if ($this->user->varoteremuiAccess()) {
            $html .= $this->varoteremService->doc_choose_button($row);
        }

        $html .= "</div>";

        return $html;
    }




    private function getReservation($id, $p) {
        if ($row = sql_fetch_array(sql_query("select f.*,t.megnev as sztipus,c.megnev as cegnev,o.nev as orvosnev from foglalasok f
                left join szurestipusok t on t.id=f.szurestipusid
                left join orvosok o on o.id=f.orvosassigned
                left join cegek c on c.id=f.cegid
                where f.id=? and f.pass=?",array($id, $p)))) {

            if ($row["nev"] == "nincs név") {
                $row["nev"] = "";
            }

            return $row;
        }
        return false;
    }

    private function _showBookingEditor($id, $p):string {
        if (!$this->user->authenticated()) {
            return "Error 500 - Not authenticated!";
        }

        $html = "";
        $id = intval($id);
        if(!isset($_GET["page"])) $_GET["page"] = "booking";

        if ($row = $this->getReservation($id, $p)) {
            $html .= "<form id='iform' name='iform' method='post' enctype='multipart/form-data'>";
            $allowNewCompany = $this->user->allCegJog() ? 1 : 0;
            $mustChooseCompany = $this->user->allCegJog() ? 0 : 1;

            $html .= "<input type='hidden' name='currentPage' id='currentPage' value='{$_GET["page"]}'/>";
            $html .= "<input type='hidden' name='fid' id='reservationId' value='{$row["id"]}'/>";
            $html .= "<input type='hidden' name='paciensid' id='paciensId' value='{$row["paciensid"]}'/>";
            $html .= "<input type='hidden' name='p' id='reservationToken' value='{$row["pass"]}'/>";
            $html .= "<input type='hidden' name='mustChooseCompany' id='mustChooseCompany' value='{$mustChooseCompany}'/>";
            $html .= "<input type='hidden' name='allowNewCompany' id='allowNewCompany' value='{$allowNewCompany}'/>";

            $html .= "<div style='background:#e0e0e0;'>";

            $html .= $this->heading($row);

            $html .= $this->printButtons($row);
            $html .= "<div style='padding:5px 10px 10px 10px;'>";
            $html .= $this->companyBlock($row);
            $html .= $this->patientNotFoundBlock($row);
            $html .= $this->patientDataBlock($row);
            $html .= $this->eljottBlock($row);
            $html .= $this->commentBlock($row);
            $html .= $this->extraServiceBlock($row);
            $html .= "</div>";
            $html .= $this->footerButtons($row);

            $html .= "</div>";
            $html.= "</form>";
        } else {
            $html.= "Az időpont adatok lekérdezése közben hiba történt! {$_GET["p"]}";
        }

        return $html;
    }

    private function munkakorInput($row):string {
        $html = "<input data-taborder='3'  class='inputbox ui-taborder fipad' style='width:100%;' type='text' name='munkakor' id='bookingeditormunkakor' value='{$row["munkakor"]}'>";

        $items = [];
        foreach (sql_query("SELECT TRIM(munkakor) as munkakor, COUNT(*) AS hany FROM foglalasok WHERE datum>DATE_SUB(NOW(), INTERVAL 1 WEEK) and munkakor IS NOT NULL AND munkakor<>'' AND CHAR_LENGTH(munkakor)<40 GROUP BY TRIM(munkakor) HAVING hany>1 ORDER BY TRIM(munkakor)")->fetchAll(PDO::FETCH_ASSOC) as $munkakor) {
            $items[] = "'".trim(str_replace("'", "", $munkakor["munkakor"]))."'";
        }


        if (Booking_Constants::SQL_DB == "keltexmed") {
            foreach ($this->keltexMunkakorok as $munkakor) {
                if (!in_array($munkakor, $items)) {
                    $items[] = "'".$munkakor."'";
                }
            }
        }

        $html.= "<script>$(function() { var munkakorok = [".implode(",", $items)."];$('#bookingeditormunkakor').autocomplete({source: function(request, response) { var results = $.ui.autocomplete.filter(munkakorok, request.term);response(results.slice(0, 14)); }}); });</script>";
        return $html;
    }


    public static function eljottCheckbox($reservationData):string {
        $icon = "<i class='far fa-square'></i>";
        $behivvaIcon = "<i class='far fa-square'></i>";
        if ($reservationData["eljott"] == 1) {
            $icon = "<i class='fas fa-check-square'></i>";
        }
        if ($reservationData["behivva"] == 1) {
            $behivvaIcon = "<i class='fas fa-check-square'></i>";
        }

        $html = "<a data-id='{$reservationData["id"]}' href='#' onclick='eljottButtonProtocol(this, 0);return false;' style='font-size: 16px;'>{$icon}</a> eljött";
        if ($reservationData["eljott"] == 1) {
            $html.= " <input style='width:45px;' name='eljottidopont' type='text' title='eljött időpont' value='".date("H:i", strtotime($reservationData["eljottidopont"]))."' />";

            $html.= "&nbsp;&nbsp;<a data-id='{$reservationData["id"]}' href='#' onclick='behivvaButtonProtocol(this);return false;' style='font-size: 16px;'>{$behivvaIcon}</a> behívva";
            if ($reservationData["behivva"] == 1) {
                $html.= " <input style='width:45px;' name='behivvaidopont' type='text' title='behívás időpontja' value='" . date("H:i", strtotime($reservationData["behivvaidopont"])) . "' />";
            }
        }

        //Lighttech kilépett státusz állítása
        if(isset($reservationData["paciensid"]) && $reservationData["cegid"]==CompanyService::LIGHTTECH_ID){
            $r=sql_query("SELECT * FROM felhasznalok WHERE id=?",[$reservationData["paciensid"]])->fetch(PDO::FETCH_ASSOC);

            $html.= "&nbsp;&nbsp;|&nbsp;&nbsp;<input type='checkbox' title='Kilépés státusz megadása' ".($r["kilepett"]==1?"checked='true'":"")." onChange='setQuitter({$r["id"]})' id='kilepett' value='1'  />&nbsp;<label for='kilepett'>Kilépett</label>";
        }

        return $html;
    }

    private function _filesFolderNew($row):string {
        $html = "";
        $files = $this->adminUtils->showPaciensFiles($row["id"]);
        $html .= "<div style='padding:0px;width:275px;height:70px;overflow-y:auto;overflow-x: hidden;'><div style='width:1000px;'>{$files}</div></div>";
        return $html;
    }

    private function _alkalmassagFolder_new($row):string {
        $html = "";

        $html .= "<div style='width:100%;max-width:500px;background:#eee;'>";
        $html .= "<form id='alkalmassgibox'>";
        $html .= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class=\"fa-solid fa-award\"></i>&nbsp;&nbsp;{$row["nev"]} - {$row["szuldatum"]} - {$row["taj"]}</div>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
        $html .= "</div>";

        $html .= "<div style='padding:10px;'>";

        if (CompanyService::isFesztivalCompany($row["cegid"]) || ($row["helyszinid"] == 536 && Booking_Constants::SQL_DB == "hungariamed")) {
            $html.= "<div id='alkquestions'>";
            $html.= $this->showQuestionButtons($row["id"], $row["questions"]);
            $html.= "</div>";

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
            if ($key == "I") {
                $html.= "<div style='padding:0px 0px 0px 25px;'>";
                $html.= "<input " . ($row["alkalmassagido"] == 3 ? "checked" : "") . " type='radio' name='alkalmassagido' value='3' />3 hó ";
                $html.= "<input " . ($row["alkalmassagido"] == 6 ? "checked" : "") . " type='radio' name='alkalmassagido' value='6' />6 hó ";
                $html.= "<input " . ($row["alkalmassagido"] == 12 ? "checked" : "") . " type='radio' name='alkalmassagido' value='12' />1 év ";
                $html.= "<input " . ($row["alkalmassagido"] == 24 ? "checked" : "") . " type='radio' name='alkalmassagido' value='24' />2 év ";
                $html.= "<input " . ($row["alkalmassagido"] == 36 ? "checked" : "") . " type='radio' name='alkalmassagido' value='36' />3 év ";
                $html.= "</div>";
            }
            if ($key == "IN") {
                $html .= "<div style='padding:0px 0px 0px 25px;'>köv. vizsgálat: <input type='text' style='width:40px;' name='alkalmassagikhet' value='{$row["alkalmassagikhet"]}' /> hét</div>";
            }
            if ($key == "K") {
                $html .= "<div style='padding:3px 0px 0px 25px;'><textarea placeholder='korlátozás szövege' style='width:100%;height:40px;' name='alkalmassagkorl'>{$row["alkalmassagkorl"]}</textarea></div>";
            }
            $html .= "</div>";
        }

        $html .= "<div style='border-top:1px solid #999;margin-top:5px;padding-top:5px;'>";
        $html .= "<div style='padding:0px 0px 0px 0px;'>vérnyomás: <input type='text' style='width:70px;' name='vernyomas' value='{$row["vernyomas"]}' /></div>";
        $html .= "</div>";

        $html .= "</div>";

        $html .= "<div style='border-top:1px solid #999;margin-top:5px;padding-top:5px;'>";

        $html .= "<div style=''><textarea onclick='orvosVelemenyEnter();' placeholder='orvos vélemény...' style='width:100%;height:40px;' name='orvosszoveg' id='orvosszoveg'>{$row["orvosszoveg"]}</textarea></div>";
        $html .= "<div class='ovsubmit' style='padding:5px 0px 5px 0px;text-align:center;display:none;'><input type='button' style='padding-left:20px;padding-right:20px;' onclick='orvosVelemenyExit();' value='OK'/></div>";
        $html .= "</div>";
        $html .= "<div style='border-top:1px solid #999;margin-top:5px;padding-top:5px;'>Kitöltötte: ";
        $html .= "<select name='alkalmassaguserid' style='width:170px;".($this->user->jogosultsagAccess()?"":"pointer-events: none;touch-action: none;")."'>";
        $html .= "<option value='0'>Válasszon!</option>";
        foreach (sql_query("select id, nev from users where pecsetszam<>'' order by nev")->fetchAll(PDO::FETCH_ASSOC) as $orvos) {
            $html .= "<option value='{$orvos["id"]}' ".($row["alkalmassaguserid"] == $orvos["id"] || ($row["alkalmassaguserid"] == 0 && $this->user->user["id"] == $orvos["id"]) ? " selected":"").">{$orvos["nev"]}</option>";
        }
        $html .= "</select>";
        $html .= "</div>";
        $html .= "<div style='border-top:1px solid #999;margin-top:5px;padding-top:10px;'>";
        $html .= "<a class='printbutton' onclick='saveAlkalmassagiWin({$row["id"]});return false;' href='#' style='background: #00aa00'>Mentés</a>&nbsp;";
        $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=alkalmassagipdf&fid={$row["id"]}&p={$row["pass"]}'><i class='fa-solid fa-print'></i> Nyomtatás</a>&nbsp;&nbsp;";

        $html .= "</div>";
        $html .= "</form>";
        $html .= "</div>";
        return $html;
    }

    private function questionaryWindow($row){
        $html = "";
        $counter = 0;
        $html .= "<div style='width:500px;background:white;'>";
        $html .= "  <form id=\"alkalmassgibox\">";
        $html .= "  <div style='display:table;width:100%;background:#8792ae;color:white;'>";
        $html .= "      <div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class=\"fa-solid fa-award\"></i>&nbsp;&nbsp;{$row[0]["nev"]} - {$row[0]["szuldatum"]} - {$row[0]["taj"]}</div>";
        $html .= "      <div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
        $html .= "  </div>";

        $html .= "  <div style='padding:10px;max-height:500px;overflow-y:scroll' id=answers-{$row[0]["id"]} contentEditable='true'>";
        foreach($row as $each){
            $counter++;
            $html .= "      <p style='font-weight:bold'>{$counter}. {$each["kerdes_szoveg"]}</p>";
            $html .= "      <p style='margin-left:20px'>{$each["valasz_szoveg"]}</p>";
        }
        
        $html .= "  </div>";
        $html .= "  <div style='padding:0px 0px 0px 0px;border-top:1px solid #999;margin-top:5px;padding-top:5px;'>";
        //$html .= "      <a class='printbutton' style='padding: 7px 5px 7px 5px !important' onclick='copyToClipboard(\"answers-{$row[0]["id"]}\")';return false;' href='#' style='background: #00aa00'>Másolás vágólapra</a>";
        $html .= "  </div>";
        $html .= "  </form>";
        $html .= "</div>";
        return $html;
    }


    public function showQuestionButtons($fid, $questions):string {
        $text = "";

        if (empty($questions)) {
            foreach ($this->alkQuestions as $alkQuestion) {
                $questions.= "{$alkQuestion} NEM\n";
            }
            sql_query("update foglalasok set questions=? where id=?", [$questions, $fid]);
        }

        $rows = explode("\n", $questions);
        foreach ($rows as $id => $row) {
            if (substr_count($row, "IGEN")) {
                $answer = "IGEN";
            } else {
                $answer = "NEM";
            }

            $row = str_replace($answer, "<a data-id='{$fid}' data-row='{$id}' data-answer='{$answer}' onclick='toggleAlkAnswer(this);return false;' href='#'>{$answer}</a>", $row);
            $text.= "<div>{$row}</div>";
        }

        return "<div style='margin:3px 5px;font-weight: bold;'>{$text}</div>";
    }


    private function setAuchanDataToAll($data, $fid) {
        if (Booking_Constants::SQL_DB == "keltexmed") {
            $auchanCondition = "and helyszinid IN (293, 294, 295, 300, 301, 302, 303, 304, 305, 306, 307, 308, 309, 310, 311, 312, 313, 314, 315, 316, 322, 319, 320, 321) and datum>'2023-10-02 00:00:00' and datum<'2023-10-18 23:59:00'";
            if ($reservationData = sql_query("select taj from foglalasok where id=? {$auchanCondition}", [$fid])->fetch(PDO::FETCH_ASSOC)) {
                if (!empty(trim($reservationData["taj"]))) {
                    sql_query("UPDATE foglalasok SET email=?, telefon=?, szuldatum=?, anyjaneve=?, szulhely=?, neme=?, irsz=?, varos=?, utca=?, munkakor=?, cegid=? WHERE taj=? {$auchanCondition} LIMIT 10",
                        [$data["email"], $data["telefon"], $data["szuldatum"], $data["anyjaneve"], $data["szulhely"], $data["neme"], $data["irsz"], $data["varos"], $data["utca"], $data["munkakor"], $data["cegid"], $reservationData["taj"]]);
                }
            }
        }
    }


    //private array $keltexMunkakorok = ["Áruátvevő","Áruátvételi adminisztrátor","Raktári munkatárs (Göngyöleg)","Raktári munkatárs (Göngyöleg)","Áruátvevő","Csoportvezető","Csoportvezető-komissió","E-ker és hsz. ellenőr","Jövedéki adminisztrátor","Karbantartási adminisztrátor","Karbantartó","Készlet koordinátor","Készletgazda","Komissiózó","Magasemelős","Magasemelős targoncás","Minőségellenőr","Raklapválogató","Raktári adminisztrátor","Raktári munkatárs - száraz","Raktári munkatárs - friss","Mirelit áruátvevő","Mirelit komissiózó","Mirelit magasemelős","Mirelit készletgazda","Mirelit csoportvezető"];

    private array $keltexMunkakorok = [
        'Alkalmazott az önkiszolgáló osztályon',
        'Áru összekészítésért felelős munkatárs',
        'Áruátvevő',
        'Áruellátó asszisztens',
        'Áruellátó manager',
        'Árufeltöltő',
        'Áruházvezető',
        'Áruházvezető helyettes',
        'Árukiszállításért felelős munkatárs',
        'Bartender',
        'Beszerzési vezető',
        'Betanított szigetelő',
        'Boltvezető',
        'Boltvezető-helyettes',
        'Burkoló',
        'Címkéző',
        'Contact Center Representative',
        'Csőhálózati szerelő I.',
        'Csőhálózati szerelő II.',
        'Dekoratőr',
        'E-ker Dark Store csoport vezető',
        'E-ker Dark Store előkészítési munkatárs',
        'Eladó-pénztáros',
        'Építésvezető',
        'Építészmérnök, tervező',
        'Erőforrás tervező manager',
        'Értékesítési tanácsadó',
        'Értékesítő szaktanácsadó',
        'Főpénztár-helyettes',
        'Főpénztáros',
        'Gépészmérnök',
        'Gépkezelő',
        'Gépkocsivezető',
        'Géplakatos',
        'Gyógyszertári szakasszisztens',
        'Hegesztő',
        'Hentes-eladó',
        'HR munkatárs',
        'Igénytervező',
        'Igénytervező csoportvezető',
        'Informatikus',
        'Irodai ügyintéző',
        'Irodavezető',
        'ISP Készletellenőr',
        'ISP üzemvezető-helyettes',
        'Junior geológus',
        'Karbantartó',
        'Kereskedelmi ügyintéző',
        'Készlet és anyagnyilvántartó',
        'Készletellenőr',
        'Kézicsomagoló',
        'Kivizsgáló hibaelhárító',
        'Komissiózó',
        'Kommunikációs munkatárs',
        'Kontrolling asszisztens',
        'Kontrolling vezető',
        'Konyhai kisegítő',
        'Kőműves',
        'Könyvelő',
        'Környezetmérnök',
        'Környezetvédelmi szakértő',
        'Külső szerelő',
        'Laboráns',
        'Laboráns',
        'Laborvezető-helyettes',
        'Lakatos',
        'Lean navigátor',
        'Logisztika csoportvezető',
        'Logisztikai asszisztens',
        'Logisztikai asszisztens',
        'Magasemelős',
        'Marketing manager',
        'Minőségellenőr',
        'Munkaerő toborzó',
        'Műszak-részlegvezető',
        'Műszaki vezető',
        'Online áru összekészítő',
        'Pék',
        'Pénztáros',
        'Pénzügyi asszisztens',
        'Projektmenedzser',
        'Pultos',
        'Raktári csoportvezető',
        'Raktári dolgozó',
        'Raktári kisegítő',
        'Raktári munkatárs',
        'Raktáros',
        'Rendszertámogató',
        'Részlegvezető',
        'Reszortfelelős',
        'Segédmunkás',
        'Szakács',
        'Szakeladó',
        'Szállítmányozási asszisztens',
        'Szenior beszerzési munkatárs',
        'Szerviz technikus',
        'Szobalány',
        'Tanuló',
        'Targoncavezető',
        'Távközlési hálózatépítő',
        'Technikus',
        'Tehergépkocsi-vezető',
        'TMK karbantartó',
        'To go szakeladó',
        'Ügyfélszolgálati központ tájékoztatója',
        'Üzemvezető helyettes',
        'Üzletvezető helyettes',
        'Villanyszerelő',
        'Vízminta vevő',
        'Vízműgépész',
        'Áruátvevő',
        'Áruátvételi adminisztrátor',
        'Raktári munkatárs (Göngyöleg)',
        'Raktári munkatárs (Göngyöleg)',
        'Áruátvevő',
        'Csoportvezető',
        'Csoportvezető-komissió',
        'E-ker és hsz. ellenőr',
        'Jövedéki adminisztrátor',
        'Karbantartási adminisztrátor',
        'Karbantartó',
        'Készlet koordinátor',
        'Készletgazda',
        'Komissiózó',
        'Magasemelős',
        'Magasemelős targoncás',
        'Minőségellenőr',
        'Raklapválogató',
        'Raktári adminisztrátor',
        'Raktári munkatárs - száraz',
        'Raktári munkatárs - friss',
        'Mirelit áruátvevő',
        'Mirelit komissiózó',
        'Mirelit magasemelős',
        'Mirelit készletgazda',
        'Mirelit csoportvezető'
    ];

}