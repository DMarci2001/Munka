<?php

class BookingService {
    private $lang;
    private $utils;
    public $packContentTypes = [];
    private $honnan;
    private $neme;
    public $helyszin = 0;
    public $szuresTipus = 0;
    public $szuresTipusData;

    public $szuresTipusMap = [];

    public function __construct()
    {
        $this->lang = new Lang();
        $this->utils = new Utils();

        if (isset($_GET["szurestipusrefresh"])) {
            echo $this->szuresTipusValasztoNew($_GET["szurestipusrefresh"],0);
            die();
        }

        if (isset($_POST["gettipusmegj"])) {
            echo $this->getTipusMegj($_SESSION["helyszindata"]["id"], $_POST["tid"], $_POST["hid"]);
            die();
        }

        if (isset($_GET["showidopontvalasztov2"])) {
            $webText = $this->lang->webText;

            header('Content-Type: application/json');

            $html                  = "";
            $this->helyszin        = intval($_GET["helyszin"]);
            $this->szuresTipus     = intval($_GET["szurestipus"]);
            $this->neme            = intval($_GET["neme"]);
            $this->honnan          = intval($_GET["honnan"]);
            $this->szuresTipusData = sql_fetch_array(sql_query("select * from szurestipusok where id=?", array($this->szuresTipus)));
            $elsoIdopont           = [];

            if ($this->helyszin == 0) {
                echo json_encode(array("error" => "Az időpont kiválasztásához válassza ki a helyszínt!", "html" => ""));
                die;
            }
            if ($this->szuresTipus == 0) {
                echo json_encode(array("error" => "Az időpont kiválasztásához válassza ki a szűrés tipusát!", "html" => ""));
                die;
            }

            $this->packContentTypes = $this->getPackContentTypes($this->szuresTipus);
            if (count($this->getGenderPackContentTypes($this->szuresTipus)) != 0 && $this->neme == 0) {
                echo json_encode(array("error" => "Szűréscsomag választása esetén előbb adja meg a nemét!", "html" => ""));
                die;
            }

            if (!$rowmax = $this->getMinMax($this->szuresTipus, $this->packContentTypes)) {
                echo json_encode(array("error" => "Erre a szűrés típusra nincsenek beállítva rendelési időpontok.", "html" => ""));
                die;
            }

            //orvosválasztó
            $html.= $this->displayDoctorSelector();

            $html.= "<div style='display:inline-block;margin:10px 0px 10px 0px;'>";
            $html.= "<div>{$webText["valasszidopontot"]}:</div>";

            $html.= "<table style='margin-top:5px;width:100%;'><tr><td><a href='javascript:showIdoPontValasztoV2(".($this->honnan-7).")'>{$webText["elo7"]}</a></td><td align='right'><a href='javascript:showIdoPontValasztoV2(".($this->honnan+7).")'>{$webText["kov7"]}</a></td></tr></table>";

            $html.= "<table cellpadding='0' cellspacing='0'><tr>";

            //ennyi órán belül kell foglalni
            $dist = "6 hour";
            if ($this->helyszin == 1) {
                //jász utca bármikor foglalható
                $dist = "0 hour";
            }
            if (in_array($_SESSION["orvosselected"], [74])) {
                //74 - Dr. Kővári Gábor
                $dist = "24 hour";
            }

            //ennyi napon belül kell foglalni
            $distFullDay = "0 day";
            if ($_SESSION["orvosselected"] == 36) {
                //36 - dr Bodonyi Melinda
                $distFullDay = "2 day";
            }

            for ($i=0; $i<=6; $i++) {
                $fix = $i+$this->honnan;

                $nap = date("Y-m-d",strtotime("this week monday +{$fix} day"));
                $wd  = date("N",strtotime("this week monday +{$fix} day"));  //day of week

                $html.= "<td valign='top'>";
                $html.= "<div style='".($nap == date("Y-m-d")?"background:#405d5b;":"background:#607d8b;")."margin:8px 1px;padding:4px 10px 4px 10px;color:#fff;font-weight:bold;text-align:center;'>{$nap}<br/>{$webText["hetnap"][$wd]}</div>";

                if (!$napiBeos = $this->getBeosztasok("{$nap}",$this->helyszin,$this->szuresTipus,$_SESSION["orvosselected"])) {
                    $html.= "<div style='text-align:center;margin:5px;padding:5px 0px;color:#888;'>{$webText["nincsrendeles"]}</div>";
                    $html.= "</td>";
                    continue;
                }

                if (in_array($nap, $this->getSzunnapok())) {
                    $html.= "<div style='text-align:center;margin:5px;padding:5px 0px;color:#888;'>Munkaszüneti<br/>nap</div>";
                    $html.= "</td>";
                    continue;
                }

                //get binterval;
                $binterval = 15;
                foreach ($napiBeos as &$beoData) {
                    //ütköző beosztások is lehetnek - nincs kezelve!
                    $binterval = $beoData["binterval"];
                }

                $beginHour   = round(substr($rowmax["minrendeles"],0,2));
                $beginMinute = round(substr($rowmax["minrendeles"],3,2));

                $napHTML="";
                $napHTML.="<input type='hidden' id='rinterval-{$nap}' value='{$binterval}' />";

                $step = 0;
                $timeLoopEnd = false;
                while (!$timeLoopEnd) {
                    $ora = date("H:i",mktime($beginHour,$beginMinute+$step*$binterval,0,date("m"),date("d"),date("Y")));
                    if (strtotime($ora)>=strtotime($rowmax["maxrendeles"])) {
                        break;
                    }
                    $step ++;

                    $napHTML.="<div style='text-align:center;'>";

                    if (isset($beos)) {
                        unset($beos);
                    }

                    $numRendeles = 0;
                    $orvosNevek  = [];
                    $buttonTitle = "";
                    $buttonClass = "foglaltbtn";
                    $buttonJava  = "nemfog();return false;";

                    //beosztások beolvasása
                    if ($beos = $this->getBeosztasok("{$nap} {$ora}", $this->helyszin, $this->szuresTipus, $_SESSION["orvosselected"])) {
                        //szabad orvos kiválasztása
                        foreach ($beos as &$beoData) {
                            if ($this->orvosIdopontIsFree("{$nap} {$ora}", $beoData["orvosid"], $this->helyszin)) {
                                $numRendeles++;
                                $orvosNevek[] = $beoData["orvosnev"];
                                $buttonClass = "foglalhatobtn";
                                $buttonTitle = "{$numRendeles} hely (".implode(", ", $orvosNevek).")";
                                $buttonJava = "chooseIdoPont(\"{$nap} {$ora}\",{$_SESSION["orvosselected"]});return false;";
                                //break;
                            }
                        }
                    }

                    //csak sorban foglalható időpontok intézése
                    if ($beoData["csaksorban"]==1 && isset($elsoIdopont[$nap]) && $buttonClass=="foglalhatobtn") {
                        $buttonJava="nemfogs(\"{$elsoIdopont[$nap]}\");return false;";
                        $buttonClass.=" halv";
                    }
                    if (!isset($elsoIdopont[$nap]) && $buttonClass=="foglalhatobtn") {
                        $elsoIdopont[$nap] = $ora;
                    }

                    //teszt: minden időpont foglalható
                    //$buttonJava="chooseIdoPont(\"{$nap} {$ora}\");return false;";

                    if (strtotime("now + {$dist}")>strtotime("{$nap} {$ora}")) {
                        //mégse foglalható, múltbéli dátum vagy túl közeli
                        $buttonTitle = "";
                        $buttonClass = "foglaltbtn";
                        $buttonJava  = "nemfog();return false;";
                    }

                    if (strtotime("now + {$distFullDay}") > strtotime("{$nap} 23:59:59")) {
                        //mégse foglalható, csak x napra előre foglalható
                        $buttonTitle = "";
                        $buttonClass = "foglaltbtn";
                        $buttonJava  = "nemfog();return false;";
                    }

                    $btn = "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a>";

                    //csak fordított sorrendben időpontok intézése
                    if ($beoData["csaksorban"]==2 && $buttonClass=="foglalhatobtn") {
                        $lastButton = $btn;
                        $buttonJava = "nemfogs2();return false;";
                        $buttonClass.=" halv";
                        $btn = "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a>";
                    }

                    //csomag override
                    if (!empty($this->packContentTypes)) {
                        $btn = "";
                        $availableData = $this->getPackageAvailabilityForDay($nap);
                        //$btn.= print_r($availableData, true);
                        if (empty($availableData["error"])) {
                            $buttonTitle = "";
                            $buttonClass = "foglalhatobtn";
                            $buttonJava = "chooseIdoPont(\"{$nap}\",{$_SESSION["orvosselected"]});return false;";
                        } else {
                            $buttonTitle = "";
                            $buttonClass = "foglaltbtn";
                            $buttonJava  = "nemfog();return false;";
                        }
                        $ora = "{$rowmax["minrendeles"]} ~ {$rowmax["maxrendeles"]}";
                        $timeLoopEnd = true;
                        $btn.= "<a class='{$buttonClass}' title='{$buttonTitle}' onclick='{$buttonJava}' href='#'>{$ora}</a><br/>";
                        $btn.= "<div style='font-size:11px;width:100px;'>{$availableData["error"]}</div>";
                        //$btn.= implode(",",$this->packContentTypes);
                        //$btn.= print_r($availableData, true);
                    }

                    $napHTML.=$btn;
                    $napHTML.="</div>";
                }

                if (isset($lastButton)) {
                    $napHTML=str_replace($btn, $lastButton, $napHTML);
                    unset($lastButton);
                }

                $html.= $napHTML;
                $html.= "</td>";
            }

            $html.= "</tr></table>";
            $html.= "</div>";

            echo json_encode(array("error" => "","html" => $html));
            die;
        }



        if (isset($_POST["checkrendeles"])) {
            header("Cache-Control: no-cache, no-store, must-revalidate");
            header("Pragma: no-cache");
            header("Expires: 0");

            $this->helyszin    = intval($_POST["helyszin"]);
            $this->szuresTipus = intval($_POST["szurestipusid"]);

            if (!$odata = $this->selectOrvosForIdopont($_POST["idopont"], $_POST["orvos"])) {
                die("Ezt az időpontot időközben lefoglalták!");
            }

            if ($odata["onlytel"]==1 && $odata["tel"]!="") {
                echo "Erre a rendelésre az online bejelentkezés jelenleg nem üzemel kérjük jelentkezzen be ezen a telefon számon: ".$odata["tel"];
                die();
            }

            $statement = $_SERVER['REQUEST_URI'];
            if (isset( $_REQUEST['version'] ) && $_REQUEST['version'] == "2" ) {
                if( $statement == "/index.php?page=welcome" || ( $statement == "/" && $_SESSION["helyszindata"]["id"] == 11 )) echo "ok3";
                if( $statement == "/index.php?page=idopontfoglalas" ) echo "ok2";
                if( $statement == "/index.php" ) echo "ok3";
            } else {
                echo "ok";
            }
            die();
        }


    }


