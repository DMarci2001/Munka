<?php

class DailyStatService {
    private $months = ["","január","február","március","április","május","június","július","augusztus","szeptember","október","november","december"];
    private $weekDays = ["","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap"];
    private $weekDaysShort = ["","h","k","sz","cs","p","sz","v"];

    public function __construct() {
        $utils = new Utils();

        if (!isset($_SESSION["dailystatoffset"])) {
            $_SESSION["dailystatoffset"] = 0;
        }

        if (isset($_REQUEST["downloaddailystat"])) {
            //$day = $_POST["day"];
            $result["error"] = "Fejlesztés alatt...";
            $utils->jsonOut($result);
        }

        if (isset($_REQUEST["getdailystateditor"])) {
            $day = $_POST["day"];
            $result["error"] = "";
            $result["html"] = $this->dailyStatEditor($day);
            $utils->jsonOut($result);
        }

        if (isset($_REQUEST["generatedailystat"])) {
            $day = $_POST["day"];

            $result["error"] = "Fejlesztés alatt...";
            $result["html"] = $this->displayCalendarDayBox($day);
            $utils->jsonOut($result);
        }

        if (isset($_POST["deletedailystat"])) {
            $day = $_POST["day"];

            sql_query("delete from dailystat where day=?", [$day]);

            $result["html"] = $this->displayCalendarDayBox($day);
            $utils->jsonOut($result);
        }


        if (isset($_REQUEST["savedailystat"])) {
            $day = $_POST["day"];

            sql_query("update dailystat set beosztasresult=?, recepcioresult=?, dokirexvizsgalatokresult=?, dokirexszamlakresult=?, zeuszresult=? where day=?",
                [$_POST["beosztasresult"], $_POST["recepcioresult"], $_POST["dokirexvizsgalatokresult"], $_POST["dokirexszamlakresult"], $_POST["zeuszresult"], $day]);

            $result["html"] = $this->displayCalendarDayBox($day);
            $utils->jsonOut($result);
        }

        if (isset($_REQUEST["adddailystatfiles"])) {
            $return = ["error" => ""];
            $day = $_REQUEST["day"];

            foreach ($_FILES as $file) {
                $result = $this->processUploadedFile($file, $day);
                if (!empty($result)) {
                    $return["error"] = $result;
                    break;
                }
            }

            $this->beosztasQuery($day);

            $return["html"] = $this->displayCalendarDayBox($day);
            $utils->jsonOut($return);
        }

        if (isset($_GET["movemonth"])) {
            $_SESSION["dailystatoffset"] += intval($_GET["movemonth"]);
            echo $this->displayCalendar($_SESSION["dailystatoffset"]);
            die;
        }

    }

    private function dailyStatEditor($day) {
        $html = "";
        $html.= "<div class='dailybutton' onclick=\"backToDailyCalendar();\" title='Letöltés'><i class='fas fa-arrow-left'></i> Vissza</div>&nbsp;";
        $html.= "<div class='dailybutton' onclick=\"saveDailyCalendar('{$day}');\" style='background: #0ca3c9;' title='Letöltés'><i class='fas fa-save'></i> Mentés</div>";
        $html.= "<div style='margin-top:20px;'>";

        $dayData = $this->getDayData($day);

        $html.= "<h2>{$day} feltöltött adatai</h2>";

        $html.= "<form method='post' id='dayform'><input type='hidden' name='day' value='{$day}' />";
        $html.= $this->jsonEditor($dayData, "dokirexvizsgalatokresult", "DOKIREX VIZSGÁLATOK");
        $html.= $this->jsonEditor($dayData, "dokirexszamlakresult", "DOKIREX SZÁMLÁK");
        $html.= $this->jsonEditor($dayData, "zeuszresult", "ZEUSZ VIZSGÁLATOK");
        $html.= $this->jsonEditor($dayData, "beosztasresult", "BEOSZTÁS");
        $html.= $this->jsonEditor($dayData, "recepcioresult", "RECEPCIÓ");
        $html.= "</form>";

        $html.= "</div>";
        return $html;
    }


    private function jsonEditor($dayData, $field, $title) {
        $html = "";
        $data = json_decode($dayData[$field], JSON_OBJECT_AS_ARRAY);

        $class = "dailyeditorhead";
        $headTitle = "{$title} - ".count($data)." sor";
        if (empty($data)) {
            $class.= " dailyeditorheaderror";
            $headTitle = "{$title} - nincs feltöltve, vagy hibás adatok";
        }

        $html.= "<div style='display: inline-block;padding: 0px 10px 10px 0px;'>";
        $html.= "<div class='{$class}'>{$headTitle}</div>";
        $html.= "<textarea id='{$field}' name='{$field}' style='width:700px;height:500px;'>{$dayData[$field]}</textarea>";
        $html.= "</div>";
        return $html;
    }

