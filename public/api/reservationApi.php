<?php

session_start();

require(__DIR__."/../../autoload.php");
require("ApiEngine.php");

$apiEngine = new ApiEngine();
$apiEngine->start();

