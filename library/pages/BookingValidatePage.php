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

        echo $this->displayFejlec(Booking_Constants::FOOTER_COPYRIGHT." - ".$this->foglalasData["szurestipus"],true);

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
                    echo "<br/>A fizetési folyamat sikerült, megrendeléséről egy visszaigazoló emailt küldtünk.<br/>Felhívjuk figyelmét, hogy abban az esetben  ha panaszait nem tudjuk kezelni Web-Doktor szolgáltatásunkon keresztül, úgy a teljes összeg visszautalásra kerül.<br/><br/>";
                    echo "<hr>Sikeres tranzakció.<br/>SimplePay tranzakció azonosító: {$transactionData["transid"]}<hr>";
                }

                echo "<br/><br/><a href='/'>{$webText["visszafooldal"]}</a>";
            } else {
                echo "<h2>{$webText["sikeresidopontreg"]}</h2>";
                echo "{$webText["kedves"]} ".$this->foglalasData["nev"]."!<br>
                <br>
                {$webText["foglalassuccesstext"]}
                
                <a href='/'>{$webText["visszafooldal"]}</a>";

                $bookingService->notificationService->sendToCegAndOrvos($this->id);
                $bookingService->notificationService->sendUserReservationNotification($this->id);
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


