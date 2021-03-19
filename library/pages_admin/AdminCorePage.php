<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AdminCorePage {
    public $utils;
    public $adminUtils;
    public $lang;
    public $formError = "";
    public $errors = [];
    public $success = [];

    public function __construct()
    {
        $this->utils = new Utils();
        $this->adminUtils = new AdminUtils();
        $this->lang = new Lang();

        //tiltott oldalak
        if (!isset($_SESSION["user"]) && isset($_GET["page"])) {
            if (in_array($_GET["page"], array("beutalok", "documents", "bookinglist"))) {
                header("location:/");
                die();
            }
        }

        $adminAjaxService = new AdminAjaxService();
        $adminAjaxService->start();

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

    public function showErrors($title = "") {
        $html = "";
        if (!empty($this->errors)) {
            $html.= "<div style='margin:0px 0px 20px 0px;background:#f77;color:#fff;border-radius:5px;padding:10px;'>".implode("<br/>", $this->errors)."</div>";
        }
        return $html;
    }

    public function showSuccess($title = "") {
        $html = "";
        if (!empty($this->success)) {
            $html.= "<div style='margin:0px 0px 20px 0px;background:#32cd32;color:#fff;border-radius:5px;padding:10px;'>".implode("<br/>", $this->success)."</div>";
        }
        return $html;
    }

    public function showPlainErrors() {
        $html = "";
        if (isset($_SESSION["error"])) {
            $html.="<div id='errordiv' style='background:#f00;padding:10px;font-weight:bold;color:#fff;text-align:center;'>{$_SESSION["error"]}</div>";
            unset($_SESSION["error"]);
        }
        return $html;
    }

    public function showPlainMessage() {
        $html = "";
        if (isset($_SESSION["message"])) {
            $html.="<div id='errordiv' style='background:#0a0;padding:10px;font-weight:bold;color:#fff;text-align:center;'>{$_SESSION["message"]}</div>";
            unset($_SESSION["message"]);
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

}