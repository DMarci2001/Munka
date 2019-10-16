<?php

class AdminBookingPage extends AdminCorePage
{

    private $bookingService;

    private $setDay;

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;
        $this->bookingService = new BookingService();

        if (!isset($_SESSION["helyszin"])) $_SESSION["helyszin"] = 0;
        if (!isset($_SESSION["helyszinceg"])) $_SESSION["helyszinceg"] = 0;
        if (!isset($_SESSION["naptarszurestipus"])) $_SESSION["naptarszurestipus"] = 0;
        if (!isset($_SESSION["ecegfilter"])) $_SESSION["ecegfilter"] = 0;
        if (!isset($_SESSION["setday"])) $_SESSION["setday"] = date("Y-m-d");
        if (isset($_GET["setday"])) $_SESSION["setday"] = $_GET["setday"];
        if (isset($_GET["ecegfilter"])) $_SESSION["ecegfilter"] = $_GET["ecegfilter"];

        $this->setDay = $_SESSION["setday"];

        if (isset($_GET["sethelyszin2"])) {
            $s = explode("-",$_GET["sethelyszin2"]);
            $_SESSION["helyszin"]    = $s[0];
            $_SESSION["helyszinceg"] = $s[1];
            header("location:index.php?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["szabira"])) {
            sql_query("insert into szabadsag set oid=?,datumtol=?,datumig=?",array($_GET["orvosid"],$_GET["szabira"],$_GET["szabira"]));

            $rowo = sql_fetch_array(sql_query("select * from orvosok where id=?",array($_GET["orvosid"])));
            logActivity("orvos",$rowo["id"],"{$rowo["nev"]} szabira küldés link {$_GET["szabira"]}","");

            header("location:{$_SERVER['PHP_SELF']}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["showelojegyzestable"])) {
            if (isset($_GET["day"])) $_SESSION["setday"] = $_GET["day"];
            echo $this->showElojegyzesTable($_SESSION["setday"]);
            die();
        }

        if (isset($_GET["addidopont"])) {
            $this->bookingService->addIdoPont();

            if (isset($_SESSION["setday"])) {
                echo $this->showElojegyzesTable($_SESSION["setday"]);
            }
            die();
        }

        if (isset($_GET["removeidopont"])) {
            $this->bookingService->removeIdopont($_GET["removeidopont"], $_GET["p"]);
            echo $this->showElojegyzesTable($_SESSION["setday"]);
            die();
        }

        if (isset($_GET["moveidopont"])) {
            if (isset($_SESSION["helyszin"])) {
                $fid            = intval($_GET["fid"]);
                $newfid         = $fid;
                $szuresTipusId = intval($_GET["szt"]);

                $rowf = sql_fetch_array(sql_query("select * from foglalasok where id=?", array($fid)));

                if (isset($_GET["cpy"]) && $_GET["cpy"]==1) {
                    $copy = 1;

                    sql_query("insert into foglalasok set
                        regdatum=now(),
                        cegid=?,
                        paciensid=?,
                        nev=?,
                        email=?,
                        telefon=?,
                        szuldatum=?,
                        szulhely=?,
                        anyjaneve=?,
                        neme=?,
                        taj=?,
                        irsz=?,
                        varos=?,
                        utca=?,
                        munkaltato=?,
                        munkakor=?,
                        rkod=?,
                        megj=?,
                        alkalmassag=?,
                        alkalmassagido=?,
                        alkalmassagikhet=?,
                        tudoszuroervenyesseg=?,
                        tudoszuro=?,
                        smssent=1
        			",array(
                        $rowf["cegid"],
                        $rowf["paciensid"],
                        $rowf["nev"],
                        $rowf["email"],
                        $rowf["telefon"],
                        $rowf["szuldatum"],
                        $rowf["szulhely"],
                        $rowf["anyjaneve"],
                        $rowf["neme"],
                        $rowf["taj"],
                        $rowf["irsz"],
                        $rowf["varos"],
                        $rowf["utca"],
                        $rowf["munkaltato"],
                        $rowf["munkakor"],
                        rand(11000,98000),
                        $rowf["megj"],
                        $rowf["alkalmassag"],
                        $rowf["alkalmassagido"],
                        $rowf["alkalmassagikhet"],
                        $rowf["tudoszuroervenyesseg"],
                        $rowf["tudoszuro"]
                    ));

                    $newfid = sql_insert_id();
                }

                logActivity("foglalas",$newfid,"{$rowf["nev"]} foglalás ".(isset($copy)?"másolása":"mozgatása")." {$rowf["datum"]} -> {$_GET["moveidopont"]}","");

                sql_query("update foglalasok set aktiv=1,foglalta=?,helyszinid=?,szurestipusid=?,datum=?,rinterval=?,orvosassigned=0 where id=?",array($_SESSION["adminuser"]["nev"],$_SESSION["helyszin"],$szuresTipusId,$_GET["moveidopont"],intval($_GET["rinterval"]),$newfid));
                $this->bookingService->updateFoglalasData($newfid);

                $oid = $this->bookingService->selectFreeOrvosForIdopont($newfid);
                sql_query("update foglalasok set orvosassigned=? where id=? and orvosassigned=0", array($oid, $newfid));
            }

            //if ($_GET["page"]=="bnaptar") {
            //    echo showAdminNaptarIdopont($_GET["moveidopont"]);
            //    die();
            //}

            echo $this->showElojegyzesTable($_SESSION["setday"]);
            die();
        }


    }

