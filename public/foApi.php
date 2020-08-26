<?php

require_once "../autoload.php";
require_once "../library/foglaljorvost/FoglaljOrvostSoapServer.php";

$foService = new FoglaljOrvostService();
$foService->processTestInput();

$GLOBALS["foSoapService"] = new FoglaljOrvostSoapServer();
$GLOBALS["foSoapService"]->startServer();
