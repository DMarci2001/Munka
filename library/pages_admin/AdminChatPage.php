<?php

class AdminChatPage extends AdminCorePage {
    const PUBLIC_CHAT_BACKGROUND = "#d3fddc";
    const PRIVATE_CHAT_BACKGROUND = "#fdfdd3";
    const EXTERNAL_CHAT_BACKGROUND = "#eee";

    private array $fastTexts = [
        "Jó napot kívánok, #name# vagyok, miben segíthetek!",
        "Kis türelmét kérem!",
        "Kérem adja meg a nevét és a TAJ számát!",
        "Minden jót, viszontlátásra!",
    ];

    public function __construct()
    {
        parent::__construct();
        $GLOBALS["javascript"][] = "adminchat.js?v=".date("YmdHi");

        if (!isset($_SESSION["openedsession"])) {
            $_SESSION["openedsession"] = 0;
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
                echo "<div style='width: 300px;' id='sessionusers{$sessionData["id"]}'>".$this->showUserButtons($sessionData["id"])."</div>";
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

            echo $this->showUserButtons($sessionId);
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


            if ($sessionId != 0) {
                $messages = sql_query("select c.*, u.username from chat c
                left join users u on u.id=c.userid
                where c.chatsessionid=? order by c.datum", [$sessionId]);
                foreach ($messages as $message) {
                    if ($message["userid"] != 0) {
                        $html .= "<div style='display:table-row;'>";
                        $html .= "<div style='display:table-cell;vertical-align: top;font-size: 24px;padding:10px 10px 0px 0px;'>";
                        $html .= "<i class='fa-solid fa-user-doctor'></i>";
                        $html .= "</div>";
                        $html .= "<div style='display:table-cell;vertical-align: top;padding:10px 0px 0px 0px;'>";
                        $html .= "<div class='chatusermessage'>{$message["message"]}</div>";
                        $html .= "<span class='chatstatus'>{$message["username"]} " . $this->chatTimeString($message["datum"]) . "</span></span>";
                        $html .= "</div>";
                        $html .= "</div>";
                    } else {
                        $html .= "<div style='display:table-row;'>";
                        $html .= "<div style='display:table-cell;vertical-align: top;font-size: 24px;padding:10px 10px 0px 0px;'>";
                        $html .= "<i class='fas fa-user'></i>";
                        $html .= "</div>";
                        $html .= "<div style='display:table-cell;vertical-align: top;padding:10px 0px 0px 0px;'>";
                        $html .= "<div class='chatadminmessage'>{$message["message"]}</div>";
                        $html .= "<span class='chatstatus'>Felhasználó " . $this->chatTimeString($message["datum"]) . "</span></span>";
                        $html .= "</div>";
                        $html .= "</div>";
                    }
                    if ($message["readdate"] == "0000-00-00 00:00:00") {
                        sql_query("update chat set readdate=now() where id=?", [$message["id"]]);
                    }
                }
            } else {
                $html.= "<div style='font-weight: bold;text-align: center;padding-bottom: 50px;'><i style='font-size: 60px;color:red;opacity: .5;' class='fas fa-triangle-exclamation'></i><br/><br/>Válassz a bal oldali chat szobák közül!</div>";
            }



            Utils::jsonOut(["html" => $html]);
        }

        if (isset($_POST["chatSessionList"])) {
            Utils::jsonOut(["html" => $this->chatSessionList()]);
        }

        if (isset($_POST["sendmessage"])) {
            $chatSession = $_SESSION["openedsession"];
            $message = strip_tags($_POST["message"]);

            if ($chatSession != 0) {
                sql_query("insert into chat set datum=now(), chatsessionid=?, message=?, userid=?", [$chatSession, $message, $this->adminUser->user["id"]]);
            }

            die("sent");
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


    private function showUserButtons($sessionId):string {
        $selectedUsers = [];
        $sessionUsers = sql_query("select * from chatsessionusers where sessionid=?", [$sessionId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sessionUsers as $sessionUser) {
            $selectedUsers[] = $sessionUser["userid"];
        }

        $buttons = "<div style='margin:5px 0px;font-weight: bold;'>Meghívott felhasználók:</div>";
        if ($sessionData = sql_query("select * from chatsession where id=?", [$sessionId])->fetch(PDO::FETCH_ASSOC)) {
            $users = sql_query("select * from users u where u.status>0 and u.username<>'' order by trim(username)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users as $user) {
                $aktiv = in_array($user["id"], $selectedUsers) ? 1:0;
                $class = $aktiv==1 ? "serviceselected":"servicenotselected";
                $arr = explode("@", $user["username"]);
                $username = array_shift($arr);
                $buttons .= "<a data-aktiv='{$aktiv}' data-chatsessionid='{$sessionData["id"]}' data-userid='{$user["id"]}' title='' class='{$class}' href='#' onclick='toggleChatSessionUser(this);return false;'>{$username}</a> ";
            }
        }
        return $buttons;
    }

    public function showPage()
    {
        if (!$this->adminUser->chatAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        if ($this->adminUtils->settings->chatStatus == 1) {
            echo "<div style='margin-bottom:10px;color:green;'>A chat jelenleg <strong>online</strong> <a href='index.php?page=chat&chatstatus=0'>kikapcsolás</a></div>";
        } else {
            echo "<div style='color:red;margin-bottom:10px;'>A chat jelenleg <strong>offline</strong> <a href='index.php?page=chat&chatstatus=1'>bekapcsolás</a></div>";
        }

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
        if ($lastItem["readdate"] == "0000-00-00 00:00:00" && $lastItem["userid"] == 0) {
            $html.= "<div style='float:left;font-size:20px;padding-right:5px;padding-top:6px;color:red'><i title='új üzenet' class='fas fa-comment'></i></div>";
        }


        $html.= "{$lock}<div>{$title}</div>";
        $html.= "<div style='font-size: 11px;color:#777;white-space: nowrap;overflow: hidden;'>{$lastItemText}</div>";
        $html.= "</div>";
        $html.= "</div>";
        $html.= "<br clear='all' />";
        $html.= "<div class='sessioneditordiv'  id='sessioneditor{$chatSession["id"]}'></div>";


        return $html;
    }

    private function chatSessionList():string {
        $html = "";

        //publikus ablakok
        $chatSessions = sql_query("SELECT s.* FROM chatsession s WHERE s.pub=1 and s.external=0 ORDER BY s.created DESC")->fetchAll(PDO::FETCH_ASSOC);
        $html.= "<div style='font-weight: bold;padding:10px;font-size: 16px;'>Publikus chat ablakok<br/><a onclick='openChatSessionEditor(\"newsessionpublic\", 0, 1);return false;' href='#' style='font-size: 12px;'>+ új publikus chat ablak létrehozása</a></div>";
        $html.= "<div class='sessioneditordiv' id='newsessionpublic'></div>";
        foreach ($chatSessions as $chatSession) {
            $html.= $this->chatSessionRow($chatSession);
        }

        //privát ablakok
        $chatSessions = sql_query("SELECT s.*, su2.id AS sessionuserid FROM chatsession s 
            LEFT JOIN chatsessionusers su ON su.sessionid=s.id AND su.`userid`=:userid
            LEFT JOIN chatsessionusers su2 ON su2.sessionid=s.id
            WHERE s.pub=0 AND s.external=0 AND (s.`createdby`=:userid OR su.id IS NOT NULL) GROUP BY s.id ORDER BY s.created DESC", ["userid" => $this->adminUser->user["id"]])->fetchAll(PDO::FETCH_ASSOC);
        $html.= "<div style='font-weight: bold;padding:10px;font-size: 16px;'>Privát chat ablakok<br/><a onclick='openChatSessionEditor(\"newsessionprivate\", 0, 0);return false;' href='#' style='font-size: 12px;'>+ új privát chat ablak létrehozása</a></div>";
        $html.= "<div class='sessioneditordiv'  id='newsessionprivate'></div>";
        foreach ($chatSessions as $chatSession) {
            $html.= $this->chatSessionRow($chatSession);
        }

        //külső chat ablakok
        $chatSessions = sql_query("SELECT s.* FROM chat c 
            LEFT JOIN chatsession s ON s.id=c.chatsessionid
            WHERE c.userid=0 AND s.external=1
            AND (s.closed='0000-00-00 00:00:00' AND created>DATE_SUB(NOW(), INTERVAL 300 DAY)) OR closed>DATE_SUB(NOW(), INTERVAL 300 DAY) and s.created>DATE_SUB(now(), interval 300 day)
            GROUP BY c.chatsessionid
            ORDER BY s.created DESC")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($chatSessions)) {
            $html.= "<div style='font-weight: bold;padding:10px;font-size: 16px;'>Nyitott külső chat ablakok</div>";
            foreach ($chatSessions as $chatSession) {
                $html.= $this->chatSessionRow($chatSession);
            }
        }

        return $html;
    }

}