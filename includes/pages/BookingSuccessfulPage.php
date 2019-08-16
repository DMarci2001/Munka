<?php

class BookingSuccessfulPage extends CorePage {

    public function __construct()
    {
        parent::__construct();
    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->displayFejlec();

        echo $webText["sikeresfoglalaspage"];
        echo "<a href='/'>{$webText["visszafooldal"]}</a>";

        if (isset($_SESSION["captcha"])) unset($_SESSION["captcha"]);
    }
}

