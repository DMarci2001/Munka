<?php

if($_SESSION['adminuser']['jog_kuponlista'] != 1) header("Location:index.php");
if( !isset( $_GET['scroll'] )) $_GET['scroll'] = 1;

//Kupon készítése
if ( isset ( $_POST['create-coupon'] ))
{
	//output DIV stílus definíciója:
	$attributes = "";
	$style = array( "background-color" 	  => "", 
					"color" 		   	  => "white", 
					"font-size" 	   	  => "20px", 
					"font-weight" 	   	  => "bold", 
					"text-align"	   	  => "center", 
					"padding" 		  	  => "10px",
					"transition-duration" => "2s" );
	$onClick = ' onClick = $("#notificationDiv").css("display","none") ';
					
	//üres input mezők ellenőrzése:
	if ( isset( $_POST['NCName'])  && $_POST['NCName'] == "") 					 $error['NCName']  = " style = 'border:1px solid red' ";
	if ( isset( $_POST['NCStart']) && $_POST['NCStart'] == "") 				 	 $error['NCStart'] = ";border:1px solid red";
	if (isset( $_POST['NCEnd']) 	 && $_POST['NCEnd'] == "") 					 $error['NCEnd']   = ";border:1px solid red";
	if (isset( $_POST['NCDiscountValue']) && $_POST['NCDiscountValue'] == "") 	 $error['NCDV']    = ";border:1px solid red";
	
	if (isset($_POST['NCQuantity']) && $_POST['NCQuantity'] == "" ) $quantity = 0;
	else $quantity = $_POST['NCQuantity'];
	
	//Hibás mező ellenőrzés:
	if (isset($_POST['NCDiscountValue']) && preg_match( "/^[a-zA-Z]+$/", $_POST['NCDiscountValue'] )) $error['NCDV'] = ";border:1px solid red";
	if ( !isset ( $error ))
	{
		sql_query("INSERT INTO kuponkodok SET 
				   megnev = ?, kedvezmeny_tipus = ?, kedvezmeny = ?, event_start = ?, event_end = ?, tipus = ?,kod = ?, statusz = ?, kibocsatas = ? ",
				   array( $_POST['NCName'], $_POST['NCDiscountType'], $_POST['NCDiscountValue'], $_POST['NCStart'], $_POST['NCEnd'], $_POST['NCusability'], $_POST['NCode'], "inaktiv", "0000-00-00 00:00:00" ));
		
		$last_id = sql_insert_id();
		$request = sql_query( "SELECT * FROM kuponkodok WHERE id = ? ", array( $last_id ));
		if($request->rowCount() == 1)
		{	
			sql_query("UPDATE kuponkodok SET sid = ? WHERE id = ?", array( $last_id, $last_id ));
			$style['background-color'] = "#00B645";
			foreach( $style as $option => $value ) $attributes.= "{$option}:{$value};";			
			$notificationDiv = "<div style = '{$attributes}' id = 'notificationDiv'>Sikeres kupon készítés!&nbsp;<i {$onClick} class='fas fa-times-circle'></i></div>";
			
			//POST értékek nullifikálása:
			$_POSTelements = array ("NCName","NCDiscountType","NCDiscountValue","NCStart","NCEnd","NCusability");
			foreach($_POSTelements as $element) unset($_POST[$element]);
		}	
		else
		{
			$style['background-color'] = "red";
			foreach( $style as $option => $value ) $attributes.= "{$option}:{$value};";			
			$notificationDiv = "<div style = '{$attributes}' id = 'notificationDiv'>Sikertelen kupon készítés!!&nbsp;<i {$onClick} class='fas fa-times-circle'></i></div>";
		}
	}
}

if( isset( $_POST['multifield-filter'] ))
{
	if( $_POST['multifield-filter'] == "" ) unset( $_SESSION['multifilter'] );
	else
	{
		$_SESSION['multifilter'] = " AND (taj LIKE '%{$_POST['multifield-filter']}%' ";
		$_SESSION['multifilter'].= " OR email LIKE '%{$_POST['multifield-filter']}%' ";
		$_SESSION['multifilter'].= " OR nev LIKE '%{$_POST['multifield-filter']}%') ";
	}
}

