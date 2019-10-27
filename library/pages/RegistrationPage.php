<?php


class RegistrationPage extends CorePage {

    public function __construct()
    {
        parent::__construct();

        $webText = $this->lang->webText;

        if (isset($_POST["regisztracio"])) {
            if (isset($_POST["szuldatumev"]) && $_POST["szuldatumev"] != "0") {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            } else {
                $_POST["szuldatum"] = "";
            }

            $_POST["telefon"] = $this->utils->fixPhoneNumber($_POST["telefon"]);

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            if (!isset($_POST["munkakor"])) $_POST["munkakor"] = "";
            if (!isset($_POST["torzsszam"])) $_POST["torzsszam"] = "";

            //if ($_POST["taj"] == "") $this->formError .= "{$webText["tajkotelezo"]}<br/>";
            if (!ctype_digit($_POST["taj"]) && !empty($_POST["taj"])) $this->formError .= "{$webText["tajformat"]}<br/>";
            if ($_POST["taj"] != "" && sql_fetch_array(sql_query("select taj from felhasznalok where taj=? and cegid=?", array($_POST["taj"], $_SESSION["helyszindata"]["id"])))) $this->formError .= "{$webText["tajletezik"]}<br/>";

            if ($_POST["email"] == "") $this->formError .= "{$webText["emailkotelezo"]}<br/>";
            if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) && $_POST["email"] != "") $this->formError .= "{$webText["emailformat"]}<br/>";
            if ($_POST["email"] != "" && sql_fetch_array(sql_query("select taj from felhasznalok where email=? and cegid=?", array($_POST["email"], $_SESSION["helyszindata"]["id"])))) $this->formError .= "{$webText["emailletezik"]}<br/>";

