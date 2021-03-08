<?php

class WorkplacesSubPage extends AdminCorePage {

    private $service;

    public function __construct($service)
    {
        parent::__construct();

        $this->service = $service;

        if (isset($_POST["openworkplacedetail"])) {
            $_SESSION["workplacedetail"] = $_POST["id"];
            echo $this->workplaceDetail($_POST["id"]);
            die;
        }
        if (isset($_POST["addnewworkplace"])) {
            sql_query("insert into schedule_tipusok set megnev='_Új munkahely', roleid=?, kulso=?", [$_POST["roleid"], $_POST["kulso"]]);
            echo $this->workplaceList();
            die;
        }
        if (isset($_POST["deleteworkplace"])) {
            sql_query("delete from schedule_tipusok where id=?", [$_POST["id"]]);
            echo $this->workplaceList();
            die;
        }
        if (isset($_POST["saveworkplace"])) {
            sql_query("update schedule_tipusok set megnev=?, sorrend=?, cim=? where id=?", [$_POST["megnev"], $_POST["sorrend"], $_POST["cim"], $_POST["id"]]);
            $result = ["list" => $this->workplaceList(), "detail" => $this->workplaceDetail($_POST["id"])];
            $this->utils->jsonOut($result);
        }
        if (isset($_POST["orderworkplace"])) {
            $id = intval($_POST["id"]);
            $workPlaceData = sql_query("select * from schedule_tipusok where id=?", [$id])->fetch(PDO::FETCH_ASSOC);

            if ($_POST["direction"] == "up") {
                if ($row2 = sql_fetch_array(sql_query("select id, sorrend from schedule_tipusok where roleid=? and kulso=? and sorrend<? order by sorrend desc limit 1", [$workPlaceData["roleid"], $workPlaceData["kulso"], $workPlaceData["sorrend"]]))) {
                    sql_query("update schedule_tipusok set sorrend=? where id=?", [$row2["sorrend"], $id]);
                    sql_query("update schedule_tipusok set sorrend=? where id=?", [$workPlaceData["sorrend"], $row2["id"]]);
                }
            }
            if ($_POST["direction"] == "down") {
                if ($row2 = sql_fetch_array(sql_query("select id, sorrend from schedule_tipusok where roleid=? and kulso=? and sorrend>? order by sorrend limit 1", [$workPlaceData["roleid"], $workPlaceData["kulso"], $workPlaceData["sorrend"]]))) {
                    sql_query("update schedule_tipusok set sorrend=? where id=?", [$row2["sorrend"], $id]);
                    sql_query("update schedule_tipusok set sorrend=? where id=?", [$workPlaceData["sorrend"], $row2["id"]]);
                }
            }

            echo $this->workplaceList();
            die;
        }
    }

    public function showPage() {
        $html = "";

        $html.= "<div id='workplacelist' style='display:table-cell;vertical-align:top;padding-right:20px;border-right:1px solid #ccc;'>";
        $html.= $this->workplaceList();
        $html.= "</div>";

        $html.= "<div id='workplacedetail' style='display:table-cell;vertical-align:top;padding-left:20px;'>";
        if (isset($_SESSION["workplacedetail"])) {
            $html.= $this->workplaceDetail($_SESSION["workplacedetail"]);
        }
        $html.= "</div>";

        return $html;
    }

