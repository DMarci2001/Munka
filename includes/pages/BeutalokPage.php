<?php

class BeutalokPage extends CorePage {

    private $bookingService;

    public function __construct()
    {
        parent::__construct();
        $webText = $this->lang->webText;
        $this->bookingService = new BookingService();

        unset($_SESSION["beutaloid"]);

        if (isset($_GET["deltime"])) {
            $this->bookingService->deleteReservation($_GET["id"], $_GET["rk"]);
            header("location:index.php?page={$_GET["page"]}");
            die();
        }

        //csak audinál kellett ---
        if (isset($_POST["adduserbeutalo"])) {
            if (isset($_SESSION["user"]["id"])) {

                $data = explode("-", $_POST["beutalotarget"]);
                $hid = intval($data[0]);
                $sztid = intval($data[1]);

                sql_query("insert into beutalok set datum=now(),selfcreated=1,userid=?,cegid=?,helyszinid=?,szurestipusid=?,naploszam=?,megj=?", array($_SESSION["user"]["id"], $_SESSION["helyszindata"]["id"], $hid, $sztid, $_POST["naploszam"], $_POST["beutalomegj"]));
            }

            header("location:index.php?page=beutalok");
            die();
        }

        if (isset($_GET["delbeutalo"])) {
            sql_query("delete from beutalok where id=? and userid=?", array($_GET["delbeutalo"], $_SESSION["user"]["id"]));
            header("location:index.php?page=beutalok");
            die();
        }

    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->displayFejlec($webText["beutalok"]);
        echo $this->showFormErrors();
        echo $this->showPageDescription($webText["beuitttalalja"]);

        $res=sql_query("SELECT t.`megnev` AS tipusnev,t.megnev_de as tipusnev_de,t.megnev_en as tipusnev_en,h.cim AS helyszinnev,b.* FROM beutalok b
        LEFT JOIN szurestipusok t ON t.`id`=b.`szurestipusid`
        LEFT JOIN helyszinek h ON h.`id`=b.`helyszinid`
        WHERE userid=? order by b.datum desc", array($_SESSION["user"]["id"]));

        if (sql_num_rows($res) == 0) {
            echo "<div>".$this->lang->getText("emptybeutalolist","Önnek még nincs beutalója")."</div>";
        }

        echo "<div style='display:inline-block'>";

        while ($row=sql_fetch_array($res)) {
            if ($_COOKIE["lang"]!="hu" && trim($row["tipusnev_{$_COOKIE["lang"]}"])!="") {
                $row["tipusnev"] = $row["tipusnev_{$_COOKIE["lang"]}"];
            }

            echo "<div class='beutalobox'>";
            echo "<div style='font-size:24px;'>{$row["tipusnev"]} {$webText["beutalo"]}</div>";
            echo "<div style='font-size:14px;'>{$row["helyszinnev"]}</div>";
            if ($row["naploszam"] != "") {
                echo "<div style='font-size:14px;'>{$webText["naploszam"]}: {$row["naploszam"]}</div>";
            }
            echo "<div style='margin-top:0px;'>{$webText["kiadva"]}: ".substr($row["datum"],0,16)."</div>";
            if ($row["foglalasid"] == 0) {
                echo "<div style='margin-top:10px;margin-bottom:5px;'><a href='index.php?page={$_GET["page"]}&page=booking&setbeutalo={$row["id"]}' class='newbutton'>{$webText["idopontfoglalasa"]}</a>";
                if ($row["selfcreated"] == 1) {
                    echo "&nbsp;&nbsp;<a onclick='return confirm(\"{$webText["biztostorlibeutalo"]}\");' href='index.php?page={$_GET["page"]}&delbeutalo={$row["id"]}' class='newbutton'>{$webText["beutorlese"]}</a>";
                }
                echo "</div>";
            } else {
                if ($rowf = sql_fetch_array(sql_query("select * from foglalasok where id='{$row["foglalasid"]}'"))) {
                    echo "<div style='margin-top:10px;'><b>Időpont foglalva: ".substr($rowf["datum"],0,16)."</b>";
                    if (strtotime("now") < strtotime($rowf["datum"])) {
                        echo " <a onclick='return confirm(\"{$webText["idopontdelconfirm"]}\");' href='index.php?page={$_GET["page"]}&deltime&id={$rowf["id"]}&rk={$rowf["rkod"]}' class='newbutton' style='padding:2px 5px;font-size:12px;'>{$webText["idoponttorlese"]}</a>";
                    }
                    echo "</div>";
                }
            }
            echo "</div>";
        }

        echo "</div>";
    }
}

