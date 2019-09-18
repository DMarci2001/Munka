<?php

class AdminUtils {
    public $settings;
    public $leletService;
    public $protocolService;

    public function __construct()
    {
        $this->settings = new Booking_Settings();
        $this->leletService = new AdminLeletService();
        $this->protocolService = new AdminProtocolService();

        if (isset($_POST["scancel"])) {
            header("location:index.php?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["setcegfilter"])) {
            $_SESSION["cegfilter"]=$_GET["setcegfilter"];
            $_SESSION["kereskulcs"]="";
            header("location:index.php?page={$_GET["p"]}");
            die();
        }

        if (isset($_GET["addnew"])) {
            if ($_GET["page"]=="companies" && $this->cegModJog()) {
                sql_query("insert into cegek set megnev='Új cég'");
            }
            if ($_GET["page"]=="places" && $this->helyszinModJog()) {
                sql_query("insert into helyszinek set cim='Új helyszín'");
            }
            if ($_GET["page"]=="doctors" && $this->orvosModJog()) {
                sql_query("insert into orvosok set nev='Új orvos',createdby=?, created=now()", array($_SESSION["adminuser"]["nev"]));
                $oid = sql_insert_id();
                sql_query("update orvosok set username='d{$oid}',jelszo=SUBSTR(MD5(CONCAT(nev,id)) FROM 3 FOR 6) where id='{$oid}'");
            }
            if ($_GET["page"]=="screenings" && $this->szurestipusModJog()) {
                sql_query("insert into szurestipusok set megnev='Új tétel'");
            }
            if ($_GET["page"]=="users") {
                if ($user["jogosultsag"]>=2) {
                    sql_query("insert into users set nev='Új felhasználó'");
                } else {
                    sql_query("insert into users set nev='Új felhasználó', cegid='{$user["cegid"]}'");
                }
                logActivity("user",sql_insert_id(),"felhasználó létrehozva");
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["delete"])) {
            if ($_GET["page"]=="places" && $this->helyszinModJog()) {
                sql_query("delete from helyszinek where id=?",array($_GET["delete"]));
            }
            if ($_GET["page"]=="doctors" && $this->orvosModJog()) {
                sql_query("delete from orvosok where id=?", array($_GET["delete"]));
                sql_query("delete from orvos_beosztas where orvosid=?", array($_GET["delete"]));
            }

            if ($_GET["page"]=="screenings" && $this->szurestipusModJog()) {
                sql_query("delete from szurestipusok where id=?",array($_GET["delete"]));
            }
            if ($_GET["page"]=="users") {
                sql_query("delete from users where id=? and id<>1",array($_GET["delete"]));
                logActivity("user",$_GET["delete"],"felhasználó törölve");
            }
            if ($_GET["page"]=="patients") {
                sql_query("delete from felhasznalok where id=?", array($_GET["delete"]));
            }

            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_GET["oaktivtoggle"])) {
            if ($_GET["page"]=="places") {
                sql_query("update helyszinek set aktiv=not aktiv where id=?",array($_GET["oaktivtoggle"]));
            }
            if ($_GET["page"]=="doctors") {
                sql_query("update orvosok set aktiv=not aktiv where id=?",array($_GET["oaktivtoggle"]));
            }
            if ($_GET["page"]=="screenings") {
                sql_query("update szurestipusok set aktiv=not aktiv where id=?",array($_GET["oaktivtoggle"]));
            }
            if ($_GET["page"]=="companies") {
                sql_query("update cegek set aktiv=not aktiv where id=?",array($_GET["oaktivtoggle"]));
            }
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }
        if (isset($_GET["ocsaktivtoggle"])) {
            if ($_GET["page"]=="szurestipusok") sql_query("update szurescsomagok set aktiv=not aktiv where id=?",array($_GET["ocsaktivtoggle"]));
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
            die();
        }

        if (isset($_POST["add2sztceg"])) {
            $sor = intval($_POST["sor"]);
            $cegid = "|".intval($_POST["cegid"])."|";

            if ($row=sql_fetch_array(sql_query("select * from arak where id=?",array($_POST["arid"])))) {

                if (substr_count($row["cegid"],$cegid)==0) {
                    $row["cegid"].=$cegid;
                    sql_query("update arak set cegid=? where id=?",array($row["cegid"],$_POST["arid"]));
                }

                echo $this->showCegListSzT($row["cegid"],$sor);
            }
            die();
        }

        if (isset($_POST["removesztceg"])) {
            $sor = intval($_POST["sor"]);
            $cegid = "|".intval($_POST["cegid"])."|";

            sql_query("update arak set cegid=replace(cegid,?,'') where id=?",array($cegid,$_POST["arid"]));

            if ($row = sql_fetch_array(sql_query("select * from arak where id=?",array($_POST["arid"])))) {
                echo $this->showCegListSzT($row["cegid"],$sor);
            }
            die();
        }


    }

