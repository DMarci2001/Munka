<?php

class AdminChatPage extends AdminCorePage {


    private array $fastTexts = [
        "Jó napot kívánok!",
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

        if (isset($_POST["loadChatWindow"])) {
            $sessionId = intval($_POST["sessionId"]);

            if ($sessionId == 0) {
                $sessionId = $_SESSION["openedsession"];
            } else {
                $_SESSION["openedsession"] = $sessionId;
            }

            $html = "";

            $messages = sql_query("select c.*, u.username from chat c
                left join users u on u.id=c.userid
                where c.chatsessionid=? order by c.datum", [$sessionId]);
            foreach ($messages as $message) {
                if ($message["userid"] != 0) {
                    $html.= "<div style='display:table-row;'>";
                    $html.= "<div style='display:table-cell;vertical-align: top;font-size: 24px;padding:10px 10px 0px 0px;'>";
                    $html.= "<i class='fa-solid fa-user-doctor'></i>";
                    $html.= "</div>";
                    $html.= "<div style='display:table-cell;vertical-align: top;padding:10px 0px 0px 0px;'>";
                    $html.= "<div class='chatusermessage'>{$message["message"]}</div>";
                    $html.= "<span class='chatstatus'>{$message["username"]} ".$this->chatTimeString($message["datum"])."</span></span>";
                    $html.= "</div>";
                    $html.= "</div>";
                } else {
                    $html.= "<div style='display:table-row;'>";
                    $html.= "<div style='display:table-cell;vertical-align: top;font-size: 24px;padding:10px 10px 0px 0px;'>";
                    $html.= "<i class='fas fa-user'></i>";
                    $html.= "</div>";
                    $html.= "<div style='display:table-cell;vertical-align: top;padding:10px 0px 0px 0px;'>";
                    $html.= "<div class='chatadminmessage'>{$message["message"]}</div>";
                    $html.= "<span class='chatstatus'>Felhasználó ".$this->chatTimeString($message["datum"])."</span></span>";
                    $html.= "</div>";
                    $html.= "</div>";
                }
                if ($message["readdate"] == "0000-00-00 00:00:00") {
                    sql_query("update chat set readdate=now() where id=?", [$message["id"]]);
                }
            }

            Utils::jsonOut(["html" => $html]);
        }

        if (isset($_POST["chatSessionList"])) {
            Utils::jsonOut(["html" => $this->chatSessionList()]);
        }

        if (isset($_POST["sendmessage"])) {
            $chatSession = $_SESSION["openedsession"];
            $message = strip_tags($_POST["message"]);

            sql_query("insert into chat set datum=now(), chatsessionid=?, message=?, userid=?", [$chatSession, $message, $this->adminUser->user["id"]]);

            die("sent");
        }

        if (isset($_GET["closechat"])) {
            sql_query("update chatsession set closed=now() where id=?", [$_SESSION["openedsession"]]);
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
        if (!$this->adminUser->cegModAccess()) {
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

    private function chatSessionList():string {
        $html = "";

        $chatSessions = sql_query("select s.* from chatsession s
           order by s.created desc limit 1000")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($chatSessions as $chatSession) {
            $lastItem = sql_query("select * from chat where chatsessionid=? and userid>-1 order by datum desc limit 1", [$chatSession["id"]])->fetch(PDO::FETCH_ASSOC);
            if (!empty($lastItem)) {
                if (strtotime("now") - strtotime($lastItem["datum"]) > 3600*24) {
                    $time = date("Y.m.d H:i", strtotime($lastItem["datum"]));
                } else {
                    $time = date("H:i", strtotime($lastItem["datum"]));
                }

                $html.= "<div class='chatsessionlistitem".($_SESSION["openedsession"] == $chatSession["id"] ? " chatsessionlistitemaktiv":"")."' onclick='loadChatWindow({$chatSession["id"]}, true);'>";
                $html.= "<div style='margin-right: 10px;'>";
                if ($lastItem["readdate"] == "0000-00-00 00:00:00" && $lastItem["userid"] == 0) {
                    $html.= "<div style='float:left;font-size:20px;padding-right:5px;padding-top:6px;color:red'><i title='új üzenet' class='fas fa-comment'></i></div>";
                }

                if ($chatSession["closed"] != "0000-00-00 00:00:00") {
                    $html.= "<div style='float:left;font-size:20px;padding-right:5px;padding-top:3px;'><i title='lezárva' class='fas fa-lock'></i></div>";
                }

                $html.= "<div>{$chatSession["domain"]}</div>";
                $html.= "<div style='font-size: 11px;color:#666;white-space: nowrap;overflow: hidden;'>{$time} {$lastItem["message"]}</div>";
                $html.= "</div>";
                $html.= "</div>";
                $html.= "<br clear='all' />";
            }
        }

        return $html;
    }

}