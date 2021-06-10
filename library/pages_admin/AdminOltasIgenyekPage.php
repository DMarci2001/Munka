<?php

use PHPMailer\PHPMailer\PHPMailer;

class AdminOltasIgenyekPage extends AdminCorePage
{

    private $bookingService;
    private $vakcinak;
    private $eljottek = 0;
    private $allRegistered = 0;

    private $prefix = "";


    public $pageParams = [
        "mscoltas" => [
            "id" => "oltasform",
            "username" => "suzukioltas",
            "title" => "Suzuki",
        ],
        "secl" => [
            "id" => "oltasformsamsung",
            "username" => "samsungoltas",
            "title" => "Samsung",
        ],
        "samoo" => [
            "id" => "oltasformsamoo",
            "username" => "samoooltas",
            "title" => "Samoo",
        ],
        "s-1" => [
            "id" => "oltasforms1",
            "username" => "s1oltas",
            "title" => "S-1",
        ],
        "sdi" => [
            "id" => "oltasformsdi",
            "username" => "sdioltas",
            "title" => "Sdi",
        ],
        "cksolution" => [
            "id" => "oltasformck",
            "username" => "cksolutionoltas",
            "title" => "CK Solution",
        ],
        "theductkft" => [
            "id" => "oltasformduct",
            "username" => "theductoltas",
            "title" => "The Duct Kft.",
        ],
        "ekg" => [
            "id" => "oltasformekg",
            "username" => "ekgoltas",
            "title" => "EKG",
        ],
        "janssen" => [
            "id" => "oltasformjanssen",
            "username" => "janssenoltas",
            "title" => "Janssen",
        ],
        "jkgroup" => [
            "id" => "oltasformjkgroup",
            "username" => "jkgroupoltas",
            "title" => "JK Group Kft.",
        ],
        "sekwang" => [
            "id" => "oltasformsekwang",
            "username" => "sekwangoltas",
            "title" => "SekwangTotalPanel Kft.",
        ],
        "gih" => [
            "id" => "oltasformgih",
            "username" => "giholtas",
            "title" => "Green Industry Hungary Kft",
        ],
        "daeha" => [
            "id" => "oltasformdaeha",
            "username" => "daehaoltas",
            "title" => "Daeha Techwon Hungary Kft",
        ],
        "topengineering" => [
            "id" => "oltasformtec",
            "username" => "tecoltas",
            "title" => "TOP Engineering Co.,Ltd",
        ],
        "amsdesign20group" => [
            "id" => "oltasformamsdesign",
            "username" => "amsoltas",
            "title" => "AMS Design 20 Group Kft",
        ],
        "uth" => [
            "id" => "oltasformuth",
            "username" => "utholtas",
            "title" => "UNI TECHNOLOGY Hungary Kft.",
        ],
        "irs" => [
            "id" => "oltasformirs",
            "username" => "irsoltas",
            "title" => "IRS Construction EU Kft.",
        ]
    ];

    public $pageParam;

