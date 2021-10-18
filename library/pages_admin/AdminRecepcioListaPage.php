<?php

class AdminRecepcioListaPage extends AdminCorePage {

    private $toggleFields = ["mrtg", "alk", "uh", "covid", "labor"];
    private $weekDays = ["","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap"];

    public function __construct()
    {
        $GLOBALS["javascript"][] = "recepcioLista.js";

        if (!isset($_SESSION["recepciooffset"])) {
            $_SESSION["recepciooffset"] = 0;
        }

        if (isset($_GET["togglerecepciomezo"])) {
            $mezo = $_GET["mezo"];

            if (in_array($mezo, $this->toggleFields)) {
                $id = intval($_GET["id"]);
                sql_query("update recepciolista set {$mezo}=if({$mezo}=0, 1, 0) where id=?", [$id]);
            }

            echo $this->recepcioListaSor(sql_query("select * from recepciolista where id=?", [$id])->fetch(PDO::FETCH_ASSOC));
            die;
        }

        if (isset($_GET["recepcioListaItemDelete"])) {
            $id = intval($_GET["id"]);
            sql_query("delete from recepciolista where id=?", [$id]);

            echo $this->recepcioLista();
            die;
        }

        if (isset($_GET["moveday"])) {
            $_SESSION["recepciooffset"] += intval($_GET["moveday"]);
            echo $this->recepcioLista();
            die;
        }

        if (isset($_GET["addRecepcioListaItem"])) {
            $item = $_GET["addRecepcioListaItem"];

            $nev = $item;
            $taj = "";

            if ($data = sql_fetch_array(sql_query("select * from foglalasok where id=?", [$item]))) {
                $nev = $data["nev"];
                $taj = $data["taj"];
            }

            sql_query("insert into recepciolista set nap=?, created=now(), nev=?, taj=?", [$this->getDay(), $nev, $taj]);
            echo $this->recepcioLista();
            die;
        }


    }

    public function showPage() {
        echo "<div id='recepciolista'>";
        echo $this->recepcioLista();
        echo "</div>";
    }

    private function getDay() {
        if ($_SESSION["recepciooffset"]<0) {
            return  date("Y-m-d", strtotime("now {$_SESSION["recepciooffset"]} day"));
        } else {
            return  date("Y-m-d", strtotime("now +{$_SESSION["recepciooffset"]} day"));
        }
    }

    private function getWeekDay() {
        return $this->weekDays[date("N", strtotime($this->getDay()))];
    }

