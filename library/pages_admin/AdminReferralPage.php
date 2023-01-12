<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AdminReferralPage extends AdminCorePage {

    public function __construct()
    {
        parent::__construct();

        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        if(!$this->adminUser->beutaloAccess()) header("Location:index.php");
		
		if (isset($_POST['deleteSelectedReferrals']) && $_GET['page']=="referral") {
			
			if (isset($_POST['selected-data']) && is_array($_POST['selected-data']) && count($_POST['selected-data'])>0) {
				
				foreach ($_POST['selected-data'] as $selector) {
					
					sql_query("DELETE FROM beutalo_ertesites WHERE id=?",array($selector));
				}
			}
		}
		
		if (isset($_POST['download-referral'])) {
			
			if (isset($_POST['selected-data']) && is_array($_POST['selected-data']) && count($_POST['selected-data'])>0) {
				if ( count( $_POST['selected-data'] ) > 1 ) {
					die("Csak egy fájlt jelölj ki a tesztre!");
				}
				else{
					
					$result=sql_fetch_array(sql_query("SELECT * FROM beutalo_ertesites WHERE id=?",array($_POST['selected-data'][0])));
					$removeCharSheet  = array("á","é","ú","ó","ű","ü","ö","ő","í"," ");
					$replaceCharSheet = array("a","e","u","o","u","u","o","o","i","_");
					$filename = strtolower(str_replace($removeCharSheet,$replaceCharSheet,$result["nev"]));
					$filename.= "-".str_replace("-","",$result["szuldatum"]);
					$setDate = date("Ymdhis");
					$setDate = date("Ymdhis",strtotime($setDate." + 1 second"));
					$filename.= "-".date("Ymdhis",strtotime($setDate)).".pdf";
					$mpdf = new \Mpdf\Mpdf();
					$mpdf->imageVars['myvariable'] = file_get_contents("../images/Pecset_TG.jpg");
					$mpdf->WriteHTML($result['attachment']);
					$mpdf->Output($filename,"D");
				}
			}
			
			
			die();
		}
		
		if(isset($_POST['saveTest']) || isset($_POST["sendSelectedReferrals"])){
			
			//2 listát vagy tömböt kell generálnom, az első
			//Először is segítségnek ki kell szednem az összes vezető email címet, és generálnom kell szá
			
			//Ha létezik,tömb és nem üres akkor mennyen bele
			if (isset($_POST['selected-data']) && is_array($_POST['selected-data']) && count($_POST['selected-data'])>0) {
				
				
				$folder = "/var/www/onlinebejelentkezes_keltexmed/library/other/tmp/";

				$overallExcel = date("Y.m.d")."-fogleu-lista(Teljes)";
				$objPHPExcel = new Spreadsheet();
				$objPHPExcel->setActiveSheetIndex(0);
				$objPHPExcel->getActiveSheet()->setTitle('Állomány');
				
				header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
				header('Content-Disposition: attachment;filename="'.$overallExcel.'.xlsx"');
				header('Cache-Control: max-age=0');
				
				//Tartalom hozzáadása:
				
				//Oszlop nevek:
				$objPHPExcel->getActiveSheet()->SetCellValue('A1', "Név");
				$objPHPExcel->getActiveSheet()->SetCellValue('B1', "Szül. dátum");
				$objPHPExcel->getActiveSheet()->SetCellValue('C1', "TAJ");
				$objPHPExcel->getActiveSheet()->SetCellValue('D1', "Munkakör");
				$objPHPExcel->getActiveSheet()->SetCellValue('E1', "Vizsgálat dátuma");
				$objPHPExcel->getActiveSheet()->SetCellValue('F1', "Fenn maradt idő(napok)");
				$row=1;
				
				//Végig futok az összes kiválasztott lista elemen
				foreach ($_POST['selected-data'] as $selector) {
					//Le kérdezem egyesével az elemeket a listát pedig a megfelelő vezető tömbjébe teszem.
					$result=sql_fetch_array(sql_query("SELECT felh.nev,felh.taj,felh.szuldatum,felh.munkakor,
															  beu.id,beu.email,beu.expiration,beu.sup_email,beu.attachment,beu.working_status,beu.text,beu.subject
													   FROM beutalo_ertesites beu
													   LEFT JOIN felhasznalok_teszt felh ON felh.id=beu.fid
													   WHERE beu.id=?",array($selector)));
					
					$removeCharSheet  = array("á","é","ú","ó","ű","ü","ö","ő","í"," ","-");
					$replaceCharSheet = array("a","e","u","o","u","u","o","o","i","_","_");
					$filename = strtolower(str_replace($removeCharSheet,$replaceCharSheet,$result["nev"]));
					$filename.= "-".str_replace("-","",$result["szuldatum"]);
					$setDate = date("Ymdhis");
					$setDate = date("Ymdhis",strtotime($setDate." + 1 second"));
					$filename.= "-".date("Ymdhis",strtotime($setDate));
					$attachment = "{$folder}{$filename}.pdf";
					$mpdf = new \Mpdf\Mpdf();
					$mpdf->imageVars['myvariable'] = file_get_contents("../images/Pecset_TG.jpg");
					$mpdf->WriteHTML($result['attachment']);
					if (!file_exists($attachment)) {
						$mpdf->Output($attachment,"F");
					}
					
					$result['attachment']=$attachment;
					
					
					
					//Email(ek) készítése:
					$mail = NotificationService::getDefaultMailer();
					if ( isset( $_POST['saveTest'] )) {
						$mail->AddAddress( "tesztemail@hungariamed.hu" ); 
					} else {
						$mail->AddAddress( $result["email"] ); 
					}
					 if (!empty(Booking_Constants::USER_BCC_MAIL)) {
						$mail->AddBCC(Booking_Constants::USER_BCC_MAIL);
					}

					$mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
					$mail->IsHTML(true);
					$mail->isSMTP();
					$mail->Host = "mail.hungariamed.hu";
					$mail->SMTPAuth = true;
					$mail->Username = "web@hungariamed.hu";
					$mail->Password = "The9vae1";
					$mail->SMTPSecure = "tls";
					$mail->Port = 366;
					$mail->charset="utf-8";

					$t = "Hamarosan lejár az alkalmassági igazolása!";

					$mbody = "<h1 style='font-family:calibri'>Tisztelt Munkavállaló!</h1>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'>Ezúton értesítjük, hogy munka alkalmassági igazolásának érvényessége <strong>".date("Y.m.d",strtotime($result["expiration"]))." dátummal lejár</strong>. Kérjük, hogy az Ön telephelyére kijelölt üzemorvosnál jelentkezzen be az éves vizsgálat elvégzésére!</p>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'>Az alkalmassági eredményt kérjük <strong>Kiss Renáta Réka részére elküldeni</strong> a <a href=\"mailto:kiss.renata.reka@tigaz.hu\" style=\"color:#a90000\">kiss.renata.reka@tigaz.hu</a> e-mail címre.</p>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'>Bármely felmerülő kérdéssel kapcsolatban ügyfélkapcsolati munkatársunk áll szolgálatára.</p>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'><b>Telefonos ügyfélszolgálat:</b><br>";
					$mbody.= "<i>Munkanapokon 08:00 –tol 16:00-ig.</i><br>";
					$mbody.= " +36 1 / 800 9333<br>";
					$mbody.= "+36 30 / 633 0961<br>";
					$mbody.= "</p>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'><b>E-mail:</b><br>ugyfelkapcsolat@hungariamed.hu</p>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'>Üdvözlettel</p>";
					$mbody.= "<a href='https://www.hungariamed.hu' target='_blank'><img src='https://hungariamed.hu/images/logo.png'/></a>";
					$mail->Subject = $t;
					$mail->Body = $mbody;
					$mail->AddAttachment($attachment);
					$mail->Send();
					//Itt helyezem be a vezetői tömbbe az adatokat a páciensről, és a csatolmány elérési útjával felül írom az forráskódját
					if($result['sup_email']!=""){
						$superiors[$result['sup_email']][] = $result;
					}
					else unlink($attachment);
					
					//Rögzítem az adatbázisban a kiküldés idejét
					if(isset($_POST["saveTest"])){
						sql_query("UPDATE beutalo_ertesites SET test_sent=NOW() WHERE id=?",array($result["id"]));
					}
					if(isset($_POST["sendSelectedReferrals"])){
						sql_query("UPDATE beutalo_ertesites SET sent=NOW() WHERE id=?",array($result["id"]));
					}
					
					
					//Adatok rögzítése az összesítő excelbe:
					$row++;
					$objPHPExcel->getActiveSheet()->SetCellValue("A{$row}", $result["nev"]);
					$objPHPExcel->getActiveSheet()->SetCellValue("B{$row}", $result["szuldatum"]);
					$objPHPExcel->getActiveSheet()->SetCellValue("C{$row}", $result["taj"]);
					$objPHPExcel->getActiveSheet()->SetCellValue("D{$row}", $result["munkakor"]);
					$objPHPExcel->getActiveSheet()->SetCellValue("E{$row}", $result["expiration"]);
					$objPHPExcel->getActiveSheet()->SetCellValue("F{$row}", round((time()-strtotime($result["expiration"])) / ((60 * 60 * 24)*-1))." nap");
				}

				//összesítő excel fájl véglegesítése:
				$objWriter = IOFactory::createWriter($objPHPExcel, 'Xlsx');
				$objWriter->save($folder.$overallExcel.".xlsx");
				
				
				//Email(ek) készítése:
				$mail = new PHPMailer\PHPMailer\PHPMailer();
				$mail = NotificationService::getDefaultMailer();
				$mail->From = Booking_Constants::NO_REPLY_ADDRESS;
				$mail->FromName = Booking_Constants::COMPANY_NAME;
				if (isset($_POST['saveTest'])) {
					$mail->AddAddress("tesztemail@hungariamed.hu");
				} else {
					$mail->AddAddress("kiss.renata.reka@opustigaz.hu");
				}
			
				if (!empty(Booking_Constants::USER_BCC_MAIL)) {
					$mail->AddBCC(Booking_Constants::USER_BCC_MAIL);
				}
				$mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
				$mail->IsHTML(true);
				$mail->isSMTP();
				$mail->Host = "mail.hungariamed.hu";
				$mail->SMTPAuth = true;
				$mail->Username = "web@hungariamed.hu";
				$mail->Password = "The9vae1";
				$mail->SMTPSecure = "tls";
				$mail->Port = 366;
				$mail->charset="utf-8";
			
				$t = "Összesítő lista az értesítésekről - " . date("Y.m.d");

				$mbody = "<h1 style='font-family:calibri'>Tisztelt Címzett!</h1>";
				$mbody.= "<p style='font-family:calibri;font-size:14px'>A csatolmány tartalmazza a kiértesített dolgozókat a ".date("Y.m.d")." dátumon elvégzett csoportos értesítésen.</p>";
				$mbody.= "<p style='font-family:calibri;font-size:14px'>Ez a levél automatikusan lett generálva.</p>";
				$mbody.= "<p style='font-family:calibri;;font-size:14px'>Üdvözlettel</p>";
				$mbody.= "<img src='https://hungariamed.hu/images/logo.png'>";
				
				$mail->Subject = $t;
				$mail->Body = $mbody;
				$mail->AddAttachment($folder.$overallExcel.".xlsx");
				$mail->Send();
				
				
	
				
				
				foreach($superiors as $name=>$data){
					
					//Ki kell találnom valami normális lista megnevezést xd....
					//és hozzá kell adnom a pdf-eket + tömörítenem is kell őket...
					$files = array();
					$filename = date("Y.m.d")."-fogleü.-lista";
					$objPHPExcel = new Spreadsheet();
					$objPHPExcel->setActiveSheetIndex(0);
					$objPHPExcel->getActiveSheet()->setTitle('Állomány');
					
					header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
					header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
					header('Cache-Control: max-age=0');
					
					//Tartalom hozzáadása:
					
					//Oszlop nevek:
					$objPHPExcel->getActiveSheet()->SetCellValue('A1', "Név");
					$objPHPExcel->getActiveSheet()->SetCellValue('B1', "Szül. dátum");
					$objPHPExcel->getActiveSheet()->SetCellValue('C1', "TAJ");
					$objPHPExcel->getActiveSheet()->SetCellValue('D1', "Munkakör");
					$objPHPExcel->getActiveSheet()->SetCellValue('E1', "Vizsgálat dátuma");
					$objPHPExcel->getActiveSheet()->SetCellValue('F1', "Fenn maradt idő(napok)");
					
					$row=1;
					foreach($data as $each){
						$row++;
						$objPHPExcel->getActiveSheet()->SetCellValue("A{$row}", $each["nev"]);
						$objPHPExcel->getActiveSheet()->SetCellValue("B{$row}", $each["szuldatum"]);
						$objPHPExcel->getActiveSheet()->SetCellValue("C{$row}", $each["taj"]);
						$objPHPExcel->getActiveSheet()->SetCellValue("D{$row}", $each["munkakor"]);
						$objPHPExcel->getActiveSheet()->SetCellValue("E{$row}", $each["expiration"]);
						$objPHPExcel->getActiveSheet()->SetCellValue("F{$row}", round((time()-strtotime($each["expiration"])) / ((60 * 60 * 24)*-1))." nap");
						
						
						$files[]=$each["attachment"];
					}
					
					$zipPath=$folder."Csatolmany_".date("Ymd")."-{$name}.zip";
					
					
					
					//Fájl véglegesítése:
					$objWriter = IOFactory::createWriter($objPHPExcel, 'Xlsx');
					$objWriter->save($folder.$filename.".xlsx");
					$files[]=$folder.$filename.".xlsx";

					exec("zip -j {$zipPath} ".implode(" ",$files));
					//$this->utils->create_zip($files, $zipPath,  null);
					
					//Email(ek) készítése:
					$mail = NotificationService::getDefaultMailer();
					if ( isset( $_POST['saveTest'] )) {
						$mail->AddAddress( "tesztemail@hungariamed.hu" ); 
					} else {
						$mail->AddAddress( $name ); 
					}
					 if (!empty(Booking_Constants::USER_BCC_MAIL)) {
						$mail->AddBCC(Booking_Constants::USER_BCC_MAIL);
					}

					$mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
					$mail->IsHTML(true);
					$mail->isSMTP();
					$mail->Host = "mail.hungariamed.hu";
					$mail->SMTPAuth = true;
					$mail->Username = "web@hungariamed.hu";
					$mail->Password = "The9vae1";
					$mail->SMTPSecure = "tls";
					$mail->Port = 366;

					$t = "Aktuális alkalmassági lejáratok - " . date("Y.m.d");

					
					$mbody = "<h1 style='font-family:calibri'>Tisztelt Címzett!</h1>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'>A csatolmányban található lista tartalmazza a hamarosan lejáratos Foglalkozás-egészségügyi vizsgálattal rendelkező Fizikai munkásokat és a részükre kiállított beutalót.</p>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'>Bármely felmerülő kérdéssel kapcsolatban ügyfélkapcsolati munkatársunk áll szolgálatára.</p>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'><b>Telefonos ügyfélszolgálat:</b><br>";
					$mbody.= "<i>Munkanapokon 08:00 –tol 16:00-ig.</i><br>";
					$mbody.= " +36 1 / 800 9333<br>";
					$mbody.= "+36 30 / 633 0961<br>";
					$mbody.= "</p>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'><b>E-mail:</b><br>ugyfelkapcsolat@hungariamed.hu</p>";
					$mbody.= "<p style='font-family:calibri;font-size:14px'>Üdvözlettel</p>";
					$mbody.= "<a href='https://www.hungariamed.hu' target='_blank'><img src='https://hungariamed.hu/images/logo.png'/></a>";

					$mail->Subject = $t;
					$mail->Body = $mbody;
					$mail->AddAttachment($zipPath);
					$mail->Send();
					foreach($files as $file) unlink($file);
					unlink($zipPath);
				}

			}
			
			die();
		}
		
	}
	
	public function showPage() {
		
		/*$filename= dirname(__DIR__)."/other/tmp";
		if (file_exists($filename)) {
			echo "The file $filename exists";
		} else {
			echo "The file $filename does not exist";
		}*/

		//if(!$this->adminUser->beutalomenupontAccess()) header("Location:index.php");

		
		
		//BACK-END
		$html=$columnTitle=$content="";
		$row=0;
		
		$tdCSS="style='padding: 8px 8px 8px 0px;border-bottom:1px solid gray'";
		
		if(!isset($_POST['data-list'])) $_POST['data-list']="waiting";
		
		//$columns=array("Azon.","Dátum","Név","Telefon","E-mail","Vizsgálat","Orvos","Összeg","Eredmény");
		
		
		if($_POST['data-list']=="waiting"){
			
			$columns=array("#.","Teljesnév","Szül. dátum","Munkakör","Cég","Beutaló típus","Munkavégzés típusa","Felettesi e-mail cím","Alkalmassági lejárat","Kiállítva","<span id='checkBoxSwitcher' onClick='switchCheckBoxes(\"referral-checker\",\"disable\")' style='color:red;cursor:pointer'>Egyikse</span>");
			$columnTitle= implode("</td><td {$tdCSS}>",$columns);
			
			$request = sql_query("SELECT felh.*,ref.*,c.megnev,ref.id AS rid,beu.megnev AS minta FROM beutalo_ertesites ref
								  LEFT JOIN felhasznalok_teszt felh ON felh.id=ref.fid
								  LEFT JOIN cegek c ON c.id=felh.cegid
								  LEFT JOIN beutalo_formak beu ON beu.id=ref.template
								  WHERE ref.sent is NULL OR ref.sent='' AND ref.subject='Beutaló' 
								  ORDER BY ref.timestamp");
								  
			while($result=sql_fetch_array($request)){
				$content.="<tr>";
				$content.= "<td {$tdCSS}>#".($row+1)."</td>";
				$content.= "<td {$tdCSS}>{$result['nev']}</td>";
				$content.= "<td {$tdCSS}>{$result['szuldatum']}</td>";
				$content.= "<td {$tdCSS}>{$result['munkakor']}</td>";
				$content.= "<td {$tdCSS}>{$result['megnev']}</td>";
				$content.= "<td {$tdCSS}>{$result['minta']}</td>";
				$content.= "<td {$tdCSS}>{$result['working_status']}</td>";
				$content.= "<td {$tdCSS}>{$result['sup_email']}</td>";
				$content.= "<td {$tdCSS}>{$result['expiration']}</td>";
				$content.= "<td {$tdCSS}>{$result['timestamp']}</td>";
				$content.= "<td {$tdCSS} align='center' onClick='toggleCheckBox(\"#{$result['rid']}\")'><input type='checkbox' onClick='toggleCheckBox(\"#{$result['rid']}\")' checked class='referral-checker' name='selected-data[]' id='{$result['rid']}' value='{$result['rid']}'/></td>";
				$content.="</tr>";
				$row++;
			}
			
			if(sql_num_rows($request)<1){
				$content.="<tr><td colspan='11' align='center'><p style='font-size:18px;font-weight:bold'> - Nincs kiküldésre várakozó adat - </p></td></tr>";
			}
			
			$html.="<h3>Műveletek</h3>";
			$html.= "<input type='submit' href='#' onclick='return confirm(\"Biztos törlöd a kijelölt sorokat?\")' value='Kiválaszott adatsorok törlése' name='deleteSelectedReferrals' />&nbsp;&nbsp;";
			$html.= "<input type='submit' href='#' onclick='return confirm(\"Biztos ki akarod küldeni a kijelölt sorokat?\")' value='Kiválaszott adatsorok kiküldése értésítésre' name='sendSelectedReferrals' />&nbsp;&nbsp;";
			$html.= "<input type='submit' href='#' value='Kijelölt beutaló letöltése' name='download-referral' />&nbsp;&nbsp;";
			$html.= "<input type='submit' href='#' value='Teszt küldés' name='saveTest' />";
			
		}
		
		if($_POST['data-list']=="sent"){
			
			$columns=array("#.","Teljesnév","Szül. dátum","Munkakör","Cég","Beutaló típus","Munkavégzés típusa","Felettesi e-mail cím","Alkalmassági lejárat","Kiállítva","Értesítve","<span id='checkBoxSwitcher' onClick='switchCheckBoxes(\"referral-checker\",\"disable\")' style='color:red;cursor:pointer'>Egyikse</span>");
			$columnTitle= implode("</td><td {$tdCSS}>",$columns);
			
			$request = sql_query("SELECT felh.*,ref.*,c.megnev,ref.id AS rid,beu.megnev AS minta FROM beutalo_ertesites ref
								  LEFT JOIN felhasznalok_teszt felh ON felh.id=ref.fid
								  LEFT JOIN cegek c ON c.id=felh.cegid
								  LEFT JOIN beutalo_formak beu ON beu.id=ref.template
								  WHERE ref.sent IS NOT NULL OR ref.sent<>'' AND ref.subject='Beutaló' 
								  ORDER BY ref.timestamp");
			
								  
			while($result=sql_fetch_array($request)){
				$content.="<tr>";
				$content.= "<td {$tdCSS}>#".($row+1)."</td>";
				$content.= "<td {$tdCSS}>{$result['nev']}</td>";
				$content.= "<td {$tdCSS}>{$result['szuldatum']}</td>";
				$content.= "<td {$tdCSS}>{$result['munkakor']}</td>";
				$content.= "<td {$tdCSS}>{$result['megnev']}</td>";
				$content.= "<td {$tdCSS}>{$result['minta']}</td>";
				$content.= "<td {$tdCSS}>{$result['working_status']}</td>";
				$content.= "<td {$tdCSS}>{$result['sup_email']}</td>";
				$content.= "<td {$tdCSS}>{$result['expiration']}</td>";
				$content.= "<td {$tdCSS}>{$result['timestamp']}</td>";
				$content.= "<td {$tdCSS}>{$result['sent']}</td>";
				$content.= "<td {$tdCSS} align='center' onClick='toggleCheckBox(\"#{$result['rid']}\")'><input type='checkbox' onClick='toggleCheckBox(\"#{$result['rid']}\")' checked class='referral-checker' name='selected-data[]' id='{$result['rid']}' value='{$result['rid']}'/></td>";
				$content.="</tr>";
				$row++;
			}
			
			if(sql_num_rows($request)<1){
				$content.="<tr><td colspan='9' align='center'><p style='font-size:18px;font-weight:bold'> - Nincs kiküldésre várakozó adat - </p></td></tr>";
			}
			$html.= "<h3>Műveletek</h3>";
			$html.= "<input type='submit' href='#' value='Kijelölt beutaló letöltése' name='download-referral' />&nbsp;&nbsp;";
		}
							  
		
							  
		//Találjuk ki, mit akarunk láátni :P Szükségünk van a pácienst beazonosító információkra, név, szül dátum, munkakör, cég, beutaló típus ami ki fogmenni(ezt eddig le se rögzítettem bakker)
		//Az aktuális értesítésről szóló lejárati idő, és a dokumenum maga (báár itt ezt nem biztos hogy megakarom jeleníteni.)
		
		
		$html.="<br><br>";
		
		//Itt akarok törlést kiküldést beépíteni, először kezdjük a törléssel és a ki jelölésen alapuló törlésen
		$html.="<select onChange='$(this).closest(\"form\").submit();' name='data-list' class='design-put' style='width:auto'>";
		$html.="	<option ".($_POST['data-list']=="waiting"?"selected":"")." value='waiting'>Rendszerben várakozó beutaló értesítése</option>";
		$html.="	<option ".($_POST['data-list']=="sent"?"selected":"")." value='sent'>Kiküldött értesítések</option>";
		$html.="</select>";
		
		//$html.="<h3 style='display:inline-block'>Rendszerben várakozó beutaló értesítések</h3>&nbsp;&nbsp;&nbsp;";
		//$html.="<h3 style='display:inline-block'>Kiküldött értesítések</h3>";
		
		$html.="<table class='transactions_table' style='border-collapse:collapse'>";
		$html.= "<tr><td {$tdCSS}>".$columnTitle."</td></tr>";
		$html.= $content;
		$html.="</table>";
		
		echo "<form action='index.php?page=referral' method='POST'>{$html}</form>";
		
		//FRONT END
	}
}

?>