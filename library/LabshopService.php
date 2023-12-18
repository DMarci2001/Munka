<?php

class LabshopService{

    const PRODUCT_EXAM = "exam";
    const PRODUCT_ITEM = "item";
    const PRODUCT_PACKAGE = "package";

    public array $typeNames = [
        self::PRODUCT_EXAM => "vizsgálat",
        self::PRODUCT_ITEM => "labor vizsgálat",
        self::PRODUCT_PACKAGE => "labor csomag",
    ];

    public function __construct()
    {

    }

    public function getProductData($type, $id = 0, $cegid): array
    {
        if ($id == 0) {
            //type konvertálása type és id-re... pl ha a type = "exam1234"
            foreach ($this->typeNames as $pType => $name) {
                if (substr_count($type, $pType)) {
                    $id = str_replace($pType, "", $type);
                    $type = $pType;
                    break;
                }
            }
        }

        if ($type == self::PRODUCT_PACKAGE) {
            $product = sql_fetch_array(sql_query("SELECT *,48 as tipusid FROM synlab_labor_csomagok WHERE id=?", [$id]));

        }

        if ($type == self::PRODUCT_ITEM) {
            $product = sql_fetch_array(sql_query("SELECT *,48 as tipusid FROM synlab_labor_tetelek WHERE id=?", [$id]));
            if (in_array($cegid, [688, 691, 4, 766, 767, 768, 702, 703,718]) && $product["id"] == 2) {
                $product["price"] = 0;
            }
        }

        if ($type == self::PRODUCT_EXAM) {
            $product = sql_fetch_array(sql_query("SELECT megnev as name,price,id,tipusid FROM arak WHERE id=?", [$id]));
        }
        
        $product["typeName"] = $this->typeNames[$type];

        return $product;
    }

}