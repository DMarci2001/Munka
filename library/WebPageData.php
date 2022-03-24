<?php

class WebPageData {
    public $params = [
        "title" => [
            "title" => "A weboldal címe",
            "type" => "textbox",
            "placeholder" => "Hagy üresen, ha a domaint akarod címnek.",
        ],
        "telefon" => [
            "title" => "Telefonszám",
            "type" => "textbox",
            "placeholder" => "",
        ],
        "cim" => [
            "title" => "Cím",
            "type" => "textbox",
            "placeholder" => "",
        ],
        "adoszam" => [
            "title" => "Adószám",
            "type" => "textbox",
            "placeholder" => "",
        ],
        "cikktags" => [
            "title" => "Cikk tag-ek",
            "type" => "textbox",
            "placeholder" => "",
        ],
        "menupont_arak" => [
            "title" => "Árak menüpont",
            "type" => "checkbox",
        ],
        "menupont_egeszsegpenztarak" => [
            "title" => "Egészségpénztárak menüpont",
            "type" => "checkbox",
        ],
        "menupont_cikkek" => [
            "title" => "Cikkek menüpont",
            "type" => "checkbox",
        ],
        "mainpageblocks" => [
            "title" => "Főoldali tartalom elemek",
            "type" => "mainpageblocks",
        ]


    ];


    public function getOrokoltParam($parentId, $key) {
        $value = "";
        if ($parentId != 0) {
            if ($data = sql_fetch_array(sql_query("select * from webpagedata where id=?", [$parentId]))) {
                $params = json_decode($data["params"], JSON_OBJECT_AS_ARRAY);
                if (isset($params[$key])) {
                    $value = $params[$key];
                } else {
                    $value = $this->getOrokoltParam($data["parent"], $key);
                }
            }
        }

        return $value;
    }


}