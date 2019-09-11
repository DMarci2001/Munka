<?php

class Booking_Settings
{
    private $munkaszunetiNapok;

    const SITE_NAME                 = 'KeltexMed időpontfoglalás';
    const SITE_LOGO                 = 'images/hmm_logo.png';
    const SITE_ADMIN_LOGO           = 'keltexmed_logo.png';
    const SITE_FAVICON              = 'hmm_favicon.png';
    const GENERATED_PASSWORD_LENGTH = 8;
    const PASSWORD_LENGTH_MIN       = 6;
    const PASSWORD_LENGTH_MAX       = 20;

    const DOCUMENT_PATH             = "/var/doc_keltexmed/";

    const FOOTER_ADDRESS_PARAM      = "<b>KeltextMed<br/>Egészségügyi Szolgáltató Kft.</b><br/><br/>Budapest, 1117 Fehérvári út 44.<br/>Csonka János Irodaház, I. emelet";
    const FOOTER_CONTACT_PARAM      = "<b>Telefon:</b><br/>+36 1 203 0091<br/><br/><b>E-mail:</b><br/>keltexmed@keltexmed.hu";
    const FOOTER_COPYRIGHT          = "KeltexMed";

    public $honaptext = array("","január","február","március","április","május","június","július","augusztus","szeptember","október","november","december");
    public $hetnap = array("","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap");

    public $adminszintek = array("recepció","cégadmin","<b>admin</b>");


    public $alkalmassagvariaciok = array ("I" => "alkalmas",
            "N" => "alkalmatlan",
            "IN" => "ideiglenesen nem alkalmas", "K" => "korlátozottan alkalmas");

    const ADMIN_DAY_DISPLAY = 7;


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