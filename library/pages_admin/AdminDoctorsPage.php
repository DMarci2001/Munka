<?php

class AdminDoctorsPage extends AdminCorePage {


    private $bookingService;

    public function __construct()
    {
        parent::__construct();

        if (!isset($_SESSION["orvosbeosztascegfilter"])) $_SESSION["orvosbeosztascegfilter"] = 0;
        if (!isset($_SESSION["cegfilter"])) $_SESSION["cegfilter"] = 0;

        if (isset($_POST["addszabadsag"])) {
            if ($this->adminUtils->szabadsagJog()) {
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
                    //$foService->sendSzabadsag($groupId);

                    logActivity("orvos", $orvosId, "{$rowo["nev"]} szabadság hozzáadva: " . $tol . " - " . $ig, print_r($_POST, true));
                }
            }
            $_POST["orvosmentes"]=1;
        }

        if (isset($_GET["delszabadsag"])) {
            if ($this->adminUtils->szabadsagJog()) {
                $rowo=sql_fetch_array(sql_query("select * from orvosok where id=?",array($_GET["szerk"])));

                $foService = new FoglaljOrvostService();
                //$foService->deleteSzabadsag($_GET["delszabadsag"]);

                sql_query("delete from szabadsag where groupid=? and oid=? and groupid<>0", [$_GET["delszabadsag"], $_GET["szerk"]]);
                logActivity("orvos",$_GET["szerk"],"{$rowo["nev"]} szabadság törlése",print_r($_POST,true));
            }
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }


