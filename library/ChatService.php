<?php

class ChatService {

    public function __construct() {
        if (!isset($_SESSION["openedsession"])) {
            $_SESSION["openedsession"] = 0;
        }
    }

    public function getChatSessions($userId) {
        return sql_query("SELECT s.*, su2.userid AS sessionuserid, uc.nev as creatorname, GROUP_CONCAT(u.nev separator ', ') AS partnername FROM chatsession s 
            LEFT JOIN chatsessionusers su ON su.sessionid=s.id AND su.userid=:userid
            LEFT JOIN chatsessionusers su2 ON su2.sessionid=s.id
            LEFT JOIN users uc ON uc.id=s.createdby
            LEFT JOIN users u ON u.id=su2.userid and u.id<>:userid
            WHERE s.pub=0 AND s.external=0 AND (s.createdby=:userid OR su.id IS NOT NULL) 
            GROUP BY s.id 
            ORDER BY s.created DESC, u.nev", ["userid" => $userId])->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getChatUsers($chatSessionId, AdminUser $adminUser):array {
        $usersData = sql_query("SELECT s.*, su2.userid AS sessionuserid, uc.nev as creatorname, GROUP_CONCAT(u.nev separator ',') AS partnername FROM chatsession s 
            LEFT JOIN chatsessionusers su ON su.sessionid=s.id AND su.userid=:userid
            LEFT JOIN chatsessionusers su2 ON su2.sessionid=s.id
            LEFT JOIN users uc ON uc.id=s.createdby
            LEFT JOIN users u ON u.id=su2.userid and u.id<>:userid
            WHERE (s.createdby=:userid OR su.id IS NOT NULL) and s.id=:sess 
            GROUP BY s.id 
            ORDER BY s.created DESC", ["sess" => $chatSessionId, "userid" => $adminUser->user["id"]])->fetch(PDO::FETCH_ASSOC);

        return ["valami" => "", "text" => implode(", ", $this->processUsersText($usersData))];
    }

    public function processUsersText($usersData):array {
        $users = [];
        if (!empty($usersData["partnername"])) {
            foreach (explode(",", $usersData["partnername"]) as $partner) {
                $users[] = trim($partner);
            }
        }

        sort($users);

        return $users;
    }

    public function getSessionListHTML($userId):string {
        $html = "";

        $chatSessions = $this->getChatSessions($userId);
        foreach ($chatSessions as $chatSession) {
            if (!empty($chatSession["partnername"])) {
                $html .= $this->chatSessionRow($chatSession);
            }
        }

        if ($_SESSION["adminuser"]["username"] == "jns") {
            $html .= $this->chatSessionRow([]);
        }

        return $html;
    }

    private function chatSessionRow($chatSession):string {
        $html = "";

        if (empty($chatSession)) {
            $html.= "<div class='chatsessionlistitemmain' style='background:".AdminChatPage::PRIVATE_CHAT_BACKGROUND."' onclick='newChatSession();'>";
            $html.= "<div style='padding:10px;' title='Új chat nyitása'>";
            $html.= "<div style='white-space: nowrap;overflow: hidden;text-align: center;'>+ Új chat ablak</div>";
            $html.= "<div style='font-size: 11px;color:#777;white-space: nowrap;overflow: hidden;text-align: center;'>válassz felhasználót...</div>";
            $html.= "</div>";
            $html.= "</div>";
            return $html;
        }

        if (empty($chatSession["partnername"])) {
            $chatSession["partnername"] = "Nincs felhasználó";
        }

        $lastItem = sql_query("select * from chat where chatsessionid=? and userid>-1 order by datum desc limit 1", [$chatSession["id"]])->fetch();
        $lastItemText = "Üres chat ablak";
        if (!empty($lastItem)) {
            if (strtotime("now") - strtotime($lastItem["datum"]) > 3600 * 24) {
                $time = date("Y.m.d H:i", strtotime($lastItem["datum"]));
            } else {
                $time = date("H:i", strtotime($lastItem["datum"]));
            }
            $lastItemText = "{$time} ".mb_substr($lastItem["message"], 0, 50);
        }
        if ($chatSession["pub"] == 0 && $chatSession["external"] == 0 && empty($chatSession["sessionuserid"])) {
            $lastItemText = "Hívj meg felhasználókat a chat ablakba";
        }

        $lock = "";
        if ($chatSession["external"] == 1) {
            if ($chatSession["closed"] != "0000-00-00 00:00:00") {
                //$lock = "<div style='float:left;font-size:20px;padding-right:5px;padding-top:3px;'><i title='lezárva' class='fas fa-lock'></i></div>";
            } else {
                //$lock = "<div style='float:left;font-size:20px;padding-right:5px;padding-top:3px;opacity: .5;'><i onclick='closeChatSession(\"{$chatSession["id"]}\");' title='chat lezárása' class='fas fa-lock-open'></i></a></div>";
            }
            $title = $chatSession["domain"];
            $backgroundColor = AdminChatPage::EXTERNAL_CHAT_BACKGROUND;
        } else {
            //$title = $chatSession["partnername"];
            $title = implode(", ", $this->processUsersText($chatSession));
            $backgroundColor = AdminChatPage::PUBLIC_CHAT_BACKGROUND;
            if ($chatSession["pub"] == 0) {
                $backgroundColor = AdminChatPage::PRIVATE_CHAT_BACKGROUND;
            }
            //$lock = "<div style='float:left;font-size:20px;padding-right:5px;padding-top:3px;opacity: .7;'><i onclick='openChatSessionEditor(\"sessioneditor{$chatSession["id"]}\", \"{$chatSession["id"]}\", 0);' title='lezárva' class='fas fa-gear'></i></div>";
        }


        $html.= "<div class='chatsessionlistitemmain".($_SESSION["openedsession"] == $chatSession["id"] ? " chatsessionlistitemaktiv":"")."' style='background:{$backgroundColor}' onclick='loadChatWindowMain({$chatSession["id"]}, true);'>";
        $html.= "<div style='padding:10px;' title='{$title}'>";
        //if ($lastItem["readdate"] == "0000-00-00 00:00:00" && $lastItem["userid"] == 0) {
            //$html.= "<div style='float:left;font-size:20px;padding-right:5px;padding-top:6px;color:red'><i title='új üzenet' class='fas fa-comment'></i></div>";
        //}

        $html.= "{$lock}<div style='white-space: nowrap;overflow: hidden;'>{$title}</div>";
        $html.= "<div style='font-size: 11px;color:#777;white-space: nowrap;overflow: hidden;'>{$lastItemText}</div>";
        $html.= "</div>";
        $html.= "</div>";

        return $html;
    }


    public function showChatMessages($chatSessionId):string {
        $html = "";

        if ($chatSessionId != 0) {
            $messages = sql_query("select c.*, u.username, u.nev from chat c
                left join users u on u.id=c.userid
                where c.chatsessionid=? order by c.datum", [$chatSessionId]);
            foreach ($messages as $message) {
                if ($message["userid"] != 0) {
                    if ($_SESSION["adminuser"]["id"] != $message["userid"]) {
                        $html .= "<div style='display:table;'>";
                        $html .= "<div style='display:table-cell;vertical-align: top;font-size:16px;padding:10px 10px 0px 0px;'>";
                        $html .= $this->chatMonogram($message["nev"]);
                        $html .= "</div>";
                        $html .= "<div style='display:table-cell;vertical-align: top;padding:10px 0px 0px 0px;'>";
                        $html .= "<div class='chatusermessage' style='display:inline-block;background:#f0f0f0;border-radius: 10px;color:black;padding:3px 10px;'>{$message["message"]}</div><br clear='all' />";
                        $html .= "<span class='chatstatus'>{$message["username"]} " . $this->chatTimeString($message["datum"]) . "</span></span>";
                        $html .= "</div>";
                        $html .= "</div>";
                    } else {
                        $html .= "<div style='padding:10px 0px 0px 0px;text-align: right;'>";
                        $html .= "<div class='chatusermessage' style='display:inline-block;background:#f0f0f0;border-radius: 10px;color:black;padding:3px 10px;'>{$message["message"]}</div><br clear='all' />";
                        $html .= "<span class='chatstatus'>{$message["username"]} " . $this->chatTimeString($message["datum"]) . "</span></span>";
                        $html .= "</div>";
                    }
                } else {
                    $html .= "<div style='display:table-row;'>";
                    if ($_SESSION["adminuser"]["id"] != $message["userid"]) {
                        $html .= "<div style='display:table-cell;vertical-align: top;font-size: 24px;padding:10px 10px 0px 0px;'>";
                        $html .= "<i class='fas fa-user'></i>";
                        $html .= "</div>";
                    }
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
        }

        return $html;
    }

    private function chatMonogram($nev):string {
        $monogram = "";
        foreach (explode(" ", $nev) as $value) {
            $monogram .= substr($value, 0, 1);
            if (strlen($monogram) == 2) {
                break;
            }
        }
        return "<div style='font-family:Courier;background:#3ba9b8;color:white;padding:5px 7px;border-radius: 100px;text-transform:uppercase;font-weight: bold;'>{$monogram}</div>";
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

    private array $selectedUsers;
    public function showUserButtons($sessionId):string {
        $this->selectedUsers = [];
        $sessionUsers = sql_query("select * from chatsessionusers where sessionid=?", [$sessionId])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sessionUsers as $sessionUser) {
            $this->selectedUsers[] = $sessionUser["userid"];
        }

        $buttons = "";
        if ($sessionData = sql_query("select * from chatsession where id=?", [$sessionId])->fetch(PDO::FETCH_ASSOC)) {
            $buttons.= "<div style='margin:5px 0px;font-weight: bold;'>Online felhasználók:</div>";
            $buttons.= $this->userButtons($sessionData, sql_query("select * from users u where u.status>0 and u.username<>'' and lastlogin>date_sub(now(), interval 1 minute) order by trim(nev)")->fetchAll(PDO::FETCH_ASSOC));
            $buttons.= "<div style='margin:5px 0px;font-weight: bold;'>Offline felhasználók:</div>";
            $buttons.= $this->userButtons($sessionData, sql_query("select * from users u where u.status>0 and u.username<>'' and lastlogin<=date_sub(now(), interval 1 minute) order by trim(nev)")->fetchAll(PDO::FETCH_ASSOC));
        }
        return $buttons;
    }

    private function userButtons($sessionData, $users):string {
        $buttons = "";
        foreach ($users as $user) {
            if ($user["id"] == $_SESSION["adminuser"]["id"]) {
                continue;
            }
            $aktiv = in_array($user["id"], $this->selectedUsers) ? 1:0;
            $class = $aktiv==1 ? "serviceselected":"servicenotselected";
            $buttons.= "<a data-aktiv='{$aktiv}' data-chatsessionid='{$sessionData["id"]}' data-userid='{$user["id"]}' title='' class='{$class}' href='#' onclick='toggleChatSessionUser(this);return false;'>".trim($user["nev"])."</a> ";
        }
        return $buttons;
    }
}