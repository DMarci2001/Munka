<?php

class VaroteremService
{
    public function __construct()
    {
        if (isset($_POST["changeWaitlistRelevantTypes"])) {
            $relevant_exam_types = json_decode($_SESSION["adminuser"]["relevant_exam_types"]);
            if (!empty($relevant_exam_types)) {
                $key = array_search($_POST["changeWaitlistRelevantTypes"], $relevant_exam_types);
            } else {
                $key = false;
            }

            if ($key !== false) {
                unset($relevant_exam_types[$key]);
            } else {
                $relevant_exam_types[] = $_POST["changeWaitlistRelevantTypes"];
            }
            $relevant_exam_types = array_values($relevant_exam_types);
            $relevant_exam_types = json_encode($relevant_exam_types, JSON_PRETTY_PRINT);
            $_SESSION["adminuser"]["relevant_exam_types"] = $relevant_exam_types;

            echo $relevant_exam_types;

            sql_query("UPDATE users SET relevant_exam_types=? WHERE id=?", array($relevant_exam_types, $_SESSION["adminuser"]["id"]));
            die();
        }

        if(isset($_POST["reloadSetupTable"])){
            die($this->setupLathatoVizsgalatok());
        }

        if (isset($_POST["reloadWaitList"])) {
            die($this->waitingRoom());
        }
        if (isset($_POST["reloadWaitListTable"])) {
            die($this->waitlistTable());
        }

        if (isset($_POST["callInToVisit"])) {
            $status = "";

            //Meg kell nézzem, hogy a behívandó pácienst betudom-e hívni egyáltalán a hozzám rendelt orvosra
            //nincs véletlen már egy ellátás alatt álló páciens
            $callin = sql_fetch_array(sql_query("SELECT szurestipusid,statusz FROM varoterem WHERE id=?", array($_POST["callInToVisit"])));

            //Ellenőrzöm, hogy a hozzám rendelt orvosnak van-e beosztása az adott szűrésípusra amire beakarom hívni a pácienst
            if ($checkCapabality = sql_fetch_array(sql_query("SELECT * FROM orvos_beosztas_new WHERE orvosid=? AND tipusok LIKE \"%|{$callin["szurestipusid"]}|%\"", array($_SESSION["adminuser"]["bound_to_doctor"])))) {
                if (!$checkCapacity = sql_fetch_array(sql_query("SELECT * FROM varoterem WHERE orvosid=? AND statusz=\"vizsgalaton\" AND erkeztetve BETWEEN \"" . $_SESSION["setday"] . " 00:00:00\" AND \"" . date("Y-m-d") . " 23:59:59\"", array($_SESSION["adminuser"]["bound_to_doctor"])))) {
                    sql_query(
                        "UPDATE varoterem SET behivas_ideje=?, behivta=?,orvosid=?,statusz = 'vizsgalaton' WHERE id=?",
                        array(date("Y-m-d H:i:s"), $_SESSION["adminuser"]["id"], $_SESSION["adminuser"]["bound_to_doctor"], $_POST["callInToVisit"])
                    );
                    $status = "ok";
                } else {
                    $status = "Hiba: A  orvos épp vizsgál!";
                    //$status = "SELECT * FROM varoterem WHERE orvosid={$_SESSION["adminuser"]["bound_to_doctor"]} AND statusz=\"vizsgalaton\" AND erkeztetve LIKE \"" . date("Y-m-d") . "%\"";
                }
            } else {
                $status = "Hiba: Az orvos nem tudja ellátni a szakrendelést!";
            }

            die(json_encode(array("html" => $this->waitlistTable(), "status" => $status)));
        }

        if (isset($_POST["addToWaitList"])) {
            $status = "";
            if ($alreadyexists = sql_fetch_array(sql_query("SELECT * FROM varoterem WHERE fid=?", array($_POST["addToWaitList"])))) {
                $status = "already exists";
            } else {
                $foglalas = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?", array($_POST["addToWaitList"])));
                $ugyfelszam = $this->generate_ugyfelszam($_SESSION["setday"],$foglalas["taj"]);
                sql_query("INSERT INTO varoterem SET fid=?, szurestipusid=?, erkeztetve=?, hozzadta=?, statusz=?, orvos_pref=?, taj=?, ugyfelszam=?", array($_POST["addToWaitList"], $foglalas["szurestipusid"], date("Y-m-d H:i:s"), $_SESSION["adminuser"]["id"], "varakozik",$_POST["oid"], $foglalas["taj"],$ugyfelszam));
                $status = "ok";
            }

            die(json_encode(array("html" => $this->waitlistTable(), "status" => $status)));
        }

        if (isset($_POST["removeFromWaitList"])) {
            $status = "";
            if ($exists = sql_fetch_array(sql_query("SELECT * FROM varoterem WHERE id=?", array($_POST["removeFromWaitList"])))) {
                $status = "ok";
                sql_query("DELETE FROM varoterem WHERE id=?", array($_POST["removeFromWaitList"]));
            } else {
                $status = "Not Exists";
            }
            die(json_encode(array("html" => $this->waitlistTable(), "status" => $status)));
        }

        if (isset($_POST["changeBoundToDoctor"])) {
            sql_query("UPDATE users SET bound_to_doctor = ? WHERE id=?", array($_POST["changeBoundToDoctor"], $_SESSION["adminuser"]["id"]));
            $_SESSION["adminuser"]["bound_to_doctor"] = $_POST["changeBoundToDoctor"];
            die();
        }

        if(isset($_POST["returnToWaitingRoom"])){
            $status = "";
            if ($exists = sql_fetch_array(sql_query("SELECT * FROM varoterem WHERE id=?", array($_POST["returnToWaitingRoom"])))) {
                $status = "ok";
                sql_query("UPDATE varoterem SET behivta=NULL, orvosid=NULL, behivas_ideje=NULL, statusz=\"varakozik\" WHERE id=?", array($_POST["returnToWaitingRoom"]));
            } else {
                $status = "Not Exists";
            }
            die(json_encode(array("html" => $this->waitlistTable(), "status" => $status)));
        }

        if(isset($_POST["finishExamination"])){
            $status = "";
            if ($exists = sql_fetch_array(sql_query("SELECT * FROM varoterem WHERE id=?", array($_POST["finishExamination"])))) {
                $status = "ok";
                sql_query("UPDATE varoterem SET vizsgalat_befejezve=?, statusz=\"vizsgalat_kesz\" WHERE id=?", array(date("Y-m-d H:i:s"),$_POST["finishExamination"]));
            } else {
                $status = "Not Exists";
            }
            die(json_encode(array("html1" => $this->waitlistTable(),"html2"=> $this->finishedExamsTable(), "status" => $status)));
        }

        if(isset($_POST["update_wl_data"])){
            $create = 0;
            //Azt akarom elérni, hogy ha rákattintok egyre és nem aktív, akkor nyissa meg és minden mást rajta kívül zárja be.
            if(!isset($_SESSION["wl-objects"][$_POST["type"]][$_POST["id"]])){
                $create++;
            }
            if(in_array($_POST["type"],array("waiting-button"))){
                unset($_SESSION["wl-objects"][$_POST["type"]]);
            }else{
                if(isset($_SESSION["wl-objects"][$_POST["type"]][$_POST["id"]])){
                    unset($_SESSION["wl-objects"][$_POST["type"]][$_POST["id"]]);
                } 
            }

            if($create){
                $_SESSION["wl-objects"][$_POST["type"]][$_POST["id"]] = "show";
            }
            die();
        }  
    }

