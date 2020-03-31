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


    if (!$foglalasData = sql_fetch_array(sql_query("select f.* from banktransactions b left join foglalasok f on f.id = b.foglalasid where b.id=?", [$result->orderRef]))) {
        die("reservation not found");
    }

    $jsonResponse = json_encode($response);

    $simpleService->setOrderId($foglalasData["id"]);
    $simpleService->setTransactionLog($result->orderRef, $result->transactionId, $result->status);
    $simpleService->setAckLog($result->orderRef, $json);

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

    if ($simpleService->generateSignature(base64_decode($_GET["r"])) != $_GET["s"]) {
        die("signature error");
    }

    $transId  = $data->t;
    $event    = $data->e;
    $merchant = $data->m;
    $orderRef = $data->o;

    if (!$foglalasData = sql_fetch_array(sql_query("select f.* from banktransactions b left join foglalasok f on f.id = b.foglalasid where b.id=?", [$orderRef]))) {
        die("reservation not found");
    }

    $simpleService->setOrderId($foglalasData["id"]);
    $simpleService->setTransactionLog($orderRef, $transId, $event);

    header("location:index.php?page=bookingvalidate&id={$foglalasData["id"]}&rk={$foglalasData["rkod"]}&setlang={$foglalasData["rlang"]}");
    die;
}

