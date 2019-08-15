<?php

sql_connect();

sql_query("SET NAMES utf8");
sql_query("SET CHARACTER SET utf8");
sql_query("SET COLLATION_CONNECTION='utf8_unicode_ci'");


$exp=time() + 60 * 60 * 24 * 365;
if (!isset($_COOKIE["lang"])) {
	setcookie("lang","hu",$exp,"/");
	$_COOKIE["lang"]="hu";
}

if (isset($_GET["setlang"])) $_GET["lang"] = $_GET["setlang"];
if (isset($_GET["lang"]) && in_array($_GET["lang"],array("hu","de","en"))) {
	setcookie("lang",$_GET["lang"],$exp,"/");
	$params = $_SERVER["QUERY_STRING"];
    $params = str_replace("lang=","slang=",$params);
    $params = str_replace("setlang=","slang=",$params);
	header("location:index.php?{$params}");
	die();
}

require_once("autoload.php");

if (isset($_GET["phpinfo_jns"])) {
    phpinfo();
    die();
}

$honaptext=$GLOBALS["honaptext"]=array("","január","február","március","április","május","június","július","augusztus","szeptember","október","november","december");
$hetnap=$GLOBALS["hetnap"]=array("","hétfő","kedd","szerda","csütörtök","péntek","szombat","vasárnap");

$adminszintek=array("recepció","cégadmin","<b>admin</b>");

$uploadbasepath="/uploads";


$alkalmassagvariaciok["I"]="alkalmas";
$alkalmassagvariaciok["N"]="alkalmatlan";
$alkalmassagvariaciok["IN"]="ideiglenesen nem alkalmas";
$alkalmassagvariaciok["K"]="korlátozottan alkalmas";
$GLOBALS["alkalmassagvariaciok"]=$alkalmassagvariaciok;


$GLOBALS["daydisplay"]=7;


function sql_connect() {
	$MYSQL_USER="hungariamed";
	$MYSQL_PASS="hmedpass";
	$MYSQL_HOST="localhost";
	$MYSQL_DB="keltexmed";

	if (substr_count(getSubDomain(),"teszt")) $MYSQL_DB="hungariamedteszt";

	try {
		$GLOBALS["db"]=new PDO("mysql:host={$MYSQL_HOST};dbname={$MYSQL_DB};charset=utf8", $MYSQL_USER, $MYSQL_PASS);
	} catch (PDOException $e) {
    print "Error: " . $e->getMessage();
    die();
	}
}


function sql_query($q,$params=null) {
    $startTime = microtime(true);
	$stmt=$GLOBALS["db"]->prepare($q);
	$stmt->execute($params);
	$error=$stmt->errorInfo();
	if ($error[2]!="") print_r($error);
    $endTime = microtime(true);
    if ($_SERVER["REMOTE_ADDR"]=="194.143.226.42") {
        $time = $endTime - $startTime;
        $GLOBALS["alltime"]+=$time;
        //echo str_replace("?","%",$q)." ".print_r($params,true)." ".$time." ".$GLOBALS["alltime"]."<br/>";
    }
	return $stmt;
}


function sql_fetch_array($stmt) {
	//return mysqli_fetch_assoc($stmt);
	$row=$stmt->fetch(PDO::FETCH_ASSOC);
	return $row;
}

function sql_num_rows($stmt) {
	//return mysqli_num_rows($stmt);
	return $stmt->rowCount();
}

function sql_insert_id() {
	return $GLOBALS["db"]->lastInsertId();
	//return mysqli_insert_id($GLOBALS["link"]);
}

if (isset($_GET["logout"])) {
	unset($_SESSION["loggeduser"]);
	unset($_SESSION["user"]);
	header("location:index.php");
	die();
}

if (isset($_SESSION["loggeduser"])) {
	$_SESSION["user"]=sql_fetch_array(sql_query("select * from felhasznalok where id=?",array($_SESSION["loggeduser"])));
}


//cgi indítás esetén nem kell
if (!substr_count(php_sapi_name(),"cgi")) {
    domainProcess();
}

