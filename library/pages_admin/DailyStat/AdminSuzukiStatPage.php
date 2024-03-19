<?php


class AdminSuzukiStatPage extends AdminCorePage
{
    private $packages = array(
        array("id"=>219, "name"=>"Suzuki 45 év alatti férfi csomag","shortcutPrivateName"=>"package_02_type", "shortcutPublicName"=>"45 év alatti csomag","gender"=>2,"ids"=>array(219,222)),
        array("id"=>220, "name"=>"Suzuki 45 év feletti férfi csomag","shortcutPrivateName"=>"package_01_type", "shortcutPublicName"=> "45 év feletti csomag", "gender"=>2,"ids"=>array(220,221)),
        array("id"=>221, "name"=>"Suzuki 45 év feletti nő csomag", "shortcutPrivateName"=>"package_01_type", "shortcutPublicName"=> "45 év feletti csomag", "gender"=>1,"ids"=>array(220,221)),
        array("id"=>222, "name"=>"Suzuki 45 év alatti nő csomag", "shortcutPrivateName"=>"package_02_type", "shortcutPublicName"=>"45 év alatti csomag", "gender"=>1,"ids"=>array(219,222))
    );

    private const cegId = 892;

    private const startDate = "2024-03-08";

    public function __construct()
    {
        parent::__construct();
        
        if (!$this->adminUser->statAccess()) {
            echo "Nincs jogosultságod!";
            return;
        }

        if (!$this->adminUser->suzukiStatAccess()) {
            echo "Nincs jogosultságod!";
            return;
        }
    }
    /**
     * Kiírom az oldalon a küldött tömböt debugolás céljából.
     * @param array $array  Tartalmazza a kiírandó tömb struktúrát.
    */
    private function debug_array($array){
        echo "<pre>";
        print_r($array);
        echo "</pre>";
        return;
    }

    public function showPage()
    {
        $bookings = $this->fetch_suzuki_bookings();
        $data = $this->arrange_suzuki_stat_data($bookings);
        $this->show_suzuki_stat_data_table($data);
    }

