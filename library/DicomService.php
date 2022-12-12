<?php

class DicomService {

    private $dir = "/var/rtg";
    private $adminUser;

    public function __construct()
    {
        $this->adminUser = new AdminUser();

    }

    public function teszt() {
        $this->processEntries();
        //$this->rescanDicomEntries();
        //print_r($dicomEntries);
    }


    public function rescanDicomEntries() {
        $entries = sql_query("select * from dicom")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($entries as $entry) {

            $manufacturer = $manufacturerModelName = $institutionName = "";

            $xml = simplexml_load_string($entry["xml"]);

            //echo $xml;
            //die;
            foreach ($xml->children() as $child){
                if ($child->getName() == "data-set") {
                    foreach ($child->children() as $subchild) {
                        foreach ($subchild->attributes() as $attr) {
                            if ($attr == "Manufacturer") {
                                $manufacturer = $subchild;
                            }
                            if ($attr == "ManufacturerModelName") {
                                $manufacturerModelName = $subchild;
                            }
                            if ($attr == "InstitutionName") {
                                $institutionName = $subchild;
                            }
                        }
                    }
                }
            }


            sql_query("update dicom set manufacturer=?, manufacturerModelName=?, institutionName=? where id=?", [$manufacturer, $manufacturerModelName, $institutionName, $entry["id"]]);
        }
    }

    public function processEntries() {
        if (Booking_Constants::SQL_DB != "hungariamed") {
            return;
        }

        $dicomEntries = $this->readDir();

        foreach ($dicomEntries as $dicomEntry) {
            if (sql_fetch_array(sql_query("select filename from dicom where filename=?", [$dicomEntry]))) {
                continue;
            }

            $output = `dcm2xml +Ca latin-1 {$dicomEntry}`;
            $xml = simplexml_load_string($output);

            $patientName      = "";
            $patientID        = "";
            $patientBirthDate = "0000-00-00";
            $patientSex       = "";
            $patientOtherIDs  = "";
            $studyDescription = "";
            $contentTime      = "";
            $contentDate      = "";
            $manufacturer     = "";
            $manufacturerModelName = "";
            $institutionName  = "";

            foreach ($xml->children() as $child){
                if ($child->getName() == "data-set") {
                    foreach ($child->attributes() as $attr) {
                        //echo ' ' . $attr->getName() . ': ' . $attr . "\n";
                    }
                    foreach ($child->children() as $subchild) {
                        foreach ($subchild->attributes() as $attr) {
                            if ($attr == "PatientName") {
                                $patientName = str_replace("^", " ", $subchild);
                            }
                            if ($attr == "PatientID") {
                                $patientID = $subchild;
                            }
                            if ($attr == "PatientBirthDate") {
                                $patientBirthDate = $subchild;
                            }
                            if ($attr == "PatientSex") {
                                $patientSex = $subchild;
                            }
                            if ($attr == "OtherPatientIDs") {
                                $patientOtherIDs = $subchild;
                            }
                            if ($attr == "ContentDate") {
                                $contentDate = $subchild;
                            }
                            if ($attr == "ContentTime") {
                                $contentTime = $subchild;
                            }
                            if ($attr == "StudyDescription") {
                                $studyDescription = $subchild;
                            }
                            if ($attr == "Manufacturer") {
                                $manufacturer = $subchild;
                            }
                            if ($attr == "ManufacturerModelName") {
                                $manufacturerModelName = $subchild;
                            }
                            if ($attr == "InstitutionName") {
                                $institutionName = $subchild;
                            }
                        }
                    }
                }
            }

            $patientUniqueId= md5(trim($patientName).trim($patientBirthDate).trim($patientOtherIDs));

            if ($institutionName == "Az intézet neve") {
                $institutionName = "KeltexMed";
            }

            if ($manufacturer == "PROTEC") {
                $institutionName = "Hungáriamed-M Győr";
            }

            $contentDate = substr($contentDate,0,4)."-".substr($contentDate,4,2)."-".substr($contentDate,6,2)." ".substr($contentTime,0,2).":".substr($contentTime,2,2).":".substr($contentTime,4,2);

            echo "storing {$patientName}\n";

            sql_query("insert into dicom set contentDate=?, fileName=?, xml=?, patientName=?, patientID=?, patientBirthDate=?, patientSex=?, patientOtherIDs=?, studyDescription=?, manufacturer=?, manufacturerModelName=?, institutionName=?, uid=uuid(), token=CONCAT(MD5(CONCAT('paSS1', xml)),MD5(CONCAT('paSS2AndLast', xml)))",
                [$contentDate, $dicomEntry, utf8_encode($output), $patientName, $patientUniqueId, $patientBirthDate, $patientSex, $patientOtherIDs, $studyDescription, $manufacturer, $manufacturerModelName, $institutionName]);

        }

    }

    private function readDir() {
        $entries = [];
        $d = dir($this->dir);

        while (false !== ($entry = $d->read())) {
            if (substr($entry, 0, 2) == "CR" || substr($entry, 0, 2) == "DX") {
                $entries[] = $this->dir."/".$entry;
            }
        }
        return $entries;
    }

