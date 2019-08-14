<?php

if (isset($_GET["tesztsms"])) {
    include("includes/seeme-gateway-class.php");
    sendSMS("36209996183","kód a regisztráció befejezéséhez: 1111");
    die("ok");
}

if (!isset($_SESSION["user"]) && isset($_GET["page"])) {
    if (in_array($_GET["page"],array("beutalok","dokumentumok","foglalasok"))) {
        header("location:/");
        die();
    }
}


if (isset($_POST["validatelogin"])) {
    $formerror="";
    if ($_POST["smskod"]=="") {
        $formerror.="{$webText["nemadtamegkod"]}<br/>";
    } else {

        $kod=round($_POST["smskod"]);
        if ($_POST["smskod"]!="" && !sql_fetch_array(sql_query("select rkod from felhasznalok where id='{$_SESSION["user"]["id"]}' and rkod='{$kod}'"))) {
            $formerror.="{$webText["hibaskod"]}<br/>";
        } else {
            sql_query("update felhasznalok set validated=1 where id='{$_SESSION["user"]["id"]}' and rkod='{$kod}'");
            header("location:index.php?page=sikereservenyesites");
            die();
        }
    }
}


if (isset($_POST["logintry"])){
    $formerror="";
    if ($rowu=sql_fetch_array(sql_query("select * from felhasznalok where email=? and jelszo=md5(?) and cegid=?",array($_POST["email"],$_POST["jelszo"],$_SESSION["helyszindata"]["id"])))) {
        $_SESSION["loggeduser"]=$rowu["id"];
        header("location:index.php");
        die();
    } else {
        $formerror="{$webText["loginerror"]}";
    }
}

function sendEljottMail($foglalasData) {
    include_once("phpmailer/class.phpmailer.php");
    $mail = new PHPMailer();
    $mail->From="noreply@hungariamed.hu";
    $mail->FromName="Hungariamed";
    //$mail->AddAddress($foglalasData["email"]); //ne élesítsd még
    $mail->AddAddress("jns@jns.hu");
    $mail->AddReplyTo("noreply@hungariamed.hu");
    $mail->IsHTML(true);

    if ($emailData=sql_fetch_array(sql_query("select * from ertekeles_formok where (instr(rule_cegids,'|{$foglalasData["cegid"]}|') or rule_cegids='all') and rule_mail=1 and rule_aftereljott=1"))) {
        $mailSzoveg=$emailData["mailszoveg_{$foglalasData["rlang"]}"];
        if ($mailSzoveg=="") $mailSzoveg=$emailData["mailszoveg_hu"];
        $mailSubject=$emailData["megnev_{$foglalasData["rlang"]}"];
        if ($mailSubject=="") $mailSubject=$emailData["megnev_hu"];
        if ($mailSzoveg!="" && $mailSubject!="") {
            $mailSzoveg=str_replace("#nev#",$foglalasData["nev"],$mailSzoveg);
            $mail->Subject=iconv("UTF-8","ISO-8859-2",$mailSubject);
            $mail->Body=iconv("UTF-8","ISO-8859-2",$mailSzoveg);
            $mail->Send();
            sql_query("update foglalasok set eljottmail=1 where id=?",array($foglalasData["id"]));
        }
    }
    return;
}


if (isset($_POST["passwordsend"])) {
    $formerror="";

    if (trim($_POST["email"])=="") {
        $formerror="{$webText["kerjukadjamegemail2"]}";
        return;
    }
    if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
        $formerror="{$webText["emailformat"]}";
        return;
    }

    if ($rowu=sql_fetch_array(sql_query("select * from felhasznalok where email=? and cegid=?",array($_POST["email"],$_SESSION["helyszindata"]["id"])))) {
        $pchars="abcdefghijklmnpqrstuvwxyz1234567899";
        $p="";
        for ($i=0;$i<8;$i++) {
            $p.=substr($pchars,rand(0,strlen($pchars)-1),1);
        }

        include_once("phpmailer/class.phpmailer.php");
        $mail = new PHPMailer();
        $mail->From="noreply@hungariamed.hu";
        $mail->FromName="Hungariamed";
        $mail->AddAddress($rowu["email"]);
        $mail->AddReplyTo("noreply@hungariamed.hu");
        $mail->IsHTML(true);

        $t=iconv("UTF-8","ISO-8859-2","Új jelszó kérése");

        $mbody="Kedves {$rowu["nev"]}!<br/><br/>";
        $mbody.="Az online bejelentkezési felületünkön új jelszó kérését kezdeményezte.<br/><br/>";
        $mbody.="Az új jelszava: <b>{$p}</b><br><br>";
        $mbody.="Az új jelszavát bejelentkezés követően az adatmódosítás menüpont alatt tudja megváltoztatni.<br/>";
        $mbody.="<br/>";
        $mbody.="Üdvözlettel:<br>Hungariamed";

        if ($_COOKIE["lang"]=="de") {
            $mbody="Lieber {$rowu["nev"]}!<br/><br/>";
            $mbody.="Unsere online anmelden Oberfláche sie beginnen eine neue Kennwort anbietten.<br/><br/>";
            $mbody.="Die neue Kennwort: <b>{$p}</b><br><br>";
            $mbody.="Nach den anmelden können Sie um  einem neuem Kennwort bitten.<br/>";
            $mbody.="<br/>";
            $mbody.="Freundlichen Grüssen:<br>Hungariamed";
        }
        if ($_COOKIE["lang"]=="en") {
            $mbody="Dear {$rowu["nev"]}!<br/><br/>";
            $mbody.="You have requested a new password on our reservation page.<br/><br/>";
            $mbody.="Your new password: <b>{$p}</b><br><br>";
            $mbody.="You can change your new password under the profile page.<br/>";
            $mbody.="<br/>";
            $mbody.="Regards<br>Hungariamed";
        }

        $mail->Subject=$t;
        $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
        //$mail->AddAttachment("");
        $mail->Send();

        sql_query("update felhasznalok set jelszo='".addslashes(md5($p))."'	where id='{$rowu["id"]}'");

        header("location:index.php?page=login&passwordsent");
        die();
    } else {
        $formerror="{$webText["nemtalalhatoemail"]}";
    }

}




if (isset($_GET["remotereserve"])) {
    if ($rowu=sql_fetch_array(sql_query("select * from felhasznalok where id='".intval($_GET["fid"])."' and rkod='".intval($_GET["fkod"])."'"))) {
        $_SESSION["remotebeutalo"]=$_GET["remotereserve"];
        $_SESSION["loggeduser"]=$rowu["id"];
        header("location:index.php?setbeutalo=".intval($_GET["remotereserve"]));
        die();
    }
}



