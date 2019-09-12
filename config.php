<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

//Itt mindig a telepítésnek megfelelő constant file legyen betöltve!
$configFile = "keltexmed";
require_once(__DIR__ . "/includes/BookingConstants_{$configFile}.php");

require_once(__DIR__ . "/includes/autoload.php");
require_once(__DIR__ . "/admin/includes/autoload.php");

sql_connect();
sql_query("SET NAMES utf8");
sql_query("SET CHARACTER SET utf8");
sql_query("SET COLLATION_CONNECTION='utf8_unicode_ci'");


