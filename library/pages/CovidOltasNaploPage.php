<?php
class CovidOltasNaploPage extends CorePage
{
    public $webText;

    public function __construct()
    {
        parent::__construct();

        $this->webText = $this->lang->webText;
    }
    public function showPage() {
        /* 
        Milyen adatokat kell elkérni kifejezetten az oltás regisztrációhoz?0
        */
    }
}