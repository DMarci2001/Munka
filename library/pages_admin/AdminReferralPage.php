<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AdminReferralPage extends AdminCorePage {

    public function __construct()
    {
        parent::__construct();
		
		if (isset($_POST['deleteSelectedReferrals']) && $_GET['page']=="referral") {
			
			if (isset($_POST['selected-data']) && is_array($_POST['selected-data']) && count($_POST['selected-data'])>0) {
				
				foreach ($_POST['selected-data'] as $selector) {
					
					sql_query("DELETE FROM beutalo_ertesites WHERE id=?",array($selector));
				}
			}
		}
		
		if(isset($_POST['downloadTest'])){
			$mpdf = new \Mpdf\Mpdf();
			
			$result=sql_fetch_array(sql_query("SELECT * FROM beutalo_ertesites WHERE id=?",array(90)));
			
			$mpdf->imageVars['myvariable'] = file_get_contents("../images/Pecset_TG.jpg");
			$mpdf->WriteHTML($result['attachment']);
			
			//$mpdf->Image('../public/images/Pecset_TG.jpg', 100, -20, 29.36875, 15.875, 'jpg', '', true, true);
			
			
			//$mpdf->Image('../../public/images/Pecset_TG.jpg', 0, 0, 210, 297, 'jpg', '', true, false);
			
			$mpdf->Output("test.pdf","D");
			
			
			
			
			die();
		}
		if(isset($_POST['saveTest'])){
			
			$folder=dirname(__DIR__)."/other/tmp/";
			
			$mpdf = new \Mpdf\Mpdf();
			$result=sql_fetch_array(sql_query("SELECT * FROM beutalo_ertesites WHERE id=?",array(90)));
			
			$mpdf->imageVars['myvariable'] = file_get_contents("../images/Pecset_TG.jpg");
			$mpdf->WriteHTML($result['attachment']);
			
			//$mpdf->WriteHTML($file['output']);
			if(!file_exists("{$folder}{$result['id']}.pdf")){
				$mpdf->Output("{$folder}{$result['id']}.pdf","F");
			}
			
			if (!function_exists('set_magic_quotes_runtime')) {
				function set_magic_quotes_runtime($new_setting) {
					return true;
				}
			}
			
			if (isset($_POST['selected-data']) && is_array($_POST['selected-data']) && count($_POST['selected-data'])>0) {
				
				foreach ($_POST['selected-data'] as $selector) {
					
					$mpdf = new \Mpdf\Mpdf();
					$result=sql_fetch_array(sql_query("SELECT * FROM beutalo_ertesites WHERE id=?",array($selector)));
					
					$mpdf->imageVars['myvariable'] = file_get_contents("../images/Pecset_TG.jpg");
					$mpdf->WriteHTML($result['attachment']);
					
					//$mpdf->WriteHTML($file['output']);
					if(!file_exists("{$folder}{$result['id']}.pdf")){
						$mpdf->Output("{$folder}{$result['id']}.pdf","F");
					}
					
					if (!function_exists('set_magic_quotes_runtime')) {
						function set_magic_quotes_runtime($new_setting) {
							return true;
						}
					}
					$mail = new PHPMailer();
					$mail->From = Booking_Constants::NO_REPLY_ADDRESS;
					$mail->FromName = Booking_Constants::COMPANY_NAME;
					$mail->AddAddress("m.gergely9409@gmail.com");
					if (!empty(Booking_Constants::USER_BCC_MAIL)) {
						$mail->AddBCC(Booking_Constants::USER_BCC_MAIL);
					}
					$mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
					$mail->IsHTML(true);

					$t = iconv("UTF-8", "ISO-8859-2", "Teszt levél");

					$mbody = "";

					$mail->Subject = $t;
					$mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
					$mail->AddAttachment("{$folder}{$result['id']}.pdf");
					$mail->Send();
				}
			}
		}
		
	}
	
	public function showPage() {
		
		/*$filename= dirname(__DIR__)."/other/tmp";
		if (file_exists($filename)) {
			echo "The file $filename exists";
		} else {
			echo "The file $filename does not exist";
		}*/
		
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
			$content.= "<td align='center' onClick='toggleCheckBox(\"#{$result['rid']}\")'><input type='checkbox' onClick='toggleCheckBox(\"#{$result['rid']}\")' checked class='referral-checker' name='selected-data[]' id='{$result['rid']}' value='{$result['rid']}'/></td>";
			$content.="</tr>";
			$row++;
		}
		
		if(sql_num_rows($request)<1){
			$content.="<tr><td colspan='9' align='center'><p style='font-size:18px;font-weight:bold'> - Nincs kiküldésre várakozó adat - </p></td></tr>";
		}
							  
		//Találjuk ki, mit akarunk láátni :P Szükségünk van a pácienst beazonosító információkra, név, szül dátum, munkakör, cég, beutaló típus ami ki fogmenni(ezt eddig le se rögzítettem bakker)
		//Az aktuális értesítésről szóló lejárati idő, és a dokumenum maga (báár itt ezt nem biztos hogy megakarom jeleníteni.)
		
		$html.="<h3>Műveletek</h3>";
		if($_POST['data-list']=="waiting"){
			$html.= "<input type='submit' href='#' value='Kiválaszott adatsorok törlése' name='deleteSelectedReferrals' />&nbsp;&nbsp;";
			$html.= "<input type='submit' href='#' value='Kiválaszott adatsorok kiküldése értésítésre' name='sendSelectedReferrals' />&nbsp;&nbsp;";
			$html.= "<input type='submit' href='#' value='Letöltés Teszt' name='downloadTest' />";
			$html.= "<input type='submit' href='#' value='Mentés Teszt' name='saveTest' />";
			
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