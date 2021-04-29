<?php

use PHPMailer\PHPMailer\PHPMailer;

class AdminOltasIgenyekPage extends AdminCorePage
{

    private $bookingService;

    public function __construct()
    {
        parent::__construct();
        $this->bookingService = new BookingService();


        if (isset($_GET["sendmessage"])) {
            $igenyles = sql_query("SELECT * FROM webservicelog WHERE id=? AND ACTION='oltasform_new' order by datum desc", [$_GET["sendmessage"]])->fetch(PDO::FETCH_ASSOC);

            $data = json_decode($igenyles["keres"], JSON_OBJECT_AS_ARRAY);

            //$data["email"] = "kuzdyg@gmail.com";
            //$data["telefon"] = "06306521732";

            $idopont = "2021-05-01 12:00";

            $szovegSMS = "Kedves ügyfelünk, {$idopont} időpontban várjuk Önt a Magyar Suzuki oltóponton. Hungáriamed csapata";
            $szovegEmail = "Kedves ügyfelünk!<br/><br/>{$idopont} időpontban várjuk Önt a Magyar Suzuki oltóponton.<br/><br/>Hungáriamed csapata";
            //$this->utils->sendSMS($data["telefon"], $szovegSMS);

            $mail = new PHPMailer();
            $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
            $mail->FromName = Booking_Constants::COMPANY_NAME;
            $mail->AddAddress($data["email"]);
            $mail->CharSet = "UTF-8";
            $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
            $mail->IsHTML(true);

            $mail->Subject = "Értesítés oltás időpontról";
            $mail->Body = $szovegEmail;

            //$mail->Send();

            sql_query("insert into webservicelog set tipus=23, datum=now(), keres=?, action='oltasform_message', response=?", [intval($_GET["sendmessage"]), $szovegSMS]);

            header("location: index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}");
            die;
        }
    }

    public function showPage() {
        echo "<div id='alkalmassaglista'>";

        if (isset($_GET["subpage"]) && $_GET["subpage"] == "showall") {
            echo $this->showAllIgeny();
        }

        if (!isset($_GET["subpage"])) {
            if ($_SESSION["adminuser"]["jogosultsag"] > 1) {
                echo "<div>[<a href='index.php?page={$_GET["page"]}&subpage=showall'>Összes regisztrált lista</a>]</div>";
            }
            echo $this->showOltasIgenyek();
        }
        echo "</div>";
    }

