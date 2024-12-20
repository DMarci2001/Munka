<?php

class AdminDirektMarketingPage extends AdminCorePage
{

    private $dmId;

    public function __construct()
    {
        parent::__construct();

        if (isset($_GET["szerk"])) {
            if (!empty($this->getDMList($_GET["szerk"])) && $_GET["szerk"]!="cimzett_lista") {
                $this->dmId = $_SESSION["dmId"] = $_GET["szerk"];
            }
        }

        if (isset($_POST["insertNewDMList"])) {
            $q = sql_query("SELECT * FROM direkt_marketing WHERE megnev='{$_POST["record"]}'")->fetch(PDO::FETCH_ASSOC);
            if ($q) {
                die(json_encode(array("error" => "megadott listanév már létezik!")));
            } else {
                sql_query(
                    "INSERT INTO direkt_marketing SET megnev=?,created=?,created_by=?,recipient_list_size=?",
                    [$_POST["record"], date("Y-m-d H:i:s"), $_SESSION["adminuser"]["id"], 0]
                );
                die(json_encode(array("success" => true, "html" => $this->initializeDMList())));
            }
            die();
        }

        if (isset($_POST["setRecipientSubscribe"])) {
            if ($data = sql_query("SELECT * FROM direkt_marketing_cimzettek WHERE id=?", [$_POST["recipient"]])->fetch(PDO::FETCH_ASSOC)) {
                if ($data["subscribed"] == 1) {
                    $value = 0;
                    $unsub_date = date("Y-m-d H:i:s");
                } else {
                    $value = 1;
                    $unsub_date = null;
                }
                sql_query("UPDATE direkt_marketing_cimzettek SET subscribed=?, unsubscribed_date=? WHERE id=?", [$value, $unsub_date, $_POST["recipient"]]);
                die(json_encode(["date" => ($unsub_date == null) ? " - " : str_replace("-", ".", $unsub_date)]));
            }
            die(json_encode(["error" => "A címzett nem található!"]));
        }
    }

    public function showPage()
    {
        $html = "";

        $html .= "<div class='container-xxl mx-3'>";
        if (empty($this->dmId)) {
            unset($_SESSION["dmId"]);
            $html  = "<div class='container-xxl mx-3'>";
            $html .= $this->initializeDMList();
            $html .= "</div>";
        }
        if (isset($_GET["szerk"]) && $_GET["szerk"]!="cimzett_lista") {
            $html  = "<div class='container-xxl mx-3'>";
            $html .= $this->initializeDMProcessManagerUI($_GET["szerk"]);
            $html .= "</div>";
        }
        if (isset($_GET["szerk"]) && $_GET["szerk"]=="cimzett_lista") {
            $_GET["szerk"] = null;
            $html  = "<div class='container-xxl mx-3'>";
            $html .= $this->initializeRecipientList();
            $html .= "</div>";
        }


        $html .= "</div>";

        echo $html;
    }

