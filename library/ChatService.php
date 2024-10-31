<?php

class ChatService {

    public function getChatSessions($userId) {
        return sql_query("SELECT s.*, su2.userid AS sessionuserid, GROUP_CONCAT(u.nev separator ', ') AS partnername FROM chatsession s 
            LEFT JOIN chatsessionusers su ON su.sessionid=s.id AND su.userid=:userid
            LEFT JOIN chatsessionusers su2 ON su2.sessionid=s.id
            LEFT JOIN users u ON u.id=su2.userid
            WHERE s.pub=0 AND s.external=0 AND (s.createdby=:userid OR su.id IS NOT NULL) 
            GROUP BY s.id 
            ORDER BY s.created DESC", ["userid" => $userId])->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getChatUsers($chatSessionId, AdminUser $adminUser):string {
        $usersData = sql_query("SELECT s.*, su2.userid AS sessionuserid, uc.nev as creatorname, GROUP_CONCAT(u.nev separator ',') AS partnername FROM chatsession s 
            LEFT JOIN chatsessionusers su ON su.sessionid=s.id AND su.userid=:userid
            LEFT JOIN chatsessionusers su2 ON su2.sessionid=s.id
            LEFT JOIN users uc ON uc.id=s.createdby
            LEFT JOIN users u ON u.id=su2.userid
            WHERE (s.createdby=:userid OR su.id IS NOT NULL) and s.id=:sess 
            GROUP BY s.id 
            ORDER BY s.created DESC", ["sess" => $chatSessionId, "userid" => $adminUser->user["id"]])->fetch(PDO::FETCH_ASSOC);


        $users = [];
        $usersText = "";
        if ($adminUser->user["id"] == $usersData["creatorid"]) {
            $users[] = $usersData["creatorname"].", ";
        }

        foreach (explode(",", $usersData["partnername"]) as $partner) {
            if ($adminUser->user["nev"] != $partner) {
                $users[] = $partner;
            }
        }

        //$usersText .= $usersData["partnername"];
        return implode(", ", $users);
    }


    public function getSessionListHTML($userId):string {
        $html = "";

        $chatSessions = $this->getChatSessions($userId);
        foreach ($chatSessions as $chatSession) {
            $html.= $this->chatSessionRow($chatSession);
        }

        return $html;
    }

    private function chatSessionRow($chatSession):string {
        $html = "";

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
            $title = $chatSession["partnername"];
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
        //$html.= "<br clear='all' />";
        //$html.= "<div class='sessioneditordiv'  id='sessioneditor{$chatSession["id"]}'></div>";

        return $html;
    }


    public function showChatMessages($chatSessionId):string {
        $html = "";

        if ($chatSessionId != 0) {
            $messages = sql_query("select c.*, u.username from chat c
                left join users u on u.id=c.userid
                where c.chatsessionid=? order by c.datum", [$chatSessionId]);
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
        }

        return $html;
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