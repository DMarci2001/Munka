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
    const APP_PATH                  = "/var/www/onlinebejelentkezes_keltexmed/";

    const ADATVEDELMI_URL           = "https://hungariamed.hu/images/adatkezeles.pdf";

    const FOOTER_ADDRESS_PARAM      = "<b>DEMO KFT</b><br/>Budapesti egészségközpont</b><br/>1135 Budapest, Teszt u. 33-35.";
    const FOOTER_CONTACT_PARAM      = "<b>Telefon:</b><br/>+36 1 123 4567,<br/>+36 30 123 4567";
    const FOOTER_COPYRIGHT          = "Hungariamed";
    const COMPANY_NAME              = "Demo Kft.";
    const COMPANY_NAME_SHORT        = "Hungariamed"; //lehetőleg rövid egy szavas cégnév (sms-ben is ez megy)

    const MAIN_URL                  = "https://demo.hungariamed.hu";
    const NO_REPLY_ADDRESS          = "noreply@hungariamed.hu";
    const RESERVATION_TO_ADDRESS    = "bejelentkezes@hungariamed.hu";
    const USER_BCC_MAIL             = "";

    const SQL_USER                  = "hungariamed";
    const SQL_PASS                  = "hmedpass";
    const SQL_HOST                  = "localhost";
    const SQL_DB                    = "hungariamed_demo";

    const SOAP_API_NAMESPACE        = "https://demo.hungariamed.hu/foApi.php";
    const SOAP_API_PASSWORD         = "wzUpTVrpexTh";

    const FO_CONNECTION_ENABLED     = true;
    const FO_API_PASSWORD           = "wzUpTVrpexTh";
    const FO_API_TEST_PASSWORD      = "wzUpTVrpexTh";
    const FO_IFC_NAME               = "HUNGARIAMED_3608";

    const DokiRex_Email             = "ugyfelkapcsolat@hungariamed.hu";
	const DokiRex_Password          = "HMMadmin12345.";
	const DokiRex_dbName		    = "hungaria";

    //simplePay public sandbox
    //const SIMPLEPAY_MERCHANT_ID     = "PUBLICTESTHUF";
    //const SIMPLEPAY_MERCHANT_SECRET = "FxDa5w314kLlNseq2sKuVwaqZshZT5d6";

    //simplePay hmm sandbox
    const SIMPLEPAY_MERCHANT_ID     = "S076901";
    const SIMPLEPAY_MERCHANT_SECRET = "SfYyNetaA1sYYppo0a2S4yv7Sy1iR3Js";

    const DEFAULT_PLACE_IDS         = [1]; //jász utca
    const DEFAULT_COMPANY_ID       = 11;

    const REPORT_MAILS              = "jns@jns.hu, jnsmobil@gmail.com";

    const SEEME_API_KEY             = "1uivd276x0rvuo9v97k6z4x7axmaukoi5828";

    const GOOGLE_MAPS_API_KEY       = "AIzaSyAZxXvfDzq149JL3wd-gJkiFy_OLsq25b8";
}
