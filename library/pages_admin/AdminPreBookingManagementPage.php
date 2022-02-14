<?php

use mikehaertl\pdftk\Pdf;

class AdminPrebookingmanagementPage extends AdminCorePage
{

    private $error;
    public  $success;

    private $availableStatus = array(
        array("name" => "Nincs még elkezdve", "value" => "new", "object" => "<i id=\"pbindicator#id#\" style=\"color:red;font-size:16px\" class=\"fas fa-circle\"></i>"),
        array("name" => "Folyamatban", "value" => "in-progress", "object" => "<i id=\"pbindicator#id#\" style=\"color:orange;font-size:16px\" class=\"fas fa-circle\"></i>"),
        array("name" => "Kész", "value" => "finished", "object" => "<i id=\"pbindicator#id#\" style=\"color:#60d22f;font-size:16px\" class=\"fas fa-circle\"></i>")
    );

    private $availableDocs = array(
        array("name" => "Képernyő előtti", "value" => "vf-kepernyo", "filename" => "../../public/admin/templates/vf-kepernyo.pdf"),
        array("name" => "Oszlopsoros", "value" => "vf-oszlop", "filename" => "../../public/admin/templates/vf-oszlop.pdf"),
        array("name" => "Alpinista", "value" => "vf-alpin", "filename" => "../../public/admin/templates/vf-alpin.pdf"),
        array("name" => "Raktáros", "value" => "vf-raktar", "filename" => "../../public/admin/templates/vf-raktar.pdf")
    );

    public function __construct()
    {
        if (isset($_POST["setPreBookingStatus"])) {
            if ($booking = sql_query("SELECT * FROM foglalasok WHERE id=?", array($_POST["setPreBookingStatus"]))) {
                $key = array_search($_POST["indicator"], array_column($this->availableStatus, "value"));
                if ($key !== false) {
                    sql_query("UPDATE foglalasok SET prebookingstatus=? WHERE id=?", array($_POST["indicator"], $_POST["setPreBookingStatus"]));
                    die($this->statusIndicatorReferences(array("id" => $_POST["setPreBookingStatus"], "prebookingstatus" => $_POST["indicator"])));
                }
            }
            die();
        }

        if (isset($_POST["autoSaveTextArea"])) {
            if ($booking = sql_query("SELECT * FROM foglalasok WHERE id=?", array($_POST["autoSaveTextArea"]))) {
                sql_query("UPDATE foglalasok SET admin_megj = ? WHERE id = ?", array($_POST["text"], $_POST["autoSaveTextArea"]));
            }
            die();
        }

        if (isset($_POST["downloadTargetFile"])) {
            if ($data = sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=? and datum=?", array($_POST["foglid"], "1900-01-01 00:00:01")))) {

                $filename=$this->createReferalDoc($data, $_POST["referaldocselector"]);

                header("Content-type: application/pdf");
                header("Content-Disposition: attachment; filename=" . $filename);
                @readfile("../../public/admin/templates/" . $filename);
    
                unlink("../../public/admin/templates/" . $filename);
            }
        }
    }

