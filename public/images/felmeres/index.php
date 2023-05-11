<?php

die("page closed");

session_start();
require("../../../autoload.php");


if (isset($_POST["datum"])) {
    $id=0;
    $error = "";
    if (isset($_GET["szerk"])) {
        $id=$_GET["szerk"];
        $rn=$_GET["rn"];
    }


    if (trim($_POST["munkaltato"]) == "") {
        $error.= "A munkáltató megadása kötelező<br/>";
    }
    if (trim($_POST["letszam"]) == 0) {
        $error.= "A létszám megadása kötelező<br/>";
    }

    if ($error == "") {
        if ($id==0) {
            $rn=rand(1000000,9999999);
            sql_query("insert into munkahigienes_felmeres set munkaltato='',rn=?",array($rn));
            $id = sql_insert_id();
        }

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
            eszrevetelek=?
            where id=? and rn=?
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
            $id,$rn
        ));

        header("location:index.php?szerk={$id}&rn={$rn}");
        die();
    }
}


?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Munkahelyen végzett munkahigiénés felmérés</title>

    <!-- CSS -->
    <style>
        body {
            font-family: Arial;
        }
        a {
            color:#f00;
        }
        .myForm {
            font-family: "Lucida Sans Unicode", "Lucida Grande", sans-serif;
            font-size: 0.8em;
            padding: 1em;
            border: 1px solid #ccc;
        }

        .myForm * {
            box-sizing: border-box;
        }

        .myForm fieldset {
            border: none;
            padding: 0;
        }

        .myForm legend,
        .myForm label {
            padding: 0;
            font-weight: bold;
        }

        .myForm label.choice {
            font-size: 0.9em;
            font-weight: normal;
        }

        .myForm input[type="text"],
        .myForm input[type="tel"],
        .myForm input[type="email"],
        .myForm input[type="datetime-local"],
        .myForm select,
        .myForm textarea {
            border: 1px solid #ccc;
            width: 300px;
            font-family: "Lucida Sans Unicode", "Lucida Grande", sans-serif;
            font-size: 0.9em;
            padding: 0.3em;
        }

        .myForm textarea {
            height: 100px;
        }

        .myForm button {
            padding: 1em;
            border-radius: 0.5em;
            background: #eee;
            border: none;
            font-weight: bold;
            margin-top: 1em;
        }

        .myForm button:hover {
            background: #ccc;
            cursor: pointer;
        }

        h2 {
            border-bottom: 1px dashed #888;
        }

        .tcella {
            padding:8px 8px 8px 0px;
        }
    </style>

</head>
<body>


<?php

if (!isset($_POST["datum"])) {
    $_POST["datum"]=date("Y-m-d");
}

if (isset($_GET["szerk"])) {
    $_POST = sql_fetch_array(sql_query("select * from munkahigienes_felmeres where id=? and rn=?",array($_GET["szerk"],$_GET["rn"])));
}


?>
<h1 style="border-bottom:1px solid #ccc;color:#444;">Munkahelyen végzett munkahigiénés felmérés</h1>
<div style="font-size:12px;border-bottom:1px solid #ccc;margin-bottom:20px;padding-bottom:20px;">
    &nbsp;&nbsp;<a href="index.php">Új felmérés</a>
    |&nbsp;&nbsp;<a href="index.php?list">Kitöltött felmérések</a>
</div>


<?php

