<?php

class SalaryCalculator {


    public $salaryTypes = [
        "monthly" => [
            "description" => "Havi fix fizetés",
            "monthly" => true,
            "daily" => false
        ],
        "daily" => [
            "description" => "Napi fix juttatás",
            "monthly" => false,
            "daily" => true
        ],
        "perpatient" => [
            "description" => "Kezelésenkénti fix juttatás",
            "monthly" => false,
            "daily" => false
        ],
        "perhour" => [
            "description" => "Órabér",
            "monthly" => false,
            "daily" => false
        ],
        "onetime" => [
            "description" => "Egyszeri juttatás",
            "monthly" => false,
            "daily" => false
        ]
    ];

    public function __construct() {



    }

    public function getDoctorSalary($orvosId, $dateFrom, $dateTo) {
        $salary = [];

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

                    $reservations = $this->getDoctorReservations($salaryData["orvosid"], $date);

                    if (count($reservations) != 0) {
                        $s[] = [
                            "date" => $date,
                            "type" => $salaryData["salarytype"],
                            "unitprice" => $salaryData["price"],
                            "price" => $salaryData["price"] * count($reservations),
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

                    $reservations = $this->getDoctorReservations($salaryData["orvosid"], $date);

                    foreach ($reservations as $reservation) {
                        if (strtotime($minTime) > strtotime($reservation["datum"])) {
                            $minTime = $reservation["datum"];
                        }
                        if (strtotime($maxTime) < strtotime("{$reservation["datum"]} + {$reservation["rinterval"]} minute")) {
                            $maxTime = date("Y-m-d H:i:s", strtotime("{$reservation["datum"]} + {$reservation["rinterval"]} minute"));
                        }
                    }

                    if (count($reservations) != 0) {
                        $hour = round(((strtotime($maxTime) - strtotime($minTime)) / 3600), 1);
                        $s[] = [
                            "date" => $date,
                            "type" => $salaryData["salarytype"],
                            "mintime" => $minTime,
                            "maxtime" => $maxTime,
                            "hour" => $hour,
                            "unitprice" => $salaryData["price"],
                            "price" => $salaryData["price"] * $hour,
                            "reservations" => $reservations
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


}