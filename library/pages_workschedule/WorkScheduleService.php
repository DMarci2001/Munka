<?php

use PHPMailer\PHPMailer\PHPMailer;

class WorkScheduleService {
    public array $scheduleMapping = [];
    public array $collisionData = [];
    public array $collisionsByDate = [];

    public array $roles = [
        1 => "orvos",
        2 => "nővér",
        3 => "egyéb"
    ];

    function __construct()
    {
        $this->reloadScheduleMapping();
        $this->recalcAllCollisions();
    }

    public static function getDailySchedule($day):array {
        return sql_query("SELECT IF(TRIM(w.`teljesnev`) <> '', w.teljesnev, w.nev) AS workername, t.megnev AS tipusnev, r.megnev AS rolename, m.datumfrom, m.datumto, m.tipusid, m.roleid, m.workerid, m.megj FROM schedule_mapping m
            LEFT JOIN schedule_workers w ON w.id = m.workerid
            LEFT JOIN schedule_tipusok t ON t.id = m.tipusid
            LEFT JOIN schedule_roles r ON r.id = m.roleid
            WHERE m.datumfrom>=? AND m.datumfrom<=? and w.id is not null and t.id is not null order by m.datumfrom, m.roleid", ["{$day} 00:00:00", "{$day} 23:59:59"])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reloadScheduleMapping() {
        $this->scheduleMapping = [];
        $res = sql_query("SELECT m.*,w.nev AS workernev, n.nev AS novernev FROM schedule_mapping m
        LEFT JOIN schedule_workers w ON m.`workerid`=w.`id`
        LEFT JOIN schedule_workers n ON m.`noverid`=n.`id`
        where datumfrom > date_sub(now(), interval 100 day) order by m.datumfrom, w.nev");
        while ($row = sql_fetch_array($res)) {
            if ($row["napszak"] == 2) {
                $key = date("Y-m-d", strtotime($row["datumfrom"])) . "_2_{$row["tipusid"]}";
            } else {
                $key = date("Y-m-d", strtotime($row["datumfrom"])) . "_0_{$row["tipusid"]}";
            }
            $this->scheduleMapping[$key][] = $row;
        }
    }

    public function recalcAllCollisions() {
        $thisWeekMonday = date("Y-m-d 00:00:00", strtotime("this week monday"));
        $collisions = [];
        $suspects = sql_query("SELECT m.id, DATE(datumfrom) AS datum, workerid, napszak, COUNT(*) AS hany FROM schedule_mapping m WHERE datumfrom>? GROUP BY DATE(datumfrom), CONCAT(workerid) HAVING hany>1", [$thisWeekMonday])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($suspects as $suspect) {
            $beos = sql_query("select m.id, m.datumfrom, m.datumto from schedule_mapping m 
            left join schedule_workers sw on m.workerid=sw.id
            left join schedule_tipusok st on m.tipusid=st.id
            where m.workerid=? and date(datumfrom)=? and sw.id is not null and st.id is not null", [$suspect["workerid"], $suspect["datum"]])->fetchAll(PDO::FETCH_ASSOC);

            foreach ($beos as $beoLook) {
                foreach ($beos as $beo) {
                    if ($beo["id"] == $beoLook["id"]) {
                        continue;
                    }

                    if (($beo["datumfrom"] < $beoLook["datumto"]) && ($beo["datumto"] > $beoLook["datumfrom"])) {
                        $collisions[$suspect["id"]] = ["workerid" => $suspect["workerid"], "datum" => $suspect["datum"], "napszak" => $suspect["napszak"], "szoveg" => "", "datumfrom" => $beo["datumfrom"], "datumto" => $beo["datumto"], "datumfrom2" => $beoLook["datumfrom"], "datumto2" => $beoLook["datumto"]];
                        $this->collisionsByDate[$suspect["datum"]][$suspect["workerid"]][] = $beo["datumfrom"].$beo["datumto"];
                    }
                }
            }
        }

        $this->collisionData = $collisions;
        //print_r($collisions);
        //die;
    }

    public function notifyScheduleChange($workerId, $type = 'email') {
        $utils = new Utils();

        if ($workerData = sql_query("select * from schedule_workers w where w.id=?", [$workerId])->fetch()) {

            $token = $this->workerTokenGen($workerData);

            if ($type == "email") {
                $mail = NotificationService::getDefaultMailer();
                $mail->AddAddress($workerData["email"]);

                $mail->Subject = "[".Booking_Constants::COMPANY_NAME_SHORT."] beosztás változás";
                $mail->Body = "Kedves Munkatársunk!<br/>
                <br/>
                Értesítjük, hogy a beosztásában változás történt.<br/>
                Beosztásának megtekintéséhez kattintson az alábbi linkre.<br/>
                <br/>
                <a href='".Booking_Constants::MAIN_URL."/admin/index.php?scheduletoken={$token}'>Beosztás megtekintése</a><br/>
                <br/>
                Üdvözlettel:<br/>
                ".Booking_Constants::COMPANY_NAME."
                ";

                $mail->Send();
            }

            if ($type == "sms") {
                $utils->sendSMS($workerData["tel"], "Értesítjük, hogy beosztásában változás történt. kérjük ellenőrizze az emailben kiküldött linken. Üdv: ".Booking_Constants::COMPANY_NAME_SHORT);
            }

        }

    }

    public function workerTokenGen($workerData):string {
        return sha1($workerData["id"].$workerData["roleid"].$workerData["email"].$workerData["tel"]).md5($workerData["email"].$workerData["tel"]);
    }

    public function workerScheduleList($workerId):string {
        $adminUtils = new AdminUtils();
        $html = "";
        $stat = [];
        $szabadsagNapok = [];

        $workerData = sql_query("select nev, teljesnev from schedule_workers where id=?", [$workerId])->fetch(PDO::FETCH_ASSOC);
        $html.= "<div style='font-weight: bold;'>{$workerData["teljesnev"]} beosztása / szabadságai</div>";

        $szabiData = sql_query("select datumtol from schedule_szabadsag sz where sz.datumtol>date_sub(now(), interval 1 month) and oid=?", [$workerId])->fetchAll();
        foreach ($szabiData as $data) {
            $szabadsagNapok[] = $data["datumtol"];
        }

        $res = sql_query("SELECT date(datumfrom) as datum, m.*, t.megnev as tipusnev, t.kulso, t.cim
                    FROM schedule_mapping m
                    LEFT JOIN schedule_tipusok t on t.id=m.tipusid
                    WHERE m.workerid=? AND m.`datumfrom`>DATE_SUB(NOW(), INTERVAL 40 DAY)", [$workerId]);

        while ($row = sql_fetch_array($res)) {
            $stat[$row["datum"]][] = $row;
        }

        for ($i = 0; $i < 7 * 5; $i++) {
            $thisDay = date("Y-m-d", strtotime("last week monday + {$i} day"));
            $weekDay = date("N", strtotime($thisDay));
            $weekNum = date("W", strtotime($thisDay));

            if ($weekDay == 1) {
                $html.= "<div style='display:table-row;'>";
                $html.= "<div style='display:table-cell;font-weight: bold;padding:4px 0px;'>{$weekNum}. hét</div>";
                $html.= "</div>";
            }
            $html.= "<div style='display:table-row;'>";
            $html.= "<div style='display:table-cell;border-top:1px solid #ccc;padding:2px 0px;'>".$adminUtils->magyarDatum($thisDay, false)."&nbsp;&nbsp;</div>";
            $html.= "<div style='display:table-cell;border-top:1px solid #ccc;'>".$adminUtils->settings->hetnap[$weekDay]."&nbsp;&nbsp;</div>";
            $html.= "<div style='display:table-cell;border-top:1px solid #ccc;'>";
            $display = [];

            if (in_array($thisDay, $szabadsagNapok)) {
                $display[] = "<span onclick='toggleWorkerFreeDay(\"{$thisDay}\", {$workerId});' style='cursor:pointer;padding:2px 5px;background:#56af56;color:#fff;border-radius: 2px;'><i class='fa-regular fa-square-check'></i> szabi</span>";
            } else {
                $display[] = "<span onclick='toggleWorkerFreeDay(\"{$thisDay}\", {$workerId});' style='cursor:pointer;padding:2px 5px;background:lightgray;color:#fff;border-radius: 2px;'><i class='fa-regular fa-square'></i> szabi</span>";
            }

            if (isset($stat[$thisDay])) {
                foreach ($stat[$thisDay] as $item) {
                    $text = $item["tipusnev"]." ".$this->workInterval($item);

                    if ($item["cim"] != "") {
                        $text.= "&nbsp;&nbsp;<a title='Google Maps' href='https://www.google.com/maps/place/".urlencode($item["cim"])."' target='_blank'><i class='fas fa-map' style='font-size:16px;'></i></a>";
                    }
                    $display[] = $text;
                }
            }
            $html.= implode("<br/>", $display);
            $html.= "</div>";

            $html.= "</div>";
        }

        return $html;
    }

    public function workInterval($mapping) {
        $html="";

        $from = date("H:i", strtotime($mapping["datumfrom"]));
        $to   = date("H:i", strtotime($mapping["datumto"]));

        if ($from != "00:00" || $to != "00:00") {
            if ($from != "00:00" && $to == "00:00") {
                $html.="{$from} -";
            } else {
                if ($from == "00:00" && $to != "00:00") {
                    $html .= "- {$to}";
                } else {
                    $html .= "{$from} - {$to}";
                }
            }
        }
        return $html;
    }

    public function dateOddOrEvenText($date):string {
        if (date('W', strtotime($date))%2==0) {
            return "páros";
        } else {
            return "páratlan";
        }
    }
}