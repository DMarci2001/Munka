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
    }

    public function show($doctorId):string {
        $html = "";

        $doctorData = sql_fetch_array(sql_query("select * from orvosok where id=?", [$doctorId]));
        $companies = sql_query("select * from cegek where true order by megnev")->fetchAll(PDO::FETCH_ASSOC);

        $sor = 1;
        $hetBackgrounds = ["", "#ffffbb", "#bbffff"];

        $beosztasok = sql_query("SELECT b.* FROM orvos_beosztas_new b WHERE b.orvosid=? {$this->beosztasService->beosztasCompanyFilter} order by groupid, nap=0, nap, beonap, tol", [$doctorId])->fetchAll(PDO::FETCH_ASSOC);

        if (!$this->adminUser->doctorsCalendarAccess()) {
            $html.= "<tr><td colspan='2' style=''><div class='nojog'>A beosztás módosításához nincs jogosultsága</div></td></tr>";
        }

        $html.= "<table style='font-size:12px;width: 100%;'>";

        $lastSectionId = 0;
        foreach ($beosztasok as $beo) {
            if ($lastSectionId != $beo["groupid"]) {
                if ($lastSectionId != 0) {
                    $html.= $this->addBeoButton($doctorId, $lastSectionId);
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

                $html .= "<tr><td colspan='2'>";
                $text = implode(", ", $companyText);
                $cutText = mb_substr($text,  0, 120);
                if ($text != $cutText) {
                    $cutText.= "...";
                }

                if ($selectedNum == 0) {
                    $selectedNum = "Összes";
                }

                $html .= "<div class='tdsepdivgreen' style='margin-top: 10px;'><i class='fas fa-calendar-alt'></i> Rendelési idők (<a style='color:#ff0;' href='#' onclick='toggleBeoCegSelector({$beo["groupid"]});return false;'>{$selectedNum} cég</a>) {$cutText}</div>";
                $html .= "</td></tr>";

                $hidden = "display:none";
                if (isset($_SESSION["toggleBeoCegSelector"][$beo["groupid"]])) {
                    $hidden = "";
                }

                if ($selectedNum == 0) {
                    $buttons = "<div style='margin-bottom: 5px;font-weight: bold;'>Ha nem jelölsz ki egy céget se, az azt jelenti hogy az összes cégre érvényes a beosztás!</div>{$buttons}";
                }
                $html .= "<tr id='selectcompanydiv{$beo["groupid"]}' style='{$hidden}'><td colspan='2'><div id='selectedcompanies{$beo["groupid"]}' data-doctorid='{$doctorId}' data-beogroupid='{$beo["groupid"]}' data-cegids='{$beo["beocegek"]}'>{$buttons}</div></td></tr>";
            }


            $preStyle = "";
            if ($beo["nap"] == 10 && strtotime($beo["beonap"]) < strtotime("now")) {
                $preStyle = "opacity:.5;";
            }
            $html.= "<tr><td colspan='2' style='{$preStyle}'>";

            $html.= "<input type='hidden' name='beosztasid{$sor}' value='{$beo["id"]}'/>";

            $html.= "<input title='aktív?' type='checkbox' name='aktiv{$sor}' value='1' " . ($beo["aktiv"] == 1 ? " checked" : "") . "/> ";

            $html.= "<select name='weekday{$sor}' onchange=\"if (this.value!=10) { $('#hetek{$sor}').show(); $('#beonap{$sor}').hide(); } else { $('#hetek{$sor}').hide(); $('#beonap{$sor}').show(); }\">";
            $html.= "<option value='0'>Válassz napot!</option>";
            for ($n = 1; $n <= 7; $n++) {
                $html.= "<option value='{$n}'" . ($beo["nap"] == $n ? " selected" : "") . ">{$GLOBALS["hetnap"][$n]}</option>";
            }
            $html.= "<option value='10'" . ($beo["nap"] == 10 ? " selected" : "") . ">Egy dátum</option>";
            $html.= "</select> ";

            $html.= "<select id='hetek{$sor}' name='hetek{$sor}' style='width:110px;background:{$hetBackgrounds[$beo["hetek"]]};" . ($beo["nap"] == 10 ? "display:none;" : "") . "'>";
            $html.= "<option value='0'" . ($beo["hetek"] == 0 ? " selected" : "") . ">Minden hét</option>";
            $html.= "<option value='1'" . ($beo["hetek"] == 1 ? " selected" : "") . ">Páratlan hetek</option>";
            $html.= "<option value='2'" . ($beo["hetek"] == 2 ? " selected" : "") . ">Páros hetek</option>";
            $html.= "</select> ";

            $html.= "<input id='beonap{$sor}' name='beonap{$sor}' type='text' value='{$beo["beonap"]}' style='width:102px;" . ($beo["nap"] == 10 ? "" : "display:none;") . "' placeholder='éééé-hh-nn' /> ";

            if (!isset($_SESSION["orvos_helyszinid"]) && $beo["helyszinid"] != 0) {
                $_SESSION["orvos_helyszinid"] = $beo["helyszinid"];
            }
            if (!isset($_SESSION["orvos_cegid"]) && $beo["cegid"] != 0) {
                $_SESSION["orvos_cegid"] = $beo["cegid"];
            }

            $html.= "<select id='helyszinid{$sor}' name='helyszinid{$sor}' style='width:200px;'>";

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

            $html.= "<select name='tol{$sor}'>";
            $html.= "<option value='0'>Kezdés?</option>";
            for ($n = 0; $n <= 1125; $n += 5) {
                $t = date("H:i", mktime(5, 0 + $n, 0, 1, 1, 2015));
                $html.= "<option value='{$t}'" . ($beo["tol"] == $t ? " selected" : "") . ">{$t}</option>";
            }
            $html.= "</select> ";

            $html.= "<select name='ig{$sor}'>";
            $html.= "<option value='0'>Vége?</option>";
            for ($n = 0; $n <= 1065; $n += 5) {
                $t = date("H:i", mktime(6, 0 + $n, 0, 1, 1, 2015));
                $html.= "<option value='{$t}'" . ($beo["ig"] == $t ? " selected" : "") . ">{$t}</option>";
            }
            $html.= "</select> ";

            $html.= "<input placeholder='pótidőpontok' title='Pótidőpontok eddig adhatók. Hagyd üresen ha nem akarsz pótidőpontokat.'  type='text' name='potig{$sor}' style='width:37px;' value='{$beo["potig"]}' /> ";

            $html.= "<input type='hidden' name='tipusidk{$sor}' id='tipusidk{$sor}' value='{$beo["tipusok"]}' />";

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

            $html.= "<select title='egy kezelés időtartama' id='intervalchooser{$beo["id"]}' onclick='changeInterval({$beo["id"]}, this.value);'>";
            foreach ($this->adminUtils->settings->validIntervals as $interval) {
                $html.= "<option value='{$interval}'" . ($beo["binterval"] == $interval ? " selected" : "") . ">{$interval} perc</option>";
            }
            $html.= "</select> ";

            $html.= "<span id='tipusstatus{$beo["id"]}'><a href='#' class='tlink' title='{$titl}' onclick='showTipusValaszto({$beo["id"]});return false;'>{$num} tipus</a></span> ";

            $html.= "<span title='Csak sorban foglalható időpontok'><input onclick='cssClick(1,{$sor});' type='checkbox' value='1' id='csaksorban{$sor}' name='csaksorban{$sor}'" . ($beo["csaksorban"] == 1 ? " checked" : "") . ">&darr;</span> ";
            $html.= "<span title='Csak fordított sorrendben foglalható időpontok'><input onclick='cssClick(2,{$sor});' type='checkbox' value='2' id='csakvsorban{$sor}' name='csakvsorban{$sor}'" . ($beo["csaksorban"] == 2 ? " checked" : "") . ">&uarr;</span> ";

            $html.= "<span title='Nincs időpontfoglalás'><input value='1' type='checkbox' id='noreservation{$sor}' name='noreservation{$sor}'" . ($beo["noreservation"] == 1 ? " checked" : "") . ">Nincs időpontfoglalás&nbsp;</span> ";

            $html.= "<a href='#' title='Sor törlése' onclick='delBeoRow({$doctorId},{$beo["id"]});return false;'><i class='fas fa-trash-alt'></i></a> ";
            $html.= "<a href='#' title='Extra adatok' onclick='$(\"#extradata{$beo["id"]}\").toggle();return false;'><i class='fas fa-bars'></i></a>";

            if ($beo["fobid"] != 0) {
                $html.= " <span style='border:1px solid #080;color:#080;cursor:pointer;' title='id: {$beo["fobid"]}'>FO</span>";
            }

            $html .= "<div id='extradata{$beo["id"]}' style='padding:2px 0px 2px 25px;".($this->isExtraData($beo)?"":"display:none;")."'>";
            $html .= "Érvényesség: <input id='validfrom{$sor}' name='validfrom{$sor}' type='text' value='{$beo["validfrom"]}' style='width:80px;' placeholder='éééé-hh-nn' /> - <input id='validto{$sor}' name='validto{$sor}' type='text' value='{$beo["validto"]}' style='width:80px;' placeholder='éééé-hh-nn' /> ";
            $html .= "Megjegyzés: <input id='bmegj{$sor}' name='bmegj{$sor}' type='text' value='{$beo["bmegj"]}' style='width:400px;' placeholder='megjegyzés a rendelési időhöz' /> ";
            $html .= "</div>";

            $html.= "<div id='tipusvalaszto{$beo["id"]}'></div>";

            $html.= "</td></tr>";


            if ($sor -1 == count($beosztasok) - 1) {
                $html .= $this->addBeoButton($doctorId, $beo["groupid"]);
            }

            $sor++;
        }

        $html.= "<tr><td colspan='2'>";


        //$html.= "<input type='button' onclick='addBeoRow({$doctorId}, {$beo["groupid"]});' value='+ Rendelési idő hozzáadása'>";

        //if ($syncData = sql_fetch_array(sql_query("select * from remoteids r where r.tipus='orvos' and remoteid=?", [$doctorData["pecsetszam"]]))) {
        //    $syncParameters = json_decode($syncData["megnev"], JSON_OBJECT_AS_ARRAY);
        //    if (isset($syncParameters["enablebeocopy"])) {
        //        $html.= " <input type='submit' name='syncbeosztas' value='Beosztás sync (" . $syncData["provider"] . ")'>";
        //    }
        //}


        $html.= "</td></tr>";

        $html .= "<tr><td colspan='2' style='border-top: 1px solid #ccc;margin-top:10px;padding-top:10px;'>";
        if (count($beosztasok) == 0) {
            $html.= "<div style='margin:5px 0px 10px 0px;'>Ennek az orvosnak nincs rendelési ideje!</div>";
        }
        $html .= "<a class='abutton' href='#' onclick='addBeoBlock({$doctorId});return false;'><i class='fas fa-calendar-alt'></i> rendelési idő csoport hozzáadása</a>";
        $html .= "</td></tr>";

        $html.= "</table>";

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

}