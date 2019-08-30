<?php
if( !isset( $_GET['scroll'] )) $_GET['scroll'] = 1;

if(isset($_POST['sort-by']))
{
	$val = explode( "|", $_POST['sort-by'] );
	$_SESSION['alkalmassagi']['sort-by'] = "{$val[0]} {$val[1]}";
	if($val[0] == "remove") unset($_SESSION['alkalmassagi']['sort-by']);
}

if(isset( $_POST['cegfilter-03']))
{
	if( $_POST['cegfilter-03'] == "empty" ) unset( $_SESSION['alkalmassagi']['cegfilter'] );
	else
	{
		$_SESSION['alkalmassagi']['cegfilter'] = " AND felh.cegid = {$_POST['cegfilter-03']} ";
	}
}

if( $_SESSION['adminuser']['jogosultsag'] < 2 ) 
{	
	$_SESSION['alkalmassagi']['cegfilter'] = " AND felh.cegid IN (".getCegList($_SESSION['adminuser']["cegjog"]).") ";
}	

if( isset( $_POST['multifield-filter'] ))
{
	if( $_POST['multifield-filter'] == "" ) unset( $_SESSION['alkalmassagi']['multifilter'] );
	else
	{
		$_SESSION['alkalmassagi']['multifilter'] = " AND (felh.nev LIKE '%{$_POST['multifield-filter']}%' ";
		$_SESSION['alkalmassagi']['multifilter'].= " OR felh.torzsszam LIKE '%{$_POST['multifield-filter']}%') ";
	}
}

if(!isset($_SESSION['alkalmassagi']['date-interval'])) $_SESSION['alkalmassagi']['date-interval'] = "AND felh.alklejarat BETWEEN '".date("Y-m")."-01%' AND '".date("Y-m",strtotime("Now + 1 month"))."-01%' ";

if( isset( $_POST['start-date'] ))
{
	if($_POST['start-date'] == "") unset( $_SESSION['alkalmassagi']['date-interval'] );
	else
	{
		if($_POST['end-date'] == "") $_POST['end-date'] = date("Y-m-d");
		$endDate = date("Y-m-d",strtotime($_POST['end-date']." + 1 day"));
		$_SESSION['alkalmassagi']['date-interval'] = "AND alklejarat BETWEEN '{$_POST['start-date']}%' AND '{$endDate}%' "; 
	}
}

$query = "SELECT felh.id,felh.nev,felh.szuldatum,felh.torzsszam,felh.alklejarat,felh.lastalkert 
		  FROM felhasznalok felh
		  LEFT JOIN cegek c ON c.id = felh.cegid
		  WHERE true
		  ".( isset( $_SESSION['alkalmassagi']['multifilter'] )   ? $_SESSION['alkalmassagi']['multifilter'] : "" )."
		  ".( isset( $_SESSION['alkalmassagi']['date-interval'] ) ? $_SESSION['alkalmassagi']['date-interval'] : "" )."
		  ".( isset( $_SESSION['alkalmassagi']['cegfilter'] )  ? $_SESSION['alkalmassagi']['cegfilter'] : "" )."
		  ".( isset( $_SESSION['alkalmassagi']['sort-by'] ) ? "ORDER BY ".$_SESSION['alkalmassagi']['sort-by'] : "" )."
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
if($_GET['scroll'] > count($page) || $_GET['scroll'] < 0) header("Location:index.php?page=alkalmassagi&scroll=1");

function checkLejarat($lejarat)
{
	$different = ( strtotime( $lejarat ) - strtotime( "Now" )) / 86400;
	$formed = date("Y.m.d",strtotime($lejarat));
	if( $different > 5 ) 				  $htmlout = "<td style = 'color:#444' >{$formed}</td>";
	if( $different < 5 && $different > 0) $htmlout = "<td class = 'hashtagf09100' style = 'color:#f09100;font-weight:bold' >{$formed}</td>";
	if( $different <= 0 ) 				  $htmlout = "<td class = 'hashtagdb2100' style = 'color:#db2100;font-weight:bold'>{$formed}</td>";
	return $htmlout;
}
?>
<div class="pagehead">
<div style="display:table-cell;vertical-align:middle;">Alkalmassági lekérdezés</div>
</div>

