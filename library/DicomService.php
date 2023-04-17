<?php

class DicomService {
    const STORAGE_DIR = "/var/rtg";
    const WORKLIST_DIR = "/var/worklist/ICWS";

    private AdminUser $adminUser;

    public function __construct()
    {
        $this->adminUser = new AdminUser();

    }

    public function teszt() {
        //$this->processEntries();
        $this->rescanDicomEntries();
        //print_r($dicomEntries);
    }


    public function rescanDicomEntries() {
        header('Content-Type: text/html; charset=utf-8');

        $entries = sql_query("select * from dicom order by contentDate desc limit 2000")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($entries as $entry) {
            if (is_file($entry["fileName"])) {
                $output = `dcm2xml +Ca latin-1 {$entry["fileName"]}`;
                $xml = simplexml_load_string($output);

                $studyDescription = "";
                $seriesDescription = "";

                //$xml = simplexml_load_string($entry["xml"]);

                //echo $xml;
                //die;
                foreach ($xml->children() as $child) {
                    if ($child->getName() == "data-set") {
                        foreach ($child->children() as $subchild) {
                            foreach ($subchild->attributes() as $attr) {
                                if ($attr == "StudyDescription") {
                                    $studyDescription = $subchild;
                                }
                                if ($attr == "SeriesDescription") {
                                    $seriesDescription = $subchild;
                                }
                            }
                        }
                    }
                }

                echo $entry["fileName"] . " " . $studyDescription . "|" . $seriesDescription . "\n";

                sql_query("update dicom set seriesDescription=? where id=?", [$seriesDescription, $entry["id"]]);
            }
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

            $patientName           = "";
            $patientID             = "";
            $patientBirthDate      = "0000-00-00";
            $patientSex            = "";
            $patientOtherIDs       = "";
            $studyDescription      = "";
            $seriesDescription     = "";
            $contentTime           = "";
            $contentDate           = "";
            $manufacturer          = "";
            $manufacturerModelName = "";
            $institutionName       = "";

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
                            if ($attr == "SeriesDescription") {
                                $seriesDescription = $subchild;
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

            sql_query("insert into dicom set contentDate=?, fileName=?, xml=?, patientName=?, patientID=?, patientBirthDate=?, patientSex=?, patientOtherIDs=?, studyDescription=?, seriesDescription=?, manufacturer=?, manufacturerModelName=?, institutionName=?, uid=uuid(), token=CONCAT(MD5(CONCAT('paSS1', xml)),MD5(CONCAT('paSS2AndLast', xml)))",
                [$contentDate, $dicomEntry, utf8_encode($output), $patientName, $patientUniqueId, $patientBirthDate, $patientSex, $patientOtherIDs, $studyDescription, $seriesDescription, $manufacturer, $manufacturerModelName, $institutionName]);

        }

    }

    private function readDir() {
        $entries = [];
        $d = dir(self::STORAGE_DIR);

        while (false !== ($entry = $d->read())) {
            if (substr($entry, 0, 2) == "CR" || substr($entry, 0, 2) == "DX") {
                $entries[] = self::STORAGE_DIR."/".$entry;
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

        if (isset($params["byid"]) && !empty($params["byid"])) {
            $w .= " and d.patientID=?";
            $queryParams[] = $params["byid"];
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
                    $thumbLocation = self::STORAGE_DIR."/thumbnails/{$id}.jpg";
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
        $dir = self::STORAGE_DIR;
        return imagecreatefromstring(`convert {$dir}/skeleton{$num}.png {$param} png:-`);
    }

    public function getCompanies() {
        return sql_query_common("select institutionName from dicom group by institutionName")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getModels() {
        return sql_query_common("select concat(manufacturerModelName,' ',manufacturer) as name, manufacturer from dicom group by manufacturer")->fetchAll(PDO::FETCH_ASSOC);
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

    public function setLeletStatus($id, $num, $user) {
        sql_query_common("update dicom set leletstatus=?, leletcreatedby=? where uid=? limit 1", [$num, $user, $id]);
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

    public function getLeletStatus($id, $date) {
        return sql_query_common("select id, leletstatus, leletcreatedby, leletkiallitva, leletkiallitvaby from dicom where patientID=? and contentDate=? limit 1", [$id, $date])->fetch(PDO::FETCH_ASSOC);
    }

    public function toggleLeletKiallitva($id, $user) {
        sql_query_common("update dicom set leletkiallitva=if(leletkiallitva=0, 1, 0), leletkiallitvaby=? where id=? limit 1", [$user, $id]);
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

    public function workListFileFormat($data):string {
        $companyId      = Booking_Constants::SQL_DB;
        $companyName    = iconv("UTF-8", "ISO-8859-2", Booking_Constants::COMPANY_NAME);
        $companyAddress = iconv("UTF-8", "ISO-8859-2", Booking_Constants::COMPANY_ADDRESS);
        $name = iconv("UTF-8", "ISO-8859-2", $data["nev"]);
        $company = iconv("UTF-8", "ISO-8859-2", $data["cegnev"]);
        $birthDate = date("Ymd", strtotime($data["szuldatum"]));
        $idopont = date("Ymd", strtotime($data["datum"]));
        $patientSex = $data["neme"] == 2 ? "F":"M";
        $accessionNumber = $data["id"];

        return "# Dicom-Data-Set
# Used TransferSyntax: Little Endian Explicit
(0008,0005) CS [ISO_IR 100]                             #  10, 1 SpecificCharacterSet
(0008,0008) CS [ORIGINAL\PRIMARY]                       #  16, 2 ImageType
(0008,0020) DA [{$idopont}]                             #   8, 1 StudyDate
(0008,0022) DA [{$idopont}]                             #   8, 1 AcquisitionDate
(0008,0023) DA [{$idopont}]                             #   8, 1 ContentDate
(0008,0050) SH [{$accessionNumber}]                     #   4, 1 AccessionNumber
(0008,0060) CS [CR]                                     #   2, 1 Modality
(0008,0070) LO [Pictron Kft]                            #  12, 1 Manufacturer
(0008,0080) LO [{$companyName}]                         #  16, 1 InstitutionName
(0008,0081) ST [{$companyAddress}]                      #  16, 1 InstitutionAddress
(0008,1010) SH [localhost]                              #  10, 1 StationName
(0008,1030) LO [{$company}]                             #   0, 0 StudyDescription
(0008,1070) PN [RTG]                                    #   4, 1 OperatorsName
(0008,1090) LO [ICWS]                                   #   4, 1 ManufacturerModelName
(0010,0010) PN [{$name}]                                #  12, 1 PatientName
(0010,0020) LO [{$data["taj"]}]                         #   8, 1 PatientID
(0010,0030) DA [{$birthDate}]                           #   8, 1 PatientBirthDate
(0010,0040) CS [{$patientSex}]                          #   2, 1 PatientSex
(0010,1000) LO [{$data["taj"]}]                         #   8, 1 OtherPatientIDs
(0018,1020) LO [ICWS V2.2 - 2012.10.16]                 #  22, 1 SoftwareVersions
(0032,1032) PN [{$companyId}]                           # RequestingPhysician
(0032,1033) LO [ez a szolgaltatas neve]                 # RequestingService
(0032,1060) LO [{$company}]                             # RequestedProcedureDescription
(0038,0050) LO [ez az amire szukseg van]                # SpecialNeeds
";
    }


}

