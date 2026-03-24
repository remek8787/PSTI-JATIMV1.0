<?php
// pelatih_api.php
session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');

// ---- suppress HTML warnings, balas JSON saja
ini_set('display_errors', 0);
set_error_handler(function($errno,$errstr,$errfile,$errline){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>"PHP $errno: $errstr @ ".basename($errfile).":$errline"]);
  exit;
});
function fail($msg,$code=500){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

$DATA_FILE  = __DIR__.'/data/pelatih.json';
$UPLOAD_DIR = __DIR__.'/uploads/pelatih';

// pastikan folder
$DATA_DIR = dirname($DATA_FILE);
if (!is_dir($DATA_DIR) && !mkdir($DATA_DIR,0777,true)) fail("Tidak bisa membuat folder data");
if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR,0777,true)) fail("Tidak bisa membuat folder upload");

function read_json($file){
  if (!file_exists($file)) return [];
  $j = file_get_contents($file);
  $a = json_decode($j,true);
  return is_array($a)?$a:[];
}
function write_json($file,$arr){
  $tmp = $file.'.tmp';
  if (file_put_contents($tmp, json_encode($arr,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX) === false)
    fail("Gagal menulis file sementara");
  if (!rename($tmp,$file)) fail("Gagal mengganti file data");
}
function idx_of(&$arr,$id){ foreach($arr as $i=>$r){ if(($r['id']??'')===$id) return $i; } return -1; }
function sanitize($s){ return preg_replace('/[^a-zA-Z0-9_\.\-]/','_', $s); }
function save_upload($field,$prefix){
  if (!isset($_FILES[$field]) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE) return null;
  if ($_FILES[$field]['error']!==UPLOAD_ERR_OK) fail("Upload error ($field): code ".$_FILES[$field]['error']);
  $tmp = $_FILES[$field]['tmp_name'];
  $name= sanitize(basename($_FILES[$field]['name']));
  $ext = strtolower(pathinfo($name,PATHINFO_EXTENSION));
  if (!in_array($ext,['jpg','jpeg','png','webp'])) fail("Ekstensi tidak didukung: .$ext");
  $dest = $GLOBALS['UPLOAD_DIR'].'/'.$prefix.'_'.time().'.'.$ext;
  if (!move_uploaded_file($tmp,$dest)) fail("Gagal memindahkan upload");
  return 'uploads/pelatih/'.basename($dest);
}
// --- penting: terima admin dari login pengurus
function need_admin(){
  if (empty($_SESSION['admin_pengurus']) && empty($_SESSION['admin'])) {
    fail('Unauthorized', 401);
  }
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$data   = read_json($DATA_FILE);

switch ($action){

  case 'list':
    // urut terbaru
    usort($data, function($a,$b){
      return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    echo json_encode(['ok'=>true,'items'=>$data]);
    break;

  case 'get':
    $id = $_GET['id'] ?? '';
    $i  = idx_of($data,$id);
    if ($i<0) fail('Not found',404);
    echo json_encode(['ok'=>true,'item'=>$data[$i]]);
    break;

  case 'create':
    need_admin();
    $id = strtoupper(bin2hex(random_bytes(4)));
    $foto = save_upload('foto','pl_'.$id);
    $rec = [
      'id'=>$id, 'created_at'=>date('Y-m-d H:i:s'),
      'nama'=>trim($_POST['nama']??''),
      'jk'=>trim($_POST['jk']??''),
      'ttl'=>trim($_POST['ttl']??''),
      'usia'=>trim($_POST['usia']??''),
      'tinggi'=>trim($_POST['tinggi']??''),
      'berat'=>trim($_POST['berat']??''),
      'alamat'=>trim($_POST['alamat']??''),
      'pendidikan'=>trim($_POST['pendidikan']??''),
      'program_studi'=>trim($_POST['program_studi']??''),
      'sekolah'=>trim($_POST['sekolah']??''),
      'cabor'=>trim($_POST['cabor']??''),
      'telepon'=>trim($_POST['telepon']??''),
      'lisensi'=>trim($_POST['lisensi']??''),
      'sertifikat'=>array_values(array_filter($_POST['sertifikat'] ?? [], 'strlen')),
      'tahun_sertifikat'=>array_values(array_filter($_POST['tahun_sertifikat'] ?? [], 'strlen')),
      'pengalaman'=>array_values(array_filter($_POST['pengalaman'] ?? [], 'strlen')),
      'tahun_pengalaman'=>array_values(array_filter($_POST['tahun_pengalaman'] ?? [], 'strlen')),
      'prestasi'=>array_values(array_filter($_POST['prestasi'] ?? [], 'strlen')),
      'tahun_prestasi'=>array_values(array_filter($_POST['tahun_prestasi'] ?? [], 'strlen')),
      'foto'=>$foto
    ];
    if ($rec['nama']==='') fail('Nama pelatih wajib',400);
    $data[] = $rec; write_json($DATA_FILE,$data);
    echo json_encode(['ok'=>true,'id'=>$id]);
    break;

  case 'update':
    need_admin();
    $id = $_POST['id'] ?? '';
    $i  = idx_of($data,$id);
    if ($i<0) fail('Not found',404);
    foreach(['nama','jk','ttl','usia','tinggi','berat','alamat','pendidikan','program_studi','sekolah','cabor','telepon','lisensi'] as $f){
      if (isset($_POST[$f])) $data[$i][$f] = trim($_POST[$f]);
    }
    $data[$i]['sertifikat'] = array_values(array_filter($_POST['sertifikat'] ?? [], 'strlen'));
    $data[$i]['tahun_sertifikat'] = array_values(array_filter($_POST['tahun_sertifikat'] ?? [], 'strlen'));
    $data[$i]['pengalaman'] = array_values(array_filter($_POST['pengalaman'] ?? [], 'strlen'));
    $data[$i]['tahun_pengalaman'] = array_values(array_filter($_POST['tahun_pengalaman'] ?? [], 'strlen'));
    $data[$i]['prestasi'] = array_values(array_filter($_POST['prestasi'] ?? [], 'strlen'));
    $data[$i]['tahun_prestasi'] = array_values(array_filter($_POST['tahun_prestasi'] ?? [], 'strlen'));

    if ($new = save_upload('foto','pl_'.$id)) $data[$i]['foto'] = $new;
    $data[$i]['updated_at'] = date('Y-m-d H:i:s');
    write_json($DATA_FILE,$data);
    echo json_encode(['ok'=>true,'id'=>$id]);
    break;

  case 'delete':
    need_admin();
    $id = $_POST['id'] ?? '';
    $i  = idx_of($data,$id);
    if ($i<0) fail('Not found',404);
    if (!empty($data[$i]['foto'])){
      $p = __DIR__.'/'.$data[$i]['foto'];
      if (strpos(realpath($p), realpath(__DIR__.'/uploads/pelatih'))===0 && file_exists($p)) @unlink($p);
    }
    array_splice($data,$i,1);
    write_json($DATA_FILE,$data);
    echo json_encode(['ok'=>true]);
    break;

  default:
    fail('Unknown action',400);
}
