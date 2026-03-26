<?php

//A keltexmed beállításokat tartalmazó osztály

class Booking_Constants {
    const IS_DEMO                   = false;
    const SITE_NAME                 = 'KeltexMed időpontfoglalás';
    const SITE_LOGO                 = 'images/keltexmed_logo_v2.png';
    const SITE_ADMIN_LOGO           = 'keltexmed_logo_v2.png';
    const SITE_FAVICON              = 'keltexmed_logo.png';
    const SITE_DOMAIN               = 'keltexmed.hu';
    const SITE_PROTOCOL             = 'http';
    const GENERATED_PASSWORD_LENGTH = 8;
    const PASSWORD_LENGTH_MIN       = 6;
    const PASSWORD_LENGTH_MAX       = 20;

    const DOCUMENT_PATH             = "/var/doc_keltexmed/";
    const APP_PATH                  = "/var/www/onlinebejelentkezes_keltexmed/";

    const ADATVEDELMI_URL           = "https://keltexmed.hu/site/images/ADATVEDELMI_TAJEKOZTATO_keltexmed_v.pdf";

    const FOOTER_ADDRESS_PARAM      = "<b>KeltexMed<br/>Egészségügyi Szolgáltató Kft.</b><br/><br/>Budapest, 1117 Fehérvári út 44.<br/>Csonka János Irodaház, I. emelet";
    const FOOTER_CONTACT_PARAM      = "<b>Telefon:</b><br/>+36 1 203 0091<br/><br/><b>E-mail:</b><br/>keltexmed@keltexmed.hu";
    const FOOTER_COPYRIGHT          = "KeltexMed";
    const COMPANY_NAME              = "Keltexmed Kft.";
    const COMPANY_NAME_SHORT        = "Keltexmed"; //lehetőleg rövid egy szavas cégnév (sms-ben is ez megy)
    const COMPANY_ADDRESS           = "Budapest, 1117 Fehérvári út 44.";
    const COMPANY_EMAIL             = "info@keltexmed.hu";

    const MAIN_URL                  = "https://bejelentkezes.keltexmed.hu";
    const NO_REPLY_ADDRESS          = "noreply@keltexmed.hu";
    const RESERVATION_TO_ADDRESS    = "bejelentkezes@keltexmed.hu";
    const USER_BCC_MAIL             = "";

    const SQL_USER                  = "hungariamed";
    const SQL_PASS                  = "hmedpass";
    const SQL_HOST                  = "localhost";
    const SQL_DB                    = "keltexmed";

    const SQL_USER_COMMON           = "hungariamed";
    const SQL_PASS_COMMON           = "hmedpass";
    const SQL_HOST_COMMON           = "localhost";
    const SQL_DB_COMMON             = "hungariamed";

    const SOAP_API_NAMESPACE        = "https://bejelentkezes.keltexmed.hu/foApi.php";
    const SOAP_API_PASSWORD         = "KMa7PnbLPkl9KRTp";
    const SOAP_API_PASSWORD2        = "25rDE89ojTRD7u";

    const FO_CONNECTION_ENABLED     = true;
    const FO_API_PASSWORD           = "KMa7PnbLPkl9KRTp";
    const FO_API_PASSWORD_BERCSENYI = "25rDE89ojTRD7u";
    const FO_API_TEST_PASSWORD      = "KMa7PnbLPkl9KRTp";
    const FO_IFC_NAME               = "KELTEX_MED";
    const FO_IFC_NAME_BERCSENYI     = "KELTEX_MED_BERCSENYI";

    const DokiRex_Email             = "admin@keltexmed.hu";
	const DokiRex_Password          = "KELTEXadmin123.";
	const DokiRex_dbName		    = "BE602C35";

    const DokiRex_HMM_Email         = "ugyfelkapcsolat@hungariamed.hu";
    const DokiRex_HMM_Password      = "HMMadmin12345.";
    const DokiRex_HMM_dbName		= "hungaria";

    const DOKIREX_V2_EMAIL          = "api@keltexmed.hu";
    const DOKIREX_V2_PASSWORD       = "L4oJNRjFFtq!Tz!y";
    const DOKIREX_V2_DB             = "BE602C35";

    const DOKIREX_V2_HMM_EMAIL      = "api@hungariamed.hu";
    const DOKIREX_V2_HMM_PASSWORD   = "qmqkUSwDdPAM!can";
    const DOKIREX_V2_HMM_DB         = "hungaria";

    //simplePay public sandbox
    const SIMPLEPAY_MERCHANT_ID_SANDBOX     = "PUBLICTESTHUF";
    const SIMPLEPAY_MERCHANT_SECRET_SANDBOX = "FxDa5w314kLlNseq2sKuVwaqZshZT5d6";

    //simplePay hmm sandbox
    //const SIMPLEPAY_MERCHANT_ID     = "S076901";
    //const SIMPLEPAY_MERCHANT_SECRET = "SfYyNetaA1sYYppo0a2S4yv7Sy1iR3Js";

    //simplePay keltexmed
    const SIMPLEPAY_MERCHANT_ID     = "S581901";
    const SIMPLEPAY_MERCHANT_SECRET = "Rfbi1e3e21J3LibsPeRsTy6eZjzs43BW";

    const SEEME_API_KEY             = "1uivd276x0rvuo9v97k6z4x7axmaukoi5828";

    const API_KEY                   = "e23f8b75-9d88-4ad1-8149-12ece3ff9ce9";

    //const DEFAULT_PLACE_IDS         = [292, 328]; //fehérvári út
    const DEFAULT_PLACE_IDS         = [372, 328]; //bicskei út
    const DEFAULT_COMPANY_ID        = 11;

    const REPORT_MAILS              = "jns@jns.hu, jnsmobil@gmail.com, marton.gergely@hungariamed.hu";

    const TUDOSZURES_ID             = 102;
    const LABOR_ID                  = 103;
    const HALLASVIZSGALAT_ID        = 85;
    const COVID_ID                  = 101;

    const GOOGLE_MAPS_API_KEY       = "AIzaSyAZxXvfDzq149JL3wd-gJkiFy_OLsq25b8";

    const SPEKTRUM_KIERTEKELES_ID   = 959;
}


