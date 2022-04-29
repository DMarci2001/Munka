<?php

class AdminDoctorsPage extends AdminCorePage {

    private $bookingService;
    private $beoEditor;

    public function __construct()
    {
        parent::__construct();

        $this->beoEditor = new AdminBeoEditor();

        if (!isset($_SESSION["orvosbeosztascegfilter"])) $_SESSION["orvosbeosztascegfilter"] = 0;
        if (!isset($_SESSION["cegfilter"])) $_SESSION["cegfilter"] = 0;

        if (isset($_POST["addszabadsag"])) {
            if ($this->adminUser->szabiAccess()) {
                $orvosId   = intval($_GET["szerk"]);
                $tol       = $_POST["szabadsagtol"];
                $ig        = $_POST["szabadsagig"];
                $startDate = $tol;
                $groupId   = 0;

                if (strtotime($tol) > strtotime($ig)) {
                    $_SESSION["doctorsaveerror"] = "A szabadság kezdő dátumának kisebbnek kell lennie mint a vég dátum!";
                }

                if (strtotime($ig) - strtotime($tol) > 86400*31) {
                    $_SESSION["doctorsaveerror"] = "A szabadság nem lehet hozsszabb mint 1 hónap!";
                }

                if (!isset($_SESSION["doctorsaveerror"])) {
                    $rowo = sql_fetch_array(sql_query("select * from orvosok where id=?", [$orvosId]));

                    while (strtotime($startDate) <= strtotime($ig)) {
                        sql_query("insert into szabadsag set datumtol=?, datumig=?, oid=?", [$startDate, $startDate, $orvosId]);
                        $newId = sql_insert_id();
                        if ($groupId == 0) {
                            $groupId = $newId;
                        }
                        sql_query("update szabadsag set groupid=? where id=?", [$groupId, $newId]);

                        $startDate = date("Y-m-d", strtotime("{$startDate} +1 day"));
                    }

                    $foService = new FoglaljOrvostService();
                    $foService->sendSzabadsag($groupId);

                    logActivity("orvos", $orvosId, "{$rowo["nev"]} szabadság hozzáadva: " . $tol . " - " . $ig, print_r($_POST, true));
                }
            }
            $_POST["orvosmentes"]=1;
        }

        if (isset($_GET["delszabadsag"])) {
            if ($this->adminUser->szabiAccess()) {
                $rowo=sql_fetch_array(sql_query("select * from orvosok where id=?",array($_GET["szerk"])));

                $foService = new FoglaljOrvostService();
                $foService->deleteSzabadsag($_GET["delszabadsag"]);

                sql_query("delete from szabadsag where groupid=? and oid=? and groupid<>0", [$_GET["delszabadsag"], $_GET["szerk"]]);
                logActivity("orvos",$_GET["szerk"],"{$rowo["nev"]} szabadság törlése",print_r($_POST,true));
            }
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_POST["addbeorow"])) {
            $doctorId = intval($_POST["doctorid"]);

            if ($this->adminUser->doctorsCalendarAccess()) {
                if ($beo = sql_fetch_array(sql_query("select * from orvos_beosztas_new where groupid=? and orvosid=? limit 1", [$_POST["groupid"], $doctorId]))) {
                    sql_query("insert into orvos_beosztas_new set orvosid=?, groupid=?, beocegek=?", [$doctorId, $_POST["groupid"], $beo["beocegek"]]);
                }
            }

            echo $this->beoEditor->show($doctorId);
            die();
        }

        if (isset($_POST["delbeorow"])) {
            $doctorId = intval($_POST["doctorid"]);

            if ($this->adminUser->doctorsCalendarAccess()) {
                if ($beo = sql_fetch_array(sql_query("select * from orvos_beosztas_new where id=? and orvosid=? and fobid<>0", [$_POST["id"], $doctorId]))) {
                    $foService = new FoglaljOrvostService();
                    $result = $foService->deleteConsultation($beo["id"]);
                }
                sql_query("delete from orvos_beosztas_new where id=? and orvosid=?", [$_POST["id"], $doctorId]);
            }

            echo $this->beoEditor->show($doctorId);
            die();
        }

