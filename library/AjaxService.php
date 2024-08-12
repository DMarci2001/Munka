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

            $_SESSION["selectedJarat"] = "";
            if (!empty($_POST["selectedJarat"])) {
                $_SESSION["selectedJarat"] = $_POST["selectedJarat"];
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

        if (isset($_GET["address"])) {
            setcookie("lockedhelyszin", $_GET["address"], time() + 60 * 60 * 24 * 365, "/");
            $_COOKIE["lockedhelyszin"] = $_GET["address"];
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
                            $GLOBALS["extraloginfo"] = "orvos nem erősítette meg";
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

        if (isset($_GET["fgszimport"])) {
            $sorok = explode("\n", $this->fgszCsv);
            foreach ($sorok as $sor) {
                $mezok = explode(";", $sor);
                $cimData = explode(" ", $mezok[6]);
                $utcaData = explode(", ", $mezok[6]);
                $cegid = 220;
                $helyszinid = 461;
                $orvos = 1078;
                $rinterval = 5;
                $szurestipusid = 48;
                $datum = "2023-11-22 {$mezok[0]}";
                $neme = 1;
                $torzsszam = $mezok[1];
                $nev = $mezok[2];
                $szuldatum = str_replace(".", "-", $mezok[4]);
                $telefon = $mezok[3];
                $taj = $mezok[5];
                $irsz = trim($cimData[0]);
                $varos = trim(str_replace(",", "", $cimData[1]));
                $utca = trim($utcaData[1]);

                if (empty($nev)) {
                    continue;
                }

                $query = "insert into foglalasok set cegid='{$cegid}', helyszinid='{$helyszinid}', orvosassigned='{$orvos}',rinterval='{$rinterval}',szurestipusid='{$szurestipusid}', aktiv=1, checked=1,regdatum=now(), 
                           neme={$neme}, datum='{$datum}', torzsszam='{$torzsszam}', nev='{$nev}', telefon='{$telefon}', szuldatum='{$szuldatum}', taj='{$taj}', irsz='{$irsz}', varos='{$varos}', utca='{$utca}'";

                echo $query."<br/>";
                sql_query($query);
            }

            die;
        }

        if(isset($_POST["checkwhitelist"])){
            if(!$onlist=sql_fetch_array(sql_query("SELECT * FROM suzuki_white_list WHERE taj=?",array($_POST["taj"])))){
                echo "Sajnálatos módon Ön nem jogosult a Suzuki Menedzser szűrésre.<br><br> Kérjük keresse meg a Magyar Suzuki Zrt. HR Osztályát.";
            }
            die();
        }

        if (isset($_GET["spektrumduplicatelist"])) {
            $container = [];
            $requests = sql_query("SELECT * FROM labrequestmessages m WHERE m.tipus='out' AND laborprovider='spektrumlab' AND datum>date_sub(NOW(), interval 14 day) ORDER BY datum")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($requests as $request) {
                $hl7 = $request["content"];


                foreach (explode("\n", $hl7) as $line) {
                    if (substr_count($line, "PID|")) {
                        $data = explode("|", $line);
                        $id = $data[3];
                        $nev = $data[5];
                        $taj = $data[19];
                        $szuldatum = date("Y-m-d", strtotime($data[7]));
                        //echo "{$id}-{$nev}-{$szuldatum} {$line}<br>";
                        $container[$id][] = "{$request["datum"]}: {$nev}, taj:{$taj} szülidő: {$szuldatum}";
                    }
                }


            }
            echo "<pre>".print_r($container, true)."</pre>";
            die;
        }
    }


    private string $fgszCsv = "07:00:00;9328116;Pirzsók László;+36709387906;1983.07.16;039204684;8981 Gellénháza, Napsugár utca 8                                                                    ;;;;;
07:05:00;9360692;Tóth Martin;+36202631530;1998.09.16;115213508;8985 Becsvölgye, Új utca 4                                                                          ;;;;;
07:10:00;9328010;Császár Sándor;+36206109734;1976.07.21;034129470;8900 Zalaegerszeg, Hóvirág utca 18                                                                  ;;X;;;
07:15:00;9322617;Szabó Attila;+36202117734;1966.09.19;026926838;8900 Zalaegerszeg, Ördöngős völgy 11                                                                ;;X;;;
07:20:00;9322662;Kardos József;+36204217685;1970.01.30;029180624;8808 Nagykanizsa(Palin), Vadrózsa utca 47                                                           ;;;;;
07:25:00;9360625;Kiss László;+36704663267;1969.02.25;028542308;8800 Nagykanizsa, Kazanlak körút 12 B lh. 2. em. 11                                                 ;;;;;
07:30:00;9321621;Kovács Gábor;+36703738211;1973.01.08;031198255;8649 Balatonberény, Ady Endre utca 1                                                                ;;;;;
07:35:00;9321646;Kustán Róbert;+36205701638;1966.04.05;026636593;8900 Zalaegerszeg, Mártírok útja 62 3. em. 6                                                        ;;;;;
07:40:00;9329963;Kocsis Géza;+36303707910;1961.10.11;024057309;8900 Zalaegerszeg, Dózsa György utca 50 fszt. 4                                                     ;;;;;
07:45:00;9360670;Agg Dávid;+36301484564;1994.02.27;094986097;8921 Alibánfa, Deák Ferenc u 12/A                                                                   ;;;;;
07:50:00;9360714;Simon László Károly;+36703738222;1968.08.28;105767671;8946 Tófej, Kosuth Lajos utca 3                                                                     ;;;;;
07:55:00;9329924;Bodó László;+36703738120;1967.03.13;027215014;8900 Zalaegerszeg, Vizslaparki út 31 1. em. 10                                                      ;;;;;
08:00:00;9360455;Kiss Bence;+36202923699;1991.03.13;043862531;8900 Zalaegerszeg, Baross Gábor utca 23 B4 ép. 4. em. 16                                            ;;;;;
08:05:00;9360181;Kovács Andor;+36704669757;1964.12.31;025890129;8981 Gellénháza, Park utca 8                                                                        ;;;;;
08:10:00;9360517;Kajdi Szilárd;+36204557946;1974.08.25;032426539;8943 Bocfölde, Bartók Béla u 11 ép.                                                                 ;;;;;
08:15:00;9360728;Foki György;+36704661556;1988.10.09;042397159;8900 Zalaegerszeg, Göcseji út 45 4. em. 45                                                          ;;;;;
08:20:00;9321600;Auer Zoltán;+36202194037;1969.04.13;028636434;9684 Egervölgy, Kossuth Lajos utca 122                                                              ;;;;;
08:25:00;9330700;Hóbor József;+36304366318;1963.11.20;025255230;8900 Zalaegerszeg, Landorhegyi út 18/A A lh. 1. em. 4                                               ;;;;;
09:25:00;9321626;Juhász László;+36204600489;1961.12.04;024135306;7271 Fonó, Petőfi utca 38                                                                           ;;;;;
10:00:00;9322565;Molnár László;+36704661586;1977.11.09;035221560;8200 Veszprém, Haszkovó utca 17/C 1. em. 5                                                          ;;;;;
10:05:00;9360767;Kiss Gábor;+36703396516;1977.04.14;034740396;8200 Veszprém, Fáskert utca 12 1 ép.                                                                ;;;;;
";


}