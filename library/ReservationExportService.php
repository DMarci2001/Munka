<?php

class ReservationExportService {

    private $utils;

    const AUDI_API_URL = "https://audi.hungariamed.hu/api/index.php";
    const AUDI_TOKEN   = "cmdlZ2VnOmVnZWdlZ2VnZWdl";

    public function __construct()
    {
        $this->utils = new Utils();
    }


    public function exportReservation($id) {
        $data["error"] = "";
        if ($reservationData = sql_fetch_array(sql_query("select * from foglalasok where id=?", [$id]))) {

            if (!empty($reservationData["exportdata"])) {

                $exportData = json_decode($reservationData["exportdata"]);
                if (isset($exportData->target) && isset($exportData->targetorvos) && isset($exportData->targetszurestipus)) {

                     $data["exportdata"] = $exportData;

                     if ($exportData->target == "audi") {
                         $this->sendToAudi($data);

                         $exportData->exportstatus = "ok";
                         sql_query("update foglalasok set exportdata=? where id=?", [json_encode($exportData), $id]);
                     }

                } else {
                    $data["error"] = "Exportdata mezők hiányosak";
                }
            } else {
                $data["error"] = "Exportdata üres";
            }
        } else {
            $data["error"] = "Foglalás nem található";
        }

    }


    private function sendToAudi($data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::AUDI_API_URL."?action=exportfoglalas");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf8", "Signature: ".self::AUDI_TOKEN]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 240);

        $result = curl_exec($ch);
        $return['httpCode'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $return['response'] = json_decode($result, true);

        print_r($return);die;

        if ($return["httpCode"] == 200 && isset($return["error"]) && $return["error"] == "") {
            return true;
        } else {
            return false;
        }
    }




}