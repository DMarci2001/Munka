<?php

class WebPageData {
    const DEFAULT_DATA_ID = 183;

    public $params = [
        "felirat1" => [
            "title" => "Alapadatok",
            "type" => "felirat"
        ],
        "tipusid" => [
            "title" => "Kapcsolódó típus",
            "type" => "tipuskapcs",
        ],
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
        "felirat2" => [
            "title" => "Fejléc feliratok",
            "type" => "felirat"
        ],
        "headerhero" => [
            "title" => "Hero image",
            "type" => "image",
            "imagetype" => DocAgent::ASSET_WEB_HERO
        ],
        "headersor2" => [
            "title" => "Vastag sor",
            "type" => "textbox",
            "placeholder" => "",
        ],
        "headersor3" => [
            "title" => "Harmadik sor (kicsit hosszabb szöveg)",
            "type" => "textbox",
            "placeholder" => "",
        ],
        "felirat3" => [
            "title" => "Főoldali tartalom elemek",
            "type" => "felirat"
        ],
        "mainpageblocks" => [
            "type" => "mainpageblocks",
        ]


    ];


    public function getOrokoltParam($parentId, $key, $paramData) {
        $value = "";
        if ($parentId != 0) {
            if ($data = sql_fetch_array(sql_query("select * from webpagedata where id=?", [$parentId]))) {
                $params = json_decode($data["params"], JSON_OBJECT_AS_ARRAY);
                if (isset($params[$key])) {
                    $value = $params[$key];
                } else {
                    $value = $this->getOrokoltParam($data["parent"], $key, $paramData);
                }
            }
        }

        return $value;
    }

    public function getImageParam($key, $tipus, $dataId):array {
        $paths = [];
        $docAgent = new DocAgent();
        if ($pageData = sql_fetch_array(sql_query_common("select * from webpagedata where id=?", [$dataId]))) {
            $params = json_decode($pageData["params"], JSON_OBJECT_AS_ARRAY);
            if (isset($params[$key])) {
                $images = sql_query("select * from dokumentumok where assetid=? and dataid=?", [$tipus, $dataId])->fetchAll(PDO::FETCH_ASSOC);
                //echo "select * from dokumentumok where assetid=? and dataid=?, [{$tipus}, {$dataId}]";
                foreach ($images as $imageData) {
                    $paths[] = $docAgent->getAssetImageURL($imageData) . "?v=" . date("YmdHis");
                }
            } else {
                if ($pageData["parent"] != 0) {
                    //echo $pageData["parent"]." ".$key." ".$tipus;
                    $paths = $this->getImageParam($key, $tipus, $pageData["parent"]);
                    //print_r($paths);die;
                }
            }
        }
        return $paths;
    }

}