<?php

$webText = getWebTexts($_COOKIE["lang"]);

function getWebTexts($lang) {
    $webText = [];
    $resL=sql_query("select * from langtext where langid=?",array($lang));
    while ($rowL=sql_fetch_array($resL)) {
        if ($rowL["tipus"]==0) {
            $webText[$rowL["kulcs"]]=$rowL["szoveg"];
        }
        if ($rowL["tipus"]==2) {
            $webText[$rowL["kulcs"]]=explode(",",$rowL["szoveg"]);
        }
    }
    return $webText;
}