        if (isset($_POST["addbeocopy"])) {
            $doctorId = intval($_POST["doctorid"]);

            if ($this->adminUser->doctorsCalendarAccess()) {
                $group = sql_query("select max(groupid)+1 as groupid from orvos_beosztas_new")->fetchAll(PDO::FETCH_ASSOC);
                $groupId = $group[0]["groupid"] ?? 1;

                $beos = sql_query("select * from orvos_beosztas_new where groupid=? and orvosid=?", [$_POST["groupid"], $doctorId])->fetchAll(PDO::FETCH_ASSOC);
                foreach ($beos as $beoData) {
                    sql_query("insert into orvos_beosztas_new set
                               orvosid=?,
                               cegid=0,
                               helyszinid=?,
                               nap=?,
                               beonap=?,
                               tol=?,
                               ig=?,
                               potig=?,
                               hetek=?,
                               binterval=?,
                               csaksorban=?,
                               tipusok=?,
                               aktiv=?,
                               noreservation=?,
                               fobid=?,
                               remoteid=?,
                               beocegek='',
                               validfrom=?,
                               validto=?,
                               bmegj=?,
                               groupid=?
                        ", [$beoData["orvosid"], $beoData["helyszinid"], $beoData["nap"], $beoData["beonap"], $beoData["tol"], $beoData["ig"], $beoData["potig"], $beoData["hetek"], $beoData["binterval"], $beoData["csaksorban"], $beoData["tipusok"], $beoData["aktiv"], $beoData["noreservation"], $beoData["fobid"], $beoData["remoteid"], $beoData["validfrom"], $beoData["validto"], $beoData["bmegj"], $groupId]);
                }
            }

            echo $this->beoEditor->show($doctorId);
            die();
        }

        if (isset($_POST["addbeoblock"])) {
            $doctorId = intval($_POST["doctorid"]);

            if ($this->adminUser->doctorsCalendarAccess()) {
                $group = sql_query("select max(groupid)+1 as groupid from orvos_beosztas_new")->fetchAll(PDO::FETCH_ASSOC);
                $groupId = $group[0]["groupid"] ?? 1;

                sql_query("insert into orvos_beosztas_new set orvosid=?, groupid=?, beocegek=''", [$doctorId, $groupId]);
            }

            echo $this->beoEditor->show($doctorId);
            die();
        }


        if (isset($_POST["syncbeosztas"])) {
            if ($this->adminUser->doctorsCalendarAccess()) {
                if (isset($_SESSION["orvosbeosztascegfilter"])) {
                    $syncApi = new BookingSyncApi();
                    $syncApi->sendBeosztas($_POST["pecsetszam"], $_SESSION["orvosbeosztascegfilter"]);
                }
            }
            $_POST["orvosmentes"]=1;
        }

        if (isset($_GET["addsmsphone"])) {
            sql_query("insert into smsphones set orvosid=?",array($_GET["oid"]));
            echo $this->smsAlertSettings($_GET["oid"]);
            die();
        }
        if (isset($_GET["deletesmsphone"])) {
            sql_query("delete from smsphones where id=? and orvosid=?",array($_GET["id"],$_GET["oid"]));
            echo $this->smsAlertSettings($_GET["oid"]);
            die();
        }


        if (isset($_GET["showcegvalaszto"])) {
            $phoneId=intval($_GET["showcegvalaszto"]);
            $rowo=sql_fetch_array(sql_query("select * from smsphones where id='{$phoneId}'"));

            $res=sql_query("select * from cegek where true order by megnev");

            echo "<div style='width:650px;'>";
            while ($row=sql_fetch_array($res)) {
                echo "<label><input onchange='saveCegList({$phoneId})' type='checkbox' name='cegvalaszto{$phoneId}_{$row["id"]}' value='{$row["megnev"]}' ".(substr_count($rowo["cegek"],"|{$row["id"]}|")>0?"checked":"")."/>{$row["megnev"]}&nbsp;&nbsp;</label>";
            }

            echo "<div style=''><input type='button' onclick='showCegValaszto({$phoneId});' value='OK'></div>";
            echo "</div>";

            die();
        }

        if (isset($_GET["savesmsphonetipusok"])) {
            sql_query("update smsphones set cegek=? where id=?",array($_GET["value"],round($_GET["savesmsphonetipusok"])));
            die();
        }

        if (isset($_GET["changeinterval"])) {
            $beosztasid = intval($_GET["changeinterval"]);
            sql_query("update orvos_beosztas_new set binterval=? where id=?", [intval($_GET["interval"]), $beosztasid]);
            die();
        }

        if (isset($_GET["showtipusvalaszto"])) {
            if (!$this->adminUser->doctorsCalendarAccess()) {
                die();
            }

            $beosztasid = intval($_GET["showtipusvalaszto"]);
            $rowo = sql_fetch_array(sql_query("select * from orvos_beosztas_new where id=?", [$beosztasid]));

            $tipusok = sql_query("select * from szurestipusok where true order by megnev")->fetchAll(PDO::FETCH_ASSOC);

            echo "<div style='width:1000px;padding:4px 0px;'>";

            foreach ($tipusok as $tipus) {
                $class = substr_count($rowo["tipusok"],"|{$tipus["id"]}|")>0 ? "serviceselected" : "servicenotselected";

                echo "<a data-beoid='{$beosztasid}' data-tipusid='{$tipus["id"]}' title='' class='{$class}' href='#' onclick='toggleBeoService(this);return false;'>{$tipus["megnev"]}</a> ";
            }

            echo "<div style=''><input type='button' onclick='showTipusValaszto({$beosztasid});' value='OK'></div>";
            echo "</div>";
            die();
        }
		
		if (isset($_GET["savebeosztastipusok"])) {
            $bid = intval($_GET["savebeosztastipusok"]);
            sql_query("update orvos_beosztas_new set tipusok=? where id=?", array($_GET["value"], $bid));
            die();
        }
		
		if (isset($_GET["showcegvalasztov2"])) {
            if (!$this->adminUser->doctorsCalendarAccess()) {
                die();
            }
            $restrictid = intval($_GET["showcegvalasztov2"]);
            $rowo = sql_fetch_array(sql_query("SELECT * FROM foglalas_korlatozasok WHERE id=?", array($restrictid)));
			
			//Kilistázom az összes céget amihez van beoja:
            $companies = $this->beoEditor->beosztasService->getDoctorCompanies($rowo["orvosid"]);

            echo "<div style='width:750px;'>";
            foreach ($companies as $company) {
                echo "<label><input onchange='saveceglistav2({$restrictid})' type='checkbox' name='cegvalasztov2{$restrictid}_{$company["id"]}' value='{$company["megnev"]}' ".(substr_count($rowo["cegek"],"|{$company["id"]}|")>0?"checked":"")."/>{$company["megnev"]}&nbsp;&nbsp;</label>";
            }

            echo "<div style=''><input type='button' onclick='showcegvalasztov2({$restrictid});' value='OK'></div>";
            echo "</div>";
            die();
        }
		
		if (isset($_GET["savecegekv2"])) {
            sql_query("UPDATE foglalas_korlatozasok SET cegek=? WHERE id=?", array($_GET["value"], intval($_GET["savecegekv2"])));
            die();
        }

        if (isset($_POST["savebeosztascompanies"])) {
            $oldCompanies = $_POST["savebeosztascompanies"];
            $doctorId = intval($_POST["doctorid"]);
            $groupId = intval($_POST["groupid"]);

            sql_query("update orvos_beosztas_new set beocegek=? where orvosid=? and beocegek=?", [$_POST["value"], $doctorId, $oldCompanies]);
            sql_query("update orvos_beosztas_new set groupid=? where orvosid=? and beocegek=?", [$groupId, $doctorId, $_POST["value"]]);

            echo $this->beoEditor->show($doctorId);
            die();
        }

        if (isset($_POST['checkSzabiData'])) {
            $tol = date("Y-m-d 00:00:00", strtotime($_POST["start"]));
            $ig  = date("Y-m-d 23:59:59", strtotime($_POST["end"]." +1 day"));

            $query = sql_query("SELECT * FROM foglalasok WHERE orvosassigned = ? AND datum BETWEEN ? AND ? limit 20", array($_POST['orvosid'], $tol, $ig));
            $data = "";
            while($result = sql_fetch_array($query)) {
                $data.=$result['nev'].",".$result['datum']."|";
            }
            $data =  substr($data, 0, -1);
            echo $data;
            die();
        }
		
		if(isset($_POST['restricttobooking'])){
			sql_query("INSERT INTO foglalas_korlatozasok SET orvosid=?,uid=?,datum=?",array(intval($_GET['szerk']),$this->adminUser->user["id"],date("Y-m-d H:i:s")));
			$_POST["orvosmentes"]=1;
		}
		if (isset($_GET["delrestriction"])) {
            sql_query("DELETE FROM foglalas_korlatozasok WHERE id=? AND orvosid=?",array($_GET['delrestriction'],$_GET["szerk"]));
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }
		

        if (isset($_POST["orvosmentes"]) || isset($_POST["orvosform"])) {
            $sor = 1;
			$restrict = 1;
            $oid = intval($_GET["szerk"]);
            $_SESSION["orvosbeosztascegfilter"] = $_POST["orvosbeosztascegfilter"];
			
            if ($this->adminUser->doctorsAccess()) {
				
                if ($this->adminUser->doctorsCalendarAccess()) {
                    while (isset($_POST["beosztasid{$sor}"])) {
                        $aktiv  = isset($_POST["aktiv{$sor}"])?1:0;
                        $sorban = isset($_POST["csaksorban{$sor}"])?1:0;
                        $sorban = isset($_POST["csakvsorban{$sor}"])?2:$sorban;
                        $noreservation = isset($_POST["noreservation{$sor}"])?1:0;
                        $nopack = isset($_POST["nopack{$sor}"])?1:0;
                        $potig = $_POST["potig{$sor}"];

                        if (!preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9])/", $potig)) {
                            $potig = "";
                        }

                        $nap = $_POST["weekday{$sor}"];
                        if ($nap == 10) {
                            $_POST["validfrom{$sor}"] = "0000-00-00";
                            $_POST["validto{$sor}"] = "0000-00-00";
                        }

                        $params = [$nap, $_POST["beonap{$sor}"], $_POST["hetek{$sor}"], $_POST["helyszinid{$sor}"], $sorban, $aktiv, $_POST["tol{$sor}"], $_POST["ig{$sor}"], $potig, $noreservation, $_POST["validfrom{$sor}"], $_POST["validto{$sor}"], $_POST["bmegj{$sor}"], $nopack, $_POST["beosztasid{$sor}"]];
                        sql_query("update orvos_beosztas_new set nap=?, beonap=?, hetek=?, helyszinid=?, csaksorban=?, aktiv=?, tol=?, ig=?, potig=?, noreservation=?, validfrom=?, validto=?, bmegj=?, nopack=? where id=?", $params);
                        $sor++;
                    }

					//korlátozások mentése:
					while (isset($_POST["restrictid{$restrict}"])) {
                        $aktiv=0;
                        if (isset($_POST["restrictionstatus{$restrict}"])) $aktiv=1;
						
						//echo "helyszinid=".$_POST['restrict_helyszin'.$restrict].", datasource=".$_POST['datasource'.$restrict].", restrict_time=".$_POST['restrict_time'.$restrict].", aktiv=".$_POST['restrictionstatus'.$restrict];
						
						$columns = "helyszinid=?, datasource=?, restrict_time=?, aktiv=?";
						$data    = array($_POST["restrict_helyszin{$restrict}"],$_POST["datasource{$restrict}"],$_POST["restrict_time{$restrict}"],$aktiv,$_POST["restrictid{$restrict}"]);
                        //cegid='".addslashes($_POST["cegid{$sor}"])."',
                        sql_query("UPDATE foglalas_korlatozasok SET {$columns} WHERE id=?",$data);
                        $restrict++;
                    }
                }

                $sor=1;
                while (isset($_POST["phoneid{$sor}"])) {
                    $smsfoglalas=$smsgroupfoglalas=0;
                    if (isset($_POST["smsfoglalas{$sor}"])) $smsfoglalas=1;
                    if (isset($_POST["smsgroupfoglalas{$sor}"])) $smsgroupfoglalas=1;

                    sql_query("update smsphones set tel=?, smsfoglalas=?, smsgroupfoglalas=? where id=?"
                        ,array($_POST["smsphone{$sor}"], $smsfoglalas, $smsgroupfoglalas, $_POST["phoneid{$sor}"]));
                    $sor++;
                }

                if (!isset($_POST["aktiv"])) $_POST["aktiv"]=0;
                if (!isset($_POST["visszaigazol"])) $_POST["visszaigazol"]=0;
                if (!isset($_POST["onlytel"])) $_POST["onlytel"]=0;
                if (!isset($_POST["smsfoglalas"])) $_POST["smsfoglalas"]=0;
                if (!isset($_POST["smsgroupfoglalas"])) $_POST["smsgroupfoglalas"]=0;
                if (!isset($_POST["telpublic"])) $_POST["telpublic"]=0;

                if (!isset($_POST['szak_belgyogy'])) $_POST['szak_belgyogy']=0;
                if (!isset($_POST['szak_rtg'])) $_POST['szak_rtg']=0;
                if (!isset($_POST['szak_uh'])) $_POST['szak_uh']=0;
                if (!isset($_POST['szak_borgyogy'])) $_POST['szak_borgyogy']=0;
                if (!isset($_POST['szak_szemesz'])) $_POST['szak_szemesz']=0;
                if (!isset($_POST['szak_kardio'])) $_POST['szak_kardio']=0;
                if (!isset($_POST['szak_torna'])) $_POST['szak_torna']=0;

                if (!isset($_POST['szak_labor'])) $_POST['szak_labor']=0;
                if (!isset($_POST['szak_urologia'])) $_POST['szak_urologia']=0;
                if (!isset($_POST['szak_nogyogy'])) $_POST['szak_nogyogy']=0;
                if (!isset($_POST['szak_tudogyogy'])) $_POST['szak_tudogyogy']=0;
                if (!isset($_POST['szak_ortopedia'])) $_POST['szak_ortopedia']=0;

                $vizsgtipusok = $_POST['szak_belgyogy'].",".$_POST['szak_rtg'].",";
                $vizsgtipusok.= $_POST['szak_uh'].",".$_POST['szak_borgyogy'].",";
                $vizsgtipusok.= $_POST['szak_szemesz'].",".$_POST['szak_kardio'].",";
                $vizsgtipusok.= $_POST['szak_torna'].",".$_POST['szak_labor'].",";

                $vizsgtipusok.= $_POST['szak_urologia'].",".$_POST['szak_nogyogy'].",";
                $vizsgtipusok.= $_POST['szak_tudogyogy'].",".$_POST['szak_ortopedia'];

                sql_query("update orvosok set 
                    nev=?,
                    pecsetszam=?,
                    email=?,
                    tel=?,
                    onlytel=?,
                    smsfoglalas=?,
                    smsgroupfoglalas=?,
                    telpublic=?,
                    hmedemail=?,
                    visszaigazol=?,
                    visszaigazolemail=?,
                    szurestipusok=?,
                    gender=?,
                    aktiv=?
                where id=?", array($_POST["nev"], $_POST["pecsetszam"], $_POST["email"], $_POST["tel"], $_POST["onlytel"], $_POST["smsfoglalas"], $_POST["smsgroupfoglalas"], $_POST["telpublic"], $_POST["hmedemail"], $_POST["visszaigazol"], $_POST["visszaigazolemail"], $_POST["szurestipusok"], $_POST["gender"], $_POST["aktiv"], $oid));

                logActivity("orvos",$oid,$_POST["nev"]." adatlap",print_r($_POST,true));
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_POST["founsync"])) {
            $oid = intval($_POST["oid"]);
            sql_query("update orvosok set foid=0 where id=?", [intval($oid)]);
            echo $this->foglaljOrvostSyncButton($oid);
            die;
        }

