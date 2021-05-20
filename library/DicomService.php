<?php

class DicomService {

    private $dir = "/var/rtg";
    public function __construct()
    {


    }

    public function teszt() {
        $this->processEntries();
        //print_r($dicomEntries);
    }

    public function processEntries() {
        $dicomEntries = $this->readDir();

        foreach ($dicomEntries as $dicomEntry) {
            if (sql_fetch_array(sql_query("select filename from dicom where filename=?", [$dicomEntry]))) {
                continue;
            }

            $output = `dcm2xml {$dicomEntry}`;
            $xml = simplexml_load_string($output);

            $patientName      = "";
            $patientID        = "";
            $patientBirthDate = "0000-00-00";
            $patientSex       = "";
            $patientOtherIDs  = "";
            $studyDescription = "";
            $contentTime      = "";
            $contentDate      = "";

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
                        }
                    }
                }
            }

            $contentDate = substr($contentDate,0,4)."-".substr($contentDate,4,2)."-".substr($contentDate,6,2)." ".substr($contentTime,0,2).":".substr($contentTime,2,2).":".substr($contentTime,4,2);

            echo "storing {$patientName}\n";

            sql_query("insert into dicom set contentDate=?, fileName=?, xml=?, patientName=?, patientID=?, patientBirthDate=?, patientSex=?, patientOtherIDs=?, studyDescription=?, uid=uuid(), token=CONCAT(MD5(CONCAT('paSS1', xml)),MD5(CONCAT('paSS2AndLast', xml)))",
                [$contentDate, $dicomEntry, utf8_encode($output), $patientName, $patientID, $patientBirthDate, $patientSex, $patientOtherIDs, $studyDescription]);

        }

    }

    private function readDir() {
        $entries = [];
        $d = dir($this->dir);

        while (false !== ($entry = $d->read())) {
            if (substr($entry, 0, 2) == "CR") {
                $entries[] = $this->dir."/".$entry;
            }
        }
        return $entries;
    }


    public function getImages($params = []) {
        $queryParams = [];
        $w = "";

        if (isset($params["search"]) && !empty($params["search"])) {
            $w .= " and instr(concat(patientName,patientBirthDate,patientOtherIDs), ?)";
            $queryParams[] = $params["search"];
        }

        return sql_query_common("select * from dicom where true {$w} order by contentDate desc limit 500", $queryParams)->fetchAll(PDO::FETCH_ASSOC);
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

    private function dicomPermission() {
        if (isset($_SESSION["adminuser"])) {
            return true;
        } else {
            return false;
        }
    }

    public function getRawImage($id) {
        if ($content = sql_query_common("select * from dicom where uid=?", [$id])->fetch(PDO::FETCH_ASSOC)) {
            $param = "";

            if (isset($_GET["normalize"])) {
                $param.= " -normalize";
            }
            if (isset($_GET["invert"])) {
                $param.= " -negate";
            }

            if (!$this->dicomPermission()) {
                $num = rand(1,12);

                $content["imageData"] = imagecreatefromstring(`convert {$this->dir}/skeleton{$num}.png {$param} png:-`);
            } else {
                if (!empty($param)) {
                    $content["imageData"] = imagecreatefromstring(`dcmj2pnm --write-png {$content["fileName"]} | convert - {$param} png:-`);
                } else {
                    $content["imageData"] = imagecreatefromstring(`dcmj2pnm --write-png {$content["fileName"]}`);
                }
            }


        }

        return $content;
    }

}

