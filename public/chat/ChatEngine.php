<?php

class ChatEngine {
    private PDO $db;
    public string $welcomeText   = "Jó napot! Ez a Hungariamed ügyfélszolgálata, miben segíthetek?";
    public string $supportName   = "Hungariamed-M";
    public string $supportTitle  = "Ügyfélszolgálat";
    public string $supportAvatar = "https://bejelentkezes.hungariamed.hu/chat/chatAvatar.jpg";

    public function __construct() {
        $this->connect();
    }

    public function processAjaxRequests() {
        if (isset($_POST["sendmessage"])) {
            $chatSession = $this->getChatSession();
            $message = strip_tags($_POST["message"]);

            $this->sqlQuery("insert into chat set datum=now(), chatsessionid=?, message=?", [$chatSession, $message]);

            //if (trim(strtolower($message)) == "banán") {
                //$this->sqlQuery("insert into chat set datum=date_add(now(), interval 1 second), chatsessionid=?, userid=1, message='A banán a trópusokon elterjedt és termesztett egyszikű, lágy szárú, bár gyakran fatermetű növények nemzetsége a banánfélék (Musaceae) róluk elnevezett családjában. A világ legmagasabb egyszikű, lágy szárú növénye.'", [$chatSession]);
            //} else {
                //$this->sqlQuery("insert into chat set datum=date_add(now(), interval 1 second), chatsessionid=?, userid=1, message='Elnézést, erre a kérdésre pont nem tudom a választ :('", [$chatSession]);
            //}

            echo $this->generateChatContentHTML();
            die;
        }

        if (isset($_POST["reloadChat"])) {
            echo $this->generateChatContentHTML();
            die;
        }
    }


    private function getChatSession() {
        $sessionId = session_id();
        $domain    = $_SERVER["HTTP_HOST"];

        if ($sessionData = $this->sqlQuery("select * from chatsession where session=? and domain=?", [$sessionId, $domain])->fetch(PDO::FETCH_ASSOC)) {
            $id = $sessionData["id"];
        } else {
            $this->sqlQuery("insert into chatsession set created=now(), session=?, domain=?", [$sessionId, $domain]);
            $id = $this->db->lastInsertId();
        }

        return $id;
    }


    public function generateChatContentHTML():string {
        $html = "";
        $chatSession = $this->getChatSession();
        $messages = $this->sqlQuery("select * from chat where chatsessionid=? order by datum", [$chatSession])->fetchAll(PDO::FETCH_ASSOC);

        if (empty($messages)) {
            $this->sqlQuery("insert into chat set datum=now(), chatsessionid=?, userid=-1, message=?", [$chatSession, $this->welcomeText]);
            $messages = $this->sqlQuery("select * from chat where chatsessionid=? order by datum", [$chatSession])->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($messages as $message) {
            if ($message["userid"] == 0) {
                $html.= "<span class='chat_msg_item chat_msg_item_user'>{$message["message"]}</span>";
                $html.= "<span class='status'>".$this->chatTimeString($message["datum"])."</span></span>";
            } else {
                $html.= "<span class='chat_msg_item chat_msg_item_admin'>";
                $html.= "<div class='chat_avatar'><img src='{$this->supportAvatar}'/></div>";
                $html.= "{$message["message"]}</span>";
            }
        }
        return $html;
    }

    private function connect() {
        try {
            $this->db = new PDO("mysql:host=localhost;dbname=hungariamed;charset=utf8", "hungariamed", "hmedpass");
        } catch (PDOException $e) {
            echo "Error 1420<br/>Kérjük próbálkozzon később!";
            //print "Error: " . $e->getMessage();
            die();
        }

        $this->sqlQuery("SET NAMES utf8");
        $this->sqlQuery("SET CHARACTER SET utf8");
        $this->sqlQuery("SET COLLATION_CONNECTION='utf8_unicode_ci'");
    }


    private function sqlQuery($q,$params=null) {
        $stmt = $this->db->prepare($q);
        $stmt->execute($params);
        $error = $stmt->errorInfo();
        if ($error[2] != "") {
            print_r($error);
        }
        return $stmt;
    }

    private function chatTimeString($datum):string {
        $diff = strtotime("now") - strtotime($datum);
        if ($diff < 60) {
            return $diff . " másodperce";
        }
        if ($diff < 3600) {
            return round($diff/60) . " perce";
        }
        if ($diff < 86400) {
            return round($diff/3600) . " órája";
        }

        return round($diff/86400) . " napja";
    }
}