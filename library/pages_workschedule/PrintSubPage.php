<?php


class PrintSubPage extends AdminCorePage
{
    private $workScheduleService;

    private $startHour = 7;
    private $endHour   = 19;
    private $hourWidth = 40;
    private $thisDay;
    private $napszak;

    public function __construct(WorkScheduleService $service)
    {
        parent::__construct();

        $this->workScheduleService = $service;
        $this->settings = new Booking_Settings();
        //$this->service->reloadScheduleMapping();

    }

    public function showPage():string
    {
        $html = "";

        $html .= "<div id='noifylist' style=''>";
        //$html.= print_r($this->service->scheduleMapping, true);
        $html .= "<h2>Nyomtatási nézet</h2>";
        //$html .= "fejlesztés alatt, később visszateszem.";
        $html .= $this->printWeek();
        $html .= "</div>";

        return $html;
    }


    private function calcOffset($i):string {
        $offset = $_SESSION["wpoffset"];
        $off = $offset*7+$i;
        if ($off >= 0) {
            $off = "+{$off}";
        }
        return $off;
    }

    private $minMaxMatrix;

    private function _datumRow():string {
        $html = "";
        $html.= "<div style='display:table-row;'>";
        for ($i = 0; $i < 7; $i++) {
            $off = $this->calcOffset($i);
            $this->thisDay = date("Y-m-d", strtotime("this week monday {$off} day"));

            $html.= "<div style='display:table-cell;'>";
            $html.= "<div class='scheduledayhead'>".$this->adminUtils->magyarDatum($this->thisDay)."</div>";
            $html.= "</div>";
        }
        $html.= "</div>";
        return $html;
    }

    private function _hourRow():string {
        $html = "";
        $html.= "<div style='display:table-row;'>";
        for ($i = 0; $i < 7; $i++) {
            $off = $this->calcOffset($i);
            $this->thisDay = date("Y-m-d", strtotime("this week monday {$off} day"));

            $this->startHour = $this->minMaxMatrix[$this->thisDay]["min"];
            $this->endHour   = $this->minMaxMatrix[$this->thisDay]["max"];

            $html.= "<div style='display:table-cell;'>";
            $html.= "<div class='sch_oszlopfejcell_print' style='max-width:80px;'>Rendelő</div>";
            $html.= "<div class='sch_oszlopfejcell'>";
            for ($ora = $this->startHour; $ora <= $this->endHour; $ora++) {
                $html.= "<div style='display:inline-block;padding-top:8px;height:22px;width:{$this->hourWidth}px;font-weight: normal;border-left:1px solid #ccc;'>".date("H:00", strtotime("{$ora}:00"))."</div>";
            }
            $html.= "</div>";
            $html.= "</div>";
        }
        $html.= "</div>";
        return $html;
    }

    private function _beoRows($kulso = 0):string {
        $this->napszak = 0;
        if ($kulso == 1) {
            $this->napszak = 2;
        }
        $html = "";
        $resTipus = sql_query("select * from schedule_tipusok where kulso=? order by roleid, sorrend", [$kulso]);
        while ($tipusData = sql_fetch_array($resTipus)) {
            $html.= "<div style='display:table-row;'>";
            for ($i = 0; $i < 7; $i++) {
                $off = $this->calcOffset($i);
                $this->thisDay = date("Y-m-d", strtotime("this week monday {$off} day"));

                $this->startHour = $this->minMaxMatrix[$this->thisDay]["min"];
                $this->endHour   = $this->minMaxMatrix[$this->thisDay]["max"];

                $html.= "<div style='display:table-cell;vertical-align:top;border-left:1px solid #ddd;border-top:1px solid #ddd;".($tipusData["roleid"]!=1?"background:#daeef3;":"")."'>";
                $html.= "<div class='sch_oszlopdatacell_print'>{$tipusData["megnev"]}</div>";
                $html.= $this->_workerCell($tipusData);
                $html.= "</div>";
            }
            $html.= "</div>";
        }

        return $html;
    }

    private function _beoRows_compact($kulso = 0):string {
        $this->napszak = 0;
        if ($kulso == 1) {
            $this->napszak = 2;
        }
        $html = "";
        $resTipus = sql_query("select * from schedule_tipusok where kulso=? order by roleid, sorrend", [$kulso]);
        while ($tipusData = sql_fetch_array($resTipus)) {
            $html.= "<div style='display:table-row;'>";
            for ($i = 0; $i < 7; $i++) {
                $off = $this->calcOffset($i);
                $this->thisDay = date("Y-m-d", strtotime("this week monday {$off} day"));

                $this->startHour = $this->minMaxMatrix[$this->thisDay]["min"];
                $this->endHour   = $this->minMaxMatrix[$this->thisDay]["max"];

                $extrastyle = ($tipusData["roleid"]!=1?"background:#daeef3;":"");
                $html.= "<div style='display:table-cell;width:400px;white-space:normal;min-width:400px;max-width:400px;vertical-align:top;border-left:1px solid #ddd;border-top:1px solid #ddd;{$extrastyle}'>";
                $html.= "<div class='sch_oszlopdatacell_print'>{$tipusData["megnev"]}</div>";
                $html.= $this->_workerCell_compact($tipusData);
                $html.= "</div>";
            }
            $html.= "</div>";
        }

        return $html;
    }