<style>
.alk td:nth-child(1),
.alk td:nth-child(3),
.alk td:nth-child(4),
.alk td:nth-child(5),
.alk td:nth-child(6){
	text-align:center;
}
.grayscale:hover{
	cursor:pointer;
	transition-duration:1s;
	filter: grayscale(1);
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
			//if($(this).children('td').attr('class') == 'colorTD') console.log('sziaaa');
			$(this).children('td').css({'background-color':'gray','color':'white'});
			$(this).children('td').each(function(index,obj){
				if( $(obj).attr('class') == 'hashtagf09100' ) $(obj).css({'background-color':'gray','color':'#f09100'}); 
				if( $(obj).attr('class') == 'hashtagdb2100' ) $(obj).css({'background-color':'gray','color':'#db2100'}); 
			});
		}, function() {
			$(this).children('td').css({'background-color':'white','color':'#444'});
			$(this).children('td').each(function(index,obj){
				if( $(obj).attr('class') == 'hashtagf09100' ) $(obj).css({'background-color':'white','color':'#f09100'}); 
				if( $(obj).attr('class') == 'hashtagdb2100' ) $(obj).css({'background-color':'white','color':'#db2100'}); 
			});
		});
		$('.design-menu-table tr').click(function (e){
			if($(e.target).closest('td').attr('class') == 'f-obj') return;
			var numb = $(this).attr('class').split('-');
			window.location.href='index.php?page=felhasznalok&szerk='+numb[1];
		});
	});
	
	function sortBy(column,sort)
	{
		var val = column+'|'+sort;
		$('#sort-by').append('<input type = "hidden" name = "sort-by" value = "'+val+'" />');
		$('#sort-by').submit();
	}
	
</script>

<form method = "POST" id = "filter-menu">
<div style = "display:block;min-height:50px;border:2px solid #ccc;border-radius:5px">
	<div style = "display:inline-block;">
		<?php
		if( isset( $_SESSION['alkalmassagi']['multifilter'] ))
		{
			$container = explode( "%", $_SESSION['alkalmassagi']['multifilter'] );
			$searchValue = $container[1];
		}
		else $searchValue = "";
	
		if( isset( $_SESSION['alkalmassagi']['date-interval'] ))
		{
			$temp_var = explode( "BETWEEN '", $_SESSION['alkalmassagi']['date-interval'] );
			$Date_01 = explode( "%'", $temp_var[1] );
			$startDate = $Date_01[0];
			unset( $temp_var );
			$temp_var = explode( "AND '", $_SESSION['alkalmassagi']['date-interval'] );
			$Date_02 = explode( "%'", $temp_var[1] );
			$endDate = date("Y-m-d", strtotime($Date_02[0]." - 1 day"));
		}
		else
		{
			$startDate = "";
			$endDate   = "";
		}
		?>
		<input type = "textbox" class = "design-put docs-menu-input" value = "<?php echo $searchValue ?>" name = "multifield-filter" placeholder = "Név, törzsszám..."/>
		<button class = "circle-button" onChange = '$("#filter-menu").submit()' style = ""><i class="fas fa-search"></i></button>
	</div>
	<div style = "display:inline-block">
		<input type = "textbox" class = "design-put docs-menu-input" id = "start-date" value = "<?php echo $startDate ?>" name = "start-date" style = "width:100px;text-align:center" placeholder = "...-tól"/>&nbsp;-
		<input type = "textbox" class = "design-put docs-menu-input" id = "end-date" value = "<?php echo $endDate ?>" name = "end-date" style = "width:100px;text-align:center" placeholder = "...-ig"/>
		<button class = "circle-button" onChange = '$("#filter-menu").submit()' style = ""><i class="fas fa-filter"></i></button>
	</div>
	<?php if( $_SESSION['adminuser']['jogosultsag'] == 2 ) { ?>
	<div style = "display:inline-block">
		<select class = "design-put docs-menu-input v2" onChange = '$("#filter-menu").submit()' name = "cegfilter-03" >
			<option value = "empty"> - Cég szerint - </option>
			<?php
				$optionout = "";
				$request = sql_query( "SELECT * FROM cegek ORDER BY megnev ASC" );
				while( $result = sql_fetch_array( $request ))
				{
					if(isset($_SESSION['alkalmassagi']['cegfilter']))
					{
						$info = explode("felh.cegid = ", $_SESSION['alkalmassagi']['cegfilter']);
						$info2 = explode(" ",$info[1]);
						if( $info2[0] == $result['id'] ) $match = " selected ";
						else $match = "";
					}
					$optionout.= "<option {$match} value = {$result['id']}>{$result['megnev']}</option>";
				}
				echo $optionout;
			?>
		</select>
	</div>
	<?php } ?>
	<div style = "display:inline-block;vertical-align:middle<?php echo ($_SESSION['adminuser']['jogosultsag'] < 2?";margin-left:10px":"") ?>">
		<?php
		//.xlsx link készítés:
		
		$downloadLink = "onClick = \" $.redirectPost('index.php', { start:'{$startDate}', end:'{$endDate}', function:'createAlkxlsx'}); \" ";
		?>
		<img <?php echo $downloadLink ?> class = "grayscale" src = "../images/icon_xlsx.png" style = "height:40px;"/>
	</div>