        if (isset($_POST["fosync"])) {
            $foService = new FoglaljOrvostService();
            $oid = intval($_POST["oid"]);
            $message = "";

            if ($result = $foService->sendDoctor($oid)) {
                $xml = simplexml_load_string($result);
                $message = (string)$xml->RETURN["RETMESSAGE"];

                if (ctype_digit($message)) {
                    sql_query("update orvosok set foid=0 where foid=?", [$message]);
                    sql_query("update orvosok set foid=? where id=?", [$message, $oid]);
                }
            }

            echo $this->foglaljOrvostSyncButton($oid, $message);
            die;
        }

        if (isset($_POST["getfodata"])) {
            $foService = new FoglaljOrvostService();
            $oid = intval($_POST["oid"]);

            if ($result = $foService->getFieldsByDoctor($oid)) {
                echo "<div style='margin-top:10px;font-weight: bold'>Szolgáltatások</div>";
                echo "<pre style='padding:5px;white-space: pre-wrap;background:#ddd;'>". Utils::converResult($result)."</pre>";

                //beosztások átküldése
                // - csak a hungáriamed cég beosztásai mennek
                // - multbéli beosztásokat nem küldjük
                // - az inaktiv jelölésű beosztások törlésre kerülnek a foglaljorvosnál, ott nincs aktiv - inaktiv beállítás
                echo "<div style='margin-top:10px;font-weight: bold'>Beosztás szinkron:</div>";
                $res = sql_query("select b.* from orvos_beosztas_new b where orvosid=? and instr(b.beocegek, ?) and noreservation=0 AND (beonap>DATE(NOW()) OR nap<>10) order by b.nap desc", [$oid, "|".Booking_Constants::DEFAULT_COMPANY_ID."|"]);
                while ($beo = sql_fetch_array($res)) {
                    if ($beo["fobid"] == 0) {
                        if ($beo["aktiv"] == 1) {
                            $result = $foService->newConsultation($beo["id"]);
                            try {
                                error_reporting(0);
                                $xml = simplexml_load_string($result);
                                $message = (string)$xml->RETURN["RETMESSAGE"];

                                if (ctype_digit($message)) {
                                    sql_query("update orvos_beosztas_new set fobid=? where id=?", [$message, $beo["id"]]);
                                }
                            } catch(Exception $e) {
                                $message = $result;
                            }
                        }
                    } else {
                        if ($beo["aktiv"] == 1) {
                            $result = $foService->modifyConsultation($beo["id"]);
                        } else {
                            $foService->deleteConsultation($beo["id"]);
                            sql_query("update orvos_beosztas_new set fobid=0 where id=?", [$beo["id"]]);
                        }
                    }
                    echo "<pre style='padding:5px;white-space: pre-wrap;background:#ddd;'>". Utils::converResult($result)."</pre>";
                }

                echo "<div style='margin:10px 0px;font-weight: bold'>Foglalások</div>";

                $res = sql_query("SELECT * FROM foglalasok f WHERE f.`orvosassigned`=? AND datum>date(NOW()) ORDER BY datum", [$oid]);
                while ($reservationData = sql_fetch_array($res)) {
                    echo "<div>{$reservationData["datum"]} ".($reservationData["fofid"]==0?" <span style='color:#f00;'>nincs szinkronizálva</span>":" <span style='color:#0a0;'>szinkronizálva</span>")."</div>";

                    if ($reservationData["fofid"] == 0) {
                        $result = $foService->newReservation($reservationData["id"]);
                        echo "<pre style='padding:5px;white-space: pre-wrap;background:#ddd;'>". Utils::converResult($result[0])."</pre>";
                    }

                }

                echo "<div style='margin:10px 0px;font-weight: bold'>Szabadságok</div>";

                $res = sql_query("SELECT groupid, foid, min(datumtol) as mindatum, max(datumtol) as maxdatum FROM szabadsag WHERE oid=? and datumtol>=date(now()) group by groupid", [$oid]);
                while ($szabadsagData = sql_fetch_array($res)) {
                    echo "<div>{$szabadsagData["mindatum"]} - {$szabadsagData["maxdatum"]} ".($szabadsagData["foid"]==0?" <span style='color:#f00;'>nincs szinkronizálva</span>":" <span style='color:#0a0;'>szinkronizálva</span>")."</div>";

                    if ($szabadsagData["foid"] == 0) {
                        //$result = $foService->newReservation($reservationData["id"]);
                        //echo "<pre style='padding:5px;white-space: pre-wrap;background:#ddd;'>". Utils::converResult($result[0])."</pre>";
                    }
                }

                echo "<br/><br/>";


                $result = $foService->sendSzabadsag();
                //echo "<pre style='padding:5px;white-space: pre-wrap;background:#ddd;'>". htmlentities(trim(str_replace("\n\n","\n",str_replace("<","\n<", $result))))."</pre>";
            } else {
                echo "hibás lekérdezés";
            }
            die;
        }

