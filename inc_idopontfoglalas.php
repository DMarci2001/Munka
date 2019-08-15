<?php
if( $_SESSION["helyszindata"]["id"] != 11 ) header( "Location:index.php" );

if(isset($_SESSION['previousUser'])) $result = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE id = ? ", array( $_SESSION['previousUser'] )));
?>
<div class = "fejlecdiv" style = "background-color:#9d0102">HungáriaMed M - Időpont foglalás</div>
<?php if( isset( $formerror ) && $formerror != "" )
{
	echo '<div style = "background-color:red;color:white;text-align:center;font-size:15px;margin-top:-10px;margin-bottom:10px">';
	echo 	$formerror;
	echo '</div>';
} ?>
<div style = "position:relative;">	
<form method = "POST" name = "booking">
<input type="hidden" name = "helyszin" value="1" />
<input type="hidden" name = "version2">
<input type="hidden" name="idopontfoglalasV2" value="1" >
<div style = "min-height:180px;min-width:1100px">
	<div style = "display:inline-block;float:left;padding-top:3px;">
		<table class = "booking-form-table">
			<tr><td align = "right">Kiválasztott szűrővizsgálat: </td><td>
				<?php if( !isset( $_POST['tipus'] )) $_POST['tipus'] = 0 ?>
				<?php if( !isset( $_POST['idopont'] )) $_POST['idopont'] = "" ?>
				<?php echo szuresTipusValasztoNewV2( 1, $_POST['tipus'] ) ?>
			</td></tr>
			<tr><td align = "right">Időpont: </td><td>
				<input class = "design-put" style = "width:133px" type = "textbox" placeholder = "Válassz!" name = "datum" readonly value = "<?php echo $_POST['idopont'] ?>"  />
				<?php $onClick = "onClick = 'showIdoPontValasztoV4($(\"#szurestipus\").val(),0,0);return false' " ?>
				<button class = "finishButton" <?php echo $onClick ?> style = "width:130px">Foglalás</button>
			<td></td></td></tr>
			<tr><td></td><td align = "center" id = "datum-error" style="color:red"></td></tr>
			<tr><td colspan = "2" style = "font-size:11px">Jelmagyarázat: * (A csillaggal jelölt adatok kötelezőek!)</td></tr>
		</table>
	</div>
	<div style = "display:inline-block;float:left">
		<div class = "state-bar-wrapper">
			<div class = "state-bar-container">
				<div class = "percent-box-container">
					<div class = "outer-percent-box">
						<div class = "inner-percent-box"><span>25</span>%</div>
						<div class = "down-triangle-box"></div>
					</div>
				</div>
				<div class = "state-bar">
					<div class = "green-bar"></div>
				</div>
			</div>
		</div>
	</div>
</div>

