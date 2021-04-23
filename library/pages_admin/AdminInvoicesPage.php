<?php


class AdminInvoicesPage extends AdminCorePage
{
    private $adminUser;

    public function __construct()
    {
        parent::__construct();

        $this->adminUser = new AdminUser();

        if (isset($_POST["toggleinvoicefizetve"])) {
            if (sql_fetch_array(sql_query("select * from remoteids where provider='invoice' and tipus='fizetve' and remoteid=?", [$_POST["toggleinvoicefizetve"]]))) {
                sql_query("delete from remoteids where provider='invoice' and tipus='fizetve' and remoteid=?", [$_POST["toggleinvoicefizetve"]]);
            } else {
                sql_query("insert into remoteids set provider='invoice', tipus='fizetve', remoteid=?", [$_POST["toggleinvoicefizetve"]]);
            }

            $result["html"] = $this->_invoiceList($_POST["partnerid"]);
            $this->utils->jsonOut($result);
        }
    }

    public function showPage() {
        if (!$this->adminUser->salaryAccess()) {
            echo "nincs jogosultságod!";
            return;
        }

        echo "<div>";
        echo $this->invoiceStat();
        echo "</div>";
    }

    private function sqliteQuery($query) {
        //a szerveren nem elérhető, ezért másik szerverről kérdezzük le

        $result = file_get_contents("http://www.jns.hu/invoicestat/index.php?lquery=".urlencode($query));

        return json_decode($result, JSON_OBJECT_AS_ARRAY);
    }


    public function invoiceStat() {
        $result = $this->sqliteQuery("SELECT orderhead_partner_id, orderhead_partner_name, count(*) as db FROM orderhead WHERE orderhead_partner_id<>0 group by orderhead_partner_id order by orderhead_partner_name");

        $html = "";
        $html.= "<div style='display:table;'>";
        $html.= "<div style='display:table-cell;width:300px;vertical-align: top;padding-right:10px;border-right: 1px solid #888;'>";
        foreach ($result as $partner) {
            $html.= "<div><a href='index.php?page=invoices&qpartner=".urlencode($partner["ORDERHEAD_PARTNER_ID"])."'>{$partner["ORDERHEAD_PARTNER_NAME"]}</a> ({$partner["db"]} db)</div>";
        }
        $html.= "</div>";

        $html.= "<div style='display:table-cell;vertical-align: top;padding-left:10px;'>";

        $html.= "<div id='invoicelist'>";
        if (isset($_GET["qpartner"])) {
            $html.= $this->_invoiceList(intval($_GET["qpartner"]));
        }
        $html.= "</div>";

        $html.= "</div>";

        $html.= "</div>";

        return $html;
    }

    private function _invoiceList($partnerId) {
        $invoices = $this->sqliteQuery("SELECT * FROM orderhead WHERE orderhead_partner_id = '{$partnerId}' order by ORDERHEAD_DATE_CREATED desc");

        $html = "";
        $html.= "<h2>{$invoices[0]["ORDERHEAD_PARTNER_NAME"]}</h2>";
        $html.= "<div style='display:table;'>";
        $html.= "<div style='display:table-row;background:#888;color:#fff;'>";
        $html.= "<div style='display:table-cell;white-space: nowrap;padding:5px;'>Művelet&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;padding:5px;'>Számla száma&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;'>Teljesítés dátuma&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;'>Számla kelte&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;'>Fizetési határidő&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;'>Partner neve</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;'>Fizetés mód&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $html.= "<div style='display:table-cell;white-space: nowrap;'>Számla összege bruttó&nbsp;&nbsp;&nbsp;&nbsp;</div>";
        $html.= "</div>";

        foreach ($invoices as $invoice) {
            $s = "";
            if (sql_fetch_array(sql_query("select * from remoteids where provider='invoice' and tipus='fizetve' and remoteid=?", [$invoice["ORDERHEAD_ID"]]))) {
                $s = "background-color:#b0ffad";
            }
            $html.= "<div style='display:table-row;{$s}'>";
            $html.= "<div style='display:table-cell;white-space: nowrap;padding:2px 0px;border-top:1px solid #ccc;'>";

            $html.= "<a href='#' onclick='toggleInvoiceFizetve({$invoice["ORDERHEAD_ID"]}, {$partnerId});return false;'>fizetve</a>";

            $html.= "</div>";
            $html.= "<div style='display:table-cell;white-space: nowrap;padding:2px 0px;border-top:1px solid #ccc;'>{$invoice["ORDERHEAD_INVOICE_NO_STR"]}</div>";
            $html.= "<div style='display:table-cell;white-space: nowrap;border-top:1px solid #ccc;'>{$invoice["ORDERHEAD_DATE_SHIPPED"]}</div>";
            $html.= "<div style='display:table-cell;white-space: nowrap;border-top:1px solid #ccc;'>{$invoice["ORDERHEAD_DATE_CREATED"]}</div>";
            $html.= "<div style='display:table-cell;white-space: nowrap;border-top:1px solid #ccc;'>{$invoice["ORDERHEAD_DATE_PAYMENT_DUE"]}</div>";
            $html.= "<div style='display:table-cell;border-top:1px solid #ccc;'>{$invoice["ORDERHEAD_PARTNER_NAME"]}&nbsp;&nbsp;&nbsp;&nbsp;</div>";
            $html.= "<div style='display:table-cell;white-space: nowrap;border-top:1px solid #ccc;'>{$invoice["ORDERHEAD_PAYMENTMETHOD_NAME"]}</div>";
            $html.= "<div style='display:table-cell;white-space: nowrap;border-top:1px solid #ccc;text-align:right;'>{$invoice["ORDERHEAD_TOTAL_GROSS"]}</div>";
            $html.= "</div>";
        }

        $html.= "</div>";
        return $html;
    }


}

