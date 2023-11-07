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

        if(isset($_GET["getInfoPageText"])){
            $bookingService = new BookingService();
            echo $bookingService->getInfoPageText($_GET["getInfoPageText"]);
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
            if (isset($_POST["neme"])) {
                $bookingService->setNeme(intval($_POST["neme"]));
            }

            if (!$odata = $bookingService->selectOrvosForIdopont($_POST["idopont"], $_POST["orvos"])) {
                die("Ezt az időpontot időközben lefoglalták!");
            }

            if ($odata["onlytel"] == 1 && $odata["tel"] != "") {

                if($_SESSION["helyszindata"]["manual_booking_option"]==1){
                    echo "manual_booking";
                    die();
                }

                if ($bookingService->numberOfReservationRequired() <= 1 && !in_array($_SESSION["helyszindata"]["id"], [375])) { //cib kivétel
                    echo "Erre a rendelésre az online bejelentkezés jelenleg nem üzemel kérjük jelentkezzen be ezen a telefon számon: " . $odata["tel"];
                    die();
                }
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
            //header('Content-Type: application/json');

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

        if (isset($_POST["uploadasset"])) {
            $dataId = intval($_POST["uploadasset"]);
            $tipus  = $_POST["tipus"];

            $docAgent = new DocAgent();
            $result = $docAgent->uploadAssetImage($tipus, $dataId, $_FILES[0]);

            $result["html"] = $docAgent->showAssetEditor($tipus, $dataId);
            Utils::jsonOut($result);

            die;
        }

        if (isset($_POST["deleteasset"])) {
            $id = intval($_POST["deleteasset"]);
            $tipus  = $_POST["tipus"];

            $data = sql_fetch_array(sql_query("select dataid from dokumentumok where id=? and assetid=?", [$id, $tipus]));
            $dataId = $data["dataid"];

            $docAgent = new DocAgent();
            $docAgent->deleteAsset($tipus, $id);

            $result["html"] = $docAgent->showAssetEditor($tipus, $dataId);
            Utils::jsonOut($result);

            die;
        }


        if (isset($_GET["showfoto"])) {
            $service = new DocAgent();
            $service->outputAsset($_GET["showfoto"], $_GET["c"]);
        }


        if (isset($_GET["selectthistime"])) {
            $bookingService = new BookingService();

            if ($reservationSelected = sql_query("select * from foglalasok where id=? and pass=?", [$_GET["selectthistime"], $_GET["p"]])->fetch(PDO::FETCH_ASSOC)) {
                if ($reservationSelected["fgroupid"] == 0) {
                    echo "Ez a foglalás már jóvá lett hagyva!";
                    die;
                }

                if (!isset($_GET["confirm"])) {
                    echo "Erősítse meg, hogy megfelelő Önnek a következő időpont: <strong>" . date("Y.m.d H:i", strtotime($reservationSelected["datum"])) . "</strong><br/><br/>";
                    echo "<a href='index.php?selectthistime={$_GET["selectthistime"]}&p={$_GET["p"]}&confirm'>Megerősítem</a>";
                }
                if (isset($_GET["confirm"])) {
                    $reservations = sql_query("select id, pass from foglalasok where fgroupid=? and fgroupid<>0", [$reservationSelected["fgroupid"]])->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($reservations as $reservation) {
                        if (intval($reservation["id"]) == intval($reservationSelected["id"])) {
                            sql_query("update foglalasok set fgroupid=0 where id=?", [$reservation["id"]]);
                            $bookingService->notificationService->sendToCegAndOrvos($reservation["id"]);
                            $bookingService->notificationService->sendUserReservationNotification($reservation["id"]);
                        } else {
                            $bookingService->deleteReservation($reservation["id"], $reservation["pass"]);
                        }
                    }

                    echo "Köszönjük, az időpont kiválasztása megtörtént.";
                }
            } else {
                echo "Foglalás nem található!";
            }
            die;
        }


        if (isset($_GET["tesztcuccok"])) {
            $service = new SpektrumlabService();
            $service->setSpectrumLabKapcs();

            die("ok");
        }

        /*
        $nameData = [
            "Deli" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
            "" => ["", ""],
        ];
        */

        if (isset($_GET["pdfenc"])) {

            /*
            $nameData = [
                "Baju" => ["BAJUSZNÁCS-BÁLINT CSENGE", "114861209"],
                "Balo" => ["BALOTAI ATTILA", "031066509"],
                "Benc" => ["BENCZÉNÉ DR. GONDOS ÁGNES VERONIKA", "085852958"],
                "Bott" => ["BOTTLÓ PÉTER DONÁT", "117326330"],
                "Csel" => ["CSELOVSZKI DÉNES", "036287556"],
                "Cser" => ["CSERNÁK ANDRÁS", "044046310"],
                "Feke" => ["FEKETE KLAUDIA", "114049234"],
                "Have" => ["HAVÉR BALÁZS", "19760723"],
                "Juha" => ["JUHÁSZ ERVIN", "025543959"],
                "Kuvi" => ["KUVIK ANNAMÁRIA", "083651542"],
                "Mocs" => ["MÓCSA FLÓRIÁN ALEX", "119264155"],
                "Olah" => ["OLÁH LÁSZLÓ", "035993849"],
                "Sime" => ["SIMEONOV MARTIN", "115060807"],
                "Szab" => ["SZABÓ RITA ÁGNES DR.", "078793299"],
            ];
            */

            /*
            $nameData = [
                "Alma" => ["ALMÁSI ILDIKÓ", "074876828"],
                "Balo" => ["BALOG KATALIN", "080390152"],
                "Bott" => ["BOTTYÁN PETRA", "091996297"],
                "Csaj" => ["CSAJKÓ ELIZA", "111221347"],
                "Csem" => ["CSEMEZ GABRIELLA", "084968926"],
                "Fabi" => ["FÁBIÁN RÉKA", "107676690"],
                "Fehe" => ["FEHÉR JÁNOS", "023747003"],
                "Gros" => ["GRÓSZNÉ KÓCZÁN MÓNIKA", "079947701"],
                "Hajd" => ["HAJDÚ CSILLA", "083339637"],
                "Heid" => ["HEIDTNÉ MOLNÁR KRISZTINA", "085331204"],
                "Huck" => ["HUCKER IVETT", "087421730"],
                "Jano" => ["JÁNOS MARIANNA", "080285580"],
                "Joo" => ["JOÓ IMRE", "024685454"],
                "Mocz" => ["MÓCZI KORINA DR.", "115476855"],
                "Nagy" => ["NAGY-BERTA ZSUZSANNA", "077357012"],
                "Pech" => ["PECHÁR PATRIK", "110588933"],
                "Ripp" => ["RIPPERT-PETŐ SZANDRA", "093448565"],
                "Sall" => ["SALLAI VIRÁG", "088570130"],
                "Simo1971" => ["SIMON ÉVA", "079077147"],
                "Simo1984" => ["SIMON ÉVA ERIKA", "088695286"],
                "Szta" => ["SZTANYIK ANDREA", "086352970"],
                "Tegz" => ["TEGZESNÉ VAJDA ÁGNES", "084392299"],
                "Tima" => ["TÍMÁR SAROLTA", "078671560"],
                "Vask" => ["VASKÓ KÁRMEN ÁGNES", "084725558"],
                "Vass" => ["VASS KATALIN", "083028069"],
            ];
            */

            $nameData = [
                "Bara" => ["BARABÁSI SÁNDOR ZSOLT", "025312760"],
                "Szab" => ["SZABÓ VIKTÓRIA MERCÉDESZ", "082123312"],
            ];

            $dir = "/var/pdfwork";
            //$password = "AJ4/YFjY"; //synlab
            //$password = "gk2q+JQU"; //mak
            $password = "Ge-Weq5u"; //törvényszék

            $d = dir($dir);
            while (false !== ($entry = $d->read())) {
                $outFile = $outFileZip = "";
                $zipPassword = "";

                foreach ($nameData as $key => $data) {
                    if (substr_count($entry, $key) && substr_count($entry, "_2023")) {
                        $outFile = "{$data[0]}.PDF";
                        $outFileZip = "{$data[0]}.zip";
                        if (substr_count($entry, "-1")) {
                            $outFile = "{$data[0]}-1.PDF";
                            $outFileZip = "{$data[0]}-1.zip";
                        }
                        $zipPassword = $data[1];
                        break;
                    }
                }

                if (empty($outFile) || empty($zipPassword)) {
                    continue;
                }

                if (substr_count(strtolower($entry), ".pdf")) {
                    $output = `qpdf --password={$password} --decrypt {$dir}/{$entry} '{$dir}/{$outFile}'`;
                    echo $output;

                    $output = `zip -j --password {$zipPassword} '{$dir}/{$outFileZip}' '{$dir}/{$outFile}'`;

                    echo $entry." zip -p {$zipPassword} '{$dir}/{$outFileZip}' '{$dir}/{$outFile}' ".$output."<br/>";
                }
            }

            die("ok");
        }




    }

}