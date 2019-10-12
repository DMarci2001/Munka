<?php

class AdminProtocolService {
    public function __construct()
    {

        if(isset($_REQUEST['set_protocol'])){

            //$request = sql_query("SELECT * FROM labor_sablonok WHERE lab_id = ".$protocol_id." ");
            $protocol = sql_fetch_array( sql_query("SELECT * FROM labor_sablonok WHERE lab_id = ? ", array( $_REQUEST['set_protocol'] )));

            ?>
            <table class = "s1-modul-table" id = "kemia-lista" style = "margin-right:5px;">
                <tr><td><i>Kémia</i></td></tr>
                <?php if( $protocol['kemia_protocol'] != "" ) echo get_protocol( $protocol['kemia_protocol'] ) ?>
            </table>
            <div class = "s1-modul-table" style = "margin-right:5px;border:none;">
                <table class = "s2-modul-table" id = "hematologia-lista" style = "margin-bottom:5px;">
                    <tr><td><i>Hematológia</i></td></tr>
                    <?php if( $protocol['hematologia_protocol'] != "" ) echo get_protocol( $protocol['hematologia_protocol'] ) ?>
                </table>
                <table class = "s2-modul-table" id = "veralvadas-lista" style = "margin-bottom:5px;height:130px;">
                    <tr><td><i>Véralvadás</i></td></tr>
                    <?php if( $protocol['veralvadas_protocol'] != "" ) echo get_protocol( $protocol['veralvadas_protocol'] ) ?>
                </table>
                <table class = "s2-modul-table" id = "egyeb-lista" style = "height:171.5px;">
                    <tr><td><i>Egyéb</i></td></tr>
                    <?php if( $protocol['egyeb_protocol'] != "" ) echo get_protocol( $protocol['egyeb_protocol'] ) ?>
                </table>
            </div>
            <div class = "s1-modul-table" id = "s3-scales">
                <table class = "s2-modul-table" id = "vizelet-lista">
                    <tr><td><i>Vizelet</i></td></tr>
                    <?php if( $protocol['vizelet_protocol'] != "" ) echo get_protocol( $protocol['vizelet_protocol'] ) ?>
                </table>
                <table class = "s2-modul-table" id = "tumormarker-lista">
                    <tr><td><i>Tumormarker</i></td></tr>
                    <?php if( $protocol['tumor_protocol'] != "" ) echo get_protocol( $protocol['tumor_protocol'] ) ?>
                </table>
                <table class = "s2-modul-table" id = "third-modul-table">
                    <tr><td><i>Speciális labor</i></td></tr>
                    <tr rowspan = "8"><td style="">
                            <textarea></textarea>
                        </td></tr>
                </table>
            </div>
            <?php
            die();
        }

    }

    public function get_protocol( $key ){
        $htmlout = "";
        if($key!="")
        {
            $request = sql_query("SELECT * FROM labor_mintak WHERE minta_id IN( ".$key." )");
            if(sql_num_rows($request) > 0)
            {
                while( $result = sql_fetch_array( $request ))
                {
                    $htmlout.= "<tr><td>{$result['minta_nev']}</td></tr>";
                }
            }
        }
        //$htmlout = "SELECT * FROM labor_mintak WHERE minta_id IN( ".$key." )";
        return $htmlout;
    }


}