<?php


class ProfilePage extends CorePage {

    public function __construct()
    {
        parent::__construct();

        $webText = $this->lang->webText;

        if (isset($_POST["adatmodositas"])) {
            if (isset($_POST["szuldatumev"]) && $_POST["szuldatumev"] != "0") {
                $_POST["szuldatum"] = $_POST["szuldatumev"] . "-" . substr("00" . $_POST["szuldatumho"], -2) . "-" . substr("00" . $_POST["szuldatumnap"], -2);
            } else {
                $_POST["szuldatum"] = "";
            }

            $_POST["telefon"] = $this->utils->fixPhoneNumber($_POST["telefon"]);

            $_POST["taj"] = str_replace("-", "", $_POST["taj"]);
            $_POST["taj"] = trim(str_replace(" ", "", $_POST["taj"]));

            if ($_POST["taj"] == "") $this->formError .= "{$webText["tajkotelezo"]}<br/>";
            if (!ctype_digit($_POST["taj"]) && $_POST["taj"] != "") $this->formError .= "{$webText["tajformat"]}<br/>";
            if ($_POST["taj"] != "" && sql_fetch_array(sql_query("select taj from felhasznalok where taj=? and cegid=? and id<>?", array($_POST["taj"], $_SESSION["helyszindata"]["id"], $_SESSION["user"]["id"])))) $this->formError .= "{$webText["tajletezik"]}<br/>";

            if ($_POST["nev"] == "") $this->formError .= "{$webText["nevkotelezo"]}<br/>";
            if ($_POST["telefon"] == "") $this->formError .= "{$webText["telkotelezo"]}<br/>";
            if (!ctype_digit($_POST["telefon"]) && $_POST["telefon"] != "") $this->formError .= "{$this->formError["telformat"]}<br/>";
            if (!empty($_POST["szuldatum"]) && !$this->utils->validateDate($_POST["szuldatum"], "Y-m-d")) $this->formError .= "{$webText["szulformat"]}<br/>";

            if ($_POST["jelszo"] != "") {
                if ($_POST["jelszo"] != $_POST["jelszo2"]) $this->formError .= "{$webText["ketjelszonem"]}<br/>";
                if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) < Booking_Constants::PASSWORD_LENGTH_MIN) $this->formError .= "{$webText["jelszomin"]}<br/>";
                if ($_POST["jelszo"] != "" && strlen($_POST["jelszo"]) > Booking_Constants::PASSWORD_LENGTH_MAX) $this->formError .= "{$webText["jelszomax"]}<br/>";
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

        if (!isset($_POST["nev"])) {
            $_POST = $_SESSION["user"];
        }
        if (!isset($_POST["neme"])) {
            $_POST["neme"] = 0;
        }


        echo "<form name='iform' method='post' enctype='multipart/form-data'>";
        echo "<table>";
        echo "<tr><td width='140'>{$webText["email"]}:</td><td>{$_SESSION["user"]["email"]}</td></tr>";
        echo "<tr><td colspan='2'><hr></td></tr>";
        echo "<tr><td>{$webText["tajszam"]}:</td><td><input class='inputbox' style='width:120px;' type='text' id='tajszam' name='taj' onchange='clearIdopontValaszto();'  value='{$_POST["taj"]}'></td></tr>";
        echo "<tr><td>{$webText["nev"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='nev' value='{$_POST["nev"]}'></td></tr>";
        echo "<tr><td>{$webText["mobil"]}:</td><td><input type='hidden' name='oldtelefon' id='oldtelefon' value='{$_POST["telefon"]}' /><input class='inputbox' style='width:250px;' type='text' name='telefon' id='telefon' value='{$_POST["telefon"]}' placeholder='Formátum pl: 06301234567' ></td></tr>";
        echo "<tr><td></td><td style='color:#888;'>{$webText["hamegvaltoztattel"]}</td></tr>";
        echo "<tr><td>{$webText["anyjaneve"]}:</td><td><input class='inputbox' style='width:180px;' type='text' name='anyjaneve' value='{$_POST["anyjaneve"]}'></td></tr>";
        echo "<tr><td>{$webText["szuletesihely"]}:</td><td><input class='inputbox' style='width:180px;' type='text' name='szulhely' value='{$_POST["szulhely"]}'></td></tr>";
        echo "<tr><td>{$webText["szuletesidatum"]}:</td><td>".$this->utils->datumSelector($_POST["szuldatum"],"szuldatum")."</td></tr>";
        echo "<tr><td>{$webText["neme"]}:</td><td><input type='radio' name='neme' value='1' ".($_POST["neme"]==1?"checked":"")."/> {$webText["ferfi"]}&nbsp;&nbsp;&nbsp;<input type='radio' name='neme' value='2' ".($_POST["neme"]==2?"checked":"")."/> {$webText["no"]}</td></tr>";
        echo "<tr><td>{$webText["irsz"]}:</td><td><input class='inputbox' style='width:60px;' maxlength='4' type='text' name='irsz' value='{$_POST["irsz"]}'></td></tr>";
        echo "<tr><td>{$webText["varos"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='varos' value='{$_POST["varos"]}'></td></tr>";
        echo "<tr><td>{$webText["utca"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='utca' value='{$_POST["utca"]}'></td></tr>";
        echo "<tr><td>{$webText["munkakor"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='munkakor' value='{$_POST["munkakor"]}'></td></tr>";
        echo "<tr><td>{$webText["torzsszam"]}:</td><td><input class='inputbox' style='width:250px;' type='text' name='torzsszam' value='{$_POST["torzsszam"]}'></td></tr>";
        echo "<tr><td colspan='2'><hr></td></tr>";
        echo "<tr><td>{$webText["jelszo"]}:</td><td><input style='width:200px;display:none;' type='text' autocomplete='off' name='dummyuser' value='' /><input style='width:200px;display:none;' type='password' autocomplete='off' name='dummypass' value='' /><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo' value=''/></td></tr>";
        echo "<tr><td>{$webText["jelszoujra"]}:</td><td><input class='inputbox' style='width:200px;' type='password' autocomplete='off' name='jelszo2' value=''/></td></tr>";
        echo "<tr><td></td><td style='color:#888;'>{$webText["toltsdki2jelszo"]}</td></tr>";
        echo "</table>";

        echo "<br/><a href='#' class='newbutton' onclick=\"
        if ($('#telefon').val()!=$('#oldtelefon').val()) {
            if (confirm('{$webText["telmodq"]} '+$('#telefon').val()+'?')) {
                document.iform.submit();return false;
            }
        } else {
            document.iform.submit();return false;
        }
        \">{$webText["adatokmodositasa"]}</a>";
        echo "<input type='hidden' name='adatmodositas' value='1' />";
        echo "</form>";
    }
}