    public function __construct()
    {
        parent::__construct();
        $this->bookingService = new BookingService();

        if (isset($_GET["sendme"])) {

            /*
            +861086519978           Dear Client, we are waiting for your arrival at 2021-05-29 13:54 at our vaccination point located in the The Duct CSC office. Hungáriamed team
            +861063570443           Dear Client, we are waiting for your arrival at 2021-05-29 13:51 at our vaccination point located in the The Duct CSC office. Hungáriamed team
            +861031389214           Dear Client, we are waiting for your arrival at 2021-05-29 13:48 at our vaccination point located in the The Duct CSC office. Hungáriamed team
            +861038512996           Dear Client, we are waiting for your arrival at 2021-05-29 13:45 at our vaccination point located in the The Duct CSC office. Hungáriamed team
            +861075311600           Dear Client, we are waiting for your arrival at 2021-05-29 13:42 at our vaccination point located in the The Duct CSC office. Hungáriamed team
            +861047418484           Dear Client, we are waiting for your arrival at 2021-05-29 13:39 at our vaccination point located in the The Duct CSC office. Hungáriamed team
            +861038798403           Dear Client, we are waiting for your arrival at 2021-05-29 13:36 at our vaccination point located in the The Duct CSC office. Hungáriamed team
            +861067737737           Dear Client, we are waiting for your arrival at 2021-05-29 13:33 at our vaccination point located in the The Duct CSC office. Hungáriamed team
            +861062721968           Dear Client, we are waiting for your arrival at 2021-05-29 13:27 at our vaccination point located in the The Duct CSC office. Hungáriamed team
            +861080062051           Dear Client, we are waiting for your arrival at 2021-05-29 13:27 at our vaccination point located in the The Duct CSC office. Hungáriamed team
            */

            /*
            $this->utils->sendSMSRaw("+821086519978", "Dear Client, we are waiting for your arrival at 2021-05-29 13:54 at our vaccination point located in the The Duct CSC office. Hungáriamed team");
            $this->utils->sendSMSRaw("+821063570443", "Dear Client, we are waiting for your arrival at 2021-05-29 13:51 at our vaccination point located in the The Duct CSC office. Hungáriamed team");
            $this->utils->sendSMSRaw("+821031389214", "Dear Client, we are waiting for your arrival at 2021-05-29 13:48 at our vaccination point located in the The Duct CSC office. Hungáriamed team");
            $this->utils->sendSMSRaw("+821038512996", "Dear Client, we are waiting for your arrival at 2021-05-29 13:45 at our vaccination point located in the The Duct CSC office. Hungáriamed team");
            $this->utils->sendSMSRaw("+821075311600", "Dear Client, we are waiting for your arrival at 2021-05-29 13:42 at our vaccination point located in the The Duct CSC office. Hungáriamed team");
            $this->utils->sendSMSRaw("+821047418484", "Dear Client, we are waiting for your arrival at 2021-05-29 13:39 at our vaccination point located in the The Duct CSC office. Hungáriamed team");
            $this->utils->sendSMSRaw("+821038798403", "Dear Client, we are waiting for your arrival at 2021-05-29 13:36 at our vaccination point located in the The Duct CSC office. Hungáriamed team");
            $this->utils->sendSMSRaw("+821067737737", "Dear Client, we are waiting for your arrival at 2021-05-29 13:33 at our vaccination point located in the The Duct CSC office. Hungáriamed team");
            $this->utils->sendSMSRaw("+821062721968", "Dear Client, we are waiting for your arrival at 2021-05-29 13:27 at our vaccination point located in the The Duct CSC office. Hungáriamed team");
            $this->utils->sendSMSRaw("+821080062051", "Dear Client, we are waiting for your arrival at 2021-05-29 13:27 at our vaccination point located in the The Duct CSC office. Hungáriamed team");
            */
            die("sent");
        }

        if (isset($_SESSION["adminuser"]) && $_SESSION["adminuser"]["jogosultsag"] > 1) {
            if (isset($_GET["setoceg"])) {
                $_SESSION["oceg"] = $_GET["setoceg"];
            }

            if (!isset($_SESSION["oceg"])) {
                $_SESSION["oceg"] = "mscoltas";
            }

            $GLOBALS["subdomain"] = $_SESSION["oceg"];
        }


        if (isset($_GET["setkor"])) {
            $_SESSION["kor"] = $_GET["setkor"];
        }

        if (!isset($_SESSION["kor"])) {
            $_SESSION["kor"] = 1;
        }


        $this->pageParam = $this->pageParams[$GLOBALS["subdomain"]];

        $oltasPage = new OltasJelentkezesPage();
        $this->vakcinak = $oltasPage->vakcinak;

        $this->prefix = $this->pageParam["id"];

        if ($_SESSION["kor"] == 2) {
            $this->prefix.= "2";
        }

        if (isset($_SESSION["adminuser"]) && $_SESSION["adminuser"]["jogosultsag"] <= 1) {
            if ($_SESSION["adminuser"]["username"] != "hmmoltas") {
                if ($this->pageParam["username"] != $_SESSION["adminuser"]["username"]) {
                    die("error 9921");
                }
            }
        }


        if (!isset($_GET["subpage"])) {
            $_GET["subpage"] = "";
        }


        if (isset($_POST["oltaseljottcheck"])) {
            $id = $_POST["oltaseljottcheck"];

            sql_query("update oltasok set eljott = if(eljott=0,1,0) where id=?", [$id]);

            echo $this->personRow(sql_fetch_array(sql_query("select * from oltasok where id=?", [$id])));
            die;
        }

        if (isset($_GET["deletemscrow"])) {
            sql_query("update webservicelog set action='{$this->prefix}_deleted' where id=? limit 1", [$_GET["deletemscrow"]]);
            header("location: index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}");
            die;
        }

        if (isset($_POST["sendoltasmessage"])) {
            $igenyles = sql_query("SELECT * FROM oltasok WHERE id=? AND cegid='{$this->prefix}'", [$_POST["sendoltasmessage"]])->fetch(PDO::FETCH_ASSOC);

            //$igenyles["email"] = "jnsmobil@gmail.com";
            //$igenyles["telefon"] = "06209996183";

            $szovegSMS = $szovegEmail = "";
            if (true || $this->prefix == "oltasform") {
                $idopont = "2021-06-12 14:00";

                $extraMessage = "";
                //$extraMessage.= " Kérjük 6:00-kor vegye fel a munkát. Az oltási időpontjára elengedik a termelésből. Helyettesítés biztosítva lesz. Az oltás után nem kell tovább folytatni a munkát.";

                $szovegSMS = "Kedves ügyfelünk, {$idopont} időpontban várjuk Önt a Magyar Suzuki oltóponton. Hungáriamed csapata.{$extraMessage}";
                $szovegEmail = "Kedves ügyfelünk!<br/><br/>{$idopont} időpontban várjuk Önt a Magyar Suzuki oltóponton.{$extraMessage}<br/><br/>Hungáriamed csapata";

                //$szovegEmail = "Kedves ügyfelünk!<br/><br/>Új időpont, {$idopont} időpontban várjuk Önt a Magyar Suzuki oltóponton.{$extraMessage}<br/><br/>Hungáriamed csapata";
            }

            //08:00 10fő, 08:30 10 fő,
            //5 japán 9:00-ra, 5 magyar 09:15
            //09:30 10 ember, 10:00 15 ember 30 percenként

            //10 mindegynek is!
            //msc oltópont

            //if (!isset($_SESSION["smsidopont"])) {
                $_SESSION["smsidopont"] = "2021-06-05 08:00";
            //}

            //50 15 15 15

            if (false || $this->prefix == "oltasformsamsung") {
                //15 percenként 4 ember  7:00

                $idopont =  $_SESSION["smsidopont"];

                $szovegSMS = "Dear Client, we are waiting for your arrival at {$idopont} at our vaccination point located in the The Duct CSC office. Hungáriamed team";
                $szovegEmail = "Dear Client!<br/><br/>We are waiting for your arrival at {$idopont} at our vaccination point located in the The Duct CSC office.<br/><br/>Hungáriamed team";
            }

            if (true || $this->prefix == "oltasform") {
                if (substr($igenyles["telefon"], 0, 1) == "+" || substr($igenyles["telefon"], 0, 2) == "00") {
                    $this->utils->sendSMSRaw($igenyles["telefon"], $szovegSMS);
                } else {
                    $this->utils->sendSMS($igenyles["telefon"], $szovegSMS);
                }
            }

            $mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress($igenyles["email"]);
            $mail->AddBCC("jns@jns.hu");
            $mail->CharSet = "UTF-8";
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);

            $mail->Subject = "Értesítés oltás időpontról";
            $mail->Body = $szovegEmail;

            $mail->Send();

            sql_query("update oltasok set idopont=? where id=?", [$idopont, intval($_POST["sendoltasmessage"])]);

            $_SESSION["smsidopont"] = date("Y-m-d H:i", strtotime("{$_SESSION["smsidopont"]} + 3 minute"));

            echo $this->personRow(sql_fetch_array(sql_query("select * from oltasok where id=?", [$_POST["sendoltasmessage"]])));
            die;
        }


