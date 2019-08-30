<?php

session_start();

require_once "config.php";

$page = new Page();
$page->showPage();
