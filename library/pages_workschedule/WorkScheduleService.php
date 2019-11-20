<?php


class WorkScheduleService {

    public $weekStart;


    public $scheduleMapping = [];


    function __construct()
    {

        $res = sql_query("SELECT m.*,w.nev AS workernev, n.nev AS novernev FROM schedule_mapping m
        LEFT JOIN schedule_workers w ON m.`workerid`=w.`id`
        LEFT JOIN schedule_workers n ON m.`noverid`=n.`id`
        where datumfrom > date_sub(now(), interval 7 day)");
        while ($row = sql_fetch_array($res)) {
            $key = date("Y-m-d", strtotime($row["datumfrom"]))."_{$row["napszak"]}_{$row["tipusid"]}";
            $this->scheduleMapping[$key][] = $row;
         }

    }

}