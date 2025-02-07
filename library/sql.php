<?php

sql_connect();
sql_query("SET NAMES utf8");
sql_query("SET CHARACTER SET utf8");
sql_query("SET COLLATION_CONNECTION='utf8_unicode_ci'");

function sql_connect() {
    try {
        $GLOBALS["db"] = new PDO("mysql:host=".Booking_Constants::SQL_HOST.";dbname=".Booking_Constants::SQL_DB.";charset=utf8", Booking_Constants::SQL_USER, Booking_Constants::SQL_PASS);
    } catch (PDOException $e) {
        echo "Error 1420<br/>Kérjük próbálkozzon később!";
        //print "Error: " . $e->getMessage();
        die();
    }
}

function sql_query($q,$params=null) {
    if (!empty($GLOBALS["showqueries"])) {
        echo "<pre>".htmlentities($q)."</pre>";
    }
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

function sql_fetch_row( $stmt ) {
	//return mysqli_fetch_assoc($stmt);
	$row = $stmt->fetch( PDO::FETCH_NUM );
	return $row;
}

function sql_num_rows($stmt) {
    return $stmt->rowCount();
}

function sql_insert_id() {
    return $GLOBALS["db"]->lastInsertId();
}

function get_client_ip()
{
    foreach (array(
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'REMOTE_ADDR') as $key) {
        if (array_key_exists($key, $_SERVER)) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if ((bool) filter_var($ip, FILTER_VALIDATE_IP,
                                FILTER_FLAG_IPV4 |
                                FILTER_FLAG_NO_PRIV_RANGE |
                                FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    return null;
}

function logActivity($tipus, $id=0, $megnev="", $query="") {
    $adminId = $_SESSION["adminuser"]["id"] ?? 0;
    $orvosId = $_SESSION["adminuser"]["orvosid"] ?? 0;
    $pid = 0;

    $megnev.= isset($GLOBALS["extraloginfo"]) ? " ({$GLOBALS["extraloginfo"]})" : "";

    sql_query("insert into activitylog set datum=now(),userid=?,orvoslogin=?,tipus=?,mid=?,pid=?,megnev=?,query=?", [$adminId, $orvosId, $tipus, $id, $pid, $megnev, $query]);
}

function logintryLog($type="",$username="",$status="",$smscode=""){
    if($type=="logintry"){
        sql_query("INSERT INTO logintry_log SET username=?,type=?,status=?,datum=?,ip_address=?",[$username,$type,$status,date("Y-m-d H:i:s"),get_client_ip()]);
    }

    if($type=="smscodetry"){
        sql_query("INSERT INTO logintry_log SET username=?,type=?,smscode=?,status=?,datum=?,ip_address=?",[$username,$type,$smscode,$status,date("Y-m-d H:i:s"),get_client_ip()]);
    }

    return;
    //logintry_log
}