        if (isset($_GET["delbeosztas"])) {
            if ($this->adminUtils->beosztasModJog()) {
                $oid = intval($_GET["szerk"]);
                if ($beoData = sql_fetch_array(sql_query("select * from orvos_beosztas where id=? and orvosid=? and fobid<>0", array($_GET["delbeosztas"], $oid)))) {
                    $foService = new FoglaljOrvostService();
                    $result = $foService->deleteConsultation($beoData["id"]);
                }
                sql_query("delete from orvos_beosztas where id=? and orvosid=?", array($_GET["delbeosztas"], $oid));
            }
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if (isset($_POST["addbeosztas"])) {
            if ($this->adminUtils->beosztasModJog()) {
                if ($_SESSION["adminuser"]["jogosultsag"]>=2) {
                    if (isset($_SESSION["orvosbeosztascegfilter"])) {
                        sql_query("insert into orvos_beosztas set orvosid=?,cegid=?",array($_GET["szerk"],$_SESSION["orvosbeosztascegfilter"]));
                    }
                } else {
                    if (isset($_SESSION["orvosbeosztascegfilter"])) {
                        sql_query("insert into orvos_beosztas set orvosid=?,cegid=?",array($_GET["szerk"],$_SESSION["orvosbeosztascegfilter"]));
                    }
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
            if ($row = sql_fetch_array(sql_query("select binterval from orvos_beosztas where id=?", array($beosztasid)))) {
                $i = intval($_GET["interval"]);
                sql_query("update orvos_beosztas set binterval=? where id=?", array($i, $beosztasid));
            }
            die();
        }

        if (isset($_GET["showtipusvalaszto"])) {
            if (!$this->adminUtils->beosztasModJog()) die();
            $beosztasid = intval($_GET["showtipusvalaszto"]);
            $rowo = sql_fetch_array(sql_query("select * from orvos_beosztas where id=?", array($beosztasid)));

            $res=sql_query("select * from szurestipusok where true order by megnev");

            echo "<div style='width:850px;'>";
            while ($row=sql_fetch_array($res)) {
                echo "<label style='white-space: nowrap;'><input onchange='saveTipusList({$beosztasid})' type='checkbox' name='tipusvalaszto{$beosztasid}_{$row["id"]}' value='{$row["megnev"]}' ".(substr_count($rowo["tipusok"],"|{$row["id"]}|")>0?"checked":"")."/>{$row["megnev"]}&nbsp;&nbsp;</label> ";
            }

            echo "<div style=''><input type='button' onclick='showTipusValaszto({$beosztasid});' value='OK'></div>";
            echo "</div>";
            die();
        }
		
		if (isset($_GET["savebeosztastipusok"])) {
            $bid = intval($_GET["savebeosztastipusok"]);
            sql_query("update orvos_beosztas set tipusok=? where id=?", array($_GET["value"], $bid));
            die();
        }
		
		if (isset($_GET["showcegvalasztov2"])) {
            if (!$this->adminUtils->beosztasModJog()) die();
            $restrictid = intval($_GET["showcegvalasztov2"]);
            $rowo = sql_fetch_array(sql_query("SELECT * FROM foglalas_korlatozasok WHERE id=?", array($restrictid)));
			
			//Kilistázom az összes céget amihez van beoja:
			$res=sql_query("SELECT beo.*,c.megnev,c.id AS cegid FROM orvos_beosztas beo LEFT JOIN cegek c ON c.id=beo.cegid WHERE beo.orvosid=? GROUP BY beo.cegid ORDER BY c.megnev ASC",array(intval($rowo['orvosid'])));

            echo "<div style='width:750px;'>";
            while ($row=sql_fetch_array($res)) {
                echo "<label><input onchange='saveceglistav2({$restrictid})' type='checkbox' name='cegvalasztov2{$restrictid}_{$row["cegid"]}' value='{$row["megnev"]}' ".(substr_count($rowo["cegek"],"|{$row["cegid"]}|")>0?"checked":"")."/>{$row["megnev"]}&nbsp;&nbsp;</label>";
            }

            echo "<div style=''><input type='button' onclick='showcegvalasztov2({$restrictid});' value='OK'></div>";
            echo "</div>";
            die();
        }
		
		if (isset($_GET["savecegekv2"])) {
            sql_query("UPDATE foglalas_korlatozasok SET cegek=? WHERE id=?", array($_GET["value"], intval($_GET["savecegekv2"])));
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
			sql_query("INSERT INTO foglalas_korlatozasok SET orvosid=?,uid=?,datum=?",array(intval($_GET['szerk']),intval($_SESSION['adminuser']['id']),date("Y-m-d H:i:s")));
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
			
            if ($this->adminUtils->orvosModJog()) {
				
                if ($this->adminUtils->beosztasModJog()) {
                    while (isset($_POST["beosztasid{$sor}"])) {
                        $aktiv  = isset($_POST["aktiv{$sor}"])?1:0;
                        $sorban = isset($_POST["csaksorban{$sor}"])?1:0;
                        $sorban = isset($_POST["csakvsorban{$sor}"])?2:$sorban;
						$noreservation = isset($_POST["noreservation{$sor}"])?1:0;
                        $potig = $_POST["potig{$sor}"];

                        if (!preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9])/", $potig)) {
                            $potig = "";
                        }

                        $params = array($_POST["weekday{$sor}"], $_POST["beonap{$sor}"], $_POST["hetek{$sor}"], $_POST["helyszinid{$sor}"], $sorban, $aktiv, $_POST["tol{$sor}"], $_POST["ig{$sor}"], $potig, $noreservation, $_POST["beosztasid{$sor}"]);
                        sql_query("update orvos_beosztas set nap=?, beonap=?, hetek=?, helyszinid=?, csaksorban=?, aktiv=?, tol=?, ig=?, potig=?, noreservation=? where id=?", $params);
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


                if ($_POST["orvosmentesandcopy"]==1 && isset($_SESSION["orvosbeosztascegfilter"]) && $this->adminUtils->beosztasModJog()) {
                    $res=sql_query("select id from cegek");
                    while ($row=sql_fetch_array($res)) {
                        $cegId = $row["id"];
                        if (isset($_POST["copyceg{$cegId}"])) {
                            sql_query("delete from orvos_beosztas where orvosid=? and cegid=?",array($oid,$cegId));

                            $ress=sql_query("select * from orvos_beosztas where orvosid=? and cegid=?",array($oid,$_SESSION["orvosbeosztascegfilter"]));
                            while ($rows=sql_fetch_array($ress)) {
                                sql_query("insert into orvos_beosztas set 
                                    orvosid='{$rows["orvosid"]}',
                                    helyszinid='{$rows["helyszinid"]}',
                                    nap='{$rows["nap"]}',
                                    beonap='{$rows["beonap"]}',
                                    tol='{$rows["tol"]}',
                                    ig='{$rows["ig"]}',
                                    hetek='{$rows["hetek"]}',
                                    binterval='{$rows["binterval"]}',
                                    cegid='{$cegId}',
                                    csaksorban='{$rows["csaksorban"]}',
                                    tipusok='{$rows["tipusok"]}',
                                    aktiv='{$rows["aktiv"]}'");
                            }

                        }
                    }
                }

                logActivity("orvos",$oid,$_POST["nev"]." adatlap",print_r($_POST,true));
            }

            if($_SESSION["adminuser"]["jog_jogset"] == 1) {
                //Jelszó módosítás:
                if ($_POST["password"]!="") sql_query("UPDATE users SET password = MD5(?) WHERE orvosid = ?",array( $_POST["password"], $oid ));

                //Jogkörök módosítása:
                sql_query("UPDATE users 
				   SET    jog_cegset = ?, jog_helyszinset = ?, jog_orvosset = ?, jog_beosztasset = ?, 
						  jog_szabi  = ?, jog_szurestipusset = ?, jog_zarolista = ?, jog_zaroszerk = ?, 
						  jog_leletszerk = ?, jog_leletlatas = ?, jog_gdprhferes = ?, jog_kuponlista = ?, 
						  jog_kuponkeszites = ?, username = ?, nev = ? WHERE orvosid = {$oid}",
                    array($_POST['jog_cegset'], 		 $_POST['jog_helyszinset'],    $_POST['jog_orvosset'],   $_POST['jog_beosztasset'],
                          $_POST['jog_szabi'], 		 $_POST['jog_szurestipusset'], $_POST['jog_zarolista'],  $_POST['jog_zaroszerk'],
                          $_POST['jog_leletszerk'], 	 $_POST['jog_leletlatas'], 	   $_POST['jog_gdprhferes'], $_POST['jog_kuponlista'],
                          $_POST['jog_kuponkeszites'], $_POST['username'], 		   $_POST['nev'])
                );
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
                $res = sql_query("select b.* from orvos_beosztas b where orvosid=? and cegid=? and noreservation=0 AND (beonap>DATE(NOW()) OR nap<>10) order by b.nap desc", [$oid, Booking_Constants::DEFAULT_COMPANY_ID]);
                while ($beo = sql_fetch_array($res)) {
                    if ($beo["fobid"] == 0) {
                        if ($beo["aktiv"] == 1) {
                            $result = $foService->newConsultation($beo["id"]);
                            try {
                                error_reporting(0);
                                $xml = simplexml_load_string($result);
                                $message = (string)$xml->RETURN["RETMESSAGE"];

                                if (ctype_digit($message)) {
                                    sql_query("update orvos_beosztas set fobid=? where id=?", [$message, $beo["id"]]);
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
                            sql_query("update orvos_beosztas set fobid=0 where id=?", [$beo["id"]]);
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


                //$result = $foService->sendSzabadsag();
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
        if (!$this->adminUtils->helyszinModJog()) {
            return;
        }

        if (isset($_GET["szerk"])) {
            $oid = intval($_GET["szerk"]);
            $row = sql_fetch_array(sql_query("select * from orvosok where id=?",array($_GET["szerk"])));
            $_POST = $row;

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

            $resc=sql_query("SELECT TIME_TO_SEC(tol) AS tolsec,TIME_TO_SEC(ig) AS igsec,b.*,c.megnev as cegnev,h.cim as helyszin FROM orvos_beosztas b 
	        left join cegek c on c.id=b.cegid
	        left join helyszinek h on h.id=b.helyszinid
	        WHERE orvosid=? AND tol<>0 AND ig<>0",array($_GET["szerk"]));
            while ($rowc = sql_fetch_array($resc)) {
                $res = sql_query("SELECT b.*,c.megnev as cegnev,h.cim as helyszin FROM orvos_beosztas b
    	        left join cegek c on c.id=b.cegid
		        left join helyszinek h on h.id=b.helyszinid
	            WHERE orvosid=? AND helyszinid<>? AND nap=? AND tol<>0 AND ig<>0 AND ((TIME_TO_SEC(tol)>? AND TIME_TO_SEC(tol)<?) OR  (TIME_TO_SEC(ig)>? AND TIME_TO_SEC(ig)<?))",array($_GET["szerk"],$rowc["helyszinid"],$rowc["nap"],$rowc["tolsec"],$rowc["igsec"],$rowc["tolsec"],$rowc["igsec"]));
                if ($rowe = sql_fetch_array($res)) {
                    //$hibak.="<div>Orvos két helyszínen van egyszerre: ".$GLOBALS["hetnap"][$rowe["nap"]]." <b>1.</b> {$rowe["tol"]}-{$rowe["ig"]} {$rowe["cegnev"]} {$rowe["helyszin"]} <b>2.</b> {$rowc["tol"]}-{$rowc["ig"]} {$rowc["cegnev"]} {$rowc["helyszin"]}</div>";
                }
            }

            if ($hibak != "") {
                echo "<div style='margin-bottom:10px;background:#f88;padding:10px;display:inline-block;'>{$hibak}</div>";
            }

            echo "<form name='iform' id='iform' method='post' enctype='multipart/form-data'><input type='hidden' name='orvosform' value='1'/><input type='hidden' id='orvosid' name='orvosid' value='{$_POST["id"]}'/>";
            echo "<table style='font-size:12px;'>";

            echo "<tr><td width='100'>Név:</td><td><input class='inputbox' style='width:400px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
            echo "<tr><td>Pecsétszám:</td><td><input class='inputbox' style='width:200px;' type='text' name='pecsetszam' value='{$_POST["pecsetszam"]}'> <span id='fosyncbutton'>".$this->foglaljOrvostSyncButton($oid)."</span></td></tr>";
            echo "<tr><td></td><td><div id='fodatadiv'></div></td></tr>";
            echo "<tr><td>Orvos E-mail címe:</td><td><input class='inputbox' style='width:600px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
            echo "<tr><td valign='top' style='padding-top:5px;'>Orvos telefonszáma:</td><td><input class='inputbox' style='width:200px;' type='text' name='tel' value='{$_POST["tel"]}'> <input type='checkbox' value=1 name='telpublic'".($_POST["telpublic"]==1?" checked":"")."> megjelenjen a foglalási oldalon <input type='checkbox' value=1 name='onlytel'".($_POST["onlytel"]==1?" checked":"")."> csak telefonra fogad bejelentkezést<div style='padding-top:5px;'>Fontos: A telefonszám formátuma 06201234567.</div></td></tr>";

            echo "<tr><td>SMS értesítés:</td><td><div id='smsalertsettings'>".$this->smsAlertSettings($oid)."</div></td></tr>";

            echo "<tr><td>&nbsp;</td><td><input type='checkbox' value=1 name='visszaigazol'".($_POST["visszaigazol"]==1?" checked":"")."> visszaigazolás szükséges, erre a címre: <input class='inputbox' style='width:200px;' type='text' name='visszaigazolemail' value='{$_POST["visszaigazolemail"]}'></td></tr>";
            echo "<tr><td>HMED értesítés email:</td><td><input class='inputbox' style='width:600px;' type='text' name='hmedemail' value='{$_POST["hmedemail"]}'></td></tr>";


            $w=$wc="";
            if ($_SESSION["adminuser"]["jogosultsag"]<2) {
                $w="and b.cegid in (".$this->adminUtils->getCegList($_SESSION["adminuser"]["cegjog"]).")";
                $wc="and id in (".$this->adminUtils->getCegList($_SESSION["adminuser"]["cegjog"]).")";
            }


            echo "<tr><td colspan='2'>";
            echo "<div class='tdsepdiv'>Beosztás ";

            $cegbeo[]=0;
            $resstat=sql_query("SELECT cegid,GROUP_CONCAT(DISTINCT concat(nap,beonap)) AS napok FROM orvos_beosztas b WHERE orvosid=? {$w} GROUP BY cegid",array($_GET["szerk"]));
            while ($rowstat=sql_fetch_array($resstat)) {
                if (isset($_GET["sp"]) && $_GET["sp"]!=1) {
                    $_GET["sp"]=1;
                    $_SESSION["orvosbeosztascegfilter"]=$rowstat["cegid"];
                }
                $beostat[$rowstat["cegid"]]=$rowstat;
                $cegbeo[]=$rowstat["cegid"];
            }


            echo "<select onchange='document.iform.submit();' name='orvosbeosztascegfilter' style='width:300px;'>";
            $resh=sql_query("select * from cegek where true {$wc} order by id not in (".implode(",",$cegbeo)."),megnev");

            if (sql_num_rows($resh)>1) {
                echo "<option value='0'>Válassz!".(count($cegbeo)>1?" (beosztva ".(count($cegbeo)-1)." céghez)":"")."</option>";
            }

            while ($rowh=sql_fetch_array($resh)) {
                echo "<option style='".(isset($beostat[$rowh["id"]])?"font-weight:bold;":"")."' value='{$rowh["id"]}'".($_SESSION["orvosbeosztascegfilter"]==$rowh["id"]?" selected":"").">{$rowh["megnev"]} ".(isset($beostat[$rowh["id"]])?"(".count(explode(",",$beostat[$rowh["id"]]["napok"]))." nap)":"")."</option>";
            }

            echo "</select> ";


            echo "<a class='ujbutton' style='padding:3px 10px;font-weight:normal;' href='#' onclick='$(\"#bcopierdiv\").slideToggle();return false;'>Beosztás másolása</a>";

            echo "<div id='bcopierdiv' style='font-size:12px;font-weight:normal;width:800px;padding:10px;display:none;'>";
            $resh=sql_query("select * from cegek where id<>? {$wc} order by id not in (".implode(",",$cegbeo)."),megnev",array($_SESSION["orvosbeosztascegfilter"]));
            while ($rowh=sql_fetch_array($resh)) {
                echo "<div style='display:inline-block;'><input class='copycegch' name='copyceg{$rowh["id"]}' type='checkbox' ".(in_array($rowh["id"],$cegbeo)?" checked":"")." value='1' /> {$rowh["megnev"]}</div/> ";
            }
            echo "<div style='padding-top:10px;'>";
            echo "<input type='hidden' id='orvosmentesandcopy' name='orvosmentesandcopy' value='0' />";
            echo "<a class='ujbutton' style='padding:3px 10px;font-weight:normal;' href='#' onclick='if (!confirm(\"Biztos másolod ezt a beosztást a kijelölt cégekhez?\")) {return false;} $(\"#orvosmentesandcopy\").val(1);document.iform.submit();'>Beosztás másolása a kijelölt cégekhez</a> <a class='ujbutton' style='padding:3px 10px;font-weight:normal;' href='#' onclick='$(\"#bcopierdiv\").slideToggle();'>Mégse</a>";

            echo "&nbsp;&nbsp;<a href='#' onclick='selectAllCopyCompany();return false;'>összes cég kijelölése</a> | <a href='#' onclick='deselectAllCopyCompany();return false;'>kijelölések törlése</a>";
            echo "</div>";


            echo "</div>";


            echo "</div>";
            echo "</td></tr>";

            if (!$this->adminUtils->beosztasModJog()) {
                echo "<tr><td colspan='2' style=''><div class='nojog'>A beosztás módosításához nincs jogosultsága</div></td></tr>";
            }


            $resb = sql_query("select * from orvos_beosztas b where orvosid=? and (cegid=?) and (nap<>10 or beonap>date_sub(now(), interval 2 month)) {$w} order by cegid, nap<>0, nap, beonap, tol",array($_GET["szerk"],$_SESSION["orvosbeosztascegfilter"]));

            $sor = 1;
            $hetBackgrounds=array("","#ffffbb","#bbffff");

            while ($rowb=sql_fetch_array($resb)) {
                echo "<tr><td colspan='2'>";

                echo "<input type='hidden' name='beosztasid{$sor}' value='{$rowb["id"]}'/>";

                echo "<input title='aktív?' type='checkbox' name='aktiv{$sor}' value='1' ".($rowb["aktiv"]==1?" checked":"")."/> ";

                echo "<select name='weekday{$sor}' onchange=\"if (this.value!=10) { $('#hetek{$sor}').show(); $('#beonap{$sor}').hide(); } else { $('#hetek{$sor}').hide(); $('#beonap{$sor}').show(); }\">";
                echo "<option value='0'>Válassz napot!</option>";
                for ($n=1;$n<=7;$n++) {
                    echo "<option value='{$n}'".($rowb["nap"]==$n?" selected":"").">{$GLOBALS["hetnap"][$n]}</option>";
                }
                echo "<option value='10'".($rowb["nap"]==10?" selected":"").">Egy dátum</option>";
                echo "</select> ";

                echo "<select id='hetek{$sor}' name='hetek{$sor}' style='width:110px;background:{$hetBackgrounds[$rowb["hetek"]]};".($rowb["nap"]==10?"display:none;":"")."'>";
                echo "<option value='0'".($rowb["hetek"]==0?" selected":"").">Minden hét</option>";
                echo "<option value='1'".($rowb["hetek"]==1?" selected":"").">Páratlan hetek</option>";
                echo "<option value='2'".($rowb["hetek"]==2?" selected":"").">Páros hetek</option>";
                echo "</select> ";

                echo "<input id='beonap{$sor}' name='beonap{$sor}' type='text' value='{$rowb["beonap"]}' style='width:102px;".($rowb["nap"]==10?"":"display:none;")."' placeholder='éééé-hh-nn' /> ";

                if (!isset($_SESSION["orvos_helyszinid"]) && $rowb["helyszinid"]!=0) {
                    $_SESSION["orvos_helyszinid"] = $rowb["helyszinid"];
                }
                if (!isset($_SESSION["orvos_cegid"]) && $rowb["cegid"]!=0) {
                    $_SESSION["orvos_cegid"] = $rowb["cegid"];
                }

                echo "<select id='helyszinid{$sor}' name='helyszinid{$sor}' style='width:200px;'>";

                if ($rowb["helyszinid"]==0 && isset($_SESSION["orvos_helyszinid"])) {
                    $rowb["helyszinid"] = $_SESSION["orvos_helyszinid"];
                }
                if ($rowb["cegid"]==0 && isset($_SESSION["orvos_cegid"])) {
                    $rowb["cegid"] = $_SESSION["orvos_cegid"];
                }


                $resh=sql_query("select * from helyszinek where true order by cim");
                echo "<option value='0'>Válassz helyszínt!</option>";
                while ($rowh=sql_fetch_array($resh)) {
                    echo "<option value='{$rowh["id"]}'".($rowb["helyszinid"]==$rowh["id"]?" selected":"").">{$rowh["cim"]}</option>";
                }
                echo "</select> ";

                echo "<select name='tol{$sor}'>";
                echo "<option value='0'>Kezdés?</option>";
                for ($n=0;$n<=1125;$n+=5) {
                    $t=date("H:i",mktime(5,0+$n,0,1,1,2015));
                    echo "<option value='{$t}'".($rowb["tol"]==$t?" selected":"").">{$t}</option>";
                }
                echo "</select> ";

                echo "<select name='ig{$sor}'>";
                echo "<option value='0'>Vége?</option>";
                for ($n=0;$n<=1065;$n+=5) {
                    $t=date("H:i",mktime(6,0+$n,0,1,1,2015));
                    echo "<option value='{$t}'".($rowb["ig"]==$t?" selected":"").">{$t}</option>";
                }
                echo "</select> ";

                echo "<input placeholder='pótidőpontok' title='Pótidőpontok eddig adhatók. Hagyd üresen ha nem akarsz pótidőpontokat.'  type='text' name='potig{$sor}' style='width:37px;' value='{$rowb["potig"]}' /> ";

                echo "<input type='hidden' name='tipusidk{$sor}' id='tipusidk{$sor}' value='{$rowb["tipusok"]}' />";

                $num=0;
                unset($idk);
                $idk[]=0;
                $titl="nincs tipus hozzárendelve";

                $ik=explode("|",$rowb["tipusok"]);
                for ($i=0;$i<count($ik);$i++) {
                    if ($ik[$i]!="") {
                        $num++;
                        $idk[]=$ik[$i];
                    }
                }

                if (count($idk)>1) {
                    $rowtt=sql_fetch_array(sql_query("SELECT GROUP_CONCAT(megnev SEPARATOR ', ') AS megnevek FROM szurestipusok WHERE id IN (".implode(",",$idk).")"));
                    $titl=$rowtt["megnevek"];
                }

                echo "<select title='egy kezelés időtartama' id='intervalchooser{$rowb["id"]}' onclick='changeInterval({$rowb["id"]}, this.value);'>";
                foreach ($this->adminUtils->settings->validIntervals as $interval) {
                    echo "<option value='{$interval}'".($rowb["binterval"]==$interval?" selected":"").">{$interval} perc</option>";
                }
                echo "</select> ";

                echo "<span id='tipusstatus{$rowb["id"]}'><a href='#' class='tlink' title='{$titl}' onclick='showTipusValaszto({$rowb["id"]});return false;'>{$num} tipus</a></span> ";
				
				//Ide rakjuk ki az orvos kérdez/felelek részét!
				//echo "<span><a href='#' class='tlink' title='Itt lehet megadni a vizsgálathoz tartozó orvosi kérdéseket.' onClick='setQuestsAnswers();return false;'>Q&A</a></span>";
				
                echo "<span title='Csak sorban foglalható időpontok'><input onclick='cssClick(1,{$sor});' type='checkbox' value='1' id='csaksorban{$sor}' name='csaksorban{$sor}'".($rowb["csaksorban"]==1?" checked":"").">&darr;</span> ";
                echo "<span title='Csak fordított sorrendben foglalható időpontok'><input onclick='cssClick(2,{$sor});' type='checkbox' value='2' id='csakvsorban{$sor}' name='csakvsorban{$sor}'".($rowb["csaksorban"]==2?" checked":"").">&uarr;</span> ";
				
                echo "<span title='Nincs időpontfoglalás'><input value='1' type='checkbox' id='noreservation{$sor}' name='noreservation{$sor}'".($rowb["noreservation"]==1?" checked":"").">Nincs időpontfoglalás&nbsp;</span> ";
				
                echo "<a href='index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}&delbeosztas={$rowb["id"]}' onclick='return confirm(\"Biztos törlöd ezt a beosztás sort?\")'><img align='baseline' src='/images/trash.png' title='Sor törlése'/></a>";

                if ($rowb["fobid"] != 0) {
                    echo " <span style='border:1px solid #080;color:#080;cursor:pointer;' title='id: {$rowb["fobid"]}'>FO</span>";
                }

                echo "<div id='tipusvalaszto{$rowb["id"]}'></div>";

                echo "</td></tr>";
                $sor++;
            }

            echo "<tr><td colspan=2 valign=top>";
            if ($_SESSION["orvosbeosztascegfilter"]==0) {
                echo "<div style='margin:10px 0px;'>A beosztás szerkesztéséhez először válassz céget!</div>";
            } else {
                if (sql_num_rows($resb)==0) echo "<div style='margin:10px 0px;'>Ennek az orvosnak nincs beosztása a kiválasztott céghez!</div>";
                echo "<input type='submit' name='addbeosztas' value='+ Beosztás hozzáadása'>";
            }
            echo "</td></tr>";

            echo "<tr><td colspan='2'><div class='tdsepdiv' style='margin-top:10px;'>Szabadság</div></td></tr>";
            if (!$this->adminUtils->szabadsagJog()) {
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
				$resa = sql_query("SELECT beo.*,h.cim FROM orvos_beosztas beo LEFT JOIN helyszinek h ON h.id=beo.helyszinid WHERE beo.orvosid=? GROUP BY beo.helyszinid",array($_GET['szerk']));
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
				$row++;
			}
			
			echo "<tr><td colspan='2' valign='top'><input type='submit' name='restricttobooking' value='+ Korlátozás hozzáadása'></td></tr>";

            $docAgent = new DocAgent();
            echo "<tr><td colspan='2'><div class='tdsepdiv'>Fotó</div></td></tr>";
            echo "<tr><td colspan='2' valign='top'><div id='asseteditor'>".$docAgent->showAssetEditor(DocAgent::ASSET_DOCTOR_PHOTO, $oid)."</div>";
            echo "</td></tr>";

            echo "<tr><td colspan='2'><div class='tdsepdiv'>Orvosi kérdések szűrésípusokhoz</div></td></tr>";
			//Itt akkor az összes vizsgálatot ami bevan állítva az emberhez meg kell hogy jeleníteni O.o....
			$resq=sql_fetch_array(sql_query("SELECT GROUP_CONCAT(tipusok) AS tipusok FROM orvos_beosztas WHERE orvosid=?",array($_GET['szerk'])));
			
			$tipusok=array_values(array_unique(explode("||",substr(str_replace(",","",$resq['tipusok']),1,-1))));
			foreach($tipusok as $tipus){
				$szurestipus=sql_fetch_array(sql_query("SELECT * FROM szurestipusok WHERE id=?",array($tipus)));
				echo "<tr><td colspan='2'>";
				echo "<div>";
				echo "<input type='textbox' disabled='true' value='{$szurestipus['megnev']}'>&nbsp;&nbsp;";
				echo "<span style='cursor:pointer' title='Szűrésípushoz tartozó kérdések szerkesztése.'><i onClick='setQndA({$_GET['szerk']},{$tipus})' class='fas fa-pen'></i></span>";
				echo "</div>";
				echo "</td></tr>";
			}
			
			

            if( $_SESSION['adminuser']['jog_orvosset'] == 1 )
            {
                $type = explode( ",", $_POST['szurestipusok'] );

                echo "<tr><td colspan = '2'><div class='tdsepdiv' style='margin:10px 0px 0px 0px'>Vizsgálat típusok kiválasztása</div></td></tr>";
                echo "<tr><td><table>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(1, $type)?"checked":"")." name = 'szak_belgyogy' value = '1' /></td>";
                echo "	  <td>Belgyógyász</td></tr>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(2, $type)?"checked":"")." name = 'szak_rtg' value = '2' /></td>";
                echo "	  <td>Röntgen</td></tr>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(3, $type)?"checked":"")." name = 'szak_uh' value = '3' /></td>";
                echo "	  <td>Ultrahang</td></tr>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(4, $type)?"checked":"")." name = 'szak_borgyogy' value = '4' /></td>";
                echo " 	  <td>Bőrgyógyász</td></tr>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(5, $type)?"checked":"")." name = 'szak_szemesz' value = '5' /></td>";
                echo "	  <td>Szemész</td></tr>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(6, $type)?"checked":"")." name = 'szak_kardio' value = '6' /></td>";
                echo "	  <td>Kardiológia</td></tr>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(7, $type)?"checked":"")." name = 'szak_torna' value = '7' /></td>";
                echo "	  <td>Gyógytornász</td></tr>";

                echo "<tr><td><input type = 'checkbox' ".(in_array(8, $type)?"checked":"")." name = 'szak_labor' value = '8' /></td>";
                echo "	  <td>Labor</td></tr>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(9, $type)?"checked":"")." name = 'szak_urologia' value = '9' /></td>";
                echo "	  <td>Urológia</td></tr>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(10, $type)?"checked":"")." name = 'szak_nogyogy' value = '10' /></td>";
                echo "	  <td>Nőgyógyászat</td></tr>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(11, $type)?"checked":"")." name = 'szak_tudogyogy' value = '11' /></td>";
                echo "	  <td>Tüdőgyógyászat</td></tr>";
                echo "<tr><td><input type = 'checkbox' ".(in_array(12, $type)?"checked":"")." name = 'szak_ortopedia' value = '12' /></td>";
                echo "	  <td>Ortopédia</td></tr>";

                echo "</td></tr></table>";
            }


            //Orvosi jogkörök:
            $request = sql_query( "SELECT * FROM users WHERE orvosid = ?", array( $_GET['szerk'] ));
            if ( sql_num_rows($request) > 0 && $_SESSION['adminuser']['jog_jogset'] == 1) {
                $adminAutorithy = "";
                $result = sql_fetch_array( $request );
                $nowrap = "style = 'white-space:nowrap'";
                $adminAutorithy.= "<tr><td colspan = '2'><div class='tdsepdiv' style='margin:10px 0px 0px 0px'>Jogkörök hozzárendelése</div></td></tr>";
                $adminAutorithy.= "<tr><td><table>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_cegset' ".( $result["jog_cegset"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Cégek kezelése</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_helyszinset' ".( $result["jog_helyszinset"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Helyszínek kezelése</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_orvosset' ".( $result["jog_orvosset"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Orvosok kezelése</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_beosztasset' ".( $result["jog_beosztasset"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Orvos beosztások kezelése</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_szabi' ".( $result["jog_szabi"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Szabadságok beállítása</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_szurestipusset' ".( $result["jog_szurestipusset"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Szűréstipusok kezelése</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_zarolista' ".( $result["jog_zarolista"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Zárólista látása</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_zaroszerk' ".( $result["jog_zaroszerk"] == 1 ? "checked" : "" )."  value = '1'/ ></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Záró leletek szerkesztése</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_leletlatas' ".( $result["jog_leletlatas"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Leletek látása</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_leletszerk' ".( $result["jog_leletszerk"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Leletek szerkesztése</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_gdprhferes' ".( $result["jog_gdprhferes"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >GDPR hozzáférés</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_kuponlista' ".( $result["jog_kuponlista"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Kuponkód lista</td></tr>";
                $adminAutorithy.= "<tr><td><input type = 'checkbox' name='jog_kuponkeszites' ".( $result["jog_kuponkeszites"] == 1 ? "checked" : "" )." value = '1' /></td>";
                $adminAutorithy.= "	   <td {$nowrap} >Kuponkód hozzáadás/szerkesztés</td></tr>";
                $adminAutorithy.= "<tr><td {$nowrap} colspan = '2'>Felh. név: <input type = 'text' value = '{$result['username']}' name = 'username' /></td></tr>";
                $adminAutorithy.= "<tr><td {$nowrap} colspan = '2'>Új jelszó: <input type = 'text' name = 'password' style = 'margin-left:2px'/></td></tr>";
                $adminAutorithy.= "</table></td></tr>";
                echo $adminAutorithy;
            }

            echo "<tr><td colspan='2' valign='top'><input type='checkbox' value=1 name='aktiv'".($_POST["aktiv"]==1?" checked":"")."> Aktív&nbsp;&nbsp;&nbsp;&nbsp;";
            echo "<input type='radio' name='gender' value='2' ".($_POST["gender"]==2 ? "checked":"")."/> Nő <input type='radio' name='gender' value='1' ".($_POST["gender"]==1 ? "checked":"")."/> Férfi";
            echo "</td></tr>";

            echo "</table>";


            echo "<div id='errorlistdiv' style='padding:10px;background:#f00;color:#fff;font-weight:bold;display:none;'></div>";
            //onclick=\"return orvosDataVerify();\";
            if ($this->adminUtils->orvosModJog()) {
                if (sql_num_rows($request) == 0) {
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


        $w="";
        if ($_SESSION["adminuser"]["jogosultsag"]<2) {
            $w="and (b.cegid in (".$this->adminUtils->getCegList($_SESSION["adminuser"]["cegjog"]).") or b.cegid is null)";
        }

        if ($_SESSION["cegfilter"]>0) $w = "and (b.cegid='".addslashes($_SESSION["cegfilter"])."' or b.cegid is null)";
        if ($_SESSION["cegfilter"]==-1) $w = "and (b.cegid='0' or b.cegid is null)";

        if ($_SESSION["adminuser"]["jogosultsag"] >= 2) {
            echo "<div style='margin-bottom:10px;'>";
            echo "<select name='cegselect' onchange='setCegFilter(this.value,\"doctors\");'>";
            echo "<option value='0'>Szűrés cégre</option>";
            echo "<option value='-1'".($_SESSION["cegfilter"]==-1?" selected":"").">Összes céget fogadók</option>";

            $res = sql_query("SELECT * FROM cegek order by megnev");
            while ($rowt = sql_fetch_array($res)) {
                echo "<option value='{$rowt["id"]}'".($_SESSION["cegfilter"]==$rowt["id"]?" selected":"").">{$rowt["megnev"]}</option>";
            }

            echo "</select>";
            echo "</div>";
        }

        $orvosok = sql_query("SELECT GROUP_CONCAT(DISTINCT b.tipusok SEPARATOR '') AS tipusok,o.*,GROUP_CONCAT(DISTINCT h.cim separator '<br/>') AS cimek,GROUP_CONCAT(DISTINCT c.megnev separator ', ') AS cegek,GROUP_CONCAT(DISTINCT IF(b.cegid=0,'nulla','') SEPARATOR ',') AS cegidk FROM orvosok o
        LEFT JOIN orvos_beosztas b ON b.`orvosid`=o.`id`
        LEFT JOIN helyszinek h ON h.`id`=b.`helyszinid`
        LEFT JOIN cegek c ON c.`id`=b.`cegid`
        where o.pecsetszam<>'temp' {$w}
        GROUP BY o.id
        ORDER BY nev<>'Új orvos', nev")->fetchAll(PDO::FETCH_ASSOC);

        $kiemeltOrvosok = sql_query("SELECT GROUP_CONCAT(DISTINCT b.tipusok SEPARATOR '') AS tipusok,o.*,GROUP_CONCAT(DISTINCT h.cim separator '<br/>') AS cimek,GROUP_CONCAT(DISTINCT c.megnev separator ', ') AS cegek,GROUP_CONCAT(DISTINCT IF(b.cegid=0,'nulla','') SEPARATOR ',') AS cegidk FROM orvosok o
        LEFT JOIN orvos_beosztas b ON b.`orvosid`=o.`id`
        LEFT JOIN helyszinek h ON h.`id`=b.`helyszinid`
        LEFT JOIN cegek c ON c.`id`=b.`cegid`
        LEFT JOIN dokumentumok d on d.dataid=o.id and d.assetid='orvosphoto'
        where o.pecsetszam<>'temp' {$w} and (d.id is not null or o.foid<>0)
        GROUP BY o.id
        ORDER BY nev")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($kiemeltOrvosok)) {
            echo "<h2>Kiemelt orvosok</h2>";
            echo $this->_orvosLista( $kiemeltOrvosok);
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
            unset($tipusok);
            $ta = explode("|",$row["tipusok"]);
            for ($i=0;$i<count($ta);$i++) {
                if (trim($ta[$i])!="") {
                    if (isset($tipusnevek[$ta[$i]])) {
                        $tipusok[] = $tipusnevek[$ta[$i]];
                    }
                }
            }

            if ($row["cegidk"] == "nulla") {
                $cegek = "";
            } else {
                $cegek = "<span title='{$row["cegek"]}' style='border-bottom: 1px dashed;'>".(substr_count($row["cegek"], ", ")+1)." cég</span>";
            }
            $tc = "tcella";
            if (!isset($first)) {
                $html.= "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (trim($row["nev"])=="") $row["nev"] = "nincs neve";
            $html.= "<tr>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>";
            $html.= "<a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}&sp'>{$row["nev"]}</a>";

            if ($row["foid"] != 0) {
                $html.= "&nbsp;&nbsp;<span class='fo_badge' title='fo id: {$row["foid"]}'>FOGLALJORVOST</span>";
            }

            if (isset($tipusok)) $html.= "<div>".implode("<br/>",array_unique($tipusok))."</div>";
            $html.= "</div></td>";
            //$html.= "<td nowrap valign=top><div class='{$tc}' style='min-width:300px;'>{$row["cim"]}&nbsp;&nbsp;</div></td>";



            $image = "";
            $assets = $docAgent->getAssetsByType(DocAgent::ASSET_DOCTOR_PHOTO, $row["id"]);
            if (!empty($assets)) {
                $image = "<img style='width:50px;height:50px;object-fit: cover;' src='{$assets[0]["url"]}' title='' />";
            }

            $html.= "<td valign='top'><div class='{$tc}'>{$image}</div></td>";

            $html.= "<td valign='top'><div class='{$tc}' style='min-width:300px;'>";
            if ($row["cimek"]!="") {
                $html.= "{$row["cimek"]}";
            } else {
                $html.= "<span style='color:#f00;'>nincs még beosztása</span>";
            }
            $html.= "</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}' style='min-width:200px;'>{$cegek}</div></td>";
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