    private function getMinMax($szuresTipus, $packContentTypes = []) {
        $typeWhere = "instr(tipusok, '|{$szuresTipus}|')";
        return sql_fetch_array(sql_query("SELECT MIN(tol) as minrendeles,MAX(ig) as maxrendeles FROM orvos_beosztas WHERE helyszinid=? and cegid=? and ({$typeWhere}) and aktiv=1 HAVING MAX(tol) IS NOT NULL",array($this->helyszin, $_SESSION["helyszindata"]["id"])));
    }

    private function getMinMaxPack($szuresTipus, $nap) {
        $typeWhere = "instr(tipusok, '|{$szuresTipus}|')";

        return sql_fetch_array(sql_query("SELECT MIN(tol) as minrendeles,MAX(ig) as maxrendeles 
        FROM orvos_beosztas b
        WHERE helyszinid=? and cegid=? and ({$typeWhere}) and aktiv=1
        AND (nap=WEEKDAY('{$nap}')+1 or beonap='{$nap}')  
		AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2)) 
        HAVING MAX(tol) IS NOT NULL",array($this->helyszin, $_SESSION["helyszindata"]["id"])));
    }

    public function getPackageAvailabilityForDay($day) {
        $vanFixError = false;
        $error = "";
        $timeTableForPackage = [];

        $checkForTypes = $this->packContentTypes;
        $checkForTypes[] = $this->szuresTipus; //csekkoljuk magát csomagot is, hogy van-e benne hely

        if (empty($this->szuresTipusMap)) {
            $res = sql_query("select * from szurestipusok");
            while ($row = sql_fetch_array($res)) {
                $this->szuresTipusMap[$row["id"]] = $row;
            }
        }

        foreach ($checkForTypes as $packTypeId) {
            if ($beos = $this->getBeosztasok("{$day}", $this->helyszin, $packTypeId)) {
                foreach ($beos as &$beoData) {
                    $interval    = $beoData["binterval"];
                    $step        = 0;
                    $beoMinMax   = $this->getMinMaxPack($packTypeId, $day);
                    $beginHour   = intval(substr($beoMinMax["minrendeles"],0,2));
                    $beginMinute = intval(substr($beoMinMax["minrendeles"],3,2));

                    while (true) {
                        if ($beoData["nap"] == 10 & $day != $beoData["beonap"]) {
                            break;
                        }

                        $ora = date("H:i", mktime($beginHour, $beginMinute + $step * $interval, 0, date("m"), date("d"), date("Y")));
                        if (strtotime($ora) >= strtotime($beoMinMax["maxrendeles"])) {
                            break;
                        }
                        $step++;

                        if ($this->orvosIdopontIsFree("{$day} {$ora}", $beoData["orvosid"], $this->helyszin)) {
                            $timeTableForPackage[$packTypeId] = ["idopont" => "{$day} {$ora}", "orvosid" => $beoData["orvosid"]];
                            break 2;
                        }
                    }
                }
            }

            if (!isset($timeTableForPackage[$packTypeId]) && $packTypeId == $this->szuresTipus) {
                $error = "Erre a napra elfogytak az időpontok!";
                $vanFixError = true;
            }

            if (!isset($timeTableForPackage[$packTypeId]) && !$vanFixError) {
                $text = "nincs időpont:<br/>";
                if (substr_count($error, $text) == 0) {
                    $error.= $text;
                }
                $error.="{$this->szuresTipusMap[$packTypeId]["megnev"]}<br/>";
            }
        }

        if (count($timeTableForPackage) < count($this->packContentTypes)) {
            //$error = "Nincs időpont erre a napra!";
        }

        if (strtotime("now")>strtotime("{$day} 00:00:00")) {
            $error= "Erre a napra már nem lehet foglalni<br/>";
        }

        return ["error" => $error, "timeTableForPackage" => $timeTableForPackage];
    }

