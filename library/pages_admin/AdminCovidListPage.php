<?php

class AdminCovidListPage extends AdminCorePage
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

        $docAgent = new DocAgent();

        $users = sql_query("SELECT n.*, f.taj, f.nev, f.email, f.telefon, f.nocovid1, f.nocovid2, d1.id AS covidigazolasid, d1.kod AS covidigazolaskod, d2.id AS covidegsid, d2.kod AS covidegskod FROM covid_oltas_naplo n
            LEFT JOIN felhasznalok f ON f.id = n.userid
            LEFT JOIN dokumentumok d1 ON d1.dataid = n.id AND d1.assetid='covidpassimage'
            LEFT JOIN dokumentumok d2 ON d2.dataid = f.id AND d2.assetid='covidegsimage'
            WHERE f.id IS NOT NULL
            GROUP BY n.id
            ORDER BY f.nev, f.id, n.sorszam")->fetchAll(PDO::FETCH_ASSOC);

        echo "<table cellpadding='0' cellspacing='0' border='0'>";
        echo "<tr style='background:#eee;'>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 5px;'>Név</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>TAJ</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Email</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Telefon</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Egészségügyi ok</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Nem kér</div></td>";
        echo "</tr>";

        $lastUser = "";

        foreach ($users as $user) {
            $tc = "tcella";

            if ($lastUser != $user["userid"]) {
                $lastUser = $user["userid"];
                //echo "<tr><td colspan='18' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                echo "<tr style='background: #eee;'>";
                echo "<td nowrap valign='top'><div class='{$tc}'>&nbsp;&nbsp;{$user["nev"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$user["taj"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$user["email"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}'>{$user["telefon"]}</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}' style='text-align: center;'>" . ($user["nocovid1"] ? "IGEN" : "-") . (empty($user["covidegsid"])?"":": <a target='_blank' href='index.php?showfoto={$user["covidegsid"]}&c={$user["covidegskod"]}'>Fotó</a>"). "</div></td>";
                echo "<td nowrap valign='top'><div class='{$tc}' style='text-align: center;'>" . ($user["nocovid2"] ? "IGEN" : "-") . "</div></td>";
                echo "</tr>";
                echo "<tr><td colspan='18' style='height:6px;'></td></tr>";
            }


            echo "<tr style=''><td colspan='18' style='padding-bottom:10px;'>";
            echo "{$user["sorszam"]}. {$user["oltas_datum"]} {$user["oltas_tipus"]} {$user["statusz"]} ";
            if (!empty($user["covidigazolasid"])) {
                echo "<a target='_blank' href='index.php?showfoto={$user["covidigazolasid"]}&c={$user["covidigazolaskod"]}'>Fotó</a>";
            }
            echo "</td></tr>";

        }
        echo "</table>";


    }


}