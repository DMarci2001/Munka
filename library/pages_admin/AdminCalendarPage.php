<?php

class AdminCalendarPage extends AdminCorePage
{

    private $bookingService;
    private $adminCalendarService;
    private $shift;

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;
        $this->bookingService = new BookingService();
        $this->adminCalendarService = new AdminCalendarService();

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
        echo "<div>";

        echo $this->companySelector();

        echo "<table cellpadding='0' cellspacing='0' style='margin-top:10px;'>";
        echo "<tr>";

        echo "<td valign='middle'>";
        echo $this->screeningSelector();
        echo "</td>";

        echo "<td valign='middle' style='text-align:right;width:60px;padding-left:20px;'>";
        echo "<div>";
        if (isset($_SESSION["helyszin"]) && $_SESSION["helyszin"]!=0) {
            $dayDisplay = 7;
            echo "<a onclick='naptarMove(-{$dayDisplay});return false;' href='#'><img style='height:20px;margin-right:10px;' src='images/prev.png' title='Előző hét'/></a>";
            echo "<a onclick='naptarMove({$dayDisplay});return false;' href='#'><img style='height:20px;margin-right:10px;' src='images/next.png' title='következő hét'/></a>";
        }
        echo "</div>";
        echo "</td>";

        echo "<td valign='middle' style='text-align:right;width:20px;'>";
        echo "<a href='#'><img id='naptarloading' src='/admin/images/loading.svg' style='height:24px;margin-right:10px;opacity:.7;display:none;' alt='' /></a>";
        echo "</td>";

        echo "</tr></table>";

        echo "<div id='foglalasnaptar' style='margin-top:10px;'>";
        echo $this->adminCalendarService->showAdminNaptar();
        echo "</div>";


        echo "<div id='foglalasnaptaridopont' style='display:table-cell;vertical-align:top;padding-left:10px;'>";
        if (isset($_GET["idopont"])) {
            echo showAdminNaptarIdopont($_GET["idopont"]);
        }
        echo "</div>";

        echo "</div>";

        echo "<div id='idoponteditor'></div>";
    }

    private function companySelector() {
        $htmlout = "";
        $resh=sql_query("select * from cegek order by megnev");
        while ($rowh=sql_fetch_array($resh)) {
            $cegek[$rowh["id"]]=$rowh["megnev"];
        }

        $htmlout.= "<div>";
        $htmlout.= "<select name='helyszin' onchange='setHelyszin(this.value);' style='width:620px;'>";
        $htmlout.= "<option value='0'>Válassz helyszínt!</option>";

        $resc = sql_query("select * from cegek where aktiv=1 order by id<>?, megnev", array($_SESSION["helyszindata"]["id"]));
        while ($rowc=sql_fetch_array($resc)) {
            if ($_SESSION["adminuser"]["jogosultsag"]<2 && substr_count($_SESSION["adminuser"]["cegjog"],"|{$rowc["id"]}|")==0) {
                continue;
            }

            $res = sql_query("SELECT h.* FROM helyszinek h WHERE instr(ceglink,?) ORDER BY h.cim", array("|{$rowc["id"]}|"));
            if (sql_num_rows($res) == 0) {
                continue;
            }

            $htmlout.= "<option value='0' disabled style='background:#bbb;color:#fff;'>{$rowc["megnev"]}</option>";
            while ($rowt = sql_fetch_array($res)) {

                $color="#000";
                if (substr_count($rowt["cim"],"Martin ")>0 && $rowc["id"]==15) $color="#a00;";
                if (substr_count($rowt["cim"],"Martin ")>0 && $rowc["id"]==42) $color="#00a;";

                $htmlout.= "<option style='color:{$color}' value='{$rowt["id"]}-{$rowc["id"]}'".("{$_SESSION["helyszin"]}-{$_SESSION["helyszinceg"]}"=="{$rowt["id"]}-{$rowc["id"]}"?" selected":"").">{$rowt["cim"]} ({$cegek[$rowc["id"]]})</option>";
            }

        }


        $htmlout.= "</select>";
        $htmlout.= "</div>";
        return $htmlout;
    }

    private function screeningSelector() {
        $htmlout = "";
        if ($_SESSION["helyszinceg"]!=0) {
            $htmlout.= "<div style=''>";
            $htmlout.= "<select name='helyszin' onchange='setNaptarSzuresTipus(this.value);'>";
            $htmlout.= "<option value='0'>Válassz szűréstípust!</option>";

            $rest = sql_query("select * from szurestipusok");
            $tipusnevek = [];
            while ($rowt = sql_fetch_array($rest)) {
                $tipusnevek[$rowt["id"]] = $rowt["megnev"];
            }

            $res = sql_query("SELECT cegid,tipusok FROM orvos_beosztas b WHERE b.helyszinid=? and b.tol<>0 and b.ig<>0", array($_SESSION["helyszin"]));
            $tipusok = [];
            while ($row = sql_fetch_array($res)) {
                if ($_SESSION["adminuser"]["jogosultsag"]<2 && substr_count($_SESSION["adminuser"]["cegjog"],"|{$row["cegid"]}|")==0) {
                    continue;
                }

                $ta = explode("|",$row["tipusok"]);
                for ($i=0;$i<count($ta);$i++) {
                    if (!empty($ta[$i]) && !in_array($ta[$i], $tipusok)) {
                        $tipusok[] = $ta[$i];
                    }
                }
            }

            if (isset($tipusok)) {
                for ($i=0;$i<count($tipusok);$i++) {
                    if (isset($tipusnevek[$tipusok[$i]])) {
                        $tipusdisplay[$tipusok[$i]] = $tipusnevek[$tipusok[$i]];
                    }
                }
                if (isset($tipusdisplay)) {
                    asort($tipusdisplay);
                    //dupla ciklus, hogy az üzemorvosi=1 elől legyen.
                    foreach ($tipusdisplay as $key => $value) {
                        if ($key==1) {
                            if ($_SESSION["naptarszurestipus"]==0) {
                                $_SESSION["naptarszurestipus"]=$key;
                            }
                            $htmlout.= "<option value='{$key}'".($_SESSION["naptarszurestipus"]==$key?" selected":"").">{$value}</option>";
                            break;
                        }
                    }
                    foreach ($tipusdisplay as $key => $value) {
                        if ($key==1) {
                            continue;
                        }
                        if ($_SESSION["naptarszurestipus"]==0) {
                            $_SESSION["naptarszurestipus"]=$key;
                        }
                        $htmlout.= "<option value='{$key}'".($_SESSION["naptarszurestipus"]==$key?" selected":"").">{$value}</option>";
                    }
                }
            }

            $htmlout.= "</select>";
            $htmlout.= "</div>";
        }
        return $htmlout;
    }


}

