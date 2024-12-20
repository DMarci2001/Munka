<?php

class InspectionReminderService
{
    public $companyId;
    protected $companyData;
    private $patients;

    /**
     * Csökkenő sorrendben add meg a funkciókat!
     */
    protected $conditions = [
        76 => [
            0 => [
                "name" => "beforeExpiryBy30days",
                "object" => "Alkalmassági vizsgálata 30 nap múlva lejár!",
                "content" => "Kedves #nev#,<br> Foglalkozás-egészségügyi vizsgálata 30 nap múlva lefog járni, <strong>#ervenyesseg# dátummal</strong>. 
                Kérjük, az alábbi linken, jelentkezzen be az időszakos vizsgálatára!<br><br>
                Link: <a style='color:#a00' href='https://#domain#.hungariamed.hu' target='_blank'>https://#domain#.hungariamed.hu</a><br><br>
                Tisztelettel,<br>
                Hungária Med-M Csapata",
            ],
            1 => [
                "name" => "beforeExpiryBy2weeks",
                "object" => "Alkalmassági vizsgálata 2 hét múlva lejár!",
                "content" => "Kedves #nev#,<br> Foglalkozás-egészségügyi vizsgálata 2 hét múlva lefog járni, <strong>#ervenyesseg# dátummal</strong>. 
                Kérjük, az alábbi linken, jelentkezzen be az időszakos vizsgálatára!<br>
                Link: <a style='color:#a00' href='https://#domain#.hungariamed.hu' target='_blank'>https://#domain#.hungariamed.hu</a><br>
                Tisztelettel,<br>
                Hungária Med-M Csapata",
            ],
            2 => [
                "name" => "beforeExpiryBy3days",
                "object" => "Alkalmassági vizsgálata 3 nap múlva lejár!",
                "content" => "Kedves #nev#,<br> Foglalkozás-egészségügyi vizsgálata 3 nap múlva lefog járni, <strong>#ervenyesseg# dátummal</strong>. 
                Kérjük, az alábbi linken, jelentkezzen be az időszakos vizsgálatára!<br>
                Link: <a style='color:#a00' href='https://#domain#.hungariamed.hu' target='_blank'>https://#domain#.hungariamed.hu</a><br>
                Tisztelettel,<br>
                Hungária Med-M Csapata",
            ],
            3 => [
                "name" => "afterExpiry",
                "object" => "Alkalmassági vizsgálata lejárt!",
                "content" => "Kedves #nev#,<br> Foglalkozás-egészségügyi vizsgálata sajnos lejárt <strong>#ervenyesseg# dátummal</strong>. 
                Kérjük, az alábbi linken, jelentkezzen be az időszakos vizsgálatára!<br>
                Link: <a style='color:#a00' href='https://#domain#.hungariamed.hu' target='_blank'>https://#domain#.hungariamed.hu</a><br>
                Tisztelettel,<br>
                Hungária Med-M Csapata",
            ],

        ],
    ];


    public function __construct($companyId)
    {
        $this->companyId = $companyId;
        $this->setCompanyData();
        $this->setPatientList();
        $this->setConditions();
    }

    public function sendReminders()
    {
        $notificationService = new NotificationService();
        foreach ($this->patients as $key => $patient) {
            if (!empty($patient["nextReminder"])) {

                $patient["reminderType"] = "{$patient["nextReminder"]}-" . (empty($patient["ervenyesseg"]) ? $patient["email"] : $patient["ervenyesseg"]);
                if (!$query = sql_query(
                    "SELECT * FROM notifications WHERE tipus=? AND INSTR(destination,?) AND !INSTR(tipus,'afterExpiry') LIMIT 1",
                    [$patient["reminderType"], $patient["email"]]
                )->fetch(PDO::FETCH_ASSOC)) {
                    $details = $this->replacePlaceholders($this->getConditionDetails($patient["nextReminder"]), $key);
                    $notificationService->ReminderForInspection($patient, $details);
                    echo "Értesítő ({$patient["nextReminder"]}) kiküldve, {$patient["nev"]} páciensnek.<br>";
                }
            }
        }
        return;
    }



    private function setCompanyData()
    {
        $this->companyData = sql_query("SELECT * FROM cegek WHERE id=?", [$this->companyId])->fetch(PDO::FETCH_ASSOC);
        $dokirexId = json_decode($this->companyData["dokirexcegid_json"], true);

        if (!empty($dokirexId[0])) {
            $dokirexCoData = sql_query("SELECT * FROM dokirex_telephelyek WHERE TelephelyID=?", [$dokirexId[0]])->fetch(PDO::FETCH_ASSOC);
            $this->companyData = array_merge($this->companyData, $dokirexCoData);
        }
        return;
    }

    public function showCompanyData()
    {
        echo "<pre>";
        print_r($this->companyData);
        echo "</pre>";
        return;
    }
    private function replacePlaceholders($details, $patientKey)
    {
        $search = ["#nev#", "#domain#","#ervenyesseg#"];
        $replace = [$this->patients[$patientKey]["nev"], $this->companyData["domain"],$this->patients[$patientKey]["ervenyesseg"]];

        //Levél tartalmának átírása
        $details["content"] = str_replace($search, $replace, $details["content"]);
        return $details;
    }

