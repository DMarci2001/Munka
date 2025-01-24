<?php


class AdminSuzukiStatPage extends AdminCorePage
{
    private $packages = array(
        array("id" => 219, "name" => "Suzuki 45 év alatti férfi csomag", "shortcutPrivateName" => "package_02_type", "shortcutPublicName" => "45 év alatti csomag", "gender" => 2, "ids" => array(219, 222)),
        array("id" => 220, "name" => "Suzuki 45 év feletti férfi csomag", "shortcutPrivateName" => "package_01_type", "shortcutPublicName" => "45 év feletti csomag", "gender" => 2, "ids" => array(220, 221)),
        array("id" => 221, "name" => "Suzuki 40 év feletti nő csomag", "shortcutPrivateName" => "package_01_type", "shortcutPublicName" => "45 év feletti csomag", "gender" => 1, "ids" => array(220, 221)),
        array("id" => 222, "name" => "Suzuki 40 év alatti nő csomag", "shortcutPrivateName" => "package_02_type", "shortcutPublicName" => "45 év alatti csomag", "gender" => 1, "ids" => array(219, 222))
    );

    private $bookings;
    private  $startDate = "0000-00-00";
    private  $endDate = "0000-00-00";
    //private $utils;
    private const cegId = 892;
    private $icons;



    public function __construct()
    {
        parent::__construct();
        $this->startDate = date("Y-01-01", strtotime("this year"));
        $this->endDate = date("Y-m-d", strtotime("now + 3 months"));

        if (!$this->adminUser->suzukiStatAccess()) {
            echo "Nincs jogosultságod!";
            return;
        }

        $facodes = sql_query("SELECT id,facode,megnev FROM szurestipusok WHERE facode<>''")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($facodes as $facode) {
            $this->icons[] = ["id" => $facode["id"], "icon" => $facode["facode"], "name" => $facode["megnev"]];
            //$this->$icons[$facode["id"]] = $facode["facode"];
        }

        $this->bookings = $this->fetch_suzuki_bookings();

        if (isset($_GET["download-excel"])) {
            //$data = $this->fetch_suzuki_ghc_registrations();
            $excelService = new ExcelService();
            $array = [];
            foreach ($this->bookings as $booking) {

                $array[] = array(
                    "Teljesnév" => $booking["nev"],
                    "Szül. dátum" => str_replace("-", ".", $booking["szuldatum"]),
                    "TAJ szám" => $booking["taj"],
                    "Időpont" => str_replace("-", ".", $booking["datum"]),
                    "Csomag" => $booking["megnev"],
                    "Eljött" => $booking["eljott"],
                );
            }

            $excelService->generateXlsxFromArray($array, "A", "N", []);
            $excelService->setFileName("suzuki_menedzserek_" . date("Ymdhis") . ".xlsx");
            $excelService->outputSpreadSheet();
        }

        //$this->utils = new Utils();
        //echo $this->utils->cancelledAppointments();
    }
    /**
     * Kiírom az oldalon a küldött tömböt debugolás céljából.
     * @param array $array  Tartalmazza a kiírandó tömb struktúrát.
     */
    private function debug_array($array)
    {
        echo "<pre>";
        print_r($array);
        echo "</pre>";
        return;
    }

