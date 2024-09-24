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
                                   REPLACE(felh.regtime,\"-\",\".\") as \"Regisztráció\",
                                   if(felh.szallitas=1,\"Kér szállítást\",\"Nem kér szállítást\") as \"Szállítás\",
                                   if(felh.otp_penztar=1,\"Van\",\"Nincs\") as \"OTP egészségpénztár\",
                                   GROUP_CONCAT(REPLACE(fogl.datum,\"-\",\".\")) AS \"Időpont\"
                            FROM felhasznalok felh
                            LEFT JOIN ghc_segedtabla ghc ON ghc.torzsszam=felh.torzsszam 
                            LEFT JOIN szurestipusok sz ON sz.id=ghc.csomagid
                            LEFT JOIN foglalasok fogl ON fogl.taj=felh.taj AND fogl.cegid=904 AND fogl.szurestipusid=ghc.csomagid AND INSTR(fogl.datum,\"2024\")
                            WHERE felh.cegid in(904) AND felh.id NOT IN(119511,119822)
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
        $html .= "       <th class=\"text-center\" scope=\"col\">Csomag</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Regisztráció</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Időpont</th>";
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
            $html .= "<td class=\"text-center\">{$value["Csomag"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Regisztráció"]}</td>";
            $html .= "<td class=\"text-center\">{$value["Időpont"]}</td>";
            $html .= "</tr>";
        }
        $html .= "   </tbody>";
        $html .= "</table>";

        return $html;
    }
}
