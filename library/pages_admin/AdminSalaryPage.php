<?php


class AdminSalaryPage extends AdminCorePage
{

    private $salaryCalculator;

    public function __construct()
    {
        parent::__construct();

        $this->salaryCalculator = new SalaryCalculator();

        if (!isset($_SESSION["salarydatumtol"])) $_SESSION["salarydatumtol"] = date("Y-m-01", strtotime("now - 1 month"));
        if (!isset($_SESSION["salarydatumig"])) $_SESSION["salarydatumig"] = date("Y-m-t", strtotime($_SESSION["salarydatumtol"]));

        if (isset($_POST["datefrom"])) $_SESSION["salarydatumtol"] = $_POST["datefrom"];
        if (isset($_POST["dateto"])) $_SESSION["salarydatumig"] = $_POST["dateto"];

        if (isset($_GET["loadlogdetail"])) {
            $row = sql_fetch_array(sql_query("select * from activitylog where id=?", array($_GET["loadlogdetail"])));
            $query = nl2br(str_replace(" ", "&nbsp;", $row["query"]));
            ob_clean();
            echo "<div style='background:#eee;padding:10px;width:900px;'>{$query}</div>";
            die();
        }

        if (isset($_POST["refreshmonthsalary"])) {
            $dateFrom = $_SESSION["salarydatumtol"] = $_POST["month"]."-01";
            $dateTo   = $_SESSION["salarydatumig"] = $_POST["month"].date("-t", strtotime($dateFrom));

            echo $this->_salaryTable($dateFrom, $dateTo);
            die;
        }

        if (isset($_POST["refreshsalary"])) {
            $html = "";
            $dateFrom = $_POST["datefrom"];
            $dateTo   = $_POST["dateto"];

            if (strtotime($dateFrom) > strtotime($dateTo)) {
                $this->utils->jsonOut(["html"=>$html, "error" => "A kezdő dátum nem lehet nagyobb mint a végdátum!"]);
            }

            $_SESSION["salarydatumtol"] = $dateFrom;
            $_SESSION["salarydatumig"] = $dateTo;

            $html = $this->_salaryTable($dateFrom, $dateTo);
            $this->utils->jsonOut(["html" => $html, "error" => ""]);
            die;
        }

        if (isset($_POST["salarydatanew"])) {
            $oid = intval($_POST["oid"]);

            sql_query("insert into salarydata set orvosid=?, datefrom=date(now())", [$oid]);
            $html = $this->salaryDataEditor($oid);
            $this->utils->jsonOut(["html" => $html, "error" => ""]);
            die;
        }

        if (isset($_POST["salarydatadelete"])) {
            $oid = intval($_POST["oid"]);
            $sid = intval($_POST["sid"]);

            sql_query("delete from salarydata where id=? and orvosid=?", [$sid, $oid]);
            $html = $this->salaryDataEditor($oid);
            $this->utils->jsonOut(["html" => $html, "error" => ""]);
            die;
        }

        if (isset($_GET["salarydatasave"])) {
            $oid = intval($_GET["oid"]);
            $error = "";

            $sor = 1;

            while (isset($_POST["sdid{$sor}"])) {
                $salaryType = $_POST["salarytype{$sor}"];
                $dateFrom   = $_POST["datefrom{$sor}"];
                $dateTo     = $_POST["dateto{$sor}"];

                if (!in_array($salaryType, ["onetime", ""]) && strtotime($dateFrom) > strtotime($dateTo)) {
                    $error = "Hibás érvényességi időtartam! {$salaryType}";
                    break;
                }

                sql_query("update salarydata set salarytype=?, price=?, datefrom=?, dateto=?, description=? where id=?", [$salaryType, $_POST["price{$sor}"], $dateFrom, $dateTo, $_POST["description{$sor}"], $_POST["sdid{$sor}"]]);
                $sor++;
            }
            $html = $this->salaryDataEditor($oid);
            $this->utils->jsonOut(["html" => $html, "error" => $error]);
            die;
        }
    }

