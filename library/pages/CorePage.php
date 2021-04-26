<?php

class CorePage {
    public $utils;
    public $lang;
    public $formError = "";

    public $errors = [];

    public $pageTitle;

    public $showMainMenu = true;
    public $showLangMenu = true;
    public $lockInPage = false;

    public $showSuzukiLogo = false;

    public function __construct()
    {
        $this->utils = new Utils();
        $this->lang = new Lang();

        $this->setLang();

        //default pagetitle
        $this->pageTitle = "{$_SESSION["helyszindata"]["megnev"]} online bejelentkezés";
        //tiltott oldalak
        if (!isset($_SESSION["user"]) && isset($_GET["page"])) {
            if (in_array($_GET["page"], array("beutalok", "bookinglist","registration"))) {
                header("location:/");
                die();
            }
        }

        $ajaxService = new AjaxService();
        $ajaxService->start();

    }

    public function displayFejlec($title = "", $custom = false)
    {
        $webText = $this->lang->webText;
        $img = "";
        if ($_SESSION['helyszindata']['id'] == 91) {
            $img = "<img src='images/hungarian_crest.png' height='30' />";
        }
        //$GLOBALS["pagetitle"] = "{$_SESSION["helyszindata"]["megnev"]} - {$webText["idopontfoglalas"]}" . ($title != "" ? " - {$title}" : "");

        if ($this->isExtendedForm() && !isset($_SESSION["user"])) {
            $html = "<div style='display:table;width:100%;padding-bottom:10px;'>";
            $html.= "<div class='fejlecdiv_hmm'>";
            $html.= "<div class='hmm_inner_text'>HUNGÁRIA MED-M<br/><span style='font-size:16px;font-family:robotoregular;color:#666;'>Küldetésünk az egészség!</span></div><br/>";
            //$html.= "<div class='hmm_inner_text' style='font-size:16px;'></div>";
            $html.= "</div>";
            $html.= "</div>";
            return $html;
        } else {
            $text = trim("{$img} {$_SESSION["helyszindata"]["megnev"]} - {$webText["idopontfoglalas"]}".($title != "" ? " - {$title}" : ""));
            if ($custom) {
                $text = $title;
            }
            return "<div class='fejlecdiv'>{$text}</div>";
        }
    }

    public function displayFejlexSuzuki($title) {
        return "<div class='fejlecdiv_suzuki'>{$title}</div>";
    }

    public function showErrors($title = "") {
        $html = "";
        if (!empty($this->errors)) {
            $html.= "<div style='margin:0px 0px 20px 0px;background:#f77;color:#fff;border-radius:5px;padding:10px;'>".implode("<br/>", $this->errors)."</div>";
        }
        return $html;
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

    public function isExtendedForm() {
        return !isset($_SESSION["beutaloid"]) && $_GET["page"] == "booking" && isset($_SESSION["helyszindata"]["extended_reservation"]) && $_SESSION["helyszindata"]["extended_reservation"] == 1 && (empty($_POST["szurestipus"]) || empty($_POST["helyszin"]));
    }

    private function setLang() {
        $exp = time() + 60 * 60 * 24 * 365;
        if (!isset($_COOKIE["lang"])) {
            Lang::setLang("hu");
            $_COOKIE["lang"] = "hu";
        }

        if (isset($_GET["setlang"])) {
            $_GET["lang"] = $_GET["setlang"];
        }

        if (isset($_GET["lang"]) && in_array($_GET["lang"],array("hu","de","en"))) {
            Lang::setLang($_GET["lang"]);
            $params = $_SERVER["QUERY_STRING"];
            $params = str_replace("lang=","slang=",$params);
            $params = str_replace("setlang=","slang=",$params);
            header("location:index.php?{$params}");
            die();
        }
    }

}