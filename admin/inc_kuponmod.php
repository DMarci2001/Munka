<?php
if(!isset($_GET['id'])) header("Location:index.php?page=kuponok");



function couponGenerator($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $CouponCode = '';
    for ($i = 0; $i < $length; $i++) {
        $CouponCode .= $characters[rand(0, $charactersLength - 1)];
    }
    return $CouponCode;
}

if(isset($_POST['coupon-modify']))
{
	$result = sql_fetch_array(sql_query( "SELECT * FROM kuponkodok WHERE id = ?", array( $_GET['id'] )));
	
	if($result['statusz'] == "inaktiv" && $_POST['statusz'] == "aktiv" && $result['kibocsatas'] == "0000-00-00 00:00:00" )
	{
		$kibocsatas = date("Y-m-d G:i:s");
	}
	else $kibocsatas = $result['kibocsatas'];
	sql_query("UPDATE kuponkodok SET
			   megnev = ?, leiras = ?, kod = ?, kedvezmeny_tipus = ?, kedvezmeny = ?, event_start = ?, 
			   event_end = ?, kibocsatas = ?, statusz = ?, szurestipusok = ?, tipus = ?
			   WHERE id = ?
			  ", array( $_POST['megnev'], $_POST['leiras'], $_POST['kod'], $_POST['kedvezmeny_tipus'], $_POST['kedvezmeny'], 
						$_POST['start'], $_POST['end'], $kibocsatas, $_POST['statusz'], $_POST['szurestipusok'], $_POST['tipus'], $_GET['id'] ));
}

$inputPadding = "style = 'padding: 5px 10px 5px'";


$result = sql_fetch_array(sql_query( "SELECT * FROM kuponkodok WHERE id = ?", array( $_GET['id'] )));
?>
<style>
.gdpr-edit-table td{
	padding:2px;
}
.vizsg-button{
	padding:5px;
	border:1px solid red;
	color:red;
	text-align:center;
	width:80px;
	cursor:pointer
}
.vizsg-button:hover{
	color:white;
	background-color:red;
}
.couponlist{
	width:100%;
	border-collapse: collapse;
	margin-top:20px;
}
.couponlist td{
	padding:10px;
	border:1px solid black;
	color:black;
	font-size:12px;
	font-family:arial;
}
.couponlist tr:first-child td{
	font-size:16px;
	font-weight:bold;
	text-align:center;
}
</style>
<script type = "text/javascript">
$(document).ready(function(){
$(function(){
	$('#start,#end').datepicker({
		dateFormat: 'yy-mm-dd',
		changeMonth: true,
		changeYear: true,
		yearRange: '-100y:c+20',
		maxDate: '+2y'
		});
	
	});
});
function calcNum()
	{
		var sum = 0;
		var szuresek = '';
		$( '.vizsg' ).each(function( index ) {
			if($(this).is(':checked'))
			{
				sum++;
				szuresek+='|'+$(this).val();
			}				
			
		});
		szuresek = szuresek.substring(1);
		$('#vizsgszam').text( sum );
		$('input[name="szurestipusok"]').val();
		$('input[name="szurestipusok"]').val(szuresek);
	}
$(document).on('click','.vizsg-button',function(){
	console.log('itt vagyok!');
});


</script>
<div class = "pagehead">
	<div style="display:table-cell;vertical-align:middle;"> KUPON - <?php echo $result['megnev'] ?></div>
	<div style="display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px"><a class = "ujbutton" href="index.php?page=kuponok">Vissza</a></div>
