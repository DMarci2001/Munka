<?php

class MissingDataPage extends CorePage {


    //1 igenre is piros
    //az alábbi tüneteket = az alábbi tünetek megjelenését

    //14 n de rendelkezik negativ pcr tesztte
    //megkaptam a koronavirus elleni védőoltás és eltelt 7 nap

    public function __construct()
    {
        parent::__construct();

        $this->showMainMenu = false;
        $this->showLangMenu = false;
        $this->lockInPage   = true;

        if (isset($_POST["sendmissingdata"])) {
            $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);

            if ($reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=? and CONCAT(SHA1(CONCAT(regdatum, id)), SHA1(CONCAT(nev, regdatum)), SHA1(CONCAT(id, nev, regdatum)))=?", [$_POST["r"], $_POST["h"]]))) {

                if ($reservationData["paciensid"] != 0) {
                    sql_query("update felhasznalok set taj=?, szuldatum=?, neme=?, irsz=?, varos=?, utca=?, munkakor=? where id=?", [$_POST["taj"], $_POST["szuldatum"], intval($_POST["neme"]), $_POST["irsz"], $_POST["varos"], $_POST["utca"], $_POST["munkakor"], $reservationData["paciensid"]]);
                }

                sql_query("update foglalasok set taj=?, szuldatum=?, neme=?, irsz=?, varos=?, utca=?, munkakor=? where id=?", [$_POST["taj"], $_POST["szuldatum"], intval($_POST["neme"]), $_POST["irsz"], $_POST["varos"], $_POST["utca"], $_POST["munkakor"], $_POST["r"]]);

                $_SESSION["missingdatamessage"] = "Az adatokat tároltuk, köszönjük a közreműködést!";
            } else {
                die("error 1672");
            }

            header("location:index.php?page={$_GET["page"]}&r={$_POST["r"]}&h={$_POST["h"]}");
            die;
        }

    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->showFormErrors();

        $fid  = $_GET["r"];
        $hash = $_GET["h"];

        if (!$reservationData = sql_fetch_array(sql_query("select * from foglalasok f where id=? and CONCAT(SHA1(CONCAT(regdatum, id)), SHA1(CONCAT(nev, regdatum)), SHA1(CONCAT(id, nev, regdatum)))=?", [$fid, $hash]))) {
            echo "Foglalás nem található!";
            return;
        }

        if (isset($_SESSION["missingdatamessage"]) && $_SESSION["missingdatamessage"] != "") {
            echo "<div style='margin:0px 0px 10px 3px;background:#0a0;color:#fff;border-radius:5px;padding:10px;'>{$_SESSION["missingdatamessage"]}</div>";
            unset($_SESSION["missingdatamessage"]);
        }

        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<div>Kedves {$reservationData["nev"]}!<br/><br/>Az ügyintézés meggyorsítása és a várakozási idő csökkentése érdekében a következő formon megadhatja a szükséges adatait.</div>";
        echo "<input type='hidden' name='r' id='r' value='{$_REQUEST["r"]}' />";
        echo "<input type='hidden' name='h' id='h' value='{$_REQUEST["h"]}' />";

        echo  "<table>";
        echo  "<tr><td></td><td>&nbsp;</td></tr>";

        echo  "<tr><td>{$webText["tajszam"]}: </td><td><input style='width:120px;' type='text' id='tajszam' name='taj' value='{$reservationData["taj"]}'></td></tr>";
        echo  "<tr><td>{$webText["szuletesidatum"]}: </td><td>" . $this->utils->datumSelector($reservationData["szuldatum"], "szuldatum") . "</td></tr>";
        echo  "<tr><td>{$webText["neme"]}:</td><td><input type='radio' name='neme' value='1' " . ($reservationData["neme"] == 1 ? "checked" : "") . "/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' " . ($reservationData["neme"] == 2 ? "checked" : "") . "/> {$webText["no"]}</td></tr>";
        echo  "<tr><td>{$webText["irsz"]}:</td><td><input style='width:60px;' type='text' name='irsz' value='{$reservationData["irsz"]}'></td></tr>";
        echo  "<tr><td>{$webText["varos"]}:</td><td><input style='width:250px;' type='text' name='varos' value='{$reservationData["varos"]}'></td></tr>";
        echo  "<tr><td>{$webText["utca"]}:</td><td><input style='width:250px;' type='text' name='utca' value='{$reservationData["utca"]}'></td></tr>";
        echo  "<tr><td>{$webText["munkakor"]}:</td><td><input style='width:250px;' type='text' name='munkakor' value='{$reservationData["munkakor"]}'></td></tr>";

        echo  "<tr><td></td><td></td></tr>";

        echo  "<tr><td></td><td>&nbsp;</td></tr>";

        echo  "<tr><td colspan='2'><input type='submit' style='border:none' class='newbutton' name='sendmissingdata' value='Elküldés'/></td></tr>";
        echo "</table>";
        echo  "</form>";
    }

}

