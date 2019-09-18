<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . "/includes/BookingConstants_".getConfigFile().".php");
require_once(__DIR__ . "/includes/autoload.php");
require_once(__DIR__ . "/admin/includes/autoload.php");

sql_connect();
sql_query("SET NAMES utf8");
sql_query("SET CHARACTER SET utf8");
sql_query("SET COLLATION_CONNECTION='utf8_unicode_ci'");


function getConfigFile() {
    $config = "";

    if (isset($_GET["config"])) {
        $config = $_GET["config"];
    }

    if (isset($_SERVER["HTTP_HOST"])) {
        $host = $_SERVER["HTTP_HOST"];

        if (substr_count($host, "keltexmed.hu")) {
            $config = "keltexmed";
        }
        if (substr_count($host, "hungariamed.hu")) {
            $config = "hmm";
        }
    }

    if (empty($config)) {
        die("Error loading config!");
    }

    return $config;
}