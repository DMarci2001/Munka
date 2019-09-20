<?php

class LeletekPage extends CorePage {

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;

    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->displayFejlec($webText["leletek"]);
        echo $this->showFormErrors();
        echo $this->showPageDescription($this->lang->getText("page.lelet.description","Itt találja a leleteit.<br/>Kattintson a dokumentumra a nyomtatáshoz, vagy megtekintéshez."));

        $request_leletek = sql_query( "SELECT * FROM paciens_leletek pl
                           LEFT JOIN lelet_mintak lm ON lm.lm_id = pl.lelet_type
                           WHERE paciens_id = ?", array( $_SESSION['user']['id'] ));

        $request_zaro = sql_query("SELECT * FROM zaro_leletek zl
                           LEFT JOIN paciens_leletek pl ON pl.zaro_id = zl.zaro_id
                           LEFT JOIN felhasznalok felh ON pl.paciens_id
                           WHERE pl.paciens_id = ? AND pl.zaro_id IS NOT NULL AND felh.cegid != 104
                           GROUP BY zl.zaro_id
                          ", array( $_SESSION['user']['id'] ));

        if (sql_num_rows($request_leletek) > 0 || sql_num_rows($request_zaro) > 0) {
            while ($lelet = sql_fetch_array($request_leletek)) {
                if ($lelet['lelet_type'] == 9) continue;
                echo "<div><a href='#' onClick='open_lelet({$lelet["lelet_id"]});return false;'>{$lelet["lelet_nev"]} - ".date("Y-m-d",strtotime($lelet["kelte"]))."</a></div>";
            }
            while ($zaro = sql_fetch_array($request_zaro)) {
                echo "<div><a onClick='open_zaro({$zaro["zaro_id"]});return false;' href='#'>Záró lelet - ".date("Y-m-d",strtotime($zaro['kelte']))."</a></div>";
            }
        } else {
            echo "<div>".$this->lang->getText("emptyleletlist","Nincs még lelet kiállítva.")."</div>";
        }

        echo "<div class ='target-lelet' style='color:#000'></div>";

    }

}