    public function selectOrvosForIdopont($idopont, $orvos=0) {
        $nap   = substr($idopont,0,10);
        $ora   = substr($idopont,11,5);
        $cegid = $_SESSION["helyszindata"]["id"];
        if (!$this->szuresTipusData = sql_fetch_array(sql_query("select * from szurestipusok where id=?", array($this->szuresTipus)))) {
            return false;
        }

        if ($this->szuresTipusData["ispack"] == 1) {
            $this->packContentTypes = $this->getPackContentTypes($this->szuresTipus);

            $packTimeData = $this->getPackageAvailabilityForDay($nap);
            if (empty($packTimeData["error"])) {
                return array("onlytel" => 0, "tel" => "");
            } else {
                return false;
            }
        }

        //időpontra beosztott orvosok kiolvasása
        $resb=sql_query("SELECT * FROM orvos_beosztas b 
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		WHERE b.`helyszinid`=? and (b.cegid=? or b.cegid=0) AND (nap=WEEKDAY(?)+1 or beonap=?) AND TIME(tol)<=TIME(?) AND TIME(ig)>TIME(?) AND INSTR(b.tipusok,?) ".($orvos==0?"":"and b.orvosid='{$orvos}'")." and b.aktiv=1 
		ORDER BY o.onlytel,b.cegid DESC,o.id", array($this->helyszin, $cegid, $nap, $nap, $ora, $ora, "|{$this->szuresTipus}|"));

        while ($rowb=sql_fetch_array($resb)) {
            //orvos foglalt-e?
            if (!sql_fetch_array(sql_query("SELECT datum FROM foglalasok WHERE datum=? AND cegid=? AND helyszinid=? AND orvosassigned=?", array($idopont, $cegid, $this->helyszin, $rowb["orvosid"])))) {
                //nap foglalt-e
                if (!sql_fetch_array(sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap=? and (szurestipusid=0 or szurestipusid=?)",array($this->helyszin, $cegid, $nap, $this->szuresTipus)))) {
                    //orvos szabad ->
                    if (!sql_fetch_array(sql_query("select * from szabadsag where oid=? and datumtol<=? and datumig>=?", array($rowb["orvosid"], $nap, $nap)))) {
                        //+nincs szabadságon
                        return $rowb;
                    }
                }
            }
        }
        return false;
    }


    private function getSzunnapok() {
        $szunnapok[]="";
        $rows=sql_fetch_array(sql_query("select * from settings"));
        $n=explode(",",$rows["szunnapok"]);
        for ($i=0;$i<count($n);$i++) {
            $szunnapok[]=trim($n[$i]);
        }
        return $szunnapok;
    }

    private function displayDoctorSelector() {
        $webText = $this->lang->webText;
        $html = "";

        if (!isset($_SESSION["orvosselected"])) {
            $_SESSION["orvosselected"]=0;
        }

        $orvosAvailable = [];
        $res=sql_query("select * from orvos_beosztas b 
                                left join orvosok o on o.id=b.orvosid 
                                where b.helyszinid=? 
                                and instr(b.tipusok,?) 
                                and	b.cegid = ? 
                                and (nap<10 or b.beonap >= date(now())) 
                                and b.aktiv=1 
                                and o.aktiv=1",array($this->helyszin, "|{$this->szuresTipus}|", $_SESSION['helyszindata']['id']));
        while ($beoData = sql_fetch_array($res)) {
            if ($beoData["csaksorban"]!=0) {
                $vanCsakSorban = true;
            }
            $orvosAvailable[$beoData["orvosid"]] = $beoData;
        }

        if (isset($_REQUEST["selectoid"]) && $_REQUEST["selectoid"] != 0) {
            $_SESSION["orvosselected"] = $_REQUEST["selectoid"];
        }

        //feltétel ami alapján kirakjuk az orvosválasztót
        if (count($orvosAvailable)>1 && $_SESSION["helyszindata"]["no_doctor_select"] == 0) {
            $html.= "<div style='margin:10px 0px 10px 0px;'>{$webText["valasszorvost"]}:</div>";
            foreach ($orvosAvailable as $orvosData) {
                $s="border:1px solid #fff;";
                if ($orvosData["orvosid"]==$_SESSION["orvosselected"]) {
                    $s="border:1px solid #080;";
                    $orvosIsSelected=true;
                }
                $html.= "<div style='display:inline-block;'><a href='#' onclick='showIdoPontValasztoV2({$this->honnan},{$orvosData["orvosid"]});return false;' style='display:inline-block;padding:3px;color:#080;{$s}'>{$orvosData["nev"]}</a></div>";
            }
            $html.= "<br clear='all'/>";
            if (!isset($orvosIsSelected)) {
                echo json_encode(array("error" => "","html" => $html));
                die;
            }
        }

        if (count($orvosAvailable)==1) {
            $data = current($orvosAvailable);
            //print_r($data);
            $_SESSION["orvosselected"] = $data["orvosid"];
        }
        return $html;
    }


    private function getPackContentTypes($szuresTipusId) {
        $types = [];
        if ($this->szuresTipusData["ispack"] == 1) {
            $res = sql_query("select * from szurescsomagok_kapcs where csomagid=?", array($szuresTipusId));
            while ($row = sql_fetch_array($res)) {
                if ($row["nemerequired"] == 0 || $row["nemerequired"] == $this->neme) {
                    $types[] = $row["szurestipusid"];
                }
            }
        }
        return $types;
    }

    private function getGenderPackContentTypes($szuresTipusId) {
        $types = [];
        if ($this->szuresTipusData["ispack"] == 1) {
            $res = sql_query("select * from szurescsomagok_kapcs where csomagid=?", array($szuresTipusId));
            while ($row = sql_fetch_array($res)) {
                if ($row["nemerequired"] != 0 && $row["nemerequired"] != $this->neme) {
                    $types[] = $row["szurestipusid"];
                }
            }
        }
        return $types;
    }

    public function selectFreeOrvosForIdopont($fid) {
        $oid = 0;

        if ($foglalasData = sql_fetch_array(sql_query("select * from foglalasok where id=?",array($fid)))) {
            $idopont = date("Y-m-d H:i",strtotime($foglalasData["datum"]));
            if ($foglalasData["orvosassigned"]!=0) {
                $oid = $foglalasData["orvosassigned"];
            } else {
                if ($beos = $this->getBeosztasok($idopont, $foglalasData["helyszinid"], $foglalasData["szurestipusid"])) {
                    //print_r($beos);
                    //szabad orvos kiválasztása
                    foreach ($beos as &$beoData) {
                        if ($this->orvosIdopontIsFree($idopont, $beoData["orvosid"])) {
                            if ($foglalasData["rinterval"] != 0 && $foglalasData["rinterval"] != $beoData["binterval"]) {
                                //ha intervallum is van a foglaláshoz, azt is csekkoljuk
                                continue;
                            }
                            $oid = $beoData["orvosid"];
                            break;
                        }
                    }
                }
            }
        }
        return $oid;
    }

    public function orvosIdopontIsFree($idoPont, $orvosId, $helyszin=0) {
        $nap  = substr($idoPont,0,10);
        $free = false;

        $wadd = "";
        if ($helyszin!=0) {
            $wadd="or (helyszinid='{$helyszin}' and cegid=0 and orvosassigned=0)";
        }

        if (!sql_fetch_array(sql_query("SELECT datum FROM foglalasok WHERE datum=? AND (orvosassigned=? {$wadd})",array($idoPont.":00", $orvosId)))) {
            if (!sql_fetch_array(sql_query("select * from szabadsag where oid=? and datumtol<=? and datumig>=?",array($orvosId, $nap, $nap)))) {
                $free = true;
            }
        }
        return $free;
    }

    public function getBeosztasok($idoPont, $helyszin, $szuresTipus, $orvos=0) {
        $nap         = substr($idoPont,0,10);
        $ora         = substr($idoPont,11,5);
        $helyszin    = intval($helyszin);
        $szuresTipus = intval($szuresTipus);
        $cegId       = $_SESSION["helyszindata"]["id"];
        if (isset($_SESSION["helyszinceg"]) && isset($GLOBALS["admin"])) {
            $cegId = $_SESSION["helyszinceg"];
        }

        $wora = $wceg = "";
        if (!empty($ora)) {
            $wora = "AND TIME(tol)<=TIME('{$ora}') AND TIME(ig)>TIME('{$ora}')";
        }

        //admin esetén lazább szűrés
        if (isset($GLOBALS["admin"])) {
            if ($_SESSION["adminuser"]["jogosultsag"] < 2) {
                $wceg = "and (b.cegid='{$cegId}' or b.cegid=0)";
            }
        } else {
            $wceg = "and (b.cegid='{$cegId}' or b.cegid=0)";
        }

        //időpontra beosztott orvosok kiolvasása
        $resb = sql_query("SELECT b.*,o.id as orvosid,o.nev as orvosnev,o.onlytel,c.megnev as cegnev FROM orvos_beosztas b 
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		left join cegek c on c.id=b.cegid
		WHERE b.`helyszinid`='{$helyszin}' {$wceg} AND (nap=WEEKDAY('{$nap}')+1 or beonap='{$nap}') {$wora} AND INSTR(b.tipusok,'|{$szuresTipus}|') 
		AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2) OR (WEEK('{$nap}',3)%2=1 AND b.hetek=1)) and b.aktiv=1
		ORDER BY b.cegid<>'{$cegId}',o.nev,o.onlytel,b.cegid DESC,o.id");

        while ($rowb = sql_fetch_array($resb)) {
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



    public function isOrvosAvailable($idopont, $helyszinid, $szurestipusid) {
        $result["status"] = "ok";
        $result["error"]  = "";
        $result["doctors"]  = [];
        $nap              = substr($idopont,0,10);
        $ora              = substr($idopont,11,5);
        $helyszinid       = intval($helyszinid);
        $cegid            = $_SESSION["helyszindata"]["id"];

        //időpontra beosztott számának megállapítása
        $resb = sql_query("SELECT b.orvosid, o.* FROM orvos_beosztas b left join orvosok o on o.id = b.orvosid WHERE b.`helyszinid`='{$helyszinid}' AND (nap=WEEKDAY('{$nap}')+1 or beonap='{$nap}') AND TIME(tol)<=TIME('{$ora}') AND TIME(ig)>TIME('{$ora}') AND INSTR(b.tipusok,'|".intval($szurestipusid)."|') and b.aktiv=1 GROUP BY b.orvosid");
        while ($rowb = sql_fetch_array($resb)) {
            //nap foglalt-e?
            if (!sql_fetch_array(sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap=? and (szurestipusid=0 or szurestipusid=?)",array($helyszinid, $cegid, $nap, $szurestipusid)))) {
                //orvos nincs szabadságon?
                if (!sql_fetch_array(sql_query("select * from szabadsag where oid='{$rowb["orvosid"]}' and datumtol<='{$nap}' and datumig>='{$nap}'"))) {
                    $result["doctors"][] = $rowb;
                }
            }
        }

        $foglalasok = sql_fetch_array(sql_query("SELECT count(*) as hany FROM foglalasok WHERE datum=? AND helyszinid=? and szurestipusid=?",array($idopont, $helyszinid, $szurestipusid)));
        if ($foglalasok["hany"] >= count($result["doctors"])) {
            $result["status"] = "notavailable";
            $result["error"]  = "Nincs szabad orvos a megjelölt időpontra!";
        }
        return $result;
    }


    public function szuresTipusValasztoNew($helyszinid, $selected=0, $onlyselected=0) {
        $tipusok = array();

        $rest = sql_query("select * from szurestipusok");
        while ($rowt = sql_fetch_array($rest)) {
            if ($_COOKIE["lang"]!="hu" && trim($rowt["megnev_{$_COOKIE["lang"]}"])!="") {
                $rowt["megnev"] = $rowt["megnev_{$_COOKIE["lang"]}"];
            }
            $tipusnevek[$rowt["id"]] = $rowt["megnev"];
        }

        $addJava = "";
        if ($_SESSION["helyszindata"]["id"] == 11) {
            $addJava = "if (this.value==1) { $(\"#fogleuwarn\").show(); } else { $(\"#fogleuwarn\").hide(); }";
        }
        $megjBox = "if(this.value==14 || this.value==65){ $(\"#borgyogystuff\").css(\"visibility\",\"visible\") } else{ $(\"#borgyogystuff\").css(\"visibility\",\"hidden\") }";
        $htmlout="";
        $htmlout.="<select name='szurestipus' id='szurestipus' onchange='clearIdopontValaszto();showTipusMegj(this.value);{$megjBox};{$addJava}'>";
        $htmlout.="<option value='0'>".$this->lang->webText["valasszon"]."!</option>";

        $res=sql_query("SELECT tipusok FROM orvos_beosztas b WHERE b.helyszinid='".addslashes($helyszinid)."' AND b.cegid='{$_SESSION["helyszindata"]["id"]}'");
        while ($row = sql_fetch_array($res)) {
            $ta = explode("|",$row["tipusok"]);
            for ($i=0;$i<count($ta);$i++) {
                if (trim($ta[$i])!="" && !in_array($ta[$i],$tipusok)) {
                    $tipusok[] = $ta[$i];
                }
            }
        }

        if (isset($tipusok)) {
            for ($i=0;$i<count($tipusok);$i++) {
                @$tipusdisplay[$tipusok[$i]] = $tipusnevek[$tipusok[$i]];
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

        $htmlout.="</select><div id='borgyogystuff' style='display: inline-block; visibility: hidden;margin-left:10px;padding:3px;background-color:#e13030;color:white;font-weight:bold'>Eltávoltításra is szükség van <input type='checkbox' style='' onChange='$(\"#foglmegj\").text(\"Eltávolításra is szükség van, VISSZAHÍVÁST KÉREK!\")' name = 'eltavolitas' value = 'szukseges'/></div>";

        if (trim($helyszinid)=="" || $helyszinid==0) $htmlout="Válassz előbb helyszínt!<input type='hidden' name='szurestipus' value='' />";

        return $htmlout;
    }


    public function getTipusMegj($cegid, $tid, $helyszinId = 1)
    {
        $h = "";
        if ($row = sql_fetch_array(sql_query("select * from szurestipusok_megj where cegid='" . intval($cegid) . "' and tipusid='" . intval($tid) . "' and csomag=0"))) {
            if (trim($row["megj"]) != "") $h .= "<div style='background:#f00;color:#fff;padding:10px;display:inline-block;font-weight:bold;'>" . trim($row["megj"]) . "</div>";
        }


        $res = sql_query("SELECT o.* FROM orvos_beosztas b 
        LEFT JOIN orvosok o ON o.id=b.`orvosid`
        WHERE cegid=? AND INSTR(b.`tipusok`,'|" . intval($tid) . "|') AND o.`tel`<>'' and o.telpublic=1 and b.helyszinid=?
        GROUP BY b.`orvosid`", array($cegid, $helyszinId));

        if (sql_num_rows($res) > 0) {
            $h .= "<div style='margin:10px 0px;'>";
            $h .= "<div style='font-weight:bold;'>Elérhetőségek:</div>";
            while ($row = sql_fetch_array($res)) {
                $h .= "<div>Telefonos időpontfoglalás: {$row["tel"]}</div>";
            }
            $h .= "</div>";
        }

        if ($helyszinId == 1 && $_SERVER["REMOTE_ADDR"] == "88.151.97.121") {
            $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and trim(megnev)<>'' and csomag=0", array("|{$cegid}|", $tid));
            if (sql_num_rows($res) > 0) {
                $h .= "<div style='margin:10px 0px;'>";
                $h .= "<div style='font-weight:bold;'>Ha kér, válasszon kiegészítő szolgáltatást:</div>";
                while ($row = sql_fetch_array($res)) {
                    //if ($_COOKIE["lang"]!="hu" && trim($row["megnev_{$_COOKIE["lang"]}"])!="") $row["megnev"]=$row["megnev_{$_COOKIE["lang"]}"];
                    $h .= "<div><input type='checkbox' name='altipus{$row["id"]}' value='1' " . (isset($_POST["altipus{$row["id"]}"]) ? "checked" : "") . " /> {$row["megnev"]}</div>";
                }
                $h .= "</div>";
            }
        }
        if ($_SESSION['helyszindata']['tudoszuroopcio'] == 1 && $helyszinId == 1 && $tid == 1) {
            $h .= "<div><input type='checkbox' name = 'tudoszuro' value = '1' />Tüdőszűrővel nem rendelkezik</div>";
        }
        return $h;
    }

    public function checkIdopontSzabad($data) {
        //TODO: időpont szabadság vizsgálása még kell ide..
        //$_POST["datum"]
        //$_POST["helyszin"]
        //$_POST["szurestipus"]

        if ($this->selectOrvosForIdopont($data["datum"], $data["orvosselected"])) {
            return true;
        }
        return false;
    }

    public function updateFoglalasData($id) {
        $rInterval = 0;
        if (isset($_REQUEST["rinterval"])) $rInterval = intval($_REQUEST["rinterval"]);

        sql_query("UPDATE foglalasok SET pass=SHA1(CONCAT(id,regdatum,datum)), rinterval=? where id=? and pass=''",array($rInterval,$id));

        if (isset($_SESSION["filefix"])) {
            sql_query("update dokumentumok set foglalasid=?,sess='validated' where sess=?",array($id,$_SESSION["filefix"].session_id()));
        }
    }

    public function availableDoctorsForTime($nap,$ora,$beosztas) {
        foreach ($beosztas as &$beo) {
            if (strtotime(date("Y-m-d {$ora}")) >= strtotime(date("Y-m-d {$beo["tol"]}")) && strtotime(date("Y-m-d {$ora}")) < strtotime(date("Y-m-d {$beo["ig"]}"))) {
                if (!isset($doks) || !in_array($beo["orvosid"],$doks)) {
                    $szabinVan = false;
                    if (isset($GLOBALS["szabidata"][$beo["orvosid"]])) {
                        foreach ($GLOBALS["szabidata"][$beo["orvosid"]] as $orvosSzabi) {
                            if (strtotime(date("{$nap} {$ora}")) >= strtotime(date("{$orvosSzabi["datumtol"]} 00:00:00")) && strtotime(date("{$nap} {$ora}")) <= strtotime(date("{$orvosSzabi["datumig"]} 23:59:59"))) {
                                $szabinVan = true;
                            }
                        }
                    }
                    if (!$szabinVan) {
                        $doks[] = $beo["orvosid"];
                    }
                }
            }
        }

        if (!isset($doks)) {
            return false;
        }
        return $doks;
    }


    public function sendVisszaIgazolas($id)
    {
        //Visszaigazolás a foglalásról, megerősítés kérése
        $h = "cim";
        if ($_SESSION["helyszindata"]["nocim"] == 1) $h = "megnev";

        $res = sql_query("SELECT h.{$h} AS helyszin,sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id='{$id}'");
        if ($row = sql_fetch_array($res)) {
            if ($row["rlang"] == "en" && $row["szurestipus_en"] != "") $row["szurestipus"] = $row["szurestipus_en"];
            if ($row["rlang"] == "de" && $row["szurestipus_de"] != "") $row["szurestipus"] = $row["szurestipus_de"];

            $mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress($row["email"]);
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);

            $webTextLocal = $this->lang->getWebTexts($row["rlang"]);
            $t = iconv("UTF-8", "ISO-8859-2", $webTextLocal["mailtitleerositsdmeg"]);

            $mbody = "";

            if ($row["rlang"] == "hu") {
                $mbody = "<h2>Már majdnem kész!</h2>
                Ha nem erősíti meg <b>1 órán belül</b>, a foglalása automatikusan <b>törlődik.</b><br/>
                {$webTextLocal["nev"]}: {$row["nev"]}<br>
                {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
                <b>Időpont: {$row["datum"]}</b><br>
                {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
                {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
                <br/>
                Az időpont foglalásának megerősítéséhez <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingvalidate&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>kattintson ide</a><br>
                <br/>
                Üdvözlettel:<br>".Booking_Constants::COMPANY_NAME;
            }
            if ($row["rlang"] == "de") {
                $mbody = "<h2>Már majdnem kész!</h2>
                Ha nem erősíti meg <b>1 órán belül</b>, a foglalása automatikusan <b>törlődik.</b><br/>
                {$webTextLocal["nev"]}: {$row["nev"]}<br>
                {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
                <b>Időpont: {$row["datum"]}</b><br>
                {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
                {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
                <br/>
                Az időpont foglalásának megerősítéséhez <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingvalidate&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>kattintson ide</a><br>
                <br/>
                Üdvözlettel:<br>".Booking_Constants::COMPANY_NAME;
            }
            if ($row["rlang"] == "en") {
                $mbody = "<h2>Almost done!</h2>
                if you do not confirm <b>within 1 hour</b>, your reservation will be automatically <b>canceled</b>.<br/>
                {$webTextLocal["nev"]}: {$row["nev"]}<br>
                {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
                <b>Time: {$row["datum"]}</b><br>
                {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
                {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
                <br/>
                To confirm your reservation <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingvalidate&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>click here</a><br>
                <br/>
                Regards<br>".Booking_Constants::COMPANY_NAME;
            }

            $mail->Subject = $t;
            $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
            //$mail->AddAttachment("");
            $mail->Send();
        }
    }


    public function sendToUser($id)
    {
        //visszaigazoló levél a foglalás sikerességéről a felhasználónak

        $res = sql_query("SELECT " . $this->utils->cimLangQuery("helyszin") . ",sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail,c.domain 
        FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=? and f.userertesitve=0",array($id));

        if ($row = sql_fetch_array($res)) {
            if ($row["rlang"] == "en" && $row["szurestipus_en"] != "") $row["szurestipus"] = $row["szurestipus_en"];
            if ($row["rlang"] == "de" && $row["szurestipus_de"] != "") $row["szurestipus"] = $row["szurestipus_de"];

            $extraMsg = "";

            if ($result = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE id = '" . intval($row["paciensid"]) . "'"))) {
                if ((strtotime("now") - strtotime($result["regtime"])) < 3600) {
                    $c = explode(",", $row["domain"]);
                    $extraMsg = "A kiállított leleteit és dokumentumait a ".Booking_Constants::SITE_PROTOCOL."://{$c[0]}.".Booking_Constants::SITE_DOMAIN." oldalon a taj számával megtekintheti online.<br/>";
                }
            }

            $webTextLocal = $this->lang->getWebTexts($row["rlang"]);

            $packText = "";
            $rescs = sql_query("SELECT f.id,sz.* FROM foglalasok f
            LEFT JOIN szurestipusok sz ON sz.id=f.szurestipusid
            WHERE parentid=?", array($id));
            while ($rowcs = sql_fetch_array($rescs)) {
                if ($row["rlang"] == "en" && $rowcs["megnev_en"] != "") $rowcs["megnev"] = $rowcs["megnev_en"];
                if ($row["rlang"] == "de" && $rowcs["megnev_de"] != "") $rowcs["megnev"] = $rowcs["megnev_de"];
                if (empty($packText)) {
                    $packText.="<br/>Csomag tartalma:<br/>";
                }
                $packText.="{$rowcs["megnev"]}<br/>";
            }
            if (!empty($packText)) {
                $packText.="<br/>";
            }

            sql_query("update foglalasok set userertesitve=1 where id='{$id}'");

            $resv = sql_query("SELECT * FROM visszaigazolok WHERE cegid='{$row["cegid"]}' AND (orvosid='{$row["orvosassigned"]}' OR orvosid=0) AND (helyszinid='{$row["helyszinid"]}' OR helyszinid=0) AND TRIM(szoveg)<>''");

            $mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress($row["email"]);
            $mail->CharSet = "UTF-8";
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);

            $t = "{$webTextLocal["sikeresidopontreg"]}";

            $mbody = "";
            $mbody .= "<h1>{$row["datum"]} - {$row["helyszin"]}</h1>";
            $mbody .= "{$webTextLocal["nev"]}: {$row["nev"]}<br>";
            $mbody .= "{$webTextLocal["telefon"]}: {$row["telefon"]}<br><br>";
            $mbody .= "<b>{$webTextLocal["idopont"]}: {$row["datum"]}</b><br><br>";
            $mbody .= "{$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>";
            $mbody .= "{$packText}";
            $mbody .= "{$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>";

            while ($rowv = sql_fetch_array($resv)) {
                $maplink = "";
                if ($rowv["mapurl"] != "") $maplink = "<a href='{$rowv["mapurl"]}'>Az útvonal térképen megjelenítéséhez kattintson ide.</a>";
                $rowv["szoveg"] = str_replace("#maplink#", $maplink, $rowv["szoveg"]);
                $mbody .= "<hr>" . nl2br($rowv["szoveg"]);
            }

            $mbody .= "<hr>";

            if ($row["rlang"] == "hu") {
                $mbody .= "Ha törölni szeretné ezt a foglalását, kérjük kattintson a következő linkre: <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>időpont regisztráció törlése</a><br>";
                $mbody .= "Amennyiben módosítani szeretné a foglalását, abban az esetben először törölje a régi időpontját a fenti linken, utána pedig regisztrálja újra.<br>{$extraMsg}";
                $mbody .= "<br/>";
                $mbody .= "Üdvözlettel:<br>".Booking_Constants::COMPANY_NAME;
            }
            if ($row["rlang"] == "de") {
                $mbody .= "Wenn Sie möchten Diese Termin Reservierung Canceln, bitte drücken Sie an Ihre Brief <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>Die Termin Registration Canceln</a> LINK.<br>";
                $mbody .= "Wenn Sie möchten Ihre Reservierung Verändern ,bitte Streichen Sie aus den anderen Zeitpunkt, dannach registrieren bitte nochmal.<br>";
                $mbody .= "<br/>";
                $mbody .= "Üdvözlettel:<br>".Booking_Constants::COMPANY_NAME;
            }
            if ($row["rlang"] == "en") {
                $mbody .= "If you wish to cancel this appointment, please click on link: <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=bookingdelete&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>Cancellation of confirmed appointment</a><br>";
                $mbody .= "If you would like to modify your appointment, first cancel your old appointment then register it again.<br>";
                $mbody .= "<br/>";
                $mbody .= "Regards:<br>".Booking_Constants::COMPANY_NAME;
            }

            $mail->Subject = $t;
            //$mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
            $mail->Body = $mbody;
            //$mail->AddAttachment("");

            if (true) {
                $mail->addStringAttachment($this->getCalendarItem($row), 'foglalas.ics', 'base64', 'text/calendar');
            }

            $mail->Send();

        }
    }

    public function sendToCegAndOrvos($id, $force = 0, $test = 0) {
        if (Booking_Constants::IS_DEMO) {
            return;
        }

        $row = sql_fetch_array(sql_query("SELECT * FROM foglalasok f WHERE f.id=?",array($id)));

        if ($row["ertesitve"] == 1 && $force == 0) {
            return;
        }

        $fids[] = $id;
        $res = sql_query("select id from foglalasok where parentid=?", array($id));
        while ($row = sql_fetch_array($res)) {
            $fids[] = $row["id"];
        }

        //orvos kikeresése és értesítése
        $resf = sql_query("SELECT h.cim AS helyszin,sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail,c.calendaritem FROM foglalasok f
		LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
		LEFT JOIN cegek c on c.id=f.cegid
		LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
		WHERE f.id in (".implode(",", $fids).")");

        while ($rowf = sql_fetch_array($resf)) {
            $cegId = $rowf["cegid"];

            if ($rowo = sql_fetch_array(sql_query("select * from orvosok where id=?",array($rowf["orvosassigned"])))) {
                $resp = sql_query("select * from smsphones where orvosid=? and smsfoglalas=1 and smsgroupfoglalas=0 and instr(cegek,'|{$cegId}|')",array($rowo["id"]));
                while ($rowp = sql_fetch_array($resp)) {
                    if ($test == 1) {
                        $rowp["tel"] = "062099961833";
                    }
                    $this->utils->sendSMS(trim($rowp["tel"]),Booking_Constants::COMPANY_NAME_SHORT." időpont foglalása érkezett: ".substr($rowf["datum"],0,16)." {$rowf["helyszin"]}");
                }

                if (!empty(trim($rowo["email"]))) {
                    $mbody = "";

                    $mail = new PHPMailer();
                    $mail->FromName = Booking_Constants::COMPANY_NAME;
                    if ($test == 1) {
                        $mail->AddAddress("jns@jns.hu");
                    } else {
                        $mail->AddAddress($rowo["email"]);
                    }

                    if ($rowo["visszaigazol"]==1 && $rowo["visszaigazolemail"]!="") {
                        $mbody.="Kedves {$rowo["nev"]}!<br>
                            <br>
                            Foglalása érkezett a ".Booking_Constants::COMPANY_NAME_SHORT." foglalási rendszerén keresztül az alábbi adatokkal. Kérjük erre az levélre válaszolva jelezze, hogy tudja-e fogadni a pacienst. Köszönjük!<br>
                            <br>
                            <hr>
                            <br>";
                        $mail->From=$rowo["visszaigazolemail"];
                        $mail->AddReplyTo($rowo["visszaigazolemail"]);
                    } else {
                        $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
                        $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
                    }
                    $mail->IsHTML(true);

                    $t = iconv("UTF-8","ISO-8859-2","{$rowf["cegnev"]} - időpont regisztráció {$rowo["nev"]} részére");

                    $mbody.="Név: {$rowf["nev"]}<br>";
                    $mbody.="Cég: {$rowf["cegnev"]}<br>";
                    $mbody.="TAJ: {$rowf["taj"]}<br>";
                    $mbody.="Munkakor: {$rowf["munkakor"]}<br>";
                    $mbody.="Telefon: {$rowf["telefon"]}<br><br>";
                    $mbody.="<b>Időpont: {$rowf["datum"]}</b><br><br>";
                    $mbody.="Szűréstípus: {$rowf["szurestipus"]}<br>";
                    $mbody.="Helyszín: {$rowf["helyszin"]}<br>";
                    if ($rowf["megj"]!="") $mbody.="Megjegyzés: {$rowf["megj"]}<br>";
                    $mbody.="<br/>";

                    $mail->Subject=$t;
                    $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);

                    if (true) {
                        $mail->addStringAttachment($this->getCalendarItem($rowf),'foglalas.ics','base64','text/calendar');
                    }

                    $mail->Send();
                }

            }
        }

        $res=sql_query("SELECT o.`nev` AS orvosnev,o.`email` AS orvosemail,o.hmedemail,h.cim AS helyszin,sz.megnev AS szurestipus,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN cegek c on c.id=f.cegid
        LEFT JOIN orvosok o ON o.`id`=f.`orvosassigned`
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id='{$id}'");
        if ($row=sql_fetch_array($res)) {
            if ($row["foglalasemail"] == 1) {

                $packText = "";
                $rescs = sql_query("SELECT f.id,sz.* FROM foglalasok f
                    LEFT JOIN szurestipusok sz ON sz.id=f.szurestipusid
                    WHERE parentid=?", array($id));
                while ($rowcs = sql_fetch_array($rescs)) {
                    if ($row["rlang"] == "en" && $rowcs["megnev_en"] != "") $rowcs["megnev"] = $rowcs["megnev_en"];
                    if ($row["rlang"] == "de" && $rowcs["megnev_de"] != "") $rowcs["megnev"] = $rowcs["megnev_de"];
                    if (empty($packText)) {
                        $packText.="<br/>Csomag tartalma:<br/>";
                    }
                    $packText.="{$rowcs["megnev"]}<br/>";
                }
                if (!empty($packText)) {
                    $packText.="<br/>";
                }

                $mail = new PHPMailer();
                $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
                $mail->FromName = Booking_Constants::COMPANY_NAME;
                if ($test == 1) {
                    $mail->AddAddress("jns@jns.hu");
                } else {
                    $mail->AddAddress($row["cegemail"]);
                    if ($row["hmedemail"] != "") {
                        $mail->AddAddress($row["hmedemail"]);
                    }
                }
                $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
                $mail->IsHTML(true);

                $t = iconv("UTF-8","ISO-8859-2","{$row["cegnev"]} - időpont regisztráció");

                $mbody="Név: {$row["nev"]}<br>";
                $mbody.="Cég: {$row["cegnev"]}<br>";
                $mbody.="TAJ: {$row["taj"]}<br>";
                $mbody.="Munkakör: {$row["munkakor"]}<br>";
                $mbody.="Telefon: {$row["telefon"]}<br><br>";
                $mbody.="<b>Időpont: {$row["datum"]}</b><br><br>";
                $mbody.="Szűréstípus: {$row["szurestipus"]}<br>";
                $mbody.=$packText;
                $mbody.="Helyszín: {$row["helyszin"]}<br>";
                if ($row["megj"]!="") $mbody.="Megjegyzés: {$row["megj"]}<br>";
                $mbody.="<br/>";

                if ($row["orvosnev"]!="" && $row["orvosemail"]!="") $mbody.="Értesített orvos: {$row["orvosnev"]} ({$row["orvosemail"]})";

                $mail->Subject=$t;
                $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
                $mail->Send();
            }
        }

        sql_query("update foglalasok set ertesitve=1 where id='{$id}'");
    }

    private function getCalendarItem($foglalasData) {
        $webTextLocal = $this->lang->getWebTexts($foglalasData["rlang"]);

        $interval = (int)$foglalasData["rinterval"];
        if ($interval == 0) {
            $interval = 15;
        }
        $dateStart = date("Ymd",strtotime("{$foglalasData["datum"]} -0 hour"));
        $timeStart = date("His",strtotime("{$foglalasData["datum"]} -0 hour"));
        $dateEnd = date("Ymd",strtotime("{$foglalasData["datum"]} -0 hour + {$interval} minute"));
        $timeEnd = date("His",strtotime("{$foglalasData["datum"]} -0 hour + {$interval} minute"));

        $ical = "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//ical.marudot.com//iCal Event Maker
CALSCALE:GREGORIAN
BEGIN:VTIMEZONE
TZID:Europe/Berlin
TZURL:http://tzurl.org/zoneinfo-outlook/Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
DTSTAMP:".date("Ymd")."T".date("His")."Z
UID:".date("Ymd")."T".date("His")."Z-".$foglalasData["id"]."@marudot.com
DTSTART;TZID=Europe/Berlin:{$dateStart}T{$timeStart}
DTEND;TZID=Europe/Berlin:{$dateEnd}T{$timeEnd}
SUMMARY:{$webTextLocal["idopontfoglalas"]} - {$foglalasData["nev"]}
DESCRIPTION:{$foglalasData["szurestipus"]}
LOCATION:{$foglalasData["helyszin"]}
ORGANIZER;CN=\"Hungária Med - m Kft . \":mailto:info@hungariamed.hu
END:VEVENT
END:VCALENDAR";

        return $ical;
    }


    public function deleteReservation($id, $code)
    {
        if ($row = sql_fetch_array(sql_query("select id from foglalasok WHERE id=? and (pass=? or rkod=?) and datum>now() and eljott=0", array($id, $code, $code)))) {
            sql_query("update beutalok set foglalasid='0' where foglalasid=?", array($row["id"]));
            sql_query("delete from foglalasok WHERE id=?", array($row["id"]));
            sql_query("delete from foglalasok WHERE parentid=? and parentid<>0", array($row["id"]));
            sql_query("delete from fizkapcs where fid=?", array($row["id"]));
        }
        return;
    }


    public function addReservation($data) {
        $cegId = $_SESSION["helyszindata"]["id"];

        if (!isset($data["tudoszuro"])) {
            $data["tudoszuro"] = 0;
        }

        $rn = rand(1000000, 9999999);

        $paciensId = 0;

        if (isset($_SESSION["user"]["id"])) {
            $paciensId = intval($_SESSION["user"]["id"]);
        } else {
            if ($userInfo = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE (taj = ? OR email = ?) and cegid=?", array($data['taj'], $data['email'], $cegId)))) {
                $paciensId = $userInfo['id'];
            } else {
                sql_query("INSERT INTO felhasznalok SET validated=1, cegid=?, regtime=now(), taj = ?, email = ?, nev = ?, telefon = ?, munkakor = ?, irsz = ?, varos = ?, utca = ?, szulhely = ?, anyjaneve = ?, szuldatum = ? ", array($cegId, $data['taj'], $data['email'], $data['nev'], $data['tel'], $data['munkakor'], $data['irsz'], $data['varos'], $data['utca'], $data['szulhely'], $data['anyjaneve'], $data['szuldatum']));
                $paciensId = sql_insert_id();
            }
        }

        $data["paciensid"] = $paciensId;
        $data["rn"] = $rn;
        $data["aktiv"] = 0;
        $data["parentid"] = 0;
        $data["cegid"] = $cegId;
        $data["lang"] = $_COOKIE["lang"];
        $data["orvosid"] = 0;

        $fid = $this->addReservationQuery($data);

        $oid = $this->selectFreeOrvosForIdopont($fid);
        sql_query("update foglalasok set orvosassigned=? where id=?", array($oid, $fid));

        if (isset($_SESSION["beutaloid"]) && isset($_SESSION["user"]) && $rowb = sql_fetch_array(sql_query("select * from beutalok where id=?", array($_SESSION["beutaloid"])))) {
            sql_query("update beutalok set foglalasid=? where id=?", array($fid, $_SESSION["beutaloid"]));
            sql_query("update fogalalasok set megj=? where id=?", array($rowb["megj"], $fid));
            unset($_SESSION["beutaloid"]);
        }

        //altipusok tárolása
        $res = sql_query("select * from arak where instr(cegid,?) and tipusid=? and csomag=0", array("|{$_SESSION["helyszindata"]["id"]}|", $data["szurestipus"]));
        while ($row = sql_fetch_array($res)) {
            if (isset($data["altipus{$row["id"]}"])) {
                sql_query("insert into fizkapcs set fid=?,aid=?,megnev=?,ar=?,valuta=?", array($fid, $row["id"], $row["megnev"], $row["price"], $row["penznem"]));
            }
        }

        $this->addSubReservation($data, $fid);

        if (isset($_SESSION["remotebeutalo"]) || $_SESSION["helyszindata"]["visszaigazolas"] == 0) {
            //orvos jött, akkor nem kérünk visszaigazolást, megyünk visszaigazolni automatikusan
            $forwardURL = "index.php?page=bookingvalidate&id={$fid}&rk={$rn}";
        } else {
            //visszaigazolást kérünk
            $this->sendVisszaIgazolas($fid);
            $forwardURL = "index.php?page=bookingsuccessful";
        }

        return $forwardURL;
    }

    public function addSubReservation($data, $parentId) {
        if ($this->szuresTipusData["ispack"] == 1) {
            $map = $this->getPackageAvailabilityForDay($data["datum"]);

            $parentReservationData = sql_fetch_array(sql_query("select * from foglalasok where id=?",array($parentId)));

            foreach ($map["timeTableForPackage"] as $subTypeId => $subData) {
                if ($parentReservationData["szurestipusid"] == $subTypeId) {
                    //a parent tipus időpontját pontosítjuk
                    sql_query("update foglalasok set datum=?, orvosassigned=? where id=?", array($subData["idopont"], $subData["orvosid"], $parentId));
                    continue;
                }

                $data["datum"] = $subData["idopont"];
                $data["paciensid"] = $parentReservationData["paciensid"];
                $data["rn"] = rand(1000000, 9999999);
                $data["aktiv"] = $parentReservationData["aktiv"];
                $data["parentid"] = $parentId;
                $data["cegid"] = $parentReservationData["cegid"];
                $data["lang"] = $parentReservationData["rlang"];
                $data["orvosid"] = $subData["orvosid"];
                $data["szurestipus"] = $subTypeId;
                $data["rinterval"] = 0;

                $this->addReservationQuery($data);
            }
        }
    }

    public function addReservationQuery($data) {
        sql_query("insert into foglalasok set 
            regdatum=now(),
            parentid=?,
            paciensid=?,
            cegid=?,
            datum=?,
            rinterval=?,
            telephely=?,
            helyszinid=?,
            szurestipusid=?,
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
            megj=?,
            munkakor=?,
            tudoszuro=?,
            rlang=?,
            orvosassigned=?,
            aktiv=?,
            rkod=?"
        , array(
            $data["parentid"],
            $data["paciensid"],
            $data["cegid"],
            $data["datum"],
            intval($data["rinterval"]),
            $data["telephely"],
            $data["helyszin"],
            $data["szurestipus"],
            $data["nev"],
            $data["email"],
            $data["telefon"],
            $data["szuldatum"],
            $data["szulhely"],
            $data["anyjaneve"],
            $data["neme"],
            $data["taj"],
            $data["irsz"],
            $data["varos"],
            $data["utca"],
            $data["megj"],
            $data["munkakor"],
            $data["tudoszuro"],
            $data["lang"],
            $data["orvosid"],
            $data["aktiv"],
            $data["rn"]));

        $fid = sql_insert_id();
        $this->updateFoglalasData($fid);

        return $fid;
    }

    public function addIdoPont() {
        if (isset($_SESSION["helyszin"])) {
            $adminUtils = new AdminUtils();

            $szuresTipusId = intval($_GET["szt"]);
            $cegId = 0;
            $orvosId = 0;

            if ($adminUtils->isCegAdmin()) {
                $cegId = $_SESSION["adminuser"]["cegid"];
            }

            if ($adminUtils->isCegAdmin()) {
                $cegIds = explode("|",$_SESSION["adminuser"]["cegjog"]);
                if (isset($cegIds[1])) {
                    $cegId = intval($cegIds[1]);
                }
            }

            if ($_SESSION["adminuser"]["jog_nofoglimitset"]==0) {
                $orvosResult = $this->isOrvosAvailable($_GET["addidopont"], $_SESSION["helyszin"], $szuresTipusId);
                if ($orvosResult["status"] != "ok") {
                    $message = $orvosResult["error"];
                    if (!empty($orvosResult["doctors"])) {
                        $doctors = [];
                        foreach ($orvosResult["doctors"] as $doctor) {
                            $doctors[] = $doctor["nev"];
                        }
                        $message.= "\nElérhető orvosok:\n".implode(", ", $doctors);
                    }
                    echo "error{$message}";
                    die;
                }
            }

            $settings = new Booking_Settings();
            if (in_array(date("Y-m-d", strtotime($_GET["addidopont"])), $settings->getMunkaszunetiNapok())) {
                die("errorMunkaszüneti napra nem lehet foglalni!");
            }

            sql_query("insert into foglalasok set aktiv=1,foglalta=?,regdatum=now(),nev='nincs név',cegid=?,helyszinid=?,szurestipusid=?,orvosassigned=?,datum=?",array($_SESSION["adminuser"]["username"], $cegId, $_SESSION["helyszin"], $szuresTipusId, $orvosId, $_GET["addidopont"]));

            $fid = sql_insert_id();
            $this->updateFoglalasData($fid);

            logActivity("foglalas",$fid,"foglalás hozzáadása {$_GET["addidopont"]}",print_r($_POST,true));

            if ($orvosId==0 && $cegId!=0) {
                $oid = $this->selectFreeOrvosForIdopont($fid);
                //echo $oid;
                sql_query("update foglalasok set orvosassigned=? where id=? and orvosassigned=0",array($oid, $fid));
            }
        }
    }

    public function removeIdopont($id, $code) {
        //todo: kód bevezetése hiányzik innen
        if ($rowf = sql_fetch_array(sql_query("select * from foglalasok where id=? and pass=?",array($id, $code)))) {
            logActivity("foglalas", $rowf["id"], "{$rowf["nev"]} foglalás törlése {$rowf["datum"]}", print_r($_POST, true));
            $this->deleteReservation($id, $code);
        }
    }
}



