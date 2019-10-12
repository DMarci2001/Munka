<?php

session_start();

require_once "../autoload.php";

$page = new Page();
$page->showPage();
