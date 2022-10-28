<?php


class AdminKlinikakPage extends AdminCorePage
{
    public function __construct()
    {
        parent::__construct();

        if (!isset($_SESSION["tipusfilter"])) {
            $_SESSION["tipusfilter"] = [];
        }

        if (isset($_REQUEST["showklinikaeditor"])) {
            echo $this->klinikaEditor($_REQUEST["showklinikaeditor"]);
            die;
        }

        if (isset($_POST["saveklinikadata"])) {
            sql_query("update klinikak.klinikak set megnev=?, url=?, cim=?, regio=?, telefon=?, megj=?, percent=? where id=?",
                [$_POST["megnev"], $_POST["url"], $_POST["cim"], $_POST["regio"], $_POST["telefon"], $_POST["megj"], $_POST["percent"], $_POST["saveklinikadata"]]);

            $tipusok = sql_query("select * from klinikak.tipusok order by megnev")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tipusok as $tipus) {
                if (isset($_POST["tipuskapcs{$tipus["id"]}"])) {
                    if (!sql_query("select * from klinikak.klinikatipusok where klinikaid=? and tipusid=?", [$_POST["saveklinikadata"], $tipus["id"]])->fetch(PDO::FETCH_ASSOC)) {
                        sql_query("insert into klinikak.klinikatipusok set klinikaid=?, tipusid=?", [$_POST["saveklinikadata"], $tipus["id"]]);
                    }
                    sql_query("update klinikak.klinikatipusok set price=? where klinikaid=? and tipusid=?", [$_POST["tipuskapcsprice{$tipus["id"]}"], $_POST["saveklinikadata"], $tipus["id"]]);
                } else {
                    sql_query("delete from klinikak.klinikatipusok where klinikaid=? and tipusid=? limit 1", [$_POST["saveklinikadata"], $tipus["id"]]);
                }
            }

            Utils::jsonOut(["message" => "Sikeres mentés", "html" => $this->klinkaLista()]);
        }

        if (isset($_POST["deleteklinika"])) {
            sql_query("delete from klinikak.klinikak where id=? limit 1", [$_POST["deleteklinika"]]);
            Utils::jsonOut(["message" => "Törlés sikerült", "html" => $this->klinkaLista()]);
        }

        if (isset($_POST["filterklinikak"])) {
            unset($_SESSION["tipusfilter"]);
            $tipusok = sql_query("select * from klinikak.tipusok order by megnev")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tipusok as $tipus) {

                if (isset($_POST["tipusfilter{$tipus["id"]}"])) {
                    $_SESSION["tipusfilter"][] = $tipus["id"];
                }
            }

