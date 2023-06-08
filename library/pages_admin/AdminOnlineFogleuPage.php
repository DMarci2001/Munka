<?php

class AdminOnlineFogleuPage extends AdminCorePage
{

    public function __construct()
    {
        parent::__construct();

        if (!isset($_SESSION["blistanap"])) $_SESSION["blistanap"]=date("Y-m-d");
        if (isset($_GET["today"])) $_GET["blistanap"]=date("Y-m-d");
        if (isset($_GET["blistanap"])) $_SESSION["blistanap"]=date("Y-m-d",strtotime($_GET["blistanap"]));

        if (isset($_SESSION["filternap"]) && $_SESSION["filternap"]!=$_SESSION["blistanap"]) {
            if (isset($_SESSION["filterszurestipus"])) unset($_SESSION["filterszurestipus"]);
            if (isset($_SESSION["filterorvos"])) unset($_SESSION["filterorvos"]);
            if (isset($_SESSION["filtercegid"])) unset($_SESSION["filtercegid"]);
            if (isset($_SESSION["filternev"])) unset($_SESSION["filternev"]);
        }

        if (isset($_POST["fogleueditor"])) {
            if ($this->adminUser->authenticated()) {
                if ($data = sql_fetch_array(sql_query("select * from foglalasok where id=?", [$_POST["fogleueditor"]]))) {
                    echo $this->showAlkalmassagForm($data);
                }
                die;
            }
            die;
        }

        if (isset($_GET["onlinefogleusave"])) {
            if (!$this->$this->adminUser->authenticated()) {
                die;
            }

            $fid = intval($_GET["fid"]);
            if (!isset($_POST["alkalmassag"])) {
                $_POST["alkalmassag"]=0;
            }
            if (!isset($_POST["alkalmassagido"])) {
                $_POST["alkalmassagido"]=0;
            }

            sql_query("update foglalasok set alkalmassag=?, alkalmassagido=?, alkalmassagikhet=?, alkalmassagkorl=? where id=?",
                [$_POST["alkalmassag"], $_POST["alkalmassagido"], $_POST["alkalmassagikhet"], $_POST["alkalmassagkorl"], $fid]);

            echo $this->showAlkalmassagLista();
            die;
        }


    }

    public function showPage() {
        echo "<div id='alkalmassaglista'>";
        echo $this->showAlkalmassagLista();
        echo "</div>";
        echo "<script src='/js/onlinefogleuform.js'></script>";
    }