    public function beosztasModJog() {
        if ($_SESSION["adminuser"]["jog_beosztasset"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
        return false;
    }

    public function orvosModJog() {
        if ($_SESSION["adminuser"]["jog_orvosset"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
        return false;
    }

    public function szabadsagJog() {
        if ($_SESSION["adminuser"]["jog_szabi"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
        return false;
    }

    public function cegModJog() {
        if ($_SESSION["adminuser"]["jog_cegset"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
        return false;
    }

    public function szurestipusModJog() {
        if ($_SESSION["adminuser"]["jog_szurestipusset"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
        return false;
    }

    public function helyszinModJog() {
        if ($_SESSION["adminuser"]["jog_helyszinset"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
        return false;
    }

    public function userModJog() {
        if ($_SESSION["adminuser"]["jog_jogset"]==1) return true;
        return false;
    }

    public function newPassSend($rowu) {
        $pchars="abcdefghijklmnpqrstuvwxyz1234567899";
        $p="";
        for ($i=0;$i<6;$i++) {
            $p.=substr($pchars,rand(0,strlen($pchars)-1),1);
        }

        $mail = new PHPMailer();
        $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
        $mail->FromName = Booking_Constants::COMPANY_NAME;
        $mail->AddAddress($rowu["email"]);
        $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
        $mail->IsHTML(true);

        $t=iconv("UTF-8","ISO-8859-2",Booking_Constants::SITE_NAME." - új jelszó");

        $mbody="Kedves {$rowu["nev"]}!<br/><br/>";
        $mbody.="A ".Booking_Constants::SITE_NAME." felületén új jelszó kérését kezdeményezte.<br/><br/>";
        $mbody.="Felhasználóneve: <b>{$rowu["username"]}</b><br/>";
        $mbody.="Az új jelszava: <b>{$p}</b><br>";
        $mbody.="<br/>";
        $mbody.="Üdvözlettel:<br>".Booking_Constants::COMPANY_NAME;

        $mail->Subject=$t;
        $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
        //$mail->AddAttachment("");
        $mail->Send();

        sql_query("update users set password='".md5($p)."' where id='{$rowu["id"]}'", array(md5($p), $rowu["id"]));
    }


    public function showAdminNaptar() {
        $bookingService = new BookingService();

        if (!isset($_SESSION["helyszin"]) || $_SESSION["helyszin"]==0) return "";

        $shift=intval($_SESSION["shift"]);

        $htmlout="";

        $helyszin=intval($_SESSION["helyszin"]);
        $helyszinceg=intval($_SESSION["helyszinceg"]);

        if ($_SESSION["naptarszurestipus"]!=0) {
            if ($row=sql_fetch_array(sql_query("select megnev from szurestipusok where id=?",array($_SESSION["naptarszurestipus"])))) {
                $_SESSION["naptarszurestipusnev"]=$row["megnev"];
            }
        }


        $foglaltidopontok[]="";

        //el kell dönteni, hogy csak a cég foglaltjait mutassa, vagy az összes kiválasztott címre foglaltakat!
        //$res=sql_query("select datum,nev,eljott from foglalasok where helyszinid='{$helyszin}' and cegid='{$helyszinceg}' and aktiv=1");
        $wf="";
        if ($_SESSION["naptarszurestipus"]!=0) $wf.=" and szurestipusid='".intval($_SESSION["naptarszurestipus"])."'";
        $res=sql_query("select datum,nev,eljott,cegid,orvosassigned from foglalasok where helyszinid='{$helyszin}' and aktiv=1 {$wf}");
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


        $szunnapok[]="";
        $rows=sql_fetch_array(sql_query("select * from settings"));
        $n=explode(",",$rows["szunnapok"]);
        for ($i=0;$i<count($n);$i++) {
            $szunnapok[]=trim($n[$i]);
        }


        $resSzabi=sql_query("SELECT * FROM szabadsag WHERE datumtol>DATE_SUB(NOW(),INTERVAL 30 DAY)");
        while ($szData=sql_fetch_array($resSzabi)) {
            $GLOBALS["szabidata"][$szData["oid"]][]=$szData;
        }

        $htmlout.="<table border='0' cellpadding='0' cellspacing='0'><tr>";


        for ($i=0; $i<Booking_Constants::ADMIN_DAY_DISPLAY; $i++) {
            $dd=$i+$shift;

            $nap=date("Y-m-d",strtotime("now +{$dd} day"));
            $wd=date("N",strtotime("now +{$dd} day")); //day of week
            $wn=date("W",strtotime("now +{$dd} day")); //number of week

            $dbg="#0a0";
            if (in_array($nap,$foglaltnapok)) $dbg="#ccc;";

            $htmlout.= "<td valign='top' sytle=''>";
            $htmlout.= "<div style='background:{$dbg};padding:2px 10px 2px 10px;color:#fff;font-weight:bold;text-align:center;margin-right:3px;'>{$nap}<br>".$this->settings->hetnap[$wd]."</div>";


            if (in_array($nap,$foglaltnapok)) {
                $htmlout.= "<div style='text-align:center;'>erre a napra<br>foglalás tiltva</div>";
                $htmlout.= "<div style='text-align:center;margin-bottom:10px;'><a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&enablenap=".urlencode("{$nap}")."'>engedélyezés</a></div>";
            } else {
                $htmlout.= "<div style='text-align:center;margin-bottom:10px;'><a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&disablenap=".urlencode("{$nap}")."'>nap tiltása</a></div>";
            }



            $minrendeles=0;
            $maxrendeles=0;
            if (isset($beosztasData)) unset($beosztasData);
            if ($beoData = $bookingService->getBeosztasok($nap, $helyszin, $_SESSION["naptarszurestipus"])) {
                foreach ($beoData as &$beo) {
                    if ($_SESSION["adminuser"]["jogosultsag"]<2 && substr_count($_SESSION["adminuser"]["cegjog"],"|{$beo["cegid"]}|")==0) continue;
                    if (strtotime($beo["tol"])<strtotime($minrendeles) || $minrendeles==0) $minrendeles=$beo["tol"];
                    if (strtotime($beo["ig"])>strtotime($maxrendeles) || $maxrendeles==0) $maxrendeles=$beo["ig"];


                    if ($beo["nap"]==10) {
                        $beosztasData[$beo["beonap"]][]=$beo;
                    } else {
                        $beosztasData[$beo["nap"]][]=$beo;
                    }
                    //$beosztasData[$beo["nap"]][]=$beo;
                }
            } else {
                $htmlout.="<div style='text-align:center;padding:0px;'>Nincs<br/>rendelés</div>";
                $htmlout.="</td>";
                continue;
            }


            if (isset($beosztasData[$nap])) {
                $beosztasData[$wd][]=$beosztasData[$nap][0];
            }

            //$htmlout.=print_r($beosztasData,true);


            if (in_array($nap,$szunnapok)) {
                $htmlout.="<div style='text-align:center;'>Munkaszüneti<br/>nap!</div>";
                $htmlout.="</td>";
                continue;
            }


            $binterval=$beosztasData[$wd][0]["binterval"];
            $beginora=round(substr($minrendeles,0,2));
            $beginperc=round(substr($minrendeles,3,2));

            for ($o=0;$o<=55;$o++) {
                $diff=$o*$binterval;
                $ora=date("H:i",strtotime("{$nap} {$minrendeles}+{$diff} minute"));
                //$ora=date("H:i",mktime($beginora,$beginperc+$o*$binterval,0,date("m"),date("d"),date("Y")));

                if (strtotime($ora)>=strtotime($maxrendeles)) break;

                $java="sF2('{$nap} {$ora}');return false;";
                $class="nfb2";
                $title="";

                if (isset($beosztasData[$wd][0]["binterval"])) {

                    //$htmlout.=print_r($beosztasData[$wd],true);

                    if ($dokik=availableDoctorsForTime($nap,$ora,$beosztasData[$wd])) {
                        $class="fhb2";
                        if (isset($foglaltData["{$nap} {$ora}"])) {
                            $class="fb2";
                            $title=$foglaltData["{$nap} {$ora}"][0]["nev"];
                            if ($foglaltData["{$nap} {$ora}"][0]["cegid"]==0 && $foglaltData["{$nap} {$ora}"][0]["orvosassigned"]==0) $title="foglalt"; //ha nincs cég és orvos, akkor az egész időpont foglalt
                        }
                    }
                }

                $htmlout.="<div id='".str_replace(array("-",":"),"","ipbox{$nap}{$ora}")."' class='ipcell'>";
                $htmlout.="<a class='{$class}' onclick=\"{$java}\" href='#' title='{$title}'>{$ora}</a>";

                if ($class=="fhb2") {
                    $htmlout.=" <a title='időpont lefoglalása' class='fi' onclick=\"addIdopontNaptar('{$nap} {$ora}',{$_SESSION["naptarszurestipus"]});return false;\" href='#'>+</a>";
                }
                if ($class=="fb2") {
                    if ($title=="foglalt") {
                        $htmlout.="&nbsp;&nbsp;fo";
                    } else {
                        $htmlout.="&nbsp;&nbsp;".count($dokik)."/".count($foglaltData["{$nap} {$ora}"]);
                    }
                }


                $htmlout.="</div>";
            }


            if (isOrvosLogin()) {
                $htmlout.="<div style='margin:10px 0px 0px 20px;'>";
                $htmlout.="<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&tobbnapfoglal&from=".urlencode("{$nap}")."' title='több nap foglalása'>F+</a>";
                $htmlout.="</div>";
            }

            $htmlout.="</td>";

        }
        $htmlout.="</tr></table>";
        return $htmlout;
    }



    public function getCegList($c) {
        $cl="0";

        if ($_SESSION["adminuser"]["jogosultsag"]==0) $cl="-1";

        $j=explode("|",$c);
        for ($i=0;$i<count($j);$i++) {
            if ($j[$i]!="") {
                $cl.=",".intval($j[$i]);
            }
        }
        return $cl;
    }

    public function showCegListSzT($raw,$sor) {
        $h="";
        $resc=sql_query("select id,megnev from cegek order by megnev");
        while ($rowc=sql_fetch_array($resc)) {
            $cegList[$rowc["id"]]=$rowc["megnev"];
        }

        $cegidk=explode("|",$raw);

        for ($i=0;$i<count($cegidk);$i++) {
            if (isset($cegList[$cegidk[$i]])) $h.="<span onclick='removeSztCegek({$cegidk[$i]},{$sor})' style='background:#f00;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;display:inline-block;margin:2px 2px 0px 0px;'>- {$cegList[$cegidk[$i]]}</span> ";
        }

        $h.="<span onclick='$(\"#cegadd{$sor}\").slideToggle();' title='Cég hozzáadása' style='background:#0a0;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;'>+ cég</span>";

        return $h;
    }


    public function cegAddSorSzT($sor) {
        $h="";
        $resc=sql_query("select id,megnev from cegek order by megnev");
        while ($rowc=sql_fetch_array($resc)) {
            $h.="<span onclick='add2SztCegek({$rowc["id"]},{$sor})' style='background:#0a0;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;display:inline-block;margin:2px 2px 0px 0px;'>+ {$rowc["megnev"]}</span> ";
        }
        return $h;
    }

    public function showFizSzolg($fid,$simple=0) {
        $h="";
        $res=sql_query("select * from fizkapcs where fid=?",array($fid));
        if (sql_num_rows($res)>0) {
            if ($simple==0) $h.="<div style='padding:10px;margin-bottom:10px;background:#fcc;display:inline-block;'>";
            while ($row=sql_fetch_array($res)) {
                if ($row["megnev"] == "") {
                    $row["megnev"] = "noname";
                }
                $h.="<div>+ {$row["megnev"]}";
                if ($row["ar"]!=0) $h.=" (".number_format($row["ar"])." Ft)";
                if ($simple==0) $h.=" [<a href='#' onclick='removeFizSzolg({$fid},{$row["id"]});return false;'>-</a>]</div>";
            }
            if ($simple==0) $h.="</div>";
        }
        return $h;
    }

    public function showPaciensFiles($id) {
        $htmlout="";
        $resf=sql_query("select * from dokumentumok where foglalasid=?",array($id));
        if (sql_num_rows($resf)>0) {
            $htmlout.="<div style='display:inline-block;'>";
            $htmlout.="<div style='background:#888;color:#fff;padding:5px;'>Paciens által feltöltött file(ok)</div>";
            while ($rowf=sql_fetch_array($resf)) {
                $htmlout.="<div style='padding:1px 4px;'><a href='//bejelentkezes.keltexmed.hu/downloaddoc.php?f={$rowf["id"]}&k={$rowf["kod"]}'>{$rowf["filename"]}</a></div>";
            }
            $htmlout.="</div>";
        }
        return $htmlout;
    }

    public function getAdminMenu() {
        $adminMenu = [];
        if (isset($_SESSION["adminuser"])) {
            $res = sql_query("select * from adminmenu where aktiv=1 order by sorrend, megnev");
            while ($menuData = sql_fetch_array($res)) {
                if ($menuData["jogosultsag"] != "" && $_SESSION["adminuser"][$menuData["jogosultsag"]] != 1) {
                    continue;
                }
                if ($menuData["jogszint"] > $_SESSION["adminuser"]["jogosultsag"]) {
                    continue;
                }
                $adminMenu[] = $menuData;
            }
        }
        return $adminMenu;
    }

    public function magyarDatum($datum) {
        $m = date("n",strtotime($datum));
        $n = date("Y-m-d",strtotime($datum));
        $w = date("N",strtotime($datum));
        return substr($datum,0,4)." ".ucfirst($GLOBALS["honaptext"][$m])." ".intval(substr($n,8,2)).". ".$GLOBALS["hetnap"][$w]." ".substr($datum,11,5);
    }

    public function cegSQLFilter($key) {
        $w = "";
        if ($this->isCegAdmin()) {
            $cegidk = str_replace("||",",",$_SESSION["adminuser"]["cegjog"]);
            $cegidk = str_replace("|","",$cegidk);
            if ($cegidk == "") $cegidk = "-1";
            $w.= "and {$key} in ({$cegidk})";
        }
        return $w;
    }

    public function isCegAdmin() {
        return $_SESSION["adminuser"]["jogosultsag"] < 2;
    }

    public function isOrvosLogin() {
        return $GLOBALS["adminuser"]["orvosid"] == 0 ? false:true;
    }


    public function showAlkalmassagStatus($row) {
        $htmlout="";

        if (isset($GLOBALS["alkalmassagvariaciok"][$row["alkalmassag"]])) {
            $htmlout.="<div style='display:table;margin-top:10px;'>";

            $htmlout.="<div style='display:table-cell;vertical-align:middle;'>";
            $htmlout.="<div class='alkalmassagjelzes alkalmascolor{$row["alkalmassag"]}'>".$GLOBALS["alkalmassagvariaciok"][$row["alkalmassag"]];
            if ($row["alkalmassag"]=="I") $htmlout.=" {$row["alkalmassagido"]} hó";
            $htmlout.="</div>";
            $htmlout.="</div>";

            $htmlout.="<div style='display:table-cell;vertical-align:middle;padding-left:10px;'>";
            $htmlout.="<a href='printalkalmassagi?id={$row["id"]}&token=".md5($row["datum"].$row["regdatum"])."' target='_blank'><img src='images/print-icon.png' style='height:21px;' title='Alkalmassági igazolás nyomtatása' alt='' /></a>";
            $htmlout.="</div>";

            $htmlout.="</div>";
        }

        return $htmlout;
    }

    public function showEljottCheckBox($row) {
        $htmlout="";
        $htmlout.="<div style='display:table;'>";
        $htmlout.="<div style='display:table-row;'>";
        $htmlout.="<div style='display:table-cell;'>";
        $htmlout.="<div onclick='toggleEljott({$row["id"]})' class='nagycheckbox".($row["eljott"]==1?" nagychecked":"")."'></div>";
        $htmlout.="</div>";
        $htmlout.="<div style='display:table-cell;vertical-align:middle;'>&nbsp;Eljött</div>";
        $htmlout.="</div>";
        $htmlout.="</div>";
        return $htmlout;
    }

}