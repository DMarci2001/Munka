<?php

class AdminLangSettingsPage extends AdminCorePage {

    public function __construct()
    {
        parent::__construct();

        if (isset($_POST["savelangvalue"])) {
            sql_query("update langtext set szoveg=? where id=?",array($_POST["savelangvalue"],$_POST["id"]));
            echo htmlentities($_POST["savelangvalue"]);
            die();
        }

    }

    public function showPage() {

        if (!$this->adminUser->beallitasTevekenysegnaploAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        echo "<div style='margin-bottom:20px;'>";
        echo "<a href='index.php?page=settings'>Vissza</a>";
        echo "</div>";

        echo "<div style='display:table-row;font-weight: bold'>";
        echo "<div class='langtd'>Nyelv</div>";
        echo "<div class='langtd'>Kulcs</div>";
        echo "<div class='langtd'>Szöveg</div>";
        echo "</div>";

        $last='';
        $resLang = sql_query("select * from langtext order by kulcs,langid<>'hu',langid<>'en',langid<>'de'");

        while ($rowLang = sql_fetch_array($resLang)) {
            echo "<div style='display:table-row;'>";
            $sepa = "";
            if ($last != $rowLang["kulcs"]) {
                $sepa = "border-top:1px solid #e0e0e0;";
            }
            $last = $rowLang["kulcs"];

            echo "<div class='langtd' style='{$sepa}'>{$rowLang["langid"]}</div>";
            echo "<div class='langtd' style='{$sepa}'>{$rowLang["kulcs"]}</div>";

            echo "<div class='langtd' style='{$sepa}'>";
            echo "<div id='lszoveg{$rowLang["id"]}'><a href='#' onclick='lEditorOpen({$rowLang["id"]});return false;' id='llink{$rowLang["id"]}'>".htmlentities($rowLang["szoveg"])."</a></div>";
            echo "<div id='leditor{$rowLang["id"]}' style='display:none;'><input type='text' id='langtext{$rowLang["id"]}' value=\"".htmlentities($rowLang["szoveg"])."\" style='width:500px;'/> <input type='button' onclick='lEditorSave({$rowLang["id"]});' value='Mentés' /> <input onclick='lEditorClose({$rowLang["id"]});' type='button' value='Mégse' /></div>";
            echo "</div>";

            echo "</div>";
        }
    }

}

