<?php

//start: start-stop-daemon --background --start --verbose --make-pidfile --pidfile /var/run/commcl.pid --exec /root/commcl/commcl
//stop: start-stop-daemon --stop --pidfile /var/run/commcl.pid

// /var/tlink_hungariamed/in/
// /var/tlink_hungariamed/out/

// /var/commcl/out/
// /var/commcl/in/


class SpektrumlabService {
    private array $spektrumLabParams = [
        "hungariamed" => [
            "login" => "hungariamedm",
            "laborId" => "SPEKTRUMLAB",
            "bekuldoKod" => "000000370",
            "bekuldoNev" => "Hungária Med-M Kft.",
            "inDir"=> "/var/tlink_hungariamed/in/",
            "outDir" => "/var/tlink_hungariamed/out/",
            "serviceName" => "/root/commcl/commcl",
            "orvosNev" => "Dr. Magyar Judit",
            "orvosPecsetszam" => "44601"
        ],
        "hungariamed_suzuki" => [
            "login" => "hungariamedm",
            "laborId" => "SPEKTRUMLAB",
            "bekuldoKod" => "000000396",
            "bekuldoNev" => "Hungária Med-M Kft. (Suzuki szűrés)",
            "inDir"=> "/var/tlink_hungariamed/in/",
            "outDir" => "/var/tlink_hungariamed/out/",
            "serviceName" => "/root/commcl/commcl",
            "orvosNev" => "Dr. Magyar Judit",
            "orvosPecsetszam" => "44601"
        ],
        "keltexmed" => [
            "login" => "keltexmed",
            "laborId" => "SPEKTRUMLAB",
            "bekuldoKod" => "000000390",
            "bekuldoNev" => "Keltexmed Kft.",
            "inDir"=> "/var/commcl_keltexmed/in/",
            "outDir" => "/var/commcl_keltexmed/out/",
            "serviceName" => "/var/commcl_keltexmed/commcl",
            "orvosNev" => "Dr Nagy Károly",
            "orvosPecsetszam" => "59963"
        ],
    ];
    public array $params = [];

    const IN_FILE = "lab.msg";
    const OUT_FILE = "lab.msg";
    const SEMAFOR_FILE = "lab.sem";

    const EOF = "\r\n";

    public bool $orvosNemFontos = true;

    public function __construct() {
        $index = Booking_Constants::SQL_DB;
        $this->params = $this->spektrumLabParams[$index];
    }

    public function serviceRunning():bool {
        return true;

        $output = `ps -aux | grep commcl`;
        if (substr_count($output, "commcl") >= 3) {
            return true;
        }

        return false;
    }

    public function writeNextRequest($requestId):string {
        if ($this->requestRunning()) {
            return "Fut az előző laborkérés!";
        }

        if ($requestData = sql_query("select * from labrequests where status='pending' and id=? order by created limit 1", [$requestId])->fetch(PDO::FETCH_ASSOC)) {
            $data = $this->generateHL7FileByRequestId($requestData["id"]);
            $this->writeRequestFile($data);
            $this->writeSemaforFile();
            sql_query("insert into labrequestmessages set tipus='out', datum=now(), content=?, requestid=?", [$data, $requestData["id"]]);
        } else {
            return "Laborkérés nem található!";
        }

        return "";
    }

    public function getReceivedAnswer() {
        $inFileName = $this->params["inDir"] . self::IN_FILE;
        $inSemaforFileName = $this->params["inDir"] . self::SEMAFOR_FILE;
        //válasz feldolgozás, utána fájlok törlése
        if (is_file($inSemaforFileName)) {
            if (is_file($inFileName)) {
                $content = file_get_contents($inFileName);
                sql_query("insert into labrequestmessages set tipus='in', datum=now(), content=?", [$content]);
            }
            $this->deleteInFiles();
        }
    }


    private static function ucName($name):string {
        $name = Utils::convertAccentsAndSpecialToNormal(trim($name));
        if ($name == mb_strtoupper($name)) {
            //nagybetűs nevek visszakonvertálása nagy kezdőbetűs kisbetűs nevekké
            $name = ucwords(mb_strtolower($name));
        }
        return $name;
    }


