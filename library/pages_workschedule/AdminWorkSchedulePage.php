<?php

class AdminWorkSchedulePage extends AdminCorePage {

    private $bookingService;

    public function __construct()
    {
        parent::__construct();


    }

    public function showPage() {
        if (!$this->adminUtils->helyszinModJog()) {
            return;
        }


        echo "work";

    }
}

