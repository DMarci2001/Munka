<?php

class CentrumlabService {

    const IN_DIR = "/var/commcl/in/";
    const OUT_DIR = "/var/commcl/out/";

    const IN_FILE = "lab.msg";
    const OUT_FILE = "lab.msg";
    const SEMAFOR_FILE = "lab.sem";

    public function __construct() {

    }


    public function writeNextRequest() {
        if ($this->requestRunning()) {
            return;
        }

        if ($requestData = sql_query("select * from labrequests where status='pending' order by created limit 1")->fetch(PDO::FETCH_ASSOC)) {
            $data = $this->generateHL7FileByRequestId($requestData);
            $this->writeRequestFile($data);
            $this->writeSemaforFile();
        }
    }

    public function getReceivedAnswer() {
        //válasz feldolgozás, utána fájlok törlése

        $this->deleteInFiles();
    }

    public function generateHL7FileByRequestId($requestData):string {
        //sql_query("update labrequests set status='sent' where id=?", [$requestData["id"]]);

        $data = "sjflsjflsjflsfs";
        return $data;
    }

    public function cronCheck() {
        //percenként hívva cronnal
        $this->writeNextRequest();
        $this->getReceivedAnswer();
    }

    public function requestRunning():bool {
        return is_file(self::OUT_DIR.self::SEMAFOR_FILE) && is_file(self::OUT_DIR.self::OUT_FILE);
    }

    public function writeSemaforFile() {
        file_put_contents(self::OUT_DIR.self::SEMAFOR_FILE, "");
    }

    public function writeRequestFile($data) {
        file_put_contents(self::OUT_DIR.self::OUT_FILE, $data);
    }

    public function deleteInFiles() {
        unlink(self::IN_DIR.self::SEMAFOR_FILE);
        unlink(self::IN_DIR.self::IN_FILE);
    }


}