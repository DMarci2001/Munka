<?php


class AdminVehiclesPage extends AdminCorePage
{

    //private $service;
    private array $months = ["","január","február","március","április","május","június","július","augusztus","szeptember","október","november","december"];
    private array $weekDays = ["","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap"];
    private array $weekDaysShort = ["","h","k","sz","cs","p","sz","v"];

    const VEHICLE_ROLEID = 4;

    public function __construct()
    {
        parent::__construct();

        //$this->service = new DailyStatService();

        if (!isset($_SESSION["vehiclestatoffset"])) {
            $_SESSION["vehiclestatoffset"] = 0;
        }

        if (isset($_GET["movemonth"])) {
            $_SESSION["vehiclestatoffset"] += intval($_GET["movemonth"]);
            echo $this->displayCalendar($_SESSION["vehiclestatoffset"]);
            die;
        }

        if (isset($_POST["addvehicle"])) {
            $result = ["status" => "ok", "message" => ""];

            if (!isset($_POST["workerselector"])) {
                $result = ["status" => "error", "message" => "Válassz dolgozót!"];
            }

            if ($result["status"] == "ok") {
                $datumStart = "{$_POST["datumtol"]} 00:00:00";
                $datumEnd   = "{$_POST["datumtol"]} 00:00:00";

                $params = [
                    "createdBy" => $this->adminUser->user["id"],
                    "datumFrom" => $datumStart,
                    "datumTo"   => $datumEnd,
                    "napszak"   => 0,
                    "tipusId"   => 0,
                    "roleId"    => self::VEHICLE_ROLEID,
                    "workerId"  => $_POST["workerselector"],
                    "megj"  => $_POST["megj"]
                ];

                if ($_POST["mapid"] == 0) {
                    sql_query("insert into schedule_mapping set createdat=now(), createdby=:createdBy, datumfrom=:datumFrom, datumto=:datumTo, napszak=:napszak, tipusid=:tipusId, roleid=:roleId, workerid=:workerId, megj=:megj", $params);
                } else {
                    $params["id"] = $_POST["mapid"];
                    sql_query("update schedule_mapping set createdby=:createdBy, datumfrom=:datumFrom, datumto=:datumTo, napszak=:napszak, tipusid=:tipusId, roleid=:roleId, workerid=:workerId, megj=:megj where id=:id", $params);
                }

                $result["message"] = "sdfs".$this->displayCalendarDayBox($_POST["datum"]);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["showvehicledialog"])) {
            $mapId = intval($_POST["mapid"]);
            if ($mapId!=0) {
                $mapData = sql_fetch_array(sql_query("select * from schedule_mapping where id=?", array($mapId)));
            }

            echo "<div style='display:table-cell;vertical-align: top;padding-right: 10px;'>";
            echo "<input type='hidden' name='napszak' value='{$_POST["napszak"]}' />";
            echo "<input type='hidden' name='mapid' value='{$mapId}' />";
            echo "<input type='hidden' name='roleid' value='{$_POST["roleid"]}' />";
            echo "<input type='hidden' name='datum' value='{$_POST["datum"]}' />";
            echo "<input type='hidden' name='tipusid' value='{$_POST["tipusid"]}' />";
            echo "<select size='6' name='workerselector' id='workerselector' style='width:210px;'>";
            $vehicles = sql_query("select * from schedule_workers where roleid=? order by roleid, nev", [self::VEHICLE_ROLEID])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($vehicles as $vehicle) {
                $checked = "";
                if (isset($mapData) && $mapData["workerid"] == $vehicle["id"]) {
                    $checked = "selected";
                }
                echo "<option value='{$vehicle["id"]}' {$checked}>{$vehicle["nev"]}</option>";
            }
            echo "</select>";
            echo "</div>";

            echo "<div style='display:table-cell;vertical-align: top;'>";
            echo "<select id='datumtol' name='datumtol' style='width:90px;'>";
            echo "<option value='{$_POST["datum"]}'>{$_POST["datum"]}</option>";
            echo "</select> - ";

            echo "<select id='datumig' name='datumig' style='width:90px;'>";
            for ($i = 0; $i < 14; $i++) {
                $datum = date("Y-m-d", strtotime("{$_POST["datum"]} +{$i} day", $_POST["datum"]));
                echo "<option value='{$datum}'>{$datum}</option>";
            }
            echo "</select> ";

            echo "<div style='padding-top:10px;'>";
            $megj = (isset($mapData)?$mapData["megj"]:"");
            echo "<textarea id='megj' name='megj' placeholder='megjegyzés' style='width:224px;height:50px;'>{$megj}</textarea>";
            echo "</div>";

            echo "<div style='padding-top:10px;'>";
            $buttonText = $mapId == 0?"+ hozzáadás":"mentés";
            echo "<input type='button' onclick='Vehicle.AddVehicle();' value='{$buttonText}'>";
            if ($mapId != 0) {
                echo " <input type='button' onclick='Vehicle.DeleteWorkerMap();' value='Törlés'>";
            }
            echo "</div>";
            echo "</div>";
            die;
        }

        $GLOBALS["css"][] = "dailystat.css";
        $GLOBALS["javascript"][] = "dailystat.js";

        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }

    public function showPage()
    {
        if (!$this->adminUser->statAccess()) {
            echo "nincs jogosultságod!";
            return;
        }

        echo "<div id='debugarea'></div>";


        /*
        echo "<div id='uploadarea'>";
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo "<div class='upload-btn-wrapper'><a href='#' onclick='return false;' class='dailystatuploadbutton'>Külső adatok feltöltése</a><input type='file' id='dailystatfile' class='dailystatfile' name='dailystatfile[]' multiple /></div>";
        echo "</div>";
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo "<div><img id='dailystatloader' style='display:none;opacity:.5;height:25px;margin-left:10px;' src='/images/loading_transparent.svg' /></div>";
        echo "</div>";
        echo "</div>";
        */

        echo "<div id='dailystattable'>";
        echo $this->displayCalendar($_SESSION["vehiclestatoffset"]);
        echo "</div>";

        echo "<div id='schdialog' class='sch_dialog'><div class='sch_dialogtop'></div><form name='dialogform' id='dialogform' method='post'><div class='sch_dialogcontent'></div></form></div>";
    }



    public function displayCalendar($offset):string {
        $html         = "";
        $now          = date("Y-m-01");
        $year         = date("Y",strtotime("{$now} +{$offset} month"));
        $month        = intval(date("n",strtotime("{$now} +{$offset} month")));
        $monthText    = date("F",strtotime("{$now} +{$offset} month"));
        $numberOfDays = date("t",strtotime("{$now} +{$offset} month"));
        $firstDay     = date("N",strtotime("first day of {$year} {$monthText}"));
        $firstDate    = date("Y-m-d",strtotime("first day of {$year} {$monthText}"));
        $firstDateY   = date("Y-01-01",strtotime("first day of {$year}"));
        $lastDate     = date("Y-m-d",strtotime("last day of {$year} {$monthText}"));
        $lastDateY    = date("Y-m-d");
        $weekDay      = 0;

        $emptyAll = true;

        $html.= "<table class='montlytable' style='margin:0px;padding:0px;'>";
        $html.= "<tr>";
        $html.= "<td colspan='1' class='montlycell mthead' style='text-align: left;'><a href='#' onclick='vehicleMoveMonth(-1);return false;'><i class='fas fa-chevron-circle-left'></i></a></td>";
        $html.= "<td colspan='5' class='montlycell mthead'>";

        $html .= "<div>&nbsp;&nbsp;{$year} " . $this->months[$month] . "&nbsp;&nbsp;</div>";

        if (!$emptyAll) {
            $html.= "tartalom";
        }

        $html.= "</td>";
        $html.= "<td colspan='1' class='montlycell mthead' style='text-align: right;'><a href='#' onclick='vehicleMoveMonth(1);return false;'><i class='fas fa-chevron-circle-right'></i></a></td>";
        $html.= "</tr>";
        $html.= "<tr>";
        for ($i = 1; $i <= 7 ;$i++) {
            $html.= "<td class='monthlycell mtweekday' style=''>".$this->weekDays[$i]."</td>";
        }
        $html.= "</tr><tr>";

        for ($i = 1; $i < $firstDay ;$i++) {
            $weekDay++;
            $html.= "<td class='monthlycell' style='background: white;'>&nbsp;</td>";
        }

        for ($nap = 1; $nap <= $numberOfDays; $nap++) {
            $thisDay = "{$year}-".substr("00{$month}",-2)."-".substr("00{$nap}",-2);

            if ($weekDay++ >= 7) {
                //plusz cell
                $html.= "<td style='text-align: center;padding:10px;'>+ cell</td>";
                $html.= "</tr><tr>";
                $weekDay = 1;
            }

            $html.= "<td id='daybox{$thisDay}'>";
            $html.= $this->displayCalendarDayBox($thisDay);
            $html.= "</td>";
        }

        $html.= "</tr>";
        $html.= "</table>";
        return $html;
    }


    private function displayCalendarDayBox($day):string {
        $html = "";

        $napClass = "calday";
        $diff = (strtotime($day) - strtotime(date("Y-m-d"))) / (3600*24);

        $boxStyle = ($diff == 0?"background:#f0e0e0":"");

        $html.= "<div class='monthlycell' style='{$boxStyle}'>";
        $html.= "<div style='text-align: center;font-weight: bold;font-size: 22px;'><div title='' onclick='alert(\"{$day}\");' class='{$napClass}'>".date("d", strtotime($day))."</div></div>";

        if (strtotime("now") > strtotime($day)) {
            $html .= "<div data-day='{$day}' id='daytext{$day}' style='padding-top:0px;'>";

            $emptyAll = false;

            $html .= "<div><img id='dailystatloader{$day}' style='display:none;opacity:.5;height:30px;margin-top:10px;' src='/images/loading_transparent.svg' /></div>";

            $html .= "<div id='datablock{$day}' style='margin-top:10px;'>";
            $html .= "<div style='height:70px;overflow: hidden;'>";
            if (!$emptyAll) {
                $html .= "<div>content</div>";
            }
            $html .= "</div>";
            $html .= "<div style='margin-top:10px;'>";
            if (!$emptyAll) {
                $html .= "<div class='dailysmallbutton' data-datum='{$day}' data-mapid='0' data-div='daybox{$day}' data-day='dayvalid' onclick='Vehicle.ShowVehicleDialog(this);' title='Napi statisztika letöltése'><i class='fa-solid fa-car'></i> hozzáadás</div> ";
            }
            $html .= "</div>";
            $html .= "</div>";

            $html .= "</div>";
        }
        $html.= "</div>";

        return $html;
    }


}