    public function printWeek():string
    {
        $this->napszak = 0;

        $html = "<div>készül...</div>";

        $this->minMaxMatrix = [];

        for ($i = 0; $i < 7; $i++) {
            $off = $this->calcOffset($i);
            $this->thisDay = date("Y-m-d", strtotime("this week monday {$off} day"));
            $startFilter   = date("Y-m-d 00:00:00", strtotime("this week monday {$off} day"));
            $endFilter     = date("Y-m-d 23:59:59", strtotime("this week monday {$off} day"));

            $minMaxData = sql_query("SELECT MIN(datumfrom), MAX(datumto), MIN(HOUR(datumfrom)) AS minhour, MAX(HOUR(datumto)) AS maxhour, MINUTE(MAX(datumto)) AS maxminute, COUNT(*) AS hany FROM schedule_mapping m WHERE m.`datumfrom`>=? AND m.`datumto`<=?", [$startFilter, $endFilter])->fetch();

            $min = 8;
            $max = 14;

            if ($minMaxData["minhour"] < $min && $minMaxData["minhour"] != 0) {
                $min = $minMaxData["minhour"];
            }
            if ($minMaxData["maxhour"] > $max) {
                $max = $minMaxData["maxhour"];
            }
            if ($minMaxData["maxminute"] > 0) {
                $max++;
            }

            $this->minMaxMatrix[$this->thisDay] = ["min" => $min, "max" => $max, "count" => $minMaxData["hany"]];
        }

        $html.= "<div id='schedulesubpage' style='white-space: nowrap;margin-top:20px;'>";
        $html.= "<div style='display:table;'>";

        //$html.= $this->_datumRow();
        //$html.= $this->_hourRow();
        //$html.= $this->_beoRows();
        //$html.= $this->_datumRow();
        //$html.= $this->_hourRow();
        //$html.= $this->_beoRows(1);

        $html.= $this->_datumRow();
        $html.= $this->_beoRows_compact();
        $html.= $this->_datumRow();
        $html.= $this->_beoRows_compact(1);

        $html.= "</div>";
        $html.= "</div>";

        return $html;
    }


    private function _workerCell_compact($tipusData) {
        $roleId = $tipusData["roleid"];
        $tipusName = $tipusData["megnev"];

        $extraStyle = ($roleId==3?" style='background:#daeef3;'":"");

        $html = $noverHtml = $orvosHtml = "";

        $html.="<div class='sch_oszlopdatacell_print2' {$extraStyle} data-datum='{$this->thisDay}' data-napszak='{$this->napszak}' data-roleid='{$roleId}' data-tipusid='{$tipusData["id"]}'>";
        if (isset($this->workScheduleService->scheduleMapping["{$this->thisDay}_{$this->napszak}_{$tipusData["id"]}"])) {
            $mappings = $this->workScheduleService->scheduleMapping["{$this->thisDay}_{$this->napszak}_{$tipusData["id"]}"];
            foreach ($mappings as $mapping) {
                if ($mapping["roleid"] != $roleId) {
                    //continue;
                }

                $c = "#ab2323";
                if ($mapping["roleid"] == 2) {
                    $c = "#1ca48d";
                }
                if ($mapping["roleid"] == 3) {
                    $c = "#779ecc";
                }

                $h = "<div style='display:inline-block;margin:1px 3px 1px 0px;background:{$c};color:#fff;padding:2px 3px;'>";
                $h .= "<div class='workerlinkprint'>";
                $h.= "{$mapping["workernev"]} ";
                $h .= $this->workScheduleService->workInterval($mapping);
                $h .= "</div>";
                $h .= "<div class='workermegj'>";
                $h .= "<div style='font-style:italic;'>{$mapping["megj"]}</div>";
                $h .= "</div>";
                $h .= "</div>";

                if ($mapping["roleid"] == 1) {
                    $orvosHtml .= $h;
                } else {
                    $noverHtml .= $h;
                }
            }
            $html.= $orvosHtml;
            $html.= $noverHtml;
        }

        $html.="</div>";
        return $html;
    }



}