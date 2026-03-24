<?php
/**
 * berita_api.php — PSTI Jatim
 * Penyimpanan: data/berita.json  (array of items)
 * Upload banner: uploads/berita/
 * Skema item:
 * { id, title, excerpt, source_url, source_name, published_at, pinned(0/1), banner }
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
date_default_timezone_set('Asia/Jakarta');
session_start();

// --- Konfigurasi ---
const DATA_FILE   = __DIR__ . '/data/berita.json';
const UPLOAD_DIR  = __DIR__ . '/uploads/berita/';
const MAX_UPLOAD  = 2 * 1024 * 1024; // 2MB
const ALLOWED_EXT = ['jpg','jpeg','png','webp'];

// (opsional) base URL kalau mau jadikan URL absolut untuk banner.
// Kosongkan untuk path relatif.
// const BASE_URL = 'https://pstijatim.com/';
const BASE_URL = '';

// --- Util kecil ---
function ok($data = []) {
  http_response_code(200);
  echo json_encode(['ok'=>true] + $data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function fail($msg, $code=400) {
  http_response_code($code);
  echo json_encode(['ok'=>false, 'error'=>$msg], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function rm_bom(string $s): string {
  return (substr($s,0,3)==="\xEF\xBB\xBF") ? substr($s,3) : $s;
}
function read_all(): array {
  if (!file_exists(DATA_FILE)) return [];
  $raw = rm_bom(file_get_contents(DATA_FILE));
  if ($raw === '') return [];
  $j = json_decode($raw, true);
  if (!is_array($j)) return [];
  return $j;
}
function write_all(array $arr): void {
  if (!is_dir(dirname(DATA_FILE))) mkdir(dirname(DATA_FILE), 0775, true);
  $tmp = DATA_FILE . '.tmp';
  $data = json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
  file_put_contents($tmp, $data, LOCK_EX);
  rename($tmp, DATA_FILE); // atomic di FS UNIX
}
function next_id(array $arr): int {
  $max = 0;
  foreach ($arr as $it) $max = max($max, intval($it['id'] ?? 0));
  return $max + 1;
}
function val_str($key, $default=''): string {
  return isset($_POST[$key]) ? trim((string)$_POST[$key]) : (isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default);
}
function val_int($key, $default=0): int {
  $v = val_str($key, (string)$default);
  return (int)$v;
}
function val_bool01($key): int {
  $v = val_str($key, '');
  if ($v === '' && isset($_POST[$key])) $v = $_POST[$key]; // handle checkbox 'on'
  return in_array((string)$v, ['1','true','on'], true) ? 1 : 0;
}
function to_abs_or_rel(string $path): string {
  if ($path==='' ) return '';
  if (BASE_URL) return rtrim(BASE_URL,'/').'/'.ltrim($path,'/');
  return $path;
}
function require_admin(): void {
  if (empty($_SESSION['admin_pengurus'])) {
    fail('Unauthorized', 401);
  }
}

// --- Upload banner ---
function handle_upload(?string $oldPath = null): ?string {
  if (!isset($_FILES['banner']) || !is_uploaded_file($_FILES['banner']['tmp_name'])) {
    return null; // tidak ada file baru
  }
  $f = $_FILES['banner'];
  if ($f['error'] !== UPLOAD_ERR_OK) fail('Upload gagal (code '.$f['error'].')', 400);
  if ($f['size'] > MAX_UPLOAD) fail('Ukuran file melebihi 2MB', 400);

  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ALLOWED_EXT, true)) fail('Format file tidak didukung (jpg, jpeg, png, webp)', 400);

  if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);

  // nama unik
  $name = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
  $dest = UPLOAD_DIR . $name;

  if (!move_uploaded_file($f['tmp_name'], $dest)) fail('Gagal menyimpan file', 500);

  // hapus lama bila ada & berbeda
  if ($oldPath) {
    $old = __DIR__ . '/' . ltrim($oldPath,'/');
    if (is_file($old)) @unlink($old);
  }

  // kembalikan path relatif untuk dipakai di <img src="">
  $rel = 'uploads/berita/' . $name;
  return $rel;
}

// --- Normalisasi item ---
function normalize_item(array $it): array {
  $it['id']           = intval($it['id'] ?? 0);
  $it['title']        = trim((string)($it['title'] ?? ''));
  $it['excerpt']      = trim((string)($it['excerpt'] ?? ''));
  $it['source_url']   = trim((string)($it['source_url'] ?? ''));
  $it['source_name']  = trim((string)($it['source_name'] ?? ''));
  $it['published_at'] = trim((string)($it['published_at'] ?? '')); // 'YYYY-mm-dd HH:ii:ss' atau kosong
  $it['pinned']       = intval($it['pinned'] ?? 0);
  $it['banner']       = trim((string)($it['banner'] ?? ''));
  return $it;
}

// --- Routing ---
$action = val_str('action', 'list');

try {
  if ($action === 'list') {
    $all = array_map('normalize_item', read_all());

    // filter
    $search = val_str('search', '');
    $pinned = val_str('pinned', ''); // '', '0', '1'
    if ($search !== '') {
      $q = mb_strtolower($search);
      $all = array_values(array_filter($all, function($it) use ($q){
        $hay = mb_strtolower(
          ($it['title'] ?? '').' '.($it['excerpt'] ?? '').' '.($it['source_name'] ?? '')
        );
        return strpos($hay, $q) !== false;
      }));
    }
    if ($pinned !== '' && ($pinned==='0' || $pinned==='1')) {
      $all = array_values(array_filter($all, function($it) use ($pinned) { return (string)$it['pinned'] === $pinned; }));
    }

    // sort: pinned desc, published_at desc, id desc
    usort($all, function($a,$b){
      if (($b['pinned']??0) !== ($a['pinned']??0)) return ($b['pinned']??0) <=> ($a['pinned']??0);
      $ta = strtotime($a['published_at'] ?? '') ?: 0;
      $tb = strtotime($b['published_at'] ?? '') ?: 0;
      if ($tb !== $ta) return $tb <=> $ta;
      return ($b['id']??0) <=> ($a['id']??0);
    });

    // paging
    $limit = max(0, val_int('limit', 0));
    $offset= max(0, val_int('offset', 0));
    $total = count($all);
    if ($limit > 0) {
      $all = array_slice($all, $offset, $limit);
    }

    // jadikan path banner absolut (opsional)
    foreach ($all as &$it) { if (!empty($it['banner'])) $it['banner'] = to_abs_or_rel($it['banner']); }

    ok(['items'=>$all, 'total'=>$total]);
  }

  if ($action === 'get') {
    $id = val_int('id', 0);
    if ($id<=0) fail('ID wajib diisi');
    $all = array_map('normalize_item', read_all());
    foreach ($all as $it) {
      if (intval($it['id']) === $id) {
        if (!empty($it['banner'])) $it['banner'] = to_abs_or_rel($it['banner']);
        ok(['item'=>$it]);
      }
    }
    fail('Data tidak ditemukan', 404);
  }

  if ($action === 'create') {
    $all = read_all();
    $id  = next_id($all);

    $item = [
      'id'           => $id,
      'title'        => val_str('title'),
      'excerpt'      => val_str('excerpt'),
      'source_url'   => val_str('source_url'),
      'source_name'  => val_str('source_name'),
      'published_at' => str_replace('T',' ', val_str('published_at')),
      'pinned'       => val_bool01('pinned'),
      'banner'       => ''
    ];

    // upload banner jika ada
    $newBanner = handle_upload(null);
    if ($newBanner !== null) $item['banner'] = $newBanner;

    $all[] = normalize_item($item);
    write_all($all);

    ok(['id'=>$id]);
  }

  if ($action === 'update') {
    require_admin();
    $id = val_int('id', 0);
    if ($id<=0) fail('ID wajib diisi');

    $all = read_all();
    $found = false;

    foreach ($all as &$it) {
      if (intval($it['id']) === $id) {
        $found = true;

        $it['title']        = val_str('title',        $it['title'] ?? '');
        $it['excerpt']      = val_str('excerpt',      $it['excerpt'] ?? '');
        $it['source_url']   = val_str('source_url',   $it['source_url'] ?? '');
        $it['source_name']  = val_str('source_name',  $it['source_name'] ?? '');
        $p                  = val_str('published_at', $it['published_at'] ?? '');
        $it['published_at'] = str_replace('T',' ', $p);
        $it['pinned']       = val_bool01('pinned');

        // ganti banner jika ada upload baru
        $newBanner = handle_upload($it['banner'] ?? null);
        if ($newBanner !== null) $it['banner'] = $newBanner;

        $it = normalize_item($it);
        break;
      }
    }
    if (!$found) fail('Data tidak ditemukan', 404);

    write_all($all);
    ok(['id'=>$id]);
  }

  if ($action === 'delete') {
    // Terima id dari POST (form-data atau JSON)
    $id = 0;
    if (isset($_POST['id'])) $id = intval($_POST['id']);
    if (!$id) {
      // JSON body?
      $raw = file_get_contents('php://input');
      if ($raw) { $j = json_decode($raw, true); if (isset($j['id'])) $id = intval($j['id']); }
    }
    if ($id<=0) fail('ID wajib diisi');

    $all = read_all();
    $new = [];
    $deletedBanner = null;
    foreach ($all as $it) {
      if (intval($it['id']) === $id) {
        $deletedBanner = $it['banner'] ?? null;
        continue; // skip (hapus)
      }
      $new[] = $it;
    }
    if (count($new) === count($all)) fail('Data tidak ditemukan', 404);

    write_all($new);

    // hapus file banner fisik (opsional)
    if ($deletedBanner) {
      $p = __DIR__ . '/' . ltrim($deletedBanner,'/');
      if (is_file($p)) @unlink($p);
    }

    ok(['id'=>$id]);
  }

  // aksi tak dikenal
  fail('Action tidak dikenal', 400);

} catch (Throwable $e) {
  // Jangan bocorkan stack trace ke user
  fail('Terjadi kesalahan server', 500);
}
