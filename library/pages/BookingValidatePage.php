<?php

class BookingValidatePage extends CorePage {

    private $id;
    private $rk;
    private $foglalasData;

    public function __construct()
    {
        parent::__construct();

        if (!isset($_GET["id"]) || !isset($_GET["rk"])) {
            die("error 488");
        }

        $simpleService = new SimplePayService();

        $this->id = intval($_GET["id"]);
        $this->rk = $_GET["rk"];
        $this->foglalasData = sql_fetch_array(sql_query("SELECT h.cim AS helyszin,sz.megnev AS szurestipus,f.* FROM foglalasok f
            LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
            LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
            WHERE f.id=? and f.rkod=?", array($this->id, $this->rk)));

        //ha simple fizetésre van jelölve, és még nem indult fizetés, akkor indítjuk
        if (isset($this->foglalasData["simplepay"]) && $this->foglalasData["simplepay"] == 1 && !$simpleService->getTransactionLog($this->id)) {
            $startPay = true;
        }

        //ha a fizetés újraindítására nyomott
        if (isset($_GET["pay"])) {
            if ($row = sql_fetch_array(sql_query("SELECT f.* FROM foglalasok f WHERE f.id=? and f.rkod=?", array($this->id, $this->rk)))) {
                $startPay = true;
            }
        }

        if (isset($startPay)) {
            $simpleService->startPay($this->id);
            die;
        }
    }

    public function showPage() {
        $webText = $this->lang->webText;
        $bookingService = new BookingService();
        $simpleService = new SimplePayService();

        echo $this->displayFejlec();

        if (!empty($this->foglalasData)) {
            sql_query("update foglalasok set aktiv=1 where id=?", array($this->foglalasData["id"]));
            sql_query("update foglalasok set aktiv=1 where parentid=? and parentid<>0", array($this->foglalasData["id"]));

            if ($transactionData = $simpleService->getTransactionLog($this->id)) {
                //fizetős foglalást

                if ($transactionData["result"] == "CANCEL") {
                    echo "<h2>A fizetési folyamatot megszakította</h2>";
                    echo "{$webText["kedves"]} ".$this->foglalasData["nev"]."!<br>";
                    echo "<br>Megrendelése még nem fejeződött be, mert a fizetési folyamatot megszakította. Ha újra meg akarja próbálni, kattintson a fizetés gombra.<br/><br/>";
                    echo "<a href='index.php?page={$_GET["page"]}&id={$_GET["id"]}&rk={$_GET["rk"]}&setslang={$_GET["setslang"]}&pay' class='newbutton' >Fizetés ({$this->foglalasData["totalprice"]} Ft)</a><br/><br/>";
                    echo $simpleService->simpleLogo();
                }

                if ($transactionData["result"] == "TIMEOUT") {
                    echo "<h2>A fizetési folyamat időtúllépés miatt megszakadt</h2>";
                    echo "{$webText["kedves"]} ".$this->foglalasData["nev"]."!<br>";
                    echo "<br>Megrendelése még nem fejeződött be, mert túllépte a tranzakció elindításának lehetséges maximális idejét. Ha újra meg akarja próbálni, kattintson a fizetés gombra.<br/><br/>";
                    echo "<a href='index.php?page={$_GET["page"]}&id={$_GET["id"]}&rk={$_GET["rk"]}&setslang={$_GET["setslang"]}&pay' class='newbutton' >Fizetés ({$this->foglalasData["totalprice"]} Ft)</a><br/><br/>";
                    echo $simpleService->simpleLogo();
                }

                if ($transactionData["result"] == "FAIL") {
                    echo "<h2>A fizetés nem sikerült</h2>";
                    echo "{$webText["kedves"]} ".$this->foglalasData["nev"]."!<br>";
                    echo "<br/>A fizetési folyamat sikertelenül zárult. Ha meg szeretné próbálni újra, kattintson a fizetés gombra.<br/><br/>";
                    echo "<a href='index.php?page={$_GET["page"]}&id={$_GET["id"]}&rk={$_GET["rk"]}&setslang={$_GET["setslang"]}&pay' class='newbutton' >Fizetés ({$this->foglalasData["totalprice"]} Ft)</a><br/><br/>";
                    echo $simpleService->simpleLogo();
                    echo "<hr>Sikertelen tranzakció.<br/>SimplePay tranzakció azonosító: {$transactionData["transid"]}<br/>Kérjük, ellenőrizze a tranzakció során megadott adatok helyességét.<br/>Amennyiben minden adatot helyesen adott meg, a visszautasítás okának kivizsgálása érdekében kérjük, szíveskedjen kapcsolatba lépni kártyakibocsátó bankjával.<hr>";
                }

                if (in_array($transactionData["result"], ["SUCCESS", "FINISHED"])) {
					$bookingService->notificationService->sendToCegAndOrvos($this->id);
                    echo "<h2>Sikeres megrendelés és fizetés</h2>";
                    echo "{$webText["kedves"]} ".$this->foglalasData["nev"]."!<br>";
                    echo "<br/>A fizetési folyamat sikerült, megrendeléséről egy visszaigazoló emailt küldtünk.<br/>Felhívjuk figyelmét, hogy abban az esetben ha panaszait nem tudjuk kezelni szolgáltatásunkon keresztül, úgy a teljes összeg visszautalásra kerül.<br/><br/>";
                    echo "<hr>Sikeres tranzakció.<br/>SimplePay tranzakció azonosító: {$transactionData["transid"]}<hr>";

                    if (isset($_SESSION["labcode"])) {
                        unset($_SESSION["labcode"]);
                    }
                }

                echo "<br/><br/><a href='/'>{$webText["visszafooldal"]}</a>";
            } else {
                $successText = $webText["foglalassuccesstext"];

                if(CompanyService::isSuzukiTeszt()){

                    $successText = "";

                    $successText.="Köszönjük, hogy a Hungária Med-M. szolgáltatását választotta.<br><br>";
                    $successText.="Ezúton tájékoztatjuk, hogy időpontfoglalása sikeresen megtörtént.<br></br>";
                    $successText.="<strong>Vizsgálat időpontja:</strong> ".date("Y.m.d H:i",strtotime($this->foglalasData["datum"]))."<br>";
                    $successText.="<strong>Választott szűrőcsomag:</strong> {$this->foglalasData["szurestipus"]}<br><br>";
                    $successText.="<strong>Vizsgálatok helyszíne:</strong> 1135 Budapest, Jász utca 33-35. Hungária Med-M Kft. rendelője<br>";
                    $successText.="<ul style=\"margin-left:10px\">";
                    $successText.="<li style=\"list-style: disc;\">Bejárat a Béke Patika épületének oldalán található</li>";
                    $successText.="<li style=\"list-style: disc;\">Parkolás a rendelő udvarában korlátozott számban lehetséges</li>";
                    $successText.="</ul>";

                    $successText.="<strong>Vizsgálatokkal kapcsolatos értesítések:</strong><br>";
                    $successText.="<ul style=\"margin-left:10px\">";

                    $successText.=" <li style=\"list-style: disc;\">Call-centeres munkatársunk a vizsgálat előtt 1 héttel és közvetlenül a vizsgálat előtt 1 munkanappal meg fogja Önt keresni egy közvetlen egyeztetés céljából a vizsgálatokkal kapcsolatban.</li>";
                    $successText.=" <li style=\"list-style: disc;\">A bejelentkezést követően a foglalásról egy megerősítő e-mailt küld a rendszer, mely tartalmazza a foglalással és a vizsgálatokkal kapcsolatos információkat.</li>";
                    $successText.=" <li style=\"list-style: disc;\">Tovább 24 órával a vizsgálat előtt egy SMS emlékeztetőt is küldünk Önnek.</li>";

                    $successText.="</ul>";
                }

                if (CompanyService::isAuchan()) {
                    $successText.= "<strong>Felhívjuk figyelmét, hogy amennyiben több szolgáltatást is választott, akkor a választott időpontjához legközelebbi szabad időpontokat állította össze Önnek a rendszer. A pontos időpontokat a visszaigazoló email-ben találja meg.</strong><br/><br/>";
                    $successText.= "Ha bármi kérdése van, a foglalt időpontját szeretné módosítani vagy lemondani, kérjük hívja ezt a telefonszámot: 06 30 537 1008";
                    $successText.= "<hr>";
                }


                if (CompanyService::isBP() && false) {
                    //Létrehozok egy új sort a pass értékkel a psyhosoc táblában.
                    if($fogl=sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=? AND rkod=?",array($_GET["id"],$_GET["rk"])))){
                        if(!$exists=sql_fetch_array(sql_query("SELECT * FROM psychosoc_eredmenyek WHERE pass=?",array($fogl["pass"])))){
                            //0-ára állítom a foglalást, hogy 1 óra múlva törlődjön, hogy ha em töltené ki a kérdőívet.
                            //sql_query("update foglalasok set aktiv=0 where id=?", array($this->foglalasData["id"]));
                            //Létre hozom a kérdőív adatsorát az adatbázisban:
                            //sql_query("INSERT INTO psychosoc_eredmenyek SET foglid=?,cegid=?,pass=?",array($fogl["id"],$fogl["cegid"],$fogl["pass"]));
                            //Átirányítom a kérdőív oldalára:
                        }
                        
                        $successText = $webText["foglalassuccesstextbp"];
                        $link = "https://{$_SERVER["HTTP_HOST"]}/?page=psychosocialform&pass={$fogl["pass"]}";
                        $successText = str_replace("#psyhosockerdoivlink#",$link,$successText);
                    }
                    
                }

                if ($this->foglalasData["fgroupid"] != 0) {
                    $successText = "A választott időpontjait sikeresen rögzítettük.<br/>
                    Amint kollégánk visszaigazolja az egyik időpontját, arról visszaigazoló email-t fogunk küldeni. Ennek átfutási ideje kb. 1-2 óra.<br/>
                    Kérdés esetén hívja ügyfélszolgáltunkat<br/>
                    <br/>
                    ";
                }
                echo "<h2>{$webText["sikeresidopontreg"]}</h2>";
                echo "{$webText["kedves"]} ".$this->foglalasData["nev"]."!<br>
                <br>
                {$successText}
                
                <a href='/'>{$webText["visszafooldal"]}</a>";

                $bookingService->notificationService->sendToCegAndOrvos($this->id);
                $bookingService->notificationService->sendUserReservationNotification($this->id,true);
            }

            //módosítás sync ha kell
            $api = new BookingSyncApi();
            $api->modifyReservation($this->foglalasData["id"]);
        } else {
            echo "Sajnáljuk!<br>
            Ez az időpont foglalás nem létezik, vagy időközben törölve lett.<br>
            <br>
            <a href='/'>Visszatérés a főoldalra</a>";
        }

    }


}


