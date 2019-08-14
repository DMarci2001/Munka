<?php
unset($_SESSION['destroyFile']);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
function openPDFfromServer($id, $status)
{
	if( $status == "open" )   $query = "SELECT * FROM dokumentumok WHERE userid = ".addslashes( $id )." AND filename LIKE 'labor%'";
	if( $status == "closed" ) $query = "SELECT *,doc.id as labid FROM zaro_leletek zl
									    LEFT JOIN paciens_leletek pl ON pl.zaro_id = zl.zaro_id
									    LEFT JOIN dokumentumok doc ON doc.userid = pl.paciens_id
									    WHERE zl.zaro_id = ".addslashes( $id );
	
	$query = sql_query( $query );
	$numb = 0;
	$PDFmenu = "";
	$PDFout = "";
	$fileList = array();
	while($result = sql_fetch_array( $query ))
	{
		if(( $status == "closed" && $result['labid'] == "") || ( $status == "open" && $result['id'] == "" )) continue;
		$numb++;
		if($numb == 1) $style = "";
		else $style = "style='display:none'";
		if($numb == 1) $class = "class='active-pdf'";
		else $class = "class='nonactive-pdf'";
		$onClick = 'onClick=(changePDF('.$result['id'].'))';
		$dir = "../doc/".floor( $result['id'] / 1000 );
		$file = $dir."/".$result['id'].".bin";
		$URL  = "../doc/".$result['id'].".pdf";
		$date = date("Y.m.d",strtotime($result['datum']));
		$PDFname = $PDFname = mb_strimwidth( $result['filename'], 0, 5, "" );
		$PDFmenu.= "<div id = '{$result['id']}' {$class} {$onClick} >{$PDFname}-{$date}</div>";
		$PDFout.=  '<div '.$style.' id = "'.$result['id'].'-file" class = "labor-pdf"><iframe style = "width:815px;height:500px;" src="'.$URL.'"></iframe></div>';
		copy( $file, $URL );
		$fileList[] = $URL;
	}
	if( count($fileList) == 0 ) return false;
	else
	{
		$_SESSION['destroyFile'] = $fileList;
		return $PDFmenu.$PDFout;
	}
	
	
	/*if(copy( $file, $URL))
	{
		$_SESSION['destroyFile'] = $URL;
		return $URL;
	}*/
	//else return $file;
}

function lid( $id, $type )
{
	if( $Query = sql_fetch_array( sql_query( "SELECT lelet_id FROM paciens_leletek WHERE paciens_id = {$id} AND lelet_type IN({$type}) AND (zaro_id IS NULL OR zaro_id = '' ) " )))
	{
		return $Query['lelet_id'];
	}
	else return "empty";
}

function lelet_splitter( $id, $type, $status )
{
	if( $status == "open" )   $Method = "SELECT lelet_szoveg FROM paciens_leletek WHERE paciens_id = {$id} AND lelet_type IN({$type}) AND (zaro_id IS NULL OR zaro_id = '' ) ";
	if( $status == "closed" ) $Method = "SELECT lelet_szoveg FROM paciens_leletek WHERE lelet_type IN({$type}) AND zaro_id = {$id} ";
	if( $Query = sql_fetch_array( sql_query( $Method )))
	{
		$Query['lelet_szoveg'] = str_replace("<br>","",$Query['lelet_szoveg']);
		$dom     = new DOMDocument;
		libxml_use_internal_errors(true);
		$dom->loadHTML( mb_convert_encoding( $Query['lelet_szoveg'] ,'HTML-ENTITIES', 'UTF-8' ));
		libxml_clear_errors();
		$xPath   = new DOMXPath( $dom );
		//HTML tag módosítások(CSS):
		$paragraphs = $dom->getElementsByTagName("p");
		for ($i = 0; $i < $paragraphs->length; $i++) {
			$paragraph = $paragraphs->item($i);
			if($type == "6" || $type == "2")
			{
				$paragraph->setAttribute("style","text-align:left;color:black;font-family:Calibri;font-size:16px");
			}
			else $paragraph->setAttribute("style","text-align:justify;color:black;font-family:Calibri;font-size:16px");
			
		}

		$spans = $dom->getElementsByTagName("span");
		for ($i = 0; $i < $spans->length; $i++) {
			$span = $spans->item($i);
			if($type == "6" || $type == "2")
			{
				$span->setAttribute("style","text-align:left;color:black;font-family:Calibri;font-size:16px");
			}
			else $span->setAttribute("style","text-align:justify;color:black;font-family:Calibri;font-size:16px");
		}
		//Kitörlendő elemek:
		$removeTags = array( '//*[@id="patient-details"]', '//*[@id="signature"]', '//*[@id="title"]' );
		if($type == "9") $removeTags[] = '//*[@id="sub-title"]';
		//END.
		
		if(!empty($dom->getElementById( "sub-title" )))
		{
			$subTitleNode = $dom->getElementById( "sub-title" );
			$subTitleNode->setAttribute("style","font-size:16px;font-weight:bold;color:black;font-family: Calibri");
			
		}
		
		//Elemek eltávolítása:
		foreach( $removeTags as $tag )
		{
			$nodes = $xPath->query( $tag );
			if( $nodes->item( 0 )) $nodes->item( 0 )->parentNode->removeChild( $nodes->item( 0 ));
		}
		//END.
		
		$product     = $dom->saveHTML();
		$blacklisted = "<html> <body>";
		$whitelist   = blacklistElements( $blacklisted );
		$product     = strip_tags( $product, implode( "", $whitelist ));
	}
	else $product = "Nincs.";
	
	return $product;
}

