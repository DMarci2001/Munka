<?php
class CovidOltasNaploPage extends CorePage
{
    public $webText;
    private $oltoanyagok = array(
        array("id" => "sinopharm", "name" => "Sinopharm vakcina"),
        array("id" => "pfizer", "name" => "Pfizer"),
        array("id" => "johnson", "name" => "Johnson & Johnson"),
        array("id" => "moderna", "name" => "Moderna"),
        array("id" => "astrazeneca", "name" => "AstraZeneca"),
        array("id" => "szputnyik", "name" => "Szputnyik V")
    );

    public function __construct()
    {
        parent::__construct();

        $this->webText = $this->lang->webText;

        //Ha már nem él a session, dobja vissza a kezdőlapra
        if (!isset($_SESSION["user"])) {
            header("Location:index.php?page=booking");
        }

        if (isset($_POST["uj-oltas-mentes"])) {
            sql_query(
                "INSERT INTO covid_oltas_naplo SET userid=?, oltas_tipus=?,oltas_datum=?,sorszam=?,regdatum=NOW()",
                array($_SESSION["user"]["id"], $_POST["vaccination-type"], $_POST["vaccine-date"], $_POST["serial-number"])
            );
            $_POST = null;
        }

        if (isset($_POST["modify_covid_data"]) && $_POST["modify_covid_data"] == true) {
            if ($data = sql_fetch_array(sql_query("SELECT id,regdatum,oltas_tipus as 'vaccination-type',oltas_datum as 'vaccine-date',sorszam as 'serial-number' FROM covid_oltas_naplo WHERE id=?", array($_POST["covId"])))) {
                echo $this->new_covid_oltas_naplo($data, "modify");
            }
            die();
        }

        if (isset($_POST["save_covid_data"]) && $_POST["save_covid_data"] == true) {
            $query = sql_query("SELECT * FROM covid_oltas_naplo WHERE id=?", array($_POST["covId"]));

            if (!empty(sql_num_rows($query))) {
                sql_query("UPDATE covid_oltas_naplo SET oltas_tipus=?,oltas_datum=?,sorszam=? WHERE id=?", array($_POST["oltas_tipus"], $_POST["oltas_datum"], $_POST["sorszam"], $_POST["covId"]));

                $covid_data = $this->select_covid_oltas_naplo($_POST["covId"]);
                echo "<td>{$covid_data[0]["regdatum"]}</td>";
                echo "<td>{$covid_data[0]["oltas_tipus"]}</td>";
                echo "<td>{$covid_data[0]["oltas_datum"]}</td>";
                echo "<td><-Fájl helye-></td>";
                echo "<td>{$covid_data[0]["sorszam"]}</td>";
                echo "<td>";
                echo "    <i title=\"Oltás adatainak módosítása\" onClick='modify_covid_data({$covid_data[0]["id"]})' class=\"fas fa-pen covid-oltas-buttons\"></i>&nbsp;&nbsp;";
                echo "    <i title=\"Oltási bejegyzés törlése\" onClick='delete_covid_data({$covid_data[0]["id"]})' class=\"fas fa-trash covid-oltas-buttons\"></i>";
                echo "</td>";
            }

            die();
        }


        if (isset($_POST["cancel_covid_data"]) && $_POST["cancel_covid_data"] == true) {

            $covid_data = $this->select_covid_oltas_naplo($_POST["covId"]);

            echo "<td>{$covid_data[0]["regdatum"]}</td>";
            echo "<td>{$covid_data[0]["oltas_tipus"]}</td>";
            echo "<td>{$covid_data[0]["oltas_datum"]}</td>";
            echo "<td><-Fájl helye-></td>";
            echo "<td>{$covid_data[0]["sorszam"]}</td>";
            echo "<td>";
            echo "    <i title=\"Oltás adatainak módosítása\" onClick='modify_covid_data({$covid_data[0]["id"]})' class=\"fas fa-pen covid-oltas-buttons\"></i>&nbsp;&nbsp;";
            echo "    <i title=\"Oltási bejegyzés törlése\" onClick='delete_covid_data({$covid_data[0]["id"]})' class=\"fas fa-trash covid-oltas-buttons\"></i>";
            echo "</td>";
            die();
        }
    }
    public function showPage()
    {
        echo "<form method=\"POST\" enctype=\"multipart/form-data\">";
        echo "  <table class=\"covid-oltas-lista\">";
        echo "      <tr><td>Rögzités dátuma</td><td>Oltás típusa</td><td>Oltás dátuma</td><td>Védettségi igazolvány QR kód</td><td>Hanyadik oltás</td><td></td></tr>";

        //Meglévő oltások listázása:
        $covid_data = $this->select_covid_oltas_naplo();

        for ($i = 0; $i < count($covid_data); $i++) {
            echo "  <tr id=\"covid-data-id-{$covid_data[$i]["id"]}\">";
            echo "      <td>{$covid_data[$i]["regdatum"]}</td>";
            echo "      <td>" . $this->oltoanyag_nev($covid_data[$i]["oltas_tipus"]) . "</td>";
            echo "      <td>{$covid_data[$i]["oltas_datum"]}</td>";
            echo "      <td><-Fájl helye-></td>";
            echo "      <td>{$covid_data[$i]["sorszam"]}</td>";
            echo "      <td>";
            echo "          <i title=\"Oltás adatainak módosítása\" onClick='modify_covid_data({$covid_data[$i]["id"]})' class=\"fas fa-pen covid-oltas-buttons\"></i>&nbsp;&nbsp;";
            echo "          <i title=\"Oltási bejegyzés törlése\" onClick='delete_covid_data({$covid_data[$i]["id"]})' class=\"fas fa-trash covid-oltas-buttons\"></i>";
            echo "      </td>";
            echo "  </tr>";
        }



        //Új oltás rögzitése:
        echo "<tr>" . $this->new_covid_oltas_naplo($_POST) . "</tr>";
        echo "      <tr>";
        echo "          <td colspan=\"6\" style=\"text-align:center\"><input type=\"submit\" class=\"covid-oltas-mentes-button\" name=\"uj-oltas-mentes\" value=\"Oltás mentése\" /></td>";
        echo "      </tr>";
        echo "  </table>";
        echo "</form>";
    }

