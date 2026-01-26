<?php


class AdminKeltexmedProductsPage extends AdminCorePage
{
    public array $services;
    public KeltexMedWebSQL $keltexSql;

    public function __construct()
    {
        parent::__construct();

        $this->keltexSql = new KeltexMedWebSQL();


        $services = sql_query("select t.id, t.megnev from szurestipusok t 
                 left join dokumentumok d on d.assetid=? and d.dataid=t.id 
                 where d.id is not null group by t.id order by t.megnev", [DocAgent::ASSET_SERVICE_ILLUSTRATION_IMAGE])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($services as $service) {
            $this->services[$service["id"]] = $service;
        }

        if (isset($_REQUEST["generalsearch"])) {
            $products = sql_query("select * from keltexmedweb.products where instr(productname, ?) and producttype in ('service', 'spektrumservice') order by alias, productname", [$_REQUEST["term"]])->fetchAll(PDO::FETCH_ASSOC);
            echo $this->list($products);
            die;
        }

        if (isset($_POST["keltexproductmentes"])) {
            //error_reporting(E_ALL);
            //ini_set('display_errors', 1);

            $_POST["aktiv"] = $_POST["aktiv"] ?? 0;
            $_POST["reservable"] = $_POST["reservable"] ?? 0;
            $_POST["fehervariexists"] = $_POST["fehervariexists"] ?? 0;
            $_POST["bercsenyiexists"] = $_POST["bercsenyiexists"] ?? 0;

            //echo "<pre>".print_r($_POST, true)."</pre>\n";
            //echo "update keltexmedweb.products p set aktiv=?, reservable=?, fehervariexists=?, bercsenyiexists=?, productname=?, alias=?, detailalias=?, description=?, extendeddescription=?, price=?, discount=?, discountprice=?, sorrend=?, reservationTypeId=? where id=?";
            $this->keltexSql->sqlQuery("update keltexmedweb.products p set aktiv=?, reservable=?, fehervariexists=?, bercsenyiexists=?, productname=?, alias=?, detailalias=?, description=?, extendeddescription=?, price=?, discount=?, discountprice=?, sorrend=?, reservationTypeId=? where id=?",
                [$_POST["aktiv"], $_POST["reservable"], $_POST["fehervariexists"], $_POST["bercsenyiexists"], $_POST["productname"], $_POST["alias"], $_POST["detailalias"], $_POST["description"], $_POST["extendeddescription"], $_POST["price"], $_POST["discount"], $_POST["discountprice"], $_POST["sorrend"], $_POST["reservationTypeId"], $_GET["szerk"]]);

            header("location:index.php?page={$_GET["page"]}&szerk={$_GET["szerk"]}");
            die;
        }

        if (isset($_GET["addnewproduct"])) {
            $this->keltexSql->sqlQuery("insert into keltexmedweb.products set producttype='service', productname='_új termék'");
            header("location:index.php?page={$_GET["page"]}");
            die;
        }

        //$GLOBALS["javascript"][] = "dicom.js?v=" . date("YmdHi");
    }

