<?php

class AdminChatPage extends AdminCorePage {
    const PUBLIC_CHAT_BACKGROUND = "#d3fddc";
    const PRIVATE_CHAT_BACKGROUND = "#fdfdd3";
    const EXTERNAL_CHAT_BACKGROUND = "#eee";

    private ChatService $chatService;

    private array $fastTexts = [
        "Jó napot kívánok, #name# vagyok, miben segíthetek!",
        "Kis türelmét kérem!",
        "Kérem adja meg a nevét és a TAJ számát!",
        "Minden jót, viszontlátásra!",
    ];

    public function __construct()
    {
        parent::__construct();
        //$GLOBALS["javascript"][] = "adminchat.js?v=".date("YmdHi");
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $this->chatService = new ChatService();

        if (isset($_POST["newchatsession"])) {
            $_SESSION["openedsession"] = 0;
            Utils::jsonOut(["chatmain" => $this->chatMainWindow(0)]);
        }

        if (isset($_POST["editchatsession"])) {
            $sessionId = intval($_POST["editchatsession"]);
            $_SESSION["openedsession"] = $sessionId;
            Utils::jsonOut(["chatmain" => $this->chatMainWindow($sessionId, true)]);
        }

        if (isset($_POST["savechatsession"])) {
            $title = trim($_POST["title"]);
            $id = intval($_POST["id"]);
            $pub = $_POST["pub"];
            $sessionId = md5($this->adminUser->user["username"] . date("YmdHis"));
            if (!empty($title)) {
                if ($id == 0) {
                    sql_query("insert into chatsession set created=now(), createdby=?, session=?, title=?, domain='', external=0, pub=?", [$this->adminUser->user["id"], $sessionId, $title, $pub]);
                    $_SESSION["openedsession"] = sql_insert_id();
                } else {
                    sql_query("update chatsession set title=? where id=?", [$title, $id]);
                }
            }
            die;
        }

        if (isset($_POST["addChatUserToSession"])) {
            $sessionId = trim($_POST["addChatUserToSession"]);
            $aktiv = intval($_POST["aktiv"]);
            $userId = intval($_POST["userId"]);

            if ($sessionId == 0 && $userId == 0) {
                //init new chatgroup
                sql_query("insert into chatsession set created=now(), csoport=1, createdby=?, session=?, title=?, domain='', external=0, pub=0", [$this->adminUser->user["id"], md5($this->adminUser->user["username"] . date("YmdHis")), "csoport neve"]);
                $sessionId = sql_insert_id();
                $_SESSION["openedsession"] = $sessionId;
                sql_query("insert into chatsessionusers set userid=?, sessionid=?, active=1", [$this->adminUser->user["id"], $sessionId]);

                Utils::jsonOut(["chatmain" => $this->chatMainWindow($sessionId), "sessionlist" => $this->chatService->getSessionListHTML($this->adminUser->user["id"])]);
                die;
            }


            if ($sessionId == 0) {
                $testSessions = sql_query("SELECT sessionid, COUNT(*) AS hany, GROUP_CONCAT(userid) AS userids FROM chatsessionusers WHERE sessionid IN (SELECT sessionid FROM chatsessionusers u LEFT JOIN chatsession s ON s.id=u.sessionid WHERE userid=? AND s.csoport=0) GROUP BY sessionid HAVING hany=2 ORDER BY sessionid DESC", [$this->adminUser->user["id"]])->fetchAll(PDO::FETCH_ASSOC);
                foreach ($testSessions as $testSession) {
                    $users = explode(",", $testSession["userids"]);
                    if (in_array($userId, $users)) {
                        $foundSession = $testSession["sessionid"];
                        break;
                    }
                }

                if (isset($foundSession)) {
                    //van már régebben nyitott session
                    $sessionId = $foundSession;
                    sql_query("update chatsessionusers set active=1 where userid=? and sessionid=?", [$this->adminUser->user["id"], $sessionId]);
                } else {
                    sql_query("insert into chatsession set created=now(), createdby=?, session=?, title=?, domain='', external=0, pub=0", [$this->adminUser->user["id"], md5($this->adminUser->user["username"] . date("YmdHis")), "új chat"]);
                    $sessionId = sql_insert_id();
                    sql_query("insert into chatsessionusers set userid=?, sessionid=?", [$this->adminUser->user["id"], $sessionId]);
                    sql_query("insert into chatsessionusers set userid=?, sessionid=?", [$userId, $sessionId]);
                }
                $_SESSION["openedsession"] = $sessionId;
            } else {
                if ($aktiv == 0) {
                    sql_query("insert into chatsessionusers set userid=?, sessionid=?", [$userId, $sessionId]);
                } else {
                    sql_query("delete from chatsessionusers where userid=? and sessionid=?", [$userId, $sessionId]);
                }
            }

            Utils::jsonOut(["chatmain" => $this->chatMainWindow($sessionId), "sessionlist" => $this->chatService->getSessionListHTML($this->adminUser->user["id"]), "userbuttons" => $this->chatService->showUserButtons($sessionId)]);
            die;
        }

        if (isset($_POST["loadChatWindow"])) {
            $sessionId = intval($_POST["sessionId"]);

            if ($sessionId == 0) {
                $sessionId = $_SESSION["openedsession"];
            } else {
                $_SESSION["openedsession"] = $sessionId;
            }

            $html = "";
            $html.= $this->chatService->showChatMessages($sessionId);

            $newMessage = 0;
            $sessionList = "";
            if (sql_query("select id from chatsessionlog where userid=? and sessionid=? and tipus='unread' and checked=0", [$this->adminUser->user["id"], $sessionId])->fetch(PDO::FETCH_ASSOC)) {
                $newMessage = 1;
                sql_query("update chatsessionlog set checked=1, notified=1 where userid=? and sessionid=? and tipus='unread'", [$this->adminUser->user["id"], $sessionId]);
                $sessionList = $this->chatService->getSessionListHTML($this->adminUser->user["id"]);
            }

            Utils::jsonOut(["html" => $html, "new" => $newMessage, "sess" => $sessionId, "sessionlist" => $sessionList]);
        }

        if (isset($_POST["archiveChatWindow"])) {
            $sessionId = intval($_POST["sessionId"]);

            if ($sessionId != 0) {
                sql_query("update chatsessionusers set active=0 where userid=? and sessionid=?", [$this->adminUser->user["id"], $sessionId]);
            }

            $sessionList = $this->chatService->getSessionListHTML($this->adminUser->user["id"]);
            Utils::jsonOut(["sessionlist" => $sessionList]);
        }


        if (isset($_POST["loadChatWindowMain"])) {
            $sessionId = intval($_POST["sessionId"]);
            if ($_POST["chatGroupTitle"] != "null") {
                sql_query("update chatsession set title=? where id=? and csoport=1", [$_POST["chatGroupTitle"], $sessionId]);
            }
            sql_query("update chatsessionlog set checked=1, notified=1 where userid=? and sessionid=? and tipus='unread'", [$this->adminUser->user["id"], $sessionId]);
            Utils::jsonOut(["html" => $this->chatMainWindow($sessionId), "sessionlist" => $this->chatService->getSessionListHTML($this->adminUser->user["id"])]);
        }

        if (isset($_POST["chatSessionList"])) {
            Utils::jsonOut(["html" => $this->chatService->getSessionListHTML($this->adminUser->user["id"])]);
        }

        if (isset($_POST["sendmessage"])) {
            $chatSession = $_SESSION["openedsession"];
            $message = strip_tags($_POST["message"]);

            if ($chatSession != 0) {
                sql_query("insert into chat set datum=now(), chatsessionid=?, message=?, userid=?", [$chatSession, $message, $this->adminUser->user["id"]]);

                foreach (sql_query("select u.userid from chatsessionusers u where u.sessionid=? and u.userid<>?", [$chatSession, $this->adminUser->user["id"]])->fetchAll(PDO::FETCH_ASSOC) as $user) {
                    sql_query("update chatsessionusers set active=1 where userid=? and sessionid=?", [$user["userid"], $chatSession]);
                    sql_query("insert into chatsessionlog set created=now(), sessionid=?, userid=?, tipus='unread'", [$chatSession, $user["userid"]]);
                }
                sql_query("update chatsessionusers set active=1 where userid=? and sessionid=?", [$this->adminUser->user["id"], $chatSession]);
            }

            Utils::jsonOut(["messages" => $this->chatService->showChatMessages($chatSession), "sessionlist" => $this->chatService->getSessionListHTML($this->adminUser->user["id"])]);
        }

        if (isset($_GET["closechat"])) {
            sql_query("update chatsession set closed=now() where id=?", [$_GET["id"]]);
            header("location:index.php?page=chat");
            die;
        }

        if (isset($_GET["chatstatus"])) {
            $this->adminUtils->settings->setChatStatus($_GET["chatstatus"]);
            header("location:index.php?page=chat");
            die;
        }
    }


