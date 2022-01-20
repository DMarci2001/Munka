<?php

class BeosztasService {
    private $utils;
    private $adminUser;
    public $userCompanyPermission = [];
    public $beosztasCompanyFilter = "";

    public function __construct() {
        //$this->utils = new Utils();
        if (isset($GLOBALS["admin"])) {
            if (empty($this->adminUser)) {
                $this->adminUser = new AdminUser();

                if (!$this->adminUser->allCegJog()) {
                    $this->userCompanyPermission = $this->adminUser->getCegListArray();
                    $this->beosztasCompanyFilter = $this->beosztasCegFilterSQL($this->userCompanyPermission);
                }
            }
        }
    }

    public function getBookingPageBeosztasok($day, $helyszinId, $szuresTipusId) {
        $wd = date("N", strtotime($day));

        $beoRes = sql_query("SELECT b.*, min(tol) as mintol, max(ig) as maxig, MAX(potig) as maxpotig, o.nev as orvosnev, o.description as orvosdescription, o.pecsetszam, o.description, o.onlytel,o.extrabuttonrequired FROM orvos_beosztas_new b 
            left join orvosok o on o.id=b.orvosid 
            WHERE b.helyszinid=? and INSTR(tipusok, ?) AND (nap=? OR (nap=10 AND beonap=?)) and tol<>0 and ig<>0 
            AND (b.hetek=0 OR (WEEK(?,3)%2=0 AND b.hetek=2) OR (WEEK(?,3)%2=1 AND b.hetek=1)) and b.aktiv=1 {$this->beosztasCompanyFilter}
            group by concat(b.orvosid,'_',b.tol,'_',b.ig) order by o.sorrend, o.nev,nap,tol", [$helyszinId, "|{$szuresTipusId}|", $wd, $day, $day, $day]);

        return $beoRes->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTipusByHelyszin($helyszinId) {
        return sql_query("SELECT tipusok FROM orvos_beosztas_new b WHERE b.helyszinid=? {$this->beosztasCompanyFilter} and b.tol<>0 and b.ig<>0", [$helyszinId]);
    }

    public function getBeosztasDataForDoctor($orvosId, $day, $helyszinId, $szuresTipusId) {
        $weekDay = date("N", strtotime($day));
        return sql_fetch_array(sql_query("SELECT min(tol) as tol, max(ig) as ig, binterval, groupid, beocegek FROM orvos_beosztas_new WHERE orvosid=? AND helyszinid=? AND (nap=? OR (beonap=? and nap=10)) AND INSTR(tipusok, ?) AND aktiv=1", [$orvosId, $helyszinId, $weekDay, $day, "|{$szuresTipusId}|"]));
    }

    public function getReservationPlaces($cegId, $szuresTipusId = 0) {
        $this->utils = new Utils();
        $helyszinek = sql_query("SELECT h.*,".$this->utils->cimLangQuery()." FROM helyszinek h 
            LEFT JOIN orvos_beosztas_new b ON b.`helyszinid`=h.id 
            LEFT JOIN orvosok o on b.orvosid=o.id
            WHERE h.aktiv=1 AND o.aktiv=1 AND b.aktiv=1 AND (b.nap<>10 or b.beonap>=DATE(NOW())) AND b.`helyszinid` IS NOT NULL and (instr(b.beocegek, ?) or b.beocegek='') and (instr(b.tipusok, ?) or ? = 0) 
            GROUP BY h.id ORDER BY cim", ["|{$cegId}|", "|{$szuresTipusId}|", $szuresTipusId])->fetchAll();

        //if ($cegId == 74) {
        //    $helyszinek[] = ["id" => 98989898989898, "cim" => "Budapest (1135) Jász utca 33-35. (Haller Gardens irodaház orvosi rendelése helyett)"];
        //}

        return $helyszinek;
    }

    public function getDoctors($cegId, $helyszinId, $szuresTipusId) {
        $reso = sql_query("SELECT o.*,COUNT(*) FROM orvos_beosztas_new b
                LEFT JOIN orvosok o ON o.id = b.orvosid
                WHERE (instr(b.beocegek, :cegId) or b.beocegek='') AND b.aktiv=1 AND b.helyszinid=:helyszinId AND INSTR(b.tipusok,:tipus)
                and (nap<10 OR b.beonap >= DATE(NOW())) and b.aktiv=1 and o.aktiv=1
                GROUP BY b.orvosid", array("cegId" => "|{$cegId}|", "helyszinId" => $helyszinId, "tipus" => "|{$szuresTipusId}|"));

        return $reso->fetchAll();
    }

    public function getDoctorCompanies($doctorId) {
        $companyIds = [0];
        $beos = sql_query("select * from orvos_beosztas_new where orvosid=?", [$doctorId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($beos as $beo) {
            $idk = array_filter(explode("|", $beo["beocegek"]));
            $companyIds = array_merge($companyIds, $idk);
        }
        $companyIds = array_unique($companyIds);

        return sql_query("select id, megnev from cegek where id in (".implode(",", $companyIds).")")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlaceCompanies($placeId, $tipusId = 0) {
        $companyIds = [0];
        $tipusFilter = "";
        if ($tipusId != 0) {
            $tipusFilter = "AND instr(b.tipusok, '|".intval($tipusId)."|')";
        }

        $beos = sql_query("select * from orvos_beosztas_new b where b.helyszinid=? {$tipusFilter}", [$placeId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($beos as $beo) {
            $idk = array_filter(explode("|", $beo["beocegek"]));
            $companyIds = array_merge($companyIds, $idk);
        }
        $companyIds = array_unique($companyIds);

        return sql_query("select id, megnev from cegek where id in (".implode(",", $companyIds).") order by megnev")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function beosztasCegFilterSQL($companyIds):string {
        $filters = [];
        $filter = "";

        foreach ($companyIds as $companyId) {
            $filters[] = "instr(b.beocegek, '|{$companyId}|')";
        }

        if (!empty($filters)) {
            $filter = " AND (b.beocegek='' OR ".implode(" OR ", $filters).")";
        }

        return $filter;
    }

}