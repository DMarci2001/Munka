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
            $sessionId = md5($this->adminUser->user["username"] . date("YmdHis"));
            sql_query("insert into chatsession set created=now(), createdby=?, session=?, title=?, domain='', external=0, pub=0", [$this->adminUser->user["id"], $sessionId, "új chat"]);
            $newId = sql_insert_id();

            sql_query("insert into chatsessionusers set userid=?, sessionid=?", [$this->adminUser->user["id"], $newId]);

            $_SESSION["openedsession"] = $newId;
            Utils::jsonOut(["chatmain" => $this->chatMainWindow($newId)]);
        }

        if (isset($_POST["opensessioneditor"])) {
            $id = intval($_POST["id"]);
            $pub = intval($_POST["pub"]);
            $saveButtonTitle = "Chat ablak létrehozása";
            $title = "";

            if ($id != 0) {
                if ($sessionData = sql_query("select * from chatsession where id=?", [$id])->fetch(PDO::FETCH_ASSOC)) {
                    $title = $sessionData["title"];
                    $pub = $sessionData["pub"];
                    $saveButtonTitle = "Mentés";
                } else {
                    die;
                }
            }

            $backGroundColor = $pub == 1 ? self::PUBLIC_CHAT_BACKGROUND : self::PRIVATE_CHAT_BACKGROUND;

            echo "<div style='background:{$backGroundColor};color:#000;padding:10px;margin-bottom:10px;'>";

            echo "<input type='hidden' id='editedsessionid' value='{$id}' />";
            echo "<input type='hidden' id='editedsessionpublic' value='{$pub}' />";
            echo "<div>Chat ablak témája:</div>";
            echo "<div><input type='text' id='editedsessiontitle' value='{$title}' style='width:250px;' /></div>";
            echo "<div style='padding-top:8px;'><a class='ujbutton' onclick='addNewChatSession();return false;' href='#'>{$saveButtonTitle}</a> <a class='ujbutton' onclick='closeChatSessionEditors();return false;' href='#'>Mégse</a></div>";

            if ($pub == 0 && isset($sessionData)) {
                echo "<div style='width: 300px;' id='sessionusers{$sessionData["id"]}'>".$this->chatService->showUserButtons($sessionData["id"])."</div>";
            }

            echo "</div>";
            die;
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

        if (isset($_POST["toggleChatUserSession"])) {
            $sessionId = trim($_POST["toggleChatUserSession"]);
            $aktiv = intval($_POST["aktiv"]);
            $userId = intval($_POST["userId"]);

            if ($aktiv == 0) {
                sql_query("insert into chatsessionusers set userid=?, sessionid=?", [$userId, $sessionId]);
            } else {
                sql_query("delete from chatsessionusers where userid=? and sessionid=?", [$userId, $sessionId]);
            }

            Utils::jsonOut(["chatmain" => $this->chatMainWindow($sessionId), "sessionlist" => $this->chatService->getSessionListHTML($this->adminUser->user["id"])]);
            //echo $this->chatService->showUserButtons($sessionId);
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
            if (sql_query("select id from chatsessionlog where userid=? and sessionid=? and tipus='unread' and checked=0", [$this->adminUser->user["id"], $sessionId])->fetch(PDO::FETCH_ASSOC)) {
                $newMessage = 1;
                sql_query("update chatsessionlog set checked=1, notified=1 where userid=? and sessionid=? and tipus='unread'", [$this->adminUser->user["id"], $sessionId]);
            }

            Utils::jsonOut(["html" => $html, "new" => $newMessage, "sess" => $sessionId]);
        }

        if (isset($_POST["loadChatWindowMain"])) {
            $sessionId = intval($_POST["sessionId"]);
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
                    sql_query("insert into chatsessionlog set sessionid=?, userid=?, tipus='unread'", [$chatSession, $user["userid"]]);
                }
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


    public function showPage()
    {
        if (!$this->adminUser->chatAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        if ($this->adminUtils->settings->chatStatus == 1) {
            echo "<div style='margin-bottom:10px;color:green;'>Az ügyfélszolgálat chat jelenleg <strong>online</strong> <a href='index.php?page=chat&chatstatus=0'>kikapcsolás</a></div>";
        } else {
            echo "<div style='color:red;margin-bottom:10px;'>A ügyfélszolgálat chat jelenleg <strong>offline</strong> <a href='index.php?page=chat&chatstatus=1'>bekapcsolás</a></div>";
        }

        /*
        echo "<div style='display:table-cell;vertical-align: top;'>";

        echo "<div id='chatsessionlist'>";
        echo $this->chatSessionList();
        echo "</div>";

        echo "</div>";

        echo "<div style='display:table-cell;vertical-align:top;background:#ccc;padding:10px;'>";
        echo "<div style='display:table-cell;vertical-align:bottom;background:#fff;height:400px;'>";
        echo "<div id='chatsessionitems' style='display:inline-block;padding:5px;width:700px;max-height:300px;overflow:auto'></div>";
        echo "</div>";

        echo "<div style='margin-top:5px;'>";
        echo "<input id='chatmessagetext' type='text' placeholder='Írd be az üzenetet...' value='' style='width:680px;border:1px solid #ccc;'/>&nbsp;&nbsp;<a title='Üzenet elküldése' href='#' onclick='sendChatMessage();return false;'><i style='font-size:16px;' class='fas fa-arrow-right'></i></a>";
        echo "</div>";

        echo "<div style='padding:10px 0px;'><strong>Gyorsszövegek:</strong></div>";
        foreach ($this->fastTexts as $fastText) {
            $fastText = str_replace("#name#", $this->adminUser->user["nev"], $fastText);
            echo "<div><a href='#' onclick='chatFastText(\"{$fastText}\");return false;'>{$fastText}</a></div>";
        }

        echo "<div style='border-top:1px solid #888;padding-top:10px;margin-top:10px;'>";
        echo "<a onclick='return confirm(\"Biztos lezárod?\");' href='index.php?page=chat&closechat'>Chat lezárása</a>";
        echo "</div>";

        echo "</div>";
        */
    }


    private function chatSessionRow($chatSession):string {
        $html = "";

        $lastItem = sql_query("select * from chat where chatsessionid=? and userid>-1 order by datum desc limit 1", [$chatSession["id"]])->fetch(PDO::FETCH_ASSOC);
        $lastItemText = "Üres chat ablak, írj bele valamit";
        if (!empty($lastItem)) {
            if (strtotime("now") - strtotime($lastItem["datum"]) > 3600 * 24) {
                $time = date("Y.m.d H:i", strtotime($lastItem["datum"]));
            } else {
                $time = date("H:i", strtotime($lastItem["datum"]));
            }
            $lastItemText = "{$time} {$lastItem["message"]}";
        }
        if ($chatSession["pub"] == 0 && $chatSession["external"] == 0 && empty($chatSession["sessionuserid"])) {
            $lastItemText = "Hívj meg felhasználókat a chat ablakba";
        }

        if ($chatSession["external"] == 1) {
            if ($chatSession["closed"] != "0000-00-00 00:00:00") {
                $lock = "<div style='float:left;font-size:20px;padding-right:5px;padding-top:3px;'><i title='lezárva' class='fas fa-lock'></i></div>";
            } else {
                $lock = "<div style='float:left;font-size:20px;padding-right:5px;padding-top:3px;opacity: .5;'><i onclick='closeChatSession(\"{$chatSession["id"]}\");' title='chat lezárása' class='fas fa-lock-open'></i></a></div>";
            }
            $title = $chatSession["domain"];
            $backgroundColor = self::EXTERNAL_CHAT_BACKGROUND;
        } else {
            $title = $chatSession["title"];
            $backgroundColor = self::PUBLIC_CHAT_BACKGROUND;
            if ($chatSession["pub"] == 0) {
                $backgroundColor = self::PRIVATE_CHAT_BACKGROUND;
            }
            $lock = "<div style='float:left;font-size:20px;padding-right:5px;padding-top:3px;opacity: .7;'><i onclick='openChatSessionEditor(\"sessioneditor{$chatSession["id"]}\", \"{$chatSession["id"]}\", 0);' title='lezárva' class='fas fa-gear'></i></div>";
        }

        $html.= "<div class='chatsessionlistitem".($_SESSION["openedsession"] == $chatSession["id"] ? " chatsessionlistitemaktiv":"")."' style='background:{$backgroundColor}' onclick='loadChatWindow({$chatSession["id"]}, true);'>";
        $html.= "<div style='margin-right: 10px;'>";
        //if ($lastItem["readdate"] == "0000-00-00 00:00:00" && $lastItem["userid"] == 0) {
        //    $html.= "<div style='float:left;font-size:20px;padding-right:5px;padding-top:6px;color:red'><i title='új üzenet' class='fas fa-comment'></i></div>";
        //}

        $html.= "{$lock}<div>{$title}</div>";
        $html.= "<div style='font-size: 11px;color:#777;white-space: nowrap;overflow: hidden;'>{$lastItemText}</div>";
        $html.= "</div>";
        $html.= "</div>";
        $html.= "<br clear='all' />";
        $html.= "<div class='sessioneditordiv'  id='sessioneditor{$chatSession["id"]}'></div>";

        return $html;
    }



    private function chatMainWindow($sessionId):string {
        if ($sessionId == 0) {
            $sessionId = $_SESSION["openedsession"];
        } else {
            $_SESSION["openedsession"] = $sessionId;
        }

        $chatUsers = $this->chatService->getChatUsers($sessionId, $this->adminUser);

        $html = "";

        $html.= "<div style='border:1px solid #ccc;background:white;'>";

        $html.= "<div style='display:table;width:100%;background:#8792ae;color:white;'>";
        $html.= "<div style='display:table-cell;vertical-align: middle;padding:8px;font-size: 14px;'><i class='fa-solid fa-comment'></i>&nbsp;&nbsp;".mb_substr(empty($chatUsers["text"]) ? "Kivel szeretnél bezsélgetni?":$chatUsers["text"], 0, 40)."</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;padding:10px;width:5px;font-size: 18px;'><i style='cursor: pointer;' onclick='hideChatPopup();return false;' class='fa-solid fa-circle-xmark'></i></div>";
        $html.= "</div>";

        $html.= "<div style='display:table-cell;height:400px;vertical-align: bottom;'>";

        if (empty($chatUsers["text"])) {
            $html.= "<div style='display:table-cell;height:400px;vertical-align: bottom;'>";
            $html.= "<div style='display:inline-block;padding:5px;width:350px;max-height:500px;overflow:auto;border:1px solid #000;' id='sessionusers{$sessionId}'>".$this->chatService->showUserButtons($sessionId)."</div>";
            $html.= "</div>";
        } else {
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