    public function generateHL7FileByRequestId($requestId):string {
        $requestData = sql_query("select * from labrequests where id=?", [$requestId])->fetch(PDO::FETCH_ASSOC);
        $reservationData = sql_query("select f.*, o.nev as orvosnev from foglalasok f left join orvosok o on o.id=f.orvosassigned where f.id=?", [$requestData["foglalasid"]])->fetch(PDO::FETCH_ASSOC);
        $items = sql_query("SELECT ri.*, commazo,t.`name` FROM labrequestitems ri LEFT JOIN synlab_labor_tetelek t ON t.id=ri.itemid WHERE ri.requestid=?", [$requestId])->fetchAll(PDO::FETCH_ASSOC);
        $result = "";

        $login = $this->params["login"];
        $laborId = $this->params["laborId"];
        $kuldesDatum = date("YmdHi");
        $adatBlokkAzonosito = $requestData["id"];
        $paciensId = trim($reservationData["paciensid"]);
        $paciensNev = self::ucName($reservationData["nev"]);
        $paciensAnyjaNeve = self::ucName($reservationData["anyjaneve"]);
        $paciensSzulDatum = date("Ymd", strtotime($reservationData["szuldatum"]));
        $paciensGender = $reservationData["neme"] == 1 ? "M" : "F";
        $paciensAddress = Utils::convertAccentsAndSpecialToNormal(trim($reservationData["utca"]));
        $paciensCity = Utils::convertAccentsAndSpecialToNormal(trim($reservationData["varos"]));
        $paciensIrsz = trim($reservationData["irsz"]);
        $paciensCountry = "HUN";
        $paciensTAJ = trim($reservationData["taj"]);
        $orvosId = $this->params["orvosPecsetszam"];
        $orvosPecsetSzam = $this->params["orvosPecsetszam"];
        $orvosNev = $this->params["orvosNev"];
        $bekuldoKod = $this->params["bekuldoKod"];;
        $bekuldoNev = $this->params["bekuldoNev"];;
        $naploszam = $requestData["id"];
        $bekuldesDatum = date("Ymd");
        $felveteliDatum = date("YmdHi", strtotime($reservationData["datum"]));
        if ($reservationData["neme"] == 0) {
            $paciensGender = "X";
        }

        if ($paciensId == 0) {
            $paciensId = "f{$paciensTAJ}";
        }

        if ($this->orvosNemFontos) {
            $orvosId = "00000";
            $orvosNev = ".";
            $orvosPecsetSzam = "00000";
        }

        //MSH - Fejléc
        $result .= "MSH|^~\&|{$login}||{$laborId}||{$kuldesDatum}||ORM^O01|{$adatBlokkAzonosito}|P|2.3|||NE|AL|".self::EOF;
        //PID - Betegadatok
        $result .= "PID|||{$paciensId}|X^HMM{$paciensId}|{$paciensNev}|{$paciensAnyjaNeve}|{$paciensSzulDatum}|{$paciensGender}|||{$paciensAddress}^^{$paciensCity}^^{$paciensIrsz}^{$paciensCountry}||||||||{$paciensTAJ}|||||||{$paciensCountry}||||N".self::EOF;
        //PV1 - Kérő adatok
        $result .= "PV1||O|||||{$orvosId}^{$orvosNev}~{$orvosPecsetSzam}|||||||^{$bekuldoKod}^{$bekuldoNev}^||||||4P||||0||||||||||||||||||||{$felveteliDatum}|".self::EOF;
        //ZPV - További kérő adatok
        $result .= "ZPV|||||||||||||||||{$naploszam}||{$bekuldesDatum}".self::EOF;
        //ZPD - nyomtató paraméterek
        $result .= "ZPD|TYPE:EPL2~OFFSX:1200~OFFSY:40|".self::EOF;
        //ORC - Kérés azonosító
        $result .= "ORC|NW|{$requestId}^{$login}|||||^^^^^R||{$kuldesDatum}||".self::EOF;

        $sor = 1;
        foreach ($items as $item) {
            //OBR - Tesztek kérése
            $result .= "OBR|{$sor}|{$requestId}^{$login}||{$item["commazo"]}^{$item["name"]}^||||||{$login}|O" . self::EOF;
            $sor++;
        }

        return $result;
    }

    public function cronCheck() {
        //percenként hívva cronnal
        //$this->getReceivedAnswer();
        //$this->writeNextRequest();
        //$this->fillMissingMessageRequestIds();
    }

    public function fillMissingMessageRequestIds() {
        $messages = sql_query("SELECT * FROM labrequestmessages WHERE datum>DATE_SUB(NOW(), INTERVAL 1 WEEK) AND requestid=0 AND tipus='in'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($messages as $message) {
            $id = 0;
            $rows = explode("\r", $message["content"]);
            foreach ($rows as $key => $row) {
                $fields = explode("|", $row);
                if ($fields[0] == "MSA" && ($fields[1] == "AA" || $fields[1] == "AR")) {
                    $id = $fields[2];
                    break;
                }
            }
            sql_query("update labrequestmessages set requestid=? where id=?", [$id, $message["id"]]);
        }
    }

    public function requestRunning():bool {
        return is_file($this->params["outDir"].self::SEMAFOR_FILE) && is_file($this->params["outDir"].self::OUT_FILE);
    }

    public function writeSemaforFile() {
        file_put_contents($this->params["outDir"].self::SEMAFOR_FILE, "");
    }

    public function writeRequestFile($data) {
        file_put_contents($this->params["outDir"].self::OUT_FILE, $data);
    }

    public function deleteInFiles() {
        unlink($this->params["inDir"].self::IN_FILE);
        unlink($this->params["inDir"].self::SEMAFOR_FILE);
    }

