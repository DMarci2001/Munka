<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

sql_connect();

sql_query("SET NAMES utf8");
sql_query("SET CHARACTER SET utf8");
sql_query("SET COLLATION_CONNECTION='utf8_unicode_ci'");


$exp=time() + 60 * 60 * 24 * 365;
if (!isset($_COOKIE["lang"])) {
	setcookie("lang","hu",$exp,"/");
	$_COOKIE["lang"]="hu";
}

if (isset($_GET["setlang"])) $_GET["lang"] = $_GET["setlang"];
if (isset($_GET["lang"]) && in_array($_GET["lang"],array("hu","de","en"))) {
	setcookie("lang",$_GET["lang"],$exp,"/");
	$params = $_SERVER["QUERY_STRING"];
    $params = str_replace("lang=","slang=",$params);
    $params = str_replace("setlang=","slang=",$params);
	header("location:index.php?{$params}");
	die();
}

require_once($_SERVER["DOCUMENT_ROOT"]."/includes/autoload.php");
require_once($_SERVER["DOCUMENT_ROOT"] ."/admin/includes/autoload.php");


if (isset($_GET["phpinfo_jns"])) {
    phpinfo();
    die();
}


function sql_connect() {
	$MYSQL_USER="hungariamed";
	$MYSQL_PASS="hmedpass";
	$MYSQL_HOST="localhost";
	$MYSQL_DB="keltexmed";

	if (substr_count(getSubDomain(),"teszt")) $MYSQL_DB="hungariamedteszt";

	try {
		$GLOBALS["db"]=new PDO("mysql:host={$MYSQL_HOST};dbname={$MYSQL_DB};charset=utf8", $MYSQL_USER, $MYSQL_PASS);
	} catch (PDOException $e) {
        print "Error: " . $e->getMessage();
        die();
	}
}


function sql_query($q,$params=null) {
    $GLOBALS["alltime"] = 0;
    $startTime = microtime(true);
	$stmt=$GLOBALS["db"]->prepare($q);
	$stmt->execute($params);
	$error=$stmt->errorInfo();
	if ($error[2]!="") print_r($error);
    $endTime = microtime(true);
    if ($_SERVER["REMOTE_ADDR"]=="194.143.226.42") {
        $time = $endTime - $startTime;
        $GLOBALS["alltime"] += $time;
        //echo str_replace("?","%",$q)." ".print_r($params,true)." ".$time." ".$GLOBALS["alltime"]."<br/>";
    }
	return $stmt;
}


function sql_fetch_array($stmt) {
	$row=$stmt->fetch(PDO::FETCH_ASSOC);
	return $row;
}

function sql_num_rows($stmt) {
	return $stmt->rowCount();
}

function sql_insert_id() {
	return $GLOBALS["db"]->lastInsertId();
}


function isTesztIP() {
	return in_array($_SERVER["REMOTE_ADDR"],array("88.151.97.121","81.182.23.124","5.204.54.10","81.182.23.106"));
}

function logActivity($tipus,$id=0,$megnev="",$query="") {
	$pid=0;
	sql_query("insert into activitylog set datum=now(),userid=?,orvoslogin=?,tipus=?,mid=?,pid=?,megnev=?,query=?",array($_SESSION["adminuser"]["id"],$_SESSION["adminuser"]["orvosid"],$tipus,$id,$pid,$megnev,$query));
}

