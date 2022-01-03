<?php

class SalaryCalculator {

    public $manualNumberOfPatients = 0;
    public $manualNumberOfHours = 0;

    public $salaryTypes = [
        "monthly" => [
            "description" => "Havi fix fizetés",
            "tag" => "/hó",
            "monthly" => true,
            "daily" => false
        ],
        "daily" => [
            "description" => "Napi fix juttatás",
            "tag" => "/nap",
            "monthly" => false,
            "daily" => true
        ],
        "perpatient" => [
            "description" => "Kezelésenkénti fix juttatás",
            "tag" => "/fő",
            "monthly" => false,
            "daily" => false
        ],
        "perhour" => [
            "description" => "Órabér",
            "tag" => "/óra",
            "monthly" => false,
            "daily" => false
        ],
        "onetime" => [
            "description" => "Egyszeri juttatás",
            "tag" => "alkalmi kifizetés",
            "monthly" => false,
            "daily" => false
        ]
    ];

    public function __construct() {



    }

    public function getDoctorSalary($orvosId, $dateFrom, $dateTo) {
        $salary = [];

        if (!ctype_digit($orvosId)) {
            if ($orvosData = sql_query("select id from orvosok where nev=?", [$orvosId])->fetch(PDO::FETCH_ASSOC)) {
                $orvosId = $orvosData["id"];
            } else {
                return $salary;
            }
        }

        $salaryDatas = $this->getDoctorSalaryDatas($orvosId, $dateFrom, $dateTo);

        $date = $dateFrom;

        $monthly = $this->calcMonthly($dateFrom, $dateTo, $salaryDatas);
        if (!empty($monthly)) {
            $salary["monthly"] = $monthly;
        }

        while (strtotime($date) <= strtotime($dateTo)) {
            //echo $date." ";
            $bonus = $this->calcOneTimeBonus($date, $salaryDatas);
            if (!empty($bonus)) {
                $salary["onetime"][$date] = $bonus;
            }

            $perPatient = $this->calcPerPatient($date, $salaryDatas);
            if (!empty($perPatient)) {
                $salary["perpatient"][$date] = $perPatient;
            }

            $perHour = $this->calcPerHour($date, $salaryDatas);
            if (!empty($perHour)) {
                $salary["perhour"][$date] = $perHour;
            }

            $date = date("Y-m-d", strtotime("{$date} + 1 day"));
        }

        return $salary;
    }


    private function calcMonthly($dateFrom, $dateTo, $salaryDatas) {
        $s = [];
        foreach ($salaryDatas as $salaryData) {
            if ($salaryData["salarytype"] == "monthly") {

                $date = $dateFrom;
                $months = [];
                while (strtotime($date) <= strtotime($dateTo)) {
                    $months[] = date("Y-m", strtotime($date));
                    $date = date("Y-m-d", strtotime("{$date} + 1 day"));
                }

                foreach ($months as $month) {
                    $workDays = 0;
                    $workedDays = 0;

                    $date = $month."-01";
                    $lastDay = date("Y-m-t", strtotime($date));

                    while (strtotime($date) <= strtotime($lastDay)) {
                        if (in_array((int)date("N", strtotime($date)), [1,2,3,4,5])) {
                            $workDays++;
                            if (strtotime($date) >= strtotime($dateFrom) && strtotime($date) <= strtotime($dateTo)) {
                                $workedDays++;
                            }
                        }
                        $date = date("Y-m-d", strtotime("{$date} + 1 day"));
                    }

                    $salary = $salaryData["price"];
                    if ($workedDays < $workDays) {
                        $salary = round($salary / $workDays * $workedDays);
                    }

                    $s[$month] = [
                        "month" => $month,
                        "type" => $salaryData["salarytype"],
                        "workdays" => $workDays,
                        "workeddays" => $workedDays,
                        "unitprice" => $salaryData["price"],
                        "price" => $salary
                    ];
                }
            }
        }

        return $s;
    }

    private function calcOneTimeBonus($date, $salaryDatas) {
        $s = [];
        foreach ($salaryDatas as $salaryData) {
            if ($salaryData["salarytype"] == "onetime") {
                if (strtotime($date) == strtotime($salaryData["datefrom"])) {
                    $s[] = [
                        "date" => $date,
                        "type" => $salaryData["salarytype"],
                        "description" => $salaryData["description"] == "" ? "Bónusz":$salaryData["description"],
                        "price" => $salaryData["price"]
                    ];
                }
            }

        }

        return $s;
    }

    private function calcPerPatient($date, $salaryDatas) {
        $s = [];
        foreach ($salaryDatas as $salaryData) {
            if ($salaryData["salarytype"] == "perpatient") {
                if (strtotime($date) >= strtotime($salaryData["datefrom"]) && strtotime($date) <= strtotime($salaryData["dateto"])) {

                    if ($this->manualNumberOfPatients != 0) {
                        $numberOfPatients = $this->manualNumberOfPatients;
                    } else {
                        $reservations = $this->getDoctorReservations($salaryData["orvosid"], $date);
                        $numberOfPatients = count($reservations);
                    }

                    if ($numberOfPatients != 0) {
                        $s[] = [
                            "date" => $date,
                            "type" => $salaryData["salarytype"],
                            "unitprice" => $salaryData["price"],
                            "price" => $salaryData["price"] * $numberOfPatients,
                            "reservations" => $reservations
                        ];
                    }
                }
            }

        }

        return $s;
    }

