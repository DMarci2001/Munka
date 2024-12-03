<?php

class AdminPatientDataPage extends AdminCorePage {

    private $data;
    private $cols = [
        0=>["name"=>"Cég ID.","id"=>"cegid"],
        1=>["name"=>"Páciens ID.","id"=>"paciensid"],
        2=>["name"=>"Időpont","id"=>"datum"],
        3=>["name"=>"Ellátási idő","id"=>"rinterval"],
        4=>["name"=>"Helyszín ID.","id"=>"helyszinid"],
        5=>["name"=>"Vizsg. ID.","id"=>"szurestipusid"],
        6=>["name"=>"Teljesnév","id"=>"nev"],
        7=>["name"=>"E-mail","id"=>"email"],
        8=>["name"=>"Telefon","id"=>"telefon"],
        9=>["name"=>"Szül. dátum","id"=>"szuldatum"],
        10=>["name"=>"Szül. hely","id"=>"szulhely"],
        11=>["name"=>"Anyjaneve","id"=>"anyjaneve"],
        12=>["name"=>"Neme","id"=>"neme"],
        13=>["name"=>"TAJ","id"=>"taj"],
        14=>["name"=>"Irsz.","id"=>"irsz"],
        15=>["name"=>"Település","id"=>"varos"],
        16=>["name"=>"Cím","id"=>"utca"],
        17=>["name"=>"Munkakör","id"=>"munkakor"],
        18=>["name"=>"Dx munkakör ID.","id"=>"dokirexmunkakorid"],
        19=>["name"=>"Dx Cég ID.","id"=>"dokirexcegid"],
    ];

    public function __construct()
    {
        parent::__construct();

        if(!empty($_SESSION["patient-excel-data"])){
            $this->data = $_SESSION["patient-excel-data"];
        }

        if(isset($_POST["uploadPatientDataFile"])){
            $excelService = New ExcelService();
            $data = $excelService->loadPatientDataExcel($_FILES["excel"]["tmp_name"]);

            if($multipleSheets = $excelService->checkSheets()){
                die(json_encode(array("multiplesheets"=>$multipleSheets)));
            }

            unset($data[0]); //Első sor törlése mert irreleváns :P
            $data = array_values($data); //Újra rendezés

            $_SESSION["patient-excel-data"] = $data;

            $viewer = $this->setExcelViewer($data);
            die(json_encode(array("html"=>$viewer)));
        }

        if(isset($_GET["remove"])){
            unset($_SESSION["patient-excel-data"]);
            unset($_SESSION["patient-excel-cols"]);
            header("location:index.php?page={$_GET["page"]}");
        } 

        if(isset($_POST["setPatientDataCol"])){
            $_SESSION["patient-excel-cols"][$_POST["index"]]=$_POST["col"];
            die();
        }
    }

    public function showPage(){
       echo "<div id='uploaded-excel-viewer'>".(!empty($this->data)?$this->setExcelViewer($this->data):"")."</div>";
    }

    private function setExcelViewer($data):string{
        $html = "";
        $colsNumb = count($data[0]);
        $html.= "<table class='table table-hover'>";
        $html.="<thead>";
        $html.="    <tr>";
        $html.="        <th scope='col'>#</th>";
        for($i=0;$i<=$colsNumb;$i++){
            //$html.= "   <th>{$i}.</th>";
            $html.= "   <th>".$this->setColumnName($i)."</th>";
        }
        $html.="    </tr>";
        $html.="</thead>";
        $html.="<tbody>";
        foreach($data as $index=>$row){
            $html.="    <tr>";
            $html.="        <th scope='row'>{$index}.</th>";
            for($i=0;$i<=$colsNumb;$i++){
                $html.= "   <td>".(isset($row[$i])?$row[$i]:"")."</td>";
            }
            $html.="    </tr>"; 
        }
        $html.="</tbody>";
        $html.= "</table>";

        return $html;
    }
    private function setColumnName($numb):string{
        $html = "";
        $html .= "<select class='form-select form-select' onChange='setPatientDataCol($(this).val(),{$numb})'>";
        foreach($this->cols as $index=>$col){
            if(isset($_SESSION["patient-excel-cols"][$numb]) && $_SESSION["patient-excel-cols"][$numb]==$col["id"]){
                $selected="selected='true'";
            }else{
                $selected="";
            }
            $html .= "<option {$selected} value='{$col["id"]}'>{$col["name"]}</option>";
        }
        $html .= "</select>";
        return $html;
    }
}