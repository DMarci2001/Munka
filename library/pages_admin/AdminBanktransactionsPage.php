<?php

class AdminBanktransactionsPage extends AdminCorePage {

    private $paymentSource;

    public function __construct()
    {
        parent::__construct();
        if (!isset($_SESSION["paymentsource"])) {
            $_SESSION["paymentsource"] = "bejelentkezo";
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

		$html=$columnTitle=$rows=$border="";
		//$count=$previousFoglId=0;
		$columns=array("Azonosító","Dátum","Név","Telefon","E-mail","Vizsgálat","Orvos","Összeg","Eredmény");

		$columnTitle.="<tr>";
		foreach($columns as $column) {
            $columnTitle.="<td style='border-bottom:1px solid black;text-align: left;'>{$column}</td>";
        }
		$columnTitle.= "</tr>";

        if ($this->paymentSource == "bejelentkezo") {
            $request = sql_query("SELECT fogl.id AS foglid,o.nev as orvosnev,trans.id,fogl.nev,trans.datum,trans.transid,fogl.telefon,fogl.email,sz.megnev as vizsgalat,trans.osszeg,trans.result FROM banktransactions trans
							LEFT JOIN foglalasok fogl ON fogl.id=trans.foglalasid
							LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
							LEFT JOIN orvosok o ON o.id=fogl.orvosassigned
							ORDER BY fogl.regdatum DESC, trans.datum desc")->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($this->paymentSource == "keltexmedwebshop") {
            $keltexMedSql = new KeltexMedWebSQL();
            $request = $keltexMedSql->sqlQuery("SELECT t.*, o.nev, o.email, o.telefon, group_concat(concat(oi.quantity, 'x ', oi.productname) separator ', ') as vizsgalat FROM banktransactions t
                            left join orders o on o.id = t.orderid
                            left join orderitems oi on oi.orderid=o.id
							group by t.id, o.id ORDER BY t.datum desc")->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($request as $result) {
			//$count++;
			$resultCSS="style='font-weight:bold'";
			//if($previousFoglId==0) $previousFoglId=$result['foglid'];
			//if($previousFoglId!=$result['foglid']) $border="style='border-top:1px solid black'";
			//else $border="";
            $border="style='border-top:1px solid black'";
			$rows.="<tr>";
			//$rows.="	<td {$border}>#{$count}.</td>";
			$rows.="	<td {$border}>{$result['transid']}</td>";
			$rows.="	<td {$border}>{$result['datum']}</td>";
			//if($previousFoglId!=$result['foglid'] || $count==1){
				$rows.="<td {$border}'>{$result['nev']}</td>";
				$rows.="<td {$border} >{$result['telefon']}</td>";
				$rows.="<td {$border} >{$result['email']}</td>";
				$rows.="<td {$border} >{$result['vizsgalat']}</td>";
				$rows.="<td {$border} >{$result['orvosnev']}</td>";
			//}
			//else{
			//	$rows.="<td {$border} ></td><td {$border}></td><td {$border}></td><td {$border}></td><td {$border}></td>";
			//}
			
			
			$rows.="	<td {$border} align='right'>{$result['osszeg']} Ft</td>";
			if($result['result']=="FINISHED") $resultCSS="style='font-weight:bold;color:#00cc00'";
			if($result['result']=="FAIL") $resultCSS="style='font-weight:bold;color:red'";
			$rows.="	<td {$border}><span {$resultCSS}>{$result['result']}</span></td>";
			$rows.="	<td {$border}>";
			if ( $result['result']=="FINISHED" && $this->adminUser->tranzakcioModAccess()){
				$rows.="[<a href='#' class='retransfer_button' onClick='showRefundWindow(\"{$this->paymentSource}\", {$result['id']})' >VISSZAUTALÁS</a>]";
			}
			$rows.="	</td>";
			
			$rows.="</tr>";
			$previousFoglId=$result['foglid'];
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

