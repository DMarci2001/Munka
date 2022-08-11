<?php

class AdminVaroteremPage extends AdminCorePage
{

    private array $dokirexTipusMap = [

    ];

    private array $colorSigns = [
        0 => ["Dokirexben nem található paciens", "#cfcfc4"],
        1 => ["Van dokirex azonsító, de nincs vizsgálat", "#fdfd96"],
        2 => ["Minden adat rendelkezésre áll", "#94fa92"],
    ];

    private array $szuresTipusok = [];
    private array $vizsgalatiLapLog = [];

    public function __construct()
    {
        parent::__construct();

        if (!isset($_SESSION["setday"])) {
            $_SESSION["setday"] = date("Y-m-d");
        }
        if (isset($_GET["setday"])) {
            $_SESSION["setday"] = $_GET["setday"];
        }


        $szurestipusok = sql_query("select id, megnev from szurestipusok")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($szurestipusok as $szurestipus) {
            $this->szuresTipusok[$szurestipus["id"]] = $szurestipus;
        }

        if (isset($_GET["showtable"])) {
            echo $this->showTable();
            die();
        }

    }

    private function loadVizsgLapLog($date) {
        $logs = sql_query("SELECT * FROM dokirexvizsglaplog WHERE Datum > ? AND Datum < ?", ["{$date} 00:00:00", "{$date} 23:59:59"])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($logs as $log) {
            $this->vizsgalatiLapLog[$log["PaciensID"]][] = $log;
        }
        //echo "<pre>".print_r($this->vizsgalatiLapLog, true)."</pre>";die;
    }

    private function napFilter($setDay):string {
        $html = "";
        $w = date("N", strtotime($setDay));
        $html.= "<input class='napfilter' id='naptarfilter' value='{$setDay} {$this->adminUtils->settings->hetnap[$w]}' style='font-size:18px;background-color:#eee;color:#444;margin-right:10px;border:1px solid #ccc;' data-page='{$_GET["page"]}' />";
        return $html;
    }