    public function getSzakrendelesStrings()
    {
        $result = [];
        $query = sql_query("SELECT szakrendeles FROM dokirex_vizsgalatok 
                            WHERE INSTR(szakrendeles,'fogl') 
                            AND !INSTR(szakrendeles,'Összefoglaló kiértékelés') GROUP BY szakrendeles")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($query as $record) {
            $result[] = $record["szakrendeles"];
        }
        return $result;
    }

    private function setPatientList()
    {
        //Egyenlőre simán ki listázom a dolgozókat a dokirex_vizsgalatokból, mert nincs állományi listám a cégtől >.>
        $this->patients = sql_query(
            "SELECT dv.paciensid,dv.nev,dv.szuldatum,dv.telephely,dv.email,
            (SELECT MAX(ervenyesseg) FROM dokirex_vizsgalatok 
             WHERE paciensid=dv.paciensid AND telephely=? AND dv.szakrendeles IN('" . implode("','", $this->getSzakrendelesStrings()) . "') LIMIT 1) as ervenyesseg
            FROM dokirex_vizsgalatok dv
            WHERE dv.telephely = ? GROUP BY paciensid",
            [$this->companyData["TelephelyNev"], $this->companyData["TelephelyNev"]]
        )->fetchAll(PDO::FETCH_ASSOC);
        return;
    }

    public function showPatients($patientid=null)
    {
        if($patientid){
            $key=array_search($patientid,array_column($this->patients,"paciensid"));
            if($key!==false){
                echo "<pre>";
                print_r($this->patients[$key]);
                echo "</pre>";
                return;
            }
            return "not found.";
        }

        echo "<pre>";
        print_r($this->patients);
        echo "</pre>";
        return;
    }

    private function getConditionNames()
    {
        $result = [];
        foreach ($this->conditions[$this->companyId] as $condition) {
            $result[] = $condition["name"];
        }
        return $result;
    }

    public function getConditionDetails($conditionName)
    {
        $key = false;
        $key = array_search($conditionName, array_column($this->conditions[$this->companyId], "name"));
        if ($key !== false) {
            return $this->conditions[$this->companyId][$key];
        }
        return false;
    }

    private function setConditions()
    {
        $conditions = $this->getConditionNames();
        foreach ($this->patients as $key => $patient) {
            foreach ($conditions as $condition) {
                $this->patients[$key][$condition] = $this->$condition($patient["ervenyesseg"], $patient["email"], $key);
                if ($this->$condition($patient["ervenyesseg"], $patient["email"], $key)) {
                    $this->patients[$key]["nextReminder"] = $condition;
                }
            }
            //Ha a fenti kód talált következő értesítési lehetőséget, megvizsgálom, nincs-e véletlenül már olyan értesítés ami már a kijelölt condíción túl mutat
            //későbbi mint a kijelölt értesítés
            if(isset($this->patients[$key]["nextReminder"])){
                $condition_position = array_search($this->patients[$key]["nextReminder"],array_column($this->conditions[$this->companyId],"name"));
                for($i=$condition_position+1;$i<count($conditions);$i++){
                    if(isset($this->patients[$key][$conditions[$i]."-".$this->patients[$key]["ervenyesseg"]])){
                        $this->patients[$key]["nextReminder"] = null;
                        break;
                    }
                }
            }
            
            
        }
        return;
    }


    /*Conditions*/
    private function beforeExpiryBy30days($expiry, $email, $key)
    {
        if (strtotime($expiry) <= strtotime("now + 30 days")) {
            if (!$query = sql_query("SELECT * FROM notifications WHERE tipus=? AND INSTR(destination,?) LIMIT 1", ["beforeExpiryBy30days-{$expiry}", $email])->fetch(PDO::FETCH_ASSOC)) {
                return true;
            } else {
                $this->patients[$key]["beforeExpiryBy30days-{$expiry}"] = $query["datum"];
            }
        }
        return false;
    }

    private function beforeExpiryBy2weeks($expiry, $email, $key)
    {
        if (strtotime($expiry) <= strtotime("now + 2 weeks")) {
            if (!$query = sql_query("SELECT * FROM notifications WHERE tipus=? AND INSTR(destination,?) LIMIT 1", ["beforeExpiryBy2weeks-{$expiry}", $email])->fetch(PDO::FETCH_ASSOC)) {
                return true;
            } else {
                $this->patients[$key]["beforeExpiryBy2weeks-{$expiry}"] = $query["datum"];
            }
        }
        return false;
    }

    private function beforeExpiryBy3days($expiry, $email, $key)
    {
        if (strtotime($expiry) <= strtotime("now + 3 days")) {
            if (!$query = sql_query("SELECT * FROM notifications WHERE tipus=? AND INSTR(destination,?) LIMIT 1", ["beforeExpiryBy3days-{$expiry}", $email])->fetch(PDO::FETCH_ASSOC)) {
                return true;
            } else {
                $this->patients[$key]["beforeExpiryBy3days-{$expiry}"] = $query["datum"];
            }
        }
        return false;
    }

    private function afterExpiry($expiry, $email, $key)
    {
        if (strtotime($expiry) <= strtotime("now")) {
            return true;
            $query = sql_query("SELECT * FROM notifications WHERE tipus=? AND INSTR(destination,?) ORDER BY datum DESC LIMIT 1", ["afterExpiry-{$expiry}", $email])->fetch(PDO::FETCH_ASSOC);
            $this->patients[$key]["afterExpiry-{$expiry}"] = $query["datum"];
        }
        return false;
    }
}
