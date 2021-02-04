<?php

class ApiEngine {

    private $utils;
    private $bookingService;
    private $companyService;

    public function __construct() {
        $this->companyService = new CompanyService();
        $this->utils = new Utils();
        $this->bookingService = new BookingService();
    }

    public function start() {
        $result = [];

        if (isset($_REQUEST["getservicelist"])) {
            $result = $this->getServiceList();
        }

        $this->utils->jsonOut($result);
    }



    public function getServiceList(): array
    {
        $helyszinId = Booking_Constants::DEFAULT_PLACE_IDS[0];
        $services = $this->bookingService->getPublicServices($helyszinId);

        return ["services" => $services, "helyszinId" => $helyszinId, "companyId" => $_SESSION["helyszindata"]["id"]];
    }

}