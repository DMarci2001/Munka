<?php

if(isset($_REQUEST['open_new_lelet'])){
	
$textarea_name = "uj-lelet-page";

$patient = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE id=?",array($_SESSION["patient_id"])));
$medic = sql_fetch_array(sql_query("SELECT * FROM orvosok WHERE id=?",array($_SESSION['medic_id'])));

$patient_details_segment  = "<h1 style = 'font-family:Times New Roman;text-align:center;color:#000000;font-weight:bold;'>Lelet</h1>
							 <span id = 'patient-details'>
							 <span style = 'font-family:Times New Roman;font-weight:bold;text-decoration:underline;font-size:18px;color:#000000;display:inline;'>
							 Beteg adatai:</span><br/>
							 <span style = 'margin-left:0px;display:inline;color:#000000;'>
							 Páciens neve:&emsp;&emsp;&emsp;&emsp;&emsp;".$patient['nev']."</span><br/>
							 <span style = 'margin-left:0px;display:inline;color:#000000;'>
							 Születési hely, idő:&emsp;&emsp;&nbsp;".$patient['szulhely'].", ".$patient['szuldatum']."</span><br/>
							 <span style = 'margin-left:0px;display:inline;color:#000000;'>
							 TAJ szám:&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&ensp;".$patient['taj']."</span><br/>
							 <span style = 'margin-left:0px;display:inline;color:#000000;'>
							 Anyja neve:&emsp;&emsp;&emsp;&emsp;&emsp;&ensp;&nbsp;".$patient['anyjaneve']."</span><br/>
							 <span style = 'margin-left:0px;display:inline;color:#000000;'>
							 Leánykori neve:&emsp;&emsp;&emsp;&emsp;</span><br/>
							 <span style = 'margin-left:0px;display:inline;color:#000000;'>
							 Lakcíme:&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;".$patient['irsz']." ".$patient['varos'].", ".$patient['utca']."</span><br/>
							 </span><br/>
							";

$medical_seals = "&lt;span style = 'color:#000000'&gt;
				  ".date("Y.m.d",strtotime("Now"))."&lt;br/&gt;&lt;br/&gt;
				  &lt;span style='float:right;display:inline;'&gt;
				  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .&lt;br/&gt;
				  &lt;span style='float:right;display:inline;'&gt;".$medic['nev']."(&lt;span id='seal-place'&gt;&lt;/span&gt;)&lt;/span&gt;&lt;br/&gt;
				  &lt;span style='float:right;display:inline;font-size:11px;color:#949494'&gt; *A lelet aláírás és pecsét nélkül is érvényes! &lt;/span&gt;
				  &lt;/span&gt;&lt;br/&gt;&lt;br/&gt;&lt;br/&gt;";

$lelet = sql_fetch_array(sql_query("SELECT * FROM lelet_mintak WHERE lm_id = 4 "));
?>
<script>
tinyMCE.init({
        mode : 'specific_textareas',
        editor_selector : 'mceEditor',
		height: 842,
		width: 595
});
</script>
<div style = "margin-top:5px;">
	<div class = "currently-text-container" style = "display:none;"></div>
	<div class = "medic-footage" style = "display:none;">"<?php echo $medical_seals ?>"</div>
	<table style = "font-size:12px;margin-bottom:10px;">
		<tr>
			<td>Pecsétszám:</td>
			<td><input type = "textbox" id = "pecsetszam" /></td>
		</tr>
	</table>
	 
	
	Milyen vizsgálati eredményt kíván hozzáadni?<br/>
	
	<select id = "minta-lista" style = "margin-top:10px;">
		<option value = "empty"> - Válassz mintát! - </option>
		<?php
		$request_mintak = sql_query("SELECT * FROM lelet_mintak");
		while($minta = sql_fetch_array($request_mintak)){
			?>
			<option value = "<?php echo $minta['lm_id'] ?>"><?php echo $minta['lelet_nev'] ?></option>
			<?php
		}
		?>
	</select>
	<input onClick = 'add_lelet($("#minta-lista").val(),"<?php echo $textarea_name ?>")' type = "button" value = "Hozzáadás"/>
	<br/><br/>
</div>

<!--Lelet szöveg helye-->
<textarea id = "<?php echo $textarea_name ?>" class = "mceEditor" style = "margin-top:10px;">
<?php echo $patient_details_segment?>
</textarea>

<div style = "margin-top:10px;">
<input value = "Lelet mentése" onClick = 'save_iFrame(<?php echo $_SESSION['patient_id'].",".$_SESSION['medic_id'].",\"".$textarea_name."\"" ?>)' type = "button"/>
<input value = "Nyomtatás" onClick = 'send_iFrame(<?php echo $_SESSION['patient_id'].",".$_SESSION['medic_id'].",\"".$textarea_name."\"" ?>)' type = "button" />
<input value = "Mégse" onClick = '$("#leletform").slideToggle();$("#leletbutton").find("input[type=\"button\"]").css("display","block");' type = "button" />
</div>
<?php
die();
}