    private function initializeDMList(): string
    {
        $html = "";

        $html .= "<div id='dm-list-container'>";
        $html .= "  <div class='container-xxl mb-3'>";
        $html .= "      <button type='button' class='btn btn-secondary btn-sm' title='Új DM lista készítése' onClick='insertNewDMList()'><i class='fa-solid fa-table-list'>&nbsp;</i><i class='fa-solid fa-plus'></i></button>";
        $html .= "      <a role='button' href='?page=direktmarketing&szerk=cimzett_lista' class='btn btn-success btn-sm' title='Teljes címzett lista' onClick=''><i class='fa-solid fa-address-book'></i></a>";
        $html .= "  </div>";

        $data = sql_query("SELECT * FROM direkt_marketing ORDER BY megnev ASC")->fetchAll(PDO::FETCH_ASSOC);

        $html .= "   <table id='dm-list' class='table table-hover'>";
        $html .= "       <thead>";
        $html .= "           <tr class='text-center'>";
        $html .= "           <th scope='col'>#</th>";
        $html .= "           <th scope='col'>Lista név</th>";
        $html .= "           <th scope='col'>Utolsó értesítés</th>";
        $html .= "           <th scope='col'>Hozzáadott címjegyzék</th>";
        $html .= "           <th scope='col'>Létrehozta</th>";
        $html .= "           </tr>";
        $html .= "       </thead>";
        $html .= "       <tbody>";
        for ($i = 0; $i < count($data); $i++) {
            $html .= "      <tr role='button' class='text-center' data-dm-id='{$data[$i]["id"]}'>";
            $html .= "          <th scope='row'>{$i}.</th>";
            $html .= "          <td>" . $this->replaceOnNull($data[$i]["megnev"]) . "</td>";
            $html .= "          <td>" . $this->replaceOnNull($data[$i]["last_send"]) . "</td>";
            $html .= "          <td>" . $this->replaceOnNull($data[$i]["recipient_list_size"]) . "</td>";
            $html .= "          <td>" . $this->replaceOnNull($this->getUserName($data[$i]["created_by"])) . "</td>";
            $html .= "      </tr>";
        }

        $html .= "       </tbody>";
        $html .= "   </table>";
        $html .= "   <script type='text/javascript' src='js/dm_ui.js'></script>";
        $html .= "</div>";

        return $html;
    }
    private function getUserName($uid): string
    {
        $user = sql_query("SELECT nev FROM users WHERE id=?", [$uid])->fetch(PDO::FETCH_ASSOC);
        return $user["nev"];
    }
    private function replaceOnNull($data)
    {
        if ($data == "0" || $data == null) {
            return " - ";
        }
        return $data;
    }

    private function getDMList($dmId): array
    {
        $data = sql_query("SELECT * FROM direkt_marketing WHERE id=?", [$dmId])->fetch(PDO::FETCH_ASSOC);
        if (empty($data)) {
            return [];
        } else {
            return $data;
        }
    }

    private function initializeDMProcessManagerUI($dmId): string
    {
        $html = "";
        $data = $this->getDMList($dmId);
        if (empty($data)) {
            return $this->initializeDMList();
        }

        $html .= "<div class='mb-3'>";
        $html .= "    <label for='megnev' class='form-label'>Lista megnevezése:</label>";
        $html .= "    <input type='text' class='form-control' id='megnev' placeholder='Megnevezés' value='{$data["megnev"]}'>";
        $html .= "</div>";
        $html .= "<div class='mb-3'>";
        $html .= "    <label for='megj' class='form-label'>Megjegyzés:</label>";
        $html .= "    <textarea class='form-control' id='megj' rows='3'></textarea>";
        $html .= "</div>";
        $html .= "<div class='d-grid gap-2'>";
        $html .= "    <button class='btn btn-secondary' type='button'><i class='fa-solid fa-floppy-disk'></i>&nbsp;Mentés</button>";
        $html .= "</div>";

        $html .= "<hr></hr>";

        $html .= "<ul class='nav nav-tabs' id='myTab' role='tablist'>";
        $html .= "    <li class='nav-item' role='presentation'>";
        $html .= "        <button class='nav-link active' id='send-dm-tab' data-bs-toggle='tab' data-bs-target='#send-dm-tab-pane' type='button' role='tab' aria-controls='send-dm-tab-pane' aria-selected='true'><i class='fa-regular fa-paper-plane'></i>&nbsp;<i class='fa-regular fa-envelope'></i>&nbsp;DM küldés</button>";
        $html .= "    </li>";
        $html .= "    <li class='nav-item' role='presentation'>";
        $html .= "        <button class='nav-link' id='previous-send-list-tab' data-bs-toggle='tab' data-bs-target='#previous-send-list-tab-pane' type='button' role='tab' aria-controls='previous-send-list-tab-pane' aria-selected='true'><i class='fa-solid fa-clock-rotate-left'></i>&nbsp;Küldési előzmények</button>";
        $html .= "    </li>";
        $html .= "    <li class='nav-item' role='presentation'>";
        $html .= "        <button class='nav-link' id='recipient-list-tab' data-bs-toggle='tab' data-bs-target='#recipient-list-tab-pane' type='button' role='tab' aria-controls='recipient-list-tab-pane' aria-selected='false'><i class='fa-solid fa-list-check'></i>&nbsp;Címzett Lista</button>";
        $html .= "    </li>";
        $html .= "</ul>";
        $html .= "<div class='tab-content' id='myTabContent'>";
        $html .= "    <div class='tab-pane pt-3 ps-3 fade show active' id='send-dm-tab-pane' role='tabpanel' aria-labelledby='profile-tab' tabindex='0'>" . $this->setDMSend() . "</div>";
        $html .= "    <div class='tab-pane pt-3 ps-3 fade' id='previous-send-list-tab-pane' role='tabpanel' aria-labelledby='home-tab' tabindex='0'>...</div>";
        $html .= "    <div class='tab-pane pt-3 ps-3 fade' id='recipient-list-tab-pane' role='tabpanel' aria-labelledby='profile-tab' tabindex='0'>" . $this->recipientListViewer() . "</div>";
        $html .= "</div>";
        $html .= "<script type='text/javascript' src='js/dm_ui.js'></script>";

        return $html;
    }

