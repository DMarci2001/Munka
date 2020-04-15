<?php
class RemoteBookingPage extends CorePage{

    private $arData;
    private $szuresData;
	private $bookingPage;

	public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;
		if($_SESSION['helyszindata']['id']!=11) $_SESSION['helyszindata']['id']=11;
        $bookingService = new BookingService();
		$this->bookingPage = new BookingPage();
		
		
		if(isset($_GET['szurestipus'])) $_POST['szurestipus']=$_GET['szurestipus'];
		if(!isset($_POST['szurestipus'])) $_POST['szurestipus']=0;
        $this->arData = sql_fetch_array(sql_query("SELECT * FROM arak WHERE tipusid=? AND cegid LIKE '%|{$_SESSION['helyszindata']['id']}|%' ", [$_POST['szurestipus']]));
        $this->szuresData = sql_fetch_array(sql_query("SELECT * FROM szurestipusok WHERE id=?",array($_POST['szurestipus'])));
		
		$this->szuresData['noreservation'] = 0;

        if(isset($_POST['saveForm'])){
			
			
			//Végig kell futni a szurestipus parameterein, abban megtalálunk minden szükséges információt ami kellhet.
			if (isset($_POST["szuldatumev"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"]."-".substr("00".$_POST["szuldatumho"],-2)."-".substr("00".$_POST["szuldatumnap"],-2);
            }
			
			
			
			//Orvos választás:
			$_POST['orvosid']=$_POST['selectorvos'];
			
			$q=sql_fetch_array(sql_query("SELECT o.questions,beo.noreservation FROM orvosok o 
										  LEFT JOIN orvos_beosztas beo ON beo.orvosid=o.id
										  WHERE o.id=? AND beo.tipusok LIKE '%{$_POST['szurestipus']}%' GROUP BY beo.orvosid ORDER BY o.nev limit 1",array($_POST['orvosid'])));
										  
			if($q['noreservation']==1){
				 $_POST["datum"] = date("Y-m-d H:i:s");
			}
			
			//Egyéni mezők ellenőrzése:
			$custominputs = explode(",",$this->szuresData['custominputs']);
			foreach($custominputs as $input){
				$actualInput = explode("_",$input);
				if($actualInput[0]=="hidden") continue;
				if (!isset($_POST[$actualInput[1]])) $_POST[$actualInput[1]] = "";
				if($_POST[$actualInput[1]]==""){
					$this->errors[] = $webText[$actualInput[1]."kotelezo"];
				}
			}
			//Speciális mezők ellenőrzése:
			if($this->szuresData['noreservation']==0){
				if($_POST['datum']=="") $this->errors[] = "Kérem válasszon egy időpontot!";
			}
			if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) $this->errors[] = $webText["hibasemail"];
			if ($_POST['szuldatum']=="0-00-00") $this->errors[] = "A születési dátum megadása kötelező!";
			if (!$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) $this->errors[] = $webText["szulformat"];
			if (!isset($_POST["aszf"])) $this->errors[] = $webText["aszfkotelezo"];
			if (!isset($_POST["simplepay"])) $this->errors[] = "A simplepay felhasználási feltételeit a vásárláshoz el kell elfogadnia!";
			if ($_POST['orvosid']==0) $this->errors[] = "Válasszon ellátó szakorvost!";
			

			$captchaError = $this->utils->checkCaptcha();
            if (!empty($captchaError)) {
                $this->errors[] = $captchaError;
            }

			//Kérdések ellenőrzése:
			$questionArr=json_decode($q['questions'],true);
			$questions = "";
			$sor=0;
			do{
				if(empty($_POST["kerdes-{$sor}"])) $this->errors[] = "Kérem, válaszoljon a ".($sor+1).". kérdésre!";
				if(strlen($_POST["kerdes-{$sor}"])>3000) $this->errors[] = "A ".($sor+1).". kérdés válasza maximum 3000 karakter hosszú lehet!";
				$questions.="<p>{$questionArr[$sor]['question']}</p><p>".$_POST["kerdes-{$sor}"]."</p><br>";
				$sor++;
			}while(isset($_POST["kerdes-{$sor}"]));
			
			//Egyéb mezők hozzáadása a queryhez:
			$_POST['helyszinid'] = 1;
			$_POST["questions"] = $questions;
			$_POST["simplepay"] = 1;
			$_POST["noreservation"] = 1;
			
           
            $_POST["totalprice"] = $this->arData["price"];
            $_POST["currency"] = $this->arData["penznem"];

			if (empty($this->errors)) {
                $forwardURL = $bookingService->addReservation($_POST);
                header("location:{$forwardURL}");
				die();
			}
		}
		if(isset($_POST['setQuestions']) && $_POST['setQuestions']==true && isset($_POST['szurestipus']) && isset($_POST['orvosid'])){	
			header('Content-Type: application/json');
			
				$q=sql_fetch_array(sql_query("SELECT o.questions,beo.noreservation FROM orvosok o 
											  LEFT JOIN orvos_beosztas beo ON beo.orvosid=o.id
											  WHERE o.id=? AND beo.tipusok LIKE '%{$_POST['szurestipus']}%' GROUP BY beo.orvosid ORDER BY o.nev limit 1",array($_POST['orvosid'])));
			
			$questionArr=json_decode($q['questions'],true);
			
			die(json_encode(array("questions"=>$this->setQuestions($questionArr,$_POST['szurestipus']),"reservationstatus"=>$q['noreservation'],"bookingselector"=>$this->_reservationTimeSelector($_POST['orvosid'],$_POST['szurestipus'])),JSON_UNESCAPED_UNICODE));
		}
    }

	public function showPage() {
		//BACK - END
		$webText = $this->lang->webText;
		
		echo $this->displayFejlec($this->szuresData['megnev'],true);
		
		if(isset($_COOKIE['lang']) && $_COOKIE['lang']!="hu"){
			echo "This medical service only available on hungarian language for more details please contact us (ugyfelszolgalat@hungariamed.hu)<br>";
			echo "<a href='https://bejelentkezes.hungariamed.hu'>Return to the mainpage</a>";
			return;
		}
		
		if($_POST['szurestipus']==0) header("Location:index.php");
		
		if(isset($_SESSION['helyszindata']['id'])) $_SESSION['helyszindata']['id'];
       
		
		
		$inputs=$html="";
		$actualInput=$inputArr=array();
		//Fogadni kell a továbbított adatot
		//kiválaszott vizsgálat: $_POST['szurestipus'];
		
		//minek kellene itt szerepelnie?
		
		//-A szükséges adatmezőknek!
		//--Ezeket az adminban a szurestipusnál lehet megadni, szval célszerű minden adatot meghívni a vizsgálatról a back-end részben hamar :P
		
		//--Ezután a kérdez/felelek modul jön, amit szint úgy a vizsgálat mögé rakok egy text mezőben. még a formulát ki kell találni!
		//--Kelleni fognak leíró szöveg modulok, amik további információkat nyújtanak a vizsgálattal kapcsolatban.
		//A form jóváhagyó gomb definiálása is szükséges lépés lesz, ezt már a Jani fogja intézni, a simplepay-el fogja párhuzamosítani.

		//Megjelenítendő mezők:
		$custominputs = explode(",",$this->szuresData['custominputs']);
		
		
		//Szabadon választott törzsadatok beillesztése:
		foreach($custominputs as $input){
			$actualInput = explode("_",$input);
			if($actualInput[0]=="hidden") continue;
			if (!isset($_POST[$actualInput[1]])) $_POST[$actualInput[1]] = "";
			array_push($inputArr,array("requirment"=>$actualInput[0],"name"=>$actualInput[1]));
		}
		
		//Kérdez/felelek opciók beillesztése:
		$oq=sql_fetch_array(sql_query("SELECT o.questions,o.id AS orvosid,beo.noreservation FROM orvos_beosztas beo
									   LEFT JOIN orvosok o ON o.id=beo.orvosid
									   WHERE tipusok LIKE '%{$_POST['szurestipus']}%' ".(isset($_POST['orvosid'])?"AND o.id={$_POST['orvosid']}":"")." GROUP BY beo.orvosid ORDER BY o.nev limit 1"));
		$questionArr=json_decode($oq['questions'],true);
		
		
		
		
		
		//FRONT-END
		
		
		echo $this->showErrors();
		
		$html.= "<form method='POST' id='remoteForm' enctype='multipart/form-data'>";
		
		//Páciens adatok:
		
		$html.= "<h2>Szükséges adatok</h2>";
		$html.= "<table>";
		$html.=		$this->setOrvosList($_POST['szurestipus']);
		//if($oq[''])
		//Ha az orvos beojában a noreservation==1 akkor az időpontfoglalást ne jelenítse meg.
		$html.= "<tr id='idopontvalasztotr'>";
		if ($oq['noreservation']==0) {
			$html.= "	<td>Időpont:* </td><td id='idopontvalasztotd'>".$this->_reservationTimeSelector($oq['orvosid'],$_POST['szurestipus'])."<td>";
		}
		$html.= "</tr>";
		$html.= 	$this->setFields($inputArr);
		$html.= "</table>";
		
		//Kérdez/Felelek:
		$html.= "<h2>Kérdések</h2>";
		$html.= "<table style='width:100%;font-family:robotoregular,font-size:14px' id='questions'>";
		$html.= 	$this->setQuestions($questionArr,$_POST['szurestipus']);
		$html.= "</table>";
		
		//Képfeltöltés:
		$html.= "<table><tr><td>";
        $html.= "<div style='font-size:16px'>Kérem, csatoljon 2-4 jó minőségű fényképet a tünetektől.</div>";
        $html.= "<div class='upload-btn-wrapper'><a href='#' class='upbtn newbutton'>{$webText["dokumentumfeltoltese"]}</a><input type='file' id='paciensfile' name='paciensfile[]' multiple /></div><img id='paciensloader' style='display:none;opacity:.5;height:30px;margin-left:10px;' src='/images/loading.svg' />";
        $html.= "</td></tr>";
        $html.= "<tr><td><div id='paciensfilediv'>".$this->utils->showPaciensFiles()."</div></td></tr>";
		
		//Captcha/ASZF:
		$html.= "<tr><td style='height:30px'></td></tr>";
		$html.= "<tr><td><div class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
        $html.= "<tr><td><div style='margin-top:10px;'><input type='checkbox' name='aszf' value='1' ".(isset($_POST["aszf"])?"checked":"")."/> {$webText["aszffizetos"]}</div></td></tr>";
		$html.= "<tr><td><div style='margin-top:10px;'><input type='checkbox' name='simplepay' value='1' /> <a style='' href='http://simplepartner.hu/PaymentService/Fizetesi_tajekoztato.pdf' target='_blank'>Elfogadom</a> a SimplePay feltételeit.</div></td></tr>";
		//Jóváhagyó gombok helye:
		
		//Itt több opciónak is meg kell majd jelennie a vizsgálat beállításainak megfelelően:
		$html.= "<input type='hidden' name='szurestipus' value='{$_POST['szurestipus']}'/>";
		$html.= "<tr><td align='center'><div style='margin-top:20px;'><input type='submit' style='border:none' class='newbutton' name='saveForm' value='Fizetek (".$this->arData['price']." ".$this->arData['penznem'].")'/><div></td></tr>";
		$html.= "<tr><td align='center'><a href='http://simplepartner.hu/PaymentService/Fizetesi_tajekoztato.pdf' target='_blank'><img src='images/simplepay_bankcard_logos_left.jpg' style='max-width:40%;width:auto'></a></td></tr>";
		
		$html.= "</table>";
		$html.= "</form>";
		
		echo $html;
		
	}
	
	public function setFields($inputs){
		$webText = $this->lang->webText;
		$output = "";
		foreach($inputs as $input){
			//inputok beillesztése:
			$inputTag = "<input class='design-put' style='width:260px' type='text' name='{$input['name']}' value='{$_POST[$input['name']]}' />";
			if($input['name']=='upload') $inputTag = "<input class='inputbox' style='width:260px' type='file' name='{$input['name']}' />";
			if($input['name']=='neme') $inputTag = "<input type='radio' name='neme' value='1' ".($_POST["neme"]==1?"checked":"")."/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_POST["neme"]==2?"checked":"")."/> {$webText["no"]}";
			if($input['name']=='szuldatum') $inputTag = $this->utils->datumSelector($_POST["szuldatum"],"szuldatum",0,"class='design-put' style='padding:3px;width:auto'"); 
			//Kötelező/nem kötelező:
			if($input['requirment']=="req") $priority = "*";
			if($input['requirment']=="notreq") $priority = "";
			
			//output form mező:
			$output.="<tr><td>{$webText[$input['name']]}: {$priority}</td><td>{$inputTag}</td></tr>";
			
			//Segéd szövegek:
			//if($input['name']=="email") $output.="<tr><td></td><td>{$webText["kerjukugyeljenemail"]}</td></tr>";
		}
		return $output;
	}
	
	public function setQuestions($questionArr,$szurestipus){
		
		//BACK-END:
		$html="";
		$sor=0;
		
		foreach($questionArr as $each){
			if($each['servicetype']==$szurestipus){
				$html.="<tr><td><strong>".($sor+1).".</strong>&nbsp;{$each['question']}</td></tr>";
				$html.="<tr><td><textarea name='kerdes-{$sor}' class='design-put' style='width:95%;height:150px'>".(isset($_POST["kerdes-{$sor}"])?$_POST["kerdes-{$sor}"]:"")."</textarea></td></tr>";
				$html.="<tr><td style='height:25px'></td></tr>";
				$sor++;
			}
		}
		
		return $html;
	}
	
	public function setOrvosList($szurestipus){
		
		//BACK-END:
		//Orvosok kilistázása, akik ellátnak ilyen típusú vizsgálatot:
		$options=$html="";
		$request=sql_query("SELECT o.id,o.nev FROM orvos_beosztas beo
							LEFT JOIN orvosok o ON o.id=beo.orvosid
							WHERE tipusok LIKE '%{$szurestipus}%' AND beo.aktiv=1 GROUP BY beo.orvosid ORDER BY o.nev");
		
		//if(sql_num_rows($request)>1) $options.="<option value='0'>Válasszon orvost!</option>";
		while($result=sql_fetch_array($request)){
			$options.="<option ".(isset($_POST['orvosid'])&&$_POST['orvosid']==$result['id']?"selected":"")." value='{$result['id']}'>{$result['nev']}</option>";
		}
		$html.="<tr><td>Ellátó orvos: *</td><td><select onChange='setQuestions($(this).val(),{$szurestipus})' class='design-put' style='padding:3px;width:268px' id='selectorvos' name='selectorvos'>";
		$html.= 	$options;
		$html.="</select></td></tr>";
		
		return $html;
	}
	
	private function _reservationTimeSelector($orvosid,$szurestipus,$helyszin=1) {
        $webText = $this->lang->webText;
		if(!isset($_POST['datum'])) $_POST['datum'] = "";
		if(!isset($_POST['rinterval'])) $_POST['rinterval'] = 0;
        $dateStyle = (!empty($_POST["datum"])?"background-image:url(images/check.png);":"")."background-repeat:no-repeat;background-position:right 5px center;width:150px;height:24px;margin-right:5px;padding:4px 5px;font-size:16px;";
        $dateVal = substr($_POST["datum"], 0, 16);

        $html = "";
        $html.= "<div style='display:table-cell;vertical-align: middle;'>";
        $html.= "<input type='hidden' name='rinterval' id='rinterval' value='{$_POST["rinterval"]}' />";
        $html.= "<input placeholder='{$webText["kattintsagombra"]}' readonly='true' class='inputbox' style='{$dateStyle}' type='text' name='datum' id='datum' value='{$dateVal}' />";
        $html.= "</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><a href='#' onclick='showIdoPontValasztoV3(0,{$orvosid},{$szurestipus},{$helyszin});return false;' style='margin:0px;' class='newbutton'>{$webText["idopontvalasztas"]}</a></div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><img id='loadingspinner' style='margin-left:5px;height:25px;display:none;' src='/images/loading.svg' /></div>";
        $html.= "<tr><td></td><td><div id='idopontvalasztodiv' style='display:none;'></div></td></tr>";
		return $html;
    }
	
	
}
