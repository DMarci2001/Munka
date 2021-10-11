<?php

class AdminMonthlyStatPage extends AdminCorePage {
    private $service;

    public function __construct()
    {
        parent::__construct();

        $this->service = new MonthlyStatService();

        $GLOBALS["css"][] = "dailystat.css";
        $GLOBALS["javascript"][] = "dailystat.js";
    }

    public function showPage()
    {
        if (!$this->adminUser->statAccess()) {
            echo "nincs jogosultságod!";
            return;
        }

        echo "<div id='monthlystattable'>";
        echo $this->service->displayCalendar($_SESSION["monthlystatoffset"]);
        echo "</div>";

        echo "<div id='monthlystateditor'></div>";
    }

}