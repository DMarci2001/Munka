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

        if (isset($_GET['type'])) {
            if ($_GET['type'] == "multiple") {
                $foglid = explode("XO", $_GET['id']);
                $rkod = explode("XE", $_GET['rk']);
            }

            foreach ($foglid as $key => $value) {
                $request = sql_query("SELECT h.cim AS helyszin,sz.megnev AS szurestipus,f.* FROM foglalasok f
									LEFT JOIN helyszinek h ON h.id=f.helyszinid
									LEFT JOIN szurestipusok sz ON sz.id=f.szurestipusid
									WHERE f.id = ? and f.rkod= ? ", array($foglid[$key], $rkod[$key]));

                if ($result = sql_fetch_array($request)) {
                    $lastId = $result['id'];
                    $nev = $result['nev'];
                    if ($result['szurestipusid'] == 6 || $result['szurestipusid'] == 34 || $result['szurestipusid'] == 35) {
                        $menedzserid = $result['id'];
                    }
                    sql_query("UPDATE foglalasok SET aktiv = 1 WHERE id = {$result["id"]} ");
                }
            }

            echo "<h2>{$webText["sikeresidopontreg"]}</h2>";

            if (isset($menedzserid)) {
                echo "Kedves {$nev}!<br>
                <br>
                Az időpont foglalása megerősítése sikeresen megtörtént!<br/>
                <br/>
                Ha törölni szeretné ezt a foglalását, kérjük kattintson a visszaigazoló levélben szereplő \"időpont regisztráció törlése\" linkre.<br/>
                Amennyiben módosítani szeretné a foglalását, abban az esetben elõször törölje a régi idõpontját, utána pedig regisztrálja újra.<br/>
                <br/>
                
                
                <a href='/'>Visszatérés a főoldalra</a>";

                sendToCegAndOrvos($menedzserid);
                sendToUser($menedzserid);
                die();
            }

            if (!isset($menedzserid) && isset($lastId)) {
                echo "Kedves {$nev}!<br>
                <br>
                Az időpont foglalása megerősítése sikeresen megtörtént!<br/>
                <br/>
                Ha törölni szeretné ezt a foglalását, kérjük kattintson a visszaigazoló levélben szereplő \"időpont regisztráció törlése\" linkre.<br/>
                Amennyiben módosítani szeretné a foglalását, abban az esetben elõször törölje a régi idõpontját, utána pedig regisztrálja újra.<br/>
                <br/>
                
                
                <a href='/'>Visszatérés a főoldalra</a>";

                sendToCegAndOrvos($lastId);
                sendToUser($lastId);
                die();
            }

            if (!isset($menedzserid) && !isset($lastId)) {
                echo "Sajnáljuk!<br>
                Ez az időpont foglalás nem létezik, vagy időközben törölve lett.<br>
                <br>
                
                <a href='/'>Visszatérés a főoldalra</a>";
                die();
            }

        }

        $id = intval($_GET["id"]);
        $rk = intval($_GET["rk"]);

        if ($row = sql_fetch_array(sql_query("SELECT h.cim AS helyszin,sz.megnev AS szurestipus,f.* FROM foglalasok f
            LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
            LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
            WHERE f.id=? and f.rkod=?", array($id, $rk)))) {

            sql_query("update foglalasok set aktiv=1 where id=?", array($row["id"]));

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


