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
		
		$html=$columnTitle=$rows="";
		$count=0;
		//Oszlopok:
		//Mit lenne érdemes kirakni?
		//tranzakció azonosítóját, páciens nevét, termék megnevezése, fizetési szolgáltatás, keltezés, összeg,tranzakació státusza
		$columns=array("#.","Név","Tranzakció Azon.","Telefon","Dátum","E-mail","Vizsgálat","Összeg","Eredmény");
		//BACK-END:
		
		$columnTitle.="<tr>";
		foreach($columns as $column) $columnTitle.="<td style='border-top:none'>{$column}</td>";
		$columnTitle.= "</tr>";
		
		$request=sql_query("SELECT trans.id,fogl.nev,trans.datum,trans.transid,fogl.telefon,fogl.email,sz.megnev,trans.osszeg,trans.result FROM banktransactions trans
							LEFT JOIN foglalasok fogl ON fogl.id=trans.foglalasid
							LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
							ORDER BY fogl.regdatum DESC, trans.datum desc");
							
		while($result=sql_fetch_array($request)){
			$count++;
			$rows.="<tr>";
			$rows.="	<td>#{$count}.</td>";
			$rows.="	<td align='center'>{$result['nev']}</td>";
			$rows.="	<td align='center'>{$result['transid']}</td>";
			$rows.="	<td>{$result['telefon']}</td>";
			$rows.="	<td>{$result['datum']}</td>";
			$rows.="	<td>{$result['email']}</td>";
			$rows.="	<td>{$result['megnev']}</td>";
			$rows.="	<td align='center'>{$result['osszeg']}FT</td>";
			$rows.="	<td align='center'>{$result['result']}</td>";
			$rows.="	<td>";
			if ( $result['result']=="FINISHED" && $this->adminUtils->tranzakciokezelesModJog()){
				$rows.="[<a href='#' class='retransfer_button' onClick='retranserOperation({$result['id']})' >VISSZAUTALÁS</a>]";
			}
			$rows.="	</td>";
			
			$rows.="</tr>";
		}
		
		//FRONT-END:
		//Oszlopokat ki kéne ide rakni :P
		
		$html.="<table class='transactions_table' style='border-collapse:collapse;'>";
		$html.= $columnTitle;
		$html.=	$rows;
		$html.="</table>";
		
		echo $html;
	}
}

?>