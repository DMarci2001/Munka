<?php

class AdminLogPage extends AdminCorePage {

    private $tipusSzoveg = array(
        "ceg"=>"Cég",
        "orvos"=>"Orvos",
        "user"=>"User",
        "helyszin"=>"Helyszín",
        "szurestipus"=>"Szűréstipus",
        "paciens"=>"Paciens",
        "foglalas"=>"Foglalás"
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

    }

    public function showPage() {
        
        if (!$this->adminUser->beallitasLangAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        echo "<div style='padding:5px;background-color:#eee;margin-bottom:10px;'>";

        echo "<form name='f1' method='post' style='padding:0px;margin:0px;'>";

        echo "<table><tr>";
        echo "<td><input type='text' name='datumtol' style='width:70px;' value='{$_SESSION["logdatumtol"]}'/> - <input type='text' name='datumig' style='width:70px;' value='{$_SESSION["logdatumig"]}'/></td>";
        echo "<td><input type='submit' name='frissit' style='' value='Frissítés'/></td>";
        echo "<td><select name='filteruid' onchange='document.f1.submit();'>";

        $res=sql_query("SELECT userid,u.nev AS nev FROM activitylog l left join users u on u.id=l.userid WHERE userid<>0 group by l.userid order by u.nev");
        echo "<option value='0'>Összes felhasználó</option>";
        while ($row=sql_fetch_array($res)) {
            echo "<option value='{$row["userid"]}'".($row["userid"]==$_SESSION["logfilteruid"]?" selected":"").">{$row["nev"]}</option>";
        }
        echo "</select></td>";

        echo "<td><select name='filtertipus' onchange='document.f1.submit();'>";
        $res=sql_query("SELECT DISTINCT tipus FROM activitylog WHERE true order by tipus");
        echo "<option value=''>Összes kategória</option>";
        while ($row=sql_fetch_array($res)) {
            echo "<option value='{$row["tipus"]}'".($row["tipus"]==$_SESSION["logfiltertipus"]?" selected":"").">{$this->tipusSzoveg[$row["tipus"]]}</option>";
        }
        echo "</select></td>";

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

        $res = sql_query("select l.*,u.nev as usernev from activitylog l
        left join users u on u.id=l.userid
        where true {$w} order by l.datum desc limit 10000");

        echo "<table style='min-width:930px;'>";
        echo "<tr style='background-color:#888;color:#fff;'>";
        echo "<td class='logtd'>Dátum</td>";
        echo "<td class='logtd'>User</td>";
        echo "<td class='logtd'>Kategória</td>";
        echo "<td class='logtd' align='center'>ID</td>";
        //echo "<td>Provider</td>";
        echo "<td class='logtd'>Szöveg</td>";
        echo "</tr>";
        while ($row = sql_fetch_array($res)) {
            echo "<tr>";
            echo "<td width='200'>{$row["datum"]}";
            if ($row["query"]!="") echo " [<a href='#' onclick='return showLogDetail({$row["id"]});'>részletek</a>]";
            echo "</td>";
            echo "<td>{$row["usernev"]}</td>";
            echo "<td>{$this->tipusSzoveg[$row["tipus"]]}</td>";
            echo "<td align='right'>{$row["mid"]}</td>";
            //echo "<td>";
            //echo substr($row["pname"],0,40);
            //echo "</td>";
            echo "<td>{$row["megnev"]}</td>";
            echo "<tr id='logdetail{$row["id"]}' style='display:none;'><td colspan='5'><div id='logdetailcontent{$row["id"]}'>ddd</div></td></tr>";
        }
        echo "</table>";

    }

}

