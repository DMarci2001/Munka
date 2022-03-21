<?php

class AdminWebPageDataPage extends AdminCorePage {

    private $params = [

    ];
    public function __construct()
    {
        parent::__construct();

        if (isset($_GET["addnew"])) {
            sql_query("insert into webpagedata set domain='aaaaa.hu'");
        }

        if (isset($_POST["webpagedatasave"])) {
            sql_query("update webpagedata set domain=?, aktiv=? where id=?", [$_POST["domain"], isset($_POST["aktiv"])?1:0, $_GET["szerk"]]);
            header("location:index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die();
        }

    }


    public function showPage() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        //echo "<div style='margin-bottom:20px;'>";
        //echo "<a href='index.php?page=settings'>Vissza</a>";
        //echo "</div>";

        if (isset($_GET["szerk"])) {
            $id = intval($_GET["szerk"]);
            $data = sql_fetch_array(sql_query("select * from webpagedata where id=?", [$id]));

            echo "<div style=''>";

            echo "<form name='iform' method='post' enctype='multipart/form-data'>";
            echo "<table style='font-size:12px;'>";

            echo "<tr><td width='100'>Domain:</td><td><input class='inputbox' style='width:400px;' type='text' name='domain' value='{$data["domain"]}'></td></tr>";
            echo "<tr><td colspan='2' valign='top'>";
            echo "<input type='checkbox' value='1' name='aktiv'" . ($data["aktiv"] == 1 ? " checked" : "") . "> Aktív&nbsp;&nbsp;";
            echo "</td></tr>";


            echo "</table>";

            echo "<br><input type='submit' name='webpagedatasave' value='Mentés'> ";
            echo "<input type='submit' name='scancel' value='Vissza'> ";
            echo "</form>";

            echo "</div>";
            return;
        }



        echo "<div style='display:table-row;font-weight: bold'>";
        echo "<div class='langtd'>Domainok</div>";
        echo "<div class='langtd'></div>";
        echo "<div class='langtd'></div>";
        echo "</div>";

        //$last='';

        echo $this->_domainList(0, 0);

    }

    private function _domainList($parent, $level) {
        $html = "";
        $resData = sql_query("select id, domain, aktiv from webpagedata d where parent=? order by d.domain", [$parent]);

        while ($rowData = sql_fetch_array($resData)) {
            $html.= "<div style='display:table-row;'>";
            $html.= "<div class='langtd' style=''>".str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level).($level==0?"":" - ")."<a href='index.php?page={$_GET["page"]}&szerk={$rowData["id"]}'>{$rowData["domain"]}</a></div>";
            $html.= "<div class='langtd' style=''><a target='_blank' href='http://{$rowData["domain"]}'>megnyitás</a></div>";
            $html.= "<div class='langtd' style=''>".($rowData["aktiv"]==1?"<span style='color:green;'>Aktív</span>":"Inaktív")."</div>";
            $html.= "<div class='langtd' style=''></div>";
            $html.= "</div>";
            $html.= $this->_domainList($rowData["id"], $level+1);
        }
        return $html;
    }

}

