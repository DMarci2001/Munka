<?php

class WorkplacesSubPage {

    private $service;

    public function __construct($service)
    {
        $this->service = $service;
    }

    public function showPage() {
        $html = "";

        $html.= "<div>";

        $res = sql_query("select * from schedule_tipusok order by kulso,roleid,sorrend");
        $lastRole = 0;
        $html.= "<table cellpadding='0' cellspacing='0'>";
        while ($data = sql_fetch_array($res)) {
            if ($lastRole != $data["roleid"]) {
                $html.= "<tr>";
                $html.= "<td colspan='10' style='padding:4px 10px 4px 10px;font-weight: bold;background:#888;color:#fff;'>";
                $html.= ($data["kulso"]==0?"Belső":"Külső");
                if ($data["kulso"] == 0) {
                    if ($data["roleid"] == 1) {
                        $html.=" - Orvos";
                    }
                    if ($data["roleid"] == 3) {
                        $html.=" - Egyéb";
                    }
                }
                $html.= "</td>";
                $html.= "</tr>";
                $lastRole = $data["roleid"];
            }
            $html.= "<tr>";
            $html.= "<td valign='middle' style='padding:2px 10px 2px 0px;'>{$data["megnev"]}</td>";
            $html.= "</tr>";
        }
        $html.= "</table>";

        $html.= "</div>";
        return $html;
    }
}