    public function showPage() {
        if (!$this->adminUser->salaryAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        $dateFrom = $_SESSION["salarydatumtol"];
        $dateTo   = $_SESSION["salarydatumig"];

        echo "<div>";
        for ($i=0; $i<12; $i++) {
            $year  = date("Y", strtotime("now - {$i} month"));
            $month = date("n", strtotime("now - {$i} month"));
            $month0 = date("m", strtotime("now - {$i} month"));

            echo "<a onclick='refreshMonthSalary(\"{$year}-{$month0}\");' class='ujbutton' style='margin-bottom:4px;' href='#'>{$year} ".$this->adminUtils->settings->honaptext[$month]."</a> ";
        }
        echo "</div>";


        echo "<div id='salarycontainer'>";
        echo $this->_salaryTable($dateFrom, $dateTo);
        echo "</div>";
    }


    public function salaryDataEditor($oid) {
        $html = "";

        $html.= "<div style='background:#eee;display:inline-block;padding:10px;'>";
        $html.= "<form method='post' id='salarydataeditorform{$oid}' onsubmit='return false;'>";
        $html.= "<table>";

        $salaryDatas = sql_query("select * from salarydata where orvosid=?", [$oid])->fetchAll(PDO::FETCH_ASSOC);

        $html.= "<tr style='font-weight: bold;'>";
        $html.= "<td>Típus</td>";
        $html.= "<td>Összeg</td>";
        $html.= "<td>Időszak</td>";
        $html.= "<td>Megjegyzés</td>";
        $html.= "</tr>";

        $sor = 1;
        foreach ($salaryDatas as $salaryData) {
            $html.="<tr>";
            $html.="<td>";

            $html.= "<input type='hidden' name='sdid{$sor}' value='{$salaryData["id"]}'/>";

            //echo "<input title='aktív?' type='checkbox' name='aktiv{$sor}' value='1' ".($rowb["aktiv"]==1?" checked":"")."/> ";

            $html.= "<select name='salarytype{$sor}' onchange=\"if (this.value!=10) { $('#hetek{$sor}').show(); $('#beonap{$sor}').hide(); } else { $('#hetek{$sor}').hide(); $('#beonap{$sor}').show(); }\">";
            $html.= "<option value=''>Válassz tipust!</option>";
            foreach ($this->salaryCalculator->salaryTypes as $key => $data) {
                $html.= "<option value='{$key}'".($salaryData["salarytype"]==$key?" selected":"").">{$data["description"]}</option>";
            }
            $html.= "</select> ";
            $html.= "</td>";

            $html.= "<td>";
            $html.= "<input id='price{$sor}' name='price{$sor}' type='text' value='{$salaryData["price"]}' style='width:82px;' placeholder='' /> ";
            $html.= "</td>";

            $html.= "<td>";
            $html.= "<input id='datefrom{$sor}' name='datefrom{$sor}' type='text' value='{$salaryData["datefrom"]}' style='width:82px;' placeholder='éééé-hh-nn' /> ";
            $html.= "<input id='dateto{$sor}' name='dateto{$sor}' type='text' value='{$salaryData["dateto"]}' style='width:82px;' placeholder='éééé-hh-nn' /> ";
            $html.= "</td>";

            $html.= "<td>";
            $html.= "<input id='description{$sor}' name='description{$sor}' type='text' value='{$salaryData["description"]}' style='width:282px;' placeholder='' /> ";
            $html.= "</td>";

            $html.= "<td>";
            $html.= "[<a href='#' onclick='salaryDataDelete({$oid}, {$salaryData["id"]});return false;'>törlés</a>] ";
            $html.= "</td>";

            $html.= "</tr>";
            $sor++;
        }

        $html.= "<tr style='font-weight: bold;'>";
        $html.= "<td colspan='4'><div style='margin-top: 10px;'><a class='ujbutton' href='#' onclick='salaryDataSave({$oid});return false;'>Mentés</a>&nbsp;&nbsp;<a class='ujbutton' href='#' onclick='$(\"#salarydataeditor{$oid}\").toggle();return false;'>Bezárás</a>&nbsp;&nbsp;<a class='ujbutton' href='#' onclick='salaryDataNew({$oid});return false;'>+ új sor hozzáadása</a></div></td>";
        $html.= "</tr>";

        $html.= "</table>";
        $html.= "</form>";
        $html.= "</div>";
        return $html;
    }

    private function _salaryTable($dateFrom, $dateTo) {
        $html = "";

        $html.= "<div style='margin-top: 10px;'>";
        $html.= "";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><input class='napinput' id='datefrom' value='{$dateFrom}' style='font-size:18px;background-color:#eee;color:#444;border:1px solid #ccc;' data-page='{$_GET["page"]}' /></div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'>&nbsp;&nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><input class='napinput' id='dateto' value='{$dateTo}' style='font-size:18px;background-color:#eee;color:#444;border:1px solid #ccc;' data-page='{$_GET["page"]}' />&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><a onclick='refreshSalary();' class='ujbutton' style='padding:8px 13px;' href='#'>Frissítés</a></div>";
        $html.= "</div>";


        $orvosok = sql_query("select o.* from orvosok o 
        left join salarydata sd on sd.orvosid=o.id
        where o.pecsetszam<>'temp' 
        group by o.id order by o.nev")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orvosok as $orvos) {
            $html.= "<h2>{$orvos["nev"]}</h2>";

            $html.= "<div style='margin-top:10px;'>[<a href='#' onclick='$(\"#salarydataeditor{$orvos["id"]}\").toggle();return false;'>Fizetési adatok megadása</a>]</div>";
            $html.= "<div id='salarydataeditor{$orvos["id"]}' style='display:none;padding-top:10px;'>".$this->salaryDataEditor($orvos["id"])."</div>";

            $salaryData = $this->salaryCalculator->getDoctorSalary($orvos["id"], $dateFrom, $dateTo);

            if (!empty($salaryData)) {
                $html .= "<div style='margin-top:10px;'>Elszámolási időszak: {$dateFrom} - {$dateTo}</div>";
                $html .= "<table cellpadding='0' cellspacing='0' style='margin-top:10px;'>";
                $html .= "<tr style='background:#eee;font-weight: bold;'>";
                $html .= "<td class='bercella' style='padding:4px 2px;'>Jogcím</td>";
                $html .= "<td class='bercella'>Időszak</td>";
                $html .= "<td class='bercella bcellkozep'>Mennyiség</td>";
                $html .= "<td class='bercella bcellkozep'>Bér</td>";
                $html .= "</tr>";

                $total = 0;

                foreach ($salaryData as $key => $salaryItem) {

                    if ($key == "monthly") {
                        foreach ($salaryItem as $month => $item) {
                            $html .= "<tr>";
                            $html .= "<td class='bercella'>Havi fizetés</td>";
                            $html .= "<td class='bercella'>{$month} hó</td>";
                            $html .= "<td class='bercella bcelljobb'>" . round($item["workeddays"] / $item["workdays"], 1) . " hónap</td>";
                            $html .= "<td class='bercella bcelljobb'>" . $this->moneyFormat($item["price"]) . " Ft</td>";
                            $html .= "</tr>";
                            $total += $item["price"];
                        }
                    }

                    if ($key == "perhour") {
                        foreach ($salaryItem as $day => $items) {
                            foreach ($items as $item) {
                                $html .= "<tr>";
                                $html .= "<td class='bercella'>Órabér (" . $this->moneyFormat($item["unitprice"]) . " Ft/óra)</td>";
                                $html .= "<td class='bercella'>{$day}</td>";
                                $html .= "<td class='bercella bcelljobb'>{$item["hour"]} óra</td>";
                                $html .= "<td class='bercella bcelljobb'>" . $this->moneyFormat($item["price"]) . " Ft</td>";
                                $html .= "</tr>";
                                $total += $item["price"];
                            }
                        }
                    }

                    if ($key == "perpatient") {
                        foreach ($salaryItem as $day => $items) {
                            foreach ($items as $item) {
                                $html .= "<tr>";
                                $html .= "<td class='bercella'>Fejpénz (" . $this->moneyFormat($item["unitprice"]) . " Ft/paciens)</td>";
                                $html .= "<td class='bercella'>{$day}</td>";
                                $html .= "<td class='bercella bcelljobb'>" . count($item["reservations"]) . " paciens</td>";
                                $html .= "<td class='bercella bcelljobb'>" . $this->moneyFormat($item["price"]) . " Ft</td>";
                                $html .= "</tr>";
                                $total += $item["price"];
                            }
                        }
                    }

                    if ($key == "onetime") {
                        foreach ($salaryItem as $day => $items) {
                            foreach ($items as $item) {
                                $html .= "<tr>";
                                $html .= "<td class='bercella'>{$item["description"]}</td>";
                                $html .= "<td class='bercella'>{$day}</td>";
                                $html .= "<td class='bercella bcelljobb'>1 db</td>";
                                $html .= "<td class='bercella bcelljobb'>" . $this->moneyFormat($item["price"]) . " Ft</td>";
                                $html .= "</tr>";
                                $total += $item["price"];
                            }
                        }
                    }

                }

                $html .= "<tr style='font-weight: bold;'>";
                $html .= "<td class='bercella'>Összesen</td>";
                $html .= "<td class='bercella bcelljobb' colspan='3'>" . $this->moneyFormat($total) . " Ft</td>";
                $html .= "</tr>";

                $html .= "</table>";
            }

            //$html.= "<pre>".print_r($salaryData, true)."</pre>";
        }
        return $html;
    }

    private function moneyFormat($num) {
        return number_format($num);
    }

}

