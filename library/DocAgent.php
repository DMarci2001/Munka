<?php


class DocAgent {
    const ASSET_DOCTOR_PHOTO = "orvosphoto";
    const ASSET_SERVICE_ILLUSTRATION_IMAGE = "serviceimage";

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

    private static function _getAssetImagePath($fileId) {
        $path = "/var/www/onlinebejelentkezes_keltexmed/public/images/assets_".Booking_Constants::SQL_DB."/";
        if (!is_dir($path)) {
            mkdir($path);
        }
        $path.= floor((int)$fileId / 1000)."/";
        if (!is_dir($path)) {
            mkdir($path);
        }
        return $path;
    }

    public function getAssetImageURL($tipus, $id) {
        $path = self::_getAssetImagePath($id)."{$tipus}_{$id}.jpg";
        if (!is_file($path)) {
            //return "";
        }
        return str_replace("/var/www/onlinebejelentkezes_keltexmed/public", "", $path);
    }

    public function getDoc($fileId) {
        $fileName = $this->_getDocPath($fileId);
        return file_get_contents($fileName);
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

            if (in_array($extension, array("pdf","doc","xls","docx","xlsx","jpg","jpeg"))) {
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
                return "A feltöltött file formátuma nem megfelelő (csak jpg, pdf, és word dokumentumot lehet feltölteni)";
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

    public function uploadAssetImage($tipus, $oid, $uploadedFile):array {
        $result = ["error" => ""];

        if (is_uploaded_file($uploadedFile["tmp_name"])) {
            $fileName = strtolower($uploadedFile["name"]);
            $fileSize = $uploadedFile["size"];
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            if (in_array($extension, ["jpg", "jpeg", "png"])) {
                sql_query("insert into dokumentumok set datum=now(), assetid=?, dataid=?, filename=?, tipus=?, size=?", [$tipus, $oid, $fileName, $extension, $fileSize]);
                $fileId = sql_insert_id();
                $path = $this->_getAssetImagePath($fileId);

                $kepfile = "{$path}/{$tipus}_{$fileId}.{$extension}";
                @move_uploaded_file($uploadedFile["tmp_name"], $kepfile);

                $size = GetImageSize($kepfile);
                $xsize = $size[0];
                $ysize = $size[1];


                if ($extension == "jpg" || $extension == "jpeg") {
                    $src_img = ImageCreateFromJpeg($kepfile);
                }

                if ($extension == "png") {
                    $src_img = ImageCreateFromPNG($kepfile);
                    unlink($kepfile);
                    $kepfile = "{$path}/{$tipus}_{$fileId}.jpg";
                    imagejpeg($src_img, $kepfile);
                }

                $scale = [512, 512];

                if ($ysize > $scale[1]) {
                    $xscale = $scale[0];
                    $yscale = $scale[1];
                    $newxsize = floor($xsize/($xsize/$xscale));
                    $newysize = floor($ysize/($xsize/$xscale));
                    if ($newysize < $yscale) {
                        $newxsize = floor($xsize/($ysize/$yscale));
                        $newysize = floor($ysize/($ysize/$yscale));
                    }
                    $dst_img = imagecreatetruecolor($newxsize,$newysize);
                    ImageCopyResampled($dst_img, $src_img, 0, 0, 0, 0, $newxsize, $newysize, ImageSX($src_img), ImageSY($src_img));
                    imagejpeg($dst_img, $kepfile);

                    $xsize = $newxsize;
                    $ysize = $newysize;
                }

            } else {
                $result["error"] = "A feltöltött file csak jpg vagy png lehet!";
            }
        } else {
            $result["error"] = "A feltöltés közben hiba történt!";
        }

        return $result;

    }

    public function deleteAsset($tipus, $id) {
        $path = self::_getAssetImagePath($id)."{$tipus}_{$id}.jpg";
        unlink($path);
        sql_query("delete from dokumentumok where assetid=? and id=? limit 1", [$tipus, $id]);
    }

    public function showAssetEditor($tipus, $dataId):string {
        $html = "";

        $images = sql_query("select * from dokumentumok where assetid=? and dataid=?", [$tipus, $dataId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($images as $imageData) {
            $photoURL = $this->getAssetImageURL($tipus, $imageData["id"])."?v=".date("YmdHis");
            $html.= "<div style='display:inline-block;'>";
            $html.= "<a href='{$photoURL}' target='_blank'><img class='assetimageitem' src='{$photoURL}' /></a>";
            $html.= "<div style='margin-top:5px;text-align: center;'><a href='#' onclick='deleteAsset(\"{$tipus}\", {$imageData["id"]});return false;'>Kép törlése</a></div>";
            $html.= "</div>";
        }

        $html.= "<div style='display:inline-block;vertical-align: top;'>";
        $html.= "<div class='upload-btn-wrapper'><div class='upbtn'>kép<br/>hozzáadása</div><input data-tipus='{$tipus}' data-id='{$dataId}' type='file' id='assetphotofile' name='assetphotofile' /></div><img id='ajaxloader' style='display:none;opacity:.5;height:30px;margin-left:10px;' src='/images/loading.svg' />";
        $html.= "</div>";

        return $html;
    }

}

