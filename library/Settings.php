<?php

class Booking_Settings
{
    private array $munkaszunetiNapok      = [];

    public array $honaptext               = array("","január","február","március","április","május","június","július","augusztus","szeptember","október","november","december");
    public array $hetnap                  = array("","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap");

    public array $alkalmassagvariaciok    = array (
            "I" => "alkalmas",
            "N" => "alkalmatlan",
            "IN" => "ideiglenesen nem alkalmas",
            "K" => "korlátozottan alkalmas");

    public $chatStatus = 0;

    public function __construct()
    {
        $rows = sql_fetch_array(sql_query("select * from settings"));

        $this->chatStatus = $rows["chat"];

        $munkaszunetiNapok = explode(",",$rows["szunnapok"]);
        foreach ($munkaszunetiNapok as $nap) {
            if (Booking_Constants::SQL_DB == "hungariamed" && isset($_SESSION["helyszindata"]) && $_SESSION["helyszindata"]["id"] == 114) {
                continue;
            }
            $this->munkaszunetiNapok[] = $nap;
        }

        $GLOBALS["honaptext"] = $this->honaptext;
        $GLOBALS["hetnap"] = $this->hetnap;
    }

    public array $validIntervals = [1,2,3,4,5,6,8,10,12,15,20,30,40,45,60];

    public function getMunkaszunetiNapok($locationId = 0):array {
        if (Booking_Constants::SQL_DB == "hungariamed") {
            if ($locationId == 1 && in_array("2024-07-01", $this->munkaszunetiNapok)) {
                $this->munkaszunetiNapok = array_diff($this->munkaszunetiNapok, ["2024-07-01"]);
            }
        }
        return $this->munkaszunetiNapok;
    }

    public function setChatStatus($status) {
        sql_query("update settings set chat=? where id=1", [intval($status)]);
    }
}