            Utils::jsonOut(["message" => "Sikeres filter", "html" => $this->klinkaLista()]);
        }


        $GLOBALS["javascript"][] = "klinikak.js?v=".date("YmdHi");
    }

    public function showPage()
    {
        if (!$this->adminUser->beallitasAccess()) {
            echo "nincs jogosultságod!";
            return;
        }

        echo "<div id='debugarea'></div>";

        echo "<div>".$this->filter()."</div>";


        echo "<div id='klinikalista'>";
        echo $this->klinkaLista();
        echo "</div>";

        echo "<div id='debugcontainer'></div>";
    }



    private function filter() {
        $html = "";
        $html.= "<div id='filter' style='margin:10px 0px;'>";

        $html.= "<div style='margin-bottom:10px;font-weight: bold;'>Szűrés</div>";
        $html.= "<form name='tipusfilterform' id='tipusfilterform'>";

        $tipusok = sql_query("select * from klinikak.tipusok order by megnev")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tipusok as $tipus) {
            $checked = in_array($tipus["id"], $_SESSION["tipusfilter"]);
            $html.= "<div style='display:inline-block;'>";
            $html.= "<input type='checkbox' onchange='tipusFilterApply();' name='tipusfilter{$tipus["id"]}' id='tipusfilter{$tipus["id"]}' value='1' ".($checked == 1 ? "checked" : "")." /> {$tipus["megnev"]}&nbsp;&nbsp;";
            $html.= "</div>";
        }

        $html.= "</form>";
        $html.= "</div>";
        return $html;
    }

    private function klinkaLista() {
        $html = "";

        $clinics = sql_query("SELECT k.*, IF (kt.id IS NULL, 0, COUNT(*)) AS db, GROUP_CONCAT(t.megnev SEPARATOR ', ') AS tipusok, GROUP_CONCAT(t.id SEPARATOR '|') AS tipusfilterids FROM klinikak.klinikak k 
            LEFT JOIN klinikak.`klinikatipusok` kt ON kt.`klinikaid`=k.id 
            LEFT JOIN klinikak.tipusok t ON t.id=kt.`tipusid` 
            GROUP BY k.id
            ORDER BY k.megnev");

        $html.= "<table cellpadding='0' cellspacing='0' border='0' width='100%;'>";
        $html.= "<tr style='background:#eee;'>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Megnevezés</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>URL</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;text-align: center;'>Szolgáltatások</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Régió</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Százalék</div></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:100px;'>Telefon</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>Megjegyzés</td>";
        $html.= "</tr>";

        foreach ($clinics as $row) {
            $notFound = false;
            if (!empty($_SESSION["tipusfilter"])) {
                $tipusok = "|{$row["tipusfilterids"]}|";
                //$html.= $tipusok;
                foreach ($_SESSION["tipusfilter"] as $filterTipusId) {
                    if (substr_count($tipusok, "|{$filterTipusId}|") == 0) {
                        $notFound = true;
                        break;
                    }
                }
            }

            if ($notFound) {
                continue;
            }

            $tc = "tcella";
            if (!isset($first)) {
                $html.= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (empty(trim($row["megnev"]))) {
                $row["megnev"] = "nincs neve";
            }
            $html.= "<tr>";

            $html.= "<td nowrap valign='top'><div class='{$tc}'>";
            $html.= "<a style='' onclick='toggleKlinikaEditor(\"{$row["id"]}\");return false;' href='#'>szerk</a> ";
            $html.= "</td>";

            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["megnev"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'><a target='_blank' href='{$row["url"]}'>{$row["url"]}</a></div></td>";
            $html.= "<td nowrap valign='top' style='text-align: center;'><div title='{$row["tipusok"]}' class='{$tc}' style='cursor: pointer;'>&nbsp;{$row["db"]}&nbsp;</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["regio"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["percent"]}%</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["telefon"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["megj"]}</div></td>";

            $html.= "</tr>";
            $html.= "<tr><td colspan='10' ><div id='datarow{$row["id"]}' style='padding:10px 0px 10px 0px;display:none;'></div></td></tr>";
            $html.= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        $html.= "</table>";

        return $html;
    }


    private function klinikaEditor($id):string {
        $klinikaData = sql_query("select * from klinikak.klinikak k where k.id=?", [$id])->fetch(PDO::FETCH_ASSOC);

        $html = "";
        $html.= "<form id='klinikaform{$id}'>";

        $html.= "<div style='display:table-cell;vertical-align: top;padding-right: 10px;'>";
        $html.= "<div style='margin-top:5px;'>Klinka megnevezése<br/><input type='text' name='megnev' value='{$klinikaData["megnev"]}' style='width: 300px;'/></div>";
        $html.= "<div style='margin-top:5px;'>URL<br/><input type='text' name='url' value='{$klinikaData["url"]}' style='width: 300px;'/></div>";
        $html.= "<div style='margin-top:5px;'>Cím<br/><input type='text' name='cim' value='{$klinikaData["cim"]}' style='width: 300px;'/></div>";
        $html.= "<div style='display:table-cell;'><div style='margin-top:5px;'>Régió<br/><input type='text' name='regio' value='{$klinikaData["regio"]}' style='width: 140px;'/></div></div>";
        $html.= "<div style='display:table-cell;padding-left:10px;'><div style='margin-top:5px;'>Százalék<br/><input type='text' name='percent' value='{$klinikaData["percent"]}' style='width: 140px;'/></div></div>";
        $html.= "<div style='margin-top:5px;'>Telefon<br/><input type='text' name='telefon' value='{$klinikaData["telefon"]}' style='width: 300px;'/></div>";
        $html.= "<div style='margin-top:5px;'>Megjegyzés<br/><textarea name='megj' style='width: 300px;'>{$klinikaData["megj"]}</textarea></div>";
        $html.= "<div style='margin-top:5px;'><input type='button' value='Mentés' onclick='saveKlinikaData({$klinikaData["id"]});' style='width: 100px;'/> <input type='button' value='Törlés' onclick='deleteKlinika({$klinikaData["id"]});' style='width: 100px;background:#f00;color:#fff;'/></div>";
        $html.= "</div>";

        $html.= "<div style='display:table-cell;vertical-align: top;padding-left: 10px;border-left:1px solid #ccc;'>";


        $kapcsok = [];
        $tipusKapcs = sql_query("select * from klinikak.klinikatipusok where klinikaid=?", [$id])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tipusKapcs as $kapcs) {
            $kapcsok[$kapcs["tipusid"]] = $kapcs;
        }


        $tipusok = sql_query("select * from klinikak.tipusok order by megnev")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tipusok as $tipus) {
            $price = $checked = 0;
            if (isset($kapcsok[$tipus["id"]])) {
                $price = $kapcsok[$tipus["id"]]["price"];
                $checked = 1;
            }
            $html.= "<div style='display:table-row;'>";
            $html.= "<div class='tdm'><input type='checkbox' name='tipuskapcs{$tipus["id"]}' value='1' ".($checked == 1 ? "checked" : "")." /></div>";
            $html.= "<div class='tdm'>{$tipus["megnev"]}&nbsp;&nbsp;</div>";
            $html.= "<div class='tdm' style='padding:1px 0px;'><input type='text' style='width: 60px;text-align: right;' name='tipuskapcsprice{$tipus["id"]}' value='{$price}' /> HUF</div>";
            $html.= "</div>";
        }

        $html.= "</div>";


        $html.= "</form>";

        return $html;
    }

}

