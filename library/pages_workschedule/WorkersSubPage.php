<?php

class WorkersSubPage extends AdminCorePage {

    private $service;

    public function __construct(WorkScheduleService $service)
    {
        parent::__construct();

        $this->service = $service;

        if (isset($_POST["openworkerdetail"])) {
            $_SESSION["workerdetail"] = $_POST["id"];
            echo $this->workerDetail($_POST["id"]);
            die;
        }
        if (isset($_POST["addnewworker"])) {
            sql_query("insert into schedule_workers set nev='_Új munkatárs', roleid=?", [$_POST["roleid"]]);
            echo $this->workerList();
            die;
        }
        if (isset($_POST["deleteworker"])) {
            sql_query("delete from schedule_workers where id=?", [$_POST["id"]]);
            echo $this->workerList();
            die;
        }
        if (isset($_POST["saveworker"])) {
            sql_query("update schedule_workers set nev=?, teljesnev=?, email=?, tel=?, smsert=?, emailert=? where id=?", [$_POST["nev"], $_POST["teljesnev"], $_POST["email"], $_POST["tel"], isset($_POST["smsert"])?1:0, isset($_POST["emailert"])?1:0, $_POST["id"]]);
            $result = ["list" => $this->workerList(), "detail" => $this->workerDetail($_POST["id"])];
            $this->utils->jsonOut($result);
        }

        if (isset($_POST["addszabadsag"])) {
            $result =   ["status" => "ok", "message" => ""];
            $workerId   = intval($_POST["workerid"]);
            $tol       = $_POST["tol"];
            $ig        = $_POST["ig"];
            $startDate = $tol;
            $groupId   = 0;

            if ($tol == '' || $ig == '') {
                $result["status"] = "Add meg a szabadság kezdő és vég napját!";
            }

            if (strtotime($tol) > strtotime($ig)) {
                $result["status"] = "A szabadság kezdő dátumának kisebbnek kell lennie mint a vég dátum!";
            }

            if (strtotime($ig) - strtotime($tol) > 86400*31) {
                $result["status"] = "A szabadság nem lehet hozsszabb mint 1 hónap!";
            }

            if ($result["status"] == "ok") {
                while (strtotime($startDate) <= strtotime($ig)) {
                    sql_query("insert into schedule_szabadsag set datumtol=?, datumig=?, oid=?", [$startDate, $startDate, $workerId]);
                    $newId = sql_insert_id();
                    if ($groupId == 0) {
                        $groupId = $newId;
                    }
                    sql_query("update schedule_szabadsag set groupid=? where id=?", [$groupId, $newId]);

                    $startDate = date("Y-m-d", strtotime("{$startDate} +1 day"));
                }
                $result["message"] = $this->workerDetail($workerId, true);
            }

            $this->utils->jsonOut($result);
        }

        if (isset($_POST["deleteszabadsag"])) {
            $result =   ["status" => "ok", "message" => ""];
            $workerId   = intval($_POST["workerid"]);
            $groupId    = intval($_POST["groupid"]);;

            sql_query("delete from schedule_szabadsag where groupid=?", [$groupId]);

            $result["message"] = $this->workerDetail($workerId, true);
            $this->utils->jsonOut($result);
        }

    }

    public function showPage() {
        $html = "";

        $html.= "<div id='workerlist' style='display:table-cell;vertical-align:top;padding-right:20px;border-right:1px solid #ccc;'>";
        $html.= $this->workerList();
        $html.= "</div>";

        $html.= "<div id='workerdetail' style='display:table-cell;vertical-align:top;padding-left:20px;'>";
        if (isset($_SESSION["workerdetail"])) {
            $html.= $this->workerDetail($_SESSION["workerdetail"]);
        }
        $html.= "</div>";

        return $html;
    }

    public function workerList() {
        $html = "";
        $res = sql_query("select w.*, r.megnev as rolenev from schedule_workers w left join schedule_roles r on r.id=w.roleid order by roleid, nev");
        $lastRole = 0;
        $html.= "<table cellpadding='0' cellspacing='0'>";
        while ($workerData = sql_fetch_array($res)) {
            if ($lastRole != $workerData["roleid"]) {
                $html.="<tr><td style='height: 10px;'></td></tr>";
                $html.="<tr>";
                $html.="<td colspan='10' style='padding:4px 4px 4px 4px;font-weight:bold;background:#aaa;color:#fff;'>";
                $html.="<div style='display:table-cell;vertical-align: middle;padding-right:5px;'><a onclick='Schedule.AddNewWorker({$workerData["roleid"]});return false;' href=''><img height='16' src='/admin/images/add.png' title='hozzáadás'/></a></div>";
                $html.="<div style='display:table-cell;vertical-align: middle;'>{$workerData["rolenev"]}</div>";
                $html.="</td>";
                $html.="</tr>";
                $html.="<tr><td style='height: 5px;'></td></tr>";
                $lastRole = $workerData["roleid"];
            }
            $html.= "<tr>";
            $html.= "<td valign='middle' style='padding:2px 10px 2px 0px;'><a onclick='Schedule.OpenWorkerDetail({$workerData["id"]});return false;' href='#'>".(!empty($workerData["teljesnev"])?$workerData["teljesnev"]:$workerData["nev"])."</a></td>";
            $html.= "</tr>";
        }
        $html.= "</table>";
        return $html;
    }

