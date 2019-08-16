<?php

class CorePage {
    public $utils;
    public $lang;
    public $formError = "";

    public function __construct()
    {
        $this->utils = new Utils();
        $this->lang = new Lang();
    }

    public function displayFejlec($title = "")
    {
        $webText = $this->lang->webText;
        $style = "";
        if ($_SESSION['helyszindata']['id'] == 91) {
            $img = "<img src='images/hungarian_crest.png' height='30' />";
        } else $img = "";

        if ($_SESSION["helyszindata"]["fejleccolor"] != "") $style .= "background:{$_SESSION["helyszindata"]["fejleccolor"]};";


        return "<div class='fejlecdiv' style='{$style}'>{$img} {$_SESSION["helyszindata"]["megnev"]} - {$webText["idopontfoglalas"]}" . ($title != "" ? " - {$title}" : "") . "</div>";
    }

    public function showFormErrors() {
        $html = "";
        if (!empty($this->formError)) {
            $html.= "<div style='margin:0px 0px 10px 0px;background:#f77;color:#fff;border-radius:5px;padding:10px;'>".$this->formError."</div>";
        }
        return $html;
    }

    public function formMessage($message) {
        $html = "<div style='margin:0px 0px 10px 0px;background:#8a8;color:#fff;border-radius:5px;padding:10px;'>{$message}</div>";
        return $html;
    }

}