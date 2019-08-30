<?php

if(isset($_POST['gdpr-modify']))
{
	if ( $_POST['status'] == "closed" ) $mod_request = 1; 
	else $mod_request = 0;
	if ( $_POST['munkahely'] != "0" )
	{
		
	if($_POST['mod_request'] == 1 && $_POST['exist_doc'] == 0 ) $mod_request = 1;
	if($_POST['mod_request'] == 1 && $_POST['exist_doc'] == 1 ) $mod_request = 0;
	$fulladdress   = $_POST['cim_irsz'].", ".$_POST['cim_varos']." ".$_POST['cim_utca'];
	$letteraddress = $_POST['level_irsz'].", ".$_POST['level_varos']." ".$_POST['level_utca'];
	if(sql_query("UPDATE GDPR SET 
				  nev 	  	  = ?, szuldatum = ?, szulhely    	  = ?, taj 		     = ?, anyjaneve   = ?, lakcim 	   = ?, 
				  cim_irsz 	  = ?, cim_varos = ?, cim_utca     	  = ?, levelezesicim = ?, level_irsz  = ?, level_varos = ?, level_utca  = ?,
				  telefon  	  = ?, email 	 = ?, beirt_munkahely = ?, munkahely 	 = ?, munkakor 	  = ?, adatlap     = ?, vizsgalat   = ?, 
				  adatkuldes  = ?, labor     = ?, tajekoztato 	  = ?, szig 	 	 = ?, mod_request = ?
				  WHERE id = ?", 
				  array( $_POST['nev'], 	 	$_POST['szuldatum'],  $_POST['szulhely'],  $_POST['taj'], 		 $_POST['anyjaneve'], $fulladdress,
						 $_POST['cim_irsz'], 	$_POST['cim_varos'],  $_POST['cim_utca'],  $letteraddress, 		 $_POST['level_irsz'], 
						 $_POST['level_varos'], $_POST['level_utca'], $_POST['telefon'],   $_POST['email'], 	 $_POST['beirt_munkahely'], 
						 $_POST['munkahely'],	$_POST['munkakor'],   $_POST['adatlap'],   $_POST['vizsgalat'],  $_POST['adatkuldes'], $_POST['labor'], 
						 $_POST['tajekoztato'], $_POST['szig'], 	  $mod_request,		   $_POST['id']	  
						)
				)
	){ 
		$response = '<div style = "background-color:#00B645;color:white;font-size:20px;font-weight:bold;text-align:center;padding:10px;margin-bottom:20px">Sikeres Módosítás!</div>'; 
	 }
	}
	else
	{
		$response = '<div style = "background-color:red;color:white;font-size:20px;font-weight:bold;text-align:center;padding:10px;margin-bottom:20px">Válassz céget a munkahely mezőben!</div>'; 
	}
}

