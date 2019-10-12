<?php

class BookingValidatePage extends CorePage {

    private $bookingService;

    public function __construct()
    {
        parent::__construct();
        $this->bookingService = new BookingService();
    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->displayFejlec();

        $id = intval($_GET["id"]);
        $rk = intval($_GET["rk"]);

        if ($row = sql_fetch_array(sql_query("SELECT h.cim AS helyszin,sz.megnev AS szurestipus,f.* FROM foglalasok f
            LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
            LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
            WHERE f.id=? and f.rkod=?", array($id, $rk)))) {

            sql_query("update foglalasok set aktiv=1 where id=?", array($row["id"]));
            sql_query("update foglalasok set aktiv=1 where parentid=? and parentid<>0", array($row["id"]));

            echo "<h2>{$webText["sikeresidopontreg"]}</h2>";
            echo "{$webText["kedves"]} {$row["nev"]}!<br>
            <br>
            {$webText["foglalassuccesstext"]}
            
            <a href='/'>{$webText["visszafooldal"]}</a>";

            $this->bookingService->sendToCegAndOrvos($id);
            $this->bookingService->sendToUser($id);
        } else {
            echo "Sajnáljuk!<br>
            Ez az időpont foglalás nem létezik, vagy időközben törölve lett.<br>
            <br>
            <a href='/'>Visszatérés a főoldalra</a>";
        }

    }
}


