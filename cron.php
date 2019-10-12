<?php

require_once "autoload.php";
require_once "library/CronService.php";

$cronService = new CronService();
$cronService->run();

