<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AdminUtils
{
    public $settings;
    public $leletService;
    public $protocolService;

    public function __construct()
    {
        $this->settings = new Booking_Settings();
        $this->leletService = new AdminLeletService();
        $this->protocolService = new AdminProtocolService();
    }

    public function newPassSend($rowu)
    {
        $pchars = "abcdefghijklmnpqrstuvwxyz1234567899";
        $p = "";
        for ($i = 0; $i < 6; $i++) {
            $p .= substr($pchars, rand(0, strlen($pchars) - 1), 1);
        }

        $mail = new PHPMailer();
        $mail->From = Booking_Constants::NO_REPLY_ADDRESS;
        $mail->FromName = Booking_Constants::COMPANY_NAME;
        $mail->AddAddress($rowu["email"]);
        $mail->AddReplyTo(Booking_Constants::NO_REPLY_ADDRESS);
        $mail->IsHTML(true);

        $t = iconv("UTF-8", "ISO-8859-2", Booking_Constants::SITE_NAME . " - új jelszó");

        $mbody = "Kedves {$rowu["nev"]}!<br/><br/>";
        $mbody .= "A " . Booking_Constants::SITE_NAME . " felületén új jelszó kérését kezdeményezte.<br/><br/>";
        $mbody .= "Felhasználóneve: <b>{$rowu["username"]}</b><br/>";
        $mbody .= "Az új jelszava: <b>{$p}</b><br>";
        $mbody .= "<br/>";
        $mbody .= "Üdvözlettel:<br>" . Booking_Constants::COMPANY_NAME;

        $mail->Subject = $t;
        $mail->Body = iconv("UTF-8", "ISO-8859-2", $mbody);
        //$mail->AddAttachment("");
        $mail->Send();

        sql_query("update users set password='" . md5($p) . "' where id='{$rowu["id"]}'", array(md5($p), $rowu["id"]));
    }

    public function showCegListSzT($raw, $sor)
    {
        $h = "";
        $resc = sql_query("select id,megnev from cegek order by megnev");
        while ($rowc = sql_fetch_array($resc)) {
            $cegList[$rowc["id"]] = $rowc["megnev"];
        }

        $cegidk = explode("|", $raw);

        for ($i = 0; $i < count($cegidk); $i++) {
            if (isset($cegList[$cegidk[$i]])) $h .= "<span onclick='removeSztCegek({$cegidk[$i]},{$sor})' style='background:#f00;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;display:inline-block;margin:2px 2px 0px 0px;'>- {$cegList[$cegidk[$i]]}</span> ";
        }

        $h .= "<span onclick='$(\"#cegadd{$sor}\").slideToggle();' title='Cég hozzáadása' style='background:#0a0;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;'>+ cég</span>";

        return $h;
    }


    public function cegAddSorSzT($sor)
    {
        $h = "";
        $resc = sql_query("select id,megnev from cegek order by megnev");
        while ($rowc = sql_fetch_array($resc)) {
            $h .= "<span onclick='add2SztCegek({$rowc["id"]},{$sor})' style='background:#0a0;color:#fff;padding:1px 3px;border-radius:3px;cursor:pointer;display:inline-block;margin:2px 2px 0px 0px;'>+ {$rowc["megnev"]}</span> ";
        }
        return $h;
    }

    public function showFizSzolg($fid, $simple = 0)
    {
        $h = "";
        $res = sql_query("select * from fizkapcs where fid=?", array($fid));
        if (sql_num_rows($res) > 0) {
            if ($simple == 0) $h .= "<div style='padding:10px;margin-bottom:10px;background:#fcc;display:inline-block;'>";
            while ($row = sql_fetch_array($res)) {
                if ($row["megnev"] == "") {
                    $row["megnev"] = "noname";
                }
                $h .= "<div>+ {$row["megnev"]}";
                if ($row["ar"] != 0) $h .= " (" . number_format($row["ar"]) . " Ft)";
                if ($simple == 0) $h .= " [<a href='#' onclick='removeFizSzolg({$fid},{$row["id"]});return false;'>-</a>]</div>";
            }
            if ($simple == 0) $h .= "</div>";
        }
        return $h;
    }

    public function showPaciensFiles($id)
    {
        $htmlout = "";
        $resf = sql_query("select * from dokumentumok where foglalasid=?", array($id));
        if (sql_num_rows($resf) > 0) {
            $htmlout .= "<div style='display:inline-block;'>";
            $htmlout .= "<div style='background:#888;color:#fff;padding:5px;'>Paciens által feltöltött file(ok)</div>";
            while ($rowf = sql_fetch_array($resf)) {
                $htmlout .= "<div style='padding:1px 4px;'><a href='" . DocAgent::getDocURL($rowf) . "'>{$rowf["filename"]}</a></div>";
            }
            $htmlout .= "</div>";
        }
        return $htmlout;
    }

    public function magyarDatum($datum, $weekDay = true)
    {
        $m = date("n", strtotime($datum));
        $n = date("Y-m-d", strtotime($datum));
        $w = date("N", strtotime($datum));
        $date = substr($datum, 0, 4) . " " . ucfirst($GLOBALS["honaptext"][$m]) . " " . intval(substr($n, 8, 2)) . ".";
        if ($weekDay) {
            $date .= " " . $GLOBALS["hetnap"][$w];
        }
        $date .= " " . substr($datum, 11, 5);
        return trim($date);
    }

}
