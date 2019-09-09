<?php

class AdminCalendarPage extends AdminCorePage
{

    private $bookingService;

    private $shift;

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;
        $this->bookingService = new BookingService();


        if (!isset($_SESSION["helyszin"])) $_SESSION["helyszin"] = 0;
        if (!isset($_SESSION["helyszinceg"])) $_SESSION["helyszinceg"] = 0;
        if (!isset($_SESSION["naptarszurestipus"])) $_SESSION["naptarszurestipus"] = 0;
        if (!isset($_SESSION["shift"])) $_SESSION["shift"] = 0;
        if (isset($_GET["shift"])) $_SESSION["shift"] = $_GET["shift"];

        $this->shift = intval($_SESSION["shift"]);

        if (isset($_GET["sethelyszin"])) {
            $s = explode("-",$_GET["sethelyszin"]);
            $_SESSION["helyszin"]    = $s[0];
            $_SESSION["helyszinceg"] = $s[1];
            header("location:index.php?page={$_GET["page"]}");
            die();
        }

    }

    public function showPage()
    {
        echo "<div style='display:table;'>";
        echo "<div style='display:table-row;'>";
        echo "<div style='display:table-cell;'>";


        $resh=sql_query("select * from cegek order by megnev");
        while ($rowh=sql_fetch_array($resh)) {
            $cegek[$rowh["id"]]=$rowh["megnev"];
        }

        echo "<div>";
        echo "<select name='helyszin' onchange='setHelyszin(this.value);' style='width:620px;'>";
        echo "<option value='0'>Válassz helyszínt!</option>";

        $resc = sql_query("select * from cegek where aktiv=1 order by id<>?, megnev", array($_SESSION["helyszindata"]["id"]));
        while ($rowc=sql_fetch_array($resc)) {
            if ($_SESSION["adminuser"]["jogosultsag"]<2 && substr_count($_SESSION["adminuser"]["cegjog"],"|{$rowc["id"]}|")==0) continue;

            $res=sql_query("SELECT h.* FROM helyszinek h WHERE instr(ceglink,?) ORDER BY h.cim", array("|{$rowc["id"]}|"));
            if (sql_num_rows($res) == 0) continue;

            echo "<option value='0' disabled style='background:#bbb;color:#fff;'>{$rowc["megnev"]}</option>";
            while ($rowt = sql_fetch_array($res)) {

                $color="#000";
                if (substr_count($rowt["cim"],"Martin ")>0 && $rowc["id"]==15) $color="#a00;";
                if (substr_count($rowt["cim"],"Martin ")>0 && $rowc["id"]==42) $color="#00a;";

                echo "<option style='color:{$color}' value='{$rowt["id"]}-{$rowc["id"]}'".("{$_SESSION["helyszin"]}-{$_SESSION["helyszinceg"]}"=="{$rowt["id"]}-{$rowc["id"]}"?" selected":"").">{$rowt["cim"]} ({$cegek[$rowc["id"]]})</option>";
            }

        }


        echo "</select>";
        echo "</div>";


        echo "<table cellpadding='0' cellspacing='0' style='margin-top:10px;width:100%;'>";
        echo "<tr>";
        echo "<td valign='middle'>";

        //szűréstipus választó
        if ($_SESSION["helyszinceg"]!=0) {
            echo "<div style=''>";
            echo "<select name='helyszin' onchange='setNaptarSzuresTipus(this.value);'>";
            echo "<option value='0'>Válassz szűréstípust!</option>";

            $rest=sql_query("select * from szurestipusok");
            while ($rowt=sql_fetch_array($rest)) {
                $tipusnevek[$rowt["id"]]=$rowt["megnev"];
            }


            $res=sql_query("SELECT cegid,tipusok FROM orvos_beosztas b WHERE b.helyszinid='".intval($_SESSION["helyszin"])."' and b.tol<>0 and b.ig<>0");
            while ($row=sql_fetch_array($res)) {

                if ($user["jogosultsag"]<2 && substr_count($user["cegjog"],"|{$row["cegid"]}|")==0) continue;

                $ta=explode("|",$row["tipusok"]);
                for ($i=0;$i<count($ta);$i++) {
                    if (trim($ta[$i])!="" && !in_array($ta[$i],$tipusok)) {
                        $tipusok[]=$ta[$i];
                    }
                }
            }

            if (isset($tipusok)) {
                for ($i=0;$i<count($tipusok);$i++) {
                    $tipusdisplay[$tipusok[$i]]=$tipusnevek[$tipusok[$i]];
                }
                if (isset($tipusdisplay)) {
                    asort($tipusdisplay);
                    //dupla ciklus, hogy az üzemorvosi=1 elől legyen.
                    foreach ($tipusdisplay as $key => $value) {
                        if ($key==1) {
                            if ($_SESSION["naptarszurestipus"]==0) $_SESSION["naptarszurestipus"]=$key;
                            echo "<option value='{$key}'".($_SESSION["naptarszurestipus"]==$key?" selected":"").">{$value}</option>";
                            break;
                        }
                    }
                    foreach ($tipusdisplay as $key => $value) {
                        if ($key==1) continue;
                        if ($_SESSION["naptarszurestipus"]==0) $_SESSION["naptarszurestipus"]=$key;
                        echo "<option value='{$key}'".($_SESSION["naptarszurestipus"]==$key?" selected":"").">{$value}</option>";
                    }
                }
            }

            echo "</select>";
            echo "</div>";
        }

        echo "</td><td valign='middle' style='text-align:right;width:20px;'>";

        echo "<a href='#'><img id='naptarloading' src='../images/loading.svg' style='height:24px;margin-right:10px;opacity:.7;display:none;' alt='' /></a>";

        echo "</td><td valign='middle' style='text-align:right;width:60px;'>";

        echo "<div>";
        if (isset($_SESSION["helyszin"]) && $_SESSION["helyszin"]!=0) {

            $dayDisplay = Booking_Settings::ADMIN_DAY_DISPLAY;
            //echo "<a href='#'><img id='naptarloading' src='../images/loading.svg' style='height:20px;margin-right:10px;opacity:.7;border:1px solid #000;' alt='' /></a> ";
            echo "<a onclick='naptarMove(-{$dayDisplay});return false;' href='#'><img style='height:20px;margin-right:10px;' src='images/prev.png' title='Lapozás vissza'/></a>";
            echo "<a onclick='naptarMove({$dayDisplay});return false;' href='#'><img style='height:20px;margin-right:10px;' src='images/next.png' title='Lapozás előre'/></a>";
        }
        echo "</div>";

        echo "</td></tr></table>";

        echo "<div id='foglalasnaptar' style='margin-top:10px;'>";
        echo $this->adminUtils->showAdminNaptar();
        echo "</div>";




        echo "</div>";


        echo "<div id='foglalasnaptaridopont' style='display:table-cell;vertical-align:top;padding-left:10px;'>";
        if (isset($_GET["idopont"])) echo showAdminNaptarIdopont($_GET["idopont"]);
        echo "</div>";



        echo "</div>";
        echo "</div>";


        echo "<div id='idoponteditor' style='position:fixed;bottom:0px;right:0px;background:#e0e0e0;display:none;'></div>";

    }
}

