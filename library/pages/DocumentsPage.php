<?php

class DocumentsPage extends CorePage {

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;

        unset($_SESSION["beutaloid"]);
    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->displayFejlec($webText["dokumentumok"]);
        echo $this->showFormErrors();
        echo $this->showPageDescription($this->lang->getText("page.documents.description","Itt találja a rendszerbe feltöltött dokumentumait.<br/>Kattintson a dokumentumra a letöltéshez, vagy megtekintéshez."));

        $res=sql_query("SELECT d.* FROM dokumentumok d WHERE userid=? and userid<>0 order by datum desc",array($_SESSION["user"]["id"]));

        if (sql_num_rows($res) == 0) {
            echo "<div>".$this->lang->getText("emptydocumentlist","Önnek még nincs dokumentuma")."</div>";
        }

        echo "<div style='display:inline-block'>";
        echo "<table>";

        while ($row=sql_fetch_array($res)) {
            echo "<div class='beutalobox' style='cursor:pointer;' onclick='window.location.href=\"index.php?downloaddoc&f={$row["id"]}&k={$row["kod"]}&v=1\";'>";
            echo "<div style='font-size:14px;font-weight:bold;'>{$row["megnev"]}</div>";
            echo "<div style='margin-top:0px;'>Feltöltve: ".substr($row["datum"],0,16)."</div>";
            echo "<div style='margin-top:5px;'><img height='50' src='images/icon_{$row["tipus"]}.png' alt='' /></div>";
            echo "<div style='margin-top:0px;'>{$row["filename"]}</div>";
            echo "</div>";
        }

        echo "</table>";
        echo "</div>";
    }
}

