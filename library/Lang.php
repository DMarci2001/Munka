<?php

class Lang {
    public $webText;

    public $validLanguages = ["hu","en","de"];

    public function __construct()
    {
        if (!isset($_COOKIE["lang"])) {
            self::setLang("hu");
        }

        $this->webText = $this->getWebTexts($_COOKIE["lang"]);
    }

    public function getWebTexts($lang) {
        $webText = [];
        $resL=sql_query("select * from langtext where langid=?",array($lang));
        while ($rowL=sql_fetch_array($resL)) {
            if ($rowL["tipus"]==0) {
                $webText[$rowL["kulcs"]]=$rowL["szoveg"];
            }
            if ($rowL["tipus"]==2) {
                $webText[$rowL["kulcs"]]=explode(",",$rowL["szoveg"]);
            }

            $webText[$rowL["kulcs"]] = str_replace("#adatvedelmilink#", Booking_Constants::ADATVEDELMI_URL, $webText[$rowL["kulcs"]]);

            $webText[$rowL["kulcs"]] = str_replace("#adatvedelmilinkhc#", "https://hc.hungariamed.hu/images/aszf_covid_hmm_hc.pdf", $webText[$rowL["kulcs"]]);
        }
        return $webText;
    }

    public function getText($key, $default = "") {
        if (isset($this->webText[$key])) {
            return $this->webText[$key];
        }
        if (!empty($default)) {
            $this->webText[$key] = $default;
            foreach ($this->validLanguages as $lang) {
                sql_query("insert into langtext set langid=?, kulcs=?, szoveg=?", array($lang, $key, $default));
            }
            return $default;
        }
        return "";
    }

    public static function getLangLink($langCode) {
        $link = $_SERVER["PHP_SELF"];
        $queryString = $_SERVER["QUERY_STRING"];

        if (substr_count($queryString, "slang=")) {
            $queryString = substr($queryString, 0, strpos($queryString, "slang=")-1);
        }

        if ($queryString != "") {
            $link.="?{$queryString}&";
        } else {
            $link.="?";
        }

        if (substr_count($link,"?page=") == 0 && substr_count($link,"&page=") == 0) {
            if (isset($_GET["page"]) && in_array($_GET["page"],array("main","welcome","booking"))) {
                $link.="page={$_GET["page"]}&";
            }
        }
		
		if ($_GET['page']=="remoteBooking") {
		    return;
        }

        $langLink = "<a class='toplink' style='".($_COOKIE["lang"] == $langCode ? "opacity:1":"opacity:.5")."' href='{$link}lang={$langCode}'>".strtoupper($langCode)."</a> ";
        return $langLink;
    }

    public static function setLang($lang) {
        $exp = time() + 60 * 60 * 24 * 365;
        if (in_array($lang, ["hu","en","de"])) {
            setcookie("lang", $lang, $exp, "/");
            $_COOKIE["lang"] = $lang;
        }
    }

    public static function multiLangField($array, $field) {
        $return = $array[$field];
        if ($_COOKIE["lang"]!="hu" && trim($array["{$field}_{$_COOKIE["lang"]}"])!="") {
            $return = $array["{$field}_{$_COOKIE["lang"]}"];
        }
        return $return;
    }

}