<div style = "overflow:hidden;width:1100px;">
	<div id = "forms-wrapper" style = "width:4000px;min-height:450px;position:relative">
		<div id = "page-01" style = "position:absolute;color:black">
			<span style = "font-family: Montserrat;font-size:20px;display:block">1. Lépés - Azonosítás</span>
			<span style = "font-family: Montserrat;font-size:14px;display:block;margin:10px 0 0 10px">Az Ön azonosítása érdekében kérjük el az alábbi adatokat.<br/> Rendszerünk e-mail és SMS értesítést küld az időpont foglalással kapcsolatban.</span>
			<table class = "booking-form-table" style = "margin-top:20px">
				<tr><td align = "right">Teljes név:* </td><td><input type = "textbox" value = "<?php echo (isset($result['nev'])?$result['nev']:"") ?>" name = "nev" value = "<?php echo $result['nev'] ?>" class = "design-put" /></td ><td id = "nev-error" style = "color:red"></td></tr>
				<tr><td align = "right">E-mail:* </td><td><input type = "textbox" name = "email" value = "<?php echo (isset($result['email'])?$result['email']:"") ?>" class = "design-put" /></td><td id = "email-error" style = "color:red"></td></tr>
				<tr><td align = "right">Telefonszám:* </td><td><input type = "textbox" value = "<?php echo (isset($result['telefon'])?$result['telefon']:"") ?>" name = "tel" class = "design-put" /></td><td id = "tel-error" style = "color:red"></td></tr>
				<!--<tr><td align = "right">TAJ szám:* </td><td><input type = "textbox" name = "taj" class = "design-put" /></td><td id = "taj-error" style = "color:red"></td></tr>-->
				<tr><td align = "right">Születési dátum:* </td><td><?php echo (isset($result['szuldatum'])?datumSelector($result['szuldatum'],"szuldatum","design-put"):datumSelector("0-0-0","szuldatum","design-put")) ?></td><td id = "szuldatum-error" style = "color:red"></td></tr>
				<!--<tr><td align = "right">Neme:* </td><td>
					Férfi <input type = "radio" checked name = "neme" value = "1" />
					Nő <input type = "radio" name = "neme" value = "2" />
				</td></tr>-->
			</table>
		</div>
		
		<div id = "page-02" style = "position:absolute;left:1100px;width:752px">
			<span style = "font-family: Montserrat;font-size:20px;display:block">Utolsó Lépés - Foglalás befejezése</span>
			<span style = "font-family: Montserrat;font-size:14px;display:block;width:632px;margin:10px 0 0 10px;border-bottom:1px solid black;padding-bottom:10px">Kérem <b>ellenőrízze mégegyszer</b> a megadott adatok helyességét,<br/>ha minden adat helytálló, nyomja meg a <b>"Befejezés"</b> gombot a <b>foglalás</b> véglegesítéséhez!</span>
			<table class = "data-check-table" style = "margin-top:20px;display:inline-block">
				<tr><td align = "right">Név: </td><td name = "name"></td></tr>
				<tr><td align = "right">E-mail: </td><td name = "mail"></td></tr>
				<tr><td align = "right">Telefon szám: </td><td name = "phone"></td></tr>
				<tr><td align = "right">TAJ szám: </td><td name = "ssi"></td></tr>
				<tr><td align = "right">Szül. dátum: </td><td name = "birth-date"></td></tr>
				<tr><td align = "right">Időpont</td><td name = "idopont"></td></tr>
				<tr><td colspan = "2" id="captcha-error" style = "padding-top:20px;border: 1px solid rgba(0,0,0,0);"><div class="g-recaptcha" data-callback="recaptchaCallback" data-sitekey="6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG"></div></td></tr>
				<!--<tr><td align = "right">Cím: </td><td name = "address"></td></tr>-->
			</table>
			<table class = "booking-form-table" style = "display:inline-block;min-height:277px;margin-left:20px">
				<tr><td style = "font-size:13px;color:#444">Bármilyen egyéb információ amit<br/> szeretne megosztani még:</td></tr>
				<tr><td style = "max-width:363px;max-height:189px"><textarea name = "megj" id = "foglmegj" class = "design-put" style = "max-width:363px;max-height:189px"></textarea></td></tr>
				<tr><td style = "border-bottom:2px solid #444;padding-right:10px">Kuponkód:</td></tr>
				<tr><td>
						<div id = "couponCheck-wrapper" style = "width:300px">
							<div>
								<input type = "textbox" class = "design-put" style = "width:120px" name = "kuponkod" id = "kuponCheck" placeholder = "Kuponkód" />
								<input type = "hidden" id = "current-datetime" value = "<?php echo $_POST['idopont'] ?>"/>
                                <input type = "hidden" id = "current-type" value = "<?php echo $_POST['tipus'] ?>"/>
                                <input type='hidden' name='orvosselected' id='orvosselected' value='<?= $_SESSION['orvosselected'] ?>'/>
								<a class = "ujbutton" style = "text-transform: uppercase" href= "#" onClick = 'kuponCheck($("#kuponCheck").val(),1,$("#current-datetime").val(),$("#current-type").val());return false' >Ellenőrzés</a>
							</div>
							<div>
								<p style = "font-family:Montserrat;font-size:14px;text-transform:uppercase" id = "coupontitle"></p>
								<p style = "font-family:montserrat;font-size:12px;font-weight:bold;" id = "coupondiscount"></p>
								<p style = "font-family:Montserrat;color:#12c915;font-size:12px;text-align:justify" id = "coupondesc"></p>
							</div>
						</div>
					</td></tr>
			</table>
			<table><tr><td id = "aszf"><input type = "checkbox" name = "aszf" value = "1"></td><td><a href = "https://hungariamed.hu/images/adatkezeles.pdf" >Adatvédelmi nyilatkozatot</a>&nbsp;elfogadom</td><td name = "aszf-error" style = "color:red;padding-left:30px"></td></tr></table>
		</div>
		
		<!--<div id = "page-02" style = "position:absolute;left:1100px">
			<span style = "font-family: Montserrat;font-size:20px;display:block">2. Lépés - Személyes adatok</span>
			<span style = "font-family: Montserrat;font-size:14px;display:block;margin:10px 0 0 10px"></span>
			<table class = "booking-form-table" style = "margin-top:20px">
				<tr><td align = "right">Születési dátum:* </td><td><?php echo datumSelector("0-0-0","szuldatum","design-put"); ?></td><td id = "szuldatum-error" style = "color:red"></td></tr>
				<tr><td align = "right">Születési hely: </td><td><input type = "textbox" name = "szulhely" class = "design-put" /></td></tr>
				<tr><td align = "right">Anyja neve: </td><td><input type = "textbox" name = "anyjaneve" class = "design-put" /></td></tr>
				<tr><td align = "right">Neme:* </td><td>
					Férfi <input type = "radio" checked name = "neme" value = "1" />
					Nő <input type = "radio" name = "neme" value = "2" />
				</td></tr>
			</table>
		</div>
		
		<div id = "page-03" style = "position:absolute;left:2200px">
			<span style = "font-family: Montserrat;font-size:20px;display:block">3. Lépés - Cím adatok</span>
			<span style = "font-family: Montserrat;font-size:14px;display:block;margin:10px 0 0 10px">Az ön azonosítása érdekében kérjük el ezeket az adatokat<br/> és mert a későbbiekben a visszaigazolás ás ártesítési üzeneteket küldünk az ön foglalásával kapcsolatba.</span>
			<table class = "booking-form-table" style = "margin-top:20px">
				<tr><td align = "right">Irányítószám: </td><td><input type = "textbox" name = "irsz" class = "design-put" /></td></tr>
				<tr><td align = "right">Város: </td><td><input type = "textbox" name = "varos" class = "design-put" /></td></tr>
				<tr><td align = "right">Utca: </td><td><input type = "textbox" name = "utca" class = "design-put" /></td></tr>
				<tr><td align = "right">Megjegyzés: </td><td><input type = "textbox" name = "megj" class = "design-put" /></td></tr>
			</table>
		</div>-->
		<!--<div id = "page-04" style = "position:absolute;left:3300px">
			<span style = "font-family: Montserrat;font-size:20px;display:block">Utolsó Lépés - Foglalás befejezése</span>
			<span style = "font-family: Montserrat;font-size:14px;display:block;margin:10px 0 0 10px;border-bottom:1px solid black;padding-bottom:10px">Kérem <b>ellenőrízze le mégegyszer</b> a megadott adatok helyességét lentebb,<br/>ha minden adat helytálló, nyomja meg a <b>"Befejezés"</b> gombot a <b>foglalás</b> véglegesítéséhez!</span>
			<table class = "data-check-table" style = "margin-top:20px;">
				<tr><td align = "right">Név: </td><td name = "name"></td></tr>
				<tr><td align = "right">E-mail: </td><td name = "mail"></td></tr>
				<tr><td align = "right">Telefon szám: </td><td name = "phone"></td></tr>
				<tr><td align = "right">TAJ szám: </td><td name = "ssi"></td></tr>
				<tr><td align = "right">Szül. dátum: </td><td name = "birth-date"></td></tr>
				<tr><td align = "right">Cím: </td><td name = "address"></td></tr>
			</table>
		</div>-->
	</div>
</div>
<div>
	<div id = "adatvedelem" style = "display:none;">
	<?php echo getASZF(); ?>
	</div>
	<table style = "margin-top:20px">
		<tr><td style = "padding-right:80px;padding-left:80px"><button class = "backButton" style = "visibility:hidden" onClick = 'return false' name = "back">Vissza</button></td>
			<td><div class = "captchaWrap"><button class = "forwardButton" onClick = 'return false' name = "forward">Tovább</button></div></td></tr>
	</table>
</div>
</form>
</div>