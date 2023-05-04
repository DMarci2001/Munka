<?php


class AdminManagerStatusPage extends AdminCorePage
{
    public function __construct()
    {
        parent::__construct();

        if (isset($_POST["showmanagerstat"])) {
            echo $this->managerStatList(intval($_POST["num"]));
            die;
        }

        if (isset($_GET["torvenyszekstatdownload"])) {
            //error_reporting(E_ALL);
            //ini_set('display_errors', 1);

            $fileName = "Törvényszék statisztika.xlsx";

            @unlink(DailyStatService::getTempFileName());
            $excelService = new ExcelService();
            $excelService->torvenyszekStat();
            $excelService->setFileName($fileName);
            $excelService->outputSpreadSheetFile(DailyStatService::getTempFileName());

            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-Disposition: attachment;filename=\"{$fileName}\"");
            header("Cache-Control: max-age=0");

            echo file_get_contents(DailyStatService::getTempFileName());

            //echo "aaa";
            die;
        }

    }

    public function showPage()
    {
        if (!$this->adminUser->beallitasAccess()) {
            echo "nincs jogosultságod!";
            return;
        }

        echo "<div style=''><img id='loadingspinner' style='margin-left:5px;height:25px;display:none;' src='/images/loading.svg' /></div>";

        if (isset($_GET["torvenyszekstat"])) {
            echo "<div>";
            echo $this->torvenyszekStat();
            echo "</div>";
            return;
        }

        echo "<div id='managerlista'></div>";
        echo "<div id='debugcontainer'></div>";
        echo "<script>$(document).ready(function() { showManagerStat(14); });</script>";
    }



    public function managerStatList($numOfDays):string {
        $html = "";

        $bookingService = new BookingService();
        $bookingService->setHelyszin(Booking_Constants::DEFAULT_PLACE_IDS[0]);
        $bookingService->setNeme(0);

        $packs = sql_query("select id, megnev from szurestipusok where ispack=1 and aktiv=1 and instr(megnev, 'platina') order by megnev")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($packs as $pack) {
            $genderIds = [];
            $res = sql_query("select * from szurescsomagok_kapcs where csomagid=?", [$pack["id"]]);
            while ($row = sql_fetch_array($res)) {
                $genderIds[$row["nemerequired"]][] = $row["szurestipusid"];
            }


            $bookingService->setSzuresTipus($pack["id"]);
            $availableData = [];
            for ($i = 0; $i < $numOfDays; $i++) {
                $day = date("Y-m-d", strtotime("now + {$i} day"));
                if (in_array(date("N", strtotime($day)), [6, 7])) {
                    continue;
                }
                $availableData[$day] = $bookingService->getPackageAvailabilityForDay($day);
            }

            $html.= "<h2>{$pack["megnev"]}</h2>";
            //$html.= print_r($bookingService->packContentTypes, true);
            //$html.= "<pre>".print_r($availableData, true)."</pre>";

            $html.= "<div style='display: table;'>";
            $html.= "<div style='display: table-row;'>";
            $html.= "<div style='display:table-cell;'>&nbsp;</div>";
            foreach ($availableData as $day => $available) {
                if (in_array(date("N", strtotime($day)), [6, 7])) {
                    continue;
                }
                $genderFree[$day][1] = true;
                $genderFree[$day][2] = true;
                $weekDay = date("l", strtotime($day));
                $html.= "<div style='display:table-cell;font-weight: bold;text-align: center;padding:1px 2px;vertical-align: center;'>{$day}<br/>{$weekDay}</div>";
            }

            $html.= "</div>";

            foreach ($bookingService->packContentTypes as $packTypeId) {
                $packType = sql_query("SELECT t.id, t.megnev FROM szurestipusok t where t.id=?", [$packTypeId])->fetch(PDO::FETCH_ASSOC);
                $html.= "<div style='display: table-row;'>";
                $html.= "<div style='display:table-cell;font-weight: bold;'>{$packType["megnev"]}</div>";
                foreach ($availableData as $day => $available) {
                    if (in_array(date("N", strtotime($day)), [6, 7])) {
                        continue;
                    }
                    $status = "<span style='color:red;'>nincs hely</span>";
                    if (isset($available["timeTableForPackage"][$packTypeId])) {
                        $status = "<span style='color:green;'>van hely</span>";
                    } else {
                        if (in_array($packTypeId, $genderIds[1])) {
                            $genderFree[$day][1] = false;
                        }
                        if (in_array($packTypeId, $genderIds[2])) {
                            $genderFree[$day][2] = false;
                        }
                    }
                    $html.= "<div style='display: table-cell;text-align: center;padding:1px 2px;vertical-align: center;'>{$status}</div>";
                }
                $html.= "<div style='display:table-cell;font-weight: bold;'>{$packType["megnev"]}</div>";
                $html.= "</div>";
            }

            $html.= "<div style='display:table-row;border-top:1px solid #ccc;'>";
            $html.= "<div style='display:table-cell;font-weight: bold;border-top:1px solid #ccc;'>Férfi</div>";
            foreach ($availableData as $day => $available) {
                $html.= "<div style='display:table-cell;font-weight: bold;text-align: center;padding:1px 2px;vertical-align: center;border-top:1px solid #ccc;'>".($genderFree[$day][1] ? "<span style='color:green;'>van hely</span>":"<span style='color:red;'>nincs hely</span>")."</div>";
            }
            $html.= "</div>";

            $html.= "<div style='display:table-row;'>";
            $html.= "<div style='display:table-cell;font-weight: bold;'>Nő</div>";
            foreach ($availableData as $day => $available) {
                $html.= "<div style='display:table-cell;font-weight: bold;text-align: center;padding:1px 2px;vertical-align: center;'>".($genderFree[$day][2] ? "<span style='color:green;'>van hely</span>":"<span style='color:red;'>nincs hely</span>")."</div>";
            }
            $html.= "</div>";



            $html.= "</div>";

        }

        return $html;
    }


    const TORVENYSZEK_DOCTOR_ID = 354;
    const TORVENYSZEK_COMPANY_ID = 56;

    public function torvenyszekStat():string {
        $html = "";

        $weekStat = [];
        $monthStat = [];

        $statDays = sql_query("SELECT DATE(datum) as nap, MIN(TIME(datum)) as mintime, MAX(TIME(DATE_ADD(f.datum, INTERVAL f.rinterval MINUTE))) as maxtime, (UNIX_TIMESTAMP(MAX(DATE_ADD(f.datum, INTERVAL f.rinterval MINUTE))) - UNIX_TIMESTAMP(MIN(datum)))/3600 AS rendelesora, COUNT(*) AS paciensek, SUM(eljott) AS eljottek, GROUP_CONCAT(cegid) AS cegek 
            FROM foglalasok f
            WHERE f.orvosassigned=? AND !INSTR(f.nev,'nincs név') AND !INSTR(f.nev,'ebéd') AND !INSTR(f.nev,'ne foglal') AND DATE(f.datum)<DATE(NOW()) and f.datum>'2022-01-01 00:00:00'
            GROUP BY DATE(datum)
            ORDER BY datum", [self::TORVENYSZEK_DOCTOR_ID])->fetchAll(PDO::FETCH_ASSOC);

        $html.= "<table>";
        $html.= "<tr style='font-weight: bold;background:#eee;'>";
        $html.= "<td style='padding:2px;'>Nap</td>";
        $html.= "<td style='padding:2px;'>Kezdés</td>";
        $html.= "<td style='padding:2px;'>Vége</td>";
        $html.= "<td style='padding:2px;'>Rendelési óraszám</td>";
        $html.= "<td style='padding:2px;'>Paciensek száma</td>";
        $html.= "<td style='padding:2px;'>Ebből Törvényszékes paciens</td>";
        $html.= "<td style='padding:2px;'>Egyéb cég paciense</td>";
        $html.= "<td style='padding:2px;'>Paciens / óra</td>";
        $html.="</tr>";

        foreach ($statDays as $statDay) {
            $html.="<tr>";

            $paciensPerHout = round($statDay["paciensek"] / $statDay["rendelesora"], 1);
            $companyCounts = array_count_values(explode(",", $statDay["cegek"]));
            $torvenyszekPaciensCount = $companyCounts[self::TORVENYSZEK_COMPANY_ID];
            if (empty($torvenyszekPaciensCount)) {
                $torvenyszekPaciensCount = 0;
            }

            $month = date("Y-m", strtotime($statDay["nap"]));
            $monthStat[$month]["hours"] += $statDay["rendelesora"];
            $monthStat[$month]["paciensek"] += $statDay["paciensek"];
            $monthStat[$month]["torvenyszekpaciensek"] += $torvenyszekPaciensCount;

            $week = date("Y-W", strtotime($statDay["nap"]));
            $weekStat[$week]["hours"] += $statDay["rendelesora"];
            $weekStat[$week]["paciensek"] += $statDay["paciensek"];
            $weekStat[$week]["torvenyszekpaciensek"] += $torvenyszekPaciensCount;

            $html.= "<td>{$statDay["nap"]} ".date("l", strtotime($statDay["nap"]))."&nbsp;</td>";
            $html.= "<td>".substr($statDay["mintime"], 0, 5)."</td>";
            $html.= "<td>".substr($statDay["maxtime"], 0, 5)."</td>";
            $html.= "<td style='text-align: right;'>".round($statDay["rendelesora"],1)."</td>";
            $html.= "<td style='text-align: right;'>{$statDay["paciensek"]}</td>";
            $html.= "<td style='text-align: right;'>{$torvenyszekPaciensCount}</td>";
            $html.= "<td style='text-align: right;'>".($statDay["paciensek"] - $companyCounts[self::TORVENYSZEK_COMPANY_ID])."</td>";
            $html.= "<td style='text-align: right;'>{$paciensPerHout}</td>";

            $html.="</tr>";
        }

        $html.= "</table>";

        /* -------------- */

        $html.= "<table style='margin-top: 20px;'>";
        $html.= "<tr style='font-weight: bold;background:#eee;'>";
        $html.= "<td style='padding:2px;'>Hónap</td>";
        $html.= "<td style='padding:2px;'>Rendelési óraszám</td>";
        $html.= "<td style='padding:2px;'>Paciensek száma</td>";
        $html.= "<td style='padding:2px;'>Ebből Törvényszékes paciens</td>";
        $html.= "<td style='padding:2px;'>Egyéb cég paciense</td>";
        $html.= "<td style='padding:2px;'>Paciens / óra</td>";
        $html.="</tr>";

        foreach ($monthStat as $honap => $stat) {
            $html.="<tr>";

            $paciensPerHout = round($stat["paciensek"] / $stat["hours"], 1);

            $html.= "<td>".substr($honap, 0, 4)." ".date("F", strtotime("{$honap}-01"))."&nbsp;</td>";
            $html.= "<td style='text-align: right;'>".round($stat["hours"])."</td>";
            $html.= "<td style='text-align: right;'>{$stat["paciensek"]}</td>";
            $html.= "<td style='text-align: right;'>{$stat["torvenyszekpaciensek"]}</td>";
            $html.= "<td style='text-align: right;'>".($stat["paciensek"] - $stat["torvenyszekpaciensek"])."</td>";
            $html.= "<td style='text-align: right;'>{$paciensPerHout}</td>";

            $html.="</tr>";
        }

        $html.= "</table>";

        /* -------------- */

        $html.= "<table style='margin-top: 20px;'>";
        $html.= "<tr style='font-weight: bold;background:#eee;'>";
        $html.= "<td style='padding:2px;'>Hét</td>";
        $html.= "<td style='padding:2px;'>Rendelési óraszám</td>";
        $html.= "<td style='padding:2px;'>Paciensek száma</td>";
        $html.= "<td style='padding:2px;'>Ebből Törvényszékes paciens</td>";
        $html.= "<td style='padding:2px;'>Egyéb cég paciense</td>";
        $html.= "<td style='padding:2px;'>Paciens / óra</td>";
        $html.="</tr>";

        foreach ($weekStat as $week => $stat) {
            $html.="<tr>";

            $paciensPerHout = round($stat["paciensek"] / $stat["hours"], 1);

            $html.= "<td>".substr($week, 0, 4)." ".substr($week, 5).". hét&nbsp;</td>";
            $html.= "<td style='text-align: right;'>".round($stat["hours"])."</td>";
            $html.= "<td style='text-align: right;'>{$stat["paciensek"]}</td>";
            $html.= "<td style='text-align: right;'>{$stat["torvenyszekpaciensek"]}</td>";
            $html.= "<td style='text-align: right;'>".($stat["paciensek"] - $stat["torvenyszekpaciensek"])."</td>";
            $html.= "<td style='text-align: right;'>{$paciensPerHout}</td>";

            $html.="</tr>";
        }

        $html.= "</table>";

        return $html;
    }

}