    public function waitingRoom()
    {
        $html = "";
        $ul = "style=\"list-style: none;padding: 0;margin: 0;display: flex;flex-direction: column;flex-wrap: wrap;height: 200px;float:left\"";
        $li = "style=\"width:200px;margin:5px;text-align:center;vertical-align:middle;background:#0a0;color:#fff;padding:5px;border-radius:5px;cursor:pointer\"";

        $html .= "<ul class=\"waitlist nav nav-tabs\" id=\"myTab\" role=\"tablist\">";
        $html .= "<li class=\"nav-item\" role=\"presentation\">";
        $html .= "    <button class=\"nav-link hmm-red-hover active\" id=\"waitlist-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#waitlist-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"waitlist-tab-pane\" aria-selected=\"true\"><i style=\"font-size:22px\" class=\"fa-solid fa-house-medical-circle-exclamation\"></i>&nbsp;Várólista</button>";
        $html .= "</li>";
        $html .= "<li class=\"nav-item\" role=\"presentation\">";
        $html .= "    <button class=\"nav-link hmm-red-hover\" id=\"finished-exam-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#finished-exam-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"finished-exams-tab-pane\" aria-selected=\"false\"><i style=\"font-size:22px\" class=\"fa-solid fa-house-medical-circle-check\"></i>&nbsp;Befejezett vizsgálatok</button>";
        $html .= "</li>";
        $html .= "<li class=\"nav-item\" role=\"presentation\">";
        $html .= "    <button class=\"nav-link hmm-red-hover\" id=\"profile-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#cancelled-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"cancelled-tab-pane\" aria-selected=\"false\"><i style=\"font-size:22px\" class=\"fa-solid fa-house-medical-circle-xmark\"></i>&nbsp;Nem jött el</button>";
        $html .= "</li>";
        $html .= "</ul>";
        $html .= "<div class=\"tab-content\" id=\"myTabContent\">";
        $html .= "<div class=\"tab-pane fade show active\" id=\"waitlist-tab-pane\" role=\"tabpanel\" aria-labelledby=\"waitlist-tab\" tabindex=\"0\">";
        $html .= "  <button style=\"font-size:12px;\" class=\"btn btn-secondary\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#waitlist-setup-table\" aria-expanded=\"false\" aria-controls=\"waitlist-setup-table\">";
        $html .= "      <i class=\"fa-solid fa-sliders\"></i>&nbsp;Vizsgálatok testreszabása";
        $html .= "  </button>";
        //$html .= "<div  id=\"collapseExamSetupPanel\">";
        $html .= "  <div class=\"collapse\" id=\"waitlist-setup-table\">{$this->setupLathatoVizsgalatok()}</div>";
        //$html .=    ;
        $html .= "  <div id=\"waitlist-table\">{$this->waitlistTable()}</div>";
        $html .= "</div>";
        $html .= "<div class=\"tab-pane fade\" id=\"finished-exam-tab-pane\" role=\"tabpanel\" aria-labelledby=\"finished-exam-tab\" tabindex=\"0\">";
        $html .= "  <div id=\"finished-exams-table\">{$this->finishedExamsTable()}</div>";
        $html .= "</div>";
        $html .= "<div class=\"tab-pane fade\" id=\"profile-tab-pane\" role=\"tabpanel\" aria-labelledby=\"profile-tab\" tabindex=\"0\">...</div>";
        $html .= "</div>";



        return $html;
    }

