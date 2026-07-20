<?php
// ============================================================
// Rövid link az eszköz QR-címkékhez.
//
// Nincs átirányítás és nincs külső szolgáltatás (pl. TinyURL): ez a fájl
// MAGA rendereli a végcélt, csak egy sokkal rövidebb URL alatt. A kód az
// eszköz numerikus azonosítójának base36 kódolása (lásd qrLabel.js
// shortCodeFor függvénye) — nincs szükség adatbázis-táblára a kód <-> cél
// megfeleltetéshez, az odavissza alakítás pusztán számrendszer-váltás.
// ============================================================
session_start();

$GLOBALS["admin"] = 1;

require_once "../autoload.php";

$_GET["page"] = "eszkoz";

$code = preg_replace('/[^0-9a-zA-Z]/', '', $_SERVER["QUERY_STRING"] ?? '');
if ($code !== '') {
  $deviceId = (int) base_convert($code, 36, 10);
  if ($deviceId > 0) {
    $row = sql_fetch_array(sql_query(
      "SELECT asset_tag FROM eszkoznyilvantartas_devices WHERE device_id = ?",
      [$deviceId]
    ));
    if ($row) $_GET["tag"] = $row["asset_tag"];
  }
}

$page = new AdminPage();
$page->showPage();
