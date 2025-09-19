<?php

class AdminLogPage extends AdminCorePage {

    private array $tipusSzoveg = array(
        "ceg"            => "Cég",
        "orvos"          => "Orvos",
        "user"           => "User",
        "helyszin"       => "Helyszín",
        "szurestipus"    => "Szűréstipus",
        "paciens"        => "Paciens",
        "foglalas"       => "Foglalás",
        "foglalastorles" => "Foglalás törlés",
        "eljott"         => "Eljött állítás",
        "behivva"        => "Behívva állítás",
        "beosztas"       => "Beosztás",
    );

    public function __construct()
    {
        parent::__construct();

        if (!isset($_SESSION["logdatum"])) $_SESSION["logdatum"] = date("Y-m");
        if (!isset($_SESSION["logdatumtol"])) $_SESSION["logdatumtol"] = date("Y-m-d");
        if (!isset($_SESSION["logdatumig"])) $_SESSION["logdatumig"] = date("Y-m-d");
        if (!isset($_SESSION["logfilteruid"])) $_SESSION["logfilteruid"] = 0;
        if (!isset($_SESSION["logfiltertipus"])) $_SESSION["logfiltertipus"] = "";
        if (!isset($_SESSION["logfilterpid"])) $_SESSION["logfilterpid"] = 0;
        if (isset($_POST["datum"])) {
            if ($_SESSION["logdatum"] != $_POST["datum"]) {
                $_SESSION["logdatum"] = $_POST["datum"];
            }
        }

        if (isset($_POST["filteruid"])) $_SESSION["logfilteruid"] = $_POST["filteruid"];
        if (isset($_POST["filtertipus"])) $_SESSION["logfiltertipus"] = $_POST["filtertipus"];
        if (isset($_POST["filterpid"])) $_SESSION["logfilterpid"] = $_POST["filterpid"];
        if (isset($_POST["datumtol"])) $_SESSION["logdatumtol"] = $_POST["datumtol"];
        if (isset($_POST["datumig"])) $_SESSION["logdatumig"] = $_POST["datumig"];

        if (isset($_GET["loadlogdetail"])) {
            $row = sql_fetch_array(sql_query("select * from activitylog where id=?",array($_GET["loadlogdetail"])));
            $query = nl2br(str_replace(" ","&nbsp;",$row["query"]));
            ob_clean();
            echo "<div style='background:#eee;padding:10px;width:900px;'>{$query}</div>";
            die();
        }

        if (isset($_POST["restoreDeletedReservation"])) {
            if (!$logData = sql_query("select * from activitylog where id=?", [intval($_POST["restoreDeletedReservation"])])->fetch(PDO::FETCH_ASSOC)) {
                die("Log record not found");
            }

            if ($reservationData = sql_query("select * from foglalasok where id=?", [$logData["mid"]])->fetch(PDO::FETCH_ASSOC)) {
                die("Ez a foglalás már vissza lett állítva");
            }

            $data = json_decode($logData["query"], JSON_OBJECT_AS_ARRAY);

            $query = "aktiv=?";
            $queryData = [1];

            foreach ($data as $key => $value) {
                if (in_array($key, ["aktiv", "valami"])) {
                    continue;
                }
                $query.= ", {$key}=?";
                $queryData[] = $value;
            }

            sql_query("insert into foglalasok set {$query}", $queryData);
            logActivity("foglalastorles", $data["id"], "{$data["nev"]} foglalás visszaállítása {$data["datum"]}", json_encode($data, JSON_PRETTY_PRINT));

            die("Sikeres visszaállítás");
        }

    }

    public function showPage() {
        
        if (!$this->adminUser->logAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        echo "<div style='padding:5px;background-color:#eee;margin-bottom:10px;'>";

        echo "<form name='f1' method='post' style='padding:0px;margin:0px;'>";

        echo "<table><tr>";
        echo "<td><input type='text' name='datumtol' style='width:80px;' value='{$_SESSION["logdatumtol"]}'/> - <input type='text' name='datumig' style='width:80px;' value='{$_SESSION["logdatumig"]}'/></td>";
        echo "<td><input type='submit' name='frissit' style='' value='Frissítés'/>&nbsp;</td>";
        echo "<td><select name='filteruid' onchange='document.f1.submit();'>";

        $users = sql_query("SELECT u.id AS userid, u.nev AS nev FROM users u WHERE TRUE ORDER BY u.nev")->fetchAll();
        echo "<option value='0'>Összes felhasználó</option>";
        foreach ($users as $user) {
            echo "<option value='{$user["userid"]}'".($user["userid"]==$_SESSION["logfilteruid"]?" selected":"").">{$user["nev"]}</option>";
        }
        echo "</select>&nbsp;</td>";

        echo "<td><select name='filtertipus' onchange='document.f1.submit();'>";
        echo "<option value=''>Összes kategória</option>";
        foreach ($this->tipusSzoveg as $tipusId => $tipusNev) {
            echo "<option value='{$tipusId}'".($tipusId == $_SESSION["logfiltertipus"] ? " selected":"").">{$tipusNev}</option>";
        }
        echo "</select>&nbsp;</td>";

        echo "</tr></table>";
        echo "</form>";
        echo "</div>";

        $w = " and datum>'{$_SESSION["logdatumtol"]} 00:00:00' and datum<'{$_SESSION["logdatumig"]} 23:59:59'";
        if ($_SESSION["logfiltertipus"] != "") {
            $w.=" and tipus='".addslashes($_SESSION["logfiltertipus"])."'";
        }
        if ($_SESSION["logfilteruid"] != 0) {
            $w.=" and userid='".intval($_SESSION["logfilteruid"])."'";
        }

        $logRows = sql_query("select l.*,u.nev as usernev from activitylog l
        left join users u on u.id=l.userid
        where true {$w} order by l.datum desc limit 10000")->fetchAll(PDO::FETCH_ASSOC);

        echo "<table style='min-width:930px;'>";
        echo "<tr style='background-color:#888;color:#fff;'>";
        echo "<td class='logtd'>Dátum</td>";
        echo "<td class='logtd'>User</td>";
        echo "<td class='logtd'>Kategória</td>";
        echo "<td class='logtd' align='center'>ID</td>";
        //echo "<td>Provider</td>";
        echo "<td class='logtd'>Szöveg</td>";
        echo "</tr>";
        foreach ($logRows as $row) {
            if ($row["tipus"] == "foglalastorles" && substr_count($row["megnev"], "foglalás törlése")) {
                $row["megnev"].= " [<a href='#' onclick='restoreDeletedReservation({$row["id"]});return false;'>Visszaállítás</a>]";
            }

            echo "<tr>";
            echo "<td width='200'>{$row["datum"]}&nbsp;";
            if ($row["query"] != "") echo " [<a href='#' onclick='return showLogDetail({$row["id"]});'>részletek</a>]&nbsp;";
            echo "</td>";
            echo "<td>{$row["usernev"]}&nbsp;</td>";
            echo "<td style='".($row["tipus"] == "foglalastorles" ? "color:#f00;":"")."'>{$this->tipusSzoveg[$row["tipus"]]}&nbsp;</td>";
            echo "<td align='right'>{$row["mid"]}&nbsp;</td>";
            //echo "<td>";
            //echo substr($row["pname"],0,40);
            //echo "</td>";
            echo "<td>{$row["megnev"]}&nbsp;</td>";
            echo "<tr id='logdetail{$row["id"]}' style='display:none;'><td colspan='5'><div id='logdetailcontent{$row["id"]}'></div></td></tr>";
        }
        echo "</table>";

    }

}

