<?php

class AdminBeoEditor {

    private $adminUser;
    private $adminUtils;
    public $beosztasService;

    public function __construct()
    {
        $this->adminUser = new AdminUser();
        $this->adminUtils = new AdminUtils();
        $this->beosztasService = new BeosztasService();

        if (isset($_POST["savebeorow"])) {
            if (!$this->adminUser->doctorsCalendarAccess()) {
                die("permission");
            }

            $aktiv  = isset($_POST["aktiv"])?1:0;
            $sorban = isset($_POST["csaksorban"])?1:0;
            $sorban = isset($_POST["csakvsorban"])?2:$sorban;
            $noreservation = isset($_POST["noreservation"])?1:0;
            $nopack = isset($_POST["nopack"])?1:0;
            $potig = $_POST["potig"];

            if (!preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9])/", $potig)) {
                $potig = "";
            }

            $nap = $_POST["weekday"];
            if ($nap == 10) {
                $_POST["validfrom"] = "0000-00-00";
                $_POST["validto"] = "0000-00-00";
            }

            $params = [$nap, $_POST["beonap"], $_POST["hetek"], $_POST["helyszinid"], $sorban, $aktiv, $_POST["tol"], $_POST["ig"], $potig, $noreservation, $_POST["validfrom"], $_POST["validto"], $_POST["bmegj"], $nopack, $_POST["beosztasid"]];
            sql_query("update orvos_beosztas_new set nap=?, beonap=?, hetek=?, helyszinid=?, csaksorban=?, aktiv=?, tol=?, ig=?, potig=?, noreservation=?, validfrom=?, validto=?, bmegj=?, nopack=? where id=?", $params);

