<?php
if( !isset( $_GET['scroll'] )) $_GET['scroll'] = 1;

if( !isset( $_GET['status'] )) $_GET['status'] = "open";


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

if( $_GET['status'] == "open" )
{
	$statusOPT = " AND exist_doc = 0 OR mod_request = 1";
	
		$query = "SELECT * from GDPR
				  WHERE true {$statusOPT}
				  ".( isset( $_SESSION['multifilter'] ) ? $_SESSION['multifilter'] : "" )."
				  ".( isset( $_SESSION['date-interval'] ) ? $_SESSION['date-interval'] : "" )."
				 ";
}	
if( $_GET['status'] == "closed" )
{
	$statusOPT = " AND exist_doc = 1 AND mod_request = 0";
	
		$query = "SELECT * from GDPR
				  WHERE true {$statusOPT}
				  ".( isset( $_SESSION['multifilter'] ) ? $_SESSION['multifilter'] : "" )."
				  ".( isset( $_SESSION['date-interval'] ) ? $_SESSION['date-interval'] : "" )."
				 ";
}

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
<script type = "text/javascript">
	$(document).ready(function(){
		$(function(){
			$('#start-date,#end-date').datepicker({
				dateFormat: 'yy-mm-dd',
				changeMonth: true,
				changeYear: true,
				yearRange: '-100y:c+nn',
				maxDate: '+2y'
				});
				$.datepicker.regional['hu'] = {
				closeText: 'Zavřít',
				prevText: '&#x3c;Dříve',
				nextText: 'Později&#x3e;',
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
		$('input[name="multifield-filter"]').keypress(function(e){
		  if(e.which === 13) $('#filter-menu').submit();
	   });
	   
		$('.gdpr-menu-table tr').hover(function () 
		{
			if($(this).hasClass('')) return false;
			
			$(this).children('td').css({'background-color':'gray','color':'white'});
		}, function() {
			$(this).children('td').css({'background-color':'white','color':'#444'});
		});
		$('.gdpr-menu-table tr').click(function (e){
			if($(e.target).closest('td').attr('class') == 'f-obj') return;
			var numb = $(this).attr('class').split('-');
			window.location.href='index.php?page=gdpr_edit&doc='+numb[1]+'&status='+numb[2];
		});
	});
</script>
<form method = "POST" id = "filter-menu">
<div style = "display:block;min-height:50px;border:2px solid #ccc;border-radius:5px;background-color:##f3f3f1;">
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
		<input type = "textbox" class = "design-put docs-menu-input" value = "<?php echo $searchValue ?>" name = "multifield-filter" placeholder = "Név, TAJ, email..."/>
		<button class = "circle-button" onChange = '$("#filter-menu").submit()' style = ""><i class="fas fa-search"></i></button>
	</div>
	<div style = "display:inline-block">
		<input type = "textbox" class = "design-put docs-menu-input" id = "start-date" value = "<?php echo $startDate ?>" name = "start-date" style = "width:100px;text-align:center" placeholder = "...-tól"/>&nbsp;-
		<input type = "textbox" class = "design-put docs-menu-input" id = "end-date" value = "<?php echo $endDate ?>" name = "end-date" style = "width:100px;text-align:center" placeholder = "...-ig"/>
		<button class = "circle-button" onChange = '$("#filter-menu").submit()' style = ""><i class="fas fa-filter"></i></button>
	</div>
</div>
</form>
<table class = "gdpr-menu-table">
	<tr>
		<td>#.</td>
		<td>Páciens neve</td>
		<td>TAJ</td>
		<td>Születési dátum</td>
		<td>E-mail</td>
		<td>Munkahely</td>
		<td>Munkakör</td>
		<td>Vizsgálat típusa</td>
		<td>Vizsgálat</td>
		<td>Keltezés</td>
		
		<td><i class="fas fa-asterisk"></i></td>
	</tr>
	<?php
		$request = sql_query( $query." LIMIT {$page[($_GET['scroll']-1)]['limit']}" );
							 
		$count = (( $_GET['scroll'] -1 ) * 50 );
		while( $result = sql_fetch_array( $request ))
		{
			$count++;
			$hourmin = explode( " ", $result['kelte'] );
			
			if( $_GET['status'] == "closed" )
			{
				$redirect 	= "onClick = \" $.redirectPost('index.php', {downloadGDPR:'{$result['id']}'}); \" ";
				$funcButton = "<i {$redirect} class = 'fas fa-arrow-circle-down download-icon'></i>";
			}
			if( $_GET['status'] == "open" )
			{
				if( $result['exist_doc'] == 0 ) $color = " style='color:red' ";
				else $color = " style = '' ";
				$funcButton = "<i {$color} class = 'fas fa-file-alt'></i>";
			}
			
			if( $result['munkahely'] != "" )
			{
				$ceg = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id = {$result['munkahely']}"));
				$munkahely = $ceg['megnev'];
			}
			else $munkahely = "";
			
			$vizsgalatok = explode( ",",$result['vizsgalat'] );
			$vizsgOutput = "";
			foreach($vizsgalatok as $obj)
			{
				$str = mb_strimwidth( $obj, 0, 7, "..." );
				$vizsgOutput.= "<div title = '{$obj}' style = 'border:1px solid #444;display:inline-block;padding:5px;margin:3px 3px 3px 3px;border-radius:5px;background-color:#dcdbd6;color:#444'>{$str}</div>";
			}
			?>
			
			<tr class = "tr-<?php echo $result['id']."-".$_GET['status'] ?>">
				<td><?php echo $count ?>.</td>
				<td><?php echo $result['nev'] ?></td>
				<td><?php echo $result['taj'] ?></td>
				<td title = "Szül. hely: <?php echo $result['szulhely'] ?>"><?php echo date("Y-m-d",strtotime($result['szuldatum'])) ?></td>
				<td><?php echo $result['email'] ?></td>
				<td><?php echo $munkahely ?></td>
				<td><?php echo $result['munkakor'] ?></td>
				<td><?php echo $result['adatlap'].( $result['adatlap'] == "uzemorvosi" ? "&nbsp;({$result['vizsgalat_tipus']})" : "" ) ?></td>
				<td><?php echo $vizsgOutput ?></td>
				<td title = "<?php echo $result['jovahagyta'] ?>"><?php echo date( "Y-m-d", strtotime( $result['kelte'] )) ?></td>
				<td class = "f-obj" ><?php echo $funcButton ?></td>
			</tr>
			<?php
		}
		//Ha nincs találati eredmény ezt írja ki:
		if( $count == 0 )
		{
			?>
			<tr><td colspan = "11" align = "center"><h2>Nincs találati eredmény!</h2></td></tr>
			<?php
		}
	?>
	<tr>
		<td colspan = "11" align = "center">
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
					$pageout.= "<a class = 'ujbutton' href = 'index.php?page=gdpr&status={$_GET['status']}&scroll=1' {$aStyle} >1.</a>&nbsp;";
					$pageout.= "...&nbsp;";
					$preHide++;
					continue;
				}
				$pageout.= "<a class = 'ujbutton' href = 'index.php?page=gdpr&status={$_GET['status']}&scroll={$page[$key]['number']}' {$aStyle} >{$page[$key]['number']}.</a>&nbsp;";
				//Ha lapszámhoz képest 8 értékkel nagyobb lapokat rejtse le, de az utolsót mutassa.
				if( $key == ( $_GET['scroll'] + 8 ))
				{
					$pageout.= "...&nbsp;";
					$pageout.= "<a class = 'ujbutton' href = 'index.php?page=gdpr&status={$_GET['status']}&scroll=".count($page)."' {$aStyle} >".count($page).".</a>&nbsp;";
					break;
				}
			}
			echo $pageout;
		?>
		</td>
	</tr>
</table>