    public function finishedExamsTable()
    {
        $html = "";
        $numb = 1;
        $html .= "<h5><a class=\"badge bg-secondary mt-2 ms-2\" data-bs-toggle=\"collapse\" href=\"#collapseWidthExample\" role=\"button\" aria-expanded=\"false\" aria-controls=\"multiCollapseExample1\"><i class=\"fa-solid fa-filter\"></i></a></h5>";
        $html .= "<div class=\"collapse collapse-horizontal\" id=\"collapseWidthExample\">";
        $html .= "    <div class=\"container\">";
        $html .= "       <div class=\"row row-cols-auto\" style=\"min-width:800px\">";
        $html .= "           <div class=\"col\">";
        $html .= "               <div class=\"input-group input-group-sm mb-1 mt-2\">";
        $html .= "                   <label class=\"input-group-text\" for=\"inputGroupSelect01\"><i class=\"fa-solid fa-building\"></i></label>";
        $html .= "                   <select class=\"form-select form-select-sm \" id=\"inputGroupSelect01\" style=\"max-width:300px\" aria-label=\".form-select-sm example\">";
        $html .= "                       <option selected>Szűrés cégre</option>";
        $html .= "                       <option value=\"1\">Cég</option>";
        $html .= "                   </select>";
        $html .= "               </div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col\">";
        $html .= "               <div class=\"input-group input-group-sm mb-1 mt-2\">";
        $html .= "                   <label class=\"input-group-text\" for=\"inputGroupSelect01\"><i class=\"fa-solid fa-paste\"></i></label>";
        $html .= "                   <select class=\"form-select form-select-sm \" id=\"inputGroupSelect01\" style=\"max-width:300px\" aria-label=\".form-select-sm example\">";
        $html .= "                       <option selected>Szűrés vizsgálatra</option>";
        $html .= "                       <option value=\"2\">Vizsgálat</option>";
        $html .= "                   </select>";
        $html .= "               </div>";
        $html .= "           </div>";
        $html .= "           <div class=\"col\">";
        $html .= "               <div class=\"input-group input-group-sm mb-1 mt-2\">";
        $html .= "                   <label class=\"input-group-text\" for=\"inputGroupSelect01\"><i class=\"fa-solid fa-user-doctor\"></i></label>";
        $html .= "                   <select class=\"form-select form-select-sm \" id=\"inputGroupSelect01\" style=\"max-width:300px\" aria-label=\".form-select-sm example\">";
        $html .= "                       <option selected>Szűrés Orvosra</option>";
        $html .= "                       <option value=\"3\">Orvos</option>";
        $html .= "                   </select>";
        $html .= "               </div>";
        $html .= "           </div>";
        $html .= "        </div>";
        $html .= "    </div>";
        $html .= "</div>";

        $html .= "<table class=\"table table-hover\" style=\"white-space: nowrap;\">";
        $html .= "    <thead>";
        $html .= "        <tr>";
        $html .= "        <th scope=\"col\">#</th>";
        $html .= "        <th scope=\"col\">Teljesnév</th>";
        $html .= "        <th scope=\"col\">Cég</th>";
        $html .= "        <th scope=\"col\">Vizsgálat</th>";
        $html .= "        <th scope=\"col\">Orvos</th>";
        $html .= "        <th scope=\"col\">Asszisztens</th>";
        $html .= "        <th scope=\"col\">Érkeztetve</th>";
        $html .= "        <th scope=\"col\">Behívás várakozási idő</th>";
        $html .= "        <th scope=\"col\">Behívás ideje</th>";
        $html .= "        <th scope=\"col\">Ellátási idő</th>";
        $html .= "        <th scope=\"col\">Ellátás befejezve</th>";
        $html .= "        <th scope=\"col\"><i class=\"fa-solid fa-gear\"></i></th>";
        $html .= "        </tr>";
        $html .= "    </thead>";
        $html .= "    <tbody class=\"table-group-divider\">";

        $q=sql_query("SELECT v.*,fogl.nev,c.megnev AS cegnev,sz.megnev AS szurestipusnev,o.nev AS orvosnev,u.nev AS asszisztensnev FROM varoterem v 
                      LEFT JOIN foglalasok fogl ON fogl.id=v.fid
                      LEFT JOIN cegek c ON c.id=fogl.cegid
                      LEFT JOIN szurestipusok sz ON sz.id=v.szurestipusid
                      LEFT JOIN orvosok o ON o.id=v.orvosid
                      LEFT JOIN users u ON u.id=v.behivta
                      WHERE v.vizsgalat_befejezve LIKE \"%".$_SESSION["setday"]."%\" AND v.statusz = \"vizsgalat_kesz\" ORDER BY v.vizsgalat_befejezve DESC");

        while($r=sql_fetch_array($q)){
            $varakozasi_ido = number_format(ceil(((strtotime($r["behivas_ideje"])-strtotime($r["erkeztetve"]))/60)));
            $vizsgalat_ido = number_format(ceil(((strtotime($r["vizsgalat_befejezve"])-strtotime($r["behivas_ideje"]))/60)));
            $html .= "        <tr>";
            $html .= "            <th scope=\"row\">{$numb}</th>";
            $html .= "            <td>{$r["nev"]}</td>";
            $html .= "            <td>{$r["cegnev"]}</td>";
            $html .= "            <td>{$r["szurestipusnev"]}</td>";
            $html .= "            <td>{$r["orvosnev"]}</td>";
            $html .= "            <td>{$r["asszisztensnev"]}</td>";
            $html .= "            <td>{$r["erkeztetve"]}</td>";
            $html .= "            <td>{$varakozasi_ido} perc</td>";
            $html .= "            <td>{$r["behivas_ideje"]}</td>";
            $html .= "            <td>{$vizsgalat_ido} perc</td>";
            $html .= "            <td>{$r["vizsgalat_befejezve"]}</td>";
            $html .= "            <td><i class=\"fa-sharp fa-solid fa-pen-to-square\"></i>&nbsp;<i class=\"fa-solid fa-trash\"></i></td>";
            $html .= "        </tr>";
            $numb++;
        }
        
        $html .= "    </tbody>";
        $html .= "</table>";

        return $html;
    }

