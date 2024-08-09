<?php

class DicomService {
    const STORAGE_DIR = "/var/rtg";
    const STORAGE_DIR_TESZT = "/var/rtg/teszt";
    const WORKLIST_DIR = "/var/worklist/ICWS";

    private AdminUser $adminUser;

    public function __construct()
    {
        $this->adminUser = new AdminUser();

    }

    public static function tesztMode():bool {
        return session_id() == "q9tcjuhgv7c72a861ldi7pvg3h";
    }

    public function teszt() {
        //$this->processEntries();
        $this->rescanDicomEntries();
        //print_r($dicomEntries);
    }

    public function addFile($uploadedFile):string {
        if (is_uploaded_file($uploadedFile["tmp_name"])) {
            $tempFile = self::STORAGE_DIR."/".$uploadedFile["name"];
            @move_uploaded_file($uploadedFile["tmp_name"], $tempFile);

            $output = `dcm2xml +Ca latin-1 {$tempFile}`;
            if (!simplexml_load_string($output)) {
                @unlink($tempFile);
                return "Hibás fájl formátum!";
            }
        } else {
            return "Nincs feltöltött file!";
        }

        return "";
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
        $wDateRestrict = "AND d.contentDate>DATE_SUB(NOW(), INTERVAL 14 DAY)";

        if (!$this->adminUser->allDicomAccess()) {
            $companyFilter = $this->adminUser->cegSQLFilter("d.cegid");
            $w.= $companyFilter;

            if (!empty($companyFilter)) {
                $this->setSelectedCompany("");
                $wDateRestrict = "";
            }
        }

        /*if (in_array($this->adminUser->user["username"], ["drkizman", "kizman", "drosvai@t-online.hu"])) {
            $w.= " and institutionName<>'Veszprém Mobil'";
            //$w.= " and leletcreatedby<>'Dr. Dánielisz Zsuzsanna'";
        }*/

        if (!empty($params["search"])) {
            $w .= " and instr(concat(patientName,patientBirthDate,patientOtherIDs), ?)";
            $queryParams[] = $params["search"];
            $wDateRestrict = "";
        }

        if (!empty($params["byuid"])) {
            $w .= " and uid=?";
            $queryParams[] = $params["byuid"];
            $wDateRestrict = "";
        }

        if (!empty($params["byid"])) {
            $w .= " and d.patientID=?";
            $queryParams[] = $params["byid"];
            $wDateRestrict = "";
        }

        if (!empty($this->getSelectedCompany())) {
            $w .= " and d.institutionName=?";
            $queryParams[] = $this->getSelectedCompany();
        }

        if (!empty($this->getSelectedModel())) {
            $w .= " and d.manufacturer=?";
            $queryParams[] = $this->getSelectedModel();
            $wDateRestrict = "AND d.contentDate>DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        }

        //$wDateRestrict = "";

        //echo "{$wDateRestrict} {$w} ";
        //echo print_r($queryParams, true);

        return sql_query_common("select d.*, max(d.contentDate) as datum, count(*) as imageNum from dicom d where TRUE {$wDateRestrict} {$w} group by d.patientID, d.patientBirthDate order by max(contentDate) desc limit 500", $queryParams)->fetchAll(PDO::FETCH_ASSOC);
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

    public function deleteDicomEntry($id) {
        sql_query_common("delete from dicom where id=? limit 1", [$id]);
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
                if (isset($_GET["embedinfo"])) {
                    $image = imagecreatefromstring(`dcmj2pnm --write-png --use-window 1 {$content["fileName"]}`);

                    $black = imagecolorallocate($image, 0, 0, 0);
                    $white = imagecolorallocate($image, 255, 255, 255);
                    $fontPath = Booking_Constants::APP_PATH."public/images/webfonts/roboto_regular_hungarian/Roboto-Regular-webfont.ttf";
                    $text = "Ide jön a paciens neve!";

                    imagettftext($image, 45, 0, 76, 126, $black, $fontPath, $text);
                    imagettftext($image, 45, 0, 75, 125, $white, $fontPath, $text);

                    $content["imageData"] = $image;
                    return $content;
                }

                if (isset($_GET["thumb"])) {
                    $thumbLocation = self::STORAGE_DIR."/thumbnails/{$id}.jpg";
                    if (!is_file($thumbLocation)) {
                        `dcmj2pnm --write-png --min-max-window {$content["fileName"]} | convert - -resize 200 {$thumbLocation}`;
                    }
                    $content["imageData"] = imagecreatefromstring(file_get_contents($thumbLocation));
                } else {
                    if (!empty($param)) {
                        $content["imageData"] = imagecreatefromstring(`dcmj2pnm --write-png {$content["fileName"]} | convert - {$param} png:-`);
                    } else {
                        $content["imageData"] = imagecreatefromstring(`dcmj2pnm --write-png --use-window 1 {$content["fileName"]}`);
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

    public function getCompanies($cache = false):array {
        $cacheFileName = Booking_Constants::DOCUMENT_PATH."dicomCompanyCache.txt";

        if ($cache || !is_file($cacheFileName)) {
            $arr = sql_query_common("select institutionName from dicom group by institutionName")->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents($cacheFileName, json_encode($arr, JSON_PRETTY_PRINT));
        }

        return json_decode(file_get_contents($cacheFileName), JSON_OBJECT_AS_ARRAY);
    }

    public function getModels($cache = false):array {
        $cacheFileName = Booking_Constants::DOCUMENT_PATH."dicomModelCache.txt";

        if ($cache || !is_file($cacheFileName)) {
            $arr = sql_query_common("select concat(manufacturerModelName,' ',manufacturer) as name, manufacturer from dicom group by manufacturer")->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents($cacheFileName, json_encode($arr, JSON_PRETTY_PRINT));
        }

        return json_decode(file_get_contents($cacheFileName), JSON_OBJECT_AS_ARRAY);
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

    public function setCompanyId($id, $companyId) {
        sql_query_common("update dicom set cegid=? where patientID=? limit 10", [$companyId, $id]);
    }

    public function setSelectedCompany($company) {
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


    private function checkArchive($filePath):bool {
        if (!is_file($filePath)) {
            //a fájl archiválva lett, megpróbáljuk lehúzni az arhívumból
            $fileName = basename($filePath);
            `scp -P 2223 root@81.183.233.8:/mnt/sdd/rtg/{$fileName} /var/rtg`;
        }

        return is_file($filePath);
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

        return "# Dicom-File-Format

# Dicom-Meta-Information-Header
# Used TransferSyntax: Little Endian Explicit
(0002,0000) UL 210                                      #   4, 1 FileMetaInformationGroupLength
(0002,0001) OB 00\\01                                    #   2, 1 FileMetaInformationVersion
(0002,0002) UI =ComputedRadiographyImageStorage         #  26, 1 MediaStorageSOPClassUID
(0002,0003) UI [2.16.840.1.111111.20210512104557.253313.304901.1168384] #  54, 1 MediaStorageSOPInstanceUID
(0002,0010) UI =LittleEndianExplicit                    #  20, 1 TransferSyntaxUID
(0002,0012) UI [1.2.276.0.7230010.3.0.3.6.0]            #  28, 1 ImplementationClassUID
(0002,0013) SH [OFFIS_DCMTK_360]                        #  16, 1 ImplementationVersionName
(0002,0016) AE [ICWS]                                   #   4, 1 SourceApplicationEntityTitle

# Dicom-Data-Set
# Used TransferSyntax: Little Endian Explicit
(0008,0005) CS [ISO_IR 100]                             #  10, 1 SpecificCharacterSet
(0008,0008) CS [ORIGINAL\PRIMARY]                       #  16, 2 ImageType
(0008,0016) UI =ComputedRadiographyImageStorage         #  26, 1 SOPClassUID
(0008,0018) UI [2.16.840.1.111111.20210512104557.253313.304901.1168384] #  54, 1 SOPInstanceUID
(0008,0020) DA [{$idopont}]                             #   8, 1 StudyDate
(0008,0022) DA [{$idopont}]                             #   8, 1 AcquisitionDate
(0008,0023) DA [{$idopont}]                             #   8, 1 ContentDate
(0008,0030) TM [104557]                                 #   6, 1 StudyTime
(0008,0033) TM [104557]                                 #   6, 1 ContentTime
(0008,0050) SH [3645]                                   #   4, 1 AccessionNumber
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
(0018,5101) CS [AP]                                     #   2, 1 ViewPosition
(0020,000d) UI [2.16.840.1.111111.20210512.3645]        #  32, 1 StudyInstanceUID
(0020,000e) UI [2.16.840.1.111111.20210512.3645.1]      #  34, 1 SeriesInstanceUID
(0020,0010) SH [3645]                                   #   4, 1 StudyID
(0020,0011) IS [1]                                      #   2, 1 SeriesNumber
(0020,0012) IS [253313]                                 #   6, 1 AcquisitionNumber
(0020,0013) IS [1]                                      #   2, 1 InstanceNumber
(0028,0002) US 1                                        #   2, 1 SamplesPerPixel
(0028,0004) CS [MONOCHROME2]                            #  12, 1 PhotometricInterpretation
(0028,0006) US 0                                        #   2, 1 PlanarConfiguration
(0028,0010) US 2048                                     #   2, 1 Rows
(0028,0011) US 2048                                     #   2, 1 Columns
(0028,0030) DS [0.102543\\0.102543]                      #  18, 2 PixelSpacing
(0028,0100) US 16                                       #   2, 1 BitsAllocated
(0028,0101) US 12                                       #   2, 1 BitsStored
(0028,0102) US 11                                       #   2, 1 HighBit
(0028,0103) US 0                                        #   2, 1 PixelRepresentation
(0028,1050) DS [1962]                                   #   4, 1 WindowCenter
(0028,1051) DS [3715]                                   #   4, 1 WindowWidth
(0029,1050) CS [1.07]                                   #   4, 1 Unknown Tag & Data
(0032,1032) PN [{$companyId}]                           # RequestingPhysician
(0032,1033) LO [ez a szolgaltatas neve]                 # RequestingService
(0032,1060) LO [{$company}]                             # RequestedProcedureDescription
(0038,0050) LO [ez az amire szukseg van]                # SpecialNeeds
(6000,0010) US 2048                                     #   2, 1 OverlayRows
(6000,0011) US 2048                                     #   2, 1 OverlayColumns
(6000,0012) US 1                                        #   2, 1 RETIRED_OverlayPlanes
(6000,0015) IS [1]                                      #   2, 1 NumberOfFramesInOverlay
(6000,0040) CS [G]                                      #   2, 1 OverlayType
(6000,0050) SS 1\\1                                      #   4, 2 OverlayOrigin
(6000,0100) US 1                                        #   2, 1 OverlayBitsAllocated
(6000,0102) US 0                                        #   2, 1 OverlayBitPosition
(6000,3000) OW 0000\\0000\\0000\\0000\\0000\\0000\\0000\\0000\\0000\\0000\\0000\\0000\\0000... # 524288, 1 OverlayData
(7fe0,0010) OW 6f6f\\6f6f\\6f6f\\6f6f\\6f6f\\6f6f\\6f6f\\6f6f\\6f6f\\6f6f\\6f6f\\6f6f\\6f6f... # 8388608, 1 PixelData
";
    }

    public function workListFileFormatNew($data):string {
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

