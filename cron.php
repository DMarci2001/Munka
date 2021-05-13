<?php

if (!empty($argv[1])) {
    parse_str($argv[1], $_GET);
}

require_once "autoload.php";
require_once "library/CronService.php";

$cronService = new CronService();
$cronService->run();