if( isset( $_POST['start-date'] ))
{
	if($_POST['start-date'] == "") unset( $_SESSION['date-interval'] );
	else
	{
		if($_POST['end-date'] == "") $_POST['end-date'] = date("Y-m-d");
		$endDate = date("Y-m-d",strtotime($_POST['end-date']." + 1 day"));
		$_SESSION['date-interval'] = "AND kelte BETWEEN '{$_POST['start-date']}%' AND '{$endDate}%' "; 
	}
}
//if(isset($_SESSION['orvosfilter'])) echo "orvosfilter: {$_SESSION['orvosfilter']}<br/>";
//if(isset($_SESSION['cegfilter-02'])) echo "cegfilter: {$_SESSION['cegfilter']}<br/>";
//if(isset($_SESSION['csakbelgy'])) echo "belgyógy: {$_SESSION['csakbelgy']}<br/>";
//if(isset($_SESSION['multifilter'])) echo "multi: {$_SESSION['multifilter']}<br/>";
//if(isset($_SESSION['interval'])) echo "interval: {$_SESSION['interval']}<br/>";

$query = "SELECT * from kuponkodok 
		  WHERE true
		  ".( isset( $_SESSION['multifilter'] ) ? $_SESSION['multifilter'] : "" )."
		  ".( isset( $_SESSION['date-interval'] ) ? $_SESSION['date-interval'] : "" )."
		 ";
		 
//Oldal számolás:
$page_counter = sql_query( $query );
						   
$page_numb = ( $page_counter->rowCount() / 50 );
$page  = array();
$range = 50;
for($i = 0; $i <= ( round( $page_numb )); $i++)
{
	if($page_numb < round($page_numb) && $i == round($page_numb)) break;
	$start_value = ( $i * $range );
	$page[] = array( "number" => ( $i + 1 ), "limit" => "{$start_value}, 50" );
}

//Ha olyan oldal szám szerepel az URL-ben ami irreleváns, akkor átirányít az első oldalra:
if($_GET['scroll'] > count($page) || $_GET['scroll'] < 0) header("Location:index.php?page=zarok&scroll=1");
//if( $_SESSION['scroll'] > $p)

