<?php

class SimplePayService {

    const PROVIDER_NAME = "simplePay";
    const SDK_VERSION = "SimplePayV2.1_Payment_PHP_SDK_2.0.7_190701:dd236896400d7463677a82a47f53e36e";
    const API_URL = "https://sandbox.simplepay.hu";
    //sandbox : https://sandbox.simplepay.hu
    //live:     https://secure.simplepay.hu

    private $orderId;
    private $order;

    public function __construct()
    {


    }

    public function startPay($id) {
        $this->setOrderId($id);

        if (empty($this->order)) {
            die("no reservation");
        }

        $logId = $this->addNewTransactionLog();

        $request = [
            "salt" => $this->getSalt(),
            "merchant" => Booking_Constants::SIMPLEPAY_MERCHANT_ID,
            "orderRef" => $logId,
            "currency" => "HUF",
            "customerEmail" => $this->order["email"],
            "language" => "HU",
            "sdkVersion" => self::SDK_VERSION,
            "methods" => ["CARD"],
            "total" => $this->order["totalprice"],
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

        $result = $this->apiCall("POST", self::API_URL."/payment/v2/start", $request);

        //print_r($result["response"]);
        //die;
        if (isset($result["response"]["paymentUrl"])) {
            $this->setTransactionLog($logId, $result["response"]["transactionId"], "PENDING", $result["response"]["total"]);
            header("location:" . $result["response"]["paymentUrl"]);
            die;
        }

        print_r($result["response"]);
        die;
    }

    public function setOrderId($id) {
        $this->orderId = $id;
        $this->order = sql_fetch_array(sql_query("select * from foglalasok where id=?", [$id]));
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

    public function addNewTransactionLog() {
        sql_query("insert into banktransactions set datum=now(), merchant=?, provider=?, foglalasid=?, result='PENDING'", [Booking_Constants::SIMPLEPAY_MERCHANT_ID, self::PROVIDER_NAME, $this->orderId]);
        return sql_insert_id();
    }

    public function setTransactionLog($logId, $transid, $event, $price = 0) {
        sql_query("update banktransactions set datum=now(), result=?, transid=? where id=?", [$event, $transid, $logId]);
        if ($price != 0) {
            sql_query("update banktransactions set osszeg=? where id=?", [$price, $logId]);
        }
    }

    public function setAckLog($id, $json) {
        sql_query("update banktransactions set ackdate=now(), ack=? where id=? ", [$json, $id]);
    }

    public function getTransactionLog($foglalasId) {
        return sql_fetch_array(sql_query("select * from banktransactions where merchant=? and provider=? and foglalasid=? order by datum desc limit 1", [Booking_Constants::SIMPLEPAY_MERCHANT_ID, self::PROVIDER_NAME, $foglalasId]));
    }

    public function simpleLogo() {
        return '<a href="http://simplepartner.hu/PaymentService/Fizetesi_tajekoztato.pdf" target="_blank"> <img width="400" src="/images/simplepay_bankcard_logos_left.jpg" title=" SimplePay - Online bankkártyás fizetés" alt=" SimplePay vásárlói tájékoztató"> </a>';
    }

    public function showRefundWindow($id) {
        $return["status"] = "ok";
        if ($transactionData = sql_fetch_array(sql_query("select * from banktransactions where id=?", [$id]))) {
            $html = "";
            $html.= "<div style='color:#444;text-align:center;'>";
            $html.= "<div id='loginbox' class='loginbox'>";
            $html.= "<div class='loginhead'>simplePay visszautalás</div>";

            $html.= "<div style='padding:20px;text-align:center;'>";
            $html.= "<div style='font-size:18px;'>Tranzakció: {$transactionData["transid"]} - {$transactionData["osszeg"]} Ft</div>";

            if ($foglalasData = sql_fetch_array(sql_query("select * from foglalasok where id=?", [$transactionData["foglalasid"]]))) {
                $html.= "<div style='margin-top:10px;'>Ügyfél: {$foglalasData["nev"]} / {$foglalasData["telefon"]}</div>";
            }

            $html.= "<div style='margin-top:10px;'>Adja meg az összeget amit vissza akar utalni:<br/>(részösszeg is visszautalható)</div>";
            $html.= "<div style='padding-top:5px;'><input type='text' style='width:100px;' id='refundprice' placeholder='' value='{$transactionData["osszeg"]}' /></div>";
            $html.= "<div style='margin-top:10px;display:none;' id='transferresult'></div>";

            $html.= "<div id='refunbuttonsor' style='padding-top:10px;'><input onclick='startSimpleRefund(".intval($id).", $(\"#refundprice\").val());return false;' type='button' id='simplerefundbutton' value='Visszautalás' /> <input onclick='hideGeneralPopup();return false;' type='button' id='simplerefundclosebutton' value='Bezárás' /></div>";
            $html.= "</div>";

            $html.= "</div>";
            $html.= "</div>";

            $return["html"] = $html;
        } else {
            $return["status"] = "Hiba!";
        }

        $utils = new Utils();
        $utils->jsonOut($return);
        die;
    }

    public function startRefund($id, $osszeg) {
        $request = [
            "salt" => $this->getSalt(),
            "orderRef" => $id,
            "merchant" => Booking_Constants::SIMPLEPAY_MERCHANT_ID,
            "currency" => "HUF",
            "refundTotal" => intval($osszeg),
            "sdkVersion" => "SimplePayV2.1_Payment_PHP_SDK_2.0.7_190701:dd236896400d7463677a82a47f53e36e"
        ];

        $result = $this->apiCall("POST", self::API_URL."/payment/v2/refund", $request);

        $return["html"] = print_r($request, true)."<br><br>".print_r($result, true);
        $utils = new Utils();
        $utils->jsonOut($return);
        die;
    }

}