<?php

function ProtocolSettings($cegid){
	$showSettings  = "onClick = '$(\".protocol-settings\").slideToggle();return false'";
	$showList 	   = "onClick = '$(\".protocol-list\").slideToggle();return false'";
	$protocolStr   = "";
	$request  = sql_query("SELECT protocol FROM ceges_protocolok
						   WHERE cegid = ?", array( $cegid ));
	$existProtocol = $request->rowCount();
	if( $existProtocol > 0 ) {
		$protocolList = '';
		$result	  = sql_fetch_array($request);
		$protocol = explode("||",$result['protocol']);
		for( $i = 0; $i < count( $protocol ); $i++ ) $protocolList = $protocolList.','.$protocol[$i];
		$protocolList = substr($protocolList, 1);

		$request = sql_query("SELECT megnev FROM vizsgalati_protocolok WHERE id IN(".$protocolList.")");
		while( $result_01 = sql_fetch_array( $request )) $protocolStr = $protocolStr.', '.$result_01['megnev'];
		$protocolStr = substr($protocolStr, 2);
	}

	?>
		<tr><td colspan = "2">
		<div class = "successful-message"><span>loading...</span></div>
		<div class = "tdsepdiv">Vizsgálati protocollok</div>
		<input name="SetProtocols" style = "margin: 5px;" <?php echo $showSettings ?> value="Protocolok beállítása" type="submit">
		<div class = "protocol-settings">
			<div style = "font-size:16px;font-weight:bold">Beállított protocollok:</div>
			<textarea id = "protocol-textarea" readonly style = "height:150px;width:500px;"><?php echo $protocolStr ?></textarea><br/>
			<input name="SetProtocols" style = "margin-top:5px;width:500px" <?php echo $showList ?> value="Választható protocollok" type="submit">
			<div class = "protocol-list">
				<div class = "protocol-list-wrapper"><?php echo protocolList( $protocol ) ?></div>
				<div style = "font-weight:bold;font-size:14px;margin-top:5px">
					Új hozzáadása:&nbsp;<input type = "textbox" id = "new-protocol" style = "width:250px" />
					<input name="addProtocol" value="Hozzáadás" onClick = 'setProtocol($("#new-protocol").val());return false' type="submit" />
				</div>
				<div style = "margin-top:5px"><input name = "save-protocol" onclick = 'saveProtocol(<?php echo $cegid ?>)' value = "Protocoll mentése" type = "submit" /></div>
			</div>
		</div>
		</td></tr>
	<?php
}

if(isset($_REQUEST['refreshProtocolList'])){
	echo  protocolList($_REQUEST['refreshProtocolList']);
	die();
}

function protocolList( $protocol = NULL ){
	$htmlout = "<table>";
	$request = sql_query("SELECT * FROM vizsgalati_protocolok");
	while( $result = sql_fetch_array( $request )) {
		$checking = '';
		$string = $result['megnev'];
		$title = 'title = "'.$result['megnev'].'"';
		$obj_ID = 'id = "protocol-'.$result['id'].'"';
		if( strlen( $string ) > 80 ) $string = substr( $string, 0, 80 ) . '...';
		if( $protocol != NULL ){
			for( $i = 0; $i < count( $protocol ); $i++ ) {
				if( $protocol[$i] == $result['id'] ) $checking = '<i class="fa fa-check"></i>';
			}
		}
		$htmlout.= '<tr><td class = "protocol-obj" '.$obj_ID.$title.' ><table style = "width:100%">';
		$htmlout.= '<tr><td class = "protocol-name" ><span>'.$string.'</span></td>';
		$htmlout.= '<td style = "float:right;"><div class = "checkDiv">'.$checking.'</div></td></tr>';
		$htmlout.= '</table></td><td class = "deleteIcon" title = "Törlés"><i class="fas fa-trash-alt"></i></td></tr>';
	}
	$htmlout.= "</table>";

	return $htmlout;
}
if( isset( $_REQUEST['newProtocol'] )) {
	$INSERT_protocol = sql_query("INSERT INTO vizsgalati_protocolok
									SET  megnev = ?, added = NOW()", array($_REQUEST['newProtocol']));
	if($INSERT_protocol) echo "Successful added!";

	die();
}
if( isset( $_REQUEST['saveProtocol'] )) {
	$protocol = "";
	for( $i = 0; $i < count($_REQUEST['saveProtocol']); $i++ ) {
		$protocol = $protocol.'||'.$_REQUEST['saveProtocol'][$i];
	}
	$protocol = substr($protocol, 2);
	$request = sql_query("SELECT * FROM ceges_protocolok WHERE cegid = ".$_REQUEST['cid']." ");
	$exist = $request->rowCount();
	if($exist > 0){
		$UPDATE_protocol = sql_query("UPDATE ceges_protocolok
									   SET	 protocol = ?, modified = NOW()
									  WHERE	 cegid = ? ",
									 array( $protocol, $_REQUEST['cid'] ));
		echo "Successful operation!";
	}
	else{
		$INSERT_protocol = sql_query("INSERT INTO ceges_protocolok
									   SET	cegid = ?, protocol = ?, added = NOW()",
									  array( $protocol, $_REQUEST['cid'] ));
		echo "Successful operation!";
	}
	die();
}

?>