    public function waitlistTable__demo()
    {
        $html = $header = $columns = $content = "";
        $tipusok = array();
        $relevant_exam_types = json_decode($_SESSION["adminuser"]["relevant_exam_types"]);
        if (!empty($relevant_exam_types)) {
            $q = sql_query("SELECT id,megnev,facode FROM szurestipusok WHERE id IN(" . implode(",", $relevant_exam_types) . ")");
        } else {
            return $html;
        }

        while ($result = sql_fetch_array($q)) $tipusok[] = $result;

        //Itt kéne beaktíválnom a sorokat
        foreach ($tipusok as $tipus) {
            $content .= "<div class=\"row\">";
            $content .= "    <div class=\"col py-2 px-2 mt-1 fw-bold fs-6\" style=\"background-color:#474747;color:white;max-width:250px\">" . (!empty($tipus["facode"]) ? $tipus["facode"] . "&nbsp;&nbsp;" : "") . "{$tipus["megnev"]}</div>";
            $content .= "    <div class=\"col w-auto\" id=\"type-column-{$tipus["id"]}\">";
            $content .=         $this->showWaitingPplv2($tipus["id"]);
            $content .= "    </div>";
            $content .= "</div>";
        }

        $html .= "<div class=\"container\">";
        $html .= $content;
        $html .= "</div>";

        return $html;
    }

    public function generate_ugyfelszam($setday, $taj){
        $ugyfelszam = "001";
        $extraZero = "";
        $last = sql_fetch_array(sql_query("SELECT * FROM varoterem WHERE erkeztetve LIKE \"{$setday}%\" ORDER BY ugyfelszam DESC LIMIT 1"));
        //Ha még nem volt ma ügyfélszám generálva
        if(empty($last["ugyfelszam"])){
            return $ugyfelszam;
        }else{
            //Ha már volt valamire a páciens behívva és egy új vizsgálatra hívják be akkor kapja meg a régi számát
            if($exists=sql_fetch_array(sql_query("SELECT * FROM varoterem WHERE erkeztetve LIKE \"{$setday}%\" AND taj LIKE \"%".$taj."%\""))){
                $ugyfelszam = $exists["ugyfelszam"];
                return $ugyfelszam;
            }else{
                //Ha az első vizsgálata és nem az első már
                $ugyfelszam = (intval($last["ugyfelszam"])+1);
                if(strlen(strval($ugyfelszam))==1){
                    $extraZero="00";
                }
                if(strlen(strval($ugyfelszam))==2){
                    $extraZero="0";
                }
                $ugyfelszam = $extraZero.$ugyfelszam;
                return $ugyfelszam;
            }
        }
        return $ugyfelszam;
    }

