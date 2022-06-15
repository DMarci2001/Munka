<?php


class DocAgent {
    const ASSET_DOCTOR_PHOTO                = "orvosphoto";
    const ASSET_SERVICE_ILLUSTRATION_IMAGE  = "serviceimage";
    const ASSET_COVIDPASS_IMAGE             = "covidpassimage";
    const ASSET_COVIDEGS_IMAGE              = "covidegsimage";
    const ASSET_WEB_HERO                    = "webhero";
    const ASSET_WEB_GALLERY                 = "webgallery";

    const ASSET_SERVICE_DEFAULT_IMAGE       = "/images/szakter_default.jpg";
    const ASSET_DOCTOR_DEFAULT_IMAGE_MALE   = "/images/doctor_male.png";
    const ASSET_DOCTOR_DEFAULT_IMAGE_FEMALE = "/images/doctor_female.png";

    public $showDefaultAsset = false;
    public $newUploadButton  = true;

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

    public function getAssetImageURL($imageData, $full = false) {
        $extension = $imageData["tipus"];
        if ($extension == "png") {
            $extension = "jpg";
        }

        $path = self::_getAssetImagePath($imageData["id"])."{$imageData["assetid"]}_{$imageData["id"]}.{$extension}";
        if (!is_file($path)) {
            //return "";
        }

        if ($full) {
            return $path;
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

    public function saveLocalDoc($fileName, $fileData) {
        if (is_file($fileName)) {
            $fileSize = filesize($fileName);
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            if (in_array($extension, array("pdf","doc","xls","docx","xlsx","jpg","jpeg"))) {
                if (empty($fileData["userid"])) {
                    $reservationData = sql_fetch_array(sql_query("select paciensid from foglalasok where id=?", [$fileData["fid"]]));
                    $fileData["userid"] = $reservationData["paciensid"];
                }

                sql_query("insert into dokumentumok set 
                    foglalasid=?, userid=?, megnev=?, filename=?, size=?, tipus=?, datum=now(), kod=SHA1(MD5(CONCAT(NOW(),RAND()*20000)))",
                    [$fileData["fid"], $fileData["userid"], pathinfo($fileName, PATHINFO_BASENAME), pathinfo($fileName, PATHINFO_BASENAME), $fileSize, $extension]);
                $id = sql_insert_id();

                $destinationFile = $this->_getDocPath($id);
                rename($fileName, $destinationFile);
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
                sql_query("insert into dokumentumok set datum=now(), assetid=?, dataid=?, filename=?, tipus=?, size=?, kod=?", [$tipus, $oid, $fileName, $extension, $fileSize, md5(date("YmdHis")."code1").md5(date("YmdHis")."code2")]);
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
                if (in_array($tipus, [self::ASSET_COVIDPASS_IMAGE, self::ASSET_COVIDEGS_IMAGE, self::ASSET_WEB_HERO, self::ASSET_WEB_GALLERY])) {
                    $scale = [1600, 1600];
                }

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
                $success = true;
            }

            if (in_array($extension, ["pdf"])) {
                sql_query("insert into dokumentumok set datum=now(), assetid=?, dataid=?, filename=?, tipus=?, size=?, kod=?", [$tipus, $oid, $fileName, $extension, $fileSize, md5(date("YmdHis")."code1").md5(date("YmdHis")."code2")]);
                $fileId = sql_insert_id();
                $path = $this->_getAssetImagePath($fileId);

                $kepfile = "{$path}/{$tipus}_{$fileId}.{$extension}";
                @move_uploaded_file($uploadedFile["tmp_name"], $kepfile);

                //$result["error"] = "Pdf feltöltése egyelőre nem lehetséges!";
                $success = true;
            }

            if (!isset($success)) {
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

        $uploadButton= "<div style='display:inline-block;vertical-align: top;'>";
        $uploadButton.= "<div class='upload-btn-wrapper'><div class='upbtn' style='display:table-cell;height:120px;vertical-align: center;'>Fotó feltöltése</div><input style='height:120px;' data-tipus='{$tipus}' data-id='{$dataId}' type='file' class='assetphotofile' name='assetphotofile' /></div><img id='ajaxloader_{$tipus}_{$dataId}' style='display:none;opacity:.5;height:30px;margin-left:10px;' src='/images/loading.svg' />";
        $uploadButton.= "</div>";

        $images = sql_query("select * from dokumentumok where assetid=? and dataid=?", [$tipus, $dataId])->fetchAll(PDO::FETCH_ASSOC);
        if ($tipus == self::ASSET_COVIDPASS_IMAGE || $tipus == self::ASSET_COVIDEGS_IMAGE) {
            foreach ($images as $imageData) {
                $html.= "<div style='display:inline-block;'>";
                $html.= "<div><a target='_blank' href='index.php?showfoto={$imageData["id"]}&c={$imageData["kod"]}'>Fotó megtekintése</a></div>";
                $html.= "<div style='margin-top:5px;text-align: center;'><a href='#' onclick='deleteAsset(\"{$tipus}\", {$imageData["id"]}, {$imageData["dataid"]});return false;'>Fotó törlése</a></div>";
                $html.= "</div>";

                //$html.= $uploadButton;
                return $html;
            }

        }
        foreach ($images as $imageData) {
            $photoURL = $this->getAssetImageURL($imageData)."?v=".date("YmdHis");
            $html.= "<div style='display:inline-block;'>";
            $html.= "<a href='{$photoURL}' target='_blank'><img class='assetimageitem' src='{$photoURL}' /></a>";
            $html.= "<div style='margin-top:5px;text-align: center;'><a href='#' onclick='deleteAsset(\"{$tipus}\", {$imageData["id"]}, {$imageData["dataid"]});return false;'>Kép törlése</a></div>";
            $html.= "</div>";
        }

        $html.= $uploadButton;

        return $html;
    }

    public function getAssetsByType($tipus, $dataId):array {
        $assets = [];
        $images = sql_query("select id, assetid, tipus, filename from dokumentumok where assetid=? and dataid=?", [$tipus, $dataId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($images as $imageData) {
            $imageData["url"] = $this->getAssetImageURL($imageData);
            $assets[] = $imageData;
        }

        if (empty($imageData) && $this->showDefaultAsset) {
            if ($tipus == self::ASSET_SERVICE_ILLUSTRATION_IMAGE) {
                $imageData["url"] = self::ASSET_SERVICE_DEFAULT_IMAGE;
                $assets[] = $imageData;
            }
            if ($tipus == self::ASSET_DOCTOR_PHOTO) {
                $doctorData = sql_query("select gender from orvosok where id=?", [$dataId])->fetch(PDO::FETCH_ASSOC);
                if ($doctorData["gender"] == 1) {
                    $imageData["url"] = self::ASSET_DOCTOR_DEFAULT_IMAGE_MALE;
                } else {
                    $imageData["url"] = self::ASSET_DOCTOR_DEFAULT_IMAGE_FEMALE;
                }
                $assets[] = $imageData;
            }
        }

        return $assets;
    }

    public function outputAsset($id, $code) {
        if ($asset = sql_query("select * from dokumentumok where id=? and kod=?", [$id, $code])->fetch(PDO::FETCH_ASSOC)) {
            $photoPath = $this->getAssetImageURL($asset, true);

            if (!is_file($photoPath)) {
                $photoPath = str_replace(".jpg", ".jpeg", $photoPath);
            }
            if (!is_file($photoPath)) {
                die("A kep nem talalhato, valoszínuleg torolve lett!");
            }

            header('Content-Disposition: inline; filename="covidPassPhoto'.$asset["id"].'.'.$asset["tipus"]);

            if ($asset["tipus"] == "pdf") {
                header("Content-Type: application/pdf");
            }
            if ($asset["tipus"] == "jpg" || $asset["tipus"] == "jpeg") {
                header("Content-Type: image/jpeg");
            }
            if ($asset["tipus"] == "png") {
                header("Content-Type: image/png");
            }

            echo file_get_contents($photoPath);
        } else {
            die("A kep nem talalhato, valoszinuleg torolve lett!");
        }
        die;
    }

}

