<?php

class ZeusService {
    const SQL_USER_ZEUS = "marcisql";
    const SQL_PASS_ZEUS = "kV8NRV+mw\\";
    const SQL_HOST_ZEUS = "localhost";
    const SQL_DB_ZEUS   = "zeus";

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
        return $this->sql_fetch_array($this->sql_query("SELECT * FROM vizsgalatilapok WHERE vizsgalatid=16 AND pid=?", array($paciensId)));
    }

    public function addLejaratiIdo($userId, $lejarat) {
        $this->sql_query("INSERT INTO vizsgalatilapok SET pid=?,kelte=NOW(),alk_kelte=NOW(),alk_visszarendeles=?,alk_tipus='predata',alk_statusz='alkalmas',vizsgalatid=16,publisher=1", array($userId, $lejarat));
    }
}