    public function showPage()
    {
        $notificationService = new NotificationService();

        //echo $notificationService->suzukiManagerNotificationList("2024-03-25");

        //echo $this->deleteExpiredReservations();
        $data = $this->arrange_suzuki_stat_data($this->bookings);

        //$this->debug_array($bookings);
        $html = "";
        $html .= "<ul class='nav nav-tabs' id='myTab' role='tablist'>";
        $html .= "    <li class='nav-item' role='presentation'>";
        $html .= "        <button class='nav-link active' id='send-dm-tab' data-bs-toggle='tab' data-bs-target='#send-dm-tab-pane' type='button' role='tab' aria-controls='send-dm-tab-pane' aria-selected='true'><i class='fa-solid fa-list'></i>&nbsp;Foglalás eloszlás</button>";
        $html .= "    </li>";
        $html .= "    <li class='nav-item' role='presentation'>";
        $html .= "        <button class='nav-link' id='previous-send-list-tab' data-bs-toggle='tab' data-bs-target='#previous-send-list-tab-pane' type='button' role='tab' aria-controls='previous-send-list-tab-pane' aria-selected='true'><i class='fa-solid fa-address-book'></i>&nbsp;Résztvevők</button>";
        $html .= "    </li>";
        //$html .= "    <li class='nav-item' role='presentation'>";
        //$html .= "        <button class='nav-link' id='recipient-list-tab' data-bs-toggle='tab' data-bs-target='#recipient-list-tab-pane' type='button' role='tab' aria-controls='recipient-list-tab-pane' aria-selected='false'><i class='fa-solid fa-list-check'></i>&nbsp;Címzett Lista</button>";
        //$html .= "    </li>";
        $html .= "</ul>";
        $html .= "<div class='tab-content' id='myTabContent'>";
        $html .= "    <div class='tab-pane pt-3 ps-3 fade show active' id='send-dm-tab-pane' role='tabpanel' aria-labelledby='profile-tab' tabindex='0'>" . $this->show_suzuki_stat_data_table($data) . "</div>";
        $html .= "    <div class='tab-pane pt-3 ps-3 fade' id='previous-send-list-tab-pane' role='tabpanel' aria-labelledby='home-tab' tabindex='0'>" . $this->show_booked_patient_list() . "</div>";
        //$html .= "    <div class='tab-pane pt-3 ps-3 fade' id='recipient-list-tab-pane' role='tabpanel' aria-labelledby='profile-tab' tabindex='0'>...</div>";
        $html .= "</div>";

        echo $html;
        //$this->show_suzuki_stat_data_table($data);
    }

