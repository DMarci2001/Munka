<?php

require_once "../autoload.php";


$simpleService = new SimplePayService();

/*
    IPN fogadása
*/
if (isset($_GET["ack"])) {
    $json = file_get_contents('php://input');
    $result = json_decode($json);

    $response = [
        "salt" => $result->salt,
        "orderRef" => $result->orderRef,
        "method" => $result->method,
        "merchant" => $result->merchant,
        "finishDate" => $result->finishDate,
        "paymentDate" => $result->paymentDate,
        "transactionId" => $result->transactionId,
        "status" => $result->status,
        "receiveDate" => date("c")
    ];


    if (!$foglalasData = sql_fetch_array(sql_query("select * from foglalasok where id=?", [$result->orderRef]))) {
        die("reservation not found");
    }

    $jsonResponse = json_encode($response);

    $simpleService->setOrderId($result->orderRef);
    $simpleService->setTransactionLog($result->transactionId, $result->status);
    $simpleService->setAckLog($result->transactionId, $json);

    header('Content-Type: application/json; charset=utf-8');
    header('Signature: ' . $simpleService->generateSignature($jsonResponse));

    echo $jsonResponse;
    die;
}


/*
    SimplePay visszairányítás elkapása
*/
if (isset($_GET["r"]) && isset($_GET["s"])) {
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