    private function recepcioLista():string {
        $html = "";

        $html.= "<div style='font-size: 18px;'>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><a title='előző nap' href='#' onclick='recepcioListaMoveDay(-1);return false;'><i class='fas fa-chevron-circle-left'></i></a>&nbsp;</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'><a title='következő nap' href='#' onclick='recepcioListaMoveDay(1);return false;'><i class='fas fa-chevron-circle-right'></i></a>&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'>".$this->getDay()." ".$this->getWeekDay()."</div>";
        $html.= "</div>";

        $html.= "<div style='margin-top: 20px;'>";
        //$html.= "<div style='margin-bottom: 5px;'>Írd be, vagy válaszd ki a pacienst:</div>";
        $html.= "<select id='recepcioadd' style='margin-top:4px;' onchange='addRecepcioListaItem(this);'>";
        $html.= "<option value='0'>Válassz, vagy írd be a paciens nevét</option>";
        $paciensek = sql_query("select id, nev, taj from foglalasok where datum>date_sub(now(), interval 2 day) and trim(nev)<>'nincs név' and !instr(nev, ' fő') group by concat(trim(nev), taj) order by trim(nev)");
        foreach ($paciensek as $paciens) {
            if ($paciens["taj"] == "") {
                $paciens["taj"] = "nincs taj szám";
            }
            $html.= "<option value='{$paciens["id"]}'>{$paciens["nev"]} ({$paciens["taj"]})</option>";
        }
        $html.= "</select>";
        $html.= "</div>";


        $recepcioLista = sql_query("select * from recepciolista where nap=? order by created desc", [$this->getDay()])->fetchAll(PDO::FETCH_ASSOC);

        $html.= "<div style='margin-top:10px;padding-top:10px;border-top:1px solid #ccc;'>";
        if (empty($recepcioLista)) {
            return "{$html}<div>Erre a napra még nincs rögzítés</div>";
        } else {
            $html .= "<div style='display:table;'>";

            $html .= "<div style='display:table-row;'>";
            $html .= "<div style='display:table-cell;font-weight: bold;padding:4px 0px;'>Paciens neve</div>";
            $html .= "<div style='display:table-cell;font-weight: bold;padding:4px 0px;'>Taj száma</div>";
            $html .= "<div style='display:table-cell;font-weight: bold;padding:4px 0px;'>Viszgálat</div>";
            $html .= "<div style='display:table-cell;font-weight: bold;padding:4px 0px;'>Vizsgálta</div>";
            $html .= "<div style='display:table-cell;font-weight: bold;padding:4px 0px;'>MRTG/labor</div>";
            foreach ($this->toggleFields as $toggleField) {
                $html .= "<div style='display:table-cell;font-weight: bold;text-align:center;padding:4px 0px;width:45px;'>{$toggleField}</div>";
            }
            $html .= "</div>";

            foreach ($recepcioLista as $recepcioData) {
                $html .= "<div id='recepciosor{$recepcioData["id"]}' style='display:table-row;'>";
                $html .= $this->recepcioListaSor($recepcioData);
                $html .= "</div>";
            }
            $html .= "</div>";
        }
        $html.="</div>";

        return $html;
    }

    private function recepcioListaSor($recepcioData):string {
        $html = "";

        $html.= "<div class='recilistacell'>";
        $html.= "<input type='text' id='rnev{$recepcioData["id"]}' value='{$recepcioData["nev"]}' />";
        $html.= "</div>";

        $html.= "<div class='recilistacell'>";
        $html.= "<input type='text' id='rtaj{$recepcioData["id"]}' value='{$recepcioData["taj"]}' />";
        $html.= "</div>";

        $html.= "<div class='recilistacell'>";
        $html.= "<input type='text' id='rtaj{$recepcioData["id"]}' value='{$recepcioData["vizsgalat"]}' />";
        $html.= "</div>";

        $html.= "<div class='recilistacell'>";
        $html.= "<input type='text' id='rtaj{$recepcioData["id"]}' value='{$recepcioData["vizsgalta"]}' />";
        $html.= "</div>";

        $html.= "<div class='recilistacell'>";
        $html.= "<input type='text' id='asszisztens{$recepcioData["id"]}' value='{$recepcioData["asszisztens"]}' />";
        $html.= "</div>";

        foreach ($this->toggleFields as $toggleField) {
            $html .= "<div class='recilistacell'>" . $this->recepcioListaCheckBox($recepcioData, $toggleField) . "</div>";
        }

        $html.= "<div class='recilistacell'>";
        $html.= "<div style='text-align: center;'><a href='#' onclick='recepcioListaItemDelete(\"{$recepcioData["id"]}\")' style='font-size: 14px;'><i class='fas fa-trash'></i></a></div>";
        $html.= "</div>";

        return $html;
    }

    private function recepcioListaCheckBox($recepcioData, $id) {
        $html = "";

        $icon = "far fa-square";
        $style= "opacity:.5;";
        if ($recepcioData[$id] == 1) {
            $icon = "fas fa-check-square";
            $style= "color:green;";
        }
        $html .= "<div style='text-align: center;'><a title='sor törlése' href='#' onclick='recepcioMezoToggle(\"{$id}\", {$recepcioData["id"]})' style='font-size: 20px;{$style}'><i class='{$icon}'></i></a></div>";

        return $html;
    }

}