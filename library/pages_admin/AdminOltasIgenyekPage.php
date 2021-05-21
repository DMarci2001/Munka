<?php

use PHPMailer\PHPMailer\PHPMailer;

class AdminOltasIgenyekPage extends AdminCorePage
{

    private $bookingService;
    private $vakcinak;
    private $eljottek = 0;

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
        "cksolutions" => [
            "id" => "oltasformck",
            "username" => "cksolutionoltas",
            "title" => "CK Solution",
        ]


    ];

    public $pageParam;

    public function __construct()
    {
        parent::__construct();
        $this->bookingService = new BookingService();

        if ($_SESSION["adminuser"]["jogosultsag"] > 1) {
            if (isset($_GET["setoceg"])) {
                $_SESSION["oceg"] = $_GET["setoceg"];
            }

            if (!isset($_SESSION["oceg"])) {
                $_SESSION["oceg"] = "mscoltas";
            }

            $GLOBALS["subdomain"] = $_SESSION["oceg"];
        }




        $this->pageParam = $this->pageParams[$GLOBALS["subdomain"]];

        $oltasPage = new OltasIgenyFelmeresPage();
        $this->vakcinak = $oltasPage->vakcinak;

        $this->prefix = $this->pageParam["id"];

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
            if ($data = sql_fetch_array(sql_query("select id from webservicelog where tipus=23 and keres=? and action='{$this->prefix}_eljott'", [$id]))) {
                sql_query("delete from webservicelog where id=?", [$data["id"]]);
            } else {
                sql_query("insert into webservicelog set tipus=23, datum=now(), keres=?, action='{$this->prefix}_eljott'", [$id]);
            }

            echo $this->personRow(sql_fetch_array(sql_query("select * from webservicelog where id=?", [$id])));
            die;
        }

        if (isset($_GET["deletemscrow"])) {
            sql_query("update webservicelog set action='{$this->prefix}_deleted' where id=? limit 1", [$_GET["deletemscrow"]]);
            header("location: index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}");
            die;
        }

        if (isset($_GET["sendmessage"])) {
            $igenyles = sql_query("SELECT * FROM webservicelog WHERE id=? AND ACTION='{$this->prefix}_new' order by datum desc", [$_GET["sendmessage"]])->fetch(PDO::FETCH_ASSOC);

            $data = json_decode($igenyles["keres"], JSON_OBJECT_AS_ARRAY);

            $szovegSMS = $szovegEmail = "";
            if ($this->prefix == "oltasform") {
                $idopont = "2021-05-08 09:30";

                $extraMessage = "";
                //$extraMessage.= " Kérjük 6:00-kor vegye fel a munkát. Az oltási időpontjára elengedik a termelésből. Helyettesítés biztosítva lesz. Az oltás után nem kell tovább folytatni a munkát.";

                $szovegSMS = "Kedves ügyfelünk, {$idopont} időpontban várjuk Önt a Magyar Suzuki oltóponton. Hungáriamed csapata.{$extraMessage}";
                $szovegEmail = "Kedves ügyfelünk!<br/><br/>{$idopont} időpontban várjuk Önt a Magyar Suzuki oltóponton.{$extraMessage}<br/><br/>Hungáriamed csapata";

                //$szovegEmail = "Kedves ügyfelünk!<br/><br/>Új időpont, {$idopont} időpontban várjuk Önt a Magyar Suzuki oltóponton.{$extraMessage}<br/><br/>Hungáriamed csapata";
            }

            //tegnapi 74ember
            //15 percenként 6 ember 1. secl, 2. samoo, 3. cksolution, 4. s1
            //07:00

            if (true || $this->prefix == "oltasformsamsung") {
                //15 percenként 4 ember  7:00

                $idopont = "2021-05-22 10:15";

                $szovegSMS = "Dear Client, we are waiting for your arrival at {$idopont} at our vaccination point located in the SECL office. Hungáriamed team";
                $szovegEmail = "Dear Client!<br/><br/>We are waiting for your arrival at {$idopont} at our vaccination point located in the SECL office.<br/><br/>Hungáriamed team";
            }

            if (true || $this->prefix == "oltasform") {
                if (substr($data["telefon"], 0, 1) == "+" || substr($data["telefon"], 0, 2) == "00") {
                    $this->utils->sendSMSRaw($data["telefon"], $szovegSMS);
                } else {
                    $this->utils->sendSMS($data["telefon"], $szovegSMS);
                }
            }

            $mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress($data["email"]);
            $mail->AddBCC("jns@jns.hu");
            $mail->CharSet = "UTF-8";
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);

            $mail->Subject = "Értesítés oltás időpontról";
            $mail->Body = $szovegEmail;

            $mail->Send();

            sql_query("insert into webservicelog set tipus=23, datum=now(), keres=?, action='{$this->prefix}_message', response=?", [intval($_GET["sendmessage"]), $szovegSMS]);

            header("location: index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}");
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

        if ($_SESSION["adminuser"]["jogosultsag"] > 1) {
            echo "<div style='margin-bottom:10px;'>";
            foreach ($this->pageParams as $key => $pageParam)
            echo "[<a href='index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}&setoceg={$key}'>{$pageParam["title"]}</a>] ";
            echo "</div>";
        }


        if (isset($_GET["subpage"]) && $_GET["subpage"] == "showall") {
            echo $this->showAllIgeny();
        }

        if (empty($_GET["subpage"])) {
            //if ($_SESSION["adminuser"]["jogosultsag"] > 1 || $_SESSION["adminuser"]["username"] == "hmmoltas") {
                echo "<div>[<a href='index.php?page={$_GET["page"]}&subpage=showall'>Összes regisztrált lista</a>]</div>";
            //}
            echo $this->showOltasIgenyekEljott();
        }
        echo "</div>";
    }


    private function showOltasIgenyekEljott() {
        $result = [];
        $html   = "";
        $oltasPage = new OltasIgenyFelmeresPage();
        $vakcinak = $oltasPage->vakcinak;

        $igenylesek = sql_query("SELECT * FROM webservicelog WHERE tipus=23 AND ACTION='{$this->prefix}_new' order by datum desc")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($igenylesek as $igenylesData) {
            $data = json_decode($igenylesData["keres"], JSON_OBJECT_AS_ARRAY);
            //$html.= "<pre>".print_r($data, true)."</pre>";

            $idopont = "";
            if ($message = sql_fetch_array(sql_query("select * from webservicelog where tipus=23 and action='{$this->prefix}_message' and keres=? limit 1", [$igenylesData["id"]]))) {
                if (substr($message["response"], 0, 4) == "Dear") {
                    $idopont = substr($message["response"], 48, 10);
                } else {
                    $idopont = substr($message["response"], 20, 10);
                }
            }


            $datum = $idopont;

            if (empty($datum)) {
                $datum = "Időpont nélkül";
            }

            if (!sql_fetch_array(sql_query("select * from webservicelog where tipus=23 and action='{$this->prefix}_eljott' and keres=? limit 1", [$igenylesData["id"]]))) {
                continue;
            }

            $csoport = "Mindenki";
            if (isset($data["csoport"])) {
                $csoport = $data["csoport"];
            }

            $van = 0;
            foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                if (isset($data["vakcina{$vakcinaId}"])) {
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



    private function showOltasIgenyek() {
        $result = [];
        $html   = "";
        $oltasPage = new OltasIgenyFelmeresPage();
        $vakcinak = $oltasPage->vakcinak;

        $igenylesek = sql_query("SELECT * FROM webservicelog WHERE tipus=23 AND ACTION='{$this->prefix}_new' order by datum desc")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($igenylesek as $igenylesData) {
            $data = json_decode($igenylesData["keres"], JSON_OBJECT_AS_ARRAY);
            //$html.= "<pre>".print_r($data, true)."</pre>";

            $datum = date("Y-m-d", strtotime($igenylesData["datum"]));
            $csoport = "Mindenki";
            if (isset($data["csoport"])) {
                $csoport = $data["csoport"];
            }

            $van = 0;
            foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                if (isset($data["vakcina{$vakcinaId}"])) {
                    @$result[$datum][$csoport][$vakcinaId]++;
                    $van = 1;
                }
            }
            if ($van == 0) {
                @$result[$datum][$csoport][-1]++;
            }



        }

        //$html.= "<pre>".print_r($result, true)."</pre>";


        foreach ($result as $datum => $datumData) {
            $html .= "<h2>{$datum}</h2>";

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
        $igen = "<span style='color:#a00;'>IGEN</span>";
        $nem = "<span style='color:#080;'>NEM</span>";

        $igenylesek = sql_query("SELECT * FROM webservicelog WHERE tipus=23 AND ACTION='{$this->prefix}_new' order by datum")->fetchAll(PDO::FETCH_ASSOC);

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
            $formData = json_decode($igenyData["keres"], JSON_OBJECT_AS_ARRAY);

            if (!isset($formData["csoport"])) {
                $formData["csoport"] = "all";
            }

            $idopont = "";
            if ($message = sql_fetch_array(sql_query("select * from webservicelog where tipus=23 and action='{$this->prefix}_message' and keres=? limit 1", [$igenyData["id"]]))) {
                if (substr($message["response"], 0, 4) == "Dear") {
                    $idopont = substr($message["response"], 48, 16);
                } else {
                    $idopont = substr($message["response"], 20, 16);
                }
            }

            if ($idopont != "" && isset($_GET["report2"])) {
                //continue;
            }

            if (isset($_GET["report2"]) && sql_fetch_array(sql_query("select * from webservicelog where tipus=23 and action='{$this->prefix}_eljott' and keres=? limit 1", [$igenyData["id"]]))) {
                //continue;
            }

            if ($formData["csoport"] != "a muszak") {
                //continue;
            }



            if (date("Y-m-d", strtotime($idopont)) == "2021-04-30") {
                //continue;
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

        $html.="<div style='margin:20px 0px 0px 5px;'>Eljöttek száma: {$this->eljottek} fő</div>";

        return $html;
    }


    private function personRow($igenyData):string {
        $formData = json_decode($igenyData["keres"], JSON_OBJECT_AS_ARRAY);
        if (!isset($formData["csoport"])) {
            $formData["csoport"] = "all";
        }

        if (!isset($formData["taj"])) {
            $formData["taj"] = "";
        }
        if (!isset($formData["lang"])) {
            $formData["lang"] = "hu";
        }
        if (!isset($formData["torzsszam"])) {
            $formData["torzsszam"] = "";
        }

        $selectedVakcina = [];
        foreach ($this->vakcinak as $vakcinaId => $vakcinaData) {
            if (isset($formData["vakcina{$vakcinaId}"])) {
                $selectedVakcina[] = $vakcinaData["name"];
            }
        }

        $idopont = "";
        if ($message = sql_fetch_array(sql_query("select * from webservicelog where tipus=23 and action='{$this->prefix}_message' and keres=? limit 1", [$igenyData["id"]]))) {
            if (substr($message["response"], 0, 4) == "Dear") {
                $idopont = substr($message["response"], 48, 16);
            } else {
                $idopont = substr($message["response"], 20, 16);
            }
        }

        $background = "";
        if (sql_fetch_array(sql_query("select * from webservicelog where tipus=23 and action='{$this->prefix}_eljott' and keres=? limit 1", [$igenyData["id"]]))) {
            $background = "background:#9f9;";
            $this->eljottek++;
        }

        if ($formData["csoport"] == "egyeb" && !empty($formData["csoporttext"])) {
            $formData["csoport"] = "<span style='font-style: italic;'>".substr(trim(strip_tags($formData["csoporttext"])), 0, 50)."</span>";
        }

        $html = "";

        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>[<a onclick='$(\"#valaszok{$igenyData["id"]}\").toggle();return false;' href='#'>Válaszok</a>] ";
        if ($_SESSION["adminuser"]["jogosultsag"] > 1) {
            $html.="[<a href='index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}&sendmessage={$igenyData["id"]}'>SMS</a>] ";
            $html.="[<a href='index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}&deletemscrow={$igenyData["id"]}' onclick='return confirm(\"Biztos törlöd ezt a sort?\");'>Törlés</a>] ";
        }
        $html.="[<a href='#' onclick='oltasEljottCheck({$igenyData["id"]});return false;'>Eljött</a>] ";
        $html.="</td>";
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["datum"]}</td>";
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["nev"]}</td>";
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["csoport"]}</td>";
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["szuldatum"]}</td>";
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["telefon"]}</td>";
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["email"]}</td>";
        if ($GLOBALS["subdomain"] == "mscoltas") {
            $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["taj"]}</td>";
            $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["torzsszam"]}</td>";
        }
        if ($GLOBALS["subdomain"] == "secl") {
            $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["utlevel"]}</td>";
            $html .= "<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["lang"]}</td>";
        }
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>".implode(", ", $selectedVakcina)."</td>";
        $html.="<td style='{$background}padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$idopont}</td>";

        return $html;
    }
}

//excel password: 111508114

//samsungoltas / Pah9shei svéd péntek szünet, csüt 1400 - 1600, fogleü 1-2 között kardió kivesz