</div>
<form method = "POST">
<input type = "hidden" name = "kibocsatas" value = "<?php echo $result['kibocsatas'] ?>"/>
<table class = "gdpr-edit-table" style = "font-family: Montserrat;">
	<tr><td align = "right"><strong>Kupon név:</strong>&nbsp;</td><td><input type = "textbox" <?php echo $inputPadding ?> value = "<?php echo $result['megnev'] ?>" class = "design-put" name = "megnev" /></td></tr>
	<tr><td align = "right"><strong>Kupon kód:</strong></td><td><input type = "textbox" class = "design-put" <?php echo $inputPadding ?> name = "kod" value = "<?php echo $result['kod'] ?>" /></td></tr>
	<tr><td align = "right"><strong>Kedv. típus:</strong>&nbsp;</td>
		<td>
			<select name = "kedvezmeny_tipus" <?php echo $inputPadding ?> class = "design-put">
				<option <?php echo ($result['kedvezmeny_tipus'] == "szazalek"?"selected":"") ?> value = "szazalek"> % </option>
				<option <?php echo ($result['kedvezmeny_tipus'] == "fix"?"selected":"") ?> value = "fix"> Fix </option>
			</select>
		</td></tr>
	<tr><td align = "right"><strong>Kedvezmény:</strong>&nbsp;</td><td><input type = "textbox" <?php echo $inputPadding ?> value = "<?php echo $result['kedvezmeny'] ?>" class = "design-put" name = "kedvezmeny" /></td></tr>
	<tr><td align = "right"><strong>Kezdete:</strong>&nbsp;</td><td><input type = "textbox" <?php echo $inputPadding ?> value = "<?php echo date("Y-m-d",strtotime($result['event_start'])) ?>" class = "design-put" id = "start" name = "start" /></td></tr>
	<tr><td align = "right"><strong>Vége:</strong>&nbsp;</td><td><input type = "textbox" <?php echo $inputPadding ?> value = "<?php echo date("Y-m-d",strtotime($result['event_end'])) ?>" class = "design-put" id = "end" name = "end" /></td></tr>
	<tr><td align = "right"><strong>Státusz:</strong>&nbsp;</td>
		<td>
			<select name = "statusz" <?php echo $inputPadding ?> class = "design-put">
				<option <?php echo ($result['statusz'] == "aktiv"?"selected":"") ?> value = "aktiv">Aktív</option>
				<option <?php echo ($result['statusz'] == "inaktiv"?"selected":"") ?> value = "inaktiv">Inaktív</option>
			</select>
		</td></tr>
	<tr><td align = "right"><strong>Típus:</strong>&nbsp;</td>
		<td>
			<select name = "tipus" <?php echo $inputPadding ?> class = "design-put">
				<option <?php echo ($result['tipus'] == "Egyszer"?"selected":"") ?> value = "Egyszer">Egyszer</option>
				<option <?php echo ($result['tipus'] == "Többször"?"selected":"") ?> value = "Többször">Többször</option>
			</select>
		</td></tr>
	<tr><td align = "right"><strong>Vizsgálatok:</strong>&nbsp;</td>
		<td>
			<?php
			$szuresArr = explode("|",$result['szurestipusok']);
			?>
			<input type = "hidden" name = "szurestipusok" value = "<?php echo $result['szurestipusok'] ?>">
			<div class = "vizsg-button" onClick = '$(".szurestipusBox").slideToggle();calcNum();'><span id = "vizsgszam"><?php echo ($result['szurestipusok'] == ""?"0":count($szuresArr)) ?></span>. vizsgálat</div>
			<div class = "szurestipusBox" style = "border:1px solid black;width:300px;height:200px;padding:5px;overflow-y:scroll;display:none;">
				<?php
				$htmlout = "";
				$request = sql_query("SELECT * FROM szurestipusok ORDER BY megnev ASC ");
				while( $szures = sql_fetch_array( $request ))
				{
					if (in_array($szures['id'], $szuresArr)) $status = "checked";
					else $status = "";
					$htmlout.= "<div><input class = 'vizsg' {$status} value = '{$szures['id']}' name = 'vizsg[]' type = 'checkbox'/>{$szures['megnev']}</div>";
				}
				echo $htmlout;
				?>
			</div>
		</td></tr>
	<tr><td align = "right"><strong>Leírás:</strong>&nbsp;</td><td><textarea name = "leiras" class = "design-put" <?php echo $inputPadding ?>><?php echo $result['leiras'] ?></textarea></td></tr>
	
	<?php if($_SESSION['adminuser']['jog_kuponkeszites']) { ?>
	<tr>
		<td colspan = "2" align = "center">
			<button class = "ujbutton" name = "coupon-modify" type = "submit">Módosítás</button>
		</td>
	</tr>
	<?php } ?>
	
</table>
</form>

<table style = "width:100%" class = "couponlist">
	<tr>
		<td>ID.</td>
		<td>Kód</td>
		<td>Felhasználó</td>
		<td>Kiadta</td>
		<td>Felhasználva</td>
	</tr>
	<?php
	$htmlout = "";
	$kuponQuery = sql_query( "SELECT * FROM kupon_lista kl LEFT JOIN foglalasok fogl ON fogl.id = kl.foglalasid WHERE kuponid = {$_GET['id']}" );
	while( $coupon = sql_fetch_array( $kuponQuery ))
	{
		$htmlout.= "<tr>";
		$htmlout.= "	<td>{$coupon['id']}</td>";
		$htmlout.= "	<td>{$coupon['kuponkod']}</td>";
		$htmlout.= "	<td>{$coupon['foglalasid']}</td>";
		$htmlout.= "	<td>{$coupon['jovahagyta']}</td>";
		$htmlout.= "	<td>{$coupon['felhasznalva']}</td>";
		$htmlout.= "</tr>";
	}
	if( $kuponQuery->rowCount() == 0 ) {
		$htmlout.= "<tr><td colspan = '5' style = 'font-weight:bold;font-size:16px;text-align:center'>Még nem használták fel!</td></tr>";
	}
	echo $htmlout;
	?>
</table>