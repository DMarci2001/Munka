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
            $html.="<h2>Kedves {$this->profile["nev"]}!</h2>";
            $html.="Köszönjük, hogy a Magyar Suzuki Zrt. és a Hungária Med-M Kft. által szervezett munkavállalói szűrővizsgálat (GHC) mellett döntött.<br><br>";
            $html.="<strong>Vizsgálatok időpontja:</strong> 2024. október 02. - 2024. október 18.<br><br>";
            $html.="<strong>Időpontfoglalás kezdete:</strong> 2024. szeptember 02.<br><br>";

            $html.="<strong>Az Ön szűrőcsomagja:</strong> {$this->profile["szurestipusNev"]}<br><br>";

            $html.="<strong>Vizsgálatok helyszíne:</strong><br>";
            $html.="<ul style=\"margin-left:10px\">";
            $html.="<li style=\"list-style: disc;\">Suzuki Aréna</li>";
            $html.="<li style=\"list-style: disc;\">2500 Esztergom, Helischer József út 5.</li>";
            $html.="</ul>";

            $html.="<strong>Vizsgálatokkal kapcsolatos értesítések:</strong><br>";
            
            $html.="<ul style=\"margin-left:10px\">";
            $html.=" <li style=\"list-style: disc;\">Regisztrációjáról a Magyar Suzuki Zrt. HR és Társasági Támogatások Osztálya tájékoztatást kap.</li>";
            $html.=" <li style=\"list-style: disc;\">Szűrővizsgálatainkra 2024 szeptember 02-tól foglalhat időpontot, melyre e-mailben és SMS-ben is felhívjuk figyelmét.</li>";
            $html.="</ul>";

            $html.="<strong>Egészségpénztári tagság:</strong><br>";

            $html.="<ul style=\"margin-left:10px\">";
            $html.=" <li style=\"list-style: disc;\">A szűrővizsgálatokon való részvételhez OTP Országos Egészség- és Önsegélyező Pénztári tagság szükséges.</li>";
            $html.=" <li style=\"list-style: disc;\">Amennyiben még nem rendelkezik tagsággal, a szűrővizsgálatokat megelőzően a Magyar Suzuki Zrt. munkatársai segítséget nyújtanak a belépéshez.</li>";
            $html.="</ul>";

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