    public function showPage() {
        if (!$this->adminUser->statAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        echo "<div id='naptartable'>";
        echo $this->showTable();
        echo "</div>";
        echo "<div id='idoponteditor'></div>";
    }


    private function showTable():string {
        $html = "";
        $today = $_SESSION["setday"];

        $this->loadVizsgLapLog($today);

        $dayStat = sql_query("select min(hour(datum)) as minhour, max(hour(datum)) as maxhour from foglalasok f where f.datum>=? and f.datum<=? and f.aktiv=1 and f.helyszinid=1 order by f.datum", ["{$today} 00:00:00", "{$today} 23:59:59"])->fetch(PDO::FETCH_ASSOC);


        $headerHeight = 52;
        $starHour = $dayStat["minhour"];
        $endHour = $dayStat["maxhour"] + 1;

        $html.= "<div id='filterbox' style='margin-top:10px;'>";
        $html.= "<div style='display:table-cell;vertical-align:middle;'>".$this->napFilter($today)."</div>";
        $html.= "<div style='display:table-cell;vertical-align:middle;font-size: 18px;'><a onclick='setNaptarDay(\"".date("Y-m-d",strtotime("{$today} -1 day"))."\");return false;' href='#'><i class='fas fa-chevron-left' title='Előző nap'></i></a>&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;vertical-align:middle;'><input type='button' onclick='setNaptarDay(\"".date("Y-m-d")."\");' value='MA' title='Ugrás a mai napra' />&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;vertical-align:middle;font-size: 18px;'><a onclick='setNaptarDay(\"".date("Y-m-d",strtotime("{$today} +1 day"))."\");return false;' href='#'><i class='fas fa-chevron-right' title='Következő nap'></i></a></div>";
        $html.= "</div>";


        $html.= "<div style='display:table;margin-top:20px;'>";
        $html.= "<div style='display:inline-block;'>Színkódok:</div>";
        foreach ($this->colorSigns as $colorSign) {
            $html.= "<div style='display:inline-block;margin:5px;padding:2px 5px;background:".$colorSign[1]."'>{$colorSign[0]}</div>";
        }
        $html.= "</div>";

        //$markerPad = round((strtotime($showPosition) - strtotime("today {$starHour} hour")) / 60) * 2 - 9 + $headerHeight;

        $orvosok = sql_query("SELECT o.id AS orvosid, o.nev, COUNT(*) AS db, GROUP_CONCAT(DISTINCT f.`szurestipusid`) AS stids FROM foglalasok f 
            LEFT JOIN orvosok o ON o.id=f.orvosassigned
            WHERE f.datum>=? AND f.datum<=? AND f.aktiv=1 and f.helyszinid=1 GROUP BY o.id
            ORDER BY f.`szurestipusid`, o.nev", ["{$today} 00:00:00", "{$today} 23:59:59"])->fetchAll(PDO::FETCH_ASSOC);

        if (strtotime("now") < strtotime($today)) {
            $html.= "<div style='margin-top: 20px;font-size:18px;color:red;'>Jövőbeni nap megtekintése nem lehetséges</div>";
            return $html;
        }

        if (empty($orvosok)) {
            $html.= "<div style='margin-top: 20px;font-size:18px;color:red;'>Erre a napra nincs foglalás</div>";
            return $html;
        }


        $html.= "<div style='display:table;margin-top:20px;'>";

        $html.= "<div style='display:table-row;'>";

        $padTop = $headerHeight;
        $html.= "<div style='display:table-cell;padding-top: {$padTop}px;vertical-align: top;'>";
        for ($ora = $starHour; $ora <= $endHour; $ora++) {
            $html.= "<div class='vtimemark'>";
            $html.= date("H:i", strtotime("today {$ora} hour"));
            $html.= "</div>";
        }
        $html.= "</div>";


        foreach ($orvosok as $orvos) {
            $html.= "<div style='display:table-cell;min-width:200px;max-width:200px;vertical-align: top;'>";
            $html.= "<div style='background:#888;color:#fff;height:{$headerHeight}px;overflow: hidden;border-right: 1px solid #fff;box-sizing: border-box;padding:5px;'>";
            $html.= "<div style='width:500px;'>";
            $html.= substr($orvos["nev"], 0, 38);
            $stids = explode(",", $orvos["stids"]);
            $szurestipusok = [];
            foreach ($stids as $stid) {
                $szurestipusok[] = $this->szuresTipusok[$stid]["megnev"];
            }
            $html.= "<div style='color:#ddd;font-size: 11px;'>" . implode(", ", $szurestipusok) . "</div>";
            $html.= "<div style=''>timestat{$orvos["orvosid"]}</div>";
            $html.= "</div>";
            $html.= "</div>";


            $timeSum = $timeCount = 0;

            $reservations = sql_query("select * from foglalasok f where f.datum>=? and f.datum<=? and f.orvosassigned=? and f.aktiv=1 and f.helyszinid=1 order by f.datum", ["{$today} 00:00:00", "{$today} 23:59:59", $orvos["orvosid"]])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($reservations as $reservation) {
                $timeData =  $this->calcTimeData($reservation["dokirex_userid"]);

                $futureStyle = "";
                if (strtotime($reservation["datum"]) > strtotime("now")) {
                    $futureStyle = "opacity:.5;";
                }

                $colorSignCode = 0;
                if ($reservation["dokirex_userid"] > 0) {
                    $colorSignCode = 1;
                }
                if (!empty($timeData["diff"])) {
                    $colorSignCode = 2;
                }

                $detailURL = "showIdopontEditor(\"{$_GET["page"]}\",\"{$reservation["pass"]}\",{$reservation["id"]});";

                $padTop = round((strtotime($reservation["datum"]) - strtotime("{$today} {$starHour} hour")) / 60) * 2;
                $height = ($reservation["rinterval"] = 0 ? 15:$reservation["rinterval"]) * 2 - 1;
                $html.= "<div onclick='{$detailURL}' style='position:absolute;margin-top:{$padTop}px;height:{$height}px;width:198px;background:".$this->colorSigns[$colorSignCode][1].";padding:1px 2px;overflow:hidden;cursor:pointer;box-sizing: border-box;{$futureStyle}'>";
                $html.= "<div style='width:500px;'>";

                if (isset($this->vizsgalatiLapLog[$reservation["dokirex_userid"]])) {
                    //echo "ok".print_r($this->vizsgalatiLapLog[$reservation["dokirex_userid"]], true);
                }

                if ($reservation["nev"] == "nincs név") {
                    $reservation["nev"] = "Foglalt";
                }


                $html.= date("H:i", strtotime($reservation["datum"]))." ".$reservation["nev"];

                if ($reservation["dokirex_userid"] > 0) {
                    //$html.= " <span title='{$reservation["dokirex_userid"]}' style=''>(d)</span>";
                    //$html.= " {$timeData["FelhasznaloSzakrendelesID"]}";
                }


                if (!empty($timeData["diff"])) {
                    $html.= "<div style='font-size:11px;'>Időtartam: ".date("H:i", strtotime($timeData["start"]))." - ".date("H:i", strtotime($timeData["end"]))." (<strong>".round(gmdate("i", $timeData["diff"]))." perc</strong>)</div>";
                    $timeCount++;
                    $timeSum += $timeData["diff"];
                }
                $html.= "</div>";
                $html.= "</div>";
            }

            if ($timeCount > 0) {
                $averageTime = round(gmdate("i", round($timeSum / $timeCount)));
                $html = str_replace("timestat{$orvos["orvosid"]}", "Átlagos vizsgálat idő: <strong>{$averageTime} perc</strong>", $html);
            } else {
                $html = str_replace("timestat{$orvos["orvosid"]}", "", $html);
            }




            $html.= "</div>";
        }


        $html.= "</div>";


        //table end
        $html.= "</div>";

        return $html;
    }


    private function calcTimeData($paciensId):array {
        $data = [];
        if ($paciensId != 0) {
            foreach ($this->vizsgalatiLapLog[$paciensId] as $log) {
                //print_r($log);die;
                if ($log["Muvelet"] == "insert") {
                    $data["start"] = $log["Datum"];
                }
                if ($log["Muvelet"] == "create") {
                    $data["end"] = $log["Datum"];
                }
                $data["FelhasznaloSzakrendelesID"] = $log["FelhasznaloSzakrendelesID"];
            }

            if (isset($data["start"]) && isset($data["end"])) {
                $data["diff"] = round(abs(strtotime($data["end"]) - strtotime($data["start"])));
            }

            //print_r($data);

        }
        return $data;
    }




}