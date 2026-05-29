<?php
class GeneraliApiService
{

    const API_TEST_URL  = "https://release.medifoglalo.hu/api/obt";
    const Username      = "marton.gergely@hungariamed.hu";
    const Password      = "HrfDqh2m8mNqPKm";

    private $apiURL;
    private $token;

    function __construct()
    {
        $this->apiURL = self::API_TEST_URL;

        if (!isset($_SESSION["generalitoken"])) {
            $this->token = $this->loginTry();
            if (!empty($this->token)) {
                $_SESSION["generalitoken"] = $this->token;
            }
        } else {
            $this->token = $_SESSION["generalitoken"];
        }
    }

    public function loginTry()
    {
        $action = "/login";
        $body = json_encode(['username' => self::Username, 'password' => self::Password]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL . $action);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        //$this->log($action, $params, $response);

        curl_close($ch);


        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function retrieveSpecialities()
    {
        $action = "/specialities";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function storeSpecialities($ownId, $generaliId)
    {
        $action = "/specialities";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $body = [
            "id" => $generaliId,
            "partner_speciality_id" => $ownId,
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function destroySpeciality($ownId)
    {
        $action = "/specialities/{$ownId}";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => $header
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    /**
     * Specialitáshoz tartozó sub vizsgálatok lekérdezése.
     */
    public function retrieveExaminationsOfSpeciality($ownId)
    {
        $action = "/specialities/{$ownId}/examinations";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    /**
     * Összes sub vizsgálat lekérdezése.
     */
    public function retrieveExaminations()
    {
        $action = "/examinations";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function storeExamination($ownId, $generaliId)
    {
        $action = "/examinations";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $body = [
            "id" => $generaliId,
            "partner_examination_id" => $ownId,
        ];


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function destroyExamination($ownId)
    {
        $action = "/examinations/{$ownId}";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => $header
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function retrieveCareSpots()
    {
        $action = "/care-spots";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    //Ezt még tesztelni kell mert a teszt környezetben hibás
    public function storeCareSpot($ownId, $name, $note, $fullAddress, $longitude, $latitude)
    {
        $action = "/care-spots";

        $header = [
            'Content-Type: application/json',
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $body = json_encode(array(
            "partner_care_spot_id" => $ownId,
            "name" => $name,
            "note" => $note,
            "full_address" => "",
            "longitude" => $longitude,
            "latitude" => $latitude
        ));

        $body = '{"partner_care_spot_id":"1","name":"Győri úti rendelő","longitude":19.0265776,"latitude":47.4906339,"full_address":"1123 Budapest, Győri út 20"}';

        

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>$body,
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function retrieveDoctors()
    {
        $action = "/doctors";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header,
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }
    
    public function storeDoctor($ownId="",$name="",$titles="",$minAge="18",$languages=array("Hungarian"))
    {
        $action = "/doctors";

        $header = [
            'Content-Type: application/json',
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $body = json_encode(array(
            "partner_doctor_id"=>$ownId,
            "name"=>$name,
            "titles"=>$titles,
            "min_age"=>$minAge,
            "languages"=> $languages
        ));

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>$body,
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function updateDoctor($ownId="",$name="",$titles="",$minAge="18",$languages=array("Hungarian")){
        $action = "/doctors/{$ownId}";

        $header = [
            'Content-Type: application/json',
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $body = json_encode(array(
            "name"=>$name,
            "titles"=>$titles,
            "min_age"=>$minAge,
            "languages"=> $languages
        ));

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS =>$body,
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function destroyDoctor($ownId){
        $action = "/doctors/{$ownId}";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function retrieveCareSpotsOfDoctor($ownId){
        $action = "/doctors/{$ownId}/care-spots";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function attachCareSpotToDoctor($doctorId,$careSpotId){
        $action = "/doctors/{$doctorId}/care-spots";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $body = [
            "partner_care_spot_id" => $careSpotId,
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>$body,
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function detachCareSpotFromDoctor($doctorId,$careSpotId){
        $action = "/doctors/{$doctorId}/care-spots/{$careSpotId}";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function retrieveExaminationsOfCareSpotOfDoctor($doctorId,$careSpotId){
        $action = "/doctors/{$doctorId}/care-spots/{$careSpotId}/examinations";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function attachExaminationToCareSpotOfDoctor($doctorId,$careSpotId,$examinationId,$totalPrice,$publicPrice){
        $action = "/doctors/{$doctorId}/care-spots/{$careSpotId}/examinations";

        $header = [
            "Content-Type: application/x-www-form-urlencoded",
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $body = http_build_query(array(
           "partner_examination_id"=>$examinationId,
           "price_ea"=>$totalPrice,
           "price_public"=>$publicPrice,
        ));

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>$body,
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function detachExaminationFromCareSpotOfDoctor($doctorId,$careSpotId,$examinationId){
        $action = "/doctors/{$doctorId}/care-spots/{$careSpotId}/examinations/{$examinationId}";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    public function retrieveBookings(){
        $action = "/bookings";

        $header = [
            "Authorization: " . $this->token["token_type"] . " " . $this->token["access_token"],
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiURL . $action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $header,
          ));

        $response = curl_exec($curl);
        curl_close($curl);

        if (!is_array(json_decode($response, JSON_OBJECT_AS_ARRAY))) {
            return $response;
        }

        return $response = json_decode($response, JSON_OBJECT_AS_ARRAY);
    }

    /**
     * Itt mit fognak lekérdezni? Gondolom én az orvoshoz tartozó rendelésheket O.o... helyszín szerint vagy orvos szerint?
     * a mymedio helyszín vizsgálat alapján is dob már eredményt, gondolom akkor énis hasonlóképpen építem fel a lekérdezést O.o
     * esetleg plusz "optional" filterként bele rakom az orvost is. és itt a lényeg, az olyan orvosokat listázom csak akiknek van már generaliID-ja gondolom én.
     * 
    */
}