if( !isset($_GET['doc'] ) || $_GET['doc'] == "" ) header( "Location:index.php?page=zarok&scroll=1" );

if( $_GET['status'] == "open" )
{
	$request = sql_query( "SELECT * FROM paciens_leletek WHERE paciens_id = ? ", array( $_GET['doc'] ));
	if( $request->rowCount() == 0 ) header( "Location:index.php?page=zarok&scroll=1" );
	
	//Páciens adatok kinyerése:
	$details = sql_fetch_array( sql_query( "SELECT * FROM felhasznalok WHERE id = ?", array( $_GET['doc'] )));
}
if( $_GET['status'] == "closed" )
{
	$request = sql_query( "SELECT * FROM zaro_leletek WHERE zaro_id = ? ", array( $_GET['doc'] ));
	if( $request->rowCount() == 0 ) header( "Location:index.php?page=zarok&scroll=1" );
	
	//Páciens adatok kinyerése:
	$details = sql_fetch_array( sql_query( "SELECT felh.* FROM felhasznalok felh 
											LEFT JOIN paciens_leletek pl ON pl.paciens_id = felh.id
											LEFT JOIN zaro_leletek zl ON zl.zaro_id = pl.zaro_id
											WHERE zl.zaro_id = ? LIMIT 1", array( $_GET['doc'] )));
}



$patient = "<p style = 'font-family:Calibri;font-size:21px;font-weight:bold;text-align:center;color:#000'><em>{$details['nev']}</em></p>";
$patient.= "<p style = 'font-family:Calibri;font-size:16px;font-weight:bold;text-align:center;color:#000'><em>Születési idő: {$details['szuldatum']}</em></p>";
$patient.= "<p style = 'font-family:Calibri;font-size:16px;font-weight:bold;text-align:center;color:#000'><em>TAJ: {$details['taj']}</em></p>";
$patient.= "<p style = 'font-family:Calibri;font-size:16px;font-weight:bold;text-align:center;color:#000'><em>Anyja neve: {$details['anyjaneve']}</em></p>";
$patient.= "<p style = 'font-family:Calibri;font-size:16px;font-weight:bold;text-align:center;color:#000'><em>Lakcím: {$details['irsz']}, {$details['varos']} {$details['utca']}</em></p>";
$patient.= "<p style = 'font-family:Calibri;font-size:16px;font-weight:bold;text-align:center;color:#000'><em>{$details['telefon']}</em></p>";

$vizsgalatok = array ();
$vizsgalatok[] = array("title" => "Belgyógyászat:", 		"text" => lelet_splitter( $_GET['doc'], "9", $_GET['status'] ), 		 	   "short" => "belgyogy", 	"id" => lid( $_GET['doc'], "9" ));
$vizsgalatok[] = array("title" => "Mellkas röntgen:", 		"text" => lelet_splitter( $_GET['doc'], "10", $_GET['status'] ), 	 	 	   "short"=> "rontgen", 	"id" => lid( $_GET['doc'], "10" ));
$vizsgalatok[] = array("title" => "Hasi ultrahang:", 		"text" => lelet_splitter( $_GET['doc'], "1,11,13,14,20,21,29,31,32,33", $_GET['status'] ), "short"=> "hasi", 		"id" => lid( $_GET['doc'], "1,11,13,14,20,21,29,31,32,33" ));
$vizsgalatok[] = array("title" => "Carotis ultrahang:", 	"text" => lelet_splitter( $_GET['doc'], "35", $_GET['status'] ), 			   "short"=> "carotis", 	"id" => lid( $_GET['doc'], "35" ));
$vizsgalatok[] = array("title" => "Nyaki ultrahang:", 		"text" => lelet_splitter( $_GET['doc'], "2,16", $_GET['status'] ), 		 	   "short"=> "nyaki", 		"id" => lid( $_GET['doc'], "2,16" ));
$vizsgalatok[] = array("title" => "Here ultrahang:", 		"text" => lelet_splitter( $_GET['doc'], "4", $_GET['status'] ),	 	 	 	   "short"=> "here", 		"id" => lid( $_GET['doc'], "4" ));
$vizsgalatok[] = array("title" => "Emlő ultrahang:", 		"text" => lelet_splitter( $_GET['doc'], "3", $_GET['status'] ), 		 	   "short"=> "emlo", 		"id" => lid( $_GET['doc'], "3" ));
$vizsgalatok[] = array("title" => "Pajzsmirigy ultrahang:", "text" => lelet_splitter( $_GET['doc'], "12,15,22,23,30,34", $_GET['status'] ),"short"=> "pajzs", 		"id" => lid( $_GET['doc'], "12,15,22,23,30,34" ));
$vizsgalatok[] = array("title" => "Bőrvizsgálat:", 			"text" => lelet_splitter( $_GET['doc'], "7", $_GET['status'] ), 		 	   "short"=> "borv", 		"id" => lid( $_GET['doc'], "7" ));
$vizsgalatok[] = array("title" => "Szemészet:", 			"text" => lelet_splitter( $_GET['doc'], "6,19", $_GET['status'] ), 		 	   "short"=> "szem", 		"id" => lid( $_GET['doc'], "6,19" ));
$vizsgalatok[] = array("title" => "Echocardiographia:", 	"text" => lelet_splitter( $_GET['doc'], "5", $_GET['status'] ), 		 	   "short"=> "echo", 		"id" => lid( $_GET['doc'], "5" ));
$vizsgalatok[] = array("title" => "Mozgásszervi vizsgálat:","text" => lelet_splitter( $_GET['doc'], "8", $_GET['status'] ),          	   "short"=> "mozgas",		"id" => lid( $_GET['doc'], "8"));
?>
<script src="https://cloud.tinymce.com/stable/tinymce.min.js?apiKey=o1cu94vbzwo8v2c7vzdtftzo83ed6q45vcqa8rarux0e6r20"></script>
<script type = "text/javascript">
	tinyMCE.init({
			mode : 'specific_textareas',
			editor_selector : 'mceEditor',
			content_style: 'body{ color:#000; font-family: Calibri }',
			height: 400,
			width: 815
	});
	function changePDF(id)
	{
		if($('#'+id+'-file').css('display') == 'none')
		{
			$('.active-pdf').attr('class','nonactive-pdf');
			$('#'+id).attr('class','active-pdf');
			$( '.labor-pdf' ).each(function( index,obj ) {
				if($(obj).attr('id') == id+'-file') $(obj).css('display','block');
				else $(obj).css('display','none');
			});
		}
	}
	$(window).scroll(function(e){ 
	  var $el = $('.pagehead'); 
	  var isPositionFixed = ($el.css('position') == 'fixed');
	  if ($(this).scrollTop() > 80 && !isPositionFixed){ 
		$el.css({'position': 'fixed', 'top': '0px', 'width':'100%'}); 
	  }
	  if ($(this).scrollTop() < 80 && isPositionFixed){
		$el.css({'position': 'static', 'top': '0px', 'width': ''}); 
	  } 
	});
</script>
<div class = "pagehead">
	<div style="display:table-cell;vertical-align:middle;"> Záró lelet - szerkesztő</div>
	<div style="display:table-cell;vertical-align:middle;padding:0px 0px 0px 20px"><a class = "ujbutton" href="index.php?page=zarok&status=<?php echo $_GET['status'] ?>&scroll=<?php echo $_GET['pp'] ?>">Vissza</a></div>
</div>
<?php
$htmlout = "<div style = 'font-weight:bold;font-size:18px;margin:0 0 10px 10px'>Páciens adatok:</div>";
$htmlout.= "<div class = 'design-put' style = 'width:787px'>{$patient}</div>";

foreach( $vizsgalatok as $key => $value )
{
	if($vizsgalatok[$key]['text'] == "Nincs.") continue;
	$style = "style = 'width:787px;max-height:300px;overflow-y:scroll;font-family:calibri;color:#000;font-size:16px'";
	$htmlout.= "<div style = 'font-weight:bold;font-size:18px;margin:10px'>{$vizsgalatok[$key]['title']}</div>";
	$htmlout.= "<div class = 'design-put' {$style}>{$vizsgalatok[$key]['text']}</div>";
}
//Labor PDF:
$htmlout.= "<div style = 'font-weight:bold;font-size:18px;margin:10px'>Labor - PDF</div>";
$htmlout.= openPDFfromServer( $_GET['doc'], $_GET['status'] );
//$htmlout.= '<div><iframe style = "width:815px;height:500px;" src="'.openPDFfromServer( $_GET['doc'],$_GET['status'] ).'"></iframe></div>';

if( $_GET['status'] == "open" )
{
	$comment = '<p style = "font-weight:bold;text-decoration:underline;font-size:16px;display:block;font-family:Calibri">';
	$comment.= 'Összefoglalás, életmód-, életvitel-javaslatunk:';
	$comment.= '</p>';
	$comment.= '<p style = " text-align: justify;font-size:16px;font-family:Calibri">';
	$comment.= 'A továbbiakban rendszeres testmozgás mellett rendezett étkezés, rostdús, vitaminokban gazdag ';
	$comment.= 'táplálkozás, bő folyadékfogyasztás (2-2,5 liter) javasolt. Ülőmunka esetén annak egy- másfél ';
	$comment.= 'óránkénti megszakítása rövid testmozgással (5-10 perc): séta, nyakkörzés, vállkörzések, utóbbiak ';
	$comment.= 'különösen folyamatos számítógép előtti munkavégzés esetén hasznosak.';
	//$comment.= 'Enyhe visszeresség miatt javasolt az ülő munka megszakítása óránként 10 percre sétával, '; 
	//$comment.= 'illetve ülés közben lábzsámoly használata, valamint az alsó végtagokat megmozgató '; 
	//$comment.= 'rendszeres sporttevékenység (úszás, kerékpár) az izompumpa javítására.<br/><br/>';
	//$comment.= 'Csontsűrűség vizsgálat csökkent csontsűrűséget igazolt, növelni kell a magas Ca tartalmú '; 
	//$comment.= 'élelmiszerek (tej, tejtermékek) és a magas D-vitamin tartalmúak fogyasztását (máj, tojás, tej, '; 
	//$comment.= 'tejtermékek). A részletesebb leírást csatoltuk. ';
	$comment.= '</p><br><br>';
	//$comment.= '<p style = "text-align:left;font-size:16px;font-family:Calibri">';
	$comment.= '<table style = "width:100%;font-family:Calibri;font-size:16px;border:none">';
	$comment.= '	<tr><td style = "font-family:Calibri;font-size:16px;border:none">Prof. Dr. Garam Tamás</td><td style = "font-family:Calibri;font-size:16px;text-align:right;border:none">Dr. Magyar Judit</td></tr>';
	$comment.= '	<tr><td style = "font-family:Calibri;font-size:16px;border:none">Belgyógyász szakorvos</td><td style = "font-family:Calibri;font-size:16px;text-align:right;border:none">Belgyógyász szakorvos</td></tr>';
	$comment.= '</table>';
	//$comment.= '</p>';
	
}
if( $_GET['status'] == "closed" )
{
	$result = sql_fetch_array(sql_query("SELECT velemenyezes FROM zaro_leletek WHERE zaro_id = {$_GET['doc']}"));
	$comment = $result['velemenyezes'];
}
$htmlout.= '<form method = "POST" action = "create-word.php">';
$htmlout.= '	<div style = "font-weight:bold;font-size:18px;margin:10px">Véleményezés:</div>';
$htmlout.= "	<textarea id = 'velemenyezes' name = 'velemenyezes' class = 'mceEditor' style = 'margin-top:10px;display:inline-block'>{$comment}</textarea>";
$htmlout.= "	<input type = 'hidden' name = 'patient' value = '{$_GET['doc']}' />";
$htmlout.= "	<input type = 'hidden' name = 'status' value = '{$_GET['status']}' />";
foreach( $vizsgalatok as $key => $value )
{
	if( $vizsgalatok[$key]['text'] == "Nincs." ) continue;
	$htmlout.= "<input type = 'hidden' name = '{$vizsgalatok[$key]['short']}' value = '{$vizsgalatok[$key]['text']}'/>";
	$htmlout.= "<input type = 'hidden' name = '{$vizsgalatok[$key]['short']}-id' value = '{$vizsgalatok[$key]['id']}'/>";
}
$htmlout.= '<div style = "margin:10px;">';
$htmlout.= '	<input type = "submit" name = "submit" value = "'.($_GET['status'] == "open"?"Lezárás":"Módosítás").'">';
$htmlout.= '	<input type = "submit" name = "submit" value = "Letöltés">';
$htmlout.= '</div>';
$htmlout.='</form>';
echo $htmlout;

?>

