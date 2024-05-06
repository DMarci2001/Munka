<?php
class PsychosocialFormPage extends CorePage {

    private $psyhosocData;
    private $foglalasData;
    public $showLangMenu;
    private $notificationService;
    public $lang;

    public function __construct()
    {
        parent::__construct();

        $this->showMainMenu = false;
        $this->showLangMenu = true;
        $this->lockInPage   = true;
        $this->lang = new Lang();
        $this->notificationService = new NotificationService();

        if(isset($_GET["pass"]) && $_GET["fid"]){
            if($existspsyhosoc=sql_fetch_array(sql_query("SELECT * FROM psychosoc_eredmenyek WHERE pass=? AND foglid=?",array($_GET["pass"],$_GET["fid"])))){
                $this->psyhosocData = $existspsyhosoc;
            }
            $this->foglalasData=sql_fetch_array(sql_query("SELECT fogl.*,fogl.id as foglid,h.cim,sz.*,sz.id as szurestipusid FROM foglalasok fogl
                                                           LEFT JOIN helyszinek h ON h.id=fogl.helyszinid
                                                           LEFT JOIN szurestipusok sz ON sz.id=fogl.szurestipusid
                                                           WHERE fogl.pass=? AND fogl.id=?",array($_GET["pass"],$_GET["fid"])));

            if ($_COOKIE["lang"] != "hu" && trim($this->foglalasData["megnev_{$_COOKIE["lang"]}"]) != "") {
                $this->foglalasData["megnev"] = $this->foglalasData["megnev_{$_COOKIE["lang"]}"];
            }
        }

        if(isset($_POST["psychosocsubmitbutton"]) && $_POST["psychosocsubmitbutton"]==1){

            $data = array(
                "id" => $this->psyhosocData["id"],
                "bad_sleeping" => (isset($_POST["bad_sleeping"]))?$_POST["bad_sleeping"]:null,
                "exhaustion" => (isset($_POST["exhaustion"]))?$_POST["exhaustion"]:null,
                "difficulty_in_sleeping" => (isset($_POST["difficulty_in_sleeping"]))?$_POST["difficulty_in_sleeping"]:null,
                "physical_exhaustion" => (isset($_POST["physical_exhaustion"]))?$_POST["physical_exhaustion"]:null,
                "emotional_exhaustion" => (isset($_POST["emotional_exhaustion"]))?$_POST["emotional_exhaustion"]:null,
                "bad_early_waking" => (isset($_POST["bad_early_waking"]))?$_POST["bad_early_waking"]:null,
                "tired" => (isset($_POST["tired"]))?$_POST["tired"]:null,
                "difficult_to_fall_back_asleep" => (isset($_POST["difficult_to_fall_back_asleep"]))?$_POST["difficult_to_fall_back_asleep"]:null,
                "cannot_relax" => (isset($_POST["cannot_relax"]))?$_POST["cannot_relax"]:null,
                "irritability" => (isset($_POST["irritability"]))?$_POST["irritability"]:null,
                "tension" => (isset($_POST["tension"]))?$_POST["tension"]:null,
                "stress" => (isset($_POST["stress"]))?$_POST["stress"]:null,
                "digestive_problems" => (isset($_POST["digestive_problems"]))?$_POST["digestive_problems"]:null,
                "allergic" => (isset($_POST["allergic"]))?$_POST["allergic"]:null,
                "difficulty_concentrating" => (isset($_POST["difficulty_concentrating"]))?$_POST["difficulty_concentrating"]:null,
                "difficulty_remembering" => (isset($_POST["difficulty_remembering"]))?$_POST["difficulty_remembering"]:null,
                "relax_after_work" => (isset($_POST["relax_after_work"]))?$_POST["relax_after_work"]:null,
                "withdrawn_personality" => (isset($_POST["withdrawn_personality"]))?$_POST["withdrawn_personality"]:null,
                "describe_your_health" => (isset($_POST["describe_your_health"]))?$_POST["describe_your_health"]:null,

                "headache" => (isset($_POST["headache"]))?$_POST["headache"]:null,
                "dizziness" => (isset($_POST["dizziness"]))?$_POST["dizziness"]:null,
                "muscle_tension" => (isset($_POST["muscle_tension"]))?$_POST["muscle_tension"]:null,
                "vomiting" => (isset($_POST["vomiting"]))?$_POST["vomiting"]:null,
                "heartbeat" => (isset($_POST["heartbeat"]))?$_POST["heartbeat"]:null,
                "vision_problems" => (isset($_POST["vision_problems"]))?$_POST["vision_problems"]:null,
                "fatigue" => (isset($_POST["fatigue"]))?$_POST["fatigue"]:null,
                "sweating" => (isset($_POST["sweating"]))?$_POST["sweating"]:null,
                "susceptibility_to_infection" => (isset($_POST["susceptibility_to_infection"]))?$_POST["susceptibility_to_infection"]:null,
                "burning_sensation_in_the_chest" => (isset($_POST["burning_sensation_in_the_chest"]))?$_POST["burning_sensation_in_the_chest"]:null,
                "constipation" => (isset($_POST["constipation"]))?$_POST["constipation"]:null,
                "itch" => (isset($_POST["itch"]))?$_POST["itch"]:null,
                "inner_pain" => (isset($_POST["inner_pain"]))?$_POST["inner_pain"]:null,
                "internal_tremor" => (isset($_POST["internal_tremor"]))?$_POST["internal_tremor"]:null,
            );

            if(empty($this->psyhosocData["datum"])){
                $this->psyhosocData["datum"] = date("Y-m-d H:i:s");
            }

            sql_query("UPDATE psychosoc_eredmenyek 
                       SET bad_sleeping=?, exhaustion=?, difficulty_in_sleeping=?, physical_exhaustion=?, emotional_exhaustion=?, bad_early_waking=?,
                       tired=?,difficult_to_fall_back_asleep=?,cannot_relax=?,irritability=?,tension=?,stress=?,digestive_problems=?,allergic=?,
                       difficulty_concentrating=?,difficulty_remembering=?,relax_after_work=?,withdrawn_personality=?,describe_your_health=?,
                       headache=?,dizziness=?,muscle_tension=?,vomiting=?,heartbeat=?,vision_problems=?,fatigue=?,sweating=?,susceptibility_to_infection=?,
                       burning_sensation_in_the_chest=?,constipation=?,itch=?,inner_pain=?,internal_tremor=?,datum=? WHERE id=?",array(
                        $data["bad_sleeping"],$data["exhaustion"],$data["difficulty_in_sleeping"],$data["physical_exhaustion"],$data["emotional_exhaustion"],
                        $data["bad_early_waking"],$data["tired"],$data["difficult_to_fall_back_asleep"],$data["cannot_relax"],$data["irritability"],
                        $data["tension"],$data["stress"],$data["digestive_problems"],$data["allergic"],$data["difficulty_concentrating"],$data["difficulty_remembering"],
                        $data["relax_after_work"],$data["withdrawn_personality"],$data["describe_your_health"],$data["headache"],$data["dizziness"],
                        $data["muscle_tension"],$data["vomiting"],$data["heartbeat"],$data["vision_problems"],$data["fatigue"],$data["sweating"],
                        $data["susceptibility_to_infection"],$data["burning_sensation_in_the_chest"],$data["constipation"],$data["itch"],
                        $data["inner_pain"],$data["internal_tremor"],$this->psyhosocData["datum"],$data["id"]
                       ));

            sql_query("UPDATE foglalasok SET aktiv=1 WHERE id=?",array($this->foglalasData["foglid"]));

            header("location:index.php?page=psychosocialform&fid={$this->foglalasData["foglid"]}&pass={$this->foglalasData["pass"]}&status=success");
            die();
        }


    }

    public function showPage() {
        //Ha nincsen bejegyezve a pass érték által egyetlen foglalás se dobjon vissza a start oldalra
        /*if( empty($this->psyhosocData)){
            header("location:index.php");
        }*/

        //Hogyha sikeres volt a mentés, akkor irányítson át a success oldalra
        if(isset($_GET["status"])&& $_GET["status"]=="success"){
            echo $this->successPsyhosocNotification();
        }

        //Hogyha még nincsen kitöltve vagy a modify szerepel mint status akkor tölte ezt a verziót be
        if(!isset($_GET["status"]) || (isset($_GET["status"]) && $_GET["status"]=="modify")){
            echo $this->psyhosocForm();
        }
        
        
    }

    private function successPsyhosocNotification(){
        $webText = $this->lang->webText;
        $html = "";

        //Értesítések kiküldése:
        $this->notificationService->sendToCegAndOrvos($this->foglalasData["foglid"]);
        $this->notificationService->sendUserReservationNotification($this->foglalasData["foglid"]);

        $replaceable = array("#idopont#","#helyszin#","#szurestipus#","#link#");
        $newText = array(
                    date("Y.m.d H:i",
                    strtotime($this->foglalasData["datum"])),$this->foglalasData["cim"],$this->foglalasData["megnev"],
                    "index.php?page=psychosocialform&pass={$this->psyhosocData["pass"]}&status=modify"
                );

        $html.= $webText["pszihosoc_success"];
        /*$html.= "<h2>Köszönjük hogy kitöltötte a Pszichoszociális kérdőívet!</h2>";
        $html.= "<p>Foglalása mentésre került, időpontja: </p>";
        $html.= "<span><strong> - Időpont: </strong>#idopont#,<br>";
        $html.= "<strong> - Helyszín: </strong>#helyszin#,<br>";
        $html.= "<strong> - Vizsgálat típusa: </strong>#szurestipus#</span><br><br>";
        $html.= "<p>Megtudja tekinteni és módosítani is tudja a kérdőívét, ha a \"módosítás gombra kattint.\"</p>";
        $html.= "<a class=\"newbutton\" href=\"#link#\">Kérdőív megtekintése és módosítása</a>";*/

        $html = str_replace($replaceable,$newText,$html);

        echo $html;
    }

    private function psyhosocForm(){

        $webText = $this->lang->webText;

        $submitButton = "<div style='margin-top:30px;text-align: center;'><button id='psychosocsubmitbutton' type=\"button\" name=\"psychosocsubmitbutton\" value=\"0\" class='newbutton' style='opacity: .3;border:none'>{$webText["adatokelkuldese"]}</button></div>";

        if(!empty($this->psyhosocData["datum"])){
            $_POST = $this->psyhosocData;
            $submitButton = "<div style='margin-top:30px;text-align: center;'><button id='psychosocsubmitbutton' type=\"button\" name=\"psychosocsubmitbutton\" value=\"0\" class='newbutton' style='opacity: .3;border:none'>{$webText["modositas"]}</button></div>";
        }

        echo $webText["psyhosoccimsor"];
        //echo "<h1 style='text-align: center;'>Pszichoszociális Kérdőív</h1>";
        //echo "<div style='text-align: center;'>A következő kérdések arra vonatkoznak, hogy Ön hogyan érezte magát az utóbbi 1 évben.</div>";
        echo "<div id='covidformdiv' style='max-width:800px;margin:40px auto 40px auto;'>";
        echo "<form id='psychosocform' method=\"POST\">";

        echo "<div style='margin-top:20px;font-weight:bold'>1. {$webText["psyhosoc_bad_sleeping"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["bad_sleeping"])&&$_POST["bad_sleeping"]==1?"checked=true":"")." name='bad_sleeping' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["bad_sleeping"])&&$_POST["bad_sleeping"]==2?"checked=true":"")." name='bad_sleeping' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["bad_sleeping"])&&$_POST["bad_sleeping"]==3?"checked=true":"")." name='bad_sleeping' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["bad_sleeping"])&&$_POST["bad_sleeping"]==4?"checked=true":"")." name='bad_sleeping' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>2. {$webText["psyhosoc_exhaustion"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["exhaustion"])&&$_POST["exhaustion"]==1?"checked=true":"")." name='exhaustion' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["exhaustion"])&&$_POST["exhaustion"]==2?"checked=true":"")." name='exhaustion' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["exhaustion"])&&$_POST["exhaustion"]==3?"checked=true":"")." name='exhaustion' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["exhaustion"])&&$_POST["exhaustion"]==4?"checked=true":"")." name='exhaustion' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>3. {$webText["psyhosoc_difficulty_in_sleeping"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_in_sleeping"])&&$_POST["difficulty_in_sleeping"]==1?"checked=true":"")." name='difficulty_in_sleeping' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_in_sleeping"])&&$_POST["difficulty_in_sleeping"]==2?"checked=true":"")." name='difficulty_in_sleeping' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_in_sleeping"])&&$_POST["difficulty_in_sleeping"]==3?"checked=true":"")." name='difficulty_in_sleeping' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_in_sleeping"])&&$_POST["difficulty_in_sleeping"]==4?"checked=true":"")." name='difficulty_in_sleeping' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>4. {$webText["psyhosoc_physical_exhaustion"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["physical_exhaustion"])&&$_POST["physical_exhaustion"]==1?"checked=true":"")." name='physical_exhaustion' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["physical_exhaustion"])&&$_POST["physical_exhaustion"]==2?"checked=true":"")." name='physical_exhaustion' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["physical_exhaustion"])&&$_POST["physical_exhaustion"]==3?"checked=true":"")." name='physical_exhaustion' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["physical_exhaustion"])&&$_POST["physical_exhaustion"]==4?"checked=true":"")." name='physical_exhaustion' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>5. {$webText["psyhosoc_emotional_exhaustion"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["emotional_exhaustion"])&&$_POST["emotional_exhaustion"]==1?"checked=true":"")." name='emotional_exhaustion' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["emotional_exhaustion"])&&$_POST["emotional_exhaustion"]==2?"checked=true":"")." name='emotional_exhaustion' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["emotional_exhaustion"])&&$_POST["emotional_exhaustion"]==3?"checked=true":"")." name='emotional_exhaustion' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["emotional_exhaustion"])&&$_POST["emotional_exhaustion"]==4?"checked=true":"")." name='emotional_exhaustion' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>6. {$webText["psyhosoc_bad_early_waking"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["bad_early_waking"])&&$_POST["bad_early_waking"]==1?"checked=true":"")." name='bad_early_waking' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["bad_early_waking"])&&$_POST["bad_early_waking"]==2?"checked=true":"")." name='bad_early_waking' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["bad_early_waking"])&&$_POST["bad_early_waking"]==3?"checked=true":"")." name='bad_early_waking' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["bad_early_waking"])&&$_POST["bad_early_waking"]==4?"checked=true":"")." name='bad_early_waking' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>7. {$webText["psyhosoc_tired"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["tired"])&&$_POST["tired"]==1?"checked=true":"")." name='tired' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["tired"])&&$_POST["tired"]==2?"checked=true":"")." name='tired' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["tired"])&&$_POST["tired"]==3?"checked=true":"")." name='tired' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["tired"])&&$_POST["tired"]==4?"checked=true":"")." name='tired' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>8. {$webText["psyhosoc_difficult_to_fall_back_asleep"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficult_to_fall_back_asleep"])&&$_POST["difficult_to_fall_back_asleep"]==1?"checked=true":"")." name='difficult_to_fall_back_asleep' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficult_to_fall_back_asleep"])&&$_POST["difficult_to_fall_back_asleep"]==2?"checked=true":"")." name='difficult_to_fall_back_asleep' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficult_to_fall_back_asleep"])&&$_POST["difficult_to_fall_back_asleep"]==3?"checked=true":"")." name='difficult_to_fall_back_asleep' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficult_to_fall_back_asleep"])&&$_POST["difficult_to_fall_back_asleep"]==4?"checked=true":"")." name='difficult_to_fall_back_asleep' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>9. {$webText["psyhosoc_cannot_relax"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["cannot_relax"])&&$_POST["cannot_relax"]==1?"checked=true":"")." name='cannot_relax' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["cannot_relax"])&&$_POST["cannot_relax"]==2?"checked=true":"")." name='cannot_relax' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["cannot_relax"])&&$_POST["cannot_relax"]==3?"checked=true":"")." name='cannot_relax' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["cannot_relax"])&&$_POST["cannot_relax"]==4?"checked=true":"")." name='cannot_relax' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>10. {$webText["psyhosoc_irritability"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["irritability"])&&$_POST["irritability"]==1?"checked=true":"")." name='irritability' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["irritability"])&&$_POST["irritability"]==2?"checked=true":"")." name='irritability' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["irritability"])&&$_POST["irritability"]==3?"checked=true":"")." name='irritability' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["irritability"])&&$_POST["irritability"]==4?"checked=true":"")." name='irritability' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>11. {$webText["psyhosoc_tension"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["tension"])&&$_POST["tension"]==1?"checked=true":"")." name='tension' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["tension"])&&$_POST["tension"]==2?"checked=true":"")."  name='tension' value='2' />{$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["tension"])&&$_POST["tension"]==3?"checked=true":"")." name='tension' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["tension"])&&$_POST["tension"]==4?"checked=true":"")." name='tension' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>12. {$webText["psyhosoc_stress"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["stress"])&&$_POST["stress"]==1?"checked=true":"")." name='stress' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["stress"])&&$_POST["stress"]==2?"checked=true":"")." name='stress' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["stress"])&&$_POST["stress"]==3?"checked=true":"")." name='stress' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["stress"])&&$_POST["stress"]==4?"checked=true":"")." name='stress' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>13. {$webText["psyhosoc_digestive_problems"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["digestive_problems"])&&$_POST["digestive_problems"]==1?"checked=true":"")." name='digestive_problems' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["digestive_problems"])&&$_POST["digestive_problems"]==2?"checked=true":"")." name='digestive_problems' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["digestive_problems"])&&$_POST["digestive_problems"]==3?"checked=true":"")." name='digestive_problems' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["digestive_problems"])&&$_POST["digestive_problems"]==4?"checked=true":"")." name='digestive_problems' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>14. {$webText["psyhosoc_allergic"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["allergic"])&&$_POST["allergic"]==1?"checked=true":"")." name='allergic' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["allergic"])&&$_POST["allergic"]==2?"checked=true":"")." name='allergic' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["allergic"])&&$_POST["allergic"]==3?"checked=true":"")." name='allergic' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["allergic"])&&$_POST["allergic"]==4?"checked=true":"")." name='allergic' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>15. {$webText["psyhosoc_difficulty_concentrating"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_concentrating"])&&$_POST["difficulty_concentrating"]==1?"checked=true":"")." name='difficulty_concentrating' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_concentrating"])&&$_POST["difficulty_concentrating"]==2?"checked=true":"")." name='difficulty_concentrating' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_concentrating"])&&$_POST["difficulty_concentrating"]==3?"checked=true":"")." name='difficulty_concentrating' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_concentrating"])&&$_POST["difficulty_concentrating"]==4?"checked=true":"")." name='difficulty_concentrating' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>16. {$webText["psyhosoc_difficulty_remembering"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_remembering"])&&$_POST["difficulty_remembering"]==1?"checked=true":"")." name='difficulty_remembering' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_remembering"])&&$_POST["difficulty_remembering"]==2?"checked=true":"")." name='difficulty_remembering' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_remembering"])&&$_POST["difficulty_remembering"]==3?"checked=true":"")." name='difficulty_remembering' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["difficulty_remembering"])&&$_POST["difficulty_remembering"]==4?"checked=true":"")." name='difficulty_remembering' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>17. {$webText["psyhosoc_relax_after_work"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["relax_after_work"])&&$_POST["relax_after_work"]==1?"checked=true":"")." name='relax_after_work' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["relax_after_work"])&&$_POST["relax_after_work"]==2?"checked=true":"")." name='relax_after_work' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["relax_after_work"])&&$_POST["relax_after_work"]==3?"checked=true":"")." name='relax_after_work' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["relax_after_work"])&&$_POST["relax_after_work"]==4?"checked=true":"")." name='relax_after_work' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>18. {$webText["psyhosoc_withdrawn_personality"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["withdrawn_personality"])&&$_POST["withdrawn_personality"]==1?"checked=true":"")." name='withdrawn_personality' value='1' /> {$webText["allandoan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["withdrawn_personality"])&&$_POST["withdrawn_personality"]==2?"checked=true":"")." name='withdrawn_personality' value='2' /> {$webText["gyakran"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["withdrawn_personality"])&&$_POST["withdrawn_personality"]==3?"checked=true":"")." name='withdrawn_personality' value='3' /> {$webText["ritkan"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["withdrawn_personality"])&&$_POST["withdrawn_personality"]==4?"checked=true":"")." name='withdrawn_personality' value='4' /> {$webText["egyaltalannem"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>19. {$webText["psyhosoc_describe_your_health"]}</div>";

        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["describe_your_health"])&&$_POST["describe_your_health"]==1?"checked=true":"")." name='describe_your_health' value='1' /> {$webText["kituno"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["describe_your_health"])&&$_POST["describe_your_health"]==2?"checked=true":"")." name='describe_your_health' value='2' /> {$webText["nagyonjo"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["describe_your_health"])&&$_POST["describe_your_health"]==3?"checked=true":"")." name='describe_your_health' value='3' /> {$webText["jo"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["describe_your_health"])&&$_POST["describe_your_health"]==4?"checked=true":"")." name='describe_your_health' value='4' /> {$webText["turheto"]}</div>";
        echo "<div><input class='psychosocelement' type='radio' ".(isset($_POST["describe_your_health"])&&$_POST["describe_your_health"]==5?"checked=true":"")." name='describe_your_health' value='5' /> {$webText["rossz"]}</div>";

        echo "<div style='margin-top:20px;font-weight:bold'>20. {$webText["psyhosoc_overall_health_status"]}</div>";

        echo "<div><table>";
        echo "<tr><td><input class='psychosocelement' type=\"checkbox\" ".(isset($_POST["headache"])?"checked=true":"")." name=\"headache\" value=\"1\">&nbsp;{$webText["headache"]}</td><td><input type=\"checkbox\" ".(isset($_POST["dizziness"])?"checked=true":"")." name=\"dizziness\" value=\"1\">&nbsp;{$webText["dizziness"]}</td></tr>";
        echo "<tr><td><input class='psychosocelement' type=\"checkbox\" ".(isset($_POST["muscle_tension"])?"checked=true":"")." name=\"muscle_tension\" value=\"1\">&nbsp;{$webText["muscle_tension"]}</td><td><input type=\"checkbox\"  ".(isset($_POST["vomiting"])?"checked=true":"")." name=\"vomiting\" value=\"1\">&nbsp;{$webText["vomiting"]}</td></tr>";
        echo "<tr><td><input class='psychosocelement' type=\"checkbox\" ".(isset($_POST["heartbeat"])?"checked=true":"")." name=\"heartbeat\" value=\"1\">&nbsp;{$webText["heartbeat"]}</td><td><input type=\"checkbox\" ".(isset($_POST["vision_problems"])?"checked=true":"")." name=\"vision_problems\" value=\"1\">&nbsp;{$webText["vision_problems"]}</td></tr>";
        echo "<tr><td><input class='psychosocelement' type=\"checkbox\" ".(isset($_POST["fatigue"])?"checked=true":"")." name=\"fatigue\" value=\"1\">&nbsp;{$webText["fatigue"]}</td><td><input type=\"checkbox\" ".(isset($_POST["sweating"])?"checked=true":"")." name=\"sweating\" value=\"1\">&nbsp;{$webText["sweating"]}</td></tr>";
        echo "<tr><td><input class='psychosocelement' type=\"checkbox\" ".(isset($_POST["susceptibility_to_infection"])?"checked=true":"")." name=\"susceptibility_to_infection\" value=\"1\">&nbsp;{$webText["susceptibility_to_infection"]}</td><td><input type=\"checkbox\" ".(isset($_POST["burning_sensation_in_the_chest"])?"checked=true":"")." name=\"burning_sensation_in_the_chest\" value=\"1\">&nbsp;{$webText["burning_sensation_in_the_chest"]}</td></tr>";
        echo "<tr><td><input class='psychosocelement' type=\"checkbox\" ".(isset($_POST["constipation"])?"checked=true":"")." name=\"constipation\" value=\"1\">&nbsp;{$webText["constipation"]}</td><td><input type=\"checkbox\" ".(isset($_POST["itch"])?"checked=true":"")." name=\"itch\" value=\"1\">&nbsp;{$webText["itch"]}</td></tr>";
        echo "<tr><td><input class='psychosocelement' type=\"checkbox\" ".(isset($_POST["inner_pain"])?"checked=true":"")." name=\"inner_pain\" value=\"1\">&nbsp;{$webText["inner_pain"]}</td><td><input type=\"checkbox\" ".(isset($_POST["internal_tremor"])?"checked=true":"")." name=\"internal_tremor\" value=\"1\">&nbsp;{$webText["internal_tremor"]}</td></tr>";
        echo "</table></div>";

        echo $submitButton;

        echo "</form>";
    }
}