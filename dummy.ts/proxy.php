<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
error_reporting(0);

$url = $_GET['url'] ?? '';
if (!$url || !preg_match('#^https?://#i', $url)) {
  echo json_encode(['error'=>'URL tidak valid']); exit;
}

$noCache = isset($_GET['nocache']);         // <-- tambah ini

/* ===== cache 24 jam ===== */
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
$key = sha1($url) . '.json';
$cacheFile = $cacheDir . '/' . $key;
$TTL = 86400;
if (!$noCache && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $TTL)) {
  readfile($cacheFile); exit;
}

/* ===== fetch via cURL ===== */
$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => $url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_USERAGENT => 'Mozilla/5.0 (PSTI Jatim Fetcher)',
  CURLOPT_SSL_VERIFYPEER => false, // untuk localhost
  CURLOPT_SSL_VERIFYHOST => false,
  CURLOPT_TIMEOUT => 12
]);
$html = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$html || $http >= 400) { echo json_encode(['error'=>'fetch_failed','http_code'=>$http]); exit; }

/* ===== helpers ===== */
function meta_tag($prop,$html){
  if (preg_match('/<meta[^>]+property=["\']'.preg_quote($prop,'/').'["\'][^>]+content=["\'](.*?)["\']/i',$html,$m)) return $m[1];
  if (preg_match('/<meta[^>]+name=["\']'.preg_quote($prop,'/').'["\'][^>]+content=["\'](.*?)["\']/i',$html,$m)) return $m[1];
  return '';
}
function clean_text($t){
  $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $t = strip_tags($t);
  $t = preg_replace('/\s+/u',' ',$t);
  return trim($t);
}
function excerpt($t,$limit=180){
  $t = clean_text($t);
  if ($t==='') return '';
  if (function_exists('mb_strlen')) {
    if (mb_strlen($t) <= $limit) return $t;
    $cut = mb_substr($t,0,$limit);
    $sp  = mb_strrpos($cut,' ');
    return mb_substr($cut,0,$sp?:$limit).'…';
  } else {
    if (strlen($t) <= $limit) return $t;
    $cut = substr($t,0,$limit);
    $sp  = strrpos($cut,' ');
    return substr($cut,0,$sp?:$limit).'…';
  }
}
/* paragraf pertama dari <article> / entry-content */
function first_paragraph($html){
  $n = preg_replace('#<(script|style|noscript)[^>]*>.*?</\1>#is','',$html);
  if (preg_match('#<article[^>]*>(.*?)</article>#is',$n,$a)) $n = $a[1];
  if (preg_match('#<div[^>]+(entry-content|post-content)[^>]*>(.*?)</div>#is',$n,$c)) $n = $c[2];
  if (preg_match('#<p[^>]*>(.*?)</p>#is',$n,$p)) return clean_text($p[1]);
  return '';
}

/* ===== parse ===== */
$title = meta_tag('og:title',$html);
if (!$title && preg_match('/<title>(.*?)<\/title>/si',$html,$m)) $title = $m[1];

$desc  = meta_tag('og:description',$html);
if (!$desc) $desc = meta_tag('description',$html);
if (!$desc) $desc = meta_tag('twitter:description',$html);
if (!$desc) $desc = first_paragraph($html);               // <-- fallback penting

$image = meta_tag('og:image',$html);
if (!$image) $image = meta_tag('og:image:secure_url',$html);
if (!$image) $image = meta_tag('twitter:image',$html);
if (!$image) $image = 'assets/contoh.jpg';

$out = [
  'judul'     => clean_text($title ?: 'Berita Eksternal'),
  'deskripsi' => excerpt($desc ?: 'Baca selengkapnya di sumber.'),
  'gambar'    => $image,
  'url'       => $url
];

$json = json_encode($out, JSON_UNESCAPED_UNICODE);
file_put_contents($cacheFile,$json);
echo $json;
