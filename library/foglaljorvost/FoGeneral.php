<?php /** @noinspection PhpComposerExtensionStubsInspection */

class FoGeneral {

    protected function description($reservationData) {
        $description = "";

        if (trim($reservationData["nev"]) != "") {
            $description.= "név: {$reservationData["nev"]}\n";
        }

        if (trim($reservationData["telefon"]) != "") {
            $description.= "telefon: {$reservationData["telefon"]}\n";
        }

        if (trim($reservationData["megj"]) != "") {
            $description.= "megjegyzés: {$reservationData["megj"]}\n";
        }

        $description = str_replace("&", "&amp;", $description);
        $description = str_replace("\"", "'", $description);
        $description = strip_tags($description);
        $description = str_replace("<", "", $description);
        $description = str_replace(">", "", $description);
        return trim($description);
    }

    protected function getApiURL() {
        $url = static::API_URL;
        if (Booking_Constants::IS_DEMO) {
            $url = static::API_TEST_URL;
        }
        return $url;
    }

    protected function getApiPassword() {
        $password = Booking_Constants::FO_API_PASSWORD;
        if (Booking_Constants::IS_DEMO) {
            $password = Booking_Constants::FO_API_TEST_PASSWORD;
        }
        return $password;
    }

    protected function generateRotateHash() {
        return md5(sha1("fo|".$this->getApiPassword()."|".date("Y.m.d"."$")));
    }

    protected function getBeosztasData($beoId) {
        $res = sql_query("select b.*, o.foid from orvos_beosztas_new b left join orvosok o on o.id = b.orvosid where b.id=?", [$beoId]);
        if (!$beo = sql_fetch_array($res)) {
            $beo["error"] = "Beosztás nem található!";
            return $beo;
        }

        $tipusok = array_values(array_filter(array_unique(explode("|", $beo["tipusok"]))));
        foreach ($tipusok as $tipus) {
            if ($szurestipusData = sql_fetch_array(sql_query("select * from szurestipusok where id=?", [$tipus]))) {
                if ($szurestipusData["fotid"] == 0) {
                    $beo["error"] = "error: {$szurestipusData["megnev"]} tipus nincs a foglaljOrvos-al szinkronizálva!";
                }

                $beo["fotid"] = $szurestipusData["fotid"];
            }
        }

        $beo["week"] = 1;
        $beo["startTime"] = date("Y-m-d");
        if ($beo["nap"] == 1) $beo["startTime"] = date("Y-m-d", strtotime("this week monday"));
        if ($beo["nap"] == 2) $beo["startTime"] = date("Y-m-d", strtotime("this week tuesday"));
        if ($beo["nap"] == 3) $beo["startTime"] = date("Y-m-d", strtotime("this week wednesday"));
        if ($beo["nap"] == 4) $beo["startTime"] = date("Y-m-d", strtotime("this week thursday"));
        if ($beo["nap"] == 5) $beo["startTime"] = date("Y-m-d", strtotime("this week friday"));
        if ($beo["nap"] == 6) $beo["startTime"] = date("Y-m-d", strtotime("this week saturday"));
        if ($beo["nap"] == 7) $beo["startTime"] = date("Y-m-d", strtotime("this week sunday"));
        $beo["startDate"] = $beo["startTime"];
        $beo["endTime"] = $beo["startTime"];
        $beo["startTime"].=" ".$beo["tol"].":00";
        $beo["endTime"].=" ".$beo["ig"].":00";

        if ($beo["nap"] == 10) {
            $beo["week"] = 0;
            $beo["startDate"] = $beo["beonap"];
            $beo["startTime"] = $beo["beonap"]." ".$beo["tol"].":00";
            $beo["endTime"] = $beo["beonap"]." ".$beo["ig"].":00";
        }
        return $beo;
    }

    protected function getReservationStatus($reservationData) {
        //STATUS: Az előjegyzés státusza. Kötelező. A státuszjelzők a következők lehetnek:
        //“E”: sima előjegyzés, foglalás, egyéb elfoglaltság, szabadság
        //“J”: jelen, megjelent. A beteg megjelent a klinikán.
        //“N”: nem jött, nem jelent meg előzetes lemondás nélkül.
        //“L”: lemondta. A beteg lemondta az előjegyzését.

        $status = "E";
        if ($reservationData["eljott"] == 1) {
            $status = "J";
        }
        return $status;
    }

    public function syncAllFieldsAndServices($fieldsData) {
        $xml = simplexml_load_string($fieldsData);

        foreach ($xml->FIELDS->FIELD as $field) {
            if (!empty($field["NAME"])) {
                //echo $field["OUTERSYS_ID"] . " " . $field["NAME"] . "\n";
                sql_query("update szurestipusok set fotid=? where megnev=? and megnev<>'' and fotid=0", [$field["OUTERSYS_ID"], $field["NAME"]]);
                sql_query("insert ignore into remoteids set provider=?, tipus='field', remoteid=?, megnev=?", [static::PROVIDER_NAME, $field["OUTERSYS_ID"], $field["NAME"]]);

                if (isset($field->SERVICES->SERVICE)) {
                    foreach ($field->SERVICES->SERVICE as $service) {
                        //todo altipusokkal mi legyen?
                        //echo "   " . $service["OUTERSYS_ID"] . " " . $service["NAME"] . "\n";
                        sql_query("insert ignore into remoteids set provider=?, tipus='service', remoteid=?, parentremoteid=?, megnev=?", [static::PROVIDER_NAME, $service["OUTERSYS_ID"], $field["OUTERSYS_ID"], $service["NAME"]]);
                    }
                }
            }
        }
    }



}