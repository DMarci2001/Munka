<?php

if($_SESSION['adminuser']['jog_zarolista'] != 1) header("Location:index.php");

if( !isset( $_GET['scroll'] )) $_GET['scroll'] = 1;

if(isset( $_POST['orvosfilter']))
{
	if( $_POST['orvosfilter'] == "empty" ) unset( $_SESSION['orvosfilter'] );
	else
	{
		$result = sql_fetch_array( sql_query( "SELECT * FROM orvosok WHERE id = ?", array( $_POST['orvosfilter'] )));
		$_SESSION['orvosfilter'] = " AND pl.lelet_szoveg LIKE '%{$result['nev']}%' ";
	}
	
}

if( !isset( $_GET['status'] )) $_GET['status'] = "open";



if(isset( $_POST['cegfilter-02']))
{
	if( $_POST['cegfilter-02'] == "empty" ) unset( $_SESSION['cegfilter-02'] );
	else
	{
		$_SESSION['cegfilter-02'] = " AND felh.cegid = {$_POST['cegfilter-02']} ";
	}
}		


if(isset( $_POST['truebelgy'] ))
{
	if( $_POST['truebelgy'] == "" ) unset( $_SESSION['csakbelgy'] );
	else
	{
		//$request = sql_query("SELECT lm_id FROM lelet_mintak");
		if($_GET['status'] == "open") $_SESSION['csakbelgy'] = " AND pl.lelet_type IN(9) ";
		if($_GET['status'] == "closed") $_SESSION['csakbelgy'] = " AND zl.belgyogy IS NOT NULL OR zl.belgyogy != '' ";
	} 
}
if( isset( $_POST['multifield-filter'] ))
{
	if( $_POST['multifield-filter'] == "" ) unset( $_SESSION['multifilter'] );
	else
	{
		$_SESSION['multifilter'] = " AND (felh.taj LIKE '%{$_POST['multifield-filter']}%' ";
		$_SESSION['multifilter'].= " OR felh.email LIKE '%{$_POST['multifield-filter']}%' ";
		$_SESSION['multifilter'].= " OR felh.nev LIKE '%{$_POST['multifield-filter']}%') ";
	}
}

if( isset( $_POST['start-date'] ))
{
	if($_POST['start-date'] == "") unset( $_SESSION['date-interval'] );
	else
	{
		if($_POST['end-date'] == "") $_POST['end-date'] = date("Y-m-d");
		$endDate = date("Y-m-d",strtotime($_POST['end-date']." + 1 day"));
		$_SESSION['date-interval'] = "AND pl.kelte BETWEEN '{$_POST['start-date']}%' AND '{$endDate}%' "; 
	}
}

