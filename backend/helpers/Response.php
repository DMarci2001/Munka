<?php
// ============================================================
// Egységes JSON válaszburkoló + hibatípus.
// ============================================================

// Üzleti szabály megsértése — a kliensnek 422-vel és üzenettel megy vissza.
class OpError extends Exception {}

function json_success($data = null, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function json_error(int $code, string $msg = ''): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// A kérés JSON törzsének beolvasása asszociatív tömbként.
function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === '' || $raw === false) return [];
  $data = json_decode($raw, true);
  if (!is_array($data)) json_error(400, 'Érvénytelen JSON törzs.');
  return $data;
}