        if (isset($_GET["copyseclmoderna"])) {
            $source      = "oltasform_new";
            $destination = "oltasform2_new";

            $igenyek = sql_query("select * from webservicelog where action=? order by datum", [$source])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($igenyek as $igeny) {
                if (substr_count($igeny["keres"], "vakcina2") == 0) {
                    echo $igeny["keres"] . " ";

                    sql_query("insert into webservicelog set datum=?, tipus=23, action=?, exception=?, keres=?", [$igeny["datum"], $destination, $igeny["id"], $igeny["keres"]]);
                }
            }

            die;
        }



        if (isset($_GET["mailsuzuki"])) {
            $source = "oltasform2_new";

            $igenyek = sql_query("select * from webservicelog where action=? and ip<>'JP'", [$source])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($igenyek as $igeny) {
                $data = json_decode($igeny["keres"], JSON_OBJECT_AS_ARRAY);

                //if (substr_count($igeny["keres"], "vakcina2")) {
                    echo $data["email"] . " ";

                    //$data["email"] = "jns@jns.hu";

                    $mail = new PHPMailer();
                    $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
                    $mail->FromName = Booking_Constants::COMPANY_NAME;
                    $mail->AddAddress($data["email"]);
                    $mail->AddBCC("jns@jns.hu");
                    $mail->CharSet = "UTF-8";
                    $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
                    $mail->IsHTML(true);

                    $mail->Subject = "Másoldik oltás időpont";
                    $mail->Body = "Kedves Ügyfelünk!<br/><br/>Kérjük jelezze vissza melyik napon alkalmas Önnek, hogy megkapja a 2. oltását: <a href='https://mscoltas.hungariamed.hu/index.php?subpage=suzukiconfirmation&sid={$igeny["id"]}'>Kattintson ide</a><br/><br/>Hungária Med Csapata";

                    $mail->Send();
                    //die;
                //}
            }

            die("itt");
        }