    public function showPage() {
        if (!$this->adminUser->beallitasWebAKeltexmedAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        $GLOBALS["subtitle"] = "Termékek";

        if (isset($_GET["szerk"])) {
            $product = sql_query("select * from keltexmedweb.products where id=?", [$_GET["szerk"]])->fetch(PDO::FETCH_ASSOC);
            echo $this->editor($product);
        } else {
            echo "<div style='margin-bottom:20px;'>";
            echo "<input data-page='contents' data-resultdiv='tartalomlist' type='text' id='generalsearch' value='' placeholder='Keresés...'/>&nbsp;";
            echo "</div>";

            echo "<div id='tartalomlist'>";
            $products = $this->keltexSql->sqlQuery("select * from keltexmedweb.products where producttype in ('service', 'spektrumservice') order by alias, productname")->fetchAll(PDO::FETCH_ASSOC);
            echo $this->list($products);
            echo "</div>";
        }
    }


    private function editor($product):string {
        $html = "";

        $id = $product["id"];

        $html.= "<div style='background-color:#fff;padding:0px;'>";
        $html.= "<form name='iform' method='post' enctype='multipart/form-data'>";
        $html.= "<table style='font-size:12px;'>";

        $html.= "<tr><td width='100'>Cím:</td><td><input class='inputbox' style='width:400px;' type='text' name='productname' value='{$product["productname"]}'></td></tr>";
        //$html.= "<tr><td width='100'>Alias:</td><td><input class='inputbox' style='width:400px;' type='text' name='alias' value='{$product["alias"]}'></td></tr>";
        $html.= "<tr><td width='100'>Oldal:</td><td>";

        $aliases = $this->keltexSql->sqlQuery("select alias from keltexmedweb.products group by alias")->fetchAll(PDO::FETCH_ASSOC);

        $html.= "<select name='alias'>";
        foreach ($aliases as $key => $val) {
            $html.= "<option value='{$val["alias"]}'" . ($product["alias"] == $val["alias"] ? " selected" : "") . ">{$val["alias"]}</option>";
        }
        $html.= "</select>";
        $html.= "</td></tr>";

        $html.= "<tr><td width='100'>Ár:</td><td><input class='inputbox' style='width:100px;' type='text' name='price' value='{$product["price"]}'> Ft</td></tr>";
        $html.= "<tr><td width='100'>Akciós ár:</td><td><input class='inputbox' style='width:100px;' type='text' name='discountprice' value='{$product["discountprice"]}'> Ft</td></tr>";
        $html.= "<tr><td width='120'>Akció százalékban:</td><td><input class='inputbox' style='width:100px;' type='text' name='discount' value='{$product["discount"]}'> %</td></tr>";

        $html.= "<tr><td colspan='2' valign='top'>";
        $html.= "<input type='checkbox' value='1' name='aktiv'" . ($product["aktiv"] == 1 ? " checked" : "") . "> Aktív";
        $html.= "</td></tr>";

        $html.= "<tr><td colspan='2'><div class='tdsepdiv'>Foglaláshoz adatok</div></td></tr>";

        $html.= "<tr><td colspan='2' valign='top'>";
        $html.= "<input type='checkbox' value='1' name='reservable'" . ($product["reservable"] == 1 ? " checked" : "") . "> Foglalható";
        $html.= "</td></tr>";

        $html.= "<tr><td colspan='2' valign='top'>";
        $html.= "<input type='checkbox' value='1' name='fehervariexists'" . ($product["fehervariexists"] == 1 ? " checked" : "") . "> Fehérvári úton elérhető";
        $html.= "</td></tr>";

        $html.= "<tr><td colspan='2' valign='top'>";
        $html.= "<input type='checkbox' value='1' name='bercsenyiexists'" . ($product["bercsenyiexists"] == 1 ? " checked" : "") . "> Bercsényi úton elérhető";
        $html.= "</td></tr>";

        $services = sql_query("select id, megnev from szurestipusok order by megnev")->fetchAll(PDO::FETCH_ASSOC);

        $html.= "<tr><td width='100'>Szolgáltatás:</td><td>";
        $html.= "<select name='reservationTypeId'>";
        foreach ($services as $service) {
            $html.= "<option value='{$service["id"]}'" . ($product["reservationTypeId"] == $service["id"] ? " selected" : "") . ">{$service["megnev"]}</option>";
        }
        $html.= "</select>";
        $html.= "</td></tr>";

        $html.= "<tr><td colspan='2' valign='top' style='padding-top:10px;'><div id='desceditor2'>Rövid leírás ami az árlista is megjelenik (pl laborcsomag összetétele):<br/>";
        $html.= "<textarea name='description' style='width:900px;height:50px;'>{$product["description"]}</textarea>";
        $html.= "</div></td></tr>";


        $docAgent = new DocAgent();
        $html.= "<tr><td colspan='2'><div class='tdsepdiv'>Title image</div></td></tr>";
        $html.= "<tr><td colspan='2' valign='top'><div id='asseteditor'>".$docAgent->showAssetEditor(DocAgent::ASSET_KELTEX_PRODUCT_IMAGE, $id)."</div>";
        $html.= "</td></tr>";

        $html.= "<tr><td colspan='2'><div class='tdsepdiv'>Termék leírás</div></td></tr>";

        $html.= "<tr><td width='100'>Termék oldal alias:</td><td><input class='inputbox' style='width:400px;' type='text' name='detailalias' value='{$product["detailalias"]}'></td></tr>";

        $html.= "<tr><td colspan='2' valign='top'><div id='desceditor'>";
        $html.= "<textarea class='mce' name='extendeddescription' style='width:900px;height:600px;'>{$product["extendeddescription"]}</textarea>";
        $html.= "</div></td></tr>";

        $html.= "</table>";

        $html.= "<br><input type='submit' name='keltexproductmentes' value='Mentés'> ";
        $html.= "<input type='submit' name='scancel' value='Vissza'> ";
        $html.= "</form>";

        $html.= "</div>";

        return $html;
    }

    private function list($contents):string {
        $html = "";

        $html .= "<table cellpadding='0' cellspacing='0' border='0' xwidth='100%;'>";
        $html .= "<tr style='background:#eee;'>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'>&nbsp;Oldal alias</div></td>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:300px;'>Termék</div></td>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'>Ár</div></td>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'>Klinika</div></td>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'>Sorrend</div></td>";
        $html .= "<td nowrap valign='top' style='padding:5px 5px 5px 0px;width:40px;'>Aktív</td>";
        $html .= "</tr>";

        foreach ($contents as $row) {
            $tc = "tcella";
            if (!isset($first)) {
                $html .= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
                $first = 1;
            }

            if (empty(trim($row["productname"]))) {
                $row["productname"] = "nincs megnevezés";
            }


            $active = "Aktív";
            if ($row["aktiv"] != 1) {
                $active = "Inaktív";
            }

            $clinic = "";
            if ($row["fehervariexists"] == 1) {
                $clinic.= "fehérvári út ";
            }
            if ($row["bercsenyiexists"] == 1) {
                $clinic.= "bercsényi utca ";
            }

            $html .= "<tr>";

            $html .= "<td nowrap valign='top'><div class='{$tc}'>&nbsp;{$row["alias"]}</div></td>";
            $html .= "<td nowrap valign='top'><div class='{$tc}'><a href='index.php?page={$_GET["page"]}&szerk={$row["id"]}'>{$row["productname"]}</a></div></td>";
            $html .= "<td nowrap valign='top'><div class='{$tc}' style='text-align:right;white-space: nowrap;'>{$row["price"]} Ft</div></td>";
            $html .= "<td nowrap valign='top'><div class='{$tc}' style='text-align:left;white-space: nowrap;'>{$clinic}</div></td>";
            $html .= "<td nowrap valign='top'><div class='{$tc}' style='text-align:right'>{$row["sorrend"]}</div></td>";
            $html .= "<td nowrap valign='top'><div class='{$tc}'>{$active}</div></td>";

            $html .= "</tr>";
            $html .= "<tr><td colspan='10' style='border-top:1px solid #ccc;height:1px;'></td></tr>";
        }
        $html .= "</table>";

        return $html;
    }





}