        if(isset($_POST['showQndA'])&&$_POST['showQndA']==true){
			//ki kell nyernem az orvos QndA json adathalmazából a kiválasztott szűréstípusra vonatkozó kérdéseket.
			
			$orvos = sql_fetch_array(sql_query("SELECT * FROM orvosok WHERE id=?",array($_POST['orvosid'])));
			$szurestipus = sql_fetch_array(sql_query("SELECT * FROM szurestipusok WHERE id=?",array($_POST['szurestipus'])));
			
			$sor=0;
			$html = "";
            $html.= "<div style='color:#444;text-align:center;'>";
            $html.= "<div id='loginbox' class='loginbox'>";
            $html.= "<div class='loginhead'>{$szurestipus['megnev']}</div>";

            $html.= "<div style='padding:20px;text-align:center;'>";
            //$html.= "<div style='font-size:18px;'>Tranzakció: Ft</div>";
			
			$html.= "<form id='questions'><table style='width:100%'>";
			
			if(!empty($orvos['questions']))
			{
				$questionArr=json_decode($orvos['questions'],true);
				foreach($questionArr as $each){
					if(!isset($each['placeholder'])) $each['placeholder']="";
					if($each['servicetype']==$_POST['szurestipus']){
						$html.= "	<tr>";
						$html.= "		<td><input style='padding:5px;width:300px' type='textbox' name='kerdes-{$sor}' value='{$each['question']}' placeholder='Kérdés...'/></td>";
						$html.= "		<td><select style='padding:5px' onchange=\" if( $(this).val()!='textarea' ) { $('#valaszopciok-{$sor}').prop('disabled',false) } else { $('#valaszopciok-{$sor}').prop('disabled',true) } \" name='valasztipus-{$sor}'>";
						$html.= "				<option ".($each['answertype']=='textarea'?'selected':'')." value='textarea'>Szövegmező</option>";
						$html.= "				<option ".($each['answertype']=='radio'?'selected':'')." value='radio'>Rádió gomb</option>";
						$html.= "				<option ".($each['answertype']=='checkbox'?'selected':'')." value='checkbox'>Checkbox</option>";
						$html.= "		</select></td>";
						$html.= "		<td><input style='padding:5px;width:300px' type='textbox' name='placeholder-{$sor}' value='{$each['placeholder']}' placeholder='Válasz mező szöveg...'/></td>";
						$html.= "		<td><input type='checkbox' value='1' ".($each['priority']==1?"checked":"")." name='kotelezo-{$sor}' >&nbsp;Kötelező</td>";
						$html.= "		<td><input style='padding:5px;width:300px' type='textbox' ".($each['answertype']=="textarea"?"disabled":"")." id='valaszopciok-{$sor}' name='valaszopciok-{$sor}' value='".(count($each['answeroptions'])>0?implode(";",$each['answeroptions']):"")."' placeholder='Válaszok;...'/></td>";
						$html.= "		<td><span style='cursor:pointer' onclick='delkerdes({$_POST['szurestipus']},{$_POST['orvosid']},{$sor})'><img src='images/trash.png' title='Sor törlése'/></span></td>";
						$html.= "	</tr>";
						$sor++;
					}
				}
			}
			
			if($sor==0) $html.= "<tr><td align='center'>Még nincsen kérdés létrehozva!</td></tr>";
			$html.= "</table></form>";

            $html.= "<div id='refunbuttonsor' style='padding-top:10px;'>";
			$html.= "<input onclick='saveQndA({$_POST['szurestipus']},{$_POST['orvosid']})' type='button' style='background:#f00;' value='Mentés' />&nbsp;";
			$html.= "<input onclick='addkerdes({$_POST['szurestipus']},{$_POST['orvosid']})' type='button' value='Kérdés hozzáadása +' />&nbsp;";
			$html.= "<input onclick='hideGeneralPopup();return false;' type='button' id='simplerefundclosebutton' value='Bezárás' />";
			$html.= "</div>";
            $html.= "</div>";

            $html.= "</div>";
            $html.= "</div>";
			
			echo $html;
			
			//echo "{$_POST['szurestipus']} {$_POST['orvosid']}";
			die();
		}
		
