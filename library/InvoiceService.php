<?php

use \SzamlaAgent\SzamlaAgentAPI;
use \SzamlaAgent\Buyer;
use \SzamlaAgent\Document\Invoice\Invoice;
use \SzamlaAgent\Item\InvoiceItem;

class InvoiceService
{

    private string $agentKey = "7ik9iz58judwzuk9iz58rnw8v3k9iz58siiq2dk9iz";
    private array $paymentMethod = [
        "utanvet" => Invoice::PAYMENT_METHOD_CASH,
        "simplepay" => Invoice::PAYMENT_METHOD_OTP_SIMPLE,
        "atutalas" => Invoice::PAYMENT_METHOD_TRANSFER,
        "bankkartya" => Invoice::PAYMENT_METHOD_BANKCARD,
        "csekk" => Invoice::PAYMENT_METHOD_CHEQUE,
        "kezpenz" => Invoice::PAYMENT_METHOD_CASH,
        "szep-katya" => Invoice::PAYMENT_METHOD_SZEP_CARD,
        "paypal" => Invoice::PAYMENT_METHOD_PAYPAL
    ];

    public array $healthFunds = array(
        array(
            "name" => "Allianz Hungária Önkéntes Kölcsönös Egészség- és Önsegélyező Pénztár",
            "postcode" => "1087",
            "city" => "Budapest",
            "address" => "Könyves Kálmán krt. 48-52.",
            "taxnumber" => "18116870141"
        ),
        array(
            "name" => "Generali Egészség- és Önsegélyező Pénztár",
            "postcode" => "1066",
            "city" => "Budapest",
            "address" => "Teréz krt. 42-44.",
            "taxnumber" => "18177796242"
        ),
        array(
            "name" => "MBH Gondoskodás Egészségpénztár",
            "postcode" => "1056",
            "city" => "Budapest",
            "address" => "Váci u. 38.",
            "taxnumber" => "18232761141"
        ),
        array(
            "name" => "OTP Országos Egészség- és Önsegélyező Pénztár",
            "postcode" => "1138",
            "city" => "Budapest",
            "address" => "Váci út 135-139.",
            "taxnumber" => "18105564241"
        ),
        array(
            "name" => "PRÉMIUM Egészségpénztár",
            "postcode" => "1138",
            "city" => "Budapest",
            "address" => "Dunavirág u. 2-6.",
            "taxnumber" => "18177734241"
        ),
    );

    public function __construct()
    {
        if(isset($_POST["invoiceWindow"])){
            //echo $this->invoiceAdminWindow($_POST["invoiceWindow"]);
            //die();
            die(json_encode(array("error"=>"","html"=>$this->invoiceAdminWindow($_POST["invoiceWindow"]))));
        }

        if(isset($_POST["autofillHealthFundData"])){
            $error = "";
            $key=array_search($_POST["autofillHealthFundData"],array_column($this->healthFunds,"name"));
            if($key!==false){
                $result = array_merge($this->healthFunds[$key], array("error"=>$error));
                die(json_encode($result));
            }else{
                $error="Nincs ilyen egészségpénztár a rendszerben!";
                die(json_encode(array("error"=>$error)));
            }
        }

        if(isset($_POST["addItemToInvoice_in_window"])){
            echo "<pre>";
            print_r($_POST);
            echo "</pre>";
            die();
        }
    }

