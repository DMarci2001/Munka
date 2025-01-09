<?php


class ReviewPage extends CorePage {

    private ReviewService $reviewService;

    public function __construct()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        parent::__construct();

        if (isset($_GET["eform"])) {
            $_SESSION["eform"]=$_GET["eform"];
        }

        if (isset($_GET["c"]))  {
            $_SESSION["eceg"] = $_GET["c"];
        }

        if (!isset($_SESSION["eceg"]))  {
            $_SESSION["eceg"] = 0;
        }

        $this->showMainMenu = false;
        $this->showLangMenu = false;
        $this->lockInPage = true;
        $this->selfContained = true;
        $this->pageTitle = "Ügyfél elégedettség felmérés - ".Booking_Constants::COMPANY_NAME_SHORT;

        $this->reviewService = new ReviewService();

        $GLOBALS["css"][] = "ertekeles.css";

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

        echo "<div id='review-page-container'>";
        echo "<div id='review-content-wrap'>";

        echo $this->reviewService->ertekelesMainMenu();
        echo $this->reviewService->ertekelesHeader();
        echo $this->reviewService->ertekelesContent();

        echo "</div>";

        echo "<div class='footercontainer_review' style='text-align: center;'>";
        echo "<div style='display:inline-block;margin:0px 40px 10px 0px;'>" . Booking_Constants::FOOTER_ADDRESS_PARAM . "</div>";
        echo "<div style='display:inline-block;margin:0px 10px 10px 0px;'>" . Booking_Constants::FOOTER_CONTACT_PARAM . "</div>";
        echo "<br clear='all'/>";
        echo "&copy; " . date("Y") . " " . Booking_Constants::FOOTER_COPYRIGHT;

        echo  "</div>";
        echo  "</div>";
    }

}

