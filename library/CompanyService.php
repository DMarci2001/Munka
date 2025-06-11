<?php

class CompanyService {

    const CIB_ID            = 6;
    const UNIQA_ID          = 200;
    const HUNGAROCONTROL_ID = 201;
    const WABERERS_ID       = 129;
    const BP_ID             = 74;
    const ASTOTEC_ID        = 664;
    const SUZUKI_ID         = 81;
    const SUZUKI_EGESZSEGUT_ID = 504;
    const SUZUKI_GHC_ID     = 904;
    const BME_ID            = 851;
    const KRE_ID            = 888;
    const LIGHTTECH_ID      = 858;

    public static array $makIds = [4,373, 374, 375, 376,933];

    public static array $suzukiManagerTorzsSzamok = ['123','17140','08020','06874','06840','03000','01029','05121','05798','00052','03945','00887','08632','17675','22225','02860','00187','11207','01444','06402','03168','19615','10355','01275','16951','20382','15812','06669','17796','19494','14432','08645','10039','03881','10195','07511','17820','00121','19286','00638','09140','00403','03468','08990','19268','08838','01350','13156','08992','04039','08717','17652','01244','01591','20229','17648','14528','00077','17978','00081','06855','07046','10867','00679','00695','19245','15866','00413','09672','16325','01004','03075','09553','03112','03925','10735','06503','00172','20192','18800','03811','03038','06912','10499','22572','06428','17682','02826','04843','01547','17152','14686','19656','03072','08312','17600','04700','12536','11229','06103','03012','00239','18430','19033','18196','03919','14865','11918','06699','03076','08943','09148','18844','15526','07876','05644','00383','15474','18702','04033','06478','20354','06990','00432','00232','09411','07483','10356','00406','17102','02149','03744','05203','15431','08285','19141','00651','19427','03826','00913','02347','18899','18691','00221','08253','03918','10448','04032','09161','08247','02823','06865','00362','19132','08314','00488','17621','00359','03082','01278','00186','09102','21231','07361','13527','00154','00225','19220','14009','12781','02867','03430','20881','06984','01318','00576','05487','00324','00761','17605','02023','20096','21967','19459','02256','05365','03364','19088','17677','10896','17593','03603','20278','21111','19360','02045','00128','07101','15363','03060','06641','08666','01358','08313','10884','00399','04449','22510','05105','17654','03144','10196','05050','09298','03078','05889','17117','10261','22443','00556','03834','00347','17645','00756','19428','15245','11392','09062','05220','16456','00476','05953','12932','02802','00809','17150','10923','05345','18275','15387','00339','21718','08587','05216','04744','19476','19159','07547','14431','10150','12034','13049','00497','03920','21346','08168','07911','00150','15021','05112','17166','21378','19404','17209','10620','15576','17651','00909','03510','04826','11528','06157','07510','08283','17167','02499','10309','17809','08277','06320','00395','05430','17258','00299'];
    public static array $suzukiManagerTajSzamok = ['123', '037590224','122205239','037682260','085118265','027709409','033298571','084102975','118668921','025755022','022531513','030476741','033720139','041972724','029591378','035256265','027455670','125273338','031624471','033856869','034794234','140965258','039759658','025099700','108361766','039572510','086632748','083998458','040546904','087861596','022587716','024809146','035935034','029991101','038660689','035907273','044097604','028088280','112234777','081560514','082077552','030610288','076132296','087120093','036324774','123024347','031910251','037993474','037111968','033511957','038580994','044331997','034336861','023826863','095088194','040603661','038083543','028892689','092442993','029562840','120886793','033148452','035622572','029001730','030018787','091413211','088882169','029740868','086673149','127490632','030887165','029629466','083277896','030915325','033559168','124974805','086788625','028309349','114674797','032241110','034468713','079286886','032647622','035023340','039786096','029488188','045109096','034487712','106269374','033594402','037327008','040567952','032609222','027968435','038495641','133910535','036454952','037160997','125276700','083003532','034390733','027439696','045315576','127597865','096001448','033185893','042206354','081599152','085985780','028175283','037251950','036424685','044475530','087893984','084766227','026431075','030040962','071951739','044854212','034532788','033178932','044993496','036798094','096001981','030193484','081747425','038487202','035595429','029956812','035588513','032865958','028970617','030660946','119200568','037558338','139423910','077432805','137012569','030718968','082501145','032694554','045102035','035439886','029635746','038343580','111435342','034574502','081962925','123506515','036907056','032098141','120887082','077691817','042854278','036759295','030617122','039497222','023042597','029628191','076618864','027155624','080434427','106520071','036344646','032054426','025946075','029152784','042838218','041542246','125733575','035932260','081944583','109420107','121205676','026113560','030419012','081857047','029042227','026468695','106962862','026858133','090596777','035352006','095202099','083868799','034235946','106124631','040529019','035348854','035708788','044472027','084099833','114604435','038258361','030803558','029530438','030258226','121367053','107115229','032263718','032345249','086059174','034084799','037453068','040185121','024532080','027512179','037892171','033259026','044183710','033257651','033004149','117767849','038667011','025301753','029395200','040113140','083914487','087479384','031471055','083951574','027019544','086442321','028350224','095122263','039502454','036453890','122122684','081716137','086697909','028407713','084065829','039276830','082466794','031545305','083900297','039867764','085196076','086981929','041539613','025216833','123565066','122929672','031353702','032632277','138947284','089356434','083178645','037688891','037649274','037689142','037072272','080363192','026948326','114375449','122316061','121637811','028847043','039152255','083930045','037629838','105793445','045313572','042559193','039714321','086642765','078996160','032333864','035207315','038163647','034364206','036493335','037923181','033805214','094040289','027337664','038854262','037533344','036198597','029413041','079881058','029484214','033853277','028287160','018128464'];

