<?php

class Maps {
    private string $apiKey;
    private string $geoCodeJSON = "";

    public function __construct() {
        $this->apiKey = Booking_Constants::GOOGLE_MAPS_API_KEY;
    }

    public function geoCoding($address) {
        $this->geoCodeJSON = file_get_contents("https://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&key=".$this->apiKey);
        return $this->geoCodeJSON;
    }

    public function setGeoCodeJSON($json) {
        $this->geoCodeJSON = $json;
    }


    public function getAddressInfo($data) {
        $lat = 0;
        $lng = 0;
        $formatted = "";
        $city = "";
        $postalCode = "";

        if (isset($data["results"][0])) {
            $lat = $data["results"][0]["geometry"]["location"]["lat"];
            $lng = $data["results"][0]["geometry"]["location"]["lng"];
            $formatted = $data["results"][0]["formatted_address"];

            foreach ($data["results"][0]["address_components"] as $component) {
                if ($component["types"][0] == "locality") {
                    $city = $component["long_name"];
                }
                if ($component["types"][0] == "postal_code") {
                    $postalCode = $component["long_name"];
                }
            }
        }

        return [
            "lat" => $lat,
            "lng" => $lng,
            "formatted" => $formatted,
            "city" => $city,
            "postalcode" => $postalCode,
        ];

    }

}