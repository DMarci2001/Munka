<?php
class ServicesPage extends CorePage
{
    public $webText;

    public function __construct()
    {
        parent::__construct();

        $this->webText = $this->lang->webText;

        if (isset($_GET["changeServiceMethod"])) {
            //Ellenőrzöm a küldött szolgáltatást, hogy a cégnél lehet-e választani és vagy létezik-e egyáltalán
            if (isset($_GET["sid"])) {
                $request = sql_query("SELECT * FROM szolgaltatasok WHERE cegek LIKE '%|{$_SESSION["helyszindata"]["id"]}|%' AND id=?", array($_GET["sid"]));
                if (sql_num_rows($request) >= 1) {
                    $result = sql_fetch_array($request);
                } else {
                    die("Service doesnt exist!");
                }
            } else {
                die("No service has been sent!");
            }

            //Ellenőrzöm a küldött metódust, hogy helyes-e vagy létezik-e egyáltalán
            if (isset($_GET["method"])) {
                if ($_GET["method"] == "havidij" || $_GET["method"] == "evesdij") {
                    //Ellenőrzöm, hogy a szolgáltatásnál van-e érték megadva a metódushoz (ez dönti el, hogy választható-e)
                    if (!empty($result[$_GET["method"]])) {
                        //Ha igen, akkor kiírom a metódust stringben, hogy az ajax hívás a callback értéket megjelenítse a kijelölt cellában olvasásra.
                        echo number_format($result[$_GET["method"]], 0, "", ".") . "Ft";
                    } else {
                        die("Method variable is empty!");
                    }
                }
            } else {
                die("Method doesnt exist!");
            }
            die();
        }

        if (isset($_POST["order-service"])) {
            /*Itt tárolom le a megrendelési szándékot, 2 adatot kapok meg a post küldéssel, a fizetési módot és a szolgáltatást. 
            Mit akarok itt le ellenőrizni? esetleg azt, hogy van-e ugyanilyen szolgáltatás megrendelve, de még nincsen befizetve.
            */

            if (sql_num_rows($request = sql_query("SELECT * FROM szolgaltatasok_rendelesek WHERE fid=? and sid=?", array($_SESSION["user"]["id"], $_POST["order-service"]))) > 0) {
                $this->errors[] = "A kiválaszott szolgáltatás aktív az Ön megrendelt szolgáltatási között.";
            } else {

                //Szolgáltatás adatainak lekérdezése:
                if (isset($_POST["method"]) && $_POST["method"] != "") {
                    $service = sql_fetch_array(sql_query("SELECT * FROM szolgaltatasok WHERE id=?", array($_POST["order-service"])));
                    $ar = ($_POST["method"] == "evesdij") ? $service["evesdij"] : ($service["futamido"] * $service["havidij"]);
                    $data = array($_SESSION["user"]["id"], $_POST["order-service"], "WFP", date("Y-m-d H:i:s"), $_POST["method"], ($_POST["method"] == "havidij") ? $service["futamido"] : "nincs", $ar, $service["havidij"]);
                    sql_query("INSERT INTO szolgaltatasok_rendelesek SET fid=?,sid=?,statusz=?,kelte=?,fizetesimod=?,futamido=?,ar=?,havidij=?", $data);
                }
            }
        }



        if (isset($_GET["startpay"])) {
            $fizId = intval($_GET["startpay"]);

            if (sql_fetch_array(sql_query("select id from szolgaltatasok_rendelesek_fizetesek where id=?", [$fizId]))) {
                $simpleService = new SimplePayService();
                $simpleService->setSandBox(true);
                $simpleService->startPay(self::serviceTransactionId($fizId));
                die;
            }
        }

    }

    public static function serviceTransactionId($id) {
        return "serv{$id}";
    }

