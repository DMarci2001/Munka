<?php

class WebShopService {
    private KeltexMedWebSQL $keltexMedSql;
    private array $fizmodeTable = ["simple" => "<span style='background:darkgreen;color:white;padding:1px 3px;'>Fizetve!</span>", "keszpenz" => "Készpénz"];

    public function __construct() {
        $this->keltexMedSql = new KeltexMedWebSQL();

        if (isset($_POST["webshoporderack"])) {
            $this->keltexMedSql->sqlQuery("update orders set ack=1 where id=?", [$_POST["webshoporderack"]]);
            echo $this->showOrdersList();
            die;
        }
    }

    public function showOrdersList($mode = "recent"):string {
        if (Booking_Constants::SQL_DB != "keltexmed") {
            return "";
        }

        $html = "";
        $orders = $this->keltexMedSql->sqlQuery("SELECT * FROM orders WHERE aktiv=1 and ack=0 AND created>DATE_SUB(NOW(), INTERVAL 1 MONTH) ORDER BY created DESC")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($orders )) {
            $cellStyle = "display:table-cell;padding-right: 10px;";
            $html.= "<div style='margin-bottom: 10px;'>";
            $html.= "<div style='font-weight: bold'>Friss Webshop vásárlások</div>";
            $html.= "<table cellpadding='0' cellspacing='0' style='margin-top: 0px;'>";
            $html.= "<tr><td colspan='6'><div style='margin-bottom: 5px;padding-bottom:5px;border-bottom: 1px solid #ccc;'></div></td></tr>";
            foreach ($orders as $order) {
                $html.= "<tr>";
                $html.= "<td style='{$cellStyle}'>".date("Y-m-d H:i", strtotime($order["created"]))."</td>";
                $html.= "<td style='{$cellStyle}'>".$this->fizmodeTable[$order["fizmod"]]."</td>";
                $html.= "<td style='{$cellStyle}'>{$order["nev"]}</td>";
                $html.= "<td style='{$cellStyle}'>{$order["email"]}</td>";
                $html.= "<td style='{$cellStyle}'>{$order["telefon"]}</td>";
                $html.= "<td rowspan='2' style='{$cellStyle}padding-left:10px;'><a class='ujbutton' onclick='webShopOrderAck({$order["id"]});return false;' href='#'>Rendben</a></td>";
                $html.= "</tr>";

                $html.= "<tr>";
                $html.= "<td colspan='5'>";

                $orderItems = $this->keltexMedSql->sqlQuery("SELECT * FROM orderitems WHERE orderid=?", [$order["id"]])->fetchAll(PDO::FETCH_ASSOC);
                $html.= "<table cellpadding='0' cellspacing='0' style=''>";
                foreach ($orderItems as $orderItem) {
                    $html.= "<tr>";
                    $html.= "<td></td>";
                    $html.= "<td style='{$cellStyle}'>&bull; {$orderItem["productname"]}</td>";
                    $html.= "<td style='{$cellStyle}text-align: right;'>".number_format($orderItem["itemprice"])." Ft</td>";
                    $html.= "<td style='{$cellStyle}text-align: right;'>{$orderItem["quantity"]} db</td>";
                    $html.= "<td>";

                    if ($orderItem["reservationid"] != 0) {
                        if ($reservation = sql_query("select f.*, o.nev as orvosnev from foglalasok f
                            left join orvosok o on o.id=f.orvosassigned
                            where f.id=?", [$orderItem["reservationid"]])->fetch(PDO::FETCH_ASSOC)) {
                            $html.= "foglalt időpont: <a title='ugrás a foglalás napjához' href='#' onclick='setListDay(\"".date("Y-m-d", strtotime($reservation["datum"]))."\")'>".date("Y-m-d H:i", strtotime($reservation["datum"]))."</a> {$reservation["orvosnev"]}";
                        }
                    }

                    $html.= "</td>";
                    $html.= "</tr>";
                }
                $html.= "</table>";


                $html.= "</td>";
                $html.= "</tr>";
                $html.= "<tr><td colspan='6'><div style='margin-bottom: 5px;padding-bottom:5px;border-bottom: 1px solid #ccc;'></div></td></tr>";
            }
            $html.= "</table>";
            $html.= "</div>";
        }
        return $html;
    }


}