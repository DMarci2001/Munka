<?php

class Booking_Settings
{
    private $munkaszunetiNapok      = [];

    public $honaptext               = array("","január","február","március","április","május","június","július","augusztus","szeptember","október","november","december");
    public $hetnap                  = array("","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap");

    public $alkalmassagvariaciok    = array (
            "I" => "alkalmas",
            "N" => "alkalmatlan",
            "IN" => "ideiglenesen nem alkalmas",
            "K" => "korlátozottan alkalmas");


    public function __construct()
    {
        $rows = sql_fetch_array(sql_query("select * from settings"));
        $munkaszunetiNapok = explode(",",$rows["szunnapok"]);
        foreach ($munkaszunetiNapok as $nap) {
            if (isset($_SESSION["helyszindata"]) && $_SESSION["helyszindata"]["id"] == 114) {
                continue;
            }
            $this->munkaszunetiNapok[] = $nap;
        }

        $GLOBALS["honaptext"] = $this->honaptext;
        $GLOBALS["hetnap"] = $this->hetnap;
    }

    public $validIntervals = [3,5,6,8,10,15,20,30,40,45,60];

    public function getMunkaszunetiNapok() {
        return $this->munkaszunetiNapok;
    }
}