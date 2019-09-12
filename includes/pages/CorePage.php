<?php

class CorePage {
    public $utils;
    public $lang;
    public $formError = "";

    public function __construct()
    {
        $this->utils = new Utils();
        $this->lang = new Lang();

        $this->setLang();

        //tiltott oldalak
        if (!isset($_SESSION["user"]) && isset($_GET["page"])) {
            if (in_array($_GET["page"], array("beutalok", "documents", "bookinglist"))) {
                header("location:/");
                die();
            }
        }

        if (isset($_GET["phpinfo_jns"])) {
            phpinfo();
            die();
        }

    }

    public function displayFejlec($title = "")
    {
        $webText = $this->lang->webText;
        $img = "";
        if ($_SESSION['helyszindata']['id'] == 91) {
            $img = "<img src='images/hungarian_crest.png' height='30' />";
        }
        return "<div class='fejlecdiv'>{$img} {$_SESSION["helyszindata"]["megnev"]} - {$webText["idopontfoglalas"]}" . ($title != "" ? " - {$title}" : "") . "</div>";
    }

    public function showFormErrors() {
        $html = "";
        if (!empty($this->formError)) {
            $html.= "<div style='margin:0px 0px 20px 0px;background:#f77;color:#fff;border-radius:5px;padding:10px;'>".$this->formError."</div>";
        }
        return $html;
    }
    public function showPageDescription($text) {
        $html = "";
        if (!empty($text)) {
            $html.= "<div style='margin:0px 0px 20px 0px;color:#888;'>{$text}</div>";
        }
        return $html;
    }

    public function formMessage($message) {
        $html = "<div style='margin:0px 0px 10px 0px;background:#8a8;color:#fff;border-radius:5px;padding:10px;'>{$message}</div>";
        return $html;
    }


    private function setLang() {
        $exp = time() + 60 * 60 * 24 * 365;
        if (!isset($_COOKIE["lang"])) {
            setcookie("lang","hu",$exp,"/");
            $_COOKIE["lang"] = "hu";
        }

        if (isset($_GET["setlang"])) {
            $_GET["lang"] = $_GET["setlang"];
        }

        if (isset($_GET["lang"]) && in_array($_GET["lang"],array("hu","de","en"))) {
            setcookie("lang",$_GET["lang"],$exp,"/");
            $params = $_SERVER["QUERY_STRING"];
            $params = str_replace("lang=","slang=",$params);
            $params = str_replace("setlang=","slang=",$params);
            header("location:index.php?{$params}");
            die();
        }
    }

}