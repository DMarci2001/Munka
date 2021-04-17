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

        if (isset($_REQUEST["gettimetable"])) {
            $result = $this->getTimeTable();
        }

        $this->utils->jsonOut($result);
    }



    public function getServiceList(): array
    {
        $helyszinId = Booking_Constants::DEFAULT_PLACE_IDS[0];
        $services = $this->bookingService->getPublicServices($helyszinId);

        return ["services" => $services, "helyszinId" => $helyszinId, "companyId" => $_SESSION["helyszindata"]["id"]];
    }

    public function getTimeTable():array {
        $startDate = date("Y-m-d", strtotime($_REQUEST["startdate"]));
        $endDate = date("Y-m-t", strtotime("{$startDate}"));
        $this->bookingService->setTaj(!isset($_REQUEST['taj']) ? 0 : $_REQUEST['taj']);
        $this->bookingService->setHelyszin(Booking_Constants::DEFAULT_PLACE_IDS[0]);
        $this->bookingService->setNeme(!isset($_REQUEST["neme"]) ? 0 : $_REQUEST["neme"]);
        $this->bookingService->setSzuresTipus($_REQUEST["szurestipus"]);

        return $this->bookingService->getAvailableTimeTable($startDate, $endDate);
    }

}