		if(isset($_POST['addkerdes']) && isset($_POST['orvosid']) && isset($_POST['szurestipus'])){
			$html="";
			$sor=0;
			$rowk=sql_fetch_array(sql_query("SELECT * FROM orvosok WHERE id=?",array($_POST['orvosid'])));
			if(empty($rowk['questions'])){
				$questionArr[]=array("servicetype"=>$_POST['szurestipus'],"question"=>"placeholder","answertype"=>"textarea","placeholder"=>"","answeroptions"=>array());
				sql_query("update orvosok SET questions=? WHERE id=?",array(json_encode($questionArr,JSON_UNESCAPED_UNICODE),$_POST['orvosid']));
			}
			else{
				$questionArr=json_decode($rowk['questions'],true);
				array_push($questionArr,array("servicetype"=>$_POST['szurestipus'],"question"=>"placeholder","answertype"=>"textarea","placeholder"=>"","answeroptions"=>array()));
				sql_query("update orvosok SET questions=? WHERE id=?",array(json_encode($questionArr,JSON_UNESCAPED_UNICODE),$_POST['orvosid']));
			}
			
			die("ok");
		}
		
		if(isset($_POST['delkerdes']) && isset($_POST['orvosid']) && isset($_POST['szurestipus']) && isset($_POST['q'])){
			$rowk=sql_fetch_array(sql_query("SELECT * FROM orvosok WHERE id=?",array($_POST['orvosid'])));
			$questionArr=json_decode($rowk['questions'],true);
			if(isset($questionArr[$_POST['q']])){
				unset($questionArr[$_POST['q']]);
				$questionArr = array_values($questionArr);
			}
			
			sql_query("UPDATE orvosok SET questions=? WHERE id=?",array((count($questionArr)>0?json_encode($questionArr,JSON_UNESCAPED_UNICODE):""),$_POST['orvosid']));
			die("ok");
		}
		