if( $_GET['status'] == "open" )
{
	$statusOPT = " AND (zaro_id IS NULL OR zaro_id = '' )";
	
		$query = "SELECT felh.nev,felh.taj,felh.szuldatum,felh.id,c.megnev as cegnev,pl.kelte FROM paciens_leletek pl
				  LEFT JOIN felhasznalok felh ON felh.id = pl.paciens_id
				  LEFT JOIN cegek c ON c.id = felh.cegid
				  WHERE true
				  {$statusOPT}
				  ".( isset( $_SESSION['orvosfilter'] ) ? $_SESSION['orvosfilter'] : "" )."
				  ".( isset( $_SESSION['cegfilter-02'] ) ? $_SESSION['cegfilter-02'] : "" )."
				  ".( isset( $_SESSION['csakbelgy'] ) ? $_SESSION['csakbelgy'] : "" )."	
				  ".( isset( $_SESSION['multifilter'] ) ? $_SESSION['multifilter'] : "" )."
				  ".( isset( $_SESSION['date-interval'] ) ? $_SESSION['date-interval'] : "" )."
				  AND felh.id IS NOT NULL
				  GROUP BY felh.id
				  ORDER BY pl.kelte DESC";
}	
if( $_GET['status'] == "closed" )
{
	$statusOPT = " AND (zaro_id IS NOT NULL OR zaro_id != '' )";
	
		$query = "SELECT zl.zaro_id,felh.nev,felh.taj,felh.szuldatum,felh.id,c.megnev AS cegnev, zl.kelte FROM zaro_leletek zl
				  LEFT JOIN paciens_leletek pl ON pl.zaro_id = zl.zaro_id
				  LEFT JOIN felhasznalok felh ON felh.id = pl.paciens_id
				  LEFT JOIN cegek c ON c.id = felh.cegid
				  WHERE true
				  ".( isset( $_SESSION['orvosfilter'] ) ? $_SESSION['orvosfilter'] : "" )."
				  ".( isset( $_SESSION['cegfilter-02'] ) ? $_SESSION['cegfilter-02'] : "" )."
				  ".( isset( $_SESSION['csakbelgy'] ) ? $_SESSION['csakbelgy'] : "" )."	
				  ".( isset( $_SESSION['multifilter'] ) ? $_SESSION['multifilter'] : "" )."
				  ".( isset( $_SESSION['date-interval'] ) ? $_SESSION['date-interval'] : "" )."
				  AND felh.id IS NOT NULL
				  GROUP BY zl.zaro_id
				  ORDER BY zl.kelte DESC";
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

function getVizsgalatok( $id, $filter, $version )
{
	if( $version == 1 )
	{
		$request = sql_query("SELECT sample.lelet_nev FROM paciens_leletek pl
							  LEFT JOIN lelet_mintak sample ON sample.lm_id = pl.lelet_type
							  WHERE paciens_id = ? {$filter}", array( $id ));
		$str = array();
		while( $result = sql_fetch_array( $request ))
		{
			$str[] = $result['lelet_nev'];
		}
	}
	if( $version == 2 )
	{
		$columns = sql_query( "SHOW COLUMNS FROM zaro_leletek" );
		$result  = sql_fetch_array( sql_query( "SELECT * FROM zaro_leletek WHERE zaro_id = ? ", array( $id )));
		$str 	 = array();
		$exceptions = array( "zaro_id", "zaro_szoveg", "lezarta", "kelte", "velemenyezes" );
		while($column = sql_fetch_array( $columns ))
		{
			if( in_array($column['Field'], $exceptions)) continue;
			else if( $result[$column['Field']] != "" ) $str[] = $column['Field'];
		}
	}
	
	return $str;
}

function checkLabor( $id, $status )
{
	if( $status == "open" ) $request = sql_query( "SELECT * FROM dokumentumok WHERE userid = ? AND megnev = 'labor' ", array( $id ));
	if( $status == "closed") $request = sql_query( "SELECT * FROM zaro_leletek zl
													LEFT JOIN paciens_leletek pl ON pl.zaro_id = zl.zaro_id
													LEFT JOIN dokumentumok doc ON doc.userid = pl.paciens_id
													WHERE zl.zaro_id = ? ", array( $id ));
													
	if( $request->rowCount() > 0 ) $result = "<i class = 'fas fa-file-alt'></i>";
	else $result = "";
	
	return $result;
}

function getBelgyogyasz($type,$id = NULL)
{
	if( $type == "list" )
	{
		$request = sql_query("SELECT nev,o.id FROM orvosok o
							  LEFT JOIN orvos_beosztas beo ON beo.orvosid = o.id
							  WHERE beo.tipusok LIKE '%|9|%'
							  GROUP BY o.id
							  ORDER BY nev ASC");
		$orvosok = array();
		while( $result = sql_fetch_array( $request ))
		{
			$orvosok[] = array( 'nev' => $result['nev'], 'id' => $result['id'] );
		}
		return $orvosok;
	}
	if( $type == "concrete" )
	{
		$orvosok = getBelgyogyasz( "list" );
		foreach($orvosok as $key => $value)
		{
			$request = sql_query("SELECT * FROM paciens_leletek 
								  WHERE paciens_id = {$id} 
								  AND   lelet_type = 9 
								  AND   lelet_szoveg LIKE '%{$orvosok[$key]['nev']}%'");
			$rows = $request->rowCount();
			if( $rows > 0 ) return $orvosok[$key]['nev'];	
		}
		return "- Nincs -";
	}
}

$orvosok = getBelgyogyasz("list");
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
	   
		$('.finish-docs-menu-table tr').hover(function () 
		{
			if($(this).hasClass('')) return false;
			
			$(this).children('td').css({'background-color':'gray','color':'white'});
		}, function() {
			$(this).children('td').css({'background-color':'white','color':'#444'});
		});
		$('.finish-docs-menu-table tr').click(function (e){
			if($(e.target).closest('td').attr('class') == 'f-obj') return;
			var numb = $(this).attr('class').split('-');
			<?php if($_SESSION['adminuser']['jog_zaroszerk'] == 1) { ?>
			window.location.href='index.php?page=zaro-kezelo&doc='+numb[1]+'&status='+numb[2]+'&pp=<?php echo $_GET['scroll'] ?>';
			<?php } ?>
		});
	});
	
	$(window).scroll(function(e){ 
	  var $el = $('.pagehead'); 
	  var isPositionFixed = ($el.css('position') == 'fixed');
	  if ($(this).scrollTop() > 170 && !isPositionFixed){ 
		$el.css({'position': 'fixed', 'top': '0px', 'width':'100%'}); 
	  }
	  if ($(this).scrollTop() < 170 && isPositionFixed){
		$el.css({'position': 'static', 'top': '0px', 'width': ''}); 
	  } 
	});
	$(window).scroll(function(e){ 
	  var $el = $('#option-menu'); 
	  var isPositionFixed = ($el.css('position') == 'fixed');
	  if ($(this).scrollTop() > 170 && !isPositionFixed){ 
		$el.css({'position': 'fixed', 'top': '63px', 'width':'100%'}); 
	  }
	  if ($(this).scrollTop() < 170 && isPositionFixed){
		$el.css({'position': 'static', 'top': '63px', 'width': ''}); 
	  } 
	});
</script>
<form method = "POST" id = "filter-menu">
<div id = "option-menu" style = "display:block;min-height:50px;border:2px solid #ccc;border-radius:5px;background-color:#fff;">
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
	<div style = "display:inline-block">
		<select class = "design-put docs-menu-input v2" onChange = '$("#filter-menu").submit()' name = "orvosfilter" >
			<option value = "empty"> - Orvos szerint - </option>
			<?php
			$optionout = "";
			foreach($orvosok as $key => $orvos )
			{
				if(isset($_SESSION['orvosfilter']))
				{
					$info = explode("%", $_SESSION['orvosfilter']);
					if( $info[1] == $orvosok[$key]['nev'] ) $match = " selected ";
					else $match = "";
				}
				else $match = "";
				$optionout.="<option {$match} value = '{$orvosok[$key]['id']}'>{$orvosok[$key]['nev']}</option>";
			}
			echo $optionout;
			?>
		</select>
	</div>
	<div style = "display:inline-block">
		<select class = "design-put docs-menu-input v2" onChange = '$("#filter-menu").submit()' name = "cegfilter-02" >
			<option value = "empty"> - Cég szerint - </option>
			<?php
				$optionout = "";
				$request = sql_query( "SELECT * FROM cegek ORDER BY megnev ASC" );
				while( $result = sql_fetch_array( $request ))
				{
					if(isset($_SESSION['cegfilter-02']))
					{
						$info = explode("felh.cegid = ", $_SESSION['cegfilter-02']);
						$info2 = explode(" ",$info[1]);
						if( $info2[0] == $result['id'] ) $match = " selected ";
						else $match = "";
					}
					else $match = "";
					$optionout.= "<option {$match} value = {$result['id']}>{$result['megnev']}</option>";
				}
				echo $optionout;
			?>
		</select>
	</div>
	<div style = "display:inline-block">
	<input name = "csakbelgy"
		   onChange = 'if($(this).prop("checked") == true){$("#truebelgy").val("csakbelgy")}else{$("#truebelgy").val()};$("#filter-menu").submit()' 
		   <?php echo ( isset( $_SESSION['csakbelgy'] ) ? "checked" : "" ) ?>
		   type = "checkbox" 
		   value = "csakbelgy"/>&nbsp;Van belgyógy.
	<input name = "truebelgy" id = "truebelgy" type = "hidden" value = "" />
	</div>
</div>
</form>
<table class = "finish-docs-menu-table">
	<tr>
		<td>#.</td>
		<td>Páciens neve</td>
		<td>TAJ</td>
		<td>Labor</td>
		<td>Vizsgálatok</td>
		<td>Belgyógyász</td>
		<td>Cég</td>
		<td>Keltezés</td>
		<td><i class="fas fa-asterisk"></i></td>
	</tr>
	<?php
		$request = sql_query( $query." LIMIT {$page[($_GET['scroll']-1)]['limit']}" );
							 
		$count = (( $_GET['scroll'] -1 ) * 50 );
		while( $result = sql_fetch_array( $request ))
		{
			if( $_GET['status'] == "open" && isset( $_SESSION['csakbelgy'] ) && getBelgyogyasz( "concrete", $result['id'] ) == "- Nincs -" ) continue;
			$count++;
			$hourmin = explode( " ", $result['kelte'] );
			
			$vizsgalatok = getVizsgalatok(( $_GET['status'] == "open" ? $result['id'] : $result['zaro_id'] ), $statusOPT, ( $_GET['status'] == "open" ? 1 : 2 ));
			$vizsgOutput = '';
			foreach($vizsgalatok as $obj)
			{
				$str = mb_strimwidth( $obj, 0, 7, "..." );
				$vizsgOutput.= "<div title = '{$obj}' style = 'border:1px solid #444;display:inline-block;padding:5px;margin:0px 3px 0 3px;border-radius:5px;background-color:#dcdbd6;color:#444'>{$str}</div>";
			}
			
			if($_GET['status'] == "closed")
			{
				if($_SESSION['adminuser']['jog_zaroszerk'] == 1)
				{
					$redirect 	= "onClick = \" $.redirectPost('create-word.php', { status:'closed', patient:'{$result['zaro_id']}', shortcut:'on', alt:'download'}); \" ";
					$funcButton = "<i {$redirect} class = 'fas fa-arrow-circle-down download-icon'></i>";
				}
				else{
					$redirect = "";
					$funcButton = "";
				}
			}
			else
			{
				$redirect = "";
				$funcButton = "";
				//$redirect 	= "onClick = \" $.redirectPost('create-word.php', { status:'closed', doc:'{$result['id']}', shortcut:'on'}); \" ";
				//$funcButton = "<i {$redirect} class = 'fas fa-check-square download-icon'></i>";
			}
			
			$id = ( $_GET['status'] == "open" ? $result['id'] : $result['zaro_id'] );
			?>
			
			<tr class = "tr-<?php echo $id."-".$_GET['status'] ?>">
				<td><?php echo $count ?>.</td>
				<td title = "szül. dátum: <?php echo date("Y-m-d",strtotime($result['szuldatum'])) ?>"><?php echo $result['nev'] ?></td>
				<td><?php echo $result['taj'] ?></td>
				<td><?php echo checkLabor( $id, $_GET['status'] ) ?></td>
				<td><?php echo $vizsgOutput ?></td>
				<td><?php echo getBelgyogyasz( "concrete", $result['id'] ) ?></td>
				<td><?php echo $result['cegnev'] ?></td>
				<td title = "<?php echo $hourmin[1] ?>"><?php echo date( "Y-m-d", strtotime( $result['kelte'] )) ?></td>
				<td class = "f-obj" ><?php echo $funcButton ?></td>
			</tr>
			<?php
		}
		//Ha nincs találati eredmény ezt írja ki:
		if( $count == 0 )
		{
			?>
			<tr><td colspan = "8" align = "center"><h2>Nincs találati eredmény!</h2></td></tr>
			<?php
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
					$pageout.= "<a class = 'ujbutton' href = 'index.php?page=zarok&status={$_GET['status']}&scroll=1' {$aStyle} >1.</a>&nbsp;";
					$pageout.= "...&nbsp;";
					$preHide++;
					continue;
				}
				$pageout.= "<a class = 'ujbutton' href = 'index.php?page=zarok&status={$_GET['status']}&scroll={$page[$key]['number']}' {$aStyle} >{$page[$key]['number']}.</a>&nbsp;";
				//Ha lapszámhoz képest 8 értékkel nagyobb lapokat rejtse le, de az utolsót mutassa.
				if( $key == ( $_GET['scroll'] + 8 ))
				{
					$pageout.= "...&nbsp;";
					$pageout.= "<a class = 'ujbutton' href = 'index.php?page=zarok&status={$_GET['status']}&scroll=".count($page)."' {$aStyle} >".count($page).".</a>&nbsp;";
					break;
				}
			}
			echo $pageout;
		?>
		</td>
	</tr>
</table>