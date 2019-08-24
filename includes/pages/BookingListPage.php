<?php

class BookingListPage extends CorePage {

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;

        if (isset($_GET["dodeletereservation"])) {
            $this->bookingService->deleteReservation($_GET["id"], $_GET["rk"]);
            header("location:index.php?page=bookingdeletesuccessful");
            die();
        }

    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->displayFejlec($webText["foglalasok"]);
        echo $this->showFormErrors();
        echo $this->showPageDescription($webText["foglalaslisttext"]);

        $res=sql_query("SELECT c.megnev as cegnev,t.`megnev` AS tipusnev,t.megnev_de as tipusnev_de,t.megnev_en as tipusnev_en,h.cim AS helyszinnev,f.* FROM foglalasok f
        LEFT JOIN szurestipusok t ON t.`id`=f.`szurestipusid`
        LEFT JOIN helyszinek h ON h.`id`=f.`helyszinid`
        left join cegek c on c.id=f.cegid
        WHERE f.paciensid=? order by f.datum desc", array($_SESSION["user"]["id"]));

        echo "<table style='font-size:16px;margin-top:20px;' cellpadding='0' cellspacing='0'>";

        while ($row = sql_fetch_array($res)) {
            if ($_COOKIE["lang"]!="hu" && trim($row["tipusnev_{$_COOKIE["lang"]}"])!="") $row["tipusnev"]=$row["tipusnev_{$_COOKIE["lang"]}"];
            echo "<tr>";
            echo "<td style='font-size:24px;vertical-align: top;padding:10px 0px;border-top:1px solid #ccc;'>".substr($row["datum"],0,16)."&nbsp;&nbsp;</td>";
            echo "<td style='font-size:14px;vertical-align: top;padding:10px 0px;border-top:1px solid #ccc;'><strong>{$row["tipusnev"]}</strong><br/>{$row["helyszinnev"]} {$row["cegnev"]}&nbsp;&nbsp";
            if (strtotime("now + 6 hour")<strtotime($row["datum"])) {
                echo "<br/>[ <a onclick='return confirm(\"{$webText["idopontdelconfirm"]}\");' href='index.php?page={$_GET["page"]}&dodeletereservation&id={$row["id"]}&rk={$row["rkod"]}'>{$webText["idoponttorlese"]}</a> ]";
            }
            echo "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }
}

