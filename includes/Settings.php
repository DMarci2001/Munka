<?php

class Booking_Settings
{

    private $munkaszunetiNapok;


    public function __construct()
    {
        $rows = sql_fetch_array(sql_query("select * from settings"));
        $this->munkaszunetiNapok = explode(",",$rows["szunnapok"]);
    }


    public function getMunkaszunetiNapok() {
        return $this->munkaszunetiNapok;
    }

}