    public function workerDetail($id, $szabadsagOpen = false) {
        $html = "";
        if ($data = sql_fetch_array(sql_query("select * from schedule_workers where id=?", [$id]))) {
            $html.= "<h2>".(!empty($data["teljesnev"])?$data["teljesnev"]:$data["nev"])."</h2>";

            $url = "https://bejelentkezes.hungariamed.hu/admin/index.php?scheduletoken=".$this->service->workerTokenGen($data);

            $html.="<form id='workerform' method='post'><input type='hidden' name='id' value='{$data["id"]}' />";
            $html.="<div style='display: table-row;'>";
            $html.="<div style='display: table-cell;'>Munkatárs rövid neve:<br/><input type='text' placeholder='Munkatárs neve' name='nev' value='{$data["nev"]}' style='' /></div>";
            $html.="<div style='display: table-cell;padding-left:10px;'>Munkatárs teljes neve:<br/><input style='width:200px;' type='text' placeholder='Munkatárs teljes neve' name='teljesnev' value='{$data["teljesnev"]}' style='' /></div>";
            $html.= "</div>";
            $html.="<div style='display: table-row;'>";
            $html.="<div style='display: table-cell;padding-top:5px;'>Telefon:<br/><input type='text' placeholder='Telefonszám' name='tel' value='{$data["tel"]}' style='' /></div>";
            $html.="<div style='display: table-cell;padding-top:5px;padding-left:10px;'>Email:<br/><input style='width:200px;' type='text' placeholder='Email cím' name='email' value='{$data["email"]}' style='' /></div>";
            $html.= "</div>";
            //$html.="<div style='display:table-cell;'>Bejelentkező kapcsolódás:<br/><input type='text' placeholder='Munkatárs neve' name='nev' value='{$data["nev"]}' style='' /></div>";
            $html.="<div style='margin-top:5px;'><input type='checkbox' name='smsert' value='1' ".($data["smsert"]==1?"checked":"")." /> sms értesítés</div>";
            $html.="<div><input type='checkbox' name='emailert' value='1' ".($data["emailert"]==1?"checked":"")." /> e-mail értesítés</div>";
            $html.="<div style='display:table-cell;padding-top:5px;vertical-align: middle;'><a onclick='Schedule.SaveWorker();return false;' href='#' class='ujbutton'>Mentés</a></div>";
            $html.="<div style='display:table-cell;padding:5px 0px 0px 10px;vertical-align: middle;'><a onclick='Schedule.DeleteWorker();return false;' href='#'>Törlés</a></div>";
            $html.="</form>";

            $html.="<div style='margin-top:5px;'>";
            $html.="Saját beosztás megtekintő URL: (kimegy az értesítő levélben is)<br/>";

            $html.="{$url} ";

            $html.="<a id='copylink' data-url='{$url}' title='vágólapra másolás' onclick='Schedule.CopyURL();return false;' href='#'><i class='far fa-copy'></i></a>";
            $html.= "</div>";


            $szabiData = sql_query("select count(*) as hanynap from schedule_szabadsag sz where oid=?", [$id])->fetch();

            $html.= "<div style='padding-top:10px;'><strong>Szabadságok</strong> [<a href='#' onclick='$(\"#szabadsageditor\").slideToggle();return false;'>szerkesztés</a>] (".($szabiData["hanynap"])." nap a jövőben)</div>";

            $html.= "<div id='szabadsageditor' style='padding-top:10px;".($szabadsagOpen?"":"display:none;")."'>";

            $ressz = sql_query("select min(sz.datumtol) as datumtol, max(datumig) as datumig, groupid from schedule_szabadsag sz where oid=? group by sz.groupid order by datumtol", [$id]);
            while ($rowsz = sql_fetch_array($ressz)) {
                $html.= "<div style='display:table-row;'>";
                $html.= "<div style='display:table-cell;vertical-align:middle;'>{$rowsz["datumtol"]} - {$rowsz["datumig"]}</div>";
                $html.= "<div style='display:table-cell;vertical-align:middle;padding-left:5px;'><a href='#' onclick='Schedule.DeleteSzabadsag({$rowsz["groupid"]});return false;'><img src='images/trash.png' title='Sor törlése'/></a></div>";
                $html.= "</div>";
            }
            $html.= "<form name='szabiform' id='szabifrom' method='post'><input type='hidden' name='workerid' id='workerid' value='{$id}' />";
            $html.= "<div><input class='inputbox' style='width:100px;' type='text' name='szabadsagtol' id='szabadsagtol' value='' placeholder='kezdő dátum'> - <input class='inputbox' style='width:100px;' type='text' name='szabadsagig' id='szabadsagig' value='' placeholder='vége dátum'> <input type='button' id='addszabadsagbutton' name='addszabadsag' value='+ szabadság hozzáadása'></div>";
            $html.= "</form>";
            $html.= "</div>";

            $html.="<div style='margin-top:5px;'>";
            $html.=$this->service->workerScheduleList($data);
            $html.= "</div>";
        }
        return $html;
    }
}