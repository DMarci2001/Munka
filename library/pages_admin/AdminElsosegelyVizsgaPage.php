<?php

class AdminElsosegelyVizsgaPage extends AdminCorePage
{

    private $answerWords = ["", "A", "B", "C", "D", "E"];

    public function __construct()
    {
        parent::__construct();


    }

    public function showPage()
    {
        //if (!$this->adminUtils->szurestipusModJog()) {
        //    return;
        //}


        $datumok = sql_query("SELECT DATE(datum) AS datum FROM vizsgavalaszok GROUP BY DATE(datum) ORDER BY datum DESC")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($datumok as $datum) {
            $datum = $datum["datum"];

            echo "<h1>{$datum} nap kitöltött tesztek</h1>";

            $valaszok = sql_query("SELECT * FROM vizsgavalaszok where date(datum)=? order by datum", [$datum])->fetchAll(PDO::FETCH_ASSOC);


            echo "<table cellpadding='0' cellspacing='0' border='0'>";

            echo "<tr style='background:#eee;'>";
            echo "<td nowrap valign='top' style='padding:5px 5px 5px 5px;'>Időpont</div></td>";
            echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Kitöltés</div></td>";
            echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Vizsgázó neve</div></td>";
            //echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Oktatási azonosító</div></td>";
            echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Iskolai végzettség</div></td>";
            echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Szül. dátum</td>";
            echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Email</td>";
            echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Irsz</td>";
            echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Város</td>";
            echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Cím</td>";
            //echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Műveletek</td>";
            echo "</tr>";

            foreach ($valaszok as $row) {
                $data = json_decode($row["adatok"], JSON_OBJECT_AS_ARRAY);

                $tc = "tcella";
                if (!isset($first)) {
                    echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                    $first = 1;
                }
                echo "<tr>";

                $szuldatum = $data["szuldatumev"]."-".substr("00{$data["szuldatumho"]}", -2)."-".substr("00{$data["szuldatumnap"]}", -2);

                echo "<td nowrap valign='top'><div class='{$tc}'>{$row["datum"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'><a onclick='$(\"#answersrow{$row["id"]}\").toggle();return false;' href='#'>{$row["osszesvalasz"]}/{$row["helyesvalasz"]}</a></div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$data["nev"]}</div></td>";
                //echo "<td nowrap valign='top'><div class='{$tc}'>{$data["oktatasiazonosito"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$data["iskolavegzettseg"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$szuldatum}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$data["email"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$data["irsz"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$data["varos"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$data["cim"]}</div></td>";

                /*
                echo "<td nowrap valign='top'><div class='{$tc}'>";
                echo "[<a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&getimage={$row["id"]}'>kép megtekintése</a>] ";
                echo "[<a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&downloaddicomfile={$row["id"]}'>DICOM file letöltése</a>]";
                echo "</td>";
                */

                echo "</tr>";
                echo "<tr id='answersrow{$row["id"]}' style='display:none;'><td colspan='18' style='padding-bottom:10px;'>";

                $questions = sql_query("select * from vizsgakerdesek where id in ({$data["questionids"]})")->fetchAll(PDO::FETCH_ASSOC);

                foreach ($questions as $question) {
                    $valasz = $data["question{$question["id"]}"];
                    $sorok = explode("<br>", $question["kerdes"]);
                    echo "<div>".reset($sorok)." ". $this->answerWords[$valasz]." <strong>".($valasz == $question["helyesvalasz"] ? "<span style='color:#0a0;'>HELYES</span>" : "<span style='color:#a00;'>HELYTELEN</span>")."</strong></div>";
                }


                echo "</td></tr>";
                echo "<tr><td colspan='18' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
            }
            echo "</table>";

        }



    }


}