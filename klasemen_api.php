<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const FILE_PATH = __DIR__ . '/data/klasemen.json';

session_start();

function out(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function read_items(): array {
  if (!file_exists(FILE_PATH)) return [];
  $raw = trim((string)file_get_contents(FILE_PATH));
  if ($raw === '') return [];
  $json = json_decode($raw, true);
  return is_array($json) ? $json : [];
}

function write_items(array $items): void {
  if (!is_dir(dirname(FILE_PATH))) mkdir(dirname(FILE_PATH), 0775, true);
  $tmp = FILE_PATH . '.tmp';
  file_put_contents($tmp, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
  rename($tmp, FILE_PATH);
}

function require_admin(): void {
  if (empty($_SESSION['admin_pengurus'])) {
    out(['ok' => false, 'error' => 'Unauthorized'], 401);
  }
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
  out(['ok' => true, 'items' => read_items()]);
}

if ($action === 'save') {
  require_admin();
  $payload = json_decode((string)file_get_contents('php://input'), true);
  if (!is_array($payload) || !isset($payload['items']) || !is_array($payload['items'])) {
    out(['ok' => false, 'error' => 'Payload tidak valid'], 400);
  }

  $clean = [];
  foreach ($payload['items'] as $it) {
    $klub = trim((string)($it['klub'] ?? ''));
    if ($klub === '') continue;
    $clean[] = [
      'klub' => $klub,
      'main' => (int)($it['main'] ?? 0),
      'menang' => (int)($it['menang'] ?? 0),
      'poin' => (int)($it['poin'] ?? 0),
    ];
  }

  write_items($clean);
  out(['ok' => true, 'count' => count($clean)]);
}

out(['ok' => false, 'error' => 'Action tidak dikenal'], 400);
