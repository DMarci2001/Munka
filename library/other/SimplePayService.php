<?php

class SimplePayService {

    const PROVIDER_NAME   = "simplePay";
    const SDK_VERSION     = "SimplePayV2.1_Payment_PHP_SDK_2.0.7_190701:dd236896400d7463677a82a47f53e36e";
    const API_URL         = "https://secure.simplepay.hu";
    const API_URL_SANDBOX = "https://sandbox.simplepay.hu";

    private $orderId;
    private $order;
    private $sandBox = false;

    public function __construct()
    {
        if (Booking_Constants::SQL_DB == "keltexmed") {
            $this->setSandBox(true);
        }

        if (isset($_SESSION["labcode"])) {
            //$this->setSandBox(true);
        }
    }

    public function setSandBox($value) {
        $this->sandBox = $value;
    }

    private function _getMerchantId() {
        if ($this->sandBox) {
            return Booking_Constants::SIMPLEPAY_MERCHANT_ID_SANDBOX;
        } else {
            return Booking_Constants::SIMPLEPAY_MERCHANT_ID;
        }
    }

    private function _getMerchantSecret() {
        if ($this->sandBox) {
            return Booking_Constants::SIMPLEPAY_MERCHANT_SECRET_SANDBOX;
        } else {
            return Booking_Constants::SIMPLEPAY_MERCHANT_SECRET;
        }
    }

    private function _getApiUrl() {
        if ($this->sandBox) {
            return self::API_URL_SANDBOX;
        } else {
            return self::API_URL;
        }
    }

