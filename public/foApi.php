<?php

require_once "../autoload.php";
require_once "../library/FoglaljOrvostService.php";

$foService = new FoglaljOrvostService();
$foService->processInput();