    public function workplaceList() {
        $html = "";

        $res = sql_query("select * from schedule_tipusok where forday='0000-00-00' order by kulso,forday,roleid,sorrend");
        $lastRole = 0;
        $html.= "<table cellpadding='0' cellspacing='0'>";
        while ($data = sql_fetch_array($res)) {
            if ($lastRole != $data["roleid"]) {
                $name = ($data["kulso"]==0?"Belső":"Külső");
                if ($data["kulso"] == 0) {
                    if ($data["roleid"] == 1) {
                        $name.=" - Orvos";
                    }
                    if ($data["roleid"] == 3) {
                        $name.=" - Egyéb";
                    }
                }
                $html.="<tr><td style='height: 10px;'></td></tr>";
                $html.="<tr>";
                $html.="<td colspan='10' style='padding:4px 4px 4px 4px;font-weight:bold;background:#aaa;color:#fff;'>";
                $html.="<div style='display:table-cell;vertical-align: middle;padding-right:5px;'><a onclick='Schedule.AddNewWorkplace(\"{$data["roleid"]}\", \"{$data["kulso"]}\");return false;' href=''><img height='16' src='/admin/images/add.png' title='hozzáadás'/></a></div>";
                $html.="<div style='display:table-cell;vertical-align: middle;'>{$name}";
                $html.="</div>";
                $html.="</td>";
                $html.="</tr>";
                $html.="<tr><td style='height: 5px;'></td></tr>";
                $lastRole = $data["roleid"];
            }
            $html.= "<tr>";
            $html.= "<td valign='middle' style='padding:2px 10px 2px 0px;'><a onclick='Schedule.OpenWorkplaceDetail({$data["id"]});return false;' href='#'>{$data["megnev"]}</a>";
            if (!empty($data["cim"])) {
                $html .= "&nbsp;&nbsp;<a title='Google Maps' href='https://www.google.com/maps/place/" . urlencode($data["cim"]) . "' target='_blank'><i class='fas fa-map' style='font-size:14px;'></i></a>";
            }
            $html.= "</td>";
            $html.= "<td valign='middle' style='padding:2px 0px 2px 0px;'>";
            $html.= "<a title='mozgatás fel' onclick='Schedule.OrderWorkplace(\"up\", {$data["id"]});return false;' href='#'><i class='fas fa-arrow-up'></i></a> ";
            $html.= "<a title='mozgatás le' onclick='Schedule.OrderWorkplace(\"down\", {$data["id"]});return false;' href='#'><i class='fas fa-arrow-down'></i></a>";
            $html.= "</td>";
            $html.= "</tr>";
        }
        $html.= "</table>";

        return $html;
    }

    public function workplaceDetail($id) {
        $html = "";
        if ($data = sql_fetch_array(sql_query("select * from schedule_tipusok where id=?", [$id]))) {
            $html.= "<h2>{$data["megnev"]}</h2>";

            $html.="<form id='workplaceform' method='post'><input type='hidden' name='id' value='{$data["id"]}' />";
            $html.="<div>Cég megnevezése:<br/><input type='text' placeholder='Megnevezés' name='megnev' value='{$data["megnev"]}' style='' /> <input type='text' placeholder='Sorrend' name='sorrend' value='{$data["sorrend"]}' style='width:30px;' title='Sorrend' /></div>";
            if ($data["kulso"] == 1) {
                $html .= "<div style='margin-top:5px;'>Cím:<br/><input style='width:300px;' type='text' placeholder='Cím' name='cim' value='{$data["cim"]}' style='' />&nbsp;&nbsp;<a title='Google Maps link (mentsd el a címet, mielőtt kattintasz)' href='https://www.google.com/maps/place/".urlencode($data["cim"])."' target='_blank'><i class='fas fa-map' style='font-size:18px;'></i></a></div>";
            }
            $html.="<div style='display: table-row;'>";
            $html.="<div style='display:table-cell;padding-top:5px;vertical-align: middle;'><a onclick='Schedule.SaveWorkplace();return false;' href='#' class='ujbutton'>Mentés</a></div>";
            $html.="<div style='display:table-cell;padding:5px 0px 0px 10px;vertical-align: middle;'><a onclick='Schedule.DeleteWorkplace();return false;' href='#'>Törlés</a></div>";
            $html.= "</div>";

            $html.="</form>";

            $html.="<div style='margin-top:5px;'>";
            $stat = [];
            $res = sql_query("SELECT date(datumfrom) as datum, m.*, t.megnev as tipusnev,w.nev as workernev 
                    FROM schedule_mapping m
                    LEFT JOIN schedule_tipusok t on t.id=m.tipusid
                    LEFT JOIN schedule_workers w on w.id=m.workerid
                    WHERE m.tipusid=? AND m.`datumfrom`>DATE_SUB(NOW(), INTERVAL 40 DAY)
                    order by m.datumfrom, m.napszak
                    ", [$data["id"]]);
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
                            $display[] = "délelőtt - ".$item["workernev"];
                        } else {
                            $display[] = "délután - ".$item["workernev"];
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