    public function __construct()
    {
        //cgi indítás esetén nem kell
        if (!substr_count(php_sapi_name(),"cgi")) {
            $this->_domainProcess();
        }
    }

    private function _domainProcess() {
        $d = $GLOBALS["subdomain"] = $this->_getSubDomain();

        //if ($d == "review") {
        //    $GLOBALS["ertekeles"] = 1;
        //    return;
        //}

        if ($d == "keltexmed" || $d == "bejelentkezesuj" || $d == "demo") {
            $d = "bejelentkezes";
        }
        if($d=="marciteszt"){
            $d="bejelentkezes";
        }
        if($d=="mak-fehervariut"){
            header("Location:https://mak-bercsenyiut.hungariamed.hu");
        }

        if (!$_SESSION["helyszindata"] = sql_fetch_array(sql_query("select * from cegek where (CONCAT(',',RTRIM(domain),',') LIKE CONCAT('%,',?,',%') or tesztdomain=?) and aktiv=1",array($d,$d)))) {

            if ($_SESSION["helyszindata"] = sql_fetch_array(sql_query("select * from cegek where (CONCAT(',',RTRIM(domain),',') LIKE CONCAT('%,',?,',%') or tesztdomain=?) and aktiv=1",array("bejelentkezes","bejelentkezes")))) {
                if ($d == "erkezes") {
                    $_GET["page"] = "covidform";
                    return;
                }

                if ($d == "suzukiform") {
                    if (!isset($GLOBALS["admin"])) {
                        $_GET["page"] = "suzukiform";
                    }
                    return;
                }

                if ($d == "mscoltas") {
                    if (!isset($GLOBALS["admin"])) {
                        $_GET["page"] = "oltasigenyfelmeres";
                    }
                    return;
                }

                if (in_array($d, ["secl", "samoo", "s-1", "testoltas", "sdi", "cksolution", "theductkft", "ekg", "janssen", "jkgroup", "sekwang", "gih", "daeha", "topengineering", "amsdesign20group", "uth", "irs", "hallimprecision", "ooksan", "shinsung", "hyojin"])) {
                    if (!isset($GLOBALS["admin"])) {
                        $_GET["page"] = "oltasjelentkezes";
                    }
                    return;
                }

                if ($d == "elsosegelyteszt") {
                    if (!isset($GLOBALS["admin"])) {
                        $_GET["page"] = "elsosegelyvizsga";
                    }
                    return;
                }

                if ($d == "review") {
                    if (!isset($GLOBALS["admin"])) {
                        $_GET["page"] = "review";
                    }
                    return;
                }
            }

            unset($_SESSION["helyszindata"]);
            die("Domain nem található.");
        }
    }

