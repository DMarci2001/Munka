<style>
    body {
        font-family: Arial;
    }
    a {
        color:#f00;
    }
    .myForm {
        font-family: "Lucida Sans Unicode", "Lucida Grande", sans-serif;
        font-size: 14px;
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

    .myForm p {
        padding: 0px;
        margin: 6px 0px 6px 0px;
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


<div style="display:table-cell;vertical-align: top;">
    <form class="myForm" name="myForm" id="myForm" method="post" enctype="application/x-www-form-urlencoded">
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
                <textarea style='width:100%;' name="tevekenysegek"><?= $_POST["tevekenysegek"]?></textarea>
        </p>

        <p>
            <label>Munkahelyiség méretei
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
                <textarea style='width:100%;' name="elsosegely"><?= $_POST["elsosegely"]?></textarea>
        </p>

        <h2>Kockázatértékelés</h2>
        <p>
                <textarea style='width:100%;' name="kockazat"><?= $_POST["kockazat"]?></textarea>
        </p>

        <h2>Munkaegészségügyi hiányosságok, észrevételek, javaslatok</h2>
        <p>
                <textarea style='width:100%;' name="eszrevetelek"><?= $_POST["eszrevetelek"]?></textarea>
        </p>
        <?php
        if (!empty($_GET["szerk"])) {
            ?>

            <p><input type="button" onclick="document.myForm.submit();" value="Adatok mentése" /> <input type="button" onclick="window.location.href='index.php?page=munkanaplo'" value="Vissza" /></p>
            <?php
        }
        ?>

    </form>
    <!-- sign -->
</div>
