<?php
class RemoteBookingPage extends CorePage{

    private $arData;
    private $szuresData;

	public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;

        $bookingService = new BookingService();
		
		
		
		if(isset($_GET['szurestipus'])) $_POST['szurestipus']=$_GET['szurestipus'];
		if(!isset($_POST['szurestipus'])) $_POST['szurestipus']=0;
		
		
		
        $this->arData = sql_fetch_array(sql_query("SELECT * FROM arak WHERE tipusid=? AND cegid LIKE '%|{$_SESSION['helyszindata']['id']}|%' ", [$_POST['szurestipus']]));
        $this->szuresData = sql_fetch_array(sql_query("SELECT * FROM szurestipusok WHERE id=?",array($_POST['szurestipus'])));

        if(isset($_POST['saveForm'])){
			//Végig kell futni a szurestipus parameterein, abban megtalálunk minden szükséges információt ami kellhet.
			if (isset($_POST["szuldatumev"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"]."-".substr("00".$_POST["szuldatumho"],-2)."-".substr("00".$_POST["szuldatumnap"],-2);
            }
			
			//Orvos választás:
			$_POST['orvosid']=64;
			
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
			if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) $this->errors[] = $webText["hibasemail"];
			if ($_POST['szuldatum']=="0-00-00") $this->errors[] = "A születési dátum megadása kötelező!";
			if (!$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) $this->errors[] = $webText["szulformat"];
			if (!isset($_POST["aszf"])) $this->errors[] = $webText["aszfkotelezo"];
			if (!isset($_POST["simplepay"])) $this->errors[] = "A simplepay felhasználási feltételeit a vásárláshoz el kell elfogadnia!";
			

			$captchaError = $this->utils->checkCaptcha();
            if (!empty($captchaError)) {
                $this->errors[] = $captchaError;
            }

			//Kérdések ellenőrzése:
			$questionArr=json_decode($this->szuresData['askandanswers'],true);
			$questions = "";
			$sor=0;
			do{
				if(empty($_POST["kerdes-{$sor}"])) $this->errors[] = "Kérem, válaszoljon a ".($sor+1).". kérdésre!";
				$questions.="<p>{$questionArr[$sor]['question']}</p><p>".$_POST["kerdes-{$sor}"]."</p><br>";
				$sor++;
			}while(isset($_POST["kerdes-{$sor}"]));
			
			//Egyéb mezők hozzáadása a queryhez:
			$_POST["questions"] = $questions;
			$_POST["simplepay"] = 1;
			$_POST["noreservation"] = 1;
            $_POST["datum"] = date("Y-m-d H:i:s");
            $_POST["totalprice"] = $this->arData["price"];
            $_POST["currency"] = $this->arData["penznem"];

			if (empty($this->errors)) {
                $forwardURL = $bookingService->addReservation($_POST);
                header("location:{$forwardURL}");
				die();
			}
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
		
		if(!isset($_POST['szurestipus'])) header("Location:index.php");
       
		
		
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
		$questionArr=json_decode($this->szuresData['askandanswers'],true);
		
		
		
		
		
		//FRONT-END
		
		
		
		echo $this->showErrors();
		
		$html.= "<form method='POST' enctype='multipart/form-data'>";
		
		//Páciens adatok:
		
		$html.= "<h2>Szükséges adatok</h2>";
		$html.= "<table>";
		$html.= 	$this->setFields($inputArr);
		$html.= "</table>";
		
		//Kérdez/Felelek:
		$html.= "<h2>Kérdések</h2>";
		$html.= "<table style='width:100%'>";
		$html.= 	$this->setQuestions($questionArr);
		
		//Képfeltöltés:
		$html.= "<tr><td>";
        $html.= "<div style='font-size:16px'>Kérem, csatoljon 2-4 jó minőségű fényképet a tünetekről.</div>";
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
	
	public function setQuestions($questionArr){
		
		//BACK-END:
		$html="";
		$sor=0;
		foreach($questionArr as $each){
			$html.="<tr><td><strong>".($sor+1).".</strong>&nbsp;{$each['question']}</td></tr>";
			$html.="<tr><td><textarea name='kerdes-{$sor}' class='design-put' style='width:95%;height:150px'>".(isset($_POST["kerdes-{$sor}"])?$_POST["kerdes-{$sor}"]:"")."</textarea></td></tr>";
			$html.="<tr><td style='height:25px'></td></tr>";
			$sor++;
		}
		
		return $html;
	}
	
	
}