    public function processPdfFromMessages():void {
        $tempPdf = "/var/pdfwork/spekTemp.pdf";
        $messages = sql_query("SELECT * FROM labrequestmessages WHERE STATUS='' and tipus='in' and datum>date_sub(now(), interval 1 week) ORDER BY datum DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($messages as $message) {
            $lastRequestId = 0;
            $lastResultDate = "0000-00-00 00:00:00";
            $rows = explode("\r", $message["content"]);
            foreach ($rows as $key => $row) {
                $fields = explode("|", $row);
                if (trim($fields[0]) == "OBR") {
                    $lastRequestId = intval($fields[2]);
                    $lastResultDate = date("Y-m-d H:i:s", strtotime($fields[7]));
                }
                if (trim($fields[3]) == "LELETPDF") {
                    file_put_contents($tempPdf, base64_decode($fields[5]));
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($tempPdf);
                    $text = $pdf->getText();
                    $folyamatban = substr_count($text, "Folyamatban") ? 1:0;

                    sql_query("update labrequests set status='done', ertesitve=0, folyamatban=?, resultpdf=?, resultdate=? where id=?", [$folyamatban, $fields[5], $lastResultDate, $lastRequestId]);
                    $lastRequestId = intval($fields[2]);
                }
            }

            sql_query("update labrequestmessages set status='processed' where id=?", [$message["id"]]);
        }
    }


    public function setSpectrumLabKapcs() {
        /*
        $content = file_get_contents(__DIR__."/labortetelkapcs.tsv");
        $kapcs = [];

        $rows = explode("\n", $content);
        foreach ($rows as $row) {
            $fields = explode("\t", $row);
            $id = trim($fields[0]);
            $spectrumLabId = trim($fields[2]);

            if (!empty($id) && !empty($spectrumLabId)) {
                echo $spectrumLabId." ";
                sql_query("update synlab_labor_tetelek set spid=? where id=?", [$spectrumLabId, $id]);
                $kapcs[$id] = $spectrumLabId;
            }
        }
        */

        $items = sql_query("select * from synlab_labor_tetelek where spid<>0")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            $kapcs[$item["id"]] = $item["spid"];
        }