    private function recipientListViewer(): string
    {
        echo $_GET["szerk"]."<br>";
        $html = "";

        $data = sql_query("SELECT dmc.* FROM direkt_marketing_cimzettek_link_tabla dmcl
                           LEFT JOIN direkt_marketing_cimzettek dmc ON dmc.id=dmcl.recipient_id AND dmc.subscribed=1
                           WHERE dmcl.dm_id=? AND dmc.id IS NOT NULL", [$this->dmId])->fetchAll(PDO::FETCH_ASSOC);

        if (empty($data)) {
            $html .= "<a href='?page=patientdata&action=create-dm-recipient-list&dmid={$this->dmId}'>";
            $html .= "  <h5>Lista feltöltéséhez kattints ide <i class='fa-solid fa-arrow-up-right-from-square'></i></h5>";
            $html .= "</a>";
        } else {
            $html .= "   <div style='max-height:800px;overflow-y: scroll;'>";
            $html .= "       <table id='recipient-list' class='table table-hover'>";
            $html .= "           <thead>";
            $html .= "               <tr>";
            $html .= "               <th class='text-center align-middle' scope='col'>#</th>";
            $html .= "               <th class='text-center align-middle' scope='col'><i class='fa-solid fa-gear'></i></th>";
            $html .= "               <th class='text-center align-middle' scope='col'>Teljesnév</th>";
            $html .= "               <th class='text-center align-middle' scope='col'>Email cím</th>";
            //$html .= "               <th class='text-center' scope='col'>Leiratkozás ideje</th>";
            $html .= "               </tr>";
            $html .= "           </thead>";
            $html .= "           <tbody>";
            for ($i = 0; $i < count($data); $i++) {
                $html .= "           <tr role='button' data-dm-recipient-id='{$data[$i]["id"]}'>";
                $html .= "               <th class='text-center align-middle' scope='row'>{$i}.</th>";
                $html .= "               <td class='text-center align-middle'>";
                if($data[$i]["subscribed"]==1){
                    $html .= "              <button type='button' class='btn btn-danger btn-sm unsub-dm'><i class='fa-solid fa-bell-slash' title='Leiratkozás'></i></button>";
                }else{
                    $html .= "              <button type='button' class='btn btn-success btn-sm resub-dm'><i class='fa-solid fa-bell' title='Feliratkozás'></i></button>";
                }
                //$html .= "                    <div class='form-check form-switch text-center'>";
                //$html .= "                        <input class='form-check-input subscribe-switch' type='checkbox' " . ($data[$i]["subscribed"] == 1 ? "checked='true'" : "") . " value='1' role='switch'>";
                //$html .= "                    </div>";
                $html .= "               </td>";
                $html .= "               <td class='align-middle'>" . $this->replaceOnNull($data[$i]["nev"]) . "</td>";
                $html .= "               <td class='align-middle'>" . $this->replaceOnNull($data[$i]["email"]) . "</td>";
                //$html .= "               <td class='text-center unsubscribed-date'>" . $this->replaceOnNull($data[$i]["unsubscribed_date"]) . "</td>";
                $html .= "           </tr>";
            }

            $html .= "           </tbody>";
            $html .= "       </table>";
            $html .= "   </div>";
        }

        return $html;
    }

    private function setDMSend()
    {
        $html = "";
        $html .= "<div class='mb-3'>";
        $html .= "   <label for='dm-sender' class='form-label'>Küldő e-mail cím:</label>";
        $html .= "   <input type='email' class='form-control' id='exampleFormControlInput1' placeholder='name@example.com'>";
        $html .= "</div>";
        $html .= "<div class='mb-3'>";
        $html .= "   <label for='dm-subject' class='form-label'>Üzenet tárgya:</label>";
        $html .= "   <input type='text' class='form-control' id='dm-subject' placeholder='Tárgy'>";
        $html .= "</div>";
        $html .= "<div class='mb-3'>";
        $html .= "   <label for='dm-email-content' class='form-label'>Levél tartalma:&nbsp;<button class='btn btn-secondary btn-sm' type='button'><i class='fa-solid fa-floppy-disk'></i>&nbsp;Mentés</button></label>";
        $html .= "   <textarea class='form-control mce' id='email-content'></textarea>";
        $html .= "</div>";
        $html .= "<div class='d-grid gap-2'>";
        $html .= "    <button class='btn btn-danger' type='button'><i class='fa-regular fa-paper-plane'></i>&nbsp;Küldés</button>";
        $html .= "</div>";

        return $html;
    }

    private function initializeRecipientList(): string
    {
        $html = "";

        echo $_GET["szerk"]."<br>";

        $data = sql_query("SELECT * FROM direkt_marketing_cimzettek", [$this->dmId])->fetchAll(PDO::FETCH_ASSOC);

        $html .= "       <table id='recipient-list' class='table table-hover'>";
        $html .= "           <thead>";
        $html .= "               <tr>";
        $html .= "               <th class='text-center align-middle' scope='col'>#</th>";
        $html .= "               <th class='text-center align-middle' scope='col'><i class='fa-solid fa-gear'></i></th>";
        $html .= "               <th class='text-center align-middle' scope='col'>Teljesnév</th>";
        $html .= "               <th class='text-center align-middle' scope='col'>Email cím</th>";
        $html .= "               <th class='text-center' scope='col'>Leiratkozás ideje</th>";
        $html .= "               </tr>";
        $html .= "           </thead>";
        $html .= "           <tbody>";
        for ($i = 0; $i < count($data); $i++) {
            $html .= "           <tr role='button' data-dm-recipient-id='{$data[$i]["id"]}'>";
            $html .= "               <th class='text-center align-middle' scope='row'>{$i}.</th>";
            $html .= "               <td class='text-center align-middle'>";
            if($data[$i]["subscribed"]==1){
                $html .= "              <button type='button' class='btn btn-danger btn-sm unsub-dm'><i class='fa-solid fa-bell-slash' title='Leiratkozás'></i></button>";
            }else{
                $html .= "              <button type='button' class='btn btn-success btn-sm resub-dm'><i class='fa-solid fa-bell' title='Feliratkozás'></i></button>";
            }
            
            //$html .= "                    <div class='form-check form-switch text-center'>";
            //$html .= "                        <input class='form-check-input subscribe-switch' type='checkbox' " . ($data[$i]["subscribed"] == 1 ? "checked='true'" : "") . " value='1' role='switch'>";
            //$html .= "                    </div>";
            $html .= "               </td>";
            $html .= "               <td class='align-middle'>" . $this->replaceOnNull($data[$i]["nev"]) . "</td>";
            $html .= "               <td class='align-middle'>" . $this->replaceOnNull($data[$i]["email"]) . "</td>";
            $html .= "               <td class='text-center unsubscribed-date'>" . $this->replaceOnNull($data[$i]["unsubscribed_date"]) . "</td>";
            $html .= "           </tr>";
        }

        $html .= "           </tbody>";
        $html .= "       </table>";
        $html .= "<script type='text/javascript' src='js/dm_ui.js'></script>";
        return $html;
    }
}
