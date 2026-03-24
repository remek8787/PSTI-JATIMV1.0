<?php
// klub_api.php — PHP 5.6 compatible, CRUD + filter, dengan normalisasi "kabupaten"
session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

ini_set('display_errors', 0);
set_error_handler(function($errno,$errstr,$errfile,$errline){
  http_response_code(500);
  echo json_encode(array('ok'=>false,'error'=>"PHP $errno: $errstr @ ".basename($errfile).":$errline"));
  exit;
});
function fail($msg,$code=500){ http_response_code($code); echo json_encode(array('ok'=>false,'error'=>$msg)); exit; }
function postv($k){ return isset($_POST[$k]) ? trim($_POST[$k]) : ''; }

$DATA_FILE  = __DIR__ . '/data/klub.json';
$UPLOAD_DIR = __DIR__ . '/uploads/klub';

$DATA_DIR = dirname($DATA_FILE);
if (!is_dir($DATA_DIR) && !mkdir($DATA_DIR, 0777, true)) fail("Tidak bisa membuat folder data: $DATA_DIR");
if (!is_writable($DATA_DIR)) fail("Folder data tidak bisa ditulis: $DATA_DIR");
if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0777, true)) fail("Tidak bisa membuat folder upload: $UPLOAD_DIR");
if (!is_writable($UPLOAD_DIR)) fail("Folder upload tidak bisa ditulis: $UPLOAD_DIR");

