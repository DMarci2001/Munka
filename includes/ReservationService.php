<?php



if (isset($_GET["szurestipusrefresh"])) {
    echo szuresTipusValasztoNew($_GET["szurestipusrefresh"],0);
    die();
}


if (isset($_GET["showidopontvalasztov2"])) {
    $helyszin=intval($_GET["helyszin"]);
    $szuresTipus=intval($_GET["szurestipus"]);
    $honnan=intval($_GET["honnan"]);
    //if ($honnan<0) $honnan=0;


    if (!$rowmax=sql_fetch_array(sql_query("SELECT MIN(tol) as minrendeles,MAX(ig) as maxrendeles FROM orvos_beosztas WHERE helyszinid='{$helyszin}' and (cegid='{$_SESSION["helyszindata"]["id"]}') and (instr(tipusok,'|{$szuresTipus}|')) HAVING MAX(tol) IS NOT NULL"))) {
        echo "<div style='margin:10px 0px;color:#f00;'>Erre a szűrés típusra nincsenek beállítva rendelési időpontok.</div>";
        die();
    }


    //
    //orvosválasztó
    //
    if (!isset($_SESSION["orvosselected"])) $_SESSION["orvosselected"]=0;
    $orvosAvailable=[];
    $res=sql_query("select * from orvos_beosztas b 
                        left join orvosok o on o.id=b.orvosid 
                        where b.helyszinid=? and instr(b.tipusok,?) and 
						b.cegid = {$_SESSION['helyszindata']['id']} 
						and (nap<10 or b.beonap >= date(now())) 
						and b.aktiv=1 
						and o.aktiv=1",array($helyszin,"|{$szuresTipus}|"));
    while ($beoData=sql_fetch_array($res)) {
        if ($beoData["csaksorban"]!=0) $vanCsakSorban=true;
        $orvosAvailable[$beoData["orvosid"]]=$beoData;
    }

    if (isset($_REQUEST["selectoid"]) && $_REQUEST["selectoid"]!=0) $_SESSION["orvosselected"]=$_REQUEST["selectoid"];

    //feltétel ami alapján kirakjuk az orvosválasztót
    if (($_SESSION['helyszindata']['id']==74 || in_array($_SERVER["REMOTE_ADDR"],array("194.143.226.42","89.134.90.181"))) && count($orvosAvailable)>1) {
        echo "<div style='margin:10px 0px 10px 0px;'>{$webText["valasszorvost"]}:</div>";
        foreach ($orvosAvailable as $orvosData) {
            $s="border:1px solid #fff;";
            if ($orvosData["orvosid"]==$_SESSION["orvosselected"]) {
                $s="border:1px solid #080;";
                $orvosIsSelected=true;
            }
            echo "<div><a href='#' onclick='showIdoPontValasztoV2({$honnan},{$orvosData["orvosid"]});return false;' style='display:inline-block;padding:3px;color:#080;{$s}'>{$orvosData["nev"]}</a></div>";
        }
        if (!isset($orvosIsSelected)) {
            die();
        }
    }

    if (count($orvosAvailable)==1) {
        $data = current($orvosAvailable);
        //print_r($data);
        $_SESSION["orvosselected"]=$data["orvosid"];
    }

    //orvosválasztó vége


    $szunnapok[]="";
    $rows=sql_fetch_array(sql_query("select * from settings"));
    $n=explode(",",$rows["szunnapok"]);
    for ($i=0;$i<count($n);$i++) {
        $szunnapok[]=trim($n[$i]);
    }


    echo "<div style='display:inline-block;margin:10px 0px 10px 0px;'>";
    echo "<div>{$webText["valasszidopontot"]}:</div>";

    echo "<table style='margin-top:5px;width:100%;'><tr><td><a href='javascript:showIdoPontValasztoV2(".($honnan-7).")'>{$webText["elo7"]}</a></td><td align='right'><a href='javascript:showIdoPontValasztoV2(".($honnan+7).")'>{$webText["kov7"]}</a></td></tr></table>";
    //echo "<div style='margin-top:5px;'> | </div>";

    echo "<table cellpadding='0' cellspacing='0'><tr>";

    //ennyi órán belül kell foglalni
    $dist = "6 hour";
    if ($helyszin==1) $dist = "0 hour"; //jász utca bármikor foglalható

    //ennyi napon belül kell foglalni
    $distFullDay = "0 day";
    if ($_SESSION["orvosselected"] == 36) {
        //36 - dr Bodonyi Melinda
        $distFullDay = "2 day";
    }


    for ($i=0;$i<=6;$i++) {
        $fix=$i+$honnan;

        $nap=date("Y-m-d",strtotime("this week monday +{$fix} day"));
        $wd=date("N",strtotime("this week monday +{$fix} day"));  //day of week
        $wn=date("W",strtotime("this week monday +{$fix} day"));  //number of week

        echo "<td valign='top'>";

        if ($nap==date("Y-m-d")) {
            echo "<div style='background:#607d8b;margin:0px 1px;padding:12px 10px 12px 10px;color:#fff;font-weight:bold;text-align:center;'>{$nap}<br/>{$webText["hetnap"][$wd]}</div>";
        } else {
            echo "<div style='background:#607d8b;margin:8px 1px;padding:4px 10px 4px 10px;color:#fff;font-weight:bold;text-align:center;'>{$nap}<br/>{$webText["hetnap"][$wd]}</div>";
        }

        if (!$napiBeos=getBeosztasok("{$nap}",$helyszin,$szuresTipus,$_SESSION["orvosselected"])) {
            echo "<div style='text-align:center;margin:5px;padding:5px 0px;color:#888;'>{$webText["nincsrendeles"]}</div>";
            echo "</td>";
            continue;
        }

        if (in_array($nap,$szunnapok)) {
            echo "<div style='text-align:center;margin:5px;padding:5px 0px;color:#888;'>Munkaszüneti<br/>nap</div>";
            echo "</td>";
            continue;
        }


        //get binterval;
        foreach ($napiBeos as &$beoData) {
            //ütköző beosztások is lehetnek
            $binterval=$beoData["binterval"];
        }


        $beginora=round(substr($rowmax["minrendeles"],0,2));
        $beginperc=round(substr($rowmax["minrendeles"],3,2));

        $napHTML="";
        $napHTML.="<input type='hidden' id='rinterval-{$nap}' value='{$binterval}' />";
        for ($o=0;$o<=200;$o++) {
            $ora=date("H:i",mktime($beginora,$beginperc+$o*$binterval,0,date("m"),date("d"),date("Y")));
            if (strtotime($ora)>=strtotime($rowmax["maxrendeles"])) break;

            $napHTML.="<div style='text-align:center;'>";

            if (isset($beos)) unset($beos);
            $numRendeles=0;
            $orvosNevek="";

            $buttonTitle = "";
            $buttonClass = "foglaltbtn";
            $buttonJava = "nemfog();return false;";

            //beosztások beolvasása
            if ($beos=getBeosztasok("{$nap} {$ora}",$helyszin,$szuresTipus,$_SESSION["orvosselected"])) {
                //szabad orvos kiválasztása
                foreach ($beos as &$beoData) {
                    $vanRendeles=true;
                    if (orvosIdopontIsFree("{$nap} {$ora}",$beoData["orvosid"],$helyszin)) {
                        $numRendeles++;
                        $orvosNevek.=", {$beoData["orvosnev"]}";
                        $buttonClass="foglalhatobtn";
                        $buttonTitle="{$numRendeles} hely (".substr($orvosNevek,2).")";
                        $buttonJava="chooseIdoPont(\"{$nap} {$ora}\",{$_SESSION["orvosselected"]});return false;";
                        //break;
                    }
                }
            }

            //csak sorban foglalható időpontok intézése
            if ($beoData["csaksorban"]==1 && isset($elsoIdopont[$nap]) && $buttonClass=="foglalhatobtn") {
                $buttonJava="nemfogs(\"{$elsoIdopont[$nap]}\");return false;";
                $buttonClass.=" halv";
            }
            if (!isset($elsoIdopont[$nap]) && $buttonClass=="foglalhatobtn") $elsoIdopont[$nap]=$ora;

            //teszt: minden időpont foglalható
            //$buttonJava="chooseIdoPont(\"{$nap} {$ora}\");return false;";

            if (strtotime("now + {$dist}")>strtotime("{$nap} {$ora}")) {
                //mégse foglalható, múltbéli dátum vagy túl közeli
                $buttonTitle = "";
                $buttonClass = "foglaltbtn";
                $buttonJava = "nemfog();return false;";
            }

            if (strtotime("now + {$distFullDay}")>strtotime("{$nap} 23:59:59")) {
                //mégse foglalható, csak x napra előre foglalható
                $buttonTitle = "";
                $buttonClass = "foglaltbtn";
                $buttonJava = "nemfog();return false;";
            }

            $btn="<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a>";

            //csak fordított sorrendben időpontok intézése
            if ($beoData["csaksorban"]==2 && $buttonClass=="foglalhatobtn") {
                $lastButton=$btn;
                $buttonJava="nemfogs2();return false;";
                $buttonClass.=" halv";
                $btn = "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a>";
            }

            $napHTML.=$btn;
            $napHTML.="</div>";
        }

        if (isset($lastButton)) {
            $napHTML=str_replace($btn,$lastButton,$napHTML);
            unset($lastButton);
        }

        echo $napHTML;
        echo "</td>";
    }




    echo "</tr></table>";
    echo "</div>";
    die();
}

if (isset($_GET["showidopontvalasztov5"])) {
    $helyszin=intval($_GET["helyszin"]);
    $szuresTipus=intval($_GET["szurestipus"]);
    $honnan=intval($_GET["honnan"]);
    //if ($honnan<0) $honnan=0;


    if ( !$rowmax = sql_fetch_array( sql_query( "SELECT MIN(tol) as minrendeles,MAX(ig) as maxrendeles FROM orvos_beosztas 
												 WHERE helyszinid = '{$helyszin}' 
												 AND  ( cegid = 11 ) 
												 AND  ( instr( tipusok, '|{$szuresTipus}|' )) HAVING MAX(tol) IS NOT NULL" ))) {
        echo "<div style='margin:10px 0px;color:#f00;'>Erre a szűrés típusra nincsenek beállítva rendelési időpontok.</div>";
        die();
    }


    //
    //orvosválasztó
    //
    if (!isset($_SESSION["orvosselected"])) $_SESSION["orvosselected"]=0;
    $orvosAvailable=[];
    $res=sql_query("select * from orvos_beosztas b 
                        left join orvosok o on o.id=b.orvosid 
                        where b.helyszinid=? and instr(b.tipusok,?) and (nap<10 or b.beonap >= date(now())) and b.aktiv=1 and o.aktiv=1",array($helyszin,"|{$szuresTipus}|"));
    while ($beoData=sql_fetch_array($res)) {
        if ($beoData["csaksorban"]!=0) $vanCsakSorban=true;
        $orvosAvailable[$beoData["orvosid"]]=$beoData;
    }

    if (isset($_REQUEST["selectoid"]) && $_REQUEST["selectoid"]!=0) $_SESSION["orvosselected"]=$_REQUEST["selectoid"];

    //feltétel ami alapján kirakjuk az orvosválasztót
    if (in_array($_SERVER["REMOTE_ADDR"],array("194.143.226.42","89.134.90.181","78.92.193.167")) && count($orvosAvailable)>1) {
        echo "<div style='margin:10px 0px 10px 0px;'>{$webText["valasszorvost"]}:</div>";
        foreach ($orvosAvailable as $orvosData) {
            $s="border:1px solid #fff;";
            if ($orvosData["orvosid"]==$_SESSION["orvosselected"]) {
                $s="border:1px solid #080;";
                $orvosIsSelected=true;
            }
            echo "<div><a href='#' onclick='showIdoPontValasztoV4({$szuresTipus},{$honnan},1,{$orvosData["orvosid"]});return false;' style='display:inline-block;padding:3px;color:#080;{$s}'>{$orvosData["nev"]}</a></div>";
        }
        if (!isset($orvosIsSelected)) {
            die();
        }
    }
    //orvosválasztó vége


    $szunnapok[] = "";
    $rows = sql_fetch_array( sql_query( "select * from settings" ));
    $n = explode( ",", $rows["szunnapok"] );
    for ( $i = 0; $i < count($n); $i++ ) {
        $szunnapok[] = trim( $n[$i] );
    }


    echo "<div style='display:inline-block;margin:10px 0px 10px 0px;'>";
    echo "<div>{$webText["valasszidopontot"]}:</div>";

    echo "<table style='margin-top:5px;width:100%;font-size:12px;'><tr><td><a href='#' style='color:#a00;text-decoration:none;' onClick='showIdoPontValasztoV4(".$szuresTipus.",".($honnan-7).",1);return false'>{$webText["elo7"]}</a></td><td align='right'><a href = '#' style='color:#a00;text-decoration:none;' onClick='showIdoPontValasztoV4(".$szuresTipus.",".($honnan+7).",1);return false'>{$webText["kov7"]}</a></td></tr></table>";
    //echo "<div style='margin-top:5px;'> | </div>";

    echo "<table style = 'font-size:12px;' cellpadding='0' cellspacing='0'><tr>";


    $dist = "6 hour";
    if ( $helyszin == 1 ) $dist = "0 hour"; //jász utca bármikor foglalható


    for ( $i = 0; $i <= 6; $i++ ) {
        $fix = $i + $honnan;

        $nap = date( "Y-m-d", strtotime( "this week monday +{$fix} day" ));
        $wd = date( "N", strtotime( "this week monday +{$fix} day" ));  //day of week
        $wn = date( "W", strtotime( "this week monday +{$fix} day" ));  //number of week

        echo "<td valign='top'>";

        if ( $nap == date( "Y-m-d" )) {
            echo "<div style='background:#607d8b;margin:0px 1px;padding:12px 10px 12px 10px;color:#fff;font-weight:bold;text-align:center;'>{$nap}<br/>{$webText["hetnap"][$wd]}</div>";
        } else {
            echo "<div style='background:#607d8b;margin:8px 1px;padding:4px 10px 4px 10px;color:#fff;font-weight:bold;text-align:center;'>{$nap}<br/>{$webText["hetnap"][$wd]}</div>";
        }

        if ( !$napiBeos = getBeosztasok( "{$nap}", $helyszin, $szuresTipus, $_SESSION["orvosselected"] )) {
            echo "<div style='text-align:center;margin:5px;padding:5px 0px;color:#888;'>{$webText["nincsrendeles"]}</div>";
            echo "</td>";
            continue;
        }

        if ( in_array( $nap,$szunnapok )) {
            echo "<div style='text-align:center;margin:5px;padding:5px 0px;color:#888;'>Munkaszüneti<br/>nap</div>";
            echo "</td>";
            continue;
        }


        //get binterval;
        foreach ( $napiBeos as &$beoData ) {
            //ütköző beosztások is lehetnek
            $binterval = $beoData["binterval"];
        }

        $beginora = round( substr( $rowmax["minrendeles"], 0, 2 ));
        $beginperc = round( substr( $rowmax["minrendeles"], 3, 2 ));

        $napHTML = "";
        for ( $o = 0; $o <= 55; $o++ ) {
            $ora = date( "H:i", mktime( $beginora, $beginperc + $o * $binterval, 0, date( "m" ), date( "d" ), date( "Y" )));
            if ( strtotime( $ora ) >= strtotime( $rowmax["maxrendeles"] )) break;

            $napHTML.= "<div style='text-align:center;'>";

            $buttonTitle = "";
            $buttonClass = "foglaltbtn";
            $buttonJava = "nemfog();return false;";

            if ( isset( $beos )) unset( $beos );
            $vanRendeles = false;
            $numRendeles = 0;
            $orvosNevek = "";
            //beosztások beolvasása
            if ( $beos = getBeosztasok( "{$nap} {$ora}", $helyszin, $szuresTipus, $_SESSION["orvosselected"] )) {
                //szabad orvos kiválasztása
                foreach ( $beos as &$beoData ) {
                    $vanRendeles = true;
                    if ( orvosIdopontIsFree( "{$nap} {$ora}", $beoData["orvosid"], $helyszin )) {
                        $numRendeles++;
                        $orvosNevek.=  ", {$beoData["orvosnev"]}";
                        $buttonClass = "foglalhatobtn";
                        $buttonTitle = "{$numRendeles} hely (".substr($orvosNevek,2).")";
                        $buttonJava =  "chooseIdoPontV1(\"{$nap} {$ora}\",{$szuresTipus},{$helyszin},{$_SESSION["orvosselected"]});return false;";
                        //break;
                    }
                }
            }

            if ( strtotime( "now + {$dist}") > strtotime( "{$nap} {$ora}" )) {
                //mégse foglalható, múltbéli dátum vagy túl közeli
                $buttonTitle = "";
                $buttonClass = "foglaltbtn";
                $buttonJava = "nemfog();return false;";
            }

            //csak sorban foglalható időpontok intézése
            if ( $beoData["csaksorban"] == 1 && isset( $elsoIdopont[$nap] ) && $buttonClass == "foglalhatobtn" ) {
                $buttonJava = "nemfogs(\"{$elsoIdopont[$nap]}\");return false;";
                $buttonClass.= " halv";
            }
            if ( !isset( $elsoIdopont[$nap] ) && $buttonClass == "foglalhatobtn" ) $elsoIdopont[$nap] = $ora;

            //teszt: minden időpont foglalható
            //$buttonJava="chooseIdoPont(\"{$nap} {$ora}\");return false;";
            $btn = "<a class = '{$buttonClass}' title = '{$buttonTitle}' onclick = '{$buttonJava}' href = '#'>{$ora}</a>";

            //csak fordított sorrendben időpontok intézése
            if ( $beoData["csaksorban"] == 2 && $buttonClass == "foglalhatobtn" ) {
                $lastButton = $btn;
                $buttonJava = "nemfogs2();return false;";
                $buttonClass.= " halv";
                $btn = "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a>";
            }

            $napHTML.= $btn;



            //echo print_r($beos,true);
            $napHTML.= "</div>";
        }

        if ( isset( $lastButton )) {
            $napHTML = str_replace( $btn, $lastButton, $napHTML );
            unset( $lastButton );
        }

        echo $napHTML;
        echo "</td>";
    }
    echo "</tr></table>";
    echo "</div>";
    die();
}

if (isset($_POST["checkrendeles"])) {
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    if (!$odata=selectOrvosForIdopont($_POST["idopont"],$_POST["helyszin"],$_POST["szurestipusid"],$_POST["orvos"])) {
        die("Ezt az időpontot időközben lefoglalták!");
    }

    if ($odata["onlytel"]==1 && $odata["tel"]!="") {
        echo "Erre a rendelésre az online bejelentkezés jelenleg nem üzemel kérjük jelentkezzen be ezen a telefon számon: ".$odata["tel"];
        die();
    }

    $statement = $_SERVER['REQUEST_URI'];
    if( isset( $_REQUEST['version'] ) && $_REQUEST['version'] == "2" )
    {
        if( $statement == "/index.php?page=welcome" || ( $statement == "/" && $_SESSION["helyszindata"]["id"] == 11 )) echo "ok3";
        if( $statement == "/index.php?page=idopontfoglalas" ) echo "ok2";
        if( $statement == "/index.php" ) echo "ok3";
    }
    else echo "ok";
    die();
}




function selectFreeOrvosForIdopont($fid) {
    $oid=0;

    if ($foglalasData=sql_fetch_array(sql_query("select * from foglalasok where id=?",array($fid)))) {
        $idopont=date("Y-m-d H:i",strtotime($foglalasData["datum"]));
        if ($foglalasData["orvosassigned"]!=0) {
            $oid=$foglalasData["orvosassigned"];
        } else {
            if ($beos=getBeosztasok($idopont,$foglalasData["helyszinid"],$foglalasData["szurestipusid"])) {
                //print_r($beos);
                //szabad orvos kiválasztása
                foreach ($beos as &$beoData) {
                    if (orvosIdopontIsFree($idopont,$beoData["orvosid"])) {
                        if ($foglalasData["rinterval"] != 0 && $foglalasData["rinterval"] != $beoData["binterval"]) continue; //ha intervallum is van a foglaláshoz, azt is csekkoljuk
                        $oid=$beoData["orvosid"];
                        break;
                    }
                }
            }
        }
    }
    return $oid;
}




function orvosIdopontIsFree($idoPont,$orvosId,$helyszin=0) {
    $nap=substr($idoPont,0,10);
    $free=false;
    //echo "SELECT datum FROM foglalasok WHERE datum='".addslashes($idoPont).":00' AND orvosassigned='{$orvosId}'";

    $wadd="";
    if ($helyszin!=0) $wadd="or (helyszinid='{$helyszin}' and cegid=0 and orvosassigned=0)";


    if (!sql_fetch_array(sql_query("SELECT datum FROM foglalasok WHERE datum='".addslashes($idoPont).":00' AND (orvosassigned='{$orvosId}' {$wadd})"))) {
        if (!sql_fetch_array(sql_query("select * from szabadsag where oid='{$orvosId}' and datumtol<='{$nap}' and datumig>='{$nap}'"))) {
            $free=true;
        }
    }
    return $free;
}




function getBeosztasok($idoPont,$helyszin,$szuresTipus,$orvos=0) {
    $nap=substr($idoPont,0,10);
    $ora=substr($idoPont,11,5);
    $helyszin=intval($helyszin);
    $szuresTipus=intval($szuresTipus);
    $cegId=$_SESSION["helyszindata"]["id"];
    if (isset($_SESSION["helyszinceg"]) && isset($GLOBALS["admin"])) $cegId=$_SESSION["helyszinceg"];

    $wora=$wceg="";
    if ($ora!="") $wora="AND TIME(tol)<=TIME('{$ora}') AND TIME(ig)>TIME('{$ora}')";

    //admin esetén lazább szűrés
    if (isset($GLOBALS["admin"])) {
        if ($_SESSION["adminuser"]["jogosultsag"]<2) $wceg="and (b.cegid='{$cegId}' or b.cegid=0)";
    } else {
        $wceg="and (b.cegid='{$cegId}' or b.cegid=0)";
    }

    //időpontra beosztott orvosok kiolvasása
    $resb=sql_query("SELECT b.*,o.id as orvosid,o.nev as orvosnev,o.onlytel,c.megnev as cegnev FROM orvos_beosztas b 
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		left join cegek c on c.id=b.cegid
		WHERE b.`helyszinid`='{$helyszin}' {$wceg} AND (nap=WEEKDAY('{$nap}')+1 or beonap='{$nap}') {$wora} AND INSTR(b.tipusok,'|{$szuresTipus}|') 
		AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2) OR (WEEK('{$nap}',3)%2=1 AND b.hetek=1)) and b.aktiv=1
		ORDER BY b.cegid<>'{$cegId}',o.nev,o.onlytel,b.cegid DESC,o.id");

    while ($rowb=sql_fetch_array($resb)) {

        if (isset($GLOBALS["admin"]) || !sql_fetch_array(sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap=? AND nap>=DATE(NOW()) and (szurestipusid=? or instr(szurestipusid,'|{$szuresTipus}|'))",array($helyszin,$cegId,$nap,$szuresTipus)))) {
            if ($rowb["orvosid"]==$orvos || $orvos==0) {
                $beos[] = $rowb;
            }
        }
    }
    if (!isset($beos)) {
        return false;
    }
    return $beos;
}



function isOrvosAvailable($idopont,$helyszinid,$szurestipusid) {
    $nap=substr($idopont,0,10);
    $ora=substr($idopont,11,5);
    $helyszinid=intval($helyszinid);
    $cegid=$_SESSION["helyszindata"]["id"];

    //időpontra beosztott számának megállapítása
    $orvosNumForIdopont=0;
    $resb=sql_query("SELECT * FROM orvos_beosztas b	WHERE b.`helyszinid`='{$helyszinid}' AND (nap=WEEKDAY('{$nap}')+1 or beonap='{$nap}') AND TIME(tol)<=TIME('{$ora}') AND TIME(ig)>TIME('{$ora}') AND INSTR(b.tipusok,'|".intval($szurestipusid)."|') and b.aktiv=1 GROUP BY b.orvosid");
    while ($rowb=sql_fetch_array($resb)) {
        //nap foglalt-e?
        if (!sql_fetch_array(sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap=? and (szurestipusid=0 or szurestipusid=?)",array($helyszinid,$cegid,$nap,$szurestipusid)))) {
            //orvos nincs szabadságon?
            if (!sql_fetch_array(sql_query("select * from szabadsag where oid='{$rowb["orvosid"]}' and datumtol<='{$nap}' and datumig>='{$nap}'"))) {
                $orvosNumForIdopont++;
            }
        }
    }

    $foglalasok=sql_fetch_array(sql_query("SELECT count(*) as hany FROM foglalasok WHERE datum=? AND helyszinid=? and szurestipusid=?",array($idopont,$helyszinid,$szurestipusid)));
    if ($foglalasok["hany"]>=$orvosNumForIdopont) {
        return false;
    }
    return true;
}

function selectOrvosForIdopont($idopont,$helyszinid,$szurestipusid,$orvos=0) {
    $nap=substr($idopont,0,10);
    $ora=substr($idopont,11,5);
    $helyszinid=intval($helyszinid);
    $cegid=$_SESSION["helyszindata"]["id"];


    //időpontra beosztott orvosok kiolvasása
    $resb=sql_query("SELECT * FROM orvos_beosztas b 
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		WHERE b.`helyszinid`='{$helyszinid}' and (b.cegid='{$cegid}' or b.cegid=0) AND (nap=WEEKDAY('{$nap}')+1 or beonap='{$nap}') AND TIME(tol)<=TIME('{$ora}') AND TIME(ig)>TIME('{$ora}') AND INSTR(b.tipusok,'|".intval($szurestipusid)."|')
		".($orvos==0?"":"and b.orvosid='{$orvos}'")." 
		ORDER BY o.onlytel,b.cegid DESC,o.id");

    while ($rowb=sql_fetch_array($resb)) {
        //orvos foglalt-e?
        if (!sql_fetch_array(sql_query("SELECT datum FROM foglalasok WHERE datum='".addslashes($idopont)."' AND cegid='{$cegid}' AND helyszinid='{$helyszinid}' AND orvosassigned='{$rowb["orvosid"]}'"))) {
            //nap foglalt-e
            if (!sql_fetch_array(sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap=? and (szurestipusid=0 or szurestipusid=?)",array($helyszinid,$cegid,$nap,$szurestipusid)))) {
                //orvos szabad ->
                if (!sql_fetch_array(sql_query("select * from szabadsag where oid='{$rowb["orvosid"]}' and datumtol<='{$nap}' and datumig>='{$nap}'"))) {
                    //+nincs szabadságon
                    return $rowb;
                }
            }
        }
    }

    /*
    while ($rowb=sql_fetch_array($resb)) {
        //orvos foglalt-e?
        if (!sql_fetch_array(sql_query("SELECT datum FROM foglalasok WHERE datum='".addslashes($idopont)."' AND cegid='{$cegid}' AND helyszinid='{$helyszinid}' AND orvosassigned='{$rowb["orvosid"]}'"))) {
            //nap foglalt-e
            if (!sql_fetch_array(sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap=? AND nap>=DATE(NOW()) and (szurestipusid=? or instr(szurestipusid,'|{$szurestipusid}|'))",array($helyszinid,$cegid,$nap,$szurestipusid)))) {
                //orvos szabad ->
                if (!sql_fetch_array(sql_query("select * from szabadsag where oid='{$rowb["orvosid"]}' and datumtol<='{$nap}' and datumig>='{$nap}'"))) {
                    //+nincs szabadságon
                    return $rowb;
                }
            }
        }
    }
    */

    return false;
}


function szuresTipusValasztoNew($helyszinid,$selected=0,$onlyselected=0) {
    global $webText;
    $tipusok=array();

    $rest=sql_query("select * from szurestipusok");
    while ($rowt=sql_fetch_array($rest)) {
        if ($_COOKIE["lang"]!="hu" && trim($rowt["megnev_{$_COOKIE["lang"]}"])!="") $rowt["megnev"]=$rowt["megnev_{$_COOKIE["lang"]}"];
        $tipusnevek[$rowt["id"]]=$rowt["megnev"];
    }

    $addJava="";
    if ($_SESSION["helyszindata"]["id"]==11) {
        $addJava="if (this.value==1) { $(\"#fogleuwarn\").show(); } else { $(\"#fogleuwarn\").hide(); }";
    }
    $megjBox="if(this.value==14 || this.value==65){ $(\"#borgyogystuff\").css(\"visibility\",\"visible\") } else{ $(\"#borgyogystuff\").css(\"visibility\",\"hidden\") }";
    $htmlout="";
    $htmlout.="<select name='szurestipus' id='szurestipus' onchange='clearIdopontValaszto();showTipusMegj(this.value);{$megjBox};{$addJava}'>";
    $htmlout.="<option value='0'>{$webText["valasszon"]}!</option>";

    $res=sql_query("SELECT tipusok FROM orvos_beosztas b WHERE b.helyszinid='".addslashes($helyszinid)."' AND b.cegid='{$_SESSION["helyszindata"]["id"]}'");
    while ($row=sql_fetch_array($res)) {
        $ta=explode("|",$row["tipusok"]);
        for ($i=0;$i<count($ta);$i++) {
            if (trim($ta[$i])!="" && !in_array($ta[$i],$tipusok)) {
                $tipusok[]=$ta[$i];
            }
        }
    }

    if (isset($tipusok)) {
        for ($i=0;$i<count($tipusok);$i++) {
            @$tipusdisplay[$tipusok[$i]]=$tipusnevek[$tipusok[$i]];
        }
        if (isset($tipusdisplay)) {
            asort($tipusdisplay);
            foreach ($tipusdisplay as $key => $value) {
                //if (count($tipusdisplay)==1) $selected=$key;
                if ($onlyselected==1 && $key!=$selected) continue;
                if (trim($value)=="") continue;
                $htmlout.="<option value='{$key}'".($selected==$key?" selected":"").">{$value}</option>";
            }
        }
    }

    $htmlout.="</select><div id='borgyogystuff' style='display: inline-block; visibility: hidden;font-size:14px;margin-left:10px;padding:5px;background-color:#e13030;color:white;font-weight:bold'>Eltávoltításra is szükség van <input type='checkbox' style='transform: scale(1.5);' onChange='$(\"#foglmegj\").text(\"Eltávolításra is szükség van, VISSZAHÍVÁST KÉREK!\")' name = 'eltavolitas' value = 'szukseges'/></div>";

    if (trim($helyszinid)=="" || $helyszinid==0) $htmlout="Válassz előbb helyszínt!<input type='hidden' name='szurestipus' value='' />";

    return $htmlout;
}


