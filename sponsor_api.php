<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
session_start();

const DATA_FILE  = __DIR__ . '/data/sponsor.json';
const UPLOAD_DIR = __DIR__ . '/uploads/sponsor/';
const MAX_UPLOAD = 2 * 1024 * 1024;
const ALLOWED_EXT = ['jpg','jpeg','png','webp','gif'];

function out(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function read_items(): array {
  if (!file_exists(DATA_FILE)) return [];
  $raw = trim((string)file_get_contents(DATA_FILE));
  if ($raw === '') return [];
  $json = json_decode($raw, true);
  return is_array($json) ? $json : [];
}
function write_items(array $items): void {
  if (!is_dir(dirname(DATA_FILE))) mkdir(dirname(DATA_FILE), 0775, true);
  $tmp = DATA_FILE . '.tmp';
  file_put_contents($tmp, json_encode($items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
  rename($tmp, DATA_FILE);
}
function require_admin(): void {
  if (empty($_SESSION['admin_pengurus'])) out(['ok'=>false,'error'=>'Unauthorized'], 401);
}
function upload_if_any(string $field, ?string $oldPath = null): ?string {
  if (!isset($_FILES[$field]) || !is_uploaded_file($_FILES[$field]['tmp_name'])) return null;
  $f = $_FILES[$field];
  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) out(['ok'=>false,'error'=>'Upload gagal'], 400);
  if (($f['size'] ?? 0) > MAX_UPLOAD) out(['ok'=>false,'error'=>'Ukuran file maksimal 2MB'], 400);
  $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ALLOWED_EXT, true)) out(['ok'=>false,'error'=>'Format file tidak didukung'], 400);

  if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
  $name = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
  $dest = UPLOAD_DIR . $name;
  if (!move_uploaded_file($f['tmp_name'], $dest)) out(['ok'=>false,'error'=>'Gagal simpan file'], 500);

  if ($oldPath && substr($oldPath, 0, strlen('uploads/sponsor/')) === 'uploads/sponsor/') {
    $old = __DIR__ . '/' . $oldPath;
    if (is_file($old)) @unlink($old);
  }
  return 'uploads/sponsor/' . $name;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
  out(['ok'=>true,'items'=>read_items()]);
}

if ($action === 'save') {
  require_admin();
  $items = read_items();
  $new = [];

  for ($i=0; $i<6; $i++) {
    $name = trim((string)($_POST['name_'.$i] ?? ''));
    $url  = trim((string)($_POST['url_'.$i] ?? ''));
    $oldImage = trim((string)($_POST['old_image_'.$i] ?? ''));
    $fromExisting = $items[$i]['image'] ?? '';
    $baseImage = $oldImage !== '' ? $oldImage : $fromExisting;
    $uploaded = upload_if_any('image_'.$i, $baseImage ?: null);
    $image = $uploaded ?? $baseImage;

    if ($name === '' && $image === '' && $url === '') continue;
    if ($name === '') $name = 'Sponsor '.($i+1);
    $new[] = ['name'=>$name, 'image'=>$image, 'url'=>$url];
  }

  write_items($new);
  out(['ok'=>true,'count'=>count($new)]);
}

out(['ok'=>false,'error'=>'Action tidak dikenal'], 400);
