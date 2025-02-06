<?php

class ReviewService {

    public function __construct() {

        $exp=time() + 60 * 60 * 24 * 365;
        if (!isset($_COOKIE["ertekelesuserid"])) {
            $uid=sha1(date("YmdHis".rand(1,999999)));
            setcookie("ertekelesuserid",$uid,$exp,"/");
            $_COOKIE["lang"] = "hu";
        }


        if (isset($_GET["elang"]) && in_array($_GET["elang"],array("hu","de","en"))) {
            setcookie("lang",$_GET["elang"],$exp,"/");
            header("location:index.php?eform={$_SESSION["eform"]}".(isset($_GET["kerdes"])?"&kerdes":""));
            die();
        }


        if (isset($_POST["formkitolt"])) {
            $error=array();
            $tarolas=array();
            $datum=date("Y-m-d H:i:s");

            $kerdesRes = sql_query("select k.*,g.tipus from ertekeles_kerdesek k left join ertekeles_valaszgroups g on k.valaszgroupid=g.id where k.formid=? order by k.sorrend", array($_POST["formkitolt"]));
            while ($kerdesData=sql_fetch_array($kerdesRes)) {
                $valasz="";
                if ($kerdesData["tipus"]==1) {
                    if (isset($_POST["valasz_{$kerdesData["id"]}"])) {
                        $v=sql_fetch_array(sql_query("select * from ertekeles_valaszok where id=?",array($_POST["valasz_{$kerdesData["id"]}"])));
                        $valasz=$v["valasz_hu"];
                    } else {
                        if ($kerdesData["kotelezo"] == 1) {
                            $error[] = "Kérjük válaszoljon az összes csillaggal jelölt kötelező kérdésre";
                            continue;
                        } else {
                            $valasz = "nincs válasz";
                        }
                    }
                }
                if ($kerdesData["tipus"]==4) {
                    $vRes=sql_query("select * from ertekeles_valaszok where valaszgroupid=?",array($kerdesData["valaszgroupid"]));
                    while ($valaszData=sql_fetch_array($vRes)) {
                        if (isset($_POST["valasz_{$kerdesData["id"]}_{$valaszData["id"]}"])) {
                            $valasz.=", ".$valaszData["valasz_hu"];
                        }
                    }
                    $valasz=substr($valasz,2);
                }
                if ($kerdesData["tipus"]==3) {
                    $valasz=$_POST["valasz_{$kerdesData["id"]}"];
                }

                $valaszExtraSzoveg = $_POST["valaszc_{$kerdesData["id"]}"] ?? "";

                sql_query("insert into ertekeles_data set cegid=?, formid=?, datum=?, sessid=?, kerdesid=?, valaszszoveg=?, valaszextraszoveg=?",
                    [$_SESSION["eceg"], $kerdesData["formid"], $datum, $_COOKIE["ertekelesuserid"], $kerdesData["id"], $valasz, $valaszExtraSzoveg]);
            }
            if (count($error)>0) {
                $_SESSION["formerror"] = array_unique($error);
                sql_query("delete from ertekeles_data where formid=? and sessid=?", array($_POST["formkitolt"],$_COOKIE["ertekelesuserid"]));
            } else {
                header("location: ".$this->ertekelesURL("&thanks"));
                die();
            }
        }

    }


    public function ertekelesURL($link):string {
        $url="index.php?eform={$_SESSION["eform"]}";
        if ($link!="") $url.="&".$link;
        return $url;
    }

    public function getFormText($key) {
        $szoveg=$GLOBALS["formData"]["{$key}_hu"];
        if ($GLOBALS["formData"]["{$key}_{$_COOKIE["lang"]}"]!="") {
            $szoveg=$GLOBALS["formData"]["{$key}_{$_COOKIE["lang"]}"];
        }
        return $szoveg;
    }

    public function getFormData($formCode) {
        return sql_fetch_array(sql_query("select * from ertekeles_formok where kod=?",array($formCode)));
    }

