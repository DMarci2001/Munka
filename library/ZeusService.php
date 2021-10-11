<?php

class ZeusService {
    const SQL_USER_ZEUS = "marcisql";
    const SQL_PASS_ZEUS = "kV8NRV+mw\\";
    const SQL_HOST_ZEUS = "localhost";
    const SQL_DB_ZEUS   = "zeus";

    const VIZSGALAT_FOGLEU_ID = 16;

    private $db;

    public function __construct()
    {
        $this->sql_connect_zeus();
    }

    private function sql_connect_zeus() {
        try {
            $this->db = new PDO("mysql:host=".self::SQL_HOST_ZEUS.";dbname=".self::SQL_DB_ZEUS.";charset=utf8", self::SQL_USER_ZEUS, self::SQL_PASS_ZEUS);
        } catch (PDOException $e) {
            print "Error: " . $e->getMessage();
            die();
        }
    }

    public function sql_query($q,$params=null) {
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $error = $stmt->errorInfo();
        if ($error[2] != "") {
            print_r($error);
        }
        return $stmt;
    }

    public function sql_fetch_array($stmt) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function sql_num_rows($stmt) {
        return $stmt->rowCount();
    }

    public function sql_insert_id() {
        return $this->db->lastInsertId();
    }

    public function getPaciensByMask($mask) {
        return $this->sql_fetch_array($this->sql_query("select * from paciensek where mask=?", array($mask)));
    }

    public function getVizsgalatiLapByPaciens($paciensId) {
        return $this->sql_fetch_array($this->sql_query("SELECT * FROM vizsgalatilapok WHERE vizsgalatid=? AND pid=?", array(self::VIZSGALAT_FOGLEU_ID, $paciensId)));
    }

    public function addLejaratiIdo($userId, $lejarat) {
        $this->sql_query("INSERT INTO vizsgalatilapok SET pid=?,kelte=NOW(),alk_kelte=NOW(),alk_visszarendeles=?,alk_tipus='predata',alk_statusz='alkalmas',vizsgalatid=?,publisher=1", array($userId, $lejarat, self::VIZSGALAT_FOGLEU_ID));
    }

    public function dailyStatQuery($date):array {
        $start = "{$date} 00:00:00";
        $end   = "{$date} 23:59:59";

        return $this->sql_query('SELECT  lelet.kelte AS "Vizsgalat/UtolsoModositasDatuma", p.nev AS "Paciens/Nev", 
            CASE WHEN v.megnev="Fogl. eü." THEN "Foglalkozás-egészségügyi alapellátás" ELSE v.megnev END AS "Szakrendelés", 
            doktor.fullname AS "Felhasználó", p.taj AS "Paciens/Azonosito", p.szuldatum AS "Paciens/SzuletesiDatum", 
            c.megnev AS "Egyedi/Telephely", 
            CASE WHEN p.munkakor="- Válassz! -" THEN "" ELSE p.munkakor END AS "Egyedi/Munkakör", lelet.korlatozas AS "Egyedi/Korlátozás", 
            lelet.alk_statusz AS "Egyedi/Alkalmasság", NULL AS "Számla", 1 AS "Vizsgálatok száma", 
            lelet.kelte AS "Vizsgálat ideje", NULL AS "Normaidő"
            FROM vizsgalatilapok lelet
            LEFT JOIN paciensek p ON p.id=lelet.pid
            LEFT JOIN vizsgalatok v ON v.id=lelet.vizsgalatid
            LEFT JOIN felhasznalok doktor ON doktor.id=lelet.publisher
            LEFT JOIN cegek c ON c.id=p.cegid
            WHERE p.cegid IN (42,99) AND lelet.kelte BETWEEN ? AND ?
            ORDER BY lelet.kelte ASC', [$start, $end])->fetchAll(PDO::FETCH_ASSOC);
    }

}