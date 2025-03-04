<?php

class AdminCancelsPage extends AdminCorePage
{

    private $answerWords = ["", "A", "B", "C", "D", "E"];

    public function __construct()
    {
        parent::__construct();



        if (isset($_GET["download"])) {
            die("fejlesztés alatt..");
            /*
            $service = new ExcelService();

            $valaszok["list"] = sql_query("SELECT * FROM vizsgavalaszok where true order by datum")->fetchAll(PDO::FETCH_ASSOC);

            $service->vizsgaList($valaszok);
            $service->setFileName("Elsosegely_vizsgazok_lista_" . date("Y-m-d").".xlsx");
            $service->outputSpreadSheet();
            */

        }

    }

    public function showPage() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        //if (!$this->adminUtils->szurestipusModJog()) {
        //    return;
        //}

        $startTime = date("Y-m-01 00:00:00", strtotime("-1 month"));
        $endTime = date("Y-m-01 00:00:00");

        $cancelLogs = sql_query("select * from activitylog l where tipus='foglalastorles' and !instr(megnev, 'nincs név') and !instr(megnev, 'egyeztet') and !instr(megnev, 'teszt') and datum>? and datum<?", [$startTime, $endTime])->fetchAll(PDO::FETCH_ASSOC);

        echo "<h1>Lemondott foglalások</h1>";

        echo "<div style='margin-bottom:10px;'><a href='index.php?page={$_GET["page"]}&download'>Letöltés</a></div>";
        echo "<div style='margin-bottom:10px;'>".count($cancelLogs)." sor</div>";


        echo "<table cellpadding='0' cellspacing='0' border='0'>";


        echo "<tr style='background:#eee;'>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 5px;'>Törlés ideje</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Időpont</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Különbség (óra)</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Cég</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Szöveg</div></td>";
        echo "</tr>";

        foreach ($cancelLogs as $cancelData) {
            $reservationData = json_decode($cancelData["query"], JSON_OBJECT_AS_ARRAY);

            $tc = "tcella";
            if (!isset($first)) {
                echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            echo "<tr>";

            //$szuldatum = $data["szuldatumev"]."-".substr("00{$data["szuldatumho"]}", -2)."-".substr("00{$data["szuldatumnap"]}", -2);
            $diffHour = round((strtotime($reservationData["datum"]) - strtotime($cancelData["datum"]))/3600);

            if ($diffHour > 48) {
                continue;
            }

            if (!$cegData = sql_query("select megnev from cegek where id=?", [$reservationData["cegid"]])->fetch(PDO::FETCH_ASSOC)) {
                $cegData["megnev"] = "";
            }

            echo "<td nowrap valign='top'><div class='{$tc}'>{$cancelData["datum"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$reservationData["datum"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$diffHour}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$cegData["megnev"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$cancelData["megnev"]}</div></td>";

            echo "</tr>";

            echo "<tr><td colspan='18' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }

        echo "</table>";

        error_reporting(0);
    }






}