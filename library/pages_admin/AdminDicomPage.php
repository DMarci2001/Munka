<?php

class AdminDicomPage extends AdminCorePage
{

    private $dicomService;
    public function __construct()
    {
        parent::__construct();


        $this->dicomService = new DicomService();

    }

    public function showPage()
    {
        //if (!$this->adminUtils->szurestipusModJog()) {
        //    return;
        //}



        $docAgent = new DocAgent();


        $services = sql_query("SELECT t.*,m.tipusid FROM szurestipusok t
        LEFT JOIN szurestipusok_megj m ON m.`tipusid`=t.`id` and csomag=0
        GROUP BY t.id
        ORDER BY !instr(megnev,'Új tétel'),megnev")->fetchAll(PDO::FETCH_ASSOC);


        $images = $this->dicomService->getImages();


        echo "<table cellpadding='0' cellspacing='0' border='0'>";

        echo "<tr style='background:#eee;'>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 5px;'>Időpont</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Paciens neve</div></td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Szül. dátum</td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>TAJ szám</td>";
        echo "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Műveletek</td>";
        echo "</tr>";


        foreach ($images as $row) {
            $tc = "tcella";
            if (!isset($first)) {
                echo "<tr><td colspan='7' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (empty(trim($row["patientName"]))) {
                $row["patientName"] = "nincs neve";
            }
            echo "<tr>";

            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["contentDate"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["patientName"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["patientBirthDate"]}</div></td>";
            echo "<td nowrap valign='top'><div class='{$tc}'>{$row["patientOtherIDs"]}</div></td>";

            echo "<td nowrap valign='top'><div class='{$tc}'>";
            echo "[<a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&getimage={$row["id"]}'>kép megtekintése</a>] ";
            echo "[<a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&downloaddicomfile={$row["id"]}'>DICOM file letöltése</a>]";
            echo "</td>";

            echo "</tr>";
            echo "<tr><td colspan='8' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        echo "</table>";

    }


}