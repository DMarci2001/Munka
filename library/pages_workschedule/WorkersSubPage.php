<?php

class WorkersSubPage extends AdminCorePage {

    private $service;

    public function __construct($service)
    {
        parent::__construct();

        $this->service = $service;

        if (isset($_POST["openworkerdetail"])) {
            $_SESSION["worderdetail"] = $_POST["id"];
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
            sql_query("update schedule_workers set nev=? where id=?", [$_POST["nev"], $_POST["id"]]);
            $result = ["list" => $this->workerList(), "detail" => $this->workerDetail($_POST["id"])];
            $this->utils->jsonOut($result);
        }
    }

    public function showPage() {
        $html = "";

        $html.= "<div id='workerlist' style='display:table-cell;vertical-align:top;padding-right:10px;border-right:1px solid #ccc;'>";
        $html.= $this->workerList();
        $html.= "</div>";

        $html.= "<div id='workerdetail' style='display:table-cell;vertical-align:top;padding-left:10px;'>";
        if (isset($_SESSION["worderdetail"])) {
            $html.= $this->workerDetail($_SESSION["worderdetail"]);
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
                $html.="<tr>";
                $html.="<td colspan='10' style='padding:4px 4px 4px 4px;font-weight:bold;background:#aaa;color:#fff;'>";
                $html.="<div style='display:table-cell;vertical-align: middle;padding-right:5px;'><a onclick='Schedule.AddNewWorker({$workerData["roleid"]});return false;' href=''><img height='16' src='/admin/images/add.png' title='hozzáadás'/></a></div>";
                $html.="<div style='display:table-cell;vertical-align: middle;'>{$workerData["rolenev"]}</div>";
                $html.="</td>";
                $html.="</tr>";
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


            $html.="<form id='workerform' method='post'><input type='hidden' name='id' value='{$data["id"]}' />";
            $html.="<div><input type='text' placeholder='Munkatárs neve' name='nev' value='{$data["nev"]}' style='' /></div>";
            $html.="<div style='display:table-cell;padding-top:5px;vertical-align: middle;'><a onclick='Schedule.SaveWorker();return false;' href='#' class='ujbutton'>Mentés</a></div>";
            $html.="<div style='display:table-cell;padding:5px 0px 0px 10px;vertical-align: middle;'><a onclick='Schedule.DeleteWorker();return false;' href='#'>Törlés</a></div>";
            $html.="</form>";

            $html.="<div style='margin-top:5px;'>";
            $stat = [];
            $res = sql_query("SELECT date(datumfrom) as datum, m.*, t.megnev as tipusnev 
                    FROM schedule_mapping m
                    LEFT JOIN schedule_tipusok t on t.id=m.tipusid
                    WHERE m.workerid=? AND m.`datumfrom`>DATE_SUB(NOW(), INTERVAL 40 DAY)", [$data["id"]]);
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
                $html.= "<div style='display:table-cell;'>".$this->adminUtils->magyarDatum($thisDay, false)."&nbsp;&nbsp;</div>";
                $html.= "<div style='display:table-cell;'>".$this->adminUtils->settings->hetnap[$weekDay]."&nbsp;&nbsp;</div>";
                $html.= "<div style='display:table-cell;'>";
                $display = [];
                if (isset($stat[$thisDay])) {
                    foreach ($stat[$thisDay] as $item) {
                        if ($item["napszak"] == 0) {
                            $display[] = "délelőtt - ".$item["tipusnev"];
                        } else {
                            $display[] = "délután - ".$item["tipusnev"];
                        }
                    }
                }
                $html.= implode(", ", $display);
                $html.= "</div>";

                $html.= "</div>";
            }
            $html.= "</div>";
        }
        return $html;
    }
}