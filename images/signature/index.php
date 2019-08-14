<?php

session_start();
$expireTime = (10 * 365 * 24 * 60 * 60);

if (!isset($_COOKIE["signature_name"])) {
    setcookie( "signature_name", "Teszt Elek", time()+$expireTime);
    setcookie( "signature_status", "php fejlesztő", time()+$expireTime);
    setcookie( "signature_phone", "+36 (20) 1234567", time()+$expireTime);
    setcookie( "signature_email", "mymail@hungariamed.hu", time()+$expireTime);
    setcookie( "signature_web", "https://www.hungariamed.hu", time()+$expireTime);
    setcookie( "signature_address", "1135 Budapest, Jász u. 33-35.", time()+$expireTime);
    setcookie( "signature_facebook", "https://www.facebook.com/hungariamed/", time()+$expireTime);
    setcookie( "signature_instagram", "https://www.instagram.com/explore/locations/107111122708734/hungaria-med-m-kft", time()+$expireTime);
    setcookie( "signature_linkedin", "", time()+$expireTime);
    header("location:index.php?saved");
    die();
}



if (isset($_POST["signature_name"])) {
    setcookie( "signature_name", $_POST["signature_name"], time()+$expireTime);
    setcookie( "signature_status", $_POST["signature_status"], time()+$expireTime);
    setcookie( "signature_phone", $_POST["signature_phone"], time()+$expireTime);
    setcookie( "signature_email", $_POST["signature_email"], time()+$expireTime);
    setcookie( "signature_web", $_POST["signature_web"], time()+$expireTime);
    setcookie( "signature_address", $_POST["signature_address"], time()+$expireTime);
    setcookie( "signature_facebook", $_POST["signature_facebook"], time()+$expireTime);
    setcookie( "signature_instagram", $_POST["signature_instagram"], time()+$expireTime);
    setcookie( "signature_linkedin", $_POST["signature_linkedin"], time()+$expireTime);

    header("location:index.php?saved");
    die();
}


if (isset($_GET["savenevjegy"])) {
    ob_start();
    include("kuzdygabor.phtml");
    $html = ob_get_contents();
    $fileName = "nevjegy_".date("YmdHis")."_".rand(1000,9999).".html";
    file_put_contents($fileName,$html);
    ob_clean();
    header("location:{$fileName}");
    die();
}


?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Hungáriamed - aláírás készítő</title>

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
            width: 20em;
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
            display: block;
            width: 100%;
            border: 1px solid #ccc;
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
    </style>

</head>
<body>

<h1 style="border-bottom:1px solid #ccc;color:#444;">E-mail aláírás szerkesztő</h1>
<div style="font-size:12px;border-bottom:1px solid #ccc;margin-bottom:20px;"><ol><li>Töltsd ki az adatokat, majd kattints az "Aláírás frissítése" gombra.</li><li>Ha minden rendben az aláírással a jobb oldalon, kattints a "Névjegy mentése" linkre, és kapsz egy végleges URL-t, amivel bármikor meg tudod nyitni az elkészült aláírást</li><li>A 2. pontban megnyitott oldalon, jelöld ki az egész aláírást, CTRL+C, majd másold be a levelező aláírás mezőjébe (gmail-en tesztelve)</li></ol></div>
<div style="display:table-cell;vertical-align: top;">
    <form class="myForm" method="post" enctype="application/x-www-form-urlencoded">

        <p>
            <label>Név
                <input type="text" name="signature_name" value="<?= $_COOKIE["signature_name"]?>">
            </label>
        </p>

        <p>
            <label>Beosztás
                <input type="text" name="signature_status" value="<?= $_COOKIE["signature_status"]?>">
            </label>
        </p>

        <p>
            <label>Telefon
                <input type="text" name="signature_phone" value="<?= $_COOKIE["signature_phone"]?>">
            </label>
        </p>

        <p>
            <label>E-mail (nem kötelező)
                <input type="text" name="signature_email" value="<?= $_COOKIE["signature_email"]?>">
            </label>
        </p>

        <p>
            <label>Cím
                <input type="text" name="signature_address" value="<?= $_COOKIE["signature_address"]?>">
            </label>
        </p>

        <p>
            <label>Web
                <input type="text" name="signature_web" value="<?= $_COOKIE["signature_web"]?>">
            </label>
        </p>

        <p>
            <label>Facebook
                <input type="text" name="signature_facebook" value="<?= $_COOKIE["signature_facebook"]?>">
            </label>
        </p>

        <p>
            <label>Instagram
                <input type="text" name="signature_instagram" value="<?= $_COOKIE["signature_instagram"]?>">
            </label>
        </p>

        <p>
            <label>Linkedin (nem kötelező)
                <input type="text" name="signature_linkedin" value="<?= $_COOKIE["signature_linkedin"]?>">
            </label>
        </p>

        <p><button onclick="document.myForm.submit();">Aláírás frissítése</button></p>

    </form>
</div>

<div style="display:table-cell;vertical-align: top;padding-left:20px;">
    <?php
        include("kuzdygabor.phtml");
    ?>
    <div><br/><a target="_blank" href="index.php?savenevjegy">Névjegy mentése</a></div>

</div>

</body>
</html>