    public function getPatients($params = []) {
        $queryParams = [];
        $w = "";

        if (isset($params["search"]) && !empty($params["search"])) {
            $w .= " and instr(concat(patientName,patientBirthDate,patientOtherIDs), ?)";
            $queryParams[] = $params["search"];
        }

        if (isset($params["byuid"]) && !empty($params["byuid"])) {
            $w .= " and uid=?";
            $queryParams[] = $params["byuid"];
        }

        if (!empty($this->getSelectedCompany())) {
            $w .= " and d.institutionName=?";
            $queryParams[] = $this->getSelectedCompany();
        }

        if (!empty($this->getSelectedModel())) {
            $w .= " and d.manufacturer=?";
            $queryParams[] = $this->getSelectedModel();
        }

        return sql_query_common("select d.*, max(d.contentDate) as datum, count(*) as imageNum from dicom d where true {$w} group by d.patientID, d.patientBirthDate order by max(contentDate) desc limit 500", $queryParams)->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getImages($patientID) {
        return sql_query_common("select * from dicom where patientID=? order by contentDate desc limit 500", [$patientID])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDicomEntry($id) {
        if ($this->dicomPermission()) {
            return sql_query_common("select * from dicom where uid=?", [$id])->fetch(PDO::FETCH_ASSOC);
        } else {
            return ["patientName" => "403 nincs jogosultságod"];
        }
    }

    public function getRawDicomFile($id) {
        $content = ["fileName" =>"", "file" => ""];
        if ($data = sql_query_common("select fileName from dicom where uid=?", [$id])->fetch(PDO::FETCH_ASSOC)) {
            $content["fileName"] = basename($data["fileName"]);
            $content["file"] = file_get_contents($data["fileName"]);
        }

        return $content;
    }

    private function dicomPermission():bool {
        if ($this->adminUser->dicomAccess()) {
            return true;
        } else {
            return false;
        }
    }

    public function getRawImage($id) {
        $param = "";

        if (isset($_GET["normalize"])) {
            $param.= " -normalize";
        }
        if (isset($_GET["invert"])) {
            $param.= " -negate";
        }

        if ($content = sql_query_common("select * from dicom where uid=?", [$id])->fetch(PDO::FETCH_ASSOC)) {
            if (!$this->dicomPermission()) {
                $content["imageData"] = $this->notAvailableImage($param);
            } else {
                if (isset($_GET["thumb"])) {
                    $thumbLocation = "{$this->dir}/thumbnails/{$id}.jpg";
                    if (!is_file($thumbLocation)) {
                        `dcmj2pnm --write-png {$content["fileName"]} | convert - -resize 200 {$thumbLocation}`;
                    }
                    $content["imageData"] = imagecreatefromstring(file_get_contents($thumbLocation));
                } else {
                    if (!empty($param)) {
                        $content["imageData"] = imagecreatefromstring(`dcmj2pnm --write-png {$content["fileName"]} | convert - {$param} png:-`);
                    } else {
                        $content["imageData"] = imagecreatefromstring(`dcmj2pnm --write-png {$content["fileName"]}`);
                    }
                }
            }
        } else {
            $content["imageData"] = $this->notAvailableImage($param);
        }

        return $content;
    }

    private function notAvailableImage($param) {
        $num = rand(1,12);
        return imagecreatefromstring(`convert {$this->dir}/skeleton{$num}.png {$param} png:-`);
    }

    public function getCompanies() {
        return sql_query_common("select institutionName from dicom group by institutionName")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getModels() {
        return sql_query_common("select concat(manufacturerModelName,\" \",manufacturer) as name, manufacturer from dicom group by manufacturer")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSelectedCompany():string {
        if (isset($_COOKIE["dcegfilter"])) {
            return $_COOKIE["dcegfilter"];
        }
        return "";
    }

    public function getSelectedModel():string {
        if (isset($_COOKIE["deszkozfilter"])) {
            return $_COOKIE["deszkozfilter"];
        }
        return "";
    }

    public static function setSelectedCompany($company) {
        $exp = time() + 60 * 60 * 24 * 365;
        if ($company == "" || sql_query_common("select id from dicom where institutionName=? limit 1", [$company])->fetchAll(PDO::FETCH_ASSOC)) {
            setcookie("dcegfilter", $company, $exp, "/");
            $_COOKIE["dcegfilter"] = $company;
        }
    }

    public static function setSelectedModel($model) {
        $exp = time() + 60 * 60 * 24 * 365;
        if ($model == "" || sql_query_common("select id from dicom where manufacturer=? limit 1", [$model])->fetchAll(PDO::FETCH_ASSOC)) {
            setcookie("deszkozfilter", $model, $exp, "/");
            $_COOKIE["deszkozfilter"] = $model;
        }
    }

    public static function getInstitutesQuery():string {
        $institutionNames = "''";
        if (Booking_Constants::SQL_DB == "keltexmed") {
            $institutionNames = "'KeltexMed'";
        }
        if (Booking_Constants::SQL_DB == "hungariamed") {
            $institutionNames = "'Hungáriamed-M', 'Hungariamed-M'";
        }
        return $institutionNames;
    }

}

