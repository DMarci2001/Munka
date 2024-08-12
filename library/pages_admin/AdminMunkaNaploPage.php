<?php


class AdminMunkaNaploPage extends AdminCorePage
{
    public function __construct()
    {
        parent::__construct();

        //error_reporting(E_ALL);
        //ini_set('display_errors', 1);

        if (isset($_POST["munkanaploautofill"])) {
            $munkaltato = $_POST["munkanaploautofill"];

            if ($result = sql_query("select * from munkahigienes_felmeres where munkaltato=? and munkaltato<>'' order by datum desc limit 1", [$munkaltato])->fetch(PDO::FETCH_ASSOC)) {
                $result["error"] = "";
            } else {
                $result["error"] = "Munkáltató nem található";
            }

            echo Utils::jsonOut($result);
            die;
        }

        if (isset($_POST["datum"])) {
            $id=0;
            $error = "";
            if (isset($_GET["szerk"])) {
                $id=$_GET["szerk"];
            }


            if (trim($_POST["munkaltato"]) == "") {
                $error.= "A munkáltató megadása kötelező<br/>";
            }
            if (trim($_POST["letszam"]) == 0) {
                $error.= "A létszám megadása kötelező<br/>";
            }

            if ($error == "") {
                sql_query("update munkahigienes_felmeres set
                    datum=?,
                    munkaltato=?,
                    munkaltatocim=?,
                    munkaltatotel=?,
                    tevekenysegikor=?,
                    letszam=?,aletszam=?,bletszam=?,cletszam=?,dletszam=?,
                    jelen1=?,jelen2=?,jelen3=?,munkakor1=?,munkakor2=?,munkakor3=?,
                    tevekenysegek=?,
                    munka_meretei=?,
                    munka_padozat=?,
                    munka_ajtok=?,
                    munka_korok=?,
                    munka_kepernyo=?,
                    munka_terheles=?,
                    munka_fizikai=?,
                    munka_kemiai=?,
                    munka_biologiai=?,
                    munka_balesetveszely=?,
                    munka_tuzvedelem=?,
                    munka_vedoeszkoz=?,
                    munka_higienes=?,
                    munka_dohanyzas=?,
                    vilagitas=?,
                    szellozes=?,
                    futes=?,
                    takaritas=?,
                    ivoviz=?,
                    wc=?,
                    etkezes=?,
                    vedofelszereles=?,
                    szurovizsgalat=?,
                    elsosegely=?,
                    kockazat=?,
                    eszrevetelek=?,
                    orvos=?,
                    pecsetszam=?
                    where id=?
                ",array(
                    $_POST["datum"],
                    $_POST["munkaltato"],
                    $_POST["munkaltatocim"],
                    $_POST["munkaltatotel"],
                    $_POST["tevekenysegikor"],
                    $_POST["letszam"],$_POST["aletszam"],$_POST["bletszam"],$_POST["cletszam"],$_POST["dletszam"],
                    $_POST["jelen1"],$_POST["jelen2"],$_POST["jelen3"],$_POST["munkakor1"],$_POST["munkakor2"],$_POST["munkakor3"],
                    $_POST["tevekenysegek"],
                    $_POST["munka_meretei"],
                    $_POST["munka_padozat"],
                    $_POST["munka_ajtok"],
                    $_POST["munka_korok"],
                    $_POST["munka_kepernyo"],
                    $_POST["munka_terheles"],
                    $_POST["munka_fizikai"],
                    $_POST["munka_kemiai"],
                    $_POST["munka_biologiai"],
                    $_POST["munka_balesetveszely"],
                    $_POST["munka_tuzvedelem"],
                    $_POST["munka_vedoeszkoz"],
                    $_POST["munka_higienes"],
                    $_POST["munka_dohanyzas"],
                    $_POST["vilagitas"],
                    $_POST["szellozes"],
                    $_POST["futes"],
                    $_POST["takaritas"],
                    $_POST["ivoviz"],
                    $_POST["wc"],
                    $_POST["etkezes"],
                    $_POST["vedofelszereles"],
                    $_POST["szurovizsgalat"],
                    $_POST["elsosegely"],
                    $_POST["kockazat"],
                    $_POST["eszrevetelek"],
                    $_SESSION["adminuser"]["nev"],
                    $_SESSION["adminuser"]["pecsetszam"],
                    $id
                ));

                header("location:index.php?page=munkanaplo");
                die();
            }
        }

        $GLOBALS["javascript"][] = "munkanaplo.js?v=".date("YmdHi");
    }

    public function showPage()
    {
        if (!$this->adminUser->beallitasAccess()) {
            //echo "nincs jogosultságod!";
            //return;
        }

        if (isset($_GET["szerk"])) {
            echo $this->munkaNaploEditor($_GET["szerk"]);
        } else {
            echo "<div id='munkanaplolista'>";
            echo $this->munkaNaploLista();
            echo "</div>";
        }

        echo "<div id='debugcontainer'></div>";
    }


    private function munkaNaploLista():string {
        $html = "";

        $munkanaplok = sql_query("SELECT * from munkahigienes_felmeres ORDER BY datum desc");

        $html.= "<table cellpadding='0' cellspacing='0' border='0' width='100%;'>";
        $html.= "<tr style='background:#eee;'>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'></td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Munkáltató</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Orvos</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:120px;'>Pecsétszám</td>";
        $html.= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;'>&nbsp;</td>";
        $html.= "</tr>";

        foreach ($munkanaplok as $row) {
            $tc = "tcella";
            if (!isset($first)) {
                $html.= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }
            if (empty(trim($row["munkaltato"])) || $row["munkaltato"] == "1") {
                $row["munkaltato"] = "nincs kitöltve";
            }
            $html.= "<tr>";

            $html.= "<td nowrap valign='top'><div class='{$tc}'>";
            $html.= "<a style='' href='index.php?page={$_GET["page"]}&szerk={$row["id"]}'>szerk</a> ";
            $html.= "</td>";

            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["munkaltato"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["orvos"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>{$row["pecsetszam"]}</div></td>";
            $html.= "<td nowrap valign='top'><div class='{$tc}'>";
            if ($row["munkaltato"] != "nincs kitöltve") {
                $html .= "<a class='printbutton' target='_blank' href='index.php?print&template=munkanaplopdf&mid={$row["id"]}&p={$row["rn"]}' style='background: #00aa00;padding:1px 5px;'>PDF letöltése</a>";
            }
            $html.= "</div></td>";

            $html.= "</tr>";
            $html.= "<tr><td colspan='10' ><div id='datarow{$row["id"]}' style='padding:10px 0px 10px 0px;display:none;'></div></td></tr>";
            $html.= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        $html.= "</table>";

        return $html;
    }

    private function munkaNaploEditor():string {
        $html = "";

        $_POST = sql_fetch_array(sql_query("select * from munkahigienes_felmeres where id=?", [$_GET["szerk"]]));

        $html.= "<div>";

        ob_start();
        include (Booking_Constants::APP_PATH."public/images/felmeres/munkaNaploTemplate.php");
        $html.= ob_get_contents();
        ob_end_clean();
        //$html.= file_get_contents(Booking_Constants::APP_PATH."public/images/felmeres/munkaNaploTemplate.php");

        $html.= "</div>";

        return $html;
    }




}

