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

    }

    public function showPage()
    {
        if (!$this->adminUser->beallitasAccess()) {
            echo "nincs jogosultságod!";
            return;
        }

        echo "<div style=''><img id='loadingspinner' style='margin-left:5px;height:25px;display:none;' src='/images/loading.svg' /></div>";

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
                $availableData[$day] = $bookingService->getPackageAvailabilityForDay($day);
            }

            $html.= "<h2>{$pack["megnev"]}</h2>";
            //$html.= print_r($bookingService->packContentTypes, true);
            //$html.= "<pre>".print_r($availableData, true)."</pre>";

            $html.= "<div style='display: table;'>";
            $html.= "<div style='display: table-row;'>";
            $html.= "<div style='display:table-cell;'>&nbsp;</div>";
            foreach ($availableData as $day => $available) {
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



}

