<?php

class AdminLeletService {

    private $adminUser;
    public function __construct()
    {

        $this->adminUser = new AdminUser();

        if (isset($_REQUEST['open_lelet'])) {
            if ($this->adminUser->leletModAccess()) {
                $lelet_id = $_REQUEST['open_lelet'];
                $textarea_name = "lelet-page-".$lelet_id;
                $lelet = sql_fetch_array(sql_query("SELECT * FROM paciens_leletek WHERE lelet_id=?",array($lelet_id)));
                ?>
                <script type="text/javascript">

                    tinyMCE.init({
                        mode : 'specific_textareas',
                        editor_selector : 'mceEditor',
                        height: 842,
                        width: 595
                    });


                </script>
                <div style = "margin-top:5px;">
                    <div class = "currently-text-container" style = "display:none;"></div>
                    <table style = "font-size:12px;margin-bottom:10px;">
                        <tr>
                            <td>Pecsétszám:</td>
                            <td><input type = "textbox" value = "<?php echo ( $lelet['pecsetszam'] != "" ? $lelet['pecsetszam'] : "" ) ?>" id = "pecsetszam" /></td>
                        </tr>
                    </table>
                </div>

                <!--Lelet szöveg helye-->
                <textarea id = "<?php echo $textarea_name ?>" class = "mceEditor" style = "margin-top:10px;display:inline-block">
	<?php echo $lelet['lelet_szoveg'] ?>
	</textarea>
                <table style = "display:inline-block;">
                    <tr>
                        <td>
                        <td valign="top" style = "padding-left:20px">
                            <form method = "POST" name = "iForm">
                                <table name = "positive-options">
                                    <?php echo $this->pozitiv_opciok($lelet['pozitiv_opciok'], $lelet['lelet_type']) ?>
                                </table>
                                <table name = "negative-option">
                                    <?php echo $this->negativ_opcio( $lelet_id ) ?>
                                </table>
                            </form>
                        </td>
                        </td>
                    </tr>
                </table>

                <div style = "margin-top:10px;">
                    <input value = "Lelet mentése" onClick = 'save_iFrame(<?php echo $_SESSION['patient_id'].",".(!isset($_SESSION['medic_id'])||$_SESSION['medic_id']==""?0:$_SESSION['medic_id']).",\"".$textarea_name."\"" ?>)'  type = "button"/>
                    <input value = "Nyomtatás" onClick = 'send_iFrame(<?php echo $_SESSION['patient_id'].",".(!isset($_SESSION['medic_id'])||$_SESSION['medic_id']==""?0:$_SESSION['medic_id']).",\"".$textarea_name."\"" ?>)' type = "button" />
                    <input value = "Mégse" name = "close_lelet" type = "button" />
                </div>
                <?php
            }
            if ($this->adminUser->leletAccess()) {
                $lelet_id = $_REQUEST['open_lelet'];
                $request_lelet = sql_query("SELECT * FROM paciens_leletek WHERE lelet_id = ? ",array($lelet_id));
                $result = sql_fetch_array($request_lelet);
                ?>
                <div class = "lelet-frame" id = "lelet-content" style = "display:block;overflow-y:scroll" ><?php echo $result['lelet_szoveg'] ?></div>
                <div class = "lelet-button-box" style = "margin-top:10px;">
                    <input class = "user-button" onClick = 'printLelet();' type = "button" value = "Nyomtatás" />
                    <input value = "Mégse" name = "close_lelet" type = "button" />
                </div>
                <?php
            }
            die();
        }



        if(isset($_REQUEST['zaro_lelet'])){

            $zaro_id = $_REQUEST['zaro_lelet'];
            $request_lelet = sql_query("SELECT * FROM zaro_leletek WHERE zaro_id = ? ",array($zaro_id));
            $result = sql_fetch_array($request_lelet);
            ?>
            <div class = "lelet-frame" id = "lelet-content" style = "display:block;overflow-y:scroll" ><?php echo $result['zaro_szoveg'] ?></div>
            <div class = "lelet-button-box" style = "margin-top:10px;">
                <input class = "user-button" onClick = 'printLelet();' type = "button" value = "Nyomtatás" />
                <!--<input class = "user-button" onClick = '$(".target-lelet").slideToggle();setTimeout(function(){$(".target-lelet").empty();}, 1000);' type = "button" value = "Bezárás" />-->
                <input value = "Mégse" name = "close_zaro" type = "button" />
            </div>
            <?php
            die();
        }


        if( isset( $_REQUEST['uj_lelet'] ))
        {
            $textarea_name = "uj-lelet-page";
            $patient 	   = sql_fetch_array( sql_query( "SELECT * FROM felhasznalok WHERE id=?", array( $_SESSION["patient_id"] )));
            $medic 		   = sql_fetch_array( sql_query( "SELECT * FROM orvosok 	 WHERE id=?", array( $_SESSION['medic_id'] )));

            if($patient['irsz'] != "" && $patient['varos'] != "") $lakcim = $patient['irsz']." ".$patient['varos'].", ".$patient['utca'];
            else $lakcim = "";

            $patient_details_segment = "<h1 id = 'title' style = 'font-family:Calibri;text-align:center;color:#000000;font-weight:bold;'>Lelet</h1>";
            $patient_details_segment.= "<table id = 'patient-details' style = 'color:#000;border:none'>";
            $patient_details_segment.= "	<tr><td style = 'border:none;font-family:Calibri;font-size:16px;font-weight:bold'>Páciens neve:</td><td style = 'border:none;font-family:Calibri;font-size:16px'>{$patient['nev']}</td></tr>";
            $patient_details_segment.= "	<tr><td style = 'border:none;font-family:Calibri;font-size:16px;font-weight:bold'>Születési hely, idő:</td><td style = 'border:none;font-family:Calibri;font-size:16px'>".($patient['szulhely'] != "" ? $patient['szulhely']."," : "").$patient['szuldatum']."</td></tr>";
            $patient_details_segment.= "	<tr><td style = 'border:none;font-family:Calibri;font-size:16px;font-weight:bold'>TAJ szám:</td><td style = 'border:none;font-family:Calibri;font-size:16px'>{$patient['taj']}</td></tr>";
            $patient_details_segment.= "	<tr><td style = 'border:none;font-family:Calibri;font-size:16px;font-weight:bold'>Leánykori neve:</td><td style = 'border:none;font-family:Calibri;font-size:16px'>{$patient['anyjaneve']}</td></tr>";
            $patient_details_segment.= "	<tr><td style = 'border:none;font-family:Calibri;font-size:16px;font-weight:bold'>Lakcíme:</td><td style = 'border:none;font-family:Calibri;font-size:16px'>{$lakcim}</td></tr>";
            $patient_details_segment.= "</table>";

            if($_SESSION['medic_id'] == "")
            {
                $medical_seals = "&lt;span style = 'color:#000000;font-family:Calibri;font-size:16px' id = 'signature' &gt;
						  ".date("Y.m.d",strtotime("Now"))."&lt;br/&gt;&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-family:Calibri;font-size:16px'&gt;
						  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-family:Calibri;font-size:16px'&gt;&lt;/span&gt;&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-size:11px;color:#949494;font-family:Calibri'&gt; *A lelet aláírás és pecsét nélkül is érvényes! &lt;/span&gt;
						  &lt;/span&gt;&lt;br/&gt;&lt;br/&gt;&lt;br/&gt;";
            }
            else
            {
                $medical_seals = "&lt;span style = 'color:#000000;font-family:Calibri;font-size:16px' id = 'signature' &gt;
						  ".date("Y.m.d",strtotime("Now"))."&lt;br/&gt;&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-family:Calibri;font-size:16px'&gt;
						  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-family:Calibri;font-size:16px'&gt;".$medic['nev']."(&lt;span id='seal-place'&gt;&lt;/span&gt;)&lt;/span&gt;&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-size:11px;color:#949494;font-family:Calibri'&gt; *A lelet aláírás és pecsét nélkül is érvényes! &lt;/span&gt;
						  &lt;/span&gt;&lt;br/&gt;&lt;br/&gt;&lt;br/&gt;";
            }

            ?>
            <script>
                tinyMCE.init({
                    mode : 'specific_textareas',
                    editor_selector : 'mceEditor',
                    content_style: 'body{ color:#000; font-family: Calibri }',
                    height: 842,
                    width: 595
                });
            </script>
            <div style = "margin-top:5px;">
                <div class = "currently-text-container" style = "display:none;"></div>
                <div class = "medic-footage" style = "display:none;"><?php echo '"'.$medical_seals.'"' ?></div>
                <table style = "font-size:12px;margin-bottom:10px;">
                    <tr>
                        <td>Pecsétszám: <input type = "textbox" value = "<?php echo (isset($medic['pecsetszam'])?$medic['pecsetszam']:"") ?>" id = "pecsetszam"  /></td>
                        <td></td>
                    </tr>
                    <tr><td colspan = "2" >Milyen vizsgálati eredményt kíván hozzáadni?</td></tr>
                    <tr>
                        <td>
                            <select id = "minta-lista" style = "margin-top:10px;">
                                <option value = "empty"> - Válassz mintát! - </option>
                                <?php
                                echo medTemplateFilter($medic['szurestipusok']);
                                /*$request_mintak = sql_query("SELECT * FROM lelet_mintak");
                                while($minta = sql_fetch_array($request_mintak)){
                                    ?>
                                    <option value = "<?php echo $minta['lm_id'] ?>"><?php echo $minta['lelet_nev'].($minta['lelet_ver'] != ""?"({$minta['lelet_ver']})":"") ?></option>
                                    <?php
                                }*/
                                ?>
                            </select>
                            <input onClick = 'add_lelet($("#minta-lista").val(),"<?php echo $textarea_name ?>")' name = "lelet_hozzadas" type = "button" value = "Kiválasztás"/>
                        </td>
                    </tr>
                </table>
            </div>

            <!--Lelet szöveg helye-->
            <textarea id = "<?php echo $textarea_name ?>" class = "mceEditor" style = "margin-top:10px;display:inline-block">
	<?php echo $patient_details_segment?>
	</textarea>
            <form method = "POST" name = "iForm" style = "display:inline-block">
                <table style = "display:inline-block">
                    <tr>
                        <td>
                        <td valign="top" style = "padding-left:20px">
                            <table name = "positive-options">
                            </table>
                            <table name = "negative-option">
                            </table>
                        </td>
                        </td>
                    </tr>
                </table>
            </form>
            <div style = "margin-top:10px;">
                <input value = "Lelet mentése" onClick = 'save_iFrame(<?php echo $_SESSION['patient_id'].",".$_SESSION['medic_id'].",\"".$textarea_name."\"" ?>)' type = "button"/>
                <input value = "Nyomtatás" onClick = 'send_iFrame(<?php echo $_SESSION['patient_id'].",".$_SESSION['medic_id'].",\"".$textarea_name."\"" ?>)' type = "button" />
                <input value = "Mégse" name = "close_lelet" type = "button" />
            </div>
            <?php
            die();
        }



        if(isset($_REQUEST['request_lelet'])){
            $lelet = sql_fetch_array(sql_query("SELECT * FROM lelet_mintak WHERE lm_id=?",array($_REQUEST['request_lelet'])));
            echo $lelet['lelet_text'];
            die();
        }
        if(isset($_REQUEST['save_lelet'])){
            $wounds = "";
            for($i = 0; $i <= count($_REQUEST['wounds']); $i++ )
            {
                $wounds = $wounds.";".$_REQUEST['wounds'][$i];
            }
            $wounds = substr($wounds, 1);
            sql_query("INSERT INTO paciens_leletek SET paciens_id=?,lelet_szoveg=?,pecsetszam=?,kelte=NOW(),pozitiv_opciok=?,lelet_type = ? ",array($_SESSION['patient_id'],$_REQUEST['save_lelet'],$_REQUEST['seal_numb'],$wounds, $_REQUEST['tipus']));
            die("Lelet feltöltés sikeres!");
        }

        if(isset($_REQUEST['update_lelet'])){
            $wounds = "";
            for($i = 0; $i <= count($_REQUEST['wounds']); $i++ )
            {
                $wounds = $wounds.";".$_REQUEST['wounds'][$i];
            }
            $wounds = substr($wounds, 1);
            sql_query("UPDATE paciens_leletek SET lelet_szoveg=?, pozitiv_opciok = ? WHERE lelet_id=?",array($_REQUEST["update_lelet"], $wounds, $_REQUEST["lid"]));
            die("Lelet módosítás sikeres!");
        }

        if (isset($_GET["deletelelet"])) {
            $rowf=sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_GET["szerk"])));
            logActivity("paciens",$rowf["id"],"{$rowf["nev"]} lelet törlése");

            sql_query("delete from paciens_leletek where lelet_id=? and paciens_id=?",array($_GET["deletelelet"],$_GET["szerk"]));
            header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

        if ( isset( $_REQUEST['reload_leletlista'] )) {
            echo leletLista( $_SESSION["patient_id"],$_GET['p'],$_GET['user'] );
            die();
        }

        if( isset( $_REQUEST['setCheckboxes'] ))
        {
            $result = sql_fetch_array( sql_query("SELECT pozitiv_opciok FROM lelet_mintak WHERE lm_id = ?", array( $_REQUEST['setCheckboxes'] )));
            $value = explode( ";", $result['pozitiv_opciok'] );

            $htmlout = "<tr><td colspan='2'><h1>Eltérések</h1></td></tr>";
            for( $i = 0; $i < count( $value ); $i++ )
            {
                $htmlout.= "<tr><td><input type = 'checkbox' name = 'wounds[]' value = '".$value[$i]."'></td><td>".$value[$i]."</td></tr>";
            }
            echo $htmlout;
            die();
        }

        if( isset( $_REQUEST['loadnegativeCheck'] ))
        {
            $htmlout = "<tr><td colspan='3'><h1>A lelet negatív</h1></td></tr>";
            $htmlout.= "<tr><td><input type = 'checkbox' name = 'wounds[]' value = 'Negatív'>&nbsp;&nbsp;Negatív</td></tr>";

            echo $htmlout;
            die();
        }

    }

    private function pozitiv_opciok($opc, $type) {
        $result = sql_fetch_array( sql_query("SELECT pozitiv_opciok FROM lelet_mintak WHERE lm_id = ?", array( $type )));
        $value = explode( ";", $result['pozitiv_opciok'] );
        $check = explode( ";", $opc );

        $htmlout = "<tr><td colspan='2'><h1>Eltérések</h1></td></tr>";
        for( $i = 0; $i < count( $value ); $i++ )
        {
            $key = array_search( $value[$i], $check );
            $htmlout.= "<tr><td><input type = 'checkbox' ".( is_numeric( $key ) ? "checked" : "" )." name = 'wounds[]' value = '".$value[$i]."'></td><td>".$value[$i]."</td></tr>";
        }
        echo $htmlout;
    }

    private function negativ_opcio($lelet_id) {
        $result = sql_fetch_array( sql_query("SELECT pozitiv_opciok FROM paciens_leletek WHERE lelet_id = ? AND pozitiv_opciok LIKE '%Negatív%' ", array( $lelet_id )));

        $htmlout = "<tr><td colspan='2'><h1>Negatív a lelet</h1></td></tr>";
        $htmlout.= "<tr><td><input type = 'checkbox' ".($result['pozitiv_opciok']!=""?"checked":"")." name = 'wounds[]' value = 'Negatív'>&nbsp;Negatív</td></tr>";
        return $htmlout;
    }

    public function leletLista($pid,$page,$szerk) {
        $htmlout="";
        $request_leletek = sql_query("SELECT pl.*,lm.lelet_nev  FROM paciens_leletek pl
								  LEFT JOIN lelet_mintak lm ON lm.lm_id = pl.lelet_type
								  WHERE paciens_id =?",array($pid));

        $request_zaro = sql_query("SELECT * FROM zaro_leletek zl
							   LEFT JOIN paciens_leletek pl ON pl.zaro_id = zl.zaro_id
							   WHERE pl.paciens_id = ? AND pl.zaro_id IS NOT NULL
							   GROUP BY zl.zaro_id
							  ", array( $pid ));

        if (sql_num_rows($request_leletek) > 0 || sql_num_rows($request_zaro) > 0) {
            while ($lelet = sql_fetch_array($request_leletek)) {
                if($lelet['lelet_type'] != ''){
                    $htmlout.="<div><a onClick='open_lelet({$lelet["lelet_id"]});return false;' href='#'>".$lelet['lelet_nev']." - ".date("Y-m-d",strtotime($lelet['kelte']))."</a>";
                } else {
                    $htmlout.="<div><a onClick='open_lelet({$lelet["lelet_id"]});return false;' href='#'>Lelet - ".date("Y-m-d",strtotime($lelet['kelte']))."</a>";
                }
                while ($zaro = sql_fetch_array($request_zaro)) {
                    $htmlout.="<div><a onClick='open_zaro({$zaro["zaro_id"]});return false;' href='#'>Záró lelet - ".date("Y-m-d",strtotime($zaro['kelte']))."</a>";
                    $htmlout.="</div>";
                }
                if (($_SESSION['pid'] == 98 ) || ($_SESSION['pid'] == 23)) {
                    $htmlout.=" [<a onclick='return confirm(\"Biztosan törli ezt a leletet?\");' href='{$_SERVER["PHP_SELF"]}?page={$page}&szerk={$szerk}&deletelelet={$lelet["lelet_id"]}'>törlés</a>]";
                }
                $htmlout.="</div>";
            }
        }	else {
            $htmlout.="Nincs még lelet kiállítva.";
        }
        return $htmlout;
    }


}