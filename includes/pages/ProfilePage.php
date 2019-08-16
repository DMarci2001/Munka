<?php


class ProfilePage extends CorePage {

    public function __construct()
    {
        parent::__construct();

        $webText = $this->lang->webText;

        if (isset($_POST["adatmodositas"])) {
            if (isset($_POST["szuldatumev"])) {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            }

            $_POST["telefon"] = $this->utils->fixPhoneNumber($_POST["telefon"]);

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            if ($_POST["taj"] == "" && $_SESSION["helyszindata"]["tajnotreq"] == 0) $this->formError .= "{$webText["tajkotelezo"]}<br/>";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $this->formError .= "{$webText["tajformat"]}<br/>";
            if ($_POST["taj"] != "" && sql_fetch_array(sql_query("select taj from felhasznalok where taj=? and cegid=? and id<>?", array($_POST["taj"], $_SESSION["helyszindata"]["id"], $_SESSION["user"]["id"])))) $this->formError .= "{$webText["tajletezik"]}<br/>";

            //if ($_POST["email"]=="") $this->formError.="Az e-mail cím megadása kötelező!<br/>";
            //if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) && $_POST["email"]!="") $this->formError.="Az e-mail cím formátuma nem megfelelő!<br/>";
            if ($_POST["nev"] == "") $this->formError .= "{$webText["nevkotelezo"]}<br/>";
            if ($_POST["telefon"] == "") $this->formError .= "{$webText["telkotelezo"]}<br/>";
            if (!ctype_digit($_POST["telefon"]) && $_POST["telefon"] != "") $this->formError .= "{$this->formError["telformat"]}<br/>";
            if ($_POST["szuldatum"] == "" && $_SESSION["helyszindata"]["tajnotreq"] == 0) $this->formError .= "{$webText["szulkotelezo"]}<br/>";
            if (!$this->utils->validateDate($_POST["szuldatum"], "Y-m-d") && $_SESSION["helyszindata"]["tajnotreq"] == 0) $this->formError .= "{$webText["szulformat"]}<br/>";
            //if ($_POST["munkakor"]=="" && $_SESSION["helyszindata"]["tajnotreq"]==0) $this->formError.="A munkakör megadása kötelező!<br/>";
            if (!isset($_POST["neme"])) $this->formError .= "{$webText["nemekotelezo"]}<br/>";

            if ($_POST["jelszo"] != "") {
                if ($_POST["jelszo"] != $_POST["jelszo2"]) $this->formError .= "{$webText["ketjelszonem"]}<br/>";
                if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) < Booking_Settings::PASSWORD_LENGTH_MIN) $this->formError .= "{$webText["jelszomin"]}<br/>";
                if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) > Booking_Settings::PASSWORD_LENGTH_MAX) $this->formError .= "{$webText["jelszomax"]}<br/>";
            }

            if ($this->formError == "") {

                sql_query("update felhasznalok set 
                        nev=?,
                        telefon=?,
                        szuldatum=?,
                        szulhely=?,
                        anyjaneve=?,
                        neme=?,
                        taj=?,
                        irsz=?,
                        varos=?,
                        utca=?,
                        munkakor=?,
                        torzsszam=? 
                        where id=?"
                    , array(
                        $_POST["nev"],
                        $_POST["telefon"],
                        $_POST["szuldatum"],
                        $_POST["szulhely"],
                        $_POST["anyjaneve"],
                        $_POST["neme"],
                        $_POST["taj"],
                        $_POST["irsz"],
                        $_POST["varos"],
                        $_POST["utca"],
                        $_POST["munkakor"],
                        $_POST["torzsszam"],
                        $_SESSION["user"]["id"]
                    )
                );

                if ($_POST["jelszo"] != "") {
                    sql_query("update felhasznalok set jelszo=? where id=?", array(md5($_POST["jelszo"]), $_SESSION["user"]["id"]));
                }

                //ideiglenesen a funkció kiszedve
                if ($_POST["telefon"] != $_POST["oldtelefon"] and false) {
                    //megváltozott a telefon, új kódot küldünk és újravalidálunk.
                    $rn = rand(11000, 98000);
                    sql_query("update felhasznalok set validated=0,rkod='{$rn}'	where id='{$_SESSION["user"]["id"]}'");
                    sendUserSMSKod($_SESSION["user"]["id"]);
                    header("location:index.php");
                    die();
                }

                header("location:index.php?page=profile");
                die();

            }
        }
    }

    public function showPage() {
        $webText = $this->lang->webText;

        echo $this->displayFejlec($webText["adatmodositas"]);
        echo $this->showFormErrors();

        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<table style='font-size:12px;'>";

        echo "<tr><td width=100>{$webText["email"]}:</td><td>{$_SESSION["user"]["email"]}</td></tr>";
        echo "<tr><td colspan='2'><hr></td></tr>";

        echo "<tr><td width=100>{$webText["tajszam"]}:</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' onchange='clearIdopontValaszto();'  value='{$_SESSION["user"]["taj"]}'></td></tr>";
        echo "<tr><td width=100>{$webText["nev"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='nev' value='{$_SESSION["user"]["nev"]}'></td></tr>";
        echo "<tr><td width=100>{$webText["mobil"]}:</td><td><input type='hidden' name='oldtelefon' id='oldtelefon' value='{$_SESSION["user"]["telefon"]}' /><input class='inputbox' style='width:250px;' type='text' name='telefon' id='telefon' value='{$_SESSION["user"]["telefon"]}' placeholder='Formátum pl: 06301234567' ></td></tr>";
        echo "<tr><td width=100></td><td style='color:#888;'>{$webText["hamegvaltoztattel"]}</td></tr>";
        echo "<tr><td width=100>{$webText["anyjaneve"]}:</td><td><input class='inputbox' style='width:180px;' type='text' name='anyjaneve' value='{$_SESSION["user"]["anyjaneve"]}'></td></tr>";
        echo "<tr><td width=100>{$webText["szuletesihely"]}:</td><td><input class='inputbox' style='width:180px;' type='text' name='szulhely' value='{$_SESSION["user"]["szulhely"]}'></td></tr>";
        echo "<tr><td width=100>{$webText["szuletesidatum"]}:</td><td>".$this->utils->datumSelector($_SESSION["user"]["szuldatum"],"szuldatum")."</td></tr>";
        echo "<tr><td width=100>{$webText["neme"]}:</td><td><input type='radio' name='neme' value='1' ".($_SESSION["user"]["neme"]==1?"checked":"")."/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_SESSION["user"]["neme"]==2?"checked":"")."/> {$webText["no"]}</td></tr>";
        echo "<tr><td width=100>{$webText["irsz"]}:</td><td><input class='inputbox' style='width:60px;' maxlength='4' type='text' name='irsz' value='{$_SESSION["user"]["irsz"]}'></td></tr>";
        echo "<tr><td width=100>{$webText["varos"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='varos' value='{$_SESSION["user"]["varos"]}'></td></tr>";
        echo "<tr><td width=100>{$webText["utca"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='utca' value='{$_SESSION["user"]["utca"]}'></td></tr>";
        //echo "<tr><td width=100>Munkáltató:</td><td><input class='inputbox' style='width:250px;' type='text' name='munkaltato' value='{$_SESSION["user"]["munkaltato"]}'></td></tr>";
        echo "<tr><td width=100>{$webText["munkakor"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='munkakor' value='{$_SESSION["user"]["munkakor"]}'></td></tr>";
        echo "<tr><td width=100>{$webText["torzsszam"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='torzsszam' value='{$_SESSION["user"]["torzsszam"]}'></td></tr>";

        echo "<tr><td colspan='2'><hr></td></tr>";

        echo "<tr><td width=100>{$webText["jelszo"]}:</td><td><input style='width:200px;display:none;' type='text' autocomplete='off' name='dummyuser' value='' /><input style='width:200px;display:none;' type='password' autocomplete='off' name='dummypass' value='' /><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo' value=''/></td></tr>";
        echo "<tr><td width=100>{$webText["jelszoujra"]}:</td><td><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo2' value=''/></td></tr>";
        echo "<tr><td width=100></td><td style='color:#888;'>{$webText["toltsdki2jelszo"]}</td></tr>";

        echo "</table>";


        echo "<br><br><input type='submit' name='adatmodositas' value='{$webText["adatokmodositasa"]}' 
        onclick=\"
        if ($('#telefon').val()!=$('#oldtelefon').val()) {
            return confirm('{$webText["telmodq"]} '+$('#telefon').val()+'?');
        }
        \"/> ";


        echo "</form>";
    }
}
