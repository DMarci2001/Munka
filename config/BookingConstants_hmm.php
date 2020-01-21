<?php

//A hungariamed beállításokat tartalmazó osztály

class Booking_Constants {
    const IS_DEMO                   = false;
    const SITE_NAME                 = 'Hungáriamed időpontfoglalás';
    const SITE_LOGO                 = 'images/hmm_logo.png';
    const SITE_ADMIN_LOGO           = 'hmm_logo_nagy.png';
    const SITE_FAVICON              = 'hmm_favicon.png';
    const SITE_DOMAIN               = 'hungariamed.hu';
    const SITE_PROTOCOL             = 'https';
    const GENERATED_PASSWORD_LENGTH = 8;
    const PASSWORD_LENGTH_MIN       = 6;
    const PASSWORD_LENGTH_MAX       = 20;

    const DOCUMENT_PATH             = "/var/doc/";

    const FOOTER_ADDRESS_PARAM      = "<b>HUNGÁRIA MED-M KFT</b><br/>Budapesti egészségközpont</b><br/>1135 Budapest, Jász u. 33-35.";
    const FOOTER_CONTACT_PARAM      = "<b>Telefon:</b><br/>+36 1 800 9333,<br/>+36 30 633 0961";
    const FOOTER_COPYRIGHT          = "Hungariamed";
    const COMPANY_NAME              = "Hungariamed-M Kft.";
    const COMPANY_NAME_SHORT        = "Hungariamed"; //lehetőleg rövid egy szavas cégnév (sms-ben is ez megy)

    const NO_REPLY_ADDRESS          = "noreply@hungariamed.hu";
    const RESERVATION_TO_ADDRESS    = "bejelentkezes@hungariamed.hu";

    const SQL_USER                  = "hungariamed";
    const SQL_PASS                  = "hmedpass";
    const SQL_HOST                  = "localhost";
    const SQL_DB                    = "hungariamed";

    const SOAP_API_NAMESPACE        = "https://bejelentkezes.hungariamed.hu/foApi.php";
    const SOAP_API_PASSWORD         = "WieGie2M";
}