    public function createInvoice($purcaseId)
    {
        $purcaseData = sql_fetch_array(sql_query("SELECT * FROM labshop_vasarlasok WHERE id=?", [$purcaseId]));
        $buyerData = json_decode($purcaseData["szamla_adatok"],true);
        $itemData = json_decode($purcaseData["cart_content"],true);

        //Egy nyomi megoldás egy problémára, a nem létező mezőkre xd
        if($buyerData["type"]=="health-fund"){
            $buyerData["postcode"] = "";
            $buyerData["city"] = "";
            $buyerData["address"] = "";
        }

        try {
            //Session létrehozása
            $agent = SzamlaAgentAPI::create($this->agentKey);
            //Számla objektum létrehozása
            $invoice = new Invoice(Invoice::INVOICE_TYPE_P_INVOICE);
            //Vásárló alap adatinak megadása
            $buyer = new Buyer($buyerData["name"], $buyerData["postcode"], $buyerData["city"], $buyerData["address"]);
            $this->setBuyerDataByType($buyer, $buyerData);
            //Header szerkesztése
            $header = $invoice->getHeader();
            //Fizetési metódus megadása
            $header->setPaymentMethod($this->paymentMethod[$purcaseData["payment_method"]]);
            // Számla fizetési határideje
            $header->setPaymentDue(date("Y-m-d",strtotime($purcaseData["date"])));
            //Megjegyzés hozzáadása a számlához
            $header->setComment('megjegyzés teszt');
            //Vásárló adatinak hozzáadása a számlához
            $invoice->setBuyer($buyer);
            //Kosár tartalmának hozzáadása
            $invoice = $this->setInvoiceItems($invoice, $itemData);
            // Számla elkészítése
            $result = $agent->generateInvoice($invoice);
            // Agent válasz sikerességének ellenőrzése
            if ($result->isSuccess()) {
                echo 'A számla sikeresen elkészült. Számlaszám: ' . $result->getDocumentNumber();
                // Válasz adatai a további feldolgozáshoz
            }

            sql_query("UPDATE labshop_vasarlasok SET invoice_result=?, invoiceorderid=? WHERE id=?",[json_encode($result->getData(),JSON_PRETTY_PRINT),$result->getDocumentNumber(),$purcaseId]);

            /*echo "<pre>";
            print_r($result->getData());
            echo "</pre>";*/

        } catch (\Exception $e) {
            $agent->logError($e->getMessage());
        }
    }

    private function setBuyerDataByType($buyer, $data)
    {
        if ($data["type"] == "individual") {
            $buyer->setEmail($data["email"]);
            $buyer->setSendEmail(true);
        }

        if($data["type"] == "health-fund"){
            $utils = new Utils();
            $healthfundInstitute = $utils->health_fund_institues($data["healthFundInstitue"]);

            $buyer->setName($healthfundInstitute["name"].", ".$data["name"].", "."tagsági száma: ".$data["healthFundCode"]);
            $buyer->setZipCode($healthfundInstitute["postcode"]);
            $buyer->setCity($healthfundInstitute["city"]);
            $buyer->setAddress($healthfundInstitute["address"]);
            $buyer->setTaxNumber($healthfundInstitute["taxnumber"]);
        }

        if($data["type"] == "legal-entity"){
            $buyer->setTaxNumber($data["taxNumber"]);
            $buyer->setEmail($data["email"]);
            $buyer->setSendEmail(true);
        }
    }

    private function setInvoiceItems($invoice, $itemData)
    {
        $fullprice = 0;
        $discount = 0;
        $productManager = new ProductManager();
        foreach($itemData as $index=>$array){
            if(!is_array($array)) continue;
            $product = $productManager->getProductData($array["type"],$array["id"]);
            $item = new InvoiceItem(
                $name = $product["name"],
                $price = $array["price"],
                $quantity = $array["unit"],
                $quantityUnit = InvoiceItem::DEFAULT_QUANTITY_UNIT,
                $vat = InvoiceItem::VAT_TAM
            );
             $item->setNetPrice($array["price"]);
             // Tétel ÁFA értéke
             $item->setVatAmount(0);
             //Tétel megjegyzése
             $item->setComment("TEÁÓR 8690");
             // Tétel bruttó értéke
             $item->setGrossAmount($array["price"]);
             // Tétel hozzáadása a számlához
             $invoice->addItem($item);
            $fullprice=($fullprice+$array["price"]);
        }
        if(isset($itemData["kedvezmeny"])){
            if($itemData["kedvezmeny"]=="10%"){
                $discount=(($fullprice/10)*-1);
                $item = new InvoiceItem(
                    $name = "10% simplepay kedvezmény",
                    $price = $discount,
                    $quantity = InvoiceItem::DEFAULT_QUANTITY,
                    $quantityUnit = InvoiceItem::DEFAULT_QUANTITY_UNIT,
                    $vat = InvoiceItem::VAT_TAM
                );
                $item->setNetPrice($discount);
                // Tétel ÁFA értéke
                $item->setVatAmount(0);
                // Tétel bruttó értéke
                $item->setGrossAmount($discount);
                // Tétel hozzáadása a számlához
                $invoice->addItem($item);
            }
            if($itemData["kedvezmeny"]=="5%"){
                $discount=(($fullprice/5)*-1);
                $item = new InvoiceItem(
                    $name = "5% simplepay kedvezmény",
                    $price = $discount,
                    $quantity = InvoiceItem::DEFAULT_QUANTITY,
                    $quantityUnit = InvoiceItem::DEFAULT_QUANTITY_UNIT,
                    $vat = InvoiceItem::VAT_TAM
                );
                $item->setNetPrice($discount);
                // Tétel ÁFA értéke
                $item->setVatAmount(0);
                // Tétel bruttó értéke
                $item->setGrossAmount($discount);
                // Tétel hozzáadása a számlához
                $invoice->addItem($item);
            }
        }
       return $invoice;
    }

