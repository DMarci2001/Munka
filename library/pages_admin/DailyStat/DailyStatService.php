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
            sleep(1);
            $result = $this->generateDailyStat($_POST["day"]);
            $_SESSION["lastgeneratedstat"]["finalresult"] = json_encode($result["result"]);
            $_SESSION["lastgeneratedstat"]["dokirexvizsgalatokresult"] = json_encode($this->getDokirexVizsgalatok($_POST["day"]));
            $_SESSION["lastgeneratedstat"]["beosztasresult"] = json_encode(WorkScheduleService::getDailySchedule($_POST["day"]));
            $utils->jsonOut($result);
        }

        if (isset($_REQUEST["downloaddailystatfile"])) {
            if (empty($_SESSION["lastgeneratedstat"])) {
                die("error 88444");
            }
            $data = $_SESSION["lastgeneratedstat"];

            $excelService = new ExcelService();
            $excelService->napiStat($data);
            $excelService->setFileName(Booking_Constants::COMPANY_NAME_SHORT." napi statisztika " . date("Y-m-d", strtotime($_REQUEST["downloaddailystatfile"])) . ".xlsx");
            $excelService->outputSpreadSheet();
        }

        if (isset($_REQUEST["getdailystateditor"])) {
            $day = $_POST["day"];
            $result["error"] = "";
            $result["html"] = $this->dailyStatEditor($day);
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

            $return["html"] = $this->displayCalendarDayBox($day);
            $utils->jsonOut($return);
        }

        if (isset($_GET["movemonth"])) {
            $_SESSION["dailystatoffset"] += intval($_GET["movemonth"]);
            echo $this->displayCalendar($_SESSION["dailystatoffset"]);
            die;
        }

    }

    /*
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
    */

    /*
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
    */

    private function processUploadedFile($uploadedFile, $day) {

        error_reporting(E_ALL);
        ini_set('display_errors', 1);


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


                //zeusz vizsgálatok + dokirex excel
                $sheet = $spreadsheet->getSheet(0);
                $days = [];

                if (!empty($sheet)) {
                    $testCell = $sheet->getCell("A1")->getValue();
                    if ($testCell == "Vizsgalat/UtolsoModositasDatuma") {
                        //$result = [];
                        $rowNr = 2;
                        while (true) {
                            $datum = $sheet->getCell("A{$rowNr}")->getFormattedValue();

                            if (empty($datum)) {
                                break;
                            }

                            $datum = str_replace(".", "-", $datum);

                            $row = [
                                "datum" => date("Y-m-d H:i:d", strtotime($datum)),
                                "nev" => $sheet->getCell("B{$rowNr}")->getValue(),
                                "szakrendeles" => $sheet->getCell("C{$rowNr}")->getValue(),
                                "orvos" => $sheet->getCell("D{$rowNr}")->getValue(),
                                "paciensid" => $sheet->getCell("E{$rowNr}")->getValue(),
                                "szuldatum" => $sheet->getCell("F{$rowNr}")->getValue(),
                                "telephely" => $sheet->getCell("G{$rowNr}")->getValue(),
                                "munkakor" => $sheet->getCell("H{$rowNr}")->getValue(),
                                "korlatozas" => $sheet->getCell("I{$rowNr}")->getValue(),
                                "alkalmassag" => $sheet->getCell("J{$rowNr}")->getValue(),
                                "ervenyesseg" => date("Y-m-d H:i:d", strtotime($sheet->getCell("K{$rowNr}")->getValue()))
                            ];

                            sql_query("delete from dokirex_vizsgalatok where datum=? and orvos=?", [$row["datum"], $row["orvos"]]);
                            sql_query("insert into dokirex_vizsgalatok set 
                                    datum=:datum, nev=:nev,
                                    szakrendeles=:szakrendeles, orvos=:orvos,
                                    paciensid=:paciensid,szuldatum=:szuldatum, telephely=:telephely, munkakor=:munkakor, 
                                    korlatozas=:korlatozas, alkalmassag=:alkalmassag, ervenyesseg=:ervenyesseg", $row);

                            $rowNr++;
                        }
                    }
                    return "";
                }

                return "A feltöltött file-t nem sikerült beazonosítani, vagy erre a napra nincsenek benne adatok";
            } else {
                return "A feltöltött file formátuma nem megfelelő (csak excel .xlsx dokumentumot lehet feltölteni)";
            }
        } else {
            return "Nincs feltöltött file!";
        }
    }

    private function getDokirexVizsgalatok($from, $to = ""):array {
        if (empty($to)) {
            $to = $from;
        }

        $from = date("Y-m-d 00:00:00", strtotime($from));
        $to = date("Y-m-d 23:59:59", strtotime($to));

        return sql_query("select * from dokirex_vizsgalatok where datum>=? and datum<=?", [$from, $to])->fetchAll(PDO::FETCH_ASSOC);
    }


    private function displayCalendarDayBox($day):string {
        $html = "";

        $napClass = "calday";
        $diff = (strtotime($day) - strtotime(date("Y-m-d"))) / (3600*24);

        $boxStyle = ($diff == 0?"background:#f0e0e0":"");

        $html.= "<div class='monthlycell' style='{$boxStyle}'>";
        $html.= "<div style='text-align: center;font-weight: bold;font-size: 22px;'><div title='' onclick='alert(\"{$day}\");' class='{$napClass}'>".date("d", strtotime($day))."</div></div>";

        if (strtotime("now") > strtotime($day)) {
            $html .= "<div data-day='{$day}' id='daytext{$day}' style='padding-top:0px;'>";

            $dokirex = $this->getDokirexVizsgalatok($day);
            $workers = [];
            $beosztas = WorkScheduleService::getDailySchedule($day);
            foreach ($beosztas as $beo) {
                $workers[] = $beo["workername"];
            }


            $html .= "<div><img id='dailystatloader{$day}' style='display:none;opacity:.5;height:30px;margin-top:10px;' src='/images/loading_transparent.svg' /></div>";

            $html .= "<div id='datablock{$day}' style='margin-top:10px;'>";
            $html .= "<div style='height:70px;overflow: hidden;'>";
            $html .= "<div>" . (empty($this->getDokirexVizsgalatok($day)) ? "dokirex vizsgálatok  <i style='color:red' class='fas fa-times-circle'></i>" : count($dokirex)." dokirex vizsgálat <i style='color:green' class='fas fa-check-circle'></i>") . "</div>";
            $html .= "<div>" . (empty($beosztas) ? "beosztás <i style='color:red' class='fas fa-times-circle'></i>" : "<span title='".implode(", ", array_unique($workers))."'>".count(array_unique($workers))." dolgozó</span> <i style='color:green' class='fas fa-check-circle'></i>") . "</div>";
            $html .= "</div>";
            $html .= "<div style='margin-top:10px;'>";
            $html .= "<div class='dailysmallbutton' onclick='downloadDailyStat(\"$day\")' title='Napi statisztika letöltése'><i class='fas fa-file-download'></i></div> ";
            $html .= "</div>";
            $html .= "</div>";

            $html .= "</div>";
        }
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
            $html.= "<td class='monthlycell mtweekday' style=''>".$this->weekDays[$i]."</td>";
        }
        $html.= "</tr><tr>";

        for ($i = 1; $i < $firstDay ;$i++) {
            $weekDay++;
            $html.= "<td class='monthlycell' style='background: white;'>&nbsp;</td>";
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


    private function generateDailyStat($day):array {
        $result = [
            "error" => "",
            "debughtml" => "",
            "result" => ""
        ];

        $dailyStatData = [];

        $szakrendelesek = $orvosok = [];

        $dokirexData = $this->getDokirexVizsgalatok($day);
        $beosztasData = WorkScheduleService::getDailySchedule($day);

        foreach ($dokirexData as $vizsgalat) {
            $szakrendelesek[] = $vizsgalat["szakrendeles"];
            $orvosok[] = $vizsgalat["orvos"];
        }

        $szakrendelesek = array_unique($szakrendelesek);
        $orvosok = array_unique($orvosok);

        foreach ($szakrendelesek as $szakrendeles) {
            $paciensekDb      = 0;
            $szakterOsszesIdo = 0;
            $normaIdoMinute   = 15;

            foreach ($dokirexData as $vizsgalat) {
                if ($vizsgalat["szakrendeles"] == $szakrendeles) {
                    $paciensekDb++;
                }
            }

            $szakrendelesOrvos = [];

            foreach ($orvosok as $orvos) {
                $orvosPaciensekDb = $osszesido = 0;
                $minTime = "{$day} 23:59:59";
                $maxTime = "{$day} 00:00:00";
                $beoTime = "nincs megadva";
                $nover = "";
                $orvosBeosztas = [];

                foreach ($dokirexData as $vizsgalat) {
                    if ($vizsgalat["orvos"] == $orvos && $vizsgalat["szakrendeles"] == $szakrendeles) {
                        $orvosPaciensekDb++;
                        if (strtotime($minTime) > strtotime($vizsgalat["datum"])) {
                            $minTime = $vizsgalat["datum"];
                        }
                        if (strtotime($maxTime) < strtotime($vizsgalat["datum"])) {
                            $maxTime = $vizsgalat["datum"];
                        }
                    }
                }

                if ($orvosPaciensekDb > 0) {
                    $osszesido = (strtotime($maxTime) - strtotime($minTime)) + ($normaIdoMinute*60);
                    $atlagido = $osszesido / $orvosPaciensekDb;

                    foreach ($beosztasData as $beosztas) {
                        if ($beosztas["workername"] == $orvos) {
                            $orvosBeosztas[] = $beosztas;
                        }
                    }

                    if (!empty($orvosBeosztas)) {
                        $beo = $orvosBeosztas[0];
                        $beoTime = date("H:i", strtotime($beo["datumfrom"])) . " - " . date("H:i", strtotime($beo["datumto"]));
                    }

                    $szakrendelesOrvos[$orvos] = [
                        "name" => $orvos,
                        "db" => $orvosPaciensekDb,
                        "nover" => $nover,
                        "mintime" => $minTime,
                        "maxtime" => $maxTime,
                        "osszesido" => round($osszesido/60, 2),
                        "atlagido" => round($atlagido/60, 2),
                        "beo" => $beoTime
                    ];
                }

                $szakterOsszesIdo += $osszesido;
            }


            $dailyStatData["day"] = $day;
            $dailyStatData["szakrendelesek"][$szakrendeles] = [
                "name" => $szakrendeles,
                "db" => $paciensekDb,
                "normaido" => $normaIdoMinute,
                "osszesido" => round($szakterOsszesIdo/60 ,2),
                "atlagido" => round(($szakterOsszesIdo / $paciensekDb) / 60, 2),
                "orvosok" => $szakrendelesOrvos
            ];
        }

        //sql_query("update dailystat set finalresult=? where day=?", [json_encode($dailyStatData, JSON_PRETTY_PRINT), $day]);

        $result["result"] = $dailyStatData;
        $result["debughtml"] = "<pre>".print_r($dailyStatData, true)."</pre>";

        return $result;
    }
}