</div>
</form>

<form method = "POST" id = "sort-by"></form>

<table class = "design-menu-table alk">
	<?php
	//Oszlopok és rendezés definiálása:
	$columns = array ();
	$columns[] = array( "title" => "Munkavállaló neve", 	"column" => "felh.nev" 		  );
	$columns[] = array( "title" => "Születési dátum", 		"column" => "felh.szuldatum"  );
	$columns[] = array( "title" => "Törzsszám", 			"column" => "felh.torzsszam"  );
	$columns[] = array( "title" => "Utolsó értesítés", 		"column" => "felh.lastalkert" );
	$columns[] = array( "title" => "Alkalmassági lejárata", "column" => "felh.alklejarat" );
	
	$sort = false;
	if(isset($_SESSION['alkalmassagi']['sort-by']))
	{
		$sort = true;
		$sortBy = explode(" ", $_SESSION['alkalmassagi']['sort-by']);
	}
	
	$columnsout = "<td onClick='sortBy(\"remove\",\"default\")'>#.</td>";
	foreach($columns as $column)
	{
		$arrow = "";
		$type = "DESC";
		if($sort == true)
		{
			if($column['column'] == $sortBy[0])
			{
				if($sortBy[1] == "DESC") {
					$arrow = "<i class='fas fa-angle-up'></i>";
					$type = "ASC";
				} 
				if($sortBy[1] == "ASC") {
					$arrow = "<i class='fas fa-angle-down'></i>";
					$type = "DESC";
				}	 
			}
		}
		$columnsout.="<td onClick = 'sortBy(\"{$column['column']}\",\"{$type}\")' >{$column['title']}".( $arrow != "" ? "&nbsp;".$arrow : "" )."</td>";
	}
	?>
	<tr>
		<?php /*Oszlopok megjelenítése:*/ echo $columnsout ?>
	</tr>
	<?php
		$request = sql_query( $query." LIMIT {$page[($_GET['scroll']-1)]['limit']}" );
							 
		$count = (( $_GET['scroll'] -1 ) * 50 );
		while( $result = sql_fetch_array( $request ))
		{
			$count++;
	?>
			
			<tr class = "tr-<?php echo $result['id'] ?>">
				<td><?php echo $count ?>.</td>
				<td><?php echo $result['nev'] ?></td>
				<td><?php echo date( "Y.m.d", strtotime( $result['szuldatum'] )) ?></td>
				<td><?php echo $result['torzsszam'] ?></td>
				<td><?php echo $result['lastalkert'] ?></td>
				<!--<td><?php echo date( "Y.m.d", strtotime( $result['alklejarat'] )) ?></td>-->
				<?php echo checkLejarat( $result['alklejarat'] ) ?>
			</tr>
			<?php
		}
		//Ha nincs találati eredmény ezt írja ki:
		if( $count == 0 )
		{
			?><tr><td colspan = "6" align = "center"><h2>Nincs találati eredmény!</h2></td></tr><?php
		}
	?>
	<tr>
		<td colspan = "6" align = "center">
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
					$pageout.= "<a class = 'ujbutton' href = 'index.php?page=alkalmassagi&scroll=1' {$aStyle} >1.</a>&nbsp;";
					$pageout.= "...&nbsp;";
					$preHide++;
					continue;
				}
				$pageout.= "<a class = 'ujbutton' href = 'index.php?page=alkalmassagi&scroll={$page[$key]['number']}' {$aStyle} >{$page[$key]['number']}.</a>&nbsp;";
				//Ha lapszámhoz képest 8 értékkel nagyobb lapokat rejtse le, de az utolsót mutassa.
				if( $key == ( $_GET['scroll'] + 8 ))
				{
					$pageout.= "...&nbsp;";
					$pageout.= "<a class = 'ujbutton' href = 'index.php?page=alkalmassagi&scroll=".count($page)."' {$aStyle} >".count($page).".</a>&nbsp;";
					break;
				}
			}
			echo $pageout;
		?>
		</td>
	</tr>
</table>