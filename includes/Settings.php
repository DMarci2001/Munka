<?php

class Booking_Settings
{
    private $munkaszunetiNapok;

    const SITE_NAME = 'KeltexMed időpontfoglalás';
    const SITE_LOGO = 'images/hmm_logo.png';
    const GENERATED_PASSWORD_LENGTH = 8;
    const PASSWORD_LENGTH_MIN = 6;
    const PASSWORD_LENGTH_MAX = 20;

    public function __construct()
    {
        $rows = sql_fetch_array(sql_query("select * from settings"));
        $this->munkaszunetiNapok = explode(",",$rows["szunnapok"]);
    }


    public function getMunkaszunetiNapok() {
        return $this->munkaszunetiNapok;
    }

}