<?php
// ============================================================
// Munkamenet indítása — süti-paraméterek a böngészős SPA-hoz.
// ============================================================

require_once __DIR__ . '/config.php';

session_name(SESSION_NAME);
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'samesite' => 'Lax',   // azonos site (localhost) esetén megfelelő
  'secure'   => !empty($_SERVER['HTTPS']),   // helyi HTTP-n false, éles HTTPS-en true
]);
session_start();