if(isset($_REQUEST['open_lelet'])){
	$lelet_id = $_REQUEST['open_lelet'];
	
	$textarea_name = "lelet-page-".$lelet_id;
	

	$lelet = sql_fetch_array(sql_query("SELECT * FROM paciens_leletek WHERE lelet_id=?",array($lelet_id)));
	$vizsgalatok = "";
	if(strpos($lelet['lelet_szoveg'], 'hasi-ultrahang') !== false){
		$vizsgalatok = $vizsgalatok."<span> - Hasi-ultrahang&ensp;<i style = 'color:#76f200;' class='fa fa-check'></i></span>";
	}
	if(strpos($lelet['lelet_szoveg'], 'nyaki-ultrahang') !== false){
		$vizsgalatok = $vizsgalatok."<span> - Nyaki-ultrahang&ensp;<i style = 'color:#76f200;font-size:16px;' class='fa fa-check'></i></span>";
	}
	if(strpos($lelet['lelet_szoveg'], 'emlo-ultrahang') !== false){
		$vizsgalatok = $vizsgalatok."<span> - Emlő-ultrahang&ensp;<i style = 'color:#76f200;font-size:16px;' class='fa fa-check'></i></span>";
	}
	if(strpos($lelet['lelet_szoveg'], 'here-ultrahang') !== false){
		$vizsgalatok = $vizsgalatok."<span> - Here-ultrahang&ensp;<i style = 'color:#76f200;font-size:16px;' class='fa fa-check'></i></span>";
	}
	if(strpos($lelet['lelet_szoveg'], 'echocardiographia') !== false){
		$vizsgalatok = $vizsgalatok."<span> - Echocardiographia&ensp;<i style = 'color:#76f200;font-size:16px;' class='fa fa-check'></i></span>";
	}
	if(strpos($lelet['lelet_szoveg'], 'szemeszet') !== false){
		$vizsgalatok = $vizsgalatok."<span> - Szemészet&ensp;<i style = 'color:#76f200;font-size:16px;' class='fa fa-check'></i></span>";
	}
	if(strpos($lelet['lelet_szoveg'], 'borvizsgalat') !== false){
		$vizsgalatok = $vizsgalatok."<span> - Bőrvizsgálat&ensp;<i style = 'color:#76f200;font-size:16px;' class='fa fa-check'></i></span>";
	}
	if(strpos($lelet['lelet_szoveg'], 'mozgasszervi-vizsgalat') !== false){
		$vizsgalatok = $vizsgalatok."<span> - Mozgásszervi-vizsgálat&ensp;<i style = 'color:#76f200;font-size:16px;' class='fa fa-check'></i></span>";
	}
	
	
	

$medical_seals = "&lt;span style = 'color:#000000'&gt;
				  ".date("Y.m.d",strtotime("Now"))."&lt;br/&gt;&lt;br/&gt;
				  &lt;span style='float:right;display:inline;'&gt;
				  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .&lt;br/&gt;
				  &lt;span style='float:right;display:inline;'&gt;".$medic['nev']."(&lt;span id='seal-place'&gt;&lt;/span&gt;)&lt;/span&gt;&lt;br/&gt;
				  &lt;span style='float:right;display:inline;font-size:11px;color:#949494'&gt; *A lelet aláírás és pecsét nélkül is érvényes! &lt;/span&gt;
				  &lt;/span&gt;&lt;br/&gt;&lt;br/&gt;&lt;br/&gt;";
?>
<script type="text/javascript">
tinyMCE.init({
        mode : 'specific_textareas',
        editor_selector : 'mceEditor',
		height: 842,
		width: 595
});

$(document).ready(function(){
	var list_content  = '<h2 style = "margin-bottom:5px;">Leleten szereplő vizsgálatok:</h2><br/>';
		list_content += "<?php echo $vizsgalatok ?>";
	$('#leletform').find('#vizsgalati-lista').append(list_content);
});

</script>
<div style = "margin-top:5px;">
	<div class = "currently-text-container" style = "display:none;"></div>
	<div class = "medic-footage" style = "display:none;">"<?php echo $medical_seals ?>"</div>
	<table style = "font-size:12px;margin-bottom:10px;">
		<tr>
			<td>Pecsétszám:</td>
			<td><input type = "textbox" id = "pecsetszam" /></td>
		</tr>
	</table>
	 
	
	Milyen vizsgálati sablont kíván hozzáadni?<br/>
	
	<select id = "minta-lista" style = "margin-top:10px;">
		<option value = "empty"> - Válassz mintát! - </option>
		<?php
		$request_mintak = sql_query("SELECT * FROM lelet_mintak");
		while($minta = sql_fetch_array($request_mintak)) {
			?>
			<option value = "<?php echo $minta['lm_id'] ?>"><?php echo $minta['lelet_nev'] ?></option>
			<?php
		}
		?>
	</select>
	<input onClick = 'add_lelet($("#minta-lista").val(),"<?php echo $textarea_name ?>")' type = "button" value = "Hozzáadás"/>
	<br/><br/>
</div>

<!--Lelet szöveg helye-->
<textarea id = "<?php echo $textarea_name ?>" class = "mceEditor" style = "margin-top:10px;display:inline-block;">
<?php echo $lelet['lelet_szoveg'] ?>
</textarea>
<div id = "vizsgalati-lista"></div>

<div style = "margin-top:10px;">
<input value = "Lelet mentése" onClick = 'save_iFrame(<?php echo $_SESSION['patient_id'].",".$_SESSION['medic_id'].",\"".$textarea_name."\"" ?>)'  type = "button"/>
<input value = "Nyomtatás" onClick = 'send_iFrame(<?php echo $_SESSION['patient_id'].",".$_SESSION['medic_id'].",\"".$textarea_name."\"" ?>)' type = "button" />
<input value = "Mégse" onClick = '$("#leletform").slideToggle();$("#leletbutton").find("input[type=\"button\"]").css("display","block");' type = "button" />
</div>
<?php
die();
}

