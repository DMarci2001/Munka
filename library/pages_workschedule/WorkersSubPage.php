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
            sql_query("update schedule_workers set nev=?, email=?, tel=?, smsert=?, emailert=? where id=?", [$_POST["nev"], $_POST["email"], $_POST["tel"], isset($_POST["smsert"])?1:0, isset($_POST["emailert"])?1:0, $_POST["id"]]);
            $result = ["list" => $this->workerList(), "detail" => $this->workerDetail($_POST["id"])];
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
            $html.= "<td valign='middle' style='padding:2px 10px 2px 0px;'><a onclick='Schedule.OpenWorkerDetail({$workerData["id"]});return false;' href='#'>{$workerData["nev"]}</a></td>";
            $html.= "</tr>";
        }
        $html.= "</table>";
        return $html;
    }

    public function workerDetail($id) {
        $html = "";
        if ($data = sql_fetch_array(sql_query("select * from schedule_workers where id=?", [$id]))) {
            $html.= "<h2>{$data["nev"]}</h2>";

            $url = "https://bejelentkezes.hungariamed.hu/admin/index.php?scheduletoken=".$this->service->workerTokenGen($data);

            $html.="<form id='workerform' method='post'><input type='hidden' name='id' value='{$data["id"]}' />";
            $html.="<div style=''>Munkatárs rövid neve:<br/><input type='text' placeholder='Munkatárs neve' name='nev' value='{$data["nev"]}' style='' /></div>";
            $html.="<div style='margin-top:5px;'>Telefon:<br/><input type='text' placeholder='Telefonszám' name='tel' value='{$data["tel"]}' style='' /></div>";
            $html.="<div style='margin-top:5px;'>Email:<br/><input type='text' placeholder='Email cím' name='email' value='{$data["email"]}' style='' /></div>";
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

            $html.="<div style='margin-top:5px;'>";
            $html.=$this->service->workerScheduleList($data);
            $html.= "</div>";
        }
        return $html;
    }
}