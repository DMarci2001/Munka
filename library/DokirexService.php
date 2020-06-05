<?php

class DokirexService {
	
	const LoginEmail    = "ugyfelkapcsolat@hungariamed.hu";
	const LoginPassword = "HMMadmin12345.";
	const dbName		= "hungaria";
	
	
	private $testing = true;
    private $bookingService;
	private $token;
	
	private $defaultParams = array("Nem"=>3,"Allampolgarsag"=>109);
	
	public function __construct() {
        $this->bookingService = new BookingService();
		$this->token = $this->getToken();
    }
	
	public function insertPaciensIntoDokirex($params=array()) {
		
		//Ellenőrzés, hogy a páciens adat tömb nem üres-e, ha igen akkor hagyja félbe a folyamatot.
		if (empty($params)) {
			exit;
		}
		
		//További adatok a service-ből:
		$params["token"]  = $this->token;
		$params["dbName"] = self::dbName;
		
		//Alapértelmezett adatok beillesztése a paraméterekbe, ha nem lettek volna deklarálva.
		foreach($this->defaultParams as $index => $value) {
			if (!isset($params[$index]) || ($params[$index]=="" && $params[$index]==null)) {
				$params[$index] = $value;
			}
		}
		
		$curl = curl_init();

		curl_setopt_array($curl, array(
		CURLOPT_URL => "api.dokirex.hu/insertUpdatePaciens",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => $params,
		));

		$response = curl_exec($curl);

		curl_close($curl);
		
		$response = json_decode($response,true);
		
		if($response["message"]=="OK") {
			return "Sikeres adatküldés! ({$response["data"]})";
		} else return $response;
		
		exit;
	}
	
	public function test_run() {
		
		$params = array("token"=>$this->token,
						"Nev"=>"Márton Gergely",
						"SzuletesiDatum"=>"1994-09-23",
						"Azonosito"=>"0123456789234",
						"Nem"=>"3",
						"Iranyitoszam"=>"2162",
						"Telepules"=>"Őrbottyán",
						"Cim"=>"Puskás Ferenc u. 74",
						"SzuletesiNev"=>"Márton Gergely",
						"Allampolgarsag"=>"109",
						"Telefon"=>"0630606922",
						"Mobiltelefon"=>"0630606922",
						"dbName"=>self::dbName
						);
		
		$curl = curl_init();

		curl_setopt_array($curl, array(
		CURLOPT_URL => "api.dokirex.hu/insertUpdatePaciens",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => $params,
		));

		$response = curl_exec($curl);

		curl_close($curl);
		
		$response = json_decode($response,true);
		
		echo "<pre>";
		print_r($response);
		echo "</pre>";
		
	}
	
	private function getToken() {
		
		$curl = curl_init();

		curl_setopt_array($curl, array(
		CURLOPT_URL => "api.dokirex.hu/login",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => array('Email' => self::LoginEmail,'Password' => self::LoginPassword),
		));

		$response = curl_exec($curl);

		curl_close($curl);
		
		$response = json_decode($response,true);
		
		if ($response["status"] == 1 && $response["message"] == "OK") {
			return $response["data"]["token"];
		}
	}
}

?>