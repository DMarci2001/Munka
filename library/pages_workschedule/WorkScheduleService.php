<?php

use PHPMailer\PHPMailer\PHPMailer;

class WorkScheduleService {
    public array $scheduleMapping = [];
    public array $collisionData = [];
    public array $collisionsByDate = [];

    public array $roles = [
        1 => "orvos",
        2 => "nővér",
        3 => "egyéb",
        5 => "jármű"
    ];

    function __construct()
    {
        $this->reloadScheduleMapping();
        $this->recalcAllCollisions();
    }

    public static function getDailySchedule($day):array {
        return sql_query("SELECT IF(TRIM(w.`teljesnev`) <> '', w.teljesnev, w.nev) AS workername, t.megnev AS tipusnev, r.megnev AS rolename, m.datumfrom, m.datumto, m.tipusid, m.roleid, m.workerid, m.megj FROM schedule_mapping m
            LEFT JOIN schedule_workers w ON w.id = m.workerid
            LEFT JOIN schedule_tipusok t ON t.id = m.tipusid
            LEFT JOIN schedule_roles r ON r.id = m.roleid
            WHERE m.datumfrom>=? AND m.datumfrom<=? and w.id is not null and t.id is not null order by m.datumfrom, m.roleid", ["{$day} 00:00:00", "{$day} 23:59:59"])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reloadScheduleMapping() {
        $this->scheduleMapping = [];
        $res = sql_query("SELECT m.*,w.nev AS workernev, n.nev AS novernev FROM schedule_mapping m
        LEFT JOIN schedule_workers w ON m.`workerid`=w.`id`
        LEFT JOIN schedule_workers n ON m.`noverid`=n.`id`
        where datumfrom > date_sub(now(), interval 100 day) order by m.datumfrom, w.nev");
        while ($row = sql_fetch_array($res)) {
            if ($row["napszak"] == 2) {
                $key = date("Y-m-d", strtotime($row["datumfrom"])) . "_2_{$row["tipusid"]}";
            } else {
                $key = date("Y-m-d", strtotime($row["datumfrom"])) . "_0_{$row["tipusid"]}";
            }
            $this->scheduleMapping[$key][] = $row;
        }
    }

    public function recalcAllCollisions() {
        $thisWeekMonday = date("Y-m-d 00:00:00", strtotime("this week monday"));
        $collisions = [];
        $suspects = sql_query("SELECT m.id, DATE(datumfrom) AS datum, workerid, napszak, COUNT(*) AS hany FROM schedule_mapping m WHERE datumfrom>? GROUP BY DATE(datumfrom), CONCAT(workerid) HAVING hany>1", [$thisWeekMonday])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($suspects as $suspect) {
            $beos = sql_query("select m.id, m.datumfrom, m.datumto from schedule_mapping m 
            left join schedule_workers sw on m.workerid=sw.id
            left join schedule_tipusok st on m.tipusid=st.id
            where m.workerid=? and date(datumfrom)=? and sw.id is not null and st.id is not null", [$suspect["workerid"], $suspect["datum"]])->fetchAll(PDO::FETCH_ASSOC);

            foreach ($beos as $beoLook) {
                foreach ($beos as $beo) {
                    if ($beo["id"] == $beoLook["id"]) {
                        continue;
                    }

                    if (($beo["datumfrom"] < $beoLook["datumto"]) && ($beo["datumto"] > $beoLook["datumfrom"])) {
                        $collisions[$suspect["id"]] = ["workerid" => $suspect["workerid"], "datum" => $suspect["datum"], "napszak" => $suspect["napszak"], "szoveg" => "", "datumfrom" => $beo["datumfrom"], "datumto" => $beo["datumto"], "datumfrom2" => $beoLook["datumfrom"], "datumto2" => $beoLook["datumto"]];
                        $this->collisionsByDate[$suspect["datum"]][$suspect["workerid"]][] = $beo["datumfrom"].$beo["datumto"];
                    }
                }
            }
        }

        $this->collisionData = $collisions;
        //print_r($collisions);
        //die;
    }

    public function notifyScheduleChange($workerId, $type = 'email') {
        $utils = new Utils();

        if ($workerData = sql_query("select * from schedule_workers w where w.id=?", [$workerId])->fetch()) {

            $token = $this->workerTokenGen($workerData);

            if ($type == "email") {
                $mail = NotificationService::getDefaultMailer();
                $mail->AddAddress($workerData["email"]);

                $mail->Subject = "[".Booking_Constants::COMPANY_NAME_SHORT."] beosztás változás";
                $mail->Body = "Kedves Munkatársunk!<br/>
                <br/>
                Értesítjük, hogy a beosztásában változás történt.<br/>
                Beosztásának megtekintéséhez kattintson az alábbi linkre.<br/>
                <br/>
                <a href='".Booking_Constants::MAIN_URL."/admin/index.php?scheduletoken={$token}'>Beosztás megtekintése</a><br/>
                <br/>
                Üdvözlettel:<br/>
                ".Booking_Constants::COMPANY_NAME."
                ";

                $mail->Send();
            }

            if ($type == "sms") {
                $utils->sendSMS($workerData["tel"], "Értesítjük, hogy beosztásában változás történt. kérjük ellenőrizze az emailben kiküldött linken. Üdv: ".Booking_Constants::COMPANY_NAME_SHORT);
            }

        }

    }

    public function workerTokenGen($workerData):string {
        return sha1($workerData["id"].$workerData["roleid"].$workerData["email"].$workerData["tel"]).md5($workerData["email"].$workerData["tel"]);
    }

    public function workerScheduleList($workerId):string {
        $adminUtils = new AdminUtils();
        $html = "";
        $stat = [];
        $szabadsagNapok = [];
        $szabadsagStatuses = [];

        $workerData = sql_query("select nev, teljesnev from schedule_workers where id=?", [$workerId])->fetch(PDO::FETCH_ASSOC);
        $html.= "<div style='font-weight: bold;'>{$workerData["teljesnev"]} beosztása / szabadságai</div>";

        $szabiData = sql_query("select datumtol, status from schedule_szabadsag sz where sz.datumtol>date_sub(now(), interval 6 month) and oid=?", [$workerId])->fetchAll();
        foreach ($szabiData as $data) {
            $szabadsagNapok[] = $data["datumtol"];
            $szabadsagStatuses[$data["datumtol"]] = $data["status"];
        }

        $res = sql_query("SELECT date(datumfrom) as datum, m.*, t.megnev as tipusnev, t.kulso, t.cim
                    FROM schedule_mapping m
                    JOIN schedule_tipusok t on t.id=m.tipusid
                    WHERE m.workerid=? AND m.`datumfrom`>DATE_SUB(NOW(), INTERVAL 40 DAY)
                    AND NOT EXISTS (SELECT 1 FROM schedule_nap_lezart WHERE datum = DATE(m.datumfrom))", [$workerId]);

        while ($row = sql_fetch_array($res)) {
            $stat[$row["datum"]][] = $row;
        }

        for ($i = 0; $i < 7 * 52; $i++) {
            $thisDay = date("Y-m-d", strtotime("last week monday + {$i} day"));
            $weekDay = date("N", strtotime($thisDay));
            $weekNum = date("W", strtotime($thisDay));

            if ($weekDay == 1) {
                $html.= "<div style='display:table-row;'>";
                $html.= "<div style='display:table-cell;font-weight: bold;padding:4px 0px;'>{$weekNum}. hét</div>";
                $html.= "</div>";
            }
            $html.= "<div style='display:table-row;'>";
            $html.= "<div style='display:table-cell;border-top:1px solid #ccc;padding:2px 0px;'>".$adminUtils->magyarDatum($thisDay, false)."&nbsp;&nbsp;</div>";
            $html.= "<div style='display:table-cell;border-top:1px solid #ccc;'>".$adminUtils->settings->hetnap[$weekDay]."&nbsp;&nbsp;</div>";
            $html.= "<div style='display:table-cell;border-top:1px solid #ccc;'>";
            $display = [];


            if (in_array($thisDay, $szabadsagNapok)) {
                $statusText = "Elbírálás folyamatban..";
                if ($szabadsagStatuses[$thisDay] == 1) {
                    $statusText = "<span style='color:darkgreen'>Engedélyezve</span>";
                }
                if ($szabadsagStatuses[$thisDay] == 2) {
                    $statusText = "<span style='color:darkred'>! Elutasítva</span>";
                }

                $display[] = "<span onclick='toggleWorkerFreeDay(\"{$thisDay}\", {$workerId});' style='cursor:pointer;padding:2px 5px;background:#56af56;color:#fff;border-radius: 2px;'><i class='fa-regular fa-square-check'></i> szabi</span> {$statusText}";
            } else {
                $display[] = "<span onclick='toggleWorkerFreeDay(\"{$thisDay}\", {$workerId});' style='cursor:pointer;padding:2px 5px;background:lightgray;color:#fff;border-radius: 2px;'><i class='fa-regular fa-square'></i> szabi</span>";
            }

            if (isset($stat[$thisDay])) {
                foreach ($stat[$thisDay] as $item) {
                    $text = $item["tipusnev"]." ".$this->workInterval($item);

                    if ($item["cim"] != "") {
                        $text.= "&nbsp;&nbsp;<a title='Google Maps' href='https://www.google.com/maps/place/".urlencode($item["cim"])."' target='_blank'><i class='fas fa-map' style='font-size:16px;'></i></a>";
                    }
                    $display[] = $text;
                }
            }
            $html.= implode("<br/>", $display);
            $html.= "</div>";

            $html.= "</div>";
        }

        return $html;
    }

    public function workInterval($mapping):string {
        $html="";

        $from = date("H:i", strtotime($mapping["datumfrom"]));
        $to   = date("H:i", strtotime($mapping["datumto"]));

        if ($from != "00:00" || $to != "00:00") {
            if ($from != "00:00" && $to == "00:00") {
                $html.="{$from} -";
            } else {
                if ($from == "00:00" && $to != "00:00") {
                    $html .= "- {$to}";
                } else {
                    $html .= "{$from} - {$to}";
                }
            }
        }
        return $html;
    }

    public function dateOddOrEvenText($date):string {
        if (date('W', strtotime($date))%2==0) {
            return "páros";
        } else {
            return "páratlan";
        }
    }

    public function workerPublicScheduleCards(int $workerId): string {
        $adminUtils = new AdminUtils();
        $html = "";

        $vacations = sql_query(
            "SELECT MIN(datumtol) AS tol, MAX(datumig) AS ig, MIN(status) AS status, groupid
             FROM schedule_szabadsag
             WHERE oid = ? AND datumig >= CURDATE()
             GROUP BY groupid
             ORDER BY tol",
            [$workerId]
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($vacations)) {
            $html .= "<div class='pub-section-head' style='margin-bottom:0;border-radius:4px 4px 0 0;'>Szabadság kérések</div>";
            $html .= "<div class='pub-vac-list'>";
            foreach ($vacations as $vac) {
                $status = (int)$vac["status"];
                if ($status === 1) {
                    $statusHtml = "<span style='color:#1a7a1a;font-weight:bold;'>Elfogadva</span>";
                } elseif ($status === 2) {
                    $statusHtml = "<span style='color:#a00;font-weight:bold;'>Elutasítva</span>";
                } else {
                    $statusHtml = "<span style='color:#666;'>Elbírálás folyamatban...</span>";
                }
                $html .= "<div class='pub-vac-item'>";
                $html .= "<span><i class='fa-solid fa-umbrella-beach' style='color:#888;margin-right:5px;'></i>"
                    . $adminUtils->magyarDatum($vac["tol"], false)
                    . " – "
                    . $adminUtils->magyarDatum($vac["ig"], false) . "</span>";
                $html .= $statusHtml;
                $html .= "</div>";
            }
            $html .= "</div>";
        }

        $res = sql_query(
            "SELECT
                DATE(m.datumfrom) AS datum,
                m.datumfrom, m.datumto, m.tipusid, m.workerid, m.megj,
                t.megnev AS tipusnev, t.kulso, t.kiszallas, t.roleid AS tipusroleid, t.cim, t.rendelo,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(
                            COALESCE(NULLIF(TRIM(pw.teljesnev),''), pw.nev),
                            '|',
                            DATE_FORMAT(pm2.datumfrom, '%H:%i'),
                            '-',
                            DATE_FORMAT(pm2.datumto, '%H:%i')
                        )
                        ORDER BY pm2.roleid
                        SEPARATOR ';;'
                    )
                    FROM schedule_mapping pm2
                    LEFT JOIN schedule_workers pw ON pw.id = pm2.workerid
                    WHERE pm2.tipusid = m.tipusid
                      AND DATE(pm2.datumfrom) = DATE(m.datumfrom)
                      AND pm2.workerid != m.workerid
                ) AS pairedDetails
             FROM schedule_mapping m
             JOIN schedule_tipusok t ON t.id = m.tipusid
             WHERE m.workerid = ? AND m.datumfrom >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
               AND NOT EXISTS (SELECT 1 FROM schedule_nap_lezart WHERE datum = DATE(m.datumfrom))
             ORDER BY m.datumfrom, t.kulso, t.kiszallas, t.sorrend",
            [$workerId]
        );

        // 0=belső, 1=belső egyéb, 2=külső, 3=kiszállás — kiszallas ellenőrzése ELŐSZÖR (mint a React)
        $bySec = [0 => [], 1 => [], 2 => [], 3 => []];
        while ($row = sql_fetch_array($res)) {
            $kulso     = (int)$row["kulso"];
            $kiszallas = (int)$row["kiszallas"];
            $roleid    = (int)$row["tipusroleid"];
            if ($kiszallas) {
                $section = 3;
            } elseif ($kulso === 0) {
                $section = ($roleid === 3) ? 1 : 0;
            } else {
                $section = 2;
            }
            $bySec[$section][] = $row;
        }

        if (empty($bySec[0]) && empty($bySec[1]) && empty($bySec[2]) && empty($bySec[3])) {
            $html .= "<div style='color:#888;padding:20px 0;'>Nincs közelgő beosztás.</div>";
            return $html;
        }

        // Kiszállások csoportosítása tipusid szerint: egy kártya per esemény, dátumtartománnyal
        if (!empty($bySec[3])) {
            $grouped = [];
            foreach ($bySec[3] as $item) {
                $tid = (int)$item["tipusid"];
                if (!isset($grouped[$tid])) {
                    $grouped[$tid] = $item;
                    $grouped[$tid]["_maxDate"] = $item["datum"];
                    $grouped[$tid]["_pairedByName"] = [];
                }
                if ($item["datum"] > $grouped[$tid]["_maxDate"]) {
                    $grouped[$tid]["_maxDate"] = $item["datum"];
                }
                if (!empty($item["pairedDetails"])) {
                    foreach (explode(";;", $item["pairedDetails"]) as $detail) {
                        $parts = explode("|", $detail, 2);
                        $name  = trim($parts[0]);
                        if ($name && !isset($grouped[$tid]["_pairedByName"][$name])) {
                            $grouped[$tid]["_pairedByName"][$name] = $parts[1] ?? "";
                        }
                    }
                }
            }
            $bySec[3] = [];
            foreach ($grouped as $gItem) {
                $pairParts = [];
                foreach ($gItem["_pairedByName"] as $name => $time) {
                    $pairParts[] = "{$name}|{$time}";
                }
                $gItem["pairedDetails"] = empty($pairParts) ? null : implode(";;", $pairParts);
                $bySec[3][] = $gItem;
            }
        }

        $sections = [
            0 => ["label" => "Belső rendelések",       "color" => "#9c3328", "bg" => "rgba(156,51,40,.10)",  "icon" => "fa-solid fa-users"],
            1 => ["label" => "Belső - Irodai / egyéb", "color" => "#9c3328", "bg" => "rgba(156,51,40,.08)",  "icon" => "fa-solid fa-house"],
            2 => ["label" => "Külső rendelések",        "color" => "#2a7c48", "bg" => "rgba(42,124,72,.10)",  "icon" => "fa-solid fa-building"],
            3 => ["label" => "Kiszállások",             "color" => "#6b21a8", "bg" => "rgba(107,33,168,.10)", "icon" => "fa-solid fa-truck"],
        ];

        static $secIdx = 0;
        foreach ($sections as $sKey => $sec) {
            if (empty($bySec[$sKey])) continue;
            $secIdx++;
            $bodyId = "pubsec{$secIdx}";
            $count  = count($bySec[$sKey]);
            $html .= "<div style='background:#fff;border:1px solid #e3e8ef;border-radius:12px;overflow:hidden;margin-bottom:12px;'>";
            // Header — kattintható, összecsukható
            $html .= "<button onclick='pubToggleSec(\"{$bodyId}\",this)' style='width:100%;background:{$sec["bg"]};border:none;cursor:pointer;padding:10px 12px;display:flex;align-items:center;gap:8px;text-align:left;'>";
            $html .= "<i class='{$sec["icon"]}' style='color:{$sec["color"]};font-size:14px;flex-shrink:0;'></i>";
            $html .= "<span style='font-size:13px;font-weight:700;color:{$sec["color"]};flex:1;'>{$sec["label"]}</span>";
            $html .= "<span style='font-size:11px;font-weight:700;color:#888;background:#f1f4f7;padding:1px 7px;border-radius:4px;margin-right:6px;'>{$count}</span>";
            $html .= "<i class='fa-solid fa-chevron-up' style='color:{$sec["color"]};font-size:12px;transition:transform .2s;'></i>";
            $html .= "</button>";
            // Body
            $html .= "<div id='{$bodyId}' style='display:flex;flex-direction:column;gap:6px;padding:8px;'>";
            foreach ($bySec[$sKey] as $item) {
                $html .= $this->_publicCard($item, $adminUtils);
            }
            $html .= "</div>";
            $html .= "</div>";
        }

        return $html;
    }

    private function _publicCard(array $item, AdminUtils $adminUtils): string {
        $hasCim   = !empty($item["cim"]);
        $mapUrl   = $hasCim ? "https://www.google.com/maps/search/" . urlencode($item["cim"]) : "";
        $mapColor = $hasCim ? "#9c3328" : "#c8cdd5";
        $mapStyle = "position:absolute;right:10px;top:10px;font-size:16px;text-decoration:none;color:{$mapColor};";
        $mapTitle = $hasCim ? htmlspecialchars($item["cim"]) : "Nincs helyszín megadva";

        $html = "<div style='background:#fff;border:1px solid #e3e8ef;border-radius:10px;padding:10px 12px;position:relative;'>";

        // Map icon — mindig látható, piros ha van helyszín, szürke ha nincs
        if ($hasCim) {
            $html .= "<a href='" . htmlspecialchars($mapUrl) . "' target='_blank' title='{$mapTitle}' style='{$mapStyle}'>"
                . "<i class='fas fa-map-marker-alt'></i></a>";
        } else {
            $html .= "<span title='{$mapTitle}' style='{$mapStyle}'>"
                . "<i class='fas fa-map-marker-alt'></i></span>";
        }

        // Dátum (tartomány ha kiszállás és van _maxDate)
        $isKiszallas = (int)($item["kiszallas"] ?? 0);
        $maxDate     = $item["_maxDate"] ?? null;
        $html .= "<div style='font-size:12px;font-weight:700;color:#5c6675;margin-bottom:2px;padding-right:26px;'>";
        if ($isKiszallas && $maxDate && $maxDate !== $item["datum"]) {
            $html .= $adminUtils->magyarDatum($item["datum"]) . " – " . $adminUtils->magyarDatum($maxDate);
        } else {
            $html .= $adminUtils->magyarDatum($item["datum"]);
        }
        $html .= "</div>";

        // Idő intervallum
        $interval = $this->workInterval($item);
        if ($interval) {
            $html .= "<div style='font-family:monospace;font-size:12px;color:#5c6675;margin-bottom:4px;'>{$interval}</div>";
        }

        // Rendelés neve
        $html .= "<div style='font-size:14px;font-weight:700;color:#1a2230;margin-bottom:3px;'>"
            . htmlspecialchars($item["tipusnev"] ?? "") . "</div>";

        // Rendelő
        if (!empty($item["rendelo"])) {
            $html .= "<div style='font-size:12px;color:#5c6675;margin-bottom:2px;display:flex;align-items:center;gap:5px;'>"
                . "<i class='fa-solid fa-house-medical' style='color:#9aa3b1;font-size:11px;flex-shrink:0;'></i>"
                . htmlspecialchars($item["rendelo"]) . "</div>";
        }

        // Helyszín cím
        if (!empty($item["cim"])) {
            $html .= "<div style='font-size:11.5px;color:#9aa3b1;font-weight:600;display:flex;align-items:center;gap:5px;margin-bottom:2px;'>"
                . "<i class='fa-solid fa-location-dot' style='font-size:10px;flex-shrink:0;'></i>"
                . htmlspecialchars($item["cim"]) . "</div>";
        }

        // Kolléga
        if (!empty($item["pairedDetails"])) {
            $html .= "<div style='margin-top:5px;display:flex;flex-wrap:wrap;gap:4px;'>";
            foreach (explode(";;", $item["pairedDetails"]) as $detail) {
                $parts = explode("|", $detail, 2);
                $name  = trim($parts[0]);
                $time  = $parts[1] ?? "";
                $showTime = $isKiszallas && $time && $time !== "00:00-00:00";
                $html .= "<span style='background:#fef2f2;color:#9c3328;font-size:11.5px;font-weight:600;padding:2px 8px;border-radius:5px;display:inline-flex;align-items:center;gap:4px;'>"
                    . "<i class='fa-solid fa-user-doctor' style='font-size:10px;'></i>"
                    . htmlspecialchars($name);
                if ($showTime) {
                    $html .= "<span style='font-family:monospace;font-size:10.5px;opacity:.75;margin-left:2px;'>{$time}</span>";
                }
                $html .= "</span>";
            }
            $html .= "</div>";
        }

        // Megjegyzés
        if (!empty($item["megj"])) {
            $html .= "<div style='font-size:11px;color:#9aa3b1;margin-top:5px;font-style:italic;'>"
                . htmlspecialchars($item["megj"]) . "</div>";
        }

        $html .= "</div>";
        return $html;
    }
}