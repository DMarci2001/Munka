<?php

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SESSION["LAST_ACTIVITY"]=time();

require_once "config.php";


$page = new BasePage();
$page->showPage();