        if (isset($_GET["mailseclmoderna"])) {
            $source = "oltasformsamsung2_new";

            $igenyek = sql_query("select * from webservicelog where action=?", [$source])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($igenyek as $igeny) {
                $data = json_decode($igeny["keres"], JSON_OBJECT_AS_ARRAY);

                if (substr_count($igeny["keres"], "vakcina2")) {
                    echo $data["email"] . " ";

                    //$data["email"] = "jns@jns.hu";

                    $mail = new PHPMailer();
                    $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
                    $mail->FromName = Booking_Constants::COMPANY_NAME;
                    $mail->AddAddress($data["email"]);
                    //$mail->AddBCC("jns@jns.hu");
                    $mail->CharSet = "UTF-8";
                    $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
                    $mail->IsHTML(true);

                    $mail->Subject = "Vaccination confirmation";
                    $mail->Body = "Dear Client!<br/><br/>At the attached link, please confirm the vaccination date: <a href='https://secl.hungariamed.hu/index.php?subpage=seclconfirmation&sid={$igeny["id"]}'>Click here</a><br/><br/>Hungariamed Team";

                    $mail->Send();
                    //die;
                }
            }

            die;
        }


        if (isset($_GET["reportmail"])) {
            $szovegEmail = "Regisztráltak, de nem jelentek meg:<br/>
<br/>
04-30 08:00<br/>
Balogh Miklós office worker 06704174128 mbalogh@suzuki.hu<br/>
Patrik Hajósi b muszak 317850876 hajpatrik@outlook.com<br/>
<br/>
04-30 10:00<br/>
Szirányi Viktória office worker 303207512 vsziranyi@suzuki.hu<br/>
Gyerák András a muszak 06308868005 andrasgyerak90@gmail.com<br/>
<br/>
04-30 11:00<br/>
Ronyecz Krisztina b muszak 06702152814 Krisztironyecz@gmail.com<br/>
Làzi Oszkàr b muszak 06703959047 Oszividampark@gmail.com<br/>
<br/>
04-30 12:00<br/>
Kovács ladislav b muszak +421911642238 lacikovacs775@gmail.com<br/>
Csapó Tamás office worker 305950425 csatomi@t-online.hu<br/>
<br/>
04-30 13:00<br/>
Priskin Lászlóné a muszak 308776604 andi4774@freemail.hu<br/>
Rostás Péter b muszak 06205010047 peterrostas870@gmail.com<br/>
Tóth Gábor a muszak 209893006 tothg087@gmail.com<br/>
<br/>
<br/>
05-01 08:00<br/>
Dávid János	a muszak 06704216559 iamdavidjanos@gmail.com<br/>
Horváth-Szeder Kata b muszak 06302541208 hszederkata@gmail.com<br/>
<br/>
05-01 09:00<br/>
Schwarz Erika F a muszak +36308574901 balazs20160816@gmail.com<br/>
Horváth István b muszak 06306510161 bogar33@citromail.hu <br/>
Tar Zoltán a muszak 0620 232 5701 zolisuzuki83@gmail.com<br/>
<br/>
05-01 10:00<br/>
Molnár Ferenc b muszak +36702083893 molnarf39@gmail.com<br/>
<br/>
05-01 11:00<br/>
Rajnoha Fridrich b muszak +421907163378 fregyo78@azet.sk<br/>
<br/>
05-01 12:00<br/>
Bertók János office worker +36209309933 jbertok2@gmail.com<br/>
Nyúl-Klein Kinga office worker 06-30-419-2829 kklein@suzuki.hu<br/>
Kiss László Imre a muszak 06709537313 papalaci6107@gmail.com<br/>
Víg László a muszak 06302967879 viglacikolyok72@gmail.com<br/>
<br/>
05-01 13:00<br/>
Pataki István Róbert office worker 06208522146 pataliirobi@gmail.com<br/>
Illés Dániel b muszak 06203418253 illesdani687@gmail.com<br/>
Török László a muszak 06303145372 laszlot342@gmail.com<br/>
Szenkovits Mónika a muszak +36706017502 szwnkomoncsi@gmail.com<br/>
Szabó Jenő b muszak +36706027091 Szabojeno720418@gmal.com<br/>

            ";

            $mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress("ateberi@suzuki.hu");
            $mail->AddAddress("mbalogh@suzuki.hu");
            $mail->AddAddress("kuzdyg@gmail.com");
            $mail->addBCC("jns@jns.hu");
            $mail->CharSet = "UTF-8";
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);
            $mail->Subject = "Suzuki oltóponton nem megjelentek";
            $mail->Body = $szovegEmail;
            $mail->Send();

