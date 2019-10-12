<?php

sql_connect();
sql_query("SET NAMES utf8");
sql_query("SET CHARACTER SET utf8");
sql_query("SET COLLATION_CONNECTION='utf8_unicode_ci'");

function sql_connect() {
    try {
        $GLOBALS["db"] = new PDO("mysql:host=".Booking_Constants::SQL_HOST.";dbname=".Booking_Constants::SQL_DB.";charset=utf8", Booking_Constants::SQL_USER, Booking_Constants::SQL_PASS);
    } catch (PDOException $e) {
        print "Error: " . $e->getMessage();
        die();
    }
}

function sql_query($q,$params=null) {
    $stmt = $GLOBALS["db"]->prepare($q);
    $stmt->execute($params);
    $error = $stmt->errorInfo();
    if ($error[2] != "") {
        print_r($error);
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

function logActivity($tipus, $id=0, $megnev="", $query="") {
    $pid = 0;
    sql_query("insert into activitylog set datum=now(),userid=?,orvoslogin=?,tipus=?,mid=?,pid=?,megnev=?,query=?",array($_SESSION["adminuser"]["id"],$_SESSION["adminuser"]["orvosid"],$tipus,$id,$pid,$megnev,$query));
}

