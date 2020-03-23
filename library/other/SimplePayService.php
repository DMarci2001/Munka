<?php

class SimplePayService {

    const MERCHANT_ID = "PUBLICTESTHUF";
    const MERCHANT_SECRET = "FxDa5w314kLlNseq2sKuVwaqZshZT5d6";

    public function __construct()
    {



    }


    public function start() {
        $request = [
            "salt" => "126dac8a12693a6475c7c24143024ef8",
            "merchant" => self::MERCHANT_ID,
            "orderRef" => "ee44mmf",
            "currency" => "HUF",
            "customerEmail" => "sdk_test@otpmobil.com",
            "language" => "HU",
            "sdkVersion" => "SimplePayV2.1_Payment_PHP_SDK_2.0.7_190701:dd236896400d7463677a82a47f53e36e",
            "methods" => ["CARD"],
            "total" => "25",
            "timeout" => "2019-09-11T19:14:08+00:00",
            "url" => "https://sdk.simplepay.hu/back.php",
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

        ];

        $result = $this->apiCall("POST", "https://sandbox.simplepay.hu/payment/v2/start", $request);
    }


    protected function apiCall($method, $url, $requestData = null) {
        $signature = $this->generateSignature(json_encode($requestData));
        //$signature = "rV2AffURYaUFMDhZgwN7fYZha0XGFCqsvBlRotCWg4MZ5e/EBZIVU3Vn8yypimPy";

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

    private function generateSignature($message) {
        return base64_encode(hash_hmac('sha384', $message, self::MERCHANT_SECRET, true));
    }

}