    public function showPage() {
        if (!$this->adminUser->chatAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        if ($this->adminUtils->settings->chatStatus == 1) {
            echo "<div style='margin-bottom:10px;color:green;'>Az ügyfélszolgálat chat jelenleg <strong>online</strong> <a href='index.php?page=chat&chatstatus=0'>kikapcsolás</a></div>";
        } else {
            echo "<div style='color:red;margin-bottom:10px;'>A ügyfélszolgálat chat jelenleg <strong>offline</strong> <a href='index.php?page=chat&chatstatus=1'>bekapcsolás</a></div>";
        }
    }


    private function chatMainWindow($sessionId, $editor = false):string {
        if ($sessionId == 0) {
            $sessionId = $_SESSION["openedsession"];
        } else {
            $_SESSION["openedsession"] = $sessionId;
        }

        $chatUsers = $this->chatService->getChatUsers($sessionId, $this->adminUser);
        $chatTitle = $chatUsers["text"];
        if (!empty($chatUsers["groupTitle"])) {
            $chatTitle = $chatUsers["groupTitle"]." - ".$chatTitle;
        }

        $html = "";

        $html.= "<div style='border:1px solid #ccc;background:white;'>";

        $html.= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
        $html.= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class='fa-solid fa-comment'></i>&nbsp;&nbsp;".mb_substr(empty($chatUsers["text"]) ? "Kivel szeretnél beszélgetni?":$chatTitle, 0, 42)."</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideChatPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
        $html.= "</div>";

        $html.= "<div style='display:table-cell;height:400px;vertical-align: bottom;'>";

        if (empty($chatUsers["text"]) || $sessionId == 0 || $editor) {
            $html.= "<div style='display:table-cell;height:400px;vertical-align: bottom;'>";

            $sessionData = sql_query("select * from chatsession where id=?", [$sessionId])->fetch(PDO::FETCH_ASSOC);

            $html.= "<div style='display:inline-block;padding:5px;width:370px;max-height:500px;overflow:auto;border:1px solid #000;'>";

            if ($sessionId == 0) {
                $html.= "<div style='margin:5px 0px;font-weight: bold;'><a href='#' data-aktiv='0' data-chatsessionid='0' data-userid='0' onclick='addChatUserToSession(this);return false;' class='newbutton'>Katt ide, ha ez egy csoport lesz</a>, vagy kattints a személyre, akivel privátban szeretnél beszélni:</div>";
            }

            if (isset($sessionData["csoport"]) && $sessionData["csoport"] == 1) {
                $html.= "<div id='chatgroupdata' style=''>Csoport megnevezése:</div>";
                $html.= "<div style='margin-top:5px;'><input type='text' id='chatgrouptitle' style='width: 100%;' value='{$sessionData["title"]}' /></div>";
                $html.= "<div style='margin-top:5px;'>Jelöld ki a felhasználókat akiket a csoporthoz akarsz adni, majd kattints a mentésre. Felhasználókat később is hozzá lehet adni a csoporthoz.</div>";
                $html.= "<div style='margin-top:5px;'><a href='#' onclick='loadChatWindowMain({$sessionId}, true);return false;' class='printbutton'>Csoport mentése</a></div>";
            }

            $html.= "<div id='sessionusers{$sessionId}'>";
            $html.= $this->chatService->showUserButtons($sessionId);
            $html.= "</div>";
            $html.= "</div>";

            $html.= "</div>";
        } else {
            if ($chatUsers["group"] == 1) {
                $num = substr_count($chatUsers["text"], ", ")+1;
                $html .= "<div style='display:table;width:100%;background:#f0f0f0;color:black;'>";
                $html .= "<div style='display:table-cell;vertical-align: middle;padding:4px 8px;font-size: 14px;'><i title='Csoport szerkesztése' style='cursor: pointer;' onclick='editChatSession({$sessionId});return false;' class='fa-solid fa-pen'></i>&nbsp;&nbsp;<span title='{$chatUsers["text"]}'>{$num} résztvevő</span></div>";
                //$html .= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideChatPopup();return false;' class='fa-solid fa-pen'></i></div>";
                $html .= "</div>";
            }

            $html.= "<div style='display:table-cell;height:400px;vertical-align: bottom;'>";
            $html.= "<div id='chatsessionitems' style='display:inline-block;padding:5px 10px;width:370px;max-height:400px;overflow:auto;'>" . $this->chatService->showChatMessages($sessionId) . "</div>";
            $html.= "</div>";
            $html.= "<div style='margin:5px 0px;'>";
            $html.= "<input id='chatmessagetext' type='text' placeholder='Írd be az üzenetet...' value='' style='margin:0px 5px 5px 5px;width:330px;padding:10px;border-radius: 10px;'/>&nbsp;&nbsp;<a title='Üzenet elküldése' href='#' onclick='sendChatMessage();return false;'><i style='font-size:16px;padding-right:10px;' class='fas fa-arrow-right'></i></a>";
            $html.= "</div>";
        }
        $html.= "</div>";

        $html.= "</div>";

        return $html;
    }

}