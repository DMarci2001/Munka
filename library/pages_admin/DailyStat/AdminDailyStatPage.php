<?php


class AdminDailyStatPage extends AdminCorePage
{

    private $service;

    public function __construct()
    {
        parent::__construct();

        $this->service = new DailyStatService();

        $GLOBALS["css"][] = "dailystat.css";
        $GLOBALS["javascript"][] = "dailystat.js";
    }

    public function showPage()
    {
        if (!$this->adminUser->statAccess()) {
            echo "nincs jogosultságod!";
            return;
        }

        echo "<div id='debugarea'></div>";

        echo "<div id='dailystattable'>";
        echo $this->service->displayCalendar($_SESSION["dailystatoffset"]);
        echo "</div>";

        echo "<div id='dailystateditor'></div>";
    }

}