if (isset($_GET["list"])) {


    $res=sql_query("SELECT * from munkahigienes_felmeres ORDER BY datum desc");

    echo "<table cellpadding=0 cellspacing=0 border=0>";
    while ($row=sql_fetch_array($res)) {
        $tc="tcella";
        if (trim($row["munkaltato"])=="") $row["megnev"]="nincs neve";
        echo "<tr>";
        echo "<td nowrap valign='top'><div class='{$tc}'>{$row["datum"]}</div></td>";
        echo "<td nowrap valign='top'><div class='{$tc}'><a style='color:#00f;' href='{$_SERVER["PHP_SELF"]}?szerk={$row["id"]}&rn={$row["rn"]}'>{$row["munkaltato"]}</a></div></td>";
        echo "<td nowrap valign='top'><div class='{$tc}'>[<a onclick='if (!confirm(\"Biztos törlöd a felmérést?\")) return false;' href='{$_SERVER["PHP_SELF"]}?list&delete={$row["id"]}&rn={$row["rn"]}'>törlés</a>]</div></td>";
        echo "</tr>";
        echo "<tr><td colspan=7 style='border-top:1px solid #ccc;height:1px;'></td></tr>";
    }
    echo "</table>";


} else {

?>
    <div style="display:table-cell;vertical-align: top;">
        <form class="myForm" method="post" enctype="application/x-www-form-urlencoded">
            <?php

            if (isset($error) && $error != "") {
                echo "<div style='background:#f00;color:#fff;padding:10px;'>{$error}</div>";
            }

            ?>
            <p>
                <label>Látogatás ideje<br/>
                    <input type="text" name="datum" value="<?= $_POST["datum"]?>" style="width:100px;">
                </label>
            </p>

            <p>
                <label>Munkáltató neve<br/>
                    <input type="text" name="munkaltato" value="<?= $_POST["munkaltato"]?>">
                </label>
            </p>

            <p>
                <label>Munkáltató címe<br/>
                    <input type="text" name="munkaltatocim" value="<?= $_POST["munkaltatocim"]?>">
                </label>
            </p>

            <p>
                <label>Mukáltató telefonszáma<br/>
                    <input type="text" name="munkaltatotel" value="<?= $_POST["munkaltatotel"]?>">
                </label>
            </p>

            <p>
                <label>Tevékenységi köre<br/>
                    <input type="text" name="tevekenysegikor" value="<?= $_POST["tevekenysegikor"]?>">
                </label>
            </p>

            <p>
                <label>Össz dolgozói létszám<br/>
                    <input style='width:50px;' type="text" name="letszam" value="<?= $_POST["letszam"]?>">
                </label>
            </p>

            <p>
                <label>Foglalkoztatás-egészségi osztályba sorolt munkakörben dolgozik:<br/>
                    <input style='width:50px;' type="text" name="aletszam" value="<?= $_POST["aletszam"]?>">A
                    <input style='width:50px;' type="text" name="bletszam" value="<?= $_POST["bletszam"]?>">B
                    <input style='width:50px;' type="text" name="cletszam" value="<?= $_POST["cletszam"]?>">C
                    <input style='width:50px;' type="text" name="dletszam" value="<?= $_POST["dletszam"]?>">D
                </label>
            </p>

            <h2>Látogatáson jelen lévők</h2>
            <p>
                <label>Munkáltató részéről<br/>
                    <input style='width:200px;' type="text" name="jelen1" value="<?= $_POST["jelen1"]?>">
                    munkakör: <input style='width:200px;' type="text" name="munkakor1" value="<?= $_POST["munkakor1"]?>">
                </label>
            </p>
            <p>
                <label>Foglalkozás-Egészségügy szolgálat részéről<br/>
                    <input style='width:200px;' type="text" name="jelen2" value="<?= $_POST["jelen2"]?>">
                    munkakör: <input style='width:200px;' type="text" name="munkakor2" value="<?= $_POST["munkakor2"]?>">
                </label>
            </p>
            <p>
                <label>Foglalkozás-Egészségügy szolgálat részéről<br/>
                    <input style='width:200px;' type="text" name="jelen3" value="<?= $_POST["jelen3"]?>">
                    munkakör: <input style='width:200px;' type="text" name="munkakor3" value="<?= $_POST["munkakor3"]?>">
                </label>
            </p>

            <h2>Munkavállalók által végzett fő tevékenységek (munkakörök)</h2>
            <p>
                <label>
                    <textarea style='width:100%;' name="tevekenysegek"><?= $_POST["tevekenysegek"]?></textarea>
                </label>
            </p>

            <p>
                <label>Munkahelyiseg méretei
                    <input type="text" name="munka_meretei" value="<?= $_POST["munka_meretei"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Padozat, falak (burkolóanyag, tisztaság)
                    <input type="text" name="munka_padozat" value="<?= $_POST["munka_padozat"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Ajtók, ablakok (mérete, elhelyezkedés)
                    <input type="text" name="munka_ajtok" value="<?= $_POST["munka_ajtok"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Munkahelyi kóroki tényezők
                    <input type="text" name="munka_korok" value="<?= $_POST["munka_korok"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Képernyő előtti munkavégzés
                    <input type="text" name="munka_kepernyo" value="<?= $_POST["munka_kepernyo"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Fizikai terhelés
                    <input type="text" name="munka_terheles" value="<?= $_POST["munka_terheles"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Fizikai kóroki tényezők (zaj, vibráció, sugárzás)
                    <input type="text" name="munka_fizikai" value="<?= $_POST["munka_fizikai"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Kémiai kóroki tényezők (kémiai anyagok, növényvédő szer, por)
                    <input type="text" name="munka_kemiai" value="<?= $_POST["munka_kemiai"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Biológiai kóroki tényezők (fertőzés veszély)
                    <input type="text" name="munka_biologiai" value="<?= $_POST["munka_biologiai"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Balesetveszély
                    <input type="text" name="munka_balesetveszely" value="<?= $_POST["munka_balesetveszely"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Tűzvédelem
                    <input type="text" name="munka_tuzvedelem" value="<?= $_POST["munka_tuzvedelem"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Védőeszköz használat
                    <input type="text" name="munka_vedoeszkoz" value="<?= $_POST["munka_vedoeszkoz"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Higiénés körülmények
                    <input type="text" name="munka_higienes" value="<?= $_POST["munka_higienes"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Dohányzásra kijelölt hely
                    <input type="text" name="munka_dohanyzas" value="<?= $_POST["munka_dohanyzas"]?>" style="width:100%;">
                </label>
            </p>

            <h2>Munkavégzés körülményei</h2>

            <p>
                <label>Világítás
                    <input type="text" name="vilagitas" value="<?= $_POST["vilagitas"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Szellőzés
                    <input type="text" name="szellozes" value="<?= $_POST["szellozes"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Fűtés
                    <input type="text" name="futes" value="<?= $_POST["futes"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Takarítás, szemét tárolás
                    <input type="text" name="takaritas" value="<?= $_POST["takaritas"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Ivóvíz, kézmosási lehetőség
                    <input type="text" name="ivoviz" value="<?= $_POST["ivoviz"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>WC, öltöző, fürdő
                    <input type="text" name="wc" value="<?= $_POST["wc"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Étkezési lehetőség
                    <input type="text" name="etkezes" value="<?= $_POST["etkezes"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Egyéni védőfelszerelések (szükségesség, megfelelőség, viselik-e?)
                    <input type="text" name="vedofelszereles" value="<?= $_POST["vedofelszereles"]?>" style="width:100%;">
                </label>
            </p>
            <p>
                <label>Szűrővizsgálatok rendje
                    <input type="text" name="szurovizsgalat" value="<?= $_POST["szurovizsgalat"]?>" style="width:100%;">
                </label>
            </p>


            <h2>Elsősegélynyújtás személyi és tárgyi feltételei/elérhetősége</h2>
            <p>
                <label>
                    <textarea style='width:100%;' name="elsosegely"><?= $_POST["elsosegely"]?></textarea>
                </label>
            </p>

            <h2>Kockázatértékelés</h2>
            <p>
                <label>
                    <textarea style='width:100%;' name="kockazat"><?= $_POST["kockazat"]?></textarea>
                </label>
            </p>

            <h2>Munkaegészségügyi hiányosságok, észrevételek, javaslatok</h2>
            <p>
                <label>
                    <textarea style='width:100%;' name="eszrevetelek"><?= $_POST["eszrevetelek"]?></textarea>
                </label>
            </p>

            <p><button onclick="document.myForm.submit();">Adatok mentése</button></p>
        </form>
    </div>

<?php } ?>
</body>
</html>

