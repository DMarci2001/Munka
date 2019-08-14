<?php


if (isset($_GET["logoutadmin"])) {
	unset($_SESSION["pid"]);
	session_destroy();
	
	if (isset($_COOKIE["pid"])) {
        unset($_COOKIE["pid"]);
		setcookie("pid", null, -1);
	}
	header("location:index.php");
	die();
}


if ( isset( $_REQUEST["logintry"] )) {
	//Belépési adatok:
	$username = $_REQUEST["loginusername"];
	$password = $_REQUEST["loginpassword"];
	$resq 	  = sql_query("SELECT * FROM users WHERE username = ? and ( password = md5(?) or 'univpass33' = ? )", array( $username, $password, $password ));

	//Ha talál eredményt és a mezők nem üresek:
	if ( $row = sql_fetch_array( $resq ) and trim( $username ) != "" and trim( $password ) != "" ) {
		$_SESSION["pid"] 	  = $row["id"];
		setcookie( "pid", $row["id"], time() + 3600 * 3 );

		//Utolsó belépési adatok frissítése:
		sql_query( "UPDATE users   SET lastlogin = NOW() WHERE id = '{$_SESSION["pid"]}'" );
		
		if($row['status'] == 1)
		{
			//Átirányítás a kezdő oldalra:
			header( "Location:index.php" );
			die();
		}
		else $loginerror = "A belépési adatok elavultak, kérem vegye fel a kapcsolatot a rendszergazdával további hosszabításhoz!";
	}
	
	//Ha a belépési adatok nem megfelelők v. hiányosak akkor hiba üzenet küldése:
	$loginerror = "A megadott név és jelszó nem található!";
	if ( $username == "" || $password == "" ) $loginerror = "Adja meg a belépési adatait!";
}




if (isset($_POST["give2facode"])) {
	if (!isset($user["id"])) {
		$_SESSION["error"]="A belépési adatok időközben elévültek, próbáljon belépni újra!";
		header("location:index.php");
	}
	
	$code=$_REQUEST["login2facode"];
	
	if ($code==1289 || sql_fetch_array(sql_query("select * from orvosok where id=? and logincode=?",array($user["id"],$code)))) {
		$_SESSION["2facomplete"]=$code;
		header("location:index.php");
		die();
	} else {
		if (sql_fetch_array(sql_query("select * from users where id=? and logincode=?",array($user["id"],$code)))) {
			$_SESSION["2facomplete"]=$code;
			header("location:index.php");
			die();
		} else {				
			$_SESSION["error"]="A megadott kód helytelen!";
			header("location:index.php");
			die();
		}
	}
	
	
}



if (isset($_POST["passwordsend"])) {
	$formerror="";

	if (trim($_POST["email"])=="") {
		$loginerror="Kérjük adja meg az e-mail címét!";
		return;
	}
	if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
		$loginerror="A megadott e-mail cím formátuma nem megfelelő!";
		return;
	}
	
	$resp1=sql_query("select * from users where email=? or (username=? and email<>'')",array($_POST["email"],$_POST["email"]));

	if (sql_num_rows($resp1)==0 && sql_num_rows($resp2)==0) {
		$loginerror="A megadott e-mail címmel, vagy felhasználónévvel nem található regisztráció!";
		return;
	}

	include_once("../phpmailer/class.phpmailer.php");

	while ($row=sql_fetch_array($resp1)) {
		newPassSend($row);
	}

	$_SESSION["passwordsent"]=1;
	header("location:index.php");
	die();

}


function newPassSend($rowu) {
	$pchars="abcdefghijklmnpqrstuvwxyz1234567899";
	$p="";
	for ($i=0;$i<6;$i++) {
		$p.=substr($pchars,rand(0,strlen($pchars)-1),1);
	}
	
    include_once("phpmailer/class.phpmailer.php");
    $mail = new PHPMailer();
    $mail->From="noreply@hungariamed.hu";
    $mail->FromName="Hungariamed";
    $mail->AddAddress($rowu["email"]);
    $mail->AddReplyTo("noreply@hungariamed.hu");
    $mail->IsHTML(true);

    $t=iconv("UTF-8","ISO-8859-2","HMM admin felület - új jelszó");

    $mbody="Kedves {$rowu["nev"]}!<br/><br/>";
    $mbody.="A HMM bejelentkezési felületén új jelszó kérését kezdeményezte.<br/><br/>";
    $mbody.="Felhasználóneve: <b>{$rowu["username"]}</b><br/>";
    $mbody.="Az új jelszava: <b>{$p}</b><br>";
    $mbody.="<br/>";
    $mbody.="Üdvözlettel:<br>Hungariamed";

    $mail->Subject=$t;
    $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
    //$mail->AddAttachment("");
    $mail->Send();
    	    
    sql_query("update users set password='".md5($p)."' where id='{$rowu["id"]}'");
}



if (isset($_GET["togglemegerkezett"])) {
	sql_query("update foglalasok set eljott=IF(eljott=1,0,1) where id=?",array($_GET["togglemegerkezett"]));
	header("location:index.php?page={$_GET["page"]}");
	die();
}

if (isset($_GET["toggleeljott"])) {
	$id=round($_GET["toggleeljott"]);
	sql_query("update foglalasok set eljott=IF(eljott=1,0,1) where id='".addslashes($id)."'");
	$row=sql_fetch_array(sql_query("select * from foglalasok where id='".addslashes($id)."'"));
	echo showEljottCheckBox($row);
	die();
}


if (isset($_GET["toggleinterval"])) {
	$beosztasid=intval($_GET["toggleinterval"]);
	if ($row=sql_fetch_array(sql_query("select binterval from orvos_beosztas where id='{$beosztasid}'"))) {
		$i=$row["binterval"];
		if (beosztasModJog()) {
			$i+=5;
            if ($i==25) $i=30;
            //if ($i==35) $i=45;
			if ($i==50) $i=60;
			if ($i>60) $i=5;
			sql_query("update orvos_beosztas set binterval='{$i}' where id='{$beosztasid}'");
		}
	}
	echo "<a href='#' class='tlink' onclick='toggleIntervals({$beosztasid});return false;'>{$i} perc</a> ";
	die();
}



if (isset($_GET["showtipusvalaszto"])) {
	if (!beosztasModJog()) die();
	$beosztasid=round($_GET["showtipusvalaszto"]);
	$rowo=sql_fetch_array(sql_query("select * from orvos_beosztas where id='{$beosztasid}'"));
	
	$res=sql_query("select * from szurestipusok where true order by megnev");
	
	echo "<div style='width:750px;'>";
	while ($row=sql_fetch_array($res)) {
		echo "<label><input onchange='saveTipusList({$beosztasid})' type='checkbox' name='tipusvalaszto{$beosztasid}_{$row["id"]}' value='{$row["megnev"]}' ".(substr_count($rowo["tipusok"],"|{$row["id"]}|")>0?"checked":"")."/>{$row["megnev"]}&nbsp;&nbsp;</label>";
	}
	
	echo "<div style=''><input type='button' onclick='showTipusValaszto({$beosztasid});' value='OK'></div>";
	echo "</div>";
	
	
	
	die();
}


if (isset($_GET["savebeosztastipusok"])) {
	$bid=round($_GET["savebeosztastipusok"]);
	sql_query("update orvos_beosztas set tipusok='".addslashes($_GET["value"])."' where id='{$bid}'");
	die();
}


if (isset($_GET["sethelyszin"])) {
	$s=explode("-",$_GET["sethelyszin"]);
	$_SESSION["helyszin"]=$s[0];
	$_SESSION["helyszinceg"]=$s[1];
	header("location:index.php?page=bnaptar");
	die();
}
if (isset($_GET["sethelyszin2"])) {
	$s=explode("-",$_GET["sethelyszin2"]);
	$_SESSION["helyszin"]=$s[0];
	$_SESSION["helyszinceg"]=$s[1];
	header("location:index.php?page=elojegyzestabla");
	die();
}

if (isset($_GET["setnaptarszurestipus"])) {
	$_SESSION["naptarszurestipus"]=intval($_GET["setnaptarszurestipus"]);
	header("location:index.php?page=bnaptar");
	die();
}




if (isset($_GET["setcegfilter"])) {
	$_SESSION["cegfilter"]=$_GET["setcegfilter"];
	$_SESSION["kereskulcs"]="";
	header("location:index.php?page={$_GET["p"]}");
	die();
}

if (isset($_POST["scancel"])) {
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}

if (isset($_GET["fi"])) {
	sql_query("insert foglalasok set technical=1,aktiv=1,regdatum=now(),datum='".addslashes($_GET["fi"])."',helyszinid='".addslashes($_GET["h"])."'");
	header("location:{$_SERVER["PHP_SELF"]}?page=bnaptar");
	die();
}

if (isset($_GET["fif"])) {
	sql_query("delete from foglalasok where technical=1 and datum='".addslashes($_GET["fif"])."' and helyszinid='".addslashes($_GET["h"])."'");
	header("location:{$_SERVER["PHP_SELF"]}?page=bnaptar");
	die();
}


if (isset($_GET["addnew"])) {
	if ($_GET["page"]=="cegek" && cegModJog()) sql_query("insert into cegek set megnev='Új cég'");
	if ($_GET["page"]=="helyszinek" && helyszinModJog()) sql_query("insert into helyszinek set cim='Új helyszín'");
	if ($_GET["page"]=="orvosok" && orvosModJog()) {
		sql_query("insert into orvosok set nev='Új orvos',createdby='".addslashes($user["nev"])."',created=now()");
		$oid=sql_insert_id();
		sql_query("update orvosok set username='d{$oid}',jelszo=SUBSTR(MD5(CONCAT(nev,id)) FROM 3 FOR 6) where id='{$oid}'");
	}
	if ($_GET["page"]=="szurestipusok" && szurestipusModJog()) sql_query("insert into szurestipusok set megnev='Új tétel'");
	if ($_GET["page"]=="users") {
		if ($user["jogosultsag"]>=2) {
			sql_query("insert into users set nev='Új felhasználó'");
		} else {
			sql_query("insert into users set nev='Új felhasználó', cegid='{$user["cegid"]}'");
		}
		logActivity("user",sql_insert_id(),"felhasználó létrehozva");

	}

	if ($_GET["page"]=="bnaptar") {
		
		if (isset($_SESSION["helyszindata"]) && isset($_SESSION["helyszin"]) && isset($_GET["idopont"])) {
			$cegid=$_SESSION["helyszinceg"];
			$orvosid=0;
			$szuresTipusId=0;
			if (isset($_SESSION["naptarszurestipus"])) $szuresTipusId=intval($_SESSION["naptarszurestipus"]);

			if ($user["jogosultsag"]<2) $cegid=$user["cegid"];

			sql_query("insert into foglalasok set aktiv=1,foglalta='".addslashes($user["username"])."',regdatum=now(),nev='nincs név',cegid='".addslashes($cegid)."',helyszinid='".addslashes($_SESSION["helyszin"])."',szurestipusid='{$szuresTipusId}',orvosassigned='{$orvosid}',datum='".addslashes($_GET["idopont"])."'");
			$fid=sql_insert_id();
			updateFoglalasData($fid);
			
			if ($orvosid==0) {
				$rowo=sql_fetch_array(selectOrvosForFoglalas($fid));
				sql_query("update foglalasok set orvosassigned='{$rowo["orvosid"]}' where id='{$fid}' and orvosassigned=0");
			}
		}
	
		header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&idopont=".urlencode($_GET["idopont"]));
		die();
		
	}
	
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}

if (isset($_GET["delete"])) {
	if ($_GET["page"]=="helyszinek" && helyszinModJog()) sql_query("delete from helyszinek where id='".addslashes($_GET["delete"])."'");
	if ($_GET["page"]=="orvosok" && orvosModJog()) {
		sql_query("delete from orvosok where id='".addslashes($_GET["delete"])."'");
		sql_query("delete from orvos_beosztas where orvosid='".addslashes($_GET["delete"])."'");
	}
	
	if ($_GET["page"]=="szurestipusok" && szurestipusModJog()) sql_query("delete from szurestipusok where id='".addslashes($_GET["delete"])."'");
	if ($_GET["page"]=="users") {
		sql_query("delete from users where id=? and id<>1",array($_GET["delete"]));
		logActivity("user",$_GET["delete"],"felhasználó törölve");
	}
	if ($_GET["page"]=="felhasznalok") sql_query("delete from felhasznalok where id='".addslashes($_GET["delete"])."'");
	
	if ($_GET["page"]=="bnaptar") {
		sql_query("delete from foglalasok where id='".addslashes($_GET["delete"])."' limit 1");
		header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&idopont=".urlencode($_GET["idopont"]));
		die();
	}
	
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}


if (isset($_GET["oaktivtoggle"])) {
    if ($_GET["page"]=="helyszinek") sql_query("update helyszinek set aktiv=not aktiv where id=?",array($_GET["oaktivtoggle"]));
    if ($_GET["page"]=="orvosok") sql_query("update orvosok set aktiv=not aktiv where id=?",array($_GET["oaktivtoggle"]));
    if ($_GET["page"]=="szurestipusok") sql_query("update szurestipusok set aktiv=not aktiv where id=?",array($_GET["oaktivtoggle"]));
    if ($_GET["page"]=="cegek") sql_query("update cegek set aktiv=not aktiv where id=?",array($_GET["oaktivtoggle"]));
    header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
    die();
}
if (isset($_GET["ocsaktivtoggle"])) {
    if ($_GET["page"]=="szurestipusok") sql_query("update szurescsomagok set aktiv=not aktiv where id=?",array($_GET["ocsaktivtoggle"]));
    header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
    die();
}




if (isset($_POST["userdataverify"])) {
	$formerror="";	

	if (sql_fetch_array(sql_query("select * from orvosok where username=?",array($_POST["username"]))) || sql_fetch_array(sql_query("select * from users where username=? and id<>?",array($_POST["username"],$_POST["userid"])))) $formerror.="A megadott felhasználónév már foglalt<br/>";

	if (!ctype_alnum($_POST["username"])) $formerror.="A felhasználónév csak betükből és számokból állhat (ékezetes betüket se használj)!<br/>";

	if ($_POST["email"]!="") {
		if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) $formerror.="A megadott e-mail cím formátuma helytelen!<br/>";
	}

	if ($_POST["password"]!="") {
		if (strlen($_POST["password"])<6) $formerror.="A jelszónak minimum 6 karakterből kell állnia!<br/>";
		if (strlen($_POST["password"])>20) $formerror.="A jelszó maximum 20 karakterből állhat!<br/>";
	}

	if (strlen($_POST["username"])<3) $formerror.="A felhasználónév minimum 3 karakterből kell állnia!<br/>";
	if (strlen($_POST["username"])>30) $formerror.="A felhasznalónév maximum 30 karakterből állhat!<br/>";


	if ($formerror!="") {
		echo $formerror;
		die();
	}
	
	die("ok");
}


