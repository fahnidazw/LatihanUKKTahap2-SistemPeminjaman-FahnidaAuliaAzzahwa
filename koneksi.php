<?php
// Koneksi database + helper umum
if (session_status() === PHP_SESSION_NONE) session_start();
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli('localhost', 'root', '', 'db_peminjaman');
if ($db->connect_error) die('Koneksi gagal: ' . $db->connect_error);
$db->set_charset('utf8mb4');
define('BASE', '/latiha-ukk2/');

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah($n) { return 'Rp ' . number_format((int)$n, 0, ',', '.'); }
function flash($m, $t = 'ok') { $_SESSION['flash'] = [$m, $t]; }
function go($u = '') { header('Location: ' . ($u ?: $_SERVER['PHP_SELF'])); exit; }

// Query aman (prepared statement). SELECT -> mysqli_result, lainnya -> true, gagal -> false
function q($sql, $types = '', ...$p) {
    global $db;
    $s = $db->prepare($sql);
    if (!$s) return false;
    if ($types) $s->bind_param($types, ...$p);
    if (!$s->execute()) return false;
    $r = $s->get_result();
    return $r === false ? true : $r;
}
// Proteksi halaman per level
function auth($level) {
    if (empty($_SESSION['u']) || $_SESSION['u']['level'] !== $level) go(BASE . 'index.php');
}
function logact($aksi) {
    $id = $_SESSION['u']['id_user'] ?? null;
    q("INSERT INTO log_aktivitas(id_user,aksi) VALUES(?,?)", 'is', $id, $aksi);
}