    public function showPage()
    {
        echo "<div>";
        echo "<select name='helyszin' onchange='setHelyszin2(this.value);'>";
        echo "<option value='0'>Válassz helyszínt!</option>";
        $res = sql_query("SELECT h.* FROM helyszinek h WHERE true ORDER BY trim(h.cim)");
        while ($placeData = sql_fetch_array($res)) {
            if ($_SESSION["adminuser"]["jogosultsag"] < 2) {
                if (isset($go)) unset($go);
                $cegidk = explode("|",$_SESSION["adminuser"]["cegjog"]);
                foreach ($cegidk as &$val) {
                    if (substr_count($placeData["ceglink"],"|{$val}|") && $val!="") {
                        $go = 1;
                        break;
                    }
                }
                if (!isset($go)) continue;
            }
            echo "<option value='{$placeData["id"]}-0'".("{$_SESSION["helyszin"]}-0"=="{$placeData["id"]}-0"?" selected":"").">{$placeData["cim"]}</option>";
        }
        echo "</select>";
        echo "</div>";

        if (!isset($_SESSION["helyszin"]) || $_SESSION["helyszin"] == 0) {
            return;
        }

        echo "<div id='elojegyzestable'>".$this->showElojegyzesTable($this->setDay)."</div>";
        echo "<div id='idoponteditor' style='position:fixed;bottom:0px;right:0px;background:#e0e0e0;display:none;'></div>";
    }


