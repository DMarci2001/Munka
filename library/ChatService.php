<?php

class ChatService {

    private AdminUser $adminUser;

    public function __construct(AdminUser $adminUser) {
        $this->adminUser = $adminUser;
        if (!isset($_SESSION["openedsession"])) {
            $_SESSION["openedsession"] = 0;
        }
    }

    public function getChatSessions($userId, $onlyActive = true) {
        $w = $onlyActive ? "AND su.active=1" : "";

        return sql_query("SELECT s.*, su.active, su2.userid AS sessionuserid, uc.nev as creatorname, GROUP_CONCAT(u.nev separator ', ') AS partnername FROM chatsession s 
            LEFT JOIN chatsessionusers su ON su.sessionid=s.id AND su.userid=:userid
            LEFT JOIN chatsessionusers su2 ON su2.sessionid=s.id
            LEFT JOIN users uc ON uc.id=s.createdby
            LEFT JOIN users u ON u.id=su2.userid and u.id<>:userid
            WHERE s.pub=0 AND s.external=0 AND (s.createdby=:userid OR su.id IS NOT NULL) {$w}
            GROUP BY s.id 
            ORDER BY s.created DESC, u.nev", ["userid" => $userId])->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getChatUsers($chatSessionId):array {
        $usersData = sql_query("SELECT s.*, su2.userid AS sessionuserid, uc.nev as creatorname, GROUP_CONCAT(u.nev separator ',') AS partnername FROM chatsession s 
            LEFT JOIN chatsessionusers su ON su.sessionid=s.id AND su.userid=:userid
            LEFT JOIN chatsessionusers su2 ON su2.sessionid=s.id
            LEFT JOIN users uc ON uc.id=s.createdby
            LEFT JOIN users u ON u.id=su2.userid and u.id<>:userid
            WHERE (s.createdby=:userid OR su.id IS NOT NULL) and s.id=:sess 
            GROUP BY s.id 
            ORDER BY s.created DESC", ["sess" => $chatSessionId, "userid" => $this->adminUser->user["id"]])->fetch(PDO::FETCH_ASSOC);

        $group = 0;
        $groupTitle = "";
        if (isset($usersData["csoport"]))  {
            $group = $usersData["csoport"];
            $groupTitle = ($usersData["csoport"] == 1 ? $usersData["title"]:"");
        }

        return ["valami" => "", "group" => $group, "groupTitle" => $groupTitle, "text" => implode(", ", $this->processUsersText($usersData))];
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
            if (!empty($chatSession["partnername"]) && $chatSession["active"] == 1) {
                $html .= $this->chatSessionRow($chatSession);
            }
        }

        $html .= $this->chatSessionRow([]);

        return $html;
    }

    private function chatSessionRow($chatSession):string {
        $html = "";

        if (empty($chatSession)) {
            $html.= "<div class='chatsessionlistitemmain' style='background:".AdminChatPage::PRIVATE_CHAT_BACKGROUND."'>";
            $html.= "<div style='padding:10px;' title='Új chat nyitása'>";
            $html.= "<div style='white-space: nowrap;overflow: hidden;text-align: center;'><a href='#' onclick='newChatSession();return false;'>+ új chat</a> | <a href='index.php?page=chat'>chat history</a></div>";
            //$html.= "<div style='font-size: 11px;color:#777;white-space: nowrap;overflow: hidden;text-align: center;'>válassz felhasználót...</div>";
            $html.= "</div>";
            $html.= "</div>";
            return $html;
        }

        if (empty($chatSession["partnername"])) {
            $chatSession["partnername"] = "Nincs felhasználó";
        }

        $title = implode(", ", $this->processUsersText($chatSession));
        $backgroundColor = AdminChatPage::PUBLIC_CHAT_BACKGROUND;
        if ($chatSession["pub"] == 0) {
            $backgroundColor = AdminChatPage::PRIVATE_CHAT_BACKGROUND;
        }

        $unChecked = sql_query("select id from chatsessionlog where tipus='unread' and userid=? and sessionid=? and checked=0", [$this->adminUser->user["id"], $chatSession["id"]])->fetch(PDO::FETCH_ASSOC);

        if ($chatSession["csoport"] == 1) {
            $title = "{$chatSession["title"]} - ".(substr_count($title, ', ')+1)." fő: {$title}";
        }

        $lastItemText = $this->lastItem($chatSession);

        $html.= "<div class='chatsessionlistitemmain".($unChecked ? " newchatpulse":"").($_SESSION["openedsession"] == $chatSession["id"] ? " chatsessionlistitemaktiv":"")."' style='background:{$backgroundColor}' onclick='loadChatWindowMain({$chatSession["id"]}, true);'>";
        $html.= "<div style='padding:10px;' title='{$title}'>";
        $html.= "<div style='float:right;background:{$backgroundColor};padding-left:4px;cursor:pointer;' onclick='window.event.stopPropagation();archiveChatWindow({$chatSession["id"]});' title='bezárás'><i class='fa-solid fa-circle-xmark'></i></div>";
        $html.= "<div style='white-space: nowrap;overflow: hidden;'>{$title}</div>";
        $html.= "<div style='font-size: 11px;color:#777;white-space: nowrap;overflow: hidden;'>{$lastItemText}</div>";
        $html.= "</div>";
        $html.= "</div>";

        return $html;
    }

    public function chatSessionBox($chatSession):string {
        $html = "";

        $typeTag = $chatSession["csoport"] == 1 ? "<span style='color:#fff;font-size:11px;padding:3px 5px;border-radius:5px;background:#6ea8fe;'>CSOPORT</span>" : "<span style='color:#fff;font-size:11px;padding:3px 5px;border-radius:5px;background:#27ae60;'>PRIVÁT</span>";

        if (empty($chatSession["partnername"])) {
            $chatSession["partnername"] = "Nincs felhasználó";
        }

        $title = implode(", ", $this->processUsersText($chatSession));
        $backgroundColor = AdminChatPage::PUBLIC_CHAT_BACKGROUND;
        if ($chatSession["pub"] == 0) {
            $backgroundColor = AdminChatPage::PRIVATE_CHAT_BACKGROUND;
        }

        if ($chatSession["csoport"] == 1) {
            $title = "{$chatSession["title"]} - ".(substr_count($title, ', ')+1)." fő: {$title}";
        }

        $lastItemText = $this->lastItem($chatSession);

        $html.= "<div class='chatsessionlistitemmain' style='background:{$backgroundColor};margin:0px 10px 10px 0px;' onclick='loadChatWindowMain({$chatSession["id"]}, true);'>";
        $html.= "<div style='padding:10px;'>";
        //$html.= "<div style='float:right;background:{$backgroundColor};padding-left:4px;cursor:pointer;' onclick='window.event.stopPropagation();archiveChatWindow({$chatSession["id"]});' title='bezárás'><i class='fa-solid fa-circle-xmark'></i></div>";
        $html.= "<div style=''>{$title}</div>";
        $html.= "<div style='font-size: 11px;color:#777;'>{$lastItemText}</div>";
        $html.= "<div>{$typeTag}</div>";
        $html.= "</div>";
        $html.= "</div>";

        return $html;
    }

    private function lastItem($chatSession):string {
        $lastItem = sql_query("select * from chat where chatsessionid=? and userid>-1 order by datum desc limit 1", [$chatSession["id"]])->fetch();
        $lastItemText = "Még nem írt senki";
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
        return strip_tags($lastItemText);
    }


    public function uploadedTempFileName($sessionId, $extension):string {
        return Booking_Constants::DOCUMENT_PATH."chatuploadimage{$this->adminUser->user["id"]}_{$sessionId}.{$extension}";
    }

    public function uploadUserImageFile($sessionId) {
        $extension = "jpg";
        if (substr_count(strtolower($_SERVER["CONTENT_TYPE"]), "png")) {
            $extension = "png";
        }

        $image = file_get_contents("php://input");
        file_put_contents($this->uploadedTempFileName($sessionId, $extension), $image);
    }

    public function showChatUploads($sessionId):string {
        $html = "";

        $file = $this->getUploadedTempFileName($sessionId);

        if (!empty($file["file"])) {
            $html.= "<div style='text-align: center;'>";
            $html.= "<div><img style='max-width:200px;max-height:80px;border:1px solid #e0e0e0;' src='index.php?page=chat&displaytempimage&sessionid={$sessionId}' /></div>";
            $html.= "<div style='padding-top: 5px;'><a onclick='finalizeImageUpload();return false;' href='#'>beszúrás</a> | <a onclick='deleteUploadedTempFile();return false;' href='#'>mégse</a></div>";
            $html.= "</div>";
        }
        return $html;
    }


    public function deleteUploadedTempFile($sessionId) {
        $file = $this->getUploadedTempFileName($sessionId);
        unlink($file["file"]);
    }

    public function getUploadedTempFileName($sessionId):array {
        $checkJPG = $this->uploadedTempFileName($sessionId, "jpg");
        $checkPNG = $this->uploadedTempFileName($sessionId, "png");

        $file = $header = "";
        if (is_file($checkJPG)) {
            $file = $checkJPG;
            $header = "image/jpeg";
        } else {
            if (is_file($checkPNG)) {
                $file = $checkPNG;
                $header = "image/png";
            }
        }

        return ["file" => $file, "header" => $header];
    }

    public function showChatMessages($chatSessionId):string {
        $html = "";

        if ($chatSessionId != 0) {
            sql_query("SET CHARACTER SET utf8mb4");

            $messages = sql_query("SELECT c.id, c.datum, c.chatsessionid, c.userid, c.readdate, c.message COLLATE 'utf8mb4_unicode_ci' AS message, u.username, u.nev, GROUP_CONCAT(l.userid) AS olvasta from chat c
                LEFT JOIN users u ON u.id=c.userid
                LEFT JOIN chatsessionlog l ON l.messageid=c.id AND l.checked=1
                WHERE c.chatsessionid=? GROUP BY c.id ORDER BY c.datum limit 100", [$chatSessionId])->fetchAll(PDO::FETCH_ASSOC);

            sql_query("SET CHARACTER SET utf8");

            foreach ($messages as $message) {
                if ($message["userid"] != 0) {
                    if ($this->adminUser->user["id"] != $message["userid"]) {
                        $html .= "<div style='display:table;'>";
                        $html .= "<div style='display:table-cell;vertical-align: top;font-size:16px;padding:10px 10px 0px 0px;'>";
                        $html .= $this->chatMonogram($message["nev"]);
                        $html .= "</div>";
                        $html .= "<div style='display:table-cell;vertical-align: top;padding:10px 0px 0px 0px;'>";
                        $html .= "<div class='chatusermessage' style='display:inline-block;background:#f0f0f0;border-radius: 10px;color:black;padding:3px 10px;'>{$this->processMessage($message["message"])}</div><br clear='all' />";
                        $html .= "<span class='chatstatus'>{$message["username"]} " . $this->chatTimeString($message["datum"]) . "</span></span>";
                        $html .= "</div>";
                        $html .= "</div>";
                    } else {
                        $olvasta = !empty($message["olvasta"])  ? "<i style='color:lightgreen;' title='olvasta' class='fa-solid fa-check'></i> " : "";
                        $html .= "<div style='padding:10px 0px 0px 0px;text-align: right;'>";
                        $html .= "<div class='chatusermessage' style='display:inline-block;background:#f0f0f0;border-radius: 10px;color:black;padding:3px 10px;'>{$this->processMessage($message["message"])}</div><br clear='all' />";
                        $html .= "<span class='chatstatus'>{$olvasta}{$message["username"]} " . $this->chatTimeString($message["datum"]) . "</span></span>";
                        $html .= "</div>";
                    }
                } else {
                    $html .= "<div style='display:table-row;'>";
                    if ($this->adminUser->user["id"] != $message["userid"]) {
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

    private function processMessage($message) {
        return preg_replace("~[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]~","<a target=\"_blank\" href=\"\\0\">\\0</a>", $message);
    }

    private function chatMonogram($nev):string {
        $color = self::colorByText($nev);

        $monogram = "";
        foreach (explode(" ", $nev) as $value) {
            $monogram .= substr($value, 0, 1);
            if (strlen($monogram) == 2) {
                break;
            }
        }

        return "<div style='font-family:Courier,serif;background:{$color};color:white;padding:5px 7px;border-radius: 100px;text-transform:uppercase;font-weight:bold;'>{$monogram}</div>";
    }


    public static function colorByText($text):string {
        $hash = md5($text);

        $color1 = hexdec(substr($hash, 8, 2));
        $color2 = hexdec(substr($hash, 4, 2));
        $color3 = hexdec(substr($hash, 0, 2));

        if ($color1+$color2+$color3 > 200*3) {
            $color1 = round($color1/2);
            $color2 = round($color2/2);
            $color3 = round($color3/2);
        }

        return "#".str_pad(dechex($color1), 2, "0").str_pad(dechex($color2), 2, "0").str_pad(dechex($color3), 2, "0");
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
        $buttons.= "<div style='margin:5px 0px;font-weight: bold;'>Online felhasználók:</div>";
        $buttons.= $this->userButtons($sessionId, sql_query("select * from users u where u.status>0 and u.username<>'' and lastlogin>date_sub(now(), interval 1 minute) order by trim(nev)")->fetchAll(PDO::FETCH_ASSOC));
        $buttons.= "<div style='margin:5px 0px;font-weight: bold;'>Offline felhasználók:</div>";
        $buttons.= $this->userButtons($sessionId, sql_query("select * from users u where u.status>0 and u.username<>'' and lastlogin<=date_sub(now(), interval 1 minute) order by trim(nev)")->fetchAll(PDO::FETCH_ASSOC));
        return $buttons;
    }

    private function userButtons($sessionId, $users):string {
        $buttons = "";
        foreach ($users as $user) {
            if ($user["id"] == $this->adminUser->user["id"]) {
                continue;
            }
            $aktiv = in_array($user["id"], $this->selectedUsers) ? 1:0;
            $class = $aktiv==1 ? "serviceselected":"servicenotselected";
            $buttons.= "<a data-aktiv='{$aktiv}' data-chatsessionid='{$sessionId}' data-userid='{$user["id"]}' title='' class='{$class}' href='#' onclick='addChatUserToSession(this);return false;'>".trim($user["nev"])."</a> ";
        }
        return $buttons;
    }
}