    private function processUploadedFile($uploadedFile, $day) {
        if (is_uploaded_file($uploadedFile["tmp_name"])) {
            $ok = false;
            $fileName = strtolower($uploadedFile["name"]);
            $fileSize = $uploadedFile["size"];
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);

            if (in_array($extension, array("xls","xlsx"))) {
                $tempFile = Booking_Constants::DOCUMENT_PATH.session_id().".{$extension}";
                @move_uploaded_file($uploadedFile["tmp_name"], $tempFile);

                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                //$reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($tempFile);

                $sheetName = date("Y.m.d.", strtotime($day));
                //$sheetName = "2021.09.24.";

                $sheet = $spreadsheet->getSheetByName($sheetName);
                if (!empty($sheet)) {
                    $testCell = $sheet->getCell("A1")->getValue();

                    //recepció xls
                    if ($testCell == "Név") {
                        $sheet = $spreadsheet->getSheetByName($sheetName);
                        $result = [];
                        $rowNr = 2;
                        while (true) {
                            $nev = $sheet->getCell("A{$rowNr}")->getValue();
                            if (empty($nev)) {
                                break;
                            }

                            $vizsgalat = $sheet->getCell("D{$rowNr}")->getValue();
                            $row = [
                                "nev" => $nev,
                                "taj" => $sheet->getCell("B{$rowNr}")->getValue(),
                                "ceg" => $sheet->getCell("C{$rowNr}")->getValue(),
                                "vizsgalat" => $vizsgalat,
                                "vizsgalta" => $sheet->getCell("E{$rowNr}")->getValue(),
                                "mrtglabor" => $sheet->getCell("F{$rowNr}")->getValue(),
                                "mrtg" => (substr_count($vizsgalat, "rtg") ? 1 : 0),
                                "alk" => (substr_count($vizsgalat, "alk") ? 1 : 0),
                                "uh" => (substr_count($vizsgalat, "uh") || substr_count($vizsgalat, "ultrahang") ? 1 : 0),
                                "covid" => (substr_count($vizsgalat, "covid") ? 1 : 0),
                                "labor" => (substr_count($vizsgalat, "labor") ? 1 : 0)
                            ];
                            $result[] = $row;
                            $rowNr++;
                        }

                        $this->insertDayIfNotExist($day);
                        sql_query("update dailystat set recepcioresult=? where day=?", [json_encode($result, JSON_PRETTY_PRINT), $day]);
                        return "";
                    }
                }

                return "A feltöltött file-t nem sikerült beazonosítani, vagy erre a napra nincsenek benne adatok";
            } else {
                return "A feltöltött file formátuma nem megfelelő (csak excel .xlsx dokumentumot lehet feltölteni)";
            }
        } else {
            return "Nincs feltöltött file!";
        }
    }


    private function displayCalendarDayBox($day):string {
        $html = "";

        $napClass = "calday";
        $diff = (strtotime($day) - strtotime(date("Y-m-d"))) / (3600*24);

        $boxStyle = ($diff == 0?"background:#f0e0e0":"");

        $html.= "<div class='monthlycell' style='{$boxStyle}'>";
        $html.= "<div style='text-align: center;font-weight: bold;font-size: 16px;'><div title='' onclick='alert(\"{$day}\");' class='{$napClass}'>".date("d", strtotime($day))."</div></div>";

        $html.= "<div data-day='{$day}' id='daytext{$day}' style='padding-top:0px;'>";

        $dayData = $this->getDayData($day);

        $html.= "<div class='upload-btn-wrapper'><a href='#' onclick='return false;' class=''>Feltöltés</a><input data-day='{$day}' type='file' id='dailystatfile' class='dailystatfile' name='dailystatfile[]' multiple /></div>";
        $html.= "<div><img id='dailystatloader{$day}' style='display:none;opacity:.5;height:30px;margin-top:10px;' src='/images/loading_transparent.svg' /></div>";

        if (isset($dayData["id"])) {
            $html .= "<div id='datablock{$day}'>";
            $html .= "<div>dokirex vizsgálatok" . (empty($dayData["dokirexvizsgalatokresult"]) ? "":" <i style='color:green' class='fas fa-check-circle'></i>") . "</div>";
            $html .= "<div>dokirex számlák" . (empty($dayData["dokirexszamlakresult"]) ? "":" <i style='color:green' class='fas fa-check-circle'></i>") . "</div>";
            $html .= "<div>zeusz vizsgálatok" . (empty($dayData["zeuszresult"]) ? "":" <i style='color:green' class='fas fa-check-circle'></i>") . "</div>";
            $html .= "<div>beosztás" . (empty($dayData["beosztasresult"]) ? "":" <i style='color:green' class='fas fa-check-circle'></i>") . "</div>";
            $html .= "<div>recepció" . (empty($dayData["recepcioresult"]) ? "":" <i style='color:green' class='fas fa-check-circle'></i>") . "</div>";
            $html.= "<div style='margin-top:10px;'>";
            $html.= "<div class='dailysmallbutton' onclick='downloadDailyStat(\"$day\")' title='Letöltés'><i class='fas fa-file-download'></i></div> ";
            $html.= "<div class='dailysmallbutton' onclick='editDailyStat(\"$day\")' title='Szerkesztés'><i class='fas fa-pen-square'></i></div> ";
            $html.= "<div class='dailysmallbutton' onclick='generateDailyStat(\"$day\")' title='Újragenerálás'><i class='fas fa-sync-alt'></i></div> ";
            $html.= "<div class='dailysmallbutton' onclick='deleteDailyStat(\"$day\")' title='Napi statisztika törlése'><i class='fas fa-trash-alt'></i></div> ";
            $html.= "</div>";
            $html.= "</div>";
        }

        $html.= "</div>";
        $html.= "</div>";

        return $html;
    }

    public function displayCalendar($offset):string {
        $html         = "";
        $now          = date("Y-m-01");
        $year         = date("Y",strtotime("{$now} +{$offset} month"));
        $month        = intval(date("n",strtotime("{$now} +{$offset} month")));
        $monthText    = date("F",strtotime("{$now} +{$offset} month"));
        $numberOfDays = date("t",strtotime("{$now} +{$offset} month"));
        $firstDay     = date("N",strtotime("first day of {$year} {$monthText}"));
        $weekDay      = 0;

        $html.= "<table class='montlytable' style='margin:0px;padding:0px;'>";
        $html.= "<tr>";
        $html.= "<td colspan='1' class='montlycell mthead' style='text-align: left;'><a href='#' onclick='DailyStatMoveMonth(-1);return false;'><i class='fas fa-chevron-circle-left'></i></a></td>";
        $html.= "<td colspan='5' class='montlycell mthead'>&nbsp;&nbsp;{$year} ".$this->months[$month]."&nbsp;&nbsp;</td>";
        $html.= "<td colspan='1' class='montlycell mthead' style='text-align: right;'><a href='#' onclick='DailyStatMoveMonth(1);return false;'><i class='fas fa-chevron-circle-right'></i></a></td>";
        $html.= "</tr>";
        $html.= "<tr>";
        for ($i = 1; $i <= 7 ;$i++) {
            $html.= "<td class='monthlycell mtweekday'>".$this->weekDays[$i]."</td>";
        }
        $html.= "</tr><tr>";

        for ($i = 1; $i < $firstDay ;$i++) {
            $weekDay++;
            $html.= "<td class='monthlycell'>&nbsp;</td>";
        }

        for ($nap = 1; $nap <= $numberOfDays; $nap++) {
            if ($weekDay++ >= 7) {
                $html.= "</tr><tr>";
                $weekDay = 1;
            }

            $thisDay = "{$year}-".substr("00{$month}",-2)."-".substr("00{$nap}",-2);

            $html.= "<td id='daybox{$thisDay}'>";
            $html.= $this->displayCalendarDayBox($thisDay);
            $html.= "</td>";
        }

        $html.= "</tr>";
        $html.= "</table>";
        return $html;
    }

    private function insertDayIfNotExist($day) {
        if (empty($this->getDayData($day))) {
            $this->resetDay($day);
        }
    }

    private function resetDay($day) {
        $this->deleteDay($day);
        sql_query("insert into dailystat set day=?", [$day]);
    }

    private function deleteDay($day) {
        sql_query("delete from dailystat where day=?", [$day]);
    }

    private function beosztasQuery($day) {
        $data = $this->getDayData($day);

        if (empty($data["beosztasresult"])) {
            $beosztasResult = sql_query("SELECT w.`teljesnev` AS workername, t.megnev AS tipusnev, r.megnev AS rolename, m.datumfrom, m.datumto, m.tipusid, m.roleid, m.workerid, m.megj FROM schedule_mapping m
                LEFT JOIN schedule_workers w ON w.id = m.workerid
                LEFT JOIN schedule_tipusok t ON t.id = m.tipusid
                LEFT JOIN schedule_roles r ON r.id = m.roleid
                WHERE m.datumfrom>='2021-06-24 00:00:00' AND m.datumfrom<='2021-06-24 23:59:59'")->fetchAll(PDO::FETCH_ASSOC);

            sql_query("update dailystat set beosztasresult=? where day=?", [json_encode($beosztasResult, JSON_PRETTY_PRINT), $day]);
        }
    }


    private function getDayData($day) {
        return sql_query("select * from dailystat where day=?", [$day])->fetch(PDO::FETCH_ASSOC);
    }

    private function setDayStatus($day, $status) {
        sql_query("update dailystat set status=? where day=?", [json_encode($status, JSON_PRETTY_PRINT), $day]);
    }

}