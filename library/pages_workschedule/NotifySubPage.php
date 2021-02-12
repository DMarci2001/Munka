<?php


class NotifySubPage extends AdminCorePage
{
    private $service;

    public function __construct(WorkScheduleService $service)
    {
        parent::__construct();

        $this->service = $service;

        if (isset($_POST["notifybyworkerid"])) {
            $result = "";
            $workerId = $_POST["workerid"];

            if ($_POST["smsnotif"] == 1) {
                $this->service->notifyScheduleChange($workerId, "sms");

                $result .= "<span style='color:#0a0;'>sms kiküldve</span>";
                //sql_query("update schedule_mapping m set notifyhash=md5(concat(m.datumfrom, m.datumto)) where m.datumfrom>now() and workerid=?", [$workerId]);
            }

            if ($_POST["emailnotif"] == 1) {
                $this->service->notifyScheduleChange($workerId, "email");

                if ($result != "") {
                    $result .= ", ";
                }
                $result .= "<span style='color:#0a0;'>email kiküldve</span>";
            }

            if ($result == "") {
                $result = "nem történt küldés";
            }

            echo $result;
            die;
        }

    }

    public function showPage():string
    {
        $html = "";

        $html .= "<div id='noifylist' style=''>";
        $html .= "<h2>Munkatársak értesítése</h2>";
        $html .= $this->notificationList();
        $html .= "</div>";

        return $html;
    }

    public function notificationList():string
    {
        $html = "";
        $workers = sql_query("select w.*, r.megnev as rolenev from schedule_workers w left join schedule_roles r on r.id=w.roleid order by roleid, nev")->fetchAll();
        $html .= "<table cellpadding='0' cellspacing='0'>";
        $changedNum = 0;
        foreach ($workers as $workerData) {

            if ($changed = sql_query("SELECT * FROM schedule_mapping m WHERE m.datumfrom>=DATE(DATE_ADD(NOW(), INTERVAL 1 DAY)) and notifyhash<>md5(concat(m.datumfrom, m.datumto)) and m.workerid=:uid", ["uid" => $workerData["id"]])->fetch()) {
                $phoneText = "<span style='color:#f00;'>nincs megadva telefonszám</span>";
                $emailText = "<span style='color:#f00;'>nincs megadva email cím</span>";
                $checkSmsDefault = $checkEmailDefault = false;

                if ($workerData["tel"] != "") {
                    $phoneText = $workerData["tel"];
                    $checkSmsDefault = true;
                }
                if ($workerData["email"] != "") {
                    $emailText = $workerData["email"];
                    $checkEmailDefault = true;
                }

                if ($workerData["smsert"] == 0) {
                    $checkSmsDefault = false;
                }
                if ($workerData["emailert"] == 0) {
                    $checkEmailDefault = false;
                }

                $html .= "<tr id='notifrow{$changedNum}' data-workerid='{$workerData["id"]}'>";
                $html .= "<td valign='middle' style='padding:2px 10px 2px 0px;'>{$workerData["nev"]}&nbsp;&nbsp;</td>";
                $html .= "<td valign='middle' style='padding:2px 10px 2px 0px;'><input type='checkbox' id='smscheck{$changedNum}' name='smscheck{$changedNum}' ".($checkSmsDefault?"checked":"")." /> sms ({$phoneText})&nbsp;&nbsp;</td>";
                $html .= "<td valign='middle' style='padding:2px 10px 2px 0px;'><input type='checkbox' id='emailcheck{$changedNum}' name='emailcheck{$changedNum}' ".($checkEmailDefault?"checked":"")." /> email ({$emailText})</td>";
                $html .= "<td valign='middle' style='padding:2px 10px 2px 0px;'><div id='notifresult{$changedNum}'></div></td>";
                $html .= "</tr>";

                $changedNum++;
            }

        }
        $html .= "</table>";

        if ($changedNum > 0) {
            $html .= "<div style='padding-top:10px;' id='sendstartbutton'><a onclick='ScheduleNotification.Start();return false;' href='#' class='ujbutton'>Kijelölt értesítések kiküldése</a></div>";
        } else {
            $html .= "<div style='padding-top:0px;'>Nem történt változás a beosztásban.</div>";
        }


        return $html;
    }
}