            echo "mail sent<br>";
        }

    }

    public function showPage() {
        echo "<div id='alkalmassaglista'>";

        echo "<div style='margin-bottom:10px;'>";
        foreach ($this->pageParams as $key => $pageParam) {
            if ($_SESSION["adminuser"]["jogosultsag"] > 1 || $GLOBALS["subdomain"] == $key) {
                echo "[<a href='index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}&setoceg={$key}&setkor=1'>{$pageParam["title"]} 1. kör</a>] ";
                echo "[<a href='index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}&setoceg={$key}&setkor=2'>{$pageParam["title"]} 2. kör</a>] ";
            }
        }
        echo "</div>";


        if (isset($_GET["subpage"]) && $_GET["subpage"] == "showall") {
            echo $this->showAllIgeny();
        }

        if (empty($_GET["subpage"])) {
            //if ($_SESSION["adminuser"]["jogosultsag"] > 1 || $_SESSION["adminuser"]["username"] == "hmmoltas") {
                echo "<div>[<a href='index.php?page={$_GET["page"]}&subpage=showall'>Összes regisztrált lista</a>]</div>";
            //}
            echo $this->showOltasIgenyek();
            echo $this->showOltasIgenyekEljott();
        }
        echo "</div>";
    }


    private function showOltasIgenyekEljott() {
        $result = [];
        $html   = "";
        $oltasPage = new OltasJelentkezesPage();
        $vakcinak = $oltasPage->vakcinak;

        $igenylesek = sql_query("SELECT * FROM oltasok WHERE cegid='{$this->prefix}' order by regtime desc")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($igenylesek as $igenylesData) {
            $idopont = "";
            if (substr($igenylesData["idopont"], 0, 4) != "0000") {
                $idopont = substr($igenylesData["idopont"], 0, 10);
            }


            $datum = $idopont;

            if (empty($datum)) {
                $datum = "Időpont nélkül";
            }

            if ($igenylesData["eljott"] == 0) {
                continue;
            }

            $csoport = "Mindenki";
            if (isset($igenylesData["csoport"])) {
                $csoport = $igenylesData["csoport"];
            }

            $van = 0;
            foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                if ($igenylesData["vakcina"] == $vakcinaId) {
                    @$result[$datum][$csoport]++;
                    $van = 1;
                }
            }
            if ($van == 0) {
                @$result[$datum][$csoport]++;
            }

        }

        //$html.= "<pre>".print_r($result, true)."</pre>";


        foreach ($result as $datum => $datumData) {
            $html .= "<h2>{$datum} eljöttek száma</h2>";

            $html.="<div style='display:table-row;background:#ddd;'>";
            $html.="<div style='display:table-cell;padding:5px;'>Csoport</div>";
            $html.= "<div style='display:table-cell;padding:5px;'>&nbsp;&nbsp;&nbsp;Eljöttek</div>";
            $html.="</div>";

            $napiDb = 0;

            ksort($datumData);

            foreach ($datumData as $csoportId => $csoportData) {
                $html.="<div style='display:table-row;'>";
                $html.="<div style='display:table-cell;padding:5px 5px 5px 5px;border-bottom:1px solid #ccc;'>{$csoportId}</div>";

                $html.= "<div style='display:table-cell;padding:5px 5px 5px 5px;text-align: right;border-bottom:1px solid #ccc;'>";
                if (isset($csoportData)) {
                    $html.= $csoportData." fő";
                    @$napiDb += $csoportData;
                }

                $html.= "</div>";

                $html.="</div>";
            }
            $html.="<div style='display:table-row;font-weight: bold;'>";
            $html.="<div style='display:table-cell;padding:5px 5px 5px 5px;border-bottom:1px solid #ccc;'>Összesen</div>";

            $html.= "<div style='display:table-cell;padding:5px 5px 5px 5px;text-align: right;border-bottom:1px solid #ccc;'>".@intval($napiDb)." fő</div>";

            $html.="</div>";
        }



        return $html;
    }

    private function korFilter() {
        return $_SESSION["kor"] == 2 ? " and exception='2'":"and exception<>'2'";
    }

    private function showOltasIgenyek() {
        $result = [];
        $html   = "";
        $oltasPage = new OltasJelentkezesPage();
        $vakcinak = $oltasPage->vakcinak;

        $igenylesek = sql_query("SELECT * FROM oltasok WHERE cegid='{$this->prefix}' order by regtime desc")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($igenylesek as $igenylesData) {
            //$html.= "<pre>".print_r($data, true)."</pre>";

            $datum = date("Y-m-d", strtotime($igenylesData["regtime"]));
            $csoport = "Mindenki";
            if (isset($igenylesData["csoport"])) {
                $csoport = $igenylesData["csoport"];
            }

            $van = 0;
            foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                if ($igenylesData["vakcina"] == $vakcinaId) {
                    @$result[$datum][$csoport][$vakcinaId]++;
                    $van = 1;
                }
            }
            if ($van == 0) {
                @$result[$datum][$csoport][-1]++;
            }



        }


        //jun 5re amit japánok suzuki csak japánok
        //link a suzuki az alábbi 2 napra 5-re 12-e, vagy bármelyik 160-160
        //pfizer kihagyva

        //$html.= "<pre>".print_r($result, true)."</pre>";


        foreach ($result as $datum => $datumData) {
            $html .= "<h2>{$datum} regisztráltak</h2>";

            $html.="<div style='display:table-row;background:#ddd;'>";
            $html.="<div style='display:table-cell;padding:5px;'>Csoport</div>";
            foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                $html.= "<div style='display:table-cell;padding:5px;'>&nbsp;&nbsp;&nbsp;{$vakcinaData["name"]}</div>";
            }
            $html.= "<div style='display:table-cell;padding:5px;'>&nbsp;&nbsp;&nbsp;Egyik sem</div>";
            $html.="</div>";

            $napiDb = [];
            foreach ($datumData as $csoportId => $csoportData) {
                $html.="<div style='display:table-row;'>";
                $html.="<div style='display:table-cell;padding:5px 5px 5px 5px;border-bottom:1px solid #ccc;'>{$csoportId}</div>";

                foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                    $html.= "<div style='display:table-cell;padding:5px 5px 5px 5px;text-align: right;border-bottom:1px solid #ccc;'>";
                    if (isset($csoportData[$vakcinaId])) {
                        $html.= $csoportData[$vakcinaId]." db";
                        @$napiDb[$vakcinaId] += $csoportData[$vakcinaId];
                    }
                    $html.= "</div>";
                }
                $html.= "<div style='display:table-cell;padding:5px 5px 5px 5px;text-align: right;border-bottom:1px solid #ccc;'>";
                if (isset($csoportData[-1])) {
                    $html.= $csoportData[-1]." db";
                    @$napiDb[-1] += $csoportData[-1];
                }

                $html.= "</div>";

                $html.="</div>";
            }
            $html.="<div style='display:table-row;font-weight: bold;'>";
            $html.="<div style='display:table-cell;padding:5px 5px 5px 5px;border-bottom:1px solid #ccc;'>Összesen</div>";

            foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                $html.= "<div style='display:table-cell;padding:5px 5px 5px 5px;text-align: right;border-bottom:1px solid #ccc;'>".@intval($napiDb[$vakcinaId])." db</div>";
            }
            $html.= "<div style='display:table-cell;padding:5px 5px 5px 5px;text-align: right;border-bottom:1px solid #ccc;'>".@intval($napiDb[-1])." db</div>";

            $html.="</div>";
        }



        return $html;
    }


    private function showAllIgeny() {
        $html = "";

        $this->eljottek = 0;
        $this->allRegistered = 0;
        $igen = "<span style='color:#a00;'>IGEN</span>";
        $nem = "<span style='color:#080;'>NEM</span>";

        $igenylesek = sql_query("SELECT * FROM oltasok WHERE cegid='{$this->prefix}' order by regtime")->fetchAll(PDO::FETCH_ASSOC);

        if (isset($_GET["report2"])) {
            //$igenylesek = sql_query("SELECT * FROM webservicelog WHERE tipus=23 AND ACTION='{$this->prefix}_new' order by instr(keres,'egyeb'), instr(keres,'office worker'), instr(keres,'karbantarto'), instr(keres,'b muszak'), instr(keres,'a muszak'), datum")->fetchAll(PDO::FETCH_ASSOC);

        }

        $html.="<table cellpadding='0' cellspacing='0'>";
        $html.="<tr style='background:#ddd;font-weight: bold'>";
        $html.="<td style='padding:5px;'></td>";
        $html.="<td style='padding:5px;'>Beérkezett</td>";
        $html.="<td style='padding:5px;'>Név</td>";
        $html.="<td style='padding:5px;'>Csoport</td>";
        $html.="<td style='padding:5px;'>születési dátum</td>";
        $html.="<td style='padding:5px;'>Telefon</td>";
        $html.="<td style='padding:5px;'>Email</td>";
        if ($GLOBALS["subdomain"] == "mscoltas") {
            $html .= "<td style='padding:5px;'>Taj szám</td>";
            $html .= "<td style='padding:5px;'>Törzsszám</td>";
        }
        if ($GLOBALS["subdomain"] == "secl") {
            $html .= "<td style='padding:5px;'>Útlevél</td>";
            $html .= "<td style='padding:5px;'>Nyelv</td>";
        }
        $html.="<td style='padding:5px;'>Választott vakcina</td>";
        $html.="<td style='padding:5px;'>Kiküldött időpont</td>";
        $html.="</tr>";

        foreach ($igenylesek as $igenyData) {
            $formData = json_decode($igenyData["answers"], JSON_OBJECT_AS_ARRAY);

            if (!isset($formData["csoport"])) {
                $formData["csoport"] = "all";
            }

            $html.="<tr id='personrow{$igenyData["id"]}'>";
            $html.=$this->personRow($igenyData);
            $html.="</tr>";

            if (!isset($_GET["report2"])) {
                $html .= "<tr>";
                $html .= "<td id='valaszok{$igenyData["id"]}' colspan='12' style='padding:5px 5px 5px 5px;display: none;'>";

                $html .= "Van-e bármilyen allergiája (élelmiszer, gyógyszer, egyéb)? " . ($formData["allergia"] == 1 ? $igen : $nem) . "<br/>";
                if (isset($formData["allergiatext"]) && $formData["allergiatext"] != "") {
                    $html .= "<span style='color:#888;text-decoration: underline;'>{$formData["allergiatext"]}</span><br/>";
                }
                $html .= "Védőoltás beadását követően volt-e anafilaxiás reakciója? " . ($formData["anafilaxia"] == 1 ? $igen : $nem) . "<br/>";
                if (isset($formData["anafilaxiatext"]) && $formData["anafilaxiatext"] != "") {
                    $html .= "<span style='color:#888;text-decoration: underline;font-weight: bold;'>{$formData["anafilaxiatext"]}</span><br/>";
                }
                $html .= "Volt-e lázas beteg az elmúlt 2 hétben? " . ($formData["lazas"] == 1 ? $igen : $nem) . "<br/>";
                $html .= "Terhes? " . ($formData["terhes"] == 1 ? $igen : $nem) . "<br/>";
                $html .= "Van-e tartós, krónikus betegsége? (cukorbetegség, magas vérnyomás, asztma, szív-, vesebetegség stb.)? " . ($formData["betegseg"] == 1 ? $igen : $nem) . "<br/>";
                $html .= "Volt-e Önnek valaha véralvadási megbetegedése (mélyvénás-trombózis, tüdőembólia, szívinfarktus, STROKE (agyi infarktus)? " . ($formData["veralvadas"] == 1 ? $igen : $nem) . "<br/>";
                $html .= "Fogamzásgátlót szed-e? " . ($formData["fogamzasgatlas"] == 1 ? $igen : $nem) . "<br/>";
                $html .= "Kapott-e az elmúlt 4 hétben védő oltást? " . ($formData["vedooltas"] == 1 ? $igen : $nem) . "<br/>";
                $html .= "Regisztrált-e Ön oltásra a vakcinainfo.gov.hu oldalon? " . ($formData["oltasregisztralt"] == 1 ? $igen : $nem) . "<br/>";
                $html .= "Kapott-e már Covid védőoltást? " . ($formData["oltasmegkapta"] == 1 ? $igen : $nem) . "<br/>";
                $html .= "Átesett-e 3 hónapon belül PCR vizsgálattal igazolt Covid fertőzésen? " . ($formData["atesett"] == 1 ? $igen : $nem) . "<br/>";

                $html .= "</td>";
                $html .= "</tr>";
            }

        }
        $html.="</table>";

        $html.="<div style='margin:20px 0px 0px 5px;'>Összesen: {$this->allRegistered}, Eljöttek száma: {$this->eljottek} fő</div>";

        return $html;
    }


    private function personRow($igenyData):string
    {
        $this->allRegistered++;

        $idopont = substr($igenyData["idopont"], 0, 16);
        if (substr($idopont, 0, 4) == "0000") {
            $idopont = "";
        }

        $vakcina = "";
        if (!empty($igenyData["vakcina"])) {
            $vakcina = $this->vakcinak[$igenyData["vakcina"]]["name"];
        }

        $background = "";
        if ($igenyData["eljott"] == 1) {
            $background = "background:#9f9;";
            $this->eljottek++;
        }

        if ($igenyData["csoport"] == "egyeb" && !empty($igenyData["csoporttext"])) {
            $igenyData["csoport"] = "<span style='font-style: italic;'>" . substr(trim(strip_tags($igenyData["csoporttext"])), 0, 50) . "</span>";
        }


        if ($igenyData["poll1"] != "") {
            $idopont = "<span style='color:#f00;'>{$igenyData["poll1"]}</span> {$idopont}";
        }

        $html = "";

        $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>[<a onclick='$(\"#valaszok{$igenyData["id"]}\").toggle();return false;' href='#'>Válaszok</a>] ";
        if ($_SESSION["adminuser"]["jogosultsag"] > 1) {
            $html .= "[<a href='#' onclick='sendOltasMessage({$igenyData["id"]});return false;'>SMS</a>] ";
            $html .= "[<a href='index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}&deletemscrow={$igenyData["id"]}' onclick='return confirm(\"Biztos törlöd ezt a sort?\");'>Törlés</a>] ";
        }
        $html .= "[<a href='#' onclick='oltasEljottCheck({$igenyData["id"]});return false;'>Eljött</a>] ";
        $html .= "</td>";
        $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["regtime"]}</td>";
        $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["nev"]}</td>";
        if (isset($formData["utlevel"])) {
            $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["utlevel"]}</td>";
        } else {
            $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["csoport"]}</td>";
        }
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["szuldatum"]}</td>";
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["telefon"]}</td>";
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["email"]}</td>";
        if ($GLOBALS["subdomain"] == "mscoltas") {
            $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["taj"]}</td>";
            $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["utlevel"]}</td>";
        }
        if ($GLOBALS["subdomain"] == "secl") {
            $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["utlevel"]}</td>";
            $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["lang"]}</td>";
        }
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$vakcina}</td>";
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$idopont}</td>";

        return $html;
    }
}

//excel password: 111508114

//samsungoltas / Pah9shei svéd péntek szünet, csüt 1400 - 1600, fogleü 1-2 között kardió kivesz