    public function doc_choose_button($data){

        $stringDay = $_SESSION["setday"];
        $numericDay = date("w", strtotime($_SESSION["setday"]));
        $helyszinid = $_SESSION["helyszin"];
        $html = "";
        $spanCSS = "
        border: 0px solid #888;
        padding: 8px 10px;
        background-color: #0a0;
        font-size: 14px;
        color: #fff;
        -moz-border-radius: 5px;
        border-radius: 5px;
        transition: all .1s linear;";

        $q = sql_query("SELECT o.id,o.nev,beo.binterval,o.colorcode FROM orvosok o
                            LEFT JOIN orvos_beosztas_new beo ON beo.orvosid=o.id
                            WHERE beo.helyszinid={$helyszinid} AND (beo.nap={$numericDay} OR beo.beonap = '{$stringDay}') AND tipusok LIKE '%|{$data["szurestipusid"]}|%' AND beo.aktiv=1
                            GROUP BY o.id");

        $html .= "   <div class=\"dropup\" style=\"display:inline\">";
        $html .= "       <span  class=\"dropdown-toggle\" style=\"{$spanCSS}\" href=\"#\" role=\"button\" data-bs-toggle=\"dropdown\" aria-expanded=\"false\" style=\"background-color:#0a0\">Érkeztetés</span>";
        $html .= "       <ul class=\"dropdown-menu\">";
        while($r=sql_fetch_array($q)){
            if(!empty($r["colorcode"])){
                $colorindicator="<i style=\"color:{$r["colorcode"]}\" class=\"fa-solid fa-circle\"></i>&nbsp;";
            }else{
                $colorindicator = "<i class=\"fa-solid fa-circle\"></i>&nbsp;"; 
            }

            $html .= "         <li><a class=\"dropdown-item\" onClick='addToWaitList({$data["id"]},{$r["id"]});return false' href=\"#\">{$colorindicator}{$r["nev"]}</a></li>";
        }
        $html .= "             <li><a class=\"dropdown-item\" onClick='addToWaitList({$data["id"]},0);return false' href=\"#\"><i class=\"fa-solid fa-user-doctor\"></i>&nbsp;Bármelyik</a></li>";
        
        //$html .= "           <li><a class=\"dropdown-item\" href=\"#\"><i class=\"fa-sharp fa-solid fa-pen-to-square\"></i>&nbsp;Adatok szerkesztése</a></li>";
        //$html .= "           <li><a class=\"dropdown-item\" href=\"#\"><i class=\"fa-solid fa-rotate-left\"></i>&nbsp;Vissza a váróterembe</a></li>";
        $html .= "       </ul>";
        $html .= "   </div>";
        return $html;
    }

    public function busy_doctor_indicator($tipus)
    {
        $html = $visitString = "";
        $spanTemplate = "style=\"margin-top: 9px;margin-right:5px;text-align:center;vertical-align:middle;background:#colorcode#;color:#fff;padding:5px;border-radius:5px;cursor:pointer;\"";
        $stringDay = $_SESSION["setday"];
        $numericDay = date("w", strtotime($_SESSION["setday"]));
        $helyszinid = $_SESSION["helyszin"];
        $colorcodes = array("#0a0", "#ffd700", "#ff4040");
        $docCapacity = $docUsage =  0;


        $q = sql_query("SELECT o.id,o.nev,beo.binterval,o.colorcode FROM orvosok o
                            LEFT JOIN orvos_beosztas_new beo ON beo.orvosid=o.id
                            WHERE beo.helyszinid={$helyszinid} AND (beo.nap={$numericDay} OR beo.beonap = '{$stringDay}') AND tipusok LIKE '%|{$tipus["id"]}|%' AND beo.aktiv=1
                            GROUP BY o.id");

        while ($orvos = sql_fetch_array($q)) {
            $docCapacity++;
            $visitString = "";
            $qVisit = sql_query("SELECT v.*,fogl.nev,fogl.pass FROM varoterem v
                            LEFT JOIN foglalasok fogl ON fogl.id=v.fid
                            WHERE v.orvosid=? AND v.statusz='vizsgalaton' AND fogl.datum LIKE \"%" . $stringDay . "%\" ", array($orvos["id"]));



            if ($duringvisit = sql_fetch_array($qVisit)) {
                $visitString = "";
                $mins = round((strtotime("NOW") - strtotime($duringvisit["behivas_ideje"])) / 60);
                $name = explode(" ", $duringvisit["nev"]);
                if ($mins <= $orvos["binterval"]) $color = $colorcodes[0];
                if ($mins > $orvos["binterval"] && $mins <= ($orvos["binterval"] + 25)) $color = $colorcodes[1];
                if ($mins > ($orvos["binterval"] + 25)) $color = $colorcodes[2];

                $finishExamination = "onClick=\"finishExamination({$duringvisit["id"]});return false;\"";
                $bookingEditor = "onClick=\"showIdopontEditor('booking','{$duringvisit["pass"]}',{$duringvisit["fid"]});return false;\"";
                $returnToWaitingRoom = "onClick=\"returnToWaitingRoom({$duringvisit["id"]});return false;\"";

                $spanCSS = str_replace("#colorcode#", $color, $spanTemplate);

                //$visitString = "&nbsp;<span {$spanCSS}><i class=\"fa-solid fa-spinner fa-spin-pulse\"></i>&nbsp;{$name[0]}-{$duringvisit["ugyfelszam"]} ({$mins}p.)</span>";

                $span = "style=\"margin-top:9px;margin-right:5px;text-align:center;vertical-align:middle;background:{$color};color:#fff;padding:5px;border-radius:5px;cursor:pointer;\"";
                $visitString .= "   <div class=\"dropdown\" style=\"display:inline\">";
                $visitString .= "       <span title=\"{$duringvisit["nev"]}\" {$span} class=\"dropdown-toggle\" href=\"#\" role=\"button\" data-bs-toggle=\"dropdown\" aria-expanded=\"false\">";
                $visitString .= "           <i class=\"fa-solid fa-spinner fa-spin-pulse\"></i>&nbsp;{$name[0]}-{$duringvisit["ugyfelszam"]} ({$mins}p.)";
                $visitString .= "       </span>";
                $visitString .= "       <ul class=\"dropdown-menu\">";
                $visitString .= "           <li><a class=\"dropdown-item\" {$finishExamination} href=\"#\"><i class=\"fa-solid fa-square-check\"></i>&nbsp;Vizsgálat befejezve</a></li>";
                $visitString .= "           <li><hr class=\"dropdown-divider\"></li>";
                $visitString .= "           <li><a class=\"dropdown-item\" {$bookingEditor} href=\"#\"><i class=\"fa-sharp fa-solid fa-pen-to-square\"></i>&nbsp;Adatok szerkesztése</a></li>";
                $visitString .= "           <li><a class=\"dropdown-item\" {$returnToWaitingRoom} href=\"#\"><i class=\"fa-solid fa-rotate-left\"></i>&nbsp;Vissza a váróterembe</a></li>";
                $visitString .= "       </ul>";
                $visitString .= "   </div>";

                $docUsage++;
            }

            $orvosnev = $orvos["nev"];
            $orvos["nev"] = str_replace("Menedzser", "M.", $orvos["nev"]);
            if (strlen($orvos["nev"]) > 20) {
                $orvos["nev"] = substr($orvos["nev"], 0, 20) . '...';
            }
            if(!empty($orvos["colorcode"])){
                $colorindicator="<i style=\"color:{$orvos["colorcode"]}\" class=\"fa-solid fa-circle\"></i>&nbsp;";
            }else{
                $colorindicator = ""; 
            }
            $html .= "<li  style=\"padding:5px 0px\">{$colorindicator}<span title=\"{$orvosnev}\">{$orvos["nev"]}</span>{$visitString}</li>";
        }

        return array("html" => $html, "status" => "({$docUsage}/{$docCapacity})");
    }

    public function waitlistTable()
    {
        $html = $content = "";

        $tipusok = array();
        $linkStyle = "style=\"color:white;text-decoration:none\"";
        $relevant_exam_types = json_decode($_SESSION["adminuser"]["relevant_exam_types"]);
        if (!empty($relevant_exam_types)) {
            $q = sql_query("SELECT id,megnev,facode FROM szurestipusok WHERE id IN(" . implode(",", $relevant_exam_types) . ")");
        } else {
            return $html;
        }

        while ($result = sql_fetch_array($q)) $tipusok[] = $result;

        //Itt kéne beaktíválnom a sorokat
        foreach ($tipusok as $tipus) {

            if(isset($_SESSION["wl-objects"]["dropdown-doctor-card"][$tipus["id"]])){
                $show = "";
            }else{
                $show = "collapse";
            }

            $doctorIndicator = $this->busy_doctor_indicator($tipus);
            $content .= "<div class=\"row\">";
            $content .= "    <div class=\"col py-2 px-2 mt-1 fw-bold fs-6\" style=\"background-color:#474747;color:white;max-width:350px\">";
            $content .= "       <a data-bs-toggle=\"collapse\" class=\"doctor-card\" data-object-type=\"dropdown-doctor-card\" data-menu-id=\"{$tipus["id"]}\" {$linkStyle} href=\"#collapseTipus{$tipus["id"]}\">" . (!empty($tipus["facode"]) ? $tipus["facode"] . "&nbsp;&nbsp;" : "") . "{$tipus["megnev"]} {$doctorIndicator["status"]}</a>";
            $content .= "       <div class=\"{$show}\" id=\"collapseTipus{$tipus["id"]}\">";
            $content .= "           <div>";
            $content .= "               <ul style=\"font-size:0.76rem!important\">";
            $content .=                   $doctorIndicator["html"];
            $content .= "               </ul>";
            $content .= "           </div>";
            $content .= "       </div>";
            $content .= "    </div>";
            $content .= "    <div class=\"col w-auto\" id=\"type-column-{$tipus["id"]}\">";
            $content .=         $this->showWaitingPplv2($tipus["id"]);
            $content .= "    </div>";
            $content .= "</div>";
        }

        $html .= "<div class=\"container\">";
        $html .= $content;
        $html .= "</div>";

        return $html;
    }

    public function showWaitingPplv2__old($tipus)
    {
        $html = $content = "";
        $ul = "style=\"list-style: none;padding: 0;margin: 0;display: flex;flex-direction: column;flex-wrap: wrap;float:left;height:28px\"";
        $colorcodes = array("#0a0", "#ffd700", "#ff4040");

        $q = sql_query("SELECT v.*,fogl.nev FROM varoterem v
                        LEFT JOIN foglalasok fogl ON fogl.id=v.fid
                        WHERE v.szurestipusid={$tipus} AND fogl.datum LIKE \"%" . $_SESSION["setday"] . "%\" AND v.statusz=\"varakozik\" ");


        while ($r = sql_fetch_array($q)) {
            $mins = round((strtotime("NOW") - strtotime($r["erkeztetve"])) / 60);
            $name = explode(" ", $r["nev"]);
            if ($mins <= 10) $color = $colorcodes[0];
            if ($mins > 10 && $mins <= 25) $color = $colorcodes[1];
            if ($mins > 25) $color = $colorcodes[2];
            $li = "style=\"margin-top:9px;margin-right:5px;text-align:center;vertical-align:middle;background:{$color};color:#fff;padding:5px;border-radius:5px;cursor:pointer;\"";
            $content .= "<li title=\"{$r["nev"]}\"{$li}>{$name[0]}-001 ({$mins}p.)</li>";
        }

        $html .= "<ul {$ul}>";
        $html .=    $content;
        $html .= "</ul>";

        //$html.= "        </div>";
        return $html;
    }

    public function showWaitingPplv2($tipus)
    {
        $html = $content = "";
        $ul = "style=\"list-style: none;padding: 0;margin: 0;display: flex;flex-direction: column;flex-wrap: wrap;float:left;height:28px\"";
        $colorcodes = array("#0a0", "#ffd700", "#ff4040");

        $q = sql_query("SELECT v.*,fogl.nev,fogl.pass,o.colorcode FROM varoterem v
                        LEFT JOIN foglalasok fogl ON fogl.id=v.fid
                        LEFT JOIN orvosok o ON o.id=v.orvos_pref
                        WHERE v.szurestipusid={$tipus} AND fogl.datum LIKE \"%" . $_SESSION["setday"] . "%\" AND v.statusz=\"varakozik\" ");


        while ($r = sql_fetch_array($q)) {
            $mins = round((strtotime("NOW") - strtotime($r["erkeztetve"])) / 60);
            $name = explode(" ", $r["nev"]);
            $bookingEditor = "onClick=\"showIdopontEditor('booking','{$r["pass"]}',{$r["fid"]});return false;\"";
            $removeFromList = "onClick=\"removeFromWaitList({$r["id"]})\"";
            $callInToVisit = "onClick=\"callInToVisit({$r["id"]})\"";
            if ($mins <= 10) $color = $colorcodes[0];
            if ($mins > 10 && $mins <= 25) $color = $colorcodes[1];
            if ($mins > 25) $color = $colorcodes[2];

            //Gombok mutatása/elrejtése újratöltés esetén
            if(isset($_SESSION["wl-objects"]["waiting-button"][$r["id"]])){
                $show = $_SESSION["wl-objects"]["waiting-button"][$r["id"]];
            }else{
                $show = "";
            }

            //Ha üres a colorcode
            if(empty($r["colorcode"])){
                $r["colorcode"] = "#474747";
            }

            $li = "style=\"margin-top:9px;margin-right:5px;text-align:center;vertical-align:middle;background-color:{$r["colorcode"]};color:#fff;padding:5px;border-radius:5px;cursor:pointer;\"";
            $content .= "<li title=\"{$r["nev"]}\"{$li}>";
            $content .= "   <div class=\"dropdown waiting-costumer\" data-object-type=\"waiting-button\" data-menu-id=\"{$r["id"]}\">";
            $content .= "       <span class=\"dropdown-toggle {$show}\" href=\"#\" role=\"button\" data-bs-toggle=\"dropdown\" aria-expanded=\"false\">";
            $content .= "           <i class=\"fa-solid fa-circle\" style=\"color:{$color}\"></i>&nbsp;{$name[0]}-{$r["ugyfelszam"]} ({$mins}p.)";
            $content .= "       </span>";
            $content .= "       <ul class=\"dropdown-menu {$show}\">";
            $content .= "           <li><a class=\"dropdown-item\" href=\"#\" {$callInToVisit}><i class=\"fa-solid fa-square-check\"></i>&nbsp;Behívás</a></li>";
            $content .= "           <li><hr class=\"dropdown-divider\"></li>";
            $content .= "           <li><a class=\"dropdown-item\" href=\"#\" {$bookingEditor} ><i class=\"fa-sharp fa-solid fa-pen-to-square\"></i>&nbsp;Adatok szerkesztése</a></li>";
            $content .= "           <li><a class=\"dropdown-item\" href=\"#\" {$removeFromList} ><i class=\"fa-solid fa-trash\"></i>&nbsp;Törlés</a></li>";
            $content .= "       </ul>";
            $content .= "   </div>";
            $content .= "</li>";
        }

        $html .= "<ul {$ul}>";
        $html .=    $content;
        $html .= "</ul>";

        //$html.= "        </div>";
        return $html;
    }

    public function old__waitlistTable()
    {

        $html = $header = $columns = $content =  "";
        $tipusok = array();
        $relevant_exam_types = json_decode($_SESSION["adminuser"]["relevant_exam_types"]);
        if (!empty($relevant_exam_types)) {
            $q = sql_query("SELECT id,megnev FROM szurestipusok WHERE id IN(" . implode(",", $relevant_exam_types) . ")");
        } else {
            return $html;
        }

        while ($result = sql_fetch_array($q)) $tipusok[] = $result;

        foreach ($tipusok as $tipus) {
            $content .= "<div class=\"row\">";
            $content .= "    <div class=\"col\">{$tipus["megnev"]}</div>";
            $content .= "    <div class=\"col py-3 px-2\" style=\"background-color:#FF10F0;color:white;font-size:16px\" id=\"type-column-{$tipus["id"]}\"></div>";
            $content .= "</div>";
        }

        $html .= "<div class=\"container\">";
        $html .= $content;
        $html .= "</div>";

        /*foreach($tipusok as $tipus){
            $header.= "<th scope=\"col\">{$tipus["megnev"]}</th>";
            $columns.= "<td><div class=\"text-center\" id=\"type-column-{$tipus["id"]}\">{$this->showWaitingPpl()}</div></td>";
        }

        $html .= "<table class=\"table text-center w-auto\">";
        $html .= "    <thead>";
        $html .= "        <tr>";
        $html .= $header;
        $html .= "        </tr>";
        $html .= "    </thead>";
        $html .= "    <tbody class=\"table-group-divider\">";
        $html .= "        <tr>";
        $html .= $columns;
        $html .= "        </tr>";
        $html .= "    </tbody>";
        $html .= "</table>";*/

        /*$html .= "<div class=\"container mx-0\">";
        $html .= "    <div class=\"row\">";
        $html .= "        <div class=\"col\">";
        $html .= "        Column";
        $html .= "        </div>";
        $html .= "    </div>";
        $html .= "</div>";*/

        return $html;
    }

    public function showWaitingPpl()
    {
        $html = "";

        $ul = "style=\"list-style: none;padding: 0;margin: 0;display: flex;flex-direction: column;flex-wrap: wrap;float:left\"";
        $li = "style=\"margin:5px;text-align:center;vertical-align:middle;background:#0a0;color:#fff;padding:5px;border-radius:5px;cursor:pointer\"";

        $html .= "   <div>";
        $html .= "       <ul class=\"\" {$ul}>";
        $html .= "          <li {$li} class=\"\">Kis Béla</li>";
        $html .= "          <li {$li} class=\"\">Nagy Tamás Béla Benekdek</li>";
        $html .= "          <li {$li} class=\"\">Kis Béla</li>";
        $html .= "          <li {$li} class=\"\">Nagy Tamás Béla Benekdek</li>";
        $html .= "          <li {$li} class=\"\">Nagy Tamás Béla Benekdek</li>";
        $html .= "          <li {$li} class=\"\">Kis Béla</li>";
        $html .= "          <li {$li} class=\"\">7</li>";
        $html .= "          <li {$li} class=\"\">8</li>";
        $html .= "          <li {$li} class=\"\">9</li>";
        $html .= "       </ul>";
        $html .= "   </div>";
        return $html;
    }

    public function setupLathatoVizsgalatok()
    {
        $html = "";

        $stringDay = $_SESSION["setday"];
        $numericDay = date("w", strtotime($_SESSION["setday"]));
        $helyszinid = $_SESSION["helyszin"];
        $tipusString = "";
        $tipusok = $tipusDictionary = $orvosok = array();
        $relevant_exam_types = json_decode($_SESSION["adminuser"]["relevant_exam_types"]);

        $q = sql_query("SELECT * FROM orvos_beosztas_new WHERE helyszinid=? AND aktiv=1 AND (nap = ? OR beonap LIKE \"{$stringDay}\") ", array($helyszinid, $numericDay));

        while ($result = sql_fetch_array($q)) {
            $tipusString .= $result["tipusok"];
        }

        $tipusString = substr($tipusString, 1, -1);
        $tipusok = explode("||", $tipusString);

        $tipusok = array_values(array_unique($tipusok));

        if(!empty($relevant_exam_types)){
            foreach ($relevant_exam_types as $tipus) {
                if (!in_array($tipus, $tipusok)) {
                    $tipusok[] = $tipus;
                }
            }
        }
        

        foreach ($tipusok as $tipus) {
            $result = sql_fetch_array(sql_query("SELECT * FROM szurestipusok WHERE id=?", array($tipus)));
            if (!empty($result)) {
                $tipusDictionary[] = array("id" => $tipus, "name" => $result["megnev"]);
                if(!empty($relevant_exam_types)){
                if (in_array($tipus, $relevant_exam_types)) {
                        $qOrvos = sql_query("SELECT o.id,o.nev,\"{$result["megnev"]}\" as szurestipusnev FROM orvosok o
                                            LEFT JOIN orvos_beosztas_new beo ON beo.orvosid=o.id
                                            WHERE beo.helyszinid={$helyszinid} AND (beo.nap={$numericDay} OR beo.beonap = '{$stringDay}') AND tipusok LIKE '%|{$tipus}|%' AND beo.aktiv=1
                                            GROUP BY o.id");
                        while ($rOrvos = sql_fetch_array($qOrvos)) $orvosok[] = $rOrvos;
                    }
                }
            }
        }

        $keys = array_column($tipusDictionary, "name");
        array_multisort($keys, SORT_ASC, $tipusDictionary);

        $keys = array_column($orvosok, "nev");
        array_multisort($keys, SORT_ASC, $orvosok);

        $html .= "  <div class=\"card card-body\">";
        $html .= "      <div class=\"row\">";
        $html .= "          <div class=\"col\">";
        $html .= "              <ul class=\"list-group\">";
        foreach ($tipusDictionary as $tipus) {
            if (!empty($relevant_exam_types) && in_array($tipus["id"], $relevant_exam_types)) {
                $checked = "checked=\"true\"";
            } else {
                $checked = "";
            }
            $html .= "              <li class=\"list-group-item form-check form-switch\">";
            $html .= "                  <input class=\"form-check-input waitlist-relevant-types\" type=\"checkbox\" {$checked} role=\"switch\" style=\"margin-left:0.5rem\" value=\"{$tipus["id"]}\" id=\"tipus{$tipus["id"]}\">";
            $html .= "                  <label class=\"form-check-label stretched-link\" style=\"margin-left: 0.5rem;\" for=\"tipus{$tipus["id"]}\">{$tipus["name"]}</label>";
            $html .= "              </li>";
        }
        $html .= "              </ul>";
        $html .= "          </div>";
        $html .= "          <div class=\"col\">";
        $html .= "              <ul class=\"list-group\">";
        $html .= "                  <select class=\"form-select form-select-lg mb-3 waitlist-bound-to-doctor-list\" aria-label=\".form-select-lg example\">";
        if(empty($_SESSION["adminuser"]["bound_to_doctor"]) || !in_array($_SESSION["adminuser"]["bound_to_doctor"],array_column($orvosok,"id"))){
            $html .= "<option selected=\"true\" value=\"0\">Válassz orvost!</option>";
        }
        foreach ($orvosok as $orvos) {
            if ($_SESSION["adminuser"]["bound_to_doctor"] == $orvos["id"]) {
                $selected = "selected=\"true\"";
            } else {
                $selected = "";
            }
            $html .= "<option {$selected} value=\"{$orvos["id"]}\">{$orvos["nev"]} ({$orvos["szurestipusnev"]})</option>";
        }
        $html .= "                  </select>";;
        $html .= "              </ul>";
        $html .= "          </div>";
        $html .= "      </div>";
        $html .= "  </div>";
        return $html;
    }
}
