<?php

class AdminHirekPage extends AdminCorePage {

    public array $categories = [
        1 => ["name" => "Protokoll"],
        2 => ["name" => "Rendelési idő"],
        3 => ["name" => "Napi feladatok"],
        4 => ["name" => "Fontos információk"],
    ];

    private array $users = [];

    public function __construct() {
        parent::__construct();

        if (!$this->adminUser->faliujsagAccess()) {
            return;
        }

        $utils = new Utils();

        $users = sql_query("select id, username from users")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $user) {
            $this->users[$user["id"]] = $user;
        }

        if (!isset($_SESSION["newsfilter"])) {
            $_SESSION["newsfilter"] = "all";
        }

        if (!isset($_SESSION["topicfilter"])) {
            $_SESSION["topicfilter"] = "";
        }

        if (isset($_POST["setnewsfilter"])) {
            $_SESSION["newsfilter"] = $_POST["setnewsfilter"];

            $utils->jsonOut(["html" => $this->_newsTable()]);
            die;
        }

        if (isset($_POST["settopicfilter"])) {
            $_SESSION["topicfilter"] = $_POST["settopicfilter"];

            $utils->jsonOut(["html" => $this->_newsTable()]);
            die;
        }

        if (isset($_POST["ireadthenews"])) {
            $userId = $_SESSION["adminuser"]["id"];
            $id = $_POST["id"];
            sql_query("update news set readby = concat(readby,?) where id=?", ["|{$userId}|", $id]);

            $utils->jsonOut(["html" => $this->_newsItem($this->getNewsItem($id))]);
            die;
        }

        if (isset($_POST["addcomment"])) {
            $id = $_POST["id"];
            $text = $_POST["text"];
            if (!empty(trim($text))) {
                sql_query("insert into newscomment set datum=now(), newsid=?, userid=?, szoveg=?", [$id, $_SESSION["adminuser"]["id"], trim($text)]);
            }

            $utils->jsonOut(["html" => $this->_newsItem($this->getNewsItem($id))]);
            die;
        }

        if (isset($_POST["addtopic"])) {
            $categoryId = $_POST["categoryid"];
            $text = $_POST["text"];
            if (!empty(trim($text))) {
                sql_query("insert into news set datum=now(), categoryid=?, createdby=?, szoveg=?", [$categoryId, $_SESSION["adminuser"]["id"], trim($text)]);
            }

            $utils->jsonOut(["html" => $this->_newsTable()]);
            die;
        }

        if (isset($_POST["deletecomment"])) {
            $id = $_POST["id"];
            $commentId = $_POST["commentid"];

            sql_query("delete from newscomment where id=? and (userid=? or ".$this->deleteAccess(0).")", [$commentId, $_SESSION["adminuser"]["id"]]);

            $utils->jsonOut(["html" => $this->_newsItem($this->getNewsItem($id))]);
            die;
        }

        if (isset($_POST["deletetopic"])) {
            $id = $_POST["id"];

            sql_query("delete from news where id=? and (createdby=? or ".$this->deleteAccess(0).")", [$id, $_SESSION["adminuser"]["id"]]);

            $utils->jsonOut(["html" => $this->_newsTable()]);
            die;
        }


