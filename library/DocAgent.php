<?php


class DocAgent {

    public function __construct()
    {
    }

    private function _getDocPath($fileId) {
        $id = (int)$fileId;
        $path = Booking_Constants::DOCUMENT_PATH.floor($id / 1000);
        if (!is_dir($path)) mkdir($path);
        $path.="/{$id}.bin";
        return $path;
    }

    public function getDoc($fileId) {
        $fileName = $this->_getDocPath($fileId);
        //return file_get_contents($fileName);
    }

    public function storeDoc($fileId, $content) {
        $fileName = $this->_getDocPath($fileId);
        return file_put_contents($fileName, $content);
    }

    public function saveDoc($uploadedFile, $fileData) {
        if (is_uploaded_file($uploadedFile["tmp_name"])) {
            $fileName = strtolower($uploadedFile["name"]);
            $fileSize = $uploadedFile["size"];
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            if (in_array($extension, array("pdf","doc","xls","docx","xlsx"))) {
                sql_query("insert into dokumentumok set 
                    beutaloid=?, userid=?, megnev=?, filename=?, size=?, tipus=?, datum=now(), kod=SHA1(MD5(CONCAT(NOW(),RAND()*20000)))",
                    array($fileData["beutaloid"], $fileData["userid"], $fileData["megnev"], $fileName, $fileSize, $extension));
                $id = sql_insert_id();

                if (isset($fileData["sess"])) {
                    sql_query("update dokumentumok set sess=? where id=?",array($fileData["sess"], $id));
                }

                $destinationFile = $this->_getDocPath($id);
                @move_uploaded_file($uploadedFile["tmp_name"], $destinationFile);
                return "0";
            } else {
                return "A feltöltött file formátuma nem megfelelő (csak pdf, és word dokumentumot lehet feltölteni)";
            }


        } else {
            return "Nincs feltöltött file!";
        }
    }

    public function deleteDoc($id, $code) {
        if (sql_fetch_array(sql_query("select * from dokumentumok where id=? and kod=?",array($id, $code)))) {
            sql_query("delete from dokumentumok where id=?",array($id));
            @unlink($this->_getDocPath($id));
        }
    }

    public function updateDisplayTime($id) {
        sql_query("update dokumentumok set megnezve=now() where id=?",array($id));
    }

    public function updateSecurityCode($id) {
        sql_query("update dokumentumok set kod=SHA1(MD5(CONCAT(NOW(),RAND()*20000))) where id=?",array($id));
    }

    public function showDocBinary($id, $code) {
        error_reporting(0);
        if (!$fileData = sql_fetch_array(sql_query("select * from dokumentumok where id=? and kod=?",array($id, $code)))) {
            die("error 2");
        }

        if (!$file = $this->getDoc($id)) {
            die("error3");
        }

        if (isset($_GET["v"])) {
            $this->updateDisplayTime($id);
        }
        $this->updateSecurityCode($id);

        header("Pragma: no-cache");
        header("Cache-Control: no-store, no-cache");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: must-revalidate");
        header('Content-transfer-encoding: binary');
        header('Content-Disposition: attachment; filename="'.$fileData["filename"].'"');
        if ($fileData["tipus"]=="pdf") header("Content-Type: application/pdf");
        if ($fileData["tipus"]=="doc") header("Content-Type: application/msword");
        if ($fileData["tipus"]=="docx") header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        if ($fileData["tipus"]=="xls") header("Content-Type: application/vnd.ms-excel");
        if ($fileData["tipus"]=="xlsx") header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        if ($fileData["tipus"]=="jpg" || $fileData["tipus"]=="jpeg") header("Content-Type: image/jpeg");
        if ($fileData["tipus"]=="png") header("Content-Type: image/png");
        echo $file;
        die();
    }

    public static function getDocURL($docData) {
        $domain = "{$_SESSION["helyszindata"]["domain"]}.".Booking_Constants::SITE_DOMAIN;
        if (isset($_SERVER["HTTP_HOST"])) {
            $domain = $_SERVER["HTTP_HOST"];
        }

        $docURL = "//{$domain}/?downloaddoc&f={$docData["id"]}&k={$docData["kod"]}";
        return $docURL;
    }

}

