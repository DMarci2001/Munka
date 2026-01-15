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

        error_reporting(E_ALL);
        ini_set('display_errors', 1);


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
        echo "<div class='upload-btn-wrapper'><a href='#' onclick='return false;' class='dailystatuploadbutton'>Külső adatok feltöltése</a><input type='file' id='dailystatfile' class='dailystatfile' name='dailystatfile[]' multiple /></div>";
        echo "</div>";
        echo "<div style='display:table-cell;vertical-align: middle;'>";
        echo "<div><img id='dailystatloader' style='display:none;opacity:.5;height:25px;margin-left:10px;' src='/images/loading_transparent.svg' /></div>";
        echo "</div>";
        echo "</div>";


        echo "<div id='dailystattable'>";
        echo $this->service->displayCalendar($_SESSION["dailystatoffset"]);
        echo "</div>";

        echo "<div id='dailystateditor'></div>";
    }

}

