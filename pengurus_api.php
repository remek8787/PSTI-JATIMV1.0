<?php
// pengurus_api.php — PHP 5.6 compatible, simpan ke JSON (tanpa DB)
// Tambahan: kategori, kat_order (urutan kategori), urut (di dalam kategori)

session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

// === Error → JSON ===
ini_set('display_errors', 0);
set_error_handler(function($errno,$errstr,$errfile,$errline){
  http_response_code(500);
  echo json_encode(array('ok'=>false,'error'=>"PHP $errno: $errstr @ ".basename($errfile).":$errline"));
  exit;
});
function fail($msg,$code=500){ http_response_code($code); echo json_encode(array('ok'=>false,'error'=>$msg)); exit; }

// === Paths ===
$DATA_FILE  = __DIR__ . '/data/pengurus.json';
$UPLOAD_DIR = __DIR__ . '/uploads/pengurus';

// Pastikan folder ada & bisa ditulis
$DATA_DIR = dirname($DATA_FILE);
if (!is_dir($DATA_DIR) && !mkdir($DATA_DIR, 0777, true)) fail("Tidak bisa membuat folder data: $DATA_DIR");
if (!is_writable($DATA_DIR)) fail("Folder data tidak bisa ditulis: $DATA_DIR");
if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0777, true)) fail("Tidak bisa membuat folder upload: $UPLOAD_DIR");
if (!is_writable($UPLOAD_DIR)) fail("Folder upload tidak bisa ditulis: $UPLOAD_DIR");

