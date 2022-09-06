<?php

use mikehaertl\pdftk\Pdf;

class AdminVizsgalatilapokPage extends AdminCorePage
{
    public function __construct()
    {
        parent::__construct();


        if (isset($_REQUEST["addvizsglapfiles"])) {
            $return = ["success" => "", "error" => "", "html" => "", "debug" => ""];

            $number = 0;
            foreach ($_FILES as $file) {
                $result = $this->processUploadedFile($file);

                $return["debug"] .= $result["debug"];
                if (!empty($result["error"])) {
                    $return["error"] = $result["error"];
                    break;
                } else {
                    $number++;
                    $return["success"] = "{$number} file feltöltése sikerült!";
                }
            }

            //$return["html"] = $this->displayCalendarDayBox($day);
            $this->utils->jsonOut($return);
        }



        $GLOBALS["javascript"][] = "vizsglap.js";
    }

    public function showPage()
    {
        if (!$this->adminUser->statAccess()) {
            echo "nincs jogosultságod!";
            return;
        }

        echo "<div id='debugarea'></div>";


        echo "<div id='uploadarea'>";
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo "<div class='upload-btn-wrapper'><a href='#' onclick='return false;' class='uploadbutton'>PDF(ek) feltöltése</a><input type='file' id='vizsglapfile' class='vizsglapfile' name='vizsglapfile[]' multiple /></div>";
        echo "</div>";
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo "<div><img id='loader' style='display:none;opacity:.5;height:25px;margin-left:10px;' src='/images/loading_transparent.svg' /></div>";
        echo "</div>";
        echo "</div>";


        //echo "<div id='dailystattable'>";
        //echo $this->service->displayCalendar($_SESSION["dailystatoffset"]);
        //echo "</div>";

        echo "<div id='debugcontainer'></div>";
    }


    private function processUploadedFile($uploadedFile) {
        $result = ["error" => "", "debug" => ""];

        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        if (is_uploaded_file($uploadedFile["tmp_name"])) {
            $fileName = strtolower($uploadedFile["name"]);
            $fileSize = $uploadedFile["size"];
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($extension, ["pdf"])) {
                $tempFile = Booking_Constants::DOCUMENT_PATH.session_id().".{$extension}";
                @move_uploaded_file($uploadedFile["tmp_name"], $tempFile);

                $pdf = new mikehaertl\pdftk\Pdf($tempFile);
                $data = $pdf->getDataFields();
                $arr = json_encode($data->__toArray(), JSON_PRETTY_PRINT);

                $result["debug"].= "<pre>".$arr."</pre>";

                //if (true) {
                //    return "{$fileName}";
                //}

                if (false) {
                    $result["error"] = "A feltöltött file-t nem sikerült beazonosítani, vagy erre a napra nincsenek benne adatok";
                }
            } else {
                $result["error"] = "A feltöltött file formátuma nem megfelelő (csak pdf-et lehet feltölteni)";
            }
        } else {
            $result["error"] = "Nincs feltöltött file!";
        }

        return $result;
    }

}

