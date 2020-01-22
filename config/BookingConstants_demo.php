<?php

//A demo beállításokat tartalmazó osztály

class Booking_Constants {
    const IS_DEMO                   = true;
    const SITE_NAME                 = 'Időpontfoglalás DEMO';
    const SITE_LOGO                 = 'images/hmm_logo.png';
    const SITE_ADMIN_LOGO           = 'hmm_logo_nagy.png';
    const SITE_FAVICON              = 'hmm_favicon.png';
    const SITE_DOMAIN               = 'hungariamed.hu';
    const SITE_PROTOCOL             = 'https';
    const GENERATED_PASSWORD_LENGTH = 8;
    const PASSWORD_LENGTH_MIN       = 6;
    const PASSWORD_LENGTH_MAX       = 20;

    const DOCUMENT_PATH             = "/var/doc_demo/";

    const FOOTER_ADDRESS_PARAM      = "<b>DEMO KFT</b><br/>Budapesti egészségközpont</b><br/>1135 Budapest, Teszt u. 33-35.";
    const FOOTER_CONTACT_PARAM      = "<b>Telefon:</b><br/>+36 1 123 4567,<br/>+36 30 123 4567";
    const FOOTER_COPYRIGHT          = "Hungariamed";
    const COMPANY_NAME              = "Demo Kft.";
    const COMPANY_NAME_SHORT        = "Hungariamed"; //lehetőleg rövid egy szavas cégnév (sms-ben is ez megy)

    const NO_REPLY_ADDRESS          = "noreply@hungariamed.hu";
    const RESERVATION_TO_ADDRESS    = "bejelentkezes@hungariamed.hu";
    const USER_BCC_MAIL             = "";

    const SQL_USER                  = "hungariamed";
    const SQL_PASS                  = "hmedpass";
    const SQL_HOST                  = "localhost";
    const SQL_DB                    = "hungariamed_demo";

    const SOAP_API_NAMESPACE        = "https://demo.hungariamed.hu/foApi.php";
    const SOAP_API_PASSWORD         = "eBahch9w";
}
