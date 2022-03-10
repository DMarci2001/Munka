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
            $_SESSION["idoszak"] = date("Y-m", strtotime("now -1 month"));
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
        if (!$this->adminUser->statAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

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
        $rowc = sql_fetch_array(sql_query("SELECT * from cegek c where c.id=? ".$this->adminUser->cegSQLFilter("c.id")." ORDER BY megnev", [$cegid]));

        $html.= "<div style='margin-top:20px;'><a href='index.php?page=stat'>Vissza</a></div>";
        $html.= "<div style='margin-top:10px;'>* csak eljöttek</div>";

        $html.= "<h1>{$rowc["megnev"]} foglalásai</h1>";

        $html.= "<table cellpadding='4' cellspacing='0' border='0' style='margin-top:10px;'>";

        $reso = sql_query("SELECT o.nev AS orvosnev,h.cim AS helyszincim,t.`megnev` AS szurestipus,COUNT(*) AS hany,SUM(eljott) AS hanyeljott,f.* FROM foglalasok f
        LEFT JOIN orvosok o ON o.id=f.orvosassigned
        LEFT JOIN szurestipusok t ON t.`id`=f.`szurestipusid`
        LEFT JOIN helyszinek h ON h.id=f.helyszinid
        WHERE f.datum>? AND f.datum<? AND f.aktiv=1 AND f.cegid=? AND (f.taj<>'' OR f.nev<>'') AND f.nev<>'nincs név' 
        GROUP BY orvosassigned,szurestipusid	
        ORDER BY orvosnev", array($this->tol, $this->ig, $rowc["id"]));

        $html.= "<tr style='font-weight: bold;'>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Orvos</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Szűréstípus</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;text-align: right;'>Összes foglalás</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;text-align: right;'>Ebből eljött</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;text-align: right;'>&nbsp;</td>";
        $html.= "</tr>";
        while ($rowo = sql_fetch_array($reso)) {
            $html.= "<tr>";
            $html.= "<td>{$rowo["orvosnev"]}</td>";
            $html.= "<td>{$rowo["szurestipus"]}</td>";
            $html.= "<td style='text-align: right;'>{$rowo["hany"]}</td>";
            $html.= "<td style='text-align: right;'>{$rowo["hanyeljott"]}</td>";
            $html.= "<td style='text-align: right;'>&nbsp;&nbsp;&nbsp;[<a href='#' onclick='$(\"#orvosdetail{$rowo["orvosassigned"]}\").toggle();return false;'>részletek</a>]</td>";
            $html.= "</tr>";

            $html.= "<tr id='orvosdetail{$rowo["orvosassigned"]}' style='display:none;'><td colspan='10'>";
            $html.= "<div style='background:#eee;padding:5px;'>";


            $html.= "<table cellpadding='0' cellspacing='4' border='0' style=''>";

            $ress = sql_query("SELECT o.nev AS orvosnev,h.cim AS helyszincim,f.* FROM foglalasok f
            LEFT JOIN orvosok o ON o.id=f.orvosassigned
            LEFT JOIN helyszinek h ON h.id=f.helyszinid
            WHERE f.datum>? and f.datum<? and f.aktiv=1 AND f.cegid=? and f.orvosassigned=? AND (f.taj<>'' OR f.nev<>'') AND f.nev<>'nincs név' and f.eljott=1
            order by datum", array($this->tol, $this->ig, $rowc["id"], $rowo["orvosassigned"]));

            $html.= "<tr style='font-weight: bold;'>";
            $html.= "<td>Foglalás időpontja</td>";
            $html.= "<td>Név</td>";
            $html.= "<td>Telefon</td>";
            $html.= "<td></td>";
            $html.= "<td>TAJ szám</td>";
            $html.= "<td>Orvos</td>";
            $html.= "<td></td>";
            $html.= "</tr>";
            while ($rows = sql_fetch_array($ress)) {
                $html.= "<tr>";
                $html.= "<td>".substr($rows["datum"],0,16)."</td>";
                $html.= "<td>{$rows["nev"]}</td>";
                $html.= "<td>{$rows["telefon"]}</td>";
                $html.= "<td>{$rows["munkakor"]}</td>";
                $html.= "<td>{$rows["taj"]}</td>";
                $html.= "<td>{$rows["orvosnev"]}</td>";
                $html.= "<td>{$rows["helyszincim"]}</td>";
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
        $cegId = intval($_GET["cegid"]);

        if (!$rowc = sql_fetch_array(sql_query("SELECT * from cegek c where c.id=? ".$this->adminUser->cegSQLFilter("c.id")." ORDER BY megnev", [$cegId]))) {
            die("company permission error!");
        }

        $html.= "<div style='margin-top:20px;'><a href='index.php?page=stat'>Vissza</a> | <a href='index.php?page={$_GET["page"]}&lista=tetel&cegid={$_GET["cegid"]}&downloadexcel'>Letöltés</a></div>";
        $html.= "<div style='margin-top:10px;'>* csak eljöttek</div>";

        $html.= "<h1>{$rowc["megnev"]} foglalásai</h1>";

        $html.= "<table cellpadding='4' cellspacing='0' border='0' style='margin-top:10px;'>";


        $data = sql_query("SELECT o.nev AS orvosnev,h.cim AS helyszincim, t.megnev as tipusnev, f.* FROM foglalasok f
        LEFT JOIN szurestipusok t on t.id = f.szurestipusid
        LEFT JOIN orvosok o ON o.id=f.orvosassigned
        LEFT JOIN helyszinek h ON h.id=f.helyszinid
        WHERE f.datum>? and f.datum<? and f.aktiv=1 AND f.eljott=1 AND f.cegid=? AND (f.taj<>'' OR f.nev<>'') AND f.nev<>'nincs név' order by datum", array($this->tol, $this->ig, $rowc["id"]))->fetchAll(PDO::FETCH_ASSOC);

        if (isset($_GET["downloadexcel"])) {
            $service = new ExcelService();

            $statData = [
                "data" => $data,
                "cegId" => $cegId,
                "cegNev" => $rowc["megnev"],
                "from" => $this->tol,
                "to" => $this->ig
            ];

            $service->cegFoglalasList($statData);
            $service->setFileName("{$rowc["megnev"]} foglalásai ".date("Y-m-d", strtotime($this->tol))." - ".date("Y-m-d", strtotime($this->ig)).".xlsx");
            $service->outputSpreadSheet();
        }


        $html.= "<tr style='font-weight: bold;'>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Foglalás időpontja</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Típus</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Név</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Telefon</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Munkakör</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>TAJ szám</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Orvos</td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'></td>";
        $html.= "<td nowrap style='border-bottom: 1px solid #888;padding:3px;'>Megjegyzés</td>";
        $html.= "</tr>";
        foreach ($data as $rows) {
            $html.= "<tr>";
            $html.= "<td nowrap>".substr($rows["datum"],0,16)."</td>";
            $html.= "<td nowrap>{$rows["tipusnev"]}</td>";
            $html.= "<td nowrap>{$rows["nev"]}</td>";
            $html.= "<td nowrap>{$rows["telefon"]}</td>";
            $html.= "<td nowrap>{$rows["munkakor"]}</td>";
            $html.= "<td nowrap>{$rows["taj"]}</td>";
            $html.= "<td nowrap>{$rows["orvosnev"]}</td>";
            $html.= "<td nowrap>{$rows["helyszincim"]}</td>";
            $html.= "<td nowrap>{$rows["megj"]}</td>";

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

        $res = sql_query("SELECT * from cegek c where true ".$this->adminUser->cegSQLFilter("c.id")." ORDER BY megnev");

        /*
        echo "<table cellpadding='0' cellspacing='0' border='0'>";
        while ($row = sql_fetch_array($res)) {
            $tc = "tcella";
            if (!isset($first)) {
                echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (trim($row["megnev"]) == "") {
                $row["megnev"] = "nincs neve";
            }

            $options = "";
            if ($row["onlyreg"] == 1) $options .= "<div>Csak regisztráltaknak</div>";
            if ($row["onlybeutalo"] == 1) $options .= "<div>Csak beutalóval lehet foglalni</div>";
            if ($row["no_doctor_select"] == 1) $options .= "<div>Nincs orvos választás a foglalásnál</div>";
            if ($row["fieldoptions"] != "") $options .= "<div>" . $this->displayFieldOptions($row["fieldoptions"]) . "</div>";

            echo "<tr>";
            echo "<td nowrap valign='top'><div class={$tc}><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["megnev"]}</a></div></td>";

            $url = Booking_Constants::SITE_PROTOCOL . "://{$row["domain"]}." . Booking_Constants::SITE_DOMAIN;

            echo "<td nowrap valign='top'><div class='{$tc}'>" . ($row["domain"] == "" ? "" : "{$url} (<a target='_blank' href='{$url}'>open</a>)") . "</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}' style='min-width:300px;padding-right: 10px;'>{$options}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}' style='min-width:50px;'>" . ($row["aktiv"] == 1 ? "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#0a0;'>aktív</a>" : "<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&oaktivtoggle={$row["id"]}' style='color:#f00;'>inaktív</a>") . "</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='alert(\"Nem törölhető!\");return false;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&delete={$row["id"]}'>delete</a>]</div></td>";
            echo "</tr>";
            echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        echo "</table>";
        */


        $tc = "tcella";

        $html.= "<table cellpadding='0' cellspacing='0' border='0' style='margin-top:20px;'>";

        $html.= "<tr style='font-weight: bold;'>";
        $html.= "<td nowrap valign='top'><div class={$tc}>Cég neve</div></td>";
        $html.= "<td nowrap valign='top'><div class={$tc}>Foglalások száma</div></td>";
        $html.= "<td nowrap valign='top'><div class={$tc}>Eljöttek</div></td>";
        $html.= "<td nowrap valign='top'><div class={$tc}></div></td>";
        $html.= "</tr>";

        while ($row = sql_fetch_array($res)) {
            if (empty(trim($row["megnev"]))) {
                $row["megnev"] = "nincs neve";
            }

            $all = $eljott = 0;
            $ress = sql_query("SELECT nev,eljott FROM foglalasok WHERE datum>? and datum<? and aktiv=1 AND cegid=? AND (taj<>'' OR nev<>'') AND nev<>'nincs név'", array($this->tol, $this->ig, $row["id"]));
            while ($rows = sql_fetch_array($ress)) {
                $all++;
                if ($rows["eljott"] == 1) {
                    $eljott++;
                }
            }


            $html.= "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";

            $html.= "<tr>";
            $html.= "<td nowrap valign='top'><div class={$tc}>{$row["megnev"]}&nbsp;&nbsp;</div></td>";
            $html.= "<td nowrap valign='top'><div class={$tc} style='".($all!=0?"font-weight:bold;":"")."'>&nbsp;{$all}</div></td>";
            $html.= "<td nowrap valign='top'><div class={$tc} style='".($all!=0?"font-weight:bold;":"")."'>&nbsp;{$eljott}</div></td>";
            $html.= "<td nowrap valign='top'><div class={$tc}>";
            if ($all > 0) {
                $html.= "[<a href='index.php?page=stat&lista=tetel&cegid={$row["id"]}'>Tételes lista</a>] [<a href='index.php?page=stat&lista=orvos&cegid={$row["id"]}'>Orvos lista</a>]";
            }
            $html.= "</div></td>";
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