    public function ertekelesMainMenu():string {
        $htmlout="";
        $htmlout.= "<div style='display:table;width:100%;margin:20px 0px;'>";
        $htmlout.= "<div style='display:table-row;'>";
        $htmlout.= "<div style='display:table-cell;vertical-align:middle;width:20px;padding-left:40px;'>";
        $htmlout.= "<a href='".$this->ertekelesURL("")."'><img width='30' src='images/hmm_logo.png' alt='' title='".$this->getFormText("megnev")."' style='margin-right:10px;' /></a>";
        $htmlout.= "</div>";

        $link=$_SERVER["PHP_SELF"];
        if ($_SERVER["QUERY_STRING"]!="") {
            $link.="?".$_SERVER["QUERY_STRING"]."&";
        } else {
            $link.="?";
        }

        $htmlout.= "<div style='display:table-cell;vertical-align:middle;padding-left:10px;padding-right:40px;text-align:left;'>";

        if (isset($_SERVER["HTTP_HOST"]) && substr_count($_SERVER["HTTP_HOST"],"anmeldung")==0) {
            $htmlout.= "<a style='".($_COOKIE["lang"]=="hu"?"opacity:1":"opacity:.5")."' href='{$link}elang=hu'>HU</a> ";
            $htmlout.= "<a style='".($_COOKIE["lang"]=="en"?"opacity:1":"opacity:.5")."' href='{$link}elang=en'>EN</a> ";
            $htmlout.= "<a style='".($_COOKIE["lang"]=="de"?"opacity:1":"opacity:.5")."' href='{$link}elang=de'>DE</a> ";
        }

        $htmlout.= "</div>";
        $htmlout.= "</div>";
        $htmlout.= "</div>";

        return $htmlout;
    }


    public function ertekelesHeader():string {
        $htmlout="";
        $htmlout.="<div class='ertekelesheader'>";
        $htmlout.="<div style='font-size:22px;font-family:robotoregular'><img src='/images/hmm_logo_nagy.png' height='60' alt='' /></div>";
        $htmlout.=$this->getFormText("megnev");
        $htmlout.="</div>";
        return $htmlout;
    }


    public function translate($arr,$key) {
        $szoveg=$arr["{$key}_{$_COOKIE["lang"]}"];
        if ($szoveg=="") $szoveg=$arr["{$key}_hu"];
        return $szoveg;
    }