    private function _getSubDomain() {
        $domain="";
        if (isset($_SERVER["HTTP_HOST"])) {
            $domain = str_replace("www.","",$_SERVER["HTTP_HOST"]);
            $domain = substr($domain,0,strpos($domain,"."));
        }
        return $domain;
    }

    public static function isHungarocontrol():bool {
        return $_SESSION["helyszindata"]["domain"] == "hc";
    }

    public static function isUniqa():bool {
        return $_SESSION["helyszindata"]["domain"] == "uniqa";
    }

    public static function isCib($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "cib" || $companyId == self::CIB_ID;
    }

    public static function isWaberers($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "wszl" || $companyId == self::WABERERS_ID;
    }

    public static function isBP($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "bp" || $companyId == self::BP_ID;
    }

    public static function isFesztivalEgyeb($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "szigetegyeb";
    }

    public static function isMagyarAllamkincstar($companyId = 0):bool {
        return in_array($companyId, self::$makIds) || substr_count($_SESSION["helyszindata"]["domain"], "mak-");
    }

    public static function isAstostecCompany($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "astotec";
    }

    const AUCHAN_ID = 338;
    public static function isAuchan($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "auchan" || ($companyId == self::AUCHAN_ID && Booking_Constants::SQL_DB == "keltexmed");
    }

    const OIF_ID = 499;
    public static function isOIF($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "oif" || ($companyId == self::OIF_ID && Booking_Constants::SQL_DB == "hungariamed");
    }

    public static function isFogleu($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "fogleu" || ($companyId == self::OIF_ID && Booking_Constants::SQL_DB == "hungariamed");
    }

    const BudapestBrand_ID = 840; 
    public static function isBudapestBrand($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "budapestbrand" || ($companyId == self::BudapestBrand_ID && Booking_Constants::SQL_DB == "hungariamed");
    }

    public static function isKRE($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "kre" || ($companyId == self::KRE_ID && Booking_Constants::SQL_DB == "hungariamed");
    }

    public static function isBME($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "bme" || ($companyId == self::BME_ID && Booking_Constants::SQL_DB == "hungariamed");
    }

    public static function auchanSingleReservationPlaces():array {
        return [319, 321];
    }

    public static function isSuzuki($companyId = 0):bool {
        return ($companyId == self::SUZUKI_ID || $companyId == self::SUZUKI_EGESZSEGUT_ID) && Booking_Constants::SQL_DB == "hungariamed";
    }

    public static function isSuzukiTeszt($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "suzukiteszt" && Booking_Constants::SQL_DB == "hungariamed";
    }

    public static function isSuzukiMenedzser($companyId = 0):bool{
        return $_SESSION["helyszindata"]["domain"] == "suzuki-menedzserszures" && Booking_Constants::SQL_DB == "hungariamed";
    }

    public static function isSuzukiGHC($companyId = 0):bool{
        return $_SESSION["helyszindata"]["domain"] == "ghc" && Booking_Constants::SQL_DB == "hungariamed";
    }

    public static function isALDI($companyId = 0):bool{
        return $_SESSION["helyszindata"]["domain"] == "aldi" && Booking_Constants::SQL_DB == "hungariamed";
    }

    public static function isFGSZ($companyId = 0):bool {
        return $_SESSION["helyszindata"]["domain"] == "fgsz" && Booking_Constants::SQL_DB == "hungariamed";
    }

    public static function isFiFi($companyId = 0):bool{
        return $_SESSION["helyszindata"]["domain"] == "aldi-fifi" && Booking_Constants::SQL_DB == "hungariamed";
    }

    public static function isApollo($companyId = 0):bool{
        return $_SESSION["helyszindata"]["domain"] == "apollo" && Booking_Constants::SQL_DB == "hungariamed";
    }

    public static function isAszMenedzser($companyId = 0):bool{
        return $_SESSION["helyszindata"]["domain"] == "asz-menedzserszures" && Booking_Constants::SQL_DB == "hungariamed";
    }

