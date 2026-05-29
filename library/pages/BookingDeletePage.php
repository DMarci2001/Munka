<?php

class BookingDeletePage extends CorePage {
    private $bookingService;

    private $reservationId;
    private $authCode;
    private $reservationData;

    public function __construct()
    {
        parent::__construct();

        $this->checkParameters();

        $this->bookingService = new BookingService();

        if(isset($_POST["submitReservationDelete"])){
            $captchaResult = $this->utils->checkCaptcha();
            if (!empty($captchaResult)) {
                $this->errors[] = $captchaResult;
            }
            if(empty($this->reservationData)) $this->errors[] = "Ez az időpont foglalás nem létezik, vagy időközben törölve lett.";

            if(empty($this->errors)){
                $service = new NotificationService();
                $service->deleteUserMessage($this->reservationId);

                $GLOBALS["extraloginfo"] = "felhasználó törölte a levél linkre kattintva";
                $this->bookingService->deleteReservation($this->reservationId, $this->authCode);
                header("location:index.php?page=bookingdeletesuccessful");
                die();
            }
        }

        if (!isset($_GET["id"]) || !isset($_GET["rk"])) {
            die("error");
        }

        if (isset($_GET["dodeletereservation"])) {
            echo "Foglalás törlés ideiglenesen letiltva.. Kérjük foglalás lemondás ügyben hívja az ügyfélszolgálatot!";
            die;

            $service = new NotificationService();
            $service->deleteUserMessage($_GET["id"]);

            $GLOBALS["extraloginfo"] = "felhasználó törölte a levél linkre kattintva";
            $this->bookingService->deleteReservation($_GET["id"], $_GET["rk"]);
            header("location:index.php?page=bookingdeletesuccessful");
            die();
        }
    }

    public function showPage() {
        $webText = $this->lang->webText;
        $html = "";

        echo $this->displayFejlec();
        echo $this->utils->showErrors($this->errors);

        if(!empty($this->reservationData)){
            $html = $this->deleteReservationForm();
        }else{
            $html = $this->undefinedReservation();
        }

        echo $html;
    }

    private function _oldDeleteCode(){
        $webText = $this->lang->webText;
         if ($row = sql_fetch_array(sql_query("SELECT ".$this->utils->cimLangQuery("helyszin").",sz.megnev AS szurestipus,f.* FROM foglalasok f
        LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
        LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
        WHERE f.id=? and (f.rkod=? or f.pass=?)",array($id, $rk, $rk)))) {
            $row = $this->aldiTimeOverride($row);

            if($row["datum"]=="1900-01-01 00:00:01"){
                $idopont = "Egyeztetés alatt";
            }else{
                $idopont = substr($row["datum"],0,16);
            }

            echo "{$webText["kedves"]} {$row["nev"]}!<br>
            <br>
            {$webText["torleskezd"]}:<br/>
            <br/>
            
            {$webText["nev"]}: {$row["nev"]}<br>
            {$webText["telefon"]}: {$row["telefon"]}<br>
            <b>{$webText["idopont"]}: {$idopont}</b><br>
            {$webText["szurestipus"]}: {$row["szurestipus"]}<br>
            {$webText["helyszin"]}: {$row["helyszin"]}<br>
            <br/>
            
            <a class='simabuttonpiros' href='index.php?page={$_GET["page"]}&id={$_GET["id"]}&rk={$_GET["rk"]}&dodeletereservation'>{$webText["torlesmegerositese"]}</a><br/>
            
            <br/>
            
            <a href='/'>{$webText["visszafooldal"]}</a>";
        } else {
            echo "Sajnáljuk!<br>
            Ez az időpont foglalás nem létezik, vagy időközben törölve lett.<br>
            <br>
            
            <a href='/'>{$webText["visszafooldal"]}</a>";
        }
    }


    private function aldiTimeOverride($data) {
        if (Booking_Constants::SQL_DB == "hungariamed" && CompanyService::isALDI() && $data["szurestipusid"] == Booking_Constants::TUDOSZURES_ID) {
            $jaratok = ["08:30", "09:30", "10:30", "11:30", "12:30", "13:30", "14:30"];
            $datum = date("Y-m-d", strtotime($data["datum"]));
            $actualJarat = $jaratok[0];

            foreach ($jaratok as $jarat) {
                if (strtotime("{$datum} {$jarat}:00") <= strtotime($data["datum"])) {
                    $actualJarat = "{$datum} {$jarat}:00";
                }
            }
            $data["datum"] = $actualJarat;
        }

        return $data;
    }

    private function checkParameters(){
        if(isset($_GET["id"]) && isset($_GET["rk"])){
            $q = "SELECT ".$this->utils->cimLangQuery("helyszin").",sz.megnev AS szurestipus,f.* FROM foglalasok f
                  LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
                  LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
                  WHERE f.id=? and (f.rkod=? or f.pass=?)";

            if($reservationData = sql_query($q,[$_GET["id"],$_GET["rk"],$_GET["rk"]])->fetch(PDO::FETCH_ASSOC)){
                $this->reservationId = $_GET["id"];
                $this->authCode =      $_GET["rk"];
                $this->reservationData = $reservationData;

                return true;
            }
        }
        
        return false;
    }

    private function deleteReservationForm(){

        if($this->reservationData["datum"]=="1900-01-01 00:00:01"){
            $idopont = "Egyeztetés alatt";
        }else{
            $idopont = substr($this->reservationData["datum"],0,16);
        }

        $html = "";
        $html.= "<form method='POST' name='reservationDeleteForm' id='reservationDeleteForm'>";
        $html.= "<p>Amennyiben törölni szeretnéd a foglalásod, kattints a <strong>Foglalás törlése“</strong> gombra.</p><br>";

        $html.= "<p><strong>{$this->lang->webText["szurestipus"]}:</strong> {$this->reservationData["szurestipus"]}<br>";
        $html.= "<strong>{$this->lang->webText["idopont"]}:</strong> {$idopont}</p>";

        $html.= "<div class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG' style='width:200px;'></div>";
        
        $html.= "<button type=\"submit\" class=\"btn btn-lg forced-font m-2 delete-reservation-button\" id=\"submitReservationDelete\" name=\"submitReservationDelete\" value=\"1\">Foglalás törlése</button>";
        $html.= "</form>";
        $html.= "<br><br><a href='/'>{$this->lang->webText["visszafooldal"]}</a>";
        return $html;
    }

    private function undefinedReservation(){
        $html = "";
        $html.= "Sajnáljuk!<br>";
        $html.= "Ez az időpont foglalás nem létezik, vagy időközben törölve lett.<br>";
        $html.= "<br>";
        $html.= "<a href='/'>{$this->lang->webText["visszafooldal"]}</a>";
        return $html;
    }
}

