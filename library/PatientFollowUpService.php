<?php

class PatientFollowUpService
{
    function __construct()
    {
    }

    public function showUI(){
        $html = "";

        $html.= $this->uiFrame();

        echo $html;
    }

    public function uiFrame(){
        $html = "";

        $html.="<ul class=\"nav nav-tabs\" id=\"patient-follow-up-tab-container\" role=\"tablist\">";
        $html.="    <li class=\"nav-item\" role=\"presentation\">";
        $html.="        <button class=\"nav-link active\" id=\"data-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#data-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"data-tab-pane\" aria-selected=\"true\"><i class=\"fa-solid fa-user\"></i>&nbsp;Páciensek</button>";
        $html.="    </li>";
        /*$html.="    <li class=\"nav-item\" role=\"presentation\">";
        $html.="        <button class=\"nav-link\" id=\"notification-editor-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#notification-editor-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"notification-editor-tab-pane\" aria-selected=\"false\">Üzenet sablonok</button>";
        $html.="    </li>";
        $html.="    <li class=\"nav-item\" role=\"presentation\">";
        $html.="        <button class=\"nav-link\" id=\"referals-management-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#referals-management-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"referals-management-tab-pane\" aria-selected=\"false\">Beutalók generálása</button>";
        $html.="    </li>";
        $html.="    <li class=\"nav-item\" role=\"presentation\">";
        $html.="        <button class=\"nav-link\" id=\"referal-arrays-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#referal-arrays-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"referal-arrays-tab-pane\" aria-selected=\"false\">Beutaló tömbök</button>";
        $html.="    </li>";
        $html.="    <li class=\"nav-item\" role=\"presentation\">";
        $html.="        <button class=\"nav-link\" id=\"sending-notifications-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#sending-notifications-tab-pane\" type=\"button\" role=\"tab\" aria-controls=\"sending-notifications-tab-pane\" aria-selected=\"false\">Értesitések kezelése</button>";
        $html.="    </li>";*/
        $html.="</ul>";

        $html.="<div class=\"tab-content\" id=\"\">";

        $html.="    <div class=\"tab-pane fade show active\" id=\"data-tab-pane\" role=\"tabpanel\" aria-labelledby=\"data-tab\" tabindex=\"0\">";
        $html.=         $this->showPatientList();
        $html.="    </div>";

        $html.="    <div class=\"tab-pane fade\" id=\"notification-editor-tab-pane\" role=\"tabpanel\" aria-labelledby=\"statistics-tab\" tabindex=\"0\">";
        $html.="    </div>";

        $html.="    <div class=\"tab-pane fade\" id=\"referals-management-tab-pane\" role=\"tabpanel\" aria-labelledby=\"referals-management-tab\" tabindex=\"0\">";
        $html.="    </div>";

        $html.="    <div class=\"tab-pane fade\" id=\"referal-arrays-tab-pane\" role=\"tabpanel\" aria-labelledby=\"referal-arrays-tab\" tabindex=\"0\">";
        $html.="    </div>";

        $html.="    <div class=\"tab-pane fade\" id=\"sending-notifications-tab-pane\" role=\"tabpanel\" aria-labelledby=\"sending-notifications-tab\" tabindex=\"0\">";
        $html.="    </div>";
        $html.="</div>";

        return $html;
    }

    public function showPatientList(){
        $html = $list = "";
        $unit = 0;

        $query = "SELECT nev,szuldatum,paciensid,szakrendeles,datum
                    FROM (
                        SELECT dv.*,
                            ROW_NUMBER() OVER (
                                PARTITION BY paciensid
                                ORDER BY datum DESC, id DESC
                            ) AS rn
                        FROM dokirex_vizsgalatok dv
                        WHERE INSTR(telephely, 'suzuki')
                    ) X
                    WHERE rn = 1
                    ORDER BY nev ASC LIMIT 50;";

        $data = sql_query($query)->fetchAll(PDO::FETCH_ASSOC);

        foreach($data as $row){
            $unit++;
            $onClick="onClick='openPatientFollowUp(\"{$row["paciensid"]}\")'";
            $list .= "<tbody class=\"align-middle\" style=\"border-top:none\">";
            $list .= "<tr data-toggle=\"collapse\" onClick=\"$('#followuplist-{$unit}').fadeToggle(500)\">";
            $list .= "    <td class=\"text-center\">{$unit}.</td>";
            $list .= "    <td>{$row["nev"]}</td>";
            $list .= "    <td>{$row["paciensid"]}</td>";
            $list .= "    <td>{$row["szuldatum"]}</td>";
            $list .= "    <td>{$row["szakrendeles"]} - {$row["datum"]}</td>";
            $list .= "    <td>Teszt állapot</td>";
            $list .= "    <td><i title=\"Megnyitás\" {$onClick} style=\"font-size:18px;cursor:pointer\" class=\"fa-solid fa-folder-open\"></i></td>";
            $list .= "</tr>";
            $list .= "</tbody>";
            /*$list .= "<tbody class=\"align-middle\" id=\"followuplist-{$unit}\" style=\"display:none;font-size:0.9rem\">";
            $list .= "<tr>";
            $list .= "    <td>valami</td>";
            $list .= "</tr>";
            $list .= "</tbody>";*/
        }

        $html .= "<div class=\"container\" style=\"max-width:1400px;margin-left:0;margin-right:auto\">";
        $html .= "  <div class=\"row\">";
        $html .= "      <div class=\"col\">";
        $html .= "          <table class=\"table table-hover caption-top table-condensed\" >";
        $html .= "              <thead class=\"text-center\">";
        $html .= "              <tr>";
        $html .= "                  <th scope= \"col\">#</th>";
        $html .= "                  <th scope=\"col\">Dolgozó neve</th>";
        $html .= "                  <th scope=\"col\">TAJ sz.</th>";
        $html .= "                  <th scope=\"col\" style=\"white-space:nowrap\">Sz.idö</th>";
        $html .= "                  <th scope=\"col\">Utolsó vizsg.</th>";
        $html .= "                  <th scope=\"col\">Státusz</th>";
        $html .= "                  <th scope=\"col\"><i class=\"fa-solid fa-gear\"></i></th>";
        $html .= "              </tr>";
        $html .= "              </thead>";
        $html .=                $list;
        $html .= "          </table>";
        $html .= "      </div>";
        $html .= "  </div>";
        $html .= "</div>";

        return $html;
    }


}