		if(isset($_POST['saveQndA']) && isset($_POST['orvosid']) && isset($_POST['szurestipus'])){
			
			
			foreach($_POST['inputs'] as $input) $_POST[$input['name']]=$input['value'];
			unset($_POST['inputs']);
			$questionArr = array();
			if(isset($_POST['kerdes-0'])){
				$sor=0;
				do{
					if(!isset($_POST["valaszopciok-{$sor}"])) $_POST["valaszopciok-{$sor}"]=array();
					else {
						$_POST["valaszopciok-{$sor}"]=$options=explode(";",$_POST["valaszopciok-{$sor}"]);
					}
					if(!isset($_POST["kotelezo-{$sor}"])) $_POST["kotelezo-{$sor}"]=0;
					$questionArr[]=array("servicetype"=>$_POST['szurestipus'],"question"=>$_POST["kerdes-{$sor}"],"answertype"=>$_POST["valasztipus-{$sor}"],"answeroptions"=>$_POST["valaszopciok-{$sor}"],"placeholder"=>$_POST["placeholder-{$sor}"],"priority"=>$_POST["kotelezo-{$sor}"]);
					$sor++;
				}while(isset($_POST["kerdes-{$sor}"]));
			}
			
			sql_query("UPDATE orvosok SET questions=? WHERE id=?",array(json_encode($questionArr,JSON_UNESCAPED_UNICODE),$_POST['orvosid']));
			
			die("ok");
		}

    }

    public function showPage() {
        if (!$this->adminUser->doctorsAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        if (isset($_GET["szerk"])) {
            $oid = intval($_GET["szerk"]);
            $doctorData = sql_fetch_array(sql_query("select * from orvosok where id=?", [$oid]));
            $_POST = $doctorData;

            $GLOBALS["subtitle"] = $this->subtitle = $doctorData["nev"];

            //scan foglalások
            $api = new BookingSyncApi();
            $res = sql_query("SELECT * FROM foglalasok f WHERE f.`orvosassigned`=? AND datum>NOW() ORDER BY datum", [$oid]);
            while ($reservationData = sql_fetch_array($res)) {
                //$api->newReservation($reservationData["id"]);
            }

            $hibak="";

            if (isset($_SESSION["doctorsaveerror"])) {
                $hibak = $_SESSION["doctorsaveerror"];
                unset($_SESSION["doctorsaveerror"]);
            }

            if ($hibak != "") {
                echo "<div style='margin-bottom:10px;background:#f88;padding:10px;display:inline-block;'>{$hibak}</div>";
            }

            echo "<form name='iform' id='iform' method='post' enctype='multipart/form-data'><input type='hidden' name='orvosform' value='1'/><input type='hidden' id='orvosid' name='orvosid' value='{$_POST["id"]}'/>";
            echo "<table style='font-size:12px;width: 100%;'>";

            echo "<tr><td width='100'>Név:</td><td><input class='inputbox' style='width:400px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
            echo "<tr><td>Pecsétszám:</td><td><input class='inputbox' style='width:200px;' type='text' name='pecsetszam' value='{$_POST["pecsetszam"]}'> <span id='fosyncbutton'>".$this->foglaljOrvostSyncButton($oid)."</span></td></tr>";
            echo "<tr><td></td><td><div id='fodatadiv'></div></td></tr>";
            echo "<tr><td>Orvos E-mail címe:</td><td><input class='inputbox' style='width:600px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
            echo "<tr><td valign='top' style='padding-top:5px;'>Orvos telefonszáma:</td><td><input class='inputbox' style='width:200px;' type='text' name='tel' value='{$_POST["tel"]}'> <input type='checkbox' value=1 name='telpublic'".($_POST["telpublic"]==1?" checked":"")."> megjelenjen a foglalási oldalon <input type='checkbox' value=1 name='onlytel'".($_POST["onlytel"]==1?" checked":"")."> csak telefonra fogad bejelentkezést<div style='padding-top:5px;'>Fontos: A telefonszám formátuma 06201234567.</div></td></tr>";

            echo "<tr><td>SMS értesítés:</td><td><div id='smsalertsettings'>".$this->smsAlertSettings($oid)."</div></td></tr>";

            echo "<tr><td>&nbsp;</td><td><input type='checkbox' value=1 name='visszaigazol'".($_POST["visszaigazol"]==1?" checked":"")."> visszaigazolás szükséges, erre a címre: <input class='inputbox' style='width:200px;' type='text' name='visszaigazolemail' value='{$_POST["visszaigazolemail"]}'></td></tr>";
            echo "<tr><td>HMED értesítés email:</td><td><input class='inputbox' style='width:600px;' type='text' name='hmedemail' value='{$_POST["hmedemail"]}'></td></tr>";

            echo "<tr><td></td><td valign='top'><input type='checkbox' value=1 name='aktiv'".($_POST["aktiv"]==1?" checked":"")."> Aktív&nbsp;&nbsp;&nbsp;&nbsp;";
            echo "<input type='radio' name='gender' value='2' ".($_POST["gender"]==2 ? "checked":"")."/> Nő <input type='radio' name='gender' value='1' ".($_POST["gender"]==1 ? "checked":"")."/> Férfi";
            echo "</td></tr>";
            echo "</table>";

            echo "<div id='beoeditor'>";
            echo $this->beoEditor->show($oid);
            echo "</div>";

            echo "<table style='font-size:12px;width: 100%;'>";
            echo "<tr><td colspan='2'><div class='tdsepdiv' style='margin-top:10px;'>Szabadság</div></td></tr>";
            if (!$this->adminUser->szabiAccess()) {
                echo "<tr><td colspan='2' style=''><div class='nojog'>A szabadságok módosításához nincs jogosultsága</div></td></tr>";
            }

            echo "<tr><td colspan='2'>";
            $ressz=sql_query("select min(sz.datumtol) as datumtol, max(datumig) as datumig, groupid from szabadsag sz where oid=? and datumtol>date_sub(now(), interval 6 month) group by sz.groupid order by datumtol", [$_GET["szerk"]]);
            while ($rowsz=sql_fetch_array($ressz)) {
                echo "<div style='display:table-row;'>";
                echo "<div style='display:table-cell;vertical-align:middle;'>{$rowsz["datumtol"]} - {$rowsz["datumig"]}</div>";
                echo "<div style='display:table-cell;vertical-align:middle;padding-left:5px;'><a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delszabadsag={$rowsz["groupid"]}' onclick='return confirm(\"Biztos törlöd ezt a szabadság sort?\")'><img src='images/trash.png' title='Sor törlése'/></a></div>";
                echo "</div>";
            }
            echo "<div><input class='inputbox' style='width:100px;' type='text' name='szabadsagtol' value='' placeholder='-tól dátum'> - <input class='inputbox' style='width:100px;' type='text' name='szabadsagig' value='' placeholder='-ig dátum'> <input type='submit' onClick='return checkSzabiData()' name='addszabadsag' value='+ szabadság hozzáadása'></div>";
            echo "</td></tr>";

			echo "<tr><td colspan='2'><div class='tdsepdiv'>Foglalások korlátozása</div></td></tr>";

			$resb = sql_query("SELECT * FROM foglalas_korlatozasok WHERE orvosid=? ORDER BY datum",array($_GET['szerk']));
			$sor = 1;
			while($rowb = sql_fetch_array($resb)){	
				echo "<tr><td colspan='2'>";
				echo "<input type='hidden' name='restrictid{$sor}' value='{$rowb["id"]}'/>";
				echo "<div>";
				echo "<input type='checkbox' name='restrictionstatus{$sor}' ".($rowb["aktiv"]>0?"checked":"")." value='1' />";
				//echo "<select type='text' name=''> value=''/> Forrás<>";
				echo "<strong>Adatforrás:&nbsp;&nbsp;</strong><select name='datasource{$sor}'>";
				echo "	<option value='bejelentkezo'>Bejelentkező</option>";
				echo "	<option value='zeus'>Zeus</option>";
				echo "</select>&nbsp;&nbsp;";
				echo "<strong>Korlátozás:&nbsp;&nbsp;</strong><select name='restrict_time{$sor}'>";
				echo "	<option value='1' ".($rowb['restrict_time']=="1"?"selected":"")." >1 hónap</option>";
				echo "	<option value='2' ".($rowb['restrict_time']=="2"?"selected":"").">2 hónap</option>";
				echo "	<option value='3' ".($rowb['restrict_time']=="3"?"selected":"").">3 hónap</option>";
				echo "</select>&nbsp;&nbsp;";
				echo "<select name='restrict_helyszin{$sor}'>";
				echo "	<option>Válassz címet!</option>";
				//Kilistázom az összes olyan címhelyet ahol rendel a doki
				$resa = sql_query("SELECT beo.*,h.cim FROM orvos_beosztas_new beo LEFT JOIN helyszinek h ON h.id=beo.helyszinid WHERE beo.orvosid=? GROUP BY beo.helyszinid",array($_GET['szerk']));
				while($rowa=sql_fetch_array($resa)){
					echo "<option ".($rowb['helyszinid']==$rowa['helyszinid']?"selected":"")." value='{$rowa['helyszinid']}'>{$rowa['cim']}</option>";
				}
				echo "</select>&nbsp;&nbsp;";
				$cegdb = (empty($rowb['cegek'])?0:count(explode(",",str_replace(array("||","|"),array(",",""),$rowb['cegek']))));
		
				$cegek = (empty($rowb['cegek'])?"":implode("",sql_fetch_row(sql_query("SELECT group_concat(' ',megnev) FROM cegek WHERE id IN(".str_replace(array("||","|"),array(",",""),$rowb['cegek']).")"))));
				echo "<span id='cegstatusz{$rowb['id']}'><a class='tlink' href='#' title='{$cegek}' onClick='showcegvalasztov2({$rowb['id']});return false'>{$cegdb} cég</a></span>";
				//cégek listája
				echo "&nbsp;&nbsp;<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delrestriction={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt az egységet?\")'><img src='images/trash.png' title='Sor törlése'/></a>";
				echo "</div>";
				echo "<div id='cegvalasztov2{$rowb['id']}'></div>";
				echo "</td></tr>";
			}
			
			echo "<tr><td colspan='2' valign='top'><input type='submit' name='restricttobooking' value='+ Korlátozás hozzáadása'></td></tr>";

            $docAgent = new DocAgent();
            echo "<tr><td colspan='2'><div class='tdsepdiv'>Fotó</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><div id='asseteditor'>".$docAgent->showAssetEditor(DocAgent::ASSET_DOCTOR_PHOTO, $oid)."</div>";
            echo "</td></tr>";

            echo "<tr><td colspan='2'><div class='tdsepdiv'>Orvosi kérdések szűrésípusokhoz</div></td></tr>";
			//Itt akkor az összes vizsgálatot ami bevan állítva az emberhez meg kell hogy jeleníteni O.o....
			$resq=sql_fetch_array(sql_query("SELECT GROUP_CONCAT(tipusok) AS tipusok FROM orvos_beosztas_new WHERE orvosid=?",array($_GET['szerk'])));
			
			$tipusok=array_values(array_unique(explode("||",substr(str_replace(",","",$resq['tipusok']),1,-1))));
			foreach($tipusok as $tipus){
				$szurestipus=sql_fetch_array(sql_query("SELECT * FROM szurestipusok WHERE id=?",array($tipus)));
				echo "<tr><td colspan='2'><div><a href='#' title='Szűrésípushoz tartozó kérdések szerkesztése.' onClick='setQndA({$_GET['szerk']},{$tipus});return false;'>{$szurestipus['megnev']}</a></div></td></tr>";
			}

            $type = explode( ",", $_POST['szurestipusok'] );
            echo "<tr><td colspan = '2'><div class='tdsepdiv' style='margin:10px 0px 0px 0px'>Vizsgálat típusok kiválasztása</div></td></tr>";
            echo "<tr><td><table>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(1, $type)?"checked":"")." name = 'szak_belgyogy' value = '1' /></td><td>Belgyógyász</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(2, $type)?"checked":"")." name = 'szak_rtg' value = '2' /></td><td>Röntgen</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(3, $type)?"checked":"")." name = 'szak_uh' value = '3' /></td><td>Ultrahang</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(4, $type)?"checked":"")." name = 'szak_borgyogy' value = '4' /></td><td>Bőrgyógyász</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(5, $type)?"checked":"")." name = 'szak_szemesz' value = '5' /></td><td>Szemész</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(6, $type)?"checked":"")." name = 'szak_kardio' value = '6' /></td><td>Kardiológia</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(7, $type)?"checked":"")." name = 'szak_torna' value = '7' /></td><td>Gyógytornász</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(8, $type)?"checked":"")." name = 'szak_labor' value = '8' /></td><td>Labor</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(9, $type)?"checked":"")." name = 'szak_urologia' value = '9' /></td><td>Urológia</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(10, $type)?"checked":"")." name = 'szak_nogyogy' value = '10' /></td><td>Nőgyógyászat</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(11, $type)?"checked":"")." name = 'szak_tudogyogy' value = '11' /></td><td>Tüdőgyógyászat</td></tr>";
            echo "<tr><td><input type = 'checkbox' ".(in_array(12, $type)?"checked":"")." name = 'szak_ortopedia' value = '12' /></td><td>Ortopédia</td></tr>";
            echo "</table></td></tr>";

            echo "</table>";


            echo "<div id='errorlistdiv' style='padding:10px;background:#f00;color:#fff;font-weight:bold;display:none;'></div>";

            if ($userConnect = sql_fetch_array(sql_query("SELECT * FROM users WHERE orvosid = ?", [$_GET["szerk"]]))) {
                echo "<div style='margin:10px 0px 5px 0px;padding:10px;border-radius: 5px;background:#8f8;display:inline-block;'>Ehhez az orvoshoz felhasználói adatlap is tartozik. <a title='szerkesztés' target='_blank' href='index.php?page=users&szerk={$userConnect["id"]}'><i class='fas fa-edit'></i></a></div><br/>";
                echo "<div>Jogosultságai: ".implode(", ", $this->adminUser->getUserPermissionList($userConnect))."</div>";
            }

            if ($this->adminUser->doctorsAccess()) {
                if (empty($userConnect)) {
                    echo "<br><input type='submit' onClick='accountini({$_GET['szerk']})' name = 'account-ini' value = 'Account inicializálás' /> ";
                } else {
                    echo "<br>";
                }
                echo "<input  type='submit' name='orvosmentes' value='Mentés'> ";
            } else {
                echo "<br><input onclick='alert(\"Az orvos adatlap módosításához nincs jogosultsága!\");return false;' type='submit' name='orvosmentes' value='Mentés'> ";
            }
            echo "<input type='submit' name='scancel' value='Vissza'> ";

            echo "</form>";
            return;
        }

        $orvosok = sql_query("SELECT GROUP_CONCAT(DISTINCT b.tipusok SEPARATOR '') AS tipusok,o.*,GROUP_CONCAT(DISTINCT h.cim separator '<br/>') AS cimek FROM orvosok o
        LEFT JOIN orvos_beosztas_new b ON b.`orvosid`=o.`id`
        LEFT JOIN helyszinek h ON h.`id`=b.`helyszinid`
        where o.pecsetszam<>'temp'
        GROUP BY o.id
        ORDER BY nev<>'Új orvos', nev")->fetchAll(PDO::FETCH_ASSOC);

        $kiemeltOrvosok = sql_query("SELECT GROUP_CONCAT(DISTINCT b.tipusok SEPARATOR '') AS tipusok,o.*,GROUP_CONCAT(DISTINCT h.cim separator '<br/>') AS cimek FROM orvosok o
        LEFT JOIN orvos_beosztas_new b ON b.`orvosid`=o.`id`
        LEFT JOIN helyszinek h ON h.`id`=b.`helyszinid`
        LEFT JOIN dokumentumok d on d.dataid=o.id and d.assetid='orvosphoto'
        where o.pecsetszam<>'temp' and (d.id is not null or o.foid<>0)
        GROUP BY o.id
        ORDER BY nev")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($kiemeltOrvosok)) {
            echo "<h2>Kiemelt orvosok</h2>";
            echo $this->_orvosLista($kiemeltOrvosok);
        }

        if (!empty($orvosok)) {
            echo "<h2>Összes orvos</h2>";
            echo $this->_orvosLista($orvosok);
        }

    }


    private function _orvosLista($orvosok) {
        $html = "";

        $docAgent = new DocAgent();

        $rest = sql_query("select * from szurestipusok");
        while ($rowt = sql_fetch_array($rest)) {
            $tipusnevek[$rowt["id"]] = $rowt["megnev"];
        }

        $html.= "<table cellpadding='0' cellspacing='0' border='0'>";
        foreach ($orvosok as $row) {
            $tipusok = [];
            $ta = explode("|",$row["tipusok"]);
            for ($i=0;$i<count($ta);$i++) {
                if (trim($ta[$i])!="") {
                    if (isset($tipusnevek[$ta[$i]])) {
                        $tipusok[] = $tipusnevek[$ta[$i]];
                    }
                }
            }

            $image = "<img style='width:50px;height:50px;object-fit: cover;' src='/admin/images/light-grey-box.png' title='' />";
            $assets = $docAgent->getAssetsByType(DocAgent::ASSET_DOCTOR_PHOTO, $row["id"]);
            if (!empty($assets)) {
                $image = "<img style='width:50px;height:50px;object-fit: cover;' src='{$assets[0]["url"]}' title='' />";
            }

            $tc = "tcella";
            if (!isset($first)) {
                $html.= "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (trim($row["nev"])=="") {
                $row["nev"] = "nincs neve";
            }
            $html.= "<tr>";
            $html.= "<td valign='top'><div class='{$tc}'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}&sp'>{$image}</a></div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>";
            $html.= "<a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}&sp'>{$row["nev"]}</a>";
            if (!empty($tipusok)) {
                $html.= "<div>".implode("<br/>", array_unique($tipusok))."</div>";
            }
            $html.= "</div></td>";
            $html.= "<td valign='top'><div class='{$tc}' style='min-width:300px;'>";
            if ($row["cimek"]!="") {
                $html.= "{$row["cimek"]}";
            } else {
                $html.= "<span style='color:#f00;'>nincs még beosztása</span>";
            }
            $html.= "</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}' style='min-width:20px;'>";
            if ($row["foid"] != 0) {
                $html.= "<span class='fo_badge' title='FoglaljOrvost kapcsolat id: {$row["foid"]}'>FO</span>";
            }
            $html.= "</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}' style='color:#f00;'>".($row["visszaigazol"]==1?"V":"")."</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}' style='min-width:50px;'>".($row["aktiv"]==1?"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>":"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>")."</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='return confirm(\"Biztosan törlöd ezt az orvost?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
            $html.= "</tr>";
            $html.= "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        $html.= "</table>";

        return $html;
    }

    private function smsAlertSettings($oid) {
        $htmlout="";
        $htmlout.="<div>";

        $res=sql_query("select * from smsphones where orvosid=?",array($oid));
        $x=1;
        while ($row=sql_fetch_array($res)) {
            $htmlout.="<div>";
            $htmlout.="<input style='width:110px;' type='text' id='smsphone{$x}' name='smsphone{$x}' placeholder='SMS telefonszám' value='{$row["tel"]}' /> ";

            $num=0;
            unset($idk);
            $idk[]=0;
            $titl="";

            $ik=explode("|",$row["cegek"]);
            for ($i=0;$i<count($ik);$i++) {
                if ($ik[$i]!="") {
                    $num++;
                    $idk[]=$ik[$i];
                }
            }

            if (count($idk)>1) {
                $rowtt=sql_fetch_array(sql_query("SELECT GROUP_CONCAT(megnev SEPARATOR ', ') AS megnevek FROM cegek WHERE id IN (".implode(",",$idk).")"));
                $titl=$rowtt["megnevek"];
            }
            $btn="{$num} cég";
            if ($num==0) $btn="Összes cég";

            $htmlout.="<span id='cegstatus{$row["id"]}'><a href='#' class='tlink' style='width:100px;' title='{$titl}' onclick='showCegValaszto({$row["id"]});return false;'>{$btn}</a></span> ";


            $htmlout.="<input type='checkbox' value=1 name='smsfoglalas{$x}'".($row["smsfoglalas"]==1?" checked":"")."> foglalás értesítés ";
            $htmlout.="<input type='checkbox' value=1 name='smsgroupfoglalas{$x}'".($row["smsgroupfoglalas"]==1?" checked":"")."> értesítés a másnapi foglalásokról (19 órakor) ";
            $htmlout.="[<a href='#' onclick='deleteSMSPhone({$oid},{$row["id"]});return false;'>szám törlése</a>]";
            $htmlout.="<input type='hidden' value='{$row["id"]}' name='phoneid{$x}' />";
            $htmlout.="</div>";
            $htmlout.="<div id='cegvalaszto{$row["id"]}'></div>";
            $x++;
        }
        $htmlout.="<div style='margin-top:5px;'><input onclick='addSMSPhone({$oid})' type='button' name='addsmstel' value='+ SMS telefonszám hozzáadása'></div>";
        $htmlout.="</div>";
        return $htmlout;
    }

    private function intervalOption($interval, $data) {
        return "<option value='{$interval}'".($data["binterval"]==$interval?" selected":"").">{$interval} perc</option>";
    }

    private function foglaljOrvostSyncButton($oid, $message = "") {
        $html = "";
        if ($orvosData = sql_fetch_array(sql_query("select * from orvosok where id=?", [$oid]))) {

            if (trim($orvosData["pecsetszam"]) != "") {
                if ($orvosData["foid"] == 0) {
                    $html .= " <a class='ujbutton' style='padding:3px 10px;' href='#' onclick='startFODoctorSync({$oid});return false;'>FoglaljOrvost Sync</a> ";
                }

                if ($message != "" && $orvosData["foid"] == 0) {
                    $html.= $message;
                } else {
                    if ($orvosData["foid"] != 0) {
                        $html.= "<span style='color:#080;'>FoglalOrvost.hu kapcsolat aktív</span> <a class='ujbutton' style='padding:3px 10px;' href='#' onclick='getFOData({$oid});return false;'>Lekérdezés</a>";
                    }
                }

            }
        }

        return $html;
    }
}

