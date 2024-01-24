<?php

class BeosztasService {
    private AdminUser $adminUser;
    public array $userCompanyPermission = [];
    public string $beosztasCompanyFilter = "";
    public string $beosztasDoctorFilter = "";

    public function __construct() {
        if (isset($GLOBALS["admin"])) {
            if (empty($this->adminUser)) {
                $this->adminUser = new AdminUser();

                if (!$this->adminUser->allCegJog()) {
                    $this->userCompanyPermission = $this->adminUser->getCegListArray();
                    $this->beosztasCompanyFilter = $this->beosztasCegFilterSQL($this->userCompanyPermission);
                }

                if ($this->adminUser->onlyDoctorReservations()) {
                    $this->beosztasDoctorFilter = " and b.orvosid in (".$this->adminUser->getUserDoctorIds().")";
                }
            }
        }
    }

    public function getBookingPageBeosztasok($day, $helyszinId) {
        $wd = date("N", strtotime($day));

        $beoRes = sql_query("SELECT ROUND(SUBSTRING(tipusok, 2)) AS primarytype, t.megnev AS tipusnev, b.*, group_concat(b.tipusok separator '') as alltipus, min(b.tol) as mintol, max(b.ig) as maxig, MAX(b.potig) as maxpotig, o.nev as orvosnev, o.description as orvosdescription, o.pecsetszam, o.description, o.onlytel,o.extrabuttonrequired 
            FROM orvos_beosztas_new b 
            left join orvosok o on o.id=b.orvosid 
            LEFT JOIN szurestipusok t ON t.id = ROUND(SUBSTRING(tipusok, 2))
            WHERE b.helyszinid=:helyszinid AND (nap=:weekday OR (nap=10 AND beonap=:day)) and tol<>0 and ig<>0 
            AND (b.validfrom='0000-00-00' OR b.validfrom<=:day) AND (b.validto='0000-00-00' OR b.validto>=:day)
            AND (b.hetek=0 OR (WEEK(:day,3)%2=0 AND b.hetek=2) OR (WEEK(:day,3)%2=1 AND b.hetek=1)) and b.aktiv=1 AND t.megnev IS NOT NULL {$this->beosztasCompanyFilter}
            group by concat(b.orvosid,'_',b.tol,'_',b.ig) order by instr(o.nev, 'kiszállásos'), !instr(o.nev, 'szűrés 2023'), !instr(o.nev, 'Egészségnap'), !instr(b.tipusok,'|1|'), !instr(b.tipusok,'|34|'), t.megnev, o.sorrend, o.id, tol, nap", ["helyszinid" => $helyszinId, "weekday" => $wd, "day" => $day]);

        return $beoRes->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBookingPageBeosztasokForTime($time, $helyszinId, $tipusId) {
        $day = date("Y-m-d", strtotime($time));
        $ora = date("H:i", strtotime($time));

        $wora = "";
        if ($ora != "00:00") {
            $wora = "AND TIME(tol)<=TIME('{$ora}') AND TIME(IF(potig='', ig, potig))>TIME('{$ora}')";
        }

        $beoRes = sql_query("SELECT 
        IF(potig<>'' and TIME(:time)>=TIME(ig),1,0) as ispotig, 
        b.*, o.nev as orvosnev, o.onlytel 
        FROM orvos_beosztas_new b 
		LEFT JOIN orvosok o ON o.id = b.orvosid
		WHERE b.`helyszinid`=:helyszinid {$this->beosztasCompanyFilter} AND (nap=WEEKDAY(:day)+1 or beonap=:day) {$wora} AND INSTR(b.tipusok,:tipusid)
        AND (b.validfrom='0000-00-00' OR b.validfrom<=:day) AND (b.validto='0000-00-00' OR b. >=:day)
		AND (b.hetek=0 OR (WEEK(:day,3)%2=0 AND b.hetek=2) OR (WEEK(:day,3)%2=1 AND b.hetek=1)) and b.aktiv=1 and o.aktiv=1
        ORDER BY o.nev, o.onlytel", ["time" => $time, "day" => $day, "helyszinid" => $helyszinId, "tipusid" => "|{$tipusId}|"]);

        return $beoRes->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getTipusByHelyszin($helyszinId) {
        return sql_query("SELECT tipusok FROM orvos_beosztas_new b WHERE b.helyszinid=? {$this->beosztasCompanyFilter} {$this->beosztasDoctorFilter} and b.tol<>0 and b.ig<>0", [$helyszinId]);
    }

    public function getBeosztasDataForDoctor($orvosId, $day, $helyszinId, $szuresTipusId) {
        $weekDay = date("N", strtotime($day));
        return sql_fetch_array(sql_query("SELECT min(tol) as tol, max(ig) as ig, binterval, groupid, beocegek FROM orvos_beosztas_new WHERE orvosid=? AND helyszinid=? AND (nap=? OR (beonap=? and nap=10)) AND INSTR(tipusok, ?) AND aktiv=1", [$orvosId, $helyszinId, $weekDay, $day, "|{$szuresTipusId}|"]));
    }

    public function getReservationPlaces($cegId, $szuresTipusId = 0) {
        $utils = new Utils();
        $helyszinek = sql_query("SELECT h.*,".$utils->cimLangQuery()." FROM helyszinek h 
            LEFT JOIN orvos_beosztas_new b ON b.`helyszinid`=h.id 
            LEFT JOIN orvosok o on b.orvosid=o.id
            WHERE h.aktiv=1 AND o.aktiv=1 AND b.aktiv=1 AND b.nap<>0 AND (b.nap<>10 or b.beonap>=DATE(NOW())) AND b.`helyszinid` IS NOT NULL and (instr(b.beocegek, ?) or b.beocegek='') and (instr(b.tipusok, ?) or ? = 0) 
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

    public function getDoctorInfos($doctorId, $type = "company") {
        $companyIds = $typeIds = [0];
        $beos = sql_query("select * from orvos_beosztas_new where orvosid=?", [$doctorId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($beos as $beo) {
            $idk = array_filter(explode("|", $beo["beocegek"]));
            $companyIds = array_merge($companyIds, $idk);

            $idk = array_filter(explode("|", $beo["tipusok"]));
            $typeIds = array_merge($typeIds, $idk);
        }
        $companyIds = array_unique($companyIds);
        $typeIds = array_unique($typeIds);

        if ($type == "company") {
            return sql_query("select id, megnev from cegek where id in (" . implode(",", $companyIds) . ")")->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($type == "service") {
            return sql_query("select id, megnev from szurestipusok where id in (" . implode(",", $typeIds) . ")")->fetchAll(PDO::FETCH_ASSOC);
        }
        return false;
    }

    public function getPlaceCompanies($placeId, $tipusId = 0) {
        $companyIds = [0];
        $tipusFilter = "";
        if ($tipusId != 0) {
            $tipusFilter = "AND instr(b.tipusok, '|".intval($tipusId)."|')";
        }

        $cegFilter = "";
        if (!$this->adminUser->allCegJog()) {
            $cegFilter = "and id in (" . $this->adminUser->getCegList() . ")";
        }

        if (in_array($placeId, Booking_Constants::DEFAULT_PLACE_IDS)) {
            return sql_query("select id, megnev from cegek where true {$cegFilter} order by megnev")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $beos = sql_query("select * from orvos_beosztas_new b where b.helyszinid=? {$tipusFilter}", [$placeId])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($beos as $beo) {
                $idk = array_filter(explode("|", $beo["beocegek"]));
                $companyIds = array_merge($companyIds, $idk);
            }
            $companyIds = array_unique($companyIds);

            return sql_query("select id, megnev from cegek where id in (" . implode(",", $companyIds) . ") {$cegFilter} order by megnev")->fetchAll(PDO::FETCH_ASSOC);
        }
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