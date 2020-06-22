<?php

class BookingDeletePage extends CorePage {
    private $bookingService;

    public function __construct()
    {
        parent::__construct();

        $this->bookingService = new BookingService();

        if (!isset($_GET["id"]) || !isset($_GET["rk"])) {
            die("error");
        }

        if (isset($_GET["dodeletereservation"])) {
            $this->bookingService->deleteReservation($_GET["id"], $_GET["rk"]);
            header("location:index.php?page=bookingdeletesuccessful");
            die();
        }

    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->displayFejlec();

        $id=round($_GET["id"]);
        $rk=$_GET["rk"];

        if ($row = sql_fetch_array(sql_query("SELECT ".$this->utils->cimLangQuery("helyszin").",sz.megnev AS szurestipus,f.* FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=? and (f.rkod=? or f.pass=?)",array($id, $rk, $rk)))) {
            echo "{$webText["kedves"]} {$row["nev"]}!<br>
            <br>
            {$webText["torleskezd"]}:<br/>
            <br/>
            
            
            {$webText["nev"]}: {$row["nev"]}<br>
            {$webText["telefon"]}: {$row["telefon"]}<br>
            <b>{$webText["idopont"]}: ".substr($row["datum"],0,16)."</b><br>
            {$webText["szurestipus"]}: {$row["szurestipus"]}<br>
            {$webText["helyszin"]}: {$row["helyszin"]}<br>
            <br/>
            
            <a class='simabuttonpiros' href='index.php?page={$_GET["page"]}&id={$_GET["id"]}&rk={$_GET["rk"]}&dodeletereservation'>{$webText["torlesmegerositese"]}</a><br/>
            
            <br/>
            
            <a href='/'>{$webText["visszafooldal"]}</a>";
        } else {
            echo "Sajnáljuk!<br>
            Ez az időpont foglalás nem létezik, vagy időközben törölve lett.<br>
            <br>
            
            <a href='/'>{$webText["visszafooldal"]}</a>";
        }

    }
}