    public function downloadInvoicePDF(){
        $agent = SzamlaAgentAPI::create($this->agentKey);
        $result = $agent->getInvoicePdf('HNGRM-2023-32');
        // Agent válasz sikerességének ellenőrzése
        if ($result->isSuccess()) {
            $result->downloadPdf();
        }
    }

    public function invoiceAdminWindow($fid){

        $fogl=sql_fetch_array(sql_query("SELECT * FROM foglalasok WHERE id=?",[$fid]));

        $purcaseData = sql_fetch_array(sql_query("SELECT * FROM labshop_vasarlasok WHERE INSTR(cart_content, '\"reservationId\": \"{$fid}\"')"));
        $buyerData = json_decode($purcaseData["szamla_adatok"],true);
        $itemData = json_decode($purcaseData["cart_content"],true);
        $purcaseId = $purcaseData["id"];
        $labshopService = New LabshopService();
        $fullprice=0;
        $discount=0;
        $labType = "synlab";
        $labortetelek=sql_query("SELECT id,provider,name,price,'item' as type FROM synlab_labor_tetelek WHERE provider=\"synlab\" ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $laborcsomagok= sql_query("SELECT id,name,'package' as type FROM synlab_labor_csomagok WHERE aktiv=1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $vizsgalatok= sql_query("SELECT a.id,a.megnev as name FROM arak a 
                                 LEFT JOIN szurestipusok sz ON sz.id=a.tipusid 
                                 WHERE a.aktiv=1 AND a.cegid LIKE \"%|{$fogl["cegid"]}|%\" 
                                 ORDER BY a.megnev ASC")->fetchAll(PDO::FETCH_ASSOC);

        if(!isset($buyerData["taxNumber"])) $buyerData["taxNumber"]="";
        if(!isset($buyerData["name"])) $buyerData["name"]="";
        if(!isset($buyerData["postcode"])) $buyerData["postcode"]="";
        if(!isset($buyerData["address"])) $buyerData["address"]="";
        if(!isset($buyerData["city"])) $buyerData["city"]="";
        

        $html = "";

        $html .= "<div style='width:1000px;background:#eee;'>";
        $html .= "<form id=\"invoiceform\">";
        $html .= "<input type=\"hidden\" id=\"purcaseid\" name=\"purcaseid\" value=\"{$purcaseId}\"/>";
        $html .= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class=\"fa-solid fa-award\"></i>&nbsp;&nbsp;{$fogl["nev"]} - {$fogl["szuldatum"]} - {$fogl["taj"]}</div>";
        $html .= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideGeneralPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
        $html .= "</div>";

        $html .= "<div style='padding:10px;'>";

        $html .= "<div class=\"container\">";
        $html .= "<h5>Számla adatai</h5>";
        $html .= "    <div class=\"row\">";
        $html .= "        <div class=\"col\">";
        $html .= "          <div class=\"form-check form-check-inline\">";
        $html .= "              <input class=\"form-check-input\" type=\"radio\" ".($buyerData["type"]=="individual"?"checked=\"true\"":"")." name=\"billing_type\" id=\"billing_type1\" value=\"individual\">";
        $html .= "              <label class=\"form-check-label\" title=\"Magánszemély\" for=\"billing_type1\">Magán</label>";
        $html .= "          </div>";
        $html .= "          <div class=\"form-check form-check-inline\">";
        $html .= "              <input class=\"form-check-input\" type=\"radio\" ".($buyerData["type"]=="legal-entity"?"checked=\"true\"":"")." name=\"billing_type\" id=\"billing_type2\" value=\"legal-entity\">";
        $html .= "              <label class=\"form-check-label\" title=\"Jogi személy\" for=\"billing_type2\">Jogi</label>";
        $html .= "          </div>";
        $html .= "          <div class=\"form-check form-check-inline\">";
        $html .= "              <input class=\"form-check-input\" type=\"radio\" ".($buyerData["type"]=="health-fund"?"checked=\"true\"":"")." name=\"billing_type\" id=\"billing_type3\" value=\"health-fund\">";
        $html .= "              <label class=\"form-check-label\" title=\"Egészségpénztár\" for=\"billing_type3\">EP</label>";
        $html .= "          </div>";
        $html .= "          <div class=\"mb-2\">";
        $html .= "              <select class=\"form-select\" aria-label=\"Default select example\">";
        $html .= "                  <option value=\"\"> - Fizetési mód - </option>";
        foreach($this->paymentMethod as $index=>$each){
            $html .= "              <option ".($purcaseData["payment_method"]==$index?"selected=\"true\"":"")." value=\"{$index}\">{$each}</option>";
        }
        $html .= "              </select>";
        $html .= "          </div>";
        $html .= "          <div class=\"mb-2\">";
        $html .= "              <select class=\"form-select\" onChange=\"autofillHealthFundData($(this).val())\" aria-label=\"\">";
        $html .= "                  <option value=\"\"> - Egészségpénztár - </option>";
        foreach($this->healthFunds as $index=>$each){
            $html .= "              <option value=\"{$each["name"]}\">{$each["name"]}</option>";
        }
        $html .= "              </select>";
        $html .= "          </div>";
        $html .= "        </div>";
        $html .= "        <div class=\"col\">";
        $html .= "          <div class=\"mb-2\">";
        $html .= "            <label for=\"name\" class=\"form-label\">Tagnév:</label>";
        $html .= "            <input type=\"text\" class=\"form-control\" id=\"tagname\" placeholder=\"\" required>";
        $html .= "            <div class=\"valid-feedback\">";
        $html .= "            </div>";
        $html .= "          </div>";
        $html .= "          <div class=\"mb-2\">";
        $html .= "            <label for=\"name\" class=\"form-label\">Tagkód:</label>";
        $html .= "            <input type=\"text\" class=\"form-control\" id=\"membercode\" placeholder=\"\" required>";
        $html .= "            <div class=\"valid-feedback\">";
        $html .= "            </div>";
        $html .= "          </div>";
        $html .= "        </div>";
        $html .= "    </div>";
        $html .= "<hr></hr>";
        $html .= "<h5>Számlabefogadó adatai</h5>";
        $html .= "    <div class=\"row\">";
        $html .= "        <div class=\"col\">";
        $html .= "          <div class=\"mb-2\">";
        $html .= "            <label for=\"name\" class=\"form-label\">Név:</label>";
        $html .= "            <input type=\"text\" class=\"form-control\" id=\"name\" placeholder=\"\" value=\"{$buyerData["name"]}\" required>";
        $html .= "            <div class=\"valid-feedback\">";
        $html .= "            </div>";
        $html .= "          </div>";
        $html .= "          <div class=\"mb-2\">";
        $html .= "            <label for=\"postcode\" class=\"form-label\">Irányítószám:</label>";
        $html .= "            <input type=\"text\" class=\"form-control\" id=\"postcode\" placeholder=\"\" value=\"{$buyerData["postcode"]}\" required>";
        $html .= "            <div class=\"valid-feedback\">";
        $html .= "            </div>";
        $html .= "          </div>";
        $html .= "          <div class=\"mb-2\">";
        $html .= "            <label for=\"address\" class=\"form-label\">Cím:</label>";
        $html .= "            <input type=\"text\" class=\"form-control\" id=\"address\" placeholder=\"\" value=\"{$buyerData["address"]}\" required>";
        $html .= "            <div class=\"valid-feedback\">";
        $html .= "            </div>";
        $html .= "          </div>";
        $html .= "        </div>";
        $html .= "        <div class=\"col\">";
        $html .= "          <div class=\"mb-2\">";
        $html .= "            <label for=\"taxnumber\" class=\"form-label\">Adószám:</label>";
        $html .= "            <input type=\"text\" class=\"form-control\" id=\"taxnumber\" placeholder=\"\" value=\"{$buyerData["taxNumber"]}\" required>";
        $html .= "            <div class=\"valid-feedback\">";
        $html .= "            </div>";
        $html .= "          </div>";
        $html .= "          <div class=\"mb-2\">";
        $html .= "            <label for=\"city\" class=\"form-label\">Település:</label>";
        $html .= "            <input type=\"text\" class=\"form-control\" id=\"city\" placeholder=\"\" value=\"{$buyerData["city"]}\" required>";
        $html .= "            <div class=\"valid-feedback\">";
        $html .= "            </div>";
        $html .= "          </div>";
        $html .= "          <div class=\"mb-2\">";
        $html .= "            <label for=\"email\" class=\"form-label\">E-mail:</label>";
        $html .= "            <input type=\"text\" class=\"form-control\" id=\"email\" placeholder=\"\" value=\"{$buyerData["email"]}\" required>";
        $html .= "            <div class=\"valid-feedback\">";
        $html .= "            </div>";
        $html .= "          </div>";
        $html .= "        </div>";
        $html .= "    </div>";
        $html .= "<hr></hr>";

        //Elkell dönteni, hogy synlab, vagy spektrumlab-e
        //Ezt úgy tudom megtenni, hogy ha van spektrumlabos bejegyzés a foglalással, akkor spektrumos, különben meg synlab
        if($spektrumData = sql_query("SELECT * FROM labrequests WHERE foglalasid=?",array($fid))->fetch()){
            $labType="spektrumlab";
            $labortetelek=sql_query("SELECT id,provider,name,price FROM synlab_labor_tetelek WHERE provider=\"spektrumlab\" ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $html .= "<div id=\"invoiceItems\">";
        $html .= "  <div class=\"row row-cols-lg-auto align-items-center\">";
        $html .= "    <div class=\"col-12\">";
        $html .= "        <h5>Tételek</h5>";
        $html .= "    </div>";
        $html .= "    <div class=\"col-12\">";
        $html .= "        <div class=\"form-check form-check-inline\">";
        $html .= "            <input class=\"form-check-input\" type=\"radio\" ".($labType=="synlab"?"checked=\"true\"":"")." name=\"labselector\" id=\"labselector1\" value=\"synlab\">";
        $html .= "            <label class=\"form-check-label\" title=\"Synlab\" for=\"labselector1\">Synlab</label>";
        $html .= "        </div>";
        $html .= "        <div class=\"form-check form-check-inline\">";
        $html .= "            <input class=\"form-check-input\" type=\"radio\" ".($labType=="spektrumlab"?"checked=\"true\"":"")." name=\"labselector\" id=\"labselector2\" value=\"spektrumlab\">";
        $html .= "            <label class=\"form-check-label\" title=\"Spektrumlab\" for=\"labselector2\">Spektrumlab</label>";
        $html .= "        </div>";
        $html .= "    </div>";
        $html .= "  </div>";

        $html .= "  <div class=\"row row-cols-lg-auto align-items-center pb-2\">";
        $html .= "    <div class=\"col-12 px-0\">";
        $html .= "        <select class=\"form-select\" aria-label=\"Labor csomagok\" id=\"labpackageselector\" style=\"max-width:150px\">";
        $html .= "            <option selected>Labor csomagok</option>";
        foreach($laborcsomagok as $index=>$package){
            $html .= "<option value=\"{$package["id"]}\">{$package["name"]}</option>";
        }
        $html .= "        </select>";
        $html .= "    </div>";
        $html .= "    <div class=\"col-12 px-0\">";
        $html .= "        <button type=\"button\" class=\"btn btn-success\" onClick=\"addItemToInvoice_in_window({$fid},'package',$('#labpackageselector').val())\" style=\"line-height: 20px;\">Hozzáadás</button>";
        $html .= "    </div>";
        $html .= "    <div class=\"col-12 pe-0\">";
        $html .= "        <select class=\"form-select\" aria-label=\"Labor elemek\" id=\"labitemselector\" style=\"max-width:150px\">";
        $html .= "            <option selected>Labor elemek</option>";
        foreach($labortetelek as $index=>$item){
            $html .= "<option value=\"{$item["id"]}\">{$item["name"]}</option>";
        }
        $html .= "        </select>";
        $html .= "    </div>";
        $html .= "    <div class=\"col-12 px-0\">";
        $html .= "        <button type=\"button\" class=\"btn btn-success\" onClick=\"addItemToInvoice_in_window({$fid},'item',$('#labitemselector').val())\" style=\"line-height: 20px;\">Hozzáadás</button>";
        $html .= "    </div>";
        $html .= "    <div class=\"col-12 pe-0\">";
        $html .= "        <select class=\"form-select\" aria-label=\"Vizsgálatok\" id=\"examselector\"style=\"max-width:150px\">";
        $html .= "            <option selected>Vizsgálatok</option>";
        foreach($vizsgalatok as $index=>$exam){
            $html .= "<option value=\"{$exam["id"]}\">{$exam["name"]}</option>";
        }
        $html .= "        </select>";
        $html .= "    </div>";
        $html .= "    <div class=\"col-12 px-0\">";
        $html .= "        <button type=\"button\" class=\"btn btn-success\" onClick=\"addItemToInvoice_in_window({$fid},'exam',$('#examselector').val())\" style=\"line-height: 20px;\">Hozzáadás</button>";
        $html .= "    </div>";
        $html .= "  </div>";
        
        $html .= "  <div class=\"row\">";
        $html .= "      <div class=\"col\">";
        $html .= "        <div style=\"overflow-y:scroll;max-height:160px;\" class=\"mb-3\">";
        $html .= "            <table class=\"table table-responsive\">";
        $html .= "                <thead>";
        $html .= "                    <tr>";
        $html .= "                        <th scope=\"col\">Megnevezés</th>";
        $html .= "                        <th scope=\"col\">Menny.</th>";
        $html .= "                        <th scope=\"col\">Egységár</th>";
        $html .= "                        <th scope=\"col\">Nettó ár</th>";
        $html .= "                        <th scope=\"col\">Áfa</th>";
        $html .= "                        <th scope=\"col\">Áfaérték</th>";
        $html .= "                        <th scope=\"col\">Bruttó ár</th>";
        $html .= "                        <th scope=\"col\"></th>";
        $html .= "                    </tr>";
        $html .= "                </thead>";
        $html .= "                <tbody>";
        foreach($itemData as $index=>$row){
            if(!is_array($row)) continue;
            $product = $labshopService->getProductData($row["type"],$row["id"],$fogl["cegid"]);
            $html .= "                <tr>";
            $html .= "                    <td>{$product["name"]}</td>";
            $html .= "                    <td>{$row["unit"]}</td>";
            $html .= "                    <td>{$row["price"]}</td>";
            $html .= "                    <td>{$row["price"]}</td>";
            $html .= "                    <td>TAM</td>";
            $html .= "                    <td>0</td>";
            $html .= "                    <td>{$row["price"]}</td>";
            $html .= "                    <td><i class=\"fa-solid fa-trash-can\"></i></td>";
            $html .= "                </tr>";
            $fullprice=($fullprice+$row["price"]);
        }
        if(isset($itemData["kedvezmeny"])){
            if($itemData["kedvezmeny"]=="10%"){
                $discount=(($fullprice/10)*-1);
                $html .= "            <tr>";
                $html .= "                <td>10% simplepay kedvezmény</td>";
                $html .= "                <td>1</td>";
                $html .= "                <td>{$discount}</td>";
                $html .= "                <td>$discount</td>";
                $html .= "                <td>TAM</td>";
                $html .= "                <td>0</td>";
                $html .= "                <td>$discount</td>";
                $html .= "                <td><i class=\"fa-solid fa-trash-can\"></i></td>";
                $html .= "            </tr>";
            }
            if($itemData["kedvezmeny"]=="5%"){
                $discount=(($fullprice/5)*-1);
                $html .= "            <tr>";
                $html .= "                <td>5% simplepay kedvezmény</td>";
                $html .= "                <td>1</td>";
                $html .= "                <td>{$discount}</td>";
                $html .= "                <td>$discount</td>";
                $html .= "                <td>TAM</td>";
                $html .= "                <td>0</td>";
                $html .= "                <td>$discount</td>";
                $html .= "            </tr>";
            }
        }
        $html .= "                </tbody>";
        $html .= "            </table>";
        $html .= "            </div>";
        $html .= "          </div>";
        $html .= "      </div>";
        $html .= "  </div>";
        $html .= "</div>";
        $html .= "</form>";
        $html .= "</div>";

        return $html;
    }

    public function testPhase()
    {
        try {
            /**
             * Számla Agent létrehozása alapértelmezett adatokkal
             *
             * A számla sikeres kiállítása esetén a válasz (response) tartalmazni fogja
             * a létrejött bizonylatot PDF formátumban (1 példányban)
             */
            $agent = SzamlaAgentAPI::create($this->agentKey);

            /**
             * Új papír alapú számla létrehozása
             *
             * Átutalással fizetendő magyar nyelvű (Ft) számla kiállítása mai keltezési és
             * teljesítési dátummal, +8 nap fizetési határidővel.
             */
            $invoice = new Invoice(Invoice::INVOICE_TYPE_P_INVOICE);
            // Számla fejléce
            $header = $invoice->getHeader();
            // Számla fizetési módja (bankkártya)
            //$header->setPaymentMethod(Invoice::PAYMENT_METHOD_BANKCARD);
            $header->setPaymentMethod(Invoice::PAYMENT_METHOD_CASH);
            $header->setComment('megjegyzés');

            // Vevő adatainak hozzáadása (kötelezően kitöltendő adatokkal)
            $invoice->setBuyer(new Buyer('Kovács Bt.', '2030', 'Érd', 'Tárnoki út 23.'));
            // Számla tétel összeállítása alapértelmezett adatokkal (1 db tétel 27%-os ÁFA tartalommal)
            $item = new InvoiceItem('Eladó tétel 1', 10000, $quantity = InvoiceItem::DEFAULT_QUANTITY, $quantityUnit = InvoiceItem::DEFAULT_QUANTITY_UNIT, $vat = InvoiceItem::VAT_TAM);
            // Tétel nettó értéke
            $item->setNetPrice(10000);
            // Tétel ÁFA értéke
            $item->setVatAmount(0);
            // Tétel bruttó értéke
            $item->setGrossAmount(10000);
            // Tétel hozzáadása a számlához
            $invoice->addItem($item);

            // Számla elkészítése
            $result = $agent->generateInvoice($invoice);
            // Agent válasz sikerességének ellenőrzése
            if ($result->isSuccess()) {
                echo 'A számla sikeresen elkészült. Számlaszám: ' . $result->getDocumentNumber();
                // Válasz adatai a további feldolgozáshoz
            }
            echo "<pre>";
            print_r($result->getDataObj());
            echo "</pre>";
        } catch (\Exception $e) {

            $agent->logError($e->getMessage());
        }
    }
}
