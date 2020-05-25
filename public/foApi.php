<?php

require_once "../autoload.php";
//require_once "../library/other/nusoap/nusoap.php";
require_once "../library/FoglaljOrvostSoapServer.php";

$foService = new FoglaljOrvostService();
$foService->processTestInput();

$GLOBALS["foSoapService"] = new FoglaljOrvostSoapServer();
$GLOBALS["foSoapService"]->startServer();
