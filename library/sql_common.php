<?php

sql_connect_common();
sql_query_common("SET NAMES utf8");
sql_query_common("SET CHARACTER SET utf8");
sql_query_common("SET COLLATION_CONNECTION='utf8_unicode_ci'");

function sql_connect_common() {
    if (defined("Booking_Constants::SQL_DB_COMMON")) {
        try {
            $GLOBALS["db_common"] = new PDO("mysql:host=" . Booking_Constants::SQL_HOST_COMMON . ";dbname=" . Booking_Constants::SQL_DB_COMMON . ";charset=utf8", Booking_Constants::SQL_USER_COMMON, Booking_Constants::SQL_PASS_COMMON);
        } catch (PDOException $e) {
            print "Error: " . $e->getMessage();
            die();
        }
    } else {
        try {
            $GLOBALS["db_common"] = new PDO("mysql:host=" . Booking_Constants::SQL_HOST . ";dbname=" . Booking_Constants::SQL_DB . ";charset=utf8", Booking_Constants::SQL_USER, Booking_Constants::SQL_PASS);
        } catch (PDOException $e) {
            print "Error: " . $e->getMessage();
            die();
        }
    }
}


function sql_query_common($q,$params=null) {
    $stmt = $GLOBALS["db_common"]->prepare($q);
    $stmt->execute($params);
    $error = $stmt->errorInfo();
    if ($error[2] != "") {
        print_r($error);
    }
    return $stmt;
}

function sql_fetch_array_common($stmt) {
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row;
}

function sql_fetch_row_common( $stmt ) {
    //return mysqli_fetch_assoc($stmt);
    $row = $stmt->fetch( PDO::FETCH_NUM );
    return $row;
}

function sql_num_rows_common($stmt) {
    return $stmt->rowCount();
}

function sql_insert_id_common() {
    return $GLOBALS["db_common"]->lastInsertId();
}