if( isset( $_POST['verify'] ))
{
	$error = 0;
	if($_POST['nev'] == "") $error++;
	if($_POST['szuldatum'] == "") $error++;
	if($_POST['szulhely'] == "") $error++;
	if($_POST['taj'] == "") $error++;
	if($_POST['anyjaneve'] == "") $error++;
	if($_POST['cim_irsz'] == "") $error++;
	if($_POST['cim_varos'] == "") $error++;
	if($_POST['cim_utca'] == "") $error++;
	if($_POST['level_irsz'] == "") $error++;
	if($_POST['level_varos'] == "") $error++;
	if($_POST['level_utca'] == "") $error++;
	if($_POST['telefon'] == "") $error++;
	if($_POST['email'] == "") $error++;
	if($_POST['beirt_munkahely'] == "") $error++;
	if($_POST['munkahely'] == "") $error++;
	if($_POST['munkakor'] == "") $error++;
	if($_POST['adatlap'] == "") $error++;
	
	if($error == 0)
	{
		if($_POST['mod_request'] == 1 && $_POST['exist_doc'] == 0 ) $mod_request = 1;
	if($_POST['mod_request'] == 1 && $_POST['exist_doc'] == 1 ) $mod_request = 0;
		$fulladdress   = $_POST['cim_irsz'].", ".$_POST['cim_varos']." ".$_POST['cim_utca'];
		$letteraddress = $_POST['level_irsz'].", ".$_POST['level_varos']." ".$_POST['level_utca'];
		if(sql_query("UPDATE GDPR SET 
					  nev 	  	  = ?, szuldatum = ?, szulhely    	  = ?, taj 		     = ?, anyjaneve   = ?, lakcim 	   = ?, 
					  cim_irsz 	  = ?, cim_varos = ?, cim_utca     	  = ?, levelezesicim = ?, level_irsz  = ?, level_varos = ?, level_utca  = ?,
					  telefon  	  = ?, email 	 = ?, beirt_munkahely = ?, munkahely 	 = ?, munkakor 	  = ?, adatlap     = ?, vizsgalat   = ?, 
					  adatkuldes  = ?, labor     = ?, tajekoztato 	  = ?, szig 	 	 = ?, mod_request = ?
					  WHERE id = ?", 
					  array( $_POST['nev'], 	 	$_POST['szuldatum'],  $_POST['szulhely'],  $_POST['taj'], 		 $_POST['anyjaneve'], $fulladdress,
							 $_POST['cim_irsz'], 	$_POST['cim_varos'],  $_POST['cim_utca'],  $letteraddress, 		 $_POST['level_irsz'], 
							 $_POST['level_varos'], $_POST['level_utca'], $_POST['telefon'],   $_POST['email'], 	 $_POST['beirt_munkahely'], 
							 $_POST['munkahely'],	$_POST['munkakor'],   $_POST['adatlap'],   $_POST['vizsgalat'],  $_POST['adatkuldes'], $_POST['labor'], 
							 $_POST['tajekoztato'], $_POST['szig'], 	  $mod_request,		   $_POST['id']	  
							)
					)
		){
			header( "Location:/admin/templates/create_gdpr_file.php?id={$_POST['id']}&adatlap={$_POST['adatlap']}&method=create" );
			//$response = '<div style = "background-color:#00B645;color:white;font-size:20px;font-weight:bold;text-align:center;padding:10px;margin-bottom:20px">Sikeres Jóváhagyás!</div>'; 
		 }
	}
}


if ( isset( $_POST['download-signed-pdf'] ))
{
	header("Pragma: no-cache");
	header("Cache-Control: no-store, no-cache");
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: must-revalidate");
	header('Content-transfer-encoding: binary');
	header("Content-Disposition: attachment; filename=" . $_GET['doc'] . ".pdf");
	header("Content-Type: application/pdf");
	readfile("../doc/GDPR/{$_GET['doc']}.pdf");
}

if( isset($_POST['download-pdf']) )
{
	header( "Location:/admin/templates/create_gdpr_file.php?id={$_POST['id']}&adatlap={$_POST['adatlap']}&method=download" );
}

$result = sql_fetch_array( sql_query("SELECT * FROM GDPR WHERE id = ".addslashes( $_GET['doc'] )));

