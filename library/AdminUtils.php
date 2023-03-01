<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AdminUtils
{
    public $settings;
    public $leletService;
    public $protocolService;

    public function __construct()
    {
        $this->settings = new Booking_Settings();
        $this->leletService = new AdminLeletService();
        $this->protocolService = new AdminProtocolService();
    }

    public function showCegListSzT($raw, $sor)
    {
        $h = "";
        $resc = sql_query("select id,megnev from cegek order by megnev");
        while ($rowc = sql_fetch_array($resc)) {
            $cegList[$rowc["id"]] = $rowc["megnev"];
        }

        $cegidk = explode("|", $raw);

        for ($i = 0; $i < count($cegidk); $i++) {
            if (isset($cegList[$cegidk[$i]])) $h .= "<span onclick='removeSztCegek({$cegidk[$i]},{$sor})' style='background:#f00;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;display:inline-block;margin:2px 2px 0px 0px;'>- {$cegList[$cegidk[$i]]}</span> ";
        }

        $h .= "<span onclick='$(\"#cegadd{$sor}\").slideToggle();' title='Cég hozzáadása' style='background:#0a0;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;'>+ cég</span>";

        return $h;
    }


    public function cegAddSorSzT($sor)
    {
        $h = "";
        $resc = sql_query("select id,megnev from cegek order by megnev");
        while ($rowc = sql_fetch_array($resc)) {
            $h .= "<span onclick='add2SztCegek({$rowc["id"]},{$sor})' style='background:#0a0;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;display:inline-block;margin:2px 2px 0px 0px;'>+ {$rowc["megnev"]}</span> ";
        }
        return $h;
    }

    public function showFizSzolg($fid, $simple = 0)
    {
        $h = "";
        $res = sql_query("select * from fizkapcs where fid=?", array($fid));
        if (sql_num_rows($res) > 0) {
            if ($simple == 0) $h .= "<div style='padding:10px;margin-bottom:10px;background:#fcc;display:inline-block;'>";
            while ($row = sql_fetch_array($res)) {
                if ($row["megnev"] == "") {
                    $row["megnev"] = "noname";
                }
                $h .= "<div>+ {$row["megnev"]}";
                if ($row["ar"] != 0) $h .= " (" . number_format($row["ar"]) . " Ft)";
                if ($simple == 0) $h .= " [<a href='#' onclick='removeFizSzolg({$fid},{$row["id"]});return false;'>-</a>]</div>";
            }
            if ($simple == 0) $h .= "</div>";
        }
        return $h;
    }

    public function showPaciensFiles($id): string
    {
        $adminUser = new AdminUser();

        $htmlout = "";
        $files = sql_query("select * from dokumentumok where foglalasid=? order by datum desc", array($id))->fetchAll(PDO::FETCH_ASSOC);
        $reservationData = sql_query("select cegid from foglalasok where id=?", [$id])->fetch(PDO::FETCH_ASSOC);
        $htmlout .= "<div style='display:inline-block;'>";
        $htmlout .= "<div style=''>";
        $htmlout .= "<strong>Feltöltött fájlok</strong>";
        if ($adminUser->beutaloAccess()) {
            if ($adminUser->beutaloHozzadasAccess() && Booking_Constants::SQL_DB == "hungariamed" && $reservationData["cegid"] == CompanyService::BP_ID) {
                $htmlout .= "&nbsp;&nbsp;<a href=\"#\" onclick='beutaloHozzadasa({$id});return false'><i class='fa-solid fa-circle-plus'></i> Beutaló</a>";
            }
            if ($adminUser->beutaloHozzadasAccess()) {
                $htmlout .= "&nbsp;&nbsp;<a href=\"#\" onclick='$(\"#beutalofile\").click();return false'><i class='fa-solid fa-circle-plus'></i> File</a>";
                $htmlout .= "<input type='file' multiple onchange='beutaloFileUpload(this, {$id});' name='beutalofile' id='beutalofile' style='display:none;' />";
            }

            $htmlout .= "<div><img id='ajaxloaderbeutalo' style='display:none;height:16px;margin-top:5px;' src='/admin/images/loading.svg' /></div>";
            $htmlout .= "</div>";
            foreach ($files as $file) {
                $deleteLink = $adminUser->beutaloHozzadasAccess() ? "<a href='#' onclick='deleteUploadedFile({$file["id"]}, \"{$file["kod"]}\", {$file["foglalasid"]});return false;'><i class='fa-solid fa-trash'></i></a> " : "";
                $htmlout .= "<div style='padding:1px 0px;'>{$deleteLink}<a target='_blank' href='" . DocAgent::getDocURL($file) . "'>{$file["filename"]}</a></div>";
            }
        } else {
            $htmlout .= "<div>Nincs jogosultságod a fájlok megtekintéséhez</div>";
        }
        $htmlout .= "</div>";

        return $htmlout;
    }

    public function magyarDatum($datum, $weekDay = true)
    {
        $m = date("n", strtotime($datum));
        $n = date("Y-m-d", strtotime($datum));
        $w = date("N", strtotime($datum));
        $date = substr($datum, 0, 4) . " " . ucfirst($GLOBALS["honaptext"][$m]) . " " . intval(substr($n, 8, 2)) . ".";
        if ($weekDay) {
            $date .= " " . $GLOBALS["hetnap"][$w];
        }
        $date .= " " . substr($datum, 11, 5);
        return trim($date);
    }

    public function munkakorlista($dokirexmunkakorid=null)
    {
        $button = "";
        $q = sql_query("SELECT * FROM activitylog WHERE tipus IN(\"munkakorlista_update_started\",\"munkakorlista_update_finished\") ORDER BY datum DESC LIMIT 1");
        $updateStatusz = sql_fetch_array($q);
        if (isset($updateStatusz["tipus"]) && $updateStatusz["tipus"] == "munkakorlista_update_started") {
            $button = "<button type=\"button\" title=\"Munkakör lista manuális frissítés jelenleg fut\" style=\"background-color:#FF4040;color:white;border:none;cursor:pointer;border-radius:6px;height:24px;margin-left:2px\"><i class=\"fa-solid fa-lock\"></i></button>";
        }else{
            $button = "<button onclick=\"refreshMunkakorlista(this)\" type=\"button\" title=\"Munkakör lista manuális frissítése\" style=\"background-color:#3ac63d;color:white;border:none;cursor:pointer;border-radius:6px;height:24px;margin-left:2px\"><i class=\"fas fa-sync\"></i></button>";
        }

        if($dokirexmunkakorid){
            $dokirexMunkakor = sql_fetch_array(sql_query("SELECT Nev FROM dokirex_munkakorok_new WHERE MunkakorID=?", array($dokirexmunkakorid)));
        }

        $html = "";
        $html .= "<div>";
        $html .=    "<div style=\"display:table-cell;vertical-align:middle;\">";
        $html .=        "<select class=\"s2 munkakorlist\" name=\"dokirexmunkakorid\" style=\"width:180px;\">";
        $html .=            "<option value=\"0\">Válaszd ki a munkakört!</option>";

        if(isset($dokirexMunkakor)){
            $html .=         "<option selected=\"true\" value=\"{$dokirexmunkakorid}\">{$dokirexMunkakor["Nev"]}</option>";
        }
       
        $html .=        "</select>";
        $html .=    "</div>";

        $html .=    "<div style=\"display:table-cell;vertical-align:middle;\">";
        $html .=        $button;
        $html .=    "</div>";
        $html .= "</div>";
        return $html;
    }

    public function ceglista($dokirexcegid=null)
    {
        $button = "";
        $q = sql_query("SELECT * FROM activitylog WHERE tipus IN(\"ceglista_update_started\",\"ceglista_update_finished\") ORDER BY datum DESC LIMIT 1");
        $updateStatusz = sql_fetch_array($q);
        if (isset($updateStatusz["tipus"]) && $updateStatusz["tipus"] == "ceglista_update_started") {
            $button = "<button type=\"button\" title=\"Cég lista manuális frissítés jelenleg fut\" style=\"background-color:#FF4040;color:white;border:none;cursor:pointer;border-radius:6px;height:24px;margin-left:2px\"><i class=\"fa-solid fa-lock\"></i></button>";
        }else{
            $button = "<button onclick=\"refreshCeglista(this)\" type=\"button\" title=\"Ceg lista manuális frissítése\" style=\"background-color:#3ac63d;color:white;border:none;cursor:pointer;border-radius:6px;height:24px;margin-left:2px\"><i class=\"fas fa-sync\"></i></button>";
        }

        if($dokirexcegid){
            $dokirexCeg = sql_fetch_array(sql_query("SELECT TelephelyNev,CegNev FROM dokirex_telephelyek WHERE TelephelyID=?", array($dokirexcegid)));
        }

        $html = "";
        $html .= "<div>";
        $html .=    "<div style=\"display:table-cell;vertical-align:middle;\">";
        $html .=        "<select class=\"s2 ceglist\" name=\"dokirexcegid\" style=\"width:180px;\">";
        $html .=            "<option value=\"0\">Válaszd ki a céget!</option>";
        if(isset($dokirexCeg)){
            if(strpos($dokirexCeg["TelephelyNev"],$dokirexCeg["CegNev"])!==false){
                //if($result["TelephelyNev"] == $result["CegNev"]){
                    $megnev = $dokirexCeg["TelephelyNev"];
                }else{
                    $megnev = $dokirexCeg["CegNev"]." - ".$dokirexCeg["TelephelyNev"];
                }
            $html .=         "<option selected=\"true\" value=\"{$dokirexcegid}\">{$megnev}</option>";
        }
        $html .=        "</select>";
        $html .=    "</div>";

        $html .=    "<div style=\"display:table-cell;vertical-align:middle;\">";
        $html .=        $button;
        $html .=    "</div>";
        $html .= "</div>";
        return $html;
    }
}
