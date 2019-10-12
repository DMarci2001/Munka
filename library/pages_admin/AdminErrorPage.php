<?php

class AdminErrorPage extends AdminCorePage {


    public function __construct()
    {
        parent::__construct();


    }

    public function showPage() {

        echo "Page not found!";

    }

}

