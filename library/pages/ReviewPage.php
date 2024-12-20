<?php


class ReviewPage extends CorePage {

    private ReviewService $reviewService;

    public function __construct()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        parent::__construct();

        $this->showMainMenu = false;
        $this->showLangMenu = false;
        $this->lockInPage = true;
        $this->selfContained = true;
        $this->pageTitle = "Ügyfél elégedettség felmérés - ".Booking_Constants::COMPANY_NAME_SHORT;

        $this->reviewService = new ReviewService();

        $GLOBALS["css"][] = "ertekeles.css";

        if (isset($_GET["eform"])) {
            $_SESSION["eform"]=$_GET["eform"];
        }

        if (isset($_GET["c"]))  {
            $_SESSION["eceg"] = $_GET["c"];
        }

        if (!isset($_SESSION["eceg"]))  {
            $_SESSION["eceg"] = 0;
        }

        if (isset($_SESSION["eform"])) {
            $GLOBALS["formData"] = $this->reviewService->getFormData($_SESSION["eform"]);
            if (empty($GLOBALS["formData"])) {
                die("form not found!");
            }
        } else {
            die("Form parameter missing!");
        }

    }


    public function showPage() {

        echo "<body>";

        echo $this->reviewService->ertekelesMainMenu();
        echo $this->reviewService->ertekelesHeader();
        echo $this->reviewService->ertekelesContent();

        //echo "<h1 style='text-align: center;'>Review</h1>";

    }

}

