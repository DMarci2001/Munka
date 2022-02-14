<?php

class AdminPrebookingmanagementPage extends AdminCorePage
{

    private $error;
    public  $success;

    public function __construct()
    {
    }

    public function showPage()
    {
        /*
        Mit akarok itt látni?
        Listázni akarom, az összes foglalást ami egyezetéssel jár.
        Hogyan különböztetem meg őket? Ezeknek az időpontja a kövertkező: 1900-01-01 00:00:01, erre kell rá keresnem az adatbázisban.
        A alábbi információkat kell itt megjelenitenem: páciens adatok, adat rögzités ideje, elérhetőség

        Miket tudjon kezelni az adminisztrátor?
        - Tudja jelölni a bejegyzés státuszát (Kapcsolat felvétel megkezdődött az orvossal, időpont egyezetve az orvossal, időpont egyeztetve a dolgozóval, sikeres egyeztetés vagy további egyezetés szükséges)
        - Lehessen küldeni az adatokat egyből a dokirexbe/zeusba.
        - Kilehessen állitani akár itt a beutalót egyből letöltésre.
        - Látható legyen az orvos elérhetősége egyből, hogy az adminisztrátor egyből eltudja kezdeni a munkát, további keresés nélkül is.
        */
        echo $this->showPreBookingList();
    }

    private function showPreBookingList()
    {

        $html = $columnTitle = $content = "";
        $row = 0;

        $columns = array("Bejegyzés ideje", "Teljesnév", "Szül. dátum", "Elérhetőség", "Cég", "Orvos adatai", "Megjegyzés", "Beutaló", "Státusz");
        $columnTitle = "<tr><td>" . implode("</td><td>", $columns) . "</tr>";


        $bookingQuery = sql_query(
            "SELECT fogl.*,c.megnev AS cegnev,h.cim AS helyszin,o.nev AS orvosnev,o.tel as orvostelefon,o.email AS orvosemail FROM foglalasok fogl
             LEFT JOIN cegek c ON c.id=fogl.cegid
             LEFT JOIN helyszinek h ON h.id=fogl.helyszinid
             LEFT JOIN orvosok o ON o.id=fogl.orvosassigned
             WHERE fogl.datum = ?
             ORDER BY fogl.regdatum DESC",
            array("1900-01-01 00:00:01")
        );

        while ($booking = sql_fetch_array($bookingQuery)) {

            $content .= "<tr>";
            $content .= "<td>{$booking["regdatum"]}</td>";
            $content .= "<td>{$booking["nev"]}</td>";
            $content .= "<td>{$booking["szuldatum"]}</td>";
            $content .= "<td style=\"text-align:center\">";
            $content .=     "<div style=\"padding:5px\">{$booking["email"]}</div>";
            $content .=     "<div style=\"padding:5px\">{$booking["telefon"]}</div>";
            $content .=  "</td>";
            $content .= "<td>{$booking["cegnev"]}</td>";
            $content .= "<td style=\"text-align:center\">";
            $content .=     "<div style=\"padding:3px\">{$booking["orvosnev"]}</div>";
            $content .=     "<div style=\"padding:3px\">{$booking["orvostelefon"]}</div>";
            $content .=     "<div style=\"padding:3px\">{$booking["orvosemail"]}</div>";
            $content .=     "<div style=\"padding:3px\">{$booking["helyszin"]}</div>";
            $content .=  "</td>";
            $content .= "<td>{$booking["megj"]}</td>";
            $content .= "<td style=\"text-align:center\">".$this->referalDocAsset()."</td>";

            $content .= "</tr>";
        }

        echo "<table class=\"pre_booking_management_table\">";
        echo $columnTitle;
        echo $content;
        echo "</table>";
    }

    private function referalDocAsset()
    {
        $content = $html = "";
        $availableAssets = array(
            array("name" => "Alpinista", "value" => "vf-alpin"),
            array("name" => "Képernyő előtti", "value" => "vf-kepernyo"),
            array("name" => "Oszlopsoros", "value" => "vf-oszlop"),
            array("name" => "Raktáros", "value" => "vf-raktas")
        );

        $content .= "<select>";
        foreach ($availableAssets as $asset) {
            $content .= "<option value=\"{$asset["value"]}\">{$asset["name"]}</option>";
        }
        $content .= "</select>&nbsp;<input type=\"button\" value=\"Letöltés\">";

        return $content;
    }
}
