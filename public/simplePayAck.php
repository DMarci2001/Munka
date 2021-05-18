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

    if (substr_count($result->orderRef, "gyor") == 0) {
        if (!$foglalasData = sql_fetch_array(sql_query("select f.* from banktransactions b left join foglalasok f on f.id = b.foglalasid where b.id=?", [$result->orderRef]))) {
            die("reservation not found");
        }
        if ($result->status == "FINISHED") {
            $bookingService = new BookingService();
            $bookingService->notificationService->sendUserReservationNotification($foglalasData["id"]);
            //$bookingService->sendToCegAndOrvos($foglalasData["id"]);
        }

        $simpleService->setOrderId($foglalasData["id"]);
        $simpleService->setTransactionLog($result->orderRef, $result->transactionId, $result->status);
        $simpleService->setAckLog($result->orderRef, $json);
    } else {
        //keresés a győri adatbázisban!
        $result = file_get_contents("https://audi.hungariamed.hu/api/ackCheck.php?id={$result->orderRef}&status={$result->status}&transactionid={$result->transactionId}");
        if (substr_count($result, "ok") == 0) {
            die("reservation not found");
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Signature: ' . $simpleService->generateSignature(json_encode($response)));

    echo json_encode($response);
    die;
}


/*
    SimplePay visszairányítás elkapása
*/
if (isset($_GET["r"]) && isset($_GET["s"])) {
    $data = json_decode(base64_decode($_GET["r"]));

    //print_r($_SERVER);die;
    $transId  = $data->t;
    $event    = $data->e;
    $merchant = $data->m;
    $orderRef = $data->o;

    if (!$foglalasData = sql_fetch_array(sql_query("select f.*, b.foglalasid, b.merchant, b.id as banktransid from banktransactions b left join foglalasok f on f.id = b.foglalasid where b.id=?", [$orderRef]))) {
        die("reservation not found");
    }

    if ($foglalasData["merchant"] == "PUBLICTESTHUF") {
        $simpleService->setSandBox(true);
    }

    if ($simpleService->generateSignature(base64_decode($_GET["r"])) != $_GET["s"]) {
        die("signature error");
    }

    $simpleService->setTransactionLog($orderRef, $transId, $event);

    if (substr($foglalasData["foglalasid"], 0, 4) == "serv") {
        //szolgáltatás vásárlás leágazás
        $simpleService->setOrderId($foglalasData["foglalasid"]);
        header("location:index.php?page=services&paymentresult={$foglalasData["foglalasid"]}&transid={$foglalasData["banktransid"]}");
        die;
    }


    $simpleService->setOrderId($foglalasData["id"]);

    header("location:index.php?page=bookingvalidate&id={$foglalasData["id"]}&rk={$foglalasData["rkod"]}&setlang={$foglalasData["rlang"]}");
    die;
}

