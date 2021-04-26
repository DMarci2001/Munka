<?php

class AdminOltasIgenyekPage extends AdminCorePage
{

    private $bookingService;

    public function __construct()
    {
        parent::__construct();
        $this->bookingService = new BookingService();

    }

    public function showPage() {
        echo "<div id='alkalmassaglista'>";
        echo $this->showOltasIgenyek();
        echo "</div>";
    }

    private function showOltasIgenyek() {
        $result = [];
        $html   = "";
        $oltasPage = new OltasIgenyFelmeresPage();
        $vakcinak = $oltasPage->vakcinak;

        $igenylesek = sql_query("SELECT * FROM webservicelog WHERE tipus=23 AND ACTION='oltasform_new'")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($igenylesek as $igenylesData) {
            $data = json_decode($igenylesData["keres"], JSON_OBJECT_AS_ARRAY);
            //$html.= "<pre>".print_r($data, true)."</pre>";

            $datum = date("Y-m-d", strtotime($igenylesData["datum"]));
            $csoport = $data["csoport"];


            foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                if (isset($data["vakcina{$vakcinaId}"])) {
                    @$result[$datum][$csoport][$vakcinaId]++;
                }

            }

        }

        //$html.= "<pre>".print_r($result, true)."</pre>";


        foreach ($result as $datum => $datumData) {
            $html .= "<h2>{$datum}</h2>";

            $html.="<div style='display:table-row;background:#ddd;'>";
            $html.="<div style='display:table-cell;padding:5px;'>Csoport</div>";
            foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                $html.= "<div style='display:table-cell;padding:5px;'>&nbsp;&nbsp;&nbsp;{$vakcinaData["name"]}</div>";
            }
            $html.="</div>";

            foreach ($datumData as $csoportId => $csoportData) {
                $html.="<div style='display:table-row;'>";
                $html.="<div style='display:table-cell;padding:5px 5px 5px 5px;border-bottom:1px solid #ccc;'>{$csoportId}</div>";

                foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                    $html.= "<div style='display:table-cell;padding:5px 5px 5px 5px;text-align: right;border-bottom:1px solid #ccc;'>";
                    if (isset($csoportData[$vakcinaId])) {
                        $html.= $csoportData[$vakcinaId]." db";
                    }
                    $html.= "</div>";
                }

                $html.="</div>";

            }
        }



        return $html;
    }




}