if($_GET['status'] == "open")
{
	$onClick = "onClick='modifyVerification();return false'";
	$downloadButtonName = "download-signed-pdf";
	$sidePDF = "src = '../admin/templates/create_gdpr_file.php?id={$result['id']}&adatlap={$result['adatlap']}&method=preview'";
	$submitButton = array("name" => "verify", "value" => "Jóváhagyás");
	$title= "GDPR - ellenőrző";
}
if($_GET['status'] == "closed")
{
	$onClick = "";
	$downloadButtonName = "download-pdf";
	$sidePDF = "src = '../doc/GDPR/{$_GET["doc"]}.pdf'";
	$submitButton = array("name" => $downloadButtonName, "value" => "Letöltés" );
	$title = "GDPR - rögzített példány";
}
$inputPadding = "style = 'padding: 5px 10px 5px'";
?>
<style>
.gdpr-edit-table td{
	padding:2px;
}
.gdpr-style{
	padding: 5px 10px 5px;
	width: 370px;
}
.shader{
	position:fixed;
	width:100%;
	height:100%;
	z-index: 100000;
	background-color:black;
	opacity:0.5;
}
#ClickBox{
	position:fixed;
	top:0;left:0;right:0;bottom:0;
	margin:auto;
	background-color:white;
	height:210px;
	width:260px;
	z-index: 100001;
}
</style>
<script type = "text/javascript">
$(document).ready(function(){
$(function(){
	$('#szuldatum').datepicker({
		dateFormat: 'yy-mm-dd',
		changeMonth: true,
		changeYear: true,
		yearRange: '-100y:c+nn',
		maxDate: '+2y'
		});
		$.datepicker.regional['hu'] = {
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
});
function changeAdatlap()
{
	var selected = $('select[name="adatlap"]').val();
	$('.adatlapok').each(function(i,obj) {
		$(obj).css('display','none');
	});
	var vizsgalatok = '';
	$('#'+selected+' input').each(function(i,obj){
		if($(obj).prop('checked') == true)
		{
			vizsgalatok+=$(obj).attr('name')+', ';
		}
	});
	vizsgalatok = vizsgalatok.slice(0,-2);
	$('input[name="vizsgalat"]').val(vizsgalatok);
	
	$('#'+selected).css('display','block');
}

function modifyVerification()
{
	popupBox('open');
}
function popupBox(command)
{
	if(command ==  'open')
	{
		$('.shader').css('display','block');
		$('#ClickBox').css('display','block');
	}
	if(command == 'close')
	{
		$('.shader').css('display','none');
		$('#ClickBox').css('display','none');
	}
}

function CheckPass(pass)
{
	$.ajax({
		 url: 'index.php',
		type: 'post',
		data: {checkpass:true,password:pass}
	}).done(function(data)
	{
		if(data == 'Access Granted.')
		{
			$('<input />').attr('type', 'hidden').attr('name', 'gdpr-modify').attr('value', '1').appendTo('#GDPR-data');
			$('#GDPR-data').submit();
		}
		else $('#errorBox').text('Hibás Jelszó!');
	});
}
$(document).on('click','input',function(){
	var target = '#'+$(this).closest('table').attr('id');
	var vizsgalatok = '';
	if(target != '#magán')
	{
		$(target+' input').each(function (i, obj) {
			$(obj).prop('checked', false);
		});
		$(this).prop('checked',true);
	}
	
	//Kijelölt vizsgálatok össze szedése:
	$(target+' input').each(function(i,obj){
		if($(obj).prop('checked') == true)
		{
			vizsgalatok+=$(obj).attr('name')+', ';
		}
	});
	vizsgalatok = vizsgalatok.slice(0,-2);
	if(target != '#undefined') $('input[name="vizsgalat"]').val(vizsgalatok);
});
</script>
<div id = "ClickBox" style = "border:1px solid #363636;border-radius:5px;display:none">
	<div class = "ClickBoxheader" style = "display:block;height:28px;background-color:#363636">
		<i style = "font-size:18px;float:right;color:white;padding:5px;cursor:pointer" onClick = 'popupBox("close")' class="fas fa-times"></i>
		<span style = "display:inline-block;width:"></span>
	</div>
	<div class = "ClickBox-Body" style = "display:block;font-family: Montserrat;font-size: 16px;text-align:center">
		<p style = "padding:10px">Adj meg egy vezetői jelszót:</p>
	</div>
	<div style = "text-align:center">
		<input class = "design-put gdpr-style" style = "width:180px" type = "password" id = "adminpass" />
		<div id = "errorBox" style = "font-family: Montserrat;font-size: 16px;color:red;text-align:center;margin-top:5px"></div>
		<input style = "margin:10px;padding:8px 20px" type = "button" onClick = 'CheckPass($("#adminpass").val())' value = "Jóváhagyás"/>
	</div>
</div>

<div class = "pagehead">
	<div style="display:table-cell;vertical-align:middle;"> <?php echo $title ?></div>
	<div style="display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px"><a class = "ujbutton" href="index.php?page=gdpr">Vissza</a></div>
</div>
<?php if( isset( $response )) echo $response ?>

<form method = "POST" id = "GDPR-data">
<input type = "hidden" name = "id" value = "<?php echo $_GET['doc'] ?>"/>
<input type = "hidden" name = "status" value = "<?php echo $_GET['status'] ?>"/>
<input type = "hidden" name = "mod_request" = value = "<?php echo $result['mod_request'] ?>"/>
<input type = "hidden" name = "exist_doc" = value = "<?php echo $result['exist_doc'] ?>"/>
<table class = "gdpr-edit-table" style = "font-family: Montserrat;display:inline-block;float:left">

<tr><td align = "right"><strong>Név:</strong>&nbsp;</td>			 
	<td><input type = "textbox"
			   value = "<?php echo $result['nev'] ?>" 
			   class = "design-put gdpr-style" 
			   name = "nev" /></td></tr>

<tr><td align = "right"><strong>Szül. dátum:</strong>&nbsp;</td>	 
	<td><input type = "textbox"
			   value = "<?php echo date("Y-m-d",strtotime($result['szuldatum'])) ?>" 
			   class = "design-put gdpr-style" 
			   id = "szuldatum" 
			   name = "szuldatum" /></td></tr>
			   
<tr><td align = "right"><strong>Szül. hely:</strong>&nbsp;</td>		 
	<td><input type = "textbox"
			   value = "<?php echo $result['szulhely'] ?>" 
			   class = "design-put gdpr-style" 
			   name = "szulhely" /></td></tr>
			   
<tr><td align = "right"><strong>TAJ:</strong>&nbsp;</td>			 
	<td><input type = "textbox"
			   value = "<?php echo $result['taj'] ?>" 
			   class = "design-put gdpr-style" 
			   name = "taj" /></td></tr>
			   
<tr><td align = "right"><strong>Anyja neve:</strong>&nbsp;</td>		 
	<td><input type = "textbox"
			   value = "<?php echo $result['anyjaneve'] ?>" 
			   class = "design-put gdpr-style" 
			   name = "anyjaneve" /></td></tr>
			   
<tr><td align = "right"><strong>Lakcím:</strong>&nbsp;</td>			 
	<td><input type = "textbox"
			   value = "<?php echo $result['cim_irsz'] ?>" 
			   class = "design-put gdpr-style"
			   style = "width:40px"
			   placeholder = "IRSZ"
			   name = "cim_irsz" />
		<input type = "textbox" 
			   value = "<?php echo $result['cim_varos'] ?>" 
			   class = "design-put gdpr-style"
			   style = "width:100px"
			   placeholder = "Város"
			   name = "cim_varos" />
		<input type = "textbox"
			   value = "<?php echo $result['cim_utca'] ?>" 
			   class = "design-put gdpr-style"
			   style = "width:180px"
			   placeholder = "utca/hsz"
			   name = "cim_utca" /></td></tr>
			   
<tr><td align = "right"><strong>Levelezési cím:</strong>&nbsp;</td>	 
	<td><input type  = "textbox"
			   value = "<?php echo $result['level_irsz'] ?>" 
			   class = "design-put gdpr-style"
			   style = "width:40px"
			   placeholder = "IRSZ"
			   name  = "level_irsz" />
		<input type  = "textbox"
			   value = "<?php echo $result['level_varos'] ?>" 
			   class = "design-put gdpr-style"
			   style = "width:100px"
			   placeholder = "Város"
			   name  = "level_varos" />
		<input type  = "textbox"
			   value = "<?php echo $result['level_utca'] ?>" 
			   class = "design-put gdpr-style"
			   style = "width:180px"
			   placeholder = "utca/hsz"			   
			   name  = "level_utca" /></td></tr>
			   
<tr><td align = "right"><strong>Telefon:</strong>&nbsp;</td>		 
	<td><input type = "textbox"
			   value = "<?php echo $result['telefon'] ?>" 
			   class = "design-put gdpr-style" 
			   name = "telefon" /></td></tr>
			   
<tr><td align = "right"><strong>Email:</strong>&nbsp;</td>			 
	<td><input type = "textbox"
			   value = "<?php echo $result['email'] ?>" 
			   class = "design-put gdpr-style" 
			   name = "email" /></td></tr>
			   
<tr><td align = "right"><strong>SZIG szám:</strong>&nbsp;</td>		 
	<td><input type = "textbox"
			   value = "<?php echo $result['szig'] ?>" 
			   class = "design-put gdpr-style" 
			   name = "szig" /></td></tr>
			   
<tr><td align = "right"><strong>Beírt munkahely:</strong>&nbsp;</td>		 
	<td><input type = "textbox"
			   value = "<?php echo $result['beirt_munkahely'] ?>" 
			   class = "design-put gdpr-style"
			   style = "width:100px"
			   name = "beirt_munkahely" />
		<select name = "munkahely"
				class = "design-put gdpr-style"
				style = "width:270px">
			<option value = "0"> - Válassz - </option>
			<?php
			$htmlout = "";
			$ceg_query = sql_query("SELECT * FROM cegek ORDER BY megnev ASC");
			while ($result_ceg = sql_fetch_array( $ceg_query ))
			{
				if( $result['munkahely'] ==  $result_ceg['id'] )
				{
					$selected = "selected";
				}
				else $selected = "";
				$htmlout.= "<option value = '{$result_ceg['id']}' {$selected} >{$result_ceg['megnev']}</option>";
			}
			echo $htmlout;
			?>
		</select></td></tr>
			   
<tr><td align = "right"><strong>Munkakör:</strong>&nbsp;</td>		 
	<td><input type = "textbox"
			   value = "<?php echo $result['munkakor'] ?>" 
			   class = "design-put gdpr-style" 
			   name = "munkakor" /></td></tr>
			   
<tr><td align = "right"><strong>Vizsgálat típusa:</strong>&nbsp;</td>
	<td><select name = "adatlap" onChange = "changeAdatlap()">
			<option <?php echo ($result['adatlap'] == "üzemorvosi"?"selected":"") ?> value = "üzemorvosi">Üzemorvosi</option>
			<option <?php echo ($result['adatlap'] == "magán"?"selected":"") ?> value = "magán">Magán</option>
			<option <?php echo ($result['adatlap'] == "biztosítós"?"selected":"") ?> value = "biztosítós">Biztosítós</option>
			<option <?php echo ($result['adatlap'] == "menedzser"?"selected":"") ?> value = "menedzser">Menedzser</option>
		</select></td></tr>
			   
<tr><td align = "right"><strong>Vizsgálat:</strong>&nbsp;</td>
<?php
$vizsgalatok = explode(", ",$result['vizsgalat']);
?>		
	<td>
	<!--Üzemorvosi vizsgálatok-->
		<table class = "adatlapok" id = "üzemorvosi" <?php echo ($result['adatlap'] == "üzemorvosi"?"":"style = 'display:none'") ?>>
			<tr><td><input type = "checkbox" <?php echo (in_array("Időszakos",$vizsgalatok)?"checked":"") ?> name = "Időszakos" value = "1" /></td><td>Időszakos</td>
				<td><input type = "checkbox" <?php echo (in_array("Előzetes",$vizsgalatok)?"checked":"") ?> name = "Előzetes" value = "1" /></td><td>Előzetes</td>
				<td><input type = "checkbox" <?php echo (in_array("Soronkívüli",$vizsgalatok)?"checked":"") ?> name = "Soronkívüli" value = "1" /></td><td>Soronkívüli</td></tr>
		</table >
	<!--Magán ügyfél vizsgálatok-->		
		<table class = "adatlapok" id = "magán" <?php echo ($result['adatlap'] == "magán"?"":"style = 'display:none'") ?>>
			<tr><td><input type = "checkbox" <?php echo (in_array("AV_doppler",$vizsgalatok)?"checked":"") ?> name = "AV_doppler" value = "1" /></td><td>AV doppler</td>
				<td><input type = "checkbox" <?php echo (in_array("ABPM",$vizsgalatok)?"checked":"") ?> name = "ABPM" value = "1" /></td><td>ABPM</td>
				<td><input type = "checkbox" <?php echo (in_array("Belgyógyászat",$vizsgalatok)?"checked":"") ?> name = "Belgyógyászat" value = "1" /></td><td>Belgyógyászat</td></tr>
			
			<tr><td><input type = "checkbox" <?php echo (in_array("Bőrgyógyászat",$vizsgalatok)?"checked":"") ?> name = "Bőrgyógyászat" value = "1" /></td><td>Bőrgyógyászat</td>
				<td><input type = "checkbox" <?php echo (in_array("Csontsűrűség_mérés",$vizsgalatok)?"checked":"") ?> name = "Csontsűrűség_mérés" value = "1" /></td><td>Csontsűrűség mérés</td>
				<td><input type = "checkbox" <?php echo (in_array("EKG",$vizsgalatok)?"checked":"") ?> name = "EKG" value = "1" /></td><td>EKG</td></tr>
			
			<tr><td><input type = "checkbox" <?php echo (in_array("Fül-orr-gégészet",$vizsgalatok)?"checked":"") ?> name = "Fül-orr-gégészet" value = "1" /></td><td>Fül-orr-gégészet</td>
				<td><input type = "checkbox" <?php echo (in_array("Holter",$vizsgalatok)?"checked":"") ?> name = "Holter" value = "1" /></td><td>Holter</td>
				<td><input type = "checkbox" <?php echo (in_array("Kardiológia",$vizsgalatok)?"checked":"") ?> name = "Kardiológia" value = "1" /></td><td>Kardiológia</td></tr>
			
			<tr><td><input type = "checkbox" <?php echo (in_array("Mellkas_-_RTG",$vizsgalatok)?"checked":"") ?> name = "Mellkas_-_RTG" value = "1" /></td><td>Mellkas - RTG</td>
				<td><input type = "checkbox" <?php echo (in_array("Mozgásszervi_rehabilitáció",$vizsgalatok)?"checked":"") ?> name = "Mozgásszervi_rehabilitáció" value = "1" /></td><td>Mozgásszervi rehabilitáció</td>
				<td><input type = "checkbox" <?php echo (in_array("Neurológia",$vizsgalatok)?"checked":"") ?> name = "Neurológia" value = "1" /></td><td>Neurológia</td></tr>
			
			
			<tr><td><input type = "checkbox" <?php echo (in_array("Nőgyógyászat",$vizsgalatok)?"checked":"") ?> name = "Nőgyógyászat" value = "1" /></td><td>Nőgyógyászat</td>
				<td><input type = "checkbox" <?php echo (in_array("Ortopédia",$vizsgalatok)?"checked":"") ?> name = "Ortopédia" value = "1" /></td><td>Ortopédia</td>
				<td><input type = "checkbox" <?php echo (in_array("Szemészet",$vizsgalatok)?"checked":"") ?> name = "Szemészet" value = "1" /></td><td>Szemészet</td></tr>
			
			<tr><td><input type = "checkbox" <?php echo (in_array("Ultrahang",$vizsgalatok)?"checked":"") ?> name = "Ultrahang" value = "1" /></td><td>Ultrahang</td>
				<td><input type = "checkbox" <?php echo (in_array("Urológia",$vizsgalatok)?"checked":"") ?> name = "Urológia" value = "1" /></td><td>Urológia</td>
				<td><input type = "checkbox" <?php echo (in_array("Vérvétel_/_labor",$vizsgalatok)?"checked":"") ?> name = "Vérvétel_/_labor" value = "1" /></td><td>Vérvétel / labor</td></tr>
		</table>
	<!--Biztosítós vizsgálatok-->	
		<table class = "adatlapok" id = "biztosítós" <?php echo ($result['adatlap'] == "biztosítós"?"":"style = 'display:none'") ?>>
			<tr><td><input type = "checkbox" <?php echo (in_array("Aegon",$vizsgalatok)?"checked":"") ?> name = "Aegon" value = "1" /></td><td>Aegon</td>
				<td><input type = "checkbox" <?php echo (in_array("Allianz",$vizsgalatok)?"checked":"") ?> name = "Allianz" value = "1" /></td><td>Allianz</td>
				<td><input type = "checkbox" <?php echo (in_array("Ergo",$vizsgalatok)?"checked":"") ?> name = "Ergo" value = "1" /></td><td>Ergo</td></tr>
			<tr><td><input type = "checkbox" <?php echo (in_array("Groupama",$vizsgalatok)?"checked":"") ?> name = "Groupama" value = "1" /></td><td>Groupama</td>
				<td><input type = "checkbox" <?php echo (in_array("KandH",$vizsgalatok)?"checked":"") ?> name = "KandH" value = "1" /></td><td>K&amp;H</td>
				<td><input type = "checkbox" <?php echo (in_array("Metlife",$vizsgalatok)?"checked":"") ?> name = "Metlife" value = "1" /></td><td>Metlife</td></tr>
			<tr><td><input type = "checkbox" <?php echo (in_array("Union",$vizsgalatok)?"checked":"") ?> name = "Union" value = "1" /></td><td>Union</td>
				<td><input type = "checkbox" <?php echo (in_array("Signál",$vizsgalatok)?"checked":"") ?> name = "Signál" value = "1" /></td><td>Signál</td>
				<td><input type = "checkbox" <?php echo (in_array("Vienna_Life",$vizsgalatok)?"checked":"") ?> name = "Vienna_Life" value = "1" /></td><td>Vienna Life</td></tr>
			<tr><td><input type = "checkbox" <?php echo (in_array("Cig_Pannónia",$vizsgalatok)?"checked":"") ?> name = "Cig_Pannónia" value = "1" /></td><td>Cig Pannónia</td>
				<td><input type = "checkbox" <?php echo (in_array("Uniqa",$vizsgalatok)?"checked":"") ?> name = "Uniqa" value = "1" /></td><td>Uniqa</td></tr>
		</table>
	<!--Menedzser vizsgálatok-->	
		<table class = "adatlapok" id = "menedzser" <?php echo ($result['adatlap'] == "menedzser"?"":"style = 'display:none'") ?>>
			<tr><td><input type = "checkbox" <?php echo (in_array("Menedzserszűrés_-_Alap",$vizsgalatok)?"checked":"") ?> name = "Menedzserszűrés_-_Alap" value = "1" /></td><td>Menedzserszűrés - Alap</td>
				<td><input type = "checkbox" <?php echo (in_array("Menedzserszűrés_-_Emelt",$vizsgalatok)?"checked":"") ?> name = "Menedzserszűrés_-_Emelt" value = "1" /></td><td>Menedzserszűrés - Emelt</td>
				<td><input type = "checkbox" <?php echo (in_array("Menedzserszűrés_-_Top",$vizsgalatok)?"checked":"") ?> name = "Menedzserszűrés_-_Top" value = "1" /></td><td>Menedzserszűrés - Top</td></tr>
		</table>
	</td></tr>
	
<tr><td colspan = "2" style = "width:100%;border-bottom:2px solid #444"><input type = "hidden" name = "vizsgalat" value = "<?php echo $result['vizsgalat'] ?>"/></td></tr>			   
<tr><td align = "right"><strong>Adatküldés:</strong>&nbsp;</td>		 
	<td style = "text-align:left"><input type = "checkbox" <?php echo ($result['adatkuldes'] == 1?" checked":"") ?> 
										 value = "1" 
										 name = "adatkuldes" /></td></tr>
										 
<tr><td align = "right"><strong>Labor:</strong>&nbsp;</td>			 
	<td align = "left"><input type = "checkbox" <?php echo ($result['labor'] == 1?" checked":"") ?> 
							  value = "1" 
							  name = "labor" /></td></tr>
							  
<tr><td align = "right"><strong>Tájékoztató:</strong>&nbsp;</td>	 
	<td align = "left"><input type = "checkbox" <?php echo ($result['tajekoztato'] == 1?" checked":"") ?> 
							  value = "1" 
							  name = "tajekoztato" /></td></tr>


<tr><td colspan = "2" align = "center"><button class = "ujbutton" style = "text-transform:none" <?php echo $onClick ?> name = "gdpr-modify" type = "submit">Módosítás</button>&nbsp;
									   <button class = "ujbutton" style = "text-transform:none" name = "<?php echo $submitButton['name'] ?>" type = "submit"><?php echo $submitButton['value'] ?></button>&nbsp;

</td></tr>
</table>

<div style = "display:inline-block;float:left;margin-left:20px">
	<iframe style = "width:700px;height:600px" <?php echo $sidePDF ?>></iframe>
</div>
</form>