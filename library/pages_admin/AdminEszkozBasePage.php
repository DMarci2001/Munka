<?php

abstract class AdminEszkozBasePage extends AdminCorePage {

    // Az SPA route-ja (pl. "/my"), amire a beágyazott alkalmazás induláskor navigáljon.
    // Üres string = alapértelmezett nézet (eszközlista).
    protected string $hashRoute = "";

    public function showPage() {
        // SSO_SECRET / SSO_TTL_SECONDS egyetlen forrása a webapp konfigja:
        require_once __DIR__ . "/../eszkoznyilvantartas/backend/config/config.php";

        $username = $this->adminUser->user["username"];
        $ts = time();
        $token = hash_hmac("sha256", $username . $ts, SSO_SECRET);

        $url = "/js/eszkoznyilvantartas/?" . http_build_query(["sso" => $token, "u" => $username, "t" => $ts]);

        $hash = $this->hashRoute;
        if (isset($_GET["tag"]) && $_GET["tag"] !== "") {
            $hash = "/scan/" . rawurlencode($_GET["tag"]);
        }
        if ($hash !== "") {
            $url .= "#" . $hash;
        }

        echo "<iframe src='" . htmlspecialchars($url, ENT_QUOTES) . "'"
           . " style='display:block;width:100%;height:calc(100vh - 175px);min-height:500px;border:0;background:#fff;'"
           . " allow='camera'></iframe>";
    }
}
