<?php
// auth_pengurus.php — login super simple (tanpa hashing)
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

function out($arr,$code=200){ http_response_code($code); echo json_encode($arr); exit; }

$USERS = [
  ['u'=>'admin_pengurus','p'=>'pstiPengurus123'],
  ['u'=>'sekretariat','p'=>'psti2025'],
  // Tambah akun lain di sini
];

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'me');

if ($action === 'login') {
  $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
  $p = trim(isset($_POST['password']) ? $_POST['password'] : '');
  foreach ($USERS as $row) {
    if ($row['u'] === $u && $row['p'] === $p) {
      $_SESSION['admin_pengurus'] = true;
      $_SESSION['user_pengurus']  = $u;
      out(['ok'=>true,'user'=>$u]);
    }
  }
  out(['ok'=>false,'error'=>'Username / password salah'], 401);
}

if ($action === 'logout') {
  unset($_SESSION['admin_pengurus'], $_SESSION['user_pengurus']);
  out(['ok'=>true]);
}

if ($action === 'me') {
  $is = !empty($_SESSION['admin_pengurus']);
  out(['ok'=>true,'admin'=>$is,'user'=>($is ? ($_SESSION['user_pengurus'] ?? '') : '')]);
}

out(['ok'=>false,'error'=>'Unknown action'],400);