    private function calcPerHour($date, $salaryDatas) {
        $s = [];
        foreach ($salaryDatas as $salaryData) {
            if ($salaryData["salarytype"] == "perhour") {
                if (strtotime($date) >= strtotime($salaryData["datefrom"]) && strtotime($date) <= strtotime($salaryData["dateto"])) {

                    $minTime = "{$date} 23:59:59";
                    $maxTime = "{$date} 00:00:00";


                    $numberOfHours = 0;
                    if ($this->manualNumberOfHours != 0) {
                        $numberOfHours = $this->manualNumberOfHours;
                    } else {
                        $reservations = $this->getDoctorReservations($salaryData["orvosid"], $date);
                        if (count($reservations) != 0) {
                            foreach ($reservations as $reservation) {
                                if (strtotime($minTime) > strtotime($reservation["datum"])) {
                                    $minTime = $reservation["datum"];
                                }
                                if (strtotime($maxTime) < strtotime("{$reservation["datum"]} + {$reservation["rinterval"]} minute")) {
                                    $maxTime = date("Y-m-d H:i:s", strtotime("{$reservation["datum"]} + {$reservation["rinterval"]} minute"));
                                }
                            }
                            $numberOfHours = round(((strtotime($maxTime) - strtotime($minTime)) / 3600), 1);
                        }
                    }

                    if ($numberOfHours != 0) {
                        $s[] = [
                            "date" => $date,
                            "type" => $salaryData["salarytype"],
                            "mintime" => $minTime,
                            "maxtime" => $maxTime,
                            "hour" => $numberOfHours,
                            "unitprice" => $salaryData["price"],
                            "price" => $salaryData["price"] * $numberOfHours,
                            "reservations" => $this->manualNumberOfPatients
                        ];
                    }
                }
            }

        }

        return $s;
    }


    public function getDoctorReservations($orvosId, $date) {
        return sql_query("select f.datum, f.rinterval, f.nev, f.taj, c.megnev as cegnev, t.megnev as szurestipusnev from foglalasok f
            left join szurestipusok t on t.id = f.szurestipusid
            left join cegek c on c.id = f.cegid
            where f.orvosassigned=? and f.datum>=? and f.datum<=? and f.aktiv=1 order by f.datum", [$orvosId, "{$date} 00:00:00", "{$date} 23:59:59"])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDoctorSalaryDatas($orvosId) {
        return sql_query("select * from salarydata where orvosid=?", [$orvosId])->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getAllSalaryDataForDay($day) {
        return sql_query("SELECT d.*, o.nev AS orvosnev FROM salarydata d 
            LEFT JOIN orvosok o ON o.id=d.orvosid
            WHERE (d.salarytype IN ('perhour', 'monthly', 'daily', 'perpatient') AND d.datefrom<=:day AND d.dateto>=:day) OR d.salarytype='onetime' AND d.datefrom=:day ORDER BY o.nev ", ["day" => $day])->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getSalaryText($salaryData) {
        $salaryItems = ["total" => 0, "text" => []];
        foreach ($salaryData as $key => $salaryItem) {
            if ($key == "monthly") {
                foreach ($salaryItem as $month => $item) {
                    $salaryItems["text"][] = $this->moneyFormat($item["price"]) . " Ft (" . $this->moneyFormat($item["unitprice"]) . " Ft/hó)";
                    $salaryItems["total"] += $item["price"];
                }
            }

            if ($key == "perhour") {
                foreach ($salaryItem as $day => $items) {
                    foreach ($items as $item) {
                        $salaryItems["text"][] = $this->moneyFormat($item["price"]) . " Ft (" . $this->moneyFormat($item["unitprice"]) . " Ft/óra)";
                        $salaryItems["total"] += $item["price"];
                    }
                }
            }

            if ($key == "perpatient") {
                foreach ($salaryItem as $day => $items) {
                    foreach ($items as $item) {
                        $salaryItems["text"][] = $this->moneyFormat($item["price"]) . " Ft (" . $this->moneyFormat($item["unitprice"]) . " Ft/paciens)";
                        $salaryItems["total"] += $item["price"];
                    }
                }
            }

            if ($key == "onetime") {
                foreach ($salaryItem as $day => $items) {
                    foreach ($items as $item) {
                        $salaryItems["text"][] = $this->moneyFormat($item["price"]) . " Ft";
                        $salaryItems["total"] += $item["price"];
                    }
                }
            }
        }

        if (empty($salaryItems["text"])) {
            $salaryItems["text"][] = "nincsenek megadva fizetési adatok";
        }

        return $salaryItems;
    }

    private function moneyFormat($num) {
        return number_format($num);
    }
}