        $packs = sql_query("select * from synlab_labor_csomagok")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($packs as $pack) {
            $spItems = [];
            $items = json_decode($pack["items"], JSON_OBJECT_AS_ARRAY);
            foreach ($items as $item) {
                if (isset($kapcs[$item])) {
                    $spItems[] = $kapcs[$item];
                }
            }
            echo $pack["name"]." {$pack["items"]}</br>";
            sql_query("update synlab_labor_csomagok set spektrumitems=? where id=?", [json_encode(array_values($spItems)), $pack["id"]]);
        }
    }

    public function importTetelek() {
        return;

        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $rows = explode(PHP_EOL, $this->spectrumlabTetelek);
        foreach ($rows as $row) {
            $data = explode(";", $row);
            //print_r($data);
            //sql_query("insert into synlab_labor_tetelek set provider='spektrumlab', appform=0, commazo=?, kod=?, name=?, elkeszules=1, category=0, price=0", [$data[0], $data[2], $data[1]]);
            //die("itt");
        }
    }

    public function sendAutomaticRequests() {
        $requests = sql_query("SELECT lm.id AS messageid, r.id AS requestid FROM labrequests r 
            LEFT JOIN foglalasok f ON f.id=r.foglalasid
            LEFT JOIN labrequestmessages lm ON lm.requestid=r.id
            WHERE r.createdby='automatic' AND r.status='pending' AND lm.id IS NULL
            ORDER BY r.created LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($requests as $request) {
            for ($i=0;$i<10;$i++) {
                $result = $this->writeNextRequest($request["requestid"]);
                echo "result: {$result}\n";
                if ($result == "") {
                    break;
                }
                sleep(2);
            }
        }
    }

    private string $spectrumlabTetelek = "13fak;Faktor XIII*;13fak;0
17OH;17-OH-progeszteron*;17OH;0
5hiaa;5-HIAA (vizelet)*;5hiaa;0
8fak;Faktor VIII*;8fak;0
a1atri;Alfa-1 antitripszin*;a1atri;0
aborbe;Autoimmun bőrbetegségek antitestjei*;aborbe;0
acetat;Acetilkolin receptor elleni antitest*;acetat;0
ACTH;ACTH;ACTH;0
adh;CT-pro-ADH (kopeptin)*;adh;0
adipo;Adiponektin*;adipo;0
aerob;Baktérium aerob tenyésztése;aerob;0
AFP;Alfa-fötoprotein (AFP);AFP;0
Agyeel;Gyermek élelmiszer panel - 60 allergén*;Agyeel;0
Agyerk;Gyermek Komplex panel - 79 allergén*;Agyerk;0
AgyerO;Gyermek OAS panel-108 allergén*;AgyerO;0
aHAV;Hepatitis A elleni antitestek (IgM,IgG);aHAV;0
ahbcgm;Anti-HBc IgM;ahbcgm;0
aHBe;Anti-HBe antitest;aHBe;0
aHBs;Anti-HBs antitest;aHBs;0
aHCV;Hepatitis C vírus elleni antitest;aHCV;0
Ainhal;Inhalatív Állati panel - 53 allergén*;Ainhal;0
Ainhex;Inhalatív Extractum panel - 36 extractum*;Ainhex;0
Ainhin;Inhalatív Indoor panel - 45 allergén *;Ainhin;0
Ainhko;Inhalatív Komplex panel - 125 allergén*;Ainhko;0
Ainhno;Inhalatív Növényi panel - 59 allergén*;Ainhno;0
Alb;Albumin;Alb;0
aldosz;Aldoszteron*;aldosz;0
allro;Allergiapanel (rovarok);allro;0
alpm;Alkalikus foszfatáz;alpm;0
AMH;Anti-Müllerian Hormon (AMH);AMH;0
amidif;Aminósav differenciálás*;amidif;0
Amil;Amiláz;Amil;0
amusk;Anti-MuSK antitest*;amusk;0
anabl;Antinukleáris antitest profil IgG (immunoblot) *;anabl;0
anaeli;ANA (anti-nukleáris antitest) ELISA*;anaeli;0
anaero;Baktérium anaerob tenyésztése;anaero;0
anahep;ANA (anti-nukleáris antitest) Hep-2;anahep;0
anca;ANCA (MPO és PR3)*;anca;0
ancap;ANCA profil*;ancap;0
Andro;Androszténdion;Andro;0
anemat;Anémia pernicióza panel*;anemat;0
annexG;Annexin V IgG*;annexG;0
annexM;Annexin V IgM*;annexM;0
antccp;Anti-CCP (anti-filaggrin)*;antccp;0
antciq;Anti C1q antitest*;antciq;0
Anutal;Nutritív Állati panel - 56 allergén*;Anutal;0
Anutex;Nutritív Extractum panel - 76 extractum*;Anutex;0
Anutko;Nutritív Komplex panel - 152 allergén*;Anutko;0
Anutno;Nutritív Növényi panel- 96 alleregén*;Anutno;0
Anutve;Nutritív Vegetáriánus - 111 allergén*;Anutve;0
apcrat;APC rezisztencia;apcrat;0
apoa1;Apolipoprotein A1*;apoa1;0
apoa2;Apolipoprotein A2*;apoa2;0
apob;Apolipoprotein B*;apob;0
apti;Aktivált parc. tromboplasztin idő (APTI);apti;0
aqua4;Aquaporin-4 elleni antitest*;aqua4;0
ascaa;ASCA IgA*;ascaa;0
ascag;ASCA IgG*;ascag;0
ASO;Anti-streptolizin O (ASO);ASO;0
at3;Antitrombin III*;at3;0
Atelj;Teljes panel - 295 allergén*;Atelj;0
aTG;Anti-tireoglobulin (anti-TG);aTG;0
aTPO;Anti-TPO;aTPO;0
autmaj;Autoimmun májbetegségek antitestjei*;autmaj;0
avit;A vitamin*;avit;0
axa;Anti-Xa (LMW-Heparin)*;axa;0
B12;B12 vitamin;B12;0
b1vit;B1 vitamin*;b1vit;0
B2MG;Béta-2-mikroglobulin*;B2MG;0
b2vit;B2 vitamin*;b2vit;0
b3vit;B3 vitamin*;b3vit;0
b5vit;B5 vitamin (pantoténsav)*;b5vit;0
b6vit;B6 vitamin*;b6vit;0
BCL;Béta-crosslaps (kollagén keresztkötés);BCL;0
borel;Borrelia burgdorferi at. (IgG,IgM);borel;0
bowgm;Borrelia at. (IgG,IgM) + Western blot;bowgm;0
brcaco;BRCA Complete*;brcaco;0
brcafo;BRCA Focus*;brcafo;0
bstrec;B csop. Streptococcus szűrő cervikális váladékból;bstrec;0
bstrep;B csop. Streptococcus szűrő hüvelyváladékból;bstrep;0
c1akt;C1-észteráz inhibitor aktivitás*;c1akt;0
c1kon;C1-észteráz inhibitor koncentráció*;c1kon;0
c3;C3 komplement*;c3;0
c4;C4 komplement*;c4;0
Ca;Kalcium (Ca);Ca;0
CA125;CA 125;CA125;0
CA153;CA 15-3;CA153;0
CA199;CA 19-9;CA199;0
ca50;CA 50*;ca50;0
CA724;CA 72-4;CA724;0
Carb;Karbamazepin*;Carb;0
CEA;CEA;CEA;0
cellim;Celluláris immunstátusz*;cellim;0
cellnk;Celluláris immunstátusz és NK funkció*;cellnk;0
cftr;CFTR gén 36 mutációja*;cftr;0
Che;Pszeudo-kolinészteráz;Che;0
chlamp;Chlamydia pneumoniae antitestek*;chlamp;0
chlamt;Chlamydia trachomatis antitestek*;chlamt;0
ciklos;Ciklosporin A*;ciklos;0
ciszt;Cisztatin C*;ciszt;0
citplt;Trombocita citrátos vérből;citplt;0
CK;Kreatin-kináz (CK);CK;0
ckizo;CK izoenzimek*;ckizo;0
ckmbt;CK-MB koncentráció;ckmbt;0
Cl;Klorid (Cl);Cl;0
clapcr;Chlamydia trachomatis PCR*;clapcr;0
CMV;Cytomegalovírus (CMV) at. (IgG,IgM);CMV;0
cmvavi;CMV IgG aviditás;cmvavi;0
cneutr;SARS-CoV2 Neutralizáló antitest kimutatás (ELISA)*;xcneut;0
Coer;Cöruloplazmin*;Coer;0
coigra;SARS-CoV-2 sejtes immunitás vizsgálat Euroimmun IGRA*;xcoigr;0
colign;Cöliákia genetikai vizsgálata*;colign;0
colim;Cöliákia szűrés (tTg IgA, tTg IgG)*;colim;0
colis;Cöliákia szűrés (tTg IgA, tTg IgG)*;colim;0
concpg;SARS-CoV2 NCP IgG ELISA (szemikvantitatív)*;xconcp;0
covat2;SARS-CoV-2 IgG (1st IS) antitest;covat2;0
covat3;SARS-CoV-2 IgG (1st IS) antitest;covat2;0
covpcr;SARS-CoV-2 PCR*;covpcr;0
Cpeptid;C-peptid;Cpeptid;0
CRP;C reaktív protein (CRP);CRP;0
CRPU;C reaktív protein ultraszenzitív (hsCRP);CRPU;0
cTnI;Troponin-I (hs-cTnI);cTnI;0
Cu;Réz (Cu) szérumból*;Cu;0
cvit;C vitamin*;cvit;0
Cyf211;Cyfra 21-1;Cyf211;0
csap;Csontspecifikus alkalikus foszfatáz (BAP)*;csap;0
d3vit;1,25-dihidroxi-D3 vitamin*;d3vit;0
dao;Hisztamin intolerancia (DAO)*;dao;0
Dbil;Direkt bilirubin;Dbil;0
Ddi;D-dimer;Ddi;0
DHEA;DHEA-szulfát (DHEAS);DHEA;0
Digo;Digoxin*;Digo;0
dihtes;Dihidro-tesztoszteron*;dihtes;0
dLDL;LDL-koleszterin;dLDL;0
dop;Dopamin*;dop;0
dsdns;dsDNS elleni antitest*;dsdns;0
ebv;Epstein-Barr v.at.(VCA, EA, EBNA);ebv;0
EE;Glükóz (ebéd előtt);EE;0
emaiga;Endomízium elleni antitest (EMA) IgG/IgA*;emaiga;0
enaeli;ENA panel ELISA*;enaeli;0
endoat;Endothel elleni antitest*;endoat;0
epesav;Epesav*;epesav;0
epesz;Epesav (széklet)*;epesz;0
epidat;Epidermális bazálmembrán elleni antitest*;epidat;0
eritro;Eritropoetin*;eritro;0
EU;Glükóz (ebéd után);EU;0
evit;E vitamin*;evit;0
Fe;Vas (Fe);Fe;0
FehELFO;Szérum elektroforézis (ELFO)*;FehELFO;0
fenil;Fenilanalin*;fenil;0
fereg;Féregpete kimutatása mikroszkópos vizsgálattal;fereg;0
Ferr;Ferritin;Ferr;0
Fibr;Fibrinogén;Fibr;0
Fol;Folsav;Fol;0
food46;Táplálékintolerancia (IgG) panel (46);food46;0
fosfG;Foszfatidilszerin IgG autoantitest*;fosfG;0
fosfM;Foszfatidilszerin IgM autoantitest*;fosfM;0
fosinG;Foszfatidilinozitol elleni antitest IgG*;fosinG;0
fosinM;Foszfatidilinozitol elleni antitest IgM*;fosinM;0
fprotS;Szabad protein S antigén*;fprotS;0
FPSA;Szabad PSA (fPSA);FPSA;0
Frukt;Fruktózamin;Frukt;0
FSH;Follikulus Stimuláló Hormon (FSH);FSH;0
FT3;Szabad T3 (fT3);FT3;0
FT4;Szabad T4 (fT4);FT4;0
fvleid;Faktor V (FV) / Leiden mutáció*;fvleid;0
gad;Glutamát-dekarboxiláz (GAD) at.*;gad;0
gard;Gardnerella vaginalis tenyésztés;gard;0
garpcr;Gardnerella vaginalis/Atopobium vaginae PCR*;garpcr;0
gastri;Gasztrin*;gastri;0
GGT;Gamma GT (GGT);GGT;0
GKrp;Vizelet kreatinin (gyűjtött vizelet);GKrp;0
Glu;Glükóz;Glu;0
GluF;Glükóz-plazmából;GluF;0
GOT;GOT (ASAT);GOT;0
GPT;GPT (ALAT);GPT;0
GT0;Glükóz 0'(glükózterhelés előtt);GT0;0
GT120;Glükóz 120'(glükózterhelés után);GT120;0
GT150;Glükóz 150'(glükózterhelés után);GT150;0
GT180;Glükóz 180' (glükózterhelés után);GT180;0
GT30;Glükóz 30' (glükózterhelés után);GT30;0
GT60;Glükóz 60' (glükózterhelés után);GT60;0
GT90;Glükóz 90' (glükózterhelés után);GT90;0
GyCa;Vizelet kálcium (gyűjtött vizelet);GyCa;0
GyCl;Vizelet klorid (gyűjtött vizelet);GyCl;0
GyFeh;Vizelet fehérje (gyűjtött vizelet);GyFeh;0
GyG;Vizelet glükóz (gyűjtött vizelet);GyG;0
GyHs;Vizelet húgysav (gyűjtött vizelet);GyHs;0
GyK;Vizelet kálium (gyűjtött vizelet);GyK;0
GyKar;Vizelet karbamid (gyűjtött vizelet);GyKar;0
gykor;Vizelet kortizol (gyűjtött vizelet);xgykor;0
GyMAU;Vizelet mikroalbumin (gyűjtött vizelet);GyMAU;0
GyMg;Vizelet magnézium (gyűjtött vizelet);GyMg;0
GyNa;Vizelet nátrium (gyűjtött vizelet);GyNa;0
GyP;Vizelet foszfát (gyűjtött vizelet);GyP;0
hapto;Haptoglobin*;hapto;0
harat;Harántcsíkolt izom elleni antitest*;harat;0
Hba1c;Hemoglobin A1c (HbA1c);Hba1c;0
HbeAg;Hepatitis B vírus e. antigén (HbeAg);HbeAg;0
HBsAg;Hepatitis B vírus s. antigén (HBsAg);HBsAg;0
HCG;HCG (human korion-gonadotropin);HCG;0
HDL;HDL-koleszterin;HDL;0
he4;HE-4 (Humán epididimisz protein 4);he4;0
Hepy;Helicobacter pylori elleni at.(IgG);Hepy;0
herpcr;Herpes simplex (HSV) PCR*;herpcr;0
HGH;Növekedési hormon (hGH);HGH;0
hisz;Hisztamin*;hisz;0
hiv-12;HIV-1,2 antitest, HIV-1 antigén;hiv-12;0
hlab27;HLA B27;hlab27;0
holo;Holo-transzkobalamin*;holo;0
homoc;Homocisztein;homoc;0
hpva;HPV szűrés (Aptima)*;hpva;0
hpvag;HPV szűrés (Aptima + genotip.)*;hpvag;0
hpvgen;HPV szűrés (Aptima genotipizálás)*;hpvgen;0
hpvq;HPV szűrés (Quant-21 PCR)*;hpvq;0
HS;Húgysav;HS;0
hsv;Herpes simplex v.(HSV) 1,2 at.(IgG,IgM)*;hsv;0
hvit;H vitamin*;hvit;0
ia2at;IA-2 antitest (tirozinfoszfatáz elleni antitest)*;ia2at;0
Ialtal;Általános ételek - 108 étel*;Ialtal;0
Iegzot;Egzotikus ételek - 45 étel*;Iegzot;0
ifakt;Intrinzik faktor elleni antitest*;ifakt;0
ife;Fehérje azonosítása immunfixációval*;ife;0
Ifusze;Fűszerek - 31 étel*;Ifusze;0
IgA;IgA;IgA;0
IGF1;Inzulinszerű növekedési faktor-1 (IGF-1);IGF1;0
IgG;IgG;IgG;0
iggalo;IgG alosztályok*;iggalo;0
IgM;IgM;IgM;0
Igyum;Gyümölcsök - 36 étel*;Igyum;0
ileu6;Interleukin-6 (IL-6)*;ileu6;0
inha;Inhalatív allergiapanel (állatok);inha;0
inhiB;Inhibin B*;inhiB;0
inhpo;Inhalatív allergiapanel (pollenek);inhpo;0
inhpp;Inhalatív allergiapanel (penész és por);inhpp;0
inhtel;Inhalatív allergiapanel (teljes);inhtel;0
int108;Táplálékintolerancia (IgG) panel (108)*;int108;0
inz;Inzulin;inz;0
inzat;Inzulin elleni antitest*;inzat;0
Ipaleo;Paleo ételek-137 étel*;Ipaleo;0
Ipuff;Puffasztó ételek - 79 étel*;Ipuff;0
It0;Inzulin 0'(glükózterhelés előtt);It0;0
It120;Inzulin 120'(glükózterhelés után);It120;0
It150;Inzulin 150'(glükózterhelés után);It150;0
It180;Inzulin 180'(glükózterhelés után);It180;0
It30;Inzulin 30'(glükózterhelés után);It30;0
It60;Inzulin 60'(glükózterhelés után);It60;0
It90;Inzulin 90'(glükózterhelés után);It90;0
Itelj;Teljes panel - 287 étel*;Itelj;0
Ivegan;Vegán ételek - 124 étel*;Ivegan;0
Iveget;Vegetáriánus ételek - 143 étel*;Iveget;0
jod;Jód*;jod;0
K;Kálium (K);K;0
Kalcit;Kalcitonin;Kalcit;0
kalpr;Kalprotektin;kalpr;0
Karb;Karbamid;Karb;0
kargam;Anti-foszfolipid antitestek (kardiolipin és B2-GPI IgG, IgM)*;kargam;0
kenert;Kenet értékelése genitális mintából;kenert;0
Kol;Koleszterin;Kol;0
koq10;Koenzim Q10*;koq10;0
Korn1;Kortizol nyálból I.*;Korn1;0
Korn2;Kortizol nyálból II.*;Korn2;0
Korn3;Kortizol nyálból III.*;Korn3;0
Korn4;Kortizol nyálból IV.*;Korn4;0
Korn5;Kortizol nyálból V.*;Korn5;0
koromk;Körömkaparék sarjadzó és fonalas gombák tenyésztése;koromk;0
Kortiz;Kortizol (szérum);Kortiz;0
Krea;Kreatinin;Krea;0
kroma;Kromogranin A*;kroma;0
kvit;K vitamin*;kvit;0
lakint;Laktóz-intolerancia (LCT 13910 C/T)*;lakint;0
lakisz;Laktóz-intolerancia (szájnyálkahártya)*;lakisz;0
LDH;Laktát-dehidrogenáz (LDH);LDH;0
lept;Leptin*;lept;0
LH;Luteinizáló Hormon (LH);LH;0
Li;Lítium*;Li;0
Lip;Lipáz;Lip;0
lippro;Lipoprotein profil (LIPODENS)*;lippro;0
lpa;Lipoprotein (a)*;lpa;0
lupusz;Lupusz antikoaguláns*;lupus;0
m2pkpl;M2-PK (plazma)*;m2pkpl;0
m2pksz;M2-PK (széklet)*;m2pksz;0
ma1298;MTHFR gén A1298C mutáció*;ma1298;0
malma;Molekuláris alma  IgE*;malma;0
MAU;Vizelet mikroalbumin;MAU;0
mbrom;Molekuláris Bromelain (CCD) MUX F3 IgE*;mbrom;0
mc677t;MTHFR gén C677T mutáció*;mc677t;0
megjm;Megjegyzés mikrobiológiai vizsgálathoz;megjm;0
melat;Mellékvesekéreg elleni antitest*;melat;0
melato;Melatonin*;melato;0
mfmogy;Molekuláris földimogyoró IgE*;mfmogy;0
Mg;Magnézium (Mg);Mg;0
Mhuvmi;Hüvelyi mikrobiom vizsgálata*;Mhuvmi;0
mmogy;Molekuláris mogyoró IgE*;mmogy;0
mnyir;Molekuláris nyírfa IgE*;mnyir;0
molbuz;Molekuláris búza IgE*;molbuz;0
moldio;Molekuláris dió IgE*;moldio;0
morb;Morbilli (kanyaró) elleni at.(IgG)*;morb;0
moszi;Molekuláris őszibarack IgE*;moszi;0
mparl;Molekuláris parlagfű IgE*;mparl;0
MRSA;MRSA szűrővizsgálat;MRSA;0
mszoja;Molekuláris szójabab IgE*;mszoja;0
mtojfe;Molekuláris tojásfehérje IgE*;mtojfe;0
mttej;Molekuláris tehéntej IgE*;mttej;0
mychom;Mycoplasma hominis PCR*;mychom;0
mycpcr;Mycoplasma genitalium PCR*;mycgen;0
Na;Nátrium (Na);Na;0
neipcr;Neisseria gonorrhoeae PCR*;neipcr;0
neiss;Neisseria gonorrhoeae tenyésztés;neiss;0
nklym;NK lymphocyta funkció*;nklym;0
NSEf;Neuron specifikus enoláz (NSE);NSEf;0
nutrh;Nutritív allergiapanel (húsok);nutrh;0
nutri;Nutritív allergiapanel;nutri;0
nuttel;Nutritív allergiapanel - teljes;nuttel;0
Oest;Ösztradiol (E2);Oest;0
olom;Ólom*;olom;0
omegzs;Omega zsírsav profil*;omegzs;0
onkoat;Onkoneuronális antitestek*;onkoat;0
Osteo;Oszteokalcin;Osteo;0
ozmol;Szérum ozmolalitás*;ozmol;0
P;Foszfát (P);P;0
pankel;Pankreász specifikus elasztáz (szérum)*;pankel;0
panks;Pankreász specifikus elasztáz (széklet)*;panks;0
pavG;Parvovírus B19 elleni antitest (IgG)*;pavG;0
pavM;Parvovírus B19 elleni antitest (IgM)*;pavM;0
pbnp;NT-proBNP;pbnp;0
peteat;Petefészek szteroidtermelő sejtjei elleni antitest*;peteat;0
PPvc;Glükóz (étkezés után);PPvc;0
Prog;Progeszteron;Prog;0
prokal;Prokalcitonin*;prokal;0
Prol;Prolaktin;Prol;0
protat;Protrombin elleni antitest*;protat;0
protC;Protein C aktivitás*;protC;0
protmu;Protrombin gén (FII) ) G20210 mutáció*;protmu;0
protoz;Protozoonok kimutatása;protoz;0
protS;Protein S aktivitás*;protS;0
PSA;Prosztata specifikus antigén (PSA);PSA;0
pszat;Pankreász szigetsejt elleni AT*;pszat;0
PT;Protrombin (INR);PT;0
PTH;Parathormon (PTH);PTH;0
RE;Glükóz (reggeli előtt);RE;0
renin;Renin*;renin;0
Reta;Retikulocita;Reta;0
RF;Reuma faktor (RF);RF;0
rpr;RPR, TPHA (szifilisz szerológia);rpr;0
rt3;Reverz T3*;rt3;0
RU;Glükóz (reggeli után);RU;0
RUB;Rubeola vírus antitestek (IgG, IgM)*;RUB;0
rubve;Rubeola védettség (IgG)*;rubve;0
S100;S 100 protein;S100;0
saa;Szérum Amiloid A (SAA)*;saa;0
sargom;Sarjadzó gomba tenyésztése;sargom;0
scc;SCC*;scc;0
sflt;SFLT-PLGF arány*;sflt;0
SHBG;SHBG;SHBG;0
sperat;Spermium elleni antitest*;sperat;0
std2;STD 2-es panel (Treponema pallidum, HSV 1/2);std2;0
std3;STD 3-as panel (Trichomonas, Gardnerella, Atopobium);std3;0
std4;STD 4-es panel (Chlamydia trachomatis, Mycoplasma genitalium, Ureaplasma, Neisseria gonorrhoeae)*;std4;0
std5;STD 5-ös panel (Chlamydia trachomatis, Mycoplasma genitalium, Ureaplasma, Neisseria gonorrhoea, Tric;std5;0
stdge;STD genitális kórokozóinak PCR vizsgálata*;stdge;0
szcali;Calicivírus AG kimutatása;szcali;0
szclos;Clostridium difficile AG és toxin kimut.;szclos;0
szeat;Szövetspecifikus antitest panel*;szeat;0
szel;Szelén*;szel;0
szerot;Szerotonin*;szerot;0
szheli;Helicobacter pylori AG kimut.;szheli;0
szmikr;Bélrendszeri mikrobiom vizsgálata*;szmikr;0
szmunk;Széklet munkaalkalmassági vizsgálata;szmunk;0
szoltr;Szolubilis transzferrin receptor*;szoltr;0
szrota;Rotavírus-Adenovírus AG kimut.;szrota;0
szteny;Széklet tenyésztése;szteny;0
Szvér1;Széklet vér (immuno);Szvér1;0
Szvér2;Széklet vér 2 (immuno);Szvér2;0
Szvér3;Széklet vér 3 (immuno);Szvér3;0
Tbil;Totál bilirubin;Tbil;0
TDVit;D-vitamin (25-OH);TDVit;0
Tesz;Totál tesztoszteron;Tesz;0
Tg;Trigliceridek;Tg;0
th1th2;TH1-TH2 citokin dominancia*;th1th2;0
thrido;Trombin idő;thrido;0
tig221;Táplálékintolerancia (IgG) panel (220+)*;tig221;0
tnfa;TNF-alfa*;tnfa;0
TotIgE;Total IgE;TotIgE;0
toxavi;Toxoplasma gondii IgG aviditás;toxavi;0
toxcar;Toxocara elleni antitest*;toxcar;0
TOXO;Toxoplasma gondii elleni at. (IgG,IgM);TOXO;0
TP;Összfehérje;TP;0
TPA;Szöveti polipeptid antigén (TPA)*;TPA;0
TRAK;TSH receptor elleni AT (TRAK);TRAK;0
trepat;Treponema pallidum ellenanyag megerősítés*;trepat;0
trepcr;Treponema pallidum PCR*;trepcr;0
Trf;Transzferrin;Trf;0
TRG;Tireoglobulin;TRG;0
tripcr;Trichomonas vaginalis PCR*;tripcr;0
tripta;Triptáz*;tripta;0
tropcr;Trombózis mutáció panel*;tropcr;0
TSH;TSH;TSH;0
Tviz;Teljes vizelet (általános és üledék);Tviz;0
urepcr;Ureaplasma Complex PCR;urepcr;0
valp;Valproinsav*;valp;0
Vami;Vizelet amiláz;Vami;0
VCa;Vizelet kálcium;VCa;0
VCl;Vizelet klorid;VCl;0
VCSOP;Vércsoport és ellenanyagszűrés*;VCSOP;0
vcssur;Vércsoport és ellenanyagszűrés (sürgős)*;vcssur;0
VE;Glükóz (vacsora előtt);VE;0
vegyal;Vegyes allergiapanel;vegyal;0
veseko;Vesekő-analízis;veseko;0
VFeh;Vizelet fehérje;VFeh;0
VG;Vizelet glükóz;VG;0
VHs;Vizelet húgysav;VHs;0
VK;Vizelet kálium;VK;0
VK5;Vérkép;VK5;0
VKar;Vizelet karbamid;VKar;0
VKr;Vizelet kreatinin;VKr;0
vlegio;Legionella AG kimutatása;vlegio;0
VNa;Vizelet nátrium;VNa;0
VP;Vizelet foszfát;VP;0
vstrep;Strept.pneumoniae AG kimut.;vstrep;0
VU;Glükóz (vacsora után);VU;0
vzgm;Varicella-zoster vírus at.(IgM,IgG)*;vzgm;0
vzigg;Varicella-zoster vírus at.(IgG)*;vzigg;0
Wedta;Vörösvérsejt süllyedés;Wedta;0
wh1069;Wilson-kór - H1069Q mutáció*;wh1069;0
wilfak;von Willebrand faktor antigén (vWF Ag)*;wilfak;0
wilfma;von Willebrand faktor multimer analízis*;wilfma;0
ykrom;Y-kromoszóma mikrodeléció*;ykrom;0
Zn;Cink (Zn)*;Zn;0
Zneja;Cink (seminális plazma)*;Zneja;0
zonu;Zonulin*;zonu;0";
}