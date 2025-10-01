<?php

class AdminSuzukiGhcReglistPage extends AdminCorePage
{
    private const cegId = 892;


    public function __construct()
    {
        parent::__construct();

        if (!$this->adminUser->suzukiGHCRegAccess()) {
            echo "Nincs jogosultságod!";
            return;
        }

        if (isset($_GET["download-excel"])) {
            $data = $this->fetch_suzuki_ghc_registrations();

            $excelService = new ExcelService();
            $excelService->generateXlsxFromArray($data, "A", "N", array("B","C","F"));
            $excelService->setFileName("suzuki_ghc_" . date("Ymdhis") . ".xlsx");
            $excelService->outputSpreadSheet();
        }
    }

    public function showPage()
    {
        $html = "";

        $data = $this->fetch_suzuki_ghc_registrations();
        $html .= $this->show_table($data);
        echo $html;
    }

    private function fetch_array($array)
    {
        echo "<pre>";
        print_r($array);
        echo "</pre>";
    }

    private function fetch_suzuki_ghc_registrations()
    {
        $array = sql_query("SELECT felh.nev as \"Teljesnév\",felh.taj as \"TAJ\",felh.torzsszam as \"Törzszám\",sz.megnev as \"Csomag\",
                                   felh.email as \"E-mail\",felh.telefon as \"Telefonszám\",REPLACE(felh.szuldatum,\"-\",\".\") as \"Szül. dátum\",
                                   CONCAT(felh.irsz,\" \",felh.varos,\", \",felh.utca) as \"Lakcím\",
                                   REPLACE(felh.regtime,\"-\",\".\") as \"Regisztráció\",dieta_description as \"Diéta\",
                                   if(felh.szallitas=1,\"Kér szállítást\",\"Nem kér szállítást\") as \"Szállítás\",
                                   if(felh.otp_penztar=1,\"Van\",\"Nincs\") as \"OTP egészségpénztár\",
                                   if(felh.family_planning=1,\"Részt vesz\",\"\") as \"Családtervezés\",
                                   if(felh.children_development=1,\"Részt vesz\",\"\") as \"Gyermekfejlesztés\",
                                   GROUP_CONCAT(REPLACE(fogl.datum,\"-\",\".\")) AS \"Időpont\",
                                   IF(fogl.eljott=1,REPLACE(fogl.eljottidopont,\"-\",\".\"),\"\") AS \"Megjelenés időpontja\"
                            FROM felhasznalok felh
                            LEFT JOIN ghc_segedtabla ghc ON ghc.torzsszam=felh.torzsszam 
                            LEFT JOIN szurestipusok sz ON sz.id=ghc.csomagid
                            LEFT JOIN foglalasok fogl ON fogl.taj=felh.taj AND fogl.cegid=1403 AND fogl.szurestipusid IN(216,217) AND fogl.datum>'2025-09-14 23:59:59'
                            WHERE felh.cegid in(1403) AND felh.id NOT IN(119511,119822)
                            GROUP BY felh.id
                            ORDER BY felh.nev ASC")->fetchAll(PDO::FETCH_ASSOC);

        return $array;
    }

    private function getAllRegistrations($data){
        $count = 0;
        foreach($data as $key=>$value){
            if(!empty($value["Időpont"])){
                $count++;
            }
        }
        return $count;
    }

    private function show_table($data)
    {

        $data = array_replace($data,array_fill_keys(array_keys($data, null),''));
        $registrationsNumb = count($data);
        $reservationNumb = $this->getAllRegistrations($data);
        $html = "";
        $html .= "<div>";
        $html .= "  <a target=\"_blank\" href=\"https://{$_SERVER["HTTP_HOST"]}/admin/?page=suzukighcreglist&download-excel\"><img style=\"cursor:pointer\"  title=\"Excel fájl letöltése\" src=\"https://{$_SERVER["HTTP_HOST"]}/admin/images/excel_icon.png\" height=\"40\"></a>";
        $html .= "  <span style=\"font-size:16px;\"><i>Regisztráció/foglalás arány:</i> <strong>{$registrationsNumb} / {$reservationNumb} (".(round(($reservationNumb/$registrationsNumb)*100))."%)</strong></span>";
        $html .= "</div>";
        
        //Itt kéne az összes napot kipakolni vhogy
        //$html .= $this->showDailyStatus();
        
        $html .= "<table class=\"table table-striped\">";
        $html .= "   <thead>";
        $html .= "       <tr class=\"h5\">";
        $html .= "       <th class=\"text-center\" scope=\"col\">#</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Teljesnév</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Szül. dátum</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">TAJ</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">E-mail</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Telefonszám</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Lakcím</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">OTP egészségpénztár</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Szállítás</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Diéta</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Családtervezés</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Gyermekfejlesztés</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Csomag</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Regisztráció</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Időpont</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Megjelenés időpontja</th>";
        $html .= "       </tr>";
        $html .= "   </thead>";
        $html .= "   <tbody>";
        foreach ($data as $key => $value) {

            $html .= "<tr style=\"font-size:14px\">";
            $html .= "<th class=\"text-center\" scope=\"row\">" . ($key + 1) . ".</th>";
            $html .= "<td class=\"text-center\">{$value["Teljesnév"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Szül. dátum"]}</td>";
            $html .= "<td class=\"text-center\">{$value["TAJ"]}</td>";
            $html .= "<td class=\"text-center\">{$value["E-mail"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Telefonszám"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Lakcím"]}</td>";
            $html .= "<td class=\"text-center\">{$value["OTP egészségpénztár"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Szállítás"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Diéta"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Családtervezés"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Gyermekfejlesztés"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Csomag"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Regisztráció"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Időpont"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Megjelenés időpontja"]}</td>";
            $html .= "</tr>";
        }
        $html .= "   </tbody>";
        $html .= "</table>";

        return $html;
    }

    private function showDailyStatus(){
        $html = "";

        

        $html.= "<table class=\"table\">";
        $html.= "    <thead class=\"text-center\">";
        $html.= "        <tr>";
        $html.= "        <th scope=\"col\"></th>";
        $html.= "        <th scope=\"col\">2024.10.02<br>(Szerda)</th>";
        //$html.= "        <th scope=\"col\">2024.10.03<br>(Csütörtök)</th>";
        $html.= "        <th scope=\"col\">2024.10.04<br>(Péntek)</th>";

        $html.= "        <th scope=\"col\">2024.10.07<br>(Hétfő)</th>";
        $html.= "        <th scope=\"col\">2024.10.08<br>(Kedd)</th>";
        $html.= "        <th scope=\"col\">2024.10.09<br>(Szerda)</th>";
        //$html.= "        <th scope=\"col\">2024.10.10<br>(Csütörtök)</th>";
        $html.= "        <th scope=\"col\">2024.10.11<br>(Péntek)</th>";

        $html.= "        <th scope=\"col\">2024.10.14<br>(Hétfő)</th>";
        $html.= "        <th scope=\"col\">2024.10.15<br>(Kedd)</th>";
        $html.= "        <th scope=\"col\">2024.10.16<br>(Szerda)</th>";
        //$html.= "        <th scope=\"col\">2024.10.17<br>(Csütörtök)</th>";
        $html.= "        <th scope=\"col\">2024.10.18<br>(Péntek)</th>";
        $html.= "        </tr>";
        $html.= "    </thead>";
        $html.= "    <tbody class=\"text-center\">";
        $html.= "        <tr>";
        $html.= "           <td class=\"text-center fw-bold\">Senior</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-02",217)."</td>";
        //$html.= "           <td>".$this->calcDailyBookingRate("2024-10-03",217)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-04",217)."</td>";

        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-07",217)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-08",217)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-09",217)."</td>";
        //$html.= "           <td>".$this->calcDailyBookingRate("2024-10-10",217)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-11",217)."</td>";

        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-14",217)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-15",217)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-16",217)."</td>";
        //$html.= "           <td>".$this->calcDailyBookingRate("2024-10-17",217)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-18",217)."</td>";
        $html.= "        </tr>";
        $html.= "        <tr>";
        $html.= "           <td class=\"text-center fw-bold\">Standard</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-02",216)."</td>";
        //$html.= "           <td>".$this->calcDailyBookingRate("2024-10-03",216)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-04",216)."</td>";

        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-07",216)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-08",216)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-09",216)."</td>";
        //$html.= "           <td>".$this->calcDailyBookingRate("2024-10-10",216)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-11",216)."</td>";

        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-14",216)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-15",216)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-16",216)."</td>";
        //$html.= "           <td>".$this->calcDailyBookingRate("2024-10-17",216)."</td>";
        $html.= "           <td>".$this->calcDailyBookingRate("2024-10-18",216)."</td>";
        $html.= "        </tr>";
        $html.= "    </tbody>";
        $html.= "</table>";

        return $html;
    }

    /**
     * Ki kalkulálom az adott napra foglalható csomagok számát és a foglalási adatok alapján visszadok egy foglalási arányt.
     * 216-os csomagid a Standard és a 217-es a Senior.
     * @param   date    $date       Vizsgálandó dátum.
     * @param   int     $package    Válaszottt csomag azonosító.
    */
    private function calcDailyBookingRate($date,$package):string{

        $beosztas = sql_query("SELECT * FROM orvos_beosztas_new 
                               WHERE beonap=? AND INSTR(beocegek,'|904|') AND INSTR(tipusok,?)",[$date,"|".$package."|"])->fetch(PDO::FETCH_ASSOC);

        $foglalasok = sql_query("SELECT count(id) as foglalasok FROM foglalasok 
                                     WHERE helyszinid=? AND szurestipusid = ?
                                     AND datum > \"{$beosztas["beonap"]} 00:00:00\" 
                                     AND datum < \"{$beosztas["beonap"]} 23:59:59\"",array($beosztas["helyszinid"],$package))->fetch(PDO::FETCH_ASSOC);

        //Kalkulációk
        $start = strtotime($beosztas["tol"]);
        $end = strtotime($beosztas["ig"]);
        $mins = ($end - $start) / 60;
        $idopontok = round(($mins/$beosztas["binterval"]));
        $percent = round((($foglalasok["foglalasok"]/$idopontok)*100));

        return "{$idopontok} / {$foglalasok["foglalasok"]} ({$percent}%)";
    }
}