function domainProcess() {
    $d=getSubDomain();

    if ($d=="ertekeles") {
        $GLOBALS["ertekeles"]=1;
        return;
    }

    if ($d=="keltexmed") $d="bejelentkezes";
    if ($d!="admin") {
        if (!$_SESSION["helyszindata"]=sql_fetch_array(sql_query("select * from cegek where CONCAT(',',RTRIM(domain),',') LIKE CONCAT('%,',?,',%') or tesztdomain=?",array($d,$d)))) {
            unset($_SESSION["helyszindata"]);
            die("Domain nem található!");
        }
    }
}


function substr_jns($s,$p1,$p2) {
	$sz=iconv("UTF-8","ISO-8859-2",$s);
	//return $sz;
	$sz=substr($sz,$p1,$p2);
	$sz=iconv("ISO-8859-2","UTF-8",$sz);
	return $sz;
}


function getSubDomain() {
	$domain="";
	if (isset($_SERVER["HTTP_HOST"])) {
		$domain=str_replace("www.","",$_SERVER["HTTP_HOST"]);
		$domain=substr($domain,0,strpos($domain,"."));
	}
	return $domain;
}




function numtostring($Mit) {
    $EgyesStr = array('', 'egy', 'kettő', 'három', 'négy', 'öt', 'hat', 'hét', 'nyolc', 'kilenc');
    $TizesStr = array('', 'tíz', 'húsz', 'harminc', 'negyven', 'ötven', 'hatvan', 'hetven', 'nyolcvan', 'kilencven');
    $TizenStr = array('', 'tizen', 'huszon', 'harminc', 'negyven', 'ötven', 'hatvan', 'hetven', 'nyolcvan', 'kilencven');
    $Result = '';
    if ($Mit == 0) {
        $Result = 'Nulla';
    } else {
        $Maradek = abs($Mit);
        if ($Maradek > 999999999999) {
            die("Túl nagy szám");
        }

        $Oszto=1000000000;
        $Osztonev="milliárd";
        if ($Maradek>=$Oszto) {
            if (mb_strlen($Result)>0) $Result = $Result . '-';
            $Mit=$Maradek/$Oszto;
            if ($Mit>=100) $Result = $Result.$EgyesStr[$Mit/100].'száz';
            $Mit = $Mit % 100;
            if ($Mit % 10 !== 0) {
                $Result = $Result . $TizenStr[$Mit / 10] . $EgyesStr[$Mit % 10] . $Osztonev;
            } else {
                $Result = $Result . $TizesStr[$Mit / 10] . $Osztonev;
            }
        }
        $Maradek=$Maradek % $Oszto;

        $Oszto=1000000;
        $Osztonev="millió";
        if ($Maradek>=$Oszto) {
            if (mb_strlen($Result)>0) $Result = $Result . '-';
            $Mit=$Maradek/$Oszto;
            if ($Mit>=100) $Result = $Result.$EgyesStr[$Mit/100].'száz';
            $Mit = $Mit % 100;
            if ($Mit % 10 !== 0) {
                $Result = $Result . $TizenStr[$Mit / 10] . $EgyesStr[$Mit % 10] . $Osztonev;
            } else {
                $Result = $Result . $TizesStr[$Mit / 10] . $Osztonev;
            }
        }
        $Maradek=$Maradek % $Oszto;

        $Oszto=1000;
        $Osztonev="ezer";
        if ($Maradek>=$Oszto) {
            if (mb_strlen($Result)>0) $Result = $Result . '-';
            $Mit=$Maradek/$Oszto;
            if ($Mit>=100) $Result = $Result.$EgyesStr[$Mit/100].'száz';
            $Mit = $Mit % 100;
            if ($Mit % 10 !== 0) {
                $Result = $Result . $TizenStr[$Mit / 10] . $EgyesStr[$Mit % 10] . $Osztonev;
            } else {
                $Result = $Result . $TizesStr[$Mit / 10] . $Osztonev;
            }
        }
        $Maradek=$Maradek % $Oszto;

        $Oszto=1;
        $Osztonev="";
        if ($Maradek>=$Oszto) {
            if (mb_strlen($Result)>0) $Result = $Result . '-';
            $Mit=$Maradek/$Oszto;
            if ($Mit>=100) $Result = $Result.$EgyesStr[$Mit/100].'száz';
            $Mit = $Mit % 100;
            if ($Mit % 10 !== 0) {
                $Result = $Result . $TizenStr[$Mit / 10] . $EgyesStr[$Mit % 10] . $Osztonev;
            } else {
                $Result = $Result . $TizesStr[$Mit / 10] . $Osztonev;
            }
        }
        $Maradek=$Maradek % $Oszto;

        /*
          Alakit($Maradek, 1000000000, 'milliárd');
          Alakit($Maradek, 1000000, 'millió');
          Alakit($Maradek, 1000, 'ezer');
          Alakit($Maradek, 1, '');
        */

        $Result = ucfirst($Result);
        if ($Mit<0) $Result = 'Mínusz ' . $Result;
    }

    return $Result;
}


function selectOrvosForFoglalas($fid) {
	$rowf=sql_fetch_array(sql_query("select * from foglalasok where id='{$fid}'"));
	$nap=substr($rowf["datum"],0,10);
	$ora=substr($rowf["datum"],11,5);
	
	if ($rowf["orvosassigned"]!=0) return sql_query("select o.*,o.id as orvosid from orvosok o where id='{$rowf["orvosassigned"]}'");
	
	return sql_query("SELECT WEEK('{$nap}',3)%2 AS weekmodulo,b.*,o.* FROM orvos_beosztas b 
		LEFT JOIN orvosok o ON o.`id`=b.`orvosid`
		WHERE b.`helyszinid`='{$rowf["helyszinid"]}' and (b.cegid='{$rowf["cegid"]}' or b.cegid=0) AND (INSTR(tipusok,'|{$rowf["szurestipusid"]}|') OR tipusok='') AND nap=WEEKDAY('{$nap}')+1 AND TIME(tol)<=TIME('{$ora}') AND TIME(ig)>TIME('{$ora}') AND TRIM(b.tipusok)<>''
		order by IF (hetek=1,weekmodulo=0,weekmodulo=1)");
}



function updateFoglalasData($id) {
    $rInterval = 0;
    if (isset($_REQUEST["rinterval"])) $rInterval = intval($_REQUEST["rinterval"]);

	sql_query("UPDATE foglalasok SET pass=SHA1(CONCAT(id,regdatum,datum)), rinterval=? where id=? and pass=''",array($rInterval,$id));
	
	if (isset($_SESSION["filefix"])) {
		sql_query("update dokumentumok set foglalasid=?,sess='validated' where sess=?",array($id,$_SESSION["filefix"].session_id()));
	}
}



function sendToCegAndOrvos($id,$force=0) {
	include_once("phpmailer/class.phpmailer.php");

	$row=sql_fetch_array(sql_query("SELECT * FROM foglalasok f WHERE f.id=?",array($id)));

	if ($row["ertesitve"]==1 && $force==0) return;

	//orvos kikeresése és értesítése
	if ($rowf=sql_fetch_array(sql_query("SELECT h.cim AS helyszin,sz.megnev AS szurestipus,sz.megnev_en AS szurestipus_en,sz.megnev_de AS szurestipus_de,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail,c.calendaritem FROM foglalasok f
		LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
		LEFT JOIN cegek c on c.id=f.cegid
		LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
		WHERE f.id=?",array($id)))) {

	    $cegId=$rowf["cegid"];
		//$reso=selectOrvosForFoglalas($id);

        //ha cégnek is a foglalás nyelvén menjen az értesítés, akkor majd kell a következő 2 sor
        //if ($rowf["rlang"] == "en" && $rowf["szurestipus_en"] != "") $rowf["szurestipus"] = $rowf["szurestipus_en"];
        //if ($rowf["rlang"] == "de" && $rowf["szurestipus_de"] != "") $rowf["szurestipus"] = $rowf["szurestipus_de"];

		if ($rowo=sql_fetch_array(sql_query("select * from orvosok where id=?",array($rowf["orvosassigned"])))) {
            require_once("includes/seeme-gateway-class.php");

            $resp=sql_query("select * from smsphones where orvosid=? and smsfoglalas=1 and smsgroupfoglalas=0 and instr(cegek,'|{$cegId}|')",array($rowo["id"]));
            while ($rowp=sql_fetch_array($resp)) {
                sendSMS(trim($rowp["tel"]),"Hungáriamed időpont foglalása érkezett: ".substr($rowf["datum"],0,16)." {$rowf["helyszin"]}");
            }

			//orvos tárolása a foglaláshoz
			//sql_query("update foglalasok set orvosassigned='{$rowo["orvosid"]}' where id='{$id}' and orvosassigned=0");
			
            if (trim($rowo["email"])!="") {
                $mbody="";

                $mail = new PHPMailer();
                $mail->FromName="Hungariamed";
                if ($_SERVER["REMOTE_ADDR"] == "84.2.96.42") {
                    $mail->AddAddress("jns@jns.hu");
                } else {
                    $mail->AddAddress($rowo["email"]);
                }

                if ($rowo["visszaigazol"]==1 && $rowo["visszaigazolemail"]!="") {
                    $mbody.="Kedves {$rowo["nev"]}!<br>
                    <br>
                    Foglalása érkezett a Hungariamed foglalási rendszerén keresztül az alábbi adatokkal. Kérjük erre az levélre válaszolva jelezze, hogy tudja-e fogadni a pacienst. Köszönjük!<br>
                    <br>
                    <hr>
                    <br>";
                    $mail->From=$rowo["visszaigazolemail"];
                    $mail->AddReplyTo($rowo["visszaigazolemail"]);
                    } else {
                    $mail->From="noreply@hungariamed.hu";
                    $mail->AddReplyTo("noreply@hungariamed.hu");
                }
                $mail->IsHTML(true);

                $t=iconv("UTF-8","ISO-8859-2","{$rowf["cegnev"]} - időpont regisztráció {$rowo["nev"]} részére");

                $mbody.="Név: {$rowf["nev"]}<br>";
                $mbody.="Cég: {$rowf["cegnev"]}<br>";
                $mbody.="TAJ: {$rowf["taj"]}<br>";
                $mbody.="Munkakor: {$rowf["munkakor"]}<br>";
                $mbody.="Telefon: {$rowf["telefon"]}<br><br>";
                $mbody.="<b>Időpont: {$rowf["datum"]}</b><br><br>";
                $mbody.="Szűréstípus: {$rowf["szurestipus"]}<br>";
                $mbody.="Helyszín: {$rowf["helyszin"]}<br>";
                if ($rowf["megj"]!="") $mbody.="Megjegyzés: {$rowf["megj"]}<br>";
                    $mbody.="<br/>";

                $mail->Subject=$t;
                $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);

                if (true) {
                    $mail->addStringAttachment(getCalendarItem($rowf),'foglalas.ics','base64','text/calendar');
                }

                $mail->Send();
            }

		}
	}	
	
	$res=sql_query("SELECT o.`nev` AS orvosnev,o.`email` AS orvosemail,o.hmedemail,h.cim AS helyszin,sz.megnev AS szurestipus,f.*,c.megnev as cegnev,c.email as cegemail,c.foglalasemail FROM foglalasok f
	LEFT JOIN helyszinek h ON h.id=f.`helyszinid`
	LEFT JOIN cegek c on c.id=f.cegid
	LEFT JOIN orvosok o ON o.`id`=f.`orvosassigned`
	LEFT JOIN szurestipusok sz ON sz.id=f.`szurestipusid`
	WHERE f.id='{$id}'");
	if ($row=sql_fetch_array($res)) {
	    if ($row["foglalasemail"] == 1) {
		    $mail = new PHPMailer();
		    $mail->From="noreply@hungariamed.hu";
		    $mail->FromName="Hungariamed";
		    $mail->AddAddress($row["cegemail"]); 
		    if ($row["hmedemail"]!="") $mail->AddAddress($row["hmedemail"]); 
		    $mail->AddReplyTo("noreply@hungariamed.hu");
		    $mail->IsHTML(true); 
		     
		    $t=iconv("UTF-8","ISO-8859-2","{$row["cegnev"]} - időpont regisztráció");
		
		    $mbody="Név: {$row["nev"]}<br>";
		    $mbody.="Cég: {$row["cegnev"]}<br>";
		    $mbody.="TAJ: {$row["taj"]}<br>";
		    $mbody.="Munkakör: {$row["munkakor"]}<br>";
		    $mbody.="Telefon: {$row["telefon"]}<br><br>";
		    $mbody.="<b>Időpont: {$row["datum"]}</b><br><br>";
		    $mbody.="Szűréstípus: {$row["szurestipus"]}<br>";
		    $mbody.="Helyszín: {$row["helyszin"]}<br>";
		    if ($row["megj"]!="") $mbody.="Megjegyzés: {$row["megj"]}<br>";
				$mbody.="<br/>";
				
				if ($row["orvosnev"]!="" && $row["orvosemail"]!="") $mbody.="Értesített orvos: {$row["orvosnev"]} ({$row["orvosemail"]})";
				
		    $mail->Subject=$t;
		    $mail->Body=iconv("UTF-8","ISO-8859-2",$mbody);
		    $mail->Send();
	    }
	}

	sql_query("update foglalasok set ertesitve=1 where id='{$id}'");
}


if (isset($_GET["tesztcalendar"])) {
    $foglalasData["datum"] = "2019-06-19 14:45:00";
    echo getCalendarItem($foglalasData);
    die;
}

function getCalendarItem($foglalasData) {
    $webTextLocal = getWebTexts($foglalasData["rlang"]);

    $interval = (int)$foglalasData["rinterval"];
    if ($interval == 0) $interval = 15;
    $dateStart = date("Ymd",strtotime("{$foglalasData["datum"]} -2 hour"));
    $timeStart = date("His",strtotime("{$foglalasData["datum"]} -2 hour"));
    $dateEnd = date("Ymd",strtotime("{$foglalasData["datum"]} -2 hour + {$interval} minute"));
    $timeEnd = date("His",strtotime("{$foglalasData["datum"]} -2 hour + {$interval} minute"));

    $ical="BEGIN:VCALENDAR
VERSION:2.0
PRODID://Drupal iCal API//EN
BEGIN:VEVENT
UID:http://www.icalmaker.com/event/d8fefcc9-a576-4432-8b20-40e90889affd
DTSTAMP:".date("Ymd")."T".date("His")."Z
DTSTART:{$dateStart}T{$timeStart}Z
DTEND:{$dateEnd}T{$timeEnd}Z
SUMMARY:{$webTextLocal["idopontfoglalas"]} - {$foglalasData["nev"]}
LOCATION:{$foglalasData["helyszin"]}
DESCRIPTION:{$foglalasData["szurestipus"]}
ORGANIZER;CN=\"Hungária Med-m Kft.\":mailto:info@hungariamed.hu
END:VEVENT
END:VCALENDAR";

    return $ical;
}

function fixPhoneNumber($tel) {
	$tel=str_replace("(","",$tel);
	$tel=str_replace(")","",$tel);
	$tel=str_replace("-","",$tel);
	$tel=str_replace("/","",$tel);
	$tel=str_replace("+","",$tel);
	$tel=str_replace(" ","",$tel);
	if (substr($tel,0,2)=="06") $tel="36".substr($tel,2);
	return $tel;
}


function checkSzulDatum($datum) {
	$datum=str_replace("-","",$datum);
	$datum=str_replace(".","",$datum);
	$datum=str_replace(" ","",$datum);

	if (strlen($datum)!=8) return false;
	if (!is_numeric($datum)) return false;

	$ev=intval(substr($datum,0,4));
	$ho=intval(substr($datum,4,2));
	$nap=intval(substr($datum,6,2));
	
	if ($ev<1900 || $ev>date("Y")) return false;
	if ($ho<1 || $ho>12) return false;
	if ($nap<1 || $nap>31) return false;
	
	return true;
}


function datumSelector($date,$prefix) {
	global $webText;
	$h="";
	
	$ev=substr($date,0,4);
	$ho=substr($date,5,2);
	$nap=substr($date,8,2);
	
	if($class != NULL) $design = "class = '".$class."' ";
	$h.= "<select ".($class != NULL ? $design."style='width:83px;font-size:12px'" : "")." name='{$prefix}ev'>";
	$h.= "<option value='0'>{$webText["ev"]}</option>";
	for ($i=date("Y");$i>date("Y")-100;$i--) {
		$h.= "<option value='{$i}'".($ev==$i?" selected":"").">{$i}</option>";
	}
	$h.= "</select> ";

	$h.= "<select ".($class != NULL ? $design."style='width:130px;font-size:12px'" : "")." name='{$prefix}ho'>";
	$h.= "<option value='0'>{$webText["ho"]}</option>";
	for ($i=1;$i<=12;$i++) {
		$h.= "<option value='{$i}'".($ho==$i?" selected":"").">{$webText["honaptext"][$i]}</option>";
	}
	$h.= "</select> ";

	$h.= "<select ".($class != NULL ? $design."style='width:78px;font-size:12px'" : "")." name='{$prefix}nap'>";
	$h.= "<option value='0'>{$webText["nap"]}</option>";
	for ($i=1;$i<=31;$i++) {
		$h.= "<option value='{$i}'".($nap==$i?" selected":"").">{$i}</option>";
	}
	$h.= "</select>";

	return $h;
}

function validateDate($date,$format="Y-m-d H:i:s") {
    $d=DateTime::createFromFormat($format, $date);
    return $d && $d->format($format)==$date;
}


function isTesztIP() {
	return in_array($_SERVER["REMOTE_ADDR"],array("88.151.97.121","81.182.23.124","5.204.54.10","81.182.23.106"));
}


function getASZF() {
	$aszf=$GLOBALS["aszf_hu"];
	if (isset($GLOBALS["aszf_{$_COOKIE["lang"]}"])) $aszf=$GLOBALS["aszf_{$_COOKIE["lang"]}"];
	
	$aszf=str_replace("bejelentkezes.hungariamed.hu",$_SERVER["HTTP_HOST"],$aszf);
	return $aszf;
}

$GLOBALS["aszf_hu"]="Tájékoztatjuk, hogy a regisztráció kitöltése során megadott adatok az információs önrendelkezési jogról és az információszabadságról szóló, 2011. évi CXII. törvényben (továbbiakban: törvény) foglaltak alapján személyes adatnak minősülnek. Ön a regisztráció benyújtásával hozzájárulását adja, hogy az itt megadott személyes adatait a Hungária Med-M Kft. a törvényben meghatározott feltételek betartásával kezelje.<br/>
	<br/>
	A hivatkozott törvényben foglaltak alapján tájékoztatjuk az alábbiakról:
	<ul>
	<li><b>Kezelendő adatok köre:</b> a regisztráló személy neve, e-mail címe, TAJ-száma, telefonszáma, neme, születési dátuma, lakcíme, munkaköre</li>
	<li><b>Adatkezelés módja:</b> Hungária Med-M Kft. belső rendszerében, kizárólag az arra felhatalmazottak hozzáférésével</li>
	<li><b>Adatkezelés célja:</b> a bejelentkezes.hungariamed.hu oldal használata, regisztrációval a biztonságos belépés biztosítása</li>
	<li><b>Adatkezelés jogalapja:</b> érintett személyek hozzájárulása</li>
	<li><b>Adatkezelés időtartama:</b> az bejelentkezes.hungariamed.hu oldalon történő regisztráció önkéntes törléséig</li>
	<li><b>Adatkezelés helye:</b> Hungária Med M Kft., 1135, Budapest, Jász u. 33-35.</li>
	<li><b>Adatkezelő személye:</b> Hungária Med M Kft.</li>
	<li><b>Adatokhoz  hozzáférők köre:</b> Hungária Med M Kft. erre feljogosított munkatársai, valamint a vele szerződésben álló, számára IT rendszerfejlesztést és -üzemeltetést végző Kardi-Soft Kft. Cím: 9024 Győr, Táncsics M. u. 43.</li>
	<li><b>Jogorvoslati lehetőségek:</b>  az információs önrendelkezési jogról és az információszabadságról szóló 2011. évi CXII. törvény 22 §-ában foglaltak alapján bírói út igénybevétele</li>
	</ul>
	Az adatkezelésről további információt, valamint adatainak módosítását, illetve törlését a Hungária Med-M Kft. alábbi kollégájánál bármikor kérheti, azonban az adatok törlése a bejelentkezes.hungariamed.hu oldal további igénybevételét nem teszi lehetővé.<br/>
	<br/>
	Név: Sorger Éva   e-mail cím: sorger.eva@hungariamed.hu";


$GLOBALS["aszf_en"]="We would like to inform you that when you fill in the registration form the data you have provided qualify as personal data under Act CXII of 2011 (hereinafter referred to as Act) on the right to self-determination as regards information and freedom of information. By submitting your registration you grant permission that your personal data given to us are processed and used by Hungária Med-M Kft. in compliance with the relevant statutory regulations.<br/>
	<br/>
	Pursuant to the provisions of the Act you are informed of the following:
	<ul>
	<li><b>Scope of data processed:</b> data subject’s name, e-mail address, Social Security Identification Number (TAJ number), telephone, date of birth, address, occupation of the registering person</li>
	<li><b>Method of data processing:</b> within the internal system of Hungária Med-M Kft. and accessed exclusively by persons duly authorised</li>
	<li><b>Objective of data processing:</b> the use of the bpetrol.hungariamed.hu site, ensuring safe access through registration</li>
	<li><b>Legal basis for data processing:</b> consent given by the data subjects concerned</li>
	<li><b>Duration of data processing:</b> up to the voluntary cancellation of registration with the bejelentkezes.hungariamed.hu site</li>
	<li><b>Location of data processing:</b> Hungária Med M Kft., 1135, Budapest, Jász u. 33-35.</li>
	<li><b>Name of data processor:</b> Hungária Med M Kft.</li>
	<li><b>Persons having access to data:</b> duly authorised employees of Hungária Med M Kft. and Kardi-Soft Kft. (Address: 9024 Győr, Táncsics M. u. 43.) as contractual service provider of IT system development and system operation</li>
	<li><b>Legal remedies:</b> judicial process under the provision of Section 22 of Act CXII of 2011 on the right to self-determination as regards information and freedom of information.</li>
	</ul>
	Further information on data processing, changing your personal data or their deletion may at any time be requested from the undermentioned employee of Hungária Med-M Kft. However, the deletion of your personal data means that the bpetrol.hungariamed.hu site may not be used any longer.<br/>
	<br/>
	Name: Sorger Éva   e-mail address: sorger.eva@hungariamed.hu";






function getTajFromString($str) {
	preg_match_all('/\d+/', $str, $matches);
	foreach ($matches[0] as $val) {
		if (strlen($val)==9) return $val;
	}
	return "";
}



function addAttachmentToPaciens($aid) {
    //nem használt function, de ha mégis kell, akkor dokumentum tárolást kicserélni!!!
	if ($rowa=sql_fetch_array(sql_query("select e.subject,a.* from emailattachments a left join email e on e.azo=a.eid where a.id=? and uid=0",array($aid)))) {
		$taj=getTajFromString($rowa["subject"]." ".$rowa["filename"]);
		if ($taj!="") {
			if ($rowu=sql_fetch_array(sql_query("select * from felhasznalok where taj=?",array($taj)))) {
				$file=file_get_contents("/var/www/emailattachments/attachment{$rowa["id"]}.bin");
				$fileSize=filesize("/var/www/emailattachments/attachment{$rowa["id"]}.bin");
				$exten=pathinfo($rowa["filename"], PATHINFO_EXTENSION);

				sql_query("insert into dokumentumok set beutaloid='0',userid='".intval($rowu["id"])."',megnev='',filename='".addslashes($rowa["filename"])."',size='".addslashes($filesize)."',tipus='{$exten}',datum=now(),kod=SHA1(MD5(CONCAT(NOW(),RAND()*20000)))");
				$p=sql_insert_id();
				$destinationFile=get___Doc____Path($p);
				file_put_contents($destinationFile,$file);
				
				sql_query("update emailattachments set uid=? where id=?",array($rowu["id"],$rowa["id"]));
			}
		}
		
	}
}



function logActivity($tipus,$id=0,$megnev="",$query="") {
	$pid=0;
	sql_query("insert into activitylog set datum=now(),userid=?,orvoslogin=?,tipus=?,mid=?,pid=?,megnev=?,query=?",array($_SESSION["adminuser"]["id"],$_SESSION["adminuser"]["orvosid"],$tipus,$id,$pid,$megnev,$query));
}


function getLangLink($langCode) {
    $link = $_SERVER["PHP_SELF"];
    if ($_SERVER["QUERY_STRING"]!="") {
        $link.="?".$_SERVER["QUERY_STRING"]."&";
    } else {
        $link.="?";
    }

    if (substr_count($link,"?page=") == 0 && substr_count($link,"&page=") == 0) {
        if (isset($_GET["page"]) && in_array($_GET["page"],array("main","welcome","idopontfoglalas"))) {
            $link.="page={$_GET["page"]}&";
        }
    }

    $langLink = "<a style='".($_COOKIE["lang"] == $langCode ? "opacity:1":"opacity:.5")."' href='{$link}lang={$langCode}'>".strtoupper($langCode)."</a> ";
    return $langLink;
}