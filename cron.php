<?php

require_once "config.php";
require_once "includes/CronService.php";

$cronService = new CronService();
$cronService->run();

