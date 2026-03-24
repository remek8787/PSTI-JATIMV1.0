<?php
// auth_api.php
session_start();
header('Content-Type: application/json; charset=utf-8');

$USERS = [
  ['u' => 'admin',       'p' => 'admin123'],   // <<< ganti sesuai kebutuhan
  ['u' => 'sekretariat', 'p' => 'psti2025']    // bisa tambah baris lain
];

$action = $_GET['action'] ?? $_POST['action'] ?? 'me';

function is_valid($u, $p, $list){
  foreach ($list as $row) if ($row['u']===$u && $row['p']===$p) return true;
  return false;
}

if ($action === 'login') {
  $u = $_POST['username'] ?? '';
  $p = $_POST['password'] ?? '';
  if (is_valid($u, $p, $USERS)) {
    $_SESSION['admin'] = true;
    $_SESSION['user']  = $u;
    echo json_encode(['ok'=>true,'user'=>$u]); exit;
  }
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Salah username/password']); exit;
}

if ($action === 'logout') {
  $_SESSION = []; session_destroy();
  echo json_encode(['ok'=>true]); exit;
}

if ($action === 'me') {
  echo json_encode(['ok'=>true,'admin'=>!empty($_SESSION['admin']), 'user'=>($_SESSION['user']??null)]); exit;
}

http_response_code(400);
echo json_encode(['ok'=>false,'error'=>'Unknown action']);
