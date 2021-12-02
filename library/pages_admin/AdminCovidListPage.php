<?php

class AdminCovidListPage extends AdminCorePage
{

    private $answerWords = ["", "A", "B", "C", "D", "E"];

    public function __construct()
    {
        parent::__construct();

        if (isset($_POST["setstatus"])) {
            if ($data = sql_fetch_array(sql_query("select n.*,d1.id AS covidigazolasid, d1.kod AS covidigazolaskod, d2.id AS covidegsid, d2.kod AS covidegskod from covid_oltas_naplo n 
                LEFT JOIN dokumentumok d1 ON d1.dataid = n.id AND d1.assetid='covidpassimage'
                LEFT JOIN dokumentumok d2 ON d2.dataid = n.userid AND d2.assetid='covidegsimage'
                where n.id=?", [$_POST["id"]]))) {
                if ($data["statusz"] != $_POST["setstatus"]) {
                    $checkedBy = "";
                    if ($_POST["setstatus"] != "IN PROGRESS") {
                        $checkedBy = $this->adminUser->user["username"];
                    }
                    sql_query("update covid_oltas_naplo set statusz=?, checkedby=?, checkeddate=now(), deniedtext=?  where id=?", [$_POST["setstatus"], $checkedBy, $_POST["deniedText"], $_POST["id"]]);
                    $data["checkedby"] = $checkedBy;
                    $data["checkeddate"] = date("Y-m-d H:i:s");
                    $data["statusz"] = $_POST["setstatus"];
                    //értesítés

                    $service = new NotificationService();
                    $service->covidListMessage($data["id"]);
                }

                echo $this->covidSor($data);
                die;
            }
            die;
        }

    }

    public function showPage()
    {
        //if (!$this->adminUtils->szurestipusModJog()) {
        //    return;
        //}

        if (empty($_GET["statusfilter"])) {
            $_GET["statusfilter"] = "";
        }

        $statusFilter = "";
        if (in_array($_GET["statusfilter"], ["IN PROGRESS", "APPROVED", "DENIED"])) {
            $statusFilter = $_GET["statusfilter"];
        }


        echo "<div>Szűrés: ";
        echo "<div onclick='setCovidListFilter(\"\");' style='display:inline-block;background:".($statusFilter == "" ? "#888":"#CCC").";color:#fff;padding:2px 5px;cursor:pointer;border-right:1px solid #888;'>ALL</div>";
        echo "<div onclick='setCovidListFilter(\"IN PROGRESS\");' style='display:inline-block;background:".($statusFilter == "IN PROGRESS" ? "#888":"#CCC").";color:#fff;padding:2px 5px;cursor:pointer;border-right:1px solid #888;'>IN PROGRESS</div>";
        echo "<div onclick='setCovidListFilter(\"APPROVED\");' style='display:inline-block;background:".($statusFilter == "APPROVED" ? "green":"#CCC").";color:#fff;padding:2px 5px;cursor:pointer;border-right:1px solid #888;'>APPROVED</div>";
        echo "<div onclick='setCovidListFilter(\"DENIED\");' style='display:inline-block;background:".($statusFilter == "DENIED" ? "red":"#CCC").";color:#fff;padding:2px 5px;cursor:pointer;'>DENIED</div>";
        echo "</div>";

        $users = sql_query("SELECT n.*, f.taj, f.nev, f.email, f.telefon, f.nocovid1, f.nocovid2, d1.id AS covidigazolasid, d1.kod AS covidigazolaskod, d2.id AS covidegsid, d2.kod AS covidegskod FROM covid_oltas_naplo n
            LEFT JOIN felhasznalok f ON f.id = n.userid
            LEFT JOIN dokumentumok d1 ON d1.dataid = n.id AND d1.assetid='covidpassimage'
            LEFT JOIN dokumentumok d2 ON d2.dataid = f.id AND d2.assetid='covidegsimage'
            WHERE f.id IS NOT NULL ".(empty($statusFilter)?"":"and n.statusz='{$statusFilter}'")."
            GROUP BY n.id
            ORDER BY f.nev, f.id, n.sorszam")->fetchAll(PDO::FETCH_ASSOC);

        echo "<table cellpadding='0' cellspacing='0' border='0' style='margin-top:20px;'>";
        echo "<tr style='background:#eee;'>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 5px;'>Név</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>TAJ</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Email</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Telefon</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Egészségügyi ok</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Nem kér</div></td>";
        echo "</tr>";

        $lastUser = "";

        $userNum = $covidNum = 0;

        foreach ($users as $user) {
            $tc = "tcella";

            if ($lastUser != $user["userid"]) {
                $lastUser = $user["userid"];
                $userNum++;
                //echo "<tr><td colspan='18' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                echo "<tr style='background: #eee;'>";
                echo "<td nowrap valign='top'><div class='{$tc}'>&nbsp;&nbsp;{$user["nev"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$user["taj"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$user["email"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$user["telefon"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}' style='text-align: center;'>" . ($user["nocovid1"] ? "IGEN" : "-") . (empty($user["covidegsid"])?"":": <a target='_blank' href='index.php?showfoto={$user["covidegsid"]}&c={$user["covidegskod"]}'>Fotó</a>"). "</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}' style='text-align: center;'>" . ($user["nocovid2"] ? "IGEN" : "-") . "</div></td>";
                echo "</tr>";
                echo "<tr><td colspan='18' style='height:6px;'></td></tr>";
            }


            echo "<tr style='' id='covidsor{$user["id"]}'>".$this->covidSor($user)."</tr>";
            $covidNum++;

        }
        echo "</table>";

        echo "<div style='margin-top:20px;'>Felhasználók: {$userNum}, bejegyzések: {$covidNum}</div>";

    }

    private function covidSor($data):string {
        $html = "";

        $html.= "<td colspan='18' style='padding-bottom:10px;'>";

        $html.= "<div onclick='setCovidListStatus(\"IN PROGRESS\", {$data["id"]});' style='display:inline-block;background:".($data["statusz"] == "IN PROGRESS" ? "#888":"#CCC").";color:#fff;padding:2px 5px;cursor:pointer;border-right:1px solid #888;'>IN PROGRESS</div>";
        $html.= "<div onclick='setCovidListStatus(\"APPROVED\", {$data["id"]});' style='display:inline-block;background:".($data["statusz"] == "APPROVED" ? "green":"#CCC").";color:#fff;padding:2px 5px;cursor:pointer;border-right:1px solid #888;'>APPROVED</div>";
        $html.= "<div onclick='setCovidListStatus(\"DENIED\", {$data["id"]});' style='display:inline-block;background:".($data["statusz"] == "DENIED" ? "red":"#CCC").";color:#fff;padding:2px 5px;cursor:pointer;'>DENIED</div>";
        $html.= "&nbsp;&nbsp;";

        $html.= "{$data["sorszam"]}. {$data["oltas_datum"]} {$data["oltas_tipus"]} ";
        if (!empty($data["covidigazolasid"])) {
            $html.= "<a target='_blank' href='index.php?showfoto={$data["covidigazolasid"]}&c={$data["covidigazolaskod"]}'>Fotó</a>";
        }

        if (!empty($data["checkedby"])) {
            $html.= "&nbsp;&nbsp;(ellenőrizte: {$data["checkedby"]} - ".(date("Y-m-d H:i", strtotime($data["checkeddate"]))).")";
        }

        if ($data["statusz"] == "DENIED" && !empty($data["deniedtext"])) {
            $html.="<div style='color:red;margin-top:5px;'>{$data["deniedtext"]}</div>";
        }

        $html.= "<div id='coviddeniedrow{$data["id"]}' style='display:none;'>";
        $html.= "<textarea id='coviddeniedtext{$data["id"]}' style='width:500px;height:80px;' placeholder='Add meg az elutasítás okát...'/></textarea>";
        $html.= "<div style='margin-top:5px;'><a onclick='setCovidListStatus(\"DENIEDCONFIRM\", {$data["id"]});return false;' class='abutton' href='#' class=''>Elutasítás elküldése</a></div>";
        $html.= "</div>";

        $html.= "</td>";

        return $html;
    }


}