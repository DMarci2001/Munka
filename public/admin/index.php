<?php

session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$GLOBALS["admin"] = 1;

require_once "../../autoload.php";

$page = new AdminPage();
$page->showPage();
