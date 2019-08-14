<?php
include("../../config.php");
include("../ajax.php");

$patient = sql_fetch_array( sql_query("SELECT * FROM felhasznalok WHERE id = ?", array( $_REQUEST['szerk'] )));

$taj = chunk_split($patient['taj'], 3, ' - ');
$taj = substr($taj,0,-3);

if(isset($_GET['protocolid']))
{
	$getProtocol = sql_fetch_array( sql_query( "SELECT * FROM labor_sablonok WHERE lab_id = ?", array( $_GET['protocolid'] )));
}
?>
<html>
<title>Labor Protocoll</title>
<header>
<link rel="stylesheet" href="print-style.css" type="text/css" media="print"/>
<link rel="stylesheet" href="style.css" type="text/css" media="screen"/>
<script src="https://code.jquery.com/jquery-latest.js"></script>
<script type="text/javascript" src = "source.js"></script>
</header>
<body>
<div class = "wrapper" style = "float:left;">
	<span class = "page-title">RUTIN VIZSGÁLATOK KÉRŐLAPJA</span>
	<span class = "top-gray-line"></span>
	<!--2018.05.10 13:18-->
	<span class="top-date-box"><i>Dátum:</i> <?php echo date("Y.m.d G:i"); ?></span>
	<table class="patient-details-table" id="PDT-row-01">
		<tr>
			<td><i>Beteg neve:</i>&emsp;&emsp;<strong style = "color:#000;"><?php echo $patient['nev'] ?></strong></td>
			<td><i>TAJ:</i>&emsp;<?php echo $taj ?></td>
		</tr>
	</table>

	<table class="patient-details-table" id="PDT-row-02">
		<tr>
			<td><i>Anyja neve:</i>&emsp;&emsp;<?php echo $patient['anyjaneve'] ?></td>
			<td><i>Leánykori neve:</i></td>
		</tr>
	</table>

	<table class="patient-details-table" id="PDT-row-03">
		<tr>
			<td><i>Neme:</i>&emsp;&emsp;<?php echo ( $patient['neme'] == 1 ? "férfi" : "nő" ) ?></td>
			<td><i>Születési idő:</i>&emsp;<?php echo date( "Y.m.d", strtotime( $patient['szuldatum'] )) ?></td>
		</tr>
	</table>

	<table class="patient-details-table" id="PDT-row-04">
		<tr>
			<td><i>Lakcím:</i>&emsp;&emsp;<?php echo $patient['irsz']." ".$patient['varos'].", ".$patient['utca'] ?></td>
			<td><i>Naplószám:</i></td>
		</tr>
	</table>

	<table class = "core-details-table">
		<tr>
			<td><i>Osztálykód:</i>&emsp;&emsp;000000719</td>
			<td><i>Orvos:</i></td>
			<td><i>Diagnózis:</i></td>
		</tr>
		<tr>
			<td colspan = "3">
				<i>Térítési kategória:</i>&emsp;&emsp;&emsp;4. Térítésköteles járó
			</td>
		</tr>
	</table>
	<div class = "modul-box">
		<table class = "s1-modul-table" id = "kemia-lista" style = "margin-right:5px;">
			<tr><td><i>Kémia</i></td></tr>
			<?php echo get_protocol((isset($getProtocol)?$getProtocol['kemia_protocol']:"")) ?>
		</table>
		<div class = "s1-modul-table" style = "margin-right:5px;border:none;">
			<table class = "s2-modul-table" id = "hematologia-lista" style = "margin-bottom:5px;">
				<tr><td><i>Hematológia</i></td></tr>
				<?php echo get_protocol((isset($getProtocol)?$getProtocol['hematologia_protocol']:"")) ?>
			</table>
			<table class = "s2-modul-table" id = "veralvadas-lista">
				<tr><td><i>Véralvadás</i></td></tr>
				<?php echo get_protocol((isset($getProtocol)?$getProtocol['veralvadas_protocol']:"")) ?>
			</table>
			<table class = "s2-modul-table" id = "egyeb-lista">
				<tr><td><i>Egyéb</i></td></tr>
				<?php echo get_protocol((isset($getProtocol)?$getProtocol['egyeb_protocol']:"")) ?>
			</table>
		</div>
		<div class = "s1-modul-table" id = "s3-scales">
		<table class = "s2-modul-table" id = "vizelet-lista">
			<tr><td><i>Vizelet</i></td></tr>
			<?php echo get_protocol((isset($getProtocol)?$getProtocol['vizelet_protocol']:"")) ?>
		</table>
		<table class = "s2-modul-table" id = "tumormarker-lista">
			<?php echo get_protocol((isset($getProtocol)?$getProtocol['tumor_protocol']:"")) ?>
		</table>
		<table class = "s2-modul-table" id = "third-modul-table">
			<tr><td><i>Speciális labor</i></td></tr>
			<tr rowspan = "8"><td style="">
			<textarea></textarea>
			</td></tr>
		</table>
		</div>
	</div>
	<div class = "comment-box">
	<span>Megjegyzés:</span>
	<textarea></textarea>
	</div>
	<div class = "docketing-box">
	<span><i>Kelt:</i><input type = "textbox" placeholder = "Budapest" />, <?php echo date("Y.m.d") ?></span>
	</div>
	<div class = "signature-box">
	<span>Aláírás:</span>
	</div>
</div>
<div class = "protocol-select-box">
<select class = "protocol-select" onChange = '$(".modul-box").load("/admin/index.php?set_protocol=" + $(".protocol-select").val());'>
<option value = "empty"> - Válassz protocolt! - </option>
<?php 
$request = sql_query("SELECT * FROM labor_sablonok WHERE lab_id IN(8,9,7,10,46,47,48,49)"); 
$htmlout = "";
while( $protocol = sql_fetch_array( $request )) 
{
	if(isset($_GET['protocolid']) && $_GET['protocolid'] == $protocol['lab_id']) $target = "selected";
	else $target = "";
	$htmlout.= "<option {$target} value = '{$protocol['lab_id']}'>{$protocol['sablon_nev']}</option>";
}
echo $htmlout;
?>
</select>
</div>
</body>
</html>