        if (isset($_POST["notifynotreaders"])) {
            $channel = $_POST["channel"];
            if (!in_array($channel, ["email", "sms"])) {
                $utils->jsonOut(["nev" => "error", "icon" => "error"]);
            }

            $id = intval($_POST["id"]);
            $newsItem = sql_query("select * from news where id=?", [$id])->fetch(PDO::FETCH_ASSOC);

            $readers = $notified = [];
            if (!empty($newsItem["readby"])) {
                $readers = explode("|", $newsItem["readby"]);
            }
            if (!empty($newsItem["notification"])) {
                $notified = explode(",", $newsItem["notification"]);
            }

            $checkedUsers = explode("_", $_POST["checkedUsers"]);
            foreach ($checkedUsers as $key => $user) {
                $checkedUsers[$key] = intval($user);
            }
            $checkedUsers[] = 0;

            $lastNotified = "";
            $icon = "success";
            $hirekUsers = sql_query("SELECT id, nev, username, tel, email FROM users WHERE INSTR(permissions, 'jog_faliujsag') AND STATUS=1 and id in (".implode(",", $checkedUsers).") order by nev")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($hirekUsers as $hirekUser) {
                if (!in_array($hirekUser["id"], $readers) && !in_array($hirekUser["id"], $notified)) {
                    if ($channel == "sms") {
                        $lastNotified = "{$channel}: " . $hirekUser["nev"] . " " . $hirekUser["tel"];
                        if (trim($hirekUser["tel"]) == "") {
                            $lastNotified = "{$channel}: " . $hirekUser["nev"] . ", nincs telefonszám!";
                            $icon = "error";
                        } else {
                            //$tel = "06209996183";
                            $tel = $hirekUser["tel"];
                            $this->utils->sendSMS($tel, "Új faliújság bejegyzés a ".Booking_Constants::COMPANY_NAME_SHORT." bejelentkezőben");
                        }
                    }

                    if ($channel == "email") {
                        $lastNotified = "{$channel}: " . $hirekUser["nev"] . " " . $hirekUser["email"];
                        if (trim($hirekUser["email"]) == "") {
                            $lastNotified = "{$channel}: " . $hirekUser["nev"] . ", nincs email!";
                            $icon = "error";
                        } else {
                            $mail = NotificationService::getDefaultMailer();
                            //$mail->AddAddress("jnsmobil@gmail.com");
                            $mail->AddAddress($hirekUser["email"]);

                            $subject = "Új faliújság bejegyzés a ".Booking_Constants::COMPANY_NAME_SHORT." bejelentkezőben";
                            $mail->Subject = $subject;

                            $text  = "<h3>Kedves {$hirekUser["nev"]}!</h3>";
                            $text.= "Új falijság bejegyzés került rögzítésre a bejelentkező rendszerben, kérjük olvassa el!";

                            $mail->Body = $text;
                            $mail->Send();

                        }
                    }

                    $notified[] = $hirekUser["id"];
                    sql_query("update news set notification=? where id=?", [implode(",", $notified), $id]);
                    break;
                }
            }

            $utils->jsonOut(["nev" => $lastNotified, "icon" => $icon]);
            die;
        }
    }

    public function showPage() {
        if (!$this->adminUser->faliujsagAccess()) {
            echo $this->noPermissionMessage();
            return;
        }

        echo "<div id='newstable'>";
        echo $this->_newsTable();
        echo "</div>";
    }

    private function _newsTable() {
        $html = "";

        $html.= "<div style=''>";
        if ($this->adminUser->newsInsertAccess()) {
            $html .= "<div style='padding-top: 5px;'><a class='ujbutton' href='#' onclick='$(\"#newtopic\").toggle();return false;'>+ téma hozzáadása</a></div>";
        }

        $html.= "<div id='newtopic' style='display:none;'>";
        $html.= "<div style='padding-top:15px;'><select id='topiccategoryid'>";
        $html.= "<option value='0'>Válassz kategóriát!</option>";
        foreach ($this->categories as $key => $category) {
            $html.= "<option value='{$key}'>{$category["name"]}</option>";
        }
        $html.= "</select></div>";
        $html.= "<div style='padding-top:5px;'><textarea id='topictext' style='width:600px;height:200px;'></textarea></div>";
        $html.= "<div style='padding-top:8px;'><a class='ujbutton' onclick='addNewTopic(this);return false;' href='#'>Téma mentése</a></div>";
        $html.= "</div>";
        $html.= "</div>";

        $html.= "<div style='border-top:1px solid #888;margin-top:25px;'>";

        $html.= "<div style='display:table;margin-top:20px;'>";
        $html.= "<div style='display:table-cell;vertical-align: middle;'>Szűrés:</div>";
        $html.= "<div style='display:table-cell;vertical-align: middle;padding-left:6px;'><a href='#' class='newsfilterbutton".("all" == $_SESSION["newsfilter"] ? " newsfilterbuttonselected":"")."' onclick='setNewsFilter(\"all\");return false;'>Összes</a></div>";
        foreach ($this->categories as $key => $category) {
            $html .= "<div style='display:table-cell;vertical-align: middle;padding-left:6px;'><a href='#' class='newsfilterbutton".($key == $_SESSION["newsfilter"] ? " newsfilterbuttonselected":"")."' onclick='setNewsFilter({$key});return false;'>{$category["name"]}</a></div>";
        }
        $html .= "<div style='display:table-cell;vertical-align: middle;padding-left:16px;'><input style='width:200px;' id='topicsearch' type='text' value='{$_SESSION["topicfilter"]}' placeholder='keresés a témák között...'/></div>";
        $html.= "</div>";


        $filter = "";
        $params = [];
        if ($_SESSION["newsfilter"] != "all") {
            $filter = "and n.categoryid=?";
            $params[] = $_SESSION["newsfilter"];
        }

        if (!empty($_SESSION["topicfilter"])) {
            $filter = "and instr(n.szoveg, ?)";
            $params[] = $_SESSION["topicfilter"];
        }

        $news = sql_query("select n.*, u.username as username from news n left join users u on u.id = n.createdby where true {$filter} order by n.datum desc limit 100", $params)->fetchAll(PDO::FETCH_ASSOC);

        if (empty($news)) {
            $html.= "<div style='margin-top: 20px;'>A keresési feltételeknek egyik elem sem felelt meg.</div>";
        }

        if (!empty($_SESSION["topicfilter"])) {
            $html.= "<div style='margin-top: 20px;padding:10px;background:#a00;color:#fff;display:inline-block;border-radius: 3px;'>Az alábbi lista keresés eredménye. Ha az összes sort akarod újra látni, <a style='color:yellow;' onclick='clearTopicFilter();return false;' href='#'>kattints ide</a>, vagy töröld a feltételt.</div>";
        }

        foreach ($news as $newsItem) {
            $html.= "<div id='newsitem{$newsItem["id"]}'>";
            $html.= $this->_newsItem($newsItem);
            $html.= "</div>";
        }


        $html.= "</div>";

        return $html;
    }

    private function _newsItem($newsItem) {
        $userId = $_SESSION["adminuser"]["id"];
        $newFlag = false;
        if (substr_count($newsItem["readby"], "|{$userId}|") == 0) {
            $newFlag = true;
        }

        $commenters = sql_query("select u.username as username from newscomment c 
                                    left join users u on u.id = c.userid
                                    where c.newsid=? group by c.userid", [$newsItem["id"]])->fetchAll(PDO::FETCH_ASSOC);

        $comments = sql_query("select c.*, u.username as username from newscomment c 
                                    left join users u on u.id = c.userid
                                    where c.newsid=? order by c.datum desc limit 100", [$newsItem["id"]])->fetchAll(PDO::FETCH_ASSOC);

        $html = "";

        $html.= "<div class='newsitem'>";

        $deleteTopicLink = $this->deleteAccess($newsItem["createdby"]) ? "<a data-id='{$newsItem["id"]}' onclick='deleteTopic(this);return false;' href='#' title='téma törlése'><i class='fas fa-trash'></i></a>" : "";


        $html.= "<div style='font-size: 14px;font-weight: bold;margin-bottom:10px;'>";
        $html.= ($newFlag?"<i style='color:#a00;' title='új' class='fas fa-exclamation-circle'></i> ":"").$this->categories[$newsItem["categoryid"]]["name"];
        $html.= ($newFlag ? " <a href='#' data-id='{$newsItem["id"]}' onclick='iReadTheNews(this);return false;' class='kisbutton' style='padding:2px 5px;'>kattints ide ha elolvastad</a> " : "");
        $html.= "</div>";

        $html.= "<div style='display:table-cell;vertical-align:middle;color:#888;'>".date("Y.m.d. H:i", strtotime($newsItem["datum"]))." - {$newsItem["username"]}</div><div style='display:table-cell;vertical-align: middle;'>&nbsp;{$deleteTopicLink}</div>";

        $readers = [];
        if (!empty($newsItem["readby"])) {
            $readers = explode("|", $newsItem["readby"]);
        }

        if ($this->adminUser->user["id"] == $newsItem["createdby"] || $this->adminUser->user["username"] == "jns") {
            $notified = [];
            if (!empty($newsItem["notification"])) {
                $notified = explode(",", $newsItem["notification"]);
            }

            $notReadUsers = [];
            $number = 0;
            $hirekUsers = sql_query("SELECT id, nev, username FROM users WHERE INSTR(permissions, 'jog_faliujsag') AND STATUS=1 order by nev")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($hirekUsers as $hirekUser) {
                if (!in_array($hirekUser["id"], $readers)) {
                    if (in_array($hirekUser["id"], $notified)) {
                        $hirekUser["nev"].=" <i title='értesítve' class='fa-solid fa-bell'></i> ";
                        $checked = "";
                    } else {
                        $number++;
                    }
                    $notReadUsers[] = "<span style='white-space: nowrap;'><input type='checkbox' id='ertesit_{$hirekUser["id"]}' value='{$hirekUser["id"]}' /><a target='_blank' href='index.php?page=users&szerk={$hirekUser["id"]}'>{$hirekUser["nev"]}</a></span>";
                }
            }

            if (!empty($notReadUsers)) {
                $html .= "<div style='margin-top:10px;' id='ertesitusers_{$newsItem["id"]}'>";
                $html .= "<strong>Nem olvasták:</strong> " . implode(", ", $notReadUsers);
                $html .= "</div>";
                $html .= "<div style='margin-top: 5px;'><a href='#' data-id='{$newsItem["id"]}' onclick='if (!confirm(\"Biztos értesíted a kijelölt felhasználókat sms-ben?\")) {return};notifyNotReadNews(this, \"sms\");return false;' class='kisbutton' style='padding:2px 5px;'>kijelöltek értesítése sms-ben</a> <a href='#' data-id='{$newsItem["id"]}' onclick='if (!confirm(\"Biztos értesíted a kijelölt felhasználókat emailben?\")) {return};notifyNotReadNews(this, \"email\");return false;' class='kisbutton' style='padding:2px 5px;'>kijelöltek értesítése email-ben</a></div>";
            }
        }

        $html.= "<div style='margin-top:10px;'>".nl2br($newsItem["szoveg"])."</div>";

        $html.= "</div>";


        $html.= "<div id='newscomments{$newsItem["id"]}' class='commentscontainer'>";

        $html.= "<div><a href='#' onclick='$(\"#newcomment{$newsItem["id"]}\").toggle();return false;'>+ hozzászólás</div>";


        $html.= "<div id='newcomment{$newsItem["id"]}' style='display:none;'>";
        $html.= "<div style='padding-top:5px;'><textarea id='commenttext{$newsItem["id"]}' style='width:500px;height:120px;'></textarea></div>";
        $html.= "<div style='padding:8px 0px;'><a class='ujbutton' data-id='{$newsItem["id"]}' onclick='addNewComment(this);return false;' href='#'>Hozzászólás mentése</a></div>";
        $html.= "</div>";

        if (!empty($newsItem["readby"])) {
            $html.= "<div style='margin-top:5px;'>Látta: ";
            foreach ($readers as $reader) {
                if (!empty($reader) && !empty($this->users[$reader])) {
                    $html .= "<div class='commenterbox'>".$this->users[$reader]["username"]."</div>";
                }
            }
            $html.= "</div>";
        }

        if (!empty($commenters)) {
            $html.= "<div style='margin-top:5px;'>Hozzászólók: ";
            foreach ($commenters as $commenter) {
                $html.= "<div class='commenterbox'>{$commenter["username"]}</div>";
            }
            $html.= "</div>";
        }

        foreach ($comments as $comment) {
            $deleteLink = $this->deleteAccess($comment["userid"]) ? "<a data-id='{$newsItem["id"]}' data-commentid='{$comment["id"]}' onclick='deleteComment(this);return false;' href='#' title='hozzászólás törlése'><i class='fas fa-trash'></i></a>" : "";
            $html.= "<div class='commentbox'>";
            $html.= "<div style='display:table-cell;vertical-align: middle;'>".date("Y.m.d. H:i", strtotime($comment["datum"]))." - {$comment["username"]} </div><div style='display:table-cell;vertical-align: middle;'>&nbsp;{$deleteLink}</div>";
            $html.= "<div style='padding-top:10px;'>{$comment["szoveg"]}</div>";
            $html.= "</div>";
        }

        $html.= "</div>";


        return $html;
    }

    private function deleteAccess($userId) {
        return $userId == $_SESSION["adminuser"]["id"] || $_SESSION["adminuser"]["jogosultsag"] == 2;
    }

    private function getNewsItem($id) {
        return sql_query("select n.*, u.username as username from news n left join users u on u.id = n.createdby where n.id=?", [$id])->fetch(PDO::FETCH_ASSOC);
    }

}

