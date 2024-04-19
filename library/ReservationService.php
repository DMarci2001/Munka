<?php

class ReservationService {

    const API_URL = "https://bejelentkezes.hungariamed.hu/hmmapi";
    const API_USER = "bejelentkezo";
    const API_PASSWORD = "med5551";

    public string $token = "";
    public int $reservationTypeId = 0;
    public int $cartRow = 0;
    public int $num = 0;
    public string $startDate = "";
    public string $endDate = "";
    public int $companyId = 0;
    public int $locationId = 0;

    public function __construct()
    {
        $this->getToken();
        $this->startDate = date("Y-m-d", strtotime("now +1 day"));
        $this->companyId = Booking_Constants::DEFAULT_COMPANY_ID;
    }


    public function getToken() {
        $result = $this->_apiCall("/token", "POST", "username=".SELF::API_USER."&grant_type=password&password=".SELF::API_PASSWORD);
        if (isset($result["access_token"])) {
            $this->token = $result["access_token"];
        }
    }

    public function getSlots() {
        return $this->_apiCall("/slots?startDate=".$this->startDate."&specializationId=".$this->reservationTypeId."&companyId=".$this->companyId);
    }

    public function getSlotsPlace() {
        return $this->_apiCall("/slots?startDate=".$this->startDate."&endDate=".$this->endDate."&specializationId=".$this->reservationTypeId."&locationId=".$this->locationId."&companyId=".$this->companyId);
    }


    private function _apiCall($action, $method = "GET", $postFields = "") {
        $headers = [];
        if ($method == "POST") {
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
        }
        if (!empty($this->token)) {
            $headers[] = "Authorization: Bearer {$this->token}";
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => SELF::API_URL.$action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function displaySlots():string {
        $slots = $this->getSlots();

        $html = "";
        $html.= "<div style='background:#f8f8f8;padding:10px;margin:10px 0px;border-radius: 5px;'>";
        $html.= "<div style=''>Szabad időpontok</div>";

        $dates = [];
        foreach ($slots as $slot) {
            $date = date("Y-m-d", strtotime($slot["date"]));
            $dates[$date][] = $slot;
        }

        $showLimit = 5;
        foreach ($dates as $day => $slots) {
            $html.="<div style='margin-top:5px;font-weight: bold;border-bottom: 1px solid #ccc;'>{$day} ".$GLOBALS["naptext"][date("N", strtotime($day))]."</div>";
            foreach ($slots as $slot) {
                $extraStyle = "";
                $timeExists = 0;
                $time = date("H:i", strtotime($slot["date"]));
                for ($i = 1;$i<= 20;$i++) {
                    if (isset($_SESSION["cartTimes"][$this->reservationTypeId]) && $_SESSION["cartTimes"][$this->reservationTypeId]["time"] == "{$day} {$time}") {
                        $extraStyle = "box-sizing: border-box;border:2px solid #000;";
                        $timeExists = 1;
                        break;
                    }
                }
                $html.= "<a href='#' data-timeexists='{$timeExists}' data-time='{$day} {$time}' data-doctorid='{$slot["doctorId"]}' data-length='{$slot["length"]}' data-cartrow='{$this->cartRow}' data-num='{$this->num}' data-reservationtypeid='{$this->reservationTypeId}' class='freesubidopontbutton' style='{$extraStyle}'>{$time}</a> ";
            }
            $showLimit--;
            if ($showLimit == 0) {
                break;
            }
        }

        $html.= "</div>";

        return $html;
    }

    public static function removeOldTimes() {
        if (isset($_SESSION["cartTimes"])) {
            foreach ($_SESSION["cartTimes"] as $cartKey => $cartTime) {
                foreach ($cartTime as $key => $timeData) {
                    if (strtotime($timeData["registered"] . " + 1 hour") < strtotime("now")) {
                        unset($_SESSION["cartTimes"][$cartKey][$key]);
                    }
                }
            }
        }
    }

    public function reserveCartTime($cartTime, $orderData, $orderItem):array {
        $result = [];

        $fizmod = "készpénz";
        if ($orderData["fizmod"] == "simple") {
            $fizmod = "simplepay-el fizetve";
        }

        foreach ($cartTime as $time) {
            $reservationData = [
                "id" => 0,
                "slotId" => 0,
                "locationId" => "292",
                "specializationId" => $time["reservationTypeId"],
                "doctorId" => $time["doctorId"],
                "date" => $time["time"],
                "length" => $time["length"],
                "patientName" => $orderData["nev"],
                "patientPhone" => $orderData["telefon"],
                "patientEmail" => $orderData["email"],
                "patientDateOfBirth" => "",
                "patientMothersName" => "",
                "patientComment" => "Vásárlás a keltexmed.hu-ról (fizetési mód: {$fizmod}) {$orderItem["productname"]}",
                "patientNotification" => false,
                "authorizationCode" => ""
            ];

            $result = $this->_apiCall("/reservations", "POST", json_encode($reservationData, JSON_PRETTY_PRINT));
        }

        return $result;
    }

}