            if ($_POST["jelszo"] == "") $this->formError .= "{$webText["jelszokotelezo"]}<br/>";
            if ($_POST["jelszo"] != $_POST["jelszo2"]) $this->formError .= "{$webText["ketjelszonem"]}<br/>";
            if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) < Booking_Constants::PASSWORD_LENGTH_MIN) $this->formError .= "{$webText["jelszomin"]}<br/>";
            if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) > Booking_Constants::PASSWORD_LENGTH_MAX) $this->formError .= "{$webText["jelszomax"]}<br/>";
            if ($_POST["nev"] == "") $this->formError .= "{$webText["nevkotelezo"]}<br/>";
            if ($_POST["telefon"] == "") $this->formError .= "{$webText["telkotelezo"]}<br/>";
            if (!ctype_digit($_POST["telefon"]) && $_POST["telefon"] != "") $this->formError .= "{$webText["telformat"]}<br/>";
            if (!empty($_POST["szuldatum"]) && !$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) $this->formError .= "{$webText["szulformat"]}<br/>";

            if (!isset($_POST["neme"])) $_POST["neme"] = 0;

            if (!isset($_POST["aszf"])) $this->formError .= "{$webText["aszfkotelezo"]}<br/>";

            $this->formError.= $this->utils->checkCaptcha();

            if (empty($this->formError)) {
                $rn = rand(11000, 98000);

                sql_query("insert into felhasznalok set
                    cegid=?,
                    regtime=now(),
                    nev=?,
                    email=?,
                    jelszo=?,
                    telefon=?,
                    szuldatum=?,
                    neme=?,
                    taj=?,
                    irsz=?,
                    varos=?,
                    utca=?,
                    munkakor=?,
                    torzsszam=?,
                    rkod=?",
                    array(
		            $_SESSION["helyszindata"]["id"],
                    $_POST["nev"],
                    $_POST["email"],
                    md5($_POST["jelszo"]),
                    $_POST["telefon"],
                    $_POST["szuldatum"],
                    $_POST["neme"],
                    $_POST["taj"],
                    $_POST["irsz"],
                    $_POST["varos"],
                    $_POST["utca"],
                    $_POST["munkakor"],
                    $_POST["torzsszam"],
                    $rn
                    )
                );

                $_SESSION["loggeduser"] = sql_insert_id();
                $this->utils->sendUserSMSKod($_SESSION["loggeduser"]);

                header("location:index.php");
                die();
            }
        }
    }

    public function showPage() {
        $webText = $this->lang->webText;

        if (!isset($_POST["email"])) {
            $_POST["email"]=$_POST["nev"]=$_POST["telefon"]=$_POST["szuldatum"]=$_POST["taj"]=$_POST["irsz"]=$_POST["varos"]=$_POST["utca"]=$_POST["munkaltato"]=$_POST["munkakor"]=$_POST["torzsszam"]=$_POST["jelszo"]=$_POST["jelszo2"]=$_POST["captcha"]="";
        }
        if (!isset($_POST["neme"])) $_POST["neme"]="";

        echo $this->displayFejlec($webText["regisztracio"]);
        echo $this->showFormErrors();

        echo $this->showPageDescription($this->lang->getText("page.reg.description","Regisztráljon, hogy kényelmesebben foglalhasson időpontot, valamint kiegészítő szolgáltatásokat érhessen el. A regisztráció után nem kell minden foglalásnál kitöltenie az adatait, megtekintheti előző foglalásait, megnézheti a leleteit, és az egyéb vizsgálatokkal kapcsolatos dokumentumokat.<br/>Adja meg az adatait az alábbi form kitöltésével."));

        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<input type='hidden' name='regisztracio' value='1'/>";
        echo "<table>";
        echo "<tr><td width='100'>{$webText["email"]}: *</td><td><input class='inputbox' autocomplete='off' style='width:250px;' type='text' name='email' value='{$_POST["email"]}'></td></tr>";
        echo "<tr><td>{$webText["jelszo"]}: *</td><td><input style='display:none;' type='text' autocomplete='off' name='dummyname' value=''><input style='display:none;' type='password' autocomplete='off' name='dummypass' value=''> <input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo' value='{$_POST["jelszo"]}'></td></tr>";
        echo "<tr><td>{$webText["jelszoujra"]}: *</td><td><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo2' value='{$_POST["jelszo2"]}'></td></tr>";
        echo "<tr><td colspan='2'><div style='border-top:1px solid #ccc;padding-top:10px;margin-top:10px;'></div></td></tr>";
        echo "<tr><td>{$webText["tajszam"]}:</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' value='{$_POST["taj"]}'></td></tr>";
        echo "<tr><td>{$webText["nev"]}: *</td><td><input class='inputbox' style='width:270px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
        echo "<tr><td>{$webText["mobil"]}: *</td><td><input class='inputbox' style='width:270px;' type='text' name='telefon' value='{$_POST["telefon"]}' placeholder='{$webText["mobilformat"]}' ></td></tr>";
        echo "<tr><td></td><td style='color:#888;'>{$webText["mobiltip"]}</td></tr>";
        echo "<tr><td>{$webText["szuletesidatum"]}:</td><td>".$this->utils->datumSelector($_POST["szuldatum"],"szuldatum")." {$_POST["szuldatum"]}</td></tr>";
        echo "<tr><td>{$webText["neme"]}:</td><td><input type='radio' name='neme' value='1' ".($_POST["neme"]==1?"checked":"")."/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_POST["neme"]==2?"checked":"")."/> {$webText["no"]}</td></tr>";
        echo "<tr><td>{$webText["irsz"]}:</td><td><input class='inputbox' style='width:60px;' maxlength='4' type='text' name='irsz' value='{$_POST["irsz"]}'></td></tr>";
        echo "<tr><td>{$webText["varos"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='varos' value='{$_POST["varos"]}'></td></tr>";
        echo "<tr><td>{$webText["utca"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='utca' value='{$_POST["utca"]}'></td></tr>";
        echo "<tr><td></td><td><div style='margin-top:5px;' class='g-recaptcha' data-sitekey='6LfCaTIUAAAAAPRgI2ymhP9u8OJKc5DJSmCb9cjG'></div></td></tr>";
        echo "<tr><td><td><div style='margin-top:10px;'><input type='checkbox' name='aszf' value='1' ".(isset($_POST["aszf"])?"checked":"")."/> {$webText["aszfelf"]}</div></td></tr>";
        echo "<tr><td></td><td><br/><a href='#' class='newbutton' onclick='document.iform.submit();return false;'>{$webText["regisztracio"]}</a></td></tr>";
        echo "</table>";
        echo "</form>";
    }
}