            die("ok");
        }
    }

    public function show($doctorId):string {
        $html = "";

        $doctorData = sql_fetch_array(sql_query("select * from orvosok where id=?", [$doctorId]));
        $companies = sql_query("select * from cegek where true order by megnev")->fetchAll(PDO::FETCH_ASSOC);

        $hetBackgrounds = ["", "#ffffbb", "#bbffff"];

        $beosztasok = sql_query("SELECT b.* FROM orvos_beosztas_new b WHERE b.orvosid=? {$this->beosztasService->beosztasCompanyFilter} order by groupid, nap=0, nap, beonap, tol", [$doctorId])->fetchAll(PDO::FETCH_ASSOC);

        if (!$this->adminUser->doctorsCalendarAccess()) {
            //$html.= "<tr><td colspan='2' style=''><div class='nojog'>A beosztás módosításához nincs jogosultsága</div></td></tr>";
        }

        $html.= "<div style='font-size:12px;width: 100%;'>";

        $lastSectionId = 0;
        foreach ($beosztasok as $key => $beo) {
            $beoId = $beo["id"];
            if ($lastSectionId != $beo["groupid"]) {
                if ($lastSectionId != 0) {
                    $html.= $this->addBeoButtonNew($doctorId, $lastSectionId);
                }

                $lastSectionId = $beo["groupid"];
                $selectedCompanyIds = explode("|", $beo["beocegek"]);
                $buttons = "";
                $companyText = [];
                $selectedNum = 0;
                foreach ($companies as $company) {
                    $class = "servicenotselected";
                    $style = "";
                    if (!empty($this->beosztasService->userCompanyPermission) && !in_array($company["id"], $this->beosztasService->userCompanyPermission)) {
                        $style = "display:none;";
                    }

                    if (in_array($company["id"], $selectedCompanyIds)) {
                        $class = "serviceselected";
                        if ($style == "") {
                            $selectedNum++;
                            $companyText[] = $company["megnev"];
                        }
                    }

                    $cegnev = $company["megnev"];
                    if ($company["id"] == Booking_Constants::DEFAULT_COMPANY_ID) {
                        $cegnev = "<strong><i class='fas fa-home'></i> {$company["megnev"]}</strong>";
                    }

                    $buttons .= "<a data-cegid='{$company["id"]}' title='' class='{$class}' style='{$style}' href='#' onclick='toggleBeoCompany(this);return false;'>{$cegnev}</a> ";
                }

                $text = implode(", ", $companyText);
                $cutText = mb_substr($text,  0, 120);
                if ($text != $cutText) {
                    $cutText.= "...";
                }

                if ($selectedNum == 0) {
                    $selectedNum = "Összes";
                }

                $html.= "<div>";
                $html.= "<div class='tdsepdivgreen' style='margin-top: 10px;'><i class='fas fa-calendar-alt'></i> Rendelési idők (<a style='color:#ff0;' href='#' onclick='toggleBeoCegSelector({$beo["groupid"]});return false;'>{$selectedNum} cég</a>) {$cutText}</div>";
                $html.= "</div>";

                $hidden = "display:none";
                if (isset($_SESSION["toggleBeoCegSelector"][$beo["groupid"]])) {
                    $hidden = "";
                }

                if ($selectedNum == 0) {
                    $buttons = "<div style='margin-bottom: 5px;font-weight: bold;'>Ha nem jelölsz ki egy céget se, az azt jelenti hogy az összes cégre érvényes a beosztás!</div>{$buttons}";
                }
                $html .= "<div id='selectcompanydiv{$beo["groupid"]}' style='{$hidden}'><div id='selectedcompanies{$beo["groupid"]}' data-doctorid='{$doctorId}' data-beogroupid='{$beo["groupid"]}' data-cegids='{$beo["beocegek"]}'>{$buttons}</div></div>";
            }

            $preStyle = "padding:2px 0px;";
            if ($beo["nap"] == 10 && strtotime($beo["beonap"]) < strtotime("now")) {
                $preStyle.= "opacity:.5;";
            }
            $html.= "<div style='{$preStyle}'>";
            $html.= "<form method='post' name='beorow{$beoId}' id='beorow{$beoId}'>";

            $html.= "<input type='hidden' name='beosztasid' value='{$beoId}'/>";

            $html.= "<input onchange='beoSave({$doctorId},{$beoId});' title='aktív?' type='checkbox' name='aktiv' value='1' " . ($beo["aktiv"] == 1 ? " checked" : "") . "/> ";

            $html.= "<select name='weekday' onchange=\"beoSave({$doctorId},{$beoId});if (this.value!=10) { $(this).parent().find('#hetek').show(); $(this).parent().find('#beonap').hide(); } else { $(this).parent().find('#hetek').hide(); $(this).parent().find('#beonap').show(); }\">";
            $html.= "<option value='0'>Válassz napot!</option>";
            for ($n = 1; $n <= 7; $n++) {
                $html.= "<option value='{$n}'" . ($beo["nap"] == $n ? " selected" : "") . ">{$GLOBALS["hetnap"][$n]}</option>";
            }
            $html.= "<option value='10'" . ($beo["nap"] == 10 ? " selected" : "") . ">Egy dátum</option>";
            $html.= "</select> ";

            $html.= "<select onchange='beoSave({$doctorId},{$beoId});' id='hetek' name='hetek' style='width:110px;background:{$hetBackgrounds[$beo["hetek"]]};" . ($beo["nap"] == 10 ? "display:none;" : "") . "'>";
            $html.= "<option value='0'" . ($beo["hetek"] == 0 ? " selected" : "") . ">Minden hét</option>";
            $html.= "<option value='1'" . ($beo["hetek"] == 1 ? " selected" : "") . ">Páratlan hetek</option>";
            $html.= "<option value='2'" . ($beo["hetek"] == 2 ? " selected" : "") . ">Páros hetek</option>";
            $html.= "</select> ";

            $html.= "<input onchange='beoSave({$doctorId},{$beoId});' id='beonap' name='beonap' type='text' value='{$beo["beonap"]}' style='width:102px;" . ($beo["nap"] == 10 ? "" : "display:none;") . "' placeholder='éééé-hh-nn' /> ";

            if (!isset($_SESSION["orvos_helyszinid"]) && $beo["helyszinid"] != 0) {
                $_SESSION["orvos_helyszinid"] = $beo["helyszinid"];
            }
            if (!isset($_SESSION["orvos_cegid"]) && $beo["cegid"] != 0) {
                $_SESSION["orvos_cegid"] = $beo["cegid"];
            }

            $html.= "<select onchange='beoSave({$doctorId},{$beoId});' id='helyszinid' name='helyszinid' style='width:200px;'>";

            if ($beo["helyszinid"] == 0 && isset($_SESSION["orvos_helyszinid"])) {
                $beo["helyszinid"] = $_SESSION["orvos_helyszinid"];
            }
            if ($beo["cegid"] == 0 && isset($_SESSION["orvos_cegid"])) {
                $beo["cegid"] = $_SESSION["orvos_cegid"];
            }

            $resh = sql_query("select * from helyszinek where true order by cim");
            $html.= "<option value='0'>Válassz helyszínt!</option>";
            while ($rowh = sql_fetch_array($resh)) {
                $html.= "<option value='{$rowh["id"]}'" . ($beo["helyszinid"] == $rowh["id"] ? " selected" : "") . ">{$rowh["cim"]}</option>";
            }
            $html.= "</select> ";

            $html.= "<select onchange='beoSave({$doctorId},{$beoId});' name='tol'>";
            $html.= "<option value='0'>Kezdés?</option>";
            for ($n = 0; $n <= 1125; $n += 5) {
                $t = date("H:i", mktime(5, 0 + $n, 0, 1, 1, 2015));
                $html.= "<option value='{$t}'" . ($beo["tol"] == $t ? " selected" : "") . ">{$t}</option>";
            }
            $html.= "</select> ";

            $html.= "<select onchange='beoSave({$doctorId},{$beoId});' name='ig'>";
            $html.= "<option value='0'>Vége?</option>";
            for ($n = 0; $n <= 1065; $n += 5) {
                $t = date("H:i", mktime(6, 0 + $n, 0, 1, 1, 2015));
                $html.= "<option value='{$t}'" . ($beo["ig"] == $t ? " selected" : "") . ">{$t}</option>";
            }
            $html.= "</select> ";

            $html.= "<input onchange='beoSave({$doctorId},{$beoId});' placeholder='pótidőpontok' title='Pótidőpontok eddig adhatók. Hagyd üresen ha nem akarsz pótidőpontokat.'  type='text' name='potig' style='width:37px;' value='{$beo["potig"]}' /> ";

            $html.= "<input type='hidden' name='tipusidk' id='tipusidk' value='{$beo["tipusok"]}' />";

            $num = 0;
            unset($idk);
            $idk[] = 0;
            $titl = "nincs tipus hozzárendelve";

            $ik = explode("|", $beo["tipusok"]);
            for ($i = 0; $i < count($ik); $i++) {
                if ($ik[$i] != "") {
                    $num++;
                    $idk[] = $ik[$i];
                }
            }

            if (count($idk) > 1) {
                $rowtt = sql_fetch_array(sql_query("SELECT GROUP_CONCAT(megnev SEPARATOR ', ') AS megnevek FROM szurestipusok WHERE id IN (" . implode(",", $idk) . ")"));
                $titl = $rowtt["megnevek"];
            }

            $html.= "<select onchange='beoSave({$doctorId},{$beoId});' title='egy kezelés időtartama' id='intervalchooser{$beo["id"]}' onclick='changeInterval({$beo["id"]}, this.value);'>";
            foreach ($this->adminUtils->settings->validIntervals as $interval) {
                $html.= "<option value='{$interval}'" . ($beo["binterval"] == $interval ? " selected" : "") . ">{$interval} perc</option>";
            }
            $html.= "</select> ";

            $html.= "<span id='tipusstatus{$beo["id"]}'><a href='#' class='tlink' title='{$titl}' onclick='showTipusValaszto({$beo["id"]});return false;'>{$num} tipus</a></span> ";

            $html.= "<span title='Csak sorban foglalható időpontok'><input onchange='beoSave({$doctorId},{$beoId});' onclick='cssClick(1);' type='checkbox' value='1' id='csaksorban' name='csaksorban'" . ($beo["csaksorban"] == 1 ? " checked" : "") . ">&darr;</span> ";
            $html.= "<span title='Csak fordított sorrendben foglalható időpontok'><input onchange='beoSave({$doctorId},{$beoId});' onclick='cssClick(2);' type='checkbox' value='2' id='csakvsorban' name='csakvsorban'" . ($beo["csaksorban"] == 2 ? " checked" : "") . ">&uarr;</span> ";

            $html.= "<span title='Nincs időpontfoglalás'><input onchange='beoSave({$doctorId},{$beoId});' value='1' type='checkbox' id='noreservation' name='noreservation'" . ($beo["noreservation"] == 1 ? " checked" : "") . ">Nincs időpontfoglalás&nbsp;</span> ";

            $html.= "<a href='#' title='Sor törlése' onclick='delBeoRow({$doctorId},{$beo["id"]});return false;'><i class='fas fa-trash-alt'></i></a> ";
            $html.= "<a href='#' title='Extra adatok' onclick='$(\"#extradata{$beo["id"]}\").toggle();return false;'><i class='fas fa-bars'></i></a>";

            if ($beo["fobid"] != 0) {
                $html.= " <span style='border:1px solid #080;color:#080;cursor:pointer;' title='id: {$beo["fobid"]}'>FO</span>";
            }


            $html.= "<div id='extradata{$beo["id"]}' style='padding:2px 0px 2px 25px;".($this->isExtraData($beo)?"":"display:none;")."'>";
            $html.= "Érvényesség: <input onchange='beoSave({$doctorId},{$beoId});' id='validfrom' name='validfrom' type='text' value='{$beo["validfrom"]}' style='width:80px;' placeholder='éééé-hh-nn' /> - <input onchange='beoSave({$doctorId},{$beoId});' id='validto' name='validto' type='text' value='{$beo["validto"]}' style='width:80px;' placeholder='éééé-hh-nn' /> ";
            $html.= "Megjegyzés: <input onchange='beoSave({$doctorId},{$beoId});' id='bmegj' name='bmegj' type='text' value='{$beo["bmegj"]}' style='width:400px;' placeholder='megjegyzés a rendelési időhöz' /> ";
            $html.= "<input onchange='beoSave({$doctorId},{$beoId});' value='1' type='checkbox' id='nopack' name='nopack'" . ($beo["nopack"] == 1 ? " checked" : "") . ">Ne kerüljön csomagba ";
            $html.= "</div>";

            $html.= "<div id='tipusvalaszto{$beo["id"]}'></div>";
            $html.= "</form>";
            $html.= "</div>";

            if ($key == count($beosztasok)-1) {
                $html .= $this->addBeoButtonNew($doctorId, $beo["groupid"]);
            }
        }


        $html.= "<div style='border-top: 1px solid #ccc;border-bottom: 1px solid #ccc;margin:10px 0px 10px 0px;padding:10px 0px 10px 0px;'>";
        if (count($beosztasok) == 0) {
            $html.= "<div style='margin:5px 0px 10px 0px;'>Ennek az orvosnak nincs rendelési ideje!</div>";
        }
        $html.= "<a class='abutton' href='#' onclick='addBeoBlock({$doctorId});return false;'><i class='fas fa-calendar-alt'></i> rendelési idő csoport hozzáadása</a>";
        $html.= "</div>";

        $html.= "</div>";

        return $html;
    }

    private function isExtraData($beo):bool {
        return ($beo["validfrom"] != "0000-00-00" || $beo["validto"] != "0000-00-00" || !empty($beo["bmegj"]));
    }

    private function addBeoButton($doctorId, $groupId) {
        $html = "";
        $html .= "<tr><td colspan='2'>";
        $html .= "<a class='abutton' href='#' onclick='addBeoRow({$doctorId}, {$groupId});return false;'><i class='fas fa-plus-square'></i> Rendelési idő hozzáadása</a> ";
        $html .= "<a class='abutton' href='#' onclick='addBeoCopy({$doctorId}, {$groupId});return false;'><i class='fas fa-copy'></i> duplikálás</a>";
        $html .= "</td></tr>";
        return $html;
    }

    private function addBeoButtonNew($doctorId, $groupId) {
        $html = "";
        $html .= "<div style='padding-top: 2px;'>";
        $html .= "<a class='abutton' href='#' onclick='addBeoRow({$doctorId}, {$groupId});return false;'><i class='fas fa-plus-square'></i> Rendelési idő hozzáadása</a> ";
        $html .= "<a class='abutton' href='#' onclick='addBeoCopy({$doctorId}, {$groupId});return false;'><i class='fas fa-copy'></i> duplikálás</a>";
        $html .= "</div>";
        return $html;
    }

}