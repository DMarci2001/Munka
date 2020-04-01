<?php

class AdminBanktransactionsPage extends AdminCorePage {
public function __construct()
    {
        parent::__construct();
	}
	
	public function showPage() {
		if (!$this->adminUtils->tranzakciolatasModJog()) {
            return;
        }
		
		$html=$columnTitle=$rows=$border="";
		$count=$previousFoglId=0;
		//Oszlopok:
		//Mit lenne érdemes kirakni?
		//tranzakció azonosítóját, páciens nevét, termék megnevezése, fizetési szolgáltatás, keltezés, összeg,tranzakació státusza
		$columns=array("Tranzakció Azon.","Dátum","Név","Telefon","E-mail","Vizsgálat","Orvos","Összeg","Eredmény");
		//BACK-END:
		
		$columnTitle.="<tr>";
		foreach($columns as $column) $columnTitle.="<td style='border-bottom:1px solid black'>{$column}</td>";
		$columnTitle.= "</tr>";
		
		$request=sql_query("SELECT fogl.id AS foglid,o.nev as orvosnev,trans.id,fogl.nev,trans.datum,trans.transid,fogl.telefon,fogl.email,sz.megnev,trans.osszeg,trans.result FROM banktransactions trans
							LEFT JOIN foglalasok fogl ON fogl.id=trans.foglalasid
							LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
							LEFT JOIN orvosok o ON o.id=fogl.orvosassigned
							ORDER BY fogl.regdatum DESC, trans.datum desc");
		
		while($result=sql_fetch_array($request)){
			$count++;
			$resultCSS="style='font-weight:bold'";
			if($previousFoglId==0) $previousFoglId=$result['foglid'];
			if($previousFoglId!=$result['foglid']) $border="style='border-top:1px solid black'";
			else $border="";
			$rows.="<tr>";
			//$rows.="	<td {$border}>#{$count}.</td>";
			$rows.="	<td {$border} align='center'>{$result['transid']}</td>";
			$rows.="	<td {$border} >{$result['datum']}</td>";
			if($previousFoglId!=$result['foglid'] || $count==1){
				$rows.="<td {$border} align='center'>{$result['nev']}</td>";
				$rows.="<td {$border} >{$result['telefon']}</td>";
				$rows.="<td {$border} >{$result['email']}</td>";
				$rows.="<td {$border} >{$result['megnev']}</td>";
				$rows.="<td {$border} >{$result['orvosnev']}</td>";
			}
			else{
				$rows.="<td {$border} ></td><td {$border}></td><td {$border}></td><td {$border}></td><td {$border}></td>";
			}
			
			
			$rows.="	<td {$border} align='center'>{$result['osszeg']} Ft</td>";
			if($result['result']=="FINISHED") $resultCSS="style='font-weight:bold;color:#00cc00'";
			if($result['result']=="FAIL") $resultCSS="style='font-weight:bold;color:red'";
			$rows.="	<td {$border} align='center'><span {$resultCSS}>{$result['result']}</span></td>";
			$rows.="	<td {$border}>";
			if ( $result['result']=="FINISHED" && $this->adminUtils->tranzakciokezelesModJog()){
				$rows.="[<a href='#' class='retransfer_button' onClick='retranserOperation({$result['id']})' >VISSZAUTALÁS</a>]";
			}
			$rows.="	</td>";
			
			$rows.="</tr>";
			$previousFoglId=$result['foglid'];
		}
		
		//FRONT-END:
		//Oszlopokat ki kéne ide rakni :P
		
		$html.="<table class='transactions_table' style='border-collapse:collapse'>";
		$html.= $columnTitle;
		$html.=	$rows;
		$html.="</table>";
		
		echo $html;
	}
}

?>