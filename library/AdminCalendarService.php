<?php

class AdminCalendarService {

    private $bookingService;
    private $adminUtils;

    public function __construct()
    {
        $this->bookingService = new BookingService();
        $this->adminUtils = new AdminUtils();

        if (isset($_GET["setnaptarszurestipus"])) {
            $_SESSION["naptarszurestipus"] = intval($_GET["setnaptarszurestipus"]);
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_POST["multifoglalstart"])) {
            $cegid = round($_SESSION["helyszinceg"]);
            for ($i=0;$i<$_POST["hanynapot"];$i++) {
                if ($i>30) {
                    break;
                }

                $nap = date("Y-m-d",strtotime("{$_GET["from"]} +{$i} day"));

                $szurestipus = 0;
                if (isset($_SESSION["naptarszurestipus"])) {
                    $szurestipus = $_SESSION["naptarszurestipus"];
                }

                if (!sql_fetch_array(sql_query("select nap from foglaltnapok where nap=? and helyszinceg=? and helyszinid=? and szurestipusid=?",array($nap, $cegid, $_SESSION["helyszin"], $szurestipus)))) {
                    sql_query("insert into foglaltnapok set foglalta=?,nap=?,helyszinceg=?,helyszinid=?,szurestipusid=?",array($_SESSION["adminuser"]["username"], $nap, $cegid, $_SESSION["helyszin"], $szurestipus));
                }
            }
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_POST["multifoglalcancel"])) {
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["enablenap"]) && isset($_SESSION["helyszin"])) {
            $cegid = intval($_SESSION["helyszinceg"]);
            $szurestipus = 0;
            if (isset($_SESSION["naptarszurestipus"])) {
                $szurestipus = $_SESSION["naptarszurestipus"];
            }

            sql_query("delete from foglaltnapok where nap=? and helyszinceg=? and helyszinid=? and szurestipusid=?",array($_GET["enablenap"], $cegid, $_SESSION["helyszin"], $szurestipus));
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["disablenap"]) && isset($_SESSION["helyszin"])) {
            $cegid = round($_SESSION["helyszinceg"]);
            $szurestipus = 0;
            if (isset($_SESSION["naptarszurestipus"])) {
                $szurestipus = $_SESSION["naptarszurestipus"];
            }
            sql_query("insert into foglaltnapok set foglalta=?,nap=?,helyszinceg=?,helyszinid=?,szurestipusid=?",array($_SESSION["adminuser"]["username"], $_GET["disablenap"], $cegid, $_SESSION["helyszin"], $szurestipus));
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["loadnaptar"])) {
            if (isset($_GET["shift"])) {
                $_SESSION["shift"]+=intval($_GET["shift"]);
            }

            echo $this->showAdminNaptar();
            die();
        }

        if (isset($_GET["addidopont"])) {
            $this->bookingService->addIdoPont();
            echo $this->showAdminNaptar();
            die();
        }

        if (isset($_GET["removeidopont"])) {
            $this->bookingService->removeIdopont($_GET["removeidopont"], $_GET["p"]);
            echo $this->showAdminNaptar();
            die();
        }
    }

    public function showAdminNaptar() {
        if (!isset($_SESSION["helyszin"]) || $_SESSION["helyszin"]==0) {
            return "";
        }

        $htmlout     = "";
        $shift       = intval($_SESSION["shift"]);
        $helyszin    = intval($_SESSION["helyszin"]);
        $helyszinceg = intval($_SESSION["helyszinceg"]);

        if ($_SESSION["naptarszurestipus"] != 0) {
            if ($row=sql_fetch_array(sql_query("select megnev from szurestipusok where id=?", array($_SESSION["naptarszurestipus"])))) {
                $_SESSION["naptarszurestipusnev"] = $row["megnev"];
            }
        }

        $foglaltidopontok[] = "";

        //el kell dönteni, hogy csak a cég foglaltjait mutassa, vagy az összes kiválasztott címre foglaltakat!
        //$res=sql_query("select datum,nev,eljott from foglalasok where helyszinid='{$helyszin}' and cegid='{$helyszinceg}' and aktiv=1");
        $wf = "";
        if ($_SESSION["naptarszurestipus"] != 0) {
            $wf.= " and szurestipusid='".intval($_SESSION["naptarszurestipus"])."'";
        }
        $res=sql_query("select datum,nev,eljott,cegid,orvosassigned,id,pass from foglalasok where helyszinid='{$helyszin}' and aktiv=1 {$wf}");
        while ($row=sql_fetch_array($res)) {
            $ido=substr($row["datum"],0,16);
            $foglaltData[$ido][]=$row;
        }

        //print_r($foglaltidopontok);

        $foglaltnapok[]="";
        $res=sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and (szurestipusid=0 or szurestipusid=?)",array($helyszin,$helyszinceg,$_SESSION["naptarszurestipus"]));
        while ($row=sql_fetch_array($res)) {
            $foglaltnapok[]=$row["nap"];
        }


        $szunnapok = [];
        $rows = sql_fetch_array(sql_query("select * from settings"));
        $n = explode(",",$rows["szunnapok"]);
        for ($i=0;$i<count($n);$i++) {
            $szunnapok[] = trim($n[$i]);
        }


        $resSzabi = sql_query("SELECT * FROM szabadsag WHERE datumtol>DATE_SUB(NOW(),INTERVAL 30 DAY)");
        while ($szData = sql_fetch_array($resSzabi)) {
            $GLOBALS["szabidata"][$szData["oid"]][] = $szData;
        }

        $htmlout.="<table border='0' cellpadding='0' cellspacing='0'><tr>";

        for ($i=0; $i<7; $i++) {
            $dd = $i+$shift;

            $firstDay   = strtotime("this week monday +{$dd} day");
            $nap        = date("Y-m-d", $firstDay);
            $wd         = date("N", $firstDay); //day of week
            $month      = date("n", $firstDay);
            $dayOfMonth = date("j", $firstDay);
            $year       = date("Y", $firstDay);

            $napDisplay = "<div style='font-size:16px;font-weight:bold;'>".$this->adminUtils->settings->hetnap[$wd]."</div>";
            if (date("Y") != $year) {
                $napDisplay = $year.". ";
            }
            $napDisplay.= $this->adminUtils->settings->honaptext[$month];
            $napDisplay.= "<br clear='all'/><div class='calendarday'>{$dayOfMonth}</div>";

            $hClass = "calendardayheader";
            if ($nap == date("Y-m-d")) {
                $hClass = "calendardayheadertoday";
            }

            if (in_array($nap, $foglaltnapok)) {
                //ha foglalt
            }

            $htmlout.= "<td valign='top'><div style='padding:0px 2px;border-left:1px solid #ccc;'></div></td>";
            $htmlout.= "<td valign='top' sytle=''>";

            $htmlout.= "<div class='{$hClass}'>{$napDisplay}</div>";


            if (in_array($nap, $foglaltnapok)) {
                $htmlout.= "<div style='text-align:center;'>erre a napra<br>foglalás tiltva</div>";
                $htmlout.= "<div style='text-align:center;margin-bottom:10px;'><a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&enablenap=".urlencode("{$nap}")."'>engedélyezés</a></div>";
            } else {
                $htmlout.= "<div style='text-align:center;margin-bottom:10px;'><a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&disablenap=".urlencode("{$nap}")."'>nap tiltása</a></div>";
            }



            $minrendeles = 0;
            $maxrendeles = 0;
            if (isset($beosztasData)) {
                unset($beosztasData);
            }
            if ($beoData = $this->bookingService->getBeosztasok($nap, $helyszin, $_SESSION["naptarszurestipus"])) {
                foreach ($beoData as &$beo) {
                    if ($_SESSION["adminuser"]["jogosultsag"]<2 && substr_count($_SESSION["adminuser"]["cegjog"],"|{$beo["cegid"]}|")==0) {
                        continue;
                    }
                    if (strtotime($beo["tol"])<strtotime($minrendeles) || $minrendeles==0) {
                        $minrendeles = $beo["tol"];
                    }
                    if (strtotime($beo["ig"])>strtotime($maxrendeles) || $maxrendeles==0) {
                        $maxrendeles = $beo["ig"];
                    }

                    if ($beo["nap"] == 10) {
                        $beosztasData[$beo["beonap"]][] = $beo;
                    } else {
                        $beosztasData[$beo["nap"]][] = $beo;
                    }
                }
            } else {
                $htmlout.="<div style='text-align:center;padding:0px;'>Nincs<br/>rendelés</div>";
                $htmlout.="</td>";
                continue;
            }

            if (isset($beosztasData[$nap])) {
                $beosztasData[$wd][] = $beosztasData[$nap][0];
            }

            if (in_array($nap,$szunnapok)) {
                $htmlout.="<div style='text-align:center;'>Munkaszüneti<br/>nap!</div>";
                $htmlout.="</td>";
                continue;
            }

            $binterval = $beosztasData[$wd][0]["binterval"];

            for ($o=0; $o<=555; $o++) {
                $diff = $o*$binterval;
                $ora = date("H:i",strtotime("{$nap} {$minrendeles}+{$diff} minute"));

                if (strtotime($ora)>=strtotime($maxrendeles)) {
                    break;
                }

                $java = "setSelectedInterval({$binterval});addIdopontNaptar('{$nap} {$ora}',{$_SESSION["naptarszurestipus"]});return false;";
                $class = "nfb2";
                $title = "";
                $reservedPercent = "";

                if (isset($beosztasData[$wd][0]["binterval"])) {
                    if ($dokik = $this->bookingService->availableDoctorsForTime($nap, $ora, $beosztasData[$wd])) {
                        $class = "fhb2";
                        if (isset($foglaltData["{$nap} {$ora}"])) {
                            $class = "fb2";
                            foreach ($foglaltData["{$nap} {$ora}"] as $foglalasData) {
                                if ($foglalasData["cegid"] == 0 && $foglalasData["orvosassigned"] == 0) {
                                    $foglalasData["nev"] = "foglalt";
                                }
                                $title.= "<div><a class='calendaritemlink' href='#' onclick=\"showIdopontEditor('calendar','{$foglalasData["pass"]}',{$foglalasData["id"]});return false;\">{$foglalasData["nev"]}</a></div>";
                            }
                        }
                    }
                }

                $id = str_replace(array("-",":"),"","ipbox{$nap}{$ora}");
                $htmlout.="<div id='{$id}' class='ipcell'>";

                //$htmlout.="<a class='{$class}' onclick=\"{$java}\" href='#' title='{$title}'>{$ora}</a>";

                $reservationButton ="<a title='időpont lefoglalása' class='fi' onclick=\"{$java}\" href='#'>+</a>";

                if ($class == "fb2") {
                    if ($title == "foglalt") {
                        $reservedPercent = "fo";
                    } else {
                        $reservedPercent = count($dokik)."/".count($foglaltData["{$nap} {$ora}"]);
                    }
                }

                $htmlout.="<div class='".(empty($title)?"freecell":"reservedcell")."'>";
                $htmlout.="<div style='display:table;width:100%;;'>";
                $htmlout.="<div style='display:table-cell;font-size:14px;width:40px;font-weight:bold;'>{$ora}</div>";
                $htmlout.="<div style='display:table-cell;font-size:14px;'>{$reservedPercent}</div>";
                $htmlout.="<div style='display:table-cell;text-align:right;'>{$reservationButton}</div>";
                $htmlout.="</div>";

                $htmlout.="<div>{$title}</div>";
                $htmlout.="</div>";

                $htmlout.="</div>";
            }


            //todo: checkolni, hogy működik-e
            /*
            if (isOrvosLogin()) {
                $htmlout.="<div style='margin:10px 0px 0px 20px;'>";
                $htmlout.="<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&tobbnapfoglal&from=".urlencode("{$nap}")."' title='több nap foglalása'>F+</a>";
                $htmlout.="</div>";
            }
            */

            $htmlout.="</td>";

        }
        $htmlout.="</tr></table>";
        return $htmlout;
    }




}