    private function showAlkalmassagLista() {
        $html     = "";
        $datumtol = $_SESSION["blistanap"]." 00:00:00";
        $datumig  = $_SESSION["blistanap"]." 23:59:59";
        $nextday = date("Y-m-d",strtotime("{$datumtol} + 1 day"));
        $prevday = date("Y-m-d",strtotime("{$datumtol} - 1 day"));
        $w       = $bw = $wo = "";
        $tc      = "tcella";
        $colspan = 10;
        $date    = date("Y-m-d", strtotime($datumtol));
        $wd      = date("N",strtotime($date));
        $s       = "background:#eee;font-size:16px;padding:5px;";

        if ($date == date("Y-m-d")) {
            $s = "background:#f88;color:#fff;font-size:16px;padding:5px;";
        }

        if (!$this->adminUser->allCegJog()) {
            $w = "and f.cegid in (".$this->adminUser->getCegList().")";
        }

        $res = sql_query("SELECT o.`nev` AS orvosnev,h.cim AS helyszin,sz.megnev AS szurestipus,f.*,b.naploszam,b.megj as beutalomegj,c.megnev as cegnev FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        left join cegek c on c.id=f.cegid
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        left join beutalok b on b.foglalasid=f.id
        LEFT JOIN orvosok o ON o.id=f.`orvosassigned`
        WHERE f.regdatum>=? and f.regdatum<=? and f.aktiv=1 and online_fogleu=1 {$w} order by regdatum desc", [$datumtol,$datumig]);

        $html.= "<div style='display:inline-block;vertical-align:middle;'>";
        $html.= "<div style='display:table-cell;vertical-align:middle;background:#eee;padding:10px;'>";
        $html.= "<input type='text' value='{$_SESSION["blistanap"]}' name='blistanap' id='blistanap' style='width:85px;font-size:16px;' /> <input onclick='window.location.href=\"{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&blistanap=\"+$(\"#blistanap\").val();' type='button' value='OK'/> <input onclick='window.location.href=\"{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&today\"' type='button' value='Ma'/>";
        $html.= "</div>";

        $html.= "<div style='display:table-cell;vertical-align:middle;background:#eee;padding:10px;'>";

        $html.= "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&blistanap={$prevday}'><img height='15' src='images/prev.png' title='Előző nap' style='margin-left:10px;'/></a>";
        $html.= "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&blistanap={$nextday}'><img height='15' src='images/next.png' title='Következő nap' style='margin-left:10px;'/></a>";
        $html.= "</div>";

        $html.= "</div>";


        $todoDates = sql_query("SELECT DATE(regdatum) AS datum, count(*) as db FROM foglalasok f WHERE regdatum>DATE_SUB(NOW(), INTERVAL 1 MONTH) AND online_fogleu=1 AND f.`alkalmassag`='' GROUP BY DATE(regdatum)")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($todoDates)) {
            $html.= "<div style='margin-top:20px;'>Elbírálandó fogl eü: ";
            foreach ($todoDates as $d) {
                $html.="<a href='index.php?page={$_GET["page"]}&blistanap={$d["datum"]}'>{$d["datum"]} ({$d["db"]} db)</a>&nbsp;&nbsp;";
            }
            $html.= "</div>";
        }


        $html.= "<table cellpadding='0' cellspacing='0' border='0' style='margin-top:10px;min-width:600px;'>";
        $html.= "<tr><td colspan='{$colspan}' style='{$s}'>{$date} ".$this->adminUtils->settings->hetnap[$wd]."</td></tr>";

        if (sql_num_rows($res) == 0) {
            $html.= "<tr><td colspan='{$colspan}' class='{$tc}'>Ezen a napon nem érkezett online fogl eü kitöltés</td></tr>";
        } else {
            $html.= "<tr style='background:#eee;'>";
            $html.= "<td nowrap valign='top' width='100'><div class='{$tc}'>&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>&nbsp;&nbsp;Beérkezett</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>Cég&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>Alkalmasság</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'></div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>TAJ szám&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>Paciens&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>Email&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>Telefon&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>Munkakör&nbsp;&nbsp;</div></td>";
            $html.= "</tr>";
        }

        while ($row = sql_fetch_array($res)) {

            $html.= "<tr><td colspan='{$colspan}' style='border-top:1px solid #ccc;height:1px;'></td></tr>";

            $html.= "<tr>";
            $html.= "<td nowrap valign='middle'><div class='{$tc}'>&nbsp;&nbsp;[<a href='#' onclick='editOnlineFogelEuRow({$row["id"]});return false;'>szerkesztés</a>]&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='middle'><div class='{$tc}'>".substr($row["regdatum"],0,16)."&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='middle'><div class='{$tc}'><div>{$row["cegnev"]}&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='middle'><div class='{$tc}'>".$this->showAlkalmassag($row)."&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='middle'><div class='{$tc}'>".($row["alkalmassag"]!=""?"<a class='printbutton' target='_blank' href='index.php?print&template=alkalmassagi&fid={$row["id"]}&p={$row["pass"]}'>nyomtatás</a>":"")."&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='middle'><div class='{$tc}'>{$row["taj"]}&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='middle'><div class='{$tc}'>{$row["nev"]}&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='middle'><div class='{$tc}'>{$row["email"]}&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='middle'><div class='{$tc}'>{$row["telefon"]}&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='middle'><div class='{$tc}'>{$row["munkakor"]}&nbsp;&nbsp;</div></td>";
            $html.= "</tr>";

            $html.= "<tr><td></td><td colspan='{$colspan}'><div id='fogleueditor{$row["id"]}'></div></td></tr>";
        }
        $html.= "</table>";

        return $html;

    }

