<?php

session_start();

$GLOBALS["admin"] = 1;

require_once "../../autoload.php";

$page = new AdminPage();
$page->showPage();
