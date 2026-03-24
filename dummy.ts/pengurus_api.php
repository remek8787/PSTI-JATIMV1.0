<?php
// pengurus_api.php — kompatibel PHP 5.6 (tanpa arrow / ??), dengan field "jabatan"
session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

// Error -> JSON
ini_set('display_errors', 0);
set_error_handler(function($errno,$errstr,$errfile,$errline){
  http_response_code(500);
  echo json_encode(array('ok'=>false,'error'=>"PHP $errno: $errstr @ ".basename($errfile).":$errline"));
  exit;
});
function fail($msg,$code=500){ http_response_code($code); echo json_encode(array('ok'=>false,'error'=>$msg)); exit; }

$DATA_FILE  = __DIR__ . '/data/pengurus.json';
$UPLOAD_DIR = __DIR__ . '/uploads/pengurus';

// Pastikan folder ada & bisa ditulis
$DATA_DIR = dirname($DATA_FILE);
if (!is_dir($DATA_DIR) && !mkdir($DATA_DIR, 0777, true)) fail("Tidak bisa membuat folder data: $DATA_DIR");
if (!is_writable($DATA_DIR)) fail("Folder data tidak bisa ditulis: $DATA_DIR");
if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0777, true)) fail("Tidak bisa membuat folder upload: $UPLOAD_DIR");
if (!is_writable($UPLOAD_DIR)) fail("Folder upload tidak bisa ditulis: $UPLOAD_DIR");

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
// ID generator kompatibel
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

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'list');
$data   = read_json($DATA_FILE);

switch($action){

  case 'list': {
    usort($data, function($a,$b){
      $ac = isset($a['created_at']) ? $a['created_at'] : '';
      $bc = isset($b['created_at']) ? $b['created_at'] : '';
      return strcmp($bc, $ac); // terbaru dulu
    });
    echo json_encode(array('ok'=>true,'items'=>$data));
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
    need_admin();
    $id   = gen_id(4);
    $foto = save_upload('foto','pg_'.$id);
    $rec = array(
      'id'          => $id,
      'created_at'  => date('Y-m-d H:i:s'),
      'nama'        => isset($_POST['nama']) ? trim($_POST['nama']) : '',
      'alamat'      => isset($_POST['alamat']) ? trim($_POST['alamat']) : '',
      'telepon'     => isset($_POST['telepon']) ? trim($_POST['telepon']) : '',
      'license'     => isset($_POST['license']) ? trim($_POST['license']) : '',
      'jabatan'     => isset($_POST['jabatan']) ? trim($_POST['jabatan']) : '',   // <— baru
      'pengalaman'  => isset($_POST['pengalaman']) ? trim($_POST['pengalaman']) : '',
      'pendidikan'  => isset($_POST['pendidikan']) ? trim($_POST['pendidikan']) : '',
      'sertifikat'  => isset($_POST['sertifikat']) ? trim($_POST['sertifikat']) : '',
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
    foreach(array('nama','alamat','telepon','license','jabatan','pengalaman','pendidikan','sertifikat') as $f){
      if (isset($_POST[$f])) $data[$idx][$f] = trim($_POST[$f]);
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
