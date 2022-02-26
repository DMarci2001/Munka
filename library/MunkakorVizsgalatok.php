<?php

class MunkakorVizsgalatok {

    public $vizsgalatok = [
        "keltexmed" => [
            "futár" => "Labor és pszichológia",
            "éttermi dolgozó" => "Labor"
        ]
    ];


    public function getMunkakorVizsgalat($munkakor):string {
        $result = "";

        if (isset($this->vizsgalatok[Booking_Constants::SQL_DB])) {
            $vizsgalatok =  $this->vizsgalatok[Booking_Constants::SQL_DB];
            $munkakor = trim(strtolower($munkakor));

            if (isset($vizsgalatok[$munkakor])) {
                $result = $vizsgalatok[$munkakor];
            }
        }

        return $result;
    }

}