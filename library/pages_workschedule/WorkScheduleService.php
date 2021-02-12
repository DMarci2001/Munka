<?php

use PHPMailer\PHPMailer\PHPMailer;

class WorkScheduleService {
    public $weekStart;
    public $scheduleMapping = [];

    function __construct()
    {
        $this->reloadScheduleMapping();
    }

    public function reloadScheduleMapping() {
        $this->scheduleMapping = [];
        $res = sql_query("SELECT m.*,w.nev AS workernev, n.nev AS novernev FROM schedule_mapping m
        LEFT JOIN schedule_workers w ON m.`workerid`=w.`id`
        LEFT JOIN schedule_workers n ON m.`noverid`=n.`id`
        where datumfrom > date_sub(now(), interval 7 day)");
        while ($row = sql_fetch_array($res)) {
            $key = date("Y-m-d", strtotime($row["datumfrom"]))."_{$row["napszak"]}_{$row["tipusid"]}";
            $this->scheduleMapping[$key][] = $row;
        }
    }


    public function notifyScheduleChange($workerId, $type = 'email') {
        $utils = new Utils();

        if ($workerData = sql_query("select * from schedule_workers w where w.id=?", [$workerId])->fetch()) {

            $token = $this->workerTokenGen($workerData);

            if ($type == "email") {
                $mail = new PHPMailer();
                $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
                $mail->FromName = Booking_Constants::COMPANY_NAME;
                $mail->AddAddress($workerData["email"]);
                $mail->CharSet = "UTF-8";
                $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
                $mail->IsHTML(true);

                $mail->Subject = "[Hungariamed] beosztás változás";
                $mail->Body = "Kedves Munkatársunk!<br/>
                <br/>
                Értesítjük, hogy a beosztásában változás történt.<br/>
                Beosztásának megtekintéséhez kattintson az alábbi linkre.<br/>
                <br/>
                <a href='https://bejelentkezes.hungariamed.hu/admin/index.php?scheduletoken={$token}'>Beosztás megtekintése</a><br/>
                <br/>
                Üdvözlettel:<br/>
                Hungáriamed
                ";

                $mail->Send();
            }

            if ($type == "sms") {
                $utils->sendSMS($workerData["tel"], "Értesítjük, hogy beosztásában változás történt. kérjük ellenőrizze az emailben kiküldött linken. Üdv: Hungariamed");
            }

        }

    }

    public function workerTokenGen($workerData):string {
        return sha1($workerData["id"].$workerData["roleid"].$workerData["email"].$workerData["tel"]).md5($workerData["email"].$workerData["tel"]);
    }

    public function workerScheduleList($workerData):string {
        $adminUtils = new AdminUtils();
        $html = "";
        $stat = [];

        $res = sql_query("SELECT date(datumfrom) as datum, m.*, t.megnev as tipusnev, t.kulso
                    FROM schedule_mapping m
                    LEFT JOIN schedule_tipusok t on t.id=m.tipusid
                    WHERE m.workerid=? AND m.`datumfrom`>DATE_SUB(NOW(), INTERVAL 40 DAY)", [$workerData["id"]]);
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
            if (isset($stat[$thisDay])) {
                foreach ($stat[$thisDay] as $item) {
                    if ($item["napszak"] == 0) {
                        $text = "délelőtt - ".$item["tipusnev"]." ";
                        $text.= $this->workInterval($item);
                    } else {
                        $text = ($item["kulso"]==1?"":"délután - ").$item["tipusnev"]." ";
                        $text.= $this->workInterval($item);
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
}