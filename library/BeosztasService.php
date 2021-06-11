<?php

class BeosztasService {
    private $utils;
    private $adminUser;
    private $wCeg = "";

    public function __construct() {
        //$this->utils = new Utils();
        if (isset($GLOBALS["admin"])) {
            if (empty($this->adminUser)) {
                $this->adminUser = new AdminUser();
                $this->wCeg = $this->adminUser->cegSQLFilter("b.cegid");
            }
        }
    }

    public function getBookingPageBeosztasok($day, $helyszinId, $szuresTipusId) {
        $wd = date("N", strtotime($day));

        $beoRes = sql_query("SELECT b.*, min(tol) as mintol, max(ig) as maxig, MAX(potig) as maxpotig, o.nev as orvosnev, o.description as orvosdescription, o.pecsetszam, o.description, o.onlytel, c.megnev as cegnev, group_concat(distinct c.megnev separator ',') as cegek FROM orvos_beosztas b 
            left join orvosok o on o.id=b.orvosid 
            left join cegek c on c.id=b.cegid
            WHERE b.helyszinid=? and INSTR(tipusok, ?) AND (nap=? OR (nap=10 AND beonap=?)) and tol<>0 and ig<>0 
            AND (b.hetek=0 OR (WEEK(?,3)%2=0 AND b.hetek=2) OR (WEEK(?,3)%2=1 AND b.hetek=1)) and b.aktiv=1 {$this->wCeg} 
            group by b.orvosid order by o.sorrend, o.nev,nap,tol", [$helyszinId, "|{$szuresTipusId}|", $wd, $day, $day, $day]);

        return $beoRes->fetchAll();
    }


    public function getTipusByHelyszin($helyszinId) {
        return sql_query("SELECT tipusok FROM orvos_beosztas b WHERE b.helyszinid=? {$this->wCeg} and b.tol<>0 and b.ig<>0", [$helyszinId]);
    }

    public function getCegListByHelyszin($helyszinId) {
        return sql_query("SELECT c.* FROM orvos_beosztas b 
        LEFT JOIN cegek c ON c.`id`=b.`cegid`
        WHERE helyszinid=? {$this->wCeg} GROUP BY c.megnev", [$helyszinId])->fetchAll();

    }

    public function getBeosztasDataForDoctor($orvosId, $day, $helyszinId, $szuresTipusId) {
        $weekDay = date("N", strtotime($day));
        return sql_fetch_array(sql_query("SELECT min(tol) as tol, max(ig) as ig, binterval FROM orvos_beosztas WHERE orvosid=? AND helyszinid=? AND (nap=? OR (beonap=? and nap=10)) AND INSTR(tipusok, ?) AND aktiv=1", [$orvosId, $helyszinId, $weekDay, $day, "|{$szuresTipusId}|"]));
    }


    public function getReservationPlaces($cegId, $szuresTipusId = 0) {
        $this->utils = new Utils();
        $helyszinek = sql_query("SELECT h.*,".$this->utils->cimLangQuery()." FROM helyszinek h 
            LEFT JOIN orvos_beosztas b ON b.`helyszinid`=h.id 
            LEFT JOIN orvosok o on b.orvosid=o.id
            WHERE h.aktiv=1 AND o.aktiv=1 AND b.aktiv=1 AND (b.nap<>10 or b.beonap>=DATE(NOW())) AND b.`helyszinid` IS NOT NULL and b.cegid=? and (instr(b.tipusok, ?) or ? = 0) 
            GROUP BY h.id ORDER BY cim", [$cegId, "|{$szuresTipusId}|", $szuresTipusId])->fetchAll();

        if ($cegId == 74) {
            $helyszinek[] = ["id" => 98989898989898, "cim" => "Budapest (1135) Jász utca 33-35. (Haller Gardens irodaház orvosi rendelése helyett)"];
        }

        return $helyszinek;
    }


    public function getDoctors($cegId, $helyszinId, $szuresTipusId) {
        $reso = sql_query("SELECT o.*,COUNT(*) FROM orvos_beosztas b
                LEFT JOIN orvosok o ON o.id = b.orvosid
                WHERE b.cegid=:cegId AND b.aktiv=1 AND b.helyszinid=:helyszinId AND INSTR(b.tipusok,:tipus)
                and (nap<10 OR b.beonap >= DATE(NOW())) and b.aktiv=1 and o.aktiv=1
                GROUP BY b.orvosid", array("cegId" => $cegId, "helyszinId" => $helyszinId, "tipus" => "|{$szuresTipusId}|"));

        return $reso->fetchAll();
    }

}