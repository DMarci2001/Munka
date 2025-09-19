<?php

class VacationSubPage
{
    private WorkScheduleService $service;

    public function __construct(WorkScheduleService $service)
    {
        //parent::__construct();

        $this->service = $service;


    }

    public function showPage():string {
        $adminUtils = new AdminUtils();

        $html = "";

        $html.= "<div style='padding-top:10px;'><strong>Szabadságok</strong></div>";


        $html.="<div id='workervacationsdiv' style='margin-top:5px;'>";

        for ($i = 0; $i < 7 * 52; $i++) {
            $thisDay = date("Y-m-d", strtotime("last week monday + {$i} day"));
            $weekDay = date("N", strtotime($thisDay));
            $weekNum = date("W", strtotime($thisDay));

            if ($weekDay == 1) {
                $html .= "<div style='display:table-row;'>";
                $html .= "<div style='display:table-cell;font-weight: bold;padding:4px 0px;'>{$weekNum}. hét</div>";
                $html .= "</div>";
            }
            $html .= "<div style='display:table-row;'>";
            $html .= "<div style='display:table-cell;border-top:1px solid #ccc;padding:2px 0px;'><div style='padding-right: 10px;'>" . $adminUtils->magyarDatum($thisDay, false) . " ".$adminUtils->settings->hetnap[$weekDay]."</div>";

            $html.= "<div id='szabirow{$thisDay}' style='padding-left:0px;'>";
            $html.= self::displaySzabiDayItems($thisDay);
            $html.= "</div>";


            $html .= "</div>";
            $html .= "<div style='display:table-cell;border-top:1px solid #ccc;'>";

            $html .= "<span style='cursor:pointer;padding:2px 5px;background:#56af56;color:#fff;border-radius: 2px;' data-datum='{$thisDay}' onclick='Schedule.ShowAddWorkerVacationDialog(this);return false;'><i class='fa-solid fa-plus'></i> szabi</span>";

            $html .= "</div>";
            $html .= "</div>";
        }

        $html.= "</div>";

        return $html;
    }

    public static function displaySzabiDayItems($thisDay):string {
        $html = "";
        $approveds = 0;
        $vacations = sql_query("select sz.status, datumtol, sz.oid, w.nev from schedule_szabadsag sz left join schedule_workers w on w.id=sz.oid where sz.datumtol=?", [$thisDay])->fetchAll();
        foreach ($vacations as $vacation) {
            $workerId = $vacation["oid"];

            $html.= "<div style=''>";
            $html.= "<span title='Szabadság törlése' onclick='Schedule.DeleteWorkerVacation(\"{$thisDay}\", {$workerId});' style='cursor:pointer;padding:2px 5px;background:#a00;color:#fff;border-radius: 2px;'><i class='fa-solid fa-trash'></i></span>&nbsp;";
            if ($vacation["status"] == 0) {
                $html .= "<span onclick='Schedule.SetVacationStatus(\"{$thisDay}\", {$workerId}, 1);' style='cursor:pointer;padding:2px 5px;background:#56af56;color:#fff;border-radius: 2px;'><i class='fa-solid fa-check'></i> Elfogadás</span>&nbsp;";
                $html .= "<span onclick='Schedule.SetVacationStatus(\"{$thisDay}\", {$workerId}, 2);' style='cursor:pointer;padding:2px 5px;background:#a00;color:#fff;border-radius: 2px;'><i class='fa-solid fa-xmark'></i> Elutasítás</span>&nbsp;";
            }
            if ($vacation["status"] == 1) {
                $approveds++;
                $html .= "<span style='color:green'>elfogadva</span> <span title='visszavonás' onclick='Schedule.SetVacationStatus(\"{$thisDay}\", {$workerId}, 0);' style='cursor:pointer;padding:1px 3px;background:darkslategray;color:#fff;border-radius: 2px;'><i class='fa-solid fa-xmark'></i></span>&nbsp;";
            }
            if ($vacation["status"] == 2) {
                $html .= "<span style='color:darkred'>!elutasítva!</span> <span title='visszavonás' onclick='Schedule.SetVacationStatus(\"{$thisDay}\", {$workerId}, 0);' style='cursor:pointer;padding:2px 3px 1px 3px;background:darkslategray;color:#fff;border-radius: 2px;'><i class='fa-solid fa-xmark'></i></span>&nbsp;";
            }
            $html.= "{$vacation["nev"]}";
            $html.= "</div>";
        }

        if ($approveds > 1) {
            $html .= "<div style=''>";
            $html .= "<span style='display:inline-block;background:yellow;color:black;padding:2px 5px;'>ütközés: több elfogadott szabadság egy napon belül!</span>&nbsp;";
            $html .= "</div>";
        }

        return $html;
    }

}