    public function showPage()
    {
        //Azokat a foglalasokat ahol nincsen a prebookingstatus meghatározva és előfoglalás, átállítom "new"-ra az értéket.
        sql_query("UPDATE foglalasok SET prebookingstatus = 'new' WHERE datum = '1900-01-01 00:00:01' AND prebookingstatus IS NULL");
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

        $columns = array("", "Bejegyzés ideje", "Páciens adatok", "Elérhetőség", "Cég", "Orvos adatai", "Megjegyzés", "Beutaló", "Státusz", "Megjegyzés");
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
            $content .= "<td id=\"pbindicatorcontainer{$booking["id"]}\">" . $this->statusIndicatorReferences($booking) . "</td>";
            $content .= "<td>{$booking["regdatum"]}</td>";
            $content .= "<td style=\"text-align:center\">";
            $content .=     "<div style=\"padding:5px\">{$booking["nev"]}</div>";
            $content .=     "<div style=\"padding:5px\">{$booking["szuldatum"]}</div>";
            $content .=     "<div style=\"padding:5px\">{$booking["taj"]}</div>";
            $content .=  "</td>";
            $content .= "<td style=\"text-align:center\">";
            $content .=     "<div class=\"tooltip\" onclick='copyToClipboard($(\"#workerEmail\"))' onmouseout='outFunc($(\"#workerEmail\"))' style=\"padding:5px;cursor:pointer\">";
            $content .=         "<span id=\"workerEmail\">{$booking["email"]}</span>";
            $content .=         "<span class=\"tooltiptext\" id=\"workerEmailtooltip\">Copy to clipboard</span>";
            $content .=     "</div>";
            $content .=     "<div style=\"padding:5px\">{$booking["telefon"]}</div>";
            $content .=  "</td>";
            $content .= "<td>{$booking["cegnev"]}</td>";
            $content .= "<td style=\"text-align:center\">";
            $content .=     "<div style=\"padding:3px\">{$booking["orvosnev"]}</div>";
            $content .=     "<div style=\"padding:3px\">{$booking["orvostelefon"]}</div>";
            $content .=     "<div class=\"tooltip\" onclick='copyToClipboard($(\"#doctorEmail\"))' onmouseout='outFunc($(\"#doctorEmail\"))' style=\"padding:5px;cursor:pointer\">";
            $content .=         "<span id=\"doctorEmail\">{$booking["orvosemail"]}</span>";
            $content .=         "<span class=\"tooltiptext\" id=\"doctorEmailtooltip\">Copy to clipboard</span>";
            $content .=     "</div>";
            $content .=     "<div style=\"padding:3px\">{$booking["helyszin"]}</div>";
            $content .=  "</td>";
            $content .= "<td>{$booking["megj"]}</td>";
            $content .= "<td style=\"text-align:center\">" . $this->referalDocReferences($booking) . "</td>";
            $content .= "<td style=\"text-align:center\">" . $this->statusReferences($booking) . "</td>";
            $content .= "<td style=\"text-align:center\"><textarea onkeyup='autoSaveTextArea({$booking["id"]},$(this).val())' id=\"pbtext{$booking["id"]}\" style=\"width:250px;height:80px;\">{$booking["admin_megj"]}</textarea></td>";

            $content .= "</tr>";
        }

        
        echo "<table class=\"pre_booking_management_table\">";
        echo $columnTitle;
        echo $content;
        echo "</table>";
        echo "";
    }

    private function referalDocReferences($data)
    {
        $content = "";

        $content .= "<form method=\"POST\">";
        $content .= "<input type=\"hidden\" name=\"foglid\" value=\"{$data["id"]}\">";
        $content .= "<select name=\"referaldocselector\">";
        foreach ($this->availableDocs as $doc) {
            $content .= "<option value=\"{$doc["value"]}\">{$doc["name"]}</option>";
        }
        $content .= "</select>&nbsp;<input type=\"submit\" name=\"downloadTargetFile\" value=\"Letöltés\"/>";
        $content .= "</form>";
        return $content;
    }

    private function statusReferences($data)
    {
        $content = "";

        $content .= "<select name=\"pbstatus\" onChange='setPreBookingStatus(\"{$data["id"]}\",$(this).val())'>";
        foreach ($this->availableStatus as $status) {
            $content .= "<option " . ($status["value"] == $data["prebookingstatus"] ? "selected" : null) . " value=\"{$status["value"]}\">{$status["name"]}</option>";
        }
        $content .= "</select>";

        return $content;
    }

    private function statusIndicatorReferences($data)
    {
        foreach ($this->availableStatus as $indicator) {
            if ($data["prebookingstatus"] == $indicator["value"]) {
                str_replace("#id#", $data["id"], $indicator["object"]);
                return $indicator["object"];
            }
        }
    }

    private function createReferalDoc($data, $docName)
    {
        $key = array_search($docName, array_column($this->availableDocs, "value"));
        $pdf = new Pdf($this->availableDocs[$key]["filename"]);

        $filename = "{$data["nev"]}-{$data["taj"]}-{$data["szuldatum"]}-{$this->availableDocs[$key]["name"]}-(" . rand(200, 1200000) . ").pdf";

        $input = [
            "nev" => $data["nev"],
            "taj" => $data["taj"],
            "szulev" => date("Y", strtotime($data["szuldatum"])),
            "szulho" => date("m", strtotime($data["szuldatum"])),
            "szulnap" => date("d", strtotime($data["szuldatum"])),
            "munkakor" => $data["munkakor"],
            "lakcim" => $data["irsz"] . " " . $data["varos"] . ", " . $data["utca"]
        ];


        $result = $pdf->fillForm($input)
            ->flatten()
            ->saveAs("../../public/admin/templates/" . $filename);

        if ($result === false) {
            $error = $pdf->getError();

            var_dump($error);
        } else {
            return $filename;
        }
    }
}
