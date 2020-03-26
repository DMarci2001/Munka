<?php

class SimplePayService {

    const PROVIDER_NAME = "simplePay";
    private $orderId;

    public function __construct()
    {


    }

    public function startPay($id) {
        $this->setOrderId($id);

        $request = [
            "salt" => $this->getSalt(),
            "merchant" => Booking_Constants::SIMPLEPAY_MERCHANT_ID,
            "orderRef" => $this->orderId,
            "currency" => "HUF",
            "customerEmail" => "sdk_test@otpmobil.com",
            "language" => "HU",
            "sdkVersion" => "SimplePayV2.1_Payment_PHP_SDK_2.0.7_190701:dd236896400d7463677a82a47f53e36e",
            "methods" => ["CARD"],
            "total" => "250",
            "timeout" => date("c", strtotime("now + 30 minute")),
            "url" => "https://".$_SERVER["HTTP_HOST"]."/simplePayAck.php"
        ];

        /*
        "invoice" => [
            "name" => "SimplePay V2 Tester",
            "company" => "",
            "country" => "hu",
            "state" => "Budapest",
            "city" => "Budapest",
            "zip" => "1111",
            "address" => "Address 1",
            "address2" => "Address 2",
            "phone" => "06203164978"
        ]
        */

        $result = $this->apiCall("POST", "https://sandbox.simplepay.hu/payment/v2/start", $request);

        //print_r($result["response"]);
        //die;
        if (isset($result["response"]["paymentUrl"])) {
            $this->setTransactionLog($result["response"]["transactionId"], "", $result["response"]["total"]);
            header("location:" . $result["response"]["paymentUrl"]);
            die;
        }

        print_r($result["response"]);
        die;
    }

    public function setOrderId($id) {
        $this->orderId = $id;
    }


    protected function apiCall($method, $url, $requestData = null) {
        $signature = $this->generateSignature(json_encode($requestData));

        $header = ["Content-Type: application/json; charset=utf8", "Signature: {$signature}"];
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($requestData)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 240);

        $result = curl_exec($ch);
        $return['httpCode'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $return['response'] = json_decode($result, true);

        return $return;
    }

    public function generateSignature($message) {
        return base64_encode(hash_hmac('sha384', $message, Booking_Constants::SIMPLEPAY_MERCHANT_SECRET, true));
    }

    private function getSalt($length = 32) {
        $saltBase = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        for ($i=1; $i < $length; $i++) {
            $saltBase .= substr($chars, rand(1, strlen($chars)), 1);
        }
        return hash('md5', $saltBase);
    }

    public function setTransactionLog($transid, $event, $price = 0) {
        if ($transData = sql_fetch_array(sql_query("select * from banktransactions where merchant=? and provider=? and foglalasid=? and transid=?", [Booking_Constants::SIMPLEPAY_MERCHANT_ID, self::PROVIDER_NAME, $this->orderId, $transid]))) {
            $id = $transData["id"];
        } else {
            sql_query("insert into banktransactions set datum=now(), merchant=?, provider=?, foglalasid=?, transid=?", [Booking_Constants::SIMPLEPAY_MERCHANT_ID, self::PROVIDER_NAME, $this->orderId, $transid]);
            $id = sql_insert_id();
        }
        sql_query("update banktransactions set datum=now(), result=? where id=?", [$event, $id]);

        if ($price != 0) {
            sql_query("update banktransactions set osszeg=? where id=?", [$price, $id]);
        }
    }

    public function setAckLog($transid, $json) {
        sql_query("update banktransactions set ackdate=now(), ack=? where merchant=? and provider=? and transid=? ", [$json, Booking_Constants::SIMPLEPAY_MERCHANT_ID, self::PROVIDER_NAME, $transid]);
    }


    public function getTransactionLog($foglalasId) {
        return sql_fetch_array(sql_query("select * from banktransactions where merchant=? and provider=? and foglalasid=? order by datum desc limit 1", [Booking_Constants::SIMPLEPAY_MERCHANT_ID, self::PROVIDER_NAME, $foglalasId]));
    }

    public function simpleLogo() {
        return '<a href="http://simplepartner.hu/PaymentService/Fizetesi_tajekoztato.pdf" target="_blank"> <img width="400" src="/images/simplepay_bankcard_logos_left.jpg" title=" SimplePay - Online bankkártyás fizetés" alt=" SimplePay vásárlói tájékoztató"> </a>';
    }


}