    private function showOltasIgenyek() {
        $result = [];
        $html   = "";
        $oltasPage = new OltasIgenyFelmeresPage();
        $vakcinak = $oltasPage->vakcinak;

        $igenylesek = sql_query("SELECT * FROM webservicelog WHERE tipus=23 AND ACTION='oltasform_new' order by datum desc")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($igenylesek as $igenylesData) {
            $data = json_decode($igenylesData["keres"], JSON_OBJECT_AS_ARRAY);
            //$html.= "<pre>".print_r($data, true)."</pre>";

            $datum = date("Y-m-d", strtotime($igenylesData["datum"]));
            $csoport = $data["csoport"];

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

        $igen = "<span style='color:#a00;'>IGEN</span>";
        $nem = "<span style='color:#080;'>NEM</span>";
        $oltasPage = new OltasIgenyFelmeresPage();
        $vakcinak = $oltasPage->vakcinak;

        $igenylesek = sql_query("SELECT * FROM webservicelog WHERE tipus=23 AND ACTION='oltasform_new' order by datum desc")->fetchAll(PDO::FETCH_ASSOC);

        $html.="<table cellpadding='0' cellspacing='0'>";
        $html.="<tr style='background:#ddd;font-weight: bold'>";
        $html.="<td style='padding:5px;'></td>";
        $html.="<td style='padding:5px;'>Dátum</td>";
        $html.="<td style='padding:5px;'>Név</td>";
        $html.="<td style='padding:5px;'>Csoport</td>";
        $html.="<td style='padding:5px;'>születési dátum</td>";
        $html.="<td style='padding:5px;'>Telefon</td>";
        $html.="<td style='padding:5px;'>Email</td>";
        $html.="<td style='padding:5px;'>Taj szám</td>";
        $html.="<td style='padding:5px;'>Törzsszám</td>";
        $html.="<td style='padding:5px;'>Választott vakcina</td>";
        $html.="<td style='padding:5px;'>Message</td>";
        $html.="</tr>";

        foreach ($igenylesek as $igenyData) {
            $formData = json_decode($igenyData["keres"], JSON_OBJECT_AS_ARRAY);

            $selectedVakcina = [];
            foreach ($vakcinak as $vakcinaId => $vakcinaData) {
                if (isset($formData["vakcina{$vakcinaId}"])) {
                    $selectedVakcina[] = $vakcinaData["name"];
                }
            }

            if (!isset($formData["taj"])) {
                $formData["taj"] = "";
            }

            if (!isset($formData["torzsszam"])) {
                $formData["torzsszam"] = "";
            }

            $messageText = "";
            if ($message = sql_fetch_array(sql_query("select * from webservicelog where tipus=23 and action='oltasform_message' and keres=? limit 1", [$igenyData["id"]]))) {
                $messageText = $message["response"];
            }

            $html.="<tr>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>[<a onclick='$(\"#valaszok{$igenyData["id"]}\").toggle();' href='#'>Válaszok</a>] [<a href='index.php?page={$_GET["page"]}&subpage={$_GET["subpage"]}&sendmessage={$igenyData["id"]}'>SMS</a>]</td>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$igenyData["datum"]}</td>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["nev"]}</td>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["csoport"]}</td>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["szuldatum"]}</td>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["telefon"]}</td>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["email"]}</td>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["taj"]}</td>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$formData["torzsszam"]}</td>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>".implode(", ", $selectedVakcina)."</td>";
            $html.="<td style='padding:5px 5px 5px 5px;border-top:1px solid #ccc;'>{$messageText}</td>";
            $html.="</tr>";

            $html.="<tr>";
            $html.="<td id='valaszok{$igenyData["id"]}' colspan='12' style='padding:5px 5px 5px 5px;display: none;'>";

            $html.="Van-e bármilyen allergiája (élelmiszer, gyógyszer, egyéb)? ".($formData["allergia"]==1?$igen:$nem)."<br/>";
            if (isset($formData["allergiatext"]) && $formData["allergiatext"] != "") {
                $html.="<span style='color:#888;text-decoration: underline;'>{$formData["allergiatext"]}</span><br/>";
            }
            $html.="Védőoltás beadását követően volt-e anafilaxiás reakciója? ".($formData["anafilaxia"]==1?$igen:$nem)."<br/>";
            if (isset($formData["anafilaxiatext"]) && $formData["anafilaxiatext"] != "") {
                $html.="<span style='color:#888;text-decoration: underline;font-weight: bold;'>{$formData["anafilaxiatext"]}</span><br/>";
            }
            $html.="Volt-e lázas beteg az elmúlt 2 hétben? ".($formData["lazas"]==1?$igen:$nem)."<br/>";
            $html.="Terhes? ".($formData["terhes"]==1?$igen:$nem)."<br/>";
            $html.="Van-e tartós, krónikus betegsége? (cukorbetegség, magas vérnyomás, asztma, szív-, vesebetegség stb.)? ".($formData["betegseg"]==1?$igen:$nem)."<br/>";
            $html.="Volt-e Önnek valaha véralvadási megbetegedése (mélyvénás-trombózis, tüdőembólia, szívinfarktus, STROKE (agyi infarktus)? ".($formData["veralvadas"]==1?$igen:$nem)."<br/>";
            $html.="Fogamzásgátlót szed-e? ".($formData["fogamzasgatlas"]==1?$igen:$nem)."<br/>";
            $html.="Kapott-e az elmúlt 4 hétben védő oltást? ".($formData["vedooltas"]==1?$igen:$nem)."<br/>";
            $html.="Regisztrált-e Ön oltásra a vakcinainfo.gov.hu oldalon? ".($formData["oltasregisztralt"]==1?$igen:$nem)."<br/>";
            $html.="Kapott-e már Covid védőoltást? ".($formData["oltasmegkapta"]==1?$igen:$nem)."<br/>";
            $html.="Átesett-e 3 hónapon belül PCR vizsgálattal igazolt Covid fertőzésen? ".($formData["atesett"]==1?$igen:$nem)."<br/>";

            $html.="</td>";
            $html.="</tr>";


        }
        $html.="</table>";

        return $html;
    }


}

