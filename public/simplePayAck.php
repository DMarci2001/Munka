<?php

require_once "../autoload.php";


if (isset($_GET["r"]) && isset($_GET["s"])) {
    $simpleService = new SimplePayService();
    $data = json_decode(base64_decode($_GET["r"]));

    print_r($data);

    if ($simpleService->generateSignature(base64_decode($_GET["r"])) != $_GET["s"]) {
        die("signature error");
    }


    $transId  = $data->t;
    $event    = $data->e;
    $merchant = $data->m;
    $foglId   = $data->o;

    if (!$foglalasData = sql_fetch_array(sql_query("select * from foglalasok where id=?", [$foglId]))) {
        die("reservation not found");
    }

    $simpleService->setOrderId($foglId);
    $simpleService->setTransactionLog($transId, $event);

    header("location:index.php?page=bookingvalidate&id={$foglalasData["id"]}&rk={$foglalasData["rkod"]}&setlang={$foglalasData["rlang"]}");
    die;
}

