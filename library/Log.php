<?php

class Log {
    const REPORTPROCESS_ID = 45;

    public function __construct() {

    }

    public static function store($type, $action="", $request="", $response=""):int {
        $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "";
        $remoteAddr = $_SERVER["REMOTE_ADDR"] ?? "";

        sql_query("insert into webservicelog set tipus=?, datum=now(), keres=?, response=?, ip=?, useragent=?, action=?", [$type, $request, $response, $remoteAddr, $userAgent, $action]);
        return sql_insert_id();
    }

}