    private function fetch_suzuki_bookings()
    {
        $array = sql_query("SELECT fogl.datum,sz.megnev,fogl.regdatum,fogl.nev,fogl.foglalta,fogl.telefon,fogl.email FROM foglalasok fogl
                        LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
                        WHERE fogl.szurestipusid IN(219,220,221,222) AND fogl.foglalta=\"\"
                        ORDER BY datum ASC")->fetchAll(PDO::FETCH_ASSOC);

        return $array;
    }



    /**
     * Kiszámolom generált időpontok darabszámát.
     * @param date      $date       Vizsgálandó dátum.
     * @param array   $typeId     Szűréstípusok azonosítója.
    */
    private function calc_capacity($date,$typeIds){
        $q = sql_query("SELECT * FROM orvos_beosztas_new 
                        WHERE INSTR(beonap,\"{$date}\") 
                        AND (INSTR(tipusok,\"|{$typeIds[0]}|\") OR INSTR(tipusok,\"|{$typeIds[1]}|\"))
                        AND INSTR(beocegek,\"|".self::cegId."|\")");

        $bookableSlots = 0;
        
        while($beosztas = sql_fetch_array($q)){
            
            $idopont = $beosztas["tol"];
    
           do{
            $bookableSlots++;
            $idopont = date("H:i",strtotime("{$idopont} + {$beosztas["binterval"]} minutes"));
           }while(strtotime($idopont)<strtotime($beosztas["ig"]));
    
        }
        
       return $bookableSlots;

       /*for($i=0;$i<10;$i++){
        $BookableSlots++;
        $idopont = date("H:i",strtotime("{$idopont} + {$beosztas["binterval"]} minutes"));
        echo strtotime($idopont)."<".strtotime($beosztas["ig"])."<br>";
       }*/
    }

    private function arrange_suzuki_stat_data($bookings){
        $data = $this->set_row_by_unique_date();
        foreach($bookings as $key=>$value){
            $data = $this->process_db_row_values($data,$value);
        }

        return $data;
    }

    /**
     * Létrehozom az adatsorokat dátum értékek egyszeri felhasználásával.
     * @return $data    
     */
    private function set_row_by_unique_date(){
        $data = array();
        $beosztas = sql_query("SELECT beonap FROM orvos_beosztas_new 
                        WHERE (INSTR(tipusok,\"|219|\") OR INSTR(tipusok,\"|220|\") OR INSTR(tipusok,\"|221|\") OR INSTR(tipusok,\"|222|\"))
                        AND INSTR(beocegek,\"|".self::cegId."|\") AND beonap >= \"".self::startDate."\" ORDER BY beonap ASC");

        while($beo=sql_fetch_array($beosztas)){
            $key = array_search(date("Y-m-d",strtotime($beo["beonap"])),array_column($data,"booking_date"));
            if($key!==false){
                
            }else{
                $data[] = array("booking_date"=>date("Y-m-d",strtotime($beo["beonap"])));
            }
        }
        return $data;
    }
    /*private function set_row_by_unique_date($data,$value):array
    {
        $key = array_search(date("Y-m-d",strtotime($value["datum"])),array_column($data,"booking_date"));
        if($key!==false){
            
        }else{
            $data[] = array("booking_date"=>date("Y-m-d",strtotime($value["datum"])));
        }
        return $data;
    }*/

    /**
     * Hozzárendelem a statisztikai adatokhoz az aktuális adatsor értékeit.
     * 
     * @param array     $data   Feldolgozás allati statisztikai adatok.
     * @param array     $value  Aktuális adatsor az adatbázisból.
     *
     * @return $data    
    */
    private function process_db_row_values($data,$value):array
    {   
        $dataKey = array_search(date("Y-m-d",strtotime($value["datum"])),array_column($data,"booking_date"));
        $packageKey = array_search($value["megnev"],array_column($this->packages,"name"));
        
        if($packageKey!==false){
            //Csomag meghatározása és foglalt időpontok inicializálása
            if(!isset($data[$dataKey][$this->packages[$packageKey]["shortcutPrivateName"]])){
                $packageUnit = $data[$dataKey][$this->packages[$packageKey]["shortcutPrivateName"]] = 0;
            }else{
                $packageUnit = $data[$dataKey][$this->packages[$packageKey]["shortcutPrivateName"]];
            }
           $packageUnit++;
           $data[$dataKey][$this->packages[$packageKey]["shortcutPrivateName"]] = $packageUnit;

           //Nem meghatározása és számszerűsítése
           if(!isset($data[$dataKey][$this->packages[$packageKey]["gender"]])){
            $genderUnit = $data[$dataKey][$this->packages[$packageKey]["gender"]] = 0;
           }else{
            $genderUnit = $data[$dataKey][$this->packages[$packageKey]["gender"]];
           }
           $genderUnit++;
           $data[$dataKey][$this->packages[$packageKey]["gender"]] = $genderUnit;
        }
        return $data;
    }

    /**
     * Ezzel a funkcióval renderelem ki a tábla HTML kódját
     * @param array     $data   Feldolgozott statisztikai adatok.
    */
    private function show_suzuki_stat_data_table($data){
        $html = "";


        $html.= "<div class=\"h6\">Összes foglalás ".date("Y.m.d",strtotime(self::startDate))." óta: <strong>".count($this->fetch_suzuki_bookings())."db</strong></div>";
        $html.= "<table class=\"table table-striped\">";
        $html.= "   <thead>";
        $html.= "       <tr class=\"h5\">";
        $html.= "       <th class=\"text-center\" scope=\"col\">#</th>";
        $html.= "       <th class=\"text-center\" scope=\"col\">Foglalható dátum</th>";
        $html.= "       <th class=\"text-center\" scope=\"col\">45 év feletti csomag</th>";
        $html.= "       <th class=\"text-center\" scope=\"col\">45 év alatti csomag</th>";
        $html.= "       <th class=\"text-center\" scope=\"col\">Lefoglalt férfi időpontok (db)</th>";
        $html.= "       <th class=\"text-center\" scope=\"col\">Lefoglalt női időpontok (db)</th>";
        $html.= "       </tr>";
        $html.= "   </thead>";
        $html.= "   <tbody>";
        foreach($data as $key=>$value){
            $package_01_type = array_search("45 év feletti csomag",array_column($this->packages,"shortcutPublicName"));
            $package_02_type = array_search("45 év alatti csomag",array_column($this->packages,"shortcutPublicName"));
            $package_01_limit = $this->calc_capacity($value["booking_date"],$this->packages[$package_01_type]["ids"]);
            $package_02_limit = $this->calc_capacity($value["booking_date"],$this->packages[$package_02_type]["ids"]);

            if(isset($value["package_01_type"]) && $package_01_limit==$value["package_01_type"]){
                $color_01 = "color:#178f64;font-weight:bold";
            }else{
                $color_01 = "";
            }
            if(isset($value["package_02_type"]) && $package_02_limit==$value["package_02_type"]){
                $color_02 = "color:#178f64;font-weight:bold";
            }else{
                $color_02 = "";
            }

            $html.= "<tr class=\"h6\">";
            $html.= "<th class=\"text-center\" scope=\"row\">".($key+1).".</th>";
            $html.= "<td class=\"text-center\">".str_replace("-",".",$value["booking_date"])."</td>";
            $html.= "<td class=\"text-center\" style=\"{$color_01}\">".($package_01_limit!=0?(isset($value["package_01_type"])?$value["package_01_type"]."/".$package_01_limit:"0/".$package_01_limit):" - ")."</td>";
            $html.= "<td class=\"text-center\" style=\"{$color_02}\">".($package_02_limit!=0?(isset($value["package_02_type"])?$value["package_02_type"]."/".$package_02_limit:"0/".$package_02_limit):" - ")."</td>";
            $html.= "<td class=\"text-center\">".(isset($value["2"])?$value["2"]."db":" - ")."</td>";
            $html.= "<td class=\"text-center\">".(isset($value["1"])?$value["1"]."db":" - ")."</td>";
            $html.= "</tr>";
        }
        $html.= "   </tbody>";
        $html.= "</table>";

        echo $html;

        return;
    }
}