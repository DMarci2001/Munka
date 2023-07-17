<?php

class AdminLabRequestsPage extends AdminCorePage {

    public function __construct()
    {
        parent::__construct();

        if (isset($_REQUEST["generalsearch"])) {
            echo $this->listLabRequests();
            die;
        }

        if (isset($_POST["showrequestdetails"])) {
            $id = intval($_POST["showrequestdetails"]);

            $items = sql_query("SELECT i.itemid, t.* FROM labrequestitems i LEFT JOIN synlab_labor_tetelek t ON t.id=i.itemid WHERE i.requestid=?", [$id])->fetchAll(PDO::FETCH_ASSOC);
            echo "<div style='margin-bottom: 5px;'>Kért vizsgálatok:</div>";
            $sor = 1;
            foreach ($items as $item) {
                echo "<div style='display:table-row;'>";
                echo "<div style='display:table-cell;'>{$sor}.&nbsp;&nbsp;</div>";
                echo "<div style='display:table-cell;'>{$item["name"]}</div>";
                echo "</div>";
                $sor++;
            }
            die;
        }

    }

    public function showPage() {
        if (!$this->adminUser->labortetelAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        $GLOBALS["subtitle"] = "Labor kérések";

        echo "<div style='margin-bottom:20px;'>";
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        //echo $this->cegFilter()."&nbsp;&nbsp;";
        //echo $this->eszkozFilter();
        echo "<input data-page='labrequests' data-resultdiv='labrequestlist' type='text' id='generalsearch' value='' placeholder='Keresés...'/>&nbsp;&nbsp;&nbsp;&nbsp;";
        echo "</div>";
        echo "</div>";

        echo "<div id='labrequestlist'>";
        echo $this->listLabRequests();
        echo "</div>";
    }

    private function listLabRequests():string {
        $requests = [];
        if (isset($_REQUEST["generalsearch"]) && isset($_REQUEST["term"])) {
            $requests = $this->getLabRequests(["search" => $_REQUEST["term"]]);
        }

        if (!isset($images)) {
            $requests = $this->getLabRequests();
        }

        $html = "";

        $html.= "<table cellpadding='0' cellspacing='0' border='0' width='100%;'>";
        $html.= "<tr style='background:#eee;'>";
        //$html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:100px;'>Kérés időpontja</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:90px;'>Provider</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:200px;'>Paciens</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:100px;'>Szül. idő</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:200px;'>Cég</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Eredmény</div></td>";
        $html.= "</tr>";

        foreach ($requests as $request) {
            $tc = "tcella";
            if (!isset($first)) {
                $html.= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            $html.= "<tr>";

            //$html.= "<td nowrap><div class='{$tc}'></div></td>";
            $html.= "<td nowrap><div class='{$tc}'>".date("Y-m-d H:i", strtotime($request["created"]))."</div></td>";
            $html.= "<td nowrap><div class='{$tc}'><div style=''><a class='printbutton' title='kérés megtekintése' target='_blank' onclick='toggleRequestDetailRow(\"{$request["id"]}\");return false;' href='#' style='padding:1px 5px;'>{$request["provider"]}</a></div></td>";
            $html.= "<td nowrap><div class='{$tc}'>{$request["nev"]}</div></td>";
            $html.= "<td nowrap><div class='{$tc}'>{$request["szuldatum"]}</div></td>";
            $html.= "<td nowrap><div class='{$tc}'>{$request["cegnev"]}</div></td>";
            $html.= "<td nowrap><div class='{$tc}'>";
            if ($request["result"] == 1) {
                $html.= "<div style=''><a class='printbutton' target='_blank' href='https://bejelentkezes.hungariamed.hu/admin/index.php?print&template=laborlelet1&rid={$request["id"]}&p={$request["pass"]}' style='background: #00aa00;padding:1px 5px;'>Lelet megtekintése</a></div>";
            }
            $html.= "</div></td>";
            $html.= "</tr>";
            $html.= "<tr><td colspan='10' ><div id='requestrow{$request["id"]}' style='padding:10px 0px 10px 0px;display:none;'></div></td></tr>";
            $html.= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        $html.= "</table>";

        return $html;
    }


    private function getLabRequests($params = []) {
        $queryParams = [];
        $w = "";

         if (!empty($params["search"])) {
            //$w .= " and instr(concat(patientName,patientBirthDate,patientOtherIDs), ?)";
            //$queryParams[] = $params["search"];
        }

        return sql_query_common("SELECT f.nev, f.szuldatum, f.taj, f.cegid, f.telefon, c.megnev AS cegnev, r.id, r.pass, r.created, r.provider, r.foglalasid, r.laborpacks, IF(r.resultpdf='', 0, 1) AS result FROM labrequests r 
            LEFT JOIN foglalasok f ON f.id=r.foglalasid
            LEFT JOIN cegek c ON c.id=f.cegid
            WHERE r.status<>'temp' {$w} ORDER BY r.created DESC LIMIT 1000", $queryParams)->fetchAll(PDO::FETCH_ASSOC);
    }


}