<?php
// ============================================================
// Rövid link az eszköz QR-címkékhez.
//
// Ez a fájl CSAK dekódol és TOVÁBBIRÁNYÍT az admin felület saját,
// /admin/ alatti belépési pontjára — nem rendereli itt, a site
// gyökeréből. Az admin oldalak (pl. AdminLoginPage sikeres belépés
// utáni redirectje) RELATÍV "index.php" hivatkozásokat használnak,
// amik /admin/index.php-ra vonatkoznak. Ha innen, a site gyökeréből
// rendereltük volna magát az admin oldalt, ezek a redirectek tévesen
// a PUBLIKUS (páciens) index.php-ra vittek volna át — pontosan ez
// történt éles teszt közben.
//
// A rövidség (base36 kód, nincs adatbázis-tábla a kód <-> eszköz
// megfeleltetéshez) megmarad — lásd qrLabel.js shortCodeFor függvénye.
// ============================================================
session_start();

require_once "../autoload.php";

$code = preg_replace('/[^0-9a-zA-Z]/', '', $_SERVER["QUERY_STRING"] ?? '');
$params = ['page' => 'eszkoz'];
if ($code !== '') {
  $deviceId = (int) base_convert($code, 36, 10);
  if ($deviceId > 0) {
    $row = sql_fetch_array(sql_query(
      "SELECT asset_tag FROM eszkoznyilvantartas_devices WHERE device_id = ?",
      [$deviceId]
    ));
    if ($row) $params['tag'] = $row["asset_tag"];
  }
}

header("Location: /admin/index.php?" . http_build_query($params));
die();
