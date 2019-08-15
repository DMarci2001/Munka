<?php

$cegid = 11;
$helyszinid = 1;

$_SESSION['fogl-error']['captcha'] = "";


if(!isset($managerError)) $resErr = 0;

//Foglalás mentése:
if(isset($_POST['finisher-button']))
{
	//Mezők elmentése sessionbe:
	$_SESSION['POST']['nev'] 	   = $_POST['nev'];
	$_SESSION['POST']['email'] 	   = $_POST['email'];
	$_SESSION['POST']['telefon']   = $_POST['telefon'];
	$_SESSION['POST']['szuldatum'] = date("Y-m-d",strtotime($_POST['szuldatum']));
	$_SESSION['POST']['megj'] 	   = $_POST['megj'];
	if( isset( $_POST['aszf'] )) $_SESSION['POST']['aszf'] = $_POST['aszf'];
	else $_SESSION['POST']['aszf'] = "";
	
	//Mező ellenőrzések:
	if($_SESSION['POST']['nev'] == "") $_SESSION['fogl-error']['nev'] = "Adja meg a nevét!";
	else $_SESSION['fogl-error']['nev'] = "";
	if($_SESSION['POST']['email'] == "") $_SESSION['fogl-error']['email'] = "Adjam eg az email címét!";
	else {
		if (!filter_var($_SESSION['POST']['email'], FILTER_VALIDATE_EMAIL)) $_SESSION['fogl-error']['email'] = "Hibás email cím!";
		else $_SESSION['fogl-error']['email'] = "";
	}
	
	if($_SESSION['POST']['telefon'] == "") $_SESSION['fogl-error']['telefon'] = "Adja meg a telefonszámát!";
	else $_SESSION['fogl-error']['telefon'] = "";
	
	if( $_SESSION['POST']['szuldatum'] == "" ) $_SESSION['fogl-error']['szuldatum'] = "Adja meg születési dátumát!";
	else $_SESSION['fogl-error']['szuldatum'] = "";
	
	if( strtotime( $_SESSION['POST']['szuldatum'] ))
	{
		$_SESSION['fogl-error']['szuldatum'] = "";
	}
	else $_SESSION['fogl-error']['szuldatum'] = "Hibás születési dátum!";
	
	if($_SESSION['POST']['aszf'] == "") $_SESSION['fogl-error']['aszf'] = "Jelöljje, hogy ha elfogadja az Adatvédelmi nyilatkozatot!";
	else
	{
		$_SESSION['POST']['aszf'] == "checked";
		$_SESSION['fogl-error']['aszf'] = "";
	}
	
	//captcha ellenőrzés:
	if (isset($_POST["g-recaptcha-response"])) $captcha=$_POST["g-recaptcha-response"];
	if (isset($captcha)) {
		if (!$captcha){
			$_SESSION['fogl-error']['captcha'] = "Please mark \"I am not a robot.\"";
		}
		else {
				$response = json_decode( file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfCaTIUAAAAAF1-t94n7TBAsKov_dglwP6b8Luo&response=".urlencode( $captcha )."&remoteip=".$_SERVER["REMOTE_ADDR"]), true);
				if ( $response["success"] == false ) {
					$_SESSION['fogl-error']['captcha'] = "You have failed the robot test.";
				}
				else $_SESSION['fogl-error']['captcha'] = "";
			}
		} else {
		$_SESSION['fogl-error']['captcha'] = "Captcha error!<br/>";
	}
	
	$checkStatus = 0;
	foreach( $_SESSION['fogl-error'] as $option )
	{
		if($option != "") $checkStatus++;
	}
	
	//Foglalt időpontok ellenőrzése:
	$reservationError = array();
	//Sima foglalások:
	if( isset( $_SESSION['reservations'] ))
	{
		foreach( $_SESSION['reservations'] as $key => $reservation )
		{
			$query = sql_query( "SELECT * FROM foglalasok 
								 WHERE datum = ? AND orvosassigned = ? AND helyszinid = ? AND szurestipusid = ?",
								 array( $reservation['idopont'], $reservation['orvosid'], $reservation['helyszinid'], $reservation['szurestipusid'] )
							   );
			if( sql_num_rows( $query ) > 0 ) $reservationError[] = $key;
		}
	}
	//Menedzser foglalások:
	$managerError = array();
	if( isset( $_SESSION['reservations-manager'] ))
	{
		foreach( $_SESSION['reservations-manager'] as $key => $manager )
		{
			$orvosid 	     = json_decode( stripslashes( $manager['orvosok'] ));
			$szurestipusid   = json_decode( stripslashes( $manager['szurestipusok'] ));
			$idopont 	     = json_decode( stripslashes( $manager['idopontok'] ));
			
			foreach( $idopont as $subKey => $value )
			{
				$query = sql_query( "SELECT * FROM foglalasok 
									 WHERE datum = ? AND orvosassigned = ? AND helyszinid = ? AND szurestipusid = ?",
									 array( $idopont[$subKey], $orvosid[$subKey], $manager['helyszinid'], $szurestipusid[$subKey] )
								   );
				if( sql_num_rows( $query ) > 0 ) $managerError[] = $key;
			}
		}
	}
	
	//Csomag foglalások:
	$csomagError = array();
	if( isset( $_SESSION['csomag-reservation'] ))
	{
		foreach( $_SESSION['csomag-reservation'] as $key => $csomag )
		{
			$orvosid 	     = json_decode( stripslashes( $csomag['orvosok'] ));
			$szurestipusid   = json_decode( stripslashes( $csomag['szurestipusok'] ));
			$idopont 	     = json_decode( stripslashes( $csomag['idopontok'] ));
			
			foreach( $idopont as $subKey => $value )
			{
				$query = sql_query( "SELECT * FROM foglalasok 
									 WHERE datum = ? AND orvosassigned = ? AND helyszinid = ? AND szurestipusid = ?",
									 array( $idopont[$subKey], $orvosid[$subKey], $csomag['helyszinid'], $szurestipusid[$subKey] )
								   );
				if( sql_num_rows( $query ) > 0 ) $csomagError[] = $key;
			}
		}
	}
	
	$reservationErrorText = "";
	if( count( $reservationError ) > 0 || count( $managerError ) > 0 || count( $csomagError ) > 0 )
	{
		$reservationErrorText.= '<div style = "margin-top:-17px;min-height:50x;font-family:Montserrat;font-size:14px;background-color:red;font-weight:bold;color:white;text-align:center;padding:10px">';
		foreach( $reservationError as $key )
		{
			$date = date("Y.m.d H:i", strtotime( $_SESSION['reservations'][$key]['idopont'] ));
			$szt  = sql_fetch_array(sql_query("SELECT * FROM szurestipusok WHERE id = {$_SESSION['reservations'][$key]['szurestipusid']}"));
			$reservationErrorText.= "<span style = 'font-size:16px;'>A {$date} - {$szt['megnev']}</span>&nbsp;&nbsp;&nbsp;&nbsp;időpontot sajnos lefoglalták, kérem válasszon másik időpontot.";
			$resErr++;
		}
		foreach( $managerError as $key )
		{	
			$date = date( "Y.m.d", strtotime( $_SESSION['reservations-manager'][$key]['displayTime'] ));
			$date.= "&nbsp;08:00~12:00";
			if( $_SESSION['reservations-manager'][$key]['menedzserid'] == 6 )  $megnev = "Alap menedzserszűrés";
			if( $_SESSION['reservations-manager'][$key]['menedzserid'] == 34 ) $megnev = "Emelt menedzserszűrés";
			if( $_SESSION['reservations-manager'][$key]['menedzserid'] == 35 ) $megnev = "Top menedzserszűrés";
			$reservationErrorText.= "<span style = 'font-size:16px;'>A {$date} - {$megnev}</span>&nbsp;&nbsp;&nbsp;&nbsp;időpontot sajnos lefoglalták, kérem válasszon másik időpontot.";
			$resErr++;
		}
		foreach( $csomagError as $key )
		{	
			$date = date( "Y.m.d", strtotime( $_SESSION['csomag-reservation'][$key]['displayTime'] ));
			$date.= "&nbsp;08:00~12:00";
			if( $_SESSION['csomag-reservation'][$key]['csomagid'] == 99 )  $megnev = "Egészségmegőrző csomag";
			$reservationErrorText.= "<span style = 'font-size:16px;'>A {$date} - {$megnev}</span>&nbsp;&nbsp;&nbsp;&nbsp;időpontot sajnos lefoglalták, kérem válasszon másik időpontot.";
			$resErr++;
		}
		$reservationErrorText.= "</div>";
	}
	
	if( count( $reservationError ) == 0 && count( $managerError ) == 0 && count( $csomagError ) == 0 && $checkStatus == 0 )
	{
		//Menedzser foglalások rögzítése:
		if( isset( $_SESSION['reservations-manager'] ))
		{
			foreach( $_SESSION['reservations-manager'] as $key => $manager )
			{
				$orvosid 	     = json_decode( stripslashes( $manager['orvosok'] ));
				$szurestipusid   = json_decode( stripslashes( $manager['szurestipusok'] ));
				$idopont 	     = json_decode( stripslashes( $manager['idopontok'] ));
				
				$foglid = array();
				foreach( $idopont as $subKey => $value )
				{
					$rn=rand( 1000000, 9999999 );
					
					if( $manager['menedzserid'] == 6 )  $extratxt = "Alap menedzser szűrés";
					if( $manager['menedzserid'] == 34 ) $extratxt = "Emelt menedzser szűrés";
					if( $manager['menedzserid'] == 35 ) $extratxt = "Top menedzser szűrés";
					
					$megj = $extratxt." - ".$_POST['megj'];
					
					$beonap = date("Y-m-d",strtotime($idopont[$subKey]));
					$nap    = date("N",strtotime($idopont[$subKey]));
					
					$request   = sql_query("SELECT binterval from orvos_beosztas where orvosid = ? AND (beonap = ? OR nap = ?) LIMIT 1",
											array( $orvosid[$subKey], $beonap, $nap ));
					$beosztas  = sql_fetch_array($request);
					
					$variables = array( $idopont[$subKey],$beosztas['binterval'], $manager['helyszinid'], $szurestipusid[$subKey], 
										$_POST['nev'], $_POST['email'], $_POST['telefon'], $_POST['szuldatum'],
										$megj, $orvosid[$subKey], $rn
									   );
					
					//Csak a fő időpontra akarjuk ki küldeni az sms-t, a többit küldöttre állítjuk:
					if( $szurestipusid[$subKey] == 6 || $szurestipusid[$subKey] == 34 || $szurestipusid[$subKey] == 35 ) $smssent = 1;
					else $smssent = 0;
					
					sql_query("INSERT INTO foglalasok 
							   SET regdatum = NOW(), cegid = 92, datum = ?, rinterval = ?, helyszinid = ?, szurestipusid = ?, nev = ?, 
							   email = ?, telefon = ?, szuldatum = ?, megj = ?, orvosassigned = ?, smssent = ?, rlang = 'hu', rkod = ?",
							   array($idopont[$subKey],$beosztas['binterval'], $manager['helyszinid'], $szurestipusid[$subKey], 
									 $_POST['nev'], $_POST['email'], $_POST['telefon'], $_POST['szuldatum'], $megj, $orvosid[$subKey], $smssent, $rn )
							  );
					$foglid[] = sql_insert_id();
				}
				//Megerősítendő levél küldése:
				if( count($foglid) > 0 ) confirmationMailSend( "menedzser", $foglid );
			}
			
		}
		//Sima foglalások rögzítése:
		if( isset( $_SESSION['reservations'] ))
		{
			foreach( $_SESSION['reservations'] as $key => $reservation )
			{
				$rn=rand( 1000000, 9999999 );
				$foglid = array();
				$type = "sima";
				
				if($reservation['vizsgalattipusid'] != "")
				{
					$tipus = sql_fetch_array( sql_query( "SELECT * FROM vizsgalattipusok WHERE id = ?", array( $reservation['vizsgalattipusid'] )));
					
					$megj  = $tipus['megnev']." - ".$_POST['megj'];
				}
				else $megj = $_POST['megj'];
				
				$beonap = date("Y-m-d",strtotime($reservation['idopont']));
				$nap    = date("N",strtotime($reservation['idopont']));
				
				$request   = sql_query("SELECT binterval from orvos_beosztas where orvosid = ? AND (beonap = ? OR nap = ?) LIMIT 1",
									    array( $reservation['orvosid'], $beonap, $nap ));
				$beosztas  = sql_fetch_array($request);
				
				$variables = array( $reservation['idopont'],$beosztas['binterval'], $reservation['helyszinid'], $reservation['szurestipusid'], $_POST['nev'], 
									$_POST['email'], $_POST['telefon'], date("Y-m-d",strtotime($_POST['szuldatum'])), $megj, $reservation['orvosid'], $rn 
								   );
				
				sql_query("INSERT INTO foglalasok 
						   SET regdatum = NOW(), cegid = 11, datum = ?, rinterval = ?, helyszinid = ?, szurestipusid = ?, nev = ?, 
						   email = ?, telefon = ?, szuldatum = ?, megj = ?, orvosassigned = ?, rlang = 'hu', rkod = ?",
						   array($reservation['idopont'],$beosztas['binterval'], $reservation['helyszinid'], $reservation['szurestipusid'], $_POST['nev'], 
								 $_POST['email'], $_POST['telefon'], date("Y-m-d",strtotime($_POST['szuldatum'])), $megj, $reservation['orvosid'], $rn )
						  );
				$foglid[] = sql_insert_id();
				
				if( $reservation['idopont2'] != "" )
				{
					sql_query("INSERT INTO foglalasok 
							   SET regdatum = NOW(), cegid = 11, datum = ?, rinterval = ?, helyszinid = ?, szurestipusid = ?, nev = ?, 
							   email = ?, telefon = ?, szuldatum = ?, megj = ?, orvosassigned = ?, rlang = 'hu', rkod = ?",
							   array($reservation['idopont2'],$beosztas['binterval'], $reservation['helyszinid'], $reservation['szurestipusid'], $_POST['nev'], 
									 $_POST['email'], $_POST['telefon'], date("Y-m-d",strtotime($_POST['szuldatum'])), $megj, $reservation['orvosid'], $rn )
							  );
					$foglid[] = sql_insert_id();
					$type = "double";
				}
				echo confirmationMailSend( $type, $foglid );
			}
			
		}
		//Csomag foglalások rögzítése:
		if( isset( $_SESSION['csomag-reservation'] ))
		{
			foreach( $_SESSION['csomag-reservation'] as $key => $csomag )
			{
				$orvosid 	     = json_decode( stripslashes( $csomag['orvosok'] ));
				$szurestipusid   = json_decode( stripslashes( $csomag['szurestipusok'] ));
				$idopont 	     = json_decode( stripslashes( $csomag['idopontok'] ));
				
				$foglid = array();
				foreach( $idopont as $subKey => $value )
				{
					$rn=rand( 1000000, 9999999 );
					
					if( $csomag['csomagid'] == 99 )  $extratxt = "Egészségmegőrző csomag";
					
					$megj = $extratxt." - ".$_POST['megj'];
					
					$beonap = date("Y-m-d",strtotime($idopont[$subKey]));
					$nap    = date("N",strtotime($idopont[$subKey]));
					
					$request   = sql_query("SELECT binterval from orvos_beosztas where orvosid = ? AND (beonap = ? OR nap = ?) LIMIT 1",
											array( $orvosid[$subKey], $beonap, $nap ));
					$beosztas  = sql_fetch_array($request);
					
					$variables = array( $idopont[$subKey],$beosztas['binterval'], $csomag['helyszinid'], $szurestipusid[$subKey], 
										$_POST['nev'], $_POST['email'], $_POST['telefon'], $_POST['szuldatum'],
										$megj, $orvosid[$subKey], $rn
									   );
					
					//Csak a fő időpontra akarjuk ki küldeni az sms-t, a többit küldöttre állítjuk:
					if( $szurestipusid[$subKey] == 6 || $szurestipusid[$subKey] == 34 || $szurestipusid[$subKey] == 35 ) $smssent = 1;
					else $smssent = 0;
					
					sql_query("INSERT INTO foglalasok 
							   SET regdatum = NOW(), cegid = 92, datum = ?, rinterval = ?, helyszinid = ?, szurestipusid = ?, nev = ?, 
							   email = ?, telefon = ?, szuldatum = ?, megj = ?, orvosassigned = ?, smssent = ?, rlang = 'hu', rkod = ?",
							   array($idopont[$subKey],$beosztas['binterval'], $csomag['helyszinid'], $szurestipusid[$subKey], 
									 $_POST['nev'], $_POST['email'], $_POST['telefon'], $_POST['szuldatum'], $megj, $orvosid[$subKey], $smssent, $rn )
							  );
					$foglid[] = sql_insert_id();
				}
				//Megerősítendő levél küldése:
				if( count($foglid) > 0 ) confirmationMailSend( "csomag", $foglid );
			}
		}
		header("location:index.php?page=sikeresfoglalas");
	}
	else echo "Sikertelen foglalás!<br/>";
}

if(isset($_SESSION['previousUser'])) $result = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE id = ? ", array( $_SESSION['previousUser'] )));
?>
<div class = "fejlecdiv" style = "background-color:#9d0102">HungáriaMed M - Időpont foglalás</div>
<link rel  = "stylesheet" href="style.css" type="text/css" media="screen" />
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src  = "jquery.js"></script>
<script>
$(document).ready(function(){
	$(function(){
	$('#szuldatum').datepicker({
		dateFormat: 'yy.mm.dd',
		changeMonth: true,
		changeYear: true,
		yearRange: '-100y:c+nn',
		maxDate: '+2y'
		});
		$.datepicker.regional['hu'] = {
		closeText: 'Bezárás',
		prevText: 'Előző hónap',
		nextText: 'Következő hónap',
		currentText: 'Nyní',
		monthNames: ['Január', 'Február', 'Március', 'Április', 'Május', 'Június', 'Július', 'Augusztus', 'Szeptember',
		  'Október', 'November', 'December'
		],
		monthNamesShort: ['Jan', 'Feb', 'Már', 'Ápr', 'Máj', 'Jún', 'Júl', 'Aug', 'Szep', 'Okt', 'Nov', 'Dec'],
		dayNames: ['Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Pénter', 'Szombat', 'Vasárnap'],
		dayNamesShort: ['Hé', 'Ke', 'Sze', 'Csü', 'Pé', 'Szo', 'Vas'],
		dayNamesMin: ['Hé', 'Ke', 'Sze', 'Csü', 'Pé', 'Szo', 'Vas'],
		weekHeader: 'hét'
	  };

	  $.datepicker.setDefaults($.datepicker.regional['hu']);
	});
	  
	$('.medic-tag-rectangle').hover(function () 
	{
		$(this).find('.mtr-more-info-tag').css({'width':'30px'});
		$(this).css({'width':'210px'});
		if($(this).data('tagged')  && $(this).data('tagged') == true) return false;
		$(this).css({'background-color':'gray','color':'white'});
		
	}, function() {
		$(this).find('.mtr-more-info-tag').css({'width':'0px'});
		$(this).css({'width':'180px'});
		if($(this).data('tagged')  && $(this).data('tagged') == true) return false;
		$(this).css({'background-color':'white','color':'#444'});
	});
	
	$('#szuldatum').on('input', function(e) 
	{
		if( $(this).val().length > 0 )
		{
			lastChar = $(this).val().substr( $(this).val().length - 1 );
			//Ha eléri a 4. karaktert, szúrjon be egy pontot:
			if( $('#szuldatum').data('last-length') == 3 && $(this).val().length == 4 )
			{
				$(this).val( $(this).val() + '.');
			}
			//Ha megáll a 4. karakteren majd leüt egy gombot, adjon hozzá egy pontot az utolsó karakter elé:
			if( $('#szuldatum').data('last-length') == 4 && $(this).val().length == 5 )
			{
				$(this).val( $('#szuldatum').data( 'last-text') + '.' + lastChar );
			}
			//Ha eléri a 7. karaktert adjon hozzá egy pontot:
			if( $('#szuldatum').data('last-length') == 6 && $(this).val().length == 7 )
			{
				$(this).val( $(this).val() + '.');
			}
			if( $('#szuldatum').data('last-length') == 7 && $(this).val().length == 8 )
			{
				$(this).val( $('#szuldatum').data( 'last-text') + '.' + lastChar );
			}
		}
		$('#szuldatum').data('last-length', $(this).val().length );
		$('#szuldatum').data('last-text', $(this).val());
	});
	
	$('#szuldatum').keydown(function (e) {
        // Allow: backspace, delete, tab, escape, enter and .
        if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110]) !== -1 ||
             // Allow: Ctrl+A, Command+A
            (e.keyCode === 65 && (e.ctrlKey === true || e.metaKey === true)) || 
             // Allow: home, end, left, right, down, up
            (e.keyCode >= 35 && e.keyCode <= 40)) {
                 // let it happen, don't do anything
                 return;
        }
        // Ensure that it is a number and stop the keypress
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
            e.preventDefault();
        }
    });
	
	$('#telefon').keydown(function (e) {
        // Allow: backspace, delete, tab, escape, enter and .
        if ($.inArray(e.keyCode, [46, 8, 9, 27, 13]) !== -1 ||
             // Allow: Ctrl+A, Command+A
            (e.keyCode === 65 && (e.ctrlKey === true || e.metaKey === true)) || 
             // Allow: home, end, left, right, down, up
            (e.keyCode >= 35 && e.keyCode <= 40)) {
                 // let it happen, don't do anything
                 return;
        }
        // Ensure that it is a number and stop the keypress
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
            e.preventDefault();
        }
    });
	
	$('.medic-tag-rectangle').click(function(){
					
		$('.medic-tag-rectangle').each(function() {
		  $(this).data('tagged',false);
		  $(this).css({'background-color':'white','color':'#444'});
		});
		
		if($(this).data('tagged'))
		{
			if($(this).data('tagged') == false)
			{
				$(this).data('tagged',true);
				$(this).css({'background-color':'#9d0102','color':'white'});
				$('#doctor').val($(this).attr('id'));
				chooseType();
			}
			if($(this).data('tagged') == true)
			{
				$(this).data('tagged',false);
				$(this).css({'background-color':'white','color':'#444'});
			}
		}
		else
		{
			$(this).data('tagged',true);
			$(this).css({'background-color':'#9d0102','color':'white'});
			$('#doctor').val($(this).attr('id'));
			chooseType();
		}
		
	}).children('.mtr-more-info-tag').click(function(e) {
		window.open('https://www.hungariamed.hu/rolunk/munkatarsaink');
		return false;
	});
});
//Form enter véglegesítés megakadályozása:
$(document).ready( function() {
  $(window).keydown( function(event){
    if( event.keyCode == 13 ) {
      event.preventDefault();
      return false;
    }
  });
});
</script>
<?php echo (isset($reservationErrorText)?$reservationErrorText:"") ?>
</div>
<div style = "padding-left:20px">
<form method = "POST">
	<input type = "hidden" id = "cegid" value = "<?php echo $cegid ?>"/>
	<input type = "hidden" id = "selectedTimes" value = "<?php echo (checkSelectedTimes($resErr)?checkSelectedTimes($resErr):"0") ?>" />
	<div style = "padding-bottom:10px;transition:all 0.3s ease;">
		<table style = "font-family:Montserrat;font-size:16px">
			<tr><td colspan = "2"><div style = "text-align:left;padding-left:5px" class = "locked"><h3 style = "text-align:left" class = "ds_title locked">Személyes adatok:</h3></div></td></tr>
			<tr>
				<td align = "right">Teljes név:</td>
				<td><input type = "textbox" class = "design-put" name = "nev" value = "<?php echo (isset($_SESSION['POST']['nev'])?$_SESSION['POST']['nev']:"") ?>" /></td>
				<td style = "color:red"><?php echo (isset($_SESSION['fogl-error']['nev'])?$_SESSION['fogl-error']['nev']:"") ?></td>
			</tr>
			<tr>
				<td align = "right">E-mail:</td>
				<td><input type = "email" class = "design-put" name = "email" value = "<?php echo (isset($_SESSION['POST']['email'])?$_SESSION['POST']['email']:"") ?>" /></td>
				<td style = "color:red"><?php echo (isset($_SESSION['fogl-error']['email'])?$_SESSION['fogl-error']['email']:"") ?></td>
			</tr>
			<tr>
				<td align = "right">Telefonszám:</td>
				<td><input type = "textbox" class = "design-put" maxlength = "11" placeholder = "pl.: 06301234567" id = "telefon" name = "telefon" value = "<?php echo (isset($_SESSION['POST']['telefon'])?$_SESSION['POST']['telefon']:"") ?>" /></td>
				<td style = "color:red"><?php echo (isset($_SESSION['fogl-error']['telefon'])?$_SESSION['fogl-error']['telefon']:"") ?></td>
			</tr>
			<tr>
				<td align = "right">Születési dátum:</td>
				<td><input type = "textbox" maxlength = "10" placeholder = "éééé.hh.nn" class = "design-put" name = "szuldatum" id = "szuldatum" value = "<?php echo (isset($_SESSION['POST']['szuldatum'])?$_SESSION['POST']['szuldatum']:"") ?>" /></td>
				<td style = "color:red"><?php echo (isset($_SESSION['fogl-error']['szuldatum'])?$_SESSION['fogl-error']['szuldatum']:"") ?></td>
			</tr>
			<tr>
				<td style = "padding-top:10px" align = "right" valign = "top">Megjegyzés:</td>
				<td><textarea class = "design-put" name = "megj"><?php echo (isset($_SESSION['POST']['megj'])?$_SESSION['POST']['megj']:"") ?></textarea></td>
			</tr>
			<tr>
				<td colspan = "2"><div style = "margin-left:136px" class="g-recaptcha" data-sitekey="6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG"></div></td>
				<td style = "color:red"><?php echo ( isset( $_SESSION['fogl-error']['captcha'] ) ? $_SESSION['fogl-error']['captcha'] : "" ) ?></td>
			</tr>
			<tr>
				<td colspan = "2" align = "center">Az <a href = "https://www.hungariamed.hu/images/adatkezeles.pdf" target = "_blank">Adatvédelmi nyilatkozatot</a> elfogadom&nbsp;<input type = "checkbox" <?php echo (isset($_SESSION['POST']['nev'])?$_SESSION['POST']['aszf']:"") ?> name = "aszf" value = "1" /></td>
				<td style = "color:red"><?php echo (isset($_SESSION['fogl-error']['aszf'])?$_SESSION['fogl-error']['aszf']:"") ?></td>
			</tr>
		</table>
	</div>
	<div id = "selected_times_wrapper" style = "transition:all 0.3s ease;"><?php echo reloadSelectedTimes($resErr) ?></div>
	<!--<div id = "locations_wrapper" ><select id = "locations" onChange = "chooseLocation()"><?php echo $helyszinek ?></select></div>-->
	<div>
		<?php
		if(reloadSelectedTimes($resErr)) $style = "display:inline-block !important;font-size:16px;margin:10px 10px 10px 5px";
		else $style = "display:none !important;font-size:16px;margin:10px 10px 10px 5px";
		?>
		<div class = "examination_object" onClick= "chooseLocation(1);return false" style = "font-size:16px;margin:10px 10px 10px 5px">Időpont választás</div>
		<input type = "submit" id = "finisher-button" name = "finisher-button"  class = "examination_object_active" style = "<?php echo $style ?>" value = "Foglalás véglegesítése"></input>
	</div>
	<div id = "reservation-UI">
		<input type = "hidden" id = "location" value = "<?php echo (isset($_POST['pre-select'])?"1":"") ?>" />
		<input type = "hidden" id = "examination" value = "<?php echo (isset($_POST['pre-select'])?$_POST['pre-select']:"") ?>" />
		<input type = "hidden" id = "doctor" value = "<?php echo (isset($_POST['pre-select'])&&($_POST['pre-select']==6||$_POST['pre-select']==34||$_POST['pre-select']==35)?"menedzser":"") ?>" />
		<input type = "hidden" id = "type" value = "" />
		<div id = "examinations_wrapper" style = "max-width:1022px;display:<?php echo (isset($_POST['pre-select'])?"block;":"none;") ?>"><?php echo (isset($_POST['pre-select'])?loadExaminations(1,11,$_POST["pre-select"]):"") ?></div>
		<div id = "doctors_wrapper" style = "padding:10px 0px 10px;max-width:1022px;display:<?php echo (isset($_POST['pre-select']) && ($_POST['pre-select'] != 6||$_POST['pre-select']!=34||$_POST['pre-select']!=35)?"block;":"none;") ?>"><?php echo (isset($_POST['pre-select'])&&$_POST['pre-select']!= 6&&$_POST['pre-select']!=34&&$_POST['pre-select']!=35?loadDoctors($_POST["pre-select"]):"") ?></div>
		<div id = "types_wrapper" style = "padding:10px 0px 10px;display:<?php echo (isset($_POST['pre-select'])&&($_POST['pre-select']==6||$_POST['pre-select']==34||$_POST['pre-select']==35)?"block;":"none;") ?>"><?php echo (isset($_POST['pre-select'])&&($_POST['pre-select']==6||$_POST['pre-select']==34||$_POST['pre-select']==35)?loadExamTypes($_POST['pre-select'],"menedzser"):"") ?></div>
		<div id = "TimeSelector-UI" style = "padding-bottom:20px;display:relative;"></div>
	</div>
</form>
</div>