    private function showAlkalmassag($data) {
        $html = "";

        if (in_array($data["alkalmassag"], array_keys($this->adminUtils->settings->alkalmassagvariaciok))) {
            $html.= $this->adminUtils->settings->alkalmassagvariaciok[$data["alkalmassag"]];
        }

        if (!empty($data["alkalmassagido"])) {
            $html.= " lejár: ".date("Y-m-d", strtotime("{$data["datum"]} + {$data["alkalmassagido"]} month"));
        }

        return $html;
    }

    private function showAlkalmassagForm($data) {
        $neme = "nem adta meg";
        if ($data["neme"] == 1) {
            $neme = "férfi";
        }
        if ($data["neme"] == 2) {
            $neme = "nő";
        }

        $html = "";

        $html.= "<form name='alkalmassagiform{$data["id"]}' id='alkalmassagiform{$data["id"]}'>";

        $html.= "<div style='background:#ccc;padding:10px;font-weight: bold;margin-bottom:5px;'>Alkalmasság megadása</div>";

        foreach ($this->adminUtils->settings->alkalmassagvariaciok as $key => $value) {
            $oc="";
            if ($key!="I") $oc="onclick=\"$('input[name=alkalmassagido]').attr('checked',false);\"";
            $html.= "<div><input ".($data["alkalmassag"]==$key?"checked":"")." {$oc} type='radio' name='alkalmassag' value='{$key}' /> {$value}";
            if ($key=="I") {
                $html.= "<input " . ($data["alkalmassagido"] == 3 ? "checked" : "") . " type='radio' name='alkalmassagido' value='3' />3 hó";
                $html.= "<input " . ($data["alkalmassagido"] == 6 ? "checked" : "") . " type='radio' name='alkalmassagido' value='6' />6 hó";
                $html.= "<input " . ($data["alkalmassagido"] == 12 ? "checked" : "") . " type='radio' name='alkalmassagido' value='12' />1 év";
                $html.= "<input " . ($data["alkalmassagido"] == 24 ? "checked" : "") . " type='radio' name='alkalmassagido' value='24' />2 év";
                $html.= "<input " . ($data["alkalmassagido"] == 36 ? "checked" : "") . " type='radio' name='alkalmassagido' value='36' />3 év";
            }
            if ($key=="IN") {
                $html.= "&nbsp;&nbsp;&nbsp;&nbsp;köv. vizsgálat: <input type='text' style='width:40px;' name='alkalmassagikhet' value='{$data["alkalmassagikhet"]}' /> hét";
            }
            if ($key=="K") {
                $html.= "<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<textarea placeholder='korlátozás szövege' style='width:300px;height:40px;' name='alkalmassagkorl'>{$data["alkalmassagkorl"]}</textarea>";
            }
            $html.= "</div>";
        }

        $html.= "<br><input type='button' onclick='fogleuMentes({$data["id"]});' value='Mentés'/>&nbsp;&nbsp;";
        $html.= "<input onclick='$(\"#fogleueditor{$data["id"]}\").html(\"\");' type='button' value='Bezár'/>&nbsp;&nbsp;";

        $html.= "<div style='background:#ccc;padding:10px;font-weight: bold;margin:10px 0px 5px 0px;'>Felhasználó által megadott adatok:</div>";

        $files = $this->adminUtils->showPaciensFiles($data["id"]);
        if ($files != "") {
            $html.="<div style='margin:10px 0px;'>{$files}</div>";
        }

        $html.="<div><strong>Név:</strong> {$data["nev"]}</div>";
        $html.="<div><strong>Születési dátum:</strong> {$data["szuldatum"]}</div>";
        $html.="<div><strong>TAJ:</strong> {$data["taj"]}</div>";
        $html.="<div><strong>Születési hely:</strong> {$data["szulhely"]}</div>";
        $html.="<div><strong>Anyja neve:</strong> {$data["anyjaneve"]}</div>";
        $html.="<div><strong>Munkakör:</strong> {$data["munkakor"]}</div>";
        $html.="<div><strong>Neme:</strong> {$neme}</div>";
        $html.="<div><strong>Email:</strong> {$data["email"]}</div>";
        $html.="<div><strong>Telefon:</strong> {$data["telefon"]}</div>";
        $html.="<div><strong>Megjegyzés:</strong> {$data["megj"]}</div>";
        $html.="<div>{$data["questions"]}</div>";


        $html.= "</form>";

        return $html;
    }



}

