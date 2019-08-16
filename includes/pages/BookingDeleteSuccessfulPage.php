<?php

class BookingDeleteSuccessfulPage extends CorePage {
    private $bookingService;

    public function __construct()
    {
        parent::__construct();
        $this->bookingService = new BookingService();
    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->displayFejlec();

        echo "{$webText["torlessikerult"]}<br/>
        <br/>
        
        <a href='/'>{$webText["visszafooldal"]}</a>";

        if (isset($_SESSION["captcha"])) unset($_SESSION["captcha"]);
    }
}

