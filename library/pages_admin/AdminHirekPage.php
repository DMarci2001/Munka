<?php

class AdminHirekPage extends AdminCorePage {

    public array $categories = [
        1 => ["name" => "Protokoll"],
        2 => ["name" => "Rendelési idő"],
        3 => ["name" => "Napi feladatok"],
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
        $html.= "<div style='padding-top: 5px;'><a class='ujbutton' href='#' onclick='$(\"#newtopic\").toggle();return false;'>+ téma hozzáadása</a></div>";

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
            $readers = explode("|", $newsItem["readby"]);
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

