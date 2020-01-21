<?php

//A keltexmed beállításokat tartalmazó osztály

class Booking_Constants {
    const IS_DEMO                   = false;
    const SITE_NAME                 = 'KeltexMed időpontfoglalás';
    const SITE_LOGO                 = 'images/hmm_logo.png';
    const SITE_ADMIN_LOGO           = 'keltexmed_logo.png';
    const SITE_FAVICON              = 'hmm_favicon.png';
    const SITE_DOMAIN               = 'keltexmed.hu';
    const SITE_PROTOCOL             = 'http';
    const GENERATED_PASSWORD_LENGTH = 8;
    const PASSWORD_LENGTH_MIN       = 6;
    const PASSWORD_LENGTH_MAX       = 20;

    const DOCUMENT_PATH             = "/var/doc_keltexmed/";

    const FOOTER_ADDRESS_PARAM      = "<b>KeltextMed<br/>Egészségügyi Szolgáltató Kft.</b><br/><br/>Budapest, 1117 Fehérvári út 44.<br/>Csonka János Irodaház, I. emelet";
    const FOOTER_CONTACT_PARAM      = "<b>Telefon:</b><br/>+36 1 203 0091<br/><br/><b>E-mail:</b><br/>keltexmed@keltexmed.hu";
    const FOOTER_COPYRIGHT          = "KeltexMed";
    const COMPANY_NAME              = "Keltexmed Kft.";
    const COMPANY_NAME_SHORT        = "Keltexmed"; //lehetőleg rövid egy szavas cégnév (sms-ben is ez megy)

    const NO_REPLY_ADDRESS          = "noreply@hungariamed.hu";
    const RESERVATION_TO_ADDRESS    = "bejelentkezes@hungariamed.hu";

    const SQL_USER                  = "hungariamed";
    const SQL_PASS                  = "hmedpass";
    const SQL_HOST                  = "localhost";
    const SQL_DB                    = "keltexmed";

    const SOAP_API_NAMESPACE        = "https://bejelentkezes.keltexmed.hu/foApi.php";
    const SOAP_API_PASSWORD         = "Maexa4iu";
}
