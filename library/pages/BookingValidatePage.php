<?php

class BookingValidatePage extends CorePage {

    public function __construct()
    {
        parent::__construct();

        if (isset($_GET["pay"])) {
            $id = intval($_GET["id"]);
            $rk = intval($_GET["rk"]);

            if ($row = sql_fetch_array(sql_query("SELECT f.* FROM foglalasok f WHERE f.id=? and f.rkod=?", array($id, $rk)))) {
                $simpleService = new SimplePayService();
                $simpleService->startPay($id);
                die;
            }
        }

    }

    public function showPage() {
        $webText = $this->lang->webText;
        $bookingService = new BookingService();
        $simpleService = new SimplePayService();

        echo $this->displayFejlec();

        $id = intval($_GET["id"]);
        $rk = intval($_GET["rk"]);

        if ($row = sql_fetch_array(sql_query("SELECT h.cim AS helyszin,sz.megnev AS szurestipus,f.* FROM foglalasok f
            LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
            LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
            WHERE f.id=? and f.rkod=?", array($id, $rk)))) {

            sql_query("update foglalasok set aktiv=1 where id=?", array($row["id"]));
            sql_query("update foglalasok set aktiv=1 where parentid=? and parentid<>0", array($row["id"]));

            if ($transactionData = $simpleService->getTransactionLog($id)) {
                //fizetős foglalást

                if ($transactionData["result"] == "CANCEL") {
                    echo "<h2>Megrendelése még nem fejeződött be</h2>";
                    echo "{$webText["kedves"]} {$row["nev"]}!<br>";
                    echo "<br>A fizetési folyamatot megszakította, ha újra meg akarja próbálni, kattintson a fizetés gombra.<br/><br/>";
                    echo "<a href='index.php?page={$_GET["page"]}&id={$_GET["id"]}&rk={$_GET["rk"]}&setslang={$_GET["setslang"]}&pay' class='newbutton' >Fizetés ({$transactionData["osszeg"]} Ft)</a><br/><br/>";
                    echo $simpleService->simpleLogo();
                }

                if ($transactionData["result"] == "FAIL") {
                    echo "<h2>A fizetés nem sikerült</h2>";
                    echo "{$webText["kedves"]} {$row["nev"]}!<br>";
                    echo "<br>A fizetési folyamat sikertelenül zárult. Ha meg szeretné próbálni újra, kattintson a fizetés gombra.<br/><br/>";
                    echo "<a href='index.php?page={$_GET["page"]}&id={$_GET["id"]}&rk={$_GET["rk"]}&setslang={$_GET["setslang"]}&pay' class='newbutton' >Fizetés ({$transactionData["osszeg"]} Ft)</a><br/><br/>";
                    echo $simpleService->simpleLogo();
                    echo "<hr>Sikertelen tranzakció.<br/>SimplePay tranzakció azonosító: {$transactionData["transid"]}<br/>Kérjük, ellenőrizze a tranzakció során megadott adatok helyességét.<hr>";
                }

                if ($transactionData["result"] == "SUCCESS") {
                    echo "<h2>Sikeres megrendelés és fizetés</h2>";
                    echo "{$webText["kedves"]} {$row["nev"]}!<br>";
                    echo "<br>A fizetési folyamat sikerült, megrendeléséről egy visszaigazoló emailt küldtünk.<br/>Felhívjuk a figyelmét, hogy ha a megrendelését nem tudjuk teljesíteni, a pénzt visszatérítjük.<br/><br/>";
                    echo "<hr>Sikeres tranzakció.<br/>SimplePay tranzakció azonosító: {$transactionData["transid"]}<hr>";
                }

                echo "<br/><br/><a href='/'>{$webText["visszafooldal"]}</a>";

                $bookingService->sendToCegAndOrvos($id);
                $bookingService->sendToUser($id);

            } else {
                echo "<h2>{$webText["sikeresidopontreg"]}</h2>";
                echo "{$webText["kedves"]} {$row["nev"]}!<br>
                <br>
                {$webText["foglalassuccesstext"]}
                
                <a href='/'>{$webText["visszafooldal"]}</a>";

                $bookingService->sendToCegAndOrvos($id);
                $bookingService->sendToUser($id);
            }
        } else {
            echo "Sajnáljuk!<br>
            Ez az időpont foglalás nem létezik, vagy időközben törölve lett.<br>
            <br>
            <a href='/'>Visszatérés a főoldalra</a>";
        }

    }


}