if (isset($_REQUEST['reload_leletlista'])) {
	echo leletLista($_SESSION["patient_id"]);
	die();
}


function leletLista($pid) {
	$htmlout="";
	$request_leletek = sql_query("SELECT * FROM paciens_leletek WHERE paciens_id=?",array($pid));

	if (sql_num_rows($request_leletek) > 0) {
		while ($lelet = sql_fetch_array($request_leletek)) {
			$htmlout.="<div><a onClick='open_lelet({$lelet["lelet_id"]});return false;' href='#'>Lelet - ".date("Y-m-d",strtotime($lelet['kelte']))."</a>";
			if ($_SESSION["adminuser"]["jogosultsag"]>=2) $htmlout.=" [<a onclick='return confirm(\"Biztosan törli ezt a leletet?\");' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}&deletelelet={$lelet["lelet_id"]}'>törlés</a>]";
			$htmlout.="</div>";
		}
	}	else {
		$htmlout.="Nincs még lelet kiállítva.";
	}
	return $htmlout;		
}


if(isset($_REQUEST['request_lelet'])){
	$lelet = sql_fetch_array(sql_query("SELECT * FROM lelet_mintak WHERE lm_id=?",array($_REQUEST['request_lelet'])));
	echo $lelet['lelet_text'];
	die();
}


if(isset($_REQUEST['save_lelet'])){
	sql_query("INSERT INTO paciens_leletek(paciens_id,lelet_szoveg,kelte) VALUES(?,?,NOW())",array($_SESSION['patient_id'],$_REQUEST['save_lelet']));
	die("Lelet feltöltés sikeres!");	
}


if(isset($_REQUEST['update_lelet'])){
	sql_query("UPDATE paciens_leletek SET lelet_szoveg=? WHERE lelet_id=?",array($_REQUEST["update_lelet"],$_REQUEST["lid"]));
	die("Lelet módosítás sikeres!");
}



?>