    public function showElojegyzesTable($setDay) {
        $settings = new Booking_Settings();

        $htmlout = "";
        $helyszin = intval($_SESSION["helyszin"]);
        $helyszinceg = intval($_SESSION["helyszinceg"]);

        $nap = date("Y-m-d",strtotime($setDay));
        $wd  = date("N",strtotime($setDay)); 		//day of week
        $wn  = date("W",strtotime($setDay)); 		//number of week

        $tipusok[] = 0;
        $wCeg = $this->adminUtils->cegSQLFilter("b.cegid");
        $res=sql_query("SELECT tipusok FROM orvos_beosztas b WHERE b.helyszinid='".intval($_SESSION["helyszin"])."' {$wCeg} and b.tol<>0 and b.ig<>0");
        while ($row=sql_fetch_array($res)) {
            $ta=explode("|",$row["tipusok"]);
            for ($i=0; $i<count($ta); $i++) {
                if (trim($ta[$i]) != "" && !in_array($ta[$i],$tipusok)) {
                    $tipusok[] = $ta[$i];
                }
            }
        }

        $htmlout.="<div style='margin-top:30px;'>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'>";
        $htmlout.= "<select name='napselect' style='margin-right:10px;font-size:22px;width:300px;' onchange='window.location.href=\"index.php?page={$_GET["page"]}&setday=\"+this.value;'>";
        for ($i=-60; $i<150; $i++) {
            $day = date("Y-m-d",strtotime("now +{$i} day"));
            $htmlout.= "<option value='{$day}'".($day == $setDay?" selected":"").">".$this->adminUtils->magyarDatum($day)."</option>";
        }
        $htmlout.= "</select>";
        $htmlout.="</div>";

        $htmlout.="<div style='display:table-cell;vertical-align:middle;'><a onclick='setListDay(\"".date("Y-m-d",strtotime("{$setDay} -1 day"))."\");return false;' href='#'><img height='20' src='images/prev.png' title='Előző nap'/></a>&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'><input type='button' onclick='setListDay(\"".date("Y-m-d")."\");' value='MA' title='Ugrás a mai napra' />&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'><a onclick='setListDay(\"".date("Y-m-d",strtotime("{$setDay} +1 day"))."\");return false;' href='#'><img height='20' src='images/next.png' title='Következő nap'/></a></div>";

        //cég kiemelés
        if (true) {
            $htmlout.="<div style='display:table-cell;vertical-align:middle;padding-left:20px;'>";

            $rescf=sql_query("SELECT c.* FROM orvos_beosztas b 
		    LEFT JOIN cegek c ON c.`id`=b.`cegid`
		    WHERE helyszinid=? GROUP BY cegid ORDER BY c.`megnev`",array($_SESSION["helyszin"]));

            $htmlout.="<select name='ecegfilter' onchange=\"window.location.href='index.php?page={$_GET["page"]}&ecegfilter='+this.value;\">";
            $htmlout.="<option value='0'>Szűrés cégre</option>";
            while ($rowcf=sql_fetch_array($rescf)) {
                $htmlout.="<option value='{$rowcf["id"]}'".($_SESSION["ecegfilter"]==$rowcf["id"]?" selected":"").">{$rowcf["megnev"]}</option>";
            }

            $htmlout.="</select>";
            $htmlout.="</div>";

        }

        $htmlout.="</div>";

        if (in_array($nap, $settings->getMunkaszunetiNapok())) {
            $htmlout.="<div style='margin-top:10px;padding:5px 10px;background: #f00;color:#fff;font-size:18px;display:inline-block;'>Munkaszüneti nap!</div>";
        }


        $htmlout.= "<table cellpadding='0' cellspacing='0' border='0'>";

        $rendelesek=0;

        sql_query("SET SESSION group_concat_max_len = 10000");
        $szuresTipusok = sql_query("select * from szurestipusok where id in (".implode(",",$tipusok).") order by id<>1,!instr(megnev,'menedzser'),megnev");
        while ($szuresTipus = sql_fetch_array($szuresTipusok)) {
            $benchmarkStart = microtime(true);

            $wfCeg = $this->adminUtils->cegSQLFilter("f.cegid");
            if ($foglalasStat = sql_fetch_array(sql_query("select max(datum) as maxidopont,min(datum) as minidopont from foglalasok f where f.datum>='{$nap} 00:00:00' and f.datum<='{$nap} 23:59:59' and f.helyszinid='".intval($_SESSION["helyszin"])."' and f.szurestipusid='{$szuresTipus["id"]}' {$wfCeg}"))) {
                if ($foglalasStat["minidopont"]!="") {
                    $minTol = substr($foglalasStat["minidopont"], 11, 5);
                    $maxIg = substr($foglalasStat["maxidopont"], 11, 5);
                }
            }

            $beoRes=sql_query("SELECT b.*,min(tol) as mintol,max(ig) as maxig,o.nev as orvosnev,c.megnev as cegnev,group_concat(distinct c.megnev separator ', ') as cegek FROM orvos_beosztas b 
            left join orvosok o on o.id=b.orvosid 
            left join cegek c on c.id=b.cegid
            WHERE b.helyszinid='".intval($_SESSION["helyszin"])."' and INSTR(tipusok,'|{$szuresTipus["id"]}|') AND (nap='{$wd}' OR (nap=10 AND beonap='{$nap}')) {$wCeg} and tol<>0 and ig<>0 
            AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2) OR (WEEK('{$nap}',3)%2=1 AND b.hetek=1)) and b.aktiv=1 group by b.orvosid order by o.nev,nap,tol");
            $beosztasok = $beoRes->fetchAll();

            $intervals = $this->_getIntervals($beosztasok);
            $multiIntervalMode = count($intervals) > 1;

            foreach ($intervals as $binterval) {
                $minTol="24:00";
                $maxIg="00:00";
                $orvosok="";
                $orvosIds=[];
                $orvosokNum=0;

                foreach ($beosztasok as $beosztas) {
                    if ($beosztas["binterval"] != $binterval) continue;
                    //szabin?
                    $szabiURL = " | szabadságon";
                    if (!sql_fetch_array(sql_query("select id from szabadsag where datumtol<=? and datumig>=? and oid=?", array($nap, $nap, $beosztas["orvosid"])))) {
                        $szabiURL = " | <a onclick='return confirm(\"Biztos beállítod szabadságra erre a napra?\");' href='{$_SERVER['PHP_SELF']}?page={$_GET["page"]}&szabira={$nap}&orvosid={$beosztas["orvosid"]}'>szabadságra</a>";
                        $orvosokNum++;
                        if (strtotime($minTol) > strtotime($beosztas["mintol"])) $minTol = $beosztas["mintol"];
                        if (strtotime($maxIg) < strtotime($beosztas["maxig"])) $maxIg = $beosztas["maxig"];
                    }
                    //$binterval = $beosztas["binterval"];

                    $orvosok .= "<div style='width:250px;'><a target='_blank' href='{$_SERVER['PHP_SELF']}?page=orvosok&szerk={$beosztas["orvosid"]}'>{$beosztas["orvosnev"]}</a> - " . (substr_count($beosztas["cegek"], ",") > 0 ? "<a onclick='$(\"#beocegek{$beosztas["id"]}\").slideToggle();return false;' href='#'>" . (substr_count($beosztas["cegek"], ",") + 1) . " cég</a>" : $this->utils->substr_jns($beosztas["cegek"], 0, 20)) . "{$szabiURL}</div>";
                    $orvosok .= "<div id='beocegek{$beosztas["id"]}' style='width:250px;display:none;font-size:10px;color:#888;'>{$beosztas["cegek"]}</div>";
                    $orvosIds[] = $beosztas["orvosid"];
                }

                $minrendeles = 0;
                $maxrendeles = 0;
                //load napi beosztás
                if ($beos = $this->bookingService->getBeosztasok($nap, $helyszin, $szuresTipus["id"])) {
                    foreach ($beos as &$beo) {
                        if (strtotime($beo["tol"]) < strtotime($minrendeles) || $minrendeles == 0) $minrendeles = $beo["tol"];
                        if (strtotime($beo["ig"]) > strtotime($maxrendeles) || $maxrendeles == 0) $maxrendeles = $beo["ig"];

                        if ($beo["nap"] == 10) {
                            $beosztasData[$beo["beonap"]][] = $beo;
                        } else {
                            $beosztasData[$beo["nap"]][] = $beo;
                        }
                    }
                }
                //$htmlout.= "<tr><td height='30' colspan='3'>".print_r($beos,true)."</td></tr>";

                $rendelesek++;
                $htmlout .= "<tr><td height='30'></td></tr>";
                $htmlout .= "<tr>";

                $htmlout .= "<td valign='top' style='padding-right:10px;'><div style='font-size:16px;font-weight:bold;'>{$szuresTipus["megnev"]}</div>{$orvosok}</td>";

                if ($minTol != "24:00") {
                    $wfCeg = "";

                    $htmlout .= "<td valign='top'>";
                    $htmlout .= "<table cellpadding='0' cellspacing='0'>";
                    for ($o = 0; $o < 3600; $o += $binterval) {
                        $ora = date("H:i", strtotime("{$minTol}:00 +{$o} minute"));
                        if (strtotime($maxIg) <= strtotime($ora)) break;

                        $idopontStyle = '';

                        $timeFrom = "{$nap} {$ora}:00";
                        $timeTo = date("Y-m-d H:i:s", strtotime("{$timeFrom} + {$binterval} minute"));

                        $addIdopontJavaScript = "addIdopont(\"{$nap} {$ora}\",{$szuresTipus["id"]});return false;";
                        if ($multiIntervalMode) {
                            $addIdopontJavaScript = "setSelectedInterval({$binterval});".$addIdopontJavaScript;
                        } else {
                            $addIdopontJavaScript = "setSelectedInterval(0);".$addIdopontJavaScript;
                        }

                        $resf = sql_query("select f.*,c.megnev as cegnev,o.nev as orvosnev,d.id as docid from foglalasok f 
                        left join cegek c on c.id=f.cegid
                        left join orvosok o on o.id=f.orvosassigned
                        left join dokumentumok d on d.foglalasid=f.id
                        where f.datum>=? and f.datum<? and f.helyszinid=? and f.szurestipusid=? {$wfCeg} group by f.id", array($timeFrom, $timeTo, $_SESSION["helyszin"], $szuresTipus["id"])); //


                        $lastIdopont = "";
                        $foglalasButtonVolt = 0;
                        while ($rowf = sql_fetch_array($resf)) {
                            $jogosult = true;
                            if ($this->adminUtils->isCegAdmin() && substr_count($_SESSION["adminuser"]["cegjog"], "|{$rowf["cegid"]}|") == 0) {
                                $jogosult = false;
                            }

                            //itt csekkoljuk, hogy a megfelelő táblázatba tartozik-e a foglalás
                            if ($multiIntervalMode) {
                                if ($rowf["rinterval"] != 0) {
                                    if ($rowf["rinterval"] != $binterval) {
                                        continue;
                                    }
                                }
                            }
                            if ($rowf["orvosassigned"] != 0) {
                                if (in_array($rowf["orvosassigned"],array($orvosIds))) {
                                    continue;
                                }
                            }
                            //táblázat filter vége

                            if ($rowf["nev"] == "nincs név") $rowf["nev"] = "Foglalt";

                            $idopontShow = substr($rowf["datum"], 11, 5);

                            $htmlout .= "<tr style='{$idopontStyle}'>";
                            $htmlout .= "<td valign='top'>" . ($idopontShow != $lastIdopont ? $idopontShow : "") . "&nbsp;&nbsp;</td>";
                            $htmlout .= "<td valign='top'>";
                            if ($foglalasButtonVolt == 0 && "{$nap} {$idopontShow}" == "{$nap} {$ora}") {
                                $htmlout .= "<a onclick='{$addIdopontJavaScript}' class='kisbutton' title='foglalás' href='#'>+</a>&nbsp;&nbsp;";
                                $foglalasButtonVolt = 1;
                            }
                            $htmlout .= "</td>";
                            $htmlout .= "<td valign='top'>";
                            if ($jogosult) {
                                $htmlout .= "<a onclick='removeIdopont({$rowf["id"]},\"{$rowf["pass"]}\",\"booking\");return false;' class='kisbutton' title='foglalás törlése' href='#'>-</a>&nbsp;&nbsp;";
                                $htmlout .= "<a onclick='showIdopontEditor(\"{$_GET["page"]}\",\"{$rowf["pass"]}\",{$rowf["id"]});return false;' href='#' style='" . ($rowf["nev"] == "Foglalt" ? "opacity:.5;" : "") . "'>{$rowf["nev"]}</a>" . ($rowf["tudoszuro"] != 0 ? " <span title='Tüdőszűrés kell' style='background:#f00;color:#fff;padding:0px 5px;border-radius:3px;'>T</span>" : "") . "&nbsp;" . ($rowf["docid"] != null ? " <span style='background:#888;color:#fff;padding:0px 5px;border-radius:3px;'>file</span>" : "") . "&nbsp;&nbsp;";
                            } else {
                                $htmlout .= "<span style='color:#aaa;'>Másik cég foglalása</span>&nbsp;&nbsp;";
                            }
                            $htmlout .= "</td>";
                            if ($jogosult) {
                                $htmlout .= "<td valign='top'>";
                                $cegNev = trim($this->utils->substr_jns($rowf["cegnev"], 0, 20));

                                $orvNev = "";
                                if ($rowf["cegid"] == 0 && $rowf["orvosassigned"] == 0 && $orvosokNum > 1) $orvNev = "minden orvos";
                                if ($rowf["orvosassigned"] != 0) $orvNev = trim($rowf["orvosnev"]);

                                $htmlout .= "<span style='" . ($rowf["cegid"] == $_SESSION["ecegfilter"] ? "font-weight:bold;color:#00a;" : "") . "'>{$cegNev}</span>";
                                if ($orvNev != "" && $cegNev != "") $htmlout .= " &#187; ";
                                $htmlout .= " <span style='color:#080;'>{$orvNev}</span>";
                                if ($orvNev == "" && $cegNev == "") $htmlout .= "???";
                                $htmlout .= "&nbsp;&nbsp;";

                                $htmlout .= "<div id='fiz_szolglist{$rowf["id"]}'>" . $this->adminUtils->showFizSzolg($rowf["id"], 1) . "</div>";

                                $kupon = sql_fetch_array(sql_query("SELECT * FROM kuponkodok kk LEFT JOIN kupon_lista kl ON kl.kuponid=kk.id WHERE kl.foglalasid = {$rowf["id"]}"));
                                $kuponNotification = "";
                                $help = "";
                                if ($kupon['kedvezmeny_tipus'] == "szazalek") {
                                    $start = date("Y-m-d", strtotime($kupon["event_start"]));
                                    $end = date("Y-m-d", strtotime($kupon["event_end"]));
                                    $help = "title='{$start}->{$end}&nbsp;{$kupon["leiras"]}'";
                                    $kuponNotification = "[{$kupon['megnev']}:&nbsp;{$kupon['kedvezmeny']}%]";
                                }
                                if ($kupon['kedvezmeny_tipus'] == "fix") {
                                    $help = "title='{$kupon["event_start"]}->{$kupon["event_end"]}'";
                                    $kuponNotification = "[{$kupon['megnev']}:&nbsp;{$kupon['kedvezmeny']}Ft]";
                                }

                                $htmlout .= "</td>";
                                $htmlout .= "<td valign='top'>";
                                $htmlout .= $rowf["megj"] . "&nbsp;";
                                $htmlout .= "<span {$help} style='color:#5e11a1;font-weight:bold'>{$kuponNotification}</span>";
                                $htmlout .= "</td>";
                            }
                            $htmlout .= "</tr>";
                            $lastIdopont = $idopontShow;
                        }

                        if ($lastIdopont == "") {
                            //nem volt foglalás, üres időpont kirakás
                            $htmlout .= "<tr style='{$idopontStyle}'>";
                            $htmlout .= "<td valign='top'>{$ora}&nbsp;&nbsp;</td>";
                            $htmlout .= "<td valign='top'><a onclick='{$addIdopontJavaScript}' class='kisbutton' title='foglalás' href='#'>+</a>&nbsp;&nbsp;</td>";
                            $htmlout .= "</tr>";
                        }
                    }
                    $htmlout .= "</table>";
                    $htmlout .= "</td>";
                }


                $htmlout .= "</tr>";
            }
            $benchmarkEnd = microtime(true);
            if (false) $htmlout.="<tr><td colspan='2'>".($benchmarkEnd-$benchmarkStart)."</td></tr>";
        }
        $htmlout.="</table>";

        if ($rendelesek==0) {
            $htmlout.="<div style='margin-top:30px;'>Ezen a napon nincs rendelés a kiválasztott helyszínen.</div>";
        }

        return $htmlout;
    }


    private function _getIntervals($beosztasok) {
        $intervals = [];
        foreach ($beosztasok as $beosztasData) {
            if (!in_array($beosztasData["binterval"],$intervals)) $intervals[] = $beosztasData["binterval"];
        }
        return $intervals;
    }

}