    private function fetch_suzuki_bookings()
    {
        $array = sql_query("SELECT cast(fogl.datum as date) AS datum,sz.megnev,sz.id,fogl.regdatum,fogl.nev,fogl.szuldatum,fogl.taj,fogl.foglalta,fogl.telefon,fogl.email,fogl.neme,fogl.eljott FROM foglalasok fogl
                        LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
                        WHERE fogl.szurestipusid IN(219,220,221,222) AND fogl.foglalta=\"\" 
                        AND datum BETWEEN '" . $this->startDate . " 00:00:00' AND '" . $this->endDate . " 23:59:59'
                        ORDER BY datum ASC")->fetchAll(PDO::FETCH_ASSOC);

        return $array;
    }

    private function get_bookedTimes($date, $beo, $array)
    {
        //$bookedTimes = 0;
        $keys = array_keys(array_column($this->bookings, "datum"), $date);
        foreach ($keys as $index) {
            if (in_array($this->bookings[$index]["id"], $beo["types"])) {
                $array["overall"]++;
                if ($this->bookings[$index]["neme"] == 1) $array["male"]++;
                if ($this->bookings[$index]["neme"] == 2) $array["female"]++;
            }
        }
        return $array;
    }

    /**
     * Kiszámolom generált időpontok darabszámát.
     * @param date      $date       Vizsgálandó dátum.
     * @param array   $typeId     Szűréstípusok azonosítója.
     */
    private function calc_capacity($data)
    {
        $bookableSlots = 0;
        $idopont = $data["startTime"];
        do {
            $bookableSlots++;
            $idopont = date("H:i", strtotime("{$idopont} + {$data["binterval"]} minutes"));
        } while (strtotime($idopont) < strtotime($data["endTime"]));

        return $bookableSlots;
    }

    /**
     * Ez a funkció meg keresi azon vizsgálatokat a csomagból, amire már nincs foglalható időpont.
     */
    private function search_for_bottleneck($packages, $date)
    {
        $dayCode = date("N", strtotime($date));
        $returnArray = $mixed = [];
        foreach ($packages as $package) {
            $schedules = [];
            //echo "A vizsgált csomag: {$package}<br>";
            //Csomaghoz tartozó vizsgálatok
            $types = sql_query(
                "SELECT szk.szurestipusid,sz.megnev FROM szurescsomagok_kapcs szk
                 LEFT JOIN szurestipusok sz ON sz.id=szurestipusid 
                 WHERE szk.csomagid=?",
                [$package]
            )->fetchAll(PDO::FETCH_ASSOC);

            //Összes lefoglalt időpont az adott napra
            $bookings = sql_query(
                "SELECT id,szurestipusid,orvosassigned,datum FROM foglalasok WHERE helyszinid=1 AND szurestipusid IN(" . implode(",", array_column($types, "szurestipusid")) . ") AND datum BETWEEN ? AND ?",
                [$date . " 00:00:00", $date . " 23:59:59"]
            )->fetchAll(PDO::FETCH_ASSOC);

            //Vizsgálatokhoz tartozó beosztások
            foreach ($types as $key => $value) {
                $schedules[$value["szurestipusid"]] = sql_query(
                    "SELECT id,tol as startTime,ig as endTime,binterval,orvosid,beonap,nap 
                     FROM orvos_beosztas_new 
                     WHERE (nap=? OR beonap=?) AND INSTR(tipusok,?) AND aktiv=1 AND helyszinid=1 AND INSTR(beocegek,?)",
                    [$dayCode, $date, "|{$value["szurestipusid"]}|", "|892|"]
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            //Orvosonként kell definiálni a beosztásokat...
            foreach ($schedules as $examination => $schedule_properties) {
                if (!isset($schedules[$examination]["availability"]["free"])) {
                    $schedules[$examination]["availability"]["free"] = 0;
                }
                
                //A beosztásokból kinézem az orvosok id-jait
                $doctors = array_unique(array_column($schedule_properties, "orvosid"));
                if($date=="2025-01-24" && $examination==10){
                    //echo "Orvosok:<br>";
                    //$this->debug_array($doctors);
                }
                foreach ($doctors as $doctor) {
                    //Megkeresem az orvosokhoz tartozó beosztásokat
                    $doctor_schedules = array_keys(array_column($schedule_properties, "orvosid"), $doctor);
                    $capacity = 0;
                    //Kikalkulálom a beosztások adatai alapján a lehetséges időpontok számát.
                    foreach ($doctor_schedules as $schedule) {
                        $capacity = ($capacity + $this->calc_capacity($schedule_properties[$schedule]));
                    }
                    $schedules[$examination]["availability"][$doctor]["capacity"] = $capacity;
                    //Kikeresem az orvoshoz tartozó foglalásokat és letárolom.
                    $bookedTimes = array_keys(array_column($bookings, "orvosassigned"), $doctor);
                    if($date=="2025-01-24" && $examination==10){
                        //echo "Lefoglalt időpontok: {$examination} - {$doctor}<br>";
                        foreach($bookedTimes as $key){
                            //$this->debug_array($bookings[$key]);
                        }
                    }
                    $schedules[$examination]["availability"][$doctor]["booked"] = count($bookedTimes);
                    $schedules[$examination]["availability"][$doctor]["free"] = ($capacity - count($bookedTimes));
                    $schedules[$examination]["availability"]["free"] = ($schedules[$examination]["availability"]["free"] + ($capacity - count($bookedTimes)));
                    if($date=="2025-01-24" && $examination==10){
                        //echo "Vizsgálat: {$examination} - {$doctor}<br>";
                        //$this->debug_array($schedules[$examination]);
                    }
                    $returnArray[$package]["availability"][$examination]["free"] = $schedules[$examination]["availability"]["free"];
                    if (!isset($mixed[$examination])) {
                        if($returnArray[$package]["availability"][$examination]["free"]<0){
                            $mixed[$examination] = 0;
                        }else{
                            $mixed[$examination] = $returnArray[$package]["availability"][$examination]["free"];
                        }
                        
                    }
                }
                
                //$this->debug_array($schedules[$examination]);
            }
        }
        if($date=="2025-01-24" && $examination==10){
            //$this->debug_array($mixed);
        }
        //$this->debug_array($mixed);

        return $mixed;
    }

    private function _calc_capacity($date, $typeIds)
    {
        $q = sql_query("SELECT * FROM orvos_beosztas_new 
                        WHERE INSTR(beonap,\"{$date}\")
                        AND (INSTR(tipusok,\"|{$typeIds[0]}|\") OR INSTR(tipusok,\"|{$typeIds[1]}|\"))
                        AND INSTR(beocegek,\"|" . self::cegId . "|\")");

        $bookableSlots = 0;
        //Ha napra szól a beosztás
        while ($beosztas = sql_fetch_array($q)) {

            $idopont = $beosztas["tol"];

            do {
                $bookableSlots++;
                $idopont = date("H:i", strtotime("{$idopont} + {$beosztas["binterval"]} minutes"));
            } while (strtotime($idopont) < strtotime($beosztas["ig"]));
        }

        return $bookableSlots;

        /*for($i=0;$i<10;$i++){
        $BookableSlots++;
        $idopont = date("H:i",strtotime("{$idopont} + {$beosztas["binterval"]} minutes"));
        echo strtotime($idopont)."<".strtotime($beosztas["ig"])."<br>";
       }*/
    }

    private function arrange_suzuki_stat_data($bookings)
    {
        //Ez már nem unique O.o
        $data = $this->set_row_by_unique_date();

        /*foreach($bookings as $key=>$value){
            $data = $this->process_db_row_values($data,$value);
        }*/

        return $data;
    }

    /**
     * Létrehozom az adatsorokat dátum értékek egyszeri felhasználásával.
     * @return $data    
     */
    private function set_row_by_unique_date()
    {
        $data = array();
        $beosztas = sql_query("SELECT beonap,nap,tol,ig,tipusok,binterval FROM orvos_beosztas_new 
                        WHERE (INSTR(tipusok,\"|219|\") OR INSTR(tipusok,\"|220|\") OR INSTR(tipusok,\"|221|\") OR INSTR(tipusok,\"|222|\"))
                        AND INSTR(beocegek,\"|" . self::cegId . "|\") AND aktiv=1  ORDER BY beonap ASC");
        //AND beonap >= \"".self::startDate."\"

        while ($beo = sql_fetch_array($beosztas)) {

            if (!empty($beo["tipusok"])) {
                $beo["tipusok"] = substr($beo["tipusok"], 1, -1);
                $beo["tipusok"] = explode("||", $beo["tipusok"]);
            }

            if ($beo["beonap"] != "0000-00-00") {
                $key = array_search(date("Y-m-d", strtotime($beo["beonap"])), array_column($data, "booking_date"));
                if ($key !== false) {
                    $data[$key]["params"][] = array("startTime" => $beo["tol"], "endTime" => $beo["ig"], "binterval" => $beo["binterval"], "types" => $beo["tipusok"]);
                } else {
                    if (strtotime($beo["beonap"]) >= strtotime($this->startDate) && strtotime($beo["beonap"]) <= strtotime($this->endDate)) {
                        $data[] = array("booking_date" => date("Y-m-d", strtotime($beo["beonap"])));
                        $index = $index = array_key_last($data);
                        $data[$index]["params"][] = array("startTime" => $beo["tol"], "endTime" => $beo["ig"], "binterval" => $beo["binterval"], "types" => $beo["tipusok"]);
                    }
                }
            }
            //Ha a beosztás nem fix napokon van, hanem álltalánosan kivan nyitva akkor erre fusson rá:
            if ($beo["beonap"] == "0000-00-00" && $beo["nap"] != 10) {
                $days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
                $endDate = strtotime($this->endDate);
                for ($i = strtotime($days[($beo["nap"] - 1)], strtotime($this->startDate)); $i <= $endDate; $i = strtotime('+1 week', $i)) {
                    $key = array_search(date('Y-m-d', $i), array_column($data, "booking_date"));
                    if ($key !== false) {
                        $data[$key]["params"][] = array("startTime" => $beo["tol"], "endTime" => $beo["ig"], "binterval" => $beo["binterval"], "types" => $beo["tipusok"]);
                    } else {
                        $data[] = array("booking_date" => date("Y-m-d", strtotime(date('Y-m-d', $i))));
                        $index = $index = array_key_last($data);
                        $data[$index]["params"][] = array("startTime" => $beo["tol"], "endTime" => $beo["ig"], "binterval" => $beo["binterval"], "types" => $beo["tipusok"]);
                    }
                }
            }
        }
        $booking_date = [];
        foreach ($data as $key => $value) {
            $booking_date[$key] = $value["booking_date"];
        }
        array_multisort($booking_date, SORT_ASC, $data);

        //$this->debug_array($data);
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
    private function process_db_row_values($data, $value): array
    {
        $dataKey = array_search(date("Y-m-d", strtotime($value["datum"])), array_column($data, "booking_date"));
        $packageKey = array_search($value["megnev"], array_column($this->packages, "name"));

        if ($packageKey !== false) {
            //Csomag meghatározása és foglalt időpontok inicializálása
            if (!isset($data[$dataKey][$this->packages[$packageKey]["shortcutPrivateName"]])) {
                $packageUnit = $data[$dataKey][$this->packages[$packageKey]["shortcutPrivateName"]] = 0;
            } else {
                $packageUnit = $data[$dataKey][$this->packages[$packageKey]["shortcutPrivateName"]];
            }
            $packageUnit++;
            $data[$dataKey][$this->packages[$packageKey]["shortcutPrivateName"]] = $packageUnit;

            //Nem meghatározása és számszerűsítése
            if (!isset($data[$dataKey][$this->packages[$packageKey]["gender"]])) {
                $genderUnit = $data[$dataKey][$this->packages[$packageKey]["gender"]] = 0;
            } else {
                $genderUnit = $data[$dataKey][$this->packages[$packageKey]["gender"]];
            }
            $genderUnit++;
            $data[$dataKey][$this->packages[$packageKey]["gender"]] = $genderUnit;
        }
        //$this->debug_array($data);
        return $data;
    }

    private function show_booked_patient_list()
    {
        $html = "";
        $html .= "<div class='container-xxl mx-3'>";
        $html .= "<div class=\"h6\">Összes foglalás " . date("Y.m.d", strtotime($this->startDate)) . " óta: <strong>" . count($this->bookings) . "db</strong></div>";
        $html .= "<div class=\"h6\">Vizsgálaton résztvettek száma " . date("Y.m.d", strtotime($this->startDate)) . "  óta: <strong>" . count(array_keys(array_column($this->bookings, "eljott"), 1)) . "db</strong></div>";
        $html .= "<div class=\"h6\">Lista letöltése:&nbsp;<a target='_blank' href='?page=suzukistat&download-excel'><img src='https://{$_SERVER["HTTP_HOST"]}/admin/images/excel_icon.png' height='40px'></a></div>";
        $html .= "<table class=\"table table-striped\">";
        $html .= "   <thead>";
        $html .= "       <tr>";
        $html .= "       <th class=\"text-center\" scope=\"col\">#</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Teljesnév</i></th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Szül. dátum</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">TAJ szám</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Időpont</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Csomag</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Eljött</th>";
        $html .= "       </tr>";
        $html .= "   </thead>";
        $html .= "   <tbody>";
        foreach ($this->bookings as $key => $appointment) {
            $html .= "<tr>";
            $html .= "<th class=\"text-center\" scope=\"row\">" . ($key + 1) . ".</th>";
            $html .= "<td class=\"text-center\">{$appointment["nev"]}</td>";
            $html .= "<td class=\"text-center\">" . str_replace("-", ".", $appointment["szuldatum"]) . "</td>";
            $html .= "<td class=\"text-center\">{$appointment["taj"]}</td>";
            $html .= "<td class=\"text-center\">" . str_replace("-", ".", $appointment["datum"]) . "</td>";
            $html .= "<td class=\"text-center\">{$appointment["megnev"]}</td>";
            $html .= "<td class=\"text-center\">" . ($appointment["eljott"] == 1 ? "Eljött" : "Nem jött el") . "</td>";
            $html .= "</tr>";
        }
        $html .= "   </tbody>";
        $html .= "</table>";
        $html .= "</div>";

        return $html;
    }

    /**
     * Ezzel a funkcióval renderelem ki a tábla HTML kódját
     * @param array     $data   Feldolgozott statisztikai adatok.
     */
    private function show_suzuki_stat_data_table($data)
    {
        $html = $tbody = "";
        $overAllAvailableTimes = 0;
        $lang = new Lang();
        $webText = $lang->webText;

        foreach ($data as $key => $value) {
            if(strtotime($value["booking_date"])<strtotime("today")){
                continue;
            }
            $plus45 = ["capacity" => 0, "times" => ["overall" => 0, "male" => 0, "female" => 0],"required"=>0];
            $minus45 = ["capacity" => 0, "times" => ["overall" => 0, "male" => 0, "female" => 0],"required"=>0];
            $male = $female = 0;
            $noTimeLeft = "";
            foreach ($value["params"] as $beo) {
                $plus = $minus = 0; //Ez azért kell, mert multiplikálja az időpontokat a szűréstípusokkal xd remélhetőleg a jövőben ez nem szül problémát majd.
                foreach ($beo["types"] as $type) {
                    //echo "beosztás: ".$type." - {$value["booking_date"]}<br>";
                    if (in_array($type, [219, 222]) && $minus == 0) {
                        $minus++;
                        if ($value["booking_date"] == "2025-01-22") {
                            //echo $value["booking_date"]."<br>";
                            //$this->search_for_bottleneck([219, 222], $value["booking_date"], $beo);
                            //return;
                        }

                        $minus45["capacity"] = ($minus45["capacity"] + $this->calc_capacity($beo));
                        $minus45["times"] = $this->get_bookedTimes($value["booking_date"], $beo, $minus45["times"]);
                        $minus45["required"] = ($minus45["capacity"]-$minus45["times"]["overall"]);
                    }
                    if (in_array($type, [220, 221]) && $plus == 0) {
                        $plus++;
                        //$plus45["bottlenecks"] = $this->search_for_bottleneck([220, 221], $value["booking_date"], $beo);
                        $plus45["capacity"] = ($plus45["capacity"] + $this->calc_capacity($beo));
                        $plus45["times"] = $this->get_bookedTimes($value["booking_date"], $beo, $plus45["times"]);
                        $plus45["required"] = ($plus45["capacity"]-$plus45["times"]["overall"]);
                    }
                }
            }
            $availableTimes = "";
            $required = ($plus45["required"]+$minus45["required"]);
            $bottlenecks = $this->search_for_bottleneck([219, 220, 221, 222], $value["booking_date"]);
            $lowestExamNumber = min($bottlenecks);
            $overAllAvailableTimes = ($overAllAvailableTimes+$lowestExamNumber);
            if($required>$lowestExamNumber && $lowestExamNumber!=0){
                $availableTimes = $lowestExamNumber;
            }
            
            $issues = array_keys($bottlenecks, 0);
            /*if($value["booking_date"]=="2025-01-24"){
                echo "összes vizsgálat:<br>";
                $this->debug_array($bottlenecks);
                echo "elfogyott vizsgálatok:<br>";
                $this->debug_array($issues);
            }*/

           
            //$this->debug_array($this->icons);
            foreach ($issues as $examination) {
                if ($noTimeLeft != "") {
                    $noTimeLeft .= "&nbsp;";
                }
                $key = array_search($examination,array_column($this->icons,"id"));
                $noTimeLeft .= "<span title='{$this->icons[$key]["name"]}'>{$this->icons[$key]["icon"]}</span>";
            }

            $male = ($minus45["times"]["male"] + $plus45["times"]["male"]);
            $female = ($minus45["times"]["female"] + $plus45["times"]["female"]);


            $tbody .= "<tr class=\"h6\">";
            //$html .= "<th class=\"text-center\" scope=\"row\">" . ($key + 1) . ".</th>";
            $tbody .= "<td class=\"text-center\">" . str_replace("-", ".", $value["booking_date"]) . ", " . ucfirst($webText["hetnap"][date("N", strtotime($value["booking_date"]))]) . "</td>";
            $tbody .= "<td class=\"text-center\">{$plus45["times"]["overall"]}/{$plus45["capacity"]}</td>";
            $tbody .= "<td class=\"text-center\">{$minus45["times"]["overall"]}/{$minus45["capacity"]}</td>";
            $tbody .= "<td class=\"text-center\">{$availableTimes}</td>";
            $tbody .= "<td class=\"text-center\">{$noTimeLeft}</td>";
            $tbody .= "<td class=\"text-center\">" . ($male > 0 ? $male . "db" : " - ") . "</td>";
            $tbody .= "<td class=\"text-center\">" . ($female > 0 ? $female . "db" : " - ") . "</td>";
            $tbody .= "</tr>";
            if ($value["booking_date"] == "2025-01-22") {
                //break;
                //echo $value["booking_date"]."<br>";
                //$this->search_for_bottleneck([219, 222], $value["booking_date"], $beo);
                //return;
            }
        }

        $html .= "<div class='container-xxl mx-3'>";
        $html .= "<div class=\"h6\">Összes foglalás " . date("Y.m.d", strtotime($this->startDate)) . " óta: <strong>" . count($this->bookings) . "db</strong></div>";
        $html .= "<div class=\"h6\">Vizsgálaton résztvettek száma " . date("Y.m.d", strtotime($this->startDate)) . "  óta: <strong>" . count(array_keys(array_column($this->bookings, "eljott"), 1)) . "db</strong></div>";
        $html .= "<div class=\"h6\">Elérhető időpontok: <strong>{$overAllAvailableTimes}</strong></div>";
        $html .= "<table class=\"table table-striped\">";
        $html .= "   <thead>";
        $html .= "       <tr class=\"h5\">";
        //$html .= "       <th class=\"text-center\" scope=\"col\">#</th>";
        $html .= "       <th class=\"text-center\" title='Rendelési dátum' scope=\"col\"><i class='fa-regular fa-calendar-days'></i></th>";
        $html .= "       <th class=\"text-center\" title='45 év feletti csomag' scope=\"col\"><i class='fa-solid fa-4'></i><i class='fa-solid fa-5'></i><i class='fa-solid fa-plus'></i></th>";
        $html .= "       <th class=\"text-center\" title='45 év alatti csomag' scope=\"col\"><i class='fa-solid fa-4'></i><i class='fa-solid fa-5'></i><i class='fa-solid fa-minus'></i></th>";
        $html .= "       <th class=\"text-center\" title='Elérhető időpontok' scope=\"col\"><i class='fa-solid fa-circle-check'></i></th>";
        $html .= "       <th class=\"text-center\" title='Problémás vizsgálatok' scope=\"col\"><i class='fa-solid fa-triangle-exclamation'></i></th>";
        $html .= "       <th class=\"text-center\" title='Férfi foglalások' scope=\"col\"><i class='fa-solid fa-mars'></i></th>";
        $html .= "       <th class=\"text-center\" title='Női foglalások' scope=\"col\"><i class='fa-solid fa-venus'></i></th>";
        $html .= "       </tr>";
        $html .= "   </thead>";
        $html .= "   <tbody>";
        $html .=        $tbody;
        $html .= "   </tbody>";
        $html .= "</table>";
        $html .= "</div>";


        return $html;
    }
    private function _show_suzuki_stat_data_table($data)
    {
        $html = "";

        $lang = new Lang();
        $webText = $lang->webText;

        $html .= "<div class=\"h6\">Összes foglalás " . date("Y.m.d", strtotime($this->startDate)) . " óta: <strong>" . count($this->fetch_suzuki_bookings()) . "db</strong></div>";
        $html .= "<table class=\"table table-striped\">";
        $html .= "   <thead>";
        $html .= "       <tr class=\"h5\">";
        $html .= "       <th class=\"text-center\" scope=\"col\">#</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Foglalható dátum</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">45 év feletti csomag</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">45 év alatti csomag</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Lefoglalt férfi időpontok (db)</th>";
        $html .= "       <th class=\"text-center\" scope=\"col\">Lefoglalt női időpontok (db)</th>";
        $html .= "       </tr>";
        $html .= "   </thead>";
        $html .= "   <tbody>";
        foreach ($data as $key => $value) {
            $package_01_type = array_search("45 év feletti csomag", array_column($this->packages, "shortcutPublicName"));
            $package_02_type = array_search("45 év alatti csomag", array_column($this->packages, "shortcutPublicName"));
            $package_01_limit = $this->calc_capacity($value, $this->packages[$package_01_type]["ids"]);
            $package_02_limit = $this->calc_capacity($value, $this->packages[$package_02_type]["ids"]);

            if (isset($value["package_01_type"]) && $package_01_limit == $value["package_01_type"]) {
                $color_01 = "color:#178f64;font-weight:bold";
            } else {
                $color_01 = "";
            }
            if (isset($value["package_02_type"]) && $package_02_limit == $value["package_02_type"]) {
                $color_02 = "color:#178f64;font-weight:bold";
            } else {
                $color_02 = "";
            }

            $html .= "<tr class=\"h6\">";
            $html .= "<th class=\"text-center\" scope=\"row\">" . ($key + 1) . ".</th>";
            $html .= "<td class=\"text-center\">" . str_replace("-", ".", $value["booking_date"]) . ", " . ucfirst($webText["hetnap"][date("N", strtotime($value["booking_date"]))]) . "</td>";
            $html .= "<td class=\"text-center\" style=\"{$color_01}\">" . ($package_01_limit != 0 ? (isset($value["package_01_type"]) ? $value["package_01_type"] . "/" . $package_01_limit : "0/" . $package_01_limit) : " - ") . "</td>";
            $html .= "<td class=\"text-center\" style=\"{$color_02}\">" . ($package_02_limit != 0 ? (isset($value["package_02_type"]) ? $value["package_02_type"] . "/" . $package_02_limit : "0/" . $package_02_limit) : " - ") . "</td>";
            $html .= "<td class=\"text-center\">" . (isset($value["2"]) ? $value["2"] . "db" : " - ") . "</td>";
            $html .= "<td class=\"text-center\">" . (isset($value["1"]) ? $value["1"] . "db" : " - ") . "</td>";
            $html .= "</tr>";
        }
        $html .= "   </tbody>";
        $html .= "</table>";

        echo $html;

        return;
    }
}