    public function startPay($id) {
        $this->setOrderId($id);

        if (empty($this->order)) {
            die("no reservation");
        }

        $logId = $this->addNewTransactionLog();

        $request = [
            "salt" => $this->getSalt(),
            "merchant" => $this->_getMerchantId(),
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

        $result = $this->apiCall("POST", $this->_getApiUrl()."/payment/v2/start", $request);

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
        if (empty($this->order)) {
            $this->order = sql_fetch_array(sql_query("SELECT osszeg AS totalprice, f.email FROM szolgaltatasok_rendelesek_fizetesek fiz 
                LEFT JOIN szolgaltatasok_rendelesek r ON r.id = fiz.order_id
                LEFT JOIN felhasznalok f ON f.id = r.fid
                WHERE fiz.id=?", [str_replace("serv", "", $id)]));

        }
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
        return base64_encode(hash_hmac('sha384', $message, $this->_getMerchantSecret(), true));
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
        if ($this->source == "keltexmedwebshop") {
            $keltexMedSql = new KeltexMedWebSQL();
            $keltexMedSql->sqlQuery("insert into banktransactions set datum=now(), merchant=?, provider=?, orderid=?, result='PENDING'", [$this->keltexmedWebShopMerchant, self::PROVIDER_NAME, $this->orderId]);

            $id = $keltexMedSql->sqlInsertId();
            $id = "keltexmedhu{$id}";

            return $id;
        }

        sql_query("insert into banktransactions set datum=now(), merchant=?, provider=?, foglalasid=?, result='PENDING'", [$this->_getMerchantId(), self::PROVIDER_NAME, $this->orderId]);

        $id = sql_insert_id();
        if (Booking_Constants::SQL_DB != "hungariamed") {
            $id = Booking_Constants::SQL_DB.$id;
        }

        return $id;
    }

    public function setTransactionLog($logId, $transid, $event, $price = 0) {
        if ($this->source == "keltexmedwebshop") {
            $keltexMedSql = new KeltexMedWebSQL();
            $keltexMedSql->sqlQuery("update banktransactions set result=?, transid=? where id=?", [$event, $transid, str_replace("keltexmedhu","", $logId)]);
            if ($price != 0) {
                $keltexMedSql->sqlQuery("update banktransactions set osszeg=? where id=?", [$price, str_replace("keltexmedhu","", $logId)]);
            }
            return;
        }

        sql_query("update banktransactions set result=?, transid=? where id=?", [$event, $transid, str_replace(Booking_Constants::SQL_DB,"", $logId)]);
        if ($price != 0) {
            sql_query("update banktransactions set osszeg=? where id=?", [$price, str_replace(Booking_Constants::SQL_DB,"", $logId)]);
        }
    }

    public function setAckLog($id, $json) {
        if ($this->source == "keltexmedwebshop") {
            $keltexMedSql = new KeltexMedWebSQL();
            $keltexMedSql->sqlQuery("update banktransactions set ackdate=now(), ack=? where id=? ", [$json, str_replace("keltexmedhu","", $id)]);
            return;
        }
        sql_query("update banktransactions set ackdate=now(), ack=? where id=? ", [$json, str_replace(Booking_Constants::SQL_DB,"", $id)]);
    }

    public function getTransactionLog($foglalasId) {
        $row = sql_fetch_array(sql_query("select * from banktransactions where merchant=? and provider=? and foglalasid=? order by datum desc limit 1", [$this->_getMerchantId(), self::PROVIDER_NAME, $foglalasId]));
        //print_r($row);
        return $row;
    }

    public function simpleLogo() {
        return '<a href="http://simplepartner.hu/PaymentService/Fizetesi_tajekoztato.pdf" target="_blank"> <img width="400" src="/images/simplepay_bankcard_logos_left.jpg" title=" SimplePay - Online bankkártyás fizetés" alt=" SimplePay vásárlói tájékoztató"> </a>';
    }

    public function showRefundWindow($source, $id) {
        $return["status"] = "ok";

        $transactionData = false;
        if ($source == "bejelentkezo") {
            $transactionData = sql_fetch_array(sql_query("select * from banktransactions where id=?", [$id]));
        }
        if ($source == "keltexmedwebshop") {
            $keltexMedSql = new KeltexMedWebSQL();
            $transactionData = sql_fetch_array($keltexMedSql->sqlQuery("select * from banktransactions where id=?", [$id]));
        }
        if ($transactionData) {
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

            $html.= "<div id='refunbuttonsor' style='padding-top:10px;'><input onclick='startSimpleRefund(".intval($id).", $(\"#refundprice\").val(), \"{$source}\");return false;' type='button' id='simplerefundbutton' value='Visszautalás' /> <input onclick='hideGeneralPopup();return false;' type='button' id='simplerefundclosebutton' value='Bezárás' /></div>";
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

    private $source = "bejelentkezo";
    private $keltexmedWebShopMerchant = "S581901";

    public function startRefund($id, $osszeg, $source) {
        $this->source = $source;
        $transactionData = false;
        $merchant = $this->_getMerchantId();
        if ($this->source == "bejelentkezo") {
            $transactionData = sql_fetch_array(sql_query("select * from banktransactions where id=?", [$id]));
            if (Booking_Constants::SQL_DB != "hungariamed") {
                $id = Booking_Constants::SQL_DB.$id;
            }
        }
        if ($this->source == "keltexmedwebshop") {
            $keltexMedSql = new KeltexMedWebSQL();
            $transactionData = sql_fetch_array($keltexMedSql->sqlQuery("select * from banktransactions where id=?", [$id]));
            $id = "keltexmedhu{$id}";
            $merchant = $this->keltexmedWebShopMerchant;
        }

        $html = "";

        $request = [
            "salt" => $this->getSalt(),
            "orderRef" => $id,
            "merchant" => $merchant,
            "currency" => "HUF",
            "refundTotal" => intval($osszeg),
            "sdkVersion" => "SimplePayV2.1_Payment_PHP_SDK_2.0.7_190701:dd236896400d7463677a82a47f53e36e"
        ];

        $result = $this->apiCall("POST", $this->_getApiUrl()."/payment/v2/refund", $request);

        if ($result["httpCode"] == 200) {
            if (empty($result["response"]["errorCodes"])) {
                //siker
                $html.= "<span style='font-weight: bold;color:#080;'>A visszautalás sikerült</span>";
                $logId = $this->addNewTransactionLog();
                $this->setTransactionLog($logId, $result["response"]["refundTransactionId"], "REFUND", -intval($result["response"]["refundTotal"]));
                $this->setAckLog($logId, json_encode($result["response"]));
                if ($this->source == "bejelentkezo") {
                    sql_query("update banktransactions set foglalasid=?, parenttransid=? where id=?", [$transactionData["foglalasid"], $id, $logId]);
                }
                if ($this->source == "keltexmedwebshop") {
                    $keltexMedSql->sqlQuery("update banktransactions set orderid=?, parenttransid=? where id=?", [$transactionData["orderid"], $id, $logId]);
                }
            } else {
                //sikertelen
                $html.= "<span style='font-weight: bold;color:#080;'>A visszautalás nem sikerült (hibakód: {$result["response"]["errorCodes"][0]})</span>";
            }
        } else {
            $html.= "<span style='font-weight: bold;color:#080;'>A visszautalás indítása sikertelen</span>";
        }

        $return["html"] = $html;
        $utils = new Utils();
        $utils->jsonOut($return);
        die;
    }

}