// === Util JSON & Files ===
function read_json($file){
  if (!file_exists($file)) return array();
  $j = file_get_contents($file);
  $a = json_decode($j, true);
  return is_array($a) ? $a : array();
}
function write_json($file,$arr){
  $tmp=$file.'.tmp';
  $json = json_encode($arr, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if (file_put_contents($tmp, $json, LOCK_EX) === false) fail("Gagal menulis file sementara: $tmp");
  if (!rename($tmp, $file)) fail("Gagal mengganti file data: $file");
}
function find_idx(&$arr,$id){
  for($i=0;$i<count($arr);$i++){ if (isset($arr[$i]['id']) && $arr[$i]['id']===$id) return $i; }
  return -1;
}
function sanitize_name($s){ return preg_replace('/[^a-zA-Z0-9_\.\-]/','_', $s); }
function save_upload($field,$prefix){
  if (!isset($_FILES[$field]) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return null;
  if ($_FILES[$field]['error']!==UPLOAD_ERR_OK) fail("Upload error ($field): code ".$_FILES[$field]['error']);
  $tmp=$_FILES[$field]['tmp_name']; $name=sanitize_name(basename($_FILES[$field]['name']));
  $ext=strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if(!in_array($ext,array('jpg','jpeg','png','webp'))) fail("Ekstensi tidak didukung: .$ext");
  $dest=$GLOBALS['UPLOAD_DIR'].'/'.$prefix.'_'.time().'.'.$ext;
  if (!move_uploaded_file($tmp,$dest)) fail("Gagal memindahkan upload ke: $dest");
  return 'uploads/pengurus/'.basename($dest);
}
function need_admin(){
  if (empty($_SESSION['admin_pengurus']) && empty($_SESSION['admin'])) {
    fail('Unauthorized', 401);
  }
}
// Helper ambil POST (trim) — PHP 5.6 kompatibel
function postv($k){ return isset($_POST[$k]) ? trim($_POST[$k]) : ''; }

// === ID generator ===
function gen_id($len=4){
  if (function_exists('random_bytes')) {
    $b = random_bytes($len);
  } elseif (function_exists('openssl_random_pseudo_bytes')) {
    $b = openssl_random_pseudo_bytes($len);
  } else {
    $b = '';
    for($i=0;$i<$len;$i++) $b .= chr(mt_rand(0,255));
  }
  return strtoupper(bin2hex($b));
}

// === Kategori Helpers ===
function cat_order_index($name){
  // urutan default kategori
  static $CAT_ORDER = array('Pelindung','Pembina','Penasehat','Ketua Umum','Wakil Ketua','Sekretaris','Bendahara','Bidang','Lainnya');
  $idx = array_search($name, $CAT_ORDER);
  return ($idx===false) ? 98 /* sebelum 99 */ : ($idx+1);
}
function infer_kategori_from_jabatan($jabatan){
  $maps = array(
    array('/pelindung/i','Pelindung'),
    array('/pembina/i','Pembina'),
    array('/penasihat|penasehat/i','Penasehat'),
    array('/ketua\s*umum/i','Ketua Umum'),
    array('/wakil|wkl\.?\s*ketua/i','Wakil Ketua'),
    array('/sekretaris/i','Sekretaris'),
    array('/bendahara/i','Bendahara'),
    array('/bidang|koor(dinator)?/i','Bidang'),
  );
  foreach($maps as $m){
    if (preg_match($m[0], $jabatan)) return $m[1];
  }
  return 'Lainnya';
}

// Normalisasi/migrasi:
// 1) copy 'pengalaman' -> 'biografi' bila biografi kosong
// 2) set default kategori/kat_order/urut bila kosong (tebak dari jabatan bila perlu)
function normalize_records(&$arr){
  $changed = false;
  for ($i=0; $i<count($arr); $i++){
    if (!isset($arr[$i]) || !is_array($arr[$i])) continue;

    // 1) migrasi biografi
    $bio = isset($arr[$i]['biografi']) ? $arr[$i]['biografi'] : '';
    $old = isset($arr[$i]['pengalaman']) ? $arr[$i]['pengalaman'] : '';
    if ($bio === '' && $old !== '') {
      $arr[$i]['biografi'] = $old;
      unset($arr[$i]['pengalaman']);
      $changed = true;
    }

    // 2) kategori
    $jab = isset($arr[$i]['jabatan']) ? $arr[$i]['jabatan'] : '';
    if (!isset($arr[$i]['kategori']) || trim($arr[$i]['kategori'])==='') {
      $arr[$i]['kategori'] = infer_kategori_from_jabatan($jab);
      $changed = true;
    }
    if (!isset($arr[$i]['kat_order']) || $arr[$i]['kat_order']==='') {
      $arr[$i]['kat_order'] = cat_order_index($arr[$i]['kategori']);
      $changed = true;
    }
    if (!isset($arr[$i]['urut']) || $arr[$i]['urut']==='') {
      $arr[$i]['urut'] = 99;
      $changed = true;
    }
  }
  return $changed;
}

// === Actions ===
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'list');
$data   = read_json($DATA_FILE);

switch($action){

  case 'list': {
    if (normalize_records($data)) { write_json($DATA_FILE,$data); }
    // Urut: kat_order → kategori → urut → nama (fallback created_at desc)
    usort($data, function($a,$b){
      $ak = isset($a['kat_order']) ? intval($a['kat_order']) : 99;
      $bk = isset($b['kat_order']) ? intval($b['kat_order']) : 99;
      if ($ak !== $bk) return $ak - $bk;

      $acat = isset($a['kategori']) ? $a['kategori'] : '';
      $bcat = isset($b['kategori']) ? $b['kategori'] : '';
      $c = strcasecmp($acat,$bcat);
      if ($c !== 0) return $c;

      $au = isset($a['urut']) ? intval($a['urut']) : 99;
      $bu = isset($b['urut']) ? intval($b['urut']) : 99;
      if ($au !== $bu) return $au - $bu;

      $cn = strcasecmp(isset($a['nama'])?$a['nama']:'', isset($b['nama'])?$b['nama']:'');
      if ($cn !== 0) return $cn;

      // fallback agar stabil
      $ad = isset($a['created_at']) ? $a['created_at'] : '';
      $bd = isset($b['created_at']) ? $b['created_at'] : '';
      return strcmp($bd, $ad); // terbaru dulu
    });
    echo json_encode(array('ok'=>true,'items'=>$data));
    break;
  }

  case 'get': {
    if (normalize_records($data)) { write_json($DATA_FILE,$data); }
    $id  = isset($_GET['id']) ? $_GET['id'] : '';
    $idx = find_idx($data,$id);
    if ($idx<0) fail('Not found',404);
    echo json_encode(array('ok'=>true,'item'=>$data[$idx]));
    break;
  }

  case 'create': {
    need_admin();
    $id   = gen_id(4);
    $foto = save_upload('foto','pg_'.$id);

    // Ambil biografi dari 'biografi' (utama) atau fallback 'pengalaman'
    $bio = postv('biografi');
    if ($bio === '') $bio = postv('pengalaman');

    // Kategori & urutan (default kalau kosong)
    $kategori  = postv('kategori');
    if ($kategori==='') $kategori = infer_kategori_from_jabatan(postv('jabatan'));
    $kat_order = postv('kat_order'); $kat_order = ($kat_order!=='' ? intval($kat_order) : cat_order_index($kategori));
    $urut      = postv('urut');      $urut      = ($urut!=='' ? intval($urut) : 99);

    $rec = array(
      'id'          => $id,
      'created_at'  => date('Y-m-d H:i:s'),
      'nama'        => postv('nama'),
      'alamat'      => postv('alamat'),
      'telepon'     => postv('telepon'),
      'jabatan'     => postv('jabatan'),
      'kategori'    => $kategori,
      'kat_order'   => $kat_order,
      'urut'        => $urut,
      'biografi'    => $bio,
      'pendidikan'  => postv('pendidikan'),
      'sertifikat'  => postv('sertifikat'),
      'foto'        => $foto
    );
    if ($rec['nama']==='') fail('Nama wajib',400);
    $data[] = $rec; write_json($DATA_FILE,$data);
    echo json_encode(array('ok'=>true,'id'=>$id));
    break;
  }

  case 'update': {
    need_admin();
    $id  = isset($_POST['id']) ? $_POST['id'] : '';
    $idx = find_idx($data,$id);
    if ($idx<0) fail('Not found',404);

    // Update field-field umum
    foreach(array('nama','alamat','telepon','jabatan','pendidikan','sertifikat') as $f){
      if (isset($_POST[$f])) $data[$idx][$f] = trim($_POST[$f]);
    }

    // Kategori & urutan
    if (isset($_POST['kategori']))  $data[$idx]['kategori']  = trim($_POST['kategori']);
    if (isset($_POST['kat_order'])) $data[$idx]['kat_order'] = ($_POST['kat_order']===''? 99 : intval($_POST['kat_order']));
    if (isset($_POST['urut']))      $data[$idx]['urut']      = ($_POST['urut']===''? 99 : intval($_POST['urut']));

    // Jika kategori kosong setelah update → infer dari jabatan
    if (!isset($data[$idx]['kategori']) || trim($data[$idx]['kategori'])==='') {
      $data[$idx]['kategori'] = infer_kategori_from_jabatan(isset($data[$idx]['jabatan'])?$data[$idx]['jabatan']:'');
    }
    if (!isset($data[$idx]['kat_order']) || $data[$idx]['kat_order']==='') {
      $data[$idx]['kat_order'] = cat_order_index($data[$idx]['kategori']);
    }
    if (!isset($data[$idx]['urut']) || $data[$idx]['urut']==='') {
      $data[$idx]['urut'] = 99;
    }

    // Biografi (pakai key baru)
    if (isset($_POST['biografi'])) {
      $data[$idx]['biografi'] = trim($_POST['biografi']);
    } elseif (isset($_POST['pengalaman'])) {
      $data[$idx]['biografi'] = trim($_POST['pengalaman']);
      if (isset($data[$idx]['pengalaman'])) unset($data[$idx]['pengalaman']);
    }

    $new = save_upload('foto','pg_'.$id);
    if ($new) $data[$idx]['foto']=$new;

    $data[$idx]['updated_at']=date('Y-m-d H:i:s');
    write_json($DATA_FILE,$data);
    echo json_encode(array('ok'=>true,'id'=>$id));
    break;
  }

  case 'delete': {
    need_admin();
    $id  = isset($_POST['id']) ? $_POST['id'] : '';
    $idx = find_idx($data,$id);
    if ($idx<0) fail('Not found',404);
    if (!empty($data[$idx]['foto'])){
      $p = __DIR__.'/'.$data[$idx]['foto'];
      if (file_exists($p) && strpos(realpath($p), realpath(__DIR__.'/uploads/pengurus'))===0) @unlink($p);
    }
    array_splice($data,$idx,1); write_json($DATA_FILE,$data);
    echo json_encode(array('ok'=>true));
    break;
  }

  default: fail('Unknown action',400);
}
