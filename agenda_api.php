<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const AGENDA_FILE = __DIR__ . '/data/agenda.json';

session_start();

function out(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function read_agenda(): array {
  if (!file_exists(AGENDA_FILE)) return [];
  $raw = trim((string)file_get_contents(AGENDA_FILE));
  if ($raw === '') return [];
  $json = json_decode($raw, true);
  return is_array($json) ? $json : [];
}

function write_agenda(array $items): void {
  if (!is_dir(dirname(AGENDA_FILE))) mkdir(dirname(AGENDA_FILE), 0775, true);
  $tmp = AGENDA_FILE . '.tmp';
  file_put_contents($tmp, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
  rename($tmp, AGENDA_FILE);
}

function require_admin(): void {
  if (empty($_SESSION['admin_pengurus'])) {
    out(['ok' => false, 'error' => 'Unauthorized'], 401);
  }
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
  out(['ok' => true, 'items' => read_agenda()]);
}

if ($action === 'save') {
  require_admin();
  $raw = file_get_contents('php://input');
  $payload = json_decode((string)$raw, true);
  if (!is_array($payload) || !isset($payload['items']) || !is_array($payload['items'])) {
    out(['ok' => false, 'error' => 'Payload tidak valid'], 400);
  }

  $clean = [];
  foreach ($payload['items'] as $it) {
    $periode = trim((string)($it['periode'] ?? ''));
    $kegiatan = trim((string)($it['kegiatan'] ?? ''));
    if ($periode === '' && $kegiatan === '') continue;
    $clean[] = ['periode' => $periode, 'kegiatan' => $kegiatan];
  }

  write_agenda($clean);
  out(['ok' => true, 'count' => count($clean)]);
}

out(['ok' => false, 'error' => 'Action tidak dikenal'], 400);