    private function oltoanyag_nev($id)
    {
        $key = array_search($id, array_column($this->oltoanyagok, 'id'));
        return $this->oltoanyagok[$key]["name"];
    }

    private function oltoanyagok($post)
    {
        $html = $extraId = "";

        if (!empty($post) && isset($post["id"])) {
            $extraId = "-" . $post["id"];
        }

        $html .= "<select class=\"design-put oltas-mezo\" name=\"vaccination-type" . $extraId . "\">";
        $html .= "  <option value=\"0\">Vakcina</option>";

        foreach ($this->oltoanyagok as $index => $value) {
            $html .= "<option " . (isset($post["vaccination-type"]) && $post["vaccination-type"] == $this->oltoanyagok[$index]["id"] ? "selected=\"true\"" : "") . " value=\"{$this->oltoanyagok[$index]["id"]}\">" . $this->oltoanyagok[$index]["name"] . "</option>";
        }

        $html .= "</select>";
        return $html;
    }

    private function napFilter($post)
    {
        $html = $extraId = "";

        if (!empty($post) && isset($post["id"])) {
            $extraId = "-" . $post["id"];
        }

        $html .= "<input class=\"napfilter\" id=\"napfilter\" value=\"" . (isset($post["vaccine-date"]) ? $post["vaccine-date"] : "") . "\" name=\"vaccine-date{$extraId}\" value=\"\" style=\"font-size:18px;background-color:#eee;color:#444;margin-right:10px;border:1px solid #ccc;text-align:center;\" data-page=\"{$_GET["page"]}\" />";
        return $html;
    }

    private function select_covid_oltas_naplo($id = null)
    {
        if (isset($_SESSION["user"]["id"])) {
            if (!empty($id)) {
                $query = sql_query("SELECT * FROM covid_oltas_naplo WHERE userid=? and id=?", array($_SESSION["user"]["id"], $id));
            } else {
                $query = sql_query("SELECT * FROM covid_oltas_naplo WHERE userid=?", array($_SESSION["user"]["id"]));
            }

            if (!empty(sql_num_rows($query))) {
                while ($fetch = sql_fetch_array($query)) $data[] = $fetch;

                return $data;
            }
        }
    }
    private function new_covid_oltas_naplo($post, $action = null)
    {
        $html = $buttons = $extraId = "";
        if (!empty($action)) {
            if ($action == "modify") {
                $buttons .= "<i title=\"Módositás mentése\" onClick='save_covid_data({$post["id"]})' class=\"fas fa-save covid-oltas-buttons\"></i>&nbsp;&nbsp;";
                $buttons .= "<i title=\"Megszakitás\" onClick='cancel_covid_data({$post["id"]})' class=\"fas fa-times covid-oltas-buttons\"></i>";
            }
            $extraId = "-" . $post["id"];
        }


        $html .= "<td>" . (!empty($action) ? $post["regdatum"] : "") . "</td>";
        $html .= "<td>" . $this->oltoanyagok($post) . "</td>";
        $html .= "<td>" . $this->napFilter($post) . "</td>";
        $html .= "<td><input type=\"file\" id=\"covid-validation-image\" name=\"covid-validation-image{$extraId}\" /></td>";
        $html .= "<td><input type=\"number\" class=\"design-put oltas-mezo\" value=\"" . (isset($post["serial-number"]) ? $post["serial-number"] : "") . "\" name=\"serial-number{$extraId}\" value=\"\" /></td>";
        $html .= "<td>" . (!empty($action) ? $buttons : "") . "</td>";
        return $html;
    }
}
