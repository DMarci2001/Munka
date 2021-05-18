<?php

class AjaxService {

    public function start() {

        if (isset($_GET["phpinfo_jns"])) {
            phpinfo();
            die();
        }

        if (isset($_GET["downloaddoc"]) && isset($_GET["f"]) && isset($_GET["k"])) {
            $docAgent = new DocAgent();
            $docAgent->showDocBinary($_GET["f"], $_GET["k"]);
        }

        if (isset($_GET["tappenzcheckrefresh"])) {
            $bookingService = new BookingService();
            echo $bookingService->tappenzCheckHTML($_GET["tappenzcheckrefresh"]);
            die();
        }

        if (isset($_POST["gettipusmegj"])) {
            $bookingService = new BookingService();
            echo $bookingService->getTipusMegj($_SESSION["helyszindata"]["id"], $_POST["tid"], $_POST["hid"]);
            die();
        }

        if (isset($_POST["checkrendeles"])) {
            header("Cache-Control: no-cache, no-store, must-revalidate");
            header("Pragma: no-cache");
            header("Expires: 0");

            $bookingService = new BookingService();
            $bookingService->setHelyszin($_POST["helyszin"]);
            $bookingService->setSzuresTipus($_POST["szurestipusid"]);

            if (!$odata = $bookingService->selectOrvosForIdopont($_POST["idopont"], $_POST["orvos"])) {
                die("Ezt az időpontot időközben lefoglalták!");
            }

            if ($odata["onlytel"] == 1 && $odata["tel"] != "") {
                echo "Erre a rendelésre az online bejelentkezés jelenleg nem üzemel kérjük jelentkezzen be ezen a telefon számon: " . $odata["tel"];
                die();
            }

            $statement = $_SERVER['REQUEST_URI'];
            if (isset($_REQUEST['version']) && $_REQUEST['version'] == "2") {
                if ($statement == "/index.php?page=welcome" || ($statement == "/" && $_SESSION["helyszindata"]["id"] == 11)) echo "ok3";
                if ($statement == "/index.php?page=idopontfoglalas") echo "ok2";
                if ($statement == "/index.php") echo "ok3";
            } else {
                echo "ok";
            }
            die();
        }

        if (isset($_GET["mailtest"])) {
            $bookingService = new BookingService();
            $bookingService->notificationService->sendUserReservationNotification(135442);
            $bookingService->notificationService->sendToCegAndOrvos(132112, 1, 1);
            die("sent");
        }

        if (isset($_GET["showidopontvalasztov2"])) {
            header('Content-Type: application/json');

            $bookingService = new BookingService();
            echo $bookingService->showIdoPontValasztoV2();
            die;
        }

        if (isset($_POST["irszquery"])) {
            $varos = "";
            if ($irszData = sql_query("select varos from irsz where irszonly=? limit 1", [$_POST["irszquery"]])->fetch()) {
                $varos = $irszData["varos"];
            } else {

                if (substr($_POST["irszquery"], 0, 1) == "1") {
                    $varos = "Budapest";
                }
            }
            if (substr_count($varos, "BUDAPEST")) {
                $varos = "Budapest";
            }

            echo  $varos;
            die();
        }


    }

}