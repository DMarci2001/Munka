<?php


echo displayFejlec();

echo $webText["sikeresfoglalaspage"];

echo "<a href='/'>{$webText["visszafooldal"]}</a>";

if (isset($_SESSION["captcha"])) unset($_SESSION["captcha"]);