function read_json($f){ if(!file_exists($f)) return array(); $j=file_get_contents($f); $a=json_decode($j,true); return is_array($a)?$a:array(); }
function write_json($f,$arr){
  $tmp=$f.'.tmp';
  $json=json_encode($arr, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if(file_put_contents($tmp,$json,LOCK_EX)===false) fail("Gagal tulis tmp");
  if(!rename($tmp,$f)) fail("Gagal rename data");
}
function find_idx(&$arr,$id){ for($i=0;$i<count($arr);$i++){ if(isset($arr[$i]['id'])&&$arr[$i]['id']===$id) return $i; } return -1; }
function sanitize_name($s){ return preg_replace('/[^a-zA-Z0-9_\.\-]/','_', $s); }
function save_upload($field,$prefix){
  if (!isset($_FILES[$field]) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return null;
  if ($_FILES[$field]['error']!==UPLOAD_ERR_OK) fail("Upload error ($field): code ".$_FILES[$field]['error']);
  $tmp=$_FILES[$field]['tmp_name']; $name=sanitize_name(basename($_FILES[$field]['name']));
  $ext=strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if(!in_array($ext,array('jpg','jpeg','png','webp'))) fail("Ekstensi tidak didukung: .$ext");
  $dest=$GLOBALS['UPLOAD_DIR'].'/'.$prefix.'_'.time().'.'.$ext;
  if (!move_uploaded_file($tmp,$dest)) fail("Gagal memindahkan upload ke: $dest");
  return 'uploads/klub/'.basename($dest);
}
// auth (opsional)
function need_admin(){
  if (empty($_SESSION['admin_pengurus']) && empty($_SESSION['admin'])) {
    fail('Unauthorized', 401);
  }
}
function gen_id($len=4){
  if (function_exists('random_bytes')) $b = random_bytes($len);
  elseif (function_exists('openssl_random_pseudo_bytes')) $b = openssl_random_pseudo_bytes($len);
  else { $b=''; for($i=0;$i<$len;$i++) $b.=chr(mt_rand(0,255)); }
  return strtoupper(bin2hex($b));
}

/* ===== Normalisasi kabupaten/kota ke bentuk kanonik =====
   "Kab, Malang" / "kab malang" / "Malang" -> "Kabupaten Malang"
   "kota surabaya" -> "Kota Surabaya" */
function normalize_kab($s){
  $t = trim(str_replace(array(',',':'), ' ', (string)$s));
  $t = preg_replace('/\s+/', ' ', $t);
  if ($t === '') return '';
  if (preg_match('/^(kab(?:\.|upaten)?)\s+/i', $t)) {
    $t = 'Kabupaten ' . preg_replace('/^(kab(?:\.|upaten)?)\s+/i', '', $t);
  } elseif (preg_match('/^(kota|kotamadya|kodya)\s+/i', $t)) {
    $t = 'Kota ' . preg_replace('/^(kota|kotamadya|kodya)\s+/i', '', $t);
  } else {
    $t = 'Kabupaten ' . $t;
  }
  $t = strtolower($t);
  $parts = explode(' ', $t);
  for($i=0;$i<count($parts);$i++){
    $w = $parts[$i];
    $parts[$i] = $w!=='' ? strtoupper($w[0]).substr($w,1) : $w;
  }
  return implode(' ', $parts);
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'list');
$data   = read_json($DATA_FILE);

switch($action){

  case 'list': {
    // filter: ?kabupaten=Kabupaten%20Malang&search=malang%20fc
    $kab = isset($_GET['kabupaten']) ? trim($_GET['kabupaten']) : '';
    $kabN = $kab!=='' ? normalize_kab($kab) : '';
    $q   = isset($_GET['search']) ? mb_strtolower(trim($_GET['search']), 'UTF-8') : '';

    $items = $data;

    // filter kabupaten pakai bentuk normalisasi supaya konsisten
    if ($kabN !== ''){
      $items = array_values(array_filter($items, function($it) use ($kabN){
        $cur = isset($it['kabupaten']) ? normalize_kab($it['kabupaten']) : '';
        return strcasecmp($cur, $kabN) === 0;
      }));
    }

    // search bebas
    if ($q !== ''){
      $items = array_values(array_filter($items, function($it) use ($q){
        $hay = mb_strtolower(implode(' ', array(
          isset($it['nama'])?$it['nama']:'',
          isset($it['kabupaten'])?$it['kabupaten']:'',
          isset($it['pemilik'])?$it['pemilik']:'',
          isset($it['alamat'])?$it['alamat']:'',
          isset($it['instagram'])?$it['instagram']:'',
          isset($it['telepon'])?$it['telepon']:''
        )), 'UTF-8');
        return strpos($hay, $q)!==false;
      }));
    }

    // urutkan: (kabupaten normalisasi) ASC, kemudian nama ASC
    usort($items, function($a,$b){
      $ak = normalize_kab(isset($a['kabupaten'])?$a['kabupaten']:'');
      $bk = normalize_kab(isset($b['kabupaten'])?$b['kabupaten']:'');
      $an = isset($a['nama'])?$a['nama']:'';
      $bn = isset($b['nama'])?$b['nama']:'';
      $c = strcasecmp($ak,$bk);
      return $c!==0 ? $c : strcasecmp($an,$bn);
    });

    echo json_encode(array('ok'=>true,'items'=>$items));
    break;
  }

  case 'get': {
    $id  = isset($_GET['id']) ? $_GET['id'] : '';
    $idx = find_idx($data,$id);
    if ($idx<0) fail('Not found',404);
    echo json_encode(array('ok'=>true,'item'=>$data[$idx]));
    break;
  }

  case 'create': {
    // need_admin();
    $id   = gen_id(4);
    $foto = save_upload('foto','kl_'.$id);
    $rec = array(
      'id'         => $id,
      'created_at' => date('Y-m-d H:i:s'),
      'kabupaten'  => normalize_kab(postv('kabupaten')), // <- NORMALISASI DI SINI
      'nama'       => postv('nama'),
      'pemilik'    => postv('pemilik'),
      'telepon'    => postv('telepon'),
      'alamat'     => postv('alamat'),
      'instagram'  => postv('instagram'),
      'foto'       => $foto
    );
    if ($rec['kabupaten']==='' || $rec['nama']==='') fail('Kabupaten & Nama wajib',400);
    $data[]=$rec; write_json($DATA_FILE,$data);
    echo json_encode(array('ok'=>true,'id'=>$id)); break;
  }

  case 'update': {
    // need_admin();
    $id  = isset($_POST['id']) ? $_POST['id'] : '';
    $idx = find_idx($data,$id);
    if ($idx<0) fail('Not found',404);

    // field umum
    foreach(array('nama','pemilik','telepon','alamat','instagram') as $f){
      if (isset($_POST[$f])) $data[$idx][$f] = trim($_POST[$f]);
    }
    // kabupaten dinormalisasi
    if (isset($_POST['kabupaten'])) $data[$idx]['kabupaten'] = normalize_kab($_POST['kabupaten']);

    $new = save_upload('foto','kl_'.$id);
    if ($new) $data[$idx]['foto']=$new;

    $data[$idx]['updated_at']=date('Y-m-d H:i:s');
    write_json($DATA_FILE,$data);
    echo json_encode(array('ok'=>true,'id'=>$id)); break;
  }

  case 'delete': {
    // need_admin();
    $id  = isset($_POST['id']) ? $_POST['id'] : '';
    $idx = find_idx($data,$id);
    if ($idx<0) fail('Not found',404);
    if (!empty($data[$idx]['foto'])){
      $p = __DIR__.'/'.$data[$idx]['foto'];
      if (file_exists($p) && strpos(realpath($p), realpath(__DIR__.'/uploads/klub'))===0) @unlink($p);
    }
    array_splice($data,$idx,1); write_json($DATA_FILE,$data);
    echo json_encode(array('ok'=>true)); break;
  }

  default: fail('Unknown action',400);
}
