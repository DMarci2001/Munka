<?php

class AdminStatPage extends AdminCorePage {

    private $csv;
    private $tol;
    private $ig;
    private $lista = "default";

    public function __construct()
    {
        parent::__construct();

        if (!isset($_SESSION["idoszak"])) {
            $_SESSION["idoszak"] = date("Y-m");
        }
        if (isset($_GET["idoszak"])) {
            $_SESSION["idoszak"]=$_GET["idoszak"];
        }

        $this->csv  = "";
        $this->tol  = $_SESSION["idoszak"]."-01 00:00:00";
        $this->ig   = $_SESSION["idoszak"]."-".date("t",strtotime($this->tol))." 23:59:59";

        if (isset($_GET["lista"])) {
            $this->lista = $_GET["lista"];
        }
    }

    public function showPage() {
        $stat = $this->_showIntervalSelector();

        switch ($this->lista) {
            case "orvos":
                $stat.= $this->_showDoctorStat();
                break;
            case "tetel":
                $stat.= $this->_showTetelStat();
                break;
            case "default":
                $stat.= $this->_showDefaultStat();
                break;
        }

        $this->_sendCSV();
        echo $stat;
    }


    private function _showDoctorStat() {
        $html = "";

        $cegid = intval($_GET["cegid"]);
        $rowc = sql_fetch_array(sql_query("SELECT * from cegek c where c.id=? ".$this->adminUtils->cegSQLFilter("c.id")." ORDER BY megnev", [$cegid]));

        $html.= "<div style='margin-top:20px;'><a href='index.php?page=stat'>Vissza</a></div>";
        $html.= "<table cellpadding='0' cellspacing='4' border='0' style='margin-top:10px;'>";

        $html.= "<tr><td colspan='10' style='background:#eee;font-size:16px;color:#888;padding:5px 10px;margin-top:20px;'>{$rowc["megnev"]}</td></tr>";
        $reso = sql_query("SELECT o.nev AS orvosnev,h.cim AS helyszincim,t.`megnev` AS szurestipus,COUNT(*) AS hany,SUM(eljott) AS hanyeljott,f.* FROM foglalasok f
        LEFT JOIN orvosok o ON o.id=f.orvosassigned
        LEFT JOIN szurestipusok t ON t.`id`=f.`szurestipusid`
        LEFT JOIN helyszinek h ON h.id=f.helyszinid
        WHERE f.datum>? AND f.datum<? AND f.aktiv=1 AND f.cegid=? AND (f.taj<>'' OR f.nev<>'') AND f.nev<>'nincs név' 
        GROUP BY orvosassigned,szurestipusid	
        ORDER BY orvosnev", array($this->tol, $this->ig, $rowc["id"]));

        $html.= "<tr>";
        $html.= "<td>Orvos</td>";
        $html.= "<td>Szűréstípus</td>";
        $html.= "<td align='right'>Összes időpont</td>";
        $html.= "<td align='right'>Ebből eljött</td>";
        $html.= "<td>&nbsp;</td>";
        $html.= "</tr>";
        while ($rowo = sql_fetch_array($reso)) {
            $html.= "<tr>";
            $html.= "<td>{$rowo["orvosnev"]}</td>";
            $html.= "<td>{$rowo["szurestipus"]}</td>";
            $html.= "<td align='right'>{$rowo["hany"]}</td>";
            $html.= "<td align='right'>{$rowo["hanyeljott"]}</td>";
            $html.= "<td align='right'>&nbsp;&nbsp;&nbsp;[<a href='#' onclick='$(\"#orvosdetail{$rowo["orvosassigned"]}\").toggle();return false;'>részletek</a>]</td>";
            $html.= "</tr>";

            $html.= "<tr><td colspan='10'>";
            $html.= "<div id='orvosdetail{$rowo["orvosassigned"]}' style='background:#eee;padding:5px;display:inline-block;display:none;'>";


            $html.= "<table cellpadding='0' cellspacing='4' border='0' style=''>";

            $ress = sql_query("SELECT o.nev AS orvosnev,h.cim AS helyszincim,f.* FROM foglalasok f
            LEFT JOIN orvosok o ON o.id=f.orvosassigned
            LEFT JOIN helyszinek h ON h.id=f.helyszinid
            WHERE f.datum>? and f.datum<? and f.aktiv=1 AND f.cegid=? and f.orvosassigned=? AND (f.taj<>'' OR f.nev<>'') AND f.nev<>'nincs név' 
            order by datum", array($this->tol, $this->ig, $rowc["id"], $rowo["orvosassigned"]));

            $html.= "<tr>";
            $html.= "<td title='Eljött'>E</td>";
            $html.= "<td title='Alkalmasság'>A</td>";
            $html.= "<td>Foglalás időpontja</td>";
            $html.= "<td>Név</td>";
            $html.= "<td>Telefon</td>";
            $html.= "<td></td>";
            $html.= "<td>TAJ szám</td>";
            $html.= "<td>Orvos</td>";
            $html.= "<td></td>";
            $html.= "<td>Regisztráció időpontja</td>";
            $html.= "</tr>";
            while ($rows = sql_fetch_array($ress)) {
                $html.= "<tr>";
                $html.= "<td>".($rows["eljott"]==1?"*":"")."</td>";
                $html.= "<td>";
                $html.= $rows["alkalmassag"];
                if ($rows["alkalmassag"]=="I") {
                    $html.= $rows["alkalmassagido"];
                }
                $html.= "</td>";
                $html.= "<td>".substr($rows["datum"],0,16)."</td>";
                $html.= "<td>{$rows["nev"]}</td>";
                $html.= "<td>{$rows["telefon"]}</td>";
                $html.= "<td>{$rows["munkakor"]}</td>";
                $html.= "<td>{$rows["taj"]}</td>";
                $html.= "<td>{$rows["orvosnev"]}</td>";
                $html.= "<td>{$rows["helyszincim"]}</td>";
                $html.= "<td>".substr($rows["regdatum"],0,16)."</td>";
                $html.= "</tr>";
            }
            $html.= "</table>";
            $html.= "</div>";
            $html.= "</td></tr>";
        }
        $html.= "</table>";
        return $html;
    }


    private function _alkalmassagColumn($rows) {
        $html = "";

        if (in_array($rows["alkalmassag"], array_keys($this->adminUtils->settings->alkalmassagvariaciok))) {
            $html.=$rows["alkalmassag"];
        }

        if (!empty($rows["alkalmassagido"])) {
            $html.=" lejár: ".date("Y-m-d", strtotime("{$rows["datum"]} + {$rows["alkalmassagido"]} month"));
        }

        //if ($rows["alkalmassag"]=="I") {
        //    $html.= $rows["alkalmassagido"];
        //}
        return $html;
    }

    private function _showTetelStat() {
        $html = "";
        $cegid = intval($_GET["cegid"]);

        $rowc = sql_fetch_array(sql_query("SELECT * from cegek c where c.id=? ".$this->adminUtils->cegSQLFilter("c.id")." ORDER BY megnev", [$cegid]));

        $html.= "<div style='margin-top:20px;'><a href='index.php?page=stat'>Vissza</a> | <a href='index.php?page={$_GET["page"]}&lista=tetel&cegid={$_GET["cegid"]}&downloadcsv'>Letöltés</a></div>";
        $html.= "<div style='margin-top:10px;'>* = Eljött. Alkalmasság: I = Alkalmas, N = Nem alkalmas, IK = Ideiglenesen nem alkalmas, K = Korlátozottan alkalmas</div>";

        $html.= "<table cellpadding='0' cellspacing='4' border='0' style='margin-top:10px;'>";

        $html.= "<tr><td colspan='10' style='background:#eee;font-size:16px;color:#888;padding:5px 10px;margin-top:20px;'>{$rowc["megnev"]}</td></tr>";

        $ress = sql_query("SELECT o.nev AS orvosnev,h.cim AS helyszincim,f.* FROM foglalasok f
        LEFT JOIN orvosok o ON o.id=f.orvosassigned
        LEFT JOIN helyszinek h ON h.id=f.helyszinid
        WHERE f.datum>? and f.datum<? and f.aktiv=1 AND f.cegid=? AND (f.taj<>'' OR f.nev<>'') AND f.nev<>'nincs név' order by datum", array($this->tol, $this->ig, $rowc["id"]));

        $html.= "<tr>";
        $html.= "<td nowrap title='Eljött'>E</td>";
        $html.= "<td nowrap title='Alkalmasság'>Alkalmasság</td>";
        $html.= "<td nowrap>Foglalás időpontja</td>";
        $html.= "<td nowrap>Név</td>";
        $html.= "<td nowrap>Telefon</td>";
        $html.= "<td nowrap></td>";
        $html.= "<td nowrap>TAJ szám</td>";
        $html.= "<td nowrap>Orvos</td>";
        $html.= "<td></td>";
        $html.= "<td nowrap>Regisztráció időpontja</td>";
        $html.= "</tr>";
        while ($rows = sql_fetch_array($ress)) {
            $html.= "<tr>";
            $html.= "<td nowrap>".($rows["eljott"]==1?"*":"")."</td>";
            $html.= "<td nowrap>".$this->_alkalmassagColumn($rows)."&nbsp;</td>";
            $html.= "<td nowrap>".substr($rows["datum"],0,16)."</td>";
            $html.= "<td nowrap>{$rows["nev"]}</td>";
            $html.= "<td nowrap>{$rows["telefon"]}</td>";
            $html.= "<td nowrap>{$rows["munkakor"]}</td>";
            $html.= "<td nowrap>{$rows["taj"]}</td>";
            $html.= "<td nowrap>{$rows["orvosnev"]}</td>";
            $html.= "<td nowrap>{$rows["helyszincim"]}</td>";
            $html.= "<td nowrap>".substr($rows["regdatum"],0,16)."</td>";

            if ($rows["taj"] == "") {
                $rows["taj"] = "000000000";
            }

            $this->csv.="{$rows["taj"]};";
            $this->csv.="{$rows["nev"]};";
            $this->csv.=substr($rows["datum"],0,16).";";
            $this->csv.=date("Y-m-d",strtotime("{$rows["datum"]} +6 month")).";";
            $this->csv.="\n";
            $html.= "</tr>";
        }
        $html.= "</table>";
        return $html;
    }

    private function _showDefaultStat() {
        $html = "";

        $res = sql_query("SELECT * from cegek c where true ".$this->adminUtils->cegSQLFilter("c.id")." ORDER BY megnev");

        $html.= "<table cellpadding='0' cellspacing='0' border='0' style='margin-top:20px;'>";
        while ($row = sql_fetch_array($res)) {
            $tc = "tcella";
            if (empty(trim($row["megnev"]))) {
                $row["megnev"] = "nincs neve";
            }
            $html.= "<tr style='background:#eee;'>";
            $html.= "<td nowrap valign='top'><div class='{$tc}' style='font-size:16px;color:#888;padding:5px 10px;'>{$row["megnev"]}</div></td>";
            //$html.= "<td nowrap valign='top'><div class='{$tc}' style='min-width:300px;'>{$row["cim"]}&nbsp;&nbsp;</div></td>";
            //$html.= "<td nowrap valign='top'><div class='{$tc}'>".($row["domain"]==""?"":"http://{$row["domain"]}.hungariamed.hu (<a target='_blank' href='http://{$row["domain"]}.hungariamed.hu'>open</a>)")."</div></td>";
            //$html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["cimek"]}</div></td>";
            //$html.= "<td nowrap valign='top'><div class='{$tc}' style='min-width:50px;'>".($row["aktiv"]==1?"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>":"<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>")."</div></td>";
            //$html.= "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='alert(\"Nem törölhető!\");return false;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
            $html.= "</tr>";

            $html.= "<tr>";
            $html.= "<td nowrap colspan='2' valign='top'>";
            $html.= "<div style='margin:5px 10px;color:#888;'>";

            $all = $eljott = 0;
            $ress = sql_query("SELECT nev,eljott FROM foglalasok WHERE datum>? and datum<? and aktiv=1 AND cegid=? AND (taj<>'' OR nev<>'') AND nev<>'nincs név'", array($this->tol, $this->ig, $row["id"]));
            while ($rows = sql_fetch_array($ress)) {
                $all++;
                if ($rows["eljott"] == 1) {
                    $eljott++;
                }
            }

            $html.= "<div style='display:table;'>";
            $html.= "<div style='display:table-row;'>";
            $html.= "<div style='display:table-cell;'>Foglalások száma:&nbsp;&nbsp;</div><div style='display:table-cell;text-align:right;'>{$all}</div>";
            if ($all > 0) {
                $html.= "<div style='display:table-cell;padding-left:20px;'>[<a href='index.php?page=stat&lista=tetel&cegid={$row["id"]}'>Tételes lista</a>] [<a href='index.php?page=stat&lista=orvos&cegid={$row["id"]}'>Orvos lista</a>]</div>";
            }
            $html.= "</div>";
            $html.= "<div style='display:table-row;'>";
            $html.= "<div style='display:table-cell;'>Eljött:&nbsp;&nbsp;</div><div style='display:table-cell;text-align:right;'>{$eljott}</div>";
            $html.= "</div>";
            $html.= "</div>";

            $html.= "</div>";

            $html.= "</td>";
            $html.= "</tr>";


        }
        $html.= "</table>";
        return $html;
    }

    private function _sendCSV() {
        if (isset($_GET["downloadcsv"]) && !empty($this->csv)) {
            ob_clean();
            header("Pragma: no-cache");
            header("Cache-Control: no-store, no-cache");
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header("Cache-Control: must-revalidate");
            //header('Content-transfer-encoding: binary');
            header('Content-Disposition: attachment; filename="tetellista.csv"');
            header("Content-type: text/csv; charset=UTF-8");
            echo iconv("UTF-8","ISO-8859-2",$this->csv);
            die();
        }
    }

    private function _showIntervalSelector() {
        $html = "";
        $html.= "<div>Időszak: <select name='idoszak' onchange='statIdoszakChange(this.value)'></div>";
        for ($i=0; $i<1000; $i++) {
            $date = date("Y-m",mktime(0,0,0,date("m")-$i,1,date("Y")));
            if (!isset($_SESSION["idoszak"])) {
                $_SESSION["idoszak"] = $date;
            }
            $html.= "<option value='{$date}'".($_SESSION["idoszak"] == $date?" selected":"").">{$date}</option>";

            if ($date == "2015-10") {
                break;
            }


        }
        $html.= "</select>";
        return $html;
    }

}

