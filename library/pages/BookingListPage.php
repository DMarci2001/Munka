<?php

class BookingListPage extends CorePage {

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;

        if (isset($_GET["dodeletereservation"])) {
            $bookingService = new BookingService();
            $GLOBALS["extraloginfo"] = "felhasználó adatlapján törölte";
            $bookingService->deleteReservation($_GET["id"], $_GET["rk"]);
            header("location:index.php?page=bookingdeletesuccessful");
            die();
        }

    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->displayFejlec($webText["foglalasok"]);
        echo $this->showFormErrors();
        echo $this->showPageDescription($webText["foglalaslisttext"]);

        $res = sql_query("SELECT c.megnev as cegnev,t.`megnev` AS tipusnev,t.megnev_de as tipusnev_de,t.megnev_en as tipusnev_en,h.cim AS helyszinnev,f.* FROM foglalasok f
        LEFT JOIN szurestipusok t ON t.`id`=f.`szurestipusid`
        LEFT JOIN helyszinek h ON h.`id`=f.`helyszinid`
        left join cegek c on c.id=f.cegid
        WHERE f.paciensid=? and f.aktiv=1 and f.parentid=0 order by f.datum desc", array($_SESSION["user"]["id"]));

        if (sql_num_rows($res) == 0) {
            echo "<div>".$this->lang->getText("emptyreservationlist","Önnek még nincs foglalása")."</div>";
        }

        echo "<table style='font-size:16px;margin-top:20px;' cellpadding='0' cellspacing='0'>";

        while ($row = sql_fetch_array($res)) {
            $rescs = sql_query("SELECT t.`megnev` AS tipusnev,t.megnev_de as tipusnev_de,t.megnev_en as tipusnev_en,f.* FROM foglalasok f
            LEFT JOIN szurestipusok t ON t.`id`=f.`szurestipusid`
            WHERE f.paciensid=? and f.aktiv=1 and f.parentid=? order by f.datum desc", array($_SESSION["user"]["id"], $row["id"]));

            $packText = "";
            while ($rowcs = sql_fetch_array($rescs)) {
                if ($_COOKIE["lang"]!="hu" && trim($rowcs["tipusnev_{$_COOKIE["lang"]}"])!="") {
                    $rowcs["tipusnev"] = $rowcs["tipusnev_{$_COOKIE["lang"]}"];
                }
                $packText.="<div>{$rowcs["tipusnev"]}</div>";
            }


            if ($_COOKIE["lang"]!="hu" && trim($row["tipusnev_{$_COOKIE["lang"]}"])!="") {
                $row["tipusnev"] = $row["tipusnev_{$_COOKIE["lang"]}"];
            }
            echo "<tr>";
            echo "<td style='font-size:24px;vertical-align: top;padding:10px 0px;'>".substr($row["datum"],0,16)."&nbsp;&nbsp;</td>";
            echo "<td style='font-size:14px;vertical-align: top;padding:10px 0px;'><strong>{$row["tipusnev"]}</strong><br/>{$row["helyszinnev"]} {$row["cegnev"]}&nbsp;&nbsp";

            if (strtotime("now + 6 hour")<strtotime($row["datum"])) {
                echo "<br/>[ <a onclick='return confirm(\"{$webText["idopontdelconfirm"]}\");' href='index.php?page={$_GET["page"]}&dodeletereservation&id={$row["id"]}&rk={$row["rkod"]}'>{$webText["idoponttorlese"]}</a> ]";
            }

            if ($packText != "") {
                echo "<div><strong>csomag tartalma:</strong>{$packText}</div>";
            }

            echo "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }
}