    public function showPage()
    {
        /*if (!isset($_SESSION["user"])) {
            header("Location:index.php?page=booking");
        }*/
        echo $this->displayFejlec("Szolgáltatások", true);
        echo $this->showErrors();

        $fizId = str_replace("serv", "", $_REQUEST["paymentresult"]);

        //fizetés eredménnyel visszatérés
        if (isset($_REQUEST["paymentresult"])) {
            $paymentResult = sql_fetch_array(sql_query("select * from banktransactions where id=? and foglalasid=? limit 1", [$_REQUEST["transid"], $_REQUEST["paymentresult"]]));

            //jogosultság ellenőrzés
            if (!sql_fetch_array(sql_query("SELECT * FROM szolgaltatasok_rendelesek_fizetesek f 
                LEFT JOIN szolgaltatasok_rendelesek r ON r.id=f.order_id
                WHERE f.id = ? AND r.fid=?", [$fizId, $_SESSION["user"]["id"]]))) {
                die("error 555");
            }

            $resultColor = "#f00";
            if ($paymentResult["result"] == "CANCEL") {
                $resultToShow = "A fizetés folyamatot megszakította!";
            }
            if ($paymentResult["result"] == "FAIL") {
                $resultToShow = "A fizetés nem sikerült!";
                sql_query("update szolgaltatasok_rendelesek_fizetesek set statusz=? where id=?", [$paymentResult["result"], $fizId]);
            }

            if ($paymentResult["result"] == "SUCCESS" || $paymentResult["result"] == "FINISHED") {
                $resultColor = "#080";
                $resultToShow = "A fizetés sikerült!";
                sql_query("update szolgaltatasok_rendelesek_fizetesek set statusz=? where id=?", [$paymentResult["result"], $fizId]);
            }



            if (isset($resultToShow)) {
                echo "<div style='margin:0px 0px 20px 0px;padding:10px;text-align: center;background:{$resultColor};color:#fff;'>{$resultToShow}</div>";
            }
        }


        $orderTable = $otr = $price = "";

        $request = sql_query("SELECT rendeles.*,szolg.megnev FROM szolgaltatasok_rendelesek rendeles
                              LEFT JOIN szolgaltatasok szolg ON szolg.id=rendeles.sid
                              WHERE fid=?", array($_SESSION["user"]["id"]));

        //Ha nem rendelt meg még semmit, akkor ezt jelenítse meg
        if (sql_num_rows($request) < 1) {
            $otr .= "<tr><td style=\"text-align:center;font-weight:lighter\"> - Nincs még megrendelésed! - </td></tr>";
        } else {
            /*Milyen oszlopokat akarok itt megjeleníteni?
              pl.: szolgáltatás neve, fizetési mód, futamidő, keltezés, havi részlet, fizetés gomb

              megnév, státusz (befizetésre vár,nincs esedékes fizetés,befizetve), utolsó befizetés:, következő fizetési esedékesség:, összeg:)
            */
            $otr .= "<tr>";
            //$otr .= "<td></td>";
            $otr .= "<td style=\"font-weight:bold\">Szolgáltatás</td>";
            $otr .= "<td style=\"font-weight:bold\">Státusz</td>";
            $otr .= "<td style=\"font-weight:bold\">Megrendelve</td>";
            $otr .= "<td style=\"font-weight:bold\">Futamidő</td>";
            $otr .= "<td style=\"font-weight:bold\">Utolsó befizetés</td>";
            $otr .= "<td style=\"font-weight:bold\">Köv. fiz. határidő</td>";
            $otr .= "<td style=\"font-weight:bold\">Összeg</td>";
            $otr .= "</tr>";

            while ($result = sql_fetch_array($request)) {
                $otr .= "<tr>";
                $otr .= "<td style=\"font-weight:bold\">{$result["megnev"]}</td>";
                //$otr .= "<td>{$result["fizetesimod"]}</td>";
                //Három értéke lehet: waiting for payment, no payment due, paid
                //Ezt az értéket a befizetések alapján updateljük majd
                $otr .= "<td>{$this->webText[$result["statusz"]]}</td>";
                $otr .= "<td>{$result["kelte"]}</td>";
                $otr .= "<td>" . ($result["futamido"] == "nincs" ? $result["futamido"] : $result["futamido"] . " hónap") . "</td>";

                //Ezekhez az adatokhoz szükségem van a befizetések táblára
                $otr .= "<td></td>";
                $otr .= "<td></td>";

                //fizetési összeg meghatározása:
                if ($result["fizetesimod"] == "havidij") {
                    $price = number_format($result["havidij"], 0, "", ".");
                }
                if ($result["fizetesimod"] == "evesdij") {
                    $price = number_format($result["ar"], 0, "", ".");
                }

                $otr .= "<td>{$price} Ft</td>";
                $otr .= "<td><a class=\"newbutton\" href='?page=services&startpay={$result["id"]}'>Fizetés</a></td>";
                $otr .= "</tr>";
            }
        }


        $orderTable .= "<table style=\"padding-bottom:20px;margin-bottom:30px;width:100%;border-bottom:1px solid black\">";
        $orderTable .= "<tr><td style=\"font-weight:bold;font-size:18px;padding-bottom:20px;\">Megrendelt szolgáltatások</td></tr>";
        $orderTable .= $otr;
        $orderTable .= "</table>";

        echo $orderTable;


        /*
        Itt létre kell hoznom egy táblázatot, ami a "szolgaltatasok" táblából meríti az információkat.
        Milyen adat oszlopok kellenek? A Szolgáltatás megnevezése, fizetési lehetőségek(mondjuk legördülő menüből?)
        */
        $table = $tr = "";

        //Szolgáltatások lekérdezése cégid alapján:
        $request = sql_query("SELECT * FROM szolgaltatasok WHERE cegek LIKE '%|{$_SESSION["helyszindata"]["id"]}|%' ORDER BY megnev ASC");

        //Címke:
        $tr .= "<tr><td colspan=\"4\" style=\"font-weight:bold;font-size:18px;padding-bottom:20px\">Szolgáltatások</td></tr>";

        //Oszlopok:
        $tr .= "<tr>";
        $tr .= "<td style=\"padding-bottom:25px\"><strong>Szolgáltatás megnevezése</strong></td>";
        $tr .= "<td style=\"padding-bottom:25px\"><strong>Fizetési lehetőségek</strong></td>";
        $tr .= "<td style=\"padding-bottom:25px\"><strong>Befizetési összeg</strong></td>";
        $tr .= "</tr>";

        while ($row = sql_fetch_array($request)) {
            $tr .= "<tr id=\"sid-{$row["id"]}\"><form method=\"POST\">";
            $tr .= "<td style=\"padding-top:30px\"><strong>{$row["megnev"]}</strong></td>";
            $tr .= "<td style=\"padding-top:30px\"><select name=\"method\" onChange=\"changeServicePaymentMethod({$row["id"]},$(this).val())\"><option value=\"evesdij\">Éves díj</option><option value=\"havidij\">Havi díj (12 hó futamidő)</option></select></td>";
            $tr .= "<td style=\"padding-top:30px\">" . number_format($row["evesdij"], 0, "", ".") . " Ft</td>";
            $tr .= "<td style=\"padding-top:30px\"><button type=\"submit\" class=\"newbutton\" style=\"border:none\" name=\"order-service\" value=\"{$row["id"]}\" >Megrendelés</button></td>";
            $tr .= "</form></tr>";

            /*Leírás a termékről*/
            $tr .= "<tr>";
            $tr .= "<td style=\"padding-bottom:20px;border-bottom:1px solid black\" colspan=\"3\"><div id=\"sid-{$row["id"]}-description\">{$row["leiras"]}</div></td>";
            $tr .= "</tr>";
        }

        $table .= "<table style=\"width:100%\">";
        $table .= $tr;
        $table .= "</table>";

        echo $table;
    }
}
