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
            $output = `dcm2xml {$dicomEntry}`;
            $xml = simplexml_load_string($output);

            //print_r($output);die;
            //print_r((string)$xml->{'data-set'}->element[18]);die;

            if (sql_fetch_array(sql_query("select filename from dicom where filename=?", [$dicomEntry]))) {
                //continue;
            }

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

            sql_query("insert into dicom set contentDate=?, fileName=?, xml=?, patientName=?, patientID=?, patientBirthDate=?, patientSex=?, patientOtherIDs=?, studyDescription=?", [$contentDate, $dicomEntry, utf8_encode($output), $patientName, $patientID, $patientBirthDate, $patientSex, $patientOtherIDs, $studyDescription]);

        }

        /*
        <element tag="0008,0005" vr="CS" vm="1" len="10" name="SpecificCharacterSet">ISO_IR 100</element>
        <element tag="0008,0008" vr="CS" vm="2" len="16" name="ImageType">ORIGINAL\PRIMARY</element>
        <element tag="0008,0016" vr="UI" vm="1" len="26" name="SOPClassUID">1.2.840.10008.5.1.4.1.1.1</element>
        <element tag="0008,0018" vr="UI" vm="1" len="56" name="SOPInstanceUID">2.16.840.1.111111.20210504143747.253310.304898.17577620</element>
        <element tag="0008,0020" vr="DA" vm="1" len="8" name="StudyDate">20210504</element>
        <element tag="0008,0022" vr="DA" vm="1" len="8" name="AcquisitionDate">20210504</element>
        <element tag="0008,0023" vr="DA" vm="1" len="8" name="ContentDate">20210504</element>
        <element tag="0008,0030" vr="TM" vm="1" len="6" name="StudyTime">143747</element>
        <element tag="0008,0033" vr="TM" vm="1" len="6" name="ContentTime">143747</element>
        <element tag="0008,0050" vr="SH" vm="1" len="10" name="AccessionNumber">9100345638</element>
        <element tag="0008,0060" vr="CS" vm="1" len="2" name="Modality">CR</element>
        <element tag="0008,0070" vr="LO" vm="1" len="12" name="Manufacturer">Pictron Kft</element>
        <element tag="0008,0080" vr="LO" vm="1" len="16" name="InstitutionName">Az int▒zet neve</element>
        <element tag="0008,0081" vr="ST" vm="1" len="16" name="InstitutionAddress">Az int▒zet c▒me</element>
        <element tag="0008,1010" vr="SH" vm="1" len="10" name="StationName">localhost</element>
        <element tag="0008,1030" vr="LO" vm="1" len="6" name="StudyDescription">Sz▒r▒s</element>
        <element tag="0008,1070" vr="PN" vm="1" len="4" name="OperatorsName">RTG</element>
        <element tag="0008,1090" vr="LO" vm="1" len="4" name="ManufacturerModelName">ICWS</element>
        <element tag="0010,0010" vr="PN" vm="1" len="10" name="PatientName">G▒L^CSABA</element>
        <element tag="0010,0020" vr="LO" vm="1" len="10" name="PatientID">0000000843</element>
        <element tag="0010,0030" vr="DA" vm="1" len="8" name="PatientBirthDate">19530924</element>
        <element tag="0010,0040" vr="CS" vm="1" len="2" name="PatientSex">M</element>
        <element tag="0010,1000" vr="LO" vm="1" len="10" name="OtherPatientIDs">018161955</element>
        <element tag="0018,1020" vr="LO" vm="1" len="22" name="SoftwareVersions">ICWS V2.2 - 2012.10.16</element>
        <element tag="0018,5101" vr="CS" vm="1" len="2" name="ViewPosition">AP</element>
        <element tag="0020,000d" vr="UI" vm="1" len="38" name="StudyInstanceUID">2.16.840.1.111111.20210504.9100345638</element>
        <element tag="0020,000e" vr="UI" vm="1" len="40" name="SeriesInstanceUID">2.16.840.1.111111.20210504.9100345638.1</element>
        <element tag="0020,0010" vr="SH" vm="1" len="10" name="StudyID">9100345638</element>
        <element tag="0020,0011" vr="IS" vm="1" len="2" name="SeriesNumber">1</element>
        <element tag="0020,0012" vr="IS" vm="1" len="6" name="AcquisitionNumber">253310</element>
        <element tag="0020,0013" vr="IS" vm="1" len="2" name="InstanceNumber">1</element>
        <element tag="0028,0002" vr="US" vm="1" len="2" name="SamplesPerPixel">1</element>
        <element tag="0028,0004" vr="CS" vm="1" len="12" name="PhotometricInterpretation">MONOCHROME2</element>
        <element tag="0028,0006" vr="US" vm="1" len="2" name="PlanarConfiguration">0</element>
        <element tag="0028,0010" vr="US" vm="1" len="2" name="Rows">2048</element>
        <element tag="0028,0011" vr="US" vm="1" len="2" name="Columns">2048</element>
        <element tag="0028,0030" vr="DS" vm="2" len="18" name="PixelSpacing">0.102543\0.102543</element>
        <element tag="0028,0100" vr="US" vm="1" len="2" name="BitsAllocated">16</element>
        <element tag="0028,0101" vr="US" vm="1" len="2" name="BitsStored">12</element>
        <element tag="0028,0102" vr="US" vm="1" len="2" name="HighBit">11</element>
        <element tag="0028,0103" vr="US" vm="1" len="2" name="PixelRepresentation">0</element>
        <element tag="0028,1050" vr="DS" vm="1" len="4" name="WindowCenter">1962</element>
        <element tag="0028,1051" vr="DS" vm="1" len="4" name="WindowWidth">3715</element>
        <element tag="0029,1050" vr="CS" vm="1" len="4" name="Unknown Tag &amp; Data">1.07</element>
        <element tag="6000,0010" vr="US" vm="1" len="2" name="OverlayRows">2048</element>
        <element tag="6000,0011" vr="US" vm="1" len="2" name="OverlayColumns">2048</element>
        <element tag="6000,0012" vr="US" vm="1" len="2" name="RETIRED_OverlayPlanes">1</element>
        <element tag="6000,0015" vr="IS" vm="1" len="2" name="NumberOfFramesInOverlay">1</element>
        <element tag="6000,0040" vr="CS" vm="1" len="2" name="OverlayType">G</element>
        <element tag="6000,0050" vr="SS" vm="2" len="4" name="OverlayOrigin">1\1</element>
        <element tag="6000,0100" vr="US" vm="1" len="2" name="OverlayBitsAllocated">1</element>
        <element tag="6000,0102" vr="US" vm="1" len="2" name="OverlayBitPosition">0</element>
        <element tag="6000,3000" vr="OW" vm="1" len="524288" name="OverlayData" loaded="no" binary="hidden"></element>
        <element tag="7fe0,0010" vr="OW" vm="1" len="8388608" name="PixelData" loaded="no" binary="hidden"></element>
        */


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

        $images = sql_query("select * from dicom where  true {$w} order by contentDate desc limit 500", $queryParams)->fetchAll(PDO::FETCH_ASSOC);
        return $images;
    }

    public function getRawDicomFile($id) {
        $content = ["fileName" =>"", "file" => ""];
        if ($data = sql_query("select fileName from dicom where id=?", [$id])->fetch(PDO::FETCH_ASSOC)) {
            $content["fileName"] = basename($data["fileName"]);
            $content["file"] = file_get_contents($data["fileName"]);
        }



        return $content;
    }

    public function getRawImage($id) {
        if ($content = sql_query("select * from dicom where id=?", [$id])->fetch(PDO::FETCH_ASSOC)) {

            $content["imageData"] = `dcmj2pnm --write-png {$content["fileName"]}`;

        }

        return $content;
    }

}

//703305233
