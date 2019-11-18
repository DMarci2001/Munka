<?php


class WorkScheduleService {

    public $weekStart;


    public $scheduleMapping = [];


    function __construct()
    {

        $res = sql_query("select * from schedule_mapping where datumfrom > date_sub(now(), interval 7 day)");
        while ($row = sql_fetch_array($res)) {
            $key = date("Y-m-d", strtotime($row["datumfrom"]));
            $this->scheduleMapping[$key] = $row;
         }

    }

}