?>
<style>
.coupon td:nth-child(1),
.coupon td:nth-child(3),
.coupon td:nth-child(4),
.coupon td:nth-child(5),
.coupon td:nth-child(6),
.coupon td:nth-child(7),
.coupon td:nth-child(8){
	text-align:center;
}
</style>
<script type = "text/javascript">
	$(document).ready(function(){
		$(function(){
			$('#start-date,#end-date,#newCouponStart,#newCouponEnd').datepicker({
				dateFormat: 'yy-mm-dd',
				changeMonth: true,
				changeYear: true,
				yearRange: '-100y:c+20',
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
		$('input[name="multifield-filter"]').keypress(function(e){
		  if(e.which === 13) $('#filter-menu').submit();
	   });
	   
		$('.design-menu-table tr').hover(function () 
		{
			if($(this).hasClass('')) return false;
			
			$(this).children('td').css({'background-color':'gray','color':'white'});
		}, function() {
			$(this).children('td').css({'background-color':'white','color':'#444'});
		});
		$('.design-menu-table tr').click(function (e){
			if($(e.target).closest('td').attr('class') == 'f-obj') return;
			var numb = $(this).attr('class').split('-');
			window.location.href='index.php?page=kuponmod&id='+numb[1];
		});
	});
	function kuponCheck(coupon)
	{
		$.ajax({
		method:'POST',
		url:'index.php',
		data:{ kuponCheck:'1', 
			   coupon:coupon
			 }
		}).done(function(data){
			if(data == 'error01')
			{
				$('#coupondesc').css('color','red').text('Érvénytelen kupon!');
			}
			if(data == 'error02')
			{
				$('#coupondesc').css('color','red').text('A kupont már felhasználták!');
			}
			if(data != 'error01' && data != 'error02')
			{
				var str = data.split('|');
				$('#coupontitle').text(str[0]);
				$('#coupondesc').css('color','#12c915').text(str[1]);
			}
		});
	}
	
</script>
	<?php 
		if($_SESSION['adminuser']['jog_kuponkeszites'] == 1) {
			echo (isset($notificationDiv)?$notificationDiv:"");
	?>
	<div style = "display:block;min-height:50px;border:2px solid #ccc;border-radius:5px;background-color:#f3f3f1;margin-bottom:10px">
		<form method = "POST">
			<div style = "display:inline-block;font-size:16px;text-transform: uppercase;font-weight:bold;padding-left:10px">Kupon készítés</div>
			<div style = "display:inline-block">
				<input type = "textbox" class = "design-put docs-menu-input" <?php echo (isset($error['NCName'])?$error['NCName']:"").(isset($_POST['NCName'])?"value='{$_POST['NCName']}'":"") ?> name = "NCName" placeholder = "Kupon név..." />
			</div>
			<div style = "display:inline-block">
				<input type = "textbox" class = "design-put docs-menu-input" id = "newCouponStart" name = "NCStart" value = "<?php echo (isset($_POST['NCStart'])?$_POST['NCStart']:"") ?>" style = "width:100px;text-align:center<?php echo (isset($error['NCStart'])?$error['NCStart']:"") ?>" placeholder = "Kezdés..."/>
			</div>
			<div style = "display:inline-block">
				<input type = "textbox" class = "design-put docs-menu-input" id = "newCouponEnd" name = "NCEnd" value = "<?php echo (isset($_POST['NCEnd'])?$_POST['NCEnd']:"") ?>" style = "width:100px;text-align:center<?php echo (isset($error['NCEnd'])?$error['NCEnd']:"") ?>" placeholder = "Vége..."/>
			</div>
			<div style = "display:inline-block">
				<input type = "textbox" class = "design-put docs-menu-input" name = "NCDiscountValue" value = "<?php echo (isset($_POST['NCDiscountValue'])?$_POST['NCDiscountValue']:"") ?>" style = "width:120px<?php echo (isset($error['NCDV'])?$error['NCDV']:"") ?>" placeholder = "Kedv. érték..." />
			</div>
			<div style = "display:inline-block">
				<select class = "design-put docs-menu-input v2" style = "width:80px" name = "NCDiscountType">
					<option <?php echo (isset($_POST['NCDiscountType']) && $_POST['NCDiscountType'] == "szazalek"?"selected":"") ?> value = "szazalek"> % </option>
					<option <?php echo (isset($_POST['NCDiscountType']) && $_POST['NCDiscountType'] == "fix"?"selected":"") ?> value = "fix"> Fix </option>
				</select>
			</div>
			<div style = "display:inline-block">
				<select class = "design-put docs-menu-input v2" style = "width:120px" name = "NCusability">
					<option <?php echo (isset($_POST['NCusability']) && $_POST['NCusability'] == "Egyszer"?"selected":"") ?> value = "Egyszer"> Egyszer </option>
					<option <?php echo (isset($_POST['NCusability']) && $_POST['NCusability'] == "Többször"?"selected":"") ?> value = "Többször"> Többször </option>
				</select>
			</div>
			<div style = "display:inline-block">
				<input type = "textbox" class = "design-put docs-menu-input" name = "NCode" value = "<?php echo (isset($_POST['NCode'])?$_POST['NCode']:"") ?>" style = "width:120px" placeholder = "Kód..." />
			</div>
			<div style = "display:inline-block">
				<button class = "circle-button" type = "submit" name = "create-coupon"><i class="fas fa-plus"></i></button>
			</div>
		</form>
	</div>
	<?php } ?>
<form method = "POST" id = "filter-menu">
<div style = "display:block;min-height:50px;border:2px solid #ccc;border-radius:5px">
	<div style = "display:inline-block">
		<?php
		if( isset( $_SESSION['multifilter'] ))
		{
			$container = explode( "%", $_SESSION['multifilter'] );
			$searchValue = $container[1];
		}
		else $searchValue = "";
	
		if( isset( $_SESSION['date-interval'] ))
		{
			$temp_var = explode( "BETWEEN '", $_SESSION['date-interval'] );
			$Date_01 = explode( "%'", $temp_var[1] );
			$startDate = $Date_01[0];
			unset( $temp_var );
			$temp_var = explode( "AND '", $_SESSION['date-interval'] );
			$Date_02 = explode( "%'", $temp_var[1] );
			$endDate = date("Y-m-d", strtotime($Date_02[0]." - 1 day"));
		}
		else
		{
			$startDate = "";
			$endDate   = "";
		}
		?>
		<input type = "textbox" class = "design-put docs-menu-input" value = "<?php echo $searchValue ?>" name = "multifield-filter" placeholder = "Kuponkód, kupon név, ügyfélnév..."/>
		<button class = "circle-button" onChange = '$("#filter-menu").submit()' style = ""><i class="fas fa-search"></i></button>
	</div>
	<div style = "display:inline-block">
		<input type = "textbox" class = "design-put docs-menu-input" id = "start-date" value = "<?php echo $startDate ?>" name = "start-date" style = "width:100px;text-align:center" placeholder = "...-tól"/>&nbsp;-
		<input type = "textbox" class = "design-put docs-menu-input" id = "end-date" value = "<?php echo $endDate ?>" name = "end-date" style = "width:100px;text-align:center" placeholder = "...-ig"/>
		<button class = "circle-button" onChange = '$("#filter-menu").submit()' style = ""><i class="fas fa-filter"></i></button>
	</div>
</div>
</form>
<table class = "design-menu-table coupon">
	<tr>
		<td>#.</td>
		<td>Kupon neve</td>
		<td>Kód</td>
		<td>Kedvezmény</td>
		<td>Típus</td>
		<td>Felhasználva</td>
		<td>Kibocsátva</td>
		<td>Státusz</td>
	</tr>
	<?php
		$request = sql_query( $query." LIMIT {$page[($_GET['scroll']-1)]['limit']}" );
							 
		$count = (( $_GET['scroll'] -1 ) * 50 );
		while( $result = sql_fetch_array( $request ))
		{
			$count++;
			if($result['tipus'] == "Többször")
			{
				$checkList = sql_query("SELECT * FROM kupon_lista kl
										   LEFT JOIN kuponkodok kk ON kk.id = kl.kuponid
										   WHERE kk.tipus = 'Többször' AND kl.kuponid = {$result['id']}");
				
				$db = $checkList->rowCount();
				
				$kupon = sql_fetch_array($checkList);
			}
			else $db = "";
			?>
			
			<tr class = "tr-<?php echo $result['id'] ?>">
				<td><?php echo $count ?>.</td>
				<td><?php echo $result['megnev'] ?></td>
				<td><?php echo ($result['kod'] != ""?$result['kod']:" - ") ?></td>
				<td><?php echo $result['kedvezmeny'].($result['kedvezmeny_tipus'] == "fix"?"&nbsp;Ft":"%") ?></td>
				<td><?php echo ($result['tipus'] == "Többször"?"{$result['tipus']} ({$db} db)":$result['tipus']) ?></td>
				<td><?php ?></td>
				<td><?php echo $result['kibocsatas'] ?></td>
				<td><?php echo $result['statusz'] ?></td>
			</tr>
			<?php
		}
		//Ha nincs találati eredmény ezt írja ki:
		if( $count == 0 )
		{
			?><tr><td colspan = "11" align = "center"><h2>Nincs találati eredmény!</h2></td></tr><?php
		}
	?>
	<tr>
		<td colspan = "8" align = "center">
		<?php
			$pageout = "";
			$preHide = 0;
			foreach($page as $key => $value)
			{
				if( $page[$key]['number'] == $_GET['scroll'] ) $aStyle = "style='background-color: #2f8793; text-decoration: none;'";
				else $aStyle = "";
				//Ha a lapszám több mint 10 akkor rejtse le a a fölösleges lap számot(de az 1.-t jelenítse meg.)
				if( ($_GET['scroll'] - 10 ) > $key )
				{
					if( $preHide > 0 ) continue;
					$pageout.= "<a class = 'ujbutton' href = 'index.php?page=kuponok&scroll=1' {$aStyle} >1.</a>&nbsp;";
					$pageout.= "...&nbsp;";
					$preHide++;
					continue;
				}
				$pageout.= "<a class = 'ujbutton' href = 'index.php?page=kuponok&scroll={$page[$key]['number']}' {$aStyle} >{$page[$key]['number']}.</a>&nbsp;";
				//Ha lapszámhoz képest 8 értékkel nagyobb lapokat rejtse le, de az utolsót mutassa.
				if( $key == ( $_GET['scroll'] + 8 ))
				{
					$pageout.= "...&nbsp;";
					$pageout.= "<a class = 'ujbutton' href = 'index.php?page=kuponok&scroll=".count($page)."' {$aStyle} >".count($page).".</a>&nbsp;";
					break;
				}
			}
			echo $pageout;
		?>
		</td>
	</tr>
</table>