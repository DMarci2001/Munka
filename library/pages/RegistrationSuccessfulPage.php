<?php

class RegistrationSuccessfulPage extends CorePage {

    private $profile;

    public function __construct()
    {
        parent::__construct();

        $this->profile = sql_query("SELECT felh.*,sz.megnev AS \"szurestipusNev\" FROM felhasznalok felh 
                                    LEFT JOIN ghc_segedtabla ghc ON ghc.torzsszam=felh.torzsszam
                                    LEFT JOIN szurestipusok sz ON sz.id=ghc.csomagid
                                    WHERE felh.id=? AND felh.pass=?
                                   ",array($_GET["id"],$_GET["pass"]))->fetch(PDO::FETCH_ASSOC);
    }

    public function showPage() {
        $webText = $this->lang->webText;
        $html = "";   
        $html.= $this->displayFejlec("Sikeres regisztráció!",true);

        if(CompanyService::isSuzukiGHC()){
            $html.="<h2 style='color:#DE0039'>Kedves {$this->profile["nev"]}!</h2>";
            $html.="<p style='color:#00368F'>Köszönjük, hogy a Magyar Suzuki Zrt. és a Hungária Med-M Kft. által szervezett munkavállalói szűrővizsgálat (GHC) mellett döntött.</p>";
            $html.="<p style='color:#00368F'><strong>Vizsgálatok időpontja:</strong> 2025. szeptember 15. - 2025. október 3.</p>";
            $html.="<p style='color:#00368F'><strong>Időpontfoglalás kezdete:</strong> 2025. augusztus 25.</p>";

            $html.="<p style='color:#00368F'><strong>Az Ön szűrőcsomagja:</strong> {$this->profile["szurestipusNev"]}</p>";

            $html.="<span style='color:#00368F'><strong>Vizsgálatok helyszíne:</strong></span><br>";
            $html.="<ul style=\"margin-left:10px;color:#00368F\">";
            $html.="<li style=\"list-style: disc;\">Suzuki Aréna</li>";
            $html.="<li style=\"list-style: disc;\">2500 Esztergom, Helischer József út 5.</li>";
            $html.="</ul>";

            $html.="<span style='color:#00368F'><strong>Vizsgálatokkal kapcsolatos értesítések:</strong></span><br>";
            
            $html.="<ul style=\"margin-left:10px;color:#00368F\">";
            $html.=" <li style=\"list-style: disc;\">Regisztrációjáról a Magyar Suzuki Zrt. HR & GA osztálya tájékoztatást kap.</li>";
            $html.=" <li style=\"list-style: disc;\">Szűrővizsgálatainkra 2025 augusztus 25-től foglalhat időpontot, melyre e-mailben és SMS-ben is felhívjuk figyelmét.</li>";
            $html.="</ul>";

            $html.="<span style='color:#00368F'><strong>Egészségpénztári tagság:</strong></span><br>";
 
            $html.="<ul style=\"margin-left:10px;color:#00368F\">";
            $html.=" <li style=\"list-style: disc;\">A szűrővizsgálatokon való részvételhez OTP Országos Egészség- és Önsegélyező Pénztári tagság szükséges.</li>";
            $html.=" <li style=\"list-style: disc;\">Amennyiben még nem rendelkezik tagsággal, a szűrővizsgálatokat megelőzően a Magyar Suzuki Zrt. munkatársai segítséget nyújtanak a belépéshez.</li>";
            $html.="</ul>";

            $html .= "      <p style=\"font-size:14px;text-align:left;margin-bottom:0px;color:#00368F\"><strong>Telefonszámaink, ahol érdeklődhet:</strong></p>";
            $html .= "      <ul style=\"margin-left: 10px;text-align:left;color:#00368F\">";
            $html .= "          <li style=\"list-style: disc\"><span  style='font-size:14px'>Suzuki - Teberi Andrea: +3630-122-9084</li>";
            $html .= "          <li style=\"list-style: disc\"><span style='font-size:14px'>Suzuki - Balogh Miklós: +3620-587-8696</li>";
            $html .= "          <li style=\"list-style: disc\"><span style='font-size:14px'>Hungária Med-M - Szabó Melinda: +3670-779-9485</li>";
            $html .= "      </ul>";

            $html.= "<div style=\"margin-bottom:50px\"></div>";
            echo $html;

            $notificaitonService= new NotificationService();
            $notificaitonService->suzuki_ghc_reg_confirmation_notification($this->profile["id"]);
        }

        if(CompanyService::isAstostecCompany()){
            $html.="<h2>Kedves {$this->profile["nev"]}!</h2>";
            $html.="Köszönjük, hogy a Astotec Automotive HU Bt. és a Hungária Med-M Kft. által szervezett munkavállalói szűrővizsgálat mellett döntött.<br><br>";
            $html.="<strong>Vizsgálatok időpontja:</strong> 2024. szeptember 03. - 2024. szeptember 06.<br><br>";
            //$html.="<strong>Időpontfoglalás kezdete:</strong> 2024. szeptember 02.<br><br>";

            $html.="<strong>Vizsgálatok helyszíne:</strong><br>";
            $html.="<ul style=\"margin-left:10px\">";
            $html.="<li style=\"list-style: disc;\">Pápa, Astotec parkoló</li>";
            //$html.="<li style=\"list-style: disc;\">Pápa, Astotec parkoló</li>";
            $html.="</ul>";

            /*$html.="<strong>Vizsgálatokkal kapcsolatos értesítések:</strong><br>";
            
            $html.="<ul style=\"margin-left:10px\">";
            $html.=" <li style=\"list-style: disc;\">Regisztrációjáról a Magyar Suzuki Zrt. HR és Társasági Támogatások Osztálya tájékoztatást kap.</li>";
            $html.=" <li style=\"list-style: disc;\">Szűrővizsgálatainkra 2024 szeptember 02-tól foglalhat időpontot, melyre e-mailben és SMS-ben is felhívjuk figyelmét.</li>";
            $html.="</ul>";*/

            /*$html.="<strong>Egészségpénztári tagság:</strong><br>";

            $html.="<ul style=\"margin-left:10px\">";
            $html.=" <li style=\"list-style: disc;\">A szűrővizsgálatokon való részvételhez OTP Országos Egészség- és Önsegélyező Pénztári tagság szükséges.</li>";
            $html.=" <li style=\"list-style: disc;\">Amennyiben még nem rendelkezik tagsággal, a szűrővizsgálatokat megelőzően a Magyar Suzuki Zrt. munkatársai segítséget nyújtanak a belépéshez.</li>";
            $html.="</ul>";*/

            $html.= "<div style=\"margin-bottom:50px\"></div>";
            echo $html;

            $notificaitonService= new NotificationService();
            $notificaitonService->astotec_reg_confirmation_notification($this->profile["id"]);
        }
        
        if(CompanyService::isFiFi()){
            $html.="<h2>Kedves {$this->profile["nev"]}!</h2>";
            $html.="Itt további információkat jeleníthetünk meg az eseménnyel kapcsolatban.";
            $html.= "<div style=\"margin-bottom:50px\"></div>";
            
            echo $html;
        }
    }
}