    const FESZTIVAL_ALKALMASSAGI_DEFAULT_TEXT = "Időszakos

Munkakör: 

Panasz: nincs
Ismert krónikus betegség: nem ismert
Gyógyszerszedés: nincs

Allergia: nincs
Cave: nincs

 
Státusz:
Kp táplált, exanthema, oedema, icterus, cyanosis nincs, sclera fehér. Részarányos mellkas, puha sejtes alaplégzés. Tiszta, ritmusos, kellően ékelt szívhangok, zörej nem hallható. Has puha, betapintható, kóros rezisztencia nem tapintható, nyomásérzékenységet nem jelez, máj-lép nem tapintható. Mozgásszervek alakilag és funkcionálisan épek. Durva neurológiai eltérés nincs, pupillák o, =.
Tüdőszűrés: neg ()
V: 1.0 1.0 .    KV: Cs IV
";

    public static $fesztivalOnkentesQuestions = [
        1 => ["type" => "igennem", "required" => true, "question" => "Allergiája van?", "question_hu" => "Allergiája van?", "question_en" => "Do you have allergies?", "question_de" => "Allergiája van?"],
        2 => ["type" => "igennem", "required" => true, "question" => "Gyógyszerérzékenysége van?", "question_hu" => "Gyógyszerérzékenysége van?", "question_en" => "Do you have a drug sensitivity?", "question_de" => "Gyógyszerérzékenysége van?"],
        3 => ["type" => "igennem", "required" => true, "question" => "Szed rendszeresen gyógyszert?", "question_hu" => "Szed rendszeresen gyógyszert?", "question_en" => "Do you take medicine regularly?", "question_de" => "Szed rendszeresen gyógyszert?"],
        4 => ["type" => "igennem", "required" => true, "question" => "Kezelik valamilyen betegséggel?", "question_hu" => "Kezelik valamilyen betegséggel?", "question_en" => "Are you being treated for any disease?", "question_de" => "Kezelik valamilyen betegséggel?"],
    ];

    public static function fesztivalCompanyIds():array {
        return [138, 275, 261, 318, 322, 639, 285, 286, 647, 632, 635, 670];
    }

    public static function isFesztivalCompany($companyId = 0):bool {
        if ($companyId != 0) {
            return in_array($companyId, self::fesztivalCompanyIds()) && Booking_Constants::SQL_DB == "hungariamed";
        }
        return $_SESSION["helyszindata"]["domain"] == "annagora-gastro" || $_SESSION["helyszindata"]["domain"] == "raga2000" || $_SESSION["helyszindata"]["domain"] == "aquapark-balatonfured" || $_SESSION["helyszindata"]["domain"] == "aquaticdipo" || $_SESSION["helyszindata"]["domain"] == "crewnmore" || $_SESSION["helyszindata"]["domain"] == "festfree" || $_SESSION["helyszindata"]["domain"] == "monofactura" || $_SESSION["helyszindata"]["domain"] == "etalon" || $_SESSION["helyszindata"]["domain"] == "fesztivalonkentes" || $_SESSION["helyszindata"]["domain"] == "szigetideny" || $_SESSION["helyszindata"]["domain"] == "tranzorg" || $_SESSION["helyszindata"]["domain"] == "szigetegyeb" || $_SESSION["helyszindata"]["domain"] == "colorcrew";
    }

    public function fillMAKPaciensData($data) {
        $data["error"] = "";
        if (self::isMagyarAllamkincstar() && !empty(trim($data["taj"]))) {

            if ($paciensData = sql_query("select * from felhasznalok where taj=? and cegid in (".implode(",", self::$makIds).")", [$data["taj"]])->fetch(PDO::FETCH_ASSOC)) {
                //$paciensData["email"] = "jnsmobil@gmail.com";
                $data["paciensid"] = $paciensData["id"];
                $data["nev"] = $paciensData["nev"];
                $data["email"] = $paciensData["email"];
                $data["telefon"] = $paciensData["telefon"];
                $data["szuldatum"] = $paciensData["szuldatum"];
                $data["szulhely"] = $paciensData["szulhely"];
                $data["anyjaneve"] = $paciensData["anyjaneve"];
                $data["neme"] = $paciensData["neme"];
                $data["irsz"] = $paciensData["irsz"];
                $data["varos"] = $paciensData["varos"];
                $data["utca"] = $paciensData["utca"];
                $data["munkakor"] = $paciensData["munkakor"];
            } else {
                $data["error"] = "TAJ szám alapján nem található MAK ügyfél!";
            }

        }
        return $data;
    }

}