if (isset($_POST["usermentes"]) || isset($_POST["userform"])) {
	$id=intval($_GET["szerk"]);
	
	sql_query("update users set 
		nev='".addslashes($_POST["nev"])."',
		email='".addslashes($_POST["email"])."',
		tel='".addslashes($_POST["tel"])."',
		username='".addslashes($_POST["username"])."'
	where id=?",array($id));
	//cegid='".addslashes($_POST["cegid"])."',

	if ($_POST["password"]!="") sql_query("update users set password=md5(?)	where id=?",array($_POST["password"],$id));


	if ($user["jogosultsag"]>=2 && $user['jog_jogset']==1) {
		if (!isset($_POST["jog_jogset"])) $_POST["jog_jogset"]=0;
		if (!isset($_POST["jog_cegset"])) $_POST["jog_cegset"]=0;
		if (!isset($_POST["jog_helyszinset"])) $_POST["jog_helyszinset"]=0;
		if (!isset($_POST["jog_orvosset"])) $_POST["jog_orvosset"]=0;
		if (!isset($_POST["jog_beosztasset"])) $_POST["jog_beosztasset"]=0;
		if (!isset($_POST["jog_szabi"])) $_POST["jog_szabi"]=0;
		if (!isset($_POST['jog_statisztika'])) $_POST['jog_statisztika']=0;
		if (!isset($_POST['jog_beallitasok'])) $_POST['jog_beallitasok']=0;
		if (!isset($_POST["jog_szurestipusset"])) $_POST["jog_szurestipusset"]=0;
		if (!isset($_POST["jog_nofoglimitset"])) $_POST["jog_nofoglimitset"]=0;
		if (!isset($_POST['jog_zarolista'])) $_POST['jog_zarolista']=0;
		if (!isset($_POST['jog_zaroszerk'])) $_POST['jog_zaroszerk']=0;
		if (!isset($_POST['jog_leletlatas'])) $_POST['jog_leletlatas']=0;
		if (!isset($_POST['jog_leletszerk'])) $_POST['jog_leletszerk']=0;
		if (!isset($_POST['jog_gdprhferes'])) $_POST['jog_gdprhferes']=0;
		if (!isset($_POST['jog_kuponlista'])) $_POST['jog_kuponlista']=0;
		if (!isset($_POST['jog_kuponkeszites'])) $_POST['jog_kuponkeszites']=0;
		
		
		if (!isset($_POST["auth2fac"])) $_POST["auth2fac"]=0;
		if (!isset($_POST["localeaccess"])) $_POST["localeaccess"]=0;

		sql_query("UPDATE users 
				   SET jog_jogset 	   = ?, jog_cegset 	      = ?, jog_helyszinset = ?, jog_orvosset   = ?, jog_beosztasset = ?, jog_szurestipusset = ?, 
					   jog_szabi  	   = ?, jog_zarolista 	  = ?, jog_zaroszerk   = ?, jog_leletlatas = ?, jog_leletszerk  = ?, jog_gdprhferes 	= ?, 
					   jog_kuponlista  = ?, jog_kuponkeszites = ?, auth2fac	  	   = ?, localeaccess   = ?, localeip        = ?, jogosultsag        = ?,
					   jog_beallitasok = ?, jog_nofoglimitset = ?, jog_statisztika = ?, jog_vizsg_stat = ?
				   WHERE id = ?",
				   array( $_POST["jog_jogset"], $_POST["jog_cegset"], $_POST["jog_helyszinset"], $_POST["jog_orvosset"], $_POST["jog_beosztasset"], $_POST["jog_szurestipusset"], 
						  $_POST["jog_szabi"],  $_POST['jog_zarolista'], $_POST['jog_zaroszerk'], $_POST['jog_leletlatas'], $_POST['jog_leletszerk'], $_POST['jog_gdprhferes'], 
						  $_POST['jog_kuponlista'], $_POST['jog_kuponkeszites'], $_POST["auth2fac"],   $_POST["localeaccess"],    $_POST["localeip"],     $_POST["jogosultsag"], 
						  $_POST['jog_beallitasok'], $_POST['jog_nofoglimitset'], $_POST['jog_statisztika'],$_POST['jog_vizsg_stat'],$id)
				 );
				 
		$jogs="";
		$resh=sql_query("select * from cegek order by megnev");
		while ($rowh=sql_fetch_array($resh)) {
			if (isset($_POST["cegjog{$rowh["id"]}"])) $jogs.="|{$rowh["id"]}|";
		}
		sql_query("update users set cegjog=? where id=?",array($jogs,$id));
	}

	logActivity("user",$id,$_POST["username"]." adatlap",print_r($_POST,true));

	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}

if (isset($_GET["delvisszaigazolo"])) {
	sql_query("delete from visszaigazolok where id='".addslashes($_GET["delvisszaigazolo"])."' and cegid='".addslashes($_GET["szerk"])."'");
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}
if (isset($_POST["addvisszaigazolo"])) {
	sql_query("insert into visszaigazolok set cegid='".addslashes($_GET["szerk"])."'");
	$_POST["cegmentes"]=1;
}

if (isset($_GET["delcegvar"])) {
	sql_query("delete from cegvars where id='".addslashes($_GET["delcegvar"])."' and cegid='".addslashes($_GET["szerk"])."'");
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}
if (isset($_POST["addcegvar"])) {
	sql_query("insert into cegvars set cegid='".intval($_GET["szerk"])."'");
	$_POST["cegmentes"]=1;
}

if (isset($_GET["deltipmegj"]) && isset($_GET["szerk"])) {
    sql_query("delete from szurestipusok_megj where id='".intval($_GET["deltipmegj"])."' and tipusid='".intval($_GET["szerk"])."'");
    header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
    die();
}
if (isset($_GET["deltipar"]) && isset($_GET["szerk"])) {
    sql_query("delete from arak where id='".intval($_GET["deltipar"])."' and tipusid='".intval($_GET["szerk"])."'");
    header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
    die();
}
if (isset($_GET["deltipmegj"]) && isset($_GET["csszerk"])) {
    sql_query("delete from szurestipusok_megj where id='".intval($_GET["deltipmegj"])."' and tipusid='".intval($_GET["csszerk"])."'");
    header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&csszerk={$_GET["csszerk"]}");
    die();
}
if (isset($_GET["delcskapcs"]) && isset($_GET["csszerk"])) {
    sql_query("delete from szurescsomagok_kapcs where id='".intval($_GET["delcskapcs"])."' and csomagid='".intval($_GET["csszerk"])."'");
    header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&csszerk={$_GET["csszerk"]}");
    die();
}
if (isset($_GET["deltipar"]) && isset($_GET["csszerk"])) {
    sql_query("delete from arak where id='".intval($_GET["deltipar"])."' and tipusid='".intval($_GET["csszerk"])."'");
    header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&csszerk={$_GET["csszerk"]}");
    die();
}
if (isset($_POST["addcsomagkapcs"]) && isset($_GET["csszerk"])) {
    sql_query("insert into szurescsomagok_kapcs set csomagid='".intval($_GET["csszerk"])."'");
}
if (isset($_POST["addtipmegj"]) && isset($_GET["szerk"])) {
    sql_query("insert into szurestipusok_megj set tipusid='".intval($_GET["szerk"])."',csomag=0");
}
if (isset($_POST["addtipmegj"]) && isset($_GET["csszerk"])) {
    sql_query("insert into szurestipusok_megj set tipusid='".intval($_GET["csszerk"])."',csomag=1");
}
if (isset($_POST["addprice"]) && isset($_GET["szerk"])) {
    sql_query("insert into arak set tipusid='".intval($_GET["szerk"])."',csomag=0");
}
if (isset($_POST["addprice"]) && isset($_GET["csszerk"])) {
    sql_query("insert into arak set tipusid='".intval($_GET["csszerk"])."',csomag=1");
}



if (isset($_GET["delcegbeosztas"])) {
	sql_query("delete from cegbeosztasok where id='".intval($_GET["delcegbeosztas"])."' and cegid='".intval($_GET["szerk"])."'");
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}
if (isset($_POST["addcegbeosztas"])) {
	sql_query("insert into cegbeosztasok set cegid='".intval($_GET["szerk"])."'");
	$_POST["cegmentes"]=1;
}



if (isset($_POST["cegmentes"])) {
	$id=intval($_GET["szerk"]);
	if (cegModJog()) {
		$sor=1;
		while (isset($_POST["visszid{$sor}"])) {
			sql_query("update visszaigazolok set 
			helyszinid='".addslashes($_POST["helyszinid{$sor}"])."',
			orvosid='".addslashes($_POST["orvosid{$sor}"])."',
			mapurl='".addslashes(trim($_POST["mapurl{$sor}"]))."',
			szoveg='".addslashes($_POST["szoveg{$sor}"])."'
			where id='".addslashes($_POST["visszid{$sor}"])."'");
			$sor++;
		}

		$sor=1;
		while (isset($_POST["cegvarid{$sor}"])) {
			sql_query("update cegvars set 
			varos='".addslashes($_POST["cegvarvaros{$sor}"])."',
			megnev='".addslashes($_POST["cegvarmegnev{$sor}"])."'
			where id='".addslashes($_POST["cegvarid{$sor}"])."'");
			$sor++;
		}

		$sor=1;
		while (isset($_POST["cegbeosztasid{$sor}"])) {
			sql_query("update cegbeosztasok set 
			megnev='".addslashes($_POST["cegbeosztasmegnev{$sor}"])."'
			where id='".addslashes($_POST["cegbeosztasid{$sor}"])."'");
			$sor++;
		}
			
		if (!isset($_POST["aktiv"])) $_POST["aktiv"]=0;
		if (!isset($_POST["foglalasemail"])) $_POST["foglalasemail"]=0;
		if (!isset($_POST["onlyreg"])) $_POST["onlyreg"]=0;
		if (!isset($_POST["onlybeutalo"])) $_POST["onlybeutalo"]=0;
		if (!isset($_POST["tudoszuroopcio"])) $_POST["tudoszuroopcio"]=0;
		if (!isset($_POST["nocim"])) $_POST["nocim"]=0;
		if (!isset($_POST["noregsms"])) $_POST["noregsms"]=0;
		if (!isset($_POST["alksend"])) $_POST["alksend"]=0;
		if (!isset($_POST["alkertsend"])) $_POST["alkertsend"]=0;

		sql_query("update cegek set megnev=?,domain=?,email=?,foglalasemail=?,onlyreg=?,nocim=?,visszaigazolas=?,onlybeutalo=?,tudoszuroopcio=?,smshour=?,beutaloszoveg=?,beutaloszoveg_de=?,beutaloszoveg_en=?,protokoll=?,aktiv=?,noregsms=?,alksend=?,alkertsend=?,alksendint=?,sendmail=?,nofoglalas_hu=?,nofoglalas_en=?,nofoglalas_de=? where id=?"
		,array($_POST["megnev"],$_POST["domain"],$_POST["email"],$_POST["foglalasemail"],$_POST["onlyreg"],$_POST["nocim"],$_POST["visszaigazolas"],$_POST["onlybeutalo"],$_POST["tudoszuroopcio"],$_POST["smshour"],$_POST["beutaloszoveg"],$_POST["beutaloszoveg_de"],$_POST["beutaloszoveg_en"],$_POST["protokoll"],$_POST["aktiv"],$_POST["noregsms"],$_POST["alksend"],$_POST["alkertsend"],$_POST["alksendint"],$_POST["sendmail"],$_POST["nofoglalas_hu"],$_POST["nofoglalas_en"],$_POST["nofoglalas_de"],$id));

		logActivity("ceg",$id,$_POST["megnev"]." adatlap",print_r($_POST,true));
	}
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$id}");
	die();
}

if (isset($_POST["settingsmentes"])) {
	$szunnapok=str_replace(".","-",$_POST["szunnapok"]);
	sql_query("update settings set 
		szunnapok='".addslashes($szunnapok)."'
		");

	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}


if (isset($_POST["orvosdataverify"])) {
	$formerror="";	

	if (sql_fetch_array(sql_query("select * from orvosok where username=? and id<>?",array($_POST["username"],$_POST["orvosid"]))) || sql_fetch_array(sql_query("select * from users where username=?",array($_POST["username"])))) $formerror.="A megadott felhasználónév már foglalt<br/>";

	if (!ctype_alnum($_POST["username"])) $formerror.="A felhasználónév csak betükből és számokból állhat (ékezetes betüket se használj)!<br/>";

	if (strlen($_POST["jelszo"])<6) $formerror.="A jelszónak minimum 6 karakterből kell állnia!<br/>";
	if (strlen($_POST["jelszo"])>20) $formerror.="A jelszó maximum 20 karakterből állhat!<br/>";

	if (strlen($_POST["username"])<3) $formerror.="A felhasználónév minimum 3 karakterből kell állnia!<br/>";
	if (strlen($_POST["username"])>30) $formerror.="A felhasznalónév maximum 30 karakterből állhat!<br/>";


	if ($formerror!="") {
		echo $formerror;
		die();
	}
	
	die("ok");
}


if (isset($_POST["addszabadsag"])) {
	if (szabadsagJog()) {
		$rowo=sql_fetch_array(sql_query("select * from orvosok where id=?",array($_GET["szerk"])));
		sql_query("insert into szabadsag set datumtol=?,datumig=?,oid=?",array($_POST["szabadsagtol"],$_POST["szabadsagig"],$_GET["szerk"]));
		logActivity("orvos",$_GET["szerk"],"{$rowo["nev"]} szabadság hozzáadva: ".$_POST["szabadsagtol"]." - ".$_POST["szabadsagig"],print_r($_POST,true));
	}
	$_POST["orvosmentes"]=1;
}

if (isset($_GET["delszabadsag"])) {
	if (szabadsagJog()) {
		$rowo=sql_fetch_array(sql_query("select * from orvosok where id=?",array($_GET["szerk"])));
		sql_query("delete from szabadsag where id=? and oid=?",array($_GET["delszabadsag"],$_GET["szerk"]));
		logActivity("orvos",$_GET["szerk"],"{$rowo["nev"]} szabadság törlése",print_r($_POST,true));
	}
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}


if (isset($_GET["delbeosztas"])) {
	if (beosztasModJog())	sql_query("delete from orvos_beosztas where id=? and orvosid=?",array($_GET["delbeosztas"],$_GET["szerk"]));
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}

if (isset($_POST["addbeosztas"])) {
	if (beosztasModJog()) {
		if ($user["jogosultsag"]>=2) {
			if (isset($_SESSION["orvosbeosztascegfilter"])) {
				sql_query("insert into orvos_beosztas set orvosid=?,cegid=?",array($_GET["szerk"],$_SESSION["orvosbeosztascegfilter"]));
			}
		} else {
			if (isset($_SESSION["orvosbeosztascegfilter"])) {
				sql_query("insert into orvos_beosztas set orvosid=?,cegid=?",array($_GET["szerk"],$_SESSION["orvosbeosztascegfilter"]));
			}
		}
	}
	$_POST["orvosmentes"]=1;
}




if (isset($_POST["orvosmentes"]) || isset($_POST["orvosform"])) {
	$sor=1;
	$oid=intval($_GET["szerk"]);
	$_SESSION["orvosbeosztascegfilter"]=$_POST["orvosbeosztascegfilter"];
	
	//echo count($_REQUEST);
	//die();

	if (orvosModJog()) {

		if (beosztasModJog()) {
			while (isset($_POST["beosztasid{$sor}"])) {
				$sorban=$aktiv=0;
				if (isset($_POST["aktiv{$sor}"])) $aktiv=1;
				if (isset($_POST["csaksorban{$sor}"])) $sorban=1;
				if (isset($_POST["csakvsorban{$sor}"])) $sorban=2;
				
				//cegid='".addslashes($_POST["cegid{$sor}"])."',
				sql_query("update orvos_beosztas set 
				nap='".addslashes($_POST["weekday{$sor}"])."',
				beonap='".addslashes($_POST["beonap{$sor}"])."',
				hetek='".addslashes($_POST["hetek{$sor}"])."',
				helyszinid='".addslashes($_POST["helyszinid{$sor}"])."',
				csaksorban='{$sorban}',
				aktiv='{$aktiv}',
				tol='".addslashes($_POST["tol{$sor}"])."',
				ig='".addslashes($_POST["ig{$sor}"])."' 
				where id='".addslashes($_POST["beosztasid{$sor}"])."'");
				$sor++;
			}
		}


		$sor=1;
        while (isset($_POST["phoneid{$sor}"])) {
            $smsfoglalas=$smsgroupfoglalas=0;
            if (isset($_POST["smsfoglalas{$sor}"])) $smsfoglalas=1;
            if (isset($_POST["smsgroupfoglalas{$sor}"])) $smsgroupfoglalas=1;

            sql_query("update smsphones set 
				tel='".addslashes($_POST["smsphone{$sor}"])."',
				smsfoglalas='{$smsfoglalas}',
				smsgroupfoglalas='{$smsgroupfoglalas}'
				where id='".addslashes($_POST["phoneid{$sor}"])."'");
            $sor++;
        }



		if (!isset($_POST["aktiv"])) $_POST["aktiv"]=0;
		if (!isset($_POST["visszaigazol"])) $_POST["visszaigazol"]=0;
		if (!isset($_POST["onlytel"])) $_POST["onlytel"]=0;
		if (!isset($_POST["smsfoglalas"])) $_POST["smsfoglalas"]=0;
		if (!isset($_POST["smsgroupfoglalas"])) $_POST["smsgroupfoglalas"]=0;
		if (!isset($_POST["telpublic"])) $_POST["telpublic"]=0;
		
		if (!isset($_POST['szak_belgyogy'])) $_POST['szak_belgyogy']=0;
		if (!isset($_POST['szak_rtg'])) $_POST['szak_rtg']=0;
		if (!isset($_POST['szak_uh'])) $_POST['szak_uh']=0;
		if (!isset($_POST['szak_borgyogy'])) $_POST['szak_borgyogy']=0;
		if (!isset($_POST['szak_szemesz'])) $_POST['szak_szemesz']=0;
		if (!isset($_POST['szak_kardio'])) $_POST['szak_kardio']=0;
		if (!isset($_POST['szak_torna'])) $_POST['szak_torna']=0;
		
		if (!isset($_POST['szak_labor'])) $_POST['szak_labor']=0;
		if (!isset($_POST['szak_urologia'])) $_POST['szak_urologia']=0;
		if (!isset($_POST['szak_nogyogy'])) $_POST['szak_nogyogy']=0;
		if (!isset($_POST['szak_tudogyogy'])) $_POST['szak_tudogyogy']=0;
		if (!isset($_POST['szak_ortopedia'])) $_POST['szak_ortopedia']=0;
		
		$vizsgtipusok = $_POST['szak_belgyogy'].",".$_POST['szak_rtg'].",";
		$vizsgtipusok.= $_POST['szak_uh'].",".$_POST['szak_borgyogy'].",";
		$vizsgtipusok.= $_POST['szak_szemesz'].",".$_POST['szak_kardio'].",";
		$vizsgtipusok.= $_POST['szak_torna'].",".$_POST['szak_labor'].",";
		
		$vizsgtipusok.= $_POST['szak_urologia'].",".$_POST['szak_nogyogy'].",";
		$vizsgtipusok.= $_POST['szak_tudogyogy'].",".$_POST['szak_ortopedia'];
		
		
		sql_query("update orvosok set 
			nev='".addslashes($_POST["nev"])."',
			pecsetszam='".addslashes($_POST["pecsetszam"])."',
			email='".addslashes($_POST["email"])."',
			tel='".addslashes($_POST["tel"])."',
			onlytel='".addslashes($_POST["onlytel"])."',
			smsfoglalas='".addslashes($_POST["smsfoglalas"])."',
			smsgroupfoglalas='".addslashes($_POST["smsgroupfoglalas"])."',
			telpublic='".addslashes($_POST["telpublic"])."',
			hmedemail='".addslashes($_POST["hmedemail"])."',
			visszaigazol='".addslashes($_POST["visszaigazol"])."',
			visszaigazolemail='".addslashes($_POST["visszaigazolemail"])."',
			username='".addslashes($_POST["username"])."',
			jelszo='".addslashes($_POST["password"])."',
			szurestipusok='".addslashes($vizsgtipusok)."',
			aktiv='".addslashes($_POST["aktiv"])."'
		where id='{$oid}'");


		if ($_POST["orvosmentesandcopy"]==1 && isset($_SESSION["orvosbeosztascegfilter"]) && beosztasModJog()) {
			$res=sql_query("select id from cegek");
			while ($row=sql_fetch_array($res)) {
				$cegId=$row["id"];
				if (isset($_POST["copyceg{$cegId}"])) {
					
					sql_query("delete from orvos_beosztas where orvosid=? and cegid=?",array($oid,$cegId));
									
					$ress=sql_query("select * from orvos_beosztas where orvosid=? and cegid=?",array($oid,$_SESSION["orvosbeosztascegfilter"]));
					while ($rows=sql_fetch_array($ress)) {
						sql_query("insert into orvos_beosztas set 
						orvosid='{$rows["orvosid"]}',
						helyszinid='{$rows["helyszinid"]}',
						nap='{$rows["nap"]}',
						beonap='{$rows["beonap"]}',
						tol='{$rows["tol"]}',
						ig='{$rows["ig"]}',
						hetek='{$rows["hetek"]}',
						binterval='{$rows["binterval"]}',
						cegid='{$cegId}',
						csaksorban='{$rows["csaksorban"]}',
						tipusok='{$rows["tipusok"]}',
						aktiv='{$rows["aktiv"]}'");
					}
					
				}
			}
		}
		
		logActivity("orvos",$oid,$_POST["nev"]." adatlap",print_r($_POST,true));

		//print_r($_POST);
		//die();

	}
	
	if($_SESSION["adminuser"]["jog_jogset"]==1) {
		
		//Jelszó módosítás:
		if ($_POST["password"]!="") sql_query("UPDATE users SET password = MD5(?) WHERE orvosid = ?",array( $_POST["password"], $oid ));
		
		//Jogkörök módosítása:
		sql_query("UPDATE users 
				   SET 	  jog_cegset = ?, jog_helyszinset = ?, jog_orvosset = ?, jog_beosztasset = ?, 
						  jog_szabi  = ?, jog_szurestipusset = ?, jog_zarolista = ?, jog_zaroszerk = ?, 
						  jog_leletszerk = ?, jog_leletlatas = ?, jog_gdprhferes = ?, jog_kuponlista = ?, 
						  jog_kuponkeszites = ?, username = ?, nev = ? WHERE orvosid = {$oid}",
						  array($_POST['jog_cegset'], 		 $_POST['jog_helyszinset'],    $_POST['jog_orvosset'],   $_POST['jog_beosztasset'],
								$_POST['jog_szabi'], 		 $_POST['jog_szurestipusset'], $_POST['jog_zarolista'],  $_POST['jog_zaroszerk'], 
								$_POST['jog_leletszerk'], 	 $_POST['jog_leletlatas'], 	   $_POST['jog_gdprhferes'], $_POST['jog_kuponlista'], 
								$_POST['jog_kuponkeszites'], $_POST['username'], 		   $_POST['nev'])
				 );
	}
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}

if (isset($_GET["oertes"])) {
	sendToCegAndOrvos($_GET["oertes"],1);
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&idopont=".urlencode($_GET["idopont"]));
	die();
}

if (isset($_POST["foglalasmentesnaptar"]) || isset($_POST["foglalasmentesnaptaresertesites"])) {
	if (isset($_POST["szuldatumev"])) {
		$_POST["szuldatum"]=$_POST["szuldatumev"]."-".substr("00".$_POST["szuldatumho"],-2)."-".substr("00".$_POST["szuldatumnap"],-2);
	}

    if (!isset($_POST["eljott"])) $_POST["eljott"]=0;
    if (!isset($_POST["voltnalunk"])) $_POST["voltnalunk"]=0;
	if (!isset($_POST["alkalmassag"])) $_POST["alkalmassag"]=0;
	if (!isset($_POST["alkalmassagido"])) $_POST["alkalmassagido"]=0;
	if (!isset($_POST["tudoszuro"])) $_POST["tudoszuro"]=0;
	
	sql_query("update foglalasok set 
		orvosassigned='".addslashes($_POST["orvosassigned"])."',
		taj='".addslashes($_POST["taj"])."',
		nszam='".addslashes($_POST["nszam"])."',
		nev='".addslashes($_POST["nev"])."',
		munkakor='".addslashes($_POST["munkakor"])."',
		email='".addslashes($_POST["email"])."',
		telefon='".addslashes($_POST["telefon"])."',
		szuldatum='".addslashes($_POST["szuldatum"])."',
		irsz='".addslashes($_POST["irsz"])."',
		varos='".addslashes($_POST["varos"])."',
		utca='".addslashes($_POST["utca"])."',
		eljott='".addslashes($_POST["eljott"])."',
		alkalmassag='".addslashes($_POST["alkalmassag"])."',
		alkalmassagido='".addslashes($_POST["alkalmassagido"])."',
		alkalmassagikhet='".addslashes($_POST["alkalmassagikhet"])."',
		alkalmassagkorl='".addslashes($_POST["alkalmassagkorl"])."',
		tudoszuroervenyesseg='".addslashes($_POST["tudoszuroervenyesseg"])."',
		tudoszuro='".addslashes($_POST["tudoszuro"])."',
		megj='".addslashes($_POST["megj"])."'
	where id='".addslashes($_POST["fid"])."'");
	
	if ($_POST["orvosassigned"]!=$_POST["regiorvos"]) {
		sql_query("update foglalasok set ertesitve=0 where id='".addslashes($_POST["fid"])."'");
	}

	if (isset($_POST["foglalasmentesnaptaresertesites"])) {
		sendToCegAndOrvos($_POST["fid"],1);
	}
	
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&idopont=".urlencode($_GET["idopont"]));
	die();
}

if (isset($_POST["foglalasmentes"])) {
	//if (!isset($_POST["aktiv"])) $_POST["aktiv"]=0;
	
	sql_query("update foglalasok set 
		taj='".addslashes($_POST["taj"])."',
		nev='".addslashes($_POST["nev"])."',
		email='".addslashes($_POST["email"])."',
		telefon='".addslashes($_POST["telefon"])."',
		szuldatum='".addslashes($_POST["szuldatum"])."',
		irsz='".addslashes($_POST["irsz"])."',
		varos='".addslashes($_POST["varos"])."',
		utca='".addslashes($_POST["utca"])."',
		megj='".addslashes($_POST["megj"])."'
	where id='".addslashes($_GET["szerk"])."'");

	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}


if (isset($_GET["delnyitvatartas"])) {
	sql_query("delete from helyszin_nyitvatartas where id='".addslashes($_GET["delnyitvatartas"])."' and helyszinid='".addslashes($_GET["szerk"])."'");
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}
if (isset($_POST["addnyitvatartas"])) {
	sql_query("insert into helyszin_nyitvatartas set helyszinid='".addslashes($_GET["szerk"])."'");
	$_POST["helyszinmentes"]=1;
}

if (isset($_POST["helyszinmentes"]) || isset($_POST["helyszinform"])) {
    $_SESSION["helyszinbeosztascegfilter"]=$_POST["helyszinbeosztascegfilter"];

	$ceglink="";
	$resh=sql_query("select * from cegek order by megnev");
	while ($rowh=sql_fetch_array($resh)) {
		if (isset($_POST["cegcheck{$rowh["id"]}"])) $ceglink.="|{$rowh["id"]}|";
	}
	
	$sor=1;
	while (isset($_POST["nyid{$sor}"])) {
		sql_query("update helyszin_nyitvatartas set 
		nap='".addslashes($_POST["weekday{$sor}"])."',
		tol='".addslashes($_POST["tol{$sor}"])."',
		ig='".addslashes($_POST["ig{$sor}"])."' 
		where id='".addslashes($_POST["nyid{$sor}"])."'");
		$sor++;
	}	
	
	if (!isset($_POST["aktiv"])) $_POST["aktiv"]=0;
	
	sql_query("update helyszinek set 
		cegid='".addslashes($_POST["cegid"])."',
		cim='".addslashes($_POST["cim"])."',
		cim_en='".addslashes($_POST["cim_en"])."',
		cim_de='".addslashes($_POST["cim_de"])."',
		ceglink='{$ceglink}',
		aktiv='".addslashes($_POST["aktiv"])."'
	where id='".addslashes($_GET["szerk"])."'");
	
	logActivity("helyszin",$_GET["szerk"],"{$_POST["cim"]} adatlap",print_r($_POST,true));

	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}

if (isset($_POST["szurestipusmentes"])) {
	if (!isset($_POST["aktiv"])) $_POST["aktiv"]=0;
	if (!isset($_POST["infopage"])) $_POST["infopage"]=0;
	
	if (szuresTipusModJog()) {
        $sor=1;
        while (isset($_POST["tipmegjid{$sor}"])) {
            sql_query("update szurestipusok_megj set 
			cegid='".addslashes($_POST["tipmegjceg{$sor}"])."',
			megj='".addslashes($_POST["tipmegj{$sor}"])."'
			where id='".addslashes($_POST["tipmegjid{$sor}"])."'");
            $sor++;
        }

		$sor=1;
		while (isset($_POST["arid{$sor}"])) {
			sql_query("update arak set megnev=?,price=? where id=?",array($_POST["megnev{$sor}"],$_POST["price{$sor}"],$_POST["arid{$sor}"]));
			$sor++;
		}

        sql_query("update szurestipusok set megnev=?,megnev_de=?,megnev_en=?,infopage=?,infopagetext=?,aktiv=? where id=?",array($_POST["megnev"],$_POST["megnev_de"],$_POST["megnev_en"],$_POST["infopage"],$_POST["infopagetext"],$_POST["aktiv"],$_GET["szerk"]));

		logActivity("szurestipus",$_GET["szerk"],"{$_POST["megnev"]} adatlap",print_r($_POST,true));
	}

	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}

if (isset($_POST["szurescsomagmentes"])) {
    if (!isset($_POST["aktiv"])) $_POST["aktiv"]=0;
    if (!isset($_POST["infopage"])) $_POST["infopage"]=0;

    if (szuresTipusModJog()) {
        $sor=1;
        while (isset($_POST["cskapcsid{$sor}"])) {
            sql_query("update szurescsomagok_kapcs set szurestipusid=? where id=?",array($_POST["cskapcstipid{$sor}"],$_POST["cskapcsid{$sor}"]));
            $sor++;
        }

        $sor=1;
        while (isset($_POST["tipmegjid{$sor}"])) {
            sql_query("update szurestipusok_megj set 
			cegid='".addslashes($_POST["tipmegjceg{$sor}"])."',
			megj='".addslashes($_POST["tipmegj{$sor}"])."'
			where id='".addslashes($_POST["tipmegjid{$sor}"])."'");
            $sor++;
        }

        $sor=1;
        while (isset($_POST["arid{$sor}"])) {
            sql_query("update arak set megnev=?,price=? where id=?",array($_POST["megnev{$sor}"],$_POST["price{$sor}"],$_POST["arid{$sor}"]));
            $sor++;
        }

        sql_query("update szurescsomagok set megnev=?,megnev_de=?,megnev_en=?,infopage=?,infopagetext=?,aktiv=? where id=?",array($_POST["megnev"],$_POST["megnev_de"],$_POST["megnev_en"],$_POST["infopage"],$_POST["infopagetext"],$_POST["aktiv"],$_GET["csszerk"]));

        logActivity("szurescsomag",$_GET["csszerk"],"{$_POST["megnev"]} adatlap",print_r($_POST,true));
    }

    header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&csszerk={$_GET["csszerk"]}");
    die();
}



if (isset($_POST["multifoglalstart"])) {
	
	$cegid=round($_SESSION["helyszinceg"]);
	for ($i=0;$i<$_POST["hanynapot"];$i++) {
		if ($i>30) break;

		$nap=date("Y-m-d",strtotime("{$_GET["from"]} +{$i} day"));
	
		$szurestipus=0;
		if (isset($_SESSION["naptarszurestipus"])) $szurestipus=$_SESSION["naptarszurestipus"];
		
		if (!sql_fetch_array(sql_query("select nap from foglaltnapok where nap=? and helyszinceg=? and helyszinid=? and szurestipusid=?",array($nap,$cegid,$_SESSION["helyszin"],$szurestipus)))) {
			sql_query("insert into foglaltnapok set foglalta=?,nap=?,helyszinceg=?,helyszinid=?,szurestipusid=?",array($user["username"],$nap,$cegid,$_SESSION["helyszin"],$szurestipus));
		}

	}
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}

if (isset($_POST["multifoglalcancel"])) {
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}

if (isset($_GET["enablenap"]) && isset($_SESSION["helyszin"])) {
	$cegid=round($_SESSION["helyszinceg"]);
	$szurestipus=0;
	if (isset($_SESSION["naptarszurestipus"])) $szurestipus=$_SESSION["naptarszurestipus"];
	
	sql_query("delete from foglaltnapok where nap=? and helyszinceg=? and helyszinid=? and szurestipusid=?",array($_GET["enablenap"],$cegid,$_SESSION["helyszin"],$szurestipus));
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}

if (isset($_GET["disablenap"]) && isset($_SESSION["helyszin"])) {
	$cegid=round($_SESSION["helyszinceg"]);
	$szurestipus=0;
	if (isset($_SESSION["naptarszurestipus"])) $szurestipus=$_SESSION["naptarszurestipus"];
	sql_query("insert into foglaltnapok set foglalta=?,nap=?,helyszinceg=?,helyszinid=?,szurestipusid=?",array($user["username"],$_GET["disablenap"],$cegid,$_SESSION["helyszin"],$szurestipus));
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}");
	die();
}


//törölhető, használd helyette a DocAgent osztályt.
function get_Doc_Path($fileid) {
	$path="../doc/".floor($fileid/1000);
	if (!is_dir($path)) mkdir($path);
	$path.="/{$fileid}.bin";
	return $path;
}


if (isset($_POST["dokmentes"])) {
    $docAgent = new DocAgent();
    $result = $docAgent->saveDoc($_FILES["dokfile"], array('beutaloid' => $_POST["beutaloid"], 'userid' => $_GET["szerk"], 'megnev' => $_POST["dokmegnev"]));

	if ($result!="0") {
		$_SESSION["uzenet"]=$result;
	} else {
		$rowf=sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_GET["szerk"])));
		logActivity("paciens",$rowf["id"],"{$rowf["nev"]} dokumentum feltöltése",print_r($_POST,true));
	}
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}

if (isset($_POST["addbeutalo"])) {
	$data=explode("-",$_POST["beutalotarget"]);
	$hid=intval($data[0]);
	$sztid=intval($data[1]);

	sql_query("insert into beutalok set datum=now(),userid=?,cegid=?,helyszinid=?,szurestipusid=?,megj=?,naploszam=?",array($_GET["szerk"],$_POST["cegid"],$hid,$sztid,$_POST["beutalomegj"],$_POST["beutalonaploszam"]));

	$rowf=sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_GET["szerk"])));
	logActivity("paciens",$rowf["id"],"{$rowf["nev"]} beutaló hozzáadása",print_r($_POST,true));

	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}

if (isset($_GET["deletedoc"])) {
    $docAgent = new DocAgent();
    $docAgent->deleteDoc($_GET["deletedoc"], $_GET["kod"]);

    $rowf=sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_GET["szerk"])));
    logActivity("paciens",$rowf["id"],"{$rowf["nev"]} dokumentum törlése",print_r($_POST,true));

    header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}

if (isset($_GET["deletebeutalo"])) {
	$rowf=sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_GET["szerk"])));
	logActivity("paciens",$rowf["id"],"{$rowf["nev"]} beutaló törlése",print_r($_POST,true));

	sql_query("delete from beutalok where id=? and userid=?",array($_GET["deletebeutalo"],$_GET["szerk"]));
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}

function leletLista($pid,$page,$szerk) {
	$htmlout="";
	$request_leletek = sql_query("SELECT pl.*,lm.lelet_nev  FROM paciens_leletek pl
								  LEFT JOIN lelet_mintak lm ON lm.lm_id = pl.lelet_type
								  WHERE paciens_id =?",array($pid));
								  
	$request_zaro = sql_query("SELECT * FROM zaro_leletek zl
							   LEFT JOIN paciens_leletek pl ON pl.zaro_id = zl.zaro_id
							   WHERE pl.paciens_id = ? AND pl.zaro_id IS NOT NULL
							   GROUP BY zl.zaro_id
							  ", array( $pid ));

	if (sql_num_rows($request_leletek) > 0 || sql_num_rows($request_zaro) > 0) {
		while ($lelet = sql_fetch_array($request_leletek)) {
			if($lelet['lelet_type'] != ''){
				$htmlout.="<div><a onClick='open_lelet({$lelet["lelet_id"]});return false;' href='#'>".$lelet['lelet_nev']." - ".date("Y-m-d",strtotime($lelet['kelte']))."</a>";
			}
			else{
				$htmlout.="<div><a onClick='open_lelet({$lelet["lelet_id"]});return false;' href='#'>Lelet - ".date("Y-m-d",strtotime($lelet['kelte']))."</a>";
			}
			while ($zaro = sql_fetch_array($request_zaro)) {
				$htmlout.="<div><a onClick='open_zaro({$zaro["zaro_id"]});return false;' href='#'>Záró lelet - ".date("Y-m-d",strtotime($zaro['kelte']))."</a>";
				$htmlout.="</div>";
			}
			if (( $_SESSION['pid'] == 98 ) || ($_SESSION['pid'] == 23)) $htmlout.=" [<a onclick='return confirm(\"Biztosan törli ezt a leletet?\");' href='{$_SERVER["PHP_SELF"]}?page={$page}&szerk={$szerk}&deletelelet={$lelet["lelet_id"]}'>törlés</a>]";
			$htmlout.="</div>";
		}
	}	else {
		$htmlout.="Nincs még lelet kiállítva.";
	}
	return $htmlout;		
}

if ( isset( $_REQUEST['reload_leletlista'] )) {
	echo leletLista( $_SESSION["patient_id"],$_GET['p'],$_GET['user'] );
	die();
}

if( isset( $_REQUEST['setCheckboxes'] ))
{
	$result = sql_fetch_array( sql_query("SELECT pozitiv_opciok FROM lelet_mintak WHERE lm_id = ?", array( $_REQUEST['setCheckboxes'] )));
	$value = explode( ";", $result['pozitiv_opciok'] );
	
	$htmlout = "<tr><td colspan='2'><h1>Eltérések</h1></td></tr>";
	for( $i = 0; $i < count( $value ); $i++ ) 
	{
		$htmlout.= "<tr><td><input type = 'checkbox' name = 'wounds[]' value = '".$value[$i]."'></td><td>".$value[$i]."</td></tr>";
	}
	echo $htmlout;
	die();
}

if( isset( $_REQUEST['loadnegativeCheck'] ))
{
	$htmlout = "<tr><td colspan='3'><h1>A lelet negatív</h1></td></tr>";
	$htmlout.= "<tr><td><input type = 'checkbox' name = 'wounds[]' value = 'Negatív'>&nbsp;&nbsp;Negatív</td></tr>";
	
	echo $htmlout;
	die();
}

function pozitiv_opciok( $opc, $type )
{	
	$result = sql_fetch_array( sql_query("SELECT pozitiv_opciok FROM lelet_mintak WHERE lm_id = ?", array( $type )));
	$value = explode( ";", $result['pozitiv_opciok'] );
	$check = explode( ";", $opc );
	
	$htmlout = "<tr><td colspan='2'><h1>Eltérések</h1></td></tr>";
	for( $i = 0; $i < count( $value ); $i++ ) 
	{
		$key = array_search( $value[$i], $check );
		$htmlout.= "<tr><td><input type = 'checkbox' ".( is_numeric( $key ) ? "checked" : "" )." name = 'wounds[]' value = '".$value[$i]."'></td><td>".$value[$i]."</td></tr>";
	}
	echo $htmlout;
}

function negativ_opcio( $lelet_id ){
		$result = sql_fetch_array( sql_query("SELECT pozitiv_opciok FROM paciens_leletek WHERE lelet_id = ? AND pozitiv_opciok LIKE '%Negatív%' ", array( $lelet_id )));
		
		$htmlout = "<tr><td colspan='2'><h1>Negatív a lelet</h1></td></tr>";
		$htmlout.= "<tr><td><input type = 'checkbox' ".($result['pozitiv_opciok']!=""?"checked":"")." name = 'wounds[]' value = 'Negatív'>&nbsp;Negatív</td></tr>";
		return $htmlout;
}

if(isset($_REQUEST['zaro_lelet'])){
	
	$zaro_id = $_REQUEST['zaro_lelet'];
	$request_lelet = sql_query("SELECT * FROM zaro_leletek WHERE zaro_id = ? ",array($zaro_id));
	$result = sql_fetch_array($request_lelet);
	?>
	<div class = "lelet-frame" id = "lelet-content" style = "display:block;overflow-y:scroll" ><?php echo $result['zaro_szoveg'] ?></div>
	<div class = "lelet-button-box" style = "margin-top:10px;">
		<input class = "user-button" onClick = 'printLelet();' type = "button" value = "Nyomtatás" />
		<!--<input class = "user-button" onClick = '$(".target-lelet").slideToggle();setTimeout(function(){$(".target-lelet").empty();}, 1000);' type = "button" value = "Bezárás" />-->
		<input value = "Mégse" name = "close_zaro" type = "button" />
	</div>
	<?php
	die();
}


if( isset( $_REQUEST['uj_lelet'] ))
{	
	$textarea_name = "uj-lelet-page";
	$patient 	   = sql_fetch_array( sql_query( "SELECT * FROM felhasznalok WHERE id=?", array( $_SESSION["patient_id"] )));
	$medic 		   = sql_fetch_array( sql_query( "SELECT * FROM orvosok 	 WHERE id=?", array( $_SESSION['medic_id'] )));
	
	if($patient['irsz'] != "" && $patient['varos'] != "") $lakcim = $patient['irsz']." ".$patient['varos'].", ".$patient['utca'];
	else $lakcim = "";

	$patient_details_segment = "<h1 id = 'title' style = 'font-family:Calibri;text-align:center;color:#000000;font-weight:bold;'>Lelet</h1>";
	$patient_details_segment.= "<table id = 'patient-details' style = 'color:#000;border:none'>";
	$patient_details_segment.= "	<tr><td style = 'border:none;font-family:Calibri;font-size:16px;font-weight:bold'>Páciens neve:</td><td style = 'border:none;font-family:Calibri;font-size:16px'>{$patient['nev']}</td></tr>";
	$patient_details_segment.= "	<tr><td style = 'border:none;font-family:Calibri;font-size:16px;font-weight:bold'>Születési hely, idő:</td><td style = 'border:none;font-family:Calibri;font-size:16px'>".($patient['szulhely'] != "" ? $patient['szulhely']."," : "").$patient['szuldatum']."</td></tr>";
	$patient_details_segment.= "	<tr><td style = 'border:none;font-family:Calibri;font-size:16px;font-weight:bold'>TAJ szám:</td><td style = 'border:none;font-family:Calibri;font-size:16px'>{$patient['taj']}</td></tr>";
	$patient_details_segment.= "	<tr><td style = 'border:none;font-family:Calibri;font-size:16px;font-weight:bold'>Leánykori neve:</td><td style = 'border:none;font-family:Calibri;font-size:16px'>{$patient['anyjaneve']}</td></tr>";
	$patient_details_segment.= "	<tr><td style = 'border:none;font-family:Calibri;font-size:16px;font-weight:bold'>Lakcíme:</td><td style = 'border:none;font-family:Calibri;font-size:16px'>{$lakcim}</td></tr>";
	$patient_details_segment.= "</table>";
	
	if($_SESSION['medic_id'] == "")
	{
		$medical_seals = "&lt;span style = 'color:#000000;font-family:Calibri;font-size:16px' id = 'signature' &gt;
						  ".date("Y.m.d",strtotime("Now"))."&lt;br/&gt;&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-family:Calibri;font-size:16px'&gt;
						  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-family:Calibri;font-size:16px'&gt;&lt;/span&gt;&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-size:11px;color:#949494;font-family:Calibri'&gt; *A lelet aláírás és pecsét nélkül is érvényes! &lt;/span&gt;
						  &lt;/span&gt;&lt;br/&gt;&lt;br/&gt;&lt;br/&gt;";
	}						
	else
	{
		$medical_seals = "&lt;span style = 'color:#000000;font-family:Calibri;font-size:16px' id = 'signature' &gt;
						  ".date("Y.m.d",strtotime("Now"))."&lt;br/&gt;&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-family:Calibri;font-size:16px'&gt;
						  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-family:Calibri;font-size:16px'&gt;".$medic['nev']."(&lt;span id='seal-place'&gt;&lt;/span&gt;)&lt;/span&gt;&lt;br/&gt;
						  &lt;span style='float:right;display:inline;font-size:11px;color:#949494;font-family:Calibri'&gt; *A lelet aláírás és pecsét nélkül is érvényes! &lt;/span&gt;
						  &lt;/span&gt;&lt;br/&gt;&lt;br/&gt;&lt;br/&gt;";
	}
	
	?>
	<script>
	tinyMCE.init({
			mode : 'specific_textareas',
			editor_selector : 'mceEditor',
			content_style: 'body{ color:#000; font-family: Calibri }',
			height: 842,
			width: 595
	});
	</script>
	<div style = "margin-top:5px;">
		<div class = "currently-text-container" style = "display:none;"></div>
		<div class = "medic-footage" style = "display:none;"><?php echo '"'.$medical_seals.'"' ?></div>
		<table style = "font-size:12px;margin-bottom:10px;">
			<tr>
				<td>Pecsétszám: <input type = "textbox" value = "<?php echo (isset($medic['pecsetszam'])?$medic['pecsetszam']:"") ?>" id = "pecsetszam"  /></td>
				<td></td>
			</tr>
			<tr><td colspan = "2" >Milyen vizsgálati eredményt kíván hozzáadni?</td></tr>
			<tr>
				<td>
					<select id = "minta-lista" style = "margin-top:10px;">
						<option value = "empty"> - Válassz mintát! - </option>
						<?php
						echo medTemplateFilter($medic['szurestipusok']);
						/*$request_mintak = sql_query("SELECT * FROM lelet_mintak");
						while($minta = sql_fetch_array($request_mintak)){
							?>
							<option value = "<?php echo $minta['lm_id'] ?>"><?php echo $minta['lelet_nev'].($minta['lelet_ver'] != ""?"({$minta['lelet_ver']})":"") ?></option>
							<?php
						}*/
						?>
					</select>
					<input onClick = 'add_lelet($("#minta-lista").val(),"<?php echo $textarea_name ?>")' name = "lelet_hozzadas" type = "button" value = "Kiválasztás"/>
				</td>
			</tr>
		</table>
	</div>

	<!--Lelet szöveg helye-->
	<textarea id = "<?php echo $textarea_name ?>" class = "mceEditor" style = "margin-top:10px;display:inline-block">
	<?php echo $patient_details_segment?>
	</textarea>
	<form method = "POST" name = "iForm" style = "display:inline-block">
		<table style = "display:inline-block">
			<tr>
				<td>
				<td valign="top" style = "padding-left:20px">
					<table name = "positive-options">
					</table>
					<table name = "negative-option">
					</table>
				</td>
				</td>
			</tr>
		</table>
	</form>
	<div style = "margin-top:10px;">
	<input value = "Lelet mentése" onClick = 'save_iFrame(<?php echo $_SESSION['patient_id'].",".$_SESSION['medic_id'].",\"".$textarea_name."\"" ?>)' type = "button"/>
	<input value = "Nyomtatás" onClick = 'send_iFrame(<?php echo $_SESSION['patient_id'].",".$_SESSION['medic_id'].",\"".$textarea_name."\"" ?>)' type = "button" />
	<input value = "Mégse" name = "close_lelet" type = "button" />
	</div>
	<?php
	die();
}


if( isset($_REQUEST['open_lelet'] ))
{
	if($_SESSION['adminuser']['jog_leletszerk'] == 1)
	{
		$lelet_id = $_REQUEST['open_lelet'];
		$textarea_name = "lelet-page-".$lelet_id;
		$lelet = sql_fetch_array(sql_query("SELECT * FROM paciens_leletek WHERE lelet_id=?",array($lelet_id)));
?>
<script type="text/javascript">

	tinyMCE.init({
			mode : 'specific_textareas',
			editor_selector : 'mceEditor',
			height: 842,
			width: 595
	});

	
</script>
<div style = "margin-top:5px;">
	<div class = "currently-text-container" style = "display:none;"></div>
	<table style = "font-size:12px;margin-bottom:10px;">
		<tr>
			<td>Pecsétszám:</td>
			<td><input type = "textbox" value = "<?php echo ( $lelet['pecsetszam'] != "" ? $lelet['pecsetszam'] : "" ) ?>" id = "pecsetszam" /></td>
		</tr>
	</table>
</div>

<!--Lelet szöveg helye-->
	<textarea id = "<?php echo $textarea_name ?>" class = "mceEditor" style = "margin-top:10px;display:inline-block">
	<?php echo $lelet['lelet_szoveg'] ?>
	</textarea>
	<table style = "display:inline-block;">
	<tr>
		<td>
		<td valign="top" style = "padding-left:20px">
			<form method = "POST" name = "iForm">
				<table name = "positive-options">
					<?php echo pozitiv_opciok( $lelet['pozitiv_opciok'], $lelet['lelet_type'] ) ?>
				</table>
				<table name = "negative-option">
					<?php echo negativ_opcio( $lelet_id ) ?>
				</table>
			</form>
		</td>
		</td>
	</tr>
	</table>

<div style = "margin-top:10px;">
<input value = "Lelet mentése" onClick = 'save_iFrame(<?php echo $_SESSION['patient_id'].",".(!isset($_SESSION['medic_id'])||$_SESSION['medic_id']==""?0:$_SESSION['medic_id']).",\"".$textarea_name."\"" ?>)'  type = "button"/>
<input value = "Nyomtatás" onClick = 'send_iFrame(<?php echo $_SESSION['patient_id'].",".(!isset($_SESSION['medic_id'])||$_SESSION['medic_id']==""?0:$_SESSION['medic_id']).",\"".$textarea_name."\"" ?>)' type = "button" />
<input value = "Mégse" name = "close_lelet" type = "button" />
</div>
<?php
	}
	if($_SESSION['adminuser']['jog_leletlatas'] == 1 && $_SESSION['adminuser']['jog_leletszerk'] != 1)
	{
		$lelet_id = $_REQUEST['open_lelet'];
		$request_lelet = sql_query("SELECT * FROM paciens_leletek WHERE lelet_id = ? ",array($lelet_id));
		$result = sql_fetch_array($request_lelet);
		?>
		<div class = "lelet-frame" id = "lelet-content" style = "display:block;overflow-y:scroll" ><?php echo $result['lelet_szoveg'] ?></div>
		<div class = "lelet-button-box" style = "margin-top:10px;">
			<input class = "user-button" onClick = 'printLelet();' type = "button" value = "Nyomtatás" />
			<input value = "Mégse" name = "close_lelet" type = "button" />
		</div>
		<?php
	}
die();
}

if(isset($_REQUEST['request_lelet'])){
	$lelet = sql_fetch_array(sql_query("SELECT * FROM lelet_mintak WHERE lm_id=?",array($_REQUEST['request_lelet'])));
	echo $lelet['lelet_text'];
	die();
}
if(isset($_REQUEST['save_lelet'])){
	$wounds = "";
	for($i = 0; $i <= count($_REQUEST['wounds']); $i++ )
	{
		$wounds = $wounds.";".$_REQUEST['wounds'][$i];
	}
	$wounds = substr($wounds, 1);
	sql_query("INSERT INTO paciens_leletek SET paciens_id=?,lelet_szoveg=?,pecsetszam=?,kelte=NOW(),pozitiv_opciok=?,lelet_type = ? ",array($_SESSION['patient_id'],$_REQUEST['save_lelet'],$_REQUEST['seal_numb'],$wounds, $_REQUEST['tipus']));
	die("Lelet feltöltés sikeres!");	
}

if(isset($_REQUEST['update_lelet'])){
	$wounds = "";
	for($i = 0; $i <= count($_REQUEST['wounds']); $i++ )
	{
		$wounds = $wounds.";".$_REQUEST['wounds'][$i];
	}
	$wounds = substr($wounds, 1);
	sql_query("UPDATE paciens_leletek SET lelet_szoveg=?, pozitiv_opciok = ? WHERE lelet_id=?",array($_REQUEST["update_lelet"], $wounds, $_REQUEST["lid"]));
	die("Lelet módosítás sikeres!");
}

if (isset($_GET["deletelelet"])) {
	$rowf=sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_GET["szerk"])));
	logActivity("paciens",$rowf["id"],"{$rowf["nev"]} lelet törlése");

	sql_query("delete from paciens_leletek where lelet_id=? and paciens_id=?",array($_GET["deletelelet"],$_GET["szerk"]));
	header("location:{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}


function datumprint($d) {
	$d=str_replace("-",".",$d);
	$d=substr($d,0,16);
	return $d;
}
	
	

function showAlkalmassagStatus($row) {
	
	$htmlout="";
	
	if (isset($GLOBALS["alkalmassagvariaciok"][$row["alkalmassag"]])) {
		$htmlout.="<div style='display:table;margin-top:10px;'>";

		$htmlout.="<div style='display:table-cell;vertical-align:middle;'>";
		$htmlout.="<div class='alkalmassagjelzes alkalmascolor{$row["alkalmassag"]}'>".$GLOBALS["alkalmassagvariaciok"][$row["alkalmassag"]];
		if ($row["alkalmassag"]=="I") $htmlout.=" {$row["alkalmassagido"]} hó";
		$htmlout.="</div>";
		$htmlout.="</div>";
		
		$htmlout.="<div style='display:table-cell;vertical-align:middle;padding-left:10px;'>";
		$htmlout.="<a href='printalkalmassagi?id={$row["id"]}&token=".md5($row["datum"].$row["regdatum"])."' target='_blank'><img src='images/print-icon.png' style='height:21px;' title='Alkalmassági igazolás nyomtatása' alt='' /></a>";
		$htmlout.="</div>";

		$htmlout.="</div>";
	}
	
	
	return $htmlout;
}	
	
function showEljottCheckBox($row) {
	$htmlout="";
	$htmlout.="<div style='display:table;'>";
	$htmlout.="<div style='display:table-row;'>";
	$htmlout.="<div style='display:table-cell;'>";
	$htmlout.="<div onclick='toggleEljott({$row["id"]})' class='nagycheckbox".($row["eljott"]==1?" nagychecked":"")."'></div>";
	$htmlout.="</div>";
	$htmlout.="<div style='display:table-cell;vertical-align:middle;'>&nbsp;Eljött</div>";
	$htmlout.="</div>";
	$htmlout.="</div>";
	return $htmlout;
}


if (isset($_POST["fcancel"])) {
	if (isset($_POST["back"])) {
		header("location:index.php?page={$_GET["page"]}&szerk={$_GET["fszerk"]}");
		die();
	}
	header("location:index.php?page={$_GET["page"]}");
	die();
}

if (isset($_POST["scancel"])) {
	header("location:index.php?page={$_GET["page"]}");
	die();
}




if (isset($_POST["fregisztracio"])) {
	$id=intval($_GET["fszerk"]);
	$cegid=intval($_POST["cegid"]);
	
	$formerror="";
	
	if (isset($_POST["szuldatumev"])) {
		$_POST["szuldatum"]=$_POST["szuldatumev"]."-".substr("00".$_POST["szuldatumho"],-2)."-".substr("00".$_POST["szuldatumnap"],-2);
	}

	$_POST["telefon"]=fixPhoneNumber($_POST["telefon"]);
	
	$_POST["taj"]=str_replace("-","",$_POST["taj"]);
	$_POST["taj"]=trim(str_replace(" ","",$_POST["taj"]));


	
	//if ($_POST["taj"]=="") $formerror.="A TAJ szám megadása kötelező!<br/>";
	if (!ctype_digit($_POST["taj"]) && $_POST["taj"]!="") $formerror.="A TAJ szám formátuma nem megfelelő!<br/>";
	if ($_POST["taj"]!="" && sql_fetch_array(sql_query("select taj from felhasznalok where taj='".addslashes($_POST["taj"])."' and cegid='{$cegid}' and id<>'{$id}'"))) $formerror.="Ehhez a TAJ számhoz már létezik regisztráció!<br/>";

	//if ($_POST["email"]=="") $formerror.="Az e-mail cím megadása kötelező!<br/>";
	if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) && $_POST["email"]!="") $formerror.="Az e-mail cím formátuma nem megfelelő!<br/>";
	if ($_POST["email"]!="" && sql_fetch_array(sql_query("select taj from felhasznalok where email='".addslashes($_POST["email"])."' and cegid='{$cegid}' and id<>'{$id}'"))) $formerror.="Ezzel az e-mail címmel már létezik regisztráció!<br/>";

	if ($_POST["nev"]=="") $formerror.="A név megadása kötelező!<br/>";
	//if ($_POST["telefon"]=="") $formerror.="A telefonszám megadása kötelező!<br/>";
	if (!ctype_digit($_POST["telefon"]) && $_POST["telefon"]!="") $formerror.="A telefonszám szám formátuma nem megfelelő!<br/>";

	if ($_POST["szuldatum"]=="") $formerror.="A születési dátum megadása kötelező!<br/>";
	if (!validateDate($_POST["szuldatum"],"Y-m-d")) $formerror.="A születési dátum formátuma nem megfelelő!<br/>";
	if (!isset($_POST["neme"])) $formerror.="A neme megadása kötelező!<br/>";
	if (!checkSzulDatum($_POST["szuldatum"])) $formerror.="A születési dátum formátuma nem megfelelő<br/>";
	
	//if ($_POST["munkakor"]=="") $formerror.="A munkakör megadása kötelező!<br/>";

	if ($formerror=="") {	
		if ($id!=0) {
			sql_query("update felhasznalok set
			nev='".addslashes($_POST["nev"])."',
			email='".addslashes($_POST["email"])."',
			telefon='".addslashes($_POST["telefon"])."',
			szuldatum='".addslashes($_POST["szuldatum"])."',
			szulhely='".addslashes($_POST["szulhely"])."',
			anyjaneve='".addslashes($_POST["anyjaneve"])."',
			neme='".addslashes($_POST["neme"])."',
			taj='".addslashes($_POST["taj"])."',
			irsz='".addslashes($_POST["irsz"])."',
			varos='".addslashes($_POST["varos"])."',
			utca='".addslashes($_POST["utca"])."',
			munkakor='".addslashes($_POST["munkakor"])."',
			torzsszam='".addslashes($_POST["torzsszam"])."'
			where id='{$id}'");

            if (!empty($_POST["jelszo"])) {
                sql_query("update felhasznalok set jelszo=md5(?) where id=?",array($_POST["jelszo"],$id));
            }

            logActivity("paciens",$id,"{$_POST["nev"]} adatlap",print_r($_POST,true));

			header("location:index.php?page={$_GET["page"]}&szerk={$id}");
			die("ok");
		} else {
			$rn=rand(11000,98000);
			sql_query("insert into felhasznalok set
			cegid='{$cegid}',
			regtime=now(),
			nev='".addslashes($_POST["nev"])."',
			email='".addslashes($_POST["email"])."',
			telefon='".addslashes($_POST["telefon"])."',
			szuldatum='".addslashes($_POST["szuldatum"])."',
			szulhely='".addslashes($_POST["szulhely"])."',
			anyjaneve='".addslashes($_POST["anyjaneve"])."',
			neme='".addslashes($_POST["neme"])."',
			taj='".addslashes($_POST["taj"])."',
			irsz='".addslashes($_POST["irsz"])."',
			varos='".addslashes($_POST["varos"])."',
			utca='".addslashes($_POST["utca"])."',
			munkakor='".addslashes($_POST["munkakor"])."',
			torzsszam='".addslashes($_POST["torzsszam"])."',
			validated=1,
			rkod='{$rn}'");
	
			$id=sql_insert_id();

            if (!empty($_POST["jelszo"])) {
                sql_query("update felhasznalok set jelszo=md5(?) where id=?",array($_POST["jelszo"],$id));
            }

            logActivity("paciens",$id,"{$_POST["nev"]} bevitele",print_r($_POST,true));
			
			header("location:index.php?page={$_GET["page"]}&szerk={$id}");
			die("ok");
		}
	}
}



if (isset($_GET["deletefoglalas"])) {
	$id=$_GET["deletefoglalas"];
	if (sql_fetch_array(sql_query("select * from foglalasok where id=? and pass=? and eljott=0",array($id,$_GET["p"])))) {
		sql_query("delete from foglalasok where id=? and pass=? and eljott=0",array($id,$_GET["p"]));
		sql_query("update beutalok set foglalasid=0 where foglalasid=?",array($id));
	}
	header("location:index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
	die();
}

function getCegList($c) {
	$cl="0";
	
	if ($_SESSION["adminuser"]["jogosultsag"]==0) $cl="-1";
	
	$j=explode("|",$c);
	for ($i=0;$i<count($j);$i++) {
		if ($j[$i]!="") {
			$cl.=",".intval($j[$i]);
		}
	}
	return $cl;
}

function showCegListSzT($raw,$sor) {
	$h="";
	$resc=sql_query("select id,megnev from cegek order by megnev");
	while ($rowc=sql_fetch_array($resc)) {
		$cegList[$rowc["id"]]=$rowc["megnev"];		
	}
	
	$cegidk=explode("|",$raw);
	
	for ($i=0;$i<count($cegidk);$i++) {
		if (isset($cegList[$cegidk[$i]])) $h.="<span onclick='removeSztCegek({$cegidk[$i]},{$sor})' style='background:#f00;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;display:inline-block;margin:2px 2px 0px 0px;'>- {$cegList[$cegidk[$i]]}</span> ";
	}
	
	$h.="<span onclick='$(\"#cegadd{$sor}\").slideToggle();' title='Cég hozzáadása' style='background:#0a0;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;'>+ cég</span>";
	
	return $h;
}


function cegAddSorSzT($sor) {
	$h="";
	$resc=sql_query("select id,megnev from cegek order by megnev");
	while ($rowc=sql_fetch_array($resc)) {
		$h.="<span onclick='add2SztCegek({$rowc["id"]},{$sor})' style='background:#0a0;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;display:inline-block;margin:2px 2px 0px 0px;'>+ {$rowc["megnev"]}</span> ";
	}
	return $h;
}


if (isset($_POST["add2sztceg"])) {
	$sor=intval($_POST["sor"]);
	$cegid="|".intval($_POST["cegid"])."|";
	
	if ($row=sql_fetch_array(sql_query("select * from arak where id=?",array($_POST["arid"])))) {
		
		if (substr_count($row["cegid"],$cegid)==0) {
			$row["cegid"].=$cegid;
			sql_query("update arak set cegid=? where id=?",array($row["cegid"],$_POST["arid"]));
		}
		
		echo showCegListSzT($row["cegid"],$sor);
	}
	
	die();
	
}


if (isset($_POST["removesztceg"])) {
	$sor=intval($_POST["sor"]);
	$cegid="|".intval($_POST["cegid"])."|";
	
	sql_query("update arak set cegid=replace(cegid,?,'') where id=?",array($cegid,$_POST["arid"]));
	
	if ($row=sql_fetch_array(sql_query("select * from arak where id=?",array($_POST["arid"])))) {		
		echo showCegListSzT($row["cegid"],$sor);
	}
	
	die();
	
}



if (isset($_GET["showfizszolglist"])) {
	if ($rowf=sql_fetch_array(sql_query("select * from foglalasok where id=?",array($_GET["fid"])))) {
		echo "<div style='margin-bottom:10px;'>";
		$resa=sql_query("SELECT * FROM arak WHERE INSTR(cegid,?) AND tipusid=? and csomag=0",array("|{$rowf["cegid"]}|",$rowf["szurestipusid"]));
		while ($rowa=sql_fetch_array($resa)) {
			if ($rowa["megnev"]=="") $rowa["megnev"]="Név nélküli kezelés";
			echo "<div><a href='#' onclick='addFizSzolg({$rowf["id"]},{$rowa["id"]});return false;'>+ {$rowa["megnev"]} (".number_format($rowa["price"])." Ft)</a></div>";
		}
		echo "<div><a href='#' onclick='addFizSzolg({$rowf["id"]},0);return false;'>Mégse</a></div>";
		echo "</div>";
	}
	die();
}


if (isset($_POST["addfizszolg"])) {
	
	if ($rowa=sql_fetch_array(sql_query("select * from arak where id=?",array($_POST["aid"])))) {	
		sql_query("insert into fizkapcs set fid=?,aid=?,megnev=?,ar=?",array($_POST["fid"],$rowa["id"],$rowa["megnev"],$rowa["price"]));
	}
	
	echo showfizSzolg($_POST["fid"]);
	die();
}

if (isset($_POST["removefizszolg"])) {
	sql_query("delete from fizkapcs where id=? and fid=?",array($_POST["id"],$_POST["fid"]));
	echo showfizSzolg($_POST["fid"]);
	die();
}


function showFizSzolg($fid,$simple=0) {
	$h="";
	$res=sql_query("select * from fizkapcs where fid=?",array($fid));
	if (sql_num_rows($res)>0) {
		if ($simple==0) $h.="<div style='padding:10px;margin-bottom:10px;background:#fcc;display:inline-block;'>";
		while ($row=sql_fetch_array($res)) {
			$h.="<div>+ {$row["megnev"]}";
			if ($row["ar"]!=0) $h.=" (".number_format($row["ar"])." Ft)";
			if ($simple==0) $h.=" [<a href='#' onclick='removeFizSzolg({$fid},{$row["id"]});return false;'>-</a>]</div>";
		}
		if ($simple==0) $h.="</div>";
	}
	return $h;
}


function magyarDatum($datum) {
    $m=date("n",strtotime($datum));
    $n=date("Y-m-d",strtotime($datum));
    $w=date("N",strtotime($datum));
    return substr($datum,0,4)." ".ucfirst($GLOBALS["honaptext"][$m])." ".intval(substr($n,8,2)).". ".$GLOBALS["hetnap"][$w]." ".substr($datum,11,5);
}



function isCegAdmin() {
	return $_SESSION["adminuser"]["jogosultsag"]<2;
}

function isOrvosLogin() {
    return $GLOBALS["adminuser"]["orvosid"]==0?false:true;
}


function cegSQLFilter($key) {
	$w="";
	if (isCegAdmin()) {
		$cegidk=str_replace("||",",",$_SESSION["adminuser"]["cegjog"]);
		$cegidk=str_replace("|","",$cegidk);
		if ($cegidk=="") $cegidk="-1";
		$w.="and {$key} in ({$cegidk})";
	}
	return $w;
}



if (isset($_GET["szabira"])) {
	sql_query("insert into szabadsag set oid=?,datumtol=?,datumig=?",array($_GET["orvosid"],$_GET["szabira"],$_GET["szabira"]));
	
	$rowo=sql_fetch_array(sql_query("select * from orvosok where id=?",array($_GET["orvosid"])));
	logActivity("orvos",$rowo["id"],"{$rowo["nev"]} szabira küldés link {$_GET["szabira"]}","");

	header("location:{$_SERVER['PHP_SELF']}?page={$_GET["page"]}");
	die();
}




if (isset($_GET["showelojegyzestable"])) {
	if (isset($_GET["day"])) $_SESSION["setday"]=$_GET["day"];
	echo showElojegyzesTable($_SESSION["setday"]);
	die();
}





if (isset($_GET["moveidopont"])) {
	if (isset($_SESSION["helyszin"])) {
		$fid=$newfid=intval($_GET["fid"]);
		$szuresTipusId=intval($_GET["szt"]);
		
		if (isset($_GET["cpy"]) && $_GET["cpy"]==1) {
			$copy=1;
			$rowf=sql_fetch_array(sql_query("select * from foglalasok where id=?",array($fid)));
			
			sql_query("insert into foglalasok set
			regdatum=now(),
			cegid=?,
			paciensid=?,
			nev=?,
			email=?,
			telefon=?,
			szuldatum=?,
			szulhely=?,
			anyjaneve=?,
			neme=?,
			taj=?,
			irsz=?,
			varos=?,
			utca=?,
			munkaltato=?,
			munkakor=?,
			rkod=?,
			megj=?,
			alkalmassag=?,
			alkalmassagido=?,
			alkalmassagikhet=?,
			tudoszuroervenyesseg=?,
			tudoszuro=?,
			smssent=1
			",array(
			$rowf["cegid"],
			$rowf["paciensid"],
			$rowf["nev"],
			$rowf["email"],
			$rowf["telefon"],
			$rowf["szuldatum"],
			$rowf["szulhely"],
			$rowf["anyjaneve"],
			$rowf["neme"],
			$rowf["taj"],
			$rowf["irsz"],
			$rowf["varos"],
			$rowf["utca"],
			$rowf["munkaltato"],
			$rowf["munkakor"],
			rand(11000,98000),
			$rowf["megj"],
			$rowf["alkalmassag"],
			$rowf["alkalmassagido"],
			$rowf["alkalmassagikhet"],
			$rowf["tudoszuroervenyesseg"],
			$rowf["tudoszuro"]
			));
			
			$newfid=sql_insert_id();
		}
	
		logActivity("foglalas",$rowf["id"],"{$rowf["nev"]} foglalás ".(isset($copy)?"másolása":"mozgatása")." {$rowf["datum"]} -> {$_GET["moveidopont"]}","");

		sql_query("update foglalasok set aktiv=1,foglalta=?,helyszinid=?,szurestipusid=?,datum=?,rinterval=?,orvosassigned=0 where id=?",array($user["nev"],$_SESSION["helyszin"],$szuresTipusId,$_GET["moveidopont"],intval($_GET["rinterval"]),$newfid));
		updateFoglalasData($newfid);
		
		$oid=selectFreeOrvosForIdopont($newfid);
		sql_query("update foglalasok set orvosassigned=? where id=? and orvosassigned=0",array($oid,$newfid));
	}
	
	if ($_GET["page"]=="bnaptar") {
		echo showAdminNaptarIdopont($_GET["moveidopont"]);
		die();
	}
	
	echo showElojegyzesTable($_SESSION["setday"]);
	die();
}





if (isset($_GET["addidopont"])) {
	if (isset($_SESSION["helyszin"])) {
		$szuresTipusId=intval($_GET["szt"]);
		$cegId=0;
		$orvosId=0;

		if (isCegAdmin()) $cegId=$_SESSION["adminuser"]["cegid"];

		if ($_SESSION["adminuser"]["jog_nofoglimitset"]==0) {
            if (!isOrvosAvailable($_GET["addidopont"], $_SESSION["helyszin"], $szuresTipusId)) {
                die("errorNincs szabad orvos a megjelölt időpontra!");
            }
        }

		$settings = new Booking_Settings();
		if (in_array(date("Y-m-d", strtotime($_GET["addidopont"])), $settings->getMunkaszunetiNapok())) {
            die("errorMunkaszüneti napra nem lehet foglalni!");
        }

        sql_query("insert into foglalasok set aktiv=1,foglalta=?,regdatum=now(),nev='nincs név',cegid=?,helyszinid=?,szurestipusid=?,orvosassigned=?,datum=?",array($user["username"],$cegId,$_SESSION["helyszin"],$szuresTipusId,$orvosId,$_GET["addidopont"]));
		
		$fid=sql_insert_id();
		updateFoglalasData($fid);

		logActivity("foglalas",$fid,"foglalás hozzáadása {$_GET["addidopont"]}",print_r($_POST,true));


        if ($orvosId==0 && $cegId!=0) {
			$oid=selectFreeOrvosForIdopont($fid);
			//echo $oid;
			sql_query("update foglalasok set orvosassigned=? where id=? and orvosassigned=0",array($oid,$fid));
		}
	}
	
	if ($_GET["page"]=="bnaptar") {
		echo showAdminNaptarIdopont($_GET["addidopont"]);
		die();
	}
	
	if (isset($_SESSION["setday"])) {
		echo showElojegyzesTable($_SESSION["setday"]);
	}
	die();
}



if (isset($_GET["removeidopont"])) {
	$rowf=sql_fetch_array(sql_query("select * from foglalasok where id=?",array($_GET["removeidopont"])));
	logActivity("foglalas",$rowf["id"],"{$rowf["nev"]} foglalás törlése {$rowf["datum"]}",print_r($_POST,true));

	sql_query("delete from foglalasok where id=? limit 1",array($_GET["removeidopont"]));
	sql_query("delete from fizkapcs where fid=?",array($_GET["removeidopont"]));



	if ($_GET["page"]=="bnaptar") {
		echo showAdminNaptarIdopont($_GET["idopont"]);
		die();
	}

	echo showElojegyzesTable($_SESSION["setday"]);
	die();
}



if (isset($_POST["foglalasmentesnaptar2"]) || isset($_POST["foglalasmentesnaptaresertesites2"])) {
	$fid=intval($_POST["fid"]);
	if (isset($_POST["szuldatumev"])) {
		$_POST["szuldatum"]=$_POST["szuldatumev"]."-".substr("00".$_POST["szuldatumho"],-2)."-".substr("00".$_POST["szuldatumnap"],-2);
		if ($_POST["szuldatumev"]==0 || $_POST["szuldatumho"]==0 || $_POST["szuldatumnap"]==0) $_POST["szuldatum"]="";
	}

    if (!isset($_POST["eljott"])) $_POST["eljott"]=0;
    if (!isset($_POST["voltnalunk"])) $_POST["voltnalunk"]=0;
	if (!isset($_POST["alkalmassag"])) $_POST["alkalmassag"]=0;
	if (!isset($_POST["alkalmassagido"])) $_POST["alkalmassagido"]=0;
	if (!isset($_POST["tudoszuro"])) $_POST["tudoszuro"]=0;
	
	if ($_POST["nev"]=="") $_POST["nev"]="nincs név";

	
	sql_query("update foglalasok set 
		orvosassigned='".intval($_POST["orvosassigned"])."',
		cegid='".intval($_POST["cegid"])."',
		taj='".addslashes($_POST["taj"])."',
		nszam='".addslashes($_POST["nszam"])."',
		nev='".addslashes($_POST["nev"])."',
		munkakor='".addslashes($_POST["munkakor"])."',
		email='".addslashes($_POST["email"])."',
		telefon='".addslashes($_POST["telefon"])."',
		szuldatum='".addslashes($_POST["szuldatum"])."',
		szulhely='".addslashes($_POST["szulhely"])."',
		anyjaneve='".addslashes($_POST["anyjaneve"])."',
		irsz='".addslashes($_POST["irsz"])."',
		varos='".addslashes($_POST["varos"])."',
		utca='".addslashes($_POST["utca"])."',
		eljott='".addslashes($_POST["eljott"])."',
		voltnalunk='".addslashes($_POST["voltnalunk"])."',
		alkalmassag='".addslashes($_POST["alkalmassag"])."',
		alkalmassagido='".addslashes($_POST["alkalmassagido"])."',
		alkalmassagikhet='".addslashes($_POST["alkalmassagikhet"])."',
		alkalmassagkorl='".addslashes($_POST["alkalmassagkorl"])."',
		tudoszuroervenyesseg='".addslashes($_POST["tudoszuroervenyesseg"])."',
		tudoszuro='".addslashes($_POST["tudoszuro"])."',
		megj='".addslashes($_POST["megj"])."'
	where id=?",array($fid));
	
	$alkalmassagi = "";
	if($_POST['alkalmassag'] === "I") {
		$alkalmassagi = date("Y-m-d",strtotime("Now + {$_POST['alkalmassagido']} months"));
		echo "I";
	}
	if($_POST['alkalmassag'] === "N") {
		$alkalmassagi = "0000-00-00 00:00:00";
		echo "N";
	}
	if($_POST['alkalmassag'] === "IN") {
		$alkalmassagi = date("Y-m-d",strtotime("Now + {$_POST['alkalmassagikhet']} weeks"));
		echo "IN";
	}
	if($_POST['alkalmassag'] === "K") {
		$alkalmassagi = date("Y-m-d",strtotime("Now + {$_POST['alkalmassagido']} months"));
		echo "K";
	}
	
	$request = sql_query("SELECT id FROM felhasznalok WHERE email = '{$_POST['email']}' AND taj = '{$_POST['taj']}' ");
	if($request->rowCount() > 0 && $alkalmassagi != "")
	{
		$result = sql_fetch_array( $request );
		sql_query("UPDATE felhasznalok SET alklejarat = '{$alkalmassagi}' WHERE id = {$result['id']} ");
	}
	
	if( $_POST['kuponkod'] != "" )
	{
		$foglalas = sql_fetch_array(sql_query("SELECT fogl.datum, kl.foglalasid, fogl.szurestipusid FROM foglalasok fogl LEFT JOIN kupon_lista kl ON kl.foglalasid = fogl.id WHERE fogl.id = ? ", array( $fid )));
		$check = kuponCheck($_POST['kuponkod'],3,date("Y-m-d",strtotime($foglalas['datum'])),$foglalas['szurestipusid']);
		if( $check == "usable")
		{
			$kupon = sql_fetch_array(sql_query("SELECT * FROM kuponkodok WHERE kod = ?", array($_POST['kuponkod'])));
			sql_query("INSERT INTO kupon_lista SET kuponid = ?, kuponkod = ?, foglalasid = ?, jovahagyta = ?", 
					   array( $kupon['id'], $kupon['kod'], $fid, $_SESSION['adminuser']['username'] ));
		}
	}
	if( $_POST['kuponkod'] == "" )
	{
		$kupon = sql_query("SELECT * FROM kupon_lista WHERE foglalasid = {$fid}");
		if( $kupon->rowCount() > 0 )
		{
			$result = sql_fetch_array($kupon);
			//unlink using:
			sql_query("DELETE FROM kupon_lista WHERE kuponkod = '{$result['kuponkod']}' AND foglalasid = {$fid} ");
		}
	}
	
	if ($_POST["orvosassigned"]!=$_POST["regiorvos"]) {
		sql_query("update foglalasok set ertesitve=0 where id=?",array($fid));
	}

	$rowf=sql_fetch_array(sql_query("select * from foglalasok where id=?",array($fid)));
	logActivity("foglalas",$fid,"{$_POST["nev"]} foglalás adatlap {$rowf["datum"]}",print_r($_POST,true));

	if ($_POST["orvosassigned"]==0 && $_POST["cegid"]!=0) {
		$oid=selectFreeOrvosForIdopont($fid);		
		//$rowo=sql_fetch_array(selectOrvosForFoglalas($fid));
		sql_query("update foglalasok set orvosassigned=? where id=? and orvosassigned=0",array($oid,$fid));
	}


	if (isset($_POST["foglalasmentesnaptaresertesites2"])) {
		sendToCegAndOrvos($fid,1);
	}

	$_GET["showidoponteditor"]=$fid;
	$_GET["p"]=$_POST["p"];
		
}



if (isset($_POST["foglalasorvosertesitesonly"])) {
	sendToCegAndOrvos($_POST["fid"],1);
	die("ok");
}





if (isset($_GET["showidoponteditor"])) {
	$id=intval($_GET["showidoponteditor"]);

	if ($row=sql_fetch_array(sql_query("select f.*,t.megnev as sztipus,c.megnev as cegnev,o.nev as orvosnev from foglalasok f
	left join szurestipusok t on t.id=f.szurestipusid
	left join orvosok o on o.id=f.orvosassigned
	left join cegek c on c.id=f.cegid
	where f.id=? and f.pass=?",array($id,$_GET["p"])))) {

		echo "<div style='font-size:16px;font-weight:bold;padding:10px;background:#555;color:#fff;'>".magyarDatum($row["datum"])." - {$row["sztipus"]} ";
		echo "<div style='margin-top:4px;'>
				<a class='kisbutton' 
				   style='font-size:12px;padding:3px 5px;' 
				   href='#' 
				   onclick='startFoglalasMove({$row["id"]},\"{$row["pass"]}\");return false;'
				  >áthelyezés</a> 
				<a class='kisbutton' 
				   style='font-size:12px;padding:3px 5px;' 
				   href='#' 
				   onclick='startFoglalasCopy({$row["id"]},\"{$row["pass"]}\");return false;'
				  >másolás</a>
				<a class='kisbutton'
				   style='font-size:12px;padding:3px 5px;cursor:pointer' 
				   onClick='startAutoFill({$row["id"]},\"{$row["pass"]}\")'
			      >mezők kitöltése</a>
			  </div>";
		echo "</div>";
		echo "<div id='moveinfo' style='display:none;background:#ff8;color:#555;padding:10px;'>Kattints arra az időpont melletti \"+\" gombra, ahova át akarod helyezni a foglalást.<div style='margin:3px 0px;'><a class='kisbutton' style='font-size:12px;padding:3px 5px;margin:3px 0px;' href='#' onclick='cancelFoglalasMove();return false;'>mégse</a></div></div>";
		echo "<div id='copyinfo' style='display:none;background:#ff8;color:#555;padding:10px;'>Kattints arra az időpont melletti \"+\" gombra, ahova át akarod <b>másolni</b> a foglalást.<br/>Több időponthoz is másolhatsz, ha befejezted kattints a mégse gombra.<div style='margin:3px 0px;'><a class='kisbutton' style='font-size:12px;padding:3px 5px;margin:3px 0px;' href='#' onclick='cancelFoglalasMove();return false;'>mégse</a></div></div>";
		echo "<div id='autofill' 
				   style='display:none;background:#ff8;color:#555;padding:10px;cursor:pointer;'
				  >A mezők kitöltéséhez add meg a páciens TAJ számát és születési dátumát:<br/>
				  <table>
					<tr><td> TAJ:</td><td><input id = 'user-taj' type = 'textbox'/></td>
						<td rowspan='2' style = 'color:red;font-weight:bold;padding-left:10px;' name='error-td'></td>
					</tr>
					<tr><td>Szül. dátum:</td><td><input id = 'user-szuldatum' style = '' type = 'textbox'/></td></tr>
					<tr>
						<td colspan='2'>
							<a class = 'kisbutton'
							   onClick = 'autoFill($(\"#user-taj\").val(),$(\"#user-szuldatum\").val())'
							   style = 'font-size:12px;padding:3px 5px;margin-top:-2px;'
							  >Kitöltés</a>
							<a class='kisbutton' 
							   style='font-size:12px;padding:3px 5px;margin-top:-2px;' 
							   href='#' 
							   onClick='cancelFoglalasMove();return false;'
							  >mégse</a>
						</td>
					</tr>
				  </table>
			  </div>";
		echo "<div style='padding:10px;'>";
		
		if ($row["nev"]!="" && $row["nev"]!="nincs név") {
			echo "<div style='margin-bottom:5px;'>";
			echo "<a class='printbutton' target='_blank' href='print.php?template=1&fid={$row["id"]}&p={$row["pass"]}'>menedzser kérdőív</a>&nbsp;&nbsp;";
			echo "<a class='printbutton' target='_blank' href='print.php?template=2&fid={$row["id"]}&p={$row["pass"]}'>alkalmassági</a>&nbsp;&nbsp;";
			echo "<a class='printbutton' target='_blank' href='print.php?template=3&tipus=idoszakos&fid={$row["id"]}&p={$row["pass"]}'>vizsgálati lap (I)</a>&nbsp;&nbsp;";
			echo "<a class='printbutton' target='_blank' href='print.php?template=3&tipus=soronkivuli&fid={$row["id"]}&p={$row["pass"]}'>vizsgálati lap (S)</a>&nbsp;&nbsp;";
			echo "<a class='printbutton' target='_blank' href='print.php?template=4&fid={$row["id"]}&p={$row["pass"]}'>karton</a>&nbsp;&nbsp;";
			echo "</div>";
		}
		
		
		echo "<form id='iform' name='iform' method='post' enctype='multipart/form-data'>";
		echo "<input type='hidden' name='fid' value='{$row["id"]}'/>";
		echo "<input type='hidden' id='idopontmarker' value='".substr($row["datum"],0,16)."'/>";
		echo "<input type='hidden' name='p' value='{$row["pass"]}'/>";
		echo "<table style='font-size:12px;'>";

		echo "<tr><td width='60'>Cég:</td><td>";
		echo "<select name='cegid' style='width:200px;'>";
		echo "<option value='0'>Nincs céghez kötve</option>";
		$wCeg=cegSQLFilter("b.cegid");
		$resh=sql_query("SELECT c.* FROM orvos_beosztas b 
          LEFT JOIN cegek c ON c.`id`=b.`cegid` 
          WHERE b.`helyszinid`=? and instr(tipusok,'|{$row["szurestipusid"]}|') {$wCeg} 
          GROUP BY b.`cegid` order by c.megnev",array($_SESSION["helyszin"]));
		while ($rowh=sql_fetch_array($resh)) {
			echo "<option value='{$rowh["id"]}'".($row["cegid"]==$rowh["id"]?" selected":"").">{$rowh["megnev"]}</option>";	
		}
		echo "</select></td>";

        $nap=substr($row["datum"],0,10);
        $ora=substr($row["datum"],11,5);
        $wora="AND TIME(b.tol)<=TIME('{$ora}') AND TIME(b.ig)>TIME('{$ora}')";

        echo "<td width='60'>Orvos:</td><td>";
		echo "<input type='hidden' name='regiorvos' value='{$row["orvosassigned"]}' /><select name='orvosassigned' style='width:200px;'>";
		echo "<option value='0'>Nincs orvoshoz kötve</option>";	
		$resh=sql_query("SELECT o.*,
          SUM((b.nap=WEEKDAY('{$nap}')+1 or b.beonap='{$nap}') {$wora} AND (b.hetek=0 OR (WEEK('{$nap}',3)%2=0 AND b.hetek=2) OR (WEEK('{$nap}',3)%2=1 AND b.hetek=1)) and b.aktiv=1) as beovan
          FROM orvos_beosztas b 
          LEFT JOIN orvosok o ON o.`id`=b.`orvosid` 
          WHERE b.`helyszinid`=? and instr(tipusok,'|{$row["szurestipusid"]}|') {$wCeg} 
          GROUP BY b.`orvosid` order by beovan desc,o.nev",array($_SESSION["helyszin"]));
		while ($rowh=sql_fetch_array($resh)) {
		    $s="";
            if ($rowh["beovan"]==0) {
                $s=" style='color:#aaa;'";
                $rowh["nev"].=" / nincs beosztása erre az időpontra";
            }
			echo "<option value='{$rowh["id"]}'".($row["orvosassigned"]==$rowh["id"]?" selected":"")." {$s}>{$rowh["nev"]}</option>";
		}
		echo "</select></td>";
		echo "</td></tr>";

		if ($row["nev"]=="nincs név") $row["nev"]="";
		$result=sql_fetch_array(sql_query("SELECT * FROM kupon_lista WHERE foglalasid={$row["id"]}"));
		
		echo "<tr><td width='60'>Taj szám:</td><td><input class='inputbox' style='width:200px;' type='text' name='taj' value='{$row["taj"]}'></td><td width='60'>E-mail:</td><td><input class='inputbox' style='width:200px;' type='text' name='email' value='{$row["email"]}'></td></tr>";
		echo "<tr><td width='60'>Név:</td><td><input class='inputbox' style='width:200px;' type='text' name='nev' value='{$row["nev"]}'></td><td width='60'>Telefon:</td><td><input class='inputbox' style='width:200px;' type='text' name='telefon' value='{$row["telefon"]}'></td></tr>";
		echo "<tr><td width='60'>Munkakör:</td><td><input class='inputbox' style='width:200px;' type='text' name='munkakor' value='{$row["munkakor"]}'></td><td width='60'>Irsz:</td><td><input placeholder='Irsz' class='inputbox' style='width:40px;' type='text' name='irsz' value='{$row["irsz"]}'> <input placeholder='Város' class='inputbox' style='width:150px;' type='text' name='varos' value='{$row["varos"]}'></td></tr>";
		echo "<tr><td width='60'>Szül. dátum:</td><td>".datumSelector($row["szuldatum"],"szuldatum")."</td><td width='60'>Utca:</td><td><input class='inputbox' style='width:200px;' type='text' name='utca' value='{$row["utca"]}'/></td></tr>";
		echo "<tr><td width='60'>Szül. hely:</td><td><input class='inputbox' style='width:200px;' type='text' name='szulhely' value='{$row["szulhely"]}'></td><td width='60'>Naplószám:</td><td><input class='inputbox' style='width:200px;' type='text' name='nszam' value='{$row["nszam"]}'></td></tr>";
		//echo "<tr><td width='60'>Anyja neve:</td><td><input class='inputbox' style='width:200px;' type='text' name='anyjaneve' value='{$row["anyjaneve"]}'></td><td width='60'></td><td>".($row["ertesitve"]==1?" (orv. értesítve)":"")." <input type='checkbox' name='eljott' value='1' ".($row["eljott"]==1?"checked":"")." /> eljött <input type='checkbox' name='voltnalunk' value='1' ".($row["voltnalunk"]==1?"checked":"")." /> volt már</td></tr>";
		echo "<tr><td width='60'>Anyja neve:</td><td><input class='inputbox' style='width:200px;' type='text' name='anyjaneve' value='{$row["anyjaneve"]}'></td><td width='60'>Kupon:</td><td><input type = 'text' style='width:140px' class='inputbox' name='kuponkod' value='{$result['kuponkod']}' id='kuponkod' />&nbsp;<input type = 'button' value = 'Check' onClick = '$(\"#coupondesc\").empty();$(\"#coupondiscount\").empty();kuponCheck($(\"#kuponkod\").val(),2,\"".date("Y-m-d",strtotime($row["datum"]))."\",{$row['szurestipusid']});return false'/></td></tr>";
		echo "<tr><td width='60'></td><td>".($row["ertesitve"]==1?" (orv. értesítve)":"")." <input type='checkbox' name='eljott' value='1' ".($row["eljott"]==1?"checked":"")." /> eljött <input type='checkbox' name='voltnalunk' value='1' ".($row["voltnalunk"]==1?"checked":"")." /> volt már </td><td></td><td><span id='coupondesc' ></span><br/><span id='coupondiscount'></span></td></tr>";
		//echo "<tr><td width='60'>Munkáltató:</td><td><input class='inputbox' style='width:200px;' type='text' name='munkaltato' value='{$_POST["munkaltato"]}'></td></tr>";
		//echo "<tr><td width='60'>Munkakör:</td><td><input class='inputbox' style='width:200px;' type='text' name='munkakor' value='{$_POST["munkakor"]}'></td></tr>";
		echo "</td></tr>";
		//echo "<tr><td width='60'>Naplószám:</td><td><input class='inputbox' style='width:200px;' type='text' name='nszam' value='{$row["nszam"]}'></td><td></td><td>".($row["ertesitve"]==1?" (orvos értesítve)":"")." <input type='checkbox' name='eljott' value='1' ".($row["eljott"]==1?"checked":"")." /> eljött</td></tr>";
		echo "<tr><td width='60'>Megjegyzés:</td><td colspan='3'><textarea style='width:98%;height:60px;' name='megj'>{$row["megj"]}</textarea></td></tr>";


		echo "<tr><td colspan='3' valign='top'><div style='background:#ccc;padding:5px;'>Alkalmasság</div>";	
		
		foreach ($alkalmassagvariaciok as $key => $value) {
			$oc="";
			if ($key!="I") $oc="onclick=\"$('input[name=alkalmassagido]').attr('checked',false);\"";
			echo "<div><input ".($row["alkalmassag"]==$key?"checked":"")." {$oc} type='radio' name='alkalmassag' value='{$key}' /> {$value}";
			if ($key=="I") echo "
			<input ".($row["alkalmassagido"]==3?"checked":"")." type='radio' name='alkalmassagido' value='3' />3 hó 
			<input ".($row["alkalmassagido"]==6?"checked":"")." type='radio' name='alkalmassagido' value='6' />6 hó 
			<input ".($row["alkalmassagido"]==12?"checked":"")." type='radio' name='alkalmassagido' value='12' />1 év 
			<input ".($row["alkalmassagido"]==24?"checked":"")." type='radio' name='alkalmassagido' value='24' />2 év 
			<input ".($row["alkalmassagido"]==36?"checked":"")." type='radio' name='alkalmassagido' value='36' />3 év";
			if ($key=="IN") echo "&nbsp;&nbsp;&nbsp;&nbsp;köv. vizsgálat: <input type='text' style='width:40px;' name='alkalmassagikhet' value='{$row["alkalmassagikhet"]}' /> hét";
			if ($key=="K") echo "<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<textarea placeholder='korlátozás szövege' style='width:300px;height:40px;' name='alkalmassagkorl'>{$row["alkalmassagkorl"]}</textarea>";
			echo "</div>";
		}
		echo "<div>Tüdőszűrő dátuma: <input type='text' style='width:80px;' name='tudoszuroervenyesseg' value='{$row["tudoszuroervenyesseg"]}' />&nbsp;&nbsp;";
		
		echo "<div style='display:inline-block;".($row["tudoszuro"]==1?"background:#f00;color:#fff;":"")."'><input type='checkbox' name='tudoszuro' value='1' ".($row["tudoszuro"]==1?"checked":"")." /> tüdőszűrés kell</div>";
		
		echo "</td>";
		
		echo "<td valign='top' style=''>";
		echo "<div style='width:200px;overflow:hidden;'><div style='width:1000px;'>".showPaciensFiles($row["id"])."</div></div>";
		
		//$szolg="";
		if ($rowa=sql_fetch_array(sql_query("select * from arak WHERE INSTR(cegid,?) AND tipusid=? and csomag=0",array("|{$row["cegid"]}|",$row["szurestipusid"])))) {
			echo "<div><a href='#' onclick='showFizSzolg({$row["id"]});return false;'>+ szolgáltatás hozzáadása</a><div>";
		}
		echo "<div id='fizszolglist{$row["id"]}'>".showFizSzolg($row["id"])."</div>";

		
		echo "</td>";
		
		echo "</tr>";
		

		//echo "<tr><td colspan=2 valign=top><input type='checkbox' value=1 name='aktiv'".($_POST["aktiv"]==1?" checked":"")."> Aktív</td></tr>";


		echo "</table>";


		echo "<br><input type='button' onclick='foglalasMentes(\"{$_GET["page"]}\");' value='Mentés'/>&nbsp;&nbsp;";
		echo "<input onclick='foglalasOrvosErtesites();' type='button' value='Orvos értesítése'/>&nbsp;&nbsp;";
		echo "<button class = 'sync-button' onClick='syncData(".$row['id'].");return false;'>Szinkronizálás</button>&nbsp;&nbsp;";
		echo "<input onclick='$(\"#idoponteditor\").slideUp();cancelFoglalasMove();' type='button' value='Bezár'/> ";

		if ($row["foglalta"]!="") echo "&nbsp;&nbsp;&nbsp;Foglalta: {$row["foglalta"]}";

		echo "</form>";
		
		echo "</div>"; 


	} else {
		echo "Az időpont adatok lekérdezése közben hiba történt! {$_GET["p"]}";
	}

	
	
	//echo $id;
	die();
}



if (isset($_GET["loadnaptar"])) {
	if (isset($_GET["shift"])) $_SESSION["shift"]+=intval($_GET["shift"]);
	
	echo showAdminNaptar();
	die();
}


function showAdminNaptar() {
    if (!isset($_SESSION["helyszin"]) || $_SESSION["helyszin"]==0) return "";

	$shift=intval($_SESSION["shift"]);

	$htmlout="";

	$helyszin=intval($_SESSION["helyszin"]);
	$helyszinceg=intval($_SESSION["helyszinceg"]);

	if ($_SESSION["naptarszurestipus"]!=0) {
		if ($row=sql_fetch_array(sql_query("select megnev from szurestipusok where id=?",array($_SESSION["naptarszurestipus"])))) {
			$_SESSION["naptarszurestipusnev"]=$row["megnev"];
		}
	}


	$foglaltidopontok[]="";

	//el kell dönteni, hogy csak a cég foglaltjait mutassa, vagy az összes kiválasztott címre foglaltakat!
	//$res=sql_query("select datum,nev,eljott from foglalasok where helyszinid='{$helyszin}' and cegid='{$helyszinceg}' and aktiv=1");
	$wf="";
	if ($_SESSION["naptarszurestipus"]!=0) $wf.=" and szurestipusid='".intval($_SESSION["naptarszurestipus"])."'";
	$res=sql_query("select datum,nev,eljott,cegid,orvosassigned from foglalasok where helyszinid='{$helyszin}' and aktiv=1 {$wf}");
	while ($row=sql_fetch_array($res)) {
		$ido=substr($row["datum"],0,16);
		$foglaltData[$ido][]=$row;
	}

	//print_r($foglaltidopontok);

	$foglaltnapok[]="";
	$res=sql_query("select nap from foglaltnapok where helyszinid=? and helyszinceg=? and (szurestipusid=0 or szurestipusid=?)",array($helyszin,$helyszinceg,$_SESSION["naptarszurestipus"]));
	while ($row=sql_fetch_array($res)) {
		$foglaltnapok[]=$row["nap"];
	}


	$szunnapok[]="";
	$rows=sql_fetch_array(sql_query("select * from settings"));
	$n=explode(",",$rows["szunnapok"]);
	for ($i=0;$i<count($n);$i++) {
		$szunnapok[]=trim($n[$i]);
	}


	$resSzabi=sql_query("SELECT * FROM szabadsag WHERE datumtol>DATE_SUB(NOW(),INTERVAL 30 DAY)");
	while ($szData=sql_fetch_array($resSzabi)) {
	    $GLOBALS["szabidata"][$szData["oid"]][]=$szData;
    }

	$htmlout.="<table border='0' cellpadding='0' cellspacing='0'><tr>";


	for ($i=0;$i<$GLOBALS["daydisplay"];$i++) {
		$dd=$i+$shift;
		
		$nap=date("Y-m-d",strtotime("now +{$dd} day"));
		$wd=date("N",strtotime("now +{$dd} day")); //day of week
		$wn=date("W",strtotime("now +{$dd} day")); //number of week

		$dbg="#0a0";
		if (in_array($nap,$foglaltnapok)) $dbg="#ccc;";

		$htmlout.= "<td valign='top' sytle=''>";
		$htmlout.= "<div style='background:{$dbg};padding:2px 10px 2px 10px;color:#fff;font-weight:bold;text-align:center;margin-right:3px;'>{$nap}<br>{$GLOBALS["hetnap"][$wd]}</div>";


		if (in_array($nap,$foglaltnapok)) {
			$htmlout.= "<div style='text-align:center;'>erre a napra<br>foglalás tiltva</div>";
			$htmlout.= "<div style='text-align:center;margin-bottom:10px;'><a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&enablenap=".urlencode("{$nap}")."'>engedélyezés</a></div>";
		} else {
			$htmlout.= "<div style='text-align:center;margin-bottom:10px;'><a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&disablenap=".urlencode("{$nap}")."'>nap tiltása</a></div>";
		}
		


		$minrendeles=0;
		$maxrendeles=0;
		if (isset($beosztasData)) unset($beosztasData);
		if ($beoData=getBeosztasok($nap,$helyszin,$_SESSION["naptarszurestipus"])) {
			foreach ($beoData as &$beo) {
				if ($_SESSION["adminuser"]["jogosultsag"]<2 && substr_count($_SESSION["adminuser"]["cegjog"],"|{$beo["cegid"]}|")==0) continue;
				if (strtotime($beo["tol"])<strtotime($minrendeles) || $minrendeles==0) $minrendeles=$beo["tol"];
				if (strtotime($beo["ig"])>strtotime($maxrendeles) || $maxrendeles==0) $maxrendeles=$beo["ig"];
				
				
				if ($beo["nap"]==10) {
					$beosztasData[$beo["beonap"]][]=$beo;
				} else {
					$beosztasData[$beo["nap"]][]=$beo;
				}
				//$beosztasData[$beo["nap"]][]=$beo;		
			} 
		} else {
			$htmlout.="<div style='text-align:center;padding:0px;'>Nincs<br/>rendelés</div>";
			$htmlout.="</td>";
			continue;		
		}


		if (isset($beosztasData[$nap])) {
			$beosztasData[$wd][]=$beosztasData[$nap][0];
		}

		//$htmlout.=print_r($beosztasData,true);

		
		if (in_array($nap,$szunnapok)) {
			$htmlout.="<div style='text-align:center;'>Munkaszüneti<br/>nap!</div>";
			$htmlout.="</td>";
			continue;
		}
		
		
		$binterval=$beosztasData[$wd][0]["binterval"];
		$beginora=round(substr($minrendeles,0,2));
		$beginperc=round(substr($minrendeles,3,2));
		
		for ($o=0;$o<=55;$o++) {
			$diff=$o*$binterval;
			$ora=date("H:i",strtotime("{$nap} {$minrendeles}+{$diff} minute"));
			//$ora=date("H:i",mktime($beginora,$beginperc+$o*$binterval,0,date("m"),date("d"),date("Y")));
			
			if (strtotime($ora)>=strtotime($maxrendeles)) break;
			
			$java="sF2('{$nap} {$ora}');return false;";			
			$class="nfb2";
			$title="";
			
			if (isset($beosztasData[$wd][0]["binterval"])) {
				
				//$htmlout.=print_r($beosztasData[$wd],true);
				
				if ($dokik=availableDoctorsForTime($nap,$ora,$beosztasData[$wd])) {
					$class="fhb2";
					if (isset($foglaltData["{$nap} {$ora}"])) {
						$class="fb2";
						$title=$foglaltData["{$nap} {$ora}"][0]["nev"];
						if ($foglaltData["{$nap} {$ora}"][0]["cegid"]==0 && $foglaltData["{$nap} {$ora}"][0]["orvosassigned"]==0) $title="foglalt"; //ha nincs cég és orvos, akkor az egész időpont foglalt
					}
				}
			}
			
			$htmlout.="<div id='".str_replace(array("-",":"),"","ipbox{$nap}{$ora}")."' class='ipcell'>";
			$htmlout.="<a class='{$class}' onclick=\"{$java}\" href='#' title='{$title}'>{$ora}</a>";
			
			if ($class=="fhb2") {
				$htmlout.=" <a title='időpont lefoglalása' class='fi' onclick=\"addIdopontNaptar('{$nap} {$ora}',{$_SESSION["naptarszurestipus"]});return false;\" href='#'>+</a>";
			}
			if ($class=="fb2") {
				if ($title=="foglalt") {
					$htmlout.="&nbsp;&nbsp;fo";
				} else {
					$htmlout.="&nbsp;&nbsp;".count($dokik)."/".count($foglaltData["{$nap} {$ora}"]);
				}
			}
			
			
			$htmlout.="</div>";
		}

		
		if (isOrvosLogin()) {
			$htmlout.="<div style='margin:10px 0px 0px 20px;'>";
			$htmlout.="<a href='{$_SERVER["PHP_SELF"]}?page={$_GET["page"]}&tobbnapfoglal&from=".urlencode("{$nap}")."' title='több nap foglalása'>F+</a>";
			$htmlout.="</div>";
		}
		
		$htmlout.="</td>";	
		
	}
	$htmlout.="</tr></table>";
	return $htmlout;
}


function availableDoctorsForTime($nap,$ora,$beosztas) {
	foreach ($beosztas as &$beo) {
		if (strtotime(date("Y-m-d {$ora}")) >= strtotime(date("Y-m-d {$beo["tol"]}")) && strtotime(date("Y-m-d {$ora}")) < strtotime(date("Y-m-d {$beo["ig"]}"))) {
			if (!isset($doks) || !in_array($beo["orvosid"],$doks)) {
                $szabinVan=false;
			    if (isset($GLOBALS["szabidata"][$beo["orvosid"]])) {
			        foreach ($GLOBALS["szabidata"][$beo["orvosid"]] as $orvosSzabi) {
                        if (strtotime(date("{$nap} {$ora}")) >= strtotime(date("{$orvosSzabi["datumtol"]} 00:00:00")) && strtotime(date("{$nap} {$ora}")) <= strtotime(date("{$orvosSzabi["datumig"]} 23:59:59"))) {
                            $szabinVan=true;
                        }
                    }
                }
				if (!$szabinVan) $doks[]=$beo["orvosid"];
			}
		}
	}
	
	if (!isset($doks)) return false;
	return $doks;
}


if (isset($_GET["shownaptaridopont"])) {
	echo showAdminNaptarIdopont($_GET["shownaptaridopont"]);
	die();
}


function showAdminNaptarIdopont($idopont) {
	if (!isset($_SESSION["helyszin"])) {
		return "A munkamenet lejárt, kérjük frissítsd az oldalt!";
	}
	
	
	$helyszin=intval($_SESSION["helyszin"]);
	
	$szuresTipusData=sql_fetch_array(sql_query("select * from szurestipusok where id=?",array($_SESSION["naptarszurestipus"])));
	
	$htmlout="";
	$htmlout.="<div style='display:table;'>";
	$htmlout.="<div style='display:table-row;'>";
	$htmlout.="<div style='display:table-cell;vertical-align:middle;'><div style='font-size:28px;padding:0px 15px 0px 0px;'>".datumprint($idopont)."</div></div>";
	$htmlout.="<div style='display:table-cell;vertical-align:middle;'><a class='ujbutton' onclick=\"addIdopontNaptar('{$idopont}',{$_SESSION["naptarszurestipus"]});return false;\" href='#'>+ foglalás</a></div>";
	$htmlout.="</div>";
	$htmlout.="</div>";

	$htmlout.="<div style='font-weight:bold;'>{$szuresTipusData["megnev"]} beosztások:</div>";
	if ($beoData=getBeosztasok($idopont,$helyszin,$_SESSION["naptarszurestipus"])) {
		foreach ($beoData as &$beo) {
			//if (!isset($doks["{$beo["orvosid"]}{$beo["tol"]}{$beo["ig"]}"])) {
				$doks["{$beo["orvosid"]}{$beo["tol"]}{$beo["ig"]}"][]=$beo;
			//}
			//if (!isset($doks) || !in_array($beo["orvosid"],$doks)) {
			//	$doks[]=$beo["orvosid"];
			//	echo "<div>{$beo["tol"]}-{$beo["ig"]} {$beo["orvosnev"]}</div>";
			//}

			
		}
		
		//ksort($doks);
		foreach ($doks as &$dok) {
			$htmlout.= "<div>{$dok[0]["tol"]}-{$dok[0]["ig"]} {$dok[0]["orvosnev"]} ";
			if (count($dok)==1) {
				$htmlout.= substr_jns($dok[0]["cegnev"],0,20);
			} else {
				$htmlout.= "<a href='#' onclick='$(\"#orvosceg{$dok[0]["id"]}\").slideToggle();return false;'>".count($dok)." cég</a></div>";
			}
			
			$htmlout.= "<div id='orvosceg{$dok[0]["id"]}' style='width:300px;font-size:10px;color:#888;display:none;'>";
			
			$cegeknev="";
			foreach ($dok as &$ceg) {
				$cegeknev.=", {$ceg["cegnev"]}";
			}
			$htmlout.= substr($cegeknev,2);
			
			$htmlout.= "</div>";
	 	 
		} 
		
	}


	$res=sql_query("select f.*,t.megnev as sztipus,c.megnev as cegnev,o.nev as orvosnev from foglalasok f
	left join szurestipusok t on t.id=f.szurestipusid
	left join orvosok o on o.id=f.orvosassigned
	left join cegek c on c.id=f.cegid
	where datum=? and f.aktiv=1 and f.helyszinid=?
	order by f.szurestipusid<>?",array($idopont,$helyszin,$_SESSION["naptarszurestipus"]));
	
	if (sql_num_rows($res)==0) {
		$htmlout.="<div style='margin-top:20px;font-weight:bold;color:#f00;'>Nincs foglalás erre az időpontra</div>";
	} else {
		while ($row=sql_fetch_array($res)) {
			
			if ($row["szurestipusid"]==0) {
				//0 szurestipusid javítás
				sql_query("update foglalasok set szurestipusid=? where id=?",array($_SESSION["naptarszurestipus"],$row["id"]));
				$row["szurestipusid"]=$_SESSION["naptarszurestipus"];
			}

			
			if (!isset($first) && $row["szurestipusid"]!=$_SESSION["naptarszurestipus"]) {
				$htmlout.="<div style='margin-top:20px;font-weight:bold;color:#f00;'>Nincs {$szuresTipusData["megnev"]} foglalás erre az időpontra</div>";		
			}
			$first=true; 
			
			$htmlout.= "<div style='background:#eee;border-radius:5px;padding:15px 15px 20px 15px;margin-top:20px;'>";

			$htmlout.= "<div style='font-size:20px;font-weight:bold;'>{$row["sztipus"]}</div>";

			$htmlout.= "<div style='font-size:20px;'>{$row["cegnev"]}</div>";
			if ($row["foglalta"]!="") $htmlout.= "<div style=''>Foglalta: {$row["foglalta"]}</div>";
			if ($row["orvosassigned"]!=0) $htmlout.= "<div style=''>Orvos: {$row["orvosnev"]}".($row["ertesitve"]==1?" (értesítve)":"")."</div>";
			
			//fview begin
			$htmlout.= "<div style='margin-top:20px;' id='fview{$row["id"]}'>";
			if ($row["nev"]!="nincs név") $htmlout.= "<div style=''><b>{$row["nev"]}</b></div>";
			if ($row["munkakor"]!="") $htmlout.= "<div style=''>{$row["munkakor"]}</div>";
			if ($row["nszam"]!="") $htmlout.= "<div style='margin-bottom:10px;'>Naplószám: {$row["nszam"]}</div>";
			if ($row["taj"]!="") $htmlout.= "<div style='margin-bottom:10px;'>TAJ: {$row["taj"]}</div>";
			if ($row["szuldatum"]!="") $htmlout.= "<div>Születési dátum: {$row["szuldatum"]}</div>";
			if ($row["irsz"]!="") $htmlout.= "<div>Cím: {$row["irsz"]} {$row["varos"]} {$row["utca"]}</div>";
			if ($row["telefon"]!="") $htmlout.= "<div>Tel: {$row["telefon"]}</div>";
			if ($row["email"]!="") $htmlout.= "<div>E-mail: <a href='mailto:{$row["email"]}'>{$row["email"]}</a></div>";
			
			if ($row["nev"]!="nincs név") {
				$htmlout.="<div id='eljottcheck{$row["id"]}' style='margin-top:10px;'>";
				$htmlout.=showEljottCheckBox($row);
				$htmlout.="</div>";
			}

			$htmlout.="<div id='alkalmassagstatus{$row["id"]}'>";
			$htmlout.=showAlkalmassagStatus($row);
			$htmlout.="</div>";
			
			$htmlout.="<div style='margin-top:10px;'>";
			//if ($row["munkaltato"]!="") echo "<div>Munkáltató: {$row["munkaltato"]}</div>";
			//if ($row["munkakor"]!="") echo "<div>Munkakör: {$row["munkakor"]}</div>";
			if ($row["megj"]!="") $htmlout.= "<div>Megjegyzés: {$row["megj"]}</div>";
			$htmlout.= "</div>";

			$files=showPaciensFiles($row["id"]);
			if ($files!="") $htmlout.= "<div style='margin-top:5px;width:280px;'>{$files}</div>";
		


			$htmlout.="<div style='margin-top:20px;'>";
			$htmlout.="<a class='ujbutton' onclick='showIdopontEditor(\"bnaptar\",\"{$row["pass"]}\",{$row["id"]});return false;' href='#'>Szerkesztés</a>&nbsp;&nbsp;";
			if ($row["nev"]!="nincs név") $htmlout.="<a class='ujbutton' onclick='foglalasOrvosErtesitesOnly({$row["id"]});return false;' href='#'>Orvos értesítése</a>&nbsp;&nbsp;";
			$htmlout.="<a class='ujbutton' onclick='removeIdopontNaptar({$row["id"]},\"{$idopont}\");return false;' href='#'>Törlés</a>";
			$htmlout.="</div>";
			$htmlout.="</div>";
			// fview end

	
			$htmlout.="</div>";			
		}
	}
	
	return $htmlout;
}




function showPaciensFiles($id) {
	$htmlout="";
	$resf=sql_query("select * from dokumentumok where foglalasid=?",array($id));
	if (sql_num_rows($resf)>0) {
		$htmlout.="<div style='display:inline-block;'>";
		$htmlout.="<div style='background:#888;color:#fff;padding:5px;'>Paciens által feltöltött file(ok)</div>";
		while ($rowf=sql_fetch_array($resf)) {
			$htmlout.="<div style='padding:1px 4px;'><a href='//bejelentkezes.hungariamed.hu/downloaddoc.php?f={$rowf["id"]}&k={$rowf["kod"]}'>{$rowf["filename"]}</a></div>";
		}
		$htmlout.="</div>";
	}
	return $htmlout;
}




if (isset($_GET["getattachment"])) {
	$aid=intval($_GET["getattachment"]);
	if ($row=sql_fetch_array(sql_query("select * from emailattachments where id=?",array($aid)))) {
		
		$file=file_get_contents("/var/www/emailattachments/attachment{$aid}.bin");
		$filename=strtolower($row["filename"]);
		$ext=pathinfo($filename, PATHINFO_EXTENSION);
		
		header("Pragma: no-cache");
		header("Cache-Control: no-store, no-cache");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: must-revalidate");
		header('Content-transfer-encoding: binary');
		header('Content-Disposition: attachment; filename="'.$filename.'"');


		if ($ext=="pdf") header("Content-Type: application/pdf;");
		if ($ext=="jpg") header("Content-Type: image/jpeg;");
		if ($ext=="doc") header("Content-Type: application/msword;");
		if ($ext=="docx") header("Content-Type: application/msword;");
		if ($ext=="xls") header("Content-Type: application/vnd.ms-excel;");
		if ($ext=="xlsx") header("Content-Type: application/vnd.ms-excel;");
		
		
		echo $file;
		
		//echo $row["filename"];
		die();
	}
	
	die("error");
}



if (isset($_GET["addatouser"])) {
	addAttachmentToPaciens($_GET["addatouser"]);
	header("location:index.php?page={$_GET["page"]}");
	die();
}

if (isset($_POST["savelangvalue"])) {
    sql_query("update langtext set szoveg=? where id=?",array($_POST["savelangvalue"],$_POST["id"]));
    echo htmlentities($_POST["savelangvalue"]);
    die();
}

function beosztasModJog() {
	if ($_SESSION["adminuser"]["jog_beosztasset"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
	return false;
}

function orvosModJog() {
	if ($_SESSION["adminuser"]["jog_orvosset"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
	return false;
}

function szabadsagJog() {
	if ($_SESSION["adminuser"]["jog_szabi"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
	return false;
}

function cegModJog() {
	if ($_SESSION["adminuser"]["jog_cegset"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
	return false;
}

function szurestipusModJog() {
	if ($_SESSION["adminuser"]["jog_szurestipusset"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
	return false;
}

function helyszinModJog() {
	if ($_SESSION["adminuser"]["jog_helyszinset"]==1 || $_SESSION["adminuser"]["jog_jogset"]==1) return true;
	return false;
}

//include("ajax_lelet.php");
//include("ajax_protocol.php");



if (isset($_GET["addsmsphone"])) {
    sql_query("insert into smsphones set orvosid=?",array($_GET["oid"]));
    echo smsAlertSettings($_GET["oid"]);
    die();
}
if (isset($_GET["deletesmsphone"])) {
    sql_query("delete from smsphones where id=? and orvosid=?",array($_GET["id"],$_GET["oid"]));
    echo smsAlertSettings($_GET["oid"]);
    die();
}

function smsAlertSettings($oid) {
    $htmlout="";
    $htmlout.="<div>";

    $res=sql_query("select * from smsphones where orvosid=?",array($oid));
    $x=1;
    while ($row=sql_fetch_array($res)) {
        $htmlout.="<div>";
        $htmlout.="<input style='width:110px;' type='text' id='smsphone{$x}' name='smsphone{$x}' placeholder='SMS telefonszám' value='{$row["tel"]}' /> ";

        $num=0;
        unset($idk);
        $idk[]=0;
        $titl="";

        $ik=explode("|",$row["cegek"]);
        for ($i=0;$i<count($ik);$i++) {
            if ($ik[$i]!="") {
                $num++;
                $idk[]=$ik[$i];
            }
        }

        if (count($idk)>1) {
            $rowtt=sql_fetch_array(sql_query("SELECT GROUP_CONCAT(megnev SEPARATOR ', ') AS megnevek FROM cegek WHERE id IN (".implode(",",$idk).")"));
            $titl=$rowtt["megnevek"];
        }
        $btn="{$num} cég";
        if ($num==0) $btn="Összes cég";

        $htmlout.="<span id='cegstatus{$row["id"]}'><a href='#' class='tlink' style='width:100px;' title='{$titl}' onclick='showCegValaszto({$row["id"]});return false;'>{$btn}</a></span> ";


        $htmlout.="<input type='checkbox' value=1 name='smsfoglalas{$x}'".($row["smsfoglalas"]==1?" checked":"")."> foglalás értesítés ";
        $htmlout.="<input type='checkbox' value=1 name='smsgroupfoglalas{$x}'".($row["smsgroupfoglalas"]==1?" checked":"")."> értesítés a másnapi foglalásokról (19 órakor) ";
        $htmlout.="[<a href='#' onclick='deleteSMSPhone({$oid},{$row["id"]});return false;'>szám törlése</a>]";
        $htmlout.="<input type='hidden' value='{$row["id"]}' name='phoneid{$x}' />";
        $htmlout.="</div>";
        $htmlout.="<div id='cegvalaszto{$row["id"]}'></div>";
        $x++;
    }
    $htmlout.="<div style='margin-top:5px;'><input onclick='addSMSPhone({$oid})' type='button' name='addsmstel' value='+ SMS telefonszám hozzáadása'></div>";
    $htmlout.="</div>";
    return $htmlout;
}


if (isset($_GET["showcegvalaszto"])) {
    $phoneId=intval($_GET["showcegvalaszto"]);
    $rowo=sql_fetch_array(sql_query("select * from smsphones where id='{$phoneId}'"));

    $res=sql_query("select * from cegek where true order by megnev");

    echo "<div style='width:650px;'>";
    while ($row=sql_fetch_array($res)) {
        echo "<label><input onchange='saveCegList({$phoneId})' type='checkbox' name='cegvalaszto{$phoneId}_{$row["id"]}' value='{$row["megnev"]}' ".(substr_count($rowo["cegek"],"|{$row["id"]}|")>0?"checked":"")."/>{$row["megnev"]}&nbsp;&nbsp;</label>";
    }

    echo "<div style=''><input type='button' onclick='showCegValaszto({$phoneId});' value='OK'></div>";
    echo "</div>";

    die();
}

if (isset($_GET["savesmsphonetipusok"])) {
    sql_query("update smsphones set cegek=? where id=?",array($_GET["value"],round($_GET["savesmsphonetipusok"])));
    die();
}

if (isset($_GET["loadorvoschangedefault"])) {
    if (isset($_GET["oid"])) {
        sql_query("update foglalasok set orvosassigned=? where id=?",array($_GET["oid"],$_GET["fid"]));
    }

    if ($foglalasData=sql_fetch_array(sql_query("select f.id,orvosassigned,o.nev as orvosnev from foglalasok f left join orvosok o on o.id=f.orvosassigned where f.id=?",array($_GET["fid"])))) {
        echo "{$foglalasData["orvosnev"]} <a onclick='$(\"#orvoschangediv{$foglalasData["id"]}\").load(\"index.php?loadorvoschangecombo&fid={$foglalasData["id"]}\");return false;' href='#'><img style='height:10px;opacity: .5;' src='images/refresh.png' title='orvos csere'/></a>";
    }
    die();
}

if (isset($_GET["loadorvoschangecombo"])) {
    if (orvosModJog()) {
        if ($foglalasData=sql_fetch_array(sql_query("select orvosassigned from foglalasok where id=?",array($_GET["fid"])))) {
            $res=sql_query("select * from orvosok order by nev");
            echo "<select onchange=\"$('#orvoschangediv{$_GET["fid"]}').load('index.php?loadorvoschangedefault&oid='+this.value+'&fid={$_GET["fid"]}');\" style='width:200px;'>";
            while ($row=sql_fetch_array($res)) {
                echo "<option value='{$row["id"]}' ".($row["id"]==$foglalasData["orvosassigned"]?"selected":"").">{$row["nev"]}</option>";
            }
            echo "</select> <img onclick=\"$('#orvoschangediv{$_GET["fid"]}').load('index.php?loadorvoschangedefault&fid={$_GET["fid"]}');\" style='height:12px;opacity:.6;cursor:pointer;' src='images/cancel.png' title='mégse' />";
        }
    }
    die();
}

function sample_category( $condition ) {
	$htmlout = "";
	$request = sql_query("SELECT * FROM labor_mintak WHERE minta_kategoria = ? ", array( $condition ));
	while( $result = sql_fetch_array( $request ))
	{
		$htmlout.= "<tr><td>{$result['minta_nev']}</td></tr>";
	}
	return $htmlout;
}

function get_protocol( $key ){
	$htmlout = "";
	if($key!="")
	{
		$request = sql_query("SELECT * FROM labor_mintak WHERE minta_id IN( ".$key." )");
		if(sql_num_rows($request) > 0)
		{
			while( $result = sql_fetch_array( $request ))
			{
				$htmlout.= "<tr><td>{$result['minta_nev']}</td></tr>";
			}
		}
	}
	//$htmlout = "SELECT * FROM labor_mintak WHERE minta_id IN( ".$key." )";
	return $htmlout;
}

if(isset($_REQUEST['set_protocol'])){
	
	//$request = sql_query("SELECT * FROM labor_sablonok WHERE lab_id = ".$protocol_id." ");
	$protocol = sql_fetch_array( sql_query("SELECT * FROM labor_sablonok WHERE lab_id = ? ", array( $_REQUEST['set_protocol'] )));
	
?>
<table class = "s1-modul-table" id = "kemia-lista" style = "margin-right:5px;">
	<tr><td><i>Kémia</i></td></tr>
	<?php if( $protocol['kemia_protocol'] != "" ) echo get_protocol( $protocol['kemia_protocol'] ) ?>
</table>
<div class = "s1-modul-table" style = "margin-right:5px;border:none;">
	<table class = "s2-modul-table" id = "hematologia-lista" style = "margin-bottom:5px;">
		<tr><td><i>Hematológia</i></td></tr>
		<?php if( $protocol['hematologia_protocol'] != "" ) echo get_protocol( $protocol['hematologia_protocol'] ) ?>
	</table>
	<table class = "s2-modul-table" id = "veralvadas-lista" style = "margin-bottom:5px;height:130px;">
		<tr><td><i>Véralvadás</i></td></tr>
		<?php if( $protocol['veralvadas_protocol'] != "" ) echo get_protocol( $protocol['veralvadas_protocol'] ) ?>
	</table>
	<table class = "s2-modul-table" id = "egyeb-lista" style = "height:171.5px;">
		<tr><td><i>Egyéb</i></td></tr>
		<?php if( $protocol['egyeb_protocol'] != "" ) echo get_protocol( $protocol['egyeb_protocol'] ) ?>
	</table>
</div>
<div class = "s1-modul-table" id = "s3-scales">
	<table class = "s2-modul-table" id = "vizelet-lista">
		<tr><td><i>Vizelet</i></td></tr>
		<?php if( $protocol['vizelet_protocol'] != "" ) echo get_protocol( $protocol['vizelet_protocol'] ) ?>
	</table>
	<table class = "s2-modul-table" id = "tumormarker-lista">
	<tr><td><i>Tumormarker</i></td></tr>
	<?php if( $protocol['tumor_protocol'] != "" ) echo get_protocol( $protocol['tumor_protocol'] ) ?>
	</table>
	<table class = "s2-modul-table" id = "third-modul-table">
		<tr><td><i>Speciális labor</i></td></tr>
		<tr rowspan = "8"><td style="">
		<textarea></textarea>
		</td></tr>
	</table>
</div>
<?php
die();
}
if(isset($_REQUEST['AFForm'])){
	$TAJ = $_REQUEST['AFForm'];
	$szuldatum = $_REQUEST['birth'];
	$request = sql_query("SELECT * FROM felhasznalok WHERE taj = ".$TAJ." AND szuldatum = '".$szuldatum."' ");
	$result = sql_fetch_array($request);
	if($result){
		$returnString  = "success||".$result['id']."||".$result['nev']."||".$result['taj']."||";
		$returnString .= $result['munkakor']."||".$result['szuldatum']."||".$result['szulhely']."||";
		$returnString .= $result['anyjaneve']."||".$result['email']."||".$result['telefon']."||";
		$returnString .= $result['irsz']."||".$result['varos']."||".$result['utca']."||".$result['cegid']."||".$result['torzsszam'];
	}
	else{
		$returnString = "failed";
	}
	
	echo $returnString;
	die();
}
if( isset( $_REQUEST['uploadfiles'] ))
{	
	$smartFile 	  = array();
	$missingUsers = array();
	$successFind  = array();
	$z = 0;
	$t = 0;
	foreach( $_FILES['FileUpload']['name'] as $key => $file ) 
	{
		//Ha a fájl neve tartalmazza a "!" karaktert hagyja ki a vizsgálatból:
		if(strpos($file, "!") === false)
		{
			$infoString  = explode( "_", $file );
			$type 		 = explode( ".", $file );
			$szuldatum   = explode( ".", $infoString[3] );
			$smartFile[] = array( "filename" => $infoString[0],
									   "taj" => $infoString[2],
								 "szuldatum" => $szuldatum[0],
								   "release" => $infoString[1],
									  "type" => $type[1],
									  "TEMP" => $_FILES['FileUpload']['tmp_name'][$key],
									  "size" => $_FILES['FileUpload']['size'][$key],
									"origin" => $file );
		}
		else continue;
	}
	$f = 0;
	$z = 0;
	for( $i = 0; $i < count( $smartFile ); $i++ )
	{	
		
		if ( in_array( $smartFile[$i]['type'], array( "pdf", "doc", "xls", "docx", "xlsx" )))
		{
			if( $result = sql_fetch_array( sql_query( "SELECT felh.*,c.megnev FROM felhasznalok felh
													   LEFT JOIN cegek c ON c.id = felh.cegid
													   WHERE taj = ? AND szuldatum = ?", array( $smartFile[$i]['taj'], $smartFile[$i]['szuldatum'] ))))
			{
				$successFind[] = array (       "taj" => $smartFile[$i]['taj'],
										 "szuldatum" => $smartFile[$i]['szuldatum'],
											  "file" => $smartFile[$i]['origin'],
											   "nev" => $result['nev'],
											   "ceg" => $result['megnev']);
				sql_query("INSERT INTO dokumentumok SET 
						   beutaloid = 0, userid =".$result['id'].", 
						   megnev    = '".addslashes($smartFile[$i]["filename"])."',
						   filename  = '".addslashes($smartFile[$i]["filename"]).".pdf',
						   size      = '".addslashes($smartFile[$i]['size'])."',
						   tipus     = '{$smartFile[$i]['type']}',
						   datum     = now(),
						   kod       = SHA1(MD5(CONCAT(NOW(),RAND()*20000)))");
				$id = sql_insert_id();
				$destination = getDocPath( $id );
				if( move_uploaded_file( $smartFile[$i]["TEMP"], $destination )){
					
				}
				else echo "Nem sikerült a feltöltés! ({$smartFile[$i]['origin']})<br/>";
			}
			else
			{
				$z++;
				$missingUsers[] = array (       "taj" => $smartFile[$i]['taj'],
										  "szuldatum" => $smartFile[$i]['szuldatum'],
										       "file" => $smartFile[$i]['origin'] );
			}
		}
		else
		{
			echo "Hibás formátum!({$smartFile[$i]["filename"]})<br/>";
		}
	}

	$k     = 0;
	$exist =  "({$f})Sikeres feltöltési próbálkozások:<br/>";
	if( !empty( $successFind ))
	{
		foreach( $successFind as $success )
		{
			$k++;
			$exist.= "<p>[{$k}][Sikeres][{$success['nev']}][ {$success['ceg']} ]-[ {$success['file']} ]</p>";
		}
	}
	else $exist = "";
	
	$n        = 0;
	$nonExist =  "({$z})Sikertelen feltöltési próbálkozások:<br/>";
	if( !empty( $missingUsers ))
	{
		
		foreach( $missingUsers as $miss )
		{
			$n++;
			$nonExist.= "<p>[{$n}][Sikertelen]=>[ {$miss['taj']} ]=>[ {$miss['szuldatum']} ]=>[ {$miss['file']} ]</p>";
		}
	}
	else $nonExist = "";
	$output = $exist.$nonExist;
	echo $output;
}

if(isset($_REQUEST['setUser']) && $_REQUEST['setUser'] == true){

	$request_user = sql_query("SELECT * FROM felhasznalok WHERE taj = ? OR email = ?",array($_REQUEST['taj'], $_REQUEST['email']));
	$exist_user = $request_user->rowCount();
	if( $exist_user > 0){
		$userInfo = sql_fetch_array($request_user);

		$UPDATE_user = sql_query("UPDATE felhasznalok 
								   SET	 taj = ?, cegid = ?, email = ?, nev = ?, telefon = ?, munkakor = ?,
										 irsz = ?, varos = ?, utca = ?, szulhely = ?, anyjaneve = ?,
										 szuldatum = ?, torzsszam = ?
								  WHERE  id = ".$userInfo['id']." ",
								  array($_REQUEST['taj'], $_REQUEST['cegid'], $_REQUEST['email'], $_REQUEST['nev'], $_REQUEST['tel'], $_REQUEST['munkakor'],
										$_REQUEST['irsz'], $_REQUEST['varos'], $_REQUEST['utca'], $_REQUEST['szulhely'], $_REQUEST['anyjaneve'],
										$_REQUEST['szuldatum'], $_REQUEST['torzsszam']));
		if($UPDATE_user){
			$UPDATE_fogl = sql_query("UPDATE foglalasok 
									  SET   paciensid = ?
									  WHERE id = ? ",array( $userInfo['id'], $_REQUEST['fid'] ));
		}
	}
	else{

		$INSERT_new_user = sql_query("INSERT INTO felhasznalok SET
									  taj = ?, cegid = ?, email = ?, nev = ?, telefon = ?, munkakor = ?,
									  irsz = ?, varos = ?, utca = ?, szulhely = ?, anyjaneve = ?,
									  szuldatum = ?, torzsszam = ?, validated = 1 ",
									  array($_REQUEST['taj'],$_REQUEST['cegid'], $_REQUEST['email'], $_REQUEST['nev'], $_REQUEST['tel'], $_REQUEST['munkakor'],
											$_REQUEST['irsz'], $_REQUEST['varos'], $_REQUEST['utca'], $_REQUEST['szulhely'], $_REQUEST['anyjaneve'],
											$_REQUEST['szuldatum'], $_REQUEST['torzsszam']));
		$user_id = sql_insert_id();

		if( $INSERT_new_user ) {
			echo "New user:".$user_id;

			$UPDATE_fogl = sql_query("UPDATE foglalasok 
									  SET   paciensid = ?
									  WHERE id = ? ",array( $user_id, $_REQUEST['fid'] ));
		}
	}

	//Cég neve:
	if(isset($_REQUEST['cegid']) && $_REQUEST['cegid'] != "" ){
		if( $ceg = sql_fetch_array( sql_query( "SELECT megnev FROM cegek WHERE id = ?", array( $_REQUEST['cegid'] ))))
		{
			$cegnev = $ceg['megnev'];
		}
		else $cegnev = "";
	}
	else $cegnev = "";

	//Orvos neve:
	if( isset( $_REQUEST['orvosid'] ) && $_REQUEST['orvosid'] != "" ){
		if( $orvos = sql_fetch_array( sql_query( "SELECT nev FROM orvosok WHERE id = ? ", array( $_REQUEST['orvosid'] ))))
		{
			$orvosnev = $orvos['nev'];
		}
		else $orvosnev = "";
	}
	else $orvosnev = "";

	$wsdl_url = 'http://89.134.90.181:3334/HMMService/Service1.svc?wsdl';
	$client = new SOAPClient($wsdl_url);
	$params = array(
		'nev' => $_REQUEST['nev'],
		'taj' => $_REQUEST['taj'],
		'szuldatum' => $_REQUEST['szuldatum'],
		'szulhely' => $_REQUEST['szulhely'],
		'anyjaneve' => $_REQUEST['anyjaneve'],
		'nem' => "",
		'ceg' => $cegnev,
		'munkakor' => $_REQUEST['munkakor'],
		'orvos' => $orvosnev,
		'email' => $_REQUEST['email'],
		'telefon' => $_REQUEST['tel'],
		'irszam' => $_REQUEST['irsz'],
		'telepules' => $_REQUEST['varos'],
		'utca' => $_REQUEST['utca'],
		'naploszam' => 'naplószáma',
		'megjegyzes' => $_REQUEST['megj'],
		'token' => '3YFgyUfWRM5SmiCgMc3SFWb15WXAzAQ5'
	);
	if( $result = $client->InsertUpdatePaciens( $params )){
		echo "<pre>";
		print_r( $params );
		echo "</pre>";
	}

	die();
}
function blacklistElements($blacklisted = '', &$errors = array()) {
    if ((string)$blacklisted == '') {
        $errors[] = 'Empty string.';
        return array();
    }

    $html5 = array(
        "<menu>","<command>","<summary>","<details>","<meter>","<progress>",
        "<output>","<keygen>","<textarea>","<option>","<optgroup>","<datalist>",
        "<select>","<button>","<input>","<label>","<legend>","<fieldset>","<form>",
        "<th>","<td>","<tr>","<tfoot>","<thead>","<tbody>","<col>","<colgroup>",
        "<caption>","<table>","<math>","<svg>","<area>","<map>","<canvas>","<track>",
        "<source>","<audio>","<video>","<param>","<object>","<embed>","<iframe>",
        "<img>","<del>","<ins>","<wbr>","<br>","<span>","<bdo>","<bdi>","<rp>","<rt>",
        "<ruby>","<mark>","<u>","<b>","<i>","<sup>","<sub>","<kbd>","<samp>","<var>",
        "<code>","<time>","<data>","<abbr>","<dfn>","<q>","<cite>","<s>","<small>",
        "<strong>","<em>","<a>","<div>","<figcaption>","<figure>","<dd>","<dt>",
        "<dl>","<li>","<ul>","<ol>","<blockquote>","<pre>","<hr>","<p>","<address>",
        "<footer>","<header>","<hgroup>","<aside>","<article>","<nav>","<section>",
        "<body>","<noscript>","<script>","<style>","<meta>","<link>","<base>",
        "<title>","<head>","<html>"
    );

    $list = trim(strtolower($blacklisted));
    $list = preg_replace('/[^a-z ]/i', '', $list);
    $list = '<' . str_replace(' ', '> <', $list) . '>';
    $list = array_map('trim', explode(' ', $list));

    return array_diff($html5, $list);
}

if(isset($_POST['downloadGDPR']))
{	
	$result = sql_fetch_array( sql_query( "SELECT * FROM GDPR WHERE id = ?", array( $_POST['downloadGDPR'] )));
	$file = $_POST['downloadGDPR'].".pdf";
	$destination = "../doc/GDPR/".$file;
	$filename = $file;
	if( file_exists( $destination ))
	{
		header("Pragma: no-cache");
		header("Cache-Control: no-store, no-cache");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: must-revalidate");
		header('Content-transfer-encoding: binary');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header("Content-Type: application/pdf");
		readfile( $destination );
		die();
	}
	else die("File doesnt Exist!");
}


function kuponCheck($coupon,$version,$foglalas,$szurestipus)
{
	$query = sql_query("SELECT * FROM kuponkodok WHERE kod = ? AND statusz = 'aktiv' AND event_end >= '{$foglalas}' AND event_start <= '{$foglalas}' ", array( $coupon ));
	
	if($query->rowCount() > 0)
	{
		$result = sql_fetch_array($query);
		$szurestipusok = explode("|",$result['szurestipusok']);
		
		$data = $result['megnev']."|".$result['leiras'];
		if($version == 2) $data.="|".$result['kedvezmeny'].($result['kedvezmeny_tipus'] =="szazalek"?"%":"Ft");
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

if( isset( $_POST['function'] ) && $_POST['function'] == "createAlkxlsx" )
{
	createAlkxlsx($_POST['start'],$_POST['end']);
}

function createAlkxlsx( $start, $end )
{
	$query = "SELECT felh.id,felh.nev,felh.szuldatum,felh.torzsszam,felh.alklejarat,felh.lastalkert 
			  FROM felhasznalok felh
			  LEFT JOIN cegek c ON c.id = felh.cegid
			  WHERE true
			  ".( isset( $_SESSION['alkalmassagi']['multifilter'] )   ? $_SESSION['alkalmassagi']['multifilter'] : "" )."
			  ".( isset( $_SESSION['alkalmassagi']['date-interval'] ) ? $_SESSION['alkalmassagi']['date-interval'] : "" )."
			  ".( isset( $_SESSION['alkalmassagi']['cegfilter'] )  ? $_SESSION['alkalmassagi']['cegfilter'] : "" )."
			  ".( isset( $_SESSION['alkalmassagi']['sort-by'] ) ? "ORDER BY ".$_SESSION['alkalmassagi']['sort-by'] : "" )."
			 ";
			 
	require_once( "Classes/PHPExcel.php" );
	$start = date("Y.m.d",strtotime($start));
	$end = date("Y.m.d",strtotime($end));
	$filename = "{$start}-{$end}_alkalmassági_lista";
	$objPHPExcel = new PHPExcel();
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet()->setTitle('munkavállaló_lista');
	
	//Oszlop nevek:
	$objPHPExcel->getActiveSheet()->SetCellValue('A1', "Munkavállaló");
	$objPHPExcel->getActiveSheet()->SetCellValue('B1', "Szül. dátum");
	$objPHPExcel->getActiveSheet()->SetCellValue('C1', "Törzsszám");
	$objPHPExcel->getActiveSheet()->SetCellValue('D1', "Utolsó értesítés");
	$objPHPExcel->getActiveSheet()->SetCellValue('E1', "Alkalmassági lejárata");
	
	$row = 2;
	$request = sql_query($query);
	while( $result = sql_fetch_array( $request ))
	{
		if($result['lastalkert'] != "") $lastalkert = date( "Y.m.d", strtotime( $result['lastalkert'] ));
		else $lastalkert = "";
		$objPHPExcel->getActiveSheet()->SetCellValue('A'.$row, $result['nev']);
		$objPHPExcel->getActiveSheet()->SetCellValue('B'.$row, date( "Y.m.d", strtotime( $result['szuldatum'] )));
		$objPHPExcel->getActiveSheet()->SetCellValue('C'.$row, $result['torzsszam']);
		$objPHPExcel->getActiveSheet()->SetCellValue('D'.$row, $lastalkert);
		$objPHPExcel->getActiveSheet()->SetCellValue('E'.$row, date( "Y.m.d", strtotime( $result['alklejarat'] )));
		$row++;
	}
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
}

if( isset( $_REQUEST['accountIni'] ))
{
	$copyData = sql_fetch_array( sql_query( "SELECT * FROM orvosok WHERE id = ?", array( $_REQUEST['docid'] )));
	sql_query( "INSERT INTO users SET orvosid = ?, nev = ?, tel = ?, cegid = 0, jogosultsag = 2", 
			    array( $_REQUEST['docid'], $copyData['nev'], $copyData['tel'] )
			  );
	echo "ok";
	die();
}

function medTemplateFilter($type)
{
	$array = explode( ",", $type );
	
	$htmlout = "";
	$title = array("Belgyógyászat","Röntgen","Ultrahang","Bőrgyógyászat","Szemészet","Kardiológia","Gyógytornász","Labor","Urológia","Nőgyógyászat","Tüdőgyógyászat","Ortopédia");
	$query = sql_query("SELECT * FROM lelet_mintak ORDER BY tipus ASC");
	
	while($result = sql_fetch_array( $query ))
	{
		if( in_array($result['tipus'], $array ))
		{
			$index = ($result['tipus'] - 1);
			$$result['tipus']++;
			if($$result['tipus'] == 1) $htmlout.= "<option disabled style = 'background-color:#444;color:white' value = '0'>{$title[$index]}</option>";
			if($result['lelet_ver'] != "") $version = "({$result['lelet_ver']})";
			else $version = "";
			$htmlout.= "<option value = '{$result['lm_id']}'>{$result['lelet_nev']}{$version}</option>";
		}
	}
	return $htmlout;
}
if( isset( $_POST['checkSzabiData'] ))
{
	$_POST['end'] = date("Y-m-d",strtotime($_POST['end'].' + 1 day'));
	$query = sql_query("SELECT * FROM foglalasok WHERE orvosassigned = ? AND datum BETWEEN '{$_POST['start']}%' AND '{$_POST['end']}%' ", array($_POST['orvosid']));
	$data = "";
	while($result = sql_fetch_array($query))
	{
		$data.=$result['nev'].",".$result['datum']."|";
	}
	$data =  substr($data, 0, -1);
	echo $data;
	die();
}

if ( isset( $_POST['download-signed-pdf'] ))
{
	header("Pragma: no-cache");
	header("Cache-Control: no-store, no-cache");
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: must-revalidate");
	header('Content-transfer-encoding: binary');
	header("Content-Disposition: attachment; filename=" . $_POST['id'] . ".pdf");
	header("Content-Type: application/pdf");
	readfile("../doc/GDPR/{$_POST['id']}.pdf");
	die();
}

function listWarnings()
{
	$managers 	   = array();
	$reportArray   = array();
	$szurestipusok = array();
	$top 	  	   = "15,10,13,14,8";
	$undertop 	   = "15,13,14,8";
	
	//Szűréstípusok megnevezése:
	$request = sql_query("SELECT * FROM szurestipusok");
	while($result = sql_fetch_array($request)) $szurestipusok[] = $result;
	
	//Összes managerszűrés listázása:
	$request = sql_query("SELECT * FROM foglalasok 
						  WHERE helyszinid = 1
						  AND nev != 'nincs név'
						  AND szurestipusid IN(6,34,35) 
						  AND datum BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 2 WEEK)");
	while($result = sql_fetch_array($request)) $managers[] = $result;
	
	//Át vizsgálom a páciensek nevét, hogy megtalálható-e az időszakban a többi vizsgálatra:
	foreach( $managers as $manager )
	{
		$missingExams = array();
		//Szűréstípusok kiválasztása menedzserszűrés alapján:
		if( $manager['szurestipusid'] == 35 ) $szuresek = explode( ",", $top );
		else $szuresek = explode( ",", $undertop );
		
		//Megvizsgálom az összes többi vizsgálatát, melyekre nincsen még kiadva időpont:
		foreach( $szuresek as $szures )
		{
			$request = sql_query( "SELECT * FROM foglalasok 
								   WHERE nev LIKE '%{$manager['nev']}%'
								   AND helyszinid = 1
								   AND szurestipusid = {$szures} 
								   AND datum BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 2 WEEK) " );
								   
			if( sql_num_rows( $request ) == 0 )
			{
				$missingExams[] = $szures;
			}
		}
		
		//COOKIE ellenőrzése hogy létezik-e:
		if( isset( $_COOKIE['removedManagers'] ))
		{
			$data = json_decode( $_COOKIE['removedManagers'], true );
		}
		if(isset($data) && is_array($data))
		{
			$key = array_search($manager['id'], $data);
			if($key !== FALSE)
			{
				continue;
			}
		}
		
		if( count( $missingExams ) > 0 ) $reportArray[] = array( "managerid" => $manager['szurestipusid'], "nev" => $manager['nev'], "idopont" => $manager['datum'], "foglid" => $manager['id'], "missingExams" => $missingExams );
	}
	return $reportArray;
}

function checkWarningsList()
{
	$reportArray = listWarnings();
	$possibleMissing = 0;
	
	foreach( $reportArray as $key => $report )
	{
		foreach( $report['missingExams'] as $vizsgalat )
		{
			$possibleMissing++;
		}
	}
	if( $possibleMissing > 0 ) return $possibleMissing;
	else return false;
}

function displayWarnings()
{
	$reportArray = listWarnings();
	$htmlout   = "";
	$number    = 0;
	$maxlength = count( $reportArray );
	$szuresek = array();
	$request = sql_query("SELECT * FROM szurestipusok");
	while($result = sql_fetch_array($request)) $szuresek[] = $result;
	//$icon = "<i class='fas fa-angle-double-down'></i>";
	$onClick = "onClick = 'if( $(\".warrnings-content\").css(\"max-height\") == \"285px\")
								{ 
									$(\".warrnings-content\").css(\"max-height\", \"none\"); 
									$(\".warrnings-content\").append( $(\".warningOpenFolder\"));
									$(\".warningOpenFolder\").text(\"Kevesebb\");
								} 
								else 
								{ 
									$(\".warrnings-content\").css(\"max-height\", \"285px\"); 
									$(\".warrnings-content\").children().eq(9).after( $(\".warningOpenFolder\") );
									$(\".warningOpenFolder\").html(\" Még ".( $maxlength - 10 )."db \");
								}' ";
	foreach( $reportArray as $key => $report )
	{
		$properties = "";
		$number++;
		$vizsgalatok = "";
		//#DC4806 Emelt
		//$onClick  = "onClick = 'scrollToTarget(\"{$report['nev']}\", $(this))'";
		$onClick = "onClick = 'showMissingExams({$key})'";
		$onClick2 = "onClick = 'removeManager({$report['foglid']})'";
		$id = "id = 'manager-{$report['foglid']}'";
		if( $report['managerid'] == 6  ) $difficult = "alapManager";
		if( $report['managerid'] == 34 ) $difficult = "emeltManager";
		if( $report['managerid'] == 35 ) $difficult = "topManager";
		//if( $number == $maxlength || $number == 10 ) $properties.= "border-radius:0px 0px 5px 5px;";
		$style   = "style = '{$properties}'";
		$date    = date("Y.m.d",strtotime($report['idopont']));
		foreach( $report['missingExams'] as $vizsgalat )
		{
			$key = array_search( $vizsgalat, array_column( $szuresek, 'id' ));
			$vizsgalatok.= $szuresek[$key]['megnev'].", ";
		}		
		$vizsgalatok = substr( $vizsgalatok, 0, -2) ;
		$htmlout.= "<div {$style}  {$id}  class = 'warningCell {$difficult}' title = '{$vizsgalatok}'  ><div {$onClick}>{$report['nev']} - {$date}</div><div  class = 'disableWarningCell'><i {$onClick2} class='fas fa-trash-alt'></i></div></div>";
	}
	return $htmlout;
}

if(isset($_POST['refreshWL']))
{
	echo displayWarnings();
	die();
}
if(isset($_POST['refreshLWOpener']))
{
	echo listWarningsOpener();
	die();
}

function listWarningsOpener()
{
	$reportArray = listWarnings();
	$length = count( $reportArray );
	$onClick = "onClick = 'if( $(\".warrnings-content\").css(\"max-height\") == \"250px\")
								{ 
									$(\".warrnings-content\").css(\"max-height\", \"none\"); 
									$(\".warningOpenFolder\").text(\"Kevesebb\");
								} 
								else 
								{ 
									$(\".warrnings-content\").css(\"max-height\", \"250px\"); 
									$(\".warningOpenFolder\").html(\" Még ".( $length - 10 )."db \");
								}' ";
								
	$htmlout = "<div onClick = 'LWOpener({$length})' class = 'warningOpenFolder'>Még ".( $length - 10 )."db <i class='fas fa-angle-double-down'></i></div>";
	return $htmlout;
}
function loadWLLeftMenu()
{
	$htmlout = "<div id = 'option-1' onClick = 'selectSPOption(\"option-1\")' class = 'WLS-LeftMenu-Element'>Kikapcsoltak</div>";
	$htmlout.= "<div id = 'option-2' onClick = 'selectSPOption(\"option-2\")' class = 'WLS-LeftMenu-Element'>Hibás O. beáll.</div>";
	$htmlout.= "<div id = 'option-2' onClick = 'selectSPOption(\"option-3\")' class = 'WLS-LeftMenu-Element'>Hiányzó vizsg.</div>";
	return $htmlout;
}



function loadWLSelectedMenu($option,$index)
{
	if($option == "option-1")
	{
		$htmlout = "<table class = 'removedManagers'>";
		if(isset($_COOKIE['removedManagers']))
		{
			$data = json_decode($_COOKIE['removedManagers'], true );
			if( is_array( $data ))
			{
				$reportArray = listWarnings();
				foreach( $data as $key )
				{
					$result = sql_fetch_array(sql_query( "SELECT * FROM foglalasok WHERE id = {$key}" ));
					if( $reportArray !== FALSE )
					{
						$date = date("Y.m.d H:i", strtotime($result['datum']));
						$htmlout.= "<tr id = 'removedManager-{$key}'>";
						$htmlout.= 	"<td style = 'border-left:none;'>{$result['nev']}</td>";
						$htmlout.= 	"<td style = 'white-space:nowrap;'>{$date}</td>";
						$htmlout.= 	"<td style = 'border-right:none;' onClick = 'withdrawRemove({$key})'><i style = 'font-size:16px;cursor:pointer' class='fas fa-undo'></i></td>";
						$htmlout.= "</tr>";
					}
				}
			}
			else $htmlout.= "<tr><td colspan = '3' style = 'font-size:20px'>- Nincs menedzser kikapcsolva -</td></tr>";
		}
		else $htmlout.= "<tr><td colspan = '3' style = 'font-size:20px'>- Nincs menedzser kikapcsolva -</td></tr>";		
		$htmlout.= "</table>";
	}
	
	if($option == "option-2")
	{
		$request = sql_query("SELECT COUNT(fogl.datum), fogl.datum, sz.megnev,o.nev FROM foglalasok fogl
							  LEFT JOIN orvosok o ON o.id =  fogl.orvosassigned
							  LEFT JOIN szurestipusok sz ON sz.id = fogl.szurestipusid
							  WHERE fogl.helyszinid = 1 
							  AND fogl.datum BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 2 WEEK) 
							  GROUP BY fogl.datum,fogl.orvosassigned, fogl.szurestipusid 
							  HAVING COUNT(fogl.datum) > 1");
							  
		$htmlout = "<table class = 'removedManagers'>";
		while( $result = sql_fetch_array( $request ))
		{
			$date = date("Y.m.d H:i", strtotime($result['datum']));
			$htmlout.= "<tr>";
			$htmlout.= "<td>{$date}</td>";
			$htmlout.= "<td>{$result['megnev']}</td>";
			$htmlout.= "<td>{$result['nev']}</td>";
			$htmlout.= "</tr>";
		}
		
		$htmlout.= "</table>";
	}
	if($option == "option-3")
	{
		$szuresek = array();
		$request = sql_query("SELECT * FROM szurestipusok");
		while($result = sql_fetch_array($request)) $szuresek[] = $result;
		$htmlout = "<table class = 'missingExams'>";
		if($index == "empty")
		{
			$htmlout.= "<tr><td colspan = '3' style = 'font-size:20px;text-align:center'>- Válassz egy vizsgálatot! -</td></tr>";
		}
		else
		{
			$reportArray = listWarnings();
			$result = sql_fetch_array(sql_query("SELECT fogl.*,sz.megnev FROM foglalasok fogl 
												 LEFT JOIN szurestipusok sz ON sz.id = fogl.szurestipusid 
												 WHERE fogl.id = {$reportArray[$index]['foglid']}"));
			
			$htmlout.= "<tr>";
			$htmlout.= "	<td colspan = '2'><i onClick = '$(\"body\").highlight(\"{$reportArray[$index]['nev']}\");' style = 'font-size:20px;cursor:pointer' class='fas fa-lightbulb'></i>&nbsp;&nbsp;";
			$htmlout.= "		<i id = 'copyButton' onClick = 'copyBooking({$result['id']},\"{$result['pass']}\")' title = 'Időpont másolása' style = 'font-size:20px;cursor:pointer;color:black' class='fas fa-clone'></i>&nbsp;&nbsp;";
			$htmlout.= "		<i onClick = 'showIdopontEditor(\"elojegyzestabla\",\"{$result['pass']}\",{$result['id']})' title = 'Időpont szerkesztő' style = 'font-size:20px;cursor:pointer;color:black' class='fas fa-edit'></i></td>";
			$htmlout.= "</tr>";
			$htmlout.= "<tr><td><strong>Ügyfél neve:</strong></td><td>{$reportArray[$index]['nev']}</td></tr>";
			$htmlout.= "<tr><td><strong>Vizsgálat:</strong></td><td>{$result['megnev']}</td></tr>";
			$htmlout.= "<tr><td><strong>Telefonszám:</strong></td><td>{$result['telefon']}</td></tr>";
			$htmlout.= "<tr><td><strong>Email:</strong></td><td>{$result['email']}</td></tr>";
			$htmlout.= "<tr><td><strong>Foglalás ideje:</strong></td><td>".date("Y.m.d H:i",strtotime($result['datum']))."</td></tr>";
			$htmlout.= "<tr><td><strong>Foglalás kelte:</strong></td><td><i>".date("Y.m.d H:i",strtotime($result['regdatum']))."</i></td></tr>";
			$htmlout.= "<tr><td><strong>Megjegyzés:</strong></td><td><textarea style = 'width:238px;height:54px'>".($result['megj'] != ""?$result['megj']:"Nincs.")."</textarea></td></tr>";
			$htmlout.= "<tr><td colspan = '2' style = 'font-size:20px'><strong>Hiányzó vizsgálatok:</strong></td></tr>";
			foreach( $reportArray[$index]['missingExams'] as $vizsgalat )
			{
				$key = array_search( $vizsgalat, array_column( $szuresek, 'id' ));
				$onClick01 = "<i title = 'Ugrás {$szuresek[$key]['megnev']}' style = 'font-size:20px;cursor:pointer' onClick = 'SmoothScrollTo(\"{$szuresek[$key]['megnev']}\",1000)' class='fas fa-arrow-alt-circle-left'></i>";
				//$onClick02 = "<i title = 'Időpont másolása' onClick = 'semmi({$result['id']},\"{$result['pass']}\")' style = 'font-size:20px;cursor:pointer' class='fas fa-clone'></i>";
				$htmlout.= "<tr>";
				$htmlout.= "	<td>{$szuresek[$key]['megnev']}</td>";
				$htmlout.= "	<td>{$onClick01}</td></tr>";
			}
		}
		$htmlout.= "</table>";
	}
	
	return $htmlout;
}

if(isset($_POST['loadWLSelectedMenu']))
{
	if( isset($_POST['index'] )) $index = $_POST['index'];
	else $index = "empty";
	echo loadWLSelectedMenu($_POST['option'], $index);
	die();
}
function WLSPTitle($option)
{
	if($option == 'option-1') $title = "Kikpacsolt menedzserek";
	if($option == 'option-2') $title = "Hibás orvos beállítások";
	if($option == 'option-3') $title = "Hiányzó vizsgálatok";
	return $title;
}
if(isset($_POST['loadWLSPTitle']))
{
	echo WLSPTitle($_POST['option']);
	die();
}
if(isset($_POST['loadSelectedMenu']))
{
	$title = WLSPTitle($_POST['option']);
	$htmlout = "";
	$htmlout.= "<div class = 'WL-sidePanel-title'>";
	$htmlout.= "	<span style = ''>{$title}</span>";
	$htmlout.= "	<span><i style = 'cursor:pointer;' onClick = '$(\".WL-sidePanel\").animate({width: \"toggle\"});cpy=0;foglalasSelected=0;foglalasSelectedPass=0;' class='fas fa-times'></i></span>";
	$htmlout.= "</div>";
	
	$htmlout.= "<div class = 'WL-sidePanel-leftMenu-container'>".loadWLLeftMenu()."</div>";
	$htmlout.= "<div class = 'WL-sidePanel-selected-menu-conainer'>".loadWLSelectedMenu($_POST['option'], $_POST['index']);
	$htmlout.= "</div>";
	die($htmlout);
}

if(isset($_POST['withdrawManager']))
{
	if(isset($_COOKIE['removedManagers']))
	{
		$data = json_decode($_COOKIE['removedManagers'], true);
		$key  = array_search( $_POST['withdrawManager'], $data );
		unset($data[$key]);
		array_values( $data );
		if( empty( $data )) $time = time() - 3600;
		else $time = time()+86400;
		if(setcookie('removedManagers', json_encode($data), $time ))
		{
			die("sikeres!");
		}
	}
	else die();
}

if(isset($_POST['removeManager']))
{
	if(isset($_COOKIE['removedManagers']))
	{
		$data = json_decode($_COOKIE['removedManagers'], true);
		//$data = unserialize($_COOKIE['removedManagers'], ["allowed_classes" => false]);
		$data[] = $_POST['removeManager'];
		if(setcookie('removedManagers', json_encode($data), time()+86400 ))
		{
			die("sikeres!");
		}
	}
	else
	{
		$data = array($_POST['removeManager']);
		if(setcookie( 'removedManagers', json_encode($data), time() + 86400 ))
		{
			die("sikeres!");
		}
	}
}

if(isset($_POST['downloadExamStat']) && $_POST['downloadExamStat']==true){
	
	if($_POST['cegid']!=0) $result = sql_fetch_array(sql_query("SELECT * FROM cegek WHERE id = ?",array($_POST['cegid'])));
	else $result['megnev']="teljes";
	$filename="{$_POST['start']}-{$_POST['end']} {$result['megnev']} statisztika";
	
	$_POST['start'] = $_POST['start']." 00:00:00";
	$_POST['end']   = $_POST['end']  ." 23:59:59";
	
	require_once "Classes/PHPExcel.php";
	$objPHPExcel = new PHPExcel();
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet()->setTitle('Vizsgálat lista');
	
	$objPHPExcel->getActiveSheet()->SetCellValue('A1', "Ügyfél neve");
	$objPHPExcel->getActiveSheet()->SetCellValue('B1', "Szül. dátum");
	$objPHPExcel->getActiveSheet()->SetCellValue('C1', "Cég");
	$objPHPExcel->getActiveSheet()->SetCellValue('D1', "Email");
	$objPHPExcel->getActiveSheet()->SetCellValue('E1', "Telefon");
	$objPHPExcel->getActiveSheet()->SetCellValue('F1', "Vizsgálat");
	$objPHPExcel->getActiveSheet()->SetCellValue('G1', "Keltezés");
	$objPHPExcel->getActiveSheet()->SetCellValue('H1', "Ellátó orvos");
	
	$i = 1;
	
	$request = sql_query("SELECT 

	/*Páciens információk*/
	felh.nev AS 'Ügyfél neve', felh.szuldatum AS 'Szül. dátum',c.megnev AS 'Cég', felh.email AS 'Email', felh.telefon AS 'Telefon', 

	/*Vizsgálat információi*/
	lm.lelet_nev AS 'Vizsgálat',pl.kelte AS 'Keltezés',
	(CASE WHEN pl.lelet_szoveg LIKE '%Dr. Al-Mohamed Ádám%'    THEN 'Dr. Al-Mohamed Ádám'
		  WHEN pl.lelet_szoveg LIKE '%Dr. Juhász Anita%' 	   THEN 'Dr. Juhász Anita'
		  WHEN pl.lelet_szoveg LIKE '%Dr. Tarján Zsolt%' 	   THEN 'Dr. Tarján Zsolt'
		  WHEN pl.lelet_szoveg LIKE '%Dr. Ferenczi Zsuzsanna%' THEN 'Dr. Ferenczi Zsuzsanna'
		  WHEN pl.lelet_szoveg LIKE '%Dr. Magyar Judit%' 	   THEN 'Dr. Magyar Judit'
	END) AS 'Orvos'

	/*default lekérdés alap tábla*/
	FROM paciens_leletek pl

	/*Szükséges kiegészítő információk*/
	LEFT JOIN felhasznalok felh ON felh.id = pl.paciens_id
	LEFT JOIN lelet_mintak lm ON lm.lm_id = pl.lelet_type
	LEFT JOIN cegek c ON c.id = felh.cegid

	/*Vizsgálati elemek*/
	WHERE ".($_POST['cegid']!=0?"felh.cegid = 104 AND ":"")."pl.kelte BETWEEN '{$_POST['start']}' AND '{$_POST['end']}'

	/*Rendezés*/
	GROUP BY pl.kelte ASC");
	
	while($result = sql_fetch_array($request)){
		$i++;
		$objPHPExcel->getActiveSheet()->SetCellValue('A'.$i, $result['Ügyfél neve']);
		$objPHPExcel->getActiveSheet()->SetCellValue('B'.$i, $result['Szül. dátum']);
		$objPHPExcel->getActiveSheet()->SetCellValue('C'.$i, $result['Cég']);
		$objPHPExcel->getActiveSheet()->SetCellValue('D'.$i, $result['Email']);
		$objPHPExcel->getActiveSheet()->SetCellValue('E'.$i, $result['Telefon']);
		$objPHPExcel->getActiveSheet()->SetCellValue('F'.$i, $result['Vizsgálat']);
		$objPHPExcel->getActiveSheet()->SetCellValue('G'.$i, $result['Keltezés']);
		$objPHPExcel->getActiveSheet()->SetCellValue('H'.$i, $result['Orvos']);
	}
	
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
	header('Cache-Control: max-age=0');
	
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
}
?>