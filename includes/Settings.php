<?php

class Booking_Settings
{
    private $munkaszunetiNapok;

    public $honaptext               = array("","január","február","március","április","május","június","július","augusztus","szeptember","október","november","december");
    public $hetnap                  = array("","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap");
    public $adminszintek            = array("recepció","cégadmin","<b>admin</b>");

    public $alkalmassagvariaciok    = array (
            "I" => "alkalmas",
            "N" => "alkalmatlan",
            "IN" => "ideiglenesen nem alkalmas",
            "K" => "korlátozottan alkalmas");


    public function __construct()
    {
        $rows = sql_fetch_array(sql_query("select * from settings"));
        $this->munkaszunetiNapok = explode(",",$rows["szunnapok"]);

        $GLOBALS["honaptext"] = $this->honaptext;
        $GLOBALS["hetnap"] = $this->hetnap;
    }


    public function getMunkaszunetiNapok() {
        return $this->munkaszunetiNapok;
    }

}