<?php

//A hungariamed beállításokat tartalmazó osztály

class Booking_Constants {
    const IS_DEMO                   = false;
    const SITE_NAME                 = 'Hungáriamed időpontfoglalás';
    const SITE_LOGO                 = '/images/logo-retina.png';
    const SITE_ADMIN_LOGO           = 'hmm_logo_nagy.png';
    const SITE_FAVICON              = 'hmm_favicon.png';
    const SITE_DOMAIN               = 'hungariamed.hu';
    const SITE_PROTOCOL             = 'https';
    const GENERATED_PASSWORD_LENGTH = 8;
    const PASSWORD_LENGTH_MIN       = 6;
    const PASSWORD_LENGTH_MAX       = 20;

    const DOCUMENT_PATH             = "/var/doc/";
    const APP_PATH                  = "/var/www/marci/onlinebejelentkezes/";

    const ADATVEDELMI_URL           = "https://hungariamed.hu/images/adatkezeles.pdf";

    const FOOTER_ADDRESS_PARAM      = "<b>HUNGÁRIA MED-M KFT</b><br/>Budapesti egészségközpont</b><br/>1135 Budapest, Jász u. 33-35.";
    const FOOTER_CONTACT_PARAM      = "<b>Telefon:</b><br/>+36 1 800 9333,<br/>+36 30 633 0961";
    const FOOTER_COPYRIGHT          = "Hungariamed";
    const COMPANY_NAME              = "Hungáriamed-M Kft.";
    const COMPANY_NAME_SHORT        = "Hungária Med-M"; //lehetőleg rövid egy szavas cégnév (sms-ben is ez megy)
    const COMPANY_ADDRESS           = "1135 Budapest, Jász u. 33-35.";
    const COMPANY_EMAIL             = "info@hungariamed.hu";

    const MAIN_URL                  = "https://bejelentkezes.hungariamed.hu";
    const NO_REPLY_ADDRESS          = "noreply@hungariamed.hu";
    const RESERVATION_TO_ADDRESS    = "bejelentkezes@hungariamed.hu";
    const USER_BCC_MAIL             = "usermail@hungariamed.hu";

    const SQL_USER                  = "hungariamed";
    const SQL_PASS                  = "hmedpass";
    const SQL_HOST                  = "localhost";
    const SQL_DB                    = "hungariamed";

    const SOAP_API_NAMESPACE        = "https://bejelentkezes.hungariamed.hu/foApi.php";
    const SOAP_API_PASSWORD         = "lW3vfbmh0kekCiUq";

    const FO_CONNECTION_ENABLED     = true;
    const FO_API_PASSWORD           = "lW3vfbmh0kekCiUq";
    const FO_API_TEST_PASSWORD      = "wzUpTVrpexTh";
    const FO_IFC_NAME               = "HUNGARIA_MED_M";

    const DokiRex_Email             = "ugyfelkapcsolat@hungariamed.hu";
	const DokiRex_Password          = "HMMadmin12345.";
	const DokiRex_dbName		    = "hungaria";

    const DOKIREX_V2_EMAIL          = "api@hungariamed.hu";
    const DOKIREX_V2_PASSWORD       = "qmqkUSwDdPAM!can";
    const DOKIREX_V2_DB             = "hungaria";

    const DOKIREX_V2_KELTEXMED_EMAIL = "api@keltexmed.hu";
    const DOKIREX_V2_KELTEXMED_PASSWORD = "L4oJNRjFFtq!Tz!y";
    const DOKIREX_V2_KELTEXMED_DB = "BE602C35";

    //simplePay public sandbox
    const SIMPLEPAY_MERCHANT_ID_SANDBOX     = "PUBLICTESTHUF";
    const SIMPLEPAY_MERCHANT_SECRET_SANDBOX = "FxDa5w314kLlNseq2sKuVwaqZshZT5d6";

    //simplePay hmm sandbox
    const SIMPLEPAY_MERCHANT_ID     = "S076901";
    const SIMPLEPAY_MERCHANT_SECRET = "SfYyNetaA1sYYppo0a2S4yv7Sy1iR3Js";

    const SEEME_API_KEY             = "1uivd276x0rvuo9v97k6z4x7axmaukoi5828";

    const API_KEY                   = "04ab0c03-7e9f-468f-8d37-edc1a639d013";

    const DEFAULT_PLACE_IDS         = [1]; //jász utca
    const DEFAULT_COMPANY_ID        = 11;

    const REPORT_MAILS              = "jns@jns.hu, jnsmobil@gmail.com, marton.gergely@hungariamed.hu";

    const TUDOSZURES_ID             = 58;
    const LABOR_ID                  = 48;
    const HALLASVIZSGALAT_ID        = 0;
    const COVID_ID                  = 0;

    const GOOGLE_MAPS_API_KEY       = "AIzaSyAZxXvfDzq149JL3wd-gJkiFy_OLsq25b8";

    const SPEKTRUM_KIERTEKELES_ID   = 870;
}