if (isset($_POST["adatmodositas"])) {
    $formerror="";

    if (isset($_POST["szuldatumev"])) {
        $_POST["szuldatum"]=$_POST["szuldatumev"]."-".substr("00".$_POST["szuldatumho"],-2)."-".substr("00".$_POST["szuldatumnap"],-2);
    }

    $_POST["telefon"]=fixPhoneNumber($_POST["telefon"]);

    $_POST["taj"]=str_replace("-","",$_POST["taj"]);
    $_POST["taj"]=trim(str_replace(" ","",$_POST["taj"]));
    if ($_POST["taj"]=="" && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="{$webText["tajkotelezo"]}<br/>";
    if (!ctype_digit($_POST["taj"]) && $_POST["taj"]!="") $formerror.="{$webText["tajformat"]}<br/>";
    if ($_POST["taj"]!="" && sql_fetch_array(sql_query("select taj from felhasznalok where taj=? and cegid=? and id<>?",array($_POST["taj"],$_SESSION["helyszindata"]["id"],$_SESSION["user"]["id"])))) $formerror.="{$webText["tajletezik"]}<br/>";

    //if ($_POST["email"]=="") $formerror.="Az e-mail cím megadása kötelező!<br/>";
    //if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) && $_POST["email"]!="") $formerror.="Az e-mail cím formátuma nem megfelelő!<br/>";
    if ($_POST["nev"]=="") $formerror.="{$webText["nevkotelezo"]}<br/>";
    if ($_POST["telefon"]=="") $formerror.="{$webText["telkotelezo"]}<br/>";
    if (!ctype_digit($_POST["telefon"]) && $_POST["telefon"]!="") $formerror.="{$webText["telformat"]}<br/>";
    if ($_POST["szuldatum"]=="" && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="{$webText["szulkotelezo"]}<br/>";
    if (!validateDate($_POST["szuldatum"],"Y-m-d") && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="{$webText["szulformat"]}<br/>";
    //if ($_POST["munkakor"]=="" && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="A munkakör megadása kötelező!<br/>";
    if (!isset($_POST["neme"])) $formerror.="{$webText["nemekotelezo"]}<br/>";

    if ($_POST["jelszo"]!="") {
        if ($_POST["jelszo"]!=$_POST["jelszo2"]) $formerror.="{$webText["ketjelszonem"]}<br/>";
        if ($_POST["jelszo"]!="" && strlen($_POST["jelszo"])<6) $formerror.="{$webText["jelszomin"]}<br/>";
        if ($_POST["jelszo"]!="" && strlen($_POST["jelszo"])>20) $formerror.="{$webText["jelszomax"]}<br/>";
    }

    if ($formerror=="") {

        sql_query("update felhasznalok set nev=?,telefon=?,szuldatum=?,szulhely=?,anyjaneve=?,neme=?,taj=?,irsz=?,varos=?,utca=?,munkakor=?,torzsszam=? where id=?"
            ,array($_POST["nev"],$_POST["telefon"],$_POST["szuldatum"],$_POST["szulhely"],$_POST["anyjaneve"],$_POST["neme"],$_POST["taj"],$_POST["irsz"],$_POST["varos"],$_POST["utca"],$_POST["munkakor"],$_POST["torzsszam"],$_SESSION["user"]["id"]));

        if ($_POST["jelszo"]!="") {
            sql_query("update felhasznalok set jelszo=? where id=?",array(md5($_POST["jelszo"]),$_SESSION["user"]["id"]));
        }

        //ideiglenesen a funkció kiszedve
        if ($_POST["telefon"]!=$_POST["oldtelefon"] and false) {
            //megváltozott a telefon, új kódot küldünk és újravalidálunk.
            $rn=rand(11000,98000);
            sql_query("update felhasznalok set validated=0,rkod='{$rn}'	where id='{$_SESSION["user"]["id"]}'");
            sendUserSMSKod($_SESSION["user"]["id"]);
            header("location:index.php");
            die();
        }

        header("location:index.php?page=profil");
        die();

    }
}





if (isset($_POST["regisztracio"])) {
    $formerror="";

    if (isset($_POST["szuldatumev"])) {
        $_POST["szuldatum"]=$_POST["szuldatumev"]."-".substr("00".$_POST["szuldatumho"],-2)."-".substr("00".$_POST["szuldatumnap"],-2);
    }

    $_POST["telefon"]=fixPhoneNumber($_POST["telefon"]);

    $_POST["taj"]=str_replace("-","",$_POST["taj"]);
    $_POST["taj"]=trim(str_replace(" ","",$_POST["taj"]));

    //if (!isset($_SESSION["captcha"])) $formerror.="A form elévült, kérjük kattints újra az elküldésre!<br/>";

    if (!isset($_POST["munkakor"])) $_POST["munkakor"]="";
    if (!isset($_POST["torzsszam"])) $_POST["torzsszam"]="";

    if ($_POST["taj"]=="" && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="{$webText["tajkotelezo"]}<br/>";
    if (!ctype_digit($_POST["taj"]) && $_POST["taj"]!="") $formerror.="{$webText["tajformat"]}<br/>";
    if ($_POST["taj"]!="" && sql_fetch_array(sql_query("select taj from felhasznalok where taj=? and cegid=?",array($_POST["taj"],$_SESSION["helyszindata"]["id"])))) $formerror.="{$webText["tajletezik"]}<br/>";

    if ($_POST["email"]=="") $formerror.="{$webText["emailkotelezo"]}<br/>";
    if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) && $_POST["email"]!="") $formerror.="{$webText["emailformat"]}<br/>";
    if ($_POST["email"]!="" && sql_fetch_array(sql_query("select taj from felhasznalok where email=? and cegid=?",array($_POST["email"],$_SESSION["helyszindata"]["id"])))) $formerror.="{$webText["emailletezik"]}<br/>";

    if ($_POST["jelszo"]=="") $formerror.="{$webText["jelszokotelezo"]}<br/>";
    if ($_POST["jelszo"]!=$_POST["jelszo2"]) $formerror.="{$webText["ketjelszonem"]}<br/>";
    if ($_POST["jelszo"]!="" && strlen($_POST["jelszo"])<6) $formerror.="{$webText["jelszomin"]}<br/>";
    if ($_POST["jelszo"]!="" && strlen($_POST["jelszo"])>20) $formerror.="{$webText["jelszomax"]}<br/>";
    if ($_POST["nev"]=="") $formerror.="{$webText["nevkotelezo"]}<br/>";
    if ($_POST["telefon"]=="") $formerror.="{$webText["telkotelezo"]}<br/>";
    if (!ctype_digit($_POST["telefon"]) && $_POST["telefon"]!="") $formerror.="{$webText["telformat"]}<br/>";
    if ($_POST["szuldatum"]=="" && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="{$webText["szulkotelezo"]}<br/>";
    if (!validateDate($_POST["szuldatum"],"Y-m-d") && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="{$webText["szulformat"]}<br/>";
    //if (isset($_POST["munkakor"]) && $_POST["munkakor"]=="" && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="A munkakör megadása kötelező! {$_SESSION["helyszindata"]["tajnotreq"]}<br/>";
    if (!isset($_POST["neme"]) && $_SESSION["helyszindata"]["tajnotreq"]==0) $formerror.="{$webText["nemekotelezo"]}<br/>";

    if (!isset($_POST["neme"])) $_POST["neme"]=0;

    //if ($_POST["captcha"]!=$_SESSION["captcha"] && $_POST["captcha"]!="111") $formerror.="Az megadott szám nem egyezik!<br/>";
    if (!isset($_POST["aszf"])) $formerror.="{$webText["aszfkotelezo"]}<br/>";


    if (isset($_POST["g-recaptcha-response"])) $captcha=$_POST["g-recaptcha-response"];
    if (isset($captcha)) {
        if (!$captcha){
            $formerror.="{$webText["captchaerror1"]}<br/>";
        } else {
            $response=json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfCaTIUAAAAAF1-t94n7TBAsKov_dglwP6b8Luo&response=".urlencode($captcha)."&remoteip=".$_SERVER["REMOTE_ADDR"]), true);
            if ($response["success"]==false) {
                $formerror.="{$webText["captchaerror2"]}<br/>";
            }
        }
    } else {
        $formerror.="{$webText["captchaerror3"]}<br/>";
    }



    if ($formerror=="") {
        $rn=rand(11000,98000);

        sql_query("insert into felhasznalok set
		cegid=?,regtime=now(),nev=?,email=?,jelszo=?,telefon=?,szuldatum=?,neme=?,taj=?,irsz=?,varos=?,utca=?,munkakor=?,torzsszam=?,
		rkod=?",array($_SESSION["helyszindata"]["id"],$_POST["nev"],$_POST["email"],md5($_POST["jelszo"]),$_POST["telefon"],$_POST["szuldatum"],$_POST["neme"],$_POST["taj"],$_POST["irsz"],$_POST["varos"],$_POST["utca"],$_POST["munkakor"],$_POST["torzsszam"],$rn));

        $id=sql_insert_id();
        if( $_SESSION["helyszindata"]["id"] != 11 ) sendUserSMSKod( $id );


        $_SESSION["loggeduser"]=$id;

        header("location:index.php");
        die();
    }
}

if (isset($_POST["idopontfoglalas"])) {
    $formerror="";

    //nem kötelező mezők létrehozása ha nincsenek
    if (!isset($_POST["szulhely"])) $_POST["szulhely"] = "";
    if (!isset($_POST["anyjaneve"])) $_POST["anyjaneve"] = "";
    if (!isset($_POST["irsz"]))  $_POST["irsz"] = "";
    if (!isset($_POST["varos"]))  $_POST["varos"] = "";
    if (!isset($_POST["utca"])) $_POST["utca"] = "";

//print_r($_POST);die;
    if (isset($_POST["szuldatumev"])) $_POST["szuldatum"]=$_POST["szuldatumev"]."-".substr("00".$_POST["szuldatumho"],-2)."-".substr("00".$_POST["szuldatumnap"],-2);

    $_POST["taj"]=str_replace("-","",$_POST["taj"]);
    $_POST["taj"]=trim(str_replace(" ","",$_POST["taj"]));

    //if (!isset($_SESSION["captcha"])) $formerror.="A form elévült, kérjük kattints újra az elküldésre!<br/>";
    if ($_POST["taj"]=="") $formerror.="{$webText["tajkotelezo"]}<br/>";
    if (!ctype_digit($_POST["taj"]) && $_POST["taj"]!="") $formerror.="{$webText["tajformat"]}<br/>";
    if ($_POST["helyszin"]=="0") $formerror.="{$webText["helyszinkotelezo"]}<br/>";
    if ($_POST["datum"]=="") $formerror.="{$webText["idopontkotelezo"]}<br/>";
    if ($_POST["szurestipus"]=="0") $formerror.="{$webText["szurestipuskotelezo"]}<br/>";

    if ($_POST["email"]=="") $formerror.="{$webText["emailkotelezo"]}<br/>";
    if ($_POST["nev"]=="") $formerror.="{$webText["nevkotelezo"]}<br/>";
    if ($_POST["telefon"]=="") $formerror.="{$webText["telkotelezo"]}<br/>";
    if ($_POST["szuldatum"]=="") $formerror.="{$webText["szulkotelezo"]}<br/>";
    if (!validateDate($_POST["szuldatum"],"Y-m-d")) $formerror.="{$webText["szulformat"]}<br/>";

    //if ($_POST["irsz"]=="") $formerror.="Az irányítószám megadása kötelező!<br/>";
    //if ($_POST["varos"]=="") $formerror.="A város megadása kötelező!<br/>";
    //if ($_POST["utca"]=="") $formerror.="Az utca megadása kötelező!<br/>";
    if (isset($_POST["munkakor"])) {
        if ($_POST["munkakor"]=="") $formerror.="{$webText["munkakorkotelezo"]}<br/>";
    } else {
        $_POST["munkakor"]="";
    }


    if (!isset($_POST["neme"])) $formerror.="{$webText["nemekotelezo"]}<br/>";
    if (!isset($_POST["aszf"])) $formerror.="{$webText["aszfkotelezo"]}<br/>";

    if (isset($_POST["telephely"]) && trim($_POST["telephely"])=="") $formerror.="{$webText["telephelykotelezo"]}<br/>";


    if (isset($_POST["captcha"]) && $_POST["captcha"]!=$_SESSION["captcha"] && $_POST["captcha"]!="111") $formerror.="Az megadott szám nem egyezik!<br/>";

    //if ($rowe=sql_fetch_array(sql_query("select id,datum,rkod from foglalasok where cegid='".addslashes($_SESSION["helyszindata"]["id"])."' and taj='".addslashes($_POST["taj"])."' and now()<datum"))) {
    //	$formerror.="Már van egy foglalása ".substr($rowe["datum"],0,16)." időpontra. Ha újra szeretne foglalni, kérjük törölje az előző foglalását! <a style='color:#ff0;' href='index.php?page=torles&id={$rowe["id"]}&rk={$rowe["rkod"]}'>Időpont törlése</a>";
    //}

    if ($_POST["datum"]!="" && !checkIdopontSzabad($_POST)) $formerror.="{$webText["idopontlefoglaltak"]}<br>";
    if (!isset($_POST["rinterval"])) $_POST["rinterval"] = 0;
    if (!isset($_POST["telephely"])) $_POST["telephely"] = "";

    if (!isset($_SESSION["user"])) {
        if (isset($_POST["version2"])) {
            if (isset($_POST["g-recaptcha-response"])) $captcha=$_POST["g-recaptcha-response"];
            if (isset($captcha)) {
                if (!$captcha){
                    $formerror.="{$webText["captchaerror1"]}<br/>";
                } else {
                    $response=json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfCaTIUAAAAAF1-t94n7TBAsKov_dglwP6b8Luo&response=".urlencode($captcha)."&remoteip=".$_SERVER["REMOTE_ADDR"]), true);
                    if ($response["success"]==false) {
                        $formerror.="{$webText["captchaerror2"]}<br/>";
                    }
                }
            } else {
                $formerror.="{$webText["captchaerror3"]}<br/>";
            }
        }
    }


    if ($formerror=="") {
        if (!isset($_POST["tudoszuro"])) $_POST["tudoszuro"]=0;

        $rn=rand(1000000,9999999);

        $paciensId=0;

        if (isset($_SESSION["user"]["id"])) {
            $paciensId=intval($_SESSION["user"]["id"]);
        } else {
            $request_user = sql_query("SELECT * FROM felhasznalok WHERE (taj = ? OR email = ?) and cegid=?",array($_REQUEST['taj'], $_REQUEST['email'],$_SESSION["helyszindata"]["id"]));
            if (sql_num_rows($request_user) > 0) {
                $userInfo = sql_fetch_array($request_user);
                $paciensId = $userInfo['id'];
            } else {
                sql_query("INSERT INTO felhasznalok SET validated=1, cegid=?, regtime=now(), taj = ?, email = ?, nev = ?, telefon = ?, munkakor = ?, irsz = ?, varos = ?, utca = ?, szulhely = ?, anyjaneve = ?, szuldatum = ? ",
                    array($_SESSION["helyszindata"]["id"], $_REQUEST['taj'], $_REQUEST['email'], $_REQUEST['nev'], $_REQUEST['tel'], $_REQUEST['munkakor'],$_REQUEST['irsz'], $_REQUEST['varos'], $_REQUEST['utca'], $_REQUEST['szulhely'], $_REQUEST['anyjaneve'],$_REQUEST['szuldatum'] ));
                $paciensId=sql_insert_id();
            }
        }


        if (isset($_SESSION["user"]["id"])) $paciensId=intval($_SESSION["user"]["id"]);

        sql_query("insert into foglalasok set regdatum=now(),paciensid=?,cegid=?,datum=?,rinterval=?,telephely=?,helyszinid=?,szurestipusid=?,nev=?,email=?,telefon=?,szuldatum=?,szulhely=?,anyjaneve=?,neme=?,taj=?,irsz=?,varos=?,utca=?,megj=?,munkakor=?,tudoszuro=?,rlang=?,rkod=?"
            ,array($paciensId,$_SESSION["helyszindata"]["id"],$_POST["datum"],intval($_POST["rinterval"]),$_POST["telephely"],$_POST["helyszin"],$_POST["szurestipus"],$_POST["nev"],$_POST["email"],$_POST["telefon"],$_POST["szuldatum"],$_POST["szulhely"],$_POST["anyjaneve"],$_POST["neme"],$_POST["taj"],$_POST["irsz"],$_POST["varos"],$_POST["utca"],$_POST["megj"],$_POST["munkakor"],$_POST["tudoszuro"],$_COOKIE["lang"],$rn));

        $fid=sql_insert_id();
        updateFoglalasData($fid);

        $oid=selectFreeOrvosForIdopont($fid);
        sql_query("update foglalasok set orvosassigned=? where id=?",array($oid,$fid));

        if (isset($_SESSION["beutaloid"]) && isset($_SESSION["user"]) && $rowb=sql_fetch_array(sql_query("select * from beutalok where id=?",array($_SESSION["beutaloid"])))) {
            sql_query("update beutalok set foglalasid=? where id=?",array($fid,$_SESSION["beutaloid"]));
            sql_query("update fogalalasok set megj=? where id=?",array($rowb["megj"],$fid));
            unset($_SESSION["beutaloid"]);
        }

        //altipusok tárolása
        $res=sql_query("select * from arak where instr(cegid,?) and tipusid=? and csomag=0",array("|{$_SESSION["helyszindata"]["id"]}|",$_POST["szurestipus"]));
        while ($row=sql_fetch_array($res)) {
            if (isset($_POST["altipus{$row["id"]}"])) {
                sql_query("insert into fizkapcs set fid=?,aid=?,megnev=?,ar=?,valuta=?",array($fid,$row["id"],$row["megnev"],$row["price"],$row["penznem"]));
            }
        }

        if (isset($_SESSION["remotebeutalo"]) || $_SESSION["helyszindata"]["visszaigazolas"]==0) {
            //orvos jött, akkor nem kérünk visszaigazolást, megyünk visszaigazolni automatikusan
            header("location:index.php?page=megerosites&id={$fid}&rk={$rn}");
        } else {
            //visszaigazolást kérünk
            sendVisszaIgazolas($fid);
            header("location:index.php?page=sikeresfoglalas");
        }

        die();
    }
}


if (isset($_GET["tesztvissza"])) {
    sendVisszaIgazolas(89252);
    die("sent");
}


if( isset( $_REQUEST['idopontfoglalasV2'] ))
{
    $formerror = "";
    //Hibás adat korrigálás:
    $_POST["szuldatum"] = $_POST["szuldatumev"]."-".substr( "00".$_POST["szuldatumho"], -2 )."-".substr( "00".$_POST["szuldatumnap"], -2 );
    $_POST["taj"] 		= str_replace( "-", "", $_POST["taj"] );
    $_POST["taj"]	    = trim(str_replace( " ", "", $_POST["taj"] ));

    //Mező ellenőrzés:
    //if( $_POST["taj"] == "" ) $formerror.= "{$webText["tajkotelezo"]}<br/>";
    if( !ctype_digit( $_POST["taj"] ) && $_POST["taj"] != "" ) $formerror.="{$webText["tajformat"]}<br/>";
    if( $_POST["helyszin"] == "0" ) $formerror.="{$webText["helyszinkotelezo"]}<br/>";
    if( $_POST["datum"] == "" ) $formerror.="{$webText["idopontkotelezo"]}<br/>";
    if( $_POST["szurestipus"] == "0" ) $formerror.="{$webText["szurestipuskotelezo"]}<br/>";

    if( $_POST["email"] == "") $formerror.="{$webText["emailkotelezo"]}<br/>";
    if( $_POST["nev"] == "" ) $formerror.="{$webText["nevkotelezo"]}<br/>";
    if( $_POST["tel"] == "" ) $formerror.="{$webText["telkotelezo"]}<br/>";
    if( $_POST["szuldatum"] == "" ) $formerror.="{$webText["szulkotelezo"]}<br/>";
    if(!validateDate( $_POST["szuldatum"], "Y-m-d" )) $formerror.= "{$webText["szulformat"]}<br/>";

    //if( !isset( $_POST["neme"] )) $formerror.= "{$webText["nemekotelezo"]}<br/>";
    if ( !isset( $_POST["aszf"] )) $formerror.= "{$webText["aszfkotelezo"]}<br/>";

    //Még nem bevett mezők:
    //if ($_POST["irsz"]=="") $formerror.="Az irányítószám megadása kötelező!<br/>";
    //if ($_POST["varos"]=="") $formerror.="A város megadása kötelező!<br/>";
    //if ($_POST["utca"]=="") $formerror.="Az utca megadása kötelező!<br/>";
    if( isset( $_POST["munkakor"] )) if ( $_POST["munkakor"] == "" ) $formerror.= "{$webText["munkakorkotelezo"]}<br/>";
    else $_POST["munkakor"] = "";

    //Szabad időpont ellenőrzése:
    if ($_POST["datum"]!="" && !checkIdopontSzabad($_POST)) $formerror.="{$webText["idopontlefoglaltak"]}<br>";

    //Captcha ellenőrzés ha a páciens nincs belépve:
    if( !isset( $_SESSION["user"] ))
    {
        if( isset( $_POST["version2"] ))
        {
            if( isset( $_POST["g-recaptcha-response"] )) $captcha = $_POST["g-recaptcha-response"];
            if( isset( $captcha ))
            {
                if( !$captcha ) $formerror.="{$webText["captchaerror1"]}<br/>";
                else
                {
                    $response = json_decode( file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=6LfCaTIUAAAAAF1-t94n7TBAsKov_dglwP6b8Luo&response=".urlencode( $captcha )."&remoteip=".$_SERVER["REMOTE_ADDR"]), true );
                    if( $response["success"] == false ) $formerror.= "{$webText["captchaerror2"]}<br/>";
                }
            }
            else $formerror.= "{$webText["captchaerror3"]}<br/>";
        }
    }

    //Ha nincsen hiba akkor rögzítem az adatok az adatbázisban:
    if ( $formerror == "" )
    {
        if ( !isset( $_POST["tudoszuro"] )) $_POST["tudoszuro"] = 0;

        $rn = rand( 1000000, 9999999 );

        $paciensId = 0;

        if( isset( $_SESSION["user"]["id"] )) $paciensId=intval($_SESSION["user"]["id"]);
        else
        {
            $request_user = sql_query("SELECT * FROM felhasznalok WHERE (taj = ? OR email = ?) and cegid=?",
                array( $_REQUEST['taj'], $_REQUEST['email'], $_SESSION["helyszindata"]["id"] ));
            if ( sql_num_rows( $request_user ) > 0 )
            {
                $userInfo  = sql_fetch_array( $request_user );
                $paciensId = $userInfo['id'];
            }
            else
            {
                sql_query("INSERT INTO felhasznalok SET 
						   validated = 1, cegid = ?, regtime = NOW(), taj  = ?, email 	  = ?, nev 		 = ?, telefon   = ?, 
						   munkakor  = ?, irsz 	= ?, varos	 = ?, 	  utca = ?, szulhely  = ?, anyjaneve = ?, szuldatum = ? ",
                    array( $_SESSION["helyszindata"]["id"], $_REQUEST['taj'], $_REQUEST['email'], $_REQUEST['nev'], $_REQUEST['tel'],
                        $_REQUEST['munkakor'],$_REQUEST['irsz'], $_REQUEST['varos'], $_REQUEST['utca'], $_REQUEST['szulhely'],
                        $_REQUEST['anyjaneve'],$_REQUEST['datum']
                    ));
                $paciensId = sql_insert_id();
            }
        }

        if( isset( $_SESSION["user"]["id"] )) $paciensId = intval( $_SESSION["user"]["id"] );

        sql_query("INSERT INTO foglalasok SET 
				   regdatum  = NOW(), paciensid = ?, cegid = ?, datum 	 = ?, helyszinid = ?, 
				   szurestipusid = ?, nev 		= ?, email = ?, telefon  = ?, szuldatum  = ?, 
				   neme  		 = ?, taj       = ?, megj  = ?, rlang 	 = ?, rkod 		 = ?",
            array( $paciensId, $_SESSION["helyszindata"]["id"], $_POST["datum"], $_POST["helyszin"], $_POST["szurestipus"],
                $_POST["nev"], $_POST["email"], $_POST["tel"], $_POST["szuldatum"],
                $_POST["neme"], $_POST["taj"], $_POST["megj"], $_COOKIE["lang"], $rn ));

        $fid = sql_insert_id();
        updateFoglalasData( $fid );

        if( $_POST['kuponkod'] != "" )
        {
            $foglalas = sql_fetch_array(sql_query("SELECT fogl.datum, kl.foglalasid, fogl.szurestipusid FROM foglalasok fogl LEFT JOIN kupon_lista kl ON kl.foglalasid = fogl.id WHERE fogl.id = ? ", array( $fid )));
            $check = kuponCheck($_POST['kuponkod'],3,date("Y-m-d",strtotime($foglalas['datum'])),$foglalas['szurestipusid']);
            if( $check == "usable")
            {
                $kupon = sql_fetch_array(sql_query("SELECT * FROM kuponkodok WHERE kod = ?", array($_POST['kuponkod'])));
                sql_query("INSERT INTO kupon_lista SET kuponid = ?, kuponkod = ?, foglalasid = ?",
                    array( $kupon['id'], $kupon['kod'], $fid ));
            }
        }

        $oid = selectFreeOrvosForIdopont( $fid );
        sql_query("UPDATE foglalasok SET orvosassigned = ? WHERE id = ?", array( $oid, $fid ));

        if ( isset( $_SESSION["beutaloid"] ) && isset( $_SESSION["user"] ) && $rowb = sql_fetch_array( sql_query( "SELECT * FROM beutalok WHERE id = ?", array( $_SESSION["beutaloid"] ))))
        {
            sql_query( "UPDATE beutalok SET foglalasid = ? WHERE id=?", array( $fid, $_SESSION["beutaloid"] ));
            sql_query( "UPDATE fogalalasok SET megj = ? where id = ?", array( $rowb["megj"], $fid ));
            unset( $_SESSION["beutaloid"] );
        }

        //altipusok tárolása
        $res = sql_query( "SELECT * FROM arak WHERE INSTR( cegid, ? ) and tipusid = ? and csomag=0", array( "|{$_SESSION["helyszindata"]["id"]}|", $_POST["szurestipus"] ));
        while( $row = sql_fetch_array( $res ))
        {
            if( isset( $_POST["altipus{$row["id"]}"] ))
            {
                sql_query( "INSERT INTO fizkapcs SET fid = ?, aid = ?, megnev = ?, ar = ?, valuta = ?",
                    array( $fid, $row["id"], $row["megnev"], $row["price"], $row["penznem"] ));
            }
        }

        if( isset( $_SESSION[ "remotebeutalo"] ) || $_SESSION["helyszindata"]["visszaigazolas"] == 0 )
        {
            //orvos jött, akkor nem kérünk visszaigazolást, megyünk visszaigazolni automatikusan
            header( "Location:index.php?page=megerosites&id={$fid}&rk={$rn}" );
        }
        else
        {
            //visszaigazolást kérünk
            sendVisszaIgazolas( $fid );
            header( "Location:index.php?page=sikeresfoglalas" );
        }

        die();
    }
}





function checkIdopontSzabad($data) {
    //TODO: időpont szabadság vizsgálása még kell ide..
    //$_POST["datum"]
    //$_POST["helyszin"]
    //$_POST["szurestipus"]

    if (selectOrvosForIdopont($data["datum"],$data["helyszin"],$data["szurestipus"],$data["orvosselected"])) return true;
    return false;
}


function sendUserSMSKod($userid) {
    if ($rowu=sql_fetch_array(sql_query("SELECT f.* FROM felhasznalok f 
	LEFT JOIN cegek c ON c.id=f.cegid
	WHERE f.id=? AND c.`noregsms`=0",array($userid))))	{
        include("includes/seeme-gateway-class.php");
        sendSMS($rowu["telefon"],"kód a regisztráció befejezéséhez: {$rowu["rkod"]}");
    } else {
        sql_query("update felhasznalok set validated=1 where id=?",array($userid));
    }
}

function sendLoginSMSKod($userid) {
    if ($rowu=sql_fetch_array(sql_query("select * from felhasznalok where id='{$userid}'")))	{
        include("includes/seeme-gateway-class.php");
        sendSMS($rowu["telefon"],"kód a bejelentkezéshez: {$rowu["rkod"]}");
    }
}

function sendVisszaIgazolas($id) {
    //Visszaigazolás a foglalásról, megerősítés kérése
    $h="cim";
    if ($_SESSION["helyszindata"]["nocim"]==1) $h="megnev";

    $res=sql_query("SELECT h.{$h} AS helyszin,sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
	LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
	LEFT JOIN cegek c on c.id=f.cegid
	LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
	WHERE f.id='{$id}'");
    if ($row=sql_fetch_array($res)) {
        if ($row["rlang"] == "en" && $row["szurestipus_en"] != "") $row["szurestipus"] = $row["szurestipus_en"];
        if ($row["rlang"] == "de" && $row["szurestipus_de"] != "") $row["szurestipus"] = $row["szurestipus_de"];

        include_once("phpmailer/class.phpmailer.php");
        $mail = new PHPMailer();
        $mail->From="noreply@hungariamed.hu";
        $mail->FromName="Hungariamed";
        $mail->AddAddress($row["email"]);
        $mail->AddReplyTo("noreply@hungariamed.hu");
        $mail->IsHTML(true);

        $webTextLocal = getWebTexts($row["rlang"]);
        $t=iconv("UTF-8","ISO-8859-2",$webTextLocal["mailtitleerositsdmeg"]);

        $mbody = "";

        if ($row["rlang"]=="hu") {
            $mbody = "<h2>Már majdnem kész!</h2>
            ha nem erősíti meg <b>1 órán belül</b>, a foglalása automatikusan <b>törlődik.</b><br/>
            {$webTextLocal["nev"]}: {$row["nev"]}<br>
            {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
            <b>Időpont: {$row["datum"]}</b><br>
            {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
            {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
            <br/>
            Az időpont foglalásának megerősítéséhez <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=megerosites&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>kattintson ide</a><br>
            <br/>
            Üdvözlettel:<br>Hungariamed";
        }
        if ($row["rlang"]=="de") {
            $mbody = "<h2>Már majdnem kész!</h2>
            ha nem erősíti meg <b>1 órán belül</b>, a foglalása automatikusan <b>törlődik.</b><br/>
            {$webTextLocal["nev"]}: {$row["nev"]}<br>
            {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
            <b>Időpont: {$row["datum"]}</b><br>
            {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
            {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
            <br/>
            Az időpont foglalásának megerősítéséhez <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=megerosites&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>kattintson ide</a><br>
            <br/>
            Üdvözlettel:<br>Hungariamed";
        }
        if ($row["rlang"]=="en") {
            $mbody = "<h2>Almost done!</h2>
            if you do not confirm <b>within 1 hour</b>, your reservation will be automatically <b>canceled</b>.<br/>
            {$webTextLocal["nev"]}: {$row["nev"]}<br>
            {$webTextLocal["telefon"]}: {$row["telefon"]}<br>
            <b>Time: {$row["datum"]}</b><br>
            {$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>
            {$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>
            <br/>
            To confirm your reservation <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=megerosites&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>click here</a><br>
            <br/>
            Regards<br>Hungariamed";
        }

        $mail->Subject=$t;
        $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
        //$mail->AddAttachment("");
        $mail->Send();
    }
}


if (isset($_GET["tesztvimail"])) {
    sendNotConfirmedReservationMessages(65143);
    die();
}


function sendNotConfirmedReservationMessages($id) {
    /*
    nem visszaigazolt foglalás esetén:
    - mail a paciensnek
    - mail a hmm-nek
    - sms a paciensnek
    */
    $h="cim";
    if ($_SESSION["helyszindata"]["nocim"]==1) $h="megnev";;

    $res=sql_query("SELECT h.{$h} AS helyszin,sz.megnev AS szurestipus,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
	LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
	LEFT JOIN cegek c on c.id=f.cegid
	LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
	WHERE f.id=?",array($id));
    if ($row=sql_fetch_array($res)) {
        include_once("phpmailer/class.phpmailer.php");
        $mail = new PHPMailer();
        $mail->From="noreply@hungariamed.hu";
        $mail->FromName="Hungariamed";
        $mail->AddAddress($row["email"]);
        //$mail->AddAddress("jns@jns.hu");
        $mail->AddReplyTo("noreply@hungariamed.hu");
        $mail->IsHTML(true);

        $t=iconv("UTF-8","ISO-8859-2","Figyelem! Foglalását töröltük!");

        $mbody="<h2>Foglalását töröltük!</h2>";
        $mbody.="Előző levelünkben küldött megerősítő hivatkozásra nem kattintott rá, ezért a {$row["datum"]} időpontra szóló foglalását töröltük.<br/>";
        $mbody.="<br/>";
        $mbody.="Üdvözlettel:<br/>Hungariamed";

        $mail->Subject=$t;
        $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
        //$mail->AddAttachment("");
        $mail->Send();

        $mail = new PHPMailer();
        $mail->From="noreply@hungariamed.hu";
        $mail->FromName="Hungariamed";
        $mail->AddAddress("bejelentkezes@hungariamed.hu");
        $mail->AddReplyTo("noreply@hungariamed.hu");
        $mail->IsHTML(true);

        $t=iconv("UTF-8","ISO-8859-2","Egy paciens foglalása törölve lett!");

        $mbody="<h2>Törölt foglalás</h2>";
        $mbody.="A paciens foglalt, de nem igazolta vissza a következő rendelést, ezért azt töröltük:<br/>";
        $mbody.="Név: {$row["nev"]}<br/>";
        $mbody.="Telefon: {$row["telefon"]}<br/>";
        $mbody.="Email: {$row["email"]}<br/>";
        $mbody.="<b>Időpont: {$row["datum"]}</b><br/>";
        $mbody.="Szűréstípus: {$row["szurestipus"]}<br/>";
        $mbody.="Helyszín: {$row["helyszin"]}<br/>";
        $mbody.="<br/>";
        $mbody.="Hívd fel az ügyfelet egyeztetés céljából.</a><br>";

        $mail->Subject=$t;
        $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
        //$mail->AddAttachment("");
        $mail->Send();

        include_once("includes/seeme-gateway-class.php");
        sendSMS($row["telefon"],"Figyelem, {$row["datum"]} foglalását visszaigazolás hiányában töröltük!");
    }
}



function sendToUser($id) {
    //visszaigazoló levél a foglalás sikerességéről

    if (isset($_GET["tesztvisszaigazolo"])) {
        $res=sql_query("SELECT ".cimLangQuery("helyszin").",sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail,c.domain FROM foglalasok f
		LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
		LEFT JOIN cegek c on c.id=f.cegid
		LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
		WHERE f.id='{$id}'");
    } else {
        $res=sql_query("SELECT ".cimLangQuery("helyszin").",sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail,c.domain FROM foglalasok f
		LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
		LEFT JOIN cegek c on c.id=f.cegid
		LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
		WHERE f.id='{$id}' and f.userertesitve=0");
    }

    if ($row=sql_fetch_array($res)) {
        if ($row["rlang"] == "en" && $row["szurestipus_en"] != "") $row["szurestipus"] = $row["szurestipus_en"];
        if ($row["rlang"] == "de" && $row["szurestipus_de"] != "") $row["szurestipus"] = $row["szurestipus_de"];

        $extraMsg = "";

        if ($result = sql_fetch_array(sql_query("SELECT * FROM felhasznalok WHERE id = '".intval($row["paciensid"])."'"))) {
            if ((strtotime("now")-strtotime($result["regtime"]))<3600) {
                $c=explode(",",$row["domain"]);
                $extraMsg = "A kiállított leleteit és dokumentumait a https://{$c[0]}.hungariamed.hu oldalon a taj számával megtekintheti online.<br/>";
            }
        }

        $webTextLocal = getWebTexts($row["rlang"]);

        sql_query("update foglalasok set userertesitve=1 where id='{$id}'");

        $resv=sql_query("SELECT * FROM visszaigazolok WHERE cegid='{$row["cegid"]}' AND (orvosid='{$row["orvosassigned"]}' OR orvosid=0) AND (helyszinid='{$row["helyszinid"]}' OR helyszinid=0) AND TRIM(szoveg)<>''");


        include_once("phpmailer/class.phpmailer.php");
        $mail = new PHPMailer();
        $mail->From="noreply@hungariamed.hu";
        $mail->FromName="Hungariamed";
        $mail->AddAddress($row["email"]);
        $mail->CharSet="UTF-8";
        $mail->AddReplyTo("noreply@hungariamed.hu");
        $mail->IsHTML(true);

        $t="{$webTextLocal["sikeresidopontreg"]}";

        $mbody="";
        $mbody.="<h1>{$row["datum"]} - {$row["helyszin"]}</h1>";
        $mbody.="{$webTextLocal["nev"]}: {$row["nev"]}<br>";
        $mbody.="{$webTextLocal["telefon"]}: {$row["telefon"]}<br><br>";
        $mbody.="<b>{$webTextLocal["idopont"]}: {$row["datum"]}</b><br><br>";
        $mbody.="{$webTextLocal["szurestipus"]}: {$row["szurestipus"]}<br>";
        $mbody.="{$webTextLocal["helyszin"]}: {$row["helyszin"]}<br>";

        while ($rowv=sql_fetch_array($resv)) {
            $maplink="";
            if ($rowv["mapurl"]!="") $maplink="<a href='{$rowv["mapurl"]}'>Az útvonal térképen megjelenítéséhez kattintson ide.</a>";
            $rowv["szoveg"]=str_replace("#maplink#",$maplink,$rowv["szoveg"]);
            $mbody.="<hr>".nl2br($rowv["szoveg"]);
        }

        $mbody.="<hr>";

        if ($row["rlang"]=="hu") {
            $mbody.="Ha törölni szeretné ezt a foglalását, kérjük kattintson a következő linkre: <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=torles&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>időpont regisztráció törlése</a><br>";
            $mbody.="Amennyiben módosítani szeretné a foglalását, abban az esetben először törölje a régi időpontját a fenti linken, utána pedig regisztrálja újra.<br>{$extraMsg}";
            $mbody.="<br/>";
            $mbody.="Üdvözlettel:<br>Hungariamed";
        }
        if ($row["rlang"]=="de") {
            $mbody.="Wenn Sie möchten Diese Termin Reservierung Canceln, bitte drücken Sie an Ihre Brief <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=torles&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>Die Termin Registration Canceln</a> LINK.<br>";
            $mbody.="Wenn Sie möchten Ihre Reservierung Verändern ,bitte Streichen Sie aus den anderen Zeitpunkt, dannach registrieren bitte nochmal.<br>";
            $mbody.="<br/>";
            $mbody.="Üdvözlettel:<br>Hungariamed";
        }
        if ($row["rlang"]=="en") {
            $mbody.="If you wish to cancel this appointment, please click on link: <a href='http://{$_SERVER["HTTP_HOST"]}/index.php?page=torles&id={$row["id"]}&rk={$row["rkod"]}&setlang={$row["rlang"]}'>Cancellation of confirmed appointment</a><br>";
            $mbody.="If you would like to modify your appointment, first cancel your old appointment then register it again.<br>";
            $mbody.="<br/>";
            $mbody.="Regards:<br>Hungariamed";
        }

        $mail->Subject=$t;
        //$mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
        $mail->Body=$mbody;
        //$mail->AddAttachment("");

        if (true) {
            $mail->addStringAttachment(getCalendarItem($row),'foglalas.ics','base64','text/calendar');
        }

        $mail->Send();

    }
}



if (isset($_GET["tesztvisszaigazolo"])) {
    sendToUser(91);
    die();
}


function deleteFoglalas($id,$kod) {
    if ($row=sql_fetch_array(sql_query("select id from foglalasok WHERE id=? and rkod=? and datum>now() and eljott=0",array($id,$kod)))) {
        sql_query("update beutalok set foglalasid='0' where foglalasid='{$row["id"]}'");
        sql_query("delete from foglalasok WHERE id='{$row["id"]}'");
    }
    return;
}


if (isset($_GET["dodeleteidopont"])) {
    deleteFoglalas($_GET["id"],$_GET["rk"]);
    header("location:index.php?page=torlessikeres");
    die();
}
if (isset($_GET["deltime"])) {
    deleteFoglalas($_GET["id"],$_GET["rk"]);
    header("location:index.php?page={$_GET["page"]}");
    die();
}



function showDebugInfo($s) {
    if ($_SERVER["REMOTE_ADDR"]=="88.151.97.121") {
        echo "<div>{$s}</div>";
    }
}





if (isset($_GET["showidopontvalaszto"])) {
    $honnan=intval($_GET["honnan"]);
    if ($honnan<0) $honnan=0;

    $helyszin=intval($_GET["helyszin"]);
    $szurestipus=intval($_GET["szurestipus"]);
    $taj=$_GET["taj"];

    $szunnapok[]="";
    $rows=sql_fetch_array(sql_query("select * from settings"));
    $n=explode(",",$rows["szunnapok"]);
    for ($i=0;$i<count($n);$i++) {
        $szunnapok[]=trim($n[$i]);
    }

    $foglaltnapok[]="";
    $res=sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and nap>=? and (szurestipusid=0 or szurestipusid=?)",array($helyszin,$_SESSION["helyszindata"]["id"],date("Y-m-d"),$szurestipus));
    while ($row=sql_fetch_array($res)) {
        $foglaltnapok[]=$row["nap"];
    }

    $res=sql_query("select datum,COUNT(*) AS hany,GROUP_CONCAT(szurestipusid) AS szurestipusok from foglalasok where helyszinid='{$helyszin}' and szurestipusid='{$szurestipus}' and datum>now() GROUP BY datum");
    while ($row=sql_fetch_array($res)) {
        $i=substr($row["datum"],0,16);
        $foglaltidopontok[$i]=$row;
    }

    if (!$rowmax=sql_fetch_array(sql_query("SELECT MIN(tol) as minrendeles,MAX(ig) as maxrendeles FROM orvos_beosztas WHERE helyszinid='{$helyszin}' and (cegid='{$_SESSION["helyszindata"]["id"]}') and (instr(tipusok,'|{$szurestipus}|')) HAVING MAX(tol) IS NOT NULL"))) {
        echo "<div style='margin:10px 0px;'>Erre a szűrés típusra nincsenek beállítva rendelési időpontok.</div>";
        die();
    }

    $res=sql_query("select b.*,o.nev as orvosnev from orvos_beosztas b
	left join orvosok o on o.id=b.orvosid
	where b.helyszinid='{$helyszin}' and (b.cegid='{$_SESSION["helyszindata"]["id"]}' or b.cegid=0) and (instr(b.tipusok,'|{$szurestipus}|'))");
    while ($row=sql_fetch_array($res)) {
        $beosztas[]=$row;
        $beosztasData[$row["nap"]]=$row;
        //$beosztasOrvos[$row["nap"]]=$row["orvosnev"];
    }

    echo "<div style='margin:10px 0px 10px 0px;'>";
    //echo "Itt lesz az időpontválasztó... most még csak fixen kirak pár lehetőséget.<br>";
    echo "<div>{$webText["valasszidopontot"]}:</div>";
    echo "<div style='margin-top:5px;'><a href='javascript:showIdoPontValaszto(".($honnan-7).")'>{$webText["elo7"]}</a> | <a href='javascript:showIdoPontValaszto(".($honnan+7).")'>{$webText["kov7"]}</a></div>";
    echo "<table><tr>";

    $dist="6 hour";
    if ($helyszin==1) $dist="0 hour"; //jász utca bármikor foglalható

    for ($i=0;$i<=6;$i++) {

        $nap=date("Y-m-d",mktime(0, 0, 0, date("m"), date("d")+$i+$honnan, date("Y")));
        $wd=date("N",mktime(0, 0, 0, date("m"), date("d")+$i+$honnan, date("Y")));  //day of week
        $wn=date("W",mktime(0, 0, 0, date("m"), date("d")+$i+$honnan, date("Y")));  //number of week

        echo "<td valign='top'>";
        echo "<div style='background:#0a0;padding:2px 10px 2px 10px;color:#fff;font-weight:bold;text-align:center;'>{$nap}<br>{$webText["hetnap"][$wd]}</div>";

        $beginora=round(substr($rowmax["minrendeles"],0,2));
        $beginperc=round(substr($rowmax["minrendeles"],3,2));

        if (!isset($beosztasData[$wd]["binterval"])) {
            echo "<div style='text-align:center;margin:5px;padding:5px 0px;border-radius:5px;'>{$webText["nincsrendeles"]}</div>";
            echo "</td>";
            continue;
        }

        $binterval=$beosztasData[$wd]["binterval"];
        $firstfreetime="";
        for ($o=0;$o<=55;$o++) {
            $ora=date("H:i",mktime($beginora,$beginperc+$o*$binterval,0,date("m"),date("d"),date("Y")));
            if (strtotime($ora)>=strtotime($rowmax["maxrendeles"])) break;

            echo "<div style='text-align:center;'>";

            $java="nemfog();";
            $class="foglaltbutton";

            if (isBeosztasWeekDay($beosztas,$wd,$wn) && !in_array($nap,$szunnapok)) {
                if (strtotime("now + {$dist}")<strtotime("{$nap} {$ora}")) {
                    $hanyfoglalt=0;
                    if (isset($foglaltidopontok["{$nap} {$ora}"])) $hanyfoglalt=$foglaltidopontok["{$nap} {$ora}"]["hany"];


                    if (!in_array("{$nap}",$foglaltnapok)) {
                        $szabad=isFreeIdopont($wd,$ora,$beosztas,$hanyfoglalt);
                        if ($szabad[0]==1 || $szabad[0]==2) {
                            $java="chooseIdoPont(\"{$nap} {$ora}\");return false;";
                            $class="foglalhatobutton";
                            if ($szabad[0]==2 && $firstfreetime!="") {
                                $java="nemfogs(\"{$firstfreetime}\");return false;";
                                $class.=" halv";
                            }
                            if ($firstfreetime=="") $firstfreetime=$ora;
                        }
                    }
                }
            }

            $t="";
            if ($_SESSION["helyszindata"]["id"]==15) {
                if (isset($beosztasData[$wd]["orvosnev"])) {
                    $t="title='".$beosztasData[$wd]["orvosnev"]."'";
                }
            }

            echo "<a class='{$class}' {$t} onclick='{$java}' href='#'>{$ora}</a>";
            echo "</div>";
        }

        echo "</td>";

    }

    echo "</tr></table>";
    echo "</div>";
    die();
}

function isBeosztasWeekDay($beosztas,$wd,$weekNumber=0) {
    for ($i=0;$i<count($beosztas);$i++) {
        if ($beosztas[$i]["nap"]==$wd) {
            if ($weekNumber==0) return true;
            if ($beosztas[$i]["hetek"]==2) {
                if ($weekNumber%2==0) {
                    return true;
                } else {
                    return false;
                }
            }
            if ($beosztas[$i]["hetek"]==1) {
                if ($weekNumber%2==0) {
                    return false;
                } else {
                    return true;
                }
            }
            return true;
        }
    }
    return false;
}

function isFreeIdopont($wd,$ora,$beosztas,$hanyfoglalt) {
    $szabad[0]=0;

    $dokik=0;
    for ($i=0;$i<count($beosztas);$i++) {
        $beo=$beosztas[$i];
        if ($beo["nap"]==$wd) {
            if (strtotime(date("Y-m-d {$ora}")) >= strtotime(date("Y-m-d {$beo["tol"]}")) && strtotime(date("Y-m-d {$ora}")) < strtotime(date("Y-m-d {$beo["ig"]}"))) {
                $dokik++;

                //csak sorban foglalható időpont ellenőrzése
                if ($beo["csaksorban"]==1) {
                    if (isset($GLOBALS["cs{$beo["id"]}"])) {
                        $szabad[0]=2;
                        $szabad[1]=$GLOBALS["cs{$beo["id"]}"];
                    } else {
                        $GLOBALS["cs{$beo["id"]}"]=$ora;
                    }
                }

            }
        }
    }
    if ($dokik>$hanyfoglalt) {
        if ($szabad[0]==0) $szabad[0]=1;
    } else {
        $szabad[0]=0;
    }

    return $szabad;
}


function displayFejlec($title="") {
    global $webText;
    $style="";
    if($_SESSION['helyszindata']['id'] == 91)
    {
        $img = "<img src='images/hungarian_crest.png' height='30' />";
    }
    else $img = "";

    if ($_SESSION["helyszindata"]["fejleccolor"]!="") $style.="background:{$_SESSION["helyszindata"]["fejleccolor"]};";


    return "<div class='fejlecdiv' style='{$style}'>{$img} {$_SESSION["helyszindata"]["megnev"]} - {$webText["idopontfoglalas"]}".($title!=""?" - {$title}":"")."</div>";
}


function szurestipusvalaszto($helyszinid,$selected=0,$onlyselected=0) {
    $tipusok=array();

    $rest=sql_query("select * from szurestipusok");
    while ($rowt=sql_fetch_array($rest)) {
        $tipusnevek[$rowt["id"]]=$rowt["megnev"];
    }

    $addJava="";
    if ($_SESSION["helyszindata"]["id"]==11) {
        $addJava="if (this.value==1) { $(\"#fogleuwarn\").show(); } else { $(\"#fogleuwarn\").hide(); }";
    }


    $htmlout="";
    $htmlout.="<select name='szurestipus' id='szurestipus' onchange='clearIdopontValaszto();showTipusMegj(this.value);{$addJava}'>";
    $htmlout.="<option value='0'>{$webText["valasszon"]}!</option>";

    /*
    $res=sql_query("SELECT t.* FROM orvos_beosztas b
        LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
        LEFT JOIN szurestipusok t ON t.`id`=o.`tipusid`
        WHERE b.helyszinid='".addslashes($helyszinid)."'  AND b.cegid='{$_SESSION["helyszindata"]["id"]}' AND t.`megnev` IS NOT NULL
        GROUP BY t.`id`");


    if ($onlyselected==0) $htmlout.="<option value='0'>Válassz!</option>";
    while ($rowt=sql_fetch_array($res)) {
        $tipusok[]=$rowt["id"];
        //$htmlout.="<option value='{$rowt["id"]}'".($selected==$rowt["id"]?" selected":"").">{$rowt["megnev"]}</option>";
    }
    */
    $res=sql_query("SELECT tipusok FROM orvos_beosztas b WHERE b.helyszinid='".addslashes($helyszinid)."' AND b.cegid='{$_SESSION["helyszindata"]["id"]}'");
    while ($row=sql_fetch_array($res)) {
        $ta=explode("|",$row["tipusok"]);
        for ($i=0;$i<count($ta);$i++) {
            if (trim($ta[$i])!="" && !in_array($ta[$i],$tipusok)) {
                $tipusok[]=$ta[$i];
            }
        }
    }

    if (isset($tipusok)) {
        for ($i=0;$i<count($tipusok);$i++) {
            @$tipusdisplay[$tipusok[$i]]=$tipusnevek[$tipusok[$i]];
        }
        if (isset($tipusdisplay)) {
            asort($tipusdisplay);
            foreach ($tipusdisplay as $key => $value) {
                if ($onlyselected==1 && $key!=$selected) continue;
                if (trim($value)=="") continue;
                $htmlout.="<option value='{$key}'".($selected==$key?" selected":"").">{$value}</option>";
            }
        }
    }

    $htmlout.="</select>";
    return $htmlout;
}

function szuresTipusValasztoNewV2($helyszinid, $selected = NULL, $onlyselected = NULL ) {
    $tipusok = array();

    $rest = sql_query("SELECT * FROM szurestipusok");
    while ( $rowt = sql_fetch_array( $rest )) {
        $tipusnevek[$rowt["id"]] = $rowt["megnev"];
    }

    $addJava = "";
    if ($_SESSION["helyszindata"]["id"] == 11) {
        $addJava = "if (this.value==1) { $(\"#fogleuwarn\").show(); } else { $(\"#fogleuwarn\").hide(); }";
    }

    $htmlout = '';
    $htmlout.= '<SELECT name = "szurestipus" class = "design-put" id = "szurestipus">';
    $htmlout.= '<option value = "0"> - Válassz Szűrést! - </option>';
    $res = sql_query( "SELECT tipusok FROM orvos_beosztas b 
					   WHERE b.helyszinid = '".addslashes($helyszinid)."' AND b.cegid = '11' " );

    while ( $row = sql_fetch_array( $res )) {
        $ta = explode( "|", $row["tipusok"] );

        for ( $i = 0; $i < count( $ta ); $i++ ) {
            if ( trim($ta[$i] ) != "" && !in_array( $ta[$i], $tipusok )) {
                $tipusok[] = $ta[$i];
            }
        }
    }

    if ( isset( $tipusok )) {
        for ( $i = 0; $i < count( $tipusok ); $i++ ) {
            @$tipusdisplay[$tipusok[$i]] = $tipusnevek[$tipusok[$i]];
        }
        if ( isset ($tipusdisplay )) {

            asort( $tipusdisplay );
            foreach ( $tipusdisplay as $key => $value ) {
                //if (count($tipusdisplay)==1) $selected=$key;
                if ( $onlyselected == 1 && $key != $selected ) continue;
                if ( trim( $value ) == "") continue;
                if( $key == 1 ) continue;
                $htmlout.= "<option value = '".$key."' ".($selected == $key ? "selected" : "" ).">".$value."</option>";
            }
        }
    }

    $htmlout.= "</select>";

    if ( trim( $helyszinid ) == "" || $helyszinid == 0 ) $htmlout = "Válassz előbb helyszínt!<input type = 'hidden' name = 'szurestipus' value = '' />";

    return $htmlout;
}





function getTipusMegj($cegid,$tid,$helyszinId=1) {
    $h="";
    if ($row=sql_fetch_array(sql_query("select * from szurestipusok_megj where cegid='".intval($cegid)."' and tipusid='".intval($tid)."' and csomag=0"))) {
        if (trim($row["megj"])!="") $h.="<div style='background:#f00;color:#fff;padding:10px;display:inline-block;font-weight:bold;'>".trim($row["megj"])."</div>";
    }


    $res=sql_query("SELECT o.* FROM orvos_beosztas b 
	LEFT JOIN orvosok o ON o.id=b.`orvosid`
	WHERE cegid=? AND INSTR(b.`tipusok`,'|".intval($tid)."|') AND o.`tel`<>'' and o.telpublic=1 and b.helyszinid=?
	GROUP BY b.`orvosid`",array($cegid,$helyszinId));

    if (sql_num_rows($res)>0) {
        $h.="<div style='margin:10px 0px;'>";
        $h.="<div style='font-weight:bold;'>Elérhetőségek:</div>";
        while ($row=sql_fetch_array($res)) {
            $h.="<div>Telefonos időpontfoglalás: {$row["tel"]}</div>";
        }
        $h.="</div>";
    }

    if ($helyszinId==1 && $_SERVER["REMOTE_ADDR"]=="88.151.97.121") {
        $res=sql_query("select * from arak where instr(cegid,?) and tipusid=? and trim(megnev)<>'' and csomag=0",array("|{$cegid}|",$tid));
        if (sql_num_rows($res)>0) {
            $h.="<div style='margin:10px 0px;'>";
            $h.="<div style='font-weight:bold;'>Ha kér, válasszon kiegészítő szolgáltatást:</div>";
            while ($row=sql_fetch_array($res)) {
                //if ($_COOKIE["lang"]!="hu" && trim($row["megnev_{$_COOKIE["lang"]}"])!="") $row["megnev"]=$row["megnev_{$_COOKIE["lang"]}"];
                $h.="<div><input type='checkbox' name='altipus{$row["id"]}' value='1' ".(isset($_POST["altipus{$row["id"]}"])?"checked":"")." /> {$row["megnev"]}</div>";
            }
            $h.="</div>";
        }
    }
    if($_SESSION['helyszindata']['tudoszuroopcio'] == 1 && $helyszinId == 1 && $tid == 1)
    {
        $h.= "<div><input type='checkbox' name = 'tudoszuro' value = '1' />Tüdőszűrővel nem rendelkezik</div>";
    }
    return $h;
}


if (isset($_POST["gettipusmegj"])) {
    echo getTipusMegj($_SESSION["helyszindata"]["id"],$_POST["tid"],$_POST["hid"]);
    die();
}



if (isset($_GET["setbeutalo"])) {
    if ($row=sql_fetch_array(sql_query("select * from beutalok where id='".intval($_GET["setbeutalo"])."' and userid='".intval($_SESSION["user"]["id"])."'"))) {
        $_SESSION["beutaloid"]=$row["id"];
    }

    header("location:index.php?page=main");
    die();
}


if (isset($_POST["requestsmskod"])) {
    $taj=$_POST["taj"];
    $taj=str_replace("-","",$taj);
    $taj=trim(str_replace(" ","",$taj));

    if ($_POST["captcha"]!=$_SESSION["captcha"]) {
        echo "A beírt szám nem egyezik!";
        die();
    }

    if ($taj=="") {
        echo "A TAJ szám megadása kötelező!";
        die();
    }
    if (!ctype_digit($taj) && $taj!="") {
        echo "A TAJ szám formátuma nem megfelelő!";
        die();
    }

    if (!$rowu=sql_fetch_array(sql_query("select f.*,UNIX_TIMESTAMP()-UNIX_TIMESTAMP(rkoddatum) as rkodsec from felhasznalok f where taj='".addslashes($taj)."' and cegid='{$_SESSION["helyszindata"]["id"]}'"))) {
        echo "A megadott TAJ számmal nem található felhasználó!";
        die();
    }

    if ($rowu["rkodsec"]<600 && $rowu["rkodsec"]!=NULL) {
        echo "sentback";
        die();
    }

    //kód generálása és kiküldése:
    $rn=rand(11000,98000);
    sql_query("update felhasznalok set rkod='{$rn}',rkoddatum=now() where id='{$rowu["id"]}'");
    sendLoginSMSKod($rowu["id"]);

    echo "sentnow";
    die();
}


if (isset($_POST["logintrywithtaj"])) {
    $taj=$_POST["taj"];
    $taj=str_replace("-","",$taj);
    $taj=trim(str_replace(" ","",$taj));

    if ($taj=="") {
        echo "A TAJ szám megadása kötelező!";
        die();
    }
    if (!ctype_digit($taj) && $taj!="") {
        echo "A TAJ szám formátuma nem megfelelő!";
        die();
    }


    if ($rowu=sql_fetch_array(sql_query("select * from felhasznalok where taj='".addslashes($taj)."' and rkod='".intval($_POST["kod"])."' and cegid='".addslashes($_SESSION["helyszindata"]["id"])."'"))) {

        if (strtotime("now")-strtotime($rowu["rkoddatum"])>600) {
            echo "lejartkod";
            die();
        }

        $_SESSION["loggeduser"]=$rowu["id"];
        echo "ok";
    } else {
        echo "A megadott TAJ szám, vagy kód nem megfelelő!";
    }
    die();
}



if (isset($_POST["adduserbeutalo"])) {
    if (isset($_SESSION["user"]["id"])) {

        $data=explode("-",$_POST["beutalotarget"]);
        $hid=intval($data[0]);
        $sztid=intval($data[1]);

        sql_query("insert into beutalok set datum=now(),selfcreated=1,userid=?,cegid=?,helyszinid=?,szurestipusid=?,naploszam=?,megj=?",array($_SESSION["user"]["id"],$_SESSION["helyszindata"]["id"],$hid,$sztid,$_POST["naploszam"],$_POST["beutalomegj"]));
    }

    header("location:index.php?page=beutalok");
    die();

}


if (isset($_GET["delbeutalo"])) {
    sql_query("delete from beutalok where id=? and userid=?",array($_GET["delbeutalo"],$_SESSION["user"]["id"]));
    header("location:index.php?page=beutalok");
    die();

}




function importAudiTorzs() {
    $csvContent=file_get_contents("/root/torzsadatok_201702.csv");

    //echo $csvContent;

    $sorok=explode("\n",$csvContent);


    for ($i=1;$i<count($sorok)-1;$i++) {
        $mezok=explode(";",$sorok[$i]);
        echo "sor{$i}: {$mezok[0]}\n";

        $cim=$mezok[7]." ".$mezok[8]." ".$mezok[9];
        if (trim($mezok[10])!="") $cim.=", épület: {$mezok[10]}";
        if (trim($mezok[11])!="") $cim.=", emelet: {$mezok[11]}";


        //if ($rowt=sql_fetch_array(sql_query("select id,paciensid from audi_torzs where paciensid=?",array($mezok[0])))) {
        //$mezok[]=$rowt["id"];
        //sql_query("update audi_torzs set paciensid=?,nev=?,allamp=?,leanykori_nev=?,anyja_neve=?,szdatum=?,nem=?,taj=?,torzsszam=?,irszam=?,telepules=?,cim=?,email=?,telefon=? where id=?",$mezok);
        //} else {
        sql_query("insert into audi_torzs set paciensid=?,nev=?,allamp=?,leanykori_nev=?,anyja_neve=?,szdatum=?,nem=?,taj=?,torzsszam=?,irszam=?,telepules=?,cim=?,email=?,telefon=?",
            array($mezok[0],$mezok[1],"","",$mezok[12],$mezok[2],$mezok[13],$mezok[3],$mezok[0],$mezok[5],$mezok[6],$cim,"",""));
        //}
    }


}

//törölhető, használd helyette a DocAgent osztályt.
function get_Doc_Path($fileid) {
    $path="./doc/".floor($fileid/1000);
    if (!is_dir($path)) mkdir($path);
    $path.="/{$fileid}.bin";
    return $path;
}



if (isset($_REQUEST["addpaciensfiles"])) {
    if (!isset($_SESSION["filefix"])) $_SESSION["filefix"]=rand(10000,99999);
    $fileFix=$_SESSION["filefix"];

    $docAgent = new DocAgent();

    foreach($_FILES as $file) {
        $sess=$fileFix.session_id();
        $result = $docAgent->saveDoc($file, array('beutaloid' => 0, 'userid' => 0, 'megnev' => $_POST["dokmegnev"],'sess' => $sess));

        if ($result != "0") {
            echo $result;
            die;
        }
    }
    die();
}


function showPaciensFiles() {
    $htmlout="";
    if (isset($_SESSION["filefix"])) {
        $htmlout.="<div style='margin:5px 0px;'>";
        $res=sql_query("select * from dokumentumok where sess=?",array($_SESSION["filefix"].session_id()));
        //if (sql_num_rows($res)==0) $htmlout.="Az adminisztráció megkönnyítése érdekében a beutaló itt feltölthető";
        while ($row=sql_fetch_array($res)) {
            $htmlout.="<div><div style='display:table-cell;vertical-align:middle;'><a href='#' onclick='deletePaciensDoc({$row["id"]},\"{$row["kod"]}\");return false;'><img style='margin-right:5px;' src='/images/trash.png' /></a></div><div style='display:table-cell;vertical-align:middle;'>{$row["filename"]}</div></div>";
        }
        $htmlout.="</div>";
    }
    return $htmlout;
}


if (isset($_GET["showpaciensfiles"])) {
    echo showPaciensFiles();
    die();
}


if (isset($_POST["deletepaciensdoc"])) {
    $docAgent = new DocAgent();
    $docAgent->deleteDoc($_POST["id"], $_POST["k"]);
    echo showPaciensFiles();
    die();
}


function cimLangQuery($fieldName="cim") {
    $q="h.cim AS {$fieldName}";
    if (isset($_COOKIE["lang"]) && in_array($_COOKIE["lang"],array("en","de"))) {
        $q="IF(h.cim_{$_COOKIE["lang"]}='',h.cim,h.cim_{$_COOKIE["lang"]}) AS {$fieldName}";
    }
    return $q;
}


if(isset($_REQUEST['load_lelet'])){
    $lelet_id = $_REQUEST['load_lelet'];
    $request_lelet = sql_query("SELECT * FROM paciens_leletek WHERE lelet_id = ? AND paciens_id = ? ",array($lelet_id,$_SESSION['user']['id']));
    $result = sql_fetch_array($request_lelet);
    ?>
    <div class = "lelet-frame" id = "lelet-content" style = "display:block;overflow-y:scroll"><?php echo $result['lelet_szoveg'] ?></div>
    <div class = "lelet-button-box" style = "margin-top:10px;">
        <input class = "user-button" onClick = 'printLelet();' type = "button" value = "Nyomtatás" />
        <input class = "user-button" onClick = '$(".target-lelet").slideToggle();setTimeout(function(){$(".target-lelet").empty();}, 1000);' type = "button" value = "Bezárás" />
    </div>
    <?php
    die();
}

if(isset($_REQUEST['load_zaro'])){
    $request = sql_fetch_array("SELECT * FROM zaro_leletek zl
								LEFT JOIN paciens_leletek pl ON pl.zaro_id = zl.zaro_id
								LEFT JOIN felhasznalok felh ON felh.paciens_id = pl.paciens_id
								WHERE felh.id=? AND pl.zaro_id=?",array($_SESSION['user']['id'],$_REQUEST['load_zaro']));
    //$zaro_id = $_REQUEST['load_zaro'];
    //$request_zaro = sql_query("SELECT * FROM zaro_leletek WHERE zaro_id = ? AND patient_id = ? ",array($zaro_id, $_SESSION['loggeduser']));
    $result = sql_fetch_array($request);
    ?>
    <div class = "lelet-frame" id = "lelet-content" style = "display:block;overflow-y:scroll"><?php echo $result['zaro_szoveg'] ?></div>
    <div class = "lelet-button-box" style = "margin-top:10px;">
        <input class = "user-button" onClick = 'printLelet();' type = "button" value = "Nyomtatás" />
        <input class = "user-button" onClick = '$(".target-lelet").slideToggle();setTimeout(function(){$(".target-lelet").empty();}, 1000);' type = "button" value = "Bezárás" />
    </div>
    <?php
    die();
}


function getWeeks( $date, $rollover ) {

    $cut = substr( $date, 0, 8 );
    $daylen = 86400;

    $timestamp 	= strtotime( $date );
    $first 		= strtotime( $cut . "00" );
    $elapsed	= ( $timestamp - $first ) / $daylen;

    $weeks = 1;

    for ( $i = 1; $i <= $elapsed; $i++ )
    {
        $dayfind 	  = $cut.( strlen( $i ) < 2 ? '0' . $i : $i);
        $daytimestamp = strtotime( $dayfind );

        $day = strtolower( date( "l", $daytimestamp ));

        if( $day == strtolower( $rollover ))  $weeks ++;
    }

    return $weeks;
}

function next_alk( $cegid ){

    /*//Email készítése:
    include_once( "phpmailer/class.phpmailer.php" );
    $mail = new PHPMailer();
    $mail->From 	= "noreply@hungariamed.hu";
    $mail->FromName	= "Hungariamed";
    $mail->AddAddress( "m.gergely9409@gmail.com" );
    $mail->AddReplyTo( "noreply@hungariamed.hu" );
    $mail->IsHTML( true );

    $t = iconv( "UTF-8", "ISO-8859-2", "Alkalmassági vizsgálat érvényessége hamarosan lejár." );

    $mbody = "Kedves Márton Gergely,<br/>";
    $mbody.= "Az alkalmassági vizsgálata érvényessége 1 hónap múlva elévül,<br/> kérjük az alábbi linken foglaljon időpontot az alkalmassági megújításához.<br/>";
    $mbody.= "Link: <a href='https://bp.hungariamed.hu/'>https://bp.hungariamed.hu/</a>";

    $mail->Subject = $t;
    $mail->Body = iconv( "UTF-8", "ISO-8859-2", $mbody );
    //$mail->AddAttachment("");
    $mail->Send();*/

    $request = sql_query("SELECT alkalmassagido,datum, DATE_ADD(datum, INTERVAL alkalmassagido MONTH) AS köv_alkalom,alkalmassagikhet,alkalmassag,email,nev
					      FROM foglalasok 
						  WHERE eljott = 1
						  AND cegid = '".$cegid."'
						  AND email != ''
						  AND alkalmassag IN( 'I', 'K' ) ");

    while( $result = sql_fetch_array( $request )) {

        if( $result['alkalmassagido'] != "" && date( "Y-m-d", strtotime( $result['köv_alkalom'] ) != date( "Y-m-d", strtotime( date( "Now" )." + 1 month" ))))
        {
            continue;
        }

        //Email készítése:
        include_once( "phpmailer/class.phpmailer.php" );
        $mail = new PHPMailer();
        $mail->From 	= "noreply@hungariamed.hu";
        $mail->FromName	= "Hungariamed";
        $mail->AddAddress( $result['email'] );
        $mail->AddReplyTo( "noreply@hungariamed.hu" );
        $mail->IsHTML( true );

        $t = iconv( "UTF-8", "ISO-8859-2", "Alkalmassági vizsgálat érvényessége hamarosan lejár." );

        $mbody = "Kedves ".$result['nev'].',<br/>';
        $mbody.= "Az alkalmassági vizsgálat érvényessége 1 hónap múlva elévül,<br/> kérjük az alábbi linken foglaljon időpontot az alkalmassági megújításához.";
        $mbody.="Link: <a href='https://bp.hungariamed.hu/'>https://bp.hungariamed.hu/</a>";

        $mail->Subject = $t;
        $mail->Body = iconv( "UTF-8", "ISO-8859-2", $mbody );
        //$mail->AddAttachment("");
        $mail->Send();
    }
}
function kuponCheck($coupon,$version,$foglalas,$szurestipus)
{
    $query = sql_query("SELECT * FROM kuponkodok WHERE kod = ? AND statusz = 'aktiv' AND event_end >= '{$foglalas}' AND event_start <= '{$foglalas}' ", array( $coupon ));

    if($query->rowCount() > 0)
    {
        $result = sql_fetch_array($query);
        $szurestipusok = explode("|",$result['szurestipusok']);

        $data = $result['megnev']."|".$result['leiras'];
        if($version == 2 || $version == 1) $data.="|".$result['kedvezmeny'].($result['kedvezmeny_tipus'] =="szazalek"?"%":"Ft");
        if($result['tipus'] == "Egyszer")
        {
            $query = sql_query("SELECT * FROM kupon_lista WHERE kuponkod = '{$coupon}' ");
            if($query->rowCount() != 0)
            {
                $data = "error02";
            }
        }
        if(!in_array($szurestipus,$szurestipusok)) $data = "error03";
        if($version == 3 && $data != "error02" && $data != "error03") $data = "usable";
    }
    else{
        $data = "error01";
    }

    return $data;
}

if(isset($_POST['kuponCheck']))
{
    echo kuponCheck($_POST['coupon'],$_POST['version'],$_POST['foglalas'],$_POST['szurestipus']);
    die();
}

if( isset( $_POST['callRequestSending'] )) {
    callBack( $_POST['nev'], $_POST['tel'], $_POST['megj'] );
}

function callBack( $nev, $phone, $megj )
{
    include_once("phpmailer/class.phpmailer.php");
    $mail = new PHPMailer();
    $mail->From="noreply@hungariamed.hu";
    $mail->FromName="Hungariamed";
    $mail->AddAddress( "recepcio@hungariamed.hu" );
    $mail->AddAddress( "m.gergely9409@gmail.com" );
    $mail->AddAddress( "sorger.eva@hungariamed.hu" );
    $mail->AddAddress( "bejelentkezes@hungariamed.hu" );
    $mail->AddReplyTo( "noreply@hungariamed.hu" );
    $mail->IsHTML( true );

    $subject = iconv( "UTF-8", "ISO-8859-2", "Visszahívás kérelem" );
    $mailTxt = "Az alábbi ügyfél visszahívást kért:<br/><br/>";
    $mailTxt.= "  Név: {$nev}<br/>";
    $mailTxt.= " Tel.: {$phone}<br/>";
    $mailTxt.= "Megj.: {$megj}";

    $mail->Subject=$subject;
    $mail->Body=iconv("UTF-8","ISO-8859-2",$mailTxt);
    if( $mail->Send() )
    {
        header("Location:index.php?page=kerelem_elkuldve");
    }
}

function ENS( $companies )
{
    foreach( $companies as $company )
    {
        $query = sql_query( "SELECT felh.alklejarat,felh.nev,c.domain,felh.taj,felh.email AS umail,felh.id AS userid, felh.hrmail 
							 FROM felhasznalok felh
							 LEFT JOIN cegek c ON c.id = felh.cegid
							 WHERE cegid = {$company}
							 AND felh.alklejarat >= NOW() AND felh.alklejarat < ADDDATE(NOW(),14)
							 AND CASE WHEN felh.lastalkert IS NOT NULL 
							 THEN felh.lastalkert NOT BETWEEN ADDDATE(NOW(),-14) AND ADDDATE(NOW(),14) 
							 ELSE TRUE 
							 END");

        while( $result = sql_fetch_array( $query ))
        {
            $checkFoglalas = sql_query("SELECT * FROM foglalasok 
										WHERE email = '{$result['umail']}' 
										AND   taj 	= '{$result['taj']}' 
										AND   datum >= NOW() AND datum < ADDDATE(NOW(),14)");

            if( $checkFoglalas->rowCount() == 0 )
            {
                include_once("phpmailer/class.phpmailer.php");
                $mail = new PHPMailer();
                $mail->From="noreply@hungariamed.hu";
                $mail->FromName="Hungariamed";
                $mail->AddAddress(iconv("UTF-8","ISO-8859-2",$result['umail']));
                if($result['hrmail'] != "") $mail->AddAddress(iconv("UTF-8","ISO-8859-2",$result['hrmail']));
                $mail->AddReplyTo("noreply@hungariamed.hu");
                $mail->IsHTML(true);

                $t=iconv("UTF-8","ISO-8859-2","Orvosi alkalmassági vizsgálata hamarosan lejár!");

                $mbody = "Kedves {$result['nev']},<br/>";
                $mbody.= "Az orvosi alkalmassági vizsgálata hamarosan lejár!<br/>";
                $mbody.= "Lejárat dátuma: ".date("Y.m.d",strtotime($result['alklejarat']))."<br/>";
                $mbody.= "Kérem foglaljon időpontot honlapunkon:<br/>";
                $mbody.= "<a href='https://".$result['domain'].".hungariamed.hu'>https://".$result['domain'].".hungariamed.hu</a><br/>";
                $mbody.= "Tisztelettel,<br/>";
                $mbody.= "Hungária Med - M.kft";

                $mail->Subject=$t;
                $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
                //$mail->AddAttachment("");
                if(!$mail->Send())
                {
                    sql_query("INSERT INTO alkert_mail SET nev = '{$result['nev']}', email = '{$result['umail']}', eredmeny = '{$mail->ErrorInfo}', datum = NOW() ");
                }
                else
                {
                    sql_query("INSERT INTO alkert_mail SET nev = '{$result['nev']}', email = '{$result['umail']}', eredmeny = 'elkuldve', datum = NOW() ");
                    sql_query("UPDATE felhasznalok SET lastalkert = NOW() WHERE id = {$result['userid']} ");
                }
            }
        }
    }
}

function send_alkExcel( $cegid, $intvallType, $mails ) {

    $rowCount = 2;
    $SendingDayParameters = array( "1", "2", "3", "4", "5", "6", "7" );
    if( $intvallType == "napi" && in_array( date( "N" ), $SendingDayParameters )) {
        $intervall = "fogl.datum ";
        //$intervall.= "LIKE '2018-10-01%' ";
        $intervall.= "LIKE '".date("Y-m-d")."%' ";
        $releaseDate = date("Y-m-d");
        //$releaseDate = "2018-11-05";
    }

    if( $intvallType == "heti" && date( "N" ) == 3 ) {

        $intervall = "fogl.datum ";
        $intervall.= "BETWEEN '".date( "Y-m-d", strtotime( date( "Y-m-d" )." -4 day" ))."' ";
        $intervall.= "AND     '".date( "Y-m-d", strtotime( date( "Y-m-d")." +1 day" ))."' ";
        $releaseDate = date( "Y-m" )." ".getWeeks( date( "Y-m-d" ), "sunday" ).". hét";
    }
    if( $intvallType == "havi" && date( "j" ) == 1 ) {

        $intervall = "fogl.datum ";
        $intervall.= "BETWEEN '".date( "Y-m-d", strtotime( date( "Y-m-d" )." -1 month" ))."' ";
        $intervall.= "AND     '".date( "Y-m-d", strtotime( date( "Y-m-d" )." -1 day" ))."' ";
        $releaseDate = date("Y-m");
    }

    //Ha nem lehetett definiálni az intervallumot szakítsa meg a kódot.
    if( !isset( $intervall )) return;

    require_once( "admin/Classes/PHPExcel.php" );

    $filename = $releaseDate." napi riport";
    $objPHPExcel = new PHPExcel();
    $objPHPExcel->setActiveSheetIndex(0);
    $objPHPExcel->getActiveSheet()->setTitle('Napi lista');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
    header('Cache-Control: max-age=0');

    //Lekérdezés
    $request = sql_query("SELECT fogl.*, doc.nev as orvos FROM foglalasok fogl
						    LEFT JOIN orvosok doc ON doc.id = fogl.orvosassigned
						    WHERE ".$intervall."
						    AND fogl.cegid = ? ", array(  $cegid ));



    //Oszlop nevek:
    $objPHPExcel->getActiveSheet()->SetCellValue('A1', "Név");
    $objPHPExcel->getActiveSheet()->SetCellValue('B1', "Szül. dátum");
    $objPHPExcel->getActiveSheet()->SetCellValue('C1', "TAJ");
    $objPHPExcel->getActiveSheet()->SetCellValue('D1', "Törzsszám");
    $objPHPExcel->getActiveSheet()->SetCellValue('E1', "Munkakör");
    $objPHPExcel->getActiveSheet()->SetCellValue('F1', "Orvos");
    $objPHPExcel->getActiveSheet()->SetCellValue('G1', "Vizsgálat dátuma");
    $objPHPExcel->getActiveSheet()->SetCellValue('H1', "Elvégzett vizsgálatok");
    $objPHPExcel->getActiveSheet()->SetCellValue('I1', "Alkalmassági státusz");
    $objPHPExcel->getActiveSheet()->SetCellValue('J1', "Alkalmassági idő");
    $objPHPExcel->getActiveSheet()->SetCellValue('K1', "Következő vizsg.");
    $objPHPExcel->getActiveSheet()->SetCellValue('L1', "Korlátozás/Megjegyzés");


    while( $result = sql_fetch_array( $request )) {

        //Extra vizsgálatok listázáa:
        $request_extra = sql_query("SELECT a.megnev FROM extra_szolg es
									LEFT JOIN arak a ON a.id = es.szurestipus_id
									WHERE idopont_id = ".$result['id']);

        $extrak = "";
        while($extra = sql_fetch_array( $request_extra ))
        {
            $extrak = $extrak.", ".$extra['megnev'];
        }
        if( $extrak != "" ) $extrak = substr( $extrak, 1 );

        //Ciklus változók:
        $status 	= "";
        $period 	= "";
        $limitation = "";
        $next_test  = "";

        if( $result['alkalmassag'] == "I" ) {
            $status 	= "Alkalmas";
            $period 	= $result['alkalmassagido']." hónap";
            $next_test 	= date("Y-m-d",strtotime($result['datum']." +".$result['alkalmassagido']." month"));
            $limitation = $result['alkalmassagkorl'];
        }
        if( $result['alkalmassag'] == "N" ) {
            $status 	= "Alkalmatlan";
            $period 	= "";
            $next_test 	= "";
            $limitation = $result['alkalmassagkorl'];
        }
        if( $result['alkalmassag'] == "IN" ) {
            $status 	= "Ideiglenesen nem alkalmas";
            $period 	= $result['alkalmassagido']." hónap";
            $next_test 	= $result['alkalmassagikhet']." hét";
            $limitation = $result['alkalmassagkorl'];
        }
        if( $result['alkalmassag'] == "K" ) {
            $status 	= "Korlátozottan alkalmas";
            $period 	= $result['alkalmassagido']." hónap";
            $next_test 	= date( "Y-m-d", strtotime( $result['datum']." +".$result['alkalmassagido']." month" ));
            $limitation = $result['alkalmassagkorl'];
        }
        if( $result['alkalmassak'] == "" && $result['alkalmassagkorl'] != "" ) {
            $limitation = $result['alkalmassagkorl'];
        }

        //Excel adatsorok:
        $objPHPExcel->getActiveSheet()->SetCellValue('A'.$rowCount, $result['nev']);
        $objPHPExcel->getActiveSheet()->SetCellValue('B'.$rowCount, $result['szuldatum']);
        $objPHPExcel->getActiveSheet()->SetCellValue('C'.$rowCount, $result['taj']);
        $objPHPExcel->getActiveSheet()->SetCellValue('D'.$rowCount, $result['torzsszam']);
        $objPHPExcel->getActiveSheet()->SetCellValue('E'.$rowCount, $result['munkakor']);
        $objPHPExcel->getActiveSheet()->SetCellValue('F'.$rowCount, $result['orvos']);
        $objPHPExcel->getActiveSheet()->SetCellValue('G'.$rowCount, $result['datum']);
        $objPHPExcel->getActiveSheet()->SetCellValue('H'.$rowCount, $extrak);
        $objPHPExcel->getActiveSheet()->SetCellValue('I'.$rowCount, $status);
        $objPHPExcel->getActiveSheet()->SetCellValue('J'.$rowCount, $period);
        $objPHPExcel->getActiveSheet()->SetCellValue('K'.$rowCount, $next_test);
        $objPHPExcel->getActiveSheet()->SetCellValue('L'.$rowCount, $limitation);

        $rowCount++;
    }

    //Fájl véglegesítése:
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    ob_start();
    $objWriter->save('php://output');
    $xlsData = ob_get_contents();
    $contact_image_data="data:application/vnd.ms-excel;base64,".base64_encode( $xlsData );
    $data 		= substr( $contact_image_data, strpos( $contact_image_data, "," ));
    $encoding 	= "base64";
    $type 		= "application/vnd.ms-excel";
    ob_end_clean();

    //Email(ek) készítése:
    include_once( "phpmailer/class.phpmailer.php" );
    $mail = new PHPMailer();
    $mail->From 	= "noreply@hungariamed.hu";
    $mail->FromName	= "Hungariamed";
    $mail->AddAddress( "m.gergely9409@gmail.com" );
    foreach($mails as $email)
    {
        $mail->AddAddress($email, $email);
    }
    $mail->AddReplyTo( "noreply@hungariamed.hu" );
    $mail->AddStringAttachment( base64_decode( $data ), $filename.".xlsx", $encoding, $type );
    $mail->IsHTML( true );

    $t = iconv( "UTF-8", "ISO-8859-2", $releaseDate." napi riport" );

    $mbody = " ";

    $mail->Subject = $t;
    $mail->Body = iconv( "UTF-8", "ISO-8859-2", $mbody );
    //$mail->AddAttachment("");
    $mail->Send();
}

//IDŐPONTFOGLALÓ V2.0
$bookingSetup = new Booking_Setup();

//Vizsgálatok betöltése:

function loadExaminations( $helyszinid, $cegid, $preSelect = NULL )
{
    //Szűréstípusok megnevezése, id-val párosítva:
    $request = sql_query( "SELECT id, megnev FROM szurestipusok" );
    $examName = array();
    while( $result = sql_fetch_array( $request )) $examName[$result['id']] = $result;

    //Adott helyszínen rendelkezésre álló vizsgálatok dekódolása SQL adatbázisból:
    $request_vizsgalatok = sql_query( "SELECT tipusok FROM orvos_beosztas WHERE helyszinid = {$helyszinid} AND cegid = {$cegid} AND aktiv = 1  group by tipusok" );
    $szuresid = array();
    while( $result = sql_fetch_array( $request_vizsgalatok ))
    {
        $current = str_replace( "||", ",", $result['tipusok'] );
        $current = substr( $current, 1, -1 );
        $tipusok = explode( ",", $current );
        $tipusok = array_filter( $tipusok, 'strlen' );
        foreach( $tipusok as $tipus ) $szuresid[] = $tipus;
    }
    $Examinations = array();
    $szuresid = array_unique( $szuresid );
    foreach( $szuresid as $vizsgalat )
    {
        $Examinations[] = array( "id" => $vizsgalat, "megnev" => $examName[$vizsgalat]['megnev'] );
    }
    array_multisort( array_column( $Examinations, "megnev"), SORT_ASC, $Examinations );

    $output = "<div style = 'text-align:left;padding-left:5px' class = 'locked'><h3 style = 'text-align:left' class = 'ds_title locked'>Vizsgálataink:</h3></div>";

    foreach($Examinations as $key => $value)
    {
        if($Examinations[$key]['id'] == 1) continue;
        if($preSelect != NULL && $Examinations[$key]['id'] == $preSelect) $class = "class = 'examination_object_active'";
        else $class = "class = 'examination_object'";
        $output.= "<div id = '{$Examinations[$key]['id']}' onClick = 'chooseExamination({$Examinations[$key]['id']})' {$class}>{$Examinations[$key]['megnev']}</div>";
    }
    return $output;
}
if( isset( $_POST['loadExaminations'] ))
{
    die( loadExaminations( $_POST['loadExaminations'], $_POST['company'] ));
}

//Orvosok betöltése:
function loadDoctors( $szurestipusid )
{
    $currentWeekMonday = date ( "Y-m-d", strtotime( "monday this week" ));
    $lastDay = date( "Y-m-d", strtotime( $currentWeekMonday." + 3 weeks" ));

    $request = sql_query("SELECT o.id,o.nev FROM orvos_beosztas beo
						  LEFT JOIN orvosok o ON o.id = beo.orvosid
						  WHERE beo.helyszinid = 1
						  AND (beo.nap < 10 || beonap BETWEEN '{$currentWeekMonday}%' AND '{$lastDay}%')
						  AND beo.tipusok LIKE '%|{$szurestipusid}|%' 
						  AND beo.aktiv = 1 
						  GROUP BY beo.orvosid
						  ORDER BY o.nev");

    $output = "";
    $count = 0;
    if( sql_num_rows( $request ) > 0 )
    {
        while( $result = sql_fetch_array( $request ))
        {
            $count++;
            //$setupOption.= "<option value = '{$result['id']}'>{$result['nev']}</option>";
            $output.= mtrObj( $result['nev'], $result['id'], "Szakorovos", "images/kinga-profil.jpg",5);
        }
    }
    else $output = "Sajnos jelenleg nincsen orvosunk ilyen típusú vizsgálatra, további információért kérem hívja telefon számunkat!";

    return $output;
}
if( isset( $_POST['loadDoctors'] ))
{
    die(loadDoctors($_POST['loadDoctors']));
}

//Vizsgálati típusok betöltése:

function loadExamTypes( $szurestipusid, $doctor )
{
    if($doctor == "menedzser") $doctor = 117;
    //Vizsgálat típusok lekérdezése:
    //$request = sql_query("SELECT * FROM vizsgalattipusok WHERE szurestipusid = {$_POST['loadExamTypes']} ");
    $vizsgalatok = 	sql_fetch_array( sql_query( "SELECT vizsgalattipusok FROM orvosok WHERE id = {$doctor}" ));
    $vizsgalatok = substr( $vizsgalatok['vizsgalattipusok'], 1, -1 );
    $vizsgalatok = str_replace( "||", ",", $vizsgalatok );
    //$vizsgalat = explode( "||", $vizsgalatok );
    $listname = "Vizsgálat típusa:";
    if( $doctor == 117 )
    {
        $listname = "Neme:";
        $output = '<div style = "font-family:Montserrat;font-size:16px;max-width:980px">';
        $output.= '<div style = "text-align:left;padding-left:5px" class = "locked"><h3 style = "text-align:left" class = "ds_title locked">Leírás:</h3></div>';
        $output.= 'A menedzser, illetve komplex szűrővizsgálatok reggeli kezdéssel indulnak. ';
        $output.= 'A vizsgálatokra, kérjük, éhgyomorral, és telt hólyaggal érkezzenek. A klinikán kollégáink folyamatosan segítik pácienseinket, ';
        $output.= 'hogy a vizsgálatokra mindig a megfelelő időben érkezzenek. ';
        $output.= 'A labor vizsgálatok után szendvics reggelit biztosítunk a recepción. ';
        $output.= 'A szűrővizsgálat komplexitása miatt a vizsgálatok maximális ideje a 4 órát is megközelítheti.<br/>';
        $output.= '<a href= \"https://www.hungariamed.hu/szurovizsgalatok/menedzserszures-top\" target = \"_blank\" >További információ</a>';
        $output.= '</div><br/>';
    }
    else $output = "";
    if( $vizsgalatok != "" )
    {
        $request = sql_query( "SELECT * FROM vizsgalattipusok WHERE id IN({$vizsgalatok})" );
        $output.= "<div style = 'display:inline-block;font-family:Montserrat;font-size:16px;'>{$listname}&nbsp;</div>";
        $output.= "<select class = 'design-put' id = 'types' style = 'width:inherit;' onChange = 'setAssignment()' >";
        $output.= "<option value = '0'> - Válassz! - </option>";

        while( $result = sql_fetch_array( $request )) $output.= "<option value = '{$result['id']}' >{$result['megnev']}</option>";
        $output.= "</select>";
    }
    else $output = "";

    return $output;
}
if( isset($_POST['loadExamTypes']) )
{
    die(loadExamTypes( $_POST['loadExamTypes'], $_POST['doc'] ));
}

//Beosztás betöltése:
if( isset( $_POST['loadAssignment'] ))
{
    if( ($_POST['type'] != "" || $_POST['type'] != 0 ) && $_POST['doc'] != "menedzser" && $_POST['doc'] != "placeholder" )
    {
        $request = sql_query("SELECT * FROM orvosok WHERE id = {$_POST['doc']} AND vizsgalattipusok like '%|{$_POST['type']}|%' ");
        if( sql_num_rows( $request ) == 0 )
        {
            $output = "Sajnos jelenleg nincsen orvosunk ilyen típusú vizsgálatra, további információért kérem hívja telefon számunkat!";
            die( $output );
        }
    }

    if( is_numeric( $_POST['type'] ))
    {
        $result = sql_fetch_array(sql_query("SELECT function FROM vizsgalattipusok WHERE id = '{$_POST['type']}'"));
        $function = $result['function'];
    }
    else $function = "onetime";


    $specialExam = "";
    if( $_POST['doc'] == "menedzser" )
    {
        if( $function == "nogyogy")   $specialExam = '11';
        if( $function == "urologia" ) $specialExam = "12";
    }

    $menedzser     = false;
    $csomag		   = false;
    $selected_exam = "";
    if($_POST['exam'] == 6)
    {
        $menedzser     = true;
        $selected_exam = 6;
        $_POST['exam'] = "6,9,48,58,15,13,8,14,{$specialExam}";
    }
    if($_POST['exam'] == 34)
    {
        $menedzser     = true;
        $selected_exam = 34;
        $_POST['exam'] = "34,9,48,58,15,13,8,14,{$specialExam}";
    }
    if($_POST['exam'] == 35)
    {
        $menedzser     = true;
        $selected_exam = 35;
        $_POST['exam'] = "35,9,48,58,15,10,13,8,14,{$specialExam}";
    }
    if($_POST['exam'] == 99)
    {
        $csomag 	   = true;
        $selected_exam = 99;
        $_POST['exam'] = "9,15,48,10,14,99";
    }
    //Inicializálás:
    $bookingSetup = new Booking_Setup();
    $iMap 	  	  = $bookingSetup->AI1_Assignment_Processing( $_POST['loc'], $_POST['comp'] );
    $assignment   = $bookingSetup->Mutli_Time_Tagger( $iMap, $_POST['exam'], $_POST['loc'] );

    //Ha üzemorvosi vizsgálatra kell időpontokat össze állítani:
    if( $menedzser == true || $csomag == true )
    {
        $assignment = $bookingSetup->Multi_Time_Selector( $assignment );
    }

    $dayColor 	  = array( "#dc0707;", "#c80707;", "#b40707;", "#a00707;", "#960707;","#8c0707;", "#820707;" );

    //Ciklus változók inicializálása:
    $output    	  = "";
    $maxLength	  = 0;
    $wIndex 	  = 0;
    //Havi beosztás felosztása hetekre:
    $output.= "<table class = ' locked' style = 'width:1022px;'>";
    $output.= "<tr>";
    $output.= "		<td colspan = '2'><div style = 'float:left' onClick = 'switch_assignment(\"week-0\",\"previous\")' id = 'prev_paging' class = 'ds_paging locked'><i style = 'margin-left:-2px' class = 'fa fa-chevron-left'></i></div></td>";
    $output.= "		<td colspan = '3'><div class = 'ds_title_container locked'><h3 class = 'ds_title locked'>Időpontjaink</h3></div></td>";
    $output.= "		<td colspan = '2'><div style = 'float:right' onClick = 'switch_assignment(\"week-1\",\"next\")' id = 'next_paging' class = 'ds_paging locked'><i class='fa fa-chevron-right'></i></div></td>";
    $output.= "</tr>";
    $output.= "</table>";
    foreach ( $iMap as $week => $day )
    {
        $wIndex++;
        $dayColumns = array();
        $output.= "<table class = 'design-schedule' id = 'week-{$wIndex}' style='".( $wIndex > 1? ";display:none" : "" )."' >";
        $output.= "<thead>";
        $output.= "<tr>";

        //Első sor Napok:
        foreach ( $iMap[$week] as $dayDate => $day )
        {
            $output.= "<td style = 'padding:5px'>";
            $output.= "<div class = 'ds_date_box locked' style = 'background-color:{$dayColor[( date( "N", strtotime( $dayDate )) - 1 )]}'>";
            $output.= "		<div class = 'ds_day_name'>";
            $output.= "			{$bookingSetup->dayName[( date( "N", strtotime( $dayDate )) - 1 )]}";
            $output.= "		</div>";
            $output.= "		<div class = 'ds_day_date locked'>";
            $output.= "			".date( "Y.m.d.", strtotime( $dayDate ));
            $output.= "		</div>";
            $output.= "</div>";
            $output.= "</td>";


            //Vizsgálom a leghosszabb napot, hogy a további oszlopok hosszát ehhez mérten állítsam:
            if( isset( $day[$_POST['exam']][$_POST['doc']] ) && $maxLength < count( $day[$_POST['exam']][$_POST['doc']] ))
            {
                $maxLength = count( $day[$_POST['exam']][$_POST['doc']] );
            }

            //kigyűjtöm a napok dátumát és a rendelési időket egy segéd tömbbe:
            $dayColumns[] = array( "date" => $dayDate );
        }

        $output.= "<tr/>";
        $output.= "</thead>";
        $output.= "<tbody>";
        //Egy vizsgálatra történő foglalás:
        if( $menedzser == false && $csomag == false )
        {
            //Napokhoz tartozó dátumok:
            for( $row = 0; $row < $maxLength; $row++ )
            {
                $output.= "<tr>";
                //Max 7 oszlopot tud le generálni, azon belül vizsgálja a tömbben rögztített időpontokat és megjeleníti:
                for ( $cell = 0; $cell <= 6; $cell++ )
                {
                    $result = sql_fetch_array(sql_query("SELECT * FROM orvos_beosztas 
														 WHERE orvosid = {$_POST['doc']} 
														 AND tipusok LIKE '|{$_POST['exam']}|'
														 AND (nap = ".( $cell + 1 )." OR beonap = '{$dayColumns[$cell]['date']}') "));
                    $dayColumns[$cell] = array("date" => $dayColumns[$cell]['date'], "order_time" => $result['binterval']);
                    //Létre kell hoznom kettő funkciót, az egyik a normál időpontok megjelenítését fogja mutatni, míg a másik a speciális feltételeknek megfelelőt:

                    if( isset( $iMap[$week][$dayColumns[$cell]["date"]][$_POST['exam']][$_POST['doc']][$row] ))
                    {
                        $TimeArray = $iMap[$week][$dayColumns[$cell]["date"]][$_POST['exam']][$_POST['doc']];
                        $onClick     = "";
                        $outputTime  = "";
                        $currentTime = $TimeArray[$row];

                        if( isset( $TimeArray[($row - 1)] )) $prevTime = $TimeArray[($row - 1)];
                        else $prevTime = "";

                        if( isset( $TimeArray[($row + 1)] )) $nextTime = $TimeArray[($row + 1)];
                        else $nextTime = "";

                        $fullTime = $dayColumns[$cell]["date"]." ".$TimeArray[$row].":00";
                        //Ha a sub tömb nem is létezik a szabad időpontok között nem kell be menni a belső ellenőrzésbe:
                        if( isset( $assignment[$week][$dayColumns[$cell]["date"]][$_POST['exam']][$_POST['doc']] ))
                        {
                            //Ha létezik, meg vizsgáljuk miket tartalmaz, hogy ehhez mérten beállítsuk a foglalható időpontokat:
                            $key = array_search( $currentTime, $assignment[$week][$dayColumns[$cell]["date"]][$_POST['exam']][$_POST['doc']] );

                            //Ha levan fogalalva, vagy a jelenhez képeset 12 órán belül van akkor rá fut erre a részre, és foglaltra teszi:
                            if( $key === FALSE || strtotime( $dayColumns[$cell]["date"]." ".$currentTime ) < strtotime( "now + 12 hours" ))
                            {
                                $class = "class = 'ds_date_reserved locked'";
                                $onClick = "";
                            }
                            else
                            {
                                $class   = "class = 'ds_date_free locked'";
                                $onClick = "onClick = 'chooseTime({$_POST['exam']},{$_POST['doc']},\"{$fullTime}\",\"\")'";
                            }
                        }
                        else{
                            $class   = "class = 'ds_date_reserved locked'";
                            $onClick = "";
                        }

                        //Kiírom az időpontot ui felületre:
                        if( $function == "doubletime" )
                        {
                            if( !isset( $usedTimes[$dayColumns[$cell]["date"]] )) $usedTimes[$dayColumns[$cell]["date"]] = array();
                            $comboTime = $currentTime;
                            $different = "";
                            if( $nextTime != "" )
                            {
                                $different = ((strtotime($nextTime)-strtotime($currentTime))/60);
                                $checkingCurrentTime = array_search( $currentTime, $usedTimes[$dayColumns[$cell]["date"]] );
                                if( $checkingCurrentTime === FALSE && $class != "class = 'ds_date_reserved locked'" && $different == $dayColumns[$cell]['order_time'])
                                {
                                    $comboTime = $currentTime."~".$nextTime;
                                    $fullNextTime = $dayColumns[$cell]['date']." ".$nextTime.":00";
                                    $onClick = "onClick = 'chooseTime({$_POST['exam']},{$_POST['doc']},\"{$fullTime}\", \"{$fullNextTime}\")'";
                                    $usedTimes[$dayColumns[$cell]["date"]][] = $nextTime;
                                }
                                else{
                                    $class   = "class = 'ds_date_reserved locked'";
                                    $onClick = "";
                                }
                            }
                            else{
                                $class   = "class = 'ds_date_reserved locked'";
                                $onClick = "";
                            }

                            if( is_numeric( $checkingCurrentTime ) && $nextTime!= "" ) $comboTime = "";

                            $output.="<td><div {$class} {$onClick}>{$comboTime}</div></td>";

                        }
                        if( $function == "onetime" ) $output.= "<td><div {$class} {$onClick}>{$currentTime}</div></td>";
                    }
                    else $output.= "<td><div class = 'ds_date_reserved locked'></div></td>";
                }
                $output.= "</tr>";
            }
        }
        if( $menedzser == true )
        {
            $output.= "<tr>";
            //Max 7 oszlopot tud le generálni, azon belül vizsgálja a tömbben rögztített időpontokat és megjeleníti:
            for ( $cell = 0; $cell <= 6; $cell++ )
            {
                $fixTime = "08:00~12:00";
                if(isset( $assignment[$week][$dayColumns[$cell]["date"]] ) || isset( $iMap[$week][$dayColumns[$cell]["date"]][$selected_exam] ))
                {
                    if( strtotime( $dayColumns[$cell]["date"] ) > strtotime( "now + 12 hours" ) && isset( $assignment[$week][$dayColumns[$cell]["date"]] ))
                    {
                        $vArray = "var vizsgalat = [";
                        $oArray = "var orvos = [";
                        $tArray = "var idopont = [";
                        foreach($assignment[$week][$dayColumns[$cell]["date"]] as $vizsgalat => $inessential)
                        {

                            $vArray.= $vizsgalat.", ";
                            foreach($assignment[$week][$dayColumns[$cell]["date"]][$vizsgalat] as $orvos => $inessential)
                            {
                                $oArray  .= $orvos.", ";
                                $fullTime = $assignment[$week][$dayColumns[$cell]["date"]][$vizsgalat][$orvos].":00";
                                $tArray  .= "\"".$fullTime."\", ";
                                $output  .= "<input type = 'hidden' name = '{$vizsgalat}-{$orvos}' value = '{$fullTime}' />";
                            }
                        }
                        $vArray  = substr( $vArray, 0, -2 );
                        $vArray .= "];";
                        $oArray  = substr( $oArray, 0, -2 );
                        $oArray .= "];";
                        $tArray  = substr( $tArray, 0, -2 );
                        $tArray .= "];";
                        $onClick = "onClick = '{$vArray}{$oArray}{$tArray}chooseMenedzser(vizsgalat,orvos,idopont,\"{$dayColumns[$cell]['date']}\",{$selected_exam})'";
                        $output .= "<td><div class = 'ds_date_free locked' {$onClick} >{$fixTime}</div></td>";
                    }
                    else $output.= "<td><div class = 'ds_date_reserved locked'>{$fixTime}</div></td>";
                }
                else $output.= "<td><div class = 'ds_date_reserved locked'></div></td>";
            }
            $output.= "</tr>";
        }
        if( $csomag == true )
        {
            $output.= "<tr>";
            //Max 7 oszlopot tud le generálni, azon belül vizsgálja a tömbben rögztített időpontokat és megjeleníti:
            for ( $cell = 0; $cell <= 6; $cell++ )
            {
                $fixTime = "08:00~12:00";
                if(isset( $assignment[$week][$dayColumns[$cell]["date"]] ) || isset( $iMap[$week][$dayColumns[$cell]["date"]][$selected_exam] ))
                {
                    if( strtotime( $dayColumns[$cell]["date"] ) > strtotime( "now + 12 hours" ) && isset( $assignment[$week][$dayColumns[$cell]["date"]] ))
                    {
                        $vArray = "var vizsgalat = [";
                        $oArray = "var orvos = [";
                        $tArray = "var idopont = [";
                        foreach($assignment[$week][$dayColumns[$cell]["date"]] as $vizsgalat => $inessential)
                        {

                            $vArray.= $vizsgalat.", ";
                            foreach($assignment[$week][$dayColumns[$cell]["date"]][$vizsgalat] as $orvos => $inessential)
                            {
                                $oArray  .= $orvos.", ";
                                $fullTime = $assignment[$week][$dayColumns[$cell]["date"]][$vizsgalat][$orvos].":00";
                                $tArray  .= "\"".$fullTime."\", ";
                                $output  .= "<input type = 'hidden' name = '{$vizsgalat}-{$orvos}' value = '{$fullTime}' />";
                            }
                        }
                        $vArray  = substr( $vArray, 0, -2 );
                        $vArray .= "];";
                        $oArray  = substr( $oArray, 0, -2 );
                        $oArray .= "];";
                        $tArray  = substr( $tArray, 0, -2 );
                        $tArray .= "];";
                        $onClick = "onClick = '{$vArray}{$oArray}{$tArray}chooseCsomag(vizsgalat,orvos,idopont,\"{$dayColumns[$cell]['date']}\",{$selected_exam})'";
                        $output .= "<td><div class = 'ds_date_free locked' {$onClick} >{$fixTime}</div></td>";
                    }
                    else $output.= "<td><div class = 'ds_date_reserved locked'>{$fixTime}</div></td>";
                }
                else $output.= "<td><div class = 'ds_date_reserved locked'></div></td>";
            }
            $output.= "</tr>";
        }
        $output.= "</tbody>";
        $output.= "</table>";
    }
    die($output);
}

function createReservation( $randomId, $szurestipusid, $orvosid, $idopont, $idopont2, $cegid, $helyszinid, $vizsgalattipusid = NULL )
{
    $vizsgalat = sql_fetch_array(sql_query("SELECT megnev FROM szurestipusok WHERE id = {$szurestipusid}"));
    $orvos = sql_fetch_array(sql_query("SELECT nev FROM orvosok WHERE id = {$orvosid}"));
    $datum = date("Y.m.d H:i", strtotime($idopont));
    if($idopont2 != false || (isset($idopont2) && $idopont2 != ""))
    {
        $hourMinute = date("H:i", strtotime($idopont2));
        $datum.= "~".$hourMinute;
    }
    $onClick = "onClick = 'removeTime($(this).parents(\":eq(1)\").attr(\"id\"))'";
    //$onClick = "onClick = '$(this).parents(\":eq(1)\").fadeToggle(function(){ $(this).remove(); });";
    //$onClick.= "var counter=$(\"#selectedTimes\").val();counter--;$(\"#selectedTimes\").val(counter);";
    //$onClick.= "if($(\"#selectedTimes\").val() == 0) $(\"#finisher-button\").css(\"display\",\"none\")'";
    $output = "";
    $output.= "<div id = 'selected-time-{$randomId}'>";
    $output.= "		<div>";
    $output.= "			<div style = 'cursor:default' class = 'examination_object'>{$vizsgalat['megnev']}</div>";
    $output.= "			<i class = 'fa fa-chevron-right'></i>";
    $output.= "			<div style = 'cursor:default' class = 'examination_object'>{$orvos['nev']}</div>";
    $output.= "			<i class = 'fa fa-chevron-right'></i>";
    $output.= "			<div style = 'cursor:default' class = 'examination_object'>{$datum}</div>";
    $output.= "			<div class = 'ds_paging removeIcon' {$onClick} ><i style = 'margin-top: 7px' class='fas fa-times'></i></div>";
    $output.= "		</div>";
    $output.= "		<input type = 'hidden' name = 'cegid[]' 		value = '{$cegid}' />";
    $output.= "		<input type = 'hidden' name = 'helyszinid[]' 	value = '{$helyszinid}' />";
    $output.= "		<input type = 'hidden' name = 'szurestipusid[]' value = '{$szurestipusid}' />";
    $output.= "		<input type = 'hidden' name = 'orvosid[]' 		value = '{$orvosid}' />";
    $output.= "		<input type = 'hidden' name = 'idopont[]' 		value = '{$idopont}' />";
    $output.= "		<input type = 'hidden' name = 'tipusid[]'		value = '{$vizsgalattipusid}' />";
    if( $idopont2 != false )
    {
        $output.= "		<input type = 'hidden' name = 'cegid[]' 		value = '{$cegid}' />";
        $output.= "		<input type = 'hidden' name = 'helyszinid[]' 	value = '{$helyszinid}' />";
        $output.= "		<input type = 'hidden' name = 'szurestipusid[]' value = '{$szurestipusid}' />";
        $output.= "		<input type = 'hidden' name = 'orvosid[]' 		value = '{$orvosid}' />";
        $output.= "		<input type = 'hidden' name = 'idopont[]' 		value = '{$idopont2}' />";
        $output.= "		<input type = 'hidden' name = 'tipusid[]'		value = '{$vizsgalattipusid}' />";
    }
    $output.= "</div>";
    return $output;
}

if( isset( $_POST['createReservation'] ))
{
    $randomId = mt_rand( 1, 1000 );

    $_SESSION['reservations'][] = array( 		   "obj-id" => $randomId,
        "szurestipusid" => $_POST['szurestipusid'],
        "orvosid" => $_POST['orvosid'],
        "idopont" => $_POST['idopont'],
        "idopont2" => $_POST['idopont2'],
        "cegid" => $_POST['cegid'],
        "helyszinid" => $_POST['helyszinid'],
        "vizsgalattipusid" => $_POST['vizsgalattipusid']);

    die( createReservation( $randomId, $_POST['szurestipusid'], $_POST['orvosid'], $_POST['idopont'], $_POST['idopont2'], $_POST['cegid'], $_POST['helyszinid'], $_POST['vizsgalattipusid'] ));
}

function checkSelectedTimes($resErr)
{
    if( isset( $_SESSION['reservations'] ) && count( $_SESSION['reservations'] ) > 0 ) $normalReservations = count($_SESSION['reservations']);
    else $normalReservations = 0;
    if( isset( $_SESSION['reservations-manager'] ) && count( $_SESSION['reservations-manager'] ) > 0 ) $managerReservations = count($_SESSION['reservations-manager']);
    else $managerReservations = 0;
    if( isset( $_SESSION['csomag-reservation'] ) && count( $_SESSION['csomag-reservation'] ) > 0 ) $csomagReservation = count($_SESSION['csomag-reservation']);
    else $csomagReservation = 0;
    $sum =  ( $normalReservations + $managerReservations + $csomagReservation );
    if($sum != 0) return $sum;
    else return false;

}

function reloadSelectedTimes($resErr)
{
    $output = "";
    $deletableKey = "";
    if( isset( $_SESSION['reservations'] ) && count( $_SESSION['reservations'] ) > 0 )
    {
        //$return = "itt vagyok!";
        //return $return;
        if( $resErr != 0 )
        {

            foreach( $_SESSION['reservations'] as $key => $value )
            {
                $deletableKey = array_search( $key, $resErr );
                if( $deletableKey !== FALSE )
                {
                    unset( $_SESSION['reservations'][$key] );
                }
            }
            array_values( $_SESSION['reservations'] );
        }

        foreach( $_SESSION['reservations'] as $key => $value )
        {

            $output.= createReservation( $_SESSION['reservations'][$key]['obj-id'],
                $_SESSION['reservations'][$key]['szurestipusid'],
                $_SESSION['reservations'][$key]['orvosid'],
                $_SESSION['reservations'][$key]['idopont'],
                $_SESSION['reservations'][$key]['idopont2'],
                $_SESSION['reservations'][$key]['cegid'],
                $_SESSION['reservations'][$key]['helyszinid'],
                $_SESSION['reservations'][$key]['vizsgalattipusid']);
        }
    }

    if( isset( $_SESSION['reservations-manager'] ) && count( $_SESSION['reservations-manager'] ) > 0 )
    {
        foreach( $_SESSION['reservations-manager'] as $key => $value )
        {
            $output.= createMenedzser( $_SESSION['reservations-manager'][$key]['obj-id'],
                $_SESSION['reservations-manager'][$key]['orvosok'],
                $_SESSION['reservations-manager'][$key]['szurestipusok'],
                $_SESSION['reservations-manager'][$key]['idopontok'],
                $_SESSION['reservations-manager'][$key]['menedzserid'],
                $_SESSION['reservations-manager'][$key]['displayTime'],
                $_SESSION['reservations-manager'][$key]['cegid'],
                $_SESSION['reservations-manager'][$key]['helyszinid']);
        }
    }

    if( isset( $_SESSION['csomag-reservation'] ) && count( $_SESSION['csomag-reservation'] ) > 0 )
    {
        foreach( $_SESSION['csomag-reservation'] as $key => $value )
        {
            $output.= createCsomag( $_SESSION['csomag-reservation'][$key]['obj-id'],
                $_SESSION['csomag-reservation'][$key]['orvosok'],
                $_SESSION['csomag-reservation'][$key]['szurestipusok'],
                $_SESSION['csomag-reservation'][$key]['idopontok'],
                $_SESSION['csomag-reservation'][$key]['csomagid'],
                $_SESSION['csomag-reservation'][$key]['displayTime'],
                $_SESSION['csomag-reservation'][$key]['cegid'],
                $_SESSION['csomag-reservation'][$key]['helyszinid']);
        }
    }

    if( $output != "" ) return $output;
    else return false;

}
if(isset($_POST['removeSessionData']))
{
    $_POST['removeSessionData'] = explode( "-", $_POST['removeSessionData'] );
    $objId = $_POST['removeSessionData'][2];
    $key01 = array_search( $objId, array_column( $_SESSION['reservations'], 'obj-id' ));
    $key02 = array_search( $objId, array_column( $_SESSION['reservations-manager'], 'obj-id' ));
    $key03 = array_search( $objId, array_column( $_SESSION['csomag-reservation'], 'obj-id' ));

    if($key01 !== FALSE)
    {
        unset( $_SESSION['reservations'][$key01] );
        $_SESSION['reservations'] = array_values($_SESSION['reservations']);
    }

    if($key02 !== FALSE)
    {
        unset( $_SESSION['reservations-manager'][$key02] );
        $_SESSION['reservations-manager'] = array_values($_SESSION['reservations-manager']);
    }

    if($key03 !== FALSE)
    {
        unset( $_SESSION['csomag-reservation'][$key03] );
        $_SESSION['csomag-reservation'] = array_values($_SESSION['csomag-reservation']);
    }
    die();

}

function createMenedzser( $randomId, $orvosok, $szurestipusok, $idopontok, $menedzserid, $displayTime, $cegid, $helyszinid )
{
    $orvosid 	     = json_decode( stripslashes( $orvosok ));
    $szurestipusid   = json_decode( stripslashes( $szurestipusok ));
    $idopont 	     = json_decode( stripslashes( $idopontok ));

    if( $menedzserid == 6  ) $mendezsertitle = "Alap";
    if( $menedzserid == 34 ) $mendezsertitle = "Emelt";
    if( $menedzserid == 35 ) $mendezsertitle = "Top";

    $datum   = date( "Y.m.d", strtotime( $displayTime ));
    $datum  .= "&nbsp;08:00~12:00";
    $onClick = "onClick = 'removeTime($(this).parents(\":eq(1)\").attr(\"id\"))'";
    //$onClick = "onClick = '$(this).parents(\":eq(1)\").fadeToggle(function(){ $(this).remove(); });";
    //$onClick.= "var counter=$(\"#selectedTimes\").val();counter--;$(\"#selectedTimes\").val(counter);";
    //$onClick.= "if($(\"#selectedTimes\").val() == 0) $(\"#finisher-button\").css(\"display\",\"none\")'";
    $output = "";
    $output.= "<div id = 'selected-time-{$randomId}'>";
    $output.= "		<div>";
    $output.= "			<div style = 'cursor:default' class = 'examination_object'>Menedzserszűrés</div>";
    $output.= "			<i class = 'fa fa-chevron-right'></i>";
    $output.= "			<div style = 'cursor:default' class = 'examination_object'>{$mendezsertitle}</div>";
    $output.= "			<i class = 'fa fa-chevron-right'></i>";
    $output.= "			<div style = 'cursor:default' class = 'examination_object'>{$datum}</div>";
    $output.= "			<div class = 'ds_paging removeIcon' {$onClick}><i style = 'margin-top: 7px' class='fas fa-times'></i></div>";
    $output.= "		</div>";
    for($i = 0; $i< count( $orvosid ); $i++)
    {
        $output.= "		<input type = 'hidden' name = 'cegid[]' 		   value = '{$cegid}' />";
        $output.= "		<input type = 'hidden' name = 'helyszinid[]' 	   value = '{$helyszinid}' />";
        $output.= "		<input type = 'hidden' name = 'szurestipusid[]'    value = '{$szurestipusid[$i]}' />";
        $output.= "		<input type = 'hidden' name = 'orvosid[]' 		   value = '{$orvosid[$i]}' />";
        $output.= "		<input type = 'hidden' name = 'idopont[]' 		   value = '{$idopont[$i]}' />";
        $output.= "		<input type = 'hidden' name = 'vizsgalattipusid[]' value = '{$mendezsertitle} '/>";
    }
    $output.= "</div>";
    return $output;
}

if( isset( $_POST['createMenedzser'] ))
{
    $randomId = mt_rand( 1, 1000 );

    $_SESSION['reservations-manager'][] = array( "obj-id" 		=> $randomId,
        "orvosok" 		=> $_POST['orvosok'],
        "szurestipusok" => $_POST['szurestipusok'],
        "idopontok"   	=> $_POST['idopontok'],
        "menedzserid" 	=> $_POST['menedzserid'],
        "displayTime" 	=> $_POST['displayTime'],
        "cegid" 	  	=> $_POST['cegid'],
        "helyszinid"  	=> $_POST['helyszinid']);

    die( createMenedzser( $randomId, $_POST['orvosok'], $_POST['szurestipusok'], $_POST['idopontok'], $_POST['menedzserid'], $_POST['displayTime'], $_POST['cegid'], $_POST['helyszinid'] ));
}

function createCsomag( $randomId, $orvosok, $szurestipusok, $idopontok, $csomagid, $displayTime, $cegid, $helyszinid )
{
    $orvosid 	     = json_decode( stripslashes( $orvosok ));
    $szurestipusid   = json_decode( stripslashes( $szurestipusok ));
    $idopont 	     = json_decode( stripslashes( $idopontok ));

    $result = sql_fetch_array( sql_query( "SELECT * FROM szurestipusok WHERE id = ?", array( $csomagid )));
    $csomagtitle = $result['megnev'];

    $datum   = date( "Y.m.d", strtotime( $displayTime ));
    $datum  .= "&nbsp;08:00~12:00";
    $onClick = "onClick = 'removeTime($(this).parents(\":eq(1)\").attr(\"id\"))'";

    $output = "";
    $output.= "<div id = 'selected-time-{$randomId}'>";
    $output.= "		<div>";
    $output.= "			<div style = 'cursor:default' class = 'examination_object'>Szűrőcsomag</div>";
    $output.= "			<i class = 'fa fa-chevron-right'></i>";
    $output.= "			<div style = 'cursor:default' class = 'examination_object'>{$csomagtitle}</div>";
    $output.= "			<i class = 'fa fa-chevron-right'></i>";
    $output.= "			<div style = 'cursor:default' class = 'examination_object'>{$datum}</div>";
    $output.= "			<div class = 'ds_paging removeIcon' {$onClick}><i style = 'margin-top: 7px' class='fas fa-times'></i></div>";
    $output.= "		</div>";
    for($i = 0; $i< count( $orvosid ); $i++)
    {
        $output.= "		<input type = 'hidden' name = 'cegid[]' 		   value = '{$cegid}' />";
        $output.= "		<input type = 'hidden' name = 'helyszinid[]' 	   value = '{$helyszinid}' />";
        $output.= "		<input type = 'hidden' name = 'szurestipusid[]'    value = '{$szurestipusid[$i]}' />";
        $output.= "		<input type = 'hidden' name = 'orvosid[]' 		   value = '{$orvosid[$i]}' />";
        $output.= "		<input type = 'hidden' name = 'idopont[]' 		   value = '{$idopont[$i]}' />";
        $output.= "		<input type = 'hidden' name = 'vizsgalattipusid[]' value = '{$csomagtitle} '/>";
    }
    $output.= "</div>";
    return $output;
}

if( isset( $_POST['createCsomag'] ))
{
    $randomId = mt_rand( 1, 1000 );

    $_SESSION['csomag-reservation'][] = array(  "obj-id" 		=> $randomId,
        "orvosok" 		=> $_POST['orvosok'],
        "szurestipusok" => $_POST['szurestipusok'],
        "idopontok"   	=> $_POST['idopontok'],
        "csomagid" 		=> $_POST['csomagid'],
        "displayTime" 	=> $_POST['displayTime'],
        "cegid" 	  	=> $_POST['cegid'],
        "helyszinid"  	=> $_POST['helyszinid']);

    die( createCsomag( $randomId, $_POST['orvosok'], $_POST['szurestipusok'], $_POST['idopontok'], $_POST['csomagid'], $_POST['displayTime'], $_POST['cegid'], $_POST['helyszinid'] ));
}



function mtrObj( $name, $id, $profession, $profile_img_URL, $marginRight = NULL )
{
    if($marginRight != NULL) $marginRight = "style = 'margin-right:{$marginRight}px'";

    $htmlout = "<script>
				$('.medic-tag-rectangle').click(function(){
					
					$('.medic-tag-rectangle').each(function() {
					  $(this).data('tagged',false);
					  $(this).css({'background-color':'white','color':'#444'});
					});
					
					if($(this).data('tagged'))
					{
						if($(this).data('tagged') == false)
						{
							$(this).data('tagged',true);
							$(this).css({'background-color':'#9d0102','color':'white'});
							$('#doctor').val($(this).attr('id'));
							chooseType();
						}
						if($(this).data('tagged') == true)
						{
							$(this).data('tagged',false);
							$(this).css({'background-color':'white','color':'#444'});
						}
					}
					else
					{
						$(this).data('tagged',true);
						$(this).css({'background-color':'#9d0102','color':'white'});
						$('#doctor').val($(this).attr('id'));
						chooseType();
					}
					
				}).children('.mtr-more-info-tag').click(function(e) {
					window.open('https://www.hungariamed.hu/rolunk/munkatarsaink');
					return false;
				});
				
				$('.medic-tag-rectangle').hover(function () 
				{
					$(this).find('.mtr-more-info-tag').css({'width':'30px'});
					$(this).css({'width':'210px'});
					if($(this).data('tagged')  && $(this).data('tagged') == true) return false;
					$(this).css({'background-color':'gray','color':'white'});
					
				}, function() {
					$(this).find('.mtr-more-info-tag').css({'width':'0px'});
					$(this).css({'width':'180px'});
					if($(this).data('tagged')  && $(this).data('tagged') == true) return false;
					$(this).css({'background-color':'white','color':'#444'});
				});</script>";

    //$img = "https://static.foglaljorvost.hu/Thumbnails/dr-csanady-kinga-borgyogyasz-4806.w@250.h@250.q70.jpg?ver=2.43.8";
    $img = "images/Icon-Placeholder-150x150.png";
    $htmlout.= "<div class = 'medic-tag-rectangle' id = '{$id}' {$marginRight}>";
    $htmlout.= "	<div class = 'mtr-profile-img-wrapper' style = 'text-align:center;line-height:50px;'>";
    $htmlout.= "		<i style = 'font-size:30px;margin-top:8px;color:black !important' class='fas fa-user-md'></i>";
    //$htmlout.= "		<img src = '{$img}' style = 'width:50px' />";
    $htmlout.= "	</div>";
    $htmlout.= "	<div class = 'mtr-text-wrapper'>";
    $htmlout.= "		<span style = 'font-size:13px'>{$name}</span>";
    $htmlout.= "		<span style = 'font-size:12px;margin-top:2px;display:inline-block'>{$profession}</span>";
    $htmlout.= "	</div>";
    $htmlout.= "	<div class = 'mtr-more-info-tag'>";
    $htmlout.= "		<a id = 'newpage' href = '#' target='_blank'><i class='fas fa-info-circle mtr-tag-md-icon'></i></a>";
    $htmlout.= "	</div>";
    $htmlout.= "</div>";

    return $htmlout;
}

function confirmationMailSend($type, $foglid)
{
    //A foglid működik, letárolja a foglalás azonosítókat

    //Szűrésvizsgálatok adatainak kinyerése:
    $szurestipusok = array();
    $request 	   = sql_query( "SELECT * FROM szurestipusok" );
    while( $result = sql_fetch_array( $request )) $szurestipusok[$result['id']] = $result;


    //Cél: Levél küldése a páciens email címre amit a foglalásban megadott,
    //tartalmazza csomagban szereplő vizsgálatok neveit, a vizsgálat dátumát és, hogy hány órára mennyen a rendelőbe.
    if( $type == "menedzser" )
    {
        $text = "";
        $menedzserid = array(6,34,35);
        $ExamList = "";
        $getID = "";
        $getRK = "";
        foreach( $foglid as $id)
        {
            $foglalas = sql_fetch_array( sql_query( "SELECT * FROM foglalasok WHERE id = ?", array( $id )));
            $getID.= $id."XO";
            $getRK.= $foglalas['rkod']."XE";
            //Individuális adatok kinyerése az első foglalás információiból:
            if( !isset( $email )) $email = $foglalas['email'];
            if( !isset( $nev )) $nev = $foglalas['nev'];
            if( !isset( $datum )) $datum = date("Y.m.d",strtotime($foglalas['datum']));

            $key = array_search( $foglalas['szurestipusid'], $menedzserid );
            //Vizsgálatok nevéből készítek egy listát, a levél tartalmába helyezem majd:
            if($key === FALSE)
            {
                $ExamList.= $szurestipusok[$foglalas['szurestipusid']]['megnev'].", <br>";
            }
            else{
                //Ha Megtaláltam a menedzser vizsgálatát, akkor betudom állítani a fő vizsgálat nevét:
                if( !isset( $menedzsertitle )) $menedzsertitle = $szurestipusok[$foglalas['szurestipusid']]['megnev'];
            }
        }

        $ExamList = substr($ExamList, 0, -6);
        $getID    = substr($getID, 0, -2);
        $getRK    = substr($getRK, 0, -2);
        $URL = "http://".$_SERVER["HTTP_HOST"]."/index.php?page=megerosites&type=multiple&id={$getID}&rk={$getRK}";

        //Levél szöveg:
        $text.= "<p style = 'font-family:Calibri;font-size:14px;'>Kedves {$nev},<br/><br/>";
        $text.= "{$menedzsertitle} foglalását rögzítettük rendszerünkben. <br/>";
        $text.= "<strong>Az időpontfoglalást meg kell erősítenie</strong> még, <strong>különben a foglalástól tekintve 1 óra elteltével töröljük</strong> azt.";
        $text.= "<br/><br/>";
        $text.= "<strong>Időpont:</strong> {$datum} 08:00~12:00<br/>";
        $text.= "<a style = 'display:inline-block;cursor:pointer;padding:10px;margin:5px;color:#fff;font-size:16px;background-color:#dc0707;border:1px solid #dc0707;text-decoration:none;' href = '{$URL}'>Megerősítés</a>";
        $text.= "<br/><br/>";
        $text.= "A csomagban szereplő vizsgálatok a következők:<br/>";
        $text.= $ExamList."<br/>";
        $text.= "<br/>";
        $text.= "Üdvözlettel:<br/><br/>";
        $text.= "<img src = 'cid:logo' /><br/><br/>";
        $text.= "<strong>Elérhetőségeink:</strong><br/>";
        $text.= "Email: <a style = 'color:#a90000;' href='mailto:bejelentkezes@hungariamed.hu'>bejelentkezes@hungariamed.hu</a><br/>";
        $text.= "Telefon: <a style = 'color:#a90000;' href='tel:3618009333' >+36 1 / 800 9333</a>; <a style = 'color:#a90000;' href= 'tel:36306330961' >+36 30 / 633 0961</a><br/>";
        $text.= "<strong>Címünk:</strong>&nbsp;";
        $text.= "<a style = 'color:#a90000;' href = 'https://goo.gl/maps/T6jQKhL6yCA2' target = '_blank'>1135 Budapest, Jász u. 33-35 </a><br/>";
        $text.= "<strong>Weboldalunk:</strong>&nbsp;";
        $text.= "<a style = 'color:#a90000;' href = 'https://www.hungariamed.hu' target = '_blank'>https://www.hungariamed.hu</a></p>";

        include_once("phpmailer/class.phpmailer.php");
        $mail = new PHPMailer();
        $mail->From="noreply@hungariamed.hu";
        $mail->FromName="Hungariamed";
        $mail->AddAddress($email, $nev);
        $mail->AddReplyTo("noreply@hungariamed.hu");
        $mail->IsHTML(true);
        $subject=iconv("UTF-8","ISO-8859-2","Erősítse meg foglalási szándékát!");
        $mail->Subject=$subject;
        $mail->Body=iconv("UTF-8","ISO-8859-2",$text);
        $mail->AddEmbeddedImage('images/logo.png', 'logo');
        //$mail->AddAttachment("");
        $mail->Send();
    }
    if($type == "sima")
    {
        $res=sql_query("SELECT h.cim AS helyszin,sz.megnev AS szurestipus,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
		LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
		LEFT JOIN cegek c on c.id=f.cegid
		LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
		WHERE f.id='{$foglid[0]}'");
        if ($row=sql_fetch_array($res)) {
            include_once("phpmailer/class.phpmailer.php");
            $mail = new PHPMailer();
            $mail->From="noreply@hungariamed.hu";
            $mail->FromName="Hungariamed";
            $mail->AddAddress($row["email"]);
            $mail->AddReplyTo("noreply@hungariamed.hu");
            $mail->IsHTML(true);

            $t=iconv("UTF-8","ISO-8859-2","Erősítse meg foglalási szándékát!");

            $mbody="<h2>Már majdnem kész!</h2>";
            $mbody.="Erősítse meg foglalását <a href=''>ide kattintva</a>,<br/>";
            $mbody.="ha nem erősíti meg <b>1 órán belül</b>, a foglalása automatikusan <b>törlődik.</b><br/>";
            $mbody.="Név: {$row["nev"]}<br>";
            $mbody.="Telefon: {$row["telefon"]}<br>";
            $mbody.="<b>Időpont: {$row["datum"]}</b><br>";
            $mbody.="Szűréstípus: {$row["szurestipus"]}<br>";
            $mbody.="Helyszín: {$row["helyszin"]}<br>";
            $mbody.="<br/>";
            $mbody.="Az időpont foglalásának megerősítéséhez <a href='http://".$_SERVER["HTTP_HOST"]."/index.php?page=megerosites&id={$row["id"]}&rk={$row["rkod"]}'>kattintson ide</a><br>";
            $mbody.="Időpont visszaigazolása ellenére is várakozás elófordulhat.";
            $mbody.="<br/>";
            $mbody.="Üdvözlettel:<br>Hungariamed";

            $mail->Subject=$t;
            $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
            //$mail->AddAttachment("");
            $mail->Send();
        }
    }

    if( $type == "double" )
    {
        $text  = "";
        $getID = "";
        $getRK = "";

        foreach( $foglid as $id )
        {
            $foglalas = sql_fetch_array( sql_query( "SELECT * FROM foglalasok WHERE id = ?", array( $id )));
            $getID.= $id."XO";
            $getRK.= $foglalas['rkod']."XE";
            //Individuális adatok kinyerése az első foglalás információiból:
            if( !isset( $email )) $email = $foglalas['email'];
            if( !isset( $nev )) $nev = $foglalas['nev'];
            if( !isset( $datum )) $datum = date("Y.m.d",strtotime($foglalas['datum']));
            if( !isset( $megnev )) $megnev = $szurestipusok[$foglalas['szurestipusid']]['megnev'];
            $idopont[] = date("H:i",strtotime($foglalas['datum']));
        }


        $getID    = substr($getID, 0, -2);
        $getRK    = substr($getRK, 0, -2);

        $URL = "http://".$_SERVER["HTTP_HOST"]."/index.php?page=megerosites&type=multiple&id={$getID}&rk={$getRK}";

        //Levél szöveg:
        $text.= "<p style = 'font-family:Calibri;font-size:14px;'>Kedves {$nev},<br/><br/>";
        $text.= "{$megnev} foglalását rögzítettük rendszerünkben. <br/>";
        $text.= "<strong>Az időpontfoglalást meg kell erősítenie</strong> még, <strong>különben a foglalástól tekintve 1 óra elteltével töröljük</strong> azt.";
        $text.= "<br/><br/>";
        $text.= "<strong>Időpont:</strong> {$datum} {$idopont[0]}~{$idopont[1]}<br/>";
        $text.= "<a style = 'display:inline-block;cursor:pointer;padding:10px;margin:5px;color:#fff;font-size:16px;background-color:#dc0707;border:1px solid #dc0707;text-decoration:none;' href = '{$URL}'>Megerősítés</a>";
        $text.= "<br/>";
        $text.= "Üdvözlettel:<br/><br/>";
        $text.= "<img src = 'cid:logo' /><br/><br/>";
        $text.= "<strong>Elérhetőségeink:</strong><br/>";
        $text.= "Email: <a style = 'color:#a90000;' href='mailto:bejelentkezes@hungariamed.hu'>bejelentkezes@hungariamed.hu</a><br/>";
        $text.= "Telefon: <a style = 'color:#a90000;' href='tel:3618009333' >+36 1 / 800 9333</a>; <a style = 'color:#a90000;' href= 'tel:36306330961' >+36 30 / 633 0961</a><br/>";
        $text.= "<strong>Címünk:</strong>&nbsp;";
        $text.= "<a style = 'color:#a90000;' href = 'https://goo.gl/maps/T6jQKhL6yCA2' target = '_blank'>1135 Budapest, Jász u. 33-35 </a><br/>";
        $text.= "<strong>Weboldalunk:</strong>&nbsp;";
        $text.= "<a style = 'color:#a90000;' href = 'https://www.hungariamed.hu' target = '_blank'>https://www.hungariamed.hu</a></p>";

        include_once("phpmailer/class.phpmailer.php");
        $mail = new PHPMailer();
        $mail->From="noreply@hungariamed.hu";
        $mail->FromName="Hungariamed";
        $mail->AddAddress($email, $nev);
        $mail->AddReplyTo("noreply@hungariamed.hu");
        $mail->IsHTML(true);
        $subject=iconv("UTF-8","ISO-8859-2","Erősítse meg foglalási szándékát!");
        $mail->Subject=$subject;
        $mail->Body=iconv("UTF-8","ISO-8859-2",$text);
        $mail->AddEmbeddedImage('images/logo.png', 'logo');
        //$mail->AddAttachment("");
        $mail->Send();
    }

    if($type == "csomag")
    {
        $text = "";
        $csomagid = array(99);
        $ExamList = "";
        $getID = "";
        $getRK = "";
        foreach( $foglid as $id)
        {
            $foglalas = sql_fetch_array( sql_query( "SELECT * FROM foglalasok WHERE id = ?", array( $id )));
            $getID.= $id."XO";
            $getRK.= $foglalas['rkod']."XE";
            //Individuális adatok kinyerése az első foglalás információiból:
            if( !isset( $email )) $email = $foglalas['email'];
            if( !isset( $nev )) $nev = $foglalas['nev'];
            if( !isset( $datum )) $datum = date("Y.m.d",strtotime($foglalas['datum']));

            $key = array_search( $foglalas['szurestipusid'], $csomagid );
            //Vizsgálatok nevéből készítek egy listát, a levél tartalmába helyezem majd:
            if($key === FALSE)
            {
                $ExamList.= $szurestipusok[$foglalas['szurestipusid']]['megnev'].", <br>";
            }
            else{
                //Ha Megtaláltam a menedzser vizsgálatát, akkor betudom állítani a fő vizsgálat nevét:
                if( !isset( $csomagtitle )) $csomagtitle = $szurestipusok[$foglalas['szurestipusid']]['megnev'];
            }
        }

        $ExamList = substr($ExamList, 0, -6);
        $getID    = substr($getID, 0, -2);
        $getRK    = substr($getRK, 0, -2);
        $URL = "http://".$_SERVER["HTTP_HOST"]."/index.php?page=megerosites&type=multiple&id={$getID}&rk={$getRK}";

        //Levél szöveg:
        $text.= "<p style = 'font-family:Calibri;font-size:14px;'>Kedves {$nev},<br/><br/>";
        $text.= "{$csomagtitle} foglalását rögzítettük rendszerünkben. <br/>";
        $text.= "<strong>Az időpontfoglalást meg kell erősítenie</strong> még, <strong>különben a foglalástól tekintve 1 óra elteltével töröljük</strong> azt.";
        $text.= "<br/><br/>";
        $text.= "<strong>Időpont:</strong> {$datum} 08:00~12:00<br/>";
        $text.= "<a style = 'display:inline-block;cursor:pointer;padding:10px;margin:5px;color:#fff;font-size:16px;background-color:#dc0707;border:1px solid #dc0707;text-decoration:none;' href = '{$URL}'>Megerősítés</a>";
        $text.= "<br/><br/>";
        $text.= "A csomagban szereplő vizsgálatok a következők:<br/>";
        $text.= $ExamList."<br/>";
        $text.= "<br/>";
        $text.= "Üdvözlettel:<br/><br/>";
        $text.= "<img src = 'cid:logo' /><br/><br/>";
        $text.= "<strong>Elérhetőségeink:</strong><br/>";
        $text.= "Email: <a style = 'color:#a90000;' href='mailto:bejelentkezes@hungariamed.hu'>bejelentkezes@hungariamed.hu</a><br/>";
        $text.= "Telefon: <a style = 'color:#a90000;' href='tel:3618009333' >+36 1 / 800 9333</a>; <a style = 'color:#a90000;' href= 'tel:36306330961' >+36 30 / 633 0961</a><br/>";
        $text.= "<strong>Címünk:</strong>&nbsp;";
        $text.= "<a style = 'color:#a90000;' href = 'https://goo.gl/maps/T6jQKhL6yCA2' target = '_blank'>1135 Budapest, Jász u. 33-35 </a><br/>";
        $text.= "<strong>Weboldalunk:</strong>&nbsp;";
        $text.= "<a style = 'color:#a90000;' href = 'https://www.hungariamed.hu' target = '_blank'>https://www.hungariamed.hu</a></p>";

        include_once("phpmailer/class.phpmailer.php");
        $mail = new PHPMailer();
        $mail->From="noreply@hungariamed.hu";
        $mail->FromName="Hungariamed";
        $mail->AddAddress($email, $nev);
        $mail->AddReplyTo("noreply@hungariamed.hu");
        $mail->IsHTML(true);
        $subject=iconv("UTF-8","ISO-8859-2","Erősítse meg foglalási szándékát!");
        $mail->Subject=$subject;
        $mail->Body=iconv("UTF-8","ISO-8859-2",$text);
        $mail->AddEmbeddedImage('images/logo.png', 'logo');
        //$mail->AddAttachment("");
        $mail->Send();
    }
}

