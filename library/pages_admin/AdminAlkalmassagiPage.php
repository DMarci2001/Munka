<?php

class AdminAlkalmassagiPage extends AdminCorePage
{

    private array $companyIds = [];

    public function __construct()
    {
        parent::__construct();

        if ($this->adminUser->allCegJog()) {
            $this->companyIds = CompanyService::fesztivalCompanyIds();
        }

        if ($this->adminUser->isCegAdmin()) {
            $this->companyIds = $this->adminUser->getCegListArray();
        }
    }

    public function showPage()
    {
        //echo "select * from cegek where id in (".implode(",", $this->companyIds).")";
        $cegData = sql_query("select * from cegek where id in (".implode(",", $this->companyIds).")")->fetch(PDO::FETCH_ASSOC);


        $reservations = sql_query("SELECT f.*, c.megnev as cegnev FROM foglalasok f 
           left join cegek c on c.id = f.cegid
           where c.id in (".implode(",", $this->companyIds).") and f.aktiv=1 and f.cegid<>0 and f.cegid is not null and f.datum>'2022-01-01 00:00:00' AND f.alkalmassag<>'' AND f.alkalmassag<>'0' ORDER BY datum DESC")->fetchAll(PDO::FETCH_ASSOC);

        echo "<h1>{$cegData["megnev"]}</h1>";

        echo "<table cellpadding='0' cellspacing='0' border='0'>";

        echo "<tr style='background:#eee;font-weight: bold;'>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 5px;'>Időpont</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Neve</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Alkalmasság</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'></div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Cég</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Munkakör</td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Email</td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Irsz</td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Város</td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Cím</td>";
        //echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Műveletek</td>";
        echo "</tr>";

        foreach ($reservations as $reservation) {
            $alkalmassagText = $this->adminUtils->settings->alkalmassagvariaciok[$reservation["alkalmassag"]];
            $alkalmassagPrintURL = "";

            if (!empty($reservation["alkalmassag"])) {
                $alkalmassagPrintURL = "<a class='printbutton' href='index.php?print&template=alkalmassagipdf&fid={$reservation["id"]}&p={$reservation["pass"]}'>alkalmassági</a>";
            }

            $tc = "tcella";
            if (!isset($first)) {
                echo "<tr><td colspan='17' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            echo "<tr>";

            echo "<td nowrap valign='middle'><div class='{$tc}'>".date("Y.m.d. H:i", strtotime($reservation["datum"]))."</div></td>";
            echo "<td nowrap valign='middle'><div class='{$tc}'>{$reservation["nev"]}</div></td>";
            echo "<td nowrap valign='middle'><div class='{$tc}'>{$alkalmassagText}</div></td>";
            echo "<td nowrap valign='middle'><div class='{$tc}'>{$alkalmassagPrintURL}</div></td>";
            echo "<td nowrap valign='middle'><div class='{$tc}'>{$reservation["cegnev"]}</div></td>";
            echo "<td nowrap valign='middle'><div class='{$tc}'>{$reservation["munkakor"]}</div></td>";
            echo "<td nowrap valign='middle'><div class='{$tc}'>{$reservation["email"]}</div></td>";
            echo "<td nowrap valign='middle'><div class='{$tc}'>{$reservation["irsz"]}</div></td>";
            echo "<td nowrap valign='middle'><div class='{$tc}'>{$reservation["varos"]}</div></td>";
            echo "<td nowrap valign='middle'><div class='{$tc}'>{$reservation["utca"]}</div></td>";

            echo "</tr>";

            //echo "<tr><td colspan='18' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        echo "</table>";
    }


}