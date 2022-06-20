<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
class AdminBpSettingsPage extends AdminCorePage
{


    public function __construct()
    {
        parent::__construct();

        if(isset($_POST["uploadBpNTIDBoard"])){

            //Fájl beolvasása
            echo $this->processExcelList();
        }

    }

    public function showPage()
    {
        $html = "";

        if (!$this->adminUser->beallitasBPsegedtablaAccess()) {
            echo $this->noPermissionMessage();
            return;
        }
        echo $this->showErrors();
        echo $this->showSuccess();

        $html .= "<div style=\"margin-top:20px;margin-bottom:10px;\">Állományi segéd tábla frissítése</div>";
        $html .= "<div><form method=\"POST\" enctype=\"multipart/form-data\" name=\"bp-seged-table\" id = \"bp-seged-table\">";
        $html .= "<input type=\"file\" name=\"file\" id=\"file\"><br>";
        $html .= "<div style = 'margin-top:10px'><input type = 'submit' onclick='return confirm(\"Biztosan felül akarod írni a meglévő segéd táblát?\")' name='uploadBpNTIDBoard' value='Feltöltés'></div>";
        $html .= "</form></div>";

        echo $html;
    }

    public function processExcelList(){

        if(empty($_FILES["file"]["tmp_name"])) return;

        $path = __DIR__ . "../../public/admin/templates/";
        $col_names = array("ntid","type","worklocation");

        $spreadsheet = new Spreadsheet();
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES["file"]["tmp_name"]);
        $worksheet = $spreadsheet->getActiveSheet();
        $array = $worksheet->toArray();
        //unlink($excelFile);

        //Oszlopszám ellenőrzés
       if(count($array[0])!=3){
        $this->errors[] =  "Hibás oszlop szám!(3 oszlop kell, hogy szerepeljen a az excelben, ntid,type,woklocation)";
       }
       //Oszlopnév ellenőrzés
       foreach($array[0] as $col){
           if(!in_array($col,$col_names)){
            $this->errors[] =  "Az oszlop nevek nem stimmelnek!(3 oszlop kell, hogy szerepeljen a az excelben, ntid,type,woklocation)";
            break;
           }
       }

       //Ha hibára futott hagyja abba a kódot
       if(!empty($this->errors)) return;

       //Törlöm az első sort, mert arra már nincs szükség
       unset($array[0]);
       $array = array_values($array);

       //Törlöm a régi tábla tartalmát
       sql_query("DELETE FROM bp_beutalo_seged_tabla");

       //Beillesztem az új adatsorokat
        foreach ($array as $object) {
            sql_query("INSERT INTO bp_beutalo_seged_tabla SET ntid=?,type=?,worklocation=?",$object);
        }
        $this->success[] = "Segéd tábla módosítása sikeres!";
        
        return true;

    }
}
