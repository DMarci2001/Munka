<?php

class AdminBanktransactionsPage extends AdminCorePage {

    private $paymentSource;

    public function __construct()
    {
        parent::__construct();
        if (!isset($_SESSION["paymentsource"])) {
            $_SESSION["paymentsource"] = "bejelentkezo";
            if (Booking_Constants::SQL_DB == "keltexmed") {
                $_SESSION["paymentsource"] = "keltexmedwebshop";
            }
        }
        if (isset($_GET["paymentsource"])) {
            $_SESSION["paymentsource"] = $_GET["paymentsource"];
        }
        $this->paymentSource = $_SESSION["paymentsource"];

        if (isset($_POST["showrefund"]) && $this->adminUser->authenticated()) {
            $simpleService = new SimplePayService();
            echo $simpleService->showRefundWindow($_POST["source"], $_POST["showrefund"]);
            die;
        }

        if (isset($_POST["startsimplerefund"]) && $this->adminUser->authenticated()) {
            $simpleService = new SimplePayService();
            echo $simpleService->startRefund($_POST["startsimplerefund"], $_POST["osszeg"], $_POST["source"]);
            die;
        }
    }
	
	public function showPage() {
		if (!$this->adminUser->tranzakcioAccess()) {
            return;
        }

        //orderid mező kitöltése
        $vasarlasok = sql_query("select id, cart_content from labshop_vasarlasok where date>date_sub(now(), interval 1 week) and (bankorderid is null or reservationid=0) order by date desc")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($vasarlasok as $vasarlas) {
            $data = json_decode($vasarlas["cart_content"], JSON_OBJECT_AS_ARRAY);
            if (isset($data["orderid"])) {
                $reservationId = 0;
                foreach ($data as $key => $subData) {
                    if (isset($subData["reservationId"]) && $reservationId == 0) {
                        $reservationId = $subData["reservationId"];
                    }
                }
                sql_query("update labshop_vasarlasok set bankorderid=?, reservationid=? where id=?", [$data["orderid"], $reservationId, $vasarlas["id"]]);
            }
        }


		$html=$columnTitle=$rows=$border="";
		//$count=$previousFoglId=0;
		$columns=array("Azonosító","Dátum","Név","Telefon","E-mail","Vizsgálat","Orvos","Összeg","Eredmény");

		$columnTitle.="<tr>";
		foreach($columns as $column) {
            $columnTitle.="<td style='border-bottom:1px solid black;text-align: left;'>{$column}</td>";
        }
		$columnTitle.= "</tr>";

        $request = [];

        if ($this->paymentSource == "bejelentkezo") {
            $request = sql_query("SELECT fogl.id AS foglid,o.nev as orvosnev,trans.merchant,trans.id,fogl.nev,trans.datum,trans.transid,fogl.telefon,fogl.email,sz.megnev as vizsgalat,trans.osszeg,trans.result, trans.orderid, trans.ack, trans.customer_name FROM banktransactions trans
							LEFT JOIN foglalasok fogl ON fogl.id=trans.foglalasid
							LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
							LEFT JOIN orvosok o ON o.id=fogl.orvosassigned
							ORDER BY trans.datum DESC, trans.datum desc")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($this->paymentSource == "keltexmedwebshop") {
            $keltexMedSql = new KeltexMedWebSQL();
            $request = $keltexMedSql->sqlQuery("SELECT t.*, o.nev, o.email, o.telefon, group_concat(concat(oi.quantity, 'x ', oi.productname) separator ', ') as vizsgalat FROM banktransactions t
                            left join orders o on o.id = t.orderid
                            left join orderitems oi on oi.orderid=o.id
							group by t.id, o.id ORDER BY t.datum desc")->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($request as $result) {
            $signs = "";
            $cartItems = [];
            if (!empty($result["orderid"])) {
                if ($labshopData = sql_query("SELECT f.*, v.cart_content from labshop_vasarlasok v LEFT JOIN foglalasok f ON f.id=v.reservationid WHERE v.bankorderid=?", [$result["orderid"]])->fetch(PDO::FETCH_ASSOC)) {
                    $result["nev"] = $labshopData["nev"];
                    $result["telefon"] = $labshopData["telefon"];
                    $result["email"] = $labshopData["email"];
                    $signs.= "<span style='background:lightblue;color:#fff;padding:2px 5px;'>LABSHOP</span> ";

                    $cartContents = json_decode($labshopData["cart_content"], JSON_OBJECT_AS_ARRAY);
                    foreach ($cartContents as $cartContent) {
                        if (isset($cartContent["type"]) && $cartContent["type"] == "package") {
                            $itemData = sql_query("select name from synlab_labor_csomagok cs where cs.id=?", [$cartContent["id"]])->fetch(PDO::FETCH_ASSOC);
                            $cartItems[] = "<span style='background:lightslategray;color:#fff;padding:2px 5px;white-space: nowrap;'>{$itemData["name"]} {$cartContent["price"]} Ft</span>";
                        }
                        if (isset($cartContent["type"]) && $cartContent["type"] == "exam") {
                            $itemData = sql_query("select megnev from arak t where t.id=?", [$cartContent["id"]])->fetch(PDO::FETCH_ASSOC);
                            $cartItems[] = "<span style='background:lightslategray;color:#fff;padding:2px 5px;white-space: nowrap;'>{$itemData["megnev"]} {$cartContent["price"]} Ft</span>";
                        }
                    }
                }
            }

            if (empty($result["nev"]) && Booking_Constants::SQL_DB == "hungariamed") {
                $result["nev"] = $result["customer_name"];
                $ackData = json_decode($result["ack"], JSON_OBJECT_AS_ARRAY);
                if (substr_count($ackData["orderRef"], "labshop") != 0) {
                    $signs.= "<span style='background:lightblue;color:#fff;padding:2px 5px;'>LABSHOP</span> ";
                }
            }

            $signs.= $result["merchant"] == "PUBLICTESTHUF" ? "<span style='background:lightblue;color:#fff;padding:2px 5px;'>TESZT</span> ":"";

			$resultCSS="style='font-weight:bold'";
            $border="style='border-top:1px solid black'";

			$rows.="<tr>";
			$rows.="<td {$border}>{$result['transid']}</td>";
			$rows.="<td {$border}>{$result['datum']}</td>";
            $rows.="<td {$border}>{$signs}{$result['nev']}</td>";
            $rows.="<td {$border}>{$result['telefon']}</td>";
            $rows.="<td {$border}>{$result['email']}</td>";

            if (empty($cartItems)) {
                $rows .= "<td {$border}>{$result['vizsgalat']}</td>";
                $rows .= "<td {$border}>{$result['orvosnev']}</td>";
            } else {
                $rows .= "<td colspan='2' {$border} >".implode(" ", $cartItems)."</td>";
            }
			
			$rows.="	<td {$border} align='right'>{$result['osszeg']} Ft</td>";
			if($result['result']=="FINISHED") $resultCSS="style='font-weight:bold;color:#00cc00'";
			if($result['result']=="FAIL") $resultCSS="style='font-weight:bold;color:red'";
			$rows.="	<td {$border}><span {$resultCSS}>{$result['result']}</span></td>";
			$rows.="	<td {$border}>";
			if ( $result['result']=="FINISHED" && $this->adminUser->tranzakcioModAccess()){
                $source = $this->paymentSource;
                if (!empty($result["orderid"])) {
                    $source = "labshop";
                }
                if ($this->paymentSource == "keltexmedwebshop") {
                    $source = "keltexmedwebshop";
                }
				$rows.="[<a href='#' class='retransfer_button' onClick='showRefundWindow(\"{$source}\", {$result['id']})' >VISSZAUTALÁS</a>]";
			}
			$rows.="	</td>";
			
			$rows.="</tr>";
		}

        $html.= "<div>";
        $html.= "<a class='filterbutton' style='".($this->paymentSource == "bejelentkezo"?"background:#888;":"")."' href='index.php?page={$_GET["page"]}&paymentsource=bejelentkezo'>Bejelentkező</a> ";
        $html.= "<a class='filterbutton' style='".($this->paymentSource == "keltexmedwebshop"?"background:#888;":"")."'  href='index.php?page={$_GET["page"]}&paymentsource=keltexmedwebshop'>Keltexmed WebShop</a>";
        $html.= "</div>";

		$html.="<table class='transactions_table' style='border-collapse:collapse;margin-top: 10px;'>";
		$html.= $columnTitle;
		$html.=	$rows;
		$html.="</table>";
		
		echo $html;
	}
}

