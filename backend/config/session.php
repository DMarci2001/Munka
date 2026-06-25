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
  'secure'   => false,   // helyi HTTP; éles HTTPS-en állítsd true-ra
]);
session_start();
