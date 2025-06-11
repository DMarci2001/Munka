<?php

class PatientService {

    private $patientFields = ["cegid", "nev", "email", "telefon", "szuldatum", "szulhely", "anyjaneve", "neme", "taj", "irsz", "varos", "utca", "munkakor", "torzsszam", "jelszo", "validated","kilepett"];

    public function __construct() {

    }

    public function getPatinentById($patientId) {
        return sql_fetch_array(sql_query("select u.*, c.id as cegid, c.megnev as cegnev from felhasznalok u left join cegek c on c.id=u.cegid where u.id=?", [$patientId]));
    }

    public function getPatinentByTaj($taj) {
        return sql_fetch_array(sql_query("select u.*, c.id as cegid, c.megnev as cegnev from felhasznalok u left join cegek c on c.id=u.cegid where u.taj=? and trim(u.taj)<>''", [$taj]));
    }

    public function getPatientReservations($patientId) {
        return sql_query("SELECT t.`megnev` AS szurestipus,c.`megnev` AS cegnev,o.`nev` AS orvos,h.`cim` AS helyszin,f.*,b.naploszam,b.megj as beutalomegj FROM foglalasok f
            LEFT JOIN szurestipusok t ON t.`id`=f.`szurestipusid`
            LEFT JOIN orvosok o ON o.id=f.`orvosassigned`
            left join beutalok b on b.foglalasid=f.id
            LEFT JOIN cegek c ON c.id=f.`cegid`
            LEFT JOIN helyszinek h ON h.`id`=f.`helyszinid`
            WHERE f.paciensid=? ORDER BY f.datum DESC", [$patientId])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePatient($patientData, $id) {
        $fields = "";
        $params = [];
        foreach ($this->patientFields as $patientField) {
            if (isset($patientData[$patientField])) {
                if ($patientField == "jelszo") {
                    if ($patientData[$patientField] == "") {
                        continue;
                    }
                    $patientData[$patientField] = md5($patientData[$patientField]);
                }
                $fields .= "{$patientField}=?,";
                $params[] = $patientData[$patientField];
            }
        }
        if(!isset($patientData["kilepett"])){
            $fields.="kilepett=?,";
            $params[] = null;
        }

        if (!empty($fields)) {
            $fields = substr($fields, 0, -1);
            $params[] = $id;
            sql_query("update felhasznalok set {$fields} where id=?", $params);
        }
    }


    public function insertPatient($patientData):int {
        $id = 0;
        $fields = "";
        $params = [];
        foreach ($this->patientFields as $patientField) {
            if (isset($patientData[$patientField])) {
                if ($patientField == "jelszo") {
                    if ($patientData[$patientField] == "") {
                        continue;
                    }
                    $patientData[$patientField] = md5($patientData[$patientField]);
                }
                $fields .= "{$patientField}=?,";
                $params[] = $patientData[$patientField];
            }
        }

        if (!empty($fields)) {
            $fields.= "regtime=now(), rkod=?";
            $params[] = rand(11000, 98000);

            sql_query("insert into felhasznalok set {$fields}", $params);
            $id = sql_insert_id();
        }

        return $id;
    }


}