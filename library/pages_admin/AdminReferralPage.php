<?php
class AdminReferralPage extends AdminCorePage {

    public function __construct()
    {
        parent::__construct();
		
		if(isset($_POST['deleteSelectedReferrals']) && $_GET['page']=="referral"){
			echo "itt vagyok";
			echo "<pre>";
			print_r($_POST);
			echo "</pre>";
		}
		
	}
	
	public function showPage() {
		//BACK-END
		$html=$columnTitle=$content="";
		$row=0;
		
		if(!isset($_POST['data-list'])) $_POST['data-list']="waiting";
		
		//$columns=array("Azon.","Dátum","Név","Telefon","E-mail","Vizsgálat","Orvos","Összeg","Eredmény");
		$columns=array("Teljesnév","Szül. dátum","Munkakör","Cég","Beutaló típus","Alkalmassági lejárat","Kiállítva","Elküldve","<span id='checkBoxSwitcher' onClick='switchCheckBoxes(\"referral-checker\",\"disable\")' style='color:red;cursor:pointer'>Egyikse</span>");
		
		$columnTitle= implode("</td><td>",$columns);
		
		if($_POST['data-list']=="waiting"){
			$request = sql_query("SELECT felh.*,ref.*,c.megnev,ref.id AS rid FROM beutalo_ertesites ref
								  LEFT JOIN felhasznalok_teszt felh ON felh.id=ref.fid
								  LEFT JOIN cegek c ON c.id=felh.cegid
								  WHERE ref.sent is NULL OR ref.sent='' AND ref.subject='Beutaló' 
								  ORDER BY ref.expiration");
		}
		
		if($_POST['data-list']=="sent"){
			$request = sql_query("SELECT felh.*,ref.*,c.megnev,ref.id AS rid FROM beutalo_ertesites ref
								  LEFT JOIN felhasznalok_teszt felh ON felh.id=ref.fid
								  LEFT JOIN cegek c ON c.id=felh.cegid
								  WHERE ref.sent IS NOT NULL OR ref.sent<>'' AND ref.subject='Beutaló' 
								  ORDER BY ref.timestamp");
		}
							  
		while($result=sql_fetch_array($request)){
			$content.="<tr>";
			$content.= "<td>{$result['nev']}</td>";
			$content.= "<td>{$result['szuldatum']}</td>";
			$content.= "<td>{$result['munkakor']}</td>";
			$content.= "<td>{$result['megnev']}</td>";
			$content.= "<td>még nincsen</td>";
			$content.= "<td>{$result['expiration']}</td>";
			$content.= "<td>{$result['timestamp']}</td>";
			$content.= "<td>{$result['sent']}</td>";
			$content.= "<td align='center' onClick='toggleCheckBox(\"#{$result['rid']}\")'><input type='checkbox' onClick='toggleCheckBox(\"#{$result['rid']}\")' checked class='referral-checker' id='{$result['rid']}' value='{$result['rid']}'/></td>";
			$content.="</tr>";
			$row++;
		}
							  
		//Találjuk ki, mit akarunk láátni :P Szükségünk van a pácienst beazonosító információkra, név, szül dátum, munkakör, cég, beutaló típus ami ki fogmenni(ezt eddig le se rögzítettem bakker)
		//Az aktuális értesítésről szóló lejárati idő, és a dokumenum maga (báár itt ezt nem biztos hogy megakarom jeleníteni.)
		
		$html.="<h3>Műveletek</h3>";
		if($_POST['data-list']=="waiting"){
			$html.= "<input type='submit' href='#' value='Kiválaszott adatsorok törlése' name='deleteSelectedReferrals' />&nbsp;&nbsp;";
			$html.= "<input type='submit' href='#' value='Kiválaszott adatsorok kiküldése értésítésre' name='sendSelectedReferrals' />";
		}
		
		$html.="<br><br>";
		
		//Itt akarok törlést kiküldést beépíteni, először kezdjük a törléssel és a ki jelölésen alapuló törlésen
		$html.="<select onChange='$(this).closest(\"form\").submit();' name='data-list' class='design-put' style='width:auto'>";
		$html.="	<option ".($_POST['data-list']=="waiting"?"selected":"")." value='waiting'>Rendszerben várakozó beutaló értesítése</option>";
		$html.="	<option ".($_POST['data-list']=="sent"?"selected":"")." value='sent'>Kiküldött értesítések</option>";
		$html.="</select>";
		
		//$html.="<h3 style='display:inline-block'>Rendszerben várakozó beutaló értesítések</h3>&nbsp;&nbsp;&nbsp;";
		//$html.="<h3 style='display:inline-block'>Kiküldött értesítések</h3>";
		
		$html.="<table class='transactions_table' style='border-collapse:collapse'>";
		$html.= "<tr><td>".$columnTitle."</td></tr>";
		$html.= $content;
		$html.="</table>";
		
		echo "<form action='index.php?page=referral' method='POST'>{$html}</form>";
		
		//FRONT END
	}
}

?>