    public function ertekelesContent():string {
        $lang = new Lang();
        $webText = $lang->webText;

        $htmlout="";

        $htmlout .= "<div style='margin:40px auto;max-width:700px;color:#888;line-height:22px;'>";
        if (isset($_GET["kerdes"])) {
            if (isset($_SESSION["formerror"])) {
                $htmlout.="<div class='ertekeleshiba'>";
                foreach ($_SESSION["formerror"] as $error) {
                    $htmlout.= "<div>{$error}</div>";
                }
                $htmlout.="</div>";
                unset($_SESSION["formerror"]);
            }

            $kerdesRes = sql_query("select k.*,g.tipus from ertekeles_kerdesek k left join ertekeles_valaszgroups g on k.valaszgroupid=g.id where k.formid=? order by k.sorrend",array($GLOBALS["formData"]["id"]));

            $htmlout.="<form method='post'><input type='hidden' name='formkitolt' value='{$GLOBALS["formData"]["id"]}' />";
            while ($kerdesData=sql_fetch_array($kerdesRes)) {
                $htmlout.= "<div class='kerdesdiv'>";

                if ($kerdesData["tipus"] == 5) {
                    $htmlout.= "<h2 style='color:#444;'>".$this->translate($kerdesData, "kerdes")."</h2>";
                    if ($kerdesData["subtext"] != "") {
                        $htmlout.= "<div style=''>{$kerdesData["subtext"]}</div>";
                    }
                    $htmlout.= "</div>";
                    continue;
                } else {
                    $htmlout .= "<div class='kerdesszoveg'>";
                    if ($kerdesData["kotelezo"] != 0) {
                        $htmlout .= "* ";
                    }
                    $htmlout .= $this->translate($kerdesData, "kerdes");
                    $htmlout .= "</div>";
                }

                $htmlout.= "<div class='valaszdiv'>";

                //egy válaszlehetőség listából
                if ($kerdesData["tipus"]==1) {
                    $valaszRes = sql_query("select * from ertekeles_valaszok where valaszgroupid=?",array($kerdesData["valaszgroupid"]))->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($valaszRes as $valaszData) {
                        $htmlout.="<input type='radio' name='valasz_{$kerdesData["id"]}' id='valasz_{$kerdesData["id"]}_{$valaszData["id"]}' value='{$valaszData["id"]}'".(isset($_POST["valasz_{$kerdesData["id"]}"]) && $_POST["valasz_{$kerdesData["id"]}"]==$valaszData["id"]?" checked":"")."/>";
                        $htmlout.= "<label for='valasz_{$kerdesData["id"]}_{$valaszData["id"]}'> ".$this->translate($valaszData,"valasz")."</label>&nbsp;&nbsp;";
                    }
                    if ($kerdesData["textcomment"] == 1) {
                        $valasz = $_POST["valaszc_{$kerdesData["id"]}"] ?? "";
                        $htmlout .= "<div><textarea placeholder='Ha szeretné, szövegesen is kifejtheti...' name='valaszc_{$kerdesData["id"]}' id='valaszc_{$kerdesData["id"]}' style='width:100%;height:100px;margin-top:10px;box-sizing: border-box;font-size: 14px;'>" . htmlentities($valasz) . "</textarea></div>";
                    }
                }

                //több válaszlehetőség listából
                if ($kerdesData["tipus"]==4) {
                    $valaszRes = sql_query("select * from ertekeles_valaszok where valaszgroupid=?",array($kerdesData["valaszgroupid"]))->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($valaszRes as $valaszData) {
                        $htmlout.= "<div>";
                        $htmlout.= "<input type='checkbox' name='valasz_{$kerdesData["id"]}_{$valaszData["id"]}' id='valasz_{$kerdesData["id"]}_{$valaszData["id"]}' value='{$valaszData["id"]}'".(isset($_POST["valasz_{$kerdesData["id"]}_{$valaszData["id"]}"])?" checked":"")."/>";
                        $htmlout.= "<label for='valasz_{$kerdesData["id"]}_{$valaszData["id"]}'> ".$this->translate($valaszData,"valasz")."</label>&nbsp;&nbsp;";
                        $htmlout.= "</div>";
                    }
                }

                //szöveges válasz
                if ($kerdesData["tipus"]==3) {
                    $valasz = $_POST["valasz_{$kerdesData["id"]}"] ?? "";
                    $htmlout.="<textarea name='valasz_{$kerdesData["id"]}' id='valasz_{$kerdesData["id"]}' style='width:100%;height:100px;box-sizing: border-box'>".htmlentities($valasz)."</textarea> ";
                }
                $htmlout.="</div>";

                $htmlout.="</div>";
            }

            $htmlout.="<div style='margin:20px auto;width:300px;'><input type='submit' value='{$webText["kerdoivelkuldese"]}' class='flat-button' /></div>";
            $htmlout.="</form>";

        } elseif (isset($_GET["thanks"])) {
            $htmlout .= "<div class='kerdesdiv'>" . $this->getFormText("koszonoszoveg") . "</div>";
        } else {
            $intro = $this->getFormText("introszoveg");
            $htmlout .= "<div style='text-align: center;'>{$intro}</div>";
            $htmlout .= "<div style='margin:20px auto;width:300px;'><input onclick='window.location.href=\"" . $this->ertekelesURL("kerdes") . "\"' type='button' value='{$webText["kerdoivkitoltese"]}' class='flat-button' /></div>";
        }
        $htmlout .= "</div>";

        return $htmlout;
    }



}