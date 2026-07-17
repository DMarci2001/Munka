<?php
// ============================================================
// Bemenet-ellenőrző segédek.
// ============================================================

require_once __DIR__ . '/Response.php';

// Kötelező mezők megléte (nem üres). Hiányzónál OpError.
function require_fields(array $data, array $fields): void {
  $missing = [];
  foreach ($fields as $f) {
    if (!array_key_exists($f, $data) || $data[$f] === null || $data[$f] === '') $missing[] = $f;
  }
  if ($missing) throw new OpError('Hiányzó mezők: ' . implode(', ', $missing));
}

// int vagy null (üres/hiányzó → null).
function int_or_null($v): ?int {
  if ($v === null || $v === '' ) return null;
  return (int) $v;
}

// Enum-ellenőrzés.
function enum_in($v, array $allowed, string $label = 'érték'): void {
  if (!in_array($v, $allowed, true)) {
    throw new OpError